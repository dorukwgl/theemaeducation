<?php

namespace EMA\Utils;

use EMA\Utils\Logger;

/**
 * Pagination - Standardized pagination utility
 * Ensures consistent pagination implementation across all controllers
 */
class Pagination
{
    /**
     * Calculate OFFSET value for SQL queries
     * @param int $page Current page number (1-based)
     * @param int $perPage Items per page
     * @return int OFFSET value
     */
    public static function getOffset(int $page, int $perPage): int
    {
        return ($page - 1) * $perPage;
    }

    /**
     * Calculate total number of pages
     * @param int $total Total number of items
     * @param int $perPage Items per page
     * @return int Total pages
     */
    public static function calculateTotalPages(int $total, int $perPage): int
    {
        if ($total === 0 || $perPage === 0) {
            return 1;
        }
        return (int) ceil($total / $perPage);
    }

    /**
     * Validate and sanitize pagination parameters
     * @param int $page Page number (passed by reference)
     * @param int $perPage Items per page (passed by reference)
     * @param int $maxPerPage Maximum allowed items per page
     * @param int $minPerPage Minimum allowed items per page
     */
    public static function validate(int &$page, int &$perPage, int $maxPerPage = 100, int $minPerPage = 1): void
    {
        // Validate page number
        if (!is_numeric($page) || $page < 1) {
            $page = 1;
            Logger::warning('Invalid page number, using default', ['provided' => $page, 'default' => 1]);
        } else {
            $page = (int) $page;
        }

        // Validate per page
        if (!is_numeric($perPage) || $perPage < $minPerPage) {
            $perPage = 20; // Default
            Logger::warning('Invalid per_page value, using default', ['provided' => $perPage, 'default' => 20]);
        } elseif ($perPage > $maxPerPage) {
            $perPage = $maxPerPage;
            Logger::warning('per_page exceeds maximum, using max', ['provided' => $perPage, 'max' => $maxPerPage]);
        } else {
            $perPage = (int) $perPage;
        }
    }

    /**
     * Generate LIMIT clause for SQL queries
     * @param int $page Current page number
     * @param int $perPage Items per page
     * @return string SQL LIMIT clause
     */
    public static function getLimitClause(int $page, int $perPage): string
    {
        $offset = self::getOffset($page, $perPage);
        return "LIMIT {$perPage} OFFSET {$offset}";
    }

    /**
     * Generate pagination metadata for API responses
     * @param int $page Current page number
     * @param int $perPage Items per page
     * @param int $total Total number of items
     * @return array Pagination metadata
     */
    public static function getMetadata(int $page, int $perPage, int $total): array
    {
        $totalPages = self::calculateTotalPages($total, $perPage);
        $hasNextPage = $page < $totalPages;
        $hasPrevPage = $page > 1;

        return [
            'page' => $page,
            'per_page' => $perPage,
            'total_items' => $total,
            'total_pages' => $totalPages,
            'has_next_page' => $hasNextPage,
            'has_prev_page' => $hasPrevPage,
            'next_page' => $hasNextPage ? $page + 1 : null,
            'prev_page' => $hasPrevPage ? $page - 1 : null,
            'is_first_page' => $page === 1,
            'is_last_page' => $page === $totalPages
        ];
    }

    /**
     * Extract pagination parameters from request
     * @param mixed $request Request object or array
     * @param int $defaultPage Default page number
     * @param int $defaultPerPage Default items per page
     * @return array Page and per_page values
     */
    public static function extractFromRequest($request, int $defaultPage = 1, int $defaultPerPage = 20): array
    {
        $page = $defaultPage;
        $perPage = $defaultPerPage;

        // Handle both Request object and array input
        if (is_object($request) && method_exists($request, 'query')) {
            $page = (int) $request->query('page', $defaultPage);
            $perPage = (int) $request->query('per_page', $defaultPerPage);
        } elseif (is_array($request)) {
            $page = (int) ($request['page'] ?? $defaultPage);
            $perPage = (int) ($request['per_page'] ?? $defaultPerPage);
        } elseif (is_object($request) && method_exists($request, 'get')) {
            $page = (int) $request->get('page', $defaultPage);
            $perPage = (int) $request->get('per_page', $defaultPerPage);
        }

        self::validate($page, $perPage);

        return [
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    /**
     * Apply pagination to a prepared statement
     * @param mixed $stmt Prepared statement
     * @param int $page Current page number
     * @param int $perPage Items per page
     * @return void
     */
    public static function applyToStatement($stmt, int $page, int $perPage): void
    {
        $offset = self::getOffset($page, $perPage);
        $stmt->bind_param('ii', $perPage, $offset);
    }
}