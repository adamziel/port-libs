# Pandoc doctemplates core current-base 2026-06-05T11:07:27Z

## Slice

- Aligned `PortLibs\Pandoc\DocTemplate` explicit `$^$` nesting for
  source-aligned literal continuation lines.
- When a pending `$^$` marker reaches a template text chunk with a newline,
  the renderer now drops the source indentation already accounted for by the
  captured nesting column before applying the nested output indent.
- Added focused coverage for a WordPress-review list row whose second template
  source line is aligned under `$^$` and should not be double-indented.
- Updated the WordPress doctemplate review-packet smoke with a reviewer status
  continuation line that exercises the same source-aligned nesting path.

## Source Truth

- Hackage `doctemplates-0.11.0.1` documents `$^$` as the directive for nested
  content and shows aligned literal continuation lines under the same nesting
  point: https://hackage.haskell.org/package/doctemplates
- Upstream `Text.DocTemplates.Parser` stores `nestedCol` for `$^$` and
  `pEndline` skips source spaces/tabs that are before that source column:
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Parser.hs
- Upstream `Text.DocTemplates.Internal` renders `Nested` from the current
  render-state column rather than adding source indentation twice:
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Internal.hs
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
  1 test file, 59 assertions, 0 failures.
- Red-first focused run after adding the new expectation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` failed with
  1 test file, 60 assertions, 1 failure because the literal continuation line
  rendered with fourteen spaces instead of seven.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 60 assertions, 0 failures.
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
deterministic map-pairs ordering, trailing separators after piped variables,
included-partial final-LF omission, explicit `$^$ display-width calculation,
or literal prefixes before multiline variables.

It only changes pending `$^$` handling for literal template text chunks whose
source continuation-line indentation is already aligned to the nesting column.
It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, charset/Unicode
source primitives, or upstream-runner dependency audit behavior.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`DocTemplate` renderer and the existing `UnicodeText` display-column support.
Full source-position nested-template termination across dedented source lines,
doclayout width-sensitive wrapping, richer source-location diagnostics,
filesystem-backed template discovery beyond the existing resource map,
writer-extension template selection, default-template parity, and full upstream
Pandoc runner parity remain separate activation slices.

Root harness: not run - isolated micro-slice.
