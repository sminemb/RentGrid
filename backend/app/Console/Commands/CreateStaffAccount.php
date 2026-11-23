<?php

namespace App\Console\Commands;

use App\Services\StaffAccountService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class CreateStaffAccount extends Command
{
    protected $signature = 'rentgrid:staff-create {email} {name} {--admin : Allow this account to create staff accounts}';

    protected $description = 'Provision a new staff account with a password entered at the prompt';

    public function handle(StaffAccountService $accounts): int
    {
        try {
            $identity = $accounts->validateIdentity($this->argument('name'), $this->argument('email'));
            $password = $accounts->validatePassword($this->secret('Password'));
            if ($this->secret('Confirm password') !== $password) {
                $this->error('Passwords do not match.');

                return self::FAILURE;
            }

            $accounts->create($identity['name'], $identity['email'], $password, administrator: $this->option('admin'));
        } catch (ValidationException $exception) {
            $this->error($exception->validator->errors()->first());

            return self::FAILURE;
        }

        $this->info('Staff account created.');

        return self::SUCCESS;
    }
}
