# XMP External About Boundary Current Base

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260605T221453Z`

Base: `8939d9ec1b75b1ccc78dcd11b00b99d8e8fa44a9`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit
`da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF extraction
through native/document metadata before OCR, layout, and model stages. In this
no-GPU PHP lane, catalog `/Metadata` XMP streams are document metadata only
when their top-level RDF nodes describe the current document resource.

XMP `rdf:about` identifies the resource described by an RDF node. Empty or
absent `rdf:about` is the document packet root used by PDF producers. Fragment
targets and blank nodes are local resource targets, and non-empty external
`rdf:about` values describe another resource. Those external-resource nodes
must not supply WordPress document title, authors, keywords, producer, dates, or
rejected-stream field counts.

## Behavior

`PdfMetadataExtractor::xmpTopLevelDescriptions()` now filters top-level XMP
resource nodes before metadata selection:

- `rdf:about=""` and absent `rdf:about` remain document metadata roots;
- non-empty `rdf:about` values, `rdf:ID`, `xml:id`, and `rdf:nodeID` remain
  reference/resource targets rather than document metadata roots;
- document-level XMP fields following an external-resource decoy win over that
  decoy;
- rejected XML metadata-stream summaries count only document-resource fields and
  keep external-resource text redacted;
- visible WordPress text still comes only from page content, not XMP payloads.

## Red-First Evidence

Before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpExternalAboutBoundaryCurrentBaseTest.php`

Result: `1 test files / 16 assertions / 2 failures`

Failures:

- document metadata title was `External Resource Decoy XMP Title`;
- rejected-stream author count was `1` from the external-resource author instead
  of `2` from the document-resource creators.

## Verification

Focused external-about XMP boundary:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpExternalAboutBoundaryCurrentBaseTest.php`

Result: `1 test files / 48 assertions / 0 failures`

Adjacent metadata/XMP family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php`

Result: `34 test files / 2315 assertions / 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-external-about-boundary-currentbase.php`

Result: passed. The smoke emits `title_from_document_xmp=true`,
`authors_from_document_xmp=true`, `external_about_values_excluded=true`,
`trailing_packet_excluded=true`, `visible_text_excludes_xmp=true`,
`packet_boundary_applied=true`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

PHP lint:

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php`
- `php -l lanes/markerpdf/tests/PdfMetadataXmpExternalAboutBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-xmp-external-about-boundary-currentbase.php`

Result: no syntax errors.

Required whitespace check:

`git diff --check -- lanes/markerpdf`

Result: passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2241 -> 2243` from two focused TestRunner PASS cases.
- `wordpressScenarios`: `1931 -> 1932` from the new WordPress external-about
  XMP smoke.

## Non-Overlap

This does not repeat accepted catalog `/Metadata` null/direct/unresolved/
unreadable boundaries, non-metadata XML stream rejection, packet padding,
complete-packet fallback, unpaired-begin handling, instruction filtering,
DTD/entity rejection, CDATA/comment root selection, namespace wrapper
filtering, same-prefix namespace packets, self-closing/empty roots, compact RDF
attributes, language alternatives, qualified/nested values, resource-reference
fragment targets, nodeID blank-node resolution, split descriptions, sparse
lists, FileSpec XMP generation exactness, encrypted metadata source priority,
OutputIntent/PieceInfo/name-tree metadata review, xref repair, fonts, images,
annotations, forms, OCR, or model execution.

The bounded behavior is only top-level XMP resource ownership for non-empty
external `rdf:about` nodes before WordPress document metadata selection and
redacted rejected-stream summaries.

## Dependency Closure

No new support component is needed. This slice reuses native PHP PDF stream
decoding, catalog metadata boundary validation, DOM-based XMP parsing, trailer
Info fallback, text extraction, and the existing WordPress smoke pattern.
GPU/model/OCR/PDFium/Python execution remains intentionally out of scope under
the current no-GPU markerPDF directive.
