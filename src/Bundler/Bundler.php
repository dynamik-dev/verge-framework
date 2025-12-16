<?php

declare(strict_types=1);

namespace Verge\Bundler;

use Closure;
use Verge\App;
use Verge\Routing\Route;
use Verge\Routing\RouterInterface;

/**
 * Build-time optimization tool that converts closure routes to handler classes.
 *
 * Usage:
 *   $app->configure(Bundler::class);
 *   $result = $app->make(Bundler::class)->build();
 *   echo $result->summary();
 *
 * With options:
 *   $app->configure(new Bundler(
 *       outputPath: __DIR__ . '/dist',
 *       namespace: 'App\\Handlers',
 *   ));
 */
class Bundler
{
    private ?App $app = null;
    private ClosureExtractor $extractor;
    private HandlerGenerator $generator;
    private RoutesGenerator $routesGenerator;

    public function __construct(
        private string $outputPath = 'dist',
        private string $namespace = 'App\\Handlers',
        private string $handlersDir = 'Handlers',
        private ?string $sourcePath = null,
        private bool $symlinkVendor = true,
    ) {
        $this->extractor = new ClosureExtractor();
        $this->generator = new HandlerGenerator($namespace);
        $this->routesGenerator = new RoutesGenerator();
    }

    /**
     * Invoked when passed to $app->configure().
     */
    public function __invoke(App $app): void
    {
        $this->app = $app;
        $app->instance(self::class, $this);
    }

    /**
     * Build the optimized distribution.
     */
    public function build(): BuildResult
    {
        if ($this->app === null) {
            throw new \RuntimeException(
                'Bundler must be configured via $app->configure() before building'
            );
        }

        $startTime = microtime(true);

        // Configure generator paths
        $handlersPath = $this->outputPath . '/' . $this->handlersDir;
        $this->generator->setOutputPath($handlersPath);
        $this->generator->setNamespace($this->namespace);

        // Get all routes
        $router = $this->app->container->resolve(RouterInterface::class);
        /** @var RouterInterface $router */
        $routes = $router->getRoutes();

        // Process closure routes
        $closuresConverted = 0;
        $filesGenerated = 0;
        $handlers = [];
        $skipped = [];
        $warnings = [];

        foreach ($routes as $method => $methodRoutes) {
            foreach ($methodRoutes as $route) {
                $handler = $route->handler;
                if (!($handler instanceof Closure)) {
                    continue;
                }

                $routeKey = "{$method} {$route->path}";

                try {
                    $result = $this->processClosureRoute($route);

                    if ($result === null) {
                        $closureInfo = $this->extractor->extract($handler);
                        $skipped[] = [
                            'route' => $routeKey,
                            'reason' => $closureInfo->getSkipReason() ?? 'Unknown',
                        ];
                        continue;
                    }

                    $handlers[$routeKey] = $result['className'];
                    $closuresConverted++;
                    $filesGenerated++;
                } catch (\Throwable $e) {
                    $warnings[] = "{$routeKey}: {$e->getMessage()}";
                }
            }
        }

        // Generate routes.php with handlers instead of closures
        $routesContent = $this->routesGenerator->generate($routes, $handlers);
        $routesFile = $this->outputPath . '/routes.php';
        $this->writeFile($routesFile, $routesContent);
        $filesGenerated++;

        // Generate bootstrap.php entry point
        $bootstrapContent = $this->generateBootstrap();
        $bootstrapFile = $this->outputPath . '/bootstrap.php';
        $this->writeFile($bootstrapFile, $bootstrapContent);
        $filesGenerated++;

        // Copy source files if sourcePath is set
        $filesCopied = 0;
        if ($this->sourcePath !== null) {
            $filesCopied = $this->copySourceFiles();
        }

        // Symlink or copy vendor
        if ($this->symlinkVendor && is_dir('vendor')) {
            $this->createVendorSymlink();
        }

        $duration = microtime(true) - $startTime;

        return new BuildResult(
            closuresConverted: $closuresConverted,
            filesGenerated: $filesGenerated,
            filesCopied: $filesCopied,
            handlers: $handlers,
            skipped: $skipped,
            warnings: $warnings,
            outputPath: realpath($this->outputPath) ?: $this->outputPath,
            duration: $duration,
        );
    }

