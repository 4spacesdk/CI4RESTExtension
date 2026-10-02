<?php

namespace Tests\Support\Crm;

use RestExtension\Filter\Operators;
use RestExtension\Filter\QueryFilter;
use RestExtension\QueryParser;

/**
 * What a filter or an include on the CRM must answer, worked out without RestExtension: the
 * rules written again as plain SQL, the links between the tables written out by hand, and the
 * rest done in PHP with sets of ids.
 *
 * A filter on a relation matches a row with a related row the caller may read that matches. Its
 * `null` matches as a LEFT JOIN's empty row does - a related row whose field is null, a key or a
 * pivot row that leads to no row, no related row - and a related row the caller may not read is
 * no row at all.
 */
final class CrmOracle
{
    /**
     * model => [table, soft deleted]
     */
    private const MODELS = [
        'team' => ['teams', true], 'rep' => ['reps', true], 'team_member' => ['team_members', true], 'company' => ['companies', true],
        'contact' => ['contacts', true], 'deal' => ['deals', true], 'activity' => ['activities', false], 'tag' => ['tags', false],
        'industry' => ['industries', false], 'country' => ['countries', true],
    ];

    /**
     * model.relation => [related model, has many, how the rows are tied]:
     * ['base', column, related column] - the model holds the key;
     * ['related', column] - the related table holds the model's id;
     * ['table', pivot, model's column, related column] - a pivot between them.
     */
    private const LINKS = [
        'team.parent' => ['team', false, ['base', 'parent_id', 'id']],
        'team.lead' => ['rep', false, ['base', 'lead_rep_id', 'id']],
        'team.child' => ['team', true, ['related', 'parent_id']],
        'team.rep' => ['rep', true, ['table', 'team_members', 'team_id', 'rep_id']],
        'team.team_member' => ['team_member', true, ['related', 'team_id']],
        'team.owned_company' => ['company', true, ['related', 'owner_team_id']],
        'team.home_rep' => ['rep', true, ['related', 'home_team_id']],
        'rep.home_team' => ['team', false, ['base', 'home_team_id', 'id']],
        'rep.team' => ['team', true, ['table', 'team_members', 'rep_id', 'team_id']],
        'rep.team_member' => ['team_member', true, ['related', 'rep_id']],
        'rep.led_team' => ['team', true, ['related', 'lead_rep_id']],
        'rep.owned_contact' => ['contact', true, ['related', 'owner_rep_id']],
        'rep.owned_deal' => ['deal', true, ['related', 'owner_rep_id']],
        'rep.authored_activity' => ['activity', true, ['related', 'author_rep_id']],
        'team_member.rep' => ['rep', false, ['base', 'rep_id', 'id']],
        'team_member.team' => ['team', false, ['base', 'team_id', 'id']],
        'company.owner_team' => ['team', false, ['base', 'owner_team_id', 'id']],
        'company.parent' => ['company', false, ['base', 'parent_id', 'id']],
        'company.industry' => ['industry', false, ['base', 'industry_id', 'id']],
        'company.country' => ['country', false, ['base', 'country_code', 'code']],
        'company.child' => ['company', true, ['related', 'parent_id']],
        'company.contact' => ['contact', true, ['related', 'company_id']],
        'company.deal' => ['deal', true, ['related', 'company_id']],
        'company.tag' => ['tag', true, ['table', 'companies_tags', 'company_id', 'tag_id']],
        'contact.company' => ['company', false, ['base', 'company_id', 'id']],
        'contact.owner' => ['rep', false, ['base', 'owner_rep_id', 'id']],
        'contact.deal' => ['deal', true, ['table', 'deal_participants', 'contact_id', 'deal_id']],
        'contact.activity' => ['activity', true, ['related', 'contact_id']],
        'contact.primary_deal' => ['deal', true, ['related', 'primary_contact_id']],
        'deal.company' => ['company', false, ['base', 'company_id', 'id']],
        'deal.owner' => ['rep', false, ['base', 'owner_rep_id', 'id']],
        'deal.primary_contact' => ['contact', false, ['base', 'primary_contact_id', 'id']],
        'deal.participant' => ['contact', true, ['table', 'deal_participants', 'deal_id', 'contact_id']],
        'deal.activity' => ['activity', true, ['related', 'deal_id']],
        'activity.deal' => ['deal', false, ['base', 'deal_id', 'id']],
        'activity.contact' => ['contact', false, ['base', 'contact_id', 'id']],
        'activity.author' => ['rep', false, ['base', 'author_rep_id', 'id']],
        'tag.company' => ['company', true, ['table', 'companies_tags', 'tag_id', 'company_id']],
        'industry.company' => ['company', true, ['related', 'industry_id']],
        'country.company' => ['company', true, ['related', 'country_code', 'code']],
    ];

