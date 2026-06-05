# markerpdf page resource duplicate Resources current-base

Slice: `markerpdf-page-resource-inheritance-current-base-20260605T215945Z`

Base accepted HEAD: `1ed26db9ffe690d36c29112143668a173c4194ae`

## Behavior

Pinned upstream markerPDF source remains `sddai/markerPDF@da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`;
the lane manifest records searchable-PDF text extraction through parser-backed
pdftext/PDF layers before OCR and model stages. Under the current no-GPU PHP
scope, page `/Resources` selection is therefore a native parser boundary.

Native PDF page resource lookup now treats duplicate top-level page `/Resources`
keys consistently with the page review metadata path: the last top-level
resource value on the selected page/page-tree node is the effective resource
dictionary. Nested private `/Resources` dictionaries still remain decoys and do
not participate in inherited resource lookup.

This keeps searchable PDF import from leaking stale fonts, Form XObjects, or
marked-content `/Properties` when a repaired/current page object contains an
earlier stale `/Resources` entry followed by the current top-level resource
entry.

## Evidence

Red-first after adding the fixture:

`php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php`

Result: `1 test files, 194 assertions, 1 failures`; the new duplicate
`/Resources` fixture extracted stale font, ActualText, and Form XObject text
from the first resource dictionary.

After the parser change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php`

Result: `1 test files, 211 assertions, 0 failures`.

Adjacent page-resource sweep:

`php tools/run-tests.php lanes/markerpdf/tests/PdfPageResource*Test.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php`

Result: `17 test files, 734 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-page-resource-duplicate-resources-currentbase.php`

Result: emitted three Gutenberg paragraphs for the current font text, current
ActualText, and current Form XObject text, with stale resource text and nested
decoys excluded.

Syntax and diff checks:

`php -l lanes/markerpdf/src/PdfTextExtractor.php`

Result: no syntax errors.

`php -l lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php`

Result: no syntax errors.

`php -l lanes/markerpdf/examples/wordpress-pdf-page-resource-duplicate-resources-currentbase.php`

Result: no syntax errors.

`php -r '$data = json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (!is_array($data)) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status json ok\n";'`

Result: `lane-status json ok`.

`git diff --check -- lanes/markerpdf`

Result: no output, exit 0.

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF dictionary,
resource, CMap, marked-content, and Form XObject handling already present in
`PdfTextExtractor` and `PdfPagePropertyExtractor`. No OCR, model, GPU, Python,
or external PDF tool execution is required.

## Non-overlap

This does not change accepted page resource inheritance through `/Parent`,
generation-exact resource references, indirect null wrappers, escaped page tree
keys, resource entry wrappers, category stream fail-closed behavior, image
XObject review, or form-local `/Properties` scoping. It only resolves duplicate
top-level `/Resources` values on the selected resource-owner dictionary.
