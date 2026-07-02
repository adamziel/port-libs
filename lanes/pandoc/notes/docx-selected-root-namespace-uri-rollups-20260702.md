# DOCX selected XML root namespace declaration URI rollups

Slice: `plib-x9v26`
Base: `89949a7748`

## Behavior

- `DocxOpenXmlReader` now carries namespace declaration URI metadata for selected DOCX/OpenXML XML parts.
- Each selected XML part exposes root namespace declaration URIs, URI counts, and prefix-to-URI maps alongside the existing declaration counts and prefixes.
- Package provenance summary now exposes selected XML root namespace declaration URI counts and sorted URI lists.
- This remains metadata-only and does not expose XML payload bytes.

## Evidence

```text
php -l lanes/pandoc/src/DocxOpenXmlReader.php
php -l lanes/pandoc/tests/DocxOpenXmlSelectedXmlRootByteAggregateTest.php
php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlSelectedXmlRootByteAggregateTest.php
1 test files, 52 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlSelectedXmlRootByteAggregateTest.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php
2 test files, 12560 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests/DocxOpenXml*.php
77 test files, 16982 assertions, 0 failures

jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json
git diff --check -- lanes/pandoc
```

## Delta

- Added one focused DOCX/OpenXML selected XML root namespace declaration URI rollup case.
- Added 14 focused assertions on URI counts, URI lists, summary parity, and per-part prefix maps.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2883 -> 2884`.
- `mappedDocxSelectedXmlRootNamespaceDeclarationUriCases`: `1`.
- `docxSelectedXmlRootNamespaceDeclarationUriAssertions`: `14`.

## Non-Overlap

This does not repeat selected XML root byte aggregation, root name/namespace buckets, XML declaration, processing-instruction, doctype, package XML CDATA/comment/prolog provenance, package path, relationship, content-type, or ZIP source-record slices. It only adds namespace declaration URI rollups for already-selected DOCX/OpenXML XML part roots.

No Pandoc, office suite, TeX/browser engine, unzip/zip command, external validator, Node, or live service was invoked.
