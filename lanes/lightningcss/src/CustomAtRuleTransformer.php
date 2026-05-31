<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class CustomAtRuleTransformer
{
    /** @var array<string, array<string, mixed>> */
    private array $customAtRules = [];

    /** @var array<string, callable> */
    private array $ruleVisitors = [];

    /** @var callable|null */
    private $genericRuleVisitor = null;

    /** @var array<string, callable> */
    private array $unknownRuleVisitors = [];

    /** @var callable|null */
    private $genericUnknownRuleVisitor = null;

    /** @var array<string, callable> */
    private array $functionVisitors = [];

    /** @var callable|null */
    private $genericFunctionVisitor = null;

    /** @var array<string, callable> */
    private array $tokenVisitors = [];

    /** @var callable|null */
    private $genericTokenVisitor = null;

    private DeclarationBlock $declarationBlock;

    private CssMinifier $minifier;

    public function __construct()
    {
        $this->declarationBlock = new DeclarationBlock();
        $this->minifier = new CssMinifier();
    }

    /**
     * Compose a small subset of LightningCSS visitors used by custom at-rule transforms.
     *
     * @param list<array<string, mixed>> $visitors
     * @return array<string, mixed>
     */
    public static function composeVisitors(array $visitors): array
    {
        if (count($visitors) === 1) {
            return $visitors[0];
        }

        return [
            'Rule' => [
                'custom' => static function (array $rule, self $transformer) use ($visitors): mixed {
                    foreach ($visitors as $visitor) {
                        $callback = self::customRuleVisitorCallback($visitor, $rule['name']);
                        if ($callback === null) {
                            continue;
                        }

                        $replacement = $callback($rule, $transformer);
                        if ($replacement !== null) {
                            return $replacement;
                        }
                    }

                    return null;
                },
                'unknown' => static function (array $rule, self $transformer) use ($visitors): mixed {
                    $forwardedUnknown = false;
                    foreach ($visitors as $visitor) {
                        $callback = self::unknownRuleVisitorCallback($visitor, $rule['name']);
                        if ($callback === null) {
                            continue;
                        }

                        $replacement = $callback($rule, $transformer);
                        if ($replacement !== null) {
                            if (self::isUnknownRuleReplacement($replacement)) {
                                $rule = self::normalizeUnknownRuleReplacement($rule, $replacement);
                                $forwardedUnknown = true;
                                continue;
                            }

                            return $replacement;
                        }
                    }

                    return $forwardedUnknown ? ['type' => 'unknown', 'value' => $rule] : null;
                },
            ],
            'Token' => [
                'at-keyword' => static function (array $token, self $transformer) use ($visitors): mixed {
                    foreach ($visitors as $visitor) {
                        $callback = self::tokenVisitorCallback($visitor, 'at-keyword');
                        if ($callback === null) {
                            continue;
                        }

                        $replacement = $callback($token, $transformer);
                        if ($replacement !== null) {
                            return $replacement;
                        }
                    }

                    return null;
                },
            ],
        ];
    }

    /**
     * @param array<string, array{prelude?:string, body?:string}> $customAtRules
     * @param array<string, mixed> $visitor
     * @param array<string, callable> $functionVisitors
     */
    public function transform(string $css, array $customAtRules, array $visitor = [], array $functionVisitors = []): string
    {
        $this->configure($customAtRules, $visitor, $functionVisitors);

        return $this->minifier->minify($this->processRuleList($this->stripComments($css)));
    }

    /**
     * @param array<string, string> $files
     * @param array<string, array{prelude?:string, body?:string}> $customAtRules
     * @param array<string, mixed> $visitor
     * @param callable|null $resolver
     * @param array<string, callable> $functionVisitors
     */
    public function bundle(
        string $entry,
        array $files,
        array $customAtRules,
        array $visitor = [],
        ?callable $resolver = null,
        array $functionVisitors = [],
    ): string {
        $css = (new CssBundler())->bundle($entry, $files, $resolver);

        return $this->transform($css, $customAtRules, $visitor, $functionVisitors);
    }

    /**
     * @return array{kind:string}
     */
    public function remove(): array
    {
        return ['kind' => 'remove'];
    }

    /**
     * @return array{kind:string, css:string}
     */
    public function raw(string $css): array
    {
        return ['kind' => 'raw', 'css' => $css];
    }

    /**
     * @return array{kind:string, css:string}
     */
    public function ruleList(string $css): array
    {
        return ['kind' => 'rule-list', 'css' => $css];
    }

    /**
     * @return array{kind:string, css:string}
     */
    public function styleBlock(string $css): array
    {
        return ['kind' => 'style-block', 'css' => $css];
    }

    /**
     * @param string|array{kind:string, css?:string, selector?:string, declarations?:mixed, query?:string, body?:mixed} $body
     * @return array{kind:string, query:string, body:mixed}
     */
    public function media(string $query, string|array $body): array
    {
        return ['kind' => 'media', 'query' => trim($query), 'body' => $body];
    }

    /**
     * @param string|array<string, string>|list<array{property:string, value:string, important?:bool}> $declarations
     * @return array{kind:string, selector:string, declarations:mixed}
     */
    public function styleRule(string $selector, string|array $declarations): array
    {
        return ['kind' => 'style-rule', 'selector' => trim($selector), 'declarations' => $declarations];
    }

    /**
     * @param array<string, array{prelude?:string, body?:string}> $customAtRules
     * @param array<string, mixed> $visitor
     * @param array<string, callable> $functionVisitors
     */
    private function configure(array $customAtRules, array $visitor, array $functionVisitors): void
    {
        $this->customAtRules = [];
        foreach ($customAtRules as $name => $definition) {
            $this->customAtRules[strtolower($name)] = $definition;
        }

        $this->ruleVisitors = [];
        $this->genericRuleVisitor = null;
        $customVisitors = $visitor['Rule']['custom'] ?? $visitor['custom'] ?? [];
        if (is_callable($customVisitors)) {
            $this->genericRuleVisitor = $customVisitors;
        } elseif (is_array($customVisitors)) {
            foreach ($customVisitors as $name => $callback) {
                if (is_callable($callback)) {
                    $this->ruleVisitors[strtolower((string) $name)] = $callback;
                }
            }
        }
        foreach ($visitor as $name => $callback) {
            if (is_string($name) && is_callable($callback) && !in_array($name, ['Rule', 'Function', 'Token', 'custom', 'unknown'], true)) {
                $this->ruleVisitors[strtolower($name)] = $callback;
            }
        }

        $this->unknownRuleVisitors = [];
        $this->genericUnknownRuleVisitor = null;
        $unknownVisitors = $visitor['Rule']['unknown'] ?? $visitor['unknown'] ?? [];
        if (is_callable($unknownVisitors)) {
            $this->genericUnknownRuleVisitor = $unknownVisitors;
        } elseif (is_array($unknownVisitors)) {
            foreach ($unknownVisitors as $name => $callback) {
                if (is_callable($callback)) {
                    $this->unknownRuleVisitors[strtolower((string) $name)] = $callback;
                }
            }
        }

        $this->functionVisitors = [];
        $this->genericFunctionVisitor = null;
        $functionVisitorConfig = $visitor['Function'] ?? [];
        if (is_callable($functionVisitorConfig)) {
            $this->genericFunctionVisitor = $functionVisitorConfig;
        } elseif (is_array($functionVisitorConfig)) {
            foreach ($functionVisitorConfig as $name => $callback) {
                if (is_callable($callback)) {
                    $this->functionVisitors[strtolower((string) $name)] = $callback;
                }
            }
        }
        foreach ($functionVisitors as $name => $callback) {
            $this->functionVisitors[strtolower($name)] = $callback;
        }

        $this->tokenVisitors = [];
        $this->genericTokenVisitor = null;
        $tokenVisitorConfig = $visitor['Token'] ?? [];
        if (is_callable($tokenVisitorConfig)) {
            $this->genericTokenVisitor = $tokenVisitorConfig;
        } elseif (is_array($tokenVisitorConfig)) {
            foreach ($tokenVisitorConfig as $name => $callback) {
                if (is_callable($callback)) {
                    $this->tokenVisitors[strtolower((string) $name)] = $callback;
                }
            }
        }
    }

    private function processRuleList(string $css): string
    {
        $output = '';
        $cursor = 0;
        $length = strlen($css);

        while (true) {
            $cursor = $this->skipWhitespace($css, $cursor);
            if ($cursor >= $length) {
                break;
            }

            $nextBlock = $this->findNextTopLevel($css, '{', $cursor);
            $nextStatement = $this->findNextTopLevel($css, ';', $cursor);

            if ($nextStatement !== null && ($nextBlock === null || $nextStatement < $nextBlock)) {
                $statement = trim(substr($css, $cursor, $nextStatement - $cursor));
                if ($statement !== '') {
                    $output .= $this->processStatement($statement, null);
                }
                $cursor = $nextStatement + 1;
                continue;
            }

            if ($nextBlock === null) {
                $trailing = trim(substr($css, $cursor));
                if ($trailing !== '') {
                    $output .= $trailing;
                }
                break;
            }

            $prelude = trim(substr($css, $cursor, $nextBlock - $cursor));
            $close = $this->findMatchingBrace($css, $nextBlock);
            $body = substr($css, $nextBlock + 1, $close - $nextBlock - 1);

            if (str_starts_with($prelude, '@')) {
                [$name, $atPrelude] = $this->parseAtPrelude($prelude);
                if ($this->isCustomAtRule($name)) {
                    $output .= $this->processCustomAtRule($prelude, $body, null);
                } else {
                    $rule = $this->buildUnknownRule($name, $atPrelude, $body, null);
                    $replacement = $this->callUnknownRuleVisitor($rule);
                    $output .= $replacement === null
                        ? $prelude . '{' . $this->processRuleList($body) . '}'
                        : $this->emitReplacement($replacement, null);
                }
            } else {
                $selectors = $this->splitTopLevel($prelude, ',');
                $output .= $this->processStyleBody($body, $selectors);
            }

            $cursor = $close + 1;
        }

        return $output;
    }

    /**
     * @param list<string>|null $parentSelectors
     */
    private function processStatement(string $statement, ?array $parentSelectors): string
    {
        if (!str_starts_with($statement, '@')) {
            return $statement . ';';
        }

        [$name, $prelude] = $this->parseAtPrelude($statement);
        if (!$this->isCustomAtRule($name)) {
            $rule = $this->buildUnknownRule($name, $prelude, null, $parentSelectors);
            $replacement = $this->callUnknownRuleVisitor($rule);

            return $replacement === null
                ? $statement . ';'
                : $this->emitReplacement($replacement, $parentSelectors);
        }

        $rule = $this->buildCustomRule($name, $prelude, null, $parentSelectors);
        $replacement = $this->callRuleVisitor($rule);
        if ($replacement === null) {
            return $statement . ';';
        }

        return $this->emitReplacement($replacement, $parentSelectors);
    }

    /**
     * @param list<string> $selectors
     */
    private function processStyleBody(string $body, array $selectors): string
    {
        $output = '';
        $declarations = '';
        $cursor = 0;
        $length = strlen($body);

        while ($cursor < $length) {
            $nextBlock = $this->findNextTopLevel($body, '{', $cursor);
            $nextStatement = $this->findNextTopLevel($body, ';', $cursor);

            if ($nextStatement !== null && ($nextBlock === null || $nextStatement < $nextBlock)) {
                $statement = trim(substr($body, $cursor, $nextStatement - $cursor));
                if ($statement !== '') {
                    if (str_starts_with($statement, '@')) {
                        $output .= $this->emitDeclarationRule($selectors, $declarations);
                        $declarations = '';
                        $output .= $this->processStatement($statement, $selectors);
                    } else {
                        $declarations .= $statement . ';';
                    }
                }
                $cursor = $nextStatement + 1;
                continue;
            }

            if ($nextBlock === null) {
                $tail = trim(substr($body, $cursor));
                if ($tail !== '' && str_starts_with($tail, '@')) {
                    [$name] = $this->parseAtPrelude($tail);
                    if ($this->isCustomAtRule($name)) {
                        $output .= $this->emitDeclarationRule($selectors, $declarations);
                        $declarations = '';
                        $output .= $this->processStatement($tail, $selectors);
                        break;
                    }
                }

                $declarations .= substr($body, $cursor);
                break;
            }

            $prefix = substr($body, $cursor, $nextBlock - $cursor);
            [$declarationPart, $nestedPrelude] = $this->splitDeclarationsAndNestedPrelude($prefix);
            $declarations .= $declarationPart;
            $output .= $this->emitDeclarationRule($selectors, $declarations);
            $declarations = '';

            $nestedPrelude = trim($nestedPrelude);
            if ($nestedPrelude === '') {
                throw new \InvalidArgumentException('Nested CSS rule is missing a prelude');
            }

            $close = $this->findMatchingBrace($body, $nextBlock);
            $nestedBody = substr($body, $nextBlock + 1, $close - $nextBlock - 1);

            if (str_starts_with($nestedPrelude, '@')) {
                [$name, $atPrelude] = $this->parseAtPrelude($nestedPrelude);
                if ($this->isCustomAtRule($name)) {
                    $output .= $this->processCustomAtRule($nestedPrelude, $nestedBody, $selectors);
                } elseif (str_starts_with($nestedPrelude, '@nest ')) {
                    $nestedSelectors = $this->resolveNestedSelectors($selectors, substr($nestedPrelude, 6));
                    $output .= $this->processStyleBody($nestedBody, $nestedSelectors);
                } else {
                    $rule = $this->buildUnknownRule($name, $atPrelude, $nestedBody, $selectors);
                    $replacement = $this->callUnknownRuleVisitor($rule);
                    $output .= $replacement === null
                        ? $nestedPrelude . '{' . $this->processStyleBody($nestedBody, $selectors) . '}'
                        : $this->emitReplacement($replacement, $selectors);
                }
            } else {
                $nestedSelectors = $this->resolveNestedSelectors($selectors, $nestedPrelude);
                $output .= $this->processStyleBody($nestedBody, $nestedSelectors);
            }

            $cursor = $close + 1;
        }

        return $output . $this->emitDeclarationRule($selectors, $declarations);
    }

    /**
     * @param list<string>|null $parentSelectors
     */
    private function processCustomAtRule(string $prelude, string $body, ?array $parentSelectors): string
    {
        [$name, $atPrelude] = $this->parseAtPrelude($prelude);
        $rule = $this->buildCustomRule($name, $atPrelude, $body, $parentSelectors);
        $replacement = $this->callRuleVisitor($rule);
        if ($replacement === null) {
            return $prelude . '{' . $body . '}';
        }

        return $this->emitReplacement($replacement, $parentSelectors);
    }

    /**
     * @param list<string>|null $parentSelectors
     * @return array{name:string, prelude:string, bodyType:string|null, body:string, declarations:list<array{property:string, value:string, important:bool}>, context:string, parentSelectors:list<string>}
     */
    private function buildCustomRule(string $name, string $prelude, ?string $body, ?array $parentSelectors): array
    {
        $definition = $this->customAtRules[$name] ?? [];
        $bodyType = null;
        if ($body === null) {
            if (array_key_exists('body', $definition) && $definition['body'] !== null) {
                throw new \InvalidArgumentException("Custom at-rule @{$name} requires a block body");
            }
        } else {
            if (!array_key_exists('body', $definition) || $definition['body'] === null) {
                throw new \InvalidArgumentException("Custom at-rule @{$name} does not accept a block body");
            }
            $bodyType = (string) $definition['body'];
            if (!in_array($bodyType, ['declaration-list', 'rule-list', 'style-block'], true)) {
                throw new \InvalidArgumentException("Unsupported custom at-rule body type for @{$name}: {$bodyType}");
            }
        }

        $preludeGrammar = $definition['prelude'] ?? null;
        $preludeValue = $this->parseCustomPreludeValue($name, $prelude, is_string($preludeGrammar) ? $preludeGrammar : null);
        $declarations = [];
        if ($body !== null && $bodyType === 'declaration-list') {
            $declarations = $this->declarationBlock->parseEntries($body);
        }

        return [
            'name' => $name,
            'prelude' => $preludeValue,
            'bodyType' => $bodyType,
            'body' => $body ?? '',
            'declarations' => $declarations,
            'context' => $parentSelectors === null ? 'rule-list' : 'style-block',
            'parentSelectors' => $parentSelectors ?? [],
        ];
    }

    /**
     * @param list<string>|null $parentSelectors
     * @return array{name:string, prelude:string, preludeTokens:list<array{type:string,value:mixed}>, body:string, hasBlock:bool, context:string, parentSelectors:list<string>}
     */
    private function buildUnknownRule(string $name, string $prelude, ?string $body, ?array $parentSelectors): array
    {
        $prelude = trim($prelude);

        return [
            'name' => $name,
            'prelude' => $prelude,
            'preludeTokens' => $this->parseUnknownPreludeTokens($prelude),
            'body' => $body ?? '',
            'hasBlock' => $body !== null,
            'context' => $parentSelectors === null ? 'rule-list' : 'style-block',
            'parentSelectors' => $parentSelectors ?? [],
        ];
    }

    private function parseCustomPreludeValue(string $name, string $prelude, ?string $grammar): string
    {
        $prelude = trim($prelude);
        if ($grammar === null) {
            if ($prelude !== '') {
                throw new \InvalidArgumentException("Custom at-rule @{$name} does not accept a prelude");
            }

            return '';
        }

        if ($grammar === '<custom-ident>' && preg_match('/^-?[_a-zA-Z][-_a-zA-Z0-9]*$/', $prelude) !== 1) {
            throw new \InvalidArgumentException("Invalid custom at-rule prelude for <custom-ident>: {$prelude}");
        }
        if ($grammar === '<length>' && preg_match('/^(?:[+-]?(?:\d+|\d*\.\d+)(?:[a-zA-Z%]+)|0)$/', $prelude) !== 1) {
            throw new \InvalidArgumentException("Invalid custom at-rule prelude for <length>: {$prelude}");
        }

        return $prelude;
    }

    /**
     * @param array{name:string} $rule
     */
    private function callRuleVisitor(array $rule): mixed
    {
        $visitor = $this->ruleVisitors[$rule['name']] ?? $this->genericRuleVisitor;
        if ($visitor === null) {
            return null;
        }

        return $visitor($rule, $this);
    }

    /**
     * @param array{name:string} $rule
     */
    private function callUnknownRuleVisitor(array $rule): mixed
    {
        $visitor = $this->unknownRuleVisitors[$rule['name']] ?? $this->genericUnknownRuleVisitor;
        if ($visitor === null) {
            return null;
        }

        return $visitor($rule, $this);
    }

    /**
     * @param list<string>|null $parentSelectors
     */
    private function emitReplacement(mixed $replacement, ?array $parentSelectors): string
    {
        if ($replacement === null) {
            return '';
        }
        if ($replacement === [] || $replacement === false) {
            return '';
        }
        if (is_string($replacement)) {
            return $replacement;
        }
        if (!is_array($replacement)) {
            throw new \InvalidArgumentException('Custom at-rule visitor must return a string, array replacement, or null');
        }
        if (array_is_list($replacement)) {
            $css = '';
            foreach ($replacement as $item) {
                $css .= $this->emitReplacement($item, $parentSelectors);
            }

            return $css;
        }
        if (self::isUnknownRuleReplacement($replacement)) {
            return $this->emitUnknownRule($replacement['value'], $parentSelectors);
        }

        $kind = (string) ($replacement['kind'] ?? '');
        if ($kind === 'remove') {
            return '';
        }
        if ($kind === 'raw') {
            return (string) ($replacement['css'] ?? '');
        }
        if ($kind === 'rule-list') {
            return $this->processRuleList((string) ($replacement['css'] ?? ''));
        }
        if ($kind === 'style-block') {
            $css = (string) ($replacement['css'] ?? '');

            return $parentSelectors === null
                ? $this->processRuleList($css)
                : $this->processStyleBody($css, $parentSelectors);
        }
        if ($kind === 'media') {
            $query = (string) ($replacement['query'] ?? '');
            $body = $replacement['body'] ?? '';
            $bodyCss = is_array($body)
                ? $this->emitReplacement($body, $parentSelectors)
                : (string) $body;

            return '@media ' . $query . '{' . $bodyCss . '}';
        }
        if ($kind === 'style-rule') {
            $selector = (string) ($replacement['selector'] ?? '');
            $declarations = $replacement['declarations'] ?? '';
            if (is_array($declarations)) {
                return $this->emitDeclarationRule([$selector], $this->declarationsToCss($declarations));
            }

            return $this->emitDeclarationRule([$selector], (string) $declarations);
        }

        throw new \InvalidArgumentException("Unsupported custom at-rule replacement kind: {$kind}");
    }

    /**
     * @param array<string, mixed> $rule
     * @param list<string>|null $parentSelectors
     */
    private function emitUnknownRule(array $rule, ?array $parentSelectors): string
    {
        $name = (string) ($rule['name'] ?? '');
        if ($name === '') {
            throw new \InvalidArgumentException('Unknown at-rule replacement is missing a name');
        }

        $prelude = trim((string) ($rule['prelude'] ?? ''));
        $head = '@' . $name . ($prelude === '' ? '' : ' ' . $prelude);
        if (empty($rule['hasBlock'])) {
            return $head . ';';
        }

        $body = (string) ($rule['body'] ?? '');

        return $head . '{' . (
            $parentSelectors === null
                ? $this->processRuleList($body)
                : $this->processStyleBody($body, $parentSelectors)
        ) . '}';
    }

    /**
     * @param array<string, mixed> $visitor
     */
    private static function customRuleVisitorCallback(array $visitor, string $ruleName): ?callable
    {
        $ruleConfig = $visitor['Rule'] ?? null;
        if (is_callable($ruleConfig)) {
            return $ruleConfig;
        }

        if (is_array($ruleConfig)) {
            $customConfig = $ruleConfig['custom'] ?? null;
            if (is_callable($customConfig)) {
                return $customConfig;
            }

            if (is_array($customConfig)) {
                return self::caseInsensitiveCallback($customConfig, $ruleName);
            }
        }

        $customConfig = $visitor['custom'] ?? null;
        if (is_callable($customConfig)) {
            return $customConfig;
        }

        if (is_array($customConfig)) {
            return self::caseInsensitiveCallback($customConfig, $ruleName);
        }

        return self::caseInsensitiveCallback($visitor, $ruleName);
    }

    /**
     * @param array<string, mixed> $visitor
     */
    private static function unknownRuleVisitorCallback(array $visitor, string $ruleName): ?callable
    {
        $ruleConfig = $visitor['Rule'] ?? null;
        if (is_array($ruleConfig)) {
            $unknownConfig = $ruleConfig['unknown'] ?? null;
            if (is_callable($unknownConfig)) {
                return $unknownConfig;
            }

            if (is_array($unknownConfig)) {
                return self::caseInsensitiveCallback($unknownConfig, $ruleName);
            }
        }

        $unknownConfig = $visitor['unknown'] ?? null;
        if (is_callable($unknownConfig)) {
            return $unknownConfig;
        }

        if (is_array($unknownConfig)) {
            return self::caseInsensitiveCallback($unknownConfig, $ruleName);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $visitor
     */
    private static function tokenVisitorCallback(array $visitor, string $tokenType): ?callable
    {
        $tokenConfig = $visitor['Token'] ?? null;
        if (is_callable($tokenConfig)) {
            return $tokenConfig;
        }

        if (is_array($tokenConfig)) {
            return self::caseInsensitiveCallback($tokenConfig, $tokenType);
        }

        return null;
    }

    private static function isUnknownRuleReplacement(mixed $replacement): bool
    {
        return is_array($replacement)
            && ($replacement['type'] ?? null) === 'unknown'
            && isset($replacement['value'])
            && is_array($replacement['value']);
    }

    /**
     * @param array<string, mixed> $current
     * @param array{value: array<string, mixed>} $replacement
     * @return array<string, mixed>
     */
    private static function normalizeUnknownRuleReplacement(array $current, array $replacement): array
    {
        return array_replace($current, $replacement['value']);
    }

    /**
     * @param array<string|int, mixed> $callbacks
     */
    private static function caseInsensitiveCallback(array $callbacks, string $name): ?callable
    {
        $callback = $callbacks[$name] ?? $callbacks[strtolower($name)] ?? null;
        if (is_callable($callback)) {
            return $callback;
        }

        foreach ($callbacks as $key => $candidate) {
            if (is_string($key) && strtolower($key) === strtolower($name) && is_callable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param string|array<string, string>|list<array{property:string, value:string, important?:bool}> $declarations
     */
    private function declarationsToCss(string|array $declarations): string
    {
        if (is_string($declarations)) {
            return $declarations;
        }

        $css = '';
        foreach ($declarations as $property => $value) {
            if (is_array($value)) {
                $css .= $value['property'] . ':' . $value['value'] . (!empty($value['important']) ? ' !important' : '') . ';';
                continue;
            }
            $css .= $property . ':' . $value . ';';
        }

        return $css;
    }

    /**
     * @param list<string> $selectors
     */
    private function emitDeclarationRule(array $selectors, string $declarations): string
    {
        $declarations = trim($declarations);
        if ($declarations === '') {
            return '';
        }

        $entries = $this->declarationBlock->parseEntries($declarations);
        $body = '';
        foreach ($entries as $entry) {
            $body .= $entry['property'] . ':' . $this->rewriteDeclarationValue($entry['value']);
            if ($entry['important']) {
                $body .= ' !important';
            }
            $body .= ';';
        }

        return implode(',', array_map('trim', $selectors)) . '{' . $body . '}';
    }

    private function rewriteDeclarationValue(string $value): string
    {
        return $this->rewriteValueTokens($this->rewriteValueFunctions($value));
    }

    private function rewriteValueFunctions(string $value): string
    {
        $output = '';
        $cursor = 0;
        $length = strlen($value);

        while ($cursor < $length) {
            if (preg_match('/[a-zA-Z_-][-_a-zA-Z0-9]*(?=\()/A', substr($value, $cursor), $matches) !== 1) {
                $output .= $value[$cursor];
                $cursor++;
                continue;
            }

            $name = $matches[0];
            $open = $cursor + strlen($name);
            $close = $this->findMatchingParen($value, $open);
            if ($close === null) {
                $output .= $name;
                $cursor += strlen($name);
                continue;
            }

            $argumentsCss = substr($value, $open + 1, $close - $open - 1);
            $replacement = $this->callFunctionVisitor($name, $this->parseFunctionArguments($argumentsCss), $name . '(' . $argumentsCss . ')');
            if ($replacement === null) {
                $output .= $name . '(' . $argumentsCss . ')';
            } else {
                $output .= $replacement;
            }
            $cursor = $close + 1;
        }

        return $output;
    }

    /**
     * @param list<string> $arguments
     */
    private function callFunctionVisitor(string $name, array $arguments, string $raw): ?string
    {
        $visitor = $this->functionVisitors[strtolower($name)] ?? $this->genericFunctionVisitor;
        if ($visitor === null) {
            return null;
        }

        $replacement = $visitor($arguments, $raw, strtolower($name), $this);
        if ($replacement === null) {
            return null;
        }

        return (string) $replacement;
    }

    private function rewriteValueTokens(string $value): string
    {
        if ($this->tokenVisitors === [] && $this->genericTokenVisitor === null) {
            return $value;
        }

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

            if ($char === '@' && preg_match('/@(-?[_a-zA-Z][-_a-zA-Z0-9]*)/A', substr($value, $i), $matches) === 1) {
                $raw = $matches[0];
                $replacement = $this->callTokenVisitor('at-keyword', [
                    'type' => 'at-keyword',
                    'value' => $matches[1],
                    'raw' => $raw,
                ]);
                $output .= $replacement ?? $raw;
                $i += strlen($raw) - 1;
                continue;
            }

            $output .= $char;
        }

        return $output;
    }

    /**
     * @param array{type:string,value:mixed,raw?:string} $token
     */
    private function callTokenVisitor(string $type, array $token): ?string
    {
        $visitor = $this->tokenVisitors[strtolower($type)] ?? $this->genericTokenVisitor;
        if ($visitor === null) {
            return null;
        }

        $replacement = $visitor($token, $this);
        if ($replacement === null) {
            return null;
        }

        return (string) $replacement;
    }

    /**
     * @return list<string>
     */
    private function parseFunctionArguments(string $arguments): array
    {
        return array_map(
            static function (string $argument): string {
                $argument = trim($argument);
                if (
                    strlen($argument) >= 2
                    && (($argument[0] === '"' && $argument[strlen($argument) - 1] === '"') || ($argument[0] === "'" && $argument[strlen($argument) - 1] === "'"))
                ) {
                    return stripcslashes(substr($argument, 1, -1));
                }

                return $argument;
            },
            $this->splitTopLevel($arguments, ',')
        );
    }

    /**
     * @return list<array{type:string,value:mixed}>
     */
    private function parseUnknownPreludeTokens(string $prelude): array
    {
        $tokens = [];
        foreach ($this->splitWhitespaceTokens($prelude) as $token) {
            if (
                strlen($token) >= 2
                && (($token[0] === '"' && $token[strlen($token) - 1] === '"') || ($token[0] === "'" && $token[strlen($token) - 1] === "'"))
            ) {
                $tokens[] = [
                    'type' => 'token',
                    'value' => [
                        'type' => 'string',
                        'value' => stripcslashes(substr($token, 1, -1)),
                    ],
                ];
                continue;
            }

            if (preg_match('/^-?[_a-zA-Z][-_a-zA-Z0-9]*$/', $token) === 1) {
                $tokens[] = [
                    'type' => 'token',
                    'value' => [
                        'type' => 'ident',
                        'value' => $token,
                    ],
                ];
                continue;
            }

            $tokens[] = [
                'type' => 'raw',
                'value' => $token,
            ];
        }

        return $tokens;
    }

    /**
     * @return list<string>
     */
    private function splitWhitespaceTokens(string $value): array
    {
        $tokens = [];
        $current = '';
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($quote !== null) {
                $current .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $current .= $value[++$i];
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $current .= $char;
                continue;
            }
            if ($char === '(') {
                $parenDepth++;
            } elseif ($char === ')') {
                $parenDepth = max(0, $parenDepth - 1);
            } elseif ($char === '[') {
                $bracketDepth++;
            } elseif ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
            }

            if (ctype_space($char) && $parenDepth === 0 && $bracketDepth === 0) {
                if (trim($current) !== '') {
                    $tokens[] = trim($current);
                    $current = '';
                }
                continue;
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $tokens[] = trim($current);
        }

        return $tokens;
    }

    private function findMatchingParen(string $css, int $open): ?int
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
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    private function isCustomAtRule(string $name): bool
    {
        return isset($this->customAtRules[strtolower($name)]);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function parseAtPrelude(string $prelude): array
    {
        if (preg_match('/^@([_a-zA-Z][-_a-zA-Z0-9]*)(?:\s+(.*))?$/s', trim($prelude), $matches) !== 1) {
            throw new \InvalidArgumentException("Invalid CSS at-rule prelude: {$prelude}");
        }

        return [strtolower($matches[1]), trim($matches[2] ?? '')];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function splitDeclarationsAndNestedPrelude(string $prefix): array
    {
        $semicolon = $this->findLastTopLevel($prefix, ';');
        if ($semicolon !== null) {
            return [substr($prefix, 0, $semicolon + 1), substr($prefix, $semicolon + 1)];
        }

        return ['', $prefix];
    }

    /**
     * @param list<string> $parentSelectors
     * @return list<string>
     */
    private function resolveNestedSelectors(array $parentSelectors, string $nestedPrelude): array
    {
        $resolved = [];
        foreach ($this->splitTopLevel($nestedPrelude, ',') as $nested) {
            if (str_contains($nested, '&')) {
                foreach ($parentSelectors as $parent) {
                    $resolved[] = str_replace('&', trim($parent), trim($nested));
                }
                continue;
            }
            foreach ($parentSelectors as $parent) {
                $resolved[] = trim($parent) . ' ' . trim($nested);
            }
        }

        return $resolved;
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

        return array_values(array_filter(array_map('trim', $parts), static fn (string $part): bool => $part !== ''));
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

    private function findLastTopLevel(string $css, string $needle): ?int
    {
        $last = null;
        $offset = 0;
        while (($next = $this->findNextTopLevel($css, $needle, $offset)) !== null) {
            $last = $next;
            $offset = $next + 1;
        }

        return $last;
    }

    private function findMatchingBrace(string $css, int $open): int
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

        throw new \InvalidArgumentException('CSS block is missing a closing brace');
    }

    private function skipWhitespace(string $css, int $offset): int
    {
        $length = strlen($css);
        while ($offset < $length && ctype_space($css[$offset])) {
            $offset++;
        }

        return $offset;
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
}