    /**
     * Generate the bootstrap.php entry point.
     */
    private function generateBootstrap(): string
    {
        $handlersNamespace = $this->namespace;

        return <<<PHP
<?php

declare(strict_types=1);

/**
 * Generated bootstrap file for optimized production build.
 * Closure routes have been converted to handler classes.
 */

require_once __DIR__ . '/vendor/autoload.php';

use Verge\\App;

\$app = new App();

// Register autoloader for generated handlers
spl_autoload_register(function (string \$class) {
    \$prefix = '{$handlersNamespace}\\\\';
    if (strpos(\$class, \$prefix) === 0) {
        \$relativeClass = substr(\$class, strlen(\$prefix));
        \$file = __DIR__ . '/{$this->handlersDir}/' . \$relativeClass . '.php';
        if (file_exists(\$file)) {
            require_once \$file;
        }
    }
});

// Load generated routes
\$routes = require __DIR__ . '/routes.php';
\$routes(\$app);

return \$app;
PHP;
    }

    /**
     * Copy source files to the output directory.
     */
    private function copySourceFiles(): int
    {
        $count = 0;
        $srcDir = $this->outputPath . '/src';

        if (!is_dir($srcDir)) {
            mkdir($srcDir, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->sourcePath ?? '', \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $relativePath = substr($file->getPathname(), strlen($this->sourcePath ?? '') + 1);
            $targetPath = $srcDir . '/' . $relativePath;

            if ($file->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                copy($file->getPathname(), $targetPath);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Create a symlink to vendor directory.
     */
    private function createVendorSymlink(): void
    {
        $vendorLink = $this->outputPath . '/vendor';
        $vendorTarget = getcwd() . '/vendor';

        if (file_exists($vendorLink)) {
            if (is_link($vendorLink)) {
                unlink($vendorLink);
            } else {
                return; // Don't overwrite if it's a real directory
            }
        }

        symlink($vendorTarget, $vendorLink);
    }

    /**
     * Write content to a file, creating directories as needed.
     */
    private function writeFile(string $path, string $content): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $content);
    }

    /**
     * Process a single closure route.
     *
     * @return array{className: string, filePath: string}|null
     */
    private function processClosureRoute(Route $route): ?array
    {
        $handler = $route->handler;
        if (!($handler instanceof Closure)) {
            return null;
        }
        $closureInfo = $this->extractor->extract($handler);

        if (!$closureInfo->isConvertible()) {
            return null;
        }

        // Generate class name from route
        $className = $this->generator->generateClassName($route->methods[0], $route->path);

        // Generate class content
        $content = $this->generator->generate($className, $closureInfo);

        // Write the file
        $filePath = $this->generator->write($className, $content);

        return [
            'className' => $this->generator->getFullyQualifiedClassName($className),
            'filePath' => $filePath,
        ];
    }

    /**
     * Get the output path.
     */
    public function getOutputPath(): string
    {
        return $this->outputPath;
    }

    /**
     * Get the handlers namespace.
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * Analyze routes without building (dry run).
     * @return array{total_routes: int, closure_routes: int, convertible: int, non_convertible: int, routes: array<string, mixed>}
     */
    public function analyze(): array
    {
        if ($this->app === null) {
            throw new \RuntimeException(
                'Bundler must be configured via $app->configure() before analyzing'
            );
        }

        $router = $this->app->container->resolve(RouterInterface::class);
        /** @var RouterInterface $router */
        $routes = $router->getRoutes();

        $analysis = [
            'total_routes' => 0,
            'closure_routes' => 0,
            'convertible' => 0,
            'non_convertible' => 0,
            'routes' => [],
        ];

        foreach ($routes as $method => $methodRoutes) {
            foreach ($methodRoutes as $route) {
                $analysis['total_routes']++;
                $routeKey = "{$method} {$route->path}";
                $handler = $route->handler;

                if (!($handler instanceof Closure)) {
                    $analysis['routes'][$routeKey] = [
                        'type' => 'class',
                        'handler' => is_array($handler)
                            ? implode('::', $handler)
                            : $handler,
                    ];
                    continue;
                }

                $analysis['closure_routes']++;

                try {
                    $closureInfo = $this->extractor->extract($handler);

                    if ($closureInfo->isConvertible()) {
                        $analysis['convertible']++;
                        $analysis['routes'][$routeKey] = [
                            'type' => 'closure',
                            'convertible' => true,
                            'target_class' => $this->generator->generateClassName($method, $route->path),
                            'has_uses' => $closureInfo->hasUses(),
                            'is_arrow' => $closureInfo->isArrowFunction,
                        ];
                    } else {
                        $analysis['non_convertible']++;
                        $analysis['routes'][$routeKey] = [
                            'type' => 'closure',
                            'convertible' => false,
                            'reason' => $closureInfo->getSkipReason(),
                        ];
                    }
                } catch (\Throwable $e) {
                    $analysis['routes'][$routeKey] = [
                        'type' => 'closure',
                        'convertible' => false,
                        'reason' => $e->getMessage(),
                    ];
                }
            }
        }

        return $analysis;
    }
}
