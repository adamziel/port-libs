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
        if (!is_file($path) || !is_readable($path)) {
            return 'other';
        }

        $bytes = @file_get_contents($path, false, null, 0, 8192);
        if (!is_string($bytes) || $bytes === '') {
            return 'other';
        }

        return $this->findFiletypeFromBytes($bytes);
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
}
