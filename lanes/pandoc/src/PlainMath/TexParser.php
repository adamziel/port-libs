<?php

declare(strict_types=1);

namespace PortLibs\Pandoc\PlainMath;

final class TexParser
{
    /** @var list<array{code:string,message:string,offset?:int}> */
    private array $diagnostics = [];

    public function __construct(
        private readonly TexPreprocessor $preprocessor = new TexPreprocessor(),
    ) {
    }

    public function parse(string $source): TexParseResult
    {
        $this->diagnostics = [];
        $parseSource = $this->preprocessor->preprocess($source);
        if ($parseSource === null) {
            return new TexParseResult($source, [], [[
                'code' => 'preprocessor-expansion-failed',
                'message' => 'TeX macro or environment expansion did not converge.',
                'offset' => 0,
            ]], 0);
        }

        $stream = new TexTokenStream($parseSource);
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
            $infix = $this->readInfixFractionCommand($stream);
            if ($infix !== null && $items !== []) {
                [$denominatorItems, $afterDenominator] = $this->parseRow($infix['stream'], $terminators);
                $numerator = $this->rowExpression($items);
                $denominator = $this->rowExpression($denominatorItems);

                return [[$this->infixFractionExpression($infix['command'], $numerator, $denominator)], $afterDenominator];
            }

            [$atom, $stream] = $this->parseScriptedAtom($stream);
            if ($atom === null) {
                break;
            }
            if ($atom->kind === 'row' && ($atom->attributes['plainmathTransparent'] ?? false) === true) {
                array_push($items, ...$atom->children);
            } else {
                $items[] = $atom;
            }
            $stream = $stream->skipWhitespace();
            if ($this->isFunctionOperatorExpression($atom) && $this->nextAtomCanBeFunctionArgument($stream)) {
                $items[] = Expression::operator("\u{2061}");
            }
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

        [$limitModifier, $stream] = $this->consumeLimitModifier($stream);
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

        if ($stream->peekByte() === "'") {
            $prime = '';
            while ($stream->peekByte() === "'") {
                $prime .= "'";
                $stream = $stream->withOffset($stream->offset() + 1);
            }
            $superscript = Expression::operator($this->primeSymbol(strlen($prime)));
        }

        $useUnderOverLimits = $limitModifier !== 'nolimits'
            && ($limitModifier === 'limits' || $this->hasDefaultLimits($base));
        if ($useUnderOverLimits && $subscript !== null && $superscript !== null) {
            return [Expression::underOver($base, $subscript, $superscript), $stream];
        }
        if ($useUnderOverLimits && $subscript !== null) {
            return [Expression::under($base, $subscript), $stream];
        }
        if ($useUnderOverLimits && $superscript !== null) {
            return [Expression::over($base, $superscript), $stream];
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
            'frac', 'dfrac', 'tfrac' => $this->parseFractionCommand($command['stream'], $command['span']['start'], $command['command']),
            'binom', 'dbinom', 'tbinom' => $this->parseBinomialCommand($command['stream'], $command['span']['start'], $command['command']),
            'sqrt' => $this->parseSqrtCommand($command['stream'], $command['span']['start']),
            'surd' => $this->parseSqrtCommand($command['stream'], $command['span']['start']),
            'boxed', 'fbox' => $this->parseEnclosureCommand($command['stream'], $command['span']['start'], 'box'),
            'text', 'mbox', 'hbox' => $this->parseTextCommand($command['stream'], $command['span']['start']),
            'operatorname' => $this->parseOperatorNameCommand($command['stream'], $command['span']['start']),
            'mathop', 'mathrel', 'mathbin', 'mathord', 'mathopen', 'mathclose', 'mathpunct' => $this->parseAtomCoercionCommand($command['command'], $command['stream'], $command['span']['start']),
            'mathrm', 'mathup', 'mathbf', 'mathit', 'mathsf', 'mathtt' => $this->parseStyleCommand($command['command'], $command['stream'], $command['span']['start']),
            'displaystyle', 'textstyle', 'scriptstyle', 'scriptscriptstyle' => $this->parseStyleDeclarationCommand($command['command'], $command['stream'], $command['span']['start']),
            'stackrel', 'overset' => $this->parseOversetCommand($command['stream'], $command['span']['start']),
            'substack' => $this->parseSubstackCommand($command['stream'], $command['span']['start']),
            'left' => $this->parseLeftRightCommand($command['stream'], $command['span']['start']),
            'middle' => $this->parseMiddleCommand($command['stream'], $command['span']['start']),
            'begin' => $this->parseBeginEnvironment($command['stream'], $command['span']['start']),
            default => $this->parseMappedCommandAtom($command['command'], $command['stream'], $command['span']['start']),
        };
    }

