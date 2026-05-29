<x-filament-panels::page>
    @if ($this->departments->isEmpty())
        <div class="flex flex-col items-center justify-center py-16 text-gray-500 dark:text-gray-400">
            <p class="text-base font-medium">No departments yet.</p>
            <p class="text-sm mt-1">Use the "New Department" button above to create the first one.</p>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->departments as $dept)
                <x-filament::section>
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h3 class="truncate text-base font-semibold text-gray-900 dark:text-white">
                                {{ $dept->name }}
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $dept->code }}</p>
                        </div>
                        <x-filament::badge :color="$dept->is_active ? 'success' : 'danger'" class="shrink-0">
                            {{ $dept->is_active ? 'Active' : 'Inactive' }}
                        </x-filament::badge>
                    </div>

                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                        <dt class="text-gray-500 dark:text-gray-400">Head of Faculty</dt>
                        <dd class="text-gray-900 dark:text-white">
                            {{ $dept->headFaculty?->user?->name ?? '—' }}
                        </dd>
                        <dt class="text-gray-500 dark:text-gray-400">Students</dt>
                        <dd class="text-gray-900 dark:text-white">{{ $dept->students_count }}</dd>
                        <dt class="text-gray-500 dark:text-gray-400">Faculty</dt>
                        <dd class="text-gray-900 dark:text-white">{{ $dept->faculty_count }}</dd>
                    </dl>

                    <div class="mt-4 flex items-center gap-3">
                        <a
                            href="{{ \App\Filament\SuperAdmin\Resources\Departments\DepartmentResource::getUrl('edit', ['record' => $dept->id]) }}"
                            class="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400"
                        >
                            <x-heroicon-o-pencil-square class="h-4 w-4" />
                            Edit
                        </a>
                        <button
                            wire:click="deleteDepartment({{ $dept->id }})"
                            wire:confirm="Delete department '{{ $dept->name }}'? This cannot be undone."
                            class="inline-flex items-center gap-1 text-sm font-medium text-danger-600 hover:text-danger-500 dark:text-danger-400"
                        >
                            <x-heroicon-o-trash class="h-4 w-4" />
                            Delete
                        </button>
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
