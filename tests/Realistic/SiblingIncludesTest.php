<?php

namespace Tests\Realistic;

use PHPUnit\Framework\Attributes\DataProvider;
use RestExtension\Exceptions\InvalidRequestException;
use Tests\Support\Ask;
use Tests\Support\Engine;

/**
 * Two relations under the same parent in one request. They arrive whether or not relations follow
 * rules: without rules the joins pile up on one query, with rules the includes are made one
 * before they are fetched. It was the second that used to keep only the last of them.
 */
final class SiblingIncludesTest extends RealisticTestCase
{
    /**
     * @return iterable<string, array{bool}>
     */
    public static function engines(): iterable
    {
        yield 'relations follow rules' => [true];
        yield 'relations do not follow rules' => [false];
    }

    /**
     * @return list<string>
     */
    private function nestedUnderCompany(string $include): array
    {
        $rows = Ask::rows(self::modelClass('contact'), '', 'id:asc', $include, 5, 0);
        $this->assertIsArray($rows, is_string($rows) ? $rows : '');
        $seen = [];
        foreach ($rows as $row) {
            if (isset($row['company']) && is_array($row['company'])) {
                foreach (array_filter($row['company'], 'is_array') as $name => $nested) {
                    $seen[$name] = $name;
                }
            }
        }
        sort($seen);

        return array_values($seen);
    }

    #[DataProvider('engines')]
    public function testBothRelationsUnderOneParentArrive(bool $followRules): void
    {
        self::signIn('admin');
        Engine::$candidate = $followRules;

        $this->assertSame(['owner_team'], $this->nestedUnderCompany('company.owner_team'));
        $this->assertSame(['country'], $this->nestedUnderCompany('company.country'));
        $this->assertSame(['country', 'owner_team'], $this->nestedUnderCompany('company.owner_team,company.country'));
    }

    #[DataProvider('engines')]
    public function testTheOrderOfTheTwoDoesNotDecideWhatComesBack(bool $followRules): void
    {
        self::signIn('admin');
        Engine::$candidate = $followRules;

        $this->assertSame(
            $this->nestedUnderCompany('company.owner_team,company.country'),
            $this->nestedUnderCompany('company.country,company.owner_team'),
        );
    }

    /**
     * A comma ends a sub query, so the second one would be an include on the contact itself -
     * a request that fails where there is no such relation and answers wrongly where there is.
     */
    #[DataProvider('engines')]
    public function testASubQueryFollowedByMoreIsRefused(bool $followRules): void
    {
        self::signIn('admin');
        Engine::$candidate = $followRules;

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionCode(422);

        Ask::rows(self::modelClass('contact'), '', 'id:asc', 'company?include=owner_team,country', 5, 0);
    }

    #[DataProvider('engines')]
    public function testOneSubQueryOnItsOwnStillNests(bool $followRules): void
    {
        self::signIn('admin');
        Engine::$candidate = $followRules;

        $this->assertSame(['country'], $this->nestedUnderCompany('company?include=country'));
    }
}
