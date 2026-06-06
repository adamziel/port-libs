## markerpdf-font-width-advance-boundary-current-base-20260606T032156Z

Accepted base: `3c8b9e6cdbfac97ac54f81052e1e910b2e2834ae`

Source-truth boundary: markerPDF's native searchable-PDF path must follow PDF font advance semantics without invoking GPU/OCR/model code. For simple fonts, `/Widths` entries in the declared `/FirstChar` through `/LastChar` range are numeric glyph advances. A malformed entry must not partially override trusted Base14 metrics and collapse WordPress paragraph word gaps.

Implementation:

- `PdfTextExtractor::simpleFontExplicitWidths()` now treats syntactically malformed declared simple-font width rows as unusable and falls back to the existing Base14/default path.
- Complete valid simple-font `/Widths` arrays remain authoritative for advance boundaries.
- No Python, OCR, Surya/Texify/Torch, external PDF tools, or model runners are used.

Evidence:

- Red-first probe before source edit returned `IllWord` for a Helvetica fixture whose `/Widths` array contained `/BadWidth` in the declared range.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontMalformedWidthAdvanceBoundaryCurrentBaseTest.php` => `1 test files, 30 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfFont*Test.php` => `65 test files, 1096 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-font-malformed-width-advance-boundary-currentbase.php` emits `malformed_width_array_fell_back_to_base14=true`, `word_gap_preserved_for_wordpress_paragraph=true`, `executes_python_or_models=false`, and the paragraph `Ill Word`.

Non-overlap:

- Does not alter Type0 CID `/W` or `/W2`, Type3 CharProc width extraction, CMap source-width segmentation, page-resource inheritance, or xref repair behavior.
- An exploratory `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php` still reports two unrelated ToUnicode `usecmap` mapping failures (`inherits ToUnicode usecmap mappings...` and `guards cyclic ToUnicode usecmap...`) on this base. That parser/name-discovery issue is not part of this font-width advance slice.

Dependency closure:

- No new support component is needed. The patch reuses the existing native PDF parser, Base14 metrics, simple-font encoding, and focused PHP test runner.
