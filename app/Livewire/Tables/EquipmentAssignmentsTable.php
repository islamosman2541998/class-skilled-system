<?php

namespace App\Livewire\Tables;

use App\Livewire\DataTable;
use App\Models\EquipmentAssignment;
use App\Tables\Column;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;

/** Assignments of equipment to projects (spec §8.17), for one piece of equipment or one project. */
class EquipmentAssignmentsTable extends DataTable
{
    #[Locked]
    public ?int $equipmentId = null;

    #[Locked]
    public ?int $projectId = null;

    public function tableKey(): string
    {
        return $this->projectId !== null ? 'equipment_assignments.project' : 'equipment_assignments';
    }

    protected function permissionPrefix(): ?string
    {
        return 'equipment';
    }

    protected function query(): Builder
    {
        return EquipmentAssignment::query()
            ->with(['equipment', 'project'])
            ->when($this->equipmentId, fn (Builder $query) => $query->where('equipment_id', $this->equipmentId))
            ->when($this->projectId, fn (Builder $query) => $query->where('project_id', $this->projectId));
    }

    protected function columns(): array
    {
        return [
            Column::make('equipment', 'resources.equipment.equipment')->format(fn (EquipmentAssignment $row) => $row->equipment->name)->onCard(),
            Column::make('project', 'resources.equipment.project')->format(fn (EquipmentAssignment $row) => $row->project->number.' · '.$row->project->title)->onCard(),
            Column::make('date_from', 'resources.equipment.date_from')->sortable()->format(fn (EquipmentAssignment $row) => fmt_date($row->date_from))->onCard(),
            Column::make('date_to', 'resources.equipment.date_to')->sortable()->format(fn (EquipmentAssignment $row) => fmt_date($row->date_to)),
            Column::make('days', 'resources.equipment.days')->sortable()->alignEnd()->format(fn (EquipmentAssignment $row) => (string) $row->days),
            Column::make('cost', 'resources.equipment.cost')->sortable()->summable()->alignEnd()->format(fn (EquipmentAssignment $row) => money($row->cost))->exportAs(fn (EquipmentAssignment $row) => $row->cost)->onCard(),
        ];
    }

    protected function dateColumn(): ?string
    {
        return 'date_from';
    }

    protected function defaultSort(): array
    {
        return ['date_from', 'desc'];
    }
}
