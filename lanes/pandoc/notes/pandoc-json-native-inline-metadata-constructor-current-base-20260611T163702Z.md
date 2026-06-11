# Pandoc JSON/native inline metadata constructor slice 2026-06-11

## Scope

This slice covers a bounded JSON/native AST constructor-completeness gap: unknown `native_inline` fallback constructors could be emitted by `PandocJsonWriter` and `NativeWriter`, but were not classified as inline nodes for metadata helper/list paths.

## Change

- `PandocJsonWriter` and `NativeWriter` now treat `native_inline` as an inline node in their inline classifiers.
- `PandocJsonNativeAstTest` adds a focused fixture proving native fallback inline constructors remain `MetaInlines` through direct metadata lists, standard `titleInlines` helpers, JSON writer output, native writer output, and `PandocJsonReader` round trips.

No Pandoc, JSON filters, Cabal/Haskell runners, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.

## Verification

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php` -> 1 test file, 803 assertions, 0 failures
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $path) { json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR); echo $path . " OK\n"; }'`
- `php tools/run-tests.php lanes/pandoc/tests` -> 44 test files, 63939 assertions, 0 failures after rebase onto `origin/main` 1396a6d1f

## Accounting

- `lane-status.json` `phpPass`: `3071 -> 3072`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3193 -> 3194`
- Added `mappedPandocJsonNativeInlineMetadataConstructorCases: 1`
- Added `pandocJsonNativeInlineMetadataConstructorAssertions: 10`
