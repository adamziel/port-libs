<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class CustomAtRuleTransformer
{
    private const CUSTOM_PRELUDE_TOKEN_REVISIT_LIMIT = 8;

    /** @var array<string, array<string, mixed>> */
    private array $customAtRules = [];

    /** @var callable|null */
    private $ruleVisitor = null;

    /** @var callable|null */
    private $ruleExitVisitor = null;

    /** @var array<string, callable> */
    private array $ruleVisitors = [];

    /** @var array<string, callable> */
    private array $ruleExitVisitors = [];

    /** @var callable|null */
    private $genericRuleVisitor = null;

    /** @var callable|null */
    private $genericRuleExitVisitor = null;

    /** @var array<string, callable> */
    private array $unknownRuleVisitors = [];

    /** @var array<string, callable> */
    private array $unknownRuleExitVisitors = [];

    /** @var callable|null */
    private $genericUnknownRuleVisitor = null;

    /** @var callable|null */
    private $genericUnknownRuleExitVisitor = null;

    /** @var callable|null */
    private $styleRuleVisitor = null;

    /** @var callable|null */
    private $styleRuleExitVisitor = null;

    /** @var callable|null */
    private $mediaRuleVisitor = null;

    /** @var callable|null */
    private $mediaRuleExitVisitor = null;

    /** @var callable|null */
    private $supportsRuleVisitor = null;

    /** @var callable|null */
    private $supportsRuleExitVisitor = null;

    /** @var callable|null */
    private $containerRuleVisitor = null;

    /** @var callable|null */
    private $containerRuleExitVisitor = null;

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

    private bool $suppressReturnedRuleExitVisitors = false;

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
    private array $environmentVariableExitVisitors = [];

    /** @var callable|null */
    private $genericEnvironmentVariableExitVisitor = null;

    /** @var array<string, callable> */
    private array $variableVisitors = [];

    /** @var callable|null */
    private $genericVariableVisitor = null;

    /** @var array<string, callable> */
    private array $variableExitVisitors = [];

    /** @var callable|null */
    private $genericVariableExitVisitor = null;

    /** @var callable|null */
    private $lengthVisitor = null;

    /** @var callable|null */
    private $angleVisitor = null;

    /** @var callable|null */
    private $timeVisitor = null;

    /** @var callable|null */
    private $ratioVisitor = null;

    /** @var callable|null */
    private $resolutionVisitor = null;

    /** @var callable|null */
    private $urlVisitor = null;

    /** @var callable|null */
    private $imageVisitor = null;

    /** @var callable|null */
    private $imageExitVisitor = null;

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

    private bool $functionReplacementAppliedColorVisitor = false;

    /** @var array<string, bool> */
    private array $rawValueVisitorReplacementProperties = [];

    private ?string $activeDeclarationProperty = null;

    private DeclarationBlock $declarationBlock;

    private CssMinifier $minifier;

    public function __construct()
    {
        $this->declarationBlock = new DeclarationBlock(false);
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
                    return self::applyComposedRuleVisitors(
                        $visitors,
                        ['type' => 'custom', 'value' => $rule],
                        $transformer,
                        false
                    );
                },
                'unknown' => static function (array $rule, self $transformer) use ($visitors): mixed {
                    return self::applyComposedRuleVisitors(
                        $visitors,
                        ['type' => 'unknown', 'value' => $rule],
                        $transformer,
                        false
                    );
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
                'supports' => static function (array $rule, self $transformer) use ($visitors): mixed {
                    $rules = [$rule];
                    $changed = false;
                    foreach ($visitors as $visitor) {
                        $callback = self::supportsRuleVisitorCallback($visitor);
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
                            foreach (self::normalizeRuleVisitorReplacement($replacement, 'Rule.supports') as $nextRule) {
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
                'container' => static function (array $rule, self $transformer) use ($visitors): mixed {
                    $rules = [$rule];
                    $changed = false;
                    foreach ($visitors as $visitor) {
                        $callback = self::containerRuleVisitorCallback($visitor);
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
                            foreach (self::normalizeRuleVisitorReplacement($replacement, 'Rule.container') as $nextRule) {
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
            'RuleExit' => [
                'custom' => static function (array $rule, self $transformer) use ($visitors): mixed {
                    return self::applyComposedRuleVisitors(
                        $visitors,
                        ['type' => 'custom', 'value' => $rule],
                        $transformer,
                        true
                    );
                },
                'unknown' => static function (array $rule, self $transformer) use ($visitors): mixed {
                    return self::applyComposedRuleVisitors(
                        $visitors,
                        ['type' => 'unknown', 'value' => $rule],
                        $transformer,
                        true
                    );
                },
                'style' => static function (array $rule, self $transformer) use ($visitors): mixed {
                    $rules = [$rule];
                    $changed = false;
                    foreach ($visitors as $visitor) {
                        $callback = self::styleRuleExitVisitorCallback($visitor);
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
                        $callback = self::mediaRuleExitVisitorCallback($visitor);
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
                            foreach (self::normalizeRuleVisitorReplacement($replacement, 'RuleExit.media') as $nextRule) {
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
                'supports' => static function (array $rule, self $transformer) use ($visitors): mixed {
                    $rules = [$rule];
                    $changed = false;
                    foreach ($visitors as $visitor) {
                        $callback = self::supportsRuleExitVisitorCallback($visitor);
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
                            foreach (self::normalizeRuleVisitorReplacement($replacement, 'RuleExit.supports') as $nextRule) {
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
                'container' => static function (array $rule, self $transformer) use ($visitors): mixed {
                    $rules = [$rule];
                    $changed = false;
                    foreach ($visitors as $visitor) {
                        $callback = self::containerRuleExitVisitorCallback($visitor);
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
                            foreach (self::normalizeRuleVisitorReplacement($replacement, 'RuleExit.container') as $nextRule) {
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
                'ident' => static fn (array $token, self $transformer): mixed => self::callComposedTokenVisitors($visitors, 'ident', $token, $transformer),
                'at-keyword' => static fn (array $token, self $transformer): mixed => self::callComposedTokenVisitors($visitors, 'at-keyword', $token, $transformer),
                'hash' => static fn (array $token, self $transformer): mixed => self::callComposedTokenVisitors($visitors, 'hash', $token, $transformer),
                'id-hash' => static fn (array $token, self $transformer): mixed => self::callComposedTokenVisitors($visitors, 'id-hash', $token, $transformer),
                'string' => static fn (array $token, self $transformer): mixed => self::callComposedTokenVisitors($visitors, 'string', $token, $transformer),
                'number' => static fn (array $token, self $transformer): mixed => self::callComposedTokenVisitors($visitors, 'number', $token, $transformer),
                'percentage' => static fn (array $token, self $transformer): mixed => self::callComposedTokenVisitors($visitors, 'percentage', $token, $transformer),
                'dimension' => static fn (array $token, self $transformer): mixed => self::callComposedTokenVisitors($visitors, 'dimension', $token, $transformer),
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
                $declarations = self::applyComposedDeclarationVisitors($visitors, $declaration, false, $transformer);
                if ($declarations === null) {
                    return null;
                }

                return count($declarations) === 1 ? $declarations[0] : $declarations;
            },
            'DeclarationExit' => static function (array $declaration, self $transformer) use ($visitors): mixed {
                $declarations = self::applyComposedDeclarationVisitors($visitors, $declaration, true, $transformer);
                if ($declarations === null) {
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
            'EnvironmentVariableExit' => static function (array $environmentVariable, self $transformer) use ($visitors): mixed {
                foreach ($visitors as $visitor) {
                    $callback = self::environmentVariableExitVisitorCallback($visitor, $environmentVariable);
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
            'VariableExit' => static function (array $variable, self $transformer) use ($visitors): mixed {
                foreach ($visitors as $visitor) {
                    $callback = self::variableExitVisitorCallback($visitor, $variable);
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
            'Angle' => static function (array $angle, self $transformer) use ($visitors): mixed {
                return self::callComposedUnitVariantVisitors($visitors, 'Angle', $angle, $transformer);
            },
            'Time' => static function (array $time, self $transformer) use ($visitors): mixed {
                return self::callComposedUnitVariantVisitors($visitors, 'Time', $time, $transformer);
            },
            'Ratio' => static function (array $ratio, self $transformer) use ($visitors): mixed {
                return self::callComposedRatioVisitors($visitors, $ratio, $transformer);
            },
            'Resolution' => static function (array $resolution, self $transformer) use ($visitors): mixed {
                return self::callComposedUnitVariantVisitors($visitors, 'Resolution', $resolution, $transformer);
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
            'Image' => static function (array $image, self $transformer) use ($visitors): mixed {
                $value = $image;
                $changed = false;
                foreach ($visitors as $visitor) {
                    $callback = self::imageVisitorCallback($visitor);
                    if ($callback === null) {
                        continue;
                    }

                    $replacement = $callback($value, $transformer);
                    if ($replacement !== null) {
                        $value = $transformer->normalizeImageVisitorValue($replacement, $value);
                        $changed = true;
                    }
                }

                return $changed ? $value : null;
            },
            'ImageExit' => static function (array $image, self $transformer) use ($visitors): mixed {
                $value = $image;
                $changed = false;
                foreach ($visitors as $visitor) {
                    $callback = self::imageExitVisitorCallback($visitor);
                    if ($callback === null) {
                        continue;
                    }

                    $replacement = $callback($value, $transformer);
                    if ($replacement !== null) {
                        $value = $transformer->normalizeImageVisitorValue($replacement, $value);
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
        if ($this->styleSheetVisitor !== null) {
            $stylesheet = $this->callStyleSheetVisitor($this->stylesheetVisitorFromCss($css));
            if ($stylesheet !== null) {
                $css = $this->stylesheetVisitorToCss($stylesheet);
            }
        }
        $code = $this->minifier->minify($this->processRuleList($css));
        $code = $this->restoreRawValueVisitorTokenBoundaries($code);
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

    public function container(string $prelude, string|array $body): array
    {
        return ['kind' => 'container', 'prelude' => trim($prelude), 'body' => $body];
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
        $this->rawValueVisitorReplacementProperties = [];
        $this->activeDeclarationProperty = null;

        $this->customAtRules = [];
        foreach ($customAtRules as $name => $definition) {
            $this->customAtRules[$this->normalizeAtRuleName((string) $name)] = $definition;
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
                    $this->ruleVisitors[$this->normalizeAtRuleName((string) $name)] = $callback;
                }
            }
        }
        foreach ($visitor as $name => $callback) {
            if (is_string($name) && is_callable($callback) && !in_array($name, ['Rule', 'Function', 'Token', 'custom', 'unknown', 'media', 'container'], true)) {
                $this->ruleVisitors[$this->normalizeAtRuleName($name)] = $callback;
            }
        }

        $ruleExitConfig = $visitor['RuleExit'] ?? null;
        $ruleExitSubVisitors = is_array($ruleExitConfig) ? $ruleExitConfig : [];

        $this->ruleExitVisitor = is_callable($ruleExitConfig) ? $ruleExitConfig : null;
        $this->ruleExitVisitors = [];
        $this->genericRuleExitVisitor = null;
        $customExitVisitors = $ruleExitSubVisitors['custom'] ?? [];
        if (is_callable($customExitVisitors)) {
            $this->genericRuleExitVisitor = $customExitVisitors;
        } elseif (is_array($customExitVisitors)) {
            foreach ($customExitVisitors as $name => $callback) {
                if (is_callable($callback)) {
                    $this->ruleExitVisitors[$this->normalizeAtRuleName((string) $name)] = $callback;
                }
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
                    $this->unknownRuleVisitors[$this->normalizeAtRuleName((string) $name)] = $callback;
                }
            }
        }

        $this->unknownRuleExitVisitors = [];
        $this->genericUnknownRuleExitVisitor = null;
        $unknownExitVisitors = $ruleExitSubVisitors['unknown'] ?? [];
        if (is_callable($unknownExitVisitors)) {
            $this->genericUnknownRuleExitVisitor = $unknownExitVisitors;
        } elseif (is_array($unknownExitVisitors)) {
            foreach ($unknownExitVisitors as $name => $callback) {
                if (is_callable($callback)) {
                    $this->unknownRuleExitVisitors[$this->normalizeAtRuleName((string) $name)] = $callback;
                }
            }
        }

        $this->styleRuleVisitor = null;
        $styleVisitor = $ruleSubVisitors['style'] ?? $visitor['style'] ?? null;
        if (is_callable($styleVisitor)) {
            $this->styleRuleVisitor = $styleVisitor;
        }

        $this->styleRuleExitVisitor = null;
        $styleExitVisitor = $ruleExitSubVisitors['style'] ?? null;
        if (is_callable($styleExitVisitor)) {
            $this->styleRuleExitVisitor = $styleExitVisitor;
        }

        $this->mediaRuleVisitor = null;
        $mediaVisitor = $ruleSubVisitors['media'] ?? $visitor['media'] ?? null;
        if (is_callable($mediaVisitor)) {
            $this->mediaRuleVisitor = $mediaVisitor;
        }

        $this->mediaRuleExitVisitor = null;
        $mediaExitVisitor = $ruleExitSubVisitors['media'] ?? null;
        if (is_callable($mediaExitVisitor)) {
            $this->mediaRuleExitVisitor = $mediaExitVisitor;
        }

        $this->supportsRuleVisitor = null;
        $supportsVisitor = $ruleSubVisitors['supports'] ?? $visitor['supports'] ?? null;
        if (is_callable($supportsVisitor)) {
            $this->supportsRuleVisitor = $supportsVisitor;
        }

        $this->supportsRuleExitVisitor = null;
        $supportsExitVisitor = $ruleExitSubVisitors['supports'] ?? null;
        if (is_callable($supportsExitVisitor)) {
            $this->supportsRuleExitVisitor = $supportsExitVisitor;
        }

        $this->containerRuleVisitor = null;
        $containerVisitor = $ruleSubVisitors['container'] ?? $visitor['container'] ?? null;
        if (is_callable($containerVisitor)) {
            $this->containerRuleVisitor = $containerVisitor;
        }

        $this->containerRuleExitVisitor = null;
        $containerExitVisitor = $ruleExitSubVisitors['container'] ?? null;
        if (is_callable($containerExitVisitor)) {
            $this->containerRuleExitVisitor = $containerExitVisitor;
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

        $this->environmentVariableExitVisitors = [];
        $this->genericEnvironmentVariableExitVisitor = null;
        $environmentVariableExitConfig = $visitor['EnvironmentVariableExit'] ?? [];
        if (is_callable($environmentVariableExitConfig)) {
            $this->genericEnvironmentVariableExitVisitor = $environmentVariableExitConfig;
        } elseif (is_array($environmentVariableExitConfig)) {
            foreach ($environmentVariableExitConfig as $name => $callback) {
                if (is_string($name) && is_callable($callback)) {
                    $this->environmentVariableExitVisitors[$name] = $callback;
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

        $this->variableExitVisitors = [];
        $this->genericVariableExitVisitor = null;
        $variableExitConfig = $visitor['VariableExit'] ?? [];
        if (is_callable($variableExitConfig)) {
            $this->genericVariableExitVisitor = $variableExitConfig;
        } elseif (is_array($variableExitConfig)) {
            foreach ($variableExitConfig as $name => $callback) {
                if (is_string($name) && is_callable($callback)) {
                    $this->variableExitVisitors[$name] = $callback;
                }
            }
        }

        $this->lengthVisitor = is_callable($visitor['Length'] ?? null) ? $visitor['Length'] : null;
        $this->angleVisitor = is_callable($visitor['Angle'] ?? null) ? $visitor['Angle'] : null;
        $this->timeVisitor = is_callable($visitor['Time'] ?? null) ? $visitor['Time'] : null;
        $this->ratioVisitor = is_callable($visitor['Ratio'] ?? null) ? $visitor['Ratio'] : null;
        $this->resolutionVisitor = is_callable($visitor['Resolution'] ?? null) ? $visitor['Resolution'] : null;
        $this->colorVisitor = is_callable($visitor['Color'] ?? null) ? $visitor['Color'] : null;
        $this->urlVisitor = is_callable($visitor['Url'] ?? null) ? $visitor['Url'] : null;
        $this->imageVisitor = is_callable($visitor['Image'] ?? null) ? $visitor['Image'] : null;
        $this->imageExitVisitor = is_callable($visitor['ImageExit'] ?? null) ? $visitor['ImageExit'] : null;
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
     * @return array{rules:list<array<string, mixed>>}|null
     */
    private function callStyleSheetVisitor(array $stylesheet): ?array
    {
        if ($this->styleSheetVisitor === null) {
            return null;
        }

        $replacement = ($this->styleSheetVisitor)($stylesheet, $this);
        if ($replacement === null) {
            return null;
        }
        if (!is_array($replacement)) {
            throw new \InvalidArgumentException('StyleSheet visitor must return a stylesheet array or null');
        }

        return $replacement;
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
            $ruleLocation = $this->sourceLocationForOffset($css, $cursor);

            $nextBlock = $this->findNextTopLevel($css, '{', $cursor);
            $nextStatement = $this->findNextTopLevel($css, ';', $cursor);

            if ($nextStatement !== null && ($nextBlock === null || $nextStatement < $nextBlock)) {
                $statement = trim(substr($css, $cursor, $nextStatement - $cursor));
                if ($statement !== '') {
                    $rules[] = str_starts_with($statement, '@')
                        ? $this->stylesheetAtRuleStatement($statement, $ruleLocation)
                        : $this->stylesheetRawRule($statement . ';');
                }
                $cursor = $nextStatement + 1;
                continue;
            }

            if ($nextBlock === null) {
                $tail = trim(substr($css, $cursor));
                if ($tail !== '') {
                    $rules[] = str_starts_with($tail, '@')
                        ? $this->stylesheetAtRuleStatement($tail, $ruleLocation)
                        : $this->stylesheetRawRule($tail);
                }
                break;
            }

            $prelude = trim(substr($css, $cursor, $nextBlock - $cursor));
            $close = $this->findMatchingBrace($css, $nextBlock);
            $body = substr($css, $nextBlock + 1, $close - $nextBlock - 1);
            $rules[] = str_starts_with($prelude, '@')
                ? $this->stylesheetAtRuleBlock($prelude, $body, $ruleLocation)
                : $this->stylesheetStyleRule($prelude, $body, $ruleLocation);
            $cursor = $close + 1;
        }

        return ['rules' => $rules];
    }

    /**
     * @return array<string, mixed>
     */
    private function stylesheetAtRuleStatement(string $statement, ?array $loc = null): array
    {
        [$name, $prelude] = $this->parseAtPrelude($statement);
        if ($this->isCustomAtRule($name)) {
            return ['type' => 'custom', 'value' => $this->buildCustomRule($name, $prelude, null, null, false, $loc)];
        }

        return ['type' => 'unknown', 'value' => $this->buildUnknownRule($name, $prelude, null, null, $loc)];
    }

    /**
     * @return array<string, mixed>
     */
    private function stylesheetAtRuleBlock(string $prelude, string $body, ?array $loc = null): array
    {
        [$name, $atPrelude] = $this->parseAtPrelude($prelude);
        if ($name === 'media') {
            return $this->buildMediaRule($atPrelude, $body, null, $loc);
        }
        if ($name === 'supports') {
            return [
                'type' => 'supports',
                'value' => [
                    'loc' => $loc ?? $this->defaultSourceLocation(),
                    'condition' => $this->parseSupportsConditionForVisitor($atPrelude),
                    'rules' => $this->parseReturnedRuleList($body, null),
                ],
            ];
        }
        if ($name === 'container') {
            return $this->buildContainerRule($atPrelude, $body, null, $loc);
        }
        if ($this->isCustomAtRule($name)) {
            return ['type' => 'custom', 'value' => $this->buildCustomRule($name, $atPrelude, $body, null, false, $loc)];
        }

        return ['type' => 'unknown', 'value' => $this->buildUnknownRule($name, $atPrelude, $body, null, $loc)];
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
    private function stylesheetStyleRule(string $selectorList, string $body, ?array $loc = null): array
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
                'loc' => $loc ?? $this->defaultSourceLocation(),
                'selectors' => array_map(fn (string $selector): array => $this->selectorComponentsFromString($selector), $selectors),
                'declarations' => [
                    'declarations' => $normal,
                    'importantDeclarations' => $important,
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
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

            if ($char === '#' && preg_match('/\#((?:\\\\.|[-_a-zA-Z0-9])+)/A', substr($selector, $cursor), $matches) === 1) {
                $components[] = ['type' => 'id', 'name' => str_replace('\\\\', '\\', $matches[1])];
                $cursor += strlen($matches[0]);
                continue;
            }

            if (in_array($char, ['>', '+', '~'], true)) {
                $components[] = ['type' => 'combinator', 'value' => $char];
                $cursor++;
                continue;
            }

            if ($char === '[') {
                $attribute = $this->parseAttributeSelectorComponent($selector, $cursor);
                if ($attribute !== null) {
                    $components[] = $attribute['component'];
                    $cursor = $attribute['end'];
                    continue;
                }
            }

            if ($char === ':' && ($selector[$cursor + 1] ?? '') === ':' && preg_match('/\:\:([-_a-zA-Z0-9]+)/A', substr($selector, $cursor), $matches) === 1) {
                $kind = $matches[1];
                $cursor += strlen($matches[0]);
                if (($selector[$cursor] ?? '') === '(') {
                    $close = $this->findMatchingParen($selector, $cursor);
                    if ($close !== null) {
                        $arguments = substr($selector, $cursor + 1, $close - $cursor - 1);
                        $components[] = $this->selectorFunctionalPseudoElementComponent($kind, $arguments);
                        $cursor = $close + 1;
                        continue;
                    }
                }

                $components[] = ['type' => 'pseudo-element', 'kind' => $kind];
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
     * @return array<string, mixed>
     */
    private function selectorFunctionalPseudoElementComponent(string $kind, string $arguments): array
    {
        $arguments = trim($arguments);
        $lower = strtolower($kind);

        if ($lower === 'part') {
            return [
                'type' => 'pseudo-element',
                'kind' => 'part',
                'names' => array_values(array_filter(
                    preg_split('/\s+/', $arguments) ?: [],
                    static fn (string $name): bool => $name !== ''
                )),
            ];
        }

        if ($lower === 'slotted') {
            return [
                'type' => 'pseudo-element',
                'kind' => 'slotted',
                'selector' => $this->selectorComponentsFromString($arguments),
            ];
        }

        if ($lower === 'cue' || $lower === 'cue-region') {
            return [
                'type' => 'pseudo-element',
                'kind' => $lower . '-function',
                'selector' => $this->selectorComponentsFromString($arguments),
            ];
        }

        return [
            'type' => 'pseudo-element',
            'kind' => 'custom-function',
            'name' => $kind,
            'arguments' => $arguments,
        ];
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
        if (($rule['type'] ?? null) === 'ignored') {
            return '';
        }
        if (($rule['type'] ?? null) === 'custom' && isset($rule['value']) && is_array($rule['value'])) {
            return $this->stylesheetCustomRuleToCss($rule['value']);
        }
        if (($rule['type'] ?? null) === 'unknown' && isset($rule['value']) && is_array($rule['value'])) {
            return $this->stylesheetUnknownRuleToCss($rule['value']);
        }
        if (($rule['type'] ?? null) === 'media' && isset($rule['value']) && is_array($rule['value'])) {
            return $this->stylesheetMediaRuleToCss($rule['value']);
        }
        if (($rule['type'] ?? null) === 'supports' && isset($rule['value']) && is_array($rule['value'])) {
            return $this->stylesheetSupportsRuleToCss($rule['value']);
        }
        if (($rule['type'] ?? null) === 'container' && isset($rule['value']) && is_array($rule['value'])) {
            return $this->stylesheetContainerRuleToCss($rule['value']);
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
     * @param array<string, mixed> $rule
     */
    private function stylesheetCustomRuleToCss(array $rule): string
    {
        $name = (string) ($rule['name'] ?? '');
        if ($name === '') {
            throw new \InvalidArgumentException('Custom at-rule replacement is missing a name');
        }

        $prelude = trim((string) ($rule['prelude'] ?? ''));
        $head = '@' . $name . ($prelude === '' ? '' : ' ' . $prelude);
        if (($rule['bodyType'] ?? null) === null) {
            return $head . ';';
        }

        return $head . '{' . ($this->serializeUnknownRuleBlockValue($rule) ?? (string) ($rule['body'] ?? '')) . '}';
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function stylesheetUnknownRuleToCss(array $rule): string
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

        return $head . '{' . (string) ($rule['body'] ?? '') . '}';
    }

    /**
     * @param array<string, mixed> $value
     */
    private function stylesheetMediaRuleToCss(array $value): string
    {
        $query = is_array($value['query'] ?? null)
            ? $this->returnedMediaQueryToCss($value['query'])
            : '';
        $rules = is_array($value['rules'] ?? null) ? $value['rules'] : [];
        $body = '';
        foreach ($rules as $rule) {
            $body .= $this->stylesheetVisitorRuleToCss($rule);
        }

        return '@media ' . $query . '{' . $body . '}';
    }

    /**
     * @param array<string, mixed> $value
     */
    private function stylesheetSupportsRuleToCss(array $value): string
    {
        $condition = is_array($value['condition'] ?? null)
            ? $this->returnedSupportsConditionToCss($value['condition'])
            : '';
        $rules = is_array($value['rules'] ?? null) ? $value['rules'] : [];
        $body = '';
        foreach ($rules as $rule) {
            $body .= $this->stylesheetVisitorRuleToCss($rule);
        }

        return '@supports ' . $condition . '{' . $body . '}';
    }

    /**
     * @param array<string, mixed> $value
     */
    private function stylesheetContainerRuleToCss(array $value): string
    {
        $prelude = $this->returnedContainerPreludeToCss($value);
        $rules = is_array($value['rules'] ?? null) ? $value['rules'] : [];
        $body = '';
        foreach ($rules as $rule) {
            $body .= $this->stylesheetVisitorRuleToCss($rule);
        }

        return '@container' . ($prelude === '' ? '' : ' ' . $prelude) . '{' . $body . '}';
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
            } elseif ($type === 'id') {
                $selector .= '#' . $this->escapeSelectorIdentifier((string) ($component['name'] ?? ''));
            } elseif ($type === 'type') {
                $selector .= (string) ($component['name'] ?? '');
            } elseif ($type === 'pseudo-class') {
                $selector .= $this->serializePseudoClassComponent($component);
            } elseif ($type === 'pseudo-element') {
                $selector .= $this->serializePseudoElementComponent($component);
            } elseif ($type === 'combinator') {
                $selector = rtrim($selector) . $this->serializeSelectorCombinator((string) ($component['value'] ?? ''));
            } elseif ($type === 'attribute') {
                $selector .= $this->serializeAttributeSelectorComponent($component);
            } elseif ($type === 'universal') {
                $selector .= '*';
            } elseif ($type === 'nesting') {
                $selector .= '&';
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

    /**
     * @param array<string, mixed> $component
     */
    private function serializePseudoElementComponent(array $component): string
    {
        $kind = (string) ($component['kind'] ?? '');
        if ($kind === '') {
            return '';
        }

        if ($kind === 'part') {
            $names = $component['names'] ?? [];
            if (!is_array($names)) {
                $names = [(string) $names];
            }

            return '::part(' . implode(' ', array_map('strval', $names)) . ')';
        }

        if ($kind === 'slotted') {
            $selector = $component['selector'] ?? [];

            return '::slotted(' . (is_array($selector) ? $this->serializeSelectorComponents($selector) : (string) $selector) . ')';
        }

        if ($kind === 'cue-function' || $kind === 'cue-region-function') {
            $selector = $component['selector'] ?? [];
            $name = $kind === 'cue-function' ? 'cue' : 'cue-region';

            return '::' . $name . '(' . (is_array($selector) ? $this->serializeSelectorComponents($selector) : (string) $selector) . ')';
        }

        if ($kind === 'custom') {
            $name = (string) ($component['name'] ?? '');

            return $name === '' ? '' : '::' . $name;
        }

        if ($kind === 'custom-function') {
            $name = (string) ($component['name'] ?? '');
            if ($name === '') {
                return '';
            }

            $arguments = $component['arguments'] ?? '';
            if (is_array($arguments)) {
                $arguments = implode(' ', array_map(fn (mixed $value): string => $this->serializeVisitorValue($value), $arguments));
            }

            return '::' . $name . '(' . (string) $arguments . ')';
        }

        if ($kind === 'webkit-scrollbar' && isset($component['value'])) {
            $value = (string) $component['value'];

            return $value === 'scrollbar' ? '::-webkit-scrollbar' : '::-webkit-scrollbar-' . $value;
        }

        return '::' . $kind;
    }

    private function serializeSelectorCombinator(string $value): string
    {
        return match ($value) {
            'descendant' => ' ',
            'child' => '>',
            'next-sibling' => '+',
            'later-sibling' => '~',
            default => $value,
        };
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
            $ruleLocation = $this->sourceLocationForOffset($css, $cursor);

            $nextBlock = $this->findNextTopLevel($css, '{', $cursor);
            $nextStatement = $this->findNextTopLevel($css, ';', $cursor);

            if ($nextStatement !== null && ($nextBlock === null || $nextStatement < $nextBlock)) {
                $statement = trim(substr($css, $cursor, $nextStatement - $cursor));
                if ($statement !== '') {
                    $output .= $this->processStatement($statement, null, $ruleLocation);
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
                    $output .= $this->processMediaRule($atPrelude, $body, null, $ruleLocation);
                } elseif ($name === 'supports') {
                    $output .= $this->processSupportsRule($atPrelude, $body, null, $ruleLocation);
                } elseif ($name === 'container') {
                    $output .= $this->processContainerRule($atPrelude, $body, null, $ruleLocation);
                } elseif ($this->isCustomAtRule($name)) {
                    $output .= $this->processCustomAtRule($prelude, $body, null, $ruleLocation);
                } else {
                    $rule = $this->buildUnknownRule($name, $atPrelude, $body, null, $ruleLocation);
                    $genericReplacement = $this->callAnyRuleVisitor(['type' => 'unknown', 'value' => $rule]);
                    if ($genericReplacement !== null) {
                        $output .= $this->emitReplacement($genericReplacement, null);
                        $cursor = $close + 1;
                        continue;
                    }

                    $replacement = $this->callUnknownRuleVisitor($rule);
                    if ($replacement === null) {
                        $exitReplacement = $this->applyUnknownRuleExit($rule, null);
                        $output .= $exitReplacement ?? $this->emitUnknownRule($rule, null);
                    } else {
                        $output .= $this->emitReplacement($replacement, null);
                    }
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
    private function processStatement(string $statement, ?array $parentSelectors, ?array $loc = null): string
    {
        if (!str_starts_with($statement, '@')) {
            return $statement . ';';
        }

        [$name, $prelude] = $this->parseAtPrelude($statement);
        if (!$this->isCustomAtRule($name)) {
            $rule = $this->buildUnknownRule($name, $prelude, null, $parentSelectors, $loc);
            $genericReplacement = $this->callAnyRuleVisitor(['type' => 'unknown', 'value' => $rule]);
            if ($genericReplacement !== null) {
                return $this->emitReplacement($genericReplacement, $parentSelectors);
            }

            $replacement = $this->callUnknownRuleVisitor($rule);

            if ($replacement === null) {
                return $this->applyUnknownRuleExit($rule, $parentSelectors) ?? $statement . ';';
            }

            return $this->emitReplacement($replacement, $parentSelectors);
        }

        $rule = $this->buildCustomRule($name, $prelude, null, $parentSelectors, true, $loc);
        $genericReplacement = $this->callAnyRuleVisitor(['type' => 'custom', 'value' => $rule]);
        if ($genericReplacement !== null) {
            return $this->emitReplacement($genericReplacement, $parentSelectors);
        }

        $replacement = $this->callRuleVisitor($rule);
        if ($replacement === null) {
            return $this->applyCustomRuleExit($rule, $parentSelectors) ?? $this->emitVisitedCustomRule($rule);
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
                        $ruleLocation = $this->sourceLocationForOffset($body, $this->skipWhitespace($body, $cursor));
                        $output .= $this->processStatement($statement, $selectors, $ruleLocation);
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
                        $output .= $this->processStatement($tail, $selectors, $this->sourceLocationForOffset($body, $this->skipWhitespace($body, $cursor)));
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
                $nestedLocation = $this->sourceLocationForOffset($body, $this->skipWhitespace($body, $cursor + strlen($declarationPart)));
                if ($name === 'media') {
                    $output .= $this->processMediaRule($atPrelude, $nestedBody, $selectors, $nestedLocation);
                } elseif ($name === 'supports') {
                    $output .= $this->processSupportsRule($atPrelude, $nestedBody, $selectors, $nestedLocation);
                } elseif ($name === 'container') {
                    $output .= $this->processContainerRule($atPrelude, $nestedBody, $selectors, $nestedLocation);
                } elseif ($this->isCustomAtRule($name)) {
                    $output .= $this->processCustomAtRule($nestedPrelude, $nestedBody, $selectors, $nestedLocation);
                } elseif (str_starts_with($nestedPrelude, '@nest ')) {
                    $nestedSelectors = $this->resolveNestedSelectors($selectors, substr($nestedPrelude, 6));
                    $output .= $this->processStyleBody($nestedBody, $nestedSelectors);
                } else {
                    $rule = $this->buildUnknownRule($name, $atPrelude, $nestedBody, $selectors, $nestedLocation);
                    $genericReplacement = $this->callAnyRuleVisitor(['type' => 'unknown', 'value' => $rule]);
                    if ($genericReplacement !== null) {
                        $output .= $this->emitReplacement($genericReplacement, $selectors);
                        $cursor = $close + 1;
                        continue;
                    }

                    $replacement = $this->callUnknownRuleVisitor($rule);
                    if ($replacement === null) {
                        $exitReplacement = $this->applyUnknownRuleExit($rule, $selectors);
                        $output .= $exitReplacement ?? $this->emitUnknownRule($rule, $selectors);
                    } else {
                        $output .= $this->emitReplacement($replacement, $selectors);
                    }
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
    private function processMediaRule(string $query, string $body, ?array $parentSelectors, ?array $loc = null): string
    {
        if ($this->ruleVisitor !== null) {
            $replacement = $this->callAnyRuleVisitor($this->buildMediaRule($query, $body, $parentSelectors, $loc));
            if ($replacement !== null) {
                return $this->emitReplacement($replacement, $parentSelectors);
            }
        }

        if ($this->mediaRuleVisitor !== null) {
            $rule = $this->buildMediaRule($query, $body, $parentSelectors, $loc);
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
        $exitReplacement = $this->applyMediaRuleExit($this->buildVisitedMediaRule($queryCss, $bodyCss, $parentSelectors, $loc), $parentSelectors);
        if ($exitReplacement !== null) {
            return $exitReplacement;
        }

        return '@media ' . $queryCss . '{' . $bodyCss . '}';
    }

    /**
     * @param list<string>|null $parentSelectors
     */
    private function processSupportsRule(string $condition, string $body, ?array $parentSelectors, ?array $loc = null): string
    {
        if ($this->ruleVisitor !== null) {
            $replacement = $this->callAnyRuleVisitor($this->buildSupportsRule($condition, $body, $parentSelectors, $loc));
            if ($replacement !== null) {
                return $this->emitReplacement($replacement, $parentSelectors);
            }
        }

        if ($this->supportsRuleVisitor !== null) {
            $rule = $this->buildSupportsRule($condition, $body, $parentSelectors, $loc);
            $replacement = ($this->supportsRuleVisitor)($rule, $this);
            if ($replacement !== null) {
                return $this->emitReplacement($replacement, $parentSelectors);
            }
        }

        $conditionCss = $this->supportsConditionVisitor !== null || $this->supportsConditionExitVisitor !== null
            ? $this->returnedSupportsConditionToCss($this->applySupportsConditionVisitors($this->parseSupportsConditionForVisitor($condition)))
            : $this->rewriteAtRulePreludeValue($condition);
        $bodyCss = $parentSelectors === null
            ? $this->processRuleList($body)
            : $this->processStyleBody($body, $parentSelectors);
        $exitReplacement = $this->applySupportsRuleExit($this->buildVisitedSupportsRule($conditionCss, $bodyCss, $parentSelectors, $loc), $parentSelectors);
        if ($exitReplacement !== null) {
            return $exitReplacement;
        }

        return '@supports ' . $conditionCss . '{' . $bodyCss . '}';
    }

    /**
     * @param list<string>|null $parentSelectors
     */
    private function processContainerRule(string $prelude, string $body, ?array $parentSelectors, ?array $loc = null): string
    {
        if ($this->ruleVisitor !== null) {
            $replacement = $this->callAnyRuleVisitor($this->buildContainerRule($prelude, $body, $parentSelectors, $loc));
            if ($replacement !== null) {
                return $this->emitReplacement($replacement, $parentSelectors);
            }
        }

        if ($this->containerRuleVisitor !== null) {
            $rule = $this->buildContainerRule($prelude, $body, $parentSelectors, $loc);
            $replacement = ($this->containerRuleVisitor)($rule, $this);
            if ($replacement !== null) {
                return $this->emitReplacement($replacement, $parentSelectors);
            }
        }

        $preludeCss = $this->minifyContainerPreludeForVisitor($prelude);
        $bodyCss = $parentSelectors === null
            ? $this->processRuleList($body)
            : $this->processStyleBody($body, $parentSelectors);
        $exitReplacement = $this->applyContainerRuleExit($this->buildVisitedContainerRule($preludeCss, $bodyCss, $parentSelectors, $loc), $parentSelectors);
        if ($exitReplacement !== null) {
            return $exitReplacement;
        }

        return '@container' . ($preludeCss === '' ? '' : ' ' . $preludeCss) . '{' . $bodyCss . '}';
    }

    /**
     * @param list<string>|null $parentSelectors
     */
    private function processCustomAtRule(string $prelude, string $body, ?array $parentSelectors, ?array $loc = null): string
    {
        [$name, $atPrelude] = $this->parseAtPrelude($prelude);
        $rule = $this->buildCustomRule($name, $atPrelude, $body, $parentSelectors, true, $loc);
        $genericReplacement = $this->callAnyRuleVisitor(['type' => 'custom', 'value' => $rule]);
        if ($genericReplacement !== null) {
            return $this->emitReplacement($genericReplacement, $parentSelectors);
        }

        $replacement = $this->callRuleVisitor($rule);
        if ($replacement === null) {
            $visitedRule = $this->processCustomRuleChildrenForExit($rule, $parentSelectors);

            return $this->applyCustomRuleExit($visitedRule, $parentSelectors) ?? $this->emitVisitedCustomRule($visitedRule);
        }

        return $this->emitReplacement($replacement, $parentSelectors);
    }

    /**
     * @param list<string>|null $parentSelectors
     * @return array{name:string, prelude:string, preludeAst:mixed, bodyType:string|null, body:string, bodyAst:mixed, bodyRules:list<array<string, mixed>>, declarations:list<array{property:string, value:string, important:bool}>, loc:array{source_index:int,line:int,column:int}, context:string, parentSelectors:list<string>}
     */
    private function buildCustomRule(string $name, string $prelude, ?string $body, ?array $parentSelectors, bool $visitPrelude = true, ?array $loc = null): array
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
        if ($preludeAst !== null && str_contains($preludeValue, '\\') && $this->customPreludeAstUsesDecodedIdentifiers($preludeAst)) {
            $preludeValue = $this->serializeVisitorValue($preludeAst);
        }
        if ($preludeAst !== null && $visitPrelude) {
            $visitedPrelude = $this->visitCustomPreludeValue($preludeAst);
            if ($visitedPrelude['changed']) {
                $preludeAst = $visitedPrelude['value'];
                $preludeValue = $this->serializeVisitorValue($preludeAst);
            }
        }
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
            'loc' => $loc ?? $this->defaultSourceLocation(),
            'context' => $parentSelectors === null ? 'rule-list' : 'style-block',
            'parentSelectors' => $parentSelectors ?? [],
        ];
    }

    /**
     * @param array<string, mixed> $rule
     * @param list<string>|null $parentSelectors
     * @return array<string, mixed>
     */
    private function processCustomRuleChildrenForExit(array $rule, ?array $parentSelectors): array
    {
        $bodyType = $rule['bodyType'] ?? null;
        if ($bodyType === null) {
            return $rule;
        }

        $body = (string) ($rule['body'] ?? '');
        if ($bodyType === 'declaration-list') {
            $entries = $this->processDeclarationEntries($this->declarationBlock->parseEntries($body));
            $rule['body'] = $this->emitDeclarationEntriesBody($entries);
            $rule['declarations'] = $entries;
            $rule['bodyRules'] = [];
            $rule['bodyAst'] = $this->customBodyAst('declaration-list', $entries, []);

            return $rule;
        }

        $bodyCss = $bodyType === 'style-block' && $parentSelectors !== null
            ? $this->processStyleBody($body, $parentSelectors)
            : $this->processRuleList($body);
        $bodyRules = $this->parseReturnedRuleList($bodyCss, null);

        $rule['body'] = $bodyCss;
        $rule['bodyRules'] = $bodyRules;
        $rule['declarations'] = [];
        $rule['bodyAst'] = $this->customBodyAst((string) $bodyType, [], $bodyRules);

        return $rule;
    }

    /**
     * @param list<string>|null $parentSelectors
     * @return array{name:string, prelude:string, preludeTokens:list<mixed>, block:list<mixed>|null, body:string, hasBlock:bool, loc:array{source_index:int,line:int,column:int}, context:string, parentSelectors:list<string>}
     */
    private function buildUnknownRule(string $name, string $prelude, ?string $body, ?array $parentSelectors, ?array $loc = null): array
    {
        $prelude = trim($prelude);

        return [
            'name' => $name,
            'prelude' => $prelude,
            'preludeTokens' => $this->parseUnknownPreludeTokens($prelude),
            'block' => $body === null ? null : $this->parseComponentValueList($body),
            'body' => $body ?? '',
            'hasBlock' => $body !== null,
            'loc' => $loc ?? $this->defaultSourceLocation(),
            'context' => $parentSelectors === null ? 'rule-list' : 'style-block',
            'parentSelectors' => $parentSelectors ?? [],
        ];
    }

    /**
     * @param list<string>|null $parentSelectors
     * @return array{type:string,value:array{loc:array{source_index:int,line:int,column:int},query:array<string, mixed>,rules:list<array<string, mixed>>},context:string,parentSelectors:list<string>}
     */
    private function buildMediaRule(string $query, string $body, ?array $parentSelectors, ?array $loc = null): array
    {
        return [
            'type' => 'media',
            'value' => [
                'loc' => $loc ?? $this->defaultSourceLocation(),
                'query' => $this->parseMediaQueryForVisitor($query),
                'rules' => $this->parseReturnedRuleList($body, $parentSelectors),
            ],
            'context' => $parentSelectors === null ? 'rule-list' : 'style-block',
            'parentSelectors' => $parentSelectors ?? [],
        ];
    }

    /**
     * @param list<string>|null $parentSelectors
     * @return array{type:string,value:array{loc:array{source_index:int,line:int,column:int},query:array<string, mixed>,rules:list<array<string, mixed>>},context:string,parentSelectors:list<string>}
     */
    private function buildVisitedMediaRule(string $query, string $body, ?array $parentSelectors, ?array $loc = null): array
    {
        return [
            'type' => 'media',
            'value' => [
                'loc' => $loc ?? $this->defaultSourceLocation(),
                'query' => $this->parseMediaQueryForVisitor($query),
                'rules' => $this->parseReturnedRuleList($body, null),
            ],
            'context' => $parentSelectors === null ? 'rule-list' : 'style-block',
            'parentSelectors' => $parentSelectors ?? [],
        ];
    }

    /**
     * @param list<string>|null $parentSelectors
     * @return array{type:string,value:array{loc:array{source_index:int,line:int,column:int},condition:array<string, mixed>,rules:list<array<string, mixed>>},context:string,parentSelectors:list<string>}
     */
    private function buildSupportsRule(string $condition, string $body, ?array $parentSelectors, ?array $loc = null): array
    {
        return [
            'type' => 'supports',
            'value' => [
                'loc' => $loc ?? $this->defaultSourceLocation(),
                'condition' => $this->parseSupportsConditionForVisitor($condition),
                'rules' => $this->parseReturnedRuleList($body, $parentSelectors),
            ],
            'context' => $parentSelectors === null ? 'rule-list' : 'style-block',
            'parentSelectors' => $parentSelectors ?? [],
        ];
    }

    /**
     * @param list<string>|null $parentSelectors
     * @return array{type:string,value:array{loc:array{source_index:int,line:int,column:int},condition:array<string, mixed>,rules:list<array<string, mixed>>},context:string,parentSelectors:list<string>}
     */
    private function buildVisitedSupportsRule(string $condition, string $body, ?array $parentSelectors, ?array $loc = null): array
    {
        return [
            'type' => 'supports',
            'value' => [
                'loc' => $loc ?? $this->defaultSourceLocation(),
                'condition' => $this->parseSupportsConditionForVisitor($condition),
                'rules' => $this->parseReturnedRuleList($body, null),
            ],
            'context' => $parentSelectors === null ? 'rule-list' : 'style-block',
            'parentSelectors' => $parentSelectors ?? [],
        ];
    }

    /**
     * @param list<string>|null $parentSelectors
     * @return array{type:string,value:array<string, mixed>,context:string,parentSelectors:list<string>}
     */
    private function buildContainerRule(string $prelude, string $body, ?array $parentSelectors, ?array $loc = null): array
    {
        return [
            'type' => 'container',
            'value' => array_replace(
                ['loc' => $loc ?? $this->defaultSourceLocation()],
                $this->parseContainerPreludeForVisitor($prelude),
                ['rules' => $this->parseReturnedRuleList($body, $parentSelectors)]
            ),
            'context' => $parentSelectors === null ? 'rule-list' : 'style-block',
            'parentSelectors' => $parentSelectors ?? [],
        ];
    }

    /**
     * @param list<string>|null $parentSelectors
     * @return array{type:string,value:array<string, mixed>,context:string,parentSelectors:list<string>}
     */
    private function buildVisitedContainerRule(string $prelude, string $body, ?array $parentSelectors, ?array $loc = null): array
    {
        return [
            'type' => 'container',
            'value' => array_replace(
                ['loc' => $loc ?? $this->defaultSourceLocation()],
                $this->parseContainerPreludeForVisitor($prelude),
                ['rules' => $this->parseReturnedRuleList($body, null)]
            ),
            'context' => $parentSelectors === null ? 'rule-list' : 'style-block',
            'parentSelectors' => $parentSelectors ?? [],
        ];
    }

    /**
     * @return array{prelude:string,name:string|null,condition:array<string, mixed>|null}
     */
    private function parseContainerPreludeForVisitor(string $prelude): array
    {
        $prelude = trim($prelude);
        [$name, $conditionCss] = $this->splitContainerPreludeForVisitor($prelude);

        return [
            'prelude' => $this->minifyContainerPreludeForVisitor($prelude),
            'name' => $name,
            'condition' => $conditionCss === '' ? null : $this->parseContainerConditionForVisitor($conditionCss),
        ];
    }

    /**
     * @return array{0:string|null,1:string}
     */
    private function splitContainerPreludeForVisitor(string $prelude): array
    {
        $prelude = trim($prelude);
        if ($prelude === '') {
            return [null, ''];
        }
        if ($prelude[0] === '(' || preg_match('/^(?:not\b|style\s*\(|scroll-state\s*\()/i', $prelude) === 1) {
            return [null, $prelude];
        }

        $parts = preg_split('/\s+/', $prelude, 2);
        $name = $this->decodeCssIdentifierToken((string) ($parts[0] ?? ''));
        if ($name === null || str_contains($name, '(') || str_contains($name, ')')) {
            return [null, $prelude];
        }

        return [$name, trim((string) ($parts[1] ?? ''))];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseContainerConditionForVisitor(string $condition): array
    {
        $condition = $this->minifyContainerPreludeForVisitor($condition);
        $parsed = $this->parseMediaConditionForVisitor($condition);

        return $parsed ?? ['raw' => $condition];
    }

    private function minifyContainerPreludeForVisitor(string $prelude): string
    {
        $prelude = trim($prelude);
        if ($prelude === '') {
            return '';
        }

        try {
            $minified = $this->minifier->minify('@container ' . $prelude . '{}');
            if (preg_match('/^@container\s*([^{]*)\{/', $minified, $matches) === 1) {
                return trim($matches[1]);
            }
        } catch (\InvalidArgumentException) {
        }

        return $this->rewriteAtRulePreludeValue($prelude);
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
                    'value' => $this->parseMediaFeatureValueForVisitor($matches[2], strtolower($matches[1])),
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
                    'value' => $this->parseMediaFeatureValueForVisitor($matches[3], strtolower($matches[1])),
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

    private function parseMediaFeatureValueForVisitor(string $value, ?string $featureName = null): mixed
    {
        $value = trim($value);
        if (
            ($featureName !== null && str_ends_with($featureName, 'aspect-ratio'))
            || str_contains($value, '/')
        ) {
            $ratio = $this->tryCustomRatioPreludeAst($value);
            if ($ratio['matched']) {
                return $ratio['value'];
            }
        }
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

        $this->customPreludeAst($prelude, $grammar);

        return $prelude;
    }

    private function customPreludeAst(string $prelude, ?string $grammar): mixed
    {
        if ($grammar === null) {
            return null;
        }

        $parsed = $this->tryCustomSyntaxAst($prelude, $grammar);
        if ($parsed['matched']) {
            return $parsed['value'];
        }

        throw new \InvalidArgumentException("Invalid custom at-rule prelude for {$grammar}: {$prelude}");
    }

    private function customPreludeAstUsesDecodedIdentifiers(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        $type = $value['type'] ?? null;
        if (in_array($type, ['custom-ident', 'dashed-ident', 'literal'], true)) {
            return true;
        }

        if ($type === 'repeated') {
            foreach (($value['value']['components'] ?? []) as $component) {
                if ($this->customPreludeAstUsesDecodedIdentifiers($component)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array{matched:bool,value:mixed}
     */
    private function tryCustomSyntaxAst(string $prelude, string $grammar): array
    {
        $grammar = trim($grammar);
        if ($grammar === '*') {
            return [
                'matched' => true,
                'value' => [
                    'type' => 'token-list',
                    'value' => $this->parseComponentValueList($prelude, true),
                ],
            ];
        }

        $componentGrammars = $this->splitTopLevelPreservingEmpty($grammar, '|');
        if (in_array('', $componentGrammars, true)) {
            return ['matched' => false, 'value' => null];
        }

        foreach ($componentGrammars as $componentGrammar) {
            $parsed = $this->tryCustomSyntaxComponentAst($prelude, $componentGrammar);
            if ($parsed['matched']) {
                return $parsed;
            }
        }

        return ['matched' => false, 'value' => null];
    }

    /**
     * @return array{matched:bool,value:mixed}
     */
    private function tryCustomLengthPercentagePreludeAst(string $prelude): array
    {
        $length = $this->tryCustomLengthPreludeAst($prelude);
        if ($length['matched']) {
            return [
                'matched' => true,
                'value' => [
                    'type' => 'length-percentage',
                    'value' => [
                        'type' => 'dimension',
                        'value' => $length['value']['value']['value'],
                    ],
                ],
            ];
        }

        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))%$/', $prelude, $matches) === 1) {
            return [
                'matched' => true,
                'value' => [
                    'type' => 'length-percentage',
                    'value' => [
                        'type' => 'percentage',
                        'value' => (float) $matches[1] / 100,
                    ],
                ],
            ];
        }

        if (preg_match('/^calc\\(/i', $prelude) === 1) {
            $open = stripos($prelude, '(');
            if ($open !== false && $this->findMatchingParen($prelude, $open) === strlen($prelude) - 1) {
                return [
                    'matched' => true,
                    'value' => [
                        'type' => 'length-percentage',
                        'value' => [
                            'type' => 'calc',
                            'value' => [
                                'type' => 'raw',
                                'value' => trim(substr($prelude, $open + 1, -1)),
                            ],
                        ],
                    ],
                ];
            }
        }

        return ['matched' => false, 'value' => null];
    }

    /**
     * @return array{unit:string,value:float}|null
     */
    private function tryEvaluateSameUnitLengthCalc(string $prelude): ?array
    {
        if (preg_match('/^calc\\((.+)\\)$/i', trim($prelude), $matches) !== 1) {
            return null;
        }

        $expression = trim($matches[1]);
        if (preg_match('/^([+-]?(?:\\d+|\\d*\\.\\d+))([a-zA-Z]+)\\s*([+-])\\s*([+-]?(?:\\d+|\\d*\\.\\d+))\\2$/', $expression, $parts) !== 1) {
            return null;
        }

        $left = (float) $parts[1];
        $right = (float) $parts[4];
        $value = $parts[3] === '-' ? $left - $right : $left + $right;

        return [
            'unit' => strtolower($parts[2]),
            'value' => $value,
        ];
    }

    /**
     * @return array{matched:bool,value:mixed}
     */
    private function tryCustomSyntaxComponentAst(string $prelude, string $grammar): array
    {
        $grammar = trim($grammar);
        if (preg_match('/^<([a-z-]+)>([+#]?)$/i', $grammar, $matches) === 1) {
            $type = strtolower($matches[1]);
            $multiplier = $matches[2] ?? '';
            if ($type === 'transform-list' && $multiplier !== '') {
                return ['matched' => false, 'value' => null];
            }
            if ($multiplier === '+') {
                return $this->tryRepeatedCustomSyntaxAst($prelude, $type, 'space');
            }
            if ($multiplier === '#') {
                return $this->tryRepeatedCustomSyntaxAst($prelude, $type, 'comma');
            }

            return $this->tryCustomComponentValueAst($prelude, $type);
        }

        if (preg_match('/^((?:[_a-zA-Z\x{0080}-\x{10FFFF}])[-_a-zA-Z0-9\x{0080}-\x{10FFFF}]*)([+#]?)$/u', $grammar, $matches) === 1) {
            $literal = $matches[1];
            $multiplier = $matches[2] ?? '';
            if ($multiplier === '+') {
                return $this->tryRepeatedLiteralSyntaxAst($prelude, $literal, 'space');
            }
            if ($multiplier === '#') {
                return $this->tryRepeatedLiteralSyntaxAst($prelude, $literal, 'comma');
            }
            if ($this->decodeCssIdentifierToken(trim($prelude)) !== $literal) {
                return ['matched' => false, 'value' => null];
            }

            return [
                'matched' => true,
                'value' => [
                    'type' => 'literal',
                    'value' => $literal,
                ],
            ];
        }

        return ['matched' => false, 'value' => null];
    }

    /**
     * @return array{matched:bool,value:mixed}
     */
    private function tryRepeatedCustomSyntaxAst(string $prelude, string $type, string $multiplier): array
    {
        $parts = $multiplier === 'comma'
            ? $this->splitTopLevelPreservingEmpty($prelude, ',')
            : $this->splitWhitespaceTokens($prelude);
        if ($parts === []) {
            return ['matched' => false, 'value' => null];
        }

        $components = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                return ['matched' => false, 'value' => null];
            }

            $parsed = $this->tryCustomComponentValueAst($part, $type);
            if (!$parsed['matched']) {
                return ['matched' => false, 'value' => null];
            }
            $components[] = $parsed['value'];
        }

        return [
            'matched' => true,
            'value' => [
                'type' => 'repeated',
                'value' => [
                    'components' => $components,
                    'multiplier' => ['type' => $multiplier],
                ],
            ],
        ];
    }

    /**
     * @return array{matched:bool,value:mixed}
     */
    private function tryRepeatedLiteralSyntaxAst(string $prelude, string $literal, string $multiplier): array
    {
        $parts = $multiplier === 'comma'
            ? $this->splitTopLevelPreservingEmpty($prelude, ',')
            : $this->splitWhitespaceTokens($prelude);
        if ($parts === []) {
            return ['matched' => false, 'value' => null];
        }

        $components = [];
        foreach ($parts as $part) {
            if ($this->decodeCssIdentifierToken(trim($part)) !== $literal) {
                return ['matched' => false, 'value' => null];
            }
            $components[] = [
                'type' => 'literal',
                'value' => $literal,
            ];
        }

        return [
            'matched' => true,
            'value' => [
                'type' => 'repeated',
                'value' => [
                    'components' => $components,
                    'multiplier' => ['type' => $multiplier],
                ],
            ],
        ];
    }

    /**
     * @return array{matched:bool,value:mixed}
     */
    private function tryCustomComponentValueAst(string $value, string $type): array
    {
        $value = trim($value);
        if ($value === '') {
            return ['matched' => false, 'value' => null];
        }
        $identifier = $this->decodeCssIdentifierToken($value);

        return match ($type) {
            'custom-ident' => $identifier !== null && $this->isCustomIdentToken($identifier)
                ? ['matched' => true, 'value' => ['type' => 'custom-ident', 'value' => $identifier]]
                : ['matched' => false, 'value' => null],
            'dashed-ident' => $identifier !== null && str_starts_with($identifier, '--') && $this->isCssIdentifierToken($identifier)
                ? ['matched' => true, 'value' => ['type' => 'dashed-ident', 'value' => $identifier]]
                : ['matched' => false, 'value' => null],
            'length' => $this->tryCustomLengthPreludeAst($value),
            'length-percentage' => $this->tryCustomLengthPercentagePreludeAst($value),
            'number' => preg_match('/^[+-]?(?:\d+|\d*\.\d+)$/', $value) === 1
                ? ['matched' => true, 'value' => ['type' => 'number', 'value' => (float) $value]]
                : ['matched' => false, 'value' => null],
            'integer' => preg_match('/^[+-]?\d+$/', $value) === 1
                ? ['matched' => true, 'value' => ['type' => 'integer', 'value' => (int) $value]]
                : ['matched' => false, 'value' => null],
            'percentage' => preg_match('/^([+-]?(?:\d+|\d*\.\d+))%$/', $value, $matches) === 1
                ? ['matched' => true, 'value' => ['type' => 'percentage', 'value' => (float) $matches[1] / 100]]
                : ['matched' => false, 'value' => null],
            'string' => $this->tryCustomStringPreludeAst($value),
            'color' => ($color = $this->parseCssColorValue($value)) !== null
                ? ['matched' => true, 'value' => ['type' => 'color', 'value' => $color]]
                : ['matched' => false, 'value' => null],
            'url' => $this->tryCustomUrlPreludeAst($value),
            'image' => $this->tryCustomImagePreludeAst($value),
            'angle' => $this->tryCustomAnglePreludeAst($value),
            'time' => $this->tryCustomTimePreludeAst($value),
            'ratio' => $this->tryCustomRatioPreludeAst($value),
            'resolution' => $this->tryCustomResolutionPreludeAst($value),
            'transform-function' => $this->tryCustomTransformFunctionPreludeAst($value),
            'transform-list' => $this->tryCustomTransformListPreludeAst($value),
            default => ['matched' => false, 'value' => null],
        };
    }

    /**
     * @return array{matched:bool,value:mixed}
     */
    private function tryCustomLengthPreludeAst(string $prelude): array
    {
        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))([a-zA-Z]+)$/', $prelude, $matches) === 1) {
            return [
                'matched' => true,
                'value' => [
                    'type' => 'length',
                    'value' => [
                        'type' => 'value',
                        'value' => [
                            'unit' => strtolower($matches[2]),
                            'value' => (float) $matches[1],
                        ],
                    ],
                ],
            ];
        }

        if (($length = $this->tryEvaluateSameUnitLengthCalc($prelude)) !== null) {
            return [
                'matched' => true,
                'value' => [
                    'type' => 'length',
                    'value' => [
                        'type' => 'value',
                        'value' => $length,
                    ],
                ],
            ];
        }

        if ($prelude === '0') {
            return [
                'matched' => true,
                'value' => [
                    'type' => 'length',
                    'value' => [
                        'type' => 'value',
                        'value' => [
                            'unit' => 'px',
                            'value' => 0.0,
                        ],
                    ],
                ],
            ];
        }

        return ['matched' => false, 'value' => null];
    }

    /**
     * @return array{matched:bool,value:mixed}
     */
    private function tryCustomStringPreludeAst(string $prelude): array
    {
        if (
            strlen($prelude) >= 2
            && (($prelude[0] === '"' && $prelude[strlen($prelude) - 1] === '"') || ($prelude[0] === "'" && $prelude[strlen($prelude) - 1] === "'"))
        ) {
            return [
                'matched' => true,
                'value' => [
                    'type' => 'string',
                    'value' => stripcslashes(substr($prelude, 1, -1)),
                ],
            ];
        }

        return ['matched' => false, 'value' => null];
    }

    /**
     * @return array{matched:bool,value:mixed}
     */
    private function tryCustomUrlPreludeAst(string $prelude): array
    {
        if (preg_match('/^url\\((.*)\\)$/i', $prelude, $matches) !== 1) {
            return ['matched' => false, 'value' => null];
        }

        return [
            'matched' => true,
            'value' => [
                'type' => 'url',
                'value' => $this->parseUrlValue($matches[1], $prelude),
            ],
        ];
    }

    /**
     * @return array{matched:bool,value:mixed}
     */
    private function tryCustomImagePreludeAst(string $prelude): array
    {
        if (strtolower($prelude) === 'none') {
            return [
                'matched' => true,
                'value' => [
                    'type' => 'image',
                    'value' => ['type' => 'none'],
                ],
            ];
        }

        $url = $this->tryCustomUrlPreludeAst($prelude);
        if ($url['matched']) {
            return [
                'matched' => true,
                'value' => [
                    'type' => 'image',
                    'value' => [
                        'type' => 'url',
                        'value' => $url['value']['value'],
                    ],
                ],
            ];
        }

        if (preg_match('/^(?:repeating-)?(?:linear|radial|conic)-gradient\\(/i', $prelude) === 1) {
            $open = strpos($prelude, '(');
            if ($open !== false && $this->findMatchingParen($prelude, $open) === strlen($prelude) - 1) {
                return [
                    'matched' => true,
                    'value' => [
                        'type' => 'image',
                        'value' => [
                            'type' => 'gradient',
                            'value' => ['raw' => $prelude],
                        ],
                    ],
                ];
            }
        }

        return ['matched' => false, 'value' => null];
    }

    /**
     * @return array{matched:bool,value:mixed}
     */
    private function tryCustomAnglePreludeAst(string $prelude): array
    {
        if (preg_match('/^([+-]?(?:\\d+|\\d*\\.\\d+))(deg|rad|grad|turn)$/i', $prelude, $matches) !== 1) {
            return ['matched' => false, 'value' => null];
        }

        return [
            'matched' => true,
            'value' => [
                'type' => 'angle',
                'value' => [
                    'type' => strtolower($matches[2]),
                    'value' => (float) $matches[1],
                ],
            ],
        ];
    }

    /**
     * @return array{matched:bool,value:mixed}
     */
    private function tryCustomTimePreludeAst(string $prelude): array
    {
        if (preg_match('/^([+-]?(?:\\d+|\\d*\\.\\d+))(ms|s)$/i', $prelude, $matches) !== 1) {
            return ['matched' => false, 'value' => null];
        }

        return [
            'matched' => true,
            'value' => [
                'type' => 'time',
                'value' => [
                    'type' => strtolower($matches[2]) === 'ms' ? 'milliseconds' : 'seconds',
                    'value' => (float) $matches[1],
                ],
            ],
        ];
    }

    /**
     * @return array{matched:bool,value:mixed}
     */
    private function tryCustomRatioPreludeAst(string $prelude): array
    {
        if (preg_match('/^([+-]?(?:\\d+|\\d*\\.\\d+))(?:\\s*\\/\\s*([+-]?(?:\\d+|\\d*\\.\\d+)))?$/', trim($prelude), $matches) !== 1) {
            return ['matched' => false, 'value' => null];
        }

        return [
            'matched' => true,
            'value' => [
                'type' => 'ratio',
                'value' => [
                    (float) $matches[1],
                    isset($matches[2]) && $matches[2] !== '' ? (float) $matches[2] : 1.0,
                ],
            ],
        ];
    }

    /**
     * @return array{matched:bool,value:mixed}
     */
    private function tryCustomResolutionPreludeAst(string $prelude): array
    {
        if (preg_match('/^([+-]?(?:\\d+|\\d*\\.\\d+))(dpi|dpcm|dppx)$/i', $prelude, $matches) !== 1) {
            return ['matched' => false, 'value' => null];
        }

        return [
            'matched' => true,
            'value' => [
                'type' => 'resolution',
                'value' => [
                    'type' => strtolower($matches[2]),
                    'value' => (float) $matches[1],
                ],
            ],
        ];
    }

    /**
     * @return array{matched:bool,value:mixed}
     */
    private function tryCustomTransformFunctionPreludeAst(string $prelude): array
    {
        $transforms = $this->parseTransformFunctionListForVisitor($prelude);
        if (count($transforms) !== 1 || ($transforms[0]['type'] ?? null) === 'raw') {
            return ['matched' => false, 'value' => null];
        }

        return [
            'matched' => true,
            'value' => [
                'type' => 'transform-function',
                'value' => $transforms[0],
            ],
        ];
    }

    /**
     * @return array{matched:bool,value:mixed}
     */
    private function tryCustomTransformListPreludeAst(string $prelude): array
    {
        $transforms = $this->parseTransformFunctionListForVisitor($prelude);
        if ($transforms === []) {
            return ['matched' => false, 'value' => null];
        }

        foreach ($transforms as $transform) {
            if (($transform['type'] ?? null) === 'raw') {
                return ['matched' => false, 'value' => null];
            }
        }

        return [
            'matched' => true,
            'value' => [
                'type' => 'transform-list',
                'value' => $transforms,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function splitTopLevelPreservingEmpty(string $value, string $delimiter): array
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

        return array_map('trim', $parts);
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
    private function callRuleExitVisitor(array $rule): mixed
    {
        $visitor = $this->ruleExitVisitors[$rule['name']] ?? $this->genericRuleExitVisitor;
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
     * @param array{name:string} $rule
     */
    private function callUnknownRuleExitVisitor(array $rule): mixed
    {
        $visitor = $this->unknownRuleExitVisitors[$rule['name']] ?? $this->genericUnknownRuleExitVisitor;
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
     * @param array<string, mixed> $rule
     */
    private function callAnyRuleExitVisitor(array $rule): mixed
    {
        if ($this->ruleExitVisitor === null) {
            return null;
        }

        return ($this->ruleExitVisitor)($rule, $this);
    }

    /**
     * @param array<string, mixed> $rule
     * @param list<string>|null $parentSelectors
     */
    private function applyCustomRuleExit(array $rule, ?array $parentSelectors): ?string
    {
        $genericReplacement = $this->callAnyRuleExitVisitor(['type' => 'custom', 'value' => $rule]);
        if ($genericReplacement !== null) {
            return $this->emitReplacementFromRuleExit($genericReplacement, $parentSelectors);
        }

        $replacement = $this->callRuleExitVisitor($rule);

        return $replacement === null ? null : $this->emitReplacementFromRuleExit($replacement, $parentSelectors);
    }

    /**
     * @param array<string, mixed> $rule
     * @param list<string>|null $parentSelectors
     */
    private function applyUnknownRuleExit(array $rule, ?array $parentSelectors): ?string
    {
        $genericReplacement = $this->callAnyRuleExitVisitor(['type' => 'unknown', 'value' => $rule]);
        if ($genericReplacement !== null) {
            return $this->emitReplacementFromRuleExit($genericReplacement, $parentSelectors);
        }

        $replacement = $this->callUnknownRuleExitVisitor($rule);

        return $replacement === null ? null : $this->emitReplacementFromRuleExit($replacement, $parentSelectors);
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function applyStyleRuleExit(array $rule): ?string
    {
        $genericReplacement = $this->callAnyRuleExitVisitor(array_replace($rule, ['type' => 'style']));
        if ($genericReplacement !== null) {
            return $this->emitStyleRuleReplacementFromRuleExit($genericReplacement, $rule);
        }

        if ($this->styleRuleExitVisitor === null) {
            return null;
        }

        $replacement = ($this->styleRuleExitVisitor)($rule, $this);

        return $replacement === null ? null : $this->emitStyleRuleReplacementFromRuleExit($replacement, $rule);
    }

    /**
     * @param array<string, mixed> $rule
     * @param list<string>|null $parentSelectors
     */
    private function applyMediaRuleExit(array $rule, ?array $parentSelectors): ?string
    {
        $genericReplacement = $this->callAnyRuleExitVisitor($rule);
        if ($genericReplacement !== null) {
            return $this->emitReplacementFromRuleExit($genericReplacement, $parentSelectors);
        }

        if ($this->mediaRuleExitVisitor === null) {
            return null;
        }

        $replacement = ($this->mediaRuleExitVisitor)($rule, $this);

        return $replacement === null ? null : $this->emitReplacementFromRuleExit($replacement, $parentSelectors);
    }

    /**
     * @param array<string, mixed> $rule
     * @param list<string>|null $parentSelectors
     */
    private function applySupportsRuleExit(array $rule, ?array $parentSelectors): ?string
    {
        $genericReplacement = $this->callAnyRuleExitVisitor($rule);
        if ($genericReplacement !== null) {
            return $this->emitReplacementFromRuleExit($genericReplacement, $parentSelectors);
        }

        if ($this->supportsRuleExitVisitor === null) {
            return null;
        }

        $replacement = ($this->supportsRuleExitVisitor)($rule, $this);

        return $replacement === null ? null : $this->emitReplacementFromRuleExit($replacement, $parentSelectors);
    }

    /**
     * @param array<string, mixed> $rule
     * @param list<string>|null $parentSelectors
     */
    private function applyContainerRuleExit(array $rule, ?array $parentSelectors): ?string
    {
        $genericReplacement = $this->callAnyRuleExitVisitor($rule);
        if ($genericReplacement !== null) {
            return $this->emitReplacementFromRuleExit($genericReplacement, $parentSelectors);
        }

        if ($this->containerRuleExitVisitor === null) {
            return null;
        }

        $replacement = ($this->containerRuleExitVisitor)($rule, $this);

        return $replacement === null ? null : $this->emitReplacementFromRuleExit($replacement, $parentSelectors);
    }

    /**
     * @param list<string>|null $parentSelectors
     */
    private function emitReplacementFromRuleExit(mixed $replacement, ?array $parentSelectors): string
    {
        $previous = $this->suppressReturnedRuleExitVisitors;
        $this->suppressReturnedRuleExitVisitors = true;
        try {
            return $this->emitReplacement($replacement, $parentSelectors);
        } finally {
            $this->suppressReturnedRuleExitVisitors = $previous;
        }
    }

    /**
     * @param array<string, mixed> $fallbackRule
     */
    private function emitStyleRuleReplacementFromRuleExit(mixed $replacement, array $fallbackRule): string
    {
        $previous = $this->suppressReturnedRuleExitVisitors;
        $this->suppressReturnedRuleExitVisitors = true;
        try {
            return $this->emitStyleRuleReplacement($replacement, $fallbackRule);
        } finally {
            $this->suppressReturnedRuleExitVisitors = $previous;
        }
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
        if (($replacement['type'] ?? null) === 'container' && isset($replacement['value']) && is_array($replacement['value'])) {
            return $this->emitReturnedContainerRule($replacement['value'], $parentSelectors);
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
        if ($kind === 'container') {
            $prelude = (string) ($replacement['prelude'] ?? '');
            $body = $replacement['body'] ?? '';
            $bodyCss = is_array($body)
                ? $this->emitReplacement($body, $parentSelectors)
                : (string) $body;

            return '@container' . (trim($prelude) === '' ? '' : ' ' . trim($prelude)) . '{' . $bodyCss . '}';
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

        $visitedRule = $this->processCustomRuleChildrenForExit($rule, $parentSelectors);

        if (!$this->suppressReturnedRuleExitVisitors) {
            $exitReplacement = $this->applyCustomRuleExit($visitedRule, $parentSelectors);
            if ($exitReplacement !== null) {
                return $exitReplacement;
            }
        }

        return $this->emitVisitedCustomRule($visitedRule);
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function emitVisitedCustomRule(array $rule): string
    {
        $name = (string) ($rule['name'] ?? '');
        if ($name === '') {
            throw new \InvalidArgumentException('Custom at-rule is missing a name');
        }

        $prelude = $this->serializeAtRulePreludeValue($rule['prelude'] ?? '');
        $head = '@' . $name . ($prelude === '' ? '' : ' ' . $prelude);
        if (($rule['bodyType'] ?? null) === null) {
            return $head . ';';
        }

        return $head . '{' . (string) ($rule['body'] ?? '') . '}';
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
            $body .= $this->emitReturnedChildRule($rule, $parentSelectors);
        }

        if (!$this->suppressReturnedRuleExitVisitors) {
            $exitReplacement = $this->applyMediaRuleExit($this->buildVisitedMediaRule($query, $body, $parentSelectors), $parentSelectors);
            if ($exitReplacement !== null) {
                return $exitReplacement;
            }
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
            $body .= $this->emitReturnedChildRule($rule, $parentSelectors);
        }

        if (!$this->suppressReturnedRuleExitVisitors) {
            $exitReplacement = $this->applySupportsRuleExit($this->buildVisitedSupportsRule($condition, $body, $parentSelectors), $parentSelectors);
            if ($exitReplacement !== null) {
                return $exitReplacement;
            }
        }

        return '@supports ' . $condition . '{' . $body . '}';
    }

    /**
     * @param array<string, mixed> $value
     * @param list<string>|null $parentSelectors
     */
    private function emitReturnedContainerRule(array $value, ?array $parentSelectors): string
    {
        $prelude = $this->returnedContainerPreludeToCss($value);
        $rules = $value['rules'] ?? [];
        if (!is_array($rules)) {
            $rules = [];
        }

        $body = '';
        foreach ($rules as $rule) {
            $body .= $this->emitReturnedChildRule($rule, $parentSelectors);
        }

        if (!$this->suppressReturnedRuleExitVisitors) {
            $exitReplacement = $this->applyContainerRuleExit($this->buildVisitedContainerRule($prelude, $body, $parentSelectors), $parentSelectors);
            if ($exitReplacement !== null) {
                return $exitReplacement;
            }
        }

        return '@container' . ($prelude === '' ? '' : ' ' . $prelude) . '{' . $body . '}';
    }

    /**
     * @param list<string>|null $parentSelectors
     */
    private function emitReturnedChildRule(mixed $rule, ?array $parentSelectors): string
    {
        if (!is_array($rule)) {
            return '';
        }
        if (($rule['type'] ?? null) === 'style' && isset($rule['value']) && is_array($rule['value'])) {
            return $this->emitReturnedStyleRule($rule['value'], true);
        }

        return $this->emitReplacement($rule, $parentSelectors);
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

    /**
     * @param array<string, mixed> $value
     */
    private function returnedContainerPreludeToCss(array $value): string
    {
        $name = isset($value['name']) && is_string($value['name']) ? trim($value['name']) : '';
        $conditionAst = $value['condition'] ?? null;
        $condition = is_array($conditionAst) ? $this->returnedContainerConditionToCss($conditionAst) : '';
        $prelude = trim($name . ($condition === '' ? '' : ' ' . $condition));
        if ($prelude === '' && isset($value['prelude']) && is_string($value['prelude'])) {
            $prelude = $value['prelude'];
        }

        return $this->minifyContainerPreludeForVisitor($prelude);
    }

    /**
     * @param array<string, mixed> $condition
     */
    private function returnedContainerConditionToCss(array $condition): string
    {
        if (($condition['type'] ?? null) === 'unknown') {
            return trim((string) ($condition['value'] ?? $condition['raw'] ?? ''));
        }

        return $this->returnedMediaConditionToCss($condition);
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
            if (in_array(($value['type'] ?? null), ['length', 'dimension', 'rgb', 'token', 'raw', 'var', 'env', 'function', 'ident', 'ratio'], true)) {
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
    private function emitReturnedStyleRule(array $value, bool $visitDeclarations = false): string
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

        if ($visitDeclarations) {
            $selectors = $this->applySelectorVisitorToSelectorList($selectors);
            if ($selectors === []) {
                return '';
            }

            $entries = $this->processDeclarationEntries($this->declarationBlock->parseEntries($body));
            if ($entries === []) {
                return '';
            }

            return implode(',', $selectors) . '{' . $this->emitDeclarationEntriesBody($entries) . '}';
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

    private function decodeCssIdentifierToken(string $token): ?string
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        if (!str_contains($token, '\\')) {
            return $token;
        }

        $decoded = $this->decodeCssEscapes($token);
        if ($decoded === '' || preg_match('/\s/', $decoded) === 1) {
            return null;
        }

        return $decoded;
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
            } elseif ($type === 'pseudo-element') {
                $css .= $this->serializePseudoElementComponent($component);
            } elseif ($type === 'combinator') {
                $css = rtrim($css);
                $css .= $this->serializeSelectorCombinator((string) ($component['value'] ?? 'descendant'));
            } elseif ($type === 'attribute') {
                $css .= $this->serializeAttributeSelectorComponent($component);
            } elseif ($type === 'nesting') {
                $css .= '&';
            }
        }

        return trim($css);
    }

    /**
     * @param array<string, mixed> $component
     */
    private function serializeAttributeSelectorComponent(array $component): string
    {
        $css = '[' . $this->serializeAttributeSelectorNamespace($component['namespace'] ?? null) . (string) ($component['name'] ?? '');
        $operation = $component['operation'] ?? null;
        if (is_array($operation) && isset($operation['operator'], $operation['value'])) {
            $operator = $this->attributeSelectorOperatorToCss((string) $operation['operator']);
            $css .= $operator . $this->serializeAttributeSelectorValue((string) $operation['value']);
            $caseSensitivity = $this->attributeSelectorCaseSensitivityToCss($operation['caseSensitivity'] ?? null);
            if ($caseSensitivity !== '') {
                $css .= ' ' . $caseSensitivity;
            }
        }

        return $css . ']';
    }

    private function serializeAttributeSelectorNamespace(mixed $namespace): string
    {
        if (!is_array($namespace)) {
            return '';
        }

        return match ($namespace['kind'] ?? null) {
            'any' => '*|',
            'none' => '|',
            'named' => $this->escapeSelectorIdentifier((string) ($namespace['prefix'] ?? '')) . '|',
            default => '',
        };
    }

    private function attributeSelectorOperatorToCss(string $operator): string
    {
        return match ($operator) {
            'equal' => '=',
            'includes' => '~=',
            'dash-match' => '|=',
            'prefix' => '^=',
            'substring' => '*=',
            'suffix' => '$=',
            default => $operator,
        };
    }

    private function serializeAttributeSelectorValue(string $value): string
    {
        if ($this->isCssIdentifierToken($value)) {
            return $this->escapeSelectorIdentifier($value);
        }

        if ($value !== '' && preg_match('/^[^\s"\'\]\[]+$/u', $value) === 1) {
            return $this->escapeSelectorIdentifier($value);
        }

        return '"' . addcslashes($value, "\\\"") . '"';
    }

    private function attributeSelectorCaseSensitivityToCss(mixed $caseSensitivity): string
    {
        return match ($caseSensitivity) {
            'ascii-case-insensitive', 'i', 'I' => 'i',
            'explicit-case-sensitive', 's', 'S' => 's',
            default => '',
        };
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

        $prelude = array_key_exists('prelude', $rule)
            ? $this->serializeAtRulePreludeValue($rule['prelude'])
            : $this->serializeAtRulePreludeValue(
                '',
                is_string($rule['preludeText'] ?? null) ? $rule['preludeText'] : ''
            );
        $prelude = trim($this->rewriteAtRulePreludeValue($prelude));
        if ($this->isKeyframesAtRule($name)) {
            $prelude = $this->rewriteKeyframesPrelude($prelude);
        }
        $head = '@' . $name . ($prelude === '' ? '' : ' ' . $prelude);
        if (empty($rule['hasBlock'])) {
            return $head . ';';
        }

        $block = $this->serializeUnknownRuleBlockValue($rule);
        if ($block !== null) {
            return $head . '{' . $block . '}';
        }

        $body = (string) ($rule['body'] ?? '');

        return $head . '{' . (
            $parentSelectors === null
                ? $this->processRuleList($body)
                : $this->processStyleBody($body, $parentSelectors)
        ) . '}';
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function serializeUnknownRuleBlockValue(array $rule): ?string
    {
        if (!array_key_exists('block', $rule) || !is_array($rule['block'])) {
            return null;
        }
        if (array_key_exists('body', $rule) && is_string($rule['body']) && $rule['body'] !== '') {
            return null;
        }

        return $this->serializeComponentSequence(
            array_values($rule['block']),
            fn (mixed $component): string => $this->serializeVisitorValue($component)
        );
    }

    private function serializeAtRulePreludeValue(mixed $prelude, string $fallback = ''): string
    {
        if (is_string($prelude)) {
            return trim($prelude);
        }
        if (is_array($prelude)) {
            $serialized = $this->serializeVisitorValue($prelude);
            if ($serialized !== '') {
                return trim($serialized);
            }
        }

        return trim($fallback);
    }

    /**
     * @param list<array<string, mixed>> $visitors
     * @param array<string, mixed> $token
     */
    private static function callComposedTokenVisitors(array $visitors, string $tokenType, array $token, self $transformer): mixed
    {
        foreach ($visitors as $visitor) {
            $callback = self::tokenVisitorCallback($visitor, $tokenType);
            if ($callback === null) {
                continue;
            }

            $replacement = $callback($token, $transformer);
            if ($replacement !== null) {
                return $replacement;
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $visitors
     * @param array<string, mixed> $value
     */
    private static function callComposedUnitVariantVisitors(array $visitors, string $visitorName, array $value, self $transformer): mixed
    {
        $changed = false;
        foreach ($visitors as $visitor) {
            $callback = self::unitVariantVisitorCallback($visitor, $visitorName);
            if ($callback === null) {
                continue;
            }

            $replacement = $callback($value, $transformer);
            if ($replacement !== null) {
                $value = $transformer->normalizeUnitVariantVisitorValue($replacement, $visitorName);
                $changed = true;
            }
        }

        return $changed ? $value : null;
    }

    /**
     * @param list<array<string, mixed>> $visitors
     * @param array<int, int|float> $value
     */
    private static function callComposedRatioVisitors(array $visitors, array $value, self $transformer): mixed
    {
        $changed = false;
        foreach ($visitors as $visitor) {
            $callback = self::ratioVisitorCallback($visitor);
            if ($callback === null) {
                continue;
            }

            $replacement = $callback($value, $transformer);
            if ($replacement !== null) {
                $value = $transformer->normalizeRatioVisitorValue($replacement);
                $changed = true;
            }
        }

        return $changed ? $value : null;
    }

    /**
     * @param list<array<string, mixed>> $visitors
     * @param array<string, mixed> $rule
     */
    private static function applyComposedRuleVisitors(array $visitors, array $rule, self $transformer, bool $exit): mixed
    {
        $rules = [$rule];
        $changed = false;
        $seen = [];
        $visitorCount = count($visitors);
        $visitorName = $exit ? 'RuleExit' : 'Rule';

        for ($ruleIndex = 0; $ruleIndex < count($rules); $ruleIndex++) {
            for ($visitorIndex = 0; $visitorIndex < $visitorCount && $ruleIndex < count($rules);) {
                if (!empty($seen[$visitorIndex])) {
                    $visitorIndex++;
                    continue;
                }

                $currentRule = $rules[$ruleIndex];
                if (!is_array($currentRule)) {
                    $visitorIndex++;
                    continue;
                }

                $callback = self::composedRuleVisitorCallback($visitors[$visitorIndex], $currentRule, $exit);
                if ($callback === null) {
                    $visitorIndex++;
                    continue;
                }

                $replacement = $callback['callback']($callback['argument'], $transformer);
                if ($replacement === null) {
                    $visitorIndex++;
                    continue;
                }

                $nextRules = self::normalizeComposedRuleVisitorReplacement($replacement, $visitorName);
                array_splice($rules, $ruleIndex, 1, $nextRules);
                $changed = true;
                $seen[$visitorIndex] = true;
                $visitorIndex = 0;
            }
        }

        if (!$changed) {
            return null;
        }

        return count($rules) === 1 ? $rules[0] : $rules;
    }

    /**
     * @param array<string, mixed> $visitor
     * @param array<string, mixed> $rule
     * @return array{callback:callable,argument:mixed}|null
     */
    private static function composedRuleVisitorCallback(array $visitor, array $rule, bool $exit): ?array
    {
        $ruleConfig = $visitor[$exit ? 'RuleExit' : 'Rule'] ?? null;
        if (is_callable($ruleConfig)) {
            return ['callback' => $ruleConfig, 'argument' => $rule];
        }

        $type = strtolower((string) ($rule['type'] ?? ''));
        if (is_array($ruleConfig)) {
            if ($type === 'custom' && isset($rule['value']) && is_array($rule['value'])) {
                $customConfig = $ruleConfig['custom'] ?? null;
                $callback = self::namedOrCallableRuleCallback($customConfig, (string) ($rule['value']['name'] ?? ''));

                return $callback === null ? null : ['callback' => $callback, 'argument' => $rule['value']];
            }

            if ($type === 'unknown' && isset($rule['value']) && is_array($rule['value'])) {
                $unknownConfig = $ruleConfig['unknown'] ?? null;
                $callback = self::namedOrCallableRuleCallback($unknownConfig, (string) ($rule['value']['name'] ?? ''));

                return $callback === null ? null : ['callback' => $callback, 'argument' => $rule['value']];
            }

            $callback = $ruleConfig[$type] ?? null;
            if (is_callable($callback)) {
                return ['callback' => $callback, 'argument' => $rule];
            }
        }

        if ($exit) {
            return null;
        }

        if ($type === 'custom' && isset($rule['value']) && is_array($rule['value'])) {
            $callback = self::namedOrCallableRuleCallback($visitor['custom'] ?? null, (string) ($rule['value']['name'] ?? ''))
                ?? self::caseInsensitiveCallback($visitor, (string) ($rule['value']['name'] ?? ''));

            return $callback === null ? null : ['callback' => $callback, 'argument' => $rule['value']];
        }

        if ($type === 'unknown' && isset($rule['value']) && is_array($rule['value'])) {
            $callback = self::namedOrCallableRuleCallback($visitor['unknown'] ?? null, (string) ($rule['value']['name'] ?? ''));

            return $callback === null ? null : ['callback' => $callback, 'argument' => $rule['value']];
        }

        foreach (['style', 'media', 'supports', 'container'] as $legacyType) {
            if ($type !== $legacyType) {
                continue;
            }
            $callback = $visitor[$legacyType] ?? null;

            return is_callable($callback) ? ['callback' => $callback, 'argument' => $rule] : null;
        }

        return null;
    }

    private static function namedOrCallableRuleCallback(mixed $config, string $name): ?callable
    {
        if (is_callable($config)) {
            return $config;
        }

        if (is_array($config)) {
            return self::caseInsensitiveCallback($config, $name);
        }

        return null;
    }

    /**
     * @return list<mixed>
     */
    private static function normalizeComposedRuleVisitorReplacement(mixed $replacement, string $visitorName): array
    {
        if ($replacement === false || $replacement === []) {
            return [];
        }
        if (is_string($replacement)) {
            return [$replacement];
        }
        if (!is_array($replacement)) {
            throw new \InvalidArgumentException($visitorName . ' visitor must return a rule array, list of rules, string, or null');
        }
        if (array_is_list($replacement)) {
            $rules = [];
            foreach ($replacement as $item) {
                foreach (self::normalizeComposedRuleVisitorReplacement($item, $visitorName) as $rule) {
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
     * @param list<array<string, mixed>> $visitors
     * @param array<string, mixed> $declaration
     * @return list<array<string, mixed>>|null
     */
    private static function applyComposedDeclarationVisitors(array $visitors, array $declaration, bool $exit, self $transformer): ?array
    {
        $declarations = [$declaration];
        $changed = false;
        $seen = [];
        $visitorCount = count($visitors);

        for ($declarationIndex = 0; $declarationIndex < count($declarations); $declarationIndex++) {
            for ($visitorIndex = 0; $visitorIndex < $visitorCount && $declarationIndex < count($declarations);) {
                if (!empty($seen[$visitorIndex])) {
                    $visitorIndex++;
                    continue;
                }

                $currentDeclaration = $declarations[$declarationIndex];
                $callback = $exit
                    ? self::declarationExitVisitorCallback($visitors[$visitorIndex], $currentDeclaration)
                    : self::declarationVisitorCallback($visitors[$visitorIndex], $currentDeclaration);
                if ($callback === null) {
                    $visitorIndex++;
                    continue;
                }

                $replacement = $callback($currentDeclaration, $transformer);
                if ($replacement === null) {
                    $visitorIndex++;
                    continue;
                }

                $nextDeclarations = self::normalizeDeclarationVisitorList($replacement);
                array_splice($declarations, $declarationIndex, 1, $nextDeclarations);
                $changed = true;
                $seen[$visitorIndex] = true;
                $visitorIndex = 0;
            }
        }

        return $changed ? $declarations : null;
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
    private static function styleRuleExitVisitorCallback(array $visitor): ?callable
    {
        $ruleConfig = $visitor['RuleExit'] ?? null;
        if (is_callable($ruleConfig)) {
            return $ruleConfig;
        }

        if (is_array($ruleConfig) && is_callable($ruleConfig['style'] ?? null)) {
            return $ruleConfig['style'];
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
    private static function mediaRuleExitVisitorCallback(array $visitor): ?callable
    {
        $ruleConfig = $visitor['RuleExit'] ?? null;
        if (is_callable($ruleConfig)) {
            return $ruleConfig;
        }

        if (is_array($ruleConfig) && is_callable($ruleConfig['media'] ?? null)) {
            return $ruleConfig['media'];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $visitor
     */
    private static function supportsRuleVisitorCallback(array $visitor): ?callable
    {
        $ruleConfig = $visitor['Rule'] ?? null;
        if (is_callable($ruleConfig)) {
            return $ruleConfig;
        }

        if (is_array($ruleConfig) && is_callable($ruleConfig['supports'] ?? null)) {
            return $ruleConfig['supports'];
        }

        $supportsConfig = $visitor['supports'] ?? null;
        if (is_callable($supportsConfig)) {
            return $supportsConfig;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $visitor
     */
    private static function supportsRuleExitVisitorCallback(array $visitor): ?callable
    {
        $ruleConfig = $visitor['RuleExit'] ?? null;
        if (is_callable($ruleConfig)) {
            return $ruleConfig;
        }

        if (is_array($ruleConfig) && is_callable($ruleConfig['supports'] ?? null)) {
            return $ruleConfig['supports'];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $visitor
     */
    private static function containerRuleVisitorCallback(array $visitor): ?callable
    {
        $ruleConfig = $visitor['Rule'] ?? null;
        if (is_callable($ruleConfig)) {
            return $ruleConfig;
        }

        if (is_array($ruleConfig) && is_callable($ruleConfig['container'] ?? null)) {
            return $ruleConfig['container'];
        }

        $containerConfig = $visitor['container'] ?? null;
        if (is_callable($containerConfig)) {
            return $containerConfig;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $visitor
     */
    private static function containerRuleExitVisitorCallback(array $visitor): ?callable
    {
        $ruleConfig = $visitor['RuleExit'] ?? null;
        if (is_callable($ruleConfig)) {
            return $ruleConfig;
        }

        if (is_array($ruleConfig) && is_callable($ruleConfig['container'] ?? null)) {
            return $ruleConfig['container'];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $visitor
     * @param array<string, mixed> $variable
     */
    private static function variableExitVisitorCallback(array $visitor, array $variable): ?callable
    {
        $config = $visitor['VariableExit'] ?? null;
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
     * @param array<string, mixed> $environmentVariable
     */
    private static function environmentVariableExitVisitorCallback(array $visitor, array $environmentVariable): ?callable
    {
        $config = $visitor['EnvironmentVariableExit'] ?? null;
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
    private static function unitVariantVisitorCallback(array $visitor, string $visitorName): ?callable
    {
        $config = $visitor[$visitorName] ?? null;

        return is_callable($config) ? $config : null;
    }

    /**
     * @param array<string, mixed> $visitor
     */
    private static function ratioVisitorCallback(array $visitor): ?callable
    {
        $ratioConfig = $visitor['Ratio'] ?? null;

        return is_callable($ratioConfig) ? $ratioConfig : null;
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
    private static function imageVisitorCallback(array $visitor): ?callable
    {
        $imageConfig = $visitor['Image'] ?? null;

        return is_callable($imageConfig) ? $imageConfig : null;
    }

    /**
     * @param array<string, mixed> $visitor
     */
    private static function imageExitVisitorCallback(array $visitor): ?callable
    {
        $imageConfig = $visitor['ImageExit'] ?? null;

        return is_callable($imageConfig) ? $imageConfig : null;
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
        return $this->serializeComponentSequence(
            $tokens,
            fn (mixed $token): string => $this->serializeDeclarationVisitorValue($token)
        );
    }

    /**
     * @param list<mixed> $components
     */
    private function serializeComponentSequence(array $components, callable $serializer): string
    {
        $output = '';
        foreach ($components as $component) {
            $css = $serializer($component);
            if ($css === '') {
                continue;
            }
            if ($this->isCommaDelimiterComponent($component, $css)) {
                $output = rtrim($output) . ',';
                continue;
            }
            if ($output !== '' && !str_ends_with($output, ',') && !$this->componentCssStartsWithoutSpace($css) && !$this->componentCssEndsWithoutSpace($output)) {
                $output .= ' ';
            }
            $output .= $css;
        }

        return $output;
    }

    private function isCommaDelimiterComponent(mixed $component, string $css): bool
    {
        if ($css !== ',') {
            return false;
        }
        if (!is_array($component)) {
            return false;
        }
        if (($component['type'] ?? null) === 'token' && is_array($component['value'] ?? null)) {
            return ($component['value']['type'] ?? null) === 'delim' && ($component['value']['value'] ?? null) === ',';
        }

        return ($component['type'] ?? null) === 'delim' && ($component['value'] ?? null) === ',';
    }

    private function componentCssStartsWithoutSpace(string $css): bool
    {
        return str_starts_with($css, ')')
            || str_starts_with($css, ']')
            || str_starts_with($css, '}')
            || str_starts_with($css, ',')
            || str_starts_with($css, '/')
            || str_starts_with($css, '=')
            || str_starts_with($css, ':')
            || str_starts_with($css, ';')
            || str_starts_with($css, '~=')
            || str_starts_with($css, '|=')
            || str_starts_with($css, '^=')
            || str_starts_with($css, '$=')
            || str_starts_with($css, '*=');
    }

    private function componentCssEndsWithoutSpace(string $css): bool
    {
        return str_ends_with($css, '(')
            || str_ends_with($css, '[')
            || str_ends_with($css, '{')
            || str_ends_with($css, '/')
            || str_ends_with($css, '=')
            || str_ends_with($css, ':')
            || str_ends_with($css, ';')
            || str_ends_with($css, '~=')
            || str_ends_with($css, '|=')
            || str_ends_with($css, '^=')
            || str_ends_with($css, '$=')
            || str_ends_with($css, '*=');
    }

    /**
     * @return list<mixed>
     */
    private function parseComponentValueList(string $value, bool $parseNestedBlocks = false): array
    {
        if ($parseNestedBlocks) {
            $cursor = 0;
            $parsed = $this->parseNestedComponentValueList($value, $cursor, null);

            return $parsed['components'];
        }

        return array_map(
            fn (string $token): mixed => $this->parseComponentValue($token),
            $this->splitComponentValueTokens($value)
        );
    }

    /**
     * @return array{components:list<mixed>,closed:bool}
     */
    private function parseNestedComponentValueList(string $value, int &$cursor, ?string $closing): array
    {
        $components = [];
        $current = '';
        $quote = null;
        $length = strlen($value);

        while ($cursor < $length) {
            $char = $value[$cursor];
            if ($quote !== null) {
                $current .= $char;
                if ($char === '\\' && $cursor + 1 < $length) {
                    $current .= $value[++$cursor];
                    $cursor++;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                $cursor++;
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $current .= $char;
                $cursor++;
                continue;
            }

            if ($char === '\\') {
                $escapeEnd = $this->consumeCssIdentifierEscape($value, $cursor);
                if ($escapeEnd !== null) {
                    $current .= substr($value, $cursor, $escapeEnd - $cursor);
                    $cursor = $escapeEnd;
                    continue;
                }

                $current .= $char;
                $cursor++;
                continue;
            }

            if ($closing !== null && $char === $closing) {
                $this->flushComponentValueToken($components, $current);
                $cursor++;

                return ['components' => $components, 'closed' => true];
            }

            if ($char === ',' || ctype_space($char)) {
                $this->flushComponentValueToken($components, $current);
                if ($char === ',') {
                    $components[] = $this->parseComponentValue(',');
                }
                $cursor++;
                continue;
            }

            if ($char === '(' && $this->currentComponentIsFunctionName($current)) {
                $close = $this->findMatchingParen($value, $cursor);
                if ($close !== null) {
                    $current .= substr($value, $cursor, $close - $cursor + 1);
                    $cursor = $close + 1;
                    continue;
                }
            }

            $delimiter = $this->componentValueDelimiterAt($value, $cursor);
            if ($delimiter !== null) {
                $this->flushComponentValueToken($components, $current);
                $components[] = $this->parseComponentValue($delimiter);
                $cursor += strlen($delimiter);
                continue;
            }

            $block = $this->componentValueBlockToken($char);
            if ($block !== null) {
                $this->flushComponentValueToken($components, $current);
                $components[] = $this->componentValueStructuralToken($block['open'], $char);
                $cursor++;

                $inner = $this->parseNestedComponentValueList($value, $cursor, $block['closeChar']);
                foreach ($inner['components'] as $component) {
                    $components[] = $component;
                }
                if ($inner['closed']) {
                    $components[] = $this->componentValueStructuralToken($block['close'], $block['closeChar']);
                }
                continue;
            }

            $current .= $char;
            $cursor++;
        }

        $this->flushComponentValueToken($components, $current);

        return ['components' => $components, 'closed' => false];
    }

    /**
     * @param list<mixed> $components
     */
    private function flushComponentValueToken(array &$components, string &$current): void
    {
        $token = trim($current);
        if ($token !== '') {
            $components[] = $this->parseComponentValue($token);
        }
        $current = '';
    }

    private function currentComponentIsFunctionName(string $component): bool
    {
        $component = trim($component);
        if ($component === '') {
            return false;
        }

        $identifier = $this->readCssIdentifierRaw($component, 0);

        return $identifier !== null && $identifier['end'] === strlen($component);
    }

    private function componentValueDelimiterAt(string $value, int $cursor): ?string
    {
        foreach (['~=', '|=', '^=', '$=', '*='] as $operator) {
            if (substr($value, $cursor, 2) === $operator) {
                return $operator;
            }
        }

        $char = $value[$cursor] ?? '';

        return in_array($char, ['=', '/', ':', ';'], true) ? $char : null;
    }

    /**
     * @return array{open:string,close:string,closeChar:string}|null
     */
    private function componentValueBlockToken(string $char): ?array
    {
        return match ($char) {
            '(' => ['open' => 'parenthesis-block', 'close' => 'close-parenthesis', 'closeChar' => ')'],
            '[' => ['open' => 'square-bracket-block', 'close' => 'close-square-bracket', 'closeChar' => ']'],
            '{' => ['open' => 'curly-bracket-block', 'close' => 'close-curly-bracket', 'closeChar' => '}'],
            default => null,
        };
    }

    /**
     * @return array{type:string,raw:string,value:array{type:string}}
     */
    private function componentValueStructuralToken(string $type, string $raw): array
    {
        return [
            'type' => 'token',
            'raw' => $raw,
            'value' => [
                'type' => $type,
            ],
        ];
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
            } elseif (in_array(strtolower($name), ['rotate', 'rotatex', 'rotatey', 'rotatez'], true)) {
                $angle = $this->tryCustomAnglePreludeAst($arguments);
                if (!$angle['matched']) {
                    $transforms[] = [
                        'type' => 'raw',
                        'value' => $name . '(' . $arguments . ')',
                    ];
                } else {
                    $transforms[] = [
                        'type' => match (strtolower($name)) {
                            'rotatex' => 'rotateX',
                            'rotatey' => 'rotateY',
                            'rotatez' => 'rotateZ',
                            default => 'rotate',
                        },
                        'value' => $angle['value']['value'],
                    ];
                }
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
        if ($token === ',') {
            return [
                'type' => 'token',
                'raw' => ',',
                'value' => [
                    'type' => 'delim',
                    'value' => ',',
                ],
            ];
        }

        if (in_array($token, ['=', '/', ':', ';', '~=', '|=', '^=', '$=', '*='], true)) {
            return [
                'type' => 'token',
                'raw' => $token,
                'value' => [
                    'type' => 'delim',
                    'value' => $token,
                ],
            ];
        }

        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))%$/', $token, $matches) === 1) {
            return [
                'type' => 'token',
                'raw' => $token,
                'value' => [
                    'type' => 'percentage',
                    'value' => (float) $matches[1] / 100,
                ],
            ];
        }

        if (($numberToken = $this->parseNumberTokenValue($token)) !== null) {
            return $numberToken;
        }

        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))((?:--)(?:\\\\[0-9a-fA-F]{1,6}\s?|\\\\[^\r\n\f]|[-_a-zA-Z0-9\x{0080}-\x{10FFFF}])+)/u', $token, $matches) === 1 && strlen($matches[0]) === strlen($token)) {
            return [
                'type' => 'token',
                'raw' => $token,
                'value' => [
                    'type' => 'dimension',
                    'value' => (float) $matches[1],
                    'unit' => $this->decodeCssEscapes($matches[2]),
                ],
            ];
        }

        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))([a-zA-Z]+)$/', $token, $matches) === 1) {
            return $this->parseComponentDimensionValue((float) $matches[1], $matches[2]);
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

        if (str_starts_with($token, '@') && ($identifier = $this->readCssIdentifierRaw($token, 1)) !== null && $identifier['end'] === strlen($token)) {
            return [
                'type' => 'token',
                'raw' => $token,
                'value' => [
                    'type' => 'at-keyword',
                    'value' => $identifier['value'],
                ],
            ];
        }

        $functionIdentifier = $this->readCssIdentifierRaw($token, 0);
        if ($functionIdentifier !== null && ($token[$functionIdentifier['end']] ?? '') === '(') {
            $name = $functionIdentifier['value'];
            $open = $functionIdentifier['end'];
            $close = $this->findMatchingParen($token, $open);
            if ($close === strlen($token) - 1) {
                $argumentsCss = substr($token, $open + 1, $close - $open - 1);
                $lowerName = strtolower($name);

                if ($lowerName === 'url') {
                    return [
                        'type' => 'url',
                        'value' => $this->parseUrlValue($argumentsCss, $token),
                    ];
                }

                if ($lowerName === 'env') {
                    return [
                        'type' => 'env',
                        'value' => $this->parseEnvironmentVariable($argumentsCss, $token),
                    ];
                }

                return [
                    'type' => $lowerName === 'var' ? 'var' : 'function',
                    'value' => $lowerName === 'var'
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

        $identifier = $this->readCssIdentifierRaw($token, 0);
        if ($identifier !== null && $identifier['end'] === strlen($token) && str_starts_with($identifier['value'], '--')) {
            return [
                'type' => 'dashed-ident',
                'value' => $identifier['value'],
            ];
        }

        if ($identifier !== null && $identifier['end'] === strlen($token)) {
            return [
                'type' => 'token',
                'value' => [
                    'type' => 'ident',
                    'value' => $identifier['value'],
                ],
            ];
        }

        return ['type' => 'raw', 'value' => $token];
    }

    /**
     * @return array{type:string,raw:string,value:array{type:string,value:float}}|null
     */
    private function parseNumberTokenValue(string $token): ?array
    {
        if (preg_match('/^[+-]?(?:\d+|\d*\.\d+)$/', $token) !== 1) {
            return null;
        }

        return [
            'type' => 'token',
            'raw' => $token,
            'value' => [
                'type' => 'number',
                'value' => (float) $token,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseComponentDimensionValue(float $number, string $unit): array
    {
        $unit = strtolower($unit);

        return match ($unit) {
            'deg', 'grad', 'rad', 'turn' => [
                'type' => 'angle',
                'value' => [
                    'type' => $unit,
                    'value' => $number,
                ],
            ],
            'ms' => [
                'type' => 'time',
                'value' => [
                    'type' => 'milliseconds',
                    'value' => $number,
                ],
            ],
            's' => [
                'type' => 'time',
                'value' => [
                    'type' => 'seconds',
                    'value' => $number,
                ],
            ],
            'dpi', 'dpcm', 'dppx' => [
                'type' => 'resolution',
                'value' => [
                    'type' => $unit,
                    'value' => $number,
                ],
            ],
            default => [
                'type' => 'length',
                'value' => [
                    'unit' => $unit,
                    'value' => $number,
                ],
            ],
        };
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
            $styleReplacement = ($this->styleRuleVisitor)($rule, $this);
            if ($styleReplacement !== null) {
                return $this->emitStyleRuleReplacement($styleReplacement, $rule);
            }
        }

        if ($visitStyleRule) {
            $exitReplacement = $this->applyStyleRuleExit($rule);
            if ($exitReplacement !== null) {
                return $exitReplacement;
            }
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
        $previousProperty = $this->activeDeclarationProperty;
        $this->activeDeclarationProperty = $property === null ? null : strtolower($property);
        $this->functionReplacementAppliedColorVisitor = false;
        try {
            $rewritten = $this->rewriteValueTokens($this->rewriteValueFunctions($this->rewriteStandaloneLengths($value)));
            if ($property !== null && !$this->functionReplacementAppliedColorVisitor) {
                $rewritten = $this->rewriteColorDeclarationValue($rewritten, $property);
            }

            return $property === null ? $rewritten : $this->rewriteAnimationCustomIdents($property, $rewritten);
        } finally {
            $this->activeDeclarationProperty = $previousProperty;
        }
    }

    private function restoreRawValueVisitorTokenBoundaries(string $code): string
    {
        if ($this->rawValueVisitorReplacementProperties === []) {
            return $code;
        }

        return preg_replace_callback(
            '/([\\{;])([-_a-zA-Z][-_a-zA-Z0-9]*):([^;{}]+)/',
            function (array $matches): string {
                $property = strtolower($matches[2]);
                if (!isset($this->rawValueVisitorReplacementProperties[$property])) {
                    return $matches[0];
                }

                return $matches[1] . $matches[2] . ':' . $this->restoreRawValueVisitorDeclarationSpacing($property, $matches[3]);
            },
            $code
        ) ?? $code;
    }

    private function restoreRawValueVisitorDeclarationSpacing(string $property, string $value): string
    {
        if ($property === 'content') {
            return $this->restoreAdjacentStringTokenSpacing($value);
        }

        if ($property === 'cursor') {
            return preg_replace('/,(?!\\s)(?=(?!url\\()[_-]?[a-zA-Z])/i', ', ', $value) ?? $value;
        }

        if ($property === 'transform' || str_ends_with($property, '-transform')) {
            return preg_replace(
                '/\\)(?=(?:matrix(?:3d)?|translate(?:3d|[XYZ])?|scale(?:3d|[XYZ])?|rotate(?:3d|[XYZ])?|skew[XY]?|perspective)\\()/i',
                ') ',
                $value
            ) ?? $value;
        }

        return $value;
    }

    private function restoreAdjacentStringTokenSpacing(string $value): string
    {
        $output = '';
        $quote = null;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            $output .= $char;

            if ($quote === null) {
                if ($char === '"' || $char === "'") {
                    $quote = $char;
                }
                continue;
            }

            if ($char === '\\' && $i + 1 < $length) {
                $output .= $value[++$i];
                continue;
            }

            if ($char === $quote) {
                $quote = null;
                $next = $value[$i + 1] ?? '';
                if ($next === '"' || $next === "'") {
                    $output .= ' ';
                }
            }
        }

        return $output;
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
        $token = trim($token);

        return $this->isCssIdentifierToken($token)
            && !in_array(strtolower($token), ['inherit', 'initial', 'revert', 'revert-layer', 'unset', 'default'], true);
    }

    private function isCssIdentifierToken(string $token): bool
    {
        $token = trim($token);
        if ($token === '' || preg_match('/\s/', $token) === 1) {
            return false;
        }

        return preg_match('/^(?:-?[_a-zA-Z\x{0080}-\x{10FFFF}][-_a-zA-Z0-9\x{0080}-\x{10FFFF}]*|--[-_a-zA-Z0-9\x{0080}-\x{10FFFF}]*)$/u', $token) === 1;
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
        if (!is_string($replacement) || !str_starts_with($replacement, '--') || !$this->isCssIdentifierToken($replacement)) {
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
            return $this->applyValueVisitors($this->normalizeVisitorValue($replacement), ['function']);
        }

        $serializedArguments = implode(',', array_map(fn (mixed $argument): string => $this->serializeVisitorValue($argument), $arguments));

        return [
            'type' => 'raw',
            'value' => $name . '(' . $this->rewriteRawVisitorFunctions($serializedArguments) . ')',
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

    /**
     * @param list<string> $skipStructuredTypes
     */
    private function callStructuredValueVisitor(string $name, string $argumentsCss, string $raw, array $skipStructuredTypes = []): mixed
    {
        $lower = strtolower($name);
        if (in_array($lower, $skipStructuredTypes, true)) {
            return null;
        }

        if ($lower === 'env' && $this->hasEnvironmentVariableVisitor()) {
            $environmentVariable = $this->parseEnvironmentVariable($argumentsCss, $raw);
            $replacement = $this->callEnvironmentVariableVisitor($environmentVariable);

            if ($replacement !== null) {
                $value = $this->applyValueVisitors($this->normalizeVisitorValue($replacement), [...$skipStructuredTypes, 'env']);
                if (!is_array($value) || ($value['type'] ?? null) !== 'env') {
                    $this->recordRawValueVisitorReplacement($value);

                    return $value;
                }

                $environmentVariable = is_array($value['value'] ?? null) ? $value['value'] : $value;
            }

            $exitReplacement = $this->callEnvironmentVariableExitVisitor($environmentVariable);

            if ($exitReplacement === null) {
                return ['type' => 'env', 'value' => $environmentVariable];
            }

            $value = $this->applyValueVisitors($this->normalizeVisitorValue($exitReplacement), [...$skipStructuredTypes, 'env']);
            $this->recordRawValueVisitorReplacement($value);

            return $value;
        }

        if ($lower === 'var' && $this->hasVariableVisitor()) {
            $variable = $this->parseVariable($argumentsCss, $raw);
            $replacement = $this->callVariableVisitor($variable);

            if ($replacement !== null) {
                $value = $this->applyValueVisitors($this->normalizeVisitorValue($replacement), [...$skipStructuredTypes, 'var']);
                if (!is_array($value) || ($value['type'] ?? null) !== 'var') {
                    $this->recordRawValueVisitorReplacement($value);

                    return $value;
                }

                $variable = is_array($value['value'] ?? null) ? $value['value'] : $value;
            }

            $exitReplacement = $this->callVariableExitVisitor($variable);

            if ($exitReplacement === null) {
                return ['type' => 'var', 'value' => $variable];
            }

            $value = $this->applyValueVisitors($this->normalizeVisitorValue($exitReplacement), [...$skipStructuredTypes, 'var']);
            $this->recordRawValueVisitorReplacement($value);

            return $value;
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

    private function recordRawValueVisitorReplacement(mixed $value): void
    {
        if ($this->activeDeclarationProperty === null || !$this->visitorValueContainsRawCss($value)) {
            return;
        }

        $this->rawValueVisitorReplacementProperties[$this->activeDeclarationProperty] = true;
    }

    private function visitorValueContainsRawCss(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        if (isset($value['raw']) && is_string($value['raw'])) {
            return true;
        }

        if (($value['type'] ?? null) === 'raw' && is_string($value['value'] ?? null)) {
            return true;
        }

        foreach ($value as $child) {
            if (is_array($child) && $this->visitorValueContainsRawCss($child)) {
                return true;
            }
        }

        return false;
    }

    private function hasEnvironmentVariableVisitor(): bool
    {
        return $this->environmentVariableVisitors !== []
            || $this->genericEnvironmentVariableVisitor !== null
            || $this->environmentVariableExitVisitors !== []
            || $this->genericEnvironmentVariableExitVisitor !== null
            || $this->dashedIdentVisitor !== null;
    }

    private function hasVariableVisitor(): bool
    {
        return $this->variableVisitors !== []
            || $this->genericVariableVisitor !== null
            || $this->variableExitVisitors !== []
            || $this->genericVariableExitVisitor !== null
            || $this->dashedIdentVisitor !== null;
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
     * @param array<string, mixed> $environmentVariable
     */
    private function callEnvironmentVariableExitVisitor(array $environmentVariable): mixed
    {
        $visitor = $this->environmentVariableExitVisitors[self::environmentVariableCallbackName($environmentVariable)] ?? $this->genericEnvironmentVariableExitVisitor;
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

    /**
     * @param array<string, mixed> $variable
     */
    private function callVariableExitVisitor(array $variable): mixed
    {
        $visitor = $this->variableExitVisitors[self::variableCallbackName($variable)] ?? $this->genericVariableExitVisitor;
        if ($visitor === null) {
            return null;
        }

        return $visitor($variable, $this);
    }

    /**
     * @return array{value:mixed,changed:bool}
     */
    private function visitCustomPreludeValue(mixed $value): array
    {
        if (!is_array($value)) {
            return ['value' => $value, 'changed' => false];
        }

        $type = $value['type'] ?? null;
        if ($type === 'repeated' && isset($value['value']) && is_array($value['value'])) {
            $components = $value['value']['components'] ?? [];
            if (!is_array($components)) {
                return ['value' => $value, 'changed' => false];
            }

            $changed = false;
            foreach ($components as $index => $component) {
                $visited = $this->visitCustomPreludeValue($component);
                if ($visited['changed']) {
                    $components[$index] = $visited['value'];
                    $changed = true;
                }
            }

            if (!$changed) {
                return ['value' => $value, 'changed' => false];
            }

            $value['value']['components'] = $components;

            return ['value' => $value, 'changed' => true];
        }

        if ($type === 'token-list' && isset($value['value']) && is_array($value['value'])) {
            $visited = $this->visitCustomPreludeTokenList($value['value']);
            if ($visited['changed']) {
                $value['value'] = $visited['value'];

                return ['value' => $value, 'changed' => true];
            }

            return ['value' => $value, 'changed' => false];
        }

        if ($type === 'image' && isset($value['value']) && is_array($value['value'])) {
            $visited = $this->visitImageValue($value['value']);
            if ($visited['changed']) {
                $value['value'] = $visited['value'];

                return ['value' => $value, 'changed' => true];
            }

            return ['value' => $value, 'changed' => false];
        }

        if ($type === 'url' && isset($value['value']) && is_array($value['value']) && $this->urlVisitor !== null) {
            $replacement = ($this->urlVisitor)($value['value'], $this);
            if ($replacement !== null) {
                $value['value'] = $this->normalizeUrlVisitorValue($replacement, $value['value']);

                return ['value' => $value, 'changed' => true];
            }
        }

        if ($type === 'custom-ident' && is_string($value['value'] ?? null)) {
            $original = $value['value'];
            $value['value'] = $this->applyCustomIdentVisitor($original);

            return ['value' => $value, 'changed' => $value['value'] !== $original];
        }

        if ($type === 'dashed-ident' && is_string($value['value'] ?? null)) {
            $original = $value['value'];
            $value['value'] = $this->applyDashedIdentVisitor($original);

            return ['value' => $value, 'changed' => $value['value'] !== $original];
        }

        $visited = $this->applyValueVisitors($value);
        if ($visited !== $value) {
            return ['value' => $visited, 'changed' => true];
        }

        return ['value' => $value, 'changed' => false];
    }

    /**
     * @param list<mixed> $components
     * @return array{value:list<mixed>,changed:bool}
     */
    private function visitCustomPreludeTokenList(array $components, int $depth = 0, ?string $skipTokenType = null): array
    {
        $visitedComponents = [];
        $changed = false;

        foreach ($components as $component) {
            $visited = $this->visitCustomPreludeTokenListComponent($component, $depth, $skipTokenType);
            foreach ($visited['value'] as $replacement) {
                $visitedComponents[] = $replacement;
            }
            $changed = $changed || $visited['changed'];
        }

        return [
            'value' => $changed ? $visitedComponents : $components,
            'changed' => $changed,
        ];
    }

    /**
     * @return array{value:list<mixed>,changed:bool}
     */
    private function visitCustomPreludeTokenListComponent(mixed $component, int $depth = 0, ?string $skipTokenType = null): array
    {
        if (!is_array($component)) {
            return ['value' => [$component], 'changed' => false];
        }

        $originalCss = $this->serializeVisitorValue($component);
        $type = $component['type'] ?? null;

        if ($type === 'function' && isset($component['value']) && is_array($component['value'])) {
            $function = $component['value'];
            $name = (string) ($function['name'] ?? '');
            $argumentsCss = $this->functionValueArgumentsCss($function);
            $raw = $name . '(' . $argumentsCss . ')';

            $structuredReplacement = $this->callStructuredValueVisitor($name, $argumentsCss, $raw);
            if ($structuredReplacement !== null) {
                return $this->customPreludeTokenListReplacement($structuredReplacement, $originalCss, $depth);
            }

            $replacement = $this->callFunctionVisitor($name, $this->parseFunctionArguments($argumentsCss), $raw);
            if ($replacement !== null) {
                return $this->customPreludeTokenListCssReplacement($replacement, $originalCss, $depth);
            }

            $hasFunctionExitVisitor = ($this->functionExitVisitors[strtolower($name)] ?? $this->genericFunctionExitVisitor) !== null;
            $arguments = $function['arguments'] ?? [];
            if (!$hasFunctionExitVisitor && is_array($arguments)) {
                $visitedArguments = $this->visitCustomPreludeTokenList($arguments, $depth + 1, $skipTokenType);
                if ($visitedArguments['changed']) {
                    $function['arguments'] = $visitedArguments['value'];
                    $component['value'] = $function;

                    return ['value' => [$component], 'changed' => true];
                }
            }

            $visited = $this->visitFunctionExit($name, $argumentsCss, $raw);
            $visitedCss = $this->serializeVisitorValue($visited);
            if ($visitedCss !== $originalCss) {
                return $this->customPreludeTokenListCssReplacement($visitedCss, $originalCss, $depth);
            }

            return ['value' => [$component], 'changed' => false];
        }

        if ($type === 'var' && isset($component['value']) && is_array($component['value'])) {
            $argumentsCss = $this->variableArgumentsCss($component['value']);
            $raw = 'var(' . $argumentsCss . ')';
            $replacement = $this->callStructuredValueVisitor('var', $argumentsCss, $raw);
            if ($replacement !== null) {
                return $this->customPreludeTokenListReplacement($replacement, $originalCss, $depth);
            }
        }

        if ($type === 'env' && isset($component['value']) && is_array($component['value'])) {
            $argumentsCss = $this->environmentVariableArgumentsCss($component['value']);
            $raw = 'env(' . $argumentsCss . ')';
            $replacement = $this->callStructuredValueVisitor('env', $argumentsCss, $raw);
            if ($replacement !== null) {
                return $this->customPreludeTokenListReplacement($replacement, $originalCss, $depth);
            }
        }

        if ($type === 'dashed-ident' && is_string($component['value'] ?? null)) {
            $replacement = $this->applyDashedIdentVisitor($component['value']);
            if ($replacement !== $component['value']) {
                return [
                    'value' => [[
                        'type' => 'dashed-ident',
                        'value' => $replacement,
                    ]],
                    'changed' => true,
                ];
            }
        }

        if ($type === 'token' && isset($component['value']) && is_array($component['value'])) {
            $token = $component['value'];
            $tokenType = (string) ($token['type'] ?? '');
            if ($tokenType !== '' && $tokenType !== $skipTokenType && $this->tokenVisitorEnabled($tokenType)) {
                $token['raw'] = isset($component['raw']) && is_string($component['raw'])
                    ? $component['raw']
                    : $originalCss;
                $replacement = $this->callTokenVisitor($tokenType, $token);
                if ($replacement !== null) {
                    return $this->customPreludeTokenListCssReplacement($replacement, $originalCss, $depth, $tokenType);
                }
            }
        }

        $visited = $this->applyValueVisitors($component);
        if ($visited !== $component) {
            return $this->customPreludeTokenListReplacement($visited, $originalCss, $depth);
        }

        return ['value' => [$component], 'changed' => false];
    }

    /**
     * @param array<string, mixed> $function
     */
    private function functionValueArgumentsCss(array $function): string
    {
        $arguments = $function['arguments'] ?? [];
        if (!is_array($arguments)) {
            return '';
        }

        return implode(',', array_map(fn (mixed $argument): string => $this->serializeVisitorValue($argument), $arguments));
    }

    /**
     * @param array<string, mixed> $variable
     */
    private function variableArgumentsCss(array $variable): string
    {
        $name = self::variableCallbackName($variable);
        $fallback = $variable['fallback'] ?? null;
        if (!is_array($fallback) || $fallback === []) {
            return $name;
        }

        return $name . ',' . implode(',', array_map(fn (mixed $value): string => $this->serializeVisitorValue($value), $fallback));
    }

    /**
     * @param array<string, mixed> $environmentVariable
     */
    private function environmentVariableArgumentsCss(array $environmentVariable): string
    {
        $name = self::environmentVariableCallbackName($environmentVariable);
        $indices = $this->environmentVariableIndicesCss($environmentVariable);
        $head = $indices === '' ? $name : $name . ' ' . $indices;
        $fallback = $environmentVariable['fallback'] ?? null;
        if (!is_array($fallback) || $fallback === []) {
            return $head;
        }

        return $head . ',' . implode(',', array_map(fn (mixed $value): string => $this->serializeVisitorValue($value), $fallback));
    }

    /**
     * @param array<string, mixed> $environmentVariable
     */
    private function environmentVariableIndicesCss(array $environmentVariable): string
    {
        $indices = $environmentVariable['indices'] ?? [];
        if (!is_array($indices) || $indices === []) {
            return '';
        }

        $parts = [];
        foreach ($indices as $index) {
            if (is_int($index) || (is_string($index) && preg_match('/^[+-]?\d+$/', $index) === 1)) {
                $parts[] = (string) (int) $index;
            }
        }

        return implode(' ', $parts);
    }

    /**
     * @return array{value:list<mixed>,changed:bool}
     */
    private function customPreludeTokenListReplacement(mixed $replacement, string $originalCss, int $depth = 0, ?string $skipTokenType = null): array
    {
        return $this->customPreludeTokenListCssReplacement($this->serializeVisitorValue($replacement), $originalCss, $depth, $skipTokenType);
    }

    /**
     * @return array{value:list<mixed>,changed:bool}
     */
    private function customPreludeTokenListCssReplacement(string $replacementCss, string $originalCss, int $depth = 0, ?string $skipTokenType = null): array
    {
        $replacementCss = trim($replacementCss);
        $changed = $replacementCss !== $originalCss;
        $components = $replacementCss === '' ? [] : $this->parseComponentValueList($replacementCss, true);

        if ($changed && $components !== [] && $depth < self::CUSTOM_PRELUDE_TOKEN_REVISIT_LIMIT) {
            $visited = $this->visitCustomPreludeTokenList($components, $depth + 1, $skipTokenType);
            if ($visited['changed']) {
                $components = $visited['value'];
            }
            $changed = true;
        }

        return [
            'value' => $components,
            'changed' => $changed,
        ];
    }

    /**
     * @param array<string, mixed> $image
     * @return array{value:array<string, mixed>,changed:bool}
     */
    private function visitImageValue(array $image): array
    {
        $changed = false;
        if ($this->imageVisitor !== null) {
            $replacement = ($this->imageVisitor)($image, $this);
            if ($replacement !== null) {
                $image = $this->normalizeImageVisitorValue($replacement, $image);
                $changed = true;
            }
        }

        if (($image['type'] ?? null) === 'url' && isset($image['value']) && is_array($image['value']) && $this->urlVisitor !== null) {
            $replacement = ($this->urlVisitor)($image['value'], $this);
            if ($replacement !== null) {
                $image['value'] = $this->normalizeUrlVisitorValue($replacement, $image['value']);
                $changed = true;
            }
        }

        if ($this->imageExitVisitor !== null) {
            $replacement = ($this->imageExitVisitor)($image, $this);
            if ($replacement !== null) {
                $image = $this->normalizeImageVisitorValue($replacement, $image);
                $changed = true;
            }
        }

        return ['value' => $image, 'changed' => $changed];
    }

    /**
     * @param list<string> $skipStructuredTypes
     */
    private function applyValueVisitors(mixed $value, array $skipStructuredTypes = []): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            $visited = [];
            $changed = false;
            foreach ($value as $part) {
                $normalized = $this->normalizeVisitorValue($part);
                $next = $this->applyValueVisitors($normalized, $skipStructuredTypes);
                if (is_array($next) && array_is_list($next)) {
                    foreach ($next as $nextPart) {
                        $visited[] = $nextPart;
                    }
                } else {
                    $visited[] = $next;
                }
                $changed = $changed || $next !== $part;
            }

            return $changed ? $visited : $value;
        }

        if (($value['type'] ?? null) === 'function' && isset($value['value']) && is_array($value['value']) && !in_array('function', $skipStructuredTypes, true)) {
            $function = $value['value'];
            $name = (string) ($function['name'] ?? '');
            if ($name !== '') {
                $argumentsCss = $this->functionValueArgumentsCss($function);
                $raw = $name . '(' . $argumentsCss . ')';
                $structuredReplacement = $this->callStructuredValueVisitor($name, $argumentsCss, $raw, $skipStructuredTypes);
                if ($structuredReplacement !== null) {
                    return $structuredReplacement;
                }

                $replacement = $this->callFunctionVisitor($name, $this->parseFunctionArguments($argumentsCss), $raw);
                if ($replacement !== null) {
                    return ['type' => 'raw', 'value' => $replacement];
                }

                $visited = $this->visitFunctionExit($name, $argumentsCss, $raw);
                if ($this->serializeVisitorValue($visited) !== $raw) {
                    return $visited;
                }
            }
        }

        if (($value['type'] ?? null) === 'var' && is_array($value['value'] ?? null)) {
            $argumentsCss = $this->variableArgumentsCss($value['value']);
            $replacement = $this->callStructuredValueVisitor('var', $argumentsCss, 'var(' . $argumentsCss . ')', $skipStructuredTypes);
            if ($replacement !== null) {
                return $replacement;
            }
        }

        if (($value['type'] ?? null) === 'env' && is_array($value['value'] ?? null)) {
            $argumentsCss = $this->environmentVariableArgumentsCss($value['value']);
            $replacement = $this->callStructuredValueVisitor('env', $argumentsCss, 'env(' . $argumentsCss . ')', $skipStructuredTypes);
            if ($replacement !== null) {
                return $replacement;
            }
        }

        if (($value['type'] ?? null) === 'url' && is_array($value['value'] ?? null) && $this->urlVisitor !== null) {
            $replacement = ($this->urlVisitor)($value['value'], $this);
            if ($replacement !== null) {
                return [
                    'type' => 'url',
                    'value' => $this->normalizeUrlVisitorValue($replacement, $value['value']),
                ];
            }
        }

        if (($value['type'] ?? null) === 'image' && isset($value['value']) && is_array($value['value'])) {
            $visited = $this->visitImageValue($value['value']);
            if ($visited['changed']) {
                return [
                    'type' => 'image',
                    'value' => $visited['value'],
                ];
            }
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

        if (($value['type'] ?? null) === 'length-percentage' && isset($value['value']) && is_array($value['value'])) {
            $visited = $this->visitLengthPercentageValue($value['value']);
            if ($visited['changed']) {
                return [
                    'type' => 'length-percentage',
                    'value' => $visited['value'],
                ];
            }
        }

        if (($value['type'] ?? null) === 'transform-function' && isset($value['value']) && is_array($value['value'])) {
            $visited = $this->visitTransformValue($value['value']);
            if ($visited['changed']) {
                return [
                    'type' => 'transform-function',
                    'value' => $visited['value'],
                ];
            }
        }

        if (($value['type'] ?? null) === 'transform-list' && isset($value['value']) && is_array($value['value'])) {
            $transforms = [];
            $changed = false;
            foreach ($value['value'] as $transform) {
                if (!is_array($transform)) {
                    $transforms[] = $transform;
                    continue;
                }

                $visited = $this->visitTransformValue($transform);
                $transforms[] = $visited['value'];
                $changed = $changed || $visited['changed'];
            }

            if ($changed) {
                return [
                    'type' => 'transform-list',
                    'value' => $transforms,
                ];
            }
        }

        foreach ([
            'angle' => $this->angleVisitor,
            'time' => $this->timeVisitor,
            'resolution' => $this->resolutionVisitor,
        ] as $type => $visitor) {
            if (($value['type'] ?? null) !== $type || $visitor === null || !is_array($value['value'] ?? null)) {
                continue;
            }

            $replacement = $visitor($value['value'], $this);
            if ($replacement !== null) {
                return [
                    'type' => $type,
                    'value' => $this->normalizeUnitVariantVisitorValue($replacement, ucfirst($type)),
                ];
            }
        }

        if (($value['type'] ?? null) === 'ratio' && $this->ratioVisitor !== null && is_array($value['value'] ?? null)) {
            $replacement = ($this->ratioVisitor)($this->normalizeRatioVisitorValue($value['value']), $this);
            if ($replacement !== null) {
                return [
                    'type' => 'ratio',
                    'value' => $this->normalizeRatioVisitorValue($replacement),
                ];
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
     * @param array<string, mixed> $transform
     * @return array{value:array<string, mixed>,changed:bool}
     */
    private function visitTransformValue(array $transform): array
    {
        $type = $transform['type'] ?? null;
        if (in_array($type, ['translateX', 'translateY'], true) && isset($transform['value']) && is_array($transform['value'])) {
            $visited = $this->visitTransformLengthPercentageValue($transform['value']);
            if ($visited['changed']) {
                $transform['value'] = $visited['value'];

                return ['value' => $transform, 'changed' => true];
            }
        }

        if (in_array($type, ['rotate', 'rotateX', 'rotateY', 'rotateZ'], true) && isset($transform['value']) && is_array($transform['value'])) {
            $visited = $this->applyValueVisitors([
                'type' => 'angle',
                'value' => $transform['value'],
            ]);
            if (is_array($visited) && ($visited['type'] ?? null) === 'angle' && isset($visited['value']) && is_array($visited['value']) && $visited['value'] !== $transform['value']) {
                $transform['value'] = $visited['value'];

                return ['value' => $transform, 'changed' => true];
            }
        }

        return ['value' => $transform, 'changed' => false];
    }

    /**
     * @param array<string, mixed> $value
     * @return array{value:array<string, mixed>,changed:bool}
     */
    private function visitTransformLengthPercentageValue(array $value): array
    {
        if (($value['type'] ?? null) === 'dimension') {
            return $this->visitLengthPercentageValue($value);
        }

        return ['value' => $value, 'changed' => false];
    }

    /**
     * @return array{type:string,value:int|float}
     */
    private function normalizeUnitVariantVisitorValue(mixed $value, string $visitorName): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException($visitorName . ' visitor must return a unit variant array or null');
        }

        $outerType = $value['type'] ?? null;
        if (in_array($outerType, ['angle', 'time', 'resolution'], true) && is_array($value['value'] ?? null)) {
            $expectedType = strtolower($visitorName);
            if ($outerType !== $expectedType) {
                throw new \InvalidArgumentException($visitorName . ' visitor must return a ' . $expectedType . ' unit variant');
            }

            $value = $value['value'];
        }

        $type = $value['type'] ?? null;
        $number = $value['value'] ?? null;
        if (!is_string($type) || (!is_int($number) && !is_float($number))) {
            throw new \InvalidArgumentException($visitorName . ' visitor must return a typed numeric unit variant');
        }

        return [
            'type' => strtolower($type),
            'value' => $number,
        ];
    }

    /**
     * @return array{0:int|float,1:int|float}
     */
    private function normalizeRatioVisitorValue(mixed $value): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('Ratio visitor must return a two-number ratio array or null');
        }

        if (($value['type'] ?? null) === 'ratio' && is_array($value['value'] ?? null)) {
            $value = $value['value'];
        }

        $first = $value[0] ?? null;
        $second = $value[1] ?? null;
        if ((!is_int($first) && !is_float($first)) || (!is_int($second) && !is_float($second))) {
            throw new \InvalidArgumentException('Ratio visitor must return a two-number ratio array or null');
        }

        return [$first, $second];
    }

    /**
     * @param array<string, mixed> $value
     * @return array{value:array<string, mixed>,changed:bool}
     */
    private function visitLengthPercentageValue(array $value): array
    {
        if (($value['type'] ?? null) !== 'dimension' || !is_array($value['value'] ?? null) || $this->lengthVisitor === null) {
            return ['value' => $value, 'changed' => false];
        }

        $length = $this->lengthComponents($this->normalizeLengthValue($value['value']));
        if ($length === null) {
            return ['value' => $value, 'changed' => false];
        }

        $replacement = ($this->lengthVisitor)($length, $this);
        if ($replacement === null) {
            return ['value' => $value, 'changed' => false];
        }

        $replacementLength = $this->lengthComponents($this->normalizeLengthValue($replacement));
        if ($replacementLength === null) {
            throw new \InvalidArgumentException('Length visitor must return a length value for length-percentage preludes or null');
        }

        return [
            'value' => [
                'type' => 'dimension',
                'value' => $replacementLength,
            ],
            'changed' => true,
        ];
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
            return $this->serializeComponentSequence(
                $value,
                fn (mixed $part): string => $this->serializeVisitorValue($part)
            );
        }

        if (isset($value['raw']) && is_string($value['raw'])) {
            return $value['raw'];
        }

        $type = $value['type'] ?? null;
        if ($type === 'translateX') {
            return 'translateX(' . $this->serializeTransformArgument($value['value'] ?? '') . ')';
        }
        if (in_array($type, ['rotate', 'rotateX', 'rotateY', 'rotateZ'], true)) {
            return $type . '(' . $this->serializeUnitVariantValue($value['value'] ?? []) . ')';
        }
        if ($type === 'transform-function' && isset($value['value'])) {
            return $this->serializeVisitorValue($value['value']);
        }
        if ($type === 'transform-list' && isset($value['value']) && is_array($value['value'])) {
            return implode(' ', array_map(fn (mixed $transform): string => $this->serializeVisitorValue($transform), $value['value']));
        }
        if ($type === 'token-list' && isset($value['value']) && is_array($value['value'])) {
            return $this->serializeComponentSequence(
                $value['value'],
                fn (mixed $component): string => $this->serializeVisitorValue($component)
            );
        }
        if ($type === 'repeated' && isset($value['value']) && is_array($value['value'])) {
            $components = $value['value']['components'] ?? [];
            if (!is_array($components)) {
                $components = [];
            }
            $multiplier = $value['value']['multiplier']['type'] ?? 'space';
            $separator = $multiplier === 'comma' ? ',' : ' ';

            return implode($separator, array_map(fn (mixed $component): string => $this->serializeVisitorValue($component), $components));
        }
        if ($type === 'literal') {
            return (string) ($value['value'] ?? '');
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
        if ($type === 'ratio' && isset($value['value']) && is_array($value['value'])) {
            return $this->serializeRatioValue($this->normalizeRatioVisitorValue($value['value']));
        }
        if (in_array($type, ['angle', 'time', 'resolution'], true) && isset($value['value']) && is_array($value['value'])) {
            return $this->serializeUnitVariantValue($value['value']);
        }
        if ($type === 'image' && isset($value['value']) && is_array($value['value'])) {
            return $this->serializeImageValue($value['value']);
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
            if (($token['type'] ?? null) === 'hash' || ($token['type'] ?? null) === 'id-hash') {
                return '#' . (string) ($token['value'] ?? '');
            }
            if (($token['type'] ?? null) === 'delim') {
                return (string) ($token['value'] ?? '');
            }
            if (($token['type'] ?? null) === 'parenthesis-block') {
                return '(';
            }
            if (($token['type'] ?? null) === 'close-parenthesis') {
                return ')';
            }
            if (($token['type'] ?? null) === 'square-bracket-block') {
                return '[';
            }
            if (($token['type'] ?? null) === 'close-square-bracket') {
                return ']';
            }
            if (($token['type'] ?? null) === 'curly-bracket-block') {
                return '{';
            }
            if (($token['type'] ?? null) === 'close-curly-bracket') {
                return '}';
            }
            if (($token['type'] ?? null) === 'colon') {
                return ':';
            }
            if (($token['type'] ?? null) === 'semicolon') {
                return ';';
            }
            if (($token['type'] ?? null) === 'comma') {
                return ',';
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
        if ($type === 'ident') {
            return (string) ($value['value'] ?? '');
        }
        if ($type === 'hash' || $type === 'id-hash') {
            return '#' . (string) ($value['value'] ?? '');
        }
        if ($type === 'string') {
            return '"' . addcslashes((string) ($value['value'] ?? ''), "\\\"") . '"';
        }
        if ($type === 'number' && (is_int($value['value'] ?? null) || is_float($value['value'] ?? null))) {
            return $this->formatNumber($value['value']);
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

    /**
     * @param array<string, mixed> $value
     */
    private function serializeUnitVariantValue(array $value): string
    {
        $type = strtolower((string) ($value['type'] ?? ''));
        $number = $value['value'] ?? null;
        if (!is_int($number) && !is_float($number)) {
            return '';
        }

        $unit = match ($type) {
            'seconds' => 's',
            'milliseconds' => 'ms',
            default => $type,
        };

        return $this->formatNumber($number) . $unit;
    }

    /**
     * @param array{0:int|float,1:int|float} $ratio
     */
    private function serializeRatioValue(array $ratio): string
    {
        $first = $this->formatNumber($ratio[0]);
        $second = $this->formatNumber($ratio[1]);

        return $second === '1' ? $first : $first . '/' . $second;
    }

    /**
     * @param array<string, mixed> $value
     */
    private function serializeImageValue(array $value): string
    {
        $type = strtolower((string) ($value['type'] ?? ''));
        if ($type === 'none') {
            return 'none';
        }
        if ($type === 'url' && isset($value['value']) && is_array($value['value'])) {
            return $this->serializeUrlValue($value['value']);
        }
        if (($type === 'gradient' || $type === 'image-set') && isset($value['value']) && is_array($value['value'])) {
            return $this->serializeVisitorValue($value['value']);
        }

        return $this->serializeVisitorValue($value['value'] ?? '');
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
            $normalized = $this->normalizeVisitorValue($replacement);
            if (
                is_array($normalized)
                && ($normalized['type'] ?? null) === 'raw'
                && is_string($normalized['value'] ?? null)
            ) {
                return $this->rewriteRawVisitorFunctions($normalized['value']);
            }

            $appliesColorVisitor = $this->colorVisitor !== null && $this->colorComponents($normalized) !== null;
            $visited = $this->applyValueVisitors($normalized, ['function']);
            if ($appliesColorVisitor) {
                $this->functionReplacementAppliedColorVisitor = true;
            }

            return $this->serializeVisitorValue($visited);
        }

        return (string) $replacement;
    }

    private function rewriteRawVisitorFunctions(string $value): string
    {
        if (
            !$this->hasEnvironmentVariableVisitor()
            && !$this->hasVariableVisitor()
            && $this->urlVisitor === null
        ) {
            return $value;
        }

        $output = '';
        $cursor = 0;
        $length = strlen($value);

        while ($cursor < $length) {
            $char = $value[$cursor];
            if ($char === '"' || $char === "'") {
                $quote = $char;
                $output .= $char;
                $cursor++;
                while ($cursor < $length) {
                    $output .= $value[$cursor];
                    if ($value[$cursor] === '\\' && $cursor + 1 < $length) {
                        $cursor++;
                        $output .= $value[$cursor];
                    } elseif ($value[$cursor] === $quote) {
                        $cursor++;
                        break;
                    }
                    $cursor++;
                }
                continue;
            }

            if (preg_match('/[a-zA-Z_-][-_a-zA-Z0-9]*(?=\()/A', substr($value, $cursor), $matches) !== 1) {
                $output .= $char;
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
            $raw = $name . '(' . $argumentsCss . ')';
            $replacement = $this->callStructuredValueVisitor($name, $argumentsCss, $raw);
            $output .= $replacement === null
                ? $name . '(' . $this->rewriteRawVisitorFunctions($argumentsCss) . ')'
                : $this->serializeVisitorValue($replacement);
            $cursor = $close + 1;
        }

        return $output;
    }

    private function rewriteValueTokens(string $value): string
    {
        if ($this->tokenVisitors === [] && $this->genericTokenVisitor === null) {
            return $value;
        }

        $output = '';
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($char === '"' || $char === "'") {
                $quote = $char;
                $raw = $char;
                $i++;
                while ($i < $length) {
                    $raw .= $value[$i];
                    if ($value[$i] === '\\' && $i + 1 < $length) {
                        $i++;
                        $raw .= $value[$i];
                    } elseif ($value[$i] === $quote) {
                        break;
                    }
                    $i++;
                }

                $replacement = $this->tokenVisitorEnabled('string')
                    ? $this->callTokenVisitor('string', [
                        'type' => 'string',
                        'value' => stripcslashes(substr($raw, 1, -1)),
                        'raw' => $raw,
                    ])
                    : null;
                $output .= $replacement ?? $raw;
                continue;
            }

            $functionIdentifier = $this->readCssIdentifierRaw($value, $i);
            if ($functionIdentifier !== null && ($value[$functionIdentifier['end']] ?? '') === '(') {
                $name = $functionIdentifier['value'];
                $rawName = $functionIdentifier['raw'];
                $open = $functionIdentifier['end'];
                $close = $this->findMatchingParen($value, $open);
                if ($close !== null) {
                    $argumentsCss = substr($value, $open + 1, $close - $open - 1);
                    $output .= $rawName . '(' . $this->rewriteValueTokens($argumentsCss) . ')';
                    $i = $close;
                    continue;
                }
            }

            if ($char === '@' && ($identifier = $this->readCssIdentifierRaw($value, $i + 1)) !== null) {
                $raw = '@' . $identifier['raw'];
                $replacement = $this->callTokenVisitor('at-keyword', [
                    'type' => 'at-keyword',
                    'value' => $identifier['value'],
                    'raw' => $raw,
                ]);
                $output .= $replacement ?? $raw;
                $i += strlen($raw) - 1;
                continue;
            }

            if ($char === '#' && preg_match('/#([-_a-zA-Z0-9]+)/A', substr($value, $i), $matches) === 1) {
                $raw = $matches[0];
                $before = $i > 0 ? $value[$i - 1] : '';
                $after = $value[$i + strlen($raw)] ?? '';
                if (
                    ($before === '' || !preg_match('/[-_a-zA-Z0-9]/', $before))
                    && ($after === '' || !preg_match('/[-_a-zA-Z0-9]/', $after))
                ) {
                    $hashType = preg_match('/^-?[_a-zA-Z][-_a-zA-Z0-9]*$/', $matches[1]) === 1 ? 'id-hash' : 'hash';
                    $replacement = $this->callTokenVisitor($hashType, [
                        'type' => $hashType,
                        'value' => $matches[1],
                        'raw' => $raw,
                    ]);
                    $output .= $replacement ?? $raw;
                    $i += strlen($raw) - 1;
                    continue;
                }
            }

            if (preg_match('/([+-]?(?:\d+|\d*\.\d+))((?:--)(?:\\\\[0-9a-fA-F]{1,6}\s?|\\\\[^\r\n\f]|[-_a-zA-Z0-9\x{0080}-\x{10FFFF}])*)/Au', substr($value, $i), $matches) === 1) {
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
                        'unit' => $this->decodeCssEscapes($matches[2]),
                        'raw' => $raw,
                    ]);
                    if ($replacement !== null) {
                        $output .= $replacement;
                        $i += strlen($raw) - 1;
                        continue;
                    }
                }
            }

            if (preg_match('/([+-]?(?:\d+|\d*\.\d+))%/A', substr($value, $i), $matches) === 1) {
                $raw = $matches[0];
                $before = $i > 0 ? $value[$i - 1] : '';
                $after = $value[$i + strlen($raw)] ?? '';
                if (
                    ($before === '' || !preg_match('/[-_a-zA-Z0-9.]/', $before))
                    && ($after === '' || !preg_match('/[-_a-zA-Z0-9]/', $after))
                ) {
                    $replacement = $this->callTokenVisitor('percentage', [
                        'type' => 'percentage',
                        'value' => (float) $matches[1] / 100,
                        'raw' => $raw,
                    ]);
                    $output .= $replacement ?? $raw;
                    $i += strlen($raw) - 1;
                    continue;
                }
            }

            if (preg_match('/[+-]?(?:\d+|\d*\.\d+)/A', substr($value, $i), $matches) === 1) {
                $raw = $matches[0];
                $before = $i > 0 ? $value[$i - 1] : '';
                $after = $value[$i + strlen($raw)] ?? '';
                if (
                    ($before === '' || !preg_match('/[-_a-zA-Z0-9.]/', $before))
                    && ($after === '' || !preg_match('/[-_a-zA-Z0-9.]/', $after))
                ) {
                    $replacement = $this->callTokenVisitor('number', [
                        'type' => 'number',
                        'value' => (float) $raw,
                        'raw' => $raw,
                    ]);
                    $output .= $replacement ?? $raw;
                    $i += strlen($raw) - 1;
                    continue;
                }
            }

            if (($identifier = $this->readCssIdentifierRaw($value, $i)) !== null) {
                $raw = $identifier['raw'];
                $before = $i > 0 ? $value[$i - 1] : '';
                $after = $value[$i + strlen($raw)] ?? '';
                if (
                    ($before === '' || !preg_match('/[-_a-zA-Z0-9]/', $before))
                    && ($after === '' || !preg_match('/[-_a-zA-Z0-9]/', $after))
                ) {
                    $replacement = $this->callTokenVisitor('ident', [
                        'type' => 'ident',
                        'value' => $identifier['value'],
                        'raw' => $raw,
                    ]);
                    $output .= $replacement ?? $raw;
                    $i += strlen($raw) - 1;
                    continue;
                }
            }

            $output .= $char;
        }

        return $output;
    }

    private function tokenVisitorEnabled(string $type): bool
    {
        return $this->genericTokenVisitor !== null || isset($this->tokenVisitors[strtolower($type)]);
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

        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))%$/', $argument, $matches) === 1) {
            return [
                'type' => 'token',
                'raw' => $argument,
                'value' => [
                    'type' => 'percentage',
                    'value' => (float) $matches[1] / 100,
                ],
            ];
        }

        if (($numberToken = $this->parseNumberTokenValue($argument)) !== null) {
            return $numberToken;
        }

        if (preg_match('/^([+-]?(?:\d+|\d*\.\d+))([a-zA-Z]+)$/', $argument, $matches) === 1) {
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
     * @return array{name:array<string, string>, indices:list<int>, fallback:list<mixed>|null, raw:string}
     */
    private function parseEnvironmentVariable(string $argumentsCss, string $raw): array
    {
        $parts = $this->splitTopLevel($argumentsCss, ',');
        [$name, $indices] = $this->parseEnvironmentVariableNameAndIndices(trim($parts[0] ?? ''));

        return [
            'name' => str_starts_with($name, '--')
                ? ['type' => 'custom', 'ident' => $name]
                : ['type' => 'ua', 'value' => $name],
            'indices' => $indices,
            'fallback' => count($parts) > 1 ? $this->parseFallbackTokenList(implode(',', array_slice($parts, 1))) : null,
            'raw' => $raw,
        ];
    }

    /**
     * @return array{0:string,1:list<int>}
     */
    private function parseEnvironmentVariableNameAndIndices(string $head): array
    {
        if ($head === '') {
            return ['', []];
        }

        $tokens = preg_split('/\s+/', $head);
        if (!is_array($tokens) || count($tokens) <= 1) {
            return [$head, []];
        }

        $name = array_shift($tokens);
        if (!is_string($name) || $name === '') {
            return [$head, []];
        }

        $indices = [];
        foreach ($tokens as $token) {
            if (!is_string($token) || preg_match('/^[+-]?\d+$/', $token) !== 1) {
                return [$head, []];
            }

            $indices[] = (int) $token;
        }

        return [$name, $indices];
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
     * @param array<string, mixed> $fallback
     * @return array<string, mixed>
     */
    private function normalizeImageVisitorValue(mixed $replacement, array $fallback): array
    {
        if (is_string($replacement)) {
            $parsed = $this->tryCustomImagePreludeAst($replacement);
            if (!$parsed['matched'] || !is_array($parsed['value'] ?? null) || !is_array($parsed['value']['value'] ?? null)) {
                throw new \InvalidArgumentException('Image visitor string replacement must parse as <image>');
            }

            return $parsed['value']['value'];
        }

        if (!is_array($replacement)) {
            throw new \InvalidArgumentException('Image visitor must return an image array, image string, or null');
        }

        if (($replacement['type'] ?? null) === 'image' && is_array($replacement['value'] ?? null)) {
            $replacement = $replacement['value'];
        }

        $type = $replacement['type'] ?? $fallback['type'] ?? null;
        if (!is_string($type)) {
            throw new \InvalidArgumentException('Image visitor replacement must contain a string type');
        }

        $normalized = array_replace($fallback, $replacement);
        $normalized['type'] = strtolower($type);

        if ($normalized['type'] === 'none') {
            unset($normalized['value']);

            return $normalized;
        }

        if ($normalized['type'] === 'url') {
            if (!is_array($normalized['value'] ?? null)) {
                throw new \InvalidArgumentException('Image visitor URL replacement must contain a URL value');
            }

            $normalized['value'] = $this->normalizeUrlVisitorValue($normalized['value'], is_array($fallback['value'] ?? null) ? $fallback['value'] : []);
        }

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
            $ruleLocation = $this->sourceLocationForOffset($css, $cursor);

            $nextBlock = $this->findNextTopLevel($css, '{', $cursor);
            $nextStatement = $this->findNextTopLevel($css, ';', $cursor);

            if ($nextStatement !== null && ($nextBlock === null || $nextStatement < $nextBlock)) {
                $statement = trim(substr($css, $cursor, $nextStatement - $cursor));
                if ($statement !== '' && str_starts_with($statement, '@')) {
                    [$name, $prelude] = $this->parseAtPrelude($statement);
                    $rules[] = $this->isCustomAtRule($name)
                        ? ['type' => 'custom', 'value' => $this->buildCustomRule($name, $prelude, null, $parentSelectors, true, $ruleLocation)]
                        : ['type' => 'unknown', 'value' => $this->buildUnknownRule($name, $prelude, null, $parentSelectors, $ruleLocation)];
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
                    $rules[] = $this->buildMediaRule($atPrelude, $body, $parentSelectors, $ruleLocation);
                } elseif ($name === 'supports') {
                    $rules[] = [
                        'type' => 'supports',
                        'value' => [
                            'loc' => $ruleLocation,
                            'condition' => $this->parseSupportsConditionForVisitor($atPrelude),
                            'rules' => $this->parseReturnedRuleList($body, $parentSelectors),
                        ],
                    ];
                } elseif ($name === 'container') {
                    $rules[] = $this->buildContainerRule($atPrelude, $body, $parentSelectors, $ruleLocation);
                } elseif ($this->isCustomAtRule($name)) {
                    $rules[] = ['type' => 'custom', 'value' => $this->buildCustomRule($name, $atPrelude, $body, $parentSelectors, true, $ruleLocation)];
                } else {
                    $rules[] = ['type' => 'unknown', 'value' => $this->buildUnknownRule($name, $atPrelude, $body, $parentSelectors, $ruleLocation)];
                }
            } else {
                $selectors = $parentSelectors === null
                    ? $this->splitTopLevel($prelude, ',')
                    : $this->resolveNestedSelectors($parentSelectors, $prelude);
                $rules[] = [
                    'type' => 'style',
                    'value' => [
                        'loc' => $ruleLocation,
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
        return $this->selectorComponentsFromString($selector);
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
            if ($char === ':' && ($selector[$offset + 1] ?? '') === ':') {
                $name = $this->readSelectorIdentifier($selector, $offset + 2);
                if ($name === '') {
                    $offset += 2;
                    continue;
                }
                $offset += strlen($name) + 2;
                if (($selector[$offset] ?? '') === '(') {
                    $close = $this->findMatchingParen($selector, $offset);
                    if ($close !== null) {
                        $components[] = $this->selectorFunctionalPseudoElementComponent($this->unescapeSelectorIdentifier($name), substr($selector, $offset + 1, $close - $offset - 1));
                        $offset = $close + 1;
                        continue;
                    }
                }
                $components[] = ['type' => 'pseudo-element', 'kind' => $this->unescapeSelectorIdentifier($name)];
                continue;
            }
            if ($char === ':') {
                $name = $this->readSelectorIdentifier($selector, $offset + 1);
                $components[] = ['type' => 'pseudo-class', 'kind' => $name];
                $offset += strlen($name) + 1;
                continue;
            }
            if ($char === '[') {
                $attribute = $this->parseAttributeSelectorComponent($selector, $offset);
                if ($attribute !== null) {
                    $components[] = $attribute['component'];
                    $offset = $attribute['end'];
                    continue;
                }
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

    /**
     * @return array{component:array<string, mixed>,end:int}|null
     */
    private function parseAttributeSelectorComponent(string $selector, int $offset): ?array
    {
        if (($selector[$offset] ?? null) !== '[') {
            return null;
        }

        $close = $this->findAttributeSelectorEnd($selector, $offset);
        if ($close === null) {
            return null;
        }

        $content = trim(substr($selector, $offset + 1, $close - $offset - 1));
        if ($content === '') {
            return null;
        }

        $identifier = '(?:\\\\[0-9a-fA-F]{1,6}\s?|\\\\[^\r\n\f]|[-_a-zA-Z0-9\x{0080}-\x{10FFFF}])+';
        $pattern = '/^(?:(\*|' . $identifier . ')?\|)?(' . $identifier . ')(?:\s*(~=|\|=|\^=|\$=|\*=|=)\s*(?:"((?:\\\\.|[^"\\\\])*)"|\'((?:\\\\.|[^\'\\\\])*)\'|([^\s\]]+))\s*(?:([iIsS])\s*)?)?$/u';
        if (preg_match($pattern, $content, $matches) !== 1) {
            return null;
        }

        $component = [
            'type' => 'attribute',
            'name' => $this->decodeCssEscapes($matches[2]),
        ];

        if (array_key_exists(1, $matches) && $matches[1] !== '') {
            $component['namespace'] = $matches[1] === '*'
                ? ['type' => 'namespace', 'kind' => 'any']
                : ['type' => 'namespace', 'kind' => 'named', 'prefix' => $this->decodeCssEscapes($matches[1])];
        } elseif (str_starts_with($content, '|')) {
            $component['namespace'] = ['type' => 'namespace', 'kind' => 'none'];
        }

        $operator = $matches[3] ?? '';
        if ($operator !== '') {
            $value = $matches[4] ?? $matches[5] ?? $matches[6] ?? '';
            $operation = [
                'operator' => $this->attributeSelectorOperatorFromCss($operator),
                'value' => $this->decodeCssEscapes($value),
            ];
            $caseFlag = $matches[7] ?? '';
            if ($caseFlag !== '') {
                $operation['caseSensitivity'] = strtolower($caseFlag) === 'i'
                    ? 'ascii-case-insensitive'
                    : 'explicit-case-sensitive';
            }
            $component['operation'] = $operation;
        }

        return [
            'component' => $component,
            'end' => $close + 1,
        ];
    }

    private function findAttributeSelectorEnd(string $selector, int $offset): ?int
    {
        $quote = null;
        $length = strlen($selector);
        for ($i = $offset + 1; $i < $length; $i++) {
            $char = $selector[$i];
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
            if ($char === ']') {
                return $i;
            }
        }

        return null;
    }

    private function attributeSelectorOperatorFromCss(string $operator): string
    {
        return match ($operator) {
            '=' => 'equal',
            '~=' => 'includes',
            '|=' => 'dash-match',
            '^=' => 'prefix',
            '*=' => 'substring',
            '$=' => 'suffix',
            default => $operator,
        };
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
        $indices = $this->environmentVariableIndicesCss($environmentVariable);
        $head = $indices === '' ? $name : $name . ' ' . $indices;
        $fallback = $environmentVariable['fallback'] ?? null;
        if (!is_array($fallback) || $fallback === []) {
            return 'env(' . $head . ')';
        }

        return 'env(' . $head . ',' . implode(',', array_map(fn (mixed $value): string => $this->serializeVisitorValue($value), $fallback)) . ')';
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
     * @return list<mixed>
     */
    private function parseUnknownPreludeTokens(string $prelude): array
    {
        return $this->parseComponentValueList($prelude, true);
    }

    /**
     * @return list<string>
     */
    private function splitComponentValueTokens(string $value): array
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
            if ($char === '\\') {
                $escapeEnd = $this->consumeCssIdentifierEscape($value, $i);
                if ($escapeEnd !== null) {
                    $current .= substr($value, $i, $escapeEnd - $i);
                    $i = $escapeEnd - 1;
                    continue;
                }

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

            if ($parenDepth === 0 && $bracketDepth === 0 && ($char === ',' || ctype_space($char))) {
                if (trim($current) !== '') {
                    $tokens[] = trim($current);
                    $current = '';
                }
                if ($char === ',') {
                    $tokens[] = ',';
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
            if ($char === '\\' && $i + 1 < $length) {
                $current .= $char;
                $next = $value[++$i];
                $current .= $next;
                if (ctype_xdigit($next)) {
                    $hexLength = 1;
                    while ($hexLength < 6 && $i + 1 < $length && ctype_xdigit($value[$i + 1])) {
                        $current .= $value[++$i];
                        $hexLength++;
                    }
                    if ($i + 1 < $length && ctype_space($value[$i + 1])) {
                        $current .= $value[++$i];
                    }
                }
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
        return isset($this->customAtRules[$this->normalizeAtRuleName($name)]);
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
        $prelude = trim($prelude);
        if ($prelude === '' || $prelude[0] !== '@') {
            throw new \InvalidArgumentException("Invalid CSS at-rule prelude: {$prelude}");
        }

        $identifier = $this->readCssIdentifierRaw($prelude, 1);
        if ($identifier === null) {
            throw new \InvalidArgumentException("Invalid CSS at-rule prelude: {$prelude}");
        }

        $name = $this->normalizeAtRuleName($identifier['value']);

        return [$name, trim(substr($prelude, $identifier['end']))];
    }

    private function normalizeAtRuleName(string $name): string
    {
        $name = trim($this->decodeCssEscapes($name));

        return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    }

    /**
     * @return array{raw:string,value:string,end:int}|null
     */
    private function readCssIdentifierRaw(string $css, int $offset): ?array
    {
        $cursor = $offset;
        $length = strlen($css);
        $raw = '';

        while ($cursor < $length) {
            $char = $css[$cursor];
            if ($char === '\\') {
                $escapeEnd = $this->consumeCssIdentifierEscape($css, $cursor);
                if ($escapeEnd === null) {
                    break;
                }

                $raw .= substr($css, $cursor, $escapeEnd - $cursor);
                $cursor = $escapeEnd;
                continue;
            }

            if (preg_match('/^[-_a-zA-Z0-9\x{0080}-\x{10FFFF}]/u', substr($css, $cursor), $matches) === 1) {
                $raw .= $matches[0];
                $cursor += strlen($matches[0]);
                continue;
            }

            break;
        }

        if ($raw === '') {
            return null;
        }

        $value = $this->decodeCssEscapes($raw);
        if (!$this->isCssIdentifierToken($value)) {
            return null;
        }

        return [
            'raw' => $raw,
            'value' => $value,
            'end' => $cursor,
        ];
    }

    private function consumeCssIdentifierEscape(string $css, int $offset): ?int
    {
        if (($css[$offset] ?? '') !== '\\') {
            return null;
        }

        $length = strlen($css);
        $cursor = $offset + 1;
        if ($cursor >= $length) {
            return null;
        }

        $char = $css[$cursor];
        if ($char === "\n" || $char === "\r" || $char === "\f") {
            return null;
        }

        if (ctype_xdigit($char)) {
            $count = 0;
            while ($cursor < $length && $count < 6 && ctype_xdigit($css[$cursor])) {
                $cursor++;
                $count++;
            }
            if ($cursor < $length && ctype_space($css[$cursor])) {
                $cursor++;
            }

            return $cursor;
        }

        return $cursor + 1;
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

    /**
     * @return array{source_index:int,line:int,column:int}
     */
    private function defaultSourceLocation(): array
    {
        return [
            'source_index' => 0,
            'line' => 0,
            'column' => 1,
        ];
    }

    /**
     * @return array{source_index:int,line:int,column:int}
     */
    private function sourceLocationForOffset(string $css, int $offset): array
    {
        $length = strlen($css);
        $offset = max(0, min($length, $offset));
        $prefix = substr($css, 0, $offset);
        $line = substr_count($prefix, "\n");
        $lastNewline = strrpos($prefix, "\n");

        return [
            'source_index' => 0,
            'line' => $line,
            'column' => $lastNewline === false ? $offset + 1 : $offset - $lastNewline,
        ];
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
