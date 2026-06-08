# Pandoc Charset/Unicode Width Core Current-Base Tab Stops

Slice: `pandoc-charset-unicode-width-core-current-base-20260608T073242Z`
Base: `c0961f7d76e4f4ac51c31452364f795d95eceddf`

## Scope

This slice owns one bounded Unicode display-width behavior: literal tab
characters now expand to the next four-column tab stop for the native PHP
display-width helper surface used by Markdown table padding, display-width
breakpoint splitting, and WordPress charset handoff audit rows.

The red baseline was local and focused: before the patch,
`UnicodeText::displayWidth("A\tB")` returned `2`, which treated the tab as
invisible and caused downstream width/padding code to undercount tabbed review
text. The lane inventory already records Pandoc fixture behavior where tabs are
expanded at four-space/default tab stops in Markdown-reader and HTML-reader
cases. This patch ports that format contract into the bounded display-width
support library without shelling out to Pandoc or a Haskell runner.

## Implementation

- Added a four-column tab-stop policy to `UnicodeText::displayWidth()` and
  `UnicodeText::splitAtDisplayWidth()` by making tab cluster width depend on
  the current display column.
- Left other C0/C1 controls at zero display width.
- Added focused coverage for tab width, breakpoint splitting, left padding,
  and Markdown pipe-table width accounting.
- Added a WordPress charset handoff audit row for tab-stop slices.

## Evidence

Baseline before the implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
1 test files, 795 assertions, 0 failures
```

Focused verification after the implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php
1 test files, 804 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test
charset unicode handoff self-test ok
```

Expected dashboard movement:

- `phpPass`: `1564 -> 1565`
- mapped denominator: `1985 -> 1986`
- `charsetUnicodeWidthCoreCases`: `9 -> 10`
- `charsetUnicodeWidthCoreAssertions`: `65 -> 74`

## Dependency Closure

No new support component is needed. This reuses the existing native
`UnicodeText` display-width helpers, `MarkdownWriter` table padding,
`WordPressBlockWriter`, focused `UnicodeTextTest.php`, and the lane-local
charset Unicode handoff example.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Stack,
external charset converter, browser renderer, online service, live provider
test, or live-service provider test was executed.

## Non-Overlap

This does not repeat the accepted charset slices for ISO-8859 family byte
decoders, Windows/Korean/Japanese/Chinese byte decoders, emoji presentation,
ambiguous East Asian width, Unicode separator wrapping, format controls,
Indic virama clusters, Myanmar/Khmer conjuncts, Thai/Lao AM clusters, or
default-ignorable zero-width accounting. It owns only column-sensitive tab-stop
display-width accounting in the current-base Unicode support library.
