<?php
/** @var \RestExtension\ApiParser\ApiItem $path */
/** @var \RestExtension\ApiParser\EndpointItem[] $endpoints */
?>
class <?=$path->name?> {

<?php foreach($endpoints as $endpoint) { ?>
    public <?=lcfirst($endpoint->getTypeScriptFunctionName())?>(<?=implode(', ', $endpoint->getTypeScriptPathArgumentsWithTypes())?>): <?=$endpoint->getTypeScriptClassName()?> {
        return new <?=$endpoint->getTypeScriptClassName()?>(<?=implode(', ', $endpoint->getTypeScriptPathArgumentsWithOutTypes())?>);
    }

<?php } ?>
}
<?php foreach($endpoints as $endpoint) { ?>

<?=$endpoint->generateTypeScript();?>
<?php } ?>
