# Link Annotation Remote GoToR Dictionary Boundary Current Base

Slice: `markerpdf-annotations-links-boundary-current-base-20260608T164657Z`

Accepted base: `7b975a5be57b960d0ba9cd45d89aa4a460b49ac9`

## Behavior

Remote `GoToR` link annotation destination dictionaries now fail closed when
they contain duplicate `/D` or `/S` boundary keys. This matches the existing
local-destination dictionary boundary and prevents malformed remote destination
dictionaries from donating WordPress link metadata. Valid remote destination
dictionaries and safe URI annotations continue to promote normally.

The slice stays in the native no-GPU markerPDF scope: searchable PDF parser
and converter behavior only, with no OCR, Python model, raster, JavaScript, PDF
action, or external PDF tool execution.

## Evidence

Red-first focused run before the source fix:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationRemoteGoToRDictionaryBoundaryCurrentBaseTest.php
```

Result: `1 test files, 3 assertions, 1 failures`.

Focused run after the fix:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationRemoteGoToRDictionaryBoundaryCurrentBaseTest.php
```

Result: `1 test files, 31 assertions, 0 failures`.

Nearby GoToR/action family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationRemoteGoToRBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationRemoteGoToRViewBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationActionOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationFileSpecDuplicateKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationDestinationOperandBoundaryCurrentBaseTest.php
```

Result: `5 test files, 197 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-link-annotation-remote-gotor-dictionary-boundary-currentbase.php --self-test
```

Result: exit 0 with `promoted_link_objects=[7,9]`,
`duplicate_destination_promoted=false`, `safe_uri_promoted=true`,
`visible_text_imported=true`, `annotation_payload_text_visible=false`,
`executes_pdf_actions=false`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted link annotation coverage for primary `/A`
promotion, `/PA` previous URI review, URI controls, duplicate Filespec keys,
remote GoToR view arrays, destination operand tails, hidden annotations, widget
links, named destinations, xref repair, or annotation appearance/action review.
It narrows to remote GoToR destination dictionaries with duplicate boundary
keys.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PDF
action parser, dictionary duplicate-key review, link annotation extractor, and
WordPress Markdown post-processing paths. The remaining model/OCR gaps remain
out of scope under the current no-GPU markerPDF directive.
