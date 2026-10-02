<?php

use Shazzoo\Assistant\Models\AvatarSettings;

/**
 * @return array<int, array<string, mixed>>
 */
function apiResources(): array
{
    $composer = json_decode(file_get_contents(__DIR__.'/../../composer.json'), true);

    return $composer['extra']['content-studio']['api_resources'];
}

it('declares only fields the models can store', function () {
    $writableResources = collect(apiResources())->filter(fn (array $resource): bool => $resource['writable'] ?? true);

    foreach ($writableResources as $resource) {
        $model = new $resource['model'];

        foreach ($resource['fields'] as $field) {
            expect($model->isFillable($field['name']))->toBeTrue("{$resource['key']}.{$field['name']} is not fillable");
        }
    }
});

it('keeps conversations read-only and the avatar settings to their single row', function () {
    $resources = collect(apiResources())->keyBy('key');

    expect($resources['conversations'])->toMatchArray(['writable' => false, 'creatable' => false])
        ->and($resources['avatar_settings']['creatable'])->toBeFalse();
});

it('never exposes the LiveAvatar API key', function () {
    $avatarFields = collect(apiResources())->firstWhere('key', 'avatar_settings')['fields'];

    expect(array_column($avatarFields, 'name'))->not->toContain('api_key')
        ->and((new AvatarSettings)->isFillable('api_key'))->toBeFalse();
});
