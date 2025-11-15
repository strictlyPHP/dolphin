<?php

declare(strict_types=1);

namespace StrictlyPHP\Dolphin\Strategy;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use StrictlyPHP\Dolphin\Strategy\Exception\DtoMapperException;

class DtoMapper
{
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
        $doc = $param->getDeclaringFunction()->getDocComment() ?: '';
        $paramName = $param->getName();
        $dtoNamespace = $param->getDeclaringClass()->getNamespaceName();

        if (preg_match('/@param\s+array(?:<[\w\\\]+\s*,\s*)?(\??[\w\\\]+)>\s+\$' . preg_quote($paramName, '/') . '/i', $doc, $m)) {
            $class = ltrim($m[1], '?');

            // Don't prepend namespace for primitive types
            if (! in_array(strtolower($class), ['string', 'int', 'float', 'bool'], true) && $class[0] !== '\\') {
                $class = $dtoNamespace . '\\' . $class;
            }

            return $class;
        }

        throw new DtoMapperException(
            sprintf('Cannot determine array element type for parameter %s', $paramName)
        );
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
