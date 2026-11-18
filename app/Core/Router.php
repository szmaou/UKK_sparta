<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Tiny server-side router. Paths are literals with optional {name} segments.
 * A route is private unless it is explicitly public; RBAC roles are metadata
 * checked by the front controller before the handler runs.
 */
final class Router
{
    /** @var list<array{method: string, path: string, regex: string, params: list<string>, handler: callable|array, auth: bool, roles: list<string>|null}> */
    private array $routes = [];

    public function get(string $path, callable|array $handler, ?array $roles = null, bool $public = false): void
    {
        $this->add('GET', $path, $handler, $roles, $public);
    }

    public function post(string $path, callable|array $handler, ?array $roles = null, bool $public = false): void
    {
        $this->add('POST', $path, $handler, $roles, $public);
    }

    /**
     * @param callable|array $handler
     * @param list<string>|null $roles null means any authenticated role
     */
    public function add(string $method, string $path, callable|array $handler, ?array $roles = null, bool $public = false): void
    {
        $path = $this->normalize($path);
        $params = [];
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static function (array $match) use (&$params): string {
                $params[] = $match[1];

                return '([^/]+)';
            },
            $path
        );

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'regex' => '#^' . $regex . '$#',
            'params' => $params,
            'handler' => $handler,
            'auth' => !$public,
            'roles' => $roles === null ? null : array_values($roles),
        ];
    }

    /**
     * @return array{handler: callable|array, params: array<string, string>, auth: bool, roles: list<string>|null}|null
     */
    public function match(string $method, string $path): ?array
    {
        $method = strtoupper($method);
        $path = $this->normalize($path);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method || preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            array_shift($matches);
            $params = [];
            foreach ($route['params'] as $index => $name) {
                $params[$name] = rawurldecode((string) ($matches[$index] ?? ''));
            }

            return [
                'handler' => $route['handler'],
                'params' => $params,
                'auth' => $route['auth'],
                'roles' => $route['roles'],
            ];
        }

        return null;
    }

    /** @return list<string> */
    public function allowedMethods(string $path): array
    {
        $path = $this->normalize($path);
        $methods = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path) === 1) {
                $methods[] = $route['method'];
            }
        }

        return array_values(array_unique($methods));
    }

    private function normalize(string $path): string
    {
        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
