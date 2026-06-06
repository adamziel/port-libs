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

    private const TOKEN_TITLES = [
        'attribute' => 'OtherTok',
        'comment' => 'CommentTok',
        'constant' => 'ConstantTok',
        'datatype' => 'DataTypeTok',
        'function' => 'FunctionTok',
        'information' => 'InformationTok',
        'keyword' => 'KeywordTok',
        'number' => 'DecValTok',
        'operator' => 'OperatorTok',
        'preprocessor' => 'PreprocessorTok',
        'region' => 'RegionMarkerTok',
        'string' => 'StringTok',
        'variable' => 'VariableTok',
        'warning' => 'AlertTok',
    ];

    private const LANGUAGE_ALIASES = [
        'apache' => 'apache',
        'apache-conf' => 'apache',
        'apache-config' => 'apache',
        'apache2' => 'apache',
        'apacheconf' => 'apache',
        'bash' => 'bash',
        'c' => 'c',
        'cargo-lock' => 'toml',
        'c#' => 'csharp',
        'cc' => 'cpp',
        'cpp' => 'cpp',
        'c++' => 'cpp',
        'cs' => 'csharp',
        'csharp' => 'csharp',
        'csx' => 'csharp',
        'cxx' => 'cpp',
        'cmake' => 'cmake',
        'cmake-in' => 'cmake',
        'cmakelists' => 'cmake',
        'cmakelists-txt' => 'cmake',
        'console' => 'bash',
        'containerfile' => 'dockerfile',
        'css' => 'css',
        'diff' => 'diff',
        'docker' => 'dockerfile',
        'dockerfile' => 'dockerfile',
        'dot' => 'dot',
        'git-diff' => 'diff',
        'graphviz' => 'dot',
        'gv' => 'dot',
        'h' => 'c',
        'hh' => 'cpp',
        'hpp' => 'cpp',
        'hxx' => 'cpp',
        'html' => 'html',
        'handlebars' => 'mustache',
        'hbs' => 'mustache',
        'hogan' => 'mustache',
        'hulk' => 'mustache',
        'html5' => 'html',
        'html-handlebars' => 'mustache',
        'html-mst' => 'mustache',
        'html-mu' => 'mustache',
        'html-rac' => 'mustache',
        'htaccess' => 'apache',
        'haskell' => 'haskell',
        'hs' => 'haskell',
        'httpd' => 'apache',
        'httpd-conf' => 'apache',
        'atom' => 'xml',
        'cjs' => 'javascript',
        'cfg' => 'ini',
        'editorconfig' => 'ini',
        'gitconfig' => 'ini',
        'gitmodules' => 'ini',
        'ini' => 'ini',
        'ecmascript' => 'javascript',
        'es6' => 'javascript',
        'javascript' => 'javascript',
        'javascript-react' => 'jsx',
        'java' => 'java',
        'js' => 'javascript',
        'jsx' => 'jsx',
        'json' => 'json',
        'kcfgc' => 'ini',
        'latex' => 'tex',
        'lhs' => 'haskell',
        'literate-haskell' => 'haskell',
        'literatehaskell' => 'haskell',
        'lua' => 'lua',
        'pandoc-lua' => 'lua',
        'commonmark' => 'markdown',
        'gfm' => 'markdown',
        'go' => 'go',
        'golang' => 'go',
        'markdown' => 'markdown',
        'mariadb' => 'sql',
        'md' => 'markdown',
        'mmd' => 'markdown',
        'multimarkdown' => 'markdown',
        'mustache' => 'mustache',
        'mysql' => 'sql',
        'nix' => 'nix',
        'nix-expr' => 'nix',
        'nix-shell' => 'nix',
        'pandoc' => 'markdown',
        'pandoc-markdown' => 'markdown',
        'patch' => 'diff',
        'pls' => 'ini',
        'gnumakefile' => 'makefile',
        'make' => 'makefile',
        'makefile' => 'makefile',
        'mk' => 'makefile',
        'mjs' => 'javascript',
        'mermaid' => 'mermaid',
        'mermaid-js' => 'mermaid',
        'mermaidjs' => 'mermaid',
        'node' => 'javascript',
        'nodejs' => 'javascript',
        'nginx' => 'nginx',
        'nginx-conf' => 'nginx',
        'nginx-config' => 'nginx',
        'nginxconf' => 'nginx',
        'perl' => 'perl',
        'pl' => 'perl',
        'pgsql' => 'sql',
        'plpgsql' => 'sql',
        'pm' => 'perl',
        'posh' => 'powershell',
        'powershell' => 'powershell',
        'php' => 'php',
        'postgres' => 'sql',
        'postgresql' => 'sql',
        'ps1' => 'powershell',
        'psd1' => 'powershell',
        'psm1' => 'powershell',
        'pwsh' => 'powershell',
        'py' => 'python',
        'py3' => 'python',
        'python' => 'python',
        'python3' => 'python',
        'q' => 'r',
        'r' => 'r',
        'ractive' => 'mustache',
        'rdf' => 'xml',
        'rss' => 'xml',
        'r-script' => 'r',
        'rscript' => 'r',
        'rake' => 'ruby',
        'rb' => 'ruby',
        'rest' => 'rst',
        'restructured-text' => 'rst',
        'restructuredtext' => 'rst',
        'ruby' => 'ruby',
        'rst' => 'rst',
        'sass' => 'sass',
        'scss' => 'scss',
        'rs' => 'rust',
        'rust' => 'rust',
        's' => 'r',
        'sh' => 'bash',
        'shell' => 'bash',
        'sql' => 'sql',
        'sqlite' => 'sql',
        'sqlite3' => 'sql',
        'svg' => 'xml',
        'tex' => 'tex',
        'toml' => 'toml',
        'ts' => 'typescript',
        'tsx' => 'tsx',
        'html+twig' => 'twig',
        'html-twig' => 'twig',
        'timber' => 'twig',
        'twig' => 'twig',
        'twig-html' => 'twig',
        'typescript' => 'typescript',
        'typescript-react' => 'tsx',
        'typescriptreact' => 'tsx',
        'udiff' => 'diff',
        'unified-diff' => 'diff',
        'xhtml' => 'html',
        'xml' => 'xml',
        'xsd' => 'xml',
        'xsl' => 'xslt',
        'xslt' => 'xslt',
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
     *   lineNumbering:array{enabled:bool, anchors:bool, start:int, lineIdPrefix:string},
     *   tokenTitles:bool
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
     *   themeJson?: string,
     *   tokenTitles?: bool|string|int
     * } $options
     * @return array{
     *   language:string,
     *   requestedLanguage:string,
     *   style:string,
     *   tokens:list<array{type:string, text:string, class:string}>,
     *   html:string,
     *   css:string,
     *   diagnostics:list<array{code:string, message:string}>,
     *   lineNumbering:array{enabled:bool, anchors:bool, start:int, lineIdPrefix:string},
     *   tokenTitles:bool
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
            'tokenTitles' => $lineOptions['tokenTitles'],
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
     *   containerClasses?: list<string>,
     *   tokenTitles?: bool
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
        $tokenTitles = (bool) ($options['tokenTitles'] ?? false);
        $html = '';

        foreach ($tokens as $token) {
            $html .= self::renderTokenHtml($token, $tokenTitles);
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
            'apache' => $this->tokenizeApacheConfig($code),
            'bash' => $this->tokenizeBash($code),
            'c', 'cpp' => $this->tokenizeC($code),
            'cmake' => $this->tokenizeCMake($code),
            'csharp' => $this->tokenizeCSharp($code),
            'css' => $this->tokenizeCss($code),
            'diff' => $this->tokenizeDiff($code),
            'dot' => $this->tokenizeDot($code),
            'dockerfile' => $this->tokenizeDockerfile($code),
            'go' => $this->tokenizeGo($code),
            'haskell' => $this->tokenizeHaskell($code),
            'html' => $this->tokenizeHtml($code),
            'ini' => $this->tokenizeIni($code),
            'java' => $this->tokenizeJava($code),
            'javascript' => $this->tokenizeJavaScript($code),
            'jsx' => $this->tokenizeJsx($code),
            'json' => $this->tokenizeJson($code),
            'lua' => $this->tokenizeLua($code),
            'makefile' => $this->tokenizeMakefile($code),
            'markdown' => $this->tokenizeMarkdown($code),
            'mermaid' => $this->tokenizeMermaid($code),
            'mustache' => $this->tokenizeMustache($code),
            'nginx' => $this->tokenizeNginx($code),
            'nix' => $this->tokenizeNix($code),
            'perl' => $this->tokenizePerl($code),
            'php' => $this->tokenizePhp($code),
            'powershell' => $this->tokenizePowerShell($code),
            'python' => $this->tokenizePython($code),
            'r' => $this->tokenizeR($code),
            'ruby' => $this->tokenizeRuby($code),
            'rst' => $this->tokenizeRest($code),
            'rust' => $this->tokenizeRust($code),
            'sass', 'scss' => $this->tokenizeScss($code),
            'sql' => $this->tokenizeSql($code),
            'tex' => $this->tokenizeTex($code),
            'toml' => $this->tokenizeToml($code),
            'twig' => $this->tokenizeTwig($code),
            'tsx' => $this->tokenizeTsx($code),
            'typescript' => $this->tokenizeTypeScript($code),
            'xml' => $this->tokenizeXml($code),
            'xslt' => $this->tokenizeXml($code),
            'yaml' => $this->tokenizeYaml($code),
            default => [['type' => 'text', 'text' => $code, 'class' => '']],
        };
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeApacheConfig(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^#[^\\n]*/'],
            ['keyword', '/^<\\/?(?:Directory|Files|If|IfDefine|IfModule|Location|VirtualHost)\\b/i'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['variable', '/^%\\{[A-Za-z_][A-Za-z0-9_:-]*\\}/'],
            ['attribute', '/^\\[[A-Za-z0-9_,=! -]+\\]/'],
            ['datatype', '/^\\bmod_[A-Za-z0-9_]+\\.c\\b/i'],
            ['keyword', '/^\\b(?:AddType|AllowOverride|AuthName|AuthType|BrowserMatch|CustomLog|Deny|DirectoryIndex|ErrorDocument|Header|IfModule|Include|Listen|LogFormat|Options|Order|Redirect|RedirectMatch|Require|RewriteBase|RewriteCond|RewriteEngine|RewriteRule|ServerAlias|ServerName|SetEnv|SetEnvIf|SetEnvIfNoCase|SetHandler|SetOutputFilter|SetInputFilter|Set|Unset|append|always|early|edit|edit\\*|env|expr|merge|onsuccess|set|unset)\\b/i'],
            ['constant', '/^\\b(?:All|Any|Denied|FollowSymLinks|Indexes|None|Off|On|SAMEORIGIN|Require|all|denied|forbidden|granted|last|redirect|skip)\\b/i'],
            ['string', '/^(?:\\/|\\.\\.?\\/)[^\\s\\[\\]#]*/'],
            ['number', '/^\\b\\d{3}\\b|^-?\\b\\d+(?:\\.\\d+)?\\b/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_.:-]*\\b/'],
            ['operator', '/^(?:!-?[A-Za-z]|-[A-Za-z]|!=|==|<=|>=|\\/?>|[{}()[\\];,.+*\\/%=!<>?:|&^-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeC(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['preprocessor', '/^#[ \\t]*(?:include|define|undef|if|ifdef|ifndef|elif|else|endif|pragma|error|warning)\\b[^\\n]*/'],
            ['string', '/^(?:u8|u|U|L)?"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^(?:u8|u|U|L)?'(?:\\\\.|[^'\\\\])+'/s"],
            ['keyword', '/^\\b(?:alignas|alignof|asm|auto|break|case|catch|class|concept|const|consteval|constexpr|constinit|continue|decltype|default|delete|do|else|enum|explicit|export|extern|for|friend|goto|if|inline|mutable|namespace|new|noexcept|operator|private|protected|public|register|requires|restrict|return|sizeof|static|static_assert|struct|switch|template|this|thread_local|throw|try|typedef|typename|union|using|virtual|volatile|while)\\b/'],
            ['constant', '/^\\b(?:false|NULL|nullptr|true)\\b/'],
            ['datatype', '/^\\b(?:bool|char|char8_t|char16_t|char32_t|double|float|int|long|short|signed|size_t|ssize_t|std|string|uint8_t|uint16_t|uint32_t|uint64_t|unsigned|void|wchar_t)\\b/'],
            ['number', '/^\\b(?:0[xX][0-9A-Fa-f]+|0[bB][01]+|\\d+(?:\\.\\d+)?(?:[eE][+-]?\\d+)?)[uUlLfF]*\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*(?=\\s*(?:[<({*&:]|\\b))/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:::|->\\*|->|\\.\\*|\\.\\.\\.|<<=|>>=|==|!=|<=|>=|&&|\\|\\||\\+\\+|--|<<|>>|[{}()[\\];,.+*\\/%=!<>?:&|^~-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeCMake(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^#\\[(=*)\\[[\\s\\S]*?\\]\\1\\]/'],
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^\\[(=*)\\[[\\s\\S]*?\\]\\1\\]/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['variable', '/^\\$<(?:(?:\\$<[^>\\n]+>)|[^>\\n])+>/'],
            ['variable', '/^\\$\\{[A-Za-z_][A-Za-z0-9_.:\\/-]*\\}/'],
            ['keyword', '/^\\b(?:AND|BYPRODUCTS|CACHE|COMMAND|COMPONENT|CONFIGURE_DEPENDS|DEPENDS|DESTINATION|EXISTS|EXPORT|FILES|FORCE|LANGUAGES|LIBRARY|MATCHES|NOT|OR|OUTPUT_VARIABLE|POLICY|PRIVATE|PUBLIC|REQUIRED|RESULT_VARIABLE|TARGETS|VERSION|WORKING_DIRECTORY)\\b/i'],
            ['datatype', '/^\\b(?:ALIAS|BOOL|C|CUDA|CXX|FILEPATH|IMPORTED|INTERFACE|INTERNAL|MODULE|OBJC|OBJCXX|OBJECT|PATH|SHARED|STATIC|STRING)\\b/i'],
            ['constant', '/^\\b(?:FALSE|NO|NOTFOUND|OFF|ON|TRUE|YES)\\b/i'],
            ['number', '/^-?\\b\\d+(?:\\.\\d+)*\\b/'],
            ['attribute', '/^[A-Za-z_][A-Za-z0-9_]*(?=\\s*=)/'],
            ['function', '/^\\b(?:add_compile_definitions|add_custom_command|add_custom_target|add_executable|add_library|cmake_minimum_required|configure_file|execute_process|file|find_library|find_package|find_path|find_program|foreach|if|include|install|list|message|option|project|set|target_compile_definitions|target_include_directories|target_link_libraries|target_sources)\\b(?=\\s*\\()/i'],
            ['function', '/^\\b(?:add|cmake|target)_[A-Za-z0-9_]+\\b(?=\\s*\\()/i'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_:-]*\\b/'],
            ['operator', '/^(?:\\.\\.\\.|==|!=|<=|>=|[{}()[\\];,.+*\\/%=!<>?:&|^-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeNginx(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['variable', '/^\\$\\{?[A-Za-z_][A-Za-z0-9_]*\\}?/'],
            ['keyword', '/^\\b(?:add_header|access_log|allow|auth_basic|auth_basic_user_file|break|client_body_buffer_size|client_max_body_size|deny|error_log|expires|fastcgi_index|fastcgi_param|fastcgi_pass|fastcgi_read_timeout|fastcgi_split_path_info|gzip|if|include|index|listen|location|proxy_pass|proxy_read_timeout|proxy_redirect|proxy_set_header|return|rewrite|root|server|server_name|set|try_files|types)\\b/i'],
            ['constant', '/^\\b(?:always|break|default_server|http2|last|off|on|permanent|redirect|ssl)\\b/i'],
            ['number', '/^-?\\b\\d+(?:\\.\\d+)?(?:[kKmMgGsSmMhHdD])?\\b/'],
            ['string', '/^unix:[^\\s{};#\'"$]+/'],
            ['string', '/^(?:\\/|\\.\\.?\\/|@)[^\\s{};#\'"$]*/'],
            ['string', '/^\\\\\\.[A-Za-z0-9_.-]*\\$?/'],
            ['string', '/^(?:\\\\.|[\\^$.*+?()[\\]|-])+/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_.-]*\\b/'],
            ['operator', '/^(?:\\^~|~\\*?|==|!=|<=|>=|[{}()[\\];,.+*\\/%=!<>?:|&^-])/'],
        ]);
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
            ['string', '/^<<<[ \\t]*([\\\'"]?)([A-Za-z_][A-Za-z0-9_]*)\\1[ \\t]*(?:\\r?\\n[\\s\\S]*?\\r?\\n[ \\t]*\\2;?)/'],
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
    private function tokenizeNix(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^#[^\\n]*/'],
            ['string', "/^''[\\s\\S]*?''/"],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['constant', '/^<[^>\\n]+>/'],
            ['string', '/^(?:\\.{1,2}|~)?\\/[A-Za-z0-9_+.,%:@=-][A-Za-z0-9_+.,%:@=\\/-]*/'],
            ['keyword', '/^\\b(?:assert|else|if|in|inherit|let|or|rec|then|with)\\b/'],
            ['constant', '/^\\b(?:false|null|true)\\b/'],
            ['function', '/^\\b(?:abort|baseNameOf|derivation|dirOf|fetchTarball|import|map|removeAttrs|throw|toString)\\b(?=\\s|$)/'],
            ['datatype', '/^\\bbuiltins\\b/'],
            ['number', '/^-?\\b\\d+(?:\\.\\d+)?\\b/'],
            ['attribute', '/^[A-Za-z_][A-Za-z0-9_-]*(?=\\s*=)/'],
            ['variable', '/^[A-Za-z_][A-Za-z0-9_-]*/'],
            ['operator', '/^(?:\\$\\{|\\+\\+|==|!=|<=|>=|&&|\\|\\||->|\\/\\/|[{}()[\\];,.?:=+*\\/!<>|-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizePerl(string $code): array
    {
        return $this->scan($code, [
            ['keyword', '/^#![^\\n]*/'],
            ['comment', '/^=(?:head[1-6]|over|back|item|for|begin|end|pod|cut)\\b[^\\n]*/'],
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^s\\/(?:\\\\.|[^\\/\\\\])*\\/(?:\\\\.|[^\\/\\\\])*\\/[msixpadluncgeor]*/'],
            ['string', '/^q[qwxr]?\\{(?:\\\\.|[^}\\\\])*\\}/s'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['keyword', '/^\\b(?:BEGIN|END|__DATA__|__END__|__FILE__|__LINE__|__PACKAGE__|break|continue|default|defer|do|each|else|elsif|for|foreach|given|if|last|local|my|next|our|package|redo|return|state|sub|unless|until|when|while)\\b/'],
            ['keyword', '/^\\b(?:bytes|constant|diagnostics|english|filetest|integer|less|locale|open|sigtrap|strict|subs|utf8|vars|warnings)\\b/'],
            ['constant', '/^\\b(?:undef)\\b/'],
            ['number', '/^-?\\b(?:0[xX]_?[0-9A-Fa-f](?:_?[0-9A-Fa-f])*|0[bB]_?[01](?:_?[01])*|0[0-7](?:_?[0-7])*|\\d(?:_?\\d)*(?:\\.\\d(?:_?\\d)*)?(?:[eE][+-]?_?\\d(?:_?\\d)*)?)\\b/'],
            ['operator', '/^\\b(?:and|cmp|eq|ge|gt|le|lt|ne|not|or)\\b/'],
            ['function', '/^\\b(?:bless|chomp|close|defined|delete|die|exists|grep|join|keys|lc|map|open|print|printf|push|require|say|shift|sort|split|uc|use|values|warn)\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*(?:::[A-Z][A-Za-z0-9_]*)*/'],
            ['variable', '/^(?:\\$[#_]?[A-Za-z_][A-Za-z0-9_]*|[@%][A-Za-z_][A-Za-z0-9_]*|\\$\\d+|[@%]_|\\$_|[$@%]\\{[^}\\n]+\\})/'],
            ['attribute', '/^[A-Za-z_][A-Za-z0-9_]*(?=\\})/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*(?:\\(|\\{))/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:::|=>|->|=~|!~|==|!=|<=|>=|&&|\\|\\||\\/\\/|\\.\\.\\.?|\\+=|-=|\\*=|\\/=|%=|[{}()[\\];,.+*\\/%=!<>?:&|^~-])/'],
        ]);
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
    private function tokenizeJava(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['string', '/^"""[\\s\\S]*?"""/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])'/s"],
            ['attribute', '/^@[A-Za-z_][A-Za-z0-9_.]*/'],
            ['keyword', '/^\\b(?:abstract|assert|break|case|catch|class|continue|default|do|else|enum|exports|extends|final|finally|for|if|implements|import|instanceof|interface|module|native|new|non-sealed|open|opens|package|permits|private|protected|provides|public|record|requires|return|sealed|static|strictfp|super|switch|synchronized|this|throw|throws|to|transient|transitive|try|uses|var|volatile|while|with|yield)\\b/'],
            ['constant', '/^\\b(?:false|null|true)\\b/'],
            ['datatype', '/^\\b(?:boolean|byte|char|double|float|int|long|short|void)\\b/'],
            ['number', '/^\\b(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|0[bB][01](?:_?[01])*|\\d(?:_?\\d)*(?:\\.\\d(?:_?\\d)*)?(?:[eE][+-]?\\d(?:_?\\d)*)?)[fFdDlL]?\\b/'],
            ['datatype', '/^\\b(?:BigDecimal|BigInteger|Boolean|Byte|Character|Class|Double|Exception|Files|Float|HashMap|HashSet|IOException|Integer|List|Long|Map|Objects|Optional|Path|Pattern|Record|Set|String|StringBuilder|URI|UUID)\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*(?=\\s*(?:[<({.]|\\b))/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:::|->|>>>?=?|<<=?|==|!=|<=|>=|&&|\\|\\||\\+\\+|--|\\.\\.\\.|[{}()[\\];,.+*\\/%=!<>?:&|^~-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeCSharp(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['preprocessor', '/^#[ \\t]*(?:define|elif|else|endif|endregion|error|if|line|nullable|pragma|region|undef|warning)\\b[^\\n]*/'],
            ['string', '/^(?:\\$@|@\\$|\\$|@)?"(?:""|\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])'/s"],
            ['attribute', '/^\\[(?:[A-Za-z_][A-Za-z0-9_]*:\\s*)?[A-Za-z_][A-Za-z0-9_.]*(?:\\([^\\]\\n]*\\))?\\]/'],
            ['keyword', '/^\\b(?:abstract|as|async|await|base|break|case|catch|checked|class|const|continue|default|delegate|do|else|enum|event|explicit|extern|finally|fixed|for|foreach|get|global|goto|if|implicit|in|init|interface|internal|is|lock|namespace|new|operator|out|override|params|partial|private|protected|public|readonly|record|ref|required|return|sealed|set|sizeof|stackalloc|static|struct|switch|this|throw|try|typeof|unchecked|unsafe|using|var|virtual|volatile|when|where|while|with|yield)\\b/'],
            ['constant', '/^\\b(?:false|null|true)\\b/'],
            ['datatype', '/^\\b(?:bool|byte|char|decimal|double|dynamic|float|int|long|nint|nuint|object|sbyte|short|string|uint|ulong|ushort|void)\\b/'],
            ['number', '/^\\b(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|0[bB][01](?:_?[01])*|\\d(?:_?\\d)*(?:\\.\\d(?:_?\\d)*)?(?:[eE][+-]?\\d(?:_?\\d)*)?)[mMdDfFlLuU]*\\b/'],
            ['datatype', '/^\\b(?:Action|CancellationToken|Console|DateTime|Dictionary|Exception|Func|Guid|IEnumerable|IReadOnlyList|JsonDocument|JsonElement|JsonPropertyName|JsonSerializer|List|Math|Memory|Nullable|Regex|ReadOnlySpan|Span|StringBuilder|Task|Uri)\\b/'],
            ['function', '/^\\b(?:Deserialize|IsNullOrWhiteSpace|RenderAsync|Trim|WriteLineAsync)\\b(?=\\s*(?:<[^>\\n]+>\\s*)?\\()/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*(?=\\s*(?:[<({.]|\\b))/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:=>|\\?\\?|\\?\\.|::|==|!=|<=|>=|&&|\\|\\||\\+\\+|--|<<=?|>>=?|\\.\\.\\.?|[{}()[\\];,.+*\\/%=!<>?:&|^~-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeGo(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['string', '/^`[\\s\\S]*?`/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])+'/s"],
            ['keyword', '/^\\b(?:break|case|chan|const|continue|default|defer|else|fallthrough|for|func|go|goto|if|import|interface|map|package|range|return|select|struct|switch|type|var)\\b/'],
            ['constant', '/^\\b(?:false|iota|nil|true)\\b/'],
            ['datatype', '/^\\b(?:any|bool|byte|comparable|complex64|complex128|error|float32|float64|int|int8|int16|int32|int64|rune|string|uint|uint8|uint16|uint32|uint64|uintptr)\\b/'],
            ['number', '/^\\b(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|0[bB][01](?:_?[01])*|0[oO][0-7](?:_?[0-7])*|\\d(?:_?\\d)*(?:\\.\\d(?:_?\\d)*)?(?:[eE][+-]?\\d(?:_?\\d)*)?)(?:i)?\\b/'],
            ['function', '/^\\b(?:append|cap|close|complex|copy|delete|imag|len|make|new|panic|print|println|real|recover)\\b(?=\\s*\\()/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*(?=\\s*(?:[({*\\[]|\\b))/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:\\.\\.\\.|:=|<-|&\\^=?|<<=?|>>=?|==|!=|<=|>=|&&|\\|\\||\\+\\+|--|[{}()[\\];,.+*\\/%=!<>?:&|^~-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizePowerShell(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^<#[\\s\\S]*?#>/'],
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^@"[\\s\\S]*?(?:\\r?\\n)"@/'],
            ['string', "/^@'[\\s\\S]*?(?:\\r?\\n)'@/"],
            ['string', '/^"(?:`.|[^"`])*"/s'],
            ['string', "/^'(?:''|[^'])*'/s"],
            ['constant', '/^\\$(?:false|null|true)\\b/i'],
            ['datatype', '/^\\[(?:bool|byte|char|datetime|decimal|double|float|hashtable|int|long|object|pscustomobject|regex|sbyte|scriptblock|short|string|switch|uint|ulong|ushort|void|xml|[A-Za-z_][A-Za-z0-9_.]*(?:\\[\\])?)\\]/i'],
            ['attribute', '/^\\[[A-Za-z_][A-Za-z0-9_.]*(?:\\([^\\]\\n]*\\))?\\]/'],
            ['function', '/^\\b[A-Za-z][A-Za-z0-9]*-[A-Za-z][A-Za-z0-9]*\\b/'],
            ['keyword', '/^\\b(?:begin|break|catch|class|continue|data|do|dynamicparam|else|elseif|end|exit|filter|finally|for|foreach|from|function|if|in|inlineScript|parallel|param|process|return|sequence|switch|throw|trap|try|until|using|var|while|workflow)\\b/i'],
            ['operator', '/^(?:@\\{|@\\(|::|=>|\\.\\.|&&|\\|\\||-(?:and|contains|eq|f|ge|gt|in|is|isnot|join|le|like|lt|match|ne|not|notcontains|notin|notlike|notmatch|or|replace|split)\\b)/i'],
            ['attribute', '/^-{1,2}[A-Za-z][A-Za-z0-9_-]*/'],
            ['number', '/^-?\\b(?:0[xX][0-9A-Fa-f]+|\\d+(?:\\.\\d+)?)\\b/'],
            ['variable', '/^\\$(?:Alias|Env|Function|Global|Local|Private|Script|Using|Variable|Workflow):[A-Za-z_][A-Za-z0-9_:-]*/i'],
            ['variable', '/^\\$\\{[^}\\n]+\\}|^\\$[_?^$]|^\\$[A-Za-z_][A-Za-z0-9_]*/'],
            ['attribute', '/^[A-Za-z_][A-Za-z0-9_-]*(?=\\s*=)/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^[{}()[\\];,.+*\\/%=!<>?:|&@`-]/'],
        ]);
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
    private function tokenizeIni(string $code): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($code);

        while ($offset < $length) {
            $nextNewline = strpos($code, "\n", $offset);
            if ($nextNewline === false) {
                $line = substr($code, $offset);
                $offset = $length;
            } else {
                $line = substr($code, $offset, $nextNewline - $offset);
                $offset = $nextNewline + 1;
            }

            $this->tokenizeIniLine($line, $tokens);
            if ($nextNewline !== false) {
                $this->appendToken($tokens, 'text', "\n");
            }
        }

        return $tokens;
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function tokenizeIniLine(string $line, array &$tokens): void
    {
        if ($line === '') {
            return;
        }

        if (preg_match('/^([ \t]*)([;#].*)$/', $line, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            $this->appendToken($tokens, 'comment', $matches[2]);
            return;
        }

        if (preg_match('/^([ \t]*)(\[[^\]\r]*\])(.*)$/', $line, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            $this->appendToken($tokens, 'keyword', $matches[2]);
            $this->appendIniTrailingText($matches[3], $tokens);
            return;
        }

        $assignmentOffset = strpos($line, '=');
        if ($assignmentOffset === false) {
            $this->appendToken($tokens, 'text', $line);
            return;
        }

        $key = substr($line, 0, $assignmentOffset);
        if (preg_match('/^([ \t]*)(.*?)([ \t]*)$/', $key, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            if ($matches[2] !== '') {
                $this->appendToken($tokens, 'datatype', $matches[2]);
            }
            $this->appendToken($tokens, 'text', $matches[3]);
        } else {
            $this->appendToken($tokens, 'datatype', $key);
        }

        $this->appendToken($tokens, 'operator', '=');
        $this->appendIniValue(substr($line, $assignmentOffset + 1), $tokens);
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function appendIniTrailingText(string $text, array &$tokens): void
    {
        if ($text === '') {
            return;
        }

        if (preg_match('/^([ \t]+)([;#].*)$/', $text, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            $this->appendToken($tokens, 'comment', $matches[2]);
            return;
        }

        $this->appendToken($tokens, 'text', $text);
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function appendIniValue(string $value, array &$tokens): void
    {
        if ($value === '') {
            return;
        }

        if (preg_match('/^([ \t]*)(.*?)([ \t]*)$/', $value, $matches) !== 1) {
            $this->appendToken($tokens, 'string', $value);
            return;
        }

        $this->appendToken($tokens, 'text', $matches[1]);
        $body = $matches[2];
        if ($body !== '') {
            if (self::isIniNumber($body)) {
                $this->appendToken($tokens, 'number', $body);
            } elseif (self::isIniPhpErrorConstantNegation($body)) {
                $this->appendToken($tokens, 'operator', '~');
                $this->appendToken($tokens, 'keyword', substr($body, 1));
            } elseif (self::isIniKeyword($body)) {
                $this->appendToken($tokens, 'keyword', $body);
            } else {
                $this->appendToken($tokens, 'string', $body);
            }
        }
        $this->appendToken($tokens, 'text', $matches[3]);
    }

    private static function isIniNumber(string $value): bool
    {
        return preg_match('/^-?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/', $value) === 1;
    }

    private static function isIniPhpErrorConstantNegation(string $value): bool
    {
        $constant = substr($value, 1);

        return str_starts_with($value, '~')
            && str_starts_with(strtolower($constant), 'e_')
            && self::isIniKeyword($constant);
    }

    private static function isIniKeyword(string $value): bool
    {
        static $keywords = [
            'default',
            'defaults',
            'e_all',
            'e_compile_error',
            'e_compile_warning',
            'e_core_error',
            'e_core_warning',
            'e_error',
            'e_notice',
            'e_parse',
            'e_strict',
            'e_user_error',
            'e_user_notice',
            'e_user_warning',
            'e_warning',
            'false',
            'localhost',
            'no',
            'normal',
            'null',
            'off',
            'on',
            'true',
            'yes',
        ];

        return in_array(strtolower($value), $keywords, true);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeToml(string $code): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($code);

        while ($offset < $length) {
            $nextNewline = strpos($code, "\n", $offset);
            if ($nextNewline === false) {
                $line = substr($code, $offset);
                $offset = $length;
            } else {
                $line = substr($code, $offset, $nextNewline - $offset);
                $offset = $nextNewline + 1;
            }

            $this->tokenizeTomlLine($line, $tokens);
            if ($nextNewline !== false) {
                $this->appendToken($tokens, 'text', "\n");
            }
        }

        return $tokens;
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function tokenizeTomlLine(string $line, array &$tokens): void
    {
        if ($line === '') {
            return;
        }

        if (preg_match('/^([ \t]*)(#.*)$/', $line, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            $this->appendToken($tokens, 'comment', $matches[2]);
            return;
        }

        if (preg_match('/^([ \t]*)(\[\[?[A-Za-z0-9_."\047 -]+(?:\.[A-Za-z0-9_."\047 -]+)*\]?\])(.*)$/', $line, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            $this->appendToken($tokens, 'keyword', $matches[2]);
            $this->appendTomlTrailingText($matches[3], $tokens);
            return;
        }

        $assignmentOffset = self::tomlAssignmentOffset($line);
        if ($assignmentOffset === null) {
            $this->appendTomlValue($line, $tokens);
            return;
        }

        $key = substr($line, 0, $assignmentOffset);
        $value = substr($line, $assignmentOffset + 1);
        if (preg_match('/^([ \t]*)(.*?)([ \t]*)$/', $key, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            if ($matches[2] !== '') {
                $this->appendTomlKey($matches[2], $tokens);
            }
            $this->appendToken($tokens, 'text', $matches[3]);
        } else {
            $this->appendTomlKey($key, $tokens);
        }

        $this->appendToken($tokens, 'operator', '=');
        $this->appendTomlValue($value, $tokens);
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function appendTomlTrailingText(string $text, array &$tokens): void
    {
        if ($text === '') {
            return;
        }

        if (preg_match('/^([ \t]+)(#.*)$/', $text, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            $this->appendToken($tokens, 'comment', $matches[2]);
            return;
        }

        $this->appendToken($tokens, 'text', $text);
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function appendTomlKey(string $key, array &$tokens): void
    {
        $this->scanInto($key, [
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['operator', '/^\\./'],
            ['datatype', '/^[A-Za-z0-9_-]+/'],
        ], $tokens);
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function appendTomlValue(string $value, array &$tokens): void
    {
        if ($value === '') {
            return;
        }

        $this->scanInto($value, [
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^"""[\\s\\S]*?"""/'],
            ['string', "/^'''[\\s\\S]*?'''/"],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['constant', '/^\\b\\d{4}-\\d{2}-\\d{2}(?:[T ]\\d{2}:\\d{2}:\\d{2}(?:\\.\\d+)?(?:Z|[+-]\\d{2}:\\d{2})?)?\\b/'],
            ['constant', '/^\\b\\d{2}:\\d{2}:\\d{2}(?:\\.\\d+)?\\b/'],
            ['constant', '/^\\b(?:false|true)\\b/'],
            ['number', '/^[+-]?(?:0[xX][0-9A-Fa-f]+(?:_[0-9A-Fa-f]+)*|0[oO][0-7]+(?:_[0-7]+)*|0[bB][01]+(?:_[01]+)*|(?:\\d+(?:_\\d+)*)(?:\\.\\d+(?:_\\d+)*)?(?:[eE][+-]?\\d+(?:_\\d+)*)?|inf|nan)\\b/i'],
            ['datatype', '/^[A-Za-z0-9_-]+(?=\\s*=)/'],
            ['operator', '/^[\\[\\]{},=]/'],
            ['operator', '/^\\./'],
            ['variable', '/^[A-Za-z0-9_-]+/'],
        ], $tokens);
    }

    private static function tomlAssignmentOffset(string $line): ?int
    {
        $quote = null;
        $length = strlen($line);

        for ($offset = 0; $offset < $length; $offset++) {
            $char = $line[$offset];
            if ($quote !== null) {
                if ($char === '\\' && $quote === '"' && $offset + 1 < $length) {
                    $offset++;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char === '#') {
                return null;
            }

            if ($char === '=') {
                return $offset;
            }
        }

        return null;
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
    private function tokenizeXml(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^<!--[\\s\\S]*?-->/'],
            ['string', '/^<!\\[CDATA\\[[\\s\\S]*?\\]\\]>/'],
            ['preprocessor', '/^<\\?[A-Za-z_][A-Za-z0-9_.:-]*/'],
            ['preprocessor', '/^<!(?:DOCTYPE|ELEMENT|ENTITY|ATTLIST|NOTATION)\\b/i'],
            ['keyword', '/^<\\/?[A-Za-z_][A-Za-z0-9_.:-]*/'],
            ['attribute', '/^(?:xmlns(?::[A-Za-z_][A-Za-z0-9_.:-]*)?|[A-Za-z_:][A-Za-z0-9_.:-]*)(?=\\s*=)/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['constant', '/^&(?:#[0-9]+|#x[0-9A-Fa-f]+|[A-Za-z_:][A-Za-z0-9_.:-]*);/'],
            ['number', '/^-?\\b\\d+(?:\\.\\d+)?\\b/'],
            ['operator', '/^(?:\\?>|\\/?>|=|\\[|\\])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeTwig(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\{#[\\s\\S]*?#\\}/'],
            ['comment', '/^<!--[\\s\\S]*?-->/'],
            ['operator', '/^\\{\\{[-~]?|^\\{%[-~]?|^[-~]?\\}\\}|^[-~]?%\\}/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['function', '/^\\b(?:abs|attribute|batch|block|capitalize|column|constant|convert_encoding|country_name|currency_name|cycle|date|date_modify|default|dump|escape|filter|first|format|function|include|join|json_encode|keys|last|length|lower|map|max|merge|min|nl2br|number_format|parent|random|range|reduce|replace|reverse|round|slice|sort|source|split|striptags|template_from_string|trim|upper|url_encode)(?=\\s*\\()/'],
            ['function', '/^\\b(?:e|escape|raw)(?=\\s*(?:[|,)}\\]\\s]|$))/'],
            ['keyword', '/^\\b(?:apply|autoescape|block|do|else|elseif|embed|endapply|endautoescape|endblock|endembed|endfilter|endfor|endif|endmacro|endsandbox|endset|endspaceless|endverbatim|extends|filter|flush|for|from|if|import|in|is|macro|only|sandbox|set|use|verbatim|with)\\b/'],
            ['constant', '/^\\b(?:false|null|none|true)\\b/i'],
            ['keyword', '/^<\\/?[A-Za-z][A-Za-z0-9:-]*/'],
            ['attribute', '/^(?:aria-[A-Za-z0-9_.:-]+|data-[A-Za-z0-9_.:-]+|alt|class|for|href|id|name|rel|role|src|style|target|title|type|value)(?=\\s*=(?!=))/i'],
            ['attribute', '/^[A-Za-z_][A-Za-z0-9_-]*(?=\\s*:)/'],
            ['number', '/^-?\\b\\d+(?:\\.\\d+)?\\b/'],
            ['variable', '/^[A-Za-z_][A-Za-z0-9_]*/'],
            ['operator', '/^(?:\\.\\.|==|!=|<=|>=|=>|\\?\\?|\\?:|\\bis\\s+not\\b|\\bnot\\s+in\\b|\\band\\b|\\bor\\b|\\bnot\\b|[{}()[\\].,:|=+*\\/%!<>?~-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeMustache(string $code): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($code);

        while ($offset < $length) {
            $next = strpos($code, '{{', $offset);
            if ($next === false) {
                $this->scanMustacheHtml(substr($code, $offset), $tokens);
                break;
            }

            if ($next > $offset) {
                $this->scanMustacheHtml(substr($code, $offset, $next - $offset), $tokens);
            }

            $expression = self::consumeMustacheExpression($code, $next);
            $this->appendMustacheExpression($expression, $tokens);
            $offset = $next + strlen($expression);
        }

        return $tokens;
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function scanMustacheHtml(string $code, array &$tokens): void
    {
        $this->scanInto($code, [
            ['comment', '/^<!--[\\s\\S]*?-->/'],
            ['string', '/^<!\\[CDATA\\[[\\s\\S]*?\\]\\]>/'],
            ['preprocessor', '/^<\\?[A-Za-z_][A-Za-z0-9_.:-]*/'],
            ['preprocessor', '/^<!(?:DOCTYPE|ELEMENT|ENTITY|ATTLIST|NOTATION)\\b/i'],
            ['keyword', '/^<\\/?[A-Za-z][A-Za-z0-9:-]*/'],
            ['attribute', '/^(?:aria-[A-Za-z0-9_.:-]+|data-[A-Za-z0-9_.:-]+|[A-Za-z_:][A-Za-z0-9_.:-]*)(?=\\s*=)/i'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['constant', '/^&(?:#[0-9]+|#x[0-9A-Fa-f]+|[A-Za-z_:][A-Za-z0-9_.:-]*);/'],
            ['number', '/^-?\\b\\d+(?:\\.\\d+)?\\b/'],
            ['operator', '/^(?:\\/?>|=|\\[|\\])/'],
        ], $tokens);
    }

    private static function consumeMustacheExpression(string $code, int $offset): string
    {
        foreach ([
            ['{{!--', '--}}'],
            ['{{{{', '}}}}'],
            ['{{{', '}}}'],
            ['{{!', '}}'],
            ['{{', '}}'],
        ] as [$open, $close]) {
            if (!str_starts_with(substr($code, $offset), $open)) {
                continue;
            }

            $end = strpos($code, $close, $offset + strlen($open));
            if ($end === false) {
                return substr($code, $offset);
            }

            return substr($code, $offset, $end - $offset + strlen($close));
        }

        return '{{';
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function appendMustacheExpression(string $expression, array &$tokens): void
    {
        if (str_starts_with($expression, '{{!--') || str_starts_with($expression, '{{!')) {
            $this->appendToken($tokens, 'comment', $expression);
            return;
        }

        [$open, $close] = match (true) {
            str_starts_with($expression, '{{{{') => ['{{{{', '}}}}'],
            str_starts_with($expression, '{{{') => ['{{{', '}}}'],
            default => ['{{', '}}'],
        };

        if (!str_ends_with($expression, $close)) {
            $this->appendToken($tokens, 'warning', $expression);
            return;
        }

        $this->appendToken($tokens, 'operator', $open);
        $inner = substr($expression, strlen($open), -strlen($close));
        $this->scanInto($inner, [
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['keyword', '/^(?:else\\s+(?:if|unless|with|each(?:-in)?)|else|if|unless|with|each(?:-in)?|block|partial|yield)\\b/'],
            ['function', '/^(?:default|formatDate|include|link|log|lookup|t|translate|wp_kses_post)(?=\\s|\\()/'],
            ['attribute', '/^[A-Za-z_:\\*#\\(\\[][\\)\\]\\w.:_-]*(?=\\s*=)/'],
            ['constant', '/^(?:false|null|true|undefined)\\b/i'],
            ['number', '/^-?\\b\\d+(?:\\.\\d+)?\\b/'],
            ['variable', '/^@?[A-Za-z_$:?\\x80-\\xff][A-Za-z0-9_$:?\\-\\x80-\\xff]*(?:\\.[A-Za-z_$:?\\x80-\\xff][A-Za-z0-9_$:?\\-\\x80-\\xff]*)*/'],
            ['operator', '/^(?:=>|==|!=|<=|>=|\\|\\||&&|[{}()[\\].,=|~#^\\/><&$*!?:+-])/'],
        ], $tokens);
        $this->appendToken($tokens, 'operator', $close);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeMermaid(string $code): array
    {
        return $this->scan($code, [
            ['preprocessor', '/^%%\\{[\\s\\S]*?\\}%%/'],
            ['comment', '/^%%[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['keyword', '/^\\b(?:flowchart|graph|sequenceDiagram|classDiagram|stateDiagram(?:-v2)?|erDiagram|journey|gantt|pie|gitGraph|mindmap|timeline|quadrantChart|requirementDiagram|C4Context|sankey-beta)\\b/i'],
            ['constant', '/^\\b(?:BT|LR|RL|TB|TD)\\b/'],
            ['keyword', '/^\\b(?:accTitle|accDescr|activate|actor|alt|and|as|autonumber|class|classDef|click|critical|dateFormat|deactivate|else|end|exclude|gitGraph|loop|note|opt|over|participant|par|rect|section|style|subgraph|title|todayMarker)\\b/i'],
            ['attribute', '/^[A-Za-z_][A-Za-z0-9_-]*(?=\\s*:)/'],
            ['string', '/^\\[[^\\]\\n]*\\]/'],
            ['string', '/^\\([^()\\n]*\\)/'],
            ['string', '/^\\{[^}\\n]*\\}/'],
            ['string', '/^\\|[^|\\n]*\\|/'],
            ['number', '/^-?\\b\\d+(?:\\.\\d+)?\\b/'],
            ['constant', '/^\\b(?:false|true)\\b/i'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_-]*(?=\\s*\\()/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_-]*\\b/'],
            ['operator', '/^(?:<-->|<--|-->|---|==>|===|-.->|-\\.->|--[ox]|[ox]--|\\|>|::|[{}()[\\];,.+*\\/%=!<>?:&|#~-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeSql(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^--[^\\n]*/'],
            ['comment', '/^#[^\\n]*/'],
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['string', "/^'(?:''|[^'])*'/s"],
            ['string', '/^\\$\\$[\\s\\S]*?\\$\\$/'],
            ['string', '/^\\$([A-Za-z_][A-Za-z0-9_]*)\\$[\\s\\S]*?\\$\\1\\$/'],
            ['attribute', '/^"(?:[^"\\\\]|\\\\.)*"/s'],
            ['attribute', '/^`(?:``|[^`])*`/'],
            ['attribute', '/^\\[(?:]]|[^\\]])*\\]/'],
            ['constant', '/^\\b(?:current_date|current_time|current_timestamp|false|null|true)\\b/i'],
            ['datatype', '/^\\b(?:bigint|binary|blob|bool|boolean|char|date|datetime|decimal|double|float|int|integer|json|jsonb|longblob|longtext|mediumblob|mediumint|mediumtext|numeric|plpgsql|real|record|regclass|serial|smallint|text|time|timestamp|tinyblob|tinyint|tinytext|trigger|uuid|varchar|void)\\b/i'],
            ['keyword', '/^\\b(?:add|alter|and|as|asc|auto_increment|before|begin|between|by|cascade|case|check|collate|column|commit|conflict|constraint|create|declare|default|delete|desc|distinct|do|drop|duplicate|each|else|end|escape|execute|exists|for|foreign|from|function|group|having|if|in|index|inner|insert|into|is|join|key|language|left|like|limit|not|notice|offset|on|or|order|outer|perform|primary|raise|references|replace|returning|returns|right|rollback|row|select|set|start|table|then|transaction|trigger|unique|unsigned|update|using|values|vacuum|when|where|with|without)\\b/i'],
            ['number', '/^\\b\\d+(?:\\.\\d+)?\\b/'],
            ['variable', '/^(?::[A-Za-z_][A-Za-z0-9_]*|@[A-Za-z_][A-Za-z0-9_]*|\\$\\d+)\\b/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:<>|!=|<=|>=|==|\\|\\||::|:=|[(),;.*=<>+\\/%?-])/'],
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
            ['comment', '/^#![^\\n]*/'],
            ['string', '/^`(?:\\\\.|[^`\\\\])*`/s'],
            ['string', '/^\\/(?:\\\\.|\\[[^\\]\\n]*(?:\\\\.[^\\]\\n]*)*\\]|[^\\/\\\\\\n])+\\/[dgimsuvy]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['keyword', '/^\\b(?:as|async|await|break|case|catch|class|const|continue|debugger|default|delete|do|else|export|extends|finally|for|from|function|get|if|import|in|instanceof|let|new|of|return|set|static|switch|this|throw|try|typeof|var|void|while|with|yield)\\b/'],
            ['constant', '/^\\b(?:false|null|true|undefined)\\b/'],
            ['datatype', '/^\\b(?:Array|ArrayBuffer|BigInt|Boolean|Buffer|Console|Date|Document|Element|Error|Event|FormData|Function|HTMLElement|Intl|JSON|Map|Math|Number|Object|Promise|Proxy|Reflect|RegExp|Set|String|Symbol|URL|WeakMap|WeakSet|Window|XMLHttpRequest|console|document|global|globalThis|process|window)\\b/'],
            ['number', '/^\\b(?:0[xX][0-9A-Fa-f]+|0[bB][01]+|0[oO][0-7]+|\\d+(?:\\.\\d+)?(?:[eE][+-]?\\d+)?n?)\\b/'],
            ['attribute', '/^[A-Za-z_$][A-Za-z0-9_$]*(?=\\s*:)/'],
            ['function', '/^\\b[A-Za-z_$][A-Za-z0-9_$]*(?=\\s*\\()/'],
            ['variable', '/^\\b[A-Za-z_$][A-Za-z0-9_$]*\\b/'],
            ['operator', '/^(?:=>|===|!==|==|!=|<=|>=|&&|\\|\\||\\?\\?|\\?\\.|\\.\\.\\.|\\+\\+|--|[{}()[\\];,.+*\\/%=!<>?:&|^-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeJsx(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['string', '/^`(?:\\\\.|[^`\\\\])*`/s'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['function', '/^<\\/?[A-Z][A-Za-z0-9_.$:-]*/'],
            ['keyword', '/^<\\/?[a-z][A-Za-z0-9_.$:-]*/'],
            ['attribute', '/^[A-Za-z_:][A-Za-z0-9_.:-]*(?=\\s*=)/'],
            ['keyword', '/^\\b(?:async|await|break|case|catch|class|const|continue|default|do|else|export|extends|finally|for|from|function|if|import|let|new|return|switch|throw|try|typeof|var|while|yield)\\b/'],
            ['constant', '/^\\b(?:false|null|true|undefined)\\b/'],
            ['number', '/^\\b(?:0[xX][0-9A-Fa-f]+|0[bB][01]+|\\d+(?:\\.\\d+)?(?:[eE][+-]?\\d+)?)\\b/'],
            ['function', '/^\\b[A-Za-z_$][A-Za-z0-9_$]*(?=\\s*\\()/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_$]*(?=\\s*(?:[<({.]|\\b))/'],
            ['variable', '/^\\b[A-Za-z_$][A-Za-z0-9_$]*\\b/'],
            ['operator', '/^(?:<\\/>|\\/?>|=>|===|!==|==|!=|<=|>=|&&|\\|\\||\\.\\.\\.|[{}()[\\];,.+*\\/%=!<>?:|-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeTsx(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['string', '/^`(?:\\\\.|[^`\\\\])*`/s'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['operator', '/^<(?=[A-Z][A-Za-z0-9_$]*>\\s*[),=>])/'],
            ['function', '/^<\\/?[A-Z][A-Za-z0-9_.$:-]*/'],
            ['keyword', '/^<\\/?[a-z][A-Za-z0-9_.$:-]*/'],
            ['attribute', '/^@[A-Za-z_$][A-Za-z0-9_$]*/'],
            ['keyword', '/^\\b(?:abstract|as|asserts|async|await|break|case|catch|class|const|constructor|continue|declare|default|do|else|enum|export|extends|finally|for|from|function|get|if|implements|import|in|infer|interface|is|keyof|let|module|namespace|new|of|private|protected|public|readonly|return|satisfies|set|static|switch|this|throw|try|type|typeof|var|while|yield)\\b/'],
            ['datatype', '/^\\b(?:any|bigint|boolean|never|null|number|object|string|symbol|undefined|unknown|void)\\b/'],
            ['constant', '/^\\b(?:false|true)\\b/'],
            ['number', '/^\\b(?:0[xX][0-9A-Fa-f]+|0[bB][01]+|\\d+(?:\\.\\d+)?(?:[eE][+-]?\\d+)?)\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_$]*(?=\\s*(?:[<:={}\\/.]|\\b))/'],
            ['attribute', '/^[A-Za-z_:][A-Za-z0-9_.:-]*(?=\\s*=)/'],
            ['function', '/^\\b[A-Za-z_$][A-Za-z0-9_$]*(?=\\s*\\()/'],
            ['variable', '/^\\b[A-Za-z_$][A-Za-z0-9_$]*\\b/'],
            ['operator', '/^(?:<\\/>|\\/?>|=>|===|!==|==|!=|<=|>=|&&|\\|\\||\\?\\?|\\?\\.|\\.\\.\\.|[{}()[\\];,.+*\\/%=!<>?:&|-])/'],
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
    private function tokenizeR(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['attribute', '/^`(?:\\\\.|[^`\\\\])+`/s'],
            ['keyword', '/^\\b(?:break|else|for|function|if|in|next|repeat|switch|while)\\b/'],
            ['constant', '/^\\b(?:FALSE|Inf|NA|NA_character_|NA_complex_|NA_integer_|NA_real_|NULL|NaN|TRUE)\\b/'],
            ['number', '/^-?(?:0[xX][0-9A-Fa-f]+|(?:\\d+\\.\\d*|\\.\\d+|\\d+)(?:[eE][+-]?\\d+)?)(?:[Li])?\\b/'],
            ['attribute', '/^[A-Za-z_.][A-Za-z0-9_.]*(?=\\s*:?\\s*=(?!=))/'],
            ['function', '/^[A-Za-z_.][A-Za-z0-9_.]*(?=\\s*\\()/'],
            ['variable', '/^[A-Za-z_.][A-Za-z0-9_.]*/'],
            ['attribute', '/^(?:<<-|<-|->>|->|=(?!(?:=|>))|:=)/'],
            ['operator', '/^(?:\\*\\*|<=|>=|==|=>|!=|\\|>|\\|\\||&&|:::|::|%[^%\\s]+%|[{}()[\\],;+*\\/<>!|&:^@$~.-])/'],
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
    private function tokenizeRust(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['attribute', '/^#!?\\[[^\\]\\n]*\\]/'],
            ['string', '/^r#+".*?"#+/s'],
            ['string', '/^(?:b|br|r)?"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^b?'(?:\\\\.|[^'\\\\])'/s"],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*!(?=\\s*[\\({\\[])/'],
            ['keyword', '/^\\b(?:as|async|await|break|const|continue|crate|dyn|else|enum|extern|fn|for|if|impl|in|let|loop|match|mod|move|mut|pub|ref|return|self|static|struct|super|trait|type|unsafe|use|where|while)\\b/'],
            ['constant', '/^\\b(?:Err|None|Ok|Some|false|true)\\b/'],
            ['datatype', '/^\\b(?:Box|Debug|HashMap|HashSet|Option|Path|PathBuf|Result|Self|String|Value|Vec|bool|char|f32|f64|i8|i16|i32|i64|i128|isize|str|u8|u16|u32|u64|u128|usize)\\b/'],
            ['attribute', "/^'[A-Za-z_][A-Za-z0-9_]*/"],
            ['number', '/^\\b(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|0[bB][01](?:_?[01])*|\\d(?:_?\\d)*(?:\\.\\d(?:_?\\d)*)?(?:[eE][+-]?\\d(?:_?\\d)*)?)(?:[iu](?:8|16|32|64|128|size)|f(?:32|64))?\\b/'],
            ['datatype', '/^\\b[A-Z][A-Za-z0-9_]*(?=\\s*(?:[<({]|::|\\b))/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*(?:\\(|::<))/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:::|->|=>|==|!=|<=|>=|&&|\\|\\||\\.\\.|[{}()[\\];,.+*\\/%=!<>?:&|^~-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeLua(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^--\\[(=*)\\[[\\s\\S]*?\\]\\1\\]/'],
            ['comment', '/^--[^\\n]*/'],
            ['string', '/^\\[(=*)\\[[\\s\\S]*?\\]\\1\\]/'],
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
        $tokens = [];
        $offset = 0;
        $length = strlen($code);
        $heredocDelimiter = null;

        while ($offset < $length) {
            $nextNewline = strpos($code, "\n", $offset);
            if ($nextNewline === false) {
                $line = substr($code, $offset);
                $offset = $length;
            } else {
                $line = substr($code, $offset, $nextNewline - $offset);
                $offset = $nextNewline + 1;
            }

            if ($heredocDelimiter !== null) {
                if (ltrim(rtrim($line, "\r"), "\t") === $heredocDelimiter) {
                    $this->appendToken($tokens, 'region', $line);
                    $heredocDelimiter = null;
                } else {
                    $this->appendToken($tokens, 'string', $line);
                }
            } else {
                $this->tokenizeBashLine($line, $tokens);
                $heredocDelimiter = self::bashHeredocDelimiter($line);
            }

            if ($nextNewline !== false) {
                $this->appendToken($tokens, 'text', "\n");
            }
        }

        return $tokens;
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function tokenizeBashLine(string $line, array &$tokens): void
    {
        $this->scanInto($line, [
            ['keyword', '/^#![^\\n]*/'],
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['operator', '/^<<-?/'],
            ['operator', '/^\\[\\[|^\\]\\]/'],
            ['attribute', '/^--[A-Za-z0-9][A-Za-z0-9_-]*/'],
            ['operator', '/^-[A-Za-z][A-Za-z0-9-]*/'],
            ['variable', '/^[A-Za-z_][A-Za-z0-9_]*(?=\\s*=)/'],
            ['variable', '/^\\$[A-Za-z_][A-Za-z0-9_]*|^\\$\\{[^}]+\\}/'],
            ['keyword', '/^\\b(?:case|do|done|elif|else|esac|fi|for|function|if|in|then|while)\\b/'],
            ['constant', '/^\\b(?:false|true)\\b/'],
            ['number', '/^\\b\\d+(?:\\.\\d+)?\\b/'],
            ['function', '/^\\b(?:awk|basename|cat|cd|chmod|chown|composer|cp|curl|dirname|echo|env|find|getopts|grep|jq|make|mkdir|mv|npm|php|printf|read|rm|rsync|sed|set|sh|sort|tar|tee|test|touch|tr|wp)(?=\\s|$|[;&|<>])/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_.-]*\\b/'],
            ['operator', '/^(?:\\$\\(|\\)\\)|;;|&&|\\|\\||>>|[{}()[\\];|&<>=$])/'],
        ], $tokens);
    }

    private static function bashHeredocDelimiter(string $line): ?string
    {
        if (preg_match('/(?:^|\\s)<<-?\\s*(?:\\\'([A-Za-z_][A-Za-z0-9_]*)\\\'|"([A-Za-z_][A-Za-z0-9_]*)"|\\\\?([A-Za-z_][A-Za-z0-9_]*))/', $line, $matches) !== 1) {
            return null;
        }

        foreach ([1, 2, 3] as $index) {
            if (isset($matches[$index]) && $matches[$index] !== '') {
                return $matches[$index];
            }
        }

        return null;
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeCss(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['keyword', '/^@[A-Za-z][A-Za-z0-9_-]*/'],
            ['keyword', '/^!important\\b/i'],
            ['constant', '/^#[0-9A-Fa-f]{3,8}\\b/'],
            ['datatype', '/^\\.[A-Za-z_-][A-Za-z0-9_-]*/'],
            ['datatype', '/^#[A-Za-z_-][A-Za-z0-9_-]*/'],
            ['function', '/^::?[A-Za-z_-][A-Za-z0-9_-]*/'],
            ['function', '/^[A-Za-z_-][A-Za-z0-9_-]*(?=\\s*\\()/'],
            ['attribute', '/^--[A-Za-z0-9_-]+|^[A-Za-z-]+(?=\\s*:\\s)/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['number', '/^-?(?:\\d+\\.\\d+|\\.\\d+|\\d+)(?:ch|cm|deg|dppx|em|ex|fr|in|mm|ms|pc|pt|px|rem|s|turn|vh|vmax|vmin|vw|%)?\\b/i'],
            ['keyword', '/^\\b(?:auto|block|border-box|center|flex|grid|inherit|initial|inline|none|relative|repeat|solid|transparent|unset)\\b/i'],
            ['datatype', '/^[A-Za-z][A-Za-z0-9_-]*(?=(?:[#.:\\s,{>+~]|$))/'],
            ['operator', '/^(?:~=|\\|=|\\^=|\\$=|\\*=|::|[{}()[\\]:;,.#>+~=*!\\/|-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeScss(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['keyword', '/^@(?>at-root|content|debug|each|else|error|extend|for|forward|function|if|import|include|mixin|return|use|warn|while)\\b/'],
            ['keyword', '/^!default\\b|^!global\\b|^!important\\b/i'],
            ['variable', '/^\\$[A-Za-z_-][A-Za-z0-9_-]*/'],
            ['operator', '/^#\\{/'],
            ['constant', '/^#[0-9A-Fa-f]{3,8}\\b/'],
            ['datatype', '/^%[A-Za-z_-][A-Za-z0-9_-]*/'],
            ['datatype', '/^\\.[A-Za-z_-][A-Za-z0-9_-]*/'],
            ['datatype', '/^#[A-Za-z_-][A-Za-z0-9_-]*/'],
            ['operator', '/^&/'],
            ['function', '/^::?[A-Za-z_-][A-Za-z0-9_-]*/'],
            ['function', '/^(?:adjust-color|append|darken|if|lighten|list\\.[A-Za-z_-]+|map\\.[A-Za-z_-]+|map-get|map-has-key|math\\.[A-Za-z_-]+|meta\\.[A-Za-z_-]+|mix|nth|rgba|selector\\.[A-Za-z_-]+|string\\.[A-Za-z_-]+|transparentize)(?=\\s*\\()/'],
            ['function', '/^[A-Za-z_-][A-Za-z0-9_-]*(?=\\s*\\()/'],
            ['attribute', '/^--[A-Za-z0-9_-]+|^[A-Za-z-]+(?=\\s*:\\s)/'],
            ['constant', '/^\\b(?:false|null|true)\\b/'],
            ['number', '/^-?(?:\\d+\\.\\d+|\\.\\d+|\\d+)(?:ch|cm|deg|dppx|em|ex|fr|in|mm|ms|pc|pt|px|rem|s|turn|vh|vmax|vmin|vw|%)?(?=$|[^A-Za-z0-9_])/i'],
            ['keyword', '/^\\b(?:and|as|auto|block|border-box|center|flex|from|grid|inherit|initial|inline|none|not|only|or|relative|repeat|solid|through|to|transparent|unset)\\b/i'],
            ['datatype', '/^[A-Za-z][A-Za-z0-9_-]*(?=(?:[#.:\\s,{>+~]|$))/'],
            ['operator', '/^(?:\\.\\.\\.|~=|\\|=|\\^=|\\$=|\\*=|==|!=|<=|>=|::|=>|[{}()[\\]:;,.#>+~=*!\\/|%-])/'],
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
    private function tokenizeDot(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^\\/\\*[\\s\\S]*?\\*\\//'],
            ['comment', '/^\\/\\/[^\\n]*/'],
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', '/^<[^>\\n]+>/'],
            ['keyword', '/^\\b(?:digraph|edge|graph|node|strict|subgraph)\\b/i'],
            ['attribute', '/^\\b(?:URL|arrowhead|arrowsize|arrowtail|bgcolor|center|color|constraint|decorateP|dir|distortion|fillcolor|fontcolor|fontname|fontsize|headclip|headlabel|height|label|labelangle|labeldistance|labelfontcolor|labelfontname|labelfontsize|layer|layers|margin|mclimit|minlen|name|nodesep|nslimit|ordering|orientation|page|pagedir|peripheries|port_label_distance|rank|rankdir|ranksep|ratio|regular|rotate|samehead|sametail|shape|shapefile|sides|size|skew|style|tailclip|taillabel|weight|width)(?=\\s*=|\\b)/'],
            ['constant', '/^\\b(?:BT|LR|RL|TB|back|bold|box|dashed|diamond|dotted|ellipse|filled|forward|invis|none|normal|oval|plaintext|record|rounded|same|solid|true|false)\\b/i'],
            ['number', '/^-?\\b(?:\\d+\\.\\d+|\\.\\d+|\\d+)\\b/'],
            ['variable', '/^\\b[A-Za-z_][A-Za-z0-9_]*\\b/'],
            ['operator', '/^(?:->|--|[{}()[\\];,=<>:+*\\/|-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeDockerfile(string $code): array
    {
        return $this->scan($code, [
            ['attribute', '/^#\\s*(?:syntax|escape)=[^\\n]+/i'],
            ['comment', '/^#[^\\n]*/'],
            ['keyword', '/^\\b(?:ADD|ARG|AS|CMD|COPY|ENTRYPOINT|ENV|EXPOSE|FROM|HEALTHCHECK|LABEL|MAINTAINER|ONBUILD|RUN|SHELL|STOPSIGNAL|USER|VOLUME|WORKDIR)\\b/i'],
            ['operator', '/^--[A-Za-z][A-Za-z0-9-]*(?:=(?:"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|[^\\s\\\\]+))?/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['variable', '/^\\$[A-Za-z_][A-Za-z0-9_]*|^\\$\\{[^}]+\\}/'],
            ['constant', '/^\\b(?:false|null|true)\\b/i'],
            ['number', '/^\\b\\d+(?:\\.\\d+)?\\b/'],
            ['attribute', '/^\\b[A-Za-z_][A-Za-z0-9_.-]*(?=\\s*=)/'],
            ['function', '/^\\b(?:cat|cd|chmod|chown|composer|cp|curl|echo|find|grep|make|mkdir|mv|npm|php|rm|sed|set|sh|tar|test|wp)(?=\\s)/'],
            ['operator', '/^(?:\\\\(?=\\r?\\n)|&&|\\|\\||[{}()[\\];,.+*\\%=!<>?:|&\\\\-])/'],
        ]);
    }

    /**
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeMakefile(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^"(?:\\\\.|[^"\\\\])*"/s'],
            ['string', "/^'(?:\\\\.|[^'\\\\])*'/s"],
            ['keyword', '/^\\b(?:define|else|endef|endif|export|if|ifdef|ifeq|ifndef|ifneq|include|override|private|sinclude|unexport|vpath)\\b/'],
            ['variable', '/^\\$\\([A-Za-z_][A-Za-z0-9_.-]*\\)|^\\$\\{[A-Za-z_][A-Za-z0-9_.-]*\\}|^\\$[@<^?*+%]/'],
            ['attribute', '/^[A-Za-z_][A-Za-z0-9_.-]*(?=\\s*(?::=|\\?=|\\+=|!=|=))/'],
            ['region', '/^(?:\\.[A-Za-z0-9_.\\/-]+|[A-Za-z0-9_%.\\/-]+)(?=\\s*:(?![=]))/'],
            ['variable', '/^\\.[A-Za-z0-9_.\\/-]+/'],
            ['operator', '/^(?::=|\\?=|\\+=|!=|::|&&|\\|\\||\\|>|-{1,2}[A-Za-z][A-Za-z0-9-]*|[{}()[\\];,.+*\\/%=!<>?:|&@\\\\-])/'],
            ['number', '/^\\b\\d+(?:\\.\\d+)*\\b/'],
            ['function', '/^\\b(?:cat|cd|chmod|chown|composer|cp|curl|echo|find|grep|make|mkdir|mv|npm|php|rm|sed|set|sh|tar|test|wp)(?=\\s|$)/'],
            ['variable', '/^(?:\\.[A-Za-z0-9_.\\/-]+|\\b[A-Za-z_][A-Za-z0-9_.\\/-]*\\b)/'],
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
     * @return list<array{type:string, text:string, class:string}>
     */
    private function tokenizeRest(string $code): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($code);
        $pendingCodeBlock = false;
        $codeBlockIndent = null;

        while ($offset < $length) {
            $nextNewline = strpos($code, "\n", $offset);
            if ($nextNewline === false) {
                $line = substr($code, $offset);
                $offset = $length;
            } else {
                $line = substr($code, $offset, $nextNewline - $offset);
                $offset = $nextNewline + 1;
            }

            if ($codeBlockIndent !== null) {
                if (trim($line) === '') {
                    $this->appendToken($tokens, 'text', $line);
                } elseif (str_starts_with($line, $codeBlockIndent)) {
                    $this->appendToken($tokens, 'datatype', $line);
                } else {
                    $codeBlockIndent = null;
                    $this->tokenizeRestLine($line, $tokens);
                    $pendingCodeBlock = self::restLineStartsCodeBlock($line);
                }
            } elseif ($pendingCodeBlock) {
                if (trim($line) === '') {
                    $this->appendToken($tokens, 'text', $line);
                } elseif (preg_match('/^([ \t]+)\\S/', $line, $matches) === 1) {
                    $codeBlockIndent = $matches[1];
                    $this->appendToken($tokens, 'datatype', $line);
                } else {
                    $pendingCodeBlock = false;
                    $this->tokenizeRestLine($line, $tokens);
                    $pendingCodeBlock = self::restLineStartsCodeBlock($line);
                }
            } else {
                $this->tokenizeRestLine($line, $tokens);
                $pendingCodeBlock = self::restLineStartsCodeBlock($line);
            }

            if ($nextNewline !== false) {
                $this->appendToken($tokens, 'text', "\n");
            }
        }

        return $tokens;
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function tokenizeRestLine(string $line, array &$tokens): void
    {
        if ($line === '') {
            return;
        }

        if (preg_match('/^([ \t]*)(\\.\\.(?:[ \t].*|$))$/', $line, $matches) === 1
            && preg_match('/^\\.\\.[ \t]+(?:[A-Za-z0-9_.-]+(?::[A-Za-z0-9_.-]+)*::|__?:|_[A-Za-z0-9_.:+ -]+:)(?:[ \t]|$)/', $matches[2]) !== 1
        ) {
            $this->appendToken($tokens, 'text', $matches[1]);
            $this->appendToken($tokens, 'comment', $matches[2]);
            return;
        }

        if (preg_match('/^([ \t]*)(\\.\\.[ \t]+(?:\\[(?:\\d+|#|\\*|#[A-Za-z0-9_.:+-]+)\\]|\\|[A-Za-z0-9_.:+ -]+\\|[ \t]+[A-Za-z0-9_.:+-]+::|(?:__|_[A-Za-z0-9_.:+ -]+):|[A-Za-z0-9_.-]+(?::[A-Za-z0-9_.-]+)*::)(?:[ \t].*|$))$/', $line, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            $this->appendToken($tokens, 'datatype', $matches[2]);
            return;
        }

        if (preg_match('/^([ \t]*)(:{1}[^:\\n]+:)(.*)$/', $line, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            $this->appendToken($tokens, 'function', $matches[2]);
            $this->appendRestInline($matches[3], $tokens);
            return;
        }

        if (preg_match('/^([ \t]*)([=\\-~`^"\'+#*]{3,})([ \t]*)$/', $line, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            $this->appendToken($tokens, 'region', $matches[2]);
            $this->appendToken($tokens, 'text', $matches[3]);
            return;
        }

        if (preg_match('/^([ \t]*)(?:([-+*])|([0-9]+[.)]))([ \t]+)(.*)$/', $line, $matches) === 1) {
            $this->appendToken($tokens, 'text', $matches[1]);
            $this->appendToken($tokens, 'operator', ($matches[2] !== '' ? $matches[2] : $matches[3]) . $matches[4]);
            $this->appendRestInline($matches[5], $tokens);
            return;
        }

        $this->appendRestInline($line, $tokens);
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function appendRestInline(string $text, array &$tokens): void
    {
        $this->scanInto($text, [
            ['datatype', '/^``[^`\\n]+``/'],
            ['function', '/^\\|[^|\\n]+\\|/'],
            ['function', '/^_`[^`\\n]+`/'],
            ['attribute', '/^\\[[A-Za-z0-9_.:+#*-]+\\]_/'],
            ['keyword', '/^:[A-Za-z0-9_.+-]+:(?=`)/'],
            ['constant', '/^`[^`\\n]+`(?=:[A-Za-z0-9_.+-]+:)/'],
            ['attribute', '/^`[^`\\n]+`__?/'],
            ['constant', '/^`[^`\\n]+`/'],
            ['attribute', '/^(?:https?|ftp):\\/\\/[^\\s<>"\'`)]+[^\\s!"\'`(),.:;<>?~\\]\\}]/'],
            ['keyword', '/^\\*\\*[^*\\n][^\\n]*?\\*\\*/'],
            ['variable', '/^\\*[^*\\n][^\\n]*?\\*/'],
            ['datatype', '/^::(?=\\s*$)/'],
            ['operator', '/^[\\\\`*_{}\\[\\]():|<>-]/'],
        ], $tokens);
    }

    private static function restLineStartsCodeBlock(string $line): bool
    {
        return preg_match('/^\\s*\\.\\.[ \t]+code(?:-block)?::(?:[ \t]|$)/', $line) === 1
            || preg_match('/::\\s*$/', $line) === 1;
    }

    /**
     * @param list<array{0:string, 1:string}> $patterns
     * @return list<array{type:string, text:string, class:string}>
     */
    private function scan(string $code, array $patterns): array
    {
        $tokens = [];
        $this->scanInto($code, $patterns, $tokens);

        return $tokens;
    }

    /**
     * @param list<array{0:string, 1:string}> $patterns
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    private function scanInto(string $code, array $patterns, array &$tokens): void
    {
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
            'title-attributes',
            'titleattributes',
            'token-title-attributes',
            'token-titleattributes',
            'tokentitle-attributes',
            'tokentitleattributes',
            'token-titles',
            'tokentitles',
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
     *   containerClasses: list<string>,
     *   tokenTitles: bool
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
        if (!is_array($attributes)) {
            $attributes = [];
        }
        $start = 1;
        foreach (['startFrom', 'start-from'] as $name) {
            if (isset($attributes[$name]) && preg_match('/^-?\d+$/', (string) $attributes[$name]) === 1) {
                $start = (int) $attributes[$name];
                break;
            }
        }

        $id = self::sanitizeId((string) ($options['id'] ?? ''));
        $tokenTitles = self::optionBoolean($options['tokenTitles'] ?? null);
        if ($tokenTitles === null) {
            $tokenTitles = in_array('title-attributes', $normalized, true)
                || in_array('titleattributes', $normalized, true)
                || in_array('token-title-attributes', $normalized, true)
                || in_array('token-titleattributes', $normalized, true)
                || in_array('tokentitle-attributes', $normalized, true)
                || in_array('tokentitleattributes', $normalized, true)
                || in_array('token-titles', $normalized, true)
                || in_array('tokentitles', $normalized, true)
                || self::attributeBoolean($attributes, [
                    'data-title-attributes',
                    'data-token-title-attributes',
                    'data-token-titles',
                    'title-attributes',
                    'titleAttributes',
                    'token-title-attributes',
                    'tokenTitleAttributes',
                    'token-titles',
                    'tokenTitles',
                ]);
        }

        return [
            'numberLines' => in_array('number', $normalized, true)
                || in_array('numberlines', $normalized, true)
                || in_array('number-lines', $normalized, true),
            'lineAnchors' => in_array('lineanchors', $normalized, true)
                || in_array('line-anchors', $normalized, true),
            'startNumber' => $start,
            'lineIdPrefix' => $id === '' ? '' : $id . '-',
            'containerClasses' => $classes,
            'tokenTitles' => $tokenTitles,
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
     *   containerClasses?: list<string>,
     *   tokenTitles?: bool
     * } $options
     */
    private static function renderLineNumberedHtml(array $tokens, string $language, array $options): string
    {
        $numberLines = (bool) ($options['numberLines'] ?? false);
        $tokenTitles = (bool) ($options['tokenTitles'] ?? false);
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
                $line .= self::renderTokenHtml($token, $tokenTitles);
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

    /**
     * @param array{type?:string, text?:string, class?:string} $token
     */
    private static function renderTokenHtml(array $token, bool $tokenTitles): string
    {
        $text = self::escapeHtml((string) ($token['text'] ?? ''));
        $class = self::sanitizeClass((string) ($token['class'] ?? ''));
        if ($class === '') {
            return $text;
        }

        $attributes = ' class="' . $class . '"';
        $title = $tokenTitles ? self::tokenTitle((string) ($token['type'] ?? '')) : null;
        if ($title !== null) {
            $attributes .= ' title="' . self::escapeHtml($title) . '"';
        }

        return '<span' . $attributes . '>' . $text . '</span>';
    }

    private static function tokenTitle(string $type): ?string
    {
        return self::TOKEN_TITLES[$type] ?? null;
    }

    /**
     * @param array<string, mixed> $attributes
     * @param list<string> $names
     */
    private static function attributeBoolean(array $attributes, array $names): bool
    {
        foreach ($names as $name) {
            if (array_key_exists($name, $attributes)) {
                return self::optionBoolean($attributes[$name]) ?? true;
            }
        }

        return false;
    }

    private static function optionBoolean(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) && ($value === 0 || $value === 1)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                '', '1', 'true', 'yes', 'on' => true,
                '0', 'false', 'no', 'off' => false,
                default => null,
            };
        }

        return null;
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
