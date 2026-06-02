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

        $imageArtifacts = $this->imageArtifacts($images);
        $imageNameMap = [];
        foreach ($imageArtifacts as $artifact) {
            if ($artifact['source_filename'] !== $artifact['filename']) {
                $imageNameMap[$artifact['source_filename']] = $artifact['filename'];
            }
        }

        $markdownPath = $this->getMarkdownFilepath($outputFolder, $filename);
        $this->writeFile($markdownPath, $this->rewriteImageReferences($fullText, $imageNameMap));

        $metadataPath = $this->stripFinalExtension($markdownPath) . '_meta.json';
        $metadata = $this->rewriteMetadataImageReferences($metadata, $imageNameMap);
        try {
            $encodedMetadata = json_encode(
                $metadata,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Unable to encode markerPDF metadata as JSON.', previous: $exception);
        }
        $this->writeFile($metadataPath, $encodedMetadata);

        foreach ($imageArtifacts as $artifact) {
            $imagePath = $this->joinPath($subfolderPath, $artifact['filename']);
            $this->writeImage($imagePath, $artifact['image']);
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

    /**
     * Upstream images are named by marker.images.save::get_image_filename as
     * "{page}_image_{index}.png". Sanitize supplied native map keys back into
     * that same single-file artifact boundary before joining paths.
     *
     * @param array<string, mixed> $images
     * @return list<array{source_filename: string, filename: string, image: mixed}>
     */
    private function imageArtifacts(array $images): array
    {
        $artifacts = [];
        $used = [];
        $index = 0;

        foreach ($images as $sourceFilename => $image) {
            $sourceFilename = (string) $sourceFilename;
            $artifacts[] = [
                'source_filename' => $sourceFilename,
                'filename' => $this->sanitizeImageFilename($sourceFilename, $index, $used),
                'image' => $image,
            ];
            $index++;
        }

        return $artifacts;
    }

    /**
     * @param array<string, bool> $used
     */
    private function sanitizeImageFilename(string $filename, int $index, array &$used): string
    {
        $basename = basename(str_replace('\\', '/', $filename));
        $basename = trim((string) preg_replace('/[\x00-\x1F\x7F]/', '', $basename));
        $stem = (string) preg_replace('/\.[^.]*$/', '', $basename);
        $stem = (string) preg_replace('/[^A-Za-z0-9._-]+/', '_', $stem);
        $stem = (string) preg_replace('/_+/', '_', $stem);
        $stem = trim($stem, '._-');

        if ($stem === '') {
            $stem = 'image_' . $index;
        }

        $candidate = $stem . '.png';
        $suffix = 2;
        while (isset($used[strtolower($candidate)])) {
            $candidate = $stem . '_' . $suffix . '.png';
            $suffix++;
        }
        $used[strtolower($candidate)] = true;

        return $candidate;
    }

    /**
     * @param array<string, string> $imageNameMap
     */
    private function rewriteImageReferences(string $markdown, array $imageNameMap): string
    {
        if ($imageNameMap === []) {
            return $markdown;
        }

        $markdown = (string) preg_replace_callback(
            '/!\[(?P<alt>[^\]]*)\]\((?P<target>[^)\r\n]+)\)/',
            static function (array $match) use ($imageNameMap): string {
                $target = trim((string) $match['target']);
                if (!isset($imageNameMap[$target])) {
                    return $match[0];
                }

                $alt = (string) $match['alt'];
                if (isset($imageNameMap[$alt])) {
                    $alt = $imageNameMap[$alt];
                }

                return '![' . $alt . '](' . $imageNameMap[$target] . ')';
            },
            $markdown
        );

        return (string) preg_replace_callback(
            '/\b(?P<attribute>src|href|alt)=(?P<quote>["\'])(?P<value>.*?)(?P=quote)/i',
            static function (array $match) use ($imageNameMap): string {
                $value = (string) $match['value'];
                if (!isset($imageNameMap[$value])) {
                    return $match[0];
                }

                return $match['attribute'] . '=' . $match['quote'] . $imageNameMap[$value] . $match['quote'];
            },
            $markdown
        );
    }

    /**
     * @param array<string, string> $imageNameMap
     */
    private function rewriteMetadataImageReferences(mixed $value, array $imageNameMap): mixed
    {
        if ($imageNameMap === []) {
            return $value;
        }
        if (is_string($value)) {
            return $imageNameMap[$value] ?? $value;
        }
        if (!is_array($value)) {
            return $value;
        }

        $rewritten = [];
        foreach ($value as $key => $item) {
            $rewrittenKey = is_string($key) && isset($imageNameMap[$key]) ? $imageNameMap[$key] : $key;
            $rewritten[$rewrittenKey] = $this->rewriteMetadataImageReferences($item, $imageNameMap);
        }

        return $rewritten;
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
