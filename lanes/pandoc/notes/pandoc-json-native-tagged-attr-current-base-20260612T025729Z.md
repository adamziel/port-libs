# Pandoc JSON/native tagged Attr helpers

Bead: `plib-oaop6`
Date: 2026-06-12 UTC
Area: Pandoc JSON/native AST constructor completeness

## Behavior

`PandocJsonReader` now accepts current tagged `Attr` helper constructors
wherever a Pandoc attr tuple is already accepted. The shared AST still exposes
normalized `id`, `classes`, and `attributes`, while `attrNative` retains the
source tagged helper payload.

`PandocJsonWriter` and `NativeWriter` now preserve a tagged `Attr` helper only
when its normalized content matches the current AST attributes. If an AST edit
changes `id`, classes, or key-values, writers regenerate a plain attr tuple and
drop the stale tagged helper payload.

No Pandoc binary, JSON filters, Cabal/Haskell runners, office suites, TeX/PDF
engines, browser renderers, `zip`/`unzip`, external validators, online services,
live provider tests, or live-service provider tests were invoked.

## Verification

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 1489 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 69682 assertions, 0 failures`
