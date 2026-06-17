# XML/HTML DOM Fragment Attribute Declaration Recovery

Date: 2026-06-17
Bead: plib-ubz3o
Base: origin/main a1658d1663

This slice hardens the AST fragment HTML preflight path in `XmlHtmlDomFragment` and `Html5DomFragment`. The declaration scanner now masks closed quoted tag attribute values before checking for doctype, DTD, entity, and processing-instruction syntax, matching the safer DOM-loader behavior for declaration-looking text that is data, not markup.

Scope is deliberately narrow:

- Preserve declaration-looking text in quoted HTML attributes through AST fragment parsing and escaped serialization.
- Continue rejecting live declarations and processing instructions outside closed attributes.
- Continue rejecting unterminated quoted-attribute source that contains processing-instruction-looking text.
- Do not change raw-text/RCDATA protection or any non-HTML format behavior.

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDomFragment.php`
- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomFragmentAttributeDeclarationLiteralRecoveryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomFragmentAttributeDeclarationLiteralRecoveryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/XmlHtmlDomFragmentTest.php lanes/pandoc/tests/XmlHtmlDomClosedCommentDeclarationRecoveryTest.php lanes/pandoc/tests/XmlHtmlDomAttributeDeclarationLiteralRecoveryTest.php lanes/pandoc/tests/XmlHtmlDomCdataDeclarationRecoveryTest.php lanes/pandoc/tests/XmlHtmlDomAutocapitalizeInheritanceTest.php lanes/pandoc/tests/XmlHtmlDomDraggableAutoTest.php lanes/pandoc/tests/XmlHtmlDomDirectionValidityTest.php lanes/pandoc/tests/XmlHtmlDomFragmentAttributeDeclarationLiteralRecoveryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`
