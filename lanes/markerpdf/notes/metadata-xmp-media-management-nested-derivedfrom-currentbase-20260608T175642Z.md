# markerPDF XMP media-management nested DerivedFrom boundary

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260608T175642Z`

Accepted base: `f2ba04d4070c87822ee15c9bf00e9247a5017259`

## Source Truth

- Adobe XMP Media Management uses the `http://ns.adobe.com/xap/1.0/mm/` namespace for document identity fields such as `xmpMM:DocumentID`, `xmpMM:InstanceID`, `xmpMM:OriginalDocumentID`, and structured provenance such as `xmpMM:DerivedFrom`.
- The `xmpMM:DerivedFrom` ResourceRef value may be serialized as a same-packet reference or as an inline RDF structured node. This slice covers the bounded inline form:
  `<xmpMM:DerivedFrom><rdf:Description stRef:documentID="..."/></xmpMM:DerivedFrom>`.
- In this no-GPU markerPDF lane, catalog `/Metadata` XMP is document metadata and WordPress import review data. Identifier bytes, private resources, qualifier text, and stale appended packets must not become visible paragraph text.

## Implementation

- `PdfMetadataExtractor::xmpMediaManagementDerivedFrom()` now routes the value element through a media-management-specific structured-resource helper.
- The helper preserves existing same-packet `rdf:resource` / `rdf:nodeID` uniqueness rules, then unwraps a nested `rdf:Description` or `stRef:*` resource node only when it carries `stRef:documentID`, `stRef:instanceID`, or `stRef:originalDocumentID`.
- Rejected non-document XML metadata streams still expose only summary field names and derived-reference presence; identifier values remain redacted.

## Red-First Evidence

Initial focused run before source changes:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpMediaManagementNestedResourceBoundaryCurrentBaseTest.php`

Result: failed as expected with `1 test files, 25 assertions, 2 failures` because `media_management.derived_from` was absent for the nested RDF `Description` form.

## Verification

Focused new test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpMediaManagementNestedResourceBoundaryCurrentBaseTest.php`

Result: `1 test files, 52 assertions, 0 failures`.

Adjacent media-management coverage:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpMediaManagementNestedResourceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpMediaManagementBoundaryCurrentBaseTest.php`

Result: `2 test files, 96 assertions, 0 failures`.

Broader XMP current-base family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php`

Result: `72 test files, 3371 assertions, 0 failures`.

Metadata extractor adjacency:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadataXmpMediaManagementNestedResourceBoundaryCurrentBaseTest.php`

Result: `2 test files, 936 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-media-management-nested-derivedfrom-currentbase.php`

Emits `document_id_preserved=true`, `derived_from_preserved=true`, `packet_boundary_applied=true`, `private_resource_decoy_excluded=true`, `trailing_packet_decoy_excluded=true`, `qualifier_text_excluded=true`, `visible_text_excludes_xmp_ids=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax:

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfMetadataXmpMediaManagementNestedResourceBoundaryCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-xmp-media-management-nested-derivedfrom-currentbase.php` passed.

Whitespace:

- `git diff --check -- lanes/markerpdf` passed.

## Non-Overlap

This does not repeat accepted catalog `/Metadata` direct-array review, `/Metadata` operand/filter/length/DecodeParms boundaries, XMP packet begin/end/root selection, unsafe DTD/entity handling, compact RDF attribute membership, simple XMP media-management same-packet resource references, duplicate resource-reference ambiguity, nested scalar resource references, PDF/A schema resource extraction, or Image XObject CTM recovery. The bounded behavior is nested RDF/XML ResourceRef extraction for `xmpMM:DerivedFrom` only.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, Flate stream decoder, XMP packet/root selector, DOM-based XMP value extraction helpers, text extractor, and WordPress smoke pattern. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, signature validation, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF metadata, fonts/CMaps, stream filters, xref repair, annotations, forms, security preflight, page geometry, image/filter metadata, and supplied-boundary table/equation handoff gaps.
