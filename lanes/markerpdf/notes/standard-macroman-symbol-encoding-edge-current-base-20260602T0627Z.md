# markerPDF Standard/MacRoman/Symbol Encoding Edge

Session: `port-dev-markerpdf-stdenc6-20260602T0627Z`
Micro-slice: `markerpdf-standard-macroman-symbol-encoding-edge-current-base-20260602T0627Z`
Base accepted HEAD: `9e6b0e7af1f31bd91591dc91290e10dc6a063e27`

## Source Truth

Upstream `sddai/markerPDF` extracts page text through pdftext/pypdfium before model conversion and Markdown cleanup, so simple-font character decoding is a parser boundary before WordPress paragraph rendering. The local upstream clone path recorded in the manifest was not present in this worktree cache, so this slice used the already accepted markerPDF text boundary plus local PDF parser encoding resources as dependency source truth:

- `/usr/share/ghostscript/Resource/Decoding/StandardEncoding`
- `/usr/share/ghostscript/Resource/Init/gs_mro_e.ps`
- `/usr/share/ghostscript/Resource/Init/gs_sym_e.ps`

Those resources define the StandardEncoding, MacRomanEncoding, and SymbolEncoding code-to-glyph vectors used by PDF simple fonts.

## Native Behavior Added

`PdfTextExtractor` now:

- Decodes `/Encoding /StandardEncoding` bytes such as Standard quoteright, `fi`, `fl`, and `AE`.
- Reads `/BaseEncoding /MacRomanEncoding` dictionaries, including the no-Differences case, before falling back to raw bytes.
- Decodes MacRoman accented and operator bytes such as eacute, udieresis, and divide.
- Applies implicit SymbolEncoding when a simple font has `/BaseFont /Symbol` without an explicit `/Encoding`.
- Keeps existing WinAnsiEncoding behavior and merges `/Differences` over a named `/BaseEncoding` when both are present.

## Evidence

Red-first focused failure before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
FAIL decodes Standard MacRoman and Symbol simple font encodings before WordPress paragraphs
Expected: WP's Standard ligature/accent/Symbol Unicode rows
Actual: Standard/MacRoman high bytes became replacement characters and Symbol alpha/beta/gamma fell back to ASCII.
1 test files, 374 assertions, 1 failures
```

Passing focused gate after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
1 test files, 376 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-standard-macroman-symbol-encoding-import.php
```

The smoke emitted three Gutenberg paragraphs: StandardEncoding `WP's fi/fl/AE` Unicode text, MacRoman `Mac eacute udieresis divide`, and Symbol `alpha beta gamma + greater-equal`, with `executes_python_or_models=false` and `executes_external_pdf_tools=false`.

Syntax and whitespace checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfTextExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-standard-macroman-symbol-encoding-import.php
git diff --check -- lanes/markerpdf
```

All passed.

## Status Delta

- Behavior tests: `413 -> 414`.
- Mapped upstream/dependency semantics: `266 -> 267 / 78`.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object/font parser and text extraction pipeline. Full upstream Python/model/benchmark parity remains dependency-gated by pdftext, pypdfium2, Surya, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI runtime paths, and benchmark tooling.

## Non-Overlap

This does not repeat accepted simple-font Encoding Differences, WinAnsiEncoding punctuation, ToUnicode CMap, Base14 width metrics, Type3 CharProc width, FontDescriptor, StructTree RoleMap, stream-filter, xref/object-stream, optional-content, or text-rendering-mode slices. The new behavior is specifically named simple-font StandardEncoding, MacRomanEncoding/BaseEncoding, and implicit SymbolEncoding fallback when no ToUnicode CMap is present.