    /**
     * @return array{0:?Expression,1:TexTokenStream}
     */
    private function parseFractionCommand(TexTokenStream $stream, int $offset, string $command = 'frac'): array
    {
        [$numerator, $stream] = $this->parseRequiredGroupedArgument($stream, 'fraction-numerator', $offset);
        [$denominator, $stream] = $this->parseRequiredGroupedArgument($stream, 'fraction-denominator', $offset);
        if ($numerator === null || $denominator === null) {
            return [null, $stream];
        }

        $fraction = Expression::fraction($numerator, $denominator);
        if ($command === 'dfrac') {
            return [Expression::style($fraction, ['displaystyle' => 'true']), $stream];
        }
        if ($command === 'tfrac') {
            return [Expression::style($fraction, ['displaystyle' => 'false']), $stream];
        }

        return [$fraction, $stream];
    }

    /**
     * @return array{0:?Expression,1:TexTokenStream}
     */
    private function parseBinomialCommand(TexTokenStream $stream, int $offset, string $command): array
    {
        [$numerator, $stream] = $this->parseRequiredGroupedArgument($stream, 'binomial-numerator', $offset);
        [$denominator, $stream] = $this->parseRequiredGroupedArgument($stream, 'binomial-denominator', $offset);
        if ($numerator === null || $denominator === null) {
            return [null, $stream];
        }

        $binomial = Expression::delimited(
            '(',
            Expression::fraction($numerator, $denominator, ['linethickness' => '0']),
            ')'
        );
        if ($command === 'dbinom') {
            return [Expression::style($binomial, ['displaystyle' => 'true']), $stream];
        }
        if ($command === 'tbinom') {
            return [Expression::style($binomial, ['displaystyle' => 'false']), $stream];
        }

        return [$binomial, $stream];
    }

    /**
     * @return array{0:?Expression,1:TexTokenStream}
     */
    private function parseSqrtCommand(TexTokenStream $stream, int $offset): array
    {
        [$rootIndex, $stream] = $this->parseOptionalBracketExpression($stream);
        [$body, $stream] = $this->parseRequiredGroupedArgument($stream, 'sqrt-body', $offset);
        if ($body === null) {
            return [null, $stream];
        }

        return [$rootIndex === null ? Expression::sqrt($body) : Expression::root($body, $rootIndex), $stream];
    }

    /**
     * @return array{0:?Expression,1:TexTokenStream}
     */
    private function parseEnclosureCommand(TexTokenStream $stream, int $offset, string $notation): array
    {
        [$body, $stream] = $this->parseRequiredGroupedArgument($stream, 'enclosure-body', $offset);
        if ($body === null) {
            return [null, $stream];
        }

        return [Expression::enclosed($body, $notation), $stream];
    }

    /**
     * @return array{0:?Expression,1:TexTokenStream}
     */
    private function parseTextCommand(TexTokenStream $stream, int $offset): array
    {
        $group = $stream->skipWhitespace()->readRawGroup();
        if ($group === null) {
            $this->diagnostics[] = [
                'code' => 'missing-text-body',
                'message' => 'TeX text command is missing a grouped body.',
                'offset' => $offset,
            ];

            return [null, $stream];
        }

        $body = $this->textModeExpression($group['value']);
        if ($body->kind === 'row') {
            $body = Expression::row($body->children, ['plainmathTransparent' => true]);
        }

        return [$body, $group['stream']];
    }

    /**
     * @return array{0:?Expression,1:TexTokenStream}
     */
    private function parseOperatorNameCommand(TexTokenStream $stream, int $offset): array
    {
        $stream = $stream->skipWhitespace();
        $starred = false;
        if ($stream->peekByte() === '*') {
            $starred = true;
            $stream = $stream->withOffset($stream->offset() + 1);
        }

        $group = $stream->skipWhitespace()->readRawGroup();
        if ($group === null) {
            $this->diagnostics[] = [
                'code' => 'missing-operator-name',
                'message' => 'TeX operatorname command is missing a grouped body.',
                'offset' => $offset,
            ];

            return [null, $stream];
        }

        return [Expression::mathOperator(
            $this->operatorNameText($group['value']),
            $starred ? ['plainmathDefaultLimits' => true] : []
        ), $group['stream']];
    }

    /**
     * @return array{0:?Expression,1:TexTokenStream}
     */
    private function parseAtomCoercionCommand(string $command, TexTokenStream $stream, int $offset): array
    {
        [$body, $stream] = $this->parseRequiredGroupedArgument($stream, 'atom-coercion-body', $offset);
        if ($body === null) {
            return [null, $stream];
        }

        $text = $this->expressionText($body);
        $coerced = match ($command) {
            'mathop' => Expression::mathOperator($text, ['plainmathNoApplyFunction' => true]),
            'mathord' => Expression::identifier($text),
            'mathopen' => Expression::operator($text, ['form' => 'prefix', 'stretchy' => 'false']),
            'mathclose' => Expression::operator($text, ['form' => 'postfix', 'stretchy' => 'false']),
            default => Expression::operator($text),
        };

        return [$coerced, $stream];
    }

    /**
     * @return array{0:?Expression,1:TexTokenStream}
     */
    private function parseStyleCommand(string $command, TexTokenStream $stream, int $offset): array
    {
        [$body, $stream] = $this->parseRequiredGroupedArgument($stream, 'style-body', $offset);
        if ($body === null) {
            return [null, $stream];
        }

        return [Expression::style($body, ['mathvariant' => $this->mathVariantForCommand($command)]), $stream];
    }

