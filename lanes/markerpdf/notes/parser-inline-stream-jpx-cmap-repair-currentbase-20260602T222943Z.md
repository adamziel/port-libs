# markerPDF parser inline JPX CMap repair

Slice: `parser-inline-stream-jpx-cmap-repair-currentbase`

Upstream source truth: `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes visible PDF text through `marker/pdf/extract_text.py::get_text_blocks()` via `pdftext.dictionary_output()` and through `naive_get_text()` via pypdfium page text extraction: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>

That boundary means inline image payload bytes are not Gutenberg paragraph text, even when they contain CMap-looking operators or text-showing tokens. The native PHP fallback already skipped complete JPX inline images and decoded filtered ToUnicode CMaps, but a truncated JPX payload without the JPEG 2000 EOC marker caused the inline-image skipper to keep scanning until EOF after the real `EI` terminator. That preserved image-payload exclusion but dropped following page text.

Implemented behavior:

- `PdfTextExtractor::skipInlineImage()` now remembers delimiter-style `EI` candidates for incomplete JPX payloads.
- Complete JPX codestreams still ignore premature payload `EI` bytes until the EOC-framed candidate is found.
- If no complete JPX candidate appears, the parser falls back to the last incomplete JPX `EI` delimiter instead of consuming the remainder of the page content stream.
- The focused fixture proves current filtered ToUnicode CMap text before and after the malformed inline JPX image is preserved, while CMap-like bytes and text operators inside the inline JPX payload stay excluded.

Red baseline before source repair:

```text
array (
  0 => 'Before Truncated JPX',
)
```

Focused green after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs truncated inline JPX boundaries without leaking CMap-like payload text

1 test files, 18 assertions, 0 failures
```

Adjacent parser/image regression gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamLengthFilterRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
8 PASS lines

6 test files, 152 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-stream-jpx-cmap-repair-currentbase.php
```

The smoke emits `visible_text_imported=true`, `excluded_inline_jpx_cmap_payload_text=true`, `excluded_inline_jpx_stream_text=true`, `cmap_stream_count=1`, `to_unicode_cmap_stream_count=1`, `decoded_cmap_count=1`, and all Python/model/external-tool execution flags false.

Non-overlap:

This does not repeat accepted complete inline JPX soft-mask handling, inline JPX ColorKey sample preview, inline image abbreviation/DecodeParms repair, filtered stream length repair, object-stream filter helper repair, or filtered CMap owner recovery. The new behavior is specifically malformed/truncated JPX inline-image delimiter fallback while preserving current CMap-decoded page text.

Dependency closure:

No new support component is needed. The slice reuses native PDF content-token parsing, inline-image dictionary abbreviation handling, stream filter decoding, CMap decoding, page-tree text extraction, and the existing WordPress smoke path. Full upstream runner parity remains dependency-gated by pdftext, pypdfium2/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark tooling, and external OCR/rendering helpers.
