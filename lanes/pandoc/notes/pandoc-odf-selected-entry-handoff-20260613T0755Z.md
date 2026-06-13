# ODF selected entry handoff provenance

Slice: `pandoc-odf-selected-entry-handoff`

## Scope

`OdfReader` now exposes `packageProvenance.selectedEntryHandoff` for ODT
package review queues. The report reuses `ZipPackage::entryHandoffPreflight()`
and selects:

- `mimetype` and `META-INF/manifest.xml`;
- manifest-declared `content.xml`, `styles.xml`, `meta.xml`, and `settings.xml`
  package parts when present;
- manifest-declared package bytes whose exposure policy is normal, missing, or
  blocked only by unsupported compression.

Metadata-only sidecars such as scripts, configurations, fonts, RDF, thumbnails,
object replacements, embedded object payloads, encrypted items, and missing
media-type entries stay out of selected byte handoff. The selected report keeps
local fixed-header, compression, CRC, SHA-256, missing optional entry, role
summary, and unsupported compression failure provenance visible through both the
import report and document manifest attributes.

## Accounting

- `phpPass`: `3360 -> 3361` after rebasing over JATS/BITS table body diagnostics
- `phpFail`: `0`
- `mappedOdfSelectedEntryHandoffCases`: `0 -> 1`
- `odfSelectedEntryHandoffAssertions`: `0 -> 49`
- ODF/ODT readiness local mapped cases: `51 -> 52`
- ODF/ODT readiness focused assertions: `5966 -> 6015`

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 4534 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OdfOdtShipReadinessStatusTest.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OdtReaderTest.php lanes/pandoc/tests/OpenDocumentReaderTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - `5 test files, 6015 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 75778 assertions, 0 failures`

No Pandoc binary, office suite, `zip`/`unzip`, `ZipArchive`, browser renderer,
Node tooling, external validator, online service, live provider test, or
live-service provider test was invoked.
