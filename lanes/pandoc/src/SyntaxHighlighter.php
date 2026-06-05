<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class SyntaxHighlighter
{
    private const TOKEN_CLASSES = [
        'attribute' => 'ot',
        'comment' => 'co',
        'constant' => 'cn',
        'datatype' => 'dt',
        'function' => 'fu',
        'information' => 'in',
        'keyword' => 'kw',
        'number' => 'dv',
        'operator' => 'op',
        'preprocessor' => 'pp',
        'region' => 're',
        'string' => 'st',
        'variable' => 'va',
        'warning' => 'al',
    ];

    private const LANGUAGE_ALIASES = [
        'bash' => 'bash',
        'console' => 'bash',
        'css' => 'css',
        'diff' => 'diff',
        'git-diff' => 'diff',
        'html' => 'html',
        'html5' => 'html',
        'haskell' => 'haskell',
        'hs' => 'haskell',
        'javascript' => 'javascript',
        'js' => 'javascript',
        'json' => 'json',
        'latex' => 'tex',
        'lhs' => 'haskell',
        'literate-haskell' => 'haskell',
        'literatehaskell' => 'haskell',
        'lua' => 'lua',
        'pandoc-lua' => 'lua',
        'commonmark' => 'markdown',
        'gfm' => 'markdown',
        'markdown' => 'markdown',
        'md' => 'markdown',
        'mmd' => 'markdown',
        'multimarkdown' => 'markdown',
        'pandoc' => 'markdown',
        'pandoc-markdown' => 'markdown',
        'patch' => 'diff',
        'php' => 'php',
        'postgres' => 'sql',
        'postgresql' => 'sql',
        'py' => 'python',
        'py3' => 'python',
        'python' => 'python',
        'python3' => 'python',
        'rake' => 'ruby',
        'rb' => 'ruby',
        'ruby' => 'ruby',
        'sh' => 'bash',
        'shell' => 'bash',
        'sql' => 'sql',
        'tex' => 'tex',
        'ts' => 'typescript',
        'typescript' => 'typescript',
        'udiff' => 'diff',
        'unified-diff' => 'diff',
        'xhtml' => 'html',
        'xml' => 'html',
        'yaml' => 'yaml',
        'yml' => 'yaml',
    ];

    private const SUPPORTED_STYLES = [
        'breezedark',
        'espresso',
        'haddock',
        'kate',
        'monochrome',
        'pygments',
        'tango',
        'zenburn',
    ];

    private const TOKEN_STYLE_ALIASES = [
        'alerttok' => 'warning',
        'annotationtok' => 'attribute',
        'attributetok' => 'attribute',
        'basentok' => 'number',
        'builtintok' => 'function',
        'chartok' => 'constant',
        'commenttok' => 'comment',
        'commentvartok' => 'comment',
        'constanttok' => 'constant',
        'controlflowtok' => 'keyword',
        'datatypetok' => 'datatype',
        'decvaltok' => 'number',
        'documentationtok' => 'comment',
        'errortok' => 'warning',
        'extensiontok' => 'preprocessor',
        'floattok' => 'number',
        'functiontok' => 'function',
        'importtok' => 'preprocessor',
        'informationtok' => 'information',
        'keywordtok' => 'keyword',
        'operatortok' => 'operator',
        'othertok' => 'attribute',
        'preprocessortok' => 'preprocessor',
        'regionmarkertok' => 'region',
        'specialchartok' => 'constant',
        'specialstringtok' => 'string',
        'stringtok' => 'string',
        'variablename' => 'variable',
        'variabletok' => 'variable',
        'verbatimstringtok' => 'string',
        'warningtok' => 'warning',
    ];

    /**
     * @return array{
     *   language:string,
     *   requestedLanguage:string,
     *   style:string,
     *   tokens:list<array{type:string, text:string, class:string}>,
     *   html:string,
     *   css:string,
     *   diagnostics:list<array{code:string, message:string}>,
     *   lineNumbering:array{enabled:bool, anchors:bool, start:int, lineIdPrefix:string}
     * }
     */
    public function highlightCodeBlock(AstNode $codeBlock, string $style = 'pygments', array $options = []): array
    {
        if ($codeBlock->type !== 'code_block') {
            throw new \InvalidArgumentException('Syntax highlighter expects a code_block node');
        }

        $requested = self::languageFromCodeBlock($codeBlock);
        $classes = $codeBlock->attr('classes', []);
        $attributes = $codeBlock->attr('attributes', []);

        return $this->highlight((string) $codeBlock->attr('text', ''), $requested, $style, array_replace([
            'id' => (string) $codeBlock->attr('id', ''),
            'classes' => is_array($classes) ? $classes : [],
            'attributes' => is_array($attributes) ? $attributes : [],
        ], $options));
    }

    /**
     * @param array{
     *   id?: string,
     *   classes?: array<int, mixed>,
     *   attributes?: array<string, mixed>,
     *   themeJson?: string
     * } $options
     * @return array{
     *   language:string,
     *   requestedLanguage:string,
     *   style:string,
     *   tokens:list<array{type:string, text:string, class:string}>,
     *   html:string,
     *   css:string,
     *   diagnostics:list<array{code:string, message:string}>,
     *   lineNumbering:array{enabled:bool, anchors:bool, start:int, lineIdPrefix:string}
     * }
     */
    public function highlight(string $code, string $language = '', string $style = 'pygments', array $options = []): array
    {
        $requested = trim($language);
        $canonicalLanguage = self::normalizeLanguage($requested) ?? '';
        $canonicalStyle = self::normalizeStyle($style);
        $lineOptions = self::lineNumberingOptions($options);
        $theme = null;
        $diagnostics = [];

        if ($requested !== '' && $canonicalLanguage === '') {
            $diagnostics[] = [
                'code' => 'unsupported-language',
                'message' => "No bounded native syntax definition is available for '{$requested}'",
            ];
        }

        if (isset($options['themeJson']) && is_string($options['themeJson']) && trim($options['themeJson']) !== '') {
            $theme = self::parseThemeJson($options['themeJson'], $canonicalStyle);
            $canonicalStyle = $theme['name'];
            $diagnostics = array_merge($diagnostics, $theme['diagnostics']);
        }

        $tokens = $canonicalLanguage === ''
            ? [['type' => 'text', 'text' => $code, 'class' => '']]
            : $this->tokenize($code, $canonicalLanguage);

        return [
            'language' => $canonicalLanguage,
            'requestedLanguage' => $requested,
            'style' => $canonicalStyle,
            'tokens' => $tokens,
            'html' => self::renderHighlightedHtml($tokens, $canonicalLanguage, $lineOptions),
            'css' => $theme === null ? self::stylesheet($canonicalStyle) : self::stylesheetFromParsedTheme($theme),
            'diagnostics' => $diagnostics,
            'lineNumbering' => [
                'enabled' => $lineOptions['numberLines'],
                'anchors' => $lineOptions['lineAnchors'],
                'start' => $lineOptions['startNumber'],
                'lineIdPrefix' => $lineOptions['lineIdPrefix'],
            ],
        ];
    }

    public static function languageFromCodeBlock(AstNode $codeBlock): string
    {
        $classes = $codeBlock->attr('classes', []);
        if (is_array($classes)) {
            foreach ($classes as $class) {
                $class = trim((string) $class);
                if ($class === '' || self::isStructuralClass($class)) {
                    continue;
                }

                return self::stripLanguagePrefix($class);
            }
        }

        $attributes = $codeBlock->attr('attributes', []);
        if (is_array($attributes)) {
            foreach (['language', 'data-language', 'lang'] as $name) {
                if (isset($attributes[$name]) && trim((string) $attributes[$name]) !== '') {
                    return self::stripLanguagePrefix((string) $attributes[$name]);
                }
            }
        }

        $info = trim((string) $codeBlock->attr('info', ''));
        if ($info !== '' && !str_starts_with($info, '{')) {
            $tokens = preg_split('/\s+/', $info, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if ($tokens !== []) {
                return self::stripLanguagePrefix((string) $tokens[0]);
            }
        }

        return '';
    }

    public static function normalizeLanguage(string $language): ?string
    {
        $language = strtolower(trim(self::stripLanguagePrefix($language)));
        $language = str_replace(['_', '.'], '-', $language);
        if ($language === '' || self::isStructuralClass($language)) {
            return null;
        }

        return self::LANGUAGE_ALIASES[$language] ?? null;
    }

    public static function normalizeStyle(string $style): string
    {
        $style = strtolower(trim($style));
        $style = str_replace(['_', ' '], '-', $style);

        return in_array($style, self::SUPPORTED_STYLES, true) ? $style : 'pygments';
    }

    public static function stylesheet(string $style = 'pygments', string $selector = '.sourceCode'): string
    {
        $selector = trim($selector) === '' ? '.sourceCode' : trim($selector);
        $style = self::normalizeStyle($style);
        $colors = self::styleColors($style);

        return self::stylesheetFromColors($colors, [], $selector);
    }

    /**
     * @return array{
     *   name:string,
     *   colors:array<string, string>,
     *   tokenStyles:array<string, array{color?:string, background?:string, bold?:bool, italic?:bool, underline?:bool}>,
     *   diagnostics:list<array{code:string, message:string}>
     * }
     */
    public static function parseThemeJson(string $json, string $baseStyle = 'pygments'): array
    {
        if (substr($json, 0, 3) === "\xEF\xBB\xBF") {
            throw new \InvalidArgumentException('Pandoc highlight theme JSON must be UTF-8 without a BOM');
        }

        try {
            $theme = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('Unable to parse Pandoc highlight theme JSON: ' . $exception->getMessage(), 0, $exception);
        }

        if (!is_array($theme)) {
            throw new \InvalidArgumentException('Pandoc highlight theme JSON must decode to an object');
        }

        $colors = self::styleColors(self::normalizeStyle($baseStyle));
        $diagnostics = [];
        $tokenStyles = [];
        $editorColors = self::arrayValue($theme, ['editor-colors', 'editorColors']) ?? [];
        if (!is_array($editorColors)) {
            $editorColors = [];
        }

        $normalStyle = self::themeTokenStyle($theme, 'NormalTok') ?? self::themeTokenStyle($theme, 'Normal');
        $textColor = self::themeColor($theme, ['text-color', 'textColor', 'default-color', 'defaultColor'], 'theme text color')
            ?? ($normalStyle === null ? null : ($normalStyle['color'] ?? null));
        $backgroundColor = self::themeColor($theme, ['background-color', 'backgroundColor'], 'theme background color')
            ?? ($normalStyle === null ? null : ($normalStyle['background'] ?? null));
        $lineNumberColor = self::themeColor($theme, ['line-number-color', 'lineNumberColor'], 'line number color')
            ?? self::themeColor($editorColors, ['line-number-color', 'line-numbers', 'lineNumberColor', 'lineNumbers'], 'editor line number color');
        $lineNumberBackground = self::themeColor($theme, ['line-number-background-color', 'lineNumberBackgroundColor'], 'line number background color')
            ?? self::themeColor($editorColors, ['line-number-background-color', 'lineNumberBackgroundColor'], 'editor line number background color');

        if ($textColor !== null) {
            $colors['text'] = $textColor;
        }
        if ($backgroundColor !== null) {
            $colors['background'] = $backgroundColor;
        }
        if ($lineNumberColor !== null) {
            $colors['lineNumber'] = $lineNumberColor;
        }
        if ($lineNumberBackground !== null) {
            $colors['lineNumberBackground'] = $lineNumberBackground;
        }

        $themeTokenStyles = self::arrayValue($theme, ['token-styles', 'tokenStyles', 'text-styles', 'textStyles']) ?? [];
        if (!is_array($themeTokenStyles)) {
            throw new \InvalidArgumentException('Pandoc highlight theme token-styles must be an object');
        }
        if ($themeTokenStyles !== [] && array_is_list($themeTokenStyles)) {
            throw new \InvalidArgumentException('Pandoc highlight theme token-styles must be keyed by token type');
        }

        foreach ($themeTokenStyles as $tokenName => $styleValue) {
            if (!is_array($styleValue)) {
                continue;
            }

            $type = self::tokenTypeFromThemeName((string) $tokenName);
            if ($type === null) {
                if (!in_array(self::normalizedThemeTokenName((string) $tokenName), ['normal', 'normaltok'], true)) {
                    $diagnostics[] = [
                        'code' => 'unsupported-theme-token',
                        'message' => "Theme token style '{$tokenName}' is not used by the bounded native highlighter",
                    ];
                }
                continue;
            }

            $parsedStyle = self::parseTokenStyle($styleValue, "token style {$tokenName}");
            if (isset($parsedStyle['color'])) {
                $colors[$type] = $parsedStyle['color'];
            }
            $tokenStyles[$type] = $parsedStyle;
        }

        $nameValue = self::arrayValue($theme, ['name', 'title']);
        if (!is_string($nameValue) || trim($nameValue) === '') {
            $metadata = $theme['metadata'] ?? null;
            if (is_array($metadata)) {
                $metadataName = self::arrayValue($metadata, ['name', 'title']);
                $nameValue = is_string($metadataName) ? $metadataName : 'custom-theme';
            } else {
                $nameValue = 'custom-theme';
            }
        }

        return [
            'name' => self::sanitizeStyleName($nameValue),
            'colors' => $colors,
            'tokenStyles' => $tokenStyles,
            'diagnostics' => $diagnostics,
        ];
    }

    public static function stylesheetFromThemeJson(string $json, string $selector = '.sourceCode', string $baseStyle = 'pygments'): string
    {
        return self::stylesheetFromParsedTheme(self::parseThemeJson($json, $baseStyle), $selector);
    }

    /**
     * @param array{
     *   name:string,
     *   colors:array<string, string>,
     *   tokenStyles:array<string, array{color?:string, background?:string, bold?:bool, italic?:bool, underline?:bool}>,
     *   diagnostics:list<array{code:string, message:string}>
     * } $theme
     */
    private static function stylesheetFromParsedTheme(array $theme, string $selector = '.sourceCode'): string
    {
        $selector = trim($selector) === '' ? '.sourceCode' : trim($selector);

        return self::stylesheetFromColors($theme['colors'], $theme['tokenStyles'], $selector);
    }

    /**
     * @param array<string, string> $colors
     * @param array<string, array{color?:string, background?:string, bold?:bool, italic?:bool, underline?:bool}> $tokenStyles
     */
    private static function stylesheetFromColors(array $colors, array $tokenStyles = [], string $selector = '.sourceCode'): string
    {
        $rules = [
            "{$selector} { background: {$colors['background']}; color: {$colors['text']}; }",
            self::tokenStylesheetRule($selector, 'kw', 'keyword', $colors, $tokenStyles, ['font-weight: 600']),
            self::tokenStylesheetRule($selector, 'dt', 'datatype', $colors, $tokenStyles),
            self::tokenStylesheetRule($selector, 'st', 'string', $colors, $tokenStyles),
            self::tokenStylesheetRule($selector, 'co', 'comment', $colors, $tokenStyles, ['font-style: italic']),
            self::tokenStylesheetRule($selector, 'dv', 'number', $colors, $tokenStyles),
            self::tokenStylesheetRule($selector, 'fu', 'function', $colors, $tokenStyles),
            self::tokenStylesheetRule($selector, 'va', 'variable', $colors, $tokenStyles),
            self::tokenStylesheetRule($selector, 'ot', 'attribute', $colors, $tokenStyles),
            self::tokenStylesheetRule($selector, 'op', 'operator', $colors, $tokenStyles),
            self::tokenStylesheetRule($selector, 'pp', 'preprocessor', $colors, $tokenStyles),
            self::tokenStylesheetRule($selector, 'cn', 'constant', $colors, $tokenStyles),
            self::tokenStylesheetRule($selector, 'in', 'information', $colors, $tokenStyles),
            self::tokenStylesheetRule($selector, 're', 'region', $colors, $tokenStyles, ['font-weight: 600']),
            self::tokenStylesheetRule($selector, 'al', 'warning', $colors, $tokenStyles, ['font-weight: 600']),
            'pre.numberSource code { counter-reset: source-line 0; }',
            'pre.numberSource code > span { position: relative; left: -4em; counter-increment: source-line; }',
            self::lineNumberStylesheetRule($colors),
            'pre.numberSource { margin-left: 3em; padding-left: 4px; }',
        ];

        return implode("\n", $rules);
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     * @param array{
     *   numberLines?: bool,
     *   lineAnchors?: bool,
     *   startNumber?: int,
     *   lineIdPrefix?: string,
     *   containerClasses?: list<string>
     * } $options
     */
    public static function renderHighlightedHtml(array $tokens, string $language = '', array $options = []): string
    {
        $language = self::normalizeLanguage($language) ?? '';
        $lineMode = ($options['numberLines'] ?? false) || ($options['lineAnchors'] ?? false);
        if ($lineMode) {
            return self::renderLineNumberedHtml($tokens, $language, $options);
        }

        $classes = trim('sourceCode' . ($language === '' ? '' : ' ' . self::sanitizeClass($language)));
        $html = '';

        foreach ($tokens as $token) {
            $text = self::escapeHtml((string) ($token['text'] ?? ''));
            $class = self::sanitizeClass((string) ($token['class'] ?? ''));
            $html .= $class === '' ? $text : '<span class="' . $class . '">' . $text . '</span>';
        }

        return '<pre class="' . $classes . '"><code class="' . $classes . '">' . $html . '</code></pre>';
    }

    public function wordpressHtmlBlock(AstNode $codeBlock, string $style = 'pygments', array $options = []): string
    {
        $highlighted = $this->highlightCodeBlock($codeBlock, $style, $options);
        $styleName = self::escapeHtml($highlighted['style']);

        return '<!-- wp:html -->'
            . "\n" . '<style data-pandoc-highlight-style="' . $styleName . '">' . "\n"
            . $highlighted['css'] . "\n"
            . '</style>' . "\n"
            . $highlighted['html']
            . "\n" . '<!-- /wp:html -->';
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenize(string $code, string $language): array
    {
        return match ($language) {
            'bash' => $this->tokenizeBash($code),
            'css' => $this->tokenizeCss($code),
            'diff' => $this->tokenizeDiff($code),
            'haskell' => $this->tokenizeHaskell($code),
            'html' => $this->tokenizeHtml($code),
            'javascript' => $this->tokenizeJavaScript($code),
            'json' => $this->tokenizeJson($code),
            'lua' => $this->tokenizeLua($code),
            'markdown' => $this->tokenizeMarkdown($code),
            'php' => $this->tokenizePhp($code),
            'python' => $this->tokenizePython($code),
            'ruby' => $this->tokenizeRuby($code),
            'sql' => $this->tokenizeSql($code),
            'tex' => $this->tokenizeTex($code),
            'typescript' => $this->tokenizeTypeScript($code),
            'yaml' => $this->tokenizeYaml($code),
            default => [['type' => 'text', 'text' => $code, 'class' => '']],
        };
    }

    /**
     * @return list<array{0:string, 1:string}>
     */
    private function phpPatterns(): array
    {
        return [
            ['preprocessor', '/^<\\?(?:php|=)?|^\\?>/i'],
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^(?:\\/\\/|#)[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['variable', '/^\\$[A-Za-z_][A-Za-z0-9_]*/'],
            ['keyword', '/^\\b(?:abstract|array|as|break|case|catch|class|clone|const|continue|declare|default|do|echo|else|elseif|extends|final|finally|for|foreach|function|global|if|implements|interface|match|namespace|new|private|protected|public|readonly|return|static|switch|throw|trait|try|use|while|yield)\\b/i'],
            ['constant', '/^\\b(?:false|null|true)\\b/i'],
            ['number', '/^\\b(?:0x[0-9A-Fa-f]+|\\d+(?:\\.\\d+)?)\\b/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['operator', '/^(?:=>|->|::|===|!==|==|!=|<=|>=|&&|\\|\\||[{}()[\\];,.+*\\/%=!<>?:-])/'],
        ];
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizePhp(string $code): array
    {
        return $this->scan($code, $this->phpPatterns());
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeJson(string $code): array
    {
        return $this->scan($code, [
            ['attribute', '/^"(?:\\\\.|[^"\\\\])*"(?=\\s*:)/s'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['constant', '/^\\b(?:false|null|true)\\b/'],
            ['number', '/^-?\\b(?:0|[1-9]\\d*)(?:\\.\\d+)?(?:[eE][+-]?\\d+)?\\b/'],
            ['operator', '/^[{}[\\]:,]/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeYaml(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^#[^\\n]*/'],
            ['attribute', '/^(?:[A-Za-z_][A-Za-z0-9_.-]*|"[^"\\n]+"|\'[^\'\\n]+\')(?=\\s*:)/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:''|[^'])*'/s"],
            ['constant', '/^\\b(?:false|null|true|yes|no)\\b/i'],
            ['number', '/^-?\\b(?:0|[1-9]\\d*)(?:\\.\\d+)?\\b/'],
            ['operator', '/^(?:---|\\.\\.\\.|[\\[\\]{}:,.&*|-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeHtml(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^<!--[\\s\\S]*?-->/'],
            ['keyword', '/^<\\/?[A-Za-z][A-Za-z0-9:-]*/'],
            ['attribute', '/^[A-Za-z_:][A-Za-z0-9_.:-]*(?=\\s*=)/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['operator', '/^\\/?>|^=/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeSql(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^--[^\\n]*/'],
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['string', "/^'(?:''|[^'])*'/s"],
            ['keyword', '/^\\b(?:alter|and|as|by|case|create|delete|desc|distinct|drop|else|end|from|group|having|in|insert|into|is|join|left|limit|not|null|on|or|order|outer|select|set|table|then|update|values|when|where)\\b/i'],
            ['number', '/^\\b\\d+(?:\\.\\d+)?\\b/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['operator', '/^(?:<>|!=|<=|>=|==|[(),.*=<>+-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeJavaScript(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['string', '/^`(?:\\\\.|[^`\\\\])*`/s'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['keyword', '/^\\b(?:async|await|break|case|catch|class|const|continue|default|do|else|export|extends|finally|for|from|function|if|import|let|new|return|switch|throw|try|typeof|var|while|yield)\\b/'],
            ['constant', '/^\\b(?:false|null|true|undefined)\\b/'],
            ['number', '/^\\b\\d+(?:\\.\\d+)?\\b/'],
            ['function', '/^\\b[A-Za-z_$][A-Za-z0-9_$]*(?=\\s*\\()/'],
            ['operator', '/^(?:=>|===|!==|==|!=|<=|>=|&&|\\|\\||[{}()[\\];,.+*\\/%=!<>?:-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeTypeScript(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['string', '/^`(?:\\\\.|[^`\\\\])*`/s'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['attribute', '/^@[A-Za-z_$][A-Za-z0-9_$]*/'],
            ['keyword', '/^\\b(?:abstract|as|asserts|async|await|break|case|catch|class|const|constructor|continue|declare|default|do|else|enum|export|extends|finally|for|from|function|get|if|implements|import|in|infer|interface|is|keyof|let|module|namespace|new|of|private|protected|public|readonly|return|satisfies|set|static|switch|this|throw|try|type|typeof|var|while|yield)\\b/'],
            ['datatype', '/^\\b(?:any|bigint|boolean|never|null|number|object|string|symbol|undefined|unknown|void)\\b/'],
            ['constant', '/^\\b(?:false|true)\\b/'],
            ['number', '/^\\b(?:0[xX][0-9A-Fa-f]+|0[bB][01]+|\\d+(?:\\.\\d+)?(?:[eE][+-]?\\d+)?)\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_$]*(?=\\s*(?:[<:={]|\\b))/'],
            ['function', '/^\\b[A-Za-z_$][A-Za-z0-9_$]*(?=\\s*\\()/'],
            ['variable', '/^\\b[A-Za-z_$][A-Za-z0-9_$]*\\b/'],
            ['operator', '/^(?:=>|===|!==|==|!=|<=|>=|&&|\\|\\||\\?\\?|\\?\\.|\\.\\.\\.|[{}()[\\];,.+*\\/%=!<>?:|-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizePython(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^(?:r|u|f|fr|rf)?"""[\\s\\S]*?"""/i'],
            ['string', "/^(?:r|u|f|fr|rf)?'''[\\s\\S]*?'''/i"],
            ['string', '/^(?:r|u|f|fr|rf)?"(?:\\\\.|[^"\\\\])*"/i'],
            ['string', "/^(?:r|u|f|fr|rf)?'(?:\\\\.|[^'\\\\])*'/i"],
            ['attribute', '/^@[A-Za-z_][A-Za-z0-9_.]*/'],
            ['keyword', '/^\\b(?:and|as|assert|async|await|break|case|class|continue|def|del|elif|else|except|finally|for|from|global|if|import|in|is|lambda|match|nonlocal|not|or|pass|raise|return|try|while|with|yield)\\b/'],
            ['constant', '/^\\b(?:False|None|True)\\b/'],
            ['datatype', '/^\\b(?:bool|bytes|complex|dict|float|frozenset|int|list|object|set|str|tuple)\\b/'],
            ['number', '/^\\b(?:0[xX][0-9A-Fa-f]+|0[bB][01]+|\\d+(?:\\.\\d+)?(?:[eE][+-]?\\d+)?)\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*\\b/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:->|:=|\\*\\*|\\/\\/|==|!=|<=|>=|[{}()[\\];,.+*\\/%=!<>:|-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeRuby(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['string', '/^%[qQ]?\\{(?:\\\\.|[^}\\\\])*\\}/s'],
            ['attribute', '/^:[A-Za-z_][A-Za-z0-9_]*[!?=]?/'],
            ['attribute', '/^[A-Za-z_][A-Za-z0-9_]*[!?=]?:(?!:)/'],
            ['variable', '/^(?:@@|@|\\$)[A-Za-z_][A-Za-z0-9_]*/'],
            ['keyword', '/^\\b(?:BEGIN|END|alias|and|begin|break|case|class|def|defined\\?|do|else|elsif|end|ensure|for|if|in|module|next|not|or|private|protected|public|redo|rescue|retry|return|then|undef|unless|until|when|while|yield)\\b/'],
            ['constant', '/^\\b(?:false|nil|self|super|true)\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*(?:::[A-Z][A-Za-z0-9_]*)*/'],
            ['number', '/^\\b(?:0[xX][0-9A-Fa-f]+|0[bB][01]+|\\d+(?:\\.\\d+)?(?:[eE][+-]?\\d+)?)\\b/'],
            ['function', '/^\\b(?:abort|autoload|binding|catch|eval|exec|exit|fail|fork|format|gets|lambda|load|loop|open|p|print|printf|proc|putc|puts|raise|rand|require|require_relative|select|sleep|sprintf|system|task|throw|warn)\\b/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*[!?=]?(?=\\s*\\()/'],
            ['variable', '/^\\b[a-z_][A-Za-z0-9_]*[!?=]?\\b/'],
            ['operator', '/^(?:::|=>|->|===|==|!=|<=|>=|=~|!~|&&|\\|\\||\\.\\.\\.?|\\+=|-=|\\*=|\\/=|%=|[{}()[\\];,.+*\\/%=!<>?:&|^-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeLua(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^--\\[\\[[\\s\\S]*?\\]\\]/'],
            ['comment', '/^--[^\\n]*/'],
            ['string', '/^\\[\\[[\\s\\S]*?\\]\\]/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['keyword', '/^\\b(?:and|break|do|else|elseif|end|for|function|goto|if|in|local|not|or|repeat|return|then|until|while)\\b/'],
            ['constant', '/^\\b(?:false|nil|true)\\b/'],
            ['datatype', '/^\\bpandoc\\b/'],
            ['number', '/^\\b(?:0[xX][0-9A-Fa-f]+|\\d+(?:\\.\\d+)?(?:[eE][+-]?\\d+)?)\\b/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['function', '/^\\b(?:assert|collectgarbage|dofile|error|getmetatable|ipairs|load|next|pairs|pcall|print|rawequal|rawget|rawlen|rawset|require|select|setmetatable|tonumber|tostring|type|warn|xpcall)\\b/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:\\.\\.\\.|\\.\\.|==|~=|<=|>=|::|\\/\\/|[{}()[\\];,.+*\\/%=#<>:-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeHaskell(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\{-[\\s\\S]*?-\\}/'],
            ['comment', '/^--[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])'/s"],
            ['keyword', '/^\\b(?:as|case|class|data|default|deriving|do|else|family|forall|foreign|hiding|if|import|in|infix|infixl|infixr|instance|let|module|newtype|of|qualified|then|type|where)\\b/'],
            ['constant', '/^\\b(?:EQ|False|GT|Just|LT|Left|Nothing|Right|True)\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_\']*(?:\\.[A-Z][A-Za-z0-9_\']*)*/'],
            ['number', '/^\\b(?:0[xX][0-9A-Fa-f]+|0[oO][0-7]+|\\d+(?:\\.\\d+)?(?:[eE][+-]?\\d+)?)\\b/'],
            ['variable', '/^\\b[a-z_][A-Za-z0-9_\']*\\b/'],
            ['operator', '/^(?:=>|->|<-|::|==|\\/=|>=|<=|&&|\\|\\||[{}()[\\];,.+*\\/%=$<>:|\\\\-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeTex(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^%[^\\n]*/'],
            ['variable', '/^\\$[A-Za-z_][A-Za-z0-9_.:-]*\\$/'],
            ['keyword', '/^\\\\(?:begin|def|documentclass|end|include|input|let|newcommand|providecommand|renewcommand|section|subsection|subsubsection|usepackage)\\*?\\b/'],
            ['function', '/^\\\\[A-Za-z@]+\\*?/'],
            ['datatype', '/^\\{(?:\\\\?[A-Za-z0-9_.:-]+|[^{}$\\n]{1,80})\\}/'],
            ['number', '/^-?\\d+(?:\\.\\d+)?/'],
            ['variable', '/^[A-Za-z_][A-Za-z0-9_-]*/'],
            ['operator', '/^(?:\\\\[#$%&_{}]|[{}[\\](),=^_~&#+*\\/:;<>|-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeBash(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['variable', '/^\\$[A-Za-z_][A-Za-z0-9_]*|^\\$\\{[^}]+\\}/'],
            ['keyword', '/^\\b(?:case|do|done|elif|else|esac|fi|for|function|if|in|then|while)\\b/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_.-]*(?=\\s)/'],
            ['operator', '/^(?:&&|\\|\\||[{}()[\\];|&<>=$])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeCss(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['attribute', '/^--[A-Za-z0-9_-]+|^[A-Za-z-]+(?=\\s*:)/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['number', '/^-?\\b\\d+(?:\\.\\d+)?(?:px|em|rem|%|vh|vw)?\\b/i'],
            ['keyword', '/^\\b(?:important|media|supports)\\b/i'],
            ['operator', '/^[{}()[\\]:;,.#>+~=*|-]/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeDiff(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\\\ No newline at end of file/'],
            ['region', '/^(?:diff --git|Index:)[^\\n]*/'],
            ['region', '/^(?:---|\\+\\+\\+)\\s[^\\n]*/'],
            ['region', '/^@@[^\\n]*@@/'],
            ['attribute', '/^(?:index|new file mode|deleted file mode|similarity index|dissimilarity index|rename from|rename to|copy from|copy to|old mode|new mode)[^\\n]*/i'],
            ['information', '/^\\+(?!\\+\\+)[^\\n]*/'],
            ['warning', '/^-(?!--)[^\\n]*/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeMarkdown(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^<!--[\\s\\S]*?-->/'],
            ['preprocessor', '/^(?:`{3,}|~{3,})[^\\n]*/'],
            ['region', '/^#{1,6}(?=\\s|$)[^\\n]*/'],
            ['region', '/^ {0,3}(?:(?:\\*\\s*){3,}|(?:_\\s*){3,}|(?:-\\s*){3,})(?=\\n|$)/'],
            ['attribute', '/^\\[[^\\]\\n]+\\]:[^\\n]*/'],
            ['attribute', '/^\\[\\^[^\\]\\n]+\\]/'],
            ['information', '/^!\\[[^\\]\\n]*\\]\\((?:\\\\.|[^)\\n])*\\)/'],
            ['attribute', '/^\\[[^\\]\\n]+\\]\\((?:\\\\.|[^)\\n])*\\)/'],
            ['attribute', '/^<(?:(?:https?|mailto):[^>\\s]+|[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,})>/'],
            ['operator', '/^ {0,3}(?:[-+*]|\\d+[.)])\\s+/'],
            ['operator', '/^>\\s?/'],
            ['constant', '/^\\[[ xX]\\](?=\\s)/'],
            ['string', '/^`+(?:[^`\\\\]|\\\\.)*`+/'],
            ['warning', '/^~~[^~\\n]+~~/'],
            ['keyword', '/^(?:\\*\\*[^*\\n]+\\*\\*|__[^_\\n]+__)/'],
            ['variable', '/^(?:\\*[^*\\n]+\\*|_[^_\\n]+_)/'],
            ['operator', '/^[\\\\*_`{}\\[\\]()#+.!|>-]/'],
        ]);
    }

    /**
     * @param list<array{0:string, 1:string}> $patterns
     * @return list<array{type:string, text:string, class:string}>
     */
    private function scan(string $code, array $patterns): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($code);

        while ($offset < $length) {
            $slice = substr($code, $offset);
            foreach ($patterns as $pattern) {
                $type = (string) ($pattern['type'] ?? $pattern[0] ?? 'text');
                $regex = (string) ($pattern['pattern'] ?? $pattern[1] ?? '');
                if ($regex !== '' && preg_match($regex, $slice, $matches) === 1 && $matches[0] !== '') {
                    $text = $matches[0];
                    $this->appendToken($tokens, $type, $text);
                    $offset += strlen($text);
                    continue 2;
                }
            }

            $this->appendToken($tokens, 'text', $code[$offset]);
            $offset++;
        }

        return $tokens;
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function appendToken(array &$tokens, string $type, string $text): void
    {
        if ($text === '') {
            return;
        }

        $class = self::TOKEN_CLASSES[$type] ?? '';
        $last = count($tokens) - 1;
        if ($last >= 0 && $tokens[$last]['type'] === $type && $tokens[$last]['class'] === $class) {
            $tokens[$last]['text'] .= $text;
            return;
        }

        $tokens[] = [
            'type' => $type,
            'text' => $text,
            'class' => $class,
        ];
    }

    private static function stripLanguagePrefix(string $language): string
    {
        $language = trim($language);
        if (str_starts_with(strtolower($language), 'language-')) {
            return substr($language, strlen('language-'));
        }

        return $language;
    }

    private static function isStructuralClass(string $class): bool
    {
        return in_array(self::normalizedClassName($class), [
            'line-anchors',
            'lineanchors',
            'number',
            'number-lines',
            'numberlines',
            'sourcecode',
        ], true);
    }

    /**
     * @param array{
     *   id?: string,
     *   classes?: array<int, mixed>,
     *   attributes?: array<string, mixed>
     * } $options
     * @return array{
     *   numberLines: bool,
     *   lineAnchors: bool,
     *   startNumber: int,
     *   lineIdPrefix: string,
     *   containerClasses: list<string>
     * }
     */
    private static function lineNumberingOptions(array $options): array
    {
        $classes = [];
        foreach (($options['classes'] ?? []) as $class) {
            $class = trim((string) $class);
            if ($class !== '') {
                $classes[] = $class;
            }
        }

        $normalized = array_map(self::normalizedClassName(...), $classes);
        $attributes = $options['attributes'] ?? [];
        $start = 1;
        foreach (['startFrom', 'start-from'] as $name) {
            if (isset($attributes[$name]) && preg_match('/^-?\d+$/', (string) $attributes[$name]) === 1) {
                $start = (int) $attributes[$name];
                break;
            }
        }

        $id = self::sanitizeId((string) ($options['id'] ?? ''));

        return [
            'numberLines' => in_array('number', $normalized, true)
                || in_array('numberlines', $normalized, true)
                || in_array('number-lines', $normalized, true),
            'lineAnchors' => in_array('lineanchors', $normalized, true)
                || in_array('line-anchors', $normalized, true),
            'startNumber' => $start,
            'lineIdPrefix' => $id === '' ? '' : $id . '-',
            'containerClasses' => $classes,
        ];
    }

    private static function normalizedClassName(string $class): string
    {
        return str_replace('_', '-', strtolower(self::stripLanguagePrefix(trim($class))));
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     * @param array{
     *   numberLines?: bool,
     *   lineAnchors?: bool,
     *   startNumber?: int,
     *   lineIdPrefix?: string,
     *   containerClasses?: list<string>
     * } $options
     */
    private static function renderLineNumberedHtml(array $tokens, string $language, array $options): string
    {
        $numberLines = (bool) ($options['numberLines'] ?? false);
        $startNumber = (int) ($options['startNumber'] ?? 1);
        $lineIdPrefix = (string) ($options['lineIdPrefix'] ?? '');
        $containerClasses = ['sourceCode'];
        if ($numberLines) {
            $containerClasses[] = 'numberSource';
        }

        foreach (($options['containerClasses'] ?? []) as $class) {
            $class = self::sanitizeClass((string) $class);
            if ($class !== '' && !in_array($class, $containerClasses, true)) {
                $containerClasses[] = $class;
            }
        }

        $codeClasses = ['sourceCode'];
        if ($language !== '') {
            $codeClasses[] = self::sanitizeClass($language);
        }

        $codeAttributes = ' class="' . implode(' ', $codeClasses) . '"';
        if ($startNumber !== 1) {
            $codeAttributes .= ' style="counter-reset: source-line ' . ($startNumber - 1) . ';"';
        }

        $lineHtml = [];
        foreach (self::splitTokensIntoLines($tokens) as $index => $lineTokens) {
            $lineNumber = $startNumber + $index;
            $lineId = self::escapeHtml($lineIdPrefix . (string) $lineNumber);
            $line = '<span id="' . $lineId . '"><a href="#' . $lineId . '"';
            if (!$numberLines) {
                $line .= ' aria-hidden="true" tabindex="-1"';
            }
            $line .= '></a>';
            foreach ($lineTokens as $token) {
                $text = self::escapeHtml((string) ($token['text'] ?? ''));
                $class = self::sanitizeClass((string) ($token['class'] ?? ''));
                $line .= $class === '' ? $text : '<span class="' . $class . '">' . $text . '</span>';
            }
            $line .= '</span>';
            $lineHtml[] = $line;
        }

        return '<div class="sourceCode"><pre class="' . implode(' ', $containerClasses) . '"><code' . $codeAttributes . '>'
            . implode("\n", $lineHtml)
            . '</code></pre></div>';
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     * @return list<list<array{type:string, text:string, class:string}>>
     */
    private static function splitTokensIntoLines(array $tokens): array
    {
        $lines = [[]];
        foreach ($tokens as $token) {
            $text = (string) ($token['text'] ?? '');
            $segments = explode("\n", $text);
            foreach ($segments as $index => $segment) {
                if ($index > 0) {
                    $lines[] = [];
                }
                if ($segment === '') {
                    continue;
                }

                $copy = $token;
                $copy['text'] = $segment;
                $lines[count($lines) - 1][] = $copy;
            }
        }

        if (count($lines) > 1 && $lines[count($lines) - 1] === []) {
            array_pop($lines);
        }

        return $lines;
    }

    /**
     * @return array<string, string>
     */
    private static function styleColors(string $style): array
    {
        $palettes = [
            'breezedark' => [
                'background' => '#232629',
                'text' => '#eff0f1',
                'keyword' => '#3daee9',
                'datatype' => '#27ae60',
                'string' => '#fdbc4b',
                'comment' => '#7f8c8d',
                'number' => '#f67400',
                'function' => '#8e44ad',
                'variable' => '#da4453',
                'attribute' => '#1cdc9a',
                'operator' => '#c0392b',
                'preprocessor' => '#16a085',
                'constant' => '#9b59b6',
                'information' => '#27ae60',
                'region' => '#3daee9',
                'warning' => '#da4453',
            ],
            'monochrome' => [
                'background' => 'transparent',
                'text' => 'inherit',
                'keyword' => 'inherit',
                'datatype' => 'inherit',
                'string' => 'inherit',
                'comment' => 'inherit',
                'number' => 'inherit',
                'function' => 'inherit',
                'variable' => 'inherit',
                'attribute' => 'inherit',
                'operator' => 'inherit',
                'preprocessor' => 'inherit',
                'constant' => 'inherit',
                'information' => 'inherit',
                'region' => 'inherit',
                'warning' => 'inherit',
            ],
            'zenburn' => [
                'background' => '#3f3f3f',
                'text' => '#dcdccc',
                'keyword' => '#f0dfaf',
                'datatype' => '#dfdfbf',
                'string' => '#cc9393',
                'comment' => '#7f9f7f',
                'number' => '#8cd0d3',
                'function' => '#efef8f',
                'variable' => '#dc8cc3',
                'attribute' => '#dfaf8f',
                'operator' => '#f0efd0',
                'preprocessor' => '#ffcfaf',
                'constant' => '#dca3a3',
                'information' => '#7f9f7f',
                'region' => '#efef8f',
                'warning' => '#e37170',
            ],
        ];

        $fallbacks = [
            'background' => '#f8f8f8',
            'text' => '#1f2328',
            'keyword' => '#005cc5',
            'datatype' => '#6f42c1',
            'string' => '#032f62',
            'comment' => '#6a737d',
            'number' => '#005cc5',
            'function' => '#6f42c1',
            'variable' => '#e36209',
            'attribute' => '#22863a',
            'operator' => '#d73a49',
            'preprocessor' => '#d73a49',
            'constant' => '#005cc5',
            'information' => '#22863a',
            'region' => '#6f42c1',
            'warning' => '#b31d28',
        ];

        if (isset($palettes[$style])) {
            return $palettes[$style];
        }

        if ($style === 'espresso') {
            return [
                ...$fallbacks,
                'background' => '#2a211c',
                'text' => '#f8f8f8',
                'keyword' => '#c5656b',
                'string' => '#a6c673',
                'comment' => '#7f7066',
                'function' => '#ffc66d',
            ];
        }

        if ($style === 'tango') {
            return [
                ...$fallbacks,
                'background' => '#fdf6e3',
                'keyword' => '#204a87',
                'string' => '#4e9a06',
                'comment' => '#8f5902',
                'function' => '#5c3566',
            ];
        }

        if ($style === 'kate' || $style === 'haddock') {
            return [
                ...$fallbacks,
                'background' => '#ffffff',
                'keyword' => '#007020',
                'string' => '#4070a0',
                'comment' => '#60a0b0',
                'function' => '#06287e',
            ];
        }

        return $fallbacks;
    }

    /**
     * @param array<string, mixed> $theme
     * @return array{color?:string, background?:string, bold?:bool, italic?:bool, underline?:bool}|null
     */
    private static function themeTokenStyle(array $theme, string $tokenName): ?array
    {
        $tokenStyles = self::arrayValue($theme, ['token-styles', 'tokenStyles', 'text-styles', 'textStyles']);
        if (!is_array($tokenStyles)) {
            return null;
        }

        foreach ($tokenStyles as $name => $style) {
            if (self::normalizedThemeTokenName((string) $name) === self::normalizedThemeTokenName($tokenName) && is_array($style)) {
                return self::parseTokenStyle($style, "token style {$tokenName}");
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $style
     * @return array{color?:string, background?:string, bold?:bool, italic?:bool, underline?:bool}
     */
    private static function parseTokenStyle(array $style, string $label): array
    {
        $parsed = [];
        $color = self::themeColor($style, ['text-color', 'textColor', 'color', 'tokenColor'], "{$label} text color");
        $background = self::themeColor($style, ['background-color', 'backgroundColor', 'background', 'tokenBackground'], "{$label} background color");
        if ($color !== null) {
            $parsed['color'] = $color;
        }
        if ($background !== null) {
            $parsed['background'] = $background;
        }

        foreach (['bold', 'italic', 'underline'] as $flag) {
            $value = self::arrayValue($style, [$flag, 'token' . ucfirst($flag)]);
            if ($value !== null) {
                $parsed[$flag] = self::themeBool($value, "{$label} {$flag}");
            }
        }

        return $parsed;
    }

    /**
     * @param array<string, mixed> $values
     * @param list<string> $names
     */
    private static function themeColor(array $values, array $names, string $label): ?string
    {
        $value = self::arrayValue($values, $names);

        return $value === null ? null : self::normalizeThemeColor($value, $label);
    }

    /**
     * @param array<string, mixed> $values
     * @param list<string> $names
     */
    private static function arrayValue(array $values, array $names): mixed
    {
        foreach ($names as $name) {
            if (array_key_exists($name, $values)) {
                return $values[$name];
            }
        }

        return null;
    }

    private static function normalizeThemeColor(mixed $value, string $label): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
            if (preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1) {
                return strtolower($value);
            }
            if (preg_match('/^#[0-9A-Fa-f]{3}$/', $value) === 1) {
                return strtolower('#' . $value[1] . $value[1] . $value[2] . $value[2] . $value[3] . $value[3]);
            }
            if (in_array(strtolower($value), ['inherit', 'transparent'], true)) {
                return strtolower($value);
            }
        }

        if (is_int($value) && $value >= 0 && $value <= 0xFFFFFF) {
            return sprintf('#%06x', $value);
        }

        if (is_array($value) && count($value) === 3) {
            $channels = array_values($value);
            foreach ($channels as $channel) {
                if (!is_int($channel) || $channel < 0 || $channel > 255) {
                    throw new \InvalidArgumentException("Invalid {$label} channel in Pandoc highlight theme");
                }
            }

            return sprintf('#%02x%02x%02x', $channels[0], $channels[1], $channels[2]);
        }

        throw new \InvalidArgumentException("Invalid {$label} in Pandoc highlight theme");
    }

    private static function themeBool(mixed $value, string $label): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) && ($value === 0 || $value === 1)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                '1', 'true', 'yes' => true,
                '0', 'false', 'no' => false,
                default => throw new \InvalidArgumentException("Invalid {$label} flag in Pandoc highlight theme"),
            };
        }

        throw new \InvalidArgumentException("Invalid {$label} flag in Pandoc highlight theme");
    }

    private static function tokenTypeFromThemeName(string $name): ?string
    {
        $normalized = self::normalizedThemeTokenName($name);
        if ($normalized === 'normal' || $normalized === 'normaltok') {
            return null;
        }

        return self::TOKEN_STYLE_ALIASES[$normalized] ?? self::TOKEN_STYLE_ALIASES[$normalized . 'tok'] ?? null;
    }

    private static function normalizedThemeTokenName(string $name): string
    {
        return strtolower(preg_replace('/[^A-Za-z0-9]/', '', $name) ?? '');
    }

    /**
     * @param array<string, string> $colors
     * @param array<string, array{color?:string, background?:string, bold?:bool, italic?:bool, underline?:bool}> $tokenStyles
     * @param list<string> $defaultDeclarations
     */
    private static function tokenStylesheetRule(
        string $selector,
        string $class,
        string $type,
        array $colors,
        array $tokenStyles,
        array $defaultDeclarations = []
    ): string {
        $style = $tokenStyles[$type] ?? null;
        $declarations = ['color: ' . ($style['color'] ?? $colors[$type])];
        if (isset($style['background'])) {
            $declarations[] = 'background-color: ' . $style['background'];
        }
        if (isset($style['bold'])) {
            if ($style['bold']) {
                $declarations[] = 'font-weight: 700';
            }
        } else {
            array_push($declarations, ...$defaultDeclarations);
        }
        if (isset($style['italic'])) {
            if ($style['italic']) {
                $declarations[] = 'font-style: italic';
            }
        } elseif (!isset($style['bold'])) {
            foreach ($defaultDeclarations as $declaration) {
                if (str_starts_with($declaration, 'font-style:')) {
                    $declarations[] = $declaration;
                }
            }
        }
        if (($style['underline'] ?? false) === true) {
            $declarations[] = 'text-decoration: underline';
        }

        return "{$selector} .{$class} { " . implode('; ', array_values(array_unique($declarations))) . '; }';
    }

    /**
     * @param array<string, string> $colors
     */
    private static function lineNumberStylesheetRule(array $colors): string
    {
        $declarations = [
            'content: counter(source-line)',
            'position: relative',
            'left: -1em',
            'text-align: right',
            'vertical-align: baseline',
            'border: none',
            'display: inline-block',
            'user-select: none',
            'padding: 0 4px',
            'width: 4em',
        ];

        if (isset($colors['lineNumber'])) {
            $declarations[] = 'color: ' . $colors['lineNumber'];
        }
        if (isset($colors['lineNumberBackground'])) {
            $declarations[] = 'background-color: ' . $colors['lineNumberBackground'];
        }

        return 'pre.numberSource code > span > a:first-child::before { ' . implode('; ', $declarations) . '; }';
    }

    private static function sanitizeClass(string $class): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '', $class) ?? '';
    }

    private static function sanitizeStyleName(string $style): string
    {
        $style = strtolower(trim($style));
        $style = preg_replace('/[^a-z0-9_-]+/', '-', $style) ?? '';
        $style = trim($style, '-_');

        return $style === '' ? 'custom-theme' : $style;
    }

    private static function sanitizeId(string $id): string
    {
        return preg_replace('/[^A-Za-z0-9_.:-]/', '', $id) ?? '';
    }

    private static function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
