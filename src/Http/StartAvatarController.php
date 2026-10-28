<?php

namespace Shazzoo\Assistant\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Shazzoo\Assistant\Avatar\AvatarSessions;

/**
 * Start de pratende avatar voor een gesprek. Bewust geen Livewire-actie: die loopt naast het
 * streamen van het antwoord en zou bij aankomst de status van vóór dat antwoord terugzetten,
 * waarna de chat geen nieuwe vraag meer aanneemt. Ondertekend: de chat krijgt de URL pas
 * na de eerste vraag. Geen inhoud betekent: de foto blijft staan.
 */
final class StartAvatarController
{
    public function __invoke(string $conversation, AvatarSessions $sessions): JsonResponse|Response
    {
        // Eén start tegelijk per gesprek, ook als de browser twee keer vraagt.
        if (! Cache::add("assistant:avatar-start:{$conversation}", true, 30)) {
            return response()->noContent();
        }

        $session = $sessions->start();

        return $session === null ? response()->noContent() : response()->json($session);
    }
}
