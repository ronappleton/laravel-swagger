<?php

namespace Mtrajano\LaravelSwagger\DataObjects;

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Route
{
    private $route;
    private $middleware;
    private $controllerMethod;

    public function __construct(LaravelRoute $route)
    {
        $this->route = $route;
        $this->middleware = $this->formatMiddleware();
        $this->controllerMethod = $this->getControllerAndMethod();
    }

    public function originalUri()
    {
        $uri = $this->route->uri();

        if (!Str::startsWith($uri, '/')) {
            $uri = '/' . $uri;
        }

        return $uri;
    }

    public function uri()
    {
        return strip_optional_char($this->originalUri());
    }

    public function middleware()
    {
        return $this->middleware;
    }

    public function action(): string
    {
        return $this->route->getActionName();
    }

    public function methods()
    {
        return array_map('strtolower', $this->route->methods());
    }

    public function getController()
    {
        return reset($this->controllerMethod);
    }

    public function getControllerMethod()
    {
        return end($this->controllerMethod);
    }

    protected function formatMiddleware()
    {
        $middleware = $this->route->getAction()['middleware'] ?? [];

        return array_map(
            function ($middleware) {
                return new Middleware($middleware);
            },
            Arr::wrap($middleware)
        );
    }

    /**
     * @return array
     */
    private function getControllerAndMethod(): array
    {
        $parts = explode('\\', $this->action());
        $subject = $parts[count($parts) - 1];
        return explode('@', $subject);
    }
}
