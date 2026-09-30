<?php

namespace Shazzoo\Assistant\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Shazzoo\Assistant\Avatar\AvatarSessions;

final class StopAvatarController
{
    public function __invoke(Request $request, string $sessionId, AvatarSessions $sessions): Response
    {
        $reason = in_array($request->input('reason'), ['idle', 'page_left', 'closed'], true) ? $request->input('reason') : 'page_left';

        $sessions->stop($sessionId, $reason);

        return response()->noContent();
    }
}
