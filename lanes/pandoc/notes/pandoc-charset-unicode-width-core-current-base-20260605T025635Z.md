# Pandoc Charset Unicode Width Core Current Base

Slice: `pandoc-charset-unicode-width-core-current-base-20260605T025635Z`

Base accepted HEAD: `138f4be69644756800069e6b54dd0c178419b02d`

## Behavior Added

- Added bounded Unicode tag-character handling to `UnicodeText`.
- U+E0020 through U+E007F are now treated as default-ignorable zero-width
  characters for display-width accounting and grapheme clustering.
- Emoji tag-sequence flags, such as subdivision flags built from U+1F3F4 plus
  tag letters and U+E007F, now remain one display cluster for width, splitting,
  padding, and wrapping.
- Extended the WordPress charset handoff smoke with an emoji tag-flag audit row
  so reviewer tables do not over-pad imported flag labels.

## Source Truth

This slice owns the supervisor row `pandoc-charset-unicode-width-core-*`, which
covers byte decoding, Unicode repair, and display-width behavior needed by
Pandoc readers and writers. Earlier charset notes had already covered BOMs,
UTF-16, Windows-1252, malformed UTF-8 repair, line-ending normalization,
Unicode normalization forms, display-width breakpoints, emoji presentation
clusters, East Asian ambiguous-width policy, and soft-break wrapping. This
slice is a bounded follow-up for emoji tag sequences and default-ignorable tag
characters.

No hydrated Pandoc checkout was available in this worktree or the upstream
cache, so this remains static source-truth mapping plus focused native PHP tests
rather than Haskell runner parity.

## Verification

Pre-slice focused baseline:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 111 assertions, 0 failures`

Red-first focused check:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result before implementation: failed with `1 test files, 112 assertions,
    1 failure`
  - Failure: the new emoji tag-sequence case expected display width `2`, but
    `UnicodeText::displayWidth()` returned `8`.

Post-implementation verification:

- `php -l lanes/pandoc/src/UnicodeText.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-charset-unicode-handoff.php`
  - Result: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`
- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 121 assertions, 0 failures`
  - Delta: `+10` focused assertions and `+1` focused PHP PASS case.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 6000 assertions, 0 failures`
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
handoff planning, line-ending normalization, display-width breakpoint
splitting, display-column wrapping, Unicode normalization forms, emoji
presentation width, East Asian ambiguous-width policy, Unicode soft-break
wrapping, or upstream-runner dependency audit work. It only extends the
charset/Unicode width primitive with bounded emoji tag-sequence display
cluster handling.

## Follow-Up

Keep full Unicode line-breaking classes, dictionary-based segmentation,
terminal-profile-specific emoji variants beyond explicit presentation/tag
sequences, HTML/XML parser charset negotiation, and writer-wide automatic
Markdown wrapping policy as separate bounded slices.
