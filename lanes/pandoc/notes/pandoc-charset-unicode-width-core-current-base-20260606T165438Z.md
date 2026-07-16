# Pandoc Charset/Unicode Width Core - ISO-8859-4 Latin-4 Source Bytes

Slice: `pandoc-charset-unicode-width-core-current-base-20260606T165438Z`
Base: `5b1009757b1754da91812e308050cf22a7c1fb8d`

## Change

- Added bounded ISO-8859-4 / Latin-4 label recognition to `UnicodeText`,
  including `iso-ir-110`, `latin4`, `latin-4`, `l4`, and `csisolatin4`
  aliases while preserving canonical source metadata as `iso-8859-4`.
- Added native single-byte decoding for the Baltic/Nordic special byte slots
  used by legacy Markdown imports, including A-ogonek, kra, R-cedilla,
  I-tilde, L-cedilla, S-caron, E-macron, G-cedilla, T-stroke, Z-caron,
  eng, A-macron, I-ogonek, C-caron, E-ogonek, E-dot, I-macron, D-stroke,
  N-cedilla, O-macron, K-cedilla, U-ogonek, U-tilde, and U-macron pairs.
- Extended the WordPress charset handoff smoke with a Latin-4 audit row
  carrying decoded text, canonical source encoding, and display-width evidence.

## Source Truth

The bounded source-truth contract is the ISO/IEC 8859-4 Latin-4 single-byte
layout: ASCII and unchanged Latin-1-compatible slots pass through unchanged,
the Latin-4 high-byte special slots map to their Unicode Baltic/Nordic code
points, and no generated charset indexes or external charset converters are
used as progress.

No current-base Pandoc rework note was present. No Pandoc, Cabal solver/build/
test command, Haskell runner, external charset converter, browser renderer,
online sanitizer, online service, live provider test, or live-service provider
test was executed.

## Red-First Evidence

Baseline before adding the new focused case:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 592 assertions, 0 failures`

After adding the ISO-8859-4 focused case and before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 593 assertions, 1 failures`
  - Failure: `UnicodeText::decodeBytes(..., 'iso-ir-110')` returned canonical
    `utf-8-repaired` instead of `iso-8859-4`.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 602 assertions, 0 failures`
  - Delta: `+1` focused PASS case / `+10` assertions.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: no syntax errors.
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
  - Result: both Pandoc JSON files decoded successfully.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`UnicodeText`, `MarkdownReader`, `MarkdownWriter`, `WordPressBlockWriter`, the
existing WordPress charset handoff example, and the lane-local focused PHP
harness. ISO-8859-10/13/14/16, KOI8-U variants, Windows-874, generated full
charset indexes, declared HTML/XML charset sniffing, bidi layout shaping,
terminal-profile-specific width variants, and full upstream Pandoc Haskell
runner parity remain separate bounded follow-up work.

## Non-Overlap

This does not overlap accepted UTF-8 repair, UTF-16/UTF-32 BOM handling,
Windows-1252/1250/1251, ISO-8859-1/2/3/5/6/7/8/15, TIS-620, MacRoman,
KOI8-R, Shift_JIS/Windows-31J, EUC-JP, ISO-2022-JP, Big5, GBK/GB18030,
EUC-KR, HZ-GB-2312, Unicode normalization, emoji presentation and tag/ZWJ
clusters, supplementary/rare East Asian wide ranges, BMP/geometric emoji
symbols, ambiguous-width policy, Unicode soft-break wrapping, Unicode
separator wrapping, default-ignorable controls, prepended format-control
zero-width accounting, Indic virama clusters, Myanmar/Khmer conjuncts,
Thai/Lao Sara Am grapheme slicing, Markdown/HTML reader behavior,
XML/HTML5 DOM, table geometry, DOCX/ODF/EPUB/PDF, syntax-highlighting,
CSL/BibTeX, YAML, doctemplate, ZIP/OPC, or upstream-runner dependency audit
slices.
