# gitoxide WordPress Scenario

Git-backed WordPress content workflows, package installs, Playground snapshots, and server-side repo primitives.

## Current Native Slice

Native loose Git object storage with canonical object headers, loose-header encode/decode and upstream-shaped loose-byte body-prefix parsing, SHA-1 and SHA-256 commit IDs, commit header/signature/identity/encoding parsing with strict upstream header order, commit extra-header first/all/position lookup, commit token iteration with upstream order and partial-error behavior, raw merge-tag header access parsed as native annotated tags, annotated tag write/size/token roundtrips, gix-validate tag-name validation and sanitization preflights, commit message summary/title/body/trailer parsing with Gitoxide byte-class trimming and continuation rules, signature-stripped signed-data extraction, tree entry parsing/serialization, pairwise and multi-head/octopus merge-base ancestry traversal, flat and recursive tree three-way merge decisions, exact same-object rename/delete and rename/rename conflict detection, bounded similar-blob rename/delete and rename/modify conflict detection, same-target rename content merges, bounded directory rename/modify merges with renamed plugin entry file heuristics and strict-best similar rename candidate selection, recursive blob-content merges with conflict-marker output, Gitoxide-style text conflict auto-resolution through ours/theirs/union policies, upstream `zdiff3` conflict-style literal normalization, optional unlabeled conflict-marker output, marker-size byte-bound checks, built-in merge-driver selection for text/binary/union attributes, external merge-driver preparation and injected-runner readback with missing resources as empty tempfiles and too-large resources rejected before any runner is called, zealous-diff3/merge/union common-edge hunk contraction, blank-line false-conflict suppression for duplicated context insertions next to deletions, and marker-newline selection for mixed-line-ending text conflicts, Git index v2 writes for ancestor/ours/theirs blob conflict stages, checkout-clean merged worktree and conflict marker-file writes for content conflicts, directory-file conflict classification, loose direct/symbolic reference parsing/storage, loose-ref same-name empty directory blocker recovery with non-empty blocker refusal, `gix-ref` full-name category/short-name/namespace helpers, namespace-transparent loose+packed reference-store iteration, bounded namespace-aware reference transaction update/delete guards, prepared loose-reference rollback, commit publication, and object-update reflog creation/failure behavior with non-atomic failure reporting, packed-ref transaction delete/update rewrites, packed-update modes that leave or prune loose source refs, deref update/delete split reporting where symbolic parents are reflog-only, update leaves receive object writes, and delete leaves receive the requested delete mode, object-update reflog append/delete handling, packed-ref header/reference/peeled lookup parsing, protocol v2 capability and `ls-refs` parsing, protocol v2 fetch negotiation argument building, common partial-clone fetch filter specs, sparse checkout path matching, lazy promisor object hydration, protocol v2 fetch response section and sideband parsing, protocol v1 receive-pack push request building, receive-pack advertisement parsing, generated pack handoff, REF_DELTA and OFS_DELTA pack generation with bounded base-candidate windows, thin send-pack request building, external-base thin REF_DELTA reads including OFS_DELTA chains and complete-pack repair, stream-backed, git-daemon, smart HTTP auth/session/redirect/followRedirects/proxy plus proxy auth-method handling, native SOCKS4/SOCKS4a/SOCKS5/SOCKS5h default-requester handshakes, HTTPS-through-SOCKS TLS peer verification with a configured CA file, SSH receive-pack transport boundaries, send-pack session orchestration, status parsing, loose+packed reference-store overlay resolution, v2 pack-index parsing/lookup, multi-pack-index parsing/lookup, MIDX-backed object database pack selection/de-duplication, pack data entry decoding, OFS_DELTA/REF_DELTA object resolution, pack+loose+alternate+promisor object database lookup/prefix/iteration with replacement refs, and gix-credentials context/cascade/helper-program command construction plus prompt fallback semantics.

## WordPress Deploy Tree Example

`examples/wordpress-content-tree.php` parses a fixture Git tree with `.wp-env.json`, `wp-config.php`, and `wp-content` entries. This models server-side PHP code inspecting a WordPress repository snapshot in shared hosting, Playground, package install, or migration tooling without shelling out to `git`.

## WordPress Loose Object Header Example

`examples/wordpress-object-header.php` builds a block-content blob, decodes its canonical loose object header, reports SHA-1 and SHA-256 object IDs, and demonstrates the upstream `ObjectRef::from_loose()` boundary where a read-ahead buffer is parsed by advertised object size while exact storage parsing remains strict. It also records Gitoxide's signed-size boundary: `+N` and `-0` loose headers parse to the canonical body size while non-zero negative sizes are rejected. This models WordPress import or deployment tools inspecting loose block-content blobs without invoking `git cat-file`.

## WordPress Commit Signature Example

