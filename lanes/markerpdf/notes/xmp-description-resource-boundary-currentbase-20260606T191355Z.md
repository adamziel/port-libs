# XMP Description Resource Boundary Current Base

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260606T191355Z`

Base accepted HEAD: `6ee64e8398d01c4bd51ef8bc1f2d16d007c2db92`

## Source Truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`
keeps searchable PDF text extraction separate from document metadata/review
surfaces. In the native no-GPU PHP lane, catalog `/Metadata` XMP is parsed as
document metadata only when the RDF node describes the document; RDF resource
reference descriptions stay review-only and must not become WordPress titles,
authors, keywords, or visible paragraph text.

## Change

`PdfMetadataExtractor::xmpElementIsNonDocumentResource()` now treats top-level
RDF nodes with `rdf:resource` as non-document resource references. This keeps
malformed or private resource-wrapper `rdf:Description` nodes out of promoted
document XMP metadata while preserving accepted legacy unadorned
`rdf:Description` document metadata.

The focused fixture proves:

- a private top-level `rdf:Description rdf:resource="#privateDescription"` is
  skipped before the current document `rdf:Description rdf:about=""`;
- an unadorned document `rdf:Description` remains accepted for producer
  compatibility;
- rejected non-metadata XML stream summaries count only document metadata
  fields and keep private resource text redacted;
- XMP values remain excluded from visible WordPress paragraph text.

## Red-First Evidence

Before the implementation change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpDescriptionResourceBoundaryCurrentBaseTest.php`

Result: failed with `1 test files, 18 assertions, 3 failures`.

Key failures:

- accepted document title was `Private Resource Description XMP Title` instead
  of `Current Description Resource XMP Title`;
- legacy unadorned document title was also replaced by the private resource
  title;
- rejected-stream summary reported `author_count` as `1` instead of `2`.

## Verification

Focused after fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpDescriptionResourceBoundaryCurrentBaseTest.php`

Result: `1 test files, 55 assertions, 0 failures`.

Adjacent XMP metadata family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php $(rg --files lanes/markerpdf/tests | rg '/PdfMetadataXmp.*CurrentBaseTest\.php$' | sort)`

Result: `49 test files, 3072 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-description-resource-boundary-currentbase.php`

Result: passed. The smoke emits `title_from_document_xmp=true`,
`authors_from_document_xmp=true`, `creator_tool_from_document_xmp=true`,
`resource_description_excluded=true`, `resource_target_scalar_excluded=true`,
`rejected_summary_uses_document_description=true`,
`trailing_packet_excluded=true`, `visible_text_excludes_xmp=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser,
stream decoder, catalog `/Metadata` boundary checks, XMP packet/root scanner,
DOM-based RDF parser, Info fallback, rejected-stream summary path, and
WordPress smoke path. Live OCR, Surya/Texify/Torch, pypdfium/PDFium, PIL,
Streamlit/FastAPI model workers, and external PDF tools remain intentionally
out of scope for the no-GPU markerPDF lane.

## Non-Overlap

This does not repeat accepted XMP catalog `/Metadata` null/direct/unresolved
boundaries, packet padding, duplicate RDF root selection, DTD/entity rejection,
namespace root filtering, language alternatives, resource references from
properties, `rdf:nodeID`, external `rdf:about`, typed nodes, parseType
Collection, qualified values, nested qualifiers, resource-wrapped lists,
attribute membership, text-subject splitting, FileSpec XMP generation
exactness, encrypted metadata source priority, OutputIntent/PieceInfo/name-tree
metadata review, xref repair, fonts, images, annotations, forms, outlines, or
runtime/model behavior. The bounded behavior is specifically top-level
`rdf:Description` nodes with `rdf:resource` staying out of document XMP
metadata selection and rejected-stream field summaries.

## Next Task

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser
behavior: fonts, CMaps, stream filters, xref repair, metadata, outlines,
annotations, forms, page geometry, image/filter metadata, or supplied-boundary
table/equation handoffs.
