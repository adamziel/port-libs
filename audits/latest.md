# Independent Audit - 2026-05-24T01:45Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, `dependency-backlog.json`,
`audits/integration-status.md`, and recent Git history.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, plan-only wrappers,
and shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

`jq empty` passed for every lane manifest, every lane-status file,
`porting-summary.json`, and `dependency-backlog.json`.

## Current Snapshot

```text
HEAD at audit edit: 296f433e0ee4
HEAD movement during audit: 061709630fb9 -> e921208ac50a -> 0a41ba20 -> 296f433e0ee4 via integration-hold and audit/status commits
latest visible commits: 296f433e Refresh independent audit status; 0a41ba20 Record integration hold status; e921208a Record integration hold status
recent history: latest sampled commits are audit/status/integration-hold commits, not accepted lane feature commits
branch sample: main...origin/main [ahead 641, behind 68]
tracked dirty rows: 297
total status rows including untracked: 11249
git diff --shortstat: 297 files changed, 141715 insertions(+), 16649 deletions(-)
tmux sessions: 172
root run by this audit: not started; a post-commit pre-finish gate briefly matched active no-argument root PID 2324743 before a later sample cleared
```

## Findings

1. **Critical - the current worktree is still not an acceptable aggregate
   verification or lane-acceptance target.**
   - Paths: `progress.md:39`, `progress.md:55` through `progress.md:72`,
     `audits/integration-status.md:3` through
     `audits/integration-status.md:65`, `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:49` require small committed slices, verification, cleanup, and
     honest repo-wide test/static-check records.
   - Evidence: `HEAD` moved while this audit was running, from
     `061709630fb9` through integration-hold commits to the audit/status
     surface. The newest integration-hold status says no lane output was
     integrated, no dashboard/progress artifacts were regenerated, and recent
     root results belong to moving snapshots. Current status still shows 297
     tracked dirty rows, 11,249 total rows including untracked files, a
     297-file diff, and 172 tmux sessions. A root run from this shell would
     measure a moving worker queue, not one accepted snapshot.

2. **Critical - `porting.html` and `porting-summary.json` are stale and
   contradict current manifest/status data.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:67`,
     `porting.html:75` through `porting.html:77`,
     `porting-summary.json`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     denominator, mapped-test, PHP pass/fail, WordPress scenario, phase, audit,
     current-work, blocker, and commit data in the dashboard.
   - Evidence: the dashboard still advertises generated time
     `2026-05-23 23:43:54 UTC` and snapshot `main 79768df0c427`, while
     current `HEAD` is `e921208ac50a`. Current manifests/statuses have moved:
     Difftastic is now 398 mapped over 774 artifacts, not 374/735; Gitoxide is
     2873/2877 in the manifest, not dashboard 2751/2877; libsqlite is 296
     mapped, not 286; LightningCSS is 1902 mapped, not 1732; markerPDF is
     290/340, not 280/330; Pandoc is 1177/2276, not 1061/2276; rclone is
     736/1601, not 698/1601; Readability focused PHP is 215 tests, not 204;
     Syncthing current work is `/rest/svc/lang`, not dashboard `ConfigLdap`.
     The dashboard dependency table says 22 items and 12 candidates, while
     `dependency-backlog.json` has 23 items and 13 candidates.

3. **High - `progress.md` active-lane handoff labels lag current handoffs.**
   - Paths: `progress.md:57` through `progress.md:70`,
     `lanes/gitoxide/lane-status.json:11`,
     `lanes/pandoc/lane-status.json:11`,
     `lanes/rclone/lane-status.json:11`,
     `lanes/syncthing/lane-status.json:11`.
   - Goal requirement at risk: `goal.md:44` requires `progress.md` to show
     active lanes, current owner/session, next task, open blockers, and current
     status.
   - Evidence: `progress.md` still lists Gitoxide SSH config-options,
     markerPDF benchmark file-inventory, Pandoc NativeWriter figure/citation,
     Syncthing system log, rclone VFS Statfs/usage, and esbuild automatic JSX
     key/spread handoffs. Current lane-status files instead describe Gitoxide
     gix-ignore, markerPDF PDF TJ nested-literal extraction, Pandoc DOCX raw
     bookmark/raw-block handling, Syncthing service-language, rclone serve ZIP
     response, and esbuild class-expression method decorators. The human
     coordination file is no longer a reliable current-work source.

4. **High - manifest/status writes are non-atomic and internally
   inconsistent across at least Gitoxide and Pandoc.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/gitoxide/lane-status.json:5` through
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:34`,
     `lanes/pandoc/lane-status.json:5` through
     `lanes/pandoc/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and `goal.md:45`
     require comparable per-lane denominator, mapped-test, PHP pass/fail,
     current-work, blocker, and commit state.
   - Evidence: the current Gitoxide manifest says the latest slice is
     gix-pathspec with 2873/2877 mapped and 6007 lane assertions, while
     `lane-status.json` still reports a gix-ignore handoff with 5939 PHP pass
     count. Pandoc status has advanced to DOCX raw bookmark/raw-block work with
     295 focused tests, while the dashboard and progress still describe older
     NativeWriter and 278-test handoffs. This is not just stale publication:
     source-of-truth files disagree inside the checkout.

