<?php

namespace App\Livewire\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;
use Rappasoft\LaravelAuthenticationLog\Models\AuthenticationLog;

final class AuthenticationLogTable extends PowerGridComponent
{
    use WithExport;

    public string $tableName = 'authenticationLogTable';

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::exportable(fileName: 'authentication_logs')
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
        // fetch most recent authentication events first
        return AuthenticationLog::query()->latest('login_at');
    }

    public function relationSearch(): array
    {
        return [];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('authenticatable_type')
            ->add('authenticatable_id')
            ->add('ip_address')
            ->add('user_agent')
            ->add('device_id')
            ->add('device_name')
            ->add('is_trusted_badge', function (AuthenticationLog $model) {
                if ($model->is_trusted) {
                    return '<span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">'
                        . '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8.004 8.004a1 1 0 01-1.414 0L3.293 10.707a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>'
                        . '<span class="ml-2">Trusted</span></span>';
                }
                return '<span class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/20">'
                    . '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 10.586l4.95-4.95a1 1 0 111.414 1.414L13.414 12l4.95 4.95a1 1 0 01-1.414 1.414L12 13.414l-4.95 4.95a1 1 0 01-1.414-1.414L10.586 12 5.636 7.05A1 1 0 117.05 5.636L12 10.586z"/></svg>'
                    . '<span class="ml-2">Not Trusted</span></span>';
            })
            ->add('login_at_formatted', fn (AuthenticationLog $model) => $model->login_at ? Carbon::parse($model->login_at)->format('d M Y, h:i A') : '')
            ->add('login_successful_badge', function (AuthenticationLog $model) {
                if ($model->login_successful) {
                    return '<span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">'
                        . '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8.004 8.004a1 1 0 01-1.414 0L3.293 10.707a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>'
                        . '<span class="ml-2">Success</span></span>';
                }
                return '<span class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/20">'
                    . '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 10.586l4.95-4.95a1 1 0 111.414 1.414L13.414 12l4.95 4.95a1 1 0 01-1.414 1.414L12 13.414l-4.95 4.95a1 1 0 01-1.414-1.414L10.586 12 5.636 7.05A1 1 0 117.05 5.636L12 10.586z"/></svg>'
                    . '<span class="ml-2">Failed</span></span>';
            })
            ->add('logout_at_formatted', fn (AuthenticationLog $model) => $model->logout_at ? Carbon::parse($model->logout_at)->format('d M Y, h:i A') : '')
            ->add('last_activity_at_formatted', fn (AuthenticationLog $model) => $model->last_activity_at ? Carbon::parse($model->last_activity_at)->format('d M Y, h:i A') : '')
            ->add('is_suspicious_badge', function (AuthenticationLog $model) {
                if ($model->is_suspicious) {
                    return '<span class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/20">'
                        . '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M11.001 10h2v5h-2zM11 16h2v2h-2z"/><path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2z"/></svg>'
                        . '<span class="ml-2">Suspicious</span></span>';
                }
                return '<span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">'
                    . '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8.004 8.004a1 1 0 01-1.414 0L3.293 10.707a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>'
                    . '<span class="ml-2">OK</span></span>';
            })
            ->add('suspicious_reason');
    }

    public function columns(): array
    {
        return [
            Column::make('Id', 'id')
                ->hidden(true)
                ->visibleInExport(true),

            Column::make('User Type', 'authenticatable_type')
                ->sortable()
                ->searchable()
                ->visibleInExport(true)
                ->hidden(isHidden: true, isForceHidden: false),

            Column::make('User ID', 'authenticatable_id')
                ->sortable()
                ->searchable()
                ->visibleInExport(true),

            Column::make('IP Address', 'ip_address')
                ->sortable()
                ->searchable(),

            Column::make('User Agent', 'user_agent')
                ->sortable()
                ->searchable()
                ->hidden(isHidden: true, isForceHidden: false),

            Column::make('Device ID', 'device_id')
                ->sortable()
                ->searchable()
                ->hidden(isHidden: true, isForceHidden: false),

            Column::make('Device Name', 'device_name')
                ->sortable()
                ->searchable()
                ->hidden(isHidden: true, isForceHidden: false),

            Column::make('Trusted', 'is_trusted_badge')
                ->sortable(),

            Column::make('Login At', 'login_at_formatted', 'login_at')
                ->sortable(),

            Column::make('Successful', 'login_successful_badge')
                ->sortable(),

            Column::make('Logout At', 'logout_at_formatted', 'logout_at')
                ->sortable(),

            Column::make('Last Activity', 'last_activity_at_formatted', 'last_activity_at')
                ->sortable(),

            Column::make('Suspicious', 'is_suspicious_badge')
                ->sortable(),

            Column::make('Reason', 'suspicious_reason')
                ->sortable()
                ->searchable()
                ->hidden(isHidden: true, isForceHidden: false),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('ip_address')->placeholder('IP Address'),
            Filter::inputText('device_name')->placeholder('Device'),
            Filter::datetimepicker('login_at'),
            Filter::datetimepicker('logout_at'),
        ];
    }
}
