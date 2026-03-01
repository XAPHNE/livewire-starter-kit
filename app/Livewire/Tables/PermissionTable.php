<?php

namespace App\Livewire\Tables;

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

final class PermissionTable extends PowerGridComponent
{
    use WithExport;
    public string $tableName = 'permissionTable';

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::exportable(fileName: 'permissions')
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
        if (auth()->user()->can('Delete Permissions')) {
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
        // order permissions newest first
        return Permission::query()->latest('created_at');
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
            ->add('created_at_formatted', fn (Permission $model) => Carbon::parse($model->created_at)->format('d M Y, h:i A'))
            ->add('updated_at_formatted', fn (Permission $model) => Carbon::parse($model->updated_at)->format('d M Y, h:i A'));
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
            $this->dispatch('bulk-delete-permissions', ids: $this->checkboxValues);
        }
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->dispatch('edit-permission', id: $rowId);
    }

    #[On('delete')]
    public function delete($rowId): void
    {
        $this->dispatch('delete-permission', id: $rowId);
    }

    public function actions(Permission $row): array
    {
        $actions = [];

        if (auth()->user()->can('Edit Permissions')) {
            $actions[] = Button::add('edit')
                ->slot('Edit')
                ->id()
                ->class('bg-amber-500 hover:bg-amber-600 font-medium py-2 px-4 rounded text-white')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (auth()->user()->can('Delete Permissions')) {
            $actions[] = Button::add('delete')
                ->slot('Delete')
                ->id()
                ->class('bg-red-500 hover:bg-red-600 font-medium py-2 px-4 rounded text-white')
                ->dispatch('delete', ['rowId' => $row->id]);
        }

        return $actions;
    }
}
