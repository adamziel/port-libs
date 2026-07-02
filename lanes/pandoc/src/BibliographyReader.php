<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class BibliographyReader
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly string $format,
        private readonly array $options = [],
    ) {
    }

    public function read(string $bytes): AstNode
    {
        $items = $this->items($bytes);
        $ids = $this->itemIds($items);
        $itemReview = $this->itemReviewSummary($items);
        $processor = CitationCslProcessor::fromItems($items);
        $bibliography = $processor->bibliographyDefinitionList($ids);
        $attrs = [
            'sourceFormat' => $this->format,
            'bibliography' => [
                'format' => $this->format,
                'reader' => self::class,
                'parser' => $this->parserName(),
                'itemCount' => count($items),
                'itemIds' => $ids,
                'sourceBytes' => strlen($bytes),
                'sourceSha256' => hash('sha256', $bytes),
                'payloadExposurePolicy' => 'source-bytes-omitted',
                'itemTypeCounts' => $itemReview['typeCounts'],
                'itemFieldNameCounts' => $itemReview['fieldNameCounts'],
                'creatorItemCount' => $itemReview['creatorItemCount'],
                'dateItemCount' => $itemReview['dateItemCount'],
                'issuedDateItemCount' => $itemReview['issuedDateItemCount'],
                'keywordItemCount' => $itemReview['keywordItemCount'],
                'categoryItemCount' => $itemReview['categoryItemCount'],
                'sourceFileItemCount' => $itemReview['sourceFileItemCount'],
                'sourceFileCount' => $itemReview['sourceFileCount'],
                'sourceFileDiagnosticCount' => $itemReview['sourceFileDiagnosticCount'],
                'citationAliasItemCount' => $itemReview['citationAliasItemCount'],
                'citationAliasCount' => $itemReview['citationAliasCount'],
                'itemMetadataReviewPolicy' => $itemReview['reviewPolicy'],
            ],
            'cslItemCount' => count($items),
            'cslItemIds' => $ids,
            'cslItems' => $items,
            'cslItemReviewSummary' => $itemReview,
        ];

        return new AstNode('document', $attrs, $bibliography->children === [] ? [] : [$bibliography]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(string $bytes): array
    {
        return match ($this->format) {
            'bibtex', 'biblatex' => CitationCslProcessor::bibtexItems($bytes),
            'csljson' => $this->cslJsonItems($bytes),
            'endnotexml' => CitationCslProcessor::endnoteXmlItems($bytes),
            'ris' => CitationCslProcessor::risItems($bytes),
            default => throw new \InvalidArgumentException("Unsupported bibliography input format '{$this->format}'."),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cslJsonItems(string $json): array
    {
        return CitationCslProcessor::cslJsonItems($json);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<string>
     */
    private function itemIds(array $items): array
    {
        $ids = [];
        foreach ($items as $index => $item) {
            $id = $item['id'] ?? null;
            if (!is_string($id) && !is_int($id)) {
                throw new \InvalidArgumentException('CSL item at index ' . $index . ' is missing string id');
            }

            $id = trim((string) $id);
            if ($id === '') {
                throw new \InvalidArgumentException('CSL item at index ' . $index . ' has an empty id');
            }

            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private function itemReviewSummary(array $items): array
    {
        $typeCounts = [];
        $fieldNameCounts = [];
        $creatorItemCount = 0;
        $dateItemCount = 0;
        $issuedDateItemCount = 0;
        $keywordItemCount = 0;
        $categoryItemCount = 0;
        $sourceFileItemCount = 0;
        $sourceFileCount = 0;
        $sourceFileDiagnosticCount = 0;
        $citationAliasItemCount = 0;
        $citationAliasCount = 0;

        foreach ($items as $item) {
            $type = $this->metadataString($item['type'] ?? null) ?: 'unknown';
            $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;

            foreach (array_keys($item) as $fieldName) {
                $fieldName = (string) $fieldName;
                $fieldNameCounts[$fieldName] = ($fieldNameCounts[$fieldName] ?? 0) + 1;
            }

            if ($this->hasAnyMetadata($item, $this->creatorFields())) {
                $creatorItemCount++;
            }
            if ($this->hasAnyMetadata($item, $this->dateFields())) {
                $dateItemCount++;
            }
            if ($this->hasAnyMetadata($item, ['issued', 'issuedDate', 'issued-date', 'issueddate', 'date'])) {
                $issuedDateItemCount++;
            }
            if ($this->hasAnyMetadata($item, ['keyword', 'keywords', 'keyword-list', 'keywordList', 'keywordlist'])) {
                $keywordItemCount++;
            }
            if ($this->hasAnyMetadata($item, ['category', 'categories', 'category-list', 'categoryList', 'categorylist'])) {
                $categoryItemCount++;
            }

            $sourceFiles = $this->metadataList($item['sourceFiles'] ?? $item['source-files'] ?? $item['sourceFile'] ?? $item['source-file'] ?? null);
            if ($sourceFiles !== []) {
                $sourceFileItemCount++;
                $sourceFileCount += count($sourceFiles);
            }

            $sourceFileDiagnostics = $this->metadataList($item['sourceFileDiagnostics'] ?? $item['source-file-diagnostics'] ?? null);
            $sourceFileDiagnosticCount += count($sourceFileDiagnostics);

            $citationAliases = $this->metadataList($item['citationAliases'] ?? $item['citation-aliases'] ?? $item['citationAlias'] ?? $item['citation-alias'] ?? $item['ids'] ?? null);
            if ($citationAliases !== []) {
                $citationAliasItemCount++;
                $citationAliasCount += count($citationAliases);
            }
        }

        ksort($typeCounts, SORT_STRING);
        ksort($fieldNameCounts, SORT_STRING);

        return [
            'typeCounts' => $typeCounts,
            'fieldNameCounts' => $fieldNameCounts,
            'creatorItemCount' => $creatorItemCount,
            'dateItemCount' => $dateItemCount,
            'issuedDateItemCount' => $issuedDateItemCount,
            'keywordItemCount' => $keywordItemCount,
            'categoryItemCount' => $categoryItemCount,
            'sourceFileItemCount' => $sourceFileItemCount,
            'sourceFileCount' => $sourceFileCount,
            'sourceFileDiagnosticCount' => $sourceFileDiagnosticCount,
            'citationAliasItemCount' => $citationAliasItemCount,
            'citationAliasCount' => $citationAliasCount,
            'reviewPolicy' => 'aggregate-field-names-and-counts-only',
        ];
    }

    /**
     * @return list<string>
     */
    private function creatorFields(): array
    {
        return [
            'author',
            'editor',
            'translator',
            'container-author',
            'containerAuthor',
            'collection-editor',
            'collectionEditor',
            'original-author',
            'originalAuthor',
            'reviewed-author',
            'reviewedAuthor',
            'director',
            'producer',
            'composer',
            'performer',
            'recipient',
            'authority',
            'chair',
            'curator',
            'eventorganizer',
            'eventOrganizer',
        ];
    }

    /**
     * @return list<string>
     */
    private function dateFields(): array
    {
        return [
            'issued',
            'issuedDate',
            'issued-date',
            'issueddate',
            'date',
            'accessed',
            'accessedDate',
            'accessed-date',
            'available-date',
            'availableDate',
            'original-date',
            'originalDate',
            'submitted',
            'submitted-date',
            'submittedDate',
            'event-date',
            'eventDate',
            'label-date',
            'labelDate',
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @param list<string> $fields
     */
    private function hasAnyMetadata(array $item, array $fields): bool
    {
        foreach ($fields as $field) {
            if (array_key_exists($field, $item) && $this->metadataPresent($item[$field])) {
                return true;
            }
        }

        return false;
    }

    private function metadataPresent(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return !is_array($value) || $value !== [];
    }

    /**
     * @return list<mixed>
     */
    private function metadataList(mixed $value): array
    {
        if (!is_array($value)) {
            return $value === null || $value === '' ? [] : [$value];
        }

        return $value === [] ? [] : array_values($value);
    }

    private function metadataString(mixed $value): string
    {
        return is_string($value) || is_int($value) ? trim((string) $value) : '';
    }

    private function parserName(): string
    {
        return match ($this->format) {
            'bibtex', 'biblatex' => BibtexCslParser::class,
            'csljson' => 'CSL JSON',
            'endnotexml' => 'EndNote XML',
            'ris' => 'RIS',
            default => 'unknown',
        };
    }
}
