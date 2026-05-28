<x-filament::section heading="System Health">
    <div class="grid grid-cols-3 gap-4 text-sm">
        <div>
            <p class="font-semibold text-gray-500">Cache</p>
            <p class="{{ $redisConnected ? 'text-green-600' : 'text-red-600' }}">
                {{ $redisConnected ? 'Connected' : 'Unavailable' }}
            </p>
        </div>
        <div>
            <p class="font-semibold text-gray-500">Active Security Policy</p>
            <p>{{ $policy?->policy_name ?? 'None' }}</p>
        </div>
        <div>
            <p class="font-semibold text-gray-500">Last Retention Run</p>
            <p>{{ $lastRetentionRun ? \Carbon\Carbon::parse($lastRetentionRun)->diffForHumans() : 'Never' }}</p>
        </div>
    </div>
</x-filament::section>
