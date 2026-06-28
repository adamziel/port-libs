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

    /** @var array<string, string> */
    private const PLUS_FORMAT_ALIASES = [
        'markdown+github' => 'gfm',
        'markdown+strict' => 'markdown_strict',
        'markdown+mmd' => 'markdown_mmd',
        'markdown+multimarkdown' => 'markdown_mmd',
        'markdown+php_extra' => 'markdown_phpextra',
        'markdown+php-extra' => 'markdown_phpextra',
        'markdown+phpextra' => 'markdown_phpextra',
    ];

    /** @var array<string, string> */
    private const EXTENSION_ALIASES = [
        'bracketed_span' => 'bracketed_spans',
        'emoji_shortcode' => 'emoji_shortcodes',
        'header_attrs' => 'header_attributes',
        'header_attribute' => 'header_attributes',
        'inline_attribute' => 'inline_attributes',
        'markdown_attribute' => 'inline_attributes',
        'line_block' => 'line_blocks',
        'raw_latex' => 'raw_tex',
        'latex_macros' => 'raw_tex',
        'subscripts' => 'subscript',
        'superscripts' => 'superscript',
        'task_list' => 'task_lists',
        'task-list' => 'task_lists',
        'tasklist' => 'task_lists',
        'wikilink_title_after_pipe' => 'wikilinks_title_after_pipe',
        'wikilink_title_before_pipe' => 'wikilinks_title_before_pipe',
        'wikilink' => 'wikilinks',
        'wiki_link' => 'wikilinks',
        'wiki_links' => 'wikilinks',
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
        return self::canonicalMarkdownFormat($format) ?? 'markdown';
    }

    public static function canonicalMarkdownFormat(mixed $format): ?string
    {
        if (!is_scalar($format)) {
            return null;
        }

        return self::markdownFormatParts($format)['base'];
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

        if (self::canonicalMarkdownFormat($format) !== null) {
            return 'markdown';
        }

        return null;
    }

    /**
     * @return array<string, bool>
     */
    public static function markdownExtensionOverrides(mixed $format): array
    {
        $parts = self::markdownFormatParts($format);
        $suffix = $parts['suffix'];
        if ($suffix === '') {
            return [];
        }

        if (preg_match_all('/([+-])([A-Za-z0-9_]+)/', $suffix, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        $overrides = [];
        foreach ($matches as $match) {
            $overrides[self::canonicalExtension(strtolower($match[2]))] = $match[1] === '+';
        }

        return $overrides;
    }

    private static function canonicalExtension(string $extension): string
    {
        return self::EXTENSION_ALIASES[$extension] ?? $extension;
    }

    /**
     * @return array{base: ?string, suffix: string}
     */
    private static function markdownFormatParts(mixed $format): array
    {
        if (!is_scalar($format)) {
            return ['base' => null, 'suffix' => ''];
        }

        $normalized = strtolower(trim((string) $format));
        if ($normalized === '') {
            return ['base' => null, 'suffix' => ''];
        }

        foreach (self::PLUS_FORMAT_ALIASES as $alias => $canonical) {
            if ($normalized === $alias) {
                return ['base' => $canonical, 'suffix' => ''];
            }

            foreach (['+', '-'] as $delimiter) {
                $prefix = $alias . $delimiter;
                if (str_starts_with($normalized, $prefix)) {
                    return ['base' => $canonical, 'suffix' => substr($normalized, strlen($alias))];
                }
            }
        }

        foreach (self::FORMAT_CANDIDATES as $candidate) {
            if ($normalized === $candidate) {
                return ['base' => self::FORMAT_ALIASES[$candidate] ?? $candidate, 'suffix' => ''];
            }

            foreach (['+', '-'] as $delimiter) {
                $prefix = $candidate . $delimiter;
                if (str_starts_with($normalized, $prefix)) {
                    return [
                        'base' => self::FORMAT_ALIASES[$candidate] ?? $candidate,
                        'suffix' => substr($normalized, strlen($candidate)),
                    ];
                }
            }
        }

        return ['base' => null, 'suffix' => ''];
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
        return self::markdownExtensionOverrides($format)[$extension] ?? $default;
    }
}
