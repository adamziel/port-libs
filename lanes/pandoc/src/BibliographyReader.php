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
            ],
            'cslItemCount' => count($items),
            'cslItemIds' => $ids,
            'cslItems' => $items,
        ];

        if ($this->format === 'csljson') {
            $review = $this->cslJsonReview($items, $ids);
            $attrs['bibliography']['cslJsonReview'] = $review;
            $attrs['cslJsonReview'] = $review;
            $attrs['cslJsonItemReviews'] = $review['items'];
        }

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
        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid CSL JSON: ' . json_last_error_msg());
        }
        if (!is_array($decoded) || !$this->decodedJsonIsList($decoded, $json)) {
            throw new \InvalidArgumentException('CSL JSON bibliography must be a list of item objects');
        }

        /** @var list<array<string, mixed>> $decoded */
        CitationCslProcessor::fromItems($decoded);

        return $decoded;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<string> $ids
     * @return array<string, mixed>
     */
    private function cslJsonReview(array $items, array $ids): array
    {
        $reviews = [];
        $fieldNames = [];
        $typeCounts = [];
        $nameVariableCounts = [];
        $dateVariableCounts = [];
        $identifierFieldCounts = [];
        $titleBearingItemCount = 0;
        $linkBearingItemCount = 0;

        foreach ($items as $index => $item) {
            $review = $this->cslJsonItemReview($item, $index);
            $reviews[] = $review;

            foreach ($review['fieldNames'] as $fieldName) {
                $fieldNames[$fieldName] = true;
            }

            $type = (string) $review['type'];
            if ($type !== '') {
                $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
            }

            foreach ($review['nameVariableCounts'] as $variable => $count) {
                $nameVariableCounts[$variable] = ($nameVariableCounts[$variable] ?? 0) + $count;
            }
            foreach ($review['datePartCounts'] as $variable => $count) {
                $dateVariableCounts[$variable] = ($dateVariableCounts[$variable] ?? 0) + $count;
            }
            foreach ($review['identifierFields'] as $fieldName) {
                $identifierFieldCounts[$fieldName] = ($identifierFieldCounts[$fieldName] ?? 0) + 1;
            }

            if ($review['titleBearing']) {
                $titleBearingItemCount++;
            }
            if ($review['linkBearing']) {
                $linkBearingItemCount++;
            }
        }

        ksort($typeCounts);
        ksort($nameVariableCounts);
        ksort($dateVariableCounts);
        ksort($identifierFieldCounts);

        return [
            'scope' => 'csl-json-bibliography',
            'byteExposurePolicy' => 'metadata-only',
            'externalTooling' => false,
            'itemCount' => count($items),
            'itemIds' => $ids,
            'fieldNameCount' => count($fieldNames),
            'fieldNames' => $this->uniqueSortedStrings(array_keys($fieldNames)),
            'typeCounts' => $typeCounts,
            'titleBearingItemCount' => $titleBearingItemCount,
            'linkBearingItemCount' => $linkBearingItemCount,
            'nameVariableCounts' => $nameVariableCounts,
            'dateVariableCounts' => $dateVariableCounts,
            'identifierFieldCounts' => $identifierFieldCounts,
            'items' => $reviews,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function cslJsonItemReview(array $item, int $index): array
    {
        $fieldNames = $this->uniqueSortedStrings(array_map('strval', array_keys($item)));
        $nameVariableCounts = $this->cslJsonNameVariableCounts($item);
        $datePartCounts = $this->cslJsonDatePartCounts($item);
        $identifierFields = $this->presentFieldNames($item, [
            'DOI', 'doi',
            'ISBN', 'isbn', 'ISBN-13', 'isbn-13', 'ISBN13', 'isbn13', 'ISBN-10', 'isbn-10', 'ISBN10', 'isbn10',
            'ISSN', 'issn', 'printISSN', 'print-issn', 'pISSN', 'p-issn', 'eISSN', 'e-issn', 'onlineISSN', 'online-issn',
            'PMID', 'pmid', 'PMCID', 'pmcid', 'ISAN', 'isan', 'ISMN', 'ismn', 'ISRN', 'isrn', 'ISWC', 'iswc',
            'MRNumber', 'mrNumber', 'mrnumber', 'Zbl', 'zbl', 'JSTOR', 'jstor', 'HDL', 'hdl', 'LCCN', 'lccn', 'OCLC', 'oclc',
            'ORCID', 'orcid', 'ISNI', 'isni', 'VIAF', 'viaf', 'ROR', 'ror', 'Wikidata', 'wikidata',
        ]);
        $linkFields = $this->presentFieldNames($item, [
            'URL', 'url', 'DOI', 'doi',
            'sourceFiles', 'source-files', 'sourceFile', 'source-file',
            'file', 'files', 'pdf', 'PDF',
        ]);
        $titleFields = $this->presentFieldNames($item, [
            'title', 'title-short', 'shortTitle', 'short-title',
            'container-title', 'containerTitle', 'collection-title', 'collectionTitle',
            'original-title', 'originalTitle', 'reviewed-title', 'reviewedTitle',
            'event', 'event-title', 'eventTitle',
        ]);
        $relationFields = $this->presentFieldNames($item, [
            'references', 'related', 'relatedKeys', 'related-keys', 'relatedItems', 'related-items',
            'crossref', 'crossrefKeys', 'crossref-keys', 'crossrefItems', 'crossref-items',
            'xref', 'xrefKeys', 'xref-keys', 'xdata', 'xdataKeys', 'xdata-keys',
            'entrySet', 'entry-set', 'entryset',
        ]);
        $id = $item['id'] ?? '';
        $type = $item['type'] ?? '';

        return [
            'index' => $index,
            'id' => is_scalar($id) ? trim((string) $id) : '',
            'type' => is_scalar($type) ? trim((string) $type) : '',
            'fieldCount' => count($fieldNames),
            'fieldNames' => $fieldNames,
            'titleBearing' => $titleFields !== [],
            'titleFields' => $titleFields,
            'nameVariableCount' => count($nameVariableCounts),
            'nameVariableCounts' => $nameVariableCounts,
            'nameCount' => array_sum($nameVariableCounts),
            'dateVariableCount' => count($datePartCounts),
            'datePartCounts' => $datePartCounts,
            'identifierFieldCount' => count($identifierFields),
            'identifierFields' => $identifierFields,
            'linkBearing' => $linkFields !== [],
            'linkFields' => $linkFields,
            'relationFieldCount' => count($relationFields),
            'relationFields' => $relationFields,
            'payloadExposurePolicy' => 'source-values-omitted',
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, int>
     */
    private function cslJsonNameVariableCounts(array $item): array
    {
        $counts = [];
        foreach ([
            'author',
            'editor',
            'short-author',
            'short-editor',
            'holder',
            'translator',
            'chair',
            'container-author',
            'original-author',
            'recipient',
            'reviewed-author',
            'event-organizer',
            'organizer',
            'interviewer',
            'compiler',
            'composer',
            'contributor',
            'producer',
            'performer',
            'narrator',
            'host',
            'guest',
            'executive-producer',
            'script-writer',
            'director',
            'editorial-director',
            'illustrator',
            'curator',
            'collection-editor',
            'series-creator',
            'editor-translator',
            'editortranslator',
            'redactor',
            'commentator',
            'annotator',
            'founder',
            'continuator',
            'reviser',
            'collaborator',
            'introduction',
            'foreword',
            'afterword',
            'authority',
            'namea',
            'nameb',
            'namec',
        ] as $variable) {
            if (!array_key_exists($variable, $item) || !$this->fieldHasValue($item[$variable])) {
                continue;
            }

            $counts[$variable] = $this->cslJsonListValueCount($item[$variable]);
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, int>
     */
    private function cslJsonDatePartCounts(array $item): array
    {
        $counts = [];
        foreach ([
            'issued',
            'issuedDate',
            'issued-date',
            'date',
            'accessed',
            'accessedDate',
            'accessed-date',
            'URLDate',
            'URL-date',
            'urlDate',
            'url-date',
            'original-date',
            'originalDate',
            'available-date',
            'availableDate',
            'reprint-date',
            'reprintDate',
            'submitted',
            'submitted-date',
            'event-date',
            'eventDate',
            'label-date',
            'labelDate',
        ] as $variable) {
            if (!array_key_exists($variable, $item) || !$this->fieldHasValue($item[$variable])) {
                continue;
            }

            $counts[$variable] = $this->cslJsonDatePartCount($item[$variable]);
        }

        ksort($counts);

        return $counts;
    }

    private function cslJsonDatePartCount(mixed $value): int
    {
        if (!is_array($value)) {
            return 1;
        }

        $dateParts = $value['date-parts'] ?? null;
        if (!is_array($dateParts) || $dateParts === []) {
            return 0;
        }

        $first = $dateParts[0] ?? null;
        if (!is_array($first)) {
            return 0;
        }

        return count($first);
    }

    private function cslJsonListValueCount(mixed $value): int
    {
        if (!is_array($value)) {
            return $this->fieldHasValue($value) ? 1 : 0;
        }

        return array_is_list($value) ? count($value) : 1;
    }

    /**
     * @param array<string, mixed> $item
     * @param list<string> $fieldNames
     * @return list<string>
     */
    private function presentFieldNames(array $item, array $fieldNames): array
    {
        $present = [];
        foreach ($fieldNames as $fieldName) {
            if (array_key_exists($fieldName, $item) && $this->fieldHasValue($item[$fieldName])) {
                $present[] = $fieldName;
            }
        }

        return $this->uniqueSortedStrings($present);
    }

    private function fieldHasValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_string($value)) {
            return trim($value) !== '';
        }
        if (is_array($value)) {
            return $value !== [];
        }

        return true;
    }

    /**
     * @param list<string> $strings
     * @return list<string>
     */
    private function uniqueSortedStrings(array $strings): array
    {
        $strings = array_values(array_unique($strings));
        sort($strings, SORT_STRING);

        return $strings;
    }

    /**
     * @param array<mixed> $decoded
     */
    private function decodedJsonIsList(array $decoded, string $json): bool
    {
        if ($decoded === []) {
            return str_starts_with(ltrim($json), '[');
        }

        return array_is_list($decoded);
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
