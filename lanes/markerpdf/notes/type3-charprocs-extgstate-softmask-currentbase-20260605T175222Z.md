# Type3 CharProcs ExtGState Soft-Mask Boundary

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T175222Z`
Accepted base: `f0c994757ade1bf76847121ddfe3ea0faea2f48b`

## Source Truth

The markerPDF no-GPU scope for this lane is native searchable-PDF parsing and conversion. Type3 CharProc streams and their private resources are glyph programs, not document paragraphs. ExtGState soft-mask transparency groups and transfer functions reached only from a Type3 CharProc resource dictionary are glyph-private drawing resources and should not be promoted by stream-only fallback text extraction.

## Red-First Boundary

The focused fixture has no page tree, so it exercises the stream-only fallback path directly. Before the patch, the visible fallback stream was returned with text leaked from a Type3-private ExtGState soft-mask `/G` Form XObject, a nested Form XObject used by that group, and a `/TR` transfer-function stream.

Red-first result before source changes:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsExtGStateSoftMaskBoundaryCurrentBaseTest.php
1 test files, 1 assertions, 1 failures
Actual lines included Type3 ExtGState soft mask group text leak, nested Type3 soft mask form text leak, and Type3 soft mask transfer text leak.
```

## Implementation

`PdfTextExtractor` now follows Type3-private `/Resources /ExtGState` soft-mask references while collecting private stream generations for fallback exclusion. It marks `/SMask /G` group streams and `/SMask /TR` transfer streams as private, and recursively walks Form soft-mask groups through the existing XObject, Pattern, and ExtGState resource traversal.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsExtGStateSoftMaskBoundaryCurrentBaseTest.php
1 test files, 10 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3*Test.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php
39 test files, 345 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-extgstate-softmask-currentbase.php
Emits fallback_content_preserved=true, charproc_payload_visible_text_excluded=true, soft_mask_group_payload_excluded=true, nested_soft_mask_form_payload_excluded=true, soft_mask_transfer_payload_excluded=true, extgstate_resource_names_excluded=true, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The patch reuses the native PDF object parser, resource dictionary resolver, exact generation lookup, and stream-only fallback exclusion machinery. No GPU/model/OCR, Python pdftext, PDFium, PIL, or external PDF tool execution is required.
