<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class XmlHtmlDom
{
    private const FRAGMENT_ROOT_ATTRIBUTE = 'data-port-libs-pandoc-fragment-root';

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

    private static function requireFragmentRoot(\DOMDocument $dom): \DOMElement
    {
        $root = self::fragmentRoot($dom);
        if (!$root instanceof \DOMElement) {
            throw new \InvalidArgumentException('DOM document is not a Pandoc HTML fragment document');
        }

        return $root;
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
        if (in_array($name, ['ol', 'ul', 'menu', 'li'], true)) {
            $summary += self::listSummary($node, $name);
        }
        if (self::isHtmlHeadingElementName($name)) {
            $summary += self::headingSummary($node, $name);
        }
        if (self::isHtmlOutlineElementName($name)) {
            $summary += self::outlineSummary($node, $name);
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
            $summary['labels'] = self::formControlLabels($node);
            $summary['disabled'] = $node->hasAttribute('disabled');
            $summary['effectiveDisabled'] = self::isEffectivelyDisabledFormControl($node);
            $summary['required'] = $node->hasAttribute('required');
            $summary['selectOptions'] = $options;
            $summary['selectedValues'] = array_values(array_map(
                static fn (array $option): string => (string) $option['value'],
                array_filter($options, static fn (array $option): bool => (bool) ($option['selected'] ?? false))
            ));
        }
        if ($name === 'input') {
            $inputType = self::inputType($node);
            $summary['formControl'] = 'input';
            $summary['inputType'] = $inputType;
            $summary['labels'] = self::formControlLabels($node);
            $summary['value'] = $node->getAttribute('value');
            $summary['checked'] = $node->hasAttribute('checked');
            $summary['disabled'] = $node->hasAttribute('disabled');
            $summary['effectiveDisabled'] = self::isEffectivelyDisabledFormControl($node);
            $summary['required'] = $node->hasAttribute('required');
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
            $summary['labels'] = self::formControlLabels($node);
            $summary['value'] = $node->textContent;
            $summary['disabled'] = $node->hasAttribute('disabled');
            $summary['effectiveDisabled'] = self::isEffectivelyDisabledFormControl($node);
            $summary['readonly'] = $node->hasAttribute('readonly');
            $summary['required'] = $node->hasAttribute('required');
            if ($node->hasAttribute('placeholder')) {
                $summary['placeholder'] = $node->getAttribute('placeholder');
            }
        }
        if ($name === 'button') {
            $buttonType = self::buttonType($node);
            $summary['formControl'] = 'button';
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
            $summary['labels'] = self::formControlLabels($node);
            $summary['value'] = $node->textContent;
            $summary['forRaw'] = $forRaw;
            $summary['forIds'] = $forRaw === null ? [] : self::spaceSeparatedTokens($forRaw);
        }
        if ($name === 'datalist') {
            $summary['formControl'] = 'datalist';
            $summary['datalistOptions'] = self::datalistOptionSummaries($node);
        }
        if ($name === 'details') {
            $summaryElements = self::detailsSummaryElements($node);
            $summary['disclosure'] = 'details';
            $summary['open'] = $node->hasAttribute('open');
            $summary['summaryText'] = $summaryElements === [] ? null : self::normalizedText($summaryElements[0]);
            $summary['summaryElementCount'] = count($summaryElements);
        }
        if ($name === 'dialog') {
            $summary['dialog'] = 'dialog';
            $summary['dialogOpen'] = $node->hasAttribute('open');
            $summary['dialogText'] = self::normalizedText($node);
        }
        if ($name === 'summary') {
            $summary['disclosure'] = 'summary';
            $summary['label'] = self::normalizedText($node);
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
            $max = self::positiveNumericAttribute($node, 'max', 1.0);
            $value = self::numericAttribute($node, 'value', null);
            $value = $value === null ? null : self::boundedNumber($value, 0.0, $max);
            $summary['measurement'] = 'progress';
            $summary['labels'] = self::formControlLabels($node);
            $summary['value'] = $value;
            $summary['max'] = $max;
            $summary['position'] = $value === null ? null : $value / $max;
            $summary['indeterminate'] = $value === null;
        }
        if ($name === 'meter') {
            $min = self::numericAttribute($node, 'min', 0.0) ?? 0.0;
            $max = self::numericAttribute($node, 'max', 1.0) ?? 1.0;
            if ($max < $min) {
                $max = $min;
            }

            $summary['measurement'] = 'meter';
            $summary['labels'] = self::formControlLabels($node);
            $summary['min'] = $min;
            $summary['max'] = $max;
            $summary['value'] = self::boundedNumber(self::numericAttribute($node, 'value', $min) ?? $min, $min, $max);
            foreach (['low', 'high', 'optimum'] as $threshold) {
                $thresholdValue = self::numericAttribute($node, $threshold, null);
                if ($thresholdValue !== null) {
                    $summary[$threshold] = $thresholdValue;
                }
            }
        }
        if (in_array($name, ['picture', 'img', 'audio', 'video', 'source', 'track', 'iframe', 'embed', 'object', 'param'], true)) {
            $summary += self::embeddedResourceSummary($node, $name);
        }
        if (in_array($name, ['a', 'area'], true)) {
            $summary += self::hyperlinkSummary($node, $name);
        }
        if (in_array($name, ['base', 'link', 'meta'], true)) {
            $summary += self::documentMetadataSummary($node, $name);
        }
        if ($name === 'template') {
            $summary += self::templateSummary($node);
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
        if (in_array($name, ['abbr', 'bdi', 'bdo', 'code', 'dfn', 'kbd', 'mark', 's', 'samp', 'small', 'sub', 'sup', 'u', 'var'], true)) {
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

    private static function isHtmlOutlineElementName(string $name): bool
    {
        return in_array($name, ['article', 'aside', 'main', 'nav', 'section'], true);
    }

    private static function firstScopedHeadingElement(\DOMElement $element): ?\DOMElement
    {
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $name = self::htmlElementName($child);
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

            return [
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
    private static function templateSummary(\DOMElement $element): array
    {
        $text = $element->textContent;

        return [
            'template' => 'inert-source',
            'templateText' => $text,
            'templateTextLength' => strlen($text),
            'templateTextSha256' => hash('sha256', $text),
            'templateContainsMarkupLikeText' => preg_match('/<\s*[A-Za-z!\/?]/', $text) === 1,
            'templateContainsActiveLikeText' => preg_match('/<\s*(script|style|iframe|object|embed|link|meta)\b|<!doctype|<\?/i', $text) === 1,
            'templateReviewPolicy' => 'template-inert-escaped-source',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function textSemanticSummary(\DOMElement $element, string $name): array
    {
        $summary = [
            'textSemantic' => match ($name) {
                'abbr' => 'abbreviation',
                'bdi' => 'bidirectional-isolate',
                'bdo' => 'bidirectional-override',
                'code' => 'code',
                'dfn' => 'definition',
                'kbd' => 'keyboard-input',
                'mark' => 'mark',
                's' => 'struck-text',
                'samp' => 'sample-output',
                'small' => 'side-comment',
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
            $valueRaw = self::attributeOrNull($element, 'value');

            return [
                'listItem' => true,
                'valueRaw' => $valueRaw,
                'value' => self::integerAttribute($element, 'value', null),
            ];
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
        }

        if (array_key_exists('role', $attributes)) {
            $summary['roleRaw'] = $attributes['role'];
            $summary['roles'] = self::spaceSeparatedTokens($attributes['role']);
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
        ];
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
            default => [],
        };
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

        return [
            'embeddedResource' => 'image',
            'src' => self::attributeOrNull($image, 'src'),
            'alt' => self::attributeOrNull($image, 'alt'),
            'srcset' => $srcset,
            'srcsetCandidates' => self::srcsetCandidateSummaries($srcset),
            'sizes' => self::attributeOrNull($image, 'sizes'),
            'loading' => self::attributeOrNull($image, 'loading'),
            'decoding' => self::attributeOrNull($image, 'decoding'),
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
    private static function iframeSummary(\DOMElement $iframe): array
    {
        $summary = [
            'embeddedResource' => 'iframe',
            'src' => self::attributeOrNull($iframe, 'src'),
            'srcdoc' => self::attributeOrNull($iframe, 'srcdoc'),
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

        return $summary;
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
        $summary = [
            'embeddedResource' => 'object',
            'data' => self::attributeOrNull($object, 'data'),
            'mimeType' => self::attributeOrNull($object, 'type'),
            'nameAttribute' => self::attributeOrNull($object, 'name'),
            'width' => self::attributeOrNull($object, 'width'),
            'height' => self::attributeOrNull($object, 'height'),
            'params' => self::objectParamSummaries($object),
        ];

        if ($fallbackText !== '') {
            $summary['fallbackText'] = $fallbackText;
        }

        return $summary;
    }

    /**
     * @return array{embeddedResource:string, paramName:?string, value:?string, valueType:?string, mimeType:?string}
     */
    private static function paramElementSummary(\DOMElement $param): array
    {
        return [
            'embeddedResource' => 'param',
            'paramName' => self::attributeOrNull($param, 'name'),
            'value' => self::attributeOrNull($param, 'value'),
            'valueType' => self::attributeOrNull($param, 'valuetype'),
            'mimeType' => self::attributeOrNull($param, 'type'),
        ];
    }

    /**
     * @return list<array{paramName:?string, value:?string, valueType:?string, mimeType:?string}>
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

        return in_array($kind, ['subtitles', 'captions', 'descriptions', 'chapters', 'metadata'], true)
            ? $kind
            : 'subtitles';
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
