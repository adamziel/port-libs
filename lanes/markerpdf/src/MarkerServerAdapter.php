<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;
use RuntimeException;
use Stringable;
use Throwable;

final class MarkerServerAdapter
{
    public const DEFAULT_DATALAB_URL = 'https://api.datalab.to/api/v1/marker';
    public const DEFAULT_HOST = '127.0.0.1';
    public const DEFAULT_PORT = 8000;
    public const DEFAULT_UPLOAD_DIRECTORY = './uploads';

    /**
     * Native non-executing boundary for marker_server.py import-time upload setup
     * and main() CLI configuration before uvicorn.run().
     *
     * @param array<string, mixed> $config
     * @param callable(string): bool|null $uploadDirectoryInitializer
     * @return array{
     *     host: string,
     *     port: int,
     *     local: bool,
     *     api_key_configured: bool,
     *     datalab_url: string,
     *     upload_directory: string,
     *     upload_directory_absolute: string,
     *     upload_directory_status: string,
     *     upload_directory_created: bool,
     *     app_state: array{API_KEY_CONFIGURED: bool, LOCAL: bool, DATALAB_URL: string},
     *     uvicorn: array{app: string, host: string, port: int},
     *     loads_models_on_lifespan: bool,
     *     loads_models_during_plan: false,
     *     executes_uvicorn: false,
     *     executes_fastapi: false,
     *     executes_python_or_models: false,
     *     executes_live_http: false
     * }
     */
    public function serverConfigPlan(array $config = [], ?callable $uploadDirectoryInitializer = null): array
    {
        $host = $this->serverHost($config['host'] ?? self::DEFAULT_HOST);
        $port = $this->serverPort($config['port'] ?? self::DEFAULT_PORT);
        $apiKey = $this->serverApiKey($config['api_key'] ?? $config['apiKey'] ?? null);
        $local = $apiKey === null;
        $datalabUrl = $this->serverString(
            $config['datalab_url'] ?? $config['datalabUrl'] ?? self::DEFAULT_DATALAB_URL,
            'datalab_url'
        );
        $uploadDirectory = $this->serverString(
            $config['upload_directory'] ?? $config['uploadDirectory'] ?? self::DEFAULT_UPLOAD_DIRECTORY,
            'upload_directory'
        );
        $uploadDirectoryAbsolute = $this->absoluteServerPath($uploadDirectory);
        $ensureUploadDirectory = $this->boolValue($config['ensure_upload_directory'] ?? $config['ensureUploadDirectory'] ?? false)
            || $uploadDirectoryInitializer !== null;

        $created = false;
        $status = is_dir($uploadDirectoryAbsolute) ? 'exists' : 'planned';
        if ($ensureUploadDirectory && !is_dir($uploadDirectoryAbsolute)) {
            $created = $uploadDirectoryInitializer !== null
                ? (bool) $uploadDirectoryInitializer($uploadDirectoryAbsolute)
                : mkdir($uploadDirectoryAbsolute, 0777, true);

            if (!$created || !is_dir($uploadDirectoryAbsolute)) {
                throw new RuntimeException('Unable to create markerPDF upload folder: ' . $uploadDirectoryAbsolute);
            }

            $status = 'created';
        }

        return [
            'host' => $host,
            'port' => $port,
            'local' => $local,
            'api_key_configured' => $apiKey !== null,
            'datalab_url' => $datalabUrl,
            'upload_directory' => $uploadDirectory,
            'upload_directory_absolute' => $uploadDirectoryAbsolute,
            'upload_directory_status' => $status,
            'upload_directory_created' => $created,
            'app_state' => [
                'API_KEY_CONFIGURED' => $apiKey !== null,
                'LOCAL' => $local,
                'DATALAB_URL' => $datalabUrl,
            ],
            'uvicorn' => [
                'app' => 'marker_server:app',
                'host' => $host,
                'port' => $port,
            ],
            'loads_models_on_lifespan' => $local,
            'loads_models_during_plan' => false,
            'executes_uvicorn' => false,
            'executes_fastapi' => false,
            'executes_python_or_models' => false,
            'executes_live_http' => false,
        ];
    }

