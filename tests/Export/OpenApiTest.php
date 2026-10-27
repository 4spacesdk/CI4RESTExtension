<?php

namespace Tests\Export;

use cebe\openapi\Reader;
use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Config\Factories;
use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use OrmExtension\ModelParser\OpenApi;
use PHPUnit\Framework\Attributes\DataProvider;
use RestExtension\ApiParser\ApiParser;
use Tests\Support\Api\First\ResourceController;

/**
 * The OpenAPI document of an API: valid OpenAPI 3.0, every `$ref` pointing at a schema, and the
 * doc comments' text, the parameters' descriptions and the properties' comments in it.
 */
final class OpenApiTest extends CIUnitTestCase
{
    private const DOCUMENTED = 'Tests\Support\Api\Documented';

    private ?array $exportNamespace = null;

    protected function setUp(): void
    {
        parent::setUp();
        helper('inflector');
        Services::autoloader()->addNamespace('Tests\Support\Api', dirname(__DIR__) . '/_support/Api');
        $config = new class () extends BaseConfig {
            public $apiControllerNamespace = 'Tests\Support\Api\Documented\Controllers';
            public $apiInterfaceNamespace = 'Tests\Support\Api\Documented\Interfaces';
            public $resourceControllerClass = ResourceController::class;
        };
        Factories::injectMock('config', 'RestExtension', $config);
        $this->exportNamespace = \Config\OrmExtension::$exportNamespace;
        \Config\OrmExtension::$exportNamespace = [self::DOCUMENTED . '\Entities'];
    }

    protected function tearDown(): void
    {
        Factories::reset('config');
        \Config\OrmExtension::$exportNamespace = $this->exportNamespace;
        parent::tearDown();
    }

    public function testTheDocumentIsValidOpenApi(): void
    {
        $openapi = Reader::readFromJson(json_encode($this->document()));

        $this->assertTrue($openapi->validate(), implode("\n", $openapi->getErrors()));
        $this->assertSame('3.0.3', $openapi->openapi);
    }

    public function testEveryReferenceIsASchemaOfTheDocument(): void
    {
        $document = $this->document();

        $references = OpenApi::references($document);
        sort($references);
        $this->assertSame(['Deletion', 'Part', 'SecretItem', 'SecretVersionItem', 'Thing', 'User', 'Workspace'], $references);
        foreach ($references as $name) {
            $this->assertArrayHasKey($name, $document['components']['schemas'], $name);
        }
        // Named, but neither a model nor an interface: an object by that name
        $this->assertSame(['title' => 'Workspace', 'type' => 'object'], $document['components']['schemas']['Workspace']);
    }

    public function testAnEndpointHasTheSummaryAndDescriptionOfItsDocComment(): void
    {
        $rotate = $this->document()['paths']['/workspaces/{id}/secrets/rotate']['put'];

        $this->assertSame('Rotate one of the secrets.', $rotate['summary']);
        $this->assertSame("The next deploy makes a new value, and the old one is kept\nas a version.", $rotate['description']);
        // The TypeScript export's name: every @param is of the path to it
        $this->assertSame('secretsRotatePutByIdByReason', $rotate['operationId']);
        $this->assertSame([['OAuth2' => ['workspaces.write']]], $rotate['security']);
    }

    public function testTheSummaryAndDescriptionTagsWin(): void
    {
        $import = $this->document()['paths']['/workspaces/{workspaceId}/secrets/import']['post'];

        $this->assertSame('Import secrets from a file', $import['summary']);
        $this->assertSame('The file is a .env file.', $import['description']);
    }

    public function testTheSummaryTagIsAsItWas(): void
    {
        // The TypeScript export writes the summary into Api.ts: the doc comment's text is not it
        foreach (ApiParser::run()->paths as $api) {
            foreach ($api->endpoints as $endpoint) {
                if ($endpoint->path == '/workspaces/{id}/secrets/rotate') {
                    $this->assertFalse(isset($endpoint->summary));
                }
            }
        }
    }

