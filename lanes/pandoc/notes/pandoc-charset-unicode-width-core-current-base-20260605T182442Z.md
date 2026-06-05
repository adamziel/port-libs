# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T182442Z`

Base accepted HEAD: `d18d8b7d427ab62c97ee01acf72ba9cfa535c34b`

## Behavior Added

- Extended `UnicodeText::decodeBytes()` to recognize bounded ISO-2022-JP
  labels: `iso-2022-jp`, `iso2022jp`, and `csiso2022jp`.
- Added a native ISO-2022-JP escape-state decoder for:
  - ASCII designation with `ESC ( B`;
  - Roman designation with `ESC ( J`, including backslash to U+00A5 and tilde
    to U+203E;
  - halfwidth Katakana designation with `ESC ( I`;
  - JIS X 0208 designation with `ESC $ @` and `ESC $ B`, reusing the bounded
    PHP JIS0208 pointer subset already used by Shift_JIS and EUC-JP slices.
- Preserved malformed escape and malformed JIS0208 trail-byte repair counts so
  broken review packets expose U+FFFD while keeping following ASCII visible.
- Extended the WordPress charset handoff smoke with an `ISO-2022-JP source`
  audit row carrying decoded text, canonical source encoding, and narrow/wide
  display-width accounting.

## Source Truth

The bounded source truth is the WHATWG Encoding Standard ISO-2022-JP label and
decoder model, especially its ASCII/Roman/Katakana/JIS0208 state transitions
and Roman yen/overline remapping. This slice intentionally does not add a full
generated JIS0208/JIS0212 table or ISO-2022-JP-2/-3 variants.

The fixture initially included the CP932-only `Takao` glyph pointer that is
valid for the existing Shift_JIS bounded slice but not representable in
standard 7-bit ISO-2022-JP JIS X 0208. The final fixture uses only pointers
already present in the bounded native JIS0208 table.

No current-base Pandoc rework note was present. No Pandoc, Cabal solver/build,
Haskell runner, external charset converter, browser renderer, online sanitizer,
or online service was executed.

## Verification

Pre-slice focused baseline:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 364 assertions, 0 failures`

Red-first focused check after adding the ISO-2022-JP test and before the
implementation:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 365 assertions, 1 failures`
  - Failure: `UnicodeText::decodeBytes(..., 'csiso2022jp')` returned
    `utf-8-repaired` instead of canonical `iso-2022-jp`.

Post-implementation focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 378 assertions, 0 failures`
  - Delta: `+14` focused assertions and `+1` focused PHP PASS case.
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
`UnicodeText` helper and reuses `MarkdownReader`, `MarkdownWriter`, `AstNode`,
and `WordPressBlockWriter`. Full upstream runner parity remains gated on a
hydrated Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` and
locally buildable Haskell Tasty executables for `test-pandoc` and
`test-pandoc-lua-engine`.

## Non-Overlap

This patch does not repeat accepted XML/HTML5 DOM support, YAML metadata,
ZIP/OPC, relationship graphs, gzip/tar/LZ4 streams, doctemplate partials,
CSL/BibTeX processing, DOCX/ODT parsing, EPUB3 package handoff, legacy DOC/CFB
extraction, table geometry span normalization, Math/TeX conversion, PDF engine
handoff planning, UTF-8/UTF-16/UTF-32 BOM handling, Windows-1252,
ISO-8859-1, ISO-8859-15 / Latin-9, MacRoman, malformed UTF-8 repair,
Shift_JIS/Windows-31J decoding, EUC-JP decoding, Unicode normalization,
display-width breakpoint splitting, display-column wrapping, emoji
presentation width, keycap/regional/tag emoji sequence width, emoji skin-tone
modifier width, emoji ZWJ variation width, supplementary/rare East Asian wide
scripts and symbols, BMP/geometric emoji symbols, decomposed Hangul Jamo
width, Indic/Thai/Lao grapheme handling, default-ignorable control width
accounting, East Asian ambiguous-width policy, Unicode soft-break wrapping,
Unicode separator wrapping, prepended format-control zero-width accounting, or
upstream-runner dependency audit work.

It only extends the charset/Unicode primitive with bounded ISO-2022-JP
escape-state byte decoding and WordPress handoff evidence.

## Follow-Up

Keep full generated JIS0208/JIS0212 tables, ISO-2022-JP-2/-3 variants,
HTML/XML charset sniffing, terminal-profile-specific width variants, generated
Unicode data refreshes, and full upstream Pandoc Haskell runner parity as
separate bounded slices.
