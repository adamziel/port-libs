# Pandoc Shared ZIP Selected Local Fixed Header Handoff

## Summary

Shared ZIP/OPC selected-entry handoff now exposes local fixed-header provenance before DOCX/EPUB/ODF reader handoff. `ZipPackage::entryHandoffPreflight()` selected rows and aggregate review lists include fixed-header byte offsets, central/local version, flags, compression method, DOS timestamp, CRC/size fields, name/extra lengths, descriptor placeholder policy, and issue counters.

The descriptor case keeps the raw local fixed-header CRC and size placeholders visible as zero while reporting the central-directory CRC and compressed/uncompressed sizes separately.

## Accounting

- Rebased merge accounting after `fa2872bebc`: `phpPass`: `3237 -> 3238`
- `phpFail`: `0`
- Mapped denominator: `3257 -> 3258`
- `mappedZipSelectedLocalHeaderFixedFieldHandoffCases`: `1`
- `zipSelectedLocalHeaderFixedFieldHandoffAssertions`: `73`

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`: 1 file, 4305 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`: 44 files, 72154 assertions, 0 failures

## Boundaries

No Pandoc, office suites, TeX/browser engines, `zip`/`unzip`, `ZipArchive`, external validators, online services, live provider tests, or live-service provider tests were invoked.

This does not repeat accepted raw local-header fixed-field mismatch preflight, local-header span byte buckets, selected extra-field provenance, selected data-descriptor provenance, ZIP64 policies, or DOCX/EPUB/ODF reader-specific package surfaces. The slice only carries validated selected-entry local fixed-header provenance through the shared ZIP handoff contract.
