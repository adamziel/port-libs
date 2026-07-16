# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T005523Z`

Base accepted HEAD: `7237c3c9b1c64d655568144f440621efd4316dfb`

## Behavior Added

- Added `UnicodeText::wrapByDisplayWidth()` as a bounded native support helper
  for Pandoc-style display-column wrapping.
- The helper repairs malformed UTF-8, normalizes hard line endings, treats
  existing hard line breaks as wrap resets, counts continuation indentation in
  display columns, and wraps overlong tokens without splitting Unicode
  grapheme clusters.
- Focused coverage proves CJK double-width text, emoji modifier and regional
  flag clusters, combining-mark text, hard-line resets, and continuation
  indentation all stay within the requested display width when possible.
- Extended `examples/wordpress-charset-unicode-handoff.php` with a wrapped-note
  audit row so WordPress review packets can expose the same native display-width
  wrap decisions used by Pandoc reader/writer support code.

## Source Truth

This slice owns the supervisor row `pandoc-charset-unicode-width-core-*`, which
covers byte decoding, Unicode repair, and display-width behavior needed by
Pandoc readers and writers. Existing lane evidence already maps upstream Pandoc
table/layout behavior that depends on display columns rather than byte or
codepoint count, and previous charset notes cite Pandoc shared display-width
splitting helpers.

No hydrated Pandoc checkout was available at
`/home/claude/port-libs/.upstream-cache/pandoc`, so this remains static
source-truth mapping plus focused native PHP tests rather than upstream Haskell
runner parity.

## Verification

Red-first focused check:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result before implementation: failed with `1 test files, 58 assertions, 1
    failure` because `UnicodeText::wrapByDisplayWidth()` did not exist.

Post-implementation verification:

- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 66 assertions, 0 failures`
  - Delta: `+8` focused assertions and `+1` focused PHP PASS case.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 4873 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice extends the existing native PHP
`UnicodeText` helper and reuses `MarkdownReader`, `MarkdownWriter`,
`WordPressBlockWriter`, and AST paths through the existing charset handoff
example. It does not invoke Pandoc, Cabal, Haskell test binaries, citeproc,
BibTeX, Biber, Word, LibreOffice, `zip`, `unzip`, `tar`, `lz4`, external
template engines, TeX/PDF engines, browser renderers, roff, Typst, MathJax,
KaTeX, or online services.

## Non-Overlap

This patch does not repeat accepted XML/HTML5 DOM support, YAML metadata,
ZIP/OPC, relationship graphs, gzip/tar/LZ4 streams, doctemplate partials, CSL or
BibTeX processing, DOCX/ODT parsing, EPUB3 package handoff, legacy DOC/CFB
extraction, table geometry span normalization, Math/TeX conversion, PDF engine
handoff planning, line-ending normalization, display-width breakpoint splitting,
emoji presentation width, or upstream-runner dependency audit work. It only
extends the charset/Unicode width primitive with bounded display-column
wrapping for repaired Unicode text.

## Follow-Up

Keep full Unicode line-breaking, East Asian ambiguous-width terminal policy,
Unicode normalization forms, HTML/XML parser charset negotiation, and writer-wide
automatic Markdown wrapping as separate bounded slices.
