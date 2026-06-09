# Pandoc Media Bag Resource Mapping

Implemented one bounded native PHP media-bag resource mapping slice for
canonical deletion and deterministic item enumeration.

## Behavior

- `MediaBag::deleteMedia()` removes resources through the same canonical source
  key normalization used by insert and lookup.
- `MediaBag::mediaItems()` exposes deterministic in-memory resource records
  including path, MIME type, byte length, SHA-1, source, and contents.
- The focused fixture proves deleted resources no longer remap AST image URLs
  during extraction while retained resources still map under the normalized
  extraction destination.

This slice does not fetch remote resources, write media files, invoke Pandoc,
Cabal/Haskell runners, browser renderers, office suites, TeX/PDF engines, JSON
filters, external validators, online services, Node tooling, or zip/unzip.

## Verification

- `php -l lanes/pandoc/src/MediaBag.php`
- `php -l lanes/pandoc/tests/MediaBagTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MediaBagTest.php`
  - Result: 1 test file, 40 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result after rebase: 42 test files, 57128 assertions, 0 failures.

Status delta after rebase: `phpPass` moves from `2834` to `2835`; mapped
focused checks move from `737` to `738`. `UPSTREAM_TEST_MANIFEST.json` mapped
denominator moves from `3049` to `3050`.
