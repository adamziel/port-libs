# markerPDF stream-filter indirect Crypt name boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260605T061332Z`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, with pdftext/PDFium handling low-level stream decoding before OCR/layout/model stages.
- PDF stream `/Filter` arrays are ordered decoder stacks. `/Crypt` is only safe as a native pass-through stage when explicit DecodeParms select the standard `/Identity` crypt filter. Private named crypt filters require real decryption support and must remain fail-closed.

## Behavior

`PdfTextExtractor::decodeCryptIdentityStream()` now resolves a present `/Name` operand in explicit Crypt DecodeParms through the current object table before deciding whether the stage is `/Identity`.

The focused fixture proves both sides of the boundary:

```text
/Filter [ /Crypt /FlateDecode ]
/DecodeParms [ << /Name 10 0 R >> null ]
10 0 obj /Identity endobj
```

imports `Indirect Identity Crypt Import`, while the sibling stream:

```text
/Filter [ /Crypt /FlateDecode ]
/DecodeParms [ << /Name 11 0 R >> null ]
11 0 obj /PrivateCF endobj
```

does not leak `Indirect Private Crypt Leak` into WordPress paragraphs.

An explicit Crypt DecodeParms dictionary with no `/Name` remains the existing Identity boundary. A present-but-unresolved `/Name` now fails closed instead of being treated as omitted.

## Red-First Probe

Before the source change, an ad hoc current-base probe with indirect `/PrivateCF` returned:

```text
array (
  0 => 'Indirect Private Crypt Leak',
)
```

## Evidence

Focused stack test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves indirect Crypt filter names before choosing identity pass-through
1 test files, 156 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php
```

The smoke emits `indirect_crypt_identity_name_resolved=true`, `indirect_private_crypt_name_fail_closed=true`, `indirect_crypt_name_objects_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted unsupported `/Crypt` fail-closed streams, direct Identity `/Crypt` stacks, encrypted PDF permission preflight, CMap identity/private Crypt filter review, inline-image unsupported `/Crypt` tokenizer boundaries, DCT/CCITT identity Crypt image boundaries, ASCII85/Flate/RunLength stream-boundary recovery, null-filter DecodeParms slot alignment, all-null filters, object-stream filter ownership, xref-stream DecodeParms recovery, or live OCR/model behavior.

The bounded behavior is only indirect `/Name` operands inside explicit content-stream Crypt DecodeParms dictionaries.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, current object table, stream dictionary reader, DecodeParms parser, ordered filter decoder, content-token parser, and WordPress smoke renderer. Non-Identity crypt filters still require a real decryption component and remain fail-closed. Full upstream model/OCR parity remains intentionally out of scope under the no-GPU markerPDF direction and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
