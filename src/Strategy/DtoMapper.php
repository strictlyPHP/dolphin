<?php

declare(strict_types=1);

namespace StrictlyPHP\Dolphin\Strategy;

use Exception;
use StrictlyPHP\Dolphin\Strategy\Exception\DtoMapperException;

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
                if (isset($data[$name])) {
                    if (is_array($data[$name])) {
                        $args[] = $this->map($nestedClass, $data[$name]);
                    } else {
                        $refNestedClass = new \ReflectionClass($nestedClass);
                        $args[] = $refNestedClass->newInstanceArgs([$data[$name]]);
                    }
                } else {
                    throw new DtoMapperException(
                        sprintf(
                            'parameter %s of type %s cannot be mapped data is %s',
                            $name,
                            $nestedClass,
                            json_encode($data)
                        )
                    );
                }
            } else {
                $args[] = $data[$name] ?? null;
            }
        }

        return $refClass->newInstanceArgs($args);
    }
}
