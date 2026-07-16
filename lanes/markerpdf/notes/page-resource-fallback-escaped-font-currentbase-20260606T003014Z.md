# Page Resource Fallback Escaped Font Current Base

Lane: markerpdf  
Micro-slice: markerpdf-page-resource-inheritance-current-base-20260606T003014Z  
Accepted base: a0f9a4e8486a1870b3b58c910a9dc3a6b97dbb35

## Scope

This slice stays inside the native no-GPU markerPDF scope. It does not run OCR,
Surya, Texify, Torch, Streamlit/FastAPI model workers, live services, or external
PDF tools.

The bounded behavior is searchable-PDF text extraction when a page has no usable
`/Resources` dictionary. markerPDF's Python path delegates searchable text to
PDF parsers, and PDF names may be hex-escaped, so the native PHP fallback should
not miss the only document font because `/Type /Font` appears as
`/Ty#70e /F#6fnt`.

## Red-First Evidence

Before the source edit, a no-`/Resources` page with a single escaped Type0 font
dictionary:

`<< /Ty#70e /F#6fnt /Subtype /Type0 /Encoding /Identity-H /ToUnicode 6 0 R >>`

extracted raw source glyph text:

`array ( 0 => 'A', )`

The escaped font dictionary missed the single-font fallback prefilter, so its
ToUnicode CMap was not used.

## Implementation

- `PdfTextExtractor::bodyMayContainFontDictionary()` keeps the fast raw
  `/Type /Font` checks.
- If the raw check misses, it now uses the existing top-level PDF-name decoder
  to inspect `/Type` and accept escaped `/Font` values.
- The change is intentionally limited to top-level font dictionary detection;
  it does not promote nested private dictionaries or page resources.

## Verification

- `php -l lanes/markerpdf/src/PdfTextExtractor.php`  
  `No syntax errors detected`
- `php -l lanes/markerpdf/tests/PdfPageResourceFallbackEscapedFontCurrentBaseTest.php`  
  `No syntax errors detected`
- `php -l lanes/markerpdf/examples/wordpress-pdf-page-resource-fallback-escaped-font-currentbase.php`  
  `No syntax errors detected`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceFallbackEscapedFontCurrentBaseTest.php`  
  `1 test files, 8 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceFallbackEscapedFontCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php`  
  `2 test files, 219 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-page-resource-fallback-escaped-font-currentbase.php`  
  emitted `escaped_type_font_fallback_mapped=true`, `page_resources_absent=true`,
  `raw_source_glyph_excluded=true`, `executes_python_or_models=false`, and
  `executes_external_pdf_tools=false`.
- `git diff --check -- lanes/markerpdf`  
  clean

Root harness was not run; this is an isolated micro-slice.

## Non-Overlap

This avoids the accepted page-resource inheritance, escaped `/Kids`, malformed
resource boundary, Form XObject resource, font-width, and CMap fallback clusters.
It only covers the document-level single-font fallback path used after page
resources are absent.

## Dependency Closure

No new support component is needed. The patch reuses existing native PDF name
decoding and ToUnicode CMap extraction helpers.

## Next

Continue with non-overlapping native PDF parser behavior: inherited resource
edge cases, CMaps, font encodings and widths, stream filters, xref repair,
metadata, annotations, forms, page geometry, image/filter metadata, and supplied
table/equation handoffs.
