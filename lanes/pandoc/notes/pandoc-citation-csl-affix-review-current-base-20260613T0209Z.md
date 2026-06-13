# Pandoc Citation CSL Affix Review Current Base

## Scope

Bounded native PHP CSL citation handoff now preserves citation prefix and
suffix review metadata without relying on external citeproc behavior.

- `CitationCslProcessor` exposes `cslCitationPrefix`,
  `cslCitationSuffix`, and `cslCitationAffixSummary` on normalized citation
  nodes when source citations carry prefix or suffix inlines.
- Citation groups expose `cslCitationAffixes` and
  `cslCitationAffixSummary` so downstream review queues can inspect the
  affixes for grouped Pandoc JSON/native citations.
- CSL text variables `citation-prefix`, `citation-suffix`, and
  `citation-affix-summary` render those review values.
- Custom CSL layouts that explicitly render `citation-prefix` or
  `citation-suffix` no longer receive the same affix a second time from the
  automatic prefix/suffix insertion path.

## Verification

```text
php -l lanes/pandoc/src/CitationCslProcessor.php
php -l lanes/pandoc/tests/CitationCslProcessorTest.php
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
php tools/run-tests.php lanes/pandoc/tests
```

Results:

- Focused `CitationCslProcessorTest.php`: 1 file, 5321 assertions, 0 failures.
- Full `lanes/pandoc/tests`: 46 files, 75562 assertions, 0 failures after
  rebase onto current main `eb56c4b88`.

No Pandoc binary, citeproc, BibTeX, Biber, bibliography manager, browser
renderer, external validator, online service, live provider test, or
live-service provider test was invoked.

## Accounting

- `phpPass` moves from 3354 to 3355.
- `phpFail` remains 0.
- `UPSTREAM_TEST_MANIFEST.json` mapped count moves from 3314 to 3315.
- `mappedCitationAffixReviewCases` is 1.
- `citationAffixReviewAssertions` is 13.

## Remaining Gaps

Citation/bibliography is still not shippable as a family. EndNote XML remains
unsupported, RIS parsing is still bounded, and broader reader-registry parity
for bibliography formats remains open.