`examples/wordpress-commit-signature.php` parses a WordPress import/deploy commit body with author and committer actors, actor-only identity bytes, timezone offsets, `encoding`, a multiline signature header, extra-header position/count lookup, upstream-ordered commit token types, embedded release merge-tag header parsed as a native annotated tag object, commit summary/body, Signed-off-by/Co-authored-by/Acked-by/Reviewed-by/Tested-by trailers, standalone body trailer parsing, and signature-stripped signed payload bytes. The parser now also rejects commit bytes that never reach Gitoxide's required header/message separator, preventing truncated deployment provenance from being treated as an empty-message commit. Focused trailer coverage now follows Gitoxide's vertical-tab handling for blank trailer separators, folded trailer continuations, and leading/trailing value trim, so odd imported commit metadata is parsed consistently with upstream instead of PHP locale behavior. The BodyRef-style helpers expose direct trailer parsing for review notes that are not full commit objects, including sole-body trailers and cherry-pick markers that affect footer detection without becoming parsed trailers. The example now emits Gitoxide-style commit storage bytes, exact size, commit object bytes, and storage/object SHA-1 hashes for provenance roundtrips. It also preflights malformed imported commits by rejecting reordered actor headers and treating late `parent`/`encoding` lines as extra headers, matching `gix-object` instead of silently accepting ambiguous provenance. The fixture covers legacy raw actor offsets such as `+051800` and malformed `--700`, preserving the raw commit bytes while exposing Gitoxide-style parsed time access for review tools. The latest identity slice exposes `name <email>` bytes separately from timestamp parsing so migration review tools can compare importer identities without conflating timezone anomalies. This models migration and deployment tooling that needs `git log`-style provenance, signed-commit metadata, structured commit token inspection, release-tag target/kind/tagger/message provenance, importer/reviewer/tester attribution, actor identity comparison, standalone migration-review trailer parsing, malformed-import and timestamp preflight, and canonical commit object hashing without invoking the Git binary.

The commit signature example now also records whitespace-preserving multiline
`gpgsig` signed-data stripping and old multi-`gpgsig` header round trips, keeping
the native verifier boundary aligned with Gitoxide commit parsing.

## WordPress Annotated Tag Example

`examples/wordpress-annotated-tag.php` parses, tokenizes, sizes, hashes, and roundtrips a signed WordPress release tag as native tag object bytes. It now preflights a draft release name with `GitTag::isValidName()`, sanitizes it with gix-validate tag-name rules, and proves the sanitized name is writable before constructing a release tag. This models deployment tooling that needs to reject or normalize invalid import/export labels, prepare valid release tags, verify release-tag provenance, and compare object identity without invoking `git tag`, `git cat-file`, or ad hoc tag-body regexes.

The example now uses an uppercase raw target ID in the fixture and reports both the normalized object ID and raw tag-body target bytes. This matches Gitoxide's `TagRef` boundary where callers can compare normalized object identity while still roundtripping the exact annotated-tag bytes used for release provenance hashes.

When the same parsed target is used to construct a new draft release tag, the example now shows the owned tag writer emits the normalized object ID instead of preserving the borrowed raw uppercase bytes. This matches Gitoxide's `TagRef` versus owned `Tag` write boundary and keeps generated WordPress release tags canonical while preserving imported tag provenance bytes separately.

The same example now also converts the parsed tag through `GitTag::toOwned()`, matching Gitoxide's `TagRef::into_owned()` boundary: the imported tag can still roundtrip exact signed bytes, while the owned copy writes a canonical lowercase object header for newly generated release artifacts.

## WordPress Reference Example

`examples/wordpress-references.php` writes and reads `HEAD`, `refs/heads/main`, and `refs/remotes/origin/HEAD` using native PHP loose-ref files. This models a shared-hosting deployment tool or Playground snapshot manager discovering the active WordPress branch and its commit without invoking the Git binary.

## WordPress Reference Category Example

`examples/wordpress-reference-categories.php` classifies WordPress deployment refs as local branches, remote-tracking branches, tags, pseudo refs, main-worktree refs, linked-worktree refs, and worktree-private refs. It also constructs full names from upstream categories and prefixes/strips a site namespace such as `refs/namespaces/site-a/refs/heads/main`. This models multisite or staged-deployment tooling that needs to route fetch, push, and review refs without invoking `git for-each-ref`.

The same example now uses `ReferenceName::joinPartial()` to compose plugin review branches such as `refs/heads/review/plugins/gutenberg` and `refs/remotes/origin/review/plugins/gutenberg` from partial ref components. It also uses `ReferenceName::sanitizePartial()` to turn an unsafe plugin-derived component such as `plugins/seo suite.lock` into `plugins/seo-suite` before joining it under `refs/heads/review`, and `ReferenceName::isValidBranchName()` to reject the reserved `refs/heads/HEAD` branch. This models deployment tools that build branch names from WordPress plugin paths while still rejecting or normalizing unsafe ref bytes, repeated slashes, empty components, leading-dot names, and `.lock` suffixes through upstream-shaped validation.

The example now also checks complete reference-name validity with `ReferenceName::isValid()`: slash-containing relative deployment refs such as `review/plugins/gutenberg` are valid complete names, while lowercase standalone names such as `main` are rejected unless expanded under `refs/heads/`. This follows `gix_validate::reference::name()` and helps WordPress deployment tooling distinguish valid relative ref paths from invalid pseudo refs before fetch/push planning.

