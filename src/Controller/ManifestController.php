<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ManifestController extends AbstractController
{
    #[Route('/manifest.json', name: 'app_manifest')]
    public function manifest(Request $request): JsonResponse
    {
        $basePath = $request->getBasePath();

        $manifest = [
            'name' => 'Honeyguide Projects',
            'short_name' => 'Projects',
            'description' => 'Project and task management application',
            'start_url' => $basePath ?: '/',
            'scope' => $basePath ?: '/',
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => '#10b981',
            'orientation' => 'portrait-primary',
            'icons' => [
                [
                    'src' => ($basePath ?: '') . '/icon-192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ],
                [
                    'src' => ($basePath ?: '') . '/icon-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ],
            ],
            'categories' => ['productivity', 'business'],
            'shortcuts' => [
                [
                    'name' => 'Projects',
                    'short_name' => 'Projects',
                    'description' => 'View all projects',
                    'url' => ($basePath ?: '') . '/projects',
                    'icons' => [
                        [
                            'src' => ($basePath ?: '') . '/icon-192.png',
                            'sizes' => '192x192',
                        ],
                    ],
                ],
                [
                    'name' => 'My Tasks',
                    'short_name' => 'My Tasks',
                    'description' => 'View your tasks',
                    'url' => ($basePath ?: '') . '/my-tasks',
                    'icons' => [
                        [
                            'src' => ($basePath ?: '') . '/icon-192.png',
                            'sizes' => '192x192',
                        ],
                    ],
                ],
                [
                    'name' => 'All Tasks',
                    'short_name' => 'All Tasks',
                    'description' => 'View all tasks',
                    'url' => ($basePath ?: '') . '/all-tasks',
                    'icons' => [
                        [
                            'src' => ($basePath ?: '') . '/icon-192.png',
                            'sizes' => '192x192',
                        ],
                    ],
                ],
                [
                    'name' => 'Settings',
                    'short_name' => 'Settings',
                    'description' => 'Manage settings',
                    'url' => ($basePath ?: '') . '/settings',
                    'icons' => [
                        [
                            'src' => ($basePath ?: '') . '/icon-192.png',
                            'sizes' => '192x192',
                        ],
                    ],
                ],
            ],
        ];

        return new JsonResponse($manifest);
    }
}
