# markerPDF Image XObject Resource Object Boundary

Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260605T181637Z`
Session: `port-dev-markerpdf-image-xobject-20260605T181637Z`
Base accepted HEAD: `6d9792dc673287d70476df95565b8d89e9e39a48`

## Source Truth

Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` separates searchable PDF text extraction from image rendering/review. The native no-GPU PHP port maps that boundary by keeping page text and Image XObject review metadata separate: page `/Resources /XObject` entries may identify review-only raster resources, but malformed resource objects must not promote private or trailing tokens into callable image resources.

## Behavior

`PdfTextExtractor::resourceCategoryDictionaryBody()` now accepts an indirect resource-category dictionary object only when the resolved object body contains exactly one dictionary token plus PDF whitespace/comments. This means:

- `/Resources << /XObject 20 0 R >>` with `20 0 obj << /BadImage 5 0 R >> /PrivateTail 99 0 R endobj` fails closed and does not count `/BadImage` as an image resource;
- `20 0 obj << /CommentImage 6 0 R >> % comment-only tail endobj` remains valid;
- raster payload bytes remain excluded from WordPress paragraphs and from review JSON.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed on malformed indirect XObject resource dictionary object tails
Expected: 1
Actual: 2
1 test files, 963 assertions, 1 failures
```

The old parser counted both the malformed tail object image and the valid comment-only sibling image.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on malformed indirect XObject resource dictionary object tails
1 test files, 982 assertions, 0 failures
```

Adjacent resource/image regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceCategoryStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEntryGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEntryStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceImageXObjectInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 1102 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-resource-object-boundary-currentbase.php
```

The smoke exits 0 and emits `malformed_resource_object_tail_rejected=true`, `comment_only_resource_object_tail_accepted=true`, `image_xobject_count=1`, `invoked_image_xobject_count=1`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, CTM placement, Form XObject resource inheritance, optional content, OCMD, artifact suppression, clipping, page box clipping, rotation/UserUnit display geometry, exact generation, SMask/Mask metadata, ColorKey masks, named ColorSpace resources, ExtGState transparency, JPX `SMaskInData`, DCT/CCITT/JPX/JBIG2 filter review, inline-image tokenizer boundaries, top-level nested private resource exclusion, stream category rejection, malformed page `/Resources`, PageLabels object-tail boundaries, or parser stream-filter slices. The bounded behavior is only indirect resource-category dictionary object tails for `/XObject` image review.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, resource dictionary/category parser, content tokenizer, Image XObject review rows, stream decoders, and WordPress smoke renderer. Full live OCR/model/raster parity remains intentionally out of scope under the no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers.
