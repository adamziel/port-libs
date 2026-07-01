# DOCX package root resource stem lookup summaries

## Scope

`plib-tvn3d` extends the DOCX/OpenXML package-root relationship resource
handoff with summary-level lookup maps that were already computed inside
`packageRootRelationshipResources` but not surfaced in `packageProvenance`.

The summary now carries metadata-only directory-base-name and basename-stem
rollups for package-root resource targets and their nested sidecar relationship
targets. It also carries target-part lookup maps by directory, basename stem,
and case-folded basename stem. Package resource and sidecar bytes remain
blocked.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`

Focused DOCX validation passed with 1 file, 12,532 assertions, and 0
failures.
