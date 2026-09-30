<?php

namespace Shazzoo\Assistant\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Shazzoo\Assistant\Avatar\AvatarSessions;
use Shazzoo\Assistant\Models\AvatarSession;
use Shazzoo\Assistant\Models\AvatarSettings;
use UnitEnum;

/**
 * Instellingen van de pratende avatar (HeyGen LiveAvatar): aan/uit, sandbox of de eigen avatar, limieten.
 *
 * @property-read Schema $form
 */
class TalkingAvatar extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedVideoCamera;

    protected static string|UnitEnum|null $navigationGroup = 'Assistent';

    protected static ?string $slug = 'assistent/pratende-avatar';

    protected static ?string $navigationLabel = 'Pratende avatar';

    protected static ?string $title = 'Pratende avatar';

    protected static ?int $navigationSort = 4;

    protected string $view = 'assistant::filament.talking-avatar';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(AvatarSettings::current()->toArray());
    }

    public function getSubheading(): string
    {
        return 'De assistent spreekt zijn antwoorden uit met een live avatar van HeyGen. Alleen zijn antwoorden gaan naar HeyGen (VS), nooit wat de bezoeker typt, en er wordt geen microfoon gebruikt.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Aan of uit')
                    ->columns(2)
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Pratende avatar aan')
                            ->helperText('Uit: de foto staat er, zoals altijd.'),
                        Toggle::make('sandbox')
                            ->label('Sandbox (testen)')
                            ->helperText('Gratis, maar met de testavatar "Wayne" en sessies van ongeveer een minuut. Zet uit om de eigen avatar te gebruiken; dan rekent LiveAvatar per minuut.')
                            ->live(),
                    ]),

                Section::make('API-key van LiveAvatar')
                    ->description(fn (): string => AvatarSettings::current()->apiKeyHint().'. De sleutel wordt versleuteld opgeslagen en is hier nooit terug te zien.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('new_api_key')
                            ->label('Nieuwe API-key')
                            ->helperText('Leeg laten om de huidige sleutel te houden. Te vinden in app.liveavatar.com onder API keys.')
                            ->password()
                            ->autocomplete('off')
                            ->maxLength(500),
                        Toggle::make('forget_api_key')
                            ->label('Sleutel uit het dashboard wissen')
                            ->helperText('Staat er een sleutel in .env, dan wordt die daarna weer gebruikt.'),
                    ]),

                Section::make('De eigen avatar')
                    ->description('Maak de assistent aan als image avatar in app.liveavatar.com en kies daar een Nederlandse stem; plak de id\'s hier.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('avatar_id')
                            ->label('Avatar-id')
                            ->uuid()
                            ->required(fn (Get $get): bool => ! $get('sandbox'))
                            ->helperText('Wordt in de sandbox genegeerd.'),
                        TextInput::make('voice_id')
                            ->label('Stem-id')
                            ->uuid()
                            ->helperText('Leeg: de standaardstem van de avatar. Een image avatar heeft geen eigen stem, dus vul dit in voor de eigen avatar.'),
                        TextInput::make('context_id')
                            ->label('Context-id')
                            ->uuid()
                            ->helperText('Alleen nodig als de avatar zonder context stil blijft.'),
                        Select::make('language')
                            ->label('Taal')
                            ->options(['nl' => 'Nederlands', 'en' => 'Engels'])
                            ->required(),
                        Select::make('quality')
                            ->label('Beeldkwaliteit')
                            ->options(['low' => 'Laag', 'medium' => 'Middel', 'high' => 'Hoog', 'very_high' => 'Zeer hoog'])
                            ->required(),
                    ]),

                Section::make('Kosten en limieten')
                    ->columns(2)
                    ->schema([
                        TextInput::make('monthly_budget_minutes')
                            ->label('Maandbudget (minuten)')
                            ->helperText('Op = de foto blijft staan tot de volgende maand. Sandbox telt niet mee.')
                            ->numeric()->minValue(0)->required(),
                        TextInput::make('max_concurrent')
                            ->label('Maximaal tegelijk')
                            ->numeric()->minValue(1)->maxValue(50)->required(),
                        TextInput::make('idle_stop_seconds')
                            ->label('Stoppen na stilte (seconden)')
                            ->helperText('HeyGen rekent door tot 5 minuten stilte; wij stoppen eerder.')
                            ->numeric()->minValue(20)->maxValue(300)->required(),
                        TextInput::make('max_session_seconds')
                            ->label('Maximale sessieduur (seconden)')
                            ->numeric()->minValue(60)->maxValue(3600)->required(),
                    ]),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $settings = AvatarSettings::current();

        $settings->update(Arr::except($state, ['new_api_key', 'forget_api_key']));

        if ($state['forget_api_key'] ?? false) {
            $settings->setApiKey(null);
        } elseif (filled($state['new_api_key'] ?? null)) {
            $settings->setApiKey($state['new_api_key']);
        }

        // De sleutel nooit in de componentstatus laten staan.
        $this->form->fill($settings->fresh()->toArray());

        $reason = app(AvatarSessions::class)->unavailableReason();

        Notification::make()
            ->title('Opgeslagen')
            ->body($reason === null ? 'De avatar staat klaar voor de volgende bezoeker.' : $reason)
            ->color($reason === null ? 'success' : 'warning')
            ->send();
    }

    /**
     * @return array{available: ?string, credits: ?float, used: int, budget: int, sandbox: bool, running: int, recent: Collection<int, AvatarSession>}
     */
    public function status(): array
    {
        $settings = AvatarSettings::current();
        $sessions = app(AvatarSessions::class);

        return [
            'available' => $sessions->unavailableReason(),
            'credits' => $sessions->creditsLeft(),
            'used' => $sessions->minutesUsedThisMonth(),
            'budget' => $settings->monthly_budget_minutes,
            'sandbox' => $settings->sandbox,
            'running' => AvatarSession::query()->running()->count(),
            'recent' => AvatarSession::query()->latest('started_at')->limit(10)->get(),
        ];
    }
}
