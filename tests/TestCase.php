<?php

namespace Shazzoo\Assistant\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use Shazzoo\Assistant\AssistantServiceProvider;
use Shazzoo\ContentStudioCore\Models\User;

use function Orchestra\Testbench\default_skeleton_path;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /**
     * Zoals in een echte site: de providers van alle geïnstalleerde packages
     * (core, Filament, Livewire), zoals Laravel ze uit Composer ontdekt.
     */
    protected function getPackageProviders($app): array
    {
        $installed = json_decode((string) file_get_contents(dirname(__DIR__).'/vendor/composer/installed.json'), true);

        $providers = collect($installed['packages'] ?? $installed)
            ->reject(fn (array $package): bool => str_starts_with($package['name'], 'orchestra/'))
            ->flatMap(fn (array $package): array => $package['extra']['laravel']['providers'] ?? [])
            ->values()
            ->all();

        return [...$providers, AssistantServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('auth.providers.users.model', User::class);

        // Zoals een Nederlandse site; de Engelse teksten hebben een eigen test.
        $app['config']->set('app.locale', 'nl');

        // In een site ontdekt core het beheer van een actieve plugin in vendor/. Hier
        // is de plugin zelf het hoofdpakket, dus doen we dat voor core.
        $app->booting(function () use ($app): void {
            $src = dirname(__DIR__).'/src/Filament';

            $app->make('filament')->getPanel('admin')
                ->discoverPages(in: $src.'/Pages', for: 'Shazzoo\\Assistant\\Filament\\Pages');
        });
    }

    /**
     * Core zoekt thema's en losse plugins in de app. Het lege skelet van
     * Testbench heeft die mappen niet, dus maken we ze daar aan.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        foreach (['Themes', 'Plugins'] as $directory) {
            @mkdir(default_skeleton_path('app').'/'.$directory, 0755, true);
        }
    }
}
