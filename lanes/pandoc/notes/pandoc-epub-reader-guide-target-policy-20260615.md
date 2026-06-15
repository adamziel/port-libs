# Pandoc EPUB Reader Guide Target Policy - 2026-06-15

This slice extends native PHP EPUB package ingestion in `EpubPackageReader`.

Direct OPF `guide/reference` entries now expose a review-ready target policy report:
local, external, missing, unmanifested, and missing-href guide targets; manifest-linked
targets; href query/fragment provenance; diagnostic type counts; and guide reference
authoring attributes.

The change keeps the existing `guide` report shape additive and mirrors it through
`guideReferenceReport`, `guideReferenceTargets`, and `guideReferenceDiagnostics`.
It does not invoke Pandoc, EPUBCheck, `zip`/`unzip`, browser renderers, external
validators, online services, or live provider tests.

Metrics:
- `phpPass`: `3801 -> 3802`
- mapped upstream cases: `3792 -> 3793`
- `mappedEpubReaderGuideTargetPolicyCases`: `1`
- `epubReaderGuideTargetPolicyAssertions`: `39`

Verification:
- `php -l lanes/pandoc/src/EpubPackageReader.php`
- `php -l lanes/pandoc/tests/EpubPackageReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php`
  passed: `1 file, 1538 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: `46 files, 90372 assertions, 0 failures`
