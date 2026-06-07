# markerPDF malformed CMap row-tail filter boundary

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260607T061603Z`

Base accepted HEAD: `0b156309dc95b4072c2ccb7cc4b489a6967b1646`

## Behavior

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through PDF text/font extraction before Markdown assembly. Under the current no-GPU lane scope, the native PHP parser owns CMap stream decoding and row-boundary parsing before WordPress paragraph import.

This slice tightens decoded filtered ToUnicode CMap row parsing when a row has a valid-looking prefix followed by an extra top-level nested array operand:

- `begincodespacerange` rows such as `<0000> <FFFF> [<DECO>]` are now treated as malformed row-local input, so the broad range is not admitted and cannot widen later `beginbfrange` replacement.
- `beginbfrange` rows such as `<0042> <0042> <...> [<DECO>]` are now ignored as malformed row-local input, so their target text cannot replace fallback source bytes.

Before the fix, the bulk hex scanner ignored the array tail and stitched the leading hex operands into valid rows, producing `Codespace Tail CMap Leakodespace Tail Safe Import` and `Bfrange Tail CMap Leakfrange Tail Safe Import`. After the fix, line-local row parsing wins whenever malformed rows are present and only well-formed row-local entries are used.

## Verification

Red-first focused run before source edit:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapRowTailFilterBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 2 assertions, 2 failures
```

Focused after fix:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapRowTailFilterBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 76 assertions, 0 failures
```

Adjacent CMap/filter family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMap*FilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapRowTailFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterEodBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
```

Result:

```text
19 test files, 2979 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-row-tail-currentbase.php
```

Result: exits `0`, emits two WordPress paragraph blocks with `Codespace Tail Safe Import` and `Bfrange Tail Safe Import`, and reports `decoded_cmap_count=2`, `safe_text_preserved=true`, `payload_excluded=true`, `codespace_tail_rejected=true`, `bfrange_tail_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed CMap `/Filter` operands, indirect/stale filter owner selection, malformed DecodeParms parameter rejection, null-filter DecodeParms alignment, all-null filter stacks, identity/private Crypt filter policy, unsupported/escaped filter names, explicit filter EOD enforcement, post-`endcmap` cleanup, complete second-program exclusion, literal-string CMapName/usecmap decoys, overdeclared literal-string bfchar rows, nested target arrays, nested full bfrange rows, malformed singleton codespace rows, nested codespace arrays, scalar null filter tails, Form/Image XObject boundaries, xref repair, metadata, annotations, forms, or model/OCR behavior.

The bounded behavior is specifically extra top-level nested array operands at the end of decoded filtered CMap `begincodespacerange` and `beginbfrange` rows before ToUnicode replacement and WordPress paragraph extraction.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream filter decoder, CMap stream decoder/parser, ToUnicode mapping path, and WordPress smoke renderer. Full upstream OCR/model/PDFium parity remains intentionally out of scope for this markerPDF lane and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, Texify, tabled-pdf, model downloads, Streamlit/FastAPI workers, and external OCR/rendering helpers.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
