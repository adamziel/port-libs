# markerpdf-inline-image-decode-boundary-current-base-20260606T005126Z

Base accepted HEAD: `c966e5ff0216e9268907832b43b9f7429fe085a0`

## Behavior

This slice closes a supplied-preview boundary for inline images using
`/F [/Crypt /JPXDecode]` with `/DecodeParms [<< /Name /Identity >> null]`.
The Identity crypt filter passes bytes through to the JPX preview-only filter,
so native PHP text extraction must still consume inline-image bytes until the
real `EI` token while supplied preview helpers must reject raw JPX codestream
payloads that contain non-whitespace surplus after the JPX EOC marker
(`FF D9`).

The implementation only tightens the explicit-boundary preview handoff for
raw JPX codestream input. Clean raw JPX prefixes remain eligible for review
metadata, and other preview-only filters are left unchanged for this slice.

## Red-First Evidence

Before the source edit, the new focused assertion failed as expected:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Expected exception InvalidArgumentException was not thrown
```

The failing case passed an Identity Crypt-wrapped raw JPX codestream followed
by `ZZ EI BT ... (Identity Crypt JPX Inline Noise) ... rawtail` into
`inlineJpxColorKeyOutputPreviewRows()`. Text extraction already resumed at the
real inline-image boundary, but the supplied preview helper accepted the
post-EOC surplus as review input.

## Verification

After the implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
1 test files, 563 assertions, 0 failures
```

The WordPress smoke example emits:

- `identity_crypt_jpx_post_eoc_preview_rejected=true`
- `identity_crypt_jpx_post_eoc_payload_excluded_until_real_ei=true`
- `identity_crypt_jpx_clean_preview_native_prefix_decoded=true`
- `executes_python_or_models=false`
- `executes_external_pdf_tools=false`

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The patch reuses native PHP parser/filter
state and existing image preview helpers. It does not invoke OCR, GPU/model
workers, Python, external PDF tools, or online services.

## Non-Overlap

This does not repeat prior ASCII85, ASCIIHex, Flate, LZW, RunLength, tokenizer,
or Identity Crypt + Flate inline-image boundary slices. The new coverage is
specifically Identity Crypt pass-through into preview-only raw JPXDecode with
post-EOC surplus before supplied preview handoff.

## Next Task

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser
or supplied-boundary behavior: stream filters, CMaps/fonts, xref repair,
annotations/forms, metadata, page geometry, image/filter metadata, and table
or equation handoff boundaries.
