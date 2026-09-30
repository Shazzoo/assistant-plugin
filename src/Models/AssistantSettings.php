<?php

namespace Shazzoo\Assistant\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Shazzoo\Assistant\Models\Concerns\TracksEditor;

/**
 * Wat per site verschilt: wie de assistent is, welke instructies hij krijgt en
 * naar wie hij doorverwijst. Er is precies één rij, te wijzigen in het beheer.
 */
#[Fillable(['name', 'company', 'greeting', 'instructions', 'contact_name', 'contact_phone', 'contact_email', 'share_to', 'public_details', 'max_question_length', 'max_questions'])]
class AssistantSettings extends Model
{
    use TracksEditor;

    protected $table = 'assistant_settings';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'public_details' => 'array',
            'max_question_length' => 'integer',
            'max_questions' => 'integer',
        ];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1]);
    }

    public function assistantName(): string
    {
        return $this->name ?: __('assistant::assistant.default_name');
    }

    public function companyName(): string
    {
        return $this->company ?: config('app.name');
    }

    /**
     * Naar wie de assistent verwijst als hij iets niet weet.
     */
    public function contactName(): string
    {
        return $this->contact_name ?: __('assistant::assistant.default_contact');
    }

    /**
     * Waar "Stuur dit gesprek mee" naartoe gaat; zonder adres staat die knop uit.
     */
    public function shareAddress(): ?string
    {
        return $this->share_to ?: $this->contact_email;
    }

    /**
     * Openbare gegevens van de organisatie zelf, die bij het schonen blijven staan.
     *
     * @return list<string>
     */
    public function publicDetails(): array
    {
        return array_values(array_filter([
            $this->contact_phone,
            $this->contact_email,
            $this->share_to,
            ...($this->public_details ?? []),
        ], filled(...)));
    }
}
