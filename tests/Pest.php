<?php

use Livewire\Features\SupportTesting\Testable;
use Shazzoo\Assistant\Filament\Pages\Settings;
use Shazzoo\Assistant\Filament\Sections\ConversationsSection;
use Shazzoo\Assistant\Filament\Sections\EmployeesSection;
use Shazzoo\Assistant\Filament\Sections\KnowledgeSection;
use Shazzoo\Assistant\Filament\Sections\ReferencesSection;
use Shazzoo\Assistant\Filament\Sections\UnansweredSection;
use Shazzoo\Assistant\Livewire\AssistantTable;
use Shazzoo\Assistant\Tests\TestCase;
use Shazzoo\ContentStudioCore\Models\Page;
use Shazzoo\ContentStudioCore\Models\User;

uses(TestCase::class)->in('Feature');

/**
 * Een gebruiker van het CMS. Core heeft geen factory; beheerders herkent het aan is_admin.
 *
 * @param  array<string, mixed>  $attributes
 */
function adminUser(array $attributes = []): User
{
    return User::query()->forceCreate([
        'name' => 'Beheerder',
        'email' => 'beheer'.uniqid().'@voorbeeld.nl',
        'password' => bcrypt('wachtwoord'),
        'is_admin' => true,
        ...$attributes,
    ]);
}

/**
 * Een pagina van het CMS, zoals een beheerder hem aanmaakt.
 *
 * @param  array<string, mixed>  $attributes
 */
function cmsPage(array $attributes): Page
{
    $author = adminUser()->id;

    return Page::query()->forceCreate([
        'locale' => 'nl',
        'is_active' => true,
        'created_by' => $author,
        'updated_by' => $author,
        ...$attributes,
    ]);
}

/**
 * Een onderdeel van het beheer: de tabel van dat blok, of de instellingen.
 */
function assistantAdmin(string $section = 'onbeantwoord'): Testable
{
    if ($section === 'instellingen') {
        return Livewire\Livewire::test(Settings::class);
    }

    $source = match ($section) {
        'gesprekken' => ConversationsSection::class,
        'onbeantwoord' => UnansweredSection::class,
        'kennisbestand' => KnowledgeSection::class,
        'medewerkers' => EmployeesSection::class,
        'referenties' => ReferencesSection::class,
    };

    return Livewire\Livewire::test(AssistantTable::class, ['source' => $source]);
}
