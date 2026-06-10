# Pandoc Native Metadata Constructors

2026-06-10 UTC slice `plib-bgb2u` maps one JSON/native AST constructor completeness case for `NativeWriter`.

## Implementation

- `NativeWriter` now emits shared document metadata values as Pandoc native `MetaString`, `MetaBool`, `MetaInlines`, `MetaBlocks`, `MetaList`, and `MetaMap` constructors.
- Standard helper metadata such as `titleInlines`, `authorInlines`, `authors`, and `dateInlines` is normalized into canonical native metadata fields without leaking helper keys.
- Pre-tagged native `Meta*` values are preserved unchanged for exact native JSON filter round trips.

## Verification

- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/NativeReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php`: 1 file, 287 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`: 44 files, 61656 assertions, 0 failures.

## Accounting

- `phpPass`: 3022 -> 3023 after rebase onto `origin/main` 70856c477.
- mapped denominator: 3167 -> 3168 after rebase onto `origin/main` 70856c477.
- Added `mappedNativeAstMetadataConstructorCases = 1`.
- Added `nativeAstMetadataConstructorAssertions = 31`.
