# Pandoc ODF/ODT Audio Video Package Media Resources

Bead: `plib-4fpkg`
Date: 2026-06-11 UTC
Area: Pandoc ODF/ODT OpenDocument package ingestion

## Behavior

`OpenDocumentPackage` now treats manifest-declared `audio/*` and `video/*`
entries as compact media resources alongside existing image and `Pictures/`
package entries.

The native PHP package summary now carries ODT audio/video resources through:

- `mediaParts` byte/provenance handoff;
- `manifestReview` media type and byte exposure rows;
- ZIP `packageInventory` `media-resource` roles.

This keeps the change bounded to package-ingestion review metadata. It does not
decode media payloads and does not invoke Pandoc, office suites, `zip`/`unzip`,
browser renderers, media tooling, external validators, online services, live
provider tests, or live-service provider tests.

## Accounting

- `phpPass` note ledger: `3134 -> 3135`
- `phpFail`: `0`
- `benchmarkDenominator.mapped`: `3218 -> 3219`
- `mappedOdfAudioVideoMediaResourceCases`: `+1`
- `odfAudioVideoMediaResourceAssertions`: `+34`

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - `1 test files, 452 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 66718 assertions, 0 failures`
