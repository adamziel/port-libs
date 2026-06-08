# Pandoc Charset/Unicode Width Core - I Ching and Counting Symbols

Slice: `pandoc-charset-unicode-width-core-current-base-20260608T121717Z`
Base accepted HEAD: `a00e14f093dc188f213b61df223920efd39f90c6`

## Behavior

- Extended `UnicodeText::displayWidth()` East Asian Wide classification for
  bounded symbolic ranges that ICU reports as wide:
  - U+2630..U+2637 I Ching trigrams.
  - U+268A..U+268F monogram/digram symbols.
  - U+1D300..U+1D356 Tai Xuan Jing symbols.
  - U+1D360..U+1D376 counting rod and ideographic tally symbols.
- Preserved combining tone marks and default-ignorable controls as zero-width
  where the existing Unicode helper already treats them as combining/control
  clusters.
- Added focused coverage for display width, display breakpoints,
  `splitAtDisplayWidth()`, wrapping, padding, and Markdown table padding.
- Extended the WordPress charset handoff example with an
  `I Ching/counting wide` audit row.

## Source Truth

The local source-truth probe used PHP ICU `IntlChar::PROPERTY_EAST_ASIAN_WIDTH`
only to identify code points with `EA_WIDE` classification. No Pandoc runner,
Cabal solver/build/test command, Haskell runner, Stack, external charset
converter, browser renderer, online service, live provider test, or
live-service provider test was executed.

## Red-First Evidence

Baseline focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
1 test files, 830 assertions, 0 failures
```

After adding the focused test and before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
1 test files, 831 assertions, 1 failures
```

Failure: `UnicodeText::displayWidth("\u{2630}\u{2637}")` expected `4`, actual
`2`.

Final focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
1 test files, 842 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test
charset unicode handoff self-test ok
```

Expected dashboard movement:

- `phpPass`: `1638 -> 1639`
- mapped denominator: `2058 -> 2059`
- `charsetUnicodeWidthCoreCases`: `9 -> 10`
- `charsetUnicodeWidthCoreAssertions`: `65 -> 77`

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`UnicodeText`, `MarkdownWriter`, focused `UnicodeTextTest.php`, and the
WordPress charset Unicode handoff example.

## Non-Overlap

This does not repeat accepted charset slices for tabs, CP437, x-user-defined,
ISO-8859 family decoders, Windows code-page decoders, Japanese/Chinese/Korean
decoders, emoji presentation, ambiguous-width policy, Unicode separators,
format controls, default ignorables, Indic virama clusters, Myanmar/Khmer
conjuncts, Thai/Lao AM clusters, or supplementary CJK/Kana/emoji wide ranges.
It owns only bounded East Asian Wide divination/counting symbols used by native
display-width and Markdown/WordPress handoff paths.
