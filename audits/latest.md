# Independent Audit - 2026-05-23T02:48:00Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane
status files needed to check dashboard/status drift, bridge/shell-out usage in
PHP files, and recent Git history through observed implementation commit
`de8da60` before this audit-only update.

I did not edit lane implementation files, launch agents or tmux sessions, or
push. The only intended writes from this pass are this audit and the
audit-status/next-intervention section in `progress.md`.

## Findings

1. **Critical - the green root harness is still not an accepted integration checkpoint.**
   - Paths: `progress.md:230`-`238`, `porting.html`, `porting-summary.json`,
     `lanes/*/lane-status.json`, and the dirty lane files listed by
     `git status`.
   - Evidence: `php tools/run-tests.php` exits `0` with `170 test files,
     15671 assertions, 0 failures`, but the worktree remains a broad dirty
     aggregate. During this audit, observed implementation commits advanced
     from `fb8810e` through `d570464`, `53fafba`, `ffffb68`, `cefd368`,
     `8925fcf`, `345cb39`, `b9a34b8`, `dcd7d16`, `d58816d`, and `de8da60`;
     interim audit commits `362ce29`, `62c1278`, and `b909661` also landed.
     The final status sample reported `274` `git status --short` entries,
     including `45` tracked modified entries, and `git diff --shortstat`
     reported `45 files changed, 7284 insertions(+), 200 deletions(-)`.
     Tracked dirt spans Dolt, Gitoxide, libsqlite, LightningCSS, markerPDF,
     Pandoc, Quadrable, rclone, Readability, Syncthing, generated dashboard
     files, and audit/status files. Some markerPDF lane files were already
     staged by another writer during this audit window.
   - Goal requirement at risk: `goal.md:29` requires small reviewable slices
     with passing tests; `goal.md:48` requires verification, commit, progress
     update, and cleanup before assigning the next slice; `goal.md:49` requires
     repo-wide checks to be recorded honestly.
   - Audit judgment: use this green run as diagnostic evidence. Freeze or
     explicitly coordinate writers, then accept or reject the dirty lane batches
     one lane at a time.

2. **Critical - `porting.html` and `porting-summary.json` are materially stale.**
   - Paths: `porting.html:30`-`64`, `porting-summary.json:1`-`210`.
   - Evidence: the dashboard still shows `Average progress: 14.3%` and
     `Generated: 2026-05-22 15:40:20 UTC`. It renders stale rows such as
     Difftastic `15 / 404`, Dolt `5 / 613`, Esbuild `16 / 2,567`, Gitoxide
     `737 / 2877`, LightningCSS `78 / 312`, markerPDF `11 / 27`, Pandoc
     `19 / 1979`, rclone `20 / 327`, Readability `89 / 1984`, and Syncthing
     `27 / 264`. Current manifests report materially different values:
     Difftastic `124 / 415`, Dolt `179 / 613`, Esbuild `138 / 2,567`,
     Gitoxide `1259 / 2877`, libsqlite `119 / 1454`, LightningCSS
     `689 / 3532`, markerPDF `78 / 78` with only `2` actual benchmark pairs,
     Pandoc `363 / 2028`, Quadrable `55 / 55`, rclone `243 / 327`,
     Readability `871 / 1984`, and Syncthing `193 / 658`.
   - Goal requirement at risk: `goal.md:3` requires durable current tracking;
     `goal.md:45` requires the dashboard to show current denominator, mapped
     tests, PHP pass/fail, audit, blocker, and commit; `goal.md:52` requires
     visible progress in `porting.html`.
   - Audit judgment: do not regenerate the dashboard from a moving dirty tree.
     Regenerate it only after the supervisor accepts a frozen green snapshot.

