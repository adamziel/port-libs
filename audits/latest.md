# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files, upstream cache state, bridge/shell-out search, and recent Git history through `9fdbcde` (`Stamp rclone runner evidence status`). The main worktree was clean at audit start. During verification, unrelated lane implementation/status changes appeared and the dashboard was regenerated; I did not edit lane implementation files. The final test result below is from that dirty working tree.

## Findings

1. **High - A large amount of lane progress is currently uncommitted, so the dashboard is not a clean-HEAD state.**
   - Paths: `goal.md:29`, `goal.md:44-49`, `progress.md`, `porting.html`, `porting-summary.json`, modified/untracked files under `lanes/*`.
   - Evidence: the final root suite passes, but `git status --short` still shows many modified and untracked lane implementation, fixture, manifest, status, note, dashboard, and progress files. Examples include Difftastic subword fixtures, markerPDF cleaner files, libsqlite database reader files, Quadrable iterator files, rclone `LsJsonListing`, and Readability embedded-video fixtures. Several visible status entries still point at older or pending commits while reporting the new local checks.
   - Goal requirement at risk: small reviewable commits with passing tests, accurate latest-commit tracking, and verifying/committing finished agent work before assigning the next slice.
   - Audit judgment: review and integrate or reject those lane changes as separate commits before treating the regenerated dashboard as published project state.

2. **High - Gitoxide progress is still overstated relative to its upstream denominator.**
   - Paths: `goal.md:7`, `goal.md:24-25`, `goal.md:35-38`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:12-20`, `porting.html:56`, `progress.md:31`, `progress.md:187`.
   - Evidence: Gitoxide is priority 1 and the dashboard now reports 65.0%, 1234 pass / 0 fail, and `735 / inventory mapped`. The manifest's denominator is still a long prose inventory, not a machine-checkable upstream test denominator, and its runner status remains `not executed`.
   - Goal requirement at risk: real upstream benchmark denominator, upstream tests as source of truth, explicit slices for huge suites, and no silent skipping of hard Git features.
   - Audit judgment: keep the native slices as useful smoke coverage, but do not treat the percentage or local check count as upstream parity until the denominator becomes concrete and focused upstream fixture IDs dominate the status.

3. **High - markerPDF still has zero real benchmark PDF/reference pairs mapped.**
   - Paths: `goal.md:9`, `goal.md:35-37`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13-18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:52-62`, `progress.md:33`, `progress.md:189`, `porting.html:59`.
   - Evidence: the working-tree manifest now maps 8 focused semantics, but it still records `mappedBenchmarkPairs: 0` and only `mappedBenchmarkSurrogatePairs: 1`.
   - Goal requirement at risk: PDF-to-structured-content extraction suitable for WordPress import/Data Liberation with meaningful fixture parity.
   - Audit judgment: priority 3 should not broaden layout/OCR behavior until at least one real benchmark document and reference output is pinned or the surrogate policy is explicitly accepted as temporary.

4. **Medium - Recent upstream-runner evidence is useful, but it is not native PHP parity.**
   - Paths: `goal.md:1`, `goal.md:30`, `goal.md:35-38`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, `progress.md:187-198`.
   - Evidence: LightningCSS, Readability, esbuild, and rclone document upstream runner passes or bounded passes, but the native PHP mappings remain small relative to upstream: 71/241, 78/1984 with 14 PHP tests, 13/2567, and 17/327 respectively. rclone's bounded pass skips provider `TestIntegration` and excludes mount/FUSE/docker packages.
   - Goal requirement at risk: bridge/upstream runner evidence must remain oracle/tooling evidence and must not count as native implementation progress.
   - Audit judgment: status wording should continue to say "runner evidence" and avoid implying the native ports passed upstream suites.

5. **Medium - Tooling records are inconsistent with the original environment record.**
   - Paths: `progress.md:20-21`, `composer.json:12`, `tools/run-tests.php:1`.
   - Evidence: the final audit shell has `/usr/bin/php` reporting PHP 8.5.6, while the original bootstrap recorded PHP 8.2.29. `composer` is not on PATH.
   - Goal requirement at risk: precise environment/tooling recording and reproducible verification.
   - Audit judgment: record the actual PHP/Composer availability before relying on runner results or generated-dashboard commands.

6. **Medium - Several upstream caches are still not reproducible runner worktrees, and LightningCSS has uncommitted upstream-cache edits.**
   - Paths: `.upstream-cache/dolt`, `.upstream-cache/esbuild`, `.upstream-cache/pandoc`, `.upstream-cache/syncthing`, `.upstream-cache/lightningcss/Cargo.lock`, `.upstream-cache/lightningcss/src/lib.rs`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:85-107`, `progress.md:190`.
   - Evidence: cache `git status --short | wc -l` reports 2387 entries for Dolt, 349 for esbuild, 2781 for Pandoc, and 940 for Syncthing. LightningCSS has modified `Cargo.lock` and `src/lib.rs` in the upstream cache to support the WASM compatibility run.
   - Goal requirement at risk: reproducible generated artifacts and precise blocker recording.
   - Audit judgment: static `git ls-tree` inventories remain useful, but runner attempts should use clean detached worktrees or versioned patch notes. The LightningCSS WASM pass should be treated as compatibility evidence unless the exact patch is captured reproducibly.

7. **Medium - The continuous auditor/worker cadence is not active.**
   - Paths: `goal.md:20`, `progress.md:14`, `progress.md:218-220`.
   - Evidence: progress records the auditor as stopped and no worker sessions active, while the goal calls for implementation lanes plus an independent auditor challenging quality every 20 minutes under a practical cap.
   - Goal requirement at risk: durable supervision and continuous quality challenge.
   - Audit judgment: this manual audit updates the latest status, but the supervisor should restart the capped cadence after the dirty tree is reconciled.

## Bridge / Shell-Out Check

Searched committed lane/tool/script files for `shell_exec`, `exec(`, `passthru`, `proc_open`, `system(`, and `popen(`. No matches were found in `lanes`, `tools`, or `scripts`, so I did not find committed bridge code or shell-outs being counted as native implementation progress.

## Test Run

Command: `php tools/run-tests.php`

Exact result:

```text
50 test files, 2467 assertions, 0 failures
```

Exit status: 0.

Note: this is a dirty-working-tree result after unrelated lane edits appeared during the audit.

## Recommended Next Intervention

First review and integrate or reject the uncommitted lane changes as passing, reviewable commits. Then continue the highest-value parity gaps: make Gitoxide's upstream denominator machine-checkable with named fixture IDs, acquire one real markerPDF benchmark PDF/reference pair, and keep new native PHP mappings tied to named upstream fixtures rather than runner-evidence headlines.
