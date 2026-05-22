<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class SingleDocumentConverter
{
    private OutputWriter $writer;

    public function __construct(?OutputWriter $writer = null)
    {
        $this->writer = $writer ?? new OutputWriter();
    }

    /**
     * Native boundary for convert_single.py's `args.langs.split(",") if args.langs else None`.
     *
     * @return list<string>|null
     */
    public function parseLanguages(?string $languages): ?array
    {
        if ($languages === null || $languages === '') {
            return null;
        }

        return explode(',', $languages);
    }

    /**
     * Native supplied-converter boundary for top-level convert_single.py.
     *
     * @param callable(string, array{max_pages: int|null, start_page: int|null, langs: list<string>|null, batch_multiplier: int}): mixed $converter
     * @return array{status: string, filename: string, output_folder: string, markdown: string, images: list<string>, elapsed_seconds: float, options: array{max_pages: int|null, start_page: int|null, langs: list<string>|null, batch_multiplier: int}}
     */
    public function convert(
        string $filename,
        string $outputFolder,
        callable $converter,
        ?int $maxPages = null,
        ?int $startPage = null,
        ?string $languages = null,
        int $batchMultiplier = 2
    ): array {
        $options = [
            'max_pages' => $maxPages,
            'start_page' => $startPage,
            'langs' => $this->parseLanguages($languages),
            'batch_multiplier' => $batchMultiplier,
        ];

        $started = microtime(true);
        $conversion = $this->normalizeConversion($converter($filename, $options));
        $basename = basename($filename);
        $subfolder = $this->writer->saveMarkdown(
            $outputFolder,
            $basename,
            $conversion['text'],
            $conversion['images'],
            $conversion['metadata']
        );

        return [
            'status' => 'converted',
            'filename' => $basename,
            'output_folder' => $subfolder,
            'markdown' => $this->writer->getMarkdownFilepath($outputFolder, $basename),
            'images' => array_keys($conversion['images']),
            'elapsed_seconds' => microtime(true) - $started,
            'options' => $options,
        ];
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
            throw new InvalidArgumentException('Single-document converter must return text or a conversion array.');
        }

        $text = $conversion['text']
            ?? $conversion['full_text']
            ?? $conversion['markdown']
            ?? $conversion[0]
            ?? '';
        $images = $conversion['images'] ?? $conversion[1] ?? [];
        $metadata = $conversion['metadata'] ?? $conversion['out_metadata'] ?? $conversion[2] ?? [];

        if (!is_array($images) || !is_array($metadata)) {
            throw new InvalidArgumentException('Single-document converter images and metadata must be arrays.');
        }

        return [
            'text' => (string) $text,
            'images' => $images,
            'metadata' => $metadata,
        ];
    }
}
