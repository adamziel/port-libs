# Pandoc EPUB3 Spine Itemref Authoring Provenance

Slice: `plib-8tlqs`

EPUB3 package ingestion now preserves OPF spine `itemref` authoring metadata for
package review queues without shelling out to Pandoc, EPUBCheck, zip/unzip,
browser renderers, external validators, online services, or live provider tests.

## Coverage

- `EpubReader::readSpine()` preserves itemref `xml:lang`, `dir`, complete raw
  OPF attributes, and non-structural custom attributes including data-* and
  namespaced review attributes.
- `spineProperties.authoring` summarizes itemref authoring coverage by index,
  language, direction, and custom-attribute presence.
- Spine content provenance and XHTML AST child attributes now carry the itemref
  authoring fields alongside existing manifest provenance.

## Verification

- `php -l lanes/pandoc/src/EpubReader.php`
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - 1 test file, 4181 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 70761 assertions, 0 failures

## Accounting

- New mapped row: `mappedEpubSpineItemrefAuthoringProvenanceCases = 1`
- New assertion row: `epubSpineItemrefAuthoringProvenanceAssertions = 28`
