# Independent Audit - 2026-05-23T04:02:37Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, selected
`lanes/*/lane-status.json` files needed to verify status drift, PHP shell-out
usage in `lanes/`, `tools/`, and `scripts/`, and recent Git history.

Recent history observed during the run: `66b4b84f`, `2eb7c44a`, `efc4ab23`,
`38173835`, `8c6d732e`, `6a4c9ec2`, `10021651`, `6d424207`, `94992feb`,
`8244f1ee`, `028890f1`, and `c80db50f`. `HEAD` moved while this audit was
reading, testing, and writing, from `10021651` through `6a4c9ec2`, `8c6d732e`,
`38173835`, `efc4ab23`, `2eb7c44a`, and `66b4b84f`, so the test result below is
a moving dirty-worktree sample, not an accepted integration checkpoint. I did
not edit lane implementation files, launch agents or tmux sessions, push, or
treat generated fixtures/bridge tooling as native implementation progress.

## Findings

1. **Critical - the root harness is green, but the portfolio still lacks an accepted, reproducible snapshot.**
   - Paths: worktree-wide; `progress.md:238`-`246`; `porting.html:32`-`36`;
     `porting-summary.json:2`-`8`.
   - Requirement at risk: `goal.md:29`, `goal.md:44`, `goal.md:45`, and
     `goal.md:49` require small reviewable slices, precise coordination
     status, a current dashboard, and repo-wide test recording.
   - Evidence: the final required root command in this audit exited `0` with
     `182` test files, `17732` assertions, and `0` failures. However, `HEAD`
     moved during the audit, and the final observed tree remains broad and
     unaccepted: `git status --short` reported `451` entries,
     `git status --short --untracked-files=no` reported `56` tracked entries,
     and `git diff --shortstat` reported `56 files changed, 11028 insertions(+),
     431 deletions(-)`.
   - Audit judgment: the red esbuild/LightningCSS blocker recorded in the
     previous audit is no longer current, but the green root result is not yet a
     stable integration checkpoint. Freeze writers or coordinate a clean
     accept/reject pass before publishing status.

2. **High - `porting.html` and `porting-summary.json` are stale and still do not expose the goal's required columns cleanly.**
   - Paths: `porting.html:32`-`36`, `porting.html:41`-`65`,
     `porting-summary.json:2`-`8`, `porting-summary.json:15`-`18`,
     `porting-summary.json:32`-`35`, `porting-summary.json:117`-`120`,
     `porting-summary.json:185`-`188`, `porting-summary.json:202`-`205`.
   - Requirement at risk: `goal.md:3` and `goal.md:45` require browsing status
     by upstream benchmark denominator, mapped upstream tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and latest
     commit.
   - Evidence: the dashboard still claims generated time
     `2026-05-23 03:09:50 UTC` and source commit `3f4ea3cda693`, while the final
     observed `HEAD` is `66b4b84f`. It collapses benchmark source plus
     denominator into one `Benchmark` column and PHP pass/fail plus mapped tests
     into one `Mapped` column. Current drift examples: Difftastic dashboard
     `139` mapped vs manifest/status `152`; Dolt `197` mapped and `171` PHP
     pass vs manifest/status `209` mapped and `184` PHP pass; esbuild `150` vs
     `159`; Gitoxide `1358` mapped vs manifest `1399`; libsqlite `129` vs
     `138`; LightningCSS `703` vs manifest `756`; markerPDF still renders
     `81 / 78` while the manifest now says `86 / 78`; Pandoc `397` vs `413`;
     rclone `265` vs `281`; Readability `907` vs `999`; Syncthing `204` vs
     `221`.
   - Audit judgment: regenerate the dashboard only from a frozen accepted
     green snapshot, and keep denominator, mapped-test, and PHP pass/fail fields
     separate.