    /** @var array<string, array<int, true>> */
    private array $visible = [];

    /** @var array<string, array<int, list<int|null>>> */
    private static array $links = [];

    /**
     * The ids that `filter` on `model` must find, sorted, for whoever CrmClient says is asking.
     * Filters separated by commas must all match; the battery never has two searches, which
     * RestExtension ORs.
     *
     * @return list<int>
     */
    public function filter(string $model, string $filter): array
    {
        $parser = new QueryParser();
        $parser->parseFilter($filter);
        $result = null;
        foreach ([...$parser->getFilters(), ...$parser->getSearchFilters()] as $parsed) {
            $ids = $this->matching($model, explode('.', $parsed->property), $parsed);
            $result = $result === null ? $ids : array_intersect_key($result, $ids);
        }
        $ids = array_keys($result ?? $this->visible($model));
        sort($ids);

        return $ids;
    }

    /**
     * What an include must give each of these rows: the id of the related row, or null, for a
     * has-one; the sorted ids of the related rows for a has-many.
     *
     * @param list<int> $ids
     *
     * @return array<int, int|list<int>|null>
     */
    public function include(string $model, string $relation, array $ids): array
    {
        [$related, $hasMany] = self::LINKS["{$model}.{$relation}"];
        $visible = $this->visible($related);
        $links = self::links("{$model}.{$relation}");
        $answer = [];
        foreach ($ids as $id) {
            $own = [];
            foreach ($links[$id] ?? [] as $key) {
                if ($key !== null && isset($visible[$key])) {
                    $own[$key] = $key;
                }
            }
            sort($own);
            $answer[$id] = $hasMany ? array_values($own) : ($own[0] ?? null);
        }

        return $answer;
    }

    public static function hasMany(string $model, string $relation): bool
    {
        return self::LINKS["{$model}.{$relation}"][1];
    }

    /**
     * @param list<string> $path
     *
     * @return array<int, true>
     */
    private function matching(string $model, array $path, QueryFilter $filter): array
    {
        $visible = $this->visible($model);
        if (count($path) === 1) {
            return array_intersect_key($visible, $this->fieldMatches($model, $path[0], $filter));
        }

        [$related] = self::LINKS["{$model}.{$path[0]}"];
        $matching = $this->matching($related, array_slice($path, 1), $filter);
        $relatedVisible = $this->visible($related);
        $links = self::links("{$model}.{$path[0]}");
        $null = $filter->operator === Operators::Equal && $filter->value === null;

        $result = [];
        foreach ($visible as $id => $_) {
            $seen = false;
            $hit = false;
            foreach ($links[$id] ?? [] as $key) {
                if ($key === null) {
                    // A key or a pivot row that leads nowhere: the join's empty row
                    if ($null) {
                        $hit = true;
                        break;
                    }
                    continue;
                }
                if (isset($relatedVisible[$key])) {
                    $seen = true;
                    if (isset($matching[$key])) {
                        $hit = true;
                        break;
                    }
                }
            }
            if ($hit || ($null && ! $seen)) {
                $result[$id] = true;
            }
        }

        return $result;
    }

