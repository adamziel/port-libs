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
        $language = $this->metadataString($metadata['language_info'] ?? null, 'name')
            ?? $this->metadataString($metadata['kernelspec'] ?? null, 'language')
            ?? '';

        $blocks = [];
        $cellSummaries = [];
        $markdownCellCount = 0;
        $codeCellCount = 0;
        $rawCellCount = 0;
        $attachmentCount = 0;
        $outputCount = 0;
        $unsupportedResourceCount = 0;
        $outputBytePresenceCount = 0;
        $outputMimeBundleCount = 0;
        $outputGroupCount = 0;
        $outputStreamGroupCount = 0;
        $outputRepeatedStreamNameCount = 0;
        $outputMimeBundleDigestCount = 0;
        $outputRepeatedMimeBundleDigestCount = 0;
        $outputRepeatedMimeBundleDigestDuplicateCount = 0;
        $outputRepeatedMimeBundleKeyCount = 0;
        $outputRepeatedMimeBundleKeyDuplicateCount = 0;
        $outputRepeatedStreamNameGroupDuplicateCount = 0;
        $outputMimeBundleDigestRecords = [];
        $outputTypeCounts = [];
        $outputRichOutputKindCounts = [];
        $outputGroupKindCounts = [];
        $outputGroupTypeCounts = [];
        $outputPolicyDiagnosticCounts = [];
        $outputAggregateDiagnostics = [];
        $metadataKeys = $this->metadataKeys($metadata);

        foreach ($cells as $index => $cell) {
            if (!is_array($cell)) {
                throw new \InvalidArgumentException("IPYNB cell {$index} is not an object");
            }

            $cellType = $this->cellType($cell['cell_type'] ?? null);
            $source = $this->normalizeSource($cell['source'] ?? '', "IPYNB cell {$index} source");
            if (strlen($source) > self::MAX_CELL_SOURCE_BYTES) {
                throw new \InvalidArgumentException("IPYNB cell {$index} exceeds the bounded native reader source limit");
            }

            $attachments = isset($cell['attachments']) && is_array($cell['attachments']) ? $cell['attachments'] : [];
            $outputs = isset($cell['outputs']) && is_array($cell['outputs']) ? $cell['outputs'] : [];
            $attachmentSummary = $this->attachmentSummary($attachments);
            $outputSummary = $this->outputSummary($outputs);
            $cellMetadata = isset($cell['metadata']) && is_array($cell['metadata']) ? $cell['metadata'] : [];
            $cellMetadataKeys = $this->metadataKeys($cellMetadata);
            $cellTags = $this->metadataStringList($cellMetadata['tags'] ?? null);
            $cellDiagnostics = $this->cellDiagnostics($attachmentSummary, $outputSummary);

            $attachmentCount += $attachmentSummary['count'];
            $outputCount += $outputSummary['count'];
            $unsupportedResourceCount += $attachmentSummary['count'] + $outputSummary['count'];
            $outputBytePresenceCount += $outputSummary['bytePresenceCount'];
            $outputMimeBundleCount += $outputSummary['mimeBundleCount'];
            $outputGroupCount += $outputSummary['groupCount'];
            $outputStreamGroupCount += $outputSummary['streamGroupCount'];
            $outputRepeatedStreamNameCount += count($outputSummary['repeatedStreamNames']);
            $outputMimeBundleDigestCount += count($outputSummary['mimeBundleDigests']);
            $outputRepeatedMimeBundleDigestCount += count($outputSummary['repeatedMimeBundleDigests']);
            $outputRepeatedMimeBundleDigestDuplicateCount += $outputSummary['repeatedMimeBundleDigestDuplicateCount'];
            $outputRepeatedMimeBundleKeyCount += count($outputSummary['repeatedMimeBundleKeys']);
            foreach ($outputSummary['repeatedMimeBundleRecords'] as $record) {
                $recordCount = $record['count'] ?? 0;
                if (is_int($recordCount) && $recordCount > 1) {
                    $outputRepeatedMimeBundleKeyDuplicateCount += $recordCount - 1;
                }
            }
            foreach ($outputSummary['repeatedStreamNameRecords'] as $record) {
                $recordGroupCount = $record['groupCount'] ?? 0;
                if (is_int($recordGroupCount) && $recordGroupCount > 1) {
                    $outputRepeatedStreamNameGroupDuplicateCount += $recordGroupCount - 1;
                }
            }
            foreach ($outputSummary['orderTypes'] as $outputType) {
                $this->incrementCount($outputTypeCounts, $outputType);
            }
            foreach ($outputSummary['outputs'] as $outputRow) {
                $richOutputKind = $outputRow['richOutputKind'] ?? null;
                if (is_string($richOutputKind) && $richOutputKind !== '') {
                    $this->incrementCount($outputRichOutputKindCounts, $richOutputKind);
                }
                foreach (($outputRow['diagnostics'] ?? []) as $diagnostic) {
                    if (is_string($diagnostic) && $diagnostic !== '') {
                        $this->incrementCount($outputPolicyDiagnosticCounts, $diagnostic);
                    }
                }
            }
            foreach ($outputSummary['groups'] as $outputGroup) {
                $groupKind = $outputGroup['kind'] ?? null;
                $groupType = $outputGroup['type'] ?? null;
                if (is_string($groupKind) && $groupKind !== '') {
                    $this->incrementCount($outputGroupKindCounts, $groupKind);
                }
                if (is_string($groupType) && $groupType !== '') {
                    $this->incrementCount($outputGroupTypeCounts, $groupType);
                }
            }
            foreach ($outputSummary['mimeBundleDigestRecords'] as $digestRecord) {
                $digestRecord['cellIndex'] = $index;
                $digestRecord['cellType'] = $cellType;
                $outputMimeBundleDigestRecords[] = $digestRecord;
            }
            foreach ($outputSummary['aggregateDiagnostics'] as $diagnostic) {
                $outputAggregateDiagnostics[] = $diagnostic;
            }

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
            if (array_key_exists('execution_count', $cell) && is_int($cell['execution_count'])) {
                $attributes['data-ipynb-execution-count'] = (string) $cell['execution_count'];
            }
            if ($cellTags !== []) {
                $attributes['data-ipynb-cell-tags'] = implode(' ', $cellTags);
            }
            if ($cellDiagnostics !== []) {
                $attributes['data-ipynb-diagnostics'] = implode(' ', $cellDiagnostics);
            }

            $children = match ($cellType) {
                'markdown' => $this->markdownCellBlocks($source),
                'code' => [$this->codeCellBlock($source, $language, $index, $cell)],
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
                'ipynbUnsupportedResourceCount' => $attachmentSummary['count'] + $outputSummary['count'],
                'ipynbUnsupportedResourceDiagnostics' => $cellDiagnostics,
                'ipynbCellMetadataKeys' => $cellMetadataKeys,
                'ipynbCellTags' => $cellTags,
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
                'sourceBytes' => strlen($source),
                'attachmentCount' => $attachmentSummary['count'],
                'attachmentMimeTypes' => $attachmentSummary['mimeTypes'],
                'outputCount' => $outputSummary['count'],
                'outputTypes' => $outputSummary['types'],
                'outputOrderTypes' => $outputSummary['orderTypes'],
                'outputIndexes' => $outputSummary['indexes'],
                'outputSummaries' => $outputSummary['outputs'],
                'outputMimeTypes' => $outputSummary['mimeTypes'],
                'outputMimeBundleCount' => $outputSummary['mimeBundleCount'],
                'outputMimeBundleDigests' => $outputSummary['mimeBundleDigests'],
                'outputMimeBundleDigestRecords' => $outputSummary['mimeBundleDigestRecords'],
                'outputRepeatedMimeBundleDigests' => $outputSummary['repeatedMimeBundleDigests'],
                'outputRepeatedMimeBundleDigestRecords' => $outputSummary['repeatedMimeBundleDigestRecords'],
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
                'unsupportedResourceCount' => $attachmentSummary['count'] + $outputSummary['count'],
                'diagnostics' => $cellDiagnostics,
                'metadataKeys' => $cellMetadataKeys,
                'tags' => $cellTags,
            ];
        }

        $outputMimeBundleDigestCollisionRecords = $this->mimeBundleDigestCollisionRecords($outputMimeBundleDigestRecords);
        $outputMimeBundleDigestCollisionPolicy = $this->mimeBundleDigestCollisionPolicy(
            $outputMimeBundleDigestRecords,
            $outputMimeBundleDigestCollisionRecords
        );
        $outputDigestCollisionCounts = $outputMimeBundleDigestCollisionPolicy['counts'];

        return new AstNode('document', [
            'sourceFormat' => 'ipynb',
            'notebookCellCount' => count($cells),
            'notebookMarkdownCellCount' => $markdownCellCount,
            'notebookCodeCellCount' => $codeCellCount,
            'notebookRawCellCount' => $rawCellCount,
            'notebookAttachmentCount' => $attachmentCount,
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
            'notebookOutputMimeBundleDigestCollisionCount' => count($outputMimeBundleDigestCollisionRecords),
            'notebookOutputMimeBundleDigestCollisionDuplicateCount' => $outputDigestCollisionCounts['duplicateCount'],
            'notebookOutputMimeBundleDigestCollisionRecords' => $outputMimeBundleDigestCollisionRecords,
            'notebookOutputMimeBundleDigestCollisionPolicy' => $outputMimeBundleDigestCollisionPolicy,
            'notebookOutputDuplicatePolicyCounts' => [
                'repeatedStreamNameCount' => $outputRepeatedStreamNameCount,
                'repeatedStreamNameGroupDuplicateCount' => $outputRepeatedStreamNameGroupDuplicateCount,
                'repeatedMimeBundleKeyCount' => $outputRepeatedMimeBundleKeyCount,
                'repeatedMimeBundleKeyDuplicateCount' => $outputRepeatedMimeBundleKeyDuplicateCount,
                'repeatedMimeBundleDigestCount' => $outputRepeatedMimeBundleDigestCount,
                'repeatedMimeBundleDigestDuplicateCount' => $outputRepeatedMimeBundleDigestDuplicateCount,
                'digestCollisionCount' => count($outputMimeBundleDigestCollisionRecords),
                'digestCollisionDuplicateCount' => $outputDigestCollisionCounts['duplicateCount'],
                'digestCrossCellCollisionCount' => $outputDigestCollisionCounts['crossCellCount'],
                'digestSameOutputIndexCollisionCount' => $outputDigestCollisionCounts['sameOutputIndexCount'],
                'digestDifferentOutputIndexCollisionCount' => $outputDigestCollisionCounts['differentOutputIndexCount'],
                'digestSameGroupIndexCollisionCount' => $outputDigestCollisionCounts['sameGroupIndexCount'],
                'digestDifferentGroupIndexCollisionCount' => $outputDigestCollisionCounts['differentGroupIndexCount'],
                'digestMixedOutputTypeCollisionCount' => $outputDigestCollisionCounts['mixedOutputTypeCount'],
            ],
            'notebookOutputGroupingSummary' => [
                'outputCount' => $outputCount,
                'groupCount' => $outputGroupCount,
                'streamGroupCount' => $outputStreamGroupCount,
                'outputTypeCounts' => $this->sortedIntMap($outputTypeCounts),
                'richOutputKindCounts' => $this->sortedIntMap($outputRichOutputKindCounts),
                'groupKindCounts' => $this->sortedIntMap($outputGroupKindCounts),
                'groupTypeCounts' => $this->sortedIntMap($outputGroupTypeCounts),
            ],
            'notebookOutputPolicyDiagnosticCounts' => $this->sortedIntMap($outputPolicyDiagnosticCounts),
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
     * @param array<string, mixed> $cell
     */
    private function codeCellBlock(string $source, string $language, int $index, array $cell): AstNode
    {
        $classes = ['ipynb-code-cell-source'];
        if ($language !== '') {
            array_unshift($classes, $this->sanitizeClassToken($language));
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

    private function normalizeSource(mixed $source, string $label): string
    {
        if (is_string($source)) {
            return $source;
        }

        if (is_array($source)) {
            $parts = [];
            foreach ($source as $index => $line) {
                if (!is_string($line)) {
                    throw new \InvalidArgumentException("{$label} entry {$index} is not a string");
                }
                $parts[] = $line;
            }

            return implode('', $parts);
        }

        throw new \InvalidArgumentException("{$label} must be a string or string array");
    }

    /**
     * @param array<string, mixed> $attachments
     * @return array{count:int, names:list<string>, mimeTypes:list<string>}
     */
    private function attachmentSummary(array $attachments): array
    {
        $names = [];
        $mimeTypes = [];
        foreach ($attachments as $name => $payload) {
            if (!is_array($payload)) {
                continue;
            }
            $names[] = (string) $name;
            foreach ($payload as $mimeType => $data) {
                if (!is_string($mimeType) || $mimeType === '' || !is_scalar($data)) {
                    continue;
                }
                $mimeTypes[] = $mimeType;
            }
        }
        sort($names);
        sort($mimeTypes);

        return [
            'count' => count($names),
            'names' => $names,
            'mimeTypes' => array_values(array_unique($mimeTypes)),
        ];
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
     * @param array{count:int, names:list<string>, mimeTypes:list<string>} $attachmentSummary
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
     * @param list<int> $ints
     * @return list<int>
     */
    private function sortedUniqueInts(array $ints): array
    {
        sort($ints, SORT_NUMERIC);

        return array_values(array_unique($ints));
    }

    /**
     * @param array<string, int> $counts
     * @return array<string, int>
     */
    private function sortedIntMap(array $counts): array
    {
        ksort($counts);

        return $counts;
    }

    /**
     * @param array<string, int> $counts
     */
    private function incrementCount(array &$counts, string $key): void
    {
        $counts[$key] = ($counts[$key] ?? 0) + 1;
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
     * @param list<array<string, mixed>> $digestRecords
     * @return list<array<string, mixed>>
     */
    private function mimeBundleDigestCollisionRecords(array $digestRecords): array
    {
        $recordsByDigest = [];
        foreach ($digestRecords as $digestRecord) {
            $digest = $digestRecord['digest'] ?? null;
            $cellIndex = $digestRecord['cellIndex'] ?? null;
            $outputIndex = $digestRecord['outputIndex'] ?? null;
            $groupIndex = $digestRecord['groupIndex'] ?? null;
            $outputType = $digestRecord['outputType'] ?? null;
            if (
                !is_string($digest)
                || !is_int($cellIndex)
                || !is_int($outputIndex)
                || !is_int($groupIndex)
                || !is_string($outputType)
            ) {
                continue;
            }

            $recordsByDigest[$digest] ??= [
                'digest' => $digest,
                'occurrences' => [],
                'cellIndexes' => [],
                'outputIndexes' => [],
                'groupIndexes' => [],
                'outputTypes' => [],
                'mimeTypes' => [],
                'count' => 0,
            ];

            $mimeTypes = $digestRecord['mimeTypes'] ?? [];
            $mimeTypes = is_array($mimeTypes)
                ? array_values(array_filter($mimeTypes, static fn (mixed $value): bool => is_string($value) && $value !== ''))
                : [];

            $recordsByDigest[$digest]['occurrences'][] = [
                'cellIndex' => $cellIndex,
                'cellType' => is_string($digestRecord['cellType'] ?? null) ? $digestRecord['cellType'] : 'unknown',
                'outputIndex' => $outputIndex,
                'groupIndex' => $groupIndex,
                'outputType' => $outputType,
                'mimeTypes' => $mimeTypes,
                'fingerprintSource' => is_string($digestRecord['fingerprintSource'] ?? null) ? $digestRecord['fingerprintSource'] : 'metadata-only',
            ];
            $recordsByDigest[$digest]['cellIndexes'][] = $cellIndex;
            $recordsByDigest[$digest]['outputIndexes'][] = $outputIndex;
            $recordsByDigest[$digest]['groupIndexes'][] = $groupIndex;
            $recordsByDigest[$digest]['outputTypes'][] = $outputType;
            foreach ($mimeTypes as $mimeType) {
                $recordsByDigest[$digest]['mimeTypes'][] = $mimeType;
            }
            $recordsByDigest[$digest]['count']++;
        }
        ksort($recordsByDigest);

        $records = [];
        foreach ($recordsByDigest as $record) {
            if ($record['count'] < 2) {
                continue;
            }

            $cellIndexes = $this->sortedUniqueInts($record['cellIndexes']);
            $outputIndexes = $this->sortedUniqueInts($record['outputIndexes']);
            $groupIndexes = $this->sortedUniqueInts($record['groupIndexes']);
            $outputTypes = array_values(array_unique($record['outputTypes']));
            $mimeTypes = $this->sortedUniqueStrings($record['mimeTypes']);

            $collisionScopes = [];
            $policyDiagnostics = ['ipynb-output-mime-bundle-digest-collision-review'];
            if (count($cellIndexes) > 1) {
                $collisionScopes[] = 'cross-cell';
                $policyDiagnostics[] = 'ipynb-output-mime-bundle-digest-cross-cell-review';
            }
            if (count($outputIndexes) === 1) {
                $collisionScopes[] = 'same-output-index';
                $policyDiagnostics[] = 'ipynb-output-mime-bundle-digest-same-output-index-review';
            } else {
                $collisionScopes[] = 'different-output-index';
                $policyDiagnostics[] = 'ipynb-output-mime-bundle-digest-different-output-index-review';
            }
            if (count($groupIndexes) === 1) {
                $collisionScopes[] = 'same-group-index';
                $policyDiagnostics[] = 'ipynb-output-mime-bundle-digest-same-group-index-review';
            } else {
                $collisionScopes[] = 'different-group-index';
                $policyDiagnostics[] = 'ipynb-output-mime-bundle-digest-different-group-index-review';
            }
            if (count($outputTypes) > 1) {
                $collisionScopes[] = 'mixed-output-type';
                $policyDiagnostics[] = 'ipynb-output-mime-bundle-digest-mixed-output-type-review';
            }

            $records[] = [
                'digest' => $record['digest'],
                'occurrenceCount' => $record['count'],
                'duplicateCount' => $record['count'] - 1,
                'cellIndexes' => $cellIndexes,
                'outputIndexes' => $outputIndexes,
                'groupIndexes' => $groupIndexes,
                'outputTypes' => $outputTypes,
                'mimeTypes' => $mimeTypes,
                'collisionScopes' => $collisionScopes,
                'policyDiagnostics' => $policyDiagnostics,
                'occurrences' => $record['occurrences'],
                'fingerprintSource' => 'metadata-only',
                'byteExposure' => 'blocked',
            ];
        }

        return $records;
    }

    /**
     * @param list<array<string, mixed>> $digestRecords
     * @param list<array<string, mixed>> $collisionRecords
     * @return array<string, mixed>
     */
    private function mimeBundleDigestCollisionPolicy(array $digestRecords, array $collisionRecords): array
    {
        $uniqueDigests = array_values(array_filter(
            array_column($digestRecords, 'digest'),
            static fn (mixed $value): bool => is_string($value) && $value !== ''
        ));
        $counts = [
            'digestRecordCount' => count($digestRecords),
            'uniqueDigestCount' => count(array_unique($uniqueDigests)),
            'collisionCount' => count($collisionRecords),
            'duplicateCount' => 0,
            'crossCellCount' => 0,
            'sameOutputIndexCount' => 0,
            'differentOutputIndexCount' => 0,
            'sameGroupIndexCount' => 0,
            'differentGroupIndexCount' => 0,
            'mixedOutputTypeCount' => 0,
        ];

        foreach ($collisionRecords as $record) {
            $duplicateCount = $record['duplicateCount'] ?? 0;
            if (is_int($duplicateCount)) {
                $counts['duplicateCount'] += $duplicateCount;
            }
            $scopes = $record['collisionScopes'] ?? [];
            if (!is_array($scopes)) {
                continue;
            }
            if (in_array('cross-cell', $scopes, true)) {
                $counts['crossCellCount']++;
            }
            if (in_array('same-output-index', $scopes, true)) {
                $counts['sameOutputIndexCount']++;
            }
            if (in_array('different-output-index', $scopes, true)) {
                $counts['differentOutputIndexCount']++;
            }
            if (in_array('same-group-index', $scopes, true)) {
                $counts['sameGroupIndexCount']++;
            }
            if (in_array('different-group-index', $scopes, true)) {
                $counts['differentGroupIndexCount']++;
            }
            if (in_array('mixed-output-type', $scopes, true)) {
                $counts['mixedOutputTypeCount']++;
            }
        }

        $diagnostics = [];
        if ($counts['digestRecordCount'] > 0) {
            $diagnostics[] = 'ipynb-output-mime-bundle-digest-metadata-only';
        }
        if ($counts['collisionCount'] > 0) {
            $diagnostics[] = 'ipynb-output-mime-bundle-digest-collision-review';
        }
        if ($counts['crossCellCount'] > 0) {
            $diagnostics[] = 'ipynb-output-mime-bundle-digest-cross-cell-review';
        }
        if ($counts['sameOutputIndexCount'] > 0) {
            $diagnostics[] = 'ipynb-output-mime-bundle-digest-same-output-index-review';
        }
        if ($counts['differentOutputIndexCount'] > 0) {
            $diagnostics[] = 'ipynb-output-mime-bundle-digest-different-output-index-review';
        }
        if ($counts['sameGroupIndexCount'] > 0) {
            $diagnostics[] = 'ipynb-output-mime-bundle-digest-same-group-index-review';
        }
        if ($counts['differentGroupIndexCount'] > 0) {
            $diagnostics[] = 'ipynb-output-mime-bundle-digest-different-group-index-review';
        }
        if ($counts['mixedOutputTypeCount'] > 0) {
            $diagnostics[] = 'ipynb-output-mime-bundle-digest-mixed-output-type-review';
        }

        return [
            'state' => $counts['digestRecordCount'] > 0 ? 'metadata-only' : 'none',
            'fingerprintSource' => 'metadata-only',
            'payloadPolicy' => 'shape-only',
            'byteExposure' => 'blocked',
            'collisionPolicy' => $counts['collisionCount'] > 0 ? 'review' : 'none',
            'counts' => $counts,
            'diagnostics' => $diagnostics,
        ];
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

    private function sanitizeClassToken(string $token): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '', $token) ?? '';
    }
}
