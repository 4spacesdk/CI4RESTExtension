<?php
/** @var array $resources */
?>

export class Api extends BaseApi {

    <?php foreach($resources as $resource) { ?>

        <?=$resource?>

    <?php } ?>

}
