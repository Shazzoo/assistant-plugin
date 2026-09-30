<?php

namespace Shazzoo\Assistant\Filament\Resources\Conversations;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Shazzoo\Assistant\Filament\Resources\Conversations\Pages\ListConversations;
use Shazzoo\Assistant\Filament\Resources\Conversations\Pages\ViewConversation;
use Shazzoo\Assistant\Filament\Resources\Conversations\Schemas\ConversationInfolist;
use Shazzoo\Assistant\Filament\Resources\Conversations\Tables\ConversationsTable;
use Shazzoo\Assistant\Models\Conversation;
use UnitEnum;

/**
 * Geschoonde transcripties: alleen lezen. Ze verdwijnen vanzelf na de bewaartermijn.
 */
class ConversationResource extends Resource
{
    protected static ?string $model = Conversation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Assistent';

    protected static ?string $slug = 'assistent/gesprekken';

    protected static ?string $recordTitleAttribute = 'session_number';

    protected static ?string $modelLabel = 'gesprek';

    protected static ?string $pluralModelLabel = 'gesprekken';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?int $navigationSort = 2;

    public static function infolist(Schema $schema): Schema
    {
        return ConversationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConversationsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConversations::route('/'),
            'view' => ViewConversation::route('/{record}'),
        ];
    }
}
