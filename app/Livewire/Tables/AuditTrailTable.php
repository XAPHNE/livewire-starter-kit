<?php

namespace App\Livewire\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;
use OwenIt\Auditing\Models\Audit;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;

final class AuditTrailTable extends PowerGridComponent
{
    use WithExport;

    public string $tableName = 'auditTrailTable';

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::exportable(fileName: 'audit_trail')
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
        // order by newest audit first
        return Audit::query()->latest('created_at');
    }

    public function relationSearch(): array
    {
        return [];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('event')
            ->add('auditable_type')
            ->add('auditable_id')
            ->add('user_type')
            ->add('user_id')
            ->add('url')
            ->add('ip_address')
            ->add('user_agent')
            ->add('created_at_formatted', fn (Audit $model) => Carbon::parse($model->created_at)->format('d M Y, h:i A'));
    }

    public function columns(): array
    {
        return [
            Column::make('Id', 'id')
                ->hidden(true)
                ->visibleInExport(true),

            Column::make('Event', 'event')
                ->sortable()
                ->searchable()
                ->visibleInExport(true),

            Column::make('Model', 'auditable_type')
                ->sortable()
                ->searchable()
                ->visibleInExport(true),

            Column::make('Model ID', 'auditable_id')
                ->sortable()
                ->searchable()
                ->hidden(isHidden: true, isForceHidden: false)
                ->visibleInExport(true),

            Column::make('User Type', 'user_type')
                ->sortable()
                ->searchable()
                ->hidden(isHidden: true, isForceHidden: false)
                ->visibleInExport(true),

            Column::make('User ID', 'user_id')
                ->sortable()
                ->searchable()
                ->visibleInExport(true),

            Column::make('URL', 'url')
                ->sortable()
                ->searchable()
                ->hidden(isHidden: true, isForceHidden: false),

            Column::make('IP Address', 'ip_address')
                ->sortable()
                ->searchable(),

            Column::make('User Agent', 'user_agent')
                ->sortable()
                ->searchable()
                ->hidden(isHidden: true, isForceHidden: false),

            Column::make('Time', 'created_at_formatted', 'created_at')
                ->sortable(),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('event')->placeholder('Event'),
            Filter::inputText('auditable_type')->placeholder('Model'),
            Filter::datetimepicker('created_at'),
        ];
    }
}
