<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Api\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;

/**
 * Puts POST /api/auth/login into the generated contract (audit C2).
 *
 * The endpoint is served by the Lexik `json_login` firewall listener, which
 * answers before routing ever resolves a controller, so API Platform has no
 * resource metadata for it. What it does have is the bare `api_auth_login`
 * route the firewall's `check_path` names — and it documents that under the
 * ROUTE NAME as if it were a URL, producing the nonsense path key
 * `api_auth_login`. `scripts/check-contract.sh` had to skip it, and
 * `schema.gen.ts` never carried the endpoint at all, so the frontend reached
 * the single most security-relevant call through an untyped `apiPath()` cast.
 *
 * This decorator rewrites that entry to the real path with the contract's
 * AuthToken response (`specs/001-solo-ttrpg-assistant/contracts/openapi.yaml`
 * → components.schemas.AuthToken). It is documentation only: nothing here
 * runs during authentication, and the firewall is deliberately left alone.
 */
final readonly class LoginPathFactory implements OpenApiFactoryInterface
{
    /**
     * The bogus key API Platform derives from the routeless login route.
     */
    private const ROUTE_NAME_KEY = 'api_auth_login';

    public const PATH = '/api/auth/login';

    /**
     * Component name taken from the canonical contract
     * (specs/001-solo-ttrpg-assistant/contracts/openapi.yaml).
     */
    private const TOKEN_SCHEMA = 'AuthToken';

    public function __construct(private OpenApiFactoryInterface $decorated)
    {
    }

    #[\Override]
    public function __invoke(array $context = []): OpenApi
    {
        $openApi = $this->decorated->__invoke($context);

        $paths = new Paths();
        /** @var array<string, PathItem> $existing */
        $existing = $openApi->getPaths()->getPaths();

        foreach ($existing as $path => $pathItem) {
            if ($path === self::ROUTE_NAME_KEY || $path === self::PATH) {
                continue;
            }

            $paths->addPath($path, $pathItem);
        }

        $paths->addPath(self::PATH, new PathItem(post: self::loginOperation()));

        // Named like the canonical contract's component so the generated
        // client exposes `ApiSchemas['AuthToken']` rather than an inline shape.
        $openApi->getComponents()->getSchemas()?->offsetSet(
            self::TOKEN_SCHEMA,
            new \ArrayObject(self::authTokenSchema()),
        );

        return $openApi->withPaths($paths);
    }

    private static function loginOperation(): Operation
    {
        return new Operation(
            operationId: 'api_auth_login_post',
            tags: ['Auth'],
            responses: [
                '200' => new Response(
                    description: 'Authenticated: a bearer token and the account\'s roles.',
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/'.self::TOKEN_SCHEMA],
                        ],
                    ]),
                ),
                '401' => new Response(
                    description: 'The credentials are wrong.',
                    content: new \ArrayObject([
                        'application/problem+json' => [
                            'schema' => ['$ref' => '#/components/schemas/Error'],
                        ],
                    ]),
                ),
            ],
            summary: 'Obtain a JWT bearer token',
            description: 'Served by the json_login firewall listener, not by a controller. '
                .'Send `Accept: application/json`.',
            requestBody: new RequestBody(
                description: 'The account\'s credentials.',
                content: new \ArrayObject([
                    'application/json' => ['schema' => self::credentialsSchema()],
                ]),
                required: true,
            ),
            // Public: this is where the bearer token comes from.
            security: [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function credentialsSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['email', 'password'],
            'properties' => [
                'email' => ['type' => 'string', 'format' => 'email'],
                'password' => ['type' => 'string'],
            ],
        ];
    }

    /**
     * Mirrors the canonical contract's AuthToken schema.
     *
     * @return array<string, mixed>
     */
    private static function authTokenSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['token'],
            'properties' => [
                'token' => ['type' => 'string'],
                'roles' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ];
    }
}
