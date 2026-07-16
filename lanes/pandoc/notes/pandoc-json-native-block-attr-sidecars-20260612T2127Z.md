# Pandoc JSON/native block Attr sidecar payloads

Bead: `plib-3gwo3`
Date: 2026-06-12 UTC
Area: Pandoc JSON/native AST constructor completeness

## Behavior

This slice adds focused regression coverage for NativeReader-sourced block
constructors whose `Attr` tuples carry inert sidecar slots beyond the canonical
identifier, class, and key/value fields.

Covered block constructors:
- `Header`
- `CodeBlock`
- `Div`

Unchanged rebuilt wrappers preserve the full native block payload through both
`PandocJsonWriter` and `NativeWriter`, including wrapper sidecars and `Attr`
tuple sidecars. Edited block content regenerates canonical `Attr` tuples and
drops stale wrapper sidecars instead of leaking source-only review payloads.

No Pandoc binary, JSON filters, Cabal/Haskell runners, office suites, TeX/PDF
engines, browser renderers, `zip`/`unzip`, external validators, online services,
live provider tests, or live-service provider tests were invoked.

## Accounting

- `phpPass`: `3275 -> 3276`
- `phpFail`: `0`
- `mappedJsonNativeBlockAttrSidecarPayloadCases`: `1`
- `jsonNativeBlockAttrSidecarPayloadAssertions`: `30`

## Verification

Final verification was run after rebasing onto `origin/main` `dbd589212`.

- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `jq empty lanes/pandoc/lane-status.json`
- `git diff --check`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 1895 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 73454 assertions, 0 failures`
