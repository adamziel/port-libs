# Pandoc doctemplates core current-base 2026-06-05T07:18:09Z

## Slice

- Aligned `PortLibs\Pandoc\DocTemplate` `$~$` breakable-space regions with
  upstream doctemplates' literal whitespace reflow behavior.
- Literal spaces, tabs, CR, LF, and CRLF runs that occur while `$~$` mode is
  active now render as a single ordinary space in this bounded no-doclayout
  renderer.
- The tokenizer keeps raw breakable text for control-boundary parsing, so
  multiline `$for(...)$`, `$sep$`, `$if(...)$`, and `$endif$` newline
  swallowing continues to work inside breakable-space regions.
- Updated the WordPress doctemplate review-packet smoke so its summary and
  warning-row templates use multiline `$~$` regions while still rendering
  compact reviewer HTML.

## Source Truth

- Upstream `Text.DocTemplates.Parser` toggles `breakingSpaces` for `$~$`,
  converts literal whitespace runs through `toBreakable`, and emits
  `DL.BreakingSpace` for newlines while the toggle is active:
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Parser.hs
- Upstream `Text.DocTemplates.Internal` renders templates through
  `Text.DocLayout`, where breakable spaces are layout tokens rather than
  literal template newlines or tabs:
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Internal.hs
- No Pandoc binary, Haskell test binary, Cabal build, Word, LibreOffice,
  `zip`/`unzip`, `tar`, `lz4`, external template engine, TeX/PDF engine,
  browser renderer, roff, Typst, MathJax, KaTeX, online sanitizer, or online
  conversion service was executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 51 assertions, 0 failures.
- Red-first focused run after adding the new expectation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` failed with
  1 test file, 52 assertions, 1 failure because literal newlines and tabs
  inside `$~$` regions were preserved.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 52 assertions, 0 failures.
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
conditionals, loop scoping, separators, `$it$`, `$^$`, automatic multiline
nesting, parameter-free pipes, parameterized pipes, Unicode display-width
padding, missing/null pipe handling, resource-map partial discovery,
path-style partial lookup, applied partials, partial final-newline handling,
partial recursion guards, braced directive tokenizer behavior, alpha overflow
labels, boolean false output rendering, Unicode identifier parsing, multiline
control boundary newline swallowing, empty standalone partial line swallowing,
or deterministic map-pairs ordering. It only changes literal whitespace
rendering while breakable-space mode is active.

It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, charset/Unicode, or
upstream-runner dependency audit behavior.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`pandoc-doctemplates-core` renderer and keeps behavior in the lane-local
token stream. Full doclayout line wrapping decisions for configured output
widths, richer source-location diagnostics, filesystem-backed template
discovery beyond the existing resource map, writer-extension template
selection, default-template parity, and full upstream Pandoc runner parity
remain separate activation slices.

Root harness: not run - isolated micro-slice.
