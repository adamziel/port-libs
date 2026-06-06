# markerPDF XMP indirect Filter operand boundary current-base

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260606T155302Z`  
Base accepted HEAD: `bb0379a1bd259483ad4081f32c8d1179a07f099f`

## Behavior

Catalog `/Metadata` XMP streams now review `/Filter` operands before document
metadata promotion. An indirect helper object must resolve to exactly one
filter value, so a helper body such as `/FlateDecode /Crypt 8 0 R` no longer
decodes with the first name while hiding the trailing operand.

Malformed helpers are kept review-only with:

- `status=rejected_malformed_metadata_stream_filter_operand`
- `filter_operand_policy=reject_malformed_filter_operands`
- `invalid_filter_operand_count=1`
- `malformed_filter_operand_count=1`
- `extra_filter_name=Crypt`

Valid single-name indirect helpers such as `/FlateDecode` still decode and
promote XMP metadata normally.

The patch does not run Python, PDFium, OCR, Surya, Texify, Torch, external PDF
tools, model workers, or live services.

## Red-First Evidence

Before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpIndirectFilterOperandBoundaryCurrentBaseTest.php`

Result: `1 test files, 17 assertions, 1 failures`

Failure: the malformed helper case returned `source=['xmp','info']` instead
of the expected `source=['info','catalog']`, proving the indirect helper's
leading `/FlateDecode` was enough to promote XMP while the trailing `/Crypt`
operand was ignored.

## Verification

After the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpIndirectFilterOperandBoundaryCurrentBaseTest.php`

Result: `1 test files, 53 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-indirect-filter-operand-boundary-currentbase.php`

Result: exits `0` and emits `source=['info','catalog']`, `xmp_promoted=false`,
`review_status=rejected_malformed_metadata_stream_filter_operand`,
`filter_operand_policy=reject_malformed_filter_operands`,
`invalid_filter_operand_count=1`, `malformed_filter_operand_count=1`,
`extra_filter_name=Crypt`, `xmp_title_excluded=true`,
`helper_tail_not_visible=true`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted XMP packet begin/end handling, complete-packet
fallback, empty-root fallback, namespace/comment/CDATA/entity/resource
boundaries, catalog `/Metadata` duplicate/direct/unresolved/non-stream
reviews, stream-object tail rejection, duplicate `/Type` or `/Subtype`
metadata stream dictionaries, fake `/Filter` names inside dictionary strings,
CMap malformed filter operand review, xref repair, attachment filters, image
filters, OutputIntent metadata, annotations, forms, page geometry, supplied
table/equation handoffs, or any OCR/model work.

The new boundary is specifically document XMP promotion through an indirect
metadata stream `/Filter` helper object whose body contains extra top-level
operands after the selected filter name.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF
object scanner, catalog `/Metadata` resolver, stream dictionary parser,
existing stream decoder, XMP parser, metadata review output, and WordPress
smoke path. Remaining GPU/model/OCR/table/equation inference gaps stay outside
the current no-GPU markerPDF scope.
