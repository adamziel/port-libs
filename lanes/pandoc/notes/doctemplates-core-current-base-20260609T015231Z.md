# Doctemplates Core Current Base - Extension-Qualified Partial Aliases

Slice: `pandoc-doctemplates-core-current-base-20260609T015231Z`
Base accepted HEAD: `21742a408faf47b66c5937f3cfd9d335c203497c`

## Source Truth

- Uses the accepted Pandoc/doctemplates resource-policy source truth already recorded in this lane: extension-qualified output formats try exact resources before base resources, and template partials remain bounded native PHP resource lookups.
- This slice maps only the missing alias edge where an explicit base-extension partial call such as `components/header.html()` should be satisfied by an exact extension resource such as `components/header.html+smart` when no real base resource exists.
- If both `components/header.html+smart` and `components/header.html` exist, the explicit base resource remains preferred.
- No Pandoc executable, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external template engine, TeX/PDF engine, browser renderer, online service, live provider test, or live-service provider test was executed.

## Implementation

- Added `DocTemplate::availableRelativePartialResourcePaths()` so partial alias generation can see whether a real base resource exists in the same search directory.
- `DocTemplate::partialAliasesForResourcePath()` now adds a base-extension alias for exact extension-qualified resources only when the base resource is absent.
- Added a focused doctemplate test for exact-only explicit base-extension partial calls and explicit base-resource precedence.
- Updated the WordPress doctemplate review-packet self-test with the same fallback and precedence behavior.

## Evidence

- Rework notes: no `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` files existed for this lane.
- Baseline focused command before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 1106 assertions, 0 failures`.
- Red-first probe before implementation:
  `php <<'PHP' ... renderResource('templates/review', ['templates/review.html+smart' => '${ components/header.html() }', 'templates/components/header.html+smart' => '<header>$title$</header>' . "\n"], ['title' => 'Exact Only'], null, 'html+smart') ... PHP`
  failed with `Missing doctemplate partial components/header.html at templates/review.html+smart:1:1`.
- Final focused command:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 1108 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.
- `git diff --check -- lanes/pandoc` passed after implementation.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Added one focused PHP PASS case with two assertions.
- `lane-status.json` `phpPass`: `2089 -> 2090`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2501 -> 2502`.
- Added `mappedDoctemplateExtensionQualifiedPartialAliasCases: 1`.
- Added `doctemplateExtensionQualifiedPartialAliasAssertions: 2`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP `DocTemplate` resource map, partial alias registry, focused doctemplate tests, and lane-local WordPress doctemplate review-packet smoke.

## Non-Overlap / Follow-Up

This does not repeat accepted doctemplate comments, delimiter trimming, unbraced dollar separator scanning, variable truthiness, loops, breakable-space wrapping, parameterized pipes, partial rebinding, recursion-limit ordering, explicit nesting, default-template fallback, or broad filesystem loading. A useful follow-up would be richer partial path diagnostics or another bounded doclayout wrapping edge.
