<?php

namespace App\Actions;

use App\Enums\LookupType;
use App\Models\Lookup;
use App\Models\PriceItem;
use App\Models\User;
use App\Support\ImportResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\SimpleExcel\SimpleExcelReader;

/**
 * Price list import from Excel or CSV (spec §8.4). Columns are matched by header name.
 * A row with the same Arabic description and unit updates the price; when more than half of the
 * rows fail nothing is imported, so a wrong file does not half-load.
 */
class ImportPriceItems
{
    public const HEADERS = ['description_ar', 'description_en', 'service', 'unit', 'price', 'active'];

    public function handle(string $path, User $by): ImportResult
    {
        $units = $this->namesToIds(LookupType::Unit);
        $services = $this->namesToIds(LookupType::Service);
        $valid = [];
        $errors = [];
        $total = 0;

        foreach (SimpleExcelReader::create($path)->getRows() as $index => $row) {
            $total++;
            $line = $index + 2; // header is row 1
            $row = array_change_key_case(array_map(fn ($value) => is_string($value) ? trim($value) : $value, $row));

            $description = (string) ($row['description_ar'] ?? '');
            $unitId = $units->get(mb_strtolower((string) ($row['unit'] ?? '')));
            $serviceName = mb_strtolower((string) ($row['service'] ?? ''));
            $price = str_replace(',', '', (string) ($row['price'] ?? ''));

            $error = match (true) {
                $description === '' => 'description_required',
                $unitId === null => 'unit_not_found',
                $serviceName !== '' && ! $services->has($serviceName) => 'service_not_found',
                ! is_numeric($price) || (float) $price < 0 => 'price_invalid',
                default => null,
            };

            if ($error !== null) {
                $errors[] = __('price_items.import_errors.'.$error, ['row' => $line]);

                continue;
            }

            $valid[] = [
                'description_ar' => mb_substr($description, 0, 2000),
                'description_en' => filled($row['description_en'] ?? null) ? (string) $row['description_en'] : null,
                'service_id' => $serviceName !== '' ? $services->get($serviceName) : null,
                'unit_id' => $unitId,
                'default_price' => number_format((float) $price, 2, '.', ''),
                'is_active' => ! in_array(mb_strtolower((string) ($row['active'] ?? '1')), ['0', 'no', 'false', 'لا'], true),
            ];
        }

        if ($total === 0 || count($errors) * 2 > $total) {
            return new ImportResult(0, 0, $errors);
        }

        [$created, $updated] = DB::transaction(function () use ($valid) {
            $created = 0;
            $updated = 0;

            foreach ($valid as $data) {
                $item = PriceItem::query()->where('description_ar', $data['description_ar'])->where('unit_id', $data['unit_id'])->first();

                if ($item === null) {
                    PriceItem::query()->create($data);
                    $created++;
                } else {
                    $item->fill(array_filter($data, fn ($value) => $value !== null))->save();
                    $updated++;
                }
            }

            return [$created, $updated];
        });

        return new ImportResult($created, $updated, $errors);
    }

    /**
     * Arabic and English names (lower-case) to ids, including inactive rows.
     *
     * @return Collection<string, int>
     */
    private function namesToIds(LookupType $type): Collection
    {
        $map = collect();

        foreach (Lookup::query()->ofType($type)->get(['id', 'name_ar', 'name_en']) as $lookup) {
            $map->put(mb_strtolower($lookup->name_ar), $lookup->id);

            if (filled($lookup->name_en)) {
                $map->put(mb_strtolower((string) $lookup->name_en), $lookup->id);
            }
        }

        return $map;
    }
}
