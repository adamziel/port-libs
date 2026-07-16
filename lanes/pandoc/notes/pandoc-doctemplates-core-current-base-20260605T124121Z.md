# Pandoc doctemplates core current-base 2026-06-05T12:41:21Z

## Slice

- Aligned `PortLibs\Pandoc\DocTemplate` `chomp` pipe traversal with upstream
  doctemplates.
- `chomp` now recursively applies to string leaves inside list and map values,
  so `$items/chomp[, ]$`, `$for(metadata/chomp/pairs)$...$endfor$`, and nested
  review-packet rows drop all trailing source newlines before rendering.
- Missing, null, boolean, integer, float, and non-text values keep their prior
  behavior.
- Updated the WordPress doctemplate review-packet smoke with newline-bearing
  reviewer source labels rendered through `chomp/uppercase`.

## Source Truth

- Hackage `doctemplates-0.11.0.1` documents `chomp` as a predefined pipe that
  removes trailing newlines and documents no automatic output escaping:
  https://hackage.haskell.org/package/doctemplates
- Upstream `Text.DocTemplates.Internal` implements `Chomp` through `mapDoc
  DL.chomp`, and `mapDoc` traverses `MapVal` and `ListVal` recursively before
  leaving booleans and nulls unchanged:
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
  1 test file, 61 assertions, 0 failures.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 62 assertions, 0 failures.
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
nesting, `$~$` markers, parameter-free pipe presence, deterministic map-pairs
ordering, parameterized pipes, Unicode display-width padding, missing/null pipe
handling, resource-map partial discovery, path-style partial lookup, applied
partials, partial final-newline handling, partial recursion guards, braced
directive tokenizer behavior, alpha overflow labels, boolean false output,
Unicode identifier parsing, multiline control boundary newline swallowing,
empty standalone partial line swallowing, or dedented nesting termination. It
only changes recursive traversal for the existing `chomp` pipe.

It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, charset/Unicode, or
upstream-runner dependency audit behavior.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`pandoc-doctemplates-core` renderer and its recursive PHP array traversal. Full
doclayout line wrapping for `$~$`, richer source-location diagnostics,
filesystem-backed template discovery beyond the existing resource map,
writer-extension template selection, default-template parity, and full upstream
Pandoc runner parity remain separate activation slices.

Root harness: not run - isolated micro-slice.
