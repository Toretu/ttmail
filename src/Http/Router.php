<?php
declare(strict_types=1);

namespace Mailings\Http;

final class Router
{
    /** @var list<array{string,string,callable}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        $this->routes[] = [$method, $regex, $handler];
    }

    public function dispatch(string $method, string $path): mixed
    {
        $pathMatched = false;
        foreach ($this->routes as [$routeMethod, $regex, $handler]) {
            if (preg_match($regex, $path, $matches) !== 1) {
                continue;
            }
            $pathMatched = true;
            if ($routeMethod === $method) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return $handler($params);
            }
        }
        throw $pathMatched
            ? new HttpException(405, 'Method not allowed')
            : new HttpException(404, 'Not found');
    }
}
