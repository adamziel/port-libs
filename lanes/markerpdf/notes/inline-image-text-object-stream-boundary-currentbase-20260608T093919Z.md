# Inline Image Text-Object Stream Boundary Current Base

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260608T093919Z`

Accepted base: `f7295ca99962e4a2fb64b25159ca465a0c1f3909`

## Source Truth

- Upstream markerPDF extraction treats inline-image bytes as raster payload; searchable text resumes after a real content-stream boundary.
- Native no-GPU scope applies: this slice only changes PHP searchable-PDF content stream repair. It does not run OCR, Surya, Texify, Torch, Python models, raster rendering, or external PDF tools.
- PDF content streams allow `BI`/`ID`/`EI` only as graphics operators outside text objects. A `BI ... EI` token sequence inside `BT`/`ET` is text content/operator noise, not an inline image payload boundary.

## Red-First Evidence

Before the source fix, a missing-Length stream containing a text-object-local `BI ... EI` sequence and a fake bare `endstream` token was truncated during stream repair:

```text
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps text-object BI tokens from truncating stale-Length stream repair before WordPress import
missing length text lines
Expected: ["Before Text BI EndstreamText Object BI With EI SurvivesAfter Fake Endstream Token Survives","After Text BI Endstream"]
Actual: ["Before Text BI EndstreamText Object BI With EI Survives"]
1 test files, 1 assertions, 1 failures
```

The declared exact-length control extracted both visible lines, proving the truncation was in stale/missing-Length terminator repair rather than text extraction.

## Change

- `contentStreamEndstreamTerminatorOffset()` now tracks `BT`/`ET` text-object state while scanning unfiltered content streams for a repaired `endstream` terminator.
- Repaired `endstream` detection and `BI` inline-image skipping now run only outside active text objects, matching the normal content tokenizer boundary.
- `BX`/`EX` compatibility depth tracking is likewise ignored while inside a text object.

## Focused Evidence

Target command:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTextObjectStreamBoundaryCurrentBaseTest.php
```

Current result:

```text
PASS keeps text-object BI tokens from truncating stale-Length stream repair before WordPress import
1 test files, 28 assertions, 0 failures
```

The test covers missing `/Length`, short stale `/Length`, overdeclared stale `/Length`, and exact declared length control.

## WordPress Smoke

Target command:

```bash
php lanes/markerpdf/examples/wordpress-pdf-inline-image-text-object-stream-boundary-currentbase.php
```

Expected flags:

- `missing_length_text_object_bi_survives_fake_endstream=true`
- `short_length_text_object_bi_survives_fake_endstream=true`
- `overdeclared_length_text_object_bi_survives_fake_endstream=true`
- fake `endstream` operator text excluded from paragraphs
- `executes_python_or_models=false`
- `executes_external_pdf_tools=false`

Current result: exits `0` and emits the expected WordPress paragraph text for all missing, short stale, and overdeclared stale stream-length variants.

## Non-Overlap

This does not repeat accepted inline-image payload decode, tight `ID`/`EI`, comment whitespace, filter metadata, image XObject review, or AcroForm indirect-reference operand boundary work. It is limited to stale/missing-Length content-stream repair when inline-image-looking tokens occur inside a text object.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP tokenizer, literal/hex/array/dictionary skipping, inline-image boundary scanner, and text extraction APIs already present under `lanes/markerpdf/src`.
