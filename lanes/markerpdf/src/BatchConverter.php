<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;
use JsonException;
use Throwable;

final class BatchConverter
{
    private OutputWriter $writer;
    private FiletypeDetector $filetypeDetector;
    private PdfTextExtractor $textExtractor;

    public function __construct(
        ?OutputWriter $writer = null,
        ?FiletypeDetector $filetypeDetector = null,
        ?PdfTextExtractor $textExtractor = null
    ) {
        $this->writer = $writer ?? new OutputWriter();
        $this->filetypeDetector = $filetypeDetector ?? new FiletypeDetector();
        $this->textExtractor = $textExtractor ?? new PdfTextExtractor();
    }

    /**
     * Native boundary for top-level convert.py task construction.
     *
     * @param array<string, array<string, mixed>> $metadataByFilename
     * @return list<array{filepath: string, out_folder: string, metadata: array<string, mixed>|null, min_length: int|null}>
     */
    public function planTasks(
        string $inputFolder,
        string $outputFolder,
        int $chunkIndex = 0,
        int $numChunks = 1,
        ?int $maxFiles = null,
        array $metadataByFilename = [],
        ?int $minLength = null
    ): array {
        $tasks = [];
        foreach ($this->chunkFiles($this->inputFiles($inputFolder), $chunkIndex, $numChunks, $maxFiles) as $filepath) {
            $basename = basename($filepath);
            $metadata = $metadataByFilename[$basename] ?? null;
            if ($metadata !== null && !is_array($metadata)) {
                throw new InvalidArgumentException('Batch metadata values must be arrays keyed by basename.');
            }

            $tasks[] = [
                'filepath' => $filepath,
                'out_folder' => $outputFolder,
                'metadata' => $metadata,
                'min_length' => $minLength,
            ];
        }

        return $tasks;
    }

    /**
     * @param list<string> $files
     * @return list<string>
     */
    public function chunkFiles(array $files, int $chunkIndex = 0, int $numChunks = 1, ?int $maxFiles = null): array
    {
        if ($chunkIndex < 0) {
            throw new InvalidArgumentException('Batch chunk index must be zero or greater.');
        }
        if ($numChunks < 1) {
            throw new InvalidArgumentException('Batch chunk count must be at least one.');
        }

        $files = array_values($files);
        $chunkSize = (int) ceil(count($files) / $numChunks);
        if ($chunkSize === 0) {
            return [];
        }

        $selected = array_slice($files, $chunkIndex * $chunkSize, $chunkSize);
        if ($maxFiles !== null && $maxFiles > 0) {
            $selected = array_slice($selected, 0, $maxFiles);
        }

        return array_values($selected);
    }

    /**
     * @param array{filepath: string, out_folder: string, metadata?: array<string, mixed>|null, min_length?: int|null} $task
     * @return array<string, mixed>
     */
    public function processTask(array $task, callable $converter, ?callable $textLength = null): array
    {
        return $this->processFile(
            $task['filepath'],
            $task['out_folder'],
            $task['metadata'] ?? null,
            $task['min_length'] ?? null,
            $converter,
            $textLength
        );
    }

    /**
     * @param array<string, array<string, mixed>> $metadataByFilename
     * @return array{tasks: list<array<string, mixed>>, results: list<array<string, mixed>>, converted: int, skipped: int, errors: int}
     */
    public function processFolder(
        string $inputFolder,
        string $outputFolder,
        callable $converter,
        int $chunkIndex = 0,
        int $numChunks = 1,
        ?int $maxFiles = null,
        array $metadataByFilename = [],
        ?int $minLength = null,
        ?callable $textLength = null
    ): array {
        $tasks = $this->planTasks(
            $inputFolder,
            $outputFolder,
            $chunkIndex,
            $numChunks,
            $maxFiles,
            $metadataByFilename,
            $minLength
        );

        $results = [];
        foreach ($tasks as $task) {
            $results[] = $this->processTask($task, $converter, $textLength);
        }

        return [
            'tasks' => $tasks,
            'results' => $results,
            'converted' => count(array_filter($results, static fn (array $result): bool => ($result['status'] ?? '') === 'converted')),
            'skipped' => count(array_filter($results, static fn (array $result): bool => str_starts_with((string) ($result['status'] ?? ''), 'skipped'))),
            'errors' => count(array_filter($results, static fn (array $result): bool => ($result['status'] ?? '') === 'error')),
        ];
    }

