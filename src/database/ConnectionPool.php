<?php

namespace EMA\Database;

use EMA\Utils\Logger;

/**
 * Connection Pool - Database connection pooling for performance optimization
 * Reuses connections instead of creating new ones for each query
 */
class ConnectionPool
{
    private static array $pool = [];
    private static array $inUse = [];
    private const MAX_POOL_SIZE = 10;
    private const CONNECTION_TIMEOUT = 30; // seconds

    /**
     * Get a connection from the pool
     * @return mysqli Database connection
     */
    public static function getConnection(): \mysqli
    {
        try {
            // Check for available connections in pool
            foreach (self::$pool as $key => $conn) {
                if (!isset(self::$inUse[$key]) && self::isConnectionAlive($conn)) {
                    self::$inUse[$key] = true;
                    Logger::debug('Reusing pooled connection', ['pool_key' => $key]);
                    return $conn;
                }
            }

            // Create new connection if pool not full
            if (count(self::$pool) < self::MAX_POOL_SIZE) {
                $conn = self::createNewConnection();
                $key = count(self::$pool);
                self::$pool[] = $conn;
                self::$inUse[$key] = true;
                Logger::debug('Created new pooled connection', ['pool_key' => $key, 'total_connections' => count(self::$pool)]);
                return $conn;
            }

            // Pool full, create non-pooled connection
            Logger::warning('Connection pool full, creating non-pooled connection');
            return self::createNewConnection();
        } catch (\Exception $e) {
            Logger::error('Failed to get connection from pool', [
                'error' => $e->getMessage(),
                'pool_size' => count(self::$pool),
                'in_use' => count(self::$inUse)
            ]);
            throw $e;
        }
    }

    /**
     * Release a connection back to the pool
     * @param mysqli $conn Connection to release
     */
    public static function releaseConnection(\mysqli $conn): void
    {
        $key = array_search($conn, self::$pool, true);
        if ($key !== false) {
            unset(self::$inUse[$key]);
            Logger::debug('Released connection back to pool', ['pool_key' => $key]);
        }
    }

    /**
     * Create a new database connection
     * @return mysqli New database connection
     */
    private static function createNewConnection(): \mysqli
    {
        $config = require ROOT_PATH . '/src/config/database.php';
        $conn = new \mysqli(
            $config['host'],
            $config['username'],
            $config['password'],
            $config['database'],
            $config['port'] ?? 3306
        );

        if ($conn->connect_error) {
            Logger::error('Database connection failed', ['error' => $conn->connect_error]);
            throw new \Exception('Database connection failed: ' . $conn->connect_error);
        }

        $conn->set_charset('utf8mb4');
        $conn->autocommit(true);
        return $conn;
    }

    /**
     * Close all connections in the pool
     * Should be called on application shutdown
     */
    public static function closeAll(): void
    {
        foreach (self::$pool as $key => $conn) {
            if ($conn instanceof \mysqli && !$conn->connect_error) {
                $conn->close();
            }
        }
        self::$pool = [];
        self::$inUse = [];
        Logger::info('Closed all pooled connections');
    }

    /**
     * Get pool statistics for monitoring
     * @return array Pool statistics
     */
    public static function getStats(): array
    {
        return [
            'total_connections' => count(self::$pool),
            'in_use_connections' => count(self::$inUse),
            'available_connections' => count(self::$pool) - count(self::$inUse),
            'max_pool_size' => self::MAX_POOL_SIZE,
            'pool_utilization' => self::$pool > 0
                ? round((count(self::$inUse) / count(self::$pool)) * 100, 2)
                : 0
        ];
    }

    /**
     * Check if a connection is alive
     * @param mysqli $conn Connection to check
     * @return bool True if connection is alive, false otherwise
     */
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

    /**
     * Clean up dead connections from pool
     */
    public static function cleanup(): void
    {
        foreach (self::$pool as $key => $conn) {
            if (!self::isConnectionAlive($conn)) {
                Logger::warning('Removing dead connection from pool', ['pool_key' => $key]);
                unset(self::$pool[$key]);
                unset(self::$inUse[$key]);
            }
        }
    }
}