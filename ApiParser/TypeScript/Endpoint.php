<?php
/** @var \RestExtension\ApiParser\ApiItem $path */
/** @var \RestExtension\ApiParser\EndpointItem $endpoint */
/** @var string $className */
/** @var \RestExtension\ApiParser\ApiItem $apiItem */
?>
class <?=$className?> extends BaseApi<<?=$path->isResourceController ? $apiItem->resourceNameUpperCase : 'any'?>> {

    protected method = '<?=$endpoint->method?>';
    protected scope = '<?=isset($endpoint->scope)?$endpoint->scope:''?>';
    protected summary = '<?=isset($endpoint->summary)?$endpoint->summary:''?>';

    public constructor(<?=implode(', ', $endpoint->getTypeScriptPathArgumentsWithTypes())?>) {
        super();
        this.uri = `<?=$endpoint->getTypeScriptUrl()?>`;
    }

    protected convertToResource(data: any): <?=$apiItem->resourceNameUpperCase?> {
        return new <?=$apiItem->resourceNameUpperCase?>(data);
    }
<?php foreach($endpoint->getTypeScriptQueryParameters() as $parameter) { ?>

    public <?=$parameter->name?>(value: <?=$parameter->getTypeScriptType()?>): <?=$className?> {
        this.addQueryParameter('<?=$parameter->name?>', value);
        return this;
    }
<?php } ?>
<?if($endpoint->hasParameter('filter')) { ?>

    public where(name: string, value: any): <?=$className?> {
        this.filter().where(name, value);
        return this;
    }

    public whereEquals(name: string, value: any): <?=$className?> {
        this.filter().whereEquals(name, value);
        return this;
    }

    public whereIn(name: string, value: any[]): <?=$className?> {
        this.filter().whereIn(name, value);
        return this;
    }

    public whereInArray(name: string, value: any[]): <?=$className?> {
        this.filter().whereInArray(name, value);
        return this;
    }

    public whereNot(name: string, value: any): <?=$className?> {
        this.filter().whereNot(name, value);
        return this;
    }

    public whereNotIn(name: string, value: any[]): <?=$className?> {
        this.filter().whereNotIn(name, value);
        return this;
    }

    public whereGreaterThan(name: string, value: any): <?=$className?> {
        this.filter().whereGreaterThan(name, value);
        return this;
    }

    public whereGreaterThanOrEqual(name: string, value: any): <?=$className?> {
        this.filter().whereGreaterThanOrEqual(name, value);
        return this;
    }

    public whereLessThan(name: string, value: any): <?=$className?> {
        this.filter().whereLessThan(name, value);
        return this;
    }

    public whereLessThanOrEqual(name: string, value: any): <?=$className?> {
        this.filter().whereLessThanOrEqual(name, value);
        return this;
    }

    public search(name: string, value: any): <?=$className?> {
        this.filter().search(name, value);
        return this;
    }
<?php } ?>
<?if($endpoint->hasParameter('include')) { ?>

    public include(name: string): <?=$className?> {
        this.getInclude().include(name);
        return this;
    }
<?php } ?>
<?if($endpoint->hasParameter('ordering')) { ?>

    public orderBy(name: string, direction: string): <?=$className?> {
        this.ordering().orderBy(name, direction);
        return this;
    }

    public orderAsc(name: string): <?=$className?> {
        this.ordering().orderAsc(name);
        return this;
    }

    public orderDesc(name: string): <?=$className?> {
        this.ordering().orderDesc(name);
        return this;
    }
<?php } ?>
<?if($endpoint->hasParameter('limit')) { ?>

    public limit(value: number): <?=$className?> {
        super.limitValue = value;
        return this;
    }
<?php } ?>
<?if($endpoint->hasParameter('offset')) { ?>

    public offset(value: number): <?=$className?> {
        super.offsetValue = value;
        return this;
    }
<?php } ?>
<?if($endpoint->hasParameter('count')) { ?>

    public count(next?: (value: number) => void): Subscription {
        return this.executeCount(next);
    }
<?php } ?>
<?php if($endpoint->method == 'get') { ?>

    public find(next?: (value: <?=$apiItem->resourceNameUpperCase?>[]) => void): Subscription {
        return super.executeFind(next);
    }

    public getClient(): Observable<any | <?=$apiItem->resourceNameUpperCase?>[] | any[]> {
        return super.executeClientGet();
    }
<?php } else if($endpoint->method == 'delete') { ?>

    public delete(next?: (value: <?=$apiItem->resourceNameUpperCase?>) => void): Subscription {
        return super.executeDelete(next);
    }
<?php } else { ?>

    public save(data: any, next?: (value: <?=$apiItem->resourceNameUpperCase?>) => void): Subscription {
        return super.executeSave(data, next);
    }
<?php } ?>
}
