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
    public const UPSTREAM_PAGE_SEPARATOR = "------------------------------------------------\n\n";

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
     * Native non-executing boundary for marker_server.py::convert_pdf_upload.
     *
     * Upstream accepts pagination/image flags on the upload form, saves the
     * temporary PDF, mutates params.filepath, then routes directly to local or
     * remote conversion. The local upload path bypasses the direct /marker
     * endpoint's local pagination/image assertion; the remote path forwards all
     * form fields into the Datalab multipart request.
     *
     * @return array{
     *     endpoint: string,
     *     method: string,
     *     file_field: array{name: string, media_type: string, required: bool, invalid_content_type_status_code: int, invalid_content_type_detail: string, rejects_non_pdf_before_guard: bool},
     *     form_params: array<string, array{default: bool|int|string|null, value: bool|int|string|null}>,
     *     upload_directory: string,
     *     upload_directory_absolute: string,
     *     selected_route: string,
     *     local_route: array{calls: string, uses_uploaded_filepath: bool, applies_direct_marker_option_guard: bool, forwards_convert_single_pdf_options: array{max_pages: int|null, langs: string|null, ocr_all_pages: bool}, paginate_forwarded_to_convert_single_pdf: bool, extract_images_forwarded_to_convert_single_pdf: bool},
     *     remote_route: array{calls: string, forwards_multipart_fields: list<string>, multipart_field_values: array{max_pages: int|null, langs: string|null, force_ocr: bool, paginate: bool, extract_images: bool}, polls_request_check_url: bool},
     *     error_boundary: array{body_errors_return_success_false: bool, invalid_content_type_raises_http_error_before_body_guard: bool, missing_request_check_url_becomes_upload_error_payload: bool},
     *     cleanup: array{removes_upload_after_success: bool, removes_upload_after_body_error: bool},
     *     executes_fastapi: false,
     *     executes_uvicorn: false,
     *     executes_live_http: false,
     *     executes_python_or_models: false
     * }
     */
    public function uploadRoutePlan(array $params = [], string $uploadDirectory = self::DEFAULT_UPLOAD_DIRECTORY, bool $local = true): array
    {
        $normalized = $this->normalizeParams($params);
        $uploadDirectory = $this->serverString($uploadDirectory, 'upload_directory');

        return [
            'endpoint' => '/marker/upload',
            'method' => 'POST',
            'file_field' => [
                'name' => 'file',
                'media_type' => 'application/pdf',
                'required' => true,
                'invalid_content_type_status_code' => 400,
                'invalid_content_type_detail' => 'Only PDF files are allowed.',
                'rejects_non_pdf_before_guard' => true,
            ],
            'form_params' => [
                'max_pages' => ['default' => null, 'value' => $normalized['max_pages']],
                'langs' => ['default' => null, 'value' => $normalized['langs']],
                'force_ocr' => ['default' => false, 'value' => $normalized['force_ocr']],
                'paginate' => ['default' => false, 'value' => $normalized['paginate']],
                'extract_images' => ['default' => true, 'value' => $normalized['extract_images']],
            ],
            'upload_directory' => $uploadDirectory,
            'upload_directory_absolute' => $this->absoluteServerPath($uploadDirectory),
            'selected_route' => $local ? 'local' : 'remote',
            'local_route' => [
                'calls' => 'convert_pdf_local',
                'uses_uploaded_filepath' => true,
                'applies_direct_marker_option_guard' => false,
                'forwards_convert_single_pdf_options' => [
                    'max_pages' => $normalized['max_pages'],
                    'langs' => $normalized['langs'],
                    'ocr_all_pages' => $normalized['force_ocr'],
                ],
                'paginate_forwarded_to_convert_single_pdf' => false,
                'extract_images_forwarded_to_convert_single_pdf' => false,
            ],
            'remote_route' => [
                'calls' => 'convert_pdf_remote',
                'forwards_multipart_fields' => ['file', 'max_pages', 'langs', 'force_ocr', 'paginate', 'extract_images'],
                'multipart_field_values' => [
                    'max_pages' => $normalized['max_pages'],
                    'langs' => $normalized['langs'],
                    'force_ocr' => $normalized['force_ocr'],
                    'paginate' => $normalized['paginate'],
                    'extract_images' => $normalized['extract_images'],
                ],
                'polls_request_check_url' => true,
            ],
            'error_boundary' => [
                'body_errors_return_success_false' => true,
                'invalid_content_type_raises_http_error_before_body_guard' => true,
                'missing_request_check_url_becomes_upload_error_payload' => true,
            ],
            'cleanup' => [
                'removes_upload_after_success' => true,
                'removes_upload_after_body_error' => true,
            ],
            'executes_fastapi' => false,
            'executes_uvicorn' => false,
            'executes_live_http' => false,
            'executes_python_or_models' => false,
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

        return $this->decorateServerOutputPagination([
            'markdown' => $conversion['text'],
            'images' => $this->base64Images($conversion['images']),
            'metadata' => $conversion['metadata'],
            'success' => true,
        ], $params);
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

        return $this->decorateServerOutputPagination($data, $params);
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

    /**
     * Native review boundary for marker.postprocessors.markdown::get_full_text
     * page markers as returned through marker_server.py conversion responses.
     *
     * @return array{
     *     paginate_requested: bool,
     *     upstream_page_separator: string,
     *     marker_prefix: string,
     *     has_upstream_markers: bool,
     *     markdown_starts_with_page_marker: bool,
     *     page_count: int,
     *     first_page: int|null,
     *     last_page: int|null,
     *     page_sequence: list<int>,
     *     monotonic_page_sequence: bool,
     *     page_markers: list<array{page: int, offset: int, marker: string}>,
     *     page_segments: list<array{page: int, text: string, raw_text_sha256: string, byte_length: int}>,
     *     strips_markers_from_page_segments: bool,
     *     review_only: true,
     *     executes_fastapi: false,
     *     executes_uvicorn: false,
     *     executes_live_http: false,
     *     executes_python_or_models: false
     * }
     */
    public function serverOutputPaginationPlan(
        string $markdown,
        bool $paginateRequested,
        string $pageSeparator = self::UPSTREAM_PAGE_SEPARATOR
    ): array {
        if ($pageSeparator === '') {
            throw new InvalidArgumentException('Marker server page separator must not be empty.');
        }

        $pattern = '/\n\n\{([0-9]+)\}' . preg_quote($pageSeparator, '/') . '/';
        preg_match_all($pattern, $markdown, $matches, PREG_OFFSET_CAPTURE);

        $markers = [];
        $segments = [];
        $sequence = [];
        $markerCount = count($matches[0]);
        for ($index = 0; $index < $markerCount; $index++) {
            $markerText = $matches[0][$index][0];
            $markerOffset = $matches[0][$index][1];
            $page = (int) $matches[1][$index][0];
            $sequence[] = $page;
            $markers[] = [
                'page' => $page,
                'offset' => $markerOffset,
                'marker' => $markerText,
            ];

            $segmentStart = $markerOffset + strlen($markerText);
            $segmentEnd = $index + 1 < $markerCount ? $matches[0][$index + 1][1] : strlen($markdown);
            $rawText = substr($markdown, $segmentStart, max(0, $segmentEnd - $segmentStart));
            $segments[] = [
                'page' => $page,
                'text' => ltrim($rawText, "\r\n"),
                'raw_text_sha256' => hash('sha256', $rawText),
                'byte_length' => strlen($rawText),
            ];
        }

        return [
            'paginate_requested' => $paginateRequested,
            'upstream_page_separator' => $pageSeparator,
            'marker_prefix' => "\n\n{PAGE_NUMBER}",
            'has_upstream_markers' => $markers !== [],
            'markdown_starts_with_page_marker' => $markers !== [] && $markers[0]['offset'] === 0,
            'page_count' => count($markers),
            'first_page' => $sequence[0] ?? null,
            'last_page' => $sequence !== [] ? $sequence[count($sequence) - 1] : null,
            'page_sequence' => $sequence,
            'monotonic_page_sequence' => $this->isStrictlyIncreasing($sequence),
            'page_markers' => $markers,
            'page_segments' => $segments,
            'strips_markers_from_page_segments' => $this->pageSegmentsExcludeMarkers($segments),
            'review_only' => true,
            'executes_fastapi' => false,
            'executes_uvicorn' => false,
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

    /**
     * @param array<string, mixed> $response
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function decorateServerOutputPagination(array $response, array $params): array
    {
        $markdown = $this->serverResponseMarkdown($response);
        if ($markdown === null) {
            return $response;
        }

        $pagination = $this->serverOutputPaginationPlan(
            $markdown,
            (bool) ($params['paginate'] ?? false)
        );
        if (!$pagination['has_upstream_markers']) {
            return $response;
        }

        $summary = $this->serverOutputPaginationSummary($pagination);
        if (!array_key_exists('metadata', $response)) {
            $response['metadata'] = [];
        }

        if (is_array($response['metadata'])) {
            $response['metadata']['server_output_pagination'] = $summary;
        } else {
            $response['server_output_pagination'] = $summary;
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function serverResponseMarkdown(array $response): ?string
    {
        foreach (['markdown', 'full_text', 'text'] as $key) {
            if (isset($response[$key]) && is_string($response[$key])) {
                return $response[$key];
            }
        }

        return null;
    }

    /**
     * @param array{
     *     paginate_requested: bool,
     *     upstream_page_separator: string,
     *     has_upstream_markers: bool,
     *     markdown_starts_with_page_marker: bool,
     *     page_count: int,
     *     first_page: int|null,
     *     last_page: int|null,
     *     page_sequence: list<int>,
     *     monotonic_page_sequence: bool,
     *     page_markers: list<array{page: int, offset: int, marker: string}>,
     *     page_segments: list<array{page: int, text: string, raw_text_sha256: string, byte_length: int}>,
     *     strips_markers_from_page_segments: bool,
     *     review_only: true,
     *     executes_fastapi: false,
     *     executes_uvicorn: false,
     *     executes_live_http: false,
     *     executes_python_or_models: false
     * } $pagination
     * @return array<string, mixed>
     */
    private function serverOutputPaginationSummary(array $pagination): array
    {
        return [
            'paginate_requested' => $pagination['paginate_requested'],
            'upstream_page_separator' => $pagination['upstream_page_separator'],
            'has_upstream_markers' => $pagination['has_upstream_markers'],
            'markdown_starts_with_page_marker' => $pagination['markdown_starts_with_page_marker'],
            'page_count' => $pagination['page_count'],
            'first_page' => $pagination['first_page'],
            'last_page' => $pagination['last_page'],
            'page_sequence' => $pagination['page_sequence'],
            'monotonic_page_sequence' => $pagination['monotonic_page_sequence'],
            'page_markers' => array_map(
                static fn (array $marker): array => [
                    'page' => $marker['page'],
                    'offset' => $marker['offset'],
                ],
                $pagination['page_markers']
            ),
            'page_segments' => array_map(
                static fn (array $segment): array => [
                    'page' => $segment['page'],
                    'text_sha256' => hash('sha256', $segment['text']),
                    'raw_text_sha256' => $segment['raw_text_sha256'],
                    'byte_length' => $segment['byte_length'],
                ],
                $pagination['page_segments']
            ),
            'strips_markers_from_page_segments' => $pagination['strips_markers_from_page_segments'],
            'review_only' => true,
            'executes_fastapi' => false,
            'executes_uvicorn' => false,
            'executes_live_http' => false,
            'executes_python_or_models' => false,
        ];
    }

    /**
     * @param list<int> $sequence
     */
    private function isStrictlyIncreasing(array $sequence): bool
    {
        $previous = null;
        foreach ($sequence as $page) {
            if ($previous !== null && $page <= $previous) {
                return false;
            }
            $previous = $page;
        }

        return true;
    }

    /**
     * @param list<array{page: int, text: string, raw_text_sha256: string, byte_length: int}> $segments
     */
    private function pageSegmentsExcludeMarkers(array $segments): bool
    {
        foreach ($segments as $segment) {
            if (preg_match('/\n\n\{[0-9]+\}' . preg_quote(self::UPSTREAM_PAGE_SEPARATOR, '/') . '/', $segment['text']) === 1) {
                return false;
            }
        }

        return true;
    }
}
