# markerPDF Named Destinations Malformed UTF-16 Boundary

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260606T152044Z`
Session: `port-dev-markerpdf-named-destinations-20260606T152044Z`
Base accepted HEAD: `7f8e868beeae24e1de79173228c726eeee807d87`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` carries searchable PDF navigation metadata through pdftext/PDFium before OCR/model handoff. Under the current no-GPU markerPDF scope, this slice maps a native PDF name-tree boundary for catalog `/Names /Dests`: malformed UTF-16BE text-string keys should fail closed before WordPress named-destination review and must not emit PHP decode notices.

No OCR, Surya, Texify, Torch, Python model worker, pypdfium/PDFium, browser, or external PDF tool execution was used.

## Behavior

- `PdfNamedDestinationExtractor::decodeTextString()` now validates UTF-16BE/UTF-16LE byte length and encoding before `iconv()`, matching the stricter decode behavior used by adjacent markerPDF extractors.
- Malformed UTF-16 destination keys such as `<FEFF004100>` are decoded to an empty name and skipped, without PHP notices.
- Valid destination rows and legacy `/Dests` dictionary entries continue to appear as review metadata.
- Malformed destination key rows, stale coordinates, and destination labels stay out of visible WordPress text.

## Red-First Evidence

Before the source edit, a focused probe against a catalog `/Names /Dests` row keyed by `<FEFF004100>` produced:

```text
PHP Notice:  iconv(): Detected an incomplete multibyte character in input string in lanes/markerpdf/src/PdfNamedDestinationExtractor.php on line 2414
```

The malformed row was skipped, but the notice made the boundary unacceptable. The new regression captures PHP decode errors with `set_error_handler()` and would have failed before the fix because the error array was non-empty.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationMalformedUtf16BoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on malformed UTF-16 destination name-tree keys without PHP notices
PASS keeps malformed UTF-16 destination key rows out of WordPress text and metadata
1 test files, 17 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfNamedDestination.*Test\.php$' | sort)
Focused test run: 38 selected test files (root lock skipped)
38 test files, 1099 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-malformed-utf16-boundary-currentbase.php
Emits malformed_utf16_key_filtered=true, php_decode_notices=0, malformed_coordinate_excluded=true, visible_text_excludes_destination_metadata=true, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

Syntax checks:

```text
php -l lanes/markerpdf/src/PdfNamedDestinationExtractor.php
php -l lanes/markerpdf/tests/PdfNamedDestinationMalformedUtf16BoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-named-destination-malformed-utf16-boundary-currentbase.php
```

All reported no syntax errors.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2591 -> 2593`.
- `wordpressScenarios`: `2195 -> 2196`.
- New focused file: `PdfNamedDestinationMalformedUtf16BoundaryCurrentBaseTest.php` adds 2 PASS cases and 17 assertions.
- New WordPress smoke: `wordpress-pdf-named-destination-malformed-utf16-boundary-currentbase.php`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, tokenizer, text-string decoder, name-tree `/Limits` parser, page-tree indexer, destination normalizer, metadata extractor, text extractor, and WordPress smoke renderer. Full upstream runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/Torch OCR/layout/table models, Texify equation recognition, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed for this bounded no-GPU PHP slice.

## Non-Overlap

This does not repeat accepted named-destination `/Limits` pruning, byte-string limit comparison, PDFDocEncoding key decoding, PDF-name key rejection, malformed leaf/intermediate fallback, indirect arrays, child `/Kids` ordering, kid generation/reference boundaries, duplicate key behavior, alias/action cycles, view-mode validation, page-operand validation, coordinate validation, object-stream/xref/trailer-root recovery, outline destination action context, link annotation promotion, metadata root selection, attachments, fonts, images, stream filters, tables, or runtime conversion behavior. The bounded behavior is only malformed UTF-16 text-string decode failure before standalone named-destination review.
