<?php

namespace Config;

/**
 * The test models and entities: the marketplace of the differential tests, and the CRM of the
 * realistic ones.
 */
class OrmExtension
{
    public static $modelNamespace = ['Tests\Support\Models\\', 'Tests\Support\Crm\Models\\'];
    public static $entityNamespace = ['Tests\Support\Entities\\', 'Tests\Support\Crm\Entities\\'];

    /** What the model export reads; the export test sets its own */
    public static $exportNamespace = ['Tests\Support\Entities\\'];
}
