<?php

namespace Tests\Support\Entities;

use RestExtension\Core\Entity;

/**
 * OTF
 * @property int $count_lines
 */
class Order extends Entity
{
    public function toArray(bool $onlyChanged = false, bool $cast = true, bool $recursive = false, ?array $fieldsFilter = null): array
    {
        $item = parent::toArray($onlyChanged, $cast, $recursive, $fieldsFilter);
        // As TP's entities do with a computed field
        if (isset($this->count_lines)) {
            $item['count_lines'] = (int) $this->count_lines;
        }

        return $item;
    }
}
