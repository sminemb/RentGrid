<?php

namespace App\Http\Controllers;

use App\Services\StaffAccountService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StaffAccountController extends Controller
{
    public function store(Request $request, StaffAccountService $accounts): Response
    {
        $identity = $accounts->validateIdentity($request->input('name'), $request->input('email'));
        $password = $accounts->validatePassword($request->input('password'));
        $request->validate([
            'password_confirmation' => ['required', 'string', 'same:password'],
        ], [
            'password_confirmation.same' => 'Passwords do not match.',
        ]);

        $accounts->create($identity['name'], $identity['email'], $password);

        return response()->noContent(201);
    }
}
