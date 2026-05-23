# Independent Audit - 2026-05-23

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files needed to validate dashboard/status drift, bridge/shell-out usage, and recent Git history through observed `HEAD` `fca215f` (`Map readability Medium page breaks`). I did not edit lane implementation files, launch agents or tmux sessions, or push.

Current audit boundary: the required PHP harness is green, but this is still a dirty, moving checkout rather than an accepted integration snapshot. During this audit window, observed `HEAD` moved from `7216d58` through `0994500`, `4d2aff7`, `a045d39`, `bcc2593`, and `0225a63` to `fca215f`. Latest sampled status reported `264` `git status --short` entries, `52` tracked modified entries, and `git diff --shortstat` reported `52 files changed, 7221 insertions(+), 297 deletions(-)`. Dirty tracked files still include lane implementations/tests plus dashboard artifacts, including `lanes/dolt/src/PatchFunctionCall.php`, `lanes/esbuild/src/TypeScriptModuleLowerer.php`, `lanes/gitoxide/src/BlobMerge.php`, `porting.html`, and `porting-summary.json`.

## Findings

1. **Critical - the current green root harness is not an accepted integration checkpoint.**
   - Paths: `progress.md:230`-`238`, `audits/latest.md`, `porting.html`, `porting-summary.json`, `lanes/*/lane-status.json`.
   - Evidence: the latest required command completed with `164 test files, 15262 assertions, 0 failures`, but `HEAD` moved during the audit and the worktree still has broad dirty implementation and status changes across multiple lanes. That means the green result is useful diagnostic evidence, not a stable snapshot the supervisor can publish or merge against.
   - Goal requirement at risk: `goal.md:29` requires small reviewable slices with passing tests; `goal.md:48` requires verifying, committing, and cleaning up finished work; `goal.md:49` requires repo-wide failures and checks to be recorded honestly.
   - Audit judgment: freeze or explicitly coordinate active writers before treating any test result, dashboard, or lane status as authoritative.

2. **High - `porting.html` and `porting-summary.json` are stale and materially disagree with current manifests/status.**
   - Paths: `porting.html:30`-`32`, `porting.html:53`-`64`, `porting-summary.json:2`-`20`.
   - Evidence: the dashboard still shows `Average progress: 14.3%` and `Generated: 2026-05-22 15:40:20 UTC`. It renders old rows such as Difftastic `15 / 404 mapped`, Dolt `5 / 613 mapped`, Esbuild `16 / 2,567 mapped`, Gitoxide `737 / 2877 mapped`, LightningCSS `78 / 312 mapped`, markerPDF `11 / 27 mapped`, Pandoc `19 / 1979 mapped`, rclone `20 / 327 mapped`, Readability `89 / 1984 mapped`, and Syncthing `27 / 264 mapped`. Current manifests/status report materially different figures such as Difftastic `118 / 411`, Dolt `177 / 613`, Esbuild `132 / 2,567`, Gitoxide `1253 / 2877`, LightningCSS `671 / 3532`, markerPDF `78 / 78`, Pandoc `346 / 2028`, rclone `221 / 327`, Readability `800 / 1984`, and Syncthing `187 / 658`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current dashboard tracking of denominator, mapped tests, PHP pass/fail, phase, audit, current work, blocker, and commit; `goal.md:52` requires visible current progress in `porting.html`.
   - Audit judgment: regenerate dashboard artifacts only after the supervisor chooses one accepted green snapshot.

3. **High - `progress.md` still presents stale active-lane estimates and prior blockers.**
   - Paths: `progress.md:29`-`42`, `progress.md:230`-`238`.
   - Evidence: the Active Lanes table still lists old estimates such as Gitoxide `66%`, LightningCSS `14%`, Pandoc `10%`, Quadrable `8%`, rclone `9%`, Dolt `5%`, and Esbuild `8%`, while current lane status files claim much larger bounded slices such as Gitoxide `91%`, LightningCSS `57%`, Pandoc `60%`, Quadrable `65%`, rclone `60%`, Dolt `45%`, and Esbuild `41%`. The owner/session section also still described the prior red Pandoc root blocker until this audit update.
   - Goal requirement at risk: `goal.md:44` requires `progress.md` to include current active lanes, blockers, owner/session, next task per lane, and percentage estimates.
   - Audit judgment: update only the audit status/next intervention now; defer the full table refresh until the dirty lane batches are accepted or rejected.

4. **High - lane status files contain contradictory root-test claims.**
   - Paths: `lanes/esbuild/lane-status.json:10`-`12`, `lanes/dolt/lane-status.json:10`-`13`, `lanes/gitoxide/lane-status.json:10`-`13`, `lanes/readability/lane-status.json:10`-`12`, `lanes/syncthing/lane-status.json:10`-`13`.
   - Evidence: Esbuild still says the required root harness is blocked by unrelated Dolt failures; Dolt says it is blocked by a LightningCSS failure; Gitoxide says it is blocked by a Readability failure; Readability and Syncthing say root is green with `164 test files / 15262 assertions / 0 failures`. The latest audit-run root result is green, but the contradictory lane-local prose shows root status is being copied manually and going stale.
   - Goal requirement at risk: `goal.md:31` requires precise blockers; `goal.md:45` requires current audit/blocker/commit fields; `goal.md:49` requires checks to be recorded honestly.
   - Audit judgment: root-test status should be centralized or stamped from one accepted run, not copied as narrative text across lane files.

