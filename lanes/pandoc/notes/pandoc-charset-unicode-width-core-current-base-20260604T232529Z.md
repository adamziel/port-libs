# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260604T232529Z`

Base accepted HEAD: `dfccfd252d4ec7968da59da8d0cbc92468a86823`

## Behavior Added

- Added `UnicodeText::splitAtDisplayWidth()` for splitting text at a display
  column boundary using the lane's Unicode display-width model.
- Added `UnicodeText::splitByDisplayBreakpoints()` for Pandoc-style absolute
  display-width breakpoints, preserving CJK, combining-mark, and emoji ZWJ
  grapheme clusters instead of splitting by byte or codepoint count.
- Extended the WordPress charset handoff example with a display-slice audit row
  so reviewer packets can expose the text segments and widths used by native
  Markdown/table handoff code.

## Source Truth

Pinned upstream Pandoc source at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`
uses `Text.Pandoc.Shared.splitTextByIndices` and `splitAtWidth` to split text by
display width rather than text indices, with `Text.DocLayout.charWidth` handling
East Asian and emoji width. This slice ports that bounded support-library
contract into native PHP without running Pandoc or Haskell test binaries.

## Verification

Pre-slice lane status recorded `393` PHP PASS lines, `850` mapped native checks,
and `UnicodeTextTest.php` at `31` assertions. This slice adds `+1` focused PHP
PASS case and `+8` focused assertions.

- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 39 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `14 test files, 3,741 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice extends the existing native PHP
`UnicodeText` helper and reuses the existing Markdown/WordPress handoff paths.
It does not invoke Pandoc, Cabal, Haskell test binaries, citeproc, BibTeX,
Biber, Word, LibreOffice, `zip`, `unzip`, external template engines, TeX/PDF
engines, browser renderers, roff, Typst, MathJax, KaTeX, or online services.

## Non-Overlap

This patch does not repeat accepted XML/HTML5 DOM support, YAML metadata,
ZIP/OPC, relationship graphs, gzip/tar/LZ4 streams, doctemplate partials, CSL or
BibTeX processing, DOCX/ODT parsing, EPUB3 package handoff, legacy DOC/CFB
extraction, table geometry span normalization, Math/TeX conversion, PDF engine
handoff planning, or upstream-runner dependency audit work. It only extends the
charset/Unicode-width support primitive with display-width splitting.

## Follow-Up

Keep full Unicode normalization forms, carriage-return byte-source filtering,
East Asian ambiguous-width policy, terminal-profile-specific emoji variation
width, and full line-wrapping/layout behavior as separate bounded slices.
