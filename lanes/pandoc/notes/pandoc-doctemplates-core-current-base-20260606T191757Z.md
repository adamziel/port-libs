# Pandoc doctemplates core current-base 2026-06-06T19:17:57Z

## Slice

- Aligned `PortLibs\Pandoc\DocTemplate` `alpha` pipe output with bounded
  upstream doctemplates support-library semantics for long reviewer lists.
- Positive integer text now renders as a single one-based modulo `a..z` marker:
  `25 -> y`, `26 -> z`, `27 -> a`, and `28 -> b`.
- Updated the focused doctemplate test and the WordPress review-packet smoke so
  list-derived reviewer markers wrap after `Z` instead of producing
  spreadsheet-style `AA` / `AB` labels.

## Source Truth

- Hackage `doctemplates-0.11.0.1` documents `alpha` as a predefined pipe that
  converts integer-like text to lowercase `a..z` modulo 26 for enumeration:
  https://hackage.haskell.org/package/doctemplates
- Upstream `Text.DocTemplates.Internal` implements `ToAlpha` as a textual
  single-glyph transformation, not as a multi-character spreadsheet label:
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Internal.hs
- No Pandoc binary, Cabal solver/build/test command, Haskell runner, external
  template engine, browser renderer, online sanitizer, online service, live
  provider test, or live-service provider test was executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 273 assertions, 0 failures.
- Red-first focused run after updating the alpha expectations:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` failed with
  1 test file, 271 assertions, 1 failure because `27` and `28` still rendered
  as `aa` and `ab`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 274 assertions, 0 failures.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, delimiters,
conditionals, loop scoping, separators, `$it$`, `$^$`, automatic multiline
nesting, `$~$` markers, missing/null pipe handling, deterministic map pairs,
parameterized block padding, Unicode display-width padding, partial discovery,
applied partial rebinding, partial final-newline handling, recursion guards,
braced directive/separator parsing, default Markdown/CommonMark templates, or
filesystem resource loading. It only corrects `alpha` pipe overflow behavior
for positive integer text and adds a long-list review marker case.

It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, charset/Unicode, or
upstream-runner dependency audit behavior.

## Dependency Closure

No new support component is needed. This reuses the native PHP
`DocTemplate` pipe renderer, the focused doctemplate test harness, and the
WordPress doctemplate review-packet example. Full Pandoc default-template data
file parity, full doclayout value modeling, richer partial diagnostics,
external template engines, and upstream Pandoc/Haskell runner parity remain
separate bounded follow-up work.

Root harness: not run - isolated micro-slice.
