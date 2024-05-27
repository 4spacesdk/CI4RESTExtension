<?php
/** @var \RestExtension\ApiParser\ApiItem[] $resources */
/** @var string[] $imports */
/** @var \RestExtension\ApiParser\InterfaceItem[] $interfaces */
?>
using System;
using <?=config('RestExtension')->xamarinBaseAPINamespace?>;
using <?=config('OrmExtension')->xamarinModelsNamespace?>;
using <?=config('OrmExtension')->xamarinBaseModelNamespace?>;
using Newtonsoft.Json;
using static <?=config('RestExtension')->xamarinAPINamespace?>.<?=config('RestExtension')->xamarinAPIClassName?>;

namespace <?=config('RestExtension')->xamarinAPINamespace?>

{
    public static class <?=config('RestExtension')->xamarinAPIClassName?>
    {
<?php foreach($interfaces as $interface) { ?>

<?=$interface->generateXamarin()?>
<?php } ?>

<?php foreach($resources as $resource) { ?>

        public static class <?=$resource->name?>

        {
<?php foreach($resource->endpoints as $endpoint) { ?>

            public static <?=$endpoint->getTypeScriptClassName()?> <?=ucfirst($endpoint->getTypeScriptFunctionName())?>(<?=implode(', ', $endpoint->getXamarinPathArgumentsWithTypes())?>)
            {
                return new <?=$endpoint->getTypeScriptClassName()?>(<?=implode(', ', $endpoint->getTypeScriptPathArgumentsWithOutTypes())?>);
            }
<?php } ?>
        }
<?php } ?>
    }
<?php foreach($resources as $resource) { ?>

<?=$resource->generateXamarin()?>
<?php } ?>

}
