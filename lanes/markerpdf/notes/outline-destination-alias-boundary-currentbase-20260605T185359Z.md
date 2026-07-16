# markerpdf outline destination alias boundary current-base

Slice: `markerpdf-outline-metadata-boundary-current-base-20260605T185359Z`

Accepted base: `ac32afd78423ca66d05dc814198315d888cb5712`

## Behavior

Native no-GPU PDF outline metadata now preserves bounded destination name alias review metadata. When an outline item points at a name-tree destination that aliases another destination name before reaching an explicit page target, document outline metadata records the declared destination, alias chain, and terminal target name. Cyclic destination aliases remain unresolved review metadata and do not become TOC/navigation rows or visible WordPress paragraph text.

The same outline resolution boundary now consistently admits name-tree destinations that resolve to local `/GoTo` action dictionaries when building TOC/page-view/navigation targets. Alias review still uses the plain destination map so action dictionaries are not reported as scalar alias chains, and non-local action dictionaries remain review-only.

This maps the native PDF parser boundary for searchable PDFs only. It does not run OCR, Surya, Texify, Torch, pypdfium, Python model workers, or external PDF tools.

## Evidence

Red-first focused run before source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataDestinationAliasBoundaryCurrentBaseTest.php`

Result: `1 test files, 21 assertions, 2 failures`

Focused run after source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataDestinationAliasBoundaryCurrentBaseTest.php`

Result: `1 test files, 64 assertions, 0 failures`

Focused outline family guard after the action-destination resolver boundary fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutline*CurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationAliasBoundaryCurrentBaseTest.php`

Result: `57 test files, 2623 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-outline-destination-alias-boundary-currentbase.php`

Result: emits `markerpdf-outline-destination-alias-boundary-currentbase` with `alias_chain=["AliasStart","FinalTarget"]`, `cycle_unresolved_reason=destination_alias_cycle`, `navigation_cycle_omitted=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is required. This reuses the existing native PHP object parser, name-tree destination map, and outline/navigation metadata extractors.

## Non-Overlap

This avoids the accepted outline Last/Prev/Parent/root Count zero, destination view operand, action-chain, structure-element `/SE`, name-tree limits/action, article-thread, OpenAction, EOF/xref ownership, and remote GoToE/GoToR review clusters. The new behavior is limited to destination-name alias chains/cycles plus local `/GoTo` action-destination target resolution needed to keep current outline metadata/navigation review aligned; remote and threaded action destinations stay review-only.
