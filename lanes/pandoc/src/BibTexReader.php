<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class BibTexReader
{
    /** @var array<string, string> */
    private const MONTH_MACROS = [
        'jan' => 'January',
        'feb' => 'February',
        'mar' => 'March',
        'apr' => 'April',
        'may' => 'May',
        'jun' => 'June',
        'jul' => 'July',
        'aug' => 'August',
        'sep' => 'September',
        'sept' => 'September',
        'oct' => 'October',
        'nov' => 'November',
        'dec' => 'December',
    ];

    /** @var array<string, string> */
    private const SIMPLE_LATEX_COMMANDS = [
        '\\LaTeX' => 'LaTeX',
        '\\TeX' => 'TeX',
        '\\BibTeX' => 'BibTeX',
        '\\&' => '&',
        '\\%' => '%',
        '\\$' => '$',
        '\\#' => '#',
        '\\_' => '_',
        '\\{' => '{',
        '\\}' => '}',
        '\\textbackslash' => '\\',
        '\\textbar' => '|',
        '\\textasciitilde' => '~',
        '\\textasciicircum' => '^',
        '\\textendash' => '--',
        '\\textemdash' => '---',
        '\\dots' => '...',
        '\\ldots' => '...',
        '\\ae' => 'ae',
        '\\AE' => 'AE',
        '\\oe' => 'oe',
        '\\OE' => 'OE',
        '\\aa' => 'aa',
        '\\AA' => 'AA',
        '\\o' => 'o',
        '\\O' => 'O',
        '\\l' => 'l',
        '\\L' => 'L',
        '\\ss' => 'ss',
    ];

    /** @var list<string> */
    private const LATEX_UNWRAP_COMMANDS = [
        'emph',
        'enquote',
        'lowercase',
        'MakeLowercase',
        'MakeUppercase',
        'mbox',
        'mkbibbold',
        'mkbibbrackets',
        'mkbibemph',
        'mkbibitalic',
        'mkbibparens',
        'mkbibquote',
        'sout',
        'textbf',
        'textit',
        'textnormal',
        'textrm',
        'textsc',
        'textsf',
        'textsl',
        'texttt',
        'underline',
        'uppercase',
    ];

    public function __construct(private readonly string $variant = 'bibtex')
    {
    }

    public function read(string $source): AstNode
    {
        $packet = $this->parseBibliography($source);
        $entries = array_values(array_filter(
            $packet['entries'],
            fn (array $entry): bool => !$this->isDataOnlyEntry($entry)
        ));
        $metadata = [
            'references' => $this->metaList(array_map(fn (array $entry): array => $this->referenceMeta($entry), $entries)),
            'nocite' => [
                'type' => 'MetaInlines',
                'value' => [
                    new AstNode('citation', [
                        'citations' => [[
                            'id' => '*',
                            'mode' => 'normal',
                            'noteNum' => 0,
                            'hash' => 0,
                        ]],
                        'text' => '[@*]',
                    ], [new AstNode('text', ['text' => '[@*]'])]),
                ],
            ],
            'bibtexVariant' => $this->variant,
            'bibtexEntryCount' => count($entries),
            'bibtexStringCount' => count($packet['strings']),
            'bibtexPreambleCount' => count($packet['preambles']),
            'bibtexCommentCount' => $packet['comments'],
            'bibtexDataEntryCount' => count($packet['entries']) - count($entries),
        ];

        if ($packet['preambles'] !== []) {
            $metadata['bibtexPreambles'] = $this->metaList(array_values($packet['preambles']));
        }

        return new AstNode('document', ['meta' => $metadata], $this->bibliographyBlocks($entries));
    }

    public function readBibTexFile(string $path): AstNode
    {
        $source = file_get_contents($path);
        if (!is_string($source)) {
            throw new \RuntimeException("Unable to read '{$path}'.");
        }

        return $this->read($source);
    }

    /**
     * @return array{
     *     entries:list<array{type:string,key:string,fields:array<string,string>,rawFields:array<string,string>}>,
     *     strings:array<string,string>,
     *     preambles:list<string>,
     *     comments:int
     * }
     */
    private function parseBibliography(string $source): array
    {
        $source = preg_replace('/^\xEF\xBB\xBF/', '', $source) ?? $source;
        $offset = 0;
        $length = strlen($source);
        $strings = self::MONTH_MACROS;
        $entries = [];
        $preambles = [];
        $comments = 0;

        while (($at = strpos($source, '@', $offset)) !== false) {
            $offset = $at + 1;
            $this->skipWhitespaceAndLineComments($source, $offset);
            $type = strtolower($this->readIdentifier($source, $offset));
            if ($type === '') {
                continue;
            }

            $this->skipWhitespaceAndLineComments($source, $offset);
            if ($offset >= $length || !in_array($source[$offset], ['{', '('], true)) {
                continue;
            }

            $open = $source[$offset];
            $close = $open === '{' ? '}' : ')';
            $offset++;

            if ($type === 'comment') {
                $this->skipEntryBody($source, $offset, $close);
                $comments++;
                continue;
            }

            if ($type === 'preamble') {
                $preamble = $this->readValueSequence($source, $offset, $close, $strings);
                if ($preamble !== '') {
                    $preambles[] = $this->cleanText($preamble);
                }
                $this->skipUntilEntryClose($source, $offset, $close);
                continue;
            }

            if ($type === 'string') {
                $this->readStringMacro($source, $offset, $close, $strings);
                continue;
            }

            $entry = $this->readBibliographyEntry($source, $offset, $close, $type, $strings);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }
        $entries = $this->resolveEntryInheritance($entries);

        return [
            'entries' => $entries,
            'strings' => $strings,
            'preambles' => $preambles,
            'comments' => $comments,
        ];
    }

    /**
     * @param list<array{type:string,key:string,fields:array<string,string>,rawFields:array<string,string>}> $entries
     * @return list<array{type:string,key:string,fields:array<string,string>,rawFields:array<string,string>}>
     */
    private function resolveEntryInheritance(array $entries): array
    {
        $byKey = [];
        foreach ($entries as $entry) {
            $byKey[strtolower($entry['key'])] = $entry;
        }

        foreach ($entries as $index => $entry) {
            foreach (['crossref', 'xdata'] as $field) {
                $targets = $this->inheritanceTargets($entry['fields'][$field] ?? '');
                foreach ($targets as $target) {
                    $parent = $byKey[strtolower($target)] ?? null;
                    if (!is_array($parent)) {
                        continue;
                    }
                    foreach ($parent['rawFields'] as $name => $value) {
                        if (in_array($name, ['crossref', 'xdata'], true) || isset($entries[$index]['rawFields'][$name])) {
                            continue;
                        }
                        $entries[$index]['rawFields'][$name] = $value;
                        $entries[$index]['fields'][$name] = $parent['fields'][$name] ?? $this->cleanText($value);
                    }
                    if (
                        $field === 'crossref'
                        && in_array($parent['type'], ['proceedings', 'collection'], true)
                        && !isset($entries[$index]['rawFields']['booktitle'])
                        && isset($parent['rawFields']['title'])
                    ) {
                        $entries[$index]['rawFields']['booktitle'] = $parent['rawFields']['title'];
                        $entries[$index]['fields']['booktitle'] = $parent['fields']['title'] ?? $this->cleanText($parent['rawFields']['title']);
                    }
                }
            }
        }

        return $entries;
    }

    /**
     * @param array{type:string,key:string,fields:array<string,string>,rawFields:array<string,string>} $entry
     */
    private function isDataOnlyEntry(array $entry): bool
    {
        return in_array($entry['type'], ['xdata', 'set'], true);
    }

    /**
     * @return list<string>
     */
    private function inheritanceTargets(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', preg_split('/\s*,\s*/u', $value) ?: []),
            static fn (string $target): bool => $target !== ''
        ));
    }

    /**
     * @param array<string, string> $strings
     */
    private function readStringMacro(string $source, int &$offset, string $close, array &$strings): void
    {
        $this->skipWhitespaceAndLineComments($source, $offset);
        $name = strtolower($this->readIdentifier($source, $offset));
        $this->skipWhitespaceAndLineComments($source, $offset);
        if ($name === '' || $offset >= strlen($source) || $source[$offset] !== '=') {
            $this->skipEntryBody($source, $offset, $close);
            return;
        }

        $offset++;
        $value = $this->readValueSequence($source, $offset, $close, $strings);
        $strings[$name] = $this->cleanText($value);
        $this->skipUntilEntryClose($source, $offset, $close);
    }

    /**
     * @param array<string, string> $strings
     * @return array{type:string,key:string,fields:array<string,string>,rawFields:array<string,string>}|null
     */
    private function readBibliographyEntry(string $source, int &$offset, string $close, string $type, array $strings): ?array
    {
        $this->skipWhitespaceAndLineComments($source, $offset);
        $key = trim($this->readUntilTopLevel($source, $offset, [',', $close]));
        if ($offset < strlen($source) && $source[$offset] === ',') {
            $offset++;
        }
        if ($key === '') {
            $this->skipEntryBody($source, $offset, $close);
            return null;
        }

        $rawFields = [];
        while ($offset < strlen($source)) {
            $this->skipWhitespaceAndLineComments($source, $offset);
            if ($offset >= strlen($source)) {
                break;
            }
            if ($source[$offset] === $close) {
                $offset++;
                break;
            }
            if ($source[$offset] === ',') {
                $offset++;
                continue;
            }

            $field = strtolower($this->readIdentifier($source, $offset));
            if ($field === '') {
                $this->skipUntilEntryClose($source, $offset, $close);
                break;
            }

            $this->skipWhitespaceAndLineComments($source, $offset);
            if ($offset >= strlen($source) || $source[$offset] !== '=') {
                $this->skipUntilEntryClose($source, $offset, $close);
                break;
            }
            $offset++;

            $rawFields[$field] = $this->readValueSequence($source, $offset, $close, $strings);
            $this->skipWhitespaceAndLineComments($source, $offset);
            if ($offset < strlen($source) && $source[$offset] === ',') {
                $offset++;
            }
        }

        $fields = [];
        foreach ($rawFields as $name => $value) {
            $fields[$name] = $this->cleanText($value);
        }

        return [
            'type' => $type,
            'key' => $key,
            'fields' => $fields,
            'rawFields' => $rawFields,
        ];
    }

    /**
     * @param list<string> $terminators
     */
    private function readUntilTopLevel(string $source, int &$offset, array $terminators): string
    {
        $start = $offset;
        while ($offset < strlen($source) && !in_array($source[$offset], $terminators, true)) {
            $offset++;
        }

        return substr($source, $start, $offset - $start);
    }

    /**
     * @param array<string, string> $strings
     */
    private function readValueSequence(string $source, int &$offset, string $close, array $strings): string
    {
        $parts = [];
        while ($offset < strlen($source)) {
            $this->skipWhitespaceAndLineComments($source, $offset);
            if ($offset >= strlen($source) || $source[$offset] === ',' || $source[$offset] === $close) {
                break;
            }

            $parts[] = $this->readValueAtom($source, $offset, $close, $strings);
            $this->skipWhitespaceAndLineComments($source, $offset);
            if ($offset < strlen($source) && $source[$offset] === '#') {
                $offset++;
                continue;
            }
            break;
        }

        return implode('', $parts);
    }

    /**
     * @param array<string, string> $strings
     */
    private function readValueAtom(string $source, int &$offset, string $close, array $strings): string
    {
        if ($offset >= strlen($source)) {
            return '';
        }

        if ($source[$offset] === '{') {
            return $this->readBracedValue($source, $offset);
        }

        if ($source[$offset] === '"') {
            return $this->readQuotedValue($source, $offset);
        }

        $start = $offset;
        while (
            $offset < strlen($source)
            && !preg_match('/\s/', $source[$offset])
            && !in_array($source[$offset], [',', '#', $close], true)
        ) {
            $offset++;
        }

        $token = trim(substr($source, $start, $offset - $start));
        $lookup = strtolower($token);

        return $strings[$lookup] ?? $token;
    }

    private function readBracedValue(string $source, int &$offset): string
    {
        $offset++;
        $depth = 1;
        $value = '';
        while ($offset < strlen($source)) {
            $char = $source[$offset];
            if ($char === '\\' && $offset + 1 < strlen($source) && in_array($source[$offset + 1], ['{', '}'], true)) {
                $value .= $char . $source[$offset + 1];
                $offset += 2;
                continue;
            }
            if ($char === '{') {
                $depth++;
                if ($depth > 1) {
                    $value .= $char;
                }
                $offset++;
                continue;
            }
            if ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    $offset++;
                    return $value;
                }
                $value .= $char;
                $offset++;
                continue;
            }

            $value .= $char;
            $offset++;
        }

        return $value;
    }

    private function readQuotedValue(string $source, int &$offset): string
    {
        $offset++;
        $depth = 0;
        $value = '';
        while ($offset < strlen($source)) {
            $char = $source[$offset];
            if ($char === '\\' && $offset + 1 < strlen($source)) {
                $value .= $char . $source[$offset + 1];
                $offset += 2;
                continue;
            }
            if ($char === '{') {
                $depth++;
                $value .= $char;
                $offset++;
                continue;
            }
            if ($char === '}') {
                $depth = max(0, $depth - 1);
                $value .= $char;
                $offset++;
                continue;
            }
            if ($char === '"' && $depth === 0) {
                $offset++;
                return $value;
            }

            $value .= $char;
            $offset++;
        }

        return $value;
    }

    private function skipEntryBody(string $source, int &$offset, string $close): void
    {
        $this->skipUntilEntryClose($source, $offset, $close);
    }

    private function skipUntilEntryClose(string $source, int &$offset, string $close): void
    {
        $depth = 0;
        $quote = false;
        while ($offset < strlen($source)) {
            $char = $source[$offset];
            if ($char === '\\') {
                $offset += 2;
                continue;
            }
            if ($char === '"' && $depth === 0) {
                $quote = !$quote;
                $offset++;
                continue;
            }
            if (!$quote && $char === '{') {
                $depth++;
                $offset++;
                continue;
            }
            if (!$quote && $char === '}') {
                if ($close === '}' && $depth === 0) {
                    $offset++;
                    return;
                }
                $depth = max(0, $depth - 1);
                $offset++;
                continue;
            }
            if (!$quote && $char === ')' && $close === ')' && $depth === 0) {
                $offset++;
                return;
            }
            $offset++;
        }
    }

    private function skipWhitespaceAndLineComments(string $source, int &$offset): void
    {
        while ($offset < strlen($source)) {
            if (preg_match('/\s/', $source[$offset]) === 1) {
                $offset++;
                continue;
            }
            if ($source[$offset] === '%') {
                while ($offset < strlen($source) && !in_array($source[$offset], ["\n", "\r"], true)) {
                    $offset++;
                }
                continue;
            }
            break;
        }
    }

    private function readIdentifier(string $source, int &$offset): string
    {
        $start = $offset;
        while ($offset < strlen($source) && preg_match('/[A-Za-z0-9_.:-]/', $source[$offset]) === 1) {
            $offset++;
        }

        return substr($source, $start, $offset - $start);
    }

    /**
     * @param array{type:string,key:string,fields:array<string,string>,rawFields:array<string,string>} $entry
     */
    private function referenceMeta(array $entry): array
    {
        $fields = $entry['fields'];
        $reference = [
            'id' => $entry['key'],
            'type' => $this->cslType($entry['type']),
            'bibtex-type' => $entry['type'],
        ];

        $title = $this->combinedTitle($fields);
        if ($title !== '') {
            $reference['title'] = $title;
        }

        foreach ([
            'journal' => 'container-title',
            'journaltitle' => 'container-title',
            'booktitle' => 'container-title',
            'publisher' => 'publisher',
            'organization' => 'publisher',
            'institution' => 'publisher',
            'school' => 'publisher',
            'location' => 'publisher-place',
            'address' => 'publisher-place',
            'volume' => 'volume',
            'number' => 'issue',
            'issue' => 'issue',
            'pages' => 'page',
            'doi' => 'DOI',
            'url' => 'URL',
            'isbn' => 'ISBN',
            'issn' => 'ISSN',
            'note' => 'note',
            'abstract' => 'abstract',
            'series' => 'collection-title',
            'edition' => 'edition',
            'chapter' => 'chapter-number',
            'type' => 'genre',
            'entrysubtype' => 'genre',
            'howpublished' => 'medium',
            'language' => 'language',
            'langid' => 'language',
            'keywords' => 'keyword',
            'pagetotal' => 'number-of-pages',
            'eventtitle' => 'event-title',
            'venue' => 'event-place',
        ] as $field => $target) {
            if (($fields[$field] ?? '') !== '' && !isset($reference[$target])) {
                $reference[$target] = $fields[$field];
            }
        }

        $year = $this->entryYear($fields);
        if ($year !== '') {
            $reference['year'] = $year;
            $reference['issued'] = $this->datePartsMeta($year);
        }
        if (($fields['date'] ?? '') !== '') {
            $reference['date'] = $fields['date'];
            $datePartLists = $this->datePartListsFromDate($fields['date']);
            if ($datePartLists !== []) {
                $reference['issued'] = $this->datePartsMeta($datePartLists);
            }
        }
        foreach ([
            'urldate' => 'accessed',
            'origdate' => 'original-date',
            'eventdate' => 'event-date',
        ] as $field => $target) {
            if (($fields[$field] ?? '') === '') {
                continue;
            }
            $datePartLists = $this->datePartListsFromDate($fields[$field]);
            if ($datePartLists !== []) {
                $reference[$target] = $this->datePartsMeta($datePartLists);
            }
        }

        foreach (['author', 'editor', 'translator'] as $nameField) {
            if (($entry['rawFields'][$nameField] ?? '') === '') {
                continue;
            }
            $names = $this->parseNames($entry['rawFields'][$nameField]);
            if ($names !== []) {
                $reference[$nameField] = $this->metaList(array_map(fn (array $name): array => $this->personNameMeta($name), $names));
            }
        }

        $fieldMeta = [];
        ksort($fields);
        foreach ($fields as $field => $value) {
            $fieldMeta[$field] = $value;
        }
        $reference['bibtex-fields'] = $this->metaMap($fieldMeta);

        return $this->metaMap($reference);
    }

    /**
     * @param array<string, string> $fields
     */
    private function combinedTitle(array $fields): string
    {
        $title = trim((string) ($fields['title'] ?? ''));
        $subtitle = trim((string) ($fields['subtitle'] ?? ''));
        if ($title === '') {
            return $subtitle;
        }
        if ($subtitle === '') {
            return $title;
        }

        return $title . ': ' . $subtitle;
    }

    /**
     * @param array<string, string> $fields
     */
    private function entryYear(array $fields): string
    {
        foreach (['year', 'date'] as $field) {
            $value = (string) ($fields[$field] ?? '');
            if (preg_match('/\b([12][0-9]{3})\b/', $value, $match) === 1) {
                return $match[1];
            }
        }

        return '';
    }

    /**
     * @return list<AstNode>
     */
    private function bibliographyBlocks(array $entries): array
    {
        if ($entries === []) {
            return [
                new AstNode('paragraph', ['text' => 'No BibTeX entries were found.'], [
                    new AstNode('text', ['text' => 'No BibTeX entries were found.']),
                ]),
            ];
        }

        return [
            new AstNode('div', [
                'id' => 'refs',
                'classes' => ['csl-bib-body'],
                'attributes' => [
                    'data-pandoc-source' => $this->variant,
                    'data-bibtex-entry-count' => (string) count($entries),
                ],
            ], array_map(fn (array $entry): AstNode => $this->entryBlock($entry), $entries)),
        ];
    }

    /**
     * @param array{type:string,key:string,fields:array<string,string>,rawFields:array<string,string>} $entry
     */
    private function entryBlock(array $entry): AstNode
    {
        return new AstNode('div', [
            'id' => 'ref-' . $this->htmlId($entry['key']),
            'classes' => ['csl-entry'],
            'attributes' => [
                'data-bibtex-key' => $entry['key'],
                'data-bibtex-type' => $entry['type'],
            ],
        ], [
            new AstNode('paragraph', [], $this->entrySummaryInlines($entry)),
        ]);
    }

    /**
     * @param array{type:string,key:string,fields:array<string,string>,rawFields:array<string,string>} $entry
     * @return list<AstNode>
     */
    private function entrySummaryInlines(array $entry): array
    {
        $fields = $entry['fields'];
        $nodes = [];
        $authors = $this->displayNames($entry['rawFields']['author'] ?? '');
        if ($authors === '' && ($entry['rawFields']['editor'] ?? '') !== '') {
            $authors = $this->displayNames($entry['rawFields']['editor']) . ' (ed.)';
        }
        if ($authors !== '') {
            $nodes[] = new AstNode('text', ['text' => rtrim($authors, '.') . '. ']);
        }

        $year = $this->entryYear($fields);
        if ($year !== '') {
            $nodes[] = new AstNode('text', ['text' => '(' . $year . '). ']);
        }

        $title = $this->combinedTitle($fields);
        if ($title !== '') {
            $titleNode = new AstNode('text', ['text' => $title]);
            $nodes[] = in_array($entry['type'], ['book', 'booklet', 'manual', 'proceedings', 'collection'], true)
                ? new AstNode('emph', [], [$titleNode])
                : $titleNode;
            $nodes[] = new AstNode('text', ['text' => '. ']);
        }

        $container = $fields['journaltitle'] ?? $fields['journal'] ?? $fields['booktitle'] ?? '';
        if ($container !== '') {
            $nodes[] = new AstNode('emph', [], [new AstNode('text', ['text' => $container])]);
            $suffix = '';
            if (($fields['volume'] ?? '') !== '') {
                $suffix .= ' ' . $fields['volume'];
            }
            if (($fields['number'] ?? '') !== '') {
                $suffix .= '(' . $fields['number'] . ')';
            }
            if (($fields['pages'] ?? '') !== '') {
                $suffix .= ': ' . $fields['pages'];
            }
            $nodes[] = new AstNode('text', ['text' => $suffix . '. ']);
        }

        $publisher = $fields['publisher'] ?? $fields['organization'] ?? $fields['institution'] ?? $fields['school'] ?? '';
        if ($publisher !== '') {
            $place = $fields['location'] ?? $fields['address'] ?? '';
            $nodes[] = new AstNode('text', ['text' => ($place !== '' ? $place . ': ' : '') . $publisher . '. ']);
        }

        if (($fields['doi'] ?? '') !== '') {
            $doi = $fields['doi'];
            $nodes[] = new AstNode('text', ['text' => 'doi: ']);
            $nodes[] = new AstNode('link', ['url' => 'https://doi.org/' . ltrim($doi, '/')], [
                new AstNode('text', ['text' => $doi]),
            ]);
            $nodes[] = new AstNode('text', ['text' => '. ']);
        } elseif (($fields['url'] ?? '') !== '') {
            $url = $fields['url'];
            $nodes[] = new AstNode('text', ['text' => 'Available at ']);
            $nodes[] = new AstNode('link', ['url' => $url], [new AstNode('text', ['text' => $url])]);
            $nodes[] = new AstNode('text', ['text' => '. ']);
        }

        if (($fields['note'] ?? '') !== '') {
            $nodes[] = new AstNode('text', ['text' => $fields['note']]);
        }

        return $nodes === [] ? [new AstNode('text', ['text' => $entry['key']])] : $nodes;
    }

    private function htmlId(string $key): string
    {
        $id = preg_replace('/[^\p{L}\p{N}_.:-]+/u', '-', $key) ?? $key;
        $id = trim($id, '-');

        return $id === '' ? substr(sha1($key), 0, 12) : $id;
    }

    private function cslType(string $type): string
    {
        return match ($type) {
            'article', 'article-journal' => 'article-journal',
            'inproceedings', 'conference' => 'paper-conference',
            'incollection', 'inbook', 'inreference', 'bookinbook' => 'chapter',
            'book', 'collection', 'reference', 'mvbook', 'mvcollection', 'mvreference' => 'book',
            'booklet', 'manual' => 'pamphlet',
            'mastersthesis', 'phdthesis', 'thesis' => 'thesis',
            'techreport', 'report' => 'report',
            'online', 'electronic', 'www' => 'webpage',
            'proceedings' => 'paper-conference',
            'misc' => 'entry',
            'software' => 'software',
            'dataset' => 'dataset',
            'patent' => 'patent',
            'unpublished' => 'manuscript',
            default => 'entry',
        };
    }

    /**
     * @param string|list<int|string>|list<list<int|string>> $parts
     * @return array{type:string,value:array<string, mixed>}
     */
    private function datePartsMeta(string|array $parts): array
    {
        $dateParts = [];
        if (is_array($parts) && isset($parts[0]) && is_array($parts[0])) {
            foreach ($parts as $partList) {
                $dateParts[] = $this->metaList($partList);
            }
        } else {
            $dateParts[] = $this->metaList(is_array($parts) ? $parts : [$parts]);
        }

        return $this->metaMap([
            'date-parts' => $this->metaList($dateParts),
        ]);
    }

    /**
     * @return list<list<int|string>>
     */
    private function datePartListsFromDate(string $date): array
    {
        $segments = preg_split('/\s*\/\s*/u', trim($date));
        if ($segments === false) {
            return [];
        }

        $datePartLists = [];
        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }
            $dateParts = $this->datePartsFromDate($segment);
            if ($dateParts !== []) {
                $datePartLists[] = $dateParts;
            }
        }

        return $datePartLists;
    }

    /**
     * @return list<int|string>
     */
    private function datePartsFromDate(string $date): array
    {
        if (preg_match('/^\s*([12][0-9]{3})(?:-(\d{1,2})(?:-(\d{1,2}))?)?/u', $date, $match) !== 1) {
            return [];
        }

        $parts = [(int) $match[1]];
        if (isset($match[2]) && $match[2] !== '') {
            $parts[] = max(1, min(12, (int) $match[2]));
        }
        if (isset($match[3]) && $match[3] !== '') {
            $parts[] = max(1, min(31, (int) $match[3]));
        }

        return $parts;
    }

    /**
     * @param array{literal?:string,given?:string,family?:string,suffix?:string,non-dropping-particle?:string} $name
     */
    private function personNameMeta(array $name): array
    {
        if (($name['literal'] ?? '') !== '') {
            return $this->metaMap(['literal' => $name['literal']]);
        }

        $meta = [];
        if (($name['given'] ?? '') !== '') {
            $meta['given'] = $name['given'];
        }
        if (($name['family'] ?? '') !== '') {
            $meta['family'] = $name['family'];
        }
        if (($name['suffix'] ?? '') !== '') {
            $meta['suffix'] = $name['suffix'];
        }
        if (($name['non-dropping-particle'] ?? '') !== '') {
            $meta['non-dropping-particle'] = $name['non-dropping-particle'];
        }

        return $this->metaMap($meta);
    }

    /**
     * @return list<array{literal?:string,given?:string,family?:string,suffix?:string,non-dropping-particle?:string}>
     */
    private function parseNames(string $raw): array
    {
        $names = [];
        foreach ($this->splitNames($raw) as $name) {
            $parsed = $this->parseName($name);
            if ($parsed !== []) {
                $names[] = $parsed;
            }
        }

        return $names;
    }

    private function displayNames(string $raw): string
    {
        if (trim($raw) === '') {
            return '';
        }

        $names = [];
        foreach ($this->parseNames($raw) as $name) {
            if (($name['literal'] ?? '') !== '') {
                $names[] = $name['literal'];
            } else {
                $family = trim((string) ($name['non-dropping-particle'] ?? '') . ' ' . (string) ($name['family'] ?? ''));
                $display = trim((string) ($name['given'] ?? '') . ' ' . $family);
                if (($name['suffix'] ?? '') !== '') {
                    $display .= ', ' . $name['suffix'];
                }
                $names[] = $display;
            }
        }

        if (count($names) <= 1) {
            return $names[0] ?? '';
        }

        return implode(', ', array_slice($names, 0, -1)) . ' and ' . $names[array_key_last($names)];
    }

    /**
     * @return list<string>
     */
    private function splitNames(string $raw): array
    {
        $parts = [];
        $start = 0;
        $depth = 0;
        $length = strlen($raw);
        for ($i = 0; $i < $length; $i++) {
            $char = $raw[$i];
            if ($char === '\\') {
                $i++;
                continue;
            }
            if ($char === '{') {
                $depth++;
                continue;
            }
            if ($char === '}') {
                $depth = max(0, $depth - 1);
                continue;
            }
            if (
                $depth === 0
                && stripos(substr($raw, $i, 5), ' and ') === 0
            ) {
                $parts[] = trim(substr($raw, $start, $i - $start));
                $i += 4;
                $start = $i + 1;
            }
        }
        $parts[] = trim(substr($raw, $start));

        return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    /**
     * @return array{literal?:string,given?:string,family?:string,suffix?:string,non-dropping-particle?:string}
     */
    private function parseName(string $raw): array
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return [];
        }

        if ($this->isProtectedLiteralName($trimmed)) {
            return ['literal' => $this->cleanText($trimmed)];
        }

        $parts = array_map(fn (string $part): string => $this->cleanText($part), explode(',', $trimmed));
        $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
        if (count($parts) >= 2) {
            $familyParts = $this->familyParticles($parts[0]);
            $given = count($parts) >= 3 ? $parts[2] : $parts[1];
            $suffix = count($parts) >= 3 ? $parts[1] : '';

            return array_filter([
                'given' => trim($given),
                'family' => $familyParts['family'],
                'suffix' => trim($suffix),
                'non-dropping-particle' => $familyParts['particle'],
            ], static fn (string $value): bool => $value !== '');
        }

        $clean = $this->cleanText($trimmed);
        $words = preg_split('/\s+/u', $clean, -1, PREG_SPLIT_NO_EMPTY);
        if ($words === false || count($words) <= 1) {
            return ['literal' => $clean];
        }

        $particleStart = $this->particleStartIndex($words);
        if ($particleStart !== null) {
            $givenWords = array_slice($words, 0, $particleStart);
            $family = implode(' ', array_slice($words, $particleStart));
        } else {
            $givenWords = $words;
            $family = (string) array_pop($givenWords);
        }
        $familyParts = $this->familyParticles((string) $family);

        return array_filter([
            'given' => implode(' ', $givenWords),
            'family' => $familyParts['family'],
            'non-dropping-particle' => $familyParts['particle'],
        ], static fn (string $value): bool => $value !== '');
    }

    /**
     * @param list<string> $words
     */
    private function particleStartIndex(array $words): ?int
    {
        for ($index = 0, $count = count($words) - 1; $index < $count; $index++) {
            if ($this->startsWithLowercaseLetter($words[$index])) {
                return $index;
            }
        }

        return null;
    }

    private function startsWithLowercaseLetter(string $word): bool
    {
        $word = preg_replace('/^[^\p{L}]+/u', '', $word) ?? $word;

        return preg_match('/^\p{Ll}/u', $word) === 1;
    }

    /**
     * @return array{family:string,particle:string}
     */
    private function familyParticles(string $family): array
    {
        $family = trim($family);
        if (preg_match('/^(de la|de las|de los|van der|van den|van de|von der|von den|von dem|van|von|de|da|del|della|di|du|la|le|ter|ten|der|den)\s+(.+)$/iu', $family, $match) === 1) {
            return [
                'particle' => $match[1],
                'family' => $match[2],
            ];
        }

        return ['family' => $family, 'particle' => ''];
    }

    private function isProtectedLiteralName(string $raw): bool
    {
        $trimmed = trim($raw);
        if (str_starts_with($trimmed, '{{') && str_ends_with($trimmed, '}}')) {
            return true;
        }
        if (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}') && !str_contains($trimmed, ',')) {
            $inner = trim(substr($trimmed, 1, -1));
            return str_contains($inner, ' ') && preg_match('/\p{Lu}/u', $inner) === 1;
        }

        return false;
    }

    private function cleanText(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = $this->replaceLatexArgumentCommands($value);
        $value = strtr($value, self::SIMPLE_LATEX_COMMANDS);
        $value = $this->replaceLatexAccents($value);
        $value = str_replace(['``', "''"], ['"', '"'], $value);

        for ($i = 0; $i < 4; $i++) {
            $next = preg_replace('/\\\\[A-Za-z]+\*?\s*\{([^{}]*)\}/u', '$1', $value);
            if (!is_string($next) || $next === $value) {
                break;
            }
            $value = $next;
        }

        $value = preg_replace('/\\\\([A-Za-z]+)/u', '$1', $value) ?? $value;
        $value = $this->stripUnescapedBraces($value);
        $value = str_replace('~', ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function replaceLatexArgumentCommands(string $value): string
    {
        $value = $this->replaceLatexCommandWithArgument($value, 'href', 2);
        foreach (['url', 'path', 'nolinkurl'] as $command) {
            $value = $this->replaceLatexCommandWithArgument($value, $command, 1);
        }

        for ($i = 0; $i < 4; $i++) {
            $next = $value;
            foreach (self::LATEX_UNWRAP_COMMANDS as $command) {
                $next = $this->replaceLatexCommandWithArgument($next, $command, 1);
            }
            if ($next === $value) {
                break;
            }
            $value = $next;
        }

        return $value;
    }

    private function replaceLatexCommandWithArgument(string $value, string $command, int $argumentIndex): string
    {
        $result = '';
        $offset = 0;
        $needle = '\\' . $command;
        $length = strlen($value);

        while (($position = strpos($value, $needle, $offset)) !== false) {
            $afterCommand = $position + strlen($needle);
            if ($afterCommand < $length && preg_match('/[A-Za-z]/', $value[$afterCommand]) === 1) {
                $result .= substr($value, $offset, $position + 1 - $offset);
                $offset = $position + 1;
                continue;
            }

            $cursor = $afterCommand;
            if ($cursor < $length && $value[$cursor] === '*') {
                $cursor++;
            }

            $arguments = [];
            $matched = true;
            for ($index = 1; $index <= $argumentIndex; $index++) {
                while ($cursor < $length && preg_match('/\s/', $value[$cursor]) === 1) {
                    $cursor++;
                }

                $argument = $this->readLatexBracedArgument($value, $cursor);
                if ($argument === null) {
                    $matched = false;
                    break;
                }

                $arguments[$index] = $argument['value'];
                $cursor = $argument['offset'];
            }

            if (!$matched) {
                $result .= substr($value, $offset, $position + 1 - $offset);
                $offset = $position + 1;
                continue;
            }

            $result .= substr($value, $offset, $position - $offset) . $arguments[$argumentIndex];
            $offset = $cursor;
        }

        return $result . substr($value, $offset);
    }

    /**
     * @return array{value:string,offset:int}|null
     */
    private function readLatexBracedArgument(string $value, int $offset): ?array
    {
        if ($offset >= strlen($value) || $value[$offset] !== '{') {
            return null;
        }

        $offset++;
        $depth = 1;
        $argument = '';
        while ($offset < strlen($value)) {
            $char = $value[$offset];
            if ($char === '\\' && $offset + 1 < strlen($value) && in_array($value[$offset + 1], ['{', '}'], true)) {
                $argument .= $char . $value[$offset + 1];
                $offset += 2;
                continue;
            }
            if ($char === '{') {
                $depth++;
                $argument .= $char;
                $offset++;
                continue;
            }
            if ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return ['value' => $argument, 'offset' => $offset + 1];
                }
                $argument .= $char;
                $offset++;
                continue;
            }

            $argument .= $char;
            $offset++;
        }

        return null;
    }

    private function replaceLatexAccents(string $value): string
    {
        $value = preg_replace_callback(
            '/\\\\([`\'"^~=.])\s*\{?([A-Za-z])\}?/u',
            fn (array $match): string => $this->accentedLetter($match[1], $match[2]),
            $value
        ) ?? $value;

        return preg_replace_callback(
            '/\\\\(c|u|v|H|r|k|b|d)\s*\{?([A-Za-z])\}?/u',
            fn (array $match): string => $this->accentedLetter($match[1], $match[2]),
            $value
        ) ?? $value;
    }

    private function accentedLetter(string $accent, string $letter): string
    {
        $lower = strtolower($letter);
        $upper = ctype_upper($letter);
        $maps = [
            "'" => ['a' => 'á', 'e' => 'é', 'i' => 'í', 'o' => 'ó', 'u' => 'ú', 'y' => 'ý', 'c' => 'ć'],
            '`' => ['a' => 'à', 'e' => 'è', 'i' => 'ì', 'o' => 'ò', 'u' => 'ù'],
            '"' => ['a' => 'ä', 'e' => 'ë', 'i' => 'ï', 'o' => 'ö', 'u' => 'ü', 'y' => 'ÿ'],
            '^' => ['a' => 'â', 'e' => 'ê', 'i' => 'î', 'o' => 'ô', 'u' => 'û'],
            '~' => ['a' => 'ã', 'n' => 'ñ', 'o' => 'õ'],
            '=' => ['a' => 'ā', 'e' => 'ē', 'i' => 'ī', 'o' => 'ō', 'u' => 'ū'],
            '.' => ['a' => 'ȧ', 'e' => 'ė', 'i' => 'ı', 'o' => 'ȯ', 'u' => 'u'],
            'b' => ['a' => 'a̱', 'e' => 'e̱', 'i' => 'i̱', 'o' => 'o̱', 'u' => 'u̱'],
            'c' => ['c' => 'ç', 'g' => 'ģ', 'k' => 'ķ', 'l' => 'ļ', 'n' => 'ņ', 'r' => 'ŗ', 's' => 'ş', 't' => 'ţ'],
            'd' => ['a' => 'ạ', 'e' => 'ẹ', 'i' => 'ị', 'o' => 'ọ', 'u' => 'ụ'],
            'H' => ['o' => 'ő', 'u' => 'ű'],
            'k' => ['a' => 'ą', 'e' => 'ę', 'i' => 'į', 'o' => 'ǫ', 'u' => 'ų'],
            'r' => ['a' => 'å', 'u' => 'ů'],
            'u' => ['a' => 'ă', 'e' => 'ĕ', 'g' => 'ğ', 'i' => 'ĭ', 'o' => 'ŏ', 'u' => 'ŭ'],
            'v' => ['c' => 'č', 'd' => 'ď', 'e' => 'ě', 'n' => 'ň', 'r' => 'ř', 's' => 'š', 't' => 'ť', 'z' => 'ž'],
        ];

        $replacement = $maps[$accent][$lower] ?? $letter;

        return $upper ? mb_strtoupper($replacement, 'UTF-8') : $replacement;
    }

    private function stripUnescapedBraces(string $value): string
    {
        $clean = '';
        for ($i = 0, $length = strlen($value); $i < $length; $i++) {
            $char = $value[$i];
            if ($char === '\\' && $i + 1 < $length && in_array($value[$i + 1], ['{', '}'], true)) {
                $clean .= $value[$i + 1];
                $i++;
                continue;
            }
            if ($char === '{' || $char === '}') {
                continue;
            }
            $clean .= $char;
        }

        return $clean;
    }

    /**
     * @param array<string, mixed> $items
     * @return array{type:string,value:array<string, mixed>}
     */
    private function metaMap(array $items): array
    {
        return ['type' => 'MetaMap', 'value' => $items];
    }

    /**
     * @param list<mixed> $items
     * @return array{type:string,value:list<mixed>}
     */
    private function metaList(array $items): array
    {
        return ['type' => 'MetaList', 'value' => array_values($items)];
    }
}
