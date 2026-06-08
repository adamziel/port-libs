# markerpdf page resource inline image ColorSpace current-base

Slice: `markerpdf-page-resource-inheritance-current-base-20260608T151517Z`

Base accepted HEAD: `9b7dedf8f156ee7a192d9054f47ee79347ca34c8`

## Behavior

Pinned upstream markerPDF source remains `sddai/markerPDF@da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`; searchable PDF text is extracted from native parser/PDFium/pdftext layers before OCR or model handoffs. Under the no-GPU PHP boundary, page-tree `/Resources` inheritance also applies to inline image dictionaries that name a `/ColorSpace` resource.

This patch carries the effective page resource dictionary's `/ColorSpace` entries into the content tokenizer's inline-image boundary checks. A page whose `/Contents` contains `BI ... /CS /InheritedRGB ... ID` now uses the inherited `/Pages /Resources /ColorSpace << /InheritedRGB /DeviceRGB >>` entry to derive the decoded sample floor before accepting delimiter-looking `EI` bytes. The resource rewrite is internal to tokenizer boundary math and does not promote raster bytes to visible text or change stream-fallback behavior.

Before this patch, the tokenizer treated `/InheritedRGB` as an unresolved color space, closed the inline image at the early payload `EI`, and emitted `Inherited Inline ColorSpace Payload Noise` as imported WordPress text.

## Evidence

Red-first focused test after adding the fixture and before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInlineImageColorSpaceInheritanceCurrentBaseTest.php
```

Result: `1 test files, 1 assertions, 1 failures`; the inline image payload text appeared between the two visible page paragraphs.

Focused test after implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInlineImageColorSpaceInheritanceCurrentBaseTest.php
```

Result: `1 test files, 8 assertions, 0 failures`.

Adjacent page-resource and inline-image tokenizer/decode sweep:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
```

Result: `3 test files, 2032 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-page-resource-inline-image-colorspace-currentbase.php
```

Result: exit 0, with `inline_image_payload_excluded_from_text=true`, `inherited_color_space_resource=InheritedRGB`, `visible_paragraph_count=2`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and diff checks:

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfPageResourceInlineImageColorSpaceInheritanceCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-resource-inline-image-colorspace-currentbase.php
git diff --check -- lanes/markerpdf
```

Result: PHP lint passed for all changed PHP files, and `git diff --check -- lanes/markerpdf` passed.

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. This reuses the native PHP page-tree resource resolver, resource-category dictionary parser, content tokenizer, inline-image sample-floor checks, text extraction path, and WordPress smoke renderer. OCR/model execution, PDFium rendering parity, GPU model runs, decryption/password validation, JavaScript/action execution, and external PDF tools remain intentionally out of scope for this markerPDF lane.

## Non-overlap

This does not repeat accepted font/CMap resource inheritance, Form XObject resource inheritance, annotation appearance resources, image XObject resource review/provenance, ProcSet inheritance, duplicate/malformed resource entry handling, object-stream resources, optional-content wrappers, direct inline image color-space arrays, or general inline image payload exclusion. The bounded behavior is only inherited page `/ColorSpace` resource resolution for inline image tokenizer boundaries before WordPress text import.
