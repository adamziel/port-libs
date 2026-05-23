# Independent Audit - 2026-05-23T02:02:00Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
lane status files needed to check dashboard/status drift, bridge/shell-out
usage in PHP files, and recent Git history through observed `HEAD` `8958bf3`.

I did not edit lane implementation files, launch agents or tmux sessions, or
push. The only intended writes from this pass are this audit and the audit
status/next-intervention section in `progress.md`.

## Findings

1. **Critical - the current green root harness is not an accepted integration checkpoint.**
   - Paths: `progress.md:230`-`238`, `porting.html`, `porting-summary.json`,
     `lanes/*/lane-status.json`, and the dirty lane files listed by
     `git status`.
   - Evidence: `php tools/run-tests.php` now exits `0` with `169 test files,
     15599 assertions, 0 failures`, but the tree is still a broad dirty
     aggregate. During this audit, observed `HEAD` moved from `6953fd2` through
     `eff561a`, `c6f7db5`, `e66d6b4`, `ce87bde`, `18d707c`, `453e279`,
     `391f457`, and `cf1ef8f` to `8958bf3`; the latest status sample reported
     `276` `git status --short` entries, `48` tracked modified entries, and
     `git diff --shortstat` reported `48 files changed, 6925 insertions(+),
     295 deletions(-)`. Tracked dirt spans Difftastic, Esbuild, LightningCSS,
     Pandoc, rclone, Readability, Syncthing, generated dashboard files, and
     audit/status files.
   - Goal requirement at risk: `goal.md:29` requires small reviewable slices
     with passing tests; `goal.md:48` requires verified/committed cleanup before
     assigning the next slice; `goal.md:49` requires repo-wide checks to be
     recorded honestly.
   - Audit judgment: use the green run as diagnostic evidence only. Freeze or
     explicitly coordinate writers, then accept or reject one lane handoff at a
     time before publishing status.

2. **Critical - `porting.html` and `porting-summary.json` are materially stale.**
   - Paths: `porting.html:30`-`64`, `porting-summary.json:2`-`20`.
   - Evidence: the dashboard still shows `Average progress: 14.3%` and
     `Generated: 2026-05-22 15:40:20 UTC`. It renders old rows such as
     Difftastic `15 / 404`, Dolt `5 / 613`, Esbuild `16 / 2,567`, Gitoxide
     `737 / 2877`, LightningCSS `78 / 312`, markerPDF `11 / 27`, Pandoc
     `19 / 1979`, rclone `20 / 327`, Readability `89 / 1984`, and Syncthing
     `27 / 264`. Current manifests/status files report materially different
     values, including Difftastic `122 / 413`, Dolt `178 / 613`, Esbuild
     `136 / 2,567`, Gitoxide `1254 / 2877`, libsqlite `118 / 1454`,
     LightningCSS `680 / 3532`, markerPDF `78 / 78` with only `2` actual
     benchmark pairs, Pandoc `355 / 2028`, rclone `234 / 327`, Readability
     `848 / 1984`, and Syncthing `191 / 658`.
   - Goal requirement at risk: `goal.md:3` requires current coordination
     tracking; `goal.md:45` requires the dashboard to show current denominator,
     mapped tests, PHP pass/fail, audit, blocker, and commit; `goal.md:52`
     requires visible progress in `porting.html`.
   - Audit judgment: regenerate dashboard artifacts only from the same frozen
     green snapshot that the supervisor accepts for integration.

3. **High - `progress.md` still reports stale active-lane state.**
   - Paths: `progress.md:29`-`42`, `progress.md:230`-`238`.
   - Evidence: the Active Lanes table says every lane is `stopped` and carries
     old estimates such as Gitoxide `66%`, LightningCSS `14%`, markerPDF `10%`,
     Pandoc `10%`, Quadrable `8%`, rclone `9%`, Dolt `5%`, and Esbuild `8%`.
     Current lane statuses claim very different bounded estimates such as
     Gitoxide `91%`, LightningCSS `59%`, markerPDF `53%`, libsqlite `63%`,
     Pandoc `60%`, Quadrable `66%`, rclone `62%`, Dolt `46%`, and Esbuild
     `43%`.
   - Goal requirement at risk: `goal.md:44` requires `progress.md` to include
     current active lanes, blockers, owner/session, next task per lane, and
     percentage estimates.
   - Audit judgment: update only this audit status now; refresh the full table
     after the dirty lane batches are accepted or rejected.

