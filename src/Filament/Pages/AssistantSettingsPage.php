<?php

namespace Shazzoo\Assistant\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Shazzoo\Assistant\Instructions;
use Shazzoo\Assistant\Models\AssistantSettings;
use UnitEnum;

/**
 * Wat per site verschilt: naam, instructies, contactgegevens en limieten.
 *
 * @property-read Schema $form
 */
class AssistantSettingsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Assistent';

    protected static ?string $slug = 'assistent/instellingen';

    protected static ?string $navigationLabel = 'Instellingen';

    protected static ?string $title = 'Instellingen van de assistent';

    protected static ?int $navigationSort = 5;

    protected string $view = 'assistant::filament.settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(AssistantSettings::current()->toArray());
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
                        TextInput::make('contact_phone')
                            ->label('Telefoon')
                            ->tel()
                            ->maxLength(30),
                        TextInput::make('contact_email')
                            ->label('E-mail')
                            ->email()
                            ->maxLength(150),
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
                    ->description('Leeg: het algemene sjabloon van de plugin. Je kunt {{assistant}}, {{company}}, {{contact}}, {{phone}} en {{email}} gebruiken. Laat een wijziging eerst door de testset gaan (php artisan assistant:eval).')
                    ->collapsible()
                    ->schema([
                        Textarea::make('instructions')
                            ->hiddenLabel()
                            ->rows(24)
                            ->placeholder(fn (): string => Instructions::defaultTemplate())
                            ->hintAction(
                                Action::make('useTemplate')
                                    ->label('Sjabloon invullen om aan te passen')
                                    ->action(fn () => $this->data['instructions'] = Instructions::defaultTemplate()),
                            ),
                    ]),
            ]);
    }

    public function save(): void
    {
        AssistantSettings::current()->update($this->form->getState());

        Notification::make()->title('Opgeslagen')->success()->send();
    }
}
