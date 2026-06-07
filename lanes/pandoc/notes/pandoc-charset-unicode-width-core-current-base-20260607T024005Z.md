# Pandoc Charset/Unicode Width Core - ISO-8859-14 Latin-8 Source Bytes

Slice: `pandoc-charset-unicode-width-core-current-base-20260607T024005Z`
Base: `9fb6438305e1aff448029b6b81d9401e25f2c3f3`

## Change

- Added bounded ISO-8859-14 / Latin-8 label recognition to `UnicodeText`,
  including `iso-ir-199`, `latin8`, `latin-8`, `l8`, and `iso-celtic`
  aliases while preserving canonical source metadata as `iso-8859-14`.
- Added native single-byte decoding for the Latin-8 Celtic special byte slots
  used by legacy Markdown imports, including dotted consonants and Welsh W/Y
  circumflex characters.
- Extended the WordPress charset handoff smoke with an ISO-8859-14 audit row
  carrying decoded text, canonical source encoding, and display-width evidence.

## Source Truth

The bounded source-truth contract is the Unicode Consortium mapping table for
ISO/IEC 8859-14:1998 to Unicode:
`https://www.unicode.org/Public/MAPPINGS/ISO8859/8859-14.TXT`. ASCII and C1
bytes pass through as the mapped control range, common Latin-1 byte slots stay
on their Unicode equivalents, and the Latin-8-specific high-byte slots map to
the Celtic/Welsh Unicode characters listed in that table. This slice does not
ingest generated charset indexes or use external charset converters for
progress.

No current-base Pandoc rework note was present. No Pandoc, Cabal solver/build/
test command, Haskell runner, external charset converter, browser renderer,
online sanitizer, online service, live provider test, or live-service provider
test was executed.

## Red-First Evidence

Baseline before adding the new focused case:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 650 assertions, 0 failures`

After adding the ISO-8859-14 focused case and before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 651 assertions, 1 failures`
  - Failure: `UnicodeText::decodeBytes(..., 'iso-ir-199')` returned canonical
    `utf-8-repaired` instead of `iso-8859-14`.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 661 assertions, 0 failures`
  - Delta: `+1` focused PASS case / `+11` assertions.
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
`MarkdownReader`, `WordPressBlockWriter`, the existing WordPress charset
handoff example, and the lane-local focused PHP harness. Windows-1254/1257,
KOI8-U variants, remaining ISO-8859 variants, declared HTML/XML charset
sniffing, bidi layout shaping, terminal-profile-specific width variants, and
full upstream Pandoc Haskell runner parity remain separate bounded follow-up
work.

## Non-Overlap

This does not overlap accepted UTF-8 repair, UTF-16/UTF-32 BOM handling,
Windows-1252/1250/1251/1253, ISO-8859-1/2/3/4/5/6/7/8/9/10/13/15, TIS-620,
MacRoman, KOI8-R, Shift_JIS/Windows-31J, EUC-JP, ISO-2022-JP, Big5, GBK/
GB18030, EUC-KR, HZ-GB-2312, Unicode normalization, emoji presentation and
tag/ZWJ clusters, supplementary/rare East Asian wide ranges, BMP/geometric
emoji symbols, ambiguous-width policy, Unicode soft-break wrapping, Unicode
separator wrapping, default-ignorable controls, prepended format-control
zero-width accounting, Indic virama clusters, Myanmar/Khmer conjuncts, Thai/Lao
Sara Am grapheme slicing, Markdown/HTML reader behavior, XML/HTML5 DOM, table
geometry, DOCX/ODF/EPUB/PDF, syntax-highlighting, CSL/BibTeX, YAML,
doctemplate, ZIP/OPC, or upstream-runner dependency audit slices.
