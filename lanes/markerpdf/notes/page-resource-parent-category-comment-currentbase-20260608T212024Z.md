# markerPDF page resource parent/category comment current-base

Slice: `markerpdf-page-resource-inheritance-current-base-20260608T212024Z`
Session: `port-dev-markerpdf-resource-inherit-20260608T212024Z`
Base accepted HEAD: `28fa19ccf3ea58dcc60033aba187e21c553c5024`

## Source Truth

Upstream markerPDF routes searchable-PDF text extraction through native page-tree
and resource lookup before OCR/model fallback. In PDF syntax, comments are
whitespace, including between the object number, generation number, and `R`
token of an indirect reference. This slice stays inside native no-GPU parser
scope and does not run OCR, Surya, Texify, Torch, browser rendering, online
services, or external PDF tools.

## Behavior

Inherited page resource lookup now uses the shared comment-aware indirect
reference tokenizer for the remaining page-tree `/Parent` and resource-category
reference paths instead of local whitespace-only regex matching. The focused
fixture puts the page `/Parent` reference and inherited `/Font`, `/XObject`, and
`/Properties` category references across PDF comment boundaries. Text extraction
resolves the parent page tree, applies inherited fonts, expands the inherited
Form XObject with the same resource owner, and replaces the raw glyph with
inherited `/ActualText`. Page-boundary metadata reports the same inherited
resource owner and category names.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceParentCategoryCommentCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS treats comments as whitespace in page Parent and inherited resource category references

1 test files, 18 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfPageResource.*CurrentBaseTest\.php$' | sort)
Focused test run: 56 selected test files (root lock skipped)
56 test files, 1244 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 629 assertions, 0 failures
```

Syntax checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfPageResourceParentCategoryCommentCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-resource-parent-category-comment-currentbase.php
```

All reported no syntax errors.

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-parent-category-comment-currentbase.php
```

The smoke exits 0 and emits three WordPress paragraph blocks for inherited font
text, inherited ActualText, and inherited Form XObject text. It reports
`parent_comment_reference_resolved=true`, `actual_text_replaces_raw_glyph=true`,
`visible_text_excludes_resource_names=true`, `executes_python_or_models=false`,
and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

```text
git diff --check -- lanes/markerpdf
```

No whitespace errors.

## Status Delta

- Focused markerPDF PHP pass count: `3500 -> 3501`.
- Added one focused inherited page-resource PASS case with 18 assertions.
- Added one WordPress page-resource parent/category comment smoke.
- No dashboard/root coordination files were edited.

## Non-Overlap

This does not repeat accepted page `/Resources` comment-delimited references,
resource-wrapper object references, inherited resource entry references,
comment-delimited category references alone, direct dictionary tail fail-closed
behavior, indirect null inheritance, explicit empty dictionaries, generation
filtering, stream resource rejection, ProcSet metadata, optional-content
wrappers, form resource null/malformed handling, duplicate `/Resources`
precedence, xref repair, stream filters, CMaps, outlines, attachments,
annotations, forms, runtime planners, or OCR/model behavior. The bounded surface
is page-tree `/Parent` and resource-category indirect reference parsing for
inherited page resources.

## Dependency Closure

No new support component is needed. This reuses the native PHP object table,
dictionary/value scanner, comment-aware indirect-reference tokenizer, page-tree
lineage resolver, inherited page-resource dictionary resolver, marked-content
property path, page-boundary metadata extractor, and lane-local WordPress smoke
harness. Full upstream OCR/model/rendering parity remains intentionally out of
scope under the current no-GPU markerPDF direction.
