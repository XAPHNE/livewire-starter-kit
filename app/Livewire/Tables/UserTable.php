<?php

namespace App\Livewire\Tables;

use App\Models\User;
use Spatie\Permission\Models\Role;
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

final class UserTable extends PowerGridComponent
{
    use WithExport;
    public string $tableName = 'userTable';

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::exportable(fileName: 'users')
                ->type(Exportable::TYPE_XLS, Exportable::TYPE_CSV),
            \PowerComponents\LivewirePowerGrid\Facades\PowerGrid::header()
                ->showSearchInput()
                ->showToggleColumns(),
            \PowerComponents\LivewirePowerGrid\Facades\PowerGrid::footer()
                ->showPerPage()
                ->showRecordCount(),
        ];
    }

    public function header(): array
    {
        $headers = [];
        
        if (auth()->user()->can('Delete Users')) {
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
        // show newest users at the top
        return User::with(['creator', 'updater', 'roles'])->latest('created_at');
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
            ->add('email')
            ->add('created_by', fn (User $model) => $model->creator ? $model->creator->name : '')
            ->add('updated_by', fn (User $model) => $model->updater ? $model->updater->name : '')
            ->add('roles', function (User $model) {
                if ($model->hasRole('Super Admin')) {
                    return '<span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10 dark:bg-blue-800/30 dark:text-blue-400 dark:ring-blue-400/30">Super Admin</span>';
                }
                $badges = $model->roles->map(function ($role) {
                    return '<span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20 dark:bg-green-800/30 dark:text-green-400 dark:ring-green-400/30">' . $role->name . '</span>';
                })->implode('');
                return '<div class="flex flex-wrap gap-1 whitespace-normal max-w-sm">' . $badges . '</div>';
            })
            ->add('created_at_formatted', fn (User $model) => Carbon::parse($model->created_at)->format('d M Y, h:i A'))
            ->add('tfa_status', function (User $model) {
                if ($model->two_factor_type === 'disabled') {
                    return '<span class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/20 dark:bg-red-800/30 dark:text-red-400 dark:ring-red-400/30">Disabled</span>';
                }
                
                $label = $model->two_factor_type === 'totp' ? 'TOTP' : 'Email';
                return '<span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20 dark:bg-green-800/30 dark:text-green-400 dark:ring-green-400/30">Enabled (' . $label . ')</span>';
            })
            ->add('account_status', function (User $model) {
                $status = [];
                if ($model->login_locked_until && Carbon::parse($model->login_locked_until)->isFuture()) {
                    $timeLeft = max(0, Carbon::parse($model->login_locked_until)->timestamp - time());
                    $status[] = '<div x-data="{
                        timeLeft: ' . $timeLeft . ',
                        formattedTime: \'\',
                        init() {
                            this.updateFormattedTime();
                            const interval = setInterval(() => {
                                if (this.timeLeft > 0) {
                                    this.timeLeft--;
                                    this.updateFormattedTime();
                                } else {
                                    clearInterval(interval);
                                    this.formattedTime = \'Unlocked\';
                                }
                            }, 1000);
                        },
                        updateFormattedTime() {
                            if (this.timeLeft <= 0) return;
                            let days = Math.floor(this.timeLeft / 86400);
                            let hours = Math.floor((this.timeLeft % 86400) / 3600);
                            let minutes = Math.floor((this.timeLeft % 3600) / 60);
                            let sec = this.timeLeft % 60;
                            let parts = [];
                            if (days > 0) parts.push(`${days}d`);
                            if (hours > 0) parts.push(`${hours}h`);
                            if (minutes > 0) parts.push(`${minutes}m`);
                            if (sec > 0 || parts.length === 0) parts.push(`${sec}s`);
                            this.formattedTime = parts.join(\' \');
                        }
                    }" class="inline-flex items-center rounded-md bg-orange-50 px-2 py-1 text-xs font-medium text-orange-700 ring-1 ring-inset ring-orange-600/20 dark:bg-orange-800/30 dark:text-orange-400 dark:ring-orange-400/30">Login Locked. Unlocks in <span class="ml-1 font-bold" x-text="formattedTime"></span></div>';
                }
                if ($model->two_factor_locked_until && Carbon::parse($model->two_factor_locked_until)->isFuture()) {
                    $timeLeft = max(0, Carbon::parse($model->two_factor_locked_until)->timestamp - time());
                    $status[] = '<div x-data="{
                        timeLeft: ' . $timeLeft . ',
                        formattedTime: \'\',
                        init() {
                            this.updateFormattedTime();
                            const interval = setInterval(() => {
                                if (this.timeLeft > 0) {
                                    this.timeLeft--;
                                    this.updateFormattedTime();
                                } else {
                                    clearInterval(interval);
                                    this.formattedTime = \'Unlocked\';
                                }
                            }, 1000);
                        },
                        updateFormattedTime() {
                            if (this.timeLeft <= 0) return;
                            let days = Math.floor(this.timeLeft / 86400);
                            let hours = Math.floor((this.timeLeft % 86400) / 3600);
                            let minutes = Math.floor((this.timeLeft % 3600) / 60);
                            let sec = this.timeLeft % 60;
                            let parts = [];
                            if (days > 0) parts.push(`${days}d`);
                            if (hours > 0) parts.push(`${hours}h`);
                            if (minutes > 0) parts.push(`${minutes}m`);
                            if (sec > 0 || parts.length === 0) parts.push(`${sec}s`);
                            this.formattedTime = parts.join(\' \');
                        }
                    }" class="inline-flex items-center rounded-md bg-purple-50 px-2 py-1 text-xs font-medium text-purple-700 ring-1 ring-inset ring-purple-600/20 dark:bg-purple-800/30 dark:text-purple-400 dark:ring-purple-400/30">2FA Locked. Unlocks in <span class="ml-1 font-bold" x-text="formattedTime"></span></div>';
                }
                if (empty($status)) {
                    return '<span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20 dark:bg-green-800/30 dark:text-green-400 dark:ring-green-400/30">Active</span>';
                }
                return '<div class="flex flex-col gap-1">' . implode('', $status) . '</div>';
            })
            ->add('updated_at_formatted', fn (User $model) => Carbon::parse($model->updated_at)->format('d M Y, h:i A'));
    }

    public function columns(): array
    {
        return [
            Column::make('#', 'id')
                ->sortable()
                ->searchable()
                ->visibleInExport(true)
                ->hidden(isHidden: true, isForceHidden: false),

            Column::make('Name', 'name')
                ->sortable()
                ->searchable()
                ->visibleInExport(true),

            Column::make('Email', 'email')
                ->sortable()
                ->searchable()
                ->visibleInExport(true),

            Column::make('2FA Status', 'tfa_status'),

            Column::make('Account Status', 'account_status'),

            Column::make('Roles', 'roles'),

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
        $guards = collect(config('auth.guards'))->keys()->map(function ($guard) {
            return ['value' => $guard, 'label' => $guard];
        });

        return [
            Filter::multiSelect('roles', 'roles')
                ->dataSource(Role::all())
                ->optionValue('name')
                ->optionLabel('name')
                ->builder(function ($query, $values) {
                    $values = is_array($values) ? $values : [$values];
                    return $query->whereHas('roles', function ($q) use ($values) {
                        $q->whereIn('name', $values);
                    });
                }),
            Filter::select('guard_name', 'guard_name')
                ->dataSource($guards)
                ->optionValue('value')
                ->optionLabel('label'),
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
            $this->dispatch('bulk-delete-users', ids: $this->checkboxValues);
        }
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->dispatch('edit-user', id: $rowId);
    }

    #[\Livewire\Attributes\On('delete')]
    public function delete($rowId): void
    {
        $this->dispatch('delete-user', id: $rowId);
    }



    public function actions(User $row): array
    {
        $actions = [];



        if (auth()->user()->can('Edit Users')) {
            $actions[] = Button::add('edit')
                ->slot('Edit')
                ->id()
                ->class('bg-amber-500 hover:bg-amber-600 font-medium py-2 px-4 rounded text-white')
                ->dispatch('edit', ['rowId' => $row->id]);
        }

        if (auth()->user()->can('Delete Users')) {
            $actions[] = Button::add('delete')
                ->slot('Delete')
                ->id()
                ->class('bg-red-500 hover:bg-red-600 font-medium py-2 px-4 rounded text-white')
                ->dispatch('delete', ['rowId' => $row->id]);
        }

        return $actions;
    }
}
