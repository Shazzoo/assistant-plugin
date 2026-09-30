<?php

namespace Shazzoo\Assistant\Http;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serveert de CSS en JS van de plugin, zodat een site ze niet zelf hoeft te bouwen.
 */
final class AssetController
{
    private const array TYPES = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'text/javascript; charset=UTF-8',
    ];

    public function __invoke(string $file): BinaryFileResponse
    {
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        $path = dirname(__DIR__, 2)."/resources/{$extension}/{$file}";

        abort_unless(is_file($path) && isset(self::TYPES[$extension]), 404);

        return response()->file($path, [
            'Content-Type' => self::TYPES[$extension],
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
