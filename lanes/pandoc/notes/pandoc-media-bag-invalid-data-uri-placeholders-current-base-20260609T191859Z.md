# Pandoc Media Bag Invalid Data URI Placeholders

Implemented one bounded native PHP media-bag resource mapping slice for malformed
inline image data resources encountered during document fill.

## Behavior

- `MediaBag::fillDocument()` now catches invalid inline `data:` image resources
  during review handoff and reports `media-resource-invalid:data-uri`.
- Malformed inline resources become the existing image placeholder spans with
  original source/title metadata instead of aborting the whole fill operation.
- Direct `MediaBag::insertDataUri()` remains strict and still throws for invalid
  standalone caller input.

This slice does not invoke Pandoc, Cabal/Haskell runners, browser renderers,
office suites, TeX/PDF engines, fetchers, filesystem writers, external
validators, online services, live provider tests, or live-service provider
tests.

## Verification

- `php -l lanes/pandoc/src/MediaBag.php`
- `php -l lanes/pandoc/tests/MediaBagTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MediaBagTest.php`
  - Result: 1 test file, 108 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result after rebase: 43 test files, 59076 assertions, 0 failures.

Status delta after rebase: `phpPass` moves from `2942` to `2943`; mapped
focused checks move from `845` to `846`.
