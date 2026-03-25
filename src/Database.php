<?php
namespace App;

use PDO;
use PDOException;

class Database
{
    private array $configs;
    private array $connections = [];

    public function __construct(array $configs)
    {
        // $configs is the 'connections' array from config/config.php
        $this->configs = $configs;
    }

    public function getDefaultName(): string
    {
        return $this->configs['__default'] ?? array_key_first($this->configs);
    }

    public function getDefault(): PDO
    {
        return $this->getConnection($this->getDefaultName());
    }

    public function getConnection(string $name): PDO
    {
        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }
        if (!isset($this->configs[$name])) {
            throw new \InvalidArgumentException("Unknown DB connection: {$name}");
        }
        $cfg = $this->configs[$name];
        $options = $cfg['options'] ?? [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        try {
            $pdo = new PDO($cfg['dsn'], $cfg['user'] ?? null, $cfg['pass'] ?? null, $options);
            $this->connections[$name] = $pdo;
            return $pdo;
        } catch (PDOException $e) {
            throw $e;
        }
    }
}
