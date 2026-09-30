<?php

namespace Shazzoo\Assistant\Filament\Resources\UnansweredQuestions\Pages;

use Filament\Resources\Pages\ListRecords;
use Shazzoo\Assistant\Filament\Resources\UnansweredQuestions\UnansweredQuestionResource;

class ListUnansweredQuestions extends ListRecords
{
    protected static string $resource = UnansweredQuestionResource::class;

    public function getSubheading(): string
    {
        return 'Vragen waarop de assistent het antwoord schuldig bleef, in de woorden van de bezoeker. Dit is de inhoudsopgave voor het kennisbestand en de site.';
    }
}
