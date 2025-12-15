<?php

declare(strict_types=1);

namespace Verge\Bundler;

/**
 * Generates invokable handler classes from closure information.
 */
class HandlerGenerator
{
    public function __construct(
        private string $namespace = 'App\\Handlers',
        private string $outputPath = 'dist/Handlers',
    ) {
    }

    /**
     * Generate a handler class name from HTTP method and path.
     *
     * Examples:
     *   GET /           -> GetIndexHandler
     *   GET /users      -> GetUsersHandler
     *   GET /users/{id} -> GetUsersIdHandler
     *   POST /users     -> PostUsersHandler
     */
    public function generateClassName(string $method, string $path): string
    {
        // Capitalize HTTP method
        $name = ucfirst(strtolower($method));

        // Handle root path
        if ($path === '/' || $path === '') {
            return $name . 'IndexHandler';
        }

        // Split path and process segments
        $segments = explode('/', trim($path, '/'));

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            // Handle route parameters: {id} -> Id
            if (preg_match('/^\{(\w+)\}$/', $segment, $matches)) {
                $name .= ucfirst($matches[1]);
            }
            // Handle parameters with constraints: {id:\d+} -> Id
            elseif (preg_match('/^\{(\w+):/', $segment, $matches)) {
                $name .= ucfirst($matches[1]);
            }
            // Regular segment
            else {
                // Convert kebab-case/snake_case to PascalCase
                $segment = str_replace(['-', '_'], ' ', $segment);
                $segment = ucwords($segment);
                $segment = str_replace(' ', '', $segment);
                $name .= $segment;
            }
        }

        return $name . 'Handler';
    }

    /**
     * Generate the handler class content.
     */
    public function generate(string $className, ClosureInfo $closureInfo): string
    {
        $useStatements = $this->buildUseStatements($closureInfo);
        $parameters = $this->buildParameterList($closureInfo);
        $returnType = $closureInfo->returnType ? ': ' . $closureInfo->returnType : '';
        $body = $this->buildMethodBody($closureInfo);

        $constructor = '';
        $properties = '';

        // If closure has 'use' variables, create constructor injection
        if ($closureInfo->hasUses()) {
            [$properties, $constructor] = $this->buildConstructorInjection($closureInfo);
        }

        $classContent = <<<PHP
<?php

declare(strict_types=1);

namespace {$this->namespace};

{$useStatements}
class {$className}
{
{$properties}{$constructor}    public function __invoke({$parameters}){$returnType}
    {
        {$body}
    }
}

PHP;

        return $classContent;
    }

    /**
     * Write the handler class to a file.
     */
    public function write(string $className, string $content): string
    {
        $directory = $this->outputPath;

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filePath = $directory . '/' . $className . '.php';
        file_put_contents($filePath, $content);

        return $filePath;
    }

    /**
     * Get the fully qualified class name.
     */
    public function getFullyQualifiedClassName(string $className): string
    {
        return $this->namespace . '\\' . $className;
    }

    /**
     * Build use statements for type-hinted classes.
     */
    private function buildUseStatements(ClosureInfo $closureInfo): string
    {
        $uses = [];

        foreach ($closureInfo->parameters as $param) {
            if ($param->type !== null && !$param->isBuiltin) {
                // Skip if type is already in our namespace
                if (!str_starts_with($param->type, $this->namespace . '\\')) {
                    $uses[$param->type] = true;
                }
            }
        }

        if (empty($uses)) {
            return '';
        }

        $statements = array_map(
            fn ($class) => "use {$class};",
            array_keys($uses)
        );

        return implode("\n", $statements) . "\n\n";
    }

    /**
     * Build the parameter list for the __invoke method.
     */
    private function buildParameterList(ClosureInfo $closureInfo): string
    {
        $params = [];

        foreach ($closureInfo->parameters as $param) {
            $params[] = $param->toDeclaration();
        }

        return implode(', ', $params);
    }

    /**
     * Build the method body.
     */
    private function buildMethodBody(ClosureInfo $closureInfo): string
    {
        $body = $closureInfo->body;

        // For arrow functions, wrap in return statement
        if ($closureInfo->isArrowFunction) {
            return 'return ' . $body . ';';
        }

        return $body;
    }

    /**
     * Build constructor injection for captured 'use' variables.
     *
     * @return array{string, string} [properties, constructor]
     */
    private function buildConstructorInjection(ClosureInfo $closureInfo): array
    {
        $properties = [];
        $constructorParams = [];
        $assignments = [];

        foreach ($closureInfo->uses as $name => $value) {
            $type = $this->inferType($value);
            $typeHint = $type ? "{$type} " : '';

            $properties[] = "    private {$typeHint}\${$name};";
            $constructorParams[] = "{$typeHint}\${$name}";
            $assignments[] = "        \$this->{$name} = \${$name};";
        }

        $propertiesStr = implode("\n", $properties) . "\n\n";

        $constructorStr = "    public function __construct(" . implode(', ', $constructorParams) . ")\n";
        $constructorStr .= "    {\n";
        $constructorStr .= implode("\n", $assignments) . "\n";
        $constructorStr .= "    }\n\n";

        return [$propertiesStr, $constructorStr];
    }

    /**
     * Infer the type of a value.
     */
    private function inferType(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return 'string';
        }

        if (is_int($value)) {
            return 'int';
        }

        if (is_float($value)) {
            return 'float';
        }

        if (is_bool($value)) {
            return 'bool';
        }

        if (is_array($value)) {
            return 'array';
        }

        if (is_object($value)) {
            return get_class($value);
        }

        return null;
    }

    /**
     * Set the output path.
     */
    public function setOutputPath(string $path): self
    {
        $this->outputPath = $path;
        return $this;
    }

    /**
     * Set the namespace.
     */
    public function setNamespace(string $namespace): self
    {
        $this->namespace = $namespace;
        return $this;
    }
}
