# Pandoc JSON/native nullary block payloads

Bead: `plib-k2n6d`
Date: 2026-06-13 UTC
Area: Pandoc JSON/native AST constructor completeness

## Behavior

`PandocJsonWriter` and `NativeWriter` now treat `HorizontalRule` and `Null` as
true nullary block constructors before reusing source native provenance. Readers
still preserve the original native payload for review, but writers regenerate
canonical nullary block constructors when a source payload carries stale `c`
content, dropping any wrapper sidecars attached to that stale payload.

This closes one bounded JSON/native constructor-completeness edge for block
payload reuse. Broader fixture parity, unsupported constructor surfaces, and
additional table/citation/metadata round-trip edges remain open.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.

## Accounting

- `phpPass`: `3361 -> 3362`
- `phpFail`: `0`
- `mappedJsonNativeNullaryBlockPayloadCases`: `+1`
- `jsonNativeNullaryBlockPayloadAssertions`: `+10`

## Verification

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 2277 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 75778 assertions, 0 failures`
