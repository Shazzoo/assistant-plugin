<?php

namespace Shazzoo\Assistant\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Shazzoo\Assistant\Database\Factories\EmployeeFactory;
use Shazzoo\Assistant\Models\Concerns\TracksEditor;

#[UseFactory(EmployeeFactory::class)]
#[Fillable(['name', 'role', 'expertise', 'hobby', 'years_of_experience', 'may_be_named', 'consented_at', 'notes'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, TracksEditor;

    protected $table = 'assistant_employees';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'may_be_named' => 'boolean',
            'consented_at' => 'date',
            'years_of_experience' => 'integer',
        ];
    }

    /**
     * Waarom de assistent deze medewerker niet noemt, of null als ze dat wel mag.
     */
    public function usageProblem(): ?string
    {
        return match (true) {
            ! $this->may_be_named => 'Niet vrijgegeven (mag_genoemd_worden = nee)',
            $this->consented_at === null => 'Geen datum van akkoord',
            preg_match(KnowledgeEntry::PLACEHOLDER_PATTERN, $this->name) === 1 => 'Naam is nog een plaatshouder',
            default => null,
        };
    }

    /**
     * Alleen medewerkers die expliciet akkoord hebben gegeven.
     */
    #[Scope]
    protected function namable(Builder $query): void
    {
        $query->where('may_be_named', true)->whereNotNull('consented_at');
    }
}
