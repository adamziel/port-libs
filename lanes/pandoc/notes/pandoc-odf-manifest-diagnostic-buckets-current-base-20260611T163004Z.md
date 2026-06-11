# ODF Manifest Diagnostic Buckets

Slice: `plib-rpbgw` ODF/ODT OpenDocument package ingestion.

`OpenDocumentPackage::summarize()` now exposes compact manifest-review aggregate
buckets for byte-exposure policies and diagnostic codes. The per-entry
`manifestReview` rows already carried policy and diagnostic provenance; this
adds deterministic `byteExposurePolicyCounts`, `byteExposurePolicyPaths`,
`diagnosticCounts`, and `diagnosticPaths` so review queues can gate on package
state without re-walking every manifest row.

The focused fixture covers root, exposable XML, directory, encrypted, missing,
and declared-size-mismatch manifest entries. It does not expose encrypted or
missing bytes and does not shell out to Pandoc, office suites, `zip`/`unzip`,
browser renderers, external validators, online services, live provider tests,
or live-service provider tests.

Accounting:

- `phpPass`: `3109 -> 3110`
- `mappedOdfManifestDiagnosticBucketCases`: `0 -> 1`
- `odfManifestDiagnosticBucketAssertions`: `0 -> 12`
- mapped denominator: `3207 -> 3208`

Verification:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  passed 1 file, 350 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed 44 files, 65728
  assertions, 0 failures.
