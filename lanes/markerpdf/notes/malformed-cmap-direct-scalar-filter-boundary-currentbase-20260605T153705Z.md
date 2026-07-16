# markerPDF malformed CMap direct scalar filter boundary current-base

Session: `port-dev-markerpdf-malformed-cmap-20260605T153705Z`

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T153705Z`

Base accepted HEAD: `5705765d7caee4ceeee159549c5c3ae09b15aa28`

## Scope

This patch stays inside the native no-GPU markerPDF searchable-PDF parser and
WordPress import review path. It does not run OCR, Surya, Texify, Torch,
PDFium, pypdfium, Python helpers, model workers, or external PDF tools.

The bounded behavior is a CMap stream dictionary that declares a scalar
`/Filter /FlateDecode` and then adds an unkeyed non-name operand before
`/Length`, such as `null`, `[ /ASCIIHexDecode ]`, or a literal string. The
native fallback must not silently ignore the trailing operand and decode a
leaking ToUnicode CMap before WordPress paragraph extraction.

## Source Truth

Pinned upstream markerPDF routes searchable PDF text through pdftext/PDFium font
and CMap decoding before Markdown generation. Under the current no-GPU lane
scope, the PHP parser owns the safety boundary for malformed native CMap stream
filter operands before text is imported into WordPress paragraphs.

## Behavior

`PdfTextExtractor` now classifies direct scalar stream filters with unkeyed
trailing operands via a shared direct-scalar extra-operand classifier. It keeps
the older `extra_filter_name_operand` and `extra_filter_name` metadata for a
second known decoder name after a scalar filter, while adding
`extra_filter_operand`, `extra_filter_operand_type`, and
`extra_filter_operand_preview` for non-name operands.

For CMap streams, `/Filter /FlateDecode null`,
`/Filter /FlateDecode [ /ASCIIHexDecode ]`, and
`/Filter /FlateDecode (literal extra filter operand)` now produce:

- `filters=[]`
- `filter_resolution_failed=true`
- `invalid_filter_operand_count=1`
- `malformed_filter_operand_count=1`
- `filter_operand_policy=reject_malformed_filter_operands`
- `decoded_cmap_count=0`

The WordPress-visible text remains the safe content stream text, while the
decoded CMap leak text and CMap name remain excluded.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects scalar CMap Filter followed by extra non-name operands before current-base text extraction
Values are not identical
Expected: array (
  0 => 'Direct Null Safe Import',
)
Actual: array (
  0 => 'Direct Null CMap Leakirect Null Safe Import',
)

1 test files, 1211 assertions, 1 failures
```

## Verification

Focused test after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects scalar CMap Filter followed by extra non-name operands before current-base text extraction

1 test files, 1342 assertions, 0 failures
```

Adjacent CMap/stream filter subset:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterEodBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterLengthOwnerReviewCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 1710 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-filter-boundary-currentbase.php
```

The smoke emits `direct_extra_scalar_filter_operands` for `null`, `array`, and
`literal` with `decoded_cmap_count=0`,
`filter_operand_policy=reject_malformed_filter_operands`,
`malformed_filter_operand_count=1`, the expected
`extra_filter_operand_type`, `payload_excluded=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Static checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-filter-boundary-currentbase.php
php -r '$data = json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status.json valid\n";'
git diff --check -- lanes/markerpdf
```

All completed with exit code `0` in this worktree.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2039 -> 2040`
- `wordpressScenarios`: `1764 -> 1765`
- Focused PASS case delta: `+1`
- Focused assertion delta over the previous focused run: `1210 -> 1342`

## Dependency Closure

No new support component is needed. This slice reuses the native PDF dictionary
scanner, direct stream filter resolver, CMap stream owner review, CMap decoder,
and WordPress smoke renderer.

Full upstream model parity remains intentionally out of scope under the current
no-GPU markerPDF directive and remains gated by pdftext, pypdfium2/PDFium,
Surya/Torch, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads,
and external OCR/rendering helpers.

## Non-Overlap

This does not repeat accepted malformed CMap filter array operands, literal
operands inside filter arrays, indirect literal/dictionary operands,
current-generation stale filter owner selection, invalid DecodeParms parameter
rejection, null-filter DecodeParms alignment, unsupported or escaped filter
names, identity/private Crypt filter handling, post-`endcmap` operator
exclusion, nested CMap array exclusion, explicit filter end-marker surplus
rejection, or filter decode-error metadata.

The new boundary is specifically a direct scalar CMap `/Filter` name followed by
an unkeyed non-name operand before the next dictionary key.

## Next Task

Continue with non-overlapping native no-GPU markerPDF parser behavior around
fonts, CMaps, stream filters, xref repair, metadata, annotations/forms, page
geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