    /**
     * WordPress-safe wrapper for marker_server.py configuration failures.
     *
     * @param array<string, mixed> $config
     * @param callable(string): bool|null $uploadDirectoryInitializer
     * @return array{success: bool, config: array<string, mixed>|null, error: string|null, executes_uvicorn: false, executes_fastapi: false, executes_python_or_models: false, executes_live_http: false}
     */
    public function serverConfigErrorBoundary(array $config = [], ?callable $uploadDirectoryInitializer = null): array
    {
        try {
            return [
                'success' => true,
                'config' => $this->serverConfigPlan($config, $uploadDirectoryInitializer),
                'error' => null,
                'executes_uvicorn' => false,
                'executes_fastapi' => false,
                'executes_python_or_models' => false,
                'executes_live_http' => false,
            ];
        } catch (Throwable $throwable) {
            return [
                'success' => false,
                'config' => null,
                'error' => $throwable->getMessage(),
                'executes_uvicorn' => false,
                'executes_fastapi' => false,
                'executes_python_or_models' => false,
                'executes_live_http' => false,
            ];
        }
    }

    /**
     * Native parameter boundary for marker_server.py::CommonParams.
     *
     * @return array{filepath: string|null, max_pages: int|null, langs: string|null, force_ocr: bool, paginate: bool, extract_images: bool}
     */
    public function normalizeParams(array $params): array
    {
        return [
            'filepath' => isset($params['filepath']) && $params['filepath'] !== '' ? (string) $params['filepath'] : null,
            'max_pages' => $this->optionalInt($params['max_pages'] ?? null, 'max_pages'),
            'langs' => isset($params['langs']) && $params['langs'] !== '' ? (string) $params['langs'] : null,
            'force_ocr' => $this->boolValue($params['force_ocr'] ?? false),
            'paginate' => $this->boolValue($params['paginate'] ?? false),
            'extract_images' => $this->boolValue($params['extract_images'] ?? true),
        ];
    }

    /**
     * Native boundary for marker_server.py::convert_pdf.
     *
     * @param callable(string, array{max_pages: int|null, langs: string|null, ocr_all_pages: bool}): mixed $localConverter
     * @param callable(string, string, array<string, mixed>): array<string, mixed>|null $remoteClient
     * @return array<string, mixed>
     */
    public function convertPdf(
        array $params,
        bool $local,
        callable $localConverter,
        ?callable $remoteClient = null,
        ?string $apiKey = null,
        string $datalabUrl = self::DEFAULT_DATALAB_URL
    ): array {
        $params = $this->normalizeParams($params);
        if ($params['filepath'] === null) {
            throw new InvalidArgumentException('Marker API conversion requires a filepath.');
        }

        if ($local) {
            if ($params['extract_images'] !== true || $params['paginate'] !== false) {
                throw new InvalidArgumentException('Local conversion API does not support image extraction or pagination.');
            }

            return $this->convertPdfLocal($params, $localConverter);
        }

        return $this->convertPdfRemote($params, $remoteClient, $apiKey, $datalabUrl);
    }

