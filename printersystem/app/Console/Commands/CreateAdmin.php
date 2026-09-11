<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

#[Signature('app:create-admin {username? : Lowercase company username}')]
#[Description('Create the first administrator using a hidden password prompt')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $usernameArgument = $this->argument('username');
        $username = Str::lower(trim(is_string($usernameArgument)
            ? $usernameArgument
            : (string) $this->ask('Username', 'admin01')));

        if (User::query()->where('username', $username)->exists()) {
            $this->error("The username [{$username}] already exists.");

            return self::FAILURE;
        }

        $password = (string) $this->secret('Password');
        $passwordConfirmation = (string) $this->secret('Confirm password');
        $validator = Validator::make([
            'username' => $username,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'username' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[a-z0-9][a-z0-9._-]*$/',
                Rule::unique(User::class, 'username'),
            ],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->mixedCase()->numbers()->symbols(),
            ],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        User::query()->create([
            'username' => $username,
            'password' => $password,
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $this->info("Administrator [{$username}] created successfully.");

        return self::SUCCESS;
    }
}
