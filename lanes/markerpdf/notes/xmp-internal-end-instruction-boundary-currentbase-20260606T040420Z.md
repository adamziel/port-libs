# markerpdf-xmp-metadata-boundary-current-base-20260606T040420Z

## Scope

- Lane: `markerpdf`
- Base accepted HEAD: `aacd91f0c62d29521f76ed00e1ea16c126d3b35d`
- Behavior cluster: native no-GPU XMP packet boundary handling for terminal
  `xpacket end` processing instructions that appear inside the active XMP XML
  root.

## Source Truth

Upstream `sddai/markerPDF` at manifest commit
`da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable PDF text
extraction separate from document metadata import. In this native PHP lane,
Catalog `/Metadata` XMP streams are promoted only after the active packet root
is bounded and parsed. XMP packet terminators close the outer packet, but a
processing instruction token inside the XML root is XML content and must not
truncate the packet before WordPress metadata extraction or rejected-stream
review summaries.

## Patch

- `PdfMetadataExtractor::xmpPacketContentCandidates()` now skips terminal
  `xpacket end` processing instructions that are inside a bounded `xmpmeta` or
  `RDF` XML root.
- The same root-aware helper is used for nested `xpacket begin` detection, so
  packet restarts still happen only for begin markers outside the active root.
- Added focused accepted-XMP and rejected-stream summary tests.
- Added a WordPress smoke proving the current packet wins, trailing decoys are
  excluded, visible text excludes XMP metadata, and no Python/models/external
  PDF tools execute.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpInternalEndInstructionBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL ignores nested terminal xpacket end instructions inside the active XMP root
Expected: 'Current Internal End XMP Title'
Actual: 'Trailing Internal End Decoy XMP Title'
FAIL summarizes rejected XML streams from the active root around internal xpacket ends
Expected: '2026-06-06T04:05:20Z'
Actual: '2026-06-06T04:59:59Z'
1 test files, 19 assertions, 2 failures
```

## Verification

Focused slice:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpInternalEndInstructionBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS ignores nested terminal xpacket end instructions inside the active XMP root
PASS summarizes rejected XML streams from the active root around internal xpacket ends
1 test files, 39 assertions, 0 failures
```

Adjacent metadata/XMP family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php
Focused test run: 37 selected test files (root lock skipped)
37 test files, 2466 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xmp-internal-end-boundary-currentbase.php
```

Result: passed. The smoke emits `title_from_current_packet=true`,
`packet_boundary_applied=true`, `internal_end_instruction_ignored=true`,
`trailing_decoy_excluded=true`, `visible_text_excludes_xmp=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax checks:

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php`
- `php -l lanes/markerpdf/tests/PdfMetadataXmpInternalEndInstructionBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-xmp-internal-end-boundary-currentbase.php`

Result: no syntax errors.

Required whitespace check:

`git diff --check -- lanes/markerpdf`

Result: passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2378 -> 2380` from two new focused TestRunner PASS cases.
- `wordpressScenarios`: `2035 -> 2036` from the new WordPress XMP internal
  terminal instruction smoke.

## Non-Overlap

This does not repeat accepted catalog `/Metadata` null/direct/unresolved/
unreadable boundaries, non-metadata XML stream rejection, packet padding,
complete-packet fallback, unpaired-begin handling, internal `xpacket begin`
handling, non-terminal instruction filtering, DTD/entity rejection,
CDATA/comment root selection, namespace wrapper filtering, self-closing/empty
roots, compact RDF attributes, text subject splitting, typed-node parsing,
language alternatives, qualified/nested values, FileSpec XMP generation
exactness, encrypted metadata source priority, OutputIntent/PieceInfo/name-tree
metadata review, xref repair, CMap/font/text extraction, images, annotations,
forms, OCR, or model execution.

The bounded behavior is only terminal `xpacket end` processing instructions
inside an active XML root before document XMP promotion or rejected-stream
summary.

## Dependency Closure

No new support component is needed. This slice reuses native PHP PDF stream
decoding, catalog metadata boundary validation, DOM-based XMP parsing, trailer
Info fallback, text extraction, and the existing WordPress smoke pattern.
GPU/model/OCR/PDFium/Python execution remains intentionally out of scope under
the current no-GPU markerPDF directive.
