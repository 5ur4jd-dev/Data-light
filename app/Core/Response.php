<?php

declare(strict_types=1);

namespace App\Core;

/**
 * data-light HTTP Response Handler
 *
 * @author Suraj Dubey
 * @link https://mrsuraj.rf.gd
 * @link https://github.com/5ur4jd-dev
 */
class Response
{
    public static function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(array $data = [], string $message = 'Success'): void
    {
        self::json([
            'success' => true,
            'message' => $message,
            ...$data
        ], 200);
    }

    public static function error(string $message, int $statusCode = 400, array $extra = []): void
    {
        self::json([
            'success' => false,
            'message' => $message,
            ...$extra
        ], $statusCode);
    }

    public static function notFound(string $message = 'Resource not found'): void
    {
        self::error($message, 404);
    }

    public static function unauthorized(string $message = 'Unauthorized'): void
    {
        self::error($message, 401);
    }

    public static function serverError(string $message = 'Internal server error'): void
    {
        self::error($message, 500);
    }
}