    /**
     * @return array{0:?Expression,1:TexTokenStream}
     */
    private function parseStyleDeclarationCommand(string $command, TexTokenStream $stream, int $offset): array
    {
        [$body, $stream] = $this->parseRequiredGroupedArgument($stream, 'style-declaration-body', $offset);
        if ($body === null) {
            return [null, $stream];
        }

        return [Expression::style($body, $this->styleDeclarationAttributes($command)), $stream];
    }

    /**
     * @return array{0:?Expression,1:TexTokenStream}
     */
    private function parseOversetCommand(TexTokenStream $stream, int $offset): array
    {
        [$above, $stream] = $this->parseRequiredGroupedArgument($stream, 'overset-above', $offset);
        [$base, $stream] = $this->parseRequiredGroupedArgument($stream, 'overset-base', $offset);
        if ($above === null || $base === null) {
            return [null, $stream];
        }

        return [Expression::over($base, $above), $stream];
    }

    /**
     * @return array{0:?Expression,1:TexTokenStream}
     */
    private function parseSubstackCommand(TexTokenStream $stream, int $offset): array
    {
        $group = $stream->skipWhitespace()->readRawGroup();
        if ($group === null) {
            $this->diagnostics[] = [
                'code' => 'missing-substack-body',
                'message' => 'TeX substack command is missing a grouped body.',
                'offset' => $offset,
            ];

            return [null, $stream];
        }

        return [$this->tableExpressionFromBody($group['value'], null, null, 'center'), $group['stream']];
    }

    /**
     * @return array{0:?Expression,1:TexTokenStream}
     */
    private function parseLeftRightCommand(TexTokenStream $stream, int $offset): array
    {
        $left = $this->readDelimiter($stream);
        if ($left === null) {
            $this->diagnostics[] = [
                'code' => 'missing-left-delimiter',
                'message' => 'TeX left command is missing a delimiter.',
                'offset' => $offset,
            ];

            return [null, $stream];
        }

        $body = $this->readUntilMatchingRight($left['stream']);
        if ($body === null) {
            $this->diagnostics[] = [
                'code' => 'missing-right-delimiter',
                'message' => 'TeX left command is missing a matching right command.',
                'offset' => $offset,
            ];

            return [null, $left['stream']];
        }

        $result = (new self())->parse($body['body']);
        if (!$result->ok() || $result->expression() === null) {
            $this->diagnostics = [...$this->diagnostics, ...$result->diagnostics];

            return [null, $body['stream']];
        }

        return [Expression::delimited($left['delimiter'], $result->expression(), $body['right']), $body['stream']];
    }

