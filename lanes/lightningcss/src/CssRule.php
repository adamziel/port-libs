<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class CssRule
{
    public const TYPE_STYLE = 'style';
    public const TYPE_AT_RULE = 'at-rule';

    /**
     * @param list<string> $selectors
     * @param array<string, string> $declarations
     * @param list<CssRule> $rules
     */
    public function __construct(
        public readonly string $type,
        public readonly ?string $name,
        public readonly string $prelude,
        public readonly array $selectors,
        public readonly array $declarations,
        public readonly array $rules,
    ) {
        if (!in_array($type, [self::TYPE_STYLE, self::TYPE_AT_RULE], true)) {
            throw new \InvalidArgumentException("Unsupported CSS rule type: {$type}");
        }
        foreach ($selectors as $selector) {
            if (!is_string($selector) || trim($selector) === '') {
                throw new \InvalidArgumentException('Selectors must be non-empty strings');
            }
        }
        foreach ($rules as $rule) {
            if (!$rule instanceof self) {
                throw new \InvalidArgumentException('Nested CSS rules must be CssRule instances');
            }
        }
    }
}
