# markerPDF XMP Structured List Boundary Current Base

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260607T141229Z`

Accepted base: `9fa2532d1407cdfcf7979d602b49aba1b4031366`

## Source Truth

Upstream `sddai/markerPDF` remains pinned in the lane manifest at
`da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. markerPDF keeps searchable PDF
text and document metadata as separate surfaces before OCR/model handoff. Under
this native no-GPU lane, catalog `/Metadata` XMP is parsed as document review
metadata, while arbitrary private RDF structures inside XMP lists must not be
flattened into WordPress titles, authors, descriptions, or keywords.

RDF list values used by `dc:title`, `dc:creator`, `dc:description`, and
`dc:subject` are scalar text values when they are plain text, explicit
`rdf:value`, or same-packet resource references that resolve to explicit text.
Structured list item nodes without `rdf:value` are not text metadata values and
stay review-only.

## Red First

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpStructuredListBoundaryCurrentBaseTest.php`

Failed before the parser change:

- expected title `Current Structured List XMP Title`, got flattened private
  structured child text `Structured Title Decoytitle qualifier decoy`;
- expected rejected-stream `author_count` `1`, got `2` because a private
  structured author list node was counted.

## Implementation

`PdfMetadataExtractor` now routes XMP Alt/list item extraction through
`xmpCollectionItemTextValue()`. The helper accepts plain scalar list item text,
explicit `rdf:value`, and valid same-packet `rdf:resource` / `rdf:nodeID`
references, but skips structured list item nodes that only contain private child
elements. Existing resource-wrapped lists, parseType Collection items with
`rdf:value`, attribute-membership lists, PDF/A schema parsing, and packet
boundaries remain unchanged.

## Verification

Focused test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpStructuredListBoundaryCurrentBaseTest.php`

Result: `1 test files, 52 assertions, 0 failures`.

Adjacent XMP metadata family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php $(rg --files lanes/markerpdf/tests | rg '/PdfMetadataXmp.*CurrentBaseTest\.php$' | sort)`

Result: `52 test files, 3225 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-structured-list-boundary-currentbase.php`

Result: exited `0`, emitting `title_from_scalar_xmp_item=true`,
`authors_from_scalar_items_only=true`,
`keywords_from_scalar_and_rdf_value_items_only=true`,
`structured_nodes_without_rdf_value_excluded=true`,
`trailing_packet_excluded=true`, `visible_text_excludes_xmp=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- Adds 2 focused PASS cases and 52 focused assertions.
- Adds 1 WordPress smoke/example.
- `phpPass`: `2886 -> 2888`.
- `wordpressScenarios`: `2411 -> 2412`.

## Non-Overlap

This does not repeat accepted catalog `/Metadata` null/reference/stream-role
boundaries, packet begin/end selection, duplicate RDF roots, resource-wrapped
lists, parseType Collection values with `rdf:value`, nested qualifiers,
attribute membership lists, typed-node `rdf:about`, PDF/A schema resource
metadata, associated-file XMP review, encrypted metadata source priority, xref
repair, text extraction, annotations, forms, images, OCR, or model execution.

The bounded behavior is specifically structured RDF list item nodes without
explicit `rdf:value` staying out of document XMP scalar/list metadata and
rejected-stream redacted summaries.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object
scanner, stream decoder, XMP packet boundary scanner, DOM-based RDF parser,
metadata review summary path, plain-text extractor, and WordPress smoke
renderer. Full upstream markerPDF scanned/OCR/model parity remains intentionally
out of scope under the no-GPU directive.