    /**
     * Native boundary for convert.py's --metadata_file json.load() path.
     *
     * @return array<string, array<string, mixed>>
     */
    public function loadMetadataFile(?string $metadataFile): array
    {
        if ($metadataFile === null || $metadataFile === '') {
            return [];
        }

        $contents = @file_get_contents($metadataFile);
        if (!is_string($contents)) {
            throw new InvalidArgumentException('Batch metadata file is not readable: ' . $metadataFile);
        }

        try {
            $metadata = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Batch metadata file must contain valid JSON.', previous: $exception);
        }
        if (!is_array($metadata)) {
            throw new InvalidArgumentException('Batch metadata file must decode to an object keyed by basename.');
        }

        $normalized = [];
        foreach ($metadata as $filename => $value) {
            if (!is_string($filename) || !is_array($value)) {
                throw new InvalidArgumentException('Batch metadata file values must be objects keyed by basename.');
            }
            $normalized[$filename] = $value;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed>|null $metadata
     * @return array<string, mixed>
     */
    public function processFile(
        string $filepath,
        string $outputFolder,
        ?array $metadata,
        ?int $minLength,
        callable $converter,
        ?callable $textLength = null
    ): array {
        $filename = basename($filepath);

        if ($this->writer->markdownExists($outputFolder, $filename)) {
            return $this->result('skipped-existing', $filepath, ['filename' => $filename]);
        }

        if ($minLength !== null && $minLength > 0) {
            $filetype = $this->filetypeDetector->findFiletype($filepath);
            if ($filetype === 'other') {
                return $this->result('skipped-unsupported-filetype', $filepath, [
                    'filename' => $filename,
                    'filetype' => $filetype,
                ]);
            }

            $length = $textLength === null
                ? $this->embeddedTextLength($filepath)
                : (int) $textLength($filepath);
            if ($length < $minLength) {
                return $this->result('skipped-short-text', $filepath, [
                    'filename' => $filename,
                    'filetype' => $filetype,
                    'text_length' => $length,
                    'min_length' => $minLength,
                ]);
            }
        }

        try {
            $conversion = $this->normalizeConversion($converter($filepath, $metadata));
            if (trim($conversion['text']) === '') {
                return $this->result('skipped-empty-output', $filepath, ['filename' => $filename]);
            }

            $subfolder = $this->writer->saveMarkdown(
                $outputFolder,
                $filename,
                $conversion['text'],
                $conversion['images'],
                $conversion['metadata']
            );
        } catch (Throwable $throwable) {
            return $this->result('error', $filepath, [
                'filename' => $filename,
                'error' => $throwable->getMessage(),
                'error_output' => $this->conversionErrorOutput($filepath, $throwable),
                'writes_markdown' => false,
                'executes_python_or_models' => false,
                'executes_external_pdf_tools' => false,
            ]);
        }

        return $this->result('converted', $filepath, [
            'filename' => $filename,
            'output_folder' => $subfolder,
            'markdown' => $this->writer->getMarkdownFilepath($outputFolder, $filename),
            'images' => array_keys($conversion['images']),
        ]);
    }

    /**
     * @return list<string>
     */
    private function inputFiles(string $inputFolder): array
    {
        if (!is_dir($inputFolder)) {
            throw new InvalidArgumentException('Batch input folder does not exist: ' . $inputFolder);
        }

        $files = [];
        foreach (scandir($inputFolder) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $inputFolder . DIRECTORY_SEPARATOR . $entry;
            if (is_file($path)) {
                $files[] = $path;
            }
        }
        sort($files, SORT_STRING);

        return $files;
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
            throw new InvalidArgumentException('Batch converter must return text or a conversion array.');
        }

        $text = $conversion['text']
            ?? $conversion['full_text']
            ?? $conversion['markdown']
            ?? $conversion[0]
            ?? '';
        $images = $conversion['images'] ?? $conversion[1] ?? [];
        $metadata = $conversion['metadata'] ?? $conversion['out_metadata'] ?? $conversion[2] ?? [];

        if (!is_array($images) || !is_array($metadata)) {
            throw new InvalidArgumentException('Batch converter images and metadata must be arrays.');
        }

        return [
            'text' => (string) $text,
            'images' => $images,
            'metadata' => $metadata,
        ];
    }

    private function embeddedTextLength(string $filepath): int
    {
        $bytes = @file_get_contents($filepath);
        if (!is_string($bytes)) {
            return 0;
        }

        return $this->length(trim($this->textExtractor->extractPlainText($bytes)));
    }

    private function length(string $text): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($text, 'UTF-8');
        }

        return strlen($text);
    }

    /**
     * @return array{message_line: string, traceback: string, traceback_available: bool, review_only: true}
     */
    private function conversionErrorOutput(string $filepath, Throwable $throwable): array
    {
        $trace = $throwable->getTraceAsString();
        $traceback = get_class($throwable) . ': ' . $throwable->getMessage();
        if ($trace !== '') {
            $traceback .= "\n" . $trace;
        }

        return [
            'message_line' => 'Error converting ' . $filepath . ': ' . $throwable->getMessage(),
            'traceback' => $traceback,
            'traceback_available' => $traceback !== '',
            'review_only' => true,
        ];
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function result(string $status, string $filepath, array $extra = []): array
    {
        return ['status' => $status, 'filepath' => $filepath] + $extra;
    }
}
