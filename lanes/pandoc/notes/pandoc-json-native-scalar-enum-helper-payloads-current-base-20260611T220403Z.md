# Pandoc JSON/native scalar enum helper payloads current base 20260611T220403Z

Bead: `plib-1nzhb`
Base: `7f33388364a4c70802915a0cdb159c7a8c3d76e3`

## Scope

`PandocJsonWriter` and `NativeWriter` now preserve matching source scalar enum helper payloads when edited shared AST nodes are regenerated.

Covered helper payloads:

- ordered-list style and delimiter helpers
- quoted inline quote-type helpers
- math inline math-type helpers
- citation mode helpers

The writers only reuse the saved helper payload when it still matches the normalized constructor. Generated AST nodes and edited constructor values continue to emit canonical tagged constructor objects.

## Verification

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 1116 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 66564 assertions, 0 failures`

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
