# HTML Fragment Comment Issue Rollups

`plib-xs2xh` adds aggregate issue rollups for HTML fragment comment provenance.

- `XmlHtmlDom::summarizeHtmlFragmentComments()` now reports total comment issue instances, per-code counts, and the comment indexes associated with each issue code.
- Existing per-comment records remain unchanged, including raw/safe comment text provenance and serialization-boundary diagnostics.
- `XmlHtmlDomTest` covers declaration-like and unsafe-boundary comment issue counts and deterministic index buckets.

Validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/XmlHtmlDomFragmentTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`

No external Pandoc, office suite, TeX/browser engine, Typst, Jupyter, Node tooling, zip/unzip tools, validators, or live services were invoked.
