# Independent Audit - 2026-05-24T22:22Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `dependency-backlog.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`, and recent Git history through `cc740727dd26`.

I did not edit lane implementation files, launch agents or tmux sessions, push, read secrets, inspect process environments, credential stores, provider configs, or auth files. Bridge code, generated fixtures, shell-outs, whole applications, external converter wrappers, and hidden process launchers are treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC sample: 2026-05-24T22:22Z
HEAD: cc740727dd26
recent history: cc740727 Integrate Pandoc HTML linebreak slice; 5fa9dbe6 Integrate markerPDF CMap codespace slice; c65d5e26 Integrate LightningCSS page rule formatter slice; 59f84374 Integrate Gitoxide signature consuming slice
default status rows including untracked: 24849
tracked dirty rows: 255
dirty shortstat: 241 files changed, 194457 insertions(+), 24074 deletions(-)
targeted status: all 12 lane manifests and all 12 lane-status files are dirty
initial exact root process gate: `pgrep -af '^php tools/run-tests\.php$'` returned no rows
final exact root process gate: active PID 2340953 owned by claude, `php tools/run-tests.php`
root run by this audit: not started; a no-argument root harness became active before finish, so a duplicate root run was forbidden
dependency backlog: 37 rows, 0 active, 1 blocked, 25 candidate, 11 deferred; every support row still has null dependency-specific upstream denominator/pass-fail ledger
```

Live dirty manifest/status samples are not accepted progress:

```text
lane          manifest mapped/total     status phpPass/phpFail     latest status
difftastic    1270/1381                 3900/0                     pending Zig function-body slice
dolt          613/613                   473/0                      not committed ROUND/TRUNCATE query-diff stack
esbuild       238/2567                  238/0                      uncommitted tsconfig/package resolver stack
gitoxide      1522/2877                 7705/0                     pending annotated-tag shortcut stack
libsqlite     238/1589                  238/0                      pending JSON subtype/aggregate/table/mutation stack
LightningCSS  3041/3548                 4425+/0                    dirty metadata after accepted page-rule slice
markerPDF     175/78                    280/0                      pending UTF-16/string/filter pile
pandoc        2276/2276                 414/0                      pending standalone video/source/track slice after accepted linebreak slice
quadrable     115/115                   263/0                      pending docopt/submodule/dirty multi-slice stack
rclone        527/1601                  527/0                      pending WebDAV GET/HEAD/POST and earlier WebDAV stack
Readability   159 local tests / 1984 upstream runner denominator; mixed 14-slice dirty pile
syncthing     658/658                   9297/0                     pending system-log route after earlier accepted BEP slice
```

## Findings

1. **Critical - the repository still has no stable aggregate acceptance baseline.**
   - Paths: `tools/run-tests.php`, `progress.md`, `audits/latest.md`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: periodically run repo-wide tests and commit small, reviewable slices with passing verification from a stable snapshot.
   - Evidence: `HEAD` moved to `cc740727dd26` after four integration commits, but the checkout is still a mixed dirty pile: `24849` status rows including untracked files, `255` tracked dirty rows, `241 files changed`, and all 12 lane manifests plus all 12 lane-status files dirty. A root test from this state cannot establish acceptance for any single lane slice.

2. **Critical - the latest integration commits do not accept the current live lane piles.**
   - Paths: `lanes/pandoc/lane-status.json`, `lanes/markerpdf/lane-status.json`, `lanes/lightningcss/lane-status.json`, `lanes/gitoxide/lane-status.json`, `progress.md`, `audits/integration-status.md`.
   - Goal requirement at risk: accepted progress must be tied to the exact committed slice and must not launder later dirty handoffs into progress.
   - Evidence: recent history accepted Pandoc linebreak, markerPDF CMap, LightningCSS page-rule, and Gitoxide signature slices. The live statuses now advertise different or broader follow-up stacks: Pandoc standalone video/source/track, markerPDF UTF-16/filter work, LightningCSS dirty metadata, and Gitoxide annotated-tag shortcuts on top of accumulated discovery/mailmap/actor work. Those later handoffs remain pending root/integrator acceptance.

3. **Critical - every live lane status remains unaccepted worker output.**
   - Paths: `lanes/difftastic/lane-status.json`, `lanes/dolt/lane-status.json`, `lanes/esbuild/lane-status.json`, `lanes/gitoxide/lane-status.json`, `lanes/libsqlite/lane-status.json`, `lanes/lightningcss/lane-status.json`, `lanes/markerpdf/lane-status.json`, `lanes/pandoc/lane-status.json`, `lanes/quadrable/lane-status.json`, `lanes/rclone/lane-status.json`, `lanes/readability/lane-status.json`, `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: dirty worker handoffs must not count as native implementation progress until supervisor/integrator acceptance and verification occur.
   - Evidence: current lane statuses still say root verification, integrator acceptance, uncommitted, pending, or split/freeze is pending. Several lanes explicitly describe broad accumulated piles rather than one reviewable slice.

