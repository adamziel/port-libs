<?php

declare(strict_types=1);

namespace PortLibs\Pandoc\PlainMath;

final class TexParser
{
    /** @var list<array{code:string,message:string,offset?:int}> */
    private array $diagnostics = [];

    public function parse(string $source): TexParseResult
    {
        $this->diagnostics = [];
        $stream = new TexTokenStream($source);
        [$expressions, $stream] = $this->parseRow($stream);
        $stream = $stream->skipWhitespace();
        if (!$stream->atEnd()) {
            $this->diagnostics[] = [
                'code' => 'unexpected-token',
                'message' => 'Unexpected TeX token after parsed expression row.',
                'offset' => $stream->offset(),
            ];
        }

        return new TexParseResult($source, $expressions, $this->diagnostics, $stream->offset());
    }

    /**
     * @param list<string> $terminators
     * @return array{0:list<Expression>,1:TexTokenStream}
     */
    private function parseRow(TexTokenStream $stream, array $terminators = []): array
    {
        $items = [];
        $stream = $stream->skipWhitespace();
        while (!$stream->atEnd() && !$this->matchesAnyTerminator($stream, $terminators)) {
            $choose = $this->readCommandIf($stream, 'choose');
            if ($choose !== null) {
                [$denominatorItems, $afterDenominator] = $this->parseRow($choose['stream'], $terminators);
                $numerator = $this->rowExpression($items);
                $denominator = $this->rowExpression($denominatorItems);

                return [[Expression::delimited(
                    '(',
                    Expression::fraction($numerator, $denominator, ['linethickness' => '0']),
                    ')'
                )], $afterDenominator];
            }

            [$atom, $stream] = $this->parseScriptedAtom($stream);
            if ($atom === null) {
                break;
            }
            $items[] = $atom;
            $stream = $stream->skipWhitespace();
        }

        return [$items, $stream];
    }

    /**
     * @return array{0:?Expression,1:TexTokenStream}
     */
    private function parseScriptedAtom(TexTokenStream $stream): array
    {
        [$base, $stream] = $this->parseAtom($stream);
        if ($base === null) {
            return [null, $stream];
        }

        $subscript = null;
        $superscript = null;
        while (($marker = $stream->peekByte()) === '_' || $marker === '^') {
            $stream = $stream->withOffset($stream->offset() + 1)->skipWhitespace();
            [$argument, $stream] = $this->parseScriptArgument($stream);
            if ($argument === null) {
                $this->diagnostics[] = [
                    'code' => 'missing-script-argument',
                    'message' => 'TeX script marker is missing its argument.',
                    'offset' => $stream->offset(),
                ];
                break;
            }

            if ($marker === '_') {
                $subscript = $argument;
            } else {
                $superscript = $argument;
            }
        }

        if ($subscript !== null && $superscript !== null) {
            return [Expression::subSup($base, $subscript, $superscript), $stream];
        }
        if ($subscript !== null) {
            return [Expression::sub($base, $subscript), $stream];
        }
        if ($superscript !== null) {
            return [Expression::super($base, $superscript), $stream];
        }

        return [$base, $stream];
    }

    /**
     * @return array{0:?Expression,1:TexTokenStream}
     */
    private function parseAtom(TexTokenStream $stream): array
    {
        $stream = $stream->skipWhitespace();
        if ($stream->atEnd()) {
            return [null, $stream];
        }

        if ($stream->peekByte() === '{') {
            return $this->parseGroupedExpression($stream);
        }

        if ($stream->peekByte() === '\\') {
            return $this->parseCommandAtom($stream);
        }

        $char = $stream->readUtf8Char();
        if ($char === null) {
            return [null, $stream];
        }

        $value = $char['char'];
        $next = $char['stream'];
        if ($this->isAsciiDigit($value)) {
            while (($peek = $next->peekByte()) !== null && $this->isAsciiDigit($peek)) {
                $read = $next->readUtf8Char();
                if ($read === null) {
                    break;
                }
                $value .= $read['char'];
                $next = $read['stream'];
            }

            return [Expression::number($value), $next];
        }

        if ($this->isIdentifierChar($value)) {
            return [Expression::identifier($value), $next];
        }

        return [Expression::operator($value), $next];
    }

