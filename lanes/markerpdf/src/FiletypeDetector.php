<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class FiletypeDetector
{
    private MarkerSettings $settings;

    public function __construct(?MarkerSettings $settings = null)
    {
        $this->settings = $settings ?? new MarkerSettings();
    }

    public function findFiletype(string $path): string
    {
        return (string) $this->findFiletypeReview($path)['filetype'];
    }

    /**
     * Native review boundary for marker.pdf.utils::find_filetype.
     *
     * Upstream prints a diagnostic for unknown filetype guesses and for
     * nonstandard mimetypes before returning "other". This records that
     * stdout-facing boundary without reading more than the initial header bytes
     * or invoking Python/filetype.
     *
     * @return array{
     *     source: string,
     *     filepath: string,
     *     path_is_file: bool,
     *     path_is_readable: bool,
     *     bytes_available: bool,
     *     filetype_guess_available: bool,
     *     mimetype: string|null,
     *     filetype: string,
     *     supported_filetype: bool,
     *     stdout_message_line: string|null,
     *     prints_stdout_message: bool,
     *     return_boundary: string,
     *     review_only: true,
     *     executes_python_or_models: false,
     *     executes_external_pdf_tools: false
     * }
     */
    public function findFiletypeReview(string $path): array
    {
        $isFile = is_file($path);
        $isReadable = is_readable($path);
        if (!is_file($path) || !is_readable($path)) {
            return $this->filetypeReview(
                $path,
                $isFile,
                $isReadable,
                false,
                null,
                'other',
                'Could not determine filetype for ' . $path,
                'unknown-kind-return-other'
            );
        }

        $bytes = @file_get_contents($path, false, null, 0, 8192);
        if (!is_string($bytes) || $bytes === '') {
            return $this->filetypeReview(
                $path,
                $isFile,
                $isReadable,
                false,
                null,
                'other',
                'Could not determine filetype for ' . $path,
                'unknown-kind-return-other'
            );
        }

        $mimeType = $this->guessMimeType($bytes);
        if ($mimeType === null) {
            return $this->filetypeReview(
                $path,
                $isFile,
                $isReadable,
                true,
                null,
                'other',
                'Could not determine filetype for ' . $path,
                'unknown-kind-return-other'
            );
        }

        $filetype = $this->filetypeFromMimeType($mimeType);
        if ($filetype === 'other') {
            return $this->filetypeReview(
                $path,
                $isFile,
                $isReadable,
                true,
                $mimeType,
                'other',
                'Found nonstandard filetype ' . $mimeType,
                'nonstandard-filetype-return-other'
            );
        }

        return $this->filetypeReview(
            $path,
            $isFile,
            $isReadable,
            true,
            $mimeType,
            $filetype,
            null,
            $filetype === 'pdf' ? 'pdf-return-pdf' : 'settings-supported-return-filetype'
        );
    }

    public function findFiletypeFromBytes(string $bytes): string
    {
        $mimeType = $this->guessMimeType($bytes);
        if ($mimeType === null) {
            return 'other';
        }

        return $this->filetypeFromMimeType($mimeType);
    }

    public function filetypeFromMimeType(string $mimeType): string
    {
        $normalized = strtolower($mimeType);
        if (str_contains($normalized, 'pdf')) {
            return 'pdf';
        }

        return $this->settings->extensionForFiletype($normalized) ?? 'other';
    }

    private function guessMimeType(string $bytes): ?string
    {
        if (str_starts_with($bytes, '%PDF-')) {
            return 'application/pdf';
        }
        if (str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }
        if (str_starts_with($bytes, "PK\x03\x04") || str_starts_with($bytes, "PK\x05\x06") || str_starts_with($bytes, "PK\x07\x08")) {
            return 'application/zip';
        }

        if (class_exists(\finfo::class)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($bytes);
            if (is_string($mimeType) && $mimeType !== '' && $mimeType !== 'application/octet-stream') {
                return strtolower($mimeType);
            }
        }

        return null;
    }

    /**
     * @return array{
     *     source: string,
     *     filepath: string,
     *     path_is_file: bool,
     *     path_is_readable: bool,
     *     bytes_available: bool,
     *     filetype_guess_available: bool,
     *     mimetype: string|null,
     *     filetype: string,
     *     supported_filetype: bool,
     *     stdout_message_line: string|null,
     *     prints_stdout_message: bool,
     *     return_boundary: string,
     *     review_only: true,
     *     executes_python_or_models: false,
     *     executes_external_pdf_tools: false
     * }
     */
    private function filetypeReview(
        string $path,
        bool $isFile,
        bool $isReadable,
        bool $bytesAvailable,
        ?string $mimeType,
        string $filetype,
        ?string $stdoutMessage,
        string $returnBoundary
    ): array {
        return [
            'source' => 'sddai/markerPDF marker.pdf.utils.find_filetype + filetype.guess',
            'filepath' => $path,
            'path_is_file' => $isFile,
            'path_is_readable' => $isReadable,
            'bytes_available' => $bytesAvailable,
            'filetype_guess_available' => $mimeType !== null,
            'mimetype' => $mimeType,
            'filetype' => $filetype,
            'supported_filetype' => $filetype !== 'other',
            'stdout_message_line' => $stdoutMessage,
            'prints_stdout_message' => $stdoutMessage !== null,
            'return_boundary' => $returnBoundary,
            'review_only' => true,
            'executes_python_or_models' => false,
            'executes_external_pdf_tools' => false,
        ];
    }
}
