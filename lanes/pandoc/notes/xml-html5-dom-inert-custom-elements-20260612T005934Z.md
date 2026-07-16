# XML/HTML5 DOM Inert and Custom Element Attributes - 2026-06-12

Slice: `plib-rbosl`

Implemented bounded HTML global-attribute provenance for inert and
custom-element/shadow DOM review attributes in `XmlHtmlDom`:

- `inert` state and raw attribute value.
- `slot` raw/name/validity metadata.
- `part` raw tokens, de-duplicated valid part names, invalid tokens, and validity.
- `exportparts` parsed mapping records, aliases, invalid records, and validity.
- `is` custom built-in element name metadata and validity.

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - 1 file, 1068 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 files, 68117 assertions, 0 failures
