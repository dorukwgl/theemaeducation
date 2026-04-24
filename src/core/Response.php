<?php

namespace EMA\Core;

use EMA\Utils\Logger;

class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private $content;
    private string $contentType = 'application/json';

    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function setHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    public function setContentType(string $type): self
    {
        $this->contentType = $type;
        return $this->setHeader('Content-Type', $type);
    }

    public function setContent($content): self
    {
        $this->content = $content;
        return $this;
    }

    public function json($data, int $status = 200, array $headers = []): void
    {
        $this->setStatusCode($status);
        $this->setContentType('application/json');
        $this->setHeaders($headers);

        if (is_array($data) || is_object($data)) {
            $this->content = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $this->content = json_encode(['data' => $data]);
        }

        $this->send();
    }

    public function success($data = [], string $message = 'Success', int $status = 200): void
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if (!empty($data)) {
            $response['data'] = $data;
        }

        $this->json($response, $status);
    }

    public function error(string $message = 'Error', int $status = 500, $errors = null): void
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $this->parseErrorIntoReadable($errors);
        }

        $this->json($response, $status);
    }

    public function validationError(array $errors, string $message = 'Validation failed'): void
    {
        $this->error($message, 422, $errors);
    }

    public function notFound(string $message = 'Resource not found'): void
    {
        $this->error($message, 404);
    }

    public function unauthorized(string $message = 'Unauthorized'): void
    {
        $this->error($message, 401);
    }

    public function forbidden(string $message = 'Forbidden'): void
    {
        $this->error($message, 403);
    }

    public function badRequest(string $message = 'Bad request'): void
    {
        $this->error($message, 400);
    }

    public function created($data = [], string $message = 'Resource created'): void
    {
        $this->success($data, $message, 201);
    }

    public function noContent(): void
    {
        $this->setStatusCode(204);
        $this->send();
    }

    public function redirect(string $url, int $status = 302): void
    {
        $this->setStatusCode($status);
        $this->setHeader('Location', $url);
        $this->send();
    }

    public function download(string $filePath, ?string $fileName = null): void
    {
        if (!file_exists($filePath)) {
            $this->notFound('File not found');
            return;
        }

        $fileName = $fileName ?? basename($filePath);
        $fileSize = filesize($filePath);
        $mimeType = $mimeType ?? mime_content_type($filePath);

        $this->setStatusCode(200);
        $this->setHeaders([
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Content-Length' => $fileSize,
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);

        $this->sendHeaders();

        $file = fopen($filePath, 'rb');
        while (!feof($file)) {
            echo fread($file, 8192);
            flush();
        }
        fclose($file);
        
        exit;
    }

    public function stream(string $filePath, ?string $mimeType = null): void
    {
        if (!file_exists($filePath)) {
            $this->notFound('File not found');
            return;
        }

        $mimeType = $mimeType ?? mime_content_type($filePath);
        $fileSize = filesize($filePath);
        $range = $this->parseRangeHeader($fileSize);

        $this->setStatusCode($range['start'] > 0 ? 206 : 200);
        $this->setHeaders([
            'Content-Type' => $mimeType,
            'Content-Length' => $range['length'],
            'Accept-Ranges' => 'bytes',
        ]);

        if ($range['start'] > 0) {
            $this->setHeader('Content-Range', "bytes {$range['start']}-{$range['end']}/$fileSize");
        }

        $this->sendHeaders();

        $file = fopen($filePath, 'rb');
        fseek($file, $range['start']);
        $bytesRemaining = $range['length'];

        while ($bytesRemaining > 0 && !feof($file)) {
            $bytesToRead = min(8192, $bytesRemaining);
            echo fread($file, $bytesToRead);
            $bytesRemaining -= $bytesToRead;
            flush();
        }
        fclose($file);

        exit;
    }

    private function parseErrorIntoReadable($errors): string
    {
        // return the first error message of first field
        if (is_array($errors)) {
            $firstField = array_key_first($errors);
            return $errors[$firstField][0];
        }
        return $errors;
    }

    private function parseRangeHeader(int $fileSize): array
    {
        $range = $_SERVER['HTTP_RANGE'] ?? null;
        if (!$range) {
            return ['start' => 0, 'end' => $fileSize - 1, 'length' => $fileSize];
        }

        if (!preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
            return ['start' => 0, 'end' => $fileSize - 1, 'length' => $fileSize];
        }

        $start = (int)$matches[1];
        $end = empty($matches[2]) ? $fileSize - 1 : (int)$matches[2];

        if ($start >= $fileSize || $end >= $fileSize || $start > $end) {
            return ['start' => 0, 'end' => $fileSize - 1, 'length' => $fileSize];
        }

        return [
            'start' => $start,
            'end' => $end,
            'length' => $end - $start + 1
        ];
    }

    private function sendHeaders(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
    }

    public function send(): void
    {
        $this->sendHeaders();

        if ($this->content !== null) {
            echo $this->content;
        }
        exit;
    }

    public static function cors(array $allowedOrigins = ['*'], array $allowedMethods = ['GET', 'POST', 'OPTIONS'], array $allowedHeaders = ['Content-Type', 'Authorization']): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';

        if (in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: $origin");
            header("Access-Control-Allow-Credentials: true");
        }

        header("Access-Control-Allow-Methods: " . implode(', ', $allowedMethods));
        header("Access-Control-Allow-Headers: " . implode(', ', $allowedHeaders));
        header("Access-Control-Max-Age: 86400");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