    /**
     * @return array{0:?Expression,1:TexTokenStream}
     */
    private function parseCommandAtom(TexTokenStream $stream): array
    {
        $command = $stream->readCommand();
        if ($command === null) {
            return [null, $stream];
        }

        return match ($command['command']) {
            'frac' => $this->parseFractionCommand($command['stream'], $command['span']['start']),
            'sqrt' => $this->parseSqrtCommand($command['stream'], $command['span']['start']),
            'begin' => $this->parseBeginEnvironment($command['stream'], $command['span']['start']),
            default => [Expression::identifier($command['command']), $command['stream']],
        };
    }

    /**
     * @return array{0:?Expression,1:TexTokenStream}
     */
    private function parseFractionCommand(TexTokenStream $stream, int $offset): array
    {
        [$numerator, $stream] = $this->parseRequiredGroupedArgument($stream, 'fraction-numerator', $offset);
        [$denominator, $stream] = $this->parseRequiredGroupedArgument($stream, 'fraction-denominator', $offset);
        if ($numerator === null || $denominator === null) {
            return [null, $stream];
        }

        return [Expression::fraction($numerator, $denominator), $stream];
    }

    /**
     * @return array{0:?Expression,1:TexTokenStream}
     */
    private function parseSqrtCommand(TexTokenStream $stream, int $offset): array
    {
        [$body, $stream] = $this->parseRequiredGroupedArgument($stream, 'sqrt-body', $offset);
        if ($body === null) {
            return [null, $stream];
        }

        return [Expression::sqrt($body), $stream];
    }

    /**
     * @return array{0:?Expression,1:TexTokenStream}
     */
    private function parseBeginEnvironment(TexTokenStream $stream, int $offset): array
    {
        $stream = $stream->skipWhitespace();
        $name = $stream->readRawGroup();
        if ($name === null) {
            $this->diagnostics[] = [
                'code' => 'missing-environment-name',
                'message' => 'TeX begin command is missing an environment name.',
                'offset' => $offset,
            ];

            return [null, $stream];
        }

        $environment = trim($name['value']);
        if ($environment !== 'pmatrix') {
            $this->diagnostics[] = [
                'code' => 'unsupported-environment',
                'message' => 'Unsupported TeX environment in core reader chunk: ' . $environment,
                'offset' => $offset,
            ];

            return [null, $name['stream']];
        }

        $bodyStart = $name['stream']->offset();
        $endNeedle = '\\end{' . $environment . '}';
        $endOffset = strpos($name['stream']->source(), $endNeedle, $bodyStart);
        if ($endOffset === false) {
            $this->diagnostics[] = [
                'code' => 'unclosed-environment',
                'message' => 'TeX environment is missing its end command.',
                'offset' => $offset,
            ];

            return [null, $name['stream']];
        }

        $body = $name['stream']->slice($bodyStart, $endOffset);
        $rows = [];
        foreach ($this->splitEnvironmentRows($body) as $row) {
            $cells = [];
            foreach ($this->splitEnvironmentCells($row) as $cell) {
                $cellResult = (new self())->parse($cell);
                if (!$cellResult->ok() || $cellResult->expression() === null) {
                    $this->diagnostics[] = [
                        'code' => 'invalid-environment-cell',
                        'message' => 'Unable to parse TeX environment cell.',
                        'offset' => $bodyStart,
                    ];
                    continue;
                }
                $cells[] = $cellResult->expression();
            }
            if ($cells !== []) {
                $rows[] = $cells;
            }
        }

        return [Expression::delimited('(', Expression::table($rows), ')'), $name['stream']->withOffset($endOffset + strlen($endNeedle))];
    }