The latest namespace prefix slice uses `ReferenceName::intoNamespacedPrefix()` to build a namespaced ref-iteration prefix such as `refs/namespaces/site-a/refs/heads/review/` while preserving the trailing slash. This follows `gix_ref::namespace::Namespace::into_namespaced_prefix()` and models multisite deployment tooling that iterates only a tenant's review branches without invoking `git for-each-ref`.

## WordPress Namespaced Reference Store Example

`examples/wordpress-namespaced-reference-store.php` writes multisite review refs under `refs/namespaces/site-a/refs/...`, combines loose refs with a packed remote-tracking ref, and opens a namespace-aware `ReferenceStore`. Store iteration returns tenant-relative names such as `refs/heads/review/plugin-a`, loose iteration uses the same transparent namespace boundary, packed refs are included through the namespace filter, symbolic targets inside the namespace are stripped to tenant-relative targets, and redundant namespaced lookups intentionally miss. This follows the focused upstream `gix-ref` namespaced store-iteration tests and models WordPress multisite deployment tooling that scopes review refs without invoking `git for-each-ref`.

## WordPress Packed Reference Example

`examples/wordpress-packed-refs.php` parses a compacted `packed-refs` buffer with a WordPress deployment branch, remote-tracking branch, and peeled release tag. This models a PHP deployment or package manager inspecting compacted repository state on shared hosting without invoking `git show-ref` or `git for-each-ref`.

## WordPress Reference Store Example

`examples/wordpress-reference-store.php` combines loose `HEAD` with packed branch and release-tag refs. This models a shared-hosting deployment tool resolving the active WordPress branch from loose refs while reading compacted branch/tag state from `packed-refs`.

## WordPress Multi-Head Merge Base Example

`examples/wordpress-merge-base.php` computes the shared release baseline across plugin, theme, and content review branch heads, and across a deployment merge head plus its source heads. This follows `gix_revision::merge_base::octopus()` for multi-head convergence checks and models deployment tooling that needs a common ancestor before combining WordPress review branches without invoking `git merge-base`.

## WordPress Protocol V2 ls-refs Example

`examples/wordpress-protocol-v2-ls-refs.php` parses a deterministic protocol v2 capability advertisement, builds an `ls-refs` request with de-duplicated `ref-prefix` arguments and `unborn` support, then parses the remote response into active branch, peeled release tag, and unborn staging branch refs. This models a PHP deployment or package manager discovering remote WordPress repo state before deciding what to fetch.

## WordPress Protocol V2 Fetch Example

`examples/wordpress-protocol-v2-fetch.php` parses a deterministic protocol v2 capability advertisement and builds a shallow blobless `fetch` request using `want-ref refs/heads/main`, `deepen 1`, `filter blob:none`, and local `have` negotiation state. This models a PHP deployment or package manager requesting only the WordPress branch metadata and reachable pack data it needs before object database reads.

## WordPress Protocol V2 Fetch Response Example

`examples/wordpress-protocol-v2-fetch-response.php` parses deterministic protocol v2 fetch response packet lines, including `acknowledgments`, `shallow-info`, `wanted-refs`, and `packfile` sections. It extracts the wanted WordPress branch object, shallow boundary update, sideband progress text, and sideband pack bytes that can be passed to the native pack/object database layer.

## WordPress Protocol V1 Push Example

`examples/wordpress-protocol-v1-push.php` builds a deterministic receive-pack request for updating `refs/heads/main`, creating `refs/tags/wp-release`, requesting `report-status-v2`, `side-band-64k`, `object-format=sha1`, `atomic`, and `push-options`, then packet-lines the ref updates before placeholder pack bytes. This models a PHP deployment tool preparing a WordPress branch/tag push without invoking `git push`.

## WordPress Protocol V1 Push Pack Example

`examples/wordpress-protocol-v1-push-pack.php` builds native pack bytes for a deterministic WordPress commit/tree/blob set, verifies the generated pack and index through the native pack reader, and appends those bytes to a receive-pack branch/tag request. This models a PHP deployment tool preparing the object payload for `git-receive-pack` without invoking `git pack-objects`.

## WordPress Protocol V1 Push Response Example

`examples/wordpress-protocol-v1-push-response.php` parses a deterministic sidebanded receive-pack response for a WordPress deployment push. It extracts progress messages, `unpack ok`, and accepted branch/tag ref statuses from nested report-status packet lines, rejects malformed deployment-hook reports that attach report-status-v2 options to unrequested refs, and treats missing requested ref statuses as remote failures, so a PHP deployment tool can determine whether the remote accepted the push without invoking `git push`.

## WordPress Send-Pack Session Example

`examples/wordpress-send-pack-session.php` parses advertised receive-pack refs and capabilities, plans a WordPress branch update plus release-tag creation, generates native pack bytes for the commit/tree/blob payload, builds the receive-pack request, and parses the status response. This models the local orchestration around a WordPress deployment push before actual transport I/O is ported.

## WordPress Thin Send-Pack Example

`examples/wordpress-send-pack-thin.php` builds a thin receive-pack request for a WordPress content update. It uses remote base objects to encode changed content as REF_DELTA entries, omits those bases from the transit pack, and preserves the update command envelope without invoking `git pack-objects` or `git push`.

