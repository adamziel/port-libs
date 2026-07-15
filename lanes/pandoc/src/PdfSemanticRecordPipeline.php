<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

use RuntimeException;

/** Run and audit an ordered set of document-level PDF semantic processors. */
final class PdfSemanticRecordPipeline
{
    /** @var list<PdfSemanticRecordProcessor> */
    private readonly array $processors;

    /** @param list<PdfSemanticRecordProcessor> $processors */
    public function __construct(array $processors)
    {
        $names = [];
        foreach ($processors as $processor) {
            if (!$processor instanceof PdfSemanticRecordProcessor) {
                throw new RuntimeException('PDF semantic pipelines require PdfSemanticRecordProcessor values.');
            }
            if (isset($names[$processor->name()])) {
                throw new RuntimeException('PDF semantic processor names must be unique within one pipeline.');
            }
            $names[$processor->name()] = true;
        }
        $this->processors = array_values($processors);
    }

    /**
     * @param list<array{text:string,layout:array<string,mixed>|null}> $records
     * @return array{
     *   records:list<array{text:string,layout:array<string,mixed>|null}>,
     *   trace:list<array<string,mixed>>
     * }
     */
    public function run(array $records): array
    {
        $trace = [];
        foreach ($this->processors as $processor) {
            $before = $this->projection($records);
            $processed = $processor->process($records);
            $records = $this->validatedRecords($processed, $processor->name());
            $after = $this->projection($records);
            $trace[] = [
                'processor' => $processor->name(),
                'inputRecords' => $before['records'],
                'outputRecords' => $after['records'],
                'recordDelta' => $after['records'] - $before['records'],
                'inputTextBytes' => $before['bytes'],
                'outputTextBytes' => $after['bytes'],
                'textByteDelta' => $after['bytes'] - $before['bytes'],
                'changed' => $before['digest'] !== $after['digest'],
                'inputDigest' => $before['digest'],
                'outputDigest' => $after['digest'],
            ];
        }

        return compact('records', 'trace');
    }

    /**
     * @param array<mixed> $records
     * @return list<array{text:string,layout:array<string,mixed>|null}>
     */
    private function validatedRecords(array $records, string $processor): array
    {
        $validated = [];
        foreach ($records as $record) {
            if (!is_array($record)
                || !is_string($record['text'] ?? null)
                || (($record['layout'] ?? null) !== null && !is_array($record['layout']))) {
                throw new RuntimeException("PDF semantic processor {$processor} returned an invalid record.");
            }
            $validated[] = ['text' => $record['text'], 'layout' => $record['layout'] ?? null];
        }

        return $validated;
    }

    /** @param list<array{text:string,layout:array<string,mixed>|null}> $records @return array{records:int,bytes:int,digest:string} */
    private function projection(array $records): array
    {
        $hash = hash_init('sha256');
        $bytes = 0;
        foreach ($records as $record) {
            $text = is_string($record['text'] ?? null) ? $record['text'] : '';
            $bytes += strlen($text);
            hash_update($hash, pack('N', strlen($text)) . $text);
            $layout = json_encode(
                is_array($record['layout'] ?? null) ? $record['layout'] : null,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
            );
            $layout = is_string($layout) ? $layout : 'null';
            hash_update($hash, pack('N', strlen($layout)) . $layout);
        }

        return ['records' => count($records), 'bytes' => $bytes, 'digest' => hash_final($hash)];
    }
}
