# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T144031Z`

Base accepted HEAD: `9bea7b4c06e1f594835627b0cfa11df5c9346166`

## Behavior Added

- Extended `UnicodeText::decodeBytes()` to detect UTF-32 BOMs before the
  shorter UTF-16 BOM prefixes:
  - UTF-32LE: `FF FE 00 00`;
  - UTF-32BE: `00 00 FE FF`.
- Added bounded native `utf-32`, `utf-32le`, `utf-32be`, `ucs-4`, `ucs-4le`,
  and `ucs-4be` label handling.
- Added UTF-32 scalar decoding with replacement-character repair for invalid
  scalar values, surrogate scalar values, and truncated 4-byte code units.
- Verified that `MarkdownReader::readBytes()` carries UTF-32 source metadata
  through to the WordPress charset handoff path instead of misclassifying
  UTF-32LE BOM input as UTF-16LE NUL-padded text.

## Source Truth

This slice owns the supervisor row `pandoc-charset-unicode-width-core-*`, which
covers byte decoding, Unicode repair, and display-width behavior needed by
Pandoc readers and writers. Earlier accepted charset slices already covered BOM
precedence for UTF-8/UTF-16, Windows-1252, ISO-8859-1, ISO-8859-15 / Latin-9,
MacRoman, malformed UTF-8 repair, line-ending normalization, Unicode
normalization, display-width breakpoint splitting, display-column wrapping,
emoji presentation width, keycap/regional/tag emoji sequence width, emoji
skin-tone modifier width, emoji ZWJ variation width, supplementary and rare
East Asian wide symbols/scripts, BMP/geometric emoji width, decomposed Hangul
Jamo width, Indic/Thai/Lao grapheme handling, default-ignorable control
accounting, East Asian ambiguous-width policy, Unicode soft-break wrapping,
Unicode separator wrapping, and prepended format-control zero-width accounting.

The bounded upstream-facing behavior is Unicode byte decoding before text
enters the native Pandoc-like reader/writer pipeline. The bug was local and
concrete: `FF FE 00 00` previously matched the UTF-16LE BOM branch first,
leaving UTF-32LE input as NUL-padded UTF-16 text.

No current-base Pandoc rework note was present. No hydrated Pandoc checkout was
available in this worktree or upstream cache, so this remains static
source-truth mapping plus focused native PHP tests rather than Haskell runner
parity.

## Verification

Pre-slice focused baseline:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 311 assertions, 0 failures`

Red-first focused check after adding the UTF-32 test and before the
implementation:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 312 assertions, 1 failures`
  - Failure: the new UTF-32LE BOM case expected `utf-32le`, but
    `UnicodeText::decodeBytes()` returned `utf-16le`.

Post-implementation verification:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 326 assertions, 0 failures`
  - Delta: `+15` focused assertions and `+1` focused PHP PASS case.
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
`UnicodeText` helper and reuses the current Markdown reader, Markdown writer,
and WordPress charset handoff example. It does not invoke Pandoc, Cabal,
Haskell test binaries, external charset converters, citeproc, BibTeX, Biber,
Word, LibreOffice, `zip`, `unzip`, `tar`, `lz4`, external template engines,
TeX/PDF engines, browser renderers, roff, Typst, MathJax, KaTeX, terminal
probes, online sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted XML/HTML5 DOM support, YAML metadata,
ZIP/OPC, relationship graphs, gzip/tar/LZ4 streams, doctemplate partials,
CSL/BibTeX processing, DOCX/ODT parsing, EPUB3 package handoff, legacy DOC/CFB
extraction, table geometry span normalization, Math/TeX conversion, PDF engine
handoff planning, line-ending normalization, UTF-8/UTF-16 BOM handling,
Windows-1252, ISO-8859-1, ISO-8859-15 / Latin-9, MacRoman, malformed UTF-8
repair, Unicode normalization, display-width breakpoint splitting,
display-column wrapping, emoji presentation width, keycap/regional/tag emoji
sequence width, emoji skin-tone modifier width, emoji ZWJ variation width,
supplementary/rare East Asian wide scripts and symbols, BMP/geometric emoji
symbols, decomposed Hangul Jamo width, Indic/Thai/Lao grapheme handling,
default-ignorable control width accounting, East Asian ambiguous-width policy,
Unicode soft-break wrapping, Unicode separator wrapping, prepended
format-control zero-width accounting, or upstream-runner dependency audit work.

It only extends the charset/Unicode primitive with bounded UTF-32 BOM and
UTF-32 scalar decoding.

## Follow-Up

Keep generated Unicode data-table refreshes, full grapheme-break property
parity, terminal-profile-specific emoji width variants, HTML/XML parser
charset sniffing, ISO-2022/Shift_JIS/EUC-JP labels, and full upstream Haskell
runner parity as separate bounded slices.
