# DOCX/OpenXML Selected Root Name Rollups

Slice: `plib-dnr3d`, DOCX/OpenXML package ingestion.

`DocxOpenXmlReader` now reports metadata-only selected XML root namespace,
local-name, and qualified-name rollups in `packageProvenance.selectedXmlParts`
and mirrors them through `packageProvenance.summary`. Missing or malformed
optional selected XML sidecars stay out of the root-name buckets, so the rollups
only describe inspectable selected package parts without exposing XML text or
package bytes.

Accounting:
- `phpPass`: `480 -> 481`
- `phpFail`: `0`
- Focused `DocxOpenXmlReaderTest.php` assertions: `13762 -> 13785`

Validation:
- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check origin/integration/pandoc-package-docx...HEAD`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed after rebase: 1 file, 13785 assertions, 0 failures.

No Pandoc, office suite, TeX/browser engine, `zip`/`unzip`, external validator,
or network-backed converter was invoked for this slice.
