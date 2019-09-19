<?php
/** @var \RestExtension\ApiParser\ApiItem[] $resources */
/** @var string[] $imports */
/** @var \RestExtension\ApiParser\InterfaceItem[] $interfaces */
?>
import {BaseApi} from '@app/core/http/Api/BaseApi';
import { Observable, Subscription } from 'rxjs';
<?php foreach($imports as $import) { ?>
import {<?=$import?>} from '@app/core/models';
<? } ?>
<?php foreach($interfaces as $interface) { ?>

<?=$interface->toTypeScript()?>
<? } ?>

export class Api {

<?php foreach($resources as $resource) { ?>
    public static <?=lcfirst($resource->name)?>(): <?=$resource->name?> {
        return new <?=$resource->name?>();
    }

<?php } ?>
}
<?php foreach($resources as $resource) { ?>

<?=$resource->generateTypeScript()?>
<?php } ?>
