<?php

namespace App\Livewire\Tables;

use App\Models\Tier;
use App\Models\User;
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

final class TierTable extends PowerGridComponent
{
    use WithExport;
    public string $tableName = 'tierTable';

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::exportable(fileName: 'tiers')
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
        
        if (auth()->user()->can('Delete Tiers')) {
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
        return Tier::with(['creator', 'updater'])->latest('created_at');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('name')
            ->add('description')
            ->add('concurrent_sessions', fn (Tier $model) => $model->concurrent_sessions ?? 'Unlimited')
            ->add('created_by', fn (Tier $model) => $model->creator ? $model->creator->name : '')
            ->add('updated_by', fn (Tier $model) => $model->updater ? $model->updater->name : '')
            ->add('created_at_formatted', fn (Tier $model) => Carbon::parse($model->created_at)->format('d M Y, h:i A'))
            ->add('updated_at_formatted', fn (Tier $model) => Carbon::parse($model->updated_at)->format('d M Y, h:i A'));
    }

    public function columns(): array
    {
        return [
            Column::make('Id', 'id')
                ->hidden(true)
                ->visibleInExport(true),

            Column::make('Name', 'name')
                ->sortable()
                ->searchable(),

            Column::make('Description', 'description')
                ->sortable()
                ->searchable(),

            Column::make('Concurrent Sessions', 'concurrent_sessions')
                ->sortable(),

            Column::make('Created at', 'created_at_formatted', 'created_at')
                ->sortable()
                ->hidden(isHidden: true, isForceHidden: false),

            Column::make('Updated at', 'updated_at_formatted', 'updated_at')
                ->sortable()
                ->hidden(isHidden: true, isForceHidden: false),

            Column::make('Created by', 'created_by')
                ->sortable()
                ->hidden(isHidden: true, isForceHidden: false),

            Column::make('Updated by', 'updated_by')
                ->sortable()
                ->hidden(isHidden: true, isForceHidden: false),

            Column::action('Action')
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('created_by', 'created_by')
                ->dataSource(User::all())
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::select('updated_by', 'updated_by')
                ->dataSource(User::all())
                ->optionValue('id')
                ->optionLabel('name'),
            Filter::datetimepicker('created_at'),
            Filter::datetimepicker('updated_at'),
        ];
    }

    #[On('bulkDelete')]
    public function bulkDelete(): void
    {
        if (count($this->checkboxValues)) {
            $this->dispatch('bulk-delete-tiers', ids: $this->checkboxValues);
        }
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->dispatch('edit-tier', id: $rowId);
    }

    #[On('delete')]
    public function delete($rowId): void
    {
        $this->dispatch('delete-tier', id: $rowId);
    }

    public function actions(Tier $row): array
    {
        return [
            Button::add('edit')
                ->slot('Edit')
                ->id()
                ->class('bg-amber-500 hover:bg-amber-600 font-medium py-2 px-4 rounded text-white')
                ->can(auth()->user()->can('Edit Tiers'))
                ->dispatch('edit', ['rowId' => $row->id]),

            Button::add('delete')
                ->slot('Delete')
                ->id()
                ->class('bg-red-500 hover:bg-red-600 font-medium py-2 px-4 rounded text-white')
                ->can(auth()->user()->can('Delete Tiers'))
                ->dispatch('delete', ['rowId' => $row->id]),
        ];
    }
}
