# Pandoc Charset/Unicode Width Core - ISO-8859-10 Latin-6 Source Bytes

Slice: `pandoc-charset-unicode-width-core-current-base-20260606T183434Z`
Base: `8d02d2b80dc4ec1dcde2ffb3e63387fccc05cf0a`

## Change

- Added bounded ISO-8859-10 / Latin-6 label recognition to `UnicodeText`,
  including `iso-ir-157`, `latin6`, `latin-6`, `l6`, and `csisolatin6`
  aliases while preserving canonical source metadata as `iso-8859-10`.
- Added native single-byte decoding for the Latin-6 Nordic, Sami, Greenlandic,
  and Baltic byte slots used by legacy Markdown imports.
- Preserved existing East Asian ambiguous-width policy for Latin-6 characters
  such as `Ŧ`, `ŧ`, `Ŋ`, `ŋ`, `ĸ`, `ē`, `ū`, and horizontal bar in wide mode.
- Extended the WordPress charset handoff smoke with an ISO-8859-10 audit row
  carrying decoded text, canonical source encoding, and narrow/wide display
  width evidence.

## Source Truth

The bounded source-truth contract is the ISO/IEC 8859-10 Latin-6 single-byte
layout: ASCII and unchanged Latin-1 slots pass through unchanged, while Latin-6
replacement byte slots map to Unicode Nordic, Sami, Greenlandic, Baltic, and
horizontal-bar characters. This slice does not ingest generated charset indexes
or use external charset converters for progress.

No current-base Pandoc rework note was present. No Pandoc, Cabal solver/build/
test command, Haskell runner, external charset converter, browser renderer,
online sanitizer, online service, live provider test, or live-service provider
test was executed.

## Red-First Evidence

Baseline before adding the new focused case:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 602 assertions, 0 failures`

After adding the ISO-8859-10 focused case and before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 603 assertions, 1 failures`
  - Failure: `UnicodeText::decodeBytes(..., 'iso-ir-157')` returned canonical
    `utf-8-repaired` instead of `iso-8859-10`.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 614 assertions, 0 failures`
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
ISO-8859-10 is now handled without Pandoc, Cabal/Haskell runners, external
charset converters, browser renderers, or online services.

Remaining follow-up work stays separate: ISO-8859-13/14/16, KOI8-U variants,
Windows-874 aliases beyond TIS-620, generated full charset indexes, declared
HTML/XML charset sniffing, bidi layout shaping, terminal-profile-specific
width variants, and full upstream Pandoc Haskell runner parity.

## Non-Overlap

This does not overlap accepted UTF-8 repair, UTF-16/UTF-32 BOM handling,
Windows-1252/1250/1251, ISO-8859-1/2/3/4/5/6/7/8/15, TIS-620, MacRoman,
KOI8-R, Shift_JIS/Windows-31J, EUC-JP, ISO-2022-JP, Big5, GBK/GB18030,
EUC-KR, HZ-GB-2312, Unicode normalization, emoji presentation and tag/ZWJ
clusters, supplementary/rare East Asian wide ranges, BMP/geometric emoji
symbols, ambiguous-width policy, Unicode soft-break wrapping, Unicode separator
wrapping, default-ignorable controls, prepended format-control zero-width
accounting, Indic virama clusters, Myanmar/Khmer conjuncts, Thai/Lao Sara Am
grapheme slicing, Markdown/HTML reader behavior, XML/HTML5 DOM, table geometry,
DOCX/ODF/EPUB/PDF, syntax-highlighting, CSL/BibTeX, YAML, doctemplate, ZIP/OPC,
or upstream-runner dependency audit slices.
