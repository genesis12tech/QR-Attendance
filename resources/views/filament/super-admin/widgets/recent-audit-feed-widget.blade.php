<x-filament::section heading="Recent Audit Events">
    <div class="divide-y">
        @forelse ($logs as $log)
            <div class="py-2 flex justify-between text-sm">
                <div>
                    <span class="font-medium">{{ $log->actor?->name ?? 'System' }}</span>
                    <span class="text-gray-500 ml-1">{{ $log->action }}</span>
                    <span class="text-gray-400 ml-1">on {{ class_basename($log->entity_type) }} #{{ $log->entity_id }}</span>
                </div>
                <span class="text-gray-400 text-xs">{{ $log->created_at->diffForHumans() }}</span>
            </div>
        @empty
            <p class="text-gray-400 text-sm py-2">No audit events yet.</p>
        @endforelse
    </div>
</x-filament::section>
