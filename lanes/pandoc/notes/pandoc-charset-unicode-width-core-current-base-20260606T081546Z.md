# Pandoc Charset/Unicode Width Core - ISO-8859-7 Greek Source Bytes

Slice: `pandoc-charset-unicode-width-core-current-base-20260606T081546Z`
Base: `ebe3852fd7a4b86c1c6805bcbe033ba165d43ceb`

## Change

- Added bounded ISO-8859-7 label recognition for Greek source imports,
  including `iso-ir-126`, `greek`, `greek8`, `elot928`, `ecma118`, and
  `csisolatingreek` aliases while preserving canonical metadata as
  `iso-8859-7`.
- Added a native ISO-8859-7 byte table for the Greek punctuation and letter
  rows used by legacy Markdown sources, including tonos/dialytika marks,
  uppercase/lowercase Greek letters, final sigma, drachma/euro symbols, and
  guillemets.
- Kept undefined ISO-8859-7 byte slots `0xAE`, `0xD2`, and `0xFF` as explicit
  replacement repairs before Markdown or WordPress handoff.
- Extended the WordPress charset handoff smoke with an ISO-8859-7 Greek audit
  row carrying canonical source encoding plus narrow/wide display-width
  evidence.

## Source Truth

The bounded source-truth contract is the ISO-8859-7 single-byte Greek layout:
ASCII bytes pass through unchanged, bytes `0xA0` through `0xFE` map through the
Greek punctuation and letter table except the undefined slots, and undefined
slots become U+FFFD repairs. This slice does not ingest generated charset
indexes or use external charset converters for progress.

No current-base pandoc rework note was present. No Pandoc, Cabal solver/build/
test command, Haskell runner, external charset converter, browser renderer,
online sanitizer, online service, live provider test, or live-service provider
test was executed.

## Red-First Evidence

Baseline before adding the new focused case:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 528 assertions, 0 failures`

After adding the ISO-8859-7 focused case and before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 529 assertions, 1 failures`
  - Failure: `UnicodeText::decodeBytes(..., 'iso-ir-126')` returned canonical
    `utf-8-repaired` instead of `iso-8859-7`.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 541 assertions, 0 failures`
  - Delta: `+1` focused PASS case / `+13` assertions.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`UnicodeText`, `MarkdownReader`, `MarkdownWriter`, `WordPressBlockWriter`, the
charset handoff example, and lane-local manifest/status machinery. Additional
single-byte families such as ISO-8859-3/4/6/8/10/13/14/16, declared HTML/XML
charset sniffing, full generated charset indexes, terminal-profile-specific
width variants, broader Unicode property-table refreshes, and full upstream
Pandoc Haskell runner parity remain separate bounded follow-up work.

## Non-Overlap

This does not overlap accepted UTF-8 repair, UTF-16/UTF-32 BOM handling,
Windows-1252/1250/1251, ISO-8859-1/2/5/15, MacRoman, KOI8-R, Shift_JIS/
Windows-31J, EUC-JP, ISO-2022-JP, Big5, GBK/GB18030, EUC-KR, HZ-GB-2312,
Unicode normalization, emoji presentation and tag/ZWJ clusters,
supplementary/rare East Asian wide ranges, BMP/geometric emoji symbols,
ambiguous-width policy, Unicode soft-break wrapping, Unicode separator
wrapping, default-ignorable controls, prepended format-control zero-width
accounting, Indic virama clusters, Myanmar/Khmer conjuncts, Markdown/HTML
reader behavior, XML/HTML5 DOM, table geometry, DOCX/ODF/EPUB/PDF,
syntax-highlighting, CSL/BibTeX, YAML, doctemplate, ZIP/OPC, or
upstream-runner dependency audit slices.
