<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class CitationCslProcessor
{
    /** @var array<string, array<string, mixed>> */
    private array $itemsById;

    private CslStyle $style;

    /**
     * @param array<string, array<string, mixed>> $itemsById
     */
    private function __construct(array $itemsById, ?CslStyle $style = null)
    {
        $this->itemsById = $itemsById;
        $this->style = $style ?? CslStyle::default();
    }

    public static function fromJson(string $json): self
    {
        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid CSL JSON: ' . json_last_error_msg());
        }

        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw new \InvalidArgumentException('CSL JSON bibliography must be a list of item objects');
        }

        return self::fromItems($decoded);
    }

    public static function fromBibtex(string $bibtex): self
    {
        return self::fromItems(self::bibtexItems($bibtex));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function bibtexItems(string $bibtex): array
    {
        return BibtexCslParser::parse($bibtex);
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    public static function fromItems(array $items): self
    {
        $itemsById = [];
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('CSL item at index ' . $index . ' must be an object');
            }

            $normalized = self::normalizeItem($item, $index);
            $id = (string) $normalized['id'];
            if (isset($itemsById[$id])) {
                throw new \InvalidArgumentException('Duplicate CSL item id: ' . $id);
            }

            $itemsById[$id] = $normalized;
        }

        return new self($itemsById);
    }

    /**
     * @param list<string> $localeXmls
     */
    public function withCslStyle(string $styleXml, array $localeXmls = []): self
    {
        return new self($this->itemsById, CslStyle::fromXml($styleXml, $localeXmls));
    }

    /**
     * @return array{title:string, id:string, class:string, defaultLocale:string, citationLayout:array{prefix:string, suffix:string, delimiter:string}, bibliographyLayout:array{prefix:string, suffix:string, delimiter:string}, bibliographyOptions:array{hangingIndent:bool, entrySpacing:int|null, lineSpacing:int|null, secondFieldAlign:string}, citationSort:list<array{sort:string, variable?:string, macro?:string}>, bibliographySort:list<array{sort:string, variable?:string, macro?:string}>, terms:array{and:string, etAl:string, noDate:string, accessed:string}}
     */
    public function cslStyleSummary(): array
    {
        return $this->style->summary();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function item(string $id): ?array
    {
        return $this->itemsById[$id] ?? null;
    }

    /**
     * @return list<string>
     */
    public function citationIds(AstNode $node): array
    {
        $ids = [];
        $this->collectCitationIds($node, $ids);

        return $ids;
    }

    /**
     * @return list<string>
     */
    public function missingCitationIds(AstNode $node): array
    {
        $missing = [];
        foreach ($this->citationIds($node) as $id) {
            if (!isset($this->itemsById[$id]) && !in_array($id, $missing, true)) {
                $missing[] = $id;
            }
        }

        return $missing;
    }

    public function normalizeCitation(AstNode $citation): AstNode
    {
        if ($citation->type !== 'citation') {
            throw new \InvalidArgumentException('Expected citation AST node');
        }

        $id = (string) $citation->attr('id', '');
        $item = $this->itemsById[$id] ?? null;
        if ($item === null) {
            return new AstNode(
                'citation',
                [
                    ...$citation->attrs,
                    'rendered' => $this->sourceCitationText($citation),
                    'missingCslItem' => true,
                ],
                $citation->children
            );
        }

        return new AstNode(
            'citation',
            [
                ...$citation->attrs,
                'rendered' => $this->renderCitationCluster([$citation]),
                'cslLabel' => $this->citationAuthorLabel($item),
                'cslYear' => $this->citationYear($item),
                'cslItem' => $item,
            ],
            $citation->children
        );
    }

    public function normalizeCitationGroup(AstNode $group): AstNode
    {
        if ($group->type !== 'citation_group') {
            throw new \InvalidArgumentException('Expected citation_group AST node');
        }

        $citations = [];
        $missing = [];
        foreach ($group->children as $child) {
            if ($child->type !== 'citation') {
                throw new \InvalidArgumentException('Citation group entries must be citation AST nodes');
            }

            $citations[] = $child;
            $id = (string) $child->attr('id', '');
            if ($id !== '' && !isset($this->itemsById[$id]) && !in_array($id, $missing, true)) {
                $missing[] = $id;
            }
        }

        $attrs = [
            ...$group->attrs,
            'rendered' => $this->renderCitationCluster($citations),
        ];
        if ($missing !== []) {
            $attrs['missingCslItems'] = $missing;
        }

        return new AstNode(
            'citation_group',
            $attrs,
            array_map(fn (AstNode $citation): AstNode => $this->normalizeCitation($citation), $citations)
        );
    }

    public function apply(AstNode $document): AstNode
    {
        return $this->mapNode($document);
    }

    public function appendBibliography(AstNode $document, string $headingText = 'References'): AstNode
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('Expected document AST node');
        }

        $processed = $this->apply($document);
        $blocks = $this->bibliographyBlocks($processed, $headingText);
        if ($blocks === []) {
            return $processed;
        }

        return new AstNode('document', $processed->attrs, [
            ...$processed->children,
            ...$blocks,
        ]);
    }

    /**
     * @return list<AstNode>
     */
    public function bibliographyBlocks(AstNode $document, string $headingText = 'References'): array
    {
        $ids = $this->uniqueKnownCitationIds($document);
        $ids = $this->sortBibliographyIds($ids);
        if ($ids === []) {
            return [];
        }

        return [
            new AstNode('heading', [
                'level' => 2,
                'id' => $this->slugify($headingText),
                'text' => $headingText,
            ], [
                new AstNode('text', ['text' => $headingText]),
            ]),
            $this->bibliographyDefinitionList($ids),
        ];
    }

    /**
     * @param list<AstNode> $citations
     */
    public function renderCitationCluster(array $citations): string
    {
        $citations = $this->sortCitationCluster($citations);
        $entries = [];
        foreach ($citations as $citation) {
            if (!$citation instanceof AstNode || $citation->type !== 'citation') {
                throw new \InvalidArgumentException('Citation cluster entries must be citation AST nodes');
            }

            $entries[] = $this->renderCitationEntry($citation);
        }

        if ($entries === []) {
            return '';
        }

        if (count($entries) === 1 && ((string) $citations[0]->attr('mode', 'normal')) === 'author_in_text') {
            return $entries[0];
        }

        return $this->style->citationPrefix()
            . implode($this->style->citationDelimiter(), $entries)
            . $this->style->citationSuffix();
    }

    public function renderBibliographyEntry(string $id): string
    {
        $item = $this->itemsById[$id] ?? null;
        if ($item === null) {
            throw new \InvalidArgumentException('Unknown CSL item id: ' . $id);
        }

        $parts = [];
        $authors = $this->bibliographyAuthors($item);
        if ($authors !== '') {
            $parts[] = rtrim($authors, '.') . '.';
        }

        $title = (string) $item['title'];
        if ($title !== '') {
            $parts[] = $title . '.';
        }

        $container = (string) $item['containerTitle'];
        if ($container !== '') {
            $parts[] = $container . '.';
        }

        $publisher = (string) $item['publisher'];
        $year = $this->citationYear($item);
        $hasDate = $this->hasIssuedDate($item);
        if ($publisher !== '' && $hasDate) {
            $parts[] = $publisher . ', ' . $year . '.';
        } elseif ($publisher !== '') {
            $parts[] = $publisher . '.';
        } elseif ($hasDate) {
            $parts[] = $year . '.';
        }

        $page = (string) $item['page'];
        if ($page !== '') {
            $parts[] = $page . '.';
        }

        $doi = (string) $item['doi'];
        if ($doi !== '') {
            $parts[] = 'DOI ' . $doi . '.';
        }

        $url = (string) $item['url'];
        if ($url !== '') {
            $parts[] = $url . '.';
        }

        $accessedDate = $item['accessedDate'] ?? null;
        if (is_array($accessedDate) && (string) ($accessedDate['display'] ?? '') !== '') {
            $parts[] = $this->style->term('accessed') . ' ' . (string) $accessedDate['display'] . '.';
        }

        return $this->style->formatBibliographyEntry(implode($this->style->bibliographyDelimiter(), $parts));
    }

    /**
     * @param list<string> $ids
     */
    public function bibliographyDefinitionList(array $ids): AstNode
    {
        $items = [];
        foreach ($ids as $id) {
            $item = $this->itemsById[$id] ?? null;
            if ($item === null) {
                continue;
            }

            $label = $this->citationLabel($item);
            $entry = $this->renderBibliographyEntry($id);
            $items[] = new AstNode('definition_item', ['term' => $label, 'cslId' => $id], [
                new AstNode('term', ['text' => $label], [
                    new AstNode('text', ['text' => $label]),
                ]),
                new AstNode('definition', [], [
                    new AstNode('paragraph', [], [
                        new AstNode('text', ['text' => $entry]),
                    ]),
                ]),
            ]);
        }

        $options = $this->style->bibliographyOptions();
        $attrs = [
            'classes' => ['pandoc-csl-bibliography'],
        ];
        if ($options['hangingIndent']) {
            $attrs['hangingIndent'] = true;
        }
        if ($options['entrySpacing'] !== null) {
            $attrs['entrySpacing'] = $options['entrySpacing'];
        }
        if ($options['lineSpacing'] !== null) {
            $attrs['lineSpacing'] = $options['lineSpacing'];
        }
        if ($options['secondFieldAlign'] !== '') {
            $attrs['secondFieldAlign'] = $options['secondFieldAlign'];
        }

        return new AstNode('definition_list', $attrs, $items);
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private static function normalizeItem(array $item, int $index): array
    {
        $id = $item['id'] ?? null;
        if (!is_string($id) && !is_int($id)) {
            throw new \InvalidArgumentException('CSL item at index ' . $index . ' is missing string id');
        }

        $id = trim((string) $id);
        if ($id === '') {
            throw new \InvalidArgumentException('CSL item at index ' . $index . ' has an empty id');
        }

        $issuedDate = self::dateVariable($item['issued'] ?? null, $id, 'issued');

        return [
            'id' => $id,
            'type' => self::stringField($item, 'type'),
            'title' => self::stringField($item, 'title'),
            'containerTitle' => self::stringField($item, 'container-title'),
            'publisher' => self::stringField($item, 'publisher'),
            'page' => self::stringField($item, 'page'),
            'doi' => self::stringField($item, 'DOI'),
            'url' => self::stringField($item, 'URL'),
            'language' => self::stringField($item, 'language'),
            'abstract' => self::stringField($item, 'abstract'),
            'keywords' => self::stringListField($item, 'keyword'),
            'sourceFiles' => self::sourceFiles($item['sourceFiles'] ?? [], $id),
            'issuedDate' => $issuedDate,
            'accessedDate' => self::dateVariable($item['accessed'] ?? null, $id, 'accessed'),
            'issuedYear' => $issuedDate['year'],
            'authors' => self::names($item['author'] ?? [], $id, 'author'),
            'editors' => self::names($item['editor'] ?? [], $id, 'editor'),
            'raw' => $item,
        ];
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function stringField(array $item, string $key): string
    {
        $value = $item[$key] ?? '';
        if ($value === null) {
            return '';
        }

        if (!is_scalar($value)) {
            throw new \InvalidArgumentException('CSL field ' . $key . ' must be scalar when present');
        }

        return trim((string) $value);
    }

    /**
     * @return list<string>
     */
    private static function stringListField(array $item, string $key): array
    {
        $value = $item[$key] ?? [];
        if ($value === null || $value === '') {
            return [];
        }

        if (is_scalar($value)) {
            $parts = preg_split('/\s*[,;]\s*/', (string) $value) ?: [];

            return array_values(array_filter(
                array_map(static fn (string $part): string => trim($part), $parts),
                static fn (string $part): bool => $part !== ''
            ));
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('CSL field ' . $key . ' must be scalar or a list when present');
        }

        $strings = [];
        foreach ($value as $index => $part) {
            if (!is_scalar($part)) {
                throw new \InvalidArgumentException('CSL field ' . $key . '[' . $index . '] must be scalar');
            }

            $part = trim((string) $part);
            if ($part !== '') {
                $strings[] = $part;
            }
        }

        return $strings;
    }

    /**
     * @return list<array{label:string, path:string, mediaType:string}>
     */
    private static function sourceFiles(mixed $value, string $id): array
    {
        if ($value === null || $value === []) {
            return [];
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' sourceFiles must be a list');
        }

        $files = [];
        foreach ($value as $index => $file) {
            if (is_scalar($file)) {
                $path = trim((string) $file);
                if ($path !== '') {
                    $files[] = [
                        'label' => '',
                        'path' => $path,
                        'mediaType' => '',
                    ];
                }
                continue;
            }

            if (!is_array($file)) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' sourceFiles[' . $index . '] must be an object or path string');
            }

            $path = self::sourceFileString($file['path'] ?? '', $id, $index, 'path');
            if ($path === '') {
                throw new \InvalidArgumentException('CSL item ' . $id . ' sourceFiles[' . $index . '] is missing path');
            }

            $files[] = [
                'label' => self::sourceFileString($file['label'] ?? '', $id, $index, 'label'),
                'path' => $path,
                'mediaType' => self::sourceFileString($file['mediaType'] ?? '', $id, $index, 'mediaType'),
            ];
        }

        return $files;
    }

    private static function sourceFileString(mixed $value, string $id, int $index, string $field): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (!is_scalar($value)) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' sourceFiles[' . $index . '].' . $field . ' must be scalar');
        }

        return trim((string) $value);
    }

    /**
     * @return list<array{family:string, given:string, literal:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool}>
     */
    private static function names(mixed $value, string $id, string $field): array
    {
        if ($value === null || $value === []) {
            return [];
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' field ' . $field . ' must be a list of names');
        }

        $names = [];
        foreach ($value as $index => $name) {
            if (!is_array($name)) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' field ' . $field . '[' . $index . '] must be an object');
            }

            $literal = self::nameString($name['literal'] ?? '');
            $family = self::nameString($name['family'] ?? '');
            $given = self::nameString($name['given'] ?? '');
            $nonDroppingParticle = self::nameString($name['non-dropping-particle'] ?? '');
            $droppingParticle = self::nameString($name['dropping-particle'] ?? '');
            $suffix = self::nameString($name['suffix'] ?? '');
            if ($literal === '' && $family === '' && $given === '' && $nonDroppingParticle === '' && $droppingParticle === '') {
                throw new \InvalidArgumentException('CSL item ' . $id . ' field ' . $field . '[' . $index . '] has no name content');
            }

            $names[] = [
                'family' => $family,
                'given' => $given,
                'literal' => $literal,
                'nonDroppingParticle' => $nonDroppingParticle,
                'droppingParticle' => $droppingParticle,
                'suffix' => $suffix,
                'commaSuffix' => self::boolField($name, 'comma-suffix', false),
                'staticOrdering' => self::boolField($name, 'static-ordering', false),
                'parseNames' => self::boolField($name, 'parse-names', true),
            ];
        }

        return $names;
    }

    private static function nameString(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (!is_scalar($value)) {
            throw new \InvalidArgumentException('CSL name fields must be scalar');
        }

        return trim((string) $value);
    }

    /**
     * @return array{year:?int, parts:list<int>, display:string, literal:string}
     */
    private static function dateVariable(mixed $date, string $id, string $field): array
    {
        if ($date === null || $date === []) {
            return [
                'year' => null,
                'parts' => [],
                'display' => '',
                'literal' => '',
            ];
        }

        if (!is_array($date)) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . ' field must be an object');
        }

        $literal = self::stringField($date, 'literal');
        $dateParts = $date['date-parts'] ?? null;
        if ($dateParts === null || $dateParts === []) {
            return [
                'year' => null,
                'parts' => [],
                'display' => $literal,
                'literal' => $literal,
            ];
        }

        if (!is_array($dateParts) || !isset($dateParts[0]) || !is_array($dateParts[0]) || !isset($dateParts[0][0])) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . ' date-parts must contain a year');
        }

        $parts = [];
        foreach (array_slice($dateParts[0], 0, 3) as $partIndex => $part) {
            if (!is_int($part) && !(is_string($part) && preg_match('/^-?\d+$/', $part) === 1)) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . ' date-parts must be numeric');
            }

            $number = (int) $part;
            if ($partIndex === 1 && ($number < 1 || $number > 12)) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . ' month must be between 1 and 12');
            }

            if ($partIndex === 2 && ($number < 1 || $number > 31)) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . ' day must be between 1 and 31');
            }

            $parts[] = $number;
        }

        return [
            'year' => $parts[0],
            'parts' => $parts,
            'display' => self::formatDateParts($parts),
            'literal' => '',
        ];
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function boolField(array $item, string $key, bool $default): bool
    {
        if (!array_key_exists($key, $item) || $item[$key] === null) {
            return $default;
        }

        if (!is_bool($item[$key])) {
            throw new \InvalidArgumentException('CSL field ' . $key . ' must be boolean when present');
        }

        return $item[$key];
    }

    /**
     * @param list<int> $parts
     */
    private static function formatDateParts(array $parts): string
    {
        if (count($parts) >= 3) {
            return sprintf('%04d-%02d-%02d', $parts[0], $parts[1], $parts[2]);
        }

        if (count($parts) === 2) {
            return sprintf('%04d-%02d', $parts[0], $parts[1]);
        }

        return (string) $parts[0];
    }

    private function mapNode(AstNode $node): AstNode
    {
        if ($node->type === 'citation') {
            return $this->normalizeCitation($node);
        }

        if ($node->type === 'citation_group') {
            return $this->normalizeCitationGroup($node);
        }

        if ($node->children === []) {
            return $node;
        }

        $children = [];
        $changed = false;
        foreach ($node->children as $child) {
            $mapped = $this->mapNode($child);
            $children[] = $mapped;
            $changed = $changed || $mapped !== $child;
        }

        return $changed ? new AstNode($node->type, $node->attrs, $children) : $node;
    }

    /**
     * @param list<string> $ids
     */
    private function collectCitationIds(AstNode $node, array &$ids): void
    {
        if ($node->type === 'citation') {
            $id = (string) $node->attr('id', '');
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        foreach ($node->children as $child) {
            $this->collectCitationIds($child, $ids);
        }
    }

    /**
     * @return list<string>
     */
    private function uniqueKnownCitationIds(AstNode $document): array
    {
        $ids = [];
        foreach ($this->citationIds($document) as $id) {
            if (isset($this->itemsById[$id]) && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @param list<string> $ids
     * @return list<string>
     */
    private function sortBibliographyIds(array $ids): array
    {
        $sortKeys = $this->style->bibliographySortKeys();
        if ($sortKeys === [] || count($ids) < 2) {
            return $ids;
        }

        $entries = [];
        foreach ($ids as $index => $id) {
            $entries[] = [
                'index' => $index,
                'id' => $id,
                'item' => $this->itemsById[$id] ?? null,
                'fallback' => $id,
            ];
        }

        usort($entries, fn (array $left, array $right): int => $this->compareSortEntries($left, $right, $sortKeys));

        return array_map(static fn (array $entry): string => (string) $entry['id'], $entries);
    }

    /**
     * @param list<AstNode> $citations
     * @return list<AstNode>
     */
    private function sortCitationCluster(array $citations): array
    {
        $sortKeys = $this->style->citationSortKeys();
        if ($sortKeys === [] || count($citations) < 2) {
            return $citations;
        }

        $entries = [];
        foreach ($citations as $index => $citation) {
            if (!$citation instanceof AstNode || $citation->type !== 'citation') {
                return $citations;
            }

            $id = (string) $citation->attr('id', '');
            $entries[] = [
                'index' => $index,
                'node' => $citation,
                'item' => $this->itemsById[$id] ?? null,
                'fallback' => $this->sourceCitationText($citation),
            ];
        }

        usort($entries, fn (array $left, array $right): int => $this->compareSortEntries($left, $right, $sortKeys));

        return array_map(static fn (array $entry): AstNode => $entry['node'], $entries);
    }

    /**
     * @param array{index:int, item:array<string, mixed>|null, fallback:string} $left
     * @param array{index:int, item:array<string, mixed>|null, fallback:string} $right
     * @param list<array{sort:string, variable?:string, macro?:string}> $sortKeys
     */
    private function compareSortEntries(array $left, array $right, array $sortKeys): int
    {
        $leftItem = $left['item'];
        $rightItem = $right['item'];
        if ($leftItem === null || $rightItem === null) {
            if ($leftItem === null && $rightItem === null) {
                return $left['index'] <=> $right['index'];
            }

            return $leftItem === null ? 1 : -1;
        }

        foreach ($sortKeys as $key) {
            $leftValue = $this->sortValue($leftItem, $key, (string) $left['fallback']);
            $rightValue = $this->sortValue($rightItem, $key, (string) $right['fallback']);
            $comparison = $leftValue <=> $rightValue;
            if ($comparison !== 0) {
                return ($key['sort'] ?? 'ascending') === 'descending' ? -$comparison : $comparison;
            }
        }

        return $left['index'] <=> $right['index'];
    }

    /**
     * @param array<string, mixed> $item
     * @param array{sort:string, variable?:string, macro?:string} $key
     */
    private function sortValue(array $item, array $key, string $fallback): string
    {
        $variable = $this->sortVariable($key);

        return match ($variable) {
            'author' => $this->normalizeSortText($this->namesSortValue($item['authors'] ?? [], $item['editors'] ?? [])),
            'editor' => $this->normalizeSortText($this->namesSortValue($item['editors'] ?? [], [])),
            'issued', 'date' => $this->issuedSortValue($item),
            'title' => $this->normalizeSortText((string) $item['title']),
            'container-title' => $this->normalizeSortText((string) $item['containerTitle']),
            'publisher' => $this->normalizeSortText((string) $item['publisher']),
            'type' => $this->normalizeSortText((string) $item['type']),
            'citation-number' => '',
            'id' => $this->normalizeSortText((string) $item['id']),
            default => $this->normalizeSortText($fallback),
        };
    }

    /**
     * @param array{sort:string, variable?:string, macro?:string} $key
     */
    private function sortVariable(array $key): string
    {
        $variable = strtolower(trim((string) ($key['variable'] ?? '')));
        if ($variable !== '') {
            return $variable;
        }

        $macro = strtolower(trim((string) ($key['macro'] ?? '')));
        if (str_contains($macro, 'author') || str_contains($macro, 'creator') || str_contains($macro, 'name')) {
            return 'author';
        }
        if (str_contains($macro, 'date') || str_contains($macro, 'issued') || str_contains($macro, 'year')) {
            return 'issued';
        }
        if (str_contains($macro, 'title')) {
            return 'title';
        }

        return 'id';
    }

    /**
     * @param mixed $primary
     * @param mixed $fallback
     */
    private function namesSortValue(mixed $primary, mixed $fallback): string
    {
        $names = is_array($primary) && $primary !== [] ? $primary : $fallback;
        if (!is_array($names) || $names === []) {
            return '';
        }

        $parts = [];
        foreach ($names as $name) {
            if (!is_array($name)) {
                continue;
            }

            if ((string) ($name['literal'] ?? '') !== '') {
                $parts[] = (string) $name['literal'];
                continue;
            }

            $parts[] = trim(implode(' ', array_filter([
                (string) ($name['nonDroppingParticle'] ?? ''),
                (string) ($name['family'] ?? ''),
                (string) ($name['given'] ?? ''),
                (string) ($name['droppingParticle'] ?? ''),
                (string) ($name['suffix'] ?? ''),
            ], static fn (string $part): bool => $part !== '')));
        }

        return implode(' ', $parts);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function issuedSortValue(array $item): string
    {
        $date = $item['issuedDate'] ?? null;
        $parts = is_array($date) && isset($date['parts']) && is_array($date['parts']) ? $date['parts'] : [];
        if ($parts !== []) {
            return sprintf('%+08d-%02d-%02d', (int) ($parts[0] ?? 0), (int) ($parts[1] ?? 0), (int) ($parts[2] ?? 0));
        }

        return $this->normalizeSortText(is_array($date) ? (string) ($date['display'] ?? $date['literal'] ?? '') : '');
    }

    private function normalizeSortText(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return strtolower($value);
    }

    private function renderCitationEntry(AstNode $citation): string
    {
        $id = (string) $citation->attr('id', '');
        $item = $this->itemsById[$id] ?? null;
        if ($item === null) {
            return $this->sourceCitationText($citation);
        }

        $mode = (string) $citation->attr('mode', 'normal');
        $year = $this->citationYear($item);
        $author = $this->citationAuthorLabel($item);
        $suffix = $this->citationSuffix($citation);
        $prefix = $this->citationPrefix($citation);

        if ($mode === 'author_in_text') {
            $entry = $author . ' (' . $year . ($suffix === '' ? '' : ', ' . $suffix) . ')';
        } elseif ($mode === 'suppress_author') {
            $entry = $year . ($suffix === '' ? '' : ', ' . $suffix);
        } else {
            $entry = $author . ' ' . $year . ($suffix === '' ? '' : ', ' . $suffix);
        }

        return $prefix === '' ? $entry : $prefix . ' ' . $entry;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function citationLabel(array $item): string
    {
        return $this->citationAuthorLabel($item) . ' ' . $this->citationYear($item);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function citationAuthorLabel(array $item): string
    {
        $names = $item['authors'];
        if ($names === []) {
            $names = $item['editors'];
        }

        if (!is_array($names) || $names === []) {
            $title = (string) $item['title'];

            return $title === '' ? (string) $item['id'] : $title;
        }

        $families = array_map(static function (array $name): string {
            if ($name['literal'] !== '') {
                return $name['literal'];
            }

            $family = trim((string) $name['nonDroppingParticle'] . ' ' . (string) $name['family']);
            if ($family !== '') {
                return $family;
            }

            return $name['given'];
        }, $names);

        if (count($families) === 1) {
            return $families[0];
        }

        if (count($families) === 2) {
            return $families[0] . ' ' . $this->style->term('and') . ' ' . $families[1];
        }

        return $families[0] . ' ' . $this->style->term('et-al');
    }

    /**
     * @param array<string, mixed> $item
     */
    private function citationYear(array $item): string
    {
        if (isset($item['issuedYear']) && $item['issuedYear'] !== null) {
            return (string) $item['issuedYear'];
        }

        $date = $item['issuedDate'] ?? null;
        if (is_array($date) && (string) ($date['literal'] ?? '') !== '') {
            return (string) $date['literal'];
        }

        return $this->style->term('no date');
    }

    /**
     * @param array<string, mixed> $item
     */
    private function hasIssuedDate(array $item): bool
    {
        if (isset($item['issuedYear']) && $item['issuedYear'] !== null) {
            return true;
        }

        $date = $item['issuedDate'] ?? null;

        return is_array($date) && (string) ($date['literal'] ?? '') !== '';
    }

    /**
     * @param array<string, mixed> $item
     */
    private function bibliographyAuthors(array $item): string
    {
        $names = $item['authors'];
        if ($names === []) {
            $names = $item['editors'];
        }

        if (!is_array($names) || $names === []) {
            return '';
        }

        $parts = [];
        foreach ($names as $name) {
            if ($name['literal'] !== '') {
                $parts[] = $name['literal'];
                continue;
            }

            $family = trim((string) $name['nonDroppingParticle'] . ' ' . (string) $name['family']);
            $given = (string) $name['given'];
            $droppingParticle = (string) $name['droppingParticle'];
            $suffix = (string) $name['suffix'];
            if ($family !== '' && $given !== '') {
                $entry = $family . ', ' . $given;
            } else {
                $entry = $family !== '' ? $family : $given;
            }

            if ($droppingParticle !== '') {
                $entry = trim($entry . ' ' . $droppingParticle);
            }

            if ($suffix !== '') {
                $entry .= ($name['commaSuffix'] ? ', ' : ' ') . $suffix;
            }

            $parts[] = $entry;
        }

        return implode('; ', $parts);
    }

    private function citationPrefix(AstNode $citation): string
    {
        return $this->inlineValue($citation->attr('prefix', ''));
    }

    private function citationSuffix(AstNode $citation): string
    {
        $suffix = $citation->attr('suffix', null);
        $locator = $citation->attr('locator', '');
        $renderedSuffix = $this->inlineValue($suffix);
        $renderedLocator = $this->inlineValue($locator);

        if ($renderedLocator !== '' && $renderedSuffix !== '') {
            return $renderedLocator . ' ' . $renderedSuffix;
        }

        return $renderedSuffix !== '' ? $renderedSuffix : $renderedLocator;
    }

    private function inlineValue(mixed $value): string
    {
        if (is_string($value) || is_int($value) || is_float($value)) {
            return trim((string) $value);
        }

        if ($value instanceof AstNode) {
            return $this->plainInlineText([$value]);
        }

        if (is_array($value)) {
            return $this->plainInlineText($value);
        }

        return '';
    }

    /**
     * @param list<mixed> $nodes
     */
    private function plainInlineText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            if (!$node instanceof AstNode) {
                $text .= is_scalar($node) ? (string) $node : '';
                continue;
            }

            $text .= match ($node->type) {
                'text', 'code' => (string) $node->attr('text', ''),
                'space', 'softbreak', 'linebreak' => ' ',
                default => $this->plainInlineText($node->children),
            };
        }

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    private function sourceCitationText(AstNode $citation): string
    {
        $text = (string) $citation->attr('text', '');
        if ($text !== '') {
            return $text;
        }

        return $this->plainInlineText($citation->children);
    }

    private function slugify(string $text): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $text) ?? $text, '-'));

        return $slug === '' ? 'references' : $slug;
    }
}
