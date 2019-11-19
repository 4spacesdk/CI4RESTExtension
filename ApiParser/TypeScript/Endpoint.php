<?php
/** @var \RestExtension\ApiParser\EndpointItem $endpoint */
?>
class <?=$endpoint->getTypeScriptClassName()?> extends BaseApi<<?=$endpoint->responseSchema ?? 'any'?>> {

    protected method = '<?=$endpoint->method?>';
    protected scope = '<?=isset($endpoint->scope)?$endpoint->scope:''?>';
    protected summary = '<?=isset($endpoint->summary)?$endpoint->summary:''?>';

    public constructor(<?=implode(', ', $endpoint->getTypeScriptPathArgumentsWithTypes())?>) {
        super();
        this.uri = `<?=$endpoint->getTypeScriptUrl()?>`;
    }

    protected convertToResource(data: any): <?=$endpoint->responseSchema ?? 'any'?> {
<?php if($endpoint->isResponseSchemaAModel()) { ?>
        return new <?="{$endpoint->responseSchema}(data)"?>;
<?php  } else { ?>
        return data;
<?php  } ?>
    }
<?php foreach($endpoint->getTypeScriptQueryParameters() as $parameter) { ?>

    public <?=$parameter->name?>(value: <?=$parameter->getTypeScriptType()?>): <?=$endpoint->getTypeScriptClassName()?> {
        this.addQueryParameter('<?=$parameter->name?>', value);
        return this;
    }
<?php } ?>
<?php if($endpoint->hasParameter('filter')) { ?>

    public where(name: string, value: any): <?=$endpoint->getTypeScriptClassName()?> {
        this.filter().where(name, value);
        return this;
    }

    public whereEquals(name: string, value: any): <?=$endpoint->getTypeScriptClassName()?> {
        this.filter().whereEquals(name, value);
        return this;
    }

    public whereIn(name: string, value: any[]): <?=$endpoint->getTypeScriptClassName()?> {
        this.filter().whereIn(name, value);
        return this;
    }

    public whereInArray(name: string, value: any[]): <?=$endpoint->getTypeScriptClassName()?> {
        this.filter().whereInArray(name, value);
        return this;
    }

    public whereNot(name: string, value: any): <?=$endpoint->getTypeScriptClassName()?> {
        this.filter().whereNot(name, value);
        return this;
    }

    public whereNotIn(name: string, value: any[]): <?=$endpoint->getTypeScriptClassName()?> {
        this.filter().whereNotIn(name, value);
        return this;
    }

    public whereGreaterThan(name: string, value: any): <?=$endpoint->getTypeScriptClassName()?> {
        this.filter().whereGreaterThan(name, value);
        return this;
    }

    public whereGreaterThanOrEqual(name: string, value: any): <?=$endpoint->getTypeScriptClassName()?> {
        this.filter().whereGreaterThanOrEqual(name, value);
        return this;
    }

    public whereLessThan(name: string, value: any): <?=$endpoint->getTypeScriptClassName()?> {
        this.filter().whereLessThan(name, value);
        return this;
    }

    public whereLessThanOrEqual(name: string, value: any): <?=$endpoint->getTypeScriptClassName()?> {
        this.filter().whereLessThanOrEqual(name, value);
        return this;
    }

    public search(name: string, value: any): <?=$endpoint->getTypeScriptClassName()?> {
        this.filter().search(name, value);
        return this;
    }
<?php } ?>
<?php if($endpoint->hasParameter('include')) { ?>

    public include(name: string): <?=$endpoint->getTypeScriptClassName()?> {
        this.getInclude().include(name);
        return this;
    }
<?php } ?>
<?php if($endpoint->hasParameter('ordering')) { ?>

    public orderBy(name: string, direction: string): <?=$endpoint->getTypeScriptClassName()?> {
        this.ordering().orderBy(name, direction);
        return this;
    }

    public orderAsc(name: string): <?=$endpoint->getTypeScriptClassName()?> {
        this.ordering().orderAsc(name);
        return this;
    }

    public orderDesc(name: string): <?=$endpoint->getTypeScriptClassName()?> {
        this.ordering().orderDesc(name);
        return this;
    }
<?php } ?>
<?php if($endpoint->hasParameter('limit')) { ?>

    public limit(value: number): <?=$endpoint->getTypeScriptClassName()?> {
        this.limitValue = value;
        return this;
    }
<?php } ?>
<?php if($endpoint->hasParameter('offset')) { ?>

    public offset(value: number): <?=$endpoint->getTypeScriptClassName()?> {
        this.offsetValue = value;
        return this;
    }
<?php } ?>
<?php if($endpoint->hasParameter('count')) { ?>

    public count(next?: (value: number) => void): RXJSSubscription {
        return this.executeCount(next);
    }
<?php } ?>
<?php if($endpoint->method == 'get') { ?>

    public find(next?: (value: <?=$endpoint->responseSchema ?? 'any'?>[]) => void): RXJSSubscription {
        return super.executeFind(next);
    }

    public getClient(): Observable<any | <?=$endpoint->responseSchema ?? 'any'?>[] | any[]> {
        return super.executeClientGet();
    }
<?php } else if($endpoint->method == 'delete') { ?>

    public delete(next?: (value: <?=$endpoint->responseSchema ?? 'any'?>) => void): RXJSSubscription {
        return super.executeDelete(next);
    }
<?php } else { ?>

    public save(data: <?=$endpoint->requestEntity ?? 'any'?>, next?: (value: <?=$endpoint->responseSchema ?? 'any'?>) => void): RXJSSubscription {
        return super.executeSave(data, next);
    }
<?php } ?>
}
