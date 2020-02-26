<?php

declare(strict_types=1);

namespace Mtrajano\LaravelSwagger;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Mtrajano\LaravelSwagger\Filters\Filters;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionException;
use ReflectionMethod;

/**
 * Class Generator
 * @package Mtrajano\LaravelSwagger
 */
class Generator
{
    /**
     * @var string
     */
    public const SECURITY_DEFINITION_NAME = 'OAuth2';

    /**
     * @var string
     */
    public const OAUTH_TOKEN_PATH = '/oauth/token';

    /**
     * @var string
     */
    public const OAUTH_AUTHORIZE_PATH = '/oauth/authorize';

    /**
     * @var
     */
    protected $config;

    /**
     * @var
     */
    protected $docParser;

    /**
     * @var Filters
     */
    protected $filters;

    /**
     * Generator constructor.
     *
     * @param $config
     * @param array|string|null $routeFilters
     */
    public function __construct($config, $routeFilters = null)
    {
        $this->config = $config;
        $this->filters = new Filters($config, $routeFilters);
        $this->docParser = DocBlockFactory::createInstance();
    }

    /**
     * @return array
     * @throws LaravelSwaggerException
     * @throws ReflectionException
     */
    public function generate(): array
    {
        $docs = $this->getBaseInfo();
        $securityDefinitions = false;

        if ($this->config['parse_security'] && $this->hasOauthRoutes()) {
            $docs['securityDefinitions'] = $this->generateSecurityDefinitions();
            $securityDefinitions = true;
        }

        foreach ($this->filters->unfilteredAppRoutes() as $route) {
            if (!isset($docs['paths'][$route->uri()])) {
                $docs['paths'][$route->uri()] = [];
            }

            foreach ($this->filters->unfilteredRequestMethods() as $method) {
                $docs = $this->generatePath($docs, $route, $method, $securityDefinitions);
            }
        }

        return $docs;
    }


    /**
     * @return array
     */
    protected function getBaseInfo(): array
    {
        $baseInfo = [
            'swagger' => '2.0',
            'info' => [
                'title' => $this->config['title'],
                'description' => $this->config['description'],
                'version' => $this->config['app_version'],
            ],
            'host' => $this->config['host'],
            'basePath' => $this->config['basePath'],
        ];

        if (!empty($this->config['schemes'])) {
            $baseInfo['schemes'] = $this->config['schemes'];
        }

        if (!empty($this->config['consumes'])) {
            $baseInfo['consumes'] = $this->config['consumes'];
        }

        if (!empty($this->config['produces'])) {
            $baseInfo['produces'] = $this->config['produces'];
        }

        $baseInfo['paths'] = [];

        return $baseInfo;
    }

    /**
     * @return array
     * @throws LaravelSwaggerException
     */
    protected function generateSecurityDefinitions(): array
    {
        $authFlow = $this->config['auth_flow'];

        $this->validateAuthFlow($authFlow);

        $securityDefinition = [
            self::SECURITY_DEFINITION_NAME => [
                'type' => 'oauth2',
                'flow' => $authFlow,
            ],
        ];

        if (in_array($authFlow, ['implicit', 'accessCode'])) {
            $securityDefinition[self::SECURITY_DEFINITION_NAME]['authorizationUrl'] = $this->getEndpoint(
                self::OAUTH_AUTHORIZE_PATH
            );
        }

        if (in_array($authFlow, ['password', 'application', 'accessCode'])) {
            $securityDefinition[self::SECURITY_DEFINITION_NAME]['tokenUrl'] = $this->getEndpoint(
                self::OAUTH_TOKEN_PATH
            );
        }

        $securityDefinition[self::SECURITY_DEFINITION_NAME]['scopes'] = $this->generateOauthScopes();

        return $securityDefinition;
    }

    /**
     * @param $docs
     * @param $route
     * @param $method
     * @param $security
     * @return array
     * @throws ReflectionException
     */
    protected function generatePath($docs, $route, $method, $security): array
    {
        $docBlock = $this->getDocBlock($route);

        [$isDeprecated, $summary, $description] = $this->parseActionDocBlock($docBlock);

        $docs['paths'][$route->uri()][$method] = [
            'summary' => $summary,
            'description' => $description,
            'deprecated' => $isDeprecated,
            'responses' => [
                '200' => [
                    'description' => 'OK',
                ],
            ],
        ];

        $docs = $this->addActionParameters($docs, $route, $method);

        if ($security) {
            $docs = $this->addActionScopes($docs, $route, $method);
        }

        return $docs;
    }

    /**
     * @param $route
     * @return false|string
     * @throws ReflectionException
     */
    protected function getDocBlock($route): string
    {
        $docBlock = '';

        $actionInstance = $this->getActionClassInstance($route);
        if ($actionInstance !== null) {
            $docBlock = $actionInstance->getDocComment();
            if (!is_string($docBlock)) {
                $docBlock = '';
            }
        }

        return $docBlock;
    }

