<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class CitationCslProcessor
{
    /** @var array<string, array<string, mixed>> */
    private array $itemsById;

    /**
     * @param array<string, array<string, mixed>> $itemsById
     */
    private function __construct(array $itemsById)
    {
        $this->itemsById = $itemsById;
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

        return '(' . implode('; ', $entries) . ')';
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
            $parts[] = $authors . '.';
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
        if ($publisher !== '' && $year !== 'n.d.') {
            $parts[] = $publisher . ', ' . $year . '.';
        } elseif ($publisher !== '') {
            $parts[] = $publisher . '.';
        } elseif ($year !== 'n.d.') {
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

        return implode(' ', $parts);
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

        return new AstNode('definition_list', ['classes' => ['pandoc-csl-bibliography']], $items);
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

        return [
            'id' => $id,
            'type' => self::stringField($item, 'type'),
            'title' => self::stringField($item, 'title'),
            'containerTitle' => self::stringField($item, 'container-title'),
            'publisher' => self::stringField($item, 'publisher'),
            'page' => self::stringField($item, 'page'),
            'doi' => self::stringField($item, 'DOI'),
            'url' => self::stringField($item, 'URL'),
            'issuedYear' => self::issuedYear($item['issued'] ?? null, $id),
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
     * @return list<array{family:string, given:string, literal:string}>
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
            if ($literal === '' && $family === '' && $given === '') {
                throw new \InvalidArgumentException('CSL item ' . $id . ' field ' . $field . '[' . $index . '] has no name content');
            }

            $names[] = [
                'family' => $family,
                'given' => $given,
                'literal' => $literal,
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

    private static function issuedYear(mixed $issued, string $id): ?int
    {
        if ($issued === null || $issued === []) {
            return null;
        }

        if (!is_array($issued)) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' issued field must be an object');
        }

        $dateParts = $issued['date-parts'] ?? null;
        if ($dateParts === null || $dateParts === []) {
            return null;
        }

        if (!is_array($dateParts) || !isset($dateParts[0]) || !is_array($dateParts[0]) || !isset($dateParts[0][0])) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' issued date-parts must contain a year');
        }

        $year = $dateParts[0][0];
        if (!is_int($year) && !(is_string($year) && preg_match('/^-?\d+$/', $year) === 1)) {
            throw new \InvalidArgumentException('CSL item ' . $id . ' issued year must be numeric');
        }

        return (int) $year;
    }

    private function mapNode(AstNode $node): AstNode
    {
        if ($node->type === 'citation') {
            return $this->normalizeCitation($node);
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

            if ($name['family'] !== '') {
                return $name['family'];
            }

            return $name['given'];
        }, $names);

        if (count($families) === 1) {
            return $families[0];
        }

        if (count($families) === 2) {
            return $families[0] . ' and ' . $families[1];
        }

        return $families[0] . ' et al.';
    }

    /**
     * @param array<string, mixed> $item
     */
    private function citationYear(array $item): string
    {
        return isset($item['issuedYear']) && $item['issuedYear'] !== null ? (string) $item['issuedYear'] : 'n.d.';
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

            $family = (string) $name['family'];
            $given = (string) $name['given'];
            if ($family !== '' && $given !== '') {
                $parts[] = $family . ', ' . $given;
            } else {
                $parts[] = $family !== '' ? $family : $given;
            }
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
        if ($suffix === null || $suffix === '') {
            $suffix = $citation->attr('locator', '');
        }

        return $this->inlineValue($suffix);
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
