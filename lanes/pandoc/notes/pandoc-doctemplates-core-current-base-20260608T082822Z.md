# Pandoc Doctemplates Core Current-Base Default Basename Fallback

Slice: `pandoc-doctemplates-core-current-base-20260608T082822Z`
Base accepted HEAD: `d26ecc00d103df4f8bfc0a6c5fcecf9fae053506`
Lane: `pandoc`

## Source Truth

- No current `port-pandoc` rework note existed for this slice.
- Primary source used: upstream Pandoc `Text.Pandoc.Templates.getTemplate` falls back to bundled `data/templates/` using `takeFileName fp` for missing relative template paths: https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Templates.hs
- Primary support-library source used: upstream doctemplates resource loading stays caller-supplied and bounded through resource lookup, so this lane keeps custom resources winning before default fallback: https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Internal.hs
- No Pandoc, Cabal solver/build/test command, Haskell runner, external template engine, browser renderer, online service, live provider test, or live-service provider test was executed.

## Implementation

- `DocTemplate::defaultTemplateResource()` now lets any missing relative resource path fall back to a bundled default template by basename.
- Caller-provided resources still win before fallback.
- Absolute resource paths still fail closed and do not reach bundled defaults.
- The WordPress doctemplate review-packet smoke now exercises a relative review-packet default HTML5 template fallback path.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` -> `1 test files, 690 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` -> `1 test files, 690 assertions, 1 failures`; the new case failed on `Missing doctemplate resource review-packets/default.html5`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` -> `1 test files, 696 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test` -> `OK wordpress doctemplate review packet`.
- Syntax checks passed:
  - `php -l lanes/pandoc/src/DocTemplate.php`
  - `php -l lanes/pandoc/tests/DocTemplateTest.php`
  - `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`
- JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.
- Focused delta: `+1` PHP PASS case, `+6` focused assertions, mapped denominator `2000 -> 2001`, lane `phpPass` `1579 -> 1580`.

## Non-Overlap

- Does not change delimiter parsing, comments, conditionals, loops, pipes, partial parsing, filesystem discovery, extension-qualified output format resolution, bundled template contents, or any default template bodies.
- Owns only default template resource fallback by basename for missing relative resource paths.

## Dependency Closure

- No new support component is needed; this reuses the native `DocTemplate` resource resolver, bundled default template resources, focused tests, and the WordPress doctemplate review-packet example.
- Full upstream runner parity remains out of scope for this slice because Pandoc/Cabal/Haskell runners and external template engines were intentionally not executed.
- Root harness: not run - isolated micro-slice.
