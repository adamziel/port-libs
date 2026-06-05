# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T012641Z`

Base accepted HEAD: `e51d905ff1c2b2be059c5da96845f24c378263a7`

## Behavior Added

- Added `UnicodeText::normalize()` with NFC, NFD, NFKC, and NFKD form aliases.
- Extended `UnicodeText::decodeBytes()` and `MarkdownReader::readBytes()` with
  an opt-in normalization form argument, preserving the existing default byte
  decoding behavior while adding `sourceNormalization` audit metadata when
  normalization is requested.
- Added a native fallback for the bounded reviewer-visible normalization cases
  used by the focused tests when PHP `intl` is unavailable; this environment
  used `intl` for the full normalization path.
- Extended the WordPress charset handoff smoke with NFC/NFKC audit rows so
  review packets can show normalized source headings and compatibility glyph
  repair without shelling out to Pandoc or external converters.

## Source Truth

This slice owns the `pandoc-charset-unicode-width-core-*` row: byte decoding,
Unicode repair, and display-width behavior needed by Pandoc readers and
writers. Previous charset notes left Unicode normalization forms as a follow-up.
This patch keeps normalization explicit and opt-in so existing reader behavior
does not change underneath accepted Markdown/HTML/DOCX/ODT tests.

No hydrated Pandoc checkout was present in `/home/claude/port-libs/.upstream-cache`
or this worktree, so this remains static manifest source-truth plus focused
native PHP tests rather than Haskell runner parity.

## Verification

Pre-slice focused baseline:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 66 assertions, 0 failures`

Post-implementation verification:

- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/src/MarkdownReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 87 assertions, 0 failures`
  - Delta: `+21` focused assertions and `+1` focused PHP PASS case.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 5175 assertions, 0 failures`
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new external support component is needed. The slice extends the existing
native PHP `UnicodeText` helper and reuses `MarkdownReader`,
`WordPressBlockWriter`, and AST paths. PHP `intl` is used when present; a
bounded fallback covers the focused reviewer cases without external services or
processes. This slice did not invoke Pandoc, Cabal, Haskell test binaries,
citeproc, BibTeX, Biber, Word, LibreOffice, `zip`, `unzip`, `tar`, `lz4`,
external template engines, TeX/PDF engines, browser renderers, roff, Typst,
MathJax, KaTeX, online sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted XML/HTML5 DOM support, YAML metadata,
ZIP/OPC, relationship graphs, gzip/tar/LZ4 streams, doctemplate partials,
CSL/BibTeX processing, DOCX/ODT parsing, EPUB3 package handoff, legacy DOC/CFB
extraction, table geometry span normalization, Math/TeX conversion, PDF engine
handoff planning, line-ending normalization, display-width breakpoint
splitting, emoji presentation width, display-column wrapping, or upstream-runner
dependency audit work. It only extends charset/Unicode support with opt-in
normalization metadata for byte-source Markdown and WordPress review handoffs.

## Follow-Up

Keep default-reader normalization policy, East Asian ambiguous-width policy,
HTML/XML parser charset negotiation, and full Unicode line-breaking/layout
behavior as separate bounded slices.
