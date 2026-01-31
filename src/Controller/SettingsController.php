<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/settings')]
class SettingsController extends AbstractController
{
    #[Route('', name: 'app_settings', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('settings/index.html.twig', [
            'page_title' => 'Settings',
        ]);
    }

    #[Route('/users', name: 'app_settings_users', methods: ['GET'])]
    public function users(): Response
    {
        return $this->render('settings/users.html.twig', [
            'page_title' => 'User Permissions',
        ]);
    }
}
