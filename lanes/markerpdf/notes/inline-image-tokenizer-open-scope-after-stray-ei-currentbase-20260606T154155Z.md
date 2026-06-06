# markerPDF inline image tokenizer open-scope after stray EI current base

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260606T154155Z`

Accepted base: `a2b593420feefc9d1a27952c885b02c018cd9ef8`

## Source truth

Upstream markerPDF extracts searchable PDF text from parser-backed content streams before any OCR/model fallback. Inline image tokenization therefore has to distinguish the true `BI ... ID ... EI` image terminator from later bare `EI` tokens that can appear in page content.

This slice covers a preview-only image payload that reaches the sample-floor fallback, then opens a normal PDF content scope (`q`, marked content `BMC`, or compatibility `BX`), emits visible text, and only closes the scope immediately after a later stray `EI` token. The native tokenizer must close the image at the earlier sample-floor `EI`, not at the later stray operator.

## Change

`PdfTextExtractor::skipInlineImage()` now asks the existing post-fallback content validator for still-open graphics-state, marked-content, and compatibility depths when it sees a valid closed text object. If those scopes are not closed before the candidate stray `EI`, the tokenizer accepts the earlier fallback only when the immediate tokens after the stray `EI` are the matching `Q`, `EMC`, or `EX` close operators.

The focused tokenizer test adds one grouped case for graphics-state, marked-content, and compatibility scopes. The WordPress smoke now emits:

- `preview_only_open_graphics_scope_closes_after_stray_ei_text_preserved=true`
- `preview_only_open_marked_content_scope_closes_after_stray_ei_text_preserved=true`
- `preview_only_open_compatibility_scope_closes_after_stray_ei_text_preserved=true`

## Red first

Before the production fix, a direct current-base probe returned only the surrounding text lines and swallowed the visible scoped text:

```text
q-close-after-stray: ["Before","After"]
marked-close-after-stray: ["Before","After"]
compat-close-after-stray: ["Before","After"]
```

The missing lines were `Visible Open Graphics Before Stray`, `Visible Open Marked Content Before Stray`, and `Visible Open Compatibility Before Stray`.

## Verification

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
```

Result: `No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php`

```bash
php -l lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
```

Result: `No syntax errors detected in lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php`

```bash
php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

Result: `No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php`

```bash
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf JSON OK\n";'
```

Result: `markerpdf JSON OK`

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
```

Result: `1 test files, 553 assertions, 0 failures`

```bash
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php | php -r '$html = stream_get_contents(STDIN); if (!preg_match("/markerpdf-inline-image-tokenizer-boundary-currentbase (\{.*?\}) --/s", html_entity_decode($html, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"), $m)) { fwrite(STDERR, "metadata missing\n"); exit(1); } $data = json_decode($m[1], true, 512, JSON_THROW_ON_ERROR); $keys = ["preview_only_open_graphics_scope_closes_after_stray_ei_text_preserved", "preview_only_open_marked_content_scope_closes_after_stray_ei_text_preserved", "preview_only_open_compatibility_scope_closes_after_stray_ei_text_preserved"]; foreach ($keys as $key) { if (($data[$key] ?? false) !== true) { fwrite(STDERR, "$key=false\n"); exit(1); } } if (($data["executes_python_or_models"] ?? true) !== false || ($data["executes_external_pdf_tools"] ?? true) !== false) { fwrite(STDERR, "unexpected external execution flags\n"); exit(1); } echo "inline-image open-scope smoke OK\n";'
```

Result: `inline-image open-scope smoke OK`

Root harness: not run - isolated micro-slice.

## Status delta

- `phpPass`: `2597 -> 2598`
- `wordpressScenarios`: `2199 -> 2200`
- Focused tokenizer assertions: `517 -> 553`
- Manifest `pdfInlineImageTokenizerBoundaryCurrentBaseBehaviors`: `1 -> 2`

## Non-overlap

This slice does not repeat accepted malformed `BI` preamble recovery, tight `ID`/`EI`, comment/NUL/vertical-tab separator behavior, compact dictionaries, nested dictionary decoys, text-object `BI`, DCT/JPX/JBIG2/CCITT/unsupported-filter payload boundaries, visible literal/TJ/ActualText `EI` recovery, post-terminator comment `EI`, same-line graphics prefixes, line-separated/same-line stray `EI`, externally closed `Q`/`EMC`/`EX` scopes opened before the inline image, Type3 metric operators, image decoding/review metadata, OCR, or model execution.

The bounded behavior is specifically open `q`, marked-content, and `BX` scopes that begin after the recovered inline-image boundary and close immediately after a later stray `EI`.

## Dependency closure

No new support component is needed. The patch reuses the native PHP content tokenizer, inline image dictionary scanner, text extractor, and existing WordPress smoke renderer. No Python, OCR, Surya/Texify/Torch, pypdfium, PIL, external PDF tools, live services, or GPU/model execution were run.
