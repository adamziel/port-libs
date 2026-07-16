# markerpdf malformed CMap duplicate Length filter boundary current base

## Source Truth

- Upstream markerPDF at pinned `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` reaches searchable PDF text through `marker/pdf/extract_text.py` and the pdftext/PDFium font decoding boundary.
- In the no-GPU PHP lane, filtered ToUnicode CMap streams are native PDF stream objects. Duplicate top-level stream dictionary keys are malformed owner evidence for parser-critical operands; the CMap decoder already failed closed for duplicate `/Filter` and duplicate `/DecodeParms`.
- This slice extends the same fail-closed policy to duplicate top-level `/Length` declarations, including escaped duplicate keys such as `/L#65ngth`, before any decoded CMap mapping can replace fallback WordPress-visible text.

## Implementation

- `PdfTextExtractor::decodeCMapStream()` now rejects CMap stream dictionaries with duplicate decoded `/Length` declarations before calling the generic stream decoder.
- `extractCMapStreamFilterLengthOwnerReview()` now reports `duplicate_length_declaration_count`, keeps `declared_length=null` when the length owner is ambiguous, and adds `length_operand_policy=reject_duplicate_length_declarations` for the stream entry.
- The filter stack can still be reviewed as syntactically supported (`filters=["FlateDecode"]`, `filter_decoders_resolved`); the CMap payload is not decoded because the length owner is malformed.

## Evidence

Red-first current-base probe before the source edit:

```text
extractPlainText => Duplicate Length CMap Leakuplicate Length Safe
decoded_cmap_count => 1
```

Focused test after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapDuplicateLengthFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects duplicate direct CMap Length declarations before filtered ToUnicode decoding
PASS rejects duplicate escaped CMap Length declarations before filtered ToUnicode decoding

1 test files, 102 assertions, 0 failures
```

Adjacent CMap filter/length-owner family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapDuplicateLengthFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapDuplicateDecodeParmsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapLengthOperandFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
5 test files, 1803 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-duplicate-length-currentbase.php --self-test
self_test_passed=true
duplicate_length_declarations_rejected=true
```

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF stream dictionary parser, name escape decoding, stream filter resolver, CMap decoder/review path, and WordPress paragraph smoke path. No Python, pdftext, pypdfium/PDFium, Poppler, Ghostscript, OCR, model, GPU, multiprocessing, or live-service execution was used.

## Non-Overlap

This does not repeat accepted malformed CMap work for scalar or array `/Filter` operands, post-`/Length` extra operands, duplicate `/Filter` or `/DecodeParms`, escaped filter names, stale/free/indirect filter owners, unsupported/Crypt filters, DecodeParms fail-closed behavior, explicit CMap filter EOD enforcement, post-`endcmap` exclusion, literal/array/procedure operator decoys, missing/underdeclared row counts, malformed `bfchar`/`bfrange` targets, same-width codespace rejection, UseCMap inheritance, Type0 Encoding CID row parsing, xref repair, image/filter metadata, annotations/forms/security, OCR/model handoffs, or supplied-boundary table/equation work.

The bounded behavior is only duplicate top-level `/Length` declarations on otherwise decodable filtered ToUnicode CMap streams before native CMap mapping and WordPress text import.