3. **High - `progress.md` still reports stale active-lane state and stale estimates.**
   - Paths: `progress.md:27`-`42`, `lanes/*/lane-status.json`.
   - Evidence: `progress.md` still says every lane is `stopped` and carries old
     estimates such as Gitoxide `66%`, LightningCSS `14%`, markerPDF `10%`,
     Pandoc `10%`, Quadrable `8%`, rclone `9%`, Dolt `5%`, and Esbuild `8%`.
     Current lane statuses claim very different estimates: Difftastic `45%`,
     Gitoxide `91%`, LightningCSS `60%`, markerPDF `54%`, libsqlite `64%`,
     Pandoc `60%`, Quadrable `67%`, rclone `63%`, Dolt `46%`, Readability
     `51%`, Syncthing `61%`, and Esbuild `43%`.
   - Goal requirement at risk: `goal.md:44` requires `progress.md` to include
     current active lanes, blockers, owner/session, next task per lane, and
     percentage estimates.
   - Audit judgment: update only this audit status now; refresh the full table
     after dirty lane batches are accepted or rejected.

4. **High - lane-local root-test/blocker claims contradict the latest required run.**
   - Paths: `lanes/lightningcss/lane-status.json:10`-`13`,
     `lanes/markerpdf/lane-status.json:10`-`13`,
     `lanes/pandoc/lane-status.json:10`-`13`,
     `lanes/rclone/lane-status.json:10`-`13`,
     `lanes/syncthing/lane-status.json:10`-`13`,
     plus older green counts in `lanes/difftastic/lane-status.json:10`,
     `lanes/gitoxide/lane-status.json:10`, `lanes/libsqlite/lane-status.json:10`,
     `lanes/quadrable/lane-status.json:10`, and `lanes/readability/lane-status.json:10`.
   - Evidence: LightningCSS still says the required root harness fails in
     Difftastic, Pandoc, and rclone. markerPDF still says root fails outside
     markerPDF. Pandoc and rclone still cite a Syncthing failure. Quadrable
     cites an unrelated Syncthing red root despite the latest green audit run.
     Gitoxide now cites a different green root total, `170 / 15719 / 0`, that
     was not this audit's recorded run. Syncthing reports root green before its
     commit, while other lanes cite older green root totals such as `167`,
     `168`, or `169` test files. The latest required run from this audit is
     green with `170 / 15671 / 0`.
   - Goal requirement at risk: `goal.md:31` requires blockers to be precise;
     `goal.md:49` requires failures to be recorded honestly.
   - Audit judgment: root-test status should be stamped centrally from the
     accepted snapshot instead of copied into lane-local prose.

5. **High - `latestCommit` fields are not reliable commit identifiers.**
   - Paths: `lanes/difftastic/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:13`, `lanes/gitoxide/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`, `lanes/pandoc/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`, `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`, `lanes/syncthing/lane-status.json:13`,
     `porting.html:53`-`64`, and `porting-summary.json:20`-`207`.
   - Evidence: lane status values include prose such as `pending current batch`,
     `pending local commit`, `not committed`, and `current batch`, while the
     dashboard still shows old or truncated values such as `pending`, `uncommi`,
     `2e1fcb0`, and `a184e4a`. The current observed implementation commit is
     `de8da60`, and several pending/prose fields cannot be machine-checked
     against it.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require tracking
     latest commit per lane.
   - Audit judgment: require a real accepted SHA plus a separate human note.
     Do not overload `latestCommit` with pending-state prose.

