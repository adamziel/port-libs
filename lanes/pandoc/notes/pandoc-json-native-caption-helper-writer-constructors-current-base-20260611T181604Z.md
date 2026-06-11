# Pandoc JSON/native caption helper writer constructors

Bead: `plib-91z7k`
Base: `a909b7a8b`
Scope: JSON/native AST constructor completeness.

## Change

`PandocJsonWriter` and `NativeWriter` now preserve source-tagged Pandoc caption helper constructors when regenerating edited table and figure captions:

- `Caption`
- `Just`
- `Nothing`
- `ShortCaption`

Tagged caption payloads are reused when generated content is unchanged. When caption content changes, the writers rebuild under the same `Caption` wrapper and preserve or rebuild the source short-caption helper shape where possible.

## Verification

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test file, 1004 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 65171 assertions, 0 failures`

No Pandoc executable, JSON filters, Cabal/Haskell runners, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
