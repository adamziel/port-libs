# Pandoc Media Bag Extraction Path Collisions

Implemented one bounded native PHP media-bag resource mapping slice for
decoded extraction path collisions.

## Behavior

- `MediaBag::extractMedia()` now builds a deterministic extraction plan before
  remapping AST image URLs.
- Literal safe relative media paths keep the stable destination when a
  percent-encoded source decodes to the same media path.
- Colliding encoded sources with different bytes receive a deterministic
  hash-suffixed media path and emit a `media-resource-path-collision`
  diagnostic before normal image remap diagnostics.

This slice does not fetch remote resources, write media files, invoke Pandoc,
Cabal/Haskell runners, browser renderers, office suites, TeX/PDF engines, JSON
filters, asset fetchers, external validators, online services, Node tooling, or
zip/unzip.

## Verification

- `php -l lanes/pandoc/src/MediaBag.php`
- `php -l lanes/pandoc/tests/MediaBagTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MediaBagTest.php`
  - Result after current-base rebase: 1 test file, 102 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result after current-base rebase: 42 test files, 58548 assertions, 0 failures.

Status delta after the full post-rebase gate: `phpPass` moves from `2914` to
`2915`; focused checks move from `817` to `818`. `UPSTREAM_TEST_MANIFEST.json`
mapped denominator moves from `3099` to `3100`.
