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
        if (!self::available()) {
            return null;
        }

        try {
            /** @var object|null $processor */
            $processor = \WP_HTML_Processor::create_full_parser($html);
            if ($processor === null) {
                return null;
            }

            $tokens = [];
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
                    if (isset($transparentRootDepth[$name]) && $attributes === []) {
                        $transparentRootDepth[$name]++;
                        continue;
                    }
                    $tokens[] = TagSoupTag::open($name, $attributes);
                    continue;
                }

                if ($type === '#text' || $type === '#cdata-section') {
                    $tokens[] = TagSoupTag::text($processor->get_modifiable_text());
                    continue;
                }

                if ($type === '#comment' || $type === '#funky-comment') {
                    $tokens[] = TagSoupTag::comment($processor->get_modifiable_text());
                }
            }

            return $tokens;
        } catch (\Throwable) {
            // A WordPress HTML API failure must not make a standalone import
            // less robust than the established TagSoup reader.
            return null;
        }
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
