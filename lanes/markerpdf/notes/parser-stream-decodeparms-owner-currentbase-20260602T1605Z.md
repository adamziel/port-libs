# markerPDF parser stream DecodeParms owner boundary

Micro-slice: `parser-stream-decodeparams-owner-currentbase-20260602T1605Z`

## Source truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` reaches page text through `marker/pdf/extract_text.py`: `get_text_blocks()` delegates structured extraction to `pdftext.extraction.dictionary_output(...)`, while `naive_get_text()` delegates page text extraction to pypdfium. Native PDF fallback therefore has to keep parser object ownership intact before bytes become WordPress paragraphs.

PDF parser behavior for this slice: a stream object's bytes remain payload through its declared `/Length`. Object headers embedded inside that payload cannot become indirect objects and cannot satisfy another stream's indirect `/DecodeParms` reference. This remains true when the owner stream's `/Length` is a simple indirect integer object.

## Red-first boundary

Before the fix, the focused fixture's carrier stream used `/Length 30 0 R` and contained an early `endstream/endobj` plus a fake later `20 0 obj << /Predictor /Twelve ... >>`. The direct-object scanner stopped at the early terminator, admitted the fake object 20, and the current Flate stream using `/DecodeParms 20 0 R` failed predictor validation. The equivalent red probe returned only:

```text
Visible After Owner Boundary
```

after losing `Current DecodeParms Owner` and `Indirect Length Skips Fake`.

## Implementation

`PdfTextExtractor::directObjectStreamDeclaredEnd()` now resolves simple numeric indirect `/Length` objects that appear before the owner stream object while scanning direct object boundaries. That lets `pdfObjectEndOffset()` skip through the owner stream payload before accepting later `obj/endobj` tokens. The normal stream decoder still resolves full indirect `/Filter` and `/DecodeParms` operands from the established current object table.

The focused fixture proves:

- the valid current `/DecodeParms 20 0 R` predictor dictionary is used;
- the fake stream-owned `20 0 obj` with `/Predictor /Twelve` is ignored;
- the carrier payload text and post-fake payload text do not become visible WordPress paragraphs;
- the path runs without Python, pdftext, pypdfium, models, or external PDF tools.

## Verification

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-parser-stream-decodeparms-owner-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php` passed with 1 file / 10 assertions / 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserObjectStreamFilterOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php lanes/markerpdf/tests/PdfParserXrefOffsetOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamObjectOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfObjectStreamLengthFilterTest.php lanes/markerpdf/tests/PdfObjectStreamNestedTokenBoundaryTest.php` passed with 8 files / 85 assertions / 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php` passed with 2 files / 588 assertions / 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-parser-stream-decodeparms-owner-currentbase.php` emitted `uses_current_decodeparms_object=true`, `fake_decodeparms_object_excluded=true`, `carrier_stream_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- `php tools/run-tests.php lanes/markerpdf/tests` passed with 75 files / 4747 assertions / 0 failures.

## Non-overlap

This does not repeat accepted direct stream `/Length` owner boundaries for fake xref-stream objects, stream dictionary escaped name handling, indirect numeric DecodeParms predictor decoding, DecodeParms fail-closed validation, object-stream indirect filter-chain operand recovery, xref offset-owner rejection, object-stream carrier exclusion, or stale stream length recovery.

The new behavior is specifically simple indirect `/Length` owner scanning before embedded fake `/DecodeParms` object headers can enter the current object table.

## Dependency closure

No new support component is needed. This slice reuses the native PDF object scanner, stream dictionary parser, indirect stream length boundary resolver, stream filter and DecodeParms resolver, Flate predictor decoder, page content extractor, and WordPress smoke harness. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
