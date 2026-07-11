<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Adapts WordPress's low-footprint HTML tree constructor to the token shape
 * consumed by the Pandoc HTML reader. The class is deliberately optional:
 * the standalone converter keeps working when WordPress is not bootstrapped.
 */
final class WpHtmlProcessorTokenStream
{
    public static function available(): bool
    {
        return class_exists('WP_HTML_Processor');
    }

    /**
     * @return list<TagSoupTag>|null
     */
    public static function tokenize(string $html): ?array
    {
        if (!self::available() || self::requiresSourceTokenCompatibility($html)) {
            return null;
        }

        try {
            /** @var object|null $processor */
            $processor = \WP_HTML_Processor::create_full_parser($html);
            if ($processor === null) {
                return null;
            }

            $tokens = [];
            $preserveExplicitRoot = [
                'html' => self::sourceContainsStartTag($html, 'html'),
                'body' => self::sourceContainsStartTag($html, 'body'),
            ];
            $transparentRootDepth = ['html' => 0, 'body' => 0];
            while ($processor->next_token()) {
                $type = $processor->get_token_type();
                if ($type === '#tag') {
                    $tag = $processor->get_tag();
                    if (!is_string($tag) || $tag === '') {
                        continue;
                    }
                    $name = self::canonicalTagName($tag);
                    if ($name === '') {
                        continue;
                    }
                    if ($processor->is_tag_closer()) {
                        if (($transparentRootDepth[$name] ?? 0) > 0) {
                            $transparentRootDepth[$name]--;
                            continue;
                        }
                        $tokens[] = TagSoupTag::close($name);
                        continue;
                    }

                    $attributes = self::attributes($processor);
                    if (isset($transparentRootDepth[$name]) && !$preserveExplicitRoot[$name] && $attributes === []) {
                        $transparentRootDepth[$name]++;
                        continue;
                    }
                    $tokens[] = TagSoupTag::open($name, $attributes);
                    if (self::rawTextTag($name)) {
                        $text = $processor->get_modifiable_text();
                        if ($text !== '') {
                            self::appendToken($tokens, TagSoupTag::text($text));
                        }
                        $tokens[] = TagSoupTag::close($name);
                    }
                    continue;
                }

                if ($type === '#text' || $type === '#cdata-section') {
                    self::appendToken($tokens, TagSoupTag::text($processor->get_modifiable_text()));
                    continue;
                }

                if ($type === '#comment' || $type === '#funky-comment') {
                    $tokens[] = TagSoupTag::comment($processor->get_modifiable_text());
                }
            }

            if ($processor->get_last_error() !== null) {
                return null;
            }

            return $tokens;
        } catch (\Throwable) {
            // A WordPress HTML API failure must not make a standalone import
            // less robust than the established TagSoup reader.
            return null;
        }
    }

    private static function rawTextTag(string $name): bool
    {
        return in_array($name, ['title', 'script', 'style', 'textarea', 'xmp'], true);
    }

    /**
     * The Pandoc reader owns source-token semantics for these structures.
     * WordPress intentionally performs HTML tree construction for them, which
     * can reparent, discard, or reinterpret source tokens before this reader
     * sees them. Use the established source tokenizer for those general
     * language constructs rather than changing imported output.
     */
    private static function requiresSourceTokenCompatibility(string $html): bool
    {
        if (preg_match(
            '/<\\s*\\/?\\s*(?:'
            . 'table|caption|colgroup|col|thead|tbody|tfoot|tr|td|th|'
            . 'template|svg|math|'
            . 'noscript|noembed|noframes|plaintext|object|iframe|applet|frameset|'
            . 'select|optgroup|option'
            . ')\\b/i',
            $html
        ) === 1) {
            return true;
        }

        // The HTML tree builder implicitly closes a paragraph before these
        // elements. The source-token reader owns that recovery itself, so use
        // it whenever the source actually crosses that boundary.
        return preg_match(
            '/<\\s*p\\b[^>]*>(?:(?!<\\s*\\/?\\s*p\\b).)*?<\\s*(?:'
            . 'address|article|aside|blockquote|center|details|dialog|dir|div|dl|fieldset|'
            . 'figcaption|figure|footer|form|h[1-6]|header|hgroup|hr|main|menu|nav|ol|p|pre|'
            . 'search|section|summary|table|ul'
            . ')\\b/is',
            $html
        ) === 1;
    }

    private static function sourceContainsStartTag(string $html, string $name): bool
    {
        return preg_match('/<\\s*' . preg_quote($name, '/') . '(?:[\\s\\/>])/i', $html) === 1;
    }

    /**
     * @param list<TagSoupTag> $tokens
     */
    private static function appendToken(array &$tokens, TagSoupTag $token): void
    {
        $previous = $tokens[array_key_last($tokens)] ?? null;
        if ($token->type === TagSoupTag::TEXT && $previous instanceof TagSoupTag && $previous->type === TagSoupTag::TEXT) {
            array_pop($tokens);
            $tokens[] = TagSoupTag::text($previous->text . $token->text);

            return;
        }

        $tokens[] = $token;
    }

    private static function canonicalTagName(string $name): string
    {
        $name = strtolower($name);
        $colon = strrpos($name, ':');

        return $colon === false ? $name : substr($name, $colon + 1);
    }

    /**
     * @return list<array{name:string,value:string}>
     */
    private static function attributes(object $processor): array
    {
        $names = $processor->get_attribute_names_with_prefix('');
        if (!is_array($names)) {
            return [];
        }

        $attributes = [];
        foreach ($names as $name) {
            if (!is_string($name) || $name === '') {
                continue;
            }
            $value = $processor->get_attribute($name);
            if ($value === null) {
                continue;
            }
            $attributes[] = [
                'name' => strtolower($name),
                'value' => $value === true ? '' : (string) $value,
            ];
        }

        return $attributes;
    }
}
