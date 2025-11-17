<?php

declare(strict_types=1);

namespace StrictlyPHP\Dolphin\Strategy;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\ParserFactory;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use StrictlyPHP\Dolphin\Strategy\Exception\DtoMapperException;

class DtoMapper
{
    /**
     * @var array<string, array<string,string>>
     */
    private array $importMapCache = [];

    /**
     * @param array<mixed, mixed> $data
     */
    public function map(string $dtoClass, array $data): object
    {
        $refClass = new ReflectionClass($dtoClass);
        $constructor = $refClass->getConstructor();

        if (! $constructor) {
            return new $dtoClass();
        }

        $args = [];

        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType();

            $hasValue = array_key_exists($name, $data);
            $raw = $hasValue ? $data[$name] : null;

            // Handle nullables
            if ($raw === null) {
                if ($type instanceof ReflectionNamedType && $type->allowsNull()) {
                    $args[] = null;
                    continue;
                }

                // Non-nullable -> error
                throw new DtoMapperException(
                    sprintf("Missing non-nullable parameter '%s'", $name)
                );
            }

            // Array type (possibly nullable)
            if ($this->isArrayParam($param)) {
                try {
                    $elementClass = $this->resolveArrayDocblockType($param);
                    $allowsNull = $this->arrayAllowsNullElements($param);
                    $args[] = $this->mapArrayOfType($elementClass, $raw, $allowsNull);
                } catch (DtoMapperException $e) {
                    // No element type declared, treat as plain array
                    $args[] = $raw;
                }
                continue;
            }


            // Object type (DTO / enum / value object)
            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                $className = $type->getName();
                $args[] = $this->mapSingleValue($className, $raw);
                continue;
            }

            // Scalar
            $args[] = $raw;
        }

        return $refClass->newInstanceArgs($args);
    }

    // ---------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------

    private function isArrayParam(ReflectionParameter $param): bool
    {
        $type = $param->getType();
        return $type instanceof ReflectionNamedType && $type->getName() === 'array';
    }

    private function resolveArrayDocblockType(ReflectionParameter $param): string
    {
        $rawDoc = $param->getDeclaringFunction()->getDocComment() ?: '';
        $paramName = $param->getName();

        // collapse whitespace
        $doc = preg_replace('/\s+/', ' ', $rawDoc);

        if (
            preg_match(
                '/@param\s+array\s*<\s*(?:[\w\\\\]+\s*,\s*)?(\??[\w\\\\]+)\s*>\s+\$' .
                preg_quote($paramName, '/') .
                '/i',
                $doc,
                $m
            )
        ) {
            $type = ltrim($m[1], '?');

            // primitive?
            if (in_array(strtolower($type), ['string', 'int', 'float', 'bool'], true)) {
                return $type;
            }

            // Try to resolve class name via imports
            $fqcn = $this->resolveClassNameFromImports($param, $type);
            return $fqcn;
        }

        throw new DtoMapperException("Cannot determine array element type for parameter $paramName");
    }

    private function resolveClassNameFromImports(ReflectionParameter $param, string $shortName): string
    {
        $dtoClass = $param->getDeclaringClass()->getName();
        $importMap = $this->getImportMapForDto($dtoClass);

        // If the short name matches an import alias, return that
        if (isset($importMap[$shortName])) {
            return $importMap[$shortName];
        }

        // Otherwise fallback to namespace of the DTO
        $dtoNamespace = $param->getDeclaringClass()->getNamespaceName();
        $candidate = $dtoNamespace . '\\' . $shortName;

        if (class_exists($candidate)) {
            return $candidate;
        }

        throw new DtoMapperException("Cannot resolve class name '$shortName' for parameter {$param->getName()}");
    }

    /**
     * @return array<string, string> alias => fqcn
     */
    private function getImportMapForDto(string $dtoClass): array
    {
        if (isset($this->importMapCache[$dtoClass])) {
            return $this->importMapCache[$dtoClass];
        }

        $ref = new ReflectionClass($dtoClass);
        $file = $ref->getFileName();
        $imports = $this->parseUseStatementsFromFile($file);

        $this->importMapCache[$dtoClass] = $imports;
        return $imports;
    }

    /**
     * Parse a file and extract `use` statements (class imports) using PHP-Parser
     *
     * @return array<string, string> alias => fqcn (fully qualified class name)
     */
    private function parseUseStatementsFromFile(string $filePath): array
    {
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $code = file_get_contents($filePath);
        if ($code === false) {
            throw new DtoMapperException("Cannot read file for class imports: $filePath");
        }

        try {
            $ast = $parser->parse($code);
        } catch (Error $e) {
            throw new DtoMapperException("PHP parser error on $filePath: {$e->getMessage()}");
        }

        $imports = [];
        foreach ($ast as $node) {
            if ($node instanceof Node\Stmt\Namespace_) {
                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof Node\Stmt\Use_) {
                        foreach ($stmt->uses as $use) {
                            /** @var UseUse $use */
                            $alias = $use->alias ? $use->alias->name : $use->name->getLast();
                            $fqcn = $use->name->toString(); // fully qualified name without leading "\"
                            $imports[$alias] = $fqcn;
                        }
                    }
                }
                break; // after namespace, other uses of top-level use done
            } elseif ($node instanceof Node\Stmt\Use_) {
                // global namespace uses
                foreach ($node->uses as $use) {
                    /** @var UseUse $use */
                    $alias = $use->alias ? $use->alias->name : $use->name->getLast();
                    $fqcn = $use->name->toString();
                    $imports[$alias] = $fqcn;
                }
            }
        }

        return $imports;
    }

    private function arrayAllowsNullElements(ReflectionParameter $param): bool
    {
        $doc = $param->getDeclaringFunction()->getDocComment() ?: '';
        return preg_match('/array<\w+,\s*\?/', $doc) === 1;
    }

    /**
     * @param class-string $className
     */
    private function mapSingleValue(string $className, mixed $value): mixed
    {
        $ref = new ReflectionClass($className);

        // enum
        if ($ref->isEnum()) {
            return $className::from($value);
        }

        // nested DTO
        if (is_array($value)) {
            return $this->map($className, $value);
        }

        // primitive value object (e.g. Uuid)
        return $ref->newInstanceArgs([$value]);
    }

    /**
     * @param array<mixed> $values
     * @return array<mixed>
     */
    private function mapArrayOfType(string $elementClass, array $values, bool $nullableElements): array
    {
        $out = [];

        foreach ($values as $val) {
            if ($val === null) {
                if ($nullableElements) {
                    $out[] = null;
                    continue;
                }
                throw new DtoMapperException("Null array element not allowed for $elementClass");
            }

            // scalar types
            if (in_array($elementClass, ['string', 'int', 'float', 'bool'], true)) {
                $out[] = $val;
                continue;
            }

            // already object of correct type
            if (is_object($val) && $val instanceof $elementClass) {
                $out[] = $val;
                continue;
            }

            // array → recursively map DTO
            if (is_array($val)) {
                $out[] = $this->map($elementClass, $val);
                continue;
            }

            // single-value constructor objects (e.g., EmailAddress)
            $ref = new \ReflectionClass($elementClass);
            $out[] = $ref->newInstanceArgs([$val]);
        }

        return $out;
    }
}