    /**
     * @param $docs
     * @param $route
     * @param $method
     * @return array
     * @throws ReflectionException
     */
    protected function addActionParameters($docs, $route, $method): array
    {
        $rules = $this->getFormRules() ?: [];

        $parameters = (new Parameters\PathParameterGenerator($route->originalUri()))->getParameters();

        if (!empty($rules)) {
            $parameterGenerator = $this->getParameterGenerator($method, $rules);

            $parameters = array_merge($parameters, $parameterGenerator->getParameters());
        }

        if (!empty($parameters)) {
            $docs['paths'][$route->uri()][$method]['parameters'] = $parameters;
        }

        return $docs;
    }

    /**
     * @param $docs
     * @param $route
     * @param $method
     * @return array
     */
    protected function addActionScopes($docs, $route, $method): array
    {
        foreach ($route->middleware() as $middleware) {
            if ($this->isPassportScopeMiddleware($middleware)) {
                $docs['paths'][$route->uri()][$method]['security'] = [
                    self::SECURITY_DEFINITION_NAME => $middleware->parameters(),
                ];
            }
        }

        return $docs;
    }

    /**
     * @return array
     * @throws ReflectionException
     */
    protected function getFormRules(): array
    {
        $action_instance = $this->getActionClassInstance();

        if (!$action_instance) {
            return [];
        }

        $parameters = $action_instance->getParameters();

        foreach ($parameters as $parameter) {
            $class = $parameter->getClass();

            if (!$class) {
                continue;
            }

            $class_name = $class->getName();

            if (is_subclass_of($class_name, FormRequest::class)) {
                return (new $class_name())->rules();
            }
        }

        return [];
    }

    /**
     * @param $method
     * @param $rules
     * @return Parameters\BodyParameterGenerator|Parameters\QueryParameterGenerator
     */
    protected function getParameterGenerator($method, $rules)
    {
        switch ($method) {
            case 'post':
            case 'put':
            case 'patch':
                return new Parameters\BodyParameterGenerator($rules);
            default:
                return new Parameters\QueryParameterGenerator($rules);
        }
    }

    /**
     * @param $route
     * @return ReflectionMethod|null
     * @throws ReflectionException
     */
    private function getActionClassInstance($route): ?ReflectionMethod
    {
        [$class, $method] = Str::parseCallback($route->action());

        if (!$class || !$method) {
            return null;
        }

        return new ReflectionMethod($class, $method);
    }

    /**
     * @param string $docBlock
     * @return array|null
     */
    private function parseActionDocBlock(string $docBlock): ?array
    {
        if (empty($docBlock) || !$this->config['parse_doc_block']) {
            return [false, '', ''];
        }

        try {
            $parsedComment = $this->docParser->create($docBlock);

            $isDeprecated = $parsedComment->hasTag('deprecated');

            $summary = $parsedComment->getSummary();
            $description = (string)$parsedComment->getDescription();

            return [$isDeprecated, $summary, $description];
        } catch (\Exception $e) {
            return [false, '', ''];
        }
    }

    /**
     * Assumes routes have been created using Passport::routes().
     */
    private function hasOauthRoutes(): bool
    {
        foreach ($this->getAppRoutes() as $route) {
            $uri = $route->uri();

            if ($uri === self::OAUTH_TOKEN_PATH || $uri === self::OAUTH_AUTHORIZE_PATH) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $path
     * @return string
     */
    private function getEndpoint(string $path): string
    {
        return rtrim($this->config['host'], '/') . $path;
    }

    /**
     * @return array|false
     */
    private function generateOauthScopes()
    {
        if (!class_exists('\Laravel\Passport\Passport')) {
            return [];
        }

        $scopes = \Laravel\Passport\Passport::scopes()->toArray();

        return array_combine(array_column($scopes, 'id'), array_column($scopes, 'description'));
    }

    /**
     * @param string $flow
     * @throws LaravelSwaggerException
     */
    private function validateAuthFlow(string $flow): void
    {
        if (!in_array($flow, ['password', 'application', 'implicit', 'accessCode'])) {
            throw new LaravelSwaggerException('Invalid OAuth flow passed');
        }
    }

    /**
     * @param DataObjects\Middleware $middleware
     * @return bool
     */
    private function isPassportScopeMiddleware(DataObjects\Middleware $middleware): bool
    {
        $resolver = $this->getMiddlewareResolver($middleware->name());

        return $resolver === 'Laravel\Passport\Http\Middleware\CheckScopes' ||
            $resolver === 'Laravel\Passport\Http\Middleware\CheckForAnyScope';
    }

    /**
     * @param string $middleware
     * @return mixed|null
     */
    private function getMiddlewareResolver(string $middleware)
    {
        $middlewareMap = app('router')->getMiddleware();

        return $middlewareMap[$middleware] ?? null;
    }
}
