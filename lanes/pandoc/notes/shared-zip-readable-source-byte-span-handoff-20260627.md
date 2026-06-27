# Shared ZIP Readable Source Byte Span Handoff

Slice: `plib-iar2c` shared ZIP/OPC package core blocker.

## Scope

`ZipPackage::entryHandoffPreflight()` already carried per-entry source-byte-span provenance and selected-entry aggregate source bytes. This slice adds the matching readable handoff aggregate so DOCX/EPUB/ODT readers can compare all selected package records against the subset that is actually exposed after duplicate, kind, readability, and size gates.

The new handoff fields summarize local record bytes, local header variable fields, compressed data, data descriptors, central-directory record fields, review-only central fields, total source record bytes, compact readable source-span entries, and source-span issues.

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: 1 test file, 4,983 assertions, 0 failures.

## Metrics

- `lane-status.json` `phpPass`: `458 -> 459`
- `lane-status.json` `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2304 -> 2305`
- New mapped case: `mappedSharedZipReadableSourceByteSpanCases`

## Guardrails

No Pandoc, office suites, TeX/browser engines, `zip`/`unzip`, `ZipArchive`, external validators, online services, live provider tests, or live-service provider tests were invoked.
