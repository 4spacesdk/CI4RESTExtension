<?php
/** @var \RestExtension\ApiParser\PropertyItem $property */
?>
<?=$property->name?>?: <?=$property->typeScriptType?><?=$property->isMany?"[]":""?>;
