# XML/HTML DOM Fragment Foreign CDATA Recovery

This slice keeps `XmlHtmlDomFragment::parseHtml()` on the same bounded source
normalization path as the shared HTML loaders for foreign-content CDATA text.

- `XmlHtmlDomFragment::parseHtml()` now runs HTML input through
  `XmlHtmlDom::protectHtmlRcdataElements()` before libxml parsing.
- The focused fixture covers SVG and MathML CDATA containing doctype-, entity-,
  and processing-instruction-looking text, then verifies live declarations
  outside closed CDATA sections still reject.
- The implementation remains native PHP only; no Pandoc, Cabal/Haskell runners,
  browser renderers, external validators, online services, or live providers are
  invoked.

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDomFragment.php lanes/pandoc/tests/XmlHtmlDomFragmentForeignCdataRecoveryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomFragmentForeignCdataRecoveryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/XmlHtmlDomFragmentTest.php lanes/pandoc/tests/XmlHtmlDomClosedCommentDeclarationRecoveryTest.php lanes/pandoc/tests/XmlHtmlDomAttributeDeclarationLiteralRecoveryTest.php lanes/pandoc/tests/XmlHtmlDomCdataDeclarationRecoveryTest.php lanes/pandoc/tests/XmlHtmlDomFragmentForeignCdataRecoveryTest.php lanes/pandoc/tests/XmlHtmlDomDraggableAutoTest.php lanes/pandoc/tests/XmlHtmlDomDirectionValidityTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`
