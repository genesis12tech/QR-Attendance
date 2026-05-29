<?php

namespace App\Filament\SuperAdmin\Resources\Departments\Pages;

use App\Filament\SuperAdmin\Resources\Departments\DepartmentResource;
use App\Models\Department;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

class ListDepartments extends Page
{
    protected static string $resource = DepartmentResource::class;

    protected string $view = 'filament.super-admin.pages.list-departments';

    #[Computed]
    public function departments(): Collection
    {
        return Department::withCount(['students', 'faculty'])
            ->with('headFaculty.user')
            ->orderBy('name')
            ->get();
    }

    public function deleteDepartment(int $id): void
    {
        Department::findOrFail($id)->delete();
        Notification::make()->title('Department deleted')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('New Department')
                ->url(DepartmentResource::getUrl('create'))
                ->icon(Heroicon::OutlinedPlus),
        ];
    }
}
