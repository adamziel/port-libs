# markerPDF font subset ligature encoding current-base

Session: port-dev-markerpdf-fontsubset9-20260602T071944Z

Micro-slice: markerpdf-font-subset-ligature-encoding-current-base-20260602T071944Z

Base accepted HEAD: 0d3c17cefb16134a0a41771bf53f41f80c8a5265

## Source truth

- Upstream markerPDF text extraction crosses through `marker/pdf/extract_text.py` into pdftext/pypdfium text characters instead of treating subset simple-font glyph names as literal bytes.
- The inspected pdftext 0.3.18 source in `/tmp/markerpdf-pdftext-src/pdftext-0.3.18/pdftext/pdf/chars.py` obtains per-character Unicode through `FPDFText_GetUnicode()`.
- The inspected pdftext postprocessor expands Unicode presentation ligatures through `replace_ligatures()`, including `ff`, `fi`, `fl`, `ffi`, `ffl`, and `st` expansions before downstream Markdown/WordPress text.
- Ghostscript Adobe Glyph List and Unicode resources list common glyph-name aliases used by subset fonts, including `f_f`, `f_i`, `f_l`, `f_f_i`, `f_f_l`, `endash`, `eacute`, and `Euro`.

## Native behavior

`PdfTextExtractor::glyphNameToUnicode()` now strips suffixes such as `.alt`, expands underscore-separated glyph-name components, and recognizes common Adobe glyph names for punctuation, accented Latin characters, Euro, and ligatures. This lets a subset simple font with `/BaseFont /ABCDEF+SubsetSerif` and `/Encoding /Differences` names such as `/f_f_i.alt`, `/f_i`, `/endash`, `/eacute`, and `/Euro` render native text before Gutenberg paragraph output.

Red-first evidence before the implementation:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php`
- Result: `1 test files, 420 assertions, 1 failures`
- New fixture expected `Office file \u2013 Caf\u00e9 \u20ac`; before the fix the subset ligature/accent/Euro glyph names decoded through byte fallbacks instead.

Passing evidence after the implementation:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php`
- Result: `1 test files, 421 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests`
- Result: `59 test files, 2507 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-subset-ligature-encoding-import.php`
- Result: emitted a Gutenberg paragraph for `Office file \u2013 Caf\u00e9 \u20ac` and smoke metadata with `executes_python_or_models=false` and `executes_external_pdf_tools=false`.

## Dependency closure

No new support component is needed. The slice reuses the native PHP PDF object parser, simple-font Encoding Differences handling, and content-stream text extraction. Full upstream Python/model/benchmark parity remains dependency-gated by pdftext/pypdfium, Surya, tabled-pdf, Texify, Torch, benchmark fixtures, and live model/runtime execution.

## Non-overlap

This does not repeat the accepted ToUnicode CMap, Standard/MacRoman/Symbol simple-font encoding, PDFDocEncoding metadata, object-stream boundary, stream-filter, or font-width slices. The new behavior is limited to subset simple-font glyph-name decoding through `/Encoding /Differences` when no ToUnicode map supplies Unicode text.
