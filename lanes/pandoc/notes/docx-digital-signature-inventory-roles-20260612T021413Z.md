# docx-digital-signature-inventory-roles-20260612T021413Z

Slice: `plib-r1rxf`, DOCX/OpenXML package ingestion.

This slice keeps existing DOCX package digital-signature parsing metadata-only
and adds package inventory roles for signature-bearing package parts:

- `_xmlsignatures/origin.sigs` now carries `digital-signature-origin`.
- `_xmlsignatures/_rels/origin.sigs.rels` now carries
  `digital-signature-relationships`.
- signature XML targets now carry `digital-signature-signature`.

`packageProvenance.summary` also exposes aggregate inventory counts for those
roles so WordPress package review queues can distinguish inert signature
payloads from ordinary relationship targets without exposing them as document
media.

Verification on current main `412827d77a`:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `1 test files, 1575 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 69480 assertions, 0 failures`

No Pandoc, Word, LibreOffice, office suites, zip/unzip, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.
