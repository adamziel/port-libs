<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class RisReader
{
    /** @var array<string, string> */
    private const TYPES = [
        'ABST' => 'article',
        'ADVS' => 'motion-picture',
        'AGGR' => 'dataset',
        'ANCIENT' => 'book',
        'ART' => 'graphic',
        'BILL' => 'bill',
        'BLOG' => 'post-weblog',
        'BOOK' => 'book',
        'CASE' => 'legal_case',
        'CHAP' => 'chapter',
        'CHART' => 'graphic',
        'COMP' => 'program',
        'CONF' => 'paper-conference',
        'CPAPER' => 'paper-conference',
        'DATA' => 'dataset',
        'EBOOK' => 'book',
        'ECHAP' => 'chapter',
        'EJOUR' => 'article',
        'GEN' => 'entry',
        'JFULL' => 'article-journal',
        'JOUR' => 'article-journal',
        'MGZN' => 'article-magazine',
        'NEWS' => 'article-newspaper',
        'PAMP' => 'pamphlet',
        'RPRT' => 'report',
        'THES' => 'thesis',
        'UNPB' => 'unpublished',
        'WEB' => 'webpage',
    ];

    public function read(string $source): AstNode
    {
        $records = $this->parseRecords($source);
        $references = $this->deduplicateReferenceIds(array_map(
            fn (array $record, int $index): array => $this->recordReference($record, $index + 1),
            $records,
            array_keys($records)
        ));
        $metadata = [
            'references' => $this->metaList(array_map(fn (array $reference): array => $this->referenceMeta($reference), $references)),
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
            'risRecordCount' => count($references),
            'risLineCount' => array_sum(array_map('count', $records)),
        ];

        return new AstNode('document', ['meta' => $metadata], $this->bibliographyBlocks($references));
    }

    public function readRisFile(string $path): AstNode
    {
        $source = file_get_contents($path);
        if (!is_string($source)) {
            throw new \RuntimeException("Unable to read '{$path}'.");
        }

        return $this->read($source);
    }

    /**
     * @return list<list<array{key:string,value:string}>>
     */
    private function parseRecords(string $source): array
    {
        $source = preg_replace('/^\xEF\xBB\xBF/', '', $source) ?? $source;
        $source = str_replace(["\r\n", "\r"], "\n", $source);
        $records = [];
        $current = [];
        $lastIndex = null;

        foreach (explode("\n", $source) as $line) {
            if ($line === '') {
                continue;
            }
            if (preg_match('/^([A-Z0-9]{2})\s+-\s?(.*)$/', $line, $match) !== 1) {
                if ($lastIndex !== null) {
                    $current[$lastIndex]['value'] = trim($current[$lastIndex]['value'] . ' ' . trim($line));
                }
                continue;
            }

            $key = $match[1];
            $value = trim($match[2]);
            if ($key === 'ER') {
                if ($current !== []) {
                    $records[] = $current;
                }
                $current = [];
                $lastIndex = null;
                continue;
            }

            $current[] = ['key' => $key, 'value' => $value];
            $lastIndex = array_key_last($current);
        }

        if ($current !== []) {
            $records[] = $current;
        }

        return $records;
    }

    /**
     * @param list<array{key:string,value:string}> $record
     * @return array{id:string,type:string,fields:array<string,mixed>,rawTags:array<string,list<string>>}
     */
    private function recordReference(array $record, int $index): array
    {
        $type = 'entry';
        $id = '';
        $fields = [];
        $rawTags = [];
        $pageStart = '';
        $pageEnd = '';

        foreach ($record as $line) {
            $key = $line['key'];
            $value = $line['value'];
            $rawTags[$key][] = $value;
            if ($value === '' && $key !== 'ID') {
                continue;
            }

            switch ($key) {
                case 'TY':
                    $type = self::TYPES[$value] ?? 'misc';
                    break;
                case 'ID':
                    $id = $value;
                    break;
                case 'VL':
                    $fields['volume'] = $value;
                    break;
                case 'KW':
                    $fields['keyword'] = isset($fields['keyword']) ? $fields['keyword'] . ', ' . $value : $value;
                    break;
                case 'PB':
                    $fields['publisher'] = $value;
                    break;
                case 'PP':
                    $fields['publisher-place'] = $value;
                    break;
                case 'DO':
                    $fields['DOI'] = $value;
                    break;
                case 'SP':
                    $pageStart = $value;
                    break;
                case 'EP':
                    $pageEnd = $value;
                    break;
                case 'AU':
                case 'A1':
                    $fields['author'][] = $this->parseName($value);
                    break;
                case 'ED':
                case 'A2':
                    $fields['editor'][] = $this->parseName($value);
                    break;
                case 'TI':
                case 'T1':
                case 'CT':
                    $fields['title'] = $value;
                    break;
                case 'BT':
                    $fields[$type === 'book' ? 'title' : 'container-title'] = $value;
                    break;
                case 'JO':
                case 'JF':
                case 'T2':
                    $fields['container-title'] = $value;
                    break;
                case 'ET':
                    $fields['edition'] = $value;
                    break;
                case 'NV':
                    $fields['number-of-volumes'] = $value;
                    break;
                case 'AB':
                    $fields['abstract'] = $value;
                    break;
                case 'PY':
                case 'Y1':
                    $fields['issued'] = $this->dateMeta($value);
                    $fields['year'] = $this->yearFromDate($value);
                    $fields['date'] = $value;
                    break;
                case 'IS':
                    $fields['issue'] = $value;
                    break;
                case 'SN':
                    $fields['ISSN'] = $value;
                    break;
                case 'LA':
                    $fields['language'] = $value;
                    break;
                case 'UR':
                case 'LK':
                    $fields['URL'] = $value;
                    break;
                case 'N1':
                    $fields['note'] = $value;
                    break;
            }
        }

        if ($pageStart !== '' || $pageEnd !== '') {
            $fields['page'] = $pageStart !== '' && $pageEnd !== '' ? $pageStart . '-' . $pageEnd : $pageStart . $pageEnd;
        }

        if ($id === '') {
            $id = $this->generatedId($fields, $index);
        }

        return [
            'id' => $id,
            'type' => $type,
            'fields' => $fields,
            'rawTags' => $rawTags,
        ];
    }

    /**
     * @param list<array{id:string,type:string,fields:array<string,mixed>,rawTags:array<string,list<string>>}> $references
     * @return list<array{id:string,type:string,fields:array<string,mixed>,rawTags:array<string,list<string>>}>
     */
    private function deduplicateReferenceIds(array $references): array
    {
        $seen = [];
        foreach ($references as $index => $reference) {
            $id = $reference['id'];
            if (!isset($seen[$id])) {
                $seen[$id] = ord('a');
                continue;
            }
            $references[$index]['id'] = $id . chr($seen[$id]);
            $seen[$id]++;
        }

        return $references;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function generatedId(array $fields, int $index): string
    {
        $author = '';
        $authors = $fields['author'] ?? [];
        if (is_array($authors) && isset($authors[0]) && is_array($authors[0])) {
            $author = (string) ($authors[0]['family'] ?? $authors[0]['literal'] ?? '');
        }
        $author = preg_replace('/[^\p{L}\p{N}]+/u', '_', $author) ?? $author;
        $author = trim($author, '_');
        $year = (string) ($fields['year'] ?? '');

        $id = trim($author . ($year !== '' ? '_' . $year : ''), '_');

        return $id !== '' ? $id : 'ris-' . $index;
    }

    /**
     * @return array{literal?:string,given?:string,family?:string}
     */
    private function parseName(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $part): bool => $part !== ''));
        if (count($parts) >= 2) {
            return ['family' => $parts[0], 'given' => count($parts) >= 3 ? $parts[2] . ' ' . $parts[1] : $parts[1]];
        }

        $words = preg_split('/\s+/u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if ($words === false || count($words) <= 1) {
            return ['literal' => $value];
        }
        $family = array_pop($words);

        return ['given' => implode(' ', $words), 'family' => (string) $family];
    }

    private function yearFromDate(string $value): string
    {
        if (preg_match('/\b([12][0-9]{3})\b/', $value, $match) === 1) {
            return $match[1];
        }

        return '';
    }

    /**
     * @return array{type:string,value:array<string, mixed>}|string
     */
    private function dateMeta(string $value): array|string
    {
        $year = $this->yearFromDate($value);
        if ($year === '') {
            return $value;
        }

        return $this->metaMap([
            'date-parts' => $this->metaList([
                $this->metaList([$year]),
            ]),
        ]);
    }

    /**
     * @param array{id:string,type:string,fields:array<string,mixed>,rawTags:array<string,list<string>>} $reference
     */
    private function referenceMeta(array $reference): array
    {
        $meta = [
            'id' => $reference['id'],
            'type' => $reference['type'],
        ];
        foreach ($reference['fields'] as $key => $value) {
            if (in_array($key, ['author', 'editor'], true) && is_array($value)) {
                $meta[$key] = $this->metaList(array_map(fn (array $name): array => $this->personNameMeta($name), $value));
                continue;
            }
            $meta[$key] = $value;
        }

        $tagMeta = [];
        ksort($reference['rawTags']);
        foreach ($reference['rawTags'] as $tag => $values) {
            $tagMeta[$tag] = $this->metaList($values);
        }
        $meta['ris-tags'] = $this->metaMap($tagMeta);

        return $this->metaMap($meta);
    }

    /**
     * @param array{literal?:string,given?:string,family?:string} $name
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

        return $this->metaMap($meta);
    }

    /**
     * @param list<array{id:string,type:string,fields:array<string,mixed>,rawTags:array<string,list<string>>}> $references
     * @return list<AstNode>
     */
    private function bibliographyBlocks(array $references): array
    {
        if ($references === []) {
            return [
                new AstNode('paragraph', ['text' => 'No RIS entries were found.'], [
                    new AstNode('text', ['text' => 'No RIS entries were found.']),
                ]),
            ];
        }

        return [
            new AstNode('div', [
                'id' => 'refs',
                'classes' => ['csl-bib-body'],
                'attributes' => [
                    'data-pandoc-source' => 'ris',
                    'data-ris-entry-count' => (string) count($references),
                ],
            ], array_map(fn (array $reference): AstNode => $this->entryBlock($reference), $references)),
        ];
    }

    /**
     * @param array{id:string,type:string,fields:array<string,mixed>,rawTags:array<string,list<string>>} $reference
     */
    private function entryBlock(array $reference): AstNode
    {
        return new AstNode('div', [
            'id' => 'ref-' . $this->htmlId($reference['id']),
            'classes' => ['csl-entry'],
            'attributes' => [
                'data-ris-id' => $reference['id'],
                'data-ris-type' => (string) (($reference['rawTags']['TY'][0] ?? '') ?: $reference['type']),
            ],
        ], [
            new AstNode('paragraph', [], $this->summaryInlines($reference)),
        ]);
    }

    /**
     * @param array{id:string,type:string,fields:array<string,mixed>,rawTags:array<string,list<string>>} $reference
     * @return list<AstNode>
     */
    private function summaryInlines(array $reference): array
    {
        $fields = $reference['fields'];
        $nodes = [];
        $authors = $this->displayNames($fields['author'] ?? []);
        if ($authors === '' && isset($fields['editor'])) {
            $authors = $this->displayNames($fields['editor']) . ' (ed.)';
        }
        if ($authors !== '') {
            $nodes[] = new AstNode('text', ['text' => $authors . '. ']);
        }
        $year = (string) ($fields['year'] ?? '');
        if ($year !== '') {
            $nodes[] = new AstNode('text', ['text' => '(' . $year . '). ']);
        }
        $title = (string) ($fields['title'] ?? '');
        if ($title !== '') {
            $titleNode = new AstNode('text', ['text' => $title]);
            $nodes[] = $reference['type'] === 'book' ? new AstNode('emph', [], [$titleNode]) : $titleNode;
            $nodes[] = new AstNode('text', ['text' => '. ']);
        }
        $container = (string) ($fields['container-title'] ?? '');
        if ($container !== '') {
            $nodes[] = new AstNode('emph', [], [new AstNode('text', ['text' => $container])]);
            $suffix = '';
            if (($fields['volume'] ?? '') !== '') {
                $suffix .= ' ' . $fields['volume'];
            }
            if (($fields['issue'] ?? '') !== '') {
                $suffix .= '(' . $fields['issue'] . ')';
            }
            if (($fields['page'] ?? '') !== '') {
                $suffix .= ': ' . $fields['page'];
            }
            $nodes[] = new AstNode('text', ['text' => $suffix . '. ']);
        }
        if (($fields['publisher'] ?? '') !== '') {
            $place = (string) ($fields['publisher-place'] ?? '');
            $nodes[] = new AstNode('text', ['text' => ($place !== '' ? $place . ': ' : '') . $fields['publisher'] . '. ']);
        }
        if (($fields['DOI'] ?? '') !== '') {
            $doi = (string) $fields['DOI'];
            $nodes[] = new AstNode('text', ['text' => 'doi: ']);
            $nodes[] = new AstNode('link', ['url' => 'https://doi.org/' . ltrim($doi, '/')], [
                new AstNode('text', ['text' => $doi]),
            ]);
            $nodes[] = new AstNode('text', ['text' => '. ']);
        } elseif (($fields['URL'] ?? '') !== '') {
            $url = (string) $fields['URL'];
            $nodes[] = new AstNode('text', ['text' => 'Available at ']);
            $nodes[] = new AstNode('link', ['url' => $url], [new AstNode('text', ['text' => $url])]);
            $nodes[] = new AstNode('text', ['text' => '. ']);
        }

        return $nodes === [] ? [new AstNode('text', ['text' => $reference['id']])] : $nodes;
    }

    private function displayNames(mixed $names): string
    {
        if (!is_array($names) || $names === []) {
            return '';
        }

        $display = [];
        foreach ($names as $name) {
            if (!is_array($name)) {
                continue;
            }
            if (($name['literal'] ?? '') !== '') {
                $display[] = $name['literal'];
                continue;
            }
            $display[] = trim((string) ($name['given'] ?? '') . ' ' . (string) ($name['family'] ?? ''));
        }

        if (count($display) <= 1) {
            return $display[0] ?? '';
        }

        return implode(', ', array_slice($display, 0, -1)) . ' and ' . $display[array_key_last($display)];
    }

    private function htmlId(string $key): string
    {
        $id = preg_replace('/[^\p{L}\p{N}_.:-]+/u', '-', $key) ?? $key;
        $id = trim($id, '-');

        return $id === '' ? substr(sha1($key), 0, 12) : $id;
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
