# DOCX OpenXML External Relationship Target Policy

Hook: `plib-9z4ze`, Pandoc DOCX OpenXML package ingestion core blocker slice.

This slice extends the native PHP `DocxOpenXmlReader` package provenance for
external OPC relationship targets. Generic relationship summaries now expose
metadata-only external target policy fields:

- `externalTargetKind`
- `externalTargetScheme`
- `externalTargetAllowed`
- `externalTargetIssues`

Package summary rollups now count allowed versus unsafe external relationship
targets, external target kinds and schemes, unsafe issue buckets, and relationship
parts containing unsafe external targets. Relationship-type buckets expose the
same safe/unsafe policy rollups plus compact unsafe target snapshots.

The policy is review-only: the reader still does not fetch external targets,
execute relationship targets, shell out to Pandoc or office tools, or expose
package bytes beyond existing metadata.

Focused mapped case added:

- `summarizes docx external relationship target policy for package review`
- Expected direct-format movement: DOCX/OpenXML package-ingestion PHP PASS cases
  `+1`; `DocxOpenXmlReaderTest.php` assertions `3871 -> 3962`.

Verification:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `1 test files, 3962 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `203 test files, 170788 assertions, 0 failures`
