# Pandoc DOCX Section Properties Slice

Date: 2026-06-12T23:40:08Z
Bead: plib-achtq
Base: origin/main 3af5dde50f

## Scope

DocxOpenXmlReader now preserves `w:sectPr` review metadata in package provenance and document-level DOCX attrs without changing body AST output. The slice covers section type, header/footer reference rows and diagnostics, page size, page margins, column layout, page numbering, document grid, title-page state, landscape counts, and package summary counters.

## Ship Readiness

Verdict: not yet shippable as full Pandoc DOCX reader parity.

| Check | Result |
| --- | --- |
| Upstream denominator | 35 accepted static DOCX/OpenXML inventory rows |
| Local passing evidence | 92 focused native PHP DOCX/OpenXML cases |
| Ratio | 262.9% of the coarse upstream denominator |
| New coverage | One `w:sectPr` section-property slice with 41 focused assertions |
| Remaining gaps | Full direct WordprocessingML semantics, including broader field, content-control, revision, list, table, and package edge cases; writer parity remains out of this input-format burn-down |
| External validators | Not invoked |

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php` passed.
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` passed: 1 test file, 2342 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 test files, 73857 assertions, 0 failures.
