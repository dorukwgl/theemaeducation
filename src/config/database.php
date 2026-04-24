<?php

namespace EMA\Config;

use mysqli;
use Exception;
use EMA\Utils\Logger;

class Database
{
    private static ?mysqli $connection = null;
    private static int $connectionCount = 0;
    private static array $config = [];

    public static function initialize(): void
    {
        if (empty(self::$config)) {
            self::$config = [
                'host' => Config::get('database.host'),
                'port' => Config::get('database.port'),
                'user' => Config::get('database.user'),
                'password' => Config::get('database.password'),
                'database' => Config::get('database.name'),
                'charset' => Config::get('database.charset'),
            ];
        }
    }

    public static function getConnection(): mysqli
    {
        self::initialize();

        if (self::$connection !== null && self::isConnectionAlive(self::$connection)) {
            self::$connectionCount++;
            return self::$connection;
        }

        try {
            self::$connection = new mysqli(
                self::$config['host'],
                self::$config['user'],
                self::$config['password'],
                self::$config['database'],
                self::$config['port']
            );

            if (self::$connection->connect_error) {
                throw new Exception("Database connection failed: " . self::$connection->connect_error);
            }

            self::$connection->set_charset(self::$config['charset']);
            self::$connectionCount++;

            return self::$connection;
        } catch (Exception $e) {
            Logger::error("Database connection error", [
                'error' => $e->getMessage(),
                'host' => self::$config['host'],
                'database' => self::$config['database']
            ]);
            throw $e;
        }
    }

    public static function prepare(string $query): ?\mysqli_stmt
    {
        try {
            $conn = self::getConnection();
            $stmt = $conn->prepare($query);

            if (!$stmt) {
                throw new Exception("Failed to prepare statement: " . $conn->error);
            }

            return $stmt;
        } catch (Exception $e) {
            Logger::error("Database prepare error", [
                'error' => $e->getMessage(),
                'query' => $query
            ]);
            throw $e;
        }
    }

    public static function query(string $query): ?\mysqli_result
    {
        try {
            $conn = self::getConnection();
            $result = $conn->query($query);

            if (!$result) {
                throw new Exception("Query failed: " . $conn->error);
            }

            return $result;
        } catch (Exception $e) {
            Logger::error("Database query error", [
                'error' => $e->getMessage(),
                'query' => $query
            ]);
            throw $e;
        }
    }

    public static function beginTransaction(): bool
    {
        $conn = self::getConnection();
        return $conn->begin_transaction();
    }

    public static function commit(): bool
    {
        $conn = self::getConnection();
        return $conn->commit();
    }

    public static function rollback(): bool
    {
        $conn = self::getConnection();
        return $conn->rollback();
    }

    public static function lastInsertId(): int
    {
        $conn = self::getConnection();
        return $conn->insert_id;
    }

    public static function escape(string $string): string
    {
        $conn = self::getConnection();
        return $conn->real_escape_string($string);
    }

    public static function close(): void
    {
        if (self::$connection !== null) {
            self::$connection->close();
            self::$connection = null;
        }
    }

    public static function getConnectionCount(): int
    {
        return self::$connectionCount;
    }

    private static function isConnectionAlive(\mysqli $conn): bool
    {
        try {
            $result = $conn->query('SELECT 1');
            if ($result === false || $conn->errno === 2006) {
                return false;
            }
            if ($result) {
                $result->free();
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function resetConnectionCount(): void
    {
        self::$connectionCount = 0;
    }
}
