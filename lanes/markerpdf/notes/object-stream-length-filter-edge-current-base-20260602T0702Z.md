# markerPDF Object Stream Length/Filter Edge

Session: `port-dev-markerpdf-objlen6-20260602T0702Z`
Micro-slice: `markerpdf-object-stream-length-filter-edge-current-base-20260602T0702Z`
Base accepted HEAD: `c1e3639a2593febf68e4098ce29ad82199121642`

## Source-Truth Boundary

Upstream markerPDF routes PDF text extraction through pdftext/pypdfium before WordPress-ready block rendering. At the native parser boundary, PDF object stream dictionaries use integer `/N` and `/First` entries, and PDF dictionary values may be indirect objects. The PHP port therefore needs to resolve indirect integer object-stream dictionary values before expanding `/ObjStm` members. The slice keeps the existing xref-stream type-2 membership guard: only compressed objects selected by the current xref stream become visible page resources.

## Native Behavior Added

`PdfTextExtractor` now resolves object-stream `/N` and `/First` through the existing indirect integer resolver used by stream `/Length`. This keeps indirect `/Length`, `/Filter`, `/N`, and `/First` object-stream PDFs importable while preventing stale or unlisted compressed members from entering the page tree. The focused fixture also proves that once the compressed catalog/page/resources are recovered, unrelated direct fallback streams are not scanned into visible WordPress text.

## Evidence

Red-first focused test before the source fix:

```text
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves indirect object stream length filter count and first offsets before WordPress text extraction (lanes/markerpdf/tests/PdfObjectStreamLengthFilterTest.php)
Values are not identical
Expected: array (
  0 => 'Object stream length filter page',
  1 => 'Recovered compressed resources',
)
Actual: array (
  0 => 'Object stream length filter page',
  1 => 'Recovered compressed resources',
  2 => 'Unreferenced direct fallback noise',
)

1 test files, 1 assertions, 1 failures
```

Green focused test after the source fix:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS resolves indirect object stream length filter count and first offsets before WordPress text extraction

1 test files, 8 assertions, 0 failures
```

The WordPress smoke `examples/wordpress-pdf-object-stream-length-filter-import.php` emits two Gutenberg paragraphs, `Object stream length filter page` and `Recovered compressed resources`, with `executes_python_or_models=false`, `executes_external_pdf_tools=false`, `recovered_catalog_from_object_stream=true`, `excluded_unreferenced_fallback_stream=true`, and `excluded_unlisted_compressed_catalog=true`.

Integration-base verification:

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfObjectStreamLengthFilterTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-object-stream-length-filter-import.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfObjectStreamLengthFilterTest.php` passed with 1 file, 8 assertions, and 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-object-stream-length-filter-import.php` emitted the two expected Gutenberg paragraphs and exclusion flags.
- `php tools/run-tests.php lanes/markerpdf/tests` passed with 57 files, 2292 assertions, and 0 failures.
- `git diff --check -- lanes/markerpdf` passed.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PDF object scanner, xref stream parser, stream-filter decoder, object-stream expansion path, and content-token text extractor. Full upstream Python/model/benchmark parity remains gated by pdftext, pypdfium2, Surya, tabled, Texify, Torch, and benchmark runner dependencies.
