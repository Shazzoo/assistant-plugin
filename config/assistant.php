<?php

use Shazzoo\Assistant\Knowledge\CmsPagesSource;

/*
|--------------------------------------------------------------------------
| Assistent
|--------------------------------------------------------------------------
|
| De technische instellingen. Wat per site verschilt (naam, instructies,
| contactgegevens, limieten) staat in het beheer, in AssistantSettings.
|
*/

return [

    /*
    | "claude" praat met de Claude API, "fake" geeft vaste antwoorden
    | (handig om de interface te bouwen zonder API-key of kosten).
    */
    'driver' => env('ASSISTANT_DRIVER', 'claude'),

    'api_key' => env('ANTHROPIC_API_KEY'),

    /*
    | claude-sonnet-5 is de standaard. claude-haiku-4-5 is goedkoper en
    | sneller; vergelijk eerst met dezelfde testvragen voordat u wisselt.
    | Effort (low, medium, high) werkt niet op Haiku en wordt daar genegeerd.
    */
    'model' => env('ASSISTANT_MODEL', 'claude-sonnet-5'),

    'effort' => env('ASSISTANT_EFFORT', 'medium'),

    'max_tokens' => (int) env('ASSISTANT_MAX_TOKENS', 8000),

    /*
    | Waar de assistent zijn kennis over de site vandaan haalt, naast het
    | kennisbestand in het beheer. Elke klasse implementeert KnowledgeSource.
    */
    'sources' => [
        CmsPagesSource::class,
    ],

    /*
    | Gesprekken worden geschoond bewaard om de assistent te verbeteren en na
    | de bewaartermijn verwijderd. Onbeantwoorde vragen vallen daar niet onder.
    */
    'transcripts' => [
        'retention_days' => (int) env('ASSISTANT_TRANSCRIPT_DAYS', 90),
    ],

    /*
    | Plafond voor alle bezoekers samen, dat het API-budget beschermt. Per
    | gesprek gelden de limieten uit het beheer.
    */
    'rate_limits' => [
        'global_per_minute' => (int) env('ASSISTANT_LIMIT_GLOBAL', 120),
    ],

    /*
    | Pratende avatar (HeyGen LiveAvatar). Alleen de antwoordtekst gaat naar
    | LiveAvatar (VS), nooit wat de bezoeker typt, en er wordt geen microfoon
    | gebruikt. Staat standaard uit; de rest is in te stellen in het beheer.
    */
    'avatar' => [
        'enabled' => (bool) env('ASSISTANT_AVATAR_ENABLED', false),
        'api_key' => env('LIVEAVATAR_API_KEY'),
        'base_url' => env('LIVEAVATAR_BASE_URL', 'https://api.liveavatar.com'),
        'sandbox' => (bool) env('LIVEAVATAR_SANDBOX', true),
        'avatar_id' => env('LIVEAVATAR_AVATAR_ID'),
        'voice_id' => env('LIVEAVATAR_VOICE_ID'),
        'context_id' => env('LIVEAVATAR_CONTEXT_ID'),
        'language' => env('LIVEAVATAR_LANGUAGE', 'nl'),
        'quality' => env('LIVEAVATAR_QUALITY', 'medium'),
        'idle_stop_seconds' => (int) env('ASSISTANT_AVATAR_IDLE_SECONDS', 90),
        'max_session_seconds' => (int) env('ASSISTANT_AVATAR_MAX_SECONDS', 600),
        'max_concurrent' => (int) env('ASSISTANT_AVATAR_MAX_CONCURRENT', 3),
        'monthly_budget_minutes' => (int) env('ASSISTANT_AVATAR_MONTHLY_MINUTES', 500),
        // LiveKit wordt pas geladen als de avatar echt start.
        'livekit_url' => env('ASSISTANT_LIVEKIT_URL', 'https://cdn.jsdelivr.net/npm/livekit-client@2.22.3/+esm'),
    ],

];
