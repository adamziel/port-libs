# pandoc-docx-openxml-settings-policy-current-base-20260612T180644Z

Slice: `plib-iq6l9`, DOCX OpenXML package ingestion.

This slice extends native PHP `DocxOpenXmlReader` settings package ingestion for
relationship-selected `word/settings.xml`-equivalent parts. After refinery
rebase over `447ffd1881`, the reader preserves additional settings policy
metadata:

- document protection algorithm/hash/salt/spin-count fields;
- write-protection recommendation and hash metadata;
- revision-view display toggles;
- proofing state;
- hyphenation controls;
- save-policy booleans for forms data, preview pictures, smart-tag embedding,
  TrueType embedding, system fonts, and subset fonts.

Relationship-selected settings parts also receive a `settings` package inventory
role, so review queues can distinguish the settings part from generic document
relationship targets.

The fixture stays bounded to inert package metadata. It does not invoke Pandoc,
Word, LibreOffice, office suites, zip/unzip, browser renderers, external
validators, online services, live provider tests, or live-service provider
tests.

## Accounting

- `phpPass`: `3254 -> 3255`
- `phpFail`: `0`
- `mappedDocxOpenXmlSettingsPolicyCases`: `1`
- `docxOpenXmlSettingsPolicyAssertions`: `38`

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed with `1 test files, 2136 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests` passed with `44 test files,
  72726 assertions, 0 failures`.
