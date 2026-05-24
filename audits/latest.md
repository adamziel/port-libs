# Independent Audit - 2026-05-24T01:50Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, `dependency-backlog.json`, `audits/latest.md`, and
recent Git history.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, plan-only wrappers,
and shell-outs are treated as non-progress unless they are explicitly temporary
oracle tooling.

`jq empty` passed for every lane manifest, every lane-status file,
`porting-summary.json`, and `dependency-backlog.json`.

## Current Snapshot

```text
HEAD movement during audit: 3b27d392 -> 60fc621a -> 7567e173 via integration-hold/status commits
HEAD at final sample: 7567e173439f
latest visible commits: 7567e173 Record integration hold status; 60fc621a Record integration hold status; 3b27d392 Refresh independent audit status
branch sample: main...origin/main [ahead 643, behind 68]
tracked dirty rows: 299
total status rows including untracked: 11264
git diff --shortstat: 299 files changed, 142360 insertions(+), 16881 deletions(-)
tmux sessions: 173
root run by this audit: not started
required pre-root gate at 2026-05-24T01:50:37Z: pgrep -af '^php tools/run-tests\.php( |$)' returned no rows
pre-finish gate at 2026-05-24T01:53:59Z: focused Syncthing PID 2435156 owned by claude was active (`php tools/run-tests.php lanes/syncthing/tests`)
final gate at 2026-05-24T01:54:44Z: pgrep -af '^php tools/run-tests\.php( |$)' returned no rows
```

## Findings

1. **Critical - the worktree is still not a valid aggregate verification or
   lane-acceptance target.**
   - Paths: `progress.md:37` through `progress.md:73`,
     `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:49` require small committed slices, verification, cleanup, and
     honest repo-wide test/static-check records.
   - Evidence: `HEAD` moved while this audit was reading the tree, the branch
     is `ahead 643, behind 68`, the checkout still has 299 tracked dirty rows
     and 11,264 total status rows, and every lane-status file still reports a
     pending, uncommitted, or not-committed handoff. A root PHP run from this
     shell would measure a moving aggregate, not an accepted snapshot.

2. **Critical - `porting.html` and `porting-summary.json` are stale enough to
   mislead reviewers.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:78`, `porting-summary.json`,
     `dependency-backlog.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require a current
     dashboard with denominator, mapped tests, PHP pass/fail, WordPress
     scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard still advertises generated time
     `2026-05-23 23:43:54 UTC` and snapshot `main 79768df0c427`, while the
     sampled source commit is `7567e173439f`. The dashboard rows show old
     handoffs such as Gitoxide `GitConfig`, markerPDF `Span.fix_unicode`,
     rclone VFS cache `Dump/AddVirtual`, and Syncthing `ConfigLdap`; current
     lane-status files describe Gitoxide pathspec, markerPDF PDF TJ array
     extraction, rclone serve ZIP responses, and Syncthing `/rest/svc/deviceid`.
     The dashboard dependency table still says 22 items and 12 candidates,
     while `dependency-backlog.json` has 23 items and 13 candidates.

3. **High - `progress.md` active-lane handoff labels lag the current
   lane-status handoffs.**
   - Paths: `progress.md:58` through `progress.md:71`,
     `lanes/gitoxide/lane-status.json:11`,
     `lanes/markerpdf/lane-status.json:11`,
     `lanes/pandoc/lane-status.json:11`,
     `lanes/rclone/lane-status.json:11`,
     `lanes/syncthing/lane-status.json:11`,
     `lanes/esbuild/lane-status.json:11`.
   - Goal requirement at risk: `goal.md:44` requires `progress.md` to show
     current lanes, current work, blockers, and next task state.
   - Evidence: `progress.md` still lists Gitoxide SSH config-options,
     markerPDF benchmark file-inventory, Pandoc figure/citation, Syncthing
     system log, rclone Statfs/usage, and esbuild automatic JSX key/spread
     handoffs. Current lane-status files instead describe Gitoxide pathspec,
     markerPDF nested PDF TJ text, Pandoc DOCX raw OpenXML, rclone serve ZIP
     response, Syncthing service-deviceid, and esbuild class-expression static
     method decorators.

