<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Observability\EventLog;
use App\Observability\Trace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request, Trace $trace): JsonResponse
    {
        return $trace->span('auth.register', function () use ($request) {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ]);

            $user = User::create($data);

            EventLog::info('user.registered', [
                'user_id' => $user->id,
            ]);

            return (new UserResource($user))
                ->additional(['token' => $user->createToken('api')->plainTextToken])
                ->response()
                ->setStatusCode(201);
        });
    }

    public function login(Request $request, Trace $trace): JsonResponse
    {
        return $trace->span('auth.login', function () use ($request) {
            $credentials = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required', 'string'],
            ]);

            $user = User::where('email', $credentials['email'])->first();

            if (! $user || ! Hash::check($credentials['password'], $user->password)) {
                EventLog::warning('auth.login_failed', [
                    'user_found' => $user !== null,
                ]);

                throw ValidationException::withMessages([
                    'email' => ['The provided credentials are incorrect.'],
                ]);
            }

            $user->tokens()->delete();

            EventLog::info('auth.login_succeeded', [
                'user_id' => $user->id,
            ]);

            return (new UserResource($user))
                ->additional(['token' => $user->createToken('api')->plainTextToken])
                ->response();
        });
    }

    public function logout(Request $request, Trace $trace): Response
    {
        return $trace->span('auth.logout', function () use ($request) {
            $user = $request->user();
            $user->currentAccessToken()->delete();

            EventLog::info('auth.logout', [
                'user_id' => $user->id,
            ]);

            return response()->noContent();
        });
    }
}
