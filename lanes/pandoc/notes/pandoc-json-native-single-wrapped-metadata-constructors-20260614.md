# Pandoc JSON/native single-wrapped metadata constructors

Bead: `plib-7fvf1`
Date: 2026-06-14 UTC
Area: Pandoc JSON/native AST constructor completeness

## Behavior

`PandocJsonReader` and `NativeReader` now accept Pandoc metadata constructors
whose `c` payload is single-wrapped:

- `MetaString` and `MetaBool` accept `[value]`.
- `MetaInlines`, `MetaBlocks`, and `MetaList` accept `[[...]]`.
- `MetaMap` accepts `[{"key": ...}]`.

`PandocJsonWriter` and `NativeWriter` preserve compatible wrapped source
metadata constructor sidecars until the corresponding metadata value changes.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.

## Accounting

- `phpPass`: `3513 -> 3514`
- `phpFail`: `0`
- `mappedJsonNativeSingleWrappedMetadataConstructorCases`: `1`
- `jsonNativeSingleWrappedMetadataConstructorAssertions`: `37`

## Verification

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 3391 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 83138 assertions, 0 failures`
