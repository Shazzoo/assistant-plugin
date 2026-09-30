<?php

namespace Shazzoo\Assistant\Filament\Resources\Conversations\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;
use Shazzoo\Assistant\Filament\Resources\Conversations\ConversationResource;

class ViewConversation extends ViewRecord
{
    protected static string $resource = ConversationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->label('Verwijderen'),
        ];
    }
}
