# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T032755Z`

Base accepted HEAD: `b6d80ef86c77afda76f2318400f9167f2fb82004`

## Behavior Added

- Added bounded default-ignorable display-width handling for U+00AD soft
  hyphen and U+FEFF zero-width no-break/BOM characters in `UnicodeText`.
- These controls now preserve source text while contributing zero columns to
  display width, grapheme-safe splitting, padding, wrapping, and East Asian
  wide-policy accounting.
- Extended the WordPress charset handoff smoke with a default-ignorable audit
  row so review tables expose preserved controls without over-padding imported
  labels.

## Source Truth

This slice owns the supervisor row `pandoc-charset-unicode-width-core-*`, which
covers byte decoding, Unicode repair, and display-width behavior needed by
Pandoc readers and writers. Earlier charset notes already covered BOMs,
UTF-16, Windows-1252, malformed UTF-8 repair, line-ending normalization,
Unicode normalization forms, display-width breakpoints, emoji presentation
clusters, East Asian ambiguous-width policy, soft-break wrapping, and emoji tag
sequences. This is a bounded follow-up for default-ignorable controls that
affect display columns in Pandoc-style layout helpers.

No current-base Pandoc rework note was present. No hydrated Pandoc checkout was
available in this worktree or the upstream cache, so this remains static
source-truth mapping plus focused native PHP tests rather than Haskell runner
parity.

## Verification

Pre-slice focused baseline:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 121 assertions, 0 failures`

Red-first focused check:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result before implementation: failed with `1 test files, 122 assertions,
    1 failure`
  - Failure: the new default-ignorable case expected
    `UnicodeText::displayWidth("soft\u{00AD}hyphen")` to return `10`, but it
    returned `11`.

Post-implementation verification:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 129 assertions, 0 failures`
  - Delta: `+8` focused assertions and `+1` focused PHP PASS case.
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
display-width breakpoint splitting, display-column wrapping, emoji
presentation width, emoji tag sequences, East Asian ambiguous-width policy, or
upstream-runner dependency audit work. It only extends the charset/Unicode
width primitive with bounded default-ignorable display-column accounting.

## Follow-Up

Keep broader Unicode line-breaking classes, dictionary-based segmentation,
terminal-profile-specific emoji variants beyond explicit presentation/tag/
default-ignorable sequences, HTML/XML parser charset negotiation, and
writer-wide automatic Markdown wrapping policy as separate bounded slices.
