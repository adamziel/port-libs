# DOCX OpenXML Relationship Source Base Name Stems

Bead: `plib-7rkwx`

This slice stays within native PHP DOCX/OpenXML package ingestion recovery. `DocxOpenXmlReader` now carries `sourceBaseNameStem` through relationship source provenance and summarizes it in `packageProvenance.summary` with `relationshipSourceBaseNameStemCount`, `relationshipSourceBaseNameStemCounts`, `duplicateRelationshipSourceBaseNameStemCount`, `duplicateRelationshipSourceBaseNameStems`, and `relationshipSourceBaseNameStems`.

The focused fixture covers two different existing source parts sharing the `review` stem, a missing source relationship part, and a relationship part used as a relationship source. Each stem bucket preserves source/existence counts, relationship counts, source-kind buckets, base-name/extension/content-type/source/role counts, source directories, source parts, relationship parts, byte totals, and the largest existing source part without exposing package bytes.

No Pandoc, Word, LibreOffice, office suites, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.

Verification:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` passed: 1 test files, 10528 assertions, 0 failures.

Parity accounting:

- `phpPass`: `464 -> 465`
- mapped upstream manifest cases: `2307 -> 2308`
- `mappedDocxRelationshipSourceBaseNameStemCases`: `1`
