# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status/dashboard state, bridge/shell-out usage, the dirty worktree, and recent Git history through `dd4569e` (`Allow Dolt integration handoffs`). I did not edit lane implementation files, launch agents, launch tmux sessions, or push.

## Findings

1. **Critical - The generated dashboard is materially stale and cannot be trusted as the required portfolio status.**
   - Paths: `porting.html:53`, `porting.html:56`, `porting.html:58`, `porting.html:59`, `porting.html:62`, `porting.html:63`, `porting.html:64`, `porting-summary.json:2`, `porting-summary.json:11`, `porting-summary.json:45`, `porting-summary.json:62`, `porting-summary.json:96`, `porting-summary.json:113`, `porting-summary.json:164`, `porting-summary.json:181`, `porting-summary.json:198`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:15`.
   - Evidence: `porting-summary.json` is generated at `2026-05-22 15:40:20 UTC`, but current manifests/lane statuses have moved. Examples: Gitoxide manifest says `756` mapped while the dashboard says `737`; LightningCSS manifest says `89` mapped / 155 PHP assertions while the dashboard says `78` / 141; markerPDF says `21` mapped and 40 PHP behavior tests while the dashboard says `11` / 23; libsqlite says `28` mapped while the dashboard says `18`; pandoc says `31` mapped with a `2028` artifact denominator while the dashboard says `19 / 1979`; rclone says `25` mapped while the dashboard says `20`; Readability says `141` mapped while the dashboard says `89`; Syncthing says `39` mapped while the dashboard says `27`.
   - Goal requirement at risk: `goal.md` requires `porting.html` to track mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current work, blocker, and commit for every lane.
   - Audit judgment: regenerate `porting.html` and `porting-summary.json` only after the current dirty lane changes are integrated or rejected from one quiesced state.

2. **High - The audit target is still moving while status files claim a stable stopped-lane snapshot.**
   - Paths: `progress.md:31`, `progress.md:42`, `progress.md:229`, `progress.md:231`, current `git status --short`, recent Git history from `acaad7e` through `dd4569e`.
   - Evidence: while this audit was running, history advanced through additional status and implementation commits including `195b5b1`, `706d622`, `f3326ab`, `fafc5eb`, and `dd4569e`. The worktree still has uncommitted implementation/test/status changes in `lanes/dolt/*`, `lanes/esbuild/*`, `lanes/libsqlite/*`, `lanes/pandoc/*`, `lanes/quadrable/*`, `lanes/rclone/*`, and `lanes/readability/*`, plus untracked audit/status and fixture/source files. `progress.md` still lists each lane session as stopped while also warning that worker sessions were active in the prior snapshot.
   - Goal requirement at risk: `goal.md` requires the supervisor to verify finished agent work, commit small passing slices, update progress, clean accidental unrelated changes, and keep the roadmap honest.
   - Audit judgment: treat the current tree as a moving integration surface, not a clean release snapshot, despite the root PHP suite passing.

3. **High - Gitoxide remains overstated relative to a machine-checkable upstream denominator.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:20`, `porting.html:56`.
   - Evidence: the priority-1 lane reports 66-68% progress and `756 / 2877` mapped, but the Cargo runner is still not executed and the manifest is a long prose static inventory rather than a normalized, machine-checkable list of upstream suites/fixtures with pass/fail state. The local PHP count is meaningful smoke coverage, not upstream pass parity.
   - Goal requirement at risk: `goal.md` requires a real upstream benchmark denominator or clearly marked defensible static inventory, and says upstream tests should be the source of truth for large suites.
   - Audit judgment: keep Gitoxide progress conservative until fixture IDs, uncovered areas, and native mapped checks are normalized into auditable counts, or a controlled crate-level upstream runner is added.

4. **High - markerPDF still has zero real benchmark PDF/reference pairs.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:16`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:57`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:58`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:82`.
   - Evidence: the manifest now maps 4 README-linked surrogate pairs and 21 focused source semantics, but `mappedBenchmarkPairs` remains `0` and the real upstream benchmark runner is still not executed because benchmark PDFs/references and heavy model dependencies are absent.
   - Goal requirement at risk: `goal.md` scopes markerPDF as a PDF-to-structured-content extraction pipeline for WordPress import/Data Liberation and requires meaningful fixture parity, not only surrogate Markdown-output scoring.
   - Audit judgment: the next markerPDF intervention should acquire at least one real `benchmark_data` PDF/reference pair before broadening OCR/layout behavior further.

5. **Medium - Upstream runner evidence is useful oracle data but is easy to misread as native PHP parity.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:47`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:48`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:47`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:75`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:61`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:90`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:38`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:84`.
   - Evidence: Dolt has bounded Go/BATS evidence but only 18 native PHP tests against 613 executable upstream test files; esbuild core `make test` passes but only 22 native PHP tests are mapped against 2,567 entries and `make test-all` is unrun; rclone has a bounded 299-package run but excludes live provider/mount coverage and maps 25 PHP tests; Readability's upstream `npm test` passes 1,984 tests while native PHP maps 18 tests / 127 checks.
   - Goal requirement at risk: `goal.md` forbids counting JS/Rust/Go/C/C++ execution, bridge calls, generated fixtures, or shell-outs as native implementation progress except temporary oracle tooling.
   - Audit judgment: keep upstream runner pass evidence visually separate from native PHP mapped/pass counts in the next dashboard generation.

## Bridge / Shell-Out Check

Command searched committed and untracked files under `lanes`, `tools`, and `scripts` for `shell_exec`, `exec(`, `passthru`, `proc_open`, `system(`, and `popen(` using `rg --pcre2`. The only match was copied Mozilla fixture JavaScript `regex.exec(url)` in `lanes/readability/fixtures/mozilla/videos-2/source.html:830`; no PHP process-execution bridge was found.

## Test Run

Command: `php tools/run-tests.php`

Exit status: 0

Exact result:

```text
59 test files, 3290 assertions, 0 failures
```

## Recommended Next Intervention

Quiesce or explicitly coordinate active workers, then integrate or reject the current dirty lane changes. After that, regenerate `porting.html`, `porting-summary.json`, and the relevant `progress.md` lane table from the same committed state. Once status is honest again, prioritize markerPDF real benchmark PDF/reference parity and Gitoxide machine-checkable fixture normalization.
