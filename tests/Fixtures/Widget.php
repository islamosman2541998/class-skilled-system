<?php

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only model that stands in for a business record when exercising shared components.
 *
 * @property int $id
 * @property string $name_ar
 * @property string|null $name_en
 * @property string $status
 * @property bool $is_featured
 * @property string $amount
 */
class Widget extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'issued_on' => 'date',
        ];
    }

    public static function createTable(): void
    {
        if (Schema::hasTable('widgets')) {
            return;
        }

        Schema::create('widgets', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('status')->default('open');
            $table->boolean('is_featured')->default(false);
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('issued_on')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function make1(array $attributes = []): self
    {
        return self::create($attributes + ['name_ar' => 'عنصر', 'status' => 'open', 'amount' => 10, 'issued_on' => '2026-11-10']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function seedMany(int $count, array $attributes = []): void
    {
        $now = now();
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $rows[] = $attributes + [
                'name_ar' => 'عنصر '.$i, 'name_en' => 'Item '.$i, 'status' => 'open', 'is_featured' => false,
                'amount' => 10, 'issued_on' => '2026-11-10', 'created_at' => $now, 'updated_at' => $now,
            ];
        }

        self::insert($rows);
    }
}
