# markerPDF stream-filter stack default Crypt boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260605T091822Z`

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through the pdftext/PDFium-backed text path before OCR/layout/model stages.
- ISO 32000-1 stream filters apply in array order. The `/Crypt` filter decode parameter `/Name` is optional and defaults to `/Identity`; named/private crypt filters still require decryption support.

## Behavior

This current-base slice adds the missing default-identity `/Crypt` stream behavior for native searchable text extraction:

```text
/Filter [ /Crypt /FlateDecode ]
/DecodeParms [ null null ]
```

Before this patch, content streams using omitted or `null` `/Crypt` DecodeParms were dropped, leaving only later fallback text. After this patch, omitted, `null`, and empty-dictionary `/Crypt` parameters pass through as `/Identity`, then the remaining filter stack decodes normally. Named private crypt filters such as `/PrivateCF` still fail closed.

Inline image tokenization remains conservative: unparameterized inline-image `/Crypt` still stays an unsupported tokenizer boundary unless explicit identity parameters are present, so raster payload bytes do not reopen text parsing.

## Red Probe

Before the source change, the focused test returned only:

```text
Visible After Default Crypt
```

The expected `/Crypt` default-identity rows were absent:

```text
Default Crypt Stack Before
Default Crypt Stack After
Default Crypt Null DecodeParms
Default Crypt Empty Dict
```

## Evidence

Focused stream-filter stack run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 193 assertions, 0 failures
```

Adjacent tokenizer/CMap/filter family run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 1290 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php
```

The smoke emits `default_crypt_decodeparms_identity_passthrough=true`, `default_crypt_private_filter_fail_closed=true`, `fake_endstream_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted direct/null filter placeholders, indirect null filter objects, all-null filter arrays, compact DecodeParms arrays, abbreviated filters, stray DecodeParms with no `/Filter`, extra DecodeParms rejection, malformed DecodeParms parameters on real filters, unresolved DecodeParms owner rejection, explicit CMap/content-stream `/Crypt /Identity` handling, image-filter exclusion, inline-image tokenizer unsupported-filter boundaries, or live OCR/model work.

The bounded behavior is specifically default `/Identity` semantics for `/Crypt` stream-filter stack stages before native searchable PDF text extraction.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream dictionary reader, filter-stack resolver, DecodeParms alignment logic, Crypt identity pass-through, Flate decoder, content-token parser, inline-image tokenizer guard, and WordPress smoke renderer. Full upstream model/OCR parity remains intentionally out of scope under the current no-GPU markerPDF direction and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed.
