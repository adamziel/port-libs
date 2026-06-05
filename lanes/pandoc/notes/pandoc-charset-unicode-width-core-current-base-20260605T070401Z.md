# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T070401Z`

Base accepted HEAD: `84c1a730bffbb80747f2327e7d5c015e433300fb`

## Behavior Added

- Fixed bounded display-width accounting for emoji ZWJ sequences that include
  variation selector 16 before a wide pictographic component.
- `UnicodeText::displayWidth()` now reduces those ZWJ emoji clusters to one
  two-column display cell before standalone emoji-variation width handling.
- Added focused coverage for U+2764 U+FE0F U+200D U+1F525, U+1F3F3 U+FE0F
  U+200D U+1F308, and U+1F441 U+FE0F U+200D U+1F5E8 U+FE0F.
- Verified that grapheme grouping, display breakpoint splitting, padding, and
  wrapping preserve the full cluster without over-padding Markdown/WordPress
  review output.
- Extended the WordPress charset handoff smoke with an `Emoji ZWJ variation`
  audit row.

## Source Truth

This slice owns the supervisor row `pandoc-charset-unicode-width-core-*`, which
covers byte decoding, Unicode repair, and display-width behavior needed by
Pandoc readers and writers. Earlier accepted charset slices already covered
BOMs, UTF-16, Windows-1252, malformed UTF-8 repair, line-ending normalization,
Unicode normalization forms, display-width breakpoint splitting, display-column
wrapping, emoji presentation sequences, emoji tag sequences, decomposed Hangul
Jamo width, Indic spacing marks, default-ignorable controls, East Asian
ambiguous-width policy, Unicode soft-break wrapping, Unicode separators, and
prepended format controls.

This is a bounded follow-up for the accepted emoji presentation/ZWJ display
cluster path: a ZWJ emoji sequence containing VS16 must not be counted as the
sum of the text-presentation base plus the wide pictographic component.

No current-base Pandoc rework note was present. No hydrated Pandoc checkout was
available in this worktree or upstream cache, so this remains static
source-truth mapping plus focused native PHP tests rather than Haskell runner
parity.

## Verification

Pre-slice focused baseline:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 193 assertions, 0 failures`

Red-first focused check:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result before implementation: failed with `1 test files, 194 assertions,
    1 failures`.
  - Failure: the new heart-on-fire case expected
    `UnicodeText::displayWidth(U+2764 U+FE0F U+200D U+1F525)` to return `2`,
    but it returned `3`.

Post-implementation verification:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 203 assertions, 0 failures`
  - Delta: `+10` focused assertions and `+1` focused PHP PASS case.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

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
emoji presentation width without ZWJ variation selectors, emoji tag sequences,
decomposed Hangul Jamo width, Indic spacing-mark width, default-ignorable
control width accounting, East Asian ambiguous-width policy, Unicode
soft-break wrapping, Unicode separator wrapping, format-control zero-width
accounting, BOM precedence, or upstream-runner dependency audit work.

It only extends the charset/Unicode width primitive with bounded display-width
accounting for emoji ZWJ sequences that include VS16.

## Follow-Up

Keep full Unicode line-breaking class parity, dictionary-based segmentation,
terminal-profile-specific emoji width variants, HTML/XML parser charset
negotiation, full Unicode normalization data tables, and writer-wide automatic
Markdown wrapping as separate bounded slices.
