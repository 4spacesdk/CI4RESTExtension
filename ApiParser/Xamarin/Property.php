<?php
/** @var \RestExtension\ApiParser\PropertyItem $property */
?>
            [JsonProperty("<?=$property->name?>")]
            public <?=$property->xamarinType?><?=$property->isMany?"[]":""?> <?=ucfirst($property->getCamelName())?> { get; set; }
