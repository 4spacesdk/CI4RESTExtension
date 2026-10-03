<?php

namespace Tests\Support\Api\First\Controllers\Admin;

use CodeIgniter\Controller;

/**
 * An endpoint for the export test.
 */
class Tools extends Controller
{
    /**
     * @route /admin/tools
     * @method get
     * @custom true
     * @responseSchema WidgetItem
     */
    public function list(): void
    {
    }
}