4. **Critical - root harness evidence remains unusable for new acceptance and a duplicate root run is forbidden.**
   - Paths: `tools/run-tests.php`, `audits/integration-status.md`, `progress.md`.
   - Goal requirement at risk: repo-wide root verification must be serialized and tied to the same frozen snapshot being accepted.
   - Evidence: the initial exact gate returned no no-argument root process, but before finish the exact gate matched active PID `2340953` owned by `claude` with command `php tools/run-tests.php`. I did not start a duplicate root harness. The active run is also not automatically acceptance evidence unless it is tied to a frozen commit and reviewed result.

5. **High - `porting.html` is stale relative to live metadata and should not be published as current.**
   - Paths: `porting.html`, `porting-summary.json`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: the dashboard must show current accepted upstream denominator, mapped tests, PHP pass/fail, phase, audit, blocker, and commit.
   - Evidence: `porting.html` still says verified source snapshot `0fa9ecafcd10` generated at `2026-05-24 21:19:36 UTC`, while live `HEAD` is `cc740727dd26` and all lane manifests/status files are dirty.

6. **High - support-library rows are visible but still not first-class support ports.**
   - Paths: `dependency-backlog.json`, `progress.md`, `porting.html`.
   - Goal requirement at risk: support libraries require the same granularity as lanes: bounded native PHP component, activation gate, dependency-specific upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases where relevant, and as much upstream/spec-suite evidence as can actually run.
   - Evidence: the backlog has `37` rows, `0` active rows, and all rows still have null `upstreamDenominator` values. These are routing notes, not accepted support-library progress. Missing packages are not final blockers until bounded `sudo -n` installs are attempted or ruled out, but no row currently records that runner ledger.

7. **High - Pandoc rich-format coverage remains over-credited even after the accepted linebreak slice.**
   - Paths: `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `lanes/pandoc/lane-status.json`, `dependency-backlog.json`, `porting.html`.
   - Goal requirement at risk: Pandoc must provide a document conversion kernel with meaningful readers/writers and upstream-backed rich-format support, not fixture routing.
   - Evidence: the manifest reports `2276/2276` mapped while lane status reports only `414` PHP behavior tests and full Haskell runner parity remains unexecuted. DOC, DOCX/OpenXML, PDF input/output handoff, EPUB, ODT/OpenDocument, citations, math, tables, templates, package containers, XML/HTML, Unicode/charset, JSON/YAML metadata, syntax highlighting, and archive/compression are visible as gated rows/reuse paths, but none is an active bounded support port with a denominator or pass/fail ledger.

8. **High - markerPDF still maps more semantics than its upstream denominator and mixes dependency planning with native extraction progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`, `lanes/markerpdf/lane-status.json`, `dependency-backlog.json`.
   - Goal requirement at risk: markerPDF must become a native PDF-to-structured-content extraction pipeline; external runtime planning, supplied converter/model callbacks, benchmark archive probing, and dependency inspection cannot count as native conversion progress.
   - Evidence: markerPDF reports `175/78` mapped and carries stream/filter, runtime, app, model, benchmark, output, OCR/layout, and table-dependency planning entries. The accepted CMap slice helps searchable text extraction, but broader PDF text dictionaries, page/layout planning, OCR/layout results, table geometry, Unicode/charset, and archive/package concerns remain inactive support rows.