    /**
     * @return array{0:?Expression,1:TexTokenStream}
     */
    private function parseMiddleCommand(TexTokenStream $stream, int $offset): array
    {
        $delimiter = $this->readDelimiter($stream);
        if ($delimiter === null) {
            $this->diagnostics[] = [
                'code' => 'missing-middle-delimiter',
                'message' => 'TeX middle command is missing a delimiter.',
                'offset' => $offset,
            ];

            return [null, $stream];
        }

        return [Expression::operator($delimiter['delimiter'] ?? '', ['stretchy' => 'true']), $delimiter['stream']];
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
        $normalizedEnvironment = $this->normalizedEnvironmentName($environment);
        if ($normalizedEnvironment === 'equation') {
            $bodyOffset = $name['stream']->offset();
            $body = $this->readEnvironmentBody($name['stream'], $environment);
            if ($body === null) {
                $this->diagnostics[] = [
                    'code' => 'unclosed-environment',
                    'message' => 'TeX environment is missing its end command.',
                    'offset' => $offset,
                ];

                return [null, $name['stream']];
            }

            $result = (new self())->parse($body['body']);
            if (!$result->ok() || $result->expression() === null) {
                $this->diagnostics = [...$this->diagnostics, ...$result->diagnostics];

                return [null, $body['stream']];
            }

            return [$result->expression(), $body['stream']];
        }

        $columnAlign = '';
        $bodyStream = $name['stream'];
        if ($normalizedEnvironment === 'array' || $normalizedEnvironment === 'subarray') {
            $columnSpec = $bodyStream->skipWhitespace()->readRawGroup();
            if ($columnSpec === null) {
                $this->diagnostics[] = [
                    'code' => 'missing-array-column-spec',
                    'message' => 'TeX array environment is missing its column specification.',
                    'offset' => $offset,
                ];

                return [null, $bodyStream];
            }
            $columnAlign = $this->arrayColumnAlign($columnSpec['value']);
            $bodyStream = $columnSpec['stream'];
        } elseif ($this->environmentConsumesLeadingGroup($normalizedEnvironment)) {
            $pairCount = $bodyStream->skipWhitespace()->readRawGroup();
            if ($pairCount === null) {
                $this->diagnostics[] = [
                    'code' => 'missing-environment-argument',
                    'message' => 'TeX alignment environment is missing its leading argument.',
                    'offset' => $offset,
                ];

                return [null, $bodyStream];
            }
            $bodyStream = $pairCount['stream'];
        }

        $fences = $this->environmentFences($normalizedEnvironment);
        if ($fences === null && $normalizedEnvironment !== 'array' && $normalizedEnvironment !== 'subarray') {
            $this->diagnostics[] = [
                'code' => 'unsupported-environment',
                'message' => 'Unsupported TeX environment in typed PlainMath reader: ' . $environment,
                'offset' => $offset,
            ];

            return [null, $name['stream']];
        }

        $body = $this->readEnvironmentBody($bodyStream, $environment);
        if ($body === null) {
            $this->diagnostics[] = [
                'code' => 'unclosed-environment',
                'message' => 'TeX environment is missing its end command.',
                'offset' => $offset,
            ];

            return [null, $bodyStream];
        }

        $columnAlign = $columnAlign !== '' ? $columnAlign : $this->environmentColumnAlign($normalizedEnvironment);

        return [
            $this->tableExpressionFromBody($body['body'], $fences[0] ?? null, $fences[1] ?? null, $columnAlign),
            $body['stream'],
        ];
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
     * @return array{0:?Expression,1:TexTokenStream}
     */
    private function parseOptionalBracketExpression(TexTokenStream $stream): array
    {
        $stream = $stream->skipWhitespace();
        $bracket = $stream->readOptionalBracket();
        if ($bracket === null) {
            return [null, $stream];
        }

        $result = (new self())->parse($bracket['value']);
        if (!$result->ok() || $result->expression() === null) {
            $this->diagnostics = [...$this->diagnostics, ...$result->diagnostics];

            return [null, $bracket['stream']];
        }

        return [$result->expression(), $bracket['stream']];
    }

    /**
     * @return array{0:?Expression,1:TexTokenStream}
     */
    private function parseMappedCommandAtom(string $command, TexTokenStream $stream, int $offset): array
    {
        if (in_array($command, ['limits', 'nolimits', 'displaylimits', 'notag', 'nonumber', 'allowbreak'], true)) {
            return [Expression::row([]), $stream];
        }

        if ($command === 'label') {
            $group = $stream->skipWhitespace()->readRawGroup();
            return [Expression::row([]), $group['stream'] ?? $stream];
        }

        if ($command === 'tag') {
            $tagStream = $stream->skipWhitespace();
            if ($tagStream->peekByte() === '*') {
                $tagStream = $tagStream->withOffset($tagStream->offset() + 1);
            }
            $group = $tagStream->skipWhitespace()->readRawGroup();
            return [Expression::row([]), $group['stream'] ?? $stream];
        }

        $spacing = $this->spacingCommandExpression($command);
        if ($spacing !== null) {
            return [$spacing, $stream];
        }

        $dimensionedSpacing = $this->parseDimensionedSpacingCommand($command, $stream);
        if ($dimensionedSpacing !== null) {
            return $dimensionedSpacing;
        }

        $namedOperator = $this->namedOperatorExpression($command);
        if ($namedOperator !== null) {
            return [$namedOperator, $stream];
        }

        $symbol = $this->symbolExpression($command);
        if ($symbol !== null) {
            return [$symbol, $stream];
        }

        return [Expression::identifier($command), $stream];
    }

    /**
     * @return array{0:Expression,1:TexTokenStream}|null
     */
    private function parseDimensionedSpacingCommand(string $command, TexTokenStream $stream): ?array
    {
        if (!in_array($command, ['hspace', 'mspace', 'kern', 'mkern'], true)) {
            return null;
        }

        $stream = $stream->skipWhitespace();
        $attributes = [];
        if ($command === 'hspace' && $stream->peekByte() === '*') {
            $attributes['linebreak'] = 'nobreak';
            $stream = $stream->withOffset($stream->offset() + 1)->skipWhitespace();
        }

        $dimension = null;
        $group = $stream->readRawGroup();
        if ($group !== null) {
            $dimension = $this->normalizeSpacingDimension($group['value']);
            $stream = $group['stream'];
        } elseif ($command === 'kern' || $command === 'mkern') {
            $read = $this->readDimensionToken($stream);
            if ($read !== null) {
                $dimension = $this->normalizeSpacingDimension($read['dimension']);
                $stream = $read['stream'];
            }
        }

        if ($dimension === null) {
            return null;
        }

        return [Expression::space(['width' => $dimension] + $attributes), $stream];
    }

    private function spacingCommandExpression(string $command): ?Expression
    {
        $width = [
            ' ' => '0.222em',
            ',' => '0.167em',
            'thinspace' => '0.167em',
            ':' => '0.222em',
            'medspace' => '0.222em',
            ';' => '0.278em',
            'thickspace' => '0.278em',
            '!' => '0em',
            'negthinspace' => '0em',
            'negmedspace' => '0em',
            'negthickspace' => '0em',
            'enspace' => '0.5em',
            'quad' => '1em',
            'qquad' => '2em',
        ][$command] ?? null;

        return $width === null ? null : Expression::space(['width' => $width]);
    }

    private function namedOperatorExpression(string $command): ?Expression
    {
        static $operators = [
            'arccos',
            'arcsin',
            'arctan',
            'arg',
            'cos',
            'cosh',
            'cot',
            'coth',
            'csc',
            'deg',
            'det',
            'dim',
            'exp',
            'gcd',
            'hom',
            'inf',
            'ker',
            'lg',
            'lim',
            'liminf',
            'limsup',
            'ln',
            'log',
            'max',
            'min',
            'Pr',
            'sec',
            'sin',
            'sinh',
            'sup',
            'tan',
            'tanh',
        ];

        return in_array($command, $operators, true) ? Expression::mathOperator($command) : null;
    }

    private function symbolExpression(string $command): ?Expression
    {
        $identifiers = [
            'alpha' => 'α',
            'beta' => 'β',
            'gamma' => 'γ',
            'delta' => 'δ',
            'epsilon' => 'ϵ',
            'varepsilon' => 'ε',
            'theta' => 'θ',
            'lambda' => 'λ',
            'mu' => 'μ',
            'pi' => 'π',
            'sigma' => 'σ',
            'phi' => 'ϕ',
            'varphi' => 'φ',
            'omega' => 'ω',
            'Gamma' => 'Γ',
            'Delta' => 'Δ',
            'Theta' => 'Θ',
            'Lambda' => 'Λ',
            'Pi' => 'Π',
            'Sigma' => 'Σ',
            'Phi' => 'Φ',
            'Omega' => 'Ω',
        ];
        if (isset($identifiers[$command])) {
            return Expression::identifier($identifiers[$command]);
        }

        $operators = [
            'sum' => '∑',
            'prod' => '∏',
            'int' => '∫',
            'oint' => '∮',
            'infty' => '∞',
            'pm' => '±',
            'mp' => '∓',
            'times' => '×',
            'cdot' => '⋅',
            'le' => '≤',
            'leq' => '≤',
            'ge' => '≥',
            'geq' => '≥',
            'neq' => '≠',
            'ne' => '≠',
            'equiv' => '≡',
            'approx' => '≈',
            'rightarrow' => '→',
            'to' => '→',
            'leftarrow' => '←',
            'leftrightarrow' => '↔',
            'partial' => '∂',
            'lbrace' => '{',
            'rbrace' => '}',
            '{' => '{',
            '}' => '}',
        ];

        return isset($operators[$command]) ? Expression::operator($operators[$command]) : null;
    }

    /**
     * @return array{command:string,stream:TexTokenStream}|null
     */
    private function readInfixFractionCommand(TexTokenStream $stream): ?array
    {
        if ($stream->peekByte() !== '\\') {
            return null;
        }

        $command = $stream->readCommand();
        if ($command === null || !in_array($command['command'], ['over', 'atop', 'choose', 'brack', 'brace', 'bangle'], true)) {
            return null;
        }

        return [
            'command' => $command['command'],
            'stream' => $command['stream'],
        ];
    }

    private function infixFractionExpression(string $command, Expression $numerator, Expression $denominator): Expression
    {
        $fraction = Expression::fraction(
            $numerator,
            $denominator,
            $command === 'over' ? [] : ['linethickness' => '0']
        );

        return match ($command) {
            'choose' => Expression::delimited('(', $fraction, ')'),
            'brack' => Expression::delimited('[', $fraction, ']'),
            'brace' => Expression::delimited('{', $fraction, '}'),
            'bangle' => Expression::delimited('⟨', $fraction, '⟩'),
            default => $fraction,
        };
    }

    private function isFunctionOperatorExpression(Expression $expression): bool
    {
        if (($expression->attributes['plainmathNoApplyFunction'] ?? false) === true) {
            return false;
        }
        if ($expression->kind === 'mathOperator') {
            return true;
        }

        return in_array($expression->kind, ['sub', 'super', 'subsup', 'under', 'over', 'underover'], true)
            && isset($expression->children[0])
            && $this->isFunctionOperatorExpression($expression->children[0]);
    }

    private function nextAtomCanBeFunctionArgument(TexTokenStream $stream): bool
    {
        $stream = $stream->skipWhitespace();
        $char = $stream->peekByte();
        if ($char === null || str_contains('^_+-=*/<>,;:!|)]}&', $char)) {
            return false;
        }
        if ($char !== '\\') {
            return true;
        }

        $command = $stream->readCommand();
        if ($command === null) {
            return false;
        }

        if ($this->spacingCommandExpression($command['command']) !== null) {
            return $this->nextAtomCanBeFunctionArgument($command['stream']);
        }

        return !in_array($command['command'], [
            '\\',
            'right',
            'middle',
            'end',
            'limits',
            'nolimits',
            'displaylimits',
            'over',
            'atop',
            'choose',
            'brack',
            'brace',
            'bangle',
        ], true);
    }

    /**
     * @return array{0:?string,1:TexTokenStream}
     */
    private function consumeLimitModifier(TexTokenStream $stream): array
    {
        $stream = $stream->skipWhitespace();
        if ($stream->peekByte() !== '\\') {
            return [null, $stream];
        }

        $command = $stream->readCommand();
        if ($command === null || !in_array($command['command'], ['limits', 'nolimits', 'displaylimits'], true)) {
            return [null, $stream];
        }

        return [$command['command'], $command['stream']->skipWhitespace()];
    }

    private function hasDefaultLimits(Expression $expression): bool
    {
        if (($expression->attributes['plainmathDefaultLimits'] ?? false) === true) {
            return true;
        }

        return in_array($expression->kind, ['sub', 'super', 'subsup'], true)
            && isset($expression->children[0])
            && $this->hasDefaultLimits($expression->children[0]);
    }

    /**
     * @param list<Expression> $items
     */
    private function rowExpression(array $items): Expression
    {
        $items = array_values(array_filter(
            $items,
            static fn (Expression $item): bool => $item->kind !== 'row' || $item->children !== []
        ));

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
     * @return array{delimiter:?string,stream:TexTokenStream}|null
     */
    private function readDelimiter(TexTokenStream $stream): ?array
    {
        $stream = $stream->skipWhitespace();
        if ($stream->atEnd()) {
            return null;
        }

        if ($stream->peekByte() !== '\\') {
            $read = $stream->readUtf8Char();
            if ($read === null) {
                return null;
            }

            return [
                'delimiter' => match ($read['char']) {
                    '.' => null,
                    '<' => '⟨',
                    '>' => '⟩',
                    default => $read['char'],
                },
                'stream' => $read['stream'],
            ];
        }

        $command = $stream->readCommand();
        if ($command === null) {
            return null;
        }

        return [
            'delimiter' => match ($command['command']) {
                '.', '' => null,
                '{', 'lbrace' => '{',
                '}', 'rbrace' => '}',
                'lparen' => '(',
                'rparen' => ')',
                'lbrack' => '[',
                'rbrack' => ']',
                'langle' => '⟨',
                'rangle' => '⟩',
                'lfloor' => '⌊',
                'rfloor' => '⌋',
                'lceil' => '⌈',
                'rceil' => '⌉',
                'vert', 'lvert', 'rvert', '|' => '|',
                'Vert', 'lVert', 'rVert' => '‖',
                'backslash' => '\\',
                default => $command['command'],
            },
            'stream' => $command['stream'],
        ];
    }

    /**
     * @return array{body:string,right:?string,stream:TexTokenStream}|null
     */
    private function readUntilMatchingRight(TexTokenStream $stream): ?array
    {
        $source = $stream->source();
        $start = $stream->offset();
        $cursor = $start;
        $depth = 1;
        $length = strlen($source);

        while ($cursor < $length) {
            if ($source[$cursor] !== '\\') {
                $cursor++;
                continue;
            }

            $command = (new TexTokenStream($source, $cursor))->readCommand();
            if ($command === null) {
                $cursor++;
                continue;
            }

            if ($command['command'] === 'left') {
                $depth++;
                $cursor = $command['stream']->offset();
                continue;
            }

            if ($command['command'] === 'right') {
                $depth--;
                if ($depth === 0) {
                    $delimiter = $this->readDelimiter($command['stream']);
                    if ($delimiter === null) {
                        return null;
                    }

                    return [
                        'body' => substr($source, $start, $cursor - $start),
                        'right' => $delimiter['delimiter'],
                        'stream' => $delimiter['stream'],
                    ];
                }
            }

            $cursor = $command['stream']->offset();
        }

        return null;
    }

    /**
     * @return array{body:string,stream:TexTokenStream}|null
     */
    private function readEnvironmentBody(TexTokenStream $stream, string $environment): ?array
    {
        $source = $stream->source();
        $start = $stream->offset();
        $cursor = $start;
        $depth = 1;
        $length = strlen($source);

        while ($cursor < $length) {
            if ($source[$cursor] !== '\\') {
                $cursor++;
                continue;
            }

            $command = (new TexTokenStream($source, $cursor))->readCommand();
            if ($command === null || ($command['command'] !== 'begin' && $command['command'] !== 'end')) {
                $cursor++;
                continue;
            }

            $name = $command['stream']->skipWhitespace()->readRawGroup();
            if ($name === null || trim($name['value']) !== $environment) {
                $cursor = $command['stream']->offset();
                continue;
            }

            if ($command['command'] === 'begin') {
                $depth++;
                $cursor = $name['stream']->offset();
                continue;
            }

            $depth--;
            if ($depth === 0) {
                return [
                    'body' => substr($source, $start, $cursor - $start),
                    'stream' => $name['stream'],
                ];
            }

            $cursor = $name['stream']->offset();
        }

        return null;
    }

    private function tableExpressionFromBody(string $body, ?string $leftFence, ?string $rightFence, string $columnAlign = ''): Expression
    {
        $rows = [];
        foreach ($this->splitEnvironmentRows($body) as $row) {
            $cells = [];
            foreach ($this->splitEnvironmentCells($row) as $cell) {
                $cellResult = (new self())->parse($cell);
                if (!$cellResult->ok() || $cellResult->expression() === null) {
                    $this->diagnostics[] = [
                        'code' => 'invalid-environment-cell',
                        'message' => 'Unable to parse TeX environment cell.',
                    ];
                    continue;
                }
                $cells[] = $cellResult->expression();
            }
            if ($cells !== []) {
                $rows[] = $cells;
            }
        }

        $table = Expression::table($rows, $columnAlign === '' ? [] : [
            'columnalign' => $this->columnAlignForTable($columnAlign, max(0, ...array_map('count', $rows))),
        ]);

        return $leftFence === null && $rightFence === null
            ? $table
            : Expression::delimited($leftFence, $table, $rightFence);
    }

    /**
     * @return array{0:?string,1:?string}|null
     */
    private function environmentFences(string $environment): ?array
    {
        return [
            'align' => [null, null],
            'alignat' => [null, null],
            'aligned' => [null, null],
            'alignedat' => [null, null],
            'eqnarray' => [null, null],
            'flalign' => [null, null],
            'flaligned' => [null, null],
            'gather' => [null, null],
            'gathered' => [null, null],
            'multline' => [null, null],
            'multlined' => [null, null],
            'split' => [null, null],
            'cases' => ['{', null],
            'dcases' => ['{', null],
            'rcases' => [null, '}'],
            'matrix' => [null, null],
            'smallmatrix' => [null, null],
            'pmatrix' => ['(', ')'],
            'bmatrix' => ['[', ']'],
            'Bmatrix' => ['{', '}'],
            'vmatrix' => ['|', '|'],
            'Vmatrix' => ['‖', '‖'],
        ][$environment] ?? null;
    }

    private function normalizedEnvironmentName(string $environment): string
    {
        return str_ends_with($environment, '*') ? substr($environment, 0, -1) : $environment;
    }

    private function environmentConsumesLeadingGroup(string $environment): bool
    {
        return in_array($this->normalizedEnvironmentName($environment), ['alignat', 'alignedat'], true);
    }

    private function environmentColumnAlign(string $environment): string
    {
        return match ($this->normalizedEnvironmentName($environment)) {
            'align', 'aligned', 'alignat', 'alignedat', 'split' => 'right left',
            'flalign', 'flaligned' => 'left right',
            'eqnarray' => 'right center left',
            'gather', 'gathered', 'multline', 'multlined', 'subarray' => 'center',
            default => '',
        };
    }

    private function arrayColumnAlign(string $columnSpec): string
    {
        $alignments = [];
        $length = strlen($columnSpec);
        for ($offset = 0; $offset < $length; $offset++) {
            $alignment = match ($columnSpec[$offset]) {
                'l' => 'left',
                'c' => 'center',
                'r' => 'right',
                default => null,
            };
            if ($alignment !== null) {
                $alignments[] = $alignment;
            }
        }

        return implode(' ', $alignments);
    }

    private function columnAlignForTable(string $columnAlign, int $columnCount): string
    {
        $alignments = array_values(array_filter(
            preg_split('/\s+/', trim($columnAlign)) ?: [],
            static fn (string $alignment): bool => $alignment !== ''
        ));
        if ($alignments === [] || $columnCount <= 0) {
            return '';
        }

        $pattern = $alignments;
        while (count($alignments) < $columnCount) {
            $alignments[] = $pattern[count($alignments) % count($pattern)];
        }

        return implode(' ', array_slice($alignments, 0, $columnCount));
    }

    /**
     * @return array{dimension:string,stream:TexTokenStream}|null
     */
    private function readDimensionToken(TexTokenStream $stream): ?array
    {
        $stream = $stream->skipWhitespace();
        $remaining = substr($stream->source(), $stream->offset());
        if (preg_match('/\A[+\-]?(?:\d+(?:\.\d+)?|\.\d+)(?:em|ex|px|pt|pc|in|cm|mm|mu)\b/', $remaining, $match) !== 1) {
            return null;
        }

        return [
            'dimension' => $match[0],
            'stream' => $stream->withOffset($stream->offset() + strlen($match[0])),
        ];
    }

    private function normalizeSpacingDimension(string $dimension): ?string
    {
        $dimension = trim($dimension);
        if (preg_match('/\A[+\-]?(?:\d+(?:\.\d+)?|\.\d+)(?:em|ex|px|pt|pc|in|cm|mm|mu)\z/', $dimension) !== 1) {
            return null;
        }

        return str_starts_with($dimension, '+') ? substr($dimension, 1) : $dimension;
    }

    private function operatorNameText(string $name): string
    {
        $text = trim($name);
        $text = preg_replace('/\\\\(?:,|:|;|!|thinspace|medspace|thickspace|negthinspace|negmedspace|negthickspace|enspace|quad|qquad)\s*/', ' ', $text) ?? $text;
        $text = preg_replace('/\\\\([A-Za-z]+)/', '$1', $text) ?? $text;
        $text = str_replace(['\\{', '\\}'], ['{', '}'], $text);

        return preg_replace('/\s+/', ' ', $text) ?? $text;
    }

    private function textModeLiteral(string $text): string
    {
        $text = str_replace(
            ['\\&', '\\%', '\\$', '\\#', '\\_', '\\{', '\\}', '\\ ', '\\textbackslash', '\\TeX', '\\LaTeX'],
            ['&', '%', '$', '#', '_', '{', '}', ' ', '\\', 'TeX', 'LaTeX'],
            $text
        );

        return str_replace(['\\dots', '\\ldots'], '…', $text);
    }

    private function textModeExpression(string $text): Expression
    {
        $nodes = [];
        $buffer = '';
        $offset = 0;
        $length = strlen($text);

        while ($offset < $length) {
            if ($text[$offset] !== '\\') {
                $read = (new TexTokenStream($text, $offset))->readUtf8Char();
                if ($read === null) {
                    break;
                }
                $buffer .= $read['char'];
                $offset = $read['stream']->offset();
                continue;
            }

            $command = (new TexTokenStream($text, $offset))->readCommand();
            if ($command === null) {
                $buffer .= '\\';
                $offset++;
                continue;
            }

            $literal = $this->textModeLiteralCommand($command['command']);
            if ($literal !== null) {
                $buffer .= $literal;
                $offset = $command['stream']->offset();
                continue;
            }

            if ($this->isTextModeStyleCommand($command['command'])) {
                $group = $command['stream']->skipWhitespace()->readRawGroup();
                if ($group !== null) {
                    $this->appendTextModeBuffer($nodes, $buffer);
                    $nodes[] = Expression::style(
                        $this->textModeExpression($group['value']),
                        ['mathvariant' => $this->textModeVariant($command['command'])]
                    );
                    $offset = $group['stream']->offset();
                    continue;
                }
            }

            $styleAttributes = $this->styleDeclarationAttributes($command['command']);
            if ($styleAttributes !== []) {
                $group = $command['stream']->skipWhitespace()->readRawGroup();
                if ($group !== null) {
                    $result = (new self())->parse($group['value']);
                    if ($result->ok() && $result->expression() !== null) {
                        $this->appendTextModeBuffer($nodes, $buffer);
                        $nodes[] = Expression::style($result->expression(), $styleAttributes);
                        $offset = $group['stream']->offset();
                        continue;
                    }
                }
            }

            $buffer .= '\\' . $command['command'];
            $offset = $command['stream']->offset();
        }

        $this->appendTextModeBuffer($nodes, $buffer);

        return $this->rowExpression($nodes);
    }

    /**
     * @param list<Expression> $nodes
     */
    private function appendTextModeBuffer(array &$nodes, string &$buffer): void
    {
        if ($buffer === '') {
            return;
        }

        $nodes[] = Expression::text($this->textModeLiteral($buffer));
        $buffer = '';
    }

    private function textModeLiteralCommand(string $command): ?string
    {
        return match ($command) {
            '&', '%', '$', '#', '_', '{', '}' => $command,
            ' ' => ' ',
            'LaTeX' => 'LaTeX',
            'TeX' => 'TeX',
            'dots', 'ldots' => '…',
            'textbackslash' => '\\',
            default => null,
        };
    }

    private function isTextModeStyleCommand(string $command): bool
    {
        return in_array($command, ['emph', 'textbf', 'textit', 'textmd', 'textnormal', 'textrm', 'textsf', 'texttt', 'textup'], true);
    }

    private function textModeVariant(string $command): string
    {
        return [
            'emph' => 'italic',
            'textbf' => 'bold',
            'textit' => 'italic',
            'textmd' => 'normal',
            'textnormal' => 'normal',
            'textrm' => 'normal',
            'textsf' => 'sans-serif',
            'texttt' => 'monospace',
            'textup' => 'normal',
        ][$command] ?? 'normal';
    }

    private function mathVariantForCommand(string $command): string
    {
        return [
            'mathrm' => 'normal',
            'mathup' => 'normal',
            'mathbf' => 'bold',
            'mathit' => 'italic',
            'mathsf' => 'sans-serif',
            'mathtt' => 'monospace',
        ][$command] ?? 'normal';
    }

    /**
     * @return array<string, string>
     */
    private function styleDeclarationAttributes(string $command): array
    {
        return [
            'displaystyle' => ['displaystyle' => 'true', 'scriptlevel' => '0'],
            'textstyle' => ['displaystyle' => 'false', 'scriptlevel' => '0'],
            'scriptstyle' => ['displaystyle' => 'false', 'scriptlevel' => '1'],
            'scriptscriptstyle' => ['displaystyle' => 'false', 'scriptlevel' => '2'],
        ][$command] ?? [];
    }

    private function expressionText(Expression $expression): string
    {
        if ($expression->value !== null) {
            return $expression->value;
        }

        $text = '';
        foreach ($expression->children as $child) {
            $text .= $this->expressionText($child);
        }

        return strtr($text, [
            '−' => '-',
            '′' => "'",
            '″' => "''",
            '‴' => "'''",
            '⁗' => "''''",
        ]);
    }

    private function primeSymbol(int $count): string
    {
        return [
            1 => '′',
            2 => '″',
            3 => '‴',
            4 => '⁗',
        ][$count] ?? str_repeat('′', $count);
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
