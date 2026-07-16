# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T042830Z`

Base accepted HEAD: `35be73b28497b6deea90611ec775bcda01acda68`

## Behavior Added

- Extended `UnicodeText::wrapByDisplayWidth()` with bounded Unicode separator
  wrapping.
- U+3000 ideographic space, U+2003 em space, and adjacent line-breakable
  Unicode space separators are now preferred wrap points instead of being
  force-split as ordinary token content.
- U+2028 line separator and U+2029 paragraph separator now reset display-line
  wrapping like hard line breaks.
- U+00A0 no-break space and U+202F narrow no-break space remain attached to
  their tokens, so reviewer labels and page references are not treated as
  ordinary breakable spaces.
- Extended `examples/wordpress-charset-unicode-handoff.php` with a Unicode
  separator audit row for WordPress review packets.

## Source Truth

This slice owns the supervisor row `pandoc-charset-unicode-width-core-*`, which
covers byte decoding, Unicode repair, and display-width behavior needed by
Pandoc readers and writers. Earlier charset notes left broader Unicode
line-breaking as a follow-up after BOM precedence, UTF-16/Windows-1252 decoding,
malformed UTF-8 repair, line-ending normalization, Unicode normalization,
display-width splitting/wrapping, emoji presentation and tag clusters,
East Asian ambiguous-width policy, soft breaks, and default-ignorable
display-width accounting.

No current-base Pandoc rework note was present. No hydrated Pandoc checkout was
available in this worktree or the upstream cache, so this remains static
source-truth mapping plus focused native PHP tests rather than Haskell runner
parity.

## Verification

Pre-slice focused baseline:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 142 assertions, 0 failures`
  - Baseline PASS lines: 17.

Red-first focused check:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result before implementation: failed with `1 test files, 143 assertions,
    1 failures`.
  - Failure: the new Unicode separator case expected U+3000, U+2003, U+2028,
    and U+2029 to create display wrap boundaries, but they were force-split
    through ordinary token content.

Post-implementation verification:

- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: no syntax errors.
- `php -r '$files=["lanes/pandoc/lane-status.json","lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"]; foreach ($files as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
  - Result: both Pandoc JSON files decoded successfully.
- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 153 assertions, 0 failures`
  - Delta: `+11` focused assertions and `+1` focused PHP PASS case.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 6978 assertions, 0 failures`
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
handoff planning, line-ending normalization, Unicode normalization,
display-width breakpoint splitting, display-column wrapping, emoji presentation
width, emoji tag sequences, default-ignorable width accounting, East Asian
ambiguous-width policy, Unicode soft-break wrapping, BOM precedence, or
upstream-runner dependency audit work. It only extends the charset/Unicode
width primitive with bounded Unicode separator wrapping.

## Follow-Up

Keep dictionary-based segmentation, full Unicode line-breaking classes beyond
bounded separator spaces and hard separators, terminal-profile-specific emoji
variants, HTML/XML parser charset negotiation, default-reader normalization
policy, and writer-wide automatic Markdown wrapping as separate bounded slices.
