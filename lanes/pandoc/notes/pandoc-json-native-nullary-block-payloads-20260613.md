# Pandoc JSON/native nullary block payloads

Bead: `plib-9xn7o`
Date: 2026-06-13 UTC
Area: Pandoc JSON/native AST constructor completeness

## Behavior

`PandocJsonWriter` and `NativeWriter` now reject source `native` payload
reuse for block-level nullary constructors when `HorizontalRule` or `Null`
carry any present `c` sidecar, including an empty array. Readers still record
the original payload for provenance, but regenerated writer output emits the
current sidecar-free constructors:

- `['t' => 'HorizontalRule']`
- `['t' => 'Null']`

This matches the current Pandoc JSON/native AST shape and prevents inert
reviewer sidecars from keeping stale constructor content, or empty legacy
constructor members, alive after handoff.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers, Node
tooling, online services, live provider tests, or external validators were
invoked.

## Accounting

- `phpPass`: `3434 -> 3435`
- `phpFail`: `0`
- `mappedJsonNativeNullaryBlockPayloadCases`: `1 -> 2`
- `jsonNativeNullaryBlockPayloadAssertions`: `50 -> 64`

## Verification

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 3028 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 79783 assertions, 0 failures`
