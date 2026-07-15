<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

/**
 * Adapts the existing pure-PHP parser to the provider-neutral page facts IR.
 */
final class NativePdfFactsProvider implements PdfFactsProvider
{
    public function providerId(): string
    {
        return 'native-php-v1';
    }

    /**
     * @param array<string, mixed> $options
     */
    public function extract(string $pdfBytes, array $options = []): PdfDocumentFacts
    {
        $sourceHash = hash('sha256', $pdfBytes);
        $extractor = new PdfTextExtractor($options);
        $inventory = $extractor->extractPageInventory($pdfBytes);
        $previewPages = (new MarkerAppPreview())->openPdfSummary($pdfBytes)['pages'];
        $selected = array_fill_keys($inventory['pageNumbers'], true);
        $pageRows = [];

        foreach ($inventory['pageNumbers'] as $pageNumber) {
            $preview = $previewPages[$pageNumber - 1] ?? [];
            $pageRows[$pageNumber] = [
                'schemaVersion' => PdfPageFacts::SCHEMA_VERSION,
                'pageNumber' => $pageNumber,
                'pageObject' => is_int($preview['object_id'] ?? null) ? $preview['object_id'] : null,
                'label' => is_string($preview['page_label'] ?? null) ? $preview['page_label'] : (string) $pageNumber,
                'geometry' => $preview,
                'text' => [
                    'lines' => [],
                    'runs' => [],
                    'spans' => [],
                    'positionedRunsLimited' => false,
                ],
                'graphics' => [
                    'filledRectangles' => [],
                    'images' => [],
                    'forms' => [],
                ],
                'annotations' => [
                    'links' => [],
                    'text' => [],
                    'fileAttachments' => [],
                    'popups' => [],
                    'appearances' => [],
                ],
                'structure' => [],
                'issues' => [],
            ];
        }

        $pageStreamIndexes = [];
        foreach ($extractor->streamImportFacts($pdfBytes) as $streamFacts) {
            $pageNumber = $streamFacts['page'];
            if (!isset($pageRows[$pageNumber])) {
                continue;
            }
            $pageStreamIndexes[$pageNumber] = ($pageStreamIndexes[$pageNumber] ?? 0) + 1;
            $pageStreamIndex = $pageStreamIndexes[$pageNumber];
            $pageObject = $streamFacts['pageObject'];
            if ($pageRows[$pageNumber]['pageObject'] === null && $pageObject !== null) {
                $pageRows[$pageNumber]['pageObject'] = $pageObject;
            }

            foreach ($streamFacts['textLineItems'] as $index => $line) {
                $pageRows[$pageNumber]['text']['lines'][] = $this->decorateFact(
                    $line,
                    'line',
                    $sourceHash,
                    $pageNumber,
                    $pageObject,
                    $pageStreamIndex,
                    $index
                );
            }
            foreach ($streamFacts['textRuns'] as $index => $text) {
                $pageRows[$pageNumber]['text']['runs'][] = $this->decorateFact(
                    ['text' => $text],
                    'run',
                    $sourceHash,
                    $pageNumber,
                    $pageObject,
                    $pageStreamIndex,
                    $index
                );
            }
            foreach ($streamFacts['positionedTextRuns'] as $index => $span) {
                $pageRows[$pageNumber]['text']['spans'][] = $this->decorateFact(
                    $span,
                    'span',
                    $sourceHash,
                    $pageNumber,
                    $pageObject,
                    $pageStreamIndex,
                    $index
                );
            }
            foreach ($streamFacts['filledRectangles'] as $index => $rectangle) {
                $pageRows[$pageNumber]['graphics']['filledRectangles'][] = $this->decorateFact(
                    $rectangle,
                    'rectangle',
                    $sourceHash,
                    $pageNumber,
                    $pageObject,
                    $pageStreamIndex,
                    $index
                );
            }
            if ($streamFacts['positionedTextRunsLimited']) {
                $pageRows[$pageNumber]['text']['positionedRunsLimited'] = true;
            }
        }

        $imageIndexes = [];
        $images = $this->optionEnabled($options, ['pdfCollectImagePlacements', 'collectPdfImagePlacements'], true)
            ? $extractor->extractImagePlacements($pdfBytes)
            : [];
        foreach ($images as $image) {
            $pageNumber = $image['page'];
            if (!isset($pageRows[$pageNumber])) {
                continue;
            }
            $index = $imageIndexes[$pageNumber] ?? 0;
            $imageIndexes[$pageNumber] = $index + 1;
            $pageRows[$pageNumber]['graphics']['images'][] = $this->decorateFact(
                $image,
                'image',
                $sourceHash,
                $pageNumber,
                is_int($image['pageObject'] ?? null) ? $image['pageObject'] : null,
                is_int($image['contentStream'] ?? null) ? $image['contentStream'] : 0,
                $index
            );
        }
        $formIndexes = [];
        $forms = $this->optionEnabled($options, ['pdfCollectFormXObjectPlacements', 'collectPdfFormXObjectPlacements'], true)
            ? $extractor->extractFormXObjectPlacements($pdfBytes)
            : [];
        foreach ($forms as $form) {
            $pageNumber = $form['page'];
            if (!isset($pageRows[$pageNumber])) {
                continue;
            }
            $index = $formIndexes[$pageNumber] ?? 0;
            $formIndexes[$pageNumber] = $index + 1;
            $pageRows[$pageNumber]['graphics']['forms'][] = $this->decorateFact(
                $form,
                'form',
                $sourceHash,
                $pageNumber,
                is_int($form['pageObject'] ?? null) ? $form['pageObject'] : null,
                is_int($form['contentStream'] ?? null) ? $form['contentStream'] : 0,
                $index
            );
        }

        $diagnostics = $extractor->diagnostics($pdfBytes);
        $structure = $this->takeKeysWithPrefixes($diagnostics, ['tagged']);
        $annotationKeys = [
            'linkAnnotations' => 'links',
            'textAnnotations' => 'text',
            'fileAttachmentAnnotations' => 'fileAttachments',
            'popupAnnotations' => 'popups',
            'appearanceAnnotations' => 'appearances',
        ];
        $unassignedAnnotations = array_fill_keys(array_values($annotationKeys), []);
        foreach ($annotationKeys as $diagnosticKey => $pageKey) {
            $rows = is_array($diagnostics[$diagnosticKey] ?? null) ? $diagnostics[$diagnosticKey] : [];
            unset($diagnostics[$diagnosticKey]);
            $annotationIndexes = [];
            foreach ($rows as $annotation) {
                if (!is_array($annotation)) {
                    continue;
                }
                $pageNumber = $this->recordPageNumber($annotation);
                if ($pageNumber === null) {
                    $unassignedAnnotations[$pageKey][] = $annotation;
                    continue;
                }
                if (!isset($pageRows[$pageNumber])) {
                    continue;
                }
                $index = $annotationIndexes[$pageNumber] ?? 0;
                $annotationIndexes[$pageNumber] = $index + 1;
                $pageRows[$pageNumber]['annotations'][$pageKey][] = $this->decorateFact(
                    $annotation,
                    'annotation-' . $pageKey,
                    $sourceHash,
                    $pageNumber,
                    is_int($annotation['pageObject'] ?? null) ? $annotation['pageObject'] : null,
                    0,
                    $index
                );
            }
        }

        $issues = is_array($diagnostics['pageExtractionIssues'] ?? null)
            ? $diagnostics['pageExtractionIssues']
            : [];
        unset($diagnostics['pageExtractionIssues']);
        $issueIndexes = [];
        foreach ($issues as $issue) {
            if (!is_array($issue)) {
                continue;
            }
            $pageNumber = $this->recordPageNumber($issue);
            if ($pageNumber === null || !isset($pageRows[$pageNumber])) {
                continue;
            }
            $index = $issueIndexes[$pageNumber] ?? 0;
            $issueIndexes[$pageNumber] = $index + 1;
            $pageRows[$pageNumber]['issues'][] = $this->decorateFact(
                $issue,
                'issue',
                $sourceHash,
                $pageNumber,
                is_int($issue['pageObject'] ?? null) ? $issue['pageObject'] : null,
                0,
                $index
            );
        }

        $pages = [];
        foreach ($pageRows as $pageNumber => $page) {
            if (isset($selected[$pageNumber])) {
                $pages[] = PdfPageFacts::fromArray($page);
            }
        }

        return new PdfDocumentFacts(
            $this->providerId(),
            ['sha256' => $sourceHash, 'byteLength' => strlen($pdfBytes)],
            $inventory,
            $pages,
            $structure,
            $diagnostics,
            $unassignedAnnotations
        );
    }

