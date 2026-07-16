# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T063544Z`

Base accepted HEAD: `beecd573326eb942861636d36f425d3bf3ca3af6`

## Behavior Added

- Extended `UnicodeText` display-width classification so Unicode
  General_Category `Format` controls are zero-width when PHP `IntlChar` data is
  available.
- Added bounded no-`intl` fallback coverage for Arabic, Syriac, Egyptian
  Hieroglyph, and Kaithi format controls, including prepended Arabic/Syriac/
  Kaithi controls that can appear in multilingual import labels.
- Preserved those source controls in text while preventing them from adding
  display columns during width measurement, display breakpoint splitting,
  right-padding, and wrapping.
- Extended the WordPress charset handoff smoke with a format-control audit row
  so review packets expose preserved controls without over-padding multilingual
  source labels.

## Source Truth

This slice owns the supervisor row `pandoc-charset-unicode-width-core-*`, which
covers byte decoding, Unicode repair, and display-width behavior needed by
Pandoc readers and writers. Earlier accepted charset slices already covered
BOMs, UTF-16, Windows-1252, malformed UTF-8 repair, line-ending normalization,
Unicode normalization forms, display-width breakpoint splitting, display-column
wrapping, emoji presentation and tag sequences, decomposed Hangul Jamo width,
Indic spacing marks, default-ignorable soft hyphen/BOM controls, East Asian
ambiguous-width policy, Unicode soft-break wrapping, and bounded Unicode
separator wrapping. This slice is a bounded follow-up for non-rendering Unicode
format controls that should not affect Pandoc-style display columns.

No current-base Pandoc rework note was present. No hydrated Pandoc checkout was
available in this worktree or upstream cache, so this remains static
source-truth mapping plus focused native PHP tests rather than Haskell runner
parity.

## Verification

Pre-slice focused baseline:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 186 assertions, 0 failures`

Red-first focused check:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result before implementation: failed with `1 test files, 187 assertions,
    1 failures`.
  - Failure: the new format-control case expected
    `UnicodeText::displayWidth("\u{0600}رقم \u{070F}ܣܘܪܝܝܐ \u{110BD}kaithi")`
    to return `17`, but it returned `20`.

Post-implementation verification:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 193 assertions, 0 failures`
  - Delta: `+7` focused assertions and `+1` focused PHP PASS case.
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
emoji presentation width, emoji tag sequences, decomposed Hangul Jamo width,
Indic spacing-mark width, default-ignorable soft hyphen/BOM width accounting,
East Asian ambiguous-width policy, Unicode soft-break wrapping, Unicode
separator wrapping, BOM precedence, or upstream-runner dependency audit work.
It only extends the charset/Unicode width primitive with bounded zero-width
format-control display accounting.

## Follow-Up

Keep full Unicode line-breaking class parity beyond bounded separators and soft
breaks, dictionary-based segmentation, terminal-profile-specific emoji width
variants, HTML/XML parser charset negotiation, full Unicode normalization data
tables, and writer-wide automatic Markdown wrapping as separate bounded slices.
