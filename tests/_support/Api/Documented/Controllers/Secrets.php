<?php

namespace Tests\Support\Api\Documented\Controllers;

use CodeIgniter\Controller;

/**
 * Endpoints documented as an app documents its own, for the OpenAPI test.
 */
class Secrets extends Controller
{
    /**
     * Rotate one of the secrets.
     *
     * The next deploy makes a new value, and the old one is kept
     * as a version.
     *
     * @route /workspaces/{id}/secrets/rotate
     * @method put
     * @custom true
     * @param int $id
     * @param string $reason not in the route
     * @parameter string $name parameterType=query - the secret's name
     * @parameter int $version parameterType=query required=false - an earlier value's; the current one without
     * @parameter int[] $ids parameterType=query required=false
     * @parameter int $limit parameterType=query required=false default=10
     * @responseSchema SecretItem
     * @scope workspaces.write
     */
    public function rotate($id): void
    {
    }

    /**
     * Not the summary: the tag is.
     *
     * @route /workspaces/{workspaceId}/secrets/import
     * @method post
     * @custom true
     * @summary Import secrets from a file
     * @description The file is a .env file.
     * @parameter File $file parameterType=formData - the .env file
     * @parameter string $prefix parameterType=formData required=true
     * @responseSchema SecretItem[]
     */
    public function import(): void
    {
    }
}
