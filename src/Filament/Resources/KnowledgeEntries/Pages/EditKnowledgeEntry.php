<?php

namespace Shazzoo\Assistant\Filament\Resources\KnowledgeEntries\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Shazzoo\Assistant\Filament\Resources\KnowledgeEntries\KnowledgeEntryResource;

class EditKnowledgeEntry extends EditRecord
{
    protected static string $resource = KnowledgeEntryResource::class;

    public function getSubheading(): ?string
    {
        return $this->record->usageProblem() === null
            ? 'De assistent gebruikt deze regel.'
            : 'De assistent gebruikt deze regel nu niet: '.mb_lcfirst($this->record->usageProblem()).'.';
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->label('Verwijderen'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
