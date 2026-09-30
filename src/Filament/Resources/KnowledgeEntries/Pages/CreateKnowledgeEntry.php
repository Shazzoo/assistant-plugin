<?php

namespace Shazzoo\Assistant\Filament\Resources\KnowledgeEntries\Pages;

use Filament\Resources\Pages\CreateRecord;
use Shazzoo\Assistant\Filament\Resources\KnowledgeEntries\KnowledgeEntryResource;

class CreateKnowledgeEntry extends CreateRecord
{
    protected static string $resource = KnowledgeEntryResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
