# ODF reader package path-shape provenance

Hook: `plib-zpzxs`, Pandoc ODF/ODT OpenDocument package ingestion core blocker slice.

## Slice

`OdfReader` now carries ZIP package path-shape provenance through the rich
`importReport.manifest.packageProvenance` report and its deterministic
`packageIdentity` payload.

Each package part now exposes metadata-only path-shape fields: kind, top-level
segment, directory, basename, lowercased extension, segments, segment count, and
directory segment count. The rich report also carries aggregate package path
kind counts, top-level segment counts, and path extension counts, matching the
compact `OpenDocumentPackage` package inventory shape.

This is package-ingestion review metadata only. It does not expose blocked
payload bytes and does not invoke external package, office, converter, or
validator tooling.

## Direct parity accounting

- `mappedOdfReaderPackagePathShapeProvenanceCases`: 1
- `odfReaderPackagePathShapeProvenanceAssertions`: 25 additional focused
  assertions in the existing package extension provenance case

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfPackagePartExtensionProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackagePartExtensionProvenanceTest.php`
  - 1 test file, 78 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php`
  - 1 test file, 52 assertions, 0 failures
