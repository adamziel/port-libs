# markerPDF inline image tokenizer Pattern tint current-base rework

Lane: `markerpdf`

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260606T082751Z`

Base accepted HEAD: `e1d03b8cc26cd725291bff3cc15a9b256bfbd961`

## Source Truth

Upstream `sddai/markerPDF` at the manifest commit routes searchable PDF text through parser-backed extraction before image/OCR/model stages. Under the current no-GPU markerPDF scope, inline image bytes remain image payload, and visible content after a valid `EI` boundary must resume before WordPress paragraph import without executing PDFium, PIL, OCR, Surya, Texify, Torch, or external PDF tools.

This slice fixes a current-base red tokenizer boundary where a preview-only inline image reaches its sample-floor `EI`, then visible content applies an uncolored Pattern color space with numeric tint operands plus a pattern name:

```text
/CSPattern cs
0.5 0.25 0.75 /P1 scn
BT ... (Visible Pattern Tint Import) Tj ET
EI
```

Before the fix, the fallback segment validator rejected `/P1` before `scn`, so the visible text stayed image-owned until the later stray `EI`.

## Implementation

`PdfTextExtractor::contentSegmentIsLineSeparatedClosedTextObject()` now lets a name token after existing numeric operands queue as a possible graphics color operand. The existing `SCN`/`scn` validator still owns the final decision, so unrelated name/numeric sequences fail closed unless a bounded color operator consumes them.

The focused test now also asserts that `/CSPattern` operator text is excluded from imported paragraphs. A new WordPress smoke covers only this Pattern tint boundary and emits explicit no-Python/no-external-tool flags.

## Evidence

Red-first focused run before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
1 test files, 463 assertions, 1 failures
FAIL closes sample-floor preview fallback before uncolored pattern tint operands followed by stray EI operator
```

After the source/test/example patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
1 test files, 473 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-pattern-tint-tokenizer-currentbase.php
before_text_preserved=true
pattern_tint_text_preserved=true
after_text_preserved=true
inline_image_payload_excluded=true
pattern_color_operands_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI`, tight `ID`/`EI`, comments after `ID`, NUL whitespace, slash delimiters, DCT/JPX/JBIG2/CCITT framing, unsupported filters, named ColorSpace fallback, marked-content ActualText, post-terminator comments, later stray `EI` text, graphics-state wrappers, clipping/XObject/marked-content operators, numeric color-state operators, normal Pattern color state, shading, dash patterns, text-state operators, compatibility sections, external `Q`/`EMC`/`EX` closures, stream filters, image review metadata, xref repair, annotations, forms, tables, equations, OCR, or model execution.

The bounded behavior is only preview-only inline-image sample-floor fallback before uncolored Pattern tint operands and visible text followed by a later stray `EI`.

## Dependency Closure

No new support component is needed. This reuses the native PHP content tokenizer, inline-image fallback scanner, preview-only image filter handling, text extraction, graphics color-operator validation, and WordPress smoke path. Full raster/OCR/model parity remains out of scope under the current no-GPU markerPDF directive.
