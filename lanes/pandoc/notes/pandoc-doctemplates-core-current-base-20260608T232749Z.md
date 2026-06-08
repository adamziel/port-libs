# Pandoc Doctemplates Core Current Base: Typst Definitions Resource

Base accepted HEAD: `72ddd104de73563cbfd9ef3ec17976bf6afc1676`

Micro-slice: `pandoc-doctemplates-core-current-base-20260608T232749Z`

## Behavior

DocTemplate now exposes the upstream Typst `definitions.typst` bundled resource as a bounded native PHP default template resource. This covers direct `templates/definitions.typst` lookup, basename fallback, `${ definitions.typst() }` default partial fallback inside custom Typst templates, and caller-supplied override precedence.

Source truth: `https://raw.githubusercontent.com/jgm/pandoc-templates/master/definitions.typst`

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with `1 test files, 1077 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with `1 test files, 1092 assertions, 0 failures`.
- Added one TestRunner PASS case and 15 focused assertions.
- Example smoke: `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test` passed.
- PHP lint passed for the changed PHP files.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`: `benchmarkDenominator.mapped` `2389 -> 2390`.
- `mappedDoctemplateDefaultTemplateCases` `1 -> 2`.
- `doctemplateDefaultTemplateAssertions` `9 -> 24`.
- `lanes/pandoc/lane-status.json`: `phpPass` `1968 -> 1969`.

## Non-Overlap

This slice does not touch prior doctemplate parser behavior, Markdown/CommonMark default fallbacks, Beamer/MS/man default resources, partial rebinding, wrapping, braced separators, or XML/HTML5 DOM work. It is limited to the missing upstream Typst definitions resource needed by richer Typst review-packet templates.

## Dependency Closure

No new support component is needed. The patch reuses the native DocTemplate resource resolver, bundled default-template fallback, partial fallback, and existing WordPress doctemplate review example. No Pandoc, Cabal solver/build/test command, Haskell runner, external template engine, Typst/PDF engine, browser renderer, online service, live provider test, or live-service provider test was executed.
