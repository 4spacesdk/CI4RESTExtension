<?php

namespace Tests\Realistic;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Ask;
use Tests\Support\Crm\CrmOracle;
use Tests\Support\Engine;

/**
 * Filters on relations of every kind, as an admin and as three reps: the count and two pages of
 * the rules engine must be what CrmOracle works out. As an admin, whom no rule narrows,
 * RestExtension's joins must give the same - which checks the oracle too.
 */
final class FiltersTest extends RealisticTestCase
{
    public const FILTERS = [
        'company' => [
            'owner_team.name:Team 5', 'owner_team.name:null', 'owner_team.parent.name:Team 1', 'owner_team.parent.parent.name:Team 2', 'owner_team.lead.email:null',
            'parent.name:~Nordic', 'parent.name:null', 'parent.owner_team.name:Team 13', 'industry.name:Industry 7', 'industry.name:null', 'country.name:Denmark',
            'country.name:null', 'country.code:-DK', 'child.revenue:>9000000', 'child.name:null', 'contact.email:~post.se', 'contact.email:null',
            'contact.last_name:[Hansen,Jensen]', 'deal.stage:won', 'deal.amount:>=990000', 'deal.amount:null', 'deal.participant.email:~firma',
            'deal.owner.email:-null', 'tag.name:Tag 3', 'tag.name:null', 'tag.name:~Tag 29', 'contact.activity.kind:meeting', 'revenue:>5000000,deal.stage:lost',
            'name:~Smart,owner_team.name:-Team 1',
        ],
        'contact' => [
            'company.name:~Labs', 'company.owner_team.name:Team 13', 'company.country.code:SE', 'company.name:null', 'company.is_shared:1', 'owner.email:~rep1',
            'owner.home_team.name:Team 7', 'owner.email:null', 'deal.stage:qualified', 'deal.stage:null', 'deal.company.is_shared:1', 'activity.kind:call',
            'activity.created:>2025-11-01', 'primary_deal.amount:>500000', 'company.tag.name:Tag 9', 'company.parent.country.name:Norway',
        ],
        'deal' => [
            'company.owner_team.parent.name:Team 2', 'company.name:~Energy', 'owner.team.name:Team 20', 'owner.team_member.role:lead', 'primary_contact.email:null',
            'primary_contact.company.name:~Foods', 'participant.last_name:Nielsen', 'participant.email:null', 'participant.company.country.name:Sweden',
            'activity.author.email:~rep2', 'activity.kind:-note', 'stage:[won,lost],company.is_shared:0', 'company.industry.name:Industry 3', 'owner.home_team.name:null',
        ],
        'activity' => [
            'deal.stage:won', 'deal.company.name:~Retail', 'contact.email:~firma', 'contact.company.owner_team.name:Team 9', 'author.home_team.name:null',
            'deal.owner.email:null', 'deal.participant.last_name:Hansen', 'deal.company.name:null',
        ],
        'rep' => [
            'team.name:Team 3', 'team.parent.name:Team 1', 'home_team.name:null', 'owned_deal.stage:won', 'owned_contact.company.name:~Nordic', 'led_team.name:-null',
            'team_member.role:lead', 'authored_activity.kind:meeting', 'team.name:null',
        ],
        'team' => [
            'parent.name:null', 'child.name:Team 13', 'rep.email:~rep14', 'owned_company.country.code:US', 'lead.email:null', 'home_rep.owned_deal.stage:won',
            'team_member.role:lead', 'rep.email:null',
        ],
        'tag' => ['company.name:~Smart', 'company.owner_team.name:Team 4', 'company.name:null'],
        'industry' => ['company.deal.stage:won', 'company.name:null'],
        'country' => ['company.name:~Prime', 'company.contact.email:null', 'company.owner_team.name:Team 11'],
    ];

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function cases(): iterable
    {
        foreach (self::USERS as $user) {
            foreach (self::FILTERS as $model => $filters) {
                foreach ($filters as $filter) {
                    yield "{$user} {$model} {$filter}" => [$user, $model, $filter];
                }
            }
        }
    }

    #[DataProvider('cases')]
    public function testTheRulesEngineAnswersAsTheOracle(string $user, string $model, string $filter): void
    {
        self::signIn($user);
        $expected = (new CrmOracle())->filter($model, $filter);

        Engine::$candidate = true;
        $this->assertSame(count($expected), Ask::count(self::modelClass($model), $filter), "{$user} count {$model} {$filter}");
        $this->assertSame(array_slice($expected, 0, 50), self::page($model, $filter, 50, 0), "{$user} first page {$model} {$filter}");
        $this->assertSame(array_slice($expected, 100, 50), self::page($model, $filter, 50, 100), "{$user} third page {$model} {$filter}");

        if ($user === 'admin') {
            Engine::$candidate = false;
            $this->assertSame(count($expected), Ask::count(self::modelClass($model), $filter), "reference count {$model} {$filter}");
            $this->assertSame(array_slice($expected, 0, 50), self::page($model, $filter, 50, 0), "reference first page {$model} {$filter}");
        }
    }

    /**
     * A battery where everything matches or nothing does proves little.
     */
    public function testTheFiltersSayNoAndYes(): void
    {
        $oracle = new CrmOracle();
        $narrowing = 0;
        $all = 0;
        foreach (self::FILTERS as $model => $filters) {
            $visible = count($oracle->visible($model));
            foreach ($filters as $filter) {
                $count = count($oracle->filter($model, $filter));
                $all++;
                if ($count > 0 && $count < $visible) {
                    $narrowing++;
                }
            }
        }
        $this->assertGreaterThanOrEqual(0.9, $narrowing / $all, "{$narrowing} of {$all} filters find some rows but not all");
    }
}
