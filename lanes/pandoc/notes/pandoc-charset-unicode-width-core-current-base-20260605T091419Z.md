# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T091419Z`

Base accepted HEAD: `abe30de2d2758415834fdd82928618cd9340d800`

## Behavior Added

- Extended `UnicodeText` display-width classification for default-presentation
  BMP emoji and symbols whose East Asian Width property is wide.
- Added bounded two-column coverage for watch/hourglass, fast-forward and
  hourglass symbols, weather/sport symbols, zodiac signs, status marks, plus
  common wide marks such as heavy check mark, cross mark, star, circle, and
  fuel pump.
- Verified display-width splitting, padding, and wrapping keep these symbols
  aligned without requiring a variation selector.
- Extended the WordPress charset handoff smoke with a `BMP emoji wide` audit
  row so reviewer tables expose the same width behavior.

## Source Truth

This slice owns the supervisor row `pandoc-charset-unicode-width-core-*`, which
covers byte decoding, Unicode repair, and display-width behavior needed by
Pandoc readers and writers. Prior accepted charset slices already covered
BOMs, UTF-16, Windows-1252, malformed UTF-8 repair, line-ending normalization,
Unicode normalization forms, display-width breakpoint splitting,
display-column wrapping, emoji presentation and tag sequences, emoji ZWJ
variation sequences, supplementary East Asian wide symbols, decomposed Hangul
Jamo width, Indic spacing-mark width, default-ignorable controls, East Asian
ambiguous-width policy, Unicode soft-break wrapping, Unicode separators, and
prepended format-control zero-width accounting.

The bounded upstream-facing behavior is Pandoc/doclayout-style display-column
accounting: combining/control characters are zero-width, regular characters are
one column, and East Asian wide/fullwidth characters are two columns while
ambiguous-width characters remain policy-controlled. This patch fills focused
BMP wide emoji/symbol gaps that were outside the older CJK, supplementary
wide, emoji-variation, and ZWJ tables.

No current-base Pandoc rework note was present. No hydrated Pandoc checkout was
available in this worktree or upstream cache, so this remains static
source-truth mapping plus focused native PHP tests rather than Haskell runner
parity.

## Verification

Pre-slice focused baseline:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 238 assertions, 0 failures`

Red-first focused check:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result before implementation: failed with `1 test files, 239 assertions,
    1 failures`.
  - Failure: the new BMP wide-symbol case expected
    `UnicodeText::displayWidth("\u{231A}\u{231B}\u{23E9}\u{23F3}")` to return
    `8`, but it returned `4`.

Post-implementation verification:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 249 assertions, 0 failures`
  - Delta: `+11` focused assertions and `+1` focused PHP PASS case.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: no syntax errors.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice extends the existing native PHP
`UnicodeText` helper and reuses the current Markdown/WordPress charset handoff
example. It does not invoke Pandoc, Cabal, Haskell test binaries, citeproc,
BibTeX, Biber, Word, LibreOffice, `zip`, `unzip`, `tar`, `lz4`, external
template engines, TeX/PDF engines, browser renderers, roff, Typst, MathJax,
KaTeX, terminal probes, online sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted XML/HTML5 DOM support, YAML metadata,
ZIP/OPC, relationship graphs, gzip/tar/LZ4 streams, doctemplate partials,
CSL/BibTeX processing, DOCX/ODT parsing, EPUB3 package handoff, legacy DOC/CFB
extraction, table geometry span normalization, Math/TeX conversion, PDF engine
handoff planning, line-ending normalization, byte decoding, Unicode
normalization, display-width breakpoint splitting, display-column wrapping,
emoji presentation width, emoji tag sequences, emoji ZWJ variation width,
supplementary East Asian wide-symbol width, decomposed Hangul Jamo width,
Indic spacing-mark width, default-ignorable control width accounting, East
Asian ambiguous-width policy, Unicode soft-break wrapping, Unicode separator
wrapping, prepended format-control zero-width accounting, BOM precedence, or
upstream-runner dependency audit work.

It only extends the charset/Unicode primitive with bounded default-presentation
BMP East Asian wide emoji/symbol display-column accounting.

## Follow-Up

Keep full UAX #11 table refreshes, terminal-profile-specific emoji width
variants, dictionary-based segmentation, HTML/XML parser charset negotiation,
full Unicode normalization data tables, and writer-wide automatic Markdown
wrapping as separate bounded slices.
