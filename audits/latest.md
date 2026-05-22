# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, current `porting.html`, current `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files where needed to validate drift, bridge/shell-out usage, current dirty worktree state, and recent Git history. The required root test result below was captured on the moving aggregate checkout; during this audit `HEAD` advanced from `697b8d6` to `54741ef`. I did not edit lane implementation files, launch agents or tmux sessions, or push.

Current audit boundary: the required root PHP harness is green at the sampled state, but this is not an accepted integration checkpoint. The latest observed `git status --short` reports 193 entries, and `git diff --shortstat` reports 44 modified tracked files with 4,675 insertions and 247 deletions, plus many untracked audit/evidence and lane files. Treat the green root run as diagnostic evidence for the current aggregate, not as approval to publish or batch-commit all dirty lane work.

## Findings

1. **Critical - the repository is green but still not a reviewable integration checkpoint.**
   - Paths: `lanes/difftastic/src/FileContentDecoder.php`, `lanes/difftastic/src/TokenDiffer.php`, `lanes/dolt/src/DiffStatRenderer.php`, `lanes/esbuild/src/TypeScriptModuleLowerer.php`, `lanes/gitoxide/src/PackData.php`, `lanes/lightningcss/src/TransitionPrefixer.php`, `lanes/markerpdf/src/BlockSpanFilter.php`, `lanes/pandoc/src/MarkdownReader.php`, `lanes/rclone/src/MemoryProvider.php`, `lanes/syncthing/src/FolderIndexState.php`, `porting.html`, `porting-summary.json`, `audits/integration-status.md`.
   - Evidence: `php tools/run-tests.php` exits 0, but the checkout still has 193 `git status --short` entries and 44 modified tracked files. Dirty implementation/test/status files still span Difftastic, Dolt, esbuild, Gitoxide, LightningCSS, markerPDF, Pandoc, rclone, Syncthing, and generated/audit/status files, and `HEAD` advanced during the audit from `697b8d6` to `54741ef`.
   - Goal requirement at risk: `goal.md` requires small, reviewable slices with passing tests, cleanup of unrelated changes, precise blockers, and latest commit tracking.
   - Audit judgment: freeze or explicitly coordinate writers, then accept/reject one lane batch at a time with a root rerun after each accepted batch.

2. **High - `porting.html` and `porting-summary.json` are stale against current manifests and lane statuses.**
   - Paths: `porting.html:30`, `porting.html:32`, `porting.html:53`, `porting.html:56`, `porting.html:58`, `porting.html:59`, `porting.html:61`, `porting.html:62`, `porting.html:63`, `porting.html:64`, `porting-summary.json:2`, `porting-summary.json:3`, `porting-summary.json:57`, `porting-summary.json:90`, `porting-summary.json:107`, `porting-summary.json:141`, `porting-summary.json:158`, `porting-summary.json:175`, `porting-summary.json:192`.
   - Evidence: the dashboard still says average progress is `14.3%` and generated at `2026-05-22 15:40:20 UTC`. Current lane statuses/manifests report materially different values: Gitoxide `88%`, `2257` PHP pass units, and `1177 / 2877` mapped; Difftastic `37%`, `101` PHP tests, and `101` mapped; Dolt `35%`, `120` PHP tests, and `126` mapped; LightningCSS `50%`, `553` pass units, and `442 / 3532` mapped; markerPDF `45%`, `191` pass units, and `78` mapped; rclone `54%`, `179` pass units, and `179 / 327` mapped; Readability `43%`, `76` PHP behavior tests, and `711 / 1984` mapped; Syncthing `49%`, `161` PHP tests, and `163 / 658` mapped. `porting.html` still renders old values such as Gitoxide `66%`, Difftastic `8%`, LightningCSS `14%`, markerPDF `10%`, rclone `9%`, Readability `12%`, and Syncthing `8%`.
   - Goal requirement at risk: `goal.md` requires `porting.html` to show current suite progress, benchmark source, upstream denominator, mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Audit judgment: regenerate dashboard artifacts only from the next accepted green state, not from the current dirty aggregate.

3. **High - `progress.md` Active Lanes is stale and conflicts with current lane files.**
   - Paths: `progress.md:31`, `progress.md:32`, `progress.md:33`, `progress.md:34`, `progress.md:35`, `progress.md:36`, `progress.md:37`, `progress.md:38`, `progress.md:40`, `progress.md:41`, `progress.md:42`, `lanes/difftastic/lane-status.json:4`, `lanes/dolt/lane-status.json:4`, `lanes/esbuild/lane-status.json:4`, `lanes/gitoxide/lane-status.json:4`, `lanes/libsqlite/lane-status.json:4`, `lanes/lightningcss/lane-status.json:4`, `lanes/markerpdf/lane-status.json:4`, `lanes/pandoc/lane-status.json:4`, `lanes/quadrable/lane-status.json:4`, `lanes/rclone/lane-status.json:4`, `lanes/readability/lane-status.json:4`, `lanes/syncthing/lane-status.json:4`.
   - Evidence: `progress.md` still reports Gitoxide `66%`, LightningCSS `14%`, markerPDF `10%`, libsqlite `12%`, Readability `12%`, Quadrable `8%`, Syncthing `8%`, rclone `9%`, Dolt `5%`, and esbuild `8%`. Current lane status files report Difftastic `37%`, Dolt `35%`, esbuild `34%`, Gitoxide `88%`, libsqlite `53%`, LightningCSS `50%`, markerPDF `45%`, Pandoc `50%`, Quadrable `59%`, rclone `54%`, Readability `43%`, and Syncthing `49%`.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include current active lanes, blockers, next task per lane, percentage estimates, owner/session, and audit status.
   - Audit judgment: I updated only the audit status and next intervention text. The lane table still needs a supervisor refresh from one accepted state.

