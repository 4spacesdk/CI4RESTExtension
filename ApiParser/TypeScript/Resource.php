<?php
/** @var \RestExtension\ApiParser\ApiItem $path */
/** @var array $endpoints */
?>
<?php foreach($path->imports as $import) { ?>
import {<?=$import?>} from '@app/core/models';
<? } ?>
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
