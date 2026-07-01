# DOCX Package Top-Level Segment Identity

Date: 2026-07-01
Slice: plib-10pnu

## Scope

DOCX/OpenXML package ingestion now carries metadata-only top-level segment rollups through package identity handoff surfaces:

- `packageIdentity.packageTopLevelSegment*` mirrors package provenance `partTopLevelSegment*` counts, duplicate buckets, and full segment summaries.
- `packageIdentity.packageCaseFoldTopLevelSegment*` mirrors case-folded segment summaries for collision review.
- `documentPackageIdentity` carries top-level and case-folded top-level segment count maps for main-document package review.
- Package identity entries now include `topLevelSegment` and `caseFoldTopLevelSegment`.
- Exact top-level segment `largestPart` records now include the same segment metadata without exposing contents.

## Guardrails

The slice uses the existing native PHP `DocxOpenXmlReader` and `ZipPackage` fixtures only. It does not invoke Pandoc, office suites, TeX/browser engines, `zip`/`unzip`, external validators, Node tooling, or live services.

## Validation

Post-rebase validation passed:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlPackageTopLevelSegmentIdentityTest.php`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXml*.php` with 78 files, 17,047 assertions, 0 failures.
- `git diff --check origin/main...HEAD -- lanes/pandoc`
- `git diff --check -- lanes/pandoc`
- conflict-marker scan of changed lane files
