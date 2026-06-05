<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class BibtexCslParser
{
    private int $offset = 0;
    private readonly int $length;

    /** @var array<string, string> */
    private array $strings;

    private function __construct(private readonly string $input)
    {
        $this->length = strlen($input);
        $this->strings = self::standardStrings();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function parse(string $bibtex): array
    {
        return (new self($bibtex))->parseEntries();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseEntries(): array
    {
        $entries = [];
        while (true) {
            $at = strpos($this->input, '@', $this->offset);
            if ($at === false) {
                break;
            }

            $this->offset = $at + 1;
            $this->skipWhitespace();
            $type = strtolower($this->readIdentifier());
            if ($type === '') {
                throw new \InvalidArgumentException('BibTeX entry missing entry type at byte ' . $at);
            }

            $this->skipWhitespace();
            $open = $this->peek();
            if ($open !== '{' && $open !== '(') {
                throw new \InvalidArgumentException('BibTeX entry ' . $type . ' must open with { or (');
            }

            $close = $open === '{' ? '}' : ')';
            $this->offset++;

            if ($type === 'comment') {
                $this->skipBalancedEntry($open, $close);
                continue;
            }

            if ($type === 'preamble') {
                $this->parseValue($close);
                $this->skipWhitespace();
                $this->expect($close);
                continue;
            }

            if ($type === 'string') {
                $this->parseStringEntry($close);
                continue;
            }

            $key = trim($this->readUntilTopLevel([',', $close]));
            if ($key === '') {
                throw new \InvalidArgumentException('BibTeX entry ' . $type . ' is missing a citation key');
            }

            $fields = [];
            if ($this->peek() === $close) {
                $this->offset++;
            } else {
                $this->expect(',');
                $fields = $this->parseFields($type, $key, $close);
            }

            $entries[] = [
                'type' => $type,
                'key' => $key,
                'fields' => $fields,
            ];
        }

        return self::entriesToCslItems($entries);
    }

    /**
     * @return array<string, string>
     */
    private function parseFields(string $type, string $key, string $close): array
    {
        $fields = [];
        while (true) {
            $this->skipWhitespace();
            if ($this->peek() === $close) {
                $this->offset++;
                break;
            }

            $field = strtolower($this->readIdentifier());
            if ($field === '') {
                throw new \InvalidArgumentException('BibTeX entry ' . $key . ' has a malformed field name');
            }

            $this->skipWhitespace();
            $this->expect('=');
            $fields[$field] = $this->parseValue($close);
            $this->skipWhitespace();

            $next = $this->peek();
            if ($next === ',') {
                $this->offset++;
                continue;
            }

            if ($next === $close) {
                $this->offset++;
                break;
            }

            throw new \InvalidArgumentException('BibTeX entry ' . $type . ':' . $key . ' field ' . $field . ' must end with comma or ' . $close);
        }

        return $fields;
    }

    private function parseStringEntry(string $close): void
    {
        while (true) {
            $this->skipWhitespace();
            if ($this->peek() === $close) {
                $this->offset++;
                return;
            }

            $name = strtolower($this->readIdentifier());
            if ($name === '') {
                throw new \InvalidArgumentException('BibTeX @string entry has a malformed name');
            }

            $this->skipWhitespace();
            $this->expect('=');
            $this->strings[$name] = self::cleanBibtexText($this->parseValue($close));
            $this->skipWhitespace();

            $next = $this->peek();
            if ($next === ',') {
                $this->offset++;
                continue;
            }

            if ($next === $close) {
                $this->offset++;
                return;
            }

            throw new \InvalidArgumentException('BibTeX @string entry must end with comma or ' . $close);
        }
    }

    private function parseValue(string $entryClose): string
    {
        $this->skipWhitespace();
        $value = $this->parseValueAtom($entryClose);

        while (true) {
            $this->skipWhitespace();
            if ($this->peek() !== '#') {
                break;
            }

            $this->offset++;
            $this->skipWhitespace();
            $value .= $this->parseValueAtom($entryClose);
        }

        return trim($value);
    }

    private function parseValueAtom(string $entryClose): string
    {
        $char = $this->peek();
        if ($char === null) {
            throw new \InvalidArgumentException('Unexpected end of BibTeX value');
        }

        if ($char === '{') {
            return $this->readBracedValue();
        }

        if ($char === '"') {
            return $this->readQuotedValue();
        }

        $token = '';
        while ($this->offset < $this->length) {
            $char = $this->input[$this->offset];
            if ($char === '#' || $char === ',' || $char === $entryClose || ctype_space($char)) {
                break;
            }

            $token .= $char;
            $this->offset++;
        }

        $token = trim($token);
        if ($token === '') {
            throw new \InvalidArgumentException('Unexpected empty BibTeX value atom');
        }

        $lookup = strtolower($token);

        return $this->strings[$lookup] ?? $token;
    }

    private function readBracedValue(): string
    {
        $this->expect('{');
        $depth = 1;
        $value = '';
        while ($this->offset < $this->length) {
            $char = $this->input[$this->offset++];
            if ($char === '{') {
                $depth++;
                $value .= $char;
                continue;
            }

            if ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return $value;
                }

                $value .= $char;
                continue;
            }

            $value .= $char;
        }

        throw new \InvalidArgumentException('Unterminated BibTeX braced value');
    }

    private function readQuotedValue(): string
    {
        $this->expect('"');
        $depth = 0;
        $value = '';
        while ($this->offset < $this->length) {
            $char = $this->input[$this->offset++];
            if ($char === '\\' && $this->offset < $this->length) {
                $value .= $char . $this->input[$this->offset++];
                continue;
            }

            if ($char === '{') {
                $depth++;
                $value .= $char;
                continue;
            }

            if ($char === '}' && $depth > 0) {
                $depth--;
                $value .= $char;
                continue;
            }

            if ($char === '"' && $depth === 0) {
                return $value;
            }

            $value .= $char;
        }

        throw new \InvalidArgumentException('Unterminated BibTeX quoted value');
    }

    /**
     * @param list<string> $delimiters
     */
    private function readUntilTopLevel(array $delimiters): string
    {
        $value = '';
        while ($this->offset < $this->length) {
            $char = $this->input[$this->offset];
            if (in_array($char, $delimiters, true)) {
                return $value;
            }

            $value .= $char;
            $this->offset++;
        }

        return $value;
    }

    private function readIdentifier(): string
    {
        $identifier = '';
        while ($this->offset < $this->length) {
            $char = $this->input[$this->offset];
            if (!preg_match('/[A-Za-z0-9_:\\.\\+-]/', $char)) {
                break;
            }

            $identifier .= $char;
            $this->offset++;
        }

        return $identifier;
    }

    private function skipWhitespace(): void
    {
        while ($this->offset < $this->length) {
            $char = $this->input[$this->offset];
            if (ctype_space($char)) {
                $this->offset++;
                continue;
            }

            if ($char === '%') {
                while ($this->offset < $this->length && !in_array($this->input[$this->offset], ["\n", "\r"], true)) {
                    $this->offset++;
                }
                continue;
            }

            return;
        }
    }

    private function skipBalancedEntry(string $open, string $close): void
    {
        $depth = 1;
        $inQuote = false;
        while ($this->offset < $this->length && $depth > 0) {
            $char = $this->input[$this->offset++];
            if ($char === '\\' && $inQuote && $this->offset < $this->length) {
                $this->offset++;
                continue;
            }

            if ($char === '"') {
                $inQuote = !$inQuote;
                continue;
            }

            if ($inQuote) {
                continue;
            }

            if ($char === $open) {
                $depth++;
            } elseif ($char === $close) {
                $depth--;
            }
        }

        if ($depth !== 0) {
            throw new \InvalidArgumentException('Unterminated BibTeX comment entry');
        }
    }

    private function expect(string $expected): void
    {
        if ($this->peek() !== $expected) {
            throw new \InvalidArgumentException('Expected BibTeX token ' . $expected . ' at byte ' . $this->offset);
        }

        $this->offset++;
    }

    private function peek(): ?string
    {
        return $this->offset < $this->length ? $this->input[$this->offset] : null;
    }

    /**
     * @param list<array{type:string, key:string, fields:array<string, string>}> $entries
     * @return list<array<string, mixed>>
     */
    private static function entriesToCslItems(array $entries): array
    {
        $entriesByKey = [];
        foreach ($entries as $entry) {
            if (isset($entriesByKey[$entry['key']])) {
                throw new \InvalidArgumentException('Duplicate BibTeX entry key: ' . $entry['key']);
            }

            $entriesByKey[$entry['key']] = $entry;
        }

        $items = [];
        foreach ($entries as $entry) {
            if (self::isDataEntryType($entry['type']) || self::isDataOnlyEntry($entry)) {
                continue;
            }

            $fields = self::resolveInheritedFields($entry, $entriesByKey);
            $items[] = self::withBiblatexRelationMetadata(
                self::entryToCslItem($entry['type'], $entry['key'], $fields),
                $fields,
                $entriesByKey
            );
        }

        return $items;
    }

    private static function isDataEntryType(string $type): bool
    {
        return in_array(strtolower($type), ['xdata'], true);
    }

    /**
     * @param array{type:string, key:string, fields:array<string, string>} $entry
     */
    private static function isDataOnlyEntry(array $entry): bool
    {
        return self::hasDataOnlyOption($entry['fields']['options'] ?? '');
    }

    private static function hasDataOnlyOption(string $options): bool
    {
        foreach (self::splitTopLevel(self::cleanBibtexText($options), ',') as $option) {
            $option = strtolower(trim($option));
            if ($option === '') {
                continue;
            }

            $name = trim(explode('=', $option, 2)[0]);
            if ($name === 'dataonly') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{type:string, key:string, fields:array<string, string>} $entry
     * @param array<string, array{type:string, key:string, fields:array<string, string>}> $entriesByKey
     * @param list<string> $stack
     * @return array<string, string>
     */
    private static function resolveInheritedFields(array $entry, array $entriesByKey, array $stack = []): array
    {
        if (in_array($entry['key'], $stack, true)) {
            throw new \InvalidArgumentException('BibTeX inheritance cycle involving entry: ' . $entry['key']);
        }

        $stack[] = $entry['key'];
        $fields = self::resolveXdataFields($entry, $entriesByKey, $stack);
        $crossref = self::cleanBibtexText($fields['crossref'] ?? '');
        if ($crossref === '' || !isset($entriesByKey[$crossref])) {
            return $fields;
        }

        $parent = $entriesByKey[$crossref];
        $parentFields = self::resolveInheritedFields($parent, $entriesByKey, $stack);
        $inherited = self::crossrefInheritedFields($entry['type'], $fields, $parentFields);
        foreach ($inherited as $field => $value) {
            if (!isset($fields[$field]) || trim($fields[$field]) === '') {
                $fields[$field] = $value;
            }
        }

        return $fields;
    }

    /**
     * @param array{type:string, key:string, fields:array<string, string>} $entry
     * @param array<string, array{type:string, key:string, fields:array<string, string>}> $entriesByKey
     * @param list<string> $stack
     * @return array<string, string>
     */
    private static function resolveXdataFields(array $entry, array $entriesByKey, array $stack): array
    {
        $fields = $entry['fields'];
        $xdata = self::biblatexKeyList($fields['xdata'] ?? '');
        if ($xdata === []) {
            return $fields;
        }

        foreach ($xdata as $key) {
            $parent = $entriesByKey[$key] ?? null;
            if ($parent === null || strtolower($parent['type']) !== 'xdata') {
                continue;
            }

            $parentFields = self::resolveInheritedFields($parent, $entriesByKey, $stack);
            unset($parentFields['xdata'], $parentFields['crossref']);
            foreach ($parentFields as $field => $value) {
                if (!isset($fields[$field]) || trim($fields[$field]) === '') {
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
    private static function crossrefInheritedFields(string $childType, array $childFields, array $parentFields): array
    {
        $inherited = $parentFields;
        unset($inherited['crossref']);

        $containerField = self::crossrefTitleContainerField($childType);
        if ($containerField !== null && !self::hasAnyField($childFields, ['booktitle', 'journaltitle', 'journal'])) {
            $parentTitle = $parentFields['booktitle'] ?? $parentFields['journaltitle'] ?? $parentFields['journal'] ?? $parentFields['title'] ?? '';
            if (trim($parentTitle) !== '') {
                $inherited[$containerField] = $parentTitle;
            }
        }

        unset($inherited['title']);

        return $inherited;
    }

    private static function crossrefTitleContainerField(string $childType): ?string
    {
        return match (strtolower($childType)) {
            'article' => 'journal',
            'conference', 'inbook', 'incollection', 'inproceedings' => 'booktitle',
            default => null,
        };
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $names
     */
    private static function hasAnyField(array $fields, array $names): bool
    {
        foreach ($names as $name) {
            if (isset($fields[$name]) && trim($fields[$name]) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, mixed>
     */
    private static function entryToCslItem(string $type, string $key, array $fields): array
    {
        $item = [
            'id' => $key,
            'type' => self::cslType($type),
            'title' => self::composedTitle($fields, ['title'], ['subtitle']),
            'short-title' => self::firstField($fields, ['shorttitle']),
            'title-addon' => self::firstField($fields, ['titleaddon']),
            'container-title' => self::composedTitle($fields, ['journaltitle', 'journal', 'booktitle'], ['journalsubtitle', 'booksubtitle']),
            'container-title-addon' => self::firstField($fields, ['journaltitleaddon', 'booktitleaddon']),
            'main-title' => self::composedTitle($fields, ['maintitle'], ['mainsubtitle']),
            'main-title-addon' => self::firstField($fields, ['maintitleaddon']),
            'event' => self::firstField($fields, ['eventtitle']),
            'event-title-addon' => self::firstField($fields, ['eventtitleaddon']),
            'event-place' => self::firstField($fields, ['venue', 'eventvenue', 'eventlocation', 'eventplace']),
            'event-type' => self::firstField($fields, ['eventtype']),
            'publisher' => self::firstField($fields, ['publisher', 'institution', 'school', 'organization']),
            'publisher-place' => self::firstField($fields, ['location', 'address', 'venue']),
            'page' => self::normalizePages(self::firstField($fields, ['pages', 'page'])),
            'number' => self::firstField($fields, ['number']),
            'volume' => self::firstField($fields, ['volume']),
            'issue' => self::issueField($type, $fields),
            'edition' => self::firstField($fields, ['edition']),
            'collection-title' => self::firstField($fields, ['series']),
            'collection-number' => self::firstField($fields, ['seriesnumber', 'series-number', 'collectionnumber', 'collection-number']),
            'number-of-volumes' => self::firstField($fields, ['volumes']),
            'number-of-pages' => self::firstField($fields, ['pagetotal', 'numpages', 'numberofpages', 'number-of-pages']),
            'chapter-number' => self::firstField($fields, ['chapter']),
            'part' => self::firstField($fields, ['part']),
            'genre' => self::firstField($fields, ['type', 'entrysubtype']),
            'authority' => self::firstField($fields, ['authority', 'court', 'institution', 'organization']),
            'jurisdiction' => self::firstField($fields, ['jurisdiction', 'location', 'address']),
            'status' => self::firstField($fields, ['status', 'pubstate']),
            'version' => self::firstField($fields, ['version']),
            'DOI' => self::firstField($fields, ['doi']),
            'URL' => self::firstField($fields, ['url']),
            'ISBN' => self::firstField($fields, ['isbn']),
            'ISSN' => self::firstField($fields, ['issn']),
            'archive' => self::firstField($fields, ['archiveprefix', 'eprinttype', 'archive']),
            'archive-place' => self::firstField($fields, ['eprintclass', 'archiveplace', 'archive-place']),
            'archive_location' => self::firstField($fields, ['eprint', 'archive_location', 'archive-location']),
            'language' => self::firstField($fields, ['langid', 'language', 'hyphenation']),
            'abstract' => self::firstField($fields, ['abstract', 'annote', 'annotation']),
            'medium' => self::firstField($fields, ['howpublished', 'medium']),
            'note' => self::firstField($fields, ['note']),
            'addendum' => self::firstField($fields, ['addendum']),
            'name-addon' => self::firstField($fields, ['nameaddon', 'name-addon']),
            'original-title' => self::firstField($fields, ['origtitle']),
            'original-publisher' => self::firstField($fields, ['origpublisher']),
            'original-publisher-place' => self::firstField($fields, ['origlocation', 'origaddress']),
            'original-language' => self::firstField($fields, ['origlanguage']),
            'rawBibtex' => [
                'type' => $type,
                'key' => $key,
                'fields' => $fields,
            ],
        ];

        $keywords = self::keywordList(self::firstField($fields, ['keywords', 'keyword']));
        if ($keywords !== []) {
            $item['keyword'] = $keywords;
        }

        $sourceFileField = self::firstField($fields, ['file']);
        $sourceFiles = self::sourceFilesFromField($sourceFileField);
        if ($sourceFiles !== []) {
            $item['sourceFiles'] = $sourceFiles;
        }
        $sourceFileDiagnostics = self::sourceFileDiagnosticsFromField($sourceFileField);
        if ($sourceFileDiagnostics !== []) {
            $item['sourceFileDiagnostics'] = $sourceFileDiagnostics;
        }

        $author = self::namesFromBibtexField($fields, 'author');
        if ($author !== []) {
            $item['author'] = $author;
        }

        $editor = self::namesFromBibtexField($fields, 'editor');
        if ($editor !== []) {
            $item['editor'] = $editor;
        }

        $holder = self::namesFromBibtexField($fields, 'holder');
        if ($holder !== []) {
            $item['holder'] = $holder;
        }

        $translator = self::namesFromBibtexField($fields, 'translator');
        if ($translator !== []) {
            $item['translator'] = $translator;
        }

        $eventOrganizer = self::eventOrganizerNames($type, $fields);
        if ($eventOrganizer !== []) {
            $item['event-organizer'] = $eventOrganizer;
        }

        $originalAuthor = self::namesFromFirstBibtexField($fields, ['origauthor', 'originalauthor']);
        if ($originalAuthor !== []) {
            $item['original-author'] = $originalAuthor;
        }

        $commentator = self::namesFromBibtexField($fields, 'commentator');
        if ($commentator !== []) {
            $item['commentator'] = $commentator;
        }

        $annotator = self::namesFromBibtexField($fields, 'annotator');
        if ($annotator !== []) {
            $item['annotator'] = $annotator;
        }

        $introduction = self::namesFromBibtexField($fields, 'introduction');
        if ($introduction !== []) {
            $item['introduction'] = $introduction;
        }

        $foreword = self::namesFromBibtexField($fields, 'foreword');
        if ($foreword !== []) {
            $item['foreword'] = $foreword;
        }

        $afterword = self::namesFromBibtexField($fields, 'afterword');
        if ($afterword !== []) {
            $item['afterword'] = $afterword;
        }

        $editorialRoles = self::editorialRolesFromFields($fields);
        foreach ($editorialRoles as $role) {
            $cslVariable = self::editorialRoleCslNameVariable($role['type']);
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

        $issued = self::dateFromFields($fields, ['date'], ['year', 'month', 'day']);
        if ($issued !== null) {
            $item['issued'] = $issued;
        }

        $originalDate = self::dateFromFields($fields, ['origdate'], ['origyear', 'origmonth', 'origday']);
        if ($originalDate !== null) {
            $item['original-date'] = $originalDate;
        }

        $eventDate = self::dateFromFields($fields, ['eventdate'], ['eventyear', 'eventmonth', 'eventday']);
        if ($eventDate !== null) {
            $item['event-date'] = $eventDate;
        }

        $accessed = self::dateFromFields($fields, ['urldate', 'accessed', 'accessdate'], []);
        if ($accessed !== null) {
            $item['accessed'] = $accessed;
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, string> $fields
     * @param array<string, array{type:string, key:string, fields:array<string, string>}> $entriesByKey
     * @return array<string, mixed>
     */
    private static function withBiblatexRelationMetadata(array $item, array $fields, array $entriesByKey): array
    {
        $entrySet = self::biblatexKeyList($fields['entryset'] ?? '');
        if ($entrySet !== []) {
            $item['entrySet'] = $entrySet;
            $item['entrySetItems'] = self::referencedEntrySummaries($entrySet, $entriesByKey);
            $missing = self::missingReferenceKeys($entrySet, $entriesByKey);
            if ($missing !== []) {
                $item['missingEntrySetKeys'] = $missing;
            }
        }

        $related = self::biblatexKeyList($fields['related'] ?? '');
        if ($related !== []) {
            $item['relatedKeys'] = $related;
            $item['relatedItems'] = self::referencedEntrySummaries($related, $entriesByKey);
            $missing = self::missingReferenceKeys($related, $entriesByKey);
            if ($missing !== []) {
                $item['missingRelatedKeys'] = $missing;
            }

            $relatedType = self::cleanBibtexText($fields['relatedtype'] ?? '');
            if ($relatedType !== '') {
                $item['relatedType'] = $relatedType;
            }

            $relatedString = self::cleanBibtexText($fields['relatedstring'] ?? '');
            if ($relatedString !== '') {
                $item['relatedString'] = $relatedString;
            }
        }

        return $item;
    }

    /**
     * @param list<string> $keys
     * @param array<string, array{type:string, key:string, fields:array<string, string>}> $entriesByKey
     * @return list<array<string, mixed>>
     */
    private static function referencedEntrySummaries(array $keys, array $entriesByKey): array
    {
        $summaries = [];
        foreach ($keys as $key) {
            $entry = $entriesByKey[$key] ?? null;
            if ($entry === null) {
                continue;
            }

            $fields = self::resolveInheritedFields($entry, $entriesByKey);
            $summary = self::entryToCslItem($entry['type'], $entry['key'], $fields);
            unset($summary['rawBibtex']);
            if (self::isDataEntryType($entry['type']) || self::isDataOnlyEntry($entry)) {
                $summary['dataOnly'] = true;
            }

            $summaries[] = $summary;
        }

        return $summaries;
    }

    /**
     * @param list<string> $keys
     * @param array<string, array{type:string, key:string, fields:array<string, string>}> $entriesByKey
     * @return list<string>
     */
    private static function missingReferenceKeys(array $keys, array $entriesByKey): array
    {
        return array_values(array_filter(
            $keys,
            static fn (string $key): bool => !isset($entriesByKey[$key])
        ));
    }

    private static function cslType(string $type): string
    {
        return match (strtolower($type)) {
            'article' => 'article-journal',
            'inproceedings', 'conference' => 'paper-conference',
            'inbook', 'incollection' => 'chapter',
            'inreference' => 'entry-encyclopedia',
            'set' => 'entry',
            'collection', 'mvcollection', 'mvbook', 'mvproceedings', 'mvreference', 'proceedings', 'reference' => 'book',
            'phdthesis', 'mastersthesis' => 'thesis',
            'report', 'techreport' => 'report',
            'patent' => 'patent',
            'legislation', 'legal' => 'legislation',
            'jurisdiction' => 'legal_case',
            'software' => 'software',
            'dataset' => 'dataset',
            'online', 'www', 'electronic' => 'webpage',
            'unpublished' => 'manuscript',
            default => strtolower($type),
        };
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $names
     */
    private static function firstField(array $fields, array $names): string
    {
        foreach ($names as $name) {
            if (isset($fields[$name])) {
                $value = self::cleanBibtexText($fields[$name]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    /**
     * @param array<string, string> $fields
     */
    private static function issueField(string $type, array $fields): string
    {
        $issue = self::firstField($fields, ['issue']);
        if ($issue !== '') {
            return $issue;
        }

        if (in_array(strtolower($type), ['article', 'periodical', 'suppperiodical'], true)) {
            return self::firstField($fields, ['number']);
        }

        return '';
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $titleFields
     * @param list<string> $subtitleFields
     */
    private static function composedTitle(array $fields, array $titleFields, array $subtitleFields): string
    {
        $title = self::firstField($fields, $titleFields);
        $subtitle = self::firstField($fields, $subtitleFields);
        if ($title === '') {
            return $subtitle;
        }

        if ($subtitle === '') {
            return $title;
        }

        $separator = preg_match('/[.?!:]\z/u', $title) === 1 ? ' ' : ': ';

        return $title . $separator . $subtitle;
    }

    /**
     * @return list<string>
     */
    private static function biblatexKeyList(string $value): array
    {
        $value = self::cleanBibtexText($value);
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(
            array_map(
                static fn (string $key): string => trim($key),
                self::splitTopLevel($value, ',')
            ),
            static fn (string $key): bool => $key !== ''
        ));
    }

    /**
     * @return list<string>
     */
    private static function keywordList(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $keywords = preg_split('/\s*[,;]\s*/', $value) ?: [];

        return array_values(array_filter(
            array_map(static fn (string $keyword): string => trim($keyword), $keywords),
            static fn (string $keyword): bool => $keyword !== ''
        ));
    }

    /**
     * @param array<string, string> $fields
     * @return list<array{field:string, type:string, label:string, names:list<array<string, mixed>>}>
     */
    private static function editorialRolesFromFields(array $fields): array
    {
        $roles = [];
        foreach ([
            ['editora', 'editoratype'],
            ['editorb', 'editorbtype'],
            ['editorc', 'editorctype'],
        ] as [$nameField, $typeField]) {
            $names = self::namesFromBibtexField($fields, $nameField);
            if ($names === []) {
                continue;
            }

            $type = self::normalizedEditorialRoleType(self::cleanBibtexText($fields[$typeField] ?? 'editor'));
            $roles[] = [
                'field' => $nameField,
                'type' => $type,
                'label' => self::editorialRoleLabel($type),
                'names' => $names,
            ];
        }

        return $roles;
    }

    /**
     * @param array<string, string> $fields
     * @return list<array<string, mixed>>
     */
    private static function eventOrganizerNames(string $type, array $fields): array
    {
        $organizer = self::namesFromFirstBibtexField($fields, ['eventorganizer', 'organizer']);
        if ($organizer !== []) {
            return $organizer;
        }

        if (!in_array(strtolower($type), ['conference', 'inproceedings', 'proceedings'], true)) {
            return [];
        }

        return self::namesFromBibtexField($fields, 'organization');
    }

    private static function normalizedEditorialRoleType(string $type): string
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

    private static function editorialRoleCslNameVariable(string $type): ?string
    {
        return match (self::normalizedEditorialRoleType($type)) {
            'editor',
            'compiler',
            'curator',
            'director',
            'editorial-director',
            'illustrator',
            'interviewer',
            'reviewed-author' => self::normalizedEditorialRoleType($type),
            default => null,
        };
    }

    private static function editorialRoleLabel(string $type): string
    {
        $type = self::normalizedEditorialRoleType($type);

        return match ($type) {
            'editor' => 'Editor',
            'compiler' => 'Compiler',
            'curator' => 'Curator',
            'director' => 'Director',
            'editorial-director' => 'Editorial director',
            'illustrator' => 'Illustrator',
            'interviewer' => 'Interviewer',
            'reviewed-author' => 'Reviewed author',
            default => ucfirst(strtolower(str_replace('-', ' ', $type))),
        };
    }

    /**
     * @return list<array{label:string, path:string, mediaType:string}>
     */
    private static function sourceFilesFromField(string $value): array
    {
        return array_map(
            static fn (array $entry): array => [
                'label' => $entry['label'],
                'path' => $entry['normalizedPath'],
                'mediaType' => $entry['mediaType'],
            ],
            array_values(array_filter(
                self::sourceFileEntriesFromField($value),
                static fn (array $entry): bool => $entry['reason'] === ''
            ))
        );
    }

    /**
     * @return list<array{label:string, path:string, mediaType:string, reason:string, importable:bool}>
     */
    private static function sourceFileDiagnosticsFromField(string $value): array
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
                self::sourceFileEntriesFromField($value),
                static fn (array $entry): bool => $entry['reason'] !== ''
            ))
        );
    }

    /**
     * @return list<array{label:string, path:string, normalizedPath:string, mediaType:string, reason:string}>
     */
    private static function sourceFileEntriesFromField(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $files = [];
        foreach (self::splitTopLevel($value, ';') as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            $parsed = self::parseSourceFileEntry($entry);
            $policy = self::sourceFilePathPolicy($parsed['path']);
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
    private static function parseSourceFileEntry(string $entry): array
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
     * @return list<array<string, mixed>>
     */
    private static function namesFromBibtexField(array $fields, string $field): array
    {
        $names = self::namesFromBibtex($fields[$field] ?? '');
        if ($names === []) {
            return [];
        }

        return self::withBiblatexNameAnnotations($names, $fields[$field . '+an'] ?? '');
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $fieldNames
     * @return list<array<string, mixed>>
     */
    private static function namesFromFirstBibtexField(array $fields, array $fieldNames): array
    {
        foreach ($fieldNames as $field) {
            if (!isset($fields[$field]) || trim($fields[$field]) === '') {
                continue;
            }

            return self::namesFromBibtexField($fields, $field);
        }

        return [];
    }

    /**
     * @param list<array<string, mixed>> $names
     * @return list<array<string, mixed>>
     */
    private static function withBiblatexNameAnnotations(array $names, string $value): array
    {
        foreach (self::biblatexNameAnnotations($value) as $annotation) {
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
    private static function biblatexNameAnnotations(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        $separator = str_contains($value, ';') ? ';' : ',';
        $annotations = [];
        foreach (self::splitTopLevel($value, $separator) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            if (preg_match('/^(\d+)\s*(?::\s*([A-Za-z][A-Za-z0-9_-]*))?\s*=\s*(.+)$/u', $entry, $matches) !== 1) {
                throw new \InvalidArgumentException('BibLaTeX name annotation is malformed: ' . self::cleanBibtexText($entry));
            }

            $index = (int) $matches[1];
            if ($index < 1) {
                throw new \InvalidArgumentException('BibLaTeX name annotation index must be one-based');
            }

            $value = self::cleanBibtexText($matches[3]);
            if ($value === '') {
                continue;
            }

            $part = strtolower(str_replace('_', '-', trim((string) ($matches[2] ?? ''))));
            $annotations[] = [
                'index' => $index,
                'part' => $part === '' ? 'name' : $part,
                'value' => $value,
            ];
        }

        return $annotations;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function namesFromBibtex(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        $names = [];
        foreach (self::splitBibtexNames($value) as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            $names[] = self::nameToCsl($name);
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private static function splitBibtexNames(string $value): array
    {
        $names = [];
        $buffer = '';
        $depth = 0;
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($char === '{') {
                $depth++;
                $buffer .= $char;
                continue;
            }

            if ($char === '}') {
                $depth = max(0, $depth - 1);
                $buffer .= $char;
                continue;
            }

            if ($depth === 0 && preg_match('/\G\s+and\s+/i', $value, $match, 0, $i) === 1) {
                $names[] = $buffer;
                $buffer = '';
                $i += strlen($match[0]) - 1;
                continue;
            }

            $buffer .= $char;
        }

        $names[] = $buffer;

        return $names;
    }

    /**
     * @return array<string, mixed>
     */
    private static function nameToCsl(string $name): array
    {
        $literal = self::outerBraced($name);
        if ($literal !== null) {
            return [
                'literal' => self::cleanBibtexText($literal),
            ];
        }

        $parts = self::splitTopLevel($name, ',');
        if (count($parts) >= 2) {
            [$particle, $family] = self::splitLeadingParticle(self::cleanBibtexText($parts[0]));
            $name = [
                'family' => $family,
                'given' => self::cleanBibtexText($parts[1]),
            ];

            if ($particle !== '') {
                $name['non-dropping-particle'] = $particle;
            }

            if (isset($parts[2]) && self::cleanBibtexText($parts[2]) !== '') {
                $name['suffix'] = self::cleanBibtexText($parts[2]);
                $name['comma-suffix'] = true;
            }

            return $name;
        }

        $tokens = preg_split('/\s+/', self::cleanBibtexText($name)) ?: [];
        $tokens = array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
        if ($tokens === []) {
            return ['literal' => ''];
        }

        if (count($tokens) === 1) {
            return ['family' => $tokens[0]];
        }

        $family = array_pop($tokens);
        $particle = [];
        while ($tokens !== [] && self::isParticle($tokens[count($tokens) - 1])) {
            array_unshift($particle, array_pop($tokens));
        }

        $name = [
            'family' => $family,
            'given' => implode(' ', $tokens),
        ];
        if ($particle !== []) {
            $name['non-dropping-particle'] = implode(' ', $particle);
        }

        return $name;
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $value, string $separator): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($char === '{') {
                $depth++;
                $buffer .= $char;
                continue;
            }

            if ($char === '}') {
                $depth = max(0, $depth - 1);
                $buffer .= $char;
                continue;
            }

            if ($char === $separator && $depth === 0) {
                $parts[] = $buffer;
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $parts[] = $buffer;

        return array_map('trim', $parts);
    }

    private static function outerBraced(string $value): ?string
    {
        $value = trim($value);
        if (!str_starts_with($value, '{') || !str_ends_with($value, '}')) {
            return null;
        }

        $depth = 0;
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            if ($value[$i] === '{') {
                $depth++;
            } elseif ($value[$i] === '}') {
                $depth--;
                if ($depth === 0 && $i < $length - 1) {
                    return null;
                }
            }
        }

        return $depth === 0 ? substr($value, 1, -1) : null;
    }

    /**
     * @return array{0:string, 1:string}
     */
    private static function splitLeadingParticle(string $family): array
    {
        $tokens = preg_split('/\s+/', trim($family)) ?: [];
        $tokens = array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
        if (count($tokens) < 2) {
            return ['', $family];
        }

        $particle = [];
        while (count($tokens) > 1 && self::isParticle($tokens[0])) {
            $particle[] = array_shift($tokens);
        }

        return [implode(' ', $particle), implode(' ', $tokens)];
    }

    private static function isParticle(string $token): bool
    {
        $token = trim($token, "{}~");
        $lower = strtolower($token);
        if (in_array($lower, ['da', 'de', 'del', 'della', 'der', 'di', 'dos', 'du', 'la', 'le', 'van', 'von', 'ten', 'ter'], true)) {
            return true;
        }

        return preg_match('/^[a-z][a-z\'.-]*$/', $token) === 1;
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $dateFields
     * @param list<string> $partFields
     * @return array<string, mixed>|null
     */
    private static function dateFromFields(array $fields, array $dateFields, array $partFields): ?array
    {
        foreach ($dateFields as $field) {
            if (isset($fields[$field]) && trim($fields[$field]) !== '') {
                return self::dateFromText(self::cleanBibtexText($fields[$field]), $field);
            }
        }

        if ($partFields === [] || !isset($fields[$partFields[0]]) || trim($fields[$partFields[0]]) === '') {
            return null;
        }

        $year = self::cleanBibtexText($fields[$partFields[0]]);
        if (!preg_match('/^-?\d+$/', $year)) {
            return ['literal' => $year];
        }

        $parts = [(int) $year];
        if (isset($partFields[1], $fields[$partFields[1]]) && trim($fields[$partFields[1]]) !== '') {
            $parts[] = self::monthNumber(self::cleanBibtexText($fields[$partFields[1]]), $partFields[1]);
        }

        if (isset($partFields[2], $fields[$partFields[2]]) && trim($fields[$partFields[2]]) !== '') {
            $day = self::cleanBibtexText($fields[$partFields[2]]);
            if (!preg_match('/^\d+$/', $day)) {
                throw new \InvalidArgumentException('BibTeX day field must be numeric');
            }

            $parts[] = (int) $day;
        }

        return ['date-parts' => [$parts]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function dateFromText(string $date, string $field): array
    {
        $rangeParts = self::dateRangePartsFromText($date, $field);
        if ($rangeParts !== null) {
            return ['date-parts' => $rangeParts];
        }

        if (preg_match('/^(-?\d{1,6})(?:[-\/](\d{1,2})(?:[-\/](\d{1,2}))?)?$/', $date, $matches) !== 1) {
            return ['literal' => $date];
        }

        $parts = [(int) $matches[1]];
        if (isset($matches[2]) && $matches[2] !== '') {
            $month = (int) $matches[2];
            if ($month < 1 || $month > 12) {
                throw new \InvalidArgumentException('BibTeX ' . $field . ' month must be between 1 and 12');
            }

            $parts[] = $month;
        }

        if (isset($matches[3]) && $matches[3] !== '') {
            $day = (int) $matches[3];
            if ($day < 1 || $day > 31) {
                throw new \InvalidArgumentException('BibTeX ' . $field . ' day must be between 1 and 31');
            }

            $parts[] = $day;
        }

        return ['date-parts' => [$parts]];
    }

    /**
     * @return list<list<int>>|null
     */
    private static function dateRangePartsFromText(string $date, string $field): ?array
    {
        if (substr_count($date, '/') !== 1) {
            return null;
        }

        [$start, $end] = array_map('trim', explode('/', $date, 2));
        if ($start === '' || $end === '') {
            return null;
        }

        if (!self::looksLikeDateRangeSide($start) || !self::looksLikeDateRangeSide($end)) {
            return null;
        }

        return [
            self::dateRangeSideParts($start, $field),
            self::dateRangeSideParts($end, $field),
        ];
    }

    private static function looksLikeDateRangeSide(string $value): bool
    {
        return preg_match('/^-?\d{3,6}(?:-\d{1,2}(?:-\d{1,2})?)?$/', $value) === 1;
    }

    /**
     * @return list<int>
     */
    private static function dateRangeSideParts(string $value, string $field): array
    {
        if (preg_match('/^(-?\d{1,6})(?:-(\d{1,2})(?:-(\d{1,2}))?)?$/', $value, $matches) !== 1) {
            throw new \InvalidArgumentException('BibTeX ' . $field . ' date range endpoint is malformed');
        }

        $parts = [(int) $matches[1]];
        if (isset($matches[2]) && $matches[2] !== '') {
            $month = (int) $matches[2];
            if ($month < 1 || $month > 12) {
                throw new \InvalidArgumentException('BibTeX ' . $field . ' month must be between 1 and 12');
            }

            $parts[] = $month;
        }

        if (isset($matches[3]) && $matches[3] !== '') {
            $day = (int) $matches[3];
            if ($day < 1 || $day > 31) {
                throw new \InvalidArgumentException('BibTeX ' . $field . ' day must be between 1 and 31');
            }

            $parts[] = $day;
        }

        return $parts;
    }

    private static function monthNumber(string $value, string $field): int
    {
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

        if (preg_match('/^\d+$/', $value) === 1) {
            $month = (int) $value;
        } elseif (isset($months[$lookup])) {
            $month = $months[$lookup];
        } else {
            throw new \InvalidArgumentException('BibTeX ' . $field . ' field must be a month name or number');
        }

        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException('BibTeX ' . $field . ' month must be between 1 and 12');
        }

        return $month;
    }

    private static function normalizePages(string $pages): string
    {
        return trim(preg_replace('/\s*--+\s*/', '-', $pages) ?? $pages);
    }

    private static function cleanBibtexText(string $value): string
    {
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);
        $value = self::decodeLatexText($value);
        $value = str_replace('~', ' ', $value);
        $value = preg_replace('/\\\\([&%$#_{}])/', '$1', $value) ?? $value;
        $value = preg_replace('/\\\\(?:emph|textit|textbf|enquote)\s*\{([^{}]*)\}/', '$1', $value) ?? $value;
        $value = preg_replace('/\\\\(?:textendash|textminus)\b/', '-', $value) ?? $value;
        $value = preg_replace('/[{}]/', '', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private static function decodeLatexText(string $value): string
    {
        $value = self::decodeLatexAccentCommands($value);

        return self::decodeLatexSpecialLetters($value);
    }

    private static function decodeLatexAccentCommands(string $value): string
    {
        $patterns = [
            '/\{\\\\([`\\\'"^~=\.uvHrcbk])\s*\{?\s*([A-Za-z])\s*\}?\}/u',
            '/\\\\([`\\\'"^~=\.uvHrcbk])\s*\{\s*([A-Za-z])\s*\}/u',
            '/\\\\([`\\\'"^~=\.])\s*([A-Za-z])/u',
            '/\\\\([uvHrcbk])\s+([A-Za-z])/u',
        ];

        foreach ($patterns as $pattern) {
            $value = preg_replace_callback(
                $pattern,
                static fn (array $matches): string => self::accentedLatinLetter($matches[1], $matches[2], $matches[0]),
                $value
            ) ?? $value;
        }

        return $value;
    }

    private static function accentedLatinLetter(string $accent, string $letter, string $original): string
    {
        $map = self::latexAccentMap();

        return $map[$accent][$letter] ?? $original;
    }

    private static function decodeLatexSpecialLetters(string $value): string
    {
        $map = self::latexSpecialLetterMap();
        $macros = array_keys($map);
        usort($macros, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $alternation = implode('|', array_map(static fn (string $macro): string => preg_quote($macro, '/'), $macros));
        $pattern = '/\{\\\\(' . $alternation . ')\}|\\\\(' . $alternation . ')(?![A-Za-z])/u';

        return preg_replace_callback(
            $pattern,
            static function (array $matches) use ($map): string {
                $macro = ($matches[1] ?? '') !== '' ? $matches[1] : ($matches[2] ?? '');

                return $map[$macro] ?? $matches[0];
            },
            $value
        ) ?? $value;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function latexAccentMap(): array
    {
        return [
            '\'' => [
                'A' => "\u{00C1}", 'C' => "\u{0106}", 'E' => "\u{00C9}", 'I' => "\u{00CD}", 'L' => "\u{0139}", 'N' => "\u{0143}", 'O' => "\u{00D3}", 'R' => "\u{0154}", 'S' => "\u{015A}", 'U' => "\u{00DA}", 'Y' => "\u{00DD}", 'Z' => "\u{0179}",
                'a' => "\u{00E1}", 'c' => "\u{0107}", 'e' => "\u{00E9}", 'i' => "\u{00ED}", 'l' => "\u{013A}", 'n' => "\u{0144}", 'o' => "\u{00F3}", 'r' => "\u{0155}", 's' => "\u{015B}", 'u' => "\u{00FA}", 'y' => "\u{00FD}", 'z' => "\u{017A}",
            ],
            '`' => [
                'A' => "\u{00C0}", 'E' => "\u{00C8}", 'I' => "\u{00CC}", 'O' => "\u{00D2}", 'U' => "\u{00D9}",
                'a' => "\u{00E0}", 'e' => "\u{00E8}", 'i' => "\u{00EC}", 'o' => "\u{00F2}", 'u' => "\u{00F9}",
            ],
            '^' => [
                'A' => "\u{00C2}", 'C' => "\u{0108}", 'E' => "\u{00CA}", 'G' => "\u{011C}", 'H' => "\u{0124}", 'I' => "\u{00CE}", 'J' => "\u{0134}", 'O' => "\u{00D4}", 'S' => "\u{015C}", 'U' => "\u{00DB}", 'W' => "\u{0174}", 'Y' => "\u{0176}",
                'a' => "\u{00E2}", 'c' => "\u{0109}", 'e' => "\u{00EA}", 'g' => "\u{011D}", 'h' => "\u{0125}", 'i' => "\u{00EE}", 'j' => "\u{0135}", 'o' => "\u{00F4}", 's' => "\u{015D}", 'u' => "\u{00FB}", 'w' => "\u{0175}", 'y' => "\u{0177}",
            ],
            '"' => [
                'A' => "\u{00C4}", 'E' => "\u{00CB}", 'I' => "\u{00CF}", 'O' => "\u{00D6}", 'U' => "\u{00DC}", 'Y' => "\u{0178}",
                'a' => "\u{00E4}", 'e' => "\u{00EB}", 'i' => "\u{00EF}", 'o' => "\u{00F6}", 'u' => "\u{00FC}", 'y' => "\u{00FF}",
            ],
            '~' => [
                'A' => "\u{00C3}", 'N' => "\u{00D1}", 'O' => "\u{00D5}",
                'a' => "\u{00E3}", 'n' => "\u{00F1}", 'o' => "\u{00F5}",
            ],
            '=' => [
                'A' => "\u{0100}", 'E' => "\u{0112}", 'I' => "\u{012A}", 'O' => "\u{014C}", 'U' => "\u{016A}",
                'a' => "\u{0101}", 'e' => "\u{0113}", 'i' => "\u{012B}", 'o' => "\u{014D}", 'u' => "\u{016B}",
            ],
            '.' => [
                'C' => "\u{010A}", 'E' => "\u{0116}", 'G' => "\u{0120}", 'I' => "\u{0130}", 'Z' => "\u{017B}",
                'c' => "\u{010B}", 'e' => "\u{0117}", 'g' => "\u{0121}", 'z' => "\u{017C}",
            ],
            'u' => [
                'A' => "\u{0102}", 'E' => "\u{0114}", 'G' => "\u{011E}", 'I' => "\u{012C}", 'O' => "\u{014E}", 'U' => "\u{016C}",
                'a' => "\u{0103}", 'e' => "\u{0115}", 'g' => "\u{011F}", 'i' => "\u{012D}", 'o' => "\u{014F}", 'u' => "\u{016D}",
            ],
            'v' => [
                'C' => "\u{010C}", 'D' => "\u{010E}", 'E' => "\u{011A}", 'L' => "\u{013D}", 'N' => "\u{0147}", 'R' => "\u{0158}", 'S' => "\u{0160}", 'T' => "\u{0164}", 'Z' => "\u{017D}",
                'c' => "\u{010D}", 'd' => "\u{010F}", 'e' => "\u{011B}", 'l' => "\u{013E}", 'n' => "\u{0148}", 'r' => "\u{0159}", 's' => "\u{0161}", 't' => "\u{0165}", 'z' => "\u{017E}",
            ],
            'H' => [
                'O' => "\u{0150}", 'U' => "\u{0170}",
                'o' => "\u{0151}", 'u' => "\u{0171}",
            ],
            'r' => [
                'A' => "\u{00C5}", 'U' => "\u{016E}",
                'a' => "\u{00E5}", 'u' => "\u{016F}",
            ],
            'c' => [
                'C' => "\u{00C7}", 'G' => "\u{0122}", 'K' => "\u{0136}", 'L' => "\u{013B}", 'N' => "\u{0145}", 'R' => "\u{0156}", 'S' => "\u{015E}", 'T' => "\u{0162}",
                'c' => "\u{00E7}", 'g' => "\u{0123}", 'k' => "\u{0137}", 'l' => "\u{013C}", 'n' => "\u{0146}", 'r' => "\u{0157}", 's' => "\u{015F}", 't' => "\u{0163}",
            ],
            'k' => [
                'A' => "\u{0104}", 'E' => "\u{0118}", 'I' => "\u{012E}", 'U' => "\u{0172}",
                'a' => "\u{0105}", 'e' => "\u{0119}", 'i' => "\u{012F}", 'u' => "\u{0173}",
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function latexSpecialLetterMap(): array
    {
        return [
            'AE' => "\u{00C6}",
            'ae' => "\u{00E6}",
            'OE' => "\u{0152}",
            'oe' => "\u{0153}",
            'AA' => "\u{00C5}",
            'aa' => "\u{00E5}",
            'O' => "\u{00D8}",
            'o' => "\u{00F8}",
            'L' => "\u{0141}",
            'l' => "\u{0142}",
            'SS' => 'SS',
            'ss' => "\u{00DF}",
            'DH' => "\u{00D0}",
            'dh' => "\u{00F0}",
            'TH' => "\u{00DE}",
            'th' => "\u{00FE}",
            'NG' => "\u{014A}",
            'ng' => "\u{014B}",
            'i' => "\u{0131}",
            'j' => "\u{0237}",
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function standardStrings(): array
    {
        return [
            'jan' => 'January',
            'feb' => 'February',
            'mar' => 'March',
            'apr' => 'April',
            'may' => 'May',
            'jun' => 'June',
            'jul' => 'July',
            'aug' => 'August',
            'sep' => 'September',
            'oct' => 'October',
            'nov' => 'November',
            'dec' => 'December',
        ];
    }
}
