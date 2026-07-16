# Pandoc Charset/Unicode Width Core - Yijing Hexagram Narrow Width

Slice: `pandoc-charset-unicode-width-core-current-base-20260606T074628Z`
Base: `661160931d6ddcf5c9e08d340a57ba9219d9fb2e`

## Change

- Updated `UnicodeText::displayWidth()` wide-range classification so
  U+4DC0..U+4DFF Yijing hexagram symbols stay narrow inside the broad
  U+2E80..U+A4CF CJK shortcut.
- Added a focused display-width case covering Yijing hexagrams next to a Han
  character, including split, pad, and wrap behavior.
- Extended the WordPress charset/unicode review-table handoff with a Yijing
  hexagram audit row showing narrow hexagrams and double-width Han side by
  side.

## Source Truth

Pandoc's pinned `Text.Pandoc.Shared.splitTextByIndices` delegates display
columns to `Text.DocLayout.charWidth`; the upstream doclayout source marks
U+4DC0..U+4DFF as `narrowState` for "Hexagrams" even though the adjacent Han
range remains wide. This slice ports that bounded contract into the native PHP
width helper without running Pandoc or a Haskell runner.

Source references inspected:

- `https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Shared.hs`
- `https://hackage-content.haskell.org/package/doclayout-0.5.0.1/docs/src/Text.DocLayout.html`

## Red-First Evidence

Baseline before adding the new focused case:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 528 assertions, 0 failures`

After adding the Yijing hexagram test and before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 529 assertions, 1 failures`
  - Failure: `UnicodeText::displayWidth()` returned `6` for three Yijing
    hexagrams instead of the upstream-compatible narrow width `3`.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 536 assertions, 0 failures`
  - Delta: `+1` focused PASS case / `+8` assertions.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`UnicodeText`, `MarkdownReader`, `MarkdownWriter`, `WordPressBlockWriter`, the
focused UnicodeText test, and the existing WordPress charset handoff example.
Full generated Unicode width table refreshes, terminal-profile-specific emoji
width variants, broader non-UTF charset families, HTML/XML declared-charset
sniffing, and full upstream Pandoc Haskell runner parity remain separate
bounded follow-up work.

## Non-Overlap

This does not overlap accepted UTF-8 repair, UTF-16/UTF-32 BOM handling,
Windows-1252/1250/1251, ISO-8859-1/2/5/15, MacRoman, KOI8-R, Shift_JIS/
Windows-31J, EUC-JP, ISO-2022-JP, Big5, GBK/GB18030, EUC-KR, HZ-GB-2312,
Unicode normalization, emoji presentation/tag/ZWJ clusters, supplementary and
rare East Asian wide ranges, Kana Extended-B, BMP/geometric emoji symbols,
ambiguous-width policy, Unicode soft-break wrapping, Unicode separator
wrapping, default-ignorable controls, prepended format-control zero-width
accounting, Indic virama clusters, Myanmar/Khmer conjuncts, Thai/Lao Sara Am
clusters, Markdown/HTML reader behavior, XML/HTML5 DOM, table geometry, DOCX,
ODF, EPUB, PDF, syntax-highlighting, CSL/BibTeX, YAML, doctemplate, ZIP/OPC,
or upstream-runner dependency audit slices.
