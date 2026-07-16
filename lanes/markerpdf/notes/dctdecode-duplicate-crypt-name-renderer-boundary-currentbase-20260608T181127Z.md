# DCTDecode Duplicate Crypt Name Renderer Boundary Current Base

Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260608T181127Z`

## Source Truth

- Upstream `sddai/markerPDF` routes searchable-PDF image handling through review/raster handoff paths such as `marker.pdf.images.render_image`; in this no-GPU PHP lane, DCTDecode remains review-only metadata unless a native raster backend is explicitly in scope.
- PDF stream filter stacks are ordered. A `/Crypt` prefix is only safe as a native pass-through stage when its DecodeParms select the standard `/Identity` crypt filter. Duplicate `/Name` entries inside one Crypt DecodeParms dictionary are ambiguous and must fail closed before private crypt-filter decoys can be treated as identity.

## Behavior

`PdfImageRenderer::cryptIdentityFilterIsSupported()` now rejects duplicate `/Name` declarations inside Crypt DecodeParms before accepting `/Name /Identity`.

This preserves the accepted positive renderer boundary:

```text
/Filter [/Crypt /DCTDecode]
/DecodeParms [<< /Name /Identity >> null]
```

but fails closed for ambiguous variants such as:

```text
/Filter [/Crypt /DCTDecode]
/DecodeParms [<< /Name /Identity /Name /PrivateCF >> null]
```

The DCTDecode marker metadata is still recorded as review-only, but the native Crypt prefix is not marked decoded and the stream remains unsupported for raster preview.

## Red-First Probe

Before the fix, a current-base renderer probe with duplicate Crypt names returned:

```text
unsupported_filters=[DCTDecode]
native_prefix_decoded=true
decode_failed=false
```

That incorrectly treated the ambiguous Crypt prefix as Identity.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeDuplicateCryptNameRendererBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on duplicate Crypt DecodeParms Name entries before renderer DCTDecode review boundaries
1 test files, 84 assertions, 0 failures
```

Adjacent DCT/inline/stream-filter regression set:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeDuplicateCryptNameRendererBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeRendererStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeIdentityCryptPrefixBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 2293 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-duplicate-crypt-name-renderer-boundary-currentbase.php
```

The smoke emits `identity_supported=true`, `duplicate_crypt_names_fail_closed=true`, duplicate cases with `unsupported_filters=["Crypt","DCTDecode"]`, and all model/PDFium/PIL/external-tool execution flags false.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted direct DCTDecode stream-length recovery, DCT APP/SOS marker parsing, raw false-EOI handling, Flate/LZW/RunLength/ASCIIHex/ASCII85 native-prefix DCT boundaries, null-filter DecodeParms slot alignment, trailing null filters, explicit single `/Name /Identity` Crypt DCT boundaries, inline image Crypt prefix boundaries, content-stream default Crypt behavior, unsupported default/null image Crypt behavior, malformed filter operands, post-DCT filter reachability metadata, or CCITT/JPX/JBIG2 preview-only image filters.

The bounded owned behavior is only duplicate `/Name` declarations inside renderer-side Crypt DecodeParms before DCTDecode.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF dictionary scanner, DecodeParms parser, image filter stack review, Crypt Identity pass-through gate, JPEG marker-boundary scanner, ICCBased image stream metadata path, and WordPress smoke renderer. Non-Identity or ambiguous crypt filters still require a real decryption/security handler and remain fail-closed. Full OCR/model/raster parity remains intentionally out of scope under the no-GPU markerPDF directive: no pdftext/PDFium/PIL, Surya/Texify/Torch/model workers, Streamlit/FastAPI workers, or external PDF tools were run.
