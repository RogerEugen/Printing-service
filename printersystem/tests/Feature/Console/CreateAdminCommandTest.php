<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates an active administrator using hidden password prompts', function () {
    $this->artisan('app:create-admin', ['username' => 'Admin01'])
        ->expectsQuestion('Password', 'Secure-Admin-2026!')
        ->expectsQuestion('Confirm password', 'Secure-Admin-2026!')
        ->expectsOutput('Administrator [admin01] created successfully.')
        ->assertSuccessful();

    $admin = User::query()->where('username', 'admin01')->sole();

    expect($admin->role)->toBe(UserRole::Admin)
        ->and($admin->is_active)->toBeTrue()
        ->and(Hash::check('Secure-Admin-2026!', $admin->password))->toBeTrue()
        ->and($admin->password)->not->toBe('Secure-Admin-2026!');
});

it('does not replace an existing account', function () {
    $employee = User::factory()->employee()->create(['username' => 'admin01']);

    $this->artisan('app:create-admin', ['username' => 'admin01'])
        ->expectsOutput('The username [admin01] already exists.')
        ->assertFailed();

    expect($employee->fresh()->role)->toBe(UserRole::Employee);
});
