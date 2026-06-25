<?php

declare(strict_types=1);

namespace PortLibs\Pandoc\PlainMath;

final class TexPreprocessor
{
    /**
     * @var array<string, array{args:int,default:?string,body:string}>
     */
    private array $macros = [];

    /**
     * @var array<string, array{args:int,default:?string,opener:string,closer:string}>
     */
    private array $environments = [];

    public function preprocess(string $source): ?string
    {
        $this->macros = [];
        $this->environments = [];

        $source = $this->stripIgnorable($source);
        $source = $this->extractOperatorDeclarations($source);
        $source = $this->extractEnvironmentDefinitions($source);
        $source = $this->extractMacroDefinitions($source);

        return $this->expandMacros($source);
    }

    private function stripIgnorable(string $source): string
    {
        $output = '';
        $offset = 0;
        $length = strlen($source);
        while ($offset < $length) {
            $char = $source[$offset];
            if ($char === '%' && !$this->charIsEscaped($source, $offset)) {
                while ($offset < $length && $source[$offset] !== "\n" && $source[$offset] !== "\r") {
                    $offset++;
                }
                if ($offset < $length) {
                    $output .= ' ';
                    $offset++;
                }
                continue;
            }

            if ($char !== '\\') {
                $output .= $char;
                $offset++;
                continue;
            }

            $commandStart = $offset;
            $offset++;
            $command = $this->readCommandName($source, $offset);
            if (in_array($command, ['notag', 'nonumber', 'allowbreak'], true)) {
                continue;
            }

            if ($command === 'label' || $command === 'tag') {
                $argumentOffset = $offset;
                if ($command === 'tag' && ($source[$argumentOffset] ?? '') === '*') {
                    $argumentOffset++;
                }
                $group = $this->readRawGroup($source, $argumentOffset);
                if ($group !== null) {
                    $offset = $argumentOffset;
                    continue;
                }
            }

            $output .= substr($source, $commandStart, $offset - $commandStart);
        }

        return $output;
    }

    private function extractOperatorDeclarations(string $source): string
    {
        $output = '';
        $offset = 0;
        $length = strlen($source);
        while ($offset < $length) {
            if ($source[$offset] !== '\\') {
                $output .= $source[$offset];
                $offset++;
                continue;
            }

            $commandStart = $offset;
            $offset++;
            $command = $this->readCommandName($source, $offset);
            if ($command !== 'DeclareMathOperator') {
                $output .= substr($source, $commandStart, $offset - $commandStart);
                continue;
            }

            $definitionOffset = $offset;
            $this->skipWhitespace($source, $definitionOffset);
            $star = '';
            if (($source[$definitionOffset] ?? '') === '*') {
                $star = '*';
                $definitionOffset++;
            }

            $name = $this->readMacroDefinitionName($source, $definitionOffset);
            $body = $this->readRawGroup($source, $definitionOffset);
            if ($name === null || $body === null) {
                $output .= substr($source, $commandStart, $offset - $commandStart);
                continue;
            }

            $this->macros[$name] = [
                'args' => 0,
                'default' => null,
                'body' => '\\operatorname' . $star . '{' . $body . '}',
            ];
            $offset = $definitionOffset;
        }

        return $output;
    }

