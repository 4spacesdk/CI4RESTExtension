<?php

namespace Tests\Support;

/**
 * No rules: every row may be read, and written unless the test says otherwise (TestClient). The differential tests compare what the engines do with the
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
        return TestClient::$mayWrite;
    }

    public function isRestUpdateAllowed($item): bool
    {
        return TestClient::$mayWrite;
    }

    public function isRestDeleteAllowed($item): bool
    {
        return TestClient::$mayWrite;
    }

    public function appleRestGetManyRelations($items)
    {
    }
}
