<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class IpynbReader
{
    private const MAX_CELLS = 200;
    private const MAX_CELL_SOURCE_BYTES = 1048576;

    private readonly MarkdownReader $markdownReader;

    public function __construct(?MarkdownReader $markdownReader = null)
    {
        $this->markdownReader = $markdownReader ?? new MarkdownReader(['yamlMetadata' => false]);
    }

    public function read(string $json): AstNode
    {
        $notebook = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($notebook)) {
            throw new \InvalidArgumentException('IPYNB source must decode to a JSON object');
        }

        $cells = $notebook['cells'] ?? null;
        if (!is_array($cells)) {
            throw new \InvalidArgumentException('IPYNB notebook is missing a cells array');
        }
        if (count($cells) > self::MAX_CELLS) {
            throw new \InvalidArgumentException('IPYNB notebook exceeds the bounded native reader cell limit');
        }

        $metadata = isset($notebook['metadata']) && is_array($notebook['metadata']) ? $notebook['metadata'] : [];
        $notebookLanguageHint = $this->notebookLanguageHint($metadata);
        $notebookLanguageMetadataSummary = $this->notebookLanguageMetadataSummary($metadata, $notebookLanguageHint);
        $language = $notebookLanguageHint['language'];

        $blocks = [];
        $cellSummaries = [];
        $markdownCellCount = 0;
        $codeCellCount = 0;
        $rawCellCount = 0;
        $attachmentCount = 0;
        $outputCount = 0;
        $unsupportedResourceCount = 0;
        $attachmentMedia = [];
        $attachmentMediaDiagnostics = [];
        $outputBytePresenceCount = 0;
        $outputMimeBundleCount = 0;
        $outputGroupCount = 0;
        $outputStreamGroupCount = 0;
        $outputRepeatedStreamNameCount = 0;
        $outputMimeBundleDigestCount = 0;
        $outputRepeatedMimeBundleDigestCount = 0;
        $outputRepeatedMimeBundleDigestDuplicateCount = 0;
        $outputAggregateDiagnostics = [];
        $metadataKeys = $this->metadataKeys($metadata);
        $sourceShapeCounts = [
            'string' => 0,
            'list' => 0,
            'missing' => 0,
            'null' => 0,
        ];
        $sourceContentStateCounts = [
            'empty' => 0,
            'whitespace-only' => 0,
            'content' => 0,
        ];
        $sourceLineEndingCounts = [
            'lf' => 0,
            'crlf' => 0,
            'cr' => 0,
        ];
        $sourceFingerprintCounts = [];
        $sourceFingerprintIndexes = [];
        $totalSourceBytes = 0;
        $totalSourceLineCount = 0;
        $mixedLineEndingSourceCount = 0;
        $trailingLineEndingSourceCount = 0;
        $languageHintCounts = [];
        $languageHintSourceCounts = [];
        $languageHintDiagnosticCounts = [];
        $cellMetadataLanguageHintCount = 0;
        $notebookLanguageFallbackCount = 0;
        $unknownLanguageHintCount = 0;
        $mismatchedLanguageHintCount = 0;
        $outputLanguageLikeMimeCount = 0;
        $outputLanguageLikeMimeCellCount = 0;
        $outputLanguageMatchCount = 0;
        $outputLanguageMismatchCount = 0;
        $outputLanguageUnknownCount = 0;
        $streamAssociatedOutputLanguageCount = 0;
        $streamAssociatedOutputLanguageMismatchCount = 0;
        $outputLanguageCounts = [];
        $outputLanguageMimeCounts = [];
        $outputLanguageDiagnosticCounts = [];
        $outputLanguagePolicyCounts = [
            'digest-associated' => 0,
            'digest-missing' => 0,
            'match' => 0,
            'mismatch' => 0,
            'unknown' => 0,
        ];
        $outputLanguageDigestAssociationCount = 0;
        $outputLanguageDigestMissingCount = 0;

        foreach ($cells as $index => $cell) {
            if (!is_array($cell)) {
                throw new \InvalidArgumentException("IPYNB cell {$index} is not an object");
            }

            $cellType = $this->cellType($cell['cell_type'] ?? null);
            $sourceReview = $this->sourceReview(
                array_key_exists('source', $cell) ? $cell['source'] : null,
                array_key_exists('source', $cell),
                "IPYNB cell {$index} source"
            );
            $source = $sourceReview['source'];
            $sourceSummary = $sourceReview['summary'];
            if (strlen($source) > self::MAX_CELL_SOURCE_BYTES) {
                throw new \InvalidArgumentException("IPYNB cell {$index} exceeds the bounded native reader source limit");
            }

            $attachments = isset($cell['attachments']) && is_array($cell['attachments']) ? $cell['attachments'] : [];
            $outputs = isset($cell['outputs']) && is_array($cell['outputs']) ? $cell['outputs'] : [];
            $attachmentSummary = $this->attachmentSummary($attachments, $index);
            $outputSummary = $this->outputSummary($outputs);
            $cellMetadata = isset($cell['metadata']) && is_array($cell['metadata']) ? $cell['metadata'] : [];
            $cellMetadataKeys = $this->metadataKeys($cellMetadata);
            $cellTags = $this->metadataStringList($cellMetadata['tags'] ?? null);
            $languageHintSummary = $this->cellLanguageHintSummary($cellMetadata, $notebookLanguageHint);
            $outputLanguageSummary = $this->outputLanguageConsistencySummary($outputSummary, $languageHintSummary);
            $cellDiagnostics = $this->cellDiagnostics($attachmentSummary, $outputSummary);

            $attachmentCount += $attachmentSummary['count'];
            $outputCount += $outputSummary['count'];
            $unsupportedResourceCount += $attachmentSummary['count'] + $outputSummary['count'];
            $attachmentMedia = array_merge($attachmentMedia, $attachmentSummary['media']);
            $attachmentMediaDiagnostics = array_merge($attachmentMediaDiagnostics, $attachmentSummary['diagnostics']);
            $outputBytePresenceCount += $outputSummary['bytePresenceCount'];
            $outputMimeBundleCount += $outputSummary['mimeBundleCount'];
            $outputGroupCount += $outputSummary['groupCount'];
            $outputStreamGroupCount += $outputSummary['streamGroupCount'];
            $outputRepeatedStreamNameCount += count($outputSummary['repeatedStreamNames']);
            $outputMimeBundleDigestCount += count($outputSummary['mimeBundleDigests']);
            $outputRepeatedMimeBundleDigestCount += count($outputSummary['repeatedMimeBundleDigests']);
            $outputRepeatedMimeBundleDigestDuplicateCount += $outputSummary['repeatedMimeBundleDigestDuplicateCount'];
            foreach ($outputSummary['aggregateDiagnostics'] as $diagnostic) {
                $outputAggregateDiagnostics[] = $diagnostic;
            }
            $sourceShapeCounts[$sourceSummary['sourceShape']] = ($sourceShapeCounts[$sourceSummary['sourceShape']] ?? 0) + 1;
            $sourceContentStateCounts[$sourceSummary['sourceContentState']] = ($sourceContentStateCounts[$sourceSummary['sourceContentState']] ?? 0) + 1;
            foreach ($sourceSummary['sourceLineEndings'] as $lineEnding => $count) {
                $sourceLineEndingCounts[$lineEnding] = ($sourceLineEndingCounts[$lineEnding] ?? 0) + $count;
            }
            $sourceFingerprint = $sourceSummary['sourceFingerprint'];
            $sourceFingerprintCounts[$sourceFingerprint] = ($sourceFingerprintCounts[$sourceFingerprint] ?? 0) + 1;
            $sourceFingerprintIndexes[$sourceFingerprint][] = $index;
            $totalSourceBytes += $sourceSummary['sourceBytes'];
            $totalSourceLineCount += $sourceSummary['sourceLineCount'];
            if ($sourceSummary['sourceHasMixedLineEndings']) {
                $mixedLineEndingSourceCount++;
            }
            if ($sourceSummary['sourceHasTrailingLineEnding']) {
                $trailingLineEndingSourceCount++;
            }
            $languageHint = $languageHintSummary['languageHint'];
            $languageHintSource = $languageHintSummary['languageHintSource'];
            $languageHintCounts[$languageHint] = ($languageHintCounts[$languageHint] ?? 0) + 1;
            $languageHintSourceCounts[$languageHintSource] = ($languageHintSourceCounts[$languageHintSource] ?? 0) + 1;
            if ($languageHintSummary['languageHintIsCellMetadata']) {
                $cellMetadataLanguageHintCount++;
            }
            if ($languageHintSummary['languageHintIsNotebookFallback']) {
                $notebookLanguageFallbackCount++;
            }
            if ($languageHint === 'unknown') {
                $unknownLanguageHintCount++;
            }
            foreach ($languageHintSummary['languageHintDiagnostics'] as $diagnostic) {
                $languageHintDiagnosticCounts[$diagnostic] = ($languageHintDiagnosticCounts[$diagnostic] ?? 0) + 1;
                if ($diagnostic === 'language-hint-mismatch-notebook-language') {
                    $mismatchedLanguageHintCount++;
                }
            }
            $outputLanguageLikeMimeCount += $outputLanguageSummary['languageLikeMimeCount'];
            if ($outputLanguageSummary['languageLikeMimeCount'] > 0) {
                $outputLanguageLikeMimeCellCount++;
            }
            $outputLanguageMatchCount += $outputLanguageSummary['matchedLanguageCount'];
            $outputLanguageMismatchCount += $outputLanguageSummary['mismatchedLanguageCount'];
            $outputLanguageUnknownCount += $outputLanguageSummary['unknownLanguageCount'];
            $streamAssociatedOutputLanguageCount += $outputLanguageSummary['streamAssociatedLanguageCount'];
            $streamAssociatedOutputLanguageMismatchCount += $outputLanguageSummary['streamAssociatedMismatchCount'];
            foreach ($outputLanguageSummary['languageCounts'] as $languageName => $count) {
                $outputLanguageCounts[$languageName] = ($outputLanguageCounts[$languageName] ?? 0) + $count;
            }
            foreach ($outputLanguageSummary['mimeTypeCounts'] as $mimeType => $count) {
                $outputLanguageMimeCounts[$mimeType] = ($outputLanguageMimeCounts[$mimeType] ?? 0) + $count;
            }
            foreach ($outputLanguageSummary['diagnosticCounts'] as $diagnostic => $count) {
                $outputLanguageDiagnosticCounts[$diagnostic] = ($outputLanguageDiagnosticCounts[$diagnostic] ?? 0) + $count;
            }
            foreach ($outputLanguageSummary['policyCounts'] as $policy => $count) {
                $outputLanguagePolicyCounts[$policy] = ($outputLanguagePolicyCounts[$policy] ?? 0) + $count;
            }
            $outputLanguageDigestAssociationCount += $outputLanguageSummary['digestAssociatedLanguageCount'];
            $outputLanguageDigestMissingCount += $outputLanguageSummary['digestMissingLanguageCount'];

            $attributes = [
                'data-ipynb-cell-index' => (string) $index,
                'data-ipynb-cell-type' => $cellType,
            ];
            if ($attachmentSummary['count'] > 0) {
                $attributes['data-ipynb-attachment-count'] = (string) $attachmentSummary['count'];
            }
            if ($outputSummary['count'] > 0) {
                $attributes['data-ipynb-output-count'] = (string) $outputSummary['count'];
                $attributes['data-ipynb-output-indexes'] = implode(' ', array_map(static fn (int $index): string => (string) $index, $outputSummary['indexes']));
                $attributes['data-ipynb-output-display-order'] = implode(' ', $outputSummary['orderTypes']);
                $attributes['data-ipynb-output-group-count'] = (string) $outputSummary['groupCount'];
            }
            if ($outputSummary['streamGroupCount'] > 0) {
                $attributes['data-ipynb-output-stream-group-count'] = (string) $outputSummary['streamGroupCount'];
            }
            if ($outputSummary['mimeTypes'] !== []) {
                $attributes['data-ipynb-output-mime-types'] = implode(' ', $outputSummary['mimeTypes']);
            }
            if ($outputSummary['mimeBundleDigests'] !== []) {
                $attributes['data-ipynb-output-mime-bundle-digests'] = implode(' ', $outputSummary['mimeBundleDigests']);
            }
            if ($outputSummary['repeatedMimeBundleKeys'] !== []) {
                $attributes['data-ipynb-output-repeated-mime-keys'] = implode(' ', $outputSummary['repeatedMimeBundleKeys']);
            }
            if ($outputSummary['repeatedMimeBundleDigests'] !== []) {
                $attributes['data-ipynb-output-repeated-mime-bundle-digests'] = implode(' ', $outputSummary['repeatedMimeBundleDigests']);
                $attributes['data-ipynb-output-repeated-mime-bundle-digest-count'] = (string) count($outputSummary['repeatedMimeBundleDigests']);
                $attributes['data-ipynb-output-repeated-mime-bundle-duplicate-count'] = (string) $outputSummary['repeatedMimeBundleDigestDuplicateCount'];
            }
            if ($outputSummary['streamNames'] !== []) {
                $attributes['data-ipynb-output-stream-names'] = implode(' ', $outputSummary['streamNames']);
            }
            if ($outputSummary['repeatedStreamNames'] !== []) {
                $attributes['data-ipynb-output-repeated-stream-names'] = implode(' ', $outputSummary['repeatedStreamNames']);
            }
            if ($outputSummary['bytePresenceCount'] > 0) {
                $attributes['data-ipynb-output-byte-policy'] = 'metadata-only';
                $attributes['data-ipynb-output-byte-presence-count'] = (string) $outputSummary['bytePresenceCount'];
            }
            if ($outputSummary['aggregateDiagnostics'] !== []) {
                $attributes['data-ipynb-output-aggregate-diagnostic-count'] = (string) count($outputSummary['aggregateDiagnostics']);
            }
            if ($outputLanguageSummary['languageLikeMimeCount'] > 0) {
                $attributes['data-ipynb-output-language-like-mime-count'] = (string) $outputLanguageSummary['languageLikeMimeCount'];
                $attributes['data-ipynb-output-language-hints'] = implode(' ', $outputLanguageSummary['languages']);
                $attributes['data-ipynb-output-language-policy'] = 'metadata-only';
            }
            if ($outputLanguageSummary['diagnostics'] !== []) {
                $attributes['data-ipynb-output-language-diagnostics'] = implode(' ', $outputLanguageSummary['diagnostics']);
            }
            if (array_key_exists('execution_count', $cell) && is_int($cell['execution_count'])) {
                $attributes['data-ipynb-execution-count'] = (string) $cell['execution_count'];
            }
            if ($cellTags !== []) {
                $attributes['data-ipynb-cell-tags'] = implode(' ', $cellTags);
            }
            if ($cellDiagnostics !== []) {
                $attributes['data-ipynb-diagnostics'] = implode(' ', $cellDiagnostics);
            }
            if ($attachmentSummary['media'] !== []) {
                $attributes['data-ipynb-attachment-media-count'] = (string) count($attachmentSummary['media']);
            }
            if ($attachmentSummary['diagnostics'] !== []) {
                $attributes['data-ipynb-attachment-diagnostics'] = implode(' ', $attachmentSummary['diagnostics']);
            }
            if ($languageHint !== 'unknown') {
                $attributes['data-ipynb-language-hint'] = $languageHint;
                $attributes['data-ipynb-language-hint-source'] = $languageHintSource;
            }
            if ($languageHintSummary['languageHintDiagnostics'] !== []) {
                $attributes['data-ipynb-language-diagnostics'] = implode(' ', $languageHintSummary['languageHintDiagnostics']);
            }

            $children = match ($cellType) {
                'markdown' => $this->markdownCellBlocks($source),
                'code' => [$this->codeCellBlock($source, $languageHintSummary, $index, $cell)],
                'raw' => [$this->rawCellBlock($source, $index)],
                default => [$this->unsupportedCellBlock($source, $cellType, $index)],
            };

            if ($cellType === 'markdown') {
                $markdownCellCount++;
            } elseif ($cellType === 'code') {
                $codeCellCount++;
            } elseif ($cellType === 'raw') {
                $rawCellCount++;
            }

            $cellAttrs = [
                'classes' => ['ipynb-cell', 'ipynb-' . $cellType . '-cell'],
                'attributes' => $attributes,
                'ipynbCellType' => $cellType,
                'ipynbCellIndex' => $index,
                'ipynbAttachmentCount' => $attachmentSummary['count'],
                'ipynbAttachmentNames' => $attachmentSummary['names'],
                'ipynbAttachmentMimeTypes' => $attachmentSummary['mimeTypes'],
                'ipynbAttachmentMedia' => $attachmentSummary['media'],
                'ipynbAttachmentMediaDiagnostics' => $attachmentSummary['diagnostics'],
                'ipynbOutputCount' => $outputSummary['count'],
                'ipynbOutputTypes' => $outputSummary['types'],
                'ipynbOutputOrderTypes' => $outputSummary['orderTypes'],
                'ipynbOutputIndexes' => $outputSummary['indexes'],
                'ipynbOutputSummaries' => $outputSummary['outputs'],
                'ipynbOutputMimeTypes' => $outputSummary['mimeTypes'],
                'ipynbOutputMimeBundleCount' => $outputSummary['mimeBundleCount'],
                'ipynbOutputMimeBundleDigests' => $outputSummary['mimeBundleDigests'],
                'ipynbOutputMimeBundleDigestRecords' => $outputSummary['mimeBundleDigestRecords'],
                'ipynbOutputRepeatedMimeBundleDigests' => $outputSummary['repeatedMimeBundleDigests'],
                'ipynbOutputRepeatedMimeBundleDigestRecords' => $outputSummary['repeatedMimeBundleDigestRecords'],
                'ipynbOutputRepeatedMimeBundleDigestCount' => count($outputSummary['repeatedMimeBundleDigests']),
                'ipynbOutputRepeatedMimeBundleDigestDuplicateCount' => $outputSummary['repeatedMimeBundleDigestDuplicateCount'],
                'ipynbOutputBytePresenceCount' => $outputSummary['bytePresenceCount'],
                'ipynbOutputGroups' => $outputSummary['groups'],
                'ipynbOutputGroupCount' => $outputSummary['groupCount'],
                'ipynbOutputStreamGroups' => $outputSummary['streamGroups'],
                'ipynbOutputStreamGroupCount' => $outputSummary['streamGroupCount'],
                'ipynbOutputStreamNames' => $outputSummary['streamNames'],
                'ipynbOutputRepeatedStreamNames' => $outputSummary['repeatedStreamNames'],
                'ipynbOutputRepeatedStreamNameRecords' => $outputSummary['repeatedStreamNameRecords'],
                'ipynbOutputRepeatedMimeBundleKeys' => $outputSummary['repeatedMimeBundleKeys'],
                'ipynbOutputRepeatedMimeBundleRecords' => $outputSummary['repeatedMimeBundleRecords'],
                'ipynbOutputAggregateDiagnostics' => $outputSummary['aggregateDiagnostics'],
                'ipynbOutputLanguageLikeMimeCount' => $outputLanguageSummary['languageLikeMimeCount'],
                'ipynbOutputLanguageLikeMimeTypes' => $outputLanguageSummary['mimeTypes'],
                'ipynbOutputLanguageHints' => $outputLanguageSummary['languages'],
                'ipynbOutputLanguageRecords' => $outputLanguageSummary['records'],
                'ipynbOutputLanguageMatchCount' => $outputLanguageSummary['matchedLanguageCount'],
                'ipynbOutputLanguageMismatchCount' => $outputLanguageSummary['mismatchedLanguageCount'],
                'ipynbOutputLanguageUnknownCount' => $outputLanguageSummary['unknownLanguageCount'],
                'ipynbOutputLanguageDiagnostics' => $outputLanguageSummary['diagnostics'],
                'ipynbOutputLanguageDiagnosticCounts' => $outputLanguageSummary['diagnosticCounts'],
                'ipynbOutputMimeLanguagePolicySummary' => $outputLanguageSummary['policySummary'],
                'ipynbOutputMimeLanguagePolicyRecords' => $outputLanguageSummary['records'],
                'ipynbUnsupportedResourceCount' => $attachmentSummary['count'] + $outputSummary['count'],
                'ipynbUnsupportedResourceDiagnostics' => $cellDiagnostics,
                'ipynbCellMetadataKeys' => $cellMetadataKeys,
                'ipynbCellTags' => $cellTags,
                'ipynbLanguageHint' => $languageHint,
                'ipynbLanguageHintSource' => $languageHintSource,
                'ipynbLanguageHintIsCellMetadata' => $languageHintSummary['languageHintIsCellMetadata'],
                'ipynbLanguageHintIsNotebookFallback' => $languageHintSummary['languageHintIsNotebookFallback'],
                'ipynbLanguageHintMatchesNotebook' => $languageHintSummary['languageHintMatchesNotebook'],
                'ipynbLanguageHintDiagnostics' => $languageHintSummary['languageHintDiagnostics'],
            ];
            if (array_key_exists('id', $cell) && is_string($cell['id']) && $cell['id'] !== '') {
                $cellAttrs['ipynbCellId'] = $cell['id'];
            }
            if (array_key_exists('execution_count', $cell) && (is_int($cell['execution_count']) || $cell['execution_count'] === null)) {
                $cellAttrs['ipynbExecutionCount'] = $cell['execution_count'];
            }

            $blocks[] = new AstNode('div', $cellAttrs, $children);
            $cellSummaries[] = [
                'index' => $index,
                'type' => $cellType,
                'sourceShape' => $sourceSummary['sourceShape'],
                'sourcePartCount' => $sourceSummary['sourcePartCount'],
                'sourceBytes' => $sourceSummary['sourceBytes'],
                'sourceLineCount' => $sourceSummary['sourceLineCount'],
                'sourceLineEndingCount' => $sourceSummary['sourceLineEndingCount'],
                'sourceLineEndings' => $sourceSummary['sourceLineEndings'],
                'sourceHasTrailingLineEnding' => $sourceSummary['sourceHasTrailingLineEnding'],
                'sourceHasMixedLineEndings' => $sourceSummary['sourceHasMixedLineEndings'],
                'sourceContentState' => $sourceSummary['sourceContentState'],
                'sourceDigest' => $sourceSummary['sourceDigest'],
                'sourceFingerprint' => $sourceFingerprint,
                'attachmentCount' => $attachmentSummary['count'],
                'attachmentMimeTypes' => $attachmentSummary['mimeTypes'],
                'attachmentMedia' => $attachmentSummary['media'],
                'attachmentMediaDiagnostics' => $attachmentSummary['diagnostics'],
                'outputCount' => $outputSummary['count'],
                'outputTypes' => $outputSummary['types'],
                'outputOrderTypes' => $outputSummary['orderTypes'],
                'outputIndexes' => $outputSummary['indexes'],
                'outputSummaries' => $outputSummary['outputs'],
                'outputMimeTypes' => $outputSummary['mimeTypes'],
                'outputMimeBundleCount' => $outputSummary['mimeBundleCount'],
                'outputMimeBundleDigests' => $outputSummary['mimeBundleDigests'],
                'outputRepeatedMimeBundleDigests' => $outputSummary['repeatedMimeBundleDigests'],
                'outputRepeatedMimeBundleDigestCount' => count($outputSummary['repeatedMimeBundleDigests']),
                'outputRepeatedMimeBundleDigestDuplicateCount' => $outputSummary['repeatedMimeBundleDigestDuplicateCount'],
                'outputBytePresenceCount' => $outputSummary['bytePresenceCount'],
                'outputGroupCount' => $outputSummary['groupCount'],
                'outputStreamGroupCount' => $outputSummary['streamGroupCount'],
                'outputGroups' => $outputSummary['groups'],
                'outputStreamGroups' => $outputSummary['streamGroups'],
                'outputRepeatedStreamNames' => $outputSummary['repeatedStreamNames'],
                'outputAggregateDiagnosticCount' => count($outputSummary['aggregateDiagnostics']),
                'outputAggregateDiagnostics' => $outputSummary['aggregateDiagnostics'],
                'outputLanguageLikeMimeCount' => $outputLanguageSummary['languageLikeMimeCount'],
                'outputLanguageLikeMimeTypes' => $outputLanguageSummary['mimeTypes'],
                'outputLanguageHints' => $outputLanguageSummary['languages'],
                'outputLanguageRecords' => $outputLanguageSummary['records'],
                'outputLanguageMatchCount' => $outputLanguageSummary['matchedLanguageCount'],
                'outputLanguageMismatchCount' => $outputLanguageSummary['mismatchedLanguageCount'],
                'outputLanguageUnknownCount' => $outputLanguageSummary['unknownLanguageCount'],
                'outputLanguageDiagnostics' => $outputLanguageSummary['diagnostics'],
                'outputLanguageDiagnosticCounts' => $outputLanguageSummary['diagnosticCounts'],
                'outputMimeLanguagePolicySummary' => $outputLanguageSummary['policySummary'],
                'outputMimeLanguagePolicyRecords' => $outputLanguageSummary['records'],
                'unsupportedResourceCount' => $attachmentSummary['count'] + $outputSummary['count'],
                'diagnostics' => $cellDiagnostics,
                'metadataKeys' => $cellMetadataKeys,
                'tags' => $cellTags,
                'languageHint' => $languageHint,
                'languageHintSource' => $languageHintSource,
                'languageHintIsCellMetadata' => $languageHintSummary['languageHintIsCellMetadata'],
                'languageHintIsNotebookFallback' => $languageHintSummary['languageHintIsNotebookFallback'],
                'languageHintMatchesNotebook' => $languageHintSummary['languageHintMatchesNotebook'],
                'languageHintDiagnostics' => $languageHintSummary['languageHintDiagnostics'],
            ];
        }

        ksort($sourceFingerprintCounts);
        ksort($languageHintCounts);
        ksort($languageHintSourceCounts);
        ksort($languageHintDiagnosticCounts);
        ksort($outputLanguageCounts);
        ksort($outputLanguageMimeCounts);
        ksort($outputLanguageDiagnosticCounts);
        ksort($outputLanguagePolicyCounts);
        foreach ($cellSummaries as &$cellSummary) {
            $cellSummary['sourceFingerprintCount'] = $sourceFingerprintCounts[$cellSummary['sourceFingerprint']] ?? 1;
        }
        unset($cellSummary);

        $duplicateSourceFingerprints = [];
        $duplicateSourceCellCount = 0;
        foreach ($sourceFingerprintCounts as $sourceFingerprint => $count) {
            if ($count <= 1) {
                continue;
            }
            $duplicateSourceCellCount += $count;
            $duplicateSourceFingerprints[] = [
                'sourceFingerprint' => $sourceFingerprint,
                'count' => $count,
                'cellIndexes' => $sourceFingerprintIndexes[$sourceFingerprint] ?? [],
            ];
        }

        return new AstNode('document', [
            'sourceFormat' => 'ipynb',
            'notebookCellCount' => count($cells),
            'notebookMarkdownCellCount' => $markdownCellCount,
            'notebookCodeCellCount' => $codeCellCount,
            'notebookRawCellCount' => $rawCellCount,
            'notebookAttachmentCount' => $attachmentCount,
            'notebookAttachmentMediaCount' => count($attachmentMedia),
            'notebookAttachmentMedia' => $attachmentMedia,
            'notebookAttachmentMediaDiagnostics' => $this->uniqueSortedStrings($attachmentMediaDiagnostics),
            'notebookOutputCount' => $outputCount,
            'notebookUnsupportedResourceCount' => $unsupportedResourceCount,
            'notebookOutputBytePresenceCount' => $outputBytePresenceCount,
            'notebookOutputMimeBundleCount' => $outputMimeBundleCount,
            'notebookOutputGroupCount' => $outputGroupCount,
            'notebookOutputStreamGroupCount' => $outputStreamGroupCount,
            'notebookOutputRepeatedStreamNameCount' => $outputRepeatedStreamNameCount,
            'notebookOutputMimeBundleDigestCount' => $outputMimeBundleDigestCount,
            'notebookOutputRepeatedMimeBundleDigestCount' => $outputRepeatedMimeBundleDigestCount,
            'notebookOutputRepeatedMimeBundleDigestDuplicateCount' => $outputRepeatedMimeBundleDigestDuplicateCount,
            'notebookOutputAggregateDiagnosticCount' => count($outputAggregateDiagnostics),
            'notebookOutputAggregateDiagnostics' => $outputAggregateDiagnostics,
            'notebookOutputBytePolicy' => [
                'state' => $outputBytePresenceCount > 0 ? 'metadata-only' : 'none',
                'byteExposure' => 'blocked',
                'diagnostics' => $outputBytePresenceCount > 0 ? ['ipynb-output-bytes-blocked'] : [],
            ],
            'notebookNbformat' => $notebook['nbformat'] ?? null,
            'notebookNbformatMinor' => $notebook['nbformat_minor'] ?? null,
            'notebookMetadataKeys' => $metadataKeys,
            'notebookKernelName' => $this->metadataString($metadata['kernelspec'] ?? null, 'name'),
            'notebookLanguage' => $language,
            'notebookCells' => $cellSummaries,
            'notebookResourcePolicy' => [
                'state' => $unsupportedResourceCount > 0 ? 'metadata-only' : 'none',
                'byteExposure' => 'blocked',
                'diagnostics' => $unsupportedResourceCount > 0 ? ['external-notebook-resource-bytes-blocked'] : [],
            ],
            'notebookSourceSummary' => [
                'cellCount' => count($cells),
                'totalSourceBytes' => $totalSourceBytes,
                'totalSourceLineCount' => $totalSourceLineCount,
                'sourceShapeCounts' => $sourceShapeCounts,
                'sourceLineEndingCounts' => $sourceLineEndingCounts,
                'emptySourceCount' => $sourceContentStateCounts['empty'],
                'whitespaceOnlySourceCount' => $sourceContentStateCounts['whitespace-only'],
                'contentSourceCount' => $sourceContentStateCounts['content'],
                'mixedLineEndingSourceCount' => $mixedLineEndingSourceCount,
                'trailingLineEndingSourceCount' => $trailingLineEndingSourceCount,
                'uniqueSourceFingerprintCount' => count($sourceFingerprintCounts),
                'duplicateSourceFingerprintCount' => count($duplicateSourceFingerprints),
                'duplicateSourceCellCount' => $duplicateSourceCellCount,
            ],
            'notebookSourceFingerprintCounts' => $sourceFingerprintCounts,
            'notebookDuplicateSourceFingerprints' => $duplicateSourceFingerprints,
            'notebookLanguageHintSummary' => [
                'notebookLanguage' => $language,
                'notebookLanguageSource' => $notebookLanguageHint['source'],
                'cellCount' => count($cells),
                'cellMetadataLanguageHintCount' => $cellMetadataLanguageHintCount,
                'notebookLanguageFallbackCount' => $notebookLanguageFallbackCount,
                'unknownLanguageHintCount' => $unknownLanguageHintCount,
                'mismatchedLanguageHintCount' => $mismatchedLanguageHintCount,
                'languageHintCounts' => $languageHintCounts,
                'languageHintSourceCounts' => $languageHintSourceCounts,
                'languageHintDiagnosticCounts' => $languageHintDiagnosticCounts,
            ],
            'notebookLanguageMetadataSummary' => $notebookLanguageMetadataSummary,
            'notebookLanguageOutputConsistencySummary' => [
                'cellCount' => count($cells),
                'notebookLanguage' => $language,
                'notebookLanguageSource' => $notebookLanguageHint['source'],
                'notebookLanguageDiagnostics' => $notebookLanguageMetadataSummary['diagnostics'],
                'cellMetadataLanguageHintCount' => $cellMetadataLanguageHintCount,
                'notebookLanguageFallbackCount' => $notebookLanguageFallbackCount,
                'unknownLanguageHintCount' => $unknownLanguageHintCount,
                'mismatchedLanguageHintCount' => $mismatchedLanguageHintCount,
                'outputLanguageLikeMimeCount' => $outputLanguageLikeMimeCount,
                'outputLanguageLikeMimeCellCount' => $outputLanguageLikeMimeCellCount,
                'outputLanguageMatchCount' => $outputLanguageMatchCount,
                'outputLanguageMismatchCount' => $outputLanguageMismatchCount,
                'outputLanguageUnknownCount' => $outputLanguageUnknownCount,
                'streamAssociatedOutputLanguageCount' => $streamAssociatedOutputLanguageCount,
                'streamAssociatedOutputLanguageMismatchCount' => $streamAssociatedOutputLanguageMismatchCount,
                'outputLanguageDigestAssociationCount' => $outputLanguageDigestAssociationCount,
                'outputLanguageDigestMissingCount' => $outputLanguageDigestMissingCount,
                'outputLanguagePolicyCounts' => $outputLanguagePolicyCounts,
                'outputLanguageCounts' => $outputLanguageCounts,
                'outputLanguageMimeCounts' => $outputLanguageMimeCounts,
                'outputLanguageDiagnosticCounts' => $outputLanguageDiagnosticCounts,
            ],
            'notebookMimeLanguagePolicySummary' => [
                'state' => $outputLanguageLikeMimeCount > 0 ? 'metadata-only' : 'none',
                'byteExposure' => 'blocked',
                'notebookLanguage' => $language,
                'notebookLanguageSource' => $notebookLanguageHint['source'],
                'notebookLanguageDiagnostics' => $notebookLanguageMetadataSummary['diagnostics'],
                'languageLikeMimeCount' => $outputLanguageLikeMimeCount,
                'languageLikeMimeCellCount' => $outputLanguageLikeMimeCellCount,
                'matchedLanguageCount' => $outputLanguageMatchCount,
                'mismatchedLanguageCount' => $outputLanguageMismatchCount,
                'unknownLanguageCount' => $outputLanguageUnknownCount,
                'digestAssociatedLanguageCount' => $outputLanguageDigestAssociationCount,
                'digestMissingLanguageCount' => $outputLanguageDigestMissingCount,
                'policyCounts' => $outputLanguagePolicyCounts,
                'languageCounts' => $outputLanguageCounts,
                'mimeTypeCounts' => $outputLanguageMimeCounts,
                'diagnosticCounts' => $outputLanguageDiagnosticCounts,
            ],
        ], $blocks);
    }

    /**
     * @return list<AstNode>
     */
    private function markdownCellBlocks(string $source): array
    {
        if (trim($source) === '') {
            return [];
        }

        return $this->markdownReader->read($source)->children;
    }

    /**
     * @param array{languageHint:string, languageHintSource:string, languageHintDiagnostics:list<string>} $languageHintSummary
     * @param array<string, mixed> $cell
     */
    private function codeCellBlock(string $source, array $languageHintSummary, int $index, array $cell): AstNode
    {
        $classes = ['ipynb-code-cell-source'];
        $languageHint = $languageHintSummary['languageHint'];
        if ($languageHint !== 'unknown') {
            array_unshift($classes, $this->sanitizeClassToken($languageHint));
        }

        $attrs = [
            'classes' => array_values(array_filter($classes, static fn (string $class): bool => $class !== '')),
            'attributes' => [
                'data-ipynb-cell-index' => (string) $index,
                'data-ipynb-cell-type' => 'code',
            ],
            'text' => $source,
        ];
        if (array_key_exists('execution_count', $cell) && is_int($cell['execution_count'])) {
            $attrs['attributes']['data-ipynb-execution-count'] = (string) $cell['execution_count'];
        }
        if ($languageHint !== 'unknown') {
            $attrs['attributes']['data-ipynb-language-hint'] = $languageHint;
            $attrs['attributes']['data-ipynb-language-hint-source'] = $languageHintSummary['languageHintSource'];
        }
        if ($languageHintSummary['languageHintDiagnostics'] !== []) {
            $attrs['attributes']['data-ipynb-language-diagnostics'] = implode(' ', $languageHintSummary['languageHintDiagnostics']);
        }

        return new AstNode('code_block', $attrs);
    }

    private function rawCellBlock(string $source, int $index): AstNode
    {
        return new AstNode('code_block', [
            'classes' => ['ipynb-raw-cell-source'],
            'attributes' => [
                'data-ipynb-cell-index' => (string) $index,
                'data-ipynb-cell-type' => 'raw',
            ],
            'text' => $source,
        ]);
    }

    private function unsupportedCellBlock(string $source, string $cellType, int $index): AstNode
    {
        return new AstNode('code_block', [
            'classes' => ['ipynb-unsupported-cell-source'],
            'attributes' => [
                'data-ipynb-cell-index' => (string) $index,
                'data-ipynb-cell-type' => $cellType,
            ],
            'text' => $source,
        ]);
    }

    private function cellType(mixed $cellType): string
    {
        if (!is_string($cellType) || $cellType === '') {
            return 'unknown';
        }

        $normalized = $this->sanitizeClassToken(strtolower($cellType));

        return $normalized === '' ? 'unknown' : $normalized;
    }

    /**
     * @return array{source:string, summary:array<string, mixed>}
     */
    private function sourceReview(mixed $source, bool $sourcePresent, string $label): array
    {
        $sourceShape = 'missing';
        $sourcePartCount = 0;
        $normalized = '';

        if (!$sourcePresent) {
            $sourceShape = 'missing';
        } elseif ($source === null) {
            $sourceShape = 'null';
        } elseif (is_string($source)) {
            $sourceShape = 'string';
            $sourcePartCount = 1;
            $normalized = $source;
        } elseif (is_array($source)) {
            $sourceShape = 'list';
            $sourcePartCount = count($source);
            $parts = [];
            foreach ($source as $index => $line) {
                if (!is_string($line)) {
                    throw new \InvalidArgumentException("{$label} entry {$index} is not a string");
                }
                $parts[] = $line;
            }
            $normalized = implode('', $parts);
        } else {
            throw new \InvalidArgumentException("{$label} must be a string, string array, null, or missing");
        }

        $sourceLineEndings = $this->sourceLineEndingCounts($normalized);
        $sourceLineEndingCount = array_sum($sourceLineEndings);
        $sourceHasTrailingLineEnding = $this->sourceHasTrailingLineEnding($normalized);
        $sourceDigest = hash('sha256', $normalized);

        return [
            'source' => $normalized,
            'summary' => [
                'sourceShape' => $sourceShape,
                'sourcePartCount' => $sourcePartCount,
                'sourceBytes' => strlen($normalized),
                'sourceLineCount' => $this->sourceLineCount($normalized, $sourceLineEndingCount, $sourceHasTrailingLineEnding),
                'sourceLineEndingCount' => $sourceLineEndingCount,
                'sourceLineEndings' => $sourceLineEndings,
                'sourceHasTrailingLineEnding' => $sourceHasTrailingLineEnding,
                'sourceHasMixedLineEndings' => count(array_filter($sourceLineEndings, static fn (int $count): bool => $count > 0)) > 1,
                'sourceContentState' => $this->sourceContentState($normalized),
                'sourceDigest' => [
                    'algorithm' => 'sha256',
                    'value' => $sourceDigest,
                ],
                'sourceFingerprint' => 'sha256:' . $sourceDigest,
            ],
        ];
    }

    /**
     * @return array{lf:int, crlf:int, cr:int}
     */
    private function sourceLineEndingCounts(string $source): array
    {
        $counts = [
            'lf' => 0,
            'crlf' => 0,
            'cr' => 0,
        ];
        if ($source === '' || preg_match_all('/\r\n|\r|\n/', $source, $matches) === false) {
            return $counts;
        }

        foreach ($matches[0] as $lineEnding) {
            if ($lineEnding === "\r\n") {
                $counts['crlf']++;
            } elseif ($lineEnding === "\r") {
                $counts['cr']++;
            } else {
                $counts['lf']++;
            }
        }

        return $counts;
    }

    private function sourceHasTrailingLineEnding(string $source): bool
    {
        return $source !== '' && preg_match('/(?:\r\n|\r|\n)\z/', $source) === 1;
    }

    private function sourceLineCount(string $source, int $lineEndingCount, bool $sourceHasTrailingLineEnding): int
    {
        if ($source === '') {
            return 0;
        }

        return $lineEndingCount + ($sourceHasTrailingLineEnding ? 0 : 1);
    }

    private function sourceContentState(string $source): string
    {
        if ($source === '') {
            return 'empty';
        }

        return trim($source) === '' ? 'whitespace-only' : 'content';
    }

    /**
     * @param array<string, mixed> $attachments
     * @return array{
     *     count:int,
     *     names:list<string>,
     *     mimeTypes:list<string>,
     *     media:list<array{
     *         cellIndex:int,
     *         name:string,
     *         mimeTypes:list<string>,
     *         primaryMimeType:string,
     *         mediaPath:string,
     *         byteExposure:string,
     *         extractionState:string,
     *         diagnostics:list<string>
     *     }>,
     *     diagnostics:list<string>
     * }
     */
    private function attachmentSummary(array $attachments, int $cellIndex): array
    {
        $names = [];
        $mimeTypes = [];
        $media = [];
        $diagnostics = [];
        $usedMediaPaths = [];
        $attachmentNames = [];
        foreach ($attachments as $name => $_payload) {
            $attachmentNames[] = (string) $name;
        }
        sort($attachmentNames);

        foreach ($attachmentNames as $name) {
            $payload = $attachments[$name] ?? null;
            if (!is_array($payload)) {
                continue;
            }
            $names[] = (string) $name;
            $payloadMimeTypes = $this->attachmentMimeTypes($payload);
            $mimeTypes = array_merge($mimeTypes, $payloadMimeTypes);
            $mediaPathPlan = $this->attachmentMediaPath($name, $payloadMimeTypes);
            $mediaPath = 'ipynb-media/' . $mediaPathPlan['safeName'];
            $itemDiagnostics = array_merge(['attachment-bytes-blocked'], $mediaPathPlan['diagnostics']);
            if (isset($usedMediaPaths[$mediaPath])) {
                $mediaPath = $this->disambiguateAttachmentMediaPath($mediaPath, $cellIndex, $name, $payloadMimeTypes);
                $itemDiagnostics[] = 'attachment-media-path-collision';
            }
            $usedMediaPaths[$mediaPath] = true;
            $itemDiagnostics = $this->uniqueSortedStrings($itemDiagnostics);
            $diagnostics = array_merge($diagnostics, $itemDiagnostics);

            $media[] = [
                'cellIndex' => $cellIndex,
                'name' => $name,
                'mimeTypes' => $payloadMimeTypes,
                'primaryMimeType' => $payloadMimeTypes[0] ?? $this->mimeTypeFromPath($name),
                'mediaPath' => $mediaPath,
                'byteExposure' => 'blocked',
                'extractionState' => 'planned-metadata-only',
                'diagnostics' => $itemDiagnostics,
            ];
        }
        sort($names);
        sort($mimeTypes);

        return [
            'count' => count($names),
            'names' => $names,
            'mimeTypes' => array_values(array_unique($mimeTypes)),
            'media' => $media,
            'diagnostics' => $this->uniqueSortedStrings($diagnostics),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<string>
     */
    private function attachmentMimeTypes(array $payload): array
    {
        $mimeTypes = [];
        foreach ($payload as $mimeType => $data) {
            if (!is_string($mimeType) || $mimeType === '') {
                continue;
            }
            if (!is_scalar($data) && !$this->isStringList($data)) {
                continue;
            }
            $mimeTypes[] = $mimeType;
        }
        sort($mimeTypes);

        return array_values(array_unique($mimeTypes));
    }

    /**
     * @param array<int, mixed> $outputs
     * @return array<string, mixed>
     */
    private function outputSummary(array $outputs): array
    {
        $types = [];
        $orderTypes = [];
        $indexes = [];
        $outputRows = [];
        $mimeTypes = [];
        $mimeOccurrences = [];
        $mimeBundleDigests = [];
        $mimeBundleDigestRecords = [];
        $mimeBundleCount = 0;
        $bytePresenceCount = 0;
        $groups = [];
        $streamNames = [];
        $streamNameOutputIndexes = [];
        $policyDiagnostics = [];
        $aggregateDiagnostics = [];

        foreach ($outputs as $output) {
            if (!is_array($output)) {
                continue;
            }
            $outputIndex = count($outputRows);
            $type = $output['output_type'] ?? null;
            $type = is_string($type) && $type !== '' ? $type : 'unknown';
            $types[] = $type;
            $orderTypes[] = $type;
            $indexes[] = $outputIndex;

            $dataMimeTypes = [];
            $data = $output['data'] ?? null;
            if (is_array($data)) {
                $dataMimeTypes = $this->mimeTypesFromBundle($data);
            }
            foreach ($dataMimeTypes as $mimeType) {
                $mimeTypes[] = $mimeType;
                $mimeOccurrences[$mimeType] ??= [];
                $mimeOccurrences[$mimeType][] = $outputIndex;
            }

            $streamName = $type === 'stream' ? $this->nonEmptyString($output['name'] ?? null) : null;
            if ($streamName !== null) {
                $streamNames[] = $streamName;
                $streamNameOutputIndexes[$streamName] ??= [];
                $streamNameOutputIndexes[$streamName][] = $outputIndex;
            }
            $groupIndex = $this->appendOutputGroup($groups, $type, $streamName, $outputIndex);

            $errorName = $this->nonEmptyString($output['ename'] ?? null);
            $hasErrorValue = $this->nonEmptyString($output['evalue'] ?? null) !== null;
            $tracebackLineCount = $this->stringListCount($output['traceback'] ?? null);
            $hasMimeBundle = $dataMimeTypes !== [];
            $hasStreamPayload = $type === 'stream' && $this->hasStringOrStringList($output['text'] ?? null);
            $hasErrorPayload = $type === 'error' && ($errorName !== null || $hasErrorValue || $tracebackLineCount > 0);
            $hasBytePresence = $hasMimeBundle || $hasStreamPayload || $hasErrorPayload;

            $outputPolicyDiagnostics = [];
            if ($hasBytePresence) {
                $bytePresenceCount++;
                $outputPolicyDiagnostics[] = 'output-bytes-blocked';
            }
            if ($hasMimeBundle) {
                $mimeBundleCount++;
                $outputPolicyDiagnostics[] = 'output-mime-bundle-metadata-only';
            }
            if ($hasStreamPayload) {
                $outputPolicyDiagnostics[] = 'output-stream-bytes-blocked';
            }
            if ($type === 'error') {
                $outputPolicyDiagnostics[] = 'output-error-metadata-only';
                if ($tracebackLineCount > 0) {
                    $outputPolicyDiagnostics[] = 'output-error-traceback-bytes-blocked';
                }
            }
            foreach ($outputPolicyDiagnostics as $diagnostic) {
                $policyDiagnostics[] = $diagnostic;
            }

            $row = [
                'index' => $outputIndex,
                'type' => $type,
                'groupIndex' => $groupIndex,
                'mimeTypes' => $dataMimeTypes,
                'metadataKeys' => isset($output['metadata']) && is_array($output['metadata'])
                    ? $this->metadataKeys($output['metadata'])
                    : [],
                'bytePresence' => $hasBytePresence ? 'present' : 'none',
                'byteExposure' => $hasBytePresence ? 'blocked' : 'none',
                'diagnostics' => array_values(array_unique($outputPolicyDiagnostics)),
            ];
            if ($streamName !== null) {
                $row['streamName'] = $streamName;
                $row['streamTextLineCount'] = $this->stringListCount($output['text'] ?? null);
            }
            if ($hasMimeBundle && is_array($data)) {
                $mimeBundlePayloadShapes = $this->mimeBundlePayloadShapes($data, $dataMimeTypes);
                $mimeBundleDigest = $this->mimeBundleDigest($dataMimeTypes, $mimeBundlePayloadShapes);
                $mimeBundleDigests[] = $mimeBundleDigest;
                $mimeBundleDigestRecord = [
                    'digest' => $mimeBundleDigest,
                    'outputIndex' => $outputIndex,
                    'groupIndex' => $groupIndex,
                    'outputType' => $type,
                    'mimeTypes' => $dataMimeTypes,
                    'payloadShapes' => $mimeBundlePayloadShapes,
                    'fingerprintSource' => 'metadata-only',
                ];
                $mimeBundleDigestRecords[] = $mimeBundleDigestRecord;
                $row['mimeBundleDigest'] = $mimeBundleDigest;
                $row['mimeBundleFingerprintSource'] = 'metadata-only';
                $row['mimeBundlePayloadShapes'] = $mimeBundlePayloadShapes;
            }
            if ($type === 'display_data') {
                $row['richOutputKind'] = 'display_data';
            }
            if ($type === 'execute_result') {
                $row['richOutputKind'] = 'execute_result';
                if (array_key_exists('execution_count', $output) && (is_int($output['execution_count']) || $output['execution_count'] === null)) {
                    $row['executionCount'] = $output['execution_count'];
                }
            }
            if ($type === 'error') {
                $row['richOutputKind'] = 'error';
                if ($errorName !== null) {
                    $row['errorName'] = $errorName;
                }
                $row['errorValuePresent'] = $hasErrorValue;
                $row['tracebackLineCount'] = $tracebackLineCount;
            }

            $outputRows[] = $row;
        }

        $streamGroups = [];
        foreach ($groups as $group) {
            if (($group['kind'] ?? '') !== 'stream') {
                continue;
            }
            $streamGroups[] = [
                'streamGroupIndex' => count($streamGroups),
                'groupIndex' => $group['groupIndex'],
                'streamName' => $group['streamName'],
                'startIndex' => $group['startIndex'],
                'endIndex' => $group['endIndex'],
                'outputIndexes' => $group['outputIndexes'],
                'count' => $group['count'],
            ];
        }
        $outputGroupIndexes = [];
        foreach ($groups as $group) {
            $groupIndex = $group['groupIndex'] ?? null;
            $outputIndexes = $group['outputIndexes'] ?? [];
            if (!is_int($groupIndex) || !is_array($outputIndexes)) {
                continue;
            }
            foreach ($outputIndexes as $groupedOutputIndex) {
                if (is_int($groupedOutputIndex)) {
                    $outputGroupIndexes[$groupedOutputIndex] = $groupIndex;
                }
            }
        }
        foreach ($outputRows as &$outputRow) {
            $outputIndex = $outputRow['index'];
            $outputRow['groupIndex'] = is_int($outputIndex) ? ($outputGroupIndexes[$outputIndex] ?? null) : null;
            $streamAssociation = is_int($outputIndex) ? $this->associatedStreamGroup($streamGroups, $outputIndex) : null;
            if ($streamAssociation !== null) {
                $outputRow['associatedStreamGroupIndex'] = $streamAssociation['streamGroupIndex'];
                $outputRow['associatedStreamGroupName'] = $streamAssociation['streamName'];
                $outputRow['associatedStreamGroupOutputIndexes'] = $streamAssociation['outputIndexes'];
            }
        }
        unset($outputRow);

        $uniqueOrderTypes = array_values(array_unique($orderTypes));
        if (count($orderTypes) > 1 && count($uniqueOrderTypes) > 1) {
            $aggregateDiagnostics[] = [
                'issue' => 'mixed-output-display-order',
                'severity' => 'review',
                'outputIndexes' => $indexes,
                'outputTypes' => $orderTypes,
                'uniqueOutputTypes' => $uniqueOrderTypes,
                'outputGroupCount' => count($groups),
            ];
        }

        $repeatedMimeBundleRecords = $this->repeatedMimeBundleRecords($mimeOccurrences);
        foreach ($repeatedMimeBundleRecords as $record) {
            $aggregateDiagnostics[] = [
                'issue' => 'repeated-output-mime-bundle-key',
                'severity' => 'review',
                'mimeType' => $record['mimeType'],
                'outputIndexes' => $record['outputIndexes'],
                'occurrenceCount' => $record['count'],
            ];
        }

        $repeatedMimeBundleDigestRecords = $this->repeatedMimeBundleDigestRecords($mimeBundleDigestRecords);
        foreach ($repeatedMimeBundleDigestRecords as $record) {
            $aggregateDiagnostics[] = [
                'issue' => 'repeated-output-mime-bundle-digest',
                'severity' => 'review',
                'digest' => $record['digest'],
                'outputIndexes' => $record['outputIndexes'],
                'groupIndexes' => $record['groupIndexes'],
                'occurrenceCount' => $record['count'],
                'duplicateCount' => $record['duplicateCount'],
                'outputTypes' => $record['outputTypes'],
            ];
        }

        $repeatedStreamNameRecords = $this->repeatedStreamNameRecords($streamGroups, $streamNameOutputIndexes);
        foreach ($repeatedStreamNameRecords as $record) {
            $aggregateDiagnostics[] = [
                'issue' => 'repeated-output-stream-name',
                'severity' => 'review',
                'streamName' => $record['streamName'],
                'outputIndexes' => $record['outputIndexes'],
                'groupIndexes' => $record['groupIndexes'],
                'occurrenceCount' => $record['count'],
                'groupCount' => $record['groupCount'],
            ];
        }
        sort($mimeTypes);

        return [
            'count' => count($outputs),
            'types' => array_values(array_unique($types)),
            'orderTypes' => $orderTypes,
            'indexes' => $indexes,
            'outputs' => $outputRows,
            'mimeTypes' => array_values(array_unique($mimeTypes)),
            'mimeBundleCount' => $mimeBundleCount,
            'mimeBundleDigests' => $mimeBundleDigests,
            'mimeBundleDigestRecords' => $mimeBundleDigestRecords,
            'repeatedMimeBundleDigests' => array_column($repeatedMimeBundleDigestRecords, 'digest'),
            'repeatedMimeBundleDigestRecords' => $repeatedMimeBundleDigestRecords,
            'repeatedMimeBundleDigestDuplicateCount' => array_sum(array_column($repeatedMimeBundleDigestRecords, 'duplicateCount')),
            'bytePresenceCount' => $bytePresenceCount,
            'groups' => $groups,
            'groupCount' => count($groups),
            'streamGroups' => $streamGroups,
            'streamGroupCount' => count($streamGroups),
            'streamNames' => $this->sortedUniqueStrings($streamNames),
            'repeatedStreamNames' => array_column($repeatedStreamNameRecords, 'streamName'),
            'repeatedStreamNameRecords' => $repeatedStreamNameRecords,
            'repeatedMimeBundleKeys' => array_column($repeatedMimeBundleRecords, 'mimeType'),
            'repeatedMimeBundleRecords' => $repeatedMimeBundleRecords,
            'aggregateDiagnostics' => $aggregateDiagnostics,
            'policyDiagnostics' => array_values(array_unique($policyDiagnostics)),
        ];
    }

    /**
     * @param array{count:int, names:list<string>, mimeTypes:list<string>, media:list<array<string, mixed>>, diagnostics:list<string>} $attachmentSummary
     * @param array<string, mixed> $outputSummary
     * @return list<string>
     */
    private function cellDiagnostics(array $attachmentSummary, array $outputSummary): array
    {
        $diagnostics = [];
        if ($attachmentSummary['count'] > 0) {
            $diagnostics[] = 'attachment-bytes-blocked';
        }
        if ($outputSummary['count'] > 0) {
            $diagnostics[] = 'output-bytes-blocked';
        }
        if ($outputSummary['mimeTypes'] !== []) {
            $diagnostics[] = 'output-mime-bundle-metadata-only';
        }

        return $diagnostics;
    }

    /**
     * @param list<array<string, mixed>> $groups
     */
    private function appendOutputGroup(array &$groups, string $type, ?string $streamName, int $outputIndex): int
    {
        $kind = $type === 'stream' ? 'stream' : 'output';
        $groupName = $kind === 'stream' ? ($streamName ?? 'unnamed') : $type;
        $lastIndex = array_key_last($groups);
        if (
            $lastIndex !== null
            && ($groups[$lastIndex]['kind'] ?? '') === $kind
            && ($groups[$lastIndex]['name'] ?? '') === $groupName
        ) {
            $groups[$lastIndex]['endIndex'] = $outputIndex;
            $groups[$lastIndex]['outputIndexes'][] = $outputIndex;
            $groups[$lastIndex]['count']++;

            return $groups[$lastIndex]['groupIndex'];
        }

        $group = [
            'groupIndex' => count($groups),
            'kind' => $kind,
            'type' => $type,
            'name' => $groupName,
            'startIndex' => $outputIndex,
            'endIndex' => $outputIndex,
            'outputIndexes' => [$outputIndex],
            'count' => 1,
        ];
        if ($kind === 'stream') {
            $group['streamName'] = $groupName;
        }
        $groups[] = $group;

        return $group['groupIndex'];
    }

    /**
     * @param array<string, mixed> $bundle
     * @return list<string>
     */
    private function mimeTypesFromBundle(array $bundle): array
    {
        $mimeTypes = [];
        foreach ($bundle as $mimeType => $data) {
            if (!is_string($mimeType) || $mimeType === '') {
                continue;
            }
            if (!is_scalar($data) && !is_array($data) && $data !== null) {
                continue;
            }
            $mimeTypes[] = $mimeType;
        }
        sort($mimeTypes);

        return array_values(array_unique($mimeTypes));
    }

    /**
     * @param array<string, mixed> $bundle
     * @param list<string> $mimeTypes
     * @return array<string, array<string, mixed>>
     */
    private function mimeBundlePayloadShapes(array $bundle, array $mimeTypes): array
    {
        $shapes = [];
        foreach ($mimeTypes as $mimeType) {
            $shapes[$mimeType] = $this->payloadShape($bundle[$mimeType] ?? null);
        }
        ksort($shapes);

        return $shapes;
    }

    /**
     * @param list<string> $mimeTypes
     * @param array<string, array<string, mixed>> $payloadShapes
     */
    private function mimeBundleDigest(array $mimeTypes, array $payloadShapes): string
    {
        $fingerprint = [
            'mimeTypes' => $mimeTypes,
            'payloadShapes' => $payloadShapes,
        ];
        $encoded = json_encode($fingerprint, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return 'sha256:' . hash('sha256', $encoded);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadShape(mixed $value): array
    {
        if (is_string($value)) {
            return [
                'kind' => 'string',
                'lineCount' => $this->stringListCount($value),
            ];
        }
        if (is_int($value) || is_float($value)) {
            return ['kind' => 'number'];
        }
        if (is_bool($value)) {
            return ['kind' => 'boolean'];
        }
        if ($value === null) {
            return ['kind' => 'null'];
        }
        if (!is_array($value)) {
            return ['kind' => 'unsupported'];
        }
        if ($this->isListArray($value)) {
            return [
                'kind' => 'list',
                'count' => count($value),
                'entryKinds' => $this->payloadShapeKinds($value),
                'stringLineCount' => $this->stringListCount($value),
            ];
        }

        $fieldKinds = [];
        foreach ($value as $key => $entry) {
            if (is_string($key) && $key !== '') {
                $fieldKinds[$key] = $this->payloadShapeKind($entry);
            }
        }
        ksort($fieldKinds);

        return [
            'kind' => 'object',
            'keys' => $this->metadataKeys($value),
            'fieldKinds' => $fieldKinds,
        ];
    }

    /**
     * @param list<mixed> $values
     * @return list<string>
     */
    private function payloadShapeKinds(array $values): array
    {
        $kinds = [];
        foreach ($values as $value) {
            $kinds[] = $this->payloadShapeKind($value);
        }
        sort($kinds);

        return array_values(array_unique($kinds));
    }

    private function payloadShapeKind(mixed $value): string
    {
        if (is_string($value)) {
            return 'string';
        }
        if (is_int($value) || is_float($value)) {
            return 'number';
        }
        if (is_bool($value)) {
            return 'boolean';
        }
        if ($value === null) {
            return 'null';
        }
        if (is_array($value)) {
            return $this->isListArray($value) ? 'list' : 'object';
        }

        return 'unsupported';
    }

    /**
     * @param array<mixed> $value
     */
    private function isListArray(array $value): bool
    {
        $expected = 0;
        foreach (array_keys($value) as $key) {
            if ($key !== $expected) {
                return false;
            }
            $expected++;
        }

        return true;
    }

    private function nonEmptyString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function hasStringOrStringList(mixed $value): bool
    {
        if (is_string($value)) {
            return $value !== '';
        }
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $entry) {
            if (is_string($entry) && $entry !== '') {
                return true;
            }
        }

        return false;
    }

    private function stringListCount(mixed $value): int
    {
        if (is_string($value)) {
            return $value === '' ? 0 : 1;
        }
        if (!is_array($value)) {
            return 0;
        }
        $count = 0;
        foreach ($value as $entry) {
            if (is_string($entry)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param list<string> $strings
     * @return list<string>
     */
    private function sortedUniqueStrings(array $strings): array
    {
        sort($strings);

        return array_values(array_unique($strings));
    }

    /**
     * @param array<string, list<int>> $mimeOccurrences
     * @return list<array{mimeType:string, outputIndexes:list<int>, count:int}>
     */
    private function repeatedMimeBundleRecords(array $mimeOccurrences): array
    {
        ksort($mimeOccurrences);
        $records = [];
        foreach ($mimeOccurrences as $mimeType => $outputIndexes) {
            if (count($outputIndexes) < 2) {
                continue;
            }
            $records[] = [
                'mimeType' => $mimeType,
                'outputIndexes' => $outputIndexes,
                'count' => count($outputIndexes),
            ];
        }

        return $records;
    }

    /**
     * @param list<array<string, mixed>> $digestRecords
     * @return list<array{digest:string, outputIndexes:list<int>, groupIndexes:list<int>, outputTypes:list<string>, count:int, duplicateCount:int}>
     */
    private function repeatedMimeBundleDigestRecords(array $digestRecords): array
    {
        $recordsByDigest = [];
        foreach ($digestRecords as $digestRecord) {
            $digest = $digestRecord['digest'] ?? null;
            $outputIndex = $digestRecord['outputIndex'] ?? null;
            $groupIndex = $digestRecord['groupIndex'] ?? null;
            $outputType = $digestRecord['outputType'] ?? null;
            if (!is_string($digest) || !is_int($outputIndex) || !is_int($groupIndex) || !is_string($outputType)) {
                continue;
            }
            $recordsByDigest[$digest] ??= [
                'digest' => $digest,
                'outputIndexes' => [],
                'groupIndexes' => [],
                'outputTypes' => [],
                'count' => 0,
                'duplicateCount' => 0,
            ];
            $recordsByDigest[$digest]['outputIndexes'][] = $outputIndex;
            $recordsByDigest[$digest]['groupIndexes'][] = $groupIndex;
            $recordsByDigest[$digest]['outputTypes'][] = $outputType;
            $recordsByDigest[$digest]['count']++;
        }
        ksort($recordsByDigest);

        $records = [];
        foreach ($recordsByDigest as $record) {
            if ($record['count'] < 2) {
                continue;
            }
            $record['groupIndexes'] = array_values(array_unique($record['groupIndexes']));
            $record['outputTypes'] = array_values(array_unique($record['outputTypes']));
            $record['duplicateCount'] = $record['count'] - 1;
            $records[] = $record;
        }

        return $records;
    }

    /**
     * @param list<array<string, mixed>> $streamGroups
     * @param array<string, list<int>> $streamNameOutputIndexes
     * @return list<array{streamName:string, outputIndexes:list<int>, groupIndexes:list<int>, count:int, groupCount:int}>
     */
    private function repeatedStreamNameRecords(array $streamGroups, array $streamNameOutputIndexes): array
    {
        $groupIndexesByName = [];
        foreach ($streamGroups as $streamGroup) {
            $streamName = $streamGroup['streamName'] ?? null;
            $groupIndex = $streamGroup['groupIndex'] ?? null;
            if (!is_string($streamName) || !is_int($groupIndex)) {
                continue;
            }
            $groupIndexesByName[$streamName] ??= [];
            $groupIndexesByName[$streamName][] = $groupIndex;
        }
        ksort($groupIndexesByName);

        $records = [];
        foreach ($groupIndexesByName as $streamName => $groupIndexes) {
            if (count($groupIndexes) < 2) {
                continue;
            }
            $outputIndexes = $streamNameOutputIndexes[$streamName] ?? [];
            $records[] = [
                'streamName' => $streamName,
                'outputIndexes' => $outputIndexes,
                'groupIndexes' => $groupIndexes,
                'count' => count($outputIndexes),
                'groupCount' => count($groupIndexes),
            ];
        }

        return $records;
    }

    /**
     * @param list<array<string, mixed>> $streamGroups
     * @return array{streamGroupIndex:int, streamName:string, outputIndexes:list<int>}|null
     */
    private function associatedStreamGroup(array $streamGroups, int $outputIndex): ?array
    {
        $associated = null;
        foreach ($streamGroups as $streamGroup) {
            $endIndex = $streamGroup['endIndex'] ?? null;
            if (!is_int($endIndex) || $endIndex >= $outputIndex) {
                continue;
            }
            $associated = $streamGroup;
        }
        if ($associated === null) {
            return null;
        }

        $streamGroupIndex = $associated['streamGroupIndex'] ?? null;
        $streamName = $associated['streamName'] ?? null;
        $outputIndexes = $associated['outputIndexes'] ?? null;
        if (!is_int($streamGroupIndex) || !is_string($streamName) || !is_array($outputIndexes)) {
            return null;
        }

        return [
            'streamGroupIndex' => $streamGroupIndex,
            'streamName' => $streamName,
            'outputIndexes' => array_values(array_filter($outputIndexes, 'is_int')),
        ];
    }

    /**
     * @param array<string, mixed> $outputSummary
     * @param array{languageHint:string, languageHintSource:string, languageHintDiagnostics:list<string>} $languageHintSummary
     * @return array{languageLikeMimeCount:int, mimeTypes:list<string>, languages:list<string>, records:list<array<string, mixed>>, matchedLanguageCount:int, mismatchedLanguageCount:int, unknownLanguageCount:int, streamAssociatedLanguageCount:int, streamAssociatedMismatchCount:int, digestAssociatedLanguageCount:int, digestMissingLanguageCount:int, diagnostics:list<string>, diagnosticCounts:array<string, int>, policyCounts:array<string, int>, languageCounts:array<string, int>, mimeTypeCounts:array<string, int>, policySummary:array<string, mixed>}
     */
    private function outputLanguageConsistencySummary(array $outputSummary, array $languageHintSummary): array
    {
        $cellLanguage = $languageHintSummary['languageHint'];
        $records = [];
        $languages = [];
        $mimeTypes = [];
        $diagnostics = [];
        $diagnosticCounts = [];
        $languageCounts = [];
        $mimeTypeCounts = [];
        $policyCounts = [
            'digest-associated' => 0,
            'digest-missing' => 0,
            'match' => 0,
            'mismatch' => 0,
            'unknown' => 0,
        ];
        $matchedLanguageCount = 0;
        $mismatchedLanguageCount = 0;
        $unknownLanguageCount = 0;
        $streamAssociatedLanguageCount = 0;
        $streamAssociatedMismatchCount = 0;
        $digestAssociatedLanguageCount = 0;
        $digestMissingLanguageCount = 0;
        $outputs = $outputSummary['outputs'] ?? [];
        if (!is_array($outputs)) {
            $outputs = [];
        }

        foreach ($outputs as $outputRow) {
            if (!is_array($outputRow)) {
                continue;
            }
            $outputIndex = $outputRow['index'] ?? null;
            $outputType = $outputRow['type'] ?? null;
            $outputMimeTypes = $outputRow['mimeTypes'] ?? [];
            if (!is_array($outputMimeTypes)) {
                continue;
            }
            foreach ($outputMimeTypes as $mimeType) {
                if (!is_string($mimeType)) {
                    continue;
                }
                $outputLanguage = $this->languageHintFromMimeType($mimeType);
                if ($outputLanguage === null) {
                    continue;
                }

                $recordDiagnostics = [];
                $matchesCellLanguage = null;
                $policy = 'unknown';
                if ($cellLanguage === 'unknown') {
                    $unknownLanguageCount++;
                    $policyCounts['unknown']++;
                    $policy = 'unknown-cell-language';
                    $recordDiagnostics[] = 'output-language-unknown-cell-language';
                } else {
                    $matchesCellLanguage = $outputLanguage === $cellLanguage;
                    if ($matchesCellLanguage) {
                        $matchedLanguageCount++;
                        $policyCounts['match']++;
                        $policy = 'matches-cell-language';
                    } else {
                        $mismatchedLanguageCount++;
                        $policyCounts['mismatch']++;
                        $policy = 'mismatches-cell-language';
                        $recordDiagnostics[] = 'output-language-mismatch-cell-language';
                    }
                }
                $mimeBundleDigest = $outputRow['mimeBundleDigest'] ?? null;
                $hasMimeBundleDigest = is_string($mimeBundleDigest) && $mimeBundleDigest !== '';
                if ($hasMimeBundleDigest) {
                    $digestAssociatedLanguageCount++;
                    $policyCounts['digest-associated']++;
                } else {
                    $digestMissingLanguageCount++;
                    $policyCounts['digest-missing']++;
                    $recordDiagnostics[] = 'output-language-mime-digest-missing';
                }

                $record = [
                    'outputIndex' => $outputIndex,
                    'outputType' => $outputType,
                    'mimeType' => $mimeType,
                    'language' => $outputLanguage,
                    'cellLanguageHint' => $cellLanguage,
                    'cellLanguageHintSource' => $languageHintSummary['languageHintSource'],
                    'matchesCellLanguage' => $matchesCellLanguage,
                    'policy' => $policy,
                    'reviewPolicy' => 'metadata-only',
                    'byteExposure' => 'blocked',
                    'outputGroupIndex' => $outputRow['groupIndex'] ?? null,
                    'diagnostics' => $recordDiagnostics,
                ];
                if ($hasMimeBundleDigest) {
                    $record['mimeBundleDigest'] = $mimeBundleDigest;
                    $record['mimeBundleFingerprintSource'] = $outputRow['mimeBundleFingerprintSource'] ?? 'metadata-only';
                }
                if (isset($outputRow['associatedStreamGroupIndex'], $outputRow['associatedStreamGroupName'])) {
                    $record['associatedStreamGroupIndex'] = $outputRow['associatedStreamGroupIndex'];
                    $record['associatedStreamGroupName'] = $outputRow['associatedStreamGroupName'];
                    $record['associatedStreamGroupOutputIndexes'] = $outputRow['associatedStreamGroupOutputIndexes'] ?? [];
                    $streamAssociatedLanguageCount++;
                    if ($matchesCellLanguage === false) {
                        $streamAssociatedMismatchCount++;
                    }
                }

                $records[] = $record;
                $languages[] = $outputLanguage;
                $mimeTypes[] = $mimeType;
                $languageCounts[$outputLanguage] = ($languageCounts[$outputLanguage] ?? 0) + 1;
                $mimeTypeCounts[$mimeType] = ($mimeTypeCounts[$mimeType] ?? 0) + 1;
                foreach ($recordDiagnostics as $diagnostic) {
                    $diagnostics[] = $diagnostic;
                    $diagnosticCounts[$diagnostic] = ($diagnosticCounts[$diagnostic] ?? 0) + 1;
                }
            }
        }
        sort($languages);
        sort($mimeTypes);
        sort($diagnostics);
        ksort($languageCounts);
        ksort($mimeTypeCounts);
        ksort($diagnosticCounts);
        ksort($policyCounts);
        $policySummary = [
            'state' => count($records) > 0 ? 'metadata-only' : 'none',
            'byteExposure' => 'blocked',
            'cellLanguageHint' => $cellLanguage,
            'cellLanguageHintSource' => $languageHintSummary['languageHintSource'],
            'languageLikeMimeCount' => count($records),
            'matchedLanguageCount' => $matchedLanguageCount,
            'mismatchedLanguageCount' => $mismatchedLanguageCount,
            'unknownLanguageCount' => $unknownLanguageCount,
            'digestAssociatedLanguageCount' => $digestAssociatedLanguageCount,
            'digestMissingLanguageCount' => $digestMissingLanguageCount,
            'policyCounts' => $policyCounts,
            'diagnostics' => array_values(array_unique($diagnostics)),
            'diagnosticCounts' => $diagnosticCounts,
        ];

        return [
            'languageLikeMimeCount' => count($records),
            'mimeTypes' => array_values(array_unique($mimeTypes)),
            'languages' => array_values(array_unique($languages)),
            'records' => $records,
            'matchedLanguageCount' => $matchedLanguageCount,
            'mismatchedLanguageCount' => $mismatchedLanguageCount,
            'unknownLanguageCount' => $unknownLanguageCount,
            'streamAssociatedLanguageCount' => $streamAssociatedLanguageCount,
            'streamAssociatedMismatchCount' => $streamAssociatedMismatchCount,
            'digestAssociatedLanguageCount' => $digestAssociatedLanguageCount,
            'digestMissingLanguageCount' => $digestMissingLanguageCount,
            'diagnostics' => array_values(array_unique($diagnostics)),
            'diagnosticCounts' => $diagnosticCounts,
            'policyCounts' => $policyCounts,
            'languageCounts' => $languageCounts,
            'mimeTypeCounts' => $mimeTypeCounts,
            'policySummary' => $policySummary,
        ];
    }

    private function languageHintFromMimeType(string $mimeType): ?string
    {
        $normalized = strtolower(trim(explode(';', $mimeType, 2)[0]));
        if ($normalized === '' || in_array($normalized, ['text/plain', 'application/json'], true)) {
            return null;
        }

        $known = [
            'application/ecmascript' => 'javascript',
            'application/javascript' => 'javascript',
            'application/x-javascript' => 'javascript',
            'application/xhtml+xml' => 'html',
            'application/x-latex' => 'latex',
            'application/x-sh' => 'bash',
            'text/css' => 'css',
            'text/ecmascript' => 'javascript',
            'text/html' => 'html',
            'text/javascript' => 'javascript',
            'text/latex' => 'latex',
            'text/markdown' => 'markdown',
            'text/x-julia' => 'julia',
            'text/x-markdown' => 'markdown',
            'text/x-python' => 'python',
            'text/x-python3' => 'python',
            'text/x-r' => 'r',
            'text/x-r-source' => 'r',
            'text/x-scala' => 'scala',
            'text/x-shellscript' => 'bash',
            'text/x-sh' => 'bash',
            'text/xml' => 'xml',
        ];
        if (isset($known[$normalized])) {
            return $known[$normalized];
        }

        if (preg_match('#^(?:text|application)/x-([a-z0-9.+_-]+)$#', $normalized, $matches) === 1) {
            $language = $this->normalizeLanguageHint($matches[1]);

            return $language === '' ? null : $language;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array{language:string, source:string} $notebookLanguageHint
     * @return array{languageInfoName:?string, languageInfoLanguage:?string, kernelspecLanguage:?string, resolvedLanguage:string, resolvedLanguageSource:string, diagnostics:list<string>}
     */
    private function notebookLanguageMetadataSummary(array $metadata, array $notebookLanguageHint): array
    {
        $languageInfoName = $this->metadataString($metadata['language_info'] ?? null, 'name');
        $languageInfoLanguage = $languageInfoName === null ? null : $this->normalizeLanguageHint($languageInfoName);
        $kernelspecLanguage = $this->metadataString($metadata['kernelspec'] ?? null, 'language');
        $kernelspecLanguage = $kernelspecLanguage === null ? null : $this->normalizeLanguageHint($kernelspecLanguage);
        $diagnostics = [];

        if (($languageInfoLanguage === null || $languageInfoLanguage === '') && ($kernelspecLanguage === null || $kernelspecLanguage === '')) {
            $diagnostics[] = 'notebook-language-unknown';
        }
        if (
            $languageInfoLanguage !== null
            && $languageInfoLanguage !== ''
            && $kernelspecLanguage !== null
            && $kernelspecLanguage !== ''
            && $languageInfoLanguage !== $kernelspecLanguage
        ) {
            $diagnostics[] = 'notebook-language-info-kernelspec-mismatch';
        }

        return [
            'languageInfoName' => $languageInfoName,
            'languageInfoLanguage' => $languageInfoLanguage === '' ? null : $languageInfoLanguage,
            'kernelspecLanguage' => $kernelspecLanguage === '' ? null : $kernelspecLanguage,
            'resolvedLanguage' => $notebookLanguageHint['language'],
            'resolvedLanguageSource' => $notebookLanguageHint['source'],
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array{language:string, source:string}
     */
    private function notebookLanguageHint(array $metadata): array
    {
        $languageInfoName = $this->metadataString($metadata['language_info'] ?? null, 'name');
        if ($languageInfoName !== null) {
            $language = $this->normalizeLanguageHint($languageInfoName);
            if ($language !== '') {
                return [
                    'language' => $language,
                    'source' => 'notebook.language_info.name',
                ];
            }
        }

        $kernelspecLanguage = $this->metadataString($metadata['kernelspec'] ?? null, 'language');
        if ($kernelspecLanguage !== null) {
            $language = $this->normalizeLanguageHint($kernelspecLanguage);
            if ($language !== '') {
                return [
                    'language' => $language,
                    'source' => 'notebook.kernelspec.language',
                ];
            }
        }

        return [
            'language' => '',
            'source' => 'none',
        ];
    }

    /**
     * @param array<string, mixed> $cellMetadata
     * @param array{language:string, source:string} $notebookLanguageHint
     * @return array{languageHint:string, languageHintSource:string, languageHintIsCellMetadata:bool, languageHintIsNotebookFallback:bool, languageHintMatchesNotebook:?bool, languageHintDiagnostics:list<string>}
     */
    private function cellLanguageHintSummary(array $cellMetadata, array $notebookLanguageHint): array
    {
        $cellHint = $this->cellMetadataLanguageHint($cellMetadata);
        $diagnostics = [];
        $languageHint = $cellHint['language'];
        $languageHintSource = $cellHint['source'];
        $isCellMetadata = $languageHint !== '';
        $isNotebookFallback = false;

        if ($languageHint === '' && $notebookLanguageHint['language'] !== '') {
            $languageHint = $notebookLanguageHint['language'];
            $languageHintSource = $notebookLanguageHint['source'];
            $isNotebookFallback = true;
        }

        $matchesNotebook = null;
        if ($languageHint !== '' && $notebookLanguageHint['language'] !== '') {
            $matchesNotebook = $languageHint === $notebookLanguageHint['language'];
            if ($isCellMetadata && !$matchesNotebook) {
                $diagnostics[] = 'language-hint-mismatch-notebook-language';
            }
        }

        if ($languageHint === '') {
            $languageHint = 'unknown';
            $languageHintSource = 'none';
            $diagnostics[] = 'language-hint-unknown';
        }

        return [
            'languageHint' => $languageHint,
            'languageHintSource' => $languageHintSource,
            'languageHintIsCellMetadata' => $isCellMetadata,
            'languageHintIsNotebookFallback' => $isNotebookFallback,
            'languageHintMatchesNotebook' => $matchesNotebook,
            'languageHintDiagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $cellMetadata
     * @return array{language:string, source:string}
     */
    private function cellMetadataLanguageHint(array $cellMetadata): array
    {
        $directLanguage = $this->normalizeLanguageHintValue($cellMetadata['language'] ?? null);
        if ($directLanguage !== '') {
            return [
                'language' => $directLanguage,
                'source' => 'cell.metadata.language',
            ];
        }

        $languageInfoName = $this->metadataString($cellMetadata['language_info'] ?? null, 'name');
        if ($languageInfoName !== null) {
            $language = $this->normalizeLanguageHint($languageInfoName);
            if ($language !== '') {
                return [
                    'language' => $language,
                    'source' => 'cell.metadata.language_info.name',
                ];
            }
        }

        $vscodeLanguage = $this->metadataString($cellMetadata['vscode'] ?? null, 'languageId');
        if ($vscodeLanguage !== null) {
            $language = $this->normalizeLanguageHint($vscodeLanguage);
            if ($language !== '') {
                return [
                    'language' => $language,
                    'source' => 'cell.metadata.vscode.languageId',
                ];
            }
        }

        $jupyterLanguage = $this->metadataString($cellMetadata['jupyter'] ?? null, 'language');
        if ($jupyterLanguage !== null) {
            $language = $this->normalizeLanguageHint($jupyterLanguage);
            if ($language !== '') {
                return [
                    'language' => $language,
                    'source' => 'cell.metadata.jupyter.language',
                ];
            }
        }

        return [
            'language' => '',
            'source' => 'none',
        ];
    }

    /**
     * @param mixed $metadata
     */
    private function metadataString(mixed $metadata, string $key): ?string
    {
        if (!is_array($metadata)) {
            return null;
        }

        $value = $metadata[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param list<string> $mimeTypes
     * @return array{safeName:string, diagnostics:list<string>}
     */
    private function attachmentMediaPath(string $name, array $mimeTypes): array
    {
        $diagnostics = [];
        $path = $name;
        if ($path === '') {
            $diagnostics[] = 'attachment-empty-name';
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            $diagnostics[] = 'attachment-control-byte-name';
        }
        if (str_contains($path, '\\')) {
            $diagnostics[] = 'attachment-backslash-path';
        }

        $normalized = str_replace('\\', '/', $path);
        if ($this->isUri($normalized)) {
            $diagnostics[] = 'attachment-uri-name';
            $uriPath = parse_url($normalized, PHP_URL_PATH);
            $normalized = is_string($uriPath) && $uriPath !== '' ? $uriPath : $normalized;
        }
        if (str_contains($normalized, '?') || str_contains($normalized, '#')) {
            $diagnostics[] = 'attachment-query-or-fragment';
            $normalized = strtok($normalized, '?#') ?: $normalized;
        }
        if (str_starts_with($normalized, '/') || str_starts_with($normalized, '//') || preg_match('/\A[A-Za-z]:\//', $normalized) === 1) {
            $diagnostics[] = 'attachment-absolute-path';
        }
        if (str_contains($normalized, '%')) {
            $diagnostics[] = 'attachment-percent-encoded-path';
        }

        $segments = [];
        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                $diagnostics[] = 'attachment-path-traversal';
                continue;
            }
            $segments[] = $segment;
        }

        $requiresOpaqueName = array_diff($diagnostics, ['attachment-safe-path-remapped']) !== [];
        if ($requiresOpaqueName) {
            return [
                'safeName' => 'attachment-' . substr(sha1($name), 0, 12) . $this->attachmentExtension($name, $mimeTypes[0] ?? ''),
                'diagnostics' => $this->uniqueSortedStrings(array_merge($diagnostics, ['attachment-safe-path-remapped'])),
            ];
        }

        $safeSegments = [];
        foreach ($segments as $segment) {
            $safeSegment = preg_replace('/[^A-Za-z0-9._-]+/', '-', $segment) ?? '';
            $safeSegment = trim($safeSegment, '-');
            if ($safeSegment === '' || $safeSegment === '.' || $safeSegment === '..') {
                $safeSegment = 'attachment';
            }
            if ($safeSegment !== $segment) {
                $diagnostics[] = 'attachment-safe-path-remapped';
            }
            $safeSegments[] = $safeSegment;
        }

        $safeName = implode('/', $safeSegments);
        if ($safeName === '') {
            $safeName = 'attachment-' . substr(sha1($name), 0, 12) . $this->attachmentExtension($name, $mimeTypes[0] ?? '');
            $diagnostics[] = 'attachment-safe-path-remapped';
        }

        return [
            'safeName' => $safeName,
            'diagnostics' => $this->uniqueSortedStrings($diagnostics),
        ];
    }

    /**
     * @param list<string> $mimeTypes
     */
    private function disambiguateAttachmentMediaPath(string $mediaPath, int $cellIndex, string $name, array $mimeTypes): string
    {
        $extension = $this->pathExtension($mediaPath);
        $stem = $extension === '' ? $mediaPath : substr($mediaPath, 0, -strlen($extension));
        $suffix = substr(sha1($cellIndex . "\0" . $name . "\0" . implode("\0", $mimeTypes)), 0, 12);

        return $stem . '-' . $suffix . $extension;
    }

    private function attachmentExtension(string $name, string $mimeType): string
    {
        $extension = $this->pathExtension(strtok(str_replace('\\', '/', $name), '?#') ?: $name);
        if ($extension !== '' && !str_contains($extension, '%')) {
            return $extension;
        }

        return match (strtolower($mimeType)) {
            'image/apng' => '.apng',
            'image/avif' => '.avif',
            'image/gif' => '.gif',
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/svg+xml' => '.svg',
            'image/webp' => '.webp',
            'text/html' => '.html',
            'text/plain' => '.txt',
            'application/json' => '.json',
            'application/pdf' => '.pdf',
            default => '',
        };
    }

    private function mimeTypeFromPath(string $path): string
    {
        return match (strtolower($this->pathExtension($path))) {
            '.apng' => 'image/apng',
            '.avif' => 'image/avif',
            '.gif' => 'image/gif',
            '.jpeg', '.jpg', '.jpe' => 'image/jpeg',
            '.png' => 'image/png',
            '.svg', '.svgz' => 'image/svg+xml',
            '.webp' => 'image/webp',
            '.html', '.htm' => 'text/html',
            '.txt', '.text' => 'text/plain',
            '.json' => 'application/json',
            '.pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }

    private function pathExtension(string $path): string
    {
        $basename = basename($path);
        $position = strrpos($basename, '.');
        if ($position === false || $position === 0) {
            return '';
        }

        return substr($basename, $position);
    }

    private function isUri(string $source): bool
    {
        return preg_match('/\A[A-Za-z][A-Za-z0-9+.-]*:/', $source) === 1
            && preg_match('/\A[A-Za-z]:\//', $source) !== 1;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return list<string>
     */
    private function metadataKeys(array $metadata): array
    {
        $keys = [];
        foreach ($metadata as $key => $_value) {
            if (is_string($key) && $key !== '') {
                $keys[] = $key;
            }
        }
        sort($keys);

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function metadataStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $strings = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $strings[] = $item;
            }
        }
        sort($strings);

        return array_values(array_unique($strings));
    }

    private function isStringList(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $item) {
            if (!is_string($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $strings
     * @return list<string>
     */
    private function uniqueSortedStrings(array $strings): array
    {
        $strings = array_values(array_unique(array_filter($strings, static fn (string $string): bool => $string !== '')));
        sort($strings);

        return $strings;
    }

    private function normalizeLanguageHintValue(mixed $value): string
    {
        return is_string($value) ? $this->normalizeLanguageHint($value) : '';
    }

    private function normalizeLanguageHint(string $language): string
    {
        $normalized = strtolower(trim($language));
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/[^a-z0-9_+.-]+/', '-', $normalized) ?? '';

        return trim($normalized, '-.');
    }

    private function sanitizeClassToken(string $token): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '', $token) ?? '';
    }
}
