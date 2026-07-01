# DOCX OpenXML Subdocument External Target Policy

Hook: `plib-v5q88`, Pandoc DOCX OpenXML package ingestion core blocker slice.

`DocxOpenXmlReader` now carries shared OPC external-target policy metadata into
DOCX `subDocument` package diagnostics. Subdocument records expose external
target kind, scheme, allow/deny decision, unsafe-target issue codes, unsafe
target lists, and package summary counters for allowed versus unsafe external
targets.

This remains metadata-only package ingestion. Master-document expansion is still
not implemented, subdocument payload bytes stay blocked, and
`directReaderParity=false` continues to report
`subdocument-master-document-expansion-not-implemented`.

No Pandoc, office suite, TeX/browser engine, zip/unzip command, Jupyter, Node
tooling, external validator, online service, live provider test, or
live-service provider test was run.

Verification:

```bash
php -l lanes/pandoc/src/DocxOpenXmlReader.php
php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php
php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php
```

Result: focused `DocxOpenXmlReaderTest.php` passed `1 test files, 10533
assertions, 0 failures`.
