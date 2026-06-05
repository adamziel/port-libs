# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T112437Z`

Base accepted HEAD: `f092164f95c96f9a2bc590427ed0fbb5ae7a5e32`

## Behavior Added

- Extended `UnicodeText::decodeBytes()` to recognize ISO-8859-15 / Latin-9
  labels, including `latin-9`.
- Added bounded Latin-9 byte remapping for the eight positions that differ from
  ISO-8859-1: Euro, S-caron, s-caron, Z-caron, z-caron, OE, oe, and
  Y-diaeresis.
- Verified the decoded text through `MarkdownReader::readBytes()` and
  `WordPressBlockWriter` so legacy review packets with Latin-9 declarations do
  not become repaired UTF-8 replacement characters.
- Extended the WordPress charset handoff self-test with a `Latin-9 source`
  audit row that records the decoded text, source encoding, and display width.

## Source Truth

This slice owns the supervisor row `pandoc-charset-unicode-width-core-*`, which
covers byte decoding, Unicode repair, and display-width behavior needed by
Pandoc readers and writers. Existing accepted charset slices already covered
BOM precedence, UTF-16, Windows-1252, ISO-8859-1, malformed UTF-8 repair,
line-ending normalization, fallback normalization, display-width splitting and
wrapping, emoji/tag/ZWJ/skin-tone clusters, East Asian wide and ambiguous width,
Hangul/Indic/Thai/Lao clusters, soft breaks, separators, default ignorables,
and format controls.

The bounded upstream-facing behavior is charset-label decoding before text
enters the native Pandoc-like reader/writer pipeline. This patch does not add a
general WHATWG Encoding implementation; it ports the focused Latin-9 mapping
needed for common legacy European sources that declare ISO-8859-15.

No current-base Pandoc rework note was present. No hydrated Pandoc checkout was
available in this worktree or upstream cache, so this remains static
source-truth mapping plus focused native PHP tests rather than Haskell runner
parity.

## Verification

Pre-slice focused baseline:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 270 assertions, 0 failures`

Red-first focused check after adding the Latin-9 test and before the
implementation:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 271 assertions, 1 failures`
  - Failure: the new Latin-9 case expected `iso-8859-15`, but
    `UnicodeText::decodeBytes(..., 'latin-9')` returned `utf-8-repaired`.

Post-implementation verification:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 278 assertions, 0 failures`
  - Delta: `+8` focused assertions and `+1` focused PHP PASS case.
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

No new support component is needed. The slice extends the existing native PHP
`UnicodeText` helper and reuses the current Markdown reader plus WordPress
charset handoff example. It does not invoke Pandoc, Cabal, Haskell test
binaries, external charset converters, citeproc, BibTeX, Biber, Word,
LibreOffice, `zip`, `unzip`, `tar`, `lz4`, external template engines, TeX/PDF
engines, browser renderers, roff, Typst, MathJax, KaTeX, terminal probes,
online sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted XML/HTML5 DOM support, YAML metadata,
ZIP/OPC, relationship graphs, gzip/tar/LZ4 streams, doctemplate partials,
CSL/BibTeX processing, DOCX/ODT parsing, EPUB3 package handoff, legacy DOC/CFB
extraction, table geometry span normalization, Math/TeX conversion, PDF engine
handoff planning, line-ending normalization, BOM precedence, UTF-16,
Windows-1252, ISO-8859-1, malformed UTF-8 repair, Unicode normalization,
display-width breakpoint splitting, display-column wrapping, emoji
presentation width, keycap/regional/tag emoji sequence width, emoji skin-tone
modifier width, emoji ZWJ variation width, supplementary East Asian
wide-symbol width, default-presentation BMP wide-symbol width, decomposed
Hangul Jamo width, Indic/Thai/Lao grapheme handling, default-ignorable control
width accounting, East Asian ambiguous-width policy, Unicode soft-break
wrapping, Unicode separator wrapping, prepended format-control zero-width
accounting, or upstream-runner dependency audit work.

It only extends the charset/Unicode primitive with bounded ISO-8859-15 /
Latin-9 byte decoding for native text handoff.

## Follow-Up

Keep declared HTML/XML charset sniffing, ISO-2022/Shift_JIS/EUC-JP labels,
MacRoman, full WHATWG Encoding label coverage, generated Unicode normalization
tables, and full upstream Haskell runner parity as separate bounded slices.