    /**
     * @return array<int, true>
     */
    private function fieldMatches(string $model, string $field, QueryFilter $filter): array
    {
        [$table] = self::MODELS[$model];
        $builder = CrmWorld::db()->table($table)->select('id');
        $value = $filter->value;
        switch ($filter->operator) {
            case Operators::Search:
                $builder->groupStart();
                foreach ((array) $value as $one) {
                    $builder->orLike($field, $one, 'both', null, true);
                }
                $builder->groupEnd();
                break;
            case Operators::Not:
                if (is_array($value)) {
                    $builder->whereNotIn($field, $value);
                } elseif ($value === null) {
                    $builder->where("{$field} IS NOT NULL");
                } else {
                    $builder->where("{$field} !=", $value);
                }
                break;
            default:
                if (is_array($value)) {
                    $builder->whereIn($field, $value);
                } elseif ($value === null && $filter->operator === Operators::Equal) {
                    $builder->where($field, null);
                } else {
                    $builder->where("{$field} {$filter->operator}", $value);
                }
        }

        return self::idSet($builder->get()->getResultArray());
    }

    /**
     * The rules of the models, as plain SQL.
     *
     * @return array<int, true>
     */
    public function visible(string $model): array
    {
        if (isset($this->visible[$model])) {
            return $this->visible[$model];
        }
        [$table, $softDeleted] = self::MODELS[$model];
        $where = $softDeleted ? ["{$table}.deletion_id IS NULL"] : [];
        if (! CrmClient::$admin) {
            $rep = CrmClient::$repId;
            $teams = implode(',', CrmClient::teamIds());
            $companies = "SELECT id FROM companies WHERE deletion_id IS NULL AND owner_team_id IN ({$teams})";
            $rule = match ($model) {
                'team' => "id IN ({$teams})",
                'rep' => "(id = {$rep} OR id IN (SELECT rep_id FROM team_members WHERE deletion_id IS NULL AND team_id IN ({$teams})))",
                'team_member' => "team_id IN ({$teams})",
                'company' => "(owner_team_id IN ({$teams}) OR is_shared = 1)",
                'contact' => "(owner_rep_id = {$rep} OR company_id IN ({$companies}))",
                'deal' => "(owner_rep_id = {$rep} OR (company_id IN ({$companies}) AND is_private = 0))",
                'activity' => "(author_rep_id = {$rep}
                    OR deal_id IN (SELECT id FROM deals WHERE deletion_id IS NULL AND owner_rep_id = {$rep})
                    OR deal_id IN (SELECT id FROM deals WHERE deletion_id IS NULL AND company_id IN ({$companies})))",
                default => null,
            };
            if ($rule !== null) {
                $where[] = $rule;
            }
        }
        $sql = "SELECT id FROM {$table}" . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where));

        return $this->visible[$model] = self::idSet(CrmWorld::db()->query($sql)->getResultArray());
    }

    /**
     * Each row's links: the related row's id, or null for one that leads nowhere.
     *
     * @return array<int, list<int|null>>
     */
    private static function links(string $name): array
    {
        if (isset(self::$links[$name])) {
            return self::$links[$name];
        }
        [$model] = explode('.', $name);
        [$related, , $how] = self::LINKS[$name];
        [$table] = self::MODELS[$model];
        [$relatedTable, $relatedSoftDeleted] = self::MODELS[$related];
        $existing = $relatedSoftDeleted ? ' AND r.deletion_id IS NULL' : '';

        $sql = match ($how[0]) {
            'base' => "SELECT b.id AS base, r.id AS related FROM {$table} b LEFT JOIN {$relatedTable} r ON r.{$how[2]} = b.{$how[1]}{$existing} WHERE b.{$how[1]} IS NOT NULL",
            'related' => isset($how[2])
                ? "SELECT b.id AS base, r.id AS related FROM {$relatedTable} r JOIN {$table} b ON b.{$how[2]} = r.{$how[1]} WHERE 1 = 1{$existing}"
                : "SELECT r.{$how[1]} AS base, r.id AS related FROM {$relatedTable} r WHERE r.{$how[1]} IS NOT NULL{$existing}",
            'table' => "SELECT p.{$how[2]} AS base, r.id AS related FROM {$how[1]} p LEFT JOIN {$relatedTable} r ON r.id = p.{$how[3]}{$existing} WHERE p.{$how[2]} IS NOT NULL",
        };
        $links = [];
        foreach (CrmWorld::db()->query($sql)->getResultArray() as $row) {
            $links[(int) $row['base']][] = $row['related'] === null ? null : (int) $row['related'];
        }

        return self::$links[$name] = $links;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<int, true>
     */
    private static function idSet(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $ids[(int) $row['id']] = true;
        }

        return $ids;
    }
}