## WordPress Thin Pack Repair Example

`examples/wordpress-thin-pack-repair.php` resolves a thin WordPress content pack against an existing `wp_posts` base blob, verifies that ordinary pack reads still reject missing REF_DELTA bases, then rewrites the payload as a complete non-thin pack with an OFS_DELTA entry. The focused tests also carry external bases through an OFS_DELTA chain whose in-pack base is a thin REF_DELTA. This models a PHP receiver repairing transit pack data before local object storage without invoking `git index-pack`.

## WordPress OFS Delta Pack Example

`examples/wordpress-send-pack-ofs-delta.php` builds a compact non-thin pack containing two related `wp_posts` export blobs. The second blob is written as an OFS_DELTA entry against the earlier in-pack base, then read back through the native pack/index resolver. This models receive-pack payload generation for WordPress deployment tools that want compact pack bytes without relying on `git pack-objects`.

## WordPress Pack Delta Window Example

`examples/wordpress-pack-delta-window.php` builds a WordPress export pack twice: once with unbounded same-type base search, and once with a one-candidate base window after an unrelated scratch blob. The unbounded pack finds the older `wp_posts` base and emits an OFS_DELTA, while the bounded pack falls back to a whole object because the recent scratch blob is not a useful base. This models a PHP pack builder capping CPU and memory work for large export batches while still producing valid pack bytes without invoking `git pack-objects`.

## WordPress Pack Reuse Example

`examples/wordpress-pack-reuse.php` starts from an already-stored WordPress export pack and builds a new pack from selected object IDs. The native builder copies compressed whole-object payloads, reuses an existing OFS_DELTA payload while rewriting its new base distance when the base object is selected, and can explicitly emit a thin REF_DELTA transit pack when the receiver already has the omitted base object. It also starts from a legacy in-pack REF_DELTA export pack and proves those source deltas are decoded and emitted as complete whole-object entries, even if thin output is allowed. This models shared-hosting deployment tooling repacking local Git objects for push, transfer, or resting storage without invoking `git pack-objects`.

## WordPress Receive-Pack Transport Example

`examples/wordpress-receive-pack-transport.php` runs a deterministic receive-pack handshake, request write, and status response read over native PHP stream resources. The transport tests now also cover receive-pack advertisement `ERR` pkt-lines before ref parsing, git-daemon `git-receive-pack` service requests with absolute repository path validation, percent-decoded git:// host/path preflight, encoded control-byte, decoded host-delimiter, and malformed extra-parameter rejection before pkt-line writes, smart HTTP discovery/POST exchanges with auth headers, Set-Cookie session propagation, safe initial redirects with effective-base reuse, `http.followRedirects` initial/all/none behavior including safe same-host POST redirects, cleartext URL credential refusal before network I/O, proxy/no-proxy, Basic proxy credential-helper boundaries, proxy auth-method normalization, native default-requester SOCKS5h and SOCKS4a receive-pack discovery through local proxy handshakes, and SSH `git-receive-pack` command setup over injected streams, modeling concrete URL adapter boundaries a shared-hosting deployment tool needs before actual SSH authentication/channel integration and broader HTTPS-through-SOCKS coverage are added.

`examples/wordpress-smart-http-cleartext-credentials.php` documents the URL-credential guard for WordPress deployment tools: `http://deploy:token@...` is rejected before discovery I/O, so an HTTP-to-HTTPS redirect cannot leak Basic credentials from the first cleartext request.

`examples/wordpress-smart-http-proxy-credentials.php` documents proxy credential-helper handling for WordPress deployment tools. It records ordinary helper lookup, username-only proxy URL helper context, final-status store/erase callbacks, accepted 304 discovery responses that store proxy credentials and preserve discovery cookies into receive-pack POSTs, proxy authorization kept out of origin headers, redirect credential reuse without leaking proxy credentials to the Git origin, and CIDR `noProxy` bypasses that preserve origin cookies without invoking proxy helpers.

`examples/wordpress-smart-http-follow-redirects.php` documents an opted-in `followRedirects=true` receive-pack flow: discovery succeeds at the original repository URL, the POST receives safe same-host 307/308 redirects, and the PHP transport resends the same generated request body to the redirected receive-pack endpoint. It records redirect-issued cookie expiration, `Max-Age` precedence, default-Path scoping, same-name scoped retention, most-specific Path ordering, explicit Domain/Path/Secure omission, and rejected 301, 302, 303, wrong-endpoint 307, credential-bearing 307, and fragment-bearing 307 POST redirects before replaying the generated pack body. This models WordPress deployment hosts that front Git repositories with same-origin routing or maintenance redirects without allowing cross-host credential, method-rewrite, endpoint-switch, fragment-mutation, or pack-body leaks.

## WordPress Smart HTTP SOCKS/TLS Example

`examples/wordpress-smart-http-socks-tls.php` documents an HTTPS receive-pack discovery flow through a SOCKS5h proxy using a configured CA file and normal peer verification. The focused transport test exercises the same boundary against a local TLS server: PHP opens the SOCKS tunnel, CONNECTs to `git.example.test:443`, negotiates TLS for the origin host, sends an origin-form smart HTTP request, and keeps proxy credentials out of origin headers.

