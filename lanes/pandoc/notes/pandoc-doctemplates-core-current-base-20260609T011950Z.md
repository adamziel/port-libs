# Pandoc Doctemplates Core Current Base

- Micro-slice: `pandoc-doctemplates-core-current-base-20260609T011950Z`
- Base accepted HEAD: `403bbfa850b87a30b18d0488738d4e785be58580`
- Scope: native PHP `DocTemplate` roman pipe zero rendering only.

## Source Truth

- Upstream doctemplates renderer source: `Text.DocTemplates.Internal` `applyPipe ToRoman` and `toRoman`.
- Reference: https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Internal.hs
- Upstream `toRoman 0` returns an empty text value. Values in `1..3999` render as lowercase Roman numerals; values outside that bounded range and non-numeric text remain unchanged.

## Implementation

- `DocTemplate::pipeRomanText()` now renders numeric zero as an empty marker.
- Existing behavior remains unchanged for valid Roman numerals, overflow values such as `4000`, and non-numeric text.
- Added focused core coverage for direct interpolation, list iteration with separators, uppercase composition, and block padding after a zero Roman marker.
- Updated the WordPress doctemplate review-packet self-test to assert zero-priority Roman marker padding without changing the main sample packet output.

## Evidence

- Rework notes: no current `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` files existed before editing.
- Red-first probe before implementation: `php -r 'require "tools/bootstrap.php"; $r=new PortLibs\Pandoc\DocTemplate(); echo $r->render("<\$n/roman\$> <\$z/roman\$> <\$bad/roman\$>", ["n"=>0,"z"=>4000,"bad"=>"draft"]), "\n";'` rendered `<0> <4000> <draft>`.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with `1 test files, 1096 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with `1 test files, 1099 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test` passed with `OK wordpress doctemplate review packet`.
- PHP lint passed for `lanes/pandoc/src/DocTemplate.php`, `lanes/pandoc/tests/DocTemplateTest.php`, and `lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`.

## Status Delta

- `lane-status.json` `phpPass`: `2029 -> 2030`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2444 -> 2445`.
- `mappedDoctemplateParameterFreePipeCases`: `11 -> 12`.
- Added `doctemplateRomanZeroPipeAssertions`: `3`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP doctemplate parser/renderer and WordPress doctemplate review-packet example. No Pandoc, Cabal solver/build/test command, Haskell runner, external template engine, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-overlap

This does not repeat accepted doctemplate comments, delimiter trimming, comments whitespace, variable truthiness, loop separators, breakable spaces, parameterized padding pipes, partial rebinding, resource/default-template fallback, default template resource work, alpha overflow labels, or explicit nesting behavior. It covers only `roman` pipe zero rendering.
