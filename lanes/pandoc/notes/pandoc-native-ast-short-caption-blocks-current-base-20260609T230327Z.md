# Pandoc Native AST Short Caption Blocks Current Base

## Status delta

- `NativeWriter` now emits shared table `shortCaptionBlocks` as valid Pandoc
  native short-caption inline constructors when the blocks are bounded
  `Plain`/`Para`-compatible inline blocks.
- `PandocJsonWriter` uses the same fallback, so generated shared table packets
  preserve review short-caption block provenance before falling back to plain
  short-caption text.
- Source review packets still identify the original short caption source as
  `shortCaptionBlocks`; native/Pandoc JSON round trips rehydrate the emitted
  short caption as `shortCaptionInlines`.

## Focused evidence

- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/NativeReaderTest.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php`
  - 1 file, 73 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - 1 file, 231 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 files, 57983 assertions, 0 failures.

## Scope and exclusions

This is a bounded native PHP AST handoff slice. It does not invoke Pandoc,
JSON filters, Cabal/Haskell runners, browser renderers, online services,
external validators, office suites, TeX/PDF engines, unzip/zip, Jupyter, or
Node tooling.
