# Pandoc MediaBag Repair Provenance Slice

Implemented one bounded native PHP MediaBag linked-resource provenance slice on current main `e45c83c62f`.

- MediaBag items now retain stable source-path, path-repair, MIME-source, inferred-MIME, and MIME-repair summaries.
- Extraction plans disambiguate case-folded media path collisions while preserving the existing `media-resource-path-collision` diagnostic and adding a case-folded diagnostic.
- Linked resources now expose duplicate MIME group metadata in mapped attrs, entries, Markdown, WordPress, and Pandoc JSON handoff.
- Focused coverage exercises normalized path repair, percent-decoded path repair, extension/content-type disagreement repair summaries, duplicate linked-resource MIME groups, case-folded collision disambiguation, and stable provenance attrs.

No Pandoc, browser, Node tooling, online service, live provider, external validator, or resource fetcher was invoked.

Verification:

- `php -l lanes/pandoc/src/MediaBag.php`
- `php -l lanes/pandoc/tests/MediaBagTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MediaBagTest.php` (1 file, 236 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests` (46 files, 82056 assertions, 0 failures)
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`
