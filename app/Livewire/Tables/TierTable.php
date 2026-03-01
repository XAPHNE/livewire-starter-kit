<?php

namespace App\Livewire\Tables;

use App\Models\Tier;
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

    public function datasource(): Builder
    {
        return Tier::query()->latest('created_at');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('name')
            ->add('description')
            ->add('concurrent_sessions', fn (Tier $model) => $model->concurrent_sessions ?? 'Unlimited')
            ->add('created_at_formatted', fn (Tier $model) => Carbon::parse($model->created_at)->format('d M Y, h:i A'));
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
                ->sortable(),

            Column::action('Action')
        ];
    }

    public function filters(): array
    {
        return [
            Filter::datetimepicker('created_at'),
        ];
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
