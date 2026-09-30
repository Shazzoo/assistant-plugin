<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Shazzoo\Assistant\Assistant;
use Shazzoo\Assistant\Avatar\AvatarSessions;
use Shazzoo\Assistant\FakeAssistant;
use Shazzoo\Assistant\Livewire\AssistantChat;
use Shazzoo\Assistant\Models\AvatarSession;
use Shazzoo\Assistant\Models\AvatarSettings;

beforeEach(function () {
    $this->app->instance(Assistant::class, new FakeAssistant(delayInMicroseconds: 0));
    config(['assistant.avatar.api_key' => 'la-test-key', 'assistant.avatar.base_url' => 'https://api.liveavatar.test']);
});

function fakeLiveAvatar(string $creditsLeft = '1110.0'): void
{
    Http::fake([
        'api.liveavatar.test/v1/sessions/token' => Http::response(['code' => 100, 'data' => ['session_id' => 'sess-1', 'session_token' => 'jwt-token'], 'message' => 'ok']),
        'api.liveavatar.test/v1/sessions/start' => Http::response(['code' => 100, 'data' => ['session_id' => 'sess-1', 'livekit_url' => 'wss://heygen.livekit.test', 'livekit_client_token' => 'lk-token', 'max_session_duration' => 600], 'message' => 'ok'], 201),
        'api.liveavatar.test/v1/sessions/stop' => Http::response(['code' => 100, 'data' => null, 'message' => 'ok']),
        'api.liveavatar.test/v1/users/credits' => Http::response(['code' => 1000, 'data' => ['credits_left' => $creditsLeft], 'message' => 'ok']),
    ]);
}

function avatarSettings(array $overrides = []): AvatarSettings
{
    $settings = AvatarSettings::current();
    $settings->update(array_merge(['enabled' => true, 'sandbox' => true], $overrides));

    return $settings;
}

function chatWithQuestion(string $question = 'Wat doen jullie? Bel me op 06-12345678'): Testable
{
    return Livewire::test(AssistantChat::class, ['page' => '/'])->set('prompt', $question)->call('send')->call('answer');
}

it('stays off by default and keeps the photo', function () {
    Http::fake();

    chatWithQuestion()
        ->assertSee('data-avatar="off"', escape: false)
        ->call('startAvatar')
        ->assertReturned(null);

    Http::assertNothingSent();
});

it('starts a sandbox session with only server-side credentials', function () {
    fakeLiveAvatar();
    avatarSettings();

    $session = chatWithQuestion()->assertSee('data-avatar="on"', escape: false)->call('startAvatar')->effects['returns'][0] ?? null;

    expect($session)
        ->livekit_url->toBe('wss://heygen.livekit.test')
        ->livekit_client_token->toBe('lk-token')
        ->not->toHaveKey('session_token')
        ->and($session['stop_url'])->toContain('signature=');

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/v1/sessions/token')
        && $request->hasHeader('X-API-KEY', 'la-test-key')
        && $request['avatar_id'] === AvatarSettings::SANDBOX_AVATAR_ID
        && $request['is_sandbox'] === true
        && $request['interactivity_type'] === 'PUSH_TO_TALK');

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/v1/sessions/start')
        && $request->hasHeader('Authorization', 'Bearer jwt-token'));

    expect(AvatarSession::sole())->session_id->toBe('sess-1')->sandbox->toBeTrue()->ended_at->toBeNull();
});

it('never sends what the visitor typed to LiveAvatar', function () {
    fakeLiveAvatar();
    avatarSettings();

    chatWithQuestion('Mijn nummer is 06-12345678, wat doen jullie?')->call('startAvatar');

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->body(), '06-12345678') || str_contains($request->body(), 'wat doen jullie'));
});

it('uses the own avatar with its voice outside the sandbox', function () {
    fakeLiveAvatar();
    avatarSettings(['sandbox' => false, 'avatar_id' => '11111111-2222-3333-4444-555555555555', 'voice_id' => '66666666-7777-8888-9999-000000000000']);

    chatWithQuestion()->call('startAvatar');

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/v1/sessions/token')
        && $request['avatar_id'] === '11111111-2222-3333-4444-555555555555'
        && $request['is_sandbox'] === false
        && $request['avatar_persona']['voice_id'] === '66666666-7777-8888-9999-000000000000'
        && $request['avatar_persona']['language'] === 'nl');
});

it('keeps sandbox sessions within the one minute LiveAvatar allows', function () {
    fakeLiveAvatar();
    avatarSettings(['max_session_seconds' => 600]);

    chatWithQuestion()->call('startAvatar');

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/v1/sessions/token') && $request['max_session_duration'] === 60);
});