    public function testParametersHaveDescriptionsAndTheirTypes(): void
    {
        $parameters = [];
        foreach ($this->document()['paths']['/workspaces/{id}/secrets/rotate']['put']['parameters'] as $parameter) {
            $parameters[$parameter['in'] . ':' . $parameter['name']] = $parameter;
        }

        $this->assertSame(['path:id', 'query:name', 'query:version', 'query:ids', 'query:limit'], array_keys($parameters));
        $this->assertSame(['in' => 'path', 'name' => 'id', 'required' => true, 'schema' => ['type' => 'integer']], $parameters['path:id']);
        $this->assertSame("the secret's name", $parameters['query:name']['description']);
        $this->assertTrue($parameters['query:name']['required']);
        $this->assertFalse($parameters['query:version']['required']);
        $this->assertSame("an earlier value's; the current one without", $parameters['query:version']['description']);
        $this->assertSame(['type' => 'array', 'items' => ['type' => 'integer']], $parameters['query:ids']['schema']);
        $this->assertSame(['form', false], [$parameters['query:ids']['style'], $parameters['query:ids']['explode']]);
        $this->assertSame(['type' => 'integer', 'default' => 10], $parameters['query:limit']['schema']);
    }

    public function testAPathParameterNotDeclaredIsAString(): void
    {
        $import = $this->document()['paths']['/workspaces/{workspaceId}/secrets/import']['post'];

        $this->assertSame([['in' => 'path', 'name' => 'workspaceId', 'required' => true, 'schema' => ['type' => 'string']]], $import['parameters']);
    }

    public function testAFileIsAFieldOfAMultipartBody(): void
    {
        $import = $this->document()['paths']['/workspaces/{workspaceId}/secrets/import']['post'];

        $this->assertSame(['multipart/form-data' => ['schema' => [
            'type' => 'object',
            'properties' => [
                'file' => ['type' => 'string', 'format' => 'binary', 'description' => 'the .env file'],
                'prefix' => ['type' => 'string'],
            ],
            'required' => ['file', 'prefix'],
        ]]], $import['requestBody']['content']);
        $this->assertSame(
            ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/SecretItem']],
            $import['responses']['200']['content']['application/json']['schema']['properties']['resources'],
        );
    }

