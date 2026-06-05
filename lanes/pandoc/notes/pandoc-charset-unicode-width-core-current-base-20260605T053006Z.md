# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T053006Z`

Base accepted HEAD: `4d91007bafdf12504e3d93f023ba1b74fc3b19ae`

## Behavior Added

- Added an explicit `auto` / `intl` / `fallback` selector to
  `UnicodeText::normalize()` so tests and review paths can exercise the native
  PHP fallback normalizer even on hosts with `intl`.
- Extended bounded fallback Unicode normalization with canonical combining-mark
  ordering before NFD/NFKD output and before NFC/NFKC composition.
- Added common Latin decompositions/compositions needed by document titles and
  metadata, including grave/acute/circumflex/tilde/diaeresis/ring, cedilla,
  and dotted/dot-below `d` review text.
- Extended the WordPress charset handoff smoke with a fallback NFC audit row so
  import review packets expose stable fallback-normalized title text when
  `intl` Normalizer is unavailable.

## Source Truth

This slice owns the supervisor row `pandoc-charset-unicode-width-core-*`, which
covers byte decoding, Unicode repair, and display-width behavior needed by
Pandoc readers and writers. Earlier accepted charset slices already covered
BOMs, UTF-16, Windows-1252, malformed UTF-8 repair, line-ending normalization,
baseline Unicode normalization forms, display-width breakpoint splitting,
wrapping, emoji presentation/tag sequences, decomposed Hangul Jamo display
width, default-ignorable controls, East Asian ambiguous-width policy, Unicode
soft-break wrapping, and bounded Unicode separator wrapping. This slice is a
bounded follow-up for fallback normalization correctness, not a repeat of those
display-width or byte-decoding paths.

No current-base Pandoc rework note was present. No hydrated Pandoc checkout was
available in this worktree or the upstream cache, so this remains static
source-truth mapping plus focused native PHP tests rather than Haskell runner
parity.

## Verification

Pre-slice focused baseline:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 162 assertions, 0 failures`
  - Baseline PASS lines: 19.

Red-first focused check:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result before implementation: failed with `1 test files, 166 assertions,
    1 failures`.
  - Failure: the new fallback-normalization case expected implementation
    `fallback`, but `UnicodeText::normalize(..., 'fallback')` still returned
    `intl`.

Post-implementation verification:

- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 176 assertions, 0 failures`
  - Delta: `+14` focused assertions and `+1` focused PHP PASS case.
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
handoff planning, line-ending normalization, byte decoding, display-width
breakpoint splitting, display-column wrapping, emoji presentation width, emoji
tag sequences, decomposed Hangul Jamo width, default-ignorable width accounting,
East Asian ambiguous-width policy, Unicode soft-break wrapping, Unicode
separator wrapping, BOM precedence, or upstream-runner dependency audit work.
It only extends the charset/Unicode primitive with bounded fallback
normalization ordering and composition.

## Follow-Up

Keep full Unicode normalization data tables, HTML/XML parser charset
negotiation, dictionary-based segmentation, terminal-profile-specific emoji
width variants, full Unicode line-breaking class parity beyond bounded
separators and soft breaks, and writer-wide automatic Markdown wrapping as
separate bounded slices.
