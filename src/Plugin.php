<?php

namespace Shazzoo\Assistant;

final class Plugin
{
    public static function key(): string
    {
        return 'shazzoo/assistant';
    }

    public static function provider(): string
    {
        return AssistantServiceProvider::class;
    }
}
