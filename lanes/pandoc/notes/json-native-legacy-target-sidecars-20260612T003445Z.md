# Pandoc JSON/native legacy target sidecars

Slice: plib-33byf

This slice keeps bare legacy `Link` and `Image` two-slot target constructor round-trips compatible in `NativeWriter`, but prevents source-tagged legacy target payloads with extra sidecars from being reused as-is.

When a reusable block or inline native payload contains a legacy target inline with sidecar fields, `NativeWriter` now regenerates the current three-slot constructor shape from shared AST fields. The target tuple and label inlines are preserved, while stale wrapper sidecars such as review queue ordinals are dropped.

Focused coverage lives in `PandocJsonNativeAstTest.php` and exercises both `PandocJsonReader` and `NativeReader` inputs.

Verification:

- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`

No Pandoc, JSON filters, Cabal/Haskell runners, browser renderers, external validators, online services, live provider tests, or live-service provider tests are invoked.
