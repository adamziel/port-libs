# Pandoc XML/HTML5 DOM Fragment Boolean Attributes - 2026-07-01

Slice: `pandoc-xml-html5-dom-fragment-boolean-attributes-20260701`

## Scope

`XmlHtmlDomFragment` already normalizes legacy HTML boolean attributes when parsing compact HTML review fragments, but the fragment surface lagged the broader `XmlHtmlDom` HTML5 boolean set for newer attributes such as `inert`, media disable flags, declarative shadow-root flags, and `typemustmatch`.

This slice keeps the behavior bounded to native PHP fragment parsing and serialization:

- `XmlHtmlDomFragment` now treats `inert`, `disablepictureinpicture`, `disableremoteplayback`, `shadowrootclonable`, `shadowrootcustomelementregistry`, `shadowrootdelegatesfocus`, `shadowrootserializable`, and `typemustmatch` as boolean attributes.
- Compact HTML serialization emits those attributes without valued fallbacks, while preserving ordinary valued attributes such as `data-review` and `src`.
- The existing fragment sanitizer, active-element dropping, URL filtering, and XML fragment behavior are unchanged.

No Pandoc binary, browser, HTML validator, network service, office suite, or external parser was executed.

## Evidence

- `php -l lanes/pandoc/src/XmlHtmlDomFragment.php` - no syntax errors.
- `php -l lanes/pandoc/tests/XmlHtmlDomFragmentTest.php` - no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomFragmentTest.php` - 1 file, 65 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomFragment*.php` - 5 files, 143 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*.php` - 81 files, 9,879 assertions, 0 failures.

## Follow-Up

Continue XML/HTML DOM fragment work in non-overlapping slices, especially remaining HTML5 fragment tree-repair and attribute-review edges. Full upstream HTML parser parity remains outside this bounded fragment serializer closure.
