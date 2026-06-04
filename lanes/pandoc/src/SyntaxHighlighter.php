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
        'keyword' => 'kw',
        'number' => 'dv',
        'operator' => 'op',
        'preprocessor' => 'pp',
        'string' => 'st',
        'variable' => 'va',
        'warning' => 'al',
    ];

    private const LANGUAGE_ALIASES = [
        'bash' => 'bash',
        'console' => 'bash',
        'css' => 'css',
        'html' => 'html',
        'html5' => 'html',
        'javascript' => 'javascript',
        'js' => 'javascript',
        'json' => 'json',
        'php' => 'php',
        'postgres' => 'sql',
        'postgresql' => 'sql',
        'py' => 'python',
        'python' => 'python',
        'sh' => 'bash',
        'shell' => 'bash',
        'sql' => 'sql',
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

    /**
     * @return array{
     *   language:string,
     *   requestedLanguage:string,
     *   style:string,
     *   tokens:list<array{type:string, text:string, class:string}>,
     *   html:string,
     *   css:string,
     *   diagnostics:list<array{code:string, message:string}>
     * }
     */
    public function highlightCodeBlock(AstNode $codeBlock, string $style = 'pygments'): array
    {
        if ($codeBlock->type !== 'code_block') {
            throw new \InvalidArgumentException('Syntax highlighter expects a code_block node');
        }

        $requested = self::languageFromCodeBlock($codeBlock);

        return $this->highlight((string) $codeBlock->attr('text', ''), $requested, $style);
    }

    /**
     * @return array{
     *   language:string,
     *   requestedLanguage:string,
     *   style:string,
     *   tokens:list<array{type:string, text:string, class:string}>,
     *   html:string,
     *   css:string,
     *   diagnostics:list<array{code:string, message:string}>
     * }
     */
    public function highlight(string $code, string $language = '', string $style = 'pygments'): array
    {
        $requested = trim($language);
        $canonicalLanguage = self::normalizeLanguage($requested) ?? '';
        $canonicalStyle = self::normalizeStyle($style);
        $diagnostics = [];

        if ($requested !== '' && $canonicalLanguage === '') {
            $diagnostics[] = [
                'code' => 'unsupported-language',
                'message' => "No bounded native syntax definition is available for '{$requested}'",
            ];
        }

        $tokens = $canonicalLanguage === ''
            ? [['type' => 'text', 'text' => $code, 'class' => '']]
            : $this->tokenize($code, $canonicalLanguage);

        return [
            'language' => $canonicalLanguage,
            'requestedLanguage' => $requested,
            'style' => $canonicalStyle,
            'tokens' => $tokens,
            'html' => self::renderHighlightedHtml($tokens, $canonicalLanguage),
            'css' => self::stylesheet($canonicalStyle),
            'diagnostics' => $diagnostics,
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

        $rules = [
            "{$selector} { background: {$colors['background']}; color: {$colors['text']}; }",
            "{$selector} .kw { color: {$colors['keyword']}; font-weight: 600; }",
            "{$selector} .dt { color: {$colors['datatype']}; }",
            "{$selector} .st { color: {$colors['string']}; }",
            "{$selector} .co { color: {$colors['comment']}; font-style: italic; }",
            "{$selector} .dv { color: {$colors['number']}; }",
            "{$selector} .fu { color: {$colors['function']}; }",
            "{$selector} .va { color: {$colors['variable']}; }",
            "{$selector} .ot { color: {$colors['attribute']}; }",
            "{$selector} .op { color: {$colors['operator']}; }",
            "{$selector} .pp { color: {$colors['preprocessor']}; }",
            "{$selector} .cn { color: {$colors['constant']}; }",
            "{$selector} .al { color: {$colors['warning']}; font-weight: 600; }",
        ];

        return implode("\n", $rules);
    }

    /**
     * @param list<array{type:string, text:string, class:string}> $tokens
     */
    public static function renderHighlightedHtml(array $tokens, string $language = ''): string
    {
        $language = self::normalizeLanguage($language) ?? '';
        $classes = trim('sourceCode' . ($language === '' ? '' : ' ' . self::sanitizeClass($language)));
        $html = '';

        foreach ($tokens as $token) {
            $text = self::escapeHtml((string) ($token['text'] ?? ''));
            $class = self::sanitizeClass((string) ($token['class'] ?? ''));
            $html .= $class === '' ? $text : '<span class="' . $class . '">' . $text . '</span>';
        }

        return '<pre class="' . $classes . '"><code class="' . $classes . '">' . $html . '</code></pre>';
    }

    public function wordpressHtmlBlock(AstNode $codeBlock, string $style = 'pygments'): string
    {
        $highlighted = $this->highlightCodeBlock($codeBlock, $style);
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
            'html' => $this->tokenizeHtml($code),
            'javascript' => $this->tokenizeJavaScript($code),
            'json' => $this->tokenizeJson($code),
            'php' => $this->tokenizePhp($code),
            'python' => $this->tokenizePython($code),
            'sql' => $this->tokenizeSql($code),
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
    private function tokenizePython(string $code): array
    {
        return $this->scan($code, [
            ['comment', '/^#[^\\n]*/'],
            ['string', '/^(?:r|u|f|fr|rf)?"""[\\s\\S]*?"""/i'],
            ['string', "/^(?:r|u|f|fr|rf)?'''[\\s\\S]*?'''/i"],
            ['string', '/^(?:r|u|f|fr|rf)?"(?:\\\\.|[^"\\\\])*"/i'],
            ['string', "/^(?:r|u|f|fr|rf)?'(?:\\\\.|[^'\\\\])*'/i"],
            ['keyword', '/^\\b(?:and|as|assert|break|class|continue|def|del|elif|else|except|finally|for|from|global|if|import|in|is|lambda|nonlocal|not|or|pass|raise|return|try|while|with|yield)\\b/'],
            ['constant', '/^\\b(?:False|None|True)\\b/'],
            ['number', '/^\\b\\d+(?:\\.\\d+)?\\b/'],
            ['function', '/^\\b[A-Za-z_][A-Za-z0-9_]*(?=\\s*\\()/'],
            ['operator', '/^(?:==|!=|<=|>=|[{}()[\\];,.+*\\/%=!<>:-])/'],
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
        return in_array(strtolower(self::stripLanguagePrefix($class)), [
            'numberlines',
            'sourcecode',
        ], true);
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

    private static function sanitizeClass(string $class): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '', $class) ?? '';
    }

    private static function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
