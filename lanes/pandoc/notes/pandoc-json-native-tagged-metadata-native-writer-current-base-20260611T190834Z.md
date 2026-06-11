# Pandoc JSON/Native Tagged Metadata Native Writer

Bead: `plib-4he7y`
Base: `a886765f4`

## Scope

This slice keeps pre-tagged Pandoc metadata constructors complete across the
JSON/native writer boundary.

`NativeWriter` now normalizes tagged `Meta*` metadata values through the same
compatible-reader path used by `PandocJsonWriter` before emitting native JSON.
That preserves valid constructors while canonicalizing legacy `MetaMap`
`unMeta` wrappers out of both top-level and nested metadata maps.

Constructor-like literal records that are not Pandoc `Meta*` values still write
as ordinary metadata maps, so user metadata with `t`/`c` fields is not mistaken
for a native constructor.

## Verification

- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  passed: 1 test file, 1052 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 test files, 65435 assertions, 0 failures.

## Accounting

- Adds 1 focused JSON/native tagged metadata canonicalization PASS case.
- Adds 16 focused assertions.

No Pandoc, JSON filters, Cabal/Haskell runners, browser renderers, external
validators, online services, live provider tests, or live-service provider tests
were invoked.
