<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class Html5Dom
{
    public const HTML_TREE_CONSTRUCTION_HTML_DOCUMENT = 'Dom\\HTMLDocument';
    public const HTML_TREE_CONSTRUCTION_LEGACY_COMPAT = 'DOMDocument-loadHTML-compat';
    public const HTML_FRAGMENT_CONTEXT_BODY = 'html-body-fragment-context';
    public const HTML_FRAGMENT_CONTEXT_TABLE = 'html-table-fragment-context';

    /** @var array<string, true> */
    private const HTML5_VOID_ELEMENTS = [
        'area' => true,
        'base' => true,
        'br' => true,
        'col' => true,
        'embed' => true,
        'hr' => true,
        'img' => true,
        'input' => true,
        'link' => true,
        'meta' => true,
        'source' => true,
        'track' => true,
        'wbr' => true,
    ];

    public static function htmlDocumentTreeConstructionBackend(): string
    {
        return self::nativeHtmlDocumentAvailable()
            ? self::HTML_TREE_CONSTRUCTION_HTML_DOCUMENT
            : self::HTML_TREE_CONSTRUCTION_LEGACY_COMPAT;
    }

    public static function htmlFragmentTreeConstructionBackend(string $html): string
    {
        return self::nativeHtmlDocumentAvailable()
            ? self::HTML_TREE_CONSTRUCTION_HTML_DOCUMENT
            : self::HTML_TREE_CONSTRUCTION_LEGACY_COMPAT;
    }

    public static function nativeHtmlDocumentAvailable(): bool
    {
        return class_exists('Dom\\HTMLDocument');
    }

    /**
     * Keep HTML5 tree construction on Dom\HTMLDocument while preserving
     * Pandoc-visible literal payloads such as template and textarea bodies.
     */
    public static function htmlTreeConstructionInput(
        string $html,
        bool $protectTemplateContent = true,
        bool $protectIframeContent = true,
        bool $protectRawTextContent = false,
        bool $protectNoscriptContent = true
    ): string {
        if (!self::htmlSourceNeedsLiteralPayloadProtection(
            $html,
            protectTemplateContent: $protectTemplateContent,
            protectIframeContent: $protectIframeContent,
            protectRawTextContent: $protectRawTextContent,
            protectNoscriptContent: $protectNoscriptContent
        )) {
            return $html;
        }

        return XmlHtmlDom::protectHtmlRcdataElements(
            $html,
            protectTemplateContent: $protectTemplateContent,
            protectIframeContent: $protectIframeContent,
            protectRawTextContent: $protectRawTextContent,
            protectNoscriptContent: $protectNoscriptContent
        );
    }

    /**
     * Parse a bounded HTML fragment under a synthetic body element.
     */
    public static function parseHtmlFragment(
        string $html,
        bool $protectRawTextContentForParse = false,
        bool $protectTemplateContentForParse = true,
        bool $protectIframeContentForParse = true,
        bool $protectNoscriptContentForParse = true
    ): \DOMElement
    {
        self::assertNoNullByte($html, 'HTML fragment');
        $preflight = XmlHtmlDom::protectHtmlRcdataElements(
            $html,
            protectTemplateContent: true,
            protectIframeContent: true,
            protectRawTextContent: true,
            protectNoscriptContent: true
        );
        self::assertNoHtmlFragmentDeclarations($preflight, 'HTML fragment');

        if (self::nativeHtmlDocumentAvailable()) {
            $fragment = self::html5TreeConstructedFragment(self::htmlTreeConstructionInput(
                $html,
                protectTemplateContent: $protectTemplateContentForParse,
                protectIframeContent: $protectIframeContentForParse,
                protectRawTextContent: $protectRawTextContentForParse,
                protectNoscriptContent: $protectNoscriptContentForParse
            ));
            if ($fragment === null) {
                throw new \RuntimeException('Unable to parse HTML fragment through Dom\\HTMLDocument');
            }

            try {
                $dom = self::loadLegacyHtml('<!doctype html><html><body>' . $fragment['source'] . '</body></html>', 'HTML fragment');
            } catch (\Throwable $exception) {
                throw new \RuntimeException('Unable to bridge Dom\\HTMLDocument output for HTML fragment', 0, $exception);
            }

            return self::requireBody($dom, 'HTML fragment');
        }

        $html = XmlHtmlDom::protectHtmlRcdataElements(
            $html,
            protectTemplateContent: $protectTemplateContentForParse,
            protectIframeContent: $protectIframeContentForParse,
            protectRawTextContent: $protectRawTextContentForParse,
            protectNoscriptContent: $protectNoscriptContentForParse
        );
        $dom = self::loadHtml(
            '<!doctype html><html><body>' . $html . '</body></html>',
            'HTML fragment',
            protectRcdata: false,
            preferHtml5TreeConstruction: false
        );

        return self::requireBody($dom, 'HTML fragment');
    }

    public static function htmlFragmentTreeConstructionContext(string $html): string
    {
        self::assertNoNullByte($html, 'HTML fragment');
        $preflight = XmlHtmlDom::protectHtmlRcdataElements(
            $html,
            protectTemplateContent: true,
            protectIframeContent: true,
            protectRawTextContent: true,
            protectNoscriptContent: true
        );
        self::assertNoHtmlFragmentDeclarations($preflight, 'HTML fragment');
        if (!self::nativeHtmlDocumentAvailable()) {
            return self::HTML_FRAGMENT_CONTEXT_BODY;
        }

        $fragment = self::html5TreeConstructedFragment(self::htmlTreeConstructionInput($html));
        if ($fragment === null) {
            throw new \RuntimeException('Unable to parse HTML fragment through Dom\\HTMLDocument');
        }

        return $fragment['context'];
    }

    /**
     * Parse a complete HTML document while keeping libxml network access off.
     */
    public static function parseHtmlDocument(string $html): \DOMDocument
    {
        self::assertSafeHtmlDocumentSource($html, 'HTML document');

        return self::loadHtml($html, 'HTML document');
    }

    public static function parseHtmlDocumentPreservingSourceLines(string $html, string $label = 'HTML document'): \DOMDocument
    {
        self::assertSafeHtmlDocumentSource($html, $label);

        return self::loadHtml($html, $label, prependEncodingDeclaration: false);
    }

    public static function treeConstructedHtmlSource(string $html): ?string
    {
        return self::html5TreeConstructedSource($html);
    }

    /**
     * @return array{name:string,selfClosing:bool,source:string,next:int,attributeNames:list<string>}|null
     */
    public static function rawHtmlOpeningTagAt(string $source, int $offset = 0): ?array
    {
        $length = strlen($source);
        if ($offset < 0 || $offset >= $length || ($source[$offset] ?? '') !== '<') {
            return null;
        }

        $nameStart = $offset + 1;
        if (!self::isAsciiAlpha($source[$nameStart] ?? '')) {
            return null;
        }

        $cursor = $nameStart + 1;
        while ($cursor < $length && self::isHtmlTagNameChar($source[$cursor])) {
            $cursor++;
        }

        $name = strtolower(substr($source, $nameStart, $cursor - $nameStart));
        $attributeNames = [];
        while ($cursor < $length) {
            $cursor = self::skipHtmlSpace($source, $cursor);
            if ($cursor >= $length) {
                return null;
            }

            if ($source[$cursor] === '>') {
                $next = $cursor + 1;
                $candidate = substr($source, $offset, $next - $offset);

                return self::htmlDocumentAcceptsRawTagCandidate($candidate)
                    ? [
                        'name' => $name,
                        'selfClosing' => false,
                        'source' => $candidate,
                        'next' => $next,
                        'attributeNames' => array_keys($attributeNames),
                    ]
                    : null;
            }

            if ($source[$cursor] === '/' && ($source[$cursor + 1] ?? '') === '>') {
                $next = $cursor + 2;
                $candidate = substr($source, $offset, $next - $offset);

                return self::htmlDocumentAcceptsRawTagCandidate($candidate)
                    ? [
                        'name' => $name,
                        'selfClosing' => true,
                        'source' => $candidate,
                        'next' => $next,
                        'attributeNames' => array_keys($attributeNames),
                    ]
                    : null;
            }

            if (!self::isHtmlAttributeNameStart($source[$cursor])) {
                return null;
            }

            $attributeStart = $cursor;
            $cursor++;
            while ($cursor < $length && self::isHtmlAttributeNameChar($source[$cursor])) {
                $cursor++;
            }
            $attributeNames[strtolower(substr($source, $attributeStart, $cursor - $attributeStart))] = true;

            $cursor = self::skipHtmlSpace($source, $cursor);
            if (($source[$cursor] ?? '') !== '=') {
                continue;
            }

            $cursor++;
            $cursor = self::skipHtmlSpace($source, $cursor);
            if ($cursor >= $length) {
                return null;
            }

            $quote = $source[$cursor];
            if ($quote === '"' || $quote === "'") {
                $end = strpos($source, $quote, $cursor + 1);
                if ($end === false) {
                    return null;
                }
                $cursor = $end + 1;
                continue;
            }

            $valueStart = $cursor;
            while ($cursor < $length && self::isHtmlUnquotedAttributeValueChar($source[$cursor])) {
                $cursor++;
            }
            if ($cursor === $valueStart) {
                return null;
            }
        }

        return null;
    }

    /**
     * @return array{name:string,source:string,next:int}|null
     */
    public static function rawHtmlClosingTagAt(string $source, int $offset = 0): ?array
    {
        $length = strlen($source);
        if (
            $offset < 0
            || $offset + 2 >= $length
            || ($source[$offset] ?? '') !== '<'
            || ($source[$offset + 1] ?? '') !== '/'
            || !self::isAsciiAlpha($source[$offset + 2] ?? '')
        ) {
            return null;
        }

        $nameStart = $offset + 2;
        $cursor = $nameStart + 1;
        while ($cursor < $length && self::isHtmlTagNameChar($source[$cursor])) {
            $cursor++;
        }

        $name = strtolower(substr($source, $nameStart, $cursor - $nameStart));
        $cursor = self::skipHtmlSpace($source, $cursor);
        if (($source[$cursor] ?? '') !== '>') {
            return null;
        }

        $next = $cursor + 1;
        $candidate = '<' . $name . '></' . $name . '>';
        if (!self::htmlDocumentAcceptsRawTagCandidate($candidate)) {
            return null;
        }

        return [
            'name' => $name,
            'source' => substr($source, $offset, $next - $offset),
            'next' => $next,
        ];
    }

    /**
     * @return array{name:string,selfClosing:bool,source:string,next:int,attributeNames:list<string>}|null
     */
    public static function markdownRawHtmlOpeningTagBoundary(string $line, int $maxIndent = 3): ?array
    {
        $offset = self::markdownRawHtmlBoundaryOffset($line, $maxIndent);

        return $offset === null ? null : self::rawHtmlOpeningTagAt($line, $offset);
    }

    /**
     * @return array{name:string,source:string,next:int}|null
     */
    public static function markdownRawHtmlClosingTagBoundary(string $line, int $maxIndent = 3): ?array
    {
        $offset = self::markdownRawHtmlBoundaryOffset($line, $maxIndent);
        if ($offset === null) {
            return null;
        }

        $tag = self::rawHtmlClosingTagAt($line, $offset);
        if ($tag === null) {
            return null;
        }

        return self::onlyHtmlSpaceAfter($line, $tag['next']) ? $tag : null;
    }

    public static function rawHtmlOpeningTagLineIsStandalone(string $line, string $name, int $maxIndent = 3): bool
    {
        $tag = self::markdownRawHtmlOpeningTagBoundary($line, $maxIndent);

        return $tag !== null
            && $tag['name'] === strtolower($name)
            && self::onlyHtmlSpaceAfter($line, $tag['next']);
    }

    public static function rawHtmlLineHasOpeningAndClosingTag(string $line, string $name, int $maxIndent = 3): bool
    {
        $tag = self::markdownRawHtmlOpeningTagBoundary($line, $maxIndent);
        if ($tag === null || $tag['name'] !== strtolower($name) || $tag['selfClosing']) {
            return false;
        }

        return self::rawHtmlClosingTagEndAfter($line, $tag['next'], $name) !== null;
    }

    public static function rawHtmlClosingTagEndAfter(string $source, int $offset, string $name): ?int
    {
        $name = strtolower($name);
        $cursor = max(0, $offset);
        while (($candidateOffset = strpos($source, '<', $cursor)) !== false) {
            $tag = self::rawHtmlClosingTagAt($source, $candidateOffset);
            if ($tag !== null && $tag['name'] === $name) {
                return $tag['next'];
            }
            $cursor = $candidateOffset + 1;
        }

        return null;
    }

    public static function rawHtmlSourceContainsOpeningTag(string $source, string $name): bool
    {
        $name = strtolower($name);
        $cursor = 0;
        while (($candidateOffset = strpos($source, '<', $cursor)) !== false) {
            $tag = self::rawHtmlOpeningTagAt($source, $candidateOffset);
            if ($tag !== null && $tag['name'] === $name) {
                return true;
            }
            $cursor = $candidateOffset + 1;
        }

        return false;
    }

    /**
     * Parse one or more XML fragment roots under a synthetic wrapper element.
     */
    public static function parseXmlFragment(string $xml, string $wrapperName = 'pandoc-fragment'): \DOMElement
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/', $wrapperName) !== 1) {
            throw new \InvalidArgumentException('XML fragment wrapper name must be a simple XML name');
        }

        self::assertSafeXmlSource($xml, 'XML fragment');
        $dom = self::loadXml('<' . $wrapperName . '>' . $xml . '</' . $wrapperName . '>', 'XML fragment');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement) {
            throw new \RuntimeException('XML fragment parser did not produce a wrapper element');
        }

        return $root;
    }

    /**
     * Parse one complete XML document without external entity expansion.
     */
    public static function parseXmlDocument(string $xml, string $label = 'XML document'): \DOMDocument
    {
        self::assertSafeXmlDocumentSource($xml, $label);

        return self::loadXml($xml, $label);
    }

    public static function serializeHtmlChildren(\DOMElement $element): string
    {
        $document = $element->ownerDocument;
        if (!$document instanceof \DOMDocument) {
            throw new \InvalidArgumentException('HTML element must belong to a DOMDocument');
        }

        return XmlHtmlDom::serializeHtmlChildren($element);
    }

    public static function serializeXmlChildren(\DOMElement $element): string
    {
        $document = $element->ownerDocument;
        if (!$document instanceof \DOMDocument) {
            throw new \InvalidArgumentException('XML element must belong to a DOMDocument');
        }

        $xml = '';
        foreach ($element->childNodes as $child) {
            $serialized = $document->saveXML($child);
            if ($serialized === false) {
                throw new \RuntimeException('Failed to serialize XML fragment child');
            }
            $xml .= $serialized;
        }

        return $xml;
    }

    /**
     * @return list<\DOMElement>
     */
    public static function childElements(\DOMElement $element, ?string $localName = null, ?string $namespace = null): array
    {
        $children = [];
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if (!self::elementMatches($child, $localName, $namespace)) {
                continue;
            }

            $children[] = $child;
        }

        return $children;
    }

    public static function firstChildElement(\DOMElement $element, ?string $localName = null, ?string $namespace = null): ?\DOMElement
    {
        foreach (self::childElements($element, $localName, $namespace) as $child) {
            return $child;
        }

        return null;
    }

    /**
     * @return list<\DOMElement>
     */
    public static function descendantElements(\DOMElement $element, ?string $localName = null, ?string $namespace = null): array
    {
        $descendants = [];
        foreach ($element->getElementsByTagName('*') as $child) {
            if (!$child instanceof \DOMElement || !self::elementMatches($child, $localName, $namespace)) {
                continue;
            }

            $descendants[] = $child;
        }

        return $descendants;
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(\DOMElement $element): array
    {
        $attributes = [];
        $isHtmlForeignElement = self::isHtmlDocumentElement($element) && self::isHtmlForeignElement($element);
        foreach ($element->attributes as $attribute) {
            if ($attribute instanceof \DOMAttr) {
                $name = self::isHtmlDocumentElement($element)
                    ? strtolower($attribute->name)
                    : ($attribute->prefix !== '' ? $attribute->prefix . ':' . $attribute->localName : $attribute->name);
                if ($isHtmlForeignElement) {
                    $name = XmlHtmlDom::adjustHtmlForeignAttributeName($name);
                }
                $attributes[$name] = $attribute->value;
            }
        }

        return $attributes;
    }

    public static function normalizedText(\DOMNode $node): string
    {
        $raw = self::textForNormalization($node);
        $text = preg_replace('/\s+/u', ' ', $raw) ?? $raw;

        return trim($text);
    }

    private static function loadHtml(
        string $html,
        string $label,
        bool $protectRcdata = true,
        bool $preferHtml5TreeConstruction = true,
        bool $prependEncodingDeclaration = true
    ): \DOMDocument
    {
        if ($preferHtml5TreeConstruction && self::nativeHtmlDocumentAvailable()) {
            $html5 = self::treeConstructedHtmlSource($protectRcdata ? self::htmlTreeConstructionInput($html) : $html);
            if ($html5 === null) {
                throw new \RuntimeException('Unable to parse ' . $label . ' through Dom\\HTMLDocument');
            }

            try {
                return self::loadLegacyHtml($html5, $label, $prependEncodingDeclaration);
            } catch (\Throwable $exception) {
                throw new \RuntimeException('Unable to bridge Dom\\HTMLDocument output for ' . $label, 0, $exception);
            }
        }

        if ($protectRcdata) {
            $html = XmlHtmlDom::protectHtmlRcdataElements(
                $html,
                protectTemplateContent: true,
                protectIframeContent: true,
                protectNoscriptContent: true
            );
        }

        // PHP < 8.4 has no Dom\HTMLDocument, so it uses the legacy libxml
        // parser with network access disabled.
        return self::loadLegacyHtml($html, $label, $prependEncodingDeclaration);
    }

    private static function loadLegacyHtml(string $html, string $label, bool $prependEncodingDeclaration = true): \DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->resolveExternals = false;
        $dom->substituteEntities = false;
        $loaded = $dom->loadHTML(
            ($prependEncodingDeclaration ? '<?xml encoding="UTF-8">' : '') . $html,
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new \RuntimeException('Unable to parse ' . $label);
        }

        self::repairLegacyVoidElementChildren($dom);

        return $dom;
    }

    private static function repairLegacyVoidElementChildren(\DOMDocument $dom): void
    {
        do {
            $moved = false;
            $elements = iterator_to_array($dom->getElementsByTagName('*'));
            foreach ($elements as $element) {
                if (!$element instanceof \DOMElement || !$element->hasChildNodes()) {
                    continue;
                }

                $name = strtolower($element->localName);
                if (!isset(self::HTML5_VOID_ELEMENTS[$name])) {
                    continue;
                }

                $parent = $element->parentNode;
                if (!$parent instanceof \DOMNode) {
                    continue;
                }

                $reference = $element->nextSibling;
                foreach (iterator_to_array($element->childNodes) as $child) {
                    if (!$child instanceof \DOMNode) {
                        continue;
                    }

                    $parent->insertBefore($child, $reference);
                    $moved = true;
                }
            }
        } while ($moved);
    }

    private static function htmlSourceNeedsLiteralPayloadProtection(
        string $html,
        bool $protectTemplateContent,
        bool $protectIframeContent,
        bool $protectRawTextContent,
        bool $protectNoscriptContent
    ): bool {
        $names = ['title', 'textarea', 'xmp', 'noembed', 'noframes', 'plaintext'];
        if ($protectTemplateContent) {
            $names[] = 'template';
        }
        if ($protectIframeContent) {
            $names[] = 'iframe';
        }
        if ($protectNoscriptContent) {
            $names[] = 'noscript';
        }
        if ($protectRawTextContent) {
            $names[] = 'script';
            $names[] = 'style';
        }

        foreach ($names as $name) {
            if (self::htmlSourceContainsElementStart($html, $name)) {
                return true;
            }
        }

        return false;
    }

    private static function htmlSourceContainsElementStart(string $html, string $name): bool
    {
        $source = strtolower($html);
        $needle = '<' . $name;
        $offset = 0;
        while (($offset = strpos($source, $needle, $offset)) !== false) {
            $afterName = $offset + strlen($needle);
            $next = $source[$afterName] ?? '';
            if ($next === '' || in_array($next, ["\t", "\n", "\f", "\r", ' ', '/', '>'], true)) {
                return true;
            }
            $offset = $afterName;
        }

        return false;
    }

    private static function markdownRawHtmlBoundaryOffset(string $line, int $maxIndent): ?int
    {
        $length = strlen($line);
        $offset = 0;
        $indent = 0;
        while ($offset < $length && $line[$offset] === ' ' && $indent < $maxIndent) {
            $offset++;
            $indent++;
        }

        return ($line[$offset] ?? '') === '<' ? $offset : null;
    }

    private static function onlyHtmlSpaceAfter(string $source, int $offset): bool
    {
        $length = strlen($source);
        for ($cursor = $offset; $cursor < $length; $cursor++) {
            if ($source[$cursor] !== ' ' && $source[$cursor] !== "\t") {
                return false;
            }
        }

        return true;
    }

    private static function skipHtmlSpace(string $source, int $offset): int
    {
        $length = strlen($source);
        while ($offset < $length && self::isHtmlSpace($source[$offset])) {
            $offset++;
        }

        return $offset;
    }

    private static function htmlDocumentAcceptsRawTagCandidate(string $candidate): bool
    {
        if (!self::nativeHtmlDocumentAvailable()) {
            return true;
        }

        $previous = libxml_use_internal_errors(true);
        try {
            \Dom\HTMLDocument::createFromString(
                '<!doctype html><html><body>' . $candidate . '</body></html>',
                LIBXML_NOERROR | LIBXML_COMPACT,
                'UTF-8'
            );

            return true;
        } catch (\Throwable) {
            return false;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function isHtmlTagNameChar(string $char): bool
    {
        return self::isAsciiAlpha($char) || ctype_digit($char) || in_array($char, ['-', ':'], true);
    }

    private static function isHtmlAttributeNameStart(string $char): bool
    {
        return self::isAsciiAlpha($char) || in_array($char, ['_', ':'], true);
    }

    private static function isHtmlAttributeNameChar(string $char): bool
    {
        return self::isHtmlAttributeNameStart($char)
            || ctype_digit($char)
            || in_array($char, ['.', '-'], true);
    }

    private static function isHtmlUnquotedAttributeValueChar(string $char): bool
    {
        return !self::isHtmlSpace($char)
            && !in_array($char, ['"', "'", '=', '<', '>', '`'], true);
    }

    private static function isHtmlSpace(string $char): bool
    {
        return in_array($char, ["\t", "\n", "\f", "\r", ' '], true);
    }

    private static function isAsciiAlpha(string $char): bool
    {
        return ($char >= 'a' && $char <= 'z') || ($char >= 'A' && $char <= 'Z');
    }

    private static function html5TreeConstructedSource(string $html): ?string
    {
        if (!self::nativeHtmlDocumentAvailable()) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = \Dom\HTMLDocument::createFromString(
                $html,
                LIBXML_NOERROR | LIBXML_COMPACT,
                'UTF-8'
            );

            return $document->saveHtml();
        } catch (\Throwable) {
            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function html5TreeConstructedFragmentSource(string $html): ?string
    {
        $fragment = self::html5TreeConstructedFragment($html);

        return $fragment['source'] ?? null;
    }

    /**
     * @return array{source:string, context:string}|null
     */
    private static function html5TreeConstructedFragment(string $html): ?array
    {
        if (!self::nativeHtmlDocumentAvailable()) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $normal = self::html5BodyContextFragment($html);
            if ($normal === null) {
                return null;
            }

            $table = self::html5TableContextFragment($html);
            if (
                $table !== null
                && !self::html5ElementHasTableStructure($normal['body'])
                && self::html5ElementChildrenHaveTableStructure($table['table'])
            ) {
                return [
                    'source' => $table['source'],
                    'context' => self::HTML_FRAGMENT_CONTEXT_TABLE,
                ];
            }

            return [
                'source' => $normal['source'],
                'context' => self::HTML_FRAGMENT_CONTEXT_BODY,
            ];
        } catch (\Throwable) {
            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * @return array{body:\Dom\Element, source:string}|null
     */
    private static function html5BodyContextFragment(string $html): ?array
    {
        $document = \Dom\HTMLDocument::createFromString(
            '<!doctype html><html><body>' . $html . '</body></html>',
            LIBXML_NOERROR | LIBXML_COMPACT,
            'UTF-8'
        );
        $body = self::html5FirstElementByTagName($document, 'body');
        if (!$body instanceof \Dom\Element) {
            return null;
        }

        return [
            'body' => $body,
            'source' => self::html5SerializeChildren($document, $body),
        ];
    }

    /**
     * @return array{table:\Dom\Element, source:string}|null
     */
    private static function html5TableContextFragment(string $html): ?array
    {
        $document = \Dom\HTMLDocument::createFromString(
            '<!doctype html><html><body><table id="pandoc-html-fragment-context"></table></body></html>',
            LIBXML_NOERROR | LIBXML_COMPACT,
            'UTF-8'
        );
        $table = $document->getElementById('pandoc-html-fragment-context');
        if (!$table instanceof \Dom\HTMLElement) {
            return null;
        }

        $table->insertAdjacentHTML(\Dom\AdjacentPosition::BeforeEnd, $html);

        return [
            'table' => $table,
            'source' => self::html5TableContextSource($document, $table),
        ];
    }

    private static function html5TableContextSource(\Dom\HTMLDocument $document, \Dom\Element $table): string
    {
        $source = '';
        $tableChildren = '';
        foreach ($table->childNodes as $child) {
            if ($child instanceof \Dom\Element && self::isHtml5TableContextChildName(strtolower($child->localName))) {
                $tableChildren .= $document->saveHtml($child);
                continue;
            }

            if ($tableChildren !== '') {
                $source .= '<table>' . $tableChildren . '</table>';
                $tableChildren = '';
            }

            $source .= $document->saveHtml($child);
        }

        if ($tableChildren !== '') {
            $source .= '<table>' . $tableChildren . '</table>';
        }

        return $source;
    }

    private static function isHtml5TableContextChildName(string $name): bool
    {
        return in_array($name, ['caption', 'col', 'colgroup', 'script', 'tbody', 'template', 'tfoot', 'thead', 'tr'], true);
    }

    private static function html5ElementHasTableStructure(\Dom\Element $element): bool
    {
        if (self::isHtml5TableStructureName(strtolower($element->localName))) {
            return true;
        }

        return self::html5ElementChildrenHaveTableStructure($element);
    }

    private static function html5ElementChildrenHaveTableStructure(\Dom\Element $element): bool
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof \Dom\Element && self::html5ElementHasTableStructure($child)) {
                return true;
            }
        }

        return false;
    }

    private static function isHtml5TableStructureName(string $name): bool
    {
        return in_array($name, ['caption', 'col', 'colgroup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr'], true);
    }

    private static function html5FirstElementByTagName(\Dom\HTMLDocument $document, string $name): ?\Dom\Element
    {
        $elements = $document->getElementsByTagName($name);
        $element = $elements->item(0);

        return $element instanceof \Dom\Element ? $element : null;
    }

    private static function html5SerializeChildren(\Dom\HTMLDocument $document, \Dom\Element $element): string
    {
        $source = '';
        foreach ($element->childNodes as $child) {
            $source .= $document->saveHtml($child);
        }

        return $source;
    }

    private static function loadXml(string $xml, string $label): \DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->resolveExternals = false;
        $dom->substituteEntities = false;
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new \RuntimeException('Unable to parse ' . $label);
        }

        self::assertNoProcessingInstructions($dom, $label);

        return $dom;
    }

    private static function requireBody(\DOMDocument $dom, string $label): \DOMElement
    {
        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body instanceof \DOMElement) {
            throw new \RuntimeException($label . ' parser did not produce a body element');
        }

        return $body;
    }

    private static function elementMatches(\DOMElement $element, ?string $localName, ?string $namespace): bool
    {
        if ($localName !== null) {
            $matchesRawName = $element->localName === $localName;
            $matchesAdjustedName = self::isHtmlDocumentElement($element)
                && XmlHtmlDom::htmlElementName($element) === $localName;
            if (!$matchesRawName && !$matchesAdjustedName) {
                return false;
            }
        }

        if ($namespace !== null && $element->namespaceURI !== $namespace) {
            return false;
        }

        return true;
    }

    private static function assertSafeXmlSource(string $xml, string $label): void
    {
        self::assertNoNullByte($xml, $label);
        $declarationScanSource = self::sourceForDeclarationScan($xml);
        if (preg_match('/<\?xml\b/i', $declarationScanSource) === 1) {
            throw new \InvalidArgumentException($label . ' must not include an XML declaration');
        }
        if (preg_match('/<\?[A-Za-z_][A-Za-z0-9_.:-]*/', $declarationScanSource) === 1) {
            throw new \InvalidArgumentException($label . ' must not include processing instructions');
        }
        if (preg_match('/<!\s*(?:DOCTYPE|ENTITY|ELEMENT|ATTLIST|NOTATION)\b/i', $declarationScanSource) === 1) {
            throw new \InvalidArgumentException($label . ' must not declare DTDs or entities');
        }
    }

    private static function assertSafeXmlDocumentSource(string $xml, string $label): void
    {
        self::assertNoNullByte($xml, $label);
        $declarationScanSource = self::sourceForDeclarationScan($xml);
        if (preg_match('/<!\s*(?:DOCTYPE|ENTITY|ELEMENT|ATTLIST|NOTATION)\b/i', $declarationScanSource) === 1) {
            throw new \InvalidArgumentException($label . ' must not declare DTDs or entities');
        }
    }

    private static function assertNoHtmlFragmentDeclarations(string $html, string $label): void
    {
        $declarationScanSource = self::sourceForDeclarationScan($html);
        if (preg_match('/<!\s*(?:DOCTYPE|ENTITY|ELEMENT|ATTLIST|NOTATION)\b/i', $declarationScanSource) === 1) {
            throw new \InvalidArgumentException($label . ' must not declare DTDs or entities');
        }
        if (preg_match('/<\?[A-Za-z_][A-Za-z0-9_.:-]*/', $declarationScanSource) === 1) {
            throw new \InvalidArgumentException($label . ' must not include processing instructions');
        }
    }

    private static function assertSafeHtmlDocumentSource(string $html, string $label): void
    {
        self::assertNoNullByte($html, $label);
        $preflight = XmlHtmlDom::protectHtmlRcdataElements(
            $html,
            protectTemplateContent: true,
            protectIframeContent: true,
            protectRawTextContent: true,
            protectNoscriptContent: true
        );
        $declarationScanSource = self::sourceForDeclarationScan($preflight);
        self::assertSimpleHtmlDocumentDoctype($declarationScanSource, $label);
        if (preg_match('/<!\s*DOCTYPE\b[^>]*\[/is', $declarationScanSource) === 1) {
            throw new \InvalidArgumentException($label . ' must not declare DTDs or entities');
        }
        if (preg_match('/<!\s*(?:ENTITY|ELEMENT|ATTLIST|NOTATION)\b/i', $declarationScanSource) === 1) {
            throw new \InvalidArgumentException($label . ' must not declare DTDs or entities');
        }
        if (preg_match('/<\?[A-Za-z_][A-Za-z0-9_.:-]*/', $declarationScanSource) === 1) {
            throw new \InvalidArgumentException($label . ' must not include processing instructions');
        }
    }

    private static function sourceForDeclarationScan(string $source): string
    {
        $length = strlen($source);
        $scan = '';
        $offset = 0;

        while ($offset < $length) {
            if (str_starts_with(substr($source, $offset, 9), '<![CDATA[')) {
                $cdataEnd = strpos($source, ']]>', $offset + 9);
                if ($cdataEnd === false) {
                    $scan .= substr($source, $offset);
                    break;
                }

                $cdataLength = $cdataEnd + 3 - $offset;
                $scan .= str_repeat(' ', $cdataLength);
                $offset += $cdataLength;
                continue;
            }

            if (str_starts_with(substr($source, $offset, 4), '<!--')) {
                $commentEnd = strpos($source, '-->', $offset + 4);
                if ($commentEnd === false) {
                    $scan .= substr($source, $offset);
                    break;
                }

                $commentLength = $commentEnd + 3 - $offset;
                $scan .= str_repeat(' ', $commentLength);
                $offset += $commentLength;
                continue;
            }

            if ($source[$offset] === '<' && self::isHtmlTagStartForDeclarationScan($source, $offset)) {
                [$tagSource, $nextOffset] = self::maskQuotedTagAttributeValuesForDeclarationScan($source, $offset);
                $scan .= $tagSource;
                $offset = $nextOffset;
                continue;
            }

            $scan .= $source[$offset];
            ++$offset;
        }

        return $scan;
    }

    private static function isHtmlTagStartForDeclarationScan(string $source, int $offset): bool
    {
        $length = strlen($source);
        $nameOffset = $offset + 1;
        if ($nameOffset >= $length) {
            return false;
        }

        if ($source[$nameOffset] === '/') {
            ++$nameOffset;
        }

        return $nameOffset < $length && preg_match('/[A-Za-z]/', $source[$nameOffset]) === 1;
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function maskQuotedTagAttributeValuesForDeclarationScan(string $source, int $offset): array
    {
        $length = strlen($source);
        $tag = '';

        while ($offset < $length) {
            $char = $source[$offset];
            if ($char === '"' || $char === "'") {
                $quoteEnd = strpos($source, $char, $offset + 1);
                if ($quoteEnd === false) {
                    $tag .= substr($source, $offset);

                    return [$tag, $length];
                }

                $tag .= $char . str_repeat(' ', $quoteEnd - $offset - 1) . $char;
                $offset = $quoteEnd + 1;
                continue;
            }

            $tag .= $char;
            ++$offset;
            if ($char === '>') {
                break;
            }
        }

        return [$tag, $offset];
    }

    private static function assertSimpleHtmlDocumentDoctype(string $html, string $label): void
    {
        $doctypeCount = preg_match_all('/<!\s*DOCTYPE\b([^>]*)>/is', $html, $matches);
        if ($doctypeCount === false) {
            return;
        }

        if (preg_match('/<!\s*DOCTYPE\b/is', $html) === 1 && $doctypeCount === 0) {
            throw new \InvalidArgumentException($label . ' must use a complete simple HTML doctype');
        }
        if ($doctypeCount > 1) {
            throw new \InvalidArgumentException($label . ' must not declare multiple doctypes');
        }
        if ($doctypeCount === 0) {
            return;
        }

        $doctypeName = preg_replace('/\s+/u', ' ', trim((string) $matches[1][0])) ?? trim((string) $matches[1][0]);
        if (strcasecmp($doctypeName, 'html') !== 0) {
            throw new \InvalidArgumentException($label . ' must use a simple HTML doctype without external identifiers or subsets');
        }
    }

    private static function assertNoNullByte(string $source, string $label): void
    {
        if (str_contains($source, "\0")) {
            throw new \InvalidArgumentException($label . ' must not contain NUL bytes');
        }
    }

    private static function assertNoProcessingInstructions(\DOMNode $node, string $label): void
    {
        if ($node instanceof \DOMProcessingInstruction) {
            throw new \InvalidArgumentException($label . ' must not include processing instructions');
        }

        foreach ($node->childNodes as $child) {
            self::assertNoProcessingInstructions($child, $label);
        }
    }

    private static function isHtmlDocumentElement(\DOMElement $element): bool
    {
        $document = $element->ownerDocument;
        $root = $document instanceof \DOMDocument ? $document->documentElement : null;

        return $root instanceof \DOMElement && strtolower($root->localName) === 'html';
    }

    private static function isHtmlForeignElement(\DOMElement $element): bool
    {
        $node = $element;
        $child = null;
        $isSelf = true;
        while ($node instanceof \DOMElement) {
            $name = strtolower($node->localName);
            if (!$isSelf && self::isHtmlIntegrationPoint($node)) {
                if (
                    !self::isMathMlTextIntegrationPointName($name)
                    || !$child instanceof \DOMElement
                    || !self::isMathMlTextIntegrationExceptionName(strtolower($child->localName))
                ) {
                    return false;
                }
            }
            if ($name === 'svg' || $name === 'math') {
                return true;
            }
            if ($name === 'html' || $name === 'body') {
                return false;
            }

            $parent = $node->parentNode;
            $child = $node;
            $node = $parent instanceof \DOMElement ? $parent : null;
            $isSelf = false;
        }

        return false;
    }

    private static function isHtmlIntegrationPoint(\DOMElement $element): bool
    {
        $name = strtolower($element->localName);
        if (self::isSvgHtmlIntegrationPointName($name)) {
            return true;
        }
        if (self::isMathMlTextIntegrationPointName($name)) {
            return true;
        }
        if ($name !== 'annotation-xml') {
            return false;
        }

        $encoding = strtolower(trim($element->getAttribute('encoding')));

        return $encoding === 'text/html' || $encoding === 'application/xhtml+xml';
    }

    private static function isSvgHtmlIntegrationPointName(string $name): bool
    {
        return in_array($name, ['foreignobject', 'desc', 'title'], true);
    }

    private static function isMathMlTextIntegrationPointName(string $name): bool
    {
        return in_array($name, ['mi', 'mn', 'mo', 'ms', 'mtext'], true);
    }

    private static function isMathMlTextIntegrationExceptionName(string $name): bool
    {
        return $name === 'mglyph' || $name === 'malignmark';
    }

    private static function textForNormalization(\DOMNode $node): string
    {
        if ($node instanceof \DOMText || $node instanceof \DOMCdataSection) {
            return $node->nodeValue ?? '';
        }

        if ($node instanceof \DOMElement && strtolower($node->localName) === 'br') {
            return "\n";
        }

        $text = '';
        foreach ($node->childNodes as $child) {
            $text .= self::textForNormalization($child);
        }

        return $text;
    }
}
