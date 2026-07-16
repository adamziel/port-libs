# XML/HTML DOM Attribute Declaration Literal Recovery

Hook: `plib-70m03`, Pandoc XML/HTML5 DOM core blocker slice, 2026-06-17.

## Scope

- Kept the change under `lanes/pandoc`.
- Added bounded native PHP recovery for declaration-looking text inside closed quoted HTML tag attributes.
- `Html5Dom` and `XmlHtmlDom` now mask closed quoted tag-attribute values for DTD/PI preflight scans while still scanning live markup declarations outside attributes.
- The slice preserves literal `<!DOCTYPE ...>`, `<!ENTITY ...>`, and `<?review ...?>` attribute text as escaped DOM attribute data, and keeps live declarations plus unterminated attribute PI-looking input rejected before parser repair.

## Accounting

- Adds `mappedXmlHtmlDomAttributeDeclarationLiteralRecoveryCases = 1`.
- Adds `xmlHtmlDomAttributeDeclarationLiteralRecoveryAssertions = 27`.
- Moves XML/HTML DOM core inventory from 15 mapped cases / 297 assertions to 16 mapped cases / 324 assertions.
- Moves `phpPass` from 16937 to 16938; `phpFail` remains 0.
- Moves manifest mapped upstream cases from 16523 to 16524.
- Moves root mapped inventory from 16492 to 16493.
- Moves benchmark denominator mapped cases from 3661 to 3662.

## Verification

- `php -l lanes/pandoc/src/Html5Dom.php`
- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomAttributeDeclarationLiteralRecoveryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomAttributeDeclarationLiteralRecoveryTest.php`
  - 1 file, 27 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/XmlHtmlDomFragmentTest.php lanes/pandoc/tests/XmlHtmlDomClosedCommentDeclarationRecoveryTest.php lanes/pandoc/tests/XmlHtmlDomAttributeDeclarationLiteralRecoveryTest.php`
  - 7 files, 7945 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 236 files, 172772 assertions, 0 failures.

No Pandoc, cmark/commonmark runners, Cabal/Haskell runners, browser renderers, Node tooling, external validators, online services, live provider tests, or live-service provider tests were invoked.
