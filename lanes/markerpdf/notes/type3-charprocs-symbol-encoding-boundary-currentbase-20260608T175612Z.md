# Type3 CharProcs SymbolEncoding Boundary Current Base

- Session: `port-dev-markerpdf-type3-charprocs-20260608T175612Z`
- Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260608T175612Z`
- Accepted base: `f2ba04d4070c87822ee15c9bf00e9247a5017259`

## Source Truth

The no-GPU markerPDF lane owns native PHP PDF parser and converter behavior before WordPress import. This slice uses the local PDF parser path only; it does not run OCR, models, PDFium, Surya, Texify, GPU code, or live services.

For the boundary behavior, the source-truth encoding vector is the standard SymbolEncoding glyph-name mapping as shipped locally in Ghostscript at `/usr/share/ghostscript/Resource/Init/gs_sym_e.ps`. The relevant upstream-backed edge is that a Type3 font can use `/Encoding /SymbolEncoding` while its `/CharProcs` dictionary is keyed by Symbol glyph names such as `/alpha`, `/beta`, and `/gamma`.

## Red-First Evidence

Before the source fix, a focused probe decoded visible text for Symbol bytes `61 62 67` as the Greek alpha/beta/gamma Unicode string, but `extractImageXObjectBoundaryReview()` returned `image_xobject_count = 0` because Type3 CharProc lookup still used StandardEncoding glyph names (`/a`, `/b`, `/g`) instead of SymbolEncoding glyph names (`/alpha`, `/beta`, `/gamma`).

After the fix, the same probe returns three Type3 glyph image-review entries:

- `/alpha` from byte `0x61`
- `/beta` from byte `0x62`
- `/gamma` from byte `0x67`

Visible WordPress text remains the Greek alpha/beta/gamma Unicode string; image payload bytes stay excluded from visible text.

## Implementation

`PdfTextExtractor::namedEncodingGlyphNamesByCode()` now routes `/SymbolEncoding` through a dedicated `symbolEncodingGlyphNamesByCode()` table. That lets Type3 CharProc image review select Symbol glyph programs while preserving the existing Unicode text extraction path from `namedEncodingMap('SymbolEncoding')`.

The focused test fixture uses:

- Type3 font `/Encoding /SymbolEncoding`
- `/CharProcs << /alpha 3 0 R /beta 3 0 R /gamma 4 0 R >>`
- page text bytes `<616267>`
- a shared glyph image resource invoked from those CharProcs

## Status Delta

- `lane-status.json` `phpPass`: `3352 -> 3353`
- `lane-status.json` `wordpressScenarios`: `2731 -> 2732`
- mapped upstream manifest coverage: unchanged
- focused test growth: `1` new behavior test, `81` assertions
- WordPress smoke growth: `1` new local example

## Verification

- `php -l lanes/markerpdf/src/PdfTextExtractor.php`
  - no syntax errors
- `php -l lanes/markerpdf/tests/PdfFontType3CharProcsSymbolEncodingBoundaryCurrentBaseTest.php`
  - no syntax errors
- `php -l lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-symbol-encoding-currentbase.php`
  - no syntax errors
- `php -r '...json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true)...'`
  - `lane-status.json valid JSON`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsSymbolEncodingBoundaryCurrentBaseTest.php`
  - `1 test files, 81 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsSymbolEncodingBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcCMapBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcToUnicodeCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php`
  - `5 test files, 798 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-symbol-encoding-currentbase.php`
  - emits a markerPDF HTML comment with `symbol_encoding_charprocs_reviewed=true`
  - emits one Gutenberg paragraph containing the decoded Symbol text
- `git diff --check -- lanes/markerpdf`
  - clean

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF parser, Type3 CharProc content interpreter, Flate decoder, and image-review metadata path already present in `lanes/markerpdf`. Model/OCR parity remains intentionally out of scope for this markerPDF lane.

## Non-Overlap

This does not repeat already-green Type3 direct/indirect `/CharProcs` dictionary boundaries, object-stream CharProcs, comment-separated CharProcs, Type3 `/ToUnicode` text mapping, page-level image review, nested Form XObject review, tiling-pattern review, or simple-font Symbol text decoding. The new behavior is specifically SymbolEncoding glyph-name lookup before Type3 CharProc image review.
