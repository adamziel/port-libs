# Shared ZIP/OPC Byte Exposure Policy Handoff

Issue: plib-em2zq
Date: 2026-06-28

`ZipPackage::entryHandoffPreflight()` now annotates every selected request with
a compact `byteExposurePolicy`:

- `readable`: selected entry bytes were read and exposed to the caller.
- `metadata-only`: the entry exists but remained selected-only because a size,
  kind, duplicate, or readability gate blocked byte exposure.
- `missing`: the requested entry was absent from the package.

The preflight also reports `byteExposurePolicySummaries` across all selected
requests and `handoffByteExposurePolicySummaries` across the readable handoff
subset. These summaries carry request counts, required/optional counts,
selected versus handoff byte totals, roles, entry names, missing/failed names,
and issue buckets. This lets DOCX, EPUB3, ODF/ODT, and other package readers
audit which selected ZIP members were actually exposed before they read package
payloads.

Validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - 1 test file, 5,378 assertions, 0 failures

Direct-format parity accounting: no input/output format denominator change.
This is a shared ZIP/OPC package primitive that supports bounded package
ingestion for existing DOCX/EPUB/ODF lanes without shelling out to external
tools.
