# Inline Image Tokenizer Structured Tail Boundary Current Base

Slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260608T222401Z`
Base accepted HEAD: `1a91e11e37bf1452c01f3630ee84977c3a03b00f`

## Source Truth

Pinned upstream markerPDF keeps searchable-PDF text extraction in the native PDF text path before image/OCR/model fallback. In this no-GPU PHP lane, the equivalent boundary is PDF content-token ownership: bytes between `BI`, `ID`, and the selected `EI` stay inline-image payload, while later searchable text remains importable.

Reference checked from the lane manifest:

- `sddai/markerPDF` `marker/pdf/extract_text.py` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`
- `sddai/markerPDF` `marker/pdf/images.py` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`

## Behavior

Previous current-base slices covered bare-word, name-valued, and dot-leading numeric malformed inline-image dictionary tails. This slice covers structured tail operands after valid image keys, including literal strings, hex strings, direct dictionaries, and arrays:

```text
BI /W 1 /H 1 /CS /G /BPC 8 /D [1 0] [9 9] /F /MalformedPreview ID<image bytes>
<image bytes> EI BT ... (Array Tail Inline Noise) ... ET
EI
BT ... (After Array Tail Inline) ... ET
```

Before the fix, the malformed-tail scanner passed an empty dictionary into the `ID` boundary check while recovering after a malformed operand. That missed tight `ID<data>` starts that are otherwise allowed by the already-read sample-boundary dictionary, so an array tail after `/D [1 0]` could reopen visible text on a fake payload `EI`.

`PdfTextExtractor::inlineImageMalformedDictionaryTailBoundaryOffset()` now carries the already-read dictionary into `inlineImageDataBoundaryOffset()`. Structured malformed tails therefore stay review-only, tight `ID<data>` starts are recognized from the current image dictionary, payload text stays excluded, and the later real `EI` boundary preserves following searchable text.

## Red Probe

Initial focused run after adding the structured-tail coverage:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerStructuredTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps literal-string inline image dictionary tails closed before WordPress text import
PASS keeps hex-string inline image dictionary tails closed across ID comments
PASS keeps direct-dictionary inline image dictionary tails closed before later text objects
FAIL keeps array inline image dictionary tails closed before tight EI payload candidates
Actual: Before Array Tail Inline, Array Tail Inline Noise, After Array Tail Inline
1 test files, 56 assertions, 1 failures
```

## Verification

Focused regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerStructuredTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps literal-string inline image dictionary tails closed before WordPress text import
PASS keeps hex-string inline image dictionary tails closed across ID comments
PASS keeps direct-dictionary inline image dictionary tails closed before later text objects
PASS keeps array inline image dictionary tails closed before tight EI payload candidates
1 test files, 73 assertions, 0 failures
```

Adjacent tokenizer-tail family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerStructuredTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBareTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerNameTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerDotNumericTailCurrentBaseTest.php
4 test files, 138 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-structured-tail-currentbase.php
```

The smoke exits 0 and emits `structured_tail_payloads_excluded=true`, `structured_tail_dictionary_operand_review_only=true`, `structured_tail_preview_failed_closed=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` recovery, tight `ID`/`EI` sample floors for well-formed dictionaries, ID comments, NUL/vertical-tab separators, compact dictionaries, nested dictionary/text-object decoys, DCT/JPX/JBIG2/CCITT/unsupported-filter payload closure, slash-delimited `EI`, direct/named/indirect marked-content ActualText/property boundaries, TJ/quote fallback, post-terminator comments, q/Q/cm/clipping/path/color/dash/text-state/shading/operator boundaries, Type3 metric fallbacks, image-mask dictionary tails, bare-word tails, name-valued tails, dot-leading numeric tails, Decode/DecodeParms malformed operands, Image XObject decode readiness, CMap/font behavior, OCR/model behavior, or raster image decoding. The bounded behavior is only structured malformed tail operands after image keys inside an inline image dictionary, with a red tight-`ID` array-tail case.

## Dependency Closure

No new support component is needed. This reuses the native PHP content tokenizer, inline image dictionary parser, malformed image filter review boundary, image review planner, text extractor, and WordPress smoke harness. Live OCR, Surya/Texify/Torch model execution, PDFium/PIL raster parity, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.
