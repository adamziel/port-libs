# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, current `porting.html`, current `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files where needed to validate dashboard/progress drift, bridge/shell-out usage, current dirty worktree state, and recent Git history through `6e39f67` (`Update quadrable gc test evidence`). I did not edit lane implementation files, launch agents or tmux sessions, or push.

Current audit boundary: the root PHP harness is green on the latest sampled checkout, but the checkout is not an accepted integration slice. `git status --short` moved from 179 to 201 entries while this audit was being written, including broad modified lane implementation/test/manifest/status files plus many untracked evidence files. The green test result therefore proves only that the aggregate dirty tree was runnable at the time sampled; it does not make the lane batches reviewable or publishable.

## Findings

1. **Critical - the repository is green but still not a reviewable integration checkpoint.**
   - Paths: `lanes/dolt/src/DiffSummaryRenderer.php`, `lanes/dolt/tests/DiffSummaryRendererTest.php`, `lanes/lightningcss/src/CssMinifier.php`, `lanes/lightningcss/tests/CssMinifierTest.php`, `lanes/markerpdf/src/LayoutAnnotator.php`, `lanes/markerpdf/tests/LayoutAnnotatorTest.php`, `lanes/pandoc/src/MarkdownReader.php`, `lanes/pandoc/src/WordPressBlockWriter.php`, `lanes/rclone/src/MemoryProvider.php`, `lanes/readability/src/ArticleExtractor.php`, `porting.html`, `porting-summary.json`, `audits/integration-status.md`.
   - Evidence: `git status --short` moved from 179 to 201 entries during this audit. The latest sampled `git diff --stat` reports 65 modified tracked files with 5,486 insertions and 368 deletions, plus many untracked audit/evidence files and untracked lane files such as `lanes/gitoxide/src/CredentialContext.php`, `lanes/libsqlite/src/SQLiteTrimIndexExpression.php`, and `lanes/rclone/examples/wordpress-provider-id-duplicate-upload-dirs.php`.
   - Goal requirement at risk: `goal.md` requires small, reviewable slices with passing tests, cleanup of unrelated changes, precise blockers, and latest commit tracking.
   - Audit judgment: do not publish or stamp this as a portfolio checkpoint. Freeze writers, then accept or reject one lane batch at a time with a root rerun after each accepted batch.

2. **High - `porting.html` and `porting-summary.json` are stale against the current lane statuses and manifests.**
   - Paths: `porting.html:29`, `porting.html:53`, `porting.html:56`, `porting.html:58`, `porting.html:59`, `porting.html:60`, `porting.html:63`, `porting.html:64`, `porting-summary.json:2`, `porting-summary.json:3`, `porting-summary.json:61`, `porting-summary.json:62`, `porting-summary.json:95`, `porting-summary.json:96`.
   - Evidence: the dashboard says `Average progress: 14.3%` and `Generated: 2026-05-22 15:40:20 UTC`, while the latest sampled `lanes/*/lane-status.json` estimates average 47.42%. Gitoxide renders `737 / 2877` mapped and `1257 pass`, but `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14-15` says `2877` total and `1151` mapped, while `lanes/gitoxide/lane-status.json:4-6` says `87%` and `2208` PHP pass/assertion units. LightningCSS renders `78 / 312`, but `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14-15` says `3532` total and `426` mapped, and `lanes/lightningcss/lane-status.json:4-6` says `48%` and `536`. markerPDF renders `11 / 27`, but `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14-15` records a 78-path inventory and `78` mapped, and `lanes/markerpdf/lane-status.json:4-6` says `43%` and `180`. Syncthing renders `27 / 264`, but `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14-15` says `658` total and `155` mapped.
   - Goal requirement at risk: `goal.md` requires `porting.html` to show current suite progress, benchmark source, upstream denominator, mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Audit judgment: regenerate dashboard artifacts only after the next accepted green checkpoint, not from the current unintegrated aggregate.

3. **High - `progress.md` Active Lanes remains stale and conflicts with current lane status files.**
   - Paths: `progress.md:31`, `progress.md:32`, `progress.md:33`, `progress.md:34`, `progress.md:37`, `progress.md:38`, `progress.md:41`, `progress.md:42`, `lanes/gitoxide/lane-status.json:4`, `lanes/lightningcss/lane-status.json:4`, `lanes/markerpdf/lane-status.json:4`, `lanes/libsqlite/lane-status.json:4`, `lanes/quadrable/lane-status.json:4`, `lanes/syncthing/lane-status.json:4`, `lanes/dolt/lane-status.json:4`, `lanes/esbuild/lane-status.json:4`.
   - Evidence: `progress.md` still reports Gitoxide `66%`, LightningCSS `14%`, markerPDF `10%`, libsqlite `12%`, Quadrable `8%`, Syncthing `8%`, Dolt `5%`, and esbuild `8%`. Latest sampled lane status files report Gitoxide `87%`, LightningCSS `48%`, markerPDF `43%`, libsqlite `51%`, Quadrable `56%`, Syncthing `47%`, Dolt `34%`, and esbuild `32%`.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include current active lanes, blockers, next task per lane, percentage estimates, owner/session, and audit status.
   - Audit judgment: I updated only the latest audit status and next intervention lines. The lane table needs a supervisor refresh from a single accepted green state.

