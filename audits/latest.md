# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files, the dirty worktree, bridge/shell-out usage, and recent Git history through `3317fd3` (`Stamp quadrable lane status`). I did not edit lane implementation files, launch agents or tmux sessions, or push.

## Findings

1. **Critical - `porting.html` and `porting-summary.json` are stale enough to be misleading.**
   - Paths: `porting.html:30`, `porting.html:32`, `porting.html:40`, `porting.html:43`, `porting.html:53-64`, `porting-summary.json`.
   - Evidence: the dashboard was generated at `2026-05-22 15:40:20 UTC` and still reports average progress `14.3%`. Current manifests/status files report newer mapped counts than the dashboard for every lane: difftastic `29` vs `15`, Dolt `26` vs `5`, esbuild `33` vs `16`, Gitoxide `787` vs `737`, libsqlite `33` vs `18`, LightningCSS `117` vs `78`, markerPDF `30` vs `11`, Pandoc `40 / 2028` vs `19 / 1979`, Quadrable `40` vs `24`, rclone `38` vs `20`, Readability `155` vs `89`, and Syncthing `45` vs `27`.
   - Goal requirement at risk: `goal.md` requires `porting.html` to show accurate per-lane suite progress, benchmark source, upstream denominator, mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Audit judgment: regenerate the dashboard only after the dirty lane batches are integrated or rejected from one committed state. Also split `mapped`, PHP behavior tests, and assertions instead of compressing them into one `Mapped` column.

2. **Critical - the repository is still a moving, dirty integration target.**
   - Paths: current `git status --short`, `progress.md:31-42`, `progress.md:229-231`, recent commits `5124ea3`, `d49dd22`, `ad9cf96`, `ceac8c1`, `5615e1a`, `4f4ddba`, `7fed5a7`, `44b675b`, `fff053f`, `373c77f`, `2d36c65`, `186a19a`, `b603470`, `ad041d3`, `1742935`, `8a8fe79`, `022f23b`, and `3317fd3`.
   - Evidence: recent history advanced during the audit window beyond the prior `0b5b6a6` audit snapshot, while the worktree still contains dirty implementation/status/dashboard changes across difftastic, esbuild, Gitoxide, libsqlite, Readability, Syncthing, `porting.html`, `porting-summary.json`, and untracked audit/lane files. The Active Lanes table still shows all lanes as `stopped` with older phases and estimates.
   - Goal requirement at risk: `goal.md` requires the supervisor to verify finished agent work, commit small passing slices, update progress, clean accidental unrelated changes, and keep the roadmap honest.
   - Audit judgment: no additional feature slice should be treated as progress until the current dirty batches are reviewed, either committed or rejected, and represented by regenerated coordination outputs.

3. **High - lane blocker/status text still contradicts the current green root suite.**
   - Paths: `lanes/difftastic/lane-status.json:12`, `lanes/esbuild/lane-status.json:12`, `lanes/syncthing/lane-status.json:12`, `lanes/pandoc/lane-status.json:12`, `lanes/gitoxide/lane-status.json:12`.
   - Evidence: the final audit run of `php tools/run-tests.php` exits 0 with `68 test files, 3779 assertions, 0 failures`. Several lane blockers still cite obsolete root-suite failures or older assertion totals; `progress.md` was updated in this audit, but the lane status files remain stale.
   - Goal requirement at risk: `goal.md` requires repo-wide test results and blockers to be recorded honestly and precisely.
   - Audit judgment: refresh lane blockers and progress from the latest root result before assigning work from those files.

