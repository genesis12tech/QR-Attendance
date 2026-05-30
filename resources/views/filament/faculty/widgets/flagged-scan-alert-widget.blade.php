<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Flagged Scans</x-slot>

        @if ($flags->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No flagged scans for the active session.</p>
        @else
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach ($flags as $flag)
                    <div class="flex items-center justify-between gap-4 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                                {{ $flag->attendanceRecord?->student?->user?->name ?? '—' }}
                            </p>
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                {{ $flag->reason_code }}
                                &middot; Risk: {{ $flag->risk_score }}
                                &middot; {{ ucfirst($flag->severity->value) }}
                            </p>
                        </div>
                        @if ($canReview)
                            <div class="flex shrink-0 gap-2">
                                <x-filament::button
                                    wire:click="allow({{ $flag->id }})"
                                    color="success"
                                    size="sm"
                                >
                                    Allow
                                </x-filament::button>
                                <x-filament::button
                                    wire:click="deny({{ $flag->id }})"
                                    color="danger"
                                    size="sm"
                                >
                                    Deny
                                </x-filament::button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
