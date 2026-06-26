# DOCX OpenXML attached-template external policy slice

Bead: `plib-02z84`

This slice promotes DOCX settings attached-template external target policy from
item-level metadata into `packageProvenance.summary` counters. The summary now
distinguishes allowed external template targets from unsafe external template
targets and preserves external target kind, scheme, and issue-code buckets for
review handoff.

The implementation remains metadata-only:

- attached template bytes stay blocked under `attached-template-bytes-blocked`;
- external targets are never fetched;
- the package summary only records relationship target policy metadata.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed with 1 file, 9,733 assertions, 0 failures.
