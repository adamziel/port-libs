# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T060125Z`

Base accepted HEAD: `e6c2c65a1e2231590eb2d76057253666c5d01998`

## Behavior Added

- Extended `UnicodeText` display-width classification to treat Unicode mark
  categories as zero-width combining marks when PHP `intl` is available.
- Added bounded no-`intl` fallback ranges for Indic spacing vowel signs used by
  Devanagari, Bengali, Tamil, and adjacent South Asian scripts.
- Devanagari, Tamil, and Bengali dependent vowel-sign clusters now stay
  attached during grapheme grouping, display-width splitting, padding, and
  wrapping instead of being counted as extra columns.
- Extended the WordPress charset handoff smoke with an Indic spacing-mark audit
  row so multilingual review tables expose the same width behavior.

## Source Truth

This slice owns the supervisor row `pandoc-charset-unicode-width-core-*`, which
covers byte decoding, Unicode repair, and display-width behavior needed by
Pandoc readers and writers. Hackage documentation for Pandoc
`Text.Pandoc.Shared.splitTextByIndices` says break points are text widths, not
indices, and the source delegates width accounting to `Text.DocLayout.charWidth`.
Pandoc imports `NonSpacingMark`, `SpacingCombiningMark`, and `EnclosingMark`
categories in `Text.Pandoc.Shared`, while doclayout documents `charWidth` as
0 for combining characters, 1 for regular characters, and 2 for East Asian
wide characters with ambiguous characters treated as width 1.

References used:

- `Text.Pandoc.Shared` docs/source:
  https://hackage-content.haskell.org/package/pandoc-3.7.0.2/docs/Text-Pandoc-Shared.html
- `Text.DocLayout.charWidth` docs:
  https://hackage-content.haskell.org/package/doclayout-0.5.0.1/docs/Text-DocLayout.html

No current-base Pandoc rework note was present. No hydrated Pandoc checkout was
available in this worktree or upstream cache, so this remains static upstream
source-truth mapping plus focused native PHP tests rather than Haskell runner
parity.

## Verification

Pre-slice focused baseline:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 176 assertions, 0 failures`

Red-first focused check:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result before implementation: failed with `1 test files, 177 assertions,
    1 failures`.
  - Failure: the new Indic spacing-mark case expected
    `UnicodeText::displayWidth("कि")` to return `1`, but it returned `2`.

Post-implementation verification:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 186 assertions, 0 failures`
  - Delta: `+10` focused assertions and `+1` focused PHP PASS case.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice extends the existing native PHP
`UnicodeText` helper and reuses the existing Markdown/WordPress charset handoff
path. It does not invoke Pandoc, Cabal, Haskell test binaries, citeproc,
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
emoji presentation width, emoji tag sequences, decomposed Hangul Jamo width,
default-ignorable width accounting, East Asian ambiguous-width policy, Unicode
soft-break wrapping, Unicode separator wrapping, BOM precedence, or
upstream-runner dependency audit work. It only extends the charset/Unicode
width primitive with bounded Indic spacing-combining-mark display clustering.

## Follow-Up

Keep HTML/XML parser charset negotiation, dictionary-based segmentation,
terminal-profile-specific emoji width variants, full Unicode line-breaking
class parity beyond bounded separators and soft breaks, full Unicode
normalization data tables, and writer-wide automatic Markdown wrapping as
separate bounded slices.