    private function extractMacroDefinitions(string $source): string
    {
        $output = '';
        $offset = 0;
        $length = strlen($source);
        while ($offset < $length) {
            if ($source[$offset] !== '\\') {
                $output .= $source[$offset];
                $offset++;
                continue;
            }

            $commandStart = $offset;
            $offset++;
            $command = $this->readCommandName($source, $offset);
            if (!in_array($command, ['newcommand', 'renewcommand', 'providecommand'], true)) {
                $output .= substr($source, $commandStart, $offset - $commandStart);
                continue;
            }

            $definitionOffset = $offset;
            $this->skipWhitespace($source, $definitionOffset);
            if (($source[$definitionOffset] ?? '') === '*') {
                $definitionOffset++;
            }

            $name = $this->readMacroDefinitionName($source, $definitionOffset);
            if ($name === null) {
                $output .= substr($source, $commandStart, $offset - $commandStart);
                continue;
            }

            $argCountRaw = $this->readOptionalRawBracket($source, $definitionOffset);
            $argCount = is_string($argCountRaw) && preg_match('/^\s*[0-9]\s*$/', $argCountRaw) === 1
                ? (int) trim($argCountRaw)
                : 0;
            $defaultArgument = $argCount > 0 ? $this->readOptionalRawBracket($source, $definitionOffset) : null;
            $body = $this->readRawGroup($source, $definitionOffset);
            if ($body === null) {
                $output .= substr($source, $commandStart, $offset - $commandStart);
                continue;
            }

            if ($command !== 'providecommand' || !array_key_exists($name, $this->macros)) {
                $this->macros[$name] = [
                    'args' => $argCount,
                    'default' => $defaultArgument,
                    'body' => $body,
                ];
            }
            $offset = $definitionOffset;
        }

        return $output;
    }

    private function extractEnvironmentDefinitions(string $source): string
    {
        $output = '';
        $offset = 0;
        $length = strlen($source);
        while ($offset < $length) {
            if ($source[$offset] !== '\\') {
                $output .= $source[$offset];
                $offset++;
                continue;
            }

            $definition = $this->readEnvironmentDefinitionAt($source, $offset);
            if ($definition === null) {
                $output .= $source[$offset];
                $offset++;
                continue;
            }

            $this->environments[$definition['name']] = [
                'args' => $definition['args'],
                'default' => $definition['default'],
                'opener' => $definition['opener'],
                'closer' => $definition['closer'],
            ];
            $offset = $definition['next'];
        }

        return $output;
    }

    /**
     * @return array{name:string,args:int,default:?string,opener:string,closer:string,next:int}|null
     */
    private function readEnvironmentDefinitionAt(string $source, int $offset): ?array
    {
        if (($source[$offset] ?? '') !== '\\') {
            return null;
        }

        $cursor = $offset + 1;
        $command = $this->readCommandName($source, $cursor);
        if (!in_array($command, ['newenvironment', 'renewenvironment'], true)) {
            return null;
        }

        $this->skipWhitespace($source, $cursor);
        if (($source[$cursor] ?? '') === '*') {
            $cursor++;
        }

        $name = $this->readRawGroup($source, $cursor);
        if (!is_string($name)) {
            return null;
        }
        $name = trim($name);
        if (preg_match('/^[A-Za-z][A-Za-z0-9*_-]*$/', $name) !== 1) {
            return null;
        }

        $argCountRaw = $this->readOptionalRawBracket($source, $cursor);
        $argCount = is_string($argCountRaw) && preg_match('/^\s*[0-9]\s*$/', $argCountRaw) === 1
            ? (int) trim($argCountRaw)
            : 0;
        $defaultArgument = $argCount > 0 ? $this->readOptionalRawBracket($source, $cursor) : null;

        $opener = $this->readRawGroup($source, $cursor);
        $closer = $this->readRawGroup($source, $cursor);
        if ($opener === null || $closer === null) {
            return null;
        }

        return [
            'name' => $name,
            'args' => $argCount,
            'default' => $defaultArgument,
            'opener' => $opener,
            'closer' => $closer,
            'next' => $cursor,
        ];
    }

