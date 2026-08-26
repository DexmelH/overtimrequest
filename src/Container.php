<?php
declare(strict_types=1);

namespace App;

use InvalidArgumentException;

/**
 * Minimal service locator: explicit factories, memoized per request.
 * No autowiring, no reflection, no scopes.
 */
class Container
{
    /** @var array<string, callable(self): mixed> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @param callable(self): mixed $factory */
    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]) || array_key_exists($id, $this->instances);
    }

    /** @return mixed */
    public function get(string $id)
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (!isset($this->factories[$id])) {
            throw new InvalidArgumentException("Unknown service: {$id}");
        }

        return $this->instances[$id] = ($this->factories[$id])($this);
    }
}
