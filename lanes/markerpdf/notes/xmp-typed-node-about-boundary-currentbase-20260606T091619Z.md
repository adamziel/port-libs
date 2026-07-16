# XMP Typed Node About Boundary Current Base

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260606T091619Z`

Accepted base: `a8baa49d45412cacff68b3c4ff80637c9e8e50fb`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit
`da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` extracts searchable PDF document
metadata before OCR/layout/model stages. In this native PHP/no-GPU lane, catalog
`/Metadata` XMP streams are handled as RDF/XML document metadata when the
top-level RDF resource describes the current document.

XMP typed RDF node elements can describe the document when they explicitly use
`rdf:about=""`. A top-level typed node with no `rdf:about`, `rdf:ID`, or
`rdf:nodeID` is an anonymous/blank resource, not the document resource. Such a
blank typed node must not override the WordPress title, authors, keywords,
producer, dates, or rejected-stream summary for the real document node.

## Red-First Evidence

Before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpTypedNodeAboutBoundaryCurrentBaseTest.php`

Result: `1 test files / 13 assertions / 3 failures`

Failures:

- accepted document title was `Private Blank Typed XMP Title` instead of
  `Current Typed About XMP Title`;
- explicit `rdf:about=""` typed document nodes were hidden by the preceding
  anonymous typed resource;
- rejected-stream summary counted `1` private author instead of `2` document
  authors.

## Implementation

`PdfMetadataExtractor::xmpElementIsNonDocumentResource()` now treats missing
`rdf:about` differently for RDF descriptions and typed RDF nodes:

- direct `rdf:Description` children without `rdf:about` remain accepted as
  document metadata roots for producer compatibility;
- direct non-RDF typed node elements must use `rdf:about=""` to be document
  metadata roots;
- typed nodes with `rdf:ID`, `xml:id`, `rdf:nodeID`, or non-empty `rdf:about`
  remain resource/reference targets rather than top-level document metadata;
- rejected non-metadata XML stream summaries use the same document-resource
  boundary and keep anonymous typed-resource text/dates out of payload-free
  review metadata.

## Verification

Focused typed-node `rdf:about` boundary:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpTypedNodeAboutBoundaryCurrentBaseTest.php`

Result: `1 test files / 49 assertions / 0 failures`.

Adjacent metadata/XMP family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php $(rg --files lanes/markerpdf/tests | rg '/PdfMetadataXmp.*CurrentBaseTest\.php$' | sort)`

Result: `41 test files / 2674 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-typed-node-about-boundary-currentbase.php`

Result: passed. The smoke emits `title_from_document_xmp=true`,
`authors_from_document_xmp=true`, `creator_tool_from_document_xmp=true`,
`anonymous_typed_resource_excluded=true`,
`rejected_summary_uses_document_resource=true`, `trailing_packet_excluded=true`,
`visible_text_excludes_xmp=true`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

PHP lint:

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php`
- `php -l lanes/markerpdf/tests/PdfMetadataXmpTypedNodeAboutBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-xmp-typed-node-about-boundary-currentbase.php`

Result: no syntax errors.

Lane status JSON:

`php -r '$json = json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (!is_array($json)) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status.json valid\n";'`

Result: `lane-status.json valid`.

Required whitespace check:

`git diff --check -- lanes/markerpdf`

Result: passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2484 -> 2487` from three focused TestRunner PASS cases.
- `wordpressScenarios`: `2116 -> 2117` from the new WordPress typed-node
  `rdf:about` boundary smoke.
- Adds 49 focused assertions after the red-first failure.
- Does not update root progress/dashboard files.

## Non-Overlap

This does not repeat accepted catalog `/Metadata` null/direct/unresolved/
unreadable boundaries, non-metadata XML stream rejection, packet padding,
complete-packet fallback, unpaired-begin handling, instruction filtering,
DTD/entity rejection, CDATA/comment root selection, namespace wrapper
filtering, same-prefix namespace packets, self-closing/empty roots, compact RDF
attributes, language alternatives, qualified/nested values, resource-reference
fragment targets, nodeID blank-node resolution, split descriptions, sparse
lists, typed-node `rdf:about=""` promotion, external non-empty `rdf:about`
filtering, FileSpec XMP generation exactness, encrypted metadata source
priority, OutputIntent/PieceInfo/name-tree metadata review, xref repair, fonts,
images, annotations, forms, OCR, or model execution.

The bounded behavior is only anonymous top-level typed RDF nodes with absent
`rdf:about` staying out of document metadata selection and rejected-stream
summary counts while explicit `rdf:about=""` typed document nodes continue to
work.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object
scanner, stream decoder, catalog metadata boundary validation, XMP XML packet
boundary logic, DOM-based RDF parser, Info fallback, rejected-stream summary
path, plain-text extractor, and WordPress smoke renderer. Full upstream
markerPDF parity for scanned/OCR/model-driven layouts remains intentionally out
of scope under the no-GPU directive.
