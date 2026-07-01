# XML/HTML DOM inert boolean review slice

## Scope

- Added reviewer-facing metadata for the HTML `inert` global attribute in `XmlHtmlDom`.
- Preserved native DOM behavior: any present `inert` attribute still marks the element and descendants as effectively inert.
- Added conformance hints for boolean-attribute values so review handoff can distinguish empty/name-valued `inert` from non-conforming values such as `inert="false"` or `inert="soft-lock"`.

## Validation

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomInertAttributeReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomInertAttributeReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
- `jq empty lanes/pandoc/lane-status.json`
- `git diff --check origin/main...HEAD -- lanes/pandoc`

No external Pandoc, office-suite, TeX/browser-engine, unzip/zip, Jupyter, Node, or external-validator tooling was used.
