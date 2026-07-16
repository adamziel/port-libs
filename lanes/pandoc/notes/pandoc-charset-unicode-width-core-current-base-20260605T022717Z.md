# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T022717Z`

Base accepted HEAD: `3f2e4896500806fc52ab80fde6ec67a27c93f816`

## Behavior Added

- Added bounded Unicode soft-break handling to `UnicodeText::wrapByDisplayWidth()`.
- The display-column wrapper now treats U+200B zero-width space and U+00AD soft
  hyphen as break opportunities before force-splitting grapheme clusters.
- Returned review lines strip those break controls; a visible `-` is emitted
  only when a soft-hyphen break is actually taken.
- Extended the WordPress charset handoff smoke with a soft-break audit row for
  copied document text that contains zero-width-space or soft-hyphen artifacts.

## Source Truth

This slice owns the supervisor row `pandoc-charset-unicode-width-core-*`, which
covers byte decoding, Unicode repair, and display-width behavior needed by
Pandoc readers and writers. Previous charset notes left broader Unicode
line-breaking behavior as follow-up after byte decoding, line-ending repair,
display-width splitting, wrapping, normalization forms, emoji presentation
clusters, and ambiguous-width policy.

No hydrated Pandoc checkout was available in this worktree or the upstream
cache, so this remains static source-truth mapping plus focused native PHP tests
rather than Haskell runner parity.

## Verification

Pre-slice focused baseline:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 100 assertions, 0 failures`

Red-first focused check:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result before implementation: failed with `1 test files, 101 assertions, 1
    failure` because `wrapByDisplayWidth()` force-split through U+200B and
    U+00AD and leaked both controls into returned lines.

Post-implementation verification:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 111 assertions, 0 failures`
  - Delta: `+11` focused assertions and `+1` focused PHP PASS case.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 5748 assertions, 0 failures`
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
KaTeX, online sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted XML/HTML5 DOM support, YAML metadata,
ZIP/OPC, relationship graphs, gzip/tar/LZ4 streams, doctemplate partials,
CSL/BibTeX processing, DOCX/ODT parsing, EPUB3 package handoff, legacy DOC/CFB
extraction, table geometry span normalization, Math/TeX conversion, PDF engine
handoff planning, line-ending normalization, display-width breakpoint
splitting, display-column wrapping, Unicode normalization forms, emoji
presentation width, East Asian ambiguous-width policy, or upstream-runner
dependency audit work. It only extends the charset/Unicode width primitive with
bounded soft-break opportunities for native display-column wrapping.

## Follow-Up

Keep full Unicode line-breaking classes, dictionary-based segmentation,
terminal-profile-specific emoji variants, HTML/XML parser charset negotiation,
and writer-wide automatic Markdown wrapping policy as separate bounded slices.