6. **High - upstream denominator units are still not comparable across lanes.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13`-`20`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`-`17`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`-`17`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:13`-`15`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:13`-`15`.
   - Evidence: Difftastic counts inspected behavior artifacts; Dolt mixes test
     files, BATS cases, Go functions, benchmarks, and fixtures; Gitoxide counts
     targeted inventory entries and fixture baselines; LightningCSS counts
     helper invocations and behavior checks; markerPDF counts repository paths
     plus `2` real benchmark pairs and `4` surrogate pairs; Pandoc counts
     files/artifacts; Readability counts Mocha tests; rclone counts Go test
     files while mapped is PHP behavior tests; Syncthing counts Go test and
     benchmark entry points plus focused path inventory.
   - Goal requirement at risk: `goal.md:25` requires a real upstream benchmark
     denominator; `goal.md:35`-`38` require meaningful fixture parity and
     upstream-test grounding; `goal.md:45` requires dashboard fields that can be
     interpreted consistently.
   - Audit judgment: split the schema into upstream files/artifacts, executable
     upstream tests, upstream behavior cases, mapped behavior cases, native PHP
     tests/checks/assertions, failures, runner parity, and bounded/static
     evidence class.

7. **Medium - high lane percentages can still read as upstream parity.**
   - Paths: `lanes/gitoxide/lane-status.json:9`-`13`,
     `lanes/markerpdf/lane-status.json:9`-`13`,
     `lanes/pandoc/lane-status.json:9`-`13`,
     `lanes/rclone/lane-status.json:9`-`13`,
     `lanes/syncthing/lane-status.json:9`-`13`.
   - Evidence: Gitoxide reports `91%` while full Cargo workspace tests remain
     unexecuted. Pandoc reports `60%` without Haskell runner parity. rclone
     reports `63%` from a bounded runner excluding live providers and mount/FUSE.
     Syncthing reports `61%` without full `go test ./...`. markerPDF reports
     `54%` while full benchmark/model execution remains unexecuted and much
     work is at supplied model/PDF boundaries.
   - Goal requirement at risk: `goal.md:35` says passing tests are not enough;
     `goal.md:37` says upstream tests are the source of truth; `goal.md:40`
     requires hard features to stay visible as blockers or future slices.
   - Audit judgment: show a parity class beside every percentage, so static or
     bounded evidence cannot read as native maturity.

8. **Medium - markerPDF still risks counting supplied model-boundary scaffolding as extraction progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`-`17`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:190`-`191`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:331`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:337`,
     `lanes/markerpdf/lane-status.json:9`-`13`.
   - Evidence: the manifest records `0` committed Python unit test files, `2`
     actual CI benchmark PDF/reference pairs, `4` surrogate pairs, and many
     native mappings at supplied pdftext/pypdfium/Surya/Texify/tabled/model
     boundaries. The full upstream benchmark runner remains `not-executed`
     because of the heavy Poetry/ML/PDF stack.
   - Goal requirement at risk: `goal.md:1` and `goal.md:30` forbid counting
     bridge calls, shell-outs, generated fixtures, or upstream boundary output as
     native port progress.
   - Audit judgment: keep markerPDF progress conservative until native
     document-level extraction parity broadens against actual benchmark pairs
     without treating supplied model outputs as native extraction.

## Bridge / Shell-Out Check

Command:

```text
rg -n 'shell_exec|\bexec\(|proc_open|passthru|system\(|popen\(|Symfony\\Component\\Process|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result:

```text
tools/generate-dashboard.php:183:    return trim((string) shell_exec($command . ' 2>/dev/null')) ?: 'unknown';
```

No lane implementation process-execution bridge calls were found. The remaining
match is dashboard Git metadata tooling and must not be counted as native port
progress.

## Test Run

Required command: `php tools/run-tests.php`

Exact result from this audit:

```text
exit status: 0
170 test files, 15671 assertions, 0 failures
```

This result was collected while `HEAD` was moving through the implementation
commits listed above and against the dirty worktree described above. It is
diagnostic until the supervisor accepts a frozen integration snapshot.

## Recommended Next Intervention

Freeze or explicitly coordinate active writers and preserve ownership of
in-flight lane batches. From the current green root state, accept or reject the
dirty lane batches one at a time, rerunning focused lane tests plus
`php tools/run-tests.php` for each accepted slice. Then regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
that same accepted snapshot; stamp root-test results and latest accepted SHAs in
one place; normalize denominator units and parity classes before publishing the
dashboard.
