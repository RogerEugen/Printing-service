<?php

use App\Models\Printer;
use App\Models\User;
use Illuminate\Support\Str;

it('stores only a hash and shows a new printer token once', function () {
    $admin = User::factory()->admin()->create();
    Str::createRandomStringsUsing(fn (int $length): string => str_repeat('t', $length));

    try {
        $response = $this->actingAs($admin)->post('/admin/printers', [
            'name' => 'Main Printer',
            'device_name' => 'OFFICE-01',
            'location' => 'Reception',
        ]);

        $printer = Printer::query()->sole();
        $response->assertRedirect(route('admin.printers.show', $printer));
        expect($printer->getRawOriginal('api_token_hash'))
            ->toBe(hash('sha256', str_repeat('t', 80)))
            ->not->toContain(str_repeat('t', 80));
        $this->followingRedirects()->get(route('admin.printers.show', $printer))
            ->assertSee(str_repeat('t', 80));
        $this->get(route('admin.printers.show', $printer))
            ->assertDontSee(str_repeat('t', 80));
    } finally {
        Str::createRandomStringsNormally();
    }
});

it('prevents the only active admin from losing admin access', function () {
    $admin = User::factory()->admin()->create(['username' => 'only.admin']);

    $response = $this->actingAs($admin)->put(route('admin.users.update', $admin), [
        'username' => 'only.admin',
        'role' => 'employee',
        'is_active' => true,
        'password' => '',
        'password_confirmation' => '',
    ]);

    $response->assertInvalid('role');
    expect($admin->fresh()->isAdmin())->toBeTrue();
});
