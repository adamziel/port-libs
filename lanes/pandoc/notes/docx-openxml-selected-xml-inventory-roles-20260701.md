# DOCX selected XML inventory roles

`DocxOpenXmlReader` now assigns semantic package-inventory roles to explicit
DOCX document relationship targets for selected XML companion parts:

- `styles` relationships add a `styles` inventory role.
- `numbering` relationships add a `numbering` inventory role.
- `theme` relationships add a `theme` inventory role.

The change is relationship-scoped. Conventional fallback parts such as
`word/styles.xml` and `word/numbering.xml` remain generic `package-part` rows
when no corresponding relationship is declared.

Review rollups now carry these roles through `packageProvenance.parts`,
`packageProvenance.summary.roleCounts`, and
`packageProvenance.relationshipTypes[*].targetRoleCounts`, matching the existing
`styles-with-effects`, settings, glossary, note, and package-artifact inventory
role behavior.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackageInventoryRolesTest.php`
