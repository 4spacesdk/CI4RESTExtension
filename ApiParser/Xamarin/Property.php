<?php
/** @var \RestExtension\ApiParser\PropertyItem $property */
?>
            [JsonProperty("<?=$property->name?>")]
            public <?=$property->getXamarinType()?><?=$property->isMany?"[]":""?> <?=ucfirst($property->getCamelName())?> { get; set; }
