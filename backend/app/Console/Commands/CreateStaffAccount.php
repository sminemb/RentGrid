<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateStaffAccount extends Command
{
    protected $signature = 'rentgrid:staff-create {email} {name}';

    protected $description = 'Provision a new staff account with a password entered at the prompt';

    public function handle(): int
    {
        $account = [
            'name' => trim($this->argument('name')),
            'email' => Str::lower(trim($this->argument('email'))),
        ];
        $validator = Validator::make($account, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:254', 'unique:users,email'],
        ]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $password = (string) $this->secret('Password');
        if (mb_strlen($password) < 12 || strlen($password) > 72) {
            $this->error('Use at least 12 characters and at most 72 bytes for the password.');

            return self::FAILURE;
        }

        if ($this->secret('Confirm password') !== $password) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }

        $user = new User([...$account, 'password' => $password]);
        $user->is_staff = true;
        $user->save();
        $this->info('Staff account created.');

        return self::SUCCESS;
    }
}
