# XMP parseType Collection boundary current-base

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260606T183031Z`

Session: `port-dev-markerpdf-metadata-xmp-20260606T183031Z`

Accepted base: `8e54b21f9fe69b8e0cb46c644ce6d3d23fb9b9ee`

## Source truth

- Pinned upstream markerPDF source in `UPSTREAM_TEST_MANIFEST.json`: `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream scope is metadata/text conversion without promoting raw metadata packet bytes into Markdown output.
- RDF/XML syntax defines `rdf:parseType="Collection"` property elements as ordered collection node elements: https://www.w3.org/TR/rdf-syntax-grammar/

## Behavior

`PdfMetadataExtractor` now treats `rdf:parseType="Collection"` property node elements as ordered XMP collection items for:

- promoted document metadata lists such as `dc:creator` and `dc:subject`;
- rejected metadata-stream review summaries, so fail-closed XML review counts match the accepted metadata path.

The boundary remains strict: qualifiers, unreferenced collection resources, trailing XMP decoys, and raw XMP packet text are not promoted to visible WordPress paragraphs.

## Red-first Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpParseTypeCollectionBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL promotes RDF parseType Collection XMP list nodes as ordered metadata values
Expected authors [ParseType Collection Author One, ParseType Collection Author Two], actual [ParseType Collection Author One]
FAIL summarizes rejected XMP streams with parseType Collection counts only
Expected author_count 2, actual 1
1 test files, 18 assertions, 2 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpParseTypeCollectionBoundaryCurrentBaseTest.php
1 test files, 49 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataPieceInfoAssociatedXmpCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataAssociatedFileOutputIntentEncryptXmpCurrentBaseTest.php
50 test files, 3110 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-xmp-parse-type-collection-boundary-currentbase.php
emits authors_from_parse_type_collection=true, keywords_from_parse_type_collection=true, info_author_not_promoted=true, packet_boundary_applied=true, qualifier_text_excluded=true, unreferenced_collection_excluded=true, trailing_decoy_excluded=true, visible_text_excludes_xmp=true, executes_python_or_models=false, executes_external_pdf_tools=false
```

PHP lint passed for the changed source, test, and example files. `git diff --check -- lanes/markerpdf` passed.

## Status Delta

- `lane-status.json` `phpPass`: `2641 -> 2643` from the two new focused PASS cases.
- `lane-status.json` `wordpressScenarios`: `2235 -> 2236` from the new parseType Collection WordPress smoke.
- `UPSTREAM_TEST_MANIFEST.json` adds `pdfMetadataXmpParseTypeCollectionCurrentBase` with one mapped behavior and increments top-level WordPress scenario coverage to `1097`.

## Non-overlap

This does not repeat accepted XMP packet padding, complete-packet fallback, internal begin/end, processing-instruction attributes, undeclared encoding fallback, UTF-16 packet decoding, entity handling, namespace handling, empty/self-closing roots, resource references, nodeID references, RDF membership properties, attribute membership properties, resource-wrapped lists, sparse list handling, typed node handling, nested qualifier exclusion, associated-file XMP/PDF-A review, or OutputIntent metadata slices.

## Dependency Closure

No new support component is required. The patch reuses the native PHP PDF object parser, stream decoder, DOM-based XMP parser, metadata review summary, text extractor, and lane-local WordPress smoke path. GPU/OCR/model execution, Python, pypdfium, PIL, PDFium, and external PDF tools remain intentionally out of scope for this markerPDF slice.
