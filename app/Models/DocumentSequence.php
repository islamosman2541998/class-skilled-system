<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * The numbering rule and counter of one document type.
 *
 * @property int $id
 * @property DocumentType $type
 * @property string $prefix
 * @property int $padding
 * @property int $next_number
 * @property bool $include_year
 * @property bool $reset_yearly
 * @property int|null $year year of the last issued number
 */
class DocumentSequence extends Model
{
    use LogsModelActivity;

    protected $fillable = ['type', 'prefix', 'padding', 'next_number', 'include_year', 'reset_yearly', 'year'];

    /** The counter moves with every issued document, so only the rule itself is logged. */
    protected array $activityAttributes = ['prefix', 'padding', 'include_year', 'reset_yearly'];

    public function activityModule(): string
    {
        return 'numbering';
    }

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'padding' => 'integer',
            'next_number' => 'integer',
            'include_year' => 'boolean',
            'reset_yearly' => 'boolean',
            'year' => 'integer',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(DocumentType $type): array
    {
        return [
            'type' => $type->value,
            'prefix' => $type->defaultPrefix(),
            'padding' => 4,
            'next_number' => 1,
            'include_year' => true,
            'reset_yearly' => true,
        ];
    }

    /** Makes sure every document type has its row. */
    public static function ensureAll(): void
    {
        $existing = self::query()->pluck('type')->map(fn ($type) => $type instanceof DocumentType ? $type->value : $type)->all();

        foreach (DocumentType::cases() as $type) {
            if (! in_array($type->value, $existing, true)) {
                self::create(self::defaults($type));
            }
        }
    }

    /** The number this sequence would issue for the given year, and the counter that produced it. */
    public function upcoming(int $year): int
    {
        return $this->reset_yearly && $this->year !== null && $this->year !== $year ? 1 : $this->next_number;
    }

    public function format(int $number, int $year): string
    {
        $padded = str_pad((string) $number, $this->padding, '0', STR_PAD_LEFT);

        return $this->include_year ? "{$this->prefix}-{$year}-{$padded}" : "{$this->prefix}-{$padded}";
    }
}
