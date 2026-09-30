<?php

namespace Shazzoo\Assistant\Filament\Resources\Employees\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Shazzoo\Assistant\Filament\Resources\Employees\EmployeeResource;

class ManageEmployees extends ManageRecords
{
    protected static string $resource = EmployeeResource::class;

    public function getSubheading(): string
    {
        return "De assistent noemt alleen wat hier staat: naam, functie, vakgebied, ervaring en hobby's. Nooit beschikbaarheid of privégegevens.";
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nieuwe medewerker'),
        ];
    }
}
