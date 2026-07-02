# Pandoc MediaBag Resource Mapping Summary

Implemented one bounded native PHP MediaBag resource mapping summary slice.

## Behavior

- `MediaBag::resourceMappingSummary()` now returns metadata-only rollups for a
  mapped document extraction: sources, canonical sources, media paths, target
  paths, MIME type maps, MIME/path repair counts, source-class counts,
  diagnostic counts, diagnostic source buckets, mapped image/link counts, total
  bytes, review status, and byte-exposure policy.
- The summary is derived from the existing native extraction plan and does not
  expose resource payload contents.
- Focused coverage exercises percent-decoded relative image paths, URL-suffixed
  linked resource paths, URI hash paths, and content-type review diagnostics.

This slice does not fetch resources, write media files, invoke Pandoc,
Cabal/Haskell runners, browser renderers, office suites, TeX/PDF engines,
external validators, online services, Node tooling, or zip/unzip.

## Verification

- `php -l lanes/pandoc/src/MediaBag.php`
- `php -l lanes/pandoc/tests/MediaBagTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MediaBagTest.php`
  - Result: 1 test file, 275 assertions, 0 failures.

Status delta: `UPSTREAM_TEST_MANIFEST.json` mapped denominator moves from
`2883` to `2884`. The slice records
`mappedMediaBagResourceMappingSummaryCases=1` and
`mediaBagResourceMappingSummaryAssertions=23`.
