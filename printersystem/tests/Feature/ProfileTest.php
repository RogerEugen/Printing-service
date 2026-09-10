<?php

use App\Models\User;

it('displays the profile page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/profile')->assertOk();
});

it('updates a username without accepting unexpected attributes', function () {
    $user = User::factory()->create(['username' => 'old.username']);

    $response = $this->actingAs($user)->patch('/profile', [
        'username' => 'NEW.USERNAME',
        'role' => 'admin',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect('/profile');
    expect($user->fresh()->username)->toBe('new.username')
        ->and($user->fresh()->isEmployee())->toBeTrue();
});
