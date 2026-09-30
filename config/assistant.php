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
    | "llm" praat via laravel/ai met een taalmodel, "fake" geeft vaste antwoorden
    | (handig om de interface te bouwen zonder API-key of kosten).
    */
    'driver' => env('ASSISTANT_DRIVER', 'llm'),

    /*
    | Welke provider van laravel/ai: anthropic (Claude), openai (ChatGPT),
    | gemini, mistral, ... De API-key staat in config/ai.php, bijvoorbeeld
    | ANTHROPIC_API_KEY, OPENAI_API_KEY of GEMINI_API_KEY in .env.
    */
    'provider' => env('ASSISTANT_PROVIDER', 'anthropic'),

    /*
    | Leeg: het standaardmodel van de provider. De instructies zijn afgesteld
    | op Claude; draai assistant:eval voordat u op een ander model overstapt.
    */
    'model' => env('ASSISTANT_MODEL'),

    'max_tokens' => (int) env('ASSISTANT_MAX_TOKENS', 8000),

    'timeout' => (int) env('ASSISTANT_TIMEOUT', 90),

    /*
    | Extra velden per provider die zo in het verzoek gaan. Claude denkt eerst
    | na, met deze effort (low, medium, high); Haiku kan dat niet en krijgt
    | deze velden dan ook niet.
    */
    'provider_options' => [
        'anthropic' => [
            'thinking' => ['type' => 'adaptive'],
            'output_config' => ['effort' => env('ASSISTANT_EFFORT', 'medium')],
        ],
    ],

    /*
    | Het model dat bij assistant:eval de antwoorden beoordeelt.
    */
    'eval' => [
        'provider' => env('ASSISTANT_EVAL_PROVIDER', 'anthropic'),
        'model' => env('ASSISTANT_EVAL_MODEL', 'claude-opus-5'),
        'path' => env('ASSISTANT_EVAL_PATH'),
    ],

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
