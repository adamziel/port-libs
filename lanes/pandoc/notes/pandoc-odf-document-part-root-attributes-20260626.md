# ODF XML document-part root attribute provenance

Hook: `plib-bb0xs`, Pandoc ODF/ODT OpenDocument package ingestion core blocker slice.

## Slice

`OdfReader` now preserves root-level attribute provenance for the core ODT XML
document parts already covered by `documentPartVersions`: `content.xml`,
`styles.xml`, `meta.xml`, and `settings.xml`.

The report now includes root attribute names, structural flags, custom
attribute maps, namespace declarations, aggregate custom-attribute counts, and
per-part custom-attribute items. `office:version` is marked structural; custom
producer/review attributes such as `wp:*` and `xml:lang` remain visible as
metadata-only provenance without exposing document-part bytes beyond the
existing package metadata fields.

## Direct parity accounting

- `phpPass`: 441 -> 442
- `phpFail`: 0
- `mappedOdfDocumentPartRootAttributeProvenanceCases`: 1
- `odfDocumentPartRootAttributeProvenanceAssertions`: 20

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderDocumentPartRootAttributesTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderDocumentPartRootAttributesTest.php`
  - 1 test file, 20 assertions, 0 failures

I also ran `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` while
the new test temporarily lived in that file. The new case passed, but the full
file still reported 22 pre-existing writer-output baseline failures unrelated
to this slice, consistent with the lane-status full-suite backlog.

No Pandoc, office suites, TeX/browser engines, zip/unzip, ZipArchive, Node
tooling, external validators, online services, or live provider tests were
invoked.
