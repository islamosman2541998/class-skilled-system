<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\LogsModelActivity;
use App\Services\Finance\ProjectLedger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;

/**
 * An expense on a project or a general company expense (spec §8.12). The receipt photo is an
 * attachment. Saving, deleting or restoring keeps the project cost figure up to date.
 *
 * @property int $id
 * @property string $number
 * @property int|null $project_id
 * @property int $category_id
 * @property string|null $supplier_name
 * @property Carbon $expense_date
 * @property string $amount
 * @property string $vat_rate
 * @property string $vat_amount
 * @property string $total
 * @property int|null $payment_method_id
 * @property int|null $paid_by
 * @property string|null $supplier_invoice_no
 * @property string|null $description
 * @property int|null $created_by
 * @property-read Project|null $project
 * @property-read Lookup $category
 * @property-read Lookup|null $paymentMethod
 * @property-read User|null $payer
 * @property-read User|null $creator
 */
class Expense extends Model implements HasMedia
{
    use HasAttachments, LogsModelActivity, SoftDeletes;

    protected $fillable = ['project_id', 'category_id', 'supplier_name', 'expense_date', 'payment_method_id', 'paid_by', 'supplier_invoice_no', 'description'];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        $refresh = function (Expense $expense) {
            $ids = array_unique(array_filter([$expense->project_id, $expense->getOriginal('project_id')]));

            foreach (Project::withTrashed()->whereIn('id', $ids)->get() as $project) {
                ProjectLedger::refresh($project);
            }
        };

        static::saved($refresh);
        static::deleted($refresh);
        static::restored($refresh);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Lookup, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'category_id');
    }

    /** @return BelongsTo<Lookup, $this> */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'payment_method_id');
    }

    /** @return BelongsTo<User, $this> */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Users of an "assigned only" role see the expenses of their projects and those they entered.
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->seesAllRecords()) {
            return;
        }

        $query->where(fn (Builder $inner) => $inner
            ->whereHas('project', fn (Builder $projects) => $projects->visibleTo($user))
            ->orWhere($query->qualifyColumn('created_by'), $user->id));
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->seesAllRecords()
            || $this->created_by === $user->id
            || ($this->project_id !== null && Project::query()->visibleTo($user)->whereKey($this->project_id)->exists());
    }

    public function attachmentPermission(): string
    {
        return 'expenses';
    }
}
