<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class CustomAtRuleTransformer
{
    /** @var array<string, array<string, mixed>> */
    private array $customAtRules = [];

    /** @var callable|null */
    private $ruleVisitor = null;

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

    /** @var callable|null */
    private $mediaRuleVisitor = null;

    /** @var callable|null */
    private $mediaQueryVisitor = null;

    /** @var callable|null */
    private $mediaQueryExitVisitor = null;

    /** @var callable|null */
    private $supportsConditionVisitor = null;

    /** @var callable|null */
    private $supportsConditionExitVisitor = null;

    /** @var callable|null */
    private $styleSheetVisitor = null;

    /** @var callable|null */
    private $styleSheetExitVisitor = null;

    /** @var callable|null */
    private $selectorVisitor = null;

    /** @var array<string, mixed>|callable|null */
    private $declarationVisitorConfig = null;

    /** @var array<string, mixed>|callable|null */
    private $declarationExitVisitorConfig = null;

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

    /** @var callable|null */
    private $urlVisitor = null;

    /** @var callable|null */
    private $colorVisitor = null;

    /** @var callable|null */
    private $dashedIdentVisitor = null;

    /** @var callable|null */
    private $customIdentVisitor = null;

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
                    $changed = false;
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

                            $changed = true;
                            foreach (self::normalizeStyleRuleVisitorReplacement($currentRule, $replacement) as $nextRule) {
                                $nextRules[] = $nextRule;
                            }
                        }

                        $rules = $nextRules;
                        if ($rules === []) {
                            break;
                        }
                    }

                    if (!$changed) {
                        return null;
                    }
                    if ($rules === []) {
                        return [];
                    }

                    return count($rules) === 1 ? $rules[0] : $rules;
                },
                'media' => static function (array $rule, self $transformer) use ($visitors): mixed {
                    $rules = [$rule];
                    $changed = false;
                    foreach ($visitors as $visitor) {
                        $callback = self::mediaRuleVisitorCallback($visitor);
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

                            $changed = true;
                            foreach (self::normalizeRuleVisitorReplacement($replacement, 'Rule.media') as $nextRule) {
                                $nextRules[] = $nextRule;
                            }
                        }

                        $rules = $nextRules;
                        if ($rules === []) {
                            break;
                        }
                    }

                    if (!$changed) {
                        return null;
                    }

                    return count($rules) === 1 ? $rules[0] : $rules;
                },
            ],
            'StyleSheet' => static function (array $stylesheet, self $transformer) use ($visitors): mixed {
                $current = $stylesheet;
                $changed = false;
                foreach ($visitors as $visitor) {
                    $callback = self::styleSheetVisitorCallback($visitor, 'StyleSheet');
                    if ($callback === null) {
                        continue;
                    }

                    $replacement = $callback($current, $transformer);
                    if ($replacement !== null) {
                        if (!is_array($replacement)) {
                            throw new \InvalidArgumentException('StyleSheet visitor must return a stylesheet array or null');
                        }
                        $current = $replacement;
                        $changed = true;
                    }
                }

                return $changed ? $current : null;
            },
            'StyleSheetExit' => static function (array $stylesheet, self $transformer) use ($visitors): mixed {
                $current = $stylesheet;
                $changed = false;
                foreach ($visitors as $visitor) {
                    $callback = self::styleSheetVisitorCallback($visitor, 'StyleSheetExit');
                    if ($callback === null) {
                        continue;
                    }

                    $replacement = $callback($current, $transformer);
                    if ($replacement !== null) {
                        if (!is_array($replacement)) {
                            throw new \InvalidArgumentException('StyleSheetExit visitor must return a stylesheet array or null');
                        }
                        $current = $replacement;
                        $changed = true;
                    }
                }

                return $changed ? $current : null;
            },
            'MediaQuery' => static function (array $query, self $transformer) use ($visitors): mixed {
                $queries = [$query];
                $changed = false;
                foreach ($visitors as $visitor) {
                    $callback = self::mediaQueryVisitorCallback($visitor, 'MediaQuery');
                    if ($callback === null) {
                        continue;
                    }

                    $nextQueries = [];
                    foreach ($queries as $currentQuery) {
                        $replacement = $callback($currentQuery, $transformer);
                        if ($replacement === null) {
                            $nextQueries[] = $currentQuery;
                            continue;
                        }

                        $changed = true;
                        foreach (self::normalizeMediaQueryVisitorReplacement($replacement, 'MediaQuery') as $nextQuery) {
                            $nextQueries[] = $nextQuery;
                        }
                    }
                    $queries = $nextQueries;
                }

                if (!$changed) {
                    return null;
                }

                return count($queries) === 1 ? $queries[0] : $queries;
            },
            'MediaQueryExit' => static function (array $query, self $transformer) use ($visitors): mixed {
                $queries = [$query];
                $changed = false;
                foreach ($visitors as $visitor) {
                    $callback = self::mediaQueryVisitorCallback($visitor, 'MediaQueryExit');
                    if ($callback === null) {
                        continue;
                    }

                    $nextQueries = [];
                    foreach ($queries as $currentQuery) {
                        $replacement = $callback($currentQuery, $transformer);
                        if ($replacement === null) {
                            $nextQueries[] = $currentQuery;
                            continue;
                        }

                        $changed = true;
                        foreach (self::normalizeMediaQueryVisitorReplacement($replacement, 'MediaQueryExit') as $nextQuery) {
                            $nextQueries[] = $nextQuery;
                        }
                    }
                    $queries = $nextQueries;
                }

                if (!$changed) {
                    return null;
                }

                return count($queries) === 1 ? $queries[0] : $queries;
            },
            'SupportsCondition' => static function (array $condition, self $transformer) use ($visitors): mixed {
                $current = $condition;
                $changed = false;
                foreach ($visitors as $visitor) {
                    $callback = self::supportsConditionVisitorCallback($visitor, 'SupportsCondition');
                    if ($callback === null) {
                        continue;
                    }

                    $replacement = $callback($current, $transformer);
                    if ($replacement !== null) {
                        if (!is_array($replacement)) {
                            throw new \InvalidArgumentException('SupportsCondition visitor must return a condition array or null');
                        }
                        $current = $replacement;
                        $changed = true;
                    }
                }

                return $changed ? $current : null;
            },
            'SupportsConditionExit' => static function (array $condition, self $transformer) use ($visitors): mixed {
                $current = $condition;
                $changed = false;
                foreach ($visitors as $visitor) {
                    $callback = self::supportsConditionVisitorCallback($visitor, 'SupportsConditionExit');
                    if ($callback === null) {
                        continue;
                    }

                    $replacement = $callback($current, $transformer);
                    if ($replacement !== null) {
                        if (!is_array($replacement)) {
                            throw new \InvalidArgumentException('SupportsConditionExit visitor must return a condition array or null');
                        }
                        $current = $replacement;
                        $changed = true;
                    }
                }

                return $changed ? $current : null;
            },
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
                'dimension' => static function (array $token, self $transformer) use ($visitors): mixed {
                    foreach ($visitors as $visitor) {
                        $callback = self::tokenVisitorCallback($visitor, 'dimension');
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
            'Selector' => static function (array $selector, self $transformer) use ($visitors): mixed {
                $current = $selector;
                $changed = false;
                foreach ($visitors as $visitor) {
                    $callback = self::selectorVisitorCallback($visitor);
                    if ($callback === null) {
                        continue;
                    }

                    $replacement = $callback($current, $transformer);
                    if ($replacement !== null) {
                        $current = $transformer->normalizeSelectorVisitorReplacement($replacement);
                        $changed = true;
                    }
                }

                return $changed ? $current : null;
            },
            'Declaration' => static function (array $declaration, self $transformer) use ($visitors): mixed {
                $declarations = [$declaration];
                $changed = false;
                foreach ($visitors as $visitor) {
                    $nextDeclarations = [];
                    foreach ($declarations as $currentDeclaration) {
                        $callback = self::declarationVisitorCallback($visitor, $currentDeclaration);
                        if ($callback === null) {
                            $nextDeclarations[] = $currentDeclaration;
                            continue;
                        }

                        $replacement = $callback($currentDeclaration, $transformer);
                        if ($replacement === null) {
                            $nextDeclarations[] = $currentDeclaration;
                            continue;
                        }

                        $changed = true;
                        foreach (self::normalizeDeclarationVisitorList($replacement) as $nextDeclaration) {
                            $nextDeclarations[] = $nextDeclaration;
                        }
                    }

                    $declarations = $nextDeclarations;
                    if ($declarations === []) {
                        break;
                    }
                }

                if (!$changed) {
                    return null;
                }

                return count($declarations) === 1 ? $declarations[0] : $declarations;
            },
            'DeclarationExit' => static function (array $declaration, self $transformer) use ($visitors): mixed {
                $declarations = [$declaration];
                $changed = false;
                foreach ($visitors as $visitor) {
                    $nextDeclarations = [];
                    foreach ($declarations as $currentDeclaration) {
                        $callback = self::declarationExitVisitorCallback($visitor, $currentDeclaration);
                        if ($callback === null) {
                            $nextDeclarations[] = $currentDeclaration;
                            continue;
                        }

                        $replacement = $callback($currentDeclaration, $transformer);
                        if ($replacement === null) {
                            $nextDeclarations[] = $currentDeclaration;
                            continue;
                        }

                        $changed = true;
                        foreach (self::normalizeDeclarationVisitorList($replacement) as $nextDeclaration) {
                            $nextDeclarations[] = $nextDeclaration;
                        }
                    }

                    $declarations = $nextDeclarations;
                    if ($declarations === []) {
                        break;
                    }
                }

                if (!$changed) {
                    return null;
                }

                return count($declarations) === 1 ? $declarations[0] : $declarations;
            },
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
            'Color' => static function (array $color, self $transformer) use ($visitors): mixed {
                $value = $color;
                $changed = false;
                foreach ($visitors as $visitor) {
                    $callback = self::colorVisitorCallback($visitor);
                    if ($callback === null) {
                        continue;
                    }

                    $replacement = $callback($value, $transformer);
                    if ($replacement !== null) {
                        $value = $transformer->normalizeColorVisitorValue($replacement);
                        $changed = true;
                    }
                }

                return $changed ? $value : null;
            },
            'Url' => static function (array $url, self $transformer) use ($visitors): mixed {
                $value = $url;
                $changed = false;
                foreach ($visitors as $visitor) {
                    $callback = self::urlVisitorCallback($visitor);
                    if ($callback === null) {
                        continue;
                    }

                    $replacement = $callback($value, $transformer);
                    if ($replacement !== null) {
                        $value = $transformer->normalizeUrlVisitorValue($replacement, $value);
                        $changed = true;
                    }
                }

                return $changed ? $value : null;
            },
            'DashedIdent' => static function (string $ident, self $transformer) use ($visitors): mixed {
                $value = $ident;
                $changed = false;
                foreach ($visitors as $visitor) {
                    $callback = self::dashedIdentVisitorCallback($visitor);
                    if ($callback === null) {
                        continue;
                    }

                    $replacement = $callback($value, $transformer);
                    if ($replacement !== null) {
                        $value = (string) $replacement;
                        $changed = true;
                    }
                }

                return $changed ? $value : null;
            },
            'CustomIdent' => static function (string $ident, self $transformer) use ($visitors): mixed {
                $value = $ident;
                $changed = false;
                foreach ($visitors as $visitor) {
                    $callback = self::customIdentVisitorCallback($visitor);
                    if ($callback === null) {
                        continue;
                    }

                    $replacement = $callback($value, $transformer);
                    if ($replacement !== null) {
                        $value = (string) $replacement;
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
        $css = $this->stripComments($css);
        $this->callStyleSheetVisitor($this->stylesheetVisitorFromCss($css));
        $code = $this->minifier->minify($this->processRuleList($css));
        $code = $this->applyStyleSheetExitVisitor($code);

        return [
            'code' => $code,
            'dependencies' => $this->dependencies,
        ];
    }

    /**
     * @param array<string, mixed>|callable(array<string, mixed>): array<string, mixed> $visitor
     * @param array<string, callable> $functionVisitors
     */
    public function transformStyleAttribute(string $declarations, array|callable $visitor = [], array $functionVisitors = []): string
    {
        return $this->transformStyleAttributeWithDependencies($declarations, $visitor, $functionVisitors)['code'];
    }

    /**
     * @return array{code:string, dependencies:list<array<string, mixed>>}
     *
     * @param array<string, mixed>|callable(array<string, mixed>): array<string, mixed> $visitor
     * @param array<string, callable> $functionVisitors
     */
    public function transformStyleAttributeWithDependencies(string $declarations, array|callable $visitor = [], array $functionVisitors = []): array
    {
        $this->dependencies = [];
        $this->configure([], $visitor, $functionVisitors);
        $entries = $this->processDeclarationEntries($this->declarationBlock->parseEntries($declarations));

        return [
            'code' => $this->emitDeclarationEntriesBody($entries),
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

        $ruleConfig = $visitor['Rule'] ?? null;
        $ruleSubVisitors = is_array($ruleConfig) ? $ruleConfig : [];

        $this->ruleVisitor = is_callable($ruleConfig) ? $ruleConfig : null;
        $this->ruleVisitors = [];
        $this->genericRuleVisitor = null;
        $customVisitors = $ruleSubVisitors['custom'] ?? $visitor['custom'] ?? [];
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
            if (is_string($name) && is_callable($callback) && !in_array($name, ['Rule', 'Function', 'Token', 'custom', 'unknown', 'media'], true)) {
                $this->ruleVisitors[strtolower($name)] = $callback;
            }
        }

        $this->unknownRuleVisitors = [];
        $this->genericUnknownRuleVisitor = null;
        $unknownVisitors = $ruleSubVisitors['unknown'] ?? $visitor['unknown'] ?? [];
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
        $styleVisitor = $ruleSubVisitors['style'] ?? $visitor['style'] ?? null;
        if (is_callable($styleVisitor)) {
            $this->styleRuleVisitor = $styleVisitor;
        }

        $this->mediaRuleVisitor = null;
        $mediaVisitor = $ruleSubVisitors['media'] ?? $visitor['media'] ?? null;
        if (is_callable($mediaVisitor)) {
            $this->mediaRuleVisitor = $mediaVisitor;
        }

        $this->mediaQueryVisitor = is_callable($visitor['MediaQuery'] ?? null) ? $visitor['MediaQuery'] : null;
        $this->mediaQueryExitVisitor = is_callable($visitor['MediaQueryExit'] ?? null) ? $visitor['MediaQueryExit'] : null;
        $this->supportsConditionVisitor = is_callable($visitor['SupportsCondition'] ?? null) ? $visitor['SupportsCondition'] : null;
        $this->supportsConditionExitVisitor = is_callable($visitor['SupportsConditionExit'] ?? null) ? $visitor['SupportsConditionExit'] : null;

        $this->styleSheetVisitor = is_callable($visitor['StyleSheet'] ?? null) ? $visitor['StyleSheet'] : null;
        $this->styleSheetExitVisitor = is_callable($visitor['StyleSheetExit'] ?? null) ? $visitor['StyleSheetExit'] : null;

        $this->selectorVisitor = is_callable($visitor['Selector'] ?? null) ? $visitor['Selector'] : null;

        $this->declarationVisitorConfig = $visitor['Declaration'] ?? null;
        $this->declarationExitVisitorConfig = $visitor['DeclarationExit'] ?? null;

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
        $this->colorVisitor = is_callable($visitor['Color'] ?? null) ? $visitor['Color'] : null;
        $this->urlVisitor = is_callable($visitor['Url'] ?? null) ? $visitor['Url'] : null;
        $this->dashedIdentVisitor = is_callable($visitor['DashedIdent'] ?? null) ? $visitor['DashedIdent'] : null;
        $this->customIdentVisitor = is_callable($visitor['CustomIdent'] ?? null) ? $visitor['CustomIdent'] : null;

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

    /**
     * @param array{rules:list<array<string, mixed>>} $stylesheet
     */
    private function callStyleSheetVisitor(array $stylesheet): void
    {
        if ($this->styleSheetVisitor === null) {
            return;
        }

        $replacement = ($this->styleSheetVisitor)($stylesheet, $this);
        if ($replacement !== null && !is_array($replacement)) {
            throw new \InvalidArgumentException('StyleSheet visitor must return a stylesheet array or null');
        }
    }

    private function applyStyleSheetExitVisitor(string $code): string
    {
        if ($this->styleSheetExitVisitor === null) {
            return $code;
        }

        $stylesheet = $this->stylesheetVisitorFromCss($code);
        $replacement = ($this->styleSheetExitVisitor)($stylesheet, $this);
        if ($replacement === null) {
            return $code;
        }
        if (!is_array($replacement)) {
            throw new \InvalidArgumentException('StyleSheetExit visitor must return a stylesheet array or null');
        }

        return $this->minifier->minify($this->stylesheetVisitorToCss($replacement));
    }

    /**
     * @return array{rules:list<array<string, mixed>>}
     */
    private function stylesheetVisitorFromCss(string $css): array
    {
        $rules = [];
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
                    $rules[] = $this->stylesheetRawRule($statement . ';');
                }
                $cursor = $nextStatement + 1;
                continue;
            }

            if ($nextBlock === null) {
                $tail = trim(substr($css, $cursor));
                if ($tail !== '') {
                    $rules[] = $this->stylesheetRawRule($tail);
                }
                break;
            }

            $prelude = trim(substr($css, $cursor, $nextBlock - $cursor));
            $close = $this->findMatchingBrace($css, $nextBlock);
            $body = substr($css, $nextBlock + 1, $close - $nextBlock - 1);
            $rules[] = str_starts_with($prelude, '@')
                ? $this->stylesheetRawRule($prelude . '{' . $body . '}')
                : $this->stylesheetStyleRule($prelude, $body);
            $cursor = $close + 1;
        }

        return ['rules' => $rules];
    }

    /**
     * @return array<string, mixed>
     */
    private function stylesheetRawRule(string $css): array
    {
        return [
            'type' => 'raw',
            'raw' => $css,
            'kind' => 'raw',
            'css' => $css,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stylesheetStyleRule(string $selectorList, string $body): array
    {
        $selectors = $this->splitTopLevel($selectorList, ',');
        if ($selectors === []) {
            $selectors = [trim($selectorList)];
        }

        try {
            $entries = $this->declarationBlock->parseEntries($body);
        } catch (\InvalidArgumentException) {
            $entries = [];
        }
        $normal = [];
        $important = [];
        foreach ($entries as $entry) {
            if ($entry['important']) {
                $important[] = $entry;
            } else {
                $normal[] = $entry;
            }
        }

        return [
            'type' => 'style',
            'kind' => 'style-rule',
            'selector' => implode(',', $selectors),
            'selectors' => $selectors,
            'declarations' => $entries,
            'value' => [
                'selectors' => array_map(fn (string $selector): array => $this->selectorComponentsFromString($selector), $selectors),
                'declarations' => [
                    'declarations' => $normal,
                    'importantDeclarations' => $important,
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function selectorComponentsFromString(string $selector): array
    {
        $components = [];
        $selector = trim($selector);
        $length = strlen($selector);

        for ($cursor = 0; $cursor < $length;) {
            $char = $selector[$cursor];
            if (ctype_space($char)) {
                while ($cursor < $length && ctype_space($selector[$cursor])) {
                    $cursor++;
                }
                $components[] = ['type' => 'combinator', 'value' => 'descendant'];
                continue;
            }

            if ($char === '.' && preg_match('/\.((?:\\\\.|[-_a-zA-Z0-9])+)/A', substr($selector, $cursor), $matches) === 1) {
                $components[] = ['type' => 'class', 'name' => str_replace('\\\\', '\\', $matches[1])];
                $cursor += strlen($matches[0]);
                continue;
            }

            if (in_array($char, ['>', '+', '~'], true)) {
                $components[] = ['type' => 'combinator', 'value' => $char];
                $cursor++;
                continue;
            }

            if ($char === ':' && preg_match('/\:([-_a-zA-Z0-9]+)/A', substr($selector, $cursor), $matches) === 1) {
                $kind = $matches[1];
                $cursor += strlen($matches[0]);
                if (($selector[$cursor] ?? '') === '(') {
                    $close = $this->findMatchingParen($selector, $cursor);
                    if ($close !== null) {
                        $arguments = substr($selector, $cursor + 1, $close - $cursor - 1);
                        $components[] = $this->selectorFunctionalPseudoComponent($kind, $arguments);
                        $cursor = $close + 1;
                        continue;
                    }
                }

                $components[] = ['type' => 'pseudo-class', 'kind' => $kind];
                continue;
            }

            if (preg_match('/(?:--[-_a-zA-Z0-9]+|[_a-zA-Z][-_a-zA-Z0-9]*)/A', substr($selector, $cursor), $matches) === 1) {
                $components[] = ['type' => 'type', 'name' => $matches[0]];
                $cursor += strlen($matches[0]);
                continue;
            }

            $components[] = ['type' => 'raw', 'value' => $char];
            $cursor++;
        }

        return $components;
    }

    /**
     * @return array<string, mixed>
     */
    private function selectorFunctionalPseudoComponent(string $kind, string $arguments): array
    {
        $component = [
            'type' => 'pseudo-class',
            'kind' => $kind,
            'arguments' => trim($arguments),
        ];
        $lower = strtolower($kind);

        if (in_array($lower, ['nth-child', 'nth-last-child', 'nth-of-type', 'nth-last-of-type'], true)) {
            [$formula, $ofSelectorList] = $this->splitNthSelectorArgument($arguments);
            $component['formula'] = $this->normalizeNthFormula($formula);
            if ($ofSelectorList !== null) {
                $component['of'] = array_map(
                    fn (string $selector): array => $this->selectorComponentsFromString($selector),
                    $this->splitTopLevel($ofSelectorList, ',')
                );
            }

            return $component;
        }

        if (in_array($lower, ['is', 'not', 'where', 'has'], true)) {
            $component['selectors'] = array_map(
                fn (string $selector): array => $this->selectorComponentsFromString($selector),
                $this->splitTopLevel($arguments, ',')
            );
        }

        return $component;
    }

    /**
     * @return array{0:string,1:string|null}
     */
    private function splitNthSelectorArgument(string $arguments): array
    {
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
        $length = strlen($arguments);
        for ($i = 0; $i < $length; $i++) {
            $char = $arguments[$i];
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
                continue;
            }
            if ($char === '(') {
                $parenDepth++;
                continue;
            }
            if ($char === ')') {
                $parenDepth = max(0, $parenDepth - 1);
                continue;
            }
            if ($char === '[') {
                $bracketDepth++;
                continue;
            }
            if ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
                continue;
            }

            if (
                $parenDepth === 0
                && $bracketDepth === 0
                && strncasecmp(substr($arguments, $i, 4), ' of ', 4) === 0
            ) {
                return [trim(substr($arguments, 0, $i)), trim(substr($arguments, $i + 4))];
            }
        }

        return [trim($arguments), null];
    }

    private function normalizeNthFormula(string $formula): string
    {
        $formula = strtolower(trim(preg_replace('/\s+/', '', $formula) ?? $formula));

        return match ($formula) {
            'even' => '2n',
            'odd' => '2n+1',
            default => $formula,
        };
    }

    /**
     * @param array<string, mixed> $stylesheet
     */
    private function stylesheetVisitorToCss(array $stylesheet): string
    {
        $rules = $stylesheet['rules'] ?? [];
        if (!is_array($rules)) {
            throw new \InvalidArgumentException('Stylesheet replacement must contain a rules array');
        }

        $css = '';
        foreach ($rules as $rule) {
            $css .= $this->stylesheetVisitorRuleToCss($rule);
        }

        return $css;
    }

    private function stylesheetVisitorRuleToCss(mixed $rule): string
    {
        if (is_string($rule)) {
            return $rule;
        }
        if (!is_array($rule)) {
            return '';
        }
        if (($rule['kind'] ?? null) === 'raw' && isset($rule['css']) && is_string($rule['css'])) {
            return $rule['css'];
        }
        if (($rule['type'] ?? null) === 'raw' && isset($rule['raw']) && is_string($rule['raw'])) {
            return $rule['raw'];
        }
        if (($rule['type'] ?? null) !== 'style' && ($rule['kind'] ?? null) !== 'style-rule') {
            return isset($rule['raw']) && is_string($rule['raw']) ? $rule['raw'] : '';
        }

        return $this->stylesheetStyleRuleToCss(
            $this->stylesheetVisitorRuleSelectors($rule),
            $this->stylesheetVisitorRuleDeclarations($rule)
        );
    }

    /**
     * @param list<string> $selectors
     * @param list<array{property:string, value:string, important:bool}> $entries
     */
    private function stylesheetStyleRuleToCss(array $selectors, array $entries): string
    {
        if ($selectors === [] || $entries === []) {
            return '';
        }

        $body = '';
        foreach ($entries as $entry) {
            if ($entry['property'] === '') {
                continue;
            }
            $body .= $entry['property'] . ':' . $entry['value'] . ($entry['important'] ? ' !important' : '') . ';';
        }

        return $body === '' ? '' : implode(',', array_map('trim', $selectors)) . '{' . $body . '}';
    }

    /**
     * @param array<string, mixed> $rule
     * @return list<string>
     */
    private function stylesheetVisitorRuleSelectors(array $rule): array
    {
        $selectors = $rule['selectors'] ?? null;
        if (is_array($selectors) && $selectors !== [] && is_string($selectors[0] ?? null)) {
            return array_values(array_map('trim', $selectors));
        }

        $value = $rule['value'] ?? null;
        $valueSelectors = is_array($value) ? ($value['selectors'] ?? null) : null;
        if (is_array($valueSelectors)) {
            $serialized = [];
            foreach ($valueSelectors as $selector) {
                $serialized[] = is_array($selector)
                    ? $this->serializeSelectorComponents($selector)
                    : (string) $selector;
            }

            return array_values(array_filter($serialized, static fn (string $selector): bool => trim($selector) !== ''));
        }

        $selector = (string) ($rule['selector'] ?? '');

        return $selector === '' ? [] : array_values(array_filter(
            array_map('trim', explode(',', $selector)),
            static fn (string $part): bool => $part !== ''
        ));
    }

    /**
     * @param list<mixed> $components
     */
    private function serializeSelectorComponents(array $components): string
    {
        $selector = '';
        foreach ($components as $component) {
            if (is_string($component)) {
                $selector .= $component;
                continue;
            }
            if (!is_array($component)) {
                continue;
            }

            $type = $component['type'] ?? null;
            if ($type === 'class') {
                $selector .= '.' . $this->escapeClassSelector((string) ($component['name'] ?? ''));
            } elseif ($type === 'type') {
                $selector .= (string) ($component['name'] ?? '');
            } elseif ($type === 'pseudo-class') {
                $selector .= $this->serializePseudoClassComponent($component);
            } elseif ($type === 'combinator') {
                $value = (string) ($component['value'] ?? '');
                $selector = rtrim($selector) . ($value === 'descendant' ? ' ' : $value);
            } elseif ($type === 'attribute') {
                $selector .= $this->serializeAttributeSelectorComponent($component);
            } else {
                $selector .= (string) ($component['value'] ?? '');
            }
        }

        return trim($selector);
    }

    /**
     * @param array<string, mixed> $component
     */
    private function serializePseudoClassComponent(array $component): string
    {
        $kind = (string) ($component['kind'] ?? '');
        if ($kind === '') {
            return '';
        }

        if (isset($component['formula'])) {
            $argument = (string) $component['formula'];
            $of = $component['of'] ?? null;
            if (is_array($of) && $of !== []) {
                $argument .= ' of ' . implode(',', array_map(
                    fn (mixed $selector): string => is_array($selector) ? $this->serializeSelectorComponents($selector) : (string) $selector,
                    $of
                ));
            }

            return ':' . $kind . '(' . $argument . ')';
        }

        if ($kind === 'dir' && isset($component['direction'])) {
            return ':dir(' . (string) $component['direction'] . ')';
        }

        $selectors = $component['selectors'] ?? null;
        if (is_array($selectors)) {
            return ':' . $kind . '(' . implode(',', array_map(
                fn (mixed $selector): string => is_array($selector) ? $this->serializeSelectorComponents($selector) : (string) $selector,
                $selectors
            )) . ')';
        }

        if (isset($component['arguments']) && is_string($component['arguments'])) {
            return ':' . $kind . '(' . $component['arguments'] . ')';
        }

        return ':' . $kind;
    }

    private function escapeClassSelector(string $name): string
    {
        return preg_replace('/([^-_a-zA-Z0-9])/', '\\\\$1', $name) ?? $name;
    }

    /**
     * @param array<string, mixed> $rule
     * @return list<array{property:string, value:string, important:bool}>
     */
    private function stylesheetVisitorRuleDeclarations(array $rule): array
    {
        if (isset($rule['declarations'])) {
            return $this->stylesheetDeclarationEntries($rule['declarations']);
        }

        $value = $rule['value'] ?? null;
        if (is_array($value) && isset($value['declarations'])) {
            return $this->stylesheetDeclarationEntries($value['declarations']);
        }

        return [];
    }

    /**
     * @return list<array{property:string, value:string, important:bool}>
     */
    private function stylesheetDeclarationEntries(mixed $declarations): array
    {
        if (is_string($declarations)) {
            return $this->declarationBlock->parseEntries($declarations);
        }
        if (!is_array($declarations)) {
            return [];
        }

        if (isset($declarations['declarations']) || isset($declarations['importantDeclarations'])) {
            $entries = [];
            foreach (($declarations['declarations'] ?? []) as $entry) {
                if (is_array($entry)) {
                    $entries[] = $this->stylesheetDeclarationEntry($entry, false);
                }
            }
            foreach (($declarations['importantDeclarations'] ?? []) as $entry) {
                if (is_array($entry)) {
                    $entries[] = $this->stylesheetDeclarationEntry($entry, true);
                }
            }

            return $entries;
        }

        $entries = [];
        foreach ($declarations as $entry) {
            if (is_array($entry)) {
                $entries[] = $this->stylesheetDeclarationEntry($entry, (bool) ($entry['important'] ?? false));
            }
        }

        return $entries;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array{property:string, value:string, important:bool}
     */
    private function stylesheetDeclarationEntry(array $entry, bool $important): array
    {
        $property = (string) ($entry['property'] ?? '');
        $value = $entry['value'] ?? '';
        if ($property === 'unparsed' && is_array($value)) {
            $propertyId = $value['propertyId'] ?? null;
            if (is_array($propertyId) && is_string($propertyId['property'] ?? null)) {
                $property = $propertyId['property'];
            }
            $value = $value['value'] ?? '';
        } elseif ($property === 'custom') {
            $property = (string) ($entry['name'] ?? '');
        }

        return [
            'property' => strtolower($property),
            'value' => $this->serializeStylesheetDeclarationValue($value),
            'important' => (bool) ($entry['important'] ?? $important),
        ];
    }

    private function serializeStylesheetDeclarationValue(mixed $value): string
    {
        if (is_array($value) && array_is_list($value)) {
            return implode(' ', array_map(fn (mixed $token): string => $this->serializeStylesheetDeclarationValue($token), $value));
        }

        if (is_array($value) && ($value['type'] ?? null) === 'length-percentage') {
            $inner = $value['value'] ?? null;
            if (is_array($inner) && ($inner['type'] ?? null) === 'dimension' && is_array($inner['value'] ?? null)) {
                $unit = $inner['value']['unit'] ?? null;
                $number = $inner['value']['value'] ?? null;
                if (is_string($unit) && (is_int($number) || is_float($number))) {
                    return $this->formatNumber($number) . strtolower($unit);
                }
            }
        }

        if (is_array($value) && ($value['type'] ?? null) === 'color' && isset($value['value'])) {
            return $this->serializeStylesheetDeclarationValue($value['value']);
        }

        if (is_array($value) && ($value['type'] ?? null) === 'rgb') {
            return $this->serializeRgbValue($value);
        }

        if (is_array($value)) {
            return $this->serializeVisitorValue($this->normalizeVisitorValue($value));
        }

        return (string) $value;
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
                if ($name === 'media') {
                    $output .= $this->processMediaRule($atPrelude, $body, null);
                } elseif ($name === 'supports') {
                    $output .= $this->processSupportsRule($atPrelude, $body, null);
                } elseif ($this->isCustomAtRule($name)) {
                    $output .= $this->processCustomAtRule($prelude, $body, null);
                } else {
                    $rule = $this->buildUnknownRule($name, $atPrelude, $body, null);
                    $genericReplacement = $this->callAnyRuleVisitor(['type' => 'unknown', 'value' => $rule]);
                    if ($genericReplacement !== null) {
                        $output .= $this->emitReplacement($genericReplacement, null);
                        $cursor = $close + 1;
                        continue;
                    }

                    $replacement = $this->callUnknownRuleVisitor($rule);
                    $output .= $replacement === null
                        ? $this->emitUnknownRule($rule, null)
                        : $this->emitReplacement($replacement, null);
                }
            } else {
                $selectors = $this->splitTopLevel($prelude, ',');
                if ($this->styleRuleVisitor !== null && $this->styleBodyHasAtRuleStatement($body)) {
                    $rule = $this->buildStyleRuleVisitorRule($selectors, $body, null);
                    $replacement = ($this->styleRuleVisitor)($rule, $this);
                    $output .= $replacement === null
                        ? $this->processStyleBody($body, $selectors)
                        : $this->emitStyleRuleReplacement($replacement, $rule);
                } else {
                    $output .= $this->processStyleBody($body, $selectors);
                }
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
            $genericReplacement = $this->callAnyRuleVisitor(['type' => 'unknown', 'value' => $rule]);
            if ($genericReplacement !== null) {
                return $this->emitReplacement($genericReplacement, $parentSelectors);
            }

            $replacement = $this->callUnknownRuleVisitor($rule);

            return $replacement === null
                ? $statement . ';'
                : $this->emitReplacement($replacement, $parentSelectors);
        }

        $rule = $this->buildCustomRule($name, $prelude, null, $parentSelectors);
        $genericReplacement = $this->callAnyRuleVisitor(['type' => 'custom', 'value' => $rule]);
        if ($genericReplacement !== null) {
            return $this->emitReplacement($genericReplacement, $parentSelectors);
        }

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
                if ($name === 'media') {
                    $output .= $this->processMediaRule($atPrelude, $nestedBody, $selectors);
                } elseif ($name === 'supports') {
                    $output .= $this->processSupportsRule($atPrelude, $nestedBody, $selectors);
                } elseif ($this->isCustomAtRule($name)) {
                    $output .= $this->processCustomAtRule($nestedPrelude, $nestedBody, $selectors);
                } elseif (str_starts_with($nestedPrelude, '@nest ')) {
                    $nestedSelectors = $this->resolveNestedSelectors($selectors, substr($nestedPrelude, 6));
                    $output .= $this->processStyleBody($nestedBody, $nestedSelectors);
                } else {
                    $rule = $this->buildUnknownRule($name, $atPrelude, $nestedBody, $selectors);
                    $genericReplacement = $this->callAnyRuleVisitor(['type' => 'unknown', 'value' => $rule]);
                    if ($genericReplacement !== null) {
                        $output .= $this->emitReplacement($genericReplacement, $selectors);
                        $cursor = $close + 1;
                        continue;
                    }

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

    private function styleBodyHasAtRuleStatement(string $body): bool
    {
        $cursor = 0;
        $length = strlen($body);

        while ($cursor < $length) {
            $nextBlock = $this->findNextTopLevel($body, '{', $cursor);
            $nextStatement = $this->findNextTopLevel($body, ';', $cursor);

            if ($nextStatement !== null && ($nextBlock === null || $nextStatement < $nextBlock)) {
                $statement = trim(substr($body, $cursor, $nextStatement - $cursor));
                if ($statement !== '' && str_starts_with($statement, '@')) {
                    return true;
                }

                $cursor = $nextStatement + 1;
                continue;
            }

            if ($nextBlock === null) {
                return false;
            }

            $close = $this->findMatchingBrace($body, $nextBlock);
            $cursor = $close + 1;
        }

        return false;
    }

    /**
     * @param list<string> $selectors
     * @param list<string>|null $parentSelectors
     * @return array<string, mixed>
     */
    private function buildStyleRuleVisitorRule(array $selectors, string $body, ?array $parentSelectors): array
    {
        $declarations = '';
        $rules = [];
        $cursor = 0;
        $length = strlen($body);

        while ($cursor < $length) {
            $nextBlock = $this->findNextTopLevel($body, '{', $cursor);
            $nextStatement = $this->findNextTopLevel($body, ';', $cursor);

            if ($nextStatement !== null && ($nextBlock === null || $nextStatement < $nextBlock)) {
                $statement = trim(substr($body, $cursor, $nextStatement - $cursor));
                if ($statement !== '') {
                    if (str_starts_with($statement, '@')) {
                        [$name, $prelude] = $this->parseAtPrelude($statement);
                        $rules[] = $this->buildNestedUnknownRuleForStyleVisitor($name, $prelude, null, $parentSelectors);
                    } else {
                        $declarations .= $statement . ';';
                    }
                }
                $cursor = $nextStatement + 1;
                continue;
            }

            if ($nextBlock === null) {
                $declarations .= substr($body, $cursor);
                break;
            }

            $prefix = substr($body, $cursor, $nextBlock - $cursor);
            [$declarationPart, $nestedPrelude] = $this->splitDeclarationsAndNestedPrelude($prefix);
            $declarations .= $declarationPart;
            $nestedPrelude = trim($nestedPrelude);
            $close = $this->findMatchingBrace($body, $nextBlock);
            $nestedBody = substr($body, $nextBlock + 1, $close - $nextBlock - 1);

            if (str_starts_with($nestedPrelude, '@')) {
                [$name, $prelude] = $this->parseAtPrelude($nestedPrelude);
                $rules[] = $this->buildNestedUnknownRuleForStyleVisitor($name, $prelude, $nestedBody, $parentSelectors);
            }

            $cursor = $close + 1;
        }

        $entries = $this->declarationBlock->parseEntries($declarations);
        $selectors = array_values(array_filter(
            array_map(static fn (string $selector): string => trim($selector), $selectors),
            static fn (string $selector): bool => $selector !== ''
        ));

        return [
            'type' => 'style',
            'kind' => 'style-rule',
            'selector' => implode(',', $selectors),
            'selectors' => $selectors,
            'declarations' => $entries,
            'value' => [
                'selectors' => array_map(fn (string $selector): array => $this->selectorComponentsFromString($selector), $selectors),
                'declarations' => $this->visitorDeclarationBlockFromEntries($entries),
                'rules' => $rules,
            ],
        ];
    }

    /**
     * @param list<string>|null $parentSelectors
     * @return array{type:string,value:array<string, mixed>}
     */
    private function buildNestedUnknownRuleForStyleVisitor(string $name, string $prelude, ?string $body, ?array $parentSelectors): array
    {
        $rule = $this->buildUnknownRule($name, $prelude, $body, $parentSelectors);
        $rule['preludeText'] = $rule['prelude'];
        $rule['prelude'] = $rule['preludeTokens'];

        return ['type' => 'unknown', 'value' => $rule];
    }

    /**
     * @param list<string>|null $parentSelectors
     */
    private function processMediaRule(string $query, string $body, ?array $parentSelectors): string
    {
        if ($this->ruleVisitor !== null) {
            $replacement = $this->callAnyRuleVisitor($this->buildMediaRule($query, $body, $parentSelectors));
            if ($replacement !== null) {
                return $this->emitReplacement($replacement, $parentSelectors);
            }
        }

        if ($this->mediaRuleVisitor !== null) {
            $rule = $this->buildMediaRule($query, $body, $parentSelectors);
            $replacement = ($this->mediaRuleVisitor)($rule, $this);
            if ($replacement !== null) {
                return $this->emitReplacement($replacement, $parentSelectors);
            }
        }

        $queryCss = $this->mediaQueryVisitor !== null || $this->mediaQueryExitVisitor !== null
            ? $this->returnedMediaQueryToCss($this->applyMediaQueryListVisitors($this->parseMediaQueryForVisitor($this->rewriteAtRulePreludeValue($query))))
            : $this->rewriteAtRulePreludeValue($query);
        $bodyCss = $parentSelectors === null
            ? $this->processRuleList($body)
            : $this->processStyleBody($body, $parentSelectors);

        return '@media ' . $queryCss . '{' . $bodyCss . '}';
    }

    /**
     * @param list<string>|null $parentSelectors
     */
    private function processSupportsRule(string $condition, string $body, ?array $parentSelectors): string
    {
        $conditionCss = $this->supportsConditionVisitor !== null || $this->supportsConditionExitVisitor !== null
            ? $this->returnedSupportsConditionToCss($this->applySupportsConditionVisitors($this->parseSupportsConditionForVisitor($condition)))
            : $this->rewriteAtRulePreludeValue($condition);
        $bodyCss = $parentSelectors === null
            ? $this->processRuleList($body)
            : $this->processStyleBody($body, $parentSelectors);

        return '@supports ' . $conditionCss . '{' . $bodyCss . '}';
    }

    /**
     * @param list<string>|null $parentSelectors
     */
    private function processCustomAtRule(string $prelude, string $body, ?array $parentSelectors): string
    {
        [$name, $atPrelude] = $this->parseAtPrelude($prelude);
        $rule = $this->buildCustomRule($name, $atPrelude, $body, $parentSelectors);
        $genericReplacement = $this->callAnyRuleVisitor(['type' => 'custom', 'value' => $rule]);
        if ($genericReplacement !== null) {
            return $this->emitReplacement($genericReplacement, $parentSelectors);
        }

        $replacement = $this->callRuleVisitor($rule);
        if ($replacement === null) {
            return $prelude . '{' . $body . '}';
        }

        return $this->emitReplacement($replacement, $parentSelectors);
    }

    /**
     * @param list<string>|null $parentSelectors
     * @return array{name:string, prelude:string, preludeAst:mixed, bodyType:string|null, body:string, bodyAst:mixed, bodyRules:list<array<string, mixed>>, declarations:list<array{property:string, value:string, important:bool}>, context:string, parentSelectors:list<string>}
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
        $preludeAst = $this->customPreludeAst($preludeValue, is_string($preludeGrammar) ? $preludeGrammar : null);
        $declarations = [];
        $bodyRules = [];
        if ($body !== null && $bodyType === 'declaration-list') {
            $declarations = $this->declarationBlock->parseEntries($body);
        } elseif ($body !== null && in_array($bodyType, ['rule-list', 'style-block'], true)) {
            $bodyRules = $this->parseReturnedRuleList($body, $bodyType === 'style-block' ? $parentSelectors : null);
        }

        return [
            'name' => $name,
            'prelude' => $preludeValue,
            'preludeAst' => $preludeAst,
            'bodyType' => $bodyType,
            'body' => $body ?? '',
            'bodyAst' => $this->customBodyAst($bodyType, $declarations, $bodyRules),
            'bodyRules' => $bodyRules,
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

    /**
     * @param list<string>|null $parentSelectors
     * @return array{type:string,value:array{query:array<string, mixed>,rules:list<array<string, mixed>>},context:string,parentSelectors:list<string>}
     */
    private function buildMediaRule(string $query, string $body, ?array $parentSelectors): array
    {
        return [
            'type' => 'media',
            'value' => [
                'query' => $this->parseMediaQueryForVisitor($query),
                'rules' => $this->parseReturnedRuleList($body, $parentSelectors),
            ],
            'context' => $parentSelectors === null ? 'rule-list' : 'style-block',
            'parentSelectors' => $parentSelectors ?? [],
        ];
    }

    /**
     * @return array{mediaQueries:list<array<string, mixed>>}
     */
    private function parseMediaQueryForVisitor(string $queryList): array
    {
        try {
            $queries = $this->splitTopLevel((new MediaQueryParser())->minifyList($queryList), ',');
        } catch (\InvalidArgumentException) {
            $queries = $this->splitTopLevel($queryList, ',');
        }
        $mediaQueries = [];
        foreach ($queries as $query) {
            $query = trim($query);
            if ($query !== '') {
                $mediaQueries[] = $this->parseSingleMediaQueryForVisitor($query);
            }
        }

        return ['mediaQueries' => $mediaQueries];
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function applyMediaQueryListVisitors(array $query): array
    {
        $mediaQueries = $query['mediaQueries'] ?? null;
        if (!is_array($mediaQueries)) {
            return $query;
        }
        if ($this->mediaQueryVisitor === null && $this->mediaQueryExitVisitor === null) {
            $query['mediaQueries'] = array_values(array_filter($mediaQueries, 'is_array'));

            return $query;
        }

        $rewritten = [];
        foreach ($mediaQueries as $mediaQuery) {
            if (!is_array($mediaQuery)) {
                continue;
            }
            foreach ($this->applyMediaQueryVisitors($mediaQuery) as $replacement) {
                $rewritten[] = $replacement;
            }
        }

        $query['mediaQueries'] = $rewritten;

        return $query;
    }

    /**
     * @param array<string, mixed> $query
     * @return list<array<string, mixed>>
     */
    private function applyMediaQueryVisitors(array $query): array
    {
        $queries = [$query];
        if ($this->mediaQueryVisitor !== null) {
            $queries = $this->applyMediaQueryVisitorToList($queries, $this->mediaQueryVisitor, 'MediaQuery');
        }
        if ($this->mediaQueryExitVisitor !== null) {
            $queries = $this->applyMediaQueryVisitorToList($queries, $this->mediaQueryExitVisitor, 'MediaQueryExit');
        }

        return $queries;
    }

    /**
     * @param list<array<string, mixed>> $queries
     * @return list<array<string, mixed>>
     */
    private function applyMediaQueryVisitorToList(array $queries, callable $visitor, string $visitorName): array
    {
        $rewritten = [];
        foreach ($queries as $query) {
            $replacement = $visitor($query, $this);
            if ($replacement === null) {
                $rewritten[] = $query;
                continue;
            }

            foreach (self::normalizeMediaQueryVisitorReplacement($replacement, $visitorName) as $nextQuery) {
                $rewritten[] = $nextQuery;
            }
        }

        return $rewritten;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseSingleMediaQueryForVisitor(string $query): array
    {
        $mediaType = 'all';
        $conditionCss = $query;

        if (preg_match('/^(not|only)\s+(screen|print|all)\s+and\s+(.+)$/i', $query, $matches) === 1) {
            return [
                'mediaType' => strtolower($matches[1]) . ' ' . strtolower($matches[2]),
                'condition' => $this->parseMediaConditionForVisitor($matches[3]) ?? ['raw' => trim($matches[3])],
            ];
        }
        if (preg_match('/^(screen|print|all)\s+and\s+(.+)$/i', $query, $matches) === 1) {
            $mediaType = strtolower($matches[1]);
            $conditionCss = $matches[2];
        } elseif (preg_match('/^(not|only)\s+(screen|print|all)$/i', $query, $matches) === 1) {
            return ['mediaType' => strtolower($matches[1]) . ' ' . strtolower($matches[2])];
        } elseif (preg_match('/^(screen|print|all)$/i', $query, $matches) === 1) {
            return ['mediaType' => strtolower($matches[1])];
        }

        $condition = $this->parseMediaConditionForVisitor($conditionCss);
        if ($condition === null) {
            return ['mediaType' => $mediaType, 'condition' => ['raw' => $conditionCss]];
        }

        return ['mediaType' => $mediaType, 'condition' => $condition];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseMediaConditionForVisitor(string $condition): ?array
    {
        $condition = trim($condition);
        if (strlen($condition) >= 2 && $condition[0] === '(' && $condition[strlen($condition) - 1] === ')') {
            return $this->parseMediaFeatureForVisitor(substr($condition, 1, -1));
        }

        return null;
    }

    /**
     * @return array{type:string,value:array<string, mixed>}
     */
    private function parseMediaFeatureForVisitor(string $feature): array
    {
        $feature = trim($feature);
        if (preg_match('/^([_a-zA-Z-][_a-zA-Z0-9-]*)\s*:\s*(.+)$/', $feature, $matches) === 1) {
            return [
                'type' => 'feature',
                'value' => [
                    'type' => 'plain',
                    'name' => strtolower($matches[1]),
                    'value' => $this->parseMediaFeatureValueForVisitor($matches[2]),
                ],
            ];
        }
        if (preg_match('/^([_a-zA-Z-][_a-zA-Z0-9-]*)\s*(<=|>=|<|>|=)\s*(.+)$/', $feature, $matches) === 1) {
            return [
                'type' => 'feature',
                'value' => [
                    'type' => 'range',
                    'name' => strtolower($matches[1]),
                    'operator' => $this->mediaComparisonName($matches[2]),
                    'value' => $this->parseMediaFeatureValueForVisitor($matches[3]),
                ],
            ];
        }

        return [
            'type' => 'feature',
            'value' => [
                'type' => 'boolean',
                'name' => strtolower($feature),
            ],
        ];
    }

    private function mediaComparisonName(string $operator): string
    {
        return match ($operator) {
            '<' => 'less-than',
            '<=' => 'less-than-equal',
            '>' => 'greater-than',
            '>=' => 'greater-than-equal',
            default => 'equal',
        };
    }

    private function parseMediaFeatureValueForVisitor(string $value): mixed
    {
        $value = trim($value);
        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))([a-zA-Z%]+)$/', $value, $matches) === 1) {
            return [
                'type' => 'length',
                'unit' => strtolower($matches[2]),
                'value' => (float) $matches[1],
            ];
        }
        if (
            strlen($value) >= 2
            && (($value[0] === '"' && $value[strlen($value) - 1] === '"') || ($value[0] === "'" && $value[strlen($value) - 1] === "'"))
        ) {
            return [
                'type' => 'token',
                'raw' => $value,
                'value' => [
                    'type' => 'string',
                    'value' => stripcslashes(substr($value, 1, -1)),
                ],
            ];
        }
        if (preg_match('/^-?[_a-zA-Z][-_a-zA-Z0-9]*$/', $value) === 1) {
            return [
                'type' => 'ident',
                'value' => strtolower($value),
            ];
        }

        return ['type' => 'raw', 'value' => $value];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseSupportsConditionForVisitor(string $condition): array
    {
        $condition = trim($condition);
        if ($condition === '') {
            return ['type' => 'unknown', 'value' => ''];
        }

        foreach (['or', 'and'] as $operator) {
            $parts = $this->splitTopLevelKeyword($condition, $operator);
            if (count($parts) > 1) {
                return [
                    'type' => $operator,
                    'value' => array_map(fn (string $part): array => $this->parseSupportsConditionForVisitor($part), $parts),
                ];
            }
        }

        if (preg_match('/^not\s+(.+)$/i', $condition, $matches) === 1) {
            return [
                'type' => 'not',
                'value' => $this->parseSupportsConditionForVisitor($matches[1]),
            ];
        }

        if (preg_match('/^selector\s*\(/i', $condition, $matches) === 1) {
            $open = strpos($condition, '(');
            if ($open !== false && $this->findMatchingParen($condition, $open) === strlen($condition) - 1) {
                return [
                    'type' => 'selector',
                    'value' => trim(substr($condition, $open + 1, -1)),
                ];
            }
        }

        $unwrapped = $this->unwrapSingleParenthesizedCondition($condition);
        if ($unwrapped !== $condition) {
            return $this->parseSupportsConditionForVisitor($unwrapped);
        }

        $colon = $this->findTopLevelCharacter($condition, ':');
        if ($colon !== null) {
            return [
                'type' => 'declaration',
                'propertyId' => ['property' => strtolower(trim(substr($condition, 0, $colon)))],
                'value' => trim(substr($condition, $colon + 1)),
            ];
        }

        return ['type' => 'unknown', 'value' => $condition];
    }

    /**
     * @param array<string, mixed> $condition
     * @return array<string, mixed>
     */
    private function applySupportsConditionVisitors(array $condition): array
    {
        if ($this->supportsConditionVisitor !== null) {
            $replacement = ($this->supportsConditionVisitor)($condition, $this);
            if ($replacement !== null) {
                if (!is_array($replacement)) {
                    throw new \InvalidArgumentException('SupportsCondition visitor must return a condition array or null');
                }
                $condition = $replacement;
            }
        }

        $type = strtolower((string) ($condition['type'] ?? ''));
        if ($type === 'not' && isset($condition['value']) && is_array($condition['value'])) {
            $condition['value'] = $this->applySupportsConditionVisitors($condition['value']);
        } elseif (($type === 'and' || $type === 'or') && isset($condition['value']) && is_array($condition['value'])) {
            $children = [];
            foreach ($condition['value'] as $child) {
                $children[] = is_array($child) ? $this->applySupportsConditionVisitors($child) : $child;
            }
            $condition['value'] = $children;
        }

        if ($this->supportsConditionExitVisitor !== null) {
            $replacement = ($this->supportsConditionExitVisitor)($condition, $this);
            if ($replacement !== null) {
                if (!is_array($replacement)) {
                    throw new \InvalidArgumentException('SupportsConditionExit visitor must return a condition array or null');
                }
                $condition = $replacement;
            }
        }

        return $condition;
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

    private function customPreludeAst(string $prelude, ?string $grammar): mixed
    {
        if ($grammar === null) {
            return null;
        }

        return match ($grammar) {
            '<custom-ident>' => [
                'type' => 'custom-ident',
                'value' => $prelude,
            ],
            '<dashed-ident>' => [
                'type' => 'dashed-ident',
                'value' => $prelude,
            ],
            '<length>' => $this->customLengthPreludeAst($prelude),
            '<number>' => is_numeric($prelude)
                ? ['type' => 'number', 'value' => (float) $prelude]
                : ['type' => 'raw', 'value' => $prelude],
            '<percentage>' => preg_match('/^([+-]?(?:\d+|\d*\.\d+))%$/', $prelude, $matches) === 1
                ? ['type' => 'percentage', 'value' => (float) $matches[1] / 100]
                : ['type' => 'raw', 'value' => $prelude],
            '<string>' => $this->customStringPreludeAst($prelude),
            default => ['type' => 'raw', 'value' => $prelude],
        };
    }

    /**
     * @return array{type:string,value:mixed}
     */
    private function customLengthPreludeAst(string $prelude): array
    {
        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))([a-zA-Z%]+)$/', $prelude, $matches) === 1) {
            return [
                'type' => 'length',
                'value' => [
                    'type' => 'value',
                    'value' => [
                        'unit' => strtolower($matches[2]),
                        'value' => (float) $matches[1],
                    ],
                ],
            ];
        }

        if ($prelude === '0') {
            return [
                'type' => 'length',
                'value' => [
                    'type' => 'value',
                    'value' => [
                        'unit' => 'px',
                        'value' => 0.0,
                    ],
                ],
            ];
        }

        return ['type' => 'raw', 'value' => $prelude];
    }

    /**
     * @return array{type:string,value:string}
     */
    private function customStringPreludeAst(string $prelude): array
    {
        if (
            strlen($prelude) >= 2
            && (($prelude[0] === '"' && $prelude[strlen($prelude) - 1] === '"') || ($prelude[0] === "'" && $prelude[strlen($prelude) - 1] === "'"))
        ) {
            return [
                'type' => 'string',
                'value' => stripcslashes(substr($prelude, 1, -1)),
            ];
        }

        return ['type' => 'string', 'value' => $prelude];
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $declarations
     * @param list<array<string, mixed>> $bodyRules
     */
    private function customBodyAst(?string $bodyType, array $declarations, array $bodyRules): mixed
    {
        if ($bodyType === null) {
            return null;
        }

        if ($bodyType === 'declaration-list') {
            return [
                'type' => 'declaration-list',
                'value' => $this->customDeclarationBlockAst($declarations),
            ];
        }

        return [
            'type' => 'rule-list',
            'value' => $bodyRules,
        ];
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array{declarations:list<array<string, mixed>>, importantDeclarations:list<array<string, mixed>>}
     */
    private function customDeclarationBlockAst(array $entries): array
    {
        $normal = [];
        $important = [];
        foreach ($entries as $entry) {
            $declaration = $this->customDeclarationAst($entry);
            if (!empty($entry['important'])) {
                $important[] = $declaration;
            } else {
                $normal[] = $declaration;
            }
        }

        return [
            'declarations' => $normal,
            'importantDeclarations' => $important,
        ];
    }

    /**
     * @param array{property:string, value:string, important:bool} $entry
     * @return array<string, mixed>
     */
    private function customDeclarationAst(array $entry): array
    {
        $declaration = $this->entryToVisitorDeclaration($entry);
        if (($declaration['property'] ?? null) === 'custom') {
            return [
                'property' => 'custom',
                'value' => [
                    'name' => (string) ($declaration['name'] ?? $entry['property']),
                    'value' => $declaration['value'] ?? [],
                ],
                'important' => $entry['important'],
            ];
        }

        $declaration['important'] = $entry['important'];

        return $declaration;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return array{declarations:list<array<string, mixed>>, importantDeclarations:list<array<string, mixed>>}
     */
    private function visitorDeclarationBlockFromEntries(array $entries): array
    {
        $normal = [];
        $important = [];
        foreach ($entries as $entry) {
            $declaration = $this->entryToRuleValueDeclaration($entry);
            if (!empty($entry['important'])) {
                $important[] = $declaration;
            } else {
                $normal[] = $declaration;
            }
        }

        return [
            'declarations' => $normal,
            'importantDeclarations' => $important,
        ];
    }

    /**
     * @param array{property:string, value:string, important:bool} $entry
     * @return array<string, mixed>
     */
    private function entryToRuleValueDeclaration(array $entry): array
    {
        if (strtolower($entry['property']) === 'transform') {
            return $this->entryToVisitorDeclaration($entry);
        }

        return $entry;
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
     * @param array<string, mixed> $rule
     */
    private function callAnyRuleVisitor(array $rule): mixed
    {
        if ($this->ruleVisitor === null) {
            return null;
        }

        return ($this->ruleVisitor)($rule, $this);
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
        if (($replacement['type'] ?? null) === 'custom' && isset($replacement['value']) && is_array($replacement['value'])) {
            return $this->emitCustomRule($replacement['value'], $parentSelectors);
        }
        if (($replacement['type'] ?? null) === 'ignored') {
            return '';
        }
        if (($replacement['type'] ?? null) === 'style' && isset($replacement['value']) && is_array($replacement['value'])) {
            return $this->emitReturnedStyleRule($replacement['value']);
        }
        if (($replacement['type'] ?? null) === 'media' && isset($replacement['value']) && is_array($replacement['value'])) {
            return $this->emitReturnedMediaRule($replacement['value'], $parentSelectors);
        }
        if (($replacement['type'] ?? null) === 'supports' && isset($replacement['value']) && is_array($replacement['value'])) {
            return $this->emitReturnedSupportsRule($replacement['value'], $parentSelectors);
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
    private function emitCustomRule(array $rule, ?array $parentSelectors): string
    {
        $name = (string) ($rule['name'] ?? '');
        if ($name === '') {
            throw new \InvalidArgumentException('Custom at-rule replacement is missing a name');
        }

        $prelude = trim((string) ($rule['prelude'] ?? ''));
        $head = '@' . $name . ($prelude === '' ? '' : ' ' . $prelude);
        $bodyType = $rule['bodyType'] ?? null;
        if ($bodyType === null) {
            return $head . ';';
        }

        $body = (string) ($rule['body'] ?? '');
        if ($bodyType === 'rule-list') {
            return $head . '{' . $this->processRuleList($body) . '}';
        }
        if ($bodyType === 'style-block' && $parentSelectors !== null) {
            return $head . '{' . $this->processStyleBody($body, $parentSelectors) . '}';
        }

        return $head . '{' . $body . '}';
    }

    /**
     * @param array<string, mixed> $value
     * @param list<string>|null $parentSelectors
     */
    private function emitReturnedMediaRule(array $value, ?array $parentSelectors): string
    {
        $queryAst = is_array($value['query'] ?? null) ? $value['query'] : [];
        $query = $this->returnedMediaQueryToCss($this->applyMediaQueryListVisitors($queryAst));
        $rules = $value['rules'] ?? [];
        if (!is_array($rules)) {
            $rules = [];
        }

        $body = '';
        foreach ($rules as $rule) {
            $body .= $this->emitReplacement($rule, $parentSelectors);
        }

        return '@media ' . $query . '{' . $body . '}';
    }

    /**
     * @param array<string, mixed> $value
     * @param list<string>|null $parentSelectors
     */
    private function emitReturnedSupportsRule(array $value, ?array $parentSelectors): string
    {
        $conditionAst = is_array($value['condition'] ?? null) ? $value['condition'] : [];
        $condition = $this->returnedSupportsConditionToCss($this->applySupportsConditionVisitors($conditionAst));
        $rules = $value['rules'] ?? [];
        if (!is_array($rules)) {
            $rules = [];
        }

        $body = '';
        foreach ($rules as $rule) {
            $body .= $this->emitReplacement($rule, $parentSelectors);
        }

        return '@supports ' . $condition . '{' . $body . '}';
    }

    /**
     * @param array<string, mixed> $condition
     */
    private function returnedSupportsConditionToCss(array $condition): string
    {
        $type = strtolower((string) ($condition['type'] ?? ''));
        if ($type === 'declaration') {
            $propertyId = $condition['propertyId'] ?? null;
            $property = is_array($propertyId)
                ? (string) ($propertyId['property'] ?? '')
                : (string) ($condition['property'] ?? '');

            return '(' . $property . ':' . $this->serializeVisitorValue($condition['value'] ?? '') . ')';
        }

        if (($type === 'and' || $type === 'or') && isset($condition['value']) && is_array($condition['value'])) {
            $parts = [];
            foreach ($condition['value'] as $child) {
                if (is_array($child)) {
                    $parts[] = $this->returnedSupportsConditionToCss($child);
                }
            }

            return implode(' ' . $type . ' ', array_filter($parts, static fn (string $part): bool => $part !== ''));
        }

        if ($type === 'not' && isset($condition['value']) && is_array($condition['value'])) {
            return 'not ' . $this->returnedSupportsConditionToCss($condition['value']);
        }

        if ($type === 'selector') {
            return 'selector(' . trim((string) ($condition['value'] ?? '')) . ')';
        }

        if ($type === 'unknown') {
            return trim((string) ($condition['value'] ?? $condition['raw'] ?? ''));
        }

        return trim((string) ($condition['raw'] ?? $condition['value'] ?? ''));
    }

    /**
     * @param array<string, mixed> $query
     */
    private function returnedMediaQueryToCss(array $query): string
    {
        $mediaQueries = $query['mediaQueries'] ?? null;
        if (!is_array($mediaQueries)) {
            return trim((string) ($query['raw'] ?? ''));
        }

        $parts = [];
        foreach ($mediaQueries as $mediaQuery) {
            if (!is_array($mediaQuery)) {
                continue;
            }
            if (isset($mediaQuery['raw']) && is_string($mediaQuery['raw'])) {
                $parts[] = trim($mediaQuery['raw']);
                continue;
            }

            $condition = $mediaQuery['condition'] ?? null;
            $mediaType = strtolower((string) ($mediaQuery['mediaType'] ?? 'all'));
            $conditionCss = is_array($condition) ? $this->returnedMediaConditionToCss($condition) : '';
            if ($mediaType === '' || $mediaType === 'all') {
                $parts[] = $conditionCss === '' ? 'all' : $conditionCss;
                continue;
            }

            $parts[] = $conditionCss === '' ? $mediaType : $mediaType . ' and ' . $conditionCss;
        }

        return implode(',', $parts);
    }

    /**
     * @param array<string, mixed> $condition
     */
    private function returnedMediaConditionToCss(array $condition): string
    {
        if (($condition['type'] ?? null) === 'feature' && isset($condition['value']) && is_array($condition['value'])) {
            $feature = $condition['value'];
            $name = (string) ($feature['name'] ?? '');
            if (($feature['type'] ?? null) === 'boolean') {
                return '(' . $name . ')';
            }
            if (($feature['type'] ?? null) === 'plain') {
                return '(' . $name . ':' . $this->serializeReturnedMediaFeatureValue($feature['value'] ?? '') . ')';
            }
            if (($feature['type'] ?? null) === 'range') {
                return '(' . $name . $this->mediaComparisonOperator((string) ($feature['operator'] ?? 'equal')) . $this->serializeReturnedMediaFeatureValue($feature['value'] ?? '') . ')';
            }
        }

        if (($condition['type'] ?? null) === 'operation' && isset($condition['conditions']) && is_array($condition['conditions'])) {
            $operator = strtolower((string) ($condition['operator'] ?? 'and'));
            $parts = [];
            foreach ($condition['conditions'] as $child) {
                if (is_array($child)) {
                    $parts[] = $this->returnedMediaConditionToCss($child);
                }
            }

            return implode(' ' . $operator . ' ', array_filter($parts, static fn (string $part): bool => $part !== ''));
        }

        if (($condition['type'] ?? null) === 'not' && isset($condition['value']) && is_array($condition['value'])) {
            return 'not ' . $this->returnedMediaConditionToCss($condition['value']);
        }

        return trim((string) ($condition['raw'] ?? ''));
    }

    private function mediaComparisonOperator(string $operator): string
    {
        return match ($operator) {
            'less-than' => '<',
            'less-than-equal' => '<=',
            'greater-than' => '>',
            'greater-than-equal' => '>=',
            default => '=',
        };
    }

    private function serializeReturnedMediaFeatureValue(mixed $value): string
    {
        if (is_array($value)) {
            if (in_array(($value['type'] ?? null), ['length', 'dimension', 'rgb', 'token', 'raw', 'var', 'env', 'function', 'ident'], true)) {
                return $this->serializeVisitorValue($value);
            }
            if (($value['type'] ?? null) === 'length' && isset($value['value']) && is_array($value['value'])) {
                return $this->serializeVisitorValue([
                    'type' => 'length',
                    'value' => $value['value'],
                ]);
            }
            if (isset($value['value'])) {
                return $this->serializeVisitorValue($value['value']);
            }
        }

        return $this->serializeVisitorValue($value);
    }

    /**
     * @param array<string, mixed> $value
     */
    private function emitReturnedStyleRule(array $value): string
    {
        $selectors = $this->serializeReturnedSelectors(is_array($value['selectors'] ?? null) ? $value['selectors'] : []);
        $declarations = $value['declarations'] ?? [];
        $body = '';
        if (is_array($declarations)) {
            foreach ($this->returnedDeclarationBlockEntries($declarations) as $entry) {
                foreach ($this->returnedDeclarationToCss($entry) as $declarationCss) {
                    $body .= $declarationCss;
                }
            }
        }

        if ($selectors === [] || $body === '') {
            return '';
        }

        return implode(',', $selectors) . '{' . $body . '}';
    }

    /**
     * @param array<string, mixed> $block
     * @return list<array<string, mixed>>
     */
    private function returnedDeclarationBlockEntries(array $block): array
    {
        $entries = [];
        foreach (['declarations', 'importantDeclarations'] as $key) {
            $declarations = $block[$key] ?? [];
            if (!is_array($declarations)) {
                continue;
            }
            foreach ($declarations as $declaration) {
                if (!is_array($declaration)) {
                    continue;
                }
                if ($key === 'importantDeclarations') {
                    $declaration['important'] = true;
                }
                $entries[] = $declaration;
            }
        }

        return $entries;
    }

    /**
     * @param array<string, mixed> $declaration
     * @return list<string>
     */
    private function returnedDeclarationToCss(array $declaration): array
    {
        $property = (string) ($declaration['property'] ?? '');
        if ($property === 'unparsed' && isset($declaration['value']) && is_array($declaration['value'])) {
            $propertyId = $declaration['value']['propertyId'] ?? [];
            if (is_array($propertyId) && isset($propertyId['property'])) {
                $property = (string) $propertyId['property'];
            }
        }
        if ($property === '') {
            return [];
        }

        $value = $this->returnedDeclarationValueToCss($declaration);
        $important = !empty($declaration['important']) ? ' !important' : '';
        $properties = [$property];
        $vendorPrefixes = $declaration['vendorPrefix'] ?? [];
        if (is_array($vendorPrefixes) && $vendorPrefixes !== [] && $property[0] !== '-') {
            $properties = [];
            foreach ($vendorPrefixes as $prefix) {
                $prefix = strtolower((string) $prefix);
                $properties[] = '-' . trim($prefix, '-') . '-' . $property;
            }
        }

        return array_map(
            static fn (string $prefixedProperty): string => $prefixedProperty . ':' . $value . $important . ';',
            $properties
        );
    }

    /**
     * @param array<string, mixed> $declaration
     */
    private function returnedDeclarationValueToCss(array $declaration): string
    {
        if (isset($declaration['raw']) && is_string($declaration['raw'])) {
            return $this->rewriteDeclarationValue(
                $this->decodeCssEscapes($declaration['raw']),
                is_string($declaration['property'] ?? null) ? $declaration['property'] : null
            );
        }

        $value = $declaration['value'] ?? '';
        if (is_array($value) && ($declaration['property'] ?? null) === 'unparsed' && isset($value['value'])) {
            $value = $value['value'];
        }

        if (is_array($value) && array_is_list($value)) {
            return implode(' ', array_map(fn (mixed $part): string => $this->serializeVisitorValue($part), $value));
        }

        return $this->serializeVisitorValue($value);
    }

    private function decodeCssEscapes(string $value): string
    {
        return preg_replace_callback(
            '/\\\\([0-9a-fA-F]{1,6})\s?|\\\\(.)/s',
            static function (array $matches): string {
                if (($matches[1] ?? '') !== '') {
                    $codepoint = hexdec($matches[1]);
                    if (function_exists('mb_chr')) {
                        return mb_chr($codepoint, 'UTF-8');
                    }

                    return $codepoint < 256 ? chr($codepoint) : '';
                }

                return (string) ($matches[2] ?? '');
            },
            $value
        ) ?? $value;
    }

    /**
     * @param list<mixed> $selectors
     * @return list<string>
     */
    private function serializeReturnedSelectors(array $selectors): array
    {
        $serialized = [];
        foreach ($selectors as $selector) {
            if (is_string($selector)) {
                $serialized[] = trim($selector);
                continue;
            }
            if (!is_array($selector)) {
                continue;
            }
            $serialized[] = $this->serializeReturnedSelector($selector);
        }

        return array_values(array_filter($serialized, static fn (string $selector): bool => $selector !== ''));
    }

    /**
     * @param list<array<string, mixed>> $selector
     */
    private function serializeReturnedSelector(array $selector): string
    {
        $css = '';
        foreach ($selector as $component) {
            if (!is_array($component)) {
                continue;
            }
            $type = (string) ($component['type'] ?? '');
            if ($type === 'universal') {
                $css .= '*';
            } elseif ($type === 'class') {
                $css .= '.' . $this->escapeSelectorIdentifier((string) ($component['name'] ?? ''));
            } elseif ($type === 'id') {
                $css .= '#' . $this->escapeSelectorIdentifier((string) ($component['name'] ?? ''));
            } elseif ($type === 'type') {
                $css .= (string) ($component['name'] ?? '');
            } elseif ($type === 'pseudo-class') {
                $css .= $this->serializePseudoClassComponent($component);
            } elseif ($type === 'combinator') {
                $value = (string) ($component['value'] ?? 'descendant');
                $css = rtrim($css);
                $css .= $value === 'descendant' ? ' ' : $value;
            } elseif ($type === 'attribute') {
                $css .= $this->serializeAttributeSelectorComponent($component);
            }
        }

        return trim($css);
    }

    /**
     * @param array<string, mixed> $component
     */
    private function serializeAttributeSelectorComponent(array $component): string
    {
        $css = '[' . (string) ($component['name'] ?? '');
        $operation = $component['operation'] ?? null;
        if (is_array($operation) && isset($operation['operator'], $operation['value'])) {
            $operator = $operation['operator'] === 'equal' ? '=' : (string) $operation['operator'];
            $css .= $operator . (string) $operation['value'];
        }

        return $css . ']';
    }

    private function escapeSelectorIdentifier(string $identifier): string
    {
        return preg_replace('/([^_a-zA-Z0-9-])/', '\\\\$1', $identifier) ?? $identifier;
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

        $preludeValue = $rule['prelude'] ?? '';
        $prelude = is_string($preludeValue)
            ? $preludeValue
            : (string) ($rule['preludeText'] ?? '');
        $prelude = trim($this->rewriteAtRulePreludeValue($prelude));
        if ($this->isKeyframesAtRule($name)) {
            $prelude = $this->rewriteKeyframesPrelude($prelude);
        }
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
    private static function mediaRuleVisitorCallback(array $visitor): ?callable
    {
        $ruleConfig = $visitor['Rule'] ?? null;
        if (is_callable($ruleConfig)) {
            return $ruleConfig;
        }

        if (is_array($ruleConfig) && is_callable($ruleConfig['media'] ?? null)) {
            return $ruleConfig['media'];
        }

        $mediaConfig = $visitor['media'] ?? null;
        if (is_callable($mediaConfig)) {
            return $mediaConfig;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $visitor
     */
    private static function styleSheetVisitorCallback(array $visitor, string $name): ?callable
    {
        $callback = $visitor[$name] ?? null;

        return is_callable($callback) ? $callback : null;
    }

    /**
     * @param array<string, mixed> $visitor
     */
    private static function mediaQueryVisitorCallback(array $visitor, string $name): ?callable
    {
        $callback = $visitor[$name] ?? null;

        return is_callable($callback) ? $callback : null;
    }

    /**
     * @param array<string, mixed> $visitor
     */
    private static function supportsConditionVisitorCallback(array $visitor, string $name): ?callable
    {
        $callback = $visitor[$name] ?? null;

        return is_callable($callback) ? $callback : null;
    }

    /**
     * @param array<string, mixed> $visitor
     */
    private static function selectorVisitorCallback(array $visitor): ?callable
    {
        $callback = $visitor['Selector'] ?? null;

        return is_callable($callback) ? $callback : null;
    }

    /**
     * @param array<string, mixed> $visitor
     * @param array<string, mixed> $declaration
     */
    private static function declarationVisitorCallback(array $visitor, array $declaration): ?callable
    {
        return self::declarationCallback($visitor['Declaration'] ?? null, $declaration);
    }

    /**
     * @param array<string, mixed> $visitor
     * @param array<string, mixed> $declaration
     */
    private static function declarationExitVisitorCallback(array $visitor, array $declaration): ?callable
    {
        return self::declarationCallback($visitor['DeclarationExit'] ?? null, $declaration);
    }

    /**
     * @param mixed $config
     * @param array<string, mixed> $declaration
     */
    private static function declarationCallback(mixed $config, array $declaration): ?callable
    {
        if (is_callable($config)) {
            return $config;
        }

        if (!is_array($config)) {
            return null;
        }

        $property = self::declarationCallbackProperty($declaration);
        $callback = self::caseInsensitiveCallback($config, $property);
        if ($callback !== null) {
            return $callback;
        }

        $customConfig = $config['custom'] ?? null;
        if (is_callable($customConfig) && self::isCustomDeclaration($declaration)) {
            return $customConfig;
        }

        if (is_array($customConfig)) {
            $callback = self::caseInsensitiveCallback($customConfig, $property);
            if ($callback !== null) {
                return $callback;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $declaration
     */
    private static function declarationCallbackProperty(array $declaration): string
    {
        if (($declaration['property'] ?? null) === 'unparsed') {
            $value = $declaration['value'] ?? null;
            if (is_array($value)) {
                $propertyId = $value['propertyId'] ?? null;
                if (is_array($propertyId) && is_string($propertyId['property'] ?? null)) {
                    return $propertyId['property'];
                }
            }
        }

        if (is_string($declaration['name'] ?? null)) {
            return $declaration['name'];
        }

        return (string) ($declaration['property'] ?? '');
    }

    /**
     * @param array<string, mixed> $declaration
     */
    private static function isCustomDeclaration(array $declaration): bool
    {
        return ($declaration['property'] ?? null) === 'custom'
            || (is_string($declaration['name'] ?? null) && !self::isKnownDeclarationProperty($declaration['name']));
    }

    private static function isKnownDeclarationProperty(string $property): bool
    {
        return in_array(strtolower($property), [
            '-webkit-overflow-scrolling',
            'background',
            'background-color',
            'border-color',
            'box-shadow',
            'color',
            'display',
            'gap',
            'height',
            'margin',
            'margin-left',
            'margin-right',
            'outline-color',
            'overflow',
            'overflow-x',
            'overflow-y',
            'padding',
            'width',
        ], true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function normalizeDeclarationVisitorList(mixed $replacement): array
    {
        if ($replacement === false || $replacement === []) {
            return [];
        }
        if (!is_array($replacement)) {
            throw new \InvalidArgumentException('Declaration visitor must return a declaration array, list of declarations, or null');
        }
        if (array_is_list($replacement)) {
            $declarations = [];
            foreach ($replacement as $item) {
                foreach (self::normalizeDeclarationVisitorList($item) as $declaration) {
                    $declarations[] = $declaration;
                }
            }

            return $declarations;
        }

        return [$replacement];
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

    /**
     * @param array<string, mixed> $visitor
     */
    private static function colorVisitorCallback(array $visitor): ?callable
    {
        $colorConfig = $visitor['Color'] ?? null;

        return is_callable($colorConfig) ? $colorConfig : null;
    }

    /**
     * @param array<string, mixed> $visitor
     */
    private static function urlVisitorCallback(array $visitor): ?callable
    {
        $urlConfig = $visitor['Url'] ?? null;

        return is_callable($urlConfig) ? $urlConfig : null;
    }

    /**
     * @param array<string, mixed> $visitor
     */
    private static function dashedIdentVisitorCallback(array $visitor): ?callable
    {
        $dashedIdentConfig = $visitor['DashedIdent'] ?? null;

        return is_callable($dashedIdentConfig) ? $dashedIdentConfig : null;
    }

    /**
     * @param array<string, mixed> $visitor
     */
    private static function customIdentVisitorCallback(array $visitor): ?callable
    {
        $customIdentConfig = $visitor['CustomIdent'] ?? null;

        return is_callable($customIdentConfig) ? $customIdentConfig : null;
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
     * @return list<array<string, mixed>>
     */
    private static function normalizeRuleVisitorReplacement(mixed $replacement, string $visitorName): array
    {
        if ($replacement === false || $replacement === []) {
            return [];
        }
        if (!is_array($replacement)) {
            throw new \InvalidArgumentException($visitorName . ' visitor must return a rule array, list of rules, or null');
        }
        if (array_is_list($replacement)) {
            $rules = [];
            foreach ($replacement as $item) {
                foreach (self::normalizeRuleVisitorReplacement($item, $visitorName) as $rule) {
                    $rules[] = $rule;
                }
            }

            return $rules;
        }
        if (($replacement['kind'] ?? null) === 'remove') {
            return [];
        }

        return [$replacement];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function normalizeMediaQueryVisitorReplacement(mixed $replacement, string $visitorName): array
    {
        if ($replacement === false || $replacement === []) {
            return [];
        }
        if (!is_array($replacement)) {
            throw new \InvalidArgumentException($visitorName . ' visitor must return a media query array, list of media queries, or null');
        }
        if (array_is_list($replacement)) {
            $queries = [];
            foreach ($replacement as $item) {
                if (!is_array($item)) {
                    throw new \InvalidArgumentException($visitorName . ' visitor list items must be media query arrays');
                }
                $queries[] = $item;
            }

            return $queries;
        }

        return [$replacement];
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
     * @param list<mixed> $selectors
     * @return list<string>
     */
    private function applySelectorVisitorToSelectorList(array $selectors): array
    {
        $normalized = array_values(array_filter(
            array_map(static fn (mixed $selector): string => trim((string) $selector), $selectors),
            static fn (string $selector): bool => $selector !== ''
        ));
        if ($this->selectorVisitor === null) {
            return $normalized;
        }

        $rewritten = [];
        foreach ($normalized as $selector) {
            $components = $this->selectorComponentsFromString($selector);
            $replacement = ($this->selectorVisitor)($components, $this);
            $nextComponents = $replacement === null
                ? $components
                : $this->normalizeSelectorVisitorReplacement($replacement);
            if ($nextComponents === []) {
                continue;
            }

            $serialized = $this->serializeSelectorComponents($nextComponents);
            if ($serialized !== '') {
                $rewritten[] = $serialized;
            }
        }

        return $rewritten;
    }

    /**
     * @return list<mixed>
     */
    private function normalizeSelectorVisitorReplacement(mixed $replacement): array
    {
        if ($replacement === false || $replacement === []) {
            return [];
        }
        if (is_string($replacement)) {
            return $this->selectorComponentsFromString($replacement);
        }
        if (!is_array($replacement)) {
            throw new \InvalidArgumentException('Selector visitor must return a selector component list, selector string, or null');
        }
        if (isset($replacement['type']) && is_string($replacement['type'])) {
            return [$replacement];
        }
        if (!array_is_list($replacement)) {
            throw new \InvalidArgumentException('Selector visitor must return a selector component list, selector string, or null');
        }

        return $replacement;
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return list<array{property:string, value:string, important:bool}>
     */
    private function processDeclarationEntries(array $entries): array
    {
        if ($this->declarationVisitorConfig !== null) {
            $entries = $this->applyDeclarationVisitorConfig($entries, $this->declarationVisitorConfig);
        }

        if ($this->declarationExitVisitorConfig !== null) {
            $entries = $this->applyDeclarationVisitorConfig($entries, $this->declarationExitVisitorConfig);
        }

        return $this->orderVendorOverflowScrollingEntries($entries);
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @param array<string, mixed>|callable $config
     * @return list<array{property:string, value:string, important:bool}>
     */
    private function applyDeclarationVisitorConfig(array $entries, array|callable $config): array
    {
        $nextEntries = [];
        foreach ($entries as $entry) {
            $declaration = $this->entryToVisitorDeclaration($entry);
            $callback = self::declarationCallback($config, $declaration);
            if ($callback === null) {
                $nextEntries[] = $entry;
                continue;
            }

            $replacement = $callback($declaration, $this);
            if ($replacement === null) {
                $nextEntries[] = $entry;
                continue;
            }

            foreach (self::normalizeDeclarationVisitorList($replacement) as $replacementDeclaration) {
                $nextEntries[] = $this->visitorDeclarationToEntry($replacementDeclaration, $entry);
            }
        }

        return $nextEntries;
    }

    /**
     * @param array{property:string, value:string, important:bool} $entry
     * @return array<string, mixed>
     */
    private function entryToVisitorDeclaration(array $entry): array
    {
        $property = strtolower($entry['property']);
        if ($property === 'transform') {
            return [
                'property' => 'transform',
                'value' => $this->parseTransformFunctionListForVisitor($entry['value']),
                'important' => $entry['important'],
            ];
        }

        $tokens = $this->parseComponentValueList($entry['value']);

        if (self::isKnownDeclarationProperty($property)) {
            if ($this->isUnparsedKnownDeclarationValue($property, $tokens)) {
                return [
                    'property' => 'unparsed',
                    'value' => [
                        'propertyId' => ['property' => $property],
                        'value' => $tokens,
                    ],
                    'important' => $entry['important'],
                ];
            }

            return [
                'property' => $property,
                'value' => count($tokens) === 1 ? $this->knownDeclarationValueFromToken($tokens[0]) : ['type' => 'raw', 'value' => $entry['value']],
                'important' => $entry['important'],
            ];
        }

        return [
            'property' => 'custom',
            'name' => $property,
            'value' => $tokens,
            'important' => $entry['important'],
        ];
    }

    /**
     * @param list<mixed> $tokens
     */
    private function isUnparsedKnownDeclarationValue(string $property, array $tokens): bool
    {
        if (!in_array($property, ['height', 'width'], true) || count($tokens) !== 1) {
            return false;
        }

        $token = $tokens[0];

        return is_array($token)
            && ($token['type'] ?? null) === 'token'
            && is_array($token['value'] ?? null)
            && ($token['value']['type'] ?? null) === 'ident';
    }

    private function knownDeclarationValueFromToken(mixed $token): mixed
    {
        if (!is_array($token)) {
            return $token;
        }

        if (($token['type'] ?? null) === 'color' && isset($token['value'])) {
            return $token['value'];
        }

        return $token;
    }

    /**
     * @param array<string, mixed> $declaration
     * @param array{property:string, value:string, important:bool} $fallback
     * @return array{property:string, value:string, important:bool}
     */
    private function visitorDeclarationToEntry(array $declaration, array $fallback): array
    {
        $property = (string) ($declaration['property'] ?? $fallback['property']);
        $raw = $declaration['raw'] ?? null;
        $value = $declaration['value'] ?? $fallback['value'];
        if ($property === 'unparsed' && is_array($value)) {
            $propertyId = $value['propertyId'] ?? null;
            if (is_array($propertyId) && is_string($propertyId['property'] ?? null)) {
                $property = $propertyId['property'];
            }
            $value = $value['value'] ?? '';
        } elseif ($property === 'custom') {
            $property = (string) ($declaration['name'] ?? $fallback['property']);
        }

        return [
            'property' => strtolower($property),
            'value' => is_string($raw)
                ? $this->decodeCssEscapes($raw)
                : $this->serializeDeclarationVisitorValue($value),
            'important' => (bool) ($declaration['important'] ?? $fallback['important']),
        ];
    }

    /**
     * @param list<array{property:string, value:string, important:bool}> $entries
     * @return list<array{property:string, value:string, important:bool}>
     */
    private function orderVendorOverflowScrollingEntries(array $entries): array
    {
        for ($index = 1, $count = count($entries); $index < $count; $index++) {
            $property = strtolower((string) ($entries[$index]['property'] ?? ''));
            $previousProperty = strtolower((string) ($entries[$index - 1]['property'] ?? ''));
            if (
                $property === '-webkit-overflow-scrolling'
                && in_array($previousProperty, ['overflow', 'overflow-x', 'overflow-y'], true)
            ) {
                $entry = $entries[$index];
                $entries[$index] = $entries[$index - 1];
                $entries[$index - 1] = $entry;
            }
        }

        return $entries;
    }

    private function serializeDeclarationVisitorValue(mixed $value): string
    {
        if (is_array($value) && array_is_list($value)) {
            return $this->serializeComponentValueList($value);
        }

        if (is_array($value) && ($value['type'] ?? null) === 'length-percentage') {
            $inner = $value['value'] ?? null;
            if (is_array($inner) && ($inner['type'] ?? null) === 'dimension' && is_array($inner['value'] ?? null)) {
                return $this->serializeVisitorValue($this->applyValueVisitors($this->normalizeLengthValue($inner['value'])));
            }
        }

        if (is_array($value) && ($value['type'] ?? null) === 'color' && isset($value['value'])) {
            return $this->serializeDeclarationVisitorValue($value['value']);
        }

        if (is_array($value) && ($value['type'] ?? null) === 'rgb') {
            return $this->serializeRgbValue($value);
        }

        if (is_array($value)) {
            return $this->serializeVisitorValue($this->applyValueVisitors($this->normalizeVisitorValue($value)));
        }

        return (string) $value;
    }

    /**
     * @param list<mixed> $tokens
     */
    private function serializeComponentValueList(array $tokens): string
    {
        return implode(' ', array_map(fn (mixed $token): string => $this->serializeDeclarationVisitorValue($token), $tokens));
    }

    /**
     * @return list<mixed>
     */
    private function parseComponentValueList(string $value): array
    {
        return array_map(
            fn (string $token): mixed => $this->parseComponentValue($token),
            $this->splitWhitespaceTokens($value)
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseTransformFunctionListForVisitor(string $value): array
    {
        $transforms = [];
        $cursor = 0;
        $length = strlen($value);

        while ($cursor < $length) {
            $cursor = $this->skipWhitespace($value, $cursor);
            if ($cursor >= $length) {
                break;
            }

            if (preg_match('/[-_a-zA-Z][-_a-zA-Z0-9]*(?=\()/A', substr($value, $cursor), $matches) !== 1) {
                $transforms[] = ['type' => 'raw', 'value' => trim(substr($value, $cursor))];
                break;
            }

            $name = $matches[0];
            $open = $cursor + strlen($name);
            $close = $this->findMatchingParen($value, $open);
            if ($close === null) {
                $transforms[] = ['type' => 'raw', 'value' => trim(substr($value, $cursor))];
                break;
            }

            $arguments = trim(substr($value, $open + 1, $close - $open - 1));
            if (strtolower($name) === 'translatex') {
                $transforms[] = [
                    'type' => 'translateX',
                    'value' => $this->parseTransformArgumentForVisitor($arguments),
                ];
            } else {
                $transforms[] = [
                    'type' => 'raw',
                    'value' => $name . '(' . $arguments . ')',
                ];
            }

            $cursor = $close + 1;
        }

        return $transforms;
    }

    private function parseTransformArgumentForVisitor(string $argument): mixed
    {
        $argument = trim($argument);
        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))([a-zA-Z]+)$/', $argument, $matches) === 1) {
            return [
                'type' => 'dimension',
                'value' => [
                    'unit' => strtolower($matches[2]),
                    'value' => (float) $matches[1],
                ],
            ];
        }

        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))%$/', $argument, $matches) === 1) {
            return [
                'type' => 'percentage',
                'value' => (float) $matches[1] / 100,
            ];
        }

        if (preg_match('/^calc\\(/i', $argument) === 1) {
            $open = stripos($argument, '(');
            if ($open !== false && $this->findMatchingParen($argument, $open) === strlen($argument) - 1) {
                return [
                    'type' => 'calc',
                    'value' => [
                        'type' => 'raw',
                        'value' => trim(substr($argument, $open + 1, -1)),
                    ],
                ];
            }
        }

        return ['type' => 'raw', 'value' => $argument];
    }

    private function parseComponentValue(string $token): mixed
    {
        $token = trim($token);
        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))([a-zA-Z%]+)$/', $token, $matches) === 1) {
            return [
                'type' => 'length',
                'value' => [
                    'unit' => strtolower($matches[2]),
                    'value' => (float) $matches[1],
                ],
            ];
        }

        if (($rgb = $this->parseHexColorValue($token)) !== null) {
            return [
                'type' => 'color',
                'value' => $rgb,
            ];
        }

        if (($color = $this->parseCssColorValue($token)) !== null) {
            return [
                'type' => 'color',
                'value' => $color,
            ];
        }

        if (
            strlen($token) >= 2
            && (($token[0] === '"' && $token[strlen($token) - 1] === '"') || ($token[0] === "'" && $token[strlen($token) - 1] === "'"))
        ) {
            return [
                'type' => 'token',
                'raw' => $token,
                'value' => [
                    'type' => 'string',
                    'value' => stripcslashes(substr($token, 1, -1)),
                ],
            ];
        }

        if (preg_match('/^([a-zA-Z_-][-_a-zA-Z0-9]*)\(/', $token, $matches) === 1) {
            $name = $matches[1];
            $open = strlen($name);
            $close = $this->findMatchingParen($token, $open);
            if ($close === strlen($token) - 1) {
                $argumentsCss = substr($token, $open + 1, $close - $open - 1);

                return [
                    'type' => strtolower($name) === 'var' ? 'var' : 'function',
                    'value' => strtolower($name) === 'var'
                        ? $this->parseVariable($argumentsCss, $token)
                        : [
                            'name' => $name,
                            'arguments' => array_map(
                                fn (string $argument): mixed => $this->parseComponentValue($argument),
                                $this->splitTopLevel($argumentsCss, ',')
                            ),
                        ],
                ];
            }
        }

        if (preg_match('/^-?[_a-zA-Z][-_a-zA-Z0-9]*$/', $token) === 1) {
            return [
                'type' => 'token',
                'value' => [
                    'type' => 'ident',
                    'value' => $token,
                ],
            ];
        }

        return ['type' => 'raw', 'value' => $token];
    }

    /**
     * @return array{type:string,r:int,g:int,b:int,alpha:int|float}|null
     */
    private function parseHexColorValue(string $token): ?array
    {
        if (preg_match('/^#([0-9a-fA-F]{3})$/', $token, $matches) === 1) {
            return [
                'type' => 'rgb',
                'r' => hexdec(str_repeat($matches[1][0], 2)),
                'g' => hexdec(str_repeat($matches[1][1], 2)),
                'b' => hexdec(str_repeat($matches[1][2], 2)),
                'alpha' => 1,
            ];
        }

        if (preg_match('/^#([0-9a-fA-F]{6})$/', $token, $matches) === 1) {
            return [
                'type' => 'rgb',
                'r' => hexdec(substr($matches[1], 0, 2)),
                'g' => hexdec(substr($matches[1], 2, 2)),
                'b' => hexdec(substr($matches[1], 4, 2)),
                'alpha' => 1,
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseCssColorValue(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        if (($hex = $this->parseHexColorValue($token)) !== null) {
            return $hex;
        }

        $keyword = strtolower($token);
        if ($keyword === 'currentcolor') {
            return ['type' => 'currentcolor'];
        }

        $named = [
            'black' => [0, 0, 0],
            'blue' => [0, 0, 255],
            'green' => [0, 128, 0],
            'lime' => [0, 255, 0],
            'red' => [255, 0, 0],
            'transparent' => [0, 0, 0, 0],
            'white' => [255, 255, 255],
            'yellow' => [255, 255, 0],
        ];
        if (!isset($named[$keyword])) {
            return null;
        }

        [$red, $green, $blue] = $named[$keyword];

        return [
            'type' => 'rgb',
            'r' => $red,
            'g' => $green,
            'b' => $blue,
            'alpha' => $named[$keyword][3] ?? 1,
        ];
    }

    /**
     * @param array<string, mixed> $rgb
     */
    private function serializeRgbValue(array $rgb): string
    {
        $red = (int) ($rgb['r'] ?? 0);
        $green = (int) ($rgb['g'] ?? 0);
        $blue = (int) ($rgb['b'] ?? 0);
        $alpha = $rgb['alpha'] ?? 1;

        $hex = sprintf('#%02x%02x%02x', max(0, min(255, $red)), max(0, min(255, $green)), max(0, min(255, $blue)));
        if ($alpha === 1 || $alpha === 1.0) {
            if ($hex[1] === $hex[2] && $hex[3] === $hex[4] && $hex[5] === $hex[6]) {
                return '#' . $hex[1] . $hex[3] . $hex[5];
            }

            return $hex;
        }

        $alphaByte = (int) round(max(0.0, min(1.0, (float) $alpha)) * 255);

        return $hex . sprintf('%02x', $alphaByte);
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

        $selectors = $this->applySelectorVisitorToSelectorList($selectors);
        if ($selectors === []) {
            return '';
        }

        $entries = $this->processDeclarationEntries($this->declarationBlock->parseEntries($declarations));
        if ($entries === []) {
            return '';
        }

        $rule = [
            'type' => 'style',
            'kind' => 'style-rule',
            'selector' => implode(',', array_map('trim', $selectors)),
            'selectors' => array_values(array_map('trim', $selectors)),
            'declarations' => $entries,
            'value' => [
                'selectors' => array_map(fn (string $selector): array => $this->selectorComponentsFromString($selector), $selectors),
                'declarations' => $this->visitorDeclarationBlockFromEntries($entries),
            ],
        ];
        if ($visitStyleRule && $this->ruleVisitor !== null) {
            $replacement = $this->callAnyRuleVisitor(array_replace($rule, ['type' => 'style']));
            if ($replacement !== null) {
                return $this->emitStyleRuleReplacement($replacement, $rule);
            }
        }

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
        if (($replacement['type'] ?? null) === 'style' && isset($replacement['value']) && is_array($replacement['value'])) {
            return $this->emitReturnedStyleRule($this->returnedStyleValueFromReplacement($replacement, $fallbackRule));
        }
        if (
            isset($replacement['type'])
            && in_array($replacement['type'], ['ignored', 'media', 'supports', 'unknown'], true)
        ) {
            return $this->emitReplacement($replacement, null);
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
     * @param array<string, mixed> $replacement
     * @param array<string, mixed> $fallbackRule
     * @return array<string, mixed>
     */
    private function returnedStyleValueFromReplacement(array $replacement, array $fallbackRule): array
    {
        $value = is_array($replacement['value'] ?? null) ? $replacement['value'] : [];

        $fallbackSelectors = $fallbackRule['selectors'] ?? [];
        $replacementSelectors = $replacement['selectors'] ?? null;
        if (is_array($replacementSelectors)) {
            $normalizedReplacementSelectors = array_values(array_map('strval', $replacementSelectors));
            $normalizedFallbackSelectors = is_array($fallbackSelectors)
                ? array_values(array_map('strval', $fallbackSelectors))
                : [];

            if (!isset($value['selectors']) || $normalizedReplacementSelectors !== $normalizedFallbackSelectors) {
                $value['selectors'] = array_map(
                    fn (string $selector): array => $this->selectorComponentsFromString($selector),
                    $normalizedReplacementSelectors
                );
            }
        } elseif (!isset($value['selectors']) && is_array($fallbackSelectors)) {
            $value['selectors'] = array_map(
                fn (string $selector): array => $this->selectorComponentsFromString($selector),
                array_values(array_map('strval', $fallbackSelectors))
            );
        }

        $fallbackDeclarations = $fallbackRule['declarations'] ?? [];
        $replacementDeclarations = $replacement['declarations'] ?? null;
        if (is_array($replacementDeclarations)) {
            if (!isset($value['declarations']) || $replacementDeclarations != $fallbackDeclarations) {
                $value['declarations'] = $this->visitorDeclarationBlockFromEntries(
                    $this->stylesheetDeclarationEntries($replacementDeclarations)
                );
            }
        } elseif (!isset($value['declarations']) && is_array($fallbackDeclarations)) {
            $value['declarations'] = $this->visitorDeclarationBlockFromEntries(
                $this->stylesheetDeclarationEntries($fallbackDeclarations)
            );
        }

        return $value;
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

        $body = $this->emitDeclarationEntriesBody($entries);

        if ($body === '') {
            return '';
        }

        return implode(',', array_map('trim', $selectors)) . '{' . $body . '}';
    }

    /**
     * @param list<array<string, mixed>> $entries
     */
    private function emitDeclarationEntriesBody(array $entries): string
    {
        $body = '';
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $property = (string) ($entry['property'] ?? '');
            if ($property === '') {
                continue;
            }
            $body .= $this->rewriteDeclarationProperty($property) . ':' . $this->rewriteDeclarationValue((string) ($entry['value'] ?? ''), $property);
            if (!empty($entry['important'])) {
                $body .= ' !important';
            }
            $body .= ';';
        }

        return rtrim($body, ';');
    }

    private function rewriteDeclarationProperty(string $property): string
    {
        return str_starts_with($property, '--') ? $this->applyDashedIdentVisitor($property) : $property;
    }

    private function rewriteDeclarationValue(string $value, ?string $property = null): string
    {
        $rewritten = $this->rewriteValueTokens($this->rewriteValueFunctions($this->rewriteStandaloneLengths($value)));
        if ($property !== null) {
            $rewritten = $this->rewriteColorDeclarationValue($rewritten, $property);
        }

        return $property === null ? $rewritten : $this->rewriteAnimationCustomIdents($property, $rewritten);
    }

    private function rewriteColorDeclarationValue(string $value, string $property): string
    {
        if ($this->colorVisitor === null || !$this->isColorDeclarationProperty($property)) {
            return $value;
        }

        $trimmed = trim($value);
        $color = $this->parseCssColorValue($trimmed);
        if ($color === null) {
            return $value;
        }

        $replacement = ($this->colorVisitor)($color, $this);
        if ($replacement === null) {
            return $value;
        }

        return $this->serializeVisitorValue($this->normalizeColorVisitorValue($replacement));
    }

    private function isColorDeclarationProperty(string $property): bool
    {
        return in_array(strtolower($property), [
            'background-color',
            'border-block-end-color',
            'border-block-start-color',
            'border-bottom-color',
            'border-color',
            'border-inline-end-color',
            'border-inline-start-color',
            'border-left-color',
            'border-right-color',
            'border-top-color',
            'caret-color',
            'color',
            'outline-color',
            'text-decoration-color',
            'text-emphasis-color',
        ], true);
    }

    private function rewriteAtRulePreludeValue(string $value): string
    {
        return $this->rewriteValueFunctions($value);
    }

    private function rewriteKeyframesPrelude(string $prelude): string
    {
        $prelude = trim($prelude);
        if ($prelude === '') {
            return $prelude;
        }
        if (
            strlen($prelude) >= 2
            && (($prelude[0] === '"' && $prelude[strlen($prelude) - 1] === '"') || ($prelude[0] === "'" && $prelude[strlen($prelude) - 1] === "'"))
        ) {
            $name = stripcslashes(substr($prelude, 1, -1));

            return $this->applyCustomIdentVisitor($name);
        }

        return $this->isCustomIdentToken($prelude) ? $this->applyCustomIdentVisitor($prelude) : $prelude;
    }

    private function rewriteAnimationCustomIdents(string $property, string $value): string
    {
        if ($this->customIdentVisitor === null) {
            return $value;
        }

        $property = strtolower($property);
        if ($property === 'animation-name') {
            return implode(',', array_map(
                fn (string $name): string => $this->isAnimationCustomIdentToken($name) ? $this->applyCustomIdentVisitor($name) : $name,
                $this->splitTopLevel($value, ',')
            ));
        }

        if (!in_array($property, ['animation', '-webkit-animation', '-moz-animation'], true)) {
            return $value;
        }

        $layers = $this->splitTopLevel($value, ',');
        $rewritten = [];
        foreach ($layers as $layer) {
            $tokens = $this->splitWhitespaceTokens($layer);
            foreach ($tokens as &$token) {
                if ($this->isAnimationCustomIdentToken($token)) {
                    $token = $this->applyCustomIdentVisitor($token);
                }
            }
            unset($token);
            $rewritten[] = implode(' ', $tokens);
        }

        return implode(',', $rewritten);
    }

    private function isAnimationCustomIdentToken(string $token): bool
    {
        $token = trim($token);
        if (!$this->isCustomIdentToken($token)) {
            return false;
        }

        return !in_array(strtolower($token), [
            'alternate',
            'alternate-reverse',
            'backwards',
            'both',
            'ease',
            'ease-in',
            'ease-in-out',
            'ease-out',
            'forwards',
            'infinite',
            'linear',
            'none',
            'normal',
            'paused',
            'reverse',
            'running',
            'step-end',
            'step-start',
        ], true);
    }

    private function isCustomIdentToken(string $token): bool
    {
        return preg_match('/^-?[_a-zA-Z][-_a-zA-Z0-9]*$/', trim($token)) === 1
            && !in_array(strtolower(trim($token)), ['inherit', 'initial', 'revert', 'revert-layer', 'unset'], true);
    }

    private function applyDashedIdentVisitor(string $ident): string
    {
        if ($this->dashedIdentVisitor === null) {
            return $ident;
        }

        $replacement = ($this->dashedIdentVisitor)($ident, $this);
        if ($replacement === null) {
            return $ident;
        }
        if (!is_string($replacement) || !str_starts_with($replacement, '--')) {
            throw new \InvalidArgumentException('DashedIdent visitor must return a dashed identifier string');
        }

        return $replacement;
    }

    private function applyCustomIdentVisitor(string $ident): string
    {
        if ($this->customIdentVisitor === null) {
            return $ident;
        }

        $replacement = ($this->customIdentVisitor)($ident, $this);
        if ($replacement === null) {
            return $ident;
        }
        if (!is_string($replacement) || !$this->isCustomIdentToken($replacement)) {
            throw new \InvalidArgumentException('CustomIdent visitor must return a custom identifier string');
        }

        return $replacement;
    }

    private function rewriteStandaloneLengths(string $value): string
    {
        if ($this->lengthVisitor === null) {
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

            if (preg_match('/[+-]?(?:\d+|\d*\.\d+)(?:[a-zA-Z%]+)/A', substr($value, $i), $matches) === 1) {
                $raw = $matches[0];
                $before = $i > 0 ? $value[$i - 1] : '';
                $after = $value[$i + strlen($raw)] ?? '';
                if (
                    ($before === '' || !preg_match('/[-_a-zA-Z0-9.]/', $before))
                    && ($after === '' || !preg_match('/[-_a-zA-Z0-9]/', $after))
                    && preg_match('/^([+-]?(?:\d+|\d*\.\d+))([a-zA-Z%]+)$/', $raw, $parts) === 1
                ) {
                    $replacement = ($this->lengthVisitor)([
                        'unit' => strtolower($parts[2]),
                        'value' => (float) $parts[1],
                    ], $this);
                    if ($replacement !== null) {
                        $output .= $this->serializeVisitorValue($this->normalizeLengthValue($replacement));
                        $i += strlen($raw) - 1;
                        continue;
                    }
                }
            }

            $output .= $char;
        }

        return $output;
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
        if ($lower === 'env' && ($this->environmentVariableVisitors !== [] || $this->genericEnvironmentVariableVisitor !== null || $this->dashedIdentVisitor !== null)) {
            $environmentVariable = $this->parseEnvironmentVariable($argumentsCss, $raw);
            $replacement = $this->callEnvironmentVariableVisitor($environmentVariable);

            return $replacement === null ? ['type' => 'env', 'value' => $environmentVariable] : $this->applyValueVisitors($this->normalizeVisitorValue($replacement));
        }

        if ($lower === 'var' && ($this->variableVisitors !== [] || $this->genericVariableVisitor !== null || $this->dashedIdentVisitor !== null)) {
            $variable = $this->parseVariable($argumentsCss, $raw);
            $replacement = $this->callVariableVisitor($variable);

            return $replacement === null ? ['type' => 'var', 'value' => $variable] : $this->applyValueVisitors($this->normalizeVisitorValue($replacement));
        }

        if ($lower === 'url' && $this->urlVisitor !== null) {
            $url = $this->parseUrlValue($argumentsCss, $raw);
            $replacement = ($this->urlVisitor)($url, $this);

            return $replacement === null ? null : [
                'type' => 'url',
                'value' => $this->normalizeUrlVisitorValue($replacement, $url),
            ];
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

        $color = $this->colorComponents($value);
        if ($color !== null && $this->colorVisitor !== null) {
            $replacement = ($this->colorVisitor)($color, $this);
            if ($replacement !== null) {
                return $this->normalizeColorVisitorValue($replacement);
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

        if (
            isset($value['value'])
            && is_array($value['value'])
            && ($value['value']['type'] ?? null) === 'value'
            && is_array($value['value']['value'] ?? null)
        ) {
            $unit = $value['value']['value']['unit'] ?? null;
            $number = $value['value']['value']['value'] ?? null;
        } elseif (isset($value['value']) && is_array($value['value'])) {
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

    /**
     * @return array<string, mixed>|null
     */
    private function colorComponents(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        if (($value['type'] ?? null) === 'color' && isset($value['value']) && is_array($value['value'])) {
            return $this->normalizeColorVisitorValue($value['value']);
        }

        if (in_array(($value['type'] ?? null), ['rgb', 'currentcolor'], true)) {
            return $this->normalizeColorVisitorValue($value);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeColorVisitorValue(mixed $value): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('Color visitor must return a color array or null');
        }

        if (($value['type'] ?? null) === 'color' && isset($value['value']) && is_array($value['value'])) {
            return $this->normalizeColorVisitorValue($value['value']);
        }

        if (!is_string($value['type'] ?? null)) {
            throw new \InvalidArgumentException('Color visitor must return a typed color array or null');
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
        if (array_is_list($value)) {
            return implode(' ', array_map(fn (mixed $part): string => $this->serializeVisitorValue($part), $value));
        }

        if (isset($value['raw']) && is_string($value['raw'])) {
            return $value['raw'];
        }

        $type = $value['type'] ?? null;
        if ($type === 'translateX') {
            return 'translateX(' . $this->serializeTransformArgument($value['value'] ?? '') . ')';
        }
        if ($type === 'calc') {
            return $this->serializeCalcValue($value['value'] ?? '');
        }
        if ($type === 'product') {
            return $this->serializeCalcProduct($value['value'] ?? []);
        }
        if ($type === 'percentage' && (is_int($value['value'] ?? null) || is_float($value['value'] ?? null))) {
            return $this->formatNumber($value['value'] * 100) . '%';
        }
        if ($type === 'length') {
            $length = $this->lengthComponents($value);
            if ($length !== null) {
                return $this->formatNumber($length['value']) . $length['unit'];
            }
        }
        if ($type === 'length-percentage' && isset($value['value']) && is_array($value['value'])) {
            return $this->serializeVisitorValue($value['value']);
        }
        if ($type === 'stretch') {
            $prefixes = $value['vendorPrefix'] ?? [];
            if (is_array($prefixes)) {
                foreach ($prefixes as $prefix) {
                    if (strtolower((string) $prefix) === 'webkit') {
                        return '-webkit-fill-available';
                    }
                }
            }

            return 'stretch';
        }
        if ($type === 'dimension' && isset($value['value']) && is_array($value['value'])) {
            $dimension = $value['value'];
            if (isset($dimension['unit'], $dimension['value']) && is_string($dimension['unit']) && (is_int($dimension['value']) || is_float($dimension['value']))) {
                return $this->formatNumber($dimension['value']) . strtolower($dimension['unit']);
            }
        }
        if ($type === 'rgb') {
            $r = max(0, min(255, (int) ($value['r'] ?? 0)));
            $g = max(0, min(255, (int) ($value['g'] ?? 0)));
            $b = max(0, min(255, (int) ($value['b'] ?? 0)));
            $alpha = $value['alpha'] ?? 1;
            if ($alpha === 1 || $alpha === 1.0) {
                return sprintf('#%02x%02x%02x', $r, $g, $b);
            }

            return 'rgba(' . $r . ',' . $g . ',' . $b . ',' . $this->formatNumber((float) $alpha) . ')';
        }
        if ($type === 'currentcolor') {
            return 'currentColor';
        }
        if ($type === 'color' && isset($value['value'])) {
            return $this->serializeVisitorValue($value['value']);
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
            if (($token['type'] ?? null) === 'at-keyword') {
                return '@' . (string) ($token['value'] ?? '');
            }
            if (($token['type'] ?? null) === 'delim') {
                return (string) ($token['value'] ?? '');
            }
            if (($token['type'] ?? null) === 'number' && (is_int($token['value'] ?? null) || is_float($token['value'] ?? null))) {
                return $this->formatNumber($token['value']);
            }
            if (($token['type'] ?? null) === 'percentage' && (is_int($token['value'] ?? null) || is_float($token['value'] ?? null))) {
                return $this->formatNumber($token['value'] * 100) . '%';
            }
            if (($token['type'] ?? null) === 'dimension' && isset($token['unit'], $token['value']) && is_string($token['unit']) && (is_int($token['value']) || is_float($token['value']))) {
                return $this->formatNumber($token['value']) . $token['unit'];
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
        if ($type === 'url') {
            return $this->serializeUrlValue(is_array($value['value'] ?? null) ? $value['value'] : $value);
        }
        if ($type === 'function' && isset($value['value']) && is_array($value['value'])) {
            $function = $value['value'];
            $arguments = $function['arguments'] ?? [];
            if (!is_array($arguments)) {
                $arguments = [];
            }

            $name = (string) ($function['name'] ?? '');
            $separator = strtolower($name) === 'calc' ? '' : ',';

            return $name . '(' . implode($separator, array_map(fn (mixed $argument): string => $this->serializeVisitorValue($argument), $arguments)) . ')';
        }

        return (string) ($value['value'] ?? '');
    }

    private function serializeTransformArgument(mixed $value): string
    {
        if (is_array($value) && ($value['type'] ?? null) === 'calc') {
            $payload = $value['value'] ?? '';
            if (is_array($payload) && ($payload['type'] ?? null) === 'product') {
                return $this->serializeCalcProduct($payload['value'] ?? []);
            }

            return $this->serializeCalcValue($payload);
        }

        return $this->serializeVisitorValue($value);
    }

    private function serializeCalcValue(mixed $value): string
    {
        if (is_array($value) && ($value['type'] ?? null) === 'product') {
            return 'calc(' . $this->serializeCalcProduct($value['value'] ?? []) . ')';
        }
        if (is_array($value) && ($value['type'] ?? null) === 'raw') {
            return 'calc(' . trim((string) ($value['value'] ?? '')) . ')';
        }
        if (is_array($value)) {
            return 'calc(' . $this->serializeVisitorValue($value) . ')';
        }

        return 'calc(' . trim((string) $value) . ')';
    }

    private function serializeCalcProduct(mixed $factors): string
    {
        if (!is_array($factors)) {
            return $this->serializeVisitorValue($factors);
        }

        $parts = [];
        foreach ($factors as $factor) {
            $parts[] = $this->serializeCalcFactor($factor);
        }

        return implode('*', array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    private function serializeCalcFactor(mixed $factor): string
    {
        if (is_array($factor) && ($factor['type'] ?? null) === 'calc') {
            return $this->serializeCalcValue($factor['value'] ?? '');
        }

        return $this->serializeVisitorValue($factor);
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
        if (is_array($replacement)) {
            return $this->serializeVisitorValue($this->normalizeVisitorValue($replacement));
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

            if (preg_match('/([+-]?(?:\d+|\d*\.\d+))(--[-_a-zA-Z0-9]+)/A', substr($value, $i), $matches) === 1) {
                $raw = $matches[0];
                $before = $i > 0 ? $value[$i - 1] : '';
                $after = $value[$i + strlen($raw)] ?? '';
                if (
                    ($before === '' || !preg_match('/[-_a-zA-Z0-9.]/', $before))
                    && ($after === '' || !preg_match('/[-_a-zA-Z0-9]/', $after))
                ) {
                    $replacement = $this->callTokenVisitor('dimension', [
                        'type' => 'dimension',
                        'value' => (float) $matches[1],
                        'unit' => $matches[2],
                        'raw' => $raw,
                    ]);
                    if ($replacement !== null) {
                        $output .= $replacement;
                        $i += strlen($raw) - 1;
                        continue;
                    }
                }
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
        if (is_array($replacement)) {
            if (array_is_list($replacement)) {
                return $this->serializeComponentValueList($replacement);
            }

            return $this->serializeVisitorValue($this->applyValueVisitors($this->normalizeVisitorValue($replacement)));
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
     * @return array{url:string,raw:string,loc:array{line:int,column:int}}
     */
    private function parseUrlValue(string $argumentsCss, string $raw): array
    {
        $url = trim($argumentsCss);
        if (
            strlen($url) >= 2
            && (($url[0] === '"' && $url[strlen($url) - 1] === '"') || ($url[0] === "'" && $url[strlen($url) - 1] === "'"))
        ) {
            $url = stripcslashes(substr($url, 1, -1));
        }

        return [
            'url' => $url,
            'raw' => $raw,
            'loc' => [
                'line' => 1,
                'column' => 1,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $fallback
     * @return array{url:string,raw?:string,loc?:array<string, int>}
     */
    private function normalizeUrlVisitorValue(mixed $replacement, array $fallback): array
    {
        if (is_string($replacement)) {
            return array_replace($fallback, ['url' => $replacement]);
        }

        if (!is_array($replacement)) {
            throw new \InvalidArgumentException('Url visitor must return a URL array, string, or null');
        }

        if (($replacement['type'] ?? null) === 'url' && is_array($replacement['value'] ?? null)) {
            $replacement = $replacement['value'];
        }

        $url = $replacement['url'] ?? $fallback['url'] ?? '';
        if (!is_string($url)) {
            throw new \InvalidArgumentException('Url visitor replacement must contain a string url');
        }

        $normalized = array_replace($fallback, $replacement);
        $normalized['url'] = $url;

        return $normalized;
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
     * @param list<string>|null $parentSelectors
     * @return list<array<string, mixed>>
     */
    private function parseReturnedRuleList(string $css, ?array $parentSelectors): array
    {
        $rules = [];
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
                if ($statement !== '' && str_starts_with($statement, '@')) {
                    [$name, $prelude] = $this->parseAtPrelude($statement);
                    $rules[] = ['type' => 'unknown', 'value' => $this->buildUnknownRule($name, $prelude, null, $parentSelectors)];
                }
                $cursor = $nextStatement + 1;
                continue;
            }

            if ($nextBlock === null) {
                break;
            }

            $prelude = trim(substr($css, $cursor, $nextBlock - $cursor));
            $close = $this->findMatchingBrace($css, $nextBlock);
            $body = substr($css, $nextBlock + 1, $close - $nextBlock - 1);
            if (str_starts_with($prelude, '@')) {
                [$name, $atPrelude] = $this->parseAtPrelude($prelude);
                if ($name === 'media') {
                    $rules[] = $this->buildMediaRule($atPrelude, $body, $parentSelectors);
                } elseif ($name === 'supports') {
                    $rules[] = [
                        'type' => 'supports',
                        'value' => [
                            'condition' => $this->parseSupportsConditionForVisitor($atPrelude),
                            'rules' => $this->parseReturnedRuleList($body, $parentSelectors),
                        ],
                    ];
                } else {
                    $rules[] = ['type' => 'unknown', 'value' => $this->buildUnknownRule($name, $atPrelude, $body, $parentSelectors)];
                }
            } else {
                $selectors = $parentSelectors === null
                    ? $this->splitTopLevel($prelude, ',')
                    : $this->resolveNestedSelectors($parentSelectors, $prelude);
                $rules[] = [
                    'type' => 'style',
                    'value' => [
                        'selectors' => array_map(fn (string $selector): array => $this->parseReturnedSelector($selector), $selectors),
                        'declarations' => [
                            'declarations' => $this->parseReturnedDeclarations($body),
                            'importantDeclarations' => [],
                        ],
                    ],
                ];
            }

            $cursor = $close + 1;
        }

        return $rules;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseReturnedDeclarations(string $css): array
    {
        return array_map(
            static fn (array $entry): array => [
                'property' => $entry['property'],
                'raw' => $entry['value'],
                'important' => $entry['important'],
            ],
            $this->declarationBlock->parseEntries($css)
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseReturnedSelector(string $selector): array
    {
        $selector = trim($selector);
        if ($selector === '*') {
            return [['type' => 'universal']];
        }

        $components = [];
        foreach (preg_split('/(\s+|[>+~])/', $selector, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            if (trim($part) === '') {
                $components[] = ['type' => 'combinator', 'value' => 'descendant'];
                continue;
            }
            if (in_array($part, ['>', '+', '~'], true)) {
                $components[] = ['type' => 'combinator', 'value' => $part];
                continue;
            }

            foreach ($this->parseCompoundReturnedSelector($part) as $component) {
                $components[] = $component;
            }
        }

        return $components;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseCompoundReturnedSelector(string $selector): array
    {
        $components = [];
        $offset = 0;
        $length = strlen($selector);
        while ($offset < $length) {
            $char = $selector[$offset];
            if ($char === '.') {
                $name = $this->readSelectorIdentifier($selector, $offset + 1);
                $components[] = ['type' => 'class', 'name' => $this->unescapeSelectorIdentifier($name)];
                $offset += strlen($name) + 1;
                continue;
            }
            if ($char === '#') {
                $name = $this->readSelectorIdentifier($selector, $offset + 1);
                $components[] = ['type' => 'id', 'name' => $this->unescapeSelectorIdentifier($name)];
                $offset += strlen($name) + 1;
                continue;
            }
            if ($char === ':') {
                $name = $this->readSelectorIdentifier($selector, $offset + 1);
                $components[] = ['type' => 'pseudo-class', 'kind' => $name];
                $offset += strlen($name) + 1;
                continue;
            }
            if ($char === '*') {
                $components[] = ['type' => 'universal'];
                $offset++;
                continue;
            }
            $name = $this->readSelectorIdentifier($selector, $offset);
            if ($name === '') {
                $offset++;
                continue;
            }
            $components[] = ['type' => 'type', 'name' => $this->unescapeSelectorIdentifier($name)];
            $offset += strlen($name);
        }

        return $components;
    }

    private function readSelectorIdentifier(string $selector, int $offset): string
    {
        $identifier = '';
        $length = strlen($selector);
        for ($i = $offset; $i < $length; $i++) {
            $char = $selector[$i];
            if ($char === '\\' && $i + 1 < $length) {
                $identifier .= $char . $selector[++$i];
                continue;
            }
            if (!preg_match('/[-_a-zA-Z0-9]/', $char)) {
                break;
            }
            $identifier .= $char;
        }

        return $identifier;
    }

    private function unescapeSelectorIdentifier(string $identifier): string
    {
        return str_replace('\\:', ':', $identifier);
    }

    /**
     * @param array<string, mixed> $variable
     */
    private function serializeVariableValue(array $variable): string
    {
        $name = self::variableCallbackName($variable);
        if (!str_starts_with($name, '--')) {
            throw new \InvalidArgumentException('Dashed idents must start with --');
        }
        $name = $this->applyDashedIdentVisitor($name);
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
        if (str_starts_with($name, '--')) {
            $name = $this->applyDashedIdentVisitor($name);
        }
        $fallback = $environmentVariable['fallback'] ?? null;
        if (!is_array($fallback) || $fallback === []) {
            return 'env(' . $name . ')';
        }

        return 'env(' . $name . ',' . implode(',', array_map(fn (mixed $value): string => $this->serializeVisitorValue($value), $fallback)) . ')';
    }

    /**
     * @param array<string, mixed> $url
     */
    private function serializeUrlValue(array $url): string
    {
        $value = (string) ($url['url'] ?? '');
        if ($value === '') {
            return 'url()';
        }

        if (preg_match('/[\s"\'()]/', $value) === 1) {
            return 'url("' . addcslashes($value, "\\\"") . '")';
        }

        return 'url(' . $value . ')';
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

    private function isKeyframesAtRule(string $name): bool
    {
        return in_array(strtolower($name), ['keyframes', '-webkit-keyframes', '-moz-keyframes'], true);
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

    /**
     * @return list<string>
     */
    private function splitTopLevelKeyword(string $value, string $keyword): array
    {
        $parts = [''];
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
        $length = strlen($value);
        $keywordLength = strlen($keyword);

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
                $parts[array_key_last($parts)] .= $char;
                continue;
            }
            if ($char === '(') {
                $parenDepth++;
                $parts[array_key_last($parts)] .= $char;
                continue;
            }
            if ($char === ')') {
                $parenDepth = max(0, $parenDepth - 1);
                $parts[array_key_last($parts)] .= $char;
                continue;
            }
            if ($char === '[') {
                $bracketDepth++;
                $parts[array_key_last($parts)] .= $char;
                continue;
            }
            if ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
                $parts[array_key_last($parts)] .= $char;
                continue;
            }

            if (
                $parenDepth === 0
                && $bracketDepth === 0
                && strncasecmp(substr($value, $i, $keywordLength), $keyword, $keywordLength) === 0
                && !$this->isIdentCharacter($value[$i - 1] ?? '')
                && !$this->isIdentCharacter($value[$i + $keywordLength] ?? '')
            ) {
                $parts[] = '';
                $i += $keywordLength - 1;
                continue;
            }

            $parts[array_key_last($parts)] .= $char;
        }

        return array_values(array_filter(array_map('trim', $parts), static fn (string $part): bool => $part !== ''));
    }

    private function unwrapSingleParenthesizedCondition(string $condition): string
    {
        $condition = trim($condition);
        if ($condition === '' || $condition[0] !== '(') {
            return $condition;
        }

        return $this->findMatchingParen($condition, 0) === strlen($condition) - 1
            ? trim(substr($condition, 1, -1))
            : $condition;
    }

    private function findTopLevelCharacter(string $value, string $needle): ?int
    {
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
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
                continue;
            }
            if ($char === '(') {
                $parenDepth++;
                continue;
            }
            if ($char === ')') {
                $parenDepth = max(0, $parenDepth - 1);
                continue;
            }
            if ($char === '[') {
                $bracketDepth++;
                continue;
            }
            if ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
                continue;
            }
            if ($parenDepth === 0 && $bracketDepth === 0 && $char === $needle) {
                return $i;
            }
        }

        return null;
    }

    private function isIdentCharacter(string $char): bool
    {
        return $char !== '' && preg_match('/[-_a-zA-Z0-9]/', $char) === 1;
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
