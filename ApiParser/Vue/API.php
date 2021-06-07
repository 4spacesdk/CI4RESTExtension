<?php
/** @var \RestExtension\ApiParser\ApiItem[] $resources */
/** @var string[] $imports */
/** @var \RestExtension\ApiParser\InterfaceItem[] $interfaces */
?>
import {BaseApi} from "./BaseApi";
<?php foreach($imports as $import) { ?>
import {<?=$import?>} from "./models";
<?php } ?>
<?php foreach($interfaces as $interface) { ?>

<?=$interface->toVue()?>
<?php } ?>
<?php foreach($resources as $resource) { ?>

<?=$resource->generateVue()?>
<?php } ?>

export class Api {

<?php foreach($resources as $resource) { ?>
    public static <?=lcfirst($resource->name)?>(): <?=$resource->name?> {
        return new <?=$resource->name?>();
    }

<?php } ?>
}
