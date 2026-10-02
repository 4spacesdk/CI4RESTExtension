<?php

namespace Tests\Support\Crm;

/**
 * Who is asking the CRM: an admin sees everything, a rep what their teams give them - the teams
 * they are a member of and every team under those.
 */
final class CrmClient
{
    public static bool $admin = true;

    public static int $repId = 0;

    /** @var list<int>|null */
    private static ?array $teams = null;

    public static function signInAs(int $repId): void
    {
        self::$admin = false;
        self::$repId = $repId;
        self::$teams = null;
    }

    public static function admin(): void
    {
        self::$admin = true;
        self::$repId = 0;
        self::$teams = null;
    }

    /**
     * @return list<int>
     */
    public static function teamIds(): array
    {
        if (self::$teams === null) {
            self::$teams = CrmWorld::teamsOf(self::$repId);
        }

        return self::$teams === [] ? [0] : self::$teams;
    }
}
