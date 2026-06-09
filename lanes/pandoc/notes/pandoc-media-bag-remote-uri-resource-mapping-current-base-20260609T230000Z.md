# Pandoc Media Bag Remote URI Resource Mapping

Implemented one bounded native PHP media-bag resource mapping slice for remote
image URLs that carry query or fragment suffixes.

## Behavior

- `MediaBag::fillDocument()` resolves absolute URI image sources such as
  `https://cdn.example.test/media/loaded.svg?download=1#review` through a
  path-only URI resource-map key when no exact resource entry exists.
- `MediaBag::extractMedia()` uses the same exact-first lookup order for
  preloaded media-bag items, so a preloaded
  `https://assets.example.test/media/preloaded.jpg` entry can satisfy an AST
  image URL with cache-busting query or media-fragment suffixes.
- Remote sources still map to deterministic content-hash extraction filenames;
  query and fragment delimiters never become media path characters.

This slice does not fetch remote resources, write media files, invoke Pandoc,
Cabal/Haskell runners, browser renderers, office suites, TeX/PDF engines, JSON
filters, external validators, online services, live provider tests, Node
tooling, or zip/unzip.

## Verification

- `php -l lanes/pandoc/src/MediaBag.php`
- `php -l lanes/pandoc/tests/MediaBagTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MediaBagTest.php`
  - Result: 1 test file, 81 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result after rebase: 42 test files, 57728 assertions, 0 failures.

Status delta after rebase: `phpPass` moves from `2866` to `2867`; mapped
focused checks move from `769` to `770`. `UPSTREAM_TEST_MANIFEST.json` mapped
denominator moves from `3071` to `3072`.
