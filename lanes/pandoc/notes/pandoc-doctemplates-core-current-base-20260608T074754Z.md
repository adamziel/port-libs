# Pandoc Doctemplates Core Current-Base Explicit Nesting Persistence

Slice: `pandoc-doctemplates-core-current-base-20260608T074754Z`
Base accepted HEAD: `abd1af5843ccdf0a6730b63402c30abf96a3e9f7`
Lane: `pandoc`

## Source Truth

- No current `port-pandoc` rework note existed for this slice.
- Local upstream cache `/home/claude/port-libs/.upstream-cache/pandoc` was absent.
- Primary source used: upstream `Text.DocTemplates.Parser` in `jgm/doctemplates`, where `$^$` is parsed through `pNested` and the nested column remains active for the nested template until source indentation dedents below that column: https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Parser.hs
- No Pandoc, Cabal solver/build/test command, Haskell runner, external template engine, browser renderer, online service, live provider test, or live-service provider test was executed.

## Implementation

- `DocTemplate` now keeps explicit `$^$` nesting active across successive rendered chunks and source-aligned variables until template text dedents below the original nested column.
- Template text nesting now returns the rendered text plus whether the nested region is still active.
- The WordPress review-packet smoke now exercises a multiline owner value on a source-aligned line inside the same explicit nested region.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` -> `1 test files, 689 assertions, 0 failures`.
- Red-first/incomplete-patch check: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` -> `1 test files, 690 assertions, 1 failures`; the failure showed a later multiline value still nested after source dedent.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` -> `1 test files, 690 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test` -> `OK wordpress doctemplate review packet`.
- PHP lint passed for changed PHP files.
- JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.
- Focused delta: `+1` PHP PASS case, `+1` focused assertion, mapped denominator `1987 -> 1988`, lane `phpPass` `1566 -> 1567`.

## Non-Overlap

- Does not change comments, delimiter whitespace, variable lookup, truthiness, loop collection, automatic nesting, final newline stripping, pipes, separators, partial resolution, default templates, filesystem resources, source-location diagnostics, Unicode/colon/digit metadata, or external runners.
- Owns only explicit `$^$` nested-region persistence across source-aligned variables.

## Dependency Closure

- No new support component is needed; this reuses the native `DocTemplate` renderer, focused tests, and the WordPress doctemplate example.
- Full upstream runner parity remains blocked/out of scope for this slice because no hydrated pinned Pandoc/doctemplates runner and reviewed non-mutating Cabal plan were available.
- Root harness: not run - isolated micro-slice.
