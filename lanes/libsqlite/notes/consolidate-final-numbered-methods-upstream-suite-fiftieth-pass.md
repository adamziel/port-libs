## Consolidate Final Numbered Methods Upstream Suite Fiftieth Pass

Slice: `consolidate-final-numbered-methods-upstream-suite-fiftieth-pass`

Changed production surface:

- The prepared upstream-suite evidence window now uses `SQLiteUpstreamSuiteEvidence::upstreamVeryquickShardPreparedWindowEvidence()`.
- The final upstream-suite evidence window now uses `SQLiteUpstreamSuiteEvidence::upstreamVeryquickShardFinalWindowEvidence()`.

Direct test migration:

- The prepared-window direct test now lives in `SQLiteUpstreamSuiteEvidencePreparedWindowTest.php`.
- The final-window direct test now lives in `SQLiteUpstreamSuiteEvidenceFinalWindowTest.php`.
- Updated direct helper/caller names in both focused tests to use stable descriptive names.

Behavior note:

- The underlying evidence windows, expected covered slices, blockers, status strings, and assertion expectations are intentionally unchanged. This is a naming consolidation pass only.
- No compatibility shim was added for the removed numbered production methods.
- No new support component is needed; this reuses the existing upstream-suite evidence and focused TestRunner harness.

Verification:

- Pending in this worktree: PHP lint for changed PHP files, focused upstream-suite evidence tests, and `git diff --check -- lanes/libsqlite`.
