<?php

use Livewire\Livewire;
use Shazzoo\Assistant\Filament\Pages\AssistantSettingsPage;
use Shazzoo\Assistant\Instructions;
use Shazzoo\Assistant\Livewire\AssistantChat;
use Shazzoo\Assistant\Models\AssistantSettings;

it('saves the settings of the assistant in the admin', function () {
    $this->actingAs(adminUser(['name' => 'Beheerder']));

    Livewire::test(AssistantSettingsPage::class)
        ->fillForm([
            'name' => 'Joan',
            'company' => 'Voorbeeld B.V.',
            'contact_name' => 'Jasper',
            'contact_phone' => '010 123 4567',
            'contact_email' => 'info@voorbeeld.nl',
            'max_question_length' => 500,
            'max_questions' => 10,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(AssistantSettings::current())
        ->name->toBe('Joan')
        ->updated_by->toBe('Beheerder')
        ->and(app(Instructions::class)->render())->toContain('Je bent Joan, de AI-assistent op de website van Voorbeeld B.V.');
});

it('uses the own instructions instead of the template, with the placeholders filled in', function () {
    AssistantSettings::current()->update(['name' => 'Joan', 'contact_phone' => '010 123 4567', 'instructions' => 'Je bent {{assistant}}. Bel {{telefoon}} of {{phone}}.']);

    expect(app(Instructions::class)->render())->toBe('Je bent Joan. Bel 010 123 4567 of 010 123 4567.');
});

it('speaks English on an English site', function () {
    app()->setLocale('en');

    Livewire::test(AssistantChat::class)
        ->assertSee('Ask Assistant a question')
        ->set('prompt', '')
        ->call('send')
        ->assertSee('Type a question first.');
});

it('serves its own stylesheet and script, and nothing else', function () {
    $this->get(route('assistant.css', 'chat.css'))->assertOk()->assertHeader('Content-Type', 'text/css; charset=UTF-8');
    $this->get(route('assistant.js', 'avatar.js'))->assertOk()->assertHeader('Content-Type', 'text/javascript; charset=UTF-8');
    $this->get('/js/assistant/..%2F..%2Fcomposer.json')->assertNotFound();
    $this->get('/css/assistant/avatar.js')->assertNotFound();
});