4. **High - lane status blocker and latest-commit fields are not reliable coordination data.**
   - Paths: `lanes/difftastic/lane-status.json:12`, `lanes/difftastic/lane-status.json:13`, `lanes/dolt/lane-status.json:12`, `lanes/dolt/lane-status.json:13`, `lanes/rclone/lane-status.json:12`, `lanes/rclone/lane-status.json:13`, `lanes/esbuild/lane-status.json:13`, `lanes/libsqlite/lane-status.json:13`, `lanes/markerpdf/lane-status.json:13`, `lanes/pandoc/lane-status.json:13`, `lanes/readability/lane-status.json:13`, `lanes/syncthing/lane-status.json:13`.
   - Evidence: Difftastic still says committing is blocked by a red root gate in unrelated esbuild/libsqlite, Dolt still says the latest exact root PHP run is red outside Dolt with a libsqlite failure, and rclone still says commit is blocked by an unrelated Syncthing failure. The current sampled root result is green. Several `latestCommit` values are prose such as `pending lane batch`, `Port libsqlite JSON reverse array paths`, `this lane batch`, `pending`, `current batch`, or `current lane commit`, not accepted commit hashes.
   - Goal requirement at risk: `goal.md` requires precise blockers, audit status, latest commit, and honest repo-wide test records.
   - Audit judgment: normalize lane-status fields after integration. Until then, do not treat `latestCommit` or blocker strings as machine-readable truth.

5. **High - upstream denominator units remain mixed, so percentage claims are still weak.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14`.
   - Evidence: Difftastic counts `408 inspected upstream behavior artifacts`, Dolt combines executable files, BATS cases, and fixture artifacts in one denominator string, Gitoxide uses `2877` upstream files while mapped counts are focused behavior slices, markerPDF has `0 committed Python unit test files` and a repository/path plus benchmark-pair inventory, Pandoc counts files/artifacts, Quadrable counts paths plus `check.cpp` scenarios/checks, while Readability, LightningCSS, and Syncthing use behavior-test/check counts.
   - Goal requirement at risk: `goal.md` requires real upstream benchmark denominators, mapped upstream tests, PHP passing/failing counts, and honest percentage estimates.
   - Audit judgment: split the schema before relying on progress percentages: upstream files/artifacts, upstream test cases, mapped upstream cases, PHP behavior tests, assertions, failures, full-runner parity, bounded-runner evidence, and static inventory should be separate fields.

6. **Medium - high percentages can be misread as upstream parity.**
   - Paths: `lanes/gitoxide/lane-status.json:4`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/dolt/lane-status.json:4`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:46`, `lanes/markerpdf/lane-status.json:4`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/pandoc/lane-status.json:4`, `lanes/syncthing/lane-status.json:4`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:202`, `lanes/difftastic/lane-status.json:4`, `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:111`.
   - Evidence: Gitoxide reports `88%` while full workspace Cargo tests are not run. Difftastic reports `37%` with no full upstream runner. Dolt reports `35%` while full `go test ./...` and full BATS are not run. markerPDF reports `45%` while the full benchmark runner is not executed. Pandoc reports `50%` without the full Haskell upstream runner. Syncthing reports `49%` with focused runner evidence but no full `go test ./...`.
   - Goal requirement at risk: `goal.md` says passing tests are not enough, upstream tests are the source of truth where possible, and hard features must be marked as blockers or future slices.
   - Audit judgment: keep bounded runner evidence visible, but label these as native/local progress signals, not upstream parity.

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
148 test files, 13340 assertions, 0 failures
```

This is not worse than the start of this audit run, but it is green on a dirty aggregate checkout. Treat it as diagnostic evidence only until lane changes are integrated or rejected one batch at a time.

## Recommended Next Intervention

Freeze or explicitly coordinate active writers. Choose one dirty lane batch, verify it in isolation, commit it with a root rerun, and repeat lane by lane. Regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane statuses only from that same accepted green state. Then normalize the status schema so upstream denominator units, mapped upstream cases, PHP behavior tests, assertions, failures, and runner parity are separate fields.
