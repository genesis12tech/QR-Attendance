<?php

namespace App\Filament\Admin\Resources\Faculty;

use App\Filament\Admin\Resources\Faculty\Pages\CreateFaculty;
use App\Filament\Admin\Resources\Faculty\Pages\EditFaculty;
use App\Filament\Admin\Resources\Faculty\Pages\ListFaculty;
use App\Filament\Admin\Resources\Faculty\Schemas\FacultyForm;
use App\Filament\Admin\Resources\Faculty\Tables\FacultyTable;
use App\Models\Faculty;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FacultyResource extends Resource
{
    protected static ?string $model = Faculty::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|\UnitEnum|null $navigationGroup = 'Academic Management';

    public static function getEloquentQuery(): Builder
    {
        $departmentId = auth()->user()?->activeAdminAssignment?->department_id;

        return parent::getEloquentQuery()
            ->withCount('attendanceSessions')
            ->with(['user', 'department'])
            ->when(
                $departmentId,
                fn (Builder $query) => $query->where('department_id', $departmentId)
            );
    }

    public static function form(Schema $schema): Schema
    {
        return FacultyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FacultyTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFaculty::route('/'),
            'create' => CreateFaculty::route('/create'),
            'edit' => EditFaculty::route('/{record}/edit'),
        ];
    }
}
