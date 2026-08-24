<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Sign-in form of the EasyAdmin backoffice (FR-030). Rendered on GET;
 * the admin firewall's form_login authenticator intercepts the POST to
 * this same route before any controller code runs.
 */
final class AdminLoginController extends AbstractController
{
    public function __construct(private readonly AuthenticationUtils $authenticationUtils)
    {
    }

    public function index(): Response
    {
        return $this->render('admin/login.html.twig', [
            'page_title' => 'Lone Wolf backoffice',
            'error' => $this->authenticationUtils->getLastAuthenticationError(),
            'last_username' => $this->authenticationUtils->getLastUsername(),
            'csrf_token_intention' => 'authenticate',
            'action' => $this->generateUrl('admin_login'),
            'target_path' => $this->generateUrl('admin_dashboard'),
        ]);
    }
}
