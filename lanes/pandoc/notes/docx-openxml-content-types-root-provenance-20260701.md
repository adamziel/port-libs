# DOCX OpenXML content-types root provenance

Slice: `plib-u94sz`

`DocxOpenXmlReader` now carries bounded root-element provenance for
`[Content_Types].xml` into `packageProvenance.contentTypesPart` and the compact
package summary. The handoff records whether the XML root parsed, the root
namespace/local/qualified names, root prefix, non-namespace attribute count,
namespace declaration count, namespace prefixes, and prefix-to-URI map.

This keeps content-types package review metadata aligned with the existing
selected XML/custom XML root provenance without exposing package payload bytes.

Post-rebase validation:

- Red-first `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlContentTypesRootProvenanceTest.php`: failed on missing `rootValidXml`.
- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlContentTypesRootProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlContentTypesRootProvenanceTest.php`: 1 file, 22 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlContentTypesRootProvenanceTest.php lanes/pandoc/tests/DocxOpenXmlContentTypeDefaultParameterValuesTest.php lanes/pandoc/tests/DocxOpenXmlContentTypeOverrideParameterValuesTest.php lanes/pandoc/tests/DocxOpenXmlContentTypeDefaultExtensionUsageTest.php lanes/pandoc/tests/DocxOpenXmlContentTypeOverrideCaseFoldPartsTest.php lanes/pandoc/tests/DocxOpenXmlMissingOverrideTargetsTest.php`: 6 files, 171 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`: 1 file, 12,508 assertions, 0 failures.

No external Pandoc, office suite, TeX/browser engine, Typst, Jupyter, Node,
zip/unzip, external validators, or live services were invoked.
