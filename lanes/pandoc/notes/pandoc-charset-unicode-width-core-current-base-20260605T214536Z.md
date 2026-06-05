# Pandoc Charset/Unicode Width Core - Indic Virama Display Clusters

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T214536Z`
Base: `66d0408b47061a698c7ebd40ce9acc8de4ae0df1`

## Change

- Extended `UnicodeText::graphemes()` with bounded Indic virama linking so
  Devanagari, Bengali, and Tamil consonant conjuncts stay intact during
  display-width slicing.
- Counted bounded virama-linked consonant clusters as one display cell for
  padding and wrapping, matching the native support contract already used for
  Indic spacing-mark clusters.
- Added a WordPress charset handoff audit row for Indic virama clusters.

## Source Truth

The source-truth contract is Unicode extended grapheme behavior for
virama-linked Indic consonant clusters as needed by Pandoc-style display-width
wrapping and table padding. This slice stays bounded to native PHP support for
Devanagari, Bengali, and Tamil review text and does not ingest full Unicode
property tables.

No Pandoc, Cabal solver/build/test command, Haskell runner, external charset
converter, browser renderer, online sanitizer, online service, or live provider
test was executed.

## Red-First Evidence

Before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
- Result: `1 test files, 451 assertions, 1 failures`
- Failing case: `keeps indic virama conjuncts intact for display slicing`
- Failure: Devanagari `क्‍ष`-style virama conjunct width was counted as `2`
  instead of `1`.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 461 assertions, 0 failures`
  - Delta: +1 focused PASS case / +11 assertions.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`UnicodeText`, `MarkdownWriter`, and WordPress charset handoff path. Full
Unicode grapheme-break property tables, generated charset indexes,
terminal-profile-specific emoji width policies, HTML/XML charset sniffing, and
locale-specific line breaking remain separate bounded follow-up work.

## Non-Overlap

This does not overlap accepted UTF-8 repair, UTF-16/UTF-32 BOM handling,
single-byte legacy encodings, Shift_JIS/Windows-31J, EUC-JP, ISO-2022-JP,
Big5, GBK, EUC-KR, HZ-GB-2312, Unicode normalization, emoji presentation and
tag/ZWJ clusters, supplementary East Asian wide ranges, ambiguous-width policy,
Unicode separator wrapping, default-ignorable controls, prepended format-control
zero-width accounting, Markdown/HTML reader behavior, table geometry,
DOCX/ODF/EPUB/PDF, syntax-highlighting, CSL/BibTeX, YAML, doctemplate, ZIP/OPC,
or upstream-runner dependency audit slices.
