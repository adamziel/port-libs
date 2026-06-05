<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class CitationCslProcessor
{
    /** @var array<string, array<string, mixed>> */
    private array $itemsById;

    /** @var list<string> */
    private array $primaryIds;

    /** @var array<string, string> */
    private array $canonicalIdsById;

    private CslStyle $style;

    /**
     * @param array<string, array<string, mixed>> $itemsById
     * @param list<string> $primaryIds
     * @param array<string, string> $canonicalIdsById
     */
    private function __construct(array $itemsById, ?CslStyle $style = null, array $primaryIds = [], array $canonicalIdsById = [])
    {
        $this->itemsById = $itemsById;
        $this->primaryIds = $primaryIds === [] ? array_keys($itemsById) : $primaryIds;
        $this->canonicalIdsById = $canonicalIdsById;
        foreach ($this->primaryIds as $id) {
            $this->canonicalIdsById[$id] = $this->canonicalIdsById[$id] ?? $id;
        }
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
        $primaryIds = [];
        $canonicalIdsById = [];
        $normalizedItems = [];
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
            $primaryIds[] = $id;
            $canonicalIdsById[$id] = $id;
            $normalizedItems[] = $normalized;
        }

        foreach ($normalizedItems as $normalized) {
            $id = (string) $normalized['id'];
            $aliases = $normalized['citationAliases'] ?? [];
            if (!is_array($aliases)) {
                continue;
            }

            foreach ($aliases as $alias) {
                if (!is_scalar($alias)) {
                    continue;
                }

                $alias = trim((string) $alias);
                if ($alias === '' || $alias === $id) {
                    continue;
                }

                if (isset($itemsById[$alias]) || isset($canonicalIdsById[$alias])) {
                    throw new \InvalidArgumentException('Duplicate CSL citation alias: ' . $alias);
                }

                $itemsById[$alias] = [
                    ...$normalized,
                    'citationAlias' => $alias,
                ];
                $canonicalIdsById[$alias] = $id;
            }
        }

        return new self($itemsById, null, $primaryIds, $canonicalIdsById);
    }

    /**
     * @param list<string> $localeXmls
     */
    public function withCslStyle(string $styleXml, array $localeXmls = []): self
    {
        return new self($this->itemsById, CslStyle::fromXml($styleXml, $localeXmls), $this->primaryIds, $this->canonicalIdsById);
    }

    /**
     * @return array{title:string, id:string, class:string, defaultLocale:string, citationLayout:array{prefix:string, suffix:string, delimiter:string}, bibliographyLayout:array{prefix:string, suffix:string, delimiter:string}, bibliographyOptions:array{hangingIndent:bool, entrySpacing:int|null, lineSpacing:int|null, secondFieldAlign:string}, citationOptions:array{disambiguateAddYearSuffix:bool, collapse:string}, citationSort:list<array{sort:string, variable?:string, macro?:string}>, bibliographySort:list<array{sort:string, variable?:string, macro?:string}>, citationRendering:list<array<string, mixed>>, bibliographyRendering:list<array<string, mixed>>, macros:array<string, list<array<string, mixed>>>, terms:array{and:string, etAl:string, noDate:string, accessed:string}}
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

        $item = $this->itemWithCitationContext($item, $citation);

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

        $citations = $this->ensureClusterCitationPositions($citations);
        $citations = $this->annotateCitationYearSuffixesForCluster($citations);
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
        $state = $this->emptyCitationPositionState();
        $positioned = $this->annotateCitationPositions($document, $state);
        $citationNumbers = $this->citationNumbersForIds($this->sortBibliographyIds($this->uniqueKnownCitationIds($positioned)));
        $numbered = $this->annotateCitationNumbers($positioned, $citationNumbers);
        $yearSuffixes = $this->yearSuffixesForIds($this->uniqueKnownCitationIds($numbered));
        $annotated = $this->annotateCitationYearSuffixes($numbered, $yearSuffixes);

        return $this->mapNode($annotated);
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
        $yearSuffixes = $this->yearSuffixesForIds($ids);
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
            $this->bibliographyDefinitionList($ids, $yearSuffixes),
        ];
    }

    /**
     * @param list<AstNode> $citations
     */
    public function renderCitationCluster(array $citations): string
    {
        $citations = $this->ensureClusterCitationPositions($citations);
        $citations = $this->sortCitationCluster($citations);
        $citations = $this->annotateCitationNumbersForCluster($citations);
        $citations = $this->annotateCitationYearSuffixesForCluster($citations);
        $entries = $this->renderCollapsedCitationEntries($citations);
        if ($entries === null) {
            $entries = [];
            foreach ($citations as $citation) {
                if (!$citation instanceof AstNode || $citation->type !== 'citation') {
                    throw new \InvalidArgumentException('Citation cluster entries must be citation AST nodes');
                }

                $entries[] = $this->renderCitationEntry($citation);
            }
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

        $canonicalId = $this->canonicalCitationId($id);
        $yearSuffixes = $this->yearSuffixesForIds($this->primaryIds);
        $item = $this->itemWithYearSuffix($item, $yearSuffixes[$canonicalId] ?? '');
        $item = $this->itemWithCitationNumber($item, $this->citationNumberForId($canonicalId));

        return $this->renderBibliographyEntryForItem($item);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function renderBibliographyEntryForItem(array $item): string
    {
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

        $titleAddon = (string) $item['titleAddon'];
        if ($titleAddon !== '') {
            $parts[] = $titleAddon . '.';
        }

        $container = (string) $item['containerTitle'];
        if ($container !== '') {
            $parts[] = $container . '.';
        }

        $containerTitleAddon = (string) $item['containerTitleAddon'];
        if ($containerTitleAddon !== '') {
            $parts[] = $containerTitleAddon . '.';
        }

        foreach ($this->publicationDetailBibliographyParts($item) as $part) {
            $parts[] = $part;
        }

        foreach ($this->eventBibliographyParts($item) as $part) {
            $parts[] = $part;
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

        $legalPatentMetadata = $this->legalPatentBibliographyParts($item);
        foreach ($legalPatentMetadata as $part) {
            $parts[] = $part;
        }

        foreach ($this->reviewMetadataBibliographyParts($item) as $part) {
            $parts[] = $part;
        }

        foreach ($this->nameMetadataBibliographyParts($item) as $part) {
            $parts[] = $part;
        }

        $translators = $this->bibliographyTranslators($item);
        if ($translators !== '') {
            $parts[] = 'Translated by ' . rtrim($translators, '.') . '.';
        }

        foreach ($this->bibliographyRoleNameParts($item) as $part) {
            $parts[] = $part;
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

        foreach ($this->identifierBibliographyParts($item) as $part) {
            $parts[] = $part;
        }

        $accessedDate = $item['accessedDate'] ?? null;
        if (is_array($accessedDate) && (string) ($accessedDate['display'] ?? '') !== '') {
            $parts[] = $this->style->term('accessed') . ' ' . (string) $accessedDate['display'] . '.';
        }

        return $this->style->formatBibliographyEntry(implode($this->style->bibliographyDelimiter(), $parts));
    }

    /**
     * @param list<string> $ids
     * @param array<string, string> $yearSuffixes
     */
    public function bibliographyDefinitionList(array $ids, array $yearSuffixes = []): AstNode
    {
        if ($yearSuffixes === []) {
            $yearSuffixes = $this->yearSuffixesForIds($ids);
        }

        $citationNumbers = $this->citationNumbersForIds($ids);
        $items = [];
        foreach ($ids as $id) {
            $item = $this->itemsById[$id] ?? null;
            if ($item === null) {
                continue;
            }

            $item = $this->itemWithYearSuffix($item, $yearSuffixes[$id] ?? '');
            $item = $this->itemWithCitationNumber($item, $citationNumbers[$this->canonicalCitationId($id)] ?? '');
            $label = $this->citationLabel($item);
            $entry = $this->renderBibliographyEntryForItem($item);
            $attrs = ['term' => $label, 'cslId' => $id];
            $displayParts = $this->bibliographyDisplayParts($item);
            if ($displayParts !== []) {
                $attrs['cslDisplayParts'] = $displayParts;
            }

            $items[] = new AstNode('definition_item', $attrs, [
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
            'citationAliases' => self::stringListFromFirstField($item, ['citation-aliases', 'citationAliases', 'ids']),
            'citationLabel' => self::firstStringField($item, ['citation-label', 'citationLabel', 'shorthand', 'label']),
            'shorthand' => self::firstStringField($item, ['shorthand']),
            'shorthandIntro' => self::firstStringField($item, ['shorthand-intro', 'shorthandIntro', 'shorthandintro']),
            'title' => self::stringField($item, 'title'),
            'shortTitle' => self::stringField($item, 'short-title'),
            'titleAddon' => self::stringField($item, 'title-addon'),
            'containerTitle' => self::stringField($item, 'container-title'),
            'containerTitleAddon' => self::stringField($item, 'container-title-addon'),
            'mainTitle' => self::firstStringField($item, ['main-title', 'mainTitle']),
            'mainTitleAddon' => self::firstStringField($item, ['main-title-addon', 'mainTitleAddon']),
            'eventTitle' => self::firstStringField($item, ['event', 'event-title', 'eventTitle']),
            'eventTitleAddon' => self::firstStringField($item, ['event-title-addon', 'eventTitleAddon']),
            'eventPlace' => self::firstStringField($item, ['event-place', 'eventPlace']),
            'eventType' => self::firstStringField($item, ['event-type', 'eventType']),
            'publisher' => self::stringField($item, 'publisher'),
            'publisherPlace' => self::stringField($item, 'publisher-place'),
            'page' => self::stringField($item, 'page'),
            'number' => self::stringField($item, 'number'),
            'volume' => self::stringField($item, 'volume'),
            'issue' => self::stringField($item, 'issue'),
            'edition' => self::stringField($item, 'edition'),
            'collectionTitle' => self::firstStringField($item, ['collection-title', 'collectionTitle']),
            'collectionNumber' => self::firstStringField($item, ['collection-number', 'collectionNumber']),
            'numberOfVolumes' => self::firstStringField($item, ['number-of-volumes', 'numberOfVolumes']),
            'numberOfPages' => self::firstStringField($item, ['number-of-pages', 'numberOfPages']),
            'chapterNumber' => self::firstStringField($item, ['chapter-number', 'chapterNumber']),
            'part' => self::stringField($item, 'part'),
            'genre' => self::stringField($item, 'genre'),
            'authority' => self::stringField($item, 'authority'),
            'jurisdiction' => self::stringField($item, 'jurisdiction'),
            'status' => self::stringField($item, 'status'),
            'version' => self::stringField($item, 'version'),
            'doi' => self::firstStringField($item, ['DOI', 'doi']),
            'url' => self::firstStringField($item, ['URL', 'url']),
            'isbn' => self::firstStringField($item, ['ISBN', 'isbn']),
            'issn' => self::firstStringField($item, ['ISSN', 'issn']),
            'archive' => self::stringField($item, 'archive'),
            'archivePlace' => self::firstStringField($item, ['archive-place', 'archivePlace']),
            'archiveLocation' => self::firstStringField($item, ['archive_location', 'archive-location', 'archiveLocation']),
            'language' => self::stringField($item, 'language'),
            'abstract' => self::stringField($item, 'abstract'),
            'medium' => self::stringField($item, 'medium'),
            'note' => self::stringField($item, 'note'),
            'addendum' => self::stringField($item, 'addendum'),
            'nameAddon' => self::firstStringField($item, ['name-addon', 'nameAddon']),
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
            'eventDate' => self::dateVariable($item['event-date'] ?? null, $id, 'event-date'),
            'issuedYear' => $issuedDate['year'],
            'authors' => self::names($item['author'] ?? [], $id, 'author'),
            'editors' => self::names($item['editor'] ?? [], $id, 'editor'),
            'shortAuthors' => self::names($item['short-author'] ?? $item['shortAuthor'] ?? [], $id, 'short-author'),
            'shortEditors' => self::names($item['short-editor'] ?? $item['shortEditor'] ?? [], $id, 'short-editor'),
            'holders' => self::names($item['holder'] ?? [], $id, 'holder'),
            'translators' => self::names($item['translator'] ?? [], $id, 'translator'),
            'eventOrganizers' => self::names($item['event-organizer'] ?? [], $id, 'event-organizer'),
            'originalAuthors' => self::names($item['original-author'] ?? [], $id, 'original-author'),
            'compilers' => self::names($item['compiler'] ?? [], $id, 'compiler'),
            'curators' => self::names($item['curator'] ?? [], $id, 'curator'),
            'directors' => self::names($item['director'] ?? [], $id, 'director'),
            'editorialDirectors' => self::names($item['editorial-director'] ?? [], $id, 'editorial-director'),
            'illustrators' => self::names($item['illustrator'] ?? [], $id, 'illustrator'),
            'interviewers' => self::names($item['interviewer'] ?? [], $id, 'interviewer'),
            'reviewedAuthors' => self::names($item['reviewed-author'] ?? [], $id, 'reviewed-author'),
            'commentators' => self::names($item['commentator'] ?? [], $id, 'commentator'),
            'annotators' => self::names($item['annotator'] ?? [], $id, 'annotator'),
            'introductionAuthors' => self::names($item['introduction'] ?? [], $id, 'introduction'),
            'forewordAuthors' => self::names($item['foreword'] ?? [], $id, 'foreword'),
            'afterwordAuthors' => self::names($item['afterword'] ?? [], $id, 'afterword'),
            'editorialRoles' => self::editorialRoles($item['editorial-roles'] ?? [], $id),
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
     * @param array<string, mixed> $item
     * @param list<string> $keys
     */
    private static function firstStringField(array $item, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $item[$key] ?? null;
            if ($value === null) {
                continue;
            }

            if (!is_scalar($value)) {
                throw new \InvalidArgumentException('CSL field ' . $key . ' must be scalar when present');
            }

            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private static function stringListField(array $item, string $key): array
    {
        return self::stringListValue($item[$key] ?? [], $key);
    }

    /**
     * @param array<string, mixed> $item
     * @param list<string> $keys
     * @return list<string>
     */
    private static function stringListFromFirstField(array $item, array $keys): array
    {
        foreach ($keys as $key) {
            $value = $item[$key] ?? null;
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            return self::stringListValue($value, $key);
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private static function stringListValue(mixed $value, string $key): array
    {
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
     * @return list<array{family:string, given:string, literal:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool, annotations:list<array{part:string, value:string}>}>
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
                'annotations' => self::nameAnnotations($name['annotations'] ?? [], $id, $field, $index),
            ];
        }

        return $names;
    }

    /**
     * @return list<array{part:string, value:string}>
     */
    private static function nameAnnotations(mixed $value, string $id, string $field, int $nameIndex): array
    {
        if ($value === null || $value === []) {
            return [];
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' field ' . $field . '[' . $nameIndex . '].annotations must be a list');
        }

        $annotations = [];
        foreach ($value as $index => $annotation) {
            if (!is_array($annotation)) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' field ' . $field . '[' . $nameIndex . '].annotations[' . $index . '] must be an object');
            }

            $part = self::nameString($annotation['part'] ?? 'name');
            $part = strtolower(str_replace('_', '-', $part));
            $text = self::nameString($annotation['value'] ?? '');
            if ($text === '') {
                continue;
            }

            $annotations[] = [
                'part' => $part === '' ? 'name' : $part,
                'value' => $text,
            ];
        }

        return $annotations;
    }

    /**
     * @return list<array{field:string, type:string, label:string, names:list<array{family:string, given:string, literal:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool, annotations:list<array{part:string, value:string}>}>}>
     */
    private static function editorialRoles(mixed $value, string $id): array
    {
        if ($value === null || $value === []) {
            return [];
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' editorial-roles must be a list');
        }

        $roles = [];
        foreach ($value as $index => $role) {
            if (!is_array($role)) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' editorial-roles[' . $index . '] must be an object');
            }

            $names = self::names($role['names'] ?? [], $id, 'editorial-roles[' . $index . '].names');
            if ($names === []) {
                continue;
            }

            $type = self::nameString($role['type'] ?? '');
            $label = self::nameString($role['label'] ?? '');
            $roles[] = [
                'field' => self::nameString($role['field'] ?? ''),
                'type' => $type === '' ? 'editor' : $type,
                'label' => $label === '' ? self::editorialRoleDefaultLabel($type) : $label,
                'names' => $names,
            ];
        }

        return $roles;
    }

    private static function editorialRoleDefaultLabel(string $type): string
    {
        $type = str_replace(['_', '-'], ' ', strtolower(trim($type)));
        if ($type === '') {
            return 'Editor';
        }

        return ucfirst($type);
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
     * @return array{year:?int, parts:list<int>, display:string, literal:string, rangeParts?:list<list<int>>}
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

        $rangeParts = [];
        foreach (array_slice($dateParts, 0, 2) as $rangeIndex => $rangePart) {
            if (!is_array($rangePart) || !isset($rangePart[0])) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . ' date-parts[' . $rangeIndex . '] must contain a year');
            }

            $rangeParts[] = self::normalizedDatePartList($rangePart, $id, $field);
        }

        $parts = $rangeParts[0];
        $normalized = [
            'year' => $parts[0],
            'parts' => $parts,
            'display' => self::formatDatePartsRange($rangeParts),
            'literal' => '',
        ];
        if (count($rangeParts) > 1) {
            $normalized['rangeParts'] = $rangeParts;
        }

        return $normalized;
    }

    /**
     * @param list<mixed> $parts
     * @return list<int>
     */
    private static function normalizedDatePartList(array $parts, string $id, string $field): array
    {
        $normalized = [];
        foreach (array_slice($parts, 0, 3) as $partIndex => $part) {
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

            $normalized[] = $number;
        }

        return $normalized;
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

    /**
     * @param list<list<int>> $rangeParts
     */
    private static function formatDatePartsRange(array $rangeParts): string
    {
        $formatted = array_map(
            static fn (array $parts): string => self::formatDateParts($parts),
            $rangeParts
        );

        return implode('/', $formatted);
    }

    /**
     * @return array{seenIds:array<string, bool>, previousUnit:array{single:bool, first:array{id:string, locatorLabel:string, locatorValue:string, locatorKey:string}|null}|null}
     */
    private function emptyCitationPositionState(): array
    {
        return [
            'seenIds' => [],
            'previousUnit' => null,
        ];
    }

    /**
     * @param array{seenIds:array<string, bool>, previousUnit:array{single:bool, first:array{id:string, locatorLabel:string, locatorValue:string, locatorKey:string}|null}|null} $state
     */
    private function annotateCitationPositions(AstNode $node, array &$state): AstNode
    {
        if ($node->type === 'citation') {
            [$annotated, $info] = $this->annotateCitationPosition($node, $state, null, true);
            $this->recordCitationPositionUnit($state, [$info]);

            return $annotated;
        }

        if ($node->type === 'citation_group') {
            $children = [];
            $unit = [];
            $previousInUnit = null;
            foreach ($node->children as $child) {
                if ($child->type !== 'citation') {
                    throw new \InvalidArgumentException('Citation group entries must be citation AST nodes');
                }

                [$annotated, $info] = $this->annotateCitationPosition($child, $state, $previousInUnit, $children === []);
                $children[] = $annotated;
                $unit[] = $info;
                $previousInUnit = $info;
            }

            $this->recordCitationPositionUnit($state, $unit);

            return new AstNode($node->type, $node->attrs, $children);
        }

        if ($node->children === []) {
            return $node;
        }

        $children = [];
        $changed = false;
        foreach ($node->children as $child) {
            $annotated = $this->annotateCitationPositions($child, $state);
            $children[] = $annotated;
            $changed = $changed || $annotated !== $child;
        }

        return $changed ? new AstNode($node->type, $node->attrs, $children) : $node;
    }

    /**
     * @param array{seenIds:array<string, bool>, previousUnit:array{single:bool, first:array{id:string, locatorLabel:string, locatorValue:string, locatorKey:string}|null}|null} $state
     * @param array{id:string, locatorLabel:string, locatorValue:string, locatorKey:string}|null $previousInUnit
     * @return array{AstNode, array{id:string, locatorLabel:string, locatorValue:string, locatorKey:string}}
     */
    private function annotateCitationPosition(AstNode $citation, array &$state, ?array $previousInUnit, bool $firstInUnit): array
    {
        $info = $this->citationPositionInfo($citation);
        $position = $this->citationPositionForInfo($info, $state, $previousInUnit, $firstInUnit);
        $annotated = new AstNode('citation', [
            ...$citation->attrs,
            'cslPosition' => $position['position'],
            'cslPositionTests' => $position['tests'],
        ], $citation->children);

        if ($info['id'] !== '') {
            $state['seenIds'][$info['id']] = true;
        }

        return [$annotated, $info];
    }

    /**
     * @param array{seenIds:array<string, bool>, previousUnit:array{single:bool, first:array{id:string, locatorLabel:string, locatorValue:string, locatorKey:string}|null}|null} $state
     * @param list<array{id:string, locatorLabel:string, locatorValue:string, locatorKey:string}> $unit
     */
    private function recordCitationPositionUnit(array &$state, array $unit): void
    {
        $known = array_values(array_filter(
            $unit,
            static fn (array $info): bool => $info['id'] !== ''
        ));
        $state['previousUnit'] = [
            'single' => count($known) === 1,
            'first' => $known[0] ?? null,
        ];
    }

    /**
     * @return array{id:string, locatorLabel:string, locatorValue:string, locatorKey:string}
     */
    private function citationPositionInfo(AstNode $citation): array
    {
        $id = (string) $citation->attr('id', '');
        if ($id !== '' && !isset($this->itemsById[$id])) {
            $id = '';
        } elseif ($id !== '') {
            $id = $this->canonicalCitationId($id);
        }

        $locator = $this->citationLocatorParts($citation);

        return [
            'id' => $id,
            'locatorLabel' => $locator['label'],
            'locatorValue' => $locator['value'],
            'locatorKey' => $locator['label'] . "\n" . $locator['value'],
        ];
    }

    /**
     * @param array{id:string, locatorLabel:string, locatorValue:string, locatorKey:string} $info
     * @param array{seenIds:array<string, bool>, previousUnit:array{single:bool, first:array{id:string, locatorLabel:string, locatorValue:string, locatorKey:string}|null}|null} $state
     * @param array{id:string, locatorLabel:string, locatorValue:string, locatorKey:string}|null $previousInUnit
     * @return array{position:string, tests:list<string>}
     */
    private function citationPositionForInfo(array $info, array $state, ?array $previousInUnit, bool $firstInUnit): array
    {
        if ($info['id'] === '' || !isset($state['seenIds'][$info['id']])) {
            return ['position' => 'first', 'tests' => ['first']];
        }

        $preceding = null;
        if (is_array($previousInUnit) && $previousInUnit['id'] === $info['id']) {
            $preceding = $previousInUnit;
        } elseif ($firstInUnit) {
            $previousUnit = $state['previousUnit'] ?? null;
            if (
                is_array($previousUnit)
                && ($previousUnit['single'] ?? false) === true
                && is_array($previousUnit['first'] ?? null)
                && $previousUnit['first']['id'] === $info['id']
            ) {
                $preceding = $previousUnit['first'];
            }
        }

        if ($preceding === null) {
            return ['position' => 'subsequent', 'tests' => ['subsequent']];
        }

        $precedingHasLocator = $preceding['locatorValue'] !== '';
        $currentHasLocator = $info['locatorValue'] !== '';
        if (!$precedingHasLocator) {
            return $currentHasLocator
                ? ['position' => 'ibid-with-locator', 'tests' => ['subsequent', 'ibid', 'ibid-with-locator']]
                : ['position' => 'ibid', 'tests' => ['subsequent', 'ibid']];
        }

        if (!$currentHasLocator) {
            return ['position' => 'subsequent', 'tests' => ['subsequent']];
        }

        if ($preceding['locatorKey'] === $info['locatorKey']) {
            return ['position' => 'ibid', 'tests' => ['subsequent', 'ibid']];
        }

        return ['position' => 'ibid-with-locator', 'tests' => ['subsequent', 'ibid', 'ibid-with-locator']];
    }

    /**
     * @param list<AstNode> $citations
     * @return list<AstNode>
     */
    private function ensureClusterCitationPositions(array $citations): array
    {
        if ($citations === []) {
            return [];
        }

        $hasPositions = true;
        foreach ($citations as $citation) {
            if (!$citation instanceof AstNode || $citation->type !== 'citation') {
                return $citations;
            }

            if ((string) $citation->attr('cslPosition', '') === '') {
                $hasPositions = false;
            }
        }

        if ($hasPositions) {
            return $citations;
        }

        $state = $this->emptyCitationPositionState();
        $annotated = [];
        $previousInUnit = null;
        foreach ($citations as $citation) {
            [$node, $info] = $this->annotateCitationPosition($citation, $state, $previousInUnit, $annotated === []);
            $annotated[] = $node;
            $previousInUnit = $info;
        }

        return $annotated;
    }

    /**
     * @param list<AstNode> $citations
     * @return list<AstNode>
     */
    private function annotateCitationYearSuffixesForCluster(array $citations): array
    {
        if (!$this->style->citationOptions()['disambiguateAddYearSuffix']) {
            return $citations;
        }

        $ids = [];
        foreach ($citations as $citation) {
            if (!$citation instanceof AstNode || $citation->type !== 'citation') {
                return $citations;
            }

            $id = (string) $citation->attr('id', '');
            if ($id !== '' && isset($this->itemsById[$id])) {
                $canonicalId = $this->canonicalCitationId($id);
                if (!in_array($canonicalId, $ids, true)) {
                    $ids[] = $canonicalId;
                }
            }
        }

        $yearSuffixes = $this->yearSuffixesForIds($ids);
        $annotated = [];
        foreach ($citations as $citation) {
            if (array_key_exists('cslYearSuffix', $citation->attrs)) {
                $annotated[] = $citation;
                continue;
            }

            $id = (string) $citation->attr('id', '');
            $canonicalId = $this->canonicalCitationId($id);
            $annotated[] = new AstNode($citation->type, [
                ...$citation->attrs,
                'cslYearSuffix' => $yearSuffixes[$canonicalId] ?? '',
            ], $citation->children);
        }

        return $annotated;
    }

    /**
     * @param array<string, string> $yearSuffixes
     */
    private function annotateCitationYearSuffixes(AstNode $node, array $yearSuffixes): AstNode
    {
        if (!$this->style->citationOptions()['disambiguateAddYearSuffix']) {
            return $node;
        }

        if ($node->type === 'citation') {
            $id = (string) $node->attr('id', '');
            if ($id === '' || !isset($this->itemsById[$id])) {
                return $node;
            }

            $canonicalId = $this->canonicalCitationId($id);

            return new AstNode($node->type, [
                ...$node->attrs,
                'cslYearSuffix' => $yearSuffixes[$canonicalId] ?? '',
            ], $node->children);
        }

        if ($node->children === []) {
            return $node;
        }

        $children = [];
        $changed = false;
        foreach ($node->children as $child) {
            $annotated = $this->annotateCitationYearSuffixes($child, $yearSuffixes);
            $children[] = $annotated;
            $changed = $changed || $annotated !== $child;
        }

        return $changed ? new AstNode($node->type, $node->attrs, $children) : $node;
    }

    /**
     * @param array<string, string> $citationNumbers
     */
    private function annotateCitationNumbers(AstNode $node, array $citationNumbers): AstNode
    {
        if ($node->type === 'citation') {
            $id = (string) $node->attr('id', '');
            if ($id === '' || !isset($this->itemsById[$id])) {
                return $node;
            }

            $canonicalId = $this->canonicalCitationId($id);

            return new AstNode($node->type, [
                ...$node->attrs,
                'cslCitationNumber' => $citationNumbers[$canonicalId] ?? '',
            ], $node->children);
        }

        if ($node->children === []) {
            return $node;
        }

        $children = [];
        $changed = false;
        foreach ($node->children as $child) {
            $annotated = $this->annotateCitationNumbers($child, $citationNumbers);
            $children[] = $annotated;
            $changed = $changed || $annotated !== $child;
        }

        return $changed ? new AstNode($node->type, $node->attrs, $children) : $node;
    }

    /**
     * @param list<AstNode> $citations
     * @return list<AstNode>
     */
    private function annotateCitationNumbersForCluster(array $citations): array
    {
        if ($citations === []) {
            return [];
        }

        $ids = [];
        foreach ($citations as $citation) {
            if (!$citation instanceof AstNode || $citation->type !== 'citation') {
                return $citations;
            }

            $id = (string) $citation->attr('id', '');
            if ($id !== '' && isset($this->itemsById[$id])) {
                $canonicalId = $this->canonicalCitationId($id);
                if (!in_array($canonicalId, $ids, true)) {
                    $ids[] = $canonicalId;
                }
            }
        }

        $numbers = $this->citationNumbersForIds($this->sortBibliographyIds($ids));
        $annotated = [];
        foreach ($citations as $citation) {
            if (array_key_exists('cslCitationNumber', $citation->attrs)) {
                $annotated[] = $citation;
                continue;
            }

            $id = (string) $citation->attr('id', '');
            $canonicalId = $this->canonicalCitationId($id);
            $annotated[] = new AstNode($citation->type, [
                ...$citation->attrs,
                'cslCitationNumber' => $numbers[$canonicalId] ?? '',
            ], $citation->children);
        }

        return $annotated;
    }

    /**
     * @param list<string> $ids
     * @return array<string, string>
     */
    private function citationNumbersForIds(array $ids): array
    {
        $numbers = [];
        $number = 1;
        foreach ($ids as $id) {
            $canonicalId = $this->canonicalCitationId($id);
            if (!isset($this->itemsById[$canonicalId]) || array_key_exists($canonicalId, $numbers)) {
                continue;
            }

            $numbers[$canonicalId] = (string) $number;
            $number++;
        }

        return $numbers;
    }

    private function citationNumberForId(string $id): string
    {
        $canonicalId = $this->canonicalCitationId($id);
        $numbers = $this->citationNumbersForIds($this->sortBibliographyIds($this->primaryIds));

        return $numbers[$canonicalId] ?? '';
    }

    private function primaryCitationNumberForId(string $id): string
    {
        $canonicalId = $this->canonicalCitationId($id);
        foreach ($this->primaryIds as $index => $primaryId) {
            if ($this->canonicalCitationId($primaryId) === $canonicalId) {
                return (string) ($index + 1);
            }
        }

        return '';
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
            if (!isset($this->itemsById[$id])) {
                continue;
            }

            $canonicalId = $this->canonicalCitationId($id);
            if (!in_array($canonicalId, $ids, true)) {
                $ids[] = $canonicalId;
            }
        }

        return $ids;
    }

    private function canonicalCitationId(string $id): string
    {
        return $this->canonicalIdsById[$id] ?? $id;
    }

    /**
     * @param list<string> $ids
     * @return array<string, string>
     */
    private function yearSuffixesForIds(array $ids): array
    {
        if (!$this->style->citationOptions()['disambiguateAddYearSuffix']) {
            return [];
        }

        $suffixes = [];
        $groups = [];
        foreach ($ids as $id) {
            $id = $this->canonicalCitationId($id);
            if (!isset($this->itemsById[$id]) || array_key_exists($id, $suffixes)) {
                continue;
            }

            $item = $this->itemsById[$id];
            $suffixes[$id] = '';
            $groups[$this->yearSuffixDisambiguationKey($item)][] = $id;
        }

        foreach ($groups as $groupIds) {
            if (count($groupIds) < 2) {
                continue;
            }

            foreach (array_values($groupIds) as $index => $id) {
                $suffixes[$id] = $this->yearSuffixForIndex($index);
            }
        }

        return $suffixes;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function yearSuffixDisambiguationKey(array $item): string
    {
        $withoutSuffix = $this->itemWithYearSuffix($item, '');

        return $this->citationAuthorLabel($withoutSuffix) . "\n" . $this->citationYear($withoutSuffix);
    }

    private function yearSuffixForIndex(int $index): string
    {
        $suffix = '';
        do {
            $suffix = chr(ord('a') + ($index % 26)) . $suffix;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);

        return $suffix;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function itemWithYearSuffix(array $item, string $suffix): array
    {
        return [
            ...$item,
            'yearSuffix' => $suffix,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function itemWithCitationNumber(array $item, string $number): array
    {
        return [
            ...$item,
            'citationNumber' => $number,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function itemWithCitationContext(array $item, ?AstNode $citation): array
    {
        if (!$citation instanceof AstNode) {
            return $item;
        }

        if (array_key_exists('cslYearSuffix', $citation->attrs)) {
            $item = $this->itemWithYearSuffix($item, (string) $citation->attr('cslYearSuffix', ''));
        }

        if (array_key_exists('cslCitationNumber', $citation->attrs)) {
            $item = $this->itemWithCitationNumber($item, (string) $citation->attr('cslCitationNumber', ''));
        }

        return $item;
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
            'short-title' => $this->normalizeSortText((string) $item['shortTitle']),
            'citation-label' => $this->normalizeSortText((string) $item['citationLabel']),
            'shorthand' => $this->normalizeSortText((string) $item['shorthand']),
            'container-title' => $this->normalizeSortText((string) $item['containerTitle']),
            'event', 'event-title' => $this->normalizeSortText((string) $item['eventTitle']),
            'event-place' => $this->normalizeSortText((string) $item['eventPlace']),
            'publisher' => $this->normalizeSortText((string) $item['publisher']),
            'type' => $this->normalizeSortText((string) $item['type']),
            'citation-number' => sprintf('%08d', (int) $this->primaryCitationNumberForId((string) ($item['id'] ?? ''))),
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

    /**
     * @param list<AstNode> $citations
     * @return list<string>|null
     */
    private function renderCollapsedCitationEntries(array $citations): ?array
    {
        $mode = $this->citationCollapseMode();
        if ($mode === '' || $mode === 'citation-number' || count($citations) < 2) {
            return null;
        }

        if (!$this->citationCollapseLayoutIsAuthorDateLike()) {
            return null;
        }

        $entries = [];
        $run = [];
        foreach ($citations as $citation) {
            if (!$citation instanceof AstNode || $citation->type !== 'citation') {
                throw new \InvalidArgumentException('Citation cluster entries must be citation AST nodes');
            }

            $entry = $this->collapseableCitationEntry($citation);
            if ($entry === null) {
                $this->appendCollapsedCitationRun($entries, $run, $mode);
                $run = [];
                $entries[] = $this->renderCitationEntry($citation);
                continue;
            }

            if ($run !== [] && (string) $entry['prefix'] !== '') {
                $this->appendCollapsedCitationRun($entries, $run, $mode);
                $run = [$entry];
                continue;
            }

            if ($run === [] || $this->citationCollapseEntriesMatch($run[0], $entry, $mode)) {
                $run[] = $entry;
                continue;
            }

            $this->appendCollapsedCitationRun($entries, $run, $mode);
            $run = [$entry];
        }

        $this->appendCollapsedCitationRun($entries, $run, $mode);

        return $entries;
    }

    private function citationCollapseMode(): string
    {
        $options = $this->style->citationOptions();
        $mode = (string) ($options['collapse'] ?? '');

        return in_array($mode, ['citation-number', 'year', 'year-suffix', 'year-suffix-ranged'], true) ? $mode : '';
    }

    private function citationCollapseLayoutIsAuthorDateLike(): bool
    {
        $elements = $this->style->citationRenderingElements();
        if ($elements === []) {
            return true;
        }

        $shape = $this->citationCollapseRenderingShape($elements);

        return $shape['hasCreator'] && $shape['hasYear'] && !$shape['unsupported'];
    }

    /**
     * @param list<array<string, mixed>> $elements
     * @param list<string> $macroStack
     * @return array{hasCreator:bool, hasYear:bool, unsupported:bool}
     */
    private function citationCollapseRenderingShape(array $elements, array $macroStack = []): array
    {
        $shape = ['hasCreator' => false, 'hasYear' => false, 'unsupported' => false];
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            $childShape = $this->citationCollapseRenderingElementShape($element, $macroStack);
            $shape['hasCreator'] = $shape['hasCreator'] || $childShape['hasCreator'];
            $shape['hasYear'] = $shape['hasYear'] || $childShape['hasYear'];
            $shape['unsupported'] = $shape['unsupported'] || $childShape['unsupported'];
        }

        return $shape;
    }

    /**
     * @param array<string, mixed> $element
     * @param list<string> $macroStack
     * @return array{hasCreator:bool, hasYear:bool, unsupported:bool}
     */
    private function citationCollapseRenderingElementShape(array $element, array $macroStack): array
    {
        $empty = ['hasCreator' => false, 'hasYear' => false, 'unsupported' => false];
        $type = (string) ($element['type'] ?? '');
        if ($type === 'group') {
            $children = $element['children'] ?? [];

            return is_array($children)
                ? $this->citationCollapseRenderingShape($children, $macroStack)
                : ['hasCreator' => false, 'hasYear' => false, 'unsupported' => true];
        }

        if ($type === 'names') {
            $variable = strtolower(trim((string) ($element['variable'] ?? 'author editor')));
            $variables = preg_split('/\s+/', $variable) ?: [];
            $hasCreator = in_array('author', $variables, true) || in_array('editor', $variables, true);

            return ['hasCreator' => $hasCreator, 'hasYear' => false, 'unsupported' => !$hasCreator];
        }

        if ($type === 'date') {
            $variable = strtolower(trim((string) ($element['variable'] ?? '')));
            $dateParts = is_array($element['dateParts'] ?? null) ? $element['dateParts'] : [];
            $hasYear = false;
            foreach ($dateParts as $datePart) {
                if (is_array($datePart) && strtolower(trim((string) ($datePart['name'] ?? ''))) === 'year') {
                    $hasYear = true;
                    break;
                }
            }

            return [
                'hasCreator' => false,
                'hasYear' => in_array($variable, ['issued', 'date'], true) && $hasYear,
                'unsupported' => !in_array($variable, ['issued', 'date'], true) || !$hasYear,
            ];
        }

        if ($type === 'text') {
            if (isset($element['macro']) && is_string($element['macro'])) {
                $name = $element['macro'];
                if (in_array($name, $macroStack, true)) {
                    return ['hasCreator' => false, 'hasYear' => false, 'unsupported' => true];
                }

                $macro = $this->style->macroRenderingElements($name);

                return is_array($macro)
                    ? $this->citationCollapseRenderingShape($macro, [...$macroStack, $name])
                    : ['hasCreator' => false, 'hasYear' => false, 'unsupported' => true];
            }

            $variable = strtolower(trim((string) ($element['variable'] ?? '')));
            if ($variable === 'year-suffix') {
                return $empty;
            }
        }

        return ['hasCreator' => false, 'hasYear' => false, 'unsupported' => true];
    }

    /**
     * @return array{citation:AstNode, author:string, year:string, yearBase:string, yearSuffix:string, prefix:string}|null
     */
    private function collapseableCitationEntry(AstNode $citation): ?array
    {
        $id = (string) $citation->attr('id', '');
        $item = $this->itemsById[$id] ?? null;
        if ($item === null || (string) $citation->attr('mode', 'normal') !== 'normal') {
            return null;
        }

        if ($this->citationSuffix($citation) !== '') {
            return null;
        }

        $item = $this->itemWithCitationContext($item, $citation);
        $withoutSuffix = $this->itemWithYearSuffix($item, '');

        return [
            'citation' => $citation,
            'author' => $this->citationAuthorLabel($item),
            'year' => $this->citationYear($item),
            'yearBase' => $this->citationYear($withoutSuffix),
            'yearSuffix' => (string) ($item['yearSuffix'] ?? ''),
            'prefix' => $this->citationPrefix($citation),
        ];
    }

    /**
     * @param array{author:string, yearBase:string} $left
     * @param array{author:string, yearBase:string} $right
     */
    private function citationCollapseEntriesMatch(array $left, array $right, string $mode): bool
    {
        if ((string) $left['author'] !== (string) $right['author']) {
            return false;
        }

        if ($mode === 'year') {
            return true;
        }

        return (string) $left['yearBase'] === (string) $right['yearBase'];
    }

    /**
     * @param list<string> $entries
     * @param list<array{citation:AstNode, author:string, year:string, yearBase:string, yearSuffix:string, prefix:string}> $run
     */
    private function appendCollapsedCitationRun(array &$entries, array $run, string $mode): void
    {
        if ($run === []) {
            return;
        }

        if (count($run) === 1) {
            $entries[] = $this->renderCitationEntry($run[0]['citation']);

            return;
        }

        $entries[] = $this->collapsedCitationRunText($run, $mode);
    }

    /**
     * @param list<array{author:string, year:string, yearBase:string, yearSuffix:string, prefix:string}> $run
     */
    private function collapsedCitationRunText(array $run, string $mode): string
    {
        $author = (string) $run[0]['author'];
        $prefix = (string) $run[0]['prefix'];
        if ($mode === 'year-suffix' || $mode === 'year-suffix-ranged') {
            $baseYear = (string) $run[0]['yearBase'];
            $suffixes = [];
            $allSameBase = true;
            $allHaveSuffixes = true;
            foreach ($run as $entry) {
                $allSameBase = $allSameBase && (string) $entry['yearBase'] === $baseYear;
                $suffix = (string) $entry['yearSuffix'];
                $allHaveSuffixes = $allHaveSuffixes && $suffix !== '';
                if (!in_array($suffix, $suffixes, true)) {
                    $suffixes[] = $suffix;
                }
            }

            if ($allSameBase && $allHaveSuffixes) {
                $entry = $author . ' ' . $baseYear . $this->collapsedYearSuffixes($suffixes, $mode === 'year-suffix-ranged');

                return $prefix === '' ? $entry : $prefix . ' ' . $entry;
            }
        }

        $years = [];
        foreach ($run as $entry) {
            $year = (string) $entry['year'];
            if (!in_array($year, $years, true)) {
                $years[] = $year;
            }
        }

        $entry = $author . ' ' . implode(', ', $years);

        return $prefix === '' ? $entry : $prefix . ' ' . $entry;
    }

    /**
     * @param list<string> $suffixes
     */
    private function collapsedYearSuffixes(array $suffixes, bool $ranged): string
    {
        if ($suffixes === []) {
            return '';
        }

        if ($ranged && count($suffixes) > 1 && $this->yearSuffixesAreSequentialSingleLetters($suffixes)) {
            return $suffixes[0] . '-' . $suffixes[count($suffixes) - 1];
        }

        return implode(',', $suffixes);
    }

    /**
     * @param list<string> $suffixes
     */
    private function yearSuffixesAreSequentialSingleLetters(array $suffixes): bool
    {
        $expected = null;
        foreach ($suffixes as $suffix) {
            if (preg_match('/^[a-z]$/', $suffix) !== 1) {
                return false;
            }

            $ordinal = ord($suffix);
            if ($expected === null) {
                $expected = $ordinal;
                continue;
            }

            if ($ordinal !== $expected + 1) {
                return false;
            }

            $expected = $ordinal;
        }

        return true;
    }

    private function renderCitationEntry(AstNode $citation): string
    {
        $id = (string) $citation->attr('id', '');
        $item = $this->itemsById[$id] ?? null;
        if ($item === null) {
            return $this->sourceCitationText($citation);
        }

        $item = $this->itemWithCitationContext($item, $citation);
        $customEntry = $this->renderCustomCitationEntry($citation, $item);
        if ($customEntry !== null) {
            return $customEntry;
        }

        $mode = (string) $citation->attr('mode', 'normal');
        $year = $this->citationYear($item);
        $author = $this->citationAuthorLabel($item);
        $suffix = $this->citationSuffix($citation);
        $prefix = $this->citationPrefix($citation);
        $citationLabel = $this->standaloneCitationLabel($item);

        if ($citationLabel !== '') {
            $entry = $citationLabel . ($suffix === '' ? '' : ', ' . $suffix);

            return $prefix === '' ? $entry : $prefix . ' ' . $entry;
        }

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
     * @return list<array{display:string, text:string}>
     */
    private function bibliographyDisplayParts(array $item): array
    {
        $parts = [];
        foreach ($this->style->bibliographyRenderingElements() as $element) {
            if (!is_array($element)) {
                continue;
            }

            $display = $this->renderingDisplay($element);
            if ($display === '') {
                continue;
            }

            $value = $this->renderRenderingElement($element, $item, 'bibliography');
            if ($value === '') {
                continue;
            }

            $parts[] = ['display' => $display, 'text' => $value];
        }

        return $parts;
    }

    /**
     * @param array<string, mixed> $element
     */
    private function renderingDisplay(array $element): string
    {
        $display = strtolower(trim((string) ($element['display'] ?? '')));

        return in_array($display, ['block', 'left-margin', 'right-inline', 'indent'], true)
            ? $display
            : '';
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

        $entry = $this->renderRenderingElements($elements, $item, 'citation', '', $citation);
        if ($entry === '') {
            return null;
        }

        $suffix = $this->citationSuffix($citation);
        if ($suffix !== '' && !$this->hasLocatorRenderingElement($elements)) {
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

            if ($type === 'choose') {
                foreach (($element['branches'] ?? []) as $branch) {
                    if (is_array($branch) && isset($branch['children']) && is_array($branch['children']) && $this->hasNonNameRenderingElement($branch['children'])) {
                        return true;
                    }
                }

                if (isset($element['else']) && is_array($element['else']) && $this->hasNonNameRenderingElement($element['else'])) {
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
        $citationLabel = $this->standaloneCitationLabel($item);
        if ($citationLabel !== '') {
            return $citationLabel;
        }

        return $this->citationAuthorLabel($item) . ' ' . $this->citationYear($item);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function standaloneCitationLabel(array $item): string
    {
        return trim((string) ($item['citationLabel'] ?? ''));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function citationAuthorLabel(array $item): string
    {
        $names = $item['shortAuthors'];
        if ($names === []) {
            $names = $item['authors'];
        }
        if ($names === []) {
            $names = $item['shortEditors'];
        }
        if ($names === []) {
            $names = $item['editors'];
        }
        if ($names === []) {
            $names = $item['translators'];
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
        $date = $item['issuedDate'] ?? null;
        if (is_array($date) && isset($date['rangeParts']) && is_array($date['rangeParts'])) {
            $yearRange = $this->citationYearRange($date['rangeParts']);
            if ($yearRange !== '') {
                return $this->appendYearSuffix($yearRange, $item);
            }
        }

        if (isset($item['issuedYear']) && $item['issuedYear'] !== null) {
            return $this->appendYearSuffix((string) $item['issuedYear'], $item);
        }

        if (is_array($date) && (string) ($date['literal'] ?? '') !== '') {
            return $this->appendYearSuffix((string) $date['literal'], $item);
        }

        return $this->appendYearSuffix($this->style->term('no date'), $item);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function appendYearSuffix(string $year, array $item): string
    {
        return $year . (string) ($item['yearSuffix'] ?? '');
    }

    /**
     * @param list<list<int>> $rangeParts
     */
    private function citationYearRange(array $rangeParts): string
    {
        if (count($rangeParts) < 2 || !isset($rangeParts[0][0], $rangeParts[1][0])) {
            return '';
        }

        $start = (int) $rangeParts[0][0];
        $end = (int) $rangeParts[1][0];

        return $start === $end ? (string) $start : $start . '/' . $end;
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
     * @param array<string, mixed> $item
     * @return list<string>
     */
    private function bibliographyRoleNameParts(array $item): array
    {
        $parts = [];
        $renderedRoleVariables = [];
        $editorialRoles = $item['editorialRoles'] ?? [];
        if (is_array($editorialRoles)) {
            foreach ($editorialRoles as $role) {
                if (!is_array($role) || !is_array($role['names'] ?? null) || $role['names'] === []) {
                    continue;
                }

                $parts[] = $this->editorialRoleBibliographyPart(
                    (string) ($role['type'] ?? ''),
                    (string) ($role['label'] ?? ''),
                    $role['names']
                );

                $variable = $this->editorialRoleNameVariable((string) ($role['type'] ?? ''));
                if ($variable !== null) {
                    $renderedRoleVariables[$variable] = true;
                }
            }
        }

        $roles = [
            ['compilers', 'Compiled by', 'compiler'],
            ['curators', 'Curated by', 'curator'],
            ['directors', 'Directed by', 'director'],
            ['editorialDirectors', 'Editorial direction by', 'editorial-director'],
            ['illustrators', 'Illustrated by', 'illustrator'],
            ['interviewers', 'Interview by', 'interviewer'],
            ['reviewedAuthors', 'Reviewed author:', 'reviewed-author'],
            ['commentators', 'Commentary by'],
            ['annotators', 'Annotated by'],
            ['introductionAuthors', 'Introduction by'],
            ['forewordAuthors', 'Foreword by'],
            ['afterwordAuthors', 'Afterword by'],
            ['originalAuthors', 'Original author:'],
        ];

        foreach ($roles as $role) {
            [$key, $label] = $role;
            $variable = $role[2] ?? null;
            if (is_string($variable) && isset($renderedRoleVariables[$variable])) {
                continue;
            }

            $names = $item[$key] ?? [];
            if (!is_array($names) || $names === []) {
                continue;
            }

            $parts[] = $label . ' ' . rtrim($this->renderNameList($names, $this->style->bibliographyNameRendering(), true), '.') . '.';
        }

        return $parts;
    }

    /**
     * @param list<array{family:string, given:string, literal:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool}> $names
     */
    private function editorialRoleBibliographyPart(string $type, string $label, array $names): string
    {
        $prefix = match ($this->normalizedEditorialRoleType($type)) {
            'editor' => 'Edited by',
            'compiler' => 'Compiled by',
            'curator' => 'Curated by',
            'director' => 'Directed by',
            'editorial-director' => 'Editorial direction by',
            'illustrator' => 'Illustrated by',
            'interviewer' => 'Interview by',
            'reviewed-author' => 'Reviewed author:',
            default => rtrim($label !== '' ? $label : self::editorialRoleDefaultLabel($type), ':') . ':',
        };

        return $prefix . ' ' . rtrim($this->renderNameList($names, $this->style->bibliographyNameRendering(), true), '.') . '.';
    }

    private function editorialRoleNameVariable(string $type): ?string
    {
        return match ($this->normalizedEditorialRoleType($type)) {
            'editor',
            'compiler',
            'curator',
            'director',
            'editorial-director',
            'illustrator',
            'interviewer',
            'reviewed-author' => $this->normalizedEditorialRoleType($type),
            default => null,
        };
    }

    private function normalizedEditorialRoleType(string $type): string
    {
        $type = strtolower(trim($type));
        if ($type === '') {
            return 'editor';
        }

        $type = str_replace(['_', ' '], '-', $type);

        return match ($type) {
            'editorialdirector', 'editorial-director' => 'editorial-director',
            'reviewedauthor', 'reviewed-author' => 'reviewed-author',
            default => $type,
        };
    }

    /**
     * @param array<string, mixed> $item
     * @return list<string>
     */
    private function legalPatentBibliographyParts(array $item): array
    {
        $type = (string) ($item['type'] ?? '');
        if (!in_array($type, ['patent', 'legislation', 'legal_case'], true)) {
            return [];
        }

        $parts = [];
        $genre = (string) ($item['genre'] ?? '');
        $number = (string) ($item['number'] ?? '');
        if ($genre !== '' || $number !== '') {
            $label = $genre !== '' ? ucfirst($genre) : ucfirst(str_replace('_', ' ', $type));
            $parts[] = trim($label . ' ' . $number) . '.';
        }

        $authority = (string) ($item['authority'] ?? '');
        if ($authority !== '') {
            $parts[] = 'Authority: ' . rtrim($authority, '.') . '.';
        }

        $jurisdiction = (string) ($item['jurisdiction'] ?? '');
        if ($jurisdiction !== '') {
            $parts[] = 'Jurisdiction: ' . rtrim($jurisdiction, '.') . '.';
        }

        $holders = $item['holders'] ?? [];
        if (is_array($holders) && $holders !== []) {
            $parts[] = 'Holder: ' . rtrim($this->renderNameList($holders, $this->style->bibliographyNameRendering(), true), '.') . '.';
        }

        $eventDate = $item['eventDate'] ?? null;
        if (is_array($eventDate) && (string) ($eventDate['display'] ?? '') !== '') {
            $parts[] = 'Event date ' . (string) $eventDate['display'] . '.';
        }

        $status = (string) ($item['status'] ?? '');
        if ($status !== '') {
            $parts[] = 'Status: ' . rtrim($status, '.') . '.';
        }

        return $parts;
    }

    /**
     * @param array<string, mixed> $item
     * @return list<string>
     */
    private function eventBibliographyParts(array $item): array
    {
        $eventTitle = trim((string) ($item['eventTitle'] ?? ''));
        $eventTitleAddon = trim((string) ($item['eventTitleAddon'] ?? ''));
        $eventPlace = trim((string) ($item['eventPlace'] ?? ''));
        $eventType = trim((string) ($item['eventType'] ?? ''));
        $eventDate = $item['eventDate'] ?? null;
        $eventOrganizers = $item['eventOrganizers'] ?? [];

        $legalType = in_array((string) ($item['type'] ?? ''), ['patent', 'legislation', 'legal_case'], true);
        if (
            $legalType
            && $eventTitle === ''
            && $eventTitleAddon === ''
            && $eventPlace === ''
            && $eventType === ''
            && (!is_array($eventOrganizers) || $eventOrganizers === [])
        ) {
            return [];
        }

        $parts = [];
        if ($eventTitle !== '') {
            $parts[] = 'Event: ' . $this->withTerminalPunctuation($eventTitle);
        }
        if ($eventTitleAddon !== '') {
            $parts[] = 'Event addendum: ' . $this->withTerminalPunctuation($eventTitleAddon);
        }
        if ($eventType !== '') {
            $parts[] = 'Event type: ' . $this->withTerminalPunctuation($eventType);
        }
        if (is_array($eventOrganizers) && $eventOrganizers !== []) {
            $parts[] = 'Event organizer: ' . rtrim($this->renderNameList($eventOrganizers, $this->style->bibliographyNameRendering(), true), '.') . '.';
        }
        if ($eventPlace !== '') {
            $parts[] = 'Event place: ' . $this->withTerminalPunctuation($eventPlace);
        }
        if (is_array($eventDate) && (string) ($eventDate['display'] ?? '') !== '') {
            $parts[] = 'Event date ' . (string) $eventDate['display'] . '.';
        }

        return $parts;
    }

    /**
     * @param array<string, mixed> $item
     * @return list<string>
     */
    private function publicationDetailBibliographyParts(array $item): array
    {
        $parts = [];
        $mainTitle = (string) ($item['mainTitle'] ?? '');
        if ($mainTitle !== '') {
            $parts[] = 'Main title: ' . rtrim($mainTitle, '.') . '.';
        }

        $mainTitleAddon = (string) ($item['mainTitleAddon'] ?? '');
        if ($mainTitleAddon !== '') {
            $parts[] = 'Main title addendum: ' . rtrim($mainTitleAddon, '.') . '.';
        }

        $volume = (string) ($item['volume'] ?? '');
        $issue = (string) ($item['issue'] ?? '');
        $numberOfVolumes = (string) ($item['numberOfVolumes'] ?? '');
        if ($volume !== '' || $issue !== '') {
            $details = [];
            if ($volume !== '') {
                $volumeDetail = ucfirst($this->style->term('volume', 'short')) . ' ' . $volume;
                if ($numberOfVolumes !== '') {
                    $volumeDetail .= ' of ' . $numberOfVolumes;
                }

                $details[] = $volumeDetail;
            }
            if ($issue !== '') {
                $details[] = $this->style->term('issue', 'short') . ' ' . $issue;
            }

            $parts[] = implode(', ', $details) . '.';
        } elseif ($numberOfVolumes !== '') {
            $parts[] = $numberOfVolumes . ' ' . $this->style->term('volume', 'short', true);
        }

        $edition = (string) ($item['edition'] ?? '');
        if ($edition !== '') {
            $parts[] = $edition . ' ' . $this->style->term('edition', 'short');
        }

        $collectionTitle = (string) ($item['collectionTitle'] ?? '');
        $collectionNumber = (string) ($item['collectionNumber'] ?? '');
        if ($collectionTitle !== '' && $collectionNumber !== '') {
            $parts[] = $collectionTitle . ', ' . $this->style->term('number', 'short') . ' ' . $collectionNumber . '.';
        } elseif ($collectionTitle !== '') {
            $parts[] = 'Series: ' . rtrim($collectionTitle, '.') . '.';
        } elseif ($collectionNumber !== '') {
            $parts[] = 'Series ' . $this->style->term('number', 'short') . ' ' . $collectionNumber . '.';
        }

        $part = (string) ($item['part'] ?? '');
        if ($part !== '') {
            $parts[] = 'Part ' . $part . '.';
        }

        $chapterNumber = (string) ($item['chapterNumber'] ?? '');
        if ($chapterNumber !== '') {
            $parts[] = ucfirst($this->style->term('chapter', 'short')) . ' ' . $chapterNumber . '.';
        }

        $numberOfPages = (string) ($item['numberOfPages'] ?? '');
        if ($numberOfPages !== '') {
            $parts[] = $numberOfPages . ' ' . $this->style->term('page', 'short', true);
        }

        return $parts;
    }

    /**
     * @param array<string, mixed> $item
     * @return list<string>
     */
    private function identifierBibliographyParts(array $item): array
    {
        $parts = [];
        $isbn = (string) ($item['isbn'] ?? '');
        if ($isbn !== '') {
            $parts[] = 'ISBN ' . $isbn . '.';
        }

        $issn = (string) ($item['issn'] ?? '');
        if ($issn !== '') {
            $parts[] = 'ISSN ' . $issn . '.';
        }

        $archive = array_values(array_filter([
            (string) ($item['archive'] ?? ''),
            (string) ($item['archivePlace'] ?? ''),
            (string) ($item['archiveLocation'] ?? ''),
        ], static fn (string $value): bool => $value !== ''));
        if ($archive !== []) {
            $parts[] = 'Archive: ' . implode(' ', $archive) . '.';
        }

        return $parts;
    }

    /**
     * @param array<string, mixed> $item
     * @return list<string>
     */
    private function reviewMetadataBibliographyParts(array $item): array
    {
        $parts = [];
        foreach ([
            ['version', 'Version'],
            ['medium', 'Medium'],
            ['status', 'Status'],
            ['note', 'Note'],
            ['addendum', 'Addendum'],
        ] as [$key, $label]) {
            if ($key === 'status' && in_array((string) ($item['type'] ?? ''), ['patent', 'legislation', 'legal_case'], true)) {
                continue;
            }

            $value = trim((string) ($item[$key] ?? ''));
            if ($value === '') {
                continue;
            }

            $parts[] = $label . ': ' . $this->withTerminalPunctuation($value);
        }

        return $parts;
    }

    /**
     * @param array<string, mixed> $item
     * @return list<string>
     */
    private function nameMetadataBibliographyParts(array $item): array
    {
        $parts = [];
        $nameAddon = trim((string) ($item['nameAddon'] ?? ''));
        if ($nameAddon !== '') {
            $parts[] = 'Name addendum: ' . $this->withTerminalPunctuation($nameAddon);
        }

        $annotations = $this->nameAnnotationSummary($item);
        if ($annotations !== '') {
            $parts[] = 'Name annotations: ' . $this->withTerminalPunctuation($annotations);
        }

        return $parts;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function nameAnnotationSummary(array $item): string
    {
        $entries = [];
        foreach ($this->nameAnnotationSources() as [$key, $label]) {
            $names = $item[$key] ?? [];
            if (!is_array($names)) {
                continue;
            }

            foreach ($names as $index => $name) {
                if (!is_array($name) || !is_array($name['annotations'] ?? null)) {
                    continue;
                }

                foreach ($name['annotations'] as $annotation) {
                    if (!is_array($annotation)) {
                        continue;
                    }

                    $value = trim((string) ($annotation['value'] ?? ''));
                    if ($value === '') {
                        continue;
                    }

                    $part = strtolower(trim((string) ($annotation['part'] ?? 'name')));
                    $entries[] = $label . ' ' . ((int) $index + 1) . ($part !== '' && $part !== 'name' ? ' ' . $part : '') . ': ' . $value;
                }
            }
        }

        return implode('; ', $entries);
    }

    /**
     * @return list<array{0:string, 1:string}>
     */
    private function nameAnnotationSources(): array
    {
        return [
            ['authors', 'Author'],
            ['editors', 'Editor'],
            ['holders', 'Holder'],
            ['translators', 'Translator'],
            ['eventOrganizers', 'Event organizer'],
            ['originalAuthors', 'Original author'],
            ['compilers', 'Compiler'],
            ['curators', 'Curator'],
            ['directors', 'Director'],
            ['editorialDirectors', 'Editorial director'],
            ['illustrators', 'Illustrator'],
            ['interviewers', 'Interviewer'],
            ['reviewedAuthors', 'Reviewed author'],
            ['commentators', 'Commentator'],
            ['annotators', 'Annotator'],
            ['introductionAuthors', 'Introduction'],
            ['forewordAuthors', 'Foreword'],
            ['afterwordAuthors', 'Afterword'],
        ];
    }

    private function withTerminalPunctuation(string $value): string
    {
        return preg_match('/[.!?]\z/u', $value) === 1 ? $value : $value . '.';
    }

    /**
     * @param list<array<string, mixed>> $elements
     * @param array<string, mixed> $item
     */
    private function renderRenderingElements(array $elements, array $item, string $scope, string $delimiter, ?AstNode $citation = null): string
    {
        $rendered = [];
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            $value = $this->renderRenderingElement($element, $item, $scope, [], $citation);
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
    private function renderRenderingElement(array $element, array $item, string $scope, array $macroStack = [], ?AstNode $citation = null): string
    {
        $type = (string) ($element['type'] ?? '');
        $value = match ($type) {
            'group' => $this->renderGroupElement($element, $item, $scope, $macroStack, $citation),
            'text' => $this->renderTextElement($element, $item, $scope, $macroStack, $citation),
            'date' => $this->renderDateElement($element, $item, $scope),
            'number' => $this->renderNumberElement($element, $item, $scope, $citation),
            'names' => $this->renderNamesElement($element, $item, $scope),
            'label' => $this->renderLabelElement($element, $item, $scope, $citation),
            'choose' => $this->renderChooseElement($element, $item, $scope, $macroStack, $citation),
            default => '',
        };

        $value = $this->stripRenderingPeriods($value, $element);
        $value = $this->applyTextCase($value, $element, $item);
        $value = $this->applyRenderingQuotes($value, $element);

        return $this->applyRenderingAffixes($value, $element);
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     */
    private function renderGroupElement(array $element, array $item, string $scope, array $macroStack = [], ?AstNode $citation = null): string
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
            $value = $this->renderRenderingElement($child, $item, $scope, $macroStack, $citation);
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
        if ($type === 'date' || $type === 'number' || $type === 'names' || $type === 'label') {
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

        if ($type === 'choose') {
            foreach (($element['branches'] ?? []) as $branch) {
                if (is_array($branch) && isset($branch['children']) && is_array($branch['children']) && $this->hasVariableRenderingElement($branch['children'])) {
                    return true;
                }
            }

            return isset($element['else']) && is_array($element['else']) && $this->hasVariableRenderingElement($element['else']);
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
     * @param list<array<string, mixed>> $elements
     */
    private function hasLocatorRenderingElement(array $elements): bool
    {
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            $type = (string) ($element['type'] ?? '');
            if (($type === 'text' || $type === 'label') && strtolower(trim((string) ($element['variable'] ?? ''))) === 'locator') {
                return true;
            }

            if ($type === 'text' && isset($element['macro']) && is_string($element['macro'])) {
                $macro = $this->style->macroRenderingElements($element['macro']);
                if (is_array($macro) && $this->hasLocatorRenderingElement($macro)) {
                    return true;
                }
            }

            if ($type === 'group' && isset($element['children']) && is_array($element['children']) && $this->hasLocatorRenderingElement($element['children'])) {
                return true;
            }

            if ($type === 'choose') {
                foreach (($element['branches'] ?? []) as $branch) {
                    if (is_array($branch) && isset($branch['children']) && is_array($branch['children']) && $this->hasLocatorRenderingElement($branch['children'])) {
                        return true;
                    }
                }

                if (isset($element['else']) && is_array($element['else']) && $this->hasLocatorRenderingElement($element['else'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     */
    private function renderTextElement(array $element, array $item, string $scope, array $macroStack = [], ?AstNode $citation = null): string
    {
        if (array_key_exists('macro', $element)) {
            return $this->renderMacroReference((string) $element['macro'], $item, $scope, $macroStack, $citation);
        }

        if (array_key_exists('variable', $element)) {
            return $this->renderVariableValue($item, (string) $element['variable'], $scope, $citation);
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
    private function renderMacroReference(string $name, array $item, string $scope, array $macroStack, ?AstNode $citation = null): string
    {
        $elements = $this->style->macroRenderingElements($name);
        if ($elements === null) {
            throw new \InvalidArgumentException('CSL references undefined macro: ' . $name);
        }

        if (in_array($name, $macroStack, true)) {
            throw new \InvalidArgumentException('CSL macro recursion detected: ' . implode(' -> ', [...$macroStack, $name]));
        }

        return $this->renderRenderingElementsWithMacroStack($elements, $item, $scope, '', [...$macroStack, $name], $citation);
    }

    /**
     * @param list<array<string, mixed>> $elements
     * @param array<string, mixed> $item
     * @param list<string> $macroStack
     */
    private function renderRenderingElementsWithMacroStack(array $elements, array $item, string $scope, string $delimiter, array $macroStack, ?AstNode $citation = null): string
    {
        $rendered = [];
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            $value = $this->renderRenderingElement($element, $item, $scope, $macroStack, $citation);
            if ($value !== '') {
                $rendered[] = $value;
            }
        }

        return $this->joinRenderedElements($rendered, $delimiter);
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     * @param list<string> $macroStack
     */
    private function renderChooseElement(array $element, array $item, string $scope, array $macroStack, ?AstNode $citation = null): string
    {
        foreach (($element['branches'] ?? []) as $branch) {
            if (!is_array($branch) || !$this->chooseBranchMatches($branch, $item, $scope, $citation)) {
                continue;
            }

            $children = $branch['children'] ?? [];

            return is_array($children)
                ? $this->renderRenderingElementsWithMacroStack($children, $item, $scope, '', $macroStack, $citation)
                : '';
        }

        $else = $element['else'] ?? [];

        return is_array($else)
            ? $this->renderRenderingElementsWithMacroStack($else, $item, $scope, '', $macroStack, $citation)
            : '';
    }

    /**
     * @param array<string, mixed> $branch
     * @param array<string, mixed> $item
     */
    private function chooseBranchMatches(array $branch, array $item, string $scope, ?AstNode $citation = null): bool
    {
        $conditions = [];
        $variables = $branch['variables'] ?? [];
        if (is_array($variables)) {
            foreach ($variables as $variable) {
                if (is_scalar($variable)) {
                    $conditions[] = $this->renderingVariableIsPresent($item, (string) $variable, $scope, $citation);
                }
            }
        }

        $types = $branch['types'] ?? [];
        if (is_array($types) && $types !== []) {
            $normalizedTypes = array_map(static fn (mixed $type): string => strtolower(trim((string) $type)), $types);
            $conditions[] = in_array(strtolower((string) ($item['type'] ?? '')), $normalizedTypes, true);
        }

        $positions = $branch['positions'] ?? [];
        if (is_array($positions)) {
            foreach ($positions as $position) {
                if (is_scalar($position)) {
                    $conditions[] = $this->citationPositionMatches((string) $position, $scope, $citation);
                }
            }
        }

        if ($conditions === []) {
            return false;
        }

        return match ((string) ($branch['match'] ?? 'all')) {
            'any' => in_array(true, $conditions, true),
            'none' => !in_array(true, $conditions, true),
            default => !in_array(false, $conditions, true),
        };
    }

    private function citationPositionMatches(string $position, string $scope, ?AstNode $citation): bool
    {
        if ($scope !== 'citation' || !$citation instanceof AstNode) {
            return false;
        }

        $position = strtolower(trim($position));
        $tests = $citation->attr('cslPositionTests', []);
        if (is_array($tests)) {
            $normalized = array_map(static fn (mixed $test): string => strtolower(trim((string) $test)), $tests);

            return in_array($position, $normalized, true);
        }

        $actual = strtolower(trim((string) $citation->attr('cslPosition', '')));
        if ($actual === '') {
            return false;
        }

        if ($position === 'subsequent' && in_array($actual, ['subsequent', 'ibid', 'ibid-with-locator'], true)) {
            return true;
        }

        if ($position === 'ibid' && $actual === 'ibid-with-locator') {
            return true;
        }

        return $actual === $position;
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
            return $this->renderSelectedDateParts(
                $date,
                $dateParts,
                $scope,
                $variable,
                (string) ($element['delimiter'] ?? ''),
                $item
            );
        }

        return $this->renderDateVariable($date, $scope, $variable);
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     */
    private function renderNamesElement(array $element, array $item, string $scope): string
    {
        return $this->renderNamesElementValue($element, $item, $scope, true);
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     */
    private function renderNamesElementValue(array $element, array $item, string $scope, bool $allowImplicitTitleFallback): string
    {
        $variable = (string) ($element['variable'] ?? 'author editor');
        $names = $this->namesForRenderingVariable($item, $variable);
        if ($names === []) {
            $substitute = $element['substitute'] ?? [];
            if (is_array($substitute) && $substitute !== []) {
                foreach ($substitute as $substituteElement) {
                    if (!is_array($substituteElement)) {
                        continue;
                    }

                    $value = ((string) ($substituteElement['type'] ?? '')) === 'names'
                        ? $this->renderNamesElementValue($substituteElement, $item, $scope, false)
                        : $this->renderRenderingElement($substituteElement, $item, $scope);
                    if ($value !== '') {
                        return $value;
                    }
                }
            }

            if ($allowImplicitTitleFallback && $scope === 'citation' && $this->namesVariableAllowsTitleFallback($variable)) {
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

    private function namesVariableAllowsTitleFallback(string $variable): bool
    {
        $variables = preg_split('/\s+/', strtolower(trim($variable))) ?: [];
        if ($variables === [] || $variables === ['']) {
            return true;
        }

        foreach ($variables as $nameVariable) {
            if (in_array($nameVariable, ['author', 'editor'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     */
    private function renderNumberElement(array $element, array $item, string $scope, ?AstNode $citation = null): string
    {
        $variable = strtolower(trim((string) ($element['variable'] ?? '')));
        if ($variable === '') {
            return '';
        }

        $value = $this->renderVariableValue($item, $variable, $scope, $citation);
        if ($value === '') {
            return '';
        }

        return $this->formatCslNumber($value, (string) ($element['form'] ?? 'numeric'));
    }

    private function formatCslNumber(string $value, string $form): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        if ($value === '' || !$this->cslNumberValueIsNumeric($value)) {
            return $value;
        }

        preg_match_all('/\d+|[-\x{2010}-\x{2015}]|,|&/u', $value, $matches);
        $tokens = $matches[0] ?? [];
        $rendered = '';
        foreach ($tokens as $token) {
            if (preg_match('/^\d+$/', $token) === 1) {
                $rendered .= $this->formatCslNumberToken((int) $token, $form);
                continue;
            }

            $rendered .= match ($token) {
                ',' => ', ',
                '&' => ' & ',
                default => '-',
            };
        }

        return trim(preg_replace('/\s+/', ' ', $rendered) ?? $rendered);
    }

    private function cslNumberValueIsNumeric(string $value): bool
    {
        return preg_match('/^\d+\s*(?:(?:[-\x{2010}-\x{2015}]|,|&)\s*\d+\s*)*$/u', $value) === 1;
    }

    private function formatCslNumberToken(int $number, string $form): string
    {
        $numeric = (string) $number;

        return match ($form) {
            'ordinal' => $numeric . $this->ordinalSuffix($number),
            'long-ordinal' => $this->longOrdinalNumber($number),
            'roman' => $this->romanNumber($number) ?? $numeric,
            default => $numeric,
        };
    }

    private function ordinalSuffix(int $number): string
    {
        $lastTwo = abs($number) % 100;
        if ($lastTwo >= 11 && $lastTwo <= 13) {
            return $this->style->termOrNull('ordinal-' . sprintf('%02d', $lastTwo))
                ?? $this->style->termOrNull('ordinal')
                ?? 'th';
        }

        $lastDigit = abs($number) % 10;

        return $this->style->termOrNull('ordinal-' . sprintf('%02d', $lastDigit))
            ?? $this->style->termOrNull('ordinal')
            ?? 'th';
    }

    private function longOrdinalNumber(int $number): string
    {
        if ($number >= 1 && $number <= 10) {
            $term = $this->style->termOrNull('long-ordinal-' . sprintf('%02d', $number));
            if ($term !== null) {
                return $term;
            }
        }

        return (string) $number . $this->ordinalSuffix($number);
    }

    private function romanNumber(int $number): ?string
    {
        if ($number < 1 || $number > 3999) {
            return null;
        }

        $map = [
            1000 => 'm',
            900 => 'cm',
            500 => 'd',
            400 => 'cd',
            100 => 'c',
            90 => 'xc',
            50 => 'l',
            40 => 'xl',
            10 => 'x',
            9 => 'ix',
            5 => 'v',
            4 => 'iv',
            1 => 'i',
        ];

        $roman = '';
        foreach ($map as $value => $glyph) {
            while ($number >= $value) {
                $roman .= $glyph;
                $number -= $value;
            }
        }

        return $roman;
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     */
    private function renderLabelElement(array $element, array $item, string $scope, ?AstNode $citation = null): string
    {
        $variable = strtolower(trim((string) ($element['variable'] ?? '')));
        if ($variable === '') {
            return '';
        }

        if ($variable === 'locator') {
            $parts = $this->citationLocatorParts($citation);
            if ($parts['value'] === '') {
                return '';
            }

            $termName = $parts['label'];
            $value = $parts['value'];
        } else {
            $value = $this->renderVariableValue($item, $variable, $scope, $citation);
            if ($value === '') {
                return '';
            }

            $termName = $this->labelTermName($variable);
        }

        $plural = match ((string) ($element['plural'] ?? 'contextual')) {
            'always' => true,
            'never' => false,
            default => $this->labelValueLooksPlural($value),
        };

        return $this->style->term($termName, (string) ($element['form'] ?? 'long'), $plural);
    }

    private function labelTermName(string $variable): string
    {
        return match ($variable) {
            'number-of-pages' => 'page',
            'number-of-volumes' => 'volume',
            'chapter-number' => 'chapter',
            'collection-number' => 'number',
            default => $variable,
        };
    }

    private function labelValueLooksPlural(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        if (preg_match('/\d\s*(?:[-\x{2010}-\x{2015}]|&|,|and)\s*\d/iu', $value) === 1) {
            return true;
        }

        return preg_match('/\band\b/iu', $value) === 1;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
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
            'delimiterPrecedesEtAl' => is_string($options['delimiterPrecedesEtAl'] ?? null) ? $options['delimiterPrecedesEtAl'] : $defaults['delimiterPrecedesEtAl'],
            'etAl' => $this->normalizedEtAlRenderingOptions(
                is_array($defaults['etAl'] ?? null) ? $defaults['etAl'] : [],
                is_array($options['etAl'] ?? null) ? $options['etAl'] : []
            ),
            'initializeWith' => is_string($options['initializeWith'] ?? null) ? $options['initializeWith'] : $defaults['initializeWith'],
            'nameAsSortOrder' => is_string($options['nameAsSortOrder'] ?? null) ? $options['nameAsSortOrder'] : $defaults['nameAsSortOrder'],
            'nameParts' => array_key_exists('nameParts', $options) && is_array($options['nameParts']) ? $options['nameParts'] : [],
        ];
    }

    /**
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $overrides
     * @return array{term:string, prefix:string, suffix:string, textCase:string, stripPeriods:bool, quotes:bool}
     */
    private function normalizedEtAlRenderingOptions(array $defaults, array $overrides): array
    {
        return [
            'term' => is_string($overrides['term'] ?? null) ? $overrides['term'] : (is_string($defaults['term'] ?? null) ? $defaults['term'] : 'et-al'),
            'prefix' => is_string($overrides['prefix'] ?? null) ? $overrides['prefix'] : (is_string($defaults['prefix'] ?? null) ? $defaults['prefix'] : ''),
            'suffix' => is_string($overrides['suffix'] ?? null) ? $overrides['suffix'] : (is_string($defaults['suffix'] ?? null) ? $defaults['suffix'] : ''),
            'textCase' => is_string($overrides['textCase'] ?? null) ? $overrides['textCase'] : (is_string($defaults['textCase'] ?? null) ? $defaults['textCase'] : ''),
            'stripPeriods' => is_bool($overrides['stripPeriods'] ?? null) ? $overrides['stripPeriods'] : (is_bool($defaults['stripPeriods'] ?? null) ? $defaults['stripPeriods'] : false),
            'quotes' => is_bool($overrides['quotes'] ?? null) ? $overrides['quotes'] : (is_bool($defaults['quotes'] ?? null) ? $defaults['quotes'] : false),
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
     * @param array<string, mixed> $element
     */
    private function stripRenderingPeriods(string $value, array $element): string
    {
        if ($value === '' || ($element['stripPeriods'] ?? false) !== true) {
            return $value;
        }

        return str_replace('.', '', $value);
    }

    /**
     * @param array<string, mixed> $element
     */
    private function applyRenderingQuotes(string $value, array $element): string
    {
        if ($value === '' || ($element['quotes'] ?? false) !== true) {
            return $value;
        }

        return $this->style->term('open-quote') . $value . $this->style->term('close-quote');
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     */
    private function applyTextCase(string $value, array $element, array $item): string
    {
        if ($value === '') {
            return '';
        }

        $textCase = strtolower(trim((string) ($element['textCase'] ?? '')));

        return match ($textCase) {
            'lowercase' => mb_strtolower($value, 'UTF-8'),
            'uppercase' => mb_strtoupper($value, 'UTF-8'),
            'capitalize-first' => $this->capitalizeFirstLowercaseWord($value),
            'capitalize-all' => $this->capitalizeAllLowercaseWords($value),
            'sentence' => $this->sentenceCaseText($value),
            'title' => $this->titleCaseText($value, $item),
            default => $value,
        };
    }

    private function capitalizeFirstLowercaseWord(string $value): string
    {
        return preg_replace_callback(
            '/[\p{L}\p{N}][\p{L}\p{M}\p{N}\']*/u',
            fn (array $matches): string => $this->wordIsLowercase($matches[0])
                ? $this->capitalizeWord($matches[0])
                : $matches[0],
            $value,
            1
        ) ?? $value;
    }

    private function capitalizeAllLowercaseWords(string $value): string
    {
        return preg_replace_callback(
            '/[\p{L}\p{N}][\p{L}\p{M}\p{N}\']*/u',
            fn (array $matches): string => $this->wordIsLowercase($matches[0])
                ? $this->capitalizeWord($matches[0])
                : $matches[0],
            $value
        ) ?? $value;
    }

    private function sentenceCaseText(string $value): string
    {
        if ($this->textIsUppercase($value)) {
            return $this->capitalizeWord(mb_strtolower($value, 'UTF-8'));
        }

        return $this->capitalizeFirstLowercaseWord($value);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function titleCaseText(string $value, array $item): string
    {
        if (!$this->titleCaseAppliesToItem($item)) {
            return $value;
        }

        $uppercase = $this->textIsUppercase($value);
        $source = $uppercase ? mb_strtolower($value, 'UTF-8') : $value;
        $matchCount = preg_match_all('/[\p{L}\p{N}][\p{L}\p{M}\p{N}\']*/u', $source, $matches, PREG_OFFSET_CAPTURE);
        if ($matchCount === false || $matchCount === 0) {
            return $source;
        }

        $words = $matches[0];
        $output = '';
        $offset = 0;
        $count = count($words);
        foreach ($words as $index => $match) {
            $word = (string) $match[0];
            $wordOffset = (int) $match[1];
            $separator = substr($source, $offset, $wordOffset - $offset);
            $output .= $separator;

            $isFirst = $index === 0;
            $isLast = $index === $count - 1;
            $followsColon = str_contains($separator, ':');
            $lowerWord = mb_strtolower($word, 'UTF-8');
            if (!$isFirst && !$isLast && !$followsColon && $this->isEnglishTitleStopWord($lowerWord)) {
                $output .= $lowerWord;
            } elseif ($uppercase || $this->wordIsLowercase($word)) {
                $output .= $this->capitalizeWord($lowerWord);
            } else {
                $output .= $word;
            }

            $offset = $wordOffset + strlen($word);
        }

        return $output . substr($source, $offset);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function titleCaseAppliesToItem(array $item): bool
    {
        $language = strtolower(trim((string) ($item['language'] ?? '')));
        if ($language !== '') {
            return str_starts_with($language, 'en');
        }

        $defaultLocale = strtolower(trim($this->style->defaultLocale()));

        return $defaultLocale === '' || str_starts_with($defaultLocale, 'en');
    }

    private function isEnglishTitleStopWord(string $word): bool
    {
        return in_array($word, [
            'a',
            'an',
            'and',
            'as',
            'at',
            'but',
            'by',
            'for',
            'from',
            'in',
            'into',
            'nor',
            'of',
            'on',
            'or',
            'over',
            'so',
            'the',
            'to',
            'up',
            'v',
            'via',
            'vs',
            'with',
            'yet',
        ], true);
    }

    private function wordIsLowercase(string $word): bool
    {
        return $word === mb_strtolower($word, 'UTF-8') && $word !== mb_strtoupper($word, 'UTF-8');
    }

    private function textIsUppercase(string $value): bool
    {
        return $value === mb_strtoupper($value, 'UTF-8') && $value !== mb_strtolower($value, 'UTF-8');
    }

    private function capitalizeWord(string $word): string
    {
        return preg_replace_callback(
            '/\p{L}/u',
            static fn (array $matches): string => mb_strtoupper($matches[0], 'UTF-8'),
            $word,
            1
        ) ?? $word;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function renderVariableValue(array $item, string $variable, string $scope, ?AstNode $citation = null): string
    {
        $normalized = strtolower(trim($variable));

        return match ($normalized) {
            'locator' => $this->citationLocatorParts($citation)['value'],
            'citation-number' => $this->citationNumberValue($item, $citation),
            'id', 'citation-key' => (string) $item['id'],
            'type' => (string) $item['type'],
            'citation-aliases', 'citation-alias' => implode(', ', is_array($item['citationAliases'] ?? null) ? $item['citationAliases'] : []),
            'citation-label' => (string) $item['citationLabel'],
            'shorthand' => (string) $item['shorthand'],
            'shorthand-intro' => (string) $item['shorthandIntro'],
            'title' => (string) $item['title'],
            'short-title' => (string) $item['shortTitle'],
            'title-addon' => (string) $item['titleAddon'],
            'container-title' => (string) $item['containerTitle'],
            'container-title-addon' => (string) $item['containerTitleAddon'],
            'main-title' => (string) $item['mainTitle'],
            'main-title-addon' => (string) $item['mainTitleAddon'],
            'event', 'event-title' => (string) $item['eventTitle'],
            'event-title-addon' => (string) $item['eventTitleAddon'],
            'event-place' => (string) $item['eventPlace'],
            'event-type' => (string) $item['eventType'],
            'publisher' => (string) $item['publisher'],
            'publisher-place' => (string) $item['publisherPlace'],
            'page' => (string) $item['page'],
            'number' => (string) $item['number'],
            'volume' => (string) $item['volume'],
            'issue' => (string) $item['issue'],
            'edition' => (string) $item['edition'],
            'collection-title' => (string) $item['collectionTitle'],
            'collection-number' => (string) $item['collectionNumber'],
            'number-of-volumes' => (string) $item['numberOfVolumes'],
            'number-of-pages' => (string) $item['numberOfPages'],
            'chapter-number' => (string) $item['chapterNumber'],
            'part' => (string) $item['part'],
            'genre' => (string) $item['genre'],
            'authority' => (string) $item['authority'],
            'jurisdiction' => (string) $item['jurisdiction'],
            'status' => (string) $item['status'],
            'version' => (string) $item['version'],
            'doi' => (string) $item['doi'],
            'url' => (string) $item['url'],
            'isbn' => (string) $item['isbn'],
            'issn' => (string) $item['issn'],
            'archive' => (string) $item['archive'],
            'archive-place' => (string) $item['archivePlace'],
            'archive_location', 'archive-location' => (string) $item['archiveLocation'],
            'language' => (string) $item['language'],
            'abstract' => (string) $item['abstract'],
            'medium' => (string) $item['medium'],
            'note' => (string) $item['note'],
            'addendum' => (string) $item['addendum'],
            'name-addon' => (string) $item['nameAddon'],
            'name-annotation-summary' => $this->nameAnnotationSummary($item),
            'keyword' => implode(', ', is_array($item['keywords'] ?? null) ? $item['keywords'] : []),
            'issued', 'date' => $this->renderDateVariable($item['issuedDate'] ?? null, $scope, 'issued'),
            'year-suffix' => (string) ($item['yearSuffix'] ?? ($citation instanceof AstNode ? $citation->attr('cslYearSuffix', '') : '')),
            'event-date' => $this->renderDateVariable($item['eventDate'] ?? null, $scope, 'event-date'),
            'accessed' => $this->renderDateVariable($item['accessedDate'] ?? null, $scope, 'accessed'),
            'short-author' => $this->renderNamesElement(['variable' => 'short-author'], $item, $scope),
            'short-editor' => $this->renderNamesElement(['variable' => 'short-editor'], $item, $scope),
            'author' => $this->renderNamesElement(['variable' => 'author'], $item, $scope),
            'editor' => $this->renderNamesElement(['variable' => 'editor'], $item, $scope),
            'holder' => $this->renderNamesElement(['variable' => 'holder'], $item, $scope),
            'translator' => $this->renderNamesElement(['variable' => 'translator'], $item, $scope),
            'event-organizer', 'organizer' => $this->renderNamesElement(['variable' => 'event-organizer'], $item, $scope),
            'original-author' => $this->renderNamesElement(['variable' => 'original-author'], $item, $scope),
            'compiler' => $this->renderNamesElement(['variable' => 'compiler'], $item, $scope),
            'curator' => $this->renderNamesElement(['variable' => 'curator'], $item, $scope),
            'director' => $this->renderNamesElement(['variable' => 'director'], $item, $scope),
            'editorial-director' => $this->renderNamesElement(['variable' => 'editorial-director'], $item, $scope),
            'illustrator' => $this->renderNamesElement(['variable' => 'illustrator'], $item, $scope),
            'interviewer' => $this->renderNamesElement(['variable' => 'interviewer'], $item, $scope),
            'reviewed-author' => $this->renderNamesElement(['variable' => 'reviewed-author'], $item, $scope),
            'commentator' => $this->renderNamesElement(['variable' => 'commentator'], $item, $scope),
            'annotator' => $this->renderNamesElement(['variable' => 'annotator'], $item, $scope),
            'introduction' => $this->renderNamesElement(['variable' => 'introduction'], $item, $scope),
            'foreword' => $this->renderNamesElement(['variable' => 'foreword'], $item, $scope),
            'afterword' => $this->renderNamesElement(['variable' => 'afterword'], $item, $scope),
            'editorial-role-summary' => implode(' ', $this->bibliographyRoleNameParts($item)),
            default => $this->rawVariableValue($item, $variable),
        };
    }

    /**
     * @param array<string, mixed> $item
     */
    private function citationNumberValue(array $item, ?AstNode $citation): string
    {
        if ($citation instanceof AstNode) {
            $number = trim((string) $citation->attr('cslCitationNumber', ''));
            if ($number !== '') {
                return $number;
            }
        }

        $number = trim((string) ($item['citationNumber'] ?? ''));
        if ($number !== '') {
            return $number;
        }

        return $this->citationNumberForId((string) ($item['id'] ?? ''));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function renderingVariableIsPresent(array $item, string $variable, string $scope, ?AstNode $citation = null): bool
    {
        $normalized = strtolower(trim($variable));
        if ($normalized === '') {
            return false;
        }

        if ($normalized === 'locator') {
            return $this->citationLocatorParts($citation)['value'] !== '';
        }

        if ($normalized === 'citation-number') {
            return $this->citationNumberValue($item, $citation) !== '';
        }

        if ($normalized === 'year-suffix') {
            return $this->renderVariableValue($item, $variable, $scope, $citation) !== '';
        }

        if (in_array($normalized, ['short-author', 'short-editor', 'author', 'editor', 'holder', 'translator', 'event-organizer', 'organizer', 'original-author', 'compiler', 'curator', 'director', 'editorial-director', 'illustrator', 'interviewer', 'reviewed-author', 'commentator', 'annotator', 'introduction', 'foreword', 'afterword'], true)) {
            return $this->namesForRenderingVariable($item, $normalized) !== [];
        }

        if (in_array($normalized, ['issued', 'date', 'accessed', 'event-date', 'original-date'], true)) {
            $date = $this->dateVariableForRendering($item, $normalized);
            if (!is_array($date)) {
                return false;
            }

            return (isset($date['parts']) && is_array($date['parts']) && $date['parts'] !== [])
                || (string) ($date['display'] ?? '') !== ''
                || (string) ($date['literal'] ?? '') !== '';
        }

        return $this->renderVariableValue($item, $variable, $scope, $citation) !== '';
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
                'short-author' => $item['shortAuthors'] ?? [],
                'short-editor' => $item['shortEditors'] ?? [],
                'author' => $item['authors'] ?? [],
                'editor' => $item['editors'] ?? [],
                'holder' => $item['holders'] ?? [],
                'translator' => $item['translators'] ?? [],
                'event-organizer', 'organizer' => $item['eventOrganizers'] ?? [],
                'original-author' => $item['originalAuthors'] ?? [],
                'compiler' => $item['compilers'] ?? [],
                'curator' => $item['curators'] ?? [],
                'director' => $item['directors'] ?? [],
                'editorial-director' => $item['editorialDirectors'] ?? [],
                'illustrator' => $item['illustrators'] ?? [],
                'interviewer' => $item['interviewers'] ?? [],
                'reviewed-author' => $item['reviewedAuthors'] ?? [],
                'commentator' => $item['commentators'] ?? [],
                'annotator' => $item['annotators'] ?? [],
                'introduction' => $item['introductionAuthors'] ?? [],
                'foreword' => $item['forewordAuthors'] ?? [],
                'afterword' => $item['afterwordAuthors'] ?? [],
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
     * @return array{year:?int, parts:list<int>, display:string, literal:string, rangeParts?:list<list<int>>}|null
     */
    private function dateVariableForRendering(array $item, string $variable): ?array
    {
        $normalized = strtolower(trim($variable));

        return match ($normalized) {
            'issued', 'date' => is_array($item['issuedDate'] ?? null) ? $item['issuedDate'] : null,
            'accessed' => is_array($item['accessedDate'] ?? null) ? $item['accessedDate'] : null,
            'event-date' => is_array($item['eventDate'] ?? null) ? $item['eventDate'] : null,
            'original-date' => is_array($item['originalDate'] ?? null) ? $item['originalDate'] : null,
            default => null,
        };
    }

    /**
     * @param array{year:?int, parts:list<int>, display:string, literal:string, rangeParts?:list<list<int>>}|mixed $date
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
     * @param array{year:?int, parts:list<int>, display:string, literal:string, rangeParts?:list<list<int>>} $date
     * @param list<array{name:string, prefix:string, suffix:string, form:string, rangeDelimiter:string, stripPeriods:bool, textCase:string}|string> $dateParts
     * @param array<string, mixed> $item
     */
    private function renderSelectedDateParts(array $date, array $dateParts, string $scope, string $variable, string $delimiter, array $item): string
    {
        $rangeParts = is_array($date['rangeParts'] ?? null) ? $date['rangeParts'] : [];
        $singleParts = is_array($date['parts'] ?? null) ? $date['parts'] : [];
        if ($rangeParts === [] && $singleParts === []) {
            return $this->renderDateVariable($date, $scope, $variable);
        }

        $specs = $this->normalizedDatePartRenderingSpecs($dateParts);
        if ($specs === []) {
            return $this->renderDateVariable($date, $scope, $variable);
        }

        $parts = $rangeParts !== [] ? $rangeParts : [$singleParts];
        $values = [];
        foreach ($parts as $rangePart) {
            if (!is_array($rangePart)) {
                continue;
            }

            $value = $this->renderDatePartSequence($rangePart, $specs, $delimiter, $item);
            if ($value !== '') {
                $values[] = $value;
            }
        }

        if ($values === []) {
            return '';
        }

        $values = array_values(array_unique($values));

        return implode($this->dateRangeDelimiter($parts, $specs), $values);
    }

    /**
     * @param list<array{name:string, prefix:string, suffix:string, form:string, rangeDelimiter:string, stripPeriods:bool, textCase:string}|string> $dateParts
     * @return list<array{name:string, prefix:string, suffix:string, form:string, rangeDelimiter:string, stripPeriods:bool, textCase:string}>
     */
    private function normalizedDatePartRenderingSpecs(array $dateParts): array
    {
        $specs = [];
        foreach ($dateParts as $part) {
            if (is_string($part)) {
                $name = strtolower(trim($part));
                if (!in_array($name, ['year', 'month', 'day'], true)) {
                    continue;
                }

                $specs[] = [
                    'name' => $name,
                    'prefix' => '',
                    'suffix' => '',
                    'form' => '',
                    'rangeDelimiter' => '',
                    'stripPeriods' => false,
                    'textCase' => '',
                ];
                continue;
            }

            if (!is_array($part)) {
                continue;
            }

            $name = strtolower(trim((string) ($part['name'] ?? '')));
            if (!in_array($name, ['year', 'month', 'day'], true)) {
                continue;
            }

            $specs[] = [
                'name' => $name,
                'prefix' => (string) ($part['prefix'] ?? ''),
                'suffix' => (string) ($part['suffix'] ?? ''),
                'form' => strtolower(trim((string) ($part['form'] ?? ''))),
                'rangeDelimiter' => (string) ($part['rangeDelimiter'] ?? ''),
                'stripPeriods' => ($part['stripPeriods'] ?? false) === true,
                'textCase' => strtolower(trim((string) ($part['textCase'] ?? ''))),
            ];
        }

        return $specs;
    }

    /**
     * @param list<int> $parts
     * @param list<array{name:string, prefix:string, suffix:string, form:string, rangeDelimiter:string, stripPeriods:bool, textCase:string}> $specs
     * @param array<string, mixed> $item
     */
    private function renderDatePartSequence(array $parts, array $specs, string $delimiter, array $item): string
    {
        $values = [];
        foreach ($specs as $spec) {
            $value = $this->renderDatePartValue($parts, $spec, $item);
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $this->joinRenderedElements($values, $delimiter !== '' ? $delimiter : '-');
    }

    /**
     * @param list<int> $parts
     * @param array{name:string, prefix:string, suffix:string, form:string, rangeDelimiter:string, stripPeriods:bool, textCase:string} $spec
     * @param array<string, mixed> $item
     */
    private function renderDatePartValue(array $parts, array $spec, array $item): string
    {
        $name = $spec['name'];
        $number = match ($name) {
            'year' => $parts[0] ?? null,
            'month' => $parts[1] ?? null,
            'day' => $parts[2] ?? null,
            default => null,
        };
        if ($number === null) {
            return '';
        }

        $value = match ($name) {
            'year' => $this->formatCslDateYearPart((int) $number, $spec['form']),
            'month' => $this->formatCslDateMonthPart((int) $number, $spec['form']),
            'day' => $this->formatCslDateDayPart((int) $number, $spec['form']),
            default => '',
        };
        if ($value === '') {
            return '';
        }

        if ($spec['stripPeriods']) {
            $value = str_replace('.', '', $value);
        }
        $value = $this->applyTextCase($value, $spec, $item);

        return (string) $spec['prefix'] . $value . (string) $spec['suffix'];
    }

    private function formatCslDateYearPart(int $year, string $form): string
    {
        if ($form === 'short') {
            return sprintf('%02d', abs($year) % 100);
        }

        return sprintf('%04d', $year);
    }

    private function formatCslDateMonthPart(int $month, string $form): string
    {
        if ($month < 1 || $month > 12) {
            return '';
        }

        return match ($form) {
            'numeric' => (string) $month,
            'numeric-leading-zeros' => sprintf('%02d', $month),
            'short' => $this->style->term('month-' . sprintf('%02d', $month), 'short'),
            default => $this->style->term('month-' . sprintf('%02d', $month), 'long'),
        };
    }

    private function formatCslDateDayPart(int $day, string $form): string
    {
        if ($day < 1 || $day > 31) {
            return '';
        }

        return match ($form) {
            'numeric-leading-zeros' => sprintf('%02d', $day),
            'ordinal' => $day . $this->ordinalSuffix($day),
            default => (string) $day,
        };
    }

    /**
     * @param list<list<int>> $parts
     * @param list<array{name:string, prefix:string, suffix:string, form:string, rangeDelimiter:string, stripPeriods:bool, textCase:string}> $specs
     */
    private function dateRangeDelimiter(array $parts, array $specs): string
    {
        if (count($parts) < 2 || !is_array($parts[0] ?? null) || !is_array($parts[1] ?? null)) {
            return '/';
        }

        $start = $parts[0];
        $end = $parts[1];
        $largestDifferingPart = null;
        foreach ([0 => 'year', 1 => 'month', 2 => 'day'] as $index => $name) {
            if (($start[$index] ?? null) !== ($end[$index] ?? null)) {
                $largestDifferingPart = $name;
                break;
            }
        }

        if ($largestDifferingPart === null) {
            return '/';
        }

        foreach ($specs as $spec) {
            if ($spec['name'] === $largestDifferingPart && $spec['rangeDelimiter'] !== '') {
                return $spec['rangeDelimiter'];
            }
        }

        return '/';
    }

    /**
     * @param list<array{family:string, given:string, literal:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool}> $names
     * @param array<string, mixed> $options
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
            $term = $this->renderEtAlTerm($options);
            if ($rendered === []) {
                return $term;
            }

            return $this->joinNamesWithEtAl($rendered, $term, $options, $bibliography, $visibleCount - 1);
        }

        return $bibliography
            ? implode($options['delimiter'], $rendered)
            : $this->joinCitationNames($rendered, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function renderEtAlTerm(array $options): string
    {
        $etAl = is_array($options['etAl'] ?? null) ? $options['etAl'] : [];
        $termName = (string) ($etAl['term'] ?? 'et-al');
        if (!in_array($termName, ['et-al', 'and others'], true)) {
            $termName = 'et-al';
        }

        $term = $this->style->term($termName);
        if (($etAl['stripPeriods'] ?? false) === true) {
            $term = str_replace('.', '', $term);
        }

        $term = $this->applyNamePartTextCase($term, $etAl);
        if (($etAl['quotes'] ?? false) === true) {
            $term = $this->style->term('open-quote') . $term . $this->style->term('close-quote');
        }

        return (string) ($etAl['prefix'] ?? '') . $term . (string) ($etAl['suffix'] ?? '');
    }

    /**
     * @param list<string> $rendered
     * @param array<string, mixed> $options
     */
    private function joinNamesWithEtAl(array $rendered, string $term, array $options, bool $bibliography, int $lastVisibleIndex): string
    {
        $names = implode((string) $options['delimiter'], $rendered);
        $separator = $this->delimiterBeforeEtAl($rendered, $options, $bibliography, $lastVisibleIndex)
            ? (string) $options['delimiter']
            : ' ';

        if ($separator === '') {
            $separator = ' ';
        }

        return $names . $separator . $term;
    }

    /**
     * @param list<string> $rendered
     * @param array<string, mixed> $options
     */
    private function delimiterBeforeEtAl(array $rendered, array $options, bool $bibliography, int $lastVisibleIndex): bool
    {
        return match ((string) ($options['delimiterPrecedesEtAl'] ?? 'contextual')) {
            'always' => true,
            'never' => false,
            'after-inverted-name' => $bibliography && $this->visibleNameWasInvertedForEtAl($options, $lastVisibleIndex),
            default => count($rendered) > 1,
        };
    }

    /**
     * @param array<string, mixed> $options
     */
    private function visibleNameWasInvertedForEtAl(array $options, int $lastVisibleIndex): bool
    {
        $nameAsSortOrder = (string) ($options['nameAsSortOrder'] ?? '');
        if ($nameAsSortOrder === 'all') {
            return true;
        }

        return $nameAsSortOrder === 'first' && $lastVisibleIndex === 0;
    }

    /**
     * @param array{family:string, given:string, literal:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool} $name
     * @param array<string, mixed> $options
     */
    private function renderCitationName(array $name, array $options): string
    {
        if ($name['literal'] !== '') {
            return $name['literal'];
        }

        $family = trim((string) $name['nonDroppingParticle'] . ' ' . (string) $name['family']);
        if ($family !== '') {
            return $this->formatNamePart('family', $family, $options);
        }

        return $this->formatNamePart('given', $this->renderGivenName((string) $name['given'], $options['initializeWith']), $options);
    }

    /**
     * @param array{family:string, given:string, literal:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool} $name
     * @param array<string, mixed> $options
     */
    private function renderBibliographyName(array $name, array $options, int $index): string
    {
        if ($name['literal'] !== '') {
            return $name['literal'];
        }

        $family = trim((string) $name['nonDroppingParticle'] . ' ' . (string) $name['family']);
        $family = $this->formatNamePart('family', $family, $options);
        $given = $this->formatNamePart('given', $this->renderGivenName((string) $name['given'], $options['initializeWith']), $options);
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
     * @param array<string, mixed> $options
     */
    private function formatNamePart(string $part, string $value, array $options): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $nameParts = $options['nameParts'] ?? [];
        $format = is_array($nameParts) && is_array($nameParts[$part] ?? null) ? $nameParts[$part] : null;
        if ($format === null) {
            return $value;
        }

        if (($format['stripPeriods'] ?? false) === true) {
            $value = str_replace('.', '', $value);
        }

        $value = $this->applyNamePartTextCase($value, $format);

        if (($format['quotes'] ?? false) === true) {
            $value = $this->style->term('open-quote') . $value . $this->style->term('close-quote');
        }

        return (string) ($format['prefix'] ?? '') . $value . (string) ($format['suffix'] ?? '');
    }

    /**
     * @param array<string, mixed> $format
     */
    private function applyNamePartTextCase(string $value, array $format): string
    {
        $textCase = strtolower(trim((string) ($format['textCase'] ?? '')));

        return match ($textCase) {
            'lowercase' => mb_strtolower($value, 'UTF-8'),
            'uppercase' => mb_strtoupper($value, 'UTF-8'),
            'capitalize-first' => $this->capitalizeFirstLowercaseWord($value),
            'capitalize-all' => $this->capitalizeAllLowercaseWords($value),
            'sentence' => $this->sentenceCaseText($value),
            'title' => $this->titleCaseText($value, []),
            default => $value,
        };
    }

    /**
     * @param list<string> $names
     * @param array<string, mixed> $options
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
     * @param array<string, mixed> $options
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

    /**
     * @return array{label:string, value:string}
     */
    private function citationLocatorParts(?AstNode $citation): array
    {
        if (!$citation instanceof AstNode) {
            return ['label' => 'page', 'value' => ''];
        }

        $explicitValue = $this->inlineValue($citation->attr('locatorValue', ''));
        if ($explicitValue !== '') {
            $label = $this->normalizedLocatorLabel((string) $citation->attr('locatorLabel', ''));

            return ['label' => $label, 'value' => $explicitValue];
        }

        $locator = $this->inlineValue($citation->attr('locator', ''));
        if ($locator === '') {
            return ['label' => 'page', 'value' => ''];
        }

        return $this->inferCitationLocatorParts($locator);
    }

    /**
     * @return array{label:string, value:string}
     */
    private function inferCitationLocatorParts(string $locator): array
    {
        $locator = trim(preg_replace('/\s+/u', ' ', $locator) ?? $locator);
        $patterns = [
            'page' => '/^(?:p(?:p)?\.?|pages?)\s+(.+)$/iu',
            'chapter' => '/^(?:chap(?:ters?|s)?\.?|chapter(?:s)?)\s+(.+)$/iu',
            'section' => '/^(?:sec(?:tions?|s)?\.?|section(?:s)?|\x{00A7}\x{00A7}?)\s+(.+)$/iu',
            'paragraph' => '/^(?:para(?:graphs?|s)?\.?|paragraph(?:s)?|\x{00B6}\x{00B6}?)\s+(.+)$/iu',
            'volume' => '/^(?:vol(?:umes?|s)?\.?|volume(?:s)?)\s+(.+)$/iu',
            'number' => '/^(?:no(?:s)?\.?|number(?:s)?)\s+(.+)$/iu',
        ];

        foreach ($patterns as $label => $pattern) {
            if (preg_match($pattern, $locator, $match) === 1) {
                return ['label' => $label, 'value' => trim($match[1])];
            }
        }

        return ['label' => 'page', 'value' => $locator];
    }

    private function normalizedLocatorLabel(string $label): string
    {
        $label = strtolower(trim($label));

        return match ($label) {
            'p', 'pp', 'page', 'pages' => 'page',
            'chap', 'chaps', 'chapter', 'chapters' => 'chapter',
            'sec', 'secs', 'section', 'sections', "\u{00A7}", "\u{00A7}\u{00A7}" => 'section',
            'para', 'paras', 'paragraph', 'paragraphs', "\u{00B6}", "\u{00B6}\u{00B6}" => 'paragraph',
            'vol', 'vols', 'volume', 'volumes' => 'volume',
            'no', 'nos', 'number', 'numbers' => 'number',
            default => $label === '' ? 'page' : $label,
        };
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
