<?php
/** @var \RestExtension\ApiParser\PropertyItem $property */
?>
<?=$property->name?>?: <?=$property->type?><?=$property->isMany?"[]":""?>;
