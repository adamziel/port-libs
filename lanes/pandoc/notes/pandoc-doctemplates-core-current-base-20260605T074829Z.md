# Pandoc doctemplates core current-base 2026-06-05T07:48:29Z

## Slice

- Aligned `PortLibs\Pandoc\DocTemplate` variable parsing with upstream
  doctemplates when an array separator follows a fully piped variable.
- Direct variable joins now accept forms such as
  `$keywords/reverse/uppercase[ / ]$` and `${ sources/rest/uppercase[, ] }`
  instead of treating `uppercase[ / ]` as an unsupported pipe name.
- Existing unpiped separators such as `$keywords[, ]$`, block-pipe quoted
  brackets, partial calls, applied partials, and multiline control-line
  swallowing remain unchanged.
- Updated the WordPress doctemplate review-packet smoke with a
  `reviewSources/rest/uppercase[ / ]` review metadata line.

## Source Truth

- Upstream `Text.DocTemplates.Parser` parses `pVar` first, including all
  variable pipes, then parses optional `pSep` for interpolated variables:
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Parser.hs
- Upstream `Text.DocTemplates.Internal` applies pipes before rendering or
  iterating values, so the separator belongs to the post-pipe value list:
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
  1 test file, 52 assertions, 0 failures.
- Red-first focused run after adding the new expectation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` failed with
  `Unsupported doctemplate pipe uppercase[ / ]`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 53 assertions, 0 failures.
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
conditionals, loop scoping, separators before pipes, `$it$`, `$^$`, automatic
multiline nesting, `$~$` breakable-space whitespace reflow, parameter-free pipe
semantics, parameterized block pipes, Unicode display-width padding,
missing/null pipe handling, resource-map partial discovery, path-style partial
lookup, applied partials, partial final-newline handling, partial recursion
guards, braced directive tokenizer behavior, alpha overflow labels, boolean
false output rendering, Unicode identifier parsing, multiline control boundary
newline swallowing, empty standalone partial line swallowing, or deterministic
map-pairs ordering. It only changes parsing of the trailing separator after a
piped variable expression.

It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, charset/Unicode, or
upstream-runner dependency audit behavior.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`pandoc-doctemplates-core` renderer and keeps behavior in the lane-local
expression parser. Full doclayout width-sensitive wrapping, richer
source-location diagnostics, filesystem-backed template discovery beyond the
existing resource map, writer-extension template selection, default-template
parity, and full upstream Pandoc runner parity remain separate activation
slices.

Root harness: not run - isolated micro-slice.
