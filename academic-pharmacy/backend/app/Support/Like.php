<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Búsqueda LIKE segura y portable (PostgreSQL / SQLite).
 *
 * Los comodines del usuario (% y _) se escapan para que se busquen como
 * texto literal. Se usa «!» como carácter de escape porque la barra
 * invertida dentro de un literal SQL confunde al analizador de
 * parámetros de PDO pgsql.
 */
final class Like
{
    public static function pattern(string $term): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower(trim($term))).'%';
    }

    /** @param list<string> $columns  nombres de columna fijos (nunca entrada del usuario) */
    public static function anyColumn(Builder $query, array $columns, string $term): Builder
    {
        $pattern = self::pattern($term);

        return $query->where(function (Builder $w) use ($columns, $pattern) {
            foreach ($columns as $column) {
                $w->orWhereRaw("LOWER({$column}) LIKE ? ESCAPE '!'", [$pattern]);
            }
        });
    }
}
