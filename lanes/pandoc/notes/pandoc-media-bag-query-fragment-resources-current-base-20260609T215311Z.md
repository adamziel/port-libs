# Pandoc Media Bag Query/Fragment Resources

Implemented one bounded native PHP media-bag resource mapping slice for relative
image URLs that carry query or fragment suffixes.

## Behavior

- `MediaBag::fillDocument()` now resolves URL-suffixed relative image sources
  through path-only canonical resource-map keys, so
  `assets/plot.svg?download=1#review` can load bytes from a resource keyed as
  `assets/plot.svg`.
- Relative media paths containing `?` or `#` are no longer treated as safe
  extraction filenames. They are mapped to deterministic content-hash filenames
  with MIME-derived extensions.
- Remote/data URI behavior remains separate: no resources are fetched, no files
  are written, and extraction stays an in-memory AST remap plan.

This slice does not invoke Pandoc, Cabal/Haskell runners, browser renderers,
office suites, TeX/PDF engines, JSON filters, external validators, online
services, live provider tests, Node tooling, or zip/unzip.

## Verification

- `php -l lanes/pandoc/src/MediaBag.php`
- `php -l lanes/pandoc/tests/MediaBagTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MediaBagTest.php`
  - Result: 1 test file, 60 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result after rebase: 42 test files, 57485 assertions, 0 failures.

Status delta after rebase: `phpPass` moves from `2852` to `2853`; mapped
focused checks move from `755` to `756`. `UPSTREAM_TEST_MANIFEST.json` mapped
denominator moves from `3058` to `3059`.
