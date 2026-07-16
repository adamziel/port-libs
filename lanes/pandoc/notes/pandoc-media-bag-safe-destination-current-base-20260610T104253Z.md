# Pandoc Media Bag Safe Extraction Destination

Implemented one bounded native PHP media-bag resource mapping slice for safe
extraction destination validation before AST image URL remapping.

## Behavior

- `MediaBag::extractMedia()` now normalizes safe relative destinations with
  redundant slash, backslash, and `.` segments before planning media entries.
- Empty, absolute, URI, drive-letter, traversal, and percent-encoded traversal
  destinations are rejected before extraction entries or remapped image URLs are
  produced.
- Existing resource-map, query/fragment, percent-decoded, remote URI, collision,
  provenance, and malformed-data placeholder behavior remains unchanged.

This slice does not fetch resources, write files, invoke Pandoc, Cabal/Haskell
runners, browser renderers, office suites, TeX/PDF engines, JSON filters, asset
fetchers, external validators, online services, Node tooling, or zip/unzip.

## Verification

- `php -l lanes/pandoc/src/MediaBag.php`
- `php -l lanes/pandoc/tests/MediaBagTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MediaBagTest.php`
  - Result: 1 test file, 129 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result after current-base rebase: 44 test files, 59438 assertions, 0 failures.

Status delta after the full post-rebase gate: `phpPass` moves from `2951` to
`2952`; mapped focused checks move from `854` to `855`. `UPSTREAM_TEST_MANIFEST.json`
mapped denominator moves from `3123` to `3124`.
