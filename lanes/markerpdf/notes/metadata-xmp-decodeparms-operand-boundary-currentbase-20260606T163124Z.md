# markerPDF XMP DecodeParms operand boundary current-base

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260606T163124Z`  
Base accepted HEAD: `c2a449a8be7ac8fe1255b17c88c9bcad3f87e41d`

## Behavior

Catalog `/Metadata` XMP streams now review `/DecodeParms` operands before
document metadata promotion. A direct or indirect helper may resolve to one
dictionary, `null`, or an array of valid dictionary/null operands. If an
indirect helper object carries extra top-level operands after the dictionary,
such as `<< /Predictor 1 >> /Crypt 8 0 R`, the stream is kept review-only
instead of promoting the decoded XMP packet as trusted document metadata.

Malformed helpers are reported with:

- `status=rejected_malformed_metadata_stream_decodeparms_operand`
- `decodeparms_operand_policy=reject_malformed_decodeparms_operands`
- `invalid_decodeparms_operand_count=1`
- `malformed_decodeparms_operand_count=1`
- `extra_decodeparms_name=Crypt`

Valid single-dictionary indirect helpers such as `<< /Predictor 1 /Columns 1 >>`
still decode and promote XMP metadata normally.

The patch does not run Python, PDFium, OCR, Surya, Texify, Torch, external PDF
tools, model workers, or live services.

## Red-First Evidence

Before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpDecodeParmsOperandBoundaryCurrentBaseTest.php`

Result: `1 test files, 17 assertions, 1 failures`

Failure: the malformed helper case returned `source=['xmp','info']` instead
of the expected `source=['info','catalog']`, proving that `/FlateDecode` XMP
promotion ignored the indirect `/DecodeParms` helper tail after the dictionary.

## Verification

After the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpDecodeParmsOperandBoundaryCurrentBaseTest.php`

Result: `1 test files, 53 assertions, 0 failures`

Related XMP boundary regression set:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpDecodeParmsOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpIndirectFilterOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpStreamFilterDictionaryBoundaryCurrentBaseTest.php`

Result: `3 test files, 144 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-decodeparms-operand-boundary-currentbase.php`

Result: exits `0` and emits `source=['info','catalog']`, `xmp_promoted=false`,
`review_status=rejected_malformed_metadata_stream_decodeparms_operand`,
`decodeparms_operand_policy=reject_malformed_decodeparms_operands`,
`invalid_decodeparms_operand_count=1`, `malformed_decodeparms_operand_count=1`,
`extra_decodeparms_name=Crypt`, `xmp_title_excluded=true`,
`helper_tail_not_visible=true`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted XMP packet begin/end handling, complete-packet
fallback, empty-root fallback, namespace/comment/CDATA/entity/resource
boundaries, catalog `/Metadata` duplicate/direct/unresolved/non-stream reviews,
stream-object tail rejection, duplicate `/Type` or `/Subtype` metadata stream
dictionaries, fake `/Filter` names inside dictionary strings, indirect
`/Filter` operand review, stream-filter dictionary boundary review, CMap
malformed filter operand review, xref repair, attachment filters, image
filters, OutputIntent metadata, annotations, forms, page geometry, supplied
table/equation handoffs, or any OCR/model work.

The new boundary is specifically document XMP promotion through a metadata
stream `/DecodeParms` helper object whose body contains extra top-level
operands after the selected dictionary/null operand.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object
scanner, catalog `/Metadata` resolver, stream dictionary parser, existing
stream decoder, XMP parser, metadata review output, and WordPress smoke path.
Remaining GPU/model/OCR/table/equation inference gaps stay outside the current
no-GPU markerPDF scope.
