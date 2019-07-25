<?php
/** @var array $resources */
?>
import {BaseApi} from '@app/core/http/Api/BaseApi';
import { Observable, Subscription } from 'rxjs';

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
