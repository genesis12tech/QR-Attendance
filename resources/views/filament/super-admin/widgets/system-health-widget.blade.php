<x-filament::section heading="System Health & Security Posture">
    <div class="space-y-4">
        {{-- Infrastructure health --}}
        <div class="grid grid-cols-3 gap-4 text-sm">
            <div>
                <p class="font-semibold text-gray-500 dark:text-gray-400">Cache</p>
                <p class="{{ $redisConnected ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                    {{ $redisConnected ? 'Connected' : 'Unavailable' }}
                </p>
            </div>
            <div>
                <p class="font-semibold text-gray-500 dark:text-gray-400">Active Security Policy</p>
                <p class="text-gray-900 dark:text-white">{{ $policy?->policy_name ?? 'None' }}</p>
            </div>
            <div>
                <p class="font-semibold text-gray-500 dark:text-gray-400">Last Retention Run</p>
                <p class="text-gray-900 dark:text-white">
                    {{ $lastRetentionRun ? \Carbon\Carbon::parse($lastRetentionRun)->diffForHumans() : 'Never' }}
                </p>
            </div>
        </div>

        <hr class="border-gray-200 dark:border-gray-700" />

        {{-- Security posture checklist --}}
        <div>
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Security Posture
            </p>
            <ul class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
                @php
                    $checks = [
                        'Device Binding'        => $deviceBindingEnabled,
                        'Auto-Reject Threshold' => $autoRejectConfigured,
                        'Geofence'              => $geofenceConfigured,
                        'QR Expiry'             => $qrExpiryShort,
                    ];
                @endphp
                @foreach ($checks as $label => $passing)
                    <li class="flex items-center gap-1.5">
                        @if ($passing)
                            <span class="text-green-500">✓</span>
                            <span class="text-gray-700 dark:text-gray-300">{{ $label }}</span>
                        @else
                            <span class="text-red-500">✗</span>
                            <span class="text-gray-500 line-through">{{ $label }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</x-filament::section>
