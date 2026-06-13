# EPUB Page-List Spine Target Diagnostics - 2026-06-13

## Scope

This follow-up extends the native PHP EPUB3 package reader's nav page-list
review packet. `EpubPackageReader` now exposes `tocReport.pageList` with
manifest target counts, linear spine reading-order counts, per-item manifest
IDs, media types, spine indexes/idrefs, and diagnostics for page-list hrefs
that are missing from the OPF manifest or point at nonlinear/out-of-spine
package items.

The existing `epub.toc` entries remain unchanged for downstream consumers.

## Evidence

- Upstream denominator: 1 bounded EPUB nav page-list manifest/spine diagnostic
  follow-up from the accepted static inventory.
- Previous local numerator on current main `efd70091`: 3,406 PHP passes / 0
  failures and 3,362 mapped upstream cases.
- New local numerator after adding this follow-up: 3,407 PHP passes / 0
  failures and 3,363 mapped upstream cases.
- Focused evidence: 1 new `EpubPackageReaderTest.php` case plus fixture
  assertions, adding 54 focused assertions for manifest-backed page-list
  targets, missing manifest targets, and nonlinear spine targets.

## Verification

- `php -l lanes/pandoc/src/EpubPackageReader.php`
- `php -l lanes/pandoc/tests/EpubPackageReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php`
  passed: 1 file, 507 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 46 files, 78507 assertions, 0 failures.
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`

No Pandoc binary, EPUBCheck, zip/unzip, ZipArchive, browser renderer, Node
tooling, online validator, online service, live provider test, or live-service
provider test was invoked.

## Remaining Work

EPUB direct reader parity remains partial. Future bounded slices should continue
covering native package metadata, navigation, spine, media, accessibility, and
writer-handoff gaps without external validators or live services.