4. **High - lane status files disagree about the current root gate and accepted commit state.**
   - Paths: `lanes/gitoxide/lane-status.json:10`, `lanes/gitoxide/lane-status.json:12`, `lanes/gitoxide/lane-status.json:13`, `lanes/lightningcss/lane-status.json:10`, `lanes/lightningcss/lane-status.json:12`, `lanes/lightningcss/lane-status.json:13`, `lanes/pandoc/lane-status.json:12`, `lanes/markerpdf/lane-status.json:13`, `lanes/syncthing/lane-status.json:13`, `lanes/libsqlite/lane-status.json:13`.
   - Evidence: Gitoxide now records uncommitted prose and a different root result (`143 files, 12844 assertions, 0 failures`) than this audit's latest observed run. LightningCSS still says root is blocked by unrelated dirty-lane failures and that the current batch is uncommitted, even though this audit's latest root run is green. Pandoc still cites an earlier esbuild root failure. Dolt, markerPDF, and Syncthing record prose such as `pending`, `this lane batch`, or `current lane commit` instead of accepted commit hashes. libsqlite records `Port libsqlite JSON text operator indexes` instead of the hash already present in recent history (`64c18d4`).
   - Goal requirement at risk: `goal.md` requires precise blockers, audit status, latest commit, and honest repo-wide test records.
   - Audit judgment: normalize lane statuses only after integration. Until then, `latestCommit` should not be treated as machine-readable or current.

5. **High - upstream denominator and mapped-test units are still mixed, weakening progress claims.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14-15`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14-15`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14-15`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14-15`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14-15`, `lanes/readability/lane-status.json:5-6`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14-15`, `lanes/lightningcss/lane-status.json:5-6`, `porting-summary.json:11`, `porting-summary.json:13`, `porting-summary.json:181`, `porting-summary.json:183`.
   - Evidence: Difftastic's denominator is `408 inspected upstream behavior artifacts`, not one clear upstream test denominator. Dolt's `total` combines 613 executable files, 3,808 BATS cases, and 256 artifacts in one string. markerPDF's denominator is a repository/path inventory and benchmark-pair count, while mapped is `78`. Pandoc's denominator is `2028 upstream test files/artifacts`, not test cases. Readability manifest maps `668` checks while lane status reports `73` PHP behavior tests, and the dashboard still renders `89 / 1984` plus `15 pass / 0 fail`. LightningCSS now maps `426` checks in the manifest while the dashboard still renders `78 / 312`.
   - Goal requirement at risk: `goal.md` requires real upstream benchmark denominators, mapped upstream tests, PHP passing/failing counts, and honest percentage estimates.
   - Audit judgment: split the schema before using percentages for planning: upstream files/artifacts, upstream test cases, mapped upstream cases, PHP behavior tests, PHP assertions, PHP failures, full-runner parity, bounded-runner evidence, and static inventory should be separate fields.

6. **Medium - high percentages can be overread as upstream parity.**
   - Paths: `lanes/gitoxide/lane-status.json:4-5`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/difftastic/lane-status.json:4-5`, `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:105`, `lanes/dolt/lane-status.json:4-5`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:46`, `lanes/markerpdf/lane-status.json:4-5`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:163`, `lanes/pandoc/lane-status.json:4-5`, `lanes/syncthing/lane-status.json:4-5`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:190`.
   - Evidence: Gitoxide reports `86%` while the full Cargo workspace runner is not executed. Difftastic reports `34%` with no full upstream runner. Dolt reports `34%` while full `go test ./...` and full BATS are not run. markerPDF reports `43%` while the full benchmark runner is not executed. Pandoc reports `48%` while the full Haskell runner is not executed. Syncthing reports `47%` while full `go test ./...` is not executed.
   - Goal requirement at risk: `goal.md` says upstream tests are the source of truth where possible, hard features must be marked as blockers or future slices, and passing local tests are not enough.
   - Audit judgment: keep bounded runner evidence visible, but label these as local/native progress signals, not upstream parity.

## Bridge / Shell-Out Check

Command searched PHP sources under `lanes`, `tools`, and `scripts` for process execution calls and common process wrappers:

```text
rg -n 'shell_exec|exec\(|passthru|proc_open|system\(|popen\(|Symfony\\Component\\Process|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result: no lane implementation process-execution bridge calls found. The only match is `tools/generate-dashboard.php:183`, where coordination tooling reads Git metadata with `shell_exec`; that is not native port progress.

## Test Run

Required command: `php tools/run-tests.php`

Exact result for this audit run:

```text
Exit status: 0
143 test files, 12828 assertions, 0 failures
```

This is not worse than the prior green audit baseline, but it is green on a dirty aggregate checkout. Treat it as diagnostic evidence only until lane changes are integrated or rejected one batch at a time.

## Recommended Next Intervention

Freeze or explicitly coordinate active writers. Choose one dirty lane batch, preferably the highest-priority Gitoxide credential-context/cascade work or another already lane-green high-priority batch, then verify it in isolation, commit it with a root rerun, and regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from that same accepted green state. Do not normalize dashboard percentages until the schema separates upstream denominator units, mapped upstream cases, PHP behavior tests, assertions, failures, and runner parity.
