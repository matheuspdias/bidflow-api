<?php

use App\Modules\User\Infrastructure\Persistence\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('public');
});

test('an authenticated user can upload an avatar', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/profile/avatar', [
        'avatar' => UploadedFile::fake()->image('avatar.jpg'),
    ])
        ->assertOk()
        ->assertJsonPath('data.avatar_path', fn (?string $path) => $path !== null);

    $path = $user->fresh()->avatar_path;

    Storage::disk('public')->assertExists($path);
});

test('uploading a non-image avatar fails validation', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/profile/avatar', [
        'avatar' => UploadedFile::fake()->create('resume.pdf', 100),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('avatar');
});

test('an authenticated user can remove their avatar', function () {
    $user = User::factory()->create(['avatar_path' => 'avatars/old.jpg']);
    Storage::disk('public')->put('avatars/old.jpg', 'fake-contents');
    Sanctum::actingAs($user, ['*']);

    $this->deleteJson('/api/profile/avatar')
        ->assertOk()
        ->assertJsonPath('data.avatar_path', null);

    Storage::disk('public')->assertMissing('avatars/old.jpg');
});
