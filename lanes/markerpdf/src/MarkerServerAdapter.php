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

        $fileBytes = @file_get_contents($params['filepath']);
        if (!is_string($fileBytes)) {
            throw new InvalidArgumentException('Remote marker API conversion file is not readable: ' . $params['filepath']);
        }

        $headers = ['X-API-Key' => (string) $apiKey];
        $data = $remoteClient('POST', $datalabUrl, [
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
        ]);

        if (!isset($data['request_check_url']) || !is_string($data['request_check_url'])) {
            throw new InvalidArgumentException('Remote marker API response is missing request_check_url.');
        }
        $checkUrl = $data['request_check_url'];

        for ($pollIndex = 0; $pollIndex < $maxPolls; $pollIndex++) {
            $data = $remoteClient('GET', $checkUrl, [
                'headers' => $headers,
                'poll_index' => $pollIndex,
            ]);

            if (($data['status'] ?? null) === 'complete') {
                break;
            }
        }

        return $data;
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
