<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class CssMinifier
{
    public function minify(string $css): string
    {
        $css = $this->stripComments($css);
        $output = '';
        $quote = null;
        $pendingSpace = false;
        $length = strlen($css);
        $tight = '{}:;,>+~()[]';

        for ($i = 0; $i < $length; $i++) {
            $char = $css[$i];
            if ($quote !== null) {
                $output .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $output .= $css[++$i];
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                if ($pendingSpace && $this->needsSpaceBefore($output, $char)) {
                    $output .= ' ';
                }
                $pendingSpace = false;
                $quote = $char;
                $output .= $char;
                continue;
            }

            if (ctype_space($char)) {
                $pendingSpace = true;
                continue;
            }

            if (str_contains($tight, $char)) {
                $output = rtrim($output);
                $output .= $char;
                $pendingSpace = false;
                continue;
            }

            if ($pendingSpace && $this->needsSpaceBefore($output, $char)) {
                $output .= ' ';
            }
            $pendingSpace = false;
            $output .= $char;
        }

        $css = $this->minifyMediaQueries($this->minifyDeclarationValues(str_replace(';}', '}', trim($output))));

        return $this->composeTransitionDeclarationBlocks($css);
    }

    private function stripComments(string $css): string
    {
        $output = '';
        $quote = null;
        $length = strlen($css);
        for ($i = 0; $i < $length; $i++) {
            $char = $css[$i];
            if ($quote !== null) {
                $output .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $output .= $css[++$i];
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $output .= $char;
                continue;
            }

            if ($char === '/' && ($css[$i + 1] ?? '') === '*') {
                $end = strpos($css, '*/', $i + 2);
                if ($end === false) {
                    return $output;
                }
                $i = $end + 1;
                continue;
            }

            $output .= $char;
        }

        return $output;
    }

    private function needsSpaceBefore(string $output, string $next): bool
    {
        if ($output === '') {
            return false;
        }
        $previous = $output[strlen($output) - 1];
        if ($previous === ')') {
            return ctype_alnum($next) || $next === '_' || $next === '-' || $next === '.' || $next === '#';
        }
        if ($previous === '"' || $previous === "'") {
            return ctype_alnum($next) || $next === '_' || $next === '-' || $next === '.' || $next === '#';
        }
        if ($next === '"' || $next === "'") {
            return ctype_alnum($previous) || $previous === '_' || $previous === '-' || $previous === '%';
        }

        return (ctype_alnum($previous) || $previous === '_' || $previous === '-' || $previous === '%')
            && (ctype_alnum($next) || $next === '_' || $next === '-' || $next === '.');
    }

    private function minifyDeclarationValues(string $css): string
    {
        $output = '';
        $quote = null;
        $braceDepth = 0;
        $length = strlen($css);

        for ($i = 0; $i < $length; $i++) {
            $char = $css[$i];
            if ($quote !== null) {
                $output .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $output .= $css[++$i];
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $output .= $char;
                continue;
            }

            if ($char === '{') {
                $braceDepth++;
                $output .= $char;
                continue;
            }

            if ($char === '}') {
                $braceDepth = max(0, $braceDepth - 1);
                $output .= $char;
                continue;
            }

            if ($char !== ':' || $braceDepth === 0) {
                $output .= $char;
                continue;
            }

            $property = $this->currentPropertyCandidate($output);
            if (!$this->isDeclarationProperty($property)) {
                $output .= $char;
                continue;
            }

            [$value, $delimiter, $offset] = $this->readDeclarationValue($css, $i + 1);
            $output .= ':' . $this->minifyDeclarationValue($property, $value);
            if ($delimiter !== '') {
                if ($delimiter === '}') {
                    $braceDepth = max(0, $braceDepth - 1);
                }
                $output .= $delimiter;
            }
            $i = $offset;
        }

        return $output;
    }

    private function minifyMediaQueries(string $css): string
    {
        $output = '';
        $cursor = 0;
        $length = strlen($css);
        $parser = new MediaQueryParser();

        while ($cursor < $length) {
            $position = stripos($css, '@media', $cursor);
            if ($position === false) {
                $output .= substr($css, $cursor);
                break;
            }
            $before = $position === 0 ? '' : $css[$position - 1];
            $after = $css[$position + 6] ?? '';
            if (($before !== '' && preg_match('/[-_a-zA-Z0-9]/', $before) === 1)
                || ($after !== '' && preg_match('/[-_a-zA-Z0-9]/', $after) === 1)
            ) {
                $output .= substr($css, $cursor, $position + 6 - $cursor);
                $cursor = $position + 6;
                continue;
            }

            $open = $this->findNextTopLevel($css, '{', $position + 6);
            if ($open === null) {
                $output .= substr($css, $cursor);
                break;
            }

            $prelude = trim(substr($css, $position + 6, $open - ($position + 6)));
            $output .= substr($css, $cursor, $position - $cursor) . '@media';
            if ($prelude !== '') {
                $output .= ' ' . $parser->minifyList($prelude);
            }
            $output .= '{';
            $cursor = $open + 1;
        }

        return $output;
    }

    private function findNextTopLevel(string $css, string $needle, int $start): ?int
    {
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
        $length = strlen($css);

        for ($i = $start; $i < $length; $i++) {
            $char = $css[$i];
            if ($quote !== null) {
                if ($char === '\\') {
                    $i++;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(') {
                $parenDepth++;
            } elseif ($char === ')') {
                $parenDepth = max(0, $parenDepth - 1);
            } elseif ($char === '[') {
                $bracketDepth++;
            } elseif ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
            } elseif ($char === $needle && $parenDepth === 0 && $bracketDepth === 0) {
                return $i;
            }
        }

        return null;
    }

    private function currentPropertyCandidate(string $output): string
    {
        $block = strrpos($output, '{');
        $semicolon = strrpos($output, ';');
        $start = max($block === false ? -1 : $block, $semicolon === false ? -1 : $semicolon) + 1;

        return trim(substr($output, $start));
    }

    private function isDeclarationProperty(string $property): bool
    {
        return preg_match('/^(?:[_a-zA-Z]|-[_a-zA-Z]|--[_a-zA-Z])[-_a-zA-Z0-9]*$/', $property) === 1;
    }

    /**
     * @return array{0: string, 1: string, 2: int}
     */
    private function readDeclarationValue(string $css, int $start): array
    {
        $value = '';
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
        $length = strlen($css);

        for ($i = $start; $i < $length; $i++) {
            $char = $css[$i];
            if ($quote !== null) {
                $value .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $value .= $css[++$i];
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(') {
                $parenDepth++;
            } elseif ($char === ')') {
                $parenDepth = max(0, $parenDepth - 1);
            } elseif ($char === '[') {
                $bracketDepth++;
            } elseif ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
            } elseif (($char === ';' || $char === '}') && $parenDepth === 0 && $bracketDepth === 0) {
                return [$value, $char, $i];
            }

            $value .= $char;
        }

        return [$value, '', $length - 1];
    }

    private function minifyDeclarationValue(string $property, string $value): string
    {
        $value = $this->normalizeMathFunctionOperators($value);
        $value = $this->minifyAnimationLonghandValue($property, $value);
        $value = $this->minifyTransitionLonghandValue($property, $value);
        if (!str_starts_with($property, '--')) {
            $value = $this->minifyColorKeywords($value);
        }

        return $value;
    }

    private function minifyAnimationLonghandValue(string $property, string $value): string
    {
        return match ($property) {
            'animation',
            '-webkit-animation',
            '-moz-animation' => $this->mapCommaList($value, fn (string $part): string => $this->minifyAnimationShorthandLayer($part)),
            'animation-name' => $this->mapCommaList($value, fn (string $part): string => $this->minifyAnimationName($part)),
            'animation-duration',
            'animation-delay' => $this->mapCommaList($value, fn (string $part): string => $this->minifyTimeToken($part)),
            'animation-timing-function' => $this->mapCommaList($value, fn (string $part): string => $this->minifyTransitionTimingFunction($part)),
            'animation-iteration-count' => $this->mapCommaList($value, fn (string $part): string => $this->minifyAnimationIterationCount($part)),
            'animation-direction',
            'animation-play-state',
            'animation-fill-mode',
            'animation-composition' => $this->mapCommaList($value, static fn (string $part): string => strtolower(trim($part))),
            default => $value,
        };
    }

    private function minifyAnimationName(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^([\'"])(.*)\1$/s', $value, $matches) !== 1) {
            return $value;
        }

        $name = $matches[2];
        if (preg_match('/^-?[_a-zA-Z][_a-zA-Z0-9-]*$/', $name) !== 1) {
            return $value;
        }

        return in_array(strtolower($name), [
            'default',
            'inherit',
            'initial',
            'none',
            'revert',
            'revert-layer',
            'unset',
        ], true) ? $value : $name;
    }

    private function minifyAnimationShorthandLayer(string $layer): string
    {
        $tokens = $this->splitWhitespaceTopLevel($layer);
        if ($tokens === []) {
            return trim($layer);
        }

        if (stripos($layer, 'var(') !== false) {
            return implode(' ', array_map(
                fn (string $token): string => $this->minifyAnimationTokenInPlace($token),
                $tokens
            ));
        }

        $components = [
            'duration' => null,
            'timing' => null,
            'delay' => null,
            'iteration' => null,
            'direction' => null,
            'fill' => null,
            'play' => null,
            'name' => null,
            'timeline' => null,
        ];

        foreach ($tokens as $index => $token) {
            $lower = strtolower($token);

            if ($this->isQuotedStringToken($token)) {
                if ($components['name'] !== null) {
                    return trim($layer);
                }
                $components['name'] = $this->minifyAnimationName($token);
                continue;
            }

            if ($this->isTimeValue($token)) {
                if ($components['duration'] === null) {
                    $components['duration'] = $this->minifyTimeValue($token);
                    continue;
                }
                if ($components['delay'] === null) {
                    $components['delay'] = $this->minifyTimeValue($token);
                    continue;
                }

                return trim($layer);
            }

            if ($components['timing'] === null && $this->isTransitionTimingFunction($token)) {
                $components['timing'] = $this->minifyTransitionTimingFunction($token);
                continue;
            }

            if ($components['iteration'] === null && $this->isAnimationIterationToken($lower)) {
                $components['iteration'] = $this->minifyAnimationIterationCount($lower);
                continue;
            }

            if ($components['direction'] === null && in_array($lower, ['normal', 'reverse', 'alternate', 'alternate-reverse'], true)) {
                $components['direction'] = $lower;
                continue;
            }

            if ($components['fill'] === null && in_array($lower, ['none', 'forwards', 'backwards', 'both'], true)) {
                if ($lower !== 'none' || $components['name'] !== null || $this->hasFutureAnimationNameToken($tokens, $index + 1)) {
                    $components['fill'] = $lower;
                    continue;
                }
            }

            if ($components['play'] === null && in_array($lower, ['running', 'paused'], true)) {
                $components['play'] = $lower;
                continue;
            }

            if ($components['timeline'] === null && $this->isAnimationTimelineToken($token)) {
                $components['timeline'] = $this->minifyAnimationTimelineToken($token);
                continue;
            }

            if ($components['name'] !== null) {
                return trim($layer);
            }
            $components['name'] = $this->minifyAnimationName($token);
        }

        return $this->serializeAnimationShorthandLayer($components);
    }

    private function minifyAnimationTokenInPlace(string $token): string
    {
        if ($this->isTimeValue($token)) {
            return $this->minifyTimeValue($token);
        }
        if ($this->isTransitionTimingFunction($token)) {
            return $this->minifyTransitionTimingFunction($token);
        }
        if ($this->isQuotedStringToken($token)) {
            return $this->minifyAnimationName($token);
        }

        return strtolower($token) === 'auto' ? 'auto' : $token;
    }

    /**
     * @param list<string> $tokens
     */
    private function hasFutureAnimationNameToken(array $tokens, int $offset): bool
    {
        for ($i = $offset; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            if ($this->isQuotedStringToken($token)) {
                return true;
            }

            $lower = strtolower($token);
            if ($this->isTimeValue($token)
                || $this->isTransitionTimingFunction($token)
                || $this->isAnimationIterationToken($lower)
                || in_array($lower, ['normal', 'reverse', 'alternate', 'alternate-reverse'], true)
                || in_array($lower, ['none', 'forwards', 'backwards', 'both'], true)
                || in_array($lower, ['running', 'paused'], true)
                || $this->isAnimationTimelineToken($token)
            ) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @param array{duration:?string,timing:?string,delay:?string,iteration:?string,direction:?string,fill:?string,play:?string,name:?string,timeline:?string} $components
     */
    private function serializeAnimationShorthandLayer(array $components): string
    {
        $duration = $components['duration'] ?? '0s';
        $timing = $components['timing'] ?? 'ease';
        $delay = $components['delay'] ?? '0s';
        $iteration = $components['iteration'] ?? '1';
        $direction = $components['direction'] ?? 'normal';
        $fill = $components['fill'] ?? 'none';
        $play = $components['play'] ?? 'running';
        $name = $components['name'] ?? 'none';
        $timeline = $components['timeline'] ?? 'auto';

        $parts = [];
        if ($duration !== '0s' || $delay !== '0s') {
            $parts[] = $duration;
        }
        if ($timing !== 'ease' || $this->animationNameConflictsWith($name, 'timing')) {
            $parts[] = $timing;
        }
        if ($delay !== '0s') {
            $parts[] = $delay;
        }
        if ($iteration !== '1' || $this->animationNameConflictsWith($name, 'iteration')) {
            $parts[] = $iteration;
        }
        if ($direction !== 'normal' || $this->animationNameConflictsWith($name, 'direction')) {
            $parts[] = $direction;
        }
        if ($fill !== 'none' || $this->animationNameConflictsWith($name, 'fill')) {
            $parts[] = $fill;
        }
        if ($play !== 'running' || $this->animationNameConflictsWith($name, 'play')) {
            $parts[] = $play;
        }
        if ($name !== 'none' || $parts === []) {
            $parts[] = $name;
        }
        if ($timeline !== 'auto') {
            $parts[] = $timeline;
        }

        return implode(' ', $parts);
    }

    private function animationNameConflictsWith(string $name, string $component): bool
    {
        if ($this->isQuotedStringToken($name)) {
            return false;
        }

        $lower = strtolower($name);

        return match ($component) {
            'timing' => $this->isTransitionTimingFunction($lower),
            'iteration' => $this->isAnimationIterationToken($lower),
            'direction' => in_array($lower, ['normal', 'reverse', 'alternate', 'alternate-reverse'], true),
            'fill' => in_array($lower, ['forwards', 'backwards', 'both'], true),
            'play' => in_array($lower, ['running', 'paused'], true),
            default => false,
        };
    }

    private function isQuotedStringToken(string $token): bool
    {
        return preg_match('/^([\'"]).*\1$/s', trim($token)) === 1;
    }

    private function isAnimationIterationToken(string $token): bool
    {
        return $token === 'infinite' || preg_match('/^[+-]?(?:\d+|\d*\.\d+)$/', $token) === 1;
    }

    private function isAnimationTimelineToken(string $token): bool
    {
        $lower = strtolower(trim($token));

        return $lower === 'auto'
            || str_starts_with($lower, '--')
            || preg_match('/^(?:scroll|view)\(/', $lower) === 1;
    }

    private function minifyAnimationTimelineToken(string $token): string
    {
        $lower = strtolower(trim($token));
        if (preg_match('/^scroll\((.*)\)$/', $lower, $matches) === 1) {
            $parts = $this->splitWhitespaceTopLevel($matches[1]);
            sort($parts);
            $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== 'block' && $part !== 'nearest'));

            return 'scroll(' . implode(' ', $parts) . ')';
        }
        if (preg_match('/^view\((.*)\)$/', $lower, $matches) === 1) {
            $parts = $this->splitWhitespaceTopLevel($matches[1]);
            if (($parts[0] ?? null) === 'block') {
                array_shift($parts);
            }
            if (count($parts) >= 3 && $parts[1] === 'auto' && $parts[2] === 'auto') {
                array_splice($parts, 1);
            }
            if (count($parts) >= 3 && $parts[1] === $parts[2]) {
                array_pop($parts);
            }

            return 'view(' . implode(' ', $parts) . ')';
        }

        return $lower;
    }

    private function minifyTransitionLonghandValue(string $property, string $value): string
    {
        return match (strtolower($property)) {
            'transition',
            '-webkit-transition',
            '-moz-transition' => $this->minifyTransitionShorthandValue($value),
            'transition-property',
            '-webkit-transition-property',
            '-moz-transition-property' => $this->minifyTransitionPropertyValue($value),
            'transition-duration',
            'transition-delay' => $this->mapCommaList($value, fn (string $part): string => $this->minifyTimeValue($part)),
            'transition-timing-function' => $this->mapCommaList($value, fn (string $part): string => $this->minifyTransitionTimingFunction($part)),
            default => $value,
        };
    }

    private function minifyTransitionPropertyValue(string $value): string
    {
        $properties = [];
        foreach ($this->splitTopLevel($value, ',') as $part) {
            array_push($properties, ...$this->expandBlockAxisTransitionProperty(trim($part)));
        }

        return implode(',', $properties);
    }

    private function minifyTransitionShorthandValue(string $value): string
    {
        return $this->mapCommaList($value, fn (string $part): string => $this->minifyTransitionLayer($part));
    }

    private function minifyTransitionLayer(string $layer): string
    {
        $tokens = $this->splitWhitespaceTopLevel($layer);
        if ($tokens === []) {
            return trim($layer);
        }

        $property = null;
        $duration = null;
        $timing = null;
        $delay = null;
        $behavior = null;

        foreach ($tokens as $token) {
            if ($this->isTimeValue($token)) {
                if ($duration === null) {
                    $duration = $this->minifyTimeValue($token);
                    continue;
                }
                if ($delay === null) {
                    $delay = $this->minifyTimeValue($token);
                    continue;
                }

                return trim($layer);
            }

            if ($this->isTransitionTimingFunction($token)) {
                if ($timing !== null) {
                    return trim($layer);
                }
                $timing = $this->minifyTransitionTimingFunction($token);
                continue;
            }

            $lower = strtolower($token);
            if ($lower === 'normal' || $lower === 'allow-discrete') {
                if ($behavior !== null) {
                    return trim($layer);
                }
                $behavior = $lower;
                continue;
            }

            if ($property !== null) {
                return trim($layer);
            }
            $property = $token;
        }

        $parts = [];
        if ($duration !== null) {
            $parts[] = $duration;
        }
        if ($timing !== null && $timing !== 'ease') {
            $parts[] = $timing;
        }
        if ($delay !== null && $delay !== '0s') {
            if ($duration === null) {
                $parts[] = '0s';
            }
            $parts[] = $delay;
        }
        if ($behavior !== null && $behavior !== 'normal') {
            $parts[] = $behavior;
        }

        $value = $parts === [] ? 'all' : implode(' ', $parts);
        if ($property === null || strtolower($property) === 'all') {
            return $value;
        }

        return implode(',', array_map(
            static fn (string $expanded): string => $expanded . ($value === 'all' ? '' : ' ' . $value),
            $this->expandBlockAxisTransitionProperty($property)
        ));
    }

    private function isTimeValue(string $value): bool
    {
        $value = trim($value);
        if (preg_match('/^[+-]?(?:\d+|\d*\.\d+)(?:ms|s)$/i', $value) === 1) {
            return true;
        }

        return $this->evaluateTimeCalc($value) !== null;
    }

    private function isTransitionTimingFunction(string $value): bool
    {
        $value = strtolower(trim($value));
        if (in_array($value, ['linear', 'ease', 'ease-in', 'ease-out', 'ease-in-out', 'step-start', 'step-end'], true)) {
            return true;
        }

        return preg_match('/^(?:cubic-bezier|steps)\(/', $value) === 1;
    }

    private function minifyTimeValue(string $value): string
    {
        $value = trim($value);
        $time = $this->evaluateTimeCalc($value);

        return $time === null ? $this->minifyTimeToken($value) : $this->shortestTime($time);
    }

    private function minifyTimeToken(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))(ms|s)$/i', $value, $matches) !== 1) {
            return $value;
        }

        $number = (float) $matches[1];
        $unit = strtolower($matches[2]);

        return $this->shortestTime($unit === 'ms' ? $number / 1000 : $number);
    }

    private function minifyAnimationIterationCount(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === 'infinite') {
            return $value;
        }

        return preg_match('/^[+-]?(?:\d+|\d*\.\d+)$/', $value) === 1
            ? $this->minifyNumber((float) $value)
            : $value;
    }

    private function minifyTransitionTimingFunction(string $value): string
    {
        $value = strtolower(trim($value));
        if (preg_match('/^cubic-bezier\((.*)\)$/', $value, $matches) === 1) {
            $numbers = array_map('trim', explode(',', $matches[1]));
            if (count($numbers) !== 4) {
                return $value;
            }

            $canonical = implode(',', array_map(fn (string $number): string => $this->minifyNumber((float) $number), $numbers));

            return match ($canonical) {
                '.25,.1,.25,1' => 'ease',
                '.42,0,1,1' => 'ease-in',
                '0,0,.58,1' => 'ease-out',
                '.42,0,.58,1' => 'ease-in-out',
                default => 'cubic-bezier(' . $canonical . ')',
            };
        }

        if (preg_match('/^steps\(\s*([+-]?(?:\d+|\d*\.\d+))\s*,\s*([^)]+)\)$/', $value, $matches) === 1) {
            $count = $this->minifyNumber((float) $matches[1]);
            $position = trim(strtolower($matches[2]));
            $position = match ($position) {
                'jump-start' => 'start',
                'jump-end' => 'end',
                default => $position,
            };

            if ($count === '1' && $position === 'start') {
                return 'step-start';
            }
            if ($count === '1' && $position === 'end') {
                return 'step-end';
            }

            return 'steps(' . $count . ',' . $position . ')';
        }

        return $value;
    }

    private function shortestTime(float $seconds): string
    {
        $secondsValue = $this->minifyNumber($seconds) . 's';
        $millisecondsValue = $this->minifyNumber($seconds * 1000) . 'ms';

        return strlen($millisecondsValue) <= strlen($secondsValue) ? $millisecondsValue : $secondsValue;
    }

    private function evaluateTimeCalc(string $value): ?float
    {
        if (preg_match('/^calc\((.*)\)$/i', trim($value), $matches) !== 1) {
            return null;
        }

        $tokens = $this->tokenizeTimeExpression($matches[1]);
        if ($tokens === []) {
            return null;
        }

        $offset = 0;
        $result = $this->parseTimeExpression($tokens, $offset);
        if ($result === null || $offset !== count($tokens) || $result['kind'] !== 'time') {
            return null;
        }

        return $result['value'];
    }

    /**
     * @return list<array{type:string,value:string}>
     */
    private function tokenizeTimeExpression(string $expression): array
    {
        $tokens = [];
        $length = strlen($expression);
        for ($i = 0; $i < $length;) {
            $char = $expression[$i];
            if (ctype_space($char)) {
                $i++;
                continue;
            }
            if (str_contains('()+-*', $char)) {
                $tokens[] = ['type' => $char, 'value' => $char];
                $i++;
                continue;
            }
            if (preg_match('/\G(?:\d+|\d*\.\d+)(?:ms|s)?/Ai', $expression, $matches, 0, $i) === 1) {
                $tokens[] = ['type' => 'number', 'value' => $matches[0]];
                $i += strlen($matches[0]);
                continue;
            }

            return [];
        }

        return $tokens;
    }

    /**
     * @param list<array{type:string,value:string}> $tokens
     * @return array{value:float,kind:string}|null
     */
    private function parseTimeExpression(array $tokens, int &$offset): ?array
    {
        $value = $this->parseTimeProduct($tokens, $offset);
        if ($value === null) {
            return null;
        }

        while ($offset < count($tokens) && in_array($tokens[$offset]['type'], ['+', '-'], true)) {
            $operator = $tokens[$offset++]['type'];
            $right = $this->parseTimeProduct($tokens, $offset);
            if ($right === null) {
                return null;
            }
            if ($value['kind'] !== 'time' || $right['kind'] !== 'time') {
                return null;
            }
            $value = [
                'value' => $operator === '+' ? $value['value'] + $right['value'] : $value['value'] - $right['value'],
                'kind' => 'time',
            ];
        }

        return $value;
    }

    /**
     * @param list<array{type:string,value:string}> $tokens
     * @return array{value:float,kind:string}|null
     */
    private function parseTimeProduct(array $tokens, int &$offset): ?array
    {
        $value = $this->parseTimeFactor($tokens, $offset);
        if ($value === null) {
            return null;
        }

        while ($offset < count($tokens) && $tokens[$offset]['type'] === '*') {
            $offset++;
            $right = $this->parseTimeFactor($tokens, $offset);
            if ($right === null) {
                return null;
            }
            if ($value['kind'] === 'time' && $right['kind'] === 'time') {
                return null;
            }
            $value = [
                'value' => $value['value'] * $right['value'],
                'kind' => $value['kind'] === 'time' || $right['kind'] === 'time' ? 'time' : 'number',
            ];
        }

        return $value;
    }

    /**
     * @param list<array{type:string,value:string}> $tokens
     * @return array{value:float,kind:string}|null
     */
    private function parseTimeFactor(array $tokens, int &$offset): ?array
    {
        if ($offset >= count($tokens)) {
            return null;
        }

        $token = $tokens[$offset++];
        if ($token['type'] === '+') {
            return $this->parseTimeFactor($tokens, $offset);
        }
        if ($token['type'] === '-') {
            $value = $this->parseTimeFactor($tokens, $offset);

            return $value === null ? null : ['value' => -$value['value'], 'kind' => $value['kind']];
        }
        if ($token['type'] === '(') {
            $value = $this->parseTimeExpression($tokens, $offset);
            if ($value === null || ($tokens[$offset]['type'] ?? null) !== ')') {
                return null;
            }
            $offset++;

            return $value;
        }
        if ($token['type'] !== 'number') {
            return null;
        }

        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))(ms|s)?$/i', $token['value'], $matches) !== 1) {
            return null;
        }

        $number = (float) $matches[1];
        $unit = strtolower($matches[2] ?? '');

        if ($unit === '') {
            return ['value' => $number, 'kind' => 'number'];
        }

        return ['value' => $unit === 'ms' ? $number / 1000 : $number, 'kind' => 'time'];
    }

    private function minifyNumber(float $number): string
    {
        if (abs($number) < 0.0000001) {
            return '0';
        }

        $formatted = rtrim(rtrim(sprintf('%.6F', $number), '0'), '.');
        if (str_starts_with($formatted, '0.')) {
            return substr($formatted, 1);
        }
        if (str_starts_with($formatted, '-0.')) {
            return '-' . substr($formatted, 2);
        }

        return $formatted;
    }

    private function mapCommaList(string $value, callable $mapper): string
    {
        return implode(',', array_map(
            static fn (string $part): string => $mapper($part),
            $this->splitTopLevel($value, ',')
        ));
    }

    /**
     * @return list<string>
     */
    private function splitWhitespaceTopLevel(string $value): array
    {
        $tokens = [];
        $token = '';
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($quote !== null) {
                $token .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $token .= $value[++$i];
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(') {
                $parenDepth++;
            } elseif ($char === ')') {
                $parenDepth = max(0, $parenDepth - 1);
            } elseif ($char === '[') {
                $bracketDepth++;
            } elseif ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
            } elseif (ctype_space($char) && $parenDepth === 0 && $bracketDepth === 0) {
                if ($token !== '') {
                    $tokens[] = $token;
                    $token = '';
                }
                continue;
            }

            $token .= $char;
        }

        if ($token !== '') {
            $tokens[] = $token;
        }

        return $tokens;
    }

    /**
     * @return list<string>
     */
    private function splitTopLevel(string $value, string $delimiter): array
    {
        $parts = [''];
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($quote !== null) {
                $parts[array_key_last($parts)] .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $parts[array_key_last($parts)] .= $value[++$i];
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(') {
                $parenDepth++;
            } elseif ($char === ')') {
                $parenDepth = max(0, $parenDepth - 1);
            } elseif ($char === '[') {
                $bracketDepth++;
            } elseif ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
            } elseif ($char === $delimiter && $parenDepth === 0 && $bracketDepth === 0) {
                $parts[] = '';
                continue;
            }

            $parts[array_key_last($parts)] .= $char;
        }

        return array_values(array_map('trim', $parts));
    }

    private function composeTransitionDeclarationBlocks(string $css): string
    {
        $output = '';
        $cursor = 0;
        $length = strlen($css);

        while ($cursor < $length) {
            $open = $this->findNextTopLevel($css, '{', $cursor);
            if ($open === null) {
                $output .= substr($css, $cursor);
                break;
            }

            $close = $this->findMatchingBraceInCss($css, $open);
            $body = $this->composeTransitionDeclarationBlocks(substr($css, $open + 1, $close - $open - 1));
            if (!str_contains($body, '{')) {
                $body = $this->composeTransitionDeclarationList($body);
            }

            $output .= substr($css, $cursor, $open - $cursor + 1) . $body . '}';
            $cursor = $close + 1;
        }

        return $output;
    }

    private function findMatchingBraceInCss(string $css, int $open): int
    {
        $quote = null;
        $depth = 0;
        $length = strlen($css);
        for ($i = $open; $i < $length; $i++) {
            $char = $css[$i];
            if ($quote !== null) {
                if ($char === '\\') {
                    $i++;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return $length - 1;
    }

    private function composeTransitionDeclarationList(string $body): string
    {
        if (stripos($body, 'transition') === false) {
            return $body;
        }

        $entries = $this->parseDeclarationEntriesForComposition($body);
        if ($entries === null) {
            return $body;
        }

        foreach (['-webkit-', '-moz-', ''] as $prefix) {
            $this->rewriteTransitionGroup($entries, $prefix);
        }

        return $this->serializeDeclarationEntriesForComposition($entries);
    }

    /**
     * @return list<array{property:string,name:string,value:string,important:bool,drop:bool}>|null
     */
    private function parseDeclarationEntriesForComposition(string $body): ?array
    {
        $entries = [];
        foreach ($this->splitTopLevel($body, ';') as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $colon = $this->findTopLevelColon($part);
            if ($colon === null) {
                return null;
            }

            $name = trim(substr($part, 0, $colon));
            $property = strtolower($name);
            $value = trim(substr($part, $colon + 1));
            if ($property === '' || $value === '') {
                return null;
            }

            [$value, $important] = $this->splitImportantFlag($value);
            $entries[] = [
                'property' => $property,
                'name' => $name,
                'value' => $value,
                'important' => $important,
                'drop' => false,
            ];
        }

        return $entries;
    }

    private function findTopLevelColon(string $part): ?int
    {
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
        $length = strlen($part);
        for ($i = 0; $i < $length; $i++) {
            $char = $part[$i];
            if ($quote !== null) {
                if ($char === '\\') {
                    $i++;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(') {
                $parenDepth++;
            } elseif ($char === ')') {
                $parenDepth = max(0, $parenDepth - 1);
            } elseif ($char === '[') {
                $bracketDepth++;
            } elseif ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
            } elseif ($char === ':' && $parenDepth === 0 && $bracketDepth === 0) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @return array{0:string,1:bool}
     */
    private function splitImportantFlag(string $value): array
    {
        if (preg_match('/^(.*?)\s*!\s*important\s*$/i', $value, $matches) === 1) {
            return [trim($matches[1]), true];
        }

        return [$value, false];
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool,drop:bool}> $entries
     */
    private function serializeDeclarationEntriesForComposition(array $entries): string
    {
        $parts = [];
        foreach ($entries as $entry) {
            if ($entry['drop']) {
                continue;
            }
            $parts[] = $entry['name'] . ':' . $entry['value'] . ($entry['important'] ? '!important' : '');
        }

        return implode(';', $parts);
    }

    /**
     * @param list<array{property:string,name:string,value:string,important:bool,drop:bool}> $entries
     */
    private function rewriteTransitionGroup(array &$entries, string $prefix): void
    {
        $properties = [
            'transition' => $prefix . 'transition',
            'property' => $prefix . 'transition-property',
            'duration' => $prefix . 'transition-duration',
            'timing' => $prefix . 'transition-timing-function',
            'delay' => $prefix . 'transition-delay',
            'behavior' => $prefix . 'transition-behavior',
        ];
        $relevantNames = array_flip($properties);
        $relevantIndices = [];
        $lastShorthand = null;

        foreach ($entries as $index => $entry) {
            if ($entry['drop'] || !isset($relevantNames[$entry['property']])) {
                continue;
            }
            if ($entry['important']) {
                return;
            }
            $relevantIndices[] = $index;
            if ($entry['property'] === $properties['transition']) {
                $lastShorthand = $index;
            }
        }

        if ($relevantIndices === []) {
            return;
        }

        if ($lastShorthand !== null) {
            foreach ($relevantIndices as $index) {
                if ($index < $lastShorthand) {
                    $entries[$index]['drop'] = true;
                }
            }

            $state = $this->parseTransitionShorthandComponents($entries[$lastShorthand]['value']);
            if ($state === null) {
                return;
            }

            $changed = false;
            foreach ($relevantIndices as $index) {
                if ($index <= $lastShorthand || $entries[$index]['drop']) {
                    continue;
                }
                $component = $relevantNames[$entries[$index]['property']];
                if ($component === 'transition') {
                    continue;
                }

                $values = $this->parseTransitionLonghandList($component, $entries[$index]['value']);
                if ($values === null) {
                    continue;
                }

                $state[$component] = $values;
                $entries[$index]['drop'] = true;
                $changed = true;
            }

            if ($changed) {
                $entries[$lastShorthand]['value'] = $this->serializeTransitionComponents($state);
            }

            return;
        }

        $latest = [];
        foreach ($relevantIndices as $index) {
            $component = $relevantNames[$entries[$index]['property']];
            if ($component !== 'transition') {
                $latest[$component] = $index;
            }
        }

        foreach (['property', 'duration', 'timing', 'delay'] as $required) {
            if (!isset($latest[$required])) {
                return;
            }
        }

        $state = [
            'property' => [],
            'duration' => [],
            'timing' => [],
            'delay' => [],
            'behavior' => ['normal'],
        ];
        foreach ($latest as $component => $index) {
            $values = $this->parseTransitionLonghandList($component, $entries[$index]['value']);
            if ($values === null) {
                return;
            }
            $state[$component] = $values;
        }

        $replaceAt = min(array_values($latest));
        foreach ($relevantIndices as $index) {
            $entries[$index]['drop'] = true;
        }

        $entries[$replaceAt] = [
            'property' => $properties['transition'],
            'name' => $properties['transition'],
            'value' => $this->serializeTransitionComponents($state),
            'important' => false,
            'drop' => false,
        ];
    }

    /**
     * @return array{property:list<string>,duration:list<string>,timing:list<string>,delay:list<string>,behavior:list<string>}|null
     */
    private function parseTransitionShorthandComponents(string $value): ?array
    {
        $state = [
            'property' => [],
            'duration' => [],
            'timing' => [],
            'delay' => [],
            'behavior' => [],
        ];

        foreach ($this->splitTopLevel($value, ',') as $layer) {
            $components = $this->parseTransitionLayerComponents($layer);
            if ($components === null) {
                return null;
            }
            foreach ($components as $component => $componentValue) {
                $state[$component][] = $componentValue;
            }
        }

        return $state;
    }

    /**
     * @return array{property:string,duration:string,timing:string,delay:string,behavior:string}|null
     */
    private function parseTransitionLayerComponents(string $layer): ?array
    {
        $property = null;
        $duration = null;
        $timing = null;
        $delay = null;
        $behavior = null;

        foreach ($this->splitWhitespaceTopLevel($layer) as $token) {
            if ($this->isTimeValue($token)) {
                if ($duration === null) {
                    $duration = $this->minifyTimeValue($token);
                    continue;
                }
                if ($delay === null) {
                    $delay = $this->minifyTimeValue($token);
                    continue;
                }

                return null;
            }

            if ($this->isTransitionTimingFunction($token)) {
                if ($timing !== null) {
                    return null;
                }
                $timing = $this->minifyTransitionTimingFunction($token);
                continue;
            }

            $lower = strtolower($token);
            if ($lower === 'normal' || $lower === 'allow-discrete') {
                if ($behavior !== null) {
                    return null;
                }
                $behavior = $lower;
                continue;
            }

            if ($property !== null) {
                return null;
            }
            $property = $token;
        }

        return [
            'property' => $property ?? 'all',
            'duration' => $duration ?? '0s',
            'timing' => $timing ?? 'ease',
            'delay' => $delay ?? '0s',
            'behavior' => $behavior ?? 'normal',
        ];
    }

    /**
     * @return list<string>|null
     */
    private function parseTransitionLonghandList(string $component, string $value): ?array
    {
        if ($component === 'behavior') {
            return $this->mapTransitionComponentList(
                $value,
                static function (string $part): ?string {
                    $part = strtolower(trim($part));

                    return $part === 'normal' || $part === 'allow-discrete' ? $part : null;
                }
            );
        }

        if ($component === 'duration' || $component === 'delay') {
            return $this->mapTransitionComponentList(
                $value,
                fn (string $part): ?string => $this->isTimeValue($part) ? $this->minifyTimeValue($part) : null
            );
        }

        if ($component === 'timing') {
            return $this->mapTransitionComponentList(
                $value,
                fn (string $part): ?string => $this->isTransitionTimingFunction($part) ? $this->minifyTransitionTimingFunction($part) : null
            );
        }

        if ($component === 'property') {
            return $this->mapTransitionComponentList(
                $value,
                function (string $part): ?string {
                    $part = trim($part);
                    if ($part === '') {
                        return null;
                    }

                    return implode(', ', $this->expandBlockAxisTransitionProperty($part));
                }
            );
        }

        return null;
    }

    /**
     * @return list<string>|null
     */
    private function mapTransitionComponentList(string $value, callable $mapper): ?array
    {
        $mapped = [];
        foreach ($this->splitTopLevel($value, ',') as $part) {
            $component = $mapper($part);
            if ($component === null) {
                return null;
            }
            $mapped[] = $component;
        }

        return $mapped === [] ? null : $mapped;
    }

    /**
     * @param array{property:list<string>,duration:list<string>,timing:list<string>,delay:list<string>,behavior:list<string>} $state
     */
    private function serializeTransitionComponents(array $state): string
    {
        $count = max(
            count($state['property']),
            count($state['duration']),
            count($state['timing']),
            count($state['delay']),
            count($state['behavior'])
        );
        $layers = [];

        for ($i = 0; $i < $count; $i++) {
            $property = $this->transitionComponentAt($state['property'], $i, 'all');
            $duration = $this->transitionComponentAt($state['duration'], $i, '0s');
            $timing = $this->transitionComponentAt($state['timing'], $i, 'ease');
            $delay = $this->transitionComponentAt($state['delay'], $i, '0s');
            $behavior = $this->transitionComponentAt($state['behavior'], $i, 'normal');

            $parts = [];
            if (strtolower($property) !== 'all') {
                $parts[] = $property;
            }

            $needsDuration = $duration !== '0s' || $timing !== 'ease' || $delay !== '0s' || $behavior !== 'normal';
            if ($needsDuration) {
                $parts[] = $duration;
            }
            if ($timing !== 'ease') {
                $parts[] = $timing;
            }
            if ($delay !== '0s') {
                if (!$needsDuration) {
                    $parts[] = '0s';
                }
                $parts[] = $delay;
            }
            if ($behavior !== 'normal') {
                $parts[] = $behavior;
            }

            $layers[] = $parts === [] ? 'all' : implode(' ', $parts);
        }

        return implode(',', $layers);
    }

    /**
     * @param list<string> $values
     */
    private function transitionComponentAt(array $values, int $index, string $default): string
    {
        if ($values === []) {
            return $default;
        }

        return $values[$index % count($values)];
    }

    /**
     * @return non-empty-list<string>
     */
    private function expandBlockAxisTransitionProperty(string $property): array
    {
        return match (strtolower($property)) {
            'margin-block' => ['margin-top', 'margin-bottom'],
            'margin-block-start' => ['margin-top'],
            'margin-block-end' => ['margin-bottom'],
            'padding-block' => ['padding-top', 'padding-bottom'],
            'padding-block-start' => ['padding-top'],
            'padding-block-end' => ['padding-bottom'],
            default => [$property],
        };
    }

    private function normalizeMathFunctionOperators(string $value): string
    {
        $output = '';
        $quote = null;
        $functionStack = [];
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($quote !== null) {
                $output .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $output .= $value[++$i];
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $output .= $char;
                continue;
            }

            if ($this->isIdentifierStart($char)) {
                $identifier = $this->readIdentifier($value, $i);
                $next = $i + strlen($identifier);
                if (($value[$next] ?? '') === '(') {
                    $parentIsMath = end($functionStack) === true;
                    $functionStack[] = $parentIsMath || in_array(strtolower($identifier), ['calc', 'clamp', 'max', 'min'], true);
                    $output .= $identifier . '(';
                    $i = $next;
                    continue;
                }
                $output .= $identifier;
                $i = $next - 1;
                continue;
            }

            if ($char === ')') {
                array_pop($functionStack);
                $output = rtrim($output) . ')';
                continue;
            }

            if (($char === '+' || $char === '-') && end($functionStack) === true && $this->isBinaryMathOperator($value, $i)) {
                $output = rtrim($output) . ' ' . $char . ' ';
                while (isset($value[$i + 1]) && ctype_space($value[$i + 1])) {
                    $i++;
                }
                continue;
            }

            $output .= $char;
        }

        return $output;
    }

    private function isBinaryMathOperator(string $value, int $offset): bool
    {
        $previous = $this->previousNonSpace($value, $offset - 1);
        $next = $this->nextNonSpace($value, $offset + 1);
        if ($previous === null || $next === null) {
            return false;
        }

        if ($this->isExponentSign($value, $offset)) {
            return false;
        }

        return preg_match('/[a-zA-Z0-9_%)]/', $previous) === 1
            && preg_match('/[a-zA-Z0-9_.(-]/', $next) === 1;
    }

    private function isExponentSign(string $value, int $offset): bool
    {
        $previous = $value[$offset - 1] ?? '';
        if ($previous !== 'e' && $previous !== 'E') {
            return false;
        }

        $beforeExponent = $value[$offset - 2] ?? '';
        $afterSign = $value[$offset + 1] ?? '';

        return ctype_digit($beforeExponent) && ctype_digit($afterSign);
    }

    private function previousNonSpace(string $value, int $offset): ?string
    {
        for ($i = $offset; $i >= 0; $i--) {
            if (!ctype_space($value[$i])) {
                return $value[$i];
            }
        }

        return null;
    }

    private function nextNonSpace(string $value, int $offset): ?string
    {
        $length = strlen($value);
        for ($i = $offset; $i < $length; $i++) {
            if (!ctype_space($value[$i])) {
                return $value[$i];
            }
        }

        return null;
    }

    private function minifyColorKeywords(string $value): string
    {
        $colors = [
            'aqua' => '#0ff',
            'black' => '#000',
            'blue' => '#00f',
            'chartreuse' => '#7fff00',
            'cyan' => '#0ff',
            'fuchsia' => '#f0f',
            'magenta' => '#f0f',
            'transparent' => '#0000',
            'white' => '#fff',
            'yellow' => '#ff0',
        ];
        $output = '';
        $quote = null;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($quote !== null) {
                $output .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $output .= $value[++$i];
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $output .= $char;
                continue;
            }

            if ($this->startsUrlFunction($value, $i)) {
                [$url, $offset] = $this->readFunctionRaw($value, $i);
                $output .= $url;
                $i = $offset;
                continue;
            }

            if ($this->isIdentifierStart($char)) {
                $identifier = $this->readIdentifier($value, $i);
                $lower = strtolower($identifier);
                $previous = $value[$i - 1] ?? '';
                $next = $value[$i + strlen($identifier)] ?? '';
                if ($previous === '-' || $next === '(') {
                    $output .= $identifier;
                    $i += strlen($identifier) - 1;
                    continue;
                }

                $output .= $colors[$lower] ?? $identifier;
                $i += strlen($identifier) - 1;
                continue;
            }

            $output .= $char;
        }

        return $output;
    }

    private function startsUrlFunction(string $value, int $offset): bool
    {
        if (strtolower(substr($value, $offset, 4)) !== 'url(') {
            return false;
        }

        $previous = $value[$offset - 1] ?? '';

        return $previous === '' || !$this->isIdentifierChar($previous);
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function readFunctionRaw(string $value, int $start): array
    {
        $output = '';
        $quote = null;
        $depth = 0;
        $length = strlen($value);

        for ($i = $start; $i < $length; $i++) {
            $char = $value[$i];
            $output .= $char;
            if ($quote !== null) {
                if ($char === '\\' && $i + 1 < $length) {
                    $output .= $value[++$i];
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return [$output, $i];
                }
            }
        }

        return [$output, $length - 1];
    }

    private function readIdentifier(string $value, int $start): string
    {
        $length = strlen($value);
        $identifier = '';
        for ($i = $start; $i < $length; $i++) {
            if (!$this->isIdentifierChar($value[$i])) {
                break;
            }
            $identifier .= $value[$i];
        }

        return $identifier;
    }

    private function isIdentifierStart(string $char): bool
    {
        return preg_match('/[a-zA-Z_]/', $char) === 1;
    }

    private function isIdentifierChar(string $char): bool
    {
        return preg_match('/[-a-zA-Z0-9_]/', $char) === 1;
    }
}
