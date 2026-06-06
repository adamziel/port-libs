# markerPDF inline image tokenizer Pattern tint sample-floor boundary

## Scope

Lane: `markerpdf`

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260606T071006Z`

Accepted base: `2c3874187ed49e9686f363014a3a498e09dbcd73`

Upstream source truth: pinned `sddai/markerPDF` routes searchable-PDF text through parser-backed extraction in `marker/pdf/extract_text.py`, so page content-stream inline image payloads must be excluded before visible text is handed to WordPress import.

This no-GPU native PHP slice narrows the inline image tokenizer boundary where:

- a preview-only inline image reaches its sample-floor `EI`;
- the following visible content applies an uncolored Pattern color space through numeric tint components plus a pattern name operand (`/CSPattern cs 0.5 0.25 0.75 /P1 scn`);
- a later stray bare `EI` operator appears after the text object.

Before this patch, a local red probe for that sample-floor sequence extracted only `Before` and `After`, because the fallback segment validator rejected the `/P1` name operand before `scn` and kept the visible text inside the inline image payload scan.

## Implementation

`PdfTextExtractor::contentSegmentIsLineSeparatedClosedTextObject()` now queues a name operand after existing numeric operands as a possible graphics color operand. The existing `SCN` / `scn` validator still decides whether the full operand stack is valid, so unrelated numeric/name sequences fail closed when their later operator is not a bounded color operator.

The focused current-base test adds `closes sample-floor preview fallback before uncolored pattern tint operands followed by stray EI operator`, covering text extraction, text runs, `naiveGetText`, page labels, outline metadata, payload exclusion, and operand-text exclusion.

The WordPress smoke now emits:

- `preview_only_pattern_tint_sample_floor_text_preserved_after_safe_boundary=true`
- `executes_python_or_models=false`
- `executes_external_pdf_tools=false`

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
...
PASS closes sample-floor preview fallback before uncolored pattern tint operands followed by stray EI operator
...
1 test files, 472 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
preview_only_pattern_tint_sample_floor_text_preserved_after_safe_boundary=true
```

The root harness was not run; this is an isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` recovery, tight `ID` / `EI`, comments after `ID`, NUL whitespace, DCT/JPX/JBIG2/CCITT preview-only framing, unsupported filters, named ColorSpace fallback, visible `EI` literal/TJ fallback, marked-content ActualText, later stray `EI` text, same-line text, graphics-state wrappers, path/clipping/XObject/marked-content point operators, numeric color state, pattern color after a normal inline-image terminator, shading, dash patterns, text-state operators, compatibility sections, externally closed `Q` / `EMC` / `EX` scopes, image review metadata, stream filters, xref repair, page geometry, annotations, forms, table/equation handoffs, or OCR/model behavior.

The bounded behavior is only the sample-floor preview-only inline image fallback before uncolored Pattern tint operands and visible text followed by a later stray `EI`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF content tokenizer, inline-image fallback scanner, preview-only image filter handling, text extraction, color-operator validation, and WordPress smoke renderer. Full raster/OCR/model parity remains gated on PDFium/pypdfium/PIL or future native raster backends, Surya/Texify/Torch, and live model workers, which remain intentionally out of scope under the current markerPDF no-GPU directive.
