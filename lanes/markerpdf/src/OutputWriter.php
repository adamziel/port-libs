<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Stringable;

final class OutputWriter
{
    public function getSubfolderPath(string $outputFolder, string $filename): string
    {
        return $this->joinPath($outputFolder, $this->stripFinalExtension($filename));
    }

    public function getMarkdownFilepath(string $outputFolder, string $filename): string
    {
        $markdownFilename = $this->stripFinalExtension($filename) . '.md';

        return $this->joinPath($this->getSubfolderPath($outputFolder, $filename), $markdownFilename);
    }

    public function markdownExists(string $outputFolder, string $filename): bool
    {
        return is_file($this->getMarkdownFilepath($outputFolder, $filename));
    }

    /**
     * Native boundary for marker.output::save_markdown.
     *
     * @param array<string, mixed> $images Values may be raw PNG bytes, Stringable bytes, arrays with a
     *                                     `bytes`, `data`, or `content` string, or objects exposing
     *                                     `save(string $path, string $format)`.
     * @param array<string, mixed> $metadata
     */
    public function saveMarkdown(
        string $outputFolder,
        string $filename,
        string $fullText,
        array $images,
        array $metadata
    ): string {
        $subfolderPath = $this->getSubfolderPath($outputFolder, $filename);
        if (!is_dir($subfolderPath) && !mkdir($subfolderPath, 0777, true) && !is_dir($subfolderPath)) {
            throw new RuntimeException('Unable to create markerPDF output folder: ' . $subfolderPath);
        }

        $markdownPath = $this->getMarkdownFilepath($outputFolder, $filename);
        $this->writeFile($markdownPath, $fullText);

        $metadataPath = $this->stripFinalExtension($markdownPath) . '_meta.json';
        try {
            $encodedMetadata = json_encode(
                $metadata,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Unable to encode markerPDF metadata as JSON.', previous: $exception);
        }
        $this->writeFile($metadataPath, $encodedMetadata);

        foreach ($images as $imageFilename => $image) {
            $imagePath = $this->joinPath($subfolderPath, (string) $imageFilename);
            $this->writeImage($imagePath, $image);
        }

        return $subfolderPath;
    }

    private function stripFinalExtension(string $filename): string
    {
        $lastDot = strrpos($filename, '.');
        if ($lastDot === false) {
            return $filename;
        }

        return substr($filename, 0, $lastDot);
    }

    private function joinPath(string $left, string $right): string
    {
        if ($left === '') {
            return $right;
        }

        return rtrim($left, '/\\') . DIRECTORY_SEPARATOR . ltrim($right, '/\\');
    }

    private function writeFile(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Unable to write markerPDF output file: ' . $path);
        }
    }

    private function writeImage(string $path, mixed $image): void
    {
        if (is_object($image) && method_exists($image, 'save')) {
            $image->save($path, 'PNG');
            return;
        }

        $this->writeFile($path, $this->imageBytes($image));
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

        throw new InvalidArgumentException('Image payload must be writable PNG bytes or expose save().');
    }
}
