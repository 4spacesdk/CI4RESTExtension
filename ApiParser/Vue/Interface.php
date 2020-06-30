<?php
/** @var \RestExtension\ApiParser\InterfaceItem $interfaceItem */
?>
export interface <?=$interfaceItem->name?> {
<?php foreach($interfaceItem->properties as $property) : ?>
    <?=$property->toVue()?>
<?php endforeach ?>
}
