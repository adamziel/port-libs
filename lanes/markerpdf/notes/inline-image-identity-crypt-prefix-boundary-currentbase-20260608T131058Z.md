# markerPDF Inline Image Identity Crypt Prefix Boundary

## Slice

- Lane: `markerpdf`
- Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260608T131058Z`
- Accepted base: `eaf19e1f6617047d412ce09c461d8bd2634185f2`
- Scope: native PDF parser/text extraction and image-filter metadata only; no OCR, raster backend, GPU/model execution, PDFium/PIL, or external PDF tools.

## Source Truth

This maps the upstream markerPDF boundary between page text extraction and inline image extraction: inline BI/ID/EI image payload bytes are not visible text, while native image filters can still provide an ownership boundary before a stray `EI` inside post-filter bytes.

PDF `/Crypt` with `/DecodeParms << /Name /Identity >>` is byte-preserving. When it prefixes a bounded native inline-image filter such as `ASCII85Decode`, `LZWDecode`, or `RunLengthDecode`, the following filter's explicit end marker should still be allowed to close the candidate before fake `EI` bytes in post-filter surplus. This matters even when `/CS` or `/BPC` are absent and the tokenizer cannot compute a decoded sample-floor length.

## Behavior

`PdfTextExtractor::inlineImageCandidateMatchesDictionary()` now checks the existing Identity Crypt prefix recovery path in the no-sample-floor fallback. The helper accepts `?int $expectedLength`: with a length it keeps the old sample-floor check; without one it requires a non-empty decoded native payload plus the existing post-filter surplus guard that looks for a delimiter-looking fake `EI`.

The focused fixture covers:

- `/Filter [/Crypt /A85] /DecodeParms [<< /Name /Identity >> null]`
- `/Filter [/Crypt /LZW] /DecodeParms [<< /Name /Identity >> null]`
- `/Filter [/Crypt /RL] /DecodeParms [<< /Name /Identity >> null]`

Each inline image omits `/CS` and `/BPC`, includes a valid native-filter EOD, then appends `ZZ EI ... rawtail` text-like surplus before the real inline image terminator. Before the fix, extraction stopped after the first pre-image text line. After the fix, only the before/after page text is emitted and the payload surplus remains excluded.

## Red First

Before the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeIdentityCryptPrefixBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps Identity Crypt prefix native-EOD inline images without sample floors closed until real EI terminators
1 test files, 19 assertions, 1 failures
```

## Verification

After the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeIdentityCryptPrefixBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps Identity Crypt prefix native-EOD inline images without sample floors closed until real EI terminators
1 test files, 29 assertions, 0 failures
```

Adjacent inline decode regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeIdentityCryptPrefixBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 2 selected test files (root lock skipped)
...
2 test files, 1029 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-crypt-prefix-boundary-currentbase.php
```

The smoke exits 0 and emits two WordPress paragraph blocks:

- `Before Identity Crypt Prefix Inline Image`
- `After Identity Crypt Prefix Inline Image`

The smoke metadata records `identity_crypt_prefix_before_native_eod=true`, `inline_payload_excluded_from_text=true`, and no Python/model/PDFium/PIL/external tool execution.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted inline image abbreviation/DecodeParms handling, ASCIIHex/ASCII85 explicit EOD, Flate DecodeParms, LZW/RunLength EOD ownership, post-EOD surplus, wrapped terminal filters, preview-only DCT/JPX/JBIG2/CCITT filters, malformed Decode/DecodeParms fail-closed behavior, Identity Crypt standalone previews, Identity Crypt + Flate sample-floor recovery, Identity Crypt + JPX preview recovery, or native-filter Identity Crypt suffix no-sample-floor recovery.

The new behavior is only Identity Crypt as a byte-preserving prefix before a bounded native inline-image filter when no decoded sample-floor length is available.

## Dependency Closure

No new support component is needed. This reuses the native PDF content tokenizer, stream filter resolver, Crypt identity pass-through decoder, ASCII85/LZW/RunLength filter end-marker scanners, inline-image review metadata, and WordPress smoke renderer. Full raster decoding, OCR, Surya/Texify/Torch model execution, live service calls, and exact upstream model benchmark parity remain intentionally out of scope under the no-GPU markerPDF directive.
