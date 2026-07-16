# Doctemplates Core Current Base - Variable Separator Order

Slice: `pandoc-doctemplates-core-current-base-20260608T222315Z`

Accepted base: `638c2a05c9464741270d591f95240e54d5519ba1`

## Source Truth

Upstream doctemplates parser order treats interpolated variables as `pVar` followed by optional `pSep`, so pipe suffixes belong before the trailing separator. Partial calls keep the separate order `partial()[sep]/pipe`.

Reference: https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Parser.hs

## Implementation

- `DocTemplate::parseVariableExpression()` now rejects `$items[sep]/pipe$` and reports that variable separators must follow pipe suffixes.
- Existing valid variable syntax such as `$items/uppercase[ / ]$` continues to render.
- Existing applied partial syntax such as `${ rows:row()[, ]/uppercase }` continues to render.
- The WordPress review-packet self-test now covers the valid and invalid variable separator order for source-list metadata.

## Focused Evidence

- `php -l lanes/pandoc/src/DocTemplate.php` passed.
- `php -l lanes/pandoc/tests/DocTemplateTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed: `1 test files, 1069 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test` passed: `OK wordpress doctemplate review packet`.
- JSON metadata validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP doctemplate parser and renderer plus the existing lane-local WordPress review-packet smoke. No Pandoc, Cabal solver/build/test command, Haskell runner, Stack, external template engine, browser renderer, online service, live provider test, or live-service provider test was executed.

Full upstream runner parity remains gated on hydrating the pinned Pandoc/doctemplates checkout and recording a reviewed non-mutating runner plan.
