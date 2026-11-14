<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Text search across columns where % and _ typed by the user match literally (spec §5.1, §5.6).
 */
final class Like
{
    /**
     * Adds "(col1 LIKE term OR col2 LIKE term …)"; "relation.column" searches a related model.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $columns
     * @return Builder<TModel>
     */
    public static function any(Builder $query, array $columns, string $term): Builder
    {
        $term = trim($term);

        if ($term === '' || $columns === []) {
            return $query;
        }

        // "!" is the escape character.
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';

        return $query->where(function (Builder $outer) use ($columns, $like) {
            foreach ($columns as $column) {
                if (str_contains($column, '.')) {
                    [$relation, $related] = explode('.', $column, 2);

                    $outer->orWhereHas($relation, function (Builder $inner) use ($related, $like) {
                        $inner->whereRaw($inner->getGrammar()->wrap($inner->qualifyColumn($related))." LIKE ? ESCAPE '!'", [$like]);
                    });
                } else {
                    $outer->orWhereRaw($outer->getGrammar()->wrap($outer->qualifyColumn($column))." LIKE ? ESCAPE '!'", [$like]);
                }
            }
        });
    }
}
