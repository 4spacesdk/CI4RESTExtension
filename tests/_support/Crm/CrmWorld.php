<?php

namespace Tests\Support\Crm;

use CodeIgniter\Database\BaseConnection;

/**
 * A CRM of a realistic size, made up the same way every time: 40 teams in three levels, 1 500
 * reps, 20 000 companies, 100 000 contacts, 60 000 deals with 150 000 participants, 400 000
 * activities and 50 000 tags on companies.
 *
 * The keys are skewed as real ones are - a few big companies hold most contacts and deals -
 * and the data is as untidy: missing values, keys to rows that are not there, soft deleted
 * rows, duplicates in the pivots, and a country named by its code.
 */
final class CrmWorld
{
    private const VERSION = 'crm-1';

    public const TABLES = ['activities', 'deal_participants', 'deals', 'contacts', 'companies_tags', 'tags', 'companies', 'countries', 'industries', 'team_members', 'reps', 'teams', 'crm_world', 'seq'];

    public static function db(): BaseConnection
    {
        return db_connect('tests');
    }

    public static function load(): void
    {
        $db = self::db();
        $tables = $db->listTables();
        if (in_array('crm_world', $tables, true) && ($db->query('SELECT version FROM crm_world')->getRowArray()['version'] ?? null) === self::VERSION) {
            return;
        }
        foreach (self::TABLES as $table) {
            $db->query("DROP TABLE IF EXISTS {$table}");
        }

        $db->query('CREATE TABLE seq (n INT PRIMARY KEY)');
        $db->query('INSERT INTO seq (n) SELECT 1 + a.d + 10 * b.d + 100 * c.d + 1000 * e.d + 10000 * f.d + 100000 * g.d
            FROM (SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) a,
            (SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) b,
            (SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) c,
            (SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) e,
            (SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) f,
            (SELECT 0 d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3) g');

        $db->query('CREATE TABLE teams (id INT PRIMARY KEY, parent_id INT NULL, lead_rep_id INT NULL, name VARCHAR(63) NULL, deletion_id INT NULL, KEY parent_id (parent_id), KEY lead_rep_id (lead_rep_id))');
        $db->query('CREATE TABLE reps (id INT PRIMARY KEY, home_team_id INT NULL, email VARCHAR(127) NULL, name VARCHAR(63) NULL, deletion_id INT NULL, KEY home_team_id (home_team_id), KEY email (email))');
        $db->query('CREATE TABLE team_members (id INT AUTO_INCREMENT PRIMARY KEY, rep_id INT NULL, team_id INT NULL, role VARCHAR(15) NOT NULL, deletion_id INT NULL, KEY rep_id (rep_id), KEY team_id (team_id))');
        $db->query('CREATE TABLE industries (id INT PRIMARY KEY, name VARCHAR(63) NULL)');
        $db->query('CREATE TABLE countries (id INT PRIMARY KEY, code CHAR(2) NOT NULL, name VARCHAR(63) NULL, deletion_id INT NULL, UNIQUE KEY code (code))');
        $db->query('CREATE TABLE companies (id INT PRIMARY KEY, owner_team_id INT NULL, parent_id INT NULL, industry_id INT NULL, country_code CHAR(2) NULL, is_shared TINYINT(1) NOT NULL DEFAULT 0, name VARCHAR(127) NULL, revenue INT NULL, deletion_id INT NULL,
            KEY owner_team_id (owner_team_id), KEY parent_id (parent_id), KEY industry_id (industry_id), KEY country_code (country_code), KEY name (name))');
        $db->query('CREATE TABLE tags (id INT PRIMARY KEY, name VARCHAR(63) NULL)');
        $db->query('CREATE TABLE companies_tags (id INT AUTO_INCREMENT PRIMARY KEY, company_id INT NULL, tag_id INT NULL, KEY company_id (company_id), KEY tag_id (tag_id))');
        $db->query('CREATE TABLE contacts (id INT PRIMARY KEY, company_id INT NULL, owner_rep_id INT NULL, email VARCHAR(127) NULL, first_name VARCHAR(63) NULL, last_name VARCHAR(63) NULL, deletion_id INT NULL,
            KEY company_id (company_id), KEY owner_rep_id (owner_rep_id), KEY email (email), KEY last_name (last_name))');
        $db->query('CREATE TABLE deals (id INT PRIMARY KEY, company_id INT NULL, owner_rep_id INT NULL, primary_contact_id INT NULL, title VARCHAR(127) NULL, stage VARCHAR(15) NOT NULL, amount INT NULL, is_private TINYINT(1) NOT NULL DEFAULT 0, deletion_id INT NULL,
            KEY company_id (company_id), KEY owner_rep_id (owner_rep_id), KEY primary_contact_id (primary_contact_id))');
        // As products_target_workspaces: no unique key, duplicates happen
        $db->query('CREATE TABLE deal_participants (id INT AUTO_INCREMENT PRIMARY KEY, deal_id INT NULL, contact_id INT NULL, KEY deal_id (deal_id), KEY contact_id (contact_id))');
        $db->query('CREATE TABLE activities (id INT PRIMARY KEY, deal_id INT NULL, contact_id INT NULL, author_rep_id INT NULL, kind VARCHAR(15) NOT NULL, subject VARCHAR(127) NULL, created DATE NOT NULL,
            KEY deal_id (deal_id), KEY contact_id (contact_id), KEY author_rep_id (author_rep_id))');

        $r = static fn (string $salt): string => "(CRC32(CONCAT('{$salt}', n)) / 4294967296)";

        // Three levels: 1-4, under them 5-12, under those 13-40. 38 is deleted, 40 has no name.
        $db->query("INSERT INTO teams (id, parent_id, lead_rep_id, name, deletion_id) SELECT n,
            CASE WHEN n <= 4 THEN NULL WHEN n <= 12 THEN 1 + MOD(n - 5, 4) ELSE 5 + MOD(n - 13, 8) END,
            CASE WHEN {$r('tl')} < 0.1 THEN NULL WHEN {$r('tl')} < 0.12 THEN 99999 ELSE 1 + FLOOR({$r('tl2')} * 1500) END,
            IF(n = 40, NULL, CONCAT('Team ', n)), IF(n = 38, 1, NULL)
            FROM seq WHERE n <= 40");
        $db->query("INSERT INTO reps (id, home_team_id, email, name, deletion_id) SELECT n,
            CASE WHEN {$r('ht')} < 0.03 THEN NULL WHEN {$r('ht')} < 0.04 THEN 999 ELSE 1 + FLOOR({$r('ht2')} * 40) END,
            IF({$r('re')} < 0.02, NULL, CONCAT('rep', n, '@crm.test')), CONCAT('Rep ', n), IF({$r('rd')} < 0.02, 1, NULL)
            FROM seq WHERE n <= 1500");
        // Most reps are in one team, some in two, some in none; a few memberships are deleted or
        // name a team or a rep that is not there
        $db->query("INSERT INTO team_members (rep_id, team_id, role, deletion_id) SELECT n, 5 + FLOOR({$r('tm')} * 36), IF({$r('tr')} < 0.1, 'lead', 'member'), IF({$r('tx')} < 0.05, 1, NULL)
            FROM seq WHERE n <= 1500 AND {$r('tn')} >= 0.08");
        $db->query("INSERT INTO team_members (rep_id, team_id, role, deletion_id) SELECT n, 1 + FLOOR({$r('tm3')} * 40), 'member', NULL
            FROM seq WHERE n <= 1500 AND {$r('tn2')} < 0.25");
        $db->query("INSERT INTO team_members (rep_id, team_id, role, deletion_id) VALUES (3, 999, 'member', NULL), (NULL, 5, 'member', NULL), (99999, 6, 'lead', NULL)");

        $db->query("INSERT INTO industries (id, name) SELECT n, IF(n = 40, NULL, CONCAT('Industry ', n)) FROM seq WHERE n <= 40");
        $db->query("INSERT INTO countries (id, code, name, deletion_id) VALUES (1, 'DK', 'Denmark', NULL), (2, 'SE', 'Sweden', NULL), (3, 'NO', 'Norway', NULL), (4, 'DE', 'Germany', NULL),
            (5, 'US', 'United States', NULL), (6, 'GB', 'United Kingdom', 1), (7, 'FI', NULL, NULL)");

        $words1 = "'Nordic','Blue','Green','North','Smart','Fast','Prime','Royal','Urban','Bright','Solid','Open'";
        $words2 = "'Logistics','Foods','Systems','Energy','Media','Health','Retail','Labs','Partners','Group'";
        $db->query("INSERT INTO companies (id, owner_team_id, parent_id, industry_id, country_code, is_shared, name, revenue, deletion_id) SELECT n,
            CASE WHEN {$r('ot')} < 0.01 THEN NULL WHEN {$r('ot')} < 0.015 THEN 999 ELSE 1 + FLOOR(POW({$r('ot2')}, 2) * 40) END,
            CASE WHEN n > 100 AND {$r('cp')} < 0.1 THEN 1 + FLOOR({$r('cp2')} * (n - 1)) WHEN {$r('cp')} < 0.102 THEN 999999 ELSE NULL END,
            IF({$r('ci')} < 0.05, NULL, 1 + FLOOR({$r('ci2')} * 40)),
            IF({$r('cc')} < 0.03, NULL, ELT(1 + FLOOR({$r('cc2')} * 10), 'DK', 'DK', 'DK', 'SE', 'NO', 'DE', 'US', 'GB', 'FI', 'XX')),
            {$r('cs')} < 0.05,
            IF({$r('cn')} < 0.005, NULL, CONCAT(ELT(1 + FLOOR({$r('w1')} * 12), {$words1}), ' ', ELT(1 + FLOOR({$r('w2')} * 10), {$words2}), ' ', n)),
            IF({$r('cr')} < 0.2, NULL, FLOOR({$r('cr2')} * 10000000)),
            IF({$r('cd')} < 0.02, 1, NULL)
            FROM seq WHERE n <= 20000");
        $db->query("INSERT INTO tags (id, name) SELECT n, IF(n = 300, NULL, CONCAT('Tag ', n)) FROM seq WHERE n <= 300");
        $db->query("INSERT INTO companies_tags (company_id, tag_id) SELECT 1 + FLOOR({$r('ct')} * 20000), IF({$r('ct2')} < 0.005, 9999, 1 + FLOOR(POW({$r('ct3')}, 2) * 300)) FROM seq WHERE n <= 50000");

        $first = "'Anna','Bo','Carl','Dorte','Erik','Frida','Gustav','Hanne','Ida','Jens','Karen','Lars'";
        $last = "'Hansen','Jensen','Nielsen','Pedersen','Andersen','Larsen','Svensson','Berg','Olsen','Holm'";
        $db->query("INSERT INTO contacts (id, company_id, owner_rep_id, email, first_name, last_name, deletion_id) SELECT n,
            CASE WHEN {$r('kc')} < 0.01 THEN NULL WHEN {$r('kc')} < 0.015 THEN 999999 ELSE 1 + FLOOR(POW({$r('kc2')}, 1.6) * 20000) END,
            IF({$r('ko')} < 0.1, NULL, 1 + FLOOR({$r('ko2')} * 1500)),
            IF({$r('ke')} < 0.05, NULL, CONCAT(LOWER(ELT(1 + FLOOR({$r('kf')} * 12), {$first})), '.', n, '@', ELT(1 + FLOOR({$r('kd')} * 3), 'mail.dk', 'post.se', 'firma.com'))),
            ELT(1 + FLOOR({$r('kf')} * 12), {$first}),
            IF({$r('kl')} < 0.02, NULL, ELT(1 + FLOOR({$r('kl2')} * 10), {$last})),
            IF({$r('kx')} < 0.02, 1, NULL)
            FROM seq WHERE n <= 100000");
        $db->query("INSERT INTO deals (id, company_id, owner_rep_id, primary_contact_id, title, stage, amount, is_private, deletion_id) SELECT n,
            IF({$r('dc')} < 0.005, NULL, 1 + FLOOR(POW({$r('dc2')}, 1.6) * 20000)),
            IF({$r('do')} < 0.03, NULL, 1 + FLOOR({$r('do2')} * 1500)),
            CASE WHEN {$r('dp')} < 0.1 THEN NULL WHEN {$r('dp')} < 0.11 THEN 9999999 ELSE 1 + FLOOR({$r('dp2')} * 100000) END,
            CONCAT('Deal ', n), ELT(1 + FLOOR({$r('ds')} * 5), 'lead', 'qualified', 'won', 'lost', 'draft'),
            IF({$r('da')} < 0.15, NULL, FLOOR({$r('da2')} * 1000000)),
            {$r('di')} < 0.1, IF({$r('dx')} < 0.01, 1, NULL)
            FROM seq WHERE n <= 60000");
        $db->query("INSERT INTO deal_participants (deal_id, contact_id) SELECT
            IF({$r('pd')} < 0.003, NULL, 1 + FLOOR({$r('pd2')} * 60000)),
            CASE WHEN {$r('pc')} < 0.003 THEN NULL WHEN {$r('pc')} < 0.008 THEN 9999999 ELSE 1 + FLOOR({$r('pc2')} * 100000) END
            FROM seq WHERE n <= 150000");
        $db->query("INSERT INTO activities (id, deal_id, contact_id, author_rep_id, kind, subject, created) SELECT n,
            IF({$r('ad')} < 0.4, NULL, 1 + FLOOR({$r('ad2')} * 60000)),
            IF({$r('ac')} < 0.4, NULL, 1 + FLOOR({$r('ac2')} * 100000)),
            IF({$r('aa')} < 0.02, NULL, 1 + FLOOR({$r('aa2')} * 1500)),
            ELT(1 + FLOOR({$r('ak')} * 4), 'call', 'mail', 'meeting', 'note'),
            IF({$r('as')} < 0.1, NULL, CONCAT('Subject ', n)),
            DATE_ADD('2024-01-01', INTERVAL FLOOR({$r('at')} * 700) DAY)
            FROM seq WHERE n <= 400000");

        foreach (self::TABLES as $table) {
            if ($table !== 'crm_world') {
                $db->query("ANALYZE TABLE {$table}");
            }
        }
        $db->query('CREATE TABLE crm_world (version VARCHAR(15) NOT NULL)');
        $db->query('INSERT INTO crm_world (version) VALUES (?)', [self::VERSION]);
    }

    /**
     * The teams a rep is a member of, and every team under those, leaving out deleted ones.
     *
     * @return list<int>
     */
    public static function teamsOf(int $repId): array
    {
        $db = self::db();
        $parents = [];
        foreach ($db->query('SELECT id, parent_id FROM teams WHERE deletion_id IS NULL')->getResultArray() as $row) {
            $parents[(int) $row['id']] = $row['parent_id'] === null ? null : (int) $row['parent_id'];
        }
        $teams = [];
        $rows = $db->query('SELECT team_id FROM team_members WHERE rep_id = ? AND deletion_id IS NULL', [$repId])->getResultArray();
        foreach ($rows as $row) {
            if (array_key_exists((int) $row['team_id'], $parents)) {
                $teams[(int) $row['team_id']] = true;
            }
        }
        do {
            $added = false;
            foreach ($parents as $id => $parent) {
                if ($parent !== null && isset($teams[$parent]) && ! isset($teams[$id])) {
                    $teams[$id] = true;
                    $added = true;
                }
            }
        } while ($added);
        $ids = array_keys($teams);
        sort($ids);

        return $ids;
    }

    /**
     * Three reps who see the CRM differently: one in a top team, who sees a quarter of it; one in
     * a single team at the bottom; and one in no team, who sees their own and what is shared.
     *
     * @return array<string, int>
     */
    public static function reps(): array
    {
        $db = self::db();
        $one = static fn (string $sql): int => (int) ($db->query($sql)->getRowArray()['id'] ?? 0);

        return [
            'manager' => $one("SELECT r.id FROM reps r JOIN team_members m ON m.rep_id = r.id AND m.deletion_id IS NULL WHERE r.deletion_id IS NULL AND m.team_id = 1 ORDER BY r.id LIMIT 1"),
            'member' => $one("SELECT r.id FROM reps r JOIN team_members m ON m.rep_id = r.id AND m.deletion_id IS NULL WHERE r.deletion_id IS NULL AND m.team_id = 27
                AND r.id NOT IN (SELECT rep_id FROM team_members WHERE team_id <> 27 AND rep_id IS NOT NULL) ORDER BY r.id LIMIT 1"),
            'loner' => $one("SELECT r.id FROM reps r WHERE r.deletion_id IS NULL AND r.id NOT IN (SELECT rep_id FROM team_members WHERE rep_id IS NOT NULL) ORDER BY r.id LIMIT 1"),
        ];
    }
}
