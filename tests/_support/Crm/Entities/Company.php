<?php

namespace Tests\Support\Crm\Entities;

use RestExtension\Core\Entity;

/**
 * OTF
 * @property int $count_open_deals
 */
class Company extends Entity
{
    public function toArray(bool $onlyChanged = false, bool $cast = true, bool $recursive = false, ?array $fieldsFilter = null): array
    {
        $item = parent::toArray($onlyChanged, $cast, $recursive, $fieldsFilter);
        if (isset($this->count_open_deals)) {
            $item['count_open_deals'] = (int) $this->count_open_deals;
        }

        return $item;
    }
}
