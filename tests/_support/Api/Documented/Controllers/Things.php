<?php

namespace Tests\Support\Api\Documented\Controllers;

use Tests\Support\Api\First\ResourceController;

/**
 * A resource controller, for the OpenAPI test.
 *
 * @resource Thing
 */
class Things extends ResourceController
{
    /**
     * Overridden to keep the caller from it - what a developer says, not what a caller reads.
     *
     * @summary Things of the caller
     * @param $id
     */
    public function get($id = 0): void
    {
    }
}
