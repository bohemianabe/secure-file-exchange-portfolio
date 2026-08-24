<?php

namespace App\Security;

use App\Entity\Firms;
use App\Repository\FirmsRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

final class TenantLoginAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'tenant_login'; // your route name for "/{account}"

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private FirmsRepository $firmsRepository,
        private UserRepository $userRepository,
    ) {}

    public function supports(Request $request): bool
    {
        // same route handles GET and POST, but authenticator should only run on POST
        return $request->attributes->get('_route') === self::LOGIN_ROUTE
            && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {

        $account = (string) $request->attributes->get('account');
        $account = trim($account);

        if ($account === '') {
            throw new CustomUserMessageAuthenticationException('Invalid firm account.');
        }

        /** @var Firms|null $firm */
        $firm = $this->firmsRepository->findOneBy(['account' => $account]);

        if (!$firm) {
            // don’t reveal which accounts exist if you want to be more private
            throw new CustomUserMessageAuthenticationException('Invalid login link.');
        }
        // ag: to persist the tenant_account as a session variable so I can retrieve it in the twofa controller
        $request->getSession()->set('tenant_account', $account);


        $email = (string) $request->request->get('_username', '');
        $email = trim($email);

        if ($email === '') {
            throw new CustomUserMessageAuthenticationException('Please enter your email.');
        }

        // Save last username for the login form error page
        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        $csrfToken = (string) $request->request->get('_csrf_token');

        return new Passport(
            new UserBadge($email, function (string $userIdentifier) use ($firm) {
                $user = $this->userRepository->findOneBy(['email' => $userIdentifier]);

                // ag: keep from admin users from logging in the tenant page. needed for 2fa status.
                if (!$user) {
                    throw new CustomUserMessageAuthenticationException('Invalid credentials.');
                }

                /**
                 * OPTIONAL BUT RECOMMENDED:
                 * Ensure the user belongs to this firm account.
                 * Replace the checks below with YOUR relationships:
                 * - maybe $user->getFirm() or $user->getFirmUserProfile()->getFirm()
                 * - or if client users are tied via client profile, check that too
                 */
                if (method_exists($user, 'getFirm') && $user->getUserProfile()) {
                    $userProfile = $user->getUserProfile();
                    if ($userProfile->getFirm()->getId() !== $firm->getId()) {
                        throw new CustomUserMessageAuthenticationException('Invalid credentials.');
                    }
                }

                // If you have separate profiles:
                // $firmFromProfile = $user->getFirmUserProfile()?->getFirm() ?? $user->getClientUserProfile()?->getFirm();
                // if (!$firmFromProfile || $firmFromProfile->getId() !== $firm->getId()) { ... }

                return $user;
            }),
            new PasswordCredentials((string) $request->request->get('_password', '')),
            [
                new CsrfTokenBadge('authenticate', $csrfToken),
                new RememberMeBadge(),
                // If you want to use the firm later, you can store it in session here:
                // new Badge('tenant', fn() => $firm) // custom badge if you build one
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, $token, string $firewallName): ?Response
    {
        // If they were trying to access a protected page, go there first
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        $account = (string) $request->attributes->get('account');

        // Otherwise go to a post-login router that decides firm vs client dashboards
        return new RedirectResponse($this->urlGenerator->generate('tenant_post_login', ['account' => $account]));
    }

    protected function getLoginUrl(Request $request): string
    {
        // When auth fails, redirect back to the same firm login page
        $account = (string) $request->attributes->get('account');
        return $this->urlGenerator->generate(self::LOGIN_ROUTE, ['account' => $account]);
    }
}
