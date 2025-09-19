<?php
namespace App\Service;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RouteTreeService
{
    public function __construct(
        private RouterInterface $router,
        private UrlGeneratorInterface $urlGen,
        private array $sampleParamMap = [],
        private ?string $baseUri = null,   // e.g. https://repweb or http://127.0.0.1
        private ?string $basePath = null,  // e.g. /repweb when served in a subdir
        private bool $skipDebugRoutes = true,
    ) {
        // Optional: set router context for other consumers (not strictly needed anymore)
        if ($this->baseUri) {
            $p = parse_url($this->baseUri);
            $scheme = $p['scheme'] ?? 'https';
            $host   = $p['host']   ?? 'localhost';
            $port   = $p['port']   ?? null;

            $ctx = $this->router->getContext();
            $ctx->setScheme($scheme);
            $ctx->setHost($host);
            if ($port) {
                if ($scheme === 'https') $ctx->setHttpsPort((int)$port);
                else                      $ctx->setHttpPort((int)$port);
            }
            if ($this->basePath && $this->basePath !== '/') {
                $ctx->setBaseUrl(rtrim($this->basePath, '/'));
            }
        }
    }

    /** Expose the computed probe base URL for diagnostics */
    public function getProbeBase(): string
    {
        $ctx = $this->router->getContext();
        $schemeHost = ($this->baseUri ?: ($ctx->getScheme() . '://' . $ctx->getHost()));
        $base = rtrim($schemeHost, '/');
        $bp   = ($this->basePath && $this->basePath !== '/') ? rtrim($this->basePath, '/') : rtrim($ctx->getBaseUrl(), '/');
        if ($bp !== '' && $bp !== '/') $base .= $bp;
        return $base; // e.g. https://repweb or https://repweb/repweb
    }

    public function listRoutes(): array
    {
        $collection = $this->router->getRouteCollection();
        $rows = [];

        /** @var Route $route */
        foreach ($collection as $name => $route) {
            if ($this->skipDebugRoutes && (str_starts_with($name, '_profiler') || str_starts_with($name, '_wdt'))) {
                continue;
            }

            $methods = $route->getMethods();
            $hasSafe = empty($methods) || !empty(array_intersect(['GET','HEAD','OPTIONS'], $methods));
            if (!$hasSafe) continue;

            $path = $route->getPath();
            $params = $this->extractParams($path);
            $paramValues = $this->paramsForRoute($name, $params);

            $url = null;
            $canGenerate = true;

            try {
                // Generate PATH only; we’ll prepend our own base (more reliable in CLI/proxy setups)
                $absPath = $this->urlGen->generate($name, $paramValues ?: [], UrlGeneratorInterface::ABSOLUTE_PATH);
                $url = $this->getProbeBase() . $absPath;
            } catch (\Throwable) {
                $canGenerate = false;
            }

            $rows[] = [
                'name'        => $name,
                'path'        => $path,
                'methods'     => $methods,
                'hasSafe'     => $hasSafe,
                'params'      => $params,
                'paramValues' => $paramValues,
                'canGenerate' => $canGenerate,
                'url'         => $url,
            ];
        }

        usort($rows, fn($a,$b) => strcmp($a['path'], $b['path']));
        return [
            'flat' => $rows,
            'tree' => $this->toTree($rows),
        ];
    }

    /** Build a nested tree by path segments */
    private function toTree(array $rows): array
    {
        $root = ['name' => '/', 'children' => [], 'routes' => []];
        foreach ($rows as $r) {
            $path = ltrim($r['path'], '/');
            if ($path === '') { $root['routes'][] = $r; continue; }
            $parts = explode('/', $path);
            $cursor = &$root;
            foreach ($parts as $seg) {
                if (!isset($cursor['children'][$seg])) {
                    $cursor['children'][$seg] = ['name' => $seg, 'children' => [], 'routes' => []];
                }
                $cursor = &$cursor['children'][$seg];
            }
            $cursor['routes'][] = $r;
            unset($cursor);
        }
        return $this->normalizeChildren($root);
    }

    private function normalizeChildren(array $node): array
    {
        $node['children'] = array_values(array_map(fn($c) => $this->normalizeChildren($c), $node['children']));
        usort($node['children'], fn($a,$b) => strcmp($a['name'],$b['name']));
        usort($node['routes'], fn($a,$b) => strcmp($a['path'],$b['path']));
        return $node;
    }

    private function extractParams(string $path): array
    {
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)(?:<[^>]+>)?\}/', $path, $m);
        return $m[1] ?? [];
    }

    private function paramsForRoute(string $routeName, array $params): array
    {
        if (!$params) return [];
        $values = [];
        foreach ($params as $p) {
            if (isset($this->sampleParamMap['byRoute'][$routeName][$p])) {
                $values[$p] = $this->sampleParamMap['byRoute'][$routeName][$p];
            } elseif (isset($this->sampleParamMap['byParam'][$p])) {
                $values[$p] = $this->sampleParamMap['byParam'][$p];
            } else {
                $values[$p] = $this->fallbackForParam($p);
            }
        }
        return $values;
    }

    private function fallbackForParam(string $p): string|int
    {
        $lp = strtolower($p);
        return match (true) {
            str_contains($lp, 'id')    => 1,
            str_contains($lp, 'page')  => 1,
            str_contains($lp, 'date')  => '2024-01-01',
            str_contains($lp, 'slug'),
            str_contains($lp, 'code'),
            str_contains($lp, 'name')  => 'test',
            default                    => 'sample',
        };
    }
}
