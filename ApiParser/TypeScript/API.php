<?php
/** @var \RestExtension\ApiParser\ApiItem[] $resources */
/** @var string[] $imports */
/** @var \RestExtension\ApiParser\InterfaceItem[] $interfaces */
/** @var string $baseApiImportPath */
/** @var string $modelsImportPath */
?>
import {BaseApi} from '<?=$baseApiImportPath?>';
import { Observable, Subscription as RXJSSubscription } from 'rxjs';
<?php foreach($imports as $import) { ?>
import {<?=$import?>} from '<?=$modelsImportPath?>';
<?php } ?>
<?php foreach($interfaces as $interface) { ?>

<?=$interface->toTypeScript()?>
<?php } ?>

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
