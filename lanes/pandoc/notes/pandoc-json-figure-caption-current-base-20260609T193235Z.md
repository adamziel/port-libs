# Pandoc JSON Figure Caption Current Base

Slice: `pandoc-json-figure-caption-current-base-20260609T193235Z`

## Status delta

- Added bounded `PandocJsonReader` support for current Pandoc JSON `Figure`
  block constructors.
- `Figure` nodes now reuse the existing caption metadata normalization for
  tagged `Caption` and `ShortCaption` values, preserving long captions, short
  captions, figure attrs, and direct image bodies as native `figure` AST nodes.
- `PandocJsonWriter` now emits native `figure` AST nodes back to Pandoc JSON
  `Figure Attr Caption [Block]` shape and wraps direct inline image children in
  `Plain` blocks for JSON compatibility.
- JSON image labels now populate native image `alt` metadata, and writer output
  falls back to image `alt` text when an image node has no label children.
- Lane accounting moves `phpPass` `2936 -> 2937`, mapped denominator
  `3114 -> 3115`, and records `mappedPandocJsonFigureCaptionCases = 1` with
  `pandocJsonFigureCaptionAssertions = 34`.

## Focused evidence

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - 1 file, 339 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 43 files, 58975 assertions, 0 failures.

## Scope and exclusions

This is a bounded native PHP JSON handoff slice. It does not invoke Pandoc,
Cabal/Haskell runners, office suites, TeX/browser engines, zip/unzip, Jupyter,
Node tooling, external validators, or live services. Broader figure parity such
as arbitrary non-image figure bodies, rich rendered figcaption inline HTML, or
full upstream runner parity remains out of scope.
