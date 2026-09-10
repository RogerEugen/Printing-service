<?php

use App\Models\User;

it('authenticates an employee with username and password', function () {
    $user = User::factory()->create(['username' => 'employee01']);

    $response = $this->post('/login', ['username' => 'EMPLOYEE01', 'password' => 'password']);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
});

it('redirects an admin to the admin dashboard after login', function () {
    $admin = User::factory()->admin()->create(['username' => 'admin']);

    $response = $this->post('/login', ['username' => 'admin', 'password' => 'password']);

    $this->assertAuthenticatedAs($admin);
    $response->assertRedirect(route('admin.dashboard', absolute: false));
});

it('rejects disabled users', function () {
    User::factory()->disabled()->create(['username' => 'disabled.user']);

    $this->post('/login', ['username' => 'disabled.user', 'password' => 'password']);

    $this->assertGuest();
});

it('forbids employee access to admin routes', function () {
    $employee = User::factory()->create();

    $this->actingAs($employee)->get('/admin/dashboard')->assertForbidden();
});

it('forbids admin access to employee routes', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/dashboard')->assertForbidden();
});

it('does not expose self registration', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [])->assertNotFound();
});