4. **High - lane-local root-test/blocker claims still contradict the latest green run.**
   - Paths: `lanes/esbuild/lane-status.json:10`-`13`,
     `lanes/lightningcss/lane-status.json:10`-`12`,
     `lanes/rclone/lane-status.json:10`-`13`,
     `lanes/quadrable/lane-status.json:10`-`12`,
     `lanes/markerpdf/lane-status.json:10`,
     `lanes/difftastic/lane-status.json:10`,
     `lanes/readability/lane-status.json:10`,
     `lanes/syncthing/lane-status.json:10`.
   - Evidence: Esbuild says the required root harness is blocked by unrelated
     Difftastic, Pandoc, and rclone failures at `169 / 15588 / 3`.
     LightningCSS, rclone, and Syncthing still cite an unrelated markerPDF
     failure. markerPDF says root passed with `15498` assertions; Difftastic
     and Pandoc still cite older `167`-file runs; Readability cites
     `168 / 15514 / 0`; Gitoxide, libsqlite, and Quadrable cite
     `168 / 15524 / 0`. The latest audit-run command is green with
     `169 / 15599 / 0`.
   - Goal requirement at risk: `goal.md:31` requires precise blockers, and
     `goal.md:49` requires checks to be recorded honestly.
   - Audit judgment: root-test status should be centralized or stamped from one
     accepted run, not copied as lane-local narrative text.

5. **High - upstream denominator units are still not comparable across lanes.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13`-`21`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:13`-`16`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`-`18`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`-`17`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:13`-`17`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:13`-`16`.
   - Evidence: Difftastic counts inspected behavior artifacts, Dolt mixes test
     files with BATS cases and Go functions, Gitoxide counts upstream files and
     targeted fixture archives, LightningCSS counts helper invocations/behavior
     checks, markerPDF counts repository/source paths plus two benchmark pairs,
     Pandoc counts files/artifacts, Readability counts Mocha tests, and
     Syncthing counts Go test/benchmark entry points plus focused paths.
   - Goal requirement at risk: `goal.md:25` requires a real benchmark
     denominator; `goal.md:35`-`38` require meaningful fixture parity and
     upstream-test grounding; `goal.md:45` requires dashboard fields that can be
     interpreted consistently.
   - Audit judgment: split denominator schema into explicit fields for upstream
     files/artifacts, executable upstream tests, upstream behavior cases,
     mapped behavior cases, native PHP tests/checks/assertions, failures,
     runner parity, and bounded/static evidence.

6. **Medium - high lane percentages can still read as upstream parity.**
   - Paths: `lanes/gitoxide/lane-status.json:4`-`12`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`-`17`,
     `lanes/rclone/lane-status.json:4`-`12`,
     `lanes/syncthing/lane-status.json:4`-`12`,
     `lanes/markerpdf/lane-status.json:4`-`12`.
   - Evidence: Gitoxide reports `91%` with full Cargo workspace tests still not
     executed; Pandoc reports `60%` from static inventory with no Haskell runner
     parity; rclone reports `62%` from a bounded runner that excludes live
     providers and mount/FUSE; Syncthing reports `60%` without full
     `go test ./...`; markerPDF reports `53%` while the full benchmark/model
     stack is not executed.
   - Goal requirement at risk: `goal.md:35` says passing tests are not enough;
     `goal.md:37` says upstream tests are the source of truth; `goal.md:40`
     requires hard features to remain visible as blockers or future slices.
   - Audit judgment: show a parity class beside every percentage so bounded or
     static evidence cannot read as port maturity.

7. **Medium - markerPDF still risks counting supplied model-boundary scaffolding as extraction progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`-`18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:24`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:190`-`191`,
     `lanes/markerpdf/lane-status.json:5`, `lanes/markerpdf/lane-status.json:12`.
   - Evidence: the manifest records `0` committed Python unit test files,
     `2` actual CI benchmark PDF/reference pairs, `4` surrogate pairs, and many
     native mappings at supplied pdftext/pypdfium/Surya/Texify/tabled
     boundaries. The full upstream benchmark runner remains `not-executed`
     because of the heavy Poetry/ML/PDF stack.
   - Goal requirement at risk: `goal.md:1` and `goal.md:30` forbid counting
     bridge calls, shell-outs, or generated/supplied oracle output as native
     port progress.
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

Latest exact result from this audit:

```text
exit status: 0
169 test files, 15599 assertions, 0 failures
```

This result was collected after the previous markerPDF red was fixed and while
`HEAD` was moving through the latest observed `8958bf3` state. Because the
worktree remains broad and dirty, the
result is diagnostic until the supervisor accepts a frozen integration snapshot.

## Recommended Next Intervention

Freeze or explicitly coordinate active writers and preserve ownership of
in-flight lane batches. Accept or reject the current dirty lane batches one at a
time from the green root state, rerunning focused lane tests plus
`php tools/run-tests.php` for each accepted slice. Then regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
that same accepted snapshot; clear stale root-test strings and pending/prose
latest-commit fields; normalize dashboard/status fields for upstream
denominator units, mapped upstream cases, native tests/checks/assertions,
failures, runner parity, and latest accepted commit.
