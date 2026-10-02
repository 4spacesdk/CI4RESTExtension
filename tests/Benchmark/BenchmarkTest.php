<?php

namespace Tests\Benchmark;

use Tests\Realistic\RealisticTestCase;
use Tests\Support\Ask;
use Tests\Support\Engine;

/**
 * What the CRM's list views cost: a page of 25 and its count, as RestExtension joins them
 * (reference) and through the rules, with the keys of a relation fetched into a list up to
 * 1 000 (the default), always left as a sub query (0), and fetched up to 10 000.
 *
 * Writes build/benchmark.md. It measures, it does not judge: a time is the median of five, after
 * one run that warms the caches.
 */
final class BenchmarkTest extends RealisticTestCase
{
    private const REQUESTS = [
        ['company', 'owner_team.name:Team 5', null, null],
        ['company', 'country.name:Denmark', null, null],
        ['company', 'contact.email:~post.se', null, null],
        ['company', 'deal.stage:won', null, null],
        ['company', 'tag.name:Tag 3', null, null],
        ['company', 'contact.email:null', null, null],
        ['contact', 'company.name:~Labs', null, null],
        ['contact', 'owner.email:~rep1', null, null],
        ['contact', 'deal.stage:null', null, null],
        ['deal', 'participant.company.country.name:Sweden', null, null],
        ['deal', 'company.owner_team.parent.name:Team 2', null, null],
        ['activity', 'deal.stage:won', null, null],
        ['activity', 'contact.company.owner_team.name:Team 9', null, null],
        ['activity', 'deal.company.name:null', null, null],
        ['contact', '', null, null],
        ['company', '', 'contact?ordering=id:asc,deal?ordering=id:asc', null],
        ['deal', '', 'company,owner,primary_contact,participant?ordering=id:asc', null],
        ['activity', '', 'deal,contact,author', null],
        ['company', '', null, 'owner_team.name:asc,id:desc'],
        ['deal', 'stage:won', null, 'company.name:asc,id:desc'],
    ];

    private const ENGINES = ['reference' => [false, 1000], 'rules' => [true, 1000], 'rules, sub query' => [true, 0], 'rules, 10 000' => [true, 10000]];

    public function testWhatTheListViewsCost(): void
    {
        $lines = ["# What the CRM's list views cost", '', 'A page of 25 and its count, in milliseconds: the median of five.', ''];
        foreach (['admin', 'member'] as $user) {
            self::signIn($user);
            $lines[] = "## As {$user}";
            $lines[] = '';
            $lines[] = '| request | ' . implode(' | ', array_keys(self::ENGINES)) . ' |';
            $lines[] = '|---|' . str_repeat('---:|', count(self::ENGINES));
            foreach (self::REQUESTS as [$model, $filter, $include, $ordering]) {
                $times = [];
                $answers = [];
                foreach (self::ENGINES as $name => [$candidate, $limit]) {
                    Engine::$candidate = $candidate;
                    Engine::$keyListLimit = $limit;
                    $request = static fn () => [
                        Ask::rows(self::modelClass($model), $filter, $ordering ?? 'id:desc', $include, 25, 0),
                        $include === null ? Ask::count(self::modelClass($model), $filter) : null,
                    ];
                    $answers[$name] = $request();
                    $samples = [];
                    for ($i = 0; $i < 5; $i++) {
                        $start = hrtime(true);
                        $request();
                        $samples[] = (hrtime(true) - $start) / 1e6;
                    }
                    sort($samples);
                    $times[] = number_format($samples[2], 1, '.', ' ');
                }
                // The three rules engines must agree with each other whatever they cost
                $this->assertSame(json_encode($answers['rules']), json_encode($answers['rules, sub query']), "{$user} {$model} {$filter}");
                $this->assertSame(json_encode($answers['rules']), json_encode($answers['rules, 10 000']), "{$user} {$model} {$filter}");
                if ($user === 'admin') {
                    $this->assertSame(json_encode($answers['reference']), json_encode($answers['rules']), "admin {$model} {$filter}");
                }
                $request = "{$model}" . ($filter !== '' ? " filter={$filter}" : '') . ($include ? " include={$include}" : '') . ($ordering ? " ordering={$ordering}" : '');
                $lines[] = '| `' . str_replace('|', '\|', $request) . '` | ' . implode(' | ', $times) . ' |';
            }
            $lines[] = '';
        }
        @mkdir(dirname(__DIR__, 2) . '/build');
        file_put_contents(dirname(__DIR__, 2) . '/build/benchmark.md', implode("\n", $lines) . "\n");
        fwrite(STDERR, implode("\n", $lines) . "\n");
    }
}
