<?php

namespace Shazzoo\Assistant;

use Illuminate\Support\Facades\File;
use Shazzoo\Assistant\Models\AssistantSettings;

/**
 * De instructies van de assistent, met naam en contactgegevens ingevuld: precies wat het model krijgt.
 *
 * Zonder eigen instructies in het beheer geldt het algemene sjabloon uit de plugin.
 */
class Instructions
{
    public function __construct(private AssistantSettings $settings) {}

    public static function defaultTemplate(): string
    {
        return File::get(dirname(__DIR__).'/resources/instructions/default.md');
    }

    /**
     * Leeg of gelijk aan het sjabloon; regeleinden en witruimte aan begin en eind tellen niet.
     */
    public static function isDefaultTemplate(?string $instructions): bool
    {
        $normalize = fn (string $text): string => trim(str_replace("\r\n", "\n", $text));

        return blank($instructions) || $normalize($instructions) === $normalize(self::defaultTemplate());
    }

    public function render(): string
    {
        $template = filled($this->settings->instructions) ? $this->settings->instructions : self::defaultTemplate();

        return strtr($template, [
            '{{assistant}}' => $this->settings->assistantName(),
            '{{company}}' => $this->settings->companyName(),
            '{{contact}}' => $this->contactName(),
            '{{phone}}' => (string) $this->settings->contact_phone,
            // De namen uit de eerste versie, zodat bestaande instructies blijven werken.
            '{{telefoon}}' => (string) $this->settings->contact_phone,
            '{{email}}' => (string) $this->settings->contact_email,
        ]);
    }

    public function contactName(): string
    {
        return $this->settings->contactName();
    }
}
