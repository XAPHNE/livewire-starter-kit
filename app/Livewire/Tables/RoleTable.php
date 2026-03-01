<?php

namespace App\Livewire\Tables;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;

final class RoleTable extends PowerGridComponent
{
    use WithExport;
    public string $tableName = 'roleTable';

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::exportable(fileName: 'roles')
                ->type(Exportable::TYPE_XLS, Exportable::TYPE_CSV),
            PowerGrid::header()
                ->showSearchInput()
                ->showToggleColumns(),
            PowerGrid::footer()
                ->showPerPage()
                ->showRecordCount(),
        ];
    }

    public function header(): array
    {
        $headers = [];
        if (auth()->user()->can('Delete Roles')) {
            $headers[] = Button::add('bulk-delete')
                ->slot('Bulk delete (<span x-text="window.pgBulkActions.count(\'' . $this->tableName . '\')"></span>)')
                ->class('cursor-pointer bg-red-500 hover:bg-red-600 font-medium py-2 rounded text-white px-4')
                ->attributes([
                    'x-show' => "window.pgBulkActions.count('{$this->tableName}') > 0"
                ])
                ->dispatch('bulkDelete', []);
        }
        return $headers;
    }

    public function datasource(): Builder
    {
        // show most-recent roles first
        return Role::with('permissions')->latest('created_at');
    }

    public function relationSearch(): array
    {
        return [];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('name')
            ->add('guard_name')
            ->add('permissions', function (Role $model) {
                if ($model->name === 'Super Admin') {
                    return '<span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10 dark:bg-blue-800/30 dark:text-blue-400 dark:ring-blue-400/30">All Access</span>';
                }
                $badges = $model->permissions->map(function ($permission) {
                    return '<span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20 dark:bg-green-800/30 dark:text-green-400 dark:ring-green-400/30">' . $permission->name . '</span>';
                })->implode('');
                return '<div class="flex flex-wrap gap-1 whitespace-normal max-w-sm">' . $badges . '</div>';
            })
            ->add('created_at_formatted', fn (Role $model) => Carbon::parse($model->created_at)->format('d M Y, h:i A'))
            ->add('updated_at_formatted', fn (Role $model) => Carbon::parse($model->updated_at)->format('d M Y, h:i A'));
    }

    public function columns(): array
    {
        return [
            Column::make('Id', 'id')
                ->hidden(true)
                ->visibleInExport(true),

            Column::make('Name', 'name')
                ->sortable()
                ->searchable()
                ->visibleInExport(true),

            Column::make('Guard Name', 'guard_name')
                ->sortable()
                ->searchable()
                ->hidden(isHidden: true, isForceHidden: false)
                ->visibleInExport(true),

            Column::make('Permissions', 'permissions'),

            Column::make('Created at', 'created_at_formatted', 'created_at')
                ->sortable()
                ->hidden(isHidden: true, isForceHidden: false),

            Column::make('Updated at', 'updated_at_formatted', 'updated_at')
                ->sortable()
                ->hidden(isHidden: true, isForceHidden: false),

            Column::action('Action')
        ];
    }

    public function filters(): array
    {
        $guards = collect(config('auth.guards'))->keys()->map(function ($guard) {
            return ['value' => $guard, 'label' => $guard];
        });

        return [
            Filter::multiSelect('permissions', 'permissions')
                ->dataSource(Permission::all())
                ->optionValue('name')
                ->optionLabel('name')
                ->builder(function ($query, $values) {
                    $values = is_array($values) ? $values : [$values];
                    if (count($values) > 0) {
                        foreach ($values as $value) {
                            $query->whereHas('permissions', function ($q) use ($value) {
                                $q->where('name', $value);
                            });
                        }
                    }
                    return $query;
                }),
            Filter::select('guard_name', 'guard_name')
                ->dataSource($guards)
                ->optionValue('value')
                ->optionLabel('label'),
            Filter::datetimepicker('created_at'),
            Filter::datetimepicker('updated_at'),
        ];
    }

    #[On('bulkDelete')]
    public function bulkDelete(): void
    {
        if (count($this->checkboxValues)) {
            $this->dispatch('bulk-delete-roles', ids: $this->checkboxValues);
        }
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->dispatch('edit-role', id: $rowId);
    }

    #[On('delete')]
    public function delete($rowId): void
    {
        $this->dispatch('delete-role', id: $rowId);
    }

    public function actions(Role $row): array
    {
        $actions = [];

        if (auth()->user()->can('Edit Roles')) {
            $actions[] = Button::add('edit')
                ->slot('Edit')
                ->id()
                ->class('bg-amber-500 hover:bg-amber-600 font-medium py-2 px-4 rounded text-white')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (auth()->user()->can('Delete Roles')) {
            $actions[] = Button::add('delete')
                ->slot('Delete')
                ->id()
                ->class('bg-red-500 hover:bg-red-600 font-medium py-2 px-4 rounded text-white')
                ->dispatch('delete', ['rowId' => $row->id]);
        }

        return $actions;
    }
}
