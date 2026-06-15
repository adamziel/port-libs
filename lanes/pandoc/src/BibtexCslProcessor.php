<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class BibtexCslProcessor
{
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
            $entries[$key] = [
                'id' => $entry['id'],
                'type' => $entry['type'],
                'fields' => $fields,
                'csl' => $this->toCslItem($key, $entry['type'], $fields),
            ];
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
        if (($item['page'] ?? '') !== '') {
            $parts[] = (string) $item['page'];
        }
        $containerTitleShort = (string) ($item['container-title-short'] ?? $item['journal-abbreviation'] ?? '');
        if ($containerTitleShort !== '') {
            $parts[] = 'Journal abbreviation: ' . rtrim($containerTitleShort, '.');
        }
        if (($item['article-number'] ?? '') !== '') {
            $parts[] = 'Article number: ' . (string) $item['article-number'];
        }
        if (($item['thesis-type'] ?? '') !== '') {
            $parts[] = 'Thesis type: ' . (string) $item['thesis-type'];
        }
        if (($item['source'] ?? '') !== '') {
            $parts[] = 'Source: ' . (string) $item['source'];
        }
        if (($item['section'] ?? '') !== '') {
            $parts[] = 'Section: ' . (string) $item['section'];
        }
        if (($item['supplement'] ?? '') !== '') {
            $parts[] = 'Supplement: ' . (string) $item['supplement'];
        }
        foreach ([
            'citation-label' => 'Citation label',
            'shorthand-intro' => 'Shorthand intro',
            'sort-shorthand' => 'Sort shorthand',
            'presort' => 'Presort',
            'sort-key' => 'Sort key',
            'label-prefix' => 'Label prefix',
            'extra-alpha' => 'Extra alpha',
        ] as $field => $label) {
            if (($item[$field] ?? '') !== '') {
                $value = (string) $item[$field];
                if ($field === 'volume-title-short') {
                    $value = rtrim($value, '.');
                }
                $parts[] = $label . ': ' . $value;
            }
        }
        if (($item['rights'] ?? '') !== '') {
            $parts[] = 'Rights: ' . (string) $item['rights'];
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
        if (($item['URL'] ?? '') !== '') {
            $parts[] = (string) $item['URL'];
        }
        foreach ([
            'reviewed-title' => 'Reviewed title',
            'reviewed-genre' => 'Reviewed genre',
            'main-title' => 'Main title',
            'main-title-addon' => 'Main title addendum',
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

        $containerTitle = $this->composedTitle($fields, ['journaltitle', 'journal', 'booktitle'], ['journalsubtitle', 'booksubtitle']);
        if ($containerTitle !== null && $containerTitle !== '') {
            $item['container-title'] = $containerTitle;
        }

        $reviewedTitle = $this->composedTitle($fields, ['reviewedtitle', 'reviewed-title'], ['reviewedsubtitle', 'reviewed-subtitle']);
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

        $originalTitle = $this->composedTitle($fields, ['origtitle', 'original-title'], ['origsubtitle', 'original-subtitle']);
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
            'label-prefix' => ['labelprefix', 'label-prefix'],
            'label-alpha' => ['labelalpha', 'label-alpha'],
            'label-title' => ['labeltitle', 'label-title'],
            'extra-alpha' => ['extraalpha', 'extra-alpha'],
            'extra-date' => ['extradate', 'extra-date'],
            'extra-title' => ['extratitle', 'extra-title'],
            'short-title' => ['shorttitle'],
            'title-addon' => ['titleaddon'],
            'container-title-addon' => ['journaltitleaddon', 'booktitleaddon'],
            'main-title-addon' => ['maintitleaddon', 'main-title-addon'],
            'reviewed-genre' => ['reviewedgenre', 'reviewed-genre', 'reviewgenre', 'review-genre'],
            'volume-title-short' => ['shortvolumetitle', 'short-volume-title', 'volumetitleshort', 'volume-title-short'],
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
            'article-number' => ['eid', 'article-number', 'articlenumber'],
            'number-of-pages' => ['pagetotal', 'numpages', 'numberofpages', 'number-of-pages'],
            'chapter-number' => ['chapter'],
            'source' => ['source', 'sourcetitle', 'source-title'],
            'section' => ['section'],
            'supplement' => ['supplement'],
            'DOI' => ['doi'],
            'URL' => ['url'],
            'URL-label' => ['urldescription', 'urltitle', 'urllabel', 'url-label'],
            'rights' => ['rights', 'copyright', 'license', 'licence'],
            'publisher' => ['publisher', 'institution', 'school', 'organization'],
            'publisher-place' => ['address', 'location', 'publisher-place'],
            'collection-title' => ['series', 'series-title', 'seriestitle', 'series-title-text', 'seriestitletext', 'collection-title', 'collectiontitle'],
            'collection-title-short' => ['shortseries', 'short-series', 'series-short', 'series-title-short', 'seriestitleshort', 'shortcollection', 'collection-title-short', 'collectiontitleshort'],
            'collection-number' => ['seriesnumber', 'series-number', 'collectionnumber', 'collection-number'],
            'version' => ['version'],
            'status' => ['status', 'publication-status', 'publicationstatus', 'pubstate'],
            'medium' => ['howpublished', 'medium'],
            'ISBN' => ['isbn'],
            'ISSN' => ['issn'],
            'archive' => ['archiveprefix', 'eprinttype', 'archive'],
            'archive-collection' => ['archivecollection', 'archive-collection', 'archive_collection'],
            'archive-place' => ['eprintclass', 'archiveplace', 'archive-place'],
            'archive_location' => ['eprint', 'archive-location', 'archive_location', 'archivelocation'],
            'call-number' => ['callnumber', 'call-number', 'library', 'shelfmark', 'shelf-mark'],
            'language' => ['language', 'langid', 'hyphenation'],
            'original-title-addon' => ['origtitleaddon', 'origtitle-addon', 'originaltitleaddon', 'original-title-addon'],
            'original-publisher' => ['origpublisher', 'originalpublisher', 'original-publisher'],
            'original-publisher-place' => ['origlocation', 'origaddress', 'originalpublisherplace', 'original-publisher-place'],
            'original-language' => ['origlanguage', 'originallanguage', 'original-language'],
            'abstract' => ['abstract', 'annotation', 'annote'],
            'annotation' => ['annotation', 'annote'],
            'note' => ['note', 'addendum'],
            'genre' => ['type', 'entrysubtype'],
            'related' => ['related'],
            'related-type' => ['relatedtype', 'related-type'],
            'related-string' => ['relatedstring', 'related-string'],
            'related-options' => ['relatedoptions', 'related-options'],
            'xref' => ['xref', 'crossref'],
        ];

        foreach ($stringFields as $target => $names) {
            $value = $this->firstField($fields, $names);
            if ($value === null || $value === '') {
                continue;
            }
            $item[$target] = $target === 'page' ? str_replace('--', '-', $value) : $value;
        }

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
            $value = $this->firstField($fields, $names);
            if ($value !== null && $value !== '') {
                $item[$target] = $this->parseNames($value);
            }
        }

        $citationAliases = $this->citationAliases($fields);
        if ($citationAliases !== []) {
            $item['citation-aliases'] = $citationAliases;
        }

        $date = $this->dateParts($fields);
        if ($date !== null) {
            $item['issued'] = ['date-parts' => [$date]];
        }

        $accessed = $this->datePartsFromFields($fields, ['urldate', 'accessed', 'accessdate'], []);
        if ($accessed !== null) {
            $item['accessed'] = ['date-parts' => [$accessed]];
        }

        $originalDate = $this->datePartsFromFields($fields, ['origdate', 'original-date'], ['origyear', 'origmonth', 'origday']);
        if ($originalDate !== null) {
            $item['original-date'] = ['date-parts' => [$originalDate]];
        }

        $eventDate = $this->datePartsFromFields($fields, ['eventdate', 'event-date'], ['eventyear', 'eventmonth', 'eventday']);
        if ($eventDate !== null) {
            $item['event-date'] = ['date-parts' => [$eventDate]];
        }

        $keywords = $this->keywordList($this->firstField($fields, ['keywords', 'keyword', 'keyword-list', 'keywordlist']));
        if ($keywords !== []) {
            $item['keyword'] = $keywords;
        }

        $categories = $this->keywordList($this->firstField($fields, ['categories', 'category', 'category-list', 'categorylist']));
        if ($categories !== []) {
            $item['categories'] = $categories;
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

        if ($date !== '' && preg_match('/^(-?\d{1,4})(?:-(\d{1,2})(?:-(\d{1,2}))?)?/', $date, $m) === 1) {
            $parts = [(int) $m[1]];
            if (($m[2] ?? '') !== '') {
                $parts[] = (int) $m[2];
            }
            if (($m[3] ?? '') !== '') {
                $parts[] = (int) $m[3];
            }

            return $parts;
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

    private function readFieldName(string $source, int &$cursor): string
    {
        $start = $cursor;
        $length = strlen($source);
        while ($cursor < $length && preg_match('/[A-Za-z0-9_-]/', $source[$cursor]) === 1) {
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
