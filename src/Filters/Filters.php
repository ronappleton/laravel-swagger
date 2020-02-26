<?php

namespace Mtrajano\LaravelSwagger\Filters;

use Mtrajano\LaravelSwagger\DataObjects\Route;

/**
 * Class Filters
 * @package Mtrajano\LaravelSwagger\Filters
 */
class Filters
{
    /**
     * @var array
     */
    protected array $config;

    /**
     * @var array
     */
    protected array $routeFilters;

    /**
     * @var Route
     */
    protected Route $route;

    public function __construct($config, $routeFilters)
    {
        $this->config = $config;
        $this->routeFilters = (array)$routeFilters;
    }

    public function unfilteredRequestMethods($route): array
    {
        $controller = $route->getController();
        $controllerMethod = $route->getControllerMethod();

        $unfiltered = [];
        foreach ($route->methods() as $requestMethod) {
            if (
                !$this->isIgnoredRequestMethod($requestMethod) &&
                !$this->isIgnoredControllerRequestMethod($controller, $requestMethod) &&
                !$this->isIgnoredControllerMethodRequestMethod($controller, $controllerMethod, $requestMethod)
            ) {
                $unfiltered[] = $requestMethod;
            }
        }

        return $unfiltered;
    }

    public function unfilteredAppRoutes(): array
    {
        $unfiltered = [];
        foreach ($this->getAppRoutes() as $route) {
            $this->route = $route;
            if (
                !$this->isFilteredRoute() &&
                !$this->isFilteredController() &&
                !$this->isFilteredControllerMethod()
            ) {
                $unfiltered[] = $route;
            }
        }

        return $unfiltered;
    }

    /**
     * @return bool
     */
    public function isFilteredRoute(): bool
    {
        foreach ($this->routeFilters as $routeFilter) {
            if (!preg_match('/^' . preg_quote($routeFilter, '/') . '/', $this->route->uri())) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $requestMethod
     * @return bool
     */
    public function isIgnoredRequestMethod($requestMethod): bool
    {
        return in_array($requestMethod, $this->config['global_ignored_request_methods'], true);
    }

    /**
     * @param $controller
     * @param $requestMethod
     * @return bool
     */
    public function isIgnoredControllerRequestMethod($controller, $requestMethod): bool
    {
        return
            array_key_exists($controller, $this->config['controller_ignored_request_methods']) &&
            in_array(
                $requestMethod,
                $this->config['controller_ignored_request_methods'][$controller],
                true
            );
    }

    /**
     * @param $controller
     * @param $controllerMethod
     * @param $requestMethod
     * @return bool
     */
    public function isIgnoredControllerMethodRequestMethod($controller, $controllerMethod, $requestMethod): bool
    {
        return
            array_key_exists($controller, $this->config['controller_method_ignored_request_methods']) &&
            array_key_exists(
                $controllerMethod,
                $this->config['controller_method_ignored_request_methods'][$controller]
            ) &&
            in_array(
                $requestMethod,
                $this->config['controller_method_ignored_request_methods'][$controller][$controllerMethod],
                true
            );
    }

    /**
     * @return bool
     */
    public function isFilteredController(): bool
    {
        return in_array($this->route->getController(), $this->config['controller_filters'], true);
    }

    /**
     * @return bool
     */
    public function isFilteredControllerMethod(): bool
    {
        $controller = $this->route->getController();

        return array_key_exists($controller, $this->config['controller_method_filters']) &&
            in_array(
                $this->route->getControllerMethod(),
                $this->config['controller_method_filters'][$controller],
                true
            );
    }

    /**
     * @return array
     */
    protected function getAppRoutes(): array
    {
        return array_map(
            static function ($route) {
                return new Route($route);
            },
            app('router')->getRoutes()->getRoutes()
        );
    }
}
