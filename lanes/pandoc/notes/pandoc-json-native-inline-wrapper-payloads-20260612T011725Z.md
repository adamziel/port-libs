# Pandoc JSON/native inline wrapper payload current-base slice

- Bead: `plib-l00iq`
- Base: current main `2a569e4541`
- Scope: Pandoc JSON/native AST constructor completeness for unchanged current inline wrapper payload reuse.

## Handoff

`PandocJsonWriter` now treats current inline wrapper constructors as reusable native payloads when the existing reader-equivalence guard confirms the shared AST has not changed: `Emph`, `Strong`, `Underline`, `Strikeout`, `Superscript`, `Subscript`, `SmallCaps`, `Quoted`, `Note`, and `Span`.

`NativeWriter` now checks inline payload equivalence through both `PandocJsonReader` and `NativeReader`, matching JSON-origin AST nodes whose child text provenance helpers differ from native-reader helper shapes.

Edited wrapper content still regenerates fresh constructors and drops stale inert provenance.

No Pandoc, JSON filters, Cabal/Haskell runners, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.

## Verification

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php` -> 1 test file, 1249 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 44 test files, 68315 assertions, 0 failures
