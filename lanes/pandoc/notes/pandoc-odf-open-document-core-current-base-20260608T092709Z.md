# pandoc-odf-open-document-core-current-base-20260608T092709Z

## Behavior

Added bounded native ODF/OpenDocument handling for `text:hidden-paragraph` fields in ODT content XML. The reader now sends the element through the existing ODF field-span path, preserving:

- `fieldType` as `hidden-paragraph`
- `text:condition` in field metadata and `data-odf-field-condition`
- `text:string-value` as fallback visible reviewer text and `data-odf-field-string-value`
- Markdown bracketed-span output and WordPress `odf-field-hidden-paragraph` block output
- content `fieldCount` accounting in the import report

This is intentionally limited to ODT manifest/content/styles/meta XML mapping under `lanes/pandoc/**`. It does not shell out to Pandoc, Cabal, Haskell runners, Word, LibreOffice, zip/unzip, external template engines, external converters, online services, live provider tests, or live-service provider tests.

## Source Truth

No hydrated Pandoc upstream checkout was present in `/home/claude/port-libs/.upstream-cache/pandoc` for direct runner comparison. This slice uses the accepted lane ODF support-library contract as source truth and extends the existing `conditional-text` / `hidden-text` field handoff behavior to the adjacent OpenDocument `text:hidden-paragraph` field shape.

## Verification

- Baseline focused ODF test before edits: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - Result: `1 test files, 1840 assertions, 0 failures`
- Red-first probe after adding fixture expectations but before source support: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - Result: `1 test files, 1817 assertions, 1 failures`
  - Failure: hidden paragraph fallback text was absent from the paragraph text and the expected field child did not exist.
- Final focused ODF test after source support: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - Result: `1 test files, 1846 assertions, 0 failures`
- WordPress example smoke: `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - Result: `odf open document handoff self-test ok`

Additional final lint and whitespace checks were run after this note and are recorded in `lane-status.json` and the final worker response.

## Status Delta

- `phpPass`: +1 focused PASS case
- Focused assertions: +6 in `OdfReaderTest.php`
- `benchmarkDenominator.mapped`: +1
- `odfOpenDocumentCoreCases`: 12 -> 13
- `mappedOdfOpenDocumentCoreCases`: 12 -> 13
- `odfOpenDocumentCoreAssertions`: 276 -> 282

## Dependency Closure

No new native PHP support component is needed. This slice reuses the existing ODF package reader, XML field metadata extraction, AST span model, Markdown writer, and WordPress block writer. Full upstream-runner parity remains gated on a hydrated pinned Pandoc checkout and reviewed non-mutating Cabal plan.

## Non-Overlap

This does not repeat the accepted ODF `text:drop-down`, `text:conditional-text`, or `text:hidden-text` slices. Follow-up ODF work should stay on a distinct source field family such as DDE/execute-macro audit fields, or broader ODF declaration/reporting behavior, without executing external converters.

Root harness status: not run - isolated micro-slice.