it('does not start before the first question, and only once per chat', function () {
    fakeLiveAvatar();
    avatarSettings();

    Livewire::test(AssistantChat::class)->call('startAvatar')->assertReturned(null);

    $chat = chatWithQuestion()->call('startAvatar');
    $chat->call('startAvatar')->assertReturned(null);

    expect(AvatarSession::count())->toBe(1);
});

it('tells the browser to start the avatar once the question is in, only when it is available', function () {
    Livewire::test(AssistantChat::class)->set('prompt', 'Wat doen jullie?')->call('send')->assertNotDispatched('assistant-question-sent');

    fakeLiveAvatar();
    avatarSettings();

    Livewire::test(AssistantChat::class)->set('prompt', 'Wat doen jullie?')->call('send')->assertDispatched('assistant-question-sent');
});

it('keeps the photo when the monthly budget is used up', function () {
    fakeLiveAvatar();
    avatarSettings(['sandbox' => false, 'avatar_id' => '11111111-2222-3333-4444-555555555555', 'monthly_budget_minutes' => 5]);
    AvatarSession::factory()->create(['started_at' => now()->subMinutes(6), 'ended_at' => now()]);

    expect(app(AvatarSessions::class)->unavailableReason())->toContain('maandbudget');

    chatWithQuestion()->call('startAvatar')->assertReturned(null);
    Http::assertNothingSent();
});

it('keeps the photo when the credits at LiveAvatar are used up', function () {
    fakeLiveAvatar(creditsLeft: '1.5');
    avatarSettings(['sandbox' => false, 'avatar_id' => '11111111-2222-3333-4444-555555555555']);

    expect(app(AvatarSessions::class)->unavailableReason())->toBe('Het saldo bij LiveAvatar is op.');

    chatWithQuestion()->assertSee('data-avatar="off"', escape: false)->call('startAvatar')->assertReturned(null);
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/v1/sessions/token'));
});

it('does not ask for the credits in the sandbox, which is free', function () {
    fakeLiveAvatar(creditsLeft: '0');
    avatarSettings();

    expect(app(AvatarSessions::class)->unavailableReason())->toBeNull();
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/v1/users/credits'));
});

it('asks for the credits once in a while and does not block the avatar when that fails', function () {
    Http::fake(['api.liveavatar.test/v1/users/credits' => Http::response(['detail' => 'boom'], 500)]);
    avatarSettings(['sandbox' => false, 'avatar_id' => '11111111-2222-3333-4444-555555555555']);

    $sessions = app(AvatarSessions::class);

    expect($sessions->unavailableReason())->toBeNull()
        ->and($sessions->creditsLeft())->toBeNull();

    Http::assertSentCount(1);
});

it('keeps the photo when too many sessions are running', function () {
    fakeLiveAvatar();
    avatarSettings(['max_concurrent' => 1]);
    AvatarSession::factory()->running()->create();

    chatWithQuestion()->call('startAvatar')->assertReturned(null);
});

it('keeps the photo when LiveAvatar fails', function () {
    Http::fake(['api.liveavatar.test/*' => Http::response(['detail' => 'Invalid API key'], 401)]);
    avatarSettings();

    chatWithQuestion()->call('startAvatar')->assertReturned(null);

    expect(AvatarSession::count())->toBe(0);
});

it('needs an API key', function () {
    config(['assistant.avatar.api_key' => null]);
    avatarSettings();

    expect(app(AvatarSessions::class)->unavailableReason())->toContain('API-key');
});

it('stops the session when the chat goes idle', function () {
    fakeLiveAvatar();
    avatarSettings();

    $session = chatWithQuestion()->call('startAvatar')->effects['returns'][0];

    $this->post($session['stop_url'], ['reason' => 'idle'])->assertNoContent();

    expect(AvatarSession::sole())->ended_at->not->toBeNull()->end_reason->toBe('idle');
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/v1/sessions/stop') && $request['session_id'] === 'sess-1' && $request['reason'] === 'IDLE_TIMEOUT');
});

it('stops the session with a signed beacon when the visitor leaves', function () {
    fakeLiveAvatar();
    AvatarSession::factory()->running()->create(['session_id' => 'sess-9']);

    $this->post('/assistant/avatar/sess-9/stop')->assertForbidden();

    $this->post(URL::temporarySignedRoute('assistant.avatar.stop', now()->addMinutes(5), ['sessionId' => 'sess-9']))->assertNoContent();

    expect(AvatarSession::sole())->end_reason->toBe('page_left');
});

