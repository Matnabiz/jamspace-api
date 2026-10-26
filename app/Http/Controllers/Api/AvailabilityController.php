<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AvailabilitySlot;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AvailabilityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'week_start' => ['required', 'date_format:Y-m-d'],
        ]);

        $start = CarbonImmutable::parse($validated['week_start'])->startOfWeek(CarbonImmutable::MONDAY);
        $end = $start->addDays(6);
        $user = $request->user()->load('band');
        abort_unless($user->band, 404, 'This account is not attached to a band.');

        $mine = AvailabilitySlot::query()
            ->where('user_id', $user->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')->orderBy('start_time')->get(['date', 'start_time', 'end_time']);

        $members = $user->band->members()
            ->where('id', '!=', $user->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'instrument'])
            ->map(function ($member) use ($start, $end) {
                $slots = AvailabilitySlot::query()
                    ->where('user_id', $member->id)
                    ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                    ->orderBy('date')->orderBy('start_time')
                    ->get(['date', 'start_time', 'end_time']);

                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'instrument' => $member->instrument,
                    'initials' => collect(explode(' ', $member->name))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode(''),
                    'slots' => $slots,
                ];
            });

        return response()->json([
            'week_start' => $start->toDateString(),
            'week_end' => $end->toDateString(),
            'mine' => $mine,
            'members' => $members,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'week_start' => ['required', 'date_format:Y-m-d'],
            'slots' => ['present', 'array', 'max:336'],
            'slots.*.date' => ['required', 'date_format:Y-m-d'],
            'slots.*.start_time' => ['required', 'date_format:H:i'],
        ]);

        $start = CarbonImmutable::parse($validated['week_start'])->startOfWeek(CarbonImmutable::MONDAY);
        $end = $start->addDays(6);
        $normalized = [];
        $seen = [];

        foreach ($validated['slots'] as $slot) {
            $date = CarbonImmutable::parse($slot['date']);
            $time = $slot['start_time'];
            $key = $date->toDateString() . '|' . $time;

            if ($date->lt($start) || $date->gt($end)) {
                throw ValidationException::withMessages(['slots' => 'Every selected slot must belong to the selected week.']);
            }
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(['slots' => 'A time slot was included more than once.']);
            }
            $seen[$key] = true;

            $minutes = ((int) substr($time, 0, 2) * 60) + (int) substr($time, 3, 2);
            if ($minutes < 0 || $minutes >= 1440 || $minutes % 30 !== 0) {
                throw ValidationException::withMessages(['slots' => 'Slots must start on a 30-minute boundary from 00:00 through 23:30.']);
            }

            $endMinutes = $minutes + 30;
            $endTime = $endMinutes === 1440
                ? '24:00:00'
                : sprintf('%02d:%02d:00', intdiv($endMinutes, 60), $endMinutes % 60);

            $normalized[] = [
                'user_id' => $request->user()->id,
                'date' => $date->toDateString(),
                'start_time' => $time . ':00',
                'end_time' => $endTime,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::transaction(function () use ($request, $start, $end, $normalized) {
            AvailabilitySlot::query()
                ->where('user_id', $request->user()->id)
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->delete();

            foreach (array_chunk($normalized, 500) as $chunk) {
                AvailabilitySlot::insert($chunk);
            }
        });

        return response()->json([
            'message' => 'Availability saved.',
            'week_start' => $start->toDateString(),
            'selected_slots' => count($normalized),
            'hours_available' => count($normalized) / 2,
        ]);
    }
}
