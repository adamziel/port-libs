# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T200841Z`

Base accepted HEAD: `b04f57c7230c881432b7183ac804ada5839368dd`

## Behavior Added

- Extended `UnicodeText::decodeBytes()` to recognize bounded GBK-family labels:
  `gbk`, `gb18030`, `gb2312`, `cp936`, `ms936`, `windows-936`, `x-gbk`,
  `x-cp936`, `euc-cn`, and common GB2312 aliases.
- Added a native PHP bounded GBK decoder for the Simplified Chinese review
  packet pairs used by the focused fixture: `简体`, `中文`, `测试`, `，`,
  `北京`, and `。`.
- Preserved canonical `gbk` source metadata through `MarkdownReader` and the
  WordPress block handoff.
- Preserved malformed lead-byte and unmapped valid-pair repair counts as
  U+FFFD so bad imported bytes remain visible and ASCII delimiters are not
  swallowed by repair.
- Extended the WordPress charset handoff smoke with a `GBK source` audit row
  carrying decoded text, canonical source encoding, and display-width
  accounting.

## Source Truth

The bounded source truth is the WHATWG Encoding Standard GB18030/GBK decoder
and its GB18030 index mapping for the selected two-byte pairs. This slice
intentionally does not add full generated WHATWG index ingestion, GB18030
four-byte ranges, EUC-KR/UHC, HZ-GB-2312, declared HTML/XML charset sniffing,
or terminal-specific width variants.

No current-base Pandoc rework note was present. No Pandoc, Cabal solver/build,
Haskell runner, external charset converter, browser renderer, online sanitizer,
or online service was executed as conversion progress.

## Verification

Pre-slice focused baseline from the accepted UnicodeText test before this case:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 407 assertions, 0 failures`

Red-first focused check after adding the GBK test and before the implementation:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 408 assertions, 1 failures`
  - Failure: `UnicodeText::decodeBytes(..., 'gb18030')` returned
    `utf-8-repaired` instead of canonical `gbk`.

Post-implementation focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 420 assertions, 0 failures`
  - Delta: `+13` focused assertions and `+1` focused PHP PASS case.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`

Additional verification:

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

No new support component is needed. The slice extends the existing native PHP
`UnicodeText` helper and reuses `MarkdownReader`, `WordPressBlockWriter`, the
WordPress charset handoff example, and the focused PHP test harness. Full
upstream runner parity remains gated on a hydrated Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` and locally buildable Haskell Tasty
executables for `test-pandoc` and `test-pandoc-lua-engine`.

## Non-Overlap

This patch does not repeat accepted XML/HTML5 DOM support, YAML metadata,
ZIP/OPC, relationship graphs, gzip/tar/LZ4 streams, doctemplate partials,
CSL/BibTeX processing, DOCX/ODT parsing, EPUB3 package handoff, legacy DOC/CFB
extraction, table geometry span normalization, Math/TeX conversion, PDF engine
handoff planning, UTF-8/UTF-16/UTF-32 BOM handling, Windows-1252,
Windows-1250, ISO-8859-1, ISO-8859-2, ISO-8859-15 / Latin-9, MacRoman,
malformed UTF-8 repair, Shift_JIS/Windows-31J decoding, EUC-JP decoding,
ISO-2022-JP decoding, Big5 decoding, Unicode normalization, display-width
breakpoint splitting, display-column wrapping, emoji presentation width,
keycap/regional/tag emoji sequence width, emoji skin-tone modifier width,
emoji ZWJ variation width, supplementary/rare East Asian wide scripts and
symbols, BMP/geometric emoji symbols, decomposed Hangul Jamo width,
Indic/Thai/Lao grapheme handling, default-ignorable control width accounting,
East Asian ambiguous-width policy, Unicode soft-break wrapping, Unicode
separator wrapping, prepended format-control zero-width accounting, or
upstream-runner dependency audit work.

It only extends the charset/Unicode primitive with bounded GBK Simplified
Chinese byte decoding and WordPress handoff evidence.

## Follow-Up

Keep full generated WHATWG index ingestion, GB18030 four-byte ranges,
EUC-KR/UHC, HZ-GB-2312, declared HTML/XML charset sniffing,
terminal-profile-specific emoji width variants, broader Unicode width table
refreshes, and full upstream Pandoc Haskell runner parity as separate bounded
slices.
