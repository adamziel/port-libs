# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T122813Z`

Base accepted HEAD: `83d14850b25025929d0658c79f2dae5d9193bbe0`

## Behavior Added

- Extended `UnicodeText` display-width accounting so U+FE0E and U+FE0F both
  act as Pandoc/doclayout-style emoji variation modifiers when attached to an
  emoji-capable base character.
- Preserved zero-width behavior for standalone variation selectors and one-
  column behavior for non-emoji bases such as `A` plus U+FE0E.
- Added focused coverage for width, grapheme grouping, display-width splitting,
  padding, wrapping, and the WordPress charset review-packet audit row.

## Source Truth

The Pandoc shared text splitter documents that split points are text widths
rather than byte or character indexes, and delegates display width to
`Text.DocLayout.charWidth`:

- https://hackage-content.haskell.org/package/pandoc-3.7.0.2/docs/Text-Pandoc-Shared.html
- https://hackage-content.haskell.org/package/doclayout-0.5.0.1/docs/Text-DocLayout.html

`Text.DocLayout` exposes `isEmojiVariation` for U+FE0E through U+FE0F and uses
emoji variation state in real-length matching. This slice ports that bounded
rule into the native PHP width helper used by Markdown table padding and
WordPress review handoffs. It does not attempt a full Unicode or terminal
profile width-table refresh.

No current-base Pandoc rework note was present. No hydrated Pandoc checkout was
available in this worktree or the upstream cache, so this remains source-backed
native PHP support-library coverage rather than Haskell runner parity.

## Verification

Pre-slice focused baseline:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 278 assertions, 0 failures`

Red-first focused check after adding the U+FE0E test and before the
implementation:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 279 assertions, 1 failures`
  - Failure: the new text-variation case expected width `2`, but
    `UnicodeText::displayWidth("\u{263A}\u{FE0E}")` returned `1`.

Post-implementation verification:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 292 assertions, 0 failures`
  - Delta: `+14` focused assertions and `+1` focused PHP PASS case.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`

Additional verification to run before handoff close:

- PHP lint for changed PHP files.
- JSON validation for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc`.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice extends the existing native PHP
`UnicodeText` helper and reuses the current WordPress charset handoff example.
It does not invoke Pandoc, Cabal, Haskell test binaries, external charset
converters, citeproc, BibTeX, Biber, Word, LibreOffice, `zip`, `unzip`, `tar`,
`lz4`, external template engines, TeX/PDF engines, browser renderers, roff,
Typst, MathJax, KaTeX, terminal probes, online sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted XML/HTML5 DOM support, YAML metadata,
ZIP/OPC, relationship graphs, gzip/tar/LZ4 streams, doctemplate partials,
CSL/BibTeX processing, DOCX/ODT parsing, EPUB3 package handoff, legacy DOC/CFB
extraction, table geometry span normalization, Math/TeX conversion, PDF engine
handoff planning, line-ending normalization, BOM precedence, UTF-16,
Windows-1252, ISO-8859-1, ISO-8859-15 / Latin-9 decoding, malformed UTF-8
repair, Unicode normalization, display-width breakpoint splitting,
display-column wrapping, emoji presentation width for U+FE0F, keycap/regional/
tag emoji sequence width, emoji skin-tone modifier width, emoji ZWJ variation
width, supplementary East Asian wide-symbol width, default-presentation BMP
wide-symbol width, decomposed Hangul Jamo width, Indic/Thai/Lao grapheme
handling, default-ignorable control width accounting, East Asian ambiguous-
width policy, Unicode soft-break wrapping, Unicode separator wrapping,
prepended format-control zero-width accounting, or upstream-runner dependency
audit work.

It only extends the charset/Unicode primitive with bounded U+FE0E text-variation
selector display-width accounting for emoji-capable bases.

## Follow-Up

Keep terminal-profile-specific emoji width variants, generated Unicode width
table refreshes, broader grapheme-break tables, dictionary-based segmentation,
declared HTML/XML charset sniffing, ISO-2022/Shift_JIS/EUC-JP labels, MacRoman,
full WHATWG Encoding label coverage, and full upstream Haskell runner parity as
separate bounded slices.
