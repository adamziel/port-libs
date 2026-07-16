# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260604T154443Z`

Base accepted HEAD: `5e84ed6e8e44d11d11a1953fb027bc4011d0af39`

## Behavior Added

- Added `UnicodeText` as a bounded native support primitive for:
  - BOM-aware UTF-8 and UTF-16LE/UTF-16BE byte decoding;
  - explicit Windows-1252 and ISO-8859-1 single-byte decoding;
  - malformed UTF-8 repair with U+FFFD replacement characters;
  - basic grapheme clustering around combining marks and zero-width joiners;
  - display-width measurement for CJK/fullwidth, combining, zero-width, and
    emoji-range code points;
  - display-width padding for Markdown table cells.
- Added `MarkdownReader::readBytes()` so byte-source readers can decode before
  Markdown parsing and preserve `sourceEncoding` audit metadata on the document
  node.
- Switched Markdown pipe-table writer column width and cell padding from byte
  counts to Unicode display widths, keeping CJK and zero-width reviewer table
  text aligned in Markdown handoff output.
- Added `examples/wordpress-charset-unicode-handoff.php` to exercise
  Windows-1252 reviewer exports plus Unicode table text on the Markdown and
  WordPress block handoff paths.

## Source Truth

This slice owns the support-library row named by the supervisor:
`pandoc-charset-unicode-width-core-*`, covering byte decoding, Unicode repair,
and display-width behavior needed by Pandoc readers/writers. It is bounded to
native PHP support used by existing Markdown reader/writer paths. It does not
attempt full upstream runner parity or whole Pandoc text/terminal width parity.

Existing lane inventory already maps upstream Pandoc grid-table fixture notes
for East Asian double-width text and zero-width German/Persian examples. This
slice extracts that need into a reusable support primitive and applies it to
Markdown writer pipe-table padding.

## Verification

Pre-slice lane status recorded `346` PHP PASS lines, `782` mapped native checks,
and `MarkdownReaderTest.php` at `2435` assertions. `UnicodeTextTest.php` did not
exist before this slice.

- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/src/MarkdownReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/src/MarkdownWriter.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 31 assertions, 0 failures`
  - Delta: `+31` focused assertions and `+6` focused PASS lines.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `2 test files, 2466 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `11 test files, 3245 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No external dependency is needed. The slice adds a native PHP support component
under `lanes/pandoc/src` and reuses existing `MarkdownReader`, `MarkdownWriter`,
`WordPressBlockWriter`, and AST paths. It does not invoke Pandoc, Cabal,
Haskell test binaries, Word, LibreOffice, `zip`, `unzip`, external template
engines, TeX/PDF engines, browser renderers, roff, Typst, MathJax, KaTeX, or
online services.

## Non-Overlap

This patch does not repeat accepted YAML metadata anchors/tags, ZIP/OPC,
relationship graphs, gzip streams, doctemplate partials, CSL processing, DOCX
or ODT parsing, legacy DOC/CFB extraction, table geometry span normalization,
Math/TeX conversion, PDF engine handoff planning, or upstream-runner dependency
audit work. It owns only bounded charset decoding, Unicode repair, display
width, and direct Markdown reader/writer integration for those primitives.

## Follow-Up

Keep broader charset detection policy, normalization forms, full Unicode line
breaking, East Asian ambiguous-width policy, terminal-profile-specific emoji
width decisions, and deeper HTML/XML parser charset negotiation as separate
bounded support slices.