it('closes sessions that were never stopped', function () {
    avatarSettings(['max_session_seconds' => 600]);
    $stale = AvatarSession::factory()->create(['started_at' => now()->subHour(), 'ended_at' => null]);
    $running = AvatarSession::factory()->running()->create();

    expect(app(AvatarSessions::class)->closeStale())->toBe(1)
        ->and($stale->fresh()->end_reason)->toBe('max_duration')
        ->and($running->fresh()->ended_at)->toBeNull();
});

it('counts billed minutes per started minute, without the sandbox', function () {
    AvatarSession::factory()->create(['started_at' => now()->subSeconds(130), 'ended_at' => now()]);
    AvatarSession::factory()->create(['sandbox' => true, 'started_at' => now()->subMinutes(10), 'ended_at' => now()]);

    expect(app(AvatarSessions::class)->minutesUsedThisMonth())->toBe(3);
});

it('stores the API key from the dashboard encrypted and never shows it', function () {
    config(['assistant.avatar.api_key' => null]);
    $this->actingAs(adminUser(['email' => 'beheer@voorbeeld.nl']));

    assistantAdmin('instellingen')
        ->fillForm(['avatar' => ['new_api_key' => 'la-geheim-1234abcd']])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertSet('data.avatar.new_api_key', null)
        ->assertDontSee('la-geheim-1234abcd')
        ->assertSee('eindigt op …abcd');

    $raw = DB::table('assistant_avatar_settings')->value('api_key');

    expect($raw)->not->toBeNull()->not->toContain('la-geheim')
        ->and(AvatarSettings::current()->apiKey())->toBe('la-geheim-1234abcd')
        ->and(AvatarSettings::current()->toArray())->not->toHaveKey('api_key')
        ->and(app(AvatarSessions::class)->unavailableReason())->not->toContain('API-key');

    $this->get('/admin/assistent/instellingen')->assertDontSee('la-geheim-1234abcd');
});

it('keeps the key when the field is left empty and can forget it', function () {
    config(['assistant.avatar.api_key' => 'env-key-9999']);
    $this->actingAs(adminUser(['email' => 'beheer@voorbeeld.nl']));
    AvatarSettings::current()->setApiKey('dashboard-key-1111');

    assistantAdmin('instellingen')->fillForm(['avatar' => ['new_api_key' => '']])->call('save');
    expect(AvatarSettings::current()->apiKey())->toBe('dashboard-key-1111');

    assistantAdmin('instellingen')->fillForm(['avatar' => ['forget_api_key' => true]])->call('save');
    expect(AvatarSettings::current()->apiKey())->toBe('env-key-9999')
        ->and(AvatarSettings::current()->apiKeyHint())->toContain('.env');
});

it('falls back to the photo when the stored key cannot be decrypted anymore', function () {
    config(['assistant.avatar.api_key' => null]);
    avatarSettings();
    DB::table('assistant_avatar_settings')->update(['api_key' => 'versleuteld-met-een-oude-app-key']);

    expect(AvatarSettings::current()->apiKey())->toBeNull()
        ->and(AvatarSettings::current()->apiKeyHint())->toBe('Nog niet ingesteld');

    chatWithQuestion()->assertSee('data-avatar="off"', escape: false);
});

it('uses the key from the dashboard to call LiveAvatar', function () {
    fakeLiveAvatar();
    config(['assistant.avatar.api_key' => null]);
    avatarSettings()->setApiKey('dashboard-key-1111');

    chatWithQuestion()->call('startAvatar');

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-API-KEY', 'dashboard-key-1111'));
});

it('lets an administrator switch the avatar and the sandbox in the dashboard', function () {
    fakeLiveAvatar();
    $this->actingAs(adminUser(['email' => 'beheer@voorbeeld.nl']));

    assistantAdmin('instellingen')
        ->fillForm(['avatar' => ['enabled' => true, 'sandbox' => false, 'avatar_id' => '']])
        ->call('save')
        ->assertHasFormErrors(['avatar.avatar_id' => 'required'])
        ->fillForm(['avatar' => ['enabled' => true, 'sandbox' => false, 'avatar_id' => '11111111-2222-3333-4444-555555555555']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(AvatarSettings::current())
        ->enabled->toBeTrue()
        ->sandbox->toBeFalse()
        ->effectiveAvatarId()->toBe('11111111-2222-3333-4444-555555555555');

    $this->get('/admin/assistent/instellingen')->assertOk()->assertSee('Deze maand')->assertSee('1.110 credits');
});