    /**
     * @return array{0:?Expression,1:TexTokenStream}
     */
    private function parseGroupedExpression(TexTokenStream $stream): array
    {
        $group = $stream->readRawGroup();
        if ($group === null) {
            $this->diagnostics[] = [
                'code' => 'unclosed-group',
                'message' => 'TeX group is missing a closing brace.',
                'offset' => $stream->offset(),
            ];

            return [null, $stream];
        }

        $result = (new self())->parse($group['value']);
        if (!$result->ok() || $result->expression() === null) {
            $this->diagnostics = [...$this->diagnostics, ...$result->diagnostics];

            return [null, $group['stream']];
        }

        return [$result->expression(), $group['stream']];
    }

    /**
     * @return array{0:?Expression,1:TexTokenStream}
     */
    private function parseRequiredGroupedArgument(TexTokenStream $stream, string $code, int $commandOffset): array
    {
        $stream = $stream->skipWhitespace();
        if ($stream->peekByte() !== '{') {
            $this->diagnostics[] = [
                'code' => 'missing-' . $code,
                'message' => 'TeX command is missing a required grouped argument.',
                'offset' => $commandOffset,
            ];

            return [null, $stream];
        }

        return $this->parseGroupedExpression($stream);
    }

    /**
     * @return array{0:?Expression,1:TexTokenStream}
     */
    private function parseScriptArgument(TexTokenStream $stream): array
    {
        if ($stream->peekByte() === '{') {
            return $this->parseGroupedExpression($stream);
        }

        return $this->parseAtom($stream);
    }

    /**
     * @param list<Expression> $items
     */
    private function rowExpression(array $items): Expression
    {
        return count($items) === 1 ? $items[0] : Expression::row($items);
    }

    /**
     * @param list<string> $terminators
     */
    private function matchesAnyTerminator(TexTokenStream $stream, array $terminators): bool
    {
        foreach ($terminators as $terminator) {
            if (str_starts_with(substr($stream->source(), $stream->offset()), $terminator)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{command:string,span:array{start:int,end:int,text:string},stream:TexTokenStream}|null
     */
    private function readCommandIf(TexTokenStream $stream, string $expected): ?array
    {
        if ($stream->peekByte() !== '\\') {
            return null;
        }

        $command = $stream->readCommand();
        if ($command === null || $command['command'] !== $expected) {
            return null;
        }

        return $command;
    }

    /**
     * @return list<string>
     */
    private function splitEnvironmentRows(string $body): array
    {
        return $this->splitTopLevel($body, '\\\\');
    }

    /**
     * @return list<string>
     */
    private function splitEnvironmentCells(string $row): array
    {
        return $this->splitTopLevel($row, '&');
    }

    /**
     * @return list<string>
     */
    private function splitTopLevel(string $source, string $separator): array
    {
        $items = [];
        $start = 0;
        $cursor = 0;
        $length = strlen($source);
        $separatorLength = strlen($separator);
        $braceDepth = 0;

        while ($cursor < $length) {
            $char = $source[$cursor];
            if ($braceDepth === 0 && substr($source, $cursor, $separatorLength) === $separator) {
                $items[] = trim(substr($source, $start, $cursor - $start));
                $cursor += $separatorLength;
                $start = $cursor;
                continue;
            }
            if ($char === '\\') {
                $cursor += 2;
                continue;
            }
            if ($char === '{') {
                $braceDepth++;
                $cursor++;
                continue;
            }
            if ($char === '}') {
                $braceDepth = max(0, $braceDepth - 1);
                $cursor++;
                continue;
            }
            $cursor++;
        }

        $items[] = trim(substr($source, $start));

        return array_values(array_filter($items, static fn (string $item): bool => $item !== ''));
    }

    private function isAsciiDigit(string $char): bool
    {
        return $char >= '0' && $char <= '9';
    }

    private function isIdentifierChar(string $char): bool
    {
        return preg_match('/\A[\p{L}]\z/u', $char) === 1;
    }
}