## WordPress Credential Context Example

`examples/wordpress-credential-context.php` writes and reads native Git credential-helper context fields for a WordPress deployment repository. It preserves upstream helper protocol field order, derives a password-free display URL for `wp-content.git`, normalizes percent-encoded deployment helper URLs through gix-url-style context destructuring, and redacts or clears the deployment password and OAuth refresh token before diagnostic logging.

## WordPress Credential Cascade Example

`examples/wordpress-credential-cascade.php` runs a native Git credential-helper cascade for a WordPress deployment repository. It ignores an expired cached deploy token, carries OAuth refresh metadata into the final complete identity, and fans out store/erase helper payloads after the deployment decision without invoking `git credential`.

## WordPress Credential Prompt Example

`examples/wordpress-credential-prompt.php` runs a native helper cascade where configured helpers only return OAuth metadata and prompt callbacks supply the missing deploy username and token. It follows Gitoxide's prompt fallback boundary by restoring the original repository URL before prompting, using a visible username prompt, using a hidden password prompt, and retaining the URL in the next store/erase context without invoking `git credential`.

## WordPress Credential Program Example

`examples/wordpress-credential-program.php` preflights configured Git credential helper declarations for a WordPress deployment repository. It maps `credential-cache`, `credential-oauth`, an absolute tenant helper path, and builtin `git credential` action names into the same command argument boundaries Gitoxide uses, including shell `$@` forwarding for helper definitions that require a shell. This lets deployment tooling inspect helper configuration before any helper process is invoked.

## WordPress Reference Transaction Example

`examples/wordpress-reference-transaction.php` promotes a tenant-scoped production branch, deletes a reviewed plugin branch, recovers an empty tenant `HEAD` directory left by an interrupted deploy, rolls back one prepared pair of review refs, commits another prepared pair of review refs with audit reflogs, prunes a stale prepared review ref with reflog-first delete ordering, and updates `HEAD` inside `refs/namespaces/site-a/` while returning store-relative ref names, symbolic targets, prepared review-ref reflog lines, and prepared delete edit names. This models the bounded `git update-ref` behavior a multisite deployment tool needs to stage, publish, audit, or prune review refs and recover from empty directory blockers without invoking the Git binary.

## WordPress Deref Reference Transaction Example

`examples/wordpress-deref-reference-transaction.php` updates a production branch through symbolic `HEAD` with `deref=true`. The transaction report keeps `HEAD` as a reflog-only edit, applies the object update to `refs/heads/production`, preserves the symbolic `HEAD` file, and writes matching audit reflogs for both names. It now also runs a delete-side reflog-only transaction through the same symbolic `HEAD`: the report keeps both `HEAD` and `refs/heads/production` as reflog-only delete edits, removes both reflogs, and leaves the symbolic parent plus production branch intact. This models deployment tooling that publishes through the active branch and later prunes audit logs while matching Gitoxide's symbolic-ref split behavior without invoking `git update-ref`.

## WordPress Packed Reference Transaction Example

`examples/wordpress-packed-reference-transaction.php` starts from compacted `packed-refs`, promotes a packed production branch, prunes a reviewed plugin branch, removes the loose production source ref in explicit packed-update mode, rewrites the sorted packed-ref file, and records a deployment reflog line. This models shared-hosting or Playground deployment tools that need pack-refs/update-ref-style branch publication and audit trails without invoking `git update-ref`, `git pack-refs`, or shell commands.

The packed-reference transaction example now also follows a symbolic
release-candidate ref into a packed tag object and peels it to the release
commit. This models release tooling resolving symbolic deployment refs through
packed tags without invoking Git.

## WordPress Reflog Audit Example

`examples/wordpress-reflog-audit.php` appends and parses deployment reflog
entries forward and newest-first, including committer trimming and rollback
message inspection. This models deployment audit tooling that would otherwise
shell out to `git reflog`.

The same example now keeps a long-lived path-backed reference store open while another process replaces and removes `packed-refs`. The store refreshes its parsed packed-ref buffer before subsequent lookups, so a WordPress deployment worker does not keep publishing decisions from stale compacted refs after another deploy process rewrites the packed ref file.

## WordPress Tree Merge Example

`examples/wordpress-tree-merge.php` merges flat root-tree changes where one side updates `wp-content` and the other adds `.wp-env.json`, then reports a structured `theme.json` modify/modify conflict. This models the first PHP-native merge decision layer for WordPress deployment snapshots before recursive tree traversal and blob conflict-marker merges are added.

## WordPress Blob Merge Example

