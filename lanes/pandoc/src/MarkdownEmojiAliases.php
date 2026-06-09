<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class MarkdownEmojiAliases
{
    private const ALIASES = [
        '+1' => "\u{1F44D}",
        '-1' => "\u{1F44E}",
        'bug' => "\u{1F41B}",
        'eyes' => "\u{1F440}",
        'fire' => "\u{1F525}",
        'heart' => "\u{2764}\u{FE0F}",
        'heavy_check_mark' => "\u{2714}\u{FE0F}",
        'information_source' => "\u{2139}\u{FE0F}",
        'joy' => "\u{1F602}",
        'memo' => "\u{1F4DD}",
        'rocket' => "\u{1F680}",
        'smile' => "\u{1F604}",
        'sparkles' => "\u{2728}",
        'tada' => "\u{1F389}",
        'thinking' => "\u{1F914}",
        'thumbsdown' => "\u{1F44E}",
        'thumbsup' => "\u{1F44D}",
        'warning' => "\u{26A0}\u{FE0F}",
        'white_check_mark' => "\u{2705}",
        'x' => "\u{274C}",
        'zap' => "\u{26A1}",
    ];

    public static function glyphForAlias(string $alias): ?string
    {
        return self::ALIASES[$alias] ?? null;
    }

    public static function aliasMatchesGlyph(string $alias, string $glyph): bool
    {
        return (self::ALIASES[$alias] ?? null) === $glyph;
    }
}
