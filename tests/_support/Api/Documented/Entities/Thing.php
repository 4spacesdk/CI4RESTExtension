<?php

namespace Tests\Support\Api\Documented\Entities;

use RestExtension\Core\Entity;

/**
 * @property string $name what it is called
 * @property int $workspace_id
 * @property Workspace $workspace
 *
 * Many
 * @property Part $parts
 */
class Thing extends Entity
{
}
