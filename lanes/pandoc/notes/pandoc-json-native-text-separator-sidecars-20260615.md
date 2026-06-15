# Pandoc JSON/native text separator sidecars

Slice: `pandoc-json-native-text-separator-sidecars-20260615`
Base: `721c2a3d70`

## Scope

This bounded JSON/native AST constructor-completeness slice preserves explicit
`SoftBreak` and `LineBreak` native inline parts on shared AST `text` nodes when
the aggregate text still matches the stored native parts. Both
`PandocJsonWriter` and `NativeWriter` now treat these current nullary separator
constructors like `Space` for nativeInlineParts reuse, while rejecting stale
separator payloads that carry a `c` field.

The slice does not invoke Pandoc, JSON filters, Cabal/Haskell runners, browser
renderers, external validators, online services, live provider tests, or
live-service provider tests.

## Accounting

- `phpPass`: `3632 -> 3633`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` upstream mapped: `3670 -> 3671`
- `mappedJsonNativeConstructorCompletenessCases`: `29 -> 30`
- `jsonNativeConstructorCompletenessAssertions`: `438 -> 450`
- `mappedJsonNativeTextSeparatorSidecarCases`: `1`
- `jsonNativeTextSeparatorSidecarAssertions`: `12`

## Verification

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  passed: 1 file, 4155 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 46 files, 85491 assertions, 0 failures