5. **High - every primary lane still reports pending or uncommitted output,
   not an accepted committed slice.**
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
   - Goal requirement at risk: `goal.md:29` and `goal.md:48` require small,
     reviewable committed slices after tests and cleanup.
   - Evidence: all lane-status handoffs explicitly say pending, uncommitted,
     not committed, or commit/root verification ownership deferred to the
     supervisor/integrator. Focused green lane results are useful evidence, but
     they are not accepted progress until integrated from a frozen snapshot.

6. **High - markerPDF still over-credits plan-only external/runtime
   orchestration as native mapped progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:424` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:425`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:445` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:446`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:903` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:940`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:9`,
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:9`,
     `goal.md:24` through `goal.md:31`, `goal.md:35`, and `goal.md:40`
     require a native PDF-to-structured-content port, no wrapper/shell-out
     progress credit, precise blockers, and explicit marking of hard unported
     features.
   - Evidence: the 290/340 mapped count includes Pandoc/XeLaTeX helper
     planning, benchmark/archive workflow planning, `chunk_convert.py`
     `subprocess.run shell=True` boundary planning, `chunk_convert.sh`
     lifecycle planning, Streamlit/FastAPI/Uvicorn planning, Poetry/package
     dependency planning, OCR/Tesseract/Ghostscript installer plans, and model
     runtime preflights. The new PDF TJ array decoding slice is legitimate
     native work, but these orchestration/planning rows should remain
     preflight metadata or blockers, not mapped native extraction progress.

7. **High - essential optional-library coverage remains backlog-only, while
   rclone is growing lane-local ZIP code that should not count as shared
   support-library progress.**
   - Paths: `progress.md:17` through `progress.md:24`,
     `dependency-backlog.json:7` through `dependency-backlog.json:23`,
     `dependency-backlog.json:381` through `dependency-backlog.json:398`,
     `porting.html:71` through `porting.html:114`,
     `lanes/rclone/src/VfsZipArchive.php:7` through
     `lanes/rclone/src/VfsZipArchive.php:13`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1242` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1243`,
     `lanes/rclone/lane-status.json:5`,
     `lanes/rclone/lane-status.json:11`.
   - Goal requirement at risk: `goal.md:9`, `goal.md:12`,
     `goal.md:24` through `goal.md:31`, `goal.md:35`, and the audit
     instruction for support libraries require bounded native PHP components,
     activation gates, dependency-specific denominators, mapped fixtures, PHP
     evidence, corrupt/malformed cases where relevant, and no hidden
     shell-outs.
   - Evidence: `dependency-backlog.json` has 23 gated items and zero active
     implementations. There are still no support-library manifests with owners,
     activation gates, dependency-specific upstream/spec denominators, fixture
     maps, malformed/corrupt-case coverage, or root evidence. Rich gaps remain
     for ZIP/package containers, XML/HTML5, DOCX/OpenXML, legacy CFB `.doc`,
     EPUB/ODT, doctemplates, CSL/citations, math/TeX, PDF text/render/OCR/table
     geometry, source maps, protobuf/BEP wire behavior, Unicode/charset repair,
     checksums, SQL/storage codecs, archive/compression streams, glob/pathspec,
     and provider metadata normalization. Rclone's `VfsZipArchive` may be a
     valid lane-local slice, but it is not the shared `shared-zip-package-core`
     dependency until it receives the same manifest, gate, denominator,
     corrupt-case, and verification treatment required of lanes.