    /**
     * @param array<string, mixed> $fact
     * @return array<string, mixed>
     */
    private function decorateFact(
        array $fact,
        string $kind,
        string $sourceHash,
        int $pageNumber,
        ?int $pageObject,
        int $pageStreamIndex,
        int $index
    ): array {
        $nativeId = is_string($fact['id'] ?? null) ? $fact['id'] : null;
        unset($fact['id']);
        // `stream` is the extractor's range-local counter. Preserve it as raw
        // evidence, but do not let it destabilize IDs when page N is later
        // extracted by itself in a resumed request.
        $identityFact = $fact;
        unset($identityFact['stream'], $identityFact['page'], $identityFact['pageObject']);
        $identity = [
            'source' => $sourceHash,
            'kind' => $kind,
            'page' => $pageNumber,
            'pageObject' => $pageObject,
            'pageStream' => $pageStreamIndex,
            'index' => $index,
            'fact' => $identityFact,
        ];
        $encodedIdentity = json_encode($identity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        $id = $kind . '-' . substr(hash('sha256', is_string($encodedIdentity) ? $encodedIdentity : serialize($identity)), 0, 24);

        return [
            'id' => $id,
            'provenance' => [
                'provider' => $this->providerId(),
                'kind' => $kind,
                'page' => $pageNumber,
                'pageObject' => $pageObject,
                'pageStream' => $pageStreamIndex,
                'index' => $index,
                'nativeId' => $nativeId,
            ],
        ] + $fact;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function recordPageNumber(array $record): ?int
    {
        foreach (['page', 'pageNumber', 'page_number'] as $key) {
            if (is_int($record[$key] ?? null) && $record[$key] > 0) {
                return $record[$key];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $source
     * @param list<string> $prefixes
     * @return array<string, mixed>
     */
    private function takeKeysWithPrefixes(array &$source, array $prefixes): array
    {
        $taken = [];
        foreach (array_keys($source) as $key) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    $taken[$key] = $source[$key];
                    unset($source[$key]);
                    break;
                }
            }
        }

        return $taken;
    }

    /**
     * @param array<string, mixed> $options
     * @param list<string> $keys
     */
    private function optionEnabled(array $options, array $keys, bool $default): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $options)) {
                return (bool) $options[$key];
            }
        }

        return $default;
    }
}
