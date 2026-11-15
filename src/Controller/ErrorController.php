<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class ErrorController extends AbstractController
{
    public function show(\Throwable $exception): Response
    {
        $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];
        [$title, $message] = match ($status) {
            404 => ['Cette page est introuvable.', 'Cette adresse ne correspond à aucune page disponible. Retrouvez les événements et choisissez votre prochaine sortie.'],
            403 => ['Cet espace est réservé.', 'Votre compte ne permet pas d’accéder à cette page. Vous pouvez revenir au catalogue ou vous connecter avec le compte adapté.'],
            410 => ['Ce billet n’est plus disponible.', 'Il a été annulé ou n’est plus valide. Retrouvez vos commandes depuis votre espace client.'],
            400, 422 => ['Votre demande n’a pas pu être traitée.', 'Revenez à la page précédente et vérifiez les informations saisies avant de réessayer.'],
            405 => ['Cette action n’est pas disponible.', 'Utilisez les liens et les boutons de l’application pour poursuivre votre visite.'],
            default => ['Le service est momentanément indisponible.', 'Un problème technique empêche l’affichage de cette page. Réessayez dans quelques instants.'],
        };

        return $this->render('error/error.html.twig', compact('status', 'title', 'message'), new Response('', $status, $headers));
    }
}
