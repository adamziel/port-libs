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
        self::assertNoDoctype($html, $label);
        self::assertNoHtmlFragmentDeclarations($html, $label);
        $html = self::protectHtmlRcdataElements($html, protectTemplateContent: true, protectIframeContent: true);

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
        bool $protectIframeContent = false
    ): string
    {
        $offset = 0;
        $protected = '';
        $rawTextNames = 'script|style|xmp|noembed|noframes|title|textarea|plaintext'
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
                $protected .= self::protectHtmlRcdataElementContent($name, substr($html, $contentStart))
                    . '</' . $name . '>';

                return $protected;
            }

            $endTag = (string) $endMatches[0][0];
            $endOffset = (int) $endMatches[0][1];
            $content = substr($html, $contentStart, $endOffset - $contentStart);
            $protected .= self::protectHtmlRcdataElementContent($name, $content);
            $protected .= $endTag;
            $offset = $endOffset + strlen($endTag);
        }

        return $protected . self::normalizeHtml5NamedCharacterReferences(
            self::protectHtmlCdataSections(substr($html, $offset))
        );
    }

    private static function protectHtmlRcdataElementContent(string $name, string $content): string
    {
        if (in_array($name, ['script', 'style'], true)) {
            return $content;
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
            $summary['formControl'] = 'input';
            $summary['inputType'] = self::inputType($node);
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
            $summary['formControl'] = 'button';
            $summary['buttonType'] = self::buttonType($node);
            $summary['labels'] = self::formControlLabels($node);
            $summary['value'] = $node->getAttribute('value');
            $summary['label'] = self::normalizedText($node);
            $summary['disabled'] = $node->hasAttribute('disabled');
            $summary['effectiveDisabled'] = self::isEffectivelyDisabledFormControl($node);
        }
        if ($name === 'datalist') {
            $summary['formControl'] = 'datalist';
            $summary['datalistOptions'] = self::datalistOptionSummaries($node);
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
        if ($name === 'audio' || $name === 'video') {
            $summary['media'] = $name;
            $summary['sources'] = self::mediaSourceSummaries($node);
            $summary['tracks'] = self::mediaTrackSummaries($node);
            $summary['controls'] = $node->hasAttribute('controls');
            $summary['autoplay'] = $node->hasAttribute('autoplay');
            $summary['loop'] = $node->hasAttribute('loop');
            $summary['muted'] = $node->hasAttribute('muted');
            $summary['preload'] = self::mediaPreload($node);
            if ($name === 'video' && $node->hasAttribute('poster')) {
                $summary['poster'] = $node->getAttribute('poster');
            }
            $fallbackText = self::normalizedTextWithoutMediaResourceChildren($node);
            if ($fallbackText !== '') {
                $summary['fallbackText'] = $fallbackText;
            }
        }

        return [$summary];
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

    private static function positiveNumericAttribute(\DOMElement $element, string $name, float $default): float
    {
        $value = self::numericAttribute($element, $name, $default) ?? $default;

        return $value > 0.0 ? $value : $default;
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
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement && strtolower(self::htmlElementName($child)) === $name) {
                return $child;
            }
        }

        return null;
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

        return [$fostered, [
            'type' => 'element',
            'name' => self::htmlElementName($element),
            'attributes' => self::htmlAttributes($element),
            'text' => self::summaryText($children),
            'children' => $children,
        ]];
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
        $isSelf = true;
        while ($node instanceof \DOMElement) {
            $name = strtolower($node->localName);
            if (!$isSelf && self::isHtmlIntegrationPoint($node)) {
                return null;
            }
            if ($name === 'svg' || $name === 'math') {
                return $name;
            }
            if ($name === 'html' || $name === 'body') {
                return null;
            }

            $parent = $node->parentNode;
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
