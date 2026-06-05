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

    /** @var array<string, string> */
    private const FUNCTION_COMMANDS = [
        'cos' => 'cos',
        'exp' => 'exp',
        'log' => 'log',
        'sin' => 'sin',
        'tan' => 'tan',
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

    public function latexFor(AstNode $node): string
    {
        $text = (string) $node->attr('text', '');

        if ($node->attr('display') === true) {
            return '\\[' . $text . '\\]';
        }

        return '$' . $text . '$';
    }

    /**
     * @param array<string, array{arity?: int, template?: string}> $macros
     */
    public function mathMlFor(AstNode $node, array $macros = []): string
    {
        return $this->texToMathMl((string) $node->attr('text', ''), $node->attr('display') === true, $macros);
    }

    /**
     * @param array<string, array{arity?: int, template?: string}> $macros
     */
    public function texToMathMl(string $tex, bool $display = false, array $macros = []): string
    {
        $expandedTex = $this->expandRawTexMathMacros($tex, $this->normalizeMacroDefinitions($macros));
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
     * @param array<string, array{arity:int, template:string}> $macros
     */
    private function collectMacroDefinitions(AstNode $node, array &$macros): void
    {
        if ($node->type === 'raw_tex') {
            $macro = $this->readRawTexMacroDefinition((string) $node->attr('tex', ''));
            if ($macro !== null) {
                $macros[$macro['name']] = [
                    'arity' => $macro['arity'],
                    'template' => $macro['template'],
                ];
            }
        }

        foreach ($node->children as $child) {
            $this->collectMacroDefinitions($child, $macros);
        }
    }

    /**
     * @return array{name:string, arity:int, template:string}|null
     */
    private function readRawTexMacroDefinition(string $tex): ?array
    {
        if (
            preg_match(
                '/^\\\\(?:re)?newcommand\{\\\\([A-Za-z]+)\}(?:\[(\d+)])?(?:\[[^\]\r\n]*])?\{((?:\\\\.|[^{}])*)\}$/',
                trim($tex),
                $m
            ) !== 1
            && preg_match(
                '/^\\\\providecommand\{\\\\([A-Za-z]+)\}(?:\[(\d+)])?(?:\[[^\]\r\n]*])?\{((?:\\\\.|[^{}])*)\}$/',
                trim($tex),
                $m
            ) !== 1
        ) {
            return null;
        }

        return [
            'name' => $m[1],
            'arity' => isset($m[2]) && $m[2] !== '' ? (int) $m[2] : $this->inferMacroArity($m[3]),
            'template' => $m[3],
        ];
    }

    private function inferMacroArity(string $template): int
    {
        if (preg_match_all('/#([1-9])/', $template, $m) !== false && $m[1] !== []) {
            return max(array_map('intval', $m[1]));
        }

        return 0;
    }

    /**
     * @param array<string, array{arity?: int, template?: string}> $macros
     * @return array<string, array{arity:int, template:string}>
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

            $normalized[$macroName] = [
                'arity' => $arity,
                'template' => $definition['template'],
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, array{arity:int, template:string}> $macros
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
     * @param array<string, array{arity:int, template:string}> $macros
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
                for ($argument = 0; $argument < $macro['arity']; $argument++) {
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
            return '<mfrac>'
                . $this->parseRequiredGroup($source, $offset)
                . $this->parseRequiredGroup($source, $offset)
                . '</mfrac>';
        }

        if ($command === 'sqrt') {
            $degree = $this->parseOptionalRootDegree($source, $offset);
            $radicand = $this->parseRequiredGroup($source, $offset);

            if ($degree !== null) {
                return '<mroot>' . $radicand . $degree . '</mroot>';
            }

            return '<msqrt>' . $radicand . '</msqrt>';
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

        if ($command === 'begin') {
            return $this->parseEnvironment($source, $offset);
        }

        if ($command === 'end') {
            throw new \InvalidArgumentException('Unexpected TeX environment end at offset ' . $offset);
        }

        if ($command === 'left' || $command === 'right') {
            return $this->parseFenceCommand($source, $offset);
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

    private function parseArrayEnvironment(string $source, int &$offset): string
    {
        $columnAlign = $this->arrayColumnAlign($this->readRequiredGroupText($source, $offset));
        $rows = $this->splitAlignmentRows($this->readEnvironmentContent($source, $offset, 'array'), 'array');

        return $this->environmentTable($rows, ' columnalign="' . $this->esc($columnAlign) . '"');
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

    private function parseFenceCommand(string $source, int &$offset): string
    {
        $delimiter = $this->readFenceDelimiter($source, $offset);
        if ($delimiter === '') {
            return '';
        }

        return '<mo fence="true" stretchy="true">' . $this->esc($delimiter) . '</mo>';
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
