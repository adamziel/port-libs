# Pandoc Media Bag Resource Lookup Provenance

Implemented one bounded native PHP media-bag resource mapping slice for
occurrence-level lookup provenance.

## Behavior

- `MediaBag::resourceMap()` now reports `sourceLookupKey` and
  `sourceLookupRepair` for each mapped image/link occurrence.
- `MediaBag::extractMedia()` preserves the same lookup provenance on mapped
  nodes as `data-pandoc-media-source-lookup-key` and
  `data-pandoc-media-source-lookup-repair`.
- Exact, canonical, path-only query/fragment, and percent-decoded source
  mappings are covered without fetching external resources, writing media
  files, invoking Pandoc, or using browser/office/TeX/PDF engines.

## Verification

- `php -l lanes/pandoc/src/MediaBag.php`
- `php -l lanes/pandoc/tests/MediaBagTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MediaBagTest.php`
  - Result: 1 test file, 288 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: 295 test files, 117017 assertions, 9781 failures.
  - The visible failures are outside this slice in `YamlMetadataReviewTest.php`;
    `MediaBagTest.php` remains green.

Status delta: `lane-status.json` `phpPass` moves from `458` to `459`.
`UPSTREAM_TEST_MANIFEST.json` mapped denominator moves from `2304` to `2305`
with `mappedMediaBagResourceLookupProvenanceCases: 1`.
