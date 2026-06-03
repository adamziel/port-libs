# markerPDF stream filter stack boundary current base

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260603T090445Z`

## Source Truth

- Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, delegating low-level PDF stream decoding to pdftext/PDFium before OCR/layout/model stages.
- PDF stream `/Filter` arrays are ordered stacks. When `/Length` is missing, a native parser must not accept a line-start `endstream` token inside the encoded bytes until the declared filter stack can decode completely.

## Behavior

The current parser already has the missing-length filtered-stream recovery needed for this boundary. This slice adds a stricter current-base fixture for a true two-filter page content stream:

- `/Filter [ /ASCII85Decode /FlateDecode ]`;
- no `/Length`;
- the raw ASCII85 bytes deliberately spell a line-start `endstream` before the ASCII85 `~>` EOD marker;
- after full ASCII85 and Flate decoding, the WordPress-visible page text still contains the two expected paragraphs and excludes fake stream-boundary bytes.

The fixture uses a hand-built zlib stored block so the ASCII85 source can contain the fake `endstream` sequence without running external PDF tools or model code.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses ASCII85 EOD markers before accepting missing-Length filter-stack endstream boundaries
PASS uses the complete ASCII85 and Flate stack before accepting missing-Length endstream boundaries

1 test files, 18 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php
```

The smoke emits `requires_complete_filter_stack_before_boundary=true`, `fake_endstream_payload_excluded=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and four paragraphs: `Before ASCII85 Stack Boundary`, `After ASCII85 Stack Boundary`, `Stacked ASCII85 Flate Before`, and `Stacked ASCII85 Flate After`.

Syntax checks:

```text
php -l lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php
```

Both reported no syntax errors.

## Non-Overlap

This does not repeat accepted length-bounded ASCIIHex/RunLength stack decoding, ordinary ASCII85 success-path decoding, stream-filter error fail-closed behavior, indirect filter-name arrays, DecodeParms alignment/fail-closed behavior, object-stream filter ownership, xref-stream filter DecodeParms recovery, image-filter exclusion, inline-image tokenizer boundaries, or the prior `null + ASCII85` missing-length boundary alone.

The added evidence is specifically the complete ordered `/ASCII85Decode /FlateDecode` page-content stack before accepting a missing-`/Length` `endstream` boundary.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF object scanner, stream dictionary reader, filter resolver, ASCII85 decoder, Flate decoder, missing-length stream boundary recovery, content-token parser, and WordPress smoke path. Full upstream model/OCR parity remains intentionally out of scope under the no-GPU direction and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
