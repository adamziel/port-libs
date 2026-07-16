# markerPDF Font CID CMap Descendant Width Current Base

Session: `port-dev-markerpdf-font47-20260602T2025Z`
Micro-slice: `font-cid-cmap-width-descendant-currentbase`
Base accepted HEAD: `1d0255efc342976ccd01090ebca142bc846d342a`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` uses pdftext/PDFium text extraction as the native PDF text boundary before Marker block cleanup. The pdftext upstream README describes structured page output with line/span text, bbox, rotation, and font metadata, and states that pdftext first extracts characters and font information from pypdfium2/PDFium before grouping characters into lines and blocks.

This PHP slice keeps that boundary native and bounded: Type0 `/Encoding` CMap source codes are mapped to CIDs before descendant CIDFont vertical advance metrics are applied. Descendant `/W2` and `/DW2` arrays may contain indirect numeric operands, just as horizontal `/W` already did in the PHP port.

## Behavior

`PdfTextExtractor::cidVerticalDisplacementsFromW2Array()` now parses `/W2` with the PDF array item parser instead of content-stream tokenization. That preserves operands like `8 0 R` and `10 0 R` as object references, allowing `pdfNumberValueAt()` and `numbersFromPdfArrayResolvingObjects()` to resolve:

- `/W2 [40 49 8 0 R 500 880 ...]` range vertical displacements.
- `/W2 [40 [8 0 R 500 880 ...]]` array-form vertical displacements.
- `/DW2 [880 9 0 R]` default vertical displacement operands.

Before the fix, the current fixture emitted `Vert Import` and `Data Flow` because indirect `/W2` operands were tokenized as bare numbers and the second CID range fell back to the wrong default advance. After the fix, WordPress paragraph extraction emits `VertImport` and `DataFlow`.

## Red / Green Evidence

Red before source fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontCidCMapWidthDescendantCurrentBaseTest.php`

Result:

`1 test files, 1 assertions, 1 failures`

Failure showed:

Expected `['VertImport', 'DataFlow']`; actual `['Vert Import', 'Data Flow']`.

Green after source fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontCidCMapWidthDescendantCurrentBaseTest.php`

Result:

`1 test files, 7 assertions, 0 failures`

Neighboring font/text regression gate:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontCMapUseCMapVerticalWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0VerticalUseCMapCidSetCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidEncodingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php`

Result:

`4 test files, 618 assertions, 0 failures`

## WordPress Smoke

`lanes/markerpdf/examples/wordpress-pdf-font-cid-cmap-width-descendant-currentbase.php` emits Gutenberg paragraph blocks for:

- `VertImport`
- `DataFlow`

The metadata comment records `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and confirms Type0 CMap CIDs plus indirect descendant `/W2` and `/DW2` operands were applied.

## Non-Overlap

This does not repeat accepted horizontal CID `/W` indirect operand handling, Type0 Encoding CMap CID mapping, named CMap resource inheritance, object-valued `/UseCMap` streams, predefined vertical CMap writing mode, CIDSet default-width grouping, Type3 CharProc width handling, FontDescriptor flag extraction, page resource scoping, ToUnicode bfrange/usecmap behavior, or xref/object-stream parser recovery. The new behavior is specifically object-valued numeric operands inside descendant CIDFont `/W2` and `/DW2` metrics after current Type0 CMap CID remapping.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object inventory, array parser, object resolver, CMap parser, Type0/CIDFont width maps, text-position grouping, and WordPress example path. Full upstream runner parity remains gated on the existing pdftext, pypdfium2/PDFium, Surya/Torch/model, tabled-pdf, Texify, Streamlit/FastAPI, and benchmark runtime dependencies.