9. **High - base lanes are crossing inactive support-library boundaries without activating shared rows.**
   - Paths: `dependency-backlog.json`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`, `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: rich dependency work should not count as shared reusable progress unless a bounded row is activated, tested, and evidenced.
   - Evidence: esbuild resolver/tsconfig work is ahead of deferred `js-package-resolution-core`; rclone WebDAV/XML work is ahead of inactive `webdav-protocol-core` and `xml-html5-dom-core`; Dolt and libsqlite JSON work is ahead of inactive `json-json5-document-core` and `sql-expression-semantics-core`; Syncthing BEP/session work is ahead of inactive `protobuf-wire-core`; Gitoxide protocol/discovery work remains ahead of inactive `git-wire-protocol-core` and URL support rows.

10. **Medium - manifest/status count units remain non-comparable.**
    - Paths: `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`, `porting-summary.json`, `porting.html`.
    - Goal requirement at risk: progress percentages must be comparable and tied to upstream denominator, mapped tests, PHP pass/fail, phase, blocker, and commit.
    - Evidence: markerPDF reports mapped counts above its upstream path denominator; Pandoc reports full mapping while status has `414` behavior tests; Syncthing, Gitoxide, Difftastic, and LightningCSS `phpPass` values are assertion-like counts; rclone and Dolt mix behavior tests, assertions, selected upstream functions, and static path inventories.

11. **Medium - Quadrable's denominator is still too easy to overread as full behavior parity.**
    - Paths: `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`, `lanes/quadrable/lane-status.json`, `porting.html`.
    - Goal requirement at risk: each lane needs a real upstream denominator and mapped tests in comparable units.
    - Evidence: the manifest reports `115/115`, while the meaningful suite story remains the upstream `check.cpp` scenarios plus proof/sync/docopt subcases. Counting initialized submodule/path inventory as fully mapped inflates progress unless the dashboard separates path inventory from behavior parity.

12. **Medium - Syncthing URL/query support remains lane-local, not reusable shared support.**
    - Paths: `dependency-backlog.json`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`, `lanes/syncthing/lane-status.json`.
    - Goal requirement at risk: missing bounded support rows become blockers once the base lane is ready for or blocked by the next essential rich capability.
    - Evidence: Syncthing discovery/config/API work uses URL/query construction behavior, but `url-percent-encoding-core` still lists `rclone`, `gitoxide`, `esbuild`, `lightningcss`, and `readability` only. Either add Syncthing as a gated consumer with spec/vector expectations, or keep that evidence explicitly lane-local and non-reusable.

## Root Harness Decision

I did not run `php tools/run-tests.php`. The initial exact no-argument root gate was clear, but the tree remained unstable and, before finish, the final exact gate found active PID `2340953` owned by `claude` with command `php tools/run-tests.php`. Starting a duplicate root run would violate the audit instruction.

## Next Intervention

Freeze writers/status publishers/dashboard regeneration/test-loop starters for two stable polls; wait for PID `2340953` to finish and record its result without counting it unless it matches a frozen snapshot; select exactly one owner-free reduced batch; run focused verification and `git diff --check`; run one serialized no-argument root harness only from that same frozen snapshot with an empty exact process gate; normalize manifest/status count units; activate support rows only behind a real accepted gate or blocker; regenerate dashboard artifacts from the accepted commit; then commit or reject.