    /**
     * Native boundary for marker_server.py::convert_pdf_from_upload.
     *
     * @param array{filename?: string, content_type?: string, bytes?: string, content?: string, data?: string} $upload
     * @param callable(string, array{max_pages: int|null, langs: string|null, ocr_all_pages: bool}): mixed $localConverter
     * @param callable(string, string, array<string, mixed>): array<string, mixed>|null $remoteClient
     * @return array<string, mixed>
     */
    public function convertPdfFromUpload(
        array $upload,
        array $params,
        string $uploadDirectory,
        bool $local,
        callable $localConverter,
        ?callable $remoteClient = null,
        ?string $apiKey = null,
        string $datalabUrl = self::DEFAULT_DATALAB_URL
    ): array {
        if (($upload['content_type'] ?? null) !== 'application/pdf') {
            throw new InvalidArgumentException('Only PDF files are allowed.');
        }

        $filename = basename((string) ($upload['filename'] ?? 'upload.pdf'));
        if ($filename === '' || $filename === '.' || $filename === '..') {
            throw new InvalidArgumentException('Uploaded PDF must include a filename.');
        }

        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0777, true) && !is_dir($uploadDirectory)) {
            throw new RuntimeException('Unable to create markerPDF upload folder: ' . $uploadDirectory);
        }

        $uploadPath = rtrim($uploadDirectory, '/\\') . DIRECTORY_SEPARATOR . $filename;
        try {
            if (file_put_contents($uploadPath, $this->uploadBytes($upload)) === false) {
                throw new RuntimeException('Unable to write markerPDF uploaded PDF: ' . $uploadPath);
            }

            $params['filepath'] = $uploadPath;
            $params = $this->normalizeParams($params);

            return $local
                ? $this->convertPdfLocal($params, $localConverter)
                : $this->convertPdfRemote($params, $remoteClient, $apiKey, $datalabUrl);
        } catch (Throwable $throwable) {
            return [
                'success' => false,
                'error' => $throwable->getMessage(),
            ];
        } finally {
            if (is_file($uploadPath)) {
                unlink($uploadPath);
            }
        }
    }

    /**
     * Native boundary for marker_server.py::convert_pdf_local.
     *
     * @param array{filepath: string, max_pages: int|null, langs: string|null, force_ocr: bool, paginate: bool, extract_images: bool} $params
     * @param callable(string, array{max_pages: int|null, langs: string|null, ocr_all_pages: bool}): mixed $converter
     * @return array{markdown?: string, images?: array<string, string>, metadata?: array<string, mixed>, success: bool, error?: string}
     */
    public function convertPdfLocal(array $params, callable $converter): array
    {
        try {
            $conversion = $this->normalizeConversion($converter(
                $params['filepath'],
                [
                    'max_pages' => $params['max_pages'],
                    'langs' => $params['langs'],
                    'ocr_all_pages' => $params['force_ocr'],
                ]
            ));
        } catch (Throwable $throwable) {
            return [
                'success' => false,
                'error' => $throwable->getMessage(),
            ];
        }

        return [
            'markdown' => $conversion['text'],
            'images' => $this->base64Images($conversion['images']),
            'metadata' => $conversion['metadata'],
            'success' => true,
        ];
    }

    /**
     * Native boundary for marker_server.py::convert_pdf_remote.
     *
     * @param array{filepath: string, max_pages: int|null, langs: string|null, force_ocr: bool, paginate: bool, extract_images: bool} $params
     * @param callable(string, string, array<string, mixed>): array<string, mixed>|null $remoteClient
     * @return array<string, mixed>
     */
    public function convertPdfRemote(
        array $params,
        ?callable $remoteClient,
        ?string $apiKey,
        string $datalabUrl = self::DEFAULT_DATALAB_URL,
        int $maxPolls = 300
    ): array {
        if ($remoteClient === null) {
            throw new InvalidArgumentException('Remote marker API conversion requires a remote client callback.');
        }
        if ($maxPolls < 1) {
            throw new InvalidArgumentException('Remote marker API max poll count must be at least one.');
        }

        $fileBytes = @file_get_contents($params['filepath']);
        if (!is_string($fileBytes)) {
            throw new InvalidArgumentException('Remote marker API conversion file is not readable: ' . $params['filepath']);
        }

        $headers = ['X-API-Key' => (string) $apiKey];
        $data = $this->remoteJsonPayload($remoteClient('POST', $datalabUrl, [
            'headers' => $headers,
            'files' => [
                'file' => [
                    'filename' => basename($params['filepath']),
                    'bytes' => $fileBytes,
                    'content_type' => 'application/pdf',
                ],
                'max_pages' => $params['max_pages'],
                'langs' => $params['langs'],
                'force_ocr' => $params['force_ocr'],
                'paginate' => $params['paginate'],
                'extract_images' => $params['extract_images'],
            ],
        ]), 'initial');

        if (!isset($data['request_check_url']) || !is_string($data['request_check_url'])) {
            throw new InvalidArgumentException('Remote marker API response is missing request_check_url.');
        }
        $checkUrl = $data['request_check_url'];

        for ($pollIndex = 0; $pollIndex < $maxPolls; $pollIndex++) {
            $data = $this->remoteJsonPayload($remoteClient('GET', $checkUrl, [
                'headers' => $headers,
                'poll_index' => $pollIndex,
            ]), 'poll');

            if (!array_key_exists('status', $data)) {
                throw new InvalidArgumentException('Remote marker API poll response is missing status.');
            }

            if ($data['status'] === 'complete') {
                break;
            }
        }

        return $data;
    }

    /**
     * Native non-executing boundary for marker_server.py::convert_pdf_remote.
     *
     * @return array{
     *     initial_request_method: string,
     *     poll_request_method: string,
     *     max_polls: int,
     *     poll_interval_seconds: int,
     *     request_check_url_key: string,
     *     poll_status_key: string,
     *     completion_status: string,
     *     returns_last_poll_response_after_exhaustion: bool,
     *     invents_timeout_error: bool,
     *     executes_live_http: false,
     *     executes_python_or_models: false
     * }
     */
    public function remotePollingPlan(int $maxPolls = 300, int $pollIntervalSeconds = 2): array
    {
        if ($maxPolls < 1) {
            throw new InvalidArgumentException('Remote marker API max poll count must be at least one.');
        }
        if ($pollIntervalSeconds < 0) {
            throw new InvalidArgumentException('Remote marker API poll interval must not be negative.');
        }

        return [
            'initial_request_method' => 'POST',
            'poll_request_method' => 'GET',
            'max_polls' => $maxPolls,
            'poll_interval_seconds' => $pollIntervalSeconds,
            'request_check_url_key' => 'request_check_url',
            'poll_status_key' => 'status',
            'completion_status' => 'complete',
            'returns_last_poll_response_after_exhaustion' => true,
            'invents_timeout_error' => false,
            'executes_live_http' => false,
            'executes_python_or_models' => false,
        ];
    }

    private function optionalInt(mixed $value, string $name): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new InvalidArgumentException("Marker API {$name} must be an integer when provided.");
    }

    private function serverPort(mixed $value): int
    {
        if (is_int($value)) {
            $port = $value;
        } elseif (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1) {
            $port = (int) trim($value);
        } else {
            throw new InvalidArgumentException('Marker server port must be an integer.');
        }

        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('Marker server port must be between 1 and 65535.');
        }

        return $port;
    }

    private function serverHost(mixed $value): string
    {
        $host = $this->serverString($value, 'host');
        if (str_contains($host, "\0")) {
            throw new InvalidArgumentException('Marker server host must not contain NUL bytes.');
        }

        return $host;
    }

    private function serverApiKey(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        throw new InvalidArgumentException('Marker server api_key must be a string when provided.');
    }

    private function serverString(mixed $value, string $name): string
    {
        if (!is_scalar($value) && !$value instanceof Stringable) {
            throw new InvalidArgumentException("Marker server {$name} must be a string.");
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            throw new InvalidArgumentException("Marker server {$name} must not be empty.");
        }

        return $normalized;
    }

    private function absoluteServerPath(string $path): string
    {
        $normalized = rtrim($path, "/\\");
        if ($normalized === '') {
            return DIRECTORY_SEPARATOR;
        }

        if (str_starts_with($normalized, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\\/]/', $normalized) === 1) {
            return $normalized;
        }

        return getcwd() . DIRECTORY_SEPARATOR . $normalized;
    }

    private function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value !== 0;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function remoteJsonPayload(mixed $payload, string $phase): array
    {
        if (!is_array($payload)) {
            throw new InvalidArgumentException("Remote marker API {$phase} response JSON must decode to an object.");
        }

        return $payload;
    }

    /**
     * @return array{text: string, images: array<string, mixed>, metadata: array<string, mixed>}
     */
    private function normalizeConversion(mixed $conversion): array
    {
        if (is_string($conversion)) {
            return ['text' => $conversion, 'images' => [], 'metadata' => []];
        }
        if (!is_array($conversion)) {
            throw new InvalidArgumentException('Marker API local converter must return text or a conversion array.');
        }

        $text = $conversion['text']
            ?? $conversion['full_text']
            ?? $conversion['markdown']
            ?? $conversion[0]
            ?? '';
        $images = $conversion['images'] ?? $conversion[1] ?? [];
        $metadata = $conversion['metadata'] ?? $conversion['out_metadata'] ?? $conversion[2] ?? [];

        if (!is_array($images) || !is_array($metadata)) {
            throw new InvalidArgumentException('Marker API local converter images and metadata must be arrays.');
        }

        return [
            'text' => (string) $text,
            'images' => $images,
            'metadata' => $metadata,
        ];
    }

    /**
     * @param array<string, mixed> $images
     * @return array<string, string>
     */
    private function base64Images(array $images): array
    {
        $encoded = [];
        foreach ($images as $filename => $image) {
            $encoded[(string) $filename] = base64_encode($this->imageBytes($image));
        }

        return $encoded;
    }

    private function uploadBytes(array $upload): string
    {
        foreach (['bytes', 'content', 'data'] as $key) {
            if (isset($upload[$key]) && is_string($upload[$key])) {
                return $upload[$key];
            }
        }

        throw new InvalidArgumentException('Uploaded PDF payload must provide bytes.');
    }

    private function imageBytes(mixed $image): string
    {
        if (is_string($image)) {
            return $image;
        }
        if ($image instanceof Stringable) {
            return (string) $image;
        }
        if (is_array($image)) {
            foreach (['bytes', 'data', 'content'] as $key) {
                if (isset($image[$key]) && is_string($image[$key])) {
                    return $image[$key];
                }
            }
        }
        if (is_object($image) && method_exists($image, 'save')) {
            $tmp = tempnam(sys_get_temp_dir(), 'markerpdf-image-');
            if ($tmp === false) {
                throw new RuntimeException('Unable to allocate temporary markerPDF image file.');
            }

            try {
                $image->save($tmp, 'PNG');
                $bytes = file_get_contents($tmp);
                if (!is_string($bytes)) {
                    throw new RuntimeException('Unable to read temporary markerPDF image file.');
                }

                return $bytes;
            } finally {
                if (is_file($tmp)) {
                    unlink($tmp);
                }
            }
        }

        throw new InvalidArgumentException('Image payload must be PNG bytes or expose save().');
    }
}
