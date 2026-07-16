# markerPDF Font ToUnicode Width Resource Boundary

Session: `port-dev-markerpdf-font23pdf-20260602T1512Z`

Micro-slice: `font-tounicode-width-resource-boundary-currentbase-20260602T1512Z`

Accepted base: `c42f7d5b86b2747133272f1f26b4e3d9fda2ed6b`

## Source Truth

Upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates low-level PDF text extraction to `pdftext.extraction.dictionary_output` in `marker/pdf/extract_text.py` and then groups pages/blocks through Marker conversion. This native PHP slice keeps that PDF text boundary local to the selected page resources before WordPress paragraph assembly.

Relevant upstream references:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/pyproject.toml`

PDF page-tree `/Resources` is an inheritable page attribute selected from the nearest page-tree object that provides it. A descendant page resource dictionary is not a partial merge with an ancestor `/Pages` resource font dictionary. The native reduced extractor therefore must not let an ancestor `/F1` ToUnicode map or descendant CIDFont width metrics leak into a page that declares its own leaf `/Resources`.

## Native Behavior

`PdfTextExtractor::pageFontToUnicodeMaps()` now asks `pageResourceDictionaryBody()` for the nearest resource dictionary in the page lineage. Font resource maps are built only from that dictionary. If a page has no resource dictionary at all, the existing single-font fallback remains available.

The focused fixture gives the `/Pages` ancestor `/Resources << /Font << /F1 ... >> >>` with a ToUnicode CMap mapping `<41>` and `<42>` to `Ancestor` and `Leak`, plus CIDFont widths that would join those words. The leaf page declares its own `/Resources << /Font << /F2 ... >> >>`; page content intentionally uses missing `/F1` for `<41>` and `<42>`, then local `/F2` for `<43>`. Correct native output is:

- text lines: `["A B", "Local Resource"]`
- text runs: `["A", "B", "Local Resource"]`
- no `Ancestor` or `Leak` visible text

A second fixture declares an empty leaf `/Resources << >>` with a single ancestor font object in the file. That page still emits raw fallback `["A B", "C"]`, proving the single-font malformed-PDF fallback does not cross a present page-resource boundary and does not import the ancestor ToUnicode or width metrics.

The WordPress smoke emits Gutenberg paragraphs for `A B` and `Local Resource` and records `nearest_resource_dictionary_wins=true`, `ancestor_font_tounicode_excluded=true`, `ancestor_font_widths_excluded=true`, `page_local_font_resource_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Evidence

Red-first before implementation:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php`
- Result: `1 test files, 565 assertions, 1 failures`
- Failure: expected `["A B", "Local Resource"]`; actual included `["AncestorLeak", "Local Resource"]`

After implementation:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php`
- Result: `1 test files, 573 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php`
- Result: `3 test files, 626 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-font-tounicode-width-resource-boundary-currentbase.php`
- Result: emitted the expected marker comment and two Gutenberg paragraph blocks

Changed PHP lint passed:

- `php -l lanes/markerpdf/src/PdfTextExtractor.php`
- `php -l lanes/markerpdf/tests/PdfTextExtractorTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-font-tounicode-width-resource-boundary-currentbase.php`

Final whitespace gate:

- `git diff --check -- lanes/markerpdf`
- Result: passed

Status delta: behavior tests `526 -> 527`; mapped semantics `373 -> 374 / 78`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object scanner, page-tree traversal, stream decoding, CMap decoding, CIDFont width parsing, text grouping, and WordPress smoke path. Full upstream runner parity remains blocked by the existing heavy Python/model stack: pdftext, pypdfium2/PDFium, Surya, tabled-pdf, Texify, Torch, Streamlit/FastAPI, and model downloads.

## Non-Overlap

This does not repeat page-tree `/Contents` inheritance, Type0 CMap boundaries, CIDFont decimal/default/vertical widths, simple-font widths, CMap row-count parsing, Form XObject scoped resources, annotation appearance resources, or OutputIntent-associated FileSpec metadata. It is only the page-resource font ToUnicode/width scope boundary for a leaf page that declares its own `/Resources`.
