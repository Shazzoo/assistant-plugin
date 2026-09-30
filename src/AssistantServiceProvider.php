<?php

namespace Shazzoo\Assistant;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Shazzoo\Assistant\Avatar\AvatarSessions;
use Shazzoo\Assistant\Avatar\LiveAvatarClient;
use Shazzoo\Assistant\Console\EvalCommand;
use Shazzoo\Assistant\Console\ExportKnowledgeCommand;
use Shazzoo\Assistant\Console\ImportKnowledgeCommand;
use Shazzoo\Assistant\Http\AssetController;
use Shazzoo\Assistant\Http\StopAvatarController;
use Shazzoo\Assistant\Livewire\AssistantChat;
use Shazzoo\Assistant\Livewire\AssistantTable;
use Shazzoo\Assistant\Models\AssistantSettings;
use Shazzoo\Assistant\Models\AvatarSettings;
use Shazzoo\Assistant\Models\Conversation;

final class AssistantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/assistant.php', 'assistant');

        $this->app->bind(Knowledge::class, fn (Application $app): Knowledge => new Knowledge(
            array_map(fn (string $source) => $app->make($source), config('assistant.sources', [])),
        ));

        $this->app->bind(AssistantSettings::class, fn (): AssistantSettings => AssistantSettings::current());

        $this->app->bind(Instructions::class, fn (Application $app): Instructions => new Instructions($app->make(AssistantSettings::class)));

        $this->app->bind(PersonalDataScrubber::class, fn (Application $app): PersonalDataScrubber => new PersonalDataScrubber(
            $app->make(AssistantSettings::class)->publicDetails(),
        ));

        $this->app->bind(LiveAvatarClient::class, fn (): LiveAvatarClient => new LiveAvatarClient(
            AvatarSettings::current()->apiKey(),
            config('assistant.avatar.base_url'),
        ));

        $this->app->bind(Assistant::class, function (Application $app): Assistant {
            if (config('assistant.driver') === 'fake') {
                return new FakeAssistant;
            }

            $model = config('assistant.model');
            $providerOptions = config('assistant.provider_options', []);

            // Haiku kent geen adaptive thinking en geen effort.
            if (str_starts_with((string) $model, 'claude-haiku')) {
                unset($providerOptions['anthropic']);
            }

            return new LlmAssistant(
                knowledge: $app->make(Knowledge::class),
                settings: $app->make(AssistantSettings::class),
                provider: config('assistant.provider'),
                model: filled($model) ? $model : null,
                maxTokens: config('assistant.max_tokens'),
                providerOptions: $providerOptions,
                timeout: config('assistant.timeout'),
            );
        });
    }

    public function boot(): void
    {
        $basePath = dirname(__DIR__);

        $this->loadMigrationsFrom($basePath.'/database/migrations');
        $this->loadViewsFrom($basePath.'/resources/views', 'assistant');
        $this->loadTranslationsFrom($basePath.'/lang', 'assistant');

        // <x-assistant::panel>, voor wie de chat buiten het blok wil tonen (bijvoorbeeld in een hero).
        Blade::anonymousComponentPath($basePath.'/resources/views/components', 'assistant');

        $this->publishes([$basePath.'/resources/views' => resource_path('views/vendor/assistant')], 'assistant-views');

        Livewire::component('assistant-chat', AssistantChat::class);
        Livewire::component('assistant-table', AssistantTable::class);

        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([ImportKnowledgeCommand::class, ExportKnowledgeCommand::class, EvalCommand::class]);

            $this->app->booted(function (): void {
                $schedule = $this->app->make(Schedule::class);

                // Transcripties na de bewaartermijn verwijderen. Alleen Conversation: de lijst
                // onbeantwoorde vragen heeft een eigen levensloop en valt hier bewust buiten.
                $schedule->command('model:prune', ['--model' => [Conversation::class]])->daily();

                // Avatarsessies die nooit netjes zijn gestopt afsluiten, zodat budget en gelijktijdigheid kloppen.
                $schedule->call(fn () => $this->app->make(AvatarSessions::class)->closeStale())
                    ->name('assistant-avatar-close-stale')
                    ->everyFifteenMinutes();
            });
        }
    }

    private function registerRoutes(): void
    {
        Route::middleware('web')->group(function (): void {
            // Eigen CSS en JS, zodat de chat er op elke site goed uitziet zonder build-stap. Onder
            // css/ en js/: die vallen buiten de catch-all route voor pagina's van core.
            Route::get('/css/assistant/{file}', AssetController::class)->where('file', 'chat\.css')->name('assistant.css');
            Route::get('/js/assistant/{file}', AssetController::class)->where('file', 'avatar\.js')->name('assistant.js');

            // Stopt de pratende avatar: bij stilte (fetch) en als de bezoeker de pagina verlaat (sendBeacon).
            // Ondertekend, zodat alleen de chat die de sessie startte hem kan stoppen.
            Route::post('/assistant/avatar/{sessionId}/stop', StopAvatarController::class)
                ->middleware('signed')
                ->withoutMiddleware(PreventRequestForgery::class)
                ->name('assistant.avatar.stop');
        });
    }
}
