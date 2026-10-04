<?php

namespace Tests\Support;

/**
 * Who is asking, as TP's Client says it: an admin sees everything, anyone else what their
 * workspaces give them. The models' preRestGet() read it the way TP's do.
 */
final class TestClient
{
    public static bool $admin = true;

    public static int $userId = 0;

    /** What isRestCreationAllowed() and friends say; the read rules are preRestGet()'s */
    public static bool $mayWrite = true;

    public static function signInAs(int $userId): void
    {
        self::$admin = false;
        self::$userId = $userId;
    }

    public static function admin(): void
    {
        self::$admin = true;
        self::$userId = 0;
        self::$mayWrite = true;
    }

    /**
     * The workspaces the caller is an approved member of.
     *
     * @return list<int>
     */
    public static function workspaceIds(): array
    {
        $rows = db_connect('tests')->table('users_workspaces')->select('workspace_id')
            ->where('user_id', self::$userId)->where('status', 'approved')->where('deletion_id', null)
            ->get()->getResultArray();
        $ids = array_values(array_unique(array_map(static fn (array $row): int => (int) $row['workspace_id'], $rows)));

        return $ids === [] ? [0] : $ids;
    }
}
