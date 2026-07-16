# Pandoc Charset/Unicode Width Core - ISO-8859-3 Latin-3 Source Bytes

Slice: `pandoc-charset-unicode-width-core-current-base-20260606T162320Z`
Base: `da8976a6177860f2d7f9e65f0e8f9d9d6aa53e65`

## Change

- Added bounded ISO-8859-3 / Latin-3 label recognition to `UnicodeText`,
  including `iso-ir-109`, `latin3`, `latin-3`, `l3`, and `csisolatin3`
  aliases while preserving canonical source metadata as `iso-8859-3`.
- Added native single-byte decoding for the Latin-3 Maltese, Esperanto, and
  Turkish special byte slots used by legacy Markdown imports.
- Kept the seven undefined ISO-8859-3 high-byte slots explicit as U+FFFD
  repairs before Markdown or WordPress handoff.
- Extended the WordPress charset handoff smoke with an ISO-8859-3 audit row
  carrying decoded text, canonical source encoding, and display-width evidence.

## Source Truth

The bounded source-truth contract is the ISO/IEC 8859-3 Latin-3 single-byte
layout: ASCII bytes pass through unchanged, the defined Latin-3 high-byte slots
map to Unicode Maltese, Esperanto, Turkish, and punctuation characters, and
undefined high-byte slots `0xA5`, `0xAE`, `0xBE`, `0xC3`, `0xD0`, `0xE3`, and
`0xF0` become U+FFFD repairs. This slice does not ingest generated charset
indexes or use external charset converters for progress.

No current-base Pandoc rework note was present. No Pandoc, Cabal solver/build/
test command, Haskell runner, external charset converter, browser renderer,
online sanitizer, online service, live provider test, or live-service provider
test was executed.

## Red-First Evidence

Baseline before adding the new focused case:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 580 assertions, 0 failures`

After adding the ISO-8859-3 focused case and before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 581 assertions, 1 failures`
  - Failure: `UnicodeText::decodeBytes(..., 'iso-ir-109')` returned canonical
    `utf-8-repaired` instead of `iso-8859-3`.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 592 assertions, 0 failures`
  - Delta: `+1` focused PASS case / `+12` assertions.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: no syntax errors.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `UnicodeText`,
`MarkdownReader`, `MarkdownWriter`, `WordPressBlockWriter`, the existing
WordPress charset handoff example, and the lane-local focused PHP harness.
ISO-8859-4/10/13/14/16, KOI8-U variants, Windows-874, generated full charset
indexes, declared HTML/XML charset sniffing, bidi layout shaping,
terminal-profile-specific width variants, and full upstream Pandoc Haskell
runner parity remain separate bounded follow-up work.

## Non-Overlap

This does not overlap accepted UTF-8 repair, UTF-16/UTF-32 BOM handling,
Windows-1252/1250/1251, ISO-8859-1/2/5/6/7/8/15, TIS-620, MacRoman, KOI8-R,
Shift_JIS/Windows-31J, EUC-JP, ISO-2022-JP, Big5, GBK/GB18030, EUC-KR,
HZ-GB-2312, Unicode normalization, emoji presentation and tag/ZWJ clusters,
supplementary/rare East Asian wide ranges, BMP/geometric emoji symbols,
ambiguous-width policy, Unicode soft-break wrapping, Unicode separator
wrapping, default-ignorable controls, prepended format-control zero-width
accounting, Indic virama clusters, Myanmar/Khmer conjuncts, Thai/Lao Sara Am
grapheme slicing, Markdown/HTML reader behavior, XML/HTML5 DOM, table geometry,
DOCX/ODF/EPUB/PDF, syntax-highlighting, CSL/BibTeX, YAML, doctemplate, ZIP/OPC,
or upstream-runner dependency audit slices.
