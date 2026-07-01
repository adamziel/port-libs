# Pandoc XML/HTML5 DOM Script AttributionSrc Issue Codes

Hook: `plib-ug7cd`, Pandoc XML/HTML5 DOM core blocker slice 20260615T080505Z.

This slice keeps the native PHP XML/HTML DOM work bounded to script attribution
source-registration metadata. `XmlHtmlDom::summarizeHtmlFragment()` already
preserved detailed `scriptAttributionSrcIssues` records for `<script
attributionsrc>`. The handoff now also exposes compact gateable metadata:

- `scriptAttributionSrcIssueCodes`
- `scriptAttributionSrcIssueCount`
- `scriptAttributionSrcValid`

The detailed issue records remain unchanged for empty boolean attribution
requests, unsafe script attribution URLs, and non-HTTP attribution URLs. No
script execution, browser engine, network fetch, Pandoc process, external
sanitizer, or external validator was used.

Focused validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomScriptAttributionSrcIssueCodesTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomScriptAttributionSrcIssueCodesTest.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  passed with 2 files, 6,260 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*Test.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/XmlHtml5DomTest.php`
  passed with 37 files, 10,500 assertions, 0 failures.
