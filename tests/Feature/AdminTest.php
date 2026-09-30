<?php

use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Shazzoo\Assistant\Assistant;
use Shazzoo\Assistant\FakeAssistant;
use Shazzoo\Assistant\Filament\Pages\Conversations;
use Shazzoo\Assistant\Livewire\AssistantChat;
use Shazzoo\Assistant\Models\AssistantSettings;
use Shazzoo\Assistant\Models\Conversation;
use Shazzoo\Assistant\Models\ConversationMessage;
use Shazzoo\Assistant\Models\DailyStatistic;
use Shazzoo\Assistant\Models\KnowledgeEntry;
use Shazzoo\Assistant\Models\UnansweredQuestion;
use Shazzoo\Assistant\UnansweredStatus;
use Shazzoo\ContentStudioCore\Models\User;

function admin(): User
{
    return adminUser(['email' => 'beheer@voorbeeld.nl', 'name' => 'Beheerder']);
}

it('sends guests to the login page', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('only lets administrators of the CMS in, with three pages under one menu item', function () {
    $this->actingAs(adminUser(['email' => 'iemand@gmail.com', 'is_admin' => false]))
        ->get('/admin/assistent/gesprekken')
        ->assertForbidden();

    $this->actingAs(admin());

    $this->get('/admin/assistent')->assertRedirect(Conversations::getUrl());

    $this->get('/admin/assistent/gesprekken')->assertOk()->assertSeeInOrder(['Gesprekken', 'Kennis', 'Instellingen'])->assertSeeInOrder(['Gesprekken', 'Onbeantwoorde vragen']);
    $this->get('/admin/assistent/kennis')->assertOk()->assertSeeInOrder(['Kennisbestand', 'Medewerkers', 'Referenties']);
    $this->get('/admin/assistent/instellingen')->assertOk()->assertSee('Wie de assistent is');
});

it('opens every tab', function (string $tab) {
    $this->actingAs(admin());

    assistantAdmin($tab)->assertOk()->assertSet('tab', $tab);
})->with(['gesprekken', 'onbeantwoord', 'kennisbestand', 'medewerkers', 'referenties']);

it('lists open questions by how often they were asked', function () {
    $this->actingAs(admin());

    $rare = UnansweredQuestion::factory()->create(['times_asked' => 1]);
    $frequent = UnansweredQuestion::factory()->create(['times_asked' => 12]);
    $resolved = UnansweredQuestion::factory()->resolved()->create(['times_asked' => 30]);

    assistantAdmin('onbeantwoord')
        ->assertCanSeeTableRecords([$frequent, $rare], inOrder: true)
        ->assertCanNotSeeTableRecords([$resolved]);
});

it('requires what changed before a question counts as resolved', function () {
    $this->actingAs(admin());
    $question = UnansweredQuestion::factory()->create();

    assistantAdmin('onbeantwoord')
        ->callAction(TestAction::make('edit')->table($question), data: ['status' => UnansweredStatus::Resolved->value, 'resolution' => ''])
        ->assertHasActionErrors(['resolution' => 'required']);

    assistantAdmin('onbeantwoord')
        ->callAction(TestAction::make('edit')->table($question), data: ['status' => UnansweredStatus::Resolved->value, 'resolution' => 'Regel 19 toegevoegd aan het kennisbestand.', 'assignee' => 'Jasper'])
        ->assertHasNoActionErrors();

    expect($question->fresh())
        ->status->toBe(UnansweredStatus::Resolved)
        ->resolution->toBe('Regel 19 toegevoegd aan het kennisbestand.')
        ->resolved_at->not->toBeNull();
});

it('shows the scrubbed transcripts, read only', function () {
    $this->actingAs(admin());

    $conversation = Conversation::factory()->create();
    ConversationMessage::factory()->for($conversation)->create(['role' => 'user', 'content' => 'Bel me op [TELEFOON]']);
    ConversationMessage::factory()->for($conversation)->create(['role' => 'assistant', 'content' => 'Dat weet ik niet zeker.', 'status' => 'geen_bron']);

    assistantAdmin('gesprekken')
        ->assertCanSeeTableRecords([$conversation])
        ->mountAction(TestAction::make('view')->table($conversation))
        ->assertMountedActionModalSee(['Bel me op [TELEFOON]', 'Dat weet ik niet zeker.', 'Geen bron'])
        ->assertActionDoesNotExist(TestAction::make('create')->table());
});

it('counts answers and shared conversations per day', function () {
    $this->app->instance(Assistant::class, new FakeAssistant(delayInMicroseconds: 0));
    AssistantSettings::current()->update(['share_to' => 'beheer@voorbeeld.nl']);
    Mail::fake();

    $chat = Livewire::test(AssistantChat::class, ['page' => '/'])
        ->set('prompt', 'En onze data?')->call('send')->call('answer')
        ->set('prompt', 'Hebben jullie ervaring met Exact?')->call('send')->call('answer');

    $chat->call('openShare')->set('shareName', 'Piet')->set('sharePhone', '0612345678')->call('share');

    expect(DailyStatistic::sole())
        ->conversations->toBe(1)
        ->questions->toBe(2)
        ->answered->toBe(1)
        ->no_source->toBe(1)
        ->shared->toBe(1)
        ->sources->toBe(['pagina Contact' => 1]);
});

it('keeps the daily figures when transcripts are pruned', function () {
    DailyStatistic::factory()->create(['date' => today()->subDays(200)]);
    Conversation::factory()->create(['created_at' => now()->subDays(200)]);

    $this->artisan('model:prune', ['--model' => [Conversation::class]]);

    expect(Conversation::count())->toBe(0)
        ->and(DailyStatistic::count())->toBe(1);
});

it('saves the settings with the button in the header', function () {
    $this->actingAs(admin());

    assistantAdmin('instellingen')
        ->assertActionVisible('save')
        ->fillForm(['name' => 'Joan'])
        ->callAction('save')
        ->assertHasNoFormErrors();

    expect(AssistantSettings::current()->name)->toBe('Joan');
});

it('filters the knowledge on rows the assistant does not use', function () {
    $this->actingAs(admin());

    $unused = KnowledgeEntry::factory()->withoutAnswer()->create();
    $used = KnowledgeEntry::factory()->create();

    assistantAdmin('kennisbestand')
        ->filterTable('used', false)
        ->assertCanSeeTableRecords([$unused])
        ->assertCanNotSeeTableRecords([$used]);
});

it('shows the settings and the provider in the settings tab', function () {
    AssistantSettings::current()->update(['name' => 'Joan', 'company' => 'Voorbeeld B.V.', 'share_to' => 'beheer@voorbeeld.nl']);

    $this->actingAs(admin());

    assistantAdmin('instellingen')
        ->assertFormSet(['name' => 'Joan', 'company' => 'Voorbeeld B.V.', 'share_to' => 'beheer@voorbeeld.nl'])
        ->assertSee('Provider: '.config('assistant.provider'));
});
