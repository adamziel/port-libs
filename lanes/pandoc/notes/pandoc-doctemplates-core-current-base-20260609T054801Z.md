# pandoc-doctemplates-core-current-base-20260609T054801Z

## Source Truth

- Accepted base: `2c84ca27878846c6b3725d422a6af783d4bbe9c7`.
- Rework notes checked first: `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` returned no files.
- Upstream behavior source: doctemplates `0.11.0.1` `test/nest.test` and the `pNested` / `handleNesting` parser path in `src/Text/DocTemplates/Parser.hs`.
- URLs: `https://raw.githubusercontent.com/jgm/doctemplates/0.11.0.1/test/nest.test` and `https://raw.githubusercontent.com/jgm/doctemplates/0.11.0.1/src/Text/DocTemplates/Parser.hs`.

## Behavior

This slice maps the explicit `$^$` nesting case where the first nested literal continuation ends before a subsequent dedented control directive. Before the patch, the renderer keyed nesting only to the rendered output column, so this shape leaked the earlier four-space nest into the following `$for(baz)$` loop rows:

```text
$bim.zub$ $^$$foo$
          bar $sup$
$for(baz)$
1. $^$Hello $if(it)$
$it$
$endif$
$endfor$
```

The native PHP `DocTemplate` renderer now keeps the `$^$` source column boundary separately from the rendered output column. A dedented control directive that begins before that source boundary terminates explicit nesting before rendering the directive, while literal continuation text still uses the rendered output column for wrapped scalar values.

## Evidence

- Baseline before edit: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` -> `1 test files, 1140 assertions, 0 failures`.
- Red-first probe before edit: the dedented loop rendered as indented lines `    1. Hello a` and `    1. Hello b`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` -> `1 test files, 1141 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test` -> `OK wordpress doctemplate review packet`.
- PHP lint passed for changed PHP files: `DocTemplate.php`, `DocTemplateTest.php`, and `wordpress-doctemplate-review-packet.php`.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2399 -> 2400`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2790 -> 2791`.
- `mappedDoctemplateNestingCases`: `2 -> 3`.
- New focused assertion/PASS case: `+1` in `DocTemplateTest.php`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP `DocTemplate` renderer. No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external template engine, TeX/PDF engine, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap And Follow-Up

This avoids the recently accepted doctemplate parser diagnostic, partial, default-template, block-pipe composition, standalone partial line-ending, source-aligned literal continuation, and PDF/ODF handoff slices. The next non-overlapping doctemplates target remains block-pipe trailing-output parity from upstream `pad.test`.