5. **High - uncommitted lane batches remain too broad for clean integration.**
   - Paths: `lanes/dolt/*`, `lanes/esbuild/*`, `lanes/gitoxide/*`, `lanes/lightningcss/*`, `lanes/markerpdf/*`, `lanes/pandoc/*`, `lanes/readability/*`, `porting.html`, `porting-summary.json`.
   - Evidence: the current tracked diff spans `52` files and multiple active implementation lanes. Recent history also moved while audit/test collection was in progress, with `0994500`, `4d2aff7`, `a045d39`, `bcc2593`, `0225a63`, and `fca215f` landing after the audit started.
   - Goal requirement at risk: `goal.md:29` requires small reviewable slices with passing tests; `goal.md:48` requires finished slices to be verified, committed, and cleaned up before the next assignment.
   - Audit judgment: accept or reject dirty lane batches one at a time from a frozen tree; do not publish a portfolio status from this aggregate.

6. **High - upstream denominator and mapped-count units remain inconsistent across manifests.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12`-`15`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12`-`15`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:12`-`15`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:12`-`20`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:12`-`15`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12`-`18`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12`-`17`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:12`-`15`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:12`-`15`.
   - Evidence: Difftastic counts inspected behavior artifacts; Dolt mixes executable files, BATS cases, Go functions, fixture/data artifacts, and bounded runner evidence; Esbuild counts upstream test entry points; Gitoxide counts a large targeted static/runner inventory; LightningCSS counts behavior checks/helper invocations; markerPDF counts inspected repository paths plus two benchmark pairs and supplied-boundary semantics; Pandoc counts upstream files/artifacts while mapped is focused checks; Readability counts upstream Mocha tests while mapped is local/assertion-like coverage; Syncthing counts Go test/benchmark entry points and focused static/probe evidence.
   - Goal requirement at risk: `goal.md:25` requires a real upstream benchmark denominator; `goal.md:35`-`38` require meaningful fixture parity and upstream tests as source of truth; `goal.md:45` requires comparable dashboard denominator/mapped fields.
   - Audit judgment: split schema fields into upstream files/artifacts, executable upstream tests, upstream behavior cases, mapped upstream cases, native PHP behavior tests, assertions, failures, full-runner parity, bounded-runner evidence, and static inventory.

7. **Medium - high percentages can still be mistaken for upstream parity.**
   - Paths: `lanes/gitoxide/lane-status.json:4`-`13`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12`-`17`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:222`-`236`, `lanes/syncthing/lane-status.json:4`-`13`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:186`-`187`.
   - Evidence: Gitoxide reports `91%` while full Cargo workspace tests remain unexecuted; Pandoc is still static inventory with no Haskell upstream runner parity; rclone has strong bounded 299-package evidence but excludes live provider integration, mount/FUSE, Docker-backed serve/docker, and full provider fstest parity; Syncthing uses focused package/static evidence rather than full `go test ./...`; markerPDF has no full benchmark runner.
   - Goal requirement at risk: `goal.md:35` says passing tests are not enough; `goal.md:37` says upstream tests are the source of truth; `goal.md:40` requires hard features to be marked as blockers or future slices.
   - Audit judgment: show a parity class beside each percentage so bounded/static evidence cannot read as port maturity.

8. **Medium - markerPDF still risks counting supplied model-boundary scaffolding as native extraction progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:186`-`187`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:308`-`320`.
   - Evidence: markerPDF has useful native post-processing, scoring, debug, table, OCR orchestration, conversion-finalizer, and artifact-planning work, but its upstream pipeline depends on pdftext/pypdfium/Surya/Texify/tabled/Torch execution. The manifest maps only `2` actual CI benchmark PDF/reference pairs and explicitly marks the full benchmark runner `not-executed`.
   - Goal requirement at risk: `goal.md:1` and `goal.md:30` forbid counting bridge/shell-out/generated-oracle work as native implementation progress; `goal.md:35` requires meaningful fixture parity beyond local scaffolding tests.
   - Audit judgment: keep markerPDF progress conservative until native document-level extraction parity broadens against actual benchmark pairs without relying on supplied model outputs as progress.

## Bridge / Shell-Out Check

Command searched PHP sources under `lanes`, `tools`, and `scripts` for process execution calls and common process wrappers:

```text
rg -n 'shell_exec|exec\(|passthru|proc_open|system\(|popen\(|Symfony\\Component\\Process|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result:

```text
tools/generate-dashboard.php:183:    return trim((string) shell_exec($command . ' 2>/dev/null')) ?: 'unknown';
```

No lane implementation process-execution bridge calls were found. The remaining match is dashboard coordination tooling reading Git metadata; it must not be counted as native port progress.

## Test Run

Required command: `php tools/run-tests.php`

Latest exact result for this audit:

```text
exit status: 0
164 test files, 15262 assertions, 0 failures
```

This result was collected in a moving checkout; by the final status sample, observed `HEAD` had advanced to `fca215f`.

## Recommended Next Intervention

Freeze or explicitly coordinate active writers and preserve ownership of in-flight lane batches. Use the green root harness as diagnostic evidence only until the tree is frozen. Then accept or reject dirty lane batches one at a time, rerun `php tools/run-tests.php`, regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from that same accepted snapshot, clear stale root-test strings and pending/prose latest-commit fields, and normalize dashboard/status fields for upstream denominator units, mapped upstream cases, native tests/checks/assertions, failures, runner parity, and latest accepted commit.
