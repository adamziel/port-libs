# Pandoc doctemplates core current-base 2026-06-05T10:06:59Z

## Slice

- Aligned `PortLibs\Pandoc\DocTemplate` explicit `$^$` nesting column
  calculation with Unicode display columns.
- `$^$` now uses the existing native `UnicodeText::displayWidth()` helper for
  the rendered prefix before the nested value, with tabs preserved as one
  layout column.
- Added focused doctemplate coverage for CJK double-width text,
  decomposed-combining labels, and emoji presentation labels before multiline
  nested values.
- Updated the WordPress doctemplate review-packet smoke so a multilingual
  source-summary note is nested under an accented review title by display
  column instead of UTF-8 byte count.

## Source Truth

- Upstream `Text.DocTemplates.Parser` parses `$^$` as `Nested` and records the
  current source column for nested rendering:
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Parser.hs
- Upstream `Text.DocTemplates.Internal` renders `Nested` from the current
  `Text.DocLayout` render-state column rather than byte offsets:
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Internal.hs
- Prior accepted `pandoc-charset-unicode-width-core` evidence established the
  lane-local native display-width primitive for CJK, combining-mark, and emoji
  layout columns. This slice reuses that existing support component.
- No Pandoc binary, Haskell test binary, Cabal build, Word, LibreOffice,
  `zip`/`unzip`, `tar`, `lz4`, external template engine, TeX/PDF engine,
  browser renderer, roff, Typst, MathJax, KaTeX, online sanitizer, online
  conversion service, or live service was executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 57 assertions, 0 failures.
- Red-first display-column probe before implementation failed because
  rendering `<p>魚 $^$$body$</p>` indented the second line with seven spaces;
  the display-column expectation is six spaces.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 58 assertions, 0 failures.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.
- PHP lint passed for `lanes/pandoc/src/DocTemplate.php`,
  `lanes/pandoc/tests/DocTemplateTest.php`, and
  `lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`.
- Lane JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed with no output.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, delimiters,
conditionals, loop scoping, separators before or after pipes, `$it$`,
automatic standalone multiline nesting, `$~$` breakable-space whitespace
reflow, parameter-free pipes, parameterized block pipes, display-width padding,
missing/null pipe handling, resource-map partial discovery, path-style partial
lookup, applied partial parsing and rebinding, partial final-newline handling,
partial recursion guards, braced directive tokenizer behavior, alpha overflow
labels, boolean false output rendering, Unicode identifier parsing, multiline
control boundary newline swallowing, empty standalone partial line swallowing,
deterministic map-pairs ordering, trailing separators after piped variables, or
included-partial final-LF omission. It only changes the current-column
calculation used by explicit `$^$` nesting.

It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, charset/Unicode source
primitives, or upstream-runner dependency audit behavior.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`UnicodeText` display-width helper inside the existing
`pandoc-doctemplates-core` renderer. Full doclayout width-sensitive wrapping,
broader `$^$` nested-template lifetime parity, richer source-location
diagnostics, filesystem-backed template discovery beyond the existing resource
map, writer-extension template selection, default-template parity, and full
upstream Pandoc runner parity remain separate activation slices.

Root harness: not run - isolated micro-slice.
