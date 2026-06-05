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
    private const EXTENSIBLE_ARROW_COMMANDS = [
        'xleftarrow' => '←',
        'xrightarrow' => '→',
        'xleftrightarrow' => '↔',
        'xLeftarrow' => '⇐',
        'xRightarrow' => '⇒',
        'xLeftrightarrow' => '⇔',
        'xmapsto' => '↦',
    ];

    /** @var array<string, array{glyph: string, position: 'over'|'under'}> */
    private const ARROW_ACCENT_COMMANDS = [
        'overleftarrow' => ['glyph' => '←', 'position' => 'over'],
        'overrightarrow' => ['glyph' => '→', 'position' => 'over'],
        'overleftrightarrow' => ['glyph' => '↔', 'position' => 'over'],
        'underleftarrow' => ['glyph' => '←', 'position' => 'under'],
        'underrightarrow' => ['glyph' => '→', 'position' => 'under'],
        'underleftrightarrow' => ['glyph' => '↔', 'position' => 'under'],
    ];

    /** @var array<string, string> */
    private const CANCEL_COMMANDS = [
        'bcancel' => 'downdiagonalstrike',
        'cancel' => 'updiagonalstrike',
        'xcancel' => 'updiagonalstrike downdiagonalstrike',
    ];

    /** @var array<string, array{width: string, lspace?: string}> */
    private const OVERLAP_BOX_COMMANDS = [
        'clap' => ['width' => '0', 'lspace' => '-0.5width'],
        'llap' => ['width' => '0', 'lspace' => '-1width'],
        'mathclap' => ['width' => '0', 'lspace' => '-0.5width'],
        'mathllap' => ['width' => '0', 'lspace' => '-1width'],
        'mathrlap' => ['width' => '0'],
        'rlap' => ['width' => '0'],
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

    /** @var array<string, string> */
    private const ACCESSIBILITY_TOKEN_TEXT = [
        '+' => 'plus',
        '-' => 'minus',
        '=' => 'equals',
        '<' => 'less than',
        '>' => 'greater than',
        '/' => 'slash',
        ',' => 'comma',
        ':' => 'colon',
        ';' => 'semicolon',
        '(' => 'left parenthesis',
        ')' => 'right parenthesis',
        '[' => 'left bracket',
        ']' => 'right bracket',
        '{' => 'left brace',
        '}' => 'right brace',
        '|' => 'vertical bar',
        'α' => 'alpha',
        'β' => 'beta',
        'γ' => 'gamma',
        'δ' => 'delta',
        'ϵ' => 'epsilon',
        'θ' => 'theta',
        'λ' => 'lambda',
        'μ' => 'mu',
        'π' => 'pi',
        'σ' => 'sigma',
        'ω' => 'omega',
        '∞' => 'infinity',
        '≈' => 'approximately equals',
        '∩' => 'intersection',
        '⋅' => 'dot',
        '∪' => 'union',
        '∅' => 'empty set',
        '≡' => 'equivalent',
        '∃' => 'there exists',
        '∀' => 'for all',
        '≥' => 'greater than or equal to',
        '⇔' => 'if and only if',
        '∈' => 'in',
        '∫' => 'integral',
        '∧' => 'and',
        '≤' => 'less than or equal to',
        '←' => 'left arrow',
        '↔' => 'left right arrow',
        '∨' => 'or',
        '¬' => 'not',
        '≠' => 'not equal',
        '∉' => 'not in',
        '∂' => 'partial',
        '±' => 'plus or minus',
        '∏' => 'product',
        '→' => 'to',
        '⇒' => 'implies',
        '∖' => 'set minus',
        '⊂' => 'subset',
        '⊆' => 'subset or equal',
        '⊃' => 'superset',
        '⊇' => 'superset or equal',
        '∑' => 'sum',
        '×' => 'times',
        '⟨' => 'left angle bracket',
        '⟩' => 'right angle bracket',
        '‖' => 'double vertical bar',
        '⏞' => 'over brace',
        '⏟' => 'under brace',
        '¯' => 'bar',
        '‾' => 'overline',
        '˙' => 'dot',
        '¨' => 'double dot',
        '~' => 'tilde',
    ];

    private int $activeLeftFenceDepth = 0;

    /** @var array<string, array{label:string, id:string, reference:string, tag:?string, tagStarred:bool}> */
    private array $equationReferenceLabels = [];

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
     * @param array<string, array{label?: string, id?: string, reference?: string, tag?: ?string, tagStarred?: bool}|string> $referenceLabels
     */
    public function mathMlFor(AstNode $node, array $macros = [], array $referenceLabels = []): string
    {
        return $this->texToMathMl((string) $node->attr('text', ''), $node->attr('display') === true, $macros, $referenceLabels);
    }

    /**
     * @param array<string, array{arity?: int, template?: string, optionalDefault?: string}> $macros
     * @param array<string, array{label?: string, id?: string, reference?: string, tag?: ?string, tagStarred?: bool}|string> $referenceLabels
     */
    public function accessibleMathMlFor(AstNode $node, array $macros = [], array $referenceLabels = []): string
    {
        return $this->texToMathMl((string) $node->attr('text', ''), $node->attr('display') === true, $macros, $referenceLabels, true);
    }

    /**
     * @param array<string, array{arity?: int, template?: string, optionalDefault?: string}> $macros
     * @param array<string, array{label?: string, id?: string, reference?: string, tag?: ?string, tagStarred?: bool}|string> $referenceLabels
     */
    public function texToAccessibleMathMl(string $tex, bool $display = false, array $macros = [], array $referenceLabels = []): string
    {
        return $this->texToMathMl($tex, $display, $macros, $referenceLabels, true);
    }

    /**
     * @param array<string, array{arity?: int, template?: string, optionalDefault?: string}> $macros
     * @param array<string, array{label?: string, id?: string, reference?: string, tag?: ?string, tagStarred?: bool}|string> $referenceLabels
     */
    public function texToMathMl(string $tex, bool $display = false, array $macros = [], array $referenceLabels = [], bool $includeAccessibility = false): string
    {
        $previousReferenceLabels = $this->equationReferenceLabels;
        $this->equationReferenceLabels = $this->normalizeEquationReferenceLabels($referenceLabels);

        try {
            $expandedTex = $this->expandRawTexMathMacros($tex, $this->normalizeMacroDefinitions($macros));
            $equation = $this->extractEquationMetadata($expandedTex);
            $this->activeLeftFenceDepth = 0;
            $offset = 0;
            $children = $this->parseExpression($equation['tex'], $offset, null);
            $this->skipWhitespace($equation['tex'], $offset);

            if ($offset < strlen($equation['tex'])) {
                throw new \InvalidArgumentException('Unsupported TeX token at offset ' . $offset);
            }

            $displayMode = $display ? 'block' : 'inline';
            $body = $this->renderEquationBody($children, $equation);
            $mathAttributes = 'display="' . $displayMode . '"';
            $annotations = '<annotation encoding="application/x-tex">' . $this->esc($tex) . '</annotation>';
            if ($equation['label'] !== null) {
                $annotations .= '<annotation encoding="application/x-tex-label">' . $this->esc($equation['label']) . '</annotation>';
            }
            if ($includeAccessibility) {
                $accessibility = $this->mathMlAccessibilityMetadata($body);
                $mathAttributes .= ' alttext="' . $this->esc($accessibility['alttext']) . '" intent="' . $this->esc($accessibility['intent']) . '"';
                $annotations .= '<annotation encoding="application/x-portlibs-math-alttext">' . $this->esc($accessibility['alttext']) . '</annotation>'
                    . '<annotation encoding="application/x-portlibs-math-intent">' . $this->esc($accessibility['intent']) . '</annotation>';
            }

            return '<math xmlns="http://www.w3.org/1998/Math/MathML" ' . $mathAttributes . '>'
                . '<semantics>'
                . $body
                . $annotations
                . '</semantics>'
                . '</math>';
        } finally {
            $this->equationReferenceLabels = $previousReferenceLabels;
        }
    }

    /**
     * @return array{alttext:string, intent:string}
     */
    private function mathMlAccessibilityMetadata(string $mathml): array
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadXML(
            '<math-accessibility-root>' . $mathml . '</math-accessibility-root>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        if (!$loaded || !$dom->documentElement instanceof \DOMElement) {
            throw new \InvalidArgumentException('Generated MathML accessibility handoff is not well-formed');
        }

        $altText = $this->normalizeAccessibilityText($this->mathMlNodeAltText($dom->documentElement));
        $intent = $this->mathMlNodeIntent($dom->documentElement);

        return [
            'alttext' => $altText !== '' ? $altText : 'math expression',
            'intent' => $intent !== '' ? $intent : 'math',
        ];
    }

    private function mathMlNodeAltText(\DOMNode $node): string
    {
        if ($node instanceof \DOMText) {
            return $node->wholeText;
        }

        if (!$node instanceof \DOMElement) {
            return $this->joinAccessibilityText($this->mathMlChildAltTexts($node));
        }

        $name = $node->localName;
        if ($name === 'mi' || $name === 'mn' || $name === 'mo' || $name === 'mtext') {
            return $this->accessibilityTokenText($node->textContent);
        }

        $children = $this->mathMlElementChildren($node);

        return match ($name) {
            'mfrac' => 'fraction ' . $this->mathMlChildAltText($children, 0) . ' over ' . $this->mathMlChildAltText($children, 1),
            'msqrt' => 'square root of ' . $this->joinAccessibilityText($this->mathMlChildAltTexts($node)),
            'mroot' => $this->mathMlChildAltText($children, 1) . ' root of ' . $this->mathMlChildAltText($children, 0),
            'msub' => $this->mathMlChildAltText($children, 0) . ' sub ' . $this->mathMlChildAltText($children, 1),
            'msup' => $this->mathMlChildAltText($children, 0) . ' superscript ' . $this->mathMlChildAltText($children, 1),
            'msubsup' => $this->mathMlChildAltText($children, 0) . ' sub ' . $this->mathMlChildAltText($children, 1) . ' superscript ' . $this->mathMlChildAltText($children, 2),
            'munder' => $this->mathMlChildAltText($children, 0) . ' under ' . $this->mathMlChildAltText($children, 1),
            'mover' => $this->mathMlChildAltText($children, 0) . ' over ' . $this->mathMlChildAltText($children, 1),
            'munderover' => $this->mathMlChildAltText($children, 0) . ' under ' . $this->mathMlChildAltText($children, 1) . ' over ' . $this->mathMlChildAltText($children, 2),
            'mtable' => 'table ' . implode('; ', array_map(fn (\DOMElement $child): string => $this->mathMlNodeAltText($child), $children)),
            'mtr', 'mlabeledtr' => 'row ' . implode(', ', array_map(fn (\DOMElement $child): string => $this->mathMlNodeAltText($child), $children)),
            'mtd' => $this->joinAccessibilityText($this->mathMlChildAltTexts($node)),
            'mspace' => 'space',
            'menclose' => 'enclosed ' . $this->joinAccessibilityText($this->mathMlChildAltTexts($node)),
            'annotation', 'annotation-xml' => '',
            default => $this->joinAccessibilityText($this->mathMlChildAltTexts($node)),
        };
    }

    private function mathMlNodeIntent(\DOMNode $node): string
    {
        if (!$node instanceof \DOMElement) {
            return $this->joinIntentText($this->mathMlChildIntents($node));
        }

        $name = $node->localName;
        if ($name === 'mi' || $name === 'mn' || $name === 'mo' || $name === 'mtext') {
            return $this->accessibilityIntentToken($node->textContent);
        }

        $children = $this->mathMlElementChildren($node);

        return match ($name) {
            'mfrac' => $this->intentCall('fraction', $children),
            'msqrt' => $this->intentCall('sqrt', $children),
            'mroot' => $this->intentCall('root', $children),
            'msub' => $this->intentCall('subscript', $children),
            'msup' => $this->intentCall('superscript', $children),
            'msubsup' => $this->intentCall('subsup', $children),
            'munder' => $this->intentCall('under', $children),
            'mover' => $this->intentCall('over', $children),
            'munderover' => $this->intentCall('underover', $children),
            'mtable' => $this->intentCall('table', $children),
            'mtr', 'mlabeledtr' => $this->intentCall('row', $children),
            'mtd' => $this->joinIntentText($this->mathMlChildIntents($node)),
            'mspace' => 'space',
            'menclose' => $this->intentCall('enclose', $children),
            'annotation', 'annotation-xml' => '',
            default => $this->intentRow($this->mathMlChildIntents($node)),
        };
    }

    /**
     * @return list<\DOMElement>
     */
    private function mathMlElementChildren(\DOMNode $node): array
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $children[] = $child;
            }
        }

        return $children;
    }

    /**
     * @return list<string>
     */
    private function mathMlChildAltTexts(\DOMNode $node): array
    {
        $texts = [];
        foreach ($node->childNodes as $child) {
            $text = $this->normalizeAccessibilityText($this->mathMlNodeAltText($child));
            if ($text !== '') {
                $texts[] = $text;
            }
        }

        return $texts;
    }

    /**
     * @return list<string>
     */
    private function mathMlChildIntents(\DOMNode $node): array
    {
        $intents = [];
        foreach ($node->childNodes as $child) {
            $intent = $this->mathMlNodeIntent($child);
            if ($intent !== '') {
                $intents[] = $intent;
            }
        }

        return $intents;
    }

    /**
     * @param list<\DOMElement> $children
     */
    private function mathMlChildAltText(array $children, int $index): string
    {
        return isset($children[$index]) ? $this->normalizeAccessibilityText($this->mathMlNodeAltText($children[$index])) : '';
    }

    /**
     * @param list<\DOMElement> $children
     */
    private function intentCall(string $name, array $children): string
    {
        return $name . '(' . implode(',', $this->mathMlElementChildIntents($children)) . ')';
    }

    /**
     * @param list<\DOMElement> $children
     * @return list<string>
     */
    private function mathMlElementChildIntents(array $children): array
    {
        $intents = [];
        foreach ($children as $child) {
            $intent = $this->mathMlNodeIntent($child);
            if ($intent !== '') {
                $intents[] = $intent;
            }
        }

        return $intents;
    }

    /**
     * @param list<string> $intents
     */
    private function intentRow(array $intents): string
    {
        if (count($intents) === 0) {
            return '';
        }

        if (count($intents) === 1) {
            return $intents[0];
        }

        return 'row(' . implode(',', $intents) . ')';
    }

    /**
     * @param list<string> $parts
     */
    private function joinAccessibilityText(array $parts): string
    {
        return $this->normalizeAccessibilityText(implode(' ', array_filter($parts, static fn (string $part): bool => $part !== '')));
    }

    /**
     * @param list<string> $parts
     */
    private function joinIntentText(array $parts): string
    {
        return implode(' ', array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    private function accessibilityTokenText(string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            return '';
        }

        return self::ACCESSIBILITY_TOKEN_TEXT[$token] ?? $token;
    }

    private function accessibilityIntentToken(string $token): string
    {
        $text = $this->accessibilityTokenText($token);
        $slug = strtolower(trim($text));
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug) ?? '';
        $slug = trim($slug, '_');

        return $slug !== '' ? $slug : 'token';
    }

    private function normalizeAccessibilityText(string $text): string
    {
        $text = preg_replace('/\s+/', ' ', trim($text)) ?? '';

        return $text;
    }

    /**
     * @return array{tex:string, label:?string, labelId:?string, tag:?string, tagStarred:bool}
     */
    private function extractEquationMetadata(string $source): array
    {
        $output = '';
        $label = null;
        $labelId = null;
        $tag = null;
        $tagStarred = false;
        $depth = 0;
        $offset = 0;
        $length = strlen($source);

        while ($offset < $length) {
            $char = $source[$offset];
            if ($char === '\\') {
                $commandOffset = $offset + 1;
                $command = $this->readCommandName($source, $commandOffset);
                if ($depth === 0 && $command === 'begin') {
                    $environmentOffset = $commandOffset;
                    $environment = $this->readRequiredGroupText($source, $environmentOffset);
                    $this->readEnvironmentContent($source, $environmentOffset, $environment);
                    $output .= substr($source, $offset, $environmentOffset - $offset);
                    $offset = $environmentOffset;
                    continue;
                }

                if ($depth === 0 && ($command === 'label' || $command === 'tag')) {
                    $cursor = $commandOffset;
                    $starred = false;
                    if ($command === 'tag' && ($source[$cursor] ?? '') === '*') {
                        $starred = true;
                        $cursor++;
                    }

                    $this->skipWhitespace($source, $cursor);
                    $argument = $this->readTexBraceArgument($source, $cursor);
                    if ($argument === null) {
                        throw new \InvalidArgumentException('Expected TeX \\' . $command . ' group at offset ' . $cursor);
                    }

                    $value = trim($argument['value']);
                    if ($value === '') {
                        throw new \InvalidArgumentException('Expected TeX \\' . $command . ' content at offset ' . $cursor);
                    }

                    if ($command === 'label') {
                        if ($label !== null) {
                            throw new \InvalidArgumentException('Duplicate TeX equation label at offset ' . $offset);
                        }

                        $label = $value;
                        $labelId = $this->normalizeEquationLabelId($value);
                    } else {
                        if ($tag !== null) {
                            throw new \InvalidArgumentException('Duplicate TeX equation tag at offset ' . $offset);
                        }

                        $tag = $value;
                        $tagStarred = $starred;
                    }

                    $offset = $argument['next'];
                    continue;
                }

                $output .= $char;
                $offset++;
                if (($source[$offset] ?? '') !== '' && !ctype_alpha($source[$offset])) {
                    $output .= $source[$offset];
                    $offset++;
                }
                continue;
            }

            if ($char === '{') {
                $depth++;
                $output .= $char;
                $offset++;
                continue;
            }

            if ($char === '}') {
                if ($depth > 0) {
                    $depth--;
                }
                $output .= $char;
                $offset++;
                continue;
            }

            $output .= $char;
            $offset++;
        }

        if (($label !== null || $tag !== null) && trim($output) === '') {
            throw new \InvalidArgumentException('Expected TeX math content before equation metadata');
        }

        return [
            'tex' => $output,
            'label' => $label,
            'labelId' => $labelId,
            'tag' => $tag,
            'tagStarred' => $tagStarred,
        ];
    }

    private function normalizeEquationLabelId(string $label): string
    {
        $id = preg_replace('/[^A-Za-z0-9_.:-]+/', '-', trim($label)) ?? '';
        $id = trim($id, '-');
        if ($id === '') {
            throw new \InvalidArgumentException('Unsupported TeX equation label ' . $label);
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9_.:-]*$/', $id) !== 1) {
            $id = 'math-' . $id;
        }

        return $id;
    }

    /**
     * @param list<string> $children
     * @param array{tex:string, label:?string, labelId:?string, tag:?string, tagStarred:bool} $equation
     */
    private function renderEquationBody(array $children, array $equation): string
    {
        $body = $this->row($children);
        if ($equation['tag'] !== null) {
            $tagText = $equation['tagStarred'] ? $equation['tag'] : '(' . $equation['tag'] . ')';
            $labelAttribute = $equation['labelId'] !== null ? ' id="' . $this->esc($equation['labelId']) . '"' : '';

            return '<mtable><mlabeledtr>'
                . '<mtd><mtext>' . $this->esc($tagText) . '</mtext></mtd>'
                . '<mtd' . $labelAttribute . '>' . $body . '</mtd>'
                . '</mlabeledtr></mtable>';
        }

        if ($equation['labelId'] !== null) {
            return $this->withMathMlId($body, $equation['labelId']);
        }

        return $body;
    }

    private function withMathMlId(string $mathml, string $id): string
    {
        $withId = preg_replace('/^<([A-Za-z][A-Za-z0-9]*)(?=[\s>])/', '<$1 id="' . $this->esc($id) . '"', $mathml, 1);
        if (is_string($withId)) {
            return $withId;
        }

        return '<mrow id="' . $this->esc($id) . '">' . $mathml . '</mrow>';
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
     * @return array<string, array{label:string, id:string, reference:string, tag:?string, tagStarred:bool}>
     */
    public function equationReferenceLabelsFromDocument(AstNode $node): array
    {
        $labels = [];
        $nextAutomaticNumber = 1;
        $this->collectEquationReferenceLabelsFromDocument($node, $labels, $nextAutomaticNumber);

        return $labels;
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
            $scriptPlacement = $this->readScriptPlacementCommand($source, $offset);
            $nodes[] = $this->applyScripts($source, $offset, $base, $scriptPlacement);
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

        if ($command === 'ref' || $command === 'eqref') {
            return $this->parseEquationReferenceCommand($source, $offset, $command);
        }

        if ($command === 'limits' || $command === 'nolimits') {
            throw new \InvalidArgumentException('Unexpected TeX \\' . $command . ' without previous math base at offset ' . $offset);
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

        if ($command === 'smash') {
            return $this->parseSmashCommand($source, $offset);
        }

        if (isset(self::OVERLAP_BOX_COMMANDS[$command])) {
            return $this->parseOverlapBoxCommand($source, $offset, $command);
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

        if (isset(self::EXTENSIBLE_ARROW_COMMANDS[$command])) {
            return $this->parseExtensibleArrowCommand($source, $offset, $command);
        }

        if (isset(self::ARROW_ACCENT_COMMANDS[$command])) {
            return $this->parseArrowAccentCommand($source, $offset, $command);
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
        if ($environment === 'smallmatrix') {
            return $this->parseSmallMatrixEnvironment($source, $offset);
        }

        if ($environment === 'subarray') {
            return $this->parseSubarrayEnvironment($source, $offset);
        }

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

    private function parseSmallMatrixEnvironment(string $source, int &$offset): string
    {
        $content = $this->readEnvironmentContent($source, $offset, 'smallmatrix');
        if ($this->endsWithTopLevelRowSeparator($content)) {
            throw new \InvalidArgumentException('Expected TeX smallmatrix row content at final row');
        }

        $rows = $this->splitAlignmentRows($content, 'smallmatrix');

        return '<mstyle scriptlevel="1">'
            . $this->environmentTable($rows, ' rowspacing="0.1em" columnspacing="0.2778em"')
            . '</mstyle>';
    }

    private function parseSubarrayEnvironment(string $source, int &$offset): string
    {
        $columnAlign = $this->arrayColumnAlign($this->readRequiredGroupText($source, $offset));
        $content = $this->readEnvironmentContent($source, $offset, 'subarray');
        if ($this->endsWithTopLevelRowSeparator($content)) {
            throw new \InvalidArgumentException('Expected TeX subarray row content at final row');
        }

        $rows = $this->splitAlignmentRows($content, 'subarray');
        $this->validateAmsRowEnvironmentRows($rows, 'subarray', count(explode(' ', $columnAlign)));

        return $this->environmentTable($rows, ' columnalign="' . $this->esc($columnAlign) . '" rowspacing="0.1em"');
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

        return $this->environmentTable($rows, ' columnalign="' . $this->esc($spec['columnalign']) . '"', true, $environment);
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

        return $this->environmentTable($rows, ' columnalign="' . $this->esc(implode(' ', array_fill(0, $pairs, 'right left'))) . '"', true, $environment);
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

    private function parseSmashCommand(string $source, int &$offset): string
    {
        $attributes = $this->smashPaddingAttributes($this->readOptionalSmashPosition($source, $offset));

        return '<mpadded' . $attributes . '>'
            . $this->parseRequiredNonEmptyGroup($source, $offset, 'smash content')
            . '</mpadded>';
    }

    private function readOptionalSmashPosition(string $source, int &$offset): ?string
    {
        $this->skipWhitespace($source, $offset);
        $argument = $this->readTexBracketArgument($source, $offset);
        if ($argument === null) {
            return null;
        }

        $position = trim($argument['value']);
        if ($position !== 't' && $position !== 'b') {
            throw new \InvalidArgumentException('Unsupported TeX \\smash position ' . $position);
        }

        $offset = $argument['next'];

        return $position;
    }

    private function smashPaddingAttributes(?string $position): string
    {
        if ($position === 't') {
            return ' height="0"';
        }

        if ($position === 'b') {
            return ' depth="0"';
        }

        return ' height="0" depth="0"';
    }

    private function parseOverlapBoxCommand(string $source, int &$offset, string $command): string
    {
        $attributes = '';
        foreach (self::OVERLAP_BOX_COMMANDS[$command] as $name => $value) {
            $attributes .= ' ' . $name . '="' . $this->esc($value) . '"';
        }

        return '<mpadded' . $attributes . '>'
            . $this->parseRequiredNonEmptyGroup($source, $offset, $command . ' content')
            . '</mpadded>';
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
        $variant = self::MATH_VARIANT_COMMANDS[$command];

        return '<mstyle mathvariant="' . $variant . '">'
            . $this->rewriteMathVariantIdentifiers($this->parseMathVariantArgument($source, $offset, $command), $variant)
            . '</mstyle>';
    }

    private function rewriteMathVariantIdentifiers(string $mathml, string $variant): string
    {
        $rewritten = preg_replace_callback('/<mi>([A-Za-z0-9])<\/mi>/', function (array $matches) use ($variant): string {
            $character = $this->mathVariantUnicodeCharacter($variant, $matches[1]) ?? $matches[1];

            return '<mi>' . $this->esc($character) . '</mi>';
        }, $mathml);

        if (!is_string($rewritten)) {
            return $mathml;
        }

        $withNumbers = preg_replace_callback('/<mn>([0-9]+)<\/mn>/', function (array $matches) use ($variant): string {
            $digits = '';
            for ($offset = 0; $offset < strlen($matches[1]); $offset++) {
                $digit = $matches[1][$offset];
                $digits .= $this->mathVariantUnicodeCharacter($variant, $digit) ?? $digit;
            }

            return '<mn>' . $this->esc($digits) . '</mn>';
        }, $rewritten);

        return is_string($withNumbers) ? $withNumbers : $rewritten;
    }

    private function mathVariantUnicodeCharacter(string $variant, string $character): ?string
    {
        $codepoint = $this->mathVariantUnicodeCodepoint($variant, $character);
        if ($codepoint === null) {
            return null;
        }

        return $this->utf8FromCodepoint($codepoint);
    }

    private function mathVariantUnicodeCodepoint(string $variant, string $character): ?int
    {
        $ord = ord($character);

        return match ($variant) {
            'bold' => $this->mathVariantOffsetCodepoint($ord, 0x1D400, 0x1D41A, 0x1D7CE),
            'double-struck' => $this->mathDoubleStruckCodepoint($ord, $character),
            'fraktur' => $this->mathFrakturCodepoint($ord, $character),
            'italic' => $this->mathItalicCodepoint($ord, $character),
            'monospace' => $this->mathVariantOffsetCodepoint($ord, 0x1D670, 0x1D68A, 0x1D7F6),
            'sans-serif' => $this->mathVariantOffsetCodepoint($ord, 0x1D5A0, 0x1D5BA, 0x1D7E2),
            'script' => $this->mathScriptCodepoint($ord, $character),
            default => null,
        };
    }

    private function mathVariantOffsetCodepoint(int $ord, int $uppercaseBase, int $lowercaseBase, ?int $digitBase): ?int
    {
        if ($ord >= 65 && $ord <= 90) {
            return $uppercaseBase + ($ord - 65);
        }

        if ($ord >= 97 && $ord <= 122) {
            return $lowercaseBase + ($ord - 97);
        }

        if ($digitBase !== null && $ord >= 48 && $ord <= 57) {
            return $digitBase + ($ord - 48);
        }

        return null;
    }

    private function mathDoubleStruckCodepoint(int $ord, string $character): ?int
    {
        $exceptions = [
            'C' => 0x2102,
            'H' => 0x210D,
            'N' => 0x2115,
            'P' => 0x2119,
            'Q' => 0x211A,
            'R' => 0x211D,
            'Z' => 0x2124,
        ];
        if (isset($exceptions[$character])) {
            return $exceptions[$character];
        }

        return $this->mathVariantOffsetCodepoint($ord, 0x1D538, 0x1D552, 0x1D7D8);
    }

    private function mathFrakturCodepoint(int $ord, string $character): ?int
    {
        $exceptions = [
            'C' => 0x212D,
            'H' => 0x210C,
            'I' => 0x2111,
            'R' => 0x211C,
            'Z' => 0x2128,
        ];
        if (isset($exceptions[$character])) {
            return $exceptions[$character];
        }

        return $this->mathVariantOffsetCodepoint($ord, 0x1D504, 0x1D51E, null);
    }

    private function mathItalicCodepoint(int $ord, string $character): ?int
    {
        if ($character === 'h') {
            return 0x210E;
        }

        return $this->mathVariantOffsetCodepoint($ord, 0x1D434, 0x1D44E, null);
    }

    private function mathScriptCodepoint(int $ord, string $character): ?int
    {
        $exceptions = [
            'B' => 0x212C,
            'E' => 0x2130,
            'F' => 0x2131,
            'H' => 0x210B,
            'I' => 0x2110,
            'L' => 0x2112,
            'M' => 0x2133,
            'R' => 0x211B,
            'e' => 0x212F,
            'g' => 0x210A,
            'o' => 0x2134,
        ];
        if (isset($exceptions[$character])) {
            return $exceptions[$character];
        }

        return $this->mathVariantOffsetCodepoint($ord, 0x1D49C, 0x1D4B6, null);
    }

    private function utf8FromCodepoint(int $codepoint): string
    {
        if ($codepoint <= 0x7F) {
            return chr($codepoint);
        }

        if ($codepoint <= 0x7FF) {
            return chr(0xC0 | ($codepoint >> 6))
                . chr(0x80 | ($codepoint & 0x3F));
        }

        if ($codepoint <= 0xFFFF) {
            return chr(0xE0 | ($codepoint >> 12))
                . chr(0x80 | (($codepoint >> 6) & 0x3F))
                . chr(0x80 | ($codepoint & 0x3F));
        }

        return chr(0xF0 | ($codepoint >> 18))
            . chr(0x80 | (($codepoint >> 12) & 0x3F))
            . chr(0x80 | (($codepoint >> 6) & 0x3F))
            . chr(0x80 | ($codepoint & 0x3F));
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

    private function parseExtensibleArrowCommand(string $source, int &$offset, string $command): string
    {
        $below = $this->parseOptionalNonEmptyBracketArgument($source, $offset, $command . ' lower label');
        $above = $this->parseRequiredNonEmptyGroup($source, $offset, $command . ' upper label');
        $arrow = '<mo stretchy="true">' . $this->esc(self::EXTENSIBLE_ARROW_COMMANDS[$command]) . '</mo>';

        if ($below !== null) {
            return '<munderover>' . $arrow . $below . $above . '</munderover>';
        }

        return '<mover>' . $arrow . $above . '</mover>';
    }

    private function parseEquationReferenceCommand(string $source, int &$offset, string $command): string
    {
        $label = trim($this->readRequiredGroupText($source, $offset));
        if ($label === '') {
            throw new \InvalidArgumentException('Expected TeX \\' . $command . ' label at offset ' . $offset);
        }

        $targetId = $this->normalizeEquationLabelId($label);
        $referenceText = $this->equationReferenceLabels[$targetId]['reference'] ?? $label;
        $reference = '<mtext href="#' . $this->esc($targetId) . '">' . $this->esc($referenceText) . '</mtext>';
        if ($command === 'eqref') {
            return '<mrow><mo>(</mo>' . $reference . '<mo>)</mo></mrow>';
        }

        return $reference;
    }

    private function parseArrowAccentCommand(string $source, int &$offset, string $command): string
    {
        $spec = self::ARROW_ACCENT_COMMANDS[$command];
        $base = $this->parseRequiredNonEmptyGroup($source, $offset, $command . ' base');
        $arrow = '<mo stretchy="true">' . $this->esc($spec['glyph']) . '</mo>';

        if ($spec['position'] === 'over') {
            return '<mover accent="true">' . $base . $arrow . '</mover>';
        }

        return '<munder accentunder="true">' . $base . $arrow . '</munder>';
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
    private function environmentTable(array $rows, string $attributes, bool $allowRowMetadata = false, string $environment = ''): string
    {
        $table = '<mtable' . $attributes . '>';
        foreach ($rows as $rowIndex => $row) {
            $metadata = [
                'labelId' => null,
                'tag' => null,
                'tagStarred' => false,
            ];

            if ($allowRowMetadata) {
                $parsed = $this->extractEnvironmentRowMetadata($row, $environment, $rowIndex);
                $row = $parsed['cells'];
                $metadata = [
                    'labelId' => $parsed['labelId'],
                    'tag' => $parsed['tag'],
                    'tagStarred' => $parsed['tagStarred'],
                ];
            }

            if ($metadata['tag'] !== null) {
                $tagText = $metadata['tagStarred'] ? $metadata['tag'] : '(' . $metadata['tag'] . ')';
                $table .= '<mlabeledtr' . ($metadata['labelId'] !== null ? ' id="' . $this->esc($metadata['labelId']) . '"' : '') . '>'
                    . '<mtd><mtext>' . $this->esc($tagText) . '</mtext></mtd>';
            } else {
                $table .= '<mtr' . ($metadata['labelId'] !== null ? ' id="' . $this->esc($metadata['labelId']) . '"' : '') . '>';
            }

            foreach ($row as $cell) {
                $table .= '<mtd>' . $this->parseEnvironmentCell($cell) . '</mtd>';
            }
            $table .= $metadata['tag'] !== null ? '</mlabeledtr>' : '</mtr>';
        }

        return $table . '</mtable>';
    }

    /**
     * @param list<string> $row
     * @return array{cells:list<string>, label:?string, labelId:?string, tag:?string, tagStarred:bool}
     */
    private function extractEnvironmentRowMetadata(array $row, string $environment, int $rowIndex): array
    {
        $label = null;
        $labelId = null;
        $tag = null;
        $tagStarred = false;
        $cells = [];

        foreach ($row as $cell) {
            $parsed = $this->stripEnvironmentCellRowMetadata($cell, $environment, $rowIndex);
            $cells[] = trim($parsed['cell']);

            if ($parsed['label'] !== null) {
                if ($label !== null) {
                    throw new \InvalidArgumentException('Duplicate TeX ' . $environment . ' row label at row ' . ($rowIndex + 1));
                }

                $label = $parsed['label'];
                $labelId = $this->normalizeEquationLabelId($parsed['label']);
            }

            if ($parsed['tag'] !== null) {
                if ($tag !== null) {
                    throw new \InvalidArgumentException('Duplicate TeX ' . $environment . ' row tag at row ' . ($rowIndex + 1));
                }

                $tag = $parsed['tag'];
                $tagStarred = $parsed['tagStarred'];
            }
        }

        $hasContent = false;
        foreach ($cells as $cell) {
            if (trim($cell) !== '') {
                $hasContent = true;
                break;
            }
        }

        if (!$hasContent) {
            throw new \InvalidArgumentException('Expected TeX ' . $environment . ' row content at row ' . ($rowIndex + 1));
        }

        return [
            'cells' => $cells,
            'label' => $label,
            'labelId' => $labelId,
            'tag' => $tag,
            'tagStarred' => $tagStarred,
        ];
    }

    /**
     * @return array{cell:string, label:?string, tag:?string, tagStarred:bool}
     */
    private function stripEnvironmentCellRowMetadata(string $source, string $environment, int $rowIndex): array
    {
        $output = '';
        $label = null;
        $tag = null;
        $tagStarred = false;
        $depth = 0;
        $offset = 0;
        $length = strlen($source);

        while ($offset < $length) {
            $char = $source[$offset];
            if ($char === '\\') {
                $commandOffset = $offset + 1;
                $command = $this->readCommandName($source, $commandOffset);
                if ($depth === 0 && $command === 'begin') {
                    $environmentOffset = $commandOffset;
                    $nestedEnvironment = $this->readRequiredGroupText($source, $environmentOffset);
                    $this->readEnvironmentContent($source, $environmentOffset, $nestedEnvironment);
                    $output .= substr($source, $offset, $environmentOffset - $offset);
                    $offset = $environmentOffset;
                    continue;
                }

                if ($depth === 0 && ($command === 'label' || $command === 'tag')) {
                    $cursor = $commandOffset;
                    $starred = false;
                    if ($command === 'tag' && ($source[$cursor] ?? '') === '*') {
                        $starred = true;
                        $cursor++;
                    }

                    $this->skipWhitespace($source, $cursor);
                    $argument = $this->readTexBraceArgument($source, $cursor);
                    if ($argument === null) {
                        throw new \InvalidArgumentException('Expected TeX \\' . $command . ' group in ' . $environment . ' row ' . ($rowIndex + 1));
                    }

                    $value = trim($argument['value']);
                    if ($value === '') {
                        throw new \InvalidArgumentException('Expected TeX \\' . $command . ' content in ' . $environment . ' row ' . ($rowIndex + 1));
                    }

                    if ($command === 'label') {
                        if ($label !== null) {
                            throw new \InvalidArgumentException('Duplicate TeX ' . $environment . ' row label at row ' . ($rowIndex + 1));
                        }

                        $label = $value;
                    } else {
                        if ($tag !== null) {
                            throw new \InvalidArgumentException('Duplicate TeX ' . $environment . ' row tag at row ' . ($rowIndex + 1));
                        }

                        $tag = $value;
                        $tagStarred = $starred;
                    }

                    $offset = $argument['next'];
                    continue;
                }

                $output .= $char;
                $offset++;
                if (($source[$offset] ?? '') !== '' && !ctype_alpha($source[$offset])) {
                    $output .= $source[$offset];
                    $offset++;
                }
                continue;
            }

            if ($char === '{') {
                $depth++;
                $output .= $char;
                $offset++;
                continue;
            }

            if ($char === '}') {
                if ($depth > 0) {
                    $depth--;
                }
                $output .= $char;
                $offset++;
                continue;
            }

            $output .= $char;
            $offset++;
        }

        return [
            'cell' => $output,
            'label' => $label,
            'tag' => $tag,
            'tagStarred' => $tagStarred,
        ];
    }

    /**
     * @param array<string, array{label:string, id:string, reference:string, tag:?string, tagStarred:bool}> $labels
     */
    private function collectEquationReferenceLabelsFromDocument(AstNode $node, array &$labels, int &$nextAutomaticNumber): void
    {
        if ($node->type === 'math') {
            $this->collectEquationReferenceLabelsFromTex(
                (string) $node->attr('text', ''),
                $labels,
                $nextAutomaticNumber,
                $node->attr('display') === true
            );
        }

        foreach ($node->children as $child) {
            $this->collectEquationReferenceLabelsFromDocument($child, $labels, $nextAutomaticNumber);
        }
    }

    /**
     * @param array<string, array{label:string, id:string, reference:string, tag:?string, tagStarred:bool}> $labels
     */
    private function collectEquationReferenceLabelsFromTex(string $source, array &$labels, int &$nextAutomaticNumber, bool $numberUntagged): void
    {
        $equation = $this->extractEquationMetadata($source);
        if ($equation['label'] !== null) {
            $automaticReference = null;
            if ($numberUntagged && $equation['tag'] === null) {
                $automaticReference = (string) $nextAutomaticNumber;
                $nextAutomaticNumber++;
            }

            $this->registerEquationReferenceLabel($labels, $equation['label'], $equation['tag'], $equation['tagStarred'], $automaticReference);
        }

        $this->collectEnvironmentEquationReferenceLabelsFromTex($source, $labels, $nextAutomaticNumber, $numberUntagged);
    }

    /**
     * @param array<string, array{label:string, id:string, reference:string, tag:?string, tagStarred:bool}> $labels
     */
    private function collectEnvironmentEquationReferenceLabelsFromTex(string $source, array &$labels, int &$nextAutomaticNumber, bool $numberUntagged): void
    {
        $offset = 0;
        $length = strlen($source);

        while ($offset < $length) {
            if ($source[$offset] !== '\\') {
                $offset++;
                continue;
            }

            $commandOffset = $offset + 1;
            $command = $this->readCommandName($source, $commandOffset);
            if ($command !== 'begin') {
                $offset++;
                continue;
            }

            $environmentOffset = $commandOffset;
            $environment = $this->readRequiredGroupText($source, $environmentOffset);
            $contentOffset = $environmentOffset;
            $alignedAtPairs = null;
            if (isset(self::AMS_ALIGNEDAT_ENVIRONMENTS[$environment])) {
                $alignedAtPairs = $this->normalizeAmsAlignedAtPairCount($this->readRequiredGroupText($source, $contentOffset), $environment);
            }

            $content = $this->readEnvironmentContent($source, $contentOffset, $environment);
            if (isset(self::AMS_ROW_ENVIRONMENTS[$environment])) {
                if ($this->endsWithTopLevelRowSeparator($content)) {
                    throw new \InvalidArgumentException('Expected TeX ' . $environment . ' row content at final row');
                }

                $rows = $this->splitAlignmentRows($content, $environment);
                $this->validateAmsRowEnvironmentRows($rows, $environment, self::AMS_ROW_ENVIRONMENTS[$environment]['columns']);
                $this->collectEquationReferenceLabelsFromEnvironmentRows($rows, $environment, $labels, $nextAutomaticNumber, $numberUntagged);
            } elseif ($alignedAtPairs !== null) {
                if ($this->endsWithTopLevelRowSeparator($content)) {
                    throw new \InvalidArgumentException('Expected TeX ' . $environment . ' row content at final row');
                }

                $rows = $this->splitAlignmentRows($content, $environment);
                $this->validateAmsRowEnvironmentRows($rows, $environment, $alignedAtPairs * 2);
                $this->collectEquationReferenceLabelsFromEnvironmentRows($rows, $environment, $labels, $nextAutomaticNumber, $numberUntagged);
            }

            $this->collectEnvironmentEquationReferenceLabelsFromTex($content, $labels, $nextAutomaticNumber, $numberUntagged);
            $offset = $contentOffset;
        }
    }

    /**
     * @param list<list<string>> $rows
     * @param array<string, array{label:string, id:string, reference:string, tag:?string, tagStarred:bool}> $labels
     */
    private function collectEquationReferenceLabelsFromEnvironmentRows(array $rows, string $environment, array &$labels, int &$nextAutomaticNumber, bool $numberUntagged): void
    {
        foreach ($rows as $rowIndex => $row) {
            $parsed = $this->extractEnvironmentRowMetadata($row, $environment, $rowIndex);
            if ($parsed['label'] !== null) {
                $automaticReference = null;
                if ($numberUntagged && $parsed['tag'] === null) {
                    $automaticReference = (string) $nextAutomaticNumber;
                    $nextAutomaticNumber++;
                }

                $this->registerEquationReferenceLabel($labels, $parsed['label'], $parsed['tag'], $parsed['tagStarred'], $automaticReference);
            }
        }
    }

    /**
     * @param array<string, array{label:string, id:string, reference:string, tag:?string, tagStarred:bool}> $labels
     */
    private function registerEquationReferenceLabel(array &$labels, string $label, ?string $tag, bool $tagStarred, ?string $automaticReference = null): void
    {
        $label = trim($label);
        if ($label === '') {
            throw new \InvalidArgumentException('Expected TeX equation label content');
        }

        $id = $this->normalizeEquationLabelId($label);
        if (isset($labels[$id])) {
            throw new \InvalidArgumentException('Duplicate TeX equation label ' . $label);
        }

        $reference = $tag !== null ? trim($tag) : ($automaticReference ?? $label);
        if ($reference === '') {
            throw new \InvalidArgumentException('Expected TeX equation reference text for ' . $label);
        }

        $labels[$id] = [
            'label' => $label,
            'id' => $id,
            'reference' => $reference,
            'tag' => $tag,
            'tagStarred' => $tagStarred,
        ];
    }

    /**
     * @param array<string, array{label?: string, id?: string, reference?: string, tag?: ?string, tagStarred?: bool}|string> $referenceLabels
     * @return array<string, array{label:string, id:string, reference:string, tag:?string, tagStarred:bool}>
     */
    private function normalizeEquationReferenceLabels(array $referenceLabels): array
    {
        $normalized = [];
        foreach ($referenceLabels as $key => $entry) {
            $tag = null;
            $tagStarred = false;

            if (is_string($entry)) {
                $label = (string) $key;
                $reference = trim($entry);
            } elseif (is_array($entry)) {
                $labelEntry = $entry['label'] ?? $entry['id'] ?? (string) $key;
                if (!is_string($labelEntry)) {
                    throw new \InvalidArgumentException('Expected TeX equation reference label');
                }
                $label = $labelEntry;

                $referenceEntry = $entry['reference'] ?? $entry['tag'] ?? $label;
                if (!is_string($referenceEntry)) {
                    throw new \InvalidArgumentException('Expected TeX equation reference text for ' . $label);
                }
                $reference = trim($referenceEntry);

                if (array_key_exists('tag', $entry)) {
                    if ($entry['tag'] !== null && !is_string($entry['tag'])) {
                        throw new \InvalidArgumentException('Expected TeX equation reference tag for ' . $label);
                    }
                    $tag = $entry['tag'];
                }
                $tagStarred = (bool) ($entry['tagStarred'] ?? false);
            } else {
                throw new \InvalidArgumentException('Expected TeX equation reference label map entry');
            }

            if ($reference === '') {
                throw new \InvalidArgumentException('Expected TeX equation reference text for ' . $label);
            }

            $id = $this->normalizeEquationLabelId($label);
            if (isset($normalized[$id])) {
                throw new \InvalidArgumentException('Duplicate TeX equation reference label ' . $label);
            }

            $normalized[$id] = [
                'label' => $label,
                'id' => $id,
                'reference' => $reference,
                'tag' => $tag,
                'tagStarred' => $tagStarred,
            ];
        }

        return $normalized;
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

    private function parseOptionalNonEmptyBracketArgument(string $source, int &$offset, string $label): ?string
    {
        $this->skipWhitespace($source, $offset);
        $start = $offset;
        $argument = $this->readTexBracketArgument($source, $offset);
        if ($argument === null) {
            return null;
        }

        if (trim($argument['value']) === '') {
            throw new \InvalidArgumentException('Expected TeX ' . $label . ' content at offset ' . $start);
        }

        $offset = $argument['next'];

        return $this->parseTexFragment($argument['value'], $label);
    }

    private function parseTexFragment(string $fragment, string $label): string
    {
        $offset = 0;
        $children = $this->parseExpression($fragment, $offset, null);
        if ($children === []) {
            throw new \InvalidArgumentException('Expected TeX ' . $label . ' content');
        }

        $this->skipWhitespace($fragment, $offset);
        if ($offset < strlen($fragment)) {
            throw new \InvalidArgumentException('Unsupported TeX token in ' . $label . ' at offset ' . $offset);
        }

        return $this->row($children);
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

    private function applyScripts(string $source, int &$offset, string $base, ?string $scriptPlacement = null): string
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

        if ($scriptPlacement !== null && $subscript === null && $superscript === null) {
            throw new \InvalidArgumentException('Expected TeX \\' . $scriptPlacement . ' subscript or superscript at offset ' . $offset);
        }

        if ($scriptPlacement === 'limits') {
            if ($subscript !== null && $superscript !== null) {
                return '<munderover>' . $base . $subscript . $superscript . '</munderover>';
            }

            if ($subscript !== null) {
                return '<munder>' . $base . $subscript . '</munder>';
            }

            if ($superscript !== null) {
                return '<mover>' . $base . $superscript . '</mover>';
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

    private function readScriptPlacementCommand(string $source, int &$offset): ?string
    {
        $this->skipWhitespace($source, $offset);
        if (($source[$offset] ?? '') !== '\\') {
            return null;
        }

        $commandOffset = $offset + 1;
        $command = $this->readCommandName($source, $commandOffset);
        if ($command !== 'limits' && $command !== 'nolimits') {
            return null;
        }

        $offset = $commandOffset;

        return $command;
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
