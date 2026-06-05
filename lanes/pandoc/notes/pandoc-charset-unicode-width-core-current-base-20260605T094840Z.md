# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T094840Z`

Base accepted HEAD: `6c17a53dace9fb9ba9844a3b8d169184f9cf69ee`

## Behavior Added

- Extended `UnicodeText` display-width accounting for emoji skin-tone
  modifiers.
- Valid emoji modifier-base plus skin-tone modifier clusters, such as
  thumbs-up plus medium skin tone, remain one two-column grapheme cluster.
- Unattached skin-tone modifiers are no longer treated as zero-width combining
  marks. They remain visible two-column glyphs for display-width splitting,
  breakpoint slicing, padding, wrapping, and WordPress review tables.
- Invalid letter-plus-modifier runs, such as `A` followed by a standalone
  skin-tone modifier, now account for both visible glyphs.

## Source Truth

This slice owns the supervisor row `pandoc-charset-unicode-width-core-*`, which
covers byte decoding, Unicode repair, and display-width behavior needed by
Pandoc readers and writers.

The upstream-facing behavior is Pandoc/doclayout-style display-column
accounting. `doclayout` keeps emoji skin-tone modifiers attached only as part
of an emoji modifier sequence while otherwise treating the modifier as a
visible character for real-length accounting:

- `Text.DocLayout` source:
  `https://hackage-content.haskell.org/package/doclayout-0.5.0.1/docs/src/Text.DocLayout.html`

Prior accepted charset slices already covered BOMs, UTF-16, Windows-1252,
malformed UTF-8 repair, line-ending normalization, Unicode normalization forms,
display-width breakpoint splitting, display-column wrapping, emoji
presentation clusters, keycap/regional/tag sequences, emoji ZWJ variation
width, supplementary East Asian wide symbols, default-presentation BMP
wide-symbol width, decomposed Hangul Jamo width, Indic spacing-mark width,
default-ignorable controls, East Asian ambiguous-width policy, Unicode
soft-break wrapping, Unicode separators, and prepended format-control
zero-width accounting. This patch fills the bounded remaining skin-tone
modifier visibility gap.

No current-base Pandoc rework note was present. No hydrated Pandoc checkout was
available in this worktree or upstream cache, so this remains source-truth
mapping plus focused native PHP tests rather than Haskell runner parity.

## Verification

Pre-slice focused baseline:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 249 assertions, 0 failures`

Red-first focused check:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result before implementation: failed with `1 test files, 251 assertions,
    1 failures`.
  - Failure: the new skin-tone modifier case expected a standalone
    `U+1F3FD` modifier to have display width `2`, but it returned `0`.

Post-implementation verification:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 260 assertions, 0 failures`
  - Delta: `+11` focused assertions and `+1` focused PHP PASS case.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 9935 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - Result: `json ok`
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
emoji presentation width, keycap/regional/tag emoji sequence width, emoji ZWJ
variation width, supplementary East Asian wide-symbol width, default
presentation BMP wide-symbol width, decomposed Hangul Jamo width, Indic
spacing-mark width, default-ignorable control width accounting, East Asian
ambiguous-width policy, Unicode soft-break wrapping, Unicode separator
wrapping, prepended format-control zero-width accounting, BOM precedence, or
upstream-runner dependency audit work.

It only extends the charset/Unicode primitive with bounded emoji skin-tone
modifier display-column accounting.

## Follow-Up

Keep full Unicode emoji data table generation, terminal-profile-specific emoji
width variants, dictionary-based segmentation, HTML/XML parser charset
negotiation, full Unicode normalization data tables, and writer-wide automatic
Markdown wrapping as separate bounded slices.