`examples/wordpress-blob-merge.php` line-merges independent WordPress metadata edits, auto-resolves an overlapping `theme.json` layout decision with the local deployment side, union-merges block stabilization notes with Gitoxide's no-trailing-newline separator behavior, emits zealous-diff3 conflict markers that keep shared changed layout/spacing decisions outside the markers while preserving the full ancestor section, accepts the upstream `merge.conflictStyle=zdiff3` literal from repository config, suppresses the upstream blank-line false conflict for a block-spacing cleanup, emits Gitoxide-shaped conflict markers for mixed CRLF/LF block content, keeps shared changed block-refactor lines outside a focused partial-match conflict, renders an anonymous editor preview with unlabeled diff3 markers, and emits diff3 conflict markers for unresolved `theme.json` color changes. This models the content-merge layer needed before tree conflicts can be resolved into merged blobs.

## WordPress Built-In Merge Driver Example

`examples/wordpress-builtin-merge-driver.php` maps `.gitattributes` merge-driver choices through native `BuiltinDriver` selection: `merge=union` appends independent block review notes without markers, `-merge` treats binary media as an unresolved pick-ours conflict, plain `merge` selects text and honors a non-zero `conflict-marker-size`, binary-like buffers marked as text fall back to the binary driver before diffing, and an unknown custom driver name falls back to text when no external driver registry is present. This follows Gitoxide's case-sensitive built-in driver lookup and platform binary-buffer fallback, and models WordPress repositories that use attributes to tune block-content, media, and theme JSON merges without shelling out to Git.

## WordPress External Merge Driver Example

`examples/wordpress-external-merge-driver.php` prepares a configured `wordpress-json-normalizer` merge driver and then exercises the readback boundary with an injected approved runner. The example selects the custom driver ahead of built-ins, writes ancestor/current/other `theme.json` buffers to worktree-local temp files, expands `%O`, `%A`, `%B`, `%L`, `%P`, `%S`, `%X`, `%Y`, preserves `%F`, lets the caller-injected runner overwrite the `%A` current temp file, and reads the merged buffer back as a complete merge result. It now also passes a deleted base as an empty `%O` tempfile and rejects an oversized media-like buffer before any external-driver tempfiles are created. This models WordPress deployment tooling that wants to audit or stage custom block/theme merge-driver commands in PHP, then hand execution to a separately approved integration runner and read its merged `theme.json` output without invoking Git or launching a shell inside the native lane.

## WordPress Recursive Tree Merge Example

`examples/wordpress-recursive-tree-merge.php` walks nested WordPress trees, merges independent `post.meta` edits into a new blob, records a full-path `theme.json` content conflict with diff3 marker output, writes the ancestor/ours/theirs blob stages into a Git index v2 file, and checks out a merged worktree containing both clean metadata and the marker-file `theme.json`. The checkout removes a stale plugin file while preserving `.git/config`. The underlying recursive merge also classifies nested directory-file collisions such as a cache directory on one side and a cache file on the other; those tree stages can now expand into file-level index entries. The merge engine detects exact same-object rename/delete and rename/rename conflicts plus bounded similar-blob rename/delete and rename/modify conflicts for renamed plugin entry files, and same-target renamed plugin entry edits now merge or conflict at the renamed path. Bounded plugin directory renames are detected by descendant similarity, including unique renamed internal leaves, so edits left under the old directory on the other side merge or conflict at the new directory path even when the renamed side also renames the main plugin file. The example now also covers a plugin parent rename where both sides rename the same nested route directory and independently edit the same nested route file, yielding the upstream-shaped marker blob and stage-2/stage-3 conflict path. When a plugin split creates multiple similar candidate directories, the merge chooses the strictly best match and keeps equal-score ambiguity on the ordinary add/delete path.

The merge fixture mapping now includes the upstream multiple-merge-bases shape. That keeps the native recursive tree merge honest for WordPress deployment histories where two review branches share more than one valid baseline.

## WordPress Partial Clone Example

`examples/wordpress-partial-clone.php` writes a deterministic WordPress pack/index pair with a `.promisor` sidecar, builds a blobless `FetchFilterSpec`, and stores a tree that references both packed content and an omitted media blob. This models a PHP deployment or package manager distinguishing local packed WordPress content from media bytes promised by a partial clone without invoking `git cat-file`.

## WordPress Lazy Promisor Fetch Example

`examples/wordpress-lazy-promisor-fetch.php` starts with a blobless promisor pack and an omitted media object, then resolves that object through a native `PromisorObjectResolver`. The object database verifies the returned object ID, persists the blob into loose storage, reports the media object as present for a fresh database instance, and refreshes object iteration/count state after an external template promisor pack is written to disk.

## WordPress Sparse Checkout Example

`examples/wordpress-sparse-checkout.php` combines a blobless fetch filter with a cone-mode sparse checkout rooted at `wp-content/plugins/gutenberg`. It filters deterministic WordPress tree entries so root files, ancestor-directory files, and Gutenberg plugin files are materialized while unrelated plugin/admin paths remain skipped.

The sparse-checkout example now also covers bounded Gitoxide pathspec rules, so
deployment tooling can include, exclude, and case-match WordPress content paths
without invoking the Git binary.

It now also records authoritative excludes and wildmatch bracket/range/escape
behavior so package checkouts can skip cache and build subtrees without invoking
Git.

## WordPress Index Cache-Tree Example

`examples/wordpress-index-cache-tree.php` writes and reads a native checkout
index with a `TREE` cache extension, sparse checkout skip-worktree flags, and
cache-tree entry counts. This models WordPress package checkout tooling keeping
index metadata consistent without invoking Git.

