<?php

namespace Tests\Support;

/**
 * Which engine the test models run: RestExtension with relationsFollowRules() off (the
 * reference) or on (the candidate), and how many keys the candidate fetches into a list before
 * it uses a sub query. RESTEXTENSION_KEY_LIST_LIMIT runs the suite with another limit: 0 is a
 * sub query every time.
 */
final class Engine
{
    public static bool $candidate = false;

    public static int $keyListLimit = 1000;

    public static function reset(): void
    {
        self::$candidate = false;
        $limit = getenv('RESTEXTENSION_KEY_LIST_LIMIT');
        self::$keyListLimit = $limit === false || $limit === '' ? 1000 : (int) $limit;
    }
}
