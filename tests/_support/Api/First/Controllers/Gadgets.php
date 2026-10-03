<?php

namespace Tests\Support\Api\First\Controllers;

use CodeIgniter\Controller;

/**
 * An endpoint for the export test.
 */
class Gadgets extends Controller
{
    /**
     * @route /gadgets
     * @method get
     * @custom true
     * @responseSchema WidgetItem
     */
    public function list(): void
    {
    }
}
