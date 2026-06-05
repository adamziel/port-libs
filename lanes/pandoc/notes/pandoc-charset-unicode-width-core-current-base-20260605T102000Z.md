# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T102000Z`

Base accepted HEAD: `3a4bdc2dccbffb08c0bcb43152a330884585659f`

## Behavior Added

- Extended `UnicodeText` display-width classification for bounded geometric
  emoji symbols used in reviewer status labels.
- U+1F7E0..U+1F7EB colored circles/squares and U+1F7F0 heavy equals now measure
  as two display columns instead of one.
- Display breakpoint splitting, padding, wrapping, and the WordPress charset
  handoff audit table now keep these symbols aligned with the accepted emoji
  and East Asian wide-width policy.

## Source Truth

This slice owns the supervisor row `pandoc-charset-unicode-width-core-*`, which
covers byte decoding, Unicode repair, and display-width behavior needed by
Pandoc readers and writers. Prior accepted charset slices used Pandoc's
`Text.Pandoc.Shared` / `Text.DocLayout.charWidth` contract as source truth:
combining/control characters are zero-width, ordinary characters are one
column, and wide/default-emoji symbols that render as full cells are two
columns while ambiguous-width characters remain policy-controlled.

Earlier accepted slices already covered BOM handling, UTF-16, Windows-1252,
malformed UTF-8 repair, line-ending normalization, Unicode normalization,
display-width splitting/wrapping, emoji presentation clusters, keycap,
regional-indicator and tag sequences, emoji skin-tone modifiers, emoji ZWJ
variation sequences, supplementary East Asian wide symbols, default-presentation
BMP wide emoji symbols, decomposed Hangul Jamo, Indic and Thai/Lao grapheme
clusters, default-ignorable controls, East Asian ambiguous-width policy,
Unicode soft breaks, Unicode separator wrapping, and prepended format controls.
This patch only fills the bounded geometric emoji block gap outside those
accepted tables.

No current-base Pandoc rework note was present. No hydrated Pandoc checkout was
available in this worktree or upstream cache, so this remains static
source-truth mapping plus focused native PHP tests rather than Haskell runner
parity.

## Verification

Pre-slice focused baseline:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 260 assertions, 0 failures`

Red-first focused check after adding the geometric emoji test and before the
implementation:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result before implementation: failed with `1 test files, 261 assertions,
    1 failures`
  - Failure: `measures geometric emoji symbols for display columns` expected
    U+1F7E0/U+1F7E2/U+1F7E6 to measure width `6`, but they measured width `3`.

Post-implementation verification:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 270 assertions, 0 failures`
  - Delta: `+10` focused assertions and `+1` focused PHP PASS case.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: no syntax errors.
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
  - Result: both Pandoc JSON files decoded successfully.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

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
emoji presentation width, keycap/regional/tag emoji sequence width, emoji
skin-tone modifier width, emoji ZWJ variation width, supplementary East Asian
wide-symbol width, default-presentation BMP wide-symbol width, decomposed
Hangul Jamo width, Indic spacing-mark width, Thai/Lao AM grapheme clustering,
default-ignorable control width accounting, East Asian ambiguous-width policy,
Unicode soft-break wrapping, Unicode separator wrapping, prepended
format-control zero-width accounting, BOM precedence, or upstream-runner
dependency audit work.

It only extends the charset/Unicode primitive with bounded geometric emoji
symbol display-column accounting.

## Follow-Up

Keep full Unicode emoji data table generation, terminal-profile-specific emoji
width variants, dictionary-based segmentation, HTML/XML parser charset
negotiation, full Unicode normalization data tables, ICU-driven width table
refreshes, and writer-wide automatic Markdown wrapping as separate bounded
slices.
