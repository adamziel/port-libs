# markerPDF inline image Identity Crypt Flate surplus boundary current base

Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T152256Z`

Base accepted HEAD: `6b3aab79916239f37aedcd25bf440809e9645e6e`

## Source Truth

Upstream markerPDF keeps searchable PDF text extraction separate from image rendering/OCR/model handoff. Under the current no-GPU lane scope, this native PHP slice maps the parser-side PDF boundary: inline image data between `BI ... ID ... EI` is image payload and must not become WordPress paragraph text, while the following content stream text must remain visible.

Relevant PDF behavior for this slice: `/Crypt` with `/DecodeParms << /Name /Identity >>` is byte-preserving. If an inline image uses `/Filter [/Crypt /FlateDecode]`, the Flate member can still expose a bounded native end offset. Surplus bytes after that complete Flate member can contain delimiter-looking `EI` and text operators, but they remain image-owned until the later real inline-image terminator.

## Implementation

- `PdfTextExtractor::inlineImageCandidateMatchesDictionary()` now checks Identity-Crypt-prefixed native filter stacks after the existing single-filter and first-filter surplus cases.
- The new helper accepts only byte-preserving Identity `/Crypt` prefixes before another native decoder.
- Unsupported/private Crypt filters still fail closed and remain review-only.
- Clean `/Filter [/Crypt /Fl]` inline images still decode natively for preview metadata.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
FAIL keeps Identity Crypt Flate post-stream surplus closed until the real EI terminator
Expected: [Before Identity Crypt Flate Inline, After Identity Crypt Flate Inline]
Actual:   [Before Identity Crypt Flate Inline]
```

The parser swallowed the post-image paragraph after a fake `EI` inside post-Flate surplus.

## Focused Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
1 test files, 430 assertions, 0 failures
```

The focused file now includes 1 new PASS case and 21 additional assertions over the previous 409-assertion current-base run.

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
```

The smoke emits `identity_crypt_flate_post_stream_payload_excluded_until_real_ei=true`, `identity_crypt_flate_post_stream_preview_rejected=true`, `identity_crypt_flate_clean_preview_decoded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted ASCII85 post-EOD surplus handling, ASCIIHex EOD boundaries, Flate single-filter post-stream surplus, LZW/RunLength EOD surplus, stacked native first-filter surplus, DCT/JPX/JBIG2/CCITT preview-only tokenizer boundaries, malformed filter operands, null-filter DecodeParms alignment, or Image XObject metadata/review behavior.

The bounded new behavior is specifically a byte-preserving Identity `/Crypt` inline-image prefix before a bounded native Flate decoder whose post-stream surplus contains fake `EI` bytes.

## Dependency Closure

No new support component is needed. This reuses the native PHP content-stream tokenizer, inline-image dictionary parser, Crypt Identity pass-through handling, Flate bounded end-offset detection, stream decoder, image preview rejection path, and WordPress smoke path. Full live OCR/model/raster parity remains intentionally out of scope under the no-GPU markerPDF directive: no pdftext/PDFium/PIL, Surya/Texify/Torch/model workers, Streamlit/FastAPI workers, or external PDF tools were run.
