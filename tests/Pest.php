<?php

use Livewire\Features\SupportTesting\Testable;
use Shazzoo\Assistant\Filament\Pages\AssistantPage;
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
 * De beheerpagina van de assistent, op het gegeven tabblad.
 */
function assistantAdmin(string $tab = 'onbeantwoord'): Testable
{
    return Livewire\Livewire::withQueryParams(['tab' => $tab])->test(AssistantPage::class);
}
