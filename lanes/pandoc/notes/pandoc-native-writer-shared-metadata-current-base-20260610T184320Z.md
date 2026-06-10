# Pandoc Native Writer Shared Metadata

Slice: `pandoc-native-writer-shared-metadata-current-base-20260610T184320Z`
Bead: `plib-434f3`

## Scope

This bounded JSON/native AST slice closes the native writer metadata-constructor
gap for shared AST documents.

- `NativeWriter` now accepts shared metadata values such as strings, booleans,
  typed `inlines`/`blocks`/`list`/`map` metadata, and standard helper fields
  like `titleInlines`, `authorInlines`, and `dateInlines`.
- Shared metadata is emitted as Pandoc native `Meta*` constructors.
- Imported pre-tagged native metadata remains exact because tagged `Meta*`
  arrays are still passed through unchanged.
- Direct-format parity accounting is not affected by this native constructor
  serialization slice.

## Evidence

- Red-first smoke:
  `php -r 'require "tools/bootstrap.php"; ... (new NativeWriter())->write($doc);'`
  failed before implementation with `Pandoc native metadata values must be tagged constructors`.
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/NativeReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php`
  passed with `1 test files, 264 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php lanes/pandoc/tests/NativeReaderTest.php`
  passed with `2 test files, 661 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed with `44 test files, 61010 assertions, 0 failures`.

## Mapping Delta

- `lane-status.json` `phpPass`: `2997 -> 2998`
- `lane-status.json` `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3153 -> 3154`
- New focused accounting:
  `mappedNativeWriterSharedMetadataCases: 1`,
  `nativeWriterSharedMetadataAssertions: 18`

## Dependency Closure

No new support component is needed. This reuses native PHP JSON handling,
shared `AstNode` metadata conventions, existing native block/inline writer
constructors, and the lane PHP harness.

No Pandoc, Cabal/Haskell runner, JSON filter, office suite, TeX/PDF engine,
browser renderer, zip/unzip, external validator, online service, live provider
test, or live-service provider test was executed.
