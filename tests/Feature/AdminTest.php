<?php

use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Shazzoo\Assistant\Assistant;
use Shazzoo\Assistant\FakeAssistant;
use Shazzoo\Assistant\Filament\Pages\HowItWorks;
use Shazzoo\Assistant\Filament\Resources\Conversations\ConversationResource;
use Shazzoo\Assistant\Filament\Resources\Conversations\Pages\ListConversations;
use Shazzoo\Assistant\Filament\Resources\Conversations\Pages\ViewConversation;
use Shazzoo\Assistant\Filament\Resources\KnowledgeEntries\KnowledgeEntryResource;
use Shazzoo\Assistant\Filament\Resources\KnowledgeEntries\Pages\ListKnowledgeEntries;
use Shazzoo\Assistant\Filament\Resources\UnansweredQuestions\Pages\EditUnansweredQuestion;
use Shazzoo\Assistant\Filament\Resources\UnansweredQuestions\Pages\ListUnansweredQuestions;
use Shazzoo\Assistant\Filament\Resources\UnansweredQuestions\UnansweredQuestionResource;
use Shazzoo\Assistant\Filament\Widgets\SourcesChart;
use Shazzoo\Assistant\Filament\Widgets\StatsOverview;
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

it('only lets administrators of the CMS in', function () {
    $this->actingAs(adminUser(['email' => 'iemand@gmail.com', 'is_admin' => false]))
        ->get('/admin/assistent/kennisbestand')
        ->assertForbidden();

    $this->actingAs(admin())
        ->get('/admin/assistent/kennisbestand')
        ->assertOk();
});

it('lists open questions by how often they were asked', function () {
    $this->actingAs(admin());

    $rare = UnansweredQuestion::factory()->create(['times_asked' => 1]);
    $frequent = UnansweredQuestion::factory()->create(['times_asked' => 12]);
    $resolved = UnansweredQuestion::factory()->resolved()->create(['times_asked' => 30]);

    Livewire::test(ListUnansweredQuestions::class)
        ->assertCanSeeTableRecords([$frequent, $rare], inOrder: true)
        ->assertCanNotSeeTableRecords([$resolved]);
});

it('requires what changed before a question counts as resolved', function () {
    $this->actingAs(admin());
    $question = UnansweredQuestion::factory()->create();

    Livewire::test(EditUnansweredQuestion::class, ['record' => $question->getRouteKey()])
        ->fillForm(['status' => UnansweredStatus::Resolved->value, 'resolution' => ''])
        ->call('save')
        ->assertHasFormErrors(['resolution' => 'required']);

    Livewire::test(EditUnansweredQuestion::class, ['record' => $question->getRouteKey()])
        ->fillForm(['status' => UnansweredStatus::Resolved->value, 'resolution' => 'Regel 19 toegevoegd aan het kennisbestand.', 'assignee' => 'Jasper'])
        ->call('save')
        ->assertHasNoFormErrors();

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

    Livewire::test(ListConversations::class)->assertCanSeeTableRecords([$conversation]);

    Livewire::test(ViewConversation::class, ['record' => $conversation->getRouteKey()])
        ->assertSee('Bel me op [TELEFOON]')
        ->assertSee('Dat weet ik niet zeker.')
        ->assertSee('Geen bron');

    $this->get('/admin/assistent/gesprekken/create')->assertNotFound();
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

it('renders the dashboard widgets', function () {
    $this->actingAs(admin());
    DailyStatistic::factory()->create();

    Livewire::test(StatsOverview::class)->assertSee('Gesprekken')->assertSee('30%');
    Livewire::test(SourcesChart::class)->assertOk();
});

it('links the dashboard figures to the matching lists', function () {
    $this->actingAs(admin());
    DailyStatistic::factory()->create();
    KnowledgeEntry::factory()->create(['answer' => 'Het is [BEDRAG].']);

    Livewire::test(StatsOverview::class)
        ->assertSee(ConversationResource::getUrl('index'), escape: false)
        ->assertSee(ConversationResource::getUrl('index', ['filters' => ['met_onbeantwoord' => ['isActive' => true]]]), escape: false)
        ->assertSee(UnansweredQuestionResource::getUrl('index'), escape: false)
        ->assertSee(KnowledgeEntryResource::getUrl('index', ['filters' => ['used' => ['value' => false]]]), escape: false)
        ->assertSee('0 van 1');

    Livewire::test(SourcesChart::class)->assertSee(HowItWorks::getUrl(), escape: false);
});

it('applies the filters from a dashboard link', function () {
    $this->actingAs(admin());

    $withUnanswered = Conversation::factory()->create();
    ConversationMessage::factory()->for($withUnanswered)->create(['role' => 'assistant', 'status' => 'geen_bron']);
    $answered = Conversation::factory()->create();
    ConversationMessage::factory()->for($answered)->create(['role' => 'assistant', 'status' => 'beantwoord']);

    Livewire::withQueryParams(['filters' => ['met_onbeantwoord' => ['isActive' => true]]])
        ->test(ListConversations::class)
        ->assertCanSeeTableRecords([$withUnanswered])
        ->assertCanNotSeeTableRecords([$answered]);

    $unused = KnowledgeEntry::factory()->withoutAnswer()->create();
    $used = KnowledgeEntry::factory()->create();

    Livewire::withQueryParams(['filters' => ['used' => ['value' => false]]])
        ->test(ListKnowledgeEntries::class)
        ->assertCanSeeTableRecords([$unused])
        ->assertCanNotSeeTableRecords([$used]);
});

it('shows the settings, sources and instructions the assistant works with', function () {
    AssistantSettings::current()->update(['name' => 'Joan', 'company' => 'Voorbeeld B.V.', 'contact_name' => 'Jasper', 'contact_phone' => '010 123 4567', 'share_to' => 'beheer@voorbeeld.nl']);
    cmsPage(['title' => 'Beheer en hosting', 'slug' => 'beheer-en-hosting', 'content' => [['type' => 'text', 'data' => ['body' => 'Wij houden het draaiende.']]]]);
    KnowledgeEntry::factory()->count(2)->create();
    KnowledgeEntry::factory()->create(['answer' => 'Het is [BEDRAG].']);

    $this->actingAs(admin())
        ->get('/admin/assistent/hoe-het-werkt')
        ->assertOk()
        ->assertSee('beheer@voorbeeld.nl')
        ->assertSee('2 van 3 regels in gebruik')
        ->assertSee('Beheer en hosting')
        ->assertSee('Je bent Joan, de AI-assistent op de website van Voorbeeld B.V.')
        ->assertSee('Jasper kan u dat binnen een werkdag vertellen')
        ->assertDontSee('{{contact}}');
});
