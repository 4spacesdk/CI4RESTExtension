<?php
/** @var \RestExtension\ApiParser\ApiItem $path */
/** @var array $endpoints */
?>

    class <?=$path->name?> extends BaseApi {
<?php foreach($endpoints as $className => $content) { ?>
        public static <?=lcfirst($className)?>(): <?=$className?> {
            return new <?=$className?>();
        }
<?php } ?>
    }
