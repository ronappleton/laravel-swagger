<?php

declare(strict_types=1);

namespace Mtrajano\LaravelSwagger;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
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
     * @var null
     */
    protected $routeFilters;

    /**
     * @var
     */
    protected $docs;

    /**
     * @var
     */
    protected $route;

    /**
     * @var
     */
    protected $method;

    /**
     * @var
     */
    protected $docParser;

    /**
     * @var bool
     */
    protected $hasSecurityDefinitions;

    /**
     * Generator constructor.
     * @param $config
     * @param array|string|null $routeFilters
     */
    public function __construct($config, $routeFilters = null)
    {
        $this->config = $config;
        $this->routeFilters = (array)$routeFilters;
        $this->docParser = DocBlockFactory::createInstance();
        $this->hasSecurityDefinitions = false;
    }

    /**
     * @return array
     * @throws LaravelSwaggerException
     * @throws ReflectionException
     */
    public function generate(): array
    {
        $this->docs = $this->getBaseInfo();

        if ($this->config['parseSecurity'] && $this->hasOauthRoutes()) {
            $this->docs['securityDefinitions'] = $this->generateSecurityDefinitions();
            $this->hasSecurityDefinitions = true;
        }

        foreach ($this->getAppRoutes() as $route) {
            $this->route = $route;

            if (
                (
                    $this->routeFilters && $this->isFilteredRoute()
                ) ||
                $this->isFilteredAction()
            ) {
                continue;
            }

            if (!isset($this->docs['paths'][$this->route->uri()])) {
                $this->docs['paths'][$this->route->uri()] = [];
            }

            foreach ($route->methods() as $method) {
                $this->method = $method;

                if (in_array($this->method, $this->config['ignoredMethods'], true)) {
                    continue;
                }

                $this->generatePath();
            }
        }

        return $this->docs;
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
                'version' => $this->config['appVersion'],
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
     */
    protected function getAppRoutes(): array
    {
        return array_map(
            static function ($route) {
                return new DataObjects\Route($route);
            },
            app('router')->getRoutes()->getRoutes()
        );
    }

    /**
     * @return array
     * @throws LaravelSwaggerException
     */
    protected function generateSecurityDefinitions(): array
    {
        $authFlow = $this->config['authFlow'];

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
     * @throws ReflectionException
     */
    protected function generatePath(): void
    {
        $docBlock = $this->getDocBlock();

        [$isDeprecated, $summary, $description] = $this->parseActionDocBlock($docBlock);

        $this->docs['paths'][$this->route->uri()][$this->method] = [
            'summary' => $summary,
            'description' => $description,
            'deprecated' => $isDeprecated,
            'responses' => [
                '200' => [
                    'description' => 'OK',
                ],
            ],
        ];

        $this->addActionParameters();

        if ($this->hasSecurityDefinitions) {
            $this->addActionScopes();
        }
    }

    /**
     * @return false|string
     * @throws ReflectionException
     */
    protected function getDocBlock(): string
    {
        $docBlock = '';

        if (($actionInstance = $this->getActionClassInstance()) !== null) {
            $docBlock = $actionInstance->getDocComment();
            if (!is_string($docBlock)) {
                $docBlock = '';
            }
        }

        return $docBlock;
    }

    /**
     *
     */
    protected function addActionParameters(): void
    {
        $rules = $this->getFormRules() ?: [];

        $parameters = (new Parameters\PathParameterGenerator($this->route->originalUri()))->getParameters();

        if (!empty($rules)) {
            $parameterGenerator = $this->getParameterGenerator($rules);

            $parameters = array_merge($parameters, $parameterGenerator->getParameters());
        }

        if (!empty($parameters)) {
            $this->docs['paths'][$this->route->uri()][$this->method]['parameters'] = $parameters;
        }
    }

    /**
     *
     */
    protected function addActionScopes(): void
    {
        foreach ($this->route->middleware() as $middleware) {
            if ($this->isPassportScopeMiddleware($middleware)) {
                $this->docs['paths'][$this->route->uri()][$this->method]['security'] = [
                    self::SECURITY_DEFINITION_NAME => $middleware->parameters(),
                ];
            }
        }
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
     * @param $rules
     * @return Parameters\BodyParameterGenerator|Parameters\QueryParameterGenerator
     */
    protected function getParameterGenerator($rules)
    {
        switch ($this->method) {
            case 'post':
            case 'put':
            case 'patch':
                return new Parameters\BodyParameterGenerator($rules);
            default:
                return new Parameters\QueryParameterGenerator($rules);
        }
    }

    /**
     * @return ReflectionMethod|null
     * @throws ReflectionException
     */
    private function getActionClassInstance(): ?ReflectionMethod
    {
        [$class, $method] = Str::parseCallback($this->route->action());

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
        if (empty($docBlock) || !$this->config['parseDocBlock']) {
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
     * @return bool
     */
    private function isFilteredRoute(): bool
    {
        foreach ($this->routeFilters as $routeFilter) {
            if (preg_match('/^' . preg_quote($routeFilter, '/') . '/', $this->route->uri())) {
                return false;
            }
        }

        return true;
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

    private function isFilteredAction()
    {
        $action = explode('\\', $this->route->action());
        [$controller, $method] = explode('@', end($action));

        if (in_array($controller, $this->config['controller_filters'], true)) {
            return true;
        }

        if (
            !empty($methods = $this->config['controller_method_filters'][$controller]) &&
            in_array($method, $methods, true)
        ) {
            return true;
        }

        return false;
    }
}