4. **High - source status is internally inconsistent in active manifests, so
   mapped denominators cannot be trusted mechanically.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/difftastic/lane-status.json:5`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:10`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:38`, and
     `goal.md:45` require defensible, comparable upstream denominators and
     mapped-test counts.
   - Evidence: the Difftastic manifest now says 778 inspected artifacts and
     401 mapped, while its lane-status says 774 artifacts and 398 focused PHP
     tests. The markerPDF manifest says 341 total and 291 mapped, while its
     lane-status/audit prose still says 340 total and 290 mapped. These are
     not accepted snapshot differences; they are source-of-truth files
     disagreeing inside the current checkout.

5. **High - every primary lane still reports unaccepted lane output.**
   - Paths: `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/libsqlite/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/pandoc/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29` and `goal.md:48` require
     committed, reviewable slices after verification and cleanup.
   - Evidence: the lane files all defer commit/root acceptance to the
     supervisor or integrator. Focused lane-green evidence is useful, but it is
     not accepted native progress until one lane batch is isolated, verified,
     committed, and reflected in generated coordination artifacts.

6. **High - markerPDF continues to over-credit runtime orchestration and
   external-app planning as mapped native port progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:427`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:905` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:941`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:9`,
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:9`, `goal.md:25`,
     `goal.md:30`, `goal.md:31`, and `goal.md:40` require native PHP
     implementation, no wrapper/shell-out progress credit, and explicit hard
     blockers.
   - Evidence: the lane has legitimate native work in `PdfTextExtractor`, but
     its mapped surface also includes Pandoc/XeLaTeX helper command planning,
     `chunk_convert.py` subprocess/shell planning, `chunk_convert.sh`
     lifecycle planning, Streamlit/FastAPI/Uvicorn app planning, Poetry/package
     setup, OCR/Tesseract/Ghostscript install readiness, model/runtime
     preflights, and GitHub Actions publishing/CLA workflow plans. Those should
     remain preflight metadata or blockers, not mapped native extraction
     parity.

7. **High - essential optional-library coverage is still backlog-only, and
   rclone is growing lane-local ZIP code without the support-library quality
   gate.**
   - Paths: `progress.md:17` through `progress.md:24`,
     `dependency-backlog.json:7` through `dependency-backlog.json:20`,
     `dependency-backlog.json:110`,
     `dependency-backlog.json:321` through `dependency-backlog.json:395`,
     `lanes/rclone/src/VfsZipArchive.php:8` through
     `lanes/rclone/src/VfsZipArchive.php:13`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1242` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1243`,
     `lanes/rclone/lane-status.json:5`,
     `lanes/rclone/lane-status.json:11`.
   - Goal requirement at risk: `goal.md:9`, `goal.md:12`, `goal.md:24`
     through `goal.md:31`, `goal.md:35`, and the support-library audit
     instruction require bounded native PHP components, activation gates,
     dependency-specific denominators, mapped fixtures, PHP evidence, malformed
     or corrupt cases where relevant, and no hidden shell-outs.
   - Evidence: `dependency-backlog.json` has 23 items and zero active
     manifest-backed support ports. Rich gaps remain for ZIP/package
     containers, DOCX/OpenXML, doctemplates, protobuf/BEP wire format, archive
     compression, XML/HTML5, CFB `.doc`, EPUB/ODT, CSL, math/TeX, PDF
     text/render/OCR/table geometry, Unicode/charset, source maps, checksums,
     SQL/storage codecs, glob/pathspec, and provider metadata normalization.
     Rclone's `VfsZipArchive` can be a valid lane-local slice, but it is not
     `shared-zip-package-core` until it has a support-library manifest,
     activation gate, spec/upstream denominator, mapped cross-lane fixtures,
     corrupt archive cases, and root evidence.

8. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56` through
     `porting.html:67`, `lanes/difftastic/lane-status.json:5`,
     `lanes/gitoxide/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/pandoc/lane-status.json:5`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:35`, `goal.md:37`, `goal.md:38`, and
     `goal.md:40` require upstream tests as source of truth and honest hard
     feature blockers.
   - Evidence: the dashboard advertises 97.7% average progress and 92-99%
     lane progress while major upstream parity is still unexecuted or excluded:
     Difftastic full Cargo, Gitoxide full Cargo workspace, Pandoc Haskell
     `test-pandoc`, Syncthing full `go test ./...`, markerPDF full PDF/model
     benchmark runner, rclone provider/mount/live-service parity, esbuild
     `make test-all`, and libsqlite all/release permutations.

9. **Medium - manifest/status schemas remain non-normalized across lanes.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/dolt/lane-status.json:6`,
     `lanes/markerpdf/lane-status.json:6`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and `goal.md:45`
     require comparable denominator, mapped-test, and PHP pass/fail fields.
   - Evidence: `benchmarkDenominator.total` is a narrative string in
     Difftastic and Pandoc, Pandoc also has `totalCount`, Dolt omits a numeric
     total while reporting `mapped: 613`, and PHP pass values mix behavior-test
     counts, selected test-file counts, and assertion counts. The dashboard
     collapses these into display strings, so accepted parity cannot be
     computed safely.

10. **Medium - blocker fields still start with local-green wording instead of
    acceptance blockers.**
    - Paths: `lanes/dolt/lane-status.json:12`,
      `lanes/esbuild/lane-status.json:12`,
      `lanes/gitoxide/lane-status.json:12`,
      `lanes/libsqlite/lane-status.json:12`,
      `lanes/markerpdf/lane-status.json:12`,
      `lanes/rclone/lane-status.json:12`,
      `lanes/readability/lane-status.json:12`,
      `lanes/syncthing/lane-status.json:12`.
    - Goal requirement at risk: `goal.md:31` and `goal.md:40` require precise
      blockers and explicit marking of unported hard features.
    - Evidence: several blockers begin with "No current" or "No focused"
      blocker, then later acknowledge pending root verification, uncommitted
      dirty batches, unexecuted full upstream runners, excluded providers or
      services, model-heavy markerPDF execution, or broad hydration/build
      limits. The unresolved acceptance blocker should be first.

## Test Gate

I did not run `php tools/run-tests.php`.

The required pre-root gate was checked before considering a root run:

```text
2026-05-24T01:50:37Z
pgrep -af '^php tools/run-tests\.php( |$)'
<no matches>
```

A pre-finish gate found active focused PHP harness activity:

```text
2026-05-24T01:53:59Z
pgrep -af '^php tools/run-tests\.php( |$)'
2435156 php tools/run-tests.php lanes/syncthing/tests

owner evidence:
2435156 claude 2397508 00:47 Rs php tools/run-tests.php lanes/syncthing/tests
```

The tree was not stable enough for an audit-owned root harness: `HEAD` moved
during the audit, 173 tmux sessions were present, all lane outputs remained
dirty/unaccepted, and a pre-finish process gate briefly matched active focused
PHP harness activity before the final gate cleared.

## Next Intervention

Freeze active writers, status publishers, and root/focused runners; take two
stable polls of `HEAD`, `git status`, and the root harness process gate; choose
one lane batch; normalize that lane's manifest/status schema; run its focused
verification plus `git diff --check`; then run exactly one no-argument
`php tools/run-tests.php` from that frozen snapshot if the duplicate-root gate
is still empty. Commit or reject that one batch, regenerate dashboard artifacts
from the accepted commit, and only then move to the next lane or activate a
support-library gate.
