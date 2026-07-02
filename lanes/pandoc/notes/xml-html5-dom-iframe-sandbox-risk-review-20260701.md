# XML/HTML5 DOM Iframe Sandbox Risk Review Slice

Session: `port_libs/polecats/1764`
Hook: `plib-d0cjd`
Micro-slice: `xml-html5-dom-iframe-sandbox-risk-review-20260701`

## Scope

This slice extends `XmlHtmlDom` iframe sandbox summaries with reviewer-facing
risk rollups for valid sandbox relaxation tokens:

- execution tokens such as `allow-scripts`
- origin/storage tokens such as `allow-same-origin` and
  `allow-storage-access-by-user-activation`
- popup and popup-escape tokens
- top-navigation tokens
- user-activation-gated tokens
- structured sandbox risk issue records and deduplicated risk issue codes

Existing sandbox token parsing, invalid-token lists, duplicate-token lists, and
`iframePolicyIssueCodes` compatibility are preserved.

## Non-Overlap

Earlier XML/HTML5 DOM work already covered iframe sandbox token validation,
duplicate-token detection, `allow` policy parsing, referrer-policy validation,
loading-state validation, `credentialless`, `csp`, and `srcdoc` summaries. This
slice does not repeat those checks. It adds metadata-only category buckets and
structured issue records for reviewers who need to distinguish benign strict
sandboxing from explicit sandbox relaxations.

No browser engine, external Pandoc runner, validator, live service, office
suite, TeX/PDF engine, Typst execution, Node tooling, or zip/unzip tool was
invoked.

## Evidence

Focused validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomIframeSandboxRiskReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomIframeSandboxRiskReviewTest.php`
- Result: `1 test files, 36 assertions, 0 failures`

Adjacent iframe/XML DOM validation:

- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomIframeSandboxRiskReviewTest.php lanes/pandoc/tests/XmlHtmlDomIframeEmbeddedPolicyReviewTest.php lanes/pandoc/tests/XmlHtmlDomIframeCredentialPolicyReviewTest.php lanes/pandoc/tests/XmlHtmlDomIframeCredentiallessCspReviewTest.php lanes/pandoc/tests/XmlHtmlDomTest.php`
- Result: `5 test files, 6529 assertions, 0 failures`

Manifest delta:

- `mappedXmlHtmlDomIframeSandboxRiskReviewCases`: `1`
- `xmlHtmlDomIframeSandboxRiskReviewAssertions`: `36`

## Next Task

Continue bounded XML/HTML5 DOM core-blocker slices in areas that are not already
covered by iframe sandbox validation, such as unrepresented media/embed review
metadata or additional inert fragment handoff provenance.
