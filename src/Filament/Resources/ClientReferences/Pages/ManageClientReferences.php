<?php

namespace Shazzoo\Assistant\Filament\Resources\ClientReferences\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Shazzoo\Assistant\Filament\Resources\ClientReferences\ClientReferenceResource;

class ManageClientReferences extends ManageRecords
{
    protected static string $resource = ClientReferenceResource::class;

    public function getSubheading(): string
    {
        return 'Leg bij elke vrijgave vast waar de toestemming staat. Zonder vrijgave noemt de assistent alleen sector en omvang.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nieuwe referentie'),
        ];
    }
}
