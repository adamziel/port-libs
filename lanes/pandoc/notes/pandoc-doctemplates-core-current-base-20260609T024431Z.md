# Pandoc doctemplates core current-base recursive partial loop sentinel

Slice: `pandoc-doctemplates-core-current-base-20260609T024431Z`
Base accepted HEAD: `12507a9792ad5cde3ccd9d84d97d5835d2a8ef77`

## Source Truth

- Upstream `jgm/doctemplates` fixture `test/loop-in-partial.test` renders a
  recursively included bare partial as exactly `(loop)` followed by one line
  ending:
  https://raw.githubusercontent.com/jgm/doctemplates/master/test/loop-in-partial.test
- Upstream partial sources for that fixture are `test/loop1.txt` and
  `test/loop2.txt`, each recursively including the other:
  https://raw.githubusercontent.com/jgm/doctemplates/master/test/loop1.txt
  https://raw.githubusercontent.com/jgm/doctemplates/master/test/loop2.txt
- Upstream parser behavior for bare partials is in
  `Text.DocTemplates.Parser.pBarePartial` / `pPartial`; over-limit partial
  nesting becomes literal `(loop)`:
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Parser.hs
- No Pandoc executable, Cabal solver/build/test command, Haskell runner, Word,
  LibreOffice, zip/unzip, external template engine, TeX/PDF engine, browser
  renderer, online service, live provider test, or live-service provider test
  was executed for progress.

## Implementation

- Added focused coverage for the upstream recursive bare-partial fixture shape:
  `$loop1()$` on a standalone line with mutually recursive `loop1` / `loop2`
  partials.
- Updated native `DocTemplate` rendering so a standalone bare partial that
  resolves to the recursion-limit `(loop)` sentinel consumes one following line
  ending instead of leaking an extra blank line.
- Left existing custom partial final-newline behavior intact; the focused
  doctemplate suite still covers regular single-line and double-newline
  partial inclusions.

## Evidence

- Rework notes: no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  note targeted this doctemplate slice.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  `1 test files, 1114 assertions, 0 failures`.
- Red-first focused command after adding the upstream fixture assertion and
  before implementation failed with expected `(loop)\n` but actual
  `(loop)\n\n`.
- Final focused verification:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  `1 test files, 1115 assertions, 0 failures`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Added one focused PHP PASS case with one assertion for upstream recursive
  bare-partial loop sentinel newline handling.
- `lane-status.json` `phpPass`: `2177 -> 2178`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2591 -> 2592`.
- Manifest doctemplate partial counters moved to
  `mappedDoctemplatePartialCases: 5`, `doctemplatePartialAssertions: 6`, with
  explicit recursive-partial keys
  `mappedDoctemplateRecursivePartialCases: 1` and
  `doctemplateRecursivePartialAssertions: 1`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`DocTemplate` parser/renderer and focused doctemplate tests. Full upstream
Pandoc/Haskell runner parity remains outside this isolated micro-slice because
it requires a hydrated Pandoc checkout and Cabal-built Tasty runners.

## Non-Overlap / Follow-Up

This does not repeat accepted doctemplate comments, delimiter trimming,
truthiness, value rendering, loops, separator rendering, breakable-space
wrapping, block pipes, applied partial rebinding, filesystem resources,
default templates, extension-qualified partial aliases, or DOCX/OpenXML work.
Remaining doctemplate follow-up is broader upstream `nest.test` / `pad.test`
fixture parity, which is intentionally left as a separate bounded parser and
layout slice.
