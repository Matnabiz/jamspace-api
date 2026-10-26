<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Band;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'instrument' => ['nullable', 'string', Rule::in(['Vocals', 'Guitar', 'Bass', 'Drums', 'Keys', 'Other'])],
            'band_name' => ['required_without:band_code', 'nullable', 'string', 'max:120'],
            'band_code' => ['required_without:band_name', 'nullable', 'string', 'max:12'],
        ]);

        [$user, $band] = DB::transaction(function () use ($validated) {
            if (!empty($validated['band_code'])) {
                $code = strtoupper(trim($validated['band_code']));
                $band = Band::where('join_code', $code)->lockForUpdate()->first();
                if (!$band) {
                    throw ValidationException::withMessages(['band_code' => 'That invite code was not found. Double-check it with your band admin.']);
                }
            } else {
                $band = Band::create([
                    'name' => trim($validated['band_name']),
                    'join_code' => $this->newJoinCode(),
                ]);
            }

            $user = User::create([
                'name' => trim($validated['name']),
                'email' => strtolower(trim($validated['email'])),
                'password' => $validated['password'],
                'instrument' => $validated['instrument'] ?? null,
                'band_id' => $band->id,
            ]);

            if (!$band->created_by) {
                $band->update(['created_by' => $user->id]);
            }

            return [$user, $band];
        });

        $token = $user->createToken('jamspace-web')->plainTextToken;

        return response()->json([
            'message' => 'Your Jamspace account is ready.',
            'token' => $token,
            'user' => $this->userPayload($user->fresh('band')),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', strtolower(trim($validated['email'])))->first();
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Those details didn’t match an account. Please try again.']);
        }

        $token = $user->createToken('jamspace-web')->plainTextToken;

        return response()->json([
            'message' => 'Welcome back.',
            'token' => $token,
            'user' => $this->userPayload($user->load('band')),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user()->load('band'))]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['message' => 'Signed out.']);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'instrument' => $user->instrument,
            'band' => $user->band ? [
                'id' => $user->band->id,
                'name' => $user->band->name,
                'join_code' => $user->band->join_code,
                'created_by' => $user->band->created_by,
            ] : null,
        ];
    }

    private function newJoinCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $suffix = '';
            for ($i = 0; $i < 4; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $code = 'JAM-' . $suffix;
        } while (Band::where('join_code', $code)->exists());

        return $code;
    }
}
