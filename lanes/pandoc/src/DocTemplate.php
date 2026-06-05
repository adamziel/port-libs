<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DocTemplate
{
    private const MAX_PARTIAL_DEPTH = 50;

    /**
     * @param array<string, mixed> $context
     * @param array<string, string> $partials
     */
    public function render(string $template, array $context, array $partials = []): string
    {
        return $this->renderTemplate($template, $context, $this->normalizePartialMap($partials), []);
    }

    /**
     * @param array<string, string> $resources
     * @param array<string, mixed> $context
     */
    public function renderResource(string $templatePath, array $resources, array $context, ?string $userDataDirectory = null): string
    {
        $templatePath = $this->normalizeTemplateResourcePath($templatePath);
        $resources = $this->normalizeTemplateResourceMap($resources);
        if (!array_key_exists($templatePath, $resources)) {
            throw new \UnexpectedValueException("Missing doctemplate resource {$templatePath}");
        }

        return $this->render(
            $resources[$templatePath],
            $context,
            $this->partialsForTemplateResource($templatePath, $resources, $userDataDirectory),
        );
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, string> $partials
     * @param list<string> $partialStack
     */
    private function renderTemplate(string $template, array $context, array $partials, array $partialStack): string
    {
        $tokens = $this->tokenize($template);

        return $this->renderRange($tokens, 0, count($tokens), $context, $partials, $partialStack);
    }

    /**
     * @param array<string, string> $resources
     * @return array<string, string>
     */
    private function normalizeTemplateResourceMap(array $resources): array
    {
        $normalized = [];
        foreach ($resources as $path => $source) {
            if (!is_string($path)) {
                throw new \InvalidArgumentException('Doctemplate resource paths must be strings');
            }

            if (!is_string($source)) {
                throw new \InvalidArgumentException("Doctemplate resource {$path} must be a string");
            }

            $normalized[$this->normalizeTemplateResourcePath($path)] = $source;
        }

        return $normalized;
    }

    /**
     * @param array<string, string> $partials
     * @return array<string, string>
     */
    private function normalizePartialMap(array $partials): array
    {
        $normalized = [];
        foreach ($partials as $name => $source) {
            if (!is_string($name)) {
                throw new \InvalidArgumentException('Doctemplate partial names must be strings');
            }

            if (!is_string($source)) {
                throw new \InvalidArgumentException("Doctemplate partial {$name} must be a string");
            }

            $normalized[$this->normalizePartialName($name)] = $source;
        }

        return $normalized;
    }

    private function normalizeTemplateResourcePath(string $path): string
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw new \InvalidArgumentException('Invalid doctemplate resource path');
        }

        $path = str_replace('\\', '/', $path);
        $absolute = str_starts_with($path, '/');
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new \InvalidArgumentException('Doctemplate resource paths must not contain parent-directory segments');
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            throw new \InvalidArgumentException('Invalid doctemplate resource path');
        }

        return ($absolute ? '/' : '') . implode('/', $segments);
    }

    private function normalizePartialName(string $name): string
    {
        if ($name === '' || str_contains($name, "\0")) {
            throw new \InvalidArgumentException('Invalid doctemplate partial name');
        }

        $name = str_replace('\\', '/', $name);
        if (str_starts_with($name, '/')) {
            throw new \InvalidArgumentException('Doctemplate partial names must be relative paths');
        }

        $segments = [];
        foreach (explode('/', $name) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException('Doctemplate partial names must not contain empty, current-directory, or parent-directory segments');
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    /**
     * @param array<string, string> $resources
     * @return array<string, string>
     */
    private function partialsForTemplateResource(string $templatePath, array $resources, ?string $userDataDirectory): array
    {
        $mainDirectory = $this->templateResourceDirectory($templatePath);
        $mainExtension = $this->templateResourceExtension($this->templateResourceBasename($templatePath));
        $searchDirectories = [$mainDirectory];
        if ($userDataDirectory !== null && !$this->isAbsoluteTemplateResourcePath($templatePath)) {
            $searchDirectories[] = $this->joinTemplateResourcePath(
                $this->normalizeTemplateResourcePath($userDataDirectory),
                'templates',
            );
        }

        $partials = [];
        foreach ($searchDirectories as $directory) {
            foreach ($resources as $resourcePath => $source) {
                if ($resourcePath === $templatePath) {
                    continue;
                }

                $relativePath = $this->relativeTemplateResourceChild($resourcePath, $directory);
                if ($relativePath === null) {
                    continue;
                }

                foreach ($this->partialAliasesForResourcePath($relativePath, $mainExtension) as $alias) {
                    if (!array_key_exists($alias, $partials)) {
                        $partials[$alias] = $source;
                    }
                }
            }
        }

        return $partials;
    }

    private function isAbsoluteTemplateResourcePath(string $path): bool
    {
        return str_starts_with($path, '/');
    }

    private function templateResourceDirectory(string $path): string
    {
        $slash = strrpos($path, '/');
        if ($slash === false) {
            return '';
        }

        if ($slash === 0) {
            return '/';
        }

        return substr($path, 0, $slash);
    }

    private function templateResourceBasename(string $path): string
    {
        $slash = strrpos($path, '/');

        return $slash === false ? $path : substr($path, $slash + 1);
    }

    private function templateResourceExtension(string $basename): string
    {
        $dot = strrpos($basename, '.');
        if ($dot === false || $dot === 0) {
            return '';
        }

        return substr($basename, $dot);
    }

    private function joinTemplateResourcePath(string $directory, string $basename): string
    {
        if ($directory === '') {
            return $basename;
        }

        if ($directory === '/') {
            return '/' . $basename;
        }

        return $directory . '/' . $basename;
    }

    private function relativeTemplateResourceChild(string $path, string $directory): ?string
    {
        if ($directory === '') {
            return $this->isAbsoluteTemplateResourcePath($path) ? null : $path;
        }

        if ($directory === '/') {
            if (!str_starts_with($path, '/')) {
                return null;
            }

            $relative = substr($path, 1);

            return $relative !== '' ? $relative : null;
        }

        $prefix = $directory . '/';
        if (!str_starts_with($path, $prefix)) {
            return null;
        }

        $relative = substr($path, strlen($prefix));

        return $relative !== '' ? $relative : null;
    }

    /**
     * @return list<string>
     */
    private function partialAliasesForResourcePath(string $relativePath, string $mainExtension): array
    {
        $basename = $this->templateResourceBasename($relativePath);
        $extension = $this->templateResourceExtension($basename);
        if ($extension === '') {
            return $mainExtension === '' ? [$relativePath] : [];
        }

        $aliases = [$relativePath];
        if ($extension === $mainExtension) {
            $aliases[] = substr($relativePath, 0, -strlen($extension));
        }

        return $aliases;
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
                $closing = $this->findBracedDirectiveClosing($template, $index + 2);
                if ($closing === null) {
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

    private function findBracedDirectiveClosing(string $template, int $start): ?int
    {
        $inQuote = false;
        $escape = false;
        $length = strlen($template);

        for ($index = $start; $index < $length; $index++) {
            $char = $template[$index];
            if ($escape) {
                $escape = false;
                continue;
            }

            if ($inQuote && $char === '\\') {
                $escape = true;
                continue;
            }

            if ($char === '"') {
                $inQuote = !$inQuote;
                continue;
            }

            if (!$inQuote && $char === '}') {
                return $index;
            }
        }

        return null;
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
     * @param array<string, string> $partials
     * @param list<string> $partialStack
     */
    private function renderRange(array $tokens, int $start, int $end, array $context, array $partials, array $partialStack): string
    {
        $output = '';
        $pendingNestColumn = null;

        for ($index = $start; $index < $end; $index++) {
            $token = $tokens[$index];
            if ($token['type'] === 'text') {
                $this->appendRenderedChunk($output, $token['value'], $pendingNestColumn);
                continue;
            }

            $directive = $token['value'];
            if ($directive === '~') {
                continue;
            }

            if ($directive === '^') {
                $pendingNestColumn = $this->currentColumn($output);
                continue;
            }

            $ifVariable = $this->controlVariable($directive, 'if');
            if ($ifVariable !== null) {
                [$rendered, $nextIndex] = $this->renderIf($tokens, $index + 1, $end, $ifVariable, $context, $partials, $partialStack);
                $this->appendRenderedChunk($output, $rendered, $pendingNestColumn);
                $index = $nextIndex - 1;
                continue;
            }

            $forVariable = $this->controlVariable($directive, 'for');
            if ($forVariable !== null) {
                [$rendered, $nextIndex] = $this->renderFor($tokens, $index + 1, $end, $forVariable, $context, $partials, $partialStack);
                $this->appendRenderedChunk($output, $rendered, $pendingNestColumn);
                $index = $nextIndex - 1;
                continue;
            }

            if (in_array($directive, ['elseif', 'else', 'endif', 'sep', 'endfor'], true) || $this->controlVariable($directive, 'elseif') !== null) {
                throw new \UnexpectedValueException("Unexpected doctemplate control directive {$directive}");
            }

            $rendered = $this->renderDirective($directive, $context, $partials, $partialStack);
            if ($pendingNestColumn === null) {
                $autoNestPrefix = $this->automaticNestPrefix($tokens, $index, $end, $output);
                if ($autoNestPrefix !== null) {
                    $rendered = $this->nestMultiline($rendered, $autoNestPrefix);
                }
            }

            $this->appendRenderedChunk($output, $rendered, $pendingNestColumn);
        }

        return $output;
    }

    /**
     * @param list<array{type:string, value:string}> $tokens
     * @param array<string, mixed> $context
     * @param array<string, string> $partials
     * @param list<string> $partialStack
     * @return array{0:string, 1:int}
     */
    private function renderIf(array $tokens, int $start, int $end, string $firstVariable, array $context, array $partials, array $partialStack): array
    {
        [$branches, $nextIndex] = $this->collectIfBranches($tokens, $start, $end, $firstVariable);

        foreach ($branches as $branch) {
            if ($branch['variable'] === null || $this->isTruthy($this->resolveExpression($branch['variable'], $context)['value'])) {
                return [
                    $this->renderRange($tokens, $branch['start'], $branch['end'], $context, $partials, $partialStack),
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
     * @param array<string, string> $partials
     * @param list<string> $partialStack
     * @return array{0:string, 1:int}
     */
    private function renderFor(array $tokens, int $start, int $end, string $variable, array $context, array $partials, array $partialStack): array
    {
        [$bodyStart, $bodyEnd, $separatorStart, $separatorEnd, $nextIndex] = $this->collectForSlices($tokens, $start, $end);
        $expression = $this->parseVariableExpression($variable);
        $resolved = $this->resolveParsedExpression($expression, $context);
        $iterations = $this->loopIterations($resolved['exists'], $resolved['value']);
        $rendered = [];

        foreach ($iterations as $item) {
            $iterationContext = $this->contextForLoopIteration($context, $expression['name'], $item);
            $rendered[] = $this->renderRange($tokens, $bodyStart, $bodyEnd, $iterationContext, $partials, $partialStack);
        }

        if ($rendered === []) {
            return ['', $nextIndex];
        }

        $separator = $separatorStart === null
            ? ''
            : $this->renderRange($tokens, $separatorStart, (int) $separatorEnd, $context, $partials, $partialStack);

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
     * @param array<string, string> $partials
     * @param list<string> $partialStack
     */
    private function renderDirective(string $directive, array $context, array $partials, array $partialStack): string
    {
        $partial = $this->parsePartialDirective($directive);
        if ($partial !== null) {
            return $this->renderPartialDirective($partial, $context, $partials, $partialStack);
        }

        $appliedPartial = $this->parseAppliedPartialDirective($directive);
        if ($appliedPartial !== null) {
            return $this->renderAppliedPartialDirective($appliedPartial, $context, $partials, $partialStack);
        }

        return $this->renderVariableDirective($directive, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderVariableDirective(string $directive, array $context): string
    {
        $expression = $this->parseVariableExpression($directive);
        $name = $expression['name'];
        if (in_array($name, ['if', 'else', 'elseif', 'endif', 'for', 'sep', 'endfor'], true)) {
            throw new \UnexpectedValueException("Reserved doctemplate keyword {$name} cannot be rendered as a variable");
        }

        $resolved = $this->resolveParsedExpression($expression, $context);
        if (!$resolved['exists']) {
            return '';
        }

        return $this->renderValue($resolved['value'], $expression['separator']);
    }

    /**
     * @param array{name:string, separator:?string, pipes:list<array{name:string, args:list<int|string>}>} $partial
     * @param array<string, mixed> $context
     * @param array<string, string> $partials
     * @param list<string> $partialStack
     */
    private function renderPartialDirective(array $partial, array $context, array $partials, array $partialStack): string
    {
        $value = $this->renderPartial($partial['name'], $context, $partials, $partialStack);
        foreach ($partial['pipes'] as $pipe) {
            $value = $this->applyPipe($pipe, $value);
        }

        return $this->renderValue($value, $partial['separator']);
    }

    /**
     * @param array{variable:array{name:string, separator:?string, pipes:list<array{name:string, args:list<int|string>}>}, partial:array{name:string, separator:?string, pipes:list<array{name:string, args:list<int|string>}>}} $appliedPartial
     * @param array<string, mixed> $context
     * @param array<string, string> $partials
     * @param list<string> $partialStack
     */
    private function renderAppliedPartialDirective(array $appliedPartial, array $context, array $partials, array $partialStack): string
    {
        $resolved = $this->resolveParsedExpression($appliedPartial['variable'], $context);
        $iterations = $this->loopIterations($resolved['exists'], $resolved['value']);
        if ($iterations === []) {
            return '';
        }

        $rendered = [];
        foreach ($iterations as $item) {
            $iterationContext = $this->contextForPartialApplication($context, $item);
            $value = $this->renderPartial($appliedPartial['partial']['name'], $iterationContext, $partials, $partialStack);
            foreach ($appliedPartial['partial']['pipes'] as $pipe) {
                $value = $this->applyPipe($pipe, $value);
            }

            $rendered[] = $this->renderValue($value, null);
        }

        return implode($appliedPartial['partial']['separator'] ?? '', $rendered);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, string> $partials
     * @param list<string> $partialStack
     */
    private function renderPartial(string $name, array $context, array $partials, array $partialStack): string
    {
        if (!array_key_exists($name, $partials) || !is_string($partials[$name])) {
            throw new \UnexpectedValueException("Missing doctemplate partial {$name}");
        }

        if (count($partialStack) >= self::MAX_PARTIAL_DEPTH) {
            return '(loop)';
        }

        $rendered = $this->renderTemplate($partials[$name], $context, $partials, [...$partialStack, $name]);

        return preg_replace('/(?:\r\n|\n|\r)+$/', '', $rendered) ?? $rendered;
    }

    /**
     * @return ?string
     */
    private function controlVariable(string $directive, string $name): ?string
    {
        if (!preg_match('/^' . preg_quote($name, '/') . '\\((.+)\\)$/s', $directive, $matches)) {
            return null;
        }

        $expression = trim($matches[1], " \t");

        return $expression === '' ? null : $expression;
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

    /**
     * @param array<string, mixed> $context
     * @return array{exists:bool, value:mixed}
     */
    private function resolveExpression(string $expression, array $context): array
    {
        return $this->resolveParsedExpression($this->parseVariableExpression($expression), $context);
    }

    /**
     * @param array{name:string, separator:?string, pipes:list<array{name:string, args:list<int|string>}>} $expression
     * @param array<string, mixed> $context
     * @return array{exists:bool, value:mixed}
     */
    private function resolveParsedExpression(array $expression, array $context): array
    {
        $resolved = $this->resolve($expression['name'], $context);
        if (!$resolved['exists']) {
            return $resolved;
        }

        $value = $resolved['value'];
        foreach ($expression['pipes'] as $pipe) {
            $value = $this->applyPipe($pipe, $value);
        }

        return ['exists' => true, 'value' => $value];
    }

    /**
     * @return array{name:string, separator:?string, pipes:list<array{name:string, args:list<int|string>}>>|null
     */
    private function parsePartialDirective(string $expression): ?array
    {
        $partial = $this->parsePartialCallExpression($expression);
        if ($partial === null) {
            return null;
        }

        return $partial;
    }

    /**
     * @return array{variable:array{name:string, separator:?string, pipes:list<array{name:string, args:list<int|string>}>}, partial:array{name:string, separator:?string, pipes:list<array{name:string, args:list<int|string>}>}}|null
     */
    private function parseAppliedPartialDirective(string $expression): ?array
    {
        $colon = $this->findAppliedPartialColon($expression);
        if ($colon === null) {
            return null;
        }

        $variableSource = trim(substr($expression, 0, $colon), " \t");
        $partialSource = trim(substr($expression, $colon + 1), " \t");
        if ($variableSource === '' || $partialSource === '') {
            return null;
        }

        $partial = $this->parsePartialCallExpression($partialSource);
        if ($partial === null) {
            return null;
        }

        return [
            'variable' => $this->parseVariableExpression($variableSource),
            'partial' => $partial,
        ];
    }

    /**
     * @return array{name:string, separator:?string, pipes:list<array{name:string, args:list<int|string>}>>|null
     */
    private function parsePartialCallExpression(string $expression): ?array
    {
        if (!preg_match('/^([A-Za-z0-9_.\\/\\\\-]+)\\(\\)(?:\\[(.*)\\])?(?:\\/(.*))?$/s', $expression, $matches)) {
            return null;
        }

        return [
            'name' => $this->normalizePartialName($matches[1]),
            'separator' => array_key_exists(2, $matches) ? $matches[2] : null,
            'pipes' => $this->parsePipeSuffix($matches[3] ?? '', $expression),
        ];
    }

    /**
     * @return list<array{name:string, args:list<int|string>}>
     */
    private function parsePipeSuffix(string $pipeSource, string $expression): array
    {
        if ($pipeSource === '') {
            return [];
        }

        return $this->parsePipeSpecs($this->splitPipeExpression($pipeSource), $expression);
    }

    private function findAppliedPartialColon(string $expression): ?int
    {
        $bracketDepth = 0;
        $inQuote = false;
        $escape = false;
        $length = strlen($expression);

        for ($index = 0; $index < $length; $index++) {
            $char = $expression[$index];
            if ($escape) {
                $escape = false;
                continue;
            }

            if ($inQuote && $char === '\\') {
                $escape = true;
                continue;
            }

            if ($char === '"') {
                $inQuote = !$inQuote;
                continue;
            }

            if (!$inQuote && $char === '[') {
                $bracketDepth++;
                continue;
            }

            if (!$inQuote && $char === ']' && $bracketDepth > 0) {
                $bracketDepth--;
                continue;
            }

            if (!$inQuote && $char === ':' && $bracketDepth === 0) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @return array{name:string, separator:?string, pipes:list<array{name:string, args:list<int|string>}>}
     */
    private function parseVariableExpression(string $expression): array
    {
        $parts = $this->splitPipeExpression($expression);
        $base = array_shift($parts);
        if ($base === null || !preg_match('/^(it|[A-Za-z][A-Za-z0-9_.-]*)(?:\\[(.*)\\])?$/s', $base, $matches)) {
            throw new \UnexpectedValueException("Unsupported doctemplate directive {$expression}");
        }

        return [
            'name' => $matches[1],
            'separator' => array_key_exists(2, $matches) ? $matches[2] : null,
            'pipes' => $this->parsePipeSpecs($parts, $expression),
        ];
    }

    /**
     * @param list<string> $pipeSpecs
     * @return list<array{name:string, args:list<int|string>}>
     */
    private function parsePipeSpecs(array $pipeSpecs, string $expression): array
    {
        $pipes = [];
        foreach ($pipeSpecs as $pipeSpec) {
            $pipeSpec = trim($pipeSpec, " \t");
            if ($pipeSpec === '') {
                throw new \UnexpectedValueException("Unsupported doctemplate directive {$expression}");
            }

            if (!preg_match('/^([A-Za-z][A-Za-z0-9_-]*)(?:\\s+(.+))?$/s', $pipeSpec, $pipeMatches)) {
                throw new \UnexpectedValueException("Unsupported doctemplate pipe {$pipeSpec}");
            }

            $pipeName = $pipeMatches[1];
            $argumentSource = isset($pipeMatches[2]) ? trim($pipeMatches[2]) : '';
            if (in_array($pipeName, ['left', 'right', 'center'], true)) {
                $pipes[] = [
                    'name' => $pipeName,
                    'args' => $this->parseBlockPipeArguments($pipeName, $argumentSource),
                ];
                continue;
            }

            if ($argumentSource !== '') {
                throw new \UnexpectedValueException("Unsupported parameterized doctemplate pipe {$pipeName}");
            }

            $pipes[] = [
                'name' => $pipeName,
                'args' => [],
            ];
        }

        return $pipes;
    }

    /**
     * @return list<int|string>
     */
    private function parseBlockPipeArguments(string $pipeName, string $source): array
    {
        if ($source === '') {
            throw new \UnexpectedValueException("Missing integer parameter for doctemplate pipe {$pipeName}");
        }

        if (!preg_match('/^([0-9]+)(.*)$/s', $source, $matches)) {
            throw new \UnexpectedValueException("Expected integer parameter for doctemplate pipe {$pipeName}");
        }

        $width = (int) $matches[1];
        if ($width < 1) {
            throw new \UnexpectedValueException("Expected positive integer parameter for doctemplate pipe {$pipeName}");
        }

        $offset = 0;
        $remaining = ltrim($matches[2], " \t\r\n");
        $borders = [];
        while ($remaining !== '') {
            if ($remaining[0] !== '"') {
                throw new \UnexpectedValueException("Expected quoted border parameter for doctemplate pipe {$pipeName}");
            }

            $borders[] = $this->parseQuotedPipeString($remaining, $offset);
            $remaining = ltrim(substr($remaining, $offset), " \t\r\n");
            $offset = 0;
            if (count($borders) > 2) {
                throw new \UnexpectedValueException("Too many border parameters for doctemplate pipe {$pipeName}");
            }
        }

        return [$width, $borders[0] ?? '', $borders[1] ?? ''];
    }

    private function parseQuotedPipeString(string $source, int &$offset): string
    {
        $offset = 1;
        $value = '';
        $length = strlen($source);

        while ($offset < $length) {
            $char = $source[$offset];
            if ($char === '"') {
                $offset++;

                return $value;
            }

            if ($char === '\\') {
                $offset++;
                if ($offset >= $length) {
                    throw new \UnexpectedValueException('Unclosed doctemplate pipe quoted string');
                }

                $value .= $source[$offset];
                $offset++;
                continue;
            }

            $value .= $char;
            $offset++;
        }

        throw new \UnexpectedValueException('Unclosed doctemplate pipe quoted string');
    }

    /**
     * @return list<string>
     */
    private function splitPipeExpression(string $expression): array
    {
        $parts = [];
        $buffer = '';
        $bracketDepth = 0;
        $inQuote = false;
        $escape = false;
        $length = strlen($expression);

        for ($index = 0; $index < $length; $index++) {
            $char = $expression[$index];
            if ($escape) {
                $buffer .= $char;
                $escape = false;
                continue;
            }

            if ($inQuote && $char === '\\') {
                $buffer .= $char;
                $escape = true;
                continue;
            }

            if ($char === '"') {
                $inQuote = !$inQuote;
                $buffer .= $char;
                continue;
            }

            if (!$inQuote && $char === '[') {
                $bracketDepth++;
                $buffer .= $char;
                continue;
            }

            if (!$inQuote && $char === ']' && $bracketDepth > 0) {
                $bracketDepth--;
                $buffer .= $char;
                continue;
            }

            if (!$inQuote && $char === '/' && $bracketDepth === 0) {
                $parts[] = $buffer;
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $parts[] = $buffer;

        return $parts;
    }

    /**
     * @param array{name:string, args:list<int|string>} $pipe
     */
    private function applyPipe(array $pipe, mixed $value): mixed
    {
        return match ($pipe['name']) {
            'pairs' => $this->pipePairs($value),
            'uppercase' => $this->mapTextualValue($value, fn (string $text): string => $this->uppercase($text)),
            'lowercase' => $this->mapTextualValue($value, fn (string $text): string => $this->lowercase($text)),
            'length' => $this->pipeLength($value),
            'reverse' => $this->pipeReverse($value),
            'first' => is_array($value) && array_is_list($value) && $value !== [] ? $value[0] : $value,
            'last' => is_array($value) && array_is_list($value) && $value !== [] ? $value[array_key_last($value)] : $value,
            'rest' => is_array($value) && array_is_list($value) && $value !== [] ? array_slice($value, 1) : $value,
            'allbutlast' => is_array($value) && array_is_list($value) && $value !== [] ? array_slice($value, 0, -1) : $value,
            'chomp' => is_string($value) ? rtrim($value, "\r\n") : $value,
            'nowrap' => $value,
            'alpha' => $this->mapTextualValue($value, fn (string $text): string => $this->pipeAlphaText($text)),
            'roman' => $this->mapTextualValue($value, fn (string $text): string => $this->pipeRomanText($text)),
            'left', 'right', 'center' => $this->pipeBlock($pipe['name'], $pipe['args'], $value),
            default => throw new \UnexpectedValueException("Unsupported doctemplate pipe {$pipe['name']}"),
        };
    }

    private function mapTextualValue(mixed $value, callable $callback): mixed
    {
        if (is_array($value)) {
            $mapped = [];
            foreach ($value as $key => $item) {
                $mapped[$key] = $this->mapTextualValue($item, $callback);
            }

            return $mapped;
        }

        if (is_string($value) || is_int($value) || is_float($value)) {
            return $callback((string) $value);
        }

        return $value;
    }

    private function pipeAlphaText(string $value): string
    {
        if (!preg_match('/^[0-9]+$/', $value)) {
            return $value;
        }

        $number = (int) $value;
        if ($number < 1) {
            return $value;
        }

        $label = '';
        while ($number > 0) {
            $number--;
            $label = chr(ord('a') + ($number % 26)) . $label;
            $number = intdiv($number, 26);
        }

        return $label;
    }

    private function pipeRomanText(string $value): string
    {
        if (!preg_match('/^[0-9]+$/', $value)) {
            return $value;
        }

        $number = (int) $value;
        if ($number < 1 || $number >= 4000) {
            return $value;
        }

        $roman = '';
        foreach ([
            1000 => 'm',
            900 => 'cm',
            500 => 'd',
            400 => 'cd',
            100 => 'c',
            90 => 'xc',
            50 => 'l',
            40 => 'xl',
            10 => 'x',
            9 => 'ix',
            5 => 'v',
            4 => 'iv',
            1 => 'i',
        ] as $decimal => $glyph) {
            while ($number >= $decimal) {
                $roman .= $glyph;
                $number -= $decimal;
            }
        }

        return $roman;
    }

    /**
     * @param list<int|string> $args
     */
    private function pipeBlock(string $alignment, array $args, mixed $value): mixed
    {
        if (!is_string($value) && !is_int($value) && !is_float($value) && $value !== null) {
            return $value;
        }

        $width = (int) ($args[0] ?? 0);
        if ($width < 1) {
            throw new \UnexpectedValueException("Missing integer parameter for doctemplate pipe {$alignment}");
        }

        $leftBorder = is_string($args[1] ?? null) ? $args[1] : '';
        $rightBorder = is_string($args[2] ?? null) ? $args[2] : '';
        $lines = preg_split('/\r\n|\n|\r/', $value === null ? '' : (string) $value);
        if ($lines === false) {
            $lines = [$value === null ? '' : (string) $value];
        }

        $padded = [];
        foreach ($lines as $line) {
            $padded[] = $leftBorder . $this->padBlockLine($line, $width, $alignment) . $rightBorder;
        }

        return implode("\n", $padded);
    }

    private function padBlockLine(string $line, int $width, string $alignment): string
    {
        return UnicodeText::padDisplay($line, $width, $alignment);
    }

    private function pipePairs(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $pairs = [];
        if (array_is_list($value)) {
            foreach ($value as $index => $item) {
                $pairs[] = ['key' => $index + 1, 'value' => $item];
            }

            return $pairs;
        }

        foreach ($value as $key => $item) {
            $pairs[] = ['key' => $key, 'value' => $item];
        }

        return $pairs;
    }

    private function pipeLength(mixed $value): int
    {
        if (is_string($value)) {
            return $this->stringLength($value);
        }

        if (is_array($value)) {
            return count($value);
        }

        return 0;
    }

    private function pipeReverse(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->reverseString($value);
        }

        if (is_array($value) && array_is_list($value)) {
            return array_reverse($value);
        }

        return $value;
    }

    private function uppercase(string $value): string
    {
        return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
    }

    private function lowercase(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private function stringLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }

        $count = preg_match_all('/./us', $value, $matches);
        if ($count !== false) {
            return $count;
        }

        return strlen($value);
    }

    private function reverseString(string $value): string
    {
        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters === false) {
            return strrev($value);
        }

        return implode('', array_reverse($characters));
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
            return $value ? 'true' : '';
        }

        if (is_string($value)) {
            return $this->stripSingleFinalNewline($value);
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return '';
    }

    private function stripSingleFinalNewline(string $value): string
    {
        if (str_ends_with($value, "\r\n")) {
            return substr($value, 0, -2);
        }

        if (str_ends_with($value, "\n") || str_ends_with($value, "\r")) {
            return substr($value, 0, -1);
        }

        return $value;
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

        return $next;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function contextForPartialApplication(array $context, mixed $item): array
    {
        $next = $context;
        $next['it'] = $item;

        return $next;
    }

    private function currentColumn(string $output): int
    {
        $lineStart = strrpos($output, "\n");
        $line = $lineStart === false ? $output : substr($output, $lineStart + 1);

        return strlen($line);
    }

    /**
     * @param list<array{type:string, value:string}> $tokens
     */
    private function automaticNestPrefix(array $tokens, int $index, int $end, string $output): ?string
    {
        $lineStart = strrpos($output, "\n");
        $prefix = $lineStart === false ? $output : substr($output, $lineStart + 1);
        if (trim($prefix, " \t") !== '') {
            return null;
        }

        for ($next = $index + 1; $next < $end; $next++) {
            $token = $tokens[$next];
            if ($token['type'] !== 'text') {
                return null;
            }

            $newline = strpos($token['value'], "\n");
            $beforeNewline = $newline === false ? $token['value'] : substr($token['value'], 0, $newline);
            if (trim($beforeNewline, " \t\r") !== '') {
                return null;
            }

            if ($newline !== false) {
                return $prefix;
            }
        }

        return $prefix;
    }

    private function appendRenderedChunk(string &$output, string $chunk, ?int &$pendingNestColumn): void
    {
        if ($pendingNestColumn !== null) {
            $chunk = $this->nestMultiline($chunk, str_repeat(' ', $pendingNestColumn));
            $pendingNestColumn = null;
        }

        $output .= $chunk;
    }

    private function nestMultiline(string $value, string $indent): string
    {
        if ($indent === '' || !str_contains($value, "\n")) {
            return $value;
        }

        return preg_replace('/\n(?!$)/', "\n" . $indent, $value) ?? $value;
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
