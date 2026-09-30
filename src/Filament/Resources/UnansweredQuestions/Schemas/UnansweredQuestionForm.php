<?php

namespace Shazzoo\Assistant\Filament\Resources\UnansweredQuestions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Shazzoo\Assistant\UnansweredStatus;

class UnansweredQuestionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('De vraag')
                    ->description('Zoals de bezoeker hem stelde, na het schonen van contactgegevens.')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('question')
                            ->hiddenLabel()
                            ->size('lg')
                            ->columnSpanFull(),
                        TextEntry::make('times_asked')
                            ->label('Aantal keer gesteld'),
                        TextEntry::make('first_seen_at')
                            ->label('Eerst gezien')
                            ->dateTime('j F Y, H:i'),
                        TextEntry::make('last_seen_at')
                            ->label('Laatst gezien')
                            ->since(),
                        TextEntry::make('reason')
                            ->label('Reden')
                            ->badge()
                            ->helperText(fn ($record) => $record?->reason?->getDescription()),
                        TextEntry::make('page')
                            ->label('Pagina')
                            ->placeholder('onbekend'),
                    ]),

                Section::make('Afhandeling')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->options(UnansweredStatus::class)
                            ->required()
                            ->live(),
                        TextInput::make('assignee')
                            ->label('Wie pakt het op')
                            ->maxLength(100)
                            ->default(fn (): ?string => auth()->user()?->name),
                        Textarea::make('resolution')
                            ->label('Wat is er veranderd')
                            ->helperText('Welke regel in het kennisbestand, welke alinea op welke pagina, of "bewust niet". Afvinken zonder wijziging is geen afhandeling.')
                            ->rows(3)
                            ->required(fn (Get $get): bool => $get('status') === UnansweredStatus::Resolved || $get('status') === UnansweredStatus::Resolved->value)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
