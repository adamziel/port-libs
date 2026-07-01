<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class BibtexCslProcessor
{
    /** @var list<string> */
    private const BIBLATEX_CUSTOM_FIELDS = ['usera', 'userb', 'userc', 'userd', 'usere', 'userf', 'verba', 'verbb', 'verbc'];

    /** @var list<string> */
    private const BIBLATEX_CUSTOM_LIST_FIELDS = ['lista', 'listb', 'listc', 'listd', 'liste', 'listf'];

    /** @var list<string> */
    private const BIBLATEX_CUSTOM_NAME_FIELDS = ['namea', 'nameb', 'namec'];

    /** @var array<string, string> */
    private const BIBLATEX_ENTRY_OPTION_FIELDS = [
        'dashed' => 'dashed',
        'data-only' => 'dataonly',
        'dataonly' => 'dataonly',
        'label-date-parts' => 'labeldateparts',
        'labeldateparts' => 'labeldateparts',
        'max-alpha-names' => 'maxalphanames',
        'maxalphanames' => 'maxalphanames',
        'max-bib-names' => 'maxbibnames',
        'maxbibnames' => 'maxbibnames',
        'max-cite-names' => 'maxcitenames',
        'maxcitenames' => 'maxcitenames',
        'maxitems' => 'maxitems',
        'maxnames' => 'maxnames',
        'merge-date' => 'mergedate',
        'mergedate' => 'mergedate',
        'min-alpha-names' => 'minalphanames',
        'minalphanames' => 'minalphanames',
        'min-bib-names' => 'minbibnames',
        'minbibnames' => 'minbibnames',
        'min-cite-names' => 'mincitenames',
        'mincitenames' => 'mincitenames',
        'minitems' => 'minitems',
        'minnames' => 'minnames',
        'skip-bib' => 'skipbib',
        'skip-lab' => 'skiplab',
        'skipbib' => 'skipbib',
        'skiplab' => 'skiplab',
        'single-title' => 'singletitle',
        'singletitle' => 'singletitle',
        'sort-locale' => 'sortlocale',
        'sortlocale' => 'sortlocale',
        'use-author' => 'useauthor',
        'use-editor' => 'useeditor',
        'use-prefix' => 'useprefix',
        'use-title' => 'usetitle',
        'use-translator' => 'usetranslator',
        'use-venue' => 'usevenue',
        'useauthor' => 'useauthor',
        'useeditor' => 'useeditor',
        'useprefix' => 'useprefix',
        'usetitle' => 'usetitle',
        'usetranslator' => 'usetranslator',
        'usevenue' => 'usevenue',
        'unique-list' => 'uniquelist',
        'unique-name' => 'uniquename',
        'unique-title' => 'uniquetitle',
        'uniquelist' => 'uniquelist',
        'uniquename' => 'uniquename',
        'uniquetitle' => 'uniquetitle',
    ];

    /** @var list<string> */
    private const BIBLATEX_NAME_ANNOTATION_FIELDS = [
        'author',
        'bookauthor',
        'chair',
        'collaborator',
        'collection-editor',
        'collectioneditor',
        'commentator',
        'compiler',
        'composer',
        'continuator',
        'contributor',
        'curator',
        'director',
        'editor',
        'editor-translator',
        'editora',
        'editorb',
        'editorc',
        'editorial-director',
        'editorialdirector',
        'editortranslator',
        'eventorganizer',
        'executive-producer',
        'executiveproducer',
        'foreword',
        'founder',
        'guest',
        'holder',
        'host',
        'illustrator',
        'introduction',
        'namea',
        'nameb',
        'namec',
        'narrator',
        'origauthor',
        'original-author',
        'originalauthor',
        'performer',
        'producer',
        'recipient',
        'redactor',
        'reviewed-author',
        'reviewedauthor',
        'reviser',
        'script-writer',
        'scriptwriter',
        'series-creator',
        'seriescreator',
        'series-editor',
        'serieseditor',
        'shortauthor',
        'shorteditor',
        'translator',
    ];

    /** @var array<string, string> */
    private const MONTH_MACROS = [
        'jan' => '1',
        'feb' => '2',
        'mar' => '3',
        'apr' => '4',
        'may' => '5',
        'jun' => '6',
        'jul' => '7',
        'aug' => '8',
        'sep' => '9',
        'oct' => '10',
        'nov' => '11',
        'dec' => '12',
    ];

    /**
     * @return array<string, array{id:string, type:string, fields:array<string, string>, csl:array<string, mixed>}>
     */
    public function parseBibtex(string $source): array
    {
        $rawEntries = [];
        $macros = self::MONTH_MACROS;
        $offset = 0;

        while (($at = strpos($source, '@', $offset)) !== false) {
            $cursor = $at + 1;
            $type = strtolower($this->readIdentifier($source, $cursor));
            if ($type === '') {
                $offset = $cursor;
                continue;
            }

            $this->skipWhitespace($source, $cursor);
            $open = $source[$cursor] ?? '';
            if ($open !== '{' && $open !== '(') {
                $offset = $cursor;
                continue;
            }

            $close = $open === '{' ? '}' : ')';
            $cursor++;
            $bodyStart = $cursor;
            $bodyEnd = $this->findBalancedEnd($source, $cursor, $open, $close);
            if ($bodyEnd === null) {
                break;
            }

            $body = substr($source, $bodyStart, $bodyEnd - $bodyStart);
            $offset = $bodyEnd + 1;

            if ($type === 'comment' || $type === 'preamble') {
                continue;
            }

            [$key, $fields] = $this->parseEntryBody($body, $macros);
            if ($type === 'string') {
                foreach ($fields as $name => $value) {
                    $macros[strtolower($name)] = $value;
                }
                continue;
            }

            if ($key === '') {
                continue;
            }

            $rawEntries[$key] = [
                'id' => $key,
                'type' => $type,
                'fields' => $fields,
            ];
        }

        $entries = [];
        foreach ($rawEntries as $key => $entry) {
            $fields = $this->resolveInheritedFields($entry, $rawEntries);
            $item = $this->toCslItem($key, $entry['type'], $fields);
            $entries[$key] = [
                'id' => $entry['id'],
                'type' => $entry['type'],
                'fields' => $fields,
                'csl' => $this->withBiblatexRelationMetadata($item, $fields, $rawEntries),
            ];
        }

        $itemsByKey = [];
        foreach ($entries as $key => $entry) {
            $itemsByKey[$key] = $entry['csl'];
        }
        $itemsByCitationKey = $this->itemsByCitationKey($itemsByKey);
        foreach ($entries as $key => $entry) {
            $entries[$key]['csl'] = $this->withRelatedReferenceMetadata($entry['csl'], $itemsByCitationKey);
        }

        return $entries;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function cslItems(string $source): array
    {
        $items = [];
        foreach ($this->parseBibtex($source) as $key => $entry) {
            $items[$key] = $entry['csl'];
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    public function citedKeys(AstNode $document): array
    {
        $keys = [];
        $seen = [];
        $walk = function (AstNode $node) use (&$walk, &$keys, &$seen): void {
            if ($node->type === 'citation') {
                $nodeKeys = $node->attr('ids');
                if (!is_array($nodeKeys)) {
                    $nodeKeys = [(string) $node->attr('id', '')];
                }

                foreach ($nodeKeys as $key) {
                    $key = (string) $key;
                    if ($key === '' || isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $keys[] = $key;
                }
            }

            foreach ($node->children as $child) {
                $walk($child);
            }
        };
        $walk($document);

        return $keys;
    }

    /**
     * @return array{citedKeys:list<string>, missingKeys:list<string>, items:list<array<string, mixed>>, bibliography:AstNode}
     */
    public function citationHandoff(AstNode $document, string $bibtex): array
    {
        $itemsByKey = $this->cslItems($bibtex);
        $itemsByCitationKey = $this->itemsByCitationKey($itemsByKey);
        $citedKeys = $this->citedKeys($document);
        $items = [];
        $missing = [];
        $includedItemIds = [];

        foreach ($citedKeys as $key) {
            if (!isset($itemsByCitationKey[$key])) {
                $missing[] = $key;
                continue;
            }

            $item = $itemsByCitationKey[$key];
            $itemId = (string) ($item['id'] ?? '');
            if ($itemId !== '' && isset($includedItemIds[$itemId])) {
                continue;
            }

            if ($itemId !== '') {
                $includedItemIds[$itemId] = true;
            }
            $items[] = $item;
        }

        return [
            'citedKeys' => $citedKeys,
            'missingKeys' => $missing,
            'items' => $items,
            'bibliography' => $this->bibliographyNode($items, $missing),
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<string> $missing
     */
    public function bibliographyNode(array $items, array $missing = []): AstNode
    {
        $children = [];
        foreach ($items as $item) {
            $id = (string) ($item['id'] ?? '');
            $text = $this->renderBibliographyText($item);
            $children[] = new AstNode('definition_item', [
                'term' => $id,
                'cslItem' => $item,
            ], [
                new AstNode('term', ['text' => $id], [
                    new AstNode('text', ['text' => $id]),
                ]),
                new AstNode('definition', [], [
                    new AstNode('paragraph', ['text' => $text], [
                        new AstNode('text', ['text' => $text]),
                    ]),
                ]),
            ]);
        }

        foreach ($missing as $key) {
            $text = 'Missing bibliography entry: ' . $key;
            $children[] = new AstNode('definition_item', [
                'term' => $key,
                'missing' => true,
            ], [
                new AstNode('term', ['text' => $key], [
                    new AstNode('text', ['text' => $key]),
                ]),
                new AstNode('definition', [], [
                    new AstNode('paragraph', ['text' => $text], [
                        new AstNode('text', ['text' => $text]),
                    ]),
                ]),
            ]);
        }

        return new AstNode('definition_list', [
            'class' => 'csl-bibliography',
            'missingCitationKeys' => $missing,
        ], $children);
    }

    /**
     * @param array<string, mixed> $item
     */
    public function renderBibliographyText(array $item): string
    {
        $parts = [];
        $authors = $this->renderNames($item['author'] ?? []);
        if ($authors !== '') {
            $parts[] = $authors;
        }
        if (($item['title'] ?? '') !== '') {
            $parts[] = (string) $item['title'];
        }
        $citationAliases = $item['citation-aliases'] ?? [];
        if (is_array($citationAliases) && $citationAliases !== []) {
            $parts[] = 'Citation aliases: ' . implode('; ', array_map('strval', $citationAliases));
        }
        $translatedTitle = (string) ($item['translated-title'] ?? '');
        if ($translatedTitle !== '') {
            $translatedSubtitle = (string) ($item['translated-subtitle'] ?? '');
            if ($translatedSubtitle !== '') {
                $translatedTitle .= ': ' . $translatedSubtitle;
            }
            $parts[] = 'Translated title: ' . $translatedTitle;
        }

        $container = (string) ($item['container-title'] ?? '');
        $volume = (string) ($item['volume'] ?? '');
        $issue = (string) ($item['issue'] ?? '');
        if ($container !== '') {
            $containerPart = $container;
            if ($volume !== '') {
                $containerPart .= ' ' . $volume;
                if ($issue !== '') {
                    $containerPart .= '(' . $issue . ')';
                }
            }
            $parts[] = $containerPart;
        } elseif (($item['publisher'] ?? '') !== '') {
            $parts[] = (string) $item['publisher'];
        }

        if (($item['issue-title'] ?? '') !== '') {
            $parts[] = 'Issue title: ' . (string) $item['issue-title'];
        }
        if (($item['issue-title-addon'] ?? '') !== '') {
            $parts[] = 'Issue title addendum: ' . (string) $item['issue-title-addon'];
        }

        $year = $this->issuedYear($item);
        if ($year !== '') {
            $parts[] = $year;
        }
        foreach ([
            'available-date' => 'Available date',
            'submitted' => 'Submitted date',
        ] as $field => $label) {
            $date = $this->dateDisplay($item[$field] ?? []);
            if ($date !== '') {
                $parts[] = $label . ': ' . $date;
            }
        }
        if (($item['page'] ?? '') !== '') {
            $parts[] = (string) $item['page'];
        }
        if (($item['pagination'] ?? '') !== '') {
            $parts[] = 'Pagination: ' . (string) $item['pagination'];
        }
        if (($item['book-pagination'] ?? '') !== '') {
            $parts[] = 'Book pagination: ' . (string) $item['book-pagination'];
        }
        $containerTitleShort = (string) ($item['container-title-short'] ?? $item['journal-abbreviation'] ?? '');
        if ($containerTitleShort !== '') {
            $parts[] = 'Journal abbreviation: ' . rtrim($containerTitleShort, '.');
        }
        if (($item['article-number'] ?? '') !== '') {
            $parts[] = 'Article number: ' . (string) $item['article-number'];
        }
        foreach ($this->legalPatentBibliographyParts($item) as $part) {
            $parts[] = $part;
        }
        foreach ([
            'collection-title' => 'Collection',
            'collection-title-short' => 'Collection abbreviation',
            'collection-number' => 'Collection number',
        ] as $field => $label) {
            if (($item[$field] ?? '') !== '') {
                $value = (string) $item[$field];
                if ($field === 'collection-title-short') {
                    $value = rtrim($value, '.');
                }
                $parts[] = $label . ': ' . $value;
            }
        }
        foreach ([
            'edition' => 'Edition',
            'version' => 'Version',
            'status' => 'Status',
            'medium' => 'Medium',
            'entry-subtype' => 'Entry subtype',
        ] as $field => $label) {
            if ($field === 'status' && in_array((string) ($item['type'] ?? ''), ['patent', 'legislation', 'legal_case'], true)) {
                continue;
            }

            if (($item[$field] ?? '') !== '') {
                $parts[] = $label . ': ' . (string) $item[$field];
            }
        }
        $addendum = (string) ($item['addendum'] ?? '');
        if ($addendum !== '' && $addendum !== (string) ($item['note'] ?? '')) {
            $parts[] = 'Addendum: ' . $addendum;
        }
        if (($item['thesis-type'] ?? '') !== '') {
            $parts[] = 'Thesis type: ' . (string) $item['thesis-type'];
        }
        if (($item['name-addon'] ?? '') !== '') {
            $parts[] = 'Name addendum: ' . (string) $item['name-addon'];
        }
        foreach ([
            'author-type' => 'Author type',
            'container-author-type' => 'Container author type',
        ] as $field => $label) {
            if (($item[$field] ?? '') !== '') {
                $parts[] = $label . ': ' . (string) $item[$field];
            }
        }
        if (($item['source'] ?? '') !== '') {
            $parts[] = 'Source: ' . (string) $item['source'];
        }
        foreach ([
            'publisher-list' => 'Publisher list',
            'publisher-place-list' => 'Publisher places',
            'original-publisher-list' => 'Original publishers',
            'original-publisher-place-list' => 'Original publisher places',
            'language-list' => 'Languages',
            'original-language-list' => 'Original languages',
            'event-place-list' => 'Event places',
        ] as $field => $label) {
            $summary = $this->literalListSummary($item[$field] ?? []);
            if ($summary !== '') {
                $parts[] = $label . ': ' . $summary;
            }
        }
        if (($item['section'] ?? '') !== '') {
            $parts[] = 'Section: ' . (string) $item['section'];
        }
        if (($item['division'] ?? '') !== '') {
            $parts[] = 'Division: ' . (string) $item['division'];
        }
        if (($item['part'] ?? '') !== '') {
            $parts[] = 'Part: ' . (string) $item['part'];
        }
        if (($item['printing-number'] ?? '') !== '') {
            $parts[] = 'Printing number: ' . (string) $item['printing-number'];
        }
        if (($item['supplement'] ?? '') !== '') {
            $parts[] = 'Supplement: ' . (string) $item['supplement'];
        }
        if (($item['supplement-number'] ?? '') !== '') {
            $parts[] = 'Supplement number: ' . (string) $item['supplement-number'];
        }
        foreach ([
            'citation-label' => 'Citation label',
            'shorthand-intro' => 'Shorthand intro',
            'sort-shorthand' => 'Sort shorthand',
            'presort' => 'Presort',
            'sort-key' => 'Sort key',
            'sort-name' => 'Sort name',
            'sort-title' => 'Sort title',
            'sort-year' => 'Sort year',
            'sort-initial' => 'Sort initial',
            'sort-initial-hash' => 'Sort initial hash',
            'index-title' => 'Index title',
            'index-sort-title' => 'Index sort title',
            'label-prefix' => 'Label prefix',
            'label-alpha' => 'Label alpha',
            'label-title' => 'Label title',
            'extra-alpha' => 'Extra alpha',
            'extra-date' => 'Extra date',
            'extra-title' => 'Extra title',
            'date-addon' => 'Date addendum',
            'original-date-addon' => 'Original date addendum',
            'reprint-date-addon' => 'Reprint date addendum',
            'original-page' => 'Original pages',
            'original-page-first' => 'Original first page',
            'original-volume' => 'Original volume',
            'original-issue' => 'Original issue',
            'original-number' => 'Original number',
            'original-edition' => 'Original edition',
            'reprint-page' => 'Reprint pages',
            'reprint-page-first' => 'Reprint first page',
            'reprint-volume' => 'Reprint volume',
            'reprint-issue' => 'Reprint issue',
            'reprint-number' => 'Reprint number',
            'reprint-edition' => 'Reprint edition',
            'event-date-addon' => 'Event date addendum',
            'accessed-date-addon' => 'Accessed date addendum',
            'biblatex-disambiguation-summary' => 'BibLaTeX disambiguation',
        ] as $field => $label) {
            if (($item[$field] ?? '') !== '') {
                $value = (string) $item[$field];
                if ($field === 'volume-title-short') {
                    $value = rtrim($value, '.');
                }
                $parts[] = $label . ': ' . $value;
            }
        }
        $labelDate = $this->datePartsText($item['label-date'] ?? null);
        if ($labelDate !== '') {
            $parts[] = 'Label date: ' . $labelDate;
        }
        foreach ($this->dateMetadataBibliographyParts($item) as $part) {
            $parts[] = $part;
        }
        if (($item['rights'] ?? '') !== '') {
            $parts[] = 'Rights: ' . (string) $item['rights'];
        }
        $customFieldSummary = $this->biblatexCustomFieldSummary($item['biblatex-custom-fields'] ?? []);
        if ($customFieldSummary !== '') {
            $parts[] = 'BibLaTeX custom fields: ' . $customFieldSummary;
        }
        $customListSummary = $this->biblatexCustomListSummary($item['biblatex-custom-lists'] ?? []);
        if ($customListSummary !== '') {
            $parts[] = 'BibLaTeX custom lists: ' . $customListSummary;
        }
        $customNameSummary = $this->biblatexCustomNameSummary($item['biblatex-custom-names'] ?? []);
        if ($customNameSummary !== '') {
            $parts[] = 'BibLaTeX custom names: ' . $customNameSummary;
        }
        $relatedOptionSummary = $this->relatedOptionSummary($item);
        if ($relatedOptionSummary !== '') {
            $parts[] = 'Related options: ' . $relatedOptionSummary;
        }
        $fieldAnnotationSummary = $this->biblatexFieldAnnotationSummary($item['biblatex-field-annotations'] ?? []);
        if ($fieldAnnotationSummary !== '') {
            $parts[] = 'BibLaTeX field annotations: ' . $fieldAnnotationSummary;
        }
        $optionSummary = $this->biblatexOptionSummary($item['biblatex-options'] ?? []);
        if ($optionSummary !== '') {
            $parts[] = 'BibLaTeX options: ' . $optionSummary;
        }
        $languageOptionSummary = $this->biblatexOptionSummary($item['biblatex-language-options'] ?? []);
        if ($languageOptionSummary !== '') {
            $parts[] = 'BibLaTeX language options: ' . $languageOptionSummary;
        }
        $referenceContextSummary = $this->biblatexReferenceContextSummary(
            (string) ($item['biblatex-refsection'] ?? ''),
            (string) ($item['biblatex-refsegment'] ?? '')
        );
        if ($referenceContextSummary !== '') {
            $parts[] = 'BibLaTeX reference context: ' . $referenceContextSummary;
        }
        if (($item['entrySetSummary'] ?? '') !== '') {
            $parts[] = 'BibLaTeX entry set: ' . (string) $item['entrySetSummary'];
        }
        if (($item['relatedSummary'] ?? '') !== '') {
            $relatedLabel = (string) ($item['related-string'] ?? '');
            $relatedType = (string) ($item['related-type'] ?? '');
            $relatedPrefix = 'BibLaTeX related sources';
            if ($relatedLabel !== '') {
                $relatedPrefix .= ': ' . $relatedLabel . ($relatedType !== '' ? ' (' . $relatedType . ')' : '');
            }
            $parts[] = $relatedPrefix . ': ' . (string) $item['relatedSummary'];
        }
        if (($item['crossrefSummary'] ?? '') !== '') {
            $parts[] = 'BibLaTeX crossref parent: ' . (string) $item['crossrefSummary'];
        }
        if (($item['xrefSummary'] ?? '') !== '') {
            $parts[] = 'BibLaTeX xref parent: ' . (string) $item['xrefSummary'];
        }
        if (($item['xdataSummary'] ?? '') !== '') {
            $parts[] = 'BibLaTeX xdata packets: ' . (string) $item['xdataSummary'];
        }
        if (($item['gender'] ?? '') !== '') {
            $parts[] = 'BibLaTeX gender: ' . (string) $item['gender'];
        }
        $nameAnnotationSummary = $this->biblatexNameAnnotationSummary($item);
        if ($nameAnnotationSummary !== '') {
            $parts[] = 'Name annotations: ' . $nameAnnotationSummary;
        }
        $editorialRoleSummary = $this->biblatexEditorialRoleSummary($item['editorial-roles'] ?? []);
        if ($editorialRoleSummary !== '') {
            $parts[] = 'BibLaTeX editorial roles: ' . $editorialRoleSummary;
        }
        if (($item['annotation'] ?? '') !== '') {
            $parts[] = 'Annotation: ' . rtrim((string) $item['annotation'], '.');
        }
        if (($item['call-number'] ?? '') !== '') {
            $parts[] = 'Call number: ' . (string) $item['call-number'];
        }
        if (($item['DOI'] ?? '') !== '') {
            $parts[] = 'doi:' . (string) $item['DOI'];
        }
        foreach ([
            'ISAN' => 'ISAN',
            'ISMN' => 'ISMN',
            'ISRN' => 'ISRN',
            'ISWC' => 'ISWC',
            'PMID' => 'PMID',
            'PMCID' => 'PMCID',
            'MRNumber' => 'MR',
            'MRClass' => 'MR class',
            'Zbl' => 'Zbl',
            'JSTOR' => 'JSTOR',
            'HDL' => 'HDL',
            'LCCN' => 'LCCN',
            'OCLC' => 'OCLC',
            'ORCID' => 'ORCID',
            'ISNI' => 'ISNI',
            'VIAF' => 'VIAF',
            'ROR' => 'ROR',
            'Wikidata' => 'Wikidata',
        ] as $field => $label) {
            if (($item[$field] ?? '') !== '') {
                $parts[] = $label . ' ' . (string) $item[$field];
            }
        }
        if (($item['URL-label'] ?? '') !== '') {
            $parts[] = 'URL label: ' . rtrim((string) $item['URL-label'], '.');
        }
        if (($item['URL'] ?? '') !== '') {
            $parts[] = (string) $item['URL'];
        }
        foreach ([
            'reviewed-title' => 'Reviewed title',
            'reviewed-genre' => 'Reviewed genre',
            'reprint-title' => 'Reprint title',
            'references' => 'References',
            'dimensions' => 'Dimensions',
            'scale' => 'Scale',
            'main-title' => 'Main title',
            'main-title-addon' => 'Main title addendum',
            'original-genre' => 'Original genre',
            'volume-title' => 'Volume title',
            'volume-title-short' => 'Volume title abbreviation',
            'part-title' => 'Part title',
        ] as $field => $label) {
            if (($item[$field] ?? '') !== '') {
                $value = (string) $item[$field];
                if ($field === 'volume-title-short') {
                    $value = rtrim($value, '.');
                }
                $parts[] = $label . ': ' . $value;
            }
        }

        return implode('. ', $parts) . ($parts === [] ? '' : '.');
    }

    /**
     * @param array<string, string> $macros
     * @return array{0:string, 1:array<string, string>}
     */
    private function parseEntryBody(string $body, array $macros): array
    {
        $cursor = 0;
        $length = strlen($body);
        $firstComma = strpos($body, ',');
        $firstEquals = strpos($body, '=');
        $key = '';

        if ($firstEquals === false || ($firstComma !== false && $firstComma < $firstEquals)) {
            while ($cursor < $length && $body[$cursor] !== ',') {
                $key .= $body[$cursor];
                $cursor++;
            }
            $key = trim($key);
            if (($body[$cursor] ?? '') === ',') {
                $cursor++;
            }
        }

        $fields = [];
        while ($cursor < $length) {
            $this->skipWhitespace($body, $cursor);
            if (($body[$cursor] ?? '') === ',') {
                $cursor++;
                continue;
            }

            $name = strtolower($this->readFieldName($body, $cursor));
            if ($name === '') {
                break;
            }

            $this->skipWhitespace($body, $cursor);
            if (($body[$cursor] ?? '') !== '=') {
                break;
            }
            $cursor++;

            $fields[$name] = $this->readFieldValue($body, $cursor, $macros);
            $this->skipWhitespace($body, $cursor);
            if (($body[$cursor] ?? '') === ',') {
                $cursor++;
            }
        }

        return [$key, $fields];
    }

    /**
     * @param array<string, string> $macros
     */
    private function readFieldValue(string $body, int &$cursor, array $macros): string
    {
        $parts = [];
        $length = strlen($body);

        while ($cursor < $length) {
            $this->skipWhitespace($body, $cursor);
            $char = $body[$cursor] ?? '';

            if ($char === '{') {
                $end = $this->findBalancedEnd($body, $cursor + 1, '{', '}');
                if ($end === null) {
                    break;
                }
                $parts[] = $this->cleanValue(substr($body, $cursor + 1, $end - $cursor - 1), false);
                $cursor = $end + 1;
            } elseif ($char === '"') {
                $parts[] = $this->cleanValue($this->readQuotedValue($body, $cursor), false);
            } else {
                $bare = $this->readBareValue($body, $cursor);
                $parts[] = $macros[strtolower($bare)] ?? $this->cleanValue($bare);
            }

            $this->skipWhitespace($body, $cursor);
            if (($body[$cursor] ?? '') !== '#') {
                break;
            }
            $cursor++;
        }

        return trim(implode('', $parts));
    }

    private function readQuotedValue(string $body, int &$cursor): string
    {
        $cursor++;
        $start = $cursor;
        $depth = 0;
        $length = strlen($body);

        while ($cursor < $length) {
            $char = $body[$cursor];
            if ($char === '\\') {
                $cursor += 2;
                continue;
            }
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth = max(0, $depth - 1);
            } elseif ($char === '"' && $depth === 0) {
                $value = substr($body, $start, $cursor - $start);
                $cursor++;

                return $value;
            }
            $cursor++;
        }

        return substr($body, $start);
    }

    private function readBareValue(string $body, int &$cursor): string
    {
        $start = $cursor;
        $length = strlen($body);
        while ($cursor < $length && !str_contains(",# \t\r\n", $body[$cursor])) {
            $cursor++;
        }

        return substr($body, $start, $cursor - $start);
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, mixed>
     */
    private function toCslItem(string $key, string $type, array $fields): array
    {
        $item = [
            'id' => $key,
            'type' => $this->cslTypeForEntry($type, $fields),
            'rawBibtex' => [
                'type' => $type,
                'fields' => $fields,
            ],
        ];

        $title = $this->composedTitle($fields, ['title'], ['subtitle']);
        if ($title !== null && $title !== '') {
            $item['title'] = $title;
        }

        $containerTitle = $this->composedTitle(
            $fields,
            [
                'journaltitle',
                'journal-title',
                'journal',
                'booktitle',
                'book-title',
                'container-title',
                'container-title-text',
                'containertitle',
                'containertitletext',
                'publication-title',
                'publicationtitle',
            ],
            [
                'journalsubtitle',
                'journal-subtitle',
                'booksubtitle',
                'book-subtitle',
                'container-subtitle',
                'containersubtitle',
                'publication-subtitle',
                'publicationsubtitle',
            ]
        );
        if ($containerTitle !== null && $containerTitle !== '') {
            $item['container-title'] = $containerTitle;
        }

        $reviewedTitle = $this->composedTitle($fields, ['reviewedtitle', 'reviewed-title', 'reviewtitle'], ['reviewedsubtitle', 'reviewed-subtitle', 'reviewsubtitle']);
        if ($reviewedTitle !== null && $reviewedTitle !== '') {
            $item['reviewed-title'] = $reviewedTitle;
        }

        $mainTitle = $this->composedTitle($fields, ['maintitle', 'main-title', 'maintitletext', 'main-title-text'], ['mainsubtitle', 'main-subtitle']);
        if ($mainTitle !== null && $mainTitle !== '') {
            $item['main-title'] = $mainTitle;
        }

        $volumeTitle = $this->composedTitle($fields, ['volumetitle', 'volume-title', 'volumetitletext', 'volume-title-text'], ['volumesubtitle', 'volume-subtitle']);
        if ($volumeTitle !== null && $volumeTitle !== '') {
            $item['volume-title'] = $volumeTitle;
        }

        $partTitle = $this->composedTitle($fields, ['parttitle', 'part-title', 'parttitletext', 'part-title-text'], ['partsubtitle', 'part-subtitle']);
        if ($partTitle !== null && $partTitle !== '') {
            $item['part-title'] = $partTitle;
        }

        $issueTitle = $this->composedTitle($fields, ['issuetitle', 'issue-title', 'issuetitletext', 'issue-title-text'], ['issuesubtitle', 'issue-subtitle']);
        if ($issueTitle !== null && $issueTitle !== '') {
            $item['issue-title'] = $issueTitle;
        }

        $originalTitle = $this->composedTitle(
            $fields,
            ['origtitle', 'originaltitle', 'original-title'],
            ['origsubtitle', 'originalsubtitle', 'original-subtitle']
        );
        if ($originalTitle !== null && $originalTitle !== '') {
            $item['original-title'] = $originalTitle;
        }

        $translatedSubtitleFields = [
            'subtitletranslation',
            'subtitle-translation',
            'translatedsubtitle',
            'translated-subtitle',
            'titletranslationsubtitle',
            'title-translation-subtitle',
        ];
        $translatedTitle = $this->firstField($fields, ['titletranslation', 'title-translation', 'translatedtitle', 'translated-title']);
        if ($translatedTitle !== null && $translatedTitle !== '') {
            $item['translated-title'] = $translatedTitle;
        }

        $translatedSubtitle = $this->firstField($fields, $translatedSubtitleFields);
        if ($translatedSubtitle !== null && $translatedSubtitle !== '') {
            $item['translated-subtitle'] = $translatedSubtitle;
        }

        $stringFields = [
            'citation-label' => ['shorthand', 'label'],
            'shorthand' => ['shorthand'],
            'shorthand-intro' => ['shorthandintro', 'shorthand-intro'],
            'sort-shorthand' => ['sortshorthand', 'sort-shorthand'],
            'presort' => ['presort'],
            'sort-key' => ['sortkey', 'sort-key'],
            'sort-name' => ['sortname', 'sort-name'],
            'sort-title' => ['sorttitle', 'sort-title'],
            'sort-year' => ['sortyear', 'sort-year'],
            'sort-initial' => ['sortinit', 'sort-initial', 'sortinitial', 'sort-initials'],
            'sort-initial-hash' => ['sortinithash', 'sort-initial-hash'],
            'index-title' => ['indextitle', 'index-title'],
            'index-sort-title' => ['indexsorttitle', 'index-sort-title'],
            'label-prefix' => ['labelprefix', 'label-prefix'],
            'label-alpha' => ['labelalpha', 'label-alpha'],
            'label-title' => ['labeltitle', 'label-title'],
            'extra-alpha' => ['extraalpha', 'extra-alpha'],
            'extra-date' => ['extradate', 'extra-date'],
            'extra-title' => ['extratitle', 'extra-title'],
            'date-addon' => ['dateaddon', 'date-addon', 'dateaddendum', 'date-addendum'],
            'original-date-addon' => ['origdateaddon', 'origdate-addon', 'orig-date-addon', 'originaldateaddon', 'original-date-addon'],
            'reprint-date-addon' => ['reprintdateaddon', 'reprintdate-addon', 'reprint-date-addon', 'reprintdateaddendum', 'reprint-date-addendum'],
            'event-date-addon' => ['eventdateaddon', 'eventdate-addon', 'event-date-addon'],
            'accessed-date-addon' => ['urldateaddon', 'urldate-addon', 'url-date-addon', 'accesseddateaddon', 'accessed-date-addon'],
            'biblatex-page-ref' => ['pageref', 'page-ref'],
            'biblatex-name-hash' => ['namehash', 'name-hash'],
            'biblatex-full-name-hash' => ['fullhash', 'full-hash'],
            'biblatex-bib-name-hash' => ['bibnamehash', 'bib-name-hash'],
            'biblatex-label-name-hash' => ['labelnamehash', 'label-name-hash'],
            'biblatex-author-name-hash' => ['authornamehash', 'author-name-hash', 'authorfullhash', 'author-full-hash'],
            'biblatex-editor-name-hash' => ['editornamehash', 'editor-name-hash', 'editorfullhash', 'editor-full-hash'],
            'biblatex-sort-name-hash' => ['sortnamehash', 'sort-name-hash'],
            'short-title' => ['shorttitle', 'short-title', 'title-short'],
            'subtitle' => ['subtitle'],
            'title-addon' => ['titleaddon', 'title-addon'],
            'container-subtitle' => [
                'journalsubtitle',
                'journal-subtitle',
                'booksubtitle',
                'book-subtitle',
                'container-subtitle',
                'containersubtitle',
                'publication-subtitle',
                'publicationsubtitle',
            ],
            'container-title-addon' => ['journaltitleaddon', 'booktitleaddon', 'journal-title-addon', 'book-title-addon', 'container-title-addon', 'containertitleaddon'],
            'main-title-addon' => ['maintitleaddon', 'main-title-addon'],
            'main-subtitle' => ['mainsubtitle', 'main-subtitle'],
            'reviewed-genre' => ['reviewedgenre', 'reviewed-genre', 'reviewgenre', 'review-genre'],
            'reviewed-subtitle' => ['reviewedsubtitle', 'reviewed-subtitle', 'reviewsubtitle'],
            'volume-subtitle' => ['volumesubtitle', 'volume-subtitle'],
            'volume-title-short' => ['shortvolumetitle', 'short-volume-title', 'volumetitleshort', 'volume-title-short'],
            'part-subtitle' => ['partsubtitle', 'part-subtitle'],
            'issue-subtitle' => ['issuesubtitle', 'issue-subtitle'],
            'issue-title-addon' => ['issuetitleaddon', 'issue-title-addon', 'issuetitle-addon'],
            'container-title-short' => [
                'shortjournal',
                'shortjournaltitle',
                'shortjournal-title',
                'journaltitle-short',
                'journalabbreviation',
                'journal-abbreviation',
                'container-title-short',
                'containertitleshort',
            ],
            'journal-abbreviation' => [
                'shortjournal',
                'shortjournaltitle',
                'shortjournal-title',
                'journaltitle-short',
                'journalabbreviation',
                'journal-abbreviation',
                'container-title-short',
                'containertitleshort',
            ],
            'event' => ['eventtitle', 'event-title', 'event'],
            'event-title-addon' => ['eventtitleaddon', 'event-title-addon'],
            'event-place' => ['venue', 'eventvenue', 'eventlocation', 'eventplace', 'event-place', 'event-location'],
            'event-type' => ['eventtype', 'event-type'],
            'edition' => ['edition'],
            'volume' => ['volume'],
            'number-of-volumes' => ['volumes'],
            'issue' => ['number', 'issue'],
            'page' => ['pages', 'page'],
            'page-first' => ['page-first', 'pagefirst'],
            'pagination' => ['pagination', 'page-label'],
            'book-pagination' => ['bookpagination', 'book-pagination'],
            'article-number' => ['eid', 'article-number', 'articlenumber'],
            'number-of-pages' => ['pagetotal', 'numpages', 'numberofpages', 'number-of-pages'],
            'chapter-number' => ['chapter'],
            'number' => ['number'],
            'source' => ['source', 'sourcetitle', 'source-title'],
            'division' => ['division', 'subdivision'],
            'section' => ['section'],
            'part' => ['part', 'part-number', 'partnumber'],
            'printing-number' => ['printingnumber', 'printing-number', 'printnumber', 'print-number', 'printing'],
            'supplement' => ['supplement'],
            'supplement-number' => ['supplementnumber', 'supplement-number'],
            'references' => ['references'],
            'dimensions' => ['dimensions', 'dimension'],
            'scale' => ['scale'],
            'DOI' => ['doi'],
            'URL' => ['url'],
            'URL-label' => ['urldescription', 'urltitle', 'urllabel', 'url-label', 'url-description'],
            'rights' => ['rights', 'copyright', 'license', 'licence'],
            'publisher' => ['publisher', 'institution', 'school', 'organization'],
            'publisher-place' => ['address', 'location', 'publisher-place'],
            'collection-title' => ['series', 'series-title', 'seriestitle', 'series-title-text', 'seriestitletext', 'collection-title', 'collectiontitle', 'collection-title-text', 'collectiontitletext'],
            'collection-title-short' => ['shortseries', 'short-series', 'series-short', 'series-title-short', 'seriestitleshort', 'shortcollection', 'collection-title-short', 'collectiontitleshort'],
            'collection-number' => ['seriesnumber', 'series-number', 'collectionnumber', 'collection-number'],
            'version' => ['version'],
            'status' => ['status', 'publication-status', 'publicationstatus', 'pubstate'],
            'medium' => ['howpublished', 'medium'],
            'ISBN' => ['isbn', 'isbn13', 'isbn-13', 'isbn10', 'isbn-10', 'eisbn', 'e-isbn', 'electronicisbn', 'electronic-isbn'],
            'ISSN' => ['issn', 'printissn', 'print-issn', 'pissn', 'p-issn', 'eissn', 'e-issn', 'electronicissn', 'electronic-issn', 'onlineissn', 'online-issn', 'issnonline', 'issn-online'],
            'ISAN' => ['isan'],
            'ISMN' => ['ismn'],
            'ISRN' => ['isrn'],
            'ISWC' => ['iswc'],
            'PMID' => ['pmid', 'pubmed', 'pubmedid', 'pubmed-id'],
            'PMCID' => ['pmcid', 'pmc', 'pmc-id', 'pmcid-id'],
            'MRNumber' => ['mrnumber', 'mr-number', 'mr', 'mathscinet'],
            'MRClass' => ['mrclass', 'mr-class'],
            'Zbl' => ['zbl', 'zbmath'],
            'JSTOR' => ['jstor', 'jstorid', 'jstor-id'],
            'HDL' => ['hdl', 'handle', 'hdlid', 'hdl-id', 'handleid', 'handle-id'],
            'LCCN' => ['lccn', 'lccnnumber', 'lccn-number'],
            'OCLC' => ['oclc', 'oclcnumber', 'oclc-number'],
            'ORCID' => ['orcid', 'orcidid', 'orcid-id'],
            'ISNI' => ['isni'],
            'VIAF' => ['viaf'],
            'ROR' => ['ror'],
            'Wikidata' => ['wikidata', 'wikidataid', 'wikidata-id', 'wd'],
            'archive' => ['archiveprefix', 'eprinttype', 'archive'],
            'archive-collection' => ['archivecollection', 'archive-collection', 'archive_collection'],
            'archive-place' => ['eprintclass', 'primaryclass', 'primary-class', 'archiveplace', 'archive-place'],
            'archive_location' => ['eprint', 'archive-location', 'archive_location', 'archivelocation'],
            'call-number' => ['callnumber', 'call-number', 'library', 'shelfmark', 'shelf-mark'],
            'language' => ['language', 'langid', 'hyphenation'],
            'original-title-addon' => ['origtitleaddon', 'origtitle-addon', 'originaltitleaddon', 'original-title-addon'],
            'original-subtitle' => ['origsubtitle', 'originalsubtitle', 'original-subtitle'],
            'original-genre' => ['origtype', 'origgenre', 'originaltype', 'original-type', 'originalgenre', 'original-genre'],
            'original-page' => ['origpages', 'orig-pages', 'origpage', 'orig-page', 'originalpages', 'original-pages', 'originalpage', 'original-page'],
            'original-page-first' => ['origpagefirst', 'orig-page-first', 'originalpagefirst', 'original-page-first'],
            'original-volume' => ['origvolume', 'orig-volume', 'originalvolume', 'original-volume'],
            'original-issue' => ['origissue', 'orig-issue', 'originalissue', 'original-issue'],
            'original-number' => ['orignumber', 'orig-number', 'originalnumber', 'original-number'],
            'original-edition' => ['origedition', 'orig-edition', 'originaledition', 'original-edition'],
            'original-publisher' => ['origpublisher', 'originalpublisher', 'original-publisher'],
            'original-publisher-place' => ['origlocation', 'origaddress', 'originalpublisherplace', 'original-publisher-place'],
            'original-language' => ['origlanguage', 'originallanguage', 'original-language'],
            'reprint-title' => ['reprinttitle', 'reprint-title'],
            'reprint-page' => ['reprintpages', 'reprint-pages', 'reprintpage', 'reprint-page'],
            'reprint-page-first' => ['reprintpagefirst', 'reprint-page-first'],
            'reprint-volume' => ['reprintvolume', 'reprint-volume'],
            'reprint-issue' => ['reprintissue', 'reprint-issue'],
            'reprint-number' => ['reprintnumber', 'reprint-number'],
            'reprint-edition' => ['reprintedition', 'reprint-edition'],
            'abstract' => ['abstract', 'annotation', 'annote'],
            'annotation' => ['annotation', 'annote'],
            'addendum' => ['addendum'],
            'note' => ['note', 'addendum'],
            'name-addon' => ['nameaddon', 'name-addon'],
            'genre' => ['type', 'entrysubtype'],
            'entry-subtype' => ['entrysubtype', 'entry-subtype'],
            'author-type' => ['authortype', 'author-type'],
            'container-author-type' => ['bookauthortype', 'bookauthor-type', 'containerauthortype', 'container-author-type'],
            'patent-type' => ['patenttype', 'patent-type'],
            'jurisdiction' => ['jurisdiction'],
            'related' => ['related'],
            'related-type' => ['relatedtype', 'related-type'],
            'related-string' => ['relatedstring', 'related-string'],
            'related-options' => ['relatedoptions', 'related-options'],
            'xref' => ['xref', 'crossref'],
            'gender' => ['gender'],
        ];

        foreach ($stringFields as $target => $names) {
            $value = $this->firstField($fields, $names);
            if ($value === null || $value === '') {
                continue;
            }
            $item[$target] = in_array($target, ['page', 'original-page', 'reprint-page'], true) ? str_replace('--', '-', $value) : $value;
        }

        if (($item['page-first'] ?? '') === '') {
            $pageFirst = $this->firstPageFromRange((string) ($item['page'] ?? ''));
            if ($pageFirst !== '') {
                $item['page-first'] = $pageFirst;
            }
        }
        if (($item['original-page-first'] ?? '') === '') {
            $pageFirst = $this->firstPageFromRange((string) ($item['original-page'] ?? ''));
            if ($pageFirst !== '') {
                $item['original-page-first'] = $pageFirst;
            }
        }
        if (($item['reprint-page-first'] ?? '') === '') {
            $pageFirst = $this->firstPageFromRange((string) ($item['reprint-page'] ?? ''));
            if ($pageFirst !== '') {
                $item['reprint-page-first'] = $pageFirst;
            }
        }

        foreach ([
            'publisher-list' => ['publisher', 'institution', 'school', 'organization'],
            'publisher-place-list' => ['address', 'location', 'publisher-place'],
            'original-publisher-list' => ['origpublisher', 'originalpublisher', 'original-publisher'],
            'original-publisher-place-list' => ['origlocation', 'origaddress', 'originalpublisherplace', 'original-publisher-place'],
            'language-list' => ['language'],
            'original-language-list' => ['origlanguage', 'originallanguage', 'original-language'],
            'event-place-list' => ['venue', 'eventvenue', 'eventlocation', 'eventplace', 'event-place', 'event-location'],
        ] as $target => $names) {
            $values = $this->literalListFromFields($fields, $names);
            if (count($values) > 1) {
                $item[$target] = $values;
            }
        }

        $authorityFieldNames = [
            'authority-list',
            'authoritylist',
            'issuing-authority-list',
            'issuingauthoritylist',
            'authority',
            'issuing-authority',
            'issuingauthority',
        ];
        if (in_array($item['type'], ['patent', 'legislation', 'legal_case'], true)) {
            $authorityFieldNames = [
                ...$authorityFieldNames,
                'court',
                'institution',
                'organization',
            ];
        }
        $authorityList = $this->literalListFromFields($fields, $authorityFieldNames);
        if ($authorityList !== []) {
            $item['authority'] = implode('; ', $authorityList);
            if (count($authorityList) > 1) {
                $item['authority-list'] = $authorityList;
            }
        } else {
            $authority = $this->firstField($fields, $authorityFieldNames);
            if ($authority !== null && $authority !== '') {
                $item['authority'] = $authority;
            }
        }

        if (
            in_array($item['type'], ['patent', 'legislation', 'legal_case'], true)
            && (($item['jurisdiction'] ?? '') === '')
        ) {
            $jurisdiction = $this->firstField($fields, ['location', 'address']);
            if ($jurisdiction !== null && $jurisdiction !== '') {
                $item['jurisdiction'] = $jurisdiction;
            }
        }

        $patentType = (string) ($item['patent-type'] ?? '');
        if ($patentType === '' && $item['type'] === 'patent' && (($item['genre'] ?? '') !== '')) {
            $patentType = (string) $item['genre'];
            $item['patent-type'] = $patentType;
        }
        if ($patentType !== '') {
            $item['patent-type-label'] = $this->patentTypeLabel($patentType);
        }

        $this->normalizeIdentifierFields($item);

        $thesisType = $this->thesisTypeForEntry($type, $fields);
        if ($thesisType !== null && $thesisType !== '') {
            $item['thesis-type'] = $thesisType;
        }

        $shorthandListSortKey = $this->firstField($fields, ['listshorthand', 'list-shorthand', 'shorthandlistsortkey', 'shorthand-list-sort-key']);
        if ($shorthandListSortKey === null || $shorthandListSortKey === '') {
            $shorthandListSortKey = $this->firstField($fields, ['sortshorthand', 'sort-shorthand']);
        }
        if ($shorthandListSortKey === null || $shorthandListSortKey === '') {
            $shorthandListSortKey = $this->firstField($fields, ['shorthand']);
        }
        if ($shorthandListSortKey !== null && $shorthandListSortKey !== '') {
            $item['shorthand-list-sort-key'] = $shorthandListSortKey;
        }

        $nameFields = [
            'author' => ['author'],
            'editor' => ['editor'],
            'short-author' => ['shortauthor', 'short-author'],
            'short-editor' => ['shorteditor', 'short-editor'],
            'holder' => ['holder'],
            'translator' => ['translator'],
            'chair' => ['chair'],
            'container-author' => ['bookauthor', 'container-author'],
            'original-author' => ['origauthor', 'originalauthor', 'original-author'],
            'recipient' => ['recipient'],
            'reviewed-author' => ['reviewedauthor', 'reviewed-author'],
            'event-organizer' => ['eventorganizer', 'event-organizer', 'organizer'],
            'interviewer' => ['interviewer'],
            'compiler' => ['compiler'],
            'composer' => ['composer'],
            'contributor' => ['contributor'],
            'producer' => ['producer'],
            'performer' => ['performer'],
            'narrator' => ['narrator'],
            'host' => ['host'],
            'guest' => ['guest'],
            'executive-producer' => ['executiveproducer', 'executive-producer'],
            'script-writer' => ['scriptwriter', 'script-writer'],
            'director' => ['director'],
            'editorial-director' => ['editorialdirector', 'editorial-director'],
            'illustrator' => ['illustrator'],
            'curator' => ['curator'],
            'collection-editor' => ['serieseditor', 'series-editor', 'collectioneditor', 'collection-editor'],
            'series-creator' => ['seriescreator', 'series-creator'],
            'editor-translator' => ['editortranslator', 'editor-translator'],
            'redactor' => ['redactor'],
            'commentator' => ['commentator'],
            'annotator' => ['annotator'],
            'founder' => ['founder'],
            'continuator' => ['continuator'],
            'reviser' => ['reviser'],
            'collaborator' => ['collaborator'],
            'introduction' => ['introduction'],
            'foreword' => ['foreword'],
            'afterword' => ['afterword'],
        ];

        foreach ($nameFields as $target => $names) {
            $fieldNames = $this->parseNamesFromFirstField($fields, $names);
            if ($fieldNames !== []) {
                $item[$target] = $fieldNames;
            }
        }

        $editorialRoles = $this->editorialRolesFromFields($fields);
        foreach ($editorialRoles as $role) {
            $cslVariable = $this->editorialRoleCslNameVariable($role['type']);
            if ($cslVariable === null) {
                continue;
            }

            $existing = $item[$cslVariable] ?? [];
            $item[$cslVariable] = [
                ...(is_array($existing) ? $existing : []),
                ...$role['names'],
            ];
        }
        if ($editorialRoles !== []) {
            $item['editorial-roles'] = $editorialRoles;
        }

        $citationAliases = $this->citationAliases($fields);
        if ($citationAliases !== []) {
            $item['citation-aliases'] = $citationAliases;
        }

        $date = $this->dateObjectFromFields($fields, ['date'], [['year'], ['month'], ['day']], [
            'hour' => 'hour',
            'minute' => 'minute',
            'second' => 'second',
            'timezone' => 'timezone',
            'endhour' => 'endhour',
            'endminute' => 'endminute',
            'endsecond' => 'endsecond',
            'endtimezone' => 'endtimezone',
        ], [['endyear', 'end-year'], ['endmonth', 'end-month'], ['endday', 'end-day']]);
        if ($date !== null) {
            $item['issued'] = $this->dateWithEra($date, $fields, ['dateera']);
        }

        $accessed = $this->dateObjectFromFields($fields, ['urldate', 'accessed', 'accessdate'], [['urlyear', 'url-year'], ['urlmonth', 'url-month'], ['urlday', 'url-day']], [
            'hour' => 'urlhour',
            'minute' => 'urlminute',
            'second' => 'urlsecond',
            'timezone' => 'urltimezone',
            'endhour' => 'urlendhour',
            'endminute' => 'urlendminute',
            'endsecond' => 'urlendsecond',
            'endtimezone' => 'urlendtimezone',
        ], [['urlendyear', 'url-end-year'], ['urlendmonth', 'url-end-month'], ['urlendday', 'url-end-day']]);
        if ($accessed !== null) {
            $item['accessed'] = $this->dateWithEra($accessed, $fields, ['urldateera', 'url-date-era', 'accesseddateera', 'accessed-date-era']);
        }

        $originalDate = $this->dateObjectFromFields(
            $fields,
            ['origdate', 'originaldate', 'original-date'],
            [['origyear', 'orig-year'], ['origmonth', 'orig-month'], ['origday', 'orig-day']],
            [
                'hour' => 'orighour',
                'minute' => 'origminute',
                'second' => 'origsecond',
                'timezone' => 'origtimezone',
                'endhour' => 'origendhour',
                'endminute' => 'origendminute',
                'endsecond' => 'origendsecond',
                'endtimezone' => 'origendtimezone',
            ],
            [['origendyear', 'orig-end-year'], ['origendmonth', 'orig-end-month'], ['origendday', 'orig-end-day']]
        ) ?? $this->dateObjectFromFields(
            $fields,
            [],
            [['originalyear', 'original-year'], ['originalmonth', 'original-month'], ['originalday', 'original-day']],
            [],
            [['originalendyear', 'original-end-year'], ['originalendmonth', 'original-end-month'], ['originalendday', 'original-end-day']]
        );
        if ($originalDate !== null) {
            $item['original-date'] = $this->dateWithEra($originalDate, $fields, ['origdateera', 'originaldateera', 'original-date-era']);
        }

        $reprintDate = $this->dateObjectFromFields($fields, ['reprintdate', 'reprint-date'], [['reprintyear', 'reprint-year'], ['reprintmonth', 'reprint-month'], ['reprintday', 'reprint-day']], [
            'hour' => 'reprinthour',
            'minute' => 'reprintminute',
            'second' => 'reprintsecond',
            'timezone' => 'reprinttimezone',
            'endhour' => 'reprintendhour',
            'endminute' => 'reprintendminute',
            'endsecond' => 'reprintendsecond',
            'endtimezone' => 'reprintendtimezone',
        ], [['reprintendyear', 'reprint-end-year'], ['reprintendmonth', 'reprint-end-month'], ['reprintendday', 'reprint-end-day']]);
        if ($reprintDate !== null) {
            $item['reprint-date'] = $this->dateWithEra($reprintDate, $fields, ['reprintdateera', 'reprint-date-era']);
        }

        $labelDate = $this->dateObjectFromFields(
            $fields,
            ['labeldate', 'label-date'],
            [['labelyear', 'label-year'], ['labelmonth', 'label-month'], ['labelday', 'label-day']],
            [],
            [['labelendyear', 'label-end-year'], ['labelendmonth', 'label-end-month'], ['labelendday', 'label-end-day']]
        );
        if ($labelDate !== null) {
            $item['label-date'] = $this->dateWithEra($labelDate, $fields, ['labeldateera', 'label-date-era']);
        }

        $eventDate = $this->dateObjectFromFields($fields, ['eventdate', 'event-date'], [['eventyear', 'event-year'], ['eventmonth', 'event-month'], ['eventday', 'event-day']], [
            'hour' => 'eventhour',
            'minute' => 'eventminute',
            'second' => 'eventsecond',
            'timezone' => 'eventtimezone',
            'endhour' => 'eventendhour',
            'endminute' => 'eventendminute',
            'endsecond' => 'eventendsecond',
            'endtimezone' => 'eventendtimezone',
        ], [['eventendyear', 'event-end-year'], ['eventendmonth', 'event-end-month'], ['eventendday', 'event-end-day']]);
        if ($eventDate !== null) {
            $item['event-date'] = $this->dateWithEra($eventDate, $fields, ['eventdateera', 'event-date-era']);
        }

        $availableDate = $this->dateObjectFromFields(
            $fields,
            ['availabledate', 'available-date', 'available'],
            [['availableyear', 'available-year'], ['availablemonth', 'available-month'], ['availableday', 'available-day']],
            [
                'hour' => 'availablehour',
                'minute' => 'availableminute',
                'second' => 'availablesecond',
                'timezone' => 'availabletimezone',
                'endhour' => 'availableendhour',
                'endminute' => 'availableendminute',
                'endsecond' => 'availableendsecond',
                'endtimezone' => 'availableendtimezone',
            ],
            [['availableendyear', 'available-end-year'], ['availableendmonth', 'available-end-month'], ['availableendday', 'available-end-day']],
            ['availableenddate', 'available-end-date']
        );
        if ($availableDate !== null) {
            $item['available-date'] = $this->dateWithEra($availableDate, $fields, ['availabledateera', 'available-date-era']);
        }

        $submittedDate = $this->dateObjectFromFields(
            $fields,
            ['submitted', 'submitteddate', 'submitted-date', 'submissiondate', 'submission-date'],
            [['submittedyear', 'submitted-year'], ['submittedmonth', 'submitted-month'], ['submittedday', 'submitted-day']],
            [
                'hour' => 'submittedhour',
                'minute' => 'submittedminute',
                'second' => 'submittedsecond',
                'timezone' => 'submittedtimezone',
                'endhour' => 'submittedendhour',
                'endminute' => 'submittedendminute',
                'endsecond' => 'submittedendsecond',
                'endtimezone' => 'submittedendtimezone',
            ],
            [['submittedendyear', 'submitted-end-year'], ['submittedendmonth', 'submitted-end-month'], ['submittedendday', 'submitted-end-day']],
            ['submittedenddate', 'submitted-end-date', 'submissionenddate', 'submission-end-date']
        );
        if ($submittedDate !== null) {
            $item['submitted'] = $this->dateWithEra($submittedDate, $fields, ['submitteddateera', 'submitted-date-era']);
        }

        $keywords = $this->keywordList($this->firstField($fields, ['keywords', 'keyword', 'keyword-list', 'keywordlist']));
        if ($keywords !== []) {
            $item['keyword'] = $keywords;
        }

        $categories = $this->keywordList($this->firstField($fields, ['categories', 'category', 'category-list', 'categorylist']));
        if ($categories !== []) {
            $item['categories'] = $categories;
        }

        $sourceFileField = $this->combinedSourceFileField($fields);
        $sourceFiles = $this->sourceFilesFromField($sourceFileField);
        if ($sourceFiles !== []) {
            $item['sourceFiles'] = $sourceFiles;
        }
        $sourceFileDiagnostics = $this->sourceFileDiagnosticsFromField($sourceFileField);
        if ($sourceFileDiagnostics !== []) {
            $item['sourceFileDiagnostics'] = $sourceFileDiagnostics;
        }

        $customFields = $this->biblatexCustomFieldsFromFields($fields);
        if ($customFields !== []) {
            $item['biblatex-custom-fields'] = $customFields;
        }

        $customLists = $this->biblatexCustomListsFromFields($fields);
        if ($customLists !== []) {
            $item['biblatex-custom-lists'] = $customLists;
        }

        $customNames = $this->biblatexCustomNamesFromFields($fields);
        if ($customNames !== []) {
            $item['biblatex-custom-names'] = $customNames;
        }

        $fieldAnnotations = $this->biblatexFieldAnnotationsFromFields($fields);
        if ($fieldAnnotations !== []) {
            $item['biblatex-field-annotations'] = $fieldAnnotations;
        }

        $options = $this->biblatexEntryOptions($fields);
        if ($options !== []) {
            $item['biblatex-options'] = $options;
        }

        $disambiguationSummary = $this->biblatexDisambiguationSummary($item);
        if ($disambiguationSummary !== '') {
            $item['biblatex-disambiguation-summary'] = $disambiguationSummary;
        }

        $languageOptions = $this->biblatexOptionList($fields['langidopts'] ?? '');
        if ($languageOptions !== []) {
            $item['biblatex-language-options'] = $languageOptions;
        }

        $refsection = $this->firstField($fields, ['refsection', 'ref-section']);
        if ($refsection !== null && $refsection !== '') {
            $item['biblatex-refsection'] = $refsection;
        }

        $refsegment = $this->firstField($fields, ['refsegment', 'ref-segment']);
        if ($refsegment !== null && $refsegment !== '') {
            $item['biblatex-refsegment'] = $refsegment;
        }

        if (($item['archive'] ?? '') !== '' || ($item['archive-collection'] ?? '') !== '' || ($item['archive_location'] ?? '') !== '') {
            $summaryParts = [];
            foreach (['archive', 'archive-collection', 'archive-place', 'archive_location'] as $field) {
                if (($item[$field] ?? '') !== '') {
                    $summaryParts[] = (string) $item[$field];
                }
            }
            $item['archive-summary'] = implode(':', $summaryParts);
        }

        return $item;
    }

    /**
     * @param array<string, array<string, mixed>> $itemsByKey
     * @return array<string, array<string, mixed>>
     */
    private function itemsByCitationKey(array $itemsByKey): array
    {
        $itemsByCitationKey = $itemsByKey;
        foreach ($itemsByKey as $key => $item) {
            $aliases = $item['citation-aliases'] ?? [];
            if (!is_array($aliases)) {
                continue;
            }

            foreach ($aliases as $alias) {
                if (!is_scalar($alias)) {
                    continue;
                }

                $alias = trim((string) $alias);
                if ($alias === '' || $alias === $key || isset($itemsByCitationKey[$alias])) {
                    continue;
                }

                $itemsByCitationKey[$alias] = $item;
            }
        }

        return $itemsByCitationKey;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, string> $fields
     * @param array<string, array{id:string, type:string, fields:array<string, string>}> $entriesByKey
     * @return array<string, mixed>
     */
    private function withBiblatexRelationMetadata(array $item, array $fields, array $entriesByKey): array
    {
        $xdata = $this->fieldKeyList($fields['xdata'] ?? '');
        if ($xdata !== []) {
            $xdataItems = $this->referencedXdataEntrySummaries($xdata, $entriesByKey);
            $missing = $this->missingXdataReferenceKeys($xdata, $entriesByKey);

            $item['xdataKeys'] = $xdata;
            $item['xdataItems'] = $xdataItems;
            $item['xdataSummary'] = $this->summarizedReferenceValues($xdataItems, $missing);
            if ($missing !== []) {
                $item['missingXdataKeys'] = $missing;
            }
        }

        $entrySet = $this->fieldKeyList($fields['entryset'] ?? '');
        if ($entrySet !== []) {
            $entrySetItems = $this->referencedEntrySummaries($entrySet, $entriesByKey);
            $missing = $this->missingReferenceKeys($entrySet, $entriesByKey);

            $item['entrySet'] = $entrySet;
            $item['entrySetItems'] = $entrySetItems;
            $item['entrySetSummary'] = $this->summarizedReferenceValues($entrySetItems, $missing);
            if ($missing !== []) {
                $item['missingEntrySetKeys'] = $missing;
            }
        }

        $related = $this->fieldKeyList($this->firstRawField($fields, ['related', 'related-keys', 'relatedkeys']));
        if ($related !== []) {
            $relatedItems = $this->referencedEntrySummaries($related, $entriesByKey);
            $missing = $this->missingReferenceKeys($related, $entriesByKey);

            $item['relatedKeys'] = $related;
            $item['relatedItems'] = $relatedItems;
            $item['relatedSummary'] = $this->summarizedReferenceValues($relatedItems, $missing);
            if ($missing !== []) {
                $item['missingRelatedKeys'] = $missing;
            }
        }

        $crossref = $this->fieldKeyList($fields['crossref'] ?? '');
        if ($crossref !== []) {
            $crossrefItems = $this->referencedEntrySummaries($crossref, $entriesByKey);
            $missing = $this->missingReferenceKeys($crossref, $entriesByKey);

            $item['crossrefKeys'] = $crossref;
            $item['crossrefItems'] = $crossrefItems;
            $item['crossrefSummary'] = $this->summarizedReferenceValues($crossrefItems, $missing);
            if ($missing !== []) {
                $item['missingCrossrefKeys'] = $missing;
            }
        }

        $xref = $this->fieldKeyList($fields['xref'] ?? '');
        if ($xref !== []) {
            $xrefItems = $this->referencedEntrySummaries($xref, $entriesByKey);
            $missing = $this->missingReferenceKeys($xref, $entriesByKey);

            $item['xrefKeys'] = $xref;
            $item['xrefItems'] = $xrefItems;
            $item['xrefSummary'] = $this->summarizedReferenceValues($xrefItems, $missing);
            if ($missing !== []) {
                $item['missingXrefKeys'] = $missing;
            }
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, array<string, mixed>> $itemsByCitationKey
     * @return array<string, mixed>
     */
    private function withRelatedReferenceMetadata(array $item, array $itemsByCitationKey): array
    {
        $relatedKeys = $this->fieldKeyList((string) ($item['related'] ?? ''));
        if ($relatedKeys === []) {
            return $item;
        }

        $item['relatedKeys'] = $relatedKeys;

        $options = $this->biblatexRelatedOptionList((string) ($item['related-options'] ?? ''));
        if ($options !== []) {
            $item['relatedOptions'] = $options;
        }

        $relatedItems = [];
        $missing = [];
        $seen = [];
        foreach ($relatedKeys as $relatedKey) {
            $relatedItem = $itemsByCitationKey[$relatedKey] ?? null;
            if ($relatedItem === null) {
                $missing[] = $relatedKey;
                continue;
            }

            $relatedId = (string) ($relatedItem['id'] ?? $relatedKey);
            if (isset($seen[$relatedId])) {
                continue;
            }

            $seen[$relatedId] = true;
            $relatedItems[] = $this->relatedReferenceSummary($relatedItem);
        }

        if ($relatedItems !== []) {
            $item['relatedItems'] = $relatedItems;
        }
        if ($missing !== []) {
            $item['missingRelatedKeys'] = $missing;
        } else {
            unset($item['missingRelatedKeys']);
        }
        $item['relatedSummary'] = $this->summarizedReferenceValues($relatedItems, $missing);

        return $item;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function relatedReferenceSummary(array $item): array
    {
        $summary = [
            'id' => (string) ($item['id'] ?? ''),
            'type' => (string) ($item['type'] ?? ''),
        ];

        $title = trim((string) ($item['title'] ?? ''));
        if ($title !== '') {
            $summary['title'] = $title;
        }
        if (isset($item['issued']) && is_array($item['issued'])) {
            $summary['issued'] = $item['issued'];
        }
        $options = $item['biblatex-options'] ?? [];
        $hasDataOnlyOption = is_array($options) && $this->hasDataOnlyOption(implode(',', array_map(
            static fn (mixed $option): string => (string) $option,
            $options
        )));
        if (($item['dataOnly'] ?? false) === true || $hasDataOnlyOption) {
            $summary['dataOnly'] = true;
        }

        return $summary;
    }

    /**
     * @param list<string> $keys
     * @param array<string, array{id:string, type:string, fields:array<string, string>}> $entriesByKey
     * @return list<array<string, mixed>>
     */
    private function referencedEntrySummaries(array $keys, array $entriesByKey): array
    {
        $summaries = [];
        foreach ($keys as $key) {
            $entry = $entriesByKey[$key] ?? null;
            if ($entry === null) {
                continue;
            }

            $fields = $this->resolveInheritedFields($entry, $entriesByKey);
            $summary = $this->toCslItem($entry['id'], $entry['type'], $fields);
            unset($summary['rawBibtex']);
            if ($entry['type'] === 'xdata' || $this->hasDataOnlyOption($entry['fields']['options'] ?? '')) {
                $summary['dataOnly'] = true;
            }
            $summaries[] = $summary;
        }

        return $summaries;
    }

    /**
     * @param list<string> $keys
     * @param array<string, array{id:string, type:string, fields:array<string, string>}> $entriesByKey
     * @return list<array<string, mixed>>
     */
    private function referencedXdataEntrySummaries(array $keys, array $entriesByKey): array
    {
        $summaries = [];
        foreach ($keys as $key) {
            $entry = $entriesByKey[$key] ?? null;
            if ($entry === null || $entry['type'] !== 'xdata') {
                continue;
            }

            $fields = $this->resolveInheritedFields($entry, $entriesByKey);
            $summary = $this->toCslItem($entry['id'], $entry['type'], $fields);
            unset($summary['rawBibtex']);
            $summary['dataOnly'] = true;
            $summaries[] = $summary;
        }

        return $summaries;
    }

    /**
     * @param list<string> $keys
     * @param array<string, array{id:string, type:string, fields:array<string, string>}> $entriesByKey
     * @return list<string>
     */
    private function missingReferenceKeys(array $keys, array $entriesByKey): array
    {
        return array_values(array_filter(
            $keys,
            static fn (string $key): bool => !isset($entriesByKey[$key])
        ));
    }

    /**
     * @param list<string> $keys
     * @param array<string, array{id:string, type:string, fields:array<string, string>}> $entriesByKey
     * @return list<string>
     */
    private function missingXdataReferenceKeys(array $keys, array $entriesByKey): array
    {
        return array_values(array_filter(
            $keys,
            static fn (string $key): bool => !isset($entriesByKey[$key]) || $entriesByKey[$key]['type'] !== 'xdata'
        ));
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<string> $missing
     */
    private function summarizedReferenceValues(array $items, array $missing): string
    {
        $values = [];
        foreach ($items as $item) {
            $label = trim((string) ($item['title'] ?? $item['id'] ?? ''));
            if ($label === '') {
                continue;
            }

            $date = $this->referenceDateLabel($item);
            $values[] = $date === '' ? $label : $label . ' (' . $date . ')';
        }

        foreach ($missing as $key) {
            $values[] = 'missing: ' . $key;
        }

        return implode('; ', $values);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function referenceDateLabel(array $item): string
    {
        $parts = $item['issued']['date-parts'][0] ?? null;
        if (!is_array($parts) || $parts === []) {
            return '';
        }

        $formatted = [];
        foreach (array_values($parts) as $index => $part) {
            if (!is_int($part) && !is_numeric($part)) {
                continue;
            }
            $formatted[] = $index === 0 ? (string) (int) $part : str_pad((string) (int) $part, 2, '0', STR_PAD_LEFT);
        }

        return implode('-', $formatted);
    }

    private function hasDataOnlyOption(string $options): bool
    {
        foreach ($this->biblatexOptionList($options) as $option) {
            $name = strtolower(trim(explode('=', $option, 2)[0]));
            if ($name === 'dataonly') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function relatedOptionSummary(array $item): string
    {
        $options = $item['relatedOptions'] ?? $this->biblatexRelatedOptionList((string) ($item['related-options'] ?? ''));
        if (!is_array($options)) {
            return '';
        }

        return implode('; ', array_values(array_filter(
            array_map(static fn (mixed $option): string => trim((string) $option), $options),
            static fn (string $option): bool => $option !== ''
        )));
    }

    /**
     * @param array{id:string, type:string, fields:array<string, string>} $entry
     * @param array<string, array{id:string, type:string, fields:array<string, string>}> $entriesByKey
     * @param list<string> $stack
     * @return array<string, string>
     */
    private function resolveInheritedFields(array $entry, array $entriesByKey, array $stack = []): array
    {
        if (in_array($entry['id'], $stack, true)) {
            throw new \InvalidArgumentException('BibTeX inheritance cycle involving entry: ' . $entry['id']);
        }

        $stack[] = $entry['id'];
        $fields = $this->resolveXdataFields($entry, $entriesByKey, $stack);
        $crossref = trim($fields['crossref'] ?? '');
        if ($crossref === '' || !isset($entriesByKey[$crossref])) {
            return $fields;
        }

        $parentFields = $this->resolveInheritedFields($entriesByKey[$crossref], $entriesByKey, $stack);
        foreach ($this->crossrefInheritedFields($entry['type'], $fields, $parentFields) as $field => $value) {
            if (($fields[$field] ?? '') === '') {
                $fields[$field] = $value;
            }
        }

        return $fields;
    }

    /**
     * @param array{id:string, type:string, fields:array<string, string>} $entry
     * @param array<string, array{id:string, type:string, fields:array<string, string>}> $entriesByKey
     * @param list<string> $stack
     * @return array<string, string>
     */
    private function resolveXdataFields(array $entry, array $entriesByKey, array $stack): array
    {
        $fields = $entry['fields'];
        foreach ($this->fieldKeyList($fields['xdata'] ?? '') as $key) {
            $parent = $entriesByKey[$key] ?? null;
            if ($parent === null || $parent['type'] !== 'xdata') {
                continue;
            }

            $parentFields = $this->resolveInheritedFields($parent, $entriesByKey, $stack);
            unset($parentFields['crossref'], $parentFields['xdata']);
            foreach ($parentFields as $field => $value) {
                if (($fields[$field] ?? '') === '') {
                    $fields[$field] = $value;
                }
            }
        }

        return $fields;
    }

    /**
     * @param array<string, string> $childFields
     * @param array<string, string> $parentFields
     * @return array<string, string>
     */
    private function crossrefInheritedFields(string $childType, array $childFields, array $parentFields): array
    {
        $inherited = $parentFields;
        unset($inherited['crossref']);

        $containerField = $this->crossrefTitleContainerField($childType);
        if ($containerField !== null && !$this->hasAnyField($childFields, ['booktitle', 'journaltitle', 'journal'])) {
            $containerParts = $this->crossrefParentContainerTitleParts($containerField, $parentFields);
            if ($containerParts['title'] !== '') {
                $inherited[$containerField] = $containerParts['title'];
            }

            $subtitleField = $this->crossrefContainerSubtitleField($containerField);
            if ($containerParts['subtitle'] !== '' && !$this->hasAnyField($childFields, [$subtitleField])) {
                $inherited[$subtitleField] = $containerParts['subtitle'];
            }

            $titleAddonField = $this->crossrefContainerTitleAddonField($containerField);
            if ($containerParts['titleAddon'] !== '' && !$this->hasAnyField($childFields, [$titleAddonField])) {
                $inherited[$titleAddonField] = $containerParts['titleAddon'];
            }
        }

        unset($inherited['title'], $inherited['subtitle'], $inherited['titleaddon']);

        return $inherited;
    }

    private function crossrefTitleContainerField(string $childType): ?string
    {
        return match (strtolower($childType)) {
            'article' => 'journal',
            'bookinbook',
            'conference',
            'inbook',
            'incollection',
            'inproceedings',
            'inreference',
            'suppbook',
            'suppcollection' => 'booktitle',
            default => null,
        };
    }

    /**
     * @param array<string, string> $parentFields
     * @return array{title:string, subtitle:string, titleAddon:string}
     */
    private function crossrefParentContainerTitleParts(string $containerField, array $parentFields): array
    {
        $subtitleFields = $containerField === 'journal'
            ? ['journalsubtitle', 'booksubtitle', 'subtitle']
            : ['booksubtitle', 'journalsubtitle', 'subtitle'];
        $titleAddonFields = $containerField === 'journal'
            ? ['journaltitleaddon', 'booktitleaddon', 'titleaddon']
            : ['booktitleaddon', 'journaltitleaddon', 'titleaddon'];

        return [
            'title' => $this->firstRawField($parentFields, ['booktitle', 'journaltitle', 'journal', 'title']),
            'subtitle' => $this->firstRawField($parentFields, $subtitleFields),
            'titleAddon' => $this->firstRawField($parentFields, $titleAddonFields),
        ];
    }

    private function crossrefContainerSubtitleField(string $containerField): string
    {
        return $containerField === 'journal' ? 'journalsubtitle' : 'booksubtitle';
    }

    private function crossrefContainerTitleAddonField(string $containerField): string
    {
        return $containerField === 'journal' ? 'journaltitleaddon' : 'booktitleaddon';
    }

    /**
     * @return list<string>
     */
    private function fieldKeyList(string $value): array
    {
        $keys = [];
        foreach (preg_split('/[,;]+/', $value) ?: [] as $key) {
            $key = trim($key);
            if ($key !== '') {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $names
     */
    private function firstRawField(array $fields, array $names): string
    {
        foreach ($names as $name) {
            if (($fields[$name] ?? '') !== '') {
                return $fields[$name];
            }
        }

        return '';
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $names
     */
    private function hasAnyField(array $fields, array $names): bool
    {
        foreach ($names as $name) {
            if (($fields[$name] ?? '') !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $titleNames
     * @param list<string> $subtitleNames
     */
    private function composedTitle(array $fields, array $titleNames, array $subtitleNames): ?string
    {
        $title = $this->firstField($fields, $titleNames);
        if ($title === null || $title === '') {
            return null;
        }

        $subtitle = $this->firstField($fields, $subtitleNames);
        if ($subtitle === null || $subtitle === '') {
            return $title;
        }

        return $title . ': ' . $subtitle;
    }

    private function cslType(string $type): string
    {
        return match ($type) {
            'article' => 'article-journal',
            'book',
            'collection',
            'manual',
            'mvbook',
            'mvcollection',
            'mvproceedings',
            'mvreference',
            'reference' => 'book',
            'bookinbook',
            'inbook',
            'incollection',
            'suppbook',
            'suppcollection' => 'chapter',
            'inreference' => 'entry-encyclopedia',
            'booklet' => 'pamphlet',
            'letter' => 'personal_communication',
            'misc' => 'document',
            'software' => 'software',
            'dataset' => 'dataset',
            'movie', 'video' => 'motion_picture',
            'audio', 'music' => 'song',
            'report', 'techreport' => 'report',
            'patent' => 'patent',
            'set' => 'entry',
            'legislation', 'legal' => 'legislation',
            'jurisdiction' => 'legal_case',
            'unpublished' => 'manuscript',
            'talk',
            'lecture',
            'presentation' => 'speech',
            'inproceedings', 'conference', 'proceedings' => 'paper-conference',
            'phdthesis', 'mastersthesis', 'thesis' => 'thesis',
            'mathesis' => 'thesis',
            'online', 'electronic', 'www' => 'webpage',
            default => 'article',
        };
    }

    /**
     * @param array<string, string> $fields
     */
    private function thesisTypeForEntry(string $type, array $fields): ?string
    {
        $explicit = $this->firstField($fields, ['thesistype', 'thesis-type']);
        if ($explicit !== null && $explicit !== '') {
            return $explicit;
        }

        $entryType = strtolower($type);
        if (in_array($entryType, ['thesis', 'phdthesis', 'mastersthesis', 'mathesis'], true)) {
            $fieldType = $this->firstField($fields, ['type']);
            if ($fieldType !== null && $fieldType !== '') {
                return $fieldType;
            }
        }

        return match ($entryType) {
            'phdthesis' => 'phdthesis',
            'mastersthesis', 'mathesis' => 'mathesis',
            default => null,
        };
    }

    /**
     * @param array<string, string> $fields
     */
    private function cslTypeForEntry(string $type, array $fields): string
    {
        $type = strtolower($type);
        if ($type === 'unpublished' && ($this->firstField($fields, ['eventtitle', 'event-title', 'event']) ?? '') !== '') {
            return 'speech';
        }

        return $this->cslType($type);
    }

    /**
     * @param array<string, string> $fields
     */
    private function combinedSourceFileField(array $fields): ?string
    {
        $values = [];
        foreach (['file', 'pdf'] as $name) {
            $value = trim($fields[$name] ?? '');
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values === [] ? null : implode('; ', $values);
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $names
     */
    private function firstField(array $fields, array $names): ?string
    {
        foreach ($names as $name) {
            if (($fields[$name] ?? '') !== '') {
                return $fields[$name];
            }
        }

        return null;
    }

    private function firstPageFromRange(string $pages): string
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
     */
    private function normalizeIdentifierFields(array &$item): void
    {
        foreach ([
            'DOI' => [$this, 'normalizeDoiIdentifier'],
            'URL' => [$this, 'normalizeUrlIdentifier'],
            'ISBN' => [$this, 'normalizeIsbnIdentifier'],
            'ISSN' => [$this, 'normalizeIssnIdentifier'],
            'PMID' => [$this, 'normalizePmidIdentifier'],
            'PMCID' => [$this, 'normalizePmcidIdentifier'],
        ] as $field => $normalizer) {
            $value = $item[$field] ?? null;
            if (!is_string($value) || $value === '') {
                continue;
            }

            $normalized = $normalizer($value);
            if ($normalized !== '') {
                $item[$field] = $normalized;
            }
        }
    }

    private function normalizeDoiIdentifier(string $value): string
    {
        $value = trim($value);
        $value = trim($value, "<> \t\r\n");
        $value = preg_replace('~\A(?:doi:\s*|https?://(?:dx\.)?doi\.org/)~i', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', '', $value) ?? $value;

        return strtolower(trim($value));
    }

    private function normalizeUrlIdentifier(string $value): string
    {
        return trim(trim($value), '<>');
    }

    private function normalizeIsbnIdentifier(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\A(?:e-?isbn|isbn(?:-1[03])?)\s*:?\s*/i', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', '', $value) ?? $value;

        return strtoupper(trim($value));
    }

    private function normalizeIssnIdentifier(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\A(?:p-?|e-?|print\s+|online\s+|electronic\s+)?issn\s*:?\s*/i', '', $value) ?? $value;
        $compact = strtoupper(preg_replace('/[\s-]+/u', '', $value) ?? $value);
        if (preg_match('/\A\d{4}\d{3}[\dX]\z/', $compact) === 1) {
            return substr($compact, 0, 4) . '-' . substr($compact, 4);
        }

        return strtoupper(trim($value));
    }

    private function normalizePmidIdentifier(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return $digits !== '' ? $digits : trim($value);
    }

    private function normalizePmcidIdentifier(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\Apmc(?:id)?\s*:?\s*/i', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', '', $value) ?? $value;

        if (preg_match('/\A\d+\z/', $value) === 1) {
            return 'PMC' . $value;
        }

        if (preg_match('/\Apmc/i', $value) === 1) {
            return 'PMC' . substr($value, 3);
        }

        return strtoupper($value);
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $dateFields
     * @param list<string|list<string>> $partFields
     * @param array<string, string> $timeFields
     * @param list<string|list<string>> $endPartFields
     * @param list<string> $endDateFields
     * @return array<string, mixed>|null
     */
    private function dateObjectFromFields(array $fields, array $dateFields, array $partFields, array $timeFields = [], array $endPartFields = [], array $endDateFields = []): ?array
    {
        foreach ($dateFields as $field) {
            $value = trim((string) ($fields[$field] ?? ''));
            if ($value === '') {
                continue;
            }

            $date = $this->dateObjectFromValue($value, $field);
            if ($date === null) {
                return null;
            }

            $end = $this->dateObjectFromEndFields($fields, $endDateFields, $endPartFields);
            $dateParts = $date['date-parts'] ?? [];
            $endParts = is_array($end) ? ($end['date-parts'][0] ?? []) : [];
            if (
                is_array($dateParts)
                && count($dateParts) === 1
                && is_array($endParts)
                && $endParts !== []
                && ($date['season'] ?? null) === null
                && ($end['season'] ?? null) === null
            ) {
                $date['date-parts'][] = $endParts;
            }

            return $this->dateWithTimeParts($date, $fields, $timeFields, $field);
        }

        $startField = $this->datePartFieldName($fields, $partFields, 0);
        if ($partFields === [] || $startField === null) {
            return null;
        }

        $start = $this->datePartInfoFromSplitFields($fields, $partFields);
        if ($start === null || $start['parts'] === []) {
            return null;
        }

        $dateParts = [$start['parts']];
        $season = $start['season'];
        $end = $this->dateObjectFromEndFields($fields, $endDateFields, $endPartFields);
        if ($end !== null) {
            $endParts = $end['date-parts'][0] ?? [];
            if (is_array($endParts) && $endParts !== [] && $season === null && ($end['season'] ?? null) === null) {
                $dateParts[] = $endParts;
            }
        }

        $date = ['date-parts' => $dateParts];
        if ($season !== null) {
            $date['season'] = $season;
        }

        return $this->dateWithTimeParts($date, $fields, $timeFields, $startField);
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $dateFields
     * @param list<string|list<string>> $partFields
     * @return array<string, mixed>|null
     */
    private function dateObjectFromEndFields(array $fields, array $dateFields, array $partFields): ?array
    {
        foreach ($dateFields as $field) {
            $value = trim((string) ($fields[$field] ?? ''));
            if ($value === '') {
                continue;
            }

            return $this->dateObjectFromValue($value, $field);
        }

        if ($partFields === [] || $this->datePartFieldName($fields, $partFields, 0) === null) {
            return null;
        }

        $end = $this->datePartInfoFromSplitFields($fields, $partFields);
        if ($end === null || $end['parts'] === []) {
            return null;
        }

        $date = ['date-parts' => [$end['parts']]];
        if ($end['season'] !== null) {
            $date['season'] = $end['season'];
        }

        return $date;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function dateObjectFromValue(string $date, string $field): ?array
    {
        $date = trim($date);
        if ($date === '') {
            return null;
        }

        $range = $this->dateRangeObjectFromValue($date, $field);
        if ($range !== null) {
            return $range;
        }

        if (preg_match('/^(-?\d{1,6})(?:[-\/](\d{1,2})(?:[-\/](\d{1,2}))?)?([?~%])?$/', $date, $matches) !== 1) {
            return null;
        }

        $parts = [(int) $matches[1]];
        $season = null;
        if (($matches[2] ?? '') !== '') {
            $month = (int) $matches[2];
            $season = $this->seasonFromBiblatexDateMonthCode($month);
            if ($season !== null) {
                if (($matches[3] ?? '') !== '') {
                    return null;
                }

                $dateObject = $this->dateObjectWithMarkers([[(int) $matches[1]]], (string) ($matches[4] ?? ''), $date);
                $dateObject['season'] = $season;

                return $dateObject;
            }

            $parts[] = $month;
        }

        if (($matches[3] ?? '') !== '') {
            $parts[] = (int) $matches[3];
        }

        return $this->dateObjectWithMarkers([$parts], (string) ($matches[4] ?? ''), $date);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function dateRangeObjectFromValue(string $date, string $field): ?array
    {
        if (substr_count($date, '/') !== 1) {
            return null;
        }

        [$start, $end] = array_map('trim', explode('/', $date, 2));
        if ($start !== '' && $end !== '') {
            $startParts = $this->dateRangeSideParts($start, $field);
            $endParts = $this->dateRangeSideParts($end, $field);
            if ($startParts === null || $endParts === null) {
                return null;
            }

            return $this->dateObjectWithMarkers([$startParts, $endParts], $this->dateRangeMarker($date), $date);
        }

        $endpoint = $start === '' ? $end : $start;
        if ($endpoint === '') {
            return null;
        }

        $parts = $this->dateRangeSideParts($endpoint, $field);
        if ($parts === null) {
            return null;
        }

        $range = $this->dateObjectWithMarkers([$parts], $this->dateRangeMarker($date), $date);
        $range['open-ended'] = $start === '' ? 'start' : 'end';
        $range['raw'] = $date;

        return $range;
    }

    /**
     * @return list<int>|null
     */
    private function dateRangeSideParts(string $value, string $field): ?array
    {
        if (preg_match('/^(-?\d{1,6})(?:-(\d{1,2})(?:-(\d{1,2}))?)?([?~%])?$/', $value, $matches) !== 1) {
            return null;
        }

        $parts = [(int) $matches[1]];
        if (($matches[2] ?? '') !== '') {
            $month = (int) $matches[2];
            if ($this->seasonFromBiblatexDateMonthCode($month) !== null || $month < 1 || $month > 12) {
                return null;
            }
            $parts[] = $month;
        }

        if (($matches[3] ?? '') !== '') {
            $day = (int) $matches[3];
            if ($day < 1 || $day > 31) {
                return null;
            }
            $parts[] = $day;
        }

        return $parts;
    }

    /**
     * @param array<string, string> $fields
     * @param list<string|list<string>> $partFields
     * @return array{parts:list<int>, season:int|null}|null
     */
    private function datePartInfoFromSplitFields(array $fields, array $partFields): ?array
    {
        $year = $this->datePartFieldValue($fields, $partFields, 0);
        if ($year === null) {
            return null;
        }

        $parts = [(int) $year];
        $season = null;
        $monthValue = $this->datePartFieldValue($fields, $partFields, 1);
        if ($monthValue !== null) {
            $month = $this->biblatexMonthNumber($monthValue);
            if ($month === null) {
                return ['parts' => $parts, 'season' => null];
            }

            $season = $this->seasonFromBiblatexDateMonthCode($month);
            if ($season !== null) {
                return ['parts' => $parts, 'season' => $season];
            }

            $parts[] = $month;
        }

        $day = $this->datePartFieldValue($fields, $partFields, 2);
        if ($day !== null) {
            $parts[] = (int) $day;
        }

        return ['parts' => $parts, 'season' => $season];
    }

    /**
     * @param array<string, string> $fields
     * @param list<string|list<string>> $partFields
     */
    private function datePartFieldName(array $fields, array $partFields, int $index): ?string
    {
        foreach ($this->datePartFieldNames($partFields, $index) as $name) {
            if (trim((string) ($fields[$name] ?? '')) !== '') {
                return $name;
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $fields
     * @param list<string|list<string>> $partFields
     */
    private function datePartFieldValue(array $fields, array $partFields, int $index): ?string
    {
        $name = $this->datePartFieldName($fields, $partFields, $index);
        if ($name === null) {
            return null;
        }

        return trim((string) $fields[$name]);
    }

    /**
     * @param list<string|list<string>> $partFields
     * @return list<string>
     */
    private function datePartFieldNames(array $partFields, int $index): array
    {
        $names = $partFields[$index] ?? [];
        if (is_string($names)) {
            return [$names];
        }

        if (!is_array($names)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $name): string => is_scalar($name) ? trim((string) $name) : '', $names),
            static fn (string $name): bool => $name !== ''
        ));
    }

    /**
     * @param list<list<int>> $dateParts
     * @return array<string, mixed>
     */
    private function dateObjectWithMarkers(array $dateParts, string $marker, string $raw): array
    {
        $date = ['date-parts' => $dateParts];
        [$circa, $uncertain] = $this->dateMarkerFlags($marker);
        if ($circa) {
            $date['circa'] = true;
        }

        if ($uncertain) {
            $date['uncertain'] = true;
        }

        if ($circa || $uncertain) {
            $date['raw'] = $raw;
        }

        return $date;
    }

    private function dateRangeMarker(string $date): string
    {
        $circa = false;
        $uncertain = false;
        foreach (array_map('trim', explode('/', $date, 2)) as $side) {
            if (preg_match('/([?~%])$/', $side, $matches) !== 1) {
                continue;
            }

            [$sideCirca, $sideUncertain] = $this->dateMarkerFlags($matches[1]);
            $circa = $circa || $sideCirca;
            $uncertain = $uncertain || $sideUncertain;
        }

        if ($circa && $uncertain) {
            return '%';
        }

        return $circa ? '~' : ($uncertain ? '?' : '');
    }

    /**
     * @return array{0:bool, 1:bool}
     */
    private function dateMarkerFlags(string $marker): array
    {
        return match ($marker) {
            '~' => [true, false],
            '?' => [false, true],
            '%' => [true, true],
            default => [false, false],
        };
    }

    private function biblatexMonthNumber(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        $lookup = strtolower(substr($value, 0, 3));
        $months = [
            'jan' => 1,
            'feb' => 2,
            'mar' => 3,
            'apr' => 4,
            'may' => 5,
            'jun' => 6,
            'jul' => 7,
            'aug' => 8,
            'sep' => 9,
            'oct' => 10,
            'nov' => 11,
            'dec' => 12,
        ];

        return $months[$lookup] ?? null;
    }

    private function seasonFromBiblatexDateMonthCode(int $month): ?int
    {
        return match ($month) {
            21 => 1,
            22 => 2,
            23 => 3,
            24 => 4,
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $date
     * @param array<string, string> $fields
     * @param array<string, string> $timeFields
     * @return array<string, mixed>
     */
    private function dateWithTimeParts(array $date, array $fields, array $timeFields, string $field): array
    {
        if ($timeFields === []) {
            return $date;
        }

        $time = $this->timeFromDatePartFields($fields, $timeFields, '', $field);
        if ($time !== '') {
            $date['time'] = $time;
        }

        $endTime = $this->timeFromDatePartFields($fields, $timeFields, 'end', $field);
        if ($endTime !== '') {
            $date['end-time'] = $endTime;
        }

        return $date;
    }

    /**
     * @param array<string, string> $fields
     * @param array<string, string> $timeFields
     */
    private function timeFromDatePartFields(array $fields, array $timeFields, string $prefix, string $field): string
    {
        $hourKey = $timeFields[$prefix . 'hour'] ?? null;
        $minuteKey = $timeFields[$prefix . 'minute'] ?? null;
        $secondKey = $timeFields[$prefix . 'second'] ?? null;
        $timezoneKey = $timeFields[$prefix . 'timezone'] ?? null;

        $hour = $hourKey === null ? '' : trim((string) ($fields[$hourKey] ?? ''));
        $minute = $minuteKey === null ? '' : trim((string) ($fields[$minuteKey] ?? ''));
        $second = $secondKey === null ? '' : trim((string) ($fields[$secondKey] ?? ''));
        $timezone = $timezoneKey === null ? '' : trim((string) ($fields[$timezoneKey] ?? ''));

        if ($hour === '' && $minute === '' && $second === '' && $timezone === '') {
            return '';
        }

        if ($hour === '') {
            return '';
        }

        $display = $this->twoDigitTimePart($hour, 0, 23);
        if ($display === '') {
            return '';
        }

        if ($minute !== '' || $second !== '') {
            $minutePart = $this->twoDigitTimePart($minute === '' ? '0' : $minute, 0, 59);
            if ($minutePart === '') {
                return '';
            }
            $display .= ':' . $minutePart;
        }

        if ($second !== '') {
            $secondPart = $this->twoDigitTimePart($second, 0, 59);
            if ($secondPart === '') {
                return '';
            }
            $display .= ':' . $secondPart;
        }

        if ($timezone !== '') {
            $normalizedTimezone = $this->normalizedDateTimeZone($timezone);
            if ($normalizedTimezone === '') {
                return '';
            }
            $display .= $normalizedTimezone;
        }

        return $display;
    }

    private function twoDigitTimePart(string $value, int $min, int $max): string
    {
        if (preg_match('/^\d{1,2}$/', $value) !== 1) {
            return '';
        }

        $number = (int) $value;
        if ($number < $min || $number > $max) {
            return '';
        }

        return str_pad((string) $number, 2, '0', STR_PAD_LEFT);
    }

    private function normalizedDateTimeZone(string $value): string
    {
        $value = strtoupper(trim($value));
        if ($value === 'Z') {
            return 'Z';
        }

        if (preg_match('/^([+-])(\d{2})(?::?(\d{2}))?$/', $value, $matches) !== 1) {
            return '';
        }

        $hour = (int) $matches[2];
        $minute = isset($matches[3]) && $matches[3] !== '' ? (int) $matches[3] : 0;
        if ($hour > 23 || $minute > 59) {
            return '';
        }

        return $matches[1] . str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . ':' . str_pad((string) $minute, 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param array<string, mixed> $date
     * @param array<string, string> $fields
     * @param list<string> $eraFields
     * @return array<string, mixed>
     */
    private function dateWithEra(array $date, array $fields, array $eraFields): array
    {
        foreach ($eraFields as $field) {
            $era = trim((string) ($fields[$field] ?? ''));
            if ($era === '') {
                continue;
            }

            $date['era'] = strtolower(str_replace('_', '-', $era));

            return $date;
        }

        return $date;
    }

    /**
     * @param array<string, string> $fields
     * @return list<int>|null
     */
    private function dateParts(array $fields): ?array
    {
        return $this->datePartsFromFields($fields, ['date'], ['year', 'month', 'day']);
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $dateFields
     * @param list<string> $ymdFields
     * @return list<int>|null
     */
    private function datePartsFromFields(array $fields, array $dateFields, array $ymdFields): ?array
    {
        $date = '';
        foreach ($dateFields as $field) {
            if (($fields[$field] ?? '') !== '') {
                $date = $fields[$field];
                break;
            }
        }

        if ($date !== '') {
            $parts = $this->datePartsFromValue($date);
            if ($parts !== null) {
                return $parts;
            }
        }

        if ($ymdFields === []) {
            return null;
        }

        [$yearField, $monthField, $dayField] = $ymdFields + [null, null, null];
        if (!is_string($yearField) || ($fields[$yearField] ?? '') === '') {
            return null;
        }

        $parts = [(int) $fields[$yearField]];
        $month = is_string($monthField) ? ($fields[$monthField] ?? '') : '';
        if ($month !== '' && ctype_digit($month)) {
            $parts[] = (int) $month;
        }

        $day = is_string($dayField) ? ($fields[$dayField] ?? '') : '';
        if ($day !== '' && ctype_digit($day)) {
            if (count($parts) === 1) {
                $parts[] = 1;
            }
            $parts[] = (int) $day;
        }

        return $parts;
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $dateFields
     * @param list<string> $ymdFields
     * @param list<string> $endDateFields
     * @param list<string> $endYmdFields
     * @return list<list<int>>|null
     */
    private function dateRangePartsFromFields(array $fields, array $dateFields, array $ymdFields, array $endDateFields, array $endYmdFields): ?array
    {
        foreach ($dateFields as $field) {
            $value = trim((string) ($fields[$field] ?? ''));
            if ($value === '' || !str_contains($value, '/')) {
                continue;
            }

            [$startValue, $endValue] = array_map('trim', explode('/', $value, 2));
            $start = $this->datePartsFromValue($startValue);
            if ($start === null) {
                continue;
            }

            $end = $this->datePartsFromValue($endValue);

            return $end === null ? [$start] : [$start, $end];
        }

        $start = $this->datePartsFromFields($fields, $dateFields, $ymdFields);
        if ($start === null) {
            return null;
        }

        $end = $this->datePartsFromFields($fields, $endDateFields, $endYmdFields);

        return $end === null ? [$start] : [$start, $end];
    }

    /**
     * @return list<int>|null
     */
    private function datePartsFromValue(string $date): ?array
    {
        if (preg_match('/^(-?\d{1,4})(?:-(\d{1,2})(?:-(\d{1,2}))?)?/', $date, $m) !== 1) {
            return null;
        }

        $parts = [(int) $m[1]];
        if (($m[2] ?? '') !== '') {
            $parts[] = (int) $m[2];
        }
        if (($m[3] ?? '') !== '') {
            $parts[] = (int) $m[3];
        }

        return $parts;
    }

    /**
     * @return list<string>
     */
    private function keywordList(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $keywords = [];
        foreach (preg_split('/[,;]+/', $value) ?: [] as $keyword) {
            $keyword = trim($keyword);
            if ($keyword !== '') {
                $keywords[] = $keyword;
            }
        }

        return $keywords;
    }

    /**
     * @return list<array{label:string, path:string, mediaType:string}>
     */
    private function sourceFilesFromField(?string $value): array
    {
        return array_map(
            static fn (array $entry): array => [
                'label' => $entry['label'],
                'path' => $entry['normalizedPath'],
                'mediaType' => $entry['mediaType'],
            ],
            array_values(array_filter(
                $this->sourceFileEntriesFromField($value),
                static fn (array $entry): bool => $entry['reason'] === ''
            ))
        );
    }

    /**
     * @return list<array{label:string, path:string, mediaType:string, reason:string, importable:bool}>
     */
    private function sourceFileDiagnosticsFromField(?string $value): array
    {
        return array_map(
            static fn (array $entry): array => [
                'label' => $entry['label'],
                'path' => $entry['path'],
                'mediaType' => $entry['mediaType'],
                'reason' => $entry['reason'],
                'importable' => false,
            ],
            array_values(array_filter(
                $this->sourceFileEntriesFromField($value),
                static fn (array $entry): bool => $entry['reason'] !== ''
            ))
        );
    }

    /**
     * @return list<array{label:string, path:string, normalizedPath:string, mediaType:string, reason:string}>
     */
    private function sourceFileEntriesFromField(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $files = [];
        foreach (explode(';', $value) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            $parsed = $this->parseSourceFileEntry($entry);
            $policy = $this->sourceFilePathPolicy($parsed['path']);
            $files[] = [
                'label' => $parsed['label'],
                'path' => $parsed['path'],
                'normalizedPath' => $policy['path'],
                'mediaType' => $parsed['mediaType'],
                'reason' => $policy['reason'],
            ];
        }

        return $files;
    }

    /**
     * @return array{label:string, path:string, mediaType:string}
     */
    private function parseSourceFileEntry(string $entry): array
    {
        $parts = array_map('trim', explode(':', $entry));
        if (count($parts) >= 3) {
            $label = array_shift($parts) ?? '';
            $mediaType = array_pop($parts) ?? '';
            $path = implode(':', $parts);
        } elseif (count($parts) === 2) {
            $label = '';
            [$path, $mediaType] = $parts;
        } else {
            $label = '';
            $path = $entry;
            $mediaType = '';
        }

        return [
            'label' => $label,
            'path' => trim($path),
            'mediaType' => $mediaType,
        ];
    }

    /**
     * @return array{path:string, reason:string}
     */
    private function sourceFilePathPolicy(string $path): array
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
     * @param array<string, string> $fields
     * @return list<string>
     */
    private function citationAliases(array $fields): array
    {
        $value = $this->firstField($fields, ['ids', 'citation-aliases', 'citationaliases', 'citation-alias', 'citationalias']);
        if ($value === null || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (string $alias): string => trim($alias), preg_split('/[,;]+/', $value) ?: []),
            static fn (string $alias): bool => $alias !== ''
        ));
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, string>
     */
    private function biblatexCustomFieldsFromFields(array $fields): array
    {
        $custom = [];
        foreach (self::BIBLATEX_CUSTOM_FIELDS as $field) {
            $value = trim($fields[$field] ?? '');
            if ($value !== '') {
                $custom[$field] = $value;
            }
        }

        return $custom;
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, list<string>>
     */
    private function biblatexCustomListsFromFields(array $fields): array
    {
        $custom = [];
        foreach (self::BIBLATEX_CUSTOM_LIST_FIELDS as $field) {
            $value = trim($fields[$field] ?? '');
            if ($value === '') {
                continue;
            }

            $values = $this->literalList($value);
            if ($values !== []) {
                $custom[$field] = $values;
            }
        }

        return $custom;
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, list<array<string, string>>>
     */
    private function biblatexCustomNamesFromFields(array $fields): array
    {
        $custom = [];
        foreach (self::BIBLATEX_CUSTOM_NAME_FIELDS as $field) {
            $value = trim($fields[$field] ?? '');
            if ($value === '') {
                continue;
            }

            $names = $this->parseNames($value);
            $names = $this->withBiblatexNameAnnotations($names, $this->biblatexNameAnnotationsForField($fields, $field));
            if ($names !== []) {
                $custom[$field] = $names;
            }
        }

        return $custom;
    }

    /**
     * @return list<string>
     */
    private function biblatexOptionList(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map(
                static fn (string $option): string => trim($option),
                $this->splitTopLevel($value, ',')
            ),
            static fn (string $option): bool => $option !== ''
        ));
    }

    /**
     * @return list<string>
     */
    private function biblatexRelatedOptionList(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        $separator = str_contains($value, ';') ? ';' : ',';

        return array_values(array_filter(
            array_map(
                static fn (string $option): string => trim($option),
                $this->splitTopLevel($value, $separator)
            ),
            static fn (string $option): bool => $option !== ''
        ));
    }

    /**
     * @param array<string, string> $fields
     * @return list<string>
     */
    private function biblatexEntryOptions(array $fields): array
    {
        $options = $this->biblatexOptionList($fields['options'] ?? '');
        foreach (self::BIBLATEX_ENTRY_OPTION_FIELDS as $field => $name) {
            if (!array_key_exists($field, $fields)) {
                continue;
            }

            $value = $this->cleanValue($fields[$field]);
            if ($value === '') {
                continue;
            }

            $this->appendOrReplaceBiblatexOption($options, $name, $value);
        }

        return $options;
    }

    /**
     * @param list<string> $options
     */
    private function appendOrReplaceBiblatexOption(array &$options, string $name, string $value): void
    {
        $normalizedName = $this->normalizedBiblatexOptionName($name);
        foreach ($options as $index => $option) {
            $existingName = $this->normalizedBiblatexOptionName(explode('=', $option, 2)[0]);
            if ($existingName === $normalizedName) {
                unset($options[$index]);
            }
        }

        $options[] = $name . '=' . $value;
        $options = array_values($options);
    }

    private function normalizedBiblatexOptionName(string $name): string
    {
        $name = strtolower($this->cleanValue($name));

        return preg_replace('/[-_\s]+/', '', $name) ?? $name;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function biblatexDisambiguationSummary(array $item): string
    {
        $parts = [];
        foreach ([
            'biblatex-page-ref' => 'pageref',
            'biblatex-name-hash' => 'namehash',
            'biblatex-full-name-hash' => 'fullhash',
            'biblatex-bib-name-hash' => 'bibnamehash',
            'biblatex-label-name-hash' => 'labelnamehash',
            'biblatex-author-name-hash' => 'authornamehash',
            'biblatex-editor-name-hash' => 'editornamehash',
            'biblatex-sort-name-hash' => 'sortnamehash',
        ] as $field => $label) {
            $value = trim((string) ($item[$field] ?? ''));
            if ($value !== '') {
                $parts[] = $label . '=' . $value;
            }
        }

        return implode('; ', $parts);
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, list<array{name:string, value:string}>>
     */
    private function biblatexFieldAnnotationsFromFields(array $fields): array
    {
        $annotations = [];
        foreach ($fields as $field => $value) {
            if (preg_match('/^([A-Za-z0-9_.-]+)\+an(?::([A-Za-z][A-Za-z0-9_-]*))?$/u', $field, $matches) !== 1) {
                continue;
            }

            $baseField = strtolower($matches[1]);
            if (in_array($baseField, self::BIBLATEX_NAME_ANNOTATION_FIELDS, true)) {
                continue;
            }

            $defaultName = $this->normalizedBiblatexFieldAnnotationName((string) ($matches[2] ?? ''));
            foreach ($this->biblatexFieldAnnotationEntries($value, $defaultName) as $annotation) {
                $annotations[$baseField][] = $annotation;
            }
        }

        return $annotations;
    }

    /**
     * @return list<array{name:string, value:string}>
     */
    private function biblatexFieldAnnotationEntries(string $value, string $defaultName): array
    {
        if (trim($value) === '') {
            return [];
        }

        $separator = str_contains($value, ';') ? ';' : ',';
        $entries = [];
        foreach ($this->splitTopLevel($value, $separator) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            $name = $defaultName;
            $text = $entry;
            if (preg_match('/^([A-Za-z][A-Za-z0-9_-]*)?\s*=\s*(.+)$/u', $entry, $matches) === 1) {
                if (($matches[1] ?? '') !== '') {
                    $name = $this->normalizedBiblatexFieldAnnotationName($matches[1]);
                }
                $text = $matches[2];
            }

            $text = trim($text);
            if ($text === '') {
                continue;
            }

            $entries[] = [
                'name' => $name === '' ? 'default' : $name,
                'value' => $text,
            ];
        }

        return $entries;
    }

    private function normalizedBiblatexFieldAnnotationName(string $name): string
    {
        return strtolower(str_replace('_', '-', trim($name)));
    }

    /**
     * @return list<string>
     */
    private function splitTopLevel(string $value, string $separator): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;
        $length = strlen($value);
        for ($cursor = 0; $cursor < $length; $cursor++) {
            $char = $value[$cursor];
            if ($char === '{' || $char === '(' || $char === '[') {
                $depth++;
            } elseif (($char === '}' || $char === ')' || $char === ']') && $depth > 0) {
                $depth--;
            }

            if ($char === $separator && $depth === 0) {
                $parts[] = $buffer;
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $parts[] = $buffer;

        return $parts;
    }

    /**
     * @return list<string>
     */
    private function literalList(string $value): array
    {
        $values = [];
        foreach ($this->splitNames($value) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $values[] = $part;
            }
        }

        return $values;
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $names
     * @return list<string>
     */
    private function literalListFromFields(array $fields, array $names): array
    {
        foreach ($names as $name) {
            $value = trim($fields[$name] ?? '');
            if ($value === '') {
                continue;
            }

            return $this->literalList($value);
        }

        return [];
    }

    /**
     * @param mixed $values
     */
    private function literalListSummary(mixed $values): string
    {
        if (!is_array($values) || count($values) < 2) {
            return '';
        }

        $parts = array_values(array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), $values),
            static fn (string $value): bool => $value !== ''
        ));

        return count($parts) > 1 ? implode('; ', $parts) : '';
    }

    /**
     * @param mixed $fields
     */
    private function biblatexCustomFieldSummary(mixed $fields): string
    {
        if (!is_array($fields)) {
            return '';
        }

        $parts = [];
        foreach (self::BIBLATEX_CUSTOM_FIELDS as $field) {
            $value = trim((string) ($fields[$field] ?? ''));
            if ($value !== '') {
                $parts[] = $field . ': ' . $value;
            }
        }

        return implode('; ', $parts);
    }

    /**
     * @param mixed $lists
     */
    private function biblatexCustomListSummary(mixed $lists): string
    {
        if (!is_array($lists)) {
            return '';
        }

        $parts = [];
        foreach (self::BIBLATEX_CUSTOM_LIST_FIELDS as $field) {
            $values = $lists[$field] ?? [];
            if (!is_array($values)) {
                continue;
            }

            $values = array_values(array_filter(
                array_map(static fn (mixed $value): string => trim((string) $value), $values),
                static fn (string $value): bool => $value !== ''
            ));
            if ($values !== []) {
                $parts[] = $field . ': ' . implode('; ', $values);
            }
        }

        return implode('; ', $parts);
    }

    /**
     * @param mixed $namesByField
     */
    private function biblatexCustomNameSummary(mixed $namesByField): string
    {
        if (!is_array($namesByField)) {
            return '';
        }

        $parts = [];
        foreach (self::BIBLATEX_CUSTOM_NAME_FIELDS as $field) {
            $names = $namesByField[$field] ?? [];
            if (!is_array($names)) {
                continue;
            }

            $values = array_values(array_filter(
                array_map(fn (mixed $name): string => $this->biblatexCustomNameDisplay($name), $names),
                static fn (string $value): bool => $value !== ''
            ));
            if ($values !== []) {
                $parts[] = $field . ': ' . implode('; ', $values);
            }
        }

        return implode('; ', $parts);
    }

    /**
     * @param mixed $annotations
     */
    private function biblatexFieldAnnotationSummary(mixed $annotations): string
    {
        if (!is_array($annotations)) {
            return '';
        }

        $parts = [];
        foreach ($annotations as $field => $fieldAnnotations) {
            if (!is_array($fieldAnnotations)) {
                continue;
            }

            foreach ($fieldAnnotations as $annotation) {
                if (!is_array($annotation)) {
                    continue;
                }

                $value = trim((string) ($annotation['value'] ?? ''));
                if ($value === '') {
                    continue;
                }

                $name = trim((string) ($annotation['name'] ?? 'default'));
                $parts[] = (string) $field . ' ' . ($name === '' ? 'default' : $name) . ': ' . $value;
            }
        }

        return implode('; ', $parts);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function biblatexNameAnnotationSummary(array $item): string
    {
        $parts = [];
        foreach ($this->biblatexNameAnnotationSources() as $field => $label) {
            $names = $item[$field] ?? [];
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
                    $parts[] = $label . ' ' . ((int) $index + 1) . ($part !== '' && $part !== 'name' ? ' ' . $part : '') . ': ' . $value;
                }
            }
        }

        return implode('; ', $parts);
    }

    /**
     * @return array<string, string>
     */
    private function biblatexNameAnnotationSources(): array
    {
        return [
            'author' => 'Author',
            'editor' => 'Editor',
            'short-author' => 'Short author',
            'short-editor' => 'Short editor',
            'holder' => 'Holder',
            'translator' => 'Translator',
            'chair' => 'Chair',
            'container-author' => 'Container author',
            'original-author' => 'Original author',
            'recipient' => 'Recipient',
            'reviewed-author' => 'Reviewed author',
            'event-organizer' => 'Event organizer',
            'interviewer' => 'Interviewer',
            'compiler' => 'Compiler',
            'composer' => 'Composer',
            'contributor' => 'Contributor',
            'producer' => 'Producer',
            'performer' => 'Performer',
            'narrator' => 'Narrator',
            'host' => 'Host',
            'guest' => 'Guest',
            'executive-producer' => 'Executive producer',
            'script-writer' => 'Script writer',
            'director' => 'Director',
            'editorial-director' => 'Editorial director',
            'illustrator' => 'Illustrator',
            'curator' => 'Curator',
            'collection-editor' => 'Collection editor',
            'series-creator' => 'Series creator',
            'editor-translator' => 'Editor-translator',
            'redactor' => 'Redactor',
            'commentator' => 'Commentator',
            'annotator' => 'Annotator',
            'founder' => 'Founder',
            'continuator' => 'Continuator',
            'reviser' => 'Reviser',
            'collaborator' => 'Collaborator',
            'introduction' => 'Introduction',
            'foreword' => 'Foreword',
            'afterword' => 'Afterword',
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return list<string>
     */
    private function legalPatentBibliographyParts(array $item): array
    {
        if (!in_array((string) ($item['type'] ?? ''), ['patent', 'legislation', 'legal_case'], true)) {
            return [];
        }

        $parts = [];
        $number = trim((string) ($item['number'] ?? ''));
        if ($number !== '') {
            $parts[] = trim($this->legalPatentTypeLabel($item) . ' ' . $number);
        }

        foreach ([
            'authority' => 'Authority',
            'jurisdiction' => 'Jurisdiction',
            'status' => 'Status',
        ] as $field => $label) {
            $value = trim((string) ($item[$field] ?? ''));
            if ($value !== '') {
                $parts[] = $label . ': ' . rtrim($value, '.');
            }
        }

        return $parts;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function legalPatentTypeLabel(array $item): string
    {
        if (($item['type'] ?? '') === 'patent') {
            $label = trim((string) ($item['patent-type-label'] ?? ''));
            if ($label !== '') {
                return $label;
            }
        }

        $genre = trim((string) ($item['genre'] ?? ''));
        if ($genre !== '') {
            return ucfirst(str_replace(['_', '-'], ' ', $genre));
        }

        return ucfirst(str_replace('_', ' ', (string) ($item['type'] ?? 'legal')));
    }

    private function patentTypeLabel(string $type): string
    {
        return match (strtolower(trim($type))) {
            'patent' => 'Patent',
            'patentde' => 'German patent',
            'patenteu' => 'European patent',
            'patentfr' => 'French patent',
            'patentuk', 'patentgb' => 'British patent',
            'patentus' => 'U.S. patent',
            'patreq' => 'Patent request',
            'patreqde' => 'German patent request',
            'patreqeu' => 'European patent request',
            'patreqfr' => 'French patent request',
            'patrequk', 'patreqgb' => 'British patent request',
            'patrequs' => 'U.S. patent request',
            default => trim($type),
        };
    }

    /**
     * @param mixed $options
     */
    private function biblatexOptionSummary(mixed $options): string
    {
        if (!is_array($options)) {
            return '';
        }

        $values = array_values(array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), $options),
            static fn (string $value): bool => $value !== ''
        ));

        return implode('; ', $values);
    }

    private function biblatexReferenceContextSummary(string $refsection, string $refsegment): string
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
     * @param array<string, string> $fields
     * @return list<array{field:string, type:string, label:string, names:list<array<string, mixed>>}>
     */
    private function editorialRolesFromFields(array $fields): array
    {
        $roles = [];
        $primaryEditorType = $this->normalizedEditorialRoleType($fields['editortype'] ?? '');
        if ($primaryEditorType !== '' && $primaryEditorType !== 'editor') {
            $editorNames = $this->parseNamesFromFirstField($fields, ['editor']);
            if ($editorNames !== []) {
                $roles[] = [
                    'field' => 'editor',
                    'type' => $primaryEditorType,
                    'label' => $this->editorialRoleLabel($primaryEditorType),
                    'names' => $editorNames,
                ];
            }
        }

        foreach ([
            ['editora', 'editoratype'],
            ['editorb', 'editorbtype'],
            ['editorc', 'editorctype'],
        ] as [$nameField, $typeField]) {
            $names = $this->parseNamesFromFirstField($fields, [$nameField]);
            if ($names === []) {
                continue;
            }

            $type = $this->normalizedEditorialRoleType($fields[$typeField] ?? 'editor');
            $roles[] = [
                'field' => $nameField,
                'type' => $type,
                'label' => $this->editorialRoleLabel($type),
                'names' => $names,
            ];
        }

        return $roles;
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

    private function editorialRoleCslNameVariable(string $type): ?string
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

    private function editorialRoleLabel(string $type): string
    {
        $type = $this->normalizedEditorialRoleType($type);

        return match ($type) {
            'editor' => 'Editor',
            'compiler' => 'Compiler',
            'curator' => 'Curator',
            'director' => 'Director',
            'editorial-director' => 'Editorial director',
            'illustrator' => 'Illustrator',
            'interviewer' => 'Interviewer',
            'reviewed-author' => 'Reviewed author',
            'redactor' => 'Redactor',
            'founder' => 'Founder',
            'continuator' => 'Continuator',
            'reviser' => 'Reviser',
            'collaborator' => 'Collaborator',
            'organizer' => 'Organizer',
            'commentator' => 'Commentator',
            'annotator' => 'Annotator',
            'executive-producer' => 'Executive producer',
            'guest' => 'Guest',
            'host' => 'Host',
            'narrator' => 'Narrator',
            'performer' => 'Performer',
            'producer' => 'Producer',
            'script-writer' => 'Script writer',
            'introduction' => 'Introduction',
            'foreword' => 'Foreword',
            'afterword' => 'Afterword',
            default => ucfirst(strtolower(str_replace('-', ' ', $type))),
        };
    }

    /**
     * @param mixed $roles
     */
    private function biblatexEditorialRoleSummary(mixed $roles): string
    {
        if (!is_array($roles)) {
            return '';
        }

        $parts = [];
        foreach ($roles as $role) {
            if (!is_array($role)) {
                continue;
            }

            $names = $role['names'] ?? [];
            if (!is_array($names) || $names === []) {
                continue;
            }

            $nameSummary = implode('; ', array_values(array_filter(
                array_map(fn (mixed $name): string => $this->biblatexCustomNameDisplay($name), $names),
                static fn (string $name): bool => $name !== ''
            )));
            if ($nameSummary === '') {
                continue;
            }

            $label = trim((string) ($role['label'] ?? ''));
            if ($label === '') {
                $label = $this->editorialRoleLabel((string) ($role['type'] ?? ''));
            }
            $field = trim((string) ($role['field'] ?? ''));
            $prefix = $field === '' ? $label : $field . ' ' . strtolower($label);
            $parts[] = $prefix . ': ' . $nameSummary;
        }

        return implode('; ', $parts);
    }

    private function biblatexCustomNameDisplay(mixed $name): string
    {
        if (!is_array($name)) {
            return '';
        }

        $literal = trim((string) ($name['literal'] ?? ''));
        if ($literal !== '') {
            return $literal;
        }

        $family = trim((string) ($name['family'] ?? ''));
        $given = trim((string) ($name['given'] ?? ''));
        if ($family !== '' && $given !== '') {
            return $family . ', ' . $given;
        }

        return $family !== '' ? $family : $given;
    }

    /**
     * @return list<array<string, string>>
     */
    private function parseNames(string $value): array
    {
        $names = [];
        foreach ($this->splitNames($value) as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            if (strtolower($name) === 'others') {
                $names[] = ['literal' => 'et al.'];
                continue;
            }
            if (str_contains($name, ',')) {
                [$family, $given] = array_map('trim', explode(',', $name, 2));
                $names[] = array_filter(['family' => $family, 'given' => $given], static fn (string $part): bool => $part !== '');
                continue;
            }

            $parts = preg_split('/\s+/', $name) ?: [];
            if (count($parts) === 1) {
                $names[] = ['family' => $name];
                continue;
            }
            $family = array_pop($parts);
            $names[] = ['family' => (string) $family, 'given' => implode(' ', $parts)];
        }

        return $names;
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $fieldNames
     * @return list<array<string, mixed>>
     */
    private function parseNamesFromFirstField(array $fields, array $fieldNames): array
    {
        foreach ($fieldNames as $field) {
            $value = trim($fields[$field] ?? '');
            if ($value === '') {
                continue;
            }

            return $this->withBiblatexNameAnnotations($this->parseNames($value), $this->biblatexNameAnnotationsForField($fields, $field));
        }

        return [];
    }

    /**
     * @param array<string, string> $fields
     * @return list<array{index:int, part:string, value:string}>
     */
    private function biblatexNameAnnotationsForField(array $fields, string $field): array
    {
        $annotations = [];
        $pattern = '/^' . preg_quote($field, '/') . '\\+an(?::([A-Za-z][A-Za-z0-9_-]*))?$/u';
        foreach ($fields as $name => $value) {
            if (preg_match($pattern, $name, $matches) !== 1) {
                continue;
            }

            $defaultPart = strtolower(str_replace('_', '-', trim((string) ($matches[1] ?? ''))));
            foreach ($this->biblatexNameAnnotations($value, $defaultPart) as $annotation) {
                $annotations[] = $annotation;
            }
        }

        return $annotations;
    }

    /**
     * @param list<array<string, mixed>> $names
     * @param list<array{index:int, part:string, value:string}> $annotations
     * @return list<array<string, mixed>>
     */
    private function withBiblatexNameAnnotations(array $names, array $annotations): array
    {
        foreach ($annotations as $annotation) {
            $index = $annotation['index'] - 1;
            if (!isset($names[$index])) {
                continue;
            }

            $existing = $names[$index]['annotations'] ?? [];
            $names[$index]['annotations'] = [
                ...(is_array($existing) ? $existing : []),
                [
                    'part' => $annotation['part'],
                    'value' => $annotation['value'],
                ],
            ];
        }

        return $names;
    }

    /**
     * @return list<array{index:int, part:string, value:string}>
     */
    private function biblatexNameAnnotations(string $value, string $defaultPart = ''): array
    {
        if (trim($value) === '') {
            return [];
        }

        $defaultPart = strtolower(str_replace('_', '-', trim($defaultPart)));
        $separator = str_contains($value, ';') ? ';' : ',';
        $annotations = [];
        foreach ($this->splitTopLevel($value, $separator) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            if (preg_match('/^(\d+)\s*(?::\s*([A-Za-z][A-Za-z0-9_-]*))?\s*=\s*(.+)$/u', $entry, $matches) !== 1) {
                throw new \InvalidArgumentException('BibLaTeX name annotation is malformed: ' . $this->cleanValue($entry));
            }

            $index = (int) $matches[1];
            if ($index < 1) {
                throw new \InvalidArgumentException('BibLaTeX name annotation index must be one-based');
            }

            $text = $this->cleanValue($matches[3]);
            if ($text === '') {
                continue;
            }

            $part = strtolower(str_replace('_', '-', trim((string) ($matches[2] ?? ''))));
            $annotations[] = [
                'index' => $index,
                'part' => $part === '' ? ($defaultPart === '' ? 'name' : $defaultPart) : $part,
                'value' => $text,
            ];
        }

        return $annotations;
    }

    /**
     * @return list<string>
     */
    private function splitNames(string $value): array
    {
        $names = [];
        $buffer = '';
        $depth = 0;
        $length = strlen($value);

        for ($cursor = 0; $cursor < $length; $cursor++) {
            $char = $value[$cursor];
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth = max(0, $depth - 1);
            }

            if ($depth === 0 && substr($value, $cursor, 5) === ' and ') {
                $names[] = $buffer;
                $buffer = '';
                $cursor += 4;
                continue;
            }

            $buffer .= $char;
        }
        $names[] = $buffer;

        return $names;
    }

    /**
     * @param mixed $names
     */
    private function renderNames(mixed $names): string
    {
        if (!is_array($names) || $names === []) {
            return '';
        }

        $parts = [];
        foreach ($names as $name) {
            if (!is_array($name)) {
                continue;
            }
            if (($name['literal'] ?? '') !== '') {
                $parts[] = (string) $name['literal'];
                continue;
            }
            $given = (string) ($name['given'] ?? '');
            $family = (string) ($name['family'] ?? '');
            $parts[] = trim($given . ' ' . $family);
        }

        return $this->joinHumanList(array_values(array_filter($parts, static fn (string $part): bool => $part !== '')));
    }

    /**
     * @param list<string> $parts
     */
    private function joinHumanList(array $parts): string
    {
        return match (count($parts)) {
            0 => '',
            1 => $parts[0],
            2 => $parts[0] . ' and ' . $parts[1],
            default => implode(', ', array_slice($parts, 0, -1)) . ', and ' . end($parts),
        };
    }

    /**
     * @param array<string, mixed> $item
     */
    private function issuedYear(array $item): string
    {
        $parts = $item['issued']['date-parts'][0] ?? [];
        if (!is_array($parts) || !isset($parts[0])) {
            return '';
        }

        return (string) $parts[0];
    }

    private function dateDisplay(mixed $date): string
    {
        if (!is_array($date)) {
            return '';
        }

        $display = trim((string) ($date['display'] ?? ''));
        if ($display !== '') {
            return $display;
        }

        $literal = trim((string) ($date['literal'] ?? ''));
        if ($literal !== '') {
            return $literal;
        }

        $dateParts = $date['date-parts'] ?? [];
        if (!is_array($dateParts) || $dateParts === []) {
            return '';
        }

        $parts = [];
        foreach ($dateParts as $datePart) {
            if (is_array($datePart)) {
                $part = $this->datePartDisplay($datePart);
                if ($part !== '' && is_int($date['season'] ?? null) && count($datePart) === 1) {
                    $season = $this->dateSeasonName((int) $date['season']);
                    $part = $season === '' ? $part : $season . ' ' . $part;
                }
                if ($part !== '') {
                    $parts[] = $part;
                }
            }
        }

        $text = implode('/', $parts);
        if ($text === '') {
            return '';
        }

        $openEnded = trim((string) ($date['open-ended'] ?? $date['openEnded'] ?? ''));
        if ($openEnded === 'start') {
            return '/' . $text;
        }

        return $openEnded === 'end' ? $text . '/' : $text;
    }

    /**
     * @param array<string, mixed> $item
     * @return list<string>
     */
    private function dateMetadataBibliographyParts(array $item): array
    {
        $dates = [
            'issued' => $item['issued'] ?? null,
            'accessed' => $item['accessed'] ?? null,
            'available-date' => $item['available-date'] ?? null,
            'original-date' => $item['original-date'] ?? null,
            'reprint-date' => $item['reprint-date'] ?? null,
            'submitted' => $item['submitted'] ?? null,
            'event-date' => $item['event-date'] ?? null,
            'label-date' => $item['label-date'] ?? null,
        ];

        return array_values(array_filter([
            $this->dateMarkerSummary($dates),
            $this->dateTimeSummary($dates),
            $this->dateSeasonSummary($dates),
            $this->dateEraSummary($dates),
        ], static fn (string $part): bool => $part !== ''));
    }

    /**
     * @param array<string, mixed> $dates
     */
    private function dateMarkerSummary(array $dates): string
    {
        $parts = [];
        foreach ($dates as $label => $date) {
            if (!is_array($date)) {
                continue;
            }

            $status = $this->dateMarkerStatus($date);
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
    private function dateMarkerStatus(array $date): string
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
     * @param array<string, mixed> $dates
     */
    private function dateTimeSummary(array $dates): string
    {
        $parts = [];
        foreach ($dates as $label => $date) {
            if (!is_array($date)) {
                continue;
            }

            $time = trim((string) ($date['time'] ?? ''));
            $endTime = trim((string) ($date['end-time'] ?? $date['endTime'] ?? ''));
            if ($time === '' && $endTime === '') {
                continue;
            }

            $parts[] = $label . ' ' . ($time !== '' ? $time : '?') . ($endTime === '' ? '' : '/' . $endTime);
        }

        return $parts === [] ? '' : 'Date times: ' . implode('; ', $parts);
    }

    /**
     * @param array<string, mixed> $dates
     */
    private function dateSeasonSummary(array $dates): string
    {
        $parts = [];
        foreach ($dates as $label => $date) {
            if (!is_array($date) || !is_int($date['season'] ?? null)) {
                continue;
            }

            $parts[] = $label . ' ' . $this->dateSeasonName((int) $date['season']);
        }

        return $parts === [] ? '' : 'Date seasons: ' . implode('; ', $parts);
    }

    /**
     * @param array<string, mixed> $dates
     */
    private function dateEraSummary(array $dates): string
    {
        $parts = [];
        foreach ($dates as $label => $date) {
            if (!is_array($date)) {
                continue;
            }

            $era = trim((string) ($date['era'] ?? ''));
            if ($era === '') {
                continue;
            }

            $parts[] = $label . ' ' . $era;
        }

        return $parts === [] ? '' : 'Date eras: ' . implode('; ', $parts);
    }

    private function dateSeasonName(int $season): string
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
     * @param array<int, mixed> $parts
     */
    private function datePartDisplay(array $parts): string
    {
        $formatted = [];
        foreach (array_values($parts) as $index => $part) {
            if (!is_int($part) && !is_numeric($part)) {
                continue;
            }

            $value = (string) (int) $part;
            $formatted[] = $index === 0 ? $value : str_pad($value, 2, '0', STR_PAD_LEFT);
        }

        return implode('-', $formatted);
    }

    private function cleanValue(string $value, bool $trim = true): string
    {
        $value = $trim ? trim($value) : $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = strtr($value, [
            '\\&' => '&',
            '\\%' => '%',
            '\\_' => '_',
            '\\#' => '#',
            '\\$' => '$',
            '\\{' => '{',
            '\\}' => '}',
            '~' => ' ',
        ]);
        $value = preg_replace('/[{}]/', '', $value) ?? $value;

        return $trim ? trim($value) : $value;
    }

    private function readIdentifier(string $source, int &$cursor): string
    {
        $start = $cursor;
        $length = strlen($source);
        while ($cursor < $length && preg_match('/[A-Za-z0-9_-]/', $source[$cursor]) === 1) {
            $cursor++;
        }

        return substr($source, $start, $cursor - $start);
    }

    private function datePartsText(mixed $date): string
    {
        return $this->dateDisplay($date);
    }

    private function readFieldName(string $source, int &$cursor): string
    {
        $start = $cursor;
        $length = strlen($source);
        while ($cursor < $length && preg_match('/[A-Za-z0-9_.:+-]/', $source[$cursor]) === 1) {
            $cursor++;
        }

        return substr($source, $start, $cursor - $start);
    }

    private function skipWhitespace(string $source, int &$cursor): void
    {
        $length = strlen($source);
        while ($cursor < $length && ctype_space($source[$cursor])) {
            $cursor++;
        }
    }

    private function findBalancedEnd(string $source, int $cursor, string $open, string $close): ?int
    {
        $depth = 1;
        $length = strlen($source);

        while ($cursor < $length) {
            $char = $source[$cursor];
            if ($char === '\\') {
                $cursor += 2;
                continue;
            }
            if ($char === '"') {
                $this->readQuotedValue($source, $cursor);
                continue;
            }
            if ($char === $open) {
                $depth++;
            } elseif ($char === $close) {
                $depth--;
                if ($depth === 0) {
                    return $cursor;
                }
            }
            $cursor++;
        }

        return null;
    }
}