3. **High - lane status files contain stale blockers and non-auditable commit fields.**
   - Paths: `lanes/esbuild/lane-status.json:10`-`13`,
     `lanes/markerpdf/lane-status.json:10`-`13`,
     `lanes/pandoc/lane-status.json:10`-`13`,
     `lanes/gitoxide/lane-status.json:10`-`13`,
     `lanes/lightningcss/lane-status.json:10`-`13`,
     `lanes/difftastic/lane-status.json:10`-`13`,
     `lanes/syncthing/lane-status.json:10`-`13`.
   - Requirement at risk: `goal.md:3`, `goal.md:29`, `goal.md:44`, and
     `goal.md:45` require trustworthy audit, blocker, current work, and latest
     commit fields.
   - Evidence: esbuild still says the required root run is red in
     LightningCSS; markerPDF still says the root run is red in esbuild; Pandoc
     still says the root run is red in LightningCSS; Gitoxide still cites
     `181` files and `17549` assertions after the current root pass is
     `182`/`17732`; LightningCSS records a `TMPDIR` workaround even though the
     plain required command passed in this audit. Several `latestCommit` fields
     are prose or dirty-batch labels (`pending inline display slice`,
     `committed lane batch; root green`, `Map Syncthing directory and symlink
     item lifecycle; root harness green`) rather than accepted SHAs.
   - Audit judgment: split lane-local verification, latest root sample,
     accepted snapshot SHA, and dirty-batch label into distinct fields. Do not
     put prose in the commit column.

4. **High - manifest denominator units and schema shapes remain too mixed for machine-checkable progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12`-`18`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:12`-`21`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:12`-`20`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12`-`17`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:12`-`16`.
   - Requirement at risk: `goal.md:25`, `goal.md:35`, and `goal.md:38` require
     a real upstream denominator, upstream tests as source of truth, and
     explicit slices for huge suites.
   - Evidence: markerPDF reports `mapped: 86` against a visible denominator of
     `78 tracked upstream repository paths`. Difftastic mixes Rust test
     attributes, golden pairs, numbered fixture pairs, directory pairs, parser
     corpus files, and source files into one `417` denominator. Dolt's visible
     denominator is executable test files while the same text also cites BATS
     cases, Go functions, benchmarks, and fixture artifacts. Gitoxide uses
     upstream files as the denominator while mapped progress is behavior slices
     and PHP assertions. Quadrable's `total` is a prose string combining paths,
     upstream scenarios, verify checks, and runner status. LightningCSS has
     `mapped: 756` at `benchmarkDenominator.mapped`, while its warning text
     still says native PHP maps `736` checks. `benchmarkDenominator.runnerStatus`
     is an object in some manifests, a string in Gitoxide/markerPDF/Quadrable,
     and absent/null in Pandoc.
   - Audit judgment: normalize manifests into explicit fields for upstream
     files, executable test cases, behavior cases, mapped cases, PHP tests,
     assertions, failures, runner parity, evidence type, and accepted snapshot.

5. **Medium - bounded/static upstream evidence is still easy to overread as full parity.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:184`-`199`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:17`-`20`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:205`-`206`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:17`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/rclone/lane-status.json:10`-`12`,
     `lanes/dolt/lane-status.json:10`-`12`.
   - Requirement at risk: `goal.md:35`-`40` require upstream tests as the source
     of truth and hard features to be marked as blockers or future slices.
   - Evidence: Difftastic, Gitoxide, markerPDF, Pandoc, and Syncthing still do
     not have full upstream runner parity. Rclone and Dolt have useful bounded
     runner evidence, but still exclude full provider/mount, full BATS, or full
     Go coverage. These distinctions are present in notes, but they are not
     consistently visible in dashboard/status percentage displays.
   - Audit judgment: keep bounded/static/full runner evidence as first-class
     fields everywhere progress percentages are shown.

## Bridge / Shell-Out Check

Command:

```text
rg -n 'proc_open|shell_exec|passthru|system\(|popen\(|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result:

```text
tools/generate-dashboard.php:183:    return trim((string) shell_exec($command . ' 2>/dev/null')) ?: 'unknown';
```

No lane PHP shell-out was found. The only current PHP shell-out match is
dashboard coordination tooling.

## Test Run

Required command:

```text
php tools/run-tests.php
```

Final completed result for this audit:

```text
exit status: 0
182 test files, 17732 assertions, 0 failures
```

An earlier run in this same audit also exited `0` with `182` test files,
`17666` assertions, and `0` failures, but `HEAD` moved afterward. `HEAD`
continued to move after the final root run; the final observed `HEAD` while
writing the audit was `66b4b84f`, and no post-`66b4b84f` root rerun was
performed.

## Recommended Next Intervention

Freeze or explicitly coordinate active writers, then accept or reject the dirty
lane batches one lane at a time. For each accepted batch, stamp real accepted
SHAs, rerun focused lane tests plus `php tools/run-tests.php`, and regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and `lane-status.json`
from the same accepted green snapshot with normalized denominator, mapped-case,
native-test, runner-parity, blocker, root-test, and latest-commit fields.