8. **High - near-complete progress percentages overstate accepted native
   upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56` through
     `porting.html:67`, `lanes/difftastic/lane-status.json:5`,
     `lanes/gitoxide/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/pandoc/lane-status.json:5`,
     `lanes/syncthing/lane-status.json:5`.
   - Goal requirement at risk: `goal.md:35`, `goal.md:37`,
     `goal.md:38`, and `goal.md:40` require meaningful upstream denominators,
     upstream tests as source of truth, explicit slices, and honest blockers.
   - Evidence: the dashboard advertises 97.7% average progress and 92-99%
     lane progress while many lanes lack full upstream parity and the current
     lane work is unaccepted. Difftastic has no full Cargo runner, Gitoxide has
     no full workspace Cargo runner, Pandoc has no full Haskell runner,
     Syncthing has no full `go test ./...`, markerPDF has no full
     benchmark/model/PDF runner, rclone excludes provider/mount/live-service
     parity, esbuild excludes release-extra `make test-all`, and libsqlite
     excludes full all/release permutations.

9. **Medium - manifest/status schemas remain non-normalized and hard to
   compare across lanes.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:34`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:17`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require comparable denominator, mapped-test, and PHP
     pass/fail fields.
   - Evidence: `benchmarkDenominator.total` is numeric in some lanes and a
     narrative string in Difftastic, Pandoc, Quadrable, and Dolt. Dolt's
     manifest uses `mapped: 613` while its lane status reports 372 PHP tests.
     PHP pass values mix behavior-test counts, selected test-file counts, and
     assertion counts. The compact dashboard collapses these into display
     strings, so accepted parity cannot be computed safely.

10. **Medium - blocker fields still lead with local-green wording while real
    acceptance blockers remain unresolved.**
    - Paths: `lanes/dolt/lane-status.json:12`,
      `lanes/esbuild/lane-status.json:12`,
      `lanes/gitoxide/lane-status.json:12`,
      `lanes/libsqlite/lane-status.json:12`,
      `lanes/markerpdf/lane-status.json:12`,
      `lanes/rclone/lane-status.json:12`,
      `lanes/syncthing/lane-status.json:12`.
    - Goal requirement at risk: `goal.md:31` and `goal.md:40` require precise
      blockers and explicit marking of hard or unported features.
    - Evidence: blockers start with "No current", "No focused", or "No
      lane-local" blocker, then later acknowledge pending root verification,
      uncommitted dirty batches, unexecuted full upstream runners, excluded
      provider/service coverage, model-heavy markerPDF execution, or broad
      hydration/build limits. The unresolved acceptance blocker should be first.

## Test Gate

I did not run `php tools/run-tests.php`.

The required pre-root gate was checked:

```text
2026-05-24T01:44Z
pgrep -af '^php tools/run-tests\.php( |$)'
2221571 php tools/run-tests.php lanes/syncthing/tests

owner evidence:
2221571 claude 2079064 11 Rs php tools/run-tests.php lanes/syncthing/tests

pgrep -af '^php tools/run-tests\.php$'
<no no-argument root process>
```

A post-commit pre-finish gate briefly matched an active no-argument root
harness before a later sample cleared:

```text
2026-05-24T01:47Z
pgrep -af '^php tools/run-tests\.php( |$)'
2324743 php tools/run-tests.php

owner evidence:
2324743 claude 2271161 18 Rs php tools/run-tests.php

2026-05-24T01:47:58Z
pgrep -af '^php tools/run-tests\.php( |$)'
<no matches>
```

This audit did not start a duplicate. Even after the process cleared, the
stability gate still failed: `HEAD` moved during the audit, 297 tracked files
remain dirty, all lanes are pending/uncommitted handoffs, 172 tmux sessions are
present, and focused/root harness state changed during handoff. A root run here
would not produce accepted aggregate evidence.

## Next Intervention

Freeze or wait out lane/reseed/runner/status writers, including focused PHP
harnesses, then confirm `HEAD`, tracked dirty count, total status count,
shortstat, runner state, and relevant coordination artifact mtimes are
unchanged across two polls. Normalize manifest/status schema fields before
publishing. Accept exactly one lane-scoped batch, rerun that lane's focused
verification, run one serialized `php tools/run-tests.php` from the same
snapshot, run `git diff --check`, regenerate dashboard artifacts from that
accepted snapshot only, and commit or reject that batch.
