# XML/HTML DOM fragment attribute declaration recovery

Issue: `plib-isebv`

Scope:
- Keep declaration scans in `Html5DomFragment` and `XmlHtmlDomFragment` from treating closed quoted HTML tag attribute values as live declarations.
- Preserve live declaration rejection for doctypes, entity declarations, processing instructions, and unterminated quoted attributes before parser repair.
- Stay within native PHP DOM handling under `lanes/pandoc`.

Verification:
- `php -l` for the touched fragment sources and focused test.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomFragmentAttributeDeclarationLiteralRecoveryTest.php`
- HTML/XML DOM cluster including existing declaration-recovery coverage.
- Full `php tools/run-tests.php lanes/pandoc/tests`.
