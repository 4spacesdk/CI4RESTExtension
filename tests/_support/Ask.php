<?php

namespace Tests\Support;

use OrmExtension\Extensions\Entity;
use RestExtension\QueryParser;

/**
 * A request to a model's restGet(), as the API would make it, and its answer as the API would
 * give it: the rows as arrays, the count, or the exception's class and message.
 */
final class Ask
{
    /**
     * @return list<array<string, mixed>>|int|string
     */
    public static function rows(string $model, string $filter = '', ?string $ordering = 'id:asc', ?string $include = null, ?int $limit = null, ?int $offset = null): array|int|string
    {
        return self::run($model, $filter, $ordering, $include, $limit, $offset, false);
    }

    public static function count(string $model, string $filter = ''): int|string
    {
        $count = self::run($model, $filter, null, null, null, null, true);

        return is_array($count) ? 'not a count' : $count;
    }

    /**
     * @return list<int>|string
     */
    public static function ids(string $model, string $filter = '', ?string $include = null, string $ordering = 'id:asc'): array|string
    {
        $rows = self::rows($model, $filter, $ordering, $include);

        return is_array($rows) ? array_map(static fn (array $row): int => (int) $row['id'], $rows) : $rows;
    }

    /**
     * @return list<array<string, mixed>>|int|string
     */
    private static function run(string $model, string $filter, ?string $ordering, ?string $include, ?int $limit, ?int $offset, bool $count): array|int|string
    {
        $parser = new QueryParser();
        if ($filter !== '') {
            $parser->parseFilter($filter);
        }
        if ($ordering !== null && ! $count) {
            $parser->parseOrdering($ordering);
        }
        if ($include !== null) {
            $parser->parseInclude($include);
        }
        $set = \Closure::bind(function (string $name, mixed $value): void { $this->{$name} = $value; }, $parser, QueryParser::class);
        if ($limit !== null) {
            $set('limit', $limit);
            $set('offset', $offset);
        }
        if ($count) {
            $set('count', true);
        }

        try {
            $result = (new $model())->restGet(null, $parser);
        } catch (\Throwable $e) {
            return get_class($e) . ': ' . $e->getMessage();
        }
        if (! $result instanceof Entity) {
            return (int) $result;
        }

        return $result->exists() ? $result->allToArray(false, true, true) : [];
    }
}
