# Pandoc JSON/native native-reader-only inline payloads

Bead: `plib-h7p6e`
Date: 2026-06-12 UTC
Area: Pandoc JSON/native AST constructor completeness

## Behavior

`NativeWriter` now mirrors the block payload reuse guard for inline
constructors by trying JSON-reader and native-reader normalization
independently. Native-reader-compatible inline payloads that the stricter JSON
reader rejects can therefore survive rebuilt wrapper handoff when their shared
AST value is unchanged.

The focused case covers `Code` and `Span` inline constructors whose native
`Attr` tuple carries an extra inert sidecar slot. Rebuilt paragraph wrappers now
preserve the full source inline constructor payload through both JSON and
native writers. Semantic inline edits still regenerate canonical constructors
and drop stale wrapper sidecars.

No Pandoc executable, JSON filter, Cabal/Haskell runner, browser renderer,
external validator, online service, live provider test, or live-service
provider test was invoked.

## Accounting

- `phpPass`: `3191 -> 3192`
- `phpFail`: `0`
- `mappedJsonNativeNativeReaderOnlyInlinePayloadCases`: `+1`
- `jsonNativeNativeReaderOnlyInlinePayloadAssertions`: `+12`

## Verification

- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 1557 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 70601 assertions, 0 failures`
