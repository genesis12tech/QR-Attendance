<x-filament-widgets::widget>
    <x-filament::section>
        @if ($activeSession)
            <x-slot name="heading">
                Live Session — {{ $activeSession->course?->code }}
            </x-slot>

            <div class="flex flex-wrap items-center gap-6">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Room</p>
                    <p class="font-medium">{{ $activeSession->room?->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Started</p>
                    <p class="font-medium">{{ $activeSession->started_at?->format('H:i') }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Present / Enrolled</p>
                    <p class="font-medium">{{ $activeSession->total_present }} / {{ $activeSession->total_enrolled }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Late</p>
                    <p class="font-medium">{{ $activeSession->total_late }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Absent</p>
                    <p class="font-medium">{{ $activeSession->total_absent }}</p>
                </div>
                <div class="ml-auto flex gap-2">
                    <x-filament::button
                        wire:click="closeSession({{ $activeSession->id }})"
                        color="danger"
                        size="sm"
                    >
                        Close Session
                    </x-filament::button>
                    <a href="{{ \App\Filament\Faculty\Pages\QrDisplayPage::getUrl() }}">
                        <x-filament::button color="primary" size="sm">
                            View QR
                        </x-filament::button>
                    </a>
                </div>
            </div>
        @else
            <x-slot name="heading">Today's Schedule</x-slot>

            @if (empty($todaySlots))
                <p class="text-sm text-gray-500 dark:text-gray-400">No classes scheduled for today.</p>
            @else
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($todaySlots as $slot)
                        <div class="flex items-center justify-between gap-4 py-3">
                            <div>
                                <p class="font-medium">{{ $slot['course']['code'] ?? '—' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $slot['class_group']['name'] ?? '' }}
                                    &middot; {{ $slot['room']['name'] ?? '—' }}
                                    &middot; {{ $slot['start_time'] }} – {{ $slot['end_time'] }}
                                </p>
                            </div>
                            <x-filament::button
                                wire:click="startSession({{ $slot['id'] }})"
                                color="success"
                                size="sm"
                            >
                                Start Session
                            </x-filament::button>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
