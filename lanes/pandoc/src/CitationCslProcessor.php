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
     * @return array{title:string, id:string, class:string, defaultLocale:string, citationLayout:array{prefix:string, suffix:string, delimiter:string}, bibliographyLayout:array{prefix:string, suffix:string, delimiter:string}, bibliographyOptions:array{hangingIndent:bool, entrySpacing:int|null, lineSpacing:int|null, secondFieldAlign:string}, citationSort:list<array{sort:string, variable?:string, macro?:string}>, bibliographySort:list<array{sort:string, variable?:string, macro?:string}>, citationRendering:list<array<string, mixed>>, bibliographyRendering:list<array<string, mixed>>, macros:array<string, list<array<string, mixed>>>, terms:array{and:string, etAl:string, noDate:string, accessed:string}}
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

        $customEntry = $this->renderCustomBibliographyEntry($item);
        if ($customEntry !== null) {
            return $customEntry;
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

        $translators = $this->bibliographyTranslators($item);
        if ($translators !== '') {
            $parts[] = 'Translated by ' . rtrim($translators, '.') . '.';
        }

        $originalTitle = (string) $item['originalTitle'];
        if ($originalTitle !== '') {
            $parts[] = 'Original title: ' . $originalTitle . '.';
        }

        $originalDate = $item['originalDate'] ?? null;
        if (is_array($originalDate) && (string) ($originalDate['display'] ?? '') !== '') {
            $parts[] = 'Original work published ' . (string) $originalDate['display'] . '.';
        }

        $originalPublisher = (string) $item['originalPublisher'];
        $originalPublisherPlace = (string) $item['originalPublisherPlace'];
        if ($originalPublisher !== '' || $originalPublisherPlace !== '') {
            $parts[] = 'Original publisher: ' . trim($originalPublisher . ($originalPublisher !== '' && $originalPublisherPlace !== '' ? ', ' : '') . $originalPublisherPlace) . '.';
        }

        $originalLanguage = (string) $item['originalLanguage'];
        if ($originalLanguage !== '') {
            $parts[] = 'Original language: ' . $originalLanguage . '.';
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
        $sourceFilePolicy = self::sourceFilesWithDiagnostics($item['sourceFiles'] ?? [], $id);
        $sourceFileDiagnostics = [
            ...$sourceFilePolicy['diagnostics'],
            ...self::sourceFileDiagnostics($item['sourceFileDiagnostics'] ?? [], $id),
        ];

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
            'sourceFiles' => $sourceFilePolicy['files'],
            'sourceFileDiagnostics' => $sourceFileDiagnostics,
            'issuedDate' => $issuedDate,
            'accessedDate' => self::dateVariable($item['accessed'] ?? null, $id, 'accessed'),
            'originalTitle' => self::stringField($item, 'original-title'),
            'originalPublisher' => self::stringField($item, 'original-publisher'),
            'originalPublisherPlace' => self::stringField($item, 'original-publisher-place'),
            'originalLanguage' => self::stringField($item, 'original-language'),
            'originalDate' => self::dateVariable($item['original-date'] ?? null, $id, 'original-date'),
            'issuedYear' => $issuedDate['year'],
            'authors' => self::names($item['author'] ?? [], $id, 'author'),
            'editors' => self::names($item['editor'] ?? [], $id, 'editor'),
            'translators' => self::names($item['translator'] ?? [], $id, 'translator'),
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
     * @return array{files:list<array{label:string, path:string, mediaType:string}>, diagnostics:list<array{label:string, path:string, mediaType:string, reason:string, importable:bool}>}
     */
    private static function sourceFilesWithDiagnostics(mixed $value, string $id): array
    {
        if ($value === null || $value === []) {
            return ['files' => [], 'diagnostics' => []];
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' sourceFiles must be a list');
        }

        $files = [];
        $diagnostics = [];
        foreach ($value as $index => $file) {
            if (is_scalar($file)) {
                $path = trim((string) $file);
                if ($path !== '') {
                    $policy = self::sourceFilePathPolicy($path);
                    if ($policy['reason'] === '') {
                        $files[] = [
                            'label' => '',
                            'path' => $policy['path'],
                            'mediaType' => '',
                        ];
                    } else {
                        $diagnostics[] = self::sourceFilePolicyDiagnostic('', $path, '', $policy['reason']);
                    }
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

            $label = self::sourceFileString($file['label'] ?? '', $id, $index, 'label');
            $mediaType = self::sourceFileString($file['mediaType'] ?? '', $id, $index, 'mediaType');
            $policy = self::sourceFilePathPolicy($path);
            if ($policy['reason'] === '') {
                $files[] = [
                    'label' => $label,
                    'path' => $policy['path'],
                    'mediaType' => $mediaType,
                ];
            } else {
                $diagnostics[] = self::sourceFilePolicyDiagnostic($label, $path, $mediaType, $policy['reason']);
            }
        }

        return ['files' => $files, 'diagnostics' => $diagnostics];
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
     * @return list<array{label:string, path:string, mediaType:string, reason:string, importable:bool}>
     */
    private static function sourceFileDiagnostics(mixed $value, string $id): array
    {
        if ($value === null || $value === []) {
            return [];
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' sourceFileDiagnostics must be a list');
        }

        $diagnostics = [];
        foreach ($value as $index => $diagnostic) {
            if (!is_array($diagnostic)) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' sourceFileDiagnostics[' . $index . '] must be an object');
            }

            $reason = self::sourceFileString($diagnostic['reason'] ?? '', $id, $index, 'reason');
            if ($reason === '') {
                throw new \InvalidArgumentException('CSL item ' . $id . ' sourceFileDiagnostics[' . $index . '] is missing reason');
            }

            $diagnostics[] = [
                'label' => self::sourceFileString($diagnostic['label'] ?? '', $id, $index, 'label'),
                'path' => self::sourceFileString($diagnostic['path'] ?? '', $id, $index, 'path'),
                'mediaType' => self::sourceFileString($diagnostic['mediaType'] ?? '', $id, $index, 'mediaType'),
                'reason' => $reason,
                'importable' => self::boolField($diagnostic, 'importable', false),
            ];
        }

        return $diagnostics;
    }

    /**
     * @return array{path:string, reason:string}
     */
    private static function sourceFilePathPolicy(string $path): array
    {
        if ($path === '') {
            return ['path' => '', 'reason' => 'missing-path'];
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            return ['path' => $path, 'reason' => 'control-character'];
        }

        if (preg_match('/^[A-Za-z]:/', $path) === 1) {
            return ['path' => $path, 'reason' => 'windows-drive-path'];
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $path) === 1) {
            return ['path' => $path, 'reason' => 'remote-uri'];
        }

        if (str_starts_with($path, '//')) {
            return ['path' => $path, 'reason' => 'uri-authority-path'];
        }

        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return ['path' => $path, 'reason' => 'absolute-path'];
        }

        if (str_contains($path, '\\')) {
            return ['path' => $path, 'reason' => 'backslash-separator'];
        }

        if (preg_match('/%(?![0-9A-Fa-f]{2})/', $path) === 1) {
            return ['path' => $path, 'reason' => 'malformed-percent-escape'];
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            $decoded = rawurldecode($segment);
            if (preg_match('/[\x00-\x1F\x7F]/', $decoded) === 1 || str_contains($decoded, '/') || str_contains($decoded, '\\')) {
                return ['path' => $path, 'reason' => 'unsafe-percent-encoded-path-byte'];
            }

            if ($decoded === '..') {
                return ['path' => $path, 'reason' => 'path-traversal'];
            }

            $segments[] = $decoded;
        }

        if ($segments === []) {
            return ['path' => $path, 'reason' => 'missing-path'];
        }

        return ['path' => implode('/', $segments), 'reason' => ''];
    }

    /**
     * @return array{label:string, path:string, mediaType:string, reason:string, importable:bool}
     */
    private static function sourceFilePolicyDiagnostic(string $label, string $path, string $mediaType, string $reason): array
    {
        return [
            'label' => $label,
            'path' => $path,
            'mediaType' => $mediaType,
            'reason' => $reason,
            'importable' => false,
        ];
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

        $customEntry = $this->renderCustomCitationEntry($citation, $item);
        if ($customEntry !== null) {
            return $customEntry;
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
    private function renderCustomBibliographyEntry(array $item): ?string
    {
        $elements = $this->style->bibliographyRenderingElements();
        if (!$this->hasNonNameRenderingElement($elements)) {
            return null;
        }

        return $this->style->formatBibliographyEntry(
            $this->renderRenderingElements($elements, $item, 'bibliography', $this->style->bibliographyDelimiter())
        );
    }

    /**
     * @param array<string, mixed> $item
     */
    private function renderCustomCitationEntry(AstNode $citation, array $item): ?string
    {
        if ((string) $citation->attr('mode', 'normal') !== 'normal') {
            return null;
        }

        $elements = $this->style->citationRenderingElements();
        if (!$this->hasNonNameRenderingElement($elements)) {
            return null;
        }

        $entry = $this->renderRenderingElements($elements, $item, 'citation', '');
        if ($entry === '') {
            return null;
        }

        $suffix = $this->citationSuffix($citation);
        if ($suffix !== '') {
            $entry .= ', ' . $suffix;
        }

        $prefix = $this->citationPrefix($citation);

        return $prefix === '' ? $entry : $prefix . ' ' . $entry;
    }

    /**
     * @param list<array<string, mixed>> $elements
     */
    private function hasNonNameRenderingElement(array $elements): bool
    {
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            $type = (string) ($element['type'] ?? '');
            if ($type === 'group') {
                if (isset($element['children']) && is_array($element['children']) && $this->hasNonNameRenderingElement($element['children'])) {
                    return true;
                }

                continue;
            }

            if ($type !== 'names') {
                return true;
            }
        }

        return false;
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

        return $this->renderNameList($names, $this->style->citationNameRendering(), false);
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

        return $this->renderNameList($names, $this->style->bibliographyNameRendering(), true);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function bibliographyTranslators(array $item): string
    {
        $names = $item['translators'] ?? [];
        if (!is_array($names) || $names === []) {
            return '';
        }

        return $this->renderNameList($names, $this->style->bibliographyNameRendering(), true);
    }

    /**
     * @param list<array<string, mixed>> $elements
     * @param array<string, mixed> $item
     */
    private function renderRenderingElements(array $elements, array $item, string $scope, string $delimiter): string
    {
        $rendered = [];
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            $value = $this->renderRenderingElement($element, $item, $scope);
            if ($value !== '') {
                $rendered[] = $value;
            }
        }

        return $this->joinRenderedElements($rendered, $delimiter);
    }

    /**
     * @param list<string> $rendered
     */
    private function joinRenderedElements(array $rendered, string $delimiter): string
    {
        if ($rendered === []) {
            return '';
        }

        if ($delimiter === '') {
            return implode('', $rendered);
        }

        $output = array_shift($rendered);
        foreach ($rendered as $value) {
            $separator = $delimiter;
            if ($separator !== '' && $output !== '' && preg_match('/^[.,;:!?]/', $separator) === 1) {
                $punctuation = $separator[0];
                if (str_ends_with($output, $punctuation)) {
                    $rest = substr($separator, 1);
                    $separator = trim($rest) === '' && $rest !== '' ? ' ' : ltrim($rest);
                }
            }

            $output .= $separator . $value;
        }

        return $output;
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     */
    private function renderRenderingElement(array $element, array $item, string $scope, array $macroStack = []): string
    {
        $type = (string) ($element['type'] ?? '');
        $value = match ($type) {
            'group' => $this->renderGroupElement($element, $item, $scope, $macroStack),
            'text' => $this->renderTextElement($element, $item, $scope, $macroStack),
            'date' => $this->renderDateElement($element, $item, $scope),
            'names' => $this->renderNamesElement($element, $item, $scope),
            default => '',
        };

        return $this->applyRenderingAffixes($value, $element);
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     */
    private function renderGroupElement(array $element, array $item, string $scope, array $macroStack = []): string
    {
        $children = $element['children'] ?? [];
        if (!is_array($children)) {
            return '';
        }

        $rendered = [];
        $hasVariableChild = false;
        $hasRenderedVariableChild = false;
        foreach ($children as $child) {
            if (!is_array($child)) {
                continue;
            }

            $isVariableChild = $this->isVariableRenderingElement($child);
            $hasVariableChild = $hasVariableChild || $isVariableChild;
            $value = $this->renderRenderingElement($child, $item, $scope, $macroStack);
            if ($value === '') {
                continue;
            }

            $rendered[] = $value;
            $hasRenderedVariableChild = $hasRenderedVariableChild || $isVariableChild;
        }

        if ($hasVariableChild && !$hasRenderedVariableChild) {
            return '';
        }

        return $this->joinRenderedElements($rendered, (string) ($element['delimiter'] ?? ''));
    }

    /**
     * @param array<string, mixed> $element
     */
    private function isVariableRenderingElement(array $element): bool
    {
        $type = (string) ($element['type'] ?? '');
        if ($type === 'date' || $type === 'names') {
            return true;
        }

        if ($type === 'text' && array_key_exists('variable', $element)) {
            return true;
        }

        if ($type === 'text' && array_key_exists('macro', $element)) {
            $macro = $this->style->macroRenderingElements((string) $element['macro']);
            return is_array($macro) && $this->hasVariableRenderingElement($macro);
        }

        if ($type === 'group' && isset($element['children']) && is_array($element['children'])) {
            foreach ($element['children'] as $child) {
                if (is_array($child) && $this->isVariableRenderingElement($child)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $elements
     */
    private function hasVariableRenderingElement(array $elements): bool
    {
        foreach ($elements as $element) {
            if (is_array($element) && $this->isVariableRenderingElement($element)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     */
    private function renderTextElement(array $element, array $item, string $scope, array $macroStack = []): string
    {
        if (array_key_exists('macro', $element)) {
            return $this->renderMacroReference((string) $element['macro'], $item, $scope, $macroStack);
        }

        if (array_key_exists('variable', $element)) {
            return $this->renderVariableValue($item, (string) $element['variable'], $scope);
        }

        if (array_key_exists('term', $element)) {
            return $this->style->term(
                (string) $element['term'],
                (string) ($element['form'] ?? 'long'),
                (bool) ($element['plural'] ?? false)
            );
        }

        return (string) ($element['value'] ?? '');
    }

    /**
     * @param array<string, mixed> $item
     * @param list<string> $macroStack
     */
    private function renderMacroReference(string $name, array $item, string $scope, array $macroStack): string
    {
        $elements = $this->style->macroRenderingElements($name);
        if ($elements === null) {
            throw new \InvalidArgumentException('CSL references undefined macro: ' . $name);
        }

        if (in_array($name, $macroStack, true)) {
            throw new \InvalidArgumentException('CSL macro recursion detected: ' . implode(' -> ', [...$macroStack, $name]));
        }

        return $this->renderRenderingElementsWithMacroStack($elements, $item, $scope, '', [...$macroStack, $name]);
    }

    /**
     * @param list<array<string, mixed>> $elements
     * @param array<string, mixed> $item
     * @param list<string> $macroStack
     */
    private function renderRenderingElementsWithMacroStack(array $elements, array $item, string $scope, string $delimiter, array $macroStack): string
    {
        $rendered = [];
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            $value = $this->renderRenderingElement($element, $item, $scope, $macroStack);
            if ($value !== '') {
                $rendered[] = $value;
            }
        }

        return $this->joinRenderedElements($rendered, $delimiter);
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     */
    private function renderDateElement(array $element, array $item, string $scope): string
    {
        $variable = strtolower((string) ($element['variable'] ?? ''));
        $date = $this->dateVariableForRendering($item, $variable);
        if ($date === null) {
            return '';
        }

        $dateParts = $element['dateParts'] ?? [];
        if (is_array($dateParts) && $dateParts !== []) {
            return $this->renderSelectedDateParts($date, $dateParts, $scope, $variable);
        }

        return $this->renderDateVariable($date, $scope, $variable);
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     */
    private function renderNamesElement(array $element, array $item, string $scope): string
    {
        $names = $this->namesForRenderingVariable($item, (string) ($element['variable'] ?? 'author editor'));
        if ($names === []) {
            if ($scope === 'citation') {
                $title = (string) ($item['title'] ?? '');

                return $title === '' ? (string) ($item['id'] ?? '') : $title;
            }

            return '';
        }

        $elementOptions = $element['nameRendering'] ?? null;
        $options = is_array($elementOptions)
            ? $this->normalizedNameRenderingOptions($elementOptions, $scope)
            : ($scope === 'bibliography' ? $this->style->bibliographyNameRendering() : $this->style->citationNameRendering());

        return $this->renderNameList(
            $names,
            $options,
            $scope === 'bibliography'
        );
    }

    /**
     * @param array<string, mixed> $options
     * @return array{delimiter:string, and:string, etAlMin:int|null, etAlUseFirst:int, initializeWith:string|null, nameAsSortOrder:string}
     */
    private function normalizedNameRenderingOptions(array $options, string $scope): array
    {
        $defaults = $scope === 'bibliography'
            ? $this->style->bibliographyNameRendering()
            : $this->style->citationNameRendering();

        return [
            'delimiter' => is_string($options['delimiter'] ?? null) ? $options['delimiter'] : $defaults['delimiter'],
            'and' => is_string($options['and'] ?? null) ? $options['and'] : $defaults['and'],
            'etAlMin' => is_int($options['etAlMin'] ?? null) ? $options['etAlMin'] : $defaults['etAlMin'],
            'etAlUseFirst' => is_int($options['etAlUseFirst'] ?? null) ? $options['etAlUseFirst'] : $defaults['etAlUseFirst'],
            'initializeWith' => is_string($options['initializeWith'] ?? null) ? $options['initializeWith'] : $defaults['initializeWith'],
            'nameAsSortOrder' => is_string($options['nameAsSortOrder'] ?? null) ? $options['nameAsSortOrder'] : $defaults['nameAsSortOrder'],
        ];
    }

    /**
     * @param array<string, mixed> $element
     */
    private function applyRenderingAffixes(string $value, array $element): string
    {
        if ($value === '') {
            return '';
        }

        return (string) ($element['prefix'] ?? '') . $value . (string) ($element['suffix'] ?? '');
    }

    /**
     * @param array<string, mixed> $item
     */
    private function renderVariableValue(array $item, string $variable, string $scope): string
    {
        $normalized = strtolower(trim($variable));

        return match ($normalized) {
            'id', 'citation-key' => (string) $item['id'],
            'type' => (string) $item['type'],
            'title' => (string) $item['title'],
            'container-title' => (string) $item['containerTitle'],
            'publisher' => (string) $item['publisher'],
            'page' => (string) $item['page'],
            'doi' => (string) $item['doi'],
            'url' => (string) $item['url'],
            'language' => (string) $item['language'],
            'abstract' => (string) $item['abstract'],
            'keyword' => implode(', ', is_array($item['keywords'] ?? null) ? $item['keywords'] : []),
            'issued', 'date' => $this->renderDateVariable($item['issuedDate'] ?? null, $scope, 'issued'),
            'accessed' => $this->renderDateVariable($item['accessedDate'] ?? null, $scope, 'accessed'),
            'author' => $this->renderNamesElement(['variable' => 'author'], $item, $scope),
            'editor' => $this->renderNamesElement(['variable' => 'editor'], $item, $scope),
            default => $this->rawVariableValue($item, $variable),
        };
    }

    /**
     * @param array<string, mixed> $item
     */
    private function rawVariableValue(array $item, string $variable): string
    {
        $raw = $item['raw'] ?? [];
        if (!is_array($raw)) {
            return '';
        }

        foreach ([$variable, strtolower($variable), strtoupper($variable)] as $key) {
            $value = $raw[$key] ?? null;
            if (is_scalar($value)) {
                return trim((string) $value);
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $item
     * @return list<array{family:string, given:string, literal:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool}>
     */
    private function namesForRenderingVariable(array $item, string $variable): array
    {
        $variables = preg_split('/\s+/', strtolower(trim($variable))) ?: [];
        if ($variables === []) {
            $variables = ['author', 'editor'];
        }

        foreach ($variables as $nameVariable) {
            $names = match ($nameVariable) {
                'author' => $item['authors'] ?? [],
                'editor' => $item['editors'] ?? [],
                default => [],
            };
            if (is_array($names) && $names !== []) {
                return $names;
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $item
     * @return array{year:?int, parts:list<int>, display:string, literal:string}|null
     */
    private function dateVariableForRendering(array $item, string $variable): ?array
    {
        $normalized = strtolower(trim($variable));

        return match ($normalized) {
            'issued', 'date' => is_array($item['issuedDate'] ?? null) ? $item['issuedDate'] : null,
            'accessed' => is_array($item['accessedDate'] ?? null) ? $item['accessedDate'] : null,
            default => null,
        };
    }

    /**
     * @param array{year:?int, parts:list<int>, display:string, literal:string}|mixed $date
     */
    private function renderDateVariable(mixed $date, string $scope, string $variable): string
    {
        if (!is_array($date)) {
            return $scope === 'citation' && ($variable === 'issued' || $variable === 'date')
                ? $this->style->term('no date')
                : '';
        }

        $display = (string) ($date['display'] ?? '');
        if ($display !== '') {
            return $display;
        }

        $literal = (string) ($date['literal'] ?? '');
        if ($literal !== '') {
            return $literal;
        }

        return $scope === 'citation' && ($variable === 'issued' || $variable === 'date')
            ? $this->style->term('no date')
            : '';
    }

    /**
     * @param array{year:?int, parts:list<int>, display:string, literal:string} $date
     * @param list<string> $dateParts
     */
    private function renderSelectedDateParts(array $date, array $dateParts, string $scope, string $variable): string
    {
        $parts = is_array($date['parts'] ?? null) ? $date['parts'] : [];
        if ($parts === []) {
            return $this->renderDateVariable($date, $scope, $variable);
        }

        $values = [];
        foreach ($dateParts as $part) {
            $value = match ($part) {
                'year' => $parts[0] ?? null,
                'month' => $parts[1] ?? null,
                'day' => $parts[2] ?? null,
                default => null,
            };
            if ($value === null) {
                continue;
            }

            $values[] = $part === 'year' ? sprintf('%04d', (int) $value) : sprintf('%02d', (int) $value);
        }

        if ($values === []) {
            return '';
        }

        return implode('-', $values);
    }

    /**
     * @param list<array{family:string, given:string, literal:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool}> $names
     * @param array{delimiter:string, and:string, etAlMin:int|null, etAlUseFirst:int, initializeWith:string|null, nameAsSortOrder:string} $options
     */
    private function renderNameList(array $names, array $options, bool $bibliography): string
    {
        $count = count($names);
        $etAlMin = $options['etAlMin'];
        $useEtAl = is_int($etAlMin) && $count >= $etAlMin;
        $visibleCount = $useEtAl ? max(1, min((int) $options['etAlUseFirst'], $count)) : $count;
        $visible = array_slice($names, 0, $visibleCount);

        $rendered = [];
        foreach ($visible as $index => $name) {
            $rendered[] = $bibliography
                ? $this->renderBibliographyName($name, $options, $index)
                : $this->renderCitationName($name, $options);
        }

        if ($useEtAl) {
            $term = $this->style->term('et-al');
            if ($rendered === []) {
                return $term;
            }

            if (count($rendered) === 1) {
                return $rendered[0] . ' ' . $term;
            }

            return implode($options['delimiter'], $rendered) . $options['delimiter'] . $term;
        }

        return $bibliography
            ? implode($options['delimiter'], $rendered)
            : $this->joinCitationNames($rendered, $options);
    }

    /**
     * @param array{family:string, given:string, literal:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool} $name
     * @param array{delimiter:string, and:string, etAlMin:int|null, etAlUseFirst:int, initializeWith:string|null, nameAsSortOrder:string} $options
     */
    private function renderCitationName(array $name, array $options): string
    {
        if ($name['literal'] !== '') {
            return $name['literal'];
        }

        $family = trim((string) $name['nonDroppingParticle'] . ' ' . (string) $name['family']);
        if ($family !== '') {
            return $family;
        }

        return $this->renderGivenName((string) $name['given'], $options['initializeWith']);
    }

    /**
     * @param array{family:string, given:string, literal:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool} $name
     * @param array{delimiter:string, and:string, etAlMin:int|null, etAlUseFirst:int, initializeWith:string|null, nameAsSortOrder:string} $options
     */
    private function renderBibliographyName(array $name, array $options, int $index): string
    {
        if ($name['literal'] !== '') {
            return $name['literal'];
        }

        $family = trim((string) $name['nonDroppingParticle'] . ' ' . (string) $name['family']);
        $given = $this->renderGivenName((string) $name['given'], $options['initializeWith']);
        $droppingParticle = (string) $name['droppingParticle'];
        $suffix = (string) $name['suffix'];
        $sortOrdered = $options['nameAsSortOrder'] === 'all' || ($options['nameAsSortOrder'] === 'first' && $index === 0);

        if ($sortOrdered) {
            if ($family !== '' && $given !== '') {
                $entry = $family . ', ' . $given;
            } else {
                $entry = $family !== '' ? $family : $given;
            }
        } else {
            $entry = trim(($given !== '' ? $given . ' ' : '') . $family);
        }

        if ($droppingParticle !== '') {
            $entry = trim($entry . ' ' . $droppingParticle);
        }

        if ($suffix !== '') {
            $entry .= ($name['commaSuffix'] ? ', ' : ' ') . $suffix;
        }

        return $entry;
    }

    /**
     * @param list<string> $names
     * @param array{delimiter:string, and:string, etAlMin:int|null, etAlUseFirst:int, initializeWith:string|null, nameAsSortOrder:string} $options
     */
    private function joinCitationNames(array $names, array $options): string
    {
        $count = count($names);
        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return $names[0];
        }

        $and = $this->andJoiner($options);
        if ($and === '') {
            return implode($options['delimiter'], $names);
        }

        if ($count === 2) {
            return $names[0] . ' ' . $and . ' ' . $names[1];
        }

        return implode($options['delimiter'], array_slice($names, 0, -1))
            . $options['delimiter']
            . $and
            . ' '
            . $names[$count - 1];
    }

    /**
     * @param array{delimiter:string, and:string, etAlMin:int|null, etAlUseFirst:int, initializeWith:string|null, nameAsSortOrder:string} $options
     */
    private function andJoiner(array $options): string
    {
        return match ($options['and']) {
            'symbol' => '&',
            'none' => '',
            default => $this->style->term('and'),
        };
    }

    private function renderGivenName(string $given, ?string $initializeWith): string
    {
        $given = trim($given);
        if ($given === '' || $initializeWith === null) {
            return $given;
        }

        $parts = preg_split('/[\s-]+/u', $given) ?: [];
        $initials = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || preg_match('/^./us', $part, $match) !== 1) {
                continue;
            }

            $initials[] = $this->uppercaseInitial($match[0]) . $initializeWith;
        }

        return rtrim(implode('', $initials));
    }

    private function uppercaseInitial(string $initial): string
    {
        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($initial, 'UTF-8');
        }

        return strtoupper($initial);
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
