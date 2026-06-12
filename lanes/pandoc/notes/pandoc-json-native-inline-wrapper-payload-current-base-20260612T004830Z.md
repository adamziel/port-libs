# Pandoc JSON/native inline wrapper payload current-base slice

- Bead: `plib-d333s`
- Base: current main `896de35817`
- Scope: Pandoc JSON/native AST constructor completeness, limited to unchanged current inline wrapper native payload reuse in the Pandoc JSON writer.

## Handoff

`PandocJsonReader` and `NativeReader` already preserve current wrapper inline constructor payloads on shared AST nodes. This slice lets `PandocJsonWriter` reuse unchanged current `Emph`, `Strong`, `Underline`, `Strikeout`, `Superscript`, `Subscript`, `SmallCaps`, `Quoted`, `Note`, and `Span` payloads under the existing reader-equivalence guard, so inert reviewer provenance and source key order survive pass-through handoff.

Edited wrapper children still regenerate fresh constructors from semantic AST fields, preventing stale inert fields from leaking into changed output.

## Accounting

- `phpPass`: `3156 -> 3157`
- Added one focused `PandocJsonNativeAstTest` PASS case.
- Added `mappedJsonNativeInlineWrapperPayloadCases = 1`.
- Added `jsonNativeInlineWrapperPayloadAssertions = 12`.

## Verification

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php` -> 1 test file, 1233 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 44 test files, 68084 assertions, 0 failures

No Pandoc, JSON filters, Cabal/Haskell runners, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
