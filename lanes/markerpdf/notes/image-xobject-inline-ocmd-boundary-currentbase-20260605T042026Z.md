# markerPDF Image XObject Inline OCMD Boundary

Session: `port-dev-markerpdf-image-xobject-20260605T042026Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260605T042026Z`
Base accepted HEAD: `a4eb702f7ee7d99c8c98d4d754371b79ebaa9e9b`

## Source Truth

Upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` separates searchable page text extraction from image rendering/review. The native no-GPU PHP boundary keeps image XObject bytes out of WordPress text while preserving visible painted image resources as review metadata.

Relevant upstream source-truth files remain:

- `marker/pdf/extract_text.py`
- `marker/pdf/images.py`
- `marker/images/extract.py`

PDF optional content marked-content sequences can pass a property dictionary directly to `BDC`, including `/Type /OCMD` membership dictionaries. Those dictionaries gate whether the marked content is visible before image `Do` operators are counted as painted invocations.

## Behavior

`PdfTextExtractor::filterOptionalContentMarkedBlocks()` now evaluates inline optional-content property dictionaries and indirect property references while preserving the existing named `/Properties` behavior. Image XObject invocation review applies that filter even when a page has no named `/Properties` dictionary, so direct inline OCMD blocks are considered before `contentXObjectInvocationDetails()` counts image `Do` operators.

The focused probe uses:

```pdf
/OC << /Type /OCMD /OCGs [20 0 R 21 0 R] /P /AllOn >> BDC
  q 16 0 0 8 72 690 cm /Inline#20Hidden Do Q
EMC
```

Catalog `/OCProperties` marks object `20 0 R` on and `21 0 R` off, so `/P /AllOn` makes that marked block hidden. The hidden image remains review metadata with its decoded hash, but `invoked=false` and `invocation_count=0`. A visible inline OCMD block and a plain image remain counted with placement bboxes.

## Red-First Evidence

Before the fix:

```text
FAIL honors inline OCMD dictionaries before counting image XObject invocations
Expected: 2
Actual: 3
1 test files, 378 assertions, 1 failures
```

The hidden inline OCMD image invocation was counted as painted.

After the fix:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 406 assertions, 0 failures
```

The new PASS case is:

```text
PASS honors inline OCMD dictionaries before counting image XObject invocations
```

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-inline-ocmd-currentbase.php
```

The smoke emits `inline_ocmd_hidden_invocation_excluded=true`, `inline_ocmd_hidden_metadata_retained=true`, `inline_ocmd_visible_invocation_counted=true`, `plain_invocation_counted=true`, all payload-excluded flags true, and all Python/model/external-tool execution flags false.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, object-level `/OC`, named `/Properties` optional-content visibility, CTM placement, clipping, exact-generation SMask/Mask/metadata/Alternates, ColorKey, DCT/CCITT/JPX/JBIG2 boundaries, inline-image filters, top-level XObject resource dictionary parsing, or Form-resource image review behavior. The bounded behavior is specifically inline `BDC` optional-content membership dictionaries gating page/Form/appearance marked-content before image invocation counting and text expansion.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP content tokenizer, dictionary parser, optional-content visibility evaluator, stream decoder, image XObject review path, and WordPress smoke renderer. It does not execute Python, models, PDFium, PIL, Poppler, Ghostscript, live OCR, or external PDF tools.
