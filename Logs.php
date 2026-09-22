<?php namespace RestExtension;

use Config\Database;

/**
 * Housekeeping for the three log tables, which nothing else ever removes a row from: every
 * request writes one to `api_access_logs`, and every exception one to `api_error_logs`.
 *
 * The extension does not schedule this - the application does, where its cron is. Deleted in
 * portions, so the first run on a table that has grown for years does not hold a lock on it for
 * the length of one enormous delete.
 */
class Logs {

    public const Tables = ['api_access_logs', 'api_error_logs', 'api_blocked_logs'];

    private const Portion = 10000;

    /**
     * @return array<string, int> how many rows went from each table
     */
    public static function PruneOlderThan(int $days): array {
        // No group named: the connection's default, which is the test database under test.
        // Naming 'default' reached the development database from a test run.
        $db = Database::connect(config('RestExtension')->databaseGroupName ?? null);
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $removed = [];
        foreach (self::Tables as $table) {
            $removed[$table] = 0;
            if (!$db->tableExists($table)) {
                continue;
            }
            do {
                $db->table($table)->where('date <', $cutoff)->limit(self::Portion)->delete();
                $affected = $db->affectedRows();
                $removed[$table] += $affected;
            } while ($affected === self::Portion);
        }

        return $removed;
    }

}
