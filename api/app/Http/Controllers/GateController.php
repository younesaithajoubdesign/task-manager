<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Services\AccessGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GateController extends Controller
{
    public function __construct(private AccessGate $gate) {}

    /**
     * Whether the app asks for a password, and whether the sent token opens it.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => [
            'enabled' => $this->gate->isEnabled(),
            'authorized' => $this->gate->accepts($request->bearerToken()),
        ]]);
    }

    /**
     * Exchange the password for the long-lived token kept in localStorage.
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $token = $this->gate->attempt($request->validated('password'));

        if ($token === null) {
            throw ValidationException::withMessages(['password' => 'Wrong password.']);
        }

        return response()->json(['data' => ['token' => $token]]);
    }
}
