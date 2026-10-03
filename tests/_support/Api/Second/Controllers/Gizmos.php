<?php

namespace Tests\Support\Api\Second\Controllers;

use CodeIgniter\Controller;

/**
 * An endpoint for the export test.
 */
class Gizmos extends Controller
{
    /**
     * @route /gizmos
     * @method get
     * @custom true
     * @responseSchema GizmoItem
     */
    public function list(): void
    {
    }
}
