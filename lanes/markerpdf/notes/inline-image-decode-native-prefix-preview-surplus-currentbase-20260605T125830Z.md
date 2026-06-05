# Inline Image Native Prefix Preview Surplus Boundary

Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T125830Z`
Base: `a7fcab9938b3f699e7572fbf8e5c7dcf121bd3dc`
Date: 2026-06-05 UTC

## Source Truth

Upstream markerPDF routes searchable PDF text through parser-backed page text before image, OCR, and model stages. Inline `BI ... ID ... EI` image bytes are raster payload and must not become WordPress paragraph text. Under the no-GPU markerPDF scope, the PHP lane owns the native content-stream tokenizer and image review metadata without running PDFium, PIL, Python models, OCR, or external PDF tools.

PDF inline images may use a native prefix filter before a preview-only raster filter, for example `[/ASCIIHexDecode /JPXDecode]`. When the native prefix reaches its explicit EOD marker and malformed surplus before the real inline-image terminator contains fake `EI` bytes, the tokenizer must keep those bytes image-owned until the real terminator while still preserving following visible text.

## Behavior

`PdfTextExtractor` now decodes the native prefix before the first non-text-decodable inline image filter when full-stack decode stops at a preview-only or unsupported filter. If that decoded prefix reaches the declared image sample floor and the post-prefix surplus contains a fake `EI`, the later real `EI` can close the inline image safely.

The new focused fixture covers an inline image with `/F [/AHx /JPXDecode]`, a valid ASCIIHex EOD marker, post-prefix surplus containing `EI BT ... Tj ET rawtail`, and a later real inline-image `EI`. Before the patch only the text before the inline image imported. After the patch both surrounding WordPress paragraphs import and the surplus payload text stays excluded.

The existing WordPress decode-boundary smoke now reports `wrapped_jpx_prefix_surplus_payload_excluded_until_real_ei=true` and keeps strict preview behavior with `wrapped_jpx_prefix_surplus_preview_rejected=true`.

## Evidence

Red-first focused run before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 368 assertions, 1 failures
```

Focused green after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 375 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
```

Result: emitted `visible_text_imported=true`, `fake_ei_inside_wrapped_jpx_prefix_surplus_payload=true`, `wrapped_jpx_prefix_surplus_payload_excluded_until_real_ei=true`, `wrapped_jpx_prefix_surplus_preview_rejected=true`, `excluded_inline_image_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the accepted clean native-prefix preview metadata slice, inline filter post-EOD preview rejection, ASCII85/ASCIIHex/RunLength EOD enforcement, Flate post-stream surplus closure, LZW/RunLength EOD checks, inline ImageMask sample decoding, inline Indexed palette/soft-mask previews, DCT/CCITT/JPX/JBIG2 review-only image filters, xref repair, metadata extraction, annotations, forms, page geometry, OCR/model execution, or supplied-boundary table/equation handoffs.

The bounded behavior is specifically content-stream tokenization for malformed surplus after a native prefix EOD before a preview-only inline image filter.

## Dependency Closure

No new support component is needed. This reuses native PHP content-stream tokenization, inline image dictionaries, stream filter parsing, native prefix decoders, image review metadata, text extraction, and the existing WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium/PIL rasterization, external PDF tools, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
