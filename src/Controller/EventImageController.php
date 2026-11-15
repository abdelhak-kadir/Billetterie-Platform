<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\EventImageStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Attribute\Route;

final class EventImageController extends AbstractController
{
    #[Route('/media/events/{filename}', name: 'event_image', requirements: ['filename' => '[a-f0-9]{32}\.(?:jpg|png|webp)'], methods: ['GET', 'HEAD'])]
    public function show(string $filename, EventImageStorage $images): BinaryFileResponse
    {
        $path = $images->path($filename);
        if (!is_file($path)) { throw $this->createNotFoundException(); }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', match (pathinfo($filename, PATHINFO_EXTENSION)) {
            'jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
        });
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setPublic()->setMaxAge(86400);

        return $response;
    }
}
