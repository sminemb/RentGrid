<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffSessionController extends Controller
{
    private const PRIVATE_HEADERS = ['Cache-Control' => 'private, no-store'];

    public function store(Request $request): Response
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:255'],
        ]);
        $credentials['email'] = Str::lower($credentials['email']);

        if (! Auth::attemptWhen($credentials, fn (User $user) => Gate::forUser($user)->allows('access-staff'))) {
            throw ValidationException::withMessages(['email' => 'The email or password is incorrect.']);
        }

        $request->session()->regenerate(true);

        return response()->noContent(headers: self::PRIVATE_HEADERS);
    }

    public function show(Request $request): JsonResponse
    {
        $staff = $request->user();

        return response()->json([
            'name' => $staff->name,
            'can_create_staff' => $staff->can('create-staff-accounts'),
        ], headers: self::PRIVATE_HEADERS);
    }

    public function destroy(Request $request): Response
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent(headers: self::PRIVATE_HEADERS);
    }
}
