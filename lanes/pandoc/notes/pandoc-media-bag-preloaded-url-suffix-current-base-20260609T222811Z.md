# Pandoc Media Bag Preloaded URL-Suffix Mapping

Implemented one bounded native PHP media-bag resource mapping slice for
preloaded path-only media entries referenced by AST image URLs with query or
fragment suffixes.

## Behavior

- `MediaBag::fillDocument()` now recognizes that a preloaded bag item such as
  `assets/charts/review.svg` satisfies an AST image URL like
  `assets/charts/review.svg?width=640#caption`.
- `MediaBag::extractMedia()` uses the same exact/canonical/path-only lookup
  order before remapping image URLs, so the planned extraction path remains
  `media/assets/charts/review.svg` instead of treating the image as missing.
- Exact source entries still win before canonical and path-only fallback keys.

This slice does not fetch remote resources, write media files, invoke Pandoc,
Cabal/Haskell runners, browser renderers, office suites, TeX/PDF engines, JSON
filters, external validators, online services, Node tooling, or zip/unzip.

## Verification

- `php -l lanes/pandoc/src/MediaBag.php`
- `php -l lanes/pandoc/tests/MediaBagTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MediaBagTest.php`
  - Result: 1 test file, 67 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: 42 test files, 57540 assertions, 0 failures.

Status delta: `phpPass` moves from `2857` to `2858`; mapped focused checks move
from `760` to `761`. `UPSTREAM_TEST_MANIFEST.json` mapped denominator moves from
`3063` to `3064`.
