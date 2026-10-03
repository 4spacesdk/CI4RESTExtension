<?php

namespace Tests\Support\Api\Second\Controllers;

use CodeIgniter\Controller;

/**
 * An endpoint for the export test.
 */
class Gadgets extends Controller
{
    /**
     * @route /other-gadgets
     * @method get
     * @custom true
     * @responseSchema GizmoItem
     */
    public function list(): void
    {
    }
}
