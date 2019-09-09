<?php
/** @var array $resources */
/** @var \RestExtension\ApiParser\InterfaceItem[] $interfaces */
?>
import {BaseApi} from '@app/core/http/Api/BaseApi';
import { Observable, Subscription } from 'rxjs';
<?php foreach($interfaces as $interface) { ?>

<?=$interface->toTypeScript()?>
<? } ?>

export class Api {

<?php foreach($resources as $pathName => $resource) { ?>
    public static <?=lcfirst($pathName)?>(): <?=$pathName?> {
        return new <?=$pathName?>();
    }

<?php } ?>
}
<?php foreach($resources as $resource) { ?>

<?=$resource?>
<?php } ?>
