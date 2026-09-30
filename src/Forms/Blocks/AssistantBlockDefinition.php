<?php

namespace Shazzoo\Assistant\Forms\Blocks;

use Shazzoo\Assistant\Livewire\AssistantChat;
use Shazzoo\ContentStudioCore\Support\Blocks\BlockDefinition;
use Shazzoo\ContentStudioCore\Support\Fields\Definitions\MediaPicker;
use Shazzoo\ContentStudioCore\Support\Fields\Definitions\Repeater;
use Shazzoo\ContentStudioCore\Support\Fields\Definitions\TextareaField;
use Shazzoo\ContentStudioCore\Support\Fields\Definitions\TextInput;

final class AssistantBlockDefinition
{
    public static function definition(): BlockDefinition
    {
        return BlockDefinition::make('assistant.assistant')
            ->label('AI-assistent')
            ->description('Een vraagveld waar bezoekers een vraag stellen en antwoord krijgen uit de inhoud van de site.')
            ->group('Plugins')
            ->icon('heroicon-o-chat-bubble-left-right')
            ->schema([
                TextInput::make('title')
                    ->label('Kop')
                    ->columnSpan(12),
                TextareaField::make('intro')
                    ->label('Inleiding')
                    ->rows(2)
                    ->columnSpan(12),
                MediaPicker::make('image')
                    ->label('Afbeelding')
                    ->helperText('Vierkant getoond, naast of boven de chat. Staat de pratende avatar aan, dan verschijnt die op deze plek.')
                    ->fileType('image')
                    ->columnSpan(8),
                TextInput::make('image_alt')
                    ->label('Alt-tekst')
                    ->columnSpan(4),
                TextInput::make('placeholder')
                    ->label('Tekst in het vraagveld')
                    ->helperText('Leeg: "Stel [naam] een vraag…"')
                    ->columnSpan(8),
                TextInput::make('button')
                    ->label('Knoptekst')
                    ->columnSpan(4),
                Repeater::make('suggestions')
                    ->label('Voorbeeldvragen')
                    ->helperText('Hooguit '.AssistantChat::MAX_SUGGESTIONS.'. Een klik vult het vraagveld en verstuurt de vraag meteen.')
                    ->schema([
                        TextInput::make('text')->label('Vraag')->required()->columnSpan(12),
                    ])
                    ->columnSpan(12),
                TextInput::make('disclaimer')
                    ->label('Tekst onder de chat')
                    ->placeholder('Antwoorden komen van AI en kunnen onvolledig zijn.')
                    ->columnSpan(12),
            ]);
    }
}
