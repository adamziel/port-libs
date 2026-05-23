# Independent Audit - 2026-05-22 23:59 UTC

Scope reviewed: `goal.md`, `progress.md`, current `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files where needed to validate drift, bridge/shell-out usage, current dirty worktree state, and recent Git history through current `HEAD` `16d8f84`. I did not edit lane implementation files, launch agents or tmux sessions, or push.

Current audit boundary: the checkout moved while this audit was running. The required root command first exited red, then a later filtered rerun was still red with a different assertion count, and the latest exact rerun is green. A concurrent mixed commit (`4cdb105`) included the audit/progress edits together with Dolt lane implementation files before I could create an audit-only commit. Latest observed state before writing this refresh: `git status --short` reported `212` entries, `git status --short --untracked-files=no` reported `45` tracked modified entries, and `git diff --shortstat` reported `45 files changed, 5880 insertions(+), 252 deletions(-)`. Treat the final green root result as diagnostic evidence on a moving dirty aggregate, not as an accepted integration checkpoint.

## Findings

1. **Critical - the repository is still a moving dirty aggregate, not a reviewable integration checkpoint.**
   - Paths: `.tmux-team/prompts/dashboard-updater.md`, `audits/integration-status.md`, `lanes/gitoxide/src/PackBuilder.php`, `lanes/libsqlite/src/SQLiteDatabase.php`, `lanes/lightningcss/src/CssMinifier.php`, `lanes/pandoc/src/MarkdownReader.php`, `lanes/quadrable/src/QuadbStore.php`, `lanes/rclone/src/MemoryProvider.php`, `lanes/readability/src/ArticleExtractor.php`, `porting.html`, `porting-summary.json`.
   - Evidence: recent history advanced through Syncthing, Gitoxide, rclone, Dolt, libsqlite, and Readability commits during/around the audit, reaching `16d8f84`. Tracked dirty files still span coordination prompts, manifests, lane source, tests, notes, examples, and generated dashboard files. The root command changed from `152 test files, 13837 assertions, 2 failures` to `153 test files, 13923 assertions, 0 failures` during the same audit window.
   - Goal requirement at risk: `goal.md:29-31` requires small reviewable slices with passing tests, no generated/bridge progress, and precise blockers; `goal.md:48-49` requires verifying and cleaning up before accepting work.
   - Audit judgment: freeze or explicitly coordinate writers, then accept or reject one lane batch at a time with a fresh root rerun after each accepted batch.

2. **High - `porting.html` and `porting-summary.json` are stale against current manifests, status files, and commits.**
   - Paths: `porting.html:30`, `porting.html:32`, `porting.html:53`, `porting.html:56`, `porting.html:57`, `porting.html:58`, `porting.html:59`, `porting.html:60`, `porting.html:61`, `porting.html:62`, `porting.html:63`, `porting.html:64`, `porting-summary.json:2`, `porting-summary.json:3`, `porting-summary.json:57`, `porting-summary.json:74`, `porting-summary.json:91`, `porting-summary.json:108`, `porting-summary.json:125`, `porting-summary.json:159`.
   - Evidence: the dashboard still says average progress is `14.3%` and was generated at `2026-05-22 15:40:20 UTC`, while current lane-status estimates are much higher. Examples: Gitoxide is `66%` on the dashboard vs `89%` in `lanes/gitoxide/lane-status.json:4`; libsqlite `13%` vs `55%`; Syncthing `8%` vs `52%`; Readability dashboard/root claims are also older than the current test run. Dashboard commits are stale, for example Syncthing still shows `pending`/old dashboard data while `HEAD` is `16d8f84`.
   - Goal requirement at risk: `goal.md:45` requires `porting.html` to show current suite progress, benchmark source, upstream denominator, mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Audit judgment: do not publish or trust the dashboard until it is regenerated from one accepted green snapshot.

3. **High - `progress.md` is stale as a coordination document.**
   - Paths: `progress.md:31`, `progress.md:32`, `progress.md:33`, `progress.md:34`, `progress.md:35`, `progress.md:36`, `progress.md:37`, `progress.md:38`, `progress.md:40`, `progress.md:41`, `progress.md:42`, `progress.md:230`.
   - Evidence: the Active Lanes table still carries old estimates and next tasks such as Gitoxide `66%`, LightningCSS `14%`, markerPDF `10%`, Quadrable `8%`, Syncthing `8%`, rclone `9%`, Dolt `5%`, and esbuild `8%`. The previous audit paragraph still described `HEAD` `fa41c8b`, 190 status entries, and a 151-file root run before this audit update.
   - Goal requirement at risk: `goal.md:44` requires `progress.md` to include current active lanes, blockers, next task per lane, percentage estimates, owner/session, and audit status.
   - Audit judgment: I updated only the latest audit/status and next intervention text. The lane table should be refreshed by the supervisor only after dirty lane batches are accepted or rejected.

4. **High - lane status blockers, root-test claims, and latest-commit fields are unreliable.**
   - Paths: `lanes/readability/lane-status.json:10`, `lanes/readability/lane-status.json:12`, `lanes/readability/lane-status.json:13`, `lanes/libsqlite/lane-status.json:10`, `lanes/libsqlite/lane-status.json:12`, `lanes/pandoc/lane-status.json:12`, `lanes/quadrable/lane-status.json:10`, `lanes/quadrable/lane-status.json:12`, `lanes/rclone/lane-status.json:10`, `lanes/rclone/lane-status.json:13`, `lanes/gitoxide/lane-status.json:13`, `lanes/lightningcss/lane-status.json:13`.
   - Evidence: several status files cite older root results or unrelated blockers. Readability says root is green at `149` files / `13615` assertions while it was the lane failing in the red audit samples. libsqlite still says root fails in `lazy-image-2`; Pandoc records a different Readability failure count; Quadrable cites a Pandoc failure; rclone and Syncthing cite older green counts. Several `latestCommit` fields contain prose such as `pending`, `current batch`, `not committed`, or root-result narratives instead of an accepted commit hash.
   - Goal requirement at risk: `goal.md:31`, `goal.md:44-45`, and `goal.md:49` require precise blockers, current audit status, and latest commit tracking.
   - Audit judgment: normalize lane statuses after integration. Until then, these fields are narrative notes, not machine-readable truth.

5. **High - upstream denominator units remain inconsistent, so percentage/status comparisons are weak.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14`.
   - Evidence: Difftastic counts inspected behavior artifacts; Dolt counts executable files plus BATS cases and fixtures; esbuild counts test entry points while also noting many helper subcases; Gitoxide counts upstream files with targeted test attributes; LightningCSS counts helper invocations as behavior checks; markerPDF counts repository/source paths plus only 2 actual benchmark pairs and 0 Python unit tests; Pandoc counts files/artifacts; Quadrable counts tracked paths and top-level scenarios; Readability counts Mocha tests; rclone and Syncthing count Go test files or entry points. These are useful inventories, but not comparable denominators.
   - Goal requirement at risk: `goal.md:24-25`, `goal.md:35`, and `goal.md:38` require real upstream benchmark denominators, mapped upstream tests, and honest percentage estimates.
   - Audit judgment: split schema fields for upstream files/artifacts, upstream test cases, mapped upstream cases, native PHP behavior tests, assertions, failures, full-runner parity, bounded-runner evidence, and static inventory.

