<?php

namespace Tests\Support;

/**
 * No rules: every row may be read. The differential tests compare what the engines do with the
 * same rows, not what rules leave out.
 */
trait RulelessModel
{
    public function preRestGet($queryParser, $id)
    {
    }

    public function postRestGet($queryParser, $items)
    {
    }

    public function isRestCreationAllowed($item): bool
    {
        return true;
    }

    public function isRestUpdateAllowed($item): bool
    {
        return true;
    }

    public function isRestDeleteAllowed($item): bool
    {
        return true;
    }

    public function appleRestGetManyRelations($items)
    {
    }
}
