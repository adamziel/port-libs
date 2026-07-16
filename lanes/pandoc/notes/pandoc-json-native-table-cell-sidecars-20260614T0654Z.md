# Pandoc JSON/native table cell sidecars

Issue: plib-rt897
Date: 2026-06-14

Added focused `PandocJsonNativeAstTest` coverage for table `Cell` Attr/id/classes/key-value preservation, alignment/rowspan/colspan helper sidecars, unchanged sibling cell preservation, and stale cell boundary sidecar dropping when only one cell Attr tuple is edited.

This intentionally records the lane change as a note instead of touching `lane-status.json` or `UPSTREAM_TEST_MANIFEST.json`, because the prior merge attempt for this bead conflicted in those aggregate files.

Verification:

- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php` - 1 file, 3186 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` - 46 files, 81841 assertions, 0 failures
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`
