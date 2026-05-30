<x-filament-panels::page>
    @php
        $grouped = collect($timetableSlots)->groupBy('day');
        $dayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $today = ucfirst(strtolower(now()->format('l')));
    @endphp

    @if (empty($timetableSlots))
        <div class="flex items-center justify-center py-12">
            <p class="text-gray-500 dark:text-gray-400">No timetable entries found.</p>
        </div>
    @else
        <div class="space-y-6">
            @foreach ($dayOrder as $day)
                @if ($grouped->has($day))
                    <div>
                        <h2 @class([
                            'mb-3 text-lg font-semibold',
                            'text-orange-600 dark:text-orange-400' => $today === $day,
                            'text-gray-700 dark:text-gray-300' => $today !== $day,
                        ])>
                            {{ $day }}
                            @if ($today === $day)
                                <span class="ml-2 inline-flex items-center rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700 dark:bg-orange-900/30 dark:text-orange-400">
                                    Today
                                </span>
                            @endif
                        </h2>

                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($grouped[$day] as $slot)
                                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                    <div class="mb-3">
                                        <p class="text-base font-bold text-gray-900 dark:text-white">
                                            {{ $slot['course_code'] }}
                                        </p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            {{ $slot['class_group_name'] }}
                                        </p>
                                        <p class="text-sm text-gray-500 dark:text-gray-500">
                                            {{ $slot['room_name'] ?? '—' }}
                                            &middot;
                                            {{ \Carbon\Carbon::parse($slot['start_time'])->format('H:i') }}–{{ \Carbon\Carbon::parse($slot['end_time'])->format('H:i') }}
                                        </p>
                                    </div>

                                    <button
                                        wire:click="startSession({{ $slot['id'] }})"
                                        wire:loading.attr="disabled"
                                        class="w-full rounded-lg bg-orange-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-600 disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        <span wire:loading.remove wire:target="startSession({{ $slot['id'] }})">Start Session</span>
                                        <span wire:loading wire:target="startSession({{ $slot['id'] }})">Starting…</span>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
