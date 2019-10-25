<?php
/** @var \RestExtension\ApiParser\InterfaceItem $interfaceItem */
?>
        public class <?=$interfaceItem->name?> : BaseModel
        {
<?php foreach($interfaceItem->properties as $property) : ?>
<?=$property->generateXamarin()?>
<?php endforeach ?>
        }
