<?php

namespace Shazzoo\Assistant\Filament\Resources\UnansweredQuestions\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Shazzoo\Assistant\Filament\Actions\AddToKnowledgeAction;
use Shazzoo\Assistant\Filament\Resources\UnansweredQuestions\UnansweredQuestionResource;
use Shazzoo\Assistant\UnansweredStatus;

class EditUnansweredQuestion extends EditRecord
{
    protected static string $resource = UnansweredQuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AddToKnowledgeAction::make()
                ->after(fn () => $this->redirect(static::getResource()::getUrl('index'))),
            DeleteAction::make()
                ->label('Verwijderen')
                ->modalDescription('Bijvoorbeeld als de vraag na het schonen onleesbaar is geworden. Wat je afhandelt, bewaar je juist: dat is de besluitenlijst.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $status = $data['status'] instanceof UnansweredStatus ? $data['status'] : UnansweredStatus::tryFrom((string) $data['status']);

        $data['resolved_at'] = $status === UnansweredStatus::Resolved ? ($this->record->resolved_at ?? now()) : null;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
