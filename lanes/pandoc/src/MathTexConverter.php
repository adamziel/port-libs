<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class MathTexConverter
{
    /** @var array<string, string> */
    private const IDENTIFIER_COMMANDS = [
        'alpha' => 'α',
        'beta' => 'β',
        'gamma' => 'γ',
        'delta' => 'δ',
        'epsilon' => 'ϵ',
        'infty' => '∞',
        'theta' => 'θ',
        'lambda' => 'λ',
        'mu' => 'μ',
        'pi' => 'π',
        'sigma' => 'σ',
        'omega' => 'ω',
    ];

    /** @var array<string, string> */
    private const OPERATOR_COMMANDS = [
        'approx' => '≈',
        'cap' => '∩',
        'cdot' => '⋅',
        'colon' => ':',
        'cup' => '∪',
        'emptyset' => '∅',
        'equiv' => '≡',
        'exists' => '∃',
        'forall' => '∀',
        'ge' => '≥',
        'geq' => '≥',
        'iff' => '⇔',
        'in' => '∈',
        'int' => '∫',
        'land' => '∧',
        'le' => '≤',
        'leq' => '≤',
        'leftarrow' => '←',
        'leftrightarrow' => '↔',
        'lim' => 'lim',
        'lor' => '∨',
        'mapsto' => '↦',
        'neg' => '¬',
        'ne' => '≠',
        'neq' => '≠',
        'notin' => '∉',
        'partial' => '∂',
        'pm' => '±',
        'prod' => '∏',
        'rightarrow' => '→',
        'Rightarrow' => '⇒',
        'setminus' => '∖',
        'subset' => '⊂',
        'subseteq' => '⊆',
        'sum' => '∑',
        'supset' => '⊃',
        'supseteq' => '⊇',
        'times' => '×',
        'to' => '→',
        'vee' => '∨',
        'wedge' => '∧',
    ];

    /** @var array<string, array{lineThickness?: string, open?: string, close?: string}> */
    private const INFIX_FRACTION_COMMANDS = [
        'atop' => ['lineThickness' => '0'],
        'brace' => ['lineThickness' => '0', 'open' => '{', 'close' => '}'],
        'brack' => ['lineThickness' => '0', 'open' => '[', 'close' => ']'],
        'choose' => ['lineThickness' => '0', 'open' => '(', 'close' => ')'],
        'over' => [],
    ];

    /** @var array<string, string> */
    private const FUNCTION_COMMANDS = [
        'cos' => 'cos',
        'exp' => 'exp',
        'log' => 'log',
        'sin' => 'sin',
        'tan' => 'tan',
    ];

    /** @var array<string, string> */
    private const SPACING_COMMANDS = [
        ' ' => '0.3333em',
        '!' => '-0.1667em',
        ',' => '0.1667em',
        ':' => '0.2222em',
        ';' => '0.2778em',
        '>' => '0.2222em',
        'enspace' => '0.5em',
        'medspace' => '0.2222em',
        'negmedspace' => '-0.2222em',
        'negthickspace' => '-0.2778em',
        'negthinspace' => '-0.1667em',
        'quad' => '1em',
        'qquad' => '2em',
        'thickspace' => '0.2778em',
        'thinspace' => '0.1667em',
    ];

    /** @var array<string, string> */
    private const OVER_ACCENT_COMMANDS = [
        'bar' => '¯',
        'ddot' => '¨',
        'dot' => '˙',
        'hat' => '^',
        'overline' => '‾',
        'tilde' => '~',
        'vec' => '→',
        'widehat' => '^',
    ];

    /** @var array<string, string> */
    private const UNDER_ACCENT_COMMANDS = [
        'underline' => '_',
    ];

    /** @var array<string, string> */
    private const CANCEL_COMMANDS = [
        'bcancel' => 'downdiagonalstrike',
        'cancel' => 'updiagonalstrike',
        'xcancel' => 'updiagonalstrike downdiagonalstrike',
    ];

    /** @var array<string, string> */
    private const MATH_VARIANT_COMMANDS = [
        'boldsymbol' => 'bold',
        'mathbf' => 'bold',
        'mathbb' => 'double-struck',
        'mathcal' => 'script',
        'mathfrak' => 'fraktur',
        'mathit' => 'italic',
        'mathscr' => 'script',
        'mathsf' => 'sans-serif',
        'mathtt' => 'monospace',
        'mathrm' => 'normal',
    ];

    /** @var array<string, string> */
    private const DELIMITER_COMMANDS = [
        '{' => '{',
        '}' => '}',
        'langle' => '⟨',
        'lbrace' => '{',
        'rangle' => '⟩',
        'rbrace' => '}',
        'vert' => '|',
        'Vert' => '‖',
    ];

    /** @var array<string, array{size: string, separator?: bool}> */
    private const SIZED_DELIMITER_COMMANDS = [
        'big' => ['size' => '1.2em'],
        'bigl' => ['size' => '1.2em'],
        'bigr' => ['size' => '1.2em'],
        'bigm' => ['size' => '1.2em', 'separator' => true],
        'Big' => ['size' => '1.8em'],
        'Bigl' => ['size' => '1.8em'],
        'Bigr' => ['size' => '1.8em'],
        'Bigm' => ['size' => '1.8em', 'separator' => true],
        'bigg' => ['size' => '2.4em'],
        'biggl' => ['size' => '2.4em'],
        'biggr' => ['size' => '2.4em'],
        'biggm' => ['size' => '2.4em', 'separator' => true],
        'Bigg' => ['size' => '3em'],
        'Biggl' => ['size' => '3em'],
        'Biggr' => ['size' => '3em'],
        'Biggm' => ['size' => '3em', 'separator' => true],
    ];

    /** @var array<string, array{open?: string, close?: string, columnalign?: string}> */
    private const MATRIX_ENVIRONMENTS = [
        'aligned' => ['columnalign' => 'right left'],
        'bmatrix' => ['open' => '[', 'close' => ']'],
        'Bmatrix' => ['open' => '{', 'close' => '}'],
        'cases' => ['open' => '{', 'columnalign' => 'left left'],
        'matrix' => [],
        'pmatrix' => ['open' => '(', 'close' => ')'],
        'vmatrix' => ['open' => '|', 'close' => '|'],
        'Vmatrix' => ['open' => '‖', 'close' => '‖'],
    ];

    /** @var array<string, array{columnalign: string, columns: int}> */
    private const AMS_ROW_ENVIRONMENTS = [
        'align' => ['columnalign' => 'right left', 'columns' => 2],
        'align*' => ['columnalign' => 'right left', 'columns' => 2],
        'gather' => ['columnalign' => 'center', 'columns' => 1],
        'gather*' => ['columnalign' => 'center', 'columns' => 1],
        'gathered' => ['columnalign' => 'center', 'columns' => 1],
        'split' => ['columnalign' => 'right left', 'columns' => 2],
    ];

    /** @var array<string, true> */
    private const AMS_ALIGNEDAT_ENVIRONMENTS = [
        'alignat' => true,
        'alignat*' => true,
        'alignedat' => true,
        'alignedat*' => true,
    ];

    private int $activeLeftFenceDepth = 0;

    public function latexFor(AstNode $node): string
    {
        $text = (string) $node->attr('text', '');

        if ($node->attr('display') === true) {
            return '\\[' . $text . '\\]';
        }

        return '$' . $text . '$';
    }

    /**
     * @param array<string, array{arity?: int, template?: string, optionalDefault?: string}> $macros
     */
    public function mathMlFor(AstNode $node, array $macros = []): string
    {
        return $this->texToMathMl((string) $node->attr('text', ''), $node->attr('display') === true, $macros);
    }

    /**
     * @param array<string, array{arity?: int, template?: string, optionalDefault?: string}> $macros
     */
    public function texToMathMl(string $tex, bool $display = false, array $macros = []): string
    {
        $expandedTex = $this->expandRawTexMathMacros($tex, $this->normalizeMacroDefinitions($macros));
        $this->activeLeftFenceDepth = 0;
        $offset = 0;
        $children = $this->parseExpression($expandedTex, $offset, null);
        $this->skipWhitespace($expandedTex, $offset);

        if ($offset < strlen($expandedTex)) {
            throw new \InvalidArgumentException('Unsupported TeX token at offset ' . $offset);
        }

        $displayMode = $display ? 'block' : 'inline';

        return '<math xmlns="http://www.w3.org/1998/Math/MathML" display="' . $displayMode . '">'
            . '<semantics>'
            . $this->row($children)
            . '<annotation encoding="application/x-tex">' . $this->esc($tex) . '</annotation>'
            . '</semantics>'
            . '</math>';
    }

    /**
     * @return array<string, array{arity:int, template:string}>
     */
    public function macroDefinitionsFromDocument(AstNode $node): array
    {
        $macros = [];
        $this->collectMacroDefinitions($node, $macros);

        return $macros;
    }

    /**
     * @param array<string, array{arity:int, template:string, optionalDefault?: string}> $macros
     */
    private function collectMacroDefinitions(AstNode $node, array &$macros): void
    {
        if ($node->type === 'raw_tex') {
            $macro = $this->readRawTexMacroDefinition((string) $node->attr('tex', ''));
            if ($macro !== null) {
                $definition = [
                    'arity' => $macro['arity'],
                    'template' => $macro['template'],
                ];
                if (array_key_exists('optionalDefault', $macro)) {
                    $definition['optionalDefault'] = $macro['optionalDefault'];
                }

                $macros[$macro['name']] = $definition;
            }
        }

        foreach ($node->children as $child) {
            $this->collectMacroDefinitions($child, $macros);
        }
    }

    /**
     * @return array{name:string, arity:int, template:string, optionalDefault?: string}|null
     */
    private function readRawTexMacroDefinition(string $tex): ?array
    {
        $source = trim($tex);
        if (preg_match('/^\\\\(?:(?:re)?newcommand|providecommand)/', $source, $m) !== 1) {
            return null;
        }

        $offset = strlen($m[0]);
        $this->skipWhitespace($source, $offset);
        $name = $this->readTexBraceArgument($source, $offset);
        if ($name === null || preg_match('/^\\\\([A-Za-z]+)$/', $name['value'], $nameMatch) !== 1) {
            return null;
        }
        $offset = $name['next'];

        $arity = null;
        $optionalDefault = null;
        $this->skipWhitespace($source, $offset);
        $arityArgument = $this->readTexBracketArgument($source, $offset);
        if ($arityArgument !== null) {
            if (preg_match('/^[0-9]$/', trim($arityArgument['value'])) !== 1) {
                return null;
            }

            $arity = (int) trim($arityArgument['value']);
            $offset = $arityArgument['next'];
            $defaultArgument = $this->readTexBracketArgument($source, $offset);
            if ($defaultArgument !== null) {
                $optionalDefault = $defaultArgument['value'];
                $offset = $defaultArgument['next'];
            }
        }

        $this->skipWhitespace($source, $offset);
        $template = $this->readTexBraceArgument($source, $offset);
        if ($template === null) {
            return null;
        }
        $offset = $template['next'];
        $this->skipWhitespace($source, $offset);
        if ($offset !== strlen($source)) {
            return null;
        }

        $definition = [
            'name' => $nameMatch[1],
            'arity' => $arity ?? $this->inferMacroArity($template['value']),
            'template' => $template['value'],
        ];
        if ($optionalDefault !== null) {
            if ($definition['arity'] < 1) {
                return null;
            }

            $definition['optionalDefault'] = $optionalDefault;
        }

        return $definition;
    }

    private function inferMacroArity(string $template): int
    {
        if (preg_match_all('/#([1-9])/', $template, $m) !== false && $m[1] !== []) {
            return max(array_map('intval', $m[1]));
        }

        return 0;
    }

    /**
     * @param array<string, array{arity?: int, template?: string, optionalDefault?: string}> $macros
     * @return array<string, array{arity:int, template:string, optionalDefault?: string}>
     */
    private function normalizeMacroDefinitions(array $macros): array
    {
        $normalized = [];
        foreach ($macros as $name => $definition) {
            $macroName = ltrim($name, '\\');
            if (preg_match('/^[A-Za-z]+$/', $macroName) !== 1) {
                throw new \InvalidArgumentException('Unsupported TeX macro name ' . $name);
            }

            if (!is_array($definition) || !isset($definition['template']) || !is_string($definition['template'])) {
                throw new \InvalidArgumentException('Expected TeX macro template for \\' . $macroName);
            }

            $arity = $definition['arity'] ?? $this->inferMacroArity($definition['template']);
            if (!is_int($arity) || $arity < 0 || $arity > 9) {
                throw new \InvalidArgumentException('Unsupported TeX macro arity for \\' . $macroName);
            }

            $macro = [
                'arity' => $arity,
                'template' => $definition['template'],
            ];

            if (array_key_exists('optionalDefault', $definition)) {
                if (!is_string($definition['optionalDefault'])) {
                    throw new \InvalidArgumentException('Expected TeX optional macro default for \\' . $macroName);
                }

                if ($arity < 1) {
                    throw new \InvalidArgumentException('Unsupported TeX optional macro arity for \\' . $macroName);
                }

                $macro['optionalDefault'] = $definition['optionalDefault'];
            }

            $normalized[$macroName] = $macro;
        }

        return $normalized;
    }

    /**
     * @param array<string, array{arity:int, template:string, optionalDefault?: string}> $macros
     */
    private function expandRawTexMathMacros(string $math, array $macros): string
    {
        if ($macros === []) {
            return $math;
        }

        $expanded = $math;
        for ($iteration = 0; $iteration < 5; $iteration++) {
            $next = $this->expandRawTexMathMacrosOnce($expanded, $macros);
            if ($next === $expanded) {
                break;
            }
            $expanded = $next;
        }

        return $expanded;
    }

    /**
     * @param array<string, array{arity:int, template:string, optionalDefault?: string}> $macros
     */
    private function expandRawTexMathMacrosOnce(string $math, array $macros): string
    {
        $output = '';
        $offset = 0;
        $length = strlen($math);

        while ($offset < $length) {
            if (
                ($math[$offset] ?? '') === '\\'
                && preg_match('/\G\\\\([A-Za-z]+)/', $math, $m, 0, $offset) === 1
                && isset($macros[$m[1]])
            ) {
                $macro = $macros[$m[1]];
                $cursor = $offset + strlen($m[0]);
                $args = [];
                $requiredArity = $macro['arity'];
                if (array_key_exists('optionalDefault', $macro)) {
                    $optionalOffset = $cursor;
                    $this->skipWhitespace($math, $optionalOffset);
                    $optionalArgument = $this->readTexBracketArgument($math, $optionalOffset);
                    if ($optionalArgument !== null) {
                        $args[] = $optionalArgument['value'];
                        $cursor = $optionalArgument['next'];
                    } else {
                        $args[] = $macro['optionalDefault'];
                    }
                    $requiredArity--;
                }

                for ($argument = 0; $argument < $requiredArity; $argument++) {
                    $this->skipWhitespace($math, $cursor);
                    $parsed = $this->readTexBraceArgument($math, $cursor);
                    if ($parsed === null) {
                        break;
                    }
                    $args[] = $parsed['value'];
                    $cursor = $parsed['next'];
                }

                if (count($args) === $macro['arity']) {
                    $output .= $this->renderRawTexMacroTemplate($macro['template'], $args);
                    $offset = $cursor;
                    continue;
                }
            }

            $output .= $math[$offset];
            $offset++;
        }

        return $output;
    }

    /**
     * @return array{value:string, next:int}|null
     */
    private function readTexBraceArgument(string $text, int $offset): ?array
    {
        if (($text[$offset] ?? '') !== '{') {
            return null;
        }

        $depth = 0;
        $length = strlen($text);
        for ($cursor = $offset; $cursor < $length; $cursor++) {
            if ($text[$cursor] === '\\') {
                $cursor++;
                continue;
            }

            if ($text[$cursor] === '{') {
                $depth++;
                continue;
            }

            if ($text[$cursor] !== '}') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                return [
                    'value' => substr($text, $offset + 1, $cursor - $offset - 1),
                    'next' => $cursor + 1,
                ];
            }
        }

        return null;
    }

    /**
     * @return array{value:string, next:int}|null
     */
    private function readTexBracketArgument(string $text, int $offset): ?array
    {
        if (($text[$offset] ?? '') !== '[') {
            return null;
        }

        $depth = 0;
        $length = strlen($text);
        for ($cursor = $offset + 1; $cursor < $length; $cursor++) {
            if ($text[$cursor] === '\\') {
                $cursor++;
                continue;
            }

            if ($text[$cursor] === '{') {
                $depth++;
                continue;
            }

            if ($text[$cursor] === '}' && $depth > 0) {
                $depth--;
                continue;
            }

            if ($text[$cursor] === ']' && $depth === 0) {
                return [
                    'value' => substr($text, $offset + 1, $cursor - $offset - 1),
                    'next' => $cursor + 1,
                ];
            }
        }

        return null;
    }

    /**
     * @param list<string> $args
     */
    private function renderRawTexMacroTemplate(string $template, array $args): string
    {
        foreach ($args as $index => $argument) {
            $template = str_replace('#' . ($index + 1), $argument, $template);
        }

        return $template;
    }

    /**
     * @return list<string>
     */
    private function parseExpression(string $source, int &$offset, ?string $stopChar): array
    {
        $nodes = [];
        $length = strlen($source);

        while ($offset < $length) {
            $this->skipWhitespace($source, $offset);
            if ($offset >= $length) {
                break;
            }

            if ($stopChar !== null && $source[$offset] === $stopChar) {
                break;
            }

            $infixOffset = $offset;
            $infixCommand = $this->readInfixFractionCommand($source, $infixOffset);
            if ($infixCommand !== null) {
                if ($nodes === []) {
                    throw new \InvalidArgumentException('Expected TeX infix numerator before \\' . $infixCommand['command'] . ' at offset ' . $offset);
                }

                $offset = $infixOffset;
                $denominator = $this->parseExpression($source, $offset, $stopChar);
                if ($denominator === []) {
                    throw new \InvalidArgumentException('Expected TeX infix denominator after \\' . $infixCommand['command'] . ' at offset ' . $offset);
                }

                return [$this->renderInfixFractionCommand($nodes, $denominator, $infixCommand)];
            }

            $base = $this->parseAtom($source, $offset);
            $nodes[] = $this->applyScripts($source, $offset, $base);
        }

        return $nodes;
    }

    private function parseAtom(string $source, int &$offset): string
    {
        $char = $source[$offset] ?? '';
        if ($char === '') {
            throw new \InvalidArgumentException('Unexpected end of TeX input');
        }

        if ($char === '{') {
            $offset++;
            $children = $this->parseExpression($source, $offset, '}');
            $this->expectGroupEnd($source, $offset);

            return $this->row($children);
        }

        if ($char === '\\') {
            return $this->parseCommand($source, $offset);
        }

        if (ctype_digit($char)) {
            $start = $offset;
            $offset++;
            while ($offset < strlen($source) && (ctype_digit($source[$offset]) || $source[$offset] === '.')) {
                $offset++;
            }

            return '<mn>' . $this->esc(substr($source, $start, $offset - $start)) . '</mn>';
        }

        if (ctype_alpha($char)) {
            $offset++;

            return '<mi>' . $this->esc($char) . '</mi>';
        }

        $offset++;

        return '<mo>' . $this->esc($char) . '</mo>';
    }

    private function parseCommand(string $source, int &$offset): string
    {
        $offset++;
        $command = $this->readCommandName($source, $offset);

        if ($command === 'frac') {
            return $this->parseFractionCommand($source, $offset, null);
        }

        if ($command === 'dfrac') {
            return $this->parseFractionCommand($source, $offset, true);
        }

        if ($command === 'tfrac') {
            return $this->parseFractionCommand($source, $offset, false);
        }

        if ($command === 'genfrac') {
            return $this->parseGeneralizedFractionCommand($source, $offset);
        }

        if ($command === 'sqrt') {
            $degree = $this->parseOptionalRootDegree($source, $offset);
            $radicand = $this->parseRequiredGroup($source, $offset);

            if ($degree !== null) {
                return '<mroot>' . $radicand . $degree . '</mroot>';
            }

            return '<msqrt>' . $radicand . '</msqrt>';
        }

        if ($command === 'binom') {
            return $this->parseBinomialCommand($source, $offset, null);
        }

        if ($command === 'tbinom') {
            return $this->parseBinomialCommand($source, $offset, false);
        }

        if ($command === 'dbinom') {
            return $this->parseBinomialCommand($source, $offset, true);
        }

        if ($command === 'text') {
            return '<mtext>' . $this->esc($this->readRequiredGroupText($source, $offset)) . '</mtext>';
        }

        if ($command === 'operatorname') {
            $operatorName = $this->readRequiredGroupText($source, $offset);
            if ($operatorName === '') {
                throw new \InvalidArgumentException('Expected TeX operator name at offset ' . $offset);
            }

            return '<mi>' . $this->esc($operatorName) . '</mi>';
        }

        if ($command === 'substack') {
            return $this->parseSubstackCommand($source, $offset);
        }

        if ($command === 'overset') {
            $above = $this->parseRequiredNonEmptyGroup($source, $offset, 'overset above');
            $base = $this->parseRequiredNonEmptyGroup($source, $offset, 'overset base');

            return '<mover>' . $base . $above . '</mover>';
        }

        if ($command === 'underset') {
            $below = $this->parseRequiredNonEmptyGroup($source, $offset, 'underset below');
            $base = $this->parseRequiredNonEmptyGroup($source, $offset, 'underset base');

            return '<munder>' . $base . $below . '</munder>';
        }

        if ($command === 'overbrace') {
            return '<mover>'
                . $this->parseRequiredNonEmptyGroup($source, $offset, 'overbrace base')
                . '<mo>⏞</mo>'
                . '</mover>';
        }

        if ($command === 'underbrace') {
            return '<munder>'
                . $this->parseRequiredNonEmptyGroup($source, $offset, 'underbrace base')
                . '<mo>⏟</mo>'
                . '</munder>';
        }

        if (in_array($command, ['displaystyle', 'textstyle', 'scriptstyle', 'scriptscriptstyle'], true)) {
            return $this->parseStyleCommand($source, $offset, $command);
        }

        if ($command === 'color' || $command === 'textcolor') {
            return $this->parseColorCommand($source, $offset, $command);
        }

        if ($command === 'phantom' || $command === 'hphantom' || $command === 'vphantom') {
            return $this->parsePhantomCommand($source, $offset, $command);
        }

        if ($command === 'cancelto') {
            return $this->parseCancelToCommand($source, $offset);
        }

        if (isset(self::CANCEL_COMMANDS[$command])) {
            return $this->parseCancelCommand($source, $offset, $command);
        }

        if (isset(self::MATH_VARIANT_COMMANDS[$command])) {
            return $this->parseMathVariantCommand($source, $offset, $command);
        }

        if ($command === 'hspace' || $command === 'mspace') {
            return $this->parseExplicitSpaceCommand($source, $offset, $command);
        }

        if (isset(self::SPACING_COMMANDS[$command])) {
            return '<mspace width="' . self::SPACING_COMMANDS[$command] . '"></mspace>';
        }

        if ($command === 'begin') {
            return $this->parseEnvironment($source, $offset);
        }

        if ($command === 'end') {
            throw new \InvalidArgumentException('Unexpected TeX environment end at offset ' . $offset);
        }

        if ($command === 'middle') {
            return $this->parseMiddleFenceCommand($source, $offset);
        }

        if ($command === 'left' || $command === 'right') {
            return $this->parseFenceCommand($source, $offset, $command);
        }

        if (isset(self::SIZED_DELIMITER_COMMANDS[$command])) {
            return $this->parseSizedDelimiterCommand($source, $offset, $command);
        }

        if (isset(self::OVER_ACCENT_COMMANDS[$command])) {
            return '<mover accent="true">'
                . $this->parseAccentArgument($source, $offset, $command)
                . '<mo>' . $this->esc(self::OVER_ACCENT_COMMANDS[$command]) . '</mo>'
                . '</mover>';
        }

        if (isset(self::UNDER_ACCENT_COMMANDS[$command])) {
            return '<munder accentunder="true">'
                . $this->parseAccentArgument($source, $offset, $command)
                . '<mo>' . $this->esc(self::UNDER_ACCENT_COMMANDS[$command]) . '</mo>'
                . '</munder>';
        }

        if (isset(self::IDENTIFIER_COMMANDS[$command])) {
            return '<mi>' . self::IDENTIFIER_COMMANDS[$command] . '</mi>';
        }

        if (isset(self::FUNCTION_COMMANDS[$command])) {
            return '<mi>' . self::FUNCTION_COMMANDS[$command] . '</mi>';
        }

        if (isset(self::OPERATOR_COMMANDS[$command])) {
            return '<mo>' . self::OPERATOR_COMMANDS[$command] . '</mo>';
        }

        if (isset(self::DELIMITER_COMMANDS[$command])) {
            return '<mo>' . $this->esc(self::DELIMITER_COMMANDS[$command]) . '</mo>';
        }

        return '<mi>' . $this->esc('\\' . $command) . '</mi>';
    }

    private function parseEnvironment(string $source, int &$offset): string
    {
        $environment = $this->readRequiredGroupText($source, $offset);
        if ($environment === 'array') {
            return $this->parseArrayEnvironment($source, $offset);
        }

        if (isset(self::AMS_ROW_ENVIRONMENTS[$environment])) {
            return $this->parseAmsRowEnvironment($source, $offset, $environment);
        }

        if (isset(self::AMS_ALIGNEDAT_ENVIRONMENTS[$environment])) {
            return $this->parseAmsAlignedAtEnvironment($source, $offset, $environment);
        }

        if (!isset(self::MATRIX_ENVIRONMENTS[$environment])) {
            throw new \InvalidArgumentException('Unsupported TeX environment ' . $environment . ' at offset ' . $offset);
        }

        $content = $this->readEnvironmentContent($source, $offset, $environment);
        $rows = $this->splitAlignmentRows($content, $environment);
        $spec = self::MATRIX_ENVIRONMENTS[$environment];
        $attributes = '';
        if (isset($spec['columnalign'])) {
            $attributes = ' columnalign="' . $this->esc($spec['columnalign']) . '"';
        }

        $table = $this->environmentTable($rows, $attributes);

        if (isset($spec['open']) || isset($spec['close'])) {
            $wrapped = '<mrow>';
            if (isset($spec['open'])) {
                $wrapped .= '<mo fence="true" stretchy="true">' . $this->esc($spec['open']) . '</mo>';
            }
            $wrapped .= $table;
            if (isset($spec['close'])) {
                $wrapped .= '<mo fence="true" stretchy="true">' . $this->esc($spec['close']) . '</mo>';
            }

            return $wrapped . '</mrow>';
        }

        return $table;
    }

    private function parseBinomialCommand(string $source, int &$offset, ?bool $displaystyle): string
    {
        $numerator = $this->parseRequiredNonEmptyGroup($source, $offset, 'binomial numerator');
        $denominator = $this->parseRequiredNonEmptyGroup($source, $offset, 'binomial denominator');
        $binomial = '<mrow>'
            . '<mo fence="true" stretchy="true">(</mo>'
            . '<mfrac linethickness="0">' . $numerator . $denominator . '</mfrac>'
            . '<mo fence="true" stretchy="true">)</mo>'
            . '</mrow>';

        if ($displaystyle === null) {
            return $binomial;
        }

        return '<mstyle displaystyle="' . ($displaystyle ? 'true' : 'false') . '">' . $binomial . '</mstyle>';
    }

    private function parseFractionCommand(string $source, int &$offset, ?bool $displaystyle): string
    {
        $fraction = '<mfrac>'
            . $this->parseRequiredGroup($source, $offset)
            . $this->parseRequiredGroup($source, $offset)
            . '</mfrac>';

        if ($displaystyle === null) {
            return $fraction;
        }

        return '<mstyle displaystyle="' . ($displaystyle ? 'true' : 'false') . '">' . $fraction . '</mstyle>';
    }

    private function parseGeneralizedFractionCommand(string $source, int &$offset): string
    {
        $left = $this->normalizeGeneralizedFractionDelimiter($this->readRequiredGroupText($source, $offset), 'left');
        $right = $this->normalizeGeneralizedFractionDelimiter($this->readRequiredGroupText($source, $offset), 'right');
        $lineThickness = $this->normalizeGeneralizedFractionLineThickness($this->readRequiredGroupText($source, $offset));
        $style = $this->normalizeGeneralizedFractionStyle($this->readRequiredGroupText($source, $offset));
        $fraction = '<mfrac'
            . ($lineThickness !== null ? ' linethickness="' . $this->esc($lineThickness) . '"' : '')
            . '>'
            . $this->parseRequiredNonEmptyGroup($source, $offset, 'genfrac numerator')
            . $this->parseRequiredNonEmptyGroup($source, $offset, 'genfrac denominator')
            . '</mfrac>';

        if ($left !== '' || $right !== '') {
            $fraction = '<mrow>'
                . ($left !== '' ? '<mo fence="true" stretchy="true">' . $this->esc($left) . '</mo>' : '')
                . $fraction
                . ($right !== '' ? '<mo fence="true" stretchy="true">' . $this->esc($right) . '</mo>' : '')
                . '</mrow>';
        }

        if ($style === null) {
            return $fraction;
        }

        return '<mstyle' . $style . '>' . $fraction . '</mstyle>';
    }

    /**
     * @param list<string> $numerator
     * @param list<string> $denominator
     * @param array{command:string, lineThickness?:string, open?:string, close?:string} $spec
     */
    private function renderInfixFractionCommand(array $numerator, array $denominator, array $spec): string
    {
        $fraction = '<mfrac'
            . (isset($spec['lineThickness']) ? ' linethickness="' . $this->esc($spec['lineThickness']) . '"' : '')
            . '>'
            . $this->row($numerator)
            . $this->row($denominator)
            . '</mfrac>';

        $open = $spec['open'] ?? '';
        $close = $spec['close'] ?? '';
        if ($open === '' && $close === '') {
            return $fraction;
        }

        return '<mrow>'
            . ($open !== '' ? '<mo fence="true" stretchy="true">' . $this->esc($open) . '</mo>' : '')
            . $fraction
            . ($close !== '' ? '<mo fence="true" stretchy="true">' . $this->esc($close) . '</mo>' : '')
            . '</mrow>';
    }

    private function normalizeGeneralizedFractionDelimiter(string $delimiter, string $side): string
    {
        $delimiter = trim($delimiter);
        if ($delimiter === '' || $delimiter === '.') {
            return '';
        }

        if (strlen($delimiter) === 1 && str_contains('()[]{}|/<>', $delimiter)) {
            return $delimiter;
        }

        if ($delimiter[0] === '\\') {
            $offset = 1;
            $command = $this->readCommandName($delimiter, $offset);
            if ($offset === strlen($delimiter) && isset(self::DELIMITER_COMMANDS[$command])) {
                return self::DELIMITER_COMMANDS[$command];
            }
        }

        throw new \InvalidArgumentException('Unsupported TeX genfrac ' . $side . ' delimiter ' . $delimiter);
    }

    private function normalizeGeneralizedFractionLineThickness(string $lineThickness): ?string
    {
        $lineThickness = trim($lineThickness);
        if ($lineThickness === '') {
            return null;
        }

        if (preg_match('/^(?:0+(?:\.0+)?)(?:pt|em|ex|px)?$/', $lineThickness) === 1) {
            return '0';
        }

        if (preg_match('/^(?:\d+(?:\.\d+)?|\.\d+)(?:pt|em|ex|px)$/', $lineThickness) !== 1) {
            throw new \InvalidArgumentException('Unsupported TeX genfrac line thickness ' . $lineThickness);
        }

        return $lineThickness;
    }

    private function normalizeGeneralizedFractionStyle(string $style): ?string
    {
        $style = trim($style);

        return match ($style) {
            '' => null,
            '0' => ' displaystyle="true"',
            '1' => ' displaystyle="false"',
            '2' => ' scriptlevel="1"',
            '3' => ' scriptlevel="2"',
            default => throw new \InvalidArgumentException('Unsupported TeX genfrac style ' . $style),
        };
    }

    private function parseArrayEnvironment(string $source, int &$offset): string
    {
        $columnAlign = $this->arrayColumnAlign($this->readRequiredGroupText($source, $offset));
        $rows = $this->splitAlignmentRows($this->readEnvironmentContent($source, $offset, 'array'), 'array');

        return $this->environmentTable($rows, ' columnalign="' . $this->esc($columnAlign) . '"');
    }

    private function parseAmsRowEnvironment(string $source, int &$offset, string $environment): string
    {
        $content = $this->readEnvironmentContent($source, $offset, $environment);
        if ($this->endsWithTopLevelRowSeparator($content)) {
            throw new \InvalidArgumentException('Expected TeX ' . $environment . ' row content at final row');
        }

        $rows = $this->splitAlignmentRows($content, $environment);
        $spec = self::AMS_ROW_ENVIRONMENTS[$environment];
        $this->validateAmsRowEnvironmentRows($rows, $environment, $spec['columns']);

        return $this->environmentTable($rows, ' columnalign="' . $this->esc($spec['columnalign']) . '"');
    }

    private function parseAmsAlignedAtEnvironment(string $source, int &$offset, string $environment): string
    {
        $pairs = $this->normalizeAmsAlignedAtPairCount($this->readRequiredGroupText($source, $offset), $environment);
        $content = $this->readEnvironmentContent($source, $offset, $environment);
        if ($this->endsWithTopLevelRowSeparator($content)) {
            throw new \InvalidArgumentException('Expected TeX ' . $environment . ' row content at final row');
        }

        $rows = $this->splitAlignmentRows($content, $environment);
        $this->validateAmsRowEnvironmentRows($rows, $environment, $pairs * 2);

        return $this->environmentTable($rows, ' columnalign="' . $this->esc(implode(' ', array_fill(0, $pairs, 'right left'))) . '"');
    }

    private function normalizeAmsAlignedAtPairCount(string $pairCount, string $environment): int
    {
        $pairCount = trim($pairCount);
        if (preg_match('/^[1-9][0-9]*$/', $pairCount) !== 1) {
            throw new \InvalidArgumentException('Expected TeX ' . $environment . ' positive column pair count');
        }

        $pairs = (int) $pairCount;
        if ($pairs > 4) {
            throw new \InvalidArgumentException('Unsupported TeX ' . $environment . ' column pair count ' . $pairCount);
        }

        return $pairs;
    }

    private function parseColorCommand(string $source, int &$offset, string $command): string
    {
        $color = $this->normalizeMathColor($this->readRequiredGroupText($source, $offset));
        $content = $this->parseRequiredNonEmptyGroup($source, $offset, $command . ' content');

        return '<mstyle mathcolor="' . $this->esc($color) . '">' . $content . '</mstyle>';
    }

    private function normalizeMathColor(string $color): string
    {
        $color = trim($color);
        if ($color === '') {
            throw new \InvalidArgumentException('Expected TeX math color');
        }

        if (preg_match('/^#[0-9A-Fa-f]{3}(?:[0-9A-Fa-f]{3})?$/', $color) === 1) {
            return $color;
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,31}$/', $color) === 1) {
            return $color;
        }

        throw new \InvalidArgumentException('Unsupported TeX math color ' . $color);
    }

    private function parsePhantomCommand(string $source, int &$offset, string $command): string
    {
        $content = '<mphantom>'
            . $this->parseRequiredNonEmptyGroup($source, $offset, $command . ' content')
            . '</mphantom>';

        if ($command === 'hphantom') {
            return '<mpadded height="0" depth="0">' . $content . '</mpadded>';
        }

        if ($command === 'vphantom') {
            return '<mpadded width="0">' . $content . '</mpadded>';
        }

        return $content;
    }

    private function parseCancelCommand(string $source, int &$offset, string $command): string
    {
        return '<menclose notation="' . self::CANCEL_COMMANDS[$command] . '">'
            . $this->parseRequiredNonEmptyGroup($source, $offset, $command . ' content')
            . '</menclose>';
    }

    private function parseCancelToCommand(string $source, int &$offset): string
    {
        $target = $this->parseRequiredNonEmptyGroup($source, $offset, 'cancelto target');
        $content = $this->parseRequiredNonEmptyGroup($source, $offset, 'cancelto content');

        return '<mover>'
            . '<menclose notation="updiagonalstrike">' . $content . '</menclose>'
            . $target
            . '</mover>';
    }

    private function parseMathVariantCommand(string $source, int &$offset, string $command): string
    {
        return '<mstyle mathvariant="' . self::MATH_VARIANT_COMMANDS[$command] . '">'
            . $this->parseMathVariantArgument($source, $offset, $command)
            . '</mstyle>';
    }

    private function parseExplicitSpaceCommand(string $source, int &$offset, string $command): string
    {
        $attributes = '';
        if ($command === 'hspace' && ($source[$offset] ?? '') === '*') {
            $attributes = ' linebreak="nobreak"';
            $offset++;
        }

        $width = $this->normalizeMathSpaceDimension($this->readRequiredGroupText($source, $offset), $command);

        return '<mspace width="' . $this->esc($width) . '"' . $attributes . '></mspace>';
    }

    private function normalizeMathSpaceDimension(string $dimension, string $command): string
    {
        $dimension = trim($dimension);
        if ($dimension === '') {
            throw new \InvalidArgumentException('Expected TeX \\' . $command . ' dimension');
        }

        if (preg_match('/^[+\\-]?(?:\\d+(?:\\.\\d+)?|\\.\\d+)(?:em|ex|px|pt|pc|in|cm|mm|mu)$/', $dimension) !== 1) {
            throw new \InvalidArgumentException('Unsupported TeX \\' . $command . ' dimension ' . $dimension);
        }

        return str_starts_with($dimension, '+') ? substr($dimension, 1) : $dimension;
    }

    private function parseSubstackCommand(string $source, int &$offset): string
    {
        $content = $this->readRequiredGroupText($source, $offset);
        if ($this->endsWithTopLevelRowSeparator($content)) {
            throw new \InvalidArgumentException('Expected TeX substack row content at final row');
        }

        $rows = $this->splitAlignmentRows($content, 'substack');
        foreach ($rows as $rowIndex => $row) {
            if (count($row) !== 1) {
                throw new \InvalidArgumentException('Expected one-column TeX substack row at row ' . ($rowIndex + 1));
            }

            if (trim($row[0]) === '') {
                throw new \InvalidArgumentException('Expected TeX substack row content at row ' . ($rowIndex + 1));
            }
        }

        return $this->environmentTable($rows, ' columnalign="center" rowspacing="0.1em"');
    }

    /**
     * @param list<list<string>> $rows
     */
    private function validateAmsRowEnvironmentRows(array $rows, string $environment, int $columns): void
    {
        foreach ($rows as $rowIndex => $row) {
            if (count($row) !== $columns) {
                throw new \InvalidArgumentException('Expected ' . $columns . '-column TeX ' . $environment . ' row at row ' . ($rowIndex + 1));
            }

            $hasContent = false;
            foreach ($row as $cell) {
                if (trim($cell) !== '') {
                    $hasContent = true;
                    break;
                }
            }

            if (!$hasContent) {
                throw new \InvalidArgumentException('Expected TeX ' . $environment . ' row content at row ' . ($rowIndex + 1));
            }
        }
    }

    private function endsWithTopLevelRowSeparator(string $content): bool
    {
        $depth = 0;
        $separatorIsLastSignificantToken = false;
        $length = strlen($content);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $content[$offset];
            if ($char === '\\') {
                if ($depth === 0 && ($content[$offset + 1] ?? '') === '\\') {
                    $separatorIsLastSignificantToken = true;
                    $offset++;
                    continue;
                }

                $separatorIsLastSignificantToken = false;
                if (($content[$offset + 1] ?? '') !== '') {
                    $offset++;
                }
                continue;
            }

            if ($char === '{') {
                $depth++;
                $separatorIsLastSignificantToken = false;
                continue;
            }

            if ($char === '}') {
                $depth = max(0, $depth - 1);
                $separatorIsLastSignificantToken = false;
                continue;
            }

            if (!ctype_space($char)) {
                $separatorIsLastSignificantToken = false;
            }
        }

        return $separatorIsLastSignificantToken;
    }

    private function parseMathVariantArgument(string $source, int &$offset, string $command): string
    {
        $this->skipWhitespace($source, $offset);
        $char = $source[$offset] ?? '';
        if ($char === '' || $char === '_' || $char === '^') {
            throw new \InvalidArgumentException('Expected TeX math variant argument for \\' . $command . ' at offset ' . $offset);
        }

        if ($char === '{') {
            return $this->parseRequiredNonEmptyGroup($source, $offset, 'math variant');
        }

        return $this->parseAtom($source, $offset);
    }

    private function arrayColumnAlign(string $columnSpec): string
    {
        $alignments = [];
        $length = strlen($columnSpec);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $columnSpec[$offset];
            if ($char === '|' || ctype_space($char)) {
                continue;
            }

            if ($char === 'l') {
                $alignments[] = 'left';
                continue;
            }

            if ($char === 'c') {
                $alignments[] = 'center';
                continue;
            }

            if ($char === 'r') {
                $alignments[] = 'right';
                continue;
            }

            throw new \InvalidArgumentException('Unsupported TeX array column specifier ' . $char . ' at offset ' . $offset);
        }

        if ($alignments === []) {
            throw new \InvalidArgumentException('Expected TeX array column specifier');
        }

        return implode(' ', $alignments);
    }

    /**
     * @param list<list<string>> $rows
     */
    private function environmentTable(array $rows, string $attributes): string
    {
        $table = '<mtable' . $attributes . '>';
        foreach ($rows as $row) {
            $table .= '<mtr>';
            foreach ($row as $cell) {
                $table .= '<mtd>' . $this->parseEnvironmentCell($cell) . '</mtd>';
            }
            $table .= '</mtr>';
        }

        return $table . '</mtable>';
    }

    private function readEnvironmentContent(string $source, int &$offset, string $environment): string
    {
        $start = $offset;
        $length = strlen($source);
        $depth = 1;

        while ($offset < $length) {
            if ($source[$offset] !== '\\') {
                $offset++;
                continue;
            }

            $commandOffset = $offset + 1;
            $command = $this->readCommandName($source, $commandOffset);
            if ($command !== 'begin' && $command !== 'end') {
                $offset++;
                continue;
            }

            $groupOffset = $commandOffset;
            try {
                $name = $this->readRequiredGroupText($source, $groupOffset);
            } catch (\InvalidArgumentException) {
                $offset++;
                continue;
            }

            if ($name !== $environment) {
                $offset = $groupOffset;
                continue;
            }

            if ($command === 'begin') {
                $depth++;
                $offset = $groupOffset;
                continue;
            }

            $depth--;
            if ($depth === 0) {
                $content = substr($source, $start, $offset - $start);
                $offset = $groupOffset;

                return $content;
            }

            $offset = $groupOffset;
        }

        throw new \InvalidArgumentException('Unclosed TeX environment ' . $environment . ' at offset ' . $start);
    }

    /**
     * @return list<list<string>>
     */
    private function splitAlignmentRows(string $content, string $environment): array
    {
        $rows = [];
        $row = [];
        $cell = '';
        $depth = 0;
        $offset = 0;
        $length = strlen($content);

        while ($offset < $length) {
            $char = $content[$offset];
            if ($char === '\\') {
                $next = $content[$offset + 1] ?? '';
                if ($depth === 0 && $next === '\\') {
                    $row[] = trim($cell);
                    $cell = '';
                    $rows[] = $row;
                    $row = [];
                    $offset += 2;
                    continue;
                }

                $cell .= $char;
                $offset++;
                if (($content[$offset] ?? '') !== '' && !ctype_alpha($content[$offset])) {
                    $cell .= $content[$offset];
                    $offset++;
                }
                continue;
            }

            if ($char === '{') {
                $depth++;
                $cell .= $char;
                $offset++;
                continue;
            }

            if ($char === '}') {
                if ($depth === 0) {
                    throw new \InvalidArgumentException('Unexpected TeX group end in ' . $environment . ' environment at offset ' . $offset);
                }
                $depth--;
                $cell .= $char;
                $offset++;
                continue;
            }

            if ($depth === 0 && $char === '&') {
                $row[] = trim($cell);
                $cell = '';
                $offset++;
                continue;
            }

            $cell .= $char;
            $offset++;
        }

        if ($depth !== 0) {
            throw new \InvalidArgumentException('Unclosed TeX group in ' . $environment . ' environment');
        }

        if (trim($cell) !== '' || $row !== []) {
            $row[] = trim($cell);
            $rows[] = $row;
        }

        if ($rows === []) {
            throw new \InvalidArgumentException('Empty TeX environment ' . $environment);
        }

        return $rows;
    }

    private function parseEnvironmentCell(string $cell): string
    {
        if ($cell === '') {
            return '';
        }

        $offset = 0;
        $children = $this->parseExpression($cell, $offset, null);
        $this->skipWhitespace($cell, $offset);
        if ($offset < strlen($cell)) {
            throw new \InvalidArgumentException('Unsupported TeX token in environment cell at offset ' . $offset);
        }

        return implode('', $children);
    }

    private function parseFenceCommand(string $source, int &$offset, string $command): string
    {
        $delimiter = $this->readFenceDelimiter($source, $offset);
        if ($command === 'left') {
            $this->activeLeftFenceDepth++;
        } elseif ($this->activeLeftFenceDepth > 0) {
            $this->activeLeftFenceDepth--;
        }

        if ($delimiter === '') {
            return '';
        }

        return '<mo fence="true" stretchy="true">' . $this->esc($delimiter) . '</mo>';
    }

    private function parseMiddleFenceCommand(string $source, int &$offset): string
    {
        if ($this->activeLeftFenceDepth <= 0) {
            throw new \InvalidArgumentException('Expected TeX \\middle inside \\left...\\right at offset ' . $offset);
        }

        $delimiter = $this->readFenceDelimiter($source, $offset);
        if ($delimiter === '') {
            return '';
        }

        return '<mo fence="true" stretchy="true" separator="true">' . $this->esc($delimiter) . '</mo>';
    }

    private function parseSizedDelimiterCommand(string $source, int &$offset, string $command): string
    {
        $delimiter = $this->readFenceDelimiter($source, $offset);
        if ($delimiter === '') {
            return '';
        }

        $spec = self::SIZED_DELIMITER_COMMANDS[$command];
        $attributes = ' fence="true" stretchy="true"';
        if (($spec['separator'] ?? false) === true) {
            $attributes .= ' separator="true"';
        }
        $attributes .= ' minsize="' . $this->esc($spec['size']) . '" maxsize="' . $this->esc($spec['size']) . '"';

        return '<mo' . $attributes . '>' . $this->esc($delimiter) . '</mo>';
    }

    private function parseStyleCommand(string $source, int &$offset, string $command): string
    {
        $base = $this->parseStyleArgument($source, $offset, $command);
        $attributes = match ($command) {
            'displaystyle' => ' displaystyle="true"',
            'textstyle' => ' displaystyle="false"',
            'scriptstyle' => ' scriptlevel="1"',
            'scriptscriptstyle' => ' scriptlevel="2"',
        };

        return '<mstyle' . $attributes . '>' . $base . '</mstyle>';
    }

    private function parseStyleArgument(string $source, int &$offset, string $command): string
    {
        $this->skipWhitespace($source, $offset);
        $char = $source[$offset] ?? '';
        if ($char === '' || $char === '_' || $char === '^') {
            throw new \InvalidArgumentException('Expected TeX style argument for \\' . $command . ' at offset ' . $offset);
        }

        return $this->applyScripts($source, $offset, $this->parseAtom($source, $offset));
    }

    private function applyScripts(string $source, int &$offset, string $base): string
    {
        $subscript = null;
        $superscript = null;

        while (true) {
            $this->skipWhitespace($source, $offset);
            $marker = $source[$offset] ?? '';
            if ($marker !== '_' && $marker !== '^') {
                break;
            }

            $offset++;
            $argument = $this->parseScriptArgument($source, $offset);
            if ($marker === '_') {
                $subscript = $argument;
            } else {
                $superscript = $argument;
            }
        }

        if ($subscript !== null && $superscript !== null) {
            return '<msubsup>' . $base . $subscript . $superscript . '</msubsup>';
        }

        if ($subscript !== null) {
            return '<msub>' . $base . $subscript . '</msub>';
        }

        if ($superscript !== null) {
            return '<msup>' . $base . $superscript . '</msup>';
        }

        return $base;
    }

    private function parseScriptArgument(string $source, int &$offset): string
    {
        $this->skipWhitespace($source, $offset);
        if (($source[$offset] ?? '') === '{') {
            $offset++;
            $children = $this->parseExpression($source, $offset, '}');
            $this->expectGroupEnd($source, $offset);

            return $this->row($children);
        }

        return $this->parseAtom($source, $offset);
    }

    private function parseAccentArgument(string $source, int &$offset, string $command): string
    {
        $this->skipWhitespace($source, $offset);
        $char = $source[$offset] ?? '';
        if ($char === '' || $char === '_' || $char === '^') {
            throw new \InvalidArgumentException('Expected TeX accent argument for \\' . $command . ' at offset ' . $offset);
        }

        return $this->parseScriptArgument($source, $offset);
    }

    private function parseRequiredGroup(string $source, int &$offset): string
    {
        $this->skipWhitespace($source, $offset);
        if (($source[$offset] ?? '') !== '{') {
            throw new \InvalidArgumentException('Expected TeX group at offset ' . $offset);
        }

        $offset++;
        $children = $this->parseExpression($source, $offset, '}');
        $this->expectGroupEnd($source, $offset);

        return $this->row($children);
    }

    private function parseRequiredNonEmptyGroup(string $source, int &$offset, string $label): string
    {
        $this->skipWhitespace($source, $offset);
        $start = $offset;
        if (($source[$offset] ?? '') !== '{') {
            throw new \InvalidArgumentException('Expected TeX ' . $label . ' group at offset ' . $offset);
        }

        $offset++;
        $children = $this->parseExpression($source, $offset, '}');
        if ($children === []) {
            throw new \InvalidArgumentException('Expected TeX ' . $label . ' content at offset ' . $start);
        }

        $this->expectGroupEnd($source, $offset);

        return $this->row($children);
    }

    private function parseOptionalRootDegree(string $source, int &$offset): ?string
    {
        $this->skipWhitespace($source, $offset);
        if (($source[$offset] ?? '') !== '[') {
            return null;
        }

        $offset++;
        $children = $this->parseExpression($source, $offset, ']');
        if ($children === []) {
            throw new \InvalidArgumentException('Expected TeX root degree at offset ' . $offset);
        }

        if (($source[$offset] ?? '') !== ']') {
            throw new \InvalidArgumentException('Unclosed TeX root degree at offset ' . $offset);
        }

        $offset++;

        return $this->row($children);
    }

    private function readRequiredGroupText(string $source, int &$offset): string
    {
        $this->skipWhitespace($source, $offset);
        if (($source[$offset] ?? '') !== '{') {
            throw new \InvalidArgumentException('Expected TeX text group at offset ' . $offset);
        }

        $offset++;
        $start = $offset;
        $depth = 1;
        $length = strlen($source);

        while ($offset < $length) {
            $char = $source[$offset];
            if ($char === '\\') {
                $offset += 2;
                continue;
            }
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    $text = substr($source, $start, $offset - $start);
                    $offset++;

                    return $text;
                }
            }
            $offset++;
        }

        throw new \InvalidArgumentException('Unclosed TeX text group at offset ' . $start);
    }

    /**
     * @return array{command:string, lineThickness?:string, open?:string, close?:string}|null
     */
    private function readInfixFractionCommand(string $source, int &$offset): ?array
    {
        if (($source[$offset] ?? '') !== '\\') {
            return null;
        }

        $commandOffset = $offset + 1;
        $command = $this->readCommandName($source, $commandOffset);
        if (isset(self::INFIX_FRACTION_COMMANDS[$command])) {
            $offset = $commandOffset;

            return ['command' => $command] + self::INFIX_FRACTION_COMMANDS[$command];
        }

        if (!in_array($command, ['overwithdelims', 'atopwithdelims', 'abovewithdelims'], true)) {
            return null;
        }

        $withDelimsOffset = $commandOffset;
        $spec = [
            'command' => $command,
            'open' => $this->readFenceDelimiter($source, $withDelimsOffset),
            'close' => $this->readFenceDelimiter($source, $withDelimsOffset),
        ];

        if ($command === 'atopwithdelims') {
            $spec['lineThickness'] = '0';
        } elseif ($command === 'abovewithdelims') {
            $spec['lineThickness'] = $this->readAboveWithDelimsLineThickness($source, $withDelimsOffset);
        }

        $offset = $withDelimsOffset;

        return $spec;
    }

    private function readAboveWithDelimsLineThickness(string $source, int &$offset): string
    {
        $this->skipWhitespace($source, $offset);
        $start = $offset;
        $length = strlen($source);
        while ($offset < $length) {
            $char = $source[$offset];
            if (ctype_space($char) || $char === '{' || $char === '}' || $char === '\\' || $char === '_' || $char === '^') {
                break;
            }
            $offset++;
        }

        if ($offset === $start) {
            throw new \InvalidArgumentException('Expected TeX abovewithdelims line thickness at offset ' . $offset);
        }

        $lineThickness = $this->normalizeGeneralizedFractionLineThickness(substr($source, $start, $offset - $start));
        if ($lineThickness === null) {
            throw new \InvalidArgumentException('Expected TeX abovewithdelims line thickness at offset ' . $start);
        }

        return $lineThickness;
    }

    private function readCommandName(string $source, int &$offset): string
    {
        $start = $offset;
        while ($offset < strlen($source) && ctype_alpha($source[$offset])) {
            $offset++;
        }

        if ($offset > $start) {
            return substr($source, $start, $offset - $start);
        }

        if (($source[$offset] ?? '') !== '') {
            return $source[$offset++];
        }

        throw new \InvalidArgumentException('Expected TeX command name at offset ' . $offset);
    }

    private function readFenceDelimiter(string $source, int &$offset): string
    {
        $this->skipWhitespace($source, $offset);
        $char = $source[$offset] ?? '';
        if ($char === '') {
            throw new \InvalidArgumentException('Expected TeX fence delimiter at offset ' . $offset);
        }

        if ($char === '\\') {
            $offset++;
            $command = $this->readCommandName($source, $offset);
            if (isset(self::DELIMITER_COMMANDS[$command])) {
                return self::DELIMITER_COMMANDS[$command];
            }

            throw new \InvalidArgumentException('Unsupported TeX fence delimiter command \\' . $command . ' at offset ' . $offset);
        }

        $offset++;
        if ($char === '.') {
            return '';
        }

        if (str_contains('()[]{}|/<>', $char)) {
            return $char;
        }

        throw new \InvalidArgumentException('Unsupported TeX fence delimiter at offset ' . ($offset - 1));
    }

    private function expectGroupEnd(string $source, int &$offset): void
    {
        if (($source[$offset] ?? '') !== '}') {
            throw new \InvalidArgumentException('Unclosed TeX group at offset ' . $offset);
        }

        $offset++;
    }

    private function skipWhitespace(string $source, int &$offset): void
    {
        while (($source[$offset] ?? '') !== '' && ctype_space($source[$offset])) {
            $offset++;
        }
    }

    /**
     * @param list<string> $children
     */
    private function row(array $children): string
    {
        if (count($children) === 1) {
            return $children[0];
        }

        return '<mrow>' . implode('', $children) . '</mrow>';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
