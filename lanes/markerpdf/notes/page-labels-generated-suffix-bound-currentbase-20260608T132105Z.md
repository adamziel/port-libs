# markerPDF PageLabels generated suffix bound current-base

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260608T132105Z`

## Source Truth

- Upstream markerPDF extracts searchable PDF text page-by-page before OCR/layout/model work; native PHP keeps `/PageLabels` as page-break and preview metadata aligned to physical pages, not visible body text. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF PageLabels are catalog number-tree entries keyed by zero-based page indices; `/S`, `/P`, and `/St` describe each label range. PDFium PageLabel tests and pypdf's page-label helper both model Roman, alphabetic, decimal, prefix, and start-number formatting from those dictionary keys. Sources: https://pdfium.googlesource.com/pdfium.git/+/refs/heads/chromium/7430/core/fpdfdoc/cpdf_pagelabel_unittest.cpp and https://sources.debian.org/src/pypdf/5.4.0-1/pypdf/_page_labels.py/
- This slice stays in the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python models, raster rendering, or external PDF tools were run.

## Implementation

- `PdfTextExtractor` now bounds generated Roman and alphabetic PageLabels suffixes at 4096 bytes before appending them to WordPress page-break labels. Decimal labels remain unchanged because they are naturally bounded by PHP integer parsing.
- `MarkerAppPreview` mirrors the same formatter guard, keeping `pageLabels()`, `openPdfSummary()`, and `getPageImagePlan()` aligned with text extraction.
- When a valid in-range `/St` would generate an oversized Roman or alphabetic suffix, the parser falls back to the decimal start number as the suffix. This preserves the page ordinal while avoiding huge metadata strings.
- Added focused coverage with `/S /R /St 5000000`, `/S /A /St 120000`, and `/S /D /St 5000000`; the first two now produce `Roman-5000000` and `Alpha-120000`, while decimal remains `Decimal-5000000`.

## Evidence

Red-first focused run before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsGeneratedSuffixBoundCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL bounds generated PageLabels roman and alphabetic suffixes before WordPress page metadata
Expected: ["Roman-5000000","Alpha-120000","Decimal-5000000"]
Actual: ["Roman-MMMMM...","Alpha-JJJJJ...","Decimal-5000000"]
1 test files, 1 assertions, 1 failures
```

Focused regression after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsGeneratedSuffixBoundCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS bounds generated PageLabels roman and alphabetic suffixes before WordPress page metadata
1 test files, 9 assertions, 0 failures
```

Adjacent PageLabels and preview family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfPageLabels.*CurrentBaseTest\.php$' | sort) lanes/markerpdf/tests/MarkerAppPreviewTest.php
Focused test run: 42 selected test files (root lock skipped)
42 test files, 862 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-generated-suffix-bound-currentbase.php > /tmp/markerpdf-page-labels-generated-suffix-bound.html
```

The smoke emits `page_labels=["Roman-5000000","Alpha-120000","Decimal-5000000"]`, `preview_page_labels=["Roman-5000000","Alpha-120000","Decimal-5000000"]`, `roman_suffix_decimal_fallback=true`, `alphabetic_suffix_decimal_fallback=true`, `decimal_start_preserved=true`, `generated_suffixes_bounded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused PHP PASS cases: `3109 -> 3110`.
- Focused PageLabels assertions: new file adds `9` assertions.
- WordPress smoke scenarios: `2562 -> 2563`.
- Mapped upstream denominator: unchanged; this is additive inside the already mapped PageLabels/catalog metadata behavior cluster.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, direct kid dictionaries, inherited/local/indirect/malformed/reversed/negative `/Limits`, no-limits kid source order, same-lower source-order guards, duplicate `/Nums` keys, duplicate catalog `/PageLabels` precedence, duplicate `/Kids` keys, duplicate `/Nums` dictionary keys, descending or out-of-range `/Nums` keys, null resets, indirect scalar operands, generation-exact dictionaries/arrays/keys, object-stream PageLabels, escaped names, PDFDocEncoding/UTF-16 prefixes, malformed dictionary/array object tails, selected trailer-root behavior, xref-stream root selection, integer overflow rejection for unrepresentable operands, ordinary alphabetic/Roman formatting, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only representable `/St` values whose Roman or alphabetic generated suffix would otherwise exceed the native metadata cap.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, top-level array/dictionary tokenizer, bounded integer parser, PageLabels number-tree parser, MarkerAppPreview inventory path, and WordPress block smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally out of scope under the current no-GPU/no-live-model direction.

## Next Task

Continue with non-overlapping native markerPDF parser behavior around searchable-PDF fonts/CMaps, xref repair, annotations/forms/security preflight, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