6. **Medium - high progress percentages can still be misread as upstream parity.**
   - Paths: `lanes/gitoxide/lane-status.json:4`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/pandoc/lane-status.json:4`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/syncthing/lane-status.json:4`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:16`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:16`, `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`.
   - Evidence: Gitoxide reports `89%` while full Cargo workspace tests are not run. Pandoc reports broad native coverage without full Haskell runner parity. Syncthing reports `52%` with bounded focused runner evidence but no full `go test ./...`. rclone has a bounded 299-package run excluding live provider/mount/serve-docker coverage. Difftastic remains a static inventory because Cargo dependency hydration is unresolved.
   - Goal requirement at risk: `goal.md:35-40` says passing tests are not enough, upstream tests are the source of truth where possible, and hard features must be marked as blockers or future slices.
   - Audit judgment: label percentages as native/local slice progress unless a lane has full upstream runner parity.

7. **Medium - markerPDF is still at risk of counting supplied model-boundary scaffolding as native extraction progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:24`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:175`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:176`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:299`, `lanes/markerpdf/lane-status.json:12`.
   - Evidence: the denominator remains a 78-path repository inventory with `0` committed Python unit test files, two actual CI PDF/reference pairs, and many supplied-boundary mappings for pdftext, pypdfium, Surya, Texify, tabled, image preview, debug data, and conversion callbacks. Those boundaries are useful oracle scaffolding, but they are not native PDF-to-structured-content extraction parity.
   - Goal requirement at risk: `goal.md:9`, `goal.md:30`, and `goal.md:35` require a native PDF-to-structured-content extraction pipeline and forbid counting bridge/generated/shell-out behavior as implementation progress.
   - Audit judgment: use the acquired benchmark pairs to drive document-level native extraction parity before increasing markerPDF status based on more supplied model/output boundaries.

## Bridge / Shell-Out Check

Command searched PHP sources under `lanes`, `tools`, and `scripts` for process execution calls and common process wrappers:

```text
rg -n 'shell_exec|exec\(|passthru|proc_open|system\(|popen\(|Symfony\\Component\\Process|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result: no lane implementation process-execution bridge calls found. The only match is `tools/generate-dashboard.php:183`, where coordination tooling reads Git metadata with `shell_exec`; that is not native port progress.

## Test Run

Required command: `php tools/run-tests.php`

Exact latest result for this audit run:

```text
Exit status: 0
153 test files, 13923 assertions, 0 failures
```

Earlier samples during the same audit window were worse on the moving checkout: the first exact run exited `1` with `152 test files, 13837 assertions, 2 failures`; a later filtered sample reported `152 test files, 13853 assertions, 1 failures`; an intermediate exact green run reported `152 test files, 13857 assertions, 0 failures`. The final green result is not worse than the start of this audit, but it is not an accepted integration checkpoint because the working tree and test count moved while auditing.

## Recommended Next Intervention

Freeze or explicitly coordinate active writers. Fix or accept/reject the current Readability batch first because it was the observed red lane during this audit, then pick one dirty lane batch at a time and verify it in isolation. Commit each accepted lane only after a fresh root rerun. Regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane statuses only from that same accepted green state, and normalize the status schema so upstream denominator units, mapped upstream cases, PHP behavior tests, assertions, failures, and runner parity are separate fields.