## WordPress Config Include Example

`examples/wordpress-config-include-conditional.php` resolves branch, gitdir, and
remote-url dependent deployment configuration from native PHP config parsing.
It models WordPress deployment code loading preview, conflict-style, HTTP
header, and transfer safety settings through `include` and `includeIf` rules
without shelling out to `git config`.

The config include example now records escaped `gitdir` policy and literal
wildcard matching for `hasconfig:remote.*.url` conditional includes, matching
the upstream backslash escape boundary.

## WordPress Attributes Pathspec Example

`examples/wordpress-attributes-pathspec.php` combines `.gitattributes` parsing
with pathspec attribute filters to select deployable plugin, theme, and upload
paths while excluding cache/build artifacts. It records merge/diff/text
attributes for the selected WordPress content using only lane-local PHP code.
It also rejects malformed long-magic attr filters such as
`:(attr:deploy,)`, matching Gitoxide's pathspec parser before a deployment
selection path can silently accept a malformed filter.

## WordPress Pack Index Example

`examples/wordpress-pack-index.php` parses a deterministic v2 pack index fixture for a WordPress repository and locates compacted object offsets, including a large 64-bit media object offset. This models a PHP object database finding packed content objects on shared hosting without invoking `git`.

## WordPress Multi-Pack Index Example

`examples/wordpress-multi-pack-index.php` parses a deterministic multi-pack-index fixture that maps content, template, and large media objects to the pack index names and offsets that contain them. This models a PHP object database using one compact MIDX fanout table to select the right WordPress content or media pack before reading pack data.

The pack and MIDX examples now exercise stronger corruption checks for sorted object IDs, fanout consistency, pack IDs, and checksum state before a WordPress content reader trusts compacted package data.

## WordPress Pack Data Example

`examples/wordpress-pack-data.php` pairs a deterministic pack index with pack data, then reads a packed commit, blob, and OFS_DELTA-reconstructed blob by object ID. This models a PHP object database reading compacted WordPress content on shared hosting without invoking `git cat-file`.

The pack data example now records strict declared-size guards, so malformed
packed or delta-compressed WordPress content is rejected before object/delta
resolution can trust an inflated payload.

## WordPress Object Database Example

`examples/wordpress-object-database.php` writes the deterministic WordPress pack fixture into a temporary `.git/objects/pack` directory, adds a loose draft object, links an alternate shared package object cache through `objects/info/alternates`, maps a draft object through `refs/replace`, then reads every source through one object database. This models package managers, Playground snapshot tools, and shared-hosting deployment code traversing packed, loose, shared-cache, and replacement repository content without invoking the Git binary.

The example now also writes a deployment commit object through the object database. This models import, package, and Playground snapshot tooling that needs the same object-size/hash semantics Gitoxide applies when storing commits, but still must stay inside the native PHP object database boundary.

The object database example now verifies loose object integrity across both the
primary repository and the alternate shared package cache, including exact
payload lengths, object IDs, structured body decoding, and zero-byte loose
object corruption rejection before zlib/header decoding.

## WordPress Smart HTTP Follow Redirects Example

`examples/wordpress-smart-http-follow-redirects.php` now records chained safe receive-pack redirects that recompute redirect-issued cookies for each effective endpoint while preserving caller cookies and the generated POST body only for method-preserving receive-pack targets.

## WordPress SSH Receive-Pack Example

`examples/wordpress-receive-pack-transport.php` now records the protocol-v2 and credential boundary passed to caller-injected SSH connectors. This keeps the native lane from shelling out while still exposing the context a hosting integration needs to authorize an SSH receive-pack session.

## WordPress Multi-Pack Object Database Example

`examples/wordpress-object-database-multi-pack.php` writes two deterministic pack/index pairs plus a `multi-pack-index`, including a package object duplicated across both packs. The object database uses the MIDX to count three indexed objects from four raw pack-index entries, read content/media/shared objects by pack selection, and keep prefix and pack-offset iteration aligned with the MIDX.

## WordPress Commit Signature Consuming Example

`examples/wordpress-commit-signature-consuming.php` parses deterministic WordPress importer and reviewer signature bytes through native `CommitSignature::parseConsuming()`. It separates the Git actor identity and lenient timestamp from local audit suffix bytes, preserves the caller-visible remainder, and rejects malformed signatures without invoking `git log`, reading live repository/account state, opening remotes, reading process environments, or touching credential stores. This maps Gitoxide's `SignatureRef::from_bytes_consuming()` boundary for import/deploy tooling that receives commit actor bytes embedded in larger audit records.

## WordPress Protocol Fetch And Push Examples

`examples/wordpress-protocol-v2-fetch-response.php` now records protocol v2 `sideband-all` responses so deployment tooling can keep progress/error channels separate from pack data before the pack arrives. `examples/wordpress-protocol-v1-push-response.php` now records a proc-receive deployment hook rewriting a review ref with SHA-256 old/new object IDs in report-status-v2 output.