    private function expandMacros(string $source): ?string
    {
        if ($this->macros === [] && $this->environments === []) {
            return $source;
        }

        $limit = (2 * (count($this->macros) + count($this->environments))) + 1;
        for ($iteration = 0; $iteration < $limit; $iteration++) {
            $changed = false;
            $output = '';
            $offset = 0;
            $length = strlen($source);
            while ($offset < $length) {
                if (($source[$offset] ?? '') === '\\') {
                    $beginOffset = $offset + 1;
                    $beginCommand = $this->readCommandName($source, $beginOffset);
                    if ($beginCommand === 'begin') {
                        $expandedEnvironment = $this->expandEnvironmentInvocation($source, $beginOffset);
                        if ($expandedEnvironment === false) {
                            return null;
                        }
                        if ($expandedEnvironment !== null) {
                            $output .= $expandedEnvironment['tex'];
                            $offset = $expandedEnvironment['next'];
                            $changed = true;
                            continue;
                        }
                    }
                }

                if ($source[$offset] !== '\\') {
                    $output .= $source[$offset];
                    $offset++;
                    continue;
                }

                $commandStart = $offset;
                $offset++;
                $command = $this->readCommandName($source, $offset);
                $macro = $this->macros[$command] ?? null;
                if ($macro === null) {
                    $output .= substr($source, $commandStart, $offset - $commandStart);
                    continue;
                }

                $argumentOffset = $offset;
                $arguments = [];
                $valid = true;
                $firstRequiredArgument = 1;
                if ($macro['default'] !== null && $macro['args'] > 0) {
                    $optional = $this->readOptionalRawBracket($source, $argumentOffset);
                    $arguments[] = $optional ?? $macro['default'];
                    $firstRequiredArgument = 2;
                }
                for ($argumentIndex = $firstRequiredArgument; $argumentIndex <= $macro['args']; $argumentIndex++) {
                    $argument = $this->readRawArgument($source, $argumentOffset);
                    if ($argument === null) {
                        $valid = false;
                        break;
                    }
                    $arguments[] = $argument;
                }

                if (!$valid) {
                    $output .= substr($source, $commandStart, $offset - $commandStart);
                    continue;
                }

                $output .= $this->applyMacroBody($macro['body'], $arguments);
                $offset = $argumentOffset;
                $changed = true;
            }

            $source = $output;
            if (!$changed) {
                return $source;
            }
        }

        return null;
    }

    /**
     * @return array{tex:string,next:int}|false|null
     */
    private function expandEnvironmentInvocation(string $source, int $offset): array|false|null
    {
        if ($this->environments === []) {
            return null;
        }

        $cursor = $offset;
        $name = $this->readRawGroup($source, $cursor);
        if (!is_string($name)) {
            return null;
        }
        $name = trim($name);
        if (!isset($this->environments[$name])) {
            return null;
        }

        $environment = $this->environments[$name];
        $arguments = [];
        $firstRequiredArgument = 1;
        if ($environment['default'] !== null && $environment['args'] > 0) {
            $optional = $this->readOptionalRawBracket($source, $cursor);
            $arguments[] = $optional ?? $environment['default'];
            $firstRequiredArgument = 2;
        }
        for ($argumentIndex = $firstRequiredArgument; $argumentIndex <= $environment['args']; $argumentIndex++) {
            $argument = $this->readRawGroup($source, $cursor);
            if ($argument === null) {
                return false;
            }
            $arguments[] = $argument;
        }

        $bodyOffset = $cursor;
        $body = $this->readEnvironmentBody($source, $bodyOffset, $name);
        if ($body === null) {
            return false;
        }

        return [
            'tex' => $this->applyMacroBody($environment['opener'] . $body . $environment['closer'], $arguments),
            'next' => $bodyOffset,
        ];
    }

    private function readEnvironmentBody(string $source, int &$offset, string $environment): ?string
    {
        $start = $offset;
        $cursor = $offset;
        $depth = 1;
        $length = strlen($source);
        while ($cursor < $length) {
            if ($source[$cursor] !== '\\') {
                $cursor++;
                continue;
            }

            $commandStart = $cursor;
            $cursor++;
            $command = $this->readCommandName($source, $cursor);
            if ($command !== 'begin' && $command !== 'end') {
                continue;
            }

            $environmentOffset = $cursor;
            $name = $this->readRawGroup($source, $environmentOffset);
            if ($name !== $environment) {
                continue;
            }

            if ($command === 'begin') {
                $depth++;
                $cursor = $environmentOffset;
                continue;
            }

            $depth--;
            if ($depth === 0) {
                $body = substr($source, $start, $commandStart - $start);
                $offset = $environmentOffset;
                return $body;
            }

            $cursor = $environmentOffset;
        }

        return null;
    }

