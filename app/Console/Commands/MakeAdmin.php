<?php

namespace App\Console\Commands;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('make:admin
    {email? : Email address of the administrator}
    {--name= : Display name (asked for if omitted)}
    {--password= : Password. Prefer the hidden prompt: command-line values end up in shell history}')]
#[Description('Create an administrator account, or promote an existing user to administrator')]
class MakeAdmin extends Command
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = (string) ($this->argument('email') ?: $this->ask('Email address'));
        $existing = User::where('email', $email)->first();

        if ($existing !== null) {
            $existing->forceFill(['role' => UserRole::Admin])->save();
            $this->components->info("{$existing->email} is now an administrator.");

            return self::SUCCESS;
        }

        $name = (string) ($this->option('name') ?: $this->ask('Name'));
        $password = (string) ($this->option('password') ?: $this->secret('Password'));

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $password],
            [...$this->profileRules(), 'password' => $this->passwordRules()],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
        // Accounts made by an administrator are trusted: skip the email-verification step
        // (mail may not be configured yet) and set the role explicitly (it is not mass-assignable).
        $user->forceFill(['role' => UserRole::Admin, 'email_verified_at' => now()])->save();

        $this->components->info("Administrator {$user->email} created.");

        return self::SUCCESS;
    }
}
