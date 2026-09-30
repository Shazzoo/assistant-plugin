<?php

namespace Shazzoo\Assistant\Filament\Resources\Conversations\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Shazzoo\Assistant\UnansweredReason;

class ConversationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Begonnen')
                            ->dateTime('j F Y, H:i'),
                        TextEntry::make('page')
                            ->label('Pagina')
                            ->placeholder('onbekend'),
                        TextEntry::make('language')
                            ->label('Taal')
                            ->placeholder('onbekend'),
                        TextEntry::make('session_number')
                            ->label('Sessienummer')
                            ->copyable()
                            ->fontFamily('mono')
                            ->size('xs'),
                    ]),

                RepeatableEntry::make('messages')
                    ->label('Gesprek')
                    ->contained(false)
                    ->schema([
                        TextEntry::make('content')
                            ->label(fn ($record): string => $record->role === 'user' ? 'Bezoeker' : 'Assistent')
                            ->markdown()
                            ->prose(),
                        TextEntry::make('meta')
                            ->hiddenLabel()
                            ->state(fn ($record): ?string => $record->role === 'user' ? null : collect([
                                UnansweredReason::tryFrom((string) $record->status)?->getLabel() ?? ($record->status === 'fout' ? 'Fout bij ophalen antwoord' : null),
                                $record->source ? "Bron: {$record->source}" : null,
                            ])->filter()->implode(' · '))
                            ->color(fn ($record): string => UnansweredReason::tryFrom((string) $record->status) ? 'danger' : 'gray')
                            ->size('xs')
                            ->hidden(fn ($record): bool => $record->role === 'user' || ($record->status === 'beantwoord' && $record->source === null)),
                    ]),
            ]);
    }
}
