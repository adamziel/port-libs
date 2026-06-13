<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class XmlHtmlDom
{
    private const FRAGMENT_ROOT_ATTRIBUTE = 'data-port-libs-pandoc-fragment-root';
    private const IFRAME_SRCDOC_REVIEW_MAX_BYTES = 65536;
    private const NOSCRIPT_CONTENT_REVIEW_MAX_BYTES = 65536;
    private const TEMPLATE_CONTENT_REVIEW_MAX_BYTES = 65536;

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

    /** @var array<string, bool> true when the ARIA attribute accepts an ID reference list */
    private const ARIA_ID_REFERENCE_ATTRIBUTES = [
        'aria-activedescendant' => false,
        'aria-controls' => true,
        'aria-describedby' => true,
        'aria-details' => false,
        'aria-errormessage' => false,
        'aria-flowto' => true,
        'aria-labelledby' => true,
        'aria-owns' => true,
    ];

    /** @var array<string, true> */
    private const HTML5_RAW_TEXT_ELEMENTS = [
        'script' => true,
        'style' => true,
    ];

    /** @var array<string, string> */
    private const HTML5_ADDITIONAL_NAMED_CHARACTER_REFERENCES = [
        'ApplyFunction' => '&#x2061;',
        'InvisibleComma' => '&#x2063;',
        'InvisibleTimes' => '&#x2062;',
        'MediumSpace' => '&#x205F;',
        'NegativeMediumSpace' => '&#x200B;',
        'NegativeThickSpace' => '&#x200B;',
        'NegativeThinSpace' => '&#x200B;',
        'NegativeVeryThinSpace' => '&#x200B;',
        'NonBreakingSpace' => '&#x00A0;',
        'NoBreak' => '&#x2060;',
        'NewLine' => '&#x000A;',
        'Tab' => '&#x0009;',
        'ThickSpace' => '&#x205F;&#x200A;',
        'ThinSpace' => '&#x2009;',
        'VeryThinSpace' => '&#x200A;',
        'ZeroWidthSpace' => '&#x200B;',
        'af' => '&#x2061;',
        'ast' => '&#x002A;',
        'bsol' => '&#x005C;',
        'colon' => '&#x003A;',
        'comma' => '&#x002C;',
        'commat' => '&#x0040;',
        'copy' => '&#x00A9;',
        'dollar' => '&#x0024;',
        'equals' => '&#x003D;',
        'excl' => '&#x0021;',
        'hairsp' => '&#x200A;',
        'hopf' => '&#x1D559;',
        'ic' => '&#x2063;',
        'it' => '&#x2062;',
        'lbrack' => '&#x005B;',
        'lcub' => '&#x007B;',
        'lowbar' => '&#x005F;',
        'lpar' => '&#x0028;',
        'lsqb' => '&#x005B;',
        'nbsp' => '&#x00A0;',
        'num' => '&#x0023;',
        'period' => '&#x002E;',
        'percnt' => '&#x0025;',
        'plus' => '&#x002B;',
        'quest' => '&#x003F;',
        'rbrack' => '&#x005D;',
        'rcub' => '&#x007D;',
        'rpar' => '&#x0029;',
        'rsqb' => '&#x005D;',
        'semi' => '&#x003B;',
        'sol' => '&#x002F;',
        'thinsp' => '&#x2009;',
        'vert' => '&#x007C;',
    ];

    /** @var array<string, true> */
    private const HTML5_LEGACY_SEMICOLONLESS_CHARACTER_REFERENCES = [
        'copy' => true,
        'nbsp' => true,
    ];

    /** @var array<string, true> */
    private const HTML5_MARKUP_SENSITIVE_CHARACTER_REFERENCES = [
        'AMP' => true,
        'GT' => true,
        'LT' => true,
        'QUOT' => true,
        'amp' => true,
        'apos' => true,
        'gt' => true,
        'lt' => true,
        'quot' => true,
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

    /** @var array<string, string> */
    private const HTML5_FOREIGN_ELEMENT_NAMES = [
        'altglyph' => 'altGlyph',
        'altglyphdef' => 'altGlyphDef',
        'altglyphitem' => 'altGlyphItem',
        'animatecolor' => 'animateColor',
        'animatemotion' => 'animateMotion',
        'animatetransform' => 'animateTransform',
        'clippath' => 'clipPath',
        'feblend' => 'feBlend',
        'fecolormatrix' => 'feColorMatrix',
        'fecomponenttransfer' => 'feComponentTransfer',
        'fecomposite' => 'feComposite',
        'feconvolvematrix' => 'feConvolveMatrix',
        'fediffuselighting' => 'feDiffuseLighting',
        'fedisplacementmap' => 'feDisplacementMap',
        'fedistantlight' => 'feDistantLight',
        'fedropshadow' => 'feDropShadow',
        'feflood' => 'feFlood',
        'fefunca' => 'feFuncA',
        'fefuncb' => 'feFuncB',
        'fefuncg' => 'feFuncG',
        'fefuncr' => 'feFuncR',
        'fegaussianblur' => 'feGaussianBlur',
        'feimage' => 'feImage',
        'femerge' => 'feMerge',
        'femergenode' => 'feMergeNode',
        'femorphology' => 'feMorphology',
        'feoffset' => 'feOffset',
        'fepointlight' => 'fePointLight',
        'fespecularlighting' => 'feSpecularLighting',
        'fespotlight' => 'feSpotLight',
        'fetile' => 'feTile',
        'feturbulence' => 'feTurbulence',
        'foreignobject' => 'foreignObject',
        'glyphref' => 'glyphRef',
        'lineargradient' => 'linearGradient',
        'radialgradient' => 'radialGradient',
        'textpath' => 'textPath',
    ];

    /** @var array<string, string> */
    private const HTML5_FOREIGN_ATTRIBUTE_NAMES = [
        'attributename' => 'attributeName',
        'attributetype' => 'attributeType',
        'basefrequency' => 'baseFrequency',
        'baseprofile' => 'baseProfile',
        'calcmode' => 'calcMode',
        'clippathunits' => 'clipPathUnits',
        'diffuseconstant' => 'diffuseConstant',
        'definitionurl' => 'definitionURL',
        'edgemode' => 'edgeMode',
        'filterunits' => 'filterUnits',
        'glyphref' => 'glyphRef',
        'gradienttransform' => 'gradientTransform',
        'gradientunits' => 'gradientUnits',
        'kernelmatrix' => 'kernelMatrix',
        'kernelunitlength' => 'kernelUnitLength',
        'keypoints' => 'keyPoints',
        'keysplines' => 'keySplines',
        'keytimes' => 'keyTimes',
        'lengthadjust' => 'lengthAdjust',
        'limitingconeangle' => 'limitingConeAngle',
        'markerheight' => 'markerHeight',
        'markerunits' => 'markerUnits',
        'markerwidth' => 'markerWidth',
        'maskcontentunits' => 'maskContentUnits',
        'maskunits' => 'maskUnits',
        'numoctaves' => 'numOctaves',
        'pathlength' => 'pathLength',
        'patterncontentunits' => 'patternContentUnits',
        'patterntransform' => 'patternTransform',
        'patternunits' => 'patternUnits',
        'pointsatx' => 'pointsAtX',
        'pointsaty' => 'pointsAtY',
        'pointsatz' => 'pointsAtZ',
        'preservealpha' => 'preserveAlpha',
        'preserveaspectratio' => 'preserveAspectRatio',
        'primitiveunits' => 'primitiveUnits',
        'refx' => 'refX',
        'refy' => 'refY',
        'repeatcount' => 'repeatCount',
        'repeatdur' => 'repeatDur',
        'requiredextensions' => 'requiredExtensions',
        'requiredfeatures' => 'requiredFeatures',
        'specularconstant' => 'specularConstant',
        'specularexponent' => 'specularExponent',
        'spreadmethod' => 'spreadMethod',
        'startoffset' => 'startOffset',
        'stddeviation' => 'stdDeviation',
        'surfacescale' => 'surfaceScale',
        'systemlanguage' => 'systemLanguage',
        'tablevalues' => 'tableValues',
        'targetx' => 'targetX',
        'targety' => 'targetY',
        'textlength' => 'textLength',
        'viewbox' => 'viewBox',
        'viewtarget' => 'viewTarget',
        'xchannelselector' => 'xChannelSelector',
        'ychannelselector' => 'yChannelSelector',
        'zoomandpan' => 'zoomAndPan',
    ];

    public static function loadXmlDocument(string $xml, string $label = 'XML document', bool $preserveWhiteSpace = true): \DOMDocument
    {
        self::assertSafeSource($xml, $label);
        self::assertNoDoctype($xml, $label);

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = $preserveWhiteSpace;
        $dom->resolveExternals = false;
        $dom->substituteEntities = false;
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new \InvalidArgumentException(self::parseErrorMessage('Unable to parse ' . $label, $errors));
        }

        self::assertNoProcessingInstructions($dom, $label);

        return $dom;
    }

    public static function loadHtmlFragment(string $html, string $label = 'HTML fragment'): \DOMDocument
    {
        self::assertSafeSource($html, $label);
        $preflight = self::protectHtmlRcdataElements(
            $html,
            protectTemplateContent: true,
            protectIframeContent: true,
            protectRawTextContent: true,
            protectNoscriptContent: true
        );
        self::assertNoDoctype($preflight, $label);
        self::assertNoHtmlFragmentDeclarations($preflight, $label);
        $html = self::protectHtmlRcdataElements(
            $html,
            protectTemplateContent: true,
            protectIframeContent: true,
            protectNoscriptContent: true
        );

        $wrapped = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><div '
            . self::FRAGMENT_ROOT_ATTRIBUTE . '="1">' . $html . '</div></body></html>';

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = true;
        $dom->resolveExternals = false;
        $dom->substituteEntities = false;
        $loaded = $dom->loadHTML($wrapped, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded || self::fragmentRoot($dom) === null) {
            throw new \InvalidArgumentException(self::parseErrorMessage('Unable to parse ' . $label, $errors));
        }

        return $dom;
    }

    public static function rootElement(\DOMDocument $dom, ?string $localName = null, ?string $namespace = null): ?\DOMElement
    {
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || !self::elementMatches($root, $localName, $namespace)) {
            return null;
        }

        return $root;
    }

    public static function elementMatches(\DOMElement $element, ?string $localName = null, ?string $namespace = null): bool
    {
        if ($localName !== null && $element->localName !== $localName) {
            return false;
        }

        if ($namespace !== null && ($element->namespaceURI ?? '') !== $namespace) {
            return false;
        }

        return true;
    }

    /**
     * @return list<\DOMElement>
     */
    public static function childElements(\DOMElement $element, ?string $localName = null, ?string $namespace = null): array
    {
        $children = [];
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement || !self::elementMatches($child, $localName, $namespace)) {
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
        $stack = [];
        for ($index = $element->childNodes->length - 1; $index >= 0; --$index) {
            $child = $element->childNodes->item($index);
            if ($child instanceof \DOMElement) {
                $stack[] = $child;
            }
        }

        while ($stack !== []) {
            $node = array_pop($stack);
            if (!$node instanceof \DOMElement) {
                continue;
            }

            if (self::elementMatches($node, $localName, $namespace)) {
                $descendants[] = $node;
            }

            for ($index = $node->childNodes->length - 1; $index >= 0; --$index) {
                $child = $node->childNodes->item($index);
                if ($child instanceof \DOMElement) {
                    $stack[] = $child;
                }
            }
        }

        return $descendants;
    }

    public static function firstDescendantElement(\DOMElement $element, ?string $localName = null, ?string $namespace = null): ?\DOMElement
    {
        $stack = [];
        for ($index = $element->childNodes->length - 1; $index >= 0; --$index) {
            $child = $element->childNodes->item($index);
            if ($child instanceof \DOMElement) {
                $stack[] = $child;
            }
        }

        while ($stack !== []) {
            $node = array_pop($stack);
            if (!$node instanceof \DOMElement) {
                continue;
            }

            if (self::elementMatches($node, $localName, $namespace)) {
                return $node;
            }

            for ($index = $node->childNodes->length - 1; $index >= 0; --$index) {
                $child = $node->childNodes->item($index);
                if ($child instanceof \DOMElement) {
                    $stack[] = $child;
                }
            }
        }

        return null;
    }

    public static function attribute(\DOMElement $element, string $localName, ?string $namespace = null): ?string
    {
        if ($namespace !== null && $namespace !== '') {
            return $element->hasAttributeNS($namespace, $localName)
                ? $element->getAttributeNS($namespace, $localName)
                : null;
        }

        return $element->hasAttribute($localName) ? $element->getAttribute($localName) : null;
    }

    public static function fragmentRoot(\DOMDocument $dom): ?\DOMElement
    {
        foreach ($dom->getElementsByTagName('div') as $element) {
            if ($element instanceof \DOMElement && $element->getAttribute(self::FRAGMENT_ROOT_ATTRIBUTE) === '1') {
                return $element;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function summarizeHtmlFragment(\DOMDocument $dom): array
    {
        $root = self::requireFragmentRoot($dom);

        return self::summarizeChildNodes($root);
    }

    public static function serializeHtmlFragment(\DOMDocument $dom): string
    {
        $root = self::requireFragmentRoot($dom);

        return self::serializeHtmlChildren($root);
    }

    public static function serializeHtmlChildren(\DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            $html .= self::serializeNode($child);
        }

        return $html;
    }

    public static function serializeHtmlNode(\DOMNode $node): string
    {
        return self::serializeNode($node);
    }

    public static function htmlElementName(\DOMElement $element): string
    {
        $name = strtolower($element->tagName);
        $foreignContext = self::htmlForeignContext($element);

        return $foreignContext !== null
            ? self::adjustHtmlForeignElementName($name, $foreignContext)
            : $name;
    }

    /**
     * @return array<string, string>
     */
    public static function htmlAttributes(\DOMElement $element): array
    {
        $attributes = [];
        $isForeignElement = self::isHtmlForeignElement($element);
        foreach ($element->attributes ?? [] as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }

            $name = strtolower($attribute->name);
            if ($name === self::FRAGMENT_ROOT_ATTRIBUTE) {
                continue;
            }
            if ($isForeignElement) {
                $name = self::adjustHtmlForeignAttributeName($name);
            }
            $attributes[$name] = $attribute->value;
        }
        ksort($attributes);

        return $attributes;
    }

    public static function adjustHtmlForeignElementName(string $lowercaseName, ?string $foreignContext = null): string
    {
        if ($foreignContext !== null && $foreignContext !== 'svg') {
            return $lowercaseName;
        }

        return self::HTML5_FOREIGN_ELEMENT_NAMES[$lowercaseName] ?? $lowercaseName;
    }

    public static function adjustHtmlForeignAttributeName(string $lowercaseName): string
    {
        return self::HTML5_FOREIGN_ATTRIBUTE_NAMES[$lowercaseName] ?? $lowercaseName;
    }

    public static function protectHtmlRcdataElements(
        string $html,
        bool $protectTemplateContent = false,
        bool $protectIframeContent = false,
        bool $protectRawTextContent = false,
        bool $protectNoscriptContent = false
    ): string
    {
        $offset = 0;
        $protected = '';
        $rawTextNames = 'script|style|xmp|noembed|noframes|title|textarea|plaintext'
            . ($protectNoscriptContent ? '|noscript' : '')
            . ($protectIframeContent ? '|iframe' : '')
            . ($protectTemplateContent ? '|template' : '');
        $pattern = '~<(?P<name>' . $rawTextNames . ')(?=[\s/>])(?:[^>"\']+|"[^"]*"|\'[^\']*\')*>~is';

        while (preg_match($pattern, $html, $matches, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $startTag = (string) $matches[0][0];
            $startOffset = (int) $matches[0][1];
            $name = strtolower((string) $matches['name'][0]);
            $contentStart = $startOffset + strlen($startTag);

            $protected .= self::normalizeHtml5NamedCharacterReferences(
                self::protectHtmlCdataSections(substr($html, $offset, $startOffset - $offset))
            ) . $startTag;

            if ($name === 'title' && self::isHtmlTitleStartInSvgContext($html, $startOffset)) {
                $offset = $contentStart;
                continue;
            }

            if ($name === 'plaintext') {
                $protected .= self::escapeHtmlRawTextContent(substr($html, $contentStart)) . '</plaintext>';

                return $protected;
            }

            $endPattern = '~</\s*' . preg_quote($name, '~') . '\s*>~i';
            if (preg_match($endPattern, $html, $endMatches, PREG_OFFSET_CAPTURE, $contentStart) !== 1) {
                $protected .= self::protectHtmlRcdataElementContent($name, substr($html, $contentStart), $protectRawTextContent)
                    . '</' . $name . '>';

                return $protected;
            }

            $endTag = (string) $endMatches[0][0];
            $endOffset = (int) $endMatches[0][1];
            $content = substr($html, $contentStart, $endOffset - $contentStart);
            $protected .= self::protectHtmlRcdataElementContent($name, $content, $protectRawTextContent);
            $protected .= $endTag;
            $offset = $endOffset + strlen($endTag);
        }

        return $protected . self::normalizeHtml5NamedCharacterReferences(
            self::protectHtmlCdataSections(substr($html, $offset))
        );
    }

    private static function protectHtmlRcdataElementContent(string $name, string $content, bool $protectRawTextContent = false): string
    {
        if (in_array($name, ['script', 'style'], true)) {
            return $protectRawTextContent ? self::escapeHtmlRawTextContent($content) : $content;
        }

        if (in_array($name, ['title', 'textarea'], true)) {
            return self::escapeHtmlRcdataContent(self::normalizeHtml5NamedCharacterReferences($content));
        }

        return self::escapeHtmlRawTextContent($content);
    }

    private static function isHtmlTitleStartInSvgContext(string $html, int $startOffset): bool
    {
        $prefix = substr($html, 0, $startOffset);
        if ($prefix === '') {
            return false;
        }

        $tagCount = preg_match_all(
            '~<\s*(/?)\s*([A-Za-z][A-Za-z0-9:-]*)(?=[\s/>])(?:[^>"\']+|"[^"]*"|\'[^\']*\')*>~is',
            $prefix,
            $matches,
            PREG_SET_ORDER
        );
        if ($tagCount === false || $tagCount === 0) {
            return false;
        }

        $stack = [];
        foreach ($matches as $match) {
            $fullTag = (string) $match[0];
            $isClosing = (string) $match[1] === '/';
            $name = strtolower((string) $match[2]);
            $name = str_contains($name, ':') ? substr($name, (int) strrpos($name, ':') + 1) : $name;

            if ($isClosing) {
                for ($index = count($stack) - 1; $index >= 0; --$index) {
                    if ($stack[$index] === $name) {
                        array_splice($stack, $index, 1);
                        break;
                    }
                }
                continue;
            }

            if (str_ends_with(rtrim($fullTag), '/>') || isset(self::HTML5_VOID_ELEMENTS[$name])) {
                continue;
            }

            $stack[] = $name;
        }

        $lastSvgIndex = null;
        for ($index = count($stack) - 1; $index >= 0; --$index) {
            if ($stack[$index] === 'svg') {
                $lastSvgIndex = $index;
                break;
            }
        }

        if ($lastSvgIndex === null) {
            return false;
        }

        $htmlIntegrationAncestors = ['foreignobject' => true, 'desc' => true, 'title' => true];
        foreach (array_slice($stack, $lastSvgIndex + 1) as $ancestor) {
            if (isset($htmlIntegrationAncestors[$ancestor])) {
                return false;
            }
        }

        return true;
    }

    public static function normalizedText(\DOMNode $node): string
    {
        $text = preg_replace('/[ \t\r\n\f]+/u', ' ', $node->textContent) ?? $node->textContent;

        return trim($text);
    }

    /**
     * @return array<string, mixed>
     */
    public static function summarizeJatsFrontMatter(\DOMDocument $dom, string $format = 'jats'): array
    {
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement) {
            throw new \InvalidArgumentException('JATS/BITS review packet requires a document element');
        }

        $format = strtolower(trim($format));
        if (!in_array($format, ['jats', 'bits'], true)) {
            throw new \InvalidArgumentException('JATS/BITS review packet format must be jats or bits');
        }

        $rootName = $root->localName;
        if (!in_array($rootName, ['article', 'book', 'book-part'], true)) {
            throw new \InvalidArgumentException('JATS/BITS review packet root must be article, book, or book-part');
        }

        $metadata = self::jatsMetadataElement($root);
        $body = self::firstDescendantElement($root, 'body') ?? self::firstDescendantElement($root, 'book-body');

        $articleIds = $metadata instanceof \DOMElement
            ? self::jatsTypedTextRecords($metadata, ['article-id'], ['pub-id-type'])
            : [];
        $bookIds = $metadata instanceof \DOMElement
            ? self::jatsTypedTextRecords($metadata, ['book-id'], ['book-id-type', 'pub-id-type'])
            : [];
        $contributors = $metadata instanceof \DOMElement ? self::jatsContributorSummaries($metadata) : [];
        $dates = $metadata instanceof \DOMElement ? self::jatsPublicationDateSummaries($metadata) : [];
        $sections = $body instanceof \DOMElement ? self::jatsSectionSummaries($body) : [];
        $xrefTargets = self::jatsXrefTargets($root);
        $referenceIds = self::jatsElementIds($root, 'ref');
        $figureIds = self::jatsElementIds($root, 'fig');
        $tableWrapIds = self::jatsElementIds($root, 'table-wrap');
        $bookPartCount = count(self::descendantElements($root, 'book-part'));
        $directReaderDiagnostics = self::jatsDirectReaderDiagnostics(
            $format,
            $body instanceof \DOMElement,
            count($sections),
            count($referenceIds),
            count($figureIds),
            count($tableWrapIds),
            $bookPartCount
        );

        return [
            'formatFamily' => 'xml-html5-jats-dom',
            'format' => $format,
            'reviewPolicy' => 'jats-bits-front-matter-review-only',
            'directReaderParity' => false,
            'directReaderDiagnosticCodes' => array_map(
                static fn (array $diagnostic): string => (string) $diagnostic['code'],
                $directReaderDiagnostics
            ),
            'directReaderDiagnosticCount' => count($directReaderDiagnostics),
            'directReaderDiagnostics' => $directReaderDiagnostics,
            'rootName' => $rootName,
            'rootAttributes' => self::xmlAttributeMap($root),
            'documentType' => self::jatsDocumentType($root),
            'dtdVersion' => self::attribute($root, 'dtd-version'),
            'language' => self::attribute($root, 'lang', 'http://www.w3.org/XML/1998/namespace')
                ?? self::attribute($root, 'lang'),
            'metadataRoot' => $metadata instanceof \DOMElement ? $metadata->localName : null,
            'hasFrontMatter' => $metadata instanceof \DOMElement,
            'title' => $metadata instanceof \DOMElement
                ? self::jatsFirstText($metadata, ['article-title', 'book-title', 'title'])
                : null,
            'subtitle' => $metadata instanceof \DOMElement ? self::jatsFirstText($metadata, ['subtitle']) : null,
            'journalTitle' => self::jatsFirstText($root, ['journal-title']),
            'publisherName' => self::jatsFirstText($root, ['publisher-name']),
            'articleIds' => $articleIds,
            'bookIds' => $bookIds,
            'identifierCount' => count($articleIds) + count($bookIds),
            'abstractText' => $metadata instanceof \DOMElement ? self::jatsFirstText($metadata, ['abstract']) : null,
            'keywords' => $metadata instanceof \DOMElement ? self::jatsTextList($metadata, 'kwd') : [],
            'contributors' => $contributors,
            'contributorCount' => count($contributors),
            'contributorNames' => array_values(array_map(
                static fn (array $contributor): string => (string) $contributor['name'],
                $contributors
            )),
            'contributorRoles' => array_values(array_unique(array_filter(
                array_map(static fn (array $contributor): ?string => $contributor['role'], $contributors),
                static fn (?string $role): bool => $role !== null && $role !== ''
            ))),
            'publicationDates' => $dates,
            'publicationDateCount' => count($dates),
            'sectionCount' => count($sections),
            'sectionTitles' => array_values(array_filter(
                array_map(static fn (array $section): ?string => $section['title'], $sections),
                static fn (?string $title): bool => $title !== null && $title !== ''
            )),
            'sections' => $sections,
            'xrefTargets' => $xrefTargets,
            'xrefTargetCount' => count($xrefTargets),
            'referenceIds' => $referenceIds,
            'referenceCount' => count($referenceIds),
            'figureIds' => $figureIds,
            'figureCount' => count($figureIds),
            'tableWrapIds' => $tableWrapIds,
            'tableWrapCount' => count($tableWrapIds),
            'bookPartCount' => $bookPartCount,
        ];
    }

    /**
     * @return list<array{code:string, severity:string, message:string, directReaderParity:bool, coveredByPacket:bool, details:array<string, int|string|bool>}>
     */
    private static function jatsDirectReaderDiagnostics(
        string $format,
        bool $hasBody,
        int $sectionCount,
        int $referenceCount,
        int $figureCount,
        int $tableWrapCount,
        int $bookPartCount
    ): array {
        $formatLabel = strtoupper($format);
        $diagnostics = [
            self::jatsDirectReaderDiagnostic(
                'direct-reader-unsupported',
                'unsupported',
                $formatLabel . ' direct reader parity is not implemented; this packet exposes bounded XML diagnostics only.',
                false,
                true,
                ['format' => $format]
            ),
        ];

        if (!$hasBody) {
            $diagnostics[] = self::jatsDirectReaderDiagnostic(
                'body-missing',
                'warning',
                'No JATS/BITS body element was found for bounded body diagnostics.',
                false,
                true
            );
        } elseif ($sectionCount > 0) {
            $diagnostics[] = self::jatsDirectReaderDiagnostic(
                'body-sections-review-only',
                'unsupported',
                'Body sections are inventoried for review but are not mapped as a full Pandoc direct reader AST.',
                false,
                false,
                ['sectionCount' => $sectionCount]
            );
        }

        if ($referenceCount > 0) {
            $diagnostics[] = self::jatsDirectReaderDiagnostic(
                'references-review-only',
                'unsupported',
                'Reference elements are inventoried for review but are not mapped as full citation-reader output.',
                false,
                false,
                ['referenceCount' => $referenceCount]
            );
        }

        if ($figureCount > 0) {
            $diagnostics[] = self::jatsDirectReaderDiagnostic(
                'figures-review-only',
                'unsupported',
                'Figure elements are inventoried for review but are not mapped as full figure blocks.',
                false,
                false,
                ['figureCount' => $figureCount]
            );
        }

        if ($tableWrapCount > 0) {
            $diagnostics[] = self::jatsDirectReaderDiagnostic(
                'table-wraps-review-only',
                'unsupported',
                'Table-wrap elements are inventoried for review but are not mapped as full table blocks.',
                false,
                false,
                ['tableWrapCount' => $tableWrapCount]
            );
        }

        if ($bookPartCount > 0) {
            $diagnostics[] = self::jatsDirectReaderDiagnostic(
                'book-parts-review-only',
                'unsupported',
                'Book-part elements are counted for review but are not mapped as nested Pandoc documents.',
                false,
                false,
                ['bookPartCount' => $bookPartCount]
            );
        }

        return $diagnostics;
    }

    /**
     * @param array<string, int|string|bool> $details
     * @return array{code:string, severity:string, message:string, directReaderParity:bool, coveredByPacket:bool, details:array<string, int|string|bool>}
     */
    private static function jatsDirectReaderDiagnostic(
        string $code,
        string $severity,
        string $message,
        bool $directReaderParity,
        bool $coveredByPacket,
        array $details = []
    ): array {
        return [
            'code' => $code,
            'severity' => $severity,
            'message' => $message,
            'directReaderParity' => $directReaderParity,
            'coveredByPacket' => $coveredByPacket,
            'details' => $details,
        ];
    }

    private static function requireFragmentRoot(\DOMDocument $dom): \DOMElement
    {
        $root = self::fragmentRoot($dom);
        if (!$root instanceof \DOMElement) {
            throw new \InvalidArgumentException('DOM document is not a Pandoc HTML fragment document');
        }

        return $root;
    }

    /**
     * @return array<string, string>
     */
    private static function xmlAttributeMap(\DOMElement $element): array
    {
        $attributes = [];
        foreach ($element->attributes ?? [] as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }

            $name = $attribute->prefix !== ''
                ? $attribute->prefix . ':' . $attribute->localName
                : $attribute->name;
            $attributes[$name] = $attribute->value;
        }
        ksort($attributes);

        return $attributes;
    }

    private static function jatsMetadataElement(\DOMElement $root): ?\DOMElement
    {
        foreach (['article-meta', 'book-meta', 'book-part-meta'] as $name) {
            $metadata = self::firstDescendantElement($root, $name);
            if ($metadata instanceof \DOMElement) {
                return $metadata;
            }
        }

        return null;
    }

    private static function jatsDocumentType(\DOMElement $root): ?string
    {
        foreach (['article-type', 'book-type', 'book-part-type'] as $name) {
            $value = self::attribute($root, $name);
            if ($value !== null && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @param list<string> $localNames
     */
    private static function jatsFirstText(\DOMElement $root, array $localNames): ?string
    {
        foreach ($localNames as $localName) {
            $element = self::firstDescendantElement($root, $localName);
            if (!$element instanceof \DOMElement) {
                continue;
            }

            $text = self::normalizedText($element);
            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function jatsTextList(\DOMElement $root, string $localName): array
    {
        $values = [];
        foreach (self::descendantElements($root, $localName) as $element) {
            $text = self::normalizedText($element);
            if ($text !== '') {
                $values[] = $text;
            }
        }

        return $values;
    }

    /**
     * @param list<string> $localNames
     * @param list<string> $typeAttributes
     * @return list<array{element:string, type:?string, value:string}>
     */
    private static function jatsTypedTextRecords(\DOMElement $root, array $localNames, array $typeAttributes): array
    {
        $records = [];
        foreach ($localNames as $localName) {
            foreach (self::descendantElements($root, $localName) as $element) {
                $value = self::normalizedText($element);
                if ($value === '') {
                    continue;
                }

                $type = null;
                foreach ($typeAttributes as $attribute) {
                    $type = self::attribute($element, $attribute);
                    if ($type !== null && trim($type) !== '') {
                        $type = trim($type);
                        break;
                    }
                    $type = null;
                }

                $records[] = [
                    'element' => $localName,
                    'type' => $type,
                    'value' => $value,
                ];
            }
        }

        return $records;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function jatsContributorSummaries(\DOMElement $metadata): array
    {
        $contributors = [];
        foreach (self::descendantElements($metadata, 'contrib') as $contrib) {
            $role = self::attribute($contrib, 'contrib-type');
            $xrefTargets = [];
            foreach (self::descendantElements($contrib, 'xref') as $xref) {
                $target = self::attribute($xref, 'rid');
                if ($target !== null && trim($target) !== '') {
                    $xrefTargets[] = trim($target);
                }
            }

            $contributors[] = [
                'role' => $role === null || trim($role) === '' ? null : trim($role),
                'name' => self::jatsContributorName($contrib),
                'xrefTargets' => array_values(array_unique($xrefTargets)),
            ];
        }

        return $contributors;
    }

    private static function jatsContributorName(\DOMElement $contrib): string
    {
        $name = self::firstDescendantElement($contrib, 'name');
        if ($name instanceof \DOMElement) {
            $surname = self::jatsFirstText($name, ['surname']);
            $given = self::jatsFirstText($name, ['given-names']);
            $combined = trim(($given ?? '') . ' ' . ($surname ?? ''));
            if ($combined !== '') {
                return $combined;
            }
        }

        foreach (['string-name', 'collab'] as $localName) {
            $element = self::firstDescendantElement($contrib, $localName);
            if (!$element instanceof \DOMElement) {
                continue;
            }

            $text = self::normalizedText($element);
            if ($text !== '') {
                return $text;
            }
        }

        return self::normalizedText($contrib);
    }

    /**
     * @return list<array{type:?string, year:?string, month:?string, day:?string, iso:?string}>
     */
    private static function jatsPublicationDateSummaries(\DOMElement $metadata): array
    {
        $dates = [];
        foreach (self::descendantElements($metadata, 'pub-date') as $date) {
            $year = self::jatsFirstText($date, ['year']);
            $month = self::jatsFirstText($date, ['month']);
            $day = self::jatsFirstText($date, ['day']);

            $dates[] = [
                'type' => self::attribute($date, 'date-type') ?? self::attribute($date, 'pub-type'),
                'year' => $year,
                'month' => $month,
                'day' => $day,
                'iso' => self::jatsIsoDate($year, $month, $day),
            ];
        }

        return $dates;
    }

    private static function jatsIsoDate(?string $year, ?string $month, ?string $day): ?string
    {
        if ($year === null || preg_match('/^\d{4}$/', $year) !== 1) {
            return null;
        }

        if ($month === null || preg_match('/^\d{1,2}$/', $month) !== 1) {
            return $year;
        }

        $iso = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT);
        if ($day === null || preg_match('/^\d{1,2}$/', $day) !== 1) {
            return $iso;
        }

        return $iso . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
    }

    /**
     * @return list<array{id:?string, title:?string, paragraphCount:int}>
     */
    private static function jatsSectionSummaries(\DOMElement $body): array
    {
        $sections = [];
        foreach (self::descendantElements($body, 'sec') as $section) {
            $title = self::firstChildElement($section, 'title');
            $sections[] = [
                'id' => self::attribute($section, 'id'),
                'title' => $title instanceof \DOMElement ? self::normalizedText($title) : null,
                'paragraphCount' => count(self::descendantElements($section, 'p')),
            ];
        }

        return $sections;
    }

    /**
     * @return list<string>
     */
    private static function jatsXrefTargets(\DOMElement $root): array
    {
        $targets = [];
        foreach (self::descendantElements($root, 'xref') as $xref) {
            $rid = self::attribute($xref, 'rid');
            if ($rid === null || trim($rid) === '') {
                continue;
            }

            foreach (self::spaceSeparatedTokens($rid) as $target) {
                $targets[] = $target;
            }
        }

        return array_values(array_unique($targets));
    }

    /**
     * @return list<string>
     */
    private static function jatsElementIds(\DOMElement $root, string $localName): array
    {
        $ids = [];
        foreach (self::descendantElements($root, $localName) as $element) {
            $id = self::attribute($element, 'id');
            if ($id !== null && trim($id) !== '') {
                $ids[] = trim($id);
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function summarizeChildNodes(\DOMNode $parent): array
    {
        $summary = [];
        foreach ($parent->childNodes as $child) {
            array_push($summary, ...self::summarizeNode($child));
        }

        return $summary;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function summarizeNode(\DOMNode $node): array
    {
        if ($node instanceof \DOMText) {
            $text = $node->nodeValue ?? '';

            return $text === '' ? [] : [['type' => 'text', 'text' => $text]];
        }

        if ($node instanceof \DOMComment) {
            return [['type' => 'comment', 'text' => $node->nodeValue ?? '']];
        }

        if (!$node instanceof \DOMElement) {
            return [];
        }

        $name = self::htmlElementName($node);
        if (self::isHtmlTableModelContext($name)) {
            [$fostered, $summary] = self::summarizeTableElementParts($node, $name);

            return [...$fostered, $summary];
        }

        $children = self::summarizeChildNodes($node);
        $summary = [
            'type' => 'element',
            'name' => $name,
            'attributes' => self::htmlAttributes($node),
            'text' => self::normalizedText($node),
            'children' => $children,
        ];
        $summary += self::globalHtmlAttributeSummary($node);

        if ($name === 'form') {
            $summary += self::formSubmissionSummary($node);
        }
        if ($name === 'label') {
            $summary += self::labelSummary($node);
        }
        if (in_array($name, ['ol', 'ul', 'menu', 'li'], true)) {
            $summary += self::listSummary($node, $name);
        }
        if (in_array($name, ['dl', 'dt', 'dd'], true)) {
            $summary += self::definitionListSummary($node, $name);
        }
        if (self::isHtmlHeadingElementName($name)) {
            $summary += self::headingSummary($node, $name);
        }
        if ($name === 'hgroup') {
            $summary += self::headingGroupSummary($node);
        }
        if (self::isHtmlOutlineElementName($name)) {
            $summary += self::outlineSummary($node, $name);
        }
        if ($name === 'search') {
            $summary += self::searchSummary($node);
        }
        if ($name === 'address') {
            $summary += self::addressSummary($node);
        }
        if (in_array($name, ['caption', 'col', 'td', 'th'], true)) {
            $summary += self::tableElementSummary($node, $name);
        }
        if ($name === 'figure' || $name === 'figcaption') {
            $summary += self::figureSummary($node, $name);
        }
        if ($name === 'select') {
            $options = self::selectOptionSummaries($node);
            $summary['formControl'] = 'select';
            $summary += self::formOwnerSummary($node);
            $summary['labels'] = self::formControlLabels($node);
            $summary['disabled'] = $node->hasAttribute('disabled');
            $summary['effectiveDisabled'] = self::isEffectivelyDisabledFormControl($node);
            $summary['required'] = $node->hasAttribute('required');
            $summary += self::formControlConstraintSummary($node, $name);
            $summary['selectOptions'] = $options;
            $summary['selectedValues'] = array_values(array_map(
                static fn (array $option): string => (string) $option['value'],
                array_filter($options, static fn (array $option): bool => (bool) ($option['selected'] ?? false))
            ));
        }
        if ($name === 'input') {
            $inputType = self::inputType($node);
            $summary['formControl'] = 'input';
            $summary += self::formOwnerSummary($node);
            $summary['inputType'] = $inputType;
            $summary['labels'] = self::formControlLabels($node);
            $summary['value'] = $node->getAttribute('value');
            $summary['checked'] = $node->hasAttribute('checked');
            $summary['disabled'] = $node->hasAttribute('disabled');
            $summary['effectiveDisabled'] = self::isEffectivelyDisabledFormControl($node);
            $summary['required'] = $node->hasAttribute('required');
            $summary += self::formControlConstraintSummary($node, $name);
            if ($node->hasAttribute('placeholder')) {
                $summary['placeholder'] = $node->getAttribute('placeholder');
            }
            if ($node->hasAttribute('list')) {
                $summary['list'] = $node->getAttribute('list');
                $summary['datalistOptions'] = self::datalistOptionsForControl($node);
            }
            if (self::isInputSubmitterType($inputType)) {
                $summary['submitter'] = self::formSubmitterSummary($node);
            }
        }
        if ($name === 'textarea') {
            $summary['formControl'] = 'textarea';
            $summary += self::formOwnerSummary($node);
            $summary['labels'] = self::formControlLabels($node);
            $summary['value'] = $node->textContent;
            $summary['disabled'] = $node->hasAttribute('disabled');
            $summary['effectiveDisabled'] = self::isEffectivelyDisabledFormControl($node);
            $summary['readonly'] = $node->hasAttribute('readonly');
            $summary['required'] = $node->hasAttribute('required');
            $summary += self::formControlConstraintSummary($node, $name);
            if ($node->hasAttribute('placeholder')) {
                $summary['placeholder'] = $node->getAttribute('placeholder');
            }
        }
        if ($name === 'button') {
            $buttonType = self::buttonType($node);
            $summary['formControl'] = 'button';
            $summary += self::formOwnerSummary($node);
            $summary['buttonType'] = $buttonType;
            $summary['labels'] = self::formControlLabels($node);
            $summary['value'] = $node->getAttribute('value');
            $summary['label'] = self::normalizedText($node);
            $summary['disabled'] = $node->hasAttribute('disabled');
            $summary['effectiveDisabled'] = self::isEffectivelyDisabledFormControl($node);
            if ($buttonType === 'submit') {
                $summary['submitter'] = self::formSubmitterSummary($node);
            }
        }
        if ($name === 'output') {
            $forRaw = $node->hasAttribute('for') ? $node->getAttribute('for') : null;
            $summary['formControl'] = 'output';
            $summary += self::formOwnerSummary($node);
            $summary['labels'] = self::formControlLabels($node);
            $summary['value'] = $node->textContent;
            $summary['forRaw'] = $forRaw;
            $summary['forIds'] = $forRaw === null ? [] : self::spaceSeparatedTokens($forRaw);
        }
        if ($name === 'datalist') {
            $summary['formControl'] = 'datalist';
            $summary['datalistOptions'] = self::datalistOptionSummaries($node);
        }
        if ($name === 'fieldset') {
            $summary += self::fieldsetSummary($node);
        }
        if ($name === 'legend') {
            $summary += self::legendSummary($node);
        }
        if ($name === 'details') {
            $summary += self::detailsDisclosureSummary($node);
        }
        if ($name === 'dialog') {
            $summary['dialog'] = 'dialog';
            $summary['dialogOpen'] = $node->hasAttribute('open');
            $summary['dialogText'] = self::normalizedText($node);
        }
        if ($name === 'summary') {
            $summary += self::summaryDisclosureSummary($node);
        }
        if ($name === 'dialog') {
            $summary += self::dialogSummary($node);
        }
        if ($name === 'ins' || $name === 'del') {
            $summary += self::revisionSummary($node, $name);
        }
        if (in_array($name, ['blockquote', 'q', 'cite'], true)) {
            $summary += self::quoteSummary($node, $name);
        }
        if ($name === 'progress') {
            $summary += self::progressMeasurementSummary($node, includeLabels: true);
        }
        if ($name === 'meter') {
            $summary += self::meterMeasurementSummary($node, includeLabels: true);
        }
        if (in_array($name, ['picture', 'img', 'audio', 'video', 'source', 'track', 'iframe', 'embed', 'object', 'param', 'canvas'], true)) {
            $summary += self::embeddedResourceSummary($node, $name);
        }
        if (self::isDocBookMediaElementName($name)) {
            $summary += self::docBookMediaSummary($node, $name);
        }
        if (in_array($name, ['a', 'area'], true)) {
            $summary += self::hyperlinkSummary($node, $name);
        }
        if ($name === 'map') {
            $summary += self::imageMapSummary($node);
        }
        if (in_array($name, ['base', 'link', 'meta'], true)) {
            $summary += self::documentMetadataSummary($node, $name);
        }
        if (in_array($name, ['script', 'style'], true)) {
            $summary += self::activeContentSummary($node, $name);
        }
        if ($name === 'template') {
            $summary += self::templateSummary($node);
        }
        if ($name === 'noscript') {
            $summary += self::noscriptSummary($node);
        }
        if ($name === 'time') {
            $summary += self::timeSummary($node);
        }
        if ($name === 'data') {
            $summary += self::dataElementSummary($node);
        }
        if (in_array($name, ['ruby', 'rb', 'rt', 'rp', 'rtc'], true)) {
            $summary += self::rubySummary($node, $name);
        }
        if (in_array($name, ['abbr', 'b', 'bdi', 'bdo', 'code', 'dfn', 'em', 'i', 'kbd', 'mark', 's', 'samp', 'small', 'strong', 'sub', 'sup', 'u', 'var'], true)) {
            $summary += self::textSemanticSummary($node, $name);
        }
        if (in_array($name, ['br', 'hr', 'wbr'], true)) {
            $summary += self::breakElementSummary($name);
        }

        return [$summary];
    }

    /**
     * @return array{documentOutline:string, heading:bool, headingTag:string, headingLevel:int, headingText:string}
     */
    private static function headingSummary(\DOMElement $element, string $name): array
    {
        return [
            'documentOutline' => 'heading',
            'heading' => true,
            'headingTag' => $name,
            'headingLevel' => self::htmlHeadingLevel($name),
            'headingText' => self::normalizedText($element),
        ];
    }

    /**
     * @return array{documentOutline:string, outlineRoot:string, sectionHeadingText:?string, sectionHeadingTag:?string, sectionHeadingLevel:?int}
     */
    private static function outlineSummary(\DOMElement $element, string $name): array
    {
        $heading = self::firstScopedHeadingElement($element);
        $headingName = $heading instanceof \DOMElement ? self::htmlElementName($heading) : null;

        return [
            'documentOutline' => match ($name) {
                'nav' => 'navigation',
                default => $name,
            },
            'outlineRoot' => $name,
            'sectionHeadingText' => $heading instanceof \DOMElement ? self::normalizedText($heading) : null,
            'sectionHeadingTag' => $headingName,
            'sectionHeadingLevel' => $headingName === null ? null : self::htmlHeadingLevel($headingName),
        ];
    }

    private static function isHtmlHeadingElementName(string $name): bool
    {
        return in_array($name, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true);
    }

    private static function htmlHeadingLevel(string $name): int
    {
        return (int) substr($name, 1);
    }

    /**
     * @return array<string, mixed>
     */
    private static function headingGroupSummary(\DOMElement $element): array
    {
        $headings = [];
        foreach (self::htmlHeadingGroupHeadingElements($element) as $heading) {
            $name = self::htmlElementName($heading);
            $headings[] = [
                'tag' => $name,
                'level' => self::htmlHeadingLevel($name),
                'text' => self::normalizedText($heading),
            ];
        }

        $mainHeading = self::htmlHeadingGroupMainHeadingElement($element);
        $mainHeadingName = $mainHeading instanceof \DOMElement ? self::htmlElementName($mainHeading) : null;
        $subtitleTexts = self::htmlHeadingGroupSubtitleTexts($element);

        return [
            'documentOutline' => 'heading-group',
            'headingGroup' => 'hgroup',
            'headingGroupText' => self::normalizedText($element),
            'headingGroupHeadingText' => $mainHeading instanceof \DOMElement ? self::normalizedText($mainHeading) : null,
            'headingGroupHeadingTag' => $mainHeadingName,
            'headingGroupHeadingLevel' => $mainHeadingName === null ? null : self::htmlHeadingLevel($mainHeadingName),
            'headingGroupHeadingCount' => count($headings),
            'headingGroupHeadingTexts' => array_values(array_map(
                static fn (array $heading): string => (string) $heading['text'],
                $headings
            )),
            'headingGroupHeadings' => $headings,
            'headingGroupSubtitleCount' => count($subtitleTexts),
            'headingGroupSubtitleTexts' => $subtitleTexts,
        ];
    }

    /**
     * @return list<\DOMElement>
     */
    private static function htmlHeadingGroupHeadingElements(\DOMElement $element): array
    {
        $headings = [];
        foreach (self::childElements($element) as $child) {
            if (self::isHtmlHeadingElementName(self::htmlElementName($child))) {
                $headings[] = $child;
            }
        }

        return $headings;
    }

    private static function htmlHeadingGroupMainHeadingElement(\DOMElement $element): ?\DOMElement
    {
        $mainHeading = null;
        $mainLevel = PHP_INT_MAX;
        foreach (self::htmlHeadingGroupHeadingElements($element) as $heading) {
            $level = self::htmlHeadingLevel(self::htmlElementName($heading));
            if ($level < $mainLevel) {
                $mainHeading = $heading;
                $mainLevel = $level;
            }
        }

        return $mainHeading;
    }

    /**
     * @return list<string>
     */
    private static function htmlHeadingGroupSubtitleTexts(\DOMElement $element): array
    {
        $subtitles = [];
        foreach (self::childElements($element, 'p') as $paragraph) {
            $text = self::normalizedText($paragraph);
            if ($text !== '') {
                $subtitles[] = $text;
            }
        }

        return $subtitles;
    }

    private static function isHtmlOutlineElementName(string $name): bool
    {
        return in_array($name, ['article', 'aside', 'main', 'nav', 'section'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private static function searchSummary(\DOMElement $search): array
    {
        $forms = [];
        foreach (self::descendantHtmlElements($search, 'form') as $form) {
            $forms[] = [
                'id' => self::attributeOrNull($form, 'id'),
                'action' => self::attributeOrNull($form, 'action'),
                'method' => self::formMethod($form, 'method', 'get'),
                'role' => self::attributeOrNull($form, 'role'),
                'controls' => self::searchControlSummaries($form),
            ];
        }

        return [
            'landmark' => 'search',
            'searchRegion' => 'search',
            'searchText' => self::normalizedText($search),
            'searchFormCount' => count($forms),
            'searchForms' => $forms,
            'searchControls' => self::searchControlSummaries($search),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function searchControlSummaries(\DOMElement $root): array
    {
        $controls = [];
        foreach ($root->getElementsByTagName('*') as $control) {
            if (!$control instanceof \DOMElement) {
                continue;
            }

            $name = self::htmlElementName($control);
            if (!in_array($name, ['button', 'input', 'select', 'textarea'], true)) {
                continue;
            }

            $summary = [
                'control' => $name,
                'id' => self::attributeOrNull($control, 'id'),
                'controlName' => self::attributeOrNull($control, 'name'),
                'label' => self::formControlLabels($control),
            ];
            if ($name === 'input') {
                $summary['type'] = self::inputType($control);
                $summary['value'] = self::attributeOrNull($control, 'value');
            }
            if ($name === 'button') {
                $summary['type'] = self::buttonType($control);
                $summary['value'] = self::attributeOrNull($control, 'value');
                $summary['text'] = self::normalizedText($control);
            }

            $controls[] = $summary;
        }

        return $controls;
    }

    /**
     * @return array<string, mixed>
     */
    private static function addressSummary(\DOMElement $address): array
    {
        $links = [];
        foreach (self::descendantHtmlElements($address, 'a') as $link) {
            $relRaw = self::attributeOrNull($link, 'rel');
            $links[] = [
                'href' => self::attributeOrNull($link, 'href'),
                'label' => self::normalizedText($link),
                'relRaw' => $relRaw,
                'relTokens' => $relRaw === null ? [] : self::spaceSeparatedTokens($relRaw),
            ];
        }

        return [
            'contactInfo' => 'address',
            'contactText' => self::normalizedText($address),
            'contactLinkCount' => count($links),
            'contactLinks' => $links,
            'contactHrefs' => array_values(array_filter(
                array_map(static fn (array $link): ?string => $link['href'], $links),
                static fn (?string $href): bool => $href !== null
            )),
            'contactEmailHrefs' => array_values(array_filter(
                array_map(static fn (array $link): ?string => $link['href'], $links),
                static fn (?string $href): bool => $href !== null && str_starts_with(strtolower($href), 'mailto:')
            )),
        ];
    }

    private static function firstScopedHeadingElement(\DOMElement $element): ?\DOMElement
    {
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $name = self::htmlElementName($child);
            if ($name === 'hgroup') {
                $heading = self::htmlHeadingGroupMainHeadingElement($child);
                if ($heading instanceof \DOMElement) {
                    return $heading;
                }

                continue;
            }
            if (self::isHtmlHeadingElementName($name)) {
                return $child;
            }
            if (self::isHtmlOutlineElementName($name)) {
                continue;
            }

            $heading = self::firstScopedHeadingElement($child);
            if ($heading instanceof \DOMElement) {
                return $heading;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function documentMetadataSummary(\DOMElement $element, string $name): array
    {
        if ($name === 'base') {
            return [
                'documentMetadata' => 'base',
                'href' => self::attributeOrNull($element, 'href'),
                'target' => self::attributeOrNull($element, 'target'),
            ];
        }

        if ($name === 'link') {
            $relRaw = self::attributeOrNull($element, 'rel');
            $imageSrcset = self::attributeOrNull($element, 'imagesrcset');

            $summary = [
                'documentMetadata' => 'link',
                'href' => self::attributeOrNull($element, 'href'),
                'relRaw' => $relRaw,
                'relTokens' => $relRaw === null ? [] : self::spaceSeparatedTokens($relRaw),
                'as' => self::attributeOrNull($element, 'as'),
                'media' => self::attributeOrNull($element, 'media'),
                'hreflang' => self::attributeOrNull($element, 'hreflang'),
                'mimeType' => self::attributeOrNull($element, 'type'),
                'crossorigin' => self::attributeOrNull($element, 'crossorigin'),
                'integrity' => self::attributeOrNull($element, 'integrity'),
                'referrerpolicy' => self::attributeOrNull($element, 'referrerpolicy'),
                'sizes' => self::attributeOrNull($element, 'sizes'),
                'imageSrcset' => $imageSrcset,
                'imageSrcsetCandidates' => self::srcsetCandidateSummaries($imageSrcset),
                'imageSizes' => self::attributeOrNull($element, 'imagesizes'),
                'fetchpriority' => self::attributeOrNull($element, 'fetchpriority'),
            ];

            return $summary + self::linkResourceReviewSummary($element, $relRaw);
        }

        $content = self::attributeOrNull($element, 'content');
        $httpEquivRaw = self::attributeOrNull($element, 'http-equiv');
        $httpEquiv = $httpEquivRaw === null ? null : strtolower(trim($httpEquivRaw));
        $summary = [
            'documentMetadata' => 'meta',
            'charset' => self::attributeOrNull($element, 'charset'),
            'nameAttribute' => self::attributeOrNull($element, 'name'),
            'property' => self::attributeOrNull($element, 'property'),
            'itemprop' => self::attributeOrNull($element, 'itemprop'),
            'httpEquivRaw' => $httpEquivRaw,
            'httpEquiv' => $httpEquiv,
            'content' => $content,
        ];

        if ($httpEquiv === 'refresh') {
            $summary['refresh'] = self::metaRefreshSummary($content);
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private static function linkResourceReviewSummary(\DOMElement $link, ?string $relRaw): array
    {
        $tokens = $relRaw === null ? [] : self::spaceSeparatedTokens($relRaw);
        $knownTokens = [
            'alternate' => true,
            'author' => true,
            'canonical' => true,
            'dns-prefetch' => true,
            'expect' => true,
            'help' => true,
            'icon' => true,
            'license' => true,
            'manifest' => true,
            'modulepreload' => true,
            'next' => true,
            'pingback' => true,
            'preconnect' => true,
            'prefetch' => true,
            'preload' => true,
            'prev' => true,
            'prerender' => true,
            'search' => true,
            'stylesheet' => true,
        ];
        $resourceTokenKinds = [
            'alternate' => 'alternate',
            'canonical' => 'canonical',
            'dns-prefetch' => 'resource-hint',
            'icon' => 'icon',
            'manifest' => 'manifest',
            'modulepreload' => 'modulepreload',
            'preconnect' => 'resource-hint',
            'prefetch' => 'resource-hint',
            'preload' => 'preload',
            'prerender' => 'resource-hint',
            'search' => 'search',
            'stylesheet' => 'stylesheet',
        ];
        $resourceHintTokens = ['dns-prefetch' => true, 'preconnect' => true, 'prefetch' => true, 'prerender' => true];
        $preloadDestinations = [
            'audio' => true,
            'document' => true,
            'embed' => true,
            'fetch' => true,
            'font' => true,
            'image' => true,
            'object' => true,
            'script' => true,
            'style' => true,
            'track' => true,
            'video' => true,
            'worker' => true,
        ];

        $normalized = [];
        $counts = [];
        $invalid = [];
        $custom = [];
        $resourceRelTokens = [];
        $resourceKinds = [];
        $resourceHints = [];
        foreach ($tokens as $token) {
            $lower = strtolower($token);
            if (!self::isSafeHtmlRelToken($token)) {
                $invalid[] = $token;
                continue;
            }

            if (!array_key_exists($lower, $counts)) {
                $normalized[] = $lower;
                $counts[$lower] = 0;
            }
            ++$counts[$lower];

            if (!isset($knownTokens[$lower])) {
                $custom[] = $lower;
            }
            if (isset($resourceTokenKinds[$lower]) && !in_array($lower, $resourceRelTokens, true)) {
                $resourceRelTokens[] = $lower;
            }
            if (isset($resourceTokenKinds[$lower]) && !in_array($resourceTokenKinds[$lower], $resourceKinds, true)) {
                $resourceKinds[] = $resourceTokenKinds[$lower];
            }
            if (isset($resourceHintTokens[$lower]) && !in_array($lower, $resourceHints, true)) {
                $resourceHints[] = $lower;
            }
        }

        $duplicates = [];
        foreach ($counts as $token => $count) {
            if ($count > 1) {
                $duplicates[] = $token;
            }
        }

        $asRaw = self::attributeOrNull($link, 'as');
        $as = $asRaw === null ? null : strtolower(trim($asRaw));
        $hasPreload = in_array('preload', $normalized, true);
        $preloadAsValid = !$hasPreload || ($as !== null && isset($preloadDestinations[$as]));
        $href = self::attributeOrNull($link, 'href');
        $hrefRequired = $resourceRelTokens !== [];
        $issues = [];

        foreach ($invalid as $token) {
            $issues[] = ['code' => 'invalid-link-rel-token', 'relToken' => $token];
        }
        foreach ($duplicates as $token) {
            $issues[] = ['code' => 'duplicate-link-rel-token', 'relToken' => $token, 'count' => $counts[$token]];
        }
        if ($hrefRequired && ($href === null || trim($href) === '')) {
            $issues[] = ['code' => 'missing-link-href', 'relTokens' => $resourceRelTokens];
        }
        if ($hasPreload && ($as === null || $as === '')) {
            $issues[] = ['code' => 'missing-preload-as'];
        } elseif ($hasPreload && !isset($preloadDestinations[$as])) {
            $issues[] = ['code' => 'invalid-preload-as', 'asRaw' => $asRaw];
        }

        return [
            'linkResourceReview' => 'link',
            'linkRelTokens' => $normalized,
            'linkRelTokenCounts' => $counts,
            'duplicateLinkRelTokens' => $duplicates,
            'invalidLinkRelTokens' => $invalid,
            'customLinkRelTokens' => $custom,
            'linkResourceRelTokens' => $resourceRelTokens,
            'linkResourceKinds' => $resourceKinds,
            'linkPrimaryResourceKind' => $resourceKinds[0] ?? null,
            'linkResourceHintTokens' => $resourceHints,
            'linkHrefRequired' => $hrefRequired,
            'linkHrefPresent' => $href !== null && trim($href) !== '',
            'preloadAsRaw' => $asRaw,
            'preloadAs' => $as === '' ? null : $as,
            'preloadAsRequired' => $hasPreload,
            'preloadAsValid' => $preloadAsValid,
            'linkIssues' => $issues,
        ];
    }

    private static function isSafeHtmlRelToken(string $token): bool
    {
        return $token !== ''
            && preg_match('/^[A-Za-z][A-Za-z0-9_.:-]*$/', $token) === 1;
    }

    /**
     * @return array{contentRaw:?string, delayRaw:?string, delay:?float, urlRaw:?string, url:?string}
     */
    private static function metaRefreshSummary(?string $content): array
    {
        if ($content === null) {
            return [
                'contentRaw' => null,
                'delayRaw' => null,
                'delay' => null,
                'urlRaw' => null,
                'url' => null,
            ];
        }

        $parts = explode(';', $content, 2);
        $delayRaw = trim($parts[0]);
        $delay = is_numeric($delayRaw) ? (float) $delayRaw : null;
        if ($delay !== null && (!is_finite($delay) || $delay < 0.0)) {
            $delay = null;
        }

        $urlRaw = null;
        $url = null;
        if (isset($parts[1]) && preg_match('/^\s*url\s*=\s*(.*)\s*$/i', $parts[1], $matches) === 1) {
            $urlRaw = trim((string) $matches[1]);
            $url = trim($urlRaw, " \t\r\n\f\"'");
        }

        return [
            'contentRaw' => $content,
            'delayRaw' => $delayRaw === '' ? null : $delayRaw,
            'delay' => $delay,
            'urlRaw' => $urlRaw,
            'url' => $url,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function breakElementSummary(string $name): array
    {
        return match ($name) {
            'br' => [
                'breakElement' => 'line-break',
                'breakTag' => 'br',
                'textEquivalent' => "\n",
                'hardBreak' => true,
            ],
            'wbr' => [
                'breakElement' => 'word-break-opportunity',
                'breakTag' => 'wbr',
                'textEquivalent' => '',
                'softBreakOpportunity' => true,
            ],
            'hr' => [
                'breakElement' => 'thematic-break',
                'breakTag' => 'hr',
                'blockSeparator' => true,
            ],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function rubySummary(\DOMElement $element, string $name): array
    {
        if ($name === 'ruby') {
            $baseTexts = self::rubyBaseTexts($element);
            $annotations = self::rubyAnnotationSummaries($element);
            $fallbackTexts = self::rubyFallbackTexts($element);

            return [
                'ruby' => 'ruby',
                'rubyText' => self::normalizedText($element),
                'rubyBaseTexts' => $baseTexts,
                'rubyBaseCount' => count($baseTexts),
                'rubyAnnotationTexts' => array_values(array_map(
                    static fn (array $annotation): string => (string) $annotation['text'],
                    $annotations
                )),
                'rubyAnnotations' => $annotations,
                'rubyAnnotationCount' => count($annotations),
                'rubyFallbackTexts' => $fallbackTexts,
                'rubyFallbackCount' => count($fallbackTexts),
            ];
        }

        if ($name === 'rb') {
            return [
                'rubyPart' => 'base',
                'rubyBaseText' => self::normalizedText($element),
            ];
        }

        if ($name === 'rt') {
            return [
                'rubyPart' => 'annotation',
                'rubyAnnotationText' => self::normalizedText($element),
            ];
        }

        if ($name === 'rp') {
            return [
                'rubyPart' => 'fallback-parenthesis',
                'rubyFallbackText' => self::normalizedText($element),
            ];
        }

        $annotations = array_values(array_map(
            static fn (\DOMElement $annotation): string => self::normalizedText($annotation),
            self::childHtmlElements($element, 'rt')
        ));

        return [
            'rubyPart' => 'annotation-container',
            'rubyAnnotationTexts' => $annotations,
            'rubyAnnotationCount' => count($annotations),
        ];
    }

    /**
     * @return list<string>
     */
    private static function rubyBaseTexts(\DOMElement $ruby): array
    {
        $baseTexts = [];
        foreach ($ruby->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $text = preg_replace('/[ \t\r\n\f]+/u', ' ', $child->nodeValue ?? '') ?? ($child->nodeValue ?? '');
                $text = trim($text);
                if ($text !== '') {
                    $baseTexts[] = $text;
                }
                continue;
            }

            if (!$child instanceof \DOMElement || self::htmlElementName($child) !== 'rb') {
                continue;
            }

            $text = self::normalizedText($child);
            if ($text !== '') {
                $baseTexts[] = $text;
            }
        }

        return $baseTexts;
    }

    /**
     * @return list<array{container:string|null, text:string}>
     */
    private static function rubyAnnotationSummaries(\DOMElement $ruby): array
    {
        $annotations = [];
        foreach ($ruby->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $name = self::htmlElementName($child);
            if ($name === 'rt') {
                $annotations[] = [
                    'container' => null,
                    'text' => self::normalizedText($child),
                ];
                continue;
            }

            if ($name !== 'rtc') {
                continue;
            }

            foreach (self::childHtmlElements($child, 'rt') as $annotation) {
                $annotations[] = [
                    'container' => 'rtc',
                    'text' => self::normalizedText($annotation),
                ];
            }
        }

        return $annotations;
    }

    /**
     * @return list<string>
     */
    private static function rubyFallbackTexts(\DOMElement $ruby): array
    {
        $fallbackTexts = [];
        foreach (self::childHtmlElements($ruby, 'rp') as $fallback) {
            $fallbackTexts[] = self::normalizedText($fallback);
        }

        return $fallbackTexts;
    }

    /**
     * @return array<string, mixed>
     */
    private static function activeContentSummary(\DOMElement $element, string $name): array
    {
        $text = $element->textContent;
        if ($name === 'script') {
            $typeRaw = self::attributeOrNull($element, 'type');
            $type = $typeRaw === null ? null : strtolower(trim($typeRaw));

            return [
                'activeContent' => 'script',
                'scriptSourceKind' => $element->hasAttribute('src') ? 'external' : 'inline',
                'src' => self::attributeOrNull($element, 'src'),
                'scriptTypeRaw' => $typeRaw,
                'scriptType' => $type,
                'module' => $type === 'module',
                'async' => $element->hasAttribute('async'),
                'defer' => $element->hasAttribute('defer'),
                'nomodule' => $element->hasAttribute('nomodule'),
                'crossorigin' => self::attributeOrNull($element, 'crossorigin'),
                'integrity' => self::attributeOrNull($element, 'integrity'),
                'referrerpolicy' => self::attributeOrNull($element, 'referrerpolicy'),
                'fetchpriority' => self::attributeOrNull($element, 'fetchpriority'),
                'blockingRaw' => self::attributeOrNull($element, 'blocking'),
                'blockingTokens' => $element->hasAttribute('blocking') ? self::spaceSeparatedTokens($element->getAttribute('blocking')) : [],
                'scriptText' => $text,
                'scriptTextLength' => strlen($text),
                'scriptTextSha256' => hash('sha256', $text),
                'activeReviewPolicy' => $element->hasAttribute('src') ? 'external-script-source' : 'inline-script-source',
            ];
        }

        $typeRaw = self::attributeOrNull($element, 'type');
        $type = $typeRaw === null ? null : strtolower(trim($typeRaw));

        return [
            'activeContent' => 'style',
            'styleSourceKind' => 'inline',
            'styleTypeRaw' => $typeRaw,
            'styleType' => $type,
            'media' => self::attributeOrNull($element, 'media'),
            'disabled' => $element->hasAttribute('disabled'),
            'blockingRaw' => self::attributeOrNull($element, 'blocking'),
            'blockingTokens' => $element->hasAttribute('blocking') ? self::spaceSeparatedTokens($element->getAttribute('blocking')) : [],
            'styleText' => $text,
            'styleTextLength' => strlen($text),
            'styleTextSha256' => hash('sha256', $text),
            'activeReviewPolicy' => 'inline-style-source',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function templateSummary(\DOMElement $element): array
    {
        $text = $element->textContent;

        $summary = [
            'template' => 'inert-source',
            'templateText' => $text,
            'templateTextLength' => strlen($text),
            'templateTextSha256' => hash('sha256', $text),
            'templateContainsMarkupLikeText' => preg_match('/<\s*[A-Za-z!\/?]/', $text) === 1,
            'templateContainsActiveLikeText' => preg_match('/<\s*(script|style|iframe|object|embed|link|meta)\b|<!doctype|<\?/i', $text) === 1,
            'templateReviewPolicy' => 'template-inert-escaped-source',
        ];

        return $summary + self::templateContentReviewSummary($text);
    }

    /**
     * @return array<string, mixed>
     */
    private static function templateContentReviewSummary(string $source): array
    {
        $summary = [
            'templateContentReviewPolicy' => 'template-content-inert-fragment-review',
            'templateContentByteLength' => strlen($source),
            'templateContentSha256' => hash('sha256', $source),
            'templateContentParsed' => false,
            'templateContentDiagnostics' => [],
        ];

        if (strlen($source) > self::TEMPLATE_CONTENT_REVIEW_MAX_BYTES) {
            $summary['templateContentDiagnostics'] = ['template-content-review-limit-exceeded'];

            return $summary;
        }

        try {
            $fragment = self::loadHtmlFragment($source, 'template content fragment');
        } catch (\InvalidArgumentException $exception) {
            $summary['templateContentDiagnostics'] = ['template-content-unsafe-or-unparseable'];
            $summary['templateContentError'] = $exception->getMessage();

            return $summary;
        }

        $root = self::requireFragmentRoot($fragment);
        $text = self::normalizedText($root);
        $topLevelElementNames = [];
        foreach ($root->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $topLevelElementNames[] = self::htmlElementName($child);
            }
        }

        $summary['templateContentParsed'] = true;
        $summary['templateContentTopLevelElementNames'] = $topLevelElementNames;
        $summary['templateContentTopLevelElementCount'] = count($topLevelElementNames);
        $summary['templateContentTextLength'] = strlen($text);
        $summary['templateContentTextSha256'] = hash('sha256', $text);
        if ($text !== '') {
            $summary['templateContentText'] = $text;
        }
        $summary['templateContentLinkHrefs'] = self::inertHtmlFragmentAttributeValues($root, ['a', 'area'], 'href');
        $summary['templateContentImageSources'] = self::inertHtmlFragmentAttributeValues($root, ['img'], 'src');
        $forms = self::descendantHtmlElements($root, 'form');
        $summary['templateContentFormCount'] = count($forms);
        $summary['templateContentFormActions'] = array_values(array_filter(
            array_map(static fn (\DOMElement $form): ?string => self::attributeOrNull($form, 'action'), $forms),
            static fn (?string $action): bool => $action !== null && $action !== ''
        ));
        $summary['templateContentActiveElementNames'] = self::inertHtmlFragmentDescendantNames($root, ['script' => true, 'style' => true]);
        $summary['templateContentEmbeddedElementNames'] = self::inertHtmlFragmentDescendantNames($root, [
            'audio' => true,
            'canvas' => true,
            'embed' => true,
            'iframe' => true,
            'object' => true,
            'video' => true,
        ]);

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private static function noscriptSummary(\DOMElement $element): array
    {
        $text = $element->textContent;

        $summary = [
            'noscript' => 'fallback-source',
            'noscriptText' => $text,
            'noscriptTextLength' => strlen($text),
            'noscriptTextSha256' => hash('sha256', $text),
            'noscriptContainsMarkupLikeText' => preg_match('/<\s*[A-Za-z!\/?]/', $text) === 1,
            'noscriptContainsActiveLikeText' => preg_match('/<\s*(script|style|iframe|object|embed|link|meta)\b|<!doctype|<\?/i', $text) === 1,
            'noscriptReviewPolicy' => 'noscript-inert-escaped-source',
        ];

        return $summary + self::noscriptContentReviewSummary($text);
    }

    /**
     * @return array<string, mixed>
     */
    private static function noscriptContentReviewSummary(string $source): array
    {
        $summary = [
            'noscriptContentReviewPolicy' => 'noscript-content-inert-fragment-review',
            'noscriptContentByteLength' => strlen($source),
            'noscriptContentSha256' => hash('sha256', $source),
            'noscriptContentParsed' => false,
            'noscriptContentDiagnostics' => [],
        ];

        if (strlen($source) > self::NOSCRIPT_CONTENT_REVIEW_MAX_BYTES) {
            $summary['noscriptContentDiagnostics'] = ['noscript-content-review-limit-exceeded'];

            return $summary;
        }

        try {
            $fragment = self::loadHtmlFragment($source, 'noscript content fragment');
        } catch (\InvalidArgumentException $exception) {
            $summary['noscriptContentDiagnostics'] = ['noscript-content-unsafe-or-unparseable'];
            $summary['noscriptContentError'] = $exception->getMessage();

            return $summary;
        }

        $root = self::requireFragmentRoot($fragment);
        $text = self::normalizedText($root);
        $topLevelElementNames = [];
        foreach ($root->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $topLevelElementNames[] = self::htmlElementName($child);
            }
        }

        $summary['noscriptContentParsed'] = true;
        $summary['noscriptContentTopLevelElementNames'] = $topLevelElementNames;
        $summary['noscriptContentTopLevelElementCount'] = count($topLevelElementNames);
        $summary['noscriptContentTextLength'] = strlen($text);
        $summary['noscriptContentTextSha256'] = hash('sha256', $text);
        if ($text !== '') {
            $summary['noscriptContentText'] = $text;
        }
        $summary['noscriptContentLinkHrefs'] = self::inertHtmlFragmentAttributeValues($root, ['a', 'area'], 'href');
        $summary['noscriptContentImageSources'] = self::inertHtmlFragmentAttributeValues($root, ['img'], 'src');
        $forms = self::descendantHtmlElements($root, 'form');
        $summary['noscriptContentFormCount'] = count($forms);
        $summary['noscriptContentFormActions'] = array_values(array_filter(
            array_map(static fn (\DOMElement $form): ?string => self::attributeOrNull($form, 'action'), $forms),
            static fn (?string $action): bool => $action !== null && $action !== ''
        ));
        $summary['noscriptContentActiveElementNames'] = self::inertHtmlFragmentDescendantNames($root, ['script' => true, 'style' => true]);
        $summary['noscriptContentEmbeddedElementNames'] = self::inertHtmlFragmentDescendantNames($root, [
            'audio' => true,
            'canvas' => true,
            'embed' => true,
            'iframe' => true,
            'object' => true,
            'video' => true,
        ]);

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private static function textSemanticSummary(\DOMElement $element, string $name): array
    {
        $summary = [
            'textSemantic' => match ($name) {
                'abbr' => 'abbreviation',
                'b' => 'bring-attention',
                'bdi' => 'bidirectional-isolate',
                'bdo' => 'bidirectional-override',
                'code' => 'code',
                'dfn' => 'definition',
                'em' => 'stress-emphasis',
                'i' => 'idiomatic-offset',
                'kbd' => 'keyboard-input',
                'mark' => 'mark',
                's' => 'struck-text',
                'samp' => 'sample-output',
                'small' => 'side-comment',
                'strong' => 'strong-importance',
                'sub' => 'subscript',
                'sup' => 'superscript',
                'u' => 'unarticulated-annotation',
                'var' => 'variable',
                default => 'text',
            },
            'semanticTag' => $name,
            'semanticText' => self::normalizedText($element),
        ];

        if ($name === 'abbr') {
            $summary['abbreviationTitle'] = self::attributeOrNull($element, 'title');
        }
        if ($name === 'dfn') {
            $summary['definitionTerm'] = self::normalizedText($element);
            $summary['definitionTitle'] = self::attributeOrNull($element, 'title');
        }
        if ($name === 'bdi' || $name === 'bdo') {
            $direction = strtolower(trim($element->getAttribute('dir')));
            $summary['textDirection'] = in_array($direction, ['auto', 'ltr', 'rtl'], true) ? $direction : null;
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private static function dataElementSummary(\DOMElement $element): array
    {
        $value = self::attributeOrNull($element, 'value');

        return [
            'dataElement' => 'data',
            'dataText' => self::normalizedText($element),
            'dataValueRaw' => $value,
            'dataValue' => $value === null ? null : trim($value),
            'dataValueSource' => $value === null ? 'missing' : 'value-attribute',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function timeSummary(\DOMElement $element): array
    {
        $text = self::normalizedText($element);
        $raw = self::attributeOrNull($element, 'datetime');
        $source = $raw === null
            ? ($text === '' ? 'missing' : 'text')
            : 'datetime-attribute';
        $candidate = $raw ?? $text;
        $summary = [
            'time' => 'time',
            'timeText' => $text,
            'timeDatetimeRaw' => $raw,
            'timeDatetimeSource' => $source,
            'timeDatetime' => null,
            'timeDatetimeKind' => $source === 'missing' ? 'missing' : null,
            'timeDatetimeValid' => false,
        ];

        if ($source === 'missing') {
            return $summary;
        }

        $datetime = self::timeDatetimeSummary($candidate);
        if ($datetime === null) {
            $summary['timeDatetimeKind'] = 'invalid';

            return $summary;
        }

        $summary['timeDatetime'] = $datetime['value'];
        $summary['timeDatetimeKind'] = $datetime['kind'];
        $summary['timeDatetimeValid'] = true;

        return $summary;
    }

    /**
     * @return array{kind:string, value:string}|null
     */
    private static function timeDatetimeSummary(string $value): ?array
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 128 || preg_match('/[<>{}`]/', $value) === 1) {
            return null;
        }

        $datePattern = '([0-9]{4})-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])';
        $timePattern = '((?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9](?:\.[0-9]{1,3})?)?)';
        $timezonePattern = '([Zz]|[+-](?:[01][0-9]|2[0-3]):?[0-5][0-9])';

        if (preg_match('/^' . $datePattern . '[T ]' . $timePattern . $timezonePattern . '$/', $value, $matches) === 1) {
            if (!self::isValidDateParts((string) $matches[1], (string) $matches[2], (string) $matches[3])) {
                return null;
            }

            return [
                'kind' => 'global-datetime',
                'value' => (string) $matches[1] . '-' . (string) $matches[2] . '-' . (string) $matches[3]
                    . 'T' . (string) $matches[4]
                    . self::normalizeTimezone((string) $matches[5]),
            ];
        }

        if (preg_match('/^' . $datePattern . '[T ]' . $timePattern . '$/', $value, $matches) === 1) {
            if (!self::isValidDateParts((string) $matches[1], (string) $matches[2], (string) $matches[3])) {
                return null;
            }

            return [
                'kind' => 'local-datetime',
                'value' => (string) $matches[1] . '-' . (string) $matches[2] . '-' . (string) $matches[3]
                    . 'T' . (string) $matches[4],
            ];
        }

        if (preg_match('/^' . $datePattern . '$/', $value, $matches) === 1) {
            if (!self::isValidDateParts((string) $matches[1], (string) $matches[2], (string) $matches[3])) {
                return null;
            }

            return [
                'kind' => 'date',
                'value' => (string) $matches[1] . '-' . (string) $matches[2] . '-' . (string) $matches[3],
            ];
        }

        if (preg_match('/^([0-9]{4})-(0[1-9]|1[0-2])$/', $value, $matches) === 1) {
            return ['kind' => 'month', 'value' => (string) $matches[1] . '-' . (string) $matches[2]];
        }

        if (preg_match('/^([0-9]{4})-W(0[1-9]|[1-4][0-9]|5[0-3])$/', $value, $matches) === 1) {
            $year = (int) $matches[1];
            $week = (int) $matches[2];
            $date = (new \DateTimeImmutable())->setISODate($year, $week, 1);
            if ((int) $date->format('o') !== $year || (int) $date->format('W') !== $week) {
                return null;
            }

            return ['kind' => 'week', 'value' => (string) $matches[1] . '-W' . (string) $matches[2]];
        }

        if (preg_match('/^[0-9]{4}$/', $value) === 1) {
            return ['kind' => 'year', 'value' => $value];
        }

        if (preg_match('/^' . $timePattern . '$/', $value, $matches) === 1) {
            return ['kind' => 'time', 'value' => (string) $matches[1]];
        }

        $duration = self::timeDurationSummary($value);
        if ($duration !== null) {
            return ['kind' => 'duration', 'value' => $duration];
        }

        return null;
    }

    private static function timeDurationSummary(string $value): ?string
    {
        $duration = strtoupper($value);
        if ($duration === '' || preg_match('/^[P0-9YMWDTHS.]+$/', $duration) !== 1) {
            return null;
        }

        if (preg_match('/^P[0-9]+W$/', $duration) === 1) {
            return $duration;
        }

        if (str_contains($duration, 'W')) {
            return null;
        }

        if (preg_match('/^P(?=.*[0-9])(?:[0-9]+Y)?(?:[0-9]+M)?(?:[0-9]+D)?(?:T(?:[0-9]+H)?(?:[0-9]+M)?(?:[0-9]+(?:\.[0-9]{1,3})?S)?)?$/', $duration) !== 1) {
            return null;
        }

        if (str_ends_with($duration, 'T')) {
            return null;
        }

        return $duration;
    }

    /**
     * @return array<string, mixed>
     */
    private static function quoteSummary(\DOMElement $element, string $name): array
    {
        if ($name === 'cite') {
            $text = self::normalizedText($element);

            return [
                'citedWork' => 'cite',
                'citedWorkText' => $text,
                'citation' => 'cite',
                'citationText' => $text,
            ];
        }

        $cite = self::attributeOrNull($element, 'cite');
        $footer = $name === 'blockquote' ? self::firstChildHtmlElement($element, 'footer') : null;
        $citationTexts = array_values(array_map(
            static fn (\DOMElement $citation): string => self::normalizedText($citation),
            self::descendantHtmlElements($element, 'cite'),
        ));

        return [
            'quote' => $name === 'blockquote' ? 'block' : 'inline',
            'quoteTag' => $name,
            'quoteCite' => $cite,
            'quoteCiteRaw' => $cite,
            'quoteCiteNormalized' => $cite === null ? null : trim($cite),
            'quoteText' => self::normalizedText($element),
            'attributionText' => $footer instanceof \DOMElement ? self::normalizedText($footer) : null,
            'citationTexts' => $citationTexts,
            'citationCount' => count($citationTexts),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function figureSummary(\DOMElement $element, string $name): array
    {
        if ($name === 'figure') {
            $captions = self::childHtmlElements($element, 'figcaption');

            return [
                'figurePart' => 'figure',
                'captionText' => isset($captions[0]) ? self::normalizedText($captions[0]) : null,
                'captionCount' => count($captions),
            ];
        }

        return [
            'figurePart' => 'caption',
            'captionText' => self::normalizedText($element),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function tableElementSummary(\DOMElement $element, string $name): array
    {
        if ($name === 'table') {
            $captions = self::childHtmlElements($element, 'caption');

            return [
                'tablePart' => 'table',
                'captionText' => isset($captions[0]) ? self::normalizedText($captions[0]) : null,
                'captionCount' => count($captions),
            ];
        }

        if ($name === 'caption') {
            return [
                'tablePart' => 'caption',
                'captionText' => self::normalizedText($element),
            ];
        }

        if ($name === 'colgroup') {
            return [
                'tablePart' => 'column-group',
                'spanRaw' => self::attributeOrNull($element, 'span'),
                'span' => self::positiveIntegerAttribute($element, 'span', 1, 1000),
            ];
        }

        if ($name === 'col') {
            return [
                'tablePart' => 'column',
                'spanRaw' => self::attributeOrNull($element, 'span'),
                'span' => self::positiveIntegerAttribute($element, 'span', 1, 1000),
            ];
        }

        if (in_array($name, ['thead', 'tbody', 'tfoot'], true)) {
            return [
                'tablePart' => match ($name) {
                    'thead' => 'header-group',
                    'tfoot' => 'footer-group',
                    default => 'body-group',
                },
            ];
        }

        if ($name === 'tr') {
            return ['tablePart' => 'row'];
        }

        if ($name === 'td' || $name === 'th') {
            $headersRaw = self::attributeOrNull($element, 'headers');
            $summary = [
                'tablePart' => 'cell',
                'tableCell' => $name === 'th' ? 'header' : 'data',
                'colSpanRaw' => self::attributeOrNull($element, 'colspan'),
                'colSpan' => self::positiveIntegerAttribute($element, 'colspan', 1, 1000),
                'rowSpanRaw' => self::attributeOrNull($element, 'rowspan'),
                'rowSpan' => self::nonNegativeIntegerAttribute($element, 'rowspan', 1, 65534),
                'headersRaw' => $headersRaw,
                'headers' => $headersRaw === null ? [] : self::spaceSeparatedTokens($headersRaw),
            ];

            if ($name === 'th') {
                $summary['scopeRaw'] = self::attributeOrNull($element, 'scope');
                $summary['scope'] = self::tableHeaderScope($element);
                $summary['abbr'] = self::attributeOrNull($element, 'abbr');
            }

            return $summary;
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function listSummary(\DOMElement $element, string $name): array
    {
        if ($name === 'li') {
            return [
                'listItem' => true,
            ] + self::listItemOrdinalSummary($element);
        }

        if ($name === 'ol') {
            $startRaw = self::attributeOrNull($element, 'start');

            return [
                'list' => 'ordered',
                'reversed' => $element->hasAttribute('reversed'),
                'startRaw' => $startRaw,
                'start' => self::integerAttribute($element, 'start', 1),
                'markerType' => self::attributeOrNull($element, 'type'),
            ];
        }

        return [
            'list' => $name === 'menu' ? 'menu' : 'unordered',
            'markerType' => self::attributeOrNull($element, 'type'),
        ];
    }

    /**
     * @return array{valueRaw:?string, value:?int, listOrdinal:?int, listOrdinalSource:?string}
     */
    private static function listItemOrdinalSummary(\DOMElement $item): array
    {
        $valueRaw = self::attributeOrNull($item, 'value');
        $value = self::integerAttribute($item, 'value', null);
        $summary = [
            'valueRaw' => $valueRaw,
            'value' => $value,
            'listOrdinal' => null,
            'listOrdinalSource' => null,
        ];

        $parent = $item->parentNode;
        if (!$parent instanceof \DOMElement || self::htmlElementName($parent) !== 'ol') {
            return $summary;
        }

        $items = self::childHtmlElements($parent, 'li');
        $reversed = $parent->hasAttribute('reversed');
        $start = self::integerAttribute($parent, 'start', null);
        $current = $start ?? ($reversed ? count($items) : 1);
        $implicitSource = $start !== null ? 'start-attribute' : ($reversed ? 'reversed-count' : 'default-start');

        foreach ($items as $child) {
            $childValue = self::integerAttribute($child, 'value', null);
            $ordinal = $childValue ?? $current;
            if ($child->isSameNode($item)) {
                $summary['listOrdinal'] = $ordinal;
                $summary['listOrdinalSource'] = $childValue !== null ? 'value-attribute' : $implicitSource;

                return $summary;
            }

            $current = $ordinal + ($reversed ? -1 : 1);
            $implicitSource = $childValue !== null ? 'previous-value' : $implicitSource;
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private static function definitionListSummary(\DOMElement $element, string $name): array
    {
        if ($name === 'dt') {
            return [
                'definitionListPart' => 'term',
                'termText' => self::normalizedText($element),
            ];
        }

        if ($name === 'dd') {
            return [
                'definitionListPart' => 'definition',
                'definitionText' => self::normalizedText($element),
            ];
        }

        $items = self::definitionListItems($element);
        $terms = [];
        $definitions = [];
        foreach ($items as $item) {
            array_push($terms, ...$item['terms']);
            array_push($definitions, ...$item['definitions']);
        }

        return [
            'definitionList' => 'dl',
            'termCount' => count($terms),
            'definitionCount' => count($definitions),
            'itemCount' => count($items),
            'terms' => $terms,
            'definitions' => $definitions,
            'items' => $items,
        ];
    }

    /**
     * @return list<array{terms:list<string>, definitions:list<string>, termCount:int, definitionCount:int}>
     */
    private static function definitionListItems(\DOMElement $definitionList): array
    {
        $items = [];
        $terms = [];
        $definitions = [];

        foreach ($definitionList->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $name = strtolower(self::htmlElementName($child));
            if ($name === 'dt') {
                if ($definitions !== []) {
                    $items[] = self::definitionListItemSummary($terms, $definitions);
                    $terms = [];
                    $definitions = [];
                }

                $terms[] = self::normalizedText($child);
                continue;
            }

            if ($name === 'dd') {
                $definitions[] = self::normalizedText($child);
            }
        }

        if ($terms !== [] || $definitions !== []) {
            $items[] = self::definitionListItemSummary($terms, $definitions);
        }

        return $items;
    }

    /**
     * @param list<string> $terms
     * @param list<string> $definitions
     * @return array{terms:list<string>, definitions:list<string>, termCount:int, definitionCount:int}
     */
    private static function definitionListItemSummary(array $terms, array $definitions): array
    {
        return [
            'terms' => $terms,
            'definitions' => $definitions,
            'termCount' => count($terms),
            'definitionCount' => count($definitions),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function globalHtmlAttributeSummary(\DOMElement $element): array
    {
        $summary = [];
        $attributes = self::htmlAttributes($element);

        if (array_key_exists('id', $attributes)) {
            $summary['elementId'] = $attributes['id'];
        }

        if (array_key_exists('class', $attributes)) {
            $summary['classRaw'] = $attributes['class'];
            $summary['classList'] = self::spaceSeparatedTokens($attributes['class']);
        }

        $dataAttributes = self::dataAttributeSummary($attributes);
        if ($dataAttributes !== []) {
            $summary['dataAttributes'] = $dataAttributes;
            $summary['dataset'] = self::datasetSummary($dataAttributes);
        }

        $ariaAttributes = self::ariaAttributeSummary($attributes);
        if ($ariaAttributes !== []) {
            $summary['ariaAttributes'] = $ariaAttributes;
            $ariaReferences = self::ariaReferenceSummary($element, $ariaAttributes);
            if ($ariaReferences !== []) {
                $summary['ariaReferences'] = $ariaReferences;
                $summary['ariaReferenceAttributes'] = array_keys($ariaReferences);
                $summary['ariaReferenceCount'] = count($ariaReferences);
            }
        }

        if (array_key_exists('role', $attributes)) {
            $summary['roleRaw'] = $attributes['role'];
            $summary['roles'] = self::spaceSeparatedTokens($attributes['role']);
        }

        $microdata = self::microdataAttributeSummary($element, $attributes);
        if ($microdata !== []) {
            $summary += $microdata;
        }

        $rdfa = self::rdfaAttributeSummary($attributes);
        if ($rdfa !== []) {
            $summary += $rdfa;
        }

        if (array_key_exists('lang', $attributes)) {
            $summary['languageRaw'] = $attributes['lang'];
            $summary['language'] = trim($attributes['lang']);
        } elseif (array_key_exists('xml:lang', $attributes)) {
            $summary['languageRaw'] = $attributes['xml:lang'];
            $summary['language'] = trim($attributes['xml:lang']);
        }

        if (array_key_exists('dir', $attributes)) {
            $dir = strtolower(trim($attributes['dir']));
            $summary['dirRaw'] = $attributes['dir'];
            $summary['direction'] = in_array($dir, ['ltr', 'rtl', 'auto'], true) ? $dir : null;
        }

        if (array_key_exists('title', $attributes)) {
            $summary['titleAttribute'] = $attributes['title'];
        }

        if (array_key_exists('hidden', $attributes)) {
            $hidden = strtolower(trim($attributes['hidden']));
            $summary['hiddenRaw'] = $attributes['hidden'];
            $summary['hiddenState'] = $hidden === 'until-found' ? 'until-found' : 'hidden';
        }

        if (array_key_exists('inert', $attributes)) {
            $summary['inertRaw'] = $attributes['inert'];
            $summary['inert'] = true;
        }

        if (array_key_exists('translate', $attributes)) {
            $translate = strtolower(trim($attributes['translate']));
            $summary['translateRaw'] = $attributes['translate'];
            $summary['translate'] = match ($translate) {
                '', 'yes' => true,
                'no' => false,
                default => null,
            };
        }

        if (array_key_exists('contenteditable', $attributes)) {
            $contentEditable = strtolower(trim($attributes['contenteditable']));
            $summary['contentEditableRaw'] = $attributes['contenteditable'];
            $summary['contentEditable'] = match ($contentEditable) {
                '', 'true' => true,
                'false' => false,
                'plaintext-only' => 'plaintext-only',
                default => null,
            };
        }

        if (array_key_exists('draggable', $attributes)) {
            $draggable = strtolower(trim($attributes['draggable']));
            $summary['draggableRaw'] = $attributes['draggable'];
            $summary['draggable'] = match ($draggable) {
                'true' => true,
                'false' => false,
                default => null,
            };
        }

        if (array_key_exists('spellcheck', $attributes)) {
            $spellcheck = strtolower(trim($attributes['spellcheck']));
            $summary['spellcheckRaw'] = $attributes['spellcheck'];
            $summary['spellcheck'] = match ($spellcheck) {
                '', 'true' => true,
                'false' => false,
                default => null,
            };
        }

        if (array_key_exists('slot', $attributes)) {
            $slot = self::slotAttributeSummary($attributes['slot']);
            $summary['slotRaw'] = $attributes['slot'];
            $summary['slotName'] = $slot['name'];
            $summary['slotValid'] = $slot['valid'];
        }

        if (array_key_exists('part', $attributes)) {
            $parts = self::partTokenListSummary($attributes['part']);
            $summary['partRaw'] = $attributes['part'];
            $summary['partTokens'] = $parts['tokens'];
            $summary['partNames'] = $parts['names'];
            $summary['invalidPartTokens'] = $parts['invalid'];
            $summary['partValid'] = $parts['valid'];
        }

        if (array_key_exists('exportparts', $attributes)) {
            $exportParts = self::exportPartsSummary($attributes['exportparts']);
            $summary['exportPartsRaw'] = $attributes['exportparts'];
            $summary['exportParts'] = $exportParts['items'];
            $summary['exportPartNames'] = $exportParts['names'];
            $summary['exportPartAliases'] = $exportParts['aliases'];
            $summary['invalidExportParts'] = $exportParts['invalid'];
            $summary['exportPartsValid'] = $exportParts['valid'];
        }

        if (array_key_exists('is', $attributes)) {
            $custom = self::customElementNameSummary($attributes['is']);
            $summary['isRaw'] = $attributes['is'];
            $summary['customElementName'] = $custom['name'];
            $summary['customElementValid'] = $custom['valid'];
        }

        if (array_key_exists('accesskey', $attributes)) {
            $accessKey = self::accessKeySummary($attributes['accesskey']);
            $summary['accessKeyRaw'] = $attributes['accesskey'];
            $summary['accessKeyTokens'] = $accessKey['tokens'];
            $summary['accessKeys'] = $accessKey['keys'];
            $summary['invalidAccessKeyTokens'] = $accessKey['invalid'];
            $summary['accessKeyValid'] = $accessKey['valid'];
        }

        if (array_key_exists('autofocus', $attributes)) {
            $summary['autofocusRaw'] = $attributes['autofocus'];
            $summary['autofocus'] = true;
        }

        if (array_key_exists('tabindex', $attributes)) {
            $summary['tabIndexRaw'] = $attributes['tabindex'];
            $summary['tabIndex'] = self::integerAttribute($element, 'tabindex', null);
            $summary['tabIndexValid'] = $summary['tabIndex'] !== null;
        }

        if (array_key_exists('inputmode', $attributes)) {
            $inputMode = self::inputModeState($attributes['inputmode']);
            $summary['inputModeRaw'] = $attributes['inputmode'];
            $summary['inputMode'] = $inputMode;
            $summary['inputModeValid'] = $inputMode !== null;
        }

        if (array_key_exists('enterkeyhint', $attributes)) {
            $enterKeyHint = self::enterKeyHintState($attributes['enterkeyhint']);
            $summary['enterKeyHintRaw'] = $attributes['enterkeyhint'];
            $summary['enterKeyHint'] = $enterKeyHint;
            $summary['enterKeyHintValid'] = $enterKeyHint !== null;
        }

        if (array_key_exists('autocapitalize', $attributes)) {
            $autocapitalize = self::autocapitalizeState($attributes['autocapitalize']);
            $summary['autocapitalizeRaw'] = $attributes['autocapitalize'];
            $summary['autocapitalize'] = $autocapitalize;
            $summary['autocapitalizeValid'] = $autocapitalize !== null;
        }

        if (array_key_exists('popover', $attributes)) {
            $popover = self::popoverState($attributes['popover']);
            $summary['popoverRaw'] = $attributes['popover'];
            $summary['popoverState'] = $popover;
            $summary['popoverValid'] = $popover !== null;
        }

        if (array_key_exists('popovertarget', $attributes)) {
            $target = self::popoverTarget($attributes['popovertarget']);
            $summary['popoverTargetRaw'] = $attributes['popovertarget'];
            $summary['popoverTarget'] = $target;
            $summary['popoverTargetValid'] = $target !== null;
        }

        if (array_key_exists('popovertargetaction', $attributes)) {
            $action = self::popoverTargetAction($attributes['popovertargetaction']);
            $summary['popoverTargetActionRaw'] = $attributes['popovertargetaction'];
            $summary['popoverTargetAction'] = $action;
            $summary['popoverTargetActionValid'] = $action !== null;
        }

        return $summary;
    }

    /**
     * @param array<string, string> $attributes
     * @return array<string, mixed>
     */
    private static function rdfaAttributeSummary(array $attributes): array
    {
        $rdfaAttributes = ['about', 'content', 'datatype', 'inlist', 'prefix', 'property', 'rel', 'resource', 'rev', 'typeof', 'vocab'];
        $present = [];
        foreach ($rdfaAttributes as $attribute) {
            if (array_key_exists($attribute, $attributes)) {
                $present[] = $attribute;
            }
        }

        if ($present === []) {
            return [];
        }

        $triggerAttributes = array_diff($present, ['content', 'rel']);
        if ($triggerAttributes === []) {
            return [];
        }

        $summary = [
            'rdfa' => self::rdfaSummaryKind($attributes),
            'rdfaAttributes' => $present,
        ];

        if (array_key_exists('vocab', $attributes)) {
            $vocab = self::rdfaSingleTokenSummary($attributes['vocab']);
            $summary['rdfaVocabRaw'] = $attributes['vocab'];
            $summary['rdfaVocab'] = $vocab['value'];
            $summary['rdfaVocabValid'] = $vocab['valid'];
        }

        if (array_key_exists('prefix', $attributes)) {
            $prefixes = self::rdfaPrefixSummary($attributes['prefix']);
            $summary['rdfaPrefixRaw'] = $attributes['prefix'];
            $summary['rdfaPrefixTokens'] = $prefixes['tokens'];
            $summary['rdfaPrefixMappings'] = $prefixes['items'];
            $summary['rdfaPrefixes'] = $prefixes['prefixes'];
            $summary['invalidRdfaPrefixMappings'] = $prefixes['invalid'];
            $summary['rdfaPrefixValid'] = $prefixes['valid'];
        }

        if (array_key_exists('typeof', $attributes)) {
            $types = self::semanticMetadataTokenSummary($attributes['typeof']);
            $summary['rdfaTypeofRaw'] = $attributes['typeof'];
            $summary['rdfaTypeofTokens'] = $types['tokens'];
            $summary['rdfaTypes'] = $types['values'];
            $summary['invalidRdfaTypes'] = $types['invalid'];
            $summary['rdfaTypeofValid'] = $types['valid'];
        }

        if (array_key_exists('property', $attributes)) {
            $properties = self::semanticMetadataTokenSummary($attributes['property']);
            $summary['rdfaPropertyRaw'] = $attributes['property'];
            $summary['rdfaPropertyTokens'] = $properties['tokens'];
            $summary['rdfaProperties'] = $properties['values'];
            $summary['invalidRdfaProperties'] = $properties['invalid'];
            $summary['rdfaPropertyValid'] = $properties['valid'];
        }

        if (array_key_exists('rel', $attributes)) {
            $relations = self::semanticMetadataTokenSummary($attributes['rel']);
            $summary['rdfaRelRaw'] = $attributes['rel'];
            $summary['rdfaRelTokens'] = $relations['tokens'];
            $summary['rdfaRelations'] = $relations['values'];
            $summary['invalidRdfaRelations'] = $relations['invalid'];
            $summary['rdfaRelValid'] = $relations['valid'];
        }

        if (array_key_exists('rev', $attributes)) {
            $reverse = self::semanticMetadataTokenSummary($attributes['rev']);
            $summary['rdfaRevRaw'] = $attributes['rev'];
            $summary['rdfaRevTokens'] = $reverse['tokens'];
            $summary['rdfaReverseRelations'] = $reverse['values'];
            $summary['invalidRdfaReverseRelations'] = $reverse['invalid'];
            $summary['rdfaRevValid'] = $reverse['valid'];
        }

        if (array_key_exists('about', $attributes)) {
            $about = self::rdfaSingleTokenSummary($attributes['about']);
            $summary['rdfaAboutRaw'] = $attributes['about'];
            $summary['rdfaAbout'] = $about['value'];
            $summary['rdfaAboutValid'] = $about['valid'];
        }

        if (array_key_exists('resource', $attributes)) {
            $resource = self::rdfaSingleTokenSummary($attributes['resource']);
            $summary['rdfaResourceRaw'] = $attributes['resource'];
            $summary['rdfaResource'] = $resource['value'];
            $summary['rdfaResourceValid'] = $resource['valid'];
        }

        if (array_key_exists('datatype', $attributes)) {
            $datatype = self::rdfaSingleTokenSummary($attributes['datatype']);
            $summary['rdfaDatatypeRaw'] = $attributes['datatype'];
            $summary['rdfaDatatype'] = $datatype['value'];
            $summary['rdfaDatatypeValid'] = $datatype['valid'];
        }

        if (array_key_exists('content', $attributes)) {
            $summary['rdfaContentRaw'] = $attributes['content'];
            $summary['rdfaContent'] = $attributes['content'];
            $summary['rdfaContentValid'] = self::isSafeRdfaLiteral($attributes['content']);
        }

        if (array_key_exists('inlist', $attributes)) {
            $summary['rdfaInListRaw'] = $attributes['inlist'];
            $summary['rdfaInList'] = true;
        }

        return $summary;
    }

    /**
     * @param array<string, string> $attributes
     */
    private static function rdfaSummaryKind(array $attributes): string
    {
        if (array_key_exists('property', $attributes)) {
            return 'property';
        }

        if (array_key_exists('rel', $attributes) || array_key_exists('rev', $attributes)) {
            return 'relationship';
        }

        if (array_key_exists('typeof', $attributes) || array_key_exists('about', $attributes) || array_key_exists('resource', $attributes)) {
            return 'resource';
        }

        return 'metadata';
    }

    /**
     * @return array{value:?string, valid:bool}
     */
    private static function rdfaSingleTokenSummary(string $value): array
    {
        $trimmed = trim($value);

        return [
            'value' => $trimmed === '' ? null : $trimmed,
            'valid' => $trimmed !== '' && self::isSafeHtmlSemanticMetadataToken($trimmed),
        ];
    }

    /**
     * @return array{tokens:list<string>, items:list<array<string, mixed>>, prefixes:array<string, string>, invalid:list<string>, valid:bool}
     */
    private static function rdfaPrefixSummary(string $value): array
    {
        $tokens = self::spaceSeparatedTokens($value);
        $items = [];
        $prefixes = [];
        $invalid = [];
        $count = count($tokens);

        for ($index = 0; $index < $count; $index += 2) {
            $prefixToken = $tokens[$index];
            $iri = $tokens[$index + 1] ?? null;
            $prefix = str_ends_with($prefixToken, ':') ? substr($prefixToken, 0, -1) : null;
            $raw = $iri === null ? $prefixToken : $prefixToken . ' ' . $iri;
            $valid = $iri !== null
                && $prefix !== null
                && preg_match('/^[A-Za-z][A-Za-z0-9._-]*$/', $prefix) === 1
                && self::isSafeHtmlSemanticMetadataToken($iri);

            if ($valid) {
                $prefixes[$prefix] = $iri;
            } else {
                $invalid[] = $raw;
            }

            $items[] = [
                'raw' => $raw,
                'prefix' => $prefix,
                'iri' => $iri,
                'valid' => $valid,
            ];
        }

        return [
            'tokens' => $tokens,
            'items' => $items,
            'prefixes' => $prefixes,
            'invalid' => $invalid,
            'valid' => $tokens !== [] && $invalid === [],
        ];
    }

    private static function isSafeRdfaLiteral(string $value): bool
    {
        return preg_match('/[<\p{Cc}\p{Zl}\p{Zp}]/u', $value) !== 1;
    }

    /**
     * @param array<string, string> $attributes
     * @return array<string, mixed>
     */
    private static function microdataAttributeSummary(\DOMElement $element, array $attributes): array
    {
        $microdataAttributes = ['itemprop', 'itemref', 'itemscope', 'itemtype', 'itemid'];
        $hasMicrodata = false;
        foreach ($microdataAttributes as $attribute) {
            if (array_key_exists($attribute, $attributes)) {
                $hasMicrodata = true;
                break;
            }
        }

        if (!$hasMicrodata) {
            return [];
        }

        $summary = [
            'microdata' => array_key_exists('itemscope', $attributes)
                ? 'item'
                : (array_key_exists('itemprop', $attributes) ? 'property' : 'metadata'),
        ];

        if (array_key_exists('itemscope', $attributes)) {
            $summary['itemScopeRaw'] = $attributes['itemscope'];
            $summary['itemScope'] = true;
        }

        if (array_key_exists('itemprop', $attributes)) {
            $properties = self::semanticMetadataTokenSummary($attributes['itemprop']);
            $summary['itemPropRaw'] = $attributes['itemprop'];
            $summary['itemPropTokens'] = $properties['tokens'];
            $summary['itemProperties'] = $properties['values'];
            $summary['invalidItemProperties'] = $properties['invalid'];
            $summary['itemPropValid'] = $properties['valid'];
        }

        if (array_key_exists('itemtype', $attributes)) {
            $types = self::semanticMetadataTokenSummary($attributes['itemtype']);
            $summary['itemTypeRaw'] = $attributes['itemtype'];
            $summary['itemTypeTokens'] = $types['tokens'];
            $summary['itemTypes'] = $types['values'];
            $summary['invalidItemTypes'] = $types['invalid'];
            $summary['itemTypeValid'] = $types['valid'];
        }

        if (array_key_exists('itemid', $attributes)) {
            $itemId = trim($attributes['itemid']);
            $summary['itemIdRaw'] = $attributes['itemid'];
            $summary['itemId'] = $itemId === '' ? null : $itemId;
            $summary['itemIdValid'] = $itemId !== '' && self::isSafeHtmlSemanticMetadataToken($itemId);
        }

        if (array_key_exists('itemref', $attributes)) {
            $references = self::idReferenceTokenSummary($attributes['itemref']);
            $resolved = self::itemRefResolutionSummary($element, $references['values']);
            $summary['itemRefRaw'] = $attributes['itemref'];
            $summary['itemRefTokens'] = $references['tokens'];
            $summary['itemRefIds'] = $references['values'];
            $summary['invalidItemRefIds'] = $references['invalid'];
            $summary['itemRefValid'] = $references['valid'];
            $summary['itemRefResolvedIds'] = $resolved['resolved'];
            $summary['itemRefMissingIds'] = $resolved['missing'];
        }

        return $summary;
    }

    /**
     * @return array{tokens:list<string>, values:list<string>, invalid:list<string>, valid:bool}
     */
    private static function semanticMetadataTokenSummary(string $value): array
    {
        $tokens = self::spaceSeparatedTokens($value);
        $values = [];
        $invalid = [];
        foreach ($tokens as $token) {
            if (!self::isSafeHtmlSemanticMetadataToken($token)) {
                $invalid[] = $token;
                continue;
            }

            if (!in_array($token, $values, true)) {
                $values[] = $token;
            }
        }

        return [
            'tokens' => $tokens,
            'values' => $values,
            'invalid' => $invalid,
            'valid' => $tokens !== [] && $invalid === [],
        ];
    }

    private static function isSafeHtmlSemanticMetadataToken(string $token): bool
    {
        if ($token === '' || preg_match('/[\s<>"\'`{}\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u', $token) === 1) {
            return false;
        }

        $scheme = strtolower(strstr($token, ':', true) ?: '');
        if (in_array($scheme, ['javascript', 'vbscript', 'data'], true)) {
            return false;
        }
        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $token) === 1 && !str_contains($token, '://')) {
            return preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*:[A-Za-z0-9_.-]+$/', $token) === 1;
        }

        return true;
    }

    /**
     * @return array{tokens:list<string>, values:list<string>, invalid:list<string>, valid:bool}
     */
    private static function idReferenceTokenSummary(string $value): array
    {
        $tokens = self::spaceSeparatedTokens($value);
        $values = [];
        $invalid = [];
        foreach ($tokens as $token) {
            if (!self::isHtmlIdReferenceToken($token)) {
                $invalid[] = $token;
                continue;
            }

            if (!in_array($token, $values, true)) {
                $values[] = $token;
            }
        }

        return [
            'tokens' => $tokens,
            'values' => $values,
            'invalid' => $invalid,
            'valid' => $tokens !== [] && $invalid === [],
        ];
    }

    private static function isHtmlIdReferenceToken(string $token): bool
    {
        return $token !== ''
            && preg_match('/[\s<>"\'`\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u', $token) !== 1;
    }

    /**
     * @param list<string> $ids
     * @return array{resolved:list<string>, missing:list<string>}
     */
    private static function itemRefResolutionSummary(\DOMElement $element, array $ids): array
    {
        $resolved = [];
        $missing = [];
        foreach ($ids as $id) {
            if (self::htmlElementById($element, $id) instanceof \DOMElement) {
                $resolved[] = $id;
                continue;
            }

            $missing[] = $id;
        }

        return [
            'resolved' => $resolved,
            'missing' => $missing,
        ];
    }

    private static function inputModeState(string $value): ?string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['none', 'text', 'tel', 'email', 'url', 'numeric', 'decimal', 'search'], true)
            ? $value
            : null;
    }

    private static function enterKeyHintState(string $value): ?string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['enter', 'done', 'go', 'next', 'previous', 'search', 'send'], true)
            ? $value
            : null;
    }

    private static function autocapitalizeState(string $value): ?string
    {
        $value = strtolower(trim($value));

        return match ($value) {
            'off', 'none' => 'none',
            'on', 'sentences' => 'sentences',
            'words' => 'words',
            'characters' => 'characters',
            default => null,
        };
    }

    private static function popoverState(string $value): ?string
    {
        $value = strtolower(trim($value));

        return match ($value) {
            '', 'auto' => 'auto',
            'manual' => 'manual',
            default => null,
        };
    }

    private static function popoverTarget(string $value): ?string
    {
        $target = trim($value);
        if ($target === '' || preg_match('/[\s<>"\'`]/u', $target) === 1) {
            return null;
        }

        return $target;
    }

    private static function popoverTargetAction(string $value): ?string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['hide', 'show', 'toggle'], true) ? $value : null;
    }

    /**
     * @return array{name:?string, valid:bool}
     */
    private static function slotAttributeSummary(string $value): array
    {
        $name = trim($value);

        return [
            'name' => $name === '' ? null : $name,
            'valid' => $name !== '' && self::isHtmlReferenceToken($name),
        ];
    }

    /**
     * @return array{tokens:list<string>, names:list<string>, invalid:list<string>, valid:bool}
     */
    private static function partTokenListSummary(string $value): array
    {
        $tokens = self::spaceSeparatedTokens($value);
        $names = [];
        $invalid = [];
        foreach ($tokens as $token) {
            if (!self::isHtmlReferenceToken($token)) {
                $invalid[] = $token;
                continue;
            }

            if (!in_array($token, $names, true)) {
                $names[] = $token;
            }
        }

        return [
            'tokens' => $tokens,
            'names' => $names,
            'invalid' => $invalid,
            'valid' => $tokens !== [] && $invalid === [],
        ];
    }

    /**
     * @return array{items:list<array<string, mixed>>, names:list<string>, aliases:list<string>, invalid:list<string>, valid:bool}
     */
    private static function exportPartsSummary(string $value): array
    {
        $items = [];
        $names = [];
        $aliases = [];
        $invalid = [];
        foreach (explode(',', $value) as $rawItem) {
            $raw = trim($rawItem);
            if ($raw === '') {
                continue;
            }

            $segments = array_map('trim', explode(':', $raw));
            $source = $segments[0] ?? '';
            $alias = count($segments) === 1 ? $source : ($segments[1] ?? '');
            $valid = count($segments) <= 2
                && self::isHtmlReferenceToken($source)
                && self::isHtmlReferenceToken($alias);
            if (!$valid) {
                $invalid[] = $raw;
            } else {
                if (!in_array($source, $names, true)) {
                    $names[] = $source;
                }
                if (!in_array($alias, $aliases, true)) {
                    $aliases[] = $alias;
                }
            }

            $items[] = [
                'raw' => $raw,
                'source' => $source === '' ? null : $source,
                'alias' => $alias === '' ? null : $alias,
                'renamed' => $valid && $source !== $alias,
                'valid' => $valid,
            ];
        }

        return [
            'items' => $items,
            'names' => $names,
            'aliases' => $aliases,
            'invalid' => $invalid,
            'valid' => $items !== [] && $invalid === [],
        ];
    }

    /**
     * @return array{name:?string, valid:bool}
     */
    private static function customElementNameSummary(string $value): array
    {
        $name = trim($value);

        return [
            'name' => $name === '' ? null : $name,
            'valid' => $name !== ''
                && preg_match('/^[a-z][.0-9_a-z-]*-[.0-9_a-z-]*$/', $name) === 1
                && self::isHtmlReferenceToken($name),
        ];
    }

    private static function isHtmlReferenceToken(string $token): bool
    {
        return $token !== ''
            && preg_match('/[\s<>"\'`=,:\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u', $token) !== 1;
    }

    /**
     * @return array{tokens:list<string>, keys:list<string>, invalid:list<string>, valid:bool}
     */
    private static function accessKeySummary(string $value): array
    {
        $tokens = self::spaceSeparatedTokens($value);
        $keys = [];
        $invalid = [];
        foreach ($tokens as $token) {
            if (!self::isAccessKeyToken($token)) {
                $invalid[] = $token;
                continue;
            }

            if (!in_array($token, $keys, true)) {
                $keys[] = $token;
            }
        }

        return [
            'tokens' => $tokens,
            'keys' => $keys,
            'invalid' => $invalid,
            'valid' => $tokens !== [] && $keys !== [] && $invalid === [],
        ];
    }

    private static function isAccessKeyToken(string $token): bool
    {
        if ($token === '' || preg_match('/[<>"\'`{}]/u', $token) === 1) {
            return false;
        }
        if (preg_match('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u', $token) === 1) {
            return false;
        }

        return preg_match_all('/./us', $token) === 1;
    }

    /**
     * @param array<string, string> $attributes
     * @return array<string, string>
     */
    private static function dataAttributeSummary(array $attributes): array
    {
        $data = [];
        foreach ($attributes as $name => $value) {
            if (!str_starts_with($name, 'data-') || strlen($name) <= 5) {
                continue;
            }

            $data[$name] = $value;
        }

        return $data;
    }

    /**
     * @param array<string, string> $dataAttributes
     * @return array<string, string>
     */
    private static function datasetSummary(array $dataAttributes): array
    {
        $dataset = [];
        foreach ($dataAttributes as $name => $value) {
            $datasetName = self::datasetPropertyName($name);
            if ($datasetName !== '') {
                $dataset[$datasetName] = $value;
            }
        }
        ksort($dataset);

        return $dataset;
    }

    private static function datasetPropertyName(string $attributeName): string
    {
        $name = substr($attributeName, 5);
        $property = '';
        $length = strlen($name);
        for ($index = 0; $index < $length; ++$index) {
            $char = $name[$index];
            $next = $index + 1 < $length ? $name[$index + 1] : '';
            if ($char === '-' && $next >= 'a' && $next <= 'z') {
                $property .= strtoupper($next);
                ++$index;
                continue;
            }

            $property .= $char;
        }

        return $property;
    }

    /**
     * @param array<string, string> $attributes
     * @return array<string, string>
     */
    private static function ariaAttributeSummary(array $attributes): array
    {
        $aria = [];
        foreach ($attributes as $name => $value) {
            if (str_starts_with($name, 'aria-') && strlen($name) > 5) {
                $aria[$name] = $value;
            }
        }

        return $aria;
    }

    /**
     * @param array<string, string> $ariaAttributes
     * @return array<string, array<string, mixed>>
     */
    private static function ariaReferenceSummary(\DOMElement $element, array $ariaAttributes): array
    {
        $knownIds = self::htmlDocumentElementIds($element);
        $references = [];
        foreach (self::ARIA_ID_REFERENCE_ATTRIBUTES as $attribute => $multiple) {
            if (!array_key_exists($attribute, $ariaAttributes)) {
                continue;
            }

            $tokens = self::spaceSeparatedTokens($ariaAttributes[$attribute]);
            $ids = [];
            $duplicates = [];
            $invalid = [];
            foreach ($tokens as $token) {
                if (!self::isHtmlReferenceToken($token)) {
                    $invalid[] = $token;
                    continue;
                }

                if (in_array($token, $ids, true)) {
                    if (!in_array($token, $duplicates, true)) {
                        $duplicates[] = $token;
                    }
                    continue;
                }

                $ids[] = $token;
            }

            $present = [];
            $missing = [];
            foreach ($ids as $id) {
                if (isset($knownIds[$id])) {
                    $present[] = $id;
                } else {
                    $missing[] = $id;
                }
            }

            $valid = $tokens !== []
                && $invalid === []
                && ($multiple || count($ids) <= 1);
            $references[$attribute] = [
                'raw' => $ariaAttributes[$attribute],
                'multiple' => $multiple,
                'tokens' => $tokens,
                'ids' => $ids,
                'duplicateIds' => $duplicates,
                'invalidTokens' => $invalid,
                'presentIds' => $present,
                'missingIds' => $missing,
                'valid' => $valid,
                'resolved' => $valid && $missing === [],
            ];
        }
        ksort($references);

        return $references;
    }

    /**
     * @return array<string, true>
     */
    private static function htmlDocumentElementIds(\DOMElement $element): array
    {
        $document = $element->ownerDocument;
        if (!$document instanceof \DOMDocument) {
            return [];
        }

        $ids = [];
        foreach ($document->getElementsByTagName('*') as $candidate) {
            if (!$candidate instanceof \DOMElement || !$candidate->hasAttribute('id')) {
                continue;
            }

            $id = trim($candidate->getAttribute('id'));
            if ($id !== '' && self::isHtmlReferenceToken($id)) {
                $ids[$id] = true;
            }
        }

        return $ids;
    }

    private static function inputType(\DOMElement $input): string
    {
        $type = strtolower(trim($input->getAttribute('type')));

        return $type === '' ? 'text' : $type;
    }

    private static function buttonType(\DOMElement $button): string
    {
        $type = strtolower(trim($button->getAttribute('type')));

        return in_array($type, ['button', 'reset', 'submit'], true) ? $type : 'submit';
    }

    /**
     * @return array<string, mixed>
     */
    private static function formSubmissionSummary(\DOMElement $form): array
    {
        $acceptCharsetRaw = self::attributeOrNull($form, 'accept-charset');
        $controls = self::formOwnedControlSummaries($form);

        return [
            'formSubmission' => 'form',
            'action' => self::attributeOrNull($form, 'action'),
            'method' => self::formMethod($form, 'method', 'get'),
            'enctype' => self::formEnctype($form, 'enctype', 'application/x-www-form-urlencoded'),
            'target' => self::attributeOrNull($form, 'target'),
            'autocomplete' => self::formAutocomplete($form),
            'novalidate' => $form->hasAttribute('novalidate'),
            'acceptCharsetRaw' => $acceptCharsetRaw,
            'acceptCharsets' => $acceptCharsetRaw === null ? [] : self::spaceSeparatedTokens($acceptCharsetRaw),
            'controlCount' => count($controls),
            'externalControlCount' => count(array_filter(
                $controls,
                static fn (array $control): bool => ($control['formOwnerSource'] ?? null) === 'form-attribute'
            )),
            'controls' => $controls,
            'controlNames' => self::formOwnedControlNames($controls),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function formOwnerSummary(\DOMElement $control): array
    {
        $form = self::formOwnerElement($control);
        $formAttribute = self::attributeOrNull($control, 'form');
        $formTargetId = $formAttribute === null ? null : trim($formAttribute);
        $source = 'none';
        if ($formAttribute !== null) {
            $source = $form instanceof \DOMElement ? 'form-attribute' : 'missing-form-attribute';
        } elseif ($form instanceof \DOMElement) {
            $source = 'ancestor';
        }

        return [
            'formOwnerRaw' => $formAttribute,
            'formOwnerTargetId' => $formTargetId === '' ? null : $formTargetId,
            'formOwnerId' => $form instanceof \DOMElement ? self::attributeOrNull($form, 'id') : null,
            'formOwnerSource' => $source,
            'formOwnerFound' => $form instanceof \DOMElement,
            'formOwnerAction' => $form instanceof \DOMElement ? self::attributeOrNull($form, 'action') : null,
            'formOwnerMethod' => $form instanceof \DOMElement ? self::formMethod($form, 'method', 'get') : null,
            'formOwnerEnctype' => $form instanceof \DOMElement ? self::formEnctype($form, 'enctype', 'application/x-www-form-urlencoded') : null,
            'formOwnerTarget' => $form instanceof \DOMElement ? self::attributeOrNull($form, 'target') : null,
        ];
    }

    private static function formOwnerElement(\DOMElement $control): ?\DOMElement
    {
        $formAttribute = self::attributeOrNull($control, 'form');
        if ($formAttribute !== null) {
            $formId = trim($formAttribute);
            if ($formId === '') {
                return null;
            }

            $candidate = self::htmlElementById($control, $formId);

            return $candidate instanceof \DOMElement && self::htmlElementName($candidate) === 'form'
                ? $candidate
                : null;
        }

        $parent = $control->parentNode;
        while ($parent instanceof \DOMElement) {
            if (self::htmlElementName($parent) === 'form') {
                return $parent;
            }
            $parent = $parent->parentNode;
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function formOwnedControlSummaries(\DOMElement $form): array
    {
        $document = $form->ownerDocument;
        if (!$document instanceof \DOMDocument) {
            return [];
        }

        $controls = [];
        foreach ($document->getElementsByTagName('*') as $control) {
            if (!$control instanceof \DOMElement || !self::isFormControlElement($control)) {
                continue;
            }
            if (!self::formOwnerElement($control)?->isSameNode($form)) {
                continue;
            }

            $name = self::htmlElementName($control);
            $summary = [
                'tag' => $name,
                'id' => self::attributeOrNull($control, 'id'),
                'controlName' => self::attributeOrNull($control, 'name'),
                'formOwnerSource' => self::attributeOrNull($control, 'form') === null ? 'ancestor' : 'form-attribute',
                'effectiveDisabled' => self::isEffectivelyDisabledFormControl($control),
            ];

            if ($name === 'input') {
                $summary['type'] = self::inputType($control);
                $summary['value'] = self::attributeOrNull($control, 'value');
                $summary['checked'] = $control->hasAttribute('checked');
            } elseif ($name === 'button') {
                $summary['type'] = self::buttonType($control);
                $summary['value'] = self::attributeOrNull($control, 'value');
                $summary['label'] = self::normalizedText($control);
            } elseif ($name === 'select') {
                $summary['selectedValues'] = array_values(array_map(
                    static fn (array $option): string => (string) $option['value'],
                    array_filter(
                        self::selectOptionSummaries($control),
                        static fn (array $option): bool => (bool) ($option['selected'] ?? false)
                    )
                ));
            } elseif ($name === 'textarea' || $name === 'output') {
                $summary['value'] = $control->textContent;
            }

            $controls[] = $summary;
        }

        return $controls;
    }

    /**
     * @param list<array<string, mixed>> $controls
     * @return list<string>
     */
    private static function formOwnedControlNames(array $controls): array
    {
        $names = [];
        foreach ($controls as $control) {
            $name = $control['controlName'] ?? null;
            if (is_string($name) && $name !== '' && !in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @return array<string, mixed>
     */
    private static function formSubmitterSummary(\DOMElement $submitter): array
    {
        return [
            'form' => self::attributeOrNull($submitter, 'form'),
            'formAction' => self::attributeOrNull($submitter, 'formaction'),
            'formMethod' => self::formMethod($submitter, 'formmethod', null),
            'formEnctype' => self::formEnctype($submitter, 'formenctype', null),
            'formTarget' => self::attributeOrNull($submitter, 'formtarget'),
            'formNoValidate' => $submitter->hasAttribute('formnovalidate'),
        ];
    }

    private static function isInputSubmitterType(string $type): bool
    {
        return in_array($type, ['image', 'submit'], true);
    }

    private static function formMethod(\DOMElement $element, string $attribute, ?string $missing): ?string
    {
        if (!$element->hasAttribute($attribute)) {
            return $missing;
        }

        $method = strtolower(trim($element->getAttribute($attribute)));

        return in_array($method, ['dialog', 'get', 'post'], true) ? $method : 'get';
    }

    private static function formEnctype(\DOMElement $element, string $attribute, ?string $missing): ?string
    {
        if (!$element->hasAttribute($attribute)) {
            return $missing;
        }

        $enctype = strtolower(trim($element->getAttribute($attribute)));

        return in_array($enctype, ['application/x-www-form-urlencoded', 'multipart/form-data', 'text/plain'], true)
            ? $enctype
            : 'application/x-www-form-urlencoded';
    }

    private static function formAutocomplete(\DOMElement $form): string
    {
        $autocomplete = strtolower(trim($form->getAttribute('autocomplete')));

        return $autocomplete === 'off' ? 'off' : 'on';
    }

    /**
     * @return array<string, mixed>
     */
    private static function formControlConstraintSummary(\DOMElement $control, string $name): array
    {
        $trackedAttributes = ['autocomplete', 'dirname', 'max', 'maxlength', 'min', 'minlength', 'pattern', 'size', 'step'];
        $hasTrackedAttribute = false;
        foreach ($trackedAttributes as $attribute) {
            if ($control->hasAttribute($attribute)) {
                $hasTrackedAttribute = true;
                break;
            }
        }
        if (!$hasTrackedAttribute && !$control->hasAttribute('multiple') && !$control->hasAttribute('readonly')) {
            return [];
        }

        $summary = ['constraintValidation' => 'form-control'];

        if (($name === 'input' || $name === 'textarea') && $control->hasAttribute('readonly')) {
            $summary['readonly'] = true;
        }
        if (($name === 'input' || $name === 'select') && $control->hasAttribute('multiple')) {
            $summary['multiple'] = true;
        }
        if ($control->hasAttribute('minlength')) {
            $minLength = self::nonNegativeIntegerToken($control->getAttribute('minlength'), 1000000);
            $summary['minLengthRaw'] = $control->getAttribute('minlength');
            $summary['minLength'] = $minLength;
            $summary['minLengthValid'] = $minLength !== null;
        }
        if ($control->hasAttribute('maxlength')) {
            $maxLength = self::nonNegativeIntegerToken($control->getAttribute('maxlength'), 1000000);
            $summary['maxLengthRaw'] = $control->getAttribute('maxlength');
            $summary['maxLength'] = $maxLength;
            $summary['maxLengthValid'] = $maxLength !== null;
        }
        if (array_key_exists('minLength', $summary) || array_key_exists('maxLength', $summary)) {
            $summary['lengthRangeValid'] = is_int($summary['minLength'] ?? null) && is_int($summary['maxLength'] ?? null)
                ? $summary['maxLength'] >= $summary['minLength']
                : null;
        }
        if ($control->hasAttribute('min')) {
            $min = self::finiteNumericToken($control->getAttribute('min'));
            $summary['constraintMinRaw'] = $control->getAttribute('min');
            $summary['constraintMin'] = $min;
            $summary['constraintMinValid'] = $min !== null;
        }
        if ($control->hasAttribute('max')) {
            $max = self::finiteNumericToken($control->getAttribute('max'));
            $summary['constraintMaxRaw'] = $control->getAttribute('max');
            $summary['constraintMax'] = $max;
            $summary['constraintMaxValid'] = $max !== null;
        }
        if (array_key_exists('constraintMin', $summary) || array_key_exists('constraintMax', $summary)) {
            $summary['constraintRangeValid'] = is_float($summary['constraintMin'] ?? null) && is_float($summary['constraintMax'] ?? null)
                ? $summary['constraintMax'] >= $summary['constraintMin']
                : null;
        }
        if ($control->hasAttribute('step')) {
            $step = self::stepConstraintToken($control->getAttribute('step'));
            $summary['constraintStepRaw'] = $control->getAttribute('step');
            $summary['constraintStep'] = $step;
            $summary['constraintStepValid'] = $step !== null;
        }
        if ($control->hasAttribute('pattern')) {
            $pattern = $control->getAttribute('pattern');
            $summary['patternRaw'] = $pattern;
            $summary['patternLength'] = strlen($pattern);
            $summary['patternReviewPolicy'] = 'pattern-source-no-regex-execution';
        }
        if ($control->hasAttribute('autocomplete')) {
            $autocomplete = self::formControlAutocompleteSummary($control->getAttribute('autocomplete'));
            $summary['autocompleteRaw'] = $control->getAttribute('autocomplete');
            $summary['autocompleteTokens'] = $autocomplete['tokens'];
            $summary['autocompleteNormalizedTokens'] = $autocomplete['normalizedTokens'];
            $summary['invalidAutocompleteTokens'] = $autocomplete['invalid'];
            $summary['autocompleteState'] = $autocomplete['state'];
            $summary['autocompleteValid'] = $autocomplete['valid'];
        }
        if (($name === 'input' || $name === 'textarea') && $control->hasAttribute('dirname')) {
            $dirname = self::formControlDirnameSummary($control->getAttribute('dirname'));
            $summary['dirnameRaw'] = $control->getAttribute('dirname');
            $summary['dirname'] = $dirname['name'];
            $summary['dirnameValid'] = $dirname['valid'];
        }
        if ($control->hasAttribute('size')) {
            $size = self::positiveIntegerToken($control->getAttribute('size'), 1000000);
            $summary['controlSizeRaw'] = $control->getAttribute('size');
            $summary['controlSize'] = $size;
            $summary['controlSizeValid'] = $size !== null;
        }

        return $summary;
    }

    private static function nonNegativeIntegerToken(string $value, int $max): ?int
    {
        $value = trim($value);
        if (preg_match('/^[0-9]+$/', $value) !== 1) {
            return null;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT);

        return is_int($integer) && $integer >= 0 ? min($integer, $max) : null;
    }

    private static function positiveIntegerToken(string $value, int $max): ?int
    {
        $integer = self::nonNegativeIntegerToken($value, $max);

        return $integer !== null && $integer > 0 ? $integer : null;
    }

    private static function finiteNumericToken(string $value): ?float
    {
        $value = trim($value);
        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return is_finite($number) ? $number : null;
    }

    private static function stepConstraintToken(string $value): float|string|null
    {
        $value = trim($value);
        if (strtolower($value) === 'any') {
            return 'any';
        }

        $number = self::finiteNumericToken($value);

        return $number !== null && $number > 0.0 ? $number : null;
    }

    /**
     * @return array{tokens:list<string>, normalizedTokens:list<string>, invalid:list<string>, state:?string, valid:bool}
     */
    private static function formControlAutocompleteSummary(string $value): array
    {
        $tokens = self::spaceSeparatedTokens($value);
        $normalized = [];
        $invalid = [];
        foreach ($tokens as $token) {
            if (!self::isHtmlReferenceToken($token)) {
                $invalid[] = $token;
                continue;
            }

            $lower = strtolower($token);
            if (!in_array($lower, $normalized, true)) {
                $normalized[] = $lower;
            }
        }

        $state = match ($normalized) {
            ['on'] => 'on',
            ['off'] => 'off',
            [] => null,
            default => 'detail',
        };

        return [
            'tokens' => $tokens,
            'normalizedTokens' => $normalized,
            'invalid' => $invalid,
            'state' => $state,
            'valid' => $tokens !== [] && $invalid === [],
        ];
    }

    /**
     * @return array{name:?string, valid:bool}
     */
    private static function formControlDirnameSummary(string $value): array
    {
        $name = trim($value);

        return [
            'name' => $name === '' ? null : $name,
            'valid' => $name !== '' && self::isHtmlReferenceToken($name),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function labelSummary(\DOMElement $label): array
    {
        $forRaw = self::attributeOrNull($label, 'for');
        $forId = $forRaw === null ? null : trim($forRaw);
        $nestedControls = self::descendantLabelableElements($label);
        $control = null;
        $source = 'missing';
        if ($forRaw !== null) {
            $source = 'for-attribute';
            $candidate = $forId === '' ? null : self::htmlElementById($label, $forId);
            $control = $candidate instanceof \DOMElement && self::isLabelableElement($candidate) ? $candidate : null;
        } elseif ($nestedControls !== []) {
            $source = 'descendant';
            $control = $nestedControls[0];
        }

        return [
            'formLabel' => 'label',
            'labelText' => self::normalizedText($label),
            'forRaw' => $forRaw,
            'forId' => $forId === '' ? null : $forId,
            'labeledControlSource' => $control instanceof \DOMElement ? $source : ($source === 'for-attribute' ? 'missing-for-target' : 'missing'),
            'labeledControl' => $control instanceof \DOMElement ? self::labelableElementSummary($control) : null,
            'nestedControlCount' => count($nestedControls),
            'nestedControls' => array_map(
                static fn (\DOMElement $control): array => self::labelableElementSummary($control),
                $nestedControls
            ),
        ];
    }

    /**
     * @return list<\DOMElement>
     */
    private static function descendantLabelableElements(\DOMElement $element): array
    {
        $controls = [];
        foreach ($element->getElementsByTagName('*') as $descendant) {
            if ($descendant instanceof \DOMElement && self::isLabelableElement($descendant)) {
                $controls[] = $descendant;
            }
        }

        return $controls;
    }

    private static function htmlElementById(\DOMElement $context, string $id): ?\DOMElement
    {
        $document = $context->ownerDocument;
        if (!$document instanceof \DOMDocument) {
            return null;
        }

        foreach ($document->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && $element->getAttribute('id') === $id) {
                return $element;
            }
        }

        return null;
    }

    private static function isLabelableElement(\DOMElement $element): bool
    {
        $name = self::htmlElementName($element);
        if ($name === 'input' && self::inputType($element) === 'hidden') {
            return false;
        }

        return in_array($name, ['button', 'input', 'meter', 'output', 'progress', 'select', 'textarea'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private static function labelableElementSummary(\DOMElement $control): array
    {
        $name = self::htmlElementName($control);
        $summary = [
            'tag' => $name,
            'id' => self::attributeOrNull($control, 'id'),
            'controlName' => self::attributeOrNull($control, 'name'),
        ];

        if (in_array($name, ['button', 'input', 'output', 'select', 'textarea'], true)) {
            $summary['effectiveDisabled'] = self::isEffectivelyDisabledFormControl($control);
        }
        if ($name === 'input') {
            $summary['type'] = self::inputType($control);
        } elseif ($name === 'button') {
            $summary['type'] = self::buttonType($control);
        } elseif ($name === 'progress') {
            $summary += self::progressMeasurementSummary($control, includeLabels: false);
        } elseif ($name === 'meter') {
            $summary += self::meterMeasurementSummary($control, includeLabels: false);
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private static function progressMeasurementSummary(\DOMElement $progress, bool $includeLabels): array
    {
        $max = self::positiveNumericAttribute($progress, 'max', 1.0);
        $value = self::numericAttribute($progress, 'value', null);
        $value = $value === null ? null : self::boundedNumber($value, 0.0, $max);
        $summary = [
            'measurement' => 'progress',
            'value' => $value,
            'max' => $max,
            'position' => $value === null ? null : $value / $max,
            'indeterminate' => $value === null,
        ];

        if ($includeLabels) {
            $summary['labels'] = self::formControlLabels($progress);
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private static function meterMeasurementSummary(\DOMElement $meter, bool $includeLabels): array
    {
        $min = self::numericAttribute($meter, 'min', 0.0) ?? 0.0;
        $max = self::numericAttribute($meter, 'max', 1.0) ?? 1.0;
        if ($max < $min) {
            $max = $min;
        }

        $summary = [
            'measurement' => 'meter',
            'min' => $min,
            'max' => $max,
            'value' => self::boundedNumber(self::numericAttribute($meter, 'value', $min) ?? $min, $min, $max),
        ];

        if ($includeLabels) {
            $summary['labels'] = self::formControlLabels($meter);
        }

        foreach (['low', 'high', 'optimum'] as $threshold) {
            $thresholdValue = self::numericAttribute($meter, $threshold, null);
            if ($thresholdValue !== null) {
                $summary[$threshold] = $thresholdValue;
            }
        }

        return $summary;
    }

    /**
     * @return list<\DOMElement>
     */
    private static function detailsSummaryElements(\DOMElement $details): array
    {
        $summaries = [];
        foreach ($details->childNodes as $child) {
            if ($child instanceof \DOMElement && strtolower(self::htmlElementName($child)) === 'summary') {
                $summaries[] = $child;
            }
        }

        return $summaries;
    }

    /**
     * @return array<string, mixed>
     */
    private static function detailsDisclosureSummary(\DOMElement $details): array
    {
        $summaryElements = self::detailsSummaryElements($details);
        $detailsNameRaw = self::attributeOrNull($details, 'name');
        $detailsName = self::normalizedNonEmptyAttribute($detailsNameRaw);
        $group = self::detailsGroupElements($details, $detailsName);
        $openGroup = array_values(array_filter(
            $group,
            static fn (\DOMElement $groupDetails): bool => $groupDetails->hasAttribute('open')
        ));

        return [
            'disclosure' => 'details',
            'open' => $details->hasAttribute('open'),
            'detailsState' => $details->hasAttribute('open') ? 'open' : 'closed',
            'detailsNameRaw' => $detailsNameRaw,
            'detailsName' => $detailsName,
            'detailsGroupIndex' => self::detailsGroupIndex($details, $group),
            'detailsGroupSize' => count($group),
            'detailsGroupOpenCount' => count($openGroup),
            'detailsGroupOpenConflict' => count($openGroup) > 1,
            'summaryText' => $summaryElements === [] ? null : self::normalizedText($summaryElements[0]),
            'primarySummaryId' => $summaryElements === [] ? null : self::attributeOrNull($summaryElements[0], 'id'),
            'summaryElementCount' => count($summaryElements),
            'summaryElements' => self::detailsSummaryElementRecords($summaryElements),
        ];
    }

    /**
     * @param list<\DOMElement> $summaryElements
     * @return list<array<string, mixed>>
     */
    private static function detailsSummaryElementRecords(array $summaryElements): array
    {
        $records = [];
        foreach ($summaryElements as $index => $summary) {
            $records[] = [
                'index' => $index,
                'id' => self::attributeOrNull($summary, 'id'),
                'text' => self::normalizedText($summary),
                'primary' => $index === 0,
                'childElementCount' => count(self::childElements($summary)),
            ];
        }

        return $records;
    }

    /**
     * @return list<\DOMElement>
     */
    private static function detailsGroupElements(\DOMElement $details, ?string $detailsName): array
    {
        if ($detailsName === null) {
            return [];
        }

        $document = $details->ownerDocument;
        if (!$document instanceof \DOMDocument) {
            return [];
        }

        $group = [];
        foreach ($document->getElementsByTagName('details') as $candidate) {
            if ($candidate instanceof \DOMElement && self::normalizedNonEmptyAttribute(self::attributeOrNull($candidate, 'name')) === $detailsName) {
                $group[] = $candidate;
            }
        }

        return $group;
    }

    /**
     * @param list<\DOMElement> $group
     */
    private static function detailsGroupIndex(\DOMElement $details, array $group): ?int
    {
        foreach ($group as $index => $candidate) {
            if ($candidate->isSameNode($details)) {
                return $index + 1;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function summaryDisclosureSummary(\DOMElement $summary): array
    {
        $details = self::summaryDetailsParent($summary);
        $summaryIndex = null;
        if ($details instanceof \DOMElement) {
            foreach (self::detailsSummaryElements($details) as $index => $candidate) {
                if ($candidate->isSameNode($summary)) {
                    $summaryIndex = $index;
                    break;
                }
            }
        }

        return [
            'disclosure' => 'summary',
            'label' => self::normalizedText($summary),
            'summaryForDetailsId' => $details instanceof \DOMElement ? self::attributeOrNull($details, 'id') : null,
            'summaryForDetailsName' => $details instanceof \DOMElement ? self::normalizedNonEmptyAttribute(self::attributeOrNull($details, 'name')) : null,
            'summaryIndex' => $summaryIndex,
            'summaryPrimary' => $summaryIndex === null ? null : $summaryIndex === 0,
        ];
    }

    private static function summaryDetailsParent(\DOMElement $summary): ?\DOMElement
    {
        $parent = $summary->parentNode;

        return $parent instanceof \DOMElement && self::htmlElementName($parent) === 'details'
            ? $parent
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function dialogSummary(\DOMElement $dialog): array
    {
        $heading = self::firstScopedHeadingElement($dialog);
        $headingName = $heading instanceof \DOMElement ? self::htmlElementName($heading) : null;
        $methodForms = self::dialogMethodFormSummaries($dialog);

        return [
            'dialog' => 'dialog',
            'dialogOpen' => $dialog->hasAttribute('open'),
            'dialogState' => $dialog->hasAttribute('open') ? 'open' : 'closed',
            'dialogHeadingText' => $heading instanceof \DOMElement ? self::normalizedText($heading) : null,
            'dialogHeadingTag' => $headingName,
            'dialogHeadingLevel' => $headingName === null ? null : self::htmlHeadingLevel($headingName),
            'dialogMethodFormCount' => count($methodForms),
            'dialogMethodForms' => $methodForms,
            'dialogCloseValues' => self::dialogCloseValues($methodForms),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function dialogMethodFormSummaries(\DOMElement $dialog): array
    {
        $forms = [];
        foreach (self::descendantHtmlElements($dialog, 'form') as $form) {
            if (self::formMethod($form, 'method', 'get') !== 'dialog') {
                continue;
            }

            $forms[] = [
                'id' => self::attributeOrNull($form, 'id'),
                'methodRaw' => self::attributeOrNull($form, 'method'),
                'method' => 'dialog',
                'action' => self::attributeOrNull($form, 'action'),
                'submitters' => self::dialogMethodSubmitterSummaries($form),
            ];
        }

        return $forms;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function dialogMethodSubmitterSummaries(\DOMElement $form): array
    {
        $submitters = [];
        foreach ($form->getElementsByTagName('*') as $control) {
            if (!$control instanceof \DOMElement) {
                continue;
            }

            $name = self::htmlElementName($control);
            if ($name === 'button') {
                $type = self::buttonType($control);
                if ($type !== 'submit') {
                    continue;
                }
                $label = self::normalizedText($control);
                $value = $control->hasAttribute('value') ? $control->getAttribute('value') : '';
            } elseif ($name === 'input') {
                $type = self::inputType($control);
                if (!self::isInputSubmitterType($type)) {
                    continue;
                }
                $value = $control->getAttribute('value');
                $label = $value;
            } else {
                continue;
            }

            $formMethod = self::formMethod($control, 'formmethod', null);
            $effectiveMethod = $formMethod ?? 'dialog';
            $submitters[] = [
                'tag' => $name,
                'type' => $type,
                'name' => self::attributeOrNull($control, 'name'),
                'value' => $value,
                'label' => $label,
                'formMethod' => $formMethod,
                'effectiveFormMethod' => $effectiveMethod,
                'disabled' => $control->hasAttribute('disabled'),
                'effectiveDisabled' => self::isEffectivelyDisabledFormControl($control),
                'dialogCloses' => $effectiveMethod === 'dialog',
            ];
        }

        return $submitters;
    }

    /**
     * @param list<array<string, mixed>> $forms
     * @return list<string>
     */
    private static function dialogCloseValues(array $forms): array
    {
        $values = [];
        foreach ($forms as $form) {
            foreach (($form['submitters'] ?? []) as $submitter) {
                if (is_array($submitter) && ($submitter['dialogCloses'] ?? false) === true) {
                    $values[] = (string) ($submitter['value'] ?? '');
                }
            }
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    private static function revisionSummary(\DOMElement $element, string $name): array
    {
        $summary = [
            'revision' => $name === 'ins' ? 'insertion' : 'deletion',
            'revisionTag' => $name,
            'revisionCite' => self::attributeOrNull($element, 'cite'),
            'revisionDatetimeRaw' => self::attributeOrNull($element, 'datetime'),
            'revisionDatetime' => null,
            'revisionDatetimeKind' => null,
            'revisionDatetimeValid' => false,
        ];

        if ($summary['revisionDatetimeRaw'] === null) {
            return $summary;
        }

        $datetime = self::revisionDatetimeSummary($summary['revisionDatetimeRaw']);
        if ($datetime === null) {
            $summary['revisionDatetimeKind'] = 'invalid';

            return $summary;
        }

        $summary['revisionDatetime'] = $datetime['value'];
        $summary['revisionDatetimeKind'] = $datetime['kind'];
        $summary['revisionDatetimeValid'] = true;

        return $summary;
    }

    /**
     * @return array{kind:string, value:string}|null
     */
    private static function revisionDatetimeSummary(string $value): ?array
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 128 || preg_match('/[<>{}`]/', $value) === 1) {
            return null;
        }

        $datePattern = '([0-9]{4})-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])';
        $timePattern = '((?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9](?:\.[0-9]{1,3})?)?)';
        $timezonePattern = '([Zz]|[+-](?:[01][0-9]|2[0-3]):?[0-5][0-9])';

        if (preg_match('/^' . $datePattern . '[T ]' . $timePattern . $timezonePattern . '$/', $value, $matches) === 1) {
            if (!self::isValidDateParts((string) $matches[1], (string) $matches[2], (string) $matches[3])) {
                return null;
            }

            return [
                'kind' => 'global-datetime',
                'value' => (string) $matches[1] . '-' . (string) $matches[2] . '-' . (string) $matches[3]
                    . 'T' . (string) $matches[4]
                    . self::normalizeTimezone((string) $matches[5]),
            ];
        }

        if (preg_match('/^' . $datePattern . '[T ]' . $timePattern . '$/', $value, $matches) === 1) {
            if (!self::isValidDateParts((string) $matches[1], (string) $matches[2], (string) $matches[3])) {
                return null;
            }

            return [
                'kind' => 'local-datetime',
                'value' => (string) $matches[1] . '-' . (string) $matches[2] . '-' . (string) $matches[3]
                    . 'T' . (string) $matches[4],
            ];
        }

        if (preg_match('/^' . $datePattern . '$/', $value, $matches) === 1) {
            if (!self::isValidDateParts((string) $matches[1], (string) $matches[2], (string) $matches[3])) {
                return null;
            }

            return [
                'kind' => 'date',
                'value' => (string) $matches[1] . '-' . (string) $matches[2] . '-' . (string) $matches[3],
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function hyperlinkSummary(\DOMElement $element, string $name): array
    {
        $relRaw = self::attributeOrNull($element, 'rel');
        $pingRaw = self::attributeOrNull($element, 'ping');
        $summary = [
            'hyperlink' => $name,
            'href' => self::attributeOrNull($element, 'href'),
            'target' => self::attributeOrNull($element, 'target'),
            'relRaw' => $relRaw,
            'relTokens' => $relRaw === null ? [] : self::spaceSeparatedTokens($relRaw),
            'download' => self::attributeOrNull($element, 'download'),
            'hreflang' => self::attributeOrNull($element, 'hreflang'),
            'mimeType' => self::attributeOrNull($element, 'type'),
            'pingRaw' => $pingRaw,
            'pingUrls' => $pingRaw === null ? [] : self::spaceSeparatedTokens($pingRaw),
            'referrerpolicy' => self::attributeOrNull($element, 'referrerpolicy'),
            'label' => $name === 'area'
                ? self::attributeOrNull($element, 'alt')
                : self::normalizedText($element),
        ];

        if ($name === 'area') {
            $summary['shape'] = self::attributeOrNull($element, 'shape');
            $summary['coords'] = self::attributeOrNull($element, 'coords');
        }

        return $summary;
    }

    private static function isValidDateParts(string $year, string $month, string $day): bool
    {
        return checkdate((int) $month, (int) $day, (int) $year);
    }

    private static function normalizeTimezone(string $timezone): string
    {
        if (strtoupper($timezone) === 'Z') {
            return 'Z';
        }

        if (preg_match('/^([+-])([0-9]{2}):?([0-9]{2})$/', $timezone, $matches) === 1) {
            return (string) $matches[1] . (string) $matches[2] . ':' . (string) $matches[3];
        }

        return $timezone;
    }

    /**
     * @return array<string, mixed>
     */
    private static function embeddedResourceSummary(\DOMElement $element, string $name): array
    {
        return match ($name) {
            'picture' => self::pictureSummary($element),
            'img' => self::imageSummary($element),
            'audio', 'video' => self::mediaElementSummary($element, $name),
            'source' => ['embeddedResource' => 'source'] + self::sourceElementSummary($element),
            'track' => ['embeddedResource' => 'track'] + self::trackElementSummary($element),
            'iframe' => self::iframeSummary($element),
            'embed' => self::embedSummary($element),
            'object' => self::objectSummary($element),
            'param' => self::paramElementSummary($element),
            'canvas' => self::canvasSummary($element),
            default => [],
        };
    }

    private static function isDocBookMediaElementName(string $name): bool
    {
        return in_array($name, ['mediaobject', 'inlinemediaobject', 'imageobject', 'imagedata', 'textobject', 'alt'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private static function docBookMediaSummary(\DOMElement $element, string $name): array
    {
        return match ($name) {
            'mediaobject', 'inlinemediaobject' => self::docBookMediaObjectSummary($element, $name),
            'imageobject' => self::docBookImageObjectSummary($element),
            'imagedata' => ['docBookMediaPart' => 'imagedata'] + self::docBookImageDataSummary($element),
            'textobject' => self::docBookTextObjectSummary($element),
            'alt' => self::docBookAltSummary($element),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function docBookMediaObjectSummary(\DOMElement $media, string $name): array
    {
        $altTexts = self::docBookAltTexts($media);
        $textObjects = self::docBookTextObjectSummaries($media);
        $imageData = self::docBookImageDataSummaries($media);
        $linkendAssociations = self::docBookLinkendAssociations($media);
        $hasAccessibleText = $altTexts !== [] || array_filter(
            array_map(static fn (array $textObject): string => (string) ($textObject['text'] ?? ''), $textObjects),
            static fn (string $text): bool => $text !== ''
        ) !== [];
        $issues = [];

        if (!$hasAccessibleText && $imageData !== []) {
            $issues[] = [
                'code' => 'missing-docbook-media-alt',
                'media' => $name,
                'imageDataCount' => count($imageData),
            ];
        }

        foreach ($imageData as $index => $image) {
            if (($image['target'] ?? null) === null) {
                $issues[] = [
                    'code' => 'missing-docbook-imagedata-target',
                    'imageDataIndex' => $index,
                ];
            }
        }

        foreach ($linkendAssociations as $association) {
            foreach (($association['invalidIds'] ?? []) as $invalidId) {
                $issues[] = [
                    'code' => 'invalid-docbook-linkend',
                    'element' => $association['element'],
                    'linkendId' => $invalidId,
                ];
            }
            foreach (($association['missingIds'] ?? []) as $missingId) {
                $issues[] = [
                    'code' => 'missing-docbook-linkend-target',
                    'element' => $association['element'],
                    'linkendId' => $missingId,
                ];
            }
        }

        return [
            'docBookMediaObject' => $name,
            'docBookMediaInline' => $name === 'inlinemediaobject',
            'docBookMediaId' => self::docBookElementId($media),
            'docBookAltTexts' => $altTexts,
            'docBookTextObjects' => $textObjects,
            'docBookTextObjectTexts' => array_values(array_filter(
                array_map(static fn (array $textObject): string => (string) ($textObject['text'] ?? ''), $textObjects),
                static fn (string $text): bool => $text !== ''
            )),
            'docBookImageData' => $imageData,
            'docBookImageTargetBasenames' => array_values(array_filter(
                array_map(static fn (array $image): ?string => $image['targetBasename'] ?? null, $imageData),
                static fn (?string $basename): bool => $basename !== null && $basename !== ''
            )),
            'docBookImageContentTypes' => array_values(array_filter(
                array_map(static fn (array $image): ?string => $image['contentType'] ?? null, $imageData),
                static fn (?string $contentType): bool => $contentType !== null && $contentType !== ''
            )),
            'docBookLinkendAssociations' => $linkendAssociations,
            'docBookMissingAlt' => !$hasAccessibleText && $imageData !== [],
            'docBookMediaIssues' => $issues,
            'docBookMediaIssueCount' => count($issues),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function docBookImageObjectSummary(\DOMElement $imageObject): array
    {
        $imageData = self::docBookImageDataSummaries($imageObject);

        return [
            'docBookMediaPart' => 'imageobject',
            'docBookImageData' => $imageData,
            'docBookImageDataCount' => count($imageData),
            'docBookImageTargetBasenames' => array_values(array_filter(
                array_map(static fn (array $image): ?string => $image['targetBasename'] ?? null, $imageData),
                static fn (?string $basename): bool => $basename !== null && $basename !== ''
            )),
            'docBookImageContentTypes' => array_values(array_filter(
                array_map(static fn (array $image): ?string => $image['contentType'] ?? null, $imageData),
                static fn (?string $contentType): bool => $contentType !== null && $contentType !== ''
            )),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function docBookTextObjectSummary(\DOMElement $textObject): array
    {
        $text = self::normalizedText($textObject);

        return [
            'docBookMediaPart' => 'textobject',
            'docBookTextObjectText' => $text,
            'docBookTextObjectTextLength' => strlen($text),
            'docBookTextObjectId' => self::docBookElementId($textObject),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function docBookAltSummary(\DOMElement $alt): array
    {
        $text = self::normalizedText($alt);

        return [
            'docBookMediaPart' => 'alt',
            'docBookAltText' => $text,
            'docBookAltTextLength' => strlen($text),
        ];
    }

    /**
     * @return list<string>
     */
    private static function docBookAltTexts(\DOMElement $media): array
    {
        $texts = [];
        foreach (self::descendantHtmlElements($media, 'alt') as $alt) {
            $text = self::normalizedText($alt);
            if ($text !== '') {
                $texts[] = $text;
            }
        }

        return $texts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function docBookTextObjectSummaries(\DOMElement $media): array
    {
        $summaries = [];
        foreach (self::descendantHtmlElements($media, 'textobject') as $index => $textObject) {
            $text = self::normalizedText($textObject);
            $summaries[] = [
                'index' => $index,
                'id' => self::docBookElementId($textObject),
                'text' => $text,
                'textLength' => strlen($text),
            ];
        }

        return $summaries;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function docBookImageDataSummaries(\DOMElement $media): array
    {
        $summaries = [];
        foreach (self::descendantHtmlElements($media, 'imagedata') as $index => $imageData) {
            $summaries[] = ['index' => $index] + self::docBookImageDataSummary($imageData);
        }

        return $summaries;
    }

    /**
     * @return array<string, mixed>
     */
    private static function docBookImageDataSummary(\DOMElement $imageData): array
    {
        $fileref = self::attributeOrNull($imageData, 'fileref');
        $entityref = self::attributeOrNull($imageData, 'entityref');
        $format = self::attributeOrNull($imageData, 'format');
        $target = self::normalizedNonEmptyAttribute($fileref) ?? self::normalizedNonEmptyAttribute($entityref);
        $path = $target === null ? null : self::docBookTargetPath($target);
        $basename = $path === null ? null : self::docBookTargetBasename($path);
        $extension = $basename === null ? null : self::docBookTargetExtension($basename);
        $contentType = self::docBookImageContentType($format, $extension);

        return [
            'target' => $target,
            'fileref' => $fileref,
            'entityref' => $entityref,
            'format' => $format,
            'targetPath' => $path,
            'targetBasename' => $basename,
            'targetExtension' => $extension,
            'contentType' => $contentType['contentType'],
            'contentTypeSource' => $contentType['source'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function docBookLinkendAssociations(\DOMElement $context): array
    {
        $knownIds = self::docBookDocumentIds($context);
        $associations = [];
        $elements = [];
        if ($context->hasAttribute('linkend')) {
            $elements[] = $context;
        }
        foreach ($context->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && $element->hasAttribute('linkend')) {
                $elements[] = $element;
            }
        }

        foreach ($elements as $element) {
            $raw = $element->getAttribute('linkend');
            $ids = self::spaceSeparatedTokens($raw);
            $invalid = [];
            $resolved = [];
            $missing = [];
            foreach ($ids as $id) {
                if (!self::isHtmlReferenceToken($id)) {
                    $invalid[] = $id;
                    continue;
                }
                if (isset($knownIds[$id])) {
                    $resolved[] = $id;
                } else {
                    $missing[] = $id;
                }
            }

            $associations[] = [
                'element' => self::htmlElementName($element),
                'sourceId' => self::docBookElementId($element),
                'linkendRaw' => $raw,
                'linkendIds' => $ids,
                'resolvedIds' => $resolved,
                'missingIds' => $missing,
                'invalidIds' => $invalid,
                'valid' => $ids !== [] && $invalid === [] && $missing === [],
            ];
        }

        return $associations;
    }

    /**
     * @return array<string, true>
     */
    private static function docBookDocumentIds(\DOMElement $context): array
    {
        $document = $context->ownerDocument;
        if (!$document instanceof \DOMDocument) {
            return [];
        }

        $ids = [];
        foreach ($document->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }
            foreach (['id', 'xml:id'] as $attribute) {
                if (!$element->hasAttribute($attribute)) {
                    continue;
                }
                $id = trim($element->getAttribute($attribute));
                if ($id !== '' && self::isHtmlReferenceToken($id)) {
                    $ids[$id] = true;
                }
            }
        }

        return $ids;
    }

    private static function docBookElementId(\DOMElement $element): ?string
    {
        foreach (['id', 'xml:id'] as $attribute) {
            $id = self::normalizedNonEmptyAttribute(self::attributeOrNull($element, $attribute));
            if ($id !== null) {
                return $id;
            }
        }

        return null;
    }

    private static function docBookTargetPath(string $target): string
    {
        $cut = strlen($target);
        foreach (['#', '?'] as $delimiter) {
            $position = strpos($target, $delimiter);
            if ($position !== false) {
                $cut = min($cut, $position);
            }
        }

        return substr($target, 0, $cut);
    }

    private static function docBookTargetBasename(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '') {
            return null;
        }

        $basename = basename($path);

        return $basename === '' || $basename === '.' ? null : $basename;
    }

    private static function docBookTargetExtension(string $basename): ?string
    {
        $extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));

        return $extension === '' ? null : $extension;
    }

    /**
     * @return array{contentType:?string, source:?string}
     */
    private static function docBookImageContentType(?string $format, ?string $extension): array
    {
        $format = self::normalizedNonEmptyAttribute($format);
        if ($format !== null) {
            $normalized = strtolower($format);
            if (str_contains($normalized, ';')) {
                $normalized = trim(strstr($normalized, ';', true) ?: $normalized);
            }
            if (str_contains($normalized, '/')) {
                return ['contentType' => $normalized, 'source' => 'format'];
            }

            $fromFormat = self::docBookImageExtensionContentType($normalized);
            if ($fromFormat !== null) {
                return ['contentType' => $fromFormat, 'source' => 'format'];
            }
        }

        $fromExtension = $extension === null ? null : self::docBookImageExtensionContentType($extension);

        return ['contentType' => $fromExtension, 'source' => $fromExtension === null ? null : 'extension'];
    }

    private static function docBookImageExtensionContentType(string $extension): ?string
    {
        return match (strtolower($extension)) {
            'apng' => 'image/apng',
            'avif' => 'image/avif',
            'bmp' => 'image/bmp',
            'eps', 'epsf', 'ps' => 'application/postscript',
            'gif' => 'image/gif',
            'heic' => 'image/heic',
            'ico' => 'image/x-icon',
            'jpeg', 'jpg', 'jpe' => 'image/jpeg',
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'svg', 'svgz' => 'image/svg+xml',
            'tif', 'tiff' => 'image/tiff',
            'webp' => 'image/webp',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function canvasSummary(\DOMElement $canvas): array
    {
        $fallbackText = self::normalizedText($canvas);
        $fallbackElementNames = [];
        foreach ($canvas->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $fallbackElementNames[] = self::htmlElementName($child);
            }
        }

        $summary = [
            'embeddedResource' => 'canvas',
            'width' => self::attributeOrNull($canvas, 'width'),
            'height' => self::attributeOrNull($canvas, 'height'),
            'bitmapWidth' => self::nonNegativeIntegerAttribute($canvas, 'width', 300, 100000),
            'bitmapHeight' => self::nonNegativeIntegerAttribute($canvas, 'height', 150, 100000),
            'fallbackElementNames' => $fallbackElementNames,
            'fallbackElementCount' => count($fallbackElementNames),
            'fallbackTextLength' => strlen($fallbackText),
            'fallbackTextSha256' => hash('sha256', $fallbackText),
            'canvasReviewPolicy' => 'canvas-fallback-source',
        ];

        if ($fallbackText !== '') {
            $summary['fallbackText'] = $fallbackText;
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private static function pictureSummary(\DOMElement $picture): array
    {
        $image = self::firstDescendantHtmlElement($picture, 'img');

        return [
            'embeddedResource' => 'picture',
            'pictureSources' => array_map(
                static fn (\DOMElement $source): array => self::sourceElementSummary($source),
                self::descendantHtmlElements($picture, 'source'),
            ),
            'image' => $image instanceof \DOMElement ? self::imageSummary($image) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function imageSummary(\DOMElement $image): array
    {
        $srcset = self::attributeOrNull($image, 'srcset');
        $summary = [
            'embeddedResource' => 'image',
            'src' => self::attributeOrNull($image, 'src'),
            'alt' => self::attributeOrNull($image, 'alt'),
            'srcset' => $srcset,
            'srcsetCandidates' => self::srcsetCandidateSummaries($srcset),
            'sizes' => self::attributeOrNull($image, 'sizes'),
            'loading' => self::attributeOrNull($image, 'loading'),
            'decoding' => self::attributeOrNull($image, 'decoding'),
        ];

        if ($image->hasAttribute('usemap')) {
            $useMap = self::useMapAttributeSummary($image->getAttribute('usemap'));
            $summary['useMapRaw'] = $useMap['raw'];
            $summary['useMapName'] = $useMap['name'];
            $summary['useMapValid'] = $useMap['valid'];
        }

        return $summary;
    }

    /**
     * @return array{imageMap:string, mapNameRaw:?string, mapName:?string, mapNameValid:bool, areaCount:int, areaHrefs:list<string>, areaLabels:list<string>, areas:list<array<string, mixed>>}
     */
    private static function imageMapSummary(\DOMElement $map): array
    {
        $name = self::attributeOrNull($map, 'name');
        $areas = array_map(
            static fn (\DOMElement $area): array => self::hyperlinkSummary($area, 'area'),
            self::descendantHtmlElements($map, 'area'),
        );

        return [
            'imageMap' => 'map',
            'mapNameRaw' => $name,
            'mapName' => $name === null ? null : trim($name),
            'mapNameValid' => $name !== null && self::isHtmlReferenceToken(trim($name)),
            'areaCount' => count($areas),
            'areaHrefs' => array_values(array_filter(
                array_map(static fn (array $area): ?string => $area['href'] ?? null, $areas),
                static fn (?string $href): bool => $href !== null && $href !== ''
            )),
            'areaLabels' => array_values(array_filter(
                array_map(static fn (array $area): ?string => $area['label'] ?? null, $areas),
                static fn (?string $label): bool => $label !== null && $label !== ''
            )),
            'areas' => $areas,
        ];
    }

    /**
     * @return array{raw:string, name:?string, valid:bool}
     */
    private static function useMapAttributeSummary(string $value): array
    {
        $raw = trim($value);
        $name = str_starts_with($raw, '#') ? substr($raw, 1) : $raw;

        return [
            'raw' => $value,
            'name' => $name === '' ? null : $name,
            'valid' => str_starts_with($raw, '#') && self::isHtmlReferenceToken($name),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function mediaElementSummary(\DOMElement $element, string $name): array
    {
        $summary = [
            'embeddedResource' => $name,
            'media' => $name,
            'src' => self::attributeOrNull($element, 'src'),
            'controls' => $element->hasAttribute('controls'),
            'autoplay' => $element->hasAttribute('autoplay'),
            'loop' => $element->hasAttribute('loop'),
            'muted' => $element->hasAttribute('muted'),
            'preload' => self::mediaPreload($element),
            'sources' => self::mediaSourceSummaries($element),
            'mediaSources' => array_map(
                static fn (\DOMElement $source): array => self::sourceElementSummary($source),
                self::mediaResourceElements($element, 'source'),
            ),
            'tracks' => self::mediaTrackSummaries($element),
        ];
        $summary += self::mediaTextTrackReviewSummary($element);

        if ($name === 'video') {
            $summary['poster'] = self::attributeOrNull($element, 'poster');
        }

        $fallbackText = self::normalizedTextWithoutMediaResourceChildren($element);
        if ($fallbackText !== '') {
            $summary['fallbackText'] = $fallbackText;
        }

        return $summary;
    }

    /**
     * @return array{src:?string, srcset:?string, srcsetCandidates:list<array<string, mixed>>, type:?string, media:?string, sizes:?string}
     */
    private static function sourceElementSummary(\DOMElement $source): array
    {
        $srcset = self::attributeOrNull($source, 'srcset');

        return [
            'src' => self::attributeOrNull($source, 'src'),
            'srcset' => $srcset,
            'srcsetCandidates' => self::srcsetCandidateSummaries($srcset),
            'type' => self::attributeOrNull($source, 'type'),
            'media' => self::attributeOrNull($source, 'media'),
            'sizes' => self::attributeOrNull($source, 'sizes'),
        ];
    }

    /**
     * @return array{src:?string, kind:string, srclang:?string, label:?string, default:bool}
     */
    private static function trackElementSummary(\DOMElement $track): array
    {
        return [
            'src' => self::attributeOrNull($track, 'src'),
            'kind' => self::trackKind($track),
            'srclang' => self::attributeOrNull($track, 'srclang'),
            'label' => self::attributeOrNull($track, 'label'),
            'default' => $track->hasAttribute('default'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function mediaTextTrackReviewSummary(\DOMElement $media): array
    {
        $tracks = [];
        $kindCounts = [];
        $languages = [];
        $subtitleLanguages = [];
        $defaultLabels = [];
        $issues = [];
        $defaultCount = 0;
        $invalidKindCount = 0;
        $invalidLanguageCount = 0;
        $missingLanguageCount = 0;

        foreach (self::mediaResourceElements($media, 'track') as $index => $track) {
            $summary = self::textTrackReviewSummary($track, $index);
            $tracks[] = $summary;

            $kind = (string) $summary['kind'];
            $kindCounts[$kind] = ($kindCounts[$kind] ?? 0) + 1;

            if (($summary['default'] ?? false) === true) {
                ++$defaultCount;
                $label = $summary['label'] ?? null;
                if ($label !== null && $label !== '') {
                    $defaultLabels[] = (string) $label;
                }
            }

            $language = $summary['srclang'] ?? null;
            if ($language !== null) {
                if (!in_array($language, $languages, true)) {
                    $languages[] = (string) $language;
                }
                if (($summary['languageRequired'] ?? false) === true && !in_array($language, $subtitleLanguages, true)) {
                    $subtitleLanguages[] = (string) $language;
                }
            }

            if (($summary['kindValid'] ?? true) !== true) {
                ++$invalidKindCount;
                $issues[] = [
                    'code' => 'invalid-text-track-kind',
                    'trackIndex' => $index,
                    'kindRaw' => $summary['kindRaw'],
                    'normalizedKind' => $kind,
                ];
            }

            if (($summary['srclangRaw'] ?? null) !== null && ($summary['srclangValid'] ?? false) !== true) {
                ++$invalidLanguageCount;
                $issues[] = [
                    'code' => 'invalid-text-track-language',
                    'trackIndex' => $index,
                    'srclangRaw' => $summary['srclangRaw'],
                ];
            }

            if (($summary['languageMissing'] ?? false) === true) {
                ++$missingLanguageCount;
                $issues[] = [
                    'code' => 'missing-text-track-language',
                    'trackIndex' => $index,
                    'kind' => $kind,
                    'label' => $summary['label'],
                    'src' => $summary['src'],
                ];
            }
        }

        if ($tracks === []) {
            return [];
        }

        ksort($kindCounts);
        if ($defaultCount > 1) {
            array_unshift($issues, [
                'code' => 'multiple-default-tracks',
                'count' => $defaultCount,
            ]);
        }

        return [
            'textTrackCount' => count($tracks),
            'textTracks' => $tracks,
            'textTrackKinds' => $kindCounts,
            'textTrackLanguages' => $languages,
            'subtitleTextTrackLanguages' => $subtitleLanguages,
            'defaultTextTrackCount' => $defaultCount,
            'defaultTextTrackLabels' => $defaultLabels,
            'defaultTextTrackConflict' => $defaultCount > 1,
            'invalidTextTrackKindCount' => $invalidKindCount,
            'invalidTextTrackLanguageCount' => $invalidLanguageCount,
            'missingSubtitleLanguageCount' => $missingLanguageCount,
            'textTrackIssues' => $issues,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function textTrackReviewSummary(\DOMElement $track, int $index): array
    {
        $kindRaw = self::attributeOrNull($track, 'kind');
        $kind = self::trackKind($track);
        $srclangRaw = self::attributeOrNull($track, 'srclang');
        $srclang = $srclangRaw === null ? null : self::normalizeHtmlTrackLanguageTag($srclangRaw);
        $languageRequired = in_array($kind, ['captions', 'subtitles'], true);

        return [
            'index' => $index,
            'src' => self::attributeOrNull($track, 'src'),
            'kindRaw' => $kindRaw,
            'kind' => $kind,
            'kindValid' => $kindRaw === null || self::isHtmlTrackKind(strtolower(trim($kindRaw))),
            'srclangRaw' => $srclangRaw,
            'srclang' => $srclang,
            'srclangValid' => $srclangRaw !== null && $srclang !== null,
            'label' => self::attributeOrNull($track, 'label'),
            'default' => $track->hasAttribute('default'),
            'languageRequired' => $languageRequired,
            'languageMissing' => $languageRequired && $srclang === null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function iframeSummary(\DOMElement $iframe): array
    {
        $srcdoc = self::attributeOrNull($iframe, 'srcdoc');
        $summary = [
            'embeddedResource' => 'iframe',
            'src' => self::attributeOrNull($iframe, 'src'),
            'srcdoc' => $srcdoc,
            'nameAttribute' => self::attributeOrNull($iframe, 'name'),
            'width' => self::attributeOrNull($iframe, 'width'),
            'height' => self::attributeOrNull($iframe, 'height'),
            'loading' => self::attributeOrNull($iframe, 'loading'),
            'referrerpolicy' => self::attributeOrNull($iframe, 'referrerpolicy'),
            'allow' => self::attributeOrNull($iframe, 'allow'),
            'sandboxTokens' => $iframe->hasAttribute('sandbox') ? self::spaceSeparatedTokens($iframe->getAttribute('sandbox')) : [],
            'allowFullscreen' => $iframe->hasAttribute('allowfullscreen'),
        ];

        $fallbackText = self::normalizedText($iframe);
        if ($fallbackText !== '') {
            $summary['fallbackText'] = $fallbackText;
        }

        if ($srcdoc !== null) {
            $summary += self::iframeSrcdocReviewSummary($srcdoc);
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private static function iframeSrcdocReviewSummary(string $srcdoc): array
    {
        $summary = [
            'srcdocReviewPolicy' => 'iframe-srcdoc-inert-fragment-review',
            'srcdocByteLength' => strlen($srcdoc),
            'srcdocSha256' => hash('sha256', $srcdoc),
            'srcdocParsed' => false,
            'srcdocDiagnostics' => [],
        ];

        if (strlen($srcdoc) > self::IFRAME_SRCDOC_REVIEW_MAX_BYTES) {
            $summary['srcdocDiagnostics'] = ['srcdoc-review-limit-exceeded'];

            return $summary;
        }

        try {
            $fragment = self::loadHtmlFragment($srcdoc, 'iframe srcdoc fragment');
        } catch (\InvalidArgumentException $exception) {
            $summary['srcdocDiagnostics'] = ['srcdoc-unsafe-or-unparseable'];
            $summary['srcdocError'] = $exception->getMessage();

            return $summary;
        }

        $root = self::requireFragmentRoot($fragment);
        $text = self::normalizedText($root);
        $topLevelElementNames = [];
        foreach ($root->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $topLevelElementNames[] = self::htmlElementName($child);
            }
        }

        $summary['srcdocParsed'] = true;
        $summary['srcdocTopLevelElementNames'] = $topLevelElementNames;
        $summary['srcdocTopLevelElementCount'] = count($topLevelElementNames);
        $summary['srcdocTextLength'] = strlen($text);
        $summary['srcdocTextSha256'] = hash('sha256', $text);
        if ($text !== '') {
            $summary['srcdocText'] = $text;
        }
        $summary['srcdocLinkHrefs'] = self::inertHtmlFragmentAttributeValues($root, ['a', 'area'], 'href');
        $summary['srcdocImageSources'] = self::inertHtmlFragmentAttributeValues($root, ['img'], 'src');
        $forms = self::descendantHtmlElements($root, 'form');
        $summary['srcdocFormCount'] = count($forms);
        $summary['srcdocFormActions'] = array_values(array_filter(
            array_map(static fn (\DOMElement $form): ?string => self::attributeOrNull($form, 'action'), $forms),
            static fn (?string $action): bool => $action !== null && $action !== ''
        ));
        $summary['srcdocActiveElementNames'] = self::inertHtmlFragmentDescendantNames($root, ['script' => true, 'style' => true]);
        $summary['srcdocEmbeddedElementNames'] = self::inertHtmlFragmentDescendantNames($root, [
            'audio' => true,
            'canvas' => true,
            'embed' => true,
            'iframe' => true,
            'object' => true,
            'video' => true,
        ]);

        return $summary;
    }

    /**
     * @param list<string> $names
     * @return list<string>
     */
    private static function inertHtmlFragmentAttributeValues(\DOMElement $root, array $names, string $attribute): array
    {
        $values = [];
        foreach ($names as $name) {
            foreach (self::descendantHtmlElements($root, $name) as $element) {
                $value = self::attributeOrNull($element, $attribute);
                if ($value !== null && $value !== '') {
                    $values[] = $value;
                }
            }
        }

        return $values;
    }

    /**
     * @param array<string, true> $names
     * @return list<string>
     */
    private static function inertHtmlFragmentDescendantNames(\DOMElement $root, array $names): array
    {
        $elementNames = [];
        foreach ($root->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }
            $name = strtolower(self::htmlElementName($element));
            if (isset($names[$name])) {
                $elementNames[] = $name;
            }
        }

        return $elementNames;
    }

    /**
     * @return array<string, mixed>
     */
    private static function embedSummary(\DOMElement $embed): array
    {
        return [
            'embeddedResource' => 'embed',
            'src' => self::attributeOrNull($embed, 'src'),
            'mimeType' => self::attributeOrNull($embed, 'type'),
            'width' => self::attributeOrNull($embed, 'width'),
            'height' => self::attributeOrNull($embed, 'height'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function objectSummary(\DOMElement $object): array
    {
        $fallbackText = self::normalizedText($object);
        $paramDetails = self::objectParamSummaries($object);
        $summary = [
            'embeddedResource' => 'object',
            'data' => self::attributeOrNull($object, 'data'),
            'mimeType' => self::attributeOrNull($object, 'type'),
            'nameAttribute' => self::attributeOrNull($object, 'name'),
            'width' => self::attributeOrNull($object, 'width'),
            'height' => self::attributeOrNull($object, 'height'),
            'params' => self::legacyObjectParamSummaries($paramDetails),
            'paramDetails' => $paramDetails,
        ];
        $summary += self::objectParamReviewSummary($paramDetails);

        if ($fallbackText !== '') {
            $summary['fallbackText'] = $fallbackText;
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private static function paramElementSummary(\DOMElement $param): array
    {
        $nameRaw = self::attributeOrNull($param, 'name');
        $name = self::normalizedNonEmptyAttribute($nameRaw);
        $valueTypeRaw = self::attributeOrNull($param, 'valuetype');
        $valueType = self::paramValueTypeSummary($valueTypeRaw);

        return [
            'embeddedResource' => 'param',
            'paramName' => $nameRaw,
            'paramNameRaw' => $nameRaw,
            'paramNameNormalized' => $name,
            'paramNameKey' => $name === null ? null : strtolower($name),
            'paramNameValid' => $name !== null && self::isSafeHtmlParamName($name),
            'value' => self::attributeOrNull($param, 'value'),
            'valueRaw' => self::attributeOrNull($param, 'value'),
            'valueType' => $valueTypeRaw,
            'valueTypeRaw' => $valueTypeRaw,
            'valueTypeState' => $valueType['state'],
            'valueTypeExplicit' => $valueType['explicit'],
            'valueTypeValid' => $valueType['valid'],
            'mimeType' => self::attributeOrNull($param, 'type'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function objectParamSummaries(\DOMElement $object): array
    {
        $params = [];
        foreach ($object->childNodes as $child) {
            if (!$child instanceof \DOMElement || strtolower(self::htmlElementName($child)) !== 'param') {
                continue;
            }

            $summary = self::paramElementSummary($child);
            unset($summary['embeddedResource']);
            $params[] = $summary;
        }

        return $params;
    }

    /**
     * @param list<array<string, mixed>> $paramDetails
     * @return list<array{paramName:?string, value:?string, valueType:?string, mimeType:?string}>
     */
    private static function legacyObjectParamSummaries(array $paramDetails): array
    {
        return array_map(
            static fn (array $summary): array => [
                'paramName' => $summary['paramName'],
                'value' => $summary['value'],
                'valueType' => $summary['valueType'],
                'mimeType' => $summary['mimeType'],
            ],
            $paramDetails
        );
    }

    /**
     * @param list<array<string, mixed>> $params
     * @return array<string, mixed>
     */
    private static function objectParamReviewSummary(array $params): array
    {
        $names = [];
        $firstNames = [];
        $nameCounts = [];
        $duplicateNames = [];
        $refParams = [];
        $objectReferenceParams = [];
        $issues = [];
        $unnamedCount = 0;
        $invalidNameCount = 0;
        $invalidValueTypeCount = 0;

        foreach ($params as $index => $param) {
            $key = $param['paramNameKey'] ?? null;
            $name = $param['paramNameNormalized'] ?? null;
            if (!is_string($key) || !is_string($name)) {
                ++$unnamedCount;
                $issues[] = [
                    'code' => 'missing-param-name',
                    'paramIndex' => $index,
                    'value' => $param['value'] ?? null,
                ];
            } elseif (($param['paramNameValid'] ?? false) !== true) {
                ++$invalidNameCount;
                $issues[] = [
                    'code' => 'invalid-param-name',
                    'paramIndex' => $index,
                    'paramNameRaw' => $param['paramNameRaw'] ?? null,
                ];
            } else {
                if (!isset($nameCounts[$key])) {
                    $names[] = $name;
                    $firstNames[$key] = $name;
                    $nameCounts[$key] = 0;
                }
                ++$nameCounts[$key];
                if ($nameCounts[$key] === 2) {
                    $duplicateNames[] = $firstNames[$key];
                    $issues[] = [
                        'code' => 'duplicate-param-name',
                        'paramName' => $firstNames[$key],
                        'paramNameKey' => $key,
                    ];
                }
            }

            if (($param['valueTypeValid'] ?? true) !== true) {
                ++$invalidValueTypeCount;
                $issues[] = [
                    'code' => 'invalid-param-valuetype',
                    'paramIndex' => $index,
                    'paramName' => $name,
                    'valueTypeRaw' => $param['valueTypeRaw'] ?? null,
                ];
            }

            $record = self::objectParamReferenceRecord($param, $index);
            if (($param['valueTypeState'] ?? 'data') === 'ref') {
                $refParams[] = $record;
            }
            if (($param['valueTypeState'] ?? 'data') === 'object') {
                $objectReferenceParams[] = $record;
            }
        }

        return [
            'paramCount' => count($params),
            'paramNames' => $names,
            'duplicateParamNames' => $duplicateNames,
            'unnamedParamCount' => $unnamedCount,
            'invalidParamNameCount' => $invalidNameCount,
            'invalidParamValueTypeCount' => $invalidValueTypeCount,
            'refParamCount' => count($refParams),
            'refParams' => $refParams,
            'objectReferenceParamCount' => count($objectReferenceParams),
            'objectReferenceParams' => $objectReferenceParams,
            'paramIssues' => $issues,
        ];
    }

    /**
     * @param array<string, mixed> $param
     * @return array{index:int, paramName:?string, value:?string, mimeType:?string, valueType:string}
     */
    private static function objectParamReferenceRecord(array $param, int $index): array
    {
        return [
            'index' => $index,
            'paramName' => is_string($param['paramNameNormalized'] ?? null) ? $param['paramNameNormalized'] : null,
            'value' => is_string($param['value'] ?? null) ? $param['value'] : null,
            'mimeType' => is_string($param['mimeType'] ?? null) ? $param['mimeType'] : null,
            'valueType' => is_string($param['valueTypeState'] ?? null) ? $param['valueTypeState'] : 'data',
        ];
    }

    /**
     * @return array{state:string, explicit:bool, valid:bool}
     */
    private static function paramValueTypeSummary(?string $valueType): array
    {
        if ($valueType === null || trim($valueType) === '') {
            return ['state' => 'data', 'explicit' => false, 'valid' => true];
        }

        $normalized = strtolower(trim($valueType));
        if (in_array($normalized, ['data', 'ref', 'object'], true)) {
            return ['state' => $normalized, 'explicit' => true, 'valid' => true];
        }

        return ['state' => 'data', 'explicit' => true, 'valid' => false];
    }

    private static function isSafeHtmlParamName(string $name): bool
    {
        return $name !== ''
            && strlen($name) <= 128
            && preg_match('/[\x00-\x1F\x7F<>"`]/', $name) !== 1;
    }

    /**
     * @return list<array{url:string, descriptor:string, descriptors:list<string>, raw:string}>
     */
    private static function srcsetCandidateSummaries(?string $srcset): array
    {
        if ($srcset === null || trim($srcset) === '') {
            return [];
        }

        $candidates = [];
        foreach (explode(',', $srcset) as $candidate) {
            $raw = trim($candidate);
            if ($raw === '') {
                continue;
            }

            $parts = preg_split('/\s+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
            if (!is_array($parts) || $parts === []) {
                continue;
            }

            $url = array_shift($parts);
            $descriptors = array_values($parts);
            $candidates[] = [
                'url' => (string) $url,
                'descriptor' => implode(' ', $descriptors),
                'descriptors' => $descriptors,
                'raw' => $raw,
            ];
        }

        return $candidates;
    }

    private static function attributeOrNull(\DOMElement $element, string $name): ?string
    {
        return $element->hasAttribute($name) ? $element->getAttribute($name) : null;
    }

    private static function normalizedNonEmptyAttribute(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    private static function mediaPreload(\DOMElement $media): string
    {
        $preload = strtolower(trim($media->getAttribute('preload')));

        return in_array($preload, ['none', 'metadata', 'auto'], true) ? $preload : 'auto';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function mediaSourceSummaries(\DOMElement $media): array
    {
        $sources = [];
        if ($media->hasAttribute('src')) {
            $sources[] = ['src' => $media->getAttribute('src')];
        }
        foreach (self::mediaResourceElements($media, 'source') as $source) {
            $summary = [];
            foreach (['src', 'type', 'media', 'srcset', 'sizes'] as $attribute) {
                if ($source->hasAttribute($attribute)) {
                    $summary[$attribute] = $source->getAttribute($attribute);
                }
            }
            $sources[] = $summary;
        }

        return $sources;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function mediaTrackSummaries(\DOMElement $media): array
    {
        $tracks = [];
        foreach (self::mediaResourceElements($media, 'track') as $track) {
            $summary = [
                'kind' => self::trackKind($track),
                'src' => $track->getAttribute('src'),
                'srclang' => $track->getAttribute('srclang'),
                'label' => $track->getAttribute('label'),
                'default' => $track->hasAttribute('default'),
            ];
            $tracks[] = $summary;
        }

        return $tracks;
    }

    private static function trackKind(\DOMElement $track): string
    {
        $kind = strtolower(trim($track->getAttribute('kind')));

        return self::isHtmlTrackKind($kind)
            ? $kind
            : 'subtitles';
    }

    private static function isHtmlTrackKind(string $kind): bool
    {
        return in_array($kind, ['subtitles', 'captions', 'descriptions', 'chapters', 'metadata'], true);
    }

    private static function normalizeHtmlTrackLanguageTag(string $value): ?string
    {
        $language = trim($value);
        if ($language === '') {
            return null;
        }

        $canonical = [];
        foreach (explode('-', $language) as $index => $part) {
            if (preg_match('/^[A-Za-z0-9]{1,8}$/', $part) !== 1) {
                return null;
            }

            if ($index === 0) {
                if (preg_match('/^(?:[A-Za-z]{2,8}|x)$/', $part) !== 1) {
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
            return null;
        }

        return implode('-', $canonical);
    }

    private static function normalizedTextWithoutMediaResourceChildren(\DOMElement $media): string
    {
        $text = '';
        foreach ($media->childNodes as $child) {
            $text .= self::mediaFallbackText($child);
        }

        $text = preg_replace('/[ \t\r\n\f]+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * @return list<\DOMElement>
     */
    private static function mediaResourceElements(\DOMElement $media, string $name): array
    {
        $resources = [];
        $stack = [];
        for ($index = $media->childNodes->length - 1; $index >= 0; --$index) {
            $child = $media->childNodes->item($index);
            if ($child instanceof \DOMElement) {
                $stack[] = $child;
            }
        }

        while ($stack !== []) {
            $node = array_pop($stack);
            if (!$node instanceof \DOMElement) {
                continue;
            }

            if (strtolower(self::htmlElementName($node)) === $name && self::belongsToMediaResourceList($node, $media)) {
                $resources[] = $node;
            }

            for ($index = $node->childNodes->length - 1; $index >= 0; --$index) {
                $child = $node->childNodes->item($index);
                if ($child instanceof \DOMElement) {
                    $stack[] = $child;
                }
            }
        }

        return $resources;
    }

    private static function belongsToMediaResourceList(\DOMElement $resource, \DOMElement $media): bool
    {
        $parent = $resource->parentNode;
        while ($parent instanceof \DOMElement && !$parent->isSameNode($media)) {
            if (in_array(strtolower(self::htmlElementName($parent)), ['audio', 'video', 'picture'], true)) {
                return false;
            }
            $parent = $parent->parentNode;
        }

        return $parent instanceof \DOMElement && $parent->isSameNode($media);
    }

    private static function mediaFallbackText(\DOMNode $node): string
    {
        if ($node instanceof \DOMText) {
            return $node->nodeValue ?? '';
        }

        if (!$node instanceof \DOMElement) {
            return '';
        }

        $text = '';
        foreach ($node->childNodes as $child) {
            $text .= self::mediaFallbackText($child);
        }

        return $text;
    }

    /**
     * @return list<string>
     */
    private static function spaceSeparatedTokens(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        return preg_split('/\s+/', $value) ?: [];
    }

    private static function numericAttribute(\DOMElement $element, string $name, ?float $default): ?float
    {
        if (!$element->hasAttribute($name)) {
            return $default;
        }

        $value = trim($element->getAttribute($name));
        if ($value === '' || !is_numeric($value)) {
            return $default;
        }

        $number = (float) $value;

        return is_finite($number) ? $number : $default;
    }

    private static function integerAttribute(\DOMElement $element, string $name, ?int $default): ?int
    {
        if (!$element->hasAttribute($name)) {
            return $default;
        }

        $value = trim($element->getAttribute($name));
        if (!preg_match('/^[+-]?[0-9]+$/', $value)) {
            return $default;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT);

        return is_int($integer) ? $integer : $default;
    }

    private static function positiveNumericAttribute(\DOMElement $element, string $name, float $default): float
    {
        $value = self::numericAttribute($element, $name, $default) ?? $default;

        return $value > 0.0 ? $value : $default;
    }

    private static function positiveIntegerAttribute(\DOMElement $element, string $name, int $default, int $max): int
    {
        $value = self::integerAttribute($element, $name, null);
        if ($value === null || $value < 1) {
            return $default;
        }

        return min($value, $max);
    }

    private static function nonNegativeIntegerAttribute(\DOMElement $element, string $name, int $default, int $max): int
    {
        $value = self::integerAttribute($element, $name, null);
        if ($value === null || $value < 0) {
            return $default;
        }

        return min($value, $max);
    }

    private static function boundedNumber(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    /**
     * @return list<string>
     */
    private static function formControlLabels(\DOMElement $control): array
    {
        $labels = [];
        $id = $control->getAttribute('id');
        $document = $control->ownerDocument;
        if ($id !== '' && $document instanceof \DOMDocument) {
            foreach ($document->getElementsByTagName('label') as $label) {
                if (!$label instanceof \DOMElement || $label->getAttribute('for') !== $id) {
                    continue;
                }
                self::appendFormControlLabel($labels, self::normalizedText($label));
            }
        }

        $parent = $control->parentNode;
        while ($parent instanceof \DOMElement) {
            if (strtolower(self::htmlElementName($parent)) === 'label') {
                self::appendFormControlLabel($labels, self::normalizedText($parent));
            }
            $parent = $parent->parentNode;
        }

        return $labels;
    }

    /**
     * @param list<string> $labels
     */
    private static function appendFormControlLabel(array &$labels, string $label): void
    {
        if ($label !== '' && !in_array($label, $labels, true)) {
            $labels[] = $label;
        }
    }

    private static function isEffectivelyDisabledFormControl(\DOMElement $control): bool
    {
        if ($control->hasAttribute('disabled')) {
            return true;
        }

        $parent = $control->parentNode;
        while ($parent instanceof \DOMElement) {
            if (strtolower(self::htmlElementName($parent)) === 'fieldset' && $parent->hasAttribute('disabled')) {
                $legend = self::firstChildHtmlElement($parent, 'legend');
                if (!$legend instanceof \DOMElement || !self::isDescendantOrSame($control, $legend)) {
                    return true;
                }
            }
            $parent = $parent->parentNode;
        }

        return false;
    }

    private static function firstChildHtmlElement(\DOMElement $element, string $name): ?\DOMElement
    {
        foreach (self::childHtmlElements($element, $name) as $child) {
            return $child;
        }

        return null;
    }

    /**
     * @return list<\DOMElement>
     */
    private static function childHtmlElements(\DOMElement $element, string $name): array
    {
        $children = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement && strtolower(self::htmlElementName($child)) === $name) {
                $children[] = $child;
            }
        }

        return $children;
    }

    private static function firstDescendantHtmlElement(\DOMElement $element, string $name): ?\DOMElement
    {
        foreach (self::descendantHtmlElements($element, $name) as $descendant) {
            return $descendant;
        }

        return null;
    }

    /**
     * @return list<\DOMElement>
     */
    private static function descendantHtmlElements(\DOMElement $element, string $name): array
    {
        $descendants = [];
        foreach ($element->getElementsByTagName('*') as $descendant) {
            if ($descendant instanceof \DOMElement && strtolower(self::htmlElementName($descendant)) === $name) {
                $descendants[] = $descendant;
            }
        }

        return $descendants;
    }

    private static function isDescendantOrSame(\DOMElement $element, \DOMElement $ancestor): bool
    {
        $current = $element;
        while ($current instanceof \DOMElement) {
            if ($current->isSameNode($ancestor)) {
                return true;
            }
            $current = $current->parentNode;
        }

        return false;
    }

    /**
     * @return list<array{value:string, label:string, text:string, disabled:bool}>
     */
    private static function datalistOptionsForControl(\DOMElement $control): array
    {
        $listId = $control->getAttribute('list');
        $document = $control->ownerDocument;
        if ($listId === '' || !$document instanceof \DOMDocument) {
            return [];
        }

        foreach ($document->getElementsByTagName('datalist') as $datalist) {
            if ($datalist instanceof \DOMElement && $datalist->getAttribute('id') === $listId) {
                return self::datalistOptionSummaries($datalist);
            }
        }

        return [];
    }

    /**
     * @return list<array{value:string, label:string, text:string, disabled:bool}>
     */
    private static function datalistOptionSummaries(\DOMElement $datalist): array
    {
        $options = [];
        foreach ($datalist->getElementsByTagName('option') as $option) {
            if (!$option instanceof \DOMElement) {
                continue;
            }
            $text = self::normalizedText($option);
            $options[] = [
                'value' => $option->hasAttribute('value') ? $option->getAttribute('value') : $text,
                'label' => $option->hasAttribute('label') ? $option->getAttribute('label') : $text,
                'text' => $text,
                'disabled' => $option->hasAttribute('disabled'),
            ];
        }

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    private static function fieldsetSummary(\DOMElement $fieldset): array
    {
        $legend = self::firstChildHtmlElement($fieldset, 'legend');
        $controls = self::fieldsetControlSummaries($fieldset, $legend);

        return [
            'formGroup' => 'fieldset',
            'disabled' => $fieldset->hasAttribute('disabled'),
            'legendText' => $legend instanceof \DOMElement ? self::normalizedText($legend) : null,
            'legendCount' => count(self::childHtmlElements($fieldset, 'legend')),
            'controlCount' => count($controls),
            'legendControlCount' => count(array_filter(
                $controls,
                static fn (array $control): bool => (bool) ($control['inFirstLegend'] ?? false)
            )),
            'controls' => $controls,
            'controlNames' => self::fieldsetControlNames($controls),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function legendSummary(\DOMElement $legend): array
    {
        $fieldset = self::parentHtmlElement($legend, 'fieldset');
        $firstLegend = $fieldset instanceof \DOMElement ? self::firstChildHtmlElement($fieldset, 'legend') : null;

        return [
            'formGroupPart' => 'legend',
            'legendText' => self::normalizedText($legend),
            'fieldsetDisabled' => $fieldset instanceof \DOMElement ? $fieldset->hasAttribute('disabled') : null,
            'firstLegend' => $firstLegend instanceof \DOMElement && $legend->isSameNode($firstLegend),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function fieldsetControlSummaries(\DOMElement $fieldset, ?\DOMElement $firstLegend): array
    {
        $controls = [];
        foreach ($fieldset->getElementsByTagName('*') as $control) {
            if (!$control instanceof \DOMElement || !self::isFormControlElement($control)) {
                continue;
            }

            $name = self::htmlElementName($control);
            $controlSummary = [
                'tag' => $name,
                'id' => self::attributeOrNull($control, 'id'),
                'controlName' => self::attributeOrNull($control, 'name'),
                'effectiveDisabled' => self::isEffectivelyDisabledFormControl($control),
                'inFirstLegend' => $firstLegend instanceof \DOMElement && self::isDescendantOrSame($control, $firstLegend),
            ];

            if ($name === 'input') {
                $controlSummary['type'] = self::inputType($control);
            } elseif ($name === 'button') {
                $controlSummary['type'] = self::buttonType($control);
            }

            $controls[] = $controlSummary;
        }

        return $controls;
    }

    private static function isFormControlElement(\DOMElement $element): bool
    {
        return in_array(self::htmlElementName($element), ['button', 'input', 'output', 'select', 'textarea'], true);
    }

    /**
     * @param list<array<string, mixed>> $controls
     * @return list<string>
     */
    private static function fieldsetControlNames(array $controls): array
    {
        $names = [];
        foreach ($controls as $control) {
            $name = $control['controlName'] ?? null;
            if (is_string($name) && $name !== '' && !in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private static function parentHtmlElement(\DOMElement $element, string $name): ?\DOMElement
    {
        $parent = $element->parentNode;

        return $parent instanceof \DOMElement && self::htmlElementName($parent) === $name ? $parent : null;
    }

    private static function tableHeaderScope(\DOMElement $header): ?string
    {
        $scope = strtolower(trim($header->getAttribute('scope')));

        return in_array($scope, ['row', 'col', 'rowgroup', 'colgroup'], true) ? $scope : null;
    }

    private static function serializeNode(\DOMNode $node): string
    {
        if ($node instanceof \DOMText) {
            return htmlspecialchars($node->nodeValue ?? '', ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        }

        if ($node instanceof \DOMComment) {
            $text = self::safeHtmlCommentText($node->nodeValue ?? '');

            return '<!--' . $text . '-->';
        }

        if ($node instanceof \DOMDocument || $node instanceof \DOMDocumentFragment) {
            $html = '';
            foreach ($node->childNodes as $child) {
                $html .= self::serializeNode($child);
            }

            return $html;
        }

        if (!$node instanceof \DOMElement) {
            return '';
        }

        $name = self::htmlElementName($node);
        if (self::isHtmlTableModelContext($name)) {
            return self::serializeTableElementWithFostered($node, $name);
        }

        return self::serializeElementWithChildren($node, null);
    }

    private static function serializeElementWithChildren(\DOMElement $node, ?string $childrenHtml): string
    {
        $name = self::htmlElementName($node);
        $html = '<' . $name . self::serializeAttributes($node);
        if (isset(self::HTML5_VOID_ELEMENTS[strtolower($name)])) {
            return $html . '>' . self::serializeHtmlChildren($node);
        }

        $html .= '>';
        if ($childrenHtml !== null) {
            $html .= $childrenHtml;
        } elseif (isset(self::HTML5_RAW_TEXT_ELEMENTS[strtolower($name)])) {
            $html .= self::rawTextContent($node);
        } else {
            foreach ($node->childNodes as $child) {
                $html .= self::serializeNode($child);
            }
        }

        return $html . '</' . $name . '>';
    }

    private static function serializeTableElementWithFostered(\DOMElement $element, string $context): string
    {
        [$fosteredHtml, $childrenHtml] = self::serializeTableChildren($element, $context);

        return $fosteredHtml . self::serializeElementWithChildren($element, $childrenHtml);
    }

    /**
     * @return array{0:string, 1:string}
     */
    private static function serializeTableElementParts(\DOMElement $element, string $context): array
    {
        [$fosteredHtml, $childrenHtml] = self::serializeTableChildren($element, $context);

        return [$fosteredHtml, self::serializeElementWithChildren($element, $childrenHtml)];
    }

    /**
     * @return array{0:string, 1:string}
     */
    private static function serializeTableChildren(\DOMElement $element, string $context): array
    {
        $fosteredHtml = '';
        $childrenHtml = '';

        foreach ($element->childNodes as $child) {
            if (self::isFosteredHtmlTableChild($child, $context)) {
                $fosteredHtml .= self::serializeNode($child);
                continue;
            }

            if ($child instanceof \DOMElement) {
                $childName = self::htmlElementName($child);
                if (self::isHtmlTableModelContext($childName)) {
                    [$nestedFosteredHtml, $childHtml] = self::serializeTableElementParts($child, $childName);
                    $fosteredHtml .= $nestedFosteredHtml;
                    $childrenHtml .= $childHtml;
                    continue;
                }
            }

            $childrenHtml .= self::serializeNode($child);
        }

        return [$fosteredHtml, $childrenHtml];
    }

    /**
     * @return array{0:list<array<string, mixed>>, 1:array<string, mixed>}
     */
    private static function summarizeTableElementParts(\DOMElement $element, string $context): array
    {
        [$fostered, $children] = self::summarizeTableChildren($element, $context);

        $summary = [
            'type' => 'element',
            'name' => self::htmlElementName($element),
            'attributes' => self::htmlAttributes($element),
            'text' => self::summaryText($children),
            'children' => $children,
        ];
        $summary += self::globalHtmlAttributeSummary($element);
        $summary += self::tableElementSummary($element, self::htmlElementName($element));

        return [$fostered, $summary];
    }

    /**
     * @return array{0:list<array<string, mixed>>, 1:list<array<string, mixed>>}
     */
    private static function summarizeTableChildren(\DOMElement $element, string $context): array
    {
        $fostered = [];
        $children = [];

        foreach ($element->childNodes as $child) {
            if (self::isFosteredHtmlTableChild($child, $context)) {
                array_push($fostered, ...self::summarizeNode($child));
                continue;
            }

            if ($child instanceof \DOMElement) {
                $childName = self::htmlElementName($child);
                if (self::isHtmlTableModelContext($childName)) {
                    [$nestedFostered, $summary] = self::summarizeTableElementParts($child, $childName);
                    array_push($fostered, ...$nestedFostered);
                    $children[] = $summary;
                    continue;
                }
            }

            array_push($children, ...self::summarizeNode($child));
        }

        return [$fostered, $children];
    }

    /**
     * @return list<array{value:string, label:string, text:string, selected:bool, disabled:bool, group?:string, groupDisabled?:bool}>
     */
    private static function selectOptionSummaries(\DOMElement $select): array
    {
        $options = [];
        foreach ($select->childNodes as $child) {
            self::collectSelectOptionSummaries($child, null, false, $options);
        }

        return $options;
    }

    /**
     * @param list<array{value:string, label:string, text:string, selected:bool, disabled:bool, group?:string, groupDisabled?:bool}> $options
     */
    private static function collectSelectOptionSummaries(\DOMNode $node, ?string $group, bool $groupDisabled, array &$options): void
    {
        if (!$node instanceof \DOMElement) {
            return;
        }

        $name = strtolower(self::htmlElementName($node));
        if ($name === 'optgroup') {
            $nextGroup = $node->getAttribute('label');
            $nextGroupDisabled = $groupDisabled || $node->hasAttribute('disabled');
            foreach ($node->childNodes as $child) {
                self::collectSelectOptionSummaries($child, $nextGroup, $nextGroupDisabled, $options);
            }

            return;
        }

        if ($name === 'option') {
            $text = self::normalizedText($node);
            $option = [
                'value' => $node->hasAttribute('value') ? $node->getAttribute('value') : $text,
                'label' => $node->hasAttribute('label') ? $node->getAttribute('label') : $text,
                'text' => $text,
                'selected' => $node->hasAttribute('selected'),
                'disabled' => $groupDisabled || $node->hasAttribute('disabled'),
            ];
            if ($group !== null) {
                $option['group'] = $group;
                $option['groupDisabled'] = $groupDisabled;
            }
            $options[] = $option;

            return;
        }

        foreach ($node->childNodes as $child) {
            self::collectSelectOptionSummaries($child, $group, $groupDisabled, $options);
        }
    }

    /**
     * @param list<array<string, mixed>> $summary
     */
    private static function summaryText(array $summary): string
    {
        $text = '';
        foreach ($summary as $node) {
            if (($node['type'] ?? '') === 'text') {
                $text .= (string) ($node['text'] ?? '');
                continue;
            }
            if (($node['type'] ?? '') === 'element') {
                $text .= (string) ($node['text'] ?? '');
            }
        }

        $normalized = preg_replace('/[ \t\r\n\f]+/u', ' ', $text) ?? $text;

        return trim($normalized);
    }

    private static function isHtmlTableModelContext(string $name): bool
    {
        return isset(self::HTML5_TABLE_ALLOWED_CHILDREN[strtolower($name)]);
    }

    private static function isFosteredHtmlTableChild(\DOMNode $node, string $context): bool
    {
        $allowed = self::HTML5_TABLE_ALLOWED_CHILDREN[strtolower($context)] ?? null;
        if ($allowed === null) {
            return false;
        }

        if ($node instanceof \DOMText) {
            return preg_match('/\S/u', $node->nodeValue ?? '') === 1;
        }

        if (!$node instanceof \DOMElement) {
            return false;
        }

        $name = strtolower(self::htmlElementName($node));

        return !isset($allowed[$name]);
    }

    private static function rawTextContent(\DOMElement $element): string
    {
        $text = '';
        foreach ($element->childNodes as $child) {
            $text .= $child->nodeValue ?? '';
        }

        return $text;
    }

    private static function escapeHtmlRawTextContent(string $content): string
    {
        return strtr($content, ['&' => '&amp;', '<' => '&lt;', '>' => '&gt;']);
    }

    private static function protectHtmlCdataSections(string $html): string
    {
        return preg_replace_callback(
            '/<!\[CDATA\[(.*?)\]\]>/s',
            static fn (array $matches): string => self::escapeHtmlRawTextContent((string) $matches[1]),
            $html
        ) ?? $html;
    }

    private static function normalizeHtml5NamedCharacterReferences(string $html): string
    {
        return preg_replace_callback(
            '/&([A-Za-z][A-Za-z0-9]+);|&([A-Za-z][A-Za-z0-9]+)(?![A-Za-z0-9=])/',
            static function (array $matches): string {
                $semicolonName = (string) ($matches[1] ?? '');
                $legacyName = (string) ($matches[2] ?? '');
                $name = $semicolonName !== '' ? $semicolonName : $legacyName;
                if ($semicolonName === '' && !isset(self::HTML5_LEGACY_SEMICOLONLESS_CHARACTER_REFERENCES[$name])) {
                    return (string) $matches[0];
                }

                return self::HTML5_ADDITIONAL_NAMED_CHARACTER_REFERENCES[$name]
                    ?? self::decodeHtml5NamedCharacterReference($name, $semicolonName !== '')
                    ?? (string) $matches[0];
            },
            $html
        ) ?? $html;
    }

    private static function decodeHtml5NamedCharacterReference(string $name, bool $semicolonTerminated): ?string
    {
        if (!$semicolonTerminated || isset(self::HTML5_MARKUP_SENSITIVE_CHARACTER_REFERENCES[$name])) {
            return null;
        }

        $source = '&' . $name . ';';
        $decoded = html_entity_decode($source, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($decoded === $source || strpbrk($decoded, "&<>\"'") !== false) {
            return null;
        }

        return $decoded;
    }

    private static function escapeHtmlRcdataContent(string $content): string
    {
        return strtr($content, ['<' => '&lt;', '>' => '&gt;']);
    }

    private static function safeHtmlCommentText(string $text): string
    {
        while (str_contains($text, '--')) {
            $text = str_replace('--', '- -', $text);
        }

        return str_ends_with($text, '-') ? $text . ' ' : $text;
    }

    private static function serializeAttributes(\DOMElement $element): string
    {
        $attributes = self::htmlAttributes($element);
        if ($attributes === []) {
            return '';
        }

        $html = '';
        foreach ($attributes as $name => $value) {
            $lowerName = strtolower($name);
            if (isset(self::HTML5_BOOLEAN_ATTRIBUTES[$lowerName]) && ($value === '' || strtolower($value) === $lowerName)) {
                $html .= ' ' . $name;
                continue;
            }

            $html .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8') . '"';
        }

        return $html;
    }

    private static function assertSafeSource(string $source, string $label): void
    {
        if (str_contains($source, "\0")) {
            throw new \InvalidArgumentException($label . ' must not contain NUL bytes');
        }
    }

    private static function assertNoDoctype(string $source, string $label): void
    {
        if (preg_match('/<!\s*DOCTYPE\b/i', $source) === 1) {
            throw new \InvalidArgumentException($label . ' must not declare a document type');
        }
    }

    private static function assertNoHtmlFragmentDeclarations(string $source, string $label): void
    {
        if (preg_match('/<!\s*(?:ENTITY|ELEMENT|ATTLIST|NOTATION)\b/i', $source) === 1) {
            throw new \InvalidArgumentException($label . ' must not declare DTDs or entities');
        }
        if (preg_match('/<\?[A-Za-z_][A-Za-z0-9_.:-]*/', $source) === 1) {
            throw new \InvalidArgumentException($label . ' must not include processing instructions');
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

    private static function isHtmlForeignElement(\DOMElement $element): bool
    {
        return self::htmlForeignContext($element) !== null;
    }

    private static function htmlForeignContext(\DOMElement $element): ?string
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
                    return null;
                }
            }
            if ($name === 'svg' || $name === 'math') {
                return $name;
            }
            if ($name === 'html' || $name === 'body') {
                return null;
            }

            $parent = $node->parentNode;
            $child = $node;
            $node = $parent instanceof \DOMElement ? $parent : null;
            $isSelf = false;
        }

        return null;
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

    /**
     * @param list<\LibXMLError> $errors
     */
    private static function parseErrorMessage(string $prefix, array $errors): string
    {
        if ($errors === []) {
            return $prefix;
        }

        $error = $errors[0];
        $message = trim($error->message);
        if ($message === '') {
            return $prefix;
        }

        return $prefix . ' at line ' . $error->line . ', column ' . $error->column . ': ' . $message;
    }
}
