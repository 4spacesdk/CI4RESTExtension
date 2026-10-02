<?php

namespace Tests\Support\Entities;

use RestExtension\Core\Entity;

/**
 * OTF
 * @property int $count_users
 */
class Workspace extends Entity
{
    public function toArray(bool $onlyChanged = false, bool $cast = true, bool $recursive = false, ?array $fieldsFilter = null): array
    {
        $item = parent::toArray($onlyChanged, $cast, $recursive, $fieldsFilter);
        // As TP's entities do with a computed field
        if (isset($this->count_users)) {
            $item['count_users'] = (int) $this->count_users;
        }

        return $item;
    }
}
