<?php

namespace Tests\Realistic;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Ask;
use Tests\Support\Crm\CrmOracle;
use Tests\Support\Engine;

/**
 * Includes along a path of two and three relations, every mix of has-one, has-many and pivot, as
 * an admin and as three reps: each level must hold what CrmOracle says the caller may read. As an
 * admin, a path of has-ones only must also be what RestExtension's joins include; through a
 * has-many, RestExtension includes it wrongly, so it is only held against the oracle.
 */
final class DeepIncludesTest extends RealisticTestCase
{
    private const PATHS = [
        'contact' => ['company.owner_team', 'company.owner_team.parent', 'company.country', 'owner.home_team', 'deal.company', 'activity.deal', 'company.tag', 'deal.participant'],
        'deal' => ['company.owner_team', 'participant.company', 'primary_contact.company.country', 'owner.team', 'activity.author', 'company.contact', 'company.parent.owner_team'],
        'activity' => ['deal.company', 'deal.participant', 'contact.company.owner_team', 'author.team', 'deal.owner.home_team', 'contact.deal.company'],
        'company' => ['parent.owner_team', 'owner_team.parent.parent', 'deal.owner', 'child.contact', 'contact.activity', 'tag.company'],
        'team' => ['rep.home_team', 'child.child', 'owned_company.country', 'lead.team'],
        'rep' => ['team.parent', 'owned_deal.company', 'home_team.owned_company'],
    ];

    /** Pages where the rows are, and small enough that a path of has-manys stays readable */
    private const PAGES = ['contact' => [600, 20], 'deal' => [300, 20], 'activity' => [1000, 20], 'company' => [0, 8], 'team' => [0, 12], 'rep' => [0, 15]];

    /** Where the default page has nothing to include: the first company in a group is 113 */
    private const PATH_PAGES = ['company parent.owner_team' => [150, 15], 'company child.contact' => [110, 40]];

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function cases(): iterable
    {
        foreach (self::USERS as $user) {
            foreach (self::PATHS as $model => $paths) {
                foreach ($paths as $path) {
                    yield "{$user} {$model} include={$path}" => [$user, $model, $path];
                }
            }
        }
    }

    #[DataProvider('cases')]
    public function testEveryLevelHoldsWhatTheOracleSays(string $user, string $model, string $path): void
    {
        self::signIn($user);
        $oracle = new CrmOracle();
        [$offset, $limit] = self::PATH_PAGES["{$model} {$path}"] ?? self::PAGES[$model];
        $visible = count($oracle->visible($model));
        $offset = min($offset, max(0, $visible - $limit));

        Engine::$candidate = true;
        $rows = Ask::rows(self::modelClass($model), '', 'id:asc', $path, $limit, $offset);
        $this->assertIsArray($rows, is_string($rows) ? $rows : '');
        if ($visible === 0) {
            $this->assertSame([], $rows);

            return;
        }
        $this->assertNotEmpty($rows);

        $names = explode('.', $path);
        $ids = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $actual = [];
        foreach ($rows as $row) {
            $actual[(int) $row['id']] = self::shape($row, $model, $names);
        }
        $this->assertSame($oracle->tree($model, $names, $ids), $actual, "{$user} {$model} include={$path}");
        if ($user === 'admin') {
            $this->assertNotSame([], array_filter($actual), "{$model} include={$path} includes nothing on the page");
        }

        if ($user === 'admin' && self::hasOnesOnly($model, $names)) {
            Engine::$candidate = false;
            $reference = Ask::rows(self::modelClass($model), '', 'id:asc', $path, $limit, $offset);
            $this->assertSame(json_encode($reference), json_encode($rows), "reference {$model} include={$path}");
        }
    }

    /**
     * The include of a row as the oracle writes it: ids at every level, a has-many by id.
     *
     * @param array<string, mixed> $row
     * @param list<string> $path
     */
    private static function shape(array $row, string $model, array $path): mixed
    {
        [$related, $hasMany] = CrmOracle::relation($model, $path[0]);
        $rest = array_slice($path, 1);
        $property = $hasMany ? plural($path[0]) : $path[0];
        $node = static fn (array $one): array => $rest === [] ? ['id' => (int) $one['id']] : ['id' => (int) $one['id'], 'next' => self::shape($one, $related, $rest)];

        if ($hasMany) {
            $list = array_map($node, $row[$property] ?? []);
            usort($list, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);

            return $list;
        }

        return isset($row[$property]['id']) ? $node($row[$property]) : null;
    }

    /**
     * @param list<string> $path
     */
    private static function hasOnesOnly(string $model, array $path): bool
    {
        foreach ($path as $relation) {
            [$related, $hasMany] = CrmOracle::relation($model, $relation);
            if ($hasMany) {
                return false;
            }
            $model = $related;
        }

        return true;
    }
}
