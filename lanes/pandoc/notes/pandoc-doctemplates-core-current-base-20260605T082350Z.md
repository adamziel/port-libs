# Pandoc doctemplates core current-base 2026-06-05T08:23:50Z

## Slice

- Aligned `PortLibs\Pandoc\DocTemplate` `$--` comment handling with upstream
  doctemplates when the comment marker starts after column 1.
- Column-one comments still swallow their line ending.
- Comments preceded by indentation or inline text now preserve that preceding
  text plus the original line ending, including CRLF, instead of treating the
  line as a standalone comment block.
- Updated the WordPress doctemplate review-packet smoke with an indented
  reviewer comment line whose whitespace-only output line remains present.

## Source Truth

- Hackage `doctemplates-0.11.0.1` documents `$--` comments as omitting text
  through the end of the line:
  https://hackage.haskell.org/package/doctemplates
- Upstream `Text.DocTemplates.Parser` consumes the line ending only when the
  `$--` comment starts in source column 1; otherwise the following newline is
  parsed normally:
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Parser.hs
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
  1 test file, 53 assertions, 0 failures.
- Red-first focused run after adding the new expectation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` failed
  because the indented comment-only line was missing; the run reported 1 test
  file, 54 assertions, 1 failure.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 55 assertions, 0 failures.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.

## Non-Overlap

This slice does not repeat accepted doctemplate delimiters, variable lookup,
conditionals, loop scoping, separators before or after pipes, `$it$`, `$^$`,
automatic multiline nesting, `$~$` breakable-space whitespace reflow,
parameter-free pipes, parameterized block pipes, Unicode display-width
padding, missing/null pipe handling, resource-map partial discovery,
path-style partial lookup, applied partials, partial final-newline handling,
partial recursion guards, braced directive tokenizer behavior, alpha overflow
labels, boolean false output rendering, Unicode identifier parsing, multiline
control boundary newline swallowing, empty standalone partial line swallowing,
deterministic map-pairs ordering, or trailing separators after piped variables.
It only changes comment-tokenizer treatment for `$--` markers that do not
start in column 1.

It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, charset/Unicode, or
upstream-runner dependency audit behavior.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`pandoc-doctemplates-core` renderer and keeps behavior in the lane-local
tokenizer. Full doclayout width-sensitive wrapping, richer source-location
diagnostics, filesystem-backed template discovery beyond the existing resource
map, writer-extension template selection, default-template parity, partial
CR-only final-newline parity, and full upstream Pandoc runner parity remain
separate activation slices.

Root harness: not run - isolated micro-slice.
