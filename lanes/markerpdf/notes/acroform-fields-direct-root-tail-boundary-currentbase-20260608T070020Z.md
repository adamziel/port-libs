# markerPDF AcroForm direct root tail boundary

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260608T070020Z`

## Source truth

Upstream `sddai/markerPDF` remains pinned in the manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Under the current no-GPU markerPDF scope, searchable-PDF form import is native parser behavior before any pdftext/PDFium/model handoff.

PDF dictionary source truth for this slice: a direct catalog `/AcroForm` value is one complete PDF object. If a stray top-level scalar/reference appears before the next catalog key, the native parser must fail closed instead of reviewing field values from the first dictionary and silently ignoring the malformed tail.

## Behavior

- Direct catalog `/AcroForm << ... >> 50 0 R` now returns no form fields and ignores `/NeedAppearances` from the malformed AcroForm root.
- Direct catalog `/AcroForm << ... >> % comment\n/Lang ...` remains valid, so comment-only tails and following catalog keys still preserve form field review metadata.
- Field values, alternate names, mapping names, and decoy trailing-root objects remain out of visible WordPress text.

Red-first probe on accepted base `ffcd9253ba667545698caf23a94d2a208517e323` showed the malformed direct-root-tail fixture promoted `["direct.root.tail"]` before the source change.

## Verification

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectRootTailBoundaryCurrentBaseTest.php
```

Result: `1 test files, 33 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectRootTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsRootDictionaryTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsArrayObjectTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsReferenceObjectTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
```

Result: `5 test files, 717 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-direct-root-tail-currentbase.php
```

Result: smoke exits 0 and emits `malformed_direct_root_tail_rejected=true`, `valid_direct_root_field_preserved=true`, `field_values_review_only=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted indirect AcroForm root dictionary tails, indirect `/Fields`/`/Kids` array-object tails, indirect reference-object tails, root stream boundaries, trailer-root boundaries, generation-exact field references, page-widget repair, direct-widget canonicalization, duplicate-key boundaries, or action/value review. The bounded behavior is only the direct catalog `/AcroForm` dictionary value tail before field discovery.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF object parser, top-level dictionary scanner, AcroForm field extractor, page-widget mapper, and WordPress smoke path. Full upstream runner parity remains intentionally out of scope for this no-GPU slice because live pdftext/PDFium/Python, Surya/OCR, Texify, Torch/model execution, and external PDF tools are not run.
