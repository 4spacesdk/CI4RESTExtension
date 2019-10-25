<?php
/** @var \RestExtension\ApiParser\EndpointItem $endpoint */
?>
    public class <?=$endpoint->getTypeScriptClassName()?> : BaseApi<<?=$endpoint->responseSchema ?? 'Empty'?>>
    {
        public <?=$endpoint->getTypeScriptClassName()?>(<?=implode(', ', $endpoint->getXamarinPathArgumentsWithTypes())?>)
        {
<?php if(count($endpoint->getTypeScriptPathArgumentsWithOutTypes())) { ?>
            Url = String.Format("<?=$endpoint->getXamarinUrl()?>", <?=implode(', ', $endpoint->getTypeScriptPathArgumentsWithOutTypes())?>);
<?php } else { ?>
            Url = "<?=$endpoint->getXamarinUrl()?>";
<?php } ?>
        }
    }
