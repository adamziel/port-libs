# markerPDF stream-filter stack Identity Crypt boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260605T042830Z`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, with pdftext/PDFium handling stream filters before OCR/layout/model stages.
- The PDF Reference 1.5 defines `/Crypt` as a stream filter whose DecodeParms dictionary names the crypt filter, with `Identity` as the standard identity crypt filter. Source: <https://opensource.adobe.com/dc-acrobat-sdk-docs/pdfstandards/pdfreference1.5_v6.pdf>.

## Behavior

`PdfTextExtractor` now decodes `/Crypt` only when the filter stage has an explicit DecodeParms dictionary whose `/Name` is `/Identity` or whose `/Name` is omitted inside that explicit dictionary. Plain `/Filter /Crypt`, named private crypt filters, and encrypted-document text extraction remain fail-closed.

The focused fixture covers ordered content-stream stacks:

```text
/Filter [ /Crypt /FlateDecode ]
/DecodeParms [ << /Name /Identity >> null ]
```

and:

```text
/Filter [ /FlateDecode /Crypt ]
/DecodeParms [ null << /Type /CryptFilterDecodeParms /Name /Identity >> ]
```

Both streams contain a compressed fake line-start `endstream` token, so boundary recovery must still prove the full stack before selecting the real stream terminator. A sibling `/Name /PrivateCF` crypt-filter stream remains excluded before WordPress paragraph rendering.

## Red-First

Before the implementation change, the focused test failed with only the later unfiltered content stream imported:

```text
FAIL treats explicit Identity Crypt filters as pass-through stack stages while rejecting named crypt filters
Expected: Identity Crypt First Before, Identity Crypt First After, Flate Then Identity Crypt, Identity Crypt Tail, Visible After Crypt Boundary
Actual: Visible After Crypt Boundary
```

## Evidence

Focused stack test after the implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
1 test files, 136 assertions, 0 failures
```

Existing stream-filter regression owner:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
1 test files, 628 assertions, 0 failures
```

Final focused crypt/filter regression set:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionUnsupportedCryptFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
5 test files, 1375 assertions, 0 failures
```

Syntax, JSON, and diff checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php
jq empty lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json
git diff --check -- lanes/markerpdf
```

All passed. Root harness status: not run - isolated micro-slice.

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php
```

The smoke emits `identity_crypt_filter_stack_passthrough=true`, `named_crypt_filter_fail_closed=true`, `crypt_filter_decodeparms_excluded=true`, `fake_endstream_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted unsupported `/Crypt` fail-closed streams, encrypted PDF preflight, public-key/Standard crypt-filter permission metadata, ASCII85/Flate stale-length recovery, Flate-first stack recovery, RunLength EOD recovery, null-filter DecodeParms slot alignment, all-null filter arrays, DCT/CCITT/JPX/JBIG2 preview-only image filters, inline-image unsupported `/Crypt` tokenizer boundaries, malformed CMap unsupported-filter handling, or object/xref stream filter ownership repair.

The bounded behavior is specifically explicit Identity `/Crypt` as an identity stage inside ordered page content stream filter stacks, while non-Identity crypt filters remain fail-closed.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream dictionary reader, filter-stack resolver, DecodeParms parser, Flate decoder, stream-boundary recovery, content-token parser, and WordPress smoke renderer. Full decryption, crypt-filter security handlers, password/key derivation, object-specific encryption keys, public-key recipient handling, and signature validation remain out of scope for this no-GPU native parser slice unless activated by a separate security/decryption support component with fixtures.
