# Pandoc Media Bag Missing Placeholder Provenance

Implemented one bounded native PHP media-bag resource mapping slice for missing
image placeholders.

## Behavior

- `MediaBag::fillDocument()` now annotates missing-image placeholders with
  bounded source review metadata: source kind, canonical source, source path,
  inferred MIME type, lookup attempt count, lookup repair classes, path-only
  source, and safe percent-decoded source when applicable.
- Unsafe percent-decoded sources such as encoded parent traversal are classified
  as rejected provenance and are not advertised as decoded lookup keys.
- Remote URI misses retain metadata-only path and path-only lookup provenance
  without fetching the remote resource.

This slice does not fetch remote resources, write media files, invoke Pandoc,
Cabal/Haskell runners, browser renderers, office suites, TeX/PDF engines, JSON
filters, external validators, online services, Node tooling, or zip/unzip.

## Verification

- `php -l lanes/pandoc/src/MediaBag.php`
- `php -l lanes/pandoc/tests/MediaBagTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MediaBagTest.php`
  - Result after rebase: 1 test file, 315 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result after rebase: 295 test files, 117044 assertions, 9781 failures.
  - Visible failures are outside this MediaBag slice, including
    `YamlMetadataReviewTest.php` provenance expectations.

Status delta after rebase: `phpPass` moves from `459` to `460`; `phpFail`
remains `0`.
