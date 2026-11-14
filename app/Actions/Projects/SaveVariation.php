<?php

namespace App\Actions\Projects;

use App\Enums\DocumentType;
use App\Enums\VariationStatus;
use App\Models\Project;
use App\Models\User;
use App\Models\Variation;
use App\Services\NumberGenerator;
use App\Services\QuotationCalculator;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a proposed variation. Quantities may be negative (removed work); totals use
 * the project's VAT rate and are rounded half away from zero.
 */
class SaveVariation
{
    public function __construct(private readonly NumberGenerator $numbers) {}

    /**
     * @param  array{description: string, items: list<array<string, mixed>>}  $data
     */
    public function handle(Project $project, array $data, User $by, ?Variation $variation = null): Variation
    {
        return DB::transaction(function () use ($project, $data, $by, $variation) {
            $creating = $variation === null;
            $variation ??= new Variation;
            $variation->description = $data['description'];

            if ($creating) {
                $variation->number = $this->numbers->next(DocumentType::Variation);
                $variation->project_id = $project->id;
                $variation->status = VariationStatus::Proposed;
                $variation->created_by = $by->id;
            }

            $lines = [];
            $subtotal = '0';

            foreach ($data['items'] as $index => $item) {
                $lineTotal = self::signedRound(bcmul((string) $item['quantity'], (string) $item['unit_price'], 8));
                $subtotal = bcadd($subtotal, $lineTotal, 2);
                $lines[] = [
                    'description_ar' => $item['description_ar'],
                    'description_en' => $item['description_en'] ?? null,
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => (string) $item['quantity'],
                    'unit_price' => (string) $item['unit_price'],
                    'line_total' => $lineTotal,
                    'sort_order' => $index + 1,
                ];
            }

            $vat = self::signedRound(bcdiv(bcmul($subtotal, (string) $project->vat_rate, 8), '100', 8));

            $variation->subtotal = $subtotal;
            $variation->vat_amount = $vat;
            $variation->total = bcadd($subtotal, $vat, 2);
            $variation->save();

            $variation->items()->delete();
            $variation->items()->createMany($lines);

            return $variation;
        });
    }

    /** Half away from zero, so a removal rounds like the same addition. */
    public static function signedRound(string $value): string
    {
        $negative = str_starts_with(ltrim($value), '-');
        $rounded = QuotationCalculator::round(ltrim($value, '-'), 2);

        return $negative && bccomp($rounded, '0', 2) !== 0 ? '-'.$rounded : $rounded;
    }
}
