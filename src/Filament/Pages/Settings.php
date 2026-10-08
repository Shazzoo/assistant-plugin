<?php

namespace Shazzoo\Assistant\Filament\Pages;

use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use FinnWiel\ShazzooMedia\Components\Forms\ShazzooMediaPicker;
use Illuminate\Support\Arr;
use Illuminate\Support\Number;
use Shazzoo\Assistant\Avatar\AvatarSessions;
use Shazzoo\Assistant\Instructions;
use Shazzoo\Assistant\Models\AssistantSettings;
use Shazzoo\Assistant\Models\AvatarSettings;

/**
 * Wat per site verschilt: naam, contactgegevens, limieten, instructies en de pratende avatar.
 *
 * @property-read Schema $form
 */
class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $cluster = Assistant::class;

    protected static ?string $navigationLabel = 'Instellingen';

    protected static ?string $title = 'Instellingen';

    protected static ?string $slug = 'instellingen';

    protected static ?int $navigationSort = 3;

    protected string $view = 'assistant::filament.settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->fillSettings();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Opslaan')
                ->icon('heroicon-o-check')
                ->keyBindings(['mod+s'])
                ->action(fn () => $this->save()),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Wie de assistent is')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Naam')
                            ->placeholder(__('assistant::assistant.default_name'))
                            ->maxLength(50),
                        TextInput::make('company')
                            ->label('Organisatie')
                            ->helperText('Leeg: de naam van de site.')
                            ->placeholder(config('app.name'))
                            ->maxLength(100),
                        Textarea::make('greeting')
                            ->label('Begroeting')
                            ->helperText('Staat bovenaan de chat voordat de bezoeker iets vraagt. Leeg: geen begroeting.')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
                Section::make('Doorverbinden')
                    ->description('Naar wie de assistent verwijst als hij iets niet weet of niet mag zeggen.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('contact_name')
                            ->label('Naam van de contactpersoon')
                            ->placeholder(__('assistant::assistant.default_contact'))
                            ->maxLength(100),
                        TextInput::make('contact_phone')->label('Telefoon')->tel()->maxLength(30),
                        TextInput::make('contact_email')->label('E-mail')->email()->maxLength(150),
                        TextInput::make('share_to')
                            ->label('"Stuur dit gesprek mee" gaat naar')
                            ->helperText('Leeg: het e-mailadres hierboven. Zonder adres staat de knop uit.')
                            ->email()
                            ->maxLength(150),
                        TagsInput::make('public_details')
                            ->label('Andere openbare gegevens')
                            ->helperText('Adressen en nummers van de organisatie zelf die bij het schonen van gesprekken mogen blijven staan. Telefoon en e-mail hierboven staan er al in.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Limieten')
                    ->description(fn (): string => 'Provider: '.config('assistant.provider').', model: '.(config('assistant.model') ?: 'standaard').'. Voor alle bezoekers samen hooguit '.config('assistant.rate_limits.global_per_minute').' vragen per minuut.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('max_question_length')
                            ->label('Tekens per vraag')
                            ->numeric()->minValue(50)->maxValue(2000)->required(),
                        TextInput::make('max_questions')
                            ->label('Vragen per gesprek')
                            ->helperText('Daarna verwijst de assistent naar een mens.')
                            ->numeric()->minValue(1)->maxValue(100)->required(),
                    ]),
                Section::make('Instructies')
                    ->description('Zolang je het sjabloon van de plugin niet wijzigt, krijgt de site verbeteringen aan het sjabloon vanzelf mee. Je kunt {{assistant}}, {{company}}, {{contact}}, {{phone}} en {{email}} gebruiken. Laat een wijziging eerst door de testset gaan (php artisan assistant:eval).')
                    ->collapsed()
                    ->schema([
                        Textarea::make('instructions')
                            ->label('Instructie voor de assistent')
                            ->rows(24)
                            ->hintAction(
                                Action::make('useTemplate')
                                    ->label('Terugzetten naar het sjabloon')
                                    ->button()
                                    ->color('gray')
                                    ->size('sm')
                                    ->icon('heroicon-o-arrow-uturn-left')
                                    ->requiresConfirmation()
                                    ->modalHeading('Instructie terugzetten naar het sjabloon?')
                                    ->modalDescription('Je eigen wijzigingen in de instructie gaan verloren. Na opslaan volgt de assistent weer het sjabloon van de plugin.')
                                    ->modalSubmitActionLabel('Terugzetten')
                                    ->action(fn () => $this->data['instructions'] = Instructions::defaultTemplate()),
                            ),
                    ]),
                Section::make('Pratende avatar')
                    ->description(fn (): string => $this->avatarStatus())
                    ->statePath('avatar')
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Pratende avatar aan')
                            ->helperText('Uit: de foto staat er, zoals altijd.'),
                        Toggle::make('sandbox')
                            ->label('Sandbox (testen)')
                            ->helperText('Gratis, maar met de testavatar "Wayne" en sessies van ongeveer een minuut. Zet uit om de eigen avatar te gebruiken; dan rekent LiveAvatar per minuut.')
                            ->live(),
                        TextInput::make('new_api_key')
                            ->label('Nieuwe API-key van LiveAvatar')
                            ->helperText(fn (): string => AvatarSettings::current()->apiKeyHint().'. Leeg laten om de huidige sleutel te houden; hij wordt versleuteld opgeslagen.')
                            ->password()
                            ->autocomplete('off')
                            ->maxLength(500)
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (AvatarSettings::isClaudeKey($value)) {
                                    $fail('Dit is een API-key van Claude (Anthropic). Vul hier de sleutel van LiveAvatar in.');
                                }
                            }),
                        Toggle::make('forget_api_key')
                            ->label('Sleutel uit het beheer wissen')
                            ->helperText('Staat er een sleutel in .env, dan wordt die daarna weer gebruikt.'),
                        ShazzooMediaPicker::make('fallback_image_id')
                            ->label('Fallbackafbeelding')
                            ->helperText('Wordt getoond zolang de pratende avatar niet spreekt. Leeg laten om alleen de avatar te tonen.')
                            ->fileType('image')
                            ->columnSpanFull(),
                        TextInput::make('avatar_id')
                            ->label('Avatar-id')
                            ->uuid()
                            ->required(fn (Get $get): bool => (bool) $get('enabled') && ! $get('sandbox'))
                            ->helperText('Uit app.liveavatar.com; wordt in de sandbox genegeerd.'),
                        TextInput::make('voice_id')
                            ->label('Stem-id')
                            ->uuid()
                            ->helperText('Een image avatar heeft geen eigen stem, dus vul dit in voor de eigen avatar.'),
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
        $avatar = $state['avatar'] ?? [];

        // Het ongewijzigde sjabloon niet opslaan: dan volgt de site latere verbeteringen eraan.
        if (Instructions::isDefaultTemplate($state['instructions'] ?? null)) {
            $state['instructions'] = null;
        }

        AssistantSettings::current()->update(Arr::except($state, ['avatar']));

        $avatarSettings = AvatarSettings::current();
        $avatarSettings->update(Arr::except($avatar, ['new_api_key', 'forget_api_key']));

        if ($avatar['forget_api_key'] ?? false) {
            $avatarSettings->setApiKey(null);
        } elseif (filled($avatar['new_api_key'] ?? null)) {
            $avatarSettings->setApiKey($avatar['new_api_key']);
        }

        // De sleutel nooit in de componentstatus laten staan.
        $this->fillSettings();

        Notification::make()->title('Opgeslagen')->success()->send();
    }

    private function fillSettings(): void
    {
        $settings = AssistantSettings::current();

        $this->form->fill([
            ...$settings->toArray(),
            // Het sjabloon als tekst om in te bewerken, niet als placeholder.
            'instructions' => filled($settings->instructions) ? $settings->instructions : Instructions::defaultTemplate(),
            'avatar' => AvatarSettings::current()->toArray(),
        ]);
    }

    private function avatarStatus(): string
    {
        $sessions = app(AvatarSessions::class);
        $settings = AvatarSettings::current();
        $reason = $sessions->unavailableReason();

        $credits = $sessions->creditsLeft();

        return ($reason ?? ($settings->sandbox ? 'Beschikbaar in de sandbox.' : 'Beschikbaar.'))
            .' Deze maand '.$sessions->minutesUsedThisMonth().' van '.$settings->monthly_budget_minutes.' minuten gebruikt.'
            .($credits === null ? '' : ' Saldo bij LiveAvatar: '.Number::format($credits, locale: 'nl').' credits.');
    }
}
