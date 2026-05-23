# Independent Audit - 2026-05-23T04:42:36Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, selected
`lanes/*/lane-status.json` files needed to verify status drift, PHP shell-out
usage in `lanes/`, `tools/`, and `scripts/`, and recent Git history observed
through `39dd5609`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
or count bridge/generated/oracle tooling as native implementation progress.

## Findings

1. **High - dashboard and progress surfaces are stale enough to mislead coordination.**
   - Paths: `porting.html:32`-`36`, `porting.html:54`-`65`,
     `porting-summary.json:2`-`8`, `progress.md:31`-`42`,
     `progress.md:241`-`249`.
   - Requirement at risk: `goal.md:3`, `goal.md:44`, `goal.md:45`, and
     `goal.md:49` require current coordination, visible per-lane dashboard
     status, and honest repo-wide test recording.
   - Evidence: `porting.html` and `porting-summary.json` still publish a
     `2026-05-23 03:09:50 UTC` snapshot from `3f4ea3cda693`, while recent
     history now reaches `39dd5609`. Current manifest mapped counts no longer
     match the dashboard: difftastic is `160` versus dashboard `139`, Dolt
     `242` versus `197`, esbuild `163` versus `150`, Gitoxide `1432` versus
     `1358`, libsqlite `149` versus `129`, LightningCSS `773` versus `703`,
     markerPDF `157` versus `81`, Pandoc `426` versus `397`, rclone `291`
     versus `265`, Readability `1031` versus `907`, and Syncthing `232`
     versus `204`.
   - Audit judgment: do not use the generated dashboard as the current
     portfolio source of truth until it is regenerated from one accepted,
     tested snapshot.

2. **High - the green root harness is a dirty moving-tree sample, not an accepted integration checkpoint.**
   - Paths: worktree-wide; representative dirty paths include
     `tools/run-tests.php`, `lanes/dolt/src/ConstraintViolationsTable.php`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/syncthing/src/PullItemUpdater.php`, `porting.html`, and
     `porting-summary.json`.
   - Requirement at risk: `goal.md:29`, `goal.md:31`, `goal.md:48`, and
     `goal.md:49` require small reviewable slices, verified integration, and
     recorded repo-wide tests.
   - Evidence: this audit observed additional implementation/status commits
     after the prior audit commit, including `c4f3096e` and `39dd5609`.
     The latest status sample reports `493` `git status --short` entries:
     `40` tracked modified entries and `453` untracked entries.
     `git diff --shortstat` reports
     `40 files changed, 10671 insertions(+), 211 deletions(-)`. The root
     harness itself is dirty and now serializes runs through an uncommitted
     `.upstream-cache/run-tests.lock` path.
   - Audit judgment: the root test result below is useful smoke evidence, but
     it must not be treated as release/integration proof until the supervisor
     freezes writers and accepts or rejects dirty lane batches.

3. **High - manifest denominator units are still mixed, so progress percentages are not machine-checkable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:13`-`15`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13`-`18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`-`17`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`-`17`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13`-`18`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:13`-`16`.
   - Requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:38`, and
     `goal.md:45` require real upstream benchmark denominators, mapped
     upstream tests, explicit slices for huge suites, and separate dashboard
     columns.
   - Evidence: Gitoxide uses upstream file count as `total` while mapped
     progress is behavior slices and bounded runner probes. Difftastic mixes
     Rust test attributes, golden pairs, numbered fixtures, parser corpora, and
     targeted source files. Dolt mixes test files, BATS cases, Go test
     functions, benchmark functions, fixture paths, and reference matches.
     markerPDF reports path inventory plus benchmark pairs, surrogate pairs,
     and supplied-document excerpts, then maps more units than its dashboard
     denominator. Quadrable reports `55 tracked upstream paths` plus `34`
     upstream scenarios and assertion counts, but the dashboard reduces that to
     a single `55 / 55`.
   - Audit judgment: normalize manifests into typed numeric fields before
     percentages are used for planning or publication.

4. **Medium - lane status/root-test prose remains contradictory after the current green run.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/dolt/lane-status.json`, `lanes/gitoxide/lane-status.json`,
     `lanes/syncthing/lane-status.json`, `progress.md:241`-`249`.
   - Requirement at risk: `goal.md:39`, `goal.md:44`, and `goal.md:49` require
     precise blockers, current lane status, and honest repo-wide test
     recording.
   - Evidence: lane metadata still carries older red-root or dirty-batch prose
     while the required command in this audit exits `0` with `183 test files,
     18285 assertions, 0 failures`. `progress.md` also had to be updated from
     the prior `18228`-assertion sample after more lane changes landed.
   - Audit judgment: lane status files need a single-source root-test stamp
     tied to an accepted SHA instead of copied-forward lane-local prose.

5. **Medium - bounded/static upstream evidence is still easy to overread as full parity.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:17`-`20`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`-`19`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`-`17`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13`-`16`.
   - Requirement at risk: `goal.md:35`-`40` require upstream tests as the
     source of truth and hard features to be marked as blockers or future
     slices.
   - Evidence: Difftastic, Gitoxide, markerPDF, Pandoc, and Syncthing still do
     not have full upstream runner parity. rclone and Dolt have useful bounded
     runner evidence but still exclude provider/mount/live-service, full BATS,
     full Go, or broader integration coverage. The dashboard collapses these
     evidence classes into progress percentages.
   - Audit judgment: make evidence class (`full-runner`, `bounded-runner`,
     `static-inventory`, `oracle-fixture`) first-class in manifests, status
     files, and the dashboard.

## Bridge / Shell-Out Check

Command:

```text
rg -n 'proc_open|shell_exec|passthru|system\(|popen\(|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result:

```text
tools/generate-dashboard.php:183:    return trim((string) shell_exec($command . ' 2>/dev/null')) ?: 'unknown';
```

No lane PHP shell-out was found. The only PHP shell-out match is dashboard
coordination tooling.

## Test Run

Required command:

```text
php tools/run-tests.php
```

Exact result for this audit:

```text
exit status: 0
183 test files, 18285 assertions, 0 failures
```

The command first waited on `/home/claude/port-libs/.upstream-cache/run-tests.lock`
because another root run was active. This is a green dirty-worktree sample, not
an accepted integration checkpoint, because the tree moved during the audit and
still has `493` status entries in the latest sample.

## Recommended Next Intervention

Freeze active writers. Accept or reject dirty lane batches one lane at a time,
remove stale red-root and dirty-batch blocker prose, stamp accepted SHAs and a
single root-test result, normalize manifest denominator/evidence fields, then
regenerate `progress.md`, `porting.html`, and `porting-summary.json` from that
same green snapshot.
