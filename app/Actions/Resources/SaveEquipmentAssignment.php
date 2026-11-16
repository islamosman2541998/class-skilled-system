<?php

namespace App\Actions\Resources;

use App\Models\Equipment;
use App\Models\EquipmentAssignment;
use App\Models\User;
use App\Services\QuotationCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Assigns equipment to a project for a period (spec §8.17). The equipment row is locked, so two
 * overlapping assignments of the same equipment can never both be saved.
 */
class SaveEquipmentAssignment
{
    /**
     * @param  array<string, mixed>  $data  equipment_id, project_id, date_from, date_to, daily_cost?, notes?
     *
     * @throws ValidationException on "date_from" when the period overlaps another assignment
     */
    public function handle(array $data, User $by, ?EquipmentAssignment $assignment = null): EquipmentAssignment
    {
        return DB::transaction(function () use ($data, $by, $assignment) {
            $equipment = Equipment::query()->lockForUpdate()->findOrFail($assignment->equipment_id ?? $data['equipment_id']);
            $from = Carbon::parse((string) $data['date_from'])->startOfDay();
            $to = Carbon::parse((string) $data['date_to'])->startOfDay();

            $overlap = EquipmentAssignment::query()->with('project')
                ->where('equipment_id', $equipment->id)
                ->when($assignment, fn ($query) => $query->whereKeyNot($assignment->id))
                ->whereDate('date_from', '<=', $to->toDateString())
                ->whereDate('date_to', '>=', $from->toDateString())
                ->first();

            if ($overlap !== null) {
                throw ValidationException::withMessages(['date_from' => __('resources.equipment.overlap', [
                    'project' => $overlap->project->number, 'from' => fmt_date($overlap->date_from), 'to' => fmt_date($overlap->date_to),
                ])]);
            }

            $assignment ??= new EquipmentAssignment;
            $daily = filled($data['daily_cost'] ?? null) ? bcadd((string) $data['daily_cost'], '0', 2) : (string) ($assignment->daily_cost ?? $equipment->daily_cost);
            $days = (int) $from->diffInDays($to) + 1;

            $assignment->fill([
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'daily_cost' => $daily,
                'notes' => $data['notes'] ?? null,
            ]);
            $assignment->forceFill([
                'equipment_id' => $equipment->id,
                'project_id' => (int) $data['project_id'],
                'days' => $days,
                'cost' => QuotationCalculator::round(bcmul($daily, (string) $days, 4), 2),
                'created_by' => $assignment->created_by ?? $by->id,
            ])->save();

            return $assignment;
        });
    }
}