    public function testAnInterfaceRefersToTheSchemasOfItsProperties(): void
    {
        $schemas = $this->document()['components']['schemas'];

        $this->assertSame([
            'title' => 'SecretItem',
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => "the secret's name"],
                'version' => ['type' => 'integer', 'nullable' => true],
                'versions' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/SecretVersionItem']],
                'thing' => ['$ref' => '#/components/schemas/Thing'],
                'amount' => ['oneOf' => [['type' => 'string'], ['type' => 'number']]],
            ],
        ], $schemas['SecretItem']);
        $this->assertSame(['type' => 'string', 'nullable' => true], $schemas['SecretVersionItem']['properties']['rotated']);
    }

    public function testAModelRefersToItsRelations(): void
    {
        $thing = $this->document()['components']['schemas']['Thing']['properties'];

        $this->assertSame(['type' => 'string', 'description' => 'what it is called'], $thing['name']);
        $this->assertSame(['$ref' => '#/components/schemas/Workspace'], $thing['workspace']);
        $this->assertSame(['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Part']], $thing['parts']);
        $this->assertSame(['$ref' => '#/components/schemas/User'], $thing['created_by']);
    }

    public function testAnOverriddenResourceMethodKeepsItsCommentToItself(): void
    {
        $paths = $this->document()['paths'];

        $this->assertSame('Things of the caller', $paths['/things']['get']['summary']);
        $this->assertArrayNotHasKey('description', $paths['/things']['get']);
        $this->assertSame('Things of the caller', $paths['/things/{id}']['get']['summary']);
        $this->assertArrayNotHasKey('summary', $paths['/things']['post']);
    }

    public function testEveryOperationIdIsUnique(): void
    {
        $ids = [];
        foreach ($this->document()['paths'] as $operations) {
            foreach ($operations as $operation) {
                $ids[] = $operation['operationId'];
            }
        }

        $this->assertSame(array_unique($ids), $ids);
        $this->assertContains('thingsGet', $ids);
        $this->assertContains('thingsGetById', $ids);
    }

    public function testTheDocumentsOwnPartsAreKept(): void
    {
        $document = ApiParser::run()->generateOpenApi([
            'info' => ['title' => 'Things', 'version' => '2.0.0'],
            'servers' => [['url' => '/api']],
            'components' => ['schemas' => ['Workspace' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']]]]],
        ], '/v1');

        $this->assertSame(['title' => 'Things', 'version' => '2.0.0'], $document['info']);
        $this->assertSame([['url' => '/api']], $document['servers']);
        $this->assertArrayHasKey('/v1/things', $document['paths']);
        $this->assertSame(['id' => ['type' => 'integer']], $document['components']['schemas']['Workspace']['properties']);
    }

    public function testRequiredFalseIsNotRequiredInTheSwaggerPaths(): void
    {
        $parameters = ApiParser::run()->generateSwagger()['/workspaces/{id}/secrets/rotate']['put']['parameters'];

        $required = array_column($parameters, 'required', 'name');
        // Without required= a parameter is required, as it was
        $this->assertSame(['id' => true, 'reason' => true, 'name' => true, 'version' => false, 'ids' => false, 'limit' => false], $required);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function types(): iterable
    {
        yield 'int' => ['int', ['type' => 'integer']];
        yield 'nullable' => ['?string', ['type' => 'string', 'nullable' => true]];
        yield 'or null' => ['int|null', ['type' => 'integer', 'nullable' => true]];
        yield 'list' => ['string[]', ['type' => 'array', 'items' => ['type' => 'string']]];
        yield 'bool' => ['bool', ['type' => 'boolean']];
        yield 'double' => ['double', ['type' => 'number']];
        yield 'class' => ['\App\Entities\User', ['$ref' => '#/components/schemas/User']];
        yield 'nullable class' => ['?User', ['allOf' => [['$ref' => '#/components/schemas/User']], 'nullable' => true]];
        yield 'mixed' => ['mixed', ['nullable' => true]];
        yield 'array' => ['array', ['type' => 'array', 'items' => ['nullable' => true]]];
    }

    #[DataProvider('types')]
    public function testATypeIsItsSchema(string $type, array $schema): void
    {
        $this->assertSame($schema, OpenApi::schema($type));
    }

    public function testADescribedReferenceIsWrapped(): void
    {
        $this->assertSame(
            ['allOf' => [['$ref' => '#/components/schemas/User']], 'description' => 'who made it'],
            OpenApi::schema('User', 'who made it'),
        );
    }

    public function testTheSummaryIsTheFirstSentenceOrParagraph(): void
    {
        $this->assertSame([null, null], OpenApi::docText(false));
        $this->assertSame(['One line', null], OpenApi::docText("/** One line */"));
        $this->assertSame(
            ['A summary over two lines.', "The rest.\n\nMore."],
            OpenApi::docText("/**\n     * A summary over\n     * two lines.\n     * The rest.\n     *\n     * More.\n     *\n     * @route /x\n     */"),
        );
        $this->assertSame(
            ['A paragraph without a period', 'The description'],
            OpenApi::docText("/**\n * A paragraph without\n * a period\n *\n * The description\n */"),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function document(): array
    {
        return ApiParser::run()->generateOpenApi([
            'info' => ['title' => 'Documented', 'version' => '1.0.0'],
            'components' => ['securitySchemes' => ['OAuth2' => ['type' => 'oauth2', 'flows' => ['authorizationCode' => [
                'authorizationUrl' => 'https://example.test/authorize',
                'tokenUrl' => 'https://example.test/token',
                'scopes' => ['workspaces.write' => 'Change workspaces'],
            ]]]]],
        ]);
    }
}
