<?php

namespace Shazzoo\Assistant\Llm;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Het hulpmiddel waarmee het model na elk antwoord vastlegt welke bron het gebruikte en of
 * de vraag beantwoord kon worden. De bezoeker ziet dit niet als tekst; de bron verschijnt
 * onder het antwoord. Wat het model invult, lezen we uit de stream; er volgt geen tweede beurt.
 */
final class RecordAnswerTool implements Tool
{
    public const string NAME = 'antwoord_vastleggen';

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): string
    {
        return 'Legt na elk antwoord vast welke bron is gebruikt en of de vraag beantwoord kon worden. De bezoeker ziet dit niet als tekst; de bron verschijnt onder het antwoord.';
    }

    public function handle(Request $request): string
    {
        return 'Vastgelegd.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'bron' => $schema->string()
                ->nullable()
                ->description('Alleen websitepagina\'s, bijvoorbeeld "pagina Contact". Null als het antwoord uit het kennisbestand komt of er geen bron is gebruikt.')
                ->required(),
            'status' => $schema->string()
                ->enum(['beantwoord', 'geen_bron', 'bron_onduidelijk', 'buiten_de_kaders'])
                ->required(),
            'taal' => $schema->string()
                ->enum(['nl', 'en', 'de', 'fr', 'anders'])
                ->description('De taal waarin de bezoeker schrijft.')
                ->required(),
        ];
    }
}
