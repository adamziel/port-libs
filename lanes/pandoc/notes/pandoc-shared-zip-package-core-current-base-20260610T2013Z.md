# Pandoc Shared ZIP Package Core Current Base

Rebase base: `5418240ff610da2ffdcb4a2ce8cdd899f12c1329`

## Behavior

- `ZipPackage::creatorHostSystemPolicyPreflight()` already exposes raw creator host-system policy before package instantiation; this slice carries that provenance through instantiated package summaries and strict import diagnostics.
- `ZipPackage::creatorHostSystemPreflight()` now reports each entry's central-directory `versionNeededToExtract`, whether the lower creator `madeByVersion` byte meets it, and `creatorVersionBelowNeededEntries`.
- `ZipPackage::strictImportPreflight()` now emits `creator-version-below-version-needed` when a bounded package can be read but its creator-version provenance needs review.

## Evidence

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- Focused ZIP/OPC: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `2 test files, 6909 assertions, 0 failures`
- Full: `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 61412 assertions, 0 failures`

## Accounting

- `phpPass`: `3013 -> 3014`
- `benchmarkDenominator.mapped`: `3164 -> 3165`
- Added `mappedZipCreatorVersionProvenanceCases=1` and `zipCreatorVersionProvenanceAssertions=29`.

## Non-Overlap

This does not repeat accepted raw creator-host unknown-host blocking, generated creator-host metadata, external-attribute policy, compression-method version-needed policy, ZIP64, Unicode-name, local-header, or archive-extra slices. The new surface is only central-directory creator-version provenance carried into strict package review without invoking Pandoc, Cabal/Haskell runners, office suites, `zip`, `unzip`, `ZipArchive`, browser renderers, external validators, online services, live provider tests, or live-service provider tests.
