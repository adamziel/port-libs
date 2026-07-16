# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T203958Z`

Base accepted HEAD: `573d6fae38c151ba4ce645385f7be4f06788579c`

## Behavior Added

- Extended `UnicodeText::decodeBytes()` to recognize `hz-gb-2312` and `hz`
  labels as canonical `hz-gb-2312`.
- Added a bounded native HZ-GB-2312 state-machine decoder:
  - `~{` enters GB byte-pair mode and `~}` returns to ASCII mode;
  - `~~` emits a literal tilde;
  - `~\n` and `~\r\n` line continuations are suppressed;
  - GB byte pairs reuse the existing native GBK mapping table by adding the
    HZ 0x80 offset to each pair byte;
  - malformed lead bytes before `~}`, invalid escape sequences, non-ASCII
    ASCII-state bytes, and unmapped pairs produce U+FFFD with repair counts.
- Preserved canonical source-encoding metadata through `MarkdownReader` and
  WordPress block handoff output.
- Extended the WordPress charset handoff smoke with an `HZ-GB-2312 source`
  audit row and display-width accounting.

## Source Truth

The bounded behavior follows the HZ-GB-2312 escape-state structure used for
7-bit GB2312 text and reuses the already accepted native GBK pair table for the
selected Simplified Chinese review-packet pairs. This slice intentionally does
not add generated WHATWG index ingestion, full GB18030 four-byte ranges,
EUC-KR/UHC, declared HTML/XML charset sniffing, or terminal-profile-specific
width variants.

No current-base Pandoc rework note was present. No Pandoc, Cabal solver/build,
Haskell runner, external charset converter, browser renderer, online sanitizer,
or online service was executed as conversion progress.

## Verification

Pre-slice focused baseline:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 420 assertions, 0 failures`

Red-first focused check after adding the HZ-GB-2312 test and before the
implementation:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 421 assertions, 1 failures`
  - Failure: `UnicodeText::decodeBytes(..., 'hz-gb-2312')` returned `utf-8`
    instead of canonical `hz-gb-2312`.

Post-implementation focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 435 assertions, 0 failures`
  - Delta: `+15` focused assertions and `+1` focused PHP PASS case.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`

Additional verification:

- `php -l lanes/pandoc/src/UnicodeText.php`
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
- `git diff --check -- lanes/pandoc`

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
ISO-2022-JP decoding, Big5 decoding, GBK decoding, Unicode normalization,
display-width breakpoint splitting, display-column wrapping, emoji presentation
width, keycap/regional/tag emoji sequence width, emoji skin-tone modifier
width, emoji ZWJ variation width, supplementary/rare East Asian wide scripts
and symbols, BMP/geometric emoji symbols, decomposed Hangul Jamo width,
Indic/Thai/Lao grapheme handling, default-ignorable control width accounting,
East Asian ambiguous-width policy, Unicode soft-break wrapping, Unicode
separator wrapping, prepended format-control zero-width accounting, or
upstream-runner dependency audit work.

It only extends the charset/Unicode primitive with bounded HZ-GB-2312
Simplified Chinese byte decoding and WordPress handoff evidence.

## Follow-Up

Keep EUC-KR/UHC, full GB18030 four-byte ranges, generated WHATWG index
ingestion, declared HTML/XML charset sniffing, terminal-profile-specific emoji
width variants, broader Unicode width table refreshes, and full upstream Pandoc
Haskell runner parity as separate bounded slices.
