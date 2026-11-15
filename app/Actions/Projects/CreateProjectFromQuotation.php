<?php

namespace App\Actions\Projects;

use App\Enums\QuotationStatus;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use App\Services\InstallmentSchedule;
use App\Services\QuotationCalculator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Accepted quotation → project (spec §8.5, §8.6): value, items and payment schedule are copied; the
 * service's stage template comes from the normal creation hook. One project per quotation: a second
 * call (double click, two tabs) returns the project that exists.
 */
class CreateProjectFromQuotation
{
    public function __construct(private readonly SaveProject $save) {}

    /**
     * @param  array<string, mixed>  $confirm  fields the user confirms: dates, manager, members, billing method
     *
     * @throws LogicException when the quotation is not accepted
     */
    public function handle(Quotation $quotation, array $confirm, User $by): Project
    {
        try {
            return DB::transaction(function () use ($quotation, $confirm, $by) {
                $locked = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);

                if (($existing = Project::withTrashed()->where('quotation_id', $locked->id)->first()) !== null) {
                    return $existing;
                }

                if ($locked->status !== QuotationStatus::Accepted) {
                    throw new LogicException('Only an accepted quotation becomes a project.');
                }

                $locked->load(['items', 'payments']);

                $project = $this->save->handle([
                    ...$confirm,
                    'client_id' => $locked->client_id,
                    'title_ar' => $locked->title_ar,
                    'title_en' => $locked->title_en,
                    'service_id' => $locked->service_id,
                    'emirate_id' => $locked->emirate_id,
                    'location' => $locked->location,
                    'original_value' => $locked->total,
                ], $by, null, [
                    'quotation_id' => $locked->id,
                    'vat_rate' => $locked->vat_rate,
                    'contract_discount_percent' => self::discountPercent($locked),
                ]);

                foreach ($locked->items as $index => $item) {
                    $project->items()->create([
                        ...$item->only(['section_ar', 'section_en', 'description_ar', 'description_en', 'unit_id', 'quantity', 'unit_price', 'line_total']),
                        'sort_order' => $index + 1,
                    ]);
                }

                // A project billed by claims has no payment schedule (spec §5.9).
                if (! $project->isClaimsBilled()) {
                    InstallmentSchedule::fromPercents($project, $locked->payments->map(fn ($payment) => [
                        'label_ar' => $payment->label_ar,
                        'label_en' => $payment->label_en,
                        'percent' => (string) $payment->percent,
                    ])->all());
                }

                return $project;
            });
        } catch (UniqueConstraintViolationException) {
            // Another request converted the same quotation at the same moment.
            return Project::withTrashed()->where('quotation_id', $quotation->id)->firstOrFail();
        }
    }

    /** The quotation discount as a share of its subtotal, 4 decimals half up; applied to each claim's work. */
    private static function discountPercent(Quotation $quotation): string
    {
        if (bccomp((string) $quotation->subtotal, '0', 2) <= 0 || bccomp((string) $quotation->discount_amount, '0', 2) <= 0) {
            return '0.0000';
        }

        return QuotationCalculator::round(bcdiv(bcmul((string) $quotation->discount_amount, '100', 10), (string) $quotation->subtotal, 10), 4);
    }
}
