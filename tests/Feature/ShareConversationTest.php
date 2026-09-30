<?php

use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Shazzoo\Assistant\Assistant;
use Shazzoo\Assistant\FakeAssistant;
use Shazzoo\Assistant\Livewire\AssistantChat;
use Shazzoo\Assistant\Mail\ConversationShared;
use Shazzoo\Assistant\Models\AssistantSettings;
use Shazzoo\Assistant\Models\ConversationMessage;

beforeEach(function () {
    $this->app->instance(Assistant::class, new FakeAssistant(delayInMicroseconds: 0));
    AssistantSettings::current()->update([
        'name' => 'Joan',
        'contact_name' => 'Jasper',
        'contact_phone' => '010 123 4567',
        'contact_email' => 'info@voorbeeld.nl',
        'share_to' => 'beheer@voorbeeld.nl',
    ]);
});

function chatWithAnswer(string $question = 'Kunnen jullie me bellen op 06-12345678 over hosting?'): Testable
{
    return Livewire::test(AssistantChat::class, ['page' => '/'])
        ->set('prompt', $question)
        ->call('send')
        ->call('answer');
}

it('only offers sharing after the assistant has answered', function () {
    Livewire::test(AssistantChat::class)
        ->assertDontSee('Stuur gesprek mee')
        ->call('openShare')
        ->assertSet('isSharing', false);

    chatWithAnswer()
        ->assertSee('Wilt u dat Jasper hierop terugkomt?')
        ->call('openShare')
        ->assertSet('isSharing', true)
        ->assertSee('inclusief wat u heeft ingetikt')
        ->assertDontSee('Stel Joan een vraag');
});

it('sends the unscrubbed conversation with the contact details to Jasper', function () {
    Mail::fake();

    chatWithAnswer()
        ->call('openShare')
        ->set('shareName', 'Piet de Vries')
        ->set('shareEmail', 'piet@bedrijf.nl')
        ->set('shareNote', 'Liefst na 14 uur.')
        ->call('share')
        ->assertHasNoErrors()
        ->assertSet('isShared', true)
        ->assertSee('Uw gesprek is doorgestuurd naar Jasper');

    Mail::assertSent(ConversationShared::class, function (ConversationShared $mail): bool {
        $html = $mail->render();

        return $mail->hasTo('beheer@voorbeeld.nl')
            && $mail->hasReplyTo('piet@bedrijf.nl')
            && str_contains($html, '06-12345678')
            && str_contains($html, 'Liefst na 14 uur.')
            && str_contains($html, 'Piet de Vries')
            && str_contains($html, 'gesprek met Joan')
            && $mail->envelope()->subject === 'Joan: Piet de Vries wil dat we terugkomen op een gesprek';
    });
});

it('keeps the stored transcript scrubbed after sharing', function () {
    Mail::fake();

    chatWithAnswer()
        ->call('openShare')
        ->set('shareName', 'Piet')
        ->set('sharePhone', '06-12345678')
        ->call('share');

    expect(ConversationMessage::where('role', 'user')->sole()->content)->toContain('[TELEFOON]')->not->toContain('12345678');
});

it('clears the contact details from the component after sending', function () {
    Mail::fake();

    chatWithAnswer()
        ->call('openShare')
        ->set('shareName', 'Piet')
        ->set('shareEmail', 'piet@bedrijf.nl')
        ->call('share')
        ->assertSet('shareName', '')
        ->assertSet('shareEmail', '');
});

it('needs a name and a way to reach the visitor', function () {
    Mail::fake();

    chatWithAnswer()
        ->call('openShare')
        ->call('share')
        ->assertHasErrors(['shareName' => 'required', 'shareEmail' => 'required_without', 'sharePhone' => 'required_without'])
        ->set('shareName', 'Piet')
        ->set('shareEmail', 'geen-adres')
        ->call('share')
        ->assertHasErrors(['shareEmail' => 'email'])
        ->set('shareEmail', '')
        ->set('sharePhone', '06-12345678')
        ->call('share')
        ->assertHasNoErrors();

    Mail::assertSentCount(1);
});

it('sends only once per conversation', function () {
    Mail::fake();

    chatWithAnswer()
        ->call('openShare')
        ->set('shareName', 'Piet')
        ->set('sharePhone', '06-12345678')
        ->call('share')
        ->call('openShare')
        ->assertSet('isSharing', false)
        ->call('share');

    Mail::assertSentCount(1);
});

it('silently ignores bots that fill the hidden field', function () {
    Mail::fake();

    chatWithAnswer()
        ->call('openShare')
        ->set('shareName', 'Bot')
        ->set('shareEmail', 'bot@spam.example')
        ->set('shareWebsite', 'https://spam.example')
        ->call('share')
        ->assertSet('isShared', true);

    Mail::assertNothingSent();
});

it('shows a way out when sending fails', function () {
    Mail::shouldReceive('to->send')->andThrow(new RuntimeException('Mailserver onbereikbaar'));

    chatWithAnswer()
        ->call('openShare')
        ->set('shareName', 'Piet')
        ->set('sharePhone', '06-12345678')
        ->call('share')
        ->assertSet('isShared', false)
        ->assertSee('Versturen is niet gelukt')
        ->assertSee('010 123 4567');
});
