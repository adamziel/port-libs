# Outline Duplicate Structure Keys Current Base

Slice: `markerpdf-outline-metadata-boundary-current-base-20260606T054418Z`

Base: `e4ea169e4e976809e607e8fc8164a335a8929b16`

## Source Truth

Upstream `sddai/markerPDF` delegates bookmark/outline discovery to PDF parser dependencies before OCR/model execution. Under the current no-GPU markerPDF scope, this slice ports the native PDF dictionary boundary: duplicate outline item dictionary keys are parser review metadata, while the selected top-level operands still drive TOC/navigation import.

## Behavior

The native PHP outline metadata extractor now records duplicate top-level outline item keys beyond `/Title`, `/Dest`, and `/A`: `/Count`, `/First`, `/Last`, `/Parent`, `/Prev`, `/Next`, `/F`, `/C`, and `/SE`.

The focused fixture proves duplicate `/Count`, `/First`, `/Last`, `/Prev`, `/F`, and `/C` are surfaced as `duplicate_key_review`, while last top-level operands select the valid child object, collapsed count state, previous-sibling link, bold/color styling, TOC/navigation rows, and WordPress visible text. A stale duplicate child/action branch remains excluded from document metadata and visible paragraphs.

This intentionally does not change duplicate `/Metadata` stream review behavior.

## Red First

Before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataDuplicateStructureKeyBoundaryCurrentBaseTest.php`

Result: `1 test files, 20 assertions, 1 failures`

Failing assertion: `duplicate_item_key_count` was missing instead of `2`.

## Verification

After the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataDuplicateStructureKeyBoundaryCurrentBaseTest.php`

Result: `1 test files, 52 assertions, 0 failures`

Adjacent duplicate outline metadata checks:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataDuplicateNavKeyBoundaryCurrentBaseTest.php`

Result: `1 test files, 57 assertions, 0 failures`

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataDuplicateKeyBoundaryCurrentBaseTest.php`

Result: `1 test files, 47 assertions, 0 failures`

Outline metadata family:

`php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfOutlineMetadata.*Test\.php$' | sort) lanes/markerpdf/tests/PdfMetadataExtractorTest.php`

Result: `41 test files, 2467 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-outline-duplicate-structure-keys-currentbase.php`

Result: exit `0`; emitted `duplicate_item_key_count=2`, `duplicate_item_keys=["Count","First","Last","Prev","F","C"]`, `selected_parent_child_object=7`, `selected_parent_count=-1`, `selected_style_flags=2`, `selected_text_color_hex="#004080"`, `stale_child_excluded=true`, `visible_text_excludes_outline_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax checks:

`php -l lanes/markerpdf/src/PdfMetadataExtractor.php`

`php -l lanes/markerpdf/tests/PdfOutlineMetadataDuplicateStructureKeyBoundaryCurrentBaseTest.php`

`php -l lanes/markerpdf/examples/wordpress-pdf-outline-duplicate-structure-keys-currentbase.php`

Result: no syntax errors.

JSON validation:

`php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`

Result: both JSON files valid.

Whitespace:

`git diff --check -- lanes/markerpdf`

Result: clean.

Full root harness: not run - isolated micro-slice.

## Non-Overlap

This slice avoids accepted duplicate `/Title`, `/Dest`, `/A`, duplicate `/Metadata` stream review, color operand cardinality, zero-count child gating, parent/prev/last traversal, action-chain, name-tree, xref, stream, image, AcroForm, XMP, and OCR/model scopes. It only adds duplicate structure/style key provenance for outline items.

## Dependency Closure

No new support component is needed. The patch reuses the native PDF tokenizer, dictionary parser, outline metadata extractor, destination resolver, navigation metadata, text extraction, and WordPress example smoke paths. GPU/model execution, OCR, and external PDF tools remain intentionally out of scope.
