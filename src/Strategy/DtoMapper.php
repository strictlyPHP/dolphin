<?php

declare(strict_types=1);

namespace StrictlyPHP\Dolphin\Strategy;

use PhpParser\Lexer\Emulative;
use PhpParser\Node;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\Parser\Php8;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use StrictlyPHP\Dolphin\Strategy\Exception\ArrayTypeNotDeclaredException;
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
                if ($type !== null && $type->allowsNull()) {
                    $args[] = null;
                    continue;
                }

                // Non-nullable -> error
                throw new DtoMapperException(
                    sprintf("Missing non-nullable parameter '%s'", $name)
                );
            }

            // Union type
            if ($type instanceof ReflectionUnionType) {
                $args[] = $this->resolveUnionType($type, $raw);
                continue;
            }

            // Array type (possibly nullable)
            if ($this->isArrayParam($param)) {
                try {
                    $elementClass = $this->resolveArrayDocblockType($param);
                    $allowsNull = $this->arrayAllowsNullElements($param);
                    $args[] = $this->mapArrayOfType($elementClass, $raw, $allowsNull);
                } catch (ArrayTypeNotDeclaredException $e) {
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

        $pattern =
            '/@param\s+array\s*<\s*' .
            '(?:[\w\\\\]+\s*,\s*)?' .      // optional key type: int,
            '(\??[\w\\\\]+)\s*' .          // capture value type: ?Geography
            '>\s*' .
            '(?:\|\s*null)?\s*' .          // allow trailing union: |null
            '\$' . preg_quote($paramName, '/') .
            '\b/i';

        if (preg_match($pattern, $doc, $m)) {
            $type = ltrim($m[1], '?');

            // primitive?
            if (in_array(strtolower($type), ['string', 'int', 'float', 'bool'], true)) {
                return $type;
            }

            // Try to resolve class name via imports
            $fqcn = $this->resolveClassNameFromImports($param, $type);
            return $fqcn;
        }

        throw new ArrayTypeNotDeclaredException("Cannot determine array element type for parameter $paramName");
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
        $code = file_get_contents($filePath);
        if ($code === false) {
            throw new DtoMapperException("Cannot read file for class imports: $filePath");
        }

        $parser = new Php8(new Emulative());

        try {
            $ast = $parser->parse($code);
        } catch (\PhpParser\Error $e) {
            throw new DtoMapperException("PHP parser error on $filePath: {$e->getMessage()}");
        }

        $imports = [];

        if (! is_array($ast)) {
            return $imports;
        }

        foreach ($ast as $node) {
            // If file has a namespace
            if ($node instanceof Node\Stmt\Namespace_) {
                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof Node\Stmt\Use_) {
                        foreach ($stmt->uses as $use) {
                            /** @var UseUse $use */
                            $alias = $use->alias ? $use->alias->name : $use->name->getLast();
                            $fqcn = $use->name->toString(); // fully qualified class name
                            $imports[$alias] = $fqcn;
                        }
                    }
                }
                break; // only process the first namespace
            }

            // Global namespace use statements
            if ($node instanceof Node\Stmt\Use_) {
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
            return $this->newEnum($className, $value);
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

            // Handle enums
            if ($ref->isEnum()) {
                $out[] = $this->newEnum($elementClass, $val);
            } else {
                // Non-enum single-value constructor objects
                $out[] = $ref->newInstanceArgs([$val]);
            }
        }
        return $out;
    }

    private function resolveUnionType(ReflectionUnionType $union, mixed $raw): mixed
    {
        $types = $union->getTypes();

        // 1. Backed enum — try each enum type via tryFrom()
        if (is_scalar($raw)) {
            foreach ($types as $t) {
                if (! $t instanceof ReflectionNamedType || $t->isBuiltin()) {
                    continue;
                }
                $className = $t->getName();
                if (! enum_exists($className)) {
                    continue;
                }
                $ref = new \ReflectionEnum($className);
                if ($ref->isBacked()) {
                    /** @var class-string<\BackedEnum> $className */
                    $result = $className::tryFrom($raw);
                    if ($result !== null) {
                        return $result;
                    }
                }
            }
        }

        // 2. Nested DTO — if value is array, try each non-enum class via map()
        if (is_array($raw)) {
            foreach ($types as $t) {
                if (! $t instanceof ReflectionNamedType || $t->isBuiltin()) {
                    continue;
                }
                $className = $t->getName();
                if (enum_exists($className)) {
                    continue;
                }
                if (class_exists($className)) {
                    try {
                        return $this->map($className, $raw);
                    } catch (\Throwable) {
                        continue;
                    }
                }
            }
        }

        // 3. Value object — if value is scalar, try each non-enum class via constructor
        if (is_scalar($raw)) {
            foreach ($types as $t) {
                if (! $t instanceof ReflectionNamedType || $t->isBuiltin()) {
                    continue;
                }
                $className = $t->getName();
                if (enum_exists($className)) {
                    continue;
                }
                if (class_exists($className)) {
                    try {
                        return (new ReflectionClass($className))->newInstanceArgs([$raw]);
                    } catch (\Throwable) {
                        continue;
                    }
                }
            }
        }

        // 4. Scalar fallback — if value matches a builtin type in the union
        if (is_scalar($raw)) {
            foreach ($types as $t) {
                if ($t instanceof ReflectionNamedType && $t->isBuiltin() && $this->scalarMatchesBuiltin($raw, $t->getName())) {
                    return $raw;
                }
            }
        }

        // 5. Array fallback
        if (is_array($raw)) {
            foreach ($types as $t) {
                if ($t instanceof ReflectionNamedType && $t->isBuiltin() && $t->getName() === 'array') {
                    return $raw;
                }
            }
        }

        throw new DtoMapperException(
            sprintf('Could not resolve union type for value of type "%s"', get_debug_type($raw))
        );
    }

    private function scalarMatchesBuiltin(mixed $value, string $typeName): bool
    {
        return match ($typeName) {
            'string' => is_string($value),
            'int' => is_int($value),
            'float' => is_float($value),
            'bool' => is_bool($value),
            default => false,
        };
    }

    /**
     * @param class-string $enumClass
     */
    private function newEnum(string $enumClass, mixed $value): \BackedEnum
    {
        $enumRef = new \ReflectionEnum($enumClass);

        if ($enumRef->isBacked()) {
            // For backed enums, use tryFrom for safe conversion
            if (($enum = $enumClass::tryFrom($value)) === null) {
                throw new DtoMapperException(sprintf(
                    'Could not map value "%s" to enum "%s"',
                    is_scalar($value) ? (string) $value : gettype($value),
                    $enumClass
                ));
            }
            return $enum;
        } else {
            throw new DtoMapperException(sprintf(
                'Could not map unit enum "%s". Unit enums are not allowed. consider turning it into a backed enum',
                $enumClass
            ));
        }
    }
}
