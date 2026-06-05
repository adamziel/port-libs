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
        'and others|long' => ['single' => 'and others', 'multiple' => 'and others'],
        'no date|long' => ['single' => 'n.d.', 'multiple' => 'n.d.'],
        'accessed|long' => ['single' => 'Accessed', 'multiple' => 'Accessed'],
        'open-quote|long' => ['single' => "\u{201C}", 'multiple' => "\u{201C}"],
        'close-quote|long' => ['single' => "\u{201D}", 'multiple' => "\u{201D}"],
        'page|long' => ['single' => 'page', 'multiple' => 'pages'],
        'page|short' => ['single' => 'p.', 'multiple' => 'pp.'],
        'chapter|long' => ['single' => 'chapter', 'multiple' => 'chapters'],
        'chapter|short' => ['single' => 'chap.', 'multiple' => 'chaps.'],
        'section|long' => ['single' => 'section', 'multiple' => 'sections'],
        'section|short' => ['single' => 'sec.', 'multiple' => 'secs.'],
        'section|symbol' => ['single' => "\u{00A7}", 'multiple' => "\u{00A7}\u{00A7}"],
        'paragraph|long' => ['single' => 'paragraph', 'multiple' => 'paragraphs'],
        'paragraph|short' => ['single' => 'para.', 'multiple' => 'paras.'],
        'paragraph|symbol' => ['single' => "\u{00B6}", 'multiple' => "\u{00B6}\u{00B6}"],
        'volume|long' => ['single' => 'volume', 'multiple' => 'volumes'],
        'volume|short' => ['single' => 'vol.', 'multiple' => 'vols.'],
        'issue|long' => ['single' => 'issue', 'multiple' => 'issues'],
        'issue|short' => ['single' => 'no.', 'multiple' => 'nos.'],
        'number|long' => ['single' => 'number', 'multiple' => 'numbers'],
        'number|short' => ['single' => 'no.', 'multiple' => 'nos.'],
        'edition|long' => ['single' => 'edition', 'multiple' => 'editions'],
        'edition|short' => ['single' => 'ed.', 'multiple' => 'eds.'],
        'ordinal|long' => ['single' => 'th', 'multiple' => 'th'],
        'ordinal-01|long' => ['single' => 'st', 'multiple' => 'st'],
        'ordinal-02|long' => ['single' => 'nd', 'multiple' => 'nd'],
        'ordinal-03|long' => ['single' => 'rd', 'multiple' => 'rd'],
        'long-ordinal-01|long' => ['single' => 'first', 'multiple' => 'first'],
        'long-ordinal-02|long' => ['single' => 'second', 'multiple' => 'second'],
        'long-ordinal-03|long' => ['single' => 'third', 'multiple' => 'third'],
        'long-ordinal-04|long' => ['single' => 'fourth', 'multiple' => 'fourth'],
        'long-ordinal-05|long' => ['single' => 'fifth', 'multiple' => 'fifth'],
        'long-ordinal-06|long' => ['single' => 'sixth', 'multiple' => 'sixth'],
        'long-ordinal-07|long' => ['single' => 'seventh', 'multiple' => 'seventh'],
        'long-ordinal-08|long' => ['single' => 'eighth', 'multiple' => 'eighth'],
        'long-ordinal-09|long' => ['single' => 'ninth', 'multiple' => 'ninth'],
        'long-ordinal-10|long' => ['single' => 'tenth', 'multiple' => 'tenth'],
        'month-01|long' => ['single' => 'January', 'multiple' => 'January'],
        'month-01|short' => ['single' => 'Jan.', 'multiple' => 'Jan.'],
        'month-02|long' => ['single' => 'February', 'multiple' => 'February'],
        'month-02|short' => ['single' => 'Feb.', 'multiple' => 'Feb.'],
        'month-03|long' => ['single' => 'March', 'multiple' => 'March'],
        'month-03|short' => ['single' => 'Mar.', 'multiple' => 'Mar.'],
        'month-04|long' => ['single' => 'April', 'multiple' => 'April'],
        'month-04|short' => ['single' => 'Apr.', 'multiple' => 'Apr.'],
        'month-05|long' => ['single' => 'May', 'multiple' => 'May'],
        'month-05|short' => ['single' => 'May', 'multiple' => 'May'],
        'month-06|long' => ['single' => 'June', 'multiple' => 'June'],
        'month-06|short' => ['single' => 'Jun.', 'multiple' => 'Jun.'],
        'month-07|long' => ['single' => 'July', 'multiple' => 'July'],
        'month-07|short' => ['single' => 'Jul.', 'multiple' => 'Jul.'],
        'month-08|long' => ['single' => 'August', 'multiple' => 'August'],
        'month-08|short' => ['single' => 'Aug.', 'multiple' => 'Aug.'],
        'month-09|long' => ['single' => 'September', 'multiple' => 'September'],
        'month-09|short' => ['single' => 'Sep.', 'multiple' => 'Sep.'],
        'month-10|long' => ['single' => 'October', 'multiple' => 'October'],
        'month-10|short' => ['single' => 'Oct.', 'multiple' => 'Oct.'],
        'month-11|long' => ['single' => 'November', 'multiple' => 'November'],
        'month-11|short' => ['single' => 'Nov.', 'multiple' => 'Nov.'],
        'month-12|long' => ['single' => 'December', 'multiple' => 'December'],
        'month-12|short' => ['single' => 'Dec.', 'multiple' => 'Dec.'],
    ];

    /** @var array{citation:array<string, mixed>, bibliography:array<string, mixed>} */
    private const DEFAULT_NAME_RENDERING = [
        'citation' => [
            'delimiter' => ', ',
            'and' => 'text',
            'etAlMin' => 3,
            'etAlUseFirst' => 1,
            'delimiterPrecedesEtAl' => 'contextual',
            'etAl' => [
                'term' => 'et-al',
                'prefix' => '',
                'suffix' => '',
                'textCase' => '',
                'stripPeriods' => false,
                'quotes' => false,
            ],
            'initializeWith' => null,
            'nameAsSortOrder' => 'first',
            'nameParts' => [],
        ],
        'bibliography' => [
            'delimiter' => '; ',
            'and' => 'text',
            'etAlMin' => null,
            'etAlUseFirst' => 1,
            'delimiterPrecedesEtAl' => 'contextual',
            'etAl' => [
                'term' => 'et-al',
                'prefix' => '',
                'suffix' => '',
                'textCase' => '',
                'stripPeriods' => false,
                'quotes' => false,
            ],
            'initializeWith' => null,
            'nameAsSortOrder' => 'all',
            'nameParts' => [],
        ],
    ];

    /**
     * @param array{prefix:string, suffix:string, delimiter:string} $citationLayout
     * @param array{prefix:string, suffix:string, delimiter:string} $bibliographyLayout
     * @param array{hangingIndent:bool, entrySpacing:int|null, lineSpacing:int|null, secondFieldAlign:string, subsequentAuthorSubstitute:string, subsequentAuthorSubstituteRule:string} $bibliographyOptions
     * @param array{disambiguateAddYearSuffix:bool, collapse:string} $citationOptions
     * @param list<array{sort:string, variable?:string, macro?:string}> $citationSortKeys
     * @param list<array{sort:string, variable?:string, macro?:string}> $bibliographySortKeys
     * @param list<array<string, mixed>> $citationRenderingElements
     * @param list<array<string, mixed>> $bibliographyRenderingElements
     * @param array<string, list<array<string, mixed>>> $macros
     * @param array{citation:array<string, mixed>, bibliography:array<string, mixed>} $nameRendering
     * @param array<string, array{single:string, multiple:string}> $terms
     * @param array{title:string, id:string, class:string, defaultLocale:string} $metadata
     */
    private function __construct(
        private readonly array $citationLayout,
        private readonly array $bibliographyLayout,
        private readonly array $bibliographyOptions,
        private readonly array $citationOptions,
        private readonly array $citationSortKeys,
        private readonly array $bibliographySortKeys,
        private readonly array $citationRenderingElements,
        private readonly array $bibliographyRenderingElements,
        private readonly array $macros,
        private readonly array $nameRendering,
        private readonly array $terms,
        private readonly array $metadata,
    ) {
    }

    public static function default(): self
    {
        return new self(
            ['prefix' => '(', 'suffix' => ')', 'delimiter' => '; '],
            ['prefix' => '', 'suffix' => '', 'delimiter' => ' '],
            ['hangingIndent' => false, 'entrySpacing' => null, 'lineSpacing' => null, 'secondFieldAlign' => '', 'subsequentAuthorSubstitute' => '', 'subsequentAuthorSubstituteRule' => 'complete-all'],
            ['disambiguateAddYearSuffix' => false, 'collapse' => ''],
            [],
            [],
            [],
            [],
            [],
            self::DEFAULT_NAME_RENDERING,
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
        $macros = self::parseMacros($root);
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

        $citationRenderingElements = self::renderingElements($layout, 'citation');
        $bibliographyRenderingElements = $bibliographyLayoutElement instanceof \DOMElement
            ? self::renderingElements($bibliographyLayoutElement, 'bibliography')
            : [];
        self::validateMacroReferences($citationRenderingElements, $bibliographyRenderingElements, $macros);
        $citationNameRendering = self::firstAuthorEditorNamesElement($layout) instanceof \DOMElement
            ? self::nameRenderingOptions($layout, 'citation')
            : (self::nameRenderingOptionsForRenderingElements($citationRenderingElements, 'citation', $macros) ?? self::DEFAULT_NAME_RENDERING['citation']);
        $bibliographyNameRendering = $bibliographyLayoutElement instanceof \DOMElement
            ? (
                self::firstAuthorEditorNamesElement($bibliographyLayoutElement) instanceof \DOMElement
                    ? self::nameRenderingOptions($bibliographyLayoutElement, 'bibliography')
                    : (self::nameRenderingOptionsForRenderingElements($bibliographyRenderingElements, 'bibliography', $macros) ?? self::DEFAULT_NAME_RENDERING['bibliography'])
            )
            : self::DEFAULT_NAME_RENDERING['bibliography'];

        return new self(
            self::layoutAttributes($layout, '; '),
            $bibliographyLayoutElement instanceof \DOMElement
                ? self::layoutAttributes($bibliographyLayoutElement, ' ')
                : ['prefix' => '', 'suffix' => '', 'delimiter' => ' '],
            $bibliography instanceof \DOMElement
                ? self::parseBibliographyOptions($bibliography)
                : ['hangingIndent' => false, 'entrySpacing' => null, 'lineSpacing' => null, 'secondFieldAlign' => '', 'subsequentAuthorSubstitute' => '', 'subsequentAuthorSubstituteRule' => 'complete-all'],
            self::parseCitationOptions($citation),
            self::sortKeys($citation, 'citation'),
            $bibliography instanceof \DOMElement ? self::sortKeys($bibliography, 'bibliography') : [],
            $citationRenderingElements,
            $bibliographyRenderingElements,
            $macros,
            [
                'citation' => $citationNameRendering,
                'bibliography' => $bibliographyNameRendering,
            ],
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
     * @return array{hangingIndent:bool, entrySpacing:int|null, lineSpacing:int|null, secondFieldAlign:string, subsequentAuthorSubstitute:string, subsequentAuthorSubstituteRule:string}
     */
    public function bibliographyOptions(): array
    {
        return $this->bibliographyOptions;
    }

    /**
     * @return array{disambiguateAddYearSuffix:bool, collapse:string}
     */
    public function citationOptions(): array
    {
        return $this->citationOptions;
    }

    public function term(string $name, string $form = 'long', bool $plural = false): string
    {
        return $this->termOrNull($name, $form, $plural) ?? $name;
    }

    public function termOrNull(string $name, string $form = 'long', bool $plural = false): ?string
    {
        foreach (self::termFallbackKeys($name, $form) as $key) {
            $term = $this->terms[$key] ?? null;
            if ($term !== null) {
                return $plural ? $term['multiple'] : $term['single'];
            }
        }

        return null;
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
     * @return list<array<string, mixed>>
     */
    public function citationRenderingElements(): array
    {
        return $this->citationRenderingElements;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function bibliographyRenderingElements(): array
    {
        return $this->bibliographyRenderingElements;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function macros(): array
    {
        return $this->macros;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function macroRenderingElements(string $name): ?array
    {
        return $this->macros[$name] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function citationNameRendering(): array
    {
        return $this->nameRendering['citation'];
    }

    /**
     * @return array<string, mixed>
     */
    public function bibliographyNameRendering(): array
    {
        return $this->nameRendering['bibliography'];
    }

    public function defaultLocale(): string
    {
        return $this->metadata['defaultLocale'];
    }

    /**
     * @return array{title:string, id:string, class:string, defaultLocale:string, citationLayout:array{prefix:string, suffix:string, delimiter:string}, bibliographyLayout:array{prefix:string, suffix:string, delimiter:string}, bibliographyOptions:array{hangingIndent:bool, entrySpacing:int|null, lineSpacing:int|null, secondFieldAlign:string, subsequentAuthorSubstitute:string, subsequentAuthorSubstituteRule:string}, citationOptions:array{disambiguateAddYearSuffix:bool, collapse:string}, citationSort:list<array{sort:string, variable?:string, macro?:string}>, bibliographySort:list<array{sort:string, variable?:string, macro?:string}>, citationRendering:list<array<string, mixed>>, bibliographyRendering:list<array<string, mixed>>, macros:array<string, list<array<string, mixed>>>, nameRendering:array{citation:array<string, mixed>, bibliography:array<string, mixed>}, terms:array{and:string, etAl:string, noDate:string, accessed:string}}
     */
    public function summary(): array
    {
        return [
            ...$this->metadata,
            'citationLayout' => $this->citationLayout,
            'bibliographyLayout' => $this->bibliographyLayout,
            'bibliographyOptions' => $this->bibliographyOptions,
            'citationOptions' => $this->citationOptions,
            'citationSort' => $this->citationSortKeys,
            'bibliographySort' => $this->bibliographySortKeys,
            'citationRendering' => $this->citationRenderingElements,
            'bibliographyRendering' => $this->bibliographyRenderingElements,
            'macros' => $this->macros,
            'nameRendering' => $this->nameRendering,
            'terms' => [
                'and' => $this->term('and'),
                'etAl' => $this->term('et-al'),
                'noDate' => $this->term('no date'),
                'accessed' => $this->term('accessed'),
            ],
        ];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private static function parseMacros(\DOMElement $root): array
    {
        $macros = [];
        foreach (self::directChildren($root, 'macro') as $macro) {
            $name = trim($macro->getAttribute('name'));
            if ($name === '') {
                throw new \InvalidArgumentException('CSL macro element is missing a name');
            }

            if (array_key_exists($name, $macros)) {
                throw new \InvalidArgumentException('Duplicate CSL macro name: ' . $name);
            }

            $macros[$name] = self::renderingElements($macro, 'macro ' . $name);
        }

        return $macros;
    }

    /**
     * @param list<array<string, mixed>> $citationElements
     * @param list<array<string, mixed>> $bibliographyElements
     * @param array<string, list<array<string, mixed>>> $macros
     */
    private static function validateMacroReferences(array $citationElements, array $bibliographyElements, array $macros): void
    {
        self::validateRenderingMacroReferences($citationElements, $macros, [], 'citation');
        self::validateRenderingMacroReferences($bibliographyElements, $macros, [], 'bibliography');
        foreach (array_keys($macros) as $name) {
            self::validateRenderingMacroReferences($macros[$name], $macros, [$name], 'macro ' . $name);
        }
    }

    /**
     * @param list<array<string, mixed>> $elements
     * @param array<string, list<array<string, mixed>>> $macros
     * @param list<string> $stack
     */
    private static function validateRenderingMacroReferences(array $elements, array $macros, array $stack, string $context): void
    {
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            if (($element['type'] ?? '') === 'text' && array_key_exists('macro', $element)) {
                $name = (string) $element['macro'];
                if (!array_key_exists($name, $macros)) {
                    throw new \InvalidArgumentException('CSL ' . $context . ' references undefined macro: ' . $name);
                }

                if (in_array($name, $stack, true)) {
                    throw new \InvalidArgumentException('CSL macro recursion detected: ' . implode(' -> ', [...$stack, $name]));
                }

                self::validateRenderingMacroReferences($macros[$name], $macros, [...$stack, $name], 'macro ' . $name);
            }

            if (($element['type'] ?? '') === 'names' && isset($element['substitute']) && is_array($element['substitute'])) {
                self::validateRenderingMacroReferences($element['substitute'], $macros, $stack, $context);
            }

            if (($element['type'] ?? '') === 'group' && isset($element['children']) && is_array($element['children'])) {
                self::validateRenderingMacroReferences($element['children'], $macros, $stack, $context);
            }

            if (($element['type'] ?? '') === 'choose') {
                foreach (($element['branches'] ?? []) as $branch) {
                    if (is_array($branch) && isset($branch['children']) && is_array($branch['children'])) {
                        self::validateRenderingMacroReferences($branch['children'], $macros, $stack, $context);
                    }
                }

                if (isset($element['else']) && is_array($element['else'])) {
                    self::validateRenderingMacroReferences($element['else'], $macros, $stack, $context);
                }
            }
        }
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
     * @return array{hangingIndent:bool, entrySpacing:int|null, lineSpacing:int|null, secondFieldAlign:string, subsequentAuthorSubstitute:string, subsequentAuthorSubstituteRule:string}
     */
    private static function parseBibliographyOptions(\DOMElement $bibliography): array
    {
        $substituteRule = trim($bibliography->getAttribute('subsequent-author-substitute-rule'));
        if ($substituteRule === '') {
            $substituteRule = 'complete-all';
        }
        if (!in_array($substituteRule, ['complete-all', 'complete-each', 'partial-each', 'partial-first'], true)) {
            throw new \InvalidArgumentException('CSL bibliography attribute subsequent-author-substitute-rule must be complete-all, complete-each, partial-each, or partial-first');
        }

        return [
            'hangingIndent' => self::booleanAttribute($bibliography, 'hanging-indent', false),
            'entrySpacing' => self::integerAttribute($bibliography, 'entry-spacing'),
            'lineSpacing' => self::integerAttribute($bibliography, 'line-spacing'),
            'secondFieldAlign' => trim($bibliography->getAttribute('second-field-align')),
            'subsequentAuthorSubstitute' => $bibliography->hasAttribute('subsequent-author-substitute') ? $bibliography->getAttribute('subsequent-author-substitute') : '',
            'subsequentAuthorSubstituteRule' => $substituteRule,
        ];
    }

    /**
     * @return array{disambiguateAddYearSuffix:bool, collapse:string}
     */
    private static function parseCitationOptions(\DOMElement $citation): array
    {
        $collapse = trim($citation->getAttribute('collapse'));
        if ($collapse !== '' && !in_array($collapse, ['citation-number', 'year', 'year-suffix', 'year-suffix-ranged'], true)) {
            throw new \InvalidArgumentException('CSL citation attribute collapse must be citation-number, year, year-suffix, or year-suffix-ranged');
        }

        return [
            'disambiguateAddYearSuffix' => self::booleanAttribute($citation, 'disambiguate-add-year-suffix', false, 'citation'),
            'collapse' => $collapse,
        ];
    }

    private static function booleanAttribute(\DOMElement $element, string $name, bool $default, string $context = 'bibliography'): bool
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

        throw new \InvalidArgumentException('CSL ' . $context . ' attribute ' . $name . ' must be true or false');
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
     * @return array<string, mixed>
     */
    private static function nameRenderingOptions(\DOMElement $layout, string $scope): array
    {
        $names = self::firstAuthorEditorNamesElement($layout);
        if (!$names instanceof \DOMElement) {
            return self::DEFAULT_NAME_RENDERING[$scope];
        }

        return self::nameRenderingOptionsFromNames($names, $scope);
    }

    /**
     * @return array<string, mixed>
     */
    private static function nameRenderingOptionsFromNames(\DOMElement $names, string $scope): array
    {
        $defaults = self::DEFAULT_NAME_RENDERING[$scope];
        $overrides = self::nameRenderingOverridesFromNames($names, $scope);

        return self::mergeNameRenderingOptions($defaults, $overrides);
    }

    /**
     * @param list<array<string, mixed>> $elements
     * @param array<string, list<array<string, mixed>>> $macros
     * @param list<string> $stack
     * @return array<string, mixed>|null
     */
    private static function nameRenderingOptionsForRenderingElements(array $elements, string $scope, array $macros, array $stack = []): ?array
    {
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            $type = (string) ($element['type'] ?? '');
            if ($type === 'names' && self::renderingNamesElementIncludesAuthorEditor($element)) {
                $overrides = is_array($element['nameRendering'] ?? null) ? $element['nameRendering'] : [];

                return self::mergeNameRenderingOptions(self::DEFAULT_NAME_RENDERING[$scope], $overrides);
            }

            if ($type === 'group' && isset($element['children']) && is_array($element['children'])) {
                $options = self::nameRenderingOptionsForRenderingElements($element['children'], $scope, $macros, $stack);
                if ($options !== null) {
                    return $options;
                }
            }

            if ($type === 'choose') {
                foreach (($element['branches'] ?? []) as $branch) {
                    if (!is_array($branch) || !isset($branch['children']) || !is_array($branch['children'])) {
                        continue;
                    }

                    $options = self::nameRenderingOptionsForRenderingElements($branch['children'], $scope, $macros, $stack);
                    if ($options !== null) {
                        return $options;
                    }
                }

                if (isset($element['else']) && is_array($element['else'])) {
                    $options = self::nameRenderingOptionsForRenderingElements($element['else'], $scope, $macros, $stack);
                    if ($options !== null) {
                        return $options;
                    }
                }
            }

            if ($type === 'text' && isset($element['macro']) && is_string($element['macro'])) {
                $name = $element['macro'];
                if (isset($macros[$name]) && !in_array($name, $stack, true)) {
                    $options = self::nameRenderingOptionsForRenderingElements($macros[$name], $scope, $macros, [...$stack, $name]);
                    if ($options !== null) {
                        return $options;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $element
     */
    private static function renderingNamesElementIncludesAuthorEditor(array $element): bool
    {
        $variable = strtolower(trim((string) ($element['variable'] ?? 'author editor')));
        if ($variable === '') {
            return true;
        }

        $variables = preg_split('/\s+/', $variable) ?: [];

        return in_array('author', $variables, true) || in_array('editor', $variables, true);
    }

    /**
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private static function mergeNameRenderingOptions(array $defaults, array $overrides): array
    {
        return [
            'delimiter' => is_string($overrides['delimiter'] ?? null) ? $overrides['delimiter'] : $defaults['delimiter'],
            'and' => is_string($overrides['and'] ?? null) ? $overrides['and'] : $defaults['and'],
            'etAlMin' => is_int($overrides['etAlMin'] ?? null) ? $overrides['etAlMin'] : $defaults['etAlMin'],
            'etAlUseFirst' => is_int($overrides['etAlUseFirst'] ?? null) ? $overrides['etAlUseFirst'] : $defaults['etAlUseFirst'],
            'delimiterPrecedesEtAl' => is_string($overrides['delimiterPrecedesEtAl'] ?? null) ? $overrides['delimiterPrecedesEtAl'] : $defaults['delimiterPrecedesEtAl'],
            'etAl' => self::mergeEtAlRenderingOptions(
                is_array($defaults['etAl'] ?? null) ? $defaults['etAl'] : [],
                is_array($overrides['etAl'] ?? null) ? $overrides['etAl'] : []
            ),
            'initializeWith' => is_string($overrides['initializeWith'] ?? null) ? $overrides['initializeWith'] : $defaults['initializeWith'],
            'nameAsSortOrder' => is_string($overrides['nameAsSortOrder'] ?? null) ? $overrides['nameAsSortOrder'] : $defaults['nameAsSortOrder'],
            'nameParts' => is_array($overrides['nameParts'] ?? null) ? $overrides['nameParts'] : ($defaults['nameParts'] ?? []),
        ];
    }

    /**
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $overrides
     * @return array{term:string, prefix:string, suffix:string, textCase:string, stripPeriods:bool, quotes:bool}
     */
    private static function mergeEtAlRenderingOptions(array $defaults, array $overrides): array
    {
        return [
            'term' => is_string($overrides['term'] ?? null) ? $overrides['term'] : (is_string($defaults['term'] ?? null) ? $defaults['term'] : 'et-al'),
            'prefix' => is_string($overrides['prefix'] ?? null) ? $overrides['prefix'] : (is_string($defaults['prefix'] ?? null) ? $defaults['prefix'] : ''),
            'suffix' => is_string($overrides['suffix'] ?? null) ? $overrides['suffix'] : (is_string($defaults['suffix'] ?? null) ? $defaults['suffix'] : ''),
            'textCase' => is_string($overrides['textCase'] ?? null) ? $overrides['textCase'] : (is_string($defaults['textCase'] ?? null) ? $defaults['textCase'] : ''),
            'stripPeriods' => is_bool($overrides['stripPeriods'] ?? null) ? $overrides['stripPeriods'] : (is_bool($defaults['stripPeriods'] ?? null) ? $defaults['stripPeriods'] : false),
            'quotes' => is_bool($overrides['quotes'] ?? null) ? $overrides['quotes'] : (is_bool($defaults['quotes'] ?? null) ? $defaults['quotes'] : false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function nameRenderingOverridesFromNames(\DOMElement $names, string $scope): array
    {
        $name = self::directChild($names, 'name');
        $overrides = [];
        if ($names->hasAttribute('delimiter')) {
            $overrides['delimiter'] = $names->getAttribute('delimiter');
        }

        $and = self::optionalNameAttribute($name, $names, 'and');
        if ($and !== null) {
            $overrides['and'] = $and;
        }
        if (!in_array($and, ['text', 'symbol', 'none'], true)) {
            if ($and !== null) {
                throw new \InvalidArgumentException('CSL ' . $scope . ' name and attribute must be text, symbol, or none');
            }
        }

        $nameAsSortOrder = self::optionalNameAttribute($name, $names, 'name-as-sort-order');
        if ($nameAsSortOrder !== null) {
            $overrides['nameAsSortOrder'] = $nameAsSortOrder;
        }
        if ($nameAsSortOrder !== null && !in_array($nameAsSortOrder, ['first', 'all'], true)) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' name-as-sort-order must be first or all');
        }

        $etAlMin = self::positiveIntegerNameAttribute($names, $name, 'et-al-min', $scope);
        $etAlUseFirst = self::positiveIntegerNameAttribute($names, $name, 'et-al-use-first', $scope);
        if ($etAlMin !== null) {
            $overrides['etAlMin'] = $etAlMin;
        }
        if ($etAlUseFirst !== null) {
            $overrides['etAlUseFirst'] = $etAlUseFirst;
        }

        $delimiterPrecedesEtAl = self::optionalNameAttribute($name, $names, 'delimiter-precedes-et-al');
        if ($delimiterPrecedesEtAl !== null) {
            if (!in_array($delimiterPrecedesEtAl, ['contextual', 'after-inverted-name', 'always', 'never'], true)) {
                throw new \InvalidArgumentException('CSL ' . $scope . ' delimiter-precedes-et-al must be contextual, after-inverted-name, always, or never');
            }

            $overrides['delimiterPrecedesEtAl'] = $delimiterPrecedesEtAl;
        }

        $etAl = self::etAlRenderingOptions($names, $scope);
        if ($etAl !== []) {
            $overrides['etAl'] = $etAl;
        }

        if ($name instanceof \DOMElement && $name->hasAttribute('initialize-with')) {
            $overrides['initializeWith'] = $name->getAttribute('initialize-with');
        }
        if ($name instanceof \DOMElement) {
            $nameParts = self::namePartRenderingOptions($name, $scope);
            if ($nameParts !== []) {
                $overrides['nameParts'] = $nameParts;
            }
        }

        return $overrides;
    }

    /**
     * @return array{term:string, prefix:string, suffix:string, textCase:string, stripPeriods:bool, quotes:bool}|array{}
     */
    private static function etAlRenderingOptions(\DOMElement $names, string $scope): array
    {
        $etAlElements = self::directChildren($names, 'et-al');
        if ($etAlElements === []) {
            return [];
        }

        if (count($etAlElements) > 1) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' names element may contain at most one et-al element');
        }

        $etAl = $etAlElements[0];
        $term = trim($etAl->getAttribute('term'));
        if ($term === '') {
            $term = 'et-al';
        }
        if (!in_array($term, ['et-al', 'and others'], true)) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' et-al term must be et-al or and others');
        }

        return [
            'term' => $term,
            'prefix' => self::optionalAttribute($etAl, 'prefix'),
            'suffix' => self::optionalAttribute($etAl, 'suffix'),
            'textCase' => self::textCaseAttribute($etAl, $scope),
            'stripPeriods' => self::booleanRenderingAttribute($etAl, 'strip-periods', false, $scope),
            'quotes' => self::booleanRenderingAttribute($etAl, 'quotes', false, $scope),
        ];
    }

    /**
     * @return array<string, array{prefix:string, suffix:string, textCase:string, stripPeriods:bool, quotes:bool}>
     */
    private static function namePartRenderingOptions(\DOMElement $name, string $scope): array
    {
        $parts = [];
        foreach (self::directChildren($name, 'name-part') as $namePart) {
            $partName = strtolower(trim($namePart->getAttribute('name')));
            if (!in_array($partName, ['family', 'given'], true)) {
                throw new \InvalidArgumentException('CSL ' . $scope . ' name-part name must be family or given');
            }

            if (array_key_exists($partName, $parts)) {
                throw new \InvalidArgumentException('Duplicate CSL ' . $scope . ' name-part formatter: ' . $partName);
            }

            $parts[$partName] = [
                'prefix' => self::optionalAttribute($namePart, 'prefix'),
                'suffix' => self::optionalAttribute($namePart, 'suffix'),
                'textCase' => self::textCaseAttribute($namePart, $scope),
                'stripPeriods' => self::booleanRenderingAttribute($namePart, 'strip-periods', false, $scope),
                'quotes' => self::booleanRenderingAttribute($namePart, 'quotes', false, $scope),
            ];
        }

        return $parts;
    }

    private static function optionalNameAttribute(?\DOMElement $name, \DOMElement $names, string $attribute): ?string
    {
        if ($name instanceof \DOMElement && $name->hasAttribute($attribute)) {
            return trim($name->getAttribute($attribute));
        }

        if ($names->hasAttribute($attribute)) {
            return trim($names->getAttribute($attribute));
        }

        return null;
    }

    private static function positiveIntegerNameAttribute(\DOMElement $names, ?\DOMElement $name, string $attribute, string $scope): ?int
    {
        $source = null;
        if ($names->hasAttribute($attribute)) {
            $source = $names;
        } elseif ($name instanceof \DOMElement && $name->hasAttribute($attribute)) {
            $source = $name;
        }

        if (!$source instanceof \DOMElement) {
            return null;
        }

        $value = trim($source->getAttribute($attribute));
        if (preg_match('/^\d+$/', $value) !== 1 || (int) $value < 1) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' name attribute ' . $attribute . ' must be a positive integer');
        }

        return (int) $value;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function renderingElements(\DOMElement $container, string $scope): array
    {
        $elements = [];
        foreach ($container->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $element = self::renderingElement($child, $scope);
            if ($element !== null) {
                $elements[] = $element;
            }
        }

        return $elements;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function renderingElement(\DOMElement $element, string $scope): ?array
    {
        return match ($element->localName) {
            'group' => self::groupRenderingElement($element, $scope),
            'text' => self::textRenderingElement($element, $scope),
            'date' => self::dateRenderingElement($element, $scope),
            'number' => self::numberRenderingElement($element, $scope),
            'names' => self::namesRenderingElement($element, $scope),
            'label' => self::labelRenderingElement($element, $scope),
            'choose' => self::chooseRenderingElement($element, $scope),
            default => null,
        };
    }

    /**
     * @return array{type:string, prefix:string, suffix:string, delimiter:string, children:list<array<string, mixed>>}
     */
    private static function groupRenderingElement(\DOMElement $group, string $scope): array
    {
        return [
            'type' => 'group',
            'prefix' => self::optionalAttribute($group, 'prefix'),
            'suffix' => self::optionalAttribute($group, 'suffix'),
            'delimiter' => self::optionalAttribute($group, 'delimiter'),
            'display' => self::displayAttribute($group, $scope),
            'children' => self::renderingElements($group, $scope),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function textRenderingElement(\DOMElement $text, string $scope): array
    {
        $variable = trim($text->getAttribute('variable'));
        $term = trim($text->getAttribute('term'));
        $value = trim($text->getAttribute('value'));
        $macro = trim($text->getAttribute('macro'));
        $declared = array_filter([$variable, $term, $value, $macro], static fn (string $attribute): bool => $attribute !== '');
        if (count($declared) !== 1) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' text element must declare exactly one variable, term, value, or macro');
        }

        $element = [
            'type' => 'text',
            'prefix' => self::optionalAttribute($text, 'prefix'),
            'suffix' => self::optionalAttribute($text, 'suffix'),
            'form' => self::optionalAttribute($text, 'form') !== '' ? self::optionalAttribute($text, 'form') : 'long',
            'plural' => self::booleanRenderingAttribute($text, 'plural', false, $scope),
            'quotes' => self::booleanRenderingAttribute($text, 'quotes', false, $scope),
            'stripPeriods' => self::booleanRenderingAttribute($text, 'strip-periods', false, $scope),
            'textCase' => self::textCaseAttribute($text, $scope),
            'display' => self::displayAttribute($text, $scope),
        ];
        if ($variable !== '') {
            $element['variable'] = $variable;
        } elseif ($term !== '') {
            $element['term'] = $term;
        } elseif ($value !== '') {
            $element['value'] = $value;
        } else {
            $element['macro'] = $macro;
        }

        return $element;
    }

    /**
     * @return array{type:string, prefix:string, suffix:string, variable:string, form:string, delimiter:string, dateParts:list<array{name:string, prefix:string, suffix:string, form:string, rangeDelimiter:string, stripPeriods:bool, textCase:string}>}
     */
    private static function dateRenderingElement(\DOMElement $date, string $scope): array
    {
        $variable = trim($date->getAttribute('variable'));
        if ($variable === '') {
            throw new \InvalidArgumentException('CSL ' . $scope . ' date element must declare a variable');
        }

        $form = strtolower(trim($date->getAttribute('form')));
        if ($form !== '' && !in_array($form, ['text', 'numeric'], true)) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' date form must be text or numeric');
        }

        $dateParts = [];
        foreach (self::directChildren($date, 'date-part') as $datePart) {
            $name = strtolower(trim($datePart->getAttribute('name')));
            if (!in_array($name, ['year', 'month', 'day'], true)) {
                throw new \InvalidArgumentException('CSL ' . $scope . ' date-part name must be year, month, or day');
            }

            $form = strtolower(trim($datePart->getAttribute('form')));
            if ($form !== '' && !self::datePartFormIsSupported($name, $form)) {
                throw new \InvalidArgumentException('CSL ' . $scope . ' date-part ' . $name . ' form is not supported: ' . $form);
            }

            $dateParts[] = [
                'name' => $name,
                'prefix' => self::optionalAttribute($datePart, 'prefix'),
                'suffix' => self::optionalAttribute($datePart, 'suffix'),
                'form' => $form,
                'rangeDelimiter' => self::optionalAttribute($datePart, 'range-delimiter'),
                'stripPeriods' => self::booleanRenderingAttribute($datePart, 'strip-periods', false, $scope),
                'textCase' => self::textCaseAttribute($datePart, $scope),
            ];
        }

        return [
            'type' => 'date',
            'prefix' => self::optionalAttribute($date, 'prefix'),
            'suffix' => self::optionalAttribute($date, 'suffix'),
            'variable' => $variable,
            'form' => $form,
            'delimiter' => self::optionalAttribute($date, 'delimiter'),
            'dateParts' => $dateParts,
            'textCase' => self::textCaseAttribute($date, $scope),
            'display' => self::displayAttribute($date, $scope),
        ];
    }

    private static function datePartFormIsSupported(string $name, string $form): bool
    {
        return match ($name) {
            'day' => in_array($form, ['numeric', 'numeric-leading-zeros', 'ordinal'], true),
            'month' => in_array($form, ['long', 'short', 'numeric', 'numeric-leading-zeros'], true),
            'year' => in_array($form, ['long', 'short'], true),
            default => false,
        };
    }

    /**
     * @return array{type:string, prefix:string, suffix:string, variable:string, form:string}
     */
    private static function numberRenderingElement(\DOMElement $number, string $scope): array
    {
        $variable = strtolower(trim($number->getAttribute('variable')));
        if ($variable === '') {
            throw new \InvalidArgumentException('CSL ' . $scope . ' number element must declare a variable');
        }

        if (!in_array($variable, self::supportedNumberVariables(), true)) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' number variable is not supported: ' . $variable);
        }

        $form = strtolower(trim($number->getAttribute('form')));
        if ($form === '') {
            $form = 'numeric';
        }
        if (!in_array($form, ['numeric', 'ordinal', 'long-ordinal', 'roman'], true)) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' number form must be numeric, ordinal, long-ordinal, or roman');
        }

        return [
            'type' => 'number',
            'prefix' => self::optionalAttribute($number, 'prefix'),
            'suffix' => self::optionalAttribute($number, 'suffix'),
            'variable' => $variable,
            'form' => $form,
            'textCase' => self::textCaseAttribute($number, $scope),
            'display' => self::displayAttribute($number, $scope),
        ];
    }

    /**
     * @return array{type:string, prefix:string, suffix:string, variable:string}
     */
    private static function namesRenderingElement(\DOMElement $names, string $scope): array
    {
        $variable = trim($names->getAttribute('variable'));
        if ($variable === '') {
            $variable = 'author editor';
        }

        $element = [
            'type' => 'names',
            'prefix' => self::optionalAttribute($names, 'prefix'),
            'suffix' => self::optionalAttribute($names, 'suffix'),
            'variable' => $variable,
            'display' => self::displayAttribute($names, $scope),
        ];
        $overrides = self::nameRenderingOverridesFromNames($names, $scope);
        if ($overrides !== []) {
            $element['nameRendering'] = $overrides;
        }
        $substitute = self::directChild($names, 'substitute');
        if ($substitute instanceof \DOMElement) {
            $element['substitute'] = self::renderingElements($substitute, $scope);
        }

        return $element;
    }

    /**
     * @return array{type:string, prefix:string, suffix:string, variable:string, form:string, plural:string}
     */
    private static function labelRenderingElement(\DOMElement $label, string $scope): array
    {
        $variable = strtolower(trim($label->getAttribute('variable')));
        if ($variable === '') {
            throw new \InvalidArgumentException('CSL ' . $scope . ' label element must declare a variable');
        }

        if (!in_array($variable, self::supportedLabelVariables(), true)) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' label variable is not supported: ' . $variable);
        }

        $form = strtolower(trim($label->getAttribute('form')));
        if ($form === '') {
            $form = 'long';
        }
        if (!in_array($form, ['long', 'short', 'symbol'], true)) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' label form must be long, short, or symbol');
        }

        $plural = strtolower(trim($label->getAttribute('plural')));
        if ($plural === '') {
            $plural = 'contextual';
        }
        if (!in_array($plural, ['contextual', 'always', 'never'], true)) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' label plural must be contextual, always, or never');
        }

        return [
            'type' => 'label',
            'prefix' => self::optionalAttribute($label, 'prefix'),
            'suffix' => self::optionalAttribute($label, 'suffix'),
            'variable' => $variable,
            'form' => $form,
            'plural' => $plural,
            'stripPeriods' => self::booleanRenderingAttribute($label, 'strip-periods', false, $scope),
            'textCase' => self::textCaseAttribute($label, $scope),
            'display' => self::displayAttribute($label, $scope),
        ];
    }

    /**
     * @return list<string>
     */
    private static function supportedLabelVariables(): array
    {
        return self::supportedNumberVariables();
    }

    /**
     * @return list<string>
     */
    private static function supportedNumberVariables(): array
    {
        return [
            'citation-number',
            'locator',
            'page',
            'page-first',
            'number',
            'edition',
            'volume',
            'issue',
            'chapter-number',
            'number-of-pages',
            'number-of-volumes',
            'collection-number',
        ];
    }

    /**
     * @return array{type:string, branches:list<array{match:string, variables:list<string>, types:list<string>, positions:list<string>, children:list<array<string, mixed>>}>, else:list<array<string, mixed>>}
     */
    private static function chooseRenderingElement(\DOMElement $choose, string $scope): array
    {
        $branches = [];
        $else = [];
        $seenIf = false;
        $seenElse = false;

        foreach ($choose->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $name = $child->localName;
            if ($name === 'if') {
                if ($seenIf || $seenElse) {
                    throw new \InvalidArgumentException('CSL ' . $scope . ' choose element must start with a single if branch');
                }

                $seenIf = true;
                $branches[] = self::conditionalRenderingBranch($child, $scope);
                continue;
            }

            if ($name === 'else-if') {
                if (!$seenIf || $seenElse) {
                    throw new \InvalidArgumentException('CSL ' . $scope . ' choose else-if branch must follow if and precede else');
                }

                $branches[] = self::conditionalRenderingBranch($child, $scope);
                continue;
            }

            if ($name === 'else') {
                if (!$seenIf || $seenElse) {
                    throw new \InvalidArgumentException('CSL ' . $scope . ' choose else branch must follow if and appear once');
                }

                $seenElse = true;
                $else = self::renderingElements($child, $scope);
                continue;
            }

            throw new \InvalidArgumentException('CSL ' . $scope . ' choose element may only contain if, else-if, or else branches');
        }

        if ($branches === []) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' choose element must contain an if branch');
        }

        return [
            'type' => 'choose',
            'branches' => $branches,
            'else' => $else,
        ];
    }

    /**
     * @return array{match:string, variables:list<string>, types:list<string>, positions:list<string>, children:list<array<string, mixed>>}
     */
    private static function conditionalRenderingBranch(\DOMElement $branch, string $scope): array
    {
        $match = trim($branch->getAttribute('match'));
        if ($match === '') {
            $match = 'all';
        }
        if (!in_array($match, ['all', 'any', 'none'], true)) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' choose branch match must be all, any, or none');
        }

        $variables = self::spaceSeparatedAttribute($branch, 'variable');
        $types = self::spaceSeparatedAttribute($branch, 'type');
        $positions = self::spaceSeparatedAttribute($branch, 'position');
        foreach ($positions as $position) {
            if (!in_array($position, ['first', 'subsequent', 'ibid', 'ibid-with-locator', 'near-note'], true)) {
                throw new \InvalidArgumentException('CSL ' . $scope . ' choose branch position is not supported: ' . $position);
            }
        }

        if ($variables === [] && $types === [] && $positions === []) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' choose branch must declare variable, type, or position');
        }

        return [
            'match' => $match,
            'variables' => $variables,
            'types' => $types,
            'positions' => $positions,
            'children' => self::renderingElements($branch, $scope),
        ];
    }

    /**
     * @return list<string>
     */
    private static function spaceSeparatedAttribute(\DOMElement $element, string $name): array
    {
        $value = trim($element->getAttribute($name));
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(
            preg_split('/\s+/', $value) ?: [],
            static fn (string $part): bool => $part !== ''
        ));
    }

    private static function optionalAttribute(\DOMElement $element, string $name): string
    {
        return $element->hasAttribute($name) ? $element->getAttribute($name) : '';
    }

    private static function booleanRenderingAttribute(\DOMElement $element, string $name, bool $default, string $scope): bool
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

        throw new \InvalidArgumentException('CSL ' . $scope . ' rendering attribute ' . $name . ' must be true or false');
    }

    private static function textCaseAttribute(\DOMElement $element, string $scope): string
    {
        $value = strtolower(trim($element->getAttribute('text-case')));
        if ($value === '') {
            return '';
        }

        if (!in_array($value, ['lowercase', 'uppercase', 'capitalize-first', 'capitalize-all', 'sentence', 'title'], true)) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' text-case must be lowercase, uppercase, capitalize-first, capitalize-all, sentence, or title');
        }

        return $value;
    }

    private static function displayAttribute(\DOMElement $element, string $scope): string
    {
        $value = strtolower(trim($element->getAttribute('display')));
        if ($value === '') {
            return '';
        }

        if (!in_array($value, ['block', 'left-margin', 'right-inline', 'indent'], true)) {
            throw new \InvalidArgumentException('CSL ' . $scope . ' display must be block, left-margin, right-inline, or indent');
        }

        return $value;
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

        $termElements = self::directChildren($termsElement, 'term');
        foreach ($termElements as $termElement) {
            if (self::isOrdinalSuffixTerm(trim($termElement->getAttribute('name')))) {
                $terms = self::withoutDefaultOrdinalSuffixTerms($terms);
                break;
            }
        }

        foreach ($termElements as $termElement) {
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

    private static function isOrdinalSuffixTerm(string $name): bool
    {
        return preg_match('/^ordinal(?:-\d{2})?$/', $name) === 1;
    }

    /**
     * @param array<string, array{single:string, multiple:string}> $terms
     * @return array<string, array{single:string, multiple:string}>
     */
    private static function withoutDefaultOrdinalSuffixTerms(array $terms): array
    {
        foreach (array_keys($terms) as $key) {
            if (preg_match('/^ordinal(?:-\d{2})?\|/', $key) === 1) {
                unset($terms[$key]);
            }
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

    private static function firstAuthorEditorNamesElement(\DOMElement $element): ?\DOMElement
    {
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($child->localName === 'names' && self::namesElementIncludesAuthorEditor($child)) {
                return $child;
            }

            $match = self::firstAuthorEditorNamesElement($child);
            if ($match instanceof \DOMElement) {
                return $match;
            }
        }

        return null;
    }

    private static function namesElementIncludesAuthorEditor(\DOMElement $names): bool
    {
        $variable = strtolower(trim($names->getAttribute('variable')));
        if ($variable === '') {
            return true;
        }

        $variables = preg_split('/\s+/', $variable) ?: [];

        return in_array('author', $variables, true) || in_array('editor', $variables, true);
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

    /**
     * @return list<string>
     */
    private static function termFallbackKeys(string $name, string $form): array
    {
        $form = strtolower(trim($form));
        $forms = match ($form) {
            'verb-short' => ['verb-short', 'verb', 'short', 'long'],
            'verb' => ['verb', 'long'],
            'symbol' => ['symbol', 'short', 'long'],
            'short' => ['short', 'long'],
            'long', '' => ['long'],
            default => [$form, 'long'],
        };

        $keys = [];
        foreach ($forms as $candidate) {
            $key = self::termKey($name, $candidate);
            if (!in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }
}
