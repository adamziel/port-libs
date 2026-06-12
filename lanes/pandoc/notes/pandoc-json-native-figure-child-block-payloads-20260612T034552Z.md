# Pandoc JSON/native figure child block payloads

Bead: `plib-ne43e`
Date: 2026-06-12 UTC
Area: Pandoc JSON/native AST constructor completeness

## Behavior

`PandocJsonWriter` now routes Figure child blocks through the same guarded block
payload encoder used by top-level blocks and `NativeWriter`. When a Figure
wrapper or caption is edited, unchanged child block constructors such as
`CodeBlock` can retain compatible source `native` payload sidecars while the
stale Figure wrapper sidecars are regenerated away.

This keeps nested Figure content constructor handoff aligned between JSON and
native writers without preserving stale provenance after semantic child edits.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.

## Accounting

- `phpPass`: `3185 -> 3186`
- `phpFail`: `0`
- `mappedJsonNativeFigureChildBlockPayloadCases`: `+1`
- `jsonNativeFigureChildBlockPayloadAssertions`: `+20`

## Verification

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 1565 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 70256 assertions, 0 failures`
