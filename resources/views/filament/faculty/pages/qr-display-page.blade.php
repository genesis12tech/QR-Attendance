<x-filament-panels::page>
    <div class="flex flex-col gap-6 lg:flex-row">
        {{-- Left: QR code + countdown (60%) --}}
        <div class="flex flex-col items-center gap-4 lg:w-3/5">
            <div class="w-full rounded-xl bg-white p-6 shadow dark:bg-gray-800">
                <h2 class="mb-4 text-center text-xl font-bold text-gray-900 dark:text-white">
                    Scan to Mark Attendance
                </h2>

                @if ($qrString)
                    <div class="flex justify-center">
                        <img
                            src="data:image/png;base64,{{ $qrString }}"
                            alt="QR Code"
                            class="h-72 w-72 rounded-lg"
                        />
                    </div>

                    <p class="mt-4 text-center text-sm text-gray-500 dark:text-gray-400">
                        Refreshes every <span class="font-semibold">{{ $expiresIn }}s</span>
                    </p>
                @else
                    <div class="flex h-72 items-center justify-center text-gray-400">
                        <p>Generating QR code…</p>
                    </div>
                @endif

                <div class="mt-4 text-center">
                    <span @class([
                        'inline-flex items-center rounded-full px-3 py-1 text-sm font-medium',
                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' => $isActive,
                        'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200' => ! $isActive,
                    ])>
                        {{ $isActive ? 'Active' : 'Paused' }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Right: Live counters + recent scan feed (40%) --}}
        <div class="flex flex-col gap-4 lg:w-2/5">
            {{-- Session counters --}}
            <div class="grid grid-cols-2 gap-4">
                <div class="rounded-xl bg-emerald-50 p-4 text-center shadow dark:bg-emerald-900/20">
                    <p class="text-3xl font-bold text-emerald-700 dark:text-emerald-400">
                        {{ $sessionStats['total_present'] ?? 0 }}
                    </p>
                    <p class="text-sm text-emerald-600 dark:text-emerald-300">Present</p>
                </div>

                <div class="rounded-xl bg-amber-50 p-4 text-center shadow dark:bg-amber-900/20">
                    <p class="text-3xl font-bold text-amber-700 dark:text-amber-400">
                        {{ $sessionStats['total_late'] ?? 0 }}
                    </p>
                    <p class="text-sm text-amber-600 dark:text-amber-300">Late</p>
                </div>

                <div class="rounded-xl bg-red-50 p-4 text-center shadow dark:bg-red-900/20">
                    <p class="text-3xl font-bold text-red-700 dark:text-red-400">
                        {{ $sessionStats['total_absent'] ?? 0 }}
                    </p>
                    <p class="text-sm text-red-600 dark:text-red-300">Absent</p>
                </div>

                <div class="rounded-xl bg-gray-50 p-4 text-center shadow dark:bg-gray-700">
                    <p class="text-3xl font-bold text-gray-700 dark:text-gray-200">
                        {{ $sessionStats['total_enrolled'] ?? 0 }}
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Enrolled</p>
                </div>
            </div>

            {{-- Recent scan feed --}}
            @if ($sessionId)
                <div class="rounded-xl bg-white p-4 shadow dark:bg-gray-800">
                    <h3 class="mb-3 font-semibold text-gray-800 dark:text-white">Recent Scans</h3>
                    @php
                        $recentRecords = \App\Models\AttendanceRecord::where('attendance_session_id', $sessionId)
                            ->with('student.user')
                            ->latest('marked_at')
                            ->limit(10)
                            ->get();
                    @endphp

                    @forelse ($recentRecords as $record)
                        <div @class([
                            'mb-2 flex items-center justify-between rounded-lg px-3 py-2 text-sm',
                            'bg-amber-50 dark:bg-amber-900/20' => $record->status === \App\Enums\AttendanceStatus::PendingReview,
                            'bg-gray-50 dark:bg-gray-700' => $record->status !== \App\Enums\AttendanceStatus::PendingReview,
                        ])>
                            <span class="font-medium text-gray-800 dark:text-gray-100">
                                {{ $record->student?->user?->name ?? '—' }}
                            </span>
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs font-semibold',
                                'bg-green-100 text-green-800' => $record->status === \App\Enums\AttendanceStatus::Present,
                                'bg-amber-100 text-amber-800' => $record->status === \App\Enums\AttendanceStatus::Late,
                                'bg-orange-100 text-orange-800' => $record->status === \App\Enums\AttendanceStatus::PendingReview,
                            ])>
                                {{ $record->status->value }}
                            </span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">No scans yet.</p>
                    @endforelse
                </div>
            @endif
        </div>
    </div>

    {{-- Auto-refresh QR every 30 seconds --}}
    <div wire:poll.30000ms="refreshQr" class="hidden"></div>
</x-filament-panels::page>
