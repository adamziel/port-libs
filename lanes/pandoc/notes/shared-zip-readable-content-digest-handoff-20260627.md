# Shared ZIP Readable Content Digest Handoff

Slice: `plib-33fgb` shared ZIP/OPC package core blocker.

## Scope

`ZipPackage::entryHandoffPreflight()` already exposed per-readable-entry `contentSha256` values after selected-entry gates. This slice adds a compact readable content digest manifest so DOCX, EPUB, and ODT readers can audit the exact payload subset exposed after size, kind, duplicate, and readability checks.

The new handoff fields summarize unique readable entry count, exposed content bytes, deterministic digest manifest version/SHA-256, and compact per-entry digest rows with request indexes, roles, compression metadata, CRC32, byte lengths, and content SHA-256. Blocked oversized media stays selected-only and is excluded from the readable digest manifest.

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: 1 test file, 5,023 assertions, 0 failures.

## Metrics

- `lane-status.json` `phpPass`: `460 -> 461`
- `lane-status.json` `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2306 -> 2307`
- New mapped case: `mappedSharedZipReadableContentDigestCases`

## Guardrails

No Pandoc, office suites, TeX/browser engines, `zip`/`unzip`, `ZipArchive`, external validators, online services, live provider tests, or live-service provider tests were invoked.
