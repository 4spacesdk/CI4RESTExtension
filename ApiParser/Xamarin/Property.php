<?php
/** @var \RestExtension\ApiParser\PropertyItem $property */
?>
            [JsonProperty("<?=$property->name?>")]
            public <?=$property->typeScriptType?><?=$property->isMany?"[]":""?> <?=ucfirst($property->getCamelName())?> { get; set; }
