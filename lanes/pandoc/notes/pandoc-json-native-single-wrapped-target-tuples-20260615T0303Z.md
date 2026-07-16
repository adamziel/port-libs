# Pandoc JSON/native single-wrapped target tuples

Date: 2026-06-15
Bead: plib-i482y
Base: current main 9f2dd9a0fd

## Scope

- Added one bounded JSON/native AST target tuple sidecar coverage slice for `Link` and `Image`.
- `PandocJsonReader` and `NativeReader` accept single-wrapped target tuples such as `[[url, title, sidecar]]` while preserving the original wrapped payload as `targetNative`.
- `PandocJsonWriter` and `NativeWriter` preserve wrapped target sidecars only when the normalized URL/title still match; edited targets regenerate canonical direct `[url, title]` tuples and drop stale sidecars.
- No Pandoc, JSON filters, Cabal/Haskell runners, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.

## Verification

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`: 1 file, 4197 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`: 46 files, 85622 assertions, 0 failures.

## Accounting

- `phpPass`: 3637 -> 3638.
- `phpFail`: 0.
- `upstream.mapped`: 3674 -> 3675.
- `benchmarkDenominator.mapped`: 3247 -> 3248.
- `mappedJsonNativeTargetTupleSidecarCases`: 1 -> 2.
- `jsonNativeTargetTupleSidecarAssertions`: 24 -> 52.
