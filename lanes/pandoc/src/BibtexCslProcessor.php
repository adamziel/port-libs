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
        $entries = [];
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

            $entries[$key] = [
                'id' => $key,
                'type' => $type,
                'fields' => $fields,
                'csl' => $this->toCslItem($key, $type, $fields),
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
        $citedKeys = $this->citedKeys($document);
        $items = [];
        $missing = [];

        foreach ($citedKeys as $key) {
            if (!isset($itemsByKey[$key])) {
                $missing[] = $key;
                continue;
            }

            $items[] = $itemsByKey[$key];
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

        $year = $this->issuedYear($item);
        if ($year !== '') {
            $parts[] = $year;
        }
        if (($item['page'] ?? '') !== '') {
            $parts[] = (string) $item['page'];
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
        if (($item['rights'] ?? '') !== '') {
            $parts[] = 'Rights: ' . (string) $item['rights'];
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
            'short-title' => ['shorttitle'],
            'title-addon' => ['titleaddon'],
            'container-title-addon' => ['journaltitleaddon', 'booktitleaddon'],
            'event' => ['eventtitle', 'event-title', 'event'],
            'event-title-addon' => ['eventtitleaddon', 'event-title-addon'],
            'event-place' => ['venue', 'eventvenue', 'eventlocation', 'eventplace', 'event-place', 'event-location'],
            'event-type' => ['eventtype', 'event-type'],
            'edition' => ['edition'],
            'volume' => ['volume'],
            'number-of-volumes' => ['volumes'],
            'issue' => ['number', 'issue'],
            'page' => ['pages', 'page'],
            'number-of-pages' => ['pagetotal', 'numpages', 'numberofpages', 'number-of-pages'],
            'chapter-number' => ['chapter'],
            'source' => ['source', 'sourcetitle', 'source-title'],
            'section' => ['section'],
            'supplement' => ['supplement'],
            'DOI' => ['doi'],
            'URL' => ['url'],
            'URL-label' => ['urldescription', 'urltitle', 'urllabel', 'url-label'],
            'rights' => ['rights', 'copyright', 'license', 'licence'],
            'publisher' => ['publisher', 'institution', 'organization'],
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
            'note' => ['note', 'addendum'],
            'genre' => ['type', 'entrysubtype'],
        ];

        foreach ($stringFields as $target => $names) {
            $value = $this->firstField($fields, $names);
            if ($value === null || $value === '') {
                continue;
            }
            $item[$target] = $target === 'page' ? str_replace('--', '-', $value) : $value;
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
            'online', 'electronic', 'www' => 'webpage',
            default => 'article',
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
