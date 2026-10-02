<?php

namespace Tests\Support;

/**
 * The model follows its relations' rules when the test says so (Engine), and fetches as many
 * keys into a list as Engine allows.
 */
trait FollowsEngine
{
    public function relationsFollowRules(): bool
    {
        return Engine::$candidate;
    }

    public function relationKeyListLimit(): int
    {
        return Engine::$keyListLimit;
    }
}
