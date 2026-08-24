<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Logout\LogoutSuccessHandlerInterface;


// ag: TODO delete file deprecated
class TenantLogoutSuccessHandler
{
    // public function __construct(private RouterInterface $router) {}

    // public function onLogoutSuccess(Request $request): RedirectResponse
    // {
    //     $session = $request->getSession();
    //     $account = $session->get('tenant_account');

    //     if ($account) {
    //         return new RedirectResponse(
    //             $this->router->generate('tenant_login', [
    //                 'account' => $account,
    //             ])
    //         );
    //     }

    //     return new RedirectResponse(
    //         $this->router->generate('homepage')
    //     );
    // }
}
