<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DocTemplate
{
    /**
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context): string
    {
        $tokens = $this->tokenize($template);

        return $this->renderRange($tokens, 0, count($tokens), $context);
    }

    /**
     * @return list<array{type:string, value:string}>
     */
    private function tokenize(string $template): array
    {
        $tokens = [];
        $buffer = '';
        $length = strlen($template);

        for ($index = 0; $index < $length; $index++) {
            $char = $template[$index];
            if ($char !== '$') {
                $buffer .= $char;
                continue;
            }

            if (substr($template, $index, 3) === '$--') {
                $lineEnd = strpos($template, "\n", $index + 3);
                if ($lineEnd === false) {
                    break;
                }

                if ($this->commentStartsStandaloneLine($buffer)) {
                    $buffer = $this->dropStandaloneCommentLinePrefix($buffer);
                } else {
                    $buffer .= "\n";
                }
                $index = $lineEnd;
                continue;
            }

            if (($template[$index + 1] ?? '') === '$') {
                $buffer .= '$';
                $index++;
                continue;
            }

            if (($template[$index + 1] ?? '') === '{') {
                $closing = strpos($template, '}', $index + 2);
                if ($closing === false) {
                    throw new \UnexpectedValueException('Unclosed doctemplate ${...} directive');
                }

                $this->appendTextToken($tokens, $buffer);
                $buffer = '';
                $tokens[] = [
                    'type' => 'directive',
                    'value' => trim(substr($template, $index + 2, $closing - $index - 2), " \t"),
                ];
                $index = $closing;
                continue;
            }

            $closing = strpos($template, '$', $index + 1);
            if ($closing === false) {
                $buffer .= '$';
                continue;
            }

            $this->appendTextToken($tokens, $buffer);
            $buffer = '';
            $tokens[] = [
                'type' => 'directive',
                'value' => trim(substr($template, $index + 1, $closing - $index - 1), " \t"),
            ];
            $index = $closing;
        }

        $this->appendTextToken($tokens, $buffer);

        return $tokens;
    }

    private function commentStartsStandaloneLine(string $buffer): bool
    {
        $lineStart = strrpos($buffer, "\n");
        $linePrefix = $lineStart === false ? $buffer : substr($buffer, $lineStart + 1);

        return trim($linePrefix, " \t") === '';
    }

    private function dropStandaloneCommentLinePrefix(string $buffer): string
    {
        $lineStart = strrpos($buffer, "\n");

        return $lineStart === false ? '' : substr($buffer, 0, $lineStart + 1);
    }

    /**
     * @param list<array{type:string, value:string}> $tokens
     * @param array<string, mixed> $context
     */
    private function renderRange(array $tokens, int $start, int $end, array $context): string
    {
        $output = '';

        for ($index = $start; $index < $end; $index++) {
            $token = $tokens[$index];
            if ($token['type'] === 'text') {
                $output .= $token['value'];
                continue;
            }

            $directive = $token['value'];
            $ifVariable = $this->controlVariable($directive, 'if');
            if ($ifVariable !== null) {
                [$rendered, $nextIndex] = $this->renderIf($tokens, $index + 1, $end, $ifVariable, $context);
                $output .= $rendered;
                $index = $nextIndex - 1;
                continue;
            }

            $forVariable = $this->controlVariable($directive, 'for');
            if ($forVariable !== null) {
                [$rendered, $nextIndex] = $this->renderFor($tokens, $index + 1, $end, $forVariable, $context);
                $output .= $rendered;
                $index = $nextIndex - 1;
                continue;
            }

            if (in_array($directive, ['elseif', 'else', 'endif', 'sep', 'endfor'], true) || $this->controlVariable($directive, 'elseif') !== null) {
                throw new \UnexpectedValueException("Unexpected doctemplate control directive {$directive}");
            }

            $output .= $this->renderVariableDirective($directive, $context);
        }

        return $output;
    }

    /**
     * @param list<array{type:string, value:string}> $tokens
     * @param array<string, mixed> $context
     * @return array{0:string, 1:int}
     */
    private function renderIf(array $tokens, int $start, int $end, string $firstVariable, array $context): array
    {
        [$branches, $nextIndex] = $this->collectIfBranches($tokens, $start, $end, $firstVariable);

        foreach ($branches as $branch) {
            if ($branch['variable'] === null || $this->isTruthy($this->resolve($branch['variable'], $context)['value'])) {
                return [
                    $this->renderRange($tokens, $branch['start'], $branch['end'], $context),
                    $nextIndex,
                ];
            }
        }

        return ['', $nextIndex];
    }

    /**
     * @param list<array{type:string, value:string}> $tokens
     * @return array{0:list<array{variable:?string, start:int, end:int}>, 1:int}
     */
    private function collectIfBranches(array $tokens, int $start, int $end, string $firstVariable): array
    {
        $branches = [];
        $branchVariable = $firstVariable;
        $branchStart = $start;
        $depth = 0;

        for ($index = $start; $index < $end; $index++) {
            $token = $tokens[$index];
            if ($token['type'] !== 'directive') {
                continue;
            }

            $directive = $token['value'];
            if ($this->startsControlBlock($directive)) {
                $depth++;
                continue;
            }

            if ($this->endsControlBlock($directive)) {
                if ($depth > 0) {
                    $depth--;
                    continue;
                }

                if ($directive === 'endif') {
                    $branches[] = [
                        'variable' => $branchVariable,
                        'start' => $branchStart,
                        'end' => $index,
                    ];

                    return [$branches, $index + 1];
                }

                throw new \UnexpectedValueException("Unexpected doctemplate control directive {$directive}");
            }

            if ($depth !== 0) {
                continue;
            }

            $elseifVariable = $this->controlVariable($directive, 'elseif');
            if ($elseifVariable !== null) {
                $branches[] = [
                    'variable' => $branchVariable,
                    'start' => $branchStart,
                    'end' => $index,
                ];
                $branchVariable = $elseifVariable;
                $branchStart = $index + 1;
                continue;
            }

            if ($directive === 'else') {
                $branches[] = [
                    'variable' => $branchVariable,
                    'start' => $branchStart,
                    'end' => $index,
                ];
                $branchVariable = null;
                $branchStart = $index + 1;
            }
        }

        throw new \UnexpectedValueException('Unclosed doctemplate if block');
    }

    /**
     * @param list<array{type:string, value:string}> $tokens
     * @param array<string, mixed> $context
     * @return array{0:string, 1:int}
     */
    private function renderFor(array $tokens, int $start, int $end, string $variable, array $context): array
    {
        [$bodyStart, $bodyEnd, $separatorStart, $separatorEnd, $nextIndex] = $this->collectForSlices($tokens, $start, $end);
        $resolved = $this->resolve($variable, $context);
        $iterations = $this->loopIterations($resolved['exists'], $resolved['value']);
        $rendered = [];

        foreach ($iterations as $item) {
            $iterationContext = $this->contextForLoopIteration($context, $variable, $item);
            $rendered[] = $this->renderRange($tokens, $bodyStart, $bodyEnd, $iterationContext);
        }

        if ($rendered === []) {
            return ['', $nextIndex];
        }

        $separator = $separatorStart === null
            ? ''
            : $this->renderRange($tokens, $separatorStart, (int) $separatorEnd, $context);

        return [implode($separator, $rendered), $nextIndex];
    }

    /**
     * @param list<array{type:string, value:string}> $tokens
     * @return array{0:int, 1:int, 2:?int, 3:?int, 4:int}
     */
    private function collectForSlices(array $tokens, int $start, int $end): array
    {
        $depth = 0;
        $separatorStart = null;
        $separatorEnd = null;

        for ($index = $start; $index < $end; $index++) {
            $token = $tokens[$index];
            if ($token['type'] !== 'directive') {
                continue;
            }

            $directive = $token['value'];
            if ($this->startsControlBlock($directive)) {
                $depth++;
                continue;
            }

            if ($this->endsControlBlock($directive)) {
                if ($depth > 0) {
                    $depth--;
                    continue;
                }

                if ($directive === 'endfor') {
                    $bodyEnd = $separatorStart === null ? $index : $separatorStart - 1;
                    if ($separatorStart !== null) {
                        $separatorEnd = $index;
                    }

                    return [$start, $bodyEnd, $separatorStart, $separatorEnd, $index + 1];
                }

                throw new \UnexpectedValueException("Unexpected doctemplate control directive {$directive}");
            }

            if ($depth === 0 && $directive === 'sep' && $separatorStart === null) {
                $separatorStart = $index + 1;
            }
        }

        throw new \UnexpectedValueException('Unclosed doctemplate for block');
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderVariableDirective(string $directive, array $context): string
    {
        if (!preg_match('/^(it|[A-Za-z][A-Za-z0-9_.-]*)(?:\\[(.*)\\])?$/s', $directive, $matches)) {
            throw new \UnexpectedValueException("Unsupported doctemplate directive {$directive}");
        }

        $name = $matches[1];
        if (in_array($name, ['if', 'else', 'elseif', 'endif', 'for', 'sep', 'endfor'], true)) {
            throw new \UnexpectedValueException("Reserved doctemplate keyword {$name} cannot be rendered as a variable");
        }

        $separator = array_key_exists(2, $matches) ? $matches[2] : null;
        $resolved = $this->resolve($name, $context);
        if (!$resolved['exists']) {
            return '';
        }

        return $this->renderValue($resolved['value'], $separator);
    }

    /**
     * @return ?string
     */
    private function controlVariable(string $directive, string $name): ?string
    {
        if (!preg_match('/^' . preg_quote($name, '/') . '\\((it|[A-Za-z][A-Za-z0-9_.-]*)\\)$/', $directive, $matches)) {
            return null;
        }

        return $matches[1];
    }

    private function startsControlBlock(string $directive): bool
    {
        return $this->controlVariable($directive, 'if') !== null || $this->controlVariable($directive, 'for') !== null;
    }

    private function endsControlBlock(string $directive): bool
    {
        return $directive === 'endif' || $directive === 'endfor';
    }

    /**
     * @param array<string, mixed> $context
     * @return array{exists:bool, value:mixed}
     */
    private function resolve(string $path, array $context): array
    {
        $segments = explode('.', $path);
        $value = $context;

        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
                continue;
            }

            return ['exists' => false, 'value' => null];
        }

        return ['exists' => true, 'value' => $value];
    }

    private function renderValue(mixed $value, ?string $separator): string
    {
        if (is_array($value)) {
            if (!array_is_list($value)) {
                return 'true';
            }

            $parts = [];
            foreach ($value as $item) {
                $parts[] = $this->renderValue($item, null);
            }

            return implode($separator ?? '', $parts);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_string($value) || is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return '';
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_array($value)) {
            if (!array_is_list($value)) {
                return true;
            }

            foreach ($value as $item) {
                if ($this->isTruthy($item)) {
                    return true;
                }
            }

            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return $value !== '';
        }

        if (is_int($value) || is_float($value)) {
            return true;
        }

        return false;
    }

    /**
     * @return list<mixed>
     */
    private function loopIterations(bool $exists, mixed $value): array
    {
        if (!$exists || $value === null) {
            return [];
        }

        if (is_array($value)) {
            if ($value === []) {
                return [];
            }

            return array_is_list($value) ? $value : [$value];
        }

        return [$value];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function contextForLoopIteration(array $context, string $path, mixed $item): array
    {
        $next = $context;
        $next['it'] = $item;

        $segments = explode('.', $path);
        if ($segments[0] !== 'it') {
            $cursor = &$next;
            foreach ($segments as $offset => $segment) {
                if ($offset === count($segments) - 1) {
                    $cursor[$segment] = $item;
                    break;
                }

                if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                    $cursor[$segment] = [];
                }
                $cursor = &$cursor[$segment];
            }
            unset($cursor);
        }

        if (is_array($item) && !array_is_list($item)) {
            foreach ($item as $key => $value) {
                if (is_string($key) && $key !== 'it') {
                    $next[$key] = $value;
                }
            }
        }

        return $next;
    }

    /**
     * @param list<array{type:string, value:string}> $tokens
     */
    private function appendTextToken(array &$tokens, string $text): void
    {
        if ($text !== '') {
            $tokens[] = ['type' => 'text', 'value' => $text];
        }
    }
}
