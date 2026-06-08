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
     * @return array{title:string, id:string, class:string, defaultLocale:string, pageRangeFormat:string, citationLayout:array{prefix:string, suffix:string, delimiter:string}, bibliographyLayout:array{prefix:string, suffix:string, delimiter:string}, bibliographyOptions:array{hangingIndent:bool, entrySpacing:int|null, lineSpacing:int|null, secondFieldAlign:string, subsequentAuthorSubstitute:string, subsequentAuthorSubstituteRule:string}, citationOptions:array{disambiguateAddYearSuffix:bool, disambiguateAddGivenName:bool, givenNameDisambiguationRule:string, collapse:string, nearNoteDistance:int}, citationSort:list<array{sort:string, variable?:string, macro?:string}>, bibliographySort:list<array{sort:string, variable?:string, macro?:string}>, citationRendering:list<array<string, mixed>>, bibliographyRendering:list<array<string, mixed>>, macros:array<string, list<array<string, mixed>>>, localeOptions:array{punctuationInQuote:bool, limitDayOrdinalsToDay1:bool}, terms:array{and:string, etAl:string, noDate:string, accessed:string}}
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
                    'cslStyleClass' => $this->style->styleClass(),
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
                'cslStyleClass' => $this->style->styleClass(),
                'rendered' => $this->renderCitationCluster([$citation]),
                'cslLabel' => $this->citationAuthorLabel($item, $citation),
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
        $citations = $this->annotateCitationGivenNameDisambiguationForCluster($citations);
        $citations = $this->annotateCitationNameDisambiguationForCluster($citations);
        $citations = $this->annotateCitationYearSuffixesForCluster($citations);
        $citations = $this->annotateCitationDisambiguationForCluster($citations);
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
        $ids = $this->uniqueKnownCitationIds($numbered);
        $givenNameDisambiguationModes = $this->givenNameDisambiguationModesForIds($ids);
        $annotated = $this->annotateCitationGivenNameDisambiguation($numbered, $givenNameDisambiguationModes);
        $nameCounts = $this->nameDisambiguationCountsForIds($ids, $givenNameDisambiguationModes);
        $annotated = $this->annotateCitationNameDisambiguation($annotated, $nameCounts);
        $yearSuffixes = $this->yearSuffixesForIds($ids, $nameCounts, $givenNameDisambiguationModes);
        $annotated = $this->annotateCitationYearSuffixes($annotated, $yearSuffixes);
        $disambiguatingIds = $this->disambiguatingCitationIdsForIds($ids, $nameCounts, $givenNameDisambiguationModes);
        $annotated = $this->annotateCitationDisambiguation($annotated, $disambiguatingIds);

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
        $citations = $this->annotateCitationGivenNameDisambiguationForCluster($citations);
        $citations = $this->annotateCitationNameDisambiguationForCluster($citations);
        $citations = $this->annotateCitationYearSuffixesForCluster($citations);
        $citations = $this->annotateCitationDisambiguationForCluster($citations);
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
    private function renderBibliographyEntryForItem(array $item, ?array &$bibliographyState = null): string
    {
        $customEntry = $this->renderCustomBibliographyEntry($item, $bibliographyState);
        if ($customEntry !== null) {
            return $customEntry;
        }

        $parts = [];
        $authors = $this->bibliographyAuthors($item, $bibliographyState);
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

        $publisherPlaceList = $item['publisherPlaceList'] ?? [];
        if (is_array($publisherPlaceList) && count($publisherPlaceList) > 1) {
            $places = array_values(array_filter(
                array_map(static fn (mixed $place): string => trim((string) $place), $publisherPlaceList),
                static fn (string $place): bool => $place !== ''
            ));
            if ($places !== []) {
                $parts[] = 'Publisher places: ' . implode('; ', $places) . '.';
            }
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

        foreach ($this->relatedBibliographyParts($item) as $part) {
            $parts[] = $part;
        }

        foreach ($this->xrefBibliographyParts($item) as $part) {
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

        $originalTitleAddon = (string) ($item['originalTitleAddon'] ?? '');
        if ($originalTitleAddon !== '') {
            $parts[] = 'Original title addendum: ' . $originalTitleAddon . '.';
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
            $urlLabel = trim((string) ($item['urlLabel'] ?? ''));
            if ($urlLabel !== '') {
                $parts[] = 'URL label: ' . $this->withTerminalPunctuation($urlLabel);
            }

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
        $bibliographyState = $this->emptyBibliographySubstitutionState();
        foreach ($ids as $id) {
            $item = $this->itemsById[$id] ?? null;
            if ($item === null) {
                continue;
            }

            $item = $this->itemWithYearSuffix($item, $yearSuffixes[$id] ?? '');
            $item = $this->itemWithCitationNumber($item, $citationNumbers[$this->canonicalCitationId($id)] ?? '');
            $label = $this->citationLabel($item);
            $this->resetBibliographySubstitutionEntry($bibliographyState);
            $entry = $this->renderBibliographyEntryForItem($item, $bibliographyState);
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
     * @return array{previousRenderedNames:string, previousRenderedNameParts:list<string>, entrySubstitutionChecked:bool}
     */
    private function emptyBibliographySubstitutionState(): array
    {
        return [
            'previousRenderedNames' => '',
            'previousRenderedNameParts' => [],
            'entrySubstitutionChecked' => false,
        ];
    }

    /**
     * @param array{previousRenderedNames:string, previousRenderedNameParts:list<string>, entrySubstitutionChecked:bool} $state
     */
    private function resetBibliographySubstitutionEntry(array &$state): void
    {
        $state['entrySubstitutionChecked'] = false;
    }

    /**
     * @param list<string> $renderedNameParts
     * @param array{previousRenderedNames:string, previousRenderedNameParts:list<string>, entrySubstitutionChecked:bool} $state
     * @return array{type:string, value?:string, parts?:list<string>}
     */
    private function bibliographySubsequentAuthorSubstitutionPlan(array $renderedNameParts, string $renderedNames, array &$state): array
    {
        if ($renderedNames === '' || ($state['entrySubstitutionChecked'] ?? false) === true) {
            return ['type' => 'none'];
        }

        $state['entrySubstitutionChecked'] = true;
        $previous = (string) ($state['previousRenderedNames'] ?? '');
        $previousParts = is_array($state['previousRenderedNameParts'] ?? null)
            ? array_values(array_map('strval', $state['previousRenderedNameParts']))
            : [];
        $state['previousRenderedNames'] = $renderedNames;
        $state['previousRenderedNameParts'] = array_values($renderedNameParts);

        $options = $this->style->bibliographyOptions();
        $substitute = (string) ($options['subsequentAuthorSubstitute'] ?? '');
        $rule = (string) ($options['subsequentAuthorSubstituteRule'] ?? 'complete-all');
        if ($substitute === '' || $previous === '') {
            return ['type' => 'none'];
        }

        $completeMatch = $this->normalizedRenderedNameKey($renderedNames) === $this->normalizedRenderedNameKey($previous);
        if ($rule === 'complete-all') {
            return $completeMatch ? ['type' => 'full', 'value' => $substitute] : ['type' => 'none'];
        }

        if ($rule === 'complete-each') {
            return $completeMatch && $renderedNameParts !== []
                ? ['type' => 'parts', 'parts' => array_fill(0, count($renderedNameParts), $substitute)]
                : ['type' => 'none'];
        }

        if ($rule !== 'partial-each' && $rule !== 'partial-first') {
            return ['type' => 'none'];
        }

        $matchCount = 0;
        $limit = min(count($renderedNameParts), count($previousParts));
        for ($index = 0; $index < $limit; $index++) {
            if ($this->normalizedRenderedNameKey($renderedNameParts[$index]) !== $this->normalizedRenderedNameKey($previousParts[$index])) {
                break;
            }

            $matchCount++;
        }

        if ($matchCount === 0) {
            return ['type' => 'none'];
        }

        $substituted = $renderedNameParts;
        $substitutionCount = $rule === 'partial-first' ? 1 : $matchCount;
        for ($index = 0; $index < $substitutionCount; $index++) {
            $substituted[$index] = $substitute;
        }

        return ['type' => 'parts', 'parts' => $substituted];
    }

    private function normalizedRenderedNameKey(string $value): string
    {
        return preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
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
        $page = self::stringField($item, 'page');
        $containerTitleShort = self::firstStringField($item, ['container-title-short', 'containerTitleShort', 'journalAbbreviation', 'journal-abbreviation']);
        $publisher = self::stringField($item, 'publisher');
        $publisherPlace = self::stringField($item, 'publisher-place');
        $originalPublisher = self::firstStringField($item, ['original-publisher', 'originalPublisher', 'origpublisher']);
        $originalPublisherPlace = self::firstStringField($item, ['original-publisher-place', 'originalPublisherPlace', 'origlocation', 'origaddress']);
        $publisherList = self::stringListFromFirstField($item, ['publisher-list', 'publisherList']);
        $publisherPlaceList = self::stringListFromFirstField($item, ['publisher-place-list', 'publisherPlaceList']);
        $originalPublisherList = self::stringListFromFirstField($item, ['original-publisher-list', 'originalPublisherList']);
        $originalPublisherPlaceList = self::stringListFromFirstField($item, ['original-publisher-place-list', 'originalPublisherPlaceList']);
        $languageList = self::stringListFromFirstField($item, ['language-list', 'languageList']);
        $language = self::stringField($item, 'language');
        if ($language === '' && $languageList !== []) {
            $language = implode('; ', $languageList);
        }
        $originalLanguageList = self::stringListFromFirstField($item, ['original-language-list', 'originalLanguageList']);
        $originalLanguage = self::stringField($item, 'original-language');
        if ($originalLanguage === '' && $originalLanguageList !== []) {
            $originalLanguage = implode('; ', $originalLanguageList);
        }
        $eventPlace = self::firstStringField($item, ['event-place', 'eventPlace']);
        $eventPlaceList = self::stringListFromFirstField($item, ['event-place-list', 'eventPlaceList']);
        if ($eventPlace === '' && $eventPlaceList !== []) {
            $eventPlace = implode('; ', $eventPlaceList);
        }
        $accessedDate = self::dateVariable($item['accessed'] ?? null, $id, 'accessed');
        $availableDate = self::dateVariable($item['available-date'] ?? $item['availableDate'] ?? null, $id, 'available-date');
        $originalDate = self::dateVariable($item['original-date'] ?? null, $id, 'original-date');
        $submittedDate = self::dateVariable($item['submitted'] ?? $item['submitted-date'] ?? $item['submittedDate'] ?? null, $id, 'submitted');
        $eventDate = self::dateVariable($item['event-date'] ?? null, $id, 'event-date');
        $dateMarkerSummary = self::dateMarkerSummary([
            'issued' => $issuedDate,
            'accessed' => $accessedDate,
            'available-date' => $availableDate,
            'original-date' => $originalDate,
            'submitted' => $submittedDate,
            'event-date' => $eventDate,
        ]);
        $dateTimeSummary = self::dateTimeSummary([
            'issued' => $issuedDate,
            'accessed' => $accessedDate,
            'available-date' => $availableDate,
            'original-date' => $originalDate,
            'submitted' => $submittedDate,
            'event-date' => $eventDate,
        ]);
        $dateSeasonSummary = self::dateSeasonSummary([
            'issued' => $issuedDate,
            'accessed' => $accessedDate,
            'available-date' => $availableDate,
            'original-date' => $originalDate,
            'submitted' => $submittedDate,
            'event-date' => $eventDate,
        ]);
        $keywords = self::stringListFromFirstField($item, ['keyword', 'keywords']);
        $biblatexOptions = self::stringListFromFirstField($item, ['biblatexOptions', 'biblatex-options', 'biblatexoptions']);
        $biblatexLanguageOptions = self::stringListFromFirstField($item, [
            'biblatexLanguageOptions',
            'biblatex-language-options',
            'biblatexlanguageoptions',
            'langidopts',
            'language-options',
            'languageOptions',
        ]);
        $biblatexGender = self::firstStringField($item, ['biblatexGender', 'biblatex-gender', 'gender']);
        $biblatexCustomFields = self::biblatexCustomFields($item, $id);
        $biblatexCustomLists = self::biblatexCustomLists($item, $id);
        $biblatexCustomNames = self::biblatexCustomNames($item, $id);
        $biblatexFieldAnnotations = self::biblatexFieldAnnotations($item, $id);
        $biblatexRefsection = self::firstStringField($item, ['biblatexRefsection', 'biblatex-refsection', 'refsection', 'ref-section']);
        $biblatexRefsegment = self::firstStringField($item, ['biblatexRefsegment', 'biblatex-refsegment', 'refsegment', 'ref-segment']);
        $citationAliases = self::stringListFromFirstField($item, ['citation-aliases', 'citationAliases', 'ids']);

        return [
            'id' => $id,
            'type' => self::stringField($item, 'type'),
            'citationAliases' => $citationAliases,
            'citationAliasSummary' => implode('; ', $citationAliases),
            'citationLabel' => self::firstStringField($item, ['citation-label', 'citationLabel', 'shorthand', 'label']),
            'shorthand' => self::firstStringField($item, ['shorthand']),
            'shorthandIntro' => self::firstStringField($item, ['shorthand-intro', 'shorthandIntro', 'shorthandintro']),
            'sortShorthand' => self::firstStringField($item, ['sort-shorthand', 'sortShorthand', 'sortshorthand']),
            'shorthandListSortKey' => self::firstStringField($item, ['shorthand-list-sort-key', 'shorthandListSortKey', 'list-shorthand', 'listshorthand'])
                ?: self::shorthandListSortKey($item),
            'presort' => self::firstStringField($item, ['presort']),
            'sortKey' => self::firstStringField($item, ['sort-key', 'sortKey', 'sortkey']),
            'sortName' => self::firstStringField($item, ['sort-name', 'sortName', 'sortname']),
            'sortTitle' => self::firstStringField($item, ['sort-title', 'sortTitle', 'sorttitle']),
            'sortYear' => self::firstStringField($item, ['sort-year', 'sortYear', 'sortyear']),
            'indexTitle' => self::firstStringField($item, ['index-title', 'indexTitle', 'indextitle']),
            'indexSortTitle' => self::firstStringField($item, ['index-sort-title', 'indexSortTitle', 'indexsorttitle']),
            'labelAlpha' => self::firstStringField($item, ['label-alpha', 'labelAlpha', 'labelalpha']),
            'labelTitle' => self::firstStringField($item, ['label-title', 'labelTitle', 'labeltitle']),
            'extraDate' => self::firstStringField($item, ['extra-date', 'extraDate', 'extradate']),
            'extraTitle' => self::firstStringField($item, ['extra-title', 'extraTitle', 'extratitle']),
            'title' => self::stringField($item, 'title'),
            'shortTitle' => self::firstStringField($item, ['short-title', 'title-short', 'shortTitle', 'titleShort']),
            'titleAddon' => self::stringField($item, 'title-addon'),
            'translatedTitle' => self::firstStringField($item, ['translated-title', 'translatedTitle', 'translatedtitle', 'title-translation', 'titleTranslation', 'titletranslation']),
            'reviewedTitle' => self::firstStringField($item, ['reviewed-title', 'reviewedTitle', 'reviewtitle']),
            'reprintTitle' => self::firstStringField($item, ['reprint-title', 'reprintTitle', 'reprinttitle']),
            'containerTitle' => self::stringField($item, 'container-title'),
            'containerTitleShort' => $containerTitleShort,
            'journalAbbreviation' => $containerTitleShort,
            'containerTitleAddon' => self::stringField($item, 'container-title-addon'),
            'mainTitle' => self::firstStringField($item, ['main-title', 'mainTitle']),
            'mainTitleAddon' => self::firstStringField($item, ['main-title-addon', 'mainTitleAddon']),
            'eventTitle' => self::firstStringField($item, ['event', 'event-title', 'eventTitle']),
            'eventTitleAddon' => self::firstStringField($item, ['event-title-addon', 'eventTitleAddon']),
            'eventPlace' => $eventPlace,
            'eventPlaceList' => $eventPlaceList !== [] ? $eventPlaceList : ($eventPlace !== '' ? [$eventPlace] : []),
            'eventType' => self::firstStringField($item, ['event-type', 'eventType']),
            'publisher' => $publisher,
            'publisherPlace' => $publisherPlace,
            'publisherList' => $publisherList !== [] ? $publisherList : ($publisher !== '' ? [$publisher] : []),
            'publisherPlaceList' => $publisherPlaceList !== [] ? $publisherPlaceList : ($publisherPlace !== '' ? [$publisherPlace] : []),
            'page' => $page,
            'pageFirst' => self::firstStringField($item, ['page-first', 'pageFirst']) ?: self::firstPageFromRange($page),
            'pagination' => self::firstStringField($item, ['pagination', 'page-label', 'pageLabel']),
            'bookPagination' => self::firstStringField($item, ['book-pagination', 'bookPagination', 'bookpagination']),
            'thesisType' => self::firstStringField($item, ['thesis-type', 'thesisType', 'thesistype']),
            'articleNumber' => self::firstStringField($item, ['article-number', 'articleNumber', 'articlenumber', 'eid']),
            'references' => self::firstStringField($item, ['references']),
            'dimensions' => self::firstStringField($item, ['dimensions', 'dimension']),
            'scale' => self::firstStringField($item, ['scale']),
            'number' => self::stringField($item, 'number'),
            'volume' => self::stringField($item, 'volume'),
            'issue' => self::stringField($item, 'issue'),
            'issueTitle' => self::firstStringField($item, ['issue-title', 'issueTitle', 'issuetitle']),
            'issueTitleAddon' => self::firstStringField($item, ['issue-title-addon', 'issueTitleAddon', 'issuetitleaddon']),
            'edition' => self::stringField($item, 'edition'),
            'collectionTitle' => self::firstStringField($item, ['collection-title', 'collectionTitle']),
            'collectionNumber' => self::firstStringField($item, ['collection-number', 'collectionNumber']),
            'numberOfVolumes' => self::firstStringField($item, ['number-of-volumes', 'numberOfVolumes']),
            'numberOfPages' => self::firstStringField($item, ['number-of-pages', 'numberOfPages']),
            'chapterNumber' => self::firstStringField($item, ['chapter-number', 'chapterNumber']),
            'part' => self::stringField($item, 'part'),
            'genre' => self::stringField($item, 'genre'),
            'entrySubtype' => self::firstStringField($item, ['entry-subtype', 'entrySubtype', 'entrysubtype']),
            'gender' => $biblatexGender,
            'biblatexGender' => $biblatexGender,
            'biblatexGenderSummary' => $biblatexGender,
            'authority' => self::stringField($item, 'authority'),
            'jurisdiction' => self::stringField($item, 'jurisdiction'),
            'status' => self::stringField($item, 'status'),
            'version' => self::stringField($item, 'version'),
            'doi' => self::firstStringField($item, ['DOI', 'doi']),
            'url' => self::firstStringField($item, ['URL', 'url']),
            'urlLabel' => self::firstStringField($item, ['URL-label', 'url-label', 'URLLabel', 'urlLabel', 'urldescription', 'urltitle', 'urllabel', 'url-description']),
            'isbn' => self::firstStringField($item, ['ISBN', 'isbn']),
            'issn' => self::firstStringField($item, ['ISSN', 'issn']),
            'isan' => self::firstStringField($item, ['ISAN', 'isan']),
            'ismn' => self::firstStringField($item, ['ISMN', 'ismn']),
            'isrn' => self::firstStringField($item, ['ISRN', 'isrn']),
            'iswc' => self::firstStringField($item, ['ISWC', 'iswc']),
            'pmid' => self::firstStringField($item, ['PMID', 'pmid']),
            'pmcid' => self::firstStringField($item, ['PMCID', 'pmcid']),
            'archive' => self::stringField($item, 'archive'),
            'archivePlace' => self::firstStringField($item, ['archive-place', 'archivePlace']),
            'archiveLocation' => self::firstStringField($item, ['archive_location', 'archive-location', 'archiveLocation']),
            'callNumber' => self::firstStringField($item, ['call-number', 'callNumber', 'callnumber', 'library']),
            'language' => $language,
            'languageList' => $languageList !== [] ? $languageList : ($language !== '' ? [$language] : []),
            'abstract' => self::stringField($item, 'abstract'),
            'annotation' => self::firstStringField($item, ['annotation', 'annote']),
            'medium' => self::stringField($item, 'medium'),
            'note' => self::stringField($item, 'note'),
            'addendum' => self::stringField($item, 'addendum'),
            'nameAddon' => self::firstStringField($item, ['name-addon', 'nameAddon']),
            'authorType' => self::firstStringField($item, ['author-type', 'authorType', 'authortype']),
            'containerAuthorType' => self::firstStringField($item, [
                'container-author-type',
                'containerAuthorType',
                'bookauthor-type',
                'bookAuthorType',
                'bookauthortype',
            ]),
            'dateAddon' => self::firstStringField($item, ['date-addon', 'dateAddon', 'dateaddendum', 'date-addendum']),
            'keywords' => $keywords,
            'keywordSummary' => implode('; ', $keywords),
            'sourceFiles' => $sourceFilePolicy['files'],
            'sourceFileDiagnostics' => $sourceFileDiagnostics,
            'relatedKeys' => self::stringListFromFirstField($item, ['relatedKeys', 'related-keys', 'related']),
            'relatedType' => self::firstStringField($item, ['relatedType', 'related-type', 'relatedtype']),
            'relatedString' => self::firstStringField($item, ['relatedString', 'related-string', 'relatedstring']),
            'relatedOptions' => self::stringListFromFirstField($item, ['relatedOptions', 'related-options', 'relatedoptions']),
            'relatedItems' => self::relatedItemSummaries($item['relatedItems'] ?? [], $id),
            'missingRelatedKeys' => self::stringListFromFirstField($item, ['missingRelatedKeys', 'missing-related-keys']),
            'xrefKeys' => self::stringListFromFirstField($item, ['xrefKeys', 'xref-keys', 'xref']),
            'xrefItems' => self::relatedItemSummaries($item['xrefItems'] ?? [], $id, 'xrefItems'),
            'missingXrefKeys' => self::stringListFromFirstField($item, ['missingXrefKeys', 'missing-xref-keys']),
            'issuedDate' => $issuedDate,
            'accessedDate' => $accessedDate,
            'availableDate' => $availableDate,
            'originalTitle' => self::firstStringField($item, ['original-title', 'originalTitle', 'origtitle']),
            'originalTitleAddon' => self::firstStringField($item, ['original-title-addon', 'originalTitleAddon', 'origtitleaddon']),
            'originalPublisher' => $originalPublisher,
            'originalPublisherPlace' => $originalPublisherPlace,
            'originalPublisherList' => $originalPublisherList !== [] ? $originalPublisherList : ($originalPublisher !== '' ? [$originalPublisher] : []),
            'originalPublisherPlaceList' => $originalPublisherPlaceList !== [] ? $originalPublisherPlaceList : ($originalPublisherPlace !== '' ? [$originalPublisherPlace] : []),
            'originalLanguage' => $originalLanguage,
            'originalLanguageList' => $originalLanguageList !== [] ? $originalLanguageList : ($originalLanguage !== '' ? [$originalLanguage] : []),
            'originalDate' => $originalDate,
            'originalDateAddon' => self::firstStringField($item, ['original-date-addon', 'originalDateAddon', 'origdateaddon', 'orig-date-addon']),
            'submittedDate' => $submittedDate,
            'eventDate' => $eventDate,
            'eventDateAddon' => self::firstStringField($item, ['event-date-addon', 'eventDateAddon', 'eventdateaddon']),
            'accessedDateAddon' => self::firstStringField($item, ['accessed-date-addon', 'accessedDateAddon', 'urldateaddon', 'url-date-addon']),
            'dateMarkerSummary' => $dateMarkerSummary,
            'dateTimeSummary' => $dateTimeSummary,
            'dateSeasonSummary' => $dateSeasonSummary,
            'biblatexOptions' => $biblatexOptions,
            'biblatexOptionSummary' => implode('; ', $biblatexOptions),
            'biblatexLanguageOptions' => $biblatexLanguageOptions,
            'biblatexLanguageOptionSummary' => implode('; ', $biblatexLanguageOptions),
            'biblatexFieldAnnotations' => $biblatexFieldAnnotations,
            'biblatexFieldAnnotationSummary' => self::biblatexFieldAnnotationSummary($biblatexFieldAnnotations),
            'biblatexCustomFields' => $biblatexCustomFields,
            'biblatexCustomFieldSummary' => self::biblatexCustomFieldSummary($biblatexCustomFields),
            'biblatexCustomLists' => $biblatexCustomLists,
            'biblatexCustomListSummary' => self::biblatexCustomListSummary($biblatexCustomLists),
            'biblatexCustomNames' => $biblatexCustomNames,
            'biblatexCustomNameSummary' => self::biblatexCustomNameSummary($biblatexCustomNames),
            'biblatexRefsection' => $biblatexRefsection,
            'biblatexRefsegment' => $biblatexRefsegment,
            'biblatexReferenceContextSummary' => self::biblatexReferenceContextSummary($biblatexRefsection, $biblatexRefsegment),
            'issuedYear' => $issuedDate['year'],
            'authors' => self::names($item['author'] ?? [], $id, 'author'),
            'editors' => self::names($item['editor'] ?? [], $id, 'editor'),
            'shortAuthors' => self::names($item['short-author'] ?? $item['shortAuthor'] ?? [], $id, 'short-author'),
            'shortEditors' => self::names($item['short-editor'] ?? $item['shortEditor'] ?? [], $id, 'short-editor'),
            'holders' => self::names($item['holder'] ?? [], $id, 'holder'),
            'translators' => self::names($item['translator'] ?? [], $id, 'translator'),
            'chairs' => self::names($item['chair'] ?? $item['chairs'] ?? [], $id, 'chair'),
            'containerAuthors' => self::names($item['container-author'] ?? $item['containerAuthor'] ?? [], $id, 'container-author'),
            'collectionEditors' => self::names($item['collection-editor'] ?? $item['collectionEditor'] ?? [], $id, 'collection-editor'),
            'composers' => self::names($item['composer'] ?? [], $id, 'composer'),
            'contributors' => self::names($item['contributor'] ?? [], $id, 'contributor'),
            'editorTranslators' => self::names($item['editor-translator'] ?? $item['editorTranslator'] ?? [], $id, 'editor-translator'),
            'executiveProducers' => self::names($item['executive-producer'] ?? $item['executiveProducer'] ?? [], $id, 'executive-producer'),
            'eventOrganizers' => self::names($item['event-organizer'] ?? [], $id, 'event-organizer'),
            'guests' => self::names($item['guest'] ?? [], $id, 'guest'),
            'hosts' => self::names($item['host'] ?? [], $id, 'host'),
            'narrators' => self::names($item['narrator'] ?? [], $id, 'narrator'),
            'originalAuthors' => self::names($item['original-author'] ?? [], $id, 'original-author'),
            'performers' => self::names($item['performer'] ?? [], $id, 'performer'),
            'producers' => self::names($item['producer'] ?? [], $id, 'producer'),
            'recipients' => self::names($item['recipient'] ?? [], $id, 'recipient'),
            'scriptWriters' => self::names($item['script-writer'] ?? $item['scriptWriter'] ?? [], $id, 'script-writer'),
            'compilers' => self::names($item['compiler'] ?? [], $id, 'compiler'),
            'curators' => self::names($item['curator'] ?? [], $id, 'curator'),
            'directors' => self::names($item['director'] ?? [], $id, 'director'),
            'editorialDirectors' => self::names($item['editorial-director'] ?? [], $id, 'editorial-director'),
            'illustrators' => self::names($item['illustrator'] ?? [], $id, 'illustrator'),
            'interviewers' => self::names($item['interviewer'] ?? [], $id, 'interviewer'),
            'reviewedAuthors' => self::names($item['reviewed-author'] ?? [], $id, 'reviewed-author'),
            'redactors' => self::names($item['redactor'] ?? [], $id, 'redactor'),
            'founders' => self::names($item['founder'] ?? [], $id, 'founder'),
            'continuators' => self::names($item['continuator'] ?? [], $id, 'continuator'),
            'revisers' => self::names($item['reviser'] ?? [], $id, 'reviser'),
            'collaborators' => self::names($item['collaborator'] ?? [], $id, 'collaborator'),
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

    private static function firstPageFromRange(string $pages): string
    {
        $pages = trim($pages);
        if ($pages === '') {
            return '';
        }

        $parts = preg_split('/\s*(?:[-\x{2010}-\x{2015}]|,|&|\band\b)\s*/u', $pages, 2);

        return trim((string) ($parts[0] ?? $pages));
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
     * @param array<string, mixed> $item
     */
    private static function shorthandListSortKey(array $item): string
    {
        $sortShorthand = self::firstStringField($item, ['sort-shorthand', 'sortShorthand', 'sortshorthand']);
        if ($sortShorthand !== '') {
            return $sortShorthand;
        }

        return self::firstStringField($item, ['shorthand']);
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
     * @param array<string, mixed> $item
     * @return array<string, string>
     */
    private static function biblatexCustomFields(array $item, string $id): array
    {
        $fields = [];
        $value = $item['biblatex-custom-fields'] ?? $item['biblatexCustomFields'] ?? null;
        if ($value !== null && $value !== []) {
            if (!is_array($value) || array_is_list($value)) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' biblatex-custom-fields must be an object map');
            }

            foreach ($value as $field => $fieldValue) {
                $field = strtolower(trim((string) $field));
                if (!in_array($field, self::biblatexCustomFieldNames(), true)) {
                    throw new \InvalidArgumentException('CSL item ' . $id . ' biblatex-custom-fields contains unsupported field ' . $field);
                }

                if (!is_scalar($fieldValue) && $fieldValue !== null) {
                    throw new \InvalidArgumentException('CSL item ' . $id . ' biblatex-custom-fields.' . $field . ' must be scalar');
                }

                $fieldValue = trim((string) ($fieldValue ?? ''));
                if ($fieldValue !== '') {
                    $fields[$field] = $fieldValue;
                }
            }
        }

        foreach (self::biblatexCustomFieldNames() as $field) {
            if (!array_key_exists($field, $item)) {
                continue;
            }

            $fieldValue = $item[$field];
            if (!is_scalar($fieldValue) && $fieldValue !== null) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' field ' . $field . ' must be scalar when present');
            }

            $fieldValue = trim((string) ($fieldValue ?? ''));
            if ($fieldValue !== '') {
                $fields[$field] = $fieldValue;
            }
        }

        return self::orderedBiblatexCustomFields($fields);
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, string>
     */
    private static function orderedBiblatexCustomFields(array $fields): array
    {
        $ordered = [];
        foreach (self::biblatexCustomFieldNames() as $field) {
            if (isset($fields[$field]) && $fields[$field] !== '') {
                $ordered[$field] = $fields[$field];
            }
        }

        return $ordered;
    }

    /**
     * @return list<string>
     */
    private static function biblatexCustomFieldNames(): array
    {
        return ['usera', 'userb', 'userc', 'userd', 'usere', 'userf', 'verba', 'verbb', 'verbc'];
    }

    /**
     * @param array<string, string> $fields
     */
    private static function biblatexCustomFieldSummary(array $fields): string
    {
        $parts = [];
        foreach (self::orderedBiblatexCustomFields($fields) as $field => $value) {
            $parts[] = $field . ': ' . $value;
        }

        return implode('; ', $parts);
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, list<array{name:string, value:string}>>
     */
    private static function biblatexFieldAnnotations(array $item, string $id): array
    {
        $value = $item['biblatex-field-annotations'] ?? $item['biblatexFieldAnnotations'] ?? null;
        if ($value === null || $value === []) {
            return [];
        }

        if (!is_array($value) || array_is_list($value)) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' biblatex-field-annotations must be an object map');
        }

        $annotations = [];
        foreach ($value as $field => $fieldValue) {
            $field = self::biblatexFieldAnnotationFieldName((string) $field, $id);
            $fieldAnnotations = self::biblatexFieldAnnotationValues($fieldValue, $id, $field);
            if ($fieldAnnotations !== []) {
                $annotations[$field] = $fieldAnnotations;
            }
        }

        return $annotations;
    }

    private static function biblatexFieldAnnotationFieldName(string $field, string $id): string
    {
        $field = strtolower(str_replace('_', '-', trim($field)));
        if ($field === '' || preg_match('/^[a-z][a-z0-9_.-]*$/', $field) !== 1) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' biblatex-field-annotations contains invalid field ' . $field);
        }

        return $field;
    }

    /**
     * @return list<array{name:string, value:string}>
     */
    private static function biblatexFieldAnnotationValues(mixed $value, string $id, string $field): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_scalar($value)) {
            $text = trim((string) $value);
            return $text === '' ? [] : [['name' => 'default', 'value' => $text]];
        }

        if (!is_array($value)) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' biblatex-field-annotations.' . $field . ' must be scalar, list, or object map');
        }

        if (!array_is_list($value) && (array_key_exists('value', $value) || array_key_exists('text', $value))) {
            $entry = self::biblatexFieldAnnotationObject($value, $id, $field);
            return $entry === null ? [] : [$entry];
        }

        $annotations = [];
        if (array_is_list($value)) {
            foreach ($value as $index => $entryValue) {
                if ($entryValue === null || $entryValue === '') {
                    continue;
                }

                if (is_scalar($entryValue)) {
                    $text = trim((string) $entryValue);
                    if ($text !== '') {
                        $annotations[] = ['name' => 'default', 'value' => $text];
                    }
                    continue;
                }

                if (!is_array($entryValue)) {
                    throw new \InvalidArgumentException('CSL item ' . $id . ' biblatex-field-annotations.' . $field . '[' . $index . '] must be scalar or object');
                }

                $entry = self::biblatexFieldAnnotationObject($entryValue, $id, $field);
                if ($entry !== null) {
                    $annotations[] = $entry;
                }
            }

            return $annotations;
        }

        foreach ($value as $name => $entryValue) {
            if (!is_scalar($entryValue) && $entryValue !== null) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' biblatex-field-annotations.' . $field . '.' . (string) $name . ' must be scalar');
            }

            $text = trim((string) ($entryValue ?? ''));
            if ($text === '') {
                continue;
            }

            $annotations[] = [
                'name' => self::biblatexFieldAnnotationName((string) $name),
                'value' => $text,
            ];
        }

        return $annotations;
    }

    /**
     * @param array<string, mixed> $value
     * @return array{name:string, value:string}|null
     */
    private static function biblatexFieldAnnotationObject(array $value, string $id, string $field): ?array
    {
        $name = $value['name'] ?? 'default';
        if (!is_scalar($name) && $name !== null) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' biblatex-field-annotations.' . $field . '.name must be scalar');
        }

        $text = $value['value'] ?? $value['text'] ?? '';
        if (!is_scalar($text) && $text !== null) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' biblatex-field-annotations.' . $field . '.value must be scalar');
        }

        $text = trim((string) ($text ?? ''));
        if ($text === '') {
            return null;
        }

        return [
            'name' => self::biblatexFieldAnnotationName((string) ($name ?? '')),
            'value' => $text,
        ];
    }

    private static function biblatexFieldAnnotationName(string $name): string
    {
        $name = strtolower(str_replace(['_', ' '], '-', trim($name)));

        return $name === '' ? 'default' : $name;
    }

    /**
     * @param array<string, list<array{name:string, value:string}>> $annotations
     */
    private static function biblatexFieldAnnotationSummary(array $annotations): string
    {
        $parts = [];
        foreach ($annotations as $field => $fieldAnnotations) {
            foreach ($fieldAnnotations as $annotation) {
                $value = trim($annotation['value']);
                if ($value === '') {
                    continue;
                }

                $name = trim($annotation['name']);
                $parts[] = $field . ' ' . ($name === '' ? 'default' : $name) . ': ' . $value;
            }
        }

        return implode('; ', $parts);
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, list<string>>
     */
    private static function biblatexCustomLists(array $item, string $id): array
    {
        $lists = [];
        $value = $item['biblatex-custom-lists'] ?? $item['biblatexCustomLists'] ?? null;
        if ($value !== null && $value !== []) {
            if (!is_array($value) || array_is_list($value)) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' biblatex-custom-lists must be an object map');
            }

            foreach ($value as $field => $fieldValue) {
                $field = strtolower(trim((string) $field));
                if (!in_array($field, self::biblatexCustomListNames(), true)) {
                    throw new \InvalidArgumentException('CSL item ' . $id . ' biblatex-custom-lists contains unsupported field ' . $field);
                }

                $fieldValues = self::stringListValue($fieldValue, 'biblatex-custom-lists.' . $field);
                if ($fieldValues !== []) {
                    $lists[$field] = $fieldValues;
                }
            }
        }

        foreach (self::biblatexCustomListNames() as $field) {
            if (!array_key_exists($field, $item)) {
                continue;
            }

            $fieldValues = self::stringListValue($item[$field], $field);
            if ($fieldValues !== []) {
                $lists[$field] = $fieldValues;
            }
        }

        return self::orderedBiblatexCustomLists($lists);
    }

    /**
     * @param array<string, list<string>> $lists
     * @return array<string, list<string>>
     */
    private static function orderedBiblatexCustomLists(array $lists): array
    {
        $ordered = [];
        foreach (self::biblatexCustomListNames() as $field) {
            if (isset($lists[$field]) && $lists[$field] !== []) {
                $ordered[$field] = $lists[$field];
            }
        }

        return $ordered;
    }

    /**
     * @return list<string>
     */
    private static function biblatexCustomListNames(): array
    {
        return ['lista', 'listb', 'listc', 'listd', 'liste', 'listf'];
    }

    /**
     * @param array<string, list<string>> $lists
     */
    private static function biblatexCustomListSummary(array $lists): string
    {
        $parts = [];
        foreach (self::orderedBiblatexCustomLists($lists) as $field => $values) {
            $parts[] = $field . ': ' . implode('; ', $values);
        }

        return implode('; ', $parts);
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, list<array<string, mixed>>>
     */
    private static function biblatexCustomNames(array $item, string $id): array
    {
        $names = [];
        $value = $item['biblatex-custom-names'] ?? $item['biblatexCustomNames'] ?? null;
        if ($value !== null && $value !== []) {
            if (!is_array($value) || array_is_list($value)) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' biblatex-custom-names must be an object map');
            }

            foreach ($value as $field => $fieldValue) {
                $field = strtolower(trim((string) $field));
                if (!in_array($field, self::biblatexCustomNameNames(), true)) {
                    throw new \InvalidArgumentException('CSL item ' . $id . ' biblatex-custom-names contains unsupported field ' . $field);
                }

                $fieldNames = self::names($fieldValue, $id, 'biblatex-custom-names.' . $field);
                if ($fieldNames !== []) {
                    $names[$field] = $fieldNames;
                }
            }
        }

        foreach (self::biblatexCustomNameNames() as $field) {
            if (!array_key_exists($field, $item)) {
                continue;
            }

            $fieldNames = self::names($item[$field], $id, $field);
            if ($fieldNames !== []) {
                $names[$field] = $fieldNames;
            }
        }

        return self::orderedBiblatexCustomNames($names);
    }

    /**
     * @param array<string, list<array<string, mixed>>> $names
     * @return array<string, list<array<string, mixed>>>
     */
    private static function orderedBiblatexCustomNames(array $names): array
    {
        $ordered = [];
        foreach (self::biblatexCustomNameNames() as $field) {
            if (isset($names[$field]) && $names[$field] !== []) {
                $ordered[$field] = $names[$field];
            }
        }

        return $ordered;
    }

    /**
     * @return list<string>
     */
    private static function biblatexCustomNameNames(): array
    {
        return ['namea', 'nameb', 'namec'];
    }

    /**
     * @param array<string, list<array<string, mixed>>> $names
     */
    private static function biblatexCustomNameSummary(array $names): string
    {
        $parts = [];
        foreach (self::orderedBiblatexCustomNames($names) as $field => $fieldNames) {
            $values = array_values(array_filter(
                array_map(
                    static fn (array $name): string => self::biblatexCustomNameDisplay($name),
                    $fieldNames
                ),
                static fn (string $value): bool => $value !== ''
            ));
            if ($values !== []) {
                $parts[] = $field . ': ' . implode('; ', $values);
            }
        }

        return implode('; ', $parts);
    }

    private static function biblatexReferenceContextSummary(string $refsection, string $refsegment): string
    {
        $parts = [];
        if ($refsection !== '') {
            $parts[] = 'refsection ' . $refsection;
        }

        if ($refsegment !== '') {
            $parts[] = 'refsegment ' . $refsegment;
        }

        return implode('; ', $parts);
    }

    /**
     * @param array<string, mixed> $name
     */
    private static function biblatexCustomNameDisplay(array $name): string
    {
        $literal = trim((string) ($name['literal'] ?? ''));
        if ($literal !== '') {
            return $literal;
        }

        $given = trim((string) ($name['given'] ?? ''));
        $family = trim(implode(' ', array_values(array_filter([
            trim((string) ($name['nonDroppingParticle'] ?? '')),
            trim((string) ($name['family'] ?? '')),
        ], static fn (string $part): bool => $part !== ''))));
        $suffix = trim((string) ($name['suffix'] ?? ''));

        if ($family !== '' && $given !== '') {
            $display = $family . ', ' . $given;
        } else {
            $display = $family !== '' ? $family : $given;
        }

        return $suffix !== '' && $display !== '' ? $display . ', ' . $suffix : $display;
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
     * @return list<array{id:string, type:string, title:string, issuedDate:array{year:?int, parts:list<int>, display:string, literal:string, rangeParts?:list<list<int>>}, display:string}>
     */
    private static function relatedItemSummaries(mixed $value, string $id, string $field = 'relatedItems'): array
    {
        if ($value === null || $value === []) {
            return [];
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . ' must be a list');
        }

        $summaries = [];
        foreach ($value as $index => $related) {
            if (!is_array($related)) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . '[' . $index . '] must be an object');
            }

            $relatedId = self::firstStringField($related, ['id', 'citation-key']);
            $title = self::stringField($related, 'title');
            if ($relatedId === '' && $title === '') {
                throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . '[' . $index . '] must include id or title');
            }

            $issuedDate = self::dateVariable($related['issued'] ?? null, $id, $field . '[' . $index . '].issued');
            $summaries[] = [
                'id' => $relatedId,
                'type' => self::stringField($related, 'type'),
                'title' => $title,
                'issuedDate' => $issuedDate,
                'display' => self::relatedItemSummaryDisplay($relatedId, $title, $issuedDate),
            ];
        }

        return $summaries;
    }

    /**
     * @param array{display:string, literal:string} $issuedDate
     */
    private static function relatedItemSummaryDisplay(string $id, string $title, array $issuedDate): string
    {
        $label = $title !== '' ? $title : $id;
        $date = trim((string) ($issuedDate['display'] ?? ''));

        return $date !== '' ? $label . ' (' . $date . ')' : $label;
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
     * @return list<array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool, annotations:list<array{part:string, value:string}>}>
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
            $short = self::nameString($name['short'] ?? $name['short-form'] ?? $name['literal-short'] ?? $name['shortLiteral'] ?? '');
            $family = self::nameString($name['family'] ?? '');
            $given = self::nameString($name['given'] ?? '');
            $nonDroppingParticle = self::nameString($name['non-dropping-particle'] ?? '');
            $droppingParticle = self::nameString($name['dropping-particle'] ?? '');
            $suffix = self::nameString($name['suffix'] ?? '');
            $etAl = self::boolField($name, 'csl-et-al', false)
                || self::boolField($name, 'etAl', false)
                || self::boolField($name, 'et-al', false);
            if (!$etAl && $literal === '' && $family === '' && $given === '' && $nonDroppingParticle === '' && $droppingParticle === '') {
                throw new \InvalidArgumentException('CSL item ' . $id . ' field ' . $field . '[' . $index . '] has no name content');
            }

            $names[] = [
                'family' => $family,
                'given' => $given,
                'literal' => $literal,
                'short' => $short,
                'nonDroppingParticle' => $nonDroppingParticle,
                'droppingParticle' => $droppingParticle,
                'suffix' => $suffix,
                'commaSuffix' => self::boolField($name, 'comma-suffix', false),
                'staticOrdering' => self::boolField($name, 'static-ordering', false),
                'parseNames' => self::boolField($name, 'parse-names', true),
                'etAl' => $etAl,
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
     * @return list<array{field:string, type:string, label:string, names:list<array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool, annotations:list<array{part:string, value:string}>}>}>
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
     * @return array{year:?int, parts:list<int>, display:string, literal:string, raw?:string, time?:string, endTime?:string, circa?:bool, uncertain?:bool, season?:int, seasonName?:string, openEnded?:string, rangeParts?:list<list<int>>}
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
        $raw = self::stringField($date, 'raw');
        $time = self::dateTimeField($date, 'time', $id, $field);
        $endTime = self::firstDateTimeField($date, ['end-time', 'endTime'], $id, $field);
        $circa = self::boolField($date, 'circa', false);
        $uncertain = self::boolField($date, 'uncertain', false);
        $openEnded = self::openEndedDateBoundary($date, $id, $field);
        $season = self::dateSeasonField($date, $id, $field);
        $dateParts = $date['date-parts'] ?? null;
        if ($dateParts === null || $dateParts === []) {
            if ($openEnded !== '') {
                throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . ' open-ended date must include date-parts');
            }

            if ($season !== null) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . ' season date must include date-parts');
            }

            $normalized = [
                'year' => null,
                'parts' => [],
                'display' => $literal,
                'literal' => $literal,
            ];
            if ($raw !== '') {
                $normalized['raw'] = $raw;
            }

            if ($time !== '') {
                $normalized['time'] = $time;
            }

            if ($endTime !== '') {
                $normalized['endTime'] = $endTime;
            }

            if ($circa) {
                $normalized['circa'] = true;
            }

            if ($uncertain) {
                $normalized['uncertain'] = true;
            }

            return $normalized;
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
        if ($season !== null) {
            foreach ($rangeParts as $rangeIndex => $rangePart) {
                if (count($rangePart) !== 1) {
                    throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . ' season date-parts[' . $rangeIndex . '] must contain only a year');
                }
            }
        }

        $normalized = [
            'year' => $parts[0],
            'parts' => $parts,
            'display' => self::formatDatePartsRange($rangeParts, $season),
            'literal' => '',
        ];
        if ($season !== null) {
            $normalized['season'] = $season;
            $normalized['seasonName'] = self::dateSeasonName($season);
        }

        if (count($rangeParts) > 1) {
            if ($openEnded !== '') {
                throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . ' open-ended date-parts must contain one endpoint');
            }

            $normalized['rangeParts'] = $rangeParts;
        }

        if ($openEnded !== '') {
            $normalized['openEnded'] = $openEnded;
            $normalized['display'] = self::formatOpenEndedDatePartsRange($rangeParts, $openEnded, $season);
        }

        if ($raw !== '') {
            $normalized['raw'] = $raw;
        }

        if ($time !== '') {
            $normalized['time'] = $time;
        }

        if ($endTime !== '') {
            $normalized['endTime'] = $endTime;
        }

        if ($circa) {
            $normalized['circa'] = true;
        }

        if ($uncertain) {
            $normalized['uncertain'] = true;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $date
     */
    private static function openEndedDateBoundary(array $date, string $id, string $field): string
    {
        foreach (['open-ended', 'openEnded'] as $key) {
            if (!array_key_exists($key, $date) || $date[$key] === null || $date[$key] === '') {
                continue;
            }

            if (!is_scalar($date[$key])) {
                throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . ' open-ended field must be scalar');
            }

            $boundary = strtolower(trim(str_replace('_', '-', (string) $date[$key])));
            if ($boundary === 'start' || $boundary === 'end') {
                return $boundary;
            }

            throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . ' open-ended field must be start or end');
        }

        return '';
    }

    /**
     * @param array<string, mixed> $date
     */
    private static function dateSeasonField(array $date, string $id, string $field): ?int
    {
        if (!array_key_exists('season', $date) || $date['season'] === null || $date['season'] === '') {
            return null;
        }

        $season = $date['season'];
        if (!is_int($season) && !(is_string($season) && preg_match('/^\d+$/', $season) === 1)) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . ' season must be numeric');
        }

        $number = (int) $season;
        if ($number < 1 || $number > 4) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . ' season must be between 1 and 4');
        }

        return $number;
    }

    /**
     * @param array<string, array<string, mixed>> $dates
     */
    private static function dateMarkerSummary(array $dates): string
    {
        $parts = [];
        foreach ($dates as $label => $date) {
            $status = self::dateMarkerStatus($date);
            if ($status === '') {
                continue;
            }

            $raw = trim((string) ($date['raw'] ?? ''));
            $parts[] = $label . ' ' . $status . ($raw === '' ? '' : ' (' . $raw . ')');
        }

        return $parts === [] ? '' : 'Date markers: ' . implode('; ', $parts);
    }

    /**
     * @param array<string, mixed> $date
     */
    private static function dateMarkerStatus(array $date): string
    {
        $circa = ($date['circa'] ?? false) === true;
        $uncertain = ($date['uncertain'] ?? false) === true;
        if ($circa && $uncertain) {
            return 'circa and uncertain';
        }

        if ($circa) {
            return 'circa';
        }

        return $uncertain ? 'uncertain' : '';
    }

    /**
     * @param array<string, array<string, mixed>> $dates
     */
    private static function dateTimeSummary(array $dates): string
    {
        $parts = [];
        foreach ($dates as $label => $date) {
            $time = trim((string) ($date['time'] ?? ''));
            $endTime = trim((string) ($date['endTime'] ?? ''));
            if ($time === '' && $endTime === '') {
                continue;
            }

            $parts[] = $label . ' ' . ($time !== '' ? $time : '?') . ($endTime === '' ? '' : '/' . $endTime);
        }

        return $parts === [] ? '' : 'Date times: ' . implode('; ', $parts);
    }

    /**
     * @param array<string, array<string, mixed>> $dates
     */
    private static function dateSeasonSummary(array $dates): string
    {
        $parts = [];
        foreach ($dates as $label => $date) {
            $season = $date['season'] ?? null;
            if (!is_int($season)) {
                continue;
            }

            $parts[] = $label . ' ' . self::dateSeasonName($season);
        }

        return $parts === [] ? '' : 'Date seasons: ' . implode('; ', $parts);
    }

    private static function dateSeasonName(int $season): string
    {
        return match ($season) {
            1 => 'Spring',
            2 => 'Summer',
            3 => 'Autumn',
            4 => 'Winter',
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $date
     */
    private static function firstDateTimeField(array $date, array $keys, string $id, string $field): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $date)) {
                continue;
            }

            return self::dateTimeField($date, $key, $id, $field);
        }

        return '';
    }

    /**
     * @param array<string, mixed> $date
     */
    private static function dateTimeField(array $date, string $key, string $id, string $field): string
    {
        $value = $date[$key] ?? '';
        if ($value === null || $value === '') {
            return '';
        }

        if (!is_scalar($value)) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . '.' . $key . ' must be scalar');
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d{2}(?::\d{2}(?::\d{2})?)?(?:Z|[+-]\d{2}:\d{2})?$/', $value) !== 1) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' ' . $field . '.' . $key . ' must be a normalized time string');
        }

        return $value;
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
    private static function formatDateParts(array $parts, ?int $season = null): string
    {
        if ($season !== null) {
            return self::dateSeasonName($season) . ' ' . sprintf('%04d', $parts[0]);
        }

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
    private static function formatDatePartsRange(array $rangeParts, ?int $season = null): string
    {
        $formatted = array_map(
            static fn (array $parts): string => self::formatDateParts($parts, $season),
            $rangeParts
        );

        return implode('/', $formatted);
    }

    /**
     * @param list<list<int>> $rangeParts
     */
    private static function formatOpenEndedDatePartsRange(array $rangeParts, string $boundary, ?int $season = null): string
    {
        $display = self::formatDatePartsRange($rangeParts, $season);

        return $boundary === 'start' ? '/' . $display : $display . '/';
    }

    /**
     * @return array{seenIds:array<string, bool>, previousUnit:array{single:bool, first:array<string, mixed>|null}|null, noteCounter:int, firstNoteById:array<string, int>, lastNoteById:array<string, array{index:int, type:string}>}
     */
    private function emptyCitationPositionState(): array
    {
        return [
            'seenIds' => [],
            'previousUnit' => null,
            'noteCounter' => 0,
            'firstNoteById' => [],
            'lastNoteById' => [],
        ];
    }

    /**
     * @param array{seenIds:array<string, bool>, previousUnit:array{single:bool, first:array<string, mixed>|null}|null, noteCounter:int, firstNoteById:array<string, int>, lastNoteById:array<string, array{index:int, type:string}>} $state
     */
    private function annotateCitationPositions(AstNode $node, array &$state, ?array $noteContext = null): AstNode
    {
        if ($node->type === 'note') {
            $noteContext = $this->citationNoteContext($node, $state);
        }

        if ($node->type === 'citation') {
            [$annotated, $info] = $this->annotateCitationPosition($node, $state, null, true, $noteContext);
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

                [$annotated, $info] = $this->annotateCitationPosition($child, $state, $previousInUnit, $children === [], $noteContext);
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
            $annotated = $this->annotateCitationPositions($child, $state, $noteContext);
            $children[] = $annotated;
            $changed = $changed || $annotated !== $child;
        }

        return $changed ? new AstNode($node->type, $node->attrs, $children) : $node;
    }

    /**
     * @param array{seenIds:array<string, bool>, previousUnit:array{single:bool, first:array<string, mixed>|null}|null, noteCounter:int, firstNoteById:array<string, int>, lastNoteById:array<string, array{index:int, type:string}>} $state
     * @param array<string, mixed>|null $previousInUnit
     * @return array{AstNode, array<string, mixed>}
     */
    private function annotateCitationPosition(AstNode $citation, array &$state, ?array $previousInUnit, bool $firstInUnit, ?array $noteContext): array
    {
        $info = $this->citationPositionInfo($citation, $noteContext);
        $position = $this->citationPositionForInfo($info, $state, $previousInUnit, $firstInUnit);
        $attrs = [
            ...$citation->attrs,
            'cslStyleClass' => $this->style->styleClass(),
            'cslPosition' => $position['position'],
            'cslPositionTests' => $position['tests'],
        ];
        if (is_int($info['noteIndex'])) {
            $attrs['cslNoteIndex'] = $info['noteIndex'];
            $attrs['cslNoteType'] = $info['noteType'];
            if ($info['id'] !== '' && !array_key_exists('cslFirstReferenceNoteNumber', $attrs)) {
                $attrs['cslFirstReferenceNoteNumber'] = $this->firstReferenceNoteNumberForInfo($info, $state);
            }
        }

        $annotated = new AstNode('citation', $attrs, $citation->children);

        if ($info['id'] !== '') {
            $state['seenIds'][$info['id']] = true;
            if (is_int($info['noteIndex']) && !isset($state['firstNoteById'][$info['id']])) {
                $state['firstNoteById'][$info['id']] = (int) $info['noteIndex'];
            }
        }

        return [$annotated, $info];
    }

    /**
     * @param array<string, mixed> $info
     * @param array{firstNoteById:array<string, int>} $state
     */
    private function firstReferenceNoteNumberForInfo(array $info, array $state): int
    {
        $id = (string) ($info['id'] ?? '');
        $noteIndex = $info['noteIndex'] ?? null;
        if ($id === '' || !is_int($noteIndex)) {
            return 0;
        }

        return $state['firstNoteById'][$id] ?? (int) $noteIndex;
    }

    /**
     * @param array{seenIds:array<string, bool>, previousUnit:array{single:bool, first:array<string, mixed>|null}|null, noteCounter:int, firstNoteById:array<string, int>, lastNoteById:array<string, array{index:int, type:string}>} $state
     * @param list<array<string, mixed>> $unit
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

        foreach ($known as $info) {
            if (!is_int($info['noteIndex'] ?? null)) {
                continue;
            }

            if (!isset($state['firstNoteById'][(string) $info['id']])) {
                $state['firstNoteById'][(string) $info['id']] = (int) $info['noteIndex'];
            }

            $state['lastNoteById'][(string) $info['id']] = [
                'index' => (int) $info['noteIndex'],
                'type' => (string) ($info['noteType'] ?? 'footnote'),
            ];
        }
    }

    /**
     * @return array{id:string, locatorLabel:string, locatorValue:string, locatorKey:string, noteIndex:int|null, noteType:string}
     */
    private function citationPositionInfo(AstNode $citation, ?array $noteContext = null): array
    {
        $id = (string) $citation->attr('id', '');
        if ($id !== '' && !isset($this->itemsById[$id])) {
            $id = '';
        } elseif ($id !== '') {
            $id = $this->canonicalCitationId($id);
        }

        $locator = $this->citationLocatorParts($citation);
        $noteContext = $this->citationExplicitNoteContext($citation) ?? $noteContext;

        return [
            'id' => $id,
            'locatorLabel' => $locator['label'],
            'locatorValue' => $locator['value'],
            'locatorKey' => $locator['label'] . "\n" . $locator['value'],
            'noteIndex' => is_array($noteContext) ? (int) ($noteContext['index'] ?? 0) : null,
            'noteType' => is_array($noteContext) ? (string) ($noteContext['type'] ?? 'footnote') : '',
        ];
    }

    /**
     * @param array{seenIds:array<string, bool>, previousUnit:array{single:bool, first:array<string, mixed>|null}|null, noteCounter:int, firstNoteById:array<string, int>, lastNoteById:array<string, array{index:int, type:string}>} $state
     * @return array{index:int, type:string}
     */
    private function citationNoteContext(AstNode $note, array &$state): array
    {
        $explicit = $this->explicitNoteIndexFromNode($note);
        if ($explicit !== null) {
            $state['noteCounter'] = max((int) ($state['noteCounter'] ?? 0), $explicit);

            return [
                'index' => $explicit,
                'type' => $this->normalizedNoteType((string) $note->attr('sourceType', $note->attr('noteType', 'footnote'))),
            ];
        }

        $state['noteCounter'] = (int) ($state['noteCounter'] ?? 0) + 1;

        return [
            'index' => $state['noteCounter'],
            'type' => $this->normalizedNoteType((string) $note->attr('sourceType', $note->attr('noteType', 'footnote'))),
        ];
    }

    /**
     * @return array{index:int, type:string}|null
     */
    private function citationExplicitNoteContext(AstNode $citation): ?array
    {
        $index = $this->explicitNoteIndexFromNode($citation);
        if ($index === null) {
            return null;
        }

        return [
            'index' => $index,
            'type' => $this->normalizedNoteType((string) $citation->attr('cslNoteType', $citation->attr('noteType', $citation->attr('sourceType', 'footnote')))),
        ];
    }

    private function explicitNoteIndexFromNode(AstNode $node): ?int
    {
        foreach (['cslNoteIndex', 'noteIndex', 'noteNumber'] as $attribute) {
            $value = $node->attr($attribute);
            if (is_int($value) && $value >= 1) {
                return $value;
            }

            if (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1 && (int) $value >= 1) {
                return (int) $value;
            }
        }

        return null;
    }

    private function normalizedNoteType(string $type): string
    {
        $type = strtolower(trim($type));

        return in_array($type, ['footnote', 'endnote'], true) ? $type : 'footnote';
    }

    /**
     * @param array<string, mixed> $info
     * @param array{seenIds:array<string, bool>, previousUnit:array{single:bool, first:array<string, mixed>|null}|null, noteCounter:int, lastNoteById:array<string, array{index:int, type:string}>} $state
     * @param array<string, mixed>|null $previousInUnit
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
            return $this->withNearNotePositionTest(['position' => 'subsequent', 'tests' => ['subsequent']], $info, $state);
        }

        $precedingHasLocator = $preceding['locatorValue'] !== '';
        $currentHasLocator = $info['locatorValue'] !== '';
        if (!$precedingHasLocator) {
            return $this->withNearNotePositionTest($currentHasLocator
                ? ['position' => 'ibid-with-locator', 'tests' => ['subsequent', 'ibid', 'ibid-with-locator']]
                : ['position' => 'ibid', 'tests' => ['subsequent', 'ibid']], $info, $state);
        }

        if (!$currentHasLocator) {
            return $this->withNearNotePositionTest(['position' => 'subsequent', 'tests' => ['subsequent']], $info, $state);
        }

        if ($preceding['locatorKey'] === $info['locatorKey']) {
            return $this->withNearNotePositionTest(['position' => 'ibid', 'tests' => ['subsequent', 'ibid']], $info, $state);
        }

        return $this->withNearNotePositionTest(['position' => 'ibid-with-locator', 'tests' => ['subsequent', 'ibid', 'ibid-with-locator']], $info, $state);
    }

    /**
     * @param array{position:string, tests:list<string>} $position
     * @param array<string, mixed> $info
     * @param array{lastNoteById:array<string, array{index:int, type:string}>} $state
     * @return array{position:string, tests:list<string>}
     */
    private function withNearNotePositionTest(array $position, array $info, array $state): array
    {
        if (!$this->citationInfoIsNearNote($info, $state)) {
            return $position;
        }

        if (!in_array('subsequent', $position['tests'], true)) {
            $position['tests'][] = 'subsequent';
        }
        if (!in_array('near-note', $position['tests'], true)) {
            $position['tests'][] = 'near-note';
        }

        return $position;
    }

    /**
     * @param array<string, mixed> $info
     * @param array{lastNoteById:array<string, array{index:int, type:string}>} $state
     */
    private function citationInfoIsNearNote(array $info, array $state): bool
    {
        $id = (string) ($info['id'] ?? '');
        if ($id === '' || !is_int($info['noteIndex'] ?? null)) {
            return false;
        }

        $previous = $state['lastNoteById'][$id] ?? null;
        if (!is_array($previous)) {
            return false;
        }

        $currentIndex = (int) $info['noteIndex'];
        $previousIndex = (int) ($previous['index'] ?? 0);
        if ($previousIndex < 1 || $currentIndex < $previousIndex) {
            return false;
        }

        if ((string) ($previous['type'] ?? 'footnote') !== (string) ($info['noteType'] ?? 'footnote')) {
            return false;
        }

        $options = $this->style->citationOptions();
        $nearNoteDistance = max(0, (int) ($options['nearNoteDistance'] ?? 5));

        return ($currentIndex - $previousIndex) <= $nearNoteDistance;
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
            [$node, $info] = $this->annotateCitationPosition($citation, $state, $previousInUnit, $annotated === [], null);
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

        $yearSuffixes = $this->yearSuffixesForIds(
            $ids,
            $this->nameDisambiguationCountsForCitationList($citations),
            $this->givenNameDisambiguationModesForCitationList($citations)
        );
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
     * @param list<AstNode> $citations
     * @return list<AstNode>
     */
    private function annotateCitationNameDisambiguationForCluster(array $citations): array
    {
        if (($this->style->citationOptions()['disambiguateAddNames'] ?? false) !== true) {
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

        return $this->annotateCitationListNameDisambiguation(
            $citations,
            $this->nameDisambiguationCountsForIds($ids, $this->givenNameDisambiguationModesForCitationList($citations))
        );
    }

    /**
     * @param list<AstNode> $citations
     * @return array<string, string>
     */
    private function givenNameDisambiguationModesForCitationList(array $citations): array
    {
        $ids = [];
        $modes = [];
        foreach ($citations as $citation) {
            if (!$citation instanceof AstNode || $citation->type !== 'citation') {
                return [];
            }

            $id = (string) $citation->attr('id', '');
            if ($id === '' || !isset($this->itemsById[$id])) {
                continue;
            }

            $canonicalId = $this->canonicalCitationId($id);
            if (!in_array($canonicalId, $ids, true)) {
                $ids[] = $canonicalId;
            }

            $mode = strtolower(trim((string) $citation->attr('cslGivenNameDisambiguation', '')));
            if (in_array($mode, ['initial', 'full'], true)) {
                $modes[$canonicalId] = $mode;
            }
        }

        foreach ($this->givenNameDisambiguationModesForIds($ids) as $id => $mode) {
            $modes[$id] = $modes[$id] ?? $mode;
        }

        return $modes;
    }

    /**
     * @param list<AstNode> $citations
     * @return array<string, int>
     */
    private function nameDisambiguationCountsForCitationList(array $citations): array
    {
        $ids = [];
        $counts = [];
        $givenModes = [];
        foreach ($citations as $citation) {
            if (!$citation instanceof AstNode || $citation->type !== 'citation') {
                return [];
            }

            $id = (string) $citation->attr('id', '');
            if ($id === '' || !isset($this->itemsById[$id])) {
                continue;
            }

            $canonicalId = $this->canonicalCitationId($id);
            if (!in_array($canonicalId, $ids, true)) {
                $ids[] = $canonicalId;
            }

            $mode = strtolower(trim((string) $citation->attr('cslGivenNameDisambiguation', '')));
            if (in_array($mode, ['initial', 'full'], true)) {
                $givenModes[$canonicalId] = $mode;
            }

            $count = $citation->attr('cslDisambiguateNameCount');
            if (is_int($count) && $count > 1) {
                $counts[$canonicalId] = $count;
            }
        }

        foreach ($this->nameDisambiguationCountsForIds($ids, $givenModes) as $id => $count) {
            $counts[$id] = $counts[$id] ?? $count;
        }

        return $counts;
    }

    /**
     * @param list<AstNode> $citations
     * @param array<string, int> $counts
     * @return list<AstNode>
     */
    private function annotateCitationListNameDisambiguation(array $citations, array $counts): array
    {
        if ($counts === []) {
            return $citations;
        }

        $annotated = [];
        foreach ($citations as $citation) {
            if (array_key_exists('cslDisambiguateNameCount', $citation->attrs)) {
                $annotated[] = $citation;
                continue;
            }

            $id = (string) $citation->attr('id', '');
            $canonicalId = $this->canonicalCitationId($id);
            $count = $counts[$canonicalId] ?? 0;
            if ($count < 2) {
                $annotated[] = $citation;
                continue;
            }

            $annotated[] = new AstNode($citation->type, [
                ...$citation->attrs,
                'cslDisambiguateNameCount' => $count,
            ], $citation->children);
        }

        return $annotated;
    }

    /**
     * @param array<string, int> $counts
     */
    private function annotateCitationNameDisambiguation(AstNode $node, array $counts): AstNode
    {
        if ($node->type === 'citation') {
            if (array_key_exists('cslDisambiguateNameCount', $node->attrs)) {
                return $node;
            }

            $id = (string) $node->attr('id', '');
            if ($id === '' || !isset($this->itemsById[$id])) {
                return $node;
            }

            $count = $counts[$this->canonicalCitationId($id)] ?? 0;
            if ($count < 2) {
                return $node;
            }

            return new AstNode($node->type, [
                ...$node->attrs,
                'cslDisambiguateNameCount' => $count,
            ], $node->children);
        }

        if ($node->children === []) {
            return $node;
        }

        $children = [];
        $changed = false;
        foreach ($node->children as $child) {
            $annotated = $this->annotateCitationNameDisambiguation($child, $counts);
            $children[] = $annotated;
            $changed = $changed || $annotated !== $child;
        }

        return $changed ? new AstNode($node->type, $node->attrs, $children) : $node;
    }

    /**
     * @param list<AstNode> $citations
     * @return list<AstNode>
     */
    private function annotateCitationGivenNameDisambiguationForCluster(array $citations): array
    {
        if (!$this->style->citationOptions()['disambiguateAddGivenName']) {
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

        $modes = $this->givenNameDisambiguationModesForIds($ids);
        if ($modes === []) {
            return $citations;
        }

        $annotated = [];
        foreach ($citations as $citation) {
            if (array_key_exists('cslGivenNameDisambiguation', $citation->attrs)) {
                $annotated[] = $citation;
                continue;
            }

            $id = (string) $citation->attr('id', '');
            $canonicalId = $this->canonicalCitationId($id);
            $mode = $modes[$canonicalId] ?? '';
            if ($mode === '') {
                $annotated[] = $citation;
                continue;
            }

            $annotated[] = new AstNode($citation->type, [
                ...$citation->attrs,
                'cslGivenNameDisambiguation' => $mode,
            ], $citation->children);
        }

        return $annotated;
    }

    /**
     * @param array<string, string> $modes
     */
    private function annotateCitationGivenNameDisambiguation(AstNode $node, array $modes): AstNode
    {
        if ($node->type === 'citation') {
            if (array_key_exists('cslGivenNameDisambiguation', $node->attrs)) {
                return $node;
            }

            $id = (string) $node->attr('id', '');
            if ($id === '' || !isset($this->itemsById[$id])) {
                return $node;
            }

            $mode = $modes[$this->canonicalCitationId($id)] ?? '';
            if ($mode === '') {
                return $node;
            }

            return new AstNode($node->type, [
                ...$node->attrs,
                'cslGivenNameDisambiguation' => $mode,
            ], $node->children);
        }

        if ($node->children === []) {
            return $node;
        }

        $children = [];
        $changed = false;
        foreach ($node->children as $child) {
            $annotated = $this->annotateCitationGivenNameDisambiguation($child, $modes);
            $children[] = $annotated;
            $changed = $changed || $annotated !== $child;
        }

        return $changed ? new AstNode($node->type, $node->attrs, $children) : $node;
    }

    /**
     * @param list<AstNode> $citations
     * @return list<AstNode>
     */
    private function annotateCitationDisambiguationForCluster(array $citations): array
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

        $givenModes = $this->givenNameDisambiguationModesForCitationList($citations);
        $nameCounts = $this->nameDisambiguationCountsForCitationList($citations);
        $disambiguatingIds = $this->disambiguatingCitationIdsForIds($ids, $nameCounts, $givenModes);
        $annotated = [];
        foreach ($citations as $citation) {
            if (array_key_exists('cslDisambiguate', $citation->attrs)) {
                $annotated[] = $citation;
                continue;
            }

            $id = (string) $citation->attr('id', '');
            $canonicalId = $this->canonicalCitationId($id);
            $needsDisambiguation = ($disambiguatingIds[$canonicalId] ?? false)
                || trim((string) $citation->attr('cslYearSuffix', '')) !== '';
            if (!$needsDisambiguation) {
                $annotated[] = $citation;
                continue;
            }

            $annotated[] = new AstNode($citation->type, [
                ...$citation->attrs,
                'cslDisambiguate' => true,
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
     * @param array<string, bool> $disambiguatingIds
     */
    private function annotateCitationDisambiguation(AstNode $node, array $disambiguatingIds): AstNode
    {
        if ($node->type === 'citation') {
            if (array_key_exists('cslDisambiguate', $node->attrs)) {
                return $node;
            }

            $id = (string) $node->attr('id', '');
            if ($id === '' || !isset($this->itemsById[$id])) {
                return $node;
            }

            $canonicalId = $this->canonicalCitationId($id);
            $needsDisambiguation = ($disambiguatingIds[$canonicalId] ?? false)
                || trim((string) $node->attr('cslYearSuffix', '')) !== '';
            if (!$needsDisambiguation) {
                return $node;
            }

            return new AstNode($node->type, [
                ...$node->attrs,
                'cslDisambiguate' => true,
            ], $node->children);
        }

        if ($node->children === []) {
            return $node;
        }

        $children = [];
        $changed = false;
        foreach ($node->children as $child) {
            $annotated = $this->annotateCitationDisambiguation($child, $disambiguatingIds);
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
     * @return array<string, int>
     */
    private function nameDisambiguationCountsForIds(array $ids, array $givenNameModes = []): array
    {
        if (($this->style->citationOptions()['disambiguateAddNames'] ?? false) !== true) {
            return [];
        }

        $groups = [];
        foreach ($ids as $id) {
            $canonicalId = $this->canonicalCitationId($id);
            if (!isset($this->itemsById[$canonicalId])) {
                continue;
            }

            $item = $this->itemsById[$canonicalId];
            if (!$this->citationAuthorLabelUsesEtAl($item)) {
                continue;
            }

            $key = $this->yearSuffixDisambiguationKey($item, 0, $givenNameModes[$canonicalId] ?? '');
            $groups[$key][] = $canonicalId;
        }

        $counts = [];
        foreach ($groups as $groupIds) {
            $groupIds = array_values(array_unique($groupIds));
            if (count($groupIds) < 2) {
                continue;
            }

            $baseCount = 1;
            $maximumCount = 1;
            foreach ($groupIds as $id) {
                $baseCount = max($baseCount, $this->citationAuthorLabelVisibleNameCount($this->itemsById[$id]));
                $maximumCount = max($maximumCount, count($this->citationAuthorLabelNames($this->itemsById[$id])));
            }

            $baseLabels = [];
            foreach ($groupIds as $id) {
                $baseLabels[$id] = $this->citationAuthorLabelWithDisambiguationContext($this->itemsById[$id], 0, $givenNameModes[$id] ?? '');
            }

            $bestCandidate = 0;
            $bestLabels = [];
            $bestDistinctCount = $this->renderedLabelDistinctCount($baseLabels);
            for ($candidate = $baseCount + 1; $candidate <= $maximumCount; $candidate++) {
                $labels = [];
                foreach ($groupIds as $id) {
                    $labels[$id] = $this->citationAuthorLabelWithNameDisambiguationCount($this->itemsById[$id], $candidate, $givenNameModes[$id] ?? '');
                }

                if ($this->renderedLabelsAreUnique($labels)) {
                    $this->addNameDisambiguationCountsForChangedLabels($counts, $groupIds, $labels, $candidate, $baseLabels);
                    $bestCandidate = 0;
                    break;
                }

                $distinctCount = $this->renderedLabelDistinctCount($labels);
                if ($distinctCount > $bestDistinctCount) {
                    $bestCandidate = $candidate;
                    $bestLabels = $labels;
                    $bestDistinctCount = $distinctCount;
                }
            }

            if ($bestCandidate > 0) {
                $this->addNameDisambiguationCountsForChangedLabels($counts, $groupIds, $bestLabels, $bestCandidate, $baseLabels);
            }
        }

        return $counts;
    }

    /**
     * @param array<string, int> $counts
     * @param list<string> $groupIds
     * @param array<string, string> $labels
     * @param array<string, string> $baseLabels
     */
    private function addNameDisambiguationCountsForChangedLabels(array &$counts, array $groupIds, array $labels, int $candidate, array $baseLabels = []): void
    {
        foreach ($groupIds as $id) {
            $baseLabel = $baseLabels[$id] ?? $this->citationAuthorLabel($this->itemsById[$id]);
            if (($labels[$id] ?? '') !== $baseLabel) {
                $counts[$id] = $candidate;
            }
        }
    }

    /**
     * @param array<string, string> $labels
     */
    private function renderedLabelDistinctCount(array $labels): int
    {
        $normalized = [];
        foreach ($labels as $label) {
            $key = $this->normalizedRenderedNameKey($label);
            if (!in_array($key, $normalized, true)) {
                $normalized[] = $key;
            }
        }

        return count($normalized);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function citationAuthorLabelUsesEtAl(array $item): bool
    {
        $names = $this->citationAuthorLabelNames($item);
        $nameCount = count($names);
        if ($nameCount < 2) {
            return false;
        }

        $options = $this->style->citationNameRendering();
        $etAlMin = $options['etAlMin'] ?? null;
        if (!is_int($etAlMin) || $nameCount < $etAlMin) {
            return false;
        }

        return $this->citationAuthorLabelVisibleNameCount($item) < $nameCount;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function citationAuthorLabelVisibleNameCount(array $item): int
    {
        $nameCount = count($this->citationAuthorLabelNames($item));
        if ($nameCount === 0) {
            return 0;
        }

        $options = $this->style->citationNameRendering();
        $etAlMin = $options['etAlMin'] ?? null;
        if (!is_int($etAlMin) || $nameCount < $etAlMin) {
            return $nameCount;
        }

        $etAlUseFirst = $options['etAlUseFirst'] ?? 1;
        if (!is_int($etAlUseFirst)) {
            $etAlUseFirst = 1;
        }

        return max(1, min($etAlUseFirst, $nameCount));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function citationAuthorLabelWithNameDisambiguationCount(array $item, int $count, string $givenNameMode = ''): string
    {
        return $this->citationAuthorLabelWithDisambiguationContext($item, $count, $givenNameMode);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function citationAuthorLabelWithDisambiguationContext(array $item, int $nameCount = 0, string $givenNameMode = ''): string
    {
        $attrs = [];
        if ($nameCount > 1) {
            $attrs['cslDisambiguateNameCount'] = $nameCount;
        }
        $givenNameMode = strtolower(trim($givenNameMode));
        if (in_array($givenNameMode, ['initial', 'full'], true)) {
            $attrs['cslGivenNameDisambiguation'] = $givenNameMode;
        }

        return $this->citationAuthorLabel(
            $item,
            $attrs === [] ? null : new AstNode('citation', $attrs)
        );
    }

    /**
     * @param list<string> $ids
     * @return array<string, string>
     */
    private function givenNameDisambiguationModesForIds(array $ids): array
    {
        $options = $this->style->citationOptions();
        if (($options['disambiguateAddGivenName'] ?? false) !== true) {
            return [];
        }

        $rule = (string) ($options['givenNameDisambiguationRule'] ?? 'by-cite');
        $groups = [];
        foreach ($ids as $id) {
            $canonicalId = $this->canonicalCitationId($id);
            if (!isset($this->itemsById[$canonicalId])) {
                continue;
            }

            $item = $this->itemsById[$canonicalId];
            $key = $rule === 'by-cite'
                ? $this->yearSuffixDisambiguationKey($item)
                : $this->citationAuthorLabel($item);
            $groups[$key][] = $canonicalId;
        }

        $modes = [];
        foreach ($groups as $groupIds) {
            $groupIds = array_values(array_unique($groupIds));
            if (count($groupIds) < 2) {
                continue;
            }

            $candidateModes = str_ends_with($rule, '-with-initials')
                ? ['initial']
                : ['initial', 'full'];
            $baseLabels = [];
            foreach ($groupIds as $id) {
                $baseLabels[$id] = $this->citationAuthorLabel($this->itemsById[$id]);
            }
            $bestMode = '';
            $bestLabels = [];
            $bestDistinctCount = $this->renderedLabelDistinctCount($baseLabels);
            foreach ($candidateModes as $mode) {
                $labels = [];
                foreach ($groupIds as $id) {
                    $labels[$id] = $this->citationAuthorLabelWithGivenNameDisambiguation($this->itemsById[$id], $mode);
                }

                if ($this->renderedLabelsAreUnique($labels)) {
                    foreach ($groupIds as $id) {
                        if ($labels[$id] !== $baseLabels[$id]) {
                            $modes[$id] = $mode;
                        }
                    }
                    $bestMode = '';
                    break;
                }

                $distinctCount = $this->renderedLabelDistinctCount($labels);
                if ($distinctCount > $bestDistinctCount) {
                    $bestMode = $mode;
                    $bestLabels = $labels;
                    $bestDistinctCount = $distinctCount;
                }
            }

            if ($bestMode !== '') {
                foreach ($groupIds as $id) {
                    if (($bestLabels[$id] ?? '') !== $baseLabels[$id]) {
                        $modes[$id] = $bestMode;
                    }
                }
            }
        }

        return $modes;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function citationAuthorLabelWithGivenNameDisambiguation(array $item, string $mode): string
    {
        return $this->citationAuthorLabel(
            $item,
            new AstNode('citation', ['cslGivenNameDisambiguation' => $mode])
        );
    }

    /**
     * @param array<string, string> $labels
     */
    private function renderedLabelsAreUnique(array $labels): bool
    {
        $normalized = array_map(
            fn (string $label): string => $this->normalizedRenderedNameKey($label),
            array_values($labels)
        );

        return count(array_unique($normalized)) === count($normalized);
    }

    /**
     * @param list<string> $ids
     * @return array<string, bool>
     */
    private function disambiguatingCitationIdsForIds(array $ids, array $nameCounts = [], array $givenNameModes = []): array
    {
        $groups = [];
        foreach ($ids as $id) {
            $canonicalId = $this->canonicalCitationId($id);
            if (!isset($this->itemsById[$canonicalId])) {
                continue;
            }

            $groups[$this->yearSuffixDisambiguationKey(
                $this->itemsById[$canonicalId],
                $nameCounts[$canonicalId] ?? 0,
                $givenNameModes[$canonicalId] ?? ''
            )][] = $canonicalId;
        }

        $disambiguating = [];
        foreach ($groups as $groupIds) {
            $groupIds = array_values(array_unique($groupIds));
            if (count($groupIds) < 2) {
                continue;
            }

            foreach ($groupIds as $id) {
                $disambiguating[$id] = true;
            }
        }

        return $disambiguating;
    }

    /**
     * @param list<string> $ids
     * @return array<string, string>
     */
    private function yearSuffixesForIds(array $ids, ?array $nameCounts = null, array $givenNameModes = []): array
    {
        if (!$this->style->citationOptions()['disambiguateAddYearSuffix']) {
            return [];
        }

        if ($givenNameModes === []) {
            $givenNameModes = $this->givenNameDisambiguationModesForIds($ids);
        }
        $nameCounts = $nameCounts ?? $this->nameDisambiguationCountsForIds($ids, $givenNameModes);
        $suffixes = [];
        $groups = [];
        foreach ($ids as $id) {
            $id = $this->canonicalCitationId($id);
            if (!isset($this->itemsById[$id]) || array_key_exists($id, $suffixes)) {
                continue;
            }

            $item = $this->itemsById[$id];
            $suffixes[$id] = '';
            $groups[$this->yearSuffixDisambiguationKey($item, $nameCounts[$id] ?? 0, $givenNameModes[$id] ?? '')][] = $id;
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
    private function yearSuffixDisambiguationKey(array $item, int $nameCount = 0, string $givenNameMode = ''): string
    {
        $withoutSuffix = $this->itemWithYearSuffix($item, '');
        $renderedKey = $this->renderedCitationDisambiguationKey($withoutSuffix, $nameCount, $givenNameMode);
        if ($renderedKey !== '') {
            return $renderedKey;
        }

        $attrs = [];
        if ($nameCount > 1) {
            $attrs['cslDisambiguateNameCount'] = $nameCount;
        }
        $givenNameMode = strtolower(trim($givenNameMode));
        if (in_array($givenNameMode, ['initial', 'full'], true)) {
            $attrs['cslGivenNameDisambiguation'] = $givenNameMode;
        }
        $citation = $attrs !== []
            ? new AstNode('citation', $attrs)
            : null;

        return $this->citationAuthorLabel($withoutSuffix, $citation) . "\n" . $this->citationYear($withoutSuffix);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function renderedCitationDisambiguationKey(array $item, int $nameCount = 0, string $givenNameMode = ''): string
    {
        $elements = $this->style->citationRenderingElements();
        if ($elements === [] || !$this->hasNonNameRenderingElement($elements)) {
            return '';
        }

        $citationAttrs = [
            'id' => (string) ($item['id'] ?? ''),
            'mode' => 'normal',
            'cslYearSuffix' => '',
            'cslDisambiguate' => false,
        ];
        if ($nameCount > 1) {
            $citationAttrs['cslDisambiguateNameCount'] = $nameCount;
        }
        $givenNameMode = strtolower(trim($givenNameMode));
        if (in_array($givenNameMode, ['initial', 'full'], true)) {
            $citationAttrs['cslGivenNameDisambiguation'] = $givenNameMode;
        }

        $citation = new AstNode('citation', $citationAttrs);
        $rendered = $this->renderRenderingElements($elements, $item, 'citation', '', $citation);

        return $this->normalizedRenderedNameKey($rendered);
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

        usort($entries, fn (array $left, array $right): int => $this->compareSortEntries($left, $right, $sortKeys, 'bibliography'));

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

        usort($entries, fn (array $left, array $right): int => $this->compareSortEntries($left, $right, $sortKeys, 'citation'));

        return array_map(static fn (array $entry): AstNode => $entry['node'], $entries);
    }

    /**
     * @param array{index:int, item:array<string, mixed>|null, fallback:string} $left
     * @param array{index:int, item:array<string, mixed>|null, fallback:string} $right
     * @param list<array{sort:string, variable?:string, macro?:string}> $sortKeys
     */
    private function compareSortEntries(array $left, array $right, array $sortKeys, string $scope): int
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
            $leftValue = $this->sortValue($leftItem, $key, (string) $left['fallback'], $scope);
            $rightValue = $this->sortValue($rightItem, $key, (string) $right['fallback'], $scope);
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
    private function sortValue(array $item, array $key, string $fallback, string $scope): string
    {
        $macro = trim((string) ($key['macro'] ?? ''));
        if ($macro !== '') {
            return $this->sortMacroValue($item, $macro, $scope);
        }

        $variable = $this->sortVariable($key);

        return match ($variable) {
            'presort' => $this->normalizeSortText((string) ($item['presort'] ?? '')),
            'sort-key' => $this->normalizeSortText((string) ($item['sortKey'] ?? '')),
            'sort-name' => $this->normalizeSortText((string) ($item['sortName'] ?? '')),
            'sort-title' => $this->normalizeSortText((string) ($item['sortTitle'] ?? '')),
            'sort-year' => $this->sortYearSortValue($item),
            'author' => $this->normalizeSortText($this->sortNameValue($item) !== '' ? $this->sortNameValue($item) : $this->namesSortValue($item['authors'] ?? [], $item['editors'] ?? [])),
            'editor' => $this->normalizeSortText($this->sortNameValue($item) !== '' ? $this->sortNameValue($item) : $this->namesSortValue($item['editors'] ?? [], [])),
            'container-author' => $this->normalizeSortText($this->namesSortValue($item['containerAuthors'] ?? [], [])),
            'issued', 'date' => $this->sortYearSortValue($item) !== '' ? $this->sortYearSortValue($item) : $this->issuedSortValue($item),
            'available-date' => $this->dateSortValue($item, 'available-date'),
            'submitted' => $this->dateSortValue($item, 'submitted'),
            'title' => $this->normalizeSortText($this->sortTitleValue($item) !== '' ? $this->sortTitleValue($item) : (string) $item['title']),
            'short-title' => $this->normalizeSortText($this->sortTitleValue($item) !== '' ? $this->sortTitleValue($item) : (string) $item['shortTitle']),
            'citation-label' => $this->normalizeSortText((string) $item['citationLabel']),
            'shorthand' => $this->normalizeSortText((string) $item['shorthand']),
            'sort-shorthand', 'sortshorthand', 'list-shorthand', 'listshorthand', 'shorthand-list-sort-key' => $this->normalizeSortText((string) ($item['shorthandListSortKey'] ?? $item['sortShorthand'] ?? $item['shorthand'] ?? '')),
            'container-title' => $this->normalizeSortText((string) $item['containerTitle']),
            'issue-title', 'issuetitle' => $this->normalizeSortText((string) ($item['issueTitle'] ?? '')),
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
     * @param array<string, mixed> $item
     */
    private function sortMacroValue(array $item, string $macro, string $scope): string
    {
        $elements = $this->style->macroRenderingElements($macro);
        if ($elements === null) {
            throw new \InvalidArgumentException('CSL references undefined macro: ' . $macro);
        }

        $bibliographyState = null;
        $substitutedVariables = [];
        $value = $this->renderRenderingElementsWithMacroStack(
            $elements,
            $item,
            $scope === 'bibliography' ? 'bibliography' : 'citation',
            '',
            [$macro],
            null,
            $bibliographyState,
            $substitutedVariables
        );

        return $this->normalizeSortText($value);
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

            if (($name['etAl'] ?? false) === true) {
                continue;
            }

            if ((string) ($name['literal'] ?? '') !== '') {
                $parts[] = (string) $name['literal'];
                continue;
            }

            $parts[] = $this->nameSortValue($name);
        }

        return implode(' ', $parts);
    }

    /**
     * @param array<string, mixed> $name
     */
    private function nameSortValue(array $name): string
    {
        $demote = (string) ($this->style->bibliographyNameRendering()['demoteNonDroppingParticle'] ?? 'never');
        $sortParts = in_array($demote, ['sort-only', 'display-and-sort'], true)
            ? [
                (string) ($name['family'] ?? ''),
                (string) ($name['droppingParticle'] ?? ''),
                (string) ($name['nonDroppingParticle'] ?? ''),
                (string) ($name['given'] ?? ''),
                (string) ($name['suffix'] ?? ''),
            ]
            : [
                (string) ($name['nonDroppingParticle'] ?? ''),
                (string) ($name['family'] ?? ''),
                (string) ($name['given'] ?? ''),
                (string) ($name['droppingParticle'] ?? ''),
                (string) ($name['suffix'] ?? ''),
            ];

        return trim(implode(' ', array_filter($sortParts, static fn (string $part): bool => $part !== '')));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function sortNameValue(array $item): string
    {
        return trim((string) ($item['sortName'] ?? ''));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function sortTitleValue(array $item): string
    {
        return trim((string) ($item['sortTitle'] ?? ''));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function issuedSortValue(array $item): string
    {
        return $this->dateSortValue($item, 'issued');
    }

    /**
     * @param array<string, mixed> $item
     */
    private function dateSortValue(array $item, string $variable): string
    {
        $date = $this->dateVariableForRendering($item, $variable);
        $parts = is_array($date) && isset($date['parts']) && is_array($date['parts']) ? $date['parts'] : [];
        if ($parts !== []) {
            return sprintf('%+08d-%02d-%02d', (int) ($parts[0] ?? 0), (int) ($parts[1] ?? 0), (int) ($parts[2] ?? 0));
        }

        return $this->normalizeSortText(is_array($date) ? (string) ($date['display'] ?? $date['literal'] ?? '') : '');
    }

    /**
     * @param array<string, mixed> $item
     */
    private function sortYearSortValue(array $item): string
    {
        $sortYear = trim((string) ($item['sortYear'] ?? ''));
        if ($sortYear === '') {
            return '';
        }

        if (preg_match('/^-?\d+$/', $sortYear) === 1) {
            return sprintf('%+08d-00-00', (int) $sortYear);
        }

        return $this->normalizeSortText($sortYear);
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
        if ($mode === '' || count($citations) < 2) {
            return null;
        }

        if ($mode === 'citation-number') {
            return $this->renderCollapsedCitationNumberEntries($citations);
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

    /**
     * @param list<AstNode> $citations
     * @return list<string>|null
     */
    private function renderCollapsedCitationNumberEntries(array $citations): ?array
    {
        if (!$this->citationNumberCollapseLayoutIsPure()) {
            return null;
        }

        $entries = [];
        $run = [];
        foreach ($citations as $citation) {
            if (!$citation instanceof AstNode || $citation->type !== 'citation') {
                throw new \InvalidArgumentException('Citation cluster entries must be citation AST nodes');
            }

            $entry = $this->collapseableCitationNumberEntry($citation);
            if ($entry === null) {
                $this->appendCollapsedCitationNumberRun($entries, $run);
                $run = [];
                $entries[] = $this->renderCitationEntry($citation);
                continue;
            }

            if ($run === [] || (int) $entry['number'] === (int) $run[count($run) - 1]['number'] + 1) {
                $run[] = $entry;
                continue;
            }

            $this->appendCollapsedCitationNumberRun($entries, $run);
            $run = [$entry];
        }

        $this->appendCollapsedCitationNumberRun($entries, $run);

        return $entries;
    }

    private function citationCollapseMode(): string
    {
        $options = $this->style->citationOptions();
        $mode = (string) ($options['collapse'] ?? '');

        return in_array($mode, ['citation-number', 'year', 'year-suffix', 'year-suffix-ranged'], true) ? $mode : '';
    }

    private function citationNumberCollapseLayoutIsPure(): bool
    {
        $elements = $this->style->citationRenderingElements();
        if ($elements === []) {
            return false;
        }

        $shape = $this->citationNumberCollapseRenderingShape($elements);

        return $shape['citationNumbers'] === 1 && !$shape['unsupported'];
    }

    /**
     * @param list<array<string, mixed>> $elements
     * @param list<string> $macroStack
     * @return array{citationNumbers:int, unsupported:bool}
     */
    private function citationNumberCollapseRenderingShape(array $elements, array $macroStack = []): array
    {
        $shape = ['citationNumbers' => 0, 'unsupported' => false];
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            $childShape = $this->citationNumberCollapseRenderingElementShape($element, $macroStack);
            $shape['citationNumbers'] += $childShape['citationNumbers'];
            $shape['unsupported'] = $shape['unsupported'] || $childShape['unsupported'];
        }

        return $shape;
    }

    /**
     * @param array<string, mixed> $element
     * @param list<string> $macroStack
     * @return array{citationNumbers:int, unsupported:bool}
     */
    private function citationNumberCollapseRenderingElementShape(array $element, array $macroStack): array
    {
        $type = (string) ($element['type'] ?? '');
        if ($type === 'group') {
            if ($this->renderingElementHasLocalDecoration($element)) {
                return ['citationNumbers' => 0, 'unsupported' => true];
            }

            $children = $element['children'] ?? [];

            return is_array($children)
                ? $this->citationNumberCollapseRenderingShape($children, $macroStack)
                : ['citationNumbers' => 0, 'unsupported' => true];
        }

        if ($type === 'number') {
            $variable = strtolower(trim((string) ($element['variable'] ?? '')));
            $form = strtolower(trim((string) ($element['form'] ?? 'numeric')));

            return [
                'citationNumbers' => $variable === 'citation-number' ? 1 : 0,
                'unsupported' => $variable !== 'citation-number'
                    || $form !== 'numeric'
                    || $this->renderingElementHasLocalDecoration($element),
            ];
        }

        if ($type === 'text') {
            if (isset($element['macro']) && is_string($element['macro'])) {
                $name = $element['macro'];
                if (in_array($name, $macroStack, true) || $this->renderingElementHasLocalDecoration($element)) {
                    return ['citationNumbers' => 0, 'unsupported' => true];
                }

                $macro = $this->style->macroRenderingElements($name);

                return is_array($macro)
                    ? $this->citationNumberCollapseRenderingShape($macro, [...$macroStack, $name])
                    : ['citationNumbers' => 0, 'unsupported' => true];
            }

            $variable = strtolower(trim((string) ($element['variable'] ?? '')));

            return [
                'citationNumbers' => $variable === 'citation-number' ? 1 : 0,
                'unsupported' => $variable !== 'citation-number' || $this->renderingElementHasLocalDecoration($element),
            ];
        }

        return ['citationNumbers' => 0, 'unsupported' => true];
    }

    /**
     * @param array<string, mixed> $element
     */
    private function renderingElementHasLocalDecoration(array $element): bool
    {
        return (string) ($element['prefix'] ?? '') !== ''
            || (string) ($element['suffix'] ?? '') !== ''
            || (string) ($element['display'] ?? '') !== ''
            || (string) ($element['textCase'] ?? '') !== ''
            || $this->renderingFormatting($element) !== []
            || ($element['quotes'] ?? false) === true
            || ($element['stripPeriods'] ?? false) === true;
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
     * @return array{citation:AstNode, number:int, text:string}|null
     */
    private function collapseableCitationNumberEntry(AstNode $citation): ?array
    {
        $id = (string) $citation->attr('id', '');
        $item = $this->itemsById[$id] ?? null;
        if ($item === null || (string) $citation->attr('mode', 'normal') !== 'normal') {
            return null;
        }

        if ($this->citationPrefix($citation) !== '' || $this->citationSuffix($citation) !== '') {
            return null;
        }

        $item = $this->itemWithCitationContext($item, $citation);
        $number = $this->citationNumberValue($item, $citation);
        if (preg_match('/^\d+$/', $number) !== 1) {
            return null;
        }

        return [
            'citation' => $citation,
            'number' => (int) $number,
            'text' => $number,
        ];
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
            'author' => $this->citationAuthorLabel($item, $citation),
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
     * @param list<array{citation:AstNode, number:int, text:string}> $run
     */
    private function appendCollapsedCitationNumberRun(array &$entries, array $run): void
    {
        if ($run === []) {
            return;
        }

        if (count($run) === 1) {
            $entries[] = $this->renderCitationEntry($run[0]['citation']);

            return;
        }

        $entries[] = $this->collapsedCitationNumberRunText($run);
    }

    /**
     * @param list<array{number:int, text:string}> $run
     */
    private function collapsedCitationNumberRunText(array $run): string
    {
        return (string) $run[0]['text'] . "\u{2013}" . (string) $run[count($run) - 1]['text'];
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
        $author = $this->citationAuthorLabel($item, $citation);
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
    private function renderCustomBibliographyEntry(array $item, ?array &$bibliographyState = null): ?string
    {
        $elements = $this->style->bibliographyRenderingElements();
        if (!$this->hasNonNameRenderingElement($elements)) {
            return null;
        }

        return $this->style->formatBibliographyEntry(
            $this->renderRenderingElements($elements, $item, 'bibliography', $this->style->bibliographyDelimiter(), null, $bibliographyState)
        );
    }

    /**
     * @param array<string, mixed> $item
     * @return list<array{display:string, text:string, formatting?:array<string, string>}>
     */
    private function bibliographyDisplayParts(array $item): array
    {
        $substitutedVariables = [];

        return $this->bibliographyDisplayPartsForElements($this->style->bibliographyRenderingElements(), $item, [], $substitutedVariables);
    }

    /**
     * @param list<array<string, mixed>> $elements
     * @param array<string, mixed> $item
     * @param list<string> $macroStack
     * @param array<string, bool> $substitutedVariables
     * @return list<array{display:string, text:string, formatting?:array<string, string>}>
     */
    private function bibliographyDisplayPartsForElements(array $elements, array $item, array $macroStack = [], array &$substitutedVariables = []): array
    {
        $parts = [];
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            foreach ($this->bibliographyDisplayPartsForElement($element, $item, $macroStack, $substitutedVariables) as $part) {
                $parts[] = $part;
            }
        }

        return $parts;
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     * @param list<string> $macroStack
     * @param array<string, bool> $substitutedVariables
     * @return list<array{display:string, text:string, formatting?:array<string, string>}>
     */
    private function bibliographyDisplayPartsForElement(array $element, array $item, array $macroStack = [], array &$substitutedVariables = []): array
    {
        if ($this->renderingElementSuppressedBySubstitute($element, $substitutedVariables)) {
            return [];
        }

        $display = $this->renderingDisplay($element);
        if ($display !== '') {
            $bibliographyState = null;
            $value = $this->renderRenderingElement($element, $item, 'bibliography', $macroStack, null, $bibliographyState, $substitutedVariables);

            if ($value === '') {
                return [];
            }

            $part = ['display' => $display, 'text' => $value];
            $formatting = $this->renderingFormatting($element);
            if ($formatting !== []) {
                $part['formatting'] = $formatting;
            }

            return [$part];
        }

        $type = (string) ($element['type'] ?? '');
        if ($type === 'group') {
            $children = $element['children'] ?? [];

            return is_array($children)
                ? $this->bibliographyDisplayPartsForElements($children, $item, $macroStack, $substitutedVariables)
                : [];
        }

        if ($type === 'text' && isset($element['macro']) && is_string($element['macro'])) {
            $name = $element['macro'];
            if (in_array($name, $macroStack, true)) {
                throw new \InvalidArgumentException('CSL macro recursion detected: ' . implode(' -> ', [...$macroStack, $name]));
            }

            $children = $this->style->macroRenderingElements($name);

            return is_array($children)
                ? $this->bibliographyDisplayPartsForElements($children, $item, [...$macroStack, $name], $substitutedVariables)
                : [];
        }

        if ($type === 'names') {
            [$names] = $this->namesForRenderingVariableWithSource(
                $item,
                (string) ($element['variable'] ?? 'author editor')
            );
            if ($names !== []) {
                return [];
            }

            $substitute = $element['substitute'] ?? [];
            if (!is_array($substitute)) {
                return [];
            }

            foreach ($substitute as $substituteElement) {
                if (!is_array($substituteElement)) {
                    continue;
                }

                $branchSubstitutedVariables = $substitutedVariables;
                $bibliographyState = null;
                $value = ((string) ($substituteElement['type'] ?? '')) === 'names'
                    ? $this->renderNamesElementValue($substituteElement, $item, 'bibliography', false, $bibliographyState, null, $branchSubstitutedVariables)
                    : $this->renderRenderingElement($substituteElement, $item, 'bibliography', $macroStack, null, $bibliographyState, $branchSubstitutedVariables);
                if ($value === '') {
                    continue;
                }

                $parts = $this->bibliographyDisplayPartsForElement($substituteElement, $item, $macroStack, $substitutedVariables);
                $this->markSubstituteRenderedVariables($substituteElement, $item, 'bibliography', $substitutedVariables, $macroStack);
                foreach ($branchSubstitutedVariables as $variable => $rendered) {
                    if ($rendered) {
                        $substitutedVariables[$variable] = true;
                    }
                }

                return $parts;
            }
        }

        if ($type === 'choose') {
            foreach (($element['branches'] ?? []) as $branch) {
                if (!is_array($branch) || !$this->chooseBranchMatches($branch, $item, 'bibliography')) {
                    continue;
                }

                $children = $branch['children'] ?? [];

                return is_array($children)
                    ? $this->bibliographyDisplayPartsForElements($children, $item, $macroStack, $substitutedVariables)
                    : [];
            }

            $children = $element['else'] ?? [];

            return is_array($children)
                ? $this->bibliographyDisplayPartsForElements($children, $item, $macroStack, $substitutedVariables)
                : [];
        }

        return [];
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
     * @param array<string, mixed> $element
     * @return array<string, string>
     */
    private function renderingFormatting(array $element): array
    {
        $formatting = $element['formatting'] ?? [];
        if (!is_array($formatting)) {
            return [];
        }

        $attributes = [];
        foreach ($formatting as $key => $value) {
            if (!is_string($key) || !is_string($value) || $value === '') {
                continue;
            }

            $attributes[$key] = $value;
        }

        return $attributes;
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

            if ($type === 'names') {
                if (!$this->namesVariableIsDefaultCreatorFallback((string) ($element['variable'] ?? 'author editor'))) {
                    return true;
                }

                continue;
            }

            return true;
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
    private function citationAuthorLabel(array $item, ?AstNode $citation = null): string
    {
        $names = $this->citationAuthorLabelNames($item);
        if ($names === []) {
            $title = (string) $item['title'];

            return $title === '' ? (string) $item['id'] : $title;
        }

        return $this->renderNameList($names, $this->style->citationNameRendering(), false, $citation);
    }

    /**
     * @param array<string, mixed> $item
     * @return list<array<string, mixed>>
     */
    private function citationAuthorLabelNames(array $item): array
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

        return is_array($names) ? array_values($names) : [];
    }

    /**
     * @param array<string, mixed> $item
     */
    private function citationYear(array $item): string
    {
        $date = $item['issuedDate'] ?? null;
        if (is_array($date) && isset($date['year']) && $date['year'] !== null && isset($date['openEnded'])) {
            $year = (string) $date['year'];
            $boundary = (string) $date['openEnded'];
            if ($boundary === 'start' || $boundary === 'end') {
                return $this->appendYearSuffix($boundary === 'start' ? '/' . $year : $year . '/', $item);
            }
        }

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
    private function bibliographyAuthors(array $item, ?array &$bibliographyState = null): string
    {
        $names = $item['authors'];
        if ($names === []) {
            $names = $item['editors'];
        }

        if (!is_array($names) || $names === []) {
            return '';
        }

        return $this->renderNameList($names, $this->style->bibliographyNameRendering(), true, null, $bibliographyState);
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
            ['chairs', 'Chaired by', 'chair'],
            ['collectionEditors', 'Collection edited by', 'collection-editor'],
            ['composers', 'Composed by', 'composer'],
            ['contributors', 'Contributed by', 'contributor'],
            ['editorTranslators', 'Edited and translated by', 'editor-translator'],
            ['executiveProducers', 'Executive produced by', 'executive-producer'],
            ['guests', 'Guest:', 'guest'],
            ['hosts', 'Hosted by', 'host'],
            ['narrators', 'Narrated by', 'narrator'],
            ['performers', 'Performed by', 'performer'],
            ['producers', 'Produced by', 'producer'],
            ['recipients', 'Recipient:', 'recipient'],
            ['scriptWriters', 'Script written by', 'script-writer'],
            ['compilers', 'Compiled by', 'compiler'],
            ['curators', 'Curated by', 'curator'],
            ['directors', 'Directed by', 'director'],
            ['editorialDirectors', 'Editorial direction by', 'editorial-director'],
            ['illustrators', 'Illustrated by', 'illustrator'],
            ['interviewers', 'Interview by', 'interviewer'],
            ['reviewedAuthors', 'Reviewed author:', 'reviewed-author'],
            ['redactors', 'Redacted by', 'redactor'],
            ['founders', 'Founded by', 'founder'],
            ['continuators', 'Continued by', 'continuator'],
            ['revisers', 'Revised by', 'reviser'],
            ['collaborators', 'Collaboration by', 'collaborator'],
            ['commentators', 'Commentary by', 'commentator'],
            ['annotators', 'Annotated by', 'annotator'],
            ['containerAuthors', 'Container author:', 'container-author'],
            ['introductionAuthors', 'Introduction by', 'introduction'],
            ['forewordAuthors', 'Foreword by', 'foreword'],
            ['afterwordAuthors', 'Afterword by', 'afterword'],
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
     * @param list<array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool}> $names
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
            'redactor' => 'Redacted by',
            'founder' => 'Founded by',
            'continuator' => 'Continued by',
            'reviser' => 'Revised by',
            'collaborator' => 'Collaboration by',
            'organizer' => 'Organized by',
            'commentator' => 'Commentary by',
            'annotator' => 'Annotated by',
            'executive-producer' => 'Executive produced by',
            'guest' => 'Guest:',
            'host' => 'Hosted by',
            'narrator' => 'Narrated by',
            'performer' => 'Performed by',
            'producer' => 'Produced by',
            'script-writer' => 'Script written by',
            'introduction' => 'Introduction by',
            'foreword' => 'Foreword by',
            'afterword' => 'Afterword by',
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
            'reviewed-author',
            'redactor',
            'founder',
            'continuator',
            'reviser',
            'collaborator',
            'commentator',
            'annotator',
            'executive-producer',
            'guest',
            'host',
            'narrator',
            'performer',
            'producer',
            'script-writer',
            'introduction',
            'foreword',
            'afterword' => $this->normalizedEditorialRoleType($type),
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
            'executiveproducer', 'executive-producer' => 'executive-producer',
            'scriptwriter', 'script-writer' => 'script-writer',
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
        $eventPlaceList = $item['eventPlaceList'] ?? [];
        $multipleEventPlaces = is_array($eventPlaceList) && count(array_filter(
            array_map(static fn (mixed $place): string => trim((string) $place), $eventPlaceList),
            static fn (string $place): bool => $place !== ''
        )) > 1;
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
            $parts[] = $this->localizedEventBibliographyPart('event', 'Event', $eventTitle);
        }
        if ($eventTitleAddon !== '') {
            $parts[] = $this->localizedEventBibliographyPart('event-title-addon', 'Event addendum', $eventTitleAddon);
        }
        if ($eventType !== '') {
            $parts[] = $this->localizedEventBibliographyPart('event-type', 'Event type', $eventType);
        }
        if (is_array($eventOrganizers) && $eventOrganizers !== []) {
            $parts[] = $this->localizedEventBibliographyLabel('event-organizer', 'Event organizer') . ': ' . rtrim($this->renderNameList($eventOrganizers, $this->style->bibliographyNameRendering(), true), '.') . '.';
        }
        if ($eventPlace !== '') {
            $parts[] = $this->localizedEventBibliographyPart('event-place', $multipleEventPlaces ? 'Event places' : 'Event place', $eventPlace, $multipleEventPlaces);
        }
        if (is_array($eventDate) && (string) ($eventDate['display'] ?? '') !== '') {
            $parts[] = $this->localizedEventBibliographyLabel('event-date', 'Event date') . ' ' . (string) $eventDate['display'] . '.';
        }

        return $parts;
    }

    private function localizedEventBibliographyPart(string $termName, string $fallbackLabel, string $value, bool $plural = false): string
    {
        return $this->localizedEventBibliographyLabel($termName, $fallbackLabel, $plural) . ': ' . $this->withTerminalPunctuation($value);
    }

    private function localizedEventBibliographyLabel(string $termName, string $fallbackLabel, bool $plural = false): string
    {
        $label = trim($this->style->term($termName, 'long', $plural));

        return $label !== '' ? $label : $fallbackLabel;
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

        $containerTitleShort = (string) ($item['containerTitleShort'] ?? '');
        if ($containerTitleShort !== '') {
            $parts[] = 'Journal abbreviation: ' . rtrim($containerTitleShort, '.') . '.';
        }

        $issueTitle = (string) ($item['issueTitle'] ?? '');
        if ($issueTitle !== '') {
            $parts[] = 'Issue title: ' . rtrim($issueTitle, '.') . '.';
        }

        $issueTitleAddon = (string) ($item['issueTitleAddon'] ?? '');
        if ($issueTitleAddon !== '') {
            $parts[] = 'Issue title addendum: ' . rtrim($issueTitleAddon, '.') . '.';
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

        $isan = (string) ($item['isan'] ?? '');
        if ($isan !== '') {
            $parts[] = 'ISAN ' . $isan . '.';
        }

        $ismn = (string) ($item['ismn'] ?? '');
        if ($ismn !== '') {
            $parts[] = 'ISMN ' . $ismn . '.';
        }

        $isrn = (string) ($item['isrn'] ?? '');
        if ($isrn !== '') {
            $parts[] = 'ISRN ' . $isrn . '.';
        }

        $iswc = (string) ($item['iswc'] ?? '');
        if ($iswc !== '') {
            $parts[] = 'ISWC ' . $iswc . '.';
        }

        $pmid = (string) ($item['pmid'] ?? '');
        if ($pmid !== '') {
            $parts[] = 'PMID ' . $pmid . '.';
        }

        $pmcid = (string) ($item['pmcid'] ?? '');
        if ($pmcid !== '') {
            $parts[] = 'PMCID ' . $pmcid . '.';
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
            ['reviewedTitle', 'Reviewed title'],
            ['reprintTitle', 'Reprint title'],
            ['translatedTitle', 'Translated title'],
            ['citationAliasSummary', 'Citation aliases'],
            ['sortShorthand', 'Sort shorthand'],
            ['presort', 'Presort'],
            ['indexTitle', 'Index title'],
            ['indexSortTitle', 'Index sort title'],
            ['labelAlpha', 'Label alpha'],
            ['labelTitle', 'Label title'],
            ['extraDate', 'Extra date'],
            ['extraTitle', 'Extra title'],
            ['references', 'References'],
            ['dimensions', 'Dimensions'],
            ['scale', 'Scale'],
            ['version', 'Version'],
            ['medium', 'Medium'],
            ['callNumber', 'Call number'],
            ['pagination', 'Pagination'],
            ['bookPagination', 'Book pagination'],
            ['thesisType', 'Thesis type'],
            ['articleNumber', 'Article number'],
            ['entrySubtype', 'Entry subtype'],
            ['biblatexGender', 'BibLaTeX gender'],
            ['status', 'Status'],
            ['annotation', 'Annotation'],
            ['note', 'Note'],
            ['addendum', 'Addendum'],
            ['authorType', 'Author type'],
            ['containerAuthorType', 'Container author type'],
            ['dateAddon', 'Date addendum'],
            ['originalDateAddon', 'Original date addendum'],
            ['eventDateAddon', 'Event date addendum'],
            ['accessedDateAddon', 'Accessed date addendum'],
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

        $dateMarkerSummary = trim((string) ($item['dateMarkerSummary'] ?? ''));
        if ($dateMarkerSummary !== '') {
            $parts[] = $this->withTerminalPunctuation($dateMarkerSummary);
        }

        $dateTimeSummary = trim((string) ($item['dateTimeSummary'] ?? ''));
        if ($dateTimeSummary !== '') {
            $parts[] = $this->withTerminalPunctuation($dateTimeSummary);
        }

        $dateSeasonSummary = trim((string) ($item['dateSeasonSummary'] ?? ''));
        if ($dateSeasonSummary !== '') {
            $parts[] = $this->withTerminalPunctuation($dateSeasonSummary);
        }

        $fieldAnnotationSummary = trim((string) ($item['biblatexFieldAnnotationSummary'] ?? ''));
        if ($fieldAnnotationSummary !== '') {
            $parts[] = 'BibLaTeX field annotations: ' . $this->withTerminalPunctuation($fieldAnnotationSummary);
        }

        $biblatexOptionSummary = trim((string) ($item['biblatexOptionSummary'] ?? ''));
        if ($biblatexOptionSummary !== '') {
            $parts[] = 'BibLaTeX options: ' . $this->withTerminalPunctuation($biblatexOptionSummary);
        }

        $biblatexLanguageOptionSummary = trim((string) ($item['biblatexLanguageOptionSummary'] ?? ''));
        if ($biblatexLanguageOptionSummary !== '') {
            $parts[] = 'BibLaTeX language options: ' . $this->withTerminalPunctuation($biblatexLanguageOptionSummary);
        }

        $referenceContextSummary = trim((string) ($item['biblatexReferenceContextSummary'] ?? ''));
        if ($referenceContextSummary !== '') {
            $parts[] = 'BibLaTeX reference context: ' . $this->withTerminalPunctuation($referenceContextSummary);
        }

        $keywordSummary = trim((string) ($item['keywordSummary'] ?? ''));
        if ($keywordSummary !== '') {
            $parts[] = 'Keywords: ' . $this->withTerminalPunctuation($keywordSummary);
        }

        $customFieldSummary = trim((string) ($item['biblatexCustomFieldSummary'] ?? ''));
        if ($customFieldSummary !== '') {
            $parts[] = 'BibLaTeX custom fields: ' . $this->withTerminalPunctuation($customFieldSummary);
        }

        $customListSummary = trim((string) ($item['biblatexCustomListSummary'] ?? ''));
        if ($customListSummary !== '') {
            $parts[] = 'BibLaTeX custom lists: ' . $this->withTerminalPunctuation($customListSummary);
        }

        $customNameSummary = trim((string) ($item['biblatexCustomNameSummary'] ?? ''));
        if ($customNameSummary !== '') {
            $parts[] = 'BibLaTeX custom names: ' . $this->withTerminalPunctuation($customNameSummary);
        }

        return $parts;
    }

    /**
     * @param array<string, mixed> $item
     * @return list<string>
     */
    private function relatedBibliographyParts(array $item): array
    {
        $summary = $this->relatedSummary($item);
        $parts = $summary === '' ? [] : [$this->withTerminalPunctuation($summary)];
        $options = $item['relatedOptions'] ?? [];
        if (is_array($options)) {
            $options = array_values(array_filter(
                array_map(static fn (mixed $option): string => trim((string) $option), $options),
                static fn (string $option): bool => $option !== ''
            ));
            if ($options !== []) {
                $parts[] = 'Related options: ' . implode('; ', $options) . '.';
            }
        }

        return $parts;
    }

    /**
     * @param array<string, mixed> $item
     * @return list<string>
     */
    private function xrefBibliographyParts(array $item): array
    {
        $summary = $this->xrefSummary($item);

        return $summary === '' ? [] : [$this->withTerminalPunctuation($summary)];
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
     * @param array<string, mixed> $item
     */
    private function relatedSummary(array $item): string
    {
        $values = $this->relatedSummaryValues($item);
        if ($values === '') {
            return '';
        }

        $type = trim((string) ($item['relatedType'] ?? ''));
        $label = trim((string) ($item['relatedString'] ?? ''));
        $hasExplicitLabel = $label !== '';
        if (!$hasExplicitLabel) {
            $label = self::defaultRelatedTypeLabel($type);
        }

        if ($type !== '' && ($hasExplicitLabel || $label === 'Related source')) {
            $label .= ' (' . $type . ')';
        }

        return $label . ': ' . $values;
    }

    private static function defaultRelatedTypeLabel(string $type): string
    {
        return match (strtolower(str_replace('_', '-', trim($type)))) {
            'license' => 'License',
            default => 'Related source',
        };
    }

    /**
     * @param array<string, mixed> $item
     */
    private function relatedSummaryValues(array $item): string
    {
        $values = [];
        $relatedItems = $item['relatedItems'] ?? [];
        if (is_array($relatedItems)) {
            foreach ($relatedItems as $relatedItem) {
                if (!is_array($relatedItem)) {
                    continue;
                }

                $display = trim((string) ($relatedItem['display'] ?? ''));
                if ($display !== '') {
                    $values[] = $display;
                }
            }
        }

        $missing = $item['missingRelatedKeys'] ?? [];
        if (is_array($missing)) {
            foreach ($missing as $key) {
                $key = trim((string) $key);
                if ($key !== '') {
                    $values[] = 'missing: ' . $key;
                }
            }
        }

        if ($values === []) {
            $relatedKeys = $item['relatedKeys'] ?? [];
            if (is_array($relatedKeys)) {
                $values = array_values(array_filter(
                    array_map(static fn (mixed $key): string => trim((string) $key), $relatedKeys),
                    static fn (string $key): bool => $key !== ''
                ));
            }
        }

        if ($values === []) {
            return '';
        }

        return implode('; ', $values);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function xrefSummary(array $item): string
    {
        $values = $this->xrefSummaryValues($item);

        return $values === '' ? '' : 'Xref: ' . $values;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function xrefSummaryValues(array $item): string
    {
        $values = [];
        $xrefItems = $item['xrefItems'] ?? [];
        if (is_array($xrefItems)) {
            foreach ($xrefItems as $xrefItem) {
                if (!is_array($xrefItem)) {
                    continue;
                }

                $display = trim((string) ($xrefItem['display'] ?? ''));
                if ($display !== '') {
                    $values[] = $display;
                }
            }
        }

        $missing = $item['missingXrefKeys'] ?? [];
        if (is_array($missing)) {
            foreach ($missing as $key) {
                $key = trim((string) $key);
                if ($key !== '') {
                    $values[] = 'missing: ' . $key;
                }
            }
        }

        if ($values === []) {
            $xrefKeys = $item['xrefKeys'] ?? [];
            if (is_array($xrefKeys)) {
                $values = array_values(array_filter(
                    array_map(static fn (mixed $key): string => trim((string) $key), $xrefKeys),
                    static fn (string $key): bool => $key !== ''
                ));
            }
        }

        if ($values === []) {
            return '';
        }

        return implode('; ', $values);
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
            ['chairs', 'Chair'],
            ['containerAuthors', 'Container author'],
            ['collectionEditors', 'Collection editor'],
            ['composers', 'Composer'],
            ['contributors', 'Contributor'],
            ['editorTranslators', 'Editor-translator'],
            ['executiveProducers', 'Executive producer'],
            ['eventOrganizers', 'Event organizer'],
            ['guests', 'Guest'],
            ['hosts', 'Host'],
            ['narrators', 'Narrator'],
            ['originalAuthors', 'Original author'],
            ['performers', 'Performer'],
            ['producers', 'Producer'],
            ['recipients', 'Recipient'],
            ['scriptWriters', 'Script writer'],
            ['compilers', 'Compiler'],
            ['curators', 'Curator'],
            ['directors', 'Director'],
            ['editorialDirectors', 'Editorial director'],
            ['illustrators', 'Illustrator'],
            ['interviewers', 'Interviewer'],
            ['reviewedAuthors', 'Reviewed author'],
            ['redactors', 'Redactor'],
            ['founders', 'Founder'],
            ['continuators', 'Continuator'],
            ['revisers', 'Reviser'],
            ['collaborators', 'Collaborator'],
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
    private function renderRenderingElements(array $elements, array $item, string $scope, string $delimiter, ?AstNode $citation = null, ?array &$bibliographyState = null): string
    {
        $substitutedVariables = [];

        return $this->renderRenderingElementsWithMacroStack($elements, $item, $scope, $delimiter, [], $citation, $bibliographyState, $substitutedVariables);
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
            [$output, $separator] = $this->moveFollowingPunctuationInsideClosingQuote($output, $separator);
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
     * @param list<array<string, mixed>> $elements
     * @param array<string, mixed> $item
     * @param list<string> $macroStack
     * @param array<string, bool> $substitutedVariables
     */
    private function renderRenderingElementsWithMacroStack(array $elements, array $item, string $scope, string $delimiter, array $macroStack, ?AstNode $citation = null, ?array &$bibliographyState = null, array &$substitutedVariables = []): string
    {
        $rendered = [];
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            $value = $this->renderRenderingElement($element, $item, $scope, $macroStack, $citation, $bibliographyState, $substitutedVariables);
            if ($value !== '') {
                $rendered[] = $value;
            }
        }

        return $this->joinRenderedElements($rendered, $delimiter);
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, bool> $substitutedVariables
     */
    private function renderingElementSuppressedBySubstitute(array $element, array $substitutedVariables): bool
    {
        if ($substitutedVariables === []) {
            return false;
        }

        $type = (string) ($element['type'] ?? '');
        if (!in_array($type, ['text', 'date', 'number', 'names', 'label'], true)) {
            return false;
        }

        if (!isset($element['variable']) || !is_string($element['variable'])) {
            return false;
        }

        return $this->renderingVariableIsSuppressedBySubstitute($element['variable'], $substitutedVariables);
    }

    /**
     * @param array<string, bool> $substitutedVariables
     */
    private function renderingVariableIsSuppressedBySubstitute(string $variable, array $substitutedVariables): bool
    {
        $variables = $this->renderingVariableNames($variable);
        if ($variables === []) {
            return false;
        }

        foreach ($variables as $name) {
            if (!isset($substitutedVariables[$name])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function renderingVariableNames(string $variable): array
    {
        return array_values(array_unique(array_filter(
            array_map(
                fn (string $part): string => $this->normalizedRenderingVariableName($part),
                preg_split('/\s+/', trim($variable)) ?: []
            ),
            static fn (string $part): bool => $part !== ''
        )));
    }

    private function normalizedRenderingVariableName(string $variable): string
    {
        return str_replace('_', '-', strtolower(trim($variable)));
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, bool> $substitutedVariables
     * @param list<string> $macroStack
     */
    private function markSubstituteRenderedVariables(array $element, array $item, string $scope, array &$substitutedVariables, array $macroStack = [], ?AstNode $citation = null): void
    {
        foreach ($this->substituteRenderedElementVariables($element, $item, $scope, $macroStack, $citation, $substitutedVariables) as $variable) {
            $substitutedVariables[$variable] = true;
        }
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     * @param list<string> $macroStack
     * @param array<string, bool> $substitutedVariables
     * @return list<string>
     */
    private function substituteRenderedElementVariables(array $element, array $item, string $scope, array $macroStack = [], ?AstNode $citation = null, array $substitutedVariables = []): array
    {
        if ($this->renderingElementSuppressedBySubstitute($element, $substitutedVariables)) {
            return [];
        }

        $type = (string) ($element['type'] ?? '');
        if ($type === 'names') {
            return $this->substituteRenderedNamesElementVariables($element, $item, $scope, $macroStack, $citation, $substitutedVariables);
        }

        if (in_array($type, ['text', 'date', 'number', 'label'], true) && isset($element['variable']) && is_string($element['variable'])) {
            $probeSubstitutedVariables = $substitutedVariables;
            $bibliographyState = null;
            $value = $this->renderRenderingElement($element, $item, $scope, $macroStack, $citation, $bibliographyState, $probeSubstitutedVariables);

            if ($value === '') {
                return [];
            }

            return $this->renderingVariableNames($element['variable']);
        }

        if ($type === 'group') {
            return $this->substituteRenderedElementsVariables(is_array($element['children'] ?? null) ? $element['children'] : [], $item, $scope, $macroStack, $citation, $substitutedVariables);
        }

        if ($type === 'choose') {
            foreach (($element['branches'] ?? []) as $branch) {
                if (!is_array($branch) || !$this->chooseBranchMatches($branch, $item, $scope, $citation)) {
                    continue;
                }

                $children = $branch['children'] ?? [];

                return is_array($children)
                    ? $this->substituteRenderedElementsVariables($children, $item, $scope, $macroStack, $citation, $substitutedVariables)
                    : [];
            }

            if (isset($element['else']) && is_array($element['else'])) {
                return $this->substituteRenderedElementsVariables($element['else'], $item, $scope, $macroStack, $citation, $substitutedVariables);
            }

            return [];
        }

        if ($type === 'text' && isset($element['macro']) && is_string($element['macro'])) {
            $macro = $element['macro'];
            if (in_array($macro, $macroStack, true)) {
                return [];
            }

            $elements = $this->style->macroRenderingElements($macro);

            return is_array($elements)
                ? $this->substituteRenderedElementsVariables($elements, $item, $scope, [...$macroStack, $macro], $citation, $substitutedVariables)
                : [];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     * @param list<string> $macroStack
     * @param array<string, bool> $substitutedVariables
     * @return list<string>
     */
    private function substituteRenderedNamesElementVariables(array $element, array $item, string $scope, array $macroStack = [], ?AstNode $citation = null, array $substitutedVariables = []): array
    {
        $variable = (string) ($element['variable'] ?? 'author editor');
        [$names] = $this->namesForRenderingVariableWithSource($item, $variable);
        if ($names !== []) {
            $probeSubstitutedVariables = $substitutedVariables;
            $bibliographyState = null;
            $value = $this->renderNamesElementValue($element, $item, $scope, false, $bibliographyState, $citation, $probeSubstitutedVariables);

            return $value === '' ? [] : $this->renderingVariableNames($variable);
        }

        $substitute = $element['substitute'] ?? [];
        if (!is_array($substitute)) {
            return [];
        }

        foreach ($substitute as $substituteElement) {
            if (!is_array($substituteElement)) {
                continue;
            }

            $probeSubstitutedVariables = $substitutedVariables;
            $bibliographyState = null;
            $value = ((string) ($substituteElement['type'] ?? '')) === 'names'
                ? $this->renderNamesElementValue($substituteElement, $item, $scope, false, $bibliographyState, $citation, $probeSubstitutedVariables)
                : $this->renderRenderingElement($substituteElement, $item, $scope, $macroStack, $citation, $bibliographyState, $probeSubstitutedVariables);
            if ($value === '') {
                continue;
            }

            return $this->substituteRenderedElementVariables($substituteElement, $item, $scope, $macroStack, $citation, $substitutedVariables);
        }

        return [];
    }

    /**
     * @param list<array<string, mixed>> $elements
     * @param array<string, mixed> $item
     * @param list<string> $macroStack
     * @param array<string, bool> $substitutedVariables
     * @return list<string>
     */
    private function substituteRenderedElementsVariables(array $elements, array $item, string $scope, array $macroStack = [], ?AstNode $citation = null, array $substitutedVariables = []): array
    {
        $variables = [];
        foreach ($elements as $element) {
            if (is_array($element)) {
                $variables = [...$variables, ...$this->substituteRenderedElementVariables($element, $item, $scope, $macroStack, $citation, $substitutedVariables)];
            }
        }

        return array_values(array_unique($variables));
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     * @param array<string, bool> $substitutedVariables
     */
    private function renderRenderingElement(array $element, array $item, string $scope, array $macroStack = [], ?AstNode $citation = null, ?array &$bibliographyState = null, array &$substitutedVariables = []): string
    {
        if ($this->renderingElementSuppressedBySubstitute($element, $substitutedVariables)) {
            return '';
        }

        $type = (string) ($element['type'] ?? '');
        $value = match ($type) {
            'group' => $this->renderGroupElement($element, $item, $scope, $macroStack, $citation, $bibliographyState, $substitutedVariables),
            'text' => $this->renderTextElement($element, $item, $scope, $macroStack, $citation, $bibliographyState, $substitutedVariables),
            'date' => $this->renderDateElement($element, $item, $scope),
            'number' => $this->renderNumberElement($element, $item, $scope, $citation),
            'names' => $this->renderNamesElement($element, $item, $scope, $bibliographyState, $citation, $substitutedVariables),
            'label' => $this->renderLabelElement($element, $item, $scope, $citation),
            'choose' => $this->renderChooseElement($element, $item, $scope, $macroStack, $citation, $bibliographyState, $substitutedVariables),
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
     * @param array<string, bool> $substitutedVariables
     */
    private function renderGroupElement(array $element, array $item, string $scope, array $macroStack = [], ?AstNode $citation = null, ?array &$bibliographyState = null, array &$substitutedVariables = []): string
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
            $value = $this->renderRenderingElement($child, $item, $scope, $macroStack, $citation, $bibliographyState, $substitutedVariables);
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
     * @param array<string, bool> $substitutedVariables
     */
    private function renderTextElement(array $element, array $item, string $scope, array $macroStack = [], ?AstNode $citation = null, ?array &$bibliographyState = null, array &$substitutedVariables = []): string
    {
        if (array_key_exists('macro', $element)) {
            return $this->renderMacroReference((string) $element['macro'], $item, $scope, $macroStack, $citation, $bibliographyState, $substitutedVariables);
        }

        if (array_key_exists('variable', $element)) {
            return $this->renderTextVariableValue(
                $item,
                (string) $element['variable'],
                (string) ($element['form'] ?? 'long'),
                $scope,
                $citation
            );
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
     * @param array<string, bool> $substitutedVariables
     */
    private function renderMacroReference(string $name, array $item, string $scope, array $macroStack, ?AstNode $citation = null, ?array &$bibliographyState = null, array &$substitutedVariables = []): string
    {
        $elements = $this->style->macroRenderingElements($name);
        if ($elements === null) {
            throw new \InvalidArgumentException('CSL references undefined macro: ' . $name);
        }

        if (in_array($name, $macroStack, true)) {
            throw new \InvalidArgumentException('CSL macro recursion detected: ' . implode(' -> ', [...$macroStack, $name]));
        }

        return $this->renderRenderingElementsWithMacroStack($elements, $item, $scope, '', [...$macroStack, $name], $citation, $bibliographyState, $substitutedVariables);
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     * @param list<string> $macroStack
     * @param array<string, bool> $substitutedVariables
     */
    private function renderChooseElement(array $element, array $item, string $scope, array $macroStack, ?AstNode $citation = null, ?array &$bibliographyState = null, array &$substitutedVariables = []): string
    {
        foreach (($element['branches'] ?? []) as $branch) {
            if (!is_array($branch) || !$this->chooseBranchMatches($branch, $item, $scope, $citation)) {
                continue;
            }

            $children = $branch['children'] ?? [];

            return is_array($children)
                ? $this->renderRenderingElementsWithMacroStack($children, $item, $scope, '', $macroStack, $citation, $bibliographyState, $substitutedVariables)
                : '';
        }

        $else = $element['else'] ?? [];

        return is_array($else)
            ? $this->renderRenderingElementsWithMacroStack($else, $item, $scope, '', $macroStack, $citation, $bibliographyState, $substitutedVariables)
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
        if (is_array($types)) {
            $itemType = strtolower(trim((string) ($item['type'] ?? '')));
            foreach ($types as $type) {
                if (is_scalar($type)) {
                    $conditions[] = $itemType !== '' && $itemType === strtolower(trim((string) $type));
                }
            }
        }

        $locators = $branch['locators'] ?? [];
        if (is_array($locators)) {
            foreach ($locators as $locator) {
                if (is_scalar($locator)) {
                    $conditions[] = $this->citationLocatorMatches([$this->normalizedLocatorLabel((string) $locator)], $scope, $citation);
                }
            }
        }

        $positions = $branch['positions'] ?? [];
        if (is_array($positions)) {
            foreach ($positions as $position) {
                if (is_scalar($position)) {
                    $conditions[] = $this->citationPositionMatches((string) $position, $scope, $citation);
                }
            }
        }

        if (($branch['disambiguate'] ?? false) === true) {
            $conditions[] = $this->citationDisambiguateMatches($item, $scope, $citation);
        }

        $isCreatorVariables = $branch['isCreator'] ?? [];
        if (is_array($isCreatorVariables)) {
            foreach ($isCreatorVariables as $variable) {
                if (is_scalar($variable)) {
                    $conditions[] = $this->renderingVariableIsCreator($item, (string) $variable);
                }
            }
        }

        $isNumericVariables = $branch['isNumeric'] ?? [];
        if (is_array($isNumericVariables)) {
            foreach ($isNumericVariables as $variable) {
                if (is_scalar($variable)) {
                    $conditions[] = $this->renderingVariableIsNumeric($item, (string) $variable, $scope, $citation);
                }
            }
        }

        $isDateVariables = $branch['isDate'] ?? [];
        if (is_array($isDateVariables)) {
            foreach ($isDateVariables as $variable) {
                if (is_scalar($variable)) {
                    $conditions[] = $this->renderingVariableIsDate($item, (string) $variable);
                }
            }
        }

        $isUncertainDateVariables = $branch['isUncertainDate'] ?? [];
        if (is_array($isUncertainDateVariables)) {
            foreach ($isUncertainDateVariables as $variable) {
                if (is_scalar($variable)) {
                    $conditions[] = $this->renderingVariableIsUncertainDate($item, (string) $variable);
                }
            }
        }

        $isCircaDateVariables = $branch['isCircaDate'] ?? [];
        if (is_array($isCircaDateVariables)) {
            foreach ($isCircaDateVariables as $variable) {
                if (is_scalar($variable)) {
                    $conditions[] = $this->renderingVariableIsCircaDate($item, (string) $variable);
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

    /**
     * @param list<string> $locators
     */
    private function citationLocatorMatches(array $locators, string $scope, ?AstNode $citation): bool
    {
        if ($scope !== 'citation' || !$citation instanceof AstNode) {
            return false;
        }

        $parts = $this->citationLocatorParts($citation);
        if ($parts['value'] === '') {
            return false;
        }

        return in_array($this->normalizedLocatorLabel($parts['label']), $locators, true);
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
     * @param array<string, mixed> $item
     */
    private function citationDisambiguateMatches(array $item, string $scope, ?AstNode $citation): bool
    {
        if ($scope === 'citation' && $citation instanceof AstNode) {
            $marker = $citation->attr('cslDisambiguate', null);
            if (is_bool($marker)) {
                return $marker;
            }

            if (is_scalar($marker)) {
                $normalized = strtolower(trim((string) $marker));
                if (in_array($normalized, ['1', 'true', 'yes'], true)) {
                    return true;
                }
                if ($normalized === '' || in_array($normalized, ['0', 'false', 'no'], true)) {
                    return false;
                }
            }

            return trim((string) $citation->attr('cslYearSuffix', '')) !== '';
        }

        return ($item['cslDisambiguate'] ?? false) === true;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function renderingVariableIsNumeric(array $item, string $variable, string $scope, ?AstNode $citation = null): bool
    {
        $normalized = strtolower(trim($variable));
        if ($normalized === '') {
            return false;
        }

        $value = $this->renderVariableValue($item, $normalized, $scope, $citation);

        return $value !== '' && $this->cslNumberValueIsNumeric($value);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function renderingVariableIsDate(array $item, string $variable): bool
    {
        $date = $this->dateVariableForRendering($item, $variable);
        if (!is_array($date)) {
            return false;
        }

        return (isset($date['parts']) && is_array($date['parts']) && $date['parts'] !== [])
            || (string) ($date['display'] ?? '') !== ''
            || (string) ($date['literal'] ?? '') !== '';
    }

    /**
     * @param array<string, mixed> $item
     */
    private function renderingVariableIsUncertainDate(array $item, string $variable): bool
    {
        $date = $this->dateVariableForRendering($item, $variable);

        return is_array($date) && (($date['uncertain'] ?? false) === true || ($date['circa'] ?? false) === true);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function renderingVariableIsCircaDate(array $item, string $variable): bool
    {
        $date = $this->dateVariableForRendering($item, $variable);

        return is_array($date) && ($date['circa'] ?? false) === true;
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

        $form = strtolower(trim((string) ($element['form'] ?? '')));
        $datePartsSelection = strtolower(trim((string) ($element['datePartsSelection'] ?? '')));
        if ($form === 'text' || $form === 'numeric') {
            return $this->renderDateForm($date, $form, $scope, $variable, $datePartsSelection);
        }

        if ($datePartsSelection !== '') {
            return $this->renderDateForm($date, 'text', $scope, $variable, $datePartsSelection);
        }

        return $this->renderDateVariable($date, $scope, $variable);
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     * @param array<string, bool> $substitutedVariables
     */
    private function renderNamesElement(array $element, array $item, string $scope, ?array &$bibliographyState = null, ?AstNode $citation = null, array &$substitutedVariables = []): string
    {
        return $this->renderNamesElementValue($element, $item, $scope, true, $bibliographyState, $citation, $substitutedVariables);
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $item
     * @param array<string, bool> $substitutedVariables
     */
    private function renderNamesElementValue(array $element, array $item, string $scope, bool $allowImplicitTitleFallback, ?array &$bibliographyState = null, ?AstNode $citation = null, array &$substitutedVariables = []): string
    {
        $variable = (string) ($element['variable'] ?? 'author editor');
        [$names, $selectedVariable] = $this->namesForRenderingVariableWithSource($item, $variable);
        if ($names === []) {
            $substitute = $element['substitute'] ?? [];
            if (is_array($substitute) && $substitute !== []) {
                foreach ($substitute as $substituteElement) {
                    if (!is_array($substituteElement)) {
                        continue;
                    }

                    $value = ((string) ($substituteElement['type'] ?? '')) === 'names'
                        ? $this->renderNamesElementValue($substituteElement, $item, $scope, false, $bibliographyState, $citation, $substitutedVariables)
                        : $this->renderRenderingElement($substituteElement, $item, $scope, [], $citation, $bibliographyState, $substitutedVariables);
                    if ($value !== '') {
                        $this->markSubstituteRenderedVariables($substituteElement, $item, $scope, $substitutedVariables, [], $citation);

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

        $rendered = $this->renderNameList(
            $names,
            $options,
            $scope === 'bibliography',
            $citation,
            $bibliographyState
        );

        return $this->applyNamesLabel($rendered, $selectedVariable, $names, $options);
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

    private function namesVariableIsDefaultCreatorFallback(string $variable): bool
    {
        $variables = preg_split('/\s+/', strtolower(trim($variable))) ?: [];
        if ($variables === [] || $variables === ['']) {
            return true;
        }

        foreach ($variables as $nameVariable) {
            if (!in_array($nameVariable, ['author', 'editor'], true)) {
                return false;
            }
        }

        return true;
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

        $value = $this->formatCslNumber($value, (string) ($element['form'] ?? 'numeric'));

        if ($variable === 'locator') {
            return $this->formatCslLocatorRanges($value);
        }

        return $variable === 'page' ? $this->formatCslPageRanges($value) : $value;
    }

    private function formatCslNumber(string $value, string $form): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        if ($value === '' || !$this->cslNumberValueCanBeFormatted($value)) {
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
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        if ($value === '') {
            return false;
        }

        $token = '[\p{L}\p{N}]*\d[\p{L}\p{N}]*';

        return preg_match('/^' . $token . '\s*(?:(?:[-\x{2010}-\x{2015}]|,|&)\s*' . $token . '\s*)*$/u', $value) === 1;
    }

    private function cslNumberValueCanBeFormatted(string $value): bool
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
        return $this->style->ordinalSuffixTerm($number) ?? 'th';
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

            $termName = $this->labelTermName($variable, $item);
        }

        $plural = match ((string) ($element['plural'] ?? 'contextual')) {
            'always' => true,
            'never' => false,
            default => $this->labelValueLooksPlural($value),
        };

        return $this->style->term($termName, (string) ($element['form'] ?? 'long'), $plural);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function labelTermName(string $variable, array $item): string
    {
        if ($variable === 'page' || $variable === 'page-first') {
            $pagination = trim((string) ($item['pagination'] ?? ''));
            if ($pagination !== '') {
                return $this->paginationTermName($pagination);
            }
        }

        return match ($variable) {
            'page-first' => 'page',
            'number-of-pages' => 'page',
            'number-of-volumes' => 'volume',
            'chapter-number' => 'chapter',
            'collection-number' => 'number',
            'part-number' => 'part',
            default => $variable,
        };
    }

    private function paginationTermName(string $pagination): string
    {
        $pagination = strtolower(trim($pagination));
        $pagination = str_replace(['_', ' '], '-', $pagination);

        return match ($pagination) {
            'p', 'pp', 'page', 'pages' => 'page',
            'col', 'cols', 'column', 'columns' => 'column',
            'l', 'll', 'line', 'lines' => 'line',
            'para', 'paras', 'paragraph', 'paragraphs' => 'paragraph',
            'sec', 'secs', 'section', 'sections' => 'section',
            'v', 'vv', 'verse', 'verses' => 'verse',
            default => $pagination,
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
            'form' => is_string($options['form'] ?? null) ? $options['form'] : ($defaults['form'] ?? 'long'),
            'etAlMin' => is_int($options['etAlMin'] ?? null) ? $options['etAlMin'] : $defaults['etAlMin'],
            'etAlUseFirst' => is_int($options['etAlUseFirst'] ?? null) ? $options['etAlUseFirst'] : $defaults['etAlUseFirst'],
            'etAlUseLast' => is_bool($options['etAlUseLast'] ?? null) ? $options['etAlUseLast'] : (bool) ($defaults['etAlUseLast'] ?? false),
            'etAlSubsequentMin' => is_int($options['etAlSubsequentMin'] ?? null) ? $options['etAlSubsequentMin'] : ($defaults['etAlSubsequentMin'] ?? null),
            'etAlSubsequentUseFirst' => is_int($options['etAlSubsequentUseFirst'] ?? null) ? $options['etAlSubsequentUseFirst'] : ($defaults['etAlSubsequentUseFirst'] ?? null),
            'delimiterPrecedesEtAl' => is_string($options['delimiterPrecedesEtAl'] ?? null) ? $options['delimiterPrecedesEtAl'] : $defaults['delimiterPrecedesEtAl'],
            'delimiterPrecedesLast' => is_string($options['delimiterPrecedesLast'] ?? null) ? $options['delimiterPrecedesLast'] : ($defaults['delimiterPrecedesLast'] ?? 'contextual'),
            'delimiterPrecedesLastExplicit' => ($options['delimiterPrecedesLastExplicit'] ?? false) === true || ($defaults['delimiterPrecedesLastExplicit'] ?? false) === true,
            'etAl' => $this->normalizedEtAlRenderingOptions(
                is_array($defaults['etAl'] ?? null) ? $defaults['etAl'] : [],
                is_array($options['etAl'] ?? null) ? $options['etAl'] : []
            ),
            'initializeWith' => is_string($options['initializeWith'] ?? null) ? $options['initializeWith'] : $defaults['initializeWith'],
            'initializeWithHyphen' => is_bool($options['initializeWithHyphen'] ?? null) ? $options['initializeWithHyphen'] : ($defaults['initializeWithHyphen'] ?? true),
            'nameAsSortOrder' => is_string($options['nameAsSortOrder'] ?? null) ? $options['nameAsSortOrder'] : $defaults['nameAsSortOrder'],
            'sortSeparator' => is_string($options['sortSeparator'] ?? null) ? $options['sortSeparator'] : ($defaults['sortSeparator'] ?? ', '),
            'demoteNonDroppingParticle' => is_string($options['demoteNonDroppingParticle'] ?? null) ? $options['demoteNonDroppingParticle'] : ($defaults['demoteNonDroppingParticle'] ?? 'never'),
            'nameParts' => array_key_exists('nameParts', $options) && is_array($options['nameParts']) ? $options['nameParts'] : [],
            'institution' => is_array($options['institution'] ?? null) ? $options['institution'] : ($defaults['institution'] ?? null),
            'label' => is_array($options['label'] ?? null) ? $options['label'] : ($defaults['label'] ?? null),
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

        $suffix = (string) ($element['suffix'] ?? '');
        [$value, $suffix] = $this->moveFollowingPunctuationInsideClosingQuote($value, $suffix);

        return (string) ($element['prefix'] ?? '') . $value . $suffix;
    }

    /**
     * @return array{0:string, 1:string}
     */
    private function moveFollowingPunctuationInsideClosingQuote(string $value, string $following): array
    {
        if (!$this->style->punctuationInQuote() || $value === '' || $following === '') {
            return [$value, $following];
        }

        $punctuation = $following[0];
        if ($punctuation !== '.' && $punctuation !== ',') {
            return [$value, $following];
        }

        $closeQuote = $this->style->term('close-quote');
        if ($closeQuote === '' || !str_ends_with($value, $closeQuote)) {
            return [$value, $following];
        }

        $inside = substr($value, 0, -strlen($closeQuote));
        if (preg_match('/[.,!?]\z/u', $inside) !== 1) {
            $inside .= $punctuation;
        }

        $rest = substr($following, 1);
        $following = trim($rest) === '' && $rest !== '' ? ' ' : ltrim($rest);

        return [$inside . $closeQuote, $following];
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
            'locator' => $this->formatCslLocatorRanges($this->citationLocatorParts($citation)['value']),
            'citation-number' => $this->citationNumberValue($item, $citation),
            'first-reference-note-number' => $this->firstReferenceNoteNumberValue($citation),
            'id', 'citation-key' => (string) $item['id'],
            'type' => (string) $item['type'],
            'citation-aliases', 'citation-alias' => implode(', ', is_array($item['citationAliases'] ?? null) ? $item['citationAliases'] : []),
            'citation-alias-summary', 'citation-aliases-summary' => (string) ($item['citationAliasSummary'] ?? ''),
            'citation-label' => (string) $item['citationLabel'],
            'shorthand' => (string) $item['shorthand'],
            'shorthand-intro' => (string) $item['shorthandIntro'],
            'sort-shorthand', 'sortshorthand' => (string) ($item['sortShorthand'] ?? ''),
            'list-shorthand', 'listshorthand', 'shorthand-list-sort-key', 'shorthand-list-sort' => (string) ($item['shorthandListSortKey'] ?? ''),
            'presort' => (string) ($item['presort'] ?? ''),
            'sort-key' => (string) $item['sortKey'],
            'sort-name' => (string) $item['sortName'],
            'sort-title' => (string) $item['sortTitle'],
            'sort-year' => (string) $item['sortYear'],
            'index-title', 'indextitle' => (string) ($item['indexTitle'] ?? ''),
            'index-sort-title', 'indexsorttitle' => (string) ($item['indexSortTitle'] ?? ''),
            'label-alpha', 'labelalpha' => (string) ($item['labelAlpha'] ?? ''),
            'label-title', 'labeltitle' => (string) ($item['labelTitle'] ?? ''),
            'extra-date', 'extradate' => (string) ($item['extraDate'] ?? ''),
            'extra-title', 'extratitle' => (string) ($item['extraTitle'] ?? ''),
            'title' => (string) $item['title'],
            'short-title', 'title-short' => (string) $item['shortTitle'],
            'title-addon' => (string) $item['titleAddon'],
            'translated-title', 'translatedtitle', 'title-translation', 'titletranslation' => (string) ($item['translatedTitle'] ?? ''),
            'reviewed-title', 'reviewedtitle' => (string) ($item['reviewedTitle'] ?? ''),
            'reprint-title', 'reprinttitle' => (string) ($item['reprintTitle'] ?? ''),
            'original-title', 'origtitle' => (string) ($item['originalTitle'] ?? ''),
            'original-title-addon', 'origtitleaddon' => (string) ($item['originalTitleAddon'] ?? ''),
            'container-title' => (string) $item['containerTitle'],
            'container-title-short' => (string) $item['containerTitleShort'],
            'journalabbreviation', 'journal-abbreviation' => (string) $item['journalAbbreviation'],
            'container-title-addon' => (string) $item['containerTitleAddon'],
            'main-title' => (string) $item['mainTitle'],
            'main-title-addon' => (string) $item['mainTitleAddon'],
            'event', 'event-title' => (string) $item['eventTitle'],
            'event-title-addon' => (string) $item['eventTitleAddon'],
            'event-place' => (string) $item['eventPlace'],
            'event-place-list' => implode('; ', is_array($item['eventPlaceList'] ?? null) ? $item['eventPlaceList'] : []),
            'event-type' => (string) $item['eventType'],
            'publisher' => (string) $item['publisher'],
            'publisher-place' => (string) $item['publisherPlace'],
            'publisher-list' => implode('; ', is_array($item['publisherList'] ?? null) ? $item['publisherList'] : []),
            'publisher-place-list' => implode('; ', is_array($item['publisherPlaceList'] ?? null) ? $item['publisherPlaceList'] : []),
            'page' => $this->formatCslPageRanges((string) $item['page']),
            'page-first' => (string) $item['pageFirst'],
            'pagination', 'page-label' => (string) $item['pagination'],
            'book-pagination', 'bookpagination' => (string) $item['bookPagination'],
            'thesis-type', 'thesistype' => (string) ($item['thesisType'] ?? ''),
            'article-number', 'articlenumber', 'eid' => (string) $item['articleNumber'],
            'references' => (string) ($item['references'] ?? ''),
            'dimensions', 'dimension' => (string) ($item['dimensions'] ?? ''),
            'scale' => (string) ($item['scale'] ?? ''),
            'number' => (string) $item['number'],
            'volume' => (string) $item['volume'],
            'issue' => (string) $item['issue'],
            'issue-title', 'issuetitle' => (string) ($item['issueTitle'] ?? ''),
            'issue-title-addon', 'issuetitleaddon' => (string) ($item['issueTitleAddon'] ?? ''),
            'edition' => (string) $item['edition'],
            'collection-title' => (string) $item['collectionTitle'],
            'collection-number' => (string) $item['collectionNumber'],
            'number-of-volumes' => (string) $item['numberOfVolumes'],
            'number-of-pages' => (string) $item['numberOfPages'],
            'chapter-number' => (string) $item['chapterNumber'],
            'part', 'part-number' => (string) $item['part'],
            'genre' => (string) $item['genre'],
            'entry-subtype', 'entrysubtype' => (string) $item['entrySubtype'],
            'gender', 'biblatex-gender', 'biblatexgender' => (string) ($item['biblatexGender'] ?? $item['gender'] ?? ''),
            'biblatex-gender-summary', 'biblatexgendersummary' => (string) ($item['biblatexGenderSummary'] ?? ''),
            'authority' => (string) $item['authority'],
            'jurisdiction' => (string) $item['jurisdiction'],
            'status' => (string) $item['status'],
            'version' => (string) $item['version'],
            'doi' => (string) $item['doi'],
            'url' => (string) $item['url'],
            'url-label', 'url-description', 'urldescription', 'urltitle', 'urllabel' => (string) ($item['urlLabel'] ?? ''),
            'isbn' => (string) $item['isbn'],
            'issn' => (string) $item['issn'],
            'isan' => (string) ($item['isan'] ?? ''),
            'ismn' => (string) ($item['ismn'] ?? ''),
            'isrn' => (string) ($item['isrn'] ?? ''),
            'iswc' => (string) ($item['iswc'] ?? ''),
            'pmid' => (string) ($item['pmid'] ?? ''),
            'pmcid' => (string) ($item['pmcid'] ?? ''),
            'archive' => (string) $item['archive'],
            'archive-place' => (string) $item['archivePlace'],
            'archive_location', 'archive-location' => (string) $item['archiveLocation'],
            'call-number', 'callnumber' => (string) $item['callNumber'],
            'language' => (string) $item['language'],
            'language-list' => implode('; ', is_array($item['languageList'] ?? null) ? $item['languageList'] : []),
            'abstract' => (string) $item['abstract'],
            'annotation', 'annote' => (string) ($item['annotation'] ?? ''),
            'medium' => (string) $item['medium'],
            'note' => (string) $item['note'],
            'addendum' => (string) $item['addendum'],
            'name-addon' => (string) $item['nameAddon'],
            'author-type', 'authortype' => (string) ($item['authorType'] ?? ''),
            'container-author-type', 'bookauthor-type', 'bookauthortype' => (string) ($item['containerAuthorType'] ?? ''),
            'date-addon', 'dateaddendum', 'date-addendum' => (string) ($item['dateAddon'] ?? ''),
            'original-date-addon', 'origdateaddon', 'orig-date-addon', 'originaldateaddon' => (string) ($item['originalDateAddon'] ?? ''),
            'event-date-addon', 'eventdateaddon' => (string) ($item['eventDateAddon'] ?? ''),
            'accessed-date-addon', 'urldateaddon', 'url-date-addon', 'accesseddateaddon' => (string) ($item['accessedDateAddon'] ?? ''),
            'name-annotation-summary' => $this->nameAnnotationSummary($item),
            'date-marker-summary', 'date-status', 'date-status-summary' => (string) ($item['dateMarkerSummary'] ?? ''),
            'date-time-summary', 'time-summary' => (string) ($item['dateTimeSummary'] ?? ''),
            'date-season-summary', 'season-summary' => (string) ($item['dateSeasonSummary'] ?? ''),
            'issued-time', 'date-time' => $this->dateTimeForVariable($item, 'issued'),
            'issued-end-time', 'date-end-time' => $this->dateEndTimeForVariable($item, 'issued'),
            'accessed-time' => $this->dateTimeForVariable($item, 'accessed'),
            'accessed-end-time' => $this->dateEndTimeForVariable($item, 'accessed'),
            'available-time', 'available-date-time' => $this->dateTimeForVariable($item, 'available-date'),
            'available-end-time', 'available-date-end-time' => $this->dateEndTimeForVariable($item, 'available-date'),
            'submitted-time', 'submitted-date-time' => $this->dateTimeForVariable($item, 'submitted'),
            'submitted-end-time', 'submitted-date-end-time' => $this->dateEndTimeForVariable($item, 'submitted'),
            'event-time' => $this->dateTimeForVariable($item, 'event-date'),
            'event-end-time' => $this->dateEndTimeForVariable($item, 'event-date'),
            'original-time' => $this->dateTimeForVariable($item, 'original-date'),
            'original-end-time' => $this->dateEndTimeForVariable($item, 'original-date'),
            'biblatex-field-annotations', 'biblatex-field-annotation-summary', 'biblatex-field-annotations-summary', 'field-annotation-summary' => (string) ($item['biblatexFieldAnnotationSummary'] ?? ''),
            'biblatex-options', 'biblatexoptions' => implode(', ', is_array($item['biblatexOptions'] ?? null) ? $item['biblatexOptions'] : []),
            'biblatex-option-summary', 'biblatex-options-summary', 'biblatexoptionssummary' => (string) ($item['biblatexOptionSummary'] ?? ''),
            'biblatex-language-options', 'biblatexlanguageoptions', 'langidopts', 'language-options' => implode(', ', is_array($item['biblatexLanguageOptions'] ?? null) ? $item['biblatexLanguageOptions'] : []),
            'biblatex-language-option-summary', 'biblatex-language-options-summary', 'biblatexlanguageoptionssummary', 'language-option-summary', 'language-options-summary' => (string) ($item['biblatexLanguageOptionSummary'] ?? ''),
            'refsection', 'ref-section', 'biblatex-refsection', 'biblatexrefsection' => (string) ($item['biblatexRefsection'] ?? ''),
            'refsegment', 'ref-segment', 'biblatex-refsegment', 'biblatexrefsegment' => (string) ($item['biblatexRefsegment'] ?? ''),
            'reference-context', 'biblatex-reference-context', 'biblatex-reference-context-summary', 'biblatexreferencecontextsummary' => (string) ($item['biblatexReferenceContextSummary'] ?? ''),
            'biblatex-custom-fields', 'biblatex-custom-field-summary', 'biblatex-custom-summary' => (string) ($item['biblatexCustomFieldSummary'] ?? ''),
            'usera', 'userb', 'userc', 'userd', 'usere', 'userf', 'verba', 'verbb', 'verbc' => $this->biblatexCustomFieldValue($item, $normalized),
            'biblatex-custom-lists', 'biblatex-custom-list-summary', 'biblatex-custom-lists-summary' => (string) ($item['biblatexCustomListSummary'] ?? ''),
            'lista', 'listb', 'listc', 'listd', 'liste', 'listf' => $this->biblatexCustomListValue($item, $normalized),
            'biblatex-custom-names', 'biblatex-custom-name-summary', 'biblatex-custom-names-summary' => (string) ($item['biblatexCustomNameSummary'] ?? ''),
            'namea', 'nameb', 'namec' => $this->biblatexCustomNameValue($item, $normalized),
            'issued-status', 'issued-date-status' => $this->dateMarkerStatusForVariable($item, 'issued'),
            'accessed-status', 'accessed-date-status' => $this->dateMarkerStatusForVariable($item, 'accessed'),
            'available-status', 'available-date-status' => $this->dateMarkerStatusForVariable($item, 'available-date'),
            'submitted-status', 'submitted-date-status' => $this->dateMarkerStatusForVariable($item, 'submitted'),
            'event-date-status' => $this->dateMarkerStatusForVariable($item, 'event-date'),
            'original-date-status' => $this->dateMarkerStatusForVariable($item, 'original-date'),
            'issued-raw', 'issued-date-raw' => $this->dateRawForVariable($item, 'issued'),
            'accessed-raw', 'accessed-date-raw' => $this->dateRawForVariable($item, 'accessed'),
            'available-raw', 'available-date-raw' => $this->dateRawForVariable($item, 'available-date'),
            'submitted-raw', 'submitted-date-raw' => $this->dateRawForVariable($item, 'submitted'),
            'event-date-raw' => $this->dateRawForVariable($item, 'event-date'),
            'original-date-raw' => $this->dateRawForVariable($item, 'original-date'),
            'issued-season', 'date-season' => $this->dateSeasonForVariable($item, 'issued'),
            'issued-season-name', 'date-season-name' => $this->dateSeasonNameForVariable($item, 'issued'),
            'accessed-season' => $this->dateSeasonForVariable($item, 'accessed'),
            'accessed-season-name' => $this->dateSeasonNameForVariable($item, 'accessed'),
            'available-season', 'available-date-season' => $this->dateSeasonForVariable($item, 'available-date'),
            'available-season-name', 'available-date-season-name' => $this->dateSeasonNameForVariable($item, 'available-date'),
            'submitted-season', 'submitted-date-season' => $this->dateSeasonForVariable($item, 'submitted'),
            'submitted-season-name', 'submitted-date-season-name' => $this->dateSeasonNameForVariable($item, 'submitted'),
            'event-date-season' => $this->dateSeasonForVariable($item, 'event-date'),
            'event-date-season-name' => $this->dateSeasonNameForVariable($item, 'event-date'),
            'original-date-season' => $this->dateSeasonForVariable($item, 'original-date'),
            'original-date-season-name' => $this->dateSeasonNameForVariable($item, 'original-date'),
            'related' => $this->relatedSummaryValues($item),
            'related-summary' => $this->relatedSummary($item),
            'related-keys' => implode(', ', is_array($item['relatedKeys'] ?? null) ? $item['relatedKeys'] : []),
            'related-type', 'relatedtype' => (string) ($item['relatedType'] ?? ''),
            'related-string', 'relatedstring' => (string) ($item['relatedString'] ?? ''),
            'related-options', 'relatedoptions' => implode(', ', is_array($item['relatedOptions'] ?? null) ? $item['relatedOptions'] : []),
            'missing-related-keys' => implode(', ', is_array($item['missingRelatedKeys'] ?? null) ? $item['missingRelatedKeys'] : []),
            'xref' => $this->xrefSummaryValues($item),
            'xref-summary' => $this->xrefSummary($item),
            'xref-keys' => implode(', ', is_array($item['xrefKeys'] ?? null) ? $item['xrefKeys'] : []),
            'missing-xref-keys' => implode(', ', is_array($item['missingXrefKeys'] ?? null) ? $item['missingXrefKeys'] : []),
            'original-publisher', 'origpublisher' => (string) ($item['originalPublisher'] ?? ''),
            'original-publisher-place', 'origlocation', 'origaddress' => (string) ($item['originalPublisherPlace'] ?? ''),
            'original-publisher-list' => implode('; ', is_array($item['originalPublisherList'] ?? null) ? $item['originalPublisherList'] : []),
            'original-publisher-place-list' => implode('; ', is_array($item['originalPublisherPlaceList'] ?? null) ? $item['originalPublisherPlaceList'] : []),
            'original-language', 'origlanguage' => (string) ($item['originalLanguage'] ?? ''),
            'original-language-list' => implode('; ', is_array($item['originalLanguageList'] ?? null) ? $item['originalLanguageList'] : []),
            'keyword', 'keywords' => implode(', ', is_array($item['keywords'] ?? null) ? $item['keywords'] : []),
            'keyword-summary', 'keywords-summary' => (string) ($item['keywordSummary'] ?? ''),
            'issued', 'date' => $this->renderDateVariable($item['issuedDate'] ?? null, $scope, 'issued'),
            'year-suffix' => (string) ($item['yearSuffix'] ?? ($citation instanceof AstNode ? $citation->attr('cslYearSuffix', '') : '')),
            'available-date' => $this->renderDateVariable($item['availableDate'] ?? null, $scope, 'available-date'),
            'submitted' => $this->renderDateVariable($item['submittedDate'] ?? null, $scope, 'submitted'),
            'event-date' => $this->renderDateVariable($item['eventDate'] ?? null, $scope, 'event-date'),
            'accessed' => $this->renderDateVariable($item['accessedDate'] ?? null, $scope, 'accessed'),
            'original-date' => $this->renderDateVariable($item['originalDate'] ?? null, $scope, 'original-date'),
            'short-author' => $this->renderNamesElement(['variable' => 'short-author'], $item, $scope),
            'short-editor' => $this->renderNamesElement(['variable' => 'short-editor'], $item, $scope),
            'author' => $this->renderNamesElement(['variable' => 'author'], $item, $scope),
            'editor' => $this->renderNamesElement(['variable' => 'editor'], $item, $scope),
            'holder' => $this->renderNamesElement(['variable' => 'holder'], $item, $scope),
            'translator' => $this->renderNamesElement(['variable' => 'translator'], $item, $scope),
            'chair' => $this->renderNamesElement(['variable' => 'chair'], $item, $scope),
            'container-author' => $this->renderNamesElement(['variable' => 'container-author'], $item, $scope),
            'collection-editor' => $this->renderNamesElement(['variable' => 'collection-editor'], $item, $scope),
            'composer' => $this->renderNamesElement(['variable' => 'composer'], $item, $scope),
            'contributor' => $this->renderNamesElement(['variable' => 'contributor'], $item, $scope),
            'editor-translator' => $this->renderNamesElement(['variable' => 'editor-translator'], $item, $scope),
            'executive-producer' => $this->renderNamesElement(['variable' => 'executive-producer'], $item, $scope),
            'event-organizer', 'organizer' => $this->renderNamesElement(['variable' => 'event-organizer'], $item, $scope),
            'guest' => $this->renderNamesElement(['variable' => 'guest'], $item, $scope),
            'host' => $this->renderNamesElement(['variable' => 'host'], $item, $scope),
            'narrator' => $this->renderNamesElement(['variable' => 'narrator'], $item, $scope),
            'original-author' => $this->renderNamesElement(['variable' => 'original-author'], $item, $scope),
            'performer' => $this->renderNamesElement(['variable' => 'performer'], $item, $scope),
            'producer' => $this->renderNamesElement(['variable' => 'producer'], $item, $scope),
            'recipient' => $this->renderNamesElement(['variable' => 'recipient'], $item, $scope),
            'script-writer' => $this->renderNamesElement(['variable' => 'script-writer'], $item, $scope),
            'compiler' => $this->renderNamesElement(['variable' => 'compiler'], $item, $scope),
            'curator' => $this->renderNamesElement(['variable' => 'curator'], $item, $scope),
            'director' => $this->renderNamesElement(['variable' => 'director'], $item, $scope),
            'editorial-director' => $this->renderNamesElement(['variable' => 'editorial-director'], $item, $scope),
            'illustrator' => $this->renderNamesElement(['variable' => 'illustrator'], $item, $scope),
            'interviewer' => $this->renderNamesElement(['variable' => 'interviewer'], $item, $scope),
            'reviewed-author' => $this->renderNamesElement(['variable' => 'reviewed-author'], $item, $scope),
            'redactor' => $this->renderNamesElement(['variable' => 'redactor'], $item, $scope),
            'founder' => $this->renderNamesElement(['variable' => 'founder'], $item, $scope),
            'continuator' => $this->renderNamesElement(['variable' => 'continuator'], $item, $scope),
            'reviser' => $this->renderNamesElement(['variable' => 'reviser'], $item, $scope),
            'collaborator' => $this->renderNamesElement(['variable' => 'collaborator'], $item, $scope),
            'commentator' => $this->renderNamesElement(['variable' => 'commentator'], $item, $scope),
            'annotator' => $this->renderNamesElement(['variable' => 'annotator'], $item, $scope),
            'introduction' => $this->renderNamesElement(['variable' => 'introduction'], $item, $scope),
            'foreword' => $this->renderNamesElement(['variable' => 'foreword'], $item, $scope),
            'afterword' => $this->renderNamesElement(['variable' => 'afterword'], $item, $scope),
            'editorial-role-summary' => implode(' ', $this->bibliographyRoleNameParts($item)),
            default => $this->rawVariableValue($item, $variable),
        };
    }

    private function textVariableAcceptsNumberForm(string $variable): bool
    {
        return in_array($variable, [
            'citation-number',
            'first-reference-note-number',
            'locator',
            'page',
            'page-first',
            'number',
            'edition',
            'volume',
            'issue',
            'chapter-number',
            'number-of-pages',
            'number-of-volumes',
            'collection-number',
            'part-number',
            'part',
            'version',
        ], true);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function biblatexCustomFieldValue(array $item, string $field): string
    {
        $fields = $item['biblatexCustomFields'] ?? [];
        if (!is_array($fields)) {
            return '';
        }

        $value = $fields[$field] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @param array<string, mixed> $item
     */
    private function biblatexCustomListValue(array $item, string $field): string
    {
        $lists = $item['biblatexCustomLists'] ?? [];
        if (!is_array($lists)) {
            return '';
        }

        $values = $lists[$field] ?? [];
        if (!is_array($values)) {
            return '';
        }

        return implode('; ', array_values(array_filter(
            array_map(
                static fn (mixed $value): string => is_scalar($value) ? trim((string) $value) : '',
                $values
            ),
            static fn (string $value): bool => $value !== ''
        )));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function biblatexCustomNameValue(array $item, string $field): string
    {
        $namesByField = $item['biblatexCustomNames'] ?? [];
        if (!is_array($namesByField)) {
            return '';
        }

        $names = $namesByField[$field] ?? [];
        if (!is_array($names)) {
            return '';
        }

        return implode('; ', array_values(array_filter(
            array_map(
                static fn (mixed $name): string => is_array($name) ? self::biblatexCustomNameDisplay($name) : '',
                $names
            ),
            static fn (string $value): bool => $value !== ''
        )));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function renderTextVariableValue(array $item, string $variable, string $form, string $scope, ?AstNode $citation = null): string
    {
        $normalizedForm = strtolower(trim($form));
        $normalizedVariable = strtolower(trim($variable));

        if (
            in_array($normalizedForm, ['numeric', 'ordinal', 'long-ordinal', 'roman'], true)
            && $this->textVariableAcceptsNumberForm($normalizedVariable)
        ) {
            return $this->formatCslNumber($this->renderVariableValue($item, $variable, $scope, $citation), $normalizedForm);
        }

        if ($normalizedForm !== 'short') {
            return $this->renderVariableValue($item, $variable, $scope, $citation);
        }

        return match ($normalizedVariable) {
            'title' => (string) ($item['shortTitle'] !== '' ? $item['shortTitle'] : $item['title']),
            'container-title' => (string) ($item['containerTitleShort'] !== '' ? $item['containerTitleShort'] : $item['containerTitle']),
            default => $this->renderVariableValue($item, $variable, $scope, $citation),
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

    private function firstReferenceNoteNumberValue(?AstNode $citation): string
    {
        if (!$citation instanceof AstNode) {
            return '';
        }

        foreach (['cslFirstReferenceNoteNumber', 'firstReferenceNoteNumber'] as $attribute) {
            $value = $citation->attr($attribute, '');
            if (is_int($value) && $value >= 1) {
                return (string) $value;
            }

            if (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1 && (int) $value >= 1) {
                return (string) ((int) $value);
            }
        }

        return '';
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

        if ($normalized === 'first-reference-note-number') {
            return $this->firstReferenceNoteNumberValue($citation) !== '';
        }

        if ($normalized === 'year-suffix') {
            return $this->renderVariableValue($item, $variable, $scope, $citation) !== '';
        }

        if (in_array($normalized, ['short-author', 'short-editor', 'author', 'editor', 'holder', 'translator', 'chair', 'container-author', 'collection-editor', 'composer', 'contributor', 'editor-translator', 'executive-producer', 'event-organizer', 'organizer', 'guest', 'host', 'narrator', 'original-author', 'performer', 'producer', 'recipient', 'script-writer', 'compiler', 'curator', 'director', 'editorial-director', 'illustrator', 'interviewer', 'reviewed-author', 'redactor', 'founder', 'continuator', 'reviser', 'collaborator', 'commentator', 'annotator', 'introduction', 'foreword', 'afterword', 'namea', 'nameb', 'namec'], true)) {
            return $this->namesForRenderingVariable($item, $normalized) !== [];
        }

        if (in_array($normalized, ['issued', 'date', 'accessed', 'available-date', 'event-date', 'original-date', 'submitted'], true)) {
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
    private function renderingVariableIsCreator(array $item, string $variable): bool
    {
        $normalized = str_replace(['_', ' '], '-', strtolower(trim($variable)));
        if ($normalized === '') {
            return false;
        }

        return $this->namesForRenderingVariable($item, $normalized) !== [];
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

    private function formatCslLocatorRanges(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return preg_replace('/(?<=[\p{L}\p{N}])\s*[-\x{2010}-\x{2015}]\s*(?=[\p{L}\p{N}])/u', "\u{2013}", $value) ?? $value;
    }

    private function formatCslPageRanges(string $value): string
    {
        $format = $this->style->pageRangeFormat();
        if ($value === '' || $format === '') {
            return $value;
        }

        $delimiter = $this->style->term('page-range-delimiter');
        if ($delimiter === '' || $delimiter === 'page-range-delimiter') {
            $delimiter = "\u{2013}";
        }

        return preg_replace_callback(
            '/(?<![\p{L}\p{N}])(\d+)\s*[-\x{2010}-\x{2015}]\s*(\d+)(?![\p{L}\p{N}])/u',
            fn (array $matches): string => $this->formatCslPageRangePair((string) $matches[1], (string) $matches[2], $format, $delimiter),
            $value
        ) ?? $value;
    }

    private function formatCslPageRangePair(string $startText, string $endText, string $format, string $delimiter): string
    {
        if ($startText === '' || $endText === '') {
            return $startText . $delimiter . $endText;
        }

        $start = (int) $startText;
        $end = $this->expandedCslPageRangeEnd($start, $endText);
        if ($end === null || $end <= $start) {
            return $startText . $delimiter . $endText;
        }

        $expandedEndText = (string) $end;

        return match ($format) {
            'expanded' => $startText . $delimiter . $expandedEndText,
            'minimal' => $startText . $delimiter . $this->collapsedCslPageRangeEnd($startText, $expandedEndText, 1),
            'minimal-two' => $startText . $delimiter . $this->collapsedCslPageRangeEnd($startText, $expandedEndText, 2),
            'chicago' => $startText . $delimiter . $this->chicagoCslPageRangeEnd($start, $startText, $end, $expandedEndText),
            default => $startText . $delimiter . $expandedEndText,
        };
    }

    private function expandedCslPageRangeEnd(int $start, string $endText): ?int
    {
        if ($endText === '' || preg_match('/^\d+$/', $endText) !== 1) {
            return null;
        }

        $end = (int) $endText;
        $digits = strlen($endText);
        if ($digits >= strlen((string) $start)) {
            return $end;
        }

        $power = 10 ** $digits;
        $candidate = intdiv($start, $power) * $power + $end;
        while ($candidate < $start) {
            $candidate += $power;
        }

        return $candidate;
    }

    private function collapsedCslPageRangeEnd(string $startText, string $endText, int $minimumDigits): string
    {
        $minimumDigits = max(1, $minimumDigits);
        $limit = min(strlen($startText), strlen($endText));
        $offset = 0;
        while ($offset < $limit && $startText[$offset] === $endText[$offset]) {
            $offset++;
        }

        $changed = substr($endText, $offset);
        if ($changed === '') {
            return $endText;
        }

        if (strlen($changed) < $minimumDigits && strlen($endText) >= $minimumDigits) {
            return substr($endText, -$minimumDigits);
        }

        return $changed;
    }

    private function chicagoCslPageRangeEnd(int $start, string $startText, int $end, string $endText): string
    {
        if ($start < 100 || $start % 100 === 0) {
            return $endText;
        }

        if (strlen((string) $start) === 4 && intdiv($start, 100) !== intdiv($end, 100)) {
            return $endText;
        }

        $lastTwoDigits = $start % 100;
        if ($lastTwoDigits >= 1 && $lastTwoDigits <= 9) {
            return $this->collapsedCslPageRangeEnd($startText, $endText, 1);
        }

        return $this->collapsedCslPageRangeEnd($startText, $endText, 2);
    }

    /**
     * @param array<string, mixed> $item
     * @return list<array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool}>
     */
    private function namesForRenderingVariable(array $item, string $variable): array
    {
        return $this->namesForRenderingVariableWithSource($item, $variable)[0];
    }

    /**
     * @param array<string, mixed> $item
     * @return array{0:list<array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool}>, 1:string}
     */
    private function namesForRenderingVariableWithSource(array $item, string $variable): array
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
                'chair' => $item['chairs'] ?? [],
                'container-author' => $item['containerAuthors'] ?? [],
                'collection-editor' => $item['collectionEditors'] ?? [],
                'composer' => $item['composers'] ?? [],
                'contributor' => $item['contributors'] ?? [],
                'editor-translator' => $item['editorTranslators'] ?? [],
                'executive-producer' => $item['executiveProducers'] ?? [],
                'event-organizer', 'organizer' => $item['eventOrganizers'] ?? [],
                'guest' => $item['guests'] ?? [],
                'host' => $item['hosts'] ?? [],
                'narrator' => $item['narrators'] ?? [],
                'original-author' => $item['originalAuthors'] ?? [],
                'performer' => $item['performers'] ?? [],
                'producer' => $item['producers'] ?? [],
                'recipient' => $item['recipients'] ?? [],
                'script-writer' => $item['scriptWriters'] ?? [],
                'compiler' => $item['compilers'] ?? [],
                'curator' => $item['curators'] ?? [],
                'director' => $item['directors'] ?? [],
                'editorial-director' => $item['editorialDirectors'] ?? [],
                'illustrator' => $item['illustrators'] ?? [],
                'interviewer' => $item['interviewers'] ?? [],
                'reviewed-author' => $item['reviewedAuthors'] ?? [],
                'redactor' => $item['redactors'] ?? [],
                'founder' => $item['founders'] ?? [],
                'continuator' => $item['continuators'] ?? [],
                'reviser' => $item['revisers'] ?? [],
                'collaborator' => $item['collaborators'] ?? [],
                'commentator' => $item['commentators'] ?? [],
                'annotator' => $item['annotators'] ?? [],
                'introduction' => $item['introductionAuthors'] ?? [],
                'foreword' => $item['forewordAuthors'] ?? [],
                'afterword' => $item['afterwordAuthors'] ?? [],
                'namea', 'nameb', 'namec' => is_array($item['biblatexCustomNames'][$nameVariable] ?? null) ? $item['biblatexCustomNames'][$nameVariable] : [],
                default => [],
            };
            if (is_array($names) && $names !== []) {
                return [$names, $nameVariable];
            }
        }

        return [[], ''];
    }

    /**
     * @param array<string, mixed> $item
     */
    private function dateMarkerStatusForVariable(array $item, string $variable): string
    {
        $date = $this->dateVariableForRendering($item, $variable);

        return is_array($date) ? self::dateMarkerStatus($date) : '';
    }

    /**
     * @param array<string, mixed> $item
     */
    private function dateRawForVariable(array $item, string $variable): string
    {
        $date = $this->dateVariableForRendering($item, $variable);
        if (!is_array($date)) {
            return '';
        }

        return trim((string) ($date['raw'] ?? ''));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function dateTimeForVariable(array $item, string $variable): string
    {
        $date = $this->dateVariableForRendering($item, $variable);
        if (!is_array($date)) {
            return '';
        }

        return trim((string) ($date['time'] ?? ''));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function dateEndTimeForVariable(array $item, string $variable): string
    {
        $date = $this->dateVariableForRendering($item, $variable);
        if (!is_array($date)) {
            return '';
        }

        return trim((string) ($date['endTime'] ?? ''));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function dateSeasonForVariable(array $item, string $variable): string
    {
        $date = $this->dateVariableForRendering($item, $variable);
        if (!is_array($date) || !is_int($date['season'] ?? null)) {
            return '';
        }

        return (string) $date['season'];
    }

    /**
     * @param array<string, mixed> $item
     */
    private function dateSeasonNameForVariable(array $item, string $variable): string
    {
        $date = $this->dateVariableForRendering($item, $variable);
        if (!is_array($date) || !is_int($date['season'] ?? null)) {
            return '';
        }

        return self::dateSeasonName((int) $date['season']);
    }

    /**
     * @param array<string, mixed> $item
     * @return array{year:?int, parts:list<int>, display:string, literal:string, raw?:string, time?:string, endTime?:string, circa?:bool, uncertain?:bool, season?:int, seasonName?:string, openEnded?:string, rangeParts?:list<list<int>>}|null
     */
    private function dateVariableForRendering(array $item, string $variable): ?array
    {
        $normalized = strtolower(trim($variable));

        return match ($normalized) {
            'issued', 'date' => is_array($item['issuedDate'] ?? null) ? $item['issuedDate'] : null,
            'accessed' => is_array($item['accessedDate'] ?? null) ? $item['accessedDate'] : null,
            'available-date' => is_array($item['availableDate'] ?? null) ? $item['availableDate'] : null,
            'event-date' => is_array($item['eventDate'] ?? null) ? $item['eventDate'] : null,
            'original-date' => is_array($item['originalDate'] ?? null) ? $item['originalDate'] : null,
            'submitted' => is_array($item['submittedDate'] ?? null) ? $item['submittedDate'] : null,
            default => null,
        };
    }

    /**
     * @param array{year:?int, parts:list<int>, display:string, literal:string, openEnded?:string, rangeParts?:list<list<int>>}|mixed $date
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
     * @param array{year:?int, parts:list<int>, display:string, literal:string, openEnded?:string, rangeParts?:list<list<int>>} $date
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

        return $this->applyOpenEndedDateBoundary(implode($this->dateRangeDelimiter($parts, $specs), $values), $date);
    }

    /**
     * @param array{year:?int, parts:list<int>, display:string, literal:string, openEnded?:string, rangeParts?:list<list<int>>} $date
     */
    private function renderDateForm(array $date, string $form, string $scope, string $variable, string $datePartsSelection = ''): string
    {
        $rangeParts = is_array($date['rangeParts'] ?? null) ? $date['rangeParts'] : [];
        $singleParts = is_array($date['parts'] ?? null) ? $date['parts'] : [];
        if ($rangeParts === [] && $singleParts === []) {
            return $this->renderDateVariable($date, $scope, $variable);
        }

        $parts = $rangeParts !== [] ? $rangeParts : [$singleParts];
        $season = is_int($date['season'] ?? null) && $datePartsSelection !== 'year' ? (int) $date['season'] : null;
        $values = [];
        foreach ($parts as $dateParts) {
            if (!is_array($dateParts)) {
                continue;
            }

            $dateParts = $this->dateFormPartsForSelection($dateParts, $datePartsSelection);
            $value = $form === 'numeric'
                ? $this->renderNumericDateFormParts($dateParts, $season)
                : $this->renderTextDateFormParts($dateParts, $season);
            if ($value !== '') {
                $values[] = $value;
            }
        }

        if ($values === []) {
            return '';
        }

        return $this->applyOpenEndedDateBoundary(implode('/', array_values(array_unique($values))), $date);
    }

    /**
     * @param list<int> $parts
     * @return list<int>
     */
    private function dateFormPartsForSelection(array $parts, string $datePartsSelection): array
    {
        return match ($datePartsSelection) {
            'year' => array_slice($parts, 0, 1),
            'year-month' => array_slice($parts, 0, 2),
            default => array_slice($parts, 0, 3),
        };
    }

    /**
     * @param array<string, mixed> $date
     */
    private function applyOpenEndedDateBoundary(string $value, array $date): string
    {
        if ($value === '') {
            return '';
        }

        $boundary = (string) ($date['openEnded'] ?? '');
        if ($boundary === 'start') {
            return '/' . $value;
        }

        return $boundary === 'end' ? $value . '/' : $value;
    }

    /**
     * @param list<int> $parts
     */
    private function renderTextDateFormParts(array $parts, ?int $season = null): string
    {
        $year = $parts[0] ?? null;
        if ($year === null) {
            return '';
        }

        $month = $parts[1] ?? null;
        $day = $parts[2] ?? null;
        $yearText = $this->formatCslDateYearPart((int) $year, 'long');
        if ($season !== null && $month === null) {
            $seasonText = $this->localizedSeasonName($season);

            return $seasonText === '' ? $yearText : $seasonText . ' ' . $yearText;
        }

        if ($month === null) {
            return $yearText;
        }

        $monthText = $this->formatCslDateMonthPart((int) $month, 'long');
        if ($monthText === '') {
            return $yearText;
        }

        if ($day === null) {
            return $monthText . ' ' . $yearText;
        }

        return $monthText . ' ' . $this->formatCslDateDayPart((int) $day, 'numeric') . ', ' . $yearText;
    }

    /**
     * @param list<int> $parts
     */
    private function renderNumericDateFormParts(array $parts, ?int $season = null): string
    {
        $year = $parts[0] ?? null;
        if ($year === null) {
            return '';
        }

        $month = $parts[1] ?? null;
        $day = $parts[2] ?? null;
        $yearText = $this->formatCslDateYearPart((int) $year, 'long');
        if ($season !== null && $month === null) {
            $seasonText = $this->localizedSeasonName($season);

            return $seasonText === '' ? $yearText : $seasonText . ' ' . $yearText;
        }

        if ($month === null) {
            return $yearText;
        }

        $monthText = $this->formatCslDateMonthPart((int) $month, 'numeric');
        if ($day === null) {
            return $monthText . '/' . $yearText;
        }

        return $monthText . '/' . $this->formatCslDateDayPart((int) $day, 'numeric') . '/' . $yearText;
    }

    private function localizedSeasonName(int $season): string
    {
        $term = $this->style->term(sprintf('season-%02d', $season));

        return str_starts_with($term, 'season-') ? self::dateSeasonName($season) : $term;
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
            'ordinal' => $this->formatCslDayOrdinal($day),
            default => (string) $day,
        };
    }

    private function formatCslDayOrdinal(int $day): string
    {
        if ($this->style->limitDayOrdinalsToDay1() && $day !== 1) {
            return (string) $day;
        }

        return $day . $this->ordinalSuffix($day);
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
     * @param list<array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool}> $names
     * @param array<string, mixed> $options
     */
    private function applyNamesLabel(string $renderedNames, string $selectedVariable, array $names, array $options): string
    {
        $label = $options['label'] ?? null;
        if ($renderedNames === '' || $selectedVariable === '' || !is_array($label)) {
            return $renderedNames;
        }

        $plural = match ((string) ($label['plural'] ?? 'contextual')) {
            'always' => true,
            'never' => false,
            default => count($names) !== 1,
        };
        $term = $this->style->term(
            $this->nameLabelTermName($selectedVariable),
            (string) ($label['form'] ?? 'long'),
            $plural
        );
        if (($label['stripPeriods'] ?? false) === true) {
            $term = str_replace('.', '', $term);
        }
        $term = $this->applyNamePartTextCase($term, $label);
        if ($term === '') {
            return $renderedNames;
        }

        $prefix = (string) ($label['prefix'] ?? '');
        $suffix = (string) ($label['suffix'] ?? '');
        $renderedLabel = $prefix . $term . $suffix;
        if (($label['position'] ?? 'after') === 'before') {
            $separator = $suffix !== '' || preg_match('/^\s|^[.,;:!?]/u', $renderedNames) === 1 ? '' : ' ';

            return trim($renderedLabel . $separator . $renderedNames);
        }

        $separator = $prefix !== '' || preg_match('/\s\z/u', $renderedNames) === 1 ? '' : ' ';

        return trim($renderedNames . $separator . $renderedLabel);
    }

    private function nameLabelTermName(string $variable): string
    {
        return match (strtolower(trim($variable))) {
            'short-author' => 'author',
            'short-editor' => 'editor',
            'event-organizer', 'organizer' => 'event-organizer',
            default => strtolower(trim($variable)),
        };
    }

    /**
     * @param list<array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool}> $names
     * @param array<string, mixed> $options
     */
    private function renderNameList(array $names, array $options, bool $bibliography, ?AstNode $citation = null, ?array &$bibliographyState = null): string
    {
        if (!$bibliography) {
            $options = $this->citationNameRenderingOptionsWithGivenNameDisambiguation($options, $citation);
        }

        $forceEtAl = false;
        $renderableNames = [];
        foreach ($names as $name) {
            if (($name['etAl'] ?? false) === true) {
                $forceEtAl = true;
                break;
            }

            $renderableNames[] = $name;
        }

        if (!$forceEtAl) {
            $renderableNames = $names;
        }

        $count = count($renderableNames);
        if (strtolower(trim((string) ($options['form'] ?? 'long'))) === 'count') {
            return (string) $count;
        }

        $etAlMin = $options['etAlMin'];
        $etAlUseFirst = $options['etAlUseFirst'];
        if (!$bibliography && $this->citationUsesSubsequentNameOptions($citation)) {
            if (is_int($options['etAlSubsequentMin'] ?? null)) {
                $etAlMin = $options['etAlSubsequentMin'];
            }
            if (is_int($options['etAlSubsequentUseFirst'] ?? null)) {
                $etAlUseFirst = $options['etAlSubsequentUseFirst'];
            }
        }
        $useEtAl = $forceEtAl || (is_int($etAlMin) && $count >= $etAlMin);
        $visibleCount = $forceEtAl
            ? $count
            : ($useEtAl ? max(1, min((int) $etAlUseFirst, $count)) : $count);
        if (!$forceEtAl && $useEtAl && $visibleCount >= $count && isset($options['disambiguateNameCount'])) {
            $useEtAl = false;
        }
        $visible = array_slice($renderableNames, 0, $visibleCount);

        $rendered = [];
        foreach ($visible as $index => $name) {
            $rendered[] = $bibliography
                ? $this->renderBibliographyName($name, $options, $index)
                : $this->renderCitationName($name, $this->citationNameRenderingOptionsForVisibleName($options, $index));
        }

        if ($useEtAl) {
            if ($this->usesEtAlLastName($options, $forceEtAl, $etAlMin, $etAlUseFirst, $visibleCount, $count)) {
                $lastName = $renderableNames[$count - 1];
                $lastRendered = $bibliography
                    ? $this->renderBibliographyName($lastName, $options, $count - 1)
                    : $this->renderCitationName($lastName, $this->citationNameRenderingOptionsForVisibleName($options, $count - 1));

                if ($bibliography && is_array($bibliographyState)) {
                    $substitution = $this->bibliographySubsequentAuthorSubstitutionPlan(
                        [...$rendered, $lastRendered],
                        $this->joinNamesWithEtAlUseLast($rendered, $lastRendered, $options),
                        $bibliographyState
                    );
                    if (($substitution['type'] ?? '') === 'full') {
                        return (string) ($substitution['value'] ?? '');
                    }
                    if (($substitution['type'] ?? '') === 'parts' && is_array($substitution['parts'] ?? null)) {
                        $substituted = $substitution['parts'];
                        $lastRendered = array_pop($substituted) ?? $lastRendered;
                        $rendered = $substituted;
                    }
                }

                return $this->joinNamesWithEtAlUseLast($rendered, $lastRendered, $options);
            }

            $term = $this->renderEtAlTerm($options);
            if ($rendered === []) {
                if ($bibliography && is_array($bibliographyState)) {
                    $substitution = $this->bibliographySubsequentAuthorSubstitutionPlan([], $term, $bibliographyState);
                    if (($substitution['type'] ?? '') === 'full') {
                        return (string) ($substitution['value'] ?? '');
                    }
                }

                return $term;
            }

            if ($bibliography && is_array($bibliographyState)) {
                $substitution = $this->bibliographySubsequentAuthorSubstitutionPlan(
                    $rendered,
                    $this->joinNamesWithEtAl($rendered, $term, $options, $bibliography, $visibleCount - 1),
                    $bibliographyState
                );
                if (($substitution['type'] ?? '') === 'full') {
                    return (string) ($substitution['value'] ?? '');
                }
                if (($substitution['type'] ?? '') === 'parts' && is_array($substitution['parts'] ?? null)) {
                    $rendered = $substitution['parts'];
                }
            }

            return $this->joinNamesWithEtAl($rendered, $term, $options, $bibliography, $visibleCount - 1);
        }

        if ($bibliography && is_array($bibliographyState)) {
            $renderedNames = $this->joinNamesWithLastDelimiter($rendered, $options, true);
            $substitution = $this->bibliographySubsequentAuthorSubstitutionPlan(
                $rendered,
                $renderedNames,
                $bibliographyState
            );
            if (($substitution['type'] ?? '') === 'full') {
                return (string) ($substitution['value'] ?? '');
            }
            if (($substitution['type'] ?? '') === 'parts' && is_array($substitution['parts'] ?? null)) {
                $rendered = $substitution['parts'];
            }
        }

        return $bibliography
            ? $this->joinNamesWithLastDelimiter($rendered, $options, true)
            : $this->joinNamesWithLastDelimiter($rendered, $options, false);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function citationNameRenderingOptionsWithGivenNameDisambiguation(array $options, ?AstNode $citation): array
    {
        if (!$citation instanceof AstNode) {
            return $options;
        }

        $count = $citation->attr('cslDisambiguateNameCount');
        if (is_int($count) || (is_string($count) && preg_match('/^\d+$/', $count) === 1)) {
            $count = (int) $count;
            if ($count > 1) {
                $etAlUseFirst = $options['etAlUseFirst'] ?? 1;
                if (!is_int($etAlUseFirst)) {
                    $etAlUseFirst = 1;
                }

                $options['etAlUseFirst'] = max($etAlUseFirst, $count);
                if (is_int($options['etAlSubsequentUseFirst'] ?? null)) {
                    $options['etAlSubsequentUseFirst'] = max($options['etAlSubsequentUseFirst'], $count);
                }
                $options['disambiguateNameCount'] = $count;
            }
        }

        $mode = strtolower(trim((string) $citation->attr('cslGivenNameDisambiguation', '')));
        if (!in_array($mode, ['initial', 'full'], true)) {
            return $options;
        }

        return [
            ...$options,
            'givenNameDisambiguationMode' => $mode,
            'givenNameDisambiguationScope' => $this->givenNameDisambiguationScope(),
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function citationNameRenderingOptionsForVisibleName(array $options, int $index): array
    {
        if (!isset($options['givenNameDisambiguationMode'])) {
            return $options;
        }

        if (($options['givenNameDisambiguationScope'] ?? 'primary') === 'all' || $index === 0) {
            return $options;
        }

        unset($options['givenNameDisambiguationMode'], $options['givenNameDisambiguationScope']);

        return $options;
    }

    private function givenNameDisambiguationScope(): string
    {
        return str_starts_with((string) ($this->style->citationOptions()['givenNameDisambiguationRule'] ?? 'by-cite'), 'all-names')
            ? 'all'
            : 'primary';
    }

    private function citationUsesSubsequentNameOptions(?AstNode $citation): bool
    {
        if (!$citation instanceof AstNode) {
            return false;
        }

        $tests = $citation->attr('cslPositionTests', []);
        if (is_array($tests)) {
            foreach ($tests as $test) {
                if (strtolower(trim((string) $test)) === 'subsequent') {
                    return true;
                }
            }
        }

        return in_array(
            strtolower(trim((string) $citation->attr('cslPosition', ''))),
            ['subsequent', 'ibid', 'ibid-with-locator', 'near-note'],
            true
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    private function usesEtAlLastName(array $options, bool $forceEtAl, mixed $etAlMin, mixed $etAlUseFirst, int $visibleCount, int $count): bool
    {
        if ($forceEtAl || ($options['etAlUseLast'] ?? false) !== true) {
            return false;
        }

        if (!is_int($etAlMin) || !is_int($etAlUseFirst) || $etAlUseFirst > $etAlMin - 2) {
            return false;
        }

        return $visibleCount + 1 < $count;
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
    private function joinNamesWithEtAlUseLast(array $rendered, string $lastRendered, array $options): string
    {
        $delimiter = (string) $options['delimiter'];

        return implode($delimiter, [...$rendered, $this->style->term('ellipsis'), $lastRendered]);
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
     * @param array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool} $name
     * @param array<string, mixed> $options
     */
    private function renderCitationName(array $name, array $options): string
    {
        if ($name['literal'] !== '') {
            return $this->renderInstitutionName($name, $options);
        }

        $givenDisambiguationMode = (string) ($options['givenNameDisambiguationMode'] ?? '');
        if (in_array($givenDisambiguationMode, ['initial', 'full'], true) && trim((string) $name['given']) !== '') {
            return $this->renderGivenNameDisambiguatedCitationName($name, $options, $givenDisambiguationMode);
        }

        $family = trim((string) $name['nonDroppingParticle'] . ' ' . (string) $name['family']);
        if ($family !== '') {
            return $this->formatNamePart('family', $family, $options);
        }

        return $this->formatNamePart('given', $this->renderGivenName((string) $name['given'], $options), $options);
    }

    /**
     * @param array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool} $name
     * @param array<string, mixed> $options
     */
    private function renderGivenNameDisambiguatedCitationName(array $name, array $options, string $mode): string
    {
        $family = trim((string) $name['nonDroppingParticle'] . ' ' . (string) $name['family']);
        $family = $this->formatNamePart('family', $family, $options);
        $given = $mode === 'full'
            ? trim((string) $name['given'])
            : $this->renderGivenName((string) $name['given'], $options);
        $given = $this->formatNamePart('given', $given, $options);

        return trim(implode(' ', array_values(array_filter(
            [$given, $family],
            static fn (string $part): bool => $part !== ''
        ))));
    }

    /**
     * @param array{family:string, given:string, literal:string, short:string, nonDroppingParticle:string, droppingParticle:string, suffix:string, commaSuffix:bool, staticOrdering:bool, parseNames:bool} $name
     * @param array<string, mixed> $options
     */
    private function renderBibliographyName(array $name, array $options, int $index): string
    {
        if ($name['literal'] !== '') {
            return $this->renderInstitutionName($name, $options);
        }

        $nonDroppingParticle = (string) $name['nonDroppingParticle'];
        if (strtolower(trim((string) ($options['form'] ?? 'long'))) === 'short') {
            $family = $this->nameUsesFamilyGivenDisplayOrder($name)
                ? trim((string) $name['family'])
                : trim($nonDroppingParticle . ' ' . (string) $name['family']);
            if ($family !== '') {
                return $this->formatNamePart('family', $family, $options);
            }

            $given = $this->nameUsesFamilyGivenDisplayOrder($name)
                ? (string) $name['given']
                : $this->renderGivenName((string) $name['given'], $options);

            return $this->formatNamePart('given', $given, $options);
        }

        if ($this->nameUsesFamilyGivenDisplayOrder($name)) {
            return $this->renderFamilyGivenBibliographyName($name, $options);
        }

        $sortOrdered = $options['nameAsSortOrder'] === 'all' || ($options['nameAsSortOrder'] === 'first' && $index === 0);
        $demoteForDisplay = $sortOrdered && (string) ($options['demoteNonDroppingParticle'] ?? 'never') === 'display-and-sort';
        $family = trim($demoteForDisplay
            ? (string) $name['family']
            : trim($nonDroppingParticle . ' ' . (string) $name['family']));
        $family = $this->formatNamePart('family', $family, $options);
        $givenParts = [];
        $given = $this->renderGivenName((string) $name['given'], $options);
        if ($given !== '') {
            $givenParts[] = $given;
        }
        $droppingParticle = (string) $name['droppingParticle'];
        if ($demoteForDisplay && $droppingParticle !== '') {
            $givenParts[] = $droppingParticle;
        }
        if ($demoteForDisplay && $nonDroppingParticle !== '') {
            $givenParts[] = $nonDroppingParticle;
        }
        $given = $this->formatNamePart('given', implode(' ', $givenParts), $options);
        $suffix = (string) $name['suffix'];

        if ($sortOrdered) {
            if ($family !== '' && $given !== '') {
                $entry = $family . (string) ($options['sortSeparator'] ?? ', ') . $given;
            } else {
                $entry = $family !== '' ? $family : $given;
            }
        } else {
            $entry = trim(($given !== '' ? $given . ' ' : '') . $family);
        }

        if (!$demoteForDisplay && $droppingParticle !== '') {
            $entry = trim($entry . ' ' . $droppingParticle);
        }

        if ($suffix !== '') {
            $entry .= ($name['commaSuffix'] ? ', ' : ' ') . $suffix;
        }

        return $entry;
    }

    /**
     * @param array<string, mixed> $name
     * @param array<string, mixed> $options
     */
    private function renderInstitutionName(array $name, array $options): string
    {
        $literal = trim((string) ($name['literal'] ?? ''));
        if ($literal === '') {
            return '';
        }

        $institution = $options['institution'] ?? null;
        if (!is_array($institution)) {
            return $literal;
        }

        $parts = $institution['parts'] ?? [];
        $longPart = is_array($parts) && is_array($parts['long'] ?? null) ? $parts['long'] : null;
        $shortPart = is_array($parts) && is_array($parts['short'] ?? null) ? $parts['short'] : null;
        $institutionParts = (string) ($institution['institutionParts'] ?? 'long');
        $delimiter = (string) ($institution['delimiter'] ?? ' ');
        $short = trim((string) ($name['short'] ?? ''));

        $longRendered = $this->formatInstitutionPart($literal, $longPart);
        $shortRendered = $this->formatInstitutionPart($short !== '' ? $short : $literal, $shortPart);

        if ($institutionParts === 'short') {
            return $shortRendered;
        }

        if ($institutionParts === 'long') {
            return $longRendered;
        }

        $hasDistinctShort = $short !== '' && $short !== $literal;
        if ($institutionParts === 'short-long') {
            $rendered = $hasDistinctShort ? [$shortRendered, $longRendered] : [$longRendered];

            return $this->joinRenderedElements(array_values(array_filter(
                $rendered,
                static fn (string $part): bool => $part !== ''
            )), $delimiter);
        }

        $rendered = $hasDistinctShort ? [$longRendered, $shortRendered] : [$longRendered];

        return $this->joinRenderedElements(array_values(array_filter(
            $rendered,
            static fn (string $part): bool => $part !== ''
        )), $delimiter);
    }

    /**
     * @param array<string, mixed>|null $format
     */
    private function formatInstitutionPart(string $value, ?array $format): string
    {
        $value = trim($value);
        if ($value === '' || $format === null) {
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
     * @param array<string, mixed> $name
     * @param array<string, mixed> $options
     */
    private function renderFamilyGivenBibliographyName(array $name, array $options): string
    {
        $family = $this->formatNamePart('family', trim((string) ($name['family'] ?? '')), $options);
        $given = $this->formatNamePart('given', trim((string) ($name['given'] ?? '')), $options);
        $separator = $this->nameUsesCompactFamilyGivenScript($name) ? '' : ' ';
        $parts = array_values(array_filter([$family, $given], static fn (string $part): bool => $part !== ''));
        $entry = implode($separator, $parts);

        $suffix = trim((string) ($name['suffix'] ?? ''));
        if ($suffix !== '') {
            $entry .= (($name['commaSuffix'] ?? false) ? ', ' : ' ') . $suffix;
        }

        return trim($entry);
    }

    /**
     * @param array<string, mixed> $name
     */
    private function nameUsesFamilyGivenDisplayOrder(array $name): bool
    {
        if (($name['staticOrdering'] ?? false) === true) {
            return true;
        }

        return $this->nameUsesCompactFamilyGivenScript($name);
    }

    /**
     * @param array<string, mixed> $name
     */
    private function nameUsesCompactFamilyGivenScript(array $name): bool
    {
        $text = trim(implode('', [
            (string) ($name['family'] ?? ''),
            (string) ($name['given'] ?? ''),
            (string) ($name['nonDroppingParticle'] ?? ''),
            (string) ($name['droppingParticle'] ?? ''),
        ]));
        if ($text === '' || preg_match('/[\x{3040}-\x{30FF}\x{3400}-\x{9FFF}\x{AC00}-\x{D7AF}]/u', $text) !== 1) {
            return false;
        }

        return preg_match('/\p{Latin}/u', $text) !== 1;
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
    private function joinNamesWithLastDelimiter(array $names, array $options, bool $bibliography): string
    {
        $count = count($names);
        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return $names[0];
        }

        if ($bibliography && ($options['delimiterPrecedesLastExplicit'] ?? false) !== true) {
            return implode($options['delimiter'], $names);
        }

        $and = $this->andJoiner($options);
        if ($and === '') {
            return implode($options['delimiter'], $names);
        }

        $delimiter = (string) ($options['delimiter'] ?? '');
        if ($count === 2) {
            $separator = $this->delimiterBeforeLastName($options, $bibliography, 0) ? $delimiter : ' ';
            if ($separator === '') {
                $separator = ' ';
            }

            return $names[0] . $separator . $and . ' ' . $names[1];
        }

        $previousNameIndex = $count - 2;
        $separator = $this->delimiterBeforeLastName($options, $bibliography, $previousNameIndex) ? $delimiter : ' ';
        if ($separator === '') {
            $separator = ' ';
        }

        return implode($delimiter, array_slice($names, 0, -1))
            . $separator
            . $and
            . ' '
            . $names[$count - 1];
    }

    /**
     * @param array<string, mixed> $options
     */
    private function delimiterBeforeLastName(array $options, bool $bibliography, int $previousNameIndex): bool
    {
        return match ((string) ($options['delimiterPrecedesLast'] ?? 'contextual')) {
            'always' => true,
            'never' => false,
            'after-inverted-name' => $bibliography && $this->visibleNameWasInvertedForEtAl($options, $previousNameIndex),
            default => $previousNameIndex > 0,
        };
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

    /**
     * @param array<string, mixed> $options
     */
    private function renderGivenName(string $given, array $options): string
    {
        $given = trim($given);
        $initializeWith = $options['initializeWith'] ?? null;
        if ($given === '' || $initializeWith === null) {
            return $given;
        }

        $initializeWith = (string) $initializeWith;
        $initializeWithHyphen = ($options['initializeWithHyphen'] ?? true) !== false;
        $tokens = preg_split('/(\s+|-+)/u', $given, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $rendered = '';
        $separator = '';
        foreach ($tokens as $token) {
            if (preg_match('/^\s+$/u', $token) === 1) {
                $separator = 'space';
                continue;
            }

            if (preg_match('/^-+$/u', $token) === 1) {
                $separator = 'hyphen';
                continue;
            }

            if (preg_match('/^./us', $token, $match) !== 1) {
                continue;
            }

            $initial = $this->uppercaseInitial($match[0]) . $initializeWith;
            if ($rendered !== '' && $separator === 'hyphen' && $initializeWithHyphen) {
                $rendered = rtrim($rendered) . '-' . $initial;
            } else {
                $rendered .= $initial;
            }
            $separator = '';
        }

        return rtrim($rendered);
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
            'article-locator' => '/^(?:art(?:icles?|s)?\.?|article(?:s)?)\s+(.+)$/iu',
            'appendix' => '/^(?:app(?:endices|endixes|s)?\.?|appendix|appendices|appendixes)\s+(.+)$/iu',
            'book' => '/^(?:bks?\.?|books?)\s+(.+)$/iu',
            'canon' => '/^(?:cc?\.?|canons?)\s+(.+)$/iu',
            'chapter' => '/^(?:chap(?:ters?|s)?\.?|chapter(?:s)?)\s+(.+)$/iu',
            'column' => '/^(?:col(?:umns?|s)?\.?|column(?:s)?)\s+(.+)$/iu',
            'elocation' => '/^(?:e-?loc(?:ations?|s)?\.?|elocations?|e-locations?)\s+(.+)$/iu',
            'equation' => '/^(?:eq(?:uations?|s)?\.?|equation(?:s)?)\s+(.+)$/iu',
            'figure' => '/^(?:fig(?:ures?|s)?\.?|figure(?:s)?)\s+(.+)$/iu',
            'folio' => '/^(?:fol(?:ios?|s)?\.?|folio(?:s)?)\s+(.+)$/iu',
            'line' => '/^(?:l(?:ines?|s)?\.?|line(?:s)?)\s+(.+)$/iu',
            'note' => '/^(?:n(?:otes?|s)?\.?|note(?:s)?)\s+(.+)$/iu',
            'opus' => '/^(?:opp?\.?|opus|opera)\s+(.+)$/iu',
            'part' => '/^(?:pts?\.?|part(?:s)?)\s+(.+)$/iu',
            'rule' => '/^(?:rr?\.?|rule(?:s)?)\s+(.+)$/iu',
            'section' => '/^(?:sec(?:tions?|s)?\.?|section(?:s)?|\x{00A7}\x{00A7}?)\s+(.+)$/iu',
            'paragraph' => '/^(?:para(?:graphs?|s)?\.?|paragraph(?:s)?|\x{00B6}\x{00B6}?)\s+(.+)$/iu',
            'sub-verbo' => '/^(?:s\.?\s*vv?\.?|sub[-\s]?verb[aois]+|sub-verbo|sub-verbum)\s+(.+)$/iu',
            'supplement' => '/^(?:supp(?:lements?|s)?\.?|supplement(?:s)?)\s+(.+)$/iu',
            'table' => '/^(?:tbl(?:s)?\.?|table(?:s)?)\s+(.+)$/iu',
            'timestamp' => '/^(?:timestamps?|ts\.?)\s+(.+)$/iu',
            'title' => '/^(?:tit(?:les?|s)?\.?|title(?:s)?)\s+(.+)$/iu',
            'verse' => '/^(?:v(?:erses?|s)?\.?|verse(?:s)?)\s+(.+)$/iu',
            'volume' => '/^(?:vol(?:umes?|s)?\.?|volume(?:s)?)\s+(.+)$/iu',
            'issue' => '/^(?:iss(?:ues?|s)?\.?|issue(?:s)?)\s+(.+)$/iu',
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
        $label = str_replace(['_', ' '], '-', $label);
        $label = rtrim($label, '.');

        return match ($label) {
            'p', 'pp', 'page', 'pages' => 'page',
            'art', 'arts', 'article', 'articles', 'article-locator', 'article-locators' => 'article-locator',
            'app', 'apps', 'appendix', 'appendices', 'appendixes' => 'appendix',
            'bk', 'bks', 'book', 'books' => 'book',
            'c', 'cc', 'canon', 'canons' => 'canon',
            'chap', 'chaps', 'chapter', 'chapters' => 'chapter',
            'col', 'cols', 'column', 'columns' => 'column',
            'e-loc', 'e-locs', 'eloc', 'elocs', 'elocation', 'elocations', 'e-location', 'e-locations' => 'elocation',
            'eq', 'eqs', 'equation', 'equations' => 'equation',
            'fig', 'figs', 'figure', 'figures' => 'figure',
            'fol', 'fols', 'folio', 'folios' => 'folio',
            'l', 'll', 'line', 'lines' => 'line',
            'n', 'nn', 'note', 'notes' => 'note',
            'op', 'opp', 'opus', 'opera' => 'opus',
            'pt', 'pts', 'part', 'parts' => 'part',
            'r', 'rr', 'rule', 'rules' => 'rule',
            'sec', 'secs', 'section', 'sections', "\u{00A7}", "\u{00A7}\u{00A7}" => 'section',
            'para', 'paras', 'paragraph', 'paragraphs', "\u{00B6}", "\u{00B6}\u{00B6}" => 'paragraph',
            's.v', 's.vv', 's.-v', 's.-vv', 'sv', 'svv', 'sub-verbo', 'sub-verbum', 'sub-verba', 'sub-verbis' => 'sub-verbo',
            'supp', 'supps', 'suppl', 'suppls', 'supplement', 'supplements' => 'supplement',
            'tbl', 'tbls', 'table', 'tables' => 'table',
            'ts', 'timestamp', 'timestamps' => 'timestamp',
            'tit', 'tits', 'ttl', 'ttls', 'title', 'titles' => 'title',
            'v', 'vv', 'verse', 'verses' => 'verse',
            'vol', 'vols', 'volume', 'volumes' => 'volume',
            'iss', 'isses', 'issue', 'issues' => 'issue',
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
