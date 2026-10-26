<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Band;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BandController extends Controller
{
    public function members(Request $request): JsonResponse
    {
        $user = $request->user()->load('band');
        abort_unless($user->band, 404, 'This account is not attached to a band.');

        $members = $user->band->members()
            ->orderByRaw('(id = ?) DESC', [$user->band->created_by])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'instrument', 'created_at'])
            ->map(fn ($member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'instrument' => $member->instrument,
                'initials' => collect(explode(' ', $member->name))->filter()->take(2)->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->implode(''),
                'role' => (int) $member->id === (int) $user->band->created_by ? 'Band admin' : 'Member',
                'created_at' => $member->created_at?->toISOString(),
            ]);

        return response()->json([
            'band' => [
                'id' => $user->band->id,
                'name' => $user->band->name,
                'join_code' => $user->band->join_code,
                'created_by' => $user->band->created_by,
            ],
            'members' => $members,
        ]);
    }

    public function regenerateInviteCode(Request $request): JsonResponse
    {
        $user = $request->user()->load('band');
        abort_unless($user->band, 404, 'This account is not attached to a band.');
        abort_unless((int) $user->id === (int) $user->band->created_by, 403, 'Only the band admin can regenerate the invite code.');

        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $suffix = '';
            for ($i = 0; $i < 4; $i++) $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            $code = 'JAM-' . $suffix;
        } while (Band::where('join_code', $code)->where('id', '!=', $user->band->id)->exists());

        $user->band->update(['join_code' => $code]);

        return response()->json([
            'message' => 'Invite code regenerated.',
            'band' => [
                'id' => $user->band->id,
                'name' => $user->band->name,
                'join_code' => $user->band->join_code,
                'created_by' => $user->band->created_by,
            ],
        ]);
    }
}
