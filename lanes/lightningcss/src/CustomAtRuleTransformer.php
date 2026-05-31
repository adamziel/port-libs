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

    /** @var callable|null */
    private $styleRuleVisitor = null;

    /** @var array<string, callable> */
    private array $functionVisitors = [];

    /** @var callable|null */
    private $genericFunctionVisitor = null;

    /** @var array<string, callable> */
    private array $functionExitVisitors = [];

    /** @var callable|null */
    private $genericFunctionExitVisitor = null;

    /** @var array<string, callable> */
    private array $environmentVariableVisitors = [];

    /** @var callable|null */
    private $genericEnvironmentVariableVisitor = null;

    /** @var array<string, callable> */
    private array $variableVisitors = [];

    /** @var callable|null */
    private $genericVariableVisitor = null;

    /** @var callable|null */
    private $lengthVisitor = null;

    /** @var array<string, callable> */
    private array $tokenVisitors = [];

    /** @var callable|null */
    private $genericTokenVisitor = null;

    /** @var list<array<string, mixed>> */
    private array $dependencies = [];

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
     * @param list<array<string, mixed>|callable(array<string, mixed>): array<string, mixed>> $visitors
     * @return array<string, mixed>|callable(array<string, mixed>): array<string, mixed>
     */
    public static function composeVisitors(array $visitors): array|callable
    {
        $hasVisitorFactory = false;
        foreach ($visitors as $visitor) {
            if (is_callable($visitor)) {
                $hasVisitorFactory = true;
                break;
            }
        }

        if ($hasVisitorFactory) {
            return static function (array $context = []) use ($visitors): array {
                $resolvedVisitors = [];
                foreach ($visitors as $visitor) {
                    $resolved = is_callable($visitor) ? $visitor($context) : $visitor;
                    if (!is_array($resolved)) {
                        throw new \InvalidArgumentException('Visitor factory must return a visitor array');
                    }
                    $resolvedVisitors[] = $resolved;
                }

                $composed = self::composeVisitors($resolvedVisitors);
                if (!is_array($composed)) {
                    throw new \InvalidArgumentException('Composed visitor factory resolved to another factory');
                }

                return $composed;
            };
        }

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
                'style' => static function (array $rule, self $transformer) use ($visitors): mixed {
                    $rules = [$rule];
                    foreach ($visitors as $visitor) {
                        $callback = self::styleRuleVisitorCallback($visitor);
                        if ($callback === null) {
                            continue;
                        }

                        $nextRules = [];
                        foreach ($rules as $currentRule) {
                            $replacement = $callback($currentRule, $transformer);
                            if ($replacement === null) {
                                $nextRules[] = $currentRule;
                                continue;
                            }

                            foreach (self::normalizeStyleRuleVisitorReplacement($currentRule, $replacement) as $nextRule) {
                                $nextRules[] = $nextRule;
                            }
                        }

                        $rules = $nextRules;
                        if ($rules === []) {
                            break;
                        }
                    }

                    if ($rules === []) {
                        return [];
                    }

                    return count($rules) === 1 ? $rules[0] : $rules;
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
            'Function' => static function (array $arguments, string $raw, string $name, self $transformer) use ($visitors): mixed {
                foreach ($visitors as $visitor) {
                    $callback = self::functionVisitorCallback($visitor, $name);
                    if ($callback === null) {
                        continue;
                    }

                    $replacement = $callback($arguments, $raw, strtolower($name), $transformer);
                    if ($replacement !== null) {
                        return $replacement;
                    }
                }

                return null;
            },
            'FunctionExit' => static function (array $function, self $transformer) use ($visitors): mixed {
                foreach ($visitors as $visitor) {
                    $callback = self::functionExitVisitorCallback($visitor, (string) ($function['name'] ?? ''));
                    if ($callback === null) {
                        continue;
                    }

                    $replacement = $callback($function, $transformer);
                    if ($replacement !== null) {
                        return $replacement;
                    }
                }

                return null;
            },
            'EnvironmentVariable' => static function (array $environmentVariable, self $transformer) use ($visitors): mixed {
                foreach ($visitors as $visitor) {
                    $callback = self::environmentVariableVisitorCallback($visitor, $environmentVariable);
                    if ($callback === null) {
                        continue;
                    }

                    $replacement = $callback($environmentVariable, $transformer);
                    if ($replacement !== null) {
                        return $replacement;
                    }
                }

                return null;
            },
            'Variable' => static function (array $variable, self $transformer) use ($visitors): mixed {
                foreach ($visitors as $visitor) {
                    $callback = self::variableVisitorCallback($visitor, $variable);
                    if ($callback === null) {
                        continue;
                    }

                    $replacement = $callback($variable, $transformer);
                    if ($replacement !== null) {
                        return $replacement;
                    }
                }

                return null;
            },
            'Length' => static function (array $length, self $transformer) use ($visitors): mixed {
                $value = $length;
                $changed = false;
                foreach ($visitors as $visitor) {
                    $callback = self::lengthVisitorCallback($visitor);
                    if ($callback === null) {
                        continue;
                    }

                    $replacement = $callback($value, $transformer);
                    if ($replacement !== null) {
                        $value = $replacement;
                        $changed = true;
                    }
                }

                return $changed ? $value : null;
            },
        ];
    }

    /**
     * @param array<string, array{prelude?:string, body?:string}> $customAtRules
     * @param array<string, mixed>|callable(array<string, mixed>): array<string, mixed> $visitor
     * @param array<string, callable> $functionVisitors
     */
    public function transform(string $css, array $customAtRules, array|callable $visitor = [], array $functionVisitors = []): string
    {
        return $this->transformWithDependencies($css, $customAtRules, $visitor, $functionVisitors)['code'];
    }

    /**
     * @return array{code:string, dependencies:list<array<string, mixed>>}
     *
     * @param array<string, array{prelude?:string, body?:string}> $customAtRules
     * @param array<string, mixed>|callable(array<string, mixed>): array<string, mixed> $visitor
     * @param array<string, callable> $functionVisitors
     */
    public function transformWithDependencies(string $css, array $customAtRules, array|callable $visitor = [], array $functionVisitors = []): array
    {
        $this->dependencies = [];
        $this->configure($customAtRules, $visitor, $functionVisitors);

        return [
            'code' => $this->minifier->minify($this->processRuleList($this->stripComments($css))),
            'dependencies' => $this->dependencies,
        ];
    }

    /**
     * @param array<string, string> $files
     * @param array<string, array{prelude?:string, body?:string}> $customAtRules
     * @param array<string, mixed>|callable(array<string, mixed>): array<string, mixed> $visitor
     * @param callable|null $resolver
     * @param array<string, callable> $functionVisitors
     */
    public function bundle(
        string $entry,
        array $files,
        array $customAtRules,
        array|callable $visitor = [],
        ?callable $resolver = null,
        array $functionVisitors = [],
    ): string {
        return $this->bundleWithDependencies($entry, $files, $customAtRules, $visitor, $resolver, $functionVisitors)['code'];
    }

    /**
     * @return array{code:string, dependencies:list<array<string, mixed>>}
     *
     * @param array<string, string> $files
     * @param array<string, array{prelude?:string, body?:string}> $customAtRules
     * @param array<string, mixed>|callable(array<string, mixed>): array<string, mixed> $visitor
     * @param callable|null $resolver
     * @param array<string, callable> $functionVisitors
     */
    public function bundleWithDependencies(
        string $entry,
        array $files,
        array $customAtRules,
        array|callable $visitor = [],
        ?callable $resolver = null,
        array $functionVisitors = [],
    ): array {
        $css = (new CssBundler())->bundle($entry, $files, $resolver);

        return $this->transformWithDependencies($css, $customAtRules, $visitor, $functionVisitors);
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
     * @param array<string, mixed>|callable(array<string, mixed>): array<string, mixed> $visitor
     * @param array<string, callable> $functionVisitors
     */
    private function configure(array $customAtRules, array|callable $visitor, array $functionVisitors): void
    {
        $visitor = $this->resolveVisitor($visitor);

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

        $this->styleRuleVisitor = null;
        $styleVisitor = $visitor['Rule']['style'] ?? $visitor['style'] ?? null;
        if (is_callable($styleVisitor)) {
            $this->styleRuleVisitor = $styleVisitor;
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

        $this->functionExitVisitors = [];
        $this->genericFunctionExitVisitor = null;
        $functionExitConfig = $visitor['FunctionExit'] ?? [];
        if (is_callable($functionExitConfig)) {
            $this->genericFunctionExitVisitor = $functionExitConfig;
        } elseif (is_array($functionExitConfig)) {
            foreach ($functionExitConfig as $name => $callback) {
                if (is_callable($callback)) {
                    $this->functionExitVisitors[strtolower((string) $name)] = $callback;
                }
            }
        }

        $this->environmentVariableVisitors = [];
        $this->genericEnvironmentVariableVisitor = null;
        $environmentVariableConfig = $visitor['EnvironmentVariable'] ?? [];
        if (is_callable($environmentVariableConfig)) {
            $this->genericEnvironmentVariableVisitor = $environmentVariableConfig;
        } elseif (is_array($environmentVariableConfig)) {
            foreach ($environmentVariableConfig as $name => $callback) {
                if (is_string($name) && is_callable($callback)) {
                    $this->environmentVariableVisitors[$name] = $callback;
                }
            }
        }

        $this->variableVisitors = [];
        $this->genericVariableVisitor = null;
        $variableConfig = $visitor['Variable'] ?? [];
        if (is_callable($variableConfig)) {
            $this->genericVariableVisitor = $variableConfig;
        } elseif (is_array($variableConfig)) {
            foreach ($variableConfig as $name => $callback) {
                if (is_string($name) && is_callable($callback)) {
                    $this->variableVisitors[$name] = $callback;
                }
            }
        }

        $this->lengthVisitor = is_callable($visitor['Length'] ?? null) ? $visitor['Length'] : null;

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

    /**
     * @param array<string, mixed>|callable(array<string, mixed>): array<string, mixed> $visitor
     * @return array<string, mixed>
     */
    private function resolveVisitor(array|callable $visitor): array
    {
        if (!is_callable($visitor)) {
            return $visitor;
        }

        $dependencies = &$this->dependencies;
        $resolved = $visitor([
            'addDependency' => static function (array $dependency) use (&$dependencies): void {
                $dependencies[] = $dependency;
            },
        ]);
        if (!is_array($resolved)) {
            throw new \InvalidArgumentException('Visitor factory must return a visitor array');
        }

        return $resolved;
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
                        ? $this->emitUnknownRule($rule, null)
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
                        ? $this->emitUnknownRule($rule, $selectors)
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
                return $this->emitDeclarationRule([$selector], $this->declarationsToCss($declarations), false);
            }

            return $this->emitDeclarationRule([$selector], (string) $declarations, false);
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

        $prelude = trim($this->rewriteAtRulePreludeValue((string) ($rule['prelude'] ?? '')));
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

    /**
     * @param array<string, mixed> $visitor
     */
    private static function styleRuleVisitorCallback(array $visitor): ?callable
    {
        $ruleConfig = $visitor['Rule'] ?? null;
        if (is_callable($ruleConfig)) {
            return $ruleConfig;
        }

        if (is_array($ruleConfig) && is_callable($ruleConfig['style'] ?? null)) {
            return $ruleConfig['style'];
        }

        $styleConfig = $visitor['style'] ?? null;
        if (is_callable($styleConfig)) {
            return $styleConfig;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $visitor
     */
    private static function functionVisitorCallback(array $visitor, string $functionName): ?callable
    {
        $functionConfig = $visitor['Function'] ?? null;
        if (is_callable($functionConfig)) {
            return $functionConfig;
        }

        if (is_array($functionConfig)) {
            return self::caseInsensitiveCallback($functionConfig, $functionName);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $visitor
     */
    private static function functionExitVisitorCallback(array $visitor, string $functionName): ?callable
    {
        $functionConfig = $visitor['FunctionExit'] ?? null;
        if (is_callable($functionConfig)) {
            return $functionConfig;
        }

        if (is_array($functionConfig)) {
            return self::caseInsensitiveCallback($functionConfig, $functionName);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $visitor
     * @param array<string, mixed> $environmentVariable
     */
    private static function environmentVariableVisitorCallback(array $visitor, array $environmentVariable): ?callable
    {
        $config = $visitor['EnvironmentVariable'] ?? null;
        if (is_callable($config)) {
            return $config;
        }

        if (is_array($config)) {
            $callback = $config[self::environmentVariableCallbackName($environmentVariable)] ?? null;

            return is_callable($callback) ? $callback : null;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $visitor
     * @param array<string, mixed> $variable
     */
    private static function variableVisitorCallback(array $visitor, array $variable): ?callable
    {
        $config = $visitor['Variable'] ?? null;
        if (is_callable($config)) {
            return $config;
        }

        if (is_array($config)) {
            $callback = $config[self::variableCallbackName($variable)] ?? null;

            return is_callable($callback) ? $callback : null;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $visitor
     */
    private static function lengthVisitorCallback(array $visitor): ?callable
    {
        $lengthConfig = $visitor['Length'] ?? null;

        return is_callable($lengthConfig) ? $lengthConfig : null;
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
     * @param array<string, mixed> $current
     * @return list<array<string, mixed>>
     */
    private static function normalizeStyleRuleVisitorReplacement(array $current, mixed $replacement): array
    {
        if ($replacement === false || $replacement === []) {
            return [];
        }
        if (!is_array($replacement)) {
            throw new \InvalidArgumentException('Rule.style visitor must return a style rule array, list of style rules, or null');
        }
        if (array_is_list($replacement)) {
            $rules = [];
            foreach ($replacement as $item) {
                foreach (self::normalizeStyleRuleVisitorReplacement($current, $item) as $rule) {
                    $rules[] = $rule;
                }
            }

            return $rules;
        }
        if (($replacement['kind'] ?? null) === 'remove') {
            return [];
        }

        $rule = array_replace($current, $replacement);
        if (array_key_exists('selector', $replacement) && !array_key_exists('selectors', $replacement)) {
            $rule['selectors'] = array_values(array_filter(
                array_map('trim', explode(',', (string) $replacement['selector'])),
                static fn (string $selector): bool => $selector !== ''
            ));
        }

        return [$rule];
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
    private function emitDeclarationRule(array $selectors, string $declarations, bool $visitStyleRule = true): string
    {
        $declarations = trim($declarations);
        if ($declarations === '') {
            return '';
        }

        $entries = $this->declarationBlock->parseEntries($declarations);
        if ($entries === []) {
            return '';
        }

        $rule = [
            'kind' => 'style-rule',
            'selector' => implode(',', array_map('trim', $selectors)),
            'selectors' => array_values(array_map('trim', $selectors)),
            'declarations' => $entries,
        ];
        if ($visitStyleRule && $this->styleRuleVisitor !== null) {
            return $this->emitStyleRuleReplacement(($this->styleRuleVisitor)($rule, $this), $rule);
        }

        return $this->emitStyleRule($rule);
    }

    /**
     * @param array<string, mixed> $fallbackRule
     */
    private function emitStyleRuleReplacement(mixed $replacement, array $fallbackRule): string
    {
        if ($replacement === null) {
            return $this->emitStyleRule($fallbackRule);
        }
        if ($replacement === false || $replacement === []) {
            return '';
        }
        if (is_string($replacement)) {
            return $replacement;
        }
        if (!is_array($replacement)) {
            throw new \InvalidArgumentException('Rule.style visitor must return a style rule array, list of style rules, string, or null');
        }
        if (array_is_list($replacement)) {
            $css = '';
            foreach ($replacement as $item) {
                $css .= $this->emitStyleRuleReplacement($item, $fallbackRule);
            }

            return $css;
        }
        if (($replacement['kind'] ?? null) === 'remove') {
            return '';
        }

        $rule = array_replace($fallbackRule, $replacement);
        if (array_key_exists('selector', $replacement) && !array_key_exists('selectors', $replacement)) {
            $rule['selectors'] = array_values(array_filter(
                array_map('trim', explode(',', (string) $replacement['selector'])),
                static fn (string $selector): bool => $selector !== ''
            ));
        }

        return $this->emitStyleRule($rule);
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function emitStyleRule(array $rule): string
    {
        $selectors = $rule['selectors'] ?? null;
        if (!is_array($selectors) || $selectors === []) {
            $selectors = [(string) ($rule['selector'] ?? '')];
        }

        $entries = $rule['declarations'] ?? [];
        if (!is_array($entries)) {
            $entries = $this->declarationBlock->parseEntries((string) $entries);
        }

        $body = '';
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $property = (string) ($entry['property'] ?? '');
            if ($property === '') {
                continue;
            }
            $body .= $property . ':' . $this->rewriteDeclarationValue((string) ($entry['value'] ?? ''));
            if (!empty($entry['important'])) {
                $body .= ' !important';
            }
            $body .= ';';
        }

        if ($body === '') {
            return '';
        }

        return implode(',', array_map('trim', $selectors)) . '{' . $body . '}';
    }

    private function rewriteDeclarationValue(string $value): string
    {
        return $this->rewriteValueTokens($this->rewriteValueFunctions($value));
    }

    private function rewriteAtRulePreludeValue(string $value): string
    {
        return $this->rewriteValueFunctions($value);
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
            $valueReplacement = $this->callStructuredValueVisitor($name, $argumentsCss, $name . '(' . $argumentsCss . ')');
            if ($valueReplacement !== null) {
                $output .= $this->serializeVisitorValue($valueReplacement);
                $cursor = $close + 1;
                continue;
            }

            $replacement = $this->callFunctionVisitor($name, $this->parseFunctionArguments($argumentsCss), $name . '(' . $argumentsCss . ')');
            if ($replacement === null) {
                $output .= $this->serializeVisitorValue($this->visitFunctionExit($name, $argumentsCss, $name . '(' . $argumentsCss . ')'));
            } else {
                $output .= $replacement;
            }
            $cursor = $close + 1;
        }

        return $output;
    }

    private function visitFunctionExit(string $name, string $argumentsCss, string $raw): mixed
    {
        $arguments = $this->parseFunctionArgumentValues($argumentsCss);
        $function = [
            'type' => 'function',
            'name' => strtolower($name),
            'arguments' => $arguments,
            'raw' => $raw,
        ];

        $replacement = $this->callFunctionExitVisitor($name, $function);
        if ($replacement !== null) {
            return $this->applyValueVisitors($this->normalizeVisitorValue($replacement));
        }

        return [
            'type' => 'raw',
            'value' => $name . '(' . implode(',', array_map(fn (mixed $argument): string => $this->serializeVisitorValue($argument), $arguments)) . ')',
        ];
    }

    /**
     * @param array<string, mixed> $function
     */
    private function callFunctionExitVisitor(string $name, array $function): mixed
    {
        $visitor = $this->functionExitVisitors[strtolower($name)] ?? $this->genericFunctionExitVisitor;
        if ($visitor === null) {
            return null;
        }

        return $visitor($function, $this);
    }

    private function callStructuredValueVisitor(string $name, string $argumentsCss, string $raw): mixed
    {
        $lower = strtolower($name);
        if ($lower === 'env' && ($this->environmentVariableVisitors !== [] || $this->genericEnvironmentVariableVisitor !== null)) {
            $replacement = $this->callEnvironmentVariableVisitor($this->parseEnvironmentVariable($argumentsCss, $raw));

            return $replacement === null ? null : $this->applyValueVisitors($this->normalizeVisitorValue($replacement));
        }

        if ($lower === 'var' && ($this->variableVisitors !== [] || $this->genericVariableVisitor !== null)) {
            $replacement = $this->callVariableVisitor($this->parseVariable($argumentsCss, $raw));

            return $replacement === null ? null : $this->applyValueVisitors($this->normalizeVisitorValue($replacement));
        }

        return null;
    }

    /**
     * @param array<string, mixed> $environmentVariable
     */
    private function callEnvironmentVariableVisitor(array $environmentVariable): mixed
    {
        $visitor = $this->environmentVariableVisitors[self::environmentVariableCallbackName($environmentVariable)] ?? $this->genericEnvironmentVariableVisitor;
        if ($visitor === null) {
            return null;
        }

        return $visitor($environmentVariable, $this);
    }

    /**
     * @param array<string, mixed> $variable
     */
    private function callVariableVisitor(array $variable): mixed
    {
        $visitor = $this->variableVisitors[self::variableCallbackName($variable)] ?? $this->genericVariableVisitor;
        if ($visitor === null) {
            return null;
        }

        return $visitor($variable, $this);
    }

    private function applyValueVisitors(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (($value['type'] ?? null) === 'length') {
            $length = $this->lengthComponents($value);
            if ($length !== null && $this->lengthVisitor !== null) {
                $replacement = ($this->lengthVisitor)($length, $this);
                if ($replacement !== null) {
                    return $this->normalizeLengthValue($replacement);
                }
            }
        }

        return $value;
    }

    /**
     * @return array{unit:string,value:int|float}|null
     */
    private function lengthComponents(array $value): ?array
    {
        if (($value['type'] ?? null) !== 'length') {
            return null;
        }

        if (isset($value['value']) && is_array($value['value'])) {
            $unit = $value['value']['unit'] ?? null;
            $number = $value['value']['value'] ?? null;
        } else {
            $unit = $value['unit'] ?? null;
            $number = $value['value'] ?? null;
        }

        if (!is_string($unit) || (!is_int($number) && !is_float($number))) {
            return null;
        }

        return ['unit' => strtolower($unit), 'value' => $number];
    }

    private function normalizeLengthValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (($value['type'] ?? null) === 'length') {
            return $value;
        }

        if (isset($value['unit'], $value['value']) && is_string($value['unit']) && (is_int($value['value']) || is_float($value['value']))) {
            return [
                'type' => 'length',
                'unit' => strtolower($value['unit']),
                'value' => $value['value'],
            ];
        }

        return $value;
    }

    private function normalizeVisitorValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (isset($value['raw']) && is_string($value['raw'])) {
            return [
                'type' => 'raw',
                'value' => $value['raw'],
            ];
        }

        if (isset($value['unit'], $value['value']) && is_string($value['unit']) && (is_int($value['value']) || is_float($value['value']))) {
            return $this->normalizeLengthValue($value);
        }

        return $value;
    }

    private function serializeVisitorValue(mixed $value): string
    {
        if (is_string($value) || is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (!is_array($value)) {
            return '';
        }

        if (isset($value['raw']) && is_string($value['raw'])) {
            return $value['raw'];
        }

        $type = $value['type'] ?? null;
        if ($type === 'length') {
            $length = $this->lengthComponents($value);
            if ($length !== null) {
                return $this->formatNumber($length['value']) . $length['unit'];
            }
        }
        if ($type === 'token' && isset($value['value']) && is_array($value['value'])) {
            $token = $value['value'];
            if (($token['type'] ?? null) === 'string') {
                return isset($value['raw']) && is_string($value['raw'])
                    ? $value['raw']
                    : '"' . addcslashes((string) ($token['value'] ?? ''), "\\\"") . '"';
            }
            if (($token['type'] ?? null) === 'ident') {
                return (string) ($token['value'] ?? '');
            }
        }
        if ($type === 'raw') {
            return (string) ($value['value'] ?? '');
        }
        if ($type === 'var') {
            return $this->serializeVariableValue(is_array($value['value'] ?? null) ? $value['value'] : $value);
        }
        if ($type === 'env') {
            return $this->serializeEnvironmentVariableValue(is_array($value['value'] ?? null) ? $value['value'] : $value);
        }
        if ($type === 'function' && isset($value['value']) && is_array($value['value'])) {
            $function = $value['value'];
            $arguments = $function['arguments'] ?? [];
            if (!is_array($arguments)) {
                $arguments = [];
            }

            return (string) ($function['name'] ?? '') . '(' . implode(',', array_map(fn (mixed $argument): string => $this->serializeVisitorValue($argument), $arguments)) . ')';
        }

        return (string) ($value['value'] ?? '');
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

            if ($char === '@' && preg_match('/@(--[-_a-zA-Z0-9]+|-?[_a-zA-Z][-_a-zA-Z0-9]*)/A', substr($value, $i), $matches) === 1) {
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
     * @return list<mixed>
     */
    private function parseFunctionArgumentValues(string $arguments): array
    {
        return array_map(
            fn (string $argument): mixed => $this->parseSingleFunctionArgumentValue($argument),
            $this->splitTopLevel($arguments, ',')
        );
    }

    private function parseSingleFunctionArgumentValue(string $argument): mixed
    {
        $argument = trim($argument);
        if ($argument === '') {
            return ['type' => 'raw', 'value' => ''];
        }

        if (preg_match('/^([a-zA-Z_-][-_a-zA-Z0-9]*)\(/', $argument, $matches) === 1) {
            $name = $matches[1];
            $open = strlen($name);
            $close = $this->findMatchingParen($argument, $open);
            if ($close === strlen($argument) - 1) {
                $argumentsCss = substr($argument, $open + 1, $close - $open - 1);
                $valueReplacement = $this->callStructuredValueVisitor($name, $argumentsCss, $argument);
                if ($valueReplacement !== null) {
                    return $valueReplacement;
                }

                $replacement = $this->callFunctionVisitor($name, $this->parseFunctionArguments($argumentsCss), $argument);

                return $replacement === null
                    ? $this->visitFunctionExit($name, $argumentsCss, $argument)
                    : ['type' => 'raw', 'value' => $replacement];
            }
        }

        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))([a-zA-Z%]+)$/', $argument, $matches) === 1) {
            return $this->applyValueVisitors([
                'type' => 'length',
                'unit' => strtolower($matches[2]),
                'value' => (float) $matches[1],
            ]);
        }

        if (
            strlen($argument) >= 2
            && (($argument[0] === '"' && $argument[strlen($argument) - 1] === '"') || ($argument[0] === "'" && $argument[strlen($argument) - 1] === "'"))
        ) {
            return [
                'type' => 'token',
                'raw' => $argument,
                'value' => [
                    'type' => 'string',
                    'value' => stripcslashes(substr($argument, 1, -1)),
                ],
            ];
        }

        if (preg_match('/^-?[_a-zA-Z][-_a-zA-Z0-9]*$/', $argument) === 1) {
            return [
                'type' => 'token',
                'value' => [
                    'type' => 'ident',
                    'value' => $argument,
                ],
            ];
        }

        return ['type' => 'raw', 'value' => $argument];
    }

    /**
     * @return array{name:array<string, string>, fallback:list<mixed>|null, raw:string}
     */
    private function parseEnvironmentVariable(string $argumentsCss, string $raw): array
    {
        $parts = $this->splitTopLevel($argumentsCss, ',');
        $name = trim($parts[0] ?? '');

        return [
            'name' => str_starts_with($name, '--')
                ? ['type' => 'custom', 'ident' => $name]
                : ['type' => 'ua', 'value' => $name],
            'fallback' => count($parts) > 1 ? $this->parseFallbackTokenList(implode(',', array_slice($parts, 1))) : null,
            'raw' => $raw,
        ];
    }

    /**
     * @return array{name:array{ident:string}, fallback:list<mixed>|null, raw:string}
     */
    private function parseVariable(string $argumentsCss, string $raw): array
    {
        $parts = $this->splitTopLevel($argumentsCss, ',');

        return [
            'name' => ['ident' => trim($parts[0] ?? '')],
            'fallback' => count($parts) > 1 ? $this->parseFallbackTokenList(implode(',', array_slice($parts, 1))) : null,
            'raw' => $raw,
        ];
    }

    /**
     * @return list<mixed>
     */
    private function parseFallbackTokenList(string $css): array
    {
        $css = trim($css);

        return $css === '' ? [] : [['type' => 'raw', 'value' => $css]];
    }

    /**
     * @param array<string, mixed> $environmentVariable
     */
    private static function environmentVariableCallbackName(array $environmentVariable): string
    {
        $name = $environmentVariable['name'] ?? [];
        if (is_array($name)) {
            if (isset($name['ident']) && is_string($name['ident'])) {
                return $name['ident'];
            }
            if (isset($name['value']) && is_string($name['value'])) {
                return $name['value'];
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $variable
     */
    private static function variableCallbackName(array $variable): string
    {
        $name = $variable['name'] ?? [];

        return is_array($name) && isset($name['ident']) && is_string($name['ident']) ? $name['ident'] : '';
    }

    /**
     * @param array<string, mixed> $variable
     */
    private function serializeVariableValue(array $variable): string
    {
        $name = self::variableCallbackName($variable);
        $fallback = $variable['fallback'] ?? null;
        if (!is_array($fallback) || $fallback === []) {
            return 'var(' . $name . ')';
        }

        return 'var(' . $name . ',' . implode(',', array_map(fn (mixed $value): string => $this->serializeVisitorValue($value), $fallback)) . ')';
    }

    /**
     * @param array<string, mixed> $environmentVariable
     */
    private function serializeEnvironmentVariableValue(array $environmentVariable): string
    {
        $name = self::environmentVariableCallbackName($environmentVariable);
        $fallback = $environmentVariable['fallback'] ?? null;
        if (!is_array($fallback) || $fallback === []) {
            return 'env(' . $name . ')';
        }

        return 'env(' . $name . ',' . implode(',', array_map(fn (mixed $value): string => $this->serializeVisitorValue($value), $fallback)) . ')';
    }

    private function formatNumber(int|float $value): string
    {
        if ((float) $value === floor((float) $value)) {
            return (string) (int) $value;
        }

        return rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
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

            if (preg_match('/^--[-_a-zA-Z0-9]+$/', $token) === 1) {
                $tokens[] = [
                    'type' => 'dashed-ident',
                    'value' => $token,
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
