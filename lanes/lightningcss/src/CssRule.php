<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class CssRule implements \JsonSerializable
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

    /**
     * @return array{
     *     type: string,
     *     name: string|null,
     *     prelude: string,
     *     selectors: list<string>,
     *     declarations: array<string, string>,
     *     rules: list<array<string, mixed>>
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return array{
     *     type: string,
     *     name: string|null,
     *     prelude: string,
     *     selectors: list<string>,
     *     declarations: array<string, string>,
     *     rules: list<array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'prelude' => $this->prelude,
            'selectors' => $this->selectors,
            'declarations' => $this->declarations,
            'rules' => array_map(static fn (self $rule): array => $rule->toArray(), $this->rules),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::requireString($data, 'type'),
            self::requireNullableString($data, 'name'),
            self::requireString($data, 'prelude'),
            self::requireStringList($data, 'selectors'),
            self::requireStringMap($data, 'declarations'),
            self::requireRuleList($data, 'rules')
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function requireString(array $data, string $key): string
    {
        if (!array_key_exists($key, $data) || !is_string($data[$key])) {
            throw new \InvalidArgumentException("CSS rule field {$key} must be a string");
        }

        return $data[$key];
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function requireNullableString(array $data, string $key): ?string
    {
        if (!array_key_exists($key, $data)) {
            throw new \InvalidArgumentException("CSS rule field {$key} is missing");
        }
        if ($data[$key] !== null && !is_string($data[$key])) {
            throw new \InvalidArgumentException("CSS rule field {$key} must be null or a string");
        }

        return $data[$key];
    }

    /**
     * @param array<string, mixed> $data
     * @return list<string>
     */
    private static function requireStringList(array $data, string $key): array
    {
        if (!array_key_exists($key, $data) || !is_array($data[$key])) {
            throw new \InvalidArgumentException("CSS rule field {$key} must be a string list");
        }

        $strings = [];
        foreach ($data[$key] as $index => $value) {
            if ($index !== count($strings) || !is_string($value)) {
                throw new \InvalidArgumentException("CSS rule field {$key} must be a string list");
            }
            $strings[] = $value;
        }

        return $strings;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private static function requireStringMap(array $data, string $key): array
    {
        if (!array_key_exists($key, $data) || !is_array($data[$key])) {
            throw new \InvalidArgumentException("CSS rule field {$key} must be a string map");
        }

        $strings = [];
        foreach ($data[$key] as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                throw new \InvalidArgumentException("CSS rule field {$key} must be a string map");
            }
            $strings[$name] = $value;
        }

        return $strings;
    }

    /**
     * @param array<string, mixed> $data
     * @return list<self>
     */
    private static function requireRuleList(array $data, string $key): array
    {
        if (!array_key_exists($key, $data) || !is_array($data[$key])) {
            throw new \InvalidArgumentException("CSS rule field {$key} must be a rule list");
        }

        $rules = [];
        foreach ($data[$key] as $index => $rule) {
            if ($index !== count($rules) || !is_array($rule)) {
                throw new \InvalidArgumentException("CSS rule field {$key} must be a rule list");
            }
            $rules[] = self::fromArray($rule);
        }

        return $rules;
    }
}
