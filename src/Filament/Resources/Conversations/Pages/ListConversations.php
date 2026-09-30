<?php

namespace Shazzoo\Assistant\Filament\Resources\Conversations\Pages;

use Filament\Resources\Pages\ListRecords;
use Shazzoo\Assistant\Filament\Resources\Conversations\ConversationResource;

class ListConversations extends ListRecords
{
    protected static string $resource = ConversationResource::class;

    public function getSubheading(): string
    {
        return 'Geschoonde gesprekken, zonder contactgegevens. Ze worden na '.config('assistant.transcripts.retention_days').' dagen automatisch verwijderd.';
    }
}
