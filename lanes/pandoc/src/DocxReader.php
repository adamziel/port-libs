<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DocxReader
{
    private const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const R_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const A_NS = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    private const WP_NS = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';
    private const M_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/math';
    private const REL_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /** @var array<string, array<string, mixed>> */
    private array $styles = [];

    /** @var array<string, array<int, array{ordered: bool, style?: string, delimiter?: string, start?: int}>> */
    private array $numbering = [];

    /** @var array<string, array{target: string, type: string, mode: string}> */
    private array $relationships = [];

    /** @var array<string, list<AstNode>> */
    private array $footnotes = [];

    /** @var array<string, list<AstNode>> */
    private array $endnotes = [];

    /** @var array<string, array{author: string, date: string, children: list<AstNode>, text: string}> */
    private array $comments = [];

    public function read(string $bytes): AstNode
    {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-docx-');
        if ($path === false) {
            throw new \RuntimeException('Unable to create temporary DOCX path.');
        }

        try {
            if (file_put_contents($path, $bytes) === false) {
                throw new \RuntimeException('Unable to write temporary DOCX package.');
            }

            return $this->readDocxFile($path);
        } finally {
            @unlink($path);
        }
    }

    public function readDocxFile(string $path): AstNode
    {
        $package = ZipOpcPackage::open($path, 'DOCX');

        try {
            $document_xml = $package->requireRead('word/document.xml', 'DOCX package is missing word/document.xml.');
            $styles_xml = $package->read('word/styles.xml');
            $numbering_xml = $package->read('word/numbering.xml');
            $rels_xml = $package->read('word/_rels/document.xml.rels');
            $core_xml = $package->read('docProps/core.xml');
            $footnotes_xml = $package->read('word/footnotes.xml');
            $endnotes_xml = $package->read('word/endnotes.xml');
            $comments_xml = $package->read('word/comments.xml');

            $entries = $package->entryNames();
            $media = [];
            $header_xmls = [];
            $footer_xmls = [];
            foreach ($entries as $name) {
                if (str_starts_with($name, 'word/media/')) {
                    $media[] = $name;
                }
                if (preg_match('#^word/header\d*\.xml$#', $name) === 1) {
                    $xml = $package->read($name);
                    if (is_string($xml)) {
                        $header_xmls[$name] = $xml;
                    }
                }
                if (preg_match('#^word/footer\d*\.xml$#', $name) === 1) {
                    $xml = $package->read($name);
                    if (is_string($xml)) {
                        $footer_xmls[$name] = $xml;
                    }
                }
            }
            ksort($header_xmls);
            ksort($footer_xmls);
        } finally {
            $package->close();
        }

        return $this->readPackage(
            $document_xml,
            $styles_xml ?? '',
            $numbering_xml ?? '',
            $rels_xml ?? '',
            $core_xml ?? '',
            $footnotes_xml ?? '',
            $endnotes_xml ?? '',
            $comments_xml ?? '',
            $header_xmls,
            $footer_xmls,
            $entries,
            $media,
        );
    }

    /**
     * @param array<string, string> $header_xmls
     * @param array<string, string> $footer_xmls
     * @param list<string> $entries
     * @param list<string> $media
     */
    private function readPackage(
        string $document_xml,
        string $styles_xml,
        string $numbering_xml,
        string $rels_xml,
        string $core_xml,
        string $footnotes_xml,
        string $endnotes_xml,
        string $comments_xml,
        array $header_xmls,
        array $footer_xmls,
        array $entries,
        array $media
    ): AstNode {
        $this->styles = $styles_xml !== '' ? $this->styles($this->loadXml($styles_xml, 'DOCX styles.xml')) : [];
        $this->numbering = $numbering_xml !== '' ? $this->numbering($this->loadXml($numbering_xml, 'DOCX numbering.xml')) : [];
        $this->relationships = $rels_xml !== '' ? $this->relationships($this->loadXml($rels_xml, 'DOCX document relationships')) : [];
        $this->footnotes = $footnotes_xml !== '' ? $this->notes($this->loadXml($footnotes_xml, 'DOCX footnotes.xml'), 'footnote') : [];
        $this->endnotes = $endnotes_xml !== '' ? $this->notes($this->loadXml($endnotes_xml, 'DOCX endnotes.xml'), 'endnote') : [];
        $this->comments = $comments_xml !== '' ? $this->comments($this->loadXml($comments_xml, 'DOCX comments.xml')) : [];

        $document = $this->loadXml($document_xml, 'DOCX document.xml');
        $body = $this->firstElementByLocalName($document, 'body');
        $headers = $this->partBlocks($header_xmls, 'DOCX header');
        $footers = $this->partBlocks($footer_xmls, 'DOCX footer');
        $body_children = $body instanceof \DOMElement ? $this->bodyBlocks($body) : [];
        $children = array_merge(
            $this->partDivs($headers, 'docx-header'),
            $body_children,
            $this->partDivs($footers, 'docx-footer')
        );
        if ($children === []) {
            $children[] = new AstNode('paragraph', ['text' => 'No readable DOCX body content was found.'], [
                new AstNode('text', ['text' => 'No readable DOCX body content was found.']),
            ]);
        }

        $metadata = $core_xml !== '' ? $this->coreProperties($this->loadXml($core_xml, 'DOCX core properties')) : [];
        $metadata['docxPackageEntries'] = count($entries);
        $metadata['docxMediaFiles'] = $media;
        $metadata['docxRelationshipCount'] = count($this->relationships);
        $metadata['docxNumberingDefinitions'] = count($this->numbering);
        $metadata['docxFootnotes'] = count($this->footnotes);
        $metadata['docxEndnotes'] = count($this->endnotes);
        $metadata['docxComments'] = count($this->comments);
        $metadata['docxHeaders'] = count($headers);
        $metadata['docxFooters'] = count($footers);
        $metadata['docxHeaderFiles'] = array_keys($header_xmls);
        $metadata['docxFooterFiles'] = array_keys($footer_xmls);

        return new AstNode('document', ['meta' => $metadata], $children);
    }

    /**
     * @param array<string, string> $xmlParts
     * @return array<string, list<AstNode>>
     */
    private function partBlocks(array $xmlParts, string $label): array
    {
        $parts = [];
        foreach ($xmlParts as $name => $xml) {
            if ($xml === '') {
                continue;
            }
            $root = $this->loadXml($xml, $label . ' ' . $name)->documentElement;
            if (!$root instanceof \DOMElement) {
                continue;
            }
            $blocks = $this->bodyBlocks($root);
            if ($blocks !== []) {
                $parts[$name] = $blocks;
            }
        }

        return $parts;
    }

    /**
     * @param array<string, list<AstNode>> $parts
     * @return list<AstNode>
     */
    private function partDivs(array $parts, string $class): array
    {
        $divs = [];
        $index = 1;
        foreach ($parts as $name => $blocks) {
            $divs[] = new AstNode('div', [
                'id' => $class . '-' . $index,
                'classes' => [$class],
                'attributes' => [
                    'data-docx-part' => $name,
                    'data-pandoc-source' => 'docx',
                ],
            ], $blocks);
            $index++;
        }

        return $divs;
    }

    /**
     * @return list<AstNode>
     */
    private function bodyBlocks(\DOMElement $body): array
    {
        $blocks = [];
        $pendingListRecords = [];

        $flushList = function () use (&$blocks, &$pendingListRecords): void {
            if ($pendingListRecords === []) {
                return;
            }
            array_push($blocks, ...$this->listBlocksFromRecords($pendingListRecords));
            $pendingListRecords = [];
        };

        foreach ($body->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 'p') {
                $paragraph = $this->paragraph($child);
                if (!$paragraph instanceof AstNode) {
                    continue;
                }
                $numbering = $this->paragraphNumbering($child);
                if ($numbering !== null) {
                    $list = $this->numberingListAttributes($numbering['numId'], $numbering['level']);
                    $pendingListRecords[] = [
                        'level' => $numbering['level'],
                        'ordered' => $list['ordered'],
                        'attrs' => $list['attrs'],
                        'paragraph' => $paragraph,
                    ];
                    continue;
                }
                $flushList();
                $blocks[] = $paragraph;
                continue;
            }
            if ($child->localName === 'tbl') {
                $flushList();
                $blocks[] = $this->table($child);
                continue;
            }
            if ($child->localName === 'oMathPara') {
                $flushList();
                $math = $this->ommlMath($child, true);
                if ($math instanceof AstNode) {
                    $blocks[] = new AstNode('plain', [], [$math]);
                }
            }
        }

        $flushList();

        return $blocks;
    }

    /**
     * @param list<array{level: int, ordered: bool, attrs: array<string, mixed>, paragraph: AstNode}> $records
     * @return list<AstNode>
     */
    private function listBlocksFromRecords(array $records): array
    {
        if ($records === []) {
            return [];
        }

        $index = 0;
        return $this->listBlocksAtLevel($records, $index, max(1, (int) $records[0]['level']));
    }

    /**
     * @param list<array{level: int, ordered: bool, attrs: array<string, mixed>, paragraph: AstNode}> $records
     * @return list<AstNode>
     */
    private function listBlocksAtLevel(array $records, int &$index, int $level): array
    {
        $blocks = [];
        $count = count($records);
        while ($index < $count) {
            $record = $records[$index];
            $recordLevel = max(1, (int) $record['level']);
            if ($recordLevel < $level) {
                break;
            }
            if ($recordLevel > $level) {
                break;
            }

            $ordered = (bool) $record['ordered'];
            $attrs = $record['attrs'];
            $items = [];

            while ($index < $count) {
                $record = $records[$index];
                $recordLevel = max(1, (int) $record['level']);
                if ($recordLevel < $level) {
                    break;
                }
                if ($recordLevel > $level) {
                    break;
                }
                if ((bool) $record['ordered'] !== $ordered || $record['attrs'] !== $attrs) {
                    break;
                }

                $index++;
                $children = [$record['paragraph']];
                while ($index < $count && max(1, (int) $records[$index]['level']) > $level) {
                    $nestedLevel = max(1, (int) $records[$index]['level']);
                    array_push($children, ...$this->listBlocksAtLevel($records, $index, $nestedLevel));
                }
                $items[] = new AstNode('list_item', [], $children);
            }

            $blocks[] = new AstNode($ordered ? 'ordered_list' : 'bullet_list', $ordered ? $attrs : [], $items);
        }

        return $blocks;
    }

    private function paragraph(\DOMElement $paragraph): ?AstNode
    {
        $inlines = $this->inlineChildren($paragraph);
        $text = $this->plainText($inlines);
        if ($text === '' && $inlines === []) {
            return null;
        }

        $styleId = $this->paragraphStyleId($paragraph);
        $attrs = $this->styleNodeAttrs($styleId, 'paragraph');
        $level = $this->headingLevel($paragraph);
        if ($level !== null) {
            return new AstNode('heading', array_replace($attrs, ['level' => $level, 'text' => $text]), $inlines);
        }

        return new AstNode('paragraph', array_replace($attrs, ['text' => $text]), $inlines);
    }

    /**
     * @return list<AstNode>
     */
    private function inlineChildren(\DOMElement $container): array
    {
        $inlines = [];
        foreach ($container->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 'r') {
                array_push($inlines, ...$this->run($child));
                continue;
            }
            if ($child->localName === 'hyperlink') {
                $link = $this->hyperlink($child);
                if ($link instanceof AstNode) {
                    $inlines[] = $link;
                }
                continue;
            }
            if ($child->localName === 'fldSimple') {
                $field = $this->simpleField($child);
                if ($field instanceof AstNode) {
                    $inlines[] = $field;
                } else {
                    array_push($inlines, ...$this->inlineChildren($child));
                }
                continue;
            }
            if ($child->localName === 'bookmarkStart' || $child->localName === 'bookmarkEnd') {
                $bookmark = $this->bookmarkRawInline($child);
                if ($bookmark instanceof AstNode) {
                    $inlines[] = $bookmark;
                }
                continue;
            }
            if ($child->localName === 'commentRangeStart' || $child->localName === 'commentRangeEnd') {
                $commentRange = $this->commentRangeSpan($child);
                if ($commentRange instanceof AstNode) {
                    $inlines[] = $commentRange;
                }
                continue;
            }
            if ($child->localName === 'oMath' || $child->localName === 'oMathPara') {
                $math = $this->ommlMath($child, $child->localName === 'oMathPara');
                if ($math instanceof AstNode) {
                    $inlines[] = $math;
                }
                continue;
            }
            if (in_array($child->localName, ['ins', 'del', 'moveFrom', 'moveTo'], true)) {
                $span = $this->trackedChangeSpan($child);
                if ($span instanceof AstNode) {
                    $inlines[] = $span;
                }
            }
        }

        return $this->mergeAdjacentText($inlines);
    }

    /**
     * @return list<AstNode>
     */
    private function run(\DOMElement $run): array
    {
        $nodes = [];
        foreach ($run->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 't' || $child->localName === 'delText') {
                $text = $child->textContent;
                if ($text !== '') {
                    $nodes[] = new AstNode('text', ['text' => $text]);
                }
                continue;
            }
            if ($child->localName === 'tab') {
                $nodes[] = new AstNode('text', ['text' => "\t"]);
                continue;
            }
            if ($child->localName === 'br' || $child->localName === 'cr') {
                $nodes[] = new AstNode('linebreak');
                continue;
            }
            if ($child->localName === 'drawing') {
                $image = $this->drawingImage($child);
                if ($image instanceof AstNode) {
                    $nodes[] = $image;
                }
                continue;
            }
            if ($child->localName === 'oMath' || $child->localName === 'oMathPara') {
                $math = $this->ommlMath($child, $child->localName === 'oMathPara');
                if ($math instanceof AstNode) {
                    $nodes[] = $math;
                }
                continue;
            }
            if ($child->localName === 'footnoteReference') {
                $note = $this->noteReference($child, 'footnote');
                if ($note instanceof AstNode) {
                    $nodes[] = $note;
                }
                continue;
            }
            if ($child->localName === 'endnoteReference') {
                $note = $this->noteReference($child, 'endnote');
                if ($note instanceof AstNode) {
                    $nodes[] = $note;
                }
                continue;
            }
            if ($child->localName === 'commentReference') {
                $note = $this->noteReference($child, 'comment');
                if ($note instanceof AstNode) {
                    $nodes[] = $note;
                }
            }
        }

        $style = $this->runStyle($run);
        if (($style['strong'] ?? false) && $nodes !== []) {
            $nodes = [new AstNode('strong', [], $nodes)];
        }
        if (($style['emph'] ?? false) && $nodes !== []) {
            $nodes = [new AstNode('emph', [], $nodes)];
        }
        if (($style['underline'] ?? false) && $nodes !== []) {
            $nodes = [new AstNode('underline', [], $nodes)];
        }
        if (($style['strikeout'] ?? false) && $nodes !== []) {
            $nodes = [new AstNode('strikeout', [], $nodes)];
        }
        if (($style['smallCaps'] ?? false) && $nodes !== []) {
            $nodes = [new AstNode('small_caps', [], $nodes)];
        }
        if (($style['superscript'] ?? false) && $nodes !== []) {
            $nodes = [new AstNode('superscript', [], $nodes)];
        }
        if (($style['subscript'] ?? false) && $nodes !== []) {
            $nodes = [new AstNode('subscript', [], $nodes)];
        }
        $styleId = (string) ($style['styleId'] ?? '');
        if ($styleId !== '' && $nodes !== []) {
            $attrs = $this->styleNodeAttrs($styleId, 'character');
            if ($attrs !== []) {
                $nodes = [new AstNode('span', $attrs, $nodes)];
            }
        }

        return $nodes;
    }

    private function trackedChangeSpan(\DOMElement $change): ?AstNode
    {
        $children = $this->inlineChildren($change);
        if ($children === []) {
            return null;
        }

        $attributes = array_filter([
            'author' => $this->attr($change, self::W_NS, 'author'),
            'date' => $this->attr($change, self::W_NS, 'date'),
        ], static fn (string $value): bool => $value !== '');

        $deletion = in_array($change->localName, ['del', 'moveFrom'], true);
        $classes = [$deletion ? 'deletion' : 'insertion'];
        if ($change->localName === 'moveFrom') {
            $classes[] = 'move-from';
        } elseif ($change->localName === 'moveTo') {
            $classes[] = 'move-to';
        }

        return new AstNode('span', [
            'classes' => $classes,
            'attributes' => $attributes,
        ], $children);
    }

    private function commentRangeSpan(\DOMElement $range): ?AstNode
    {
        $id = $this->attr($range, self::W_NS, 'id');
        if ($id === '') {
            return null;
        }

        $comment = $this->comments[$id] ?? [];
        $attributes = ['id' => $id];
        if (is_array($comment)) {
            foreach (['author', 'date'] as $key) {
                $value = (string) ($comment[$key] ?? '');
                if ($value !== '') {
                    $attributes[$key] = $value;
                }
            }
        }

        return new AstNode('span', [
            'classes' => [$range->localName === 'commentRangeStart' ? 'comment-start' : 'comment-end'],
            'attributes' => $attributes,
        ]);
    }

    private function noteReference(\DOMElement $reference, string $kind): ?AstNode
    {
        $id = $this->attr($reference, self::W_NS, 'id');
        if ($id === '') {
            return null;
        }

        $attrs = [
            'id' => $id,
            'noteType' => $kind,
        ];

        if ($kind === 'footnote') {
            $children = $this->footnotes[$id] ?? [];
        } elseif ($kind === 'endnote') {
            $children = $this->endnotes[$id] ?? [];
        } else {
            $comment = $this->comments[$id] ?? null;
            if (!is_array($comment)) {
                return null;
            }
            $attrs['author'] = $comment['author'];
            $attrs['date'] = $comment['date'];
            $children = $comment['children'];
        }

        if ($children === []) {
            return null;
        }

        return new AstNode('note', $attrs, $children);
    }

    private function hyperlink(\DOMElement $hyperlink): ?AstNode
    {
        $inlines = [];
        foreach ($hyperlink->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === 'r') {
                array_push($inlines, ...$this->run($child));
            }
        }
        if ($inlines === []) {
            return null;
        }
        $rid = $this->attr($hyperlink, self::R_NS, 'id');
        $url = $rid !== '' ? ($this->relationships[$rid]['target'] ?? '') : '';
        if ($url === '') {
            $anchor = $this->attr($hyperlink, self::W_NS, 'anchor');
            $url = $anchor !== '' ? '#' . $anchor : '';
        }

        return new AstNode('link', ['url' => $url, 'title' => ''], $inlines);
    }

    private function simpleField(\DOMElement $field): ?AstNode
    {
        $inlines = $this->inlineChildren($field);
        if ($inlines === []) {
            return null;
        }

        $anchor = $this->fieldAnchor($this->attr($field, self::W_NS, 'instr'));
        if ($anchor === '') {
            return new AstNode('span', [
                'classes' => ['docx-field'],
                'attributes' => ['data-docx-field-instruction' => trim($this->attr($field, self::W_NS, 'instr'))],
            ], $inlines);
        }

        return new AstNode('link', [
            'url' => '#' . $anchor,
            'title' => '',
            'attributes' => ['data-docx-field' => trim($this->attr($field, self::W_NS, 'instr'))],
        ], $inlines);
    }

    private function fieldAnchor(string $instruction): string
    {
        $instruction = trim(preg_replace('/\s+/u', ' ', $instruction) ?? $instruction);
        if ($instruction === '') {
            return '';
        }

        if (preg_match('/\bHYPERLINK\s+\\\\l\s+"?([^"\\\\\s]+)"?/i', $instruction, $match) === 1) {
            return $match[1];
        }

        if (preg_match('/\b(?:REF|PAGEREF|NOTEREF)\s+"?([^"\\\\\s]+)"?/i', $instruction, $match) === 1) {
            return $match[1];
        }

        return '';
    }

    private function bookmarkRawInline(\DOMElement $bookmark): ?AstNode
    {
        $id = $this->attr($bookmark, self::W_NS, 'id');
        if ($id === '') {
            return null;
        }

        if ($bookmark->localName === 'bookmarkStart') {
            $name = $this->attr($bookmark, self::W_NS, 'name');
            if ($name === '') {
                return null;
            }

            return new AstNode('raw_inline', [
                'format' => 'openxml',
                'text' => '<w:bookmarkStart w:id="' . $this->xmlAttr($id) . '" w:name="' . $this->xmlAttr($name) . '"/>',
            ]);
        }

        return new AstNode('raw_inline', [
            'format' => 'openxml',
            'text' => '<w:bookmarkEnd w:id="' . $this->xmlAttr($id) . '"/>',
        ]);
    }

    private function ommlMath(\DOMElement $math, bool $display): ?AstNode
    {
        $text = trim($this->ommlTex($math));
        if ($text === '') {
            $text = trim(preg_replace('/\s+/u', ' ', $math->textContent) ?? $math->textContent);
        }
        if ($text === '') {
            return null;
        }

        return new AstNode('math', [
            'display' => $display,
            'text' => $text,
        ]);
    }

    private function ommlTex(\DOMNode $node): string
    {
        if ($node instanceof \DOMText || $node instanceof \DOMCdataSection) {
            return $node->nodeValue;
        }
        if (!$node instanceof \DOMElement) {
            return '';
        }

        return match ($node->localName) {
            'oMath', 'oMathPara', 'r', 'e', 'num', 'den', 'sup', 'sub', 'deg', 'fName', 'lim' => $this->ommlChildrenTex($node),
            't' => $node->textContent,
            'f' => '\\frac{' . $this->ommlFirstChildTex($node, 'num') . '}{' . $this->ommlFirstChildTex($node, 'den') . '}',
            'sSup' => $this->ommlFirstChildTex($node, 'e') . '^{' . $this->ommlFirstChildTex($node, 'sup') . '}',
            'sSub' => $this->ommlFirstChildTex($node, 'e') . '_{' . $this->ommlFirstChildTex($node, 'sub') . '}',
            'sSubSup' => $this->ommlFirstChildTex($node, 'e') . '_{' . $this->ommlFirstChildTex($node, 'sub') . '}^{' . $this->ommlFirstChildTex($node, 'sup') . '}',
            'rad' => $this->ommlRadicalTex($node),
            'nary' => $this->ommlNaryTex($node),
            'd' => $this->ommlDelimiterTex($node),
            'func' => $this->ommlChildrenTex($node),
            'bar', 'box', 'groupChr', 'limLow', 'limUpp', 'phant', 'borderBox' => $this->ommlChildrenTex($node),
            'brk' => ' ',
            default => str_ends_with($node->localName, 'Pr') || in_array($node->localName, ['ctrlPr', 'argPr', 'rPr'], true)
                ? ''
                : $this->ommlChildrenTex($node),
        };
    }

    private function ommlChildrenTex(\DOMNode $node): string
    {
        $text = '';
        foreach ($node->childNodes as $child) {
            $text .= $this->ommlTex($child);
        }

        return $text;
    }

    private function ommlFirstChildTex(\DOMElement $node, string $localName): string
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                return $this->ommlChildrenTex($child);
            }
        }

        return '';
    }

    private function ommlRadicalTex(\DOMElement $node): string
    {
        $base = $this->ommlFirstChildTex($node, 'e');
        $degree = $this->ommlFirstChildTex($node, 'deg');

        return $degree === '' ? '\\sqrt{' . $base . '}' : '\\sqrt[' . $degree . ']{' . $base . '}';
    }

    private function ommlNaryTex(\DOMElement $node): string
    {
        $operator = '';
        foreach ($node->getElementsByTagNameNS(self::M_NS, 'chr') as $chr) {
            if ($chr instanceof \DOMElement) {
                $operator = $this->attr($chr, self::M_NS, 'val');
                break;
            }
        }
        $operator = match ($operator) {
            "\u{2211}" => '\\sum',
            "\u{222B}" => '\\int',
            "\u{220F}" => '\\prod',
            '' => '\\sum',
            default => $operator,
        };

        $sub = $this->ommlFirstChildTex($node, 'sub');
        $sup = $this->ommlFirstChildTex($node, 'sup');
        $expr = $this->ommlFirstChildTex($node, 'e');

        return $operator
            . ($sub === '' ? '' : '_{' . $sub . '}')
            . ($sup === '' ? '' : '^{' . $sup . '}')
            . ($expr === '' ? '' : ' ' . $expr);
    }

    private function ommlDelimiterTex(\DOMElement $node): string
    {
        $open = '(';
        $close = ')';
        foreach ($node->getElementsByTagNameNS(self::M_NS, 'begChr') as $begChr) {
            if ($begChr instanceof \DOMElement && $this->attr($begChr, self::M_NS, 'val') !== '') {
                $open = $this->attr($begChr, self::M_NS, 'val');
                break;
            }
        }
        foreach ($node->getElementsByTagNameNS(self::M_NS, 'endChr') as $endChr) {
            if ($endChr instanceof \DOMElement && $this->attr($endChr, self::M_NS, 'val') !== '') {
                $close = $this->attr($endChr, self::M_NS, 'val');
                break;
            }
        }

        return $open . $this->ommlFirstChildTex($node, 'e') . $close;
    }

    private function drawingImage(\DOMElement $drawing): ?AstNode
    {
        foreach ($drawing->getElementsByTagNameNS(self::A_NS, 'blip') as $blip) {
            if (!$blip instanceof \DOMElement) {
                continue;
            }
            $rid = $this->attr($blip, self::R_NS, 'embed');
            if ($rid === '') {
                $rid = $this->attr($blip, self::R_NS, 'link');
            }
            $target = $rid !== '' ? ($this->relationships[$rid]['target'] ?? '') : '';
            if ($target === '') {
                continue;
            }
            $url = $this->relationships[$rid]['mode'] === 'External' ? $target : $this->normalizeWordTarget($target);
            if ($url === '') {
                continue;
            }

            $attrs = ['url' => $url, 'title' => '', 'alt' => ''];
            $sourceAttributes = [
                'data-docx-image-relationship-id' => $rid,
            ];

            foreach ($drawing->getElementsByTagNameNS(self::WP_NS, 'docPr') as $docPr) {
                if (!$docPr instanceof \DOMElement) {
                    continue;
                }
                $description = $docPr->getAttribute('descr');
                if ($description !== '') {
                    $attrs['alt'] = $description;
                }
                $title = $docPr->getAttribute('title');
                if ($title !== '') {
                    $attrs['title'] = $title;
                }
                $name = $docPr->getAttribute('name');
                if ($name !== '') {
                    $sourceAttributes['data-docx-image-name'] = $name;
                }
                $id = $docPr->getAttribute('id');
                if ($id !== '') {
                    $sourceAttributes['data-docx-image-id'] = $id;
                }
                break;
            }

            foreach ($drawing->getElementsByTagNameNS(self::WP_NS, 'extent') as $extent) {
                if (!$extent instanceof \DOMElement) {
                    continue;
                }
                $width = $this->emuCssDimension($extent->getAttribute('cx'));
                if ($width !== '') {
                    $attrs['width'] = $width;
                    $sourceAttributes['width'] = $width;
                }
                $height = $this->emuCssDimension($extent->getAttribute('cy'));
                if ($height !== '') {
                    $attrs['height'] = $height;
                    $sourceAttributes['height'] = $height;
                }
                break;
            }

            $attrs['attributes'] = $sourceAttributes;

            return new AstNode('image', $attrs);
        }

        return null;
    }

    private function table(\DOMElement $table): AstNode
    {
        $rowSpecs = [];
        foreach ($table->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === 'tr') {
                $cells = [];
                $column = 0;
                foreach ($child->childNodes as $cell) {
                    if (!$cell instanceof \DOMElement || $cell->localName !== 'tc') {
                        continue;
                    }
                    $colspan = $this->gridSpan($cell);
                    $cells[] = [
                        'element' => $cell,
                        'column' => $column,
                        'colspan' => $colspan,
                        'vMerge' => $this->verticalMerge($cell),
                    ];
                    $column += $colspan;
                }
                $rowSpecs[] = $cells;
            }
        }

        $rowspans = [];
        $skip = [];
        $active = [];
        foreach ($rowSpecs as $rowIndex => $cells) {
            foreach ($cells as $cellIndex => $cell) {
                $key = $rowIndex . ':' . $cellIndex;
                $rowspans[$key] = 1;
                $coveredColumns = range((int) $cell['column'], (int) $cell['column'] + (int) $cell['colspan'] - 1);

                if ($cell['vMerge'] === 'continue') {
                    $owners = [];
                    foreach ($coveredColumns as $column) {
                        if (isset($active[$column])) {
                            $owners[$active[$column]] = true;
                        }
                    }
                    if ($owners !== []) {
                        foreach (array_keys($owners) as $owner) {
                            $rowspans[$owner] = ($rowspans[$owner] ?? 1) + 1;
                        }
                        $skip[$key] = true;
                        continue;
                    }
                }

                foreach ($coveredColumns as $column) {
                    unset($active[$column]);
                }

                if ($cell['vMerge'] === 'restart') {
                    foreach ($coveredColumns as $column) {
                        $active[$column] = $key;
                    }
                }
            }
        }

        $rows = [];
        foreach ($rowSpecs as $rowIndex => $cells) {
            $rowCells = [];
            foreach ($cells as $cellIndex => $cell) {
                $key = $rowIndex . ':' . $cellIndex;
                if (isset($skip[$key])) {
                    continue;
                }
                $rowCells[] = $this->tableCell($cell['element'], (int) $cell['colspan'], (int) ($rowspans[$key] ?? 1), (string) $cell['vMerge']);
            }
            $rows[] = new AstNode('table_row', [], $rowCells);
        }

        return new AstNode('table', $this->tableAttributes($table), [
            new AstNode('table_head'),
            new AstNode('table_body', [], $rows),
        ]);
    }

    private function tableRow(\DOMElement $row): AstNode
    {
        $cells = [];
        foreach ($row->childNodes as $cell) {
            if (!$cell instanceof \DOMElement || $cell->localName !== 'tc') {
                continue;
            }
            $cells[] = $this->tableCell($cell);
        }

        return new AstNode('table_row', [], $cells);
    }

    private function tableCell(\DOMElement $cell, ?int $colspan = null, int $rowspan = 1, string $verticalMerge = ''): AstNode
    {
        $blocks = [];
        foreach ($cell->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === 'p') {
                $paragraph = $this->paragraph($child);
                if ($paragraph instanceof AstNode) {
                    $blocks[] = $paragraph;
                }
            } elseif ($child instanceof \DOMElement && $child->localName === 'tbl') {
                $blocks[] = $this->table($child);
            }
        }
        $text = trim(implode(' ', array_map(fn (AstNode $block): string => $this->nodeText($block), $blocks)));

        $attrs = [
            'text' => $text,
            'colspan' => $colspan ?? $this->gridSpan($cell),
            'rowspan' => max(1, $rowspan),
        ];
        $htmlAttributes = $this->tableCellHtmlAttributes($cell, $verticalMerge);
        if ($htmlAttributes !== []) {
            $attrs['htmlAttributes'] = $htmlAttributes;
        }

        return new AstNode('table_cell', $attrs, $blocks);
    }

    private function gridSpan(\DOMElement $cell): int
    {
        foreach ($cell->getElementsByTagNameNS(self::W_NS, 'gridSpan') as $gridSpan) {
            if ($gridSpan instanceof \DOMElement) {
                return max(1, (int) ($this->attr($gridSpan, self::W_NS, 'val') ?: '1'));
            }
        }

        return 1;
    }

    private function verticalMerge(\DOMElement $cell): string
    {
        $tcPr = $this->directChild($cell, 'tcPr');
        if (!$tcPr instanceof \DOMElement) {
            return '';
        }

        foreach ($tcPr->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'vMerge') {
                continue;
            }
            $value = strtolower($this->attr($child, self::W_NS, 'val'));

            return $value === '' || $value === 'continue' ? 'continue' : 'restart';
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    private function tableAttributes(\DOMElement $table): array
    {
        $tblPr = $this->directChild($table, 'tblPr');
        if (!$tblPr instanceof \DOMElement) {
            return [];
        }

        $htmlAttributes = [];
        foreach ($tblPr->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 'tblStyle') {
                $style = $this->attr($child, self::W_NS, 'val');
                if ($style !== '') {
                    $htmlAttributes['data-docx-table-style'] = $style;
                }
            }
        }

        return $htmlAttributes === [] ? [] : ['htmlAttributes' => $htmlAttributes];
    }

    /**
     * @return array<string, string>
     */
    private function tableCellHtmlAttributes(\DOMElement $cell, string $verticalMerge): array
    {
        $attrs = [];
        if ($verticalMerge !== '') {
            $attrs['data-docx-vmerge'] = $verticalMerge;
        }

        $tcPr = $this->directChild($cell, 'tcPr');
        if (!$tcPr instanceof \DOMElement) {
            return $attrs;
        }

        $styles = [];
        foreach ($tcPr->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 'shd') {
                $fill = strtoupper($this->attr($child, self::W_NS, 'fill'));
                if ($fill !== '' && $fill !== 'AUTO') {
                    $styles[] = 'background-color:#' . ltrim($fill, '#');
                }
            } elseif ($child->localName === 'vAlign') {
                $align = strtolower($this->attr($child, self::W_NS, 'val'));
                if ($align !== '') {
                    $styles[] = 'vertical-align:' . ($align === 'center' ? 'middle' : $align);
                }
            } elseif ($child->localName === 'tcW') {
                $width = $this->attr($child, self::W_NS, 'w');
                if ($width !== '') {
                    $attrs['data-docx-cell-width'] = $width;
                }
                $type = $this->attr($child, self::W_NS, 'type');
                if ($type !== '') {
                    $attrs['data-docx-cell-width-type'] = $type;
                }
            }
        }

        if ($styles !== []) {
            $attrs['style'] = implode('; ', $styles);
        }

        return $attrs;
    }

    private function headingLevel(\DOMElement $paragraph): ?int
    {
        $styleId = $this->paragraphStyleId($paragraph);
        if ($styleId !== '') {
            if (isset($this->styles[$styleId]['headingLevel'])) {
                return max(1, min(6, (int) $this->styles[$styleId]['headingLevel']));
            }
            if (preg_match('/heading\s*([1-6])|Heading([1-6])/i', $styleId, $m) === 1) {
                return (int) ($m[1] !== '' ? $m[1] : $m[2]);
            }
        }
        foreach ($paragraph->getElementsByTagNameNS(self::W_NS, 'outlineLvl') as $outline) {
            if ($outline instanceof \DOMElement) {
                return max(1, min(6, (int) ($this->attr($outline, self::W_NS, 'val') ?: '0') + 1));
            }
        }

        return null;
    }

    private function paragraphStyleId(\DOMElement $paragraph): string
    {
        foreach ($paragraph->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'pPr') {
                continue;
            }
            foreach ($child->childNodes as $prop) {
                if ($prop instanceof \DOMElement && $prop->localName === 'pStyle') {
                    return $this->attr($prop, self::W_NS, 'val');
                }
            }
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    private function styleNodeAttrs(string $styleId, string $type): array
    {
        if ($styleId === '') {
            return [];
        }
        $style = $this->styles[$styleId] ?? [];
        $name = (string) ($style['name'] ?? $styleId);
        $classes = ['docx-style-' . $this->styleClassToken($styleId)];
        if ($type !== '') {
            $classes[] = 'docx-' . $type . '-style';
        }

        $htmlAttributes = [
            'class' => implode(' ', $classes),
            'data-docx-style-id' => $styleId,
        ];
        if ($name !== '') {
            $htmlAttributes['data-docx-style-name'] = $name;
            $htmlAttributes['custom-style'] = $name;
        }
        if (isset($style['type'])) {
            $htmlAttributes['data-docx-style-type'] = (string) $style['type'];
        }

        return [
            'classes' => $classes,
            'attributes' => ['custom-style' => $name],
            'htmlAttributes' => $htmlAttributes,
        ];
    }

    private function styleClassToken(string $styleId): string
    {
        $token = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $styleId) ?? $styleId);
        $token = trim($token, '-');

        return $token !== '' ? $token : 'style';
    }

    /**
     * @return array{numId: string, level: int}|null
     */
    private function paragraphNumbering(\DOMElement $paragraph): ?array
    {
        foreach ($paragraph->getElementsByTagNameNS(self::W_NS, 'numPr') as $numPr) {
            if (!$numPr instanceof \DOMElement) {
                continue;
            }
            $numId = '';
            $level = 1;
            foreach ($numPr->childNodes as $child) {
                if ($child instanceof \DOMElement && $child->localName === 'numId') {
                    $numId = $this->attr($child, self::W_NS, 'val');
                } elseif ($child instanceof \DOMElement && $child->localName === 'ilvl') {
                    $level = max(1, (int) ($this->attr($child, self::W_NS, 'val') ?: '0') + 1);
                }
            }
            if ($numId !== '') {
                return ['numId' => $numId, 'level' => $level];
            }
        }

        return null;
    }

    /**
     * @return array{ordered: bool, attrs: array<string, mixed>}
     */
    private function numberingListAttributes(string $numId, int $level): array
    {
        $levels = $this->numbering[$numId] ?? [];
        $style = $levels[$level] ?? $levels[1] ?? null;
        if (!is_array($style) || !($style['ordered'] ?? false)) {
            return ['ordered' => false, 'attrs' => []];
        }

        return [
            'ordered' => true,
            'attrs' => [
                'start' => max(1, (int) ($style['start'] ?? 1)),
                'style' => $style['style'] ?? 'decimal',
                'delimiter' => $style['delimiter'] ?? 'period',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runStyle(\DOMElement $run): array
    {
        $style = [];
        foreach ($run->getElementsByTagNameNS(self::W_NS, 'rPr') as $rPr) {
            if (!$rPr instanceof \DOMElement || $rPr->parentNode !== $run) {
                continue;
            }
            foreach ($rPr->childNodes as $prop) {
                if (!$prop instanceof \DOMElement) {
                    continue;
                }
                if ($prop->localName === 'b' && $this->truthyOnOff($prop)) {
                    $style['strong'] = true;
                } elseif ($prop->localName === 'i' && $this->truthyOnOff($prop)) {
                    $style['emph'] = true;
                } elseif ($prop->localName === 'u' && $this->truthyUnderline($prop)) {
                    $style['underline'] = true;
                } elseif (($prop->localName === 'strike' || $prop->localName === 'dstrike') && $this->truthyOnOff($prop)) {
                    $style['strikeout'] = true;
                } elseif ($prop->localName === 'smallCaps' && $this->truthyOnOff($prop)) {
                    $style['smallCaps'] = true;
                } elseif ($prop->localName === 'vertAlign') {
                    $value = strtolower($this->attr($prop, self::W_NS, 'val'));
                    if ($value === 'superscript') {
                        $style['superscript'] = true;
                    } elseif ($value === 'subscript') {
                        $style['subscript'] = true;
                    }
                } elseif ($prop->localName === 'rStyle') {
                    $styleId = $this->attr($prop, self::W_NS, 'val');
                    $style = array_replace($style, $this->styles[$styleId] ?? []);
                    if ($styleId !== '') {
                        $style['styleId'] = $styleId;
                    }
                }
            }
        }

        return $style;
    }

    private function truthyOnOff(\DOMElement $element): bool
    {
        $value = strtolower($this->attr($element, self::W_NS, 'val'));
        return !in_array($value, ['0', 'false', 'off', 'no'], true);
    }

    private function truthyUnderline(\DOMElement $element): bool
    {
        return strtolower($this->attr($element, self::W_NS, 'val')) !== 'none'
            && $this->truthyOnOff($element);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function styles(\DOMDocument $dom): array
    {
        $styles = [];
        $basedOn = [];
        foreach ($dom->getElementsByTagNameNS(self::W_NS, 'style') as $style) {
            if (!$style instanceof \DOMElement) {
                continue;
            }
            $styleId = $this->attr($style, self::W_NS, 'styleId');
            if ($styleId === '') {
                continue;
            }
            $entry = [
                'styleId' => $styleId,
                'type' => $this->attr($style, self::W_NS, 'type'),
            ];
            foreach ($style->childNodes as $child) {
                if (!$child instanceof \DOMElement) {
                    continue;
                }
                if ($child->localName === 'name') {
                    $entry['name'] = $this->attr($child, self::W_NS, 'val');
                } elseif ($child->localName === 'basedOn') {
                    $parent = $this->attr($child, self::W_NS, 'val');
                    if ($parent !== '') {
                        $basedOn[$styleId] = $parent;
                    }
                } elseif ($child->localName === 'pPr') {
                    foreach ($child->getElementsByTagNameNS(self::W_NS, 'outlineLvl') as $outline) {
                        if ($outline instanceof \DOMElement) {
                            $entry['headingLevel'] = max(1, min(6, (int) ($this->attr($outline, self::W_NS, 'val') ?: '0') + 1));
                        }
                    }
                } elseif ($child->localName === 'rPr') {
                    foreach ($child->childNodes as $prop) {
                        if (!$prop instanceof \DOMElement) {
                            continue;
                        }
                        if ($prop->localName === 'b' && $this->truthyOnOff($prop)) {
                            $entry['strong'] = true;
                        } elseif ($prop->localName === 'i' && $this->truthyOnOff($prop)) {
                            $entry['emph'] = true;
                        } elseif ($prop->localName === 'u' && $this->truthyUnderline($prop)) {
                            $entry['underline'] = true;
                        } elseif (($prop->localName === 'strike' || $prop->localName === 'dstrike') && $this->truthyOnOff($prop)) {
                            $entry['strikeout'] = true;
                        } elseif ($prop->localName === 'smallCaps' && $this->truthyOnOff($prop)) {
                            $entry['smallCaps'] = true;
                        } elseif ($prop->localName === 'vertAlign') {
                            $value = strtolower($this->attr($prop, self::W_NS, 'val'));
                            if ($value === 'superscript') {
                                $entry['superscript'] = true;
                            } elseif ($value === 'subscript') {
                                $entry['subscript'] = true;
                            }
                        }
                    }
                }
            }
            if (!isset($entry['name']) || (string) $entry['name'] === '') {
                $entry['name'] = $styleId;
            }
            if (!isset($entry['headingLevel']) && preg_match('/heading\s*([1-6])|Heading([1-6])/i', $styleId, $m) === 1) {
                $entry['headingLevel'] = (int) ($m[1] !== '' ? $m[1] : $m[2]);
            }
            $styles[$styleId] = $entry;
        }

        foreach ($basedOn as $styleId => $parentId) {
            if (!isset($styles[$styleId], $styles[$parentId])) {
                continue;
            }
            $styles[$styleId] = array_replace($styles[$parentId], $styles[$styleId]);
            $styles[$styleId]['styleId'] = $styleId;
        }

        return $styles;
    }

    /**
     * @return array<string, array<int, array{ordered: bool, style?: string, delimiter?: string, start?: int}>>
     */
    private function numbering(\DOMDocument $dom): array
    {
        $abstractLevels = [];
        foreach ($dom->getElementsByTagNameNS(self::W_NS, 'abstractNum') as $abstractNum) {
            if (!$abstractNum instanceof \DOMElement) {
                continue;
            }
            $abstractId = $this->attr($abstractNum, self::W_NS, 'abstractNumId');
            if ($abstractId === '') {
                continue;
            }

            foreach ($abstractNum->childNodes as $level) {
                if (!$level instanceof \DOMElement || $level->localName !== 'lvl') {
                    continue;
                }
                $levelIndex = max(1, (int) ($this->attr($level, self::W_NS, 'ilvl') ?: '0') + 1);
                $abstractLevels[$abstractId][$levelIndex] = $this->numberingLevel($level);
            }
        }

        $numbering = [];
        foreach ($dom->getElementsByTagNameNS(self::W_NS, 'num') as $num) {
            if (!$num instanceof \DOMElement) {
                continue;
            }
            $numId = $this->attr($num, self::W_NS, 'numId');
            $abstractId = '';
            $overrides = [];
            foreach ($num->childNodes as $child) {
                if (!$child instanceof \DOMElement) {
                    continue;
                }
                if ($child->localName === 'abstractNumId') {
                    $abstractId = $this->attr($child, self::W_NS, 'val');
                    continue;
                }
                if ($child->localName !== 'lvlOverride') {
                    continue;
                }
                $levelIndex = max(1, (int) ($this->attr($child, self::W_NS, 'ilvl') ?: '0') + 1);
                foreach ($child->childNodes as $override) {
                    if (!$override instanceof \DOMElement) {
                        continue;
                    }
                    if ($override->localName === 'startOverride') {
                        $start = $this->attr($override, self::W_NS, 'val');
                        if ($start !== '' && is_numeric($start)) {
                            $overrides[$levelIndex]['start'] = max(1, (int) $start);
                        }
                    } elseif ($override->localName === 'lvl') {
                        $overrides[$levelIndex] = array_replace($overrides[$levelIndex] ?? [], $this->numberingLevel($override));
                    }
                }
            }
            if ($numId !== '') {
                $levels = $abstractLevels[$abstractId] ?? [1 => ['ordered' => false]];
                foreach ($overrides as $level => $override) {
                    $levels[$level] = array_replace($levels[$level] ?? ['ordered' => false], $override);
                }
                $numbering[$numId] = $levels;
            }
        }

        return $numbering;
    }

    /**
     * @return array{ordered: bool, style?: string, delimiter?: string, start?: int}
     */
    private function numberingLevel(\DOMElement $level): array
    {
        $format = 'bullet';
        $text = '';
        $start = null;
        foreach ($level->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 'numFmt') {
                $format = $this->attr($child, self::W_NS, 'val') ?: 'bullet';
            } elseif ($child->localName === 'lvlText') {
                $text = $this->attr($child, self::W_NS, 'val');
            } elseif ($child->localName === 'start') {
                $value = $this->attr($child, self::W_NS, 'val');
                if ($value !== '' && is_numeric($value)) {
                    $start = max(1, (int) $value);
                }
            }
        }

        if ($format === 'bullet') {
            return ['ordered' => false];
        }

        $entry = [
            'ordered' => true,
            'style' => $this->docxOrderedListStyle($format),
            'delimiter' => $this->docxOrderedListDelimiter($text),
        ];
        if ($start !== null) {
            $entry['start'] = $start;
        }

        return $entry;
    }

    private function docxOrderedListStyle(string $format): string
    {
        return match ($format) {
            'lowerLetter' => 'lower_alpha',
            'upperLetter' => 'upper_alpha',
            'lowerRoman' => 'lower_roman',
            'upperRoman' => 'upper_roman',
            default => 'decimal',
        };
    }

    private function docxOrderedListDelimiter(string $levelText): string
    {
        $levelText = trim($levelText);
        if (preg_match('/^\(%\d+\)$/', $levelText) === 1) {
            return 'two_parens';
        }
        if (preg_match('/%\d+\)$/', $levelText) === 1) {
            return 'one_paren';
        }
        if (preg_match('/%\d+\.$/', $levelText) === 1) {
            return 'period';
        }

        return 'default';
    }

    /**
     * @return array<string, array{target: string, type: string, mode: string}>
     */
    private function relationships(\DOMDocument $dom): array
    {
        $rels = [];
        foreach ($dom->getElementsByTagNameNS(self::REL_NS, 'Relationship') as $rel) {
            if (!$rel instanceof \DOMElement) {
                continue;
            }
            $id = $rel->getAttribute('Id');
            if ($id === '') {
                continue;
            }
            $rels[$id] = [
                'target' => $rel->getAttribute('Target'),
                'type' => $rel->getAttribute('Type'),
                'mode' => $rel->getAttribute('TargetMode'),
            ];
        }

        return $rels;
    }

    /**
     * @return array<string, list<AstNode>>
     */
    private function notes(\DOMDocument $dom, string $localName): array
    {
        $notes = [];
        foreach ($dom->getElementsByTagNameNS(self::W_NS, $localName) as $note) {
            if (!$note instanceof \DOMElement) {
                continue;
            }
            $id = $this->attr($note, self::W_NS, 'id');
            if ($id === '' || (int) $id < 1) {
                continue;
            }
            $children = $this->bodyBlocks($note);
            if ($children !== []) {
                $notes[$id] = $children;
            }
        }

        return $notes;
    }

    /**
     * @return array<string, array{author: string, date: string, children: list<AstNode>, text: string}>
     */
    private function comments(\DOMDocument $dom): array
    {
        $comments = [];
        foreach ($dom->getElementsByTagNameNS(self::W_NS, 'comment') as $comment) {
            if (!$comment instanceof \DOMElement) {
                continue;
            }
            $id = $this->attr($comment, self::W_NS, 'id');
            if ($id === '') {
                continue;
            }
            $children = $this->bodyBlocks($comment);
            if ($children === []) {
                continue;
            }
            $comments[$id] = [
                'author' => $this->attr($comment, self::W_NS, 'author'),
                'date' => $this->attr($comment, self::W_NS, 'date'),
                'children' => $children,
                'text' => trim(implode(' ', array_map(fn (AstNode $block): string => $this->nodeText($block), $children))),
            ];
        }

        return $comments;
    }

    /**
     * @return array<string, mixed>
     */
    private function coreProperties(\DOMDocument $dom): array
    {
        $map = [
            'title' => 'title',
            'creator' => 'author',
            'created' => 'date',
            'description' => 'description',
            'subject' => 'subject',
        ];
        $meta = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }
            $key = $map[$element->localName] ?? '';
            if ($key === '') {
                continue;
            }
            $text = trim(preg_replace('/\s+/', ' ', $element->textContent) ?? $element->textContent);
            if ($text === '') {
                continue;
            }
            $meta[$key] = $text;
            if ($key === 'title') {
                $meta['titleInlines'] = [new AstNode('text', ['text' => $text])];
            }
        }

        return $meta;
    }

    private function loadXml(string $xml, string $label): \DOMDocument
    {
        if (!class_exists(\DOMDocument::class)) {
            throw new \RuntimeException($label . ' needs DOMDocument, which is unavailable in this runtime.');
        }
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$ok) {
            throw new \InvalidArgumentException($label . ' is not valid XML.');
        }

        return $dom;
    }

    private function firstElementByLocalName(\DOMDocument $dom, string $localName): ?\DOMElement
    {
        foreach ($dom->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && $element->localName === $localName) {
                return $element;
            }
        }

        return null;
    }

    private function directChild(\DOMElement $element, string $localName): ?\DOMElement
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    private function attr(\DOMElement $element, string $namespace, string $name): string
    {
        $value = $element->getAttributeNS($namespace, $name);
        if ($value !== '') {
            return $value;
        }
        foreach ($element->attributes ?? [] as $attribute) {
            if ($attribute instanceof \DOMAttr && $attribute->localName === $name) {
                return $attribute->value;
            }
        }

        return '';
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function plainText(array $inlines): string
    {
        $text = '';
        foreach ($inlines as $inline) {
            $text .= $this->nodeText($inline);
        }

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    private function nodeText(AstNode $node): string
    {
        if (isset($node->attrs['text'])) {
            return (string) $node->attrs['text'];
        }
        if ($node->type === 'linebreak') {
            return ' ';
        }
        $text = '';
        foreach ($node->children as $child) {
            $text .= $this->nodeText($child);
        }

        return $text;
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function mergeAdjacentText(array $nodes): array
    {
        $merged = [];
        foreach ($nodes as $node) {
            $last = $merged[array_key_last($merged)] ?? null;
            if ($node->type === 'text' && $last instanceof AstNode && $last->type === 'text') {
                $merged[array_key_last($merged)] = new AstNode('text', [
                    'text' => (string) $last->attr('text', '') . (string) $node->attr('text', ''),
                ]);
                continue;
            }
            $merged[] = $node;
        }

        return $merged;
    }

    private function normalizeWordTarget(string $target): string
    {
        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $target)) {
            return $target;
        }
        $target = str_replace('\\', '/', $target);
        if (str_starts_with($target, '/')) {
            $candidate = ltrim($target, '/');
        } elseif (str_starts_with($target, 'word/')) {
            $candidate = $target;
        } else {
            $candidate = 'word/' . ltrim($target, '/');
        }

        try {
            return ZipOpcPackage::normalizePathStrict($candidate);
        } catch (\InvalidArgumentException) {
            return '';
        }
    }

    private function emuCssDimension(string $value): string
    {
        if ($value === '' || !is_numeric($value) || (float) $value <= 0.0) {
            return '';
        }

        $inches = (float) $value / 914400.0;
        $formatted = rtrim(rtrim(number_format($inches, 4, '.', ''), '0'), '.');

        return ($formatted === '' ? '0' : $formatted) . 'in';
    }

    private function xmlAttr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
