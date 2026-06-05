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
        $html = self::protectHtmlRcdataElements($html);

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

        return self::isHtmlForeignElement($element)
            ? self::adjustHtmlForeignElementName($name)
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

    public static function adjustHtmlForeignElementName(string $lowercaseName): string
    {
        return self::HTML5_FOREIGN_ELEMENT_NAMES[$lowercaseName] ?? $lowercaseName;
    }

    public static function adjustHtmlForeignAttributeName(string $lowercaseName): string
    {
        return self::HTML5_FOREIGN_ATTRIBUTE_NAMES[$lowercaseName] ?? $lowercaseName;
    }

    public static function protectHtmlRcdataElements(string $html): string
    {
        $offset = 0;
        $protected = '';
        $pattern = '~<(?P<name>xmp|noembed|noframes|title|textarea|plaintext)(?=[\s/>])(?:[^>"\']+|"[^"]*"|\'[^\']*\')*>~is';

        while (preg_match($pattern, $html, $matches, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $startTag = (string) $matches[0][0];
            $startOffset = (int) $matches[0][1];
            $name = strtolower((string) $matches['name'][0]);
            $contentStart = $startOffset + strlen($startTag);

            $protected .= substr($html, $offset, $startOffset - $offset) . $startTag;

            if ($name === 'plaintext') {
                $protected .= self::escapeHtmlRawTextContent(substr($html, $contentStart)) . '</plaintext>';

                return $protected;
            }

            $endPattern = '~</\s*' . preg_quote($name, '~') . '\s*>~i';
            if (preg_match($endPattern, $html, $endMatches, PREG_OFFSET_CAPTURE, $contentStart) !== 1) {
                $protected .= substr($html, $contentStart);

                return $protected;
            }

            $endTag = (string) $endMatches[0][0];
            $endOffset = (int) $endMatches[0][1];
            $content = substr($html, $contentStart, $endOffset - $contentStart);
            $protected .= in_array($name, ['title', 'textarea'], true)
                ? self::escapeHtmlRcdataContent($content)
                : self::escapeHtmlRawTextContent($content);
            $protected .= $endTag;
            $offset = $endOffset + strlen($endTag);
        }

        return $protected . substr($html, $offset);
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

        return [[
            'type' => 'element',
            'name' => $name,
            'attributes' => self::htmlAttributes($node),
            'text' => self::normalizedText($node),
            'children' => $children,
        ]];
    }

    private static function serializeNode(\DOMNode $node): string
    {
        if ($node instanceof \DOMText) {
            return htmlspecialchars($node->nodeValue ?? '', ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        }

        if ($node instanceof \DOMComment) {
            $text = str_replace('--', '- -', $node->nodeValue ?? '');

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
            return $html . '>';
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

    private static function escapeHtmlRcdataContent(string $content): string
    {
        return strtr($content, ['<' => '&lt;', '>' => '&gt;']);
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
        $node = $element;
        $isSelf = true;
        while ($node instanceof \DOMElement) {
            $name = strtolower($node->localName);
            if (!$isSelf && self::isHtmlIntegrationPoint($node)) {
                return false;
            }
            if ($name === 'svg' || $name === 'math') {
                return true;
            }
            if ($name === 'html' || $name === 'body') {
                return false;
            }

            $parent = $node->parentNode;
            $node = $parent instanceof \DOMElement ? $parent : null;
            $isSelf = false;
        }

        return false;
    }

    private static function isHtmlIntegrationPoint(\DOMElement $element): bool
    {
        $name = strtolower($element->localName);
        if ($name === 'foreignobject') {
            return true;
        }
        if ($name !== 'annotation-xml') {
            return false;
        }

        $encoding = strtolower(trim($element->getAttribute('encoding')));

        return $encoding === 'text/html' || $encoding === 'application/xhtml+xml';
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
