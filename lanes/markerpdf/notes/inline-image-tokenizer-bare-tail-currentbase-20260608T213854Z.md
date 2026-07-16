# Inline Image Tokenizer Bare Tail Boundary Current Base

Slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260608T213854Z`
Base accepted HEAD: `ba1acddf7dda63f41a17e1f25945a52ff91962c3`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to `pdftext.extraction.dictionary_output` before image/OCR/model fallback, and image rendering later targets RGB through PDFium/PIL. In this no-GPU native PHP slice, the matching boundary is token ownership: bytes between `BI`, `ID`, and the selected `EI` stay inline image payload, while following searchable text remains importable without raster execution.

Reference checked:

- `sddai/markerPDF` `marker/pdf/extract_text.py` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`
- `sddai/markerPDF` `marker/pdf/images.py` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`

## Behavior

Before this patch, an inline image dictionary with real image keys followed by a bare-word malformed tail operand could reopen parsing on a fake `EI` inside the payload:

```text
BI /W 1 /H 1 BadTail /CS /G /BPC 8 /D [1 0] /F /MalformedPreview ID
<image bytes> EI BT ... (Bare Tail Inline Noise) ... ET
EI
BT ... (After Bare Tail Inline) ... ET
```

The tokenizer now treats bare-word tail operands as malformed dictionary operands after image keys have been seen, then keeps the payload closed until the later valid `ID ... EI` boundary. The image review planner reports the same malformed dictionary state, sets `native_raster_decode=false`, and keeps output preview fail-closed.

## Red Probe

Initial focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBareTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps bare-word inline image dictionary tails closed before WordPress text import
Expected: Before Bare Tail Inline, After Bare Tail Inline
Actual:   Before Bare Tail Inline, Bare Tail Inline Noise, After Bare Tail Inline
1 test files, 1 assertions, 1 failures
```

## Verification

Focused regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBareTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps bare-word inline image dictionary tails closed before WordPress text import
1 test files, 18 assertions, 0 failures
```

Adjacent tokenizer-tail family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBareTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerNameTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerDotNumericTailCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
4 test files, 824 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-bare-tail-currentbase.php
```

The smoke exits 0 and emits `visible_text_imported=true`, `bare_tail_payload_excluded=true`, `bare_tail_dictionary_operand_review_only=true`, `bare_tail_preview_failed_closed=true`, `native_raster_decode=false`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

## Non-Overlap

This does not repeat accepted malformed `BI` recovery, tight `ID`/`EI` sample floors, ID comments, NUL/vertical-tab separators, compact dictionaries, nested dictionary/text-object decoys, DCT/JPX/JBIG2/CCITT/unsupported-filter payload closure, slash-delimited `EI`, direct/named/indirect marked-content ActualText/property boundaries, TJ/quote fallback, post-terminator comments, q/Q/cm/clipping/path/color/dash/text-state/shading/operator boundaries, Type3 metric fallbacks, image-mask dictionary tails, dot-leading numeric tails, name-valued tails, Decode/DecodeParms malformed operands, Image XObject decode readiness, CMap/font behavior, OCR/model behavior, or raster image decoding. The bounded behavior is only bare-word malformed tail operands after image keys inside an inline image dictionary.

## Dependency Closure

No new support component is needed. This reuses the native PHP content tokenizer, inline image dictionary parser, malformed image filter review boundary, image review planner, text extractor, and WordPress smoke harness. Live OCR, Surya/Texify/Torch model execution, PDFium/PIL raster parity, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.
