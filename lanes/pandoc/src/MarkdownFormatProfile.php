<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class MarkdownFormatProfile
{
    /** @var list<string> */
    private const FORMAT_CANDIDATES = [
        'markdown_github',
        'markdown-github',
        'markdown_phpextra',
        'markdown-php-extra',
        'markdown_strict',
        'markdown-strict',
        'markdown_mmd',
        'markdown-mmd',
        'commonmark_x',
        'commonmark-x',
        'commonmark',
        'pandoc',
        'markdown',
        'gfm',
        'md',
    ];

    /** @var array<string, string> */
    private const FORMAT_ALIASES = [
        'md' => 'markdown',
        'pandoc' => 'markdown',
        'markdown_github' => 'gfm',
        'markdown-github' => 'gfm',
        'markdown-php-extra' => 'markdown_phpextra',
        'markdown-strict' => 'markdown_strict',
        'markdown-mmd' => 'markdown_mmd',
        'commonmark-x' => 'commonmark_x',
    ];

    /** @var array<string, array{yamlMetadata:bool, titleBlock:bool, rawAttribute:bool, rawHtml:bool, rawTex:bool, rawMarkdown:bool}> */
    private const DEFAULTS = [
        'markdown' => [
            'yamlMetadata' => true,
            'titleBlock' => true,
            'rawAttribute' => true,
            'rawHtml' => true,
            'rawTex' => true,
            'rawMarkdown' => true,
        ],
        'commonmark' => [
            'yamlMetadata' => false,
            'titleBlock' => false,
            'rawAttribute' => false,
            'rawHtml' => true,
            'rawTex' => false,
            'rawMarkdown' => true,
        ],
        'commonmark_x' => [
            'yamlMetadata' => false,
            'titleBlock' => false,
            'rawAttribute' => true,
            'rawHtml' => true,
            'rawTex' => true,
            'rawMarkdown' => true,
        ],
        'gfm' => [
            'yamlMetadata' => false,
            'titleBlock' => false,
            'rawAttribute' => false,
            'rawHtml' => true,
            'rawTex' => false,
            'rawMarkdown' => true,
        ],
        'markdown_mmd' => [
            'yamlMetadata' => false,
            'titleBlock' => false,
            'rawAttribute' => false,
            'rawHtml' => true,
            'rawTex' => false,
            'rawMarkdown' => true,
        ],
        'markdown_phpextra' => [
            'yamlMetadata' => false,
            'titleBlock' => false,
            'rawAttribute' => false,
            'rawHtml' => true,
            'rawTex' => false,
            'rawMarkdown' => true,
        ],
        'markdown_strict' => [
            'yamlMetadata' => false,
            'titleBlock' => false,
            'rawAttribute' => false,
            'rawHtml' => true,
            'rawTex' => false,
            'rawMarkdown' => true,
        ],
    ];

    public static function canonicalFormat(mixed $format): string
    {
        if (!is_scalar($format)) {
            return 'markdown';
        }

        $normalized = strtolower(trim((string) $format));
        if ($normalized === '') {
            return 'markdown';
        }

        foreach (self::FORMAT_CANDIDATES as $candidate) {
            if (
                $normalized === $candidate
                || str_starts_with($normalized, $candidate . '+')
                || str_starts_with($normalized, $candidate . '-')
            ) {
                return self::FORMAT_ALIASES[$candidate] ?? $candidate;
            }
        }

        return 'markdown';
    }

    public static function rawFamily(string $format): ?string
    {
        $normalized = strtolower(trim($format));
        $base = str_replace('-', '+', $normalized);
        $base = explode('+', $base, 2)[0];

        if (in_array($base, ['html', 'html4', 'html5', 'xhtml'], true)) {
            return 'html';
        }

        if (in_array($base, ['tex', 'latex', 'context'], true)) {
            return 'tex';
        }

        foreach (self::FORMAT_CANDIDATES as $candidate) {
            if (
                $normalized === $candidate
                || str_starts_with($normalized, $candidate . '+')
                || str_starts_with($normalized, $candidate . '-')
            ) {
                return 'markdown';
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function yamlMetadataEnabled(array $options, bool $defaultWithoutFormat): bool
    {
        return self::enabled($options, 'yamlMetadata', $defaultWithoutFormat, 'yaml_metadata_block');
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function titleBlockEnabled(array $options, bool $defaultWithoutFormat): bool
    {
        return self::enabled($options, 'titleBlock', $defaultWithoutFormat, 'pandoc_title_block');
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function rawAttributeEnabled(array $options, bool $defaultWithoutFormat): bool
    {
        return self::enabled($options, 'rawAttribute', $defaultWithoutFormat, 'raw_attribute');
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function rawHtmlEnabled(array $options, bool $defaultWithoutFormat): bool
    {
        return self::enabled($options, 'rawHtml', $defaultWithoutFormat, 'raw_html');
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function rawTexEnabled(array $options, bool $defaultWithoutFormat): bool
    {
        return self::enabled($options, 'rawTex', $defaultWithoutFormat, 'raw_tex');
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function rawMarkdownEnabled(array $options, bool $defaultWithoutFormat): bool
    {
        return self::enabled($options, 'rawMarkdown', $defaultWithoutFormat, 'raw_markdown');
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function enabled(array $options, string $key, bool $defaultWithoutFormat, string $extension): bool
    {
        if (array_key_exists($key, $options)) {
            return self::boolFlag($options[$key], $defaultWithoutFormat);
        }

        if (!array_key_exists('format', $options)) {
            return $defaultWithoutFormat;
        }

        $format = $options['format'];
        $canonical = self::canonicalFormat($format);
        $default = self::DEFAULTS[$canonical][$key] ?? $defaultWithoutFormat;

        return self::extensionFlag($format, $extension, $default);
    }

    private static function boolFlag(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return $default;
    }

    private static function extensionFlag(mixed $format, string $extension, bool $default): bool
    {
        if (!is_scalar($format)) {
            return $default;
        }

        $normalized = strtolower(trim((string) $format));
        if ($normalized === '') {
            return $default;
        }

        if (preg_match_all('/([+-])' . preg_quote($extension, '/') . '(?=$|[+-])/', $normalized, $matches) === false) {
            return $default;
        }

        if ($matches[1] === []) {
            return $default;
        }

        return end($matches[1]) === '+';
    }
}
