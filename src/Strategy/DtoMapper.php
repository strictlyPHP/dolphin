<?php

declare(strict_types=1);

namespace StrictlyPHP\Dolphin\Strategy;

use Exception;

class DtoMapper
{
    public function map(string $dtoClass, array $data): object
    {
        $refClass = new \ReflectionClass($dtoClass);
        $constructor = $refClass->getConstructor();

        if (! $constructor) {
            return new $dtoClass();
        }

        $args = [];

        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType();

            if ($type instanceof \ReflectionNamedType && ! $type->isBuiltin()) {
                // Recursive DTO
                $nestedClass = $type->getName();

                if (isset($data[$name]) && is_array($data[$name])) {
                    $args[] = $this->map($nestedClass, $data[$name]);
                } else {
                    throw new Exception(sprintf('parameter %s has no type', $name));
                }
            } else {
                $args[] = $data[$name] ?? null;
            }
        }

        return $refClass->newInstanceArgs($args);
    }
}