    private function readMacroDefinitionName(string $source, int &$offset): ?string
    {
        $this->skipWhitespace($source, $offset);
        if (($source[$offset] ?? '') === '{') {
            $name = $this->readRawGroup($source, $offset);
            if (!is_string($name) || !str_starts_with($name, '\\')) {
                return null;
            }

            return substr($name, 1);
        }

        if (($source[$offset] ?? '') !== '\\') {
            return null;
        }

        $offset++;
        $name = $this->readCommandName($source, $offset);

        return $name === '' ? null : $name;
    }

    private function readRawArgument(string $source, int &$offset): ?string
    {
        $this->skipWhitespace($source, $offset);
        $char = $source[$offset] ?? '';
        if ($char === '') {
            return null;
        }

        if ($char === '{') {
            return $this->readRawGroup($source, $offset);
        }

        if ($char === '\\') {
            $offset++;
            return '\\' . $this->readCommandName($source, $offset);
        }

        $offset++;
        return $char;
    }

    private function readRawGroup(string $source, int &$offset): ?string
    {
        $this->skipWhitespace($source, $offset);
        if (($source[$offset] ?? '') !== '{') {
            return null;
        }

        $offset++;
        $start = $offset;
        $depth = 1;
        $length = strlen($source);
        while ($offset < $length && $depth > 0) {
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

        return null;
    }

    private function readOptionalRawBracket(string $source, int &$offset): ?string
    {
        $this->skipWhitespace($source, $offset);
        if (($source[$offset] ?? '') !== '[') {
            return null;
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
            if ($char === '[') {
                $depth++;
            } elseif ($char === ']') {
                $depth--;
                if ($depth === 0) {
                    $text = substr($source, $start, $offset - $start);
                    $offset++;
                    return $text;
                }
            }
            $offset++;
        }

        return null;
    }

    /**
     * @param list<string> $arguments
     */
    private function applyMacroBody(string $body, array $arguments): string
    {
        $output = '';
        $length = strlen($body);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $body[$offset];
            if ($char === '\\' && ($body[$offset + 1] ?? '') === '#') {
                $output .= '\\#';
                $offset++;
                continue;
            }
            if ($char === '#' && ctype_digit($body[$offset + 1] ?? '') && $body[$offset + 1] !== '0') {
                $argumentIndex = (int) $body[$offset + 1] - 1;
                $output .= $arguments[$argumentIndex] ?? '#' . $body[$offset + 1];
                $offset++;
                continue;
            }

            $output .= $char;
        }

        return $output;
    }

    private function skipWhitespace(string $source, int &$offset): void
    {
        $length = strlen($source);
        while ($offset < $length && ctype_space($source[$offset])) {
            $offset++;
        }
    }

    private function readCommandName(string $source, int &$offset): string
    {
        $length = strlen($source);
        $start = $offset;
        while ($offset < $length && ctype_alpha($source[$offset])) {
            $offset++;
        }
        if ($offset > $start) {
            return substr($source, $start, $offset - $start);
        }

        $command = $source[$offset] ?? '';
        if ($command !== '') {
            $offset++;
        }

        return $command;
    }

    private function charIsEscaped(string $source, int $offset): bool
    {
        $slashes = 0;
        for ($cursor = $offset - 1; $cursor >= 0 && $source[$cursor] === '\\'; $cursor--) {
            $slashes++;
        }

        return $slashes % 2 === 1;
    }
}
