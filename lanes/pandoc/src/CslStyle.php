<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class CslStyle
{
    private const CSL_NS = 'http://purl.org/net/xbiblio/csl';
    private const XML_NS = 'http://www.w3.org/XML/1998/namespace';

    /** @var array<string, array{single:string, multiple:string}> */
    private const DEFAULT_TERMS = [
        'and|long' => ['single' => 'and', 'multiple' => 'and'],
        'et-al|long' => ['single' => 'et al.', 'multiple' => 'et al.'],
        'no date|long' => ['single' => 'n.d.', 'multiple' => 'n.d.'],
        'accessed|long' => ['single' => 'Accessed', 'multiple' => 'Accessed'],
    ];

    /**
     * @param array{prefix:string, suffix:string, delimiter:string} $citationLayout
     * @param array{prefix:string, suffix:string, delimiter:string} $bibliographyLayout
     * @param array{hangingIndent:bool, entrySpacing:int|null, lineSpacing:int|null, secondFieldAlign:string} $bibliographyOptions
     * @param list<array{sort:string, variable?:string, macro?:string}> $citationSortKeys
     * @param list<array{sort:string, variable?:string, macro?:string}> $bibliographySortKeys
     * @param array<string, array{single:string, multiple:string}> $terms
     * @param array{title:string, id:string, class:string, defaultLocale:string} $metadata
     */
    private function __construct(
        private readonly array $citationLayout,
        private readonly array $bibliographyLayout,
        private readonly array $bibliographyOptions,
        private readonly array $citationSortKeys,
        private readonly array $bibliographySortKeys,
        private readonly array $terms,
        private readonly array $metadata,
    ) {
    }

    public static function default(): self
    {
        return new self(
            ['prefix' => '(', 'suffix' => ')', 'delimiter' => '; '],
            ['prefix' => '', 'suffix' => '', 'delimiter' => ' '],
            ['hangingIndent' => false, 'entrySpacing' => null, 'lineSpacing' => null, 'secondFieldAlign' => ''],
            [],
            [],
            self::DEFAULT_TERMS,
            ['title' => '', 'id' => '', 'class' => 'in-text', 'defaultLocale' => '']
        );
    }

    /**
     * @param list<string> $localeXmls
     */
    public static function fromXml(string $styleXml, array $localeXmls = []): self
    {
        $dom = self::loadXml($styleXml, 'CSL style XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'style') {
            throw new \InvalidArgumentException('CSL style XML root element must be style');
        }

        if ($root->namespaceURI !== self::CSL_NS) {
            throw new \InvalidArgumentException('CSL style XML root element must use the CSL namespace');
        }

        if ($root->getAttribute('version') !== '1.0') {
            throw new \InvalidArgumentException('CSL style XML must declare version 1.0');
        }

        $defaultLocale = trim($root->getAttribute('default-locale'));
        $terms = self::DEFAULT_TERMS;
        foreach ($localeXmls as $index => $localeXml) {
            if (!is_string($localeXml)) {
                throw new \InvalidArgumentException('CSL locale XML at index ' . $index . ' must be a string');
            }

            $terms = self::parseLocaleXmlTerms($localeXml, $terms);
        }

        foreach (self::directChildren($root, 'locale') as $locale) {
            if (self::localeMatches($locale, $defaultLocale)) {
                $terms = self::applyLocaleElementTerms($locale, $terms);
            }
        }

        $citation = self::directChild($root, 'citation');
        if (!$citation instanceof \DOMElement) {
            throw new \InvalidArgumentException('CSL style XML must contain a citation element');
        }

        $layout = self::directChild($citation, 'layout');
        if (!$layout instanceof \DOMElement) {
            throw new \InvalidArgumentException('CSL citation element must contain a layout element');
        }

        $bibliography = self::directChild($root, 'bibliography');
        $bibliographyLayoutElement = null;
        if ($bibliography instanceof \DOMElement) {
            $bibliographyLayoutElement = self::directChild($bibliography, 'layout');
            if (!$bibliographyLayoutElement instanceof \DOMElement) {
                throw new \InvalidArgumentException('CSL bibliography element must contain a layout element when present');
            }
        }

        $info = self::directChild($root, 'info');
        $metadata = [
            'title' => $info instanceof \DOMElement ? self::childText($info, 'title') : '',
            'id' => $info instanceof \DOMElement ? self::childText($info, 'id') : '',
            'class' => trim($root->getAttribute('class')),
            'defaultLocale' => $defaultLocale,
        ];

        return new self(
            self::layoutAttributes($layout, '; '),
            $bibliographyLayoutElement instanceof \DOMElement
                ? self::layoutAttributes($bibliographyLayoutElement, ' ')
                : ['prefix' => '', 'suffix' => '', 'delimiter' => ' '],
            $bibliography instanceof \DOMElement
                ? self::parseBibliographyOptions($bibliography)
                : ['hangingIndent' => false, 'entrySpacing' => null, 'lineSpacing' => null, 'secondFieldAlign' => ''],
            self::sortKeys($citation, 'citation'),
            $bibliography instanceof \DOMElement ? self::sortKeys($bibliography, 'bibliography') : [],
            $terms,
            $metadata
        );
    }

    public function citationPrefix(): string
    {
        return $this->citationLayout['prefix'];
    }

    public function citationSuffix(): string
    {
        return $this->citationLayout['suffix'];
    }

    public function citationDelimiter(): string
    {
        return $this->citationLayout['delimiter'];
    }

    public function bibliographyDelimiter(): string
    {
        return $this->bibliographyLayout['delimiter'];
    }

    public function formatBibliographyEntry(string $entry): string
    {
        if ($entry === '') {
            return '';
        }

        return $this->bibliographyLayout['prefix'] . $entry . $this->bibliographyLayout['suffix'];
    }

    /**
     * @return array{hangingIndent:bool, entrySpacing:int|null, lineSpacing:int|null, secondFieldAlign:string}
     */
    public function bibliographyOptions(): array
    {
        return $this->bibliographyOptions;
    }

    public function term(string $name, string $form = 'long', bool $plural = false): string
    {
        $key = self::termKey($name, $form);
        $term = $this->terms[$key] ?? $this->terms[self::termKey($name, 'long')] ?? null;
        if ($term === null) {
            return $name;
        }

        return $plural ? $term['multiple'] : $term['single'];
    }

    /**
     * @return list<array{sort:string, variable?:string, macro?:string}>
     */
    public function citationSortKeys(): array
    {
        return $this->citationSortKeys;
    }

    /**
     * @return list<array{sort:string, variable?:string, macro?:string}>
     */
    public function bibliographySortKeys(): array
    {
        return $this->bibliographySortKeys;
    }

    /**
     * @return array{title:string, id:string, class:string, defaultLocale:string, citationLayout:array{prefix:string, suffix:string, delimiter:string}, bibliographyLayout:array{prefix:string, suffix:string, delimiter:string}, bibliographyOptions:array{hangingIndent:bool, entrySpacing:int|null, lineSpacing:int|null, secondFieldAlign:string}, citationSort:list<array{sort:string, variable?:string, macro?:string}>, bibliographySort:list<array{sort:string, variable?:string, macro?:string}>, terms:array{and:string, etAl:string, noDate:string, accessed:string}}
     */
    public function summary(): array
    {
        return [
            ...$this->metadata,
            'citationLayout' => $this->citationLayout,
            'bibliographyLayout' => $this->bibliographyLayout,
            'bibliographyOptions' => $this->bibliographyOptions,
            'citationSort' => $this->citationSortKeys,
            'bibliographySort' => $this->bibliographySortKeys,
            'terms' => [
                'and' => $this->term('and'),
                'etAl' => $this->term('et-al'),
                'noDate' => $this->term('no date'),
                'accessed' => $this->term('accessed'),
            ],
        ];
    }

    /**
     * @return array{prefix:string, suffix:string, delimiter:string}
     */
    private static function layoutAttributes(\DOMElement $layout, string $defaultDelimiter): array
    {
        return [
            'prefix' => $layout->hasAttribute('prefix') ? $layout->getAttribute('prefix') : '',
            'suffix' => $layout->hasAttribute('suffix') ? $layout->getAttribute('suffix') : '',
            'delimiter' => $layout->hasAttribute('delimiter') ? $layout->getAttribute('delimiter') : $defaultDelimiter,
        ];
    }

    /**
     * @return array{hangingIndent:bool, entrySpacing:int|null, lineSpacing:int|null, secondFieldAlign:string}
     */
    private static function parseBibliographyOptions(\DOMElement $bibliography): array
    {
        return [
            'hangingIndent' => self::booleanAttribute($bibliography, 'hanging-indent', false),
            'entrySpacing' => self::integerAttribute($bibliography, 'entry-spacing'),
            'lineSpacing' => self::integerAttribute($bibliography, 'line-spacing'),
            'secondFieldAlign' => trim($bibliography->getAttribute('second-field-align')),
        ];
    }

    private static function booleanAttribute(\DOMElement $element, string $name, bool $default): bool
    {
        if (!$element->hasAttribute($name)) {
            return $default;
        }

        $value = strtolower(trim($element->getAttribute($name)));
        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        throw new \InvalidArgumentException('CSL bibliography attribute ' . $name . ' must be true or false');
    }

    private static function integerAttribute(\DOMElement $element, string $name): ?int
    {
        if (!$element->hasAttribute($name)) {
            return null;
        }

        $value = trim($element->getAttribute($name));
        if (preg_match('/^-?\d+$/', $value) !== 1) {
            throw new \InvalidArgumentException('CSL bibliography attribute ' . $name . ' must be an integer');
        }

        return (int) $value;
    }

    /**
     * @return list<array{sort:string, variable?:string, macro?:string}>
     */
    private static function sortKeys(\DOMElement $container, string $label): array
    {
        $sort = self::directChild($container, 'sort');
        if (!$sort instanceof \DOMElement) {
            return [];
        }

        $keys = [];
        foreach (self::directChildren($sort, 'key') as $keyElement) {
            $variable = trim($keyElement->getAttribute('variable'));
            $macro = trim($keyElement->getAttribute('macro'));
            if (($variable === '') === ($macro === '')) {
                throw new \InvalidArgumentException('CSL ' . $label . ' sort key must declare exactly one variable or macro');
            }

            $order = trim($keyElement->getAttribute('sort'));
            if ($order === '') {
                $order = 'ascending';
            }
            if ($order !== 'ascending' && $order !== 'descending') {
                throw new \InvalidArgumentException('CSL ' . $label . ' sort key sort must be ascending or descending');
            }

            $key = ['sort' => $order];
            if ($variable !== '') {
                $key['variable'] = $variable;
            } else {
                $key['macro'] = $macro;
            }
            $keys[] = $key;
        }

        if ($keys === []) {
            throw new \InvalidArgumentException('CSL ' . $label . ' sort element must contain at least one key');
        }

        return $keys;
    }

    /**
     * @param array<string, array{single:string, multiple:string}> $terms
     * @return array<string, array{single:string, multiple:string}>
     */
    private static function parseLocaleXmlTerms(string $localeXml, array $terms): array
    {
        $dom = self::loadXml($localeXml, 'CSL locale XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'locale') {
            throw new \InvalidArgumentException('CSL locale XML root element must be locale');
        }

        if ($root->namespaceURI !== self::CSL_NS) {
            throw new \InvalidArgumentException('CSL locale XML root element must use the CSL namespace');
        }

        if ($root->getAttribute('version') !== '1.0') {
            throw new \InvalidArgumentException('CSL locale XML must declare version 1.0');
        }

        return self::applyLocaleElementTerms($root, $terms);
    }

    /**
     * @param array<string, array{single:string, multiple:string}> $terms
     * @return array<string, array{single:string, multiple:string}>
     */
    private static function applyLocaleElementTerms(\DOMElement $locale, array $terms): array
    {
        $termsElement = self::directChild($locale, 'terms');
        if (!$termsElement instanceof \DOMElement) {
            return $terms;
        }

        foreach (self::directChildren($termsElement, 'term') as $termElement) {
            $name = trim($termElement->getAttribute('name'));
            if ($name === '') {
                throw new \InvalidArgumentException('CSL locale term is missing a name');
            }

            $form = trim($termElement->getAttribute('form'));
            if ($form === '') {
                $form = 'long';
            }

            $single = self::directChild($termElement, 'single');
            $multiple = self::directChild($termElement, 'multiple');
            if ($single instanceof \DOMElement || $multiple instanceof \DOMElement) {
                $singleText = $single instanceof \DOMElement ? self::elementText($single) : '';
                $multipleText = $multiple instanceof \DOMElement ? self::elementText($multiple) : '';
                $terms[self::termKey($name, $form)] = [
                    'single' => $singleText !== '' ? $singleText : $multipleText,
                    'multiple' => $multipleText !== '' ? $multipleText : $singleText,
                ];
                continue;
            }

            $text = self::elementText($termElement);
            $terms[self::termKey($name, $form)] = ['single' => $text, 'multiple' => $text];
        }

        return $terms;
    }

    private static function localeMatches(\DOMElement $locale, string $defaultLocale): bool
    {
        $lang = trim($locale->getAttributeNS(self::XML_NS, 'lang'));
        if ($lang === '') {
            $lang = trim($locale->getAttribute('xml:lang'));
        }

        if ($lang === '' || $defaultLocale === '') {
            return true;
        }

        $lang = strtolower($lang);
        $defaultLocale = strtolower($defaultLocale);
        if ($lang === $defaultLocale) {
            return true;
        }

        return strtok($lang, '-') === strtok($defaultLocale, '-');
    }

    private static function loadXml(string $xml, string $label): \DOMDocument
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            $detail = $errors === [] ? 'unknown XML parse error' : trim($errors[0]->message);
            throw new \InvalidArgumentException('Invalid ' . $label . ': ' . $detail);
        }

        return $dom;
    }

    private static function directChild(\DOMElement $element, string $localName): ?\DOMElement
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @return list<\DOMElement>
     */
    private static function directChildren(\DOMElement $element, string $localName): array
    {
        $children = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                $children[] = $child;
            }
        }

        return $children;
    }

    private static function childText(\DOMElement $element, string $localName): string
    {
        $child = self::directChild($element, $localName);

        return $child instanceof \DOMElement ? self::elementText($child) : '';
    }

    private static function elementText(\DOMElement $element): string
    {
        return trim(preg_replace('/\s+/u', ' ', $element->textContent) ?? $element->textContent);
    }

    private static function termKey(string $name, string $form): string
    {
        return strtolower(trim($name)) . '|' . strtolower(trim($form));
    }
}
