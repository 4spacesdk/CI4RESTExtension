<?php
/** @var \RestExtension\ApiParser\ApiItem $path */
/** @var array $endpoints */
?>
import {<?=$path->resourceNameUpperCase?>} from '@app/core/models';
class <?=$path->name?> {

<?php foreach($endpoints as [$funcName, $className, $argsWithType, $argsWithOutType, $content]) { ?>
    public <?=lcfirst($funcName)?>(<?=$argsWithType?>): <?=$className?> {
        return new <?=$className?>(<?=$argsWithOutType?>);
    }

<?php } ?>
}
<?php foreach($endpoints as [$funcName, $className, $argsWithType, $argsWithOutType, $content]) { ?>

<?=$content?>
<?php } ?>
