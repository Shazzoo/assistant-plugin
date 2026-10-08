<?php

use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Shazzoo\Assistant\Instructions;
use Shazzoo\Assistant\Livewire\AssistantChat;
use Shazzoo\Assistant\Models\AssistantSettings;

it('saves the settings of the assistant in the admin', function () {
    $this->actingAs(adminUser(['name' => 'Beheerder']));

    assistantAdmin('instellingen')
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

it('starts with the default limits on a fresh install', function () {
    expect(AssistantSettings::current())
        ->max_question_length->toBe(500)
        ->max_questions->toBe(10);
});

it('uses the own instructions instead of the template, with the placeholders filled in', function () {
    AssistantSettings::current()->update(['name' => 'Joan', 'contact_phone' => '010 123 4567', 'instructions' => 'Je bent {{assistant}}. Bel {{telefoon}} of {{phone}}.']);

    expect(app(Instructions::class)->render())->toBe('Je bent Joan. Bel 010 123 4567 of 010 123 4567.');
});

it('shows the template as editable text and keeps following it while unchanged', function () {
    $this->actingAs(adminUser());

    assistantAdmin('instellingen')
        ->assertSet('data.instructions', Instructions::defaultTemplate())
        ->call('save')
        ->assertHasNoFormErrors();

    expect(AssistantSettings::current()->instructions)->toBeNull();
});

it('treats the template with other line endings or surrounding whitespace as unchanged', function () {
    $template = Instructions::defaultTemplate();

    expect(Instructions::isDefaultTemplate(str_replace("\n", "\r\n", $template)."  \n"))->toBeTrue()
        ->and(Instructions::isDefaultTemplate(null))->toBeTrue()
        ->and(Instructions::isDefaultTemplate($template.'Extra regel.'))->toBeFalse();
});

it('saves edited instructions and can reset them to the template', function () {
    $this->actingAs(adminUser());

    assistantAdmin('instellingen')
        ->fillForm(['instructions' => Instructions::defaultTemplate()."\nNoem nooit prijzen."])
        ->call('save');

    expect(AssistantSettings::current()->instructions)->toEndWith('Noem nooit prijzen.');

    assistantAdmin('instellingen')
        ->assertSet('data.instructions', AssistantSettings::current()->instructions)
        ->fillForm(['instructions' => ''])
        ->call('save');

    expect(AssistantSettings::current()->instructions)->toBeNull();
});

it('resets the instructions to the template after a confirmation', function () {
    $this->actingAs(adminUser());
    AssistantSettings::current()->update(['instructions' => 'Eigen instructie.']);
    $action = TestAction::make('useTemplate')->schemaComponent('instructions', schema: 'form');

    assistantAdmin('instellingen')
        ->mountAction($action)
        ->assertActionMounted($action)
        ->assertSet('data.instructions', 'Eigen instructie.')
        ->callMountedAction()
        ->assertSet('data.instructions', Instructions::defaultTemplate())
        ->call('save');

    expect(AssistantSettings::current()->instructions)->toBeNull();
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
