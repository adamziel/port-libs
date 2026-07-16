# Pandoc doctemplates core current-base 2026-06-05T04:40:37Z

## Slice

- Aligned `PortLibs\Pandoc\DocTemplate` missing variable pipe handling with
  upstream doctemplates null-value pipe resolution.
- Missing lookups with a pipe suffix now flow through the pipe chain as `null`
  instead of returning before pipe application.
- This preserves Pandoc-style output for `$missing/length$` as `0` and
  `$missing/left 6 "[" "]"$` as a padded blank block, while non-producing
  pipes such as `uppercase` and `rest` still render empty or iterate zero
  times.
- Updated the WordPress doctemplate review-packet smoke so an omitted
  `review-id` still renders a fixed-width placeholder in the reviewer header.

## Source Truth

- Hackage `doctemplates-0.11.0.1` documents that pipes transform variables and
  partials, `length` returns zero for non-list/map/text values, and block
  alignment pipes render textual/null-like values in fixed-width blocks:
  https://hackage.haskell.org/package/doctemplates
- Upstream `Text.DocTemplates.Internal` applies pipes after `multiLookup`;
  `NullVal` reaches `ToLength` as `0`, and block pipes convert `NullVal` to an
  empty simple value before alignment:
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
  1 test file, 44 assertions, 0 failures.
- Red-first focused run after adding the missing/null pipe expectation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` failed with
  1 test file, 45 assertions, 1 failure because missing `length` and missing
  block-alignment output rendered empty, and `$if(missing/length)$` did not
  take the truthy branch.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 45 assertions, 0 failures.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, delimiters,
conditionals, loop scoping, separators, `$it$`, `$^$`, automatic multiline
nesting, `$~$` markers, parameter-free pipes on present values,
parameterized pipes on present/null explicit values, Unicode display-width
padding, inline partial arrays, resource-map partial discovery, path-style
partial names, applied partials, partial final-newline handling, partial
recursion guards, braced directive tokenizer behavior, alpha overflow labels,
or boolean false output rendering. It only changes missing lookup behavior
when a pipe suffix is present.

It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, charset/Unicode, or
upstream-runner dependency audit behavior.

## Dependency Closure

No new external support component is needed. This reuses the existing native
PHP `pandoc-doctemplates-core` renderer and keeps behavior in lane-local
variable resolution. Full doclayout line wrapping for `$~$`,
filesystem-backed template discovery beyond the existing resource map,
writer-extension template selection, default-template parity, richer parser
source-location diagnostics, and full upstream Pandoc runner parity remain
separate activation slices.

Root harness: not run - isolated micro-slice.
