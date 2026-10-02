<?php

namespace Tests\Realistic;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Ask;
use Tests\Support\Crm\CrmOracle;
use Tests\Support\Engine;

/**
 * Includes on a page of rows, as an admin and as three reps: each row's related rows must be the
 * ones CrmOracle says the caller may read. As an admin RestExtension must include the same.
 */
final class IncludesTest extends RealisticTestCase
{
    private const INCLUDES = [
        'company' => ['owner_team', 'parent', 'country', 'industry', 'contact', 'deal', 'tag', 'child'],
        'contact' => ['company', 'owner', 'deal', 'activity', 'primary_deal'],
        'deal' => ['company', 'owner', 'primary_contact', 'participant', 'activity'],
        'activity' => ['deal', 'contact', 'author'],
        'rep' => ['home_team', 'team', 'team_member', 'owned_deal'],
        'team' => ['parent', 'lead', 'rep', 'child', 'owned_company'],
        'country' => ['company'],
    ];

    /** Pages that start where the rows are: the big companies are the first ones */
    private const OFFSETS = ['company' => 0, 'contact' => 500, 'deal' => 300, 'activity' => 1000, 'rep' => 0, 'team' => 0, 'country' => 0];

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function cases(): iterable
    {
        foreach (self::USERS as $user) {
            foreach (self::INCLUDES as $model => $includes) {
                foreach ($includes as $include) {
                    yield "{$user} {$model} include={$include}" => [$user, $model, $include];
                }
            }
        }
    }

    #[DataProvider('cases')]
    public function testEachRowHasWhatTheOracleSays(string $user, string $model, string $relation): void
    {
        self::signIn($user);
        $hasMany = CrmOracle::hasMany($model, $relation);
        $include = $hasMany ? "{$relation}?ordering=id:asc" : $relation;
        $limit = $model === 'company' ? 10 : 30;

        // A rep may see fewer rows than the offset; then the page ends with what they see
        $visible = count((new CrmOracle())->visible($model));
        $offset = min(self::OFFSETS[$model], max(0, $visible - $limit));

        Engine::$candidate = true;
        $rows = Ask::rows(self::modelClass($model), '', 'id:asc', $include, $limit, $offset);
        $this->assertIsArray($rows, is_string($rows) ? $rows : '');
        if ($visible === 0) {
            $this->assertSame([], $rows);

            return;
        }
        $this->assertNotEmpty($rows);

        $ids = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $expected = (new CrmOracle())->include($model, $relation, $ids);
        $property = $hasMany ? plural($relation) : $relation;
        $actual = [];
        foreach ($rows as $row) {
            $actual[(int) $row['id']] = $hasMany
                ? array_map(static fn (array $related): int => (int) $related['id'], $row[$property] ?? [])
                : (isset($row[$property]['id']) ? (int) $row[$property]['id'] : null);
        }
        $this->assertSame($expected, $actual, "{$user} {$model} include={$include}");

        if ($user === 'admin') {
            Engine::$candidate = false;
            $reference = Ask::rows(self::modelClass($model), '', 'id:asc', $include, $limit, $offset);
            $this->assertSame(json_encode($reference), json_encode($rows), "reference {$model} include={$include}");
        }
    }
}
