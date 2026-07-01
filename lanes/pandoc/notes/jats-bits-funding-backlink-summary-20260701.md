# JATS/BITS Funding Backlink Summary

Work item: plib-ymqmf

XmlHtmlDom now derives a metadata-only `fundingReferenceBacklinkSummary` from the existing funding backlink records. The summary keeps the detailed backlink records as the source of truth and exposes stable review buckets for:

- missing, duplicate, and linked reference counts plus reference IDs
- award-source conflict reference IDs
- multi-award reference IDs
- maximum backlink count and per-reference link counts
- per-reference award IDs and funding-source IDs

Top-level packet aliases mirror the summary for quick review: `fundingReferenceBacklinkStatusCounts`, `fundingReferenceBacklinkReferenceIdsByStatus`, `fundingReferenceBacklinkConflictReferenceIds`, `fundingReferenceBacklinkMultiAwardReferenceIds`, and `maxFundingReferenceBacklinkLinkCount`.

The summary is explicitly metadata-only. Citation payload text remains blocked, and funding backlink link text is represented through existing length/hash fields in funding-specific linked-reference and backlink records. The focused JATS fixture covers one missing funding reference, one duplicate/conflicting reference, and one clean linked reference without invoking Pandoc, XML validators, browsers, Node, online services, or external validators.

Focused validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomJatsFundingBacklinkSummaryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomJatsFundingBacklinkSummaryTest.php` with 1 file, 33 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*.php lanes/pandoc/tests/Html5DomFragmentTest.php` with 83 files, 12,698 assertions, 0 failures
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- `git diff --check`
- conflict-marker scan of changed lane files
- `php tools/run-tests.php lanes/pandoc/tests` completed red outside this slice with 535 files, 142,327 assertions, and 8,912 existing broad Markdown/WordPress/table baseline failures
