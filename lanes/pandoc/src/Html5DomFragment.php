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

    /** @var array<string, true> */
    private const HTML_FRAGMENT_INACTIVE_BASE_ANCESTORS = [
        'applet' => true,
        'button' => true,
        'form' => true,
        'iframe' => true,
        'math' => true,
        'map' => true,
        'noembed' => true,
        'noframes' => true,
        'noscript' => true,
        'object' => true,
        'optgroup' => true,
        'option' => true,
        'plaintext' => true,
        'select' => true,
        'svg' => true,
        'template' => true,
        'textarea' => true,
        'xmp' => true,
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
        $documentMetadataNodes = self::htmlDocumentElementMetadataNodes($html, $diagnostics);

        return new self(
            'html',
            [
                ...$documentMetadataNodes,
                ...self::normalizeChildren($wrapper, 'html', $diagnostics, baseUrl: $resolvedBaseUrl),
            ],
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
            if (in_array(($diagnostic['code'] ?? ''), ['unsafe-attribute', 'unsafe-url', 'invalid-srcset-descriptor', 'hidden-content-review', 'inert-content-review', 'dialog-review'], true)) {
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
                'line' => $error->line,
                'column' => $error->column,
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
                'line' => $error->line,
                'column' => $error->column,
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
            return [self::nodeWithSourceLine([
                'type' => 'text',
                'text' => $node->nodeValue ?? '',
            ], $node)];
        }

        if ($node instanceof \DOMComment) {
            return [self::nodeWithSourceLine([
                'type' => 'comment',
                'text' => $node->nodeValue ?? '',
            ], $node)];
        }

        if (!$node instanceof \DOMElement) {
            return null;
        }

        $rawName = self::rawElementName($node, $mode);
        $elementForeignContext = self::elementForeignContext($rawName, $mode, $foreignContext);
        $name = self::normalizedElementName($rawName, $elementForeignContext);
        if (!self::isSafeElementName($name)) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'blocked-tag',
                'tag' => $name,
            ], $node);

            return null;
        }

        if ($mode === 'html' && self::isUnwrappedElement($name)) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'blocked-tag',
                'tag' => $name,
            ], $node);

            if ($name === 'iframe' && $node->hasAttribute('srcdoc')) {
                $srcdocNodes = self::normalizeHtmlSrcdocAttribute(
                    $node->getAttribute('srcdoc'),
                    $diagnostics,
                    $baseUrl
                );
                if ($srcdocNodes !== []) {
                    return $srcdocNodes;
                }
            }

            $children = self::normalizeChildren(
                $node,
                $mode,
                $diagnostics,
                self::childForeignContext($node, $rawName, $elementForeignContext),
                $baseUrl
            );

            if ($name === 'iframe') {
                if ($children !== []) {
                    return $children;
                }

                $iframeSource = self::normalizeHtmlIframeSourceElement($node, $diagnostics, $baseUrl);

                return $iframeSource === null ? [] : [$iframeSource];
            }

            return self::withVisibleFormChoiceLabel($node, $name, $children);
        }

        if ($mode === 'html' && $name === 'input') {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'blocked-tag',
                'tag' => $name,
            ], $node);

            $label = self::visibleInputLabel($node);

            return $label === null ? null : [['type' => 'text', 'text' => $label]];
        }

        if ($mode === 'html' && $name === 'area') {
            return self::normalizeHtmlAreaElement($node, $diagnostics, $baseUrl);
        }

        if ($mode === 'html' && $name === 'link') {
            return self::normalizeHtmlLinkElement($node, $diagnostics, $baseUrl);
        }

        if ($mode === 'html' && $name === 'meta') {
            return self::normalizeHtmlMetaElement($node, $diagnostics, $baseUrl);
        }

        if ($mode === 'html' && $name === 'title') {
            return self::normalizeHtmlTitleElement($node, $diagnostics);
        }

        if ($mode === 'html' && self::isBlockedElement($name)) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'blocked-tag',
                'tag' => $name,
            ], $node);

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
        if ($mode === 'html' && $name === 'img') {
            $imageAltFallback = self::htmlImageAltFallbackNode($node, $attrs, $diagnostics);
            if ($imageAltFallback !== null) {
                return [$imageAltFallback];
            }
        }
        if ($mode === 'html' && $name === 'details') {
            self::markClosedHtmlDetailsReviewMetadata($node, $attrs, $children, $diagnostics);
        }
        if ($mode === 'html' && $name === 'dialog') {
            self::markHtmlDialogReviewMetadata($node, $attrs, $diagnostics);
            $name = 'div';
        }
        if ($mode === 'html') {
            self::markHtmlHiddenInertReviewMetadata($node, $name, $attrs, $diagnostics);
        }

        if ($mode === 'html' && self::isEmptyHtmlPictureSourceElement($node, $name, $attrs)) {
            $diagnostics[] = self::diagnosticWithSourceLine([
                'code' => 'empty-source',
                'tag' => $name,
                'reason' => 'missing-src-or-srcset',
            ], $node);

            return $children === [] ? null : $children;
        }

        $element = [
            'type' => 'element',
            'name' => $name,
            'attrs' => $attrs,
            'children' => $mode === 'html' && self::isHtmlVoidElement($name) ? [] : $children,
        ];
        $element = self::nodeWithSourceLine($element, $node);

        if ($mode === 'html' && self::isHtmlVoidElement($name) && $children !== []) {
            return [$element, ...$children];
        }

        if ($mode === 'html' && $name === 'table') {
            return self::normalizeHtmlTableElement($element, $diagnostics);
        }

        return [$element];
    }

    /**
     * @param array<string, string> $attrs
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, mixed>|null
     */
    private static function htmlImageAltFallbackNode(\DOMElement $element, array $attrs, array &$diagnostics): ?array
    {
        if (array_key_exists('src', $attrs) || array_key_exists('srcset', $attrs)) {
            return null;
        }
        if (!$element->hasAttribute('src') && !$element->hasAttribute('srcset')) {
            return null;
        }
        if (!$element->hasAttribute('alt')) {
            return null;
        }

        $alt = str_replace("\0", '', $element->getAttribute('alt'));
        if (trim($alt) === '') {
            return null;
        }

        $diagnostics[] = self::diagnosticWithSourceLine([
            'code' => 'image-alt-fallback',
            'tag' => 'img',
            'attribute' => 'alt',
            'reason' => 'stripped-image-resource',
        ], $element);

        return self::nodeWithSourceLine([
            'type' => 'element',
            'name' => 'span',
            'attrs' => [
                'data-pandoc-image-alt-fallback' => 'true',
            ],
            'children' => [[
                'type' => 'text',
                'text' => $alt,
            ]],
        ], $element);
    }

    /**
     * @param array<string, string> $attrs
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function markHtmlHiddenInertReviewMetadata(
        \DOMElement $element,
        string $tagName,
        array &$attrs,
        array &$diagnostics
    ): void {
        if ($element->hasAttribute('hidden')) {
            unset($attrs['hidden']);
            $attrs['data-pandoc-hidden-state'] = self::normalizeHtmlHiddenState($element->getAttribute('hidden'));
            $diagnostics[] = [
                'code' => 'hidden-content-review',
                'tag' => $tagName,
                'attribute' => 'hidden',
                'reason' => 'hidden-content-preserved',
            ];
        }

        if ($element->hasAttribute('inert')) {
            unset($attrs['inert']);
            $attrs['data-pandoc-inert-state'] = 'true';
            $diagnostics[] = [
                'code' => 'inert-content-review',
                'tag' => $tagName,
                'attribute' => 'inert',
                'reason' => 'inert-content-preserved',
            ];
        }
    }

    private static function normalizeHtmlHiddenState(string $value): string
    {
        $state = strtolower(self::cleanHtmlMetadataAttribute($value));

        return $state === 'until-found' ? 'until-found' : 'hidden';
    }

    /**
     * @param array<string, string> $attrs
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function markHtmlDialogReviewMetadata(
        \DOMElement $element,
        array &$attrs,
        array &$diagnostics
    ): void {
        $state = $element->hasAttribute('open') ? 'open' : 'closed';
        unset($attrs['open']);
        $attrs['data-pandoc-dialog-state'] = $state;

        $diagnostic = [
            'code' => 'dialog-review',
            'tag' => 'dialog',
            'replacement' => 'div',
            'state' => $state,
            'reason' => 'dialog-content-preserved',
        ];
        if ($state === 'open') {
            $diagnostic['attribute'] = 'open';
        }

        $diagnostics[] = $diagnostic;
    }

    /**
     * @param array<string, string> $attrs
     * @param list<array<string, mixed>> $children
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function markClosedHtmlDetailsReviewMetadata(
        \DOMElement $element,
        array &$attrs,
        array &$children,
        array &$diagnostics
    ): void {
        if ($element->hasAttribute('open')) {
            return;
        }

        $attrs['data-pandoc-details-state'] = 'closed';
        foreach ($children as $index => $child) {
            if (($child['type'] ?? '') !== 'element' || strtolower((string) ($child['name'] ?? '')) !== 'summary') {
                continue;
            }

            $childAttrs = is_array($child['attrs'] ?? null) ? $child['attrs'] : [];
            $childAttrs['data-pandoc-details-summary'] = 'true';
            $child['attrs'] = $childAttrs;
            $children[$index] = $child;
            break;
        }

        $diagnostics[] = [
            'code' => 'closed-details-review',
            'tag' => 'details',
            'reason' => 'collapsed-content-preserved',
        ];
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>
     */
    private static function normalizeHtmlSrcdocAttribute(string $srcdoc, array &$diagnostics, ?string $baseUrl): array
    {
        $srcdoc = trim($srcdoc);
        if ($srcdoc === '') {
            return [];
        }

        try {
            self::assertSafeHtmlSource($srcdoc, 'iframe srcdoc');
            $srcdocDiagnostics = [];
            $dom = self::loadHtmlDocument($srcdoc, $srcdocDiagnostics);
        } catch (\InvalidArgumentException $error) {
            $diagnostics[] = [
                'code' => 'invalid-srcdoc',
                'tag' => 'iframe',
                'attribute' => 'srcdoc',
                'message' => $error->getMessage(),
            ];

            return [];
        }

        foreach ($srcdocDiagnostics as $diagnostic) {
            $diagnostic['context'] ??= 'iframe-srcdoc';
            $diagnostics[] = $diagnostic;
        }

        $wrapper = self::htmlWrapper($dom);
        if (!$wrapper instanceof \DOMElement) {
            $diagnostics[] = [
                'code' => 'invalid-srcdoc',
                'tag' => 'iframe',
                'attribute' => 'srcdoc',
                'message' => 'Unable to parse iframe srcdoc wrapper',
            ];

            return [];
        }

        $srcdocBaseUrl = self::resolveFragmentBaseUrl($wrapper, $baseUrl, $diagnostics);

        return self::normalizeChildren($wrapper, 'html', $diagnostics, baseUrl: $srcdocBaseUrl);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, mixed>|null
     */
    private static function normalizeHtmlIframeSourceElement(\DOMElement $element, array &$diagnostics, ?string $baseUrl): ?array
    {
        if (!$element->hasAttribute('src')) {
            return null;
        }

        $target = $element->getAttribute('src');
        $normalizedTarget = self::normalizeUrlAttributeValue($target);
        if ($normalizedTarget === '' || !self::isSafeFetchUrl($normalizedTarget)) {
            $diagnostics[] = [
                'code' => 'unsafe-url',
                'tag' => 'iframe',
                'attribute' => 'src',
            ];

            return null;
        }

        if ($normalizedTarget !== $target) {
            $diagnostics[] = [
                'code' => 'normalized-url',
                'tag' => 'iframe',
                'attribute' => 'src',
            ];
        }

        $href = $baseUrl !== null
            ? self::resolveRelativeUrl($baseUrl, $normalizedTarget)
            : $normalizedTarget;
        $title = $element->hasAttribute('title')
            ? self::cleanHtmlMetadataAttribute($element->getAttribute('title'))
            : '';
        $attrs = [
            'href' => $href,
            'data-pandoc-iframe-src' => 'true',
        ];
        if ($title !== '') {
            $attrs['title'] = $title;
        }
        self::addIframePolicyReviewAttributes($element, $attrs, $diagnostics);

        return [
            'type' => 'element',
            'name' => 'a',
            'attrs' => $attrs,
            'children' => [[
                'type' => 'text',
                'text' => $title !== '' ? $title : 'Embedded frame source',
            ]],
        ];
    }

    /**
     * @param array<string, string> $attrs
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addIframePolicyReviewAttributes(\DOMElement $element, array &$attrs, array &$diagnostics): void
    {
        if ($element->hasAttribute('sandbox')) {
            $sandbox = self::normalizeIframeSandboxAttribute($element->getAttribute('sandbox'), $diagnostics);
            if ($sandbox !== null) {
                $attrs['data-pandoc-iframe-sandbox'] = $sandbox;
            }
        }

        if ($element->hasAttribute('allow')) {
            $allow = self::normalizeIframeAllowAttribute($element->getAttribute('allow'));
            if ($allow !== null) {
                $attrs['data-pandoc-iframe-allow'] = $allow;
            }
        }

        if ($element->hasAttribute('referrerpolicy')) {
            $referrerPolicy = self::normalizeIframeReferrerPolicyAttribute(
                $element->getAttribute('referrerpolicy'),
                $diagnostics
            );
            if ($referrerPolicy !== null) {
                $attrs['data-pandoc-iframe-referrerpolicy'] = $referrerPolicy;
            }
        }

        if ($element->hasAttribute('allowfullscreen')) {
            $attrs['data-pandoc-iframe-allowfullscreen'] = 'true';
        }
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeIframeSandboxAttribute(string $value, array &$diagnostics): ?string
    {
        $trimmed = strtolower(trim(str_replace("\0", '', $value)));
        if ($trimmed === '') {
            return '';
        }

        $tokens = preg_split('/[\x00-\x20]+/', $trimmed);
        if (!is_array($tokens)) {
            return null;
        }

        $allowedTokens = [
            'allow-downloads' => true,
            'allow-forms' => true,
            'allow-modals' => true,
            'allow-orientation-lock' => true,
            'allow-pointer-lock' => true,
            'allow-popups' => true,
            'allow-popups-to-escape-sandbox' => true,
            'allow-presentation' => true,
            'allow-same-origin' => true,
            'allow-scripts' => true,
            'allow-storage-access-by-user-activation' => true,
            'allow-top-navigation' => true,
            'allow-top-navigation-by-user-activation' => true,
            'allow-top-navigation-to-custom-protocols' => true,
        ];

        $normalized = [];
        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token === '') {
                continue;
            }
            if (!isset($allowedTokens[$token])) {
                $diagnostics[] = [
                    'code' => 'unsafe-attribute',
                    'tag' => 'iframe',
                    'attribute' => 'sandbox',
                    'token' => $token,
                ];
                continue;
            }
            if (!in_array($token, $normalized, true)) {
                $normalized[] = $token;
            }
        }

        return $normalized === [] ? null : implode(' ', $normalized);
    }

    private static function normalizeIframeAllowAttribute(string $value): ?string
    {
        $cleaned = self::cleanHtmlMetadataAttribute($value);
        if ($cleaned === '') {
            return null;
        }

        $directives = [];
        foreach (explode(';', $cleaned) as $directive) {
            $directive = trim($directive);
            if ($directive === '') {
                continue;
            }
            $directive = preg_replace('/[\t\r\n\f ]+/u', ' ', $directive) ?? $directive;
            $directives[] = $directive;
        }

        return $directives === [] ? null : implode('; ', $directives);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeIframeReferrerPolicyAttribute(string $value, array &$diagnostics): ?string
    {
        $policy = strtolower(self::cleanHtmlMetadataAttribute($value));
        if (in_array($policy, [
            'no-referrer',
            'no-referrer-when-downgrade',
            'origin',
            'origin-when-cross-origin',
            'same-origin',
            'strict-origin',
            'strict-origin-when-cross-origin',
            'unsafe-url',
        ], true)) {
            return $policy;
        }

        $diagnostics[] = [
            'code' => 'unsafe-attribute',
            'tag' => 'iframe',
            'attribute' => 'referrerpolicy',
            'value' => $policy,
        ];

        return null;
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
                $diagnostics[] = self::diagnosticWithNormalizedNodeLine(
                    self::htmlTableFosterDiagnostic($child, $context),
                    $child
                );
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
     * @param array<string, string> $attrs
     */
    private static function isEmptyHtmlPictureSourceElement(\DOMElement $element, string $name, array $attrs): bool
    {
        return self::hasHtmlAncestor($element, 'picture')
            && strtolower($name) === 'source'
            && !array_key_exists('src', $attrs)
            && !array_key_exists('srcset', $attrs);
    }

    private static function hasHtmlAncestor(\DOMElement $element, string $name): bool
    {
        $parent = $element->parentNode;
        while ($parent instanceof \DOMElement) {
            if (strtolower($parent->tagName) === strtolower($name)) {
                return true;
            }

            $parent = $parent->parentNode;
        }

        return false;
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
        if ($elementForeignContext === 'svg' && self::isSvgHtmlIntegrationPointName($rawName)) {
            return null;
        }
        if ($elementForeignContext === 'math' && self::isMathMlTextIntegrationPointName($rawName)) {
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

    private static function isSvgHtmlIntegrationPointName(string $name): bool
    {
        return in_array($name, ['foreignobject', 'desc'], true);
    }

    private static function isMathMlTextIntegrationPointName(string $name): bool
    {
        return in_array($name, ['mi', 'mn', 'mo', 'ms', 'mtext'], true);
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private static function nodeWithSourceLine(array $node, \DOMNode $sourceNode): array
    {
        $line = $sourceNode->getLineNo();
        if ($line > 0) {
            $node['line'] = $line;
        }

        return $node;
    }

    /**
     * @param array<string, mixed> $diagnostic
     * @return array<string, mixed>
     */
    private static function diagnosticWithSourceLine(array $diagnostic, \DOMNode $sourceNode): array
    {
        $line = $sourceNode->getLineNo();
        if ($line > 0) {
            $diagnostic['line'] = $line;
        }

        return $diagnostic;
    }

    /**
     * @param array<string, mixed> $diagnostic
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private static function diagnosticWithNormalizedNodeLine(array $diagnostic, array $node): array
    {
        $line = $node['line'] ?? null;
        if (is_int($line) && $line > 0) {
            $diagnostic['line'] = $line;
        }

        return $diagnostic;
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
            'map',
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

    private static function visibleInputLabel(\DOMElement $element): ?string
    {
        $type = strtolower(trim($element->getAttribute('type')));
        $attribute = match ($type) {
            'button', 'reset', 'submit' => 'value',
            'image' => 'alt',
            default => null,
        };
        if ($attribute === null || !$element->hasAttribute($attribute)) {
            return null;
        }

        $label = str_replace("\0", '', $element->getAttribute($attribute));

        return trim($label) === '' ? null : $label;
    }

    /**
     * @param list<array<string, mixed>> $children
     * @return list<array<string, mixed>>
     */
    private static function withVisibleFormChoiceLabel(\DOMElement $element, string $name, array $children): array
    {
        if ($name !== 'option' && $name !== 'optgroup') {
            return $children;
        }

        $label = self::visibleFormChoiceLabel($element);
        if ($label === null) {
            return $children;
        }

        $labelNode = [
            'type' => 'text',
            'text' => $label,
        ];

        return $name === 'option'
            ? [$labelNode]
            : [$labelNode, ...$children];
    }

    private static function visibleFormChoiceLabel(\DOMElement $element): ?string
    {
        if (!$element->hasAttribute('label')) {
            return null;
        }

        $label = str_replace("\0", '', $element->getAttribute('label'));

        return trim($label) === '' ? null : $label;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>|null
     */
    private static function normalizeHtmlAreaElement(\DOMElement $element, array &$diagnostics, ?string $baseUrl): ?array
    {
        $diagnostics[] = [
            'code' => 'blocked-tag',
            'tag' => 'area',
        ];

        foreach (['download', 'ping', 'target'] as $attributeName) {
            if ($element->hasAttribute($attributeName)) {
                $diagnostics[] = [
                    'code' => 'unsafe-attribute',
                    'tag' => 'area',
                    'attribute' => $attributeName,
                ];
            }
        }

        if (!$element->hasAttribute('href')) {
            return null;
        }

        $target = $element->getAttribute('href');
        $normalizedTarget = self::normalizeUrlAttributeValue($target);
        if ($normalizedTarget === '' || !self::isSafeUrl($normalizedTarget)) {
            $diagnostics[] = [
                'code' => 'unsafe-url',
                'tag' => 'area',
                'attribute' => 'href',
            ];

            return null;
        }

        if ($normalizedTarget !== $target) {
            $diagnostics[] = [
                'code' => 'normalized-url',
                'tag' => 'area',
                'attribute' => 'href',
            ];
        }

        $href = $baseUrl !== null
            ? self::resolveRelativeUrl($baseUrl, $normalizedTarget)
            : $normalizedTarget;
        $attrs = [
            'href' => $href,
            'data-pandoc-image-map-area' => 'true',
        ];

        $mapName = self::nearestHtmlMapName($element);
        if ($mapName !== null) {
            $attrs['data-pandoc-image-map-name'] = $mapName;
        }

        if ($element->hasAttribute('shape')) {
            $shape = self::normalizeHtmlAreaShapeAttribute($element->getAttribute('shape'), $diagnostics);
            if ($shape !== null) {
                $attrs['data-pandoc-image-map-shape'] = $shape;
            }
        }

        if ($element->hasAttribute('coords')) {
            $coords = self::normalizeHtmlAreaCoordsAttribute($element->getAttribute('coords'), $diagnostics);
            if ($coords !== null) {
                $attrs['data-pandoc-image-map-coords'] = $coords;
            }
        }

        $label = null;
        if ($element->hasAttribute('alt')) {
            $alt = self::cleanHtmlMetadataAttribute($element->getAttribute('alt'));
            if ($alt !== '') {
                $attrs['data-pandoc-image-map-alt'] = $alt;
                $label = $alt;
            }
        }

        if ($element->hasAttribute('title')) {
            $title = self::cleanHtmlMetadataAttribute($element->getAttribute('title'));
            if ($title !== '') {
                $attrs['title'] = $title;
                $label ??= $title;
            }
        }

        if ($element->hasAttribute('rel')) {
            $rel = self::normalizeHtmlRelAttribute($element->getAttribute('rel'), 'area', $diagnostics);
            if ($rel !== null) {
                $attrs['rel'] = $rel;
            }
        }

        return [[
            'type' => 'element',
            'name' => 'a',
            'attrs' => $attrs,
            'children' => [[
                'type' => 'text',
                'text' => $label ?? 'Image map region',
            ]],
        ]];
    }

    private static function nearestHtmlMapName(\DOMElement $element): ?string
    {
        $parent = $element->parentNode;
        while ($parent instanceof \DOMElement) {
            if (strtolower($parent->tagName) === 'map') {
                foreach (['name', 'id'] as $attributeName) {
                    if (!$parent->hasAttribute($attributeName)) {
                        continue;
                    }

                    $name = self::cleanHtmlMetadataAttribute($parent->getAttribute($attributeName));
                    if ($name !== '') {
                        return $name;
                    }
                }

                return null;
            }

            $parent = $parent->parentNode;
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlAreaShapeAttribute(string $value, array &$diagnostics): ?string
    {
        $shape = strtolower(self::cleanHtmlMetadataAttribute($value));
        if ($shape === '') {
            return null;
        }

        if (in_array($shape, ['circle', 'default', 'poly', 'rect'], true)) {
            return $shape;
        }

        $diagnostics[] = [
            'code' => 'unsafe-attribute',
            'tag' => 'area',
            'attribute' => 'shape',
            'value' => $shape,
        ];

        return null;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlAreaCoordsAttribute(string $value, array &$diagnostics): ?string
    {
        $cleaned = trim(str_replace("\0", '', $value));
        if ($cleaned === '') {
            return null;
        }

        $parts = preg_split('/[\s,]+/u', $cleaned, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts) || $parts === []) {
            return null;
        }

        $coords = [];
        foreach ($parts as $part) {
            $coord = self::normalizeHtmlAreaCoord((string) $part);
            if ($coord === null) {
                $diagnostics[] = [
                    'code' => 'unsafe-attribute',
                    'tag' => 'area',
                    'attribute' => 'coords',
                    'value' => $cleaned,
                ];

                return null;
            }

            $coords[] = $coord;
        }

        return implode(',', $coords);
    }

    private static function normalizeHtmlAreaCoord(string $value): ?string
    {
        $trimmed = trim($value);
        if (preg_match('/^[0-9]+(?:\.[0-9]+)?$/', $trimmed) !== 1) {
            return null;
        }

        if (str_contains($trimmed, '.')) {
            [$integer, $fraction] = explode('.', $trimmed, 2);
            $integer = ltrim($integer, '0');
            $fraction = rtrim($fraction, '0');

            return ($integer === '' ? '0' : $integer) . ($fraction === '' ? '' : '.' . $fraction);
        }

        $integer = ltrim($trimmed, '0');

        return $integer === '' ? '0' : $integer;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>|null
     */
    private static function normalizeHtmlLinkElement(\DOMElement $element, array &$diagnostics, ?string $baseUrl): ?array
    {
        $diagnostics[] = [
            'code' => 'blocked-tag',
            'tag' => 'link',
        ];

        $relations = self::htmlLinkRelationTokens($element);
        $reviewRelations = self::reviewableHtmlLinkRelations($relations);
        if ($reviewRelations === [] || self::hasActiveHtmlLinkResourceRelation($relations) || !$element->hasAttribute('href')) {
            return null;
        }

        $target = $element->getAttribute('href');
        $normalizedTarget = self::normalizeUrlAttributeValue($target);
        if ($normalizedTarget === '' || !self::isSafeFetchUrl($normalizedTarget)) {
            $diagnostics[] = [
                'code' => 'unsafe-url',
                'tag' => 'link',
                'attribute' => 'href',
            ];

            return null;
        }

        if ($normalizedTarget !== $target) {
            $diagnostics[] = [
                'code' => 'normalized-url',
                'tag' => 'link',
                'attribute' => 'href',
            ];
        }

        $href = $baseUrl !== null
            ? self::resolveRelativeUrl($baseUrl, $normalizedTarget)
            : $normalizedTarget;

        $attrs = [
            'href' => $href,
            'data-pandoc-link-rel' => implode(' ', $reviewRelations),
        ];

        foreach (['hreflang', 'type', 'title'] as $attributeName) {
            if (!$element->hasAttribute($attributeName)) {
                continue;
            }

            $value = self::cleanHtmlMetadataAttribute($element->getAttribute($attributeName));
            if ($value !== '') {
                $attrs[$attributeName] = $value;
            }
        }

        return [[
            'type' => 'element',
            'name' => 'a',
            'attrs' => $attrs,
            'children' => [[
                'type' => 'text',
                'text' => self::htmlLinkReviewLabel($reviewRelations, $attrs),
            ]],
        ]];
    }

    /**
     * @return list<string>
     */
    private static function htmlLinkRelationTokens(\DOMElement $element): array
    {
        $rel = strtolower(trim($element->getAttribute('rel')));
        if ($rel === '') {
            return [];
        }

        $tokens = preg_split('/[\x00-\x20]+/', $rel);
        if (!is_array($tokens)) {
            return [];
        }

        $relations = [];
        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token === '' || in_array($token, $relations, true)) {
                continue;
            }

            $relations[] = $token;
        }

        return $relations;
    }

    /**
     * @param list<string> $relations
     * @return list<string>
     */
    private static function reviewableHtmlLinkRelations(array $relations): array
    {
        $reviewable = [];
        foreach (['canonical', 'alternate', 'shortlink', 'author', 'license', 'help', 'bookmark'] as $relation) {
            if (in_array($relation, $relations, true)) {
                $reviewable[] = $relation;
            }
        }

        return $reviewable;
    }

    /**
     * @param list<string> $relations
     */
    private static function hasActiveHtmlLinkResourceRelation(array $relations): bool
    {
        foreach ($relations as $relation) {
            if (in_array($relation, [
                'apple-touch-icon',
                'apple-touch-startup-image',
                'dns-prefetch',
                'icon',
                'import',
                'manifest',
                'mask-icon',
                'modulepreload',
                'preconnect',
                'prefetch',
                'preload',
                'prerender',
                'stylesheet',
            ], true)) {
                return true;
            }
        }

        return false;
    }

    private static function cleanHtmlMetadataAttribute(string $value): string
    {
        $value = str_replace("\0", '', $value);
        $value = preg_replace('/[\t\r\n\f ]+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * @param list<string> $relations
     * @param array<string, string> $attrs
     */
    private static function htmlLinkReviewLabel(array $relations, array $attrs): string
    {
        $title = $attrs['title'] ?? '';
        if ($title !== '') {
            return $title;
        }

        return match ($relations[0] ?? '') {
            'canonical' => 'Canonical source',
            'alternate' => 'Alternate source',
            'shortlink' => 'Shortlink',
            'author' => 'Author source',
            'license' => 'License source',
            'help' => 'Help source',
            'bookmark' => 'Bookmark source',
            default => 'Linked source',
        };
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>|null
     */
    private static function normalizeHtmlMetaElement(\DOMElement $element, array &$diagnostics, ?string $baseUrl): ?array
    {
        $diagnostics[] = [
            'code' => 'blocked-tag',
            'tag' => 'meta',
        ];

        $target = self::htmlMetaRefreshTarget($element);
        if ($target === null) {
            $charsetMetadata = self::htmlMetaCharsetMetadata($element);
            if ($charsetMetadata !== null) {
                [$source, $charset] = $charsetMetadata;

                return [[
                    'type' => 'element',
                    'name' => 'span',
                    'attrs' => [
                        'data-pandoc-meta-charset' => $charset,
                        'data-pandoc-meta-source' => $source,
                    ],
                    'children' => [[
                        'type' => 'text',
                        'text' => 'Charset: ' . $charset,
                    ]],
                ]];
            }

            $urlMetadata = self::htmlMetaUrlMetadata($element);
            if ($urlMetadata !== null) {
                [$kind, $name, $target] = $urlMetadata;
                $normalizedTarget = self::normalizeUrlAttributeValue($target);
                if ($normalizedTarget === '' || !self::isSafeFetchUrl($normalizedTarget)) {
                    $diagnostics[] = [
                        'code' => 'unsafe-url',
                        'tag' => 'meta',
                        'attribute' => 'content',
                    ];

                    return null;
                }

                if ($normalizedTarget !== $target) {
                    $diagnostics[] = [
                        'code' => 'normalized-url',
                        'tag' => 'meta',
                        'attribute' => 'content',
                    ];
                }

                $href = $baseUrl !== null
                    ? self::resolveRelativeUrl($baseUrl, $normalizedTarget)
                    : $normalizedTarget;
                $metadataAttributeName = $kind === 'property'
                    ? 'data-pandoc-meta-property'
                    : 'data-pandoc-meta-name';

                return [[
                    'type' => 'element',
                    'name' => 'a',
                    'attrs' => [
                        'href' => $href,
                        $metadataAttributeName => $name,
                        'data-pandoc-meta-content' => $href,
                        'data-pandoc-meta-url' => 'true',
                    ],
                    'children' => [[
                        'type' => 'text',
                        'text' => self::htmlMetaReviewLabel($name),
                    ]],
                ]];
            }

            $policyMetadata = self::htmlMetaPolicyMetadata($element, $diagnostics);
            if ($policyMetadata !== null) {
                [$kind, $name, $content] = $policyMetadata;
                $metadataAttributeName = $kind === 'http-equiv'
                    ? 'data-pandoc-meta-http-equiv'
                    : 'data-pandoc-meta-name';

                return [[
                    'type' => 'element',
                    'name' => 'span',
                    'attrs' => [
                        $metadataAttributeName => $name,
                        'data-pandoc-meta-content' => $content,
                    ],
                    'children' => [[
                        'type' => 'text',
                        'text' => self::htmlMetaReviewLabel($name) . ': ' . $content,
                    ]],
                ]];
            }

            $reviewMetadata = self::htmlMetaReviewMetadata($element);
            if ($reviewMetadata === null) {
                return null;
            }

            [$kind, $name, $content] = $reviewMetadata;
            $metadataAttributeName = $kind === 'property'
                ? 'data-pandoc-meta-property'
                : 'data-pandoc-meta-name';

            return [[
                'type' => 'element',
                'name' => 'span',
                'attrs' => [
                    $metadataAttributeName => $name,
                    'data-pandoc-meta-content' => $content,
                ],
                'children' => [[
                    'type' => 'text',
                    'text' => self::htmlMetaReviewLabel($name) . ': ' . $content,
                ]],
            ]];
        }

        $normalizedTarget = self::normalizeUrlAttributeValue($target);
        if ($normalizedTarget === '' || !self::isSafeFetchUrl($normalizedTarget)) {
            $diagnostics[] = [
                'code' => 'unsafe-url',
                'tag' => 'meta',
                'attribute' => 'content',
            ];

            return null;
        }

        if ($normalizedTarget !== $target) {
            $diagnostics[] = [
                'code' => 'normalized-url',
                'tag' => 'meta',
                'attribute' => 'content',
            ];
        }

        $href = $baseUrl !== null
            ? self::resolveRelativeUrl($baseUrl, $normalizedTarget)
            : $normalizedTarget;

        return [[
            'type' => 'element',
            'name' => 'a',
            'attrs' => [
                'href' => $href,
                'data-pandoc-meta-refresh' => 'true',
            ],
            'children' => [
                [
                    'type' => 'text',
                    'text' => 'Refresh target',
                ],
            ],
        ]];
    }

    private static function htmlMetaRefreshTarget(\DOMElement $element): ?string
    {
        if (strcasecmp(trim($element->getAttribute('http-equiv')), 'refresh') !== 0) {
            return null;
        }
        if (!$element->hasAttribute('content')) {
            return null;
        }

        $content = $element->getAttribute('content');
        if (preg_match('/(?:^|;)\s*url\s*=\s*(.+)\s*$/is', $content, $matches) !== 1) {
            return null;
        }

        $target = trim((string) $matches[1]);
        if (strlen($target) >= 2) {
            $quote = $target[0];
            if (($quote === '"' || $quote === "'") && str_ends_with($target, $quote)) {
                $target = substr($target, 1, -1);
            }
        }

        return $target;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>|null
     */
    private static function normalizeHtmlTitleElement(\DOMElement $element, array &$diagnostics): ?array
    {
        $diagnostics[] = [
            'code' => 'blocked-tag',
            'tag' => 'title',
        ];

        $title = self::cleanHtmlMetadataAttribute($element->textContent);
        if ($title === '') {
            return null;
        }

        return [[
            'type' => 'element',
            'name' => 'span',
            'attrs' => [
                'data-pandoc-meta-name' => 'title',
                'data-pandoc-meta-source' => 'title',
                'data-pandoc-meta-content' => $title,
            ],
            'children' => [[
                'type' => 'text',
                'text' => 'Title: ' . $title,
            ]],
        ]];
    }

    /**
     * @return array{0:string, 1:string}|null
     */
    private static function htmlMetaCharsetMetadata(\DOMElement $element): ?array
    {
        if ($element->hasAttribute('charset')) {
            $charset = self::normalizeHtmlCharsetLabel($element->getAttribute('charset'));

            return $charset === null ? null : ['charset', $charset];
        }

        if (strcasecmp(self::cleanHtmlMetadataAttribute($element->getAttribute('http-equiv')), 'content-type') !== 0) {
            return null;
        }
        if (!$element->hasAttribute('content')) {
            return null;
        }

        $content = $element->getAttribute('content');
        if (preg_match('/(?:^|;)\s*charset\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^;\s]+))/i', $content, $matches) !== 1) {
            return null;
        }

        $label = '';
        foreach ([1, 2, 3] as $index) {
            if (($matches[$index] ?? '') === '') {
                continue;
            }

            $label = (string) $matches[$index];
            break;
        }
        $charset = self::normalizeHtmlCharsetLabel($label);

        return $charset === null ? null : ['content-type', $charset];
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array{0:string, 1:string, 2:string}|null
     */
    private static function htmlMetaPolicyMetadata(\DOMElement $element, array &$diagnostics): ?array
    {
        if (!$element->hasAttribute('content')) {
            return null;
        }

        $httpEquiv = strtolower(self::cleanHtmlMetadataAttribute($element->getAttribute('http-equiv')));
        if ($httpEquiv === 'content-security-policy') {
            $policy = self::normalizeHtmlContentSecurityPolicy($element->getAttribute('content'), $diagnostics);

            return $policy === null ? null : ['http-equiv', 'content-security-policy', $policy];
        }

        $name = self::normalizeHtmlMetaName($element->getAttribute('name'));
        if ($name === 'referrer') {
            $policy = self::normalizeHtmlReferrerMetaPolicy($element->getAttribute('content'), $diagnostics);

            return $policy === null ? null : ['name', 'referrer', $policy];
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlContentSecurityPolicy(string $value, array &$diagnostics): ?string
    {
        $content = self::cleanHtmlMetadataAttribute($value);
        if ($content === '') {
            return null;
        }

        $directives = [];
        foreach (explode(';', $content) as $directive) {
            $directive = trim(preg_replace('/[\t\r\n\f ]+/u', ' ', $directive) ?? $directive);
            if ($directive === '') {
                continue;
            }

            [$name, $sources] = self::splitHtmlContentSecurityPolicyDirective($directive);
            if (!self::isReviewableHtmlContentSecurityPolicyDirective($name, $sources)) {
                $diagnostics[] = [
                    'code' => 'unsafe-attribute',
                    'tag' => 'meta',
                    'attribute' => 'content',
                    'directive' => $name,
                ];
                continue;
            }

            $directives[] = $name . ($sources === '' ? '' : ' ' . $sources);
        }

        return $directives === [] ? null : implode('; ', $directives);
    }

    /**
     * @return array{0:string, 1:string}
     */
    private static function splitHtmlContentSecurityPolicyDirective(string $directive): array
    {
        $parts = preg_split('/[\t\r\n\f ]+/', $directive, 2);
        if (!is_array($parts) || $parts === []) {
            return ['', ''];
        }

        $name = strtolower((string) $parts[0]);
        $sources = isset($parts[1])
            ? trim(preg_replace('/[\t\r\n\f ]+/u', ' ', (string) $parts[1]) ?? (string) $parts[1])
            : '';

        return [$name, $sources];
    }

    private static function isReviewableHtmlContentSecurityPolicyDirective(string $name, string $sources): bool
    {
        if (preg_match('/^[a-z][a-z0-9-]*$/', $name) !== 1) {
            return false;
        }

        if (in_array($name, ['report-to', 'report-uri'], true)) {
            return false;
        }

        $lowerCompact = strtolower(preg_replace('/[\x00-\x20]+/', '', $sources) ?? $sources);
        if ($lowerCompact !== '' && preg_match('/(?:java|vb)script:/', $lowerCompact) === 1) {
            return false;
        }

        return preg_match('/[<>{}`]/', $sources) !== 1;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlReferrerMetaPolicy(string $value, array &$diagnostics): ?string
    {
        $policy = strtolower(self::cleanHtmlMetadataAttribute($value));
        if (in_array($policy, [
            'no-referrer',
            'no-referrer-when-downgrade',
            'origin',
            'origin-when-cross-origin',
            'same-origin',
            'strict-origin',
            'strict-origin-when-cross-origin',
            'unsafe-url',
        ], true)) {
            return $policy;
        }

        if ($policy !== '') {
            $diagnostics[] = [
                'code' => 'unsafe-attribute',
                'tag' => 'meta',
                'attribute' => 'content',
                'value' => $policy,
            ];
        }

        return null;
    }

    /**
     * @return array{0:string, 1:string, 2:string}|null
     */
    private static function htmlMetaUrlMetadata(\DOMElement $element): ?array
    {
        if (!$element->hasAttribute('content')) {
            return null;
        }

        $kind = '';
        $name = '';
        if ($element->hasAttribute('property')) {
            $property = self::normalizeHtmlMetaProperty($element->getAttribute('property'));
            if (in_array($property, [
                'og:image',
                'og:image:secure_url',
                'og:image:url',
                'twitter:image',
                'twitter:image:src',
            ], true)) {
                $kind = 'property';
                $name = $property;
            }
        }

        if ($name === '' && $element->hasAttribute('name')) {
            $metaName = self::normalizeHtmlMetaName($element->getAttribute('name'));
            if (in_array($metaName, ['twitter:image', 'twitter:image:src'], true)) {
                $kind = 'name';
                $name = $metaName;
            }
        }

        if ($name === '') {
            return null;
        }

        $content = str_replace("\0", '', $element->getAttribute('content'));
        $trimmedContent = trim($content);
        if ($trimmedContent === '') {
            return null;
        }

        if (preg_match('/[\x00-\x20]/', $trimmedContent) === 1) {
            $compact = preg_replace('/[\x00-\x20]+/', '', $trimmedContent) ?? $trimmedContent;
            if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $compact) !== 1) {
                return null;
            }
        }

        return [$kind, $name, $content];
    }

    private static function normalizeHtmlCharsetLabel(string $value): ?string
    {
        $label = strtolower(self::cleanHtmlMetadataAttribute($value));
        if ($label === '' || strlen($label) > 64) {
            return null;
        }
        if (preg_match('/^[a-z0-9._:-]+$/', $label) !== 1) {
            return null;
        }

        return $label;
    }

    /**
     * @return array{0:string, 1:string, 2:string}|null
     */
    private static function htmlMetaReviewMetadata(\DOMElement $element): ?array
    {
        if (!$element->hasAttribute('content')) {
            return null;
        }

        $kind = 'name';
        $name = '';
        if ($element->hasAttribute('name')) {
            $name = self::normalizeHtmlMetaName($element->getAttribute('name'));
            if (!in_array($name, ['author', 'description', 'generator', 'keywords'], true)) {
                $name = '';
            }
        }

        if ($name === '' && $element->hasAttribute('property')) {
            $kind = 'property';
            $name = self::normalizeHtmlMetaProperty($element->getAttribute('property'));
            if (!in_array($name, [
                'article:modified_time',
                'article:published_time',
                'og:description',
                'og:title',
                'twitter:description',
                'twitter:title',
            ], true)) {
                return null;
            }
        }

        if ($name === '') {
            return null;
        }

        $content = self::cleanHtmlMetadataAttribute($element->getAttribute('content'));
        if ($content === '') {
            return null;
        }

        return [$kind, $name, $content];
    }

    private static function normalizeHtmlMetaName(string $name): string
    {
        $name = str_replace("\0", '', $name);
        $name = preg_replace('/[\t\r\n\f ]+/u', ' ', $name) ?? $name;

        return strtolower(trim($name));
    }

    private static function normalizeHtmlMetaProperty(string $property): string
    {
        $property = str_replace("\0", '', $property);
        $property = preg_replace('/[\t\r\n\f ]+/u', '', $property) ?? $property;

        return strtolower(trim($property));
    }

    private static function htmlMetaReviewLabel(string $name): string
    {
        return match ($name) {
            'article:modified_time' => 'Article modified time',
            'article:published_time' => 'Article published time',
            'author' => 'Author',
            'description' => 'Description',
            'generator' => 'Generator',
            'keywords' => 'Keywords',
            'content-security-policy' => 'Content security policy',
            'og:image' => 'Open Graph image',
            'og:image:secure_url' => 'Open Graph secure image',
            'og:image:url' => 'Open Graph image',
            'og:description' => 'Open Graph description',
            'og:title' => 'Open Graph title',
            'referrer' => 'Referrer policy',
            'twitter:description' => 'Twitter description',
            'twitter:image' => 'Twitter image',
            'twitter:image:src' => 'Twitter image',
            'twitter:title' => 'Twitter title',
            default => 'Metadata',
        };
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>
     */
    private static function htmlDocumentElementMetadataNodes(string $html, array &$diagnostics): array
    {
        if (preg_match('/^\s*<html(?:\s|>|\/)/i', $html) !== 1) {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $htmlElement = $loaded ? $dom->documentElement : null;
        if (!$htmlElement instanceof \DOMElement || strtolower($htmlElement->tagName) !== 'html') {
            return [];
        }

        $nodes = [];
        $language = self::htmlDocumentLanguage($htmlElement, $diagnostics);
        if ($language !== null) {
            $nodes[] = self::htmlDocumentMetadataSpan('language', $language, 'Language');
        }

        $direction = self::htmlDocumentDirection($htmlElement, $diagnostics);
        if ($direction !== null) {
            $nodes[] = self::htmlDocumentMetadataSpan('direction', $direction, 'Direction');
        }

        return $nodes;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function htmlDocumentLanguage(\DOMElement $htmlElement, array &$diagnostics): ?string
    {
        $attributeName = $htmlElement->hasAttribute('lang') ? 'lang' : ($htmlElement->hasAttribute('xml:lang') ? 'xml:lang' : '');
        if ($attributeName === '') {
            return null;
        }

        $language = self::normalizeHtmlLanguageTag($htmlElement->getAttribute($attributeName));
        if ($language === null) {
            $diagnostics[] = [
                'code' => 'unsafe-attribute',
                'tag' => 'html',
                'attribute' => $attributeName,
            ];

            return null;
        }

        $diagnostics[] = [
            'code' => 'document-metadata-review',
            'tag' => 'html',
            'attribute' => $attributeName,
            'name' => 'language',
        ];

        return $language;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function htmlDocumentDirection(\DOMElement $htmlElement, array &$diagnostics): ?string
    {
        if (!$htmlElement->hasAttribute('dir')) {
            return null;
        }

        $direction = strtolower(self::cleanHtmlMetadataAttribute($htmlElement->getAttribute('dir')));
        if (!in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
            if ($direction !== '') {
                $diagnostics[] = [
                    'code' => 'unsafe-attribute',
                    'tag' => 'html',
                    'attribute' => 'dir',
                    'value' => $direction,
                ];
            }

            return null;
        }

        $diagnostics[] = [
            'code' => 'document-metadata-review',
            'tag' => 'html',
            'attribute' => 'dir',
            'name' => 'direction',
        ];

        return $direction;
    }

    private static function normalizeHtmlLanguageTag(string $value): ?string
    {
        $tag = self::cleanHtmlMetadataAttribute($value);
        if ($tag === '' || strlen($tag) > 64 || preg_match('/\s/u', $tag) === 1) {
            return null;
        }

        if (preg_match('/^[A-Za-z]{1,8}(?:-[A-Za-z0-9]{1,8})*$/', $tag) !== 1) {
            return null;
        }

        $parts = explode('-', $tag);
        if (count($parts) === 1 && strlen($parts[0]) === 1) {
            return null;
        }

        $normalized = [];
        foreach ($parts as $index => $part) {
            if ($index === 0 || strtolower($parts[0]) === 'x') {
                $normalized[] = strtolower($part);
                continue;
            }

            if (preg_match('/^[A-Za-z]{4}$/', $part) === 1) {
                $normalized[] = ucfirst(strtolower($part));
                continue;
            }

            if (preg_match('/^[A-Za-z]{2}$/', $part) === 1) {
                $normalized[] = strtoupper($part);
                continue;
            }

            $normalized[] = strtolower($part);
        }

        return implode('-', $normalized);
    }

    /**
     * @return array<string, mixed>
     */
    private static function htmlDocumentMetadataSpan(string $name, string $content, string $label): array
    {
        return [
            'type' => 'element',
            'name' => 'span',
            'attrs' => [
                'data-pandoc-meta-name' => $name,
                'data-pandoc-meta-source' => 'html',
                'data-pandoc-meta-content' => $content,
            ],
            'children' => [[
                'type' => 'text',
                'text' => $label . ': ' . $content,
            ]],
        ];
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
        if (!$element->hasAttributes() && $mode !== 'xml') {
            return [];
        }

        $attrs = $mode === 'xml' ? self::xmlNamespaceDeclarationAttributes($element) : [];
        foreach ($element->attributes as $attribute) {
            $name = $mode === 'xml'
                ? self::xmlAttributeName($attribute)
                : strtolower($attribute->name);
            if ($foreignContext !== null) {
                $name = XmlHtmlDom::adjustHtmlForeignAttributeName($name);
            }
            $value = str_replace("\0", '', $attribute->value);

            if (!self::isSafeAttributeName($name)) {
                $diagnostics[] = self::diagnosticWithSourceLine([
                    'code' => 'unsafe-attribute',
                    'tag' => $tagName,
                    'attribute' => $name,
                ], $element);
                continue;
            }

            if ($mode === 'html' && strtolower($name) === 'style') {
                $style = self::normalizeHtmlStyleAttribute($value, $tagName, $diagnostics);
                if ($style !== null) {
                    $attrs['data-pandoc-style'] = $style;
                }
                continue;
            }

            if ($mode === 'html' && self::isBlockedAttribute($name, $foreignContext)) {
                $diagnostics[] = self::diagnosticWithSourceLine([
                    'code' => 'unsafe-attribute',
                    'tag' => $tagName,
                    'attribute' => $name,
                ], $element);
                continue;
            }

            if ($mode === 'html' && self::isHtmlSemanticMetadataAttribute($name)) {
                $metadataAttrs = self::normalizeHtmlSemanticMetadataAttribute(
                    $name,
                    $value,
                    $tagName,
                    $diagnostics,
                    $baseUrl
                );
                foreach ($metadataAttrs as $metadataName => $metadataValue) {
                    $attrs[$metadataName] = $metadataValue;
                }
                continue;
            }

            if ($mode === 'html' && strtolower($tagName) === 'track') {
                $trackAttributeName = strtolower($name);
                if ($trackAttributeName === 'kind') {
                    $kind = self::normalizeHtmlTrackKindAttribute($value, $diagnostics);
                    if ($kind !== null) {
                        $attrs[$name] = $kind;
                    }
                    continue;
                }
                if ($trackAttributeName === 'srclang') {
                    $language = self::normalizeHtmlTrackLanguageAttribute($value, $diagnostics);
                    if ($language !== null) {
                        $attrs[$name] = $language;
                    }
                    continue;
                }
                if ($trackAttributeName === 'label') {
                    $label = self::cleanHtmlMetadataAttribute($value);
                    if ($label !== '') {
                        $attrs[$name] = $label;
                    }
                    continue;
                }
                if ($trackAttributeName === 'default') {
                    $attrs[$name] = '';
                    continue;
                }
            }

            if ($mode === 'html' && strtolower($name) === 'srcset') {
                $srcset = self::normalizeSrcsetAttribute($value, $tagName, $diagnostics, $baseUrl);
                if ($srcset === null) {
                    continue;
                }

                $attrs[$name] = $srcset;
                continue;
            }

            if ($mode === 'html' && strtolower($name) === 'rel') {
                $rel = self::normalizeHtmlRelAttribute($value, $tagName, $diagnostics);
                if ($rel === null) {
                    continue;
                }

                $attrs[$name] = $rel;
                continue;
            }

            if ($mode === 'html' && self::isSvgPresentationResourceAttribute($name, $foreignContext)) {
                $resourceValue = self::normalizeSvgPresentationResourceAttribute(
                    $value,
                    $tagName,
                    $name,
                    $diagnostics,
                    $baseUrl
                );
                if ($resourceValue === null) {
                    continue;
                }

                $attrs[$name] = $resourceValue;
                continue;
            }

            if ($mode === 'html' && self::isUrlAttribute($name)) {
                if (!self::isSafeUrlAttributeValue($tagName, $name, $value, $foreignContext)) {
                    $diagnostics[] = self::diagnosticWithSourceLine([
                        'code' => 'unsafe-url',
                        'tag' => $tagName,
                        'attribute' => $name,
                    ], $element);
                    continue;
                }

                $normalizedUrl = self::normalizeUrlAttributeValue($value);
                if ($normalizedUrl !== $value) {
                    $diagnostics[] = self::diagnosticWithSourceLine([
                        'code' => 'normalized-url',
                        'tag' => $tagName,
                        'attribute' => $name,
                    ], $element);
                }

                $value = $mode === 'html'
                    && $baseUrl !== null
                    && !self::isLocalSvgReferenceUrl($tagName, $name, $normalizedUrl, $foreignContext)
                    && !self::isLocalImageMapReferenceUrl($name, $normalizedUrl)
                    ? self::resolveRelativeUrl($baseUrl, $normalizedUrl)
                    : $normalizedUrl;
            }

            $attrs[$name] = $value;
        }

        return $attrs;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlTrackKindAttribute(string $value, array &$diagnostics): ?string
    {
        $kind = strtolower(self::cleanHtmlMetadataAttribute($value));
        if ($kind === '') {
            return null;
        }

        if (in_array($kind, ['subtitles', 'captions', 'descriptions', 'chapters', 'metadata'], true)) {
            return $kind;
        }

        $diagnostics[] = [
            'code' => 'unsafe-attribute',
            'tag' => 'track',
            'attribute' => 'kind',
            'value' => $kind,
        ];

        return null;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlTrackLanguageAttribute(string $value, array &$diagnostics): ?string
    {
        $language = self::cleanHtmlMetadataAttribute($value);
        if ($language === '') {
            return null;
        }

        $parts = explode('-', $language);
        $canonical = [];
        foreach ($parts as $index => $part) {
            if (preg_match('/^[A-Za-z0-9]{1,8}$/', $part) !== 1) {
                $diagnostics[] = [
                    'code' => 'unsafe-attribute',
                    'tag' => 'track',
                    'attribute' => 'srclang',
                    'value' => $language,
                ];

                return null;
            }

            if ($index === 0) {
                if (preg_match('/^(?:[A-Za-z]{2,8}|x)$/', $part) !== 1) {
                    $diagnostics[] = [
                        'code' => 'unsafe-attribute',
                        'tag' => 'track',
                        'attribute' => 'srclang',
                        'value' => $language,
                    ];

                    return null;
                }

                $canonical[] = strtolower($part);
                continue;
            }

            if (strlen($part) === 4 && preg_match('/^[A-Za-z]{4}$/', $part) === 1) {
                $canonical[] = ucfirst(strtolower($part));
                continue;
            }
            if (
                (strlen($part) === 2 && preg_match('/^[A-Za-z]{2}$/', $part) === 1)
                || (strlen($part) === 3 && preg_match('/^[0-9]{3}$/', $part) === 1)
            ) {
                $canonical[] = strtoupper($part);
                continue;
            }

            $canonical[] = strtolower($part);
        }

        if ($canonical[0] === 'x' && count($canonical) === 1) {
            $diagnostics[] = [
                'code' => 'unsafe-attribute',
                'tag' => 'track',
                'attribute' => 'srclang',
                'value' => $language,
            ];

            return null;
        }

        return implode('-', $canonical);
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

    private static function isHtmlSemanticMetadataAttribute(string $name): bool
    {
        return in_array(strtolower($name), [
            'about',
            'datatype',
            'inlist',
            'itemid',
            'itemprop',
            'itemref',
            'itemscope',
            'itemtype',
            'prefix',
            'property',
            'resource',
            'typeof',
            'vocab',
        ], true);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, string>
     */
    private static function normalizeHtmlSemanticMetadataAttribute(
        string $name,
        string $value,
        string $tagName,
        array &$diagnostics,
        ?string $baseUrl
    ): array {
        $name = strtolower($name);
        if ($name === 'itemscope' || $name === 'inlist') {
            self::addSemanticMetadataDiagnostic($diagnostics, $tagName, $name);

            return [
                $name === 'itemscope' ? 'data-pandoc-microdata-scope' : 'data-pandoc-rdfa-inlist' => 'true',
            ];
        }

        if ($name === 'itemtype') {
            $tokens = self::normalizeHtmlSemanticUrlTokenList($value, $tagName, $name, $diagnostics, $baseUrl);

            return $tokens === null ? [] : ['data-pandoc-microdata-type' => $tokens];
        }

        if ($name === 'itemid') {
            $url = self::normalizeHtmlSemanticUrl($value, $tagName, $name, $diagnostics, $baseUrl);
            if ($url !== null) {
                self::addSemanticMetadataDiagnostic($diagnostics, $tagName, $name);
            }

            return $url === null ? [] : ['data-pandoc-microdata-id' => $url];
        }

        if ($name === 'about' || $name === 'resource' || $name === 'vocab') {
            $url = self::normalizeHtmlSemanticUrl($value, $tagName, $name, $diagnostics, $baseUrl);
            if ($url === null) {
                return [];
            }

            self::addSemanticMetadataDiagnostic($diagnostics, $tagName, $name);

            return ['data-pandoc-rdfa-' . $name => $url];
        }

        if ($name === 'prefix') {
            $prefixes = self::normalizeHtmlRdfaPrefixMap($value, $tagName, $diagnostics, $baseUrl);

            return $prefixes === null ? [] : ['data-pandoc-rdfa-prefix' => $prefixes];
        }

        $targetName = match ($name) {
            'datatype' => 'data-pandoc-rdfa-datatype',
            'itemprop' => 'data-pandoc-microdata-property',
            'itemref' => 'data-pandoc-microdata-ref',
            'property' => 'data-pandoc-rdfa-property',
            'typeof' => 'data-pandoc-rdfa-typeof',
            default => null,
        };
        if ($targetName === null) {
            return [];
        }

        $tokens = self::normalizeHtmlSemanticTermTokenList($value, $tagName, $name, $diagnostics);

        return $tokens === null ? [] : [$targetName => $tokens];
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function addSemanticMetadataDiagnostic(array &$diagnostics, string $tagName, string $attributeName): void
    {
        $diagnostics[] = [
            'code' => 'semantic-metadata-review',
            'tag' => $tagName,
            'attribute' => $attributeName,
        ];
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlSemanticUrl(
        string $value,
        string $tagName,
        string $attributeName,
        array &$diagnostics,
        ?string $baseUrl
    ): ?string {
        $normalized = self::normalizeUrlAttributeValue($value);
        if ($normalized === '' || !self::isSafeFetchUrl($normalized)) {
            $diagnostics[] = [
                'code' => 'unsafe-url',
                'tag' => $tagName,
                'attribute' => $attributeName,
            ];

            return null;
        }

        if ($normalized !== $value) {
            $diagnostics[] = [
                'code' => 'normalized-url',
                'tag' => $tagName,
                'attribute' => $attributeName,
            ];
        }

        if ($baseUrl !== null) {
            $normalized = self::resolveRelativeUrl($baseUrl, $normalized);
        }

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlSemanticUrlTokenList(
        string $value,
        string $tagName,
        string $attributeName,
        array &$diagnostics,
        ?string $baseUrl
    ): ?string {
        if (self::hasCompactUnsafeSemanticScheme($value)) {
            $diagnostics[] = [
                'code' => 'unsafe-url',
                'tag' => $tagName,
                'attribute' => $attributeName,
            ];

            return null;
        }

        $tokens = self::splitHtmlSemanticTokens($value);
        if ($tokens === []) {
            return null;
        }

        $normalized = [];
        foreach ($tokens as $token) {
            $url = self::normalizeHtmlSemanticUrl($token, $tagName, $attributeName, $diagnostics, $baseUrl);
            if ($url === null || in_array($url, $normalized, true)) {
                continue;
            }

            $normalized[] = $url;
        }

        if ($normalized === []) {
            return null;
        }

        self::addSemanticMetadataDiagnostic($diagnostics, $tagName, $attributeName);

        return implode(' ', $normalized);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlSemanticTermTokenList(
        string $value,
        string $tagName,
        string $attributeName,
        array &$diagnostics
    ): ?string {
        if (self::hasCompactUnsafeSemanticScheme($value)) {
            $diagnostics[] = [
                'code' => 'unsafe-attribute',
                'tag' => $tagName,
                'attribute' => $attributeName,
            ];

            return null;
        }

        $tokens = self::splitHtmlSemanticTokens($value);
        if ($tokens === []) {
            return null;
        }

        $normalized = [];
        foreach ($tokens as $token) {
            if (!self::isSafeHtmlSemanticTermToken($token)) {
                $diagnostics[] = [
                    'code' => 'unsafe-attribute',
                    'tag' => $tagName,
                    'attribute' => $attributeName,
                    'token' => $token,
                ];
                continue;
            }

            if (!in_array($token, $normalized, true)) {
                $normalized[] = $token;
            }
        }

        if ($normalized === []) {
            return null;
        }

        self::addSemanticMetadataDiagnostic($diagnostics, $tagName, $attributeName);

        return implode(' ', $normalized);
    }

    /**
     * @return list<string>
     */
    private static function splitHtmlSemanticTokens(string $value): array
    {
        $cleaned = self::cleanHtmlMetadataAttribute($value);
        if ($cleaned === '') {
            return [];
        }

        $tokens = preg_split('/[\x00-\x20]+/', $cleaned, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($tokens)) {
            return [];
        }

        return array_values(array_map(static fn (string $token): string => trim($token), $tokens));
    }

    private static function isSafeHtmlSemanticTermToken(string $token): bool
    {
        if ($token === '' || preg_match('/[<>{}`]/', $token) === 1) {
            return false;
        }

        if (self::isTrustedAbsoluteBaseUrl($token)) {
            return true;
        }

        $scheme = strtolower(strstr($token, ':', true) ?: '');
        if (in_array($scheme, ['javascript', 'vbscript', 'data'], true)) {
            return false;
        }
        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $token) === 1 && !str_contains($token, '://')) {
            return preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*:[A-Za-z0-9_.-]+$/', $token) === 1;
        }

        return preg_match('/^[A-Za-z_][A-Za-z0-9_.:-]*$/', $token) === 1;
    }

    private static function hasCompactUnsafeSemanticScheme(string $value): bool
    {
        $compact = strtolower(preg_replace('/[\x00-\x20]+/', '', $value) ?? $value);

        return preg_match('/(?:javascript|vbscript|data):/', $compact) === 1;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlRdfaPrefixMap(
        string $value,
        string $tagName,
        array &$diagnostics,
        ?string $baseUrl
    ): ?string {
        $cleaned = self::cleanHtmlMetadataAttribute($value);
        if ($cleaned === '' || preg_match('/[<>{}`]/', $cleaned) === 1) {
            return null;
        }

        if (preg_match_all('/([A-Za-z][A-Za-z0-9_-]*):\s+(\S+)/', $cleaned, $matches, PREG_SET_ORDER) !== false && $matches !== []) {
            $prefixes = [];
            foreach ($matches as $match) {
                $prefix = strtolower((string) $match[1]);
                $iri = self::normalizeHtmlSemanticUrl((string) $match[2], $tagName, 'prefix', $diagnostics, $baseUrl);
                if ($iri === null) {
                    continue;
                }

                $prefixes[$prefix] = $prefix . ': ' . $iri;
            }

            if ($prefixes === []) {
                return null;
            }

            self::addSemanticMetadataDiagnostic($diagnostics, $tagName, 'prefix');

            return implode(' ', array_values($prefixes));
        }

        $diagnostics[] = [
            'code' => 'unsafe-attribute',
            'tag' => $tagName,
            'attribute' => 'prefix',
        ];

        return null;
    }

    private static function xmlAttributeName(\DOMAttr $attribute): string
    {
        $prefix = $attribute->prefix;
        if (is_string($prefix) && $prefix !== '') {
            return $prefix . ':' . $attribute->localName;
        }

        return $attribute->name;
    }

    /**
     * @return array<string, string>
     */
    private static function xmlNamespaceDeclarationAttributes(\DOMElement $element): array
    {
        $attrs = [];
        $namespaceUri = $element->namespaceURI;
        $prefix = $element->prefix;

        if (is_string($namespaceUri) && $namespaceUri !== '') {
            if (is_string($prefix) && $prefix !== '') {
                if ($prefix !== 'xml') {
                    $attrs['xmlns:' . $prefix] = $namespaceUri;
                }
            } elseif (self::requiresXmlDefaultNamespaceDeclaration($element, $namespaceUri)) {
                $attrs['xmlns'] = $namespaceUri;
            }
        } elseif (self::requiresXmlDefaultNamespaceReset($element)) {
            $attrs['xmlns'] = '';
        }

        foreach ($element->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }

            $attributePrefix = $attribute->prefix;
            $attributeNamespace = $attribute->namespaceURI;
            if (
                !is_string($attributePrefix)
                || $attributePrefix === ''
                || $attributePrefix === 'xml'
                || $attributePrefix === 'xmlns'
                || !is_string($attributeNamespace)
                || $attributeNamespace === ''
            ) {
                continue;
            }

            $attrs['xmlns:' . $attributePrefix] ??= $attributeNamespace;
        }

        return $attrs;
    }

    private static function requiresXmlDefaultNamespaceDeclaration(\DOMElement $element, string $namespaceUri): bool
    {
        $parent = $element->parentNode;
        if (!$parent instanceof \DOMElement) {
            return true;
        }

        return ($parent->prefix ?? '') !== ''
            || ($parent->namespaceURI ?? '') !== $namespaceUri;
    }

    private static function requiresXmlDefaultNamespaceReset(\DOMElement $element): bool
    {
        $parent = $element->parentNode;

        return $parent instanceof \DOMElement
            && ($parent->lookupNamespaceURI(null) ?? '') !== '';
    }

    private static function isBlockedAttribute(string $name, ?string $foreignContext): bool
    {
        $lower = strtolower($name);

        return str_starts_with($lower, 'on')
            || $lower === 'download'
            || $lower === 'ping'
            || $lower === 'style'
            || $lower === 'srcdoc'
            || $lower === 'target'
            || ($foreignContext === null && ($lower === 'xmlns' || str_starts_with($lower, 'xmlns:')))
            || str_starts_with($lower, 'data-pandoc-');
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlStyleAttribute(string $value, string $tagName, array &$diagnostics): ?string
    {
        $declarations = [];
        foreach (self::splitCssDeclarations($value) as $declaration) {
            $declaration = trim($declaration);
            if ($declaration === '') {
                continue;
            }

            $colon = strpos($declaration, ':');
            if ($colon === false) {
                $diagnostics[] = self::unsafeStyleDiagnostic($tagName);
                continue;
            }

            $property = strtolower(trim(substr($declaration, 0, $colon)));
            if (!self::isReviewableHtmlStyleProperty($property)) {
                $diagnostics[] = self::unsafeStyleDiagnostic($tagName, $property);
                continue;
            }

            $propertyValue = self::normalizeReviewableHtmlStyleValue(substr($declaration, $colon + 1));
            if ($propertyValue === null) {
                $diagnostics[] = self::unsafeStyleDiagnostic($tagName, $property);
                continue;
            }

            $declarations[$property] = $property . ': ' . $propertyValue;
        }

        if ($declarations === []) {
            return null;
        }

        $diagnostics[] = [
            'code' => 'style-review-metadata',
            'tag' => $tagName,
            'attribute' => 'style',
            'declarations' => count($declarations),
        ];

        return implode('; ', array_values($declarations));
    }

    /**
     * @return list<string>
     */
    private static function splitCssDeclarations(string $value): array
    {
        $parts = [];
        $start = 0;
        $quote = null;
        $parenDepth = 0;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($char === '\\') {
                $i++;
                continue;
            }
            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }
            if ($char === '(') {
                $parenDepth++;
                continue;
            }
            if ($char === ')' && $parenDepth > 0) {
                $parenDepth--;
                continue;
            }
            if ($char !== ';' || $parenDepth !== 0) {
                continue;
            }

            $parts[] = substr($value, $start, $i - $start);
            $start = $i + 1;
        }

        $parts[] = substr($value, $start);

        return $parts;
    }

    private static function isReviewableHtmlStyleProperty(string $property): bool
    {
        return in_array($property, [
            'background-color',
            'border-color',
            'border-style',
            'border-width',
            'color',
            'direction',
            'font-family',
            'font-size',
            'font-style',
            'font-variant',
            'font-weight',
            'letter-spacing',
            'line-height',
            'margin-left',
            'margin-right',
            'text-align',
            'text-decoration',
            'text-transform',
            'vertical-align',
            'white-space',
        ], true);
    }

    private static function normalizeReviewableHtmlStyleValue(string $value): ?string
    {
        $value = trim(str_replace("\0", '', $value));
        if ($value === '' || strlen($value) > 256 || str_contains($value, '/*') || str_contains($value, '*/')) {
            return null;
        }

        $decoded = self::decodeCssEscapes($value);
        if ($decoded === null) {
            return null;
        }

        $lowerCompact = strtolower(preg_replace('/[\x00-\x20]+/', '', $decoded) ?? $decoded);
        foreach (['url(', 'expression(', '@import', 'javascript:', 'vbscript:', 'data:', '-moz-binding'] as $blockedToken) {
            if (str_contains($lowerCompact, $blockedToken)) {
                return null;
            }
        }
        if (preg_match('/[<>{}`]/', $decoded) === 1) {
            return null;
        }

        $decoded = preg_replace('/[\t\r\n\f ]+/u', ' ', $decoded) ?? $decoded;
        $decoded = preg_replace('/\s*,\s*/u', ', ', $decoded) ?? $decoded;
        $decoded = preg_replace('/\(\s+/u', '(', $decoded) ?? $decoded;
        $decoded = preg_replace('/\s+\)/u', ')', $decoded) ?? $decoded;

        return trim($decoded);
    }

    /**
     * @return array<string, mixed>
     */
    private static function unsafeStyleDiagnostic(string $tagName, string $property = ''): array
    {
        $diagnostic = [
            'code' => 'unsafe-attribute',
            'tag' => $tagName,
            'attribute' => 'style',
        ];
        if ($property !== '') {
            $diagnostic['property'] = $property;
        }

        return $diagnostic;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeHtmlRelAttribute(string $value, string $tagName, array &$diagnostics): ?string
    {
        $tokens = preg_split('/[\x00-\x20]+/', strtolower(trim($value)));
        if (!is_array($tokens)) {
            return null;
        }

        $normalized = [];
        $removedOpener = false;
        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token === '') {
                continue;
            }
            if ($token === 'opener') {
                $removedOpener = true;
                continue;
            }
            if (!in_array($token, $normalized, true)) {
                $normalized[] = $token;
            }
        }

        if ($removedOpener) {
            $diagnostics[] = [
                'code' => 'unsafe-attribute',
                'tag' => $tagName,
                'attribute' => 'rel',
                'token' => 'opener',
            ];
        }

        return $normalized === [] ? null : implode(' ', $normalized);
    }

    private static function isUrlAttribute(string $name): bool
    {
        return in_array(strtolower($name), [
            'action',
            'background',
            'cite',
            'codebase',
            'data',
            'dynsrc',
            'formaction',
            'href',
            'longdesc',
            'lowsrc',
            'manifest',
            'poster',
            'profile',
            'src',
            'srcset',
            'usemap',
            'xlink:href',
        ], true);
    }

    private static function isSvgPresentationResourceAttribute(string $name, ?string $foreignContext): bool
    {
        return $foreignContext === 'svg'
            && in_array(strtolower($name), [
                'clip-path',
                'color-profile',
                'cursor',
                'fill',
                'filter',
                'marker',
                'marker-end',
                'marker-mid',
                'marker-start',
                'mask',
                'stroke',
            ], true);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function normalizeSvgPresentationResourceAttribute(
        string $value,
        string $tagName,
        string $name,
        array &$diagnostics,
        ?string $baseUrl
    ): ?string {
        if (stripos($value, 'url(') === false) {
            return $value;
        }

        $hasUnsafeUrl = false;
        $matchedUrlFunction = false;
        $normalized = preg_replace_callback(
            '/url\(\s*([^)]+?)\s*\)/i',
            static function (array $matches) use (&$hasUnsafeUrl, &$matchedUrlFunction, $baseUrl): string {
                $matchedUrlFunction = true;
                $url = self::normalizeCssUrlToken((string) $matches[1]);
                if ($url === null || $url === '' || !self::isSafeFetchUrl($url)) {
                    $hasUnsafeUrl = true;

                    return '';
                }
                if ($baseUrl !== null && !str_starts_with($url, '#')) {
                    $url = self::resolveRelativeUrl($baseUrl, $url);
                }

                return 'url(' . $url . ')';
            },
            $value
        );

        if (!is_string($normalized) || !$matchedUrlFunction || $hasUnsafeUrl) {
            $diagnostics[] = [
                'code' => 'unsafe-url',
                'tag' => $tagName,
                'attribute' => $name,
            ];

            return null;
        }

        $normalized = trim($normalized);
        if ($normalized !== $value) {
            $diagnostics[] = [
                'code' => 'normalized-url',
                'tag' => $tagName,
                'attribute' => $name,
            ];
        }

        return $normalized;
    }

    private static function normalizeCssUrlToken(string $url): ?string
    {
        $url = trim($url);
        if (str_contains($url, '/*') || str_contains($url, '*/')) {
            return null;
        }

        if (strlen($url) >= 2) {
            $quote = $url[0];
            if ($quote === '"' || $quote === "'") {
                $url = substr($url, 1);
                if (str_ends_with($url, $quote)) {
                    $url = substr($url, 0, -1);
                }
            }
        }

        $url = self::decodeCssEscapes($url);
        if ($url === null) {
            return null;
        }

        return self::normalizeUrlAttributeValue($url);
    }

    private static function decodeCssEscapes(string $value): ?string
    {
        $invalid = false;
        $decoded = preg_replace_callback(
            '/\\\\(?:([0-9A-Fa-f]{1,6})(?:\r\n|[ \t\r\n\f])?|(.))/su',
            static function (array $matches) use (&$invalid): string {
                if (isset($matches[1]) && $matches[1] !== '') {
                    $codepoint = hexdec((string) $matches[1]);
                    if (!is_int($codepoint) || !self::isValidCssUrlCodepoint($codepoint)) {
                        $invalid = true;

                        return '';
                    }

                    return self::codepointToUtf8($codepoint);
                }

                $escaped = (string) ($matches[2] ?? '');
                if ($escaped === '' || preg_match('/[\r\n\f]/', $escaped) === 1) {
                    $invalid = true;

                    return '';
                }

                return $escaped;
            },
            $value
        );

        if (!is_string($decoded) || $invalid) {
            return null;
        }

        return $decoded;
    }

    private static function isValidCssUrlCodepoint(int $codepoint): bool
    {
        return $codepoint > 0
            && $codepoint <= 0x10FFFF
            && ($codepoint < 0xD800 || $codepoint > 0xDFFF);
    }

    private static function codepointToUtf8(int $codepoint): string
    {
        if ($codepoint <= 0x7F) {
            return chr($codepoint);
        }
        if ($codepoint <= 0x7FF) {
            return chr(0xC0 | ($codepoint >> 6))
                . chr(0x80 | ($codepoint & 0x3F));
        }
        if ($codepoint <= 0xFFFF) {
            return chr(0xE0 | ($codepoint >> 12))
                . chr(0x80 | (($codepoint >> 6) & 0x3F))
                . chr(0x80 | ($codepoint & 0x3F));
        }

        return chr(0xF0 | ($codepoint >> 18))
            . chr(0x80 | (($codepoint >> 12) & 0x3F))
            . chr(0x80 | (($codepoint >> 6) & 0x3F))
            . chr(0x80 | ($codepoint & 0x3F));
    }

    private static function isSafeUrlAttributeValue(string $tagName, string $name, string $value, ?string $foreignContext): bool
    {
        if (strtolower($name) === 'usemap') {
            return self::isSafeImageMapReference($value);
        }

        if (self::isSafeRasterImageSourceAttribute($tagName, $name, $value, $foreignContext)) {
            return true;
        }

        if (self::isSvgResourceReferenceAttribute($tagName, $name, $foreignContext)) {
            return self::isSafeFetchUrl($value);
        }

        if (in_array(strtolower($name), [
            'action',
            'background',
            'codebase',
            'data',
            'dynsrc',
            'formaction',
            'longdesc',
            'lowsrc',
            'manifest',
            'poster',
            'profile',
            'src',
        ], true)) {
            return self::isSafeFetchUrl($value);
        }

        return self::isSafeUrl($value);
    }

    private static function isSafeRasterImageSourceAttribute(string $tagName, string $name, string $value, ?string $foreignContext): bool
    {
        $lowerTagName = strtolower($tagName);
        $lowerName = strtolower($name);
        $isHtmlImageSource = $lowerTagName === 'img' && $lowerName === 'src';
        $isSvgImageResource = $foreignContext === 'svg'
            && in_array($lowerTagName, ['image', 'feimage'], true)
            && in_array($lowerName, ['href', 'xlink:href'], true);

        if (!$isHtmlImageSource && !$isSvgImageResource) {
            return false;
        }

        $normalized = self::normalizeUrlAttributeValue($value);

        return self::isSafeRasterImageDataUrl($normalized);
    }

    private static function isSvgResourceReferenceAttribute(string $tagName, string $name, ?string $foreignContext): bool
    {
        return $foreignContext === 'svg'
            && strtolower($tagName) !== 'a'
            && in_array(strtolower($name), ['href', 'xlink:href'], true);
    }

    private static function isLocalSvgReferenceUrl(string $tagName, string $name, string $value, ?string $foreignContext): bool
    {
        return self::isSvgResourceReferenceAttribute($tagName, $name, $foreignContext)
            && str_starts_with($value, '#');
    }

    private static function isLocalImageMapReferenceUrl(string $name, string $value): bool
    {
        return strtolower($name) === 'usemap' && str_starts_with($value, '#');
    }

    private static function isSafeImageMapReference(string $value): bool
    {
        $normalized = self::normalizeUrlAttributeValue($value);

        return preg_match('/^#[A-Za-z0-9_.:-]+$/', $normalized) === 1;
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
        foreach (self::splitSrcsetCandidates($value) as $candidate) {
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

    /**
     * @return list<string>
     */
    private static function splitSrcsetCandidates(string $value): array
    {
        $candidates = [];
        $start = 0;
        $offset = 0;

        while (($comma = strpos($value, ',', $offset)) !== false) {
            $candidatePrefix = substr($value, $start, $comma - $start);
            if (self::isDataUrlPayloadComma($candidatePrefix)) {
                $offset = $comma + 1;
                continue;
            }

            $candidates[] = $candidatePrefix;
            $start = $comma + 1;
            $offset = $start;
        }

        $candidates[] = substr($value, $start);

        return $candidates;
    }

    private static function isDataUrlPayloadComma(string $candidatePrefix): bool
    {
        $trimmed = trim($candidatePrefix);
        if ($trimmed === '' || preg_match('/[\x00-\x20]/', $trimmed) === 1) {
            return false;
        }

        return preg_match('/^data:[^,]*$/i', $trimmed) === 1;
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
        $parts = self::parseSrcsetCandidateParts($candidate);
        if ($parts === null) {
            return null;
        }

        [$url, $descriptor, $urlWasNormalized, $descriptorWasInvalid] = $parts;
        if ($url === '' || !self::isSafeImageCandidateUrl($url)) {
            $diagnostics[] = [
                'code' => 'unsafe-url',
                'tag' => $tagName,
                'attribute' => 'srcset',
                'candidate' => $candidate,
            ];

            return null;
        }

        if ($descriptorWasInvalid) {
            $diagnostics[] = [
                'code' => 'invalid-srcset-descriptor',
                'tag' => $tagName,
                'attribute' => 'srcset',
                'candidate' => $candidate,
                'descriptor' => self::invalidSrcsetDescriptorText($candidate),
            ];

            return null;
        }

        if ($urlWasNormalized) {
            $diagnostics[] = [
                'code' => 'normalized-url',
                'tag' => $tagName,
                'attribute' => 'srcset',
                'candidate' => $candidate,
            ];
        }

        if ($baseUrl !== null) {
            $url = self::resolveRelativeUrl($baseUrl, $url);
        }

        return $descriptor === '' ? $url : $url . ' ' . $descriptor;
    }

    /**
     * @return array{0:string, 1:string, 2:bool, 3:bool}|null
     */
    private static function parseSrcsetCandidateParts(string $candidate): ?array
    {
        $trimmed = trim($candidate);
        if ($trimmed === '') {
            return null;
        }

        $parts = preg_split('/[\x00-\x20]+/', $trimmed);
        if (!is_array($parts) || $parts === []) {
            return null;
        }

        $url = (string) array_shift($parts);
        $normalizedUrl = self::normalizeUrlAttributeValue($url);
        $descriptor = self::normalizeSrcsetDescriptor($parts);
        if (
            $descriptor !== null
            && $normalizedUrl !== ''
            && preg_match('/[\x00-\x20]/', $normalizedUrl) !== 1
        ) {
            return [$normalizedUrl, $descriptor, $normalizedUrl !== $url, false];
        }

        $controlSeparated = self::parseControlSeparatedSrcsetCandidate($trimmed);
        if ($controlSeparated !== null) {
            return $controlSeparated;
        }

        return [$normalizedUrl, '', $normalizedUrl !== $url, $parts !== []];
    }

    /**
     * @return array{0:string, 1:string, 2:bool, 3:bool}|null
     */
    private static function parseControlSeparatedSrcsetCandidate(string $candidate): ?array
    {
        if (preg_match('/^(.*?)[\x00-\x20]+([^\x00-\x20]+)$/s', $candidate, $matches) === 1) {
            $descriptor = self::normalizeSrcsetDescriptor([(string) $matches[2]]);
            if ($descriptor !== null) {
                $rawUrl = (string) $matches[1];
                $url = self::normalizeUrlAttributeValue($rawUrl);
                if ($url !== '' && preg_match('/[\x00-\x20]/', $url) !== 1) {
                    return [$url, $descriptor, $url !== $rawUrl, false];
                }
            }
        }

        $url = self::normalizeUrlAttributeValue($candidate);
        if ($url === $candidate || preg_match('/[\x00-\x20]/', $url) === 1) {
            return null;
        }
        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $url) !== 1) {
            return null;
        }

        return [$url, '', true, false];
    }

    private static function invalidSrcsetDescriptorText(string $candidate): string
    {
        $parts = preg_split('/[\x00-\x20]+/', trim($candidate));
        if (!is_array($parts) || count($parts) <= 1) {
            return '';
        }

        array_shift($parts);

        return implode(' ', $parts);
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
        if (self::isSafeRasterImageDataUrl($trimmed)) {
            return true;
        }
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

    private static function isSafeRasterImageDataUrl(string $value): bool
    {
        if (preg_match('/^data:image\/(?:png|gif|jpe?g|webp);base64,([A-Za-z0-9+\/]+={0,2})$/i', $value, $matches) !== 1) {
            return false;
        }

        return base64_decode((string) $matches[1], true) !== false;
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
            if (self::isInactiveFragmentBaseElement($baseElement)) {
                continue;
            }

            $href = trim($baseElement->getAttribute('href'));
            if ($href === '') {
                continue;
            }

            $normalizedHref = self::normalizeUrlAttributeValue($href);
            if ($normalizedHref === '') {
                continue;
            }
            if ($normalizedHref !== $href) {
                $diagnostics[] = [
                    'code' => 'normalized-url',
                    'tag' => 'base',
                    'attribute' => 'href',
                ];
            }

            $resolved = self::resolveBaseHref($normalizedHref, $documentBaseUrl, $diagnostics);
            if ($resolved !== null) {
                return $resolved;
            }

            break;
        }

        return $documentBaseUrl;
    }

    private static function isInactiveFragmentBaseElement(\DOMElement $element): bool
    {
        $parent = $element->parentNode;
        while ($parent instanceof \DOMElement) {
            $name = strtolower($parent->localName);
            if (isset(self::HTML_FRAGMENT_INACTIVE_BASE_ANCESTORS[$name])) {
                return true;
            }

            $parent = $parent->parentNode;
        }

        return false;
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
