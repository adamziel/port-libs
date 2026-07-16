# pandoc-docx-openxml-core-current-base-20260608T100637Z

Accepted base: `6bc71cbbbe736a9858bd60708161d8103d8ce185`

## Scope

This slice ports a bounded DOCX/OpenXML reviewer-range behavior into the native PHP reader: `w:proofErr` spelling/grammar ranges and `w:permStart`/`w:permEnd` editing-permission ranges now survive paragraph boundaries. The emitted AST remains paragraph-scoped, so Markdown and WordPress output get one reviewer span per paragraph segment while retaining the original range metadata and close marker where present.

Source truth used locally:

- Existing `DocxReader` same-paragraph proof-error and permission range behavior.
- Existing cross-paragraph comment-range state handling in the accepted DOCX reader.
- Prior lane note follow-up identifying cross-paragraph proof-error and permission ranges as the remaining gap.

No upstream Pandoc checkout was available under `/home/claude/port-libs/.upstream-cache/pandoc`, so no Haskell source or runner was executed.

## Evidence

- Rework note check: no `port-pandoc-*.needs-lane-rework.md` file existed for this lane.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - `1 test files, 2482 assertions, 0 failures`
- Red-first probe after adding the cross-paragraph fixture:
  - `1 test files, 2494 assertions, 1 failures`
  - Failure confirmed proof/permission range state was closed at paragraph end.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - `1 test files, 2526 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - `docx body handoff self-test ok`

Focused delta: `+1` PHP PASS case and `+44` focused assertions.

## Dependency Closure

No new support component is needed. The implementation reuses existing ZIP/OPC package fixtures, WordprocessingML DOM traversal, reviewer span AST nodes, Markdown writer attributes, and WordPress block writer attributes.

Excluded by slice policy: Pandoc, Word, LibreOffice, zip/unzip, external office tools, Cabal/Haskell runners, online services, live provider tests, and live-service provider tests.

## Non-Overlap

This does not repeat the accepted same-paragraph proof/permission range slice, tracked formatting-change metadata, deleted OMML math revision handling, structured document tag form-control metadata, or cross-paragraph comment range handling. It adds the specific cross-paragraph proof-error and editing-permission state lifetime behavior.

## Follow-Up

Reasonable next DOCX/OpenXML follow-up: cross-container reviewer ranges, section-linked header/footer edge cases, or additional field/revision metadata that remains native PHP and external-tool free.
