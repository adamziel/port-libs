<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class Html5DomFragment
{
    /** @var list<array<string, mixed>> */
    private array $nodes;

    /** @var list<array<string, mixed>> */
    private array $diagnostics;

    /**
     * @param list<array<string, mixed>> $nodes
     * @param list<array<string, mixed>> $diagnostics
     */
    private function __construct(
        private readonly string $mode,
        array $nodes,
        array $diagnostics,
    ) {
        $this->nodes = $nodes;
        $this->diagnostics = $diagnostics;
    }

    public static function fromHtml(string $html): self
    {
        $diagnostics = [];
        $dom = self::loadHtmlDocument($html, $diagnostics);
        $wrapper = self::htmlWrapper($dom);
        if (!$wrapper instanceof \DOMElement) {
            throw new \InvalidArgumentException('Unable to parse HTML fragment wrapper');
        }

        return new self('html', self::normalizeChildren($wrapper, 'html', $diagnostics), $diagnostics);
    }

    public static function fromXml(string $xml): self
    {
        if (preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i', $xml) === 1) {
            throw new \InvalidArgumentException('XML fragments with DTD or entity declarations are not supported');
        }

        $diagnostics = [];
        $dom = self::loadXmlDocument($xml, $diagnostics);
        $wrapper = $dom->documentElement;
        if (!$wrapper instanceof \DOMElement || $wrapper->tagName !== 'pandoc-fragment-root') {
            throw new \InvalidArgumentException('Unable to parse XML fragment wrapper');
        }

        return new self('xml', self::normalizeChildren($wrapper, 'xml', $diagnostics), $diagnostics);
    }

    public function serialize(): string
    {
        return $this->serializeNodes($this->nodes);
    }

    public function toRawHtmlAst(array $attrs = []): AstNode
    {
        return new AstNode('raw_html', array_merge($attrs, [
            'format' => $this->mode === 'xml' ? 'xml' : 'html',
            'html' => $this->serialize(),
            'diagnostics' => $this->diagnostics,
        ]));
    }

    public function textContent(): string
    {
        return $this->textFromNodes($this->nodes);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function nodes(): array
    {
        return $this->nodes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    /**
     * @return list<string>
     */
    public function diagnosticCodes(): array
    {
        return array_map(
            static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''),
            $this->diagnostics
        );
    }

    /**
     * @return array{
     *     mode:string,
     *     topLevelNodes:int,
     *     elements:int,
     *     textNodes:int,
     *     comments:int,
     *     diagnostics:int,
     *     elementNames:list<string>,
     *     blockedTags:list<string>,
     *     filteredAttributes:list<string>
     * }
     */
    public function summary(): array
    {
        $counts = [
            'elements' => 0,
            'textNodes' => 0,
            'comments' => 0,
            'elementNames' => [],
        ];
        $this->summarizeNodes($this->nodes, $counts);

        $blockedTags = [];
        $filteredAttributes = [];
        foreach ($this->diagnostics as $diagnostic) {
            if (($diagnostic['code'] ?? '') === 'blocked-tag') {
                $blockedTags[] = (string) ($diagnostic['tag'] ?? '');
            }
            if (in_array(($diagnostic['code'] ?? ''), ['unsafe-attribute', 'unsafe-url'], true)) {
                $filteredAttributes[] = (string) ($diagnostic['attribute'] ?? '');
            }
        }

        $elementNames = array_keys($counts['elementNames']);
        sort($elementNames);
        $blockedTags = array_values(array_unique(array_filter($blockedTags, static fn (string $tag): bool => $tag !== '')));
        $filteredAttributes = array_values(array_unique(array_filter($filteredAttributes, static fn (string $name): bool => $name !== '')));
        sort($blockedTags);
        sort($filteredAttributes);

        return [
            'mode' => $this->mode,
            'topLevelNodes' => count($this->nodes),
            'elements' => $counts['elements'],
            'textNodes' => $counts['textNodes'],
            'comments' => $counts['comments'],
            'diagnostics' => count($this->diagnostics),
            'elementNames' => $elementNames,
            'blockedTags' => $blockedTags,
            'filteredAttributes' => $filteredAttributes,
        ];
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function loadHtmlDocument(string $html, array &$diagnostics): \DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $flags = LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED;
        $source = '<html><body><div data-pandoc-fragment-root="1">' . $html . '</div></body></html>';
        $loaded = $dom->loadHTML($source, $flags);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        foreach ($errors as $error) {
            $message = trim($error->message);
            if ($message === '') {
                continue;
            }
            $diagnostics[] = [
                'code' => 'libxml-repair',
                'level' => $error->level,
                'message' => $message,
            ];
        }

        if (!$loaded) {
            throw new \InvalidArgumentException('Unable to parse HTML fragment');
        }

        return $dom;
    }

    private static function htmlWrapper(\DOMDocument $dom): ?\DOMElement
    {
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('//*[@data-pandoc-fragment-root="1"]');
        if (!$nodes instanceof \DOMNodeList || $nodes->length === 0) {
            return null;
        }

        $node = $nodes->item(0);

        return $node instanceof \DOMElement ? $node : null;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function loadXmlDocument(string $xml, array &$diagnostics): \DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $wrapped = '<pandoc-fragment-root>' . $xml . '</pandoc-fragment-root>';
        $loaded = $dom->loadXML($wrapped, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        foreach ($errors as $error) {
            $message = trim($error->message);
            if ($message === '') {
                continue;
            }
            $diagnostics[] = [
                'code' => 'xml-parse-error',
                'level' => $error->level,
                'message' => $message,
            ];
        }

        if (!$loaded) {
            throw new \InvalidArgumentException('Unable to parse XML fragment');
        }

        return $dom;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>
     */
    private static function normalizeChildren(\DOMNode $parent, string $mode, array &$diagnostics, ?string $foreignContext = null): array
    {
        $nodes = [];
        foreach ($parent->childNodes as $child) {
            $normalized = self::normalizeNode($child, $mode, $diagnostics, $foreignContext);
            if ($normalized === null) {
                continue;
            }
            array_push($nodes, ...$normalized);
        }

        return $nodes;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>|null
     */
    private static function normalizeNode(\DOMNode $node, string $mode, array &$diagnostics, ?string $foreignContext = null): ?array
    {
        if ($node instanceof \DOMText || $node instanceof \DOMCdataSection) {
            return [['type' => 'text', 'text' => $node->nodeValue ?? '']];
        }

        if ($node instanceof \DOMComment) {
            $text = str_replace('--', '-', $node->nodeValue ?? '');

            return [['type' => 'comment', 'text' => $text]];
        }

        if (!$node instanceof \DOMElement) {
            return null;
        }

        $rawName = self::rawElementName($node, $mode);
        $elementForeignContext = self::elementForeignContext($rawName, $mode, $foreignContext);
        $name = self::normalizedElementName($rawName, $elementForeignContext);
        if (!self::isSafeElementName($name)) {
            $diagnostics[] = [
                'code' => 'blocked-tag',
                'tag' => $name,
            ];

            return null;
        }

        if (self::isBlockedElement($name)) {
            $diagnostics[] = [
                'code' => 'blocked-tag',
                'tag' => $name,
            ];

            return null;
        }

        return [[
            'type' => 'element',
            'name' => $name,
            'attrs' => self::normalizeAttributes($node, $name, $mode, $diagnostics, $elementForeignContext),
            'children' => self::normalizeChildren($node, $mode, $diagnostics, self::childForeignContext($rawName, $elementForeignContext)),
        ]];
    }

    private static function rawElementName(\DOMElement $element, string $mode): string
    {
        if ($mode === 'xml') {
            return $element->tagName;
        }

        return strtolower($element->tagName);
    }

    private static function normalizedElementName(string $rawName, ?string $foreignContext): string
    {
        return $foreignContext !== null
            ? XmlHtmlDom::adjustHtmlForeignElementName($rawName)
            : $rawName;
    }

    private static function elementForeignContext(string $rawName, string $mode, ?string $parentForeignContext): ?string
    {
        if ($mode !== 'html') {
            return null;
        }
        if ($rawName === 'svg' || $rawName === 'math') {
            return $rawName;
        }

        return $parentForeignContext;
    }

    private static function childForeignContext(string $rawName, ?string $elementForeignContext): ?string
    {
        if ($elementForeignContext === 'svg' && $rawName === 'foreignobject') {
            return null;
        }

        return $elementForeignContext;
    }

    private static function isSafeElementName(string $name): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9:._-]*$/', $name) === 1;
    }

    private static function isBlockedElement(string $name): bool
    {
        return in_array(strtolower($name), [
            'applet',
            'base',
            'button',
            'embed',
            'form',
            'frame',
            'frameset',
            'iframe',
            'input',
            'link',
            'meta',
            'object',
            'option',
            'script',
            'select',
            'style',
            'textarea',
        ], true);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, string>
     */
    private static function normalizeAttributes(\DOMElement $element, string $tagName, string $mode, array &$diagnostics, ?string $foreignContext): array
    {
        if (!$element->hasAttributes()) {
            return [];
        }

        $attrs = [];
        foreach ($element->attributes as $attribute) {
            $name = $mode === 'xml'
                ? self::xmlAttributeName($attribute)
                : strtolower($attribute->name);
            if ($foreignContext !== null) {
                $name = XmlHtmlDom::adjustHtmlForeignAttributeName($name);
            }
            $value = str_replace("\0", '', $attribute->value);

            if (!self::isSafeAttributeName($name)) {
                $diagnostics[] = [
                    'code' => 'unsafe-attribute',
                    'tag' => $tagName,
                    'attribute' => $name,
                ];
                continue;
            }

            if (self::isBlockedAttribute($name)) {
                $diagnostics[] = [
                    'code' => 'unsafe-attribute',
                    'tag' => $tagName,
                    'attribute' => $name,
                ];
                continue;
            }

            if (self::isUrlAttribute($name) && !self::isSafeUrl($value)) {
                $diagnostics[] = [
                    'code' => 'unsafe-url',
                    'tag' => $tagName,
                    'attribute' => $name,
                ];
                continue;
            }

            $attrs[$name] = $value;
        }

        return $attrs;
    }

    private static function isSafeAttributeName(string $name): bool
    {
        return preg_match('/^(?:[A-Za-z_:][A-Za-z0-9:._-]*|aria-[A-Za-z0-9._-]+|data-[A-Za-z0-9._:-]+)$/', $name) === 1;
    }

    private static function xmlAttributeName(\DOMAttr $attribute): string
    {
        $prefix = $attribute->prefix;
        if (is_string($prefix) && $prefix !== '') {
            return $prefix . ':' . $attribute->localName;
        }

        return $attribute->name;
    }

    private static function isBlockedAttribute(string $name): bool
    {
        $lower = strtolower($name);

        return str_starts_with($lower, 'on')
            || $lower === 'style'
            || $lower === 'srcdoc'
            || $lower === 'data-pandoc-fragment-root';
    }

    private static function isUrlAttribute(string $name): bool
    {
        return in_array(strtolower($name), ['href', 'src', 'cite', 'poster', 'xlink:href', 'srcset'], true);
    }

    private static function isSafeUrl(string $value): bool
    {
        $trimmed = trim(preg_replace('/[\x00-\x20]+/', '', $value) ?? $value);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || str_starts_with($trimmed, '/')) {
            return true;
        }
        if (str_starts_with($trimmed, './') || str_starts_with($trimmed, '../') || str_starts_with($trimmed, '?')) {
            return true;
        }
        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $trimmed) !== 1) {
            return true;
        }

        $scheme = strtolower(strstr($trimmed, ':', true) ?: '');

        return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true);
    }

    /**
     * @param list<array<string, mixed>> $nodes
     */
    private function serializeNodes(array $nodes): string
    {
        $html = '';
        foreach ($nodes as $node) {
            $html .= $this->serializeNode($node);
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function serializeNode(array $node): string
    {
        $type = (string) ($node['type'] ?? '');
        if ($type === 'text') {
            return htmlspecialchars((string) ($node['text'] ?? ''), ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        if ($type === 'comment') {
            return '<!--' . str_replace('--', '-', (string) ($node['text'] ?? '')) . '-->';
        }
        if ($type !== 'element') {
            return '';
        }

        $name = (string) $node['name'];
        $attrs = $this->serializeAttributes(is_array($node['attrs'] ?? null) ? $node['attrs'] : []);
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        if ($children === []) {
            if ($this->mode === 'html' && self::isHtmlVoidElement($name)) {
                return '<' . $name . $attrs . '>';
            }
            if ($this->mode === 'xml') {
                return '<' . $name . $attrs . '/>';
            }
        }

        return '<' . $name . $attrs . '>' . $this->serializeNodes($children) . '</' . $name . '>';
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function serializeAttributes(array $attrs): string
    {
        $serialized = '';
        foreach ($attrs as $name => $value) {
            $serialized .= ' ' . $name . '="' . htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }

        return $serialized;
    }

    private static function isHtmlVoidElement(string $name): bool
    {
        return in_array(strtolower($name), [
            'area',
            'base',
            'br',
            'col',
            'embed',
            'hr',
            'img',
            'input',
            'link',
            'meta',
            'param',
            'source',
            'track',
            'wbr',
        ], true);
    }

    /**
     * @param list<array<string, mixed>> $nodes
     */
    private function textFromNodes(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            if (($node['type'] ?? '') === 'text') {
                $text .= (string) ($node['text'] ?? '');
                continue;
            }
            if (($node['type'] ?? '') === 'element' && is_array($node['children'] ?? null)) {
                $text .= $this->textFromNodes($node['children']);
            }
        }

        return $text;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @param array{elements:int, textNodes:int, comments:int, elementNames:array<string, bool>} $counts
     */
    private function summarizeNodes(array $nodes, array &$counts): void
    {
        foreach ($nodes as $node) {
            $type = (string) ($node['type'] ?? '');
            if ($type === 'text') {
                $counts['textNodes']++;
                continue;
            }
            if ($type === 'comment') {
                $counts['comments']++;
                continue;
            }
            if ($type !== 'element') {
                continue;
            }

            $counts['elements']++;
            $counts['elementNames'][(string) $node['name']] = true;
            if (is_array($node['children'] ?? null)) {
                $this->summarizeNodes($node['children'], $counts);
            }
        }
    }
}
