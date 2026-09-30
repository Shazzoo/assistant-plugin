<?php

namespace Shazzoo\Assistant;

interface Assistant
{
    /**
     * Beantwoord de laatste vraag in het gesprek.
     *
     * @param  list<array{role: 'user'|'assistant', content: string}>  $conversation
     * @param  callable(string): void  $onDelta  Wordt aangeroepen met elk nieuw stukje tekst.
     */
    public function answer(array $conversation, callable $onDelta): Answer;
}
