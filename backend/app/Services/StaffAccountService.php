<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

class StaffAccountService
{
    /** @return array{name: string, email: string} */
    public function validateIdentity(mixed $name, mixed $email): array
    {
        return Validator::validate([
            'name' => is_string($name) ? trim($name) : $name,
            'email' => is_string($email) ? Str::lower(trim($email)) : $email,
        ], [
            'name' => ['required', 'string', 'max:255', 'not_regex:/\x00/'],
            'email' => ['bail', 'required', 'string', 'email', 'max:254', 'unique:users,email'],
        ]);
    }

    public function validatePassword(#[SensitiveParameter] mixed $password): string
    {
        if (! is_string($password) || trim($password) === '' || mb_strlen($password) < 12 || strlen($password) > 72) {
            throw ValidationException::withMessages(['password' => 'Use at least 12 characters and at most 72 bytes for the password.']);
        }

        if (str_contains($password, "\0")) {
            throw ValidationException::withMessages(['password' => 'The password contains an invalid character.']);
        }

        return $password;
    }

    public function create(string $name, string $email, #[SensitiveParameter] string $password, bool $administrator = false): void
    {
        // The model cast preserves values it recognizes as hashes, so hash the submitted password explicitly.
        $user = new User(['name' => $name, 'email' => $email, 'password' => Hash::make($password)]);
        $user->is_staff = true;
        $user->is_staff_admin = $administrator;

        try {
            $user->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'An account already uses this email address.']);
        }
    }
}
