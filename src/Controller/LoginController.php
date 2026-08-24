<?php

namespace App\Controller;

use App\Repository\FirmsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function home(): Response
    {

        return $this->redirectToRoute('admin_login');
    }

    #[Route('/admin', name: 'admin_home')]
    public function adminHome(): Response
    {
        return $this->redirectToRoute('admin_login');
    }

    #[Route(path: '/admin/login', name: 'admin_login')]
    public function adminLogin(AuthenticationUtils $authenticationUtils): Response
    {
        // ag: check if user is logged in
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('admin_post_login');
        }
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('login/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    // ag: handles the logic afer a successful login for admin users
    #[Route('/admin/post-login', name: 'admin_post_login')]
    public function adminPostLogin(Security $security): Response
    {
        if (!$security->getUser()) {
            return $this->redirectToRoute('admin_login');
        }


        return $this->redirectToRoute(match (true) {
            $this->isGranted('ROLE_ADMIN')  => 'admin_dashboard',
        });
    }

    #[Route(path: '/admin/logout', name: 'admin_logout')]
    public function adminLogout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }


    #[Route('/{account}/login', name: 'tenant_login', requirements: ['account' => '[a-z0-9\-]+'])]
    public function tenantLogin(?string $account, SessionInterface $session, AuthenticationUtils $auth, FirmsRepository $firms, Request $request): Response
    {
        if (empty($account)) {
            $account = $session->get('tenant_account') ?? null;
        }
        $firm = $firms->findOneBy(['account' => $account, 'active' => true]);

        if (!$firm) {
            throw $this->createNotFoundException();
        }

        // store tenant context for later requests (dashboards, branding, etc)
        $session->set('tenant_account', $account);
        $session->set('tenant_firm_id', $firm->getId());

        return $this->render('login/tenant-login.html.twig', [
            'firm' => $firm,
            'last_username' => $auth->getLastUsername(),
            'error' => $auth->getLastAuthenticationError(),
        ]);
    }

    // ag: handles the logic afer a successful login for admin users
    #[Route('/{account}/post-login', name: 'tenant_post_login')]
    public function tenantPostLogin(Security $security, string $account): Response
    {
        if (!$security->getUser()) {
            return $this->redirectToRoute('tenant_login', ['account' => $account]);
        }

        return $this->redirectToRoute(match (true) {
            $this->isGranted('ROLE_FIRM')  => 'firm_dashboard',
            $this->isGranted('ROLE_CLIENT')  => 'client_dashboard',
        });
    }

    // ag: added default value for account so that 2fa can proceed w/o getting stuck on that, and keep dynamic logout functioning
    #[Route('/{account}/logout', name: 'tenant_logout', defaults: ['account' => 'admin'], methods: ['GET', 'POST'])]
    public function tenantAccountLogout(?string $account): void
    {
        throw new \LogicException('Intercepted by Symfony logout.');
    }

    // ag: for the access denied custom 403/500 page
    #[Route('/redirect-home', name: 'redirect_home')]
    public function redirectHome(): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_dashboard');
        }

        if ($this->isGranted('ROLE_FIRM')) {
            return $this->redirectToRoute('firm_dashboard');
        }

        if ($this->isGranted('ROLE_CLIENT')) {
            return $this->redirectToRoute('client_dashboard');
        }

        return $this->redirectToRoute('admin_login');
    }
}
