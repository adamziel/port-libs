# Pandoc MediaBag Repair Stage Provenance

Implemented one bounded native PHP MediaBag linked-resource provenance follow-up for `plib-h4a98`.

## Behavior

- `MediaBag::extractMedia()` entries now expose `sourcePathRepaired`, `extractionPathRepaired`, and `pathCollision` alongside the existing path, MIME, linked MIME group, and collision diagnostics.
- `MediaBag::resourceMap()` carries the same repair-stage fields in document order for image/link mappings.
- Mapped image/link attributes now include stable `data-pandoc-media-source-path-*`, `data-pandoc-media-extraction-path-*`, and `data-pandoc-media-path-collision` fields, separating source normalization such as percent-decoding from extraction-time disambiguation such as case-folded path collisions.
- Focused coverage exercises normalized and percent-decoded linked resources, extension/content-type disagreement summaries, duplicate linked-resource MIME groups, and case-folded extraction collision disambiguation without fetching resources or invoking external tools.

No Pandoc, browser, Node tooling, online service, live provider, external validator, or resource fetcher was invoked.

## Verification

- `php -l lanes/pandoc/src/MediaBag.php`
- `php -l lanes/pandoc/tests/MediaBagTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MediaBagTest.php`
  - Result: 1 test file, 375 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: 295 test files, 117104 assertions, 9781 failures.
  - Visible failures are outside this MediaBag slice, including `SyntaxHighlighterTest.php`, `TableGeometryReaderHandoffTest.php`, `TableGeometryTest.php`, and `YamlMetadataReviewTest.php`.
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`
- `rg -n "^(<<<<<<<|=======|>>>>>>>)" lanes/pandoc/src/MediaBag.php lanes/pandoc/tests/MediaBagTest.php lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/notes/pandoc-media-bag-repair-stage-provenance-20260701.md`
  - Result: no conflict markers found.

Status delta: `lane-status.json` `phpPass` moves from `460` to `461`.
`UPSTREAM_TEST_MANIFEST.json` mapped denominator moves from `2305` to `2306`
with `mappedMediaBagRepairStageProvenanceCases: 1`.
