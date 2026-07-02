# Pandoc MediaBag Decoded Remote URI Resource Map

Implemented one bounded native PHP media-bag resource mapping slice for
percent-encoded remote media URLs.

## Behavior

- `MediaBag::fillDocument()` now resolves remote URI image sources such as
  `https://cdn.example.test/media/review%20chart.svg?download=1#review`
  against a decoded URI resource-map key after exact and path-only lookup.
- Exact resource entries keep precedence over decoded URI fallbacks; conflicting
  exact and decoded candidates continue to emit repair conflict diagnostics.
- Remote media still extracts through MIME-derived hashed paths, so decoded URI
  spaces or percent octets never become package-local media paths.

This slice does not fetch remote resources, write media files, invoke Pandoc,
Cabal/Haskell runners, browser renderers, office suites, TeX/PDF engines,
external validators, online services, live provider tests, Node tooling, or
zip/unzip.

## Verification

- `php -l lanes/pandoc/src/MediaBag.php`
- `php -l lanes/pandoc/tests/MediaBagTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MediaBagTest.php`
  - Result: 1 test file, 273 assertions, 0 failures.

The slice records `mappedMediaBagDecodedRemoteUriResourceCases=1` and
`mediaBagDecodedRemoteUriResourceAssertions=21`.
