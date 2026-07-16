# markerPDF Inline Image Decode Tail DecodeParms Boundary

- Lane: `markerpdf`
- Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260608T065114Z`
- Base accepted HEAD: `c73ab3af9ca883f50ffd6b3d1d33ae6c6162db8c`
- Scope: native no-GPU searchable-PDF parser and image/filter review metadata only. No OCR, model execution, PDFium/PIL raster execution, Python workers, external PDF tools, or PDF action execution.

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable text through `marker/pdf/extract_text.py` with `pdftext.extraction.dictionary_output()` and page text extraction, while image insertion/rendering is handled later through `marker/images/extract.py`. The no-GPU PHP boundary mirrors that separation: inline `BI ... ID ... EI` image bytes are not paragraph text, and malformed image decode metadata is review-only instead of native RGB preview input.

## Behavior

PDF inline image dictionaries may use abbreviated keys:

```pdf
/F /Fl /D [1 0] 99 0 R /DP << /Predictor 1 >>
```

The existing renderer correctly rejected the malformed `/D` tail operand, but canonical inline dictionary parsing stopped at the stray `99 0 R` token before it could see the later `/DP` dictionary. That dropped valid filter DecodeParms metadata from WordPress review rows.

This slice makes the inline dictionary canonicalizer skip one malformed `/Decode` tail operand and continue parsing later keys. `/Decode` remains invalid and review-only, but the following `/DecodeParms` dictionary stays attached to the `FlateDecode` filter. The content tokenizer still excludes the image payload and fake `EI` bytes from visible WordPress paragraphs.

## Files

- `lanes/markerpdf/src/PdfImageRenderer.php`
  - Adds `inlineImageDecodeExtraOperandEndAfterValue()`.
  - Uses it only while canonicalizing inline `/Decode`, so later `/DecodeParms` keys survive without treating the malformed `/Decode` as valid.
- `lanes/markerpdf/tests/PdfInlineImageDecodeTailDecodeParmsCurrentBaseTest.php`
  - Adds the focused current-base regression.
- `lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-tail-decodeparms-currentbase.php`
  - Emits WordPress paragraph output plus review metadata proving `decodeparms_preserved_after_decode_tail=true`, preview fail-closed behavior, and payload exclusion.

## Verification

Focused new test:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeTailDecodeParmsCurrentBaseTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS preserves inline DecodeParms after malformed Decode tail operands before WordPress text extraction

1 test files, 25 assertions, 0 failures
```

Adjacent inline decode family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeTailDecodeParmsCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeArrayOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterTailDecodeParmsCurrentBaseTest.php
```

Result:

```text
4 test files, 1079 assertions, 0 failures
```

Example smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-tail-decodeparms-currentbase.php
```

Result:

```text
exits 0; metadata includes inline_decode_tail_rejected=true, decodeparms_preserved_after_decode_tail=true, image_decode_source=invalid, preview_failed_closed=true, native_raster_decode=false, inline_payload_excluded_from_text=true, and all Python/model/PDFium/PIL/external-tool flags false.
```

## Non-Overlap

This does not repeat accepted inline image payload exclusion, supported-filter decoded sample-floor recovery, ASCIIHex/ASCII85/RunLength/LZW/Flate EOD surplus handling, null filter DecodeParms alignment, duplicate/extra DecodeParms declarations, malformed filter operands, indirect `/Decode` operands, duplicate `/Decode` operands, trailing `/Decode` operand review-only metadata without a later `/DecodeParms` dictionary, or DCTDecode filter-tail DecodeParms preservation. The new behavior is specifically malformed inline `/Decode` tail operands followed by a valid `/DecodeParms` dictionary.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF value parser, inline dictionary canonicalizer, stream-filter/DecodeParms metadata resolver, content tokenizer, focused PHP harness, and WordPress smoke path. Full OCR, Surya/Texify/Torch, pdftext/PDFium/pypdfium/PIL raster parity, model workers, and upstream benchmark model parity remain intentionally out of scope under the current markerPDF no-GPU directive.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