4. **High - PHP pass/fail units remain mixed and are not dashboard-safe.**
   - Paths: `porting.html:43`, `porting.html:56`, `porting.html:58`, `lanes/gitoxide/lane-status.json:6`, `lanes/lightningcss/lane-status.json:6`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:92`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:137`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:194`.
   - Evidence: Gitoxide reports `phpPass: 1342`, which is an assertion count, not a behavior-test count. LightningCSS reports `174` similarly. Other lanes record behavior-test counts such as rclone `36`, Dolt `26`, and Readability `22`, while Readability separately maps `155` checks. The dashboard labels all of these as `pass / fail`.
   - Goal requirement at risk: `goal.md` requires per-lane PHP pass/fail counts that can be compared honestly.
   - Audit judgment: add separate schema fields for PHP test files, behavior tests, assertions, and failures; do not label assertion totals as pass counts.

5. **High - markerPDF still has zero real upstream benchmark PDF/reference pairs.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13-17`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:57`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:93-94`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:126`.
   - Evidence: markerPDF maps `30` focused semantics and `4` upstream-derived surrogate pairs, but `mappedBenchmarkPairs` remains `0`; the real benchmark runner is still `not-executed` because benchmark PDFs/references and heavy Poetry/model dependencies are absent.
   - Goal requirement at risk: `goal.md` scopes markerPDF as a PDF-to-structured-content extraction pipeline and requires meaningful fixture parity, not only Markdown-output surrogate scoring.
   - Audit judgment: the next markerPDF intervention should acquire and map at least one real `benchmark_data` PDF/reference pair before adding more cleaner/layout breadth.

6. **High - Gitoxide's priority-lane status is ahead of its upstream evidence quality.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13-18`, `lanes/gitoxide/lane-status.json:4`, `lanes/gitoxide/lane-status.json:6`, `lanes/gitoxide/lane-status.json:12`.
   - Evidence: Gitoxide reports `estimatedProgress: 70`, `mapped: 787`, and `phpPass: 1342`, but the upstream Cargo runner is still not executed and the manifest is still largely targeted static inventory prose rather than normalized upstream fixture IDs, mapped native tests, and uncovered category accounting.
   - Goal requirement at risk: `goal.md` requires a real upstream benchmark denominator, mapped upstream tests, and upstream tests as the source of truth whenever possible.
   - Audit judgment: keep Gitoxide status conservative until it has normalized fixture/test IDs and uncovered-category accounting, or a controlled crate-level upstream runner probe.

7. **Medium - upstream runner evidence is inconsistently modeled across manifests.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13-15`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:46`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:13-15`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:67`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13-15`, `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:51`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:72`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:57`.
   - Evidence: Dolt and rclone have bounded runner evidence and set `runnerStatus.executed` in an object, markerPDF uses a string `not-executed`, Difftastic/Syncthing use objects for non-execution, Pandoc has no explicit `runnerStatus`, and Gitoxide uses prose. Bounded wins are valid, but the data model lets readers confuse bounded evidence with full upstream parity.
   - Goal requirement at risk: `goal.md` requires the upstream benchmark denominator and runner status to distinguish real upstream pass parity from focused or bounded evidence.
   - Audit judgment: normalize runner status as `fullRunnerExecuted`, `runnerScope`, `boundedRunnerEvidence`, and `notRunReason`.

## Bridge / Shell-Out Check

Command searched `lanes`, `tools`, and `scripts` for PHP process execution calls:

```text
rg --pcre2 "\b(shell_exec|exec|passthru|proc_open|system|popen)\s*\(" lanes tools scripts -n
```

Only match: `lanes/readability/fixtures/mozilla/videos-2/source.html:830`, JavaScript fixture code `regex.exec(url)`. No PHP process-execution bridge matches were found.

## Test Run

Command: `php tools/run-tests.php`

Exit status: 0

Exact result:

```text
68 test files, 3779 assertions, 0 failures
```

## Recommended Next Intervention

Freeze or explicitly coordinate the existing workers, then review the dirty lane batches and either commit or reject them. Regenerate `progress.md`, `porting.html`, and `porting-summary.json` from that committed state, and refresh stale lane blockers. After status is honest again, prioritize markerPDF real benchmark PDF/reference parity, Gitoxide normalized fixture IDs or a controlled crate-level runner probe, and schema separation for full upstream runner evidence, bounded runner evidence, behavior tests, and assertions.
