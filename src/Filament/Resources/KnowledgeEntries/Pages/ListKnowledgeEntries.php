<?php

namespace Shazzoo\Assistant\Filament\Resources\KnowledgeEntries\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Shazzoo\Assistant\Filament\Resources\KnowledgeEntries\KnowledgeEntryResource;

class ListKnowledgeEntries extends ListRecords
{
    protected static string $resource = KnowledgeEntryResource::class;

    public function getSubheading(): string
    {
        return 'Wat de assistent naast de website mag weten. Staat een antwoord nergens, dan zegt ze dat ze het niet weet.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nieuwe vraag'),
        ];
    }
}
