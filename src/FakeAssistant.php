<?php

namespace Shazzoo\Assistant;

use Illuminate\Support\Str;

/**
 * Nep-assistent met vaste antwoorden, om het chatvenster te bouwen en te testen
 * zonder dat er een model aan hangt.
 */
class FakeAssistant implements Assistant
{
    public function __construct(private int $delayInMicroseconds = 35_000) {}

    public function answer(array $conversation, callable $onDelta): Answer
    {
        $question = Str::lower((string) collect($conversation)->where('role', 'user')->last()['content']);

        $answer = $this->cannedAnswerFor($question);

        foreach (preg_split('/(?<=\s)/', $answer->text) as $word) {
            $onDelta($word);

            if ($this->delayInMicroseconds > 0) {
                usleep($this->delayInMicroseconds);
            }
        }

        return $answer;
    }

    private function cannedAnswerFor(string $question): Answer
    {
        if (Str::contains($question, ['kost', 'tarief', 'prijs', 'cost', 'price'])) {
            return new Answer(
                text: 'Ons uurtarief is [BEDRAG] per uur, exclusief btw. Voor een heel traject werken wij liever met een vaste prijs per fase; die kunnen wij pas geven nadat we uw situatie hebben gezien.',
            );
        }

        if (Str::contains($question, ['data', 'privacy', 'avg', 'gdpr'])) {
            return new Answer(
                text: "Uw gegevens blijven van u. Wij halen persoonsgegevens eruit voordat er iets naar een model gaat, en er wordt niet getraind op uw invoer.\n\nHoe dat per project geregeld is, leggen wij vast in een verwerkersovereenkomst.",
                source: 'pagina Contact',
            );
        }

        if (Str::contains($question, ['doet', 'diensten', 'wat kunnen', 'services'])) {
            return new Answer(
                text: "Dat doen wij op drie manieren. Welke past hangt af van waar het bij u vastloopt:\n\n- **Advies**: eerst uitzoeken wat u nodig heeft\n- **Bouwen**: een oplossing op maat\n- **Beheer**: het daarna draaiende houden",
                source: 'pagina Diensten',
            );
        }

        return new Answer(
            text: 'Dat weet ik niet zeker, en ik ga het niet gokken. Een collega kan u dat binnen een werkdag vertellen. U kunt dit gesprek meesturen, of zelf even bellen.',
            unansweredReason: UnansweredReason::NoSource,
        );
    }
}
