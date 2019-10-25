<?php
/** @var \RestExtension\ApiParser\ApiItem $path */
/** @var \RestExtension\ApiParser\EndpointItem[] $endpoints */
?>
<?php foreach($endpoints as $endpoint) { ?>

<?=$endpoint->generateXamarin();?>
<?php } ?>
