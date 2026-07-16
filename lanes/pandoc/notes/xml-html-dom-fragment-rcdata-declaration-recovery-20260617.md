# XML/HTML DOM Fragment RCDATA Declaration Recovery

This slice keeps the HTML fragment object preflight on the same safe-source path
as the shared HTML5 fragment loader for declaration-looking text inside protected
raw/RCDATA-like content.

- `Html5DomFragment::assertSafeHtmlSource()` and
  `XmlHtmlDomFragment::assertSafeHtmlSource()` now run the fragment through
  `XmlHtmlDom::protectHtmlRcdataElements()` before declaration scanning.
- The focused fixture covers declaration-looking text in script, style,
  template, and iframe fragment content, then verifies live doctype/entity/PI
  declarations outside protected content still reject before parser repair.
- The implementation remains native PHP only; no Pandoc, Cabal/Haskell runners,
  browser renderers, external validators, online services, or live providers are
  invoked.

Verification:

- `php -l lanes/pandoc/src/Html5DomFragment.php lanes/pandoc/src/XmlHtmlDomFragment.php lanes/pandoc/tests/XmlHtmlDomFragmentRcdataDeclarationRecoveryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomFragmentRcdataDeclarationRecoveryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/XmlHtmlDomFragmentTest.php lanes/pandoc/tests/XmlHtmlDomClosedCommentDeclarationRecoveryTest.php lanes/pandoc/tests/XmlHtmlDomAttributeDeclarationLiteralRecoveryTest.php lanes/pandoc/tests/XmlHtmlDomCdataDeclarationRecoveryTest.php lanes/pandoc/tests/XmlHtmlDomFragmentRcdataDeclarationRecoveryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`
