<?php

namespace App\Actions;

use App\Contracts\GuardsDeletion;
use App\Models\User;
use App\Tables\DeleteResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Soft-deletes every record of a query one by one, so model events fire and guarded
 * records are skipped and counted instead of failing the whole operation.
 */
class DeleteRecords
{
    public function handle(Builder $query, User $by): DeleteResult
    {
        $result = new DeleteResult;

        $query->reorder()->chunkById(200, function ($records) use ($by, $result) {
            foreach ($records as $record) {
                $reason = $record instanceof GuardsDeletion ? $record->deletionBlockedReason($by) : null;

                if ($reason !== null) {
                    $result->skipped++;
                    $result->firstReason ??= $reason;

                    continue;
                }

                $record->delete();
                $result->deleted++;
            }
        });

        return $result;
    }
}
