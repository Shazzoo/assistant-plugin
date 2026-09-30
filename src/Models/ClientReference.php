<?php

namespace Shazzoo\Assistant\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Shazzoo\Assistant\Database\Factories\ClientReferenceFactory;
use Shazzoo\Assistant\Models\Concerns\TracksEditor;

#[UseFactory(ClientReferenceFactory::class)]
#[Fillable(['client', 'sector', 'size', 'what_we_did', 'name_released', 'figures_released', 'released_at', 'recorded_in'])]
class ClientReference extends Model
{
    /** @use HasFactory<ClientReferenceFactory> */
    use HasFactory, TracksEditor;

    protected $table = 'assistant_client_references';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name_released' => 'boolean',
            'figures_released' => 'boolean',
            'released_at' => 'date',
        ];
    }

    /**
     * De naam waaronder de assistent deze klant mag noemen. Zonder vrijgave alleen sector en omvang.
     */
    public function publicName(): string
    {
        if ($this->name_released) {
            return $this->client;
        }

        return collect(['een klant in de sector '.mb_strtolower($this->sector), $this->size ? "({$this->size})" : null])
            ->filter()
            ->implode(' ');
    }
}
