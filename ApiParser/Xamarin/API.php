<?php
/** @var \RestExtension\ApiParser\ApiItem[] $resources */
/** @var string[] $imports */
/** @var \RestExtension\ApiParser\InterfaceItem[] $interfaces */
?>
using System;
using <?=\CodeIgniter\Config\Config::get('RestExtension')->xamarinBaseAPINamespace?>;
using <?=\CodeIgniter\Config\Config::get('OrmExtension')->xamarinModelsNamespace?>;
using <?=\CodeIgniter\Config\Config::get('OrmExtension')->xamarinBaseModelNamespace?>;
using Newtonsoft.Json;
using static <?=\CodeIgniter\Config\Config::get('RestExtension')->xamarinAPINamespace?>.Api;

namespace <?=\CodeIgniter\Config\Config::get('RestExtension')->xamarinAPINamespace?>

{
    public static class Api
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
