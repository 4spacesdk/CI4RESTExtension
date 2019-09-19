<?php
/** @var \RestExtension\ApiParser\ApiItem $apiItem */
?>
<?php foreach($apiItem->endpoints as $endpoint) { ?>
<?if($endpoint->isRestPatchEndpoint) { ?>

    public patch(fields: string[] = [], callback?: () => void) {
        let data: any = this;
        if (fields.length > 0) {
            data = [];
            fields.forEach(field => data[field] = this[field]);
        }
        Api.<?=lcfirst($apiItem->name)?>().<?=$endpoint->getTypeScriptFunctionName()?>(this.id).save(data, value => {
            this.populate(value, true);
            if (callback) {
               callback();
            }
        });
    }
<? } ?>
<? } ?>