`examples/wordpress-protocol-v2-ls-refs.php` now uses packet-line protocol v2
capability and `ls-refs` advertisements plus upstream-shaped request bytes, so
deployment tooling can inspect active branches, unborn refs, peeled tags, and
symbolic HEADs through native PHP protocol framing.

The ls-refs example now also records SHA-256 remote refs. The pack data example
rejects oversized delta base/result headers before integer wraparound can
affect object reconstruction.

The fetch response example now also records upstream sideband fixture progress
messages parsed into native remote-progress records. The push response example
now rejects oversized receive-pack status packet-lines before interpreting them
as ref updates.

## WordPress URL And Refspec Example

`examples/wordpress-url-refspec-normalize.php` normalizes deployment remote URLs
and fetch/push refspecs through native PHP parsers. This models deployment code
deciding which WordPress branch/tag namespaces to fetch or update without
shelling out to `git remote` or `git push`.

The URL/refspec example now also covers file-authority URLs, SCP-like IPv6
remotes, forced fetch-only refspec normalization, and oversized remote-host
rejection before deployment tooling stores or replays a malformed URL. It now
also rejects malformed bracketed SCP-like remotes such as `[::1:repo` before
they can be mistaken for local mirror paths.

## WordPress Commit Signature Example

`examples/wordpress-commit-signature.php` now exposes raw multiline `gpgsig`
bytes while preserving old-git multiline header behavior. This lets deployment
or package provenance checks inspect signed commit payloads without invoking
`git cat-file` or `git log`.

## WordPress Merge Base Example

`examples/wordpress-merge-base.php` now records graph-walk merge-base checks
against other review heads, including archive-branch rejection. This models
release-baseline selection for plugin/theme review branches without invoking
Git.

## WordPress Tree Pathspec Walk Example

`examples/wordpress-tree-pathspec-walk.php` applies native pathspec parsing and
breadth-first tree traversal to select deployable plugin, theme, and upload
paths while skipping excluded build/cache subtrees. This models WordPress
package and deployment tooling walking repository trees without invoking the
Git binary.

## WordPress Attributes Pathspec Example

`examples/wordpress-attributes-pathspec.php` now validates value-qualified
attribute suffixes while matching set/unset state, so filters such as
`:(attr:-diff=legacy)` can select must-use plugin content without accepting a
malformed attribute value expression.

## WordPress Protocol, Refs, Pathspec, And Object Examples

The latest accepted batch extends the native Gitoxide examples for deployment
selection and repository inspection: sparse checkout now handles prefix
pathspecs, protocol v2 `ls-refs` handles refspec-prefix requests, fetch/push
examples cover sideband packet bounds and fatal receive-status reports, tree
walking handles prefix/case pathspecs, merge-base checks support SHA-256
object ids, reflog audit flows cover direct append/reverse iteration, index
cache-tree checks handle object-backed children, loose-object inspection covers
headers, and packed-reference lookups return prefixed peeled values.

## WordPress Config And Pack Delta Examples

`examples/wordpress-config-include-conditional.php` now covers `includeIf`
double-star matching where `**/` can match zero or more path components for
deployment branch, repository, and remote URL conditions. The pack-data example
now exposes a result-buffer guard so native pack readers reject delta
copy/insert overruns and short applications before accepting compacted content
objects.

## WordPress URL/Refspec And Empty Pathspec Examples

`examples/wordpress-url-refspec-normalize.php` now covers one-sided push
refspec writer normalization so deployment tooling can serialize implicit
destinations such as `HEAD:HEAD` without shelling out to Git. The tree pathspec
example now covers empty pathspec searches and walks, including prefixed walks,
so repository traversal can intentionally enumerate complete deploy trees.

## WordPress Commit, Push Status, And Sparse Checkout Examples

`examples/wordpress-commit-signature.php` now covers writing detached gpgsig
headers while keeping already-signed import commits stable. The protocol v1
push-response example now parses proc-receive fall-through statuses, and the
sparse-checkout example now normalizes absolute pathspecs under the worktree
root for deployment selection.

## WordPress Protocol And Merge-Base Priority Examples

The latest accepted batch extends protocol and graph behavior used by native
deployment tooling. Protocol v2 fetch parsing now accepts exact upstream
section/sideband fixture shapes, protocol v2 ls-refs parsing accepts the smart
HTTP `# service=git-upload-pack` announcement prelude before capabilities, and
merge-base selection now orders independent common baselines by priority graph
metadata before deterministic tie-breakers.

## WordPress Object, Pack, Attributes, And Config Examples

The latest accepted batch extends native repository safety around deployment
selection and content-pack ingestion. Object storage now honors SHA-256 loose
object paths, headers, writes, and integrity verification; attributes/pathspec
matching now distinguishes absent selected attributes from explicitly
unspecified ones; pack parsing rejects non-canonical size metadata and
OFS_DELTA base-distance overflow before trusting deltas; and config includes
refuse bracket classes that would otherwise consume slash separators.

## Next Task

Broaden protocol/transport runner evidence with a controlled focused crate probe, deepen mmap-specific packed-ref race parity beyond metadata/hash invalidation if needed, or map another focused `gix-merge` tree fixture.
