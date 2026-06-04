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
        'theta' => 'θ',
        'lambda' => 'λ',
        'mu' => 'μ',
        'pi' => 'π',
        'sigma' => 'σ',
        'omega' => 'ω',
    ];

    /** @var array<string, string> */
    private const OPERATOR_COMMANDS = [
        'cdot' => '⋅',
        'geq' => '≥',
        'in' => '∈',
        'leq' => '≤',
        'lim' => 'lim',
        'neq' => '≠',
        'pm' => '±',
        'times' => '×',
        'to' => '→',
        'wedge' => '∧',
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

    public function latexFor(AstNode $node): string
    {
        $text = (string) $node->attr('text', '');

        if ($node->attr('display') === true) {
            return '\\[' . $text . '\\]';
        }

        return '$' . $text . '$';
    }

    public function mathMlFor(AstNode $node): string
    {
        return $this->texToMathMl((string) $node->attr('text', ''), $node->attr('display') === true);
    }

    public function texToMathMl(string $tex, bool $display = false): string
    {
        $offset = 0;
        $children = $this->parseExpression($tex, $offset, null);
        $this->skipWhitespace($tex, $offset);

        if ($offset < strlen($tex)) {
            throw new \InvalidArgumentException('Unsupported TeX token at offset ' . $offset);
        }

        $displayMode = $display ? 'block' : 'inline';

        return '<math xmlns="http://www.w3.org/1998/Math/MathML" display="' . $displayMode . '">'
            . implode('', $children)
            . '</math>';
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
            return '<msqrt>' . $this->parseRequiredGroup($source, $offset) . '</msqrt>';
        }

        if ($command === 'text') {
            return '<mtext>' . $this->esc($this->readRequiredGroupText($source, $offset)) . '</mtext>';
        }

        if ($command === 'left' || $command === 'right') {
            return $this->parseFenceCommand($source, $offset);
        }

        if (isset(self::IDENTIFIER_COMMANDS[$command])) {
            return '<mi>' . self::IDENTIFIER_COMMANDS[$command] . '</mi>';
        }

        if (isset(self::OPERATOR_COMMANDS[$command])) {
            return '<mo>' . self::OPERATOR_COMMANDS[$command] . '</mo>';
        }

        if (isset(self::DELIMITER_COMMANDS[$command])) {
            return '<mo>' . $this->esc(self::DELIMITER_COMMANDS[$command]) . '</mo>';
        }

        return '<mi>' . $this->esc('\\' . $command) . '</mi>';
    }

    private function parseFenceCommand(string $source, int &$offset): string
    {
        $delimiter = $this->readFenceDelimiter($source, $offset);
        if ($delimiter === '') {
            return '';
        }

        return '<mo fence="true" stretchy="true">' . $this->esc($delimiter) . '</mo>';
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
