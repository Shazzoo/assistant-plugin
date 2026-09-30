<?php

use Illuminate\Support\Facades\File;
use Shazzoo\Assistant\Answer;
use Shazzoo\Assistant\Eval\EvalGrader;
use Shazzoo\Assistant\UnansweredReason;

beforeEach(function () {
    // Een kleine testset met elk soort controle; een site houdt zijn eigen set bij.
    config(['assistant.eval_path' => dirname(__DIR__).'/fixtures/eval']);
});

function evalCase(string $id): array
{
    $cases = json_decode(File::get(config('assistant.eval_path').'/cases.json'), true);

    return collect($cases)->firstWhere('id', $id);
}

function grader(): EvalGrader
{
    return app(EvalGrader::class);
}

it('passes the expected answers the user wrote themselves', function (string $id, Answer $answer) {
    expect(grader()->failedChecks(evalCase($id), $answer))->toBe([]);
})->with([
    'afspraak' => ['afspraak', new Answer('Zeker, de koffie staat klaar! Een afspraak maken kan door even te bellen of het contactformulier in te vullen.')],
    'storing' => ['grens-storing', new Answer('Ik weet zelf niet zo veel van storingen. Tijdens kantoortijden kunt u bellen met 010 123 4567, anders stuurt u even een mail naar service@voorbeeld.nl.')],
    'doorvraag' => ['prijs-breed', new Answer('Waar wilt u de prijs van weten: ons uurtarief, een pakket of een project?')],
    'pakketten' => ['prijs-pakketten-vergelijk', new Answer('Het basispakket kost € 25 per maand, het propakket vanaf € 50 per maand.', source: 'pagina Basispakket, pagina Propakket')],
    'uurtarief zonder bron' => ['prijs-uurtarief', new Answer('Ons uurtarief is € 150 per uur, exclusief btw.')],
    'buiten de kaders' => ['mensen-prive', new Answer('Een privénummer geef ik niet. Bel gerust 010 123 4567.', unansweredReason: UnansweredReason::OutOfBounds)],
]);

it('fails an empty answer on every case with checks', function () {
    $cases = json_decode(File::get(config('assistant.eval_path').'/cases.json'), true);

    foreach ($cases as $case) {
        // Een leeg antwoord mag alleen de vaste controles halen als die niets eisen; de beoordelaar vangt de rest.
        $failed = grader()->failedChecks($case, new Answer(''));
        $demandsSomething = collect(['moet_bevatten', 'moet_bevatten_een_van', 'bron_bevat', 'doorvraag', 'status'])
            ->contains(fn (string $key): bool => ! empty($case['checks'][$key]) && ! (($case['checks'][$key] ?? null) === 'beantwoord'));

        if ($demandsSomething) {
            expect($failed)->not->toBe([], "leeg antwoord haalt {$case['id']}");
        }
    }
});

it('catches the mistakes the assistant made during tuning', function (string $id, Answer $answer, string $expectedFailure) {
    expect(implode('; ', grader()->failedChecks(evalCase($id), $answer)))->toContain($expectedFailure);
})->with([
    'je-vorm zonder aanleiding' => ['prijs-uurtarief', new Answer('Ons uurtarief is € 150. Die prijs geven we nadat we je proces hebben gezien.'), 'je-vorm'],
    'u-vorm terwijl de bezoeker je schreef' => ['taal-je-vorm', new Answer('Wij beginnen met één proces. Wat wilt u automatiseren?'), 'u-vorm'],
    'vanaf vergeten' => ['prijs-pakketten-vergelijk', new Answer('Voor beide € 25 per maand.', source: 'pagina Basispakket'), 'bevat niet "vanaf"'],
    'bron vergeten' => ['prijs-pakketten-vergelijk', new Answer('Basis € 25, Pro vanaf € 50.', source: 'pagina Basispakket'), 'bron mist "Propakket"'],
    'x keer sneller' => ['pro-waarom', new Answer('Het propakket is drie keer sneller.'), 'bevat "keer sneller"'],
    'bedrag bij brede vraag' => ['prijs-breed', new Answer('Ons uurtarief is € 150.'), 'bevat "€"'],
    'te lang' => ['diensten-hosting', new Answer('Ja. Een. Twee. Drie. Vier. Vijf.', source: 'pagina Hosting'), '6 zinnen'],
    'bronregel bij kennisbestand' => ['assistent-leeftijd', new Answer('Ik ben in 2026 gebouwd.', source: 'Kennisbestand'), 'bronregel'],
]);

it('counts sentences without splitting times and amounts', function (string $text, int $expected) {
    expect(EvalGrader::sentenceCount($text))->toBe($expected);
})->with([
    'tijden' => ['We zijn bereikbaar van 9.00 tot 17.00 uur.', 1],
    'bedrag' => ['Het kost € 1.500. Dat is exclusief btw.', 2],
    'vraag' => ['Ja. Wil je meer weten?', 2],
    'opsomming' => ["Drie dingen:\n- AI\n- automatisering\n- legacy", 4],
]);
