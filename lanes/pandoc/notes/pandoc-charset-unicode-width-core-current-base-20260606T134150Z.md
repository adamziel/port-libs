# Pandoc Charset/Unicode Width Core - ISO-8859-6 Arabic Source Bytes

Slice: `pandoc-charset-unicode-width-core-current-base-20260606T134150Z`
Base: `972d7696f9725a30feefbe40aa423dceb19ed0c3`

## Change

- Added bounded ISO-8859-6 Arabic label recognition to `UnicodeText`, including
  `iso-ir-127`, `arabic`, `asmo708`, `ecma114`, and `csisolatinarabic` aliases
  while preserving canonical source metadata as `iso-8859-6`.
- Added native single-byte decoding for the ISO-8859-6 Arabic punctuation,
  letters, tatweel, and harakat rows needed by legacy Arabic Markdown imports.
- Kept undefined ISO-8859-6 high-byte slots explicit as U+FFFD repairs before
  Markdown or WordPress handoff.
- Extended the WordPress charset handoff smoke with an ISO-8859-6 Arabic audit
  row carrying canonical source encoding and display-width evidence.

## Source Truth

The bounded source-truth contract is the ISO/IEC 8859-6 Arabic single-byte
layout: ASCII bytes pass through unchanged, bytes `0xA0`, `0xA4`, `0xAC`,
`0xAD`, `0xBB`, `0xBF`, `0xC1`-`0xDA`, and `0xE0`-`0xF2` map to Unicode Arabic
punctuation, letters, tatweel, and harakat, and undefined high-byte slots become
U+FFFD repairs. This slice does not ingest generated charset indexes or use
external charset converters for progress.

No current-base Pandoc rework note was present. No Pandoc, Cabal solver/build/
test command, Haskell runner, external charset converter, browser renderer,
online sanitizer, online service, live provider test, or live-service provider
test was executed.

## Red-First Evidence

Baseline before adding the new focused case:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 554 assertions, 0 failures`

After adding the ISO-8859-6 focused case and before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 555 assertions, 1 failures`
  - Failure: `UnicodeText::decodeBytes(..., 'iso-ir-127')` returned canonical
    `utf-8-repaired` instead of `iso-8859-6`.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 567 assertions, 0 failures`
  - Delta: `+1` focused PASS case / `+13` assertions.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: no syntax errors.
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
  - Result: both pandoc JSON files decoded successfully.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `UnicodeText`,
`MarkdownReader`, `MarkdownWriter`, `WordPressBlockWriter`, the existing
WordPress charset handoff example, and the lane-local focused PHP harness.
ISO-8859-3/4/10/13/14/16, KOI8-U variants, generated full charset indexes,
declared HTML/XML charset sniffing, bidi layout shaping, terminal-profile-
specific width variants, and full upstream Pandoc Haskell runner parity remain
separate bounded follow-up work.

## Non-Overlap

This does not overlap accepted UTF-8 repair, UTF-16/UTF-32 BOM handling,
Windows-1252/1250/1251, ISO-8859-1/2/5/7/8/15, MacRoman, KOI8-R,
Shift_JIS/Windows-31J, EUC-JP, ISO-2022-JP, Big5, GBK/GB18030, EUC-KR,
HZ-GB-2312, Unicode normalization, emoji presentation and tag/ZWJ clusters,
supplementary/rare East Asian wide ranges, BMP/geometric emoji symbols,
ambiguous-width policy, Unicode soft-break wrapping, Unicode separator
wrapping, default-ignorable controls, prepended format-control zero-width
accounting, Indic virama clusters, Myanmar/Khmer conjuncts, Markdown/HTML
reader behavior, XML/HTML5 DOM, table geometry, DOCX/ODF/EPUB/PDF,
syntax-highlighting, CSL/BibTeX, YAML, doctemplate, ZIP/OPC, or upstream-runner
dependency audit slices.
