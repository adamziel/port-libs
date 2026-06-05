<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class Html5DomFragment
{
    /** @var array<string, true> */
    private const HTML5_BOOLEAN_ATTRIBUTES = [
        'allowfullscreen' => true,
        'async' => true,
        'autofocus' => true,
        'autoplay' => true,
        'checked' => true,
        'controls' => true,
        'default' => true,
        'defer' => true,
        'disabled' => true,
        'formnovalidate' => true,
        'hidden' => true,
        'inert' => true,
        'ismap' => true,
        'itemscope' => true,
        'loop' => true,
        'multiple' => true,
        'muted' => true,
        'nomodule' => true,
        'novalidate' => true,
        'open' => true,
        'playsinline' => true,
        'readonly' => true,
        'required' => true,
        'reversed' => true,
        'selected' => true,
    ];

    /** @var array<string, array<string, true>> */
    private const HTML5_TABLE_ALLOWED_CHILDREN = [
        'table' => [
            'caption' => true,
            'colgroup' => true,
            'script' => true,
            'tbody' => true,
            'template' => true,
            'tfoot' => true,
            'thead' => true,
            'tr' => true,
        ],
        'colgroup' => [
            'col' => true,
            'script' => true,
            'template' => true,
        ],
        'thead' => [
            'script' => true,
            'template' => true,
            'tr' => true,
        ],
        'tbody' => [
            'script' => true,
            'template' => true,
            'tr' => true,
        ],
        'tfoot' => [
            'script' => true,
            'template' => true,
            'tr' => true,
        ],
        'tr' => [
            'script' => true,
            'td' => true,
            'template' => true,
            'th' => true,
        ],
    ];

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
        private readonly ?string $baseUrl = null,
    ) {
        $this->nodes = $nodes;
        $this->diagnostics = $diagnostics;
    }

    public static function fromHtml(string $html, ?string $baseUrl = null): self
    {
        self::assertSafeHtmlSource($html, 'HTML fragment');

        $diagnostics = [];
        $dom = self::loadHtmlDocument($html, $diagnostics);
        $wrapper = self::htmlWrapper($dom);
        if (!$wrapper instanceof \DOMElement) {
            throw new \InvalidArgumentException('Unable to parse HTML fragment wrapper');
        }

        $resolvedBaseUrl = self::resolveFragmentBaseUrl($wrapper, $baseUrl, $diagnostics);

        return new self(
            'html',
            self::normalizeChildren($wrapper, 'html', $diagnostics, baseUrl: $resolvedBaseUrl),
            $diagnostics,
            $resolvedBaseUrl
        );
    }

    public static function fromXml(string $xml): self
    {
        self::assertSafeXmlSource($xml, 'XML fragment');

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
        $rawAttrs = array_merge($attrs, [
            'format' => $this->mode === 'xml' ? 'xml' : 'html',
            'html' => $this->serialize(),
            'diagnostics' => $this->diagnostics,
        ]);
        if ($this->baseUrl !== null) {
            $rawAttrs['baseUrl'] = $this->baseUrl;
        }

        return new AstNode('raw_html', $rawAttrs);
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

    public function baseUrl(): ?string
    {
        return $this->baseUrl;
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
     *     baseUrl:string|null,
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
            if (in_array(($diagnostic['code'] ?? ''), ['unsafe-attribute', 'unsafe-url', 'invalid-srcset-descriptor'], true)) {
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
            'baseUrl' => $this->baseUrl,
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
        $source = '<html><body><div data-pandoc-fragment-root="1">'
            . XmlHtmlDom::protectHtmlRcdataElements($html)
            . '</div></body></html>';
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
    private static function normalizeChildren(
        \DOMNode $parent,
        string $mode,
        array &$diagnostics,
        ?string $foreignContext = null,
        ?string $baseUrl = null
    ): array {
        $nodes = [];
        foreach ($parent->childNodes as $child) {
            $normalized = self::normalizeNode($child, $mode, $diagnostics, $foreignContext, $baseUrl);
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
    private static function normalizeNode(
        \DOMNode $node,
        string $mode,
        array &$diagnostics,
        ?string $foreignContext = null,
        ?string $baseUrl = null
    ): ?array {
        if ($node instanceof \DOMText || $node instanceof \DOMCdataSection) {
            return [['type' => 'text', 'text' => $node->nodeValue ?? '']];
        }

        if ($node instanceof \DOMComment) {
            return [['type' => 'comment', 'text' => $node->nodeValue ?? '']];
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

        if ($mode === 'html' && self::isUnwrappedElement($name)) {
            $diagnostics[] = [
                'code' => 'blocked-tag',
                'tag' => $name,
            ];

            return self::normalizeChildren(
                $node,
                $mode,
                $diagnostics,
                self::childForeignContext($node, $rawName, $elementForeignContext),
                $baseUrl
            );
        }

        if (self::isBlockedElement($name)) {
            $diagnostics[] = [
                'code' => 'blocked-tag',
                'tag' => $name,
            ];

            return null;
        }

        $attrs = self::normalizeAttributes($node, $name, $mode, $diagnostics, $elementForeignContext, $baseUrl);
        $children = self::normalizeChildren(
            $node,
            $mode,
            $diagnostics,
            self::childForeignContext($node, $rawName, $elementForeignContext),
            $baseUrl
        );
        $element = [
            'type' => 'element',
            'name' => $name,
            'attrs' => $attrs,
            'children' => $mode === 'html' && self::isHtmlVoidElement($name) ? [] : $children,
        ];

        if ($mode === 'html' && self::isHtmlVoidElement($name) && $children !== []) {
            return [$element, ...$children];
        }

        if ($mode === 'html' && $name === 'table') {
            return self::normalizeHtmlTableElement($element, $diagnostics);
        }

        return [$element];
    }

    /**
     * @param array<string, mixed> $element
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>
     */
    private static function normalizeHtmlTableElement(array $element, array &$diagnostics): array
    {
        [$fostered, $children] = self::normalizeHtmlTableChildren(
            is_array($element['children'] ?? null) ? $element['children'] : [],
            (string) ($element['name'] ?? ''),
            $diagnostics
        );
        $element['children'] = $children;

        return [...$fostered, $element];
    }

    /**
     * @param list<array<string, mixed>> $children
     * @param list<array<string, mixed>> $diagnostics
     * @return array{0:list<array<string, mixed>>, 1:list<array<string, mixed>>}
     */
    private static function normalizeHtmlTableChildren(array $children, string $context, array &$diagnostics): array
    {
        $fostered = [];
        $cleanChildren = [];

        foreach ($children as $child) {
            if (self::isFosteredHtmlTableNode($child, $context)) {
                $diagnostics[] = self::htmlTableFosterDiagnostic($child, $context);
                $fostered[] = $child;
                continue;
            }

            if (($child['type'] ?? '') === 'element' && self::isHtmlTableModelContext((string) ($child['name'] ?? ''))) {
                [$nestedFostered, $cleanChild] = self::normalizeHtmlTableElementParts($child, $diagnostics);
                array_push($fostered, ...$nestedFostered);
                $cleanChildren[] = $cleanChild;
                continue;
            }

            $cleanChildren[] = $child;
        }

        return [$fostered, $cleanChildren];
    }

    /**
     * @param array<string, mixed> $element
     * @param list<array<string, mixed>> $diagnostics
     * @return array{0:list<array<string, mixed>>, 1:array<string, mixed>}
     */
    private static function normalizeHtmlTableElementParts(array $element, array &$diagnostics): array
    {
        [$fostered, $children] = self::normalizeHtmlTableChildren(
            is_array($element['children'] ?? null) ? $element['children'] : [],
            (string) ($element['name'] ?? ''),
            $diagnostics
        );
        $element['children'] = $children;

        return [$fostered, $element];
    }

    private static function isHtmlTableModelContext(string $name): bool
    {
        return isset(self::HTML5_TABLE_ALLOWED_CHILDREN[strtolower($name)]);
    }

    /**
     * @param array<string, mixed> $node
     */
    private static function isFosteredHtmlTableNode(array $node, string $context): bool
    {
        $allowed = self::HTML5_TABLE_ALLOWED_CHILDREN[strtolower($context)] ?? null;
        if ($allowed === null) {
            return false;
        }

        $type = (string) ($node['type'] ?? '');
        if ($type === 'text') {
            return preg_match('/\S/u', (string) ($node['text'] ?? '')) === 1;
        }
        if ($type !== 'element') {
            return false;
        }

        return !isset($allowed[strtolower((string) ($node['name'] ?? ''))]);
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private static function htmlTableFosterDiagnostic(array $node, string $context): array
    {
        $diagnostic = [
            'code' => 'table-foster-parented-content',
            'context' => strtolower($context),
            'nodeType' => (string) ($node['type'] ?? ''),
        ];

        if (($node['type'] ?? '') === 'element') {
            $diagnostic['tag'] = (string) ($node['name'] ?? '');
        }

        return $diagnostic;
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

    private static function childForeignContext(\DOMElement $element, string $rawName, ?string $elementForeignContext): ?string
    {
        if ($elementForeignContext === 'svg' && $rawName === 'foreignobject') {
            return null;
        }
        if ($elementForeignContext === 'math' && $rawName === 'annotation-xml') {
            $encoding = strtolower(trim($element->getAttribute('encoding')));
            if ($encoding === 'text/html' || $encoding === 'application/xhtml+xml') {
                return null;
            }
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
            'param',
            'script',
            'select',
            'style',
            'textarea',
        ], true);
    }

    private static function isUnwrappedElement(string $name): bool
    {
        return in_array(strtolower($name), [
            'button',
            'form',
            'applet',
            'iframe',
            'noembed',
            'noframes',
            'noscript',
            'object',
            'optgroup',
            'option',
            'plaintext',
            'select',
            'textarea',
            'template',
            'xmp',
        ], true);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, string>
     */
    private static function normalizeAttributes(
        \DOMElement $element,
        string $tagName,
        string $mode,
        array &$diagnostics,
        ?string $foreignContext,
        ?string $baseUrl
    ): array {
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

            if (strtolower($name) === 'srcset') {
                $srcset = self::normalizeSrcsetAttribute($value, $tagName, $diagnostics, $baseUrl);
                if ($srcset === null) {
                    continue;
                }

                $attrs[$name] = $srcset;
                continue;
            }

            if (self::isUrlAttribute($name)) {
                if (!self::isSafeUrlAttributeValue($name, $value)) {
                    $diagnostics[] = [
                        'code' => 'unsafe-url',
                        'tag' => $tagName,
                        'attribute' => $name,
                    ];
                    continue;
                }

                $normalizedUrl = self::normalizeUrlAttributeValue($value);
                if ($normalizedUrl !== $value) {
                    $diagnostics[] = [
                        'code' => 'normalized-url',
                        'tag' => $tagName,
                        'attribute' => $name,
                    ];
                }

                $value = $mode === 'html' && $baseUrl !== null
                    ? self::resolveRelativeUrl($baseUrl, $normalizedUrl)
                    : $normalizedUrl;
            }

            $attrs[$name] = $value;
        }

        return $attrs;
    }

    private static function normalizeUrlAttributeValue(string $value): string
    {
        $withoutControls = preg_replace('/[\x00-\x1F\x7F]+/', '', $value) ?? $value;
        $trimmed = trim($withoutControls);
        $compact = preg_replace('/[\x00-\x20]+/', '', $value) ?? $value;

        if (
            preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $compact) === 1
            && preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $trimmed) !== 1
        ) {
            return $compact;
        }

        return $trimmed;
    }

    private static function assertSafeHtmlSource(string $html, string $label): void
    {
        self::assertNoNullByte($html, $label);
        if (preg_match('/<!\s*(?:DOCTYPE|ENTITY|ELEMENT|ATTLIST|NOTATION)\b/i', $html) === 1) {
            throw new \InvalidArgumentException($label . ' must not declare DTDs or entities');
        }
        if (preg_match('/<\?[A-Za-z_][A-Za-z0-9_.:-]*/', $html) === 1) {
            throw new \InvalidArgumentException($label . ' must not include processing instructions');
        }
    }

    private static function assertSafeXmlSource(string $xml, string $label): void
    {
        self::assertNoNullByte($xml, $label);
        if (preg_match('/<\?[A-Za-z_][A-Za-z0-9_.:-]*/', $xml) === 1) {
            throw new \InvalidArgumentException($label . ' must not include processing instructions');
        }
        if (preg_match('/<!\s*(?:DOCTYPE|ENTITY|ELEMENT|ATTLIST|NOTATION)\b/i', $xml) === 1) {
            throw new \InvalidArgumentException('XML fragments with DTD or entity declarations are not supported');
        }
    }

    private static function assertNoNullByte(string $source, string $label): void
    {
        if (str_contains($source, "\0")) {
            throw new \InvalidArgumentException($label . ' must not contain NUL bytes');
        }
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
            || $lower === 'ping'
            || $lower === 'style'
            || $lower === 'srcdoc'
            || $lower === 'data-pandoc-fragment-root';
    }

    private static function isUrlAttribute(string $name): bool
    {
        return in_array(strtolower($name), [
            'action',
            'background',
            'cite',
            'codebase',
            'data',
            'formaction',
            'href',
            'longdesc',
            'manifest',
            'poster',
            'profile',
            'src',
            'srcset',
            'xlink:href',
        ], true);
    }

    private static function isSafeUrlAttributeValue(string $name, string $value): bool
    {
        if (in_array(strtolower($name), [
            'action',
            'background',
            'codebase',
            'data',
            'formaction',
            'longdesc',
            'manifest',
            'poster',
            'profile',
            'src',
        ], true)) {
            return self::isSafeFetchUrl($value);
        }

        return self::isSafeUrl($value);
    }

    private static function isSafeFetchUrl(string $value): bool
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

        return in_array($scheme, ['http', 'https'], true);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeSrcsetAttribute(
        string $value,
        string $tagName,
        array &$diagnostics,
        ?string $baseUrl
    ): ?string {
        $normalized = [];
        foreach (explode(',', $value) as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                $diagnostics[] = [
                    'code' => 'invalid-srcset-descriptor',
                    'tag' => $tagName,
                    'attribute' => 'srcset',
                    'descriptor' => '',
                ];
                continue;
            }

            $candidateValue = self::normalizeSrcsetCandidate($candidate, $tagName, $diagnostics, $baseUrl);
            if ($candidateValue !== null) {
                $normalized[] = $candidateValue;
            }
        }

        return $normalized === [] ? null : implode(', ', $normalized);
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
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeSrcsetCandidate(
        string $candidate,
        string $tagName,
        array &$diagnostics,
        ?string $baseUrl
    ): ?string {
        $candidateWithoutControls = preg_replace('/[\x00-\x20]+/', '', $candidate) ?? $candidate;
        if (!self::isSafeImageCandidateUrl($candidateWithoutControls)) {
            $diagnostics[] = [
                'code' => 'unsafe-url',
                'tag' => $tagName,
                'attribute' => 'srcset',
                'candidate' => $candidate,
            ];

            return null;
        }

        $parts = preg_split('/\s+/', $candidate);
        if (!is_array($parts) || $parts === []) {
            return null;
        }

        $url = (string) array_shift($parts);
        if ($url === '' || !self::isSafeImageCandidateUrl($url)) {
            $diagnostics[] = [
                'code' => 'unsafe-url',
                'tag' => $tagName,
                'attribute' => 'srcset',
                'candidate' => $candidate,
            ];

            return null;
        }

        $descriptor = self::normalizeSrcsetDescriptor($parts);
        if ($descriptor === null) {
            $diagnostics[] = [
                'code' => 'invalid-srcset-descriptor',
                'tag' => $tagName,
                'attribute' => 'srcset',
                'candidate' => $candidate,
                'descriptor' => implode(' ', $parts),
            ];

            return null;
        }

        if ($baseUrl !== null) {
            $url = self::resolveRelativeUrl($baseUrl, $url);
        }

        return $descriptor === '' ? $url : $url . ' ' . $descriptor;
    }

    /**
     * @param list<string> $descriptors
     */
    private static function normalizeSrcsetDescriptor(array $descriptors): ?string
    {
        if ($descriptors === []) {
            return '';
        }
        if (count($descriptors) !== 1) {
            return null;
        }

        $descriptor = strtolower($descriptors[0]);
        if (preg_match('/^([0-9]+)w$/', $descriptor, $width) === 1) {
            $pixels = (int) $width[1];

            return $pixels > 0 ? $pixels . 'w' : null;
        }

        if (preg_match('/^(?:[0-9]+(?:\.[0-9]+)?|\.[0-9]+)x$/', $descriptor) === 1) {
            $density = substr($descriptor, 0, -1);
            if ((float) $density <= 0.0) {
                return null;
            }

            return self::normalizeDecimalDescriptor($density) . 'x';
        }

        return null;
    }

    private static function normalizeDecimalDescriptor(string $value): string
    {
        if (str_starts_with($value, '.')) {
            $value = '0' . $value;
        }

        if (str_contains($value, '.')) {
            [$integer, $fraction] = explode('.', $value, 2);
            $integer = ltrim($integer, '0');
            $fraction = rtrim($fraction, '0');
            $value = ($integer === '' ? '0' : $integer) . ($fraction === '' ? '' : '.' . $fraction);
        } else {
            $value = ltrim($value, '0');
        }

        return $value === '' ? '0' : $value;
    }

    private static function isSafeImageCandidateUrl(string $value): bool
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

        return in_array($scheme, ['http', 'https'], true);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function resolveFragmentBaseUrl(\DOMElement $wrapper, ?string $callerBaseUrl, array &$diagnostics): ?string
    {
        $documentBaseUrl = self::normalizeCallerBaseUrl($callerBaseUrl);

        foreach ($wrapper->getElementsByTagName('base') as $baseElement) {
            if (!$baseElement instanceof \DOMElement || !$baseElement->hasAttribute('href')) {
                continue;
            }

            $href = trim($baseElement->getAttribute('href'));
            if ($href === '') {
                continue;
            }

            $resolved = self::resolveBaseHref($href, $documentBaseUrl, $diagnostics);
            if ($resolved !== null) {
                return $resolved;
            }

            break;
        }

        return $documentBaseUrl;
    }

    private static function normalizeCallerBaseUrl(?string $baseUrl): ?string
    {
        if ($baseUrl === null) {
            return null;
        }

        $trimmed = trim($baseUrl);
        if ($trimmed === '') {
            return null;
        }

        if (!self::isTrustedAbsoluteBaseUrl($trimmed)) {
            throw new \InvalidArgumentException('HTML fragment base URL must be an absolute HTTP(S) URL without credentials');
        }

        return $trimmed;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function resolveBaseHref(string $href, ?string $documentBaseUrl, array &$diagnostics): ?string
    {
        if (self::isTrustedAbsoluteBaseUrl($href)) {
            return $href;
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $href) === 1) {
            $diagnostics[] = [
                'code' => 'unsafe-url',
                'tag' => 'base',
                'attribute' => 'href',
            ];

            return null;
        }

        if ($documentBaseUrl !== null) {
            return self::resolveRelativeUrl($documentBaseUrl, $href);
        }

        $diagnostics[] = [
            'code' => 'unresolved-base-url',
            'tag' => 'base',
            'attribute' => 'href',
        ];

        return null;
    }

    private static function isTrustedAbsoluteBaseUrl(string $value): bool
    {
        $parts = parse_url($value);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        return isset($parts['host'])
            && !isset($parts['user'])
            && !isset($parts['pass']);
    }

    private static function resolveRelativeUrl(string $baseUrl, string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return $value;
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $trimmed) === 1) {
            return $trimmed;
        }

        $base = parse_url($baseUrl);
        if (!is_array($base) || !isset($base['scheme'], $base['host'])) {
            return $value;
        }

        $scheme = strtolower((string) $base['scheme']);
        $host = (string) $base['host'];
        $port = isset($base['port']) ? ':' . (string) $base['port'] : '';
        $origin = $scheme . '://' . $host . $port;

        if (str_starts_with($trimmed, '//')) {
            return $scheme . ':' . $trimmed;
        }

        $basePath = (string) ($base['path'] ?? '/');
        if ($basePath === '') {
            $basePath = '/';
        }

        $baseQuery = isset($base['query']) ? '?' . (string) $base['query'] : '';
        if (str_starts_with($trimmed, '#')) {
            return $origin . $basePath . $baseQuery . $trimmed;
        }
        if (str_starts_with($trimmed, '?')) {
            return $origin . $basePath . $trimmed;
        }

        $fragment = '';
        $pathAndQuery = $trimmed;
        $fragmentOffset = strpos($pathAndQuery, '#');
        if ($fragmentOffset !== false) {
            $fragment = substr($pathAndQuery, $fragmentOffset);
            $pathAndQuery = substr($pathAndQuery, 0, $fragmentOffset);
        }

        $query = '';
        $path = $pathAndQuery;
        $queryOffset = strpos($path, '?');
        if ($queryOffset !== false) {
            $query = substr($path, $queryOffset);
            $path = substr($path, 0, $queryOffset);
        }

        $resolvedPath = str_starts_with($path, '/')
            ? $path
            : self::joinBasePath($basePath, $path);

        return $origin . self::normalizeUrlPath($resolvedPath) . $query . $fragment;
    }

    private static function joinBasePath(string $basePath, string $relativePath): string
    {
        $slash = strrpos($basePath, '/');
        $directory = str_ends_with($basePath, '/')
            ? $basePath
            : substr($basePath, 0, $slash === false ? 0 : $slash + 1);

        if ($directory === '') {
            $directory = '/';
        }

        return $directory . $relativePath;
    }

    private static function normalizeUrlPath(string $path): string
    {
        $trailingSlash = str_ends_with($path, '/') && $path !== '/';
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        $normalized = '/' . implode('/', $segments);
        if ($trailingSlash && $normalized !== '/') {
            $normalized .= '/';
        }

        return $normalized;
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
            return '<!--' . self::safeCommentText((string) ($node['text'] ?? '')) . '-->';
        }
        if ($type !== 'element') {
            return '';
        }

        $name = (string) $node['name'];
        $attrs = $this->serializeAttributes(is_array($node['attrs'] ?? null) ? $node['attrs'] : []);
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        if ($this->mode === 'html' && self::isHtmlVoidElement($name)) {
            return '<' . $name . $attrs . '>';
        }
        if ($children === []) {
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
            $lowerName = strtolower((string) $name);
            $stringValue = (string) $value;
            if ($this->mode === 'html' && isset(self::HTML5_BOOLEAN_ATTRIBUTES[$lowerName]) && ($stringValue === '' || strtolower($stringValue) === $lowerName)) {
                $serialized .= ' ' . $name;
                continue;
            }

            $serialized .= ' ' . $name . '="' . htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }

        return $serialized;
    }

    private static function safeCommentText(string $text): string
    {
        while (str_contains($text, '--')) {
            $text = str_replace('--', '- -', $text);
        }

        return str_ends_with($text, '-') ? $text . ' ' : $text;
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
