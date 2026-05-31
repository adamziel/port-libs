<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitAttributes
{
    public const STATE_SET = 'set';
    public const STATE_UNSET = 'unset';
    public const STATE_UNSPECIFIED = 'unspecified';
    public const STATE_VALUE = 'value';

    /**
     * @param list<array{pattern:array{text:string,absolute:bool,mustBeDirectory:bool,noSubDirectory:bool},assignments:list<array{name:string,state:string,value:?string}>,line:int,base:string}> $rules
     * @param array<string, list<array{name:string,state:string,value:?string}>> $macros
     */
    private function __construct(
        private readonly array $rules,
        private readonly array $macros,
    ) {
    }

    public static function fromString(string $contents, string $baseDirectory = '', bool $withBuiltInMacros = true): self
    {
        $macros = $withBuiltInMacros ? [
            'binary' => [
                ['name' => 'diff', 'state' => self::STATE_UNSET, 'value' => null],
                ['name' => 'merge', 'state' => self::STATE_UNSET, 'value' => null],
                ['name' => 'text', 'state' => self::STATE_UNSET, 'value' => null],
            ],
        ] : [];
        $rules = [];
        $baseDirectory = self::normalizePath($baseDirectory);
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        foreach (preg_split('/\r\n|\n|\r/', $contents) ?: [] as $lineNumber => $line) {
            $parsed = self::parseLine($line, $lineNumber + 1);
            if ($parsed === null) {
                continue;
            }

            if ($parsed['macro'] !== null) {
                $macros[$parsed['macro']] = $parsed['assignments'];
                continue;
            }

            $rules[] = [
                'pattern' => $parsed['pattern'],
                'assignments' => $parsed['assignments'],
                'line' => $lineNumber + 1,
                'base' => $baseDirectory,
            ];
        }

        return new self($rules, $macros);
    }

    /**
     * @param list<string> $selected
     * @return array<string, bool|string|null>
     */
    public function attributesForPath(
        string $path,
        array $selected = [],
        ?bool $isDirectory = null,
        bool $ignoreCase = false,
    ): array {
        return $this->resolveAttributesForPath($path, $selected, $isDirectory, $ignoreCase)['states'];
    }

    /**
     * @param list<array{name:string,state:string,value:?string}> $requirements
     */
    public function matchesRequirements(string $path, array $requirements, ?bool $isDirectory = null): bool
    {
        $selected = array_values(array_unique(array_map(
            static fn (array $requirement): string => $requirement['name'],
            $requirements,
        )));
        $result = $this->resolveAttributesForPath($path, $selected, $isDirectory, false);
        if (!$result['matchedSelected']) {
            return false;
        }

        foreach ($requirements as $requirement) {
            $actual = $result['states'][$requirement['name']] ?? null;
            $matches = match ($requirement['state']) {
                self::STATE_SET => $actual === true,
                self::STATE_UNSET => $actual === false,
                self::STATE_UNSPECIFIED => $actual === null,
                self::STATE_VALUE => $actual === $requirement['value'],
                default => false,
            };
            if (!$matches) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $selected
     * @return array{states:array<string, bool|string|null>,matchedSelected:bool}
     */
    private function resolveAttributesForPath(
        string $path,
        array $selected = [],
        ?bool $isDirectory = null,
        bool $ignoreCase = false,
    ): array {
        $path = self::normalizePath($path);
        $selectedSet = [];
        foreach ($selected as $name) {
            $selectedSet[$name] = true;
        }

        $states = [];
        if ($selected !== []) {
            foreach ($selected as $name) {
                $states[$name] = null;
            }
        }
        $matchedSelected = false;

        foreach ($this->rules as $rule) {
            if (!self::patternMatches($rule['pattern'], $path, $isDirectory, $ignoreCase, $rule['base'])) {
                continue;
            }
            foreach ($this->expandAssignments($rule['assignments']) as $assignment) {
                if ($selectedSet !== [] && !isset($selectedSet[$assignment['name']])) {
                    continue;
                }
                $states[$assignment['name']] = match ($assignment['state']) {
                    self::STATE_SET => true,
                    self::STATE_UNSET => false,
                    self::STATE_VALUE => (string) $assignment['value'],
                    self::STATE_UNSPECIFIED => null,
                    default => throw new \LogicException('Unknown attribute state'),
                };
                $matchedSelected = true;
            }
        }

        ksort($states, SORT_STRING);

        return ['states' => $states, 'matchedSelected' => $matchedSelected];
    }

    /**
     * @return list<array{name:string,state:string,value:?string}>
     */
    public static function parseRequirements(string $input): array
    {
        $assignments = self::parseAssignments(self::normalizeRequirementValues($input), false);
        if ($assignments === null || $assignments === []) {
            throw new \InvalidArgumentException('Attribute specification cannot be empty');
        }

        return $assignments;
    }

    /**
     * @return array{macro:?string,pattern:array{text:string,absolute:bool,mustBeDirectory:bool,noSubDirectory:bool},assignments:list<array{name:string,state:string,value:?string}>}|null
     */
    private static function parseLine(string $line, int $lineNumber): ?array
    {
        $line = ltrim($line, " \t\r");
        if ($line === '' || str_starts_with($line, '#')) {
            return null;
        }

        if ($line[0] === '"') {
            $quoted = self::consumeQuotedPattern($line);
            if ($quoted === null) {
                return null;
            }
            [$patternText, $rest] = $quoted;
        } else {
            $split = strcspn($line, " \t\r");
            $patternText = substr($line, 0, $split);
            $rest = substr($line, $split);
        }

        if ($patternText === '') {
            return null;
        }

        $assignments = self::parseAssignments($rest, false);
        if ($assignments === null) {
            return null;
        }

        if (str_starts_with($patternText, '[attr]')) {
            $macro = substr($patternText, 6);
            if (!self::validAttributeName($macro)) {
                return null;
            }

            return [
                'macro' => $macro,
                'pattern' => ['text' => '', 'absolute' => false, 'mustBeDirectory' => false, 'noSubDirectory' => true],
                'assignments' => $assignments,
            ];
        }

        $pattern = self::parsePattern($patternText);
        if ($pattern === null) {
            return null;
        }

        return [
            'macro' => null,
            'pattern' => $pattern,
            'assignments' => $assignments,
        ];
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private static function consumeQuotedPattern(string $line): ?array
    {
        $out = '';
        $length = strlen($line);
        for ($i = 1; $i < $length; $i++) {
            $char = $line[$i];
            if ($char === '"') {
                return [$out, substr($line, $i + 1)];
            }
            if ($char !== '\\') {
                $out .= $char;
                continue;
            }
            if ($i + 1 >= $length) {
                return null;
            }
            $next = $line[++$i];
            $out .= match ($next) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\x08",
                'a' => "\x07",
                'v' => "\x0b",
                default => $next,
            };
        }

        return null;
    }

    /**
     * @return array{text:string,absolute:bool,mustBeDirectory:bool,noSubDirectory:bool}|null
     */
    private static function parsePattern(string $pattern): ?array
    {
        if (str_starts_with($pattern, '\\!') || str_starts_with($pattern, '\\#')) {
            $pattern = substr($pattern, 1);
        } elseif (str_starts_with($pattern, '!')) {
            return null;
        }

        if (trim($pattern) === '') {
            return null;
        }

        $absolute = str_starts_with($pattern, '/');
        if ($absolute) {
            $pattern = substr($pattern, 1);
        }

        $mustBeDirectory = str_ends_with($pattern, '/');
        if ($mustBeDirectory) {
            $pattern = substr($pattern, 0, -1);
        }

        if ($pattern === '') {
            return null;
        }

        return [
            'text' => $pattern,
            'absolute' => $absolute,
            'mustBeDirectory' => $mustBeDirectory,
            'noSubDirectory' => !str_contains($pattern, '/'),
        ];
    }

    /**
     * @return list<array{name:string,state:string,value:?string}>|null
     */
    private static function parseAssignments(string $input, bool $strictValues): ?array
    {
        $assignments = [];
        foreach (preg_split('/[ \t\r]+/', trim($input)) ?: [] as $field) {
            if ($field === '') {
                continue;
            }
            $assignment = self::parseAssignment($field, $strictValues);
            if ($assignment === null) {
                return null;
            }
            $assignments[] = $assignment;
        }

        return $assignments;
    }

    /**
     * @return array{name:string,state:string,value:?string}|null
     */
    private static function parseAssignment(string $field, bool $strictValues): ?array
    {
        $state = self::STATE_SET;
        $value = null;
        $name = $field;
        $rawValue = null;

        if (str_contains($field, '=')) {
            [$name, $rawValue] = explode('=', $field, 2);
            if ($strictValues) {
                $value = self::unescapeRequirementValue($rawValue);
            } else {
                $value = $rawValue;
            }
        }

        if (str_starts_with($name, '-')) {
            $state = self::STATE_UNSET;
            $name = substr($name, 1);
            $value = null;
        } elseif (str_starts_with($name, '!')) {
            $state = self::STATE_UNSPECIFIED;
            $name = substr($name, 1);
            $value = null;
        } elseif ($rawValue !== null) {
            $state = self::STATE_VALUE;
        }

        if (!self::validAttributeName($name)) {
            return null;
        }

        return ['name' => $name, 'state' => $state, 'value' => $value];
    }

    private static function unescapeRequirementValue(string $value): ?string
    {
        $out = '';
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($char === '\\') {
                if ($i + 1 >= $length) {
                    throw new \InvalidArgumentException("Escape character '\\' is not allowed as the last character in an attribute value");
                }
                $char = $value[++$i];
            }
            if (!preg_match('/^[A-Za-z0-9,_-]$/', $char)) {
                throw new \InvalidArgumentException("Invalid character in attribute value: {$char}");
            }
            $out .= $char;
        }

        return $out;
    }

    private static function normalizeRequirementValues(string $input): string
    {
        if (!str_contains($input, '=')) {
            return $input;
        }

        $normalized = '';
        foreach (explode(' ', $input) as $field) {
            $equals = strpos($field, '=');
            if ($equals === false) {
                $normalized .= $field . ' ';
                continue;
            }

            $name = substr($field, 0, $equals + 1);
            $value = substr($field, $equals + 1);
            $normalized .= $name . self::unescapeRequirementValue($value) . ' ';
        }

        return $normalized;
    }

    private static function validAttributeName(string $name): bool
    {
        return $name !== '' && $name[0] !== '-' && preg_match('/^[A-Za-z0-9_.-]+$/', $name) === 1;
    }

    /**
     * @param list<array{name:string,state:string,value:?string}> $assignments
     * @return list<array{name:string,state:string,value:?string}>
     */
    private function expandAssignments(array $assignments, int $depth = 0): array
    {
        if ($depth > 8) {
            return $assignments;
        }

        $expanded = [];
        foreach ($assignments as $assignment) {
            if ($assignment['state'] === self::STATE_SET && isset($this->macros[$assignment['name']])) {
                array_push($expanded, ...$this->expandAssignments($this->macros[$assignment['name']], $depth + 1));
            }
            $expanded[] = $assignment;
        }

        return $expanded;
    }

    /**
     * @param array{text:string,absolute:bool,mustBeDirectory:bool,noSubDirectory:bool} $pattern
     */
    private static function patternMatches(
        array $pattern,
        string $path,
        ?bool $isDirectory,
        bool $ignoreCase,
        string $baseDirectory,
    ): bool {
        if ($baseDirectory !== '') {
            $base = $baseDirectory . '/';
            $prefix = substr($path, 0, strlen($base));
            $prefixMatches = $ignoreCase ? strcasecmp($prefix, $base) === 0 : $prefix === $base;
            if (!$prefixMatches) {
                return false;
            }
            $path = substr($path, strlen($base));
        }

        if ($path === '') {
            return false;
        }
        if ($pattern['mustBeDirectory'] && $isDirectory === false) {
            return false;
        }

        $candidate = $pattern['noSubDirectory'] && !$pattern['absolute'] ? basename($path) : $path;
        $regex = self::globRegex($pattern['text'], true, $ignoreCase);

        return preg_match($regex, $candidate) === 1;
    }

    public static function globMatches(string $pattern, string $path, bool $pathAware = true, bool $ignoreCase = false): bool
    {
        return preg_match(self::globRegex($pattern, $pathAware, $ignoreCase), $path) === 1;
    }

    private static function globRegex(string $pattern, bool $pathAware, bool $ignoreCase = false): string
    {
        $regex = '';
        $length = strlen($pattern);
        for ($i = 0; $i < $length; $i++) {
            $char = $pattern[$i];
            if ($char === '\\') {
                if ($i + 1 < $length) {
                    $regex .= preg_quote($pattern[++$i], '~');
                } else {
                    $regex .= preg_quote($char, '~');
                }
                continue;
            }
            if ($char === '*') {
                if (($pattern[$i + 1] ?? '') === '*') {
                    $next = $pattern[$i + 2] ?? '';
                    if ($next === '/') {
                        $regex .= '(?:.*/)?';
                        $i += 2;
                    } else {
                        $regex .= '.*';
                        $i++;
                    }
                } else {
                    $regex .= $pathAware ? '[^/]*' : '.*';
                }
                continue;
            }
            if ($char === '?') {
                $regex .= $pathAware ? '[^/]' : '.';
                continue;
            }
            if ($char === '[') {
                $end = self::findCharacterClassEnd($pattern, $i);
                if ($end !== null) {
                    $regex .= ($pathAware ? '(?!/)' : '')
                        . self::characterClassRegex(substr($pattern, $i + 1, $end - $i - 1));
                    $i = $end;
                    continue;
                }
            }
            $regex .= preg_quote($char, '~');
        }

        return '~^' . $regex . '$~' . ($ignoreCase ? 'i' : '');
    }

    private static function findCharacterClassEnd(string $pattern, int $start): ?int
    {
        $length = strlen($pattern);
        $cursor = $start + 1;
        if ($cursor >= $length) {
            return null;
        }
        if (($pattern[$cursor] ?? '') === '!' || ($pattern[$cursor] ?? '') === '^') {
            $cursor++;
        }
        if (($pattern[$cursor] ?? '') === ']') {
            $cursor++;
        }

        for (; $cursor < $length; $cursor++) {
            $char = $pattern[$cursor];
            if ($char === '\\') {
                $cursor++;
                continue;
            }
            if ($char === '[' && ($pattern[$cursor + 1] ?? '') === ':') {
                $classEnd = strpos($pattern, ':]', $cursor + 2);
                if ($classEnd !== false) {
                    $cursor = $classEnd + 1;
                    continue;
                }
            }
            if ($char === ']') {
                return $cursor;
            }
        }

        return null;
    }

    private static function characterClassRegex(string $class): string
    {
        if ($class === '') {
            return preg_quote('[]', '~');
        }

        $negated = false;
        if ($class[0] === '!' || $class[0] === '^') {
            $negated = true;
            $class = substr($class, 1);
        }

        $body = '';
        $length = strlen($class);
        for ($i = 0; $i < $length; $i++) {
            $char = $class[$i];
            if ($char === '\\') {
                if ($i + 1 < $length) {
                    $body .= self::escapeCharacterClassByte($class[++$i]);
                } else {
                    $body .= '\\\\';
                }
                continue;
            }
            if ($char === '[' && ($class[$i + 1] ?? '') === ':') {
                $end = strpos($class, ':]', $i + 2);
                if ($end !== false) {
                    $name = substr($class, $i + 2, $end - $i - 2);
                    $mapped = self::posixCharacterClassRegex($name);
                    if ($mapped === null) {
                        return '(?!)';
                    }
                    $body .= $mapped;
                    $i = $end + 1;
                    continue;
                }
            }
            $body .= self::escapeCharacterClassByte($char);
        }

        if ($body === '') {
            return preg_quote('[]', '~');
        }

        return '[' . ($negated ? '^' : '') . $body . ']';
    }

    private static function escapeCharacterClassByte(string $char): string
    {
        return match ($char) {
            '\\' => '\\\\',
            ']' => '\\]',
            '~' => '\\~',
            default => $char,
        };
    }

    private static function posixCharacterClassRegex(string $class): ?string
    {
        return match ($class) {
            'alnum' => 'A-Za-z0-9',
            'alpha' => 'A-Za-z',
            'blank' => '\\x09-\\x0d ',
            'cntrl' => '\\x00-\\x1f\\x7f',
            'digit' => '0-9',
            'graph' => '\\x21-\\x7e',
            'lower' => 'a-z',
            'print' => '\\x20-\\x7e',
            'punct' => '\\x21-\\x2f\\x3a-\\x40\\x5b-\\x60\\x7b-\\x7e',
            'space' => ' ',
            'upper' => 'A-Z',
            'xdigit' => 'A-Fa-f0-9',
            default => null,
        };
    }

    private static function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        if (str_contains($path, "\0")) {
            throw new \InvalidArgumentException('Git attribute paths cannot contain NUL bytes');
        }

        $parts = [];
        foreach (explode('/', trim($path, '/')) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }
}
