<?php

declare(strict_types=1);

use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PandocFormatRegistry;

require __DIR__ . '/bootstrap.php';

$repoRoot = dirname(__DIR__);
$defaultRoot = $repoRoot . '/.port-libs/document-corpus';
$root = getenv('DOCUMENT_CORPUS_ROOT') ?: $defaultRoot;
$dbPath = $root . '/corpus.sqlite';
$command = $argv[1] ?? 'help';
$args = parseArgs(array_slice($argv, 2));

if (($args['root'] ?? '') !== '') {
    $root = normalizePath($repoRoot, (string) $args['root']);
    $dbPath = $root . '/corpus.sqlite';
}
if (($args['db'] ?? '') !== '') {
    $dbPath = normalizePath($repoRoot, (string) $args['db']);
    $root = dirname($dbPath);
}

try {
    match ($command) {
        'help', '-h', '--help' => printHelp(),
        'init' => commandInit($repoRoot, $root, $dbPath),
        'seed' => commandSeed($repoRoot, $root, $dbPath),
        'add-url' => commandAddUrl($repoRoot, $root, $dbPath, $args),
        'discover-github' => commandDiscoverGithub($repoRoot, $root, $dbPath, $args),
        'discover-known' => commandDiscoverKnown($repoRoot, $root, $dbPath, $args),
        'fetch' => commandFetch($repoRoot, $root, $dbPath, $args),
        'fetch-balanced' => commandFetchBalanced($repoRoot, $root, $dbPath, $args),
        'render' => commandRender($repoRoot, $root, $dbPath, $args),
        'render-balanced' => commandRenderBalanced($repoRoot, $root, $dbPath, $args),
        'render-one' => commandRenderOne($repoRoot, $root, $dbPath, $args),
        'report' => commandReport($repoRoot, $root, $dbPath, $args),
        'formats' => commandFormats($repoRoot, $root, $dbPath),
        default => throw new InvalidArgumentException("Unknown command '{$command}'. Run: php tools/document-corpus.php help"),
    };
} catch (Throwable $error) {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . "\n");
    exit(1);
}

function printHelp(): void
{
    fwrite(STDOUT, <<<'TXT'
Usage: php tools/document-corpus.php <command> [options]

Commands:
  init                         Create the local SQLite corpus database.
  seed                         Insert curated organic online seed URLs.
  add-url --url=URL --format=F  Insert one organic online candidate URL.
  discover-github [--limit=N]  Discover public files from built-in GitHub repositories.
  discover-known [--limit=N]   Discover public files using format-specific GitHub path rules.
  fetch [--limit=N]            Download queued candidate URLs into the local corpus store.
  fetch-balanced [--limit=N]   Fetch queued candidates across under-target formats.
  render [--limit=N]           Render fetched documents through reference and PHP/WordPress paths.
  render-balanced [--limit=N]  Render pending documents across under-rendered formats.
  report                       Write JSON and HTML review reports.
  formats                      Print supported input formats and current corpus counts.

Options:
  --root=PATH                  Corpus root. Default: .port-libs/document-corpus
  --db=PATH                    SQLite DB path. Default: ROOT/corpus.sqlite
  --limit=N                    Maximum rows to process for fetch/render/discovery.
  --per-format=N               Per-format cap for balanced fetch/render. Default: 3.
  --format=FORMAT              Restrict fetch/render to one input format.
  --origin=TEXT                Origin for add-url. Default: URL host.
  --title=TEXT                 Human label for add-url. Default: URL basename.
  --source-kind=TEXT           Source kind for add-url. Default: manual-url.

The database and downloaded documents are intentionally local and ignored by git.
The corpus is for organic online documents; do not seed synthetic fixtures.

TXT);
}

function commandInit(string $repoRoot, string $root, string $dbPath): void
{
    ensureDirectory($root);
    ensureDirectory($root . '/files');
    ensureDirectory($root . '/renders');
    $db = db($dbPath);
    migrate($db);
    seedFormats($db);
    fwrite(STDOUT, "Initialized {$dbPath}\n");
}

function commandSeed(string $repoRoot, string $root, string $dbPath): void
{
    commandInit($repoRoot, $root, $dbPath);
    $db = db($dbPath);
    $inserted = 0;
    foreach (organicSeedUrls() as $seed) {
        $inserted += insertCandidate($db, $seed) ? 1 : 0;
    }
    fwrite(STDOUT, "Seeded {$inserted} new organic candidate URLs\n");
}

function commandAddUrl(string $repoRoot, string $root, string $dbPath, array $args): void
{
    commandInit($repoRoot, $root, $dbPath);
    $url = trim((string) ($args['url'] ?? ''));
    $format = trim((string) ($args['format'] ?? ''));
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
        throw new InvalidArgumentException('add-url requires --url=https://...');
    }
    if ($format === '') {
        throw new InvalidArgumentException('add-url requires --format=FORMAT');
    }

    $db = db($dbPath);
    if (!formatExists($db, $format)) {
        throw new InvalidArgumentException("Unsupported corpus format '{$format}'");
    }
    $path = parse_url($url, PHP_URL_PATH);
    if (is_string($path) && shouldSkipOrganicPath($path)) {
        throw new InvalidArgumentException('URL path looks like a fixture, test corpus, vendor copy, or metadata file');
    }
    $host = parse_url($url, PHP_URL_HOST);
    $titlePath = is_string($path) ? basename($path) : '';
    $inserted = insertCandidate($db, [
        'url' => $url,
        'format' => $format,
        'source_kind' => trim((string) ($args['source-kind'] ?? 'manual-url')) ?: 'manual-url',
        'origin' => trim((string) ($args['origin'] ?? '')) ?: (is_string($host) ? $host : 'manual'),
        'title' => trim((string) ($args['title'] ?? '')) ?: ($titlePath !== '' ? $titlePath : $url),
        'discovery_meta' => json_encode(['addedBy' => 'document-corpus add-url'], JSON_THROW_ON_ERROR),
    ]);

    fwrite(STDOUT, $inserted ? "Inserted {$format} candidate {$url}\n" : "Candidate already exists: {$url}\n");
}

function commandDiscoverGithub(string $repoRoot, string $root, string $dbPath, array $args): void
{
    commandInit($repoRoot, $root, $dbPath);
    $db = db($dbPath);
    $limit = positiveInt($args['limit'] ?? null, 300);
    $inserted = 0;
    $seen = 0;
    $formatCounts = corpusCandidateCounts($db);
    $formatTargets = corpusFormatTargets($db);
    foreach (githubDiscoveryRepos() as $repo) {
        if ($inserted >= $limit) {
            break;
        }
        try {
            $tree = githubTree((string) $repo['repo'], (string) $repo['ref']);
        } catch (Throwable $error) {
            fwrite(STDERR, 'github discovery skipped ' . (string) $repo['repo'] . '@' . (string) $repo['ref'] . ': ' . $error->getMessage() . "\n");
            continue;
        }
        foreach ($tree as $node) {
            if ($inserted >= $limit) {
                break 2;
            }
            if (($node['type'] ?? '') !== 'blob') {
                continue;
            }
            $path = (string) ($node['path'] ?? '');
            $size = (int) ($node['size'] ?? 0);
            $format = inferFormatFromPath($path);
            if ($format === null || $size <= 0 || $size > maxBytesForFormat($format) || shouldSkipOrganicPath($path)) {
                continue;
            }
            if (($formatCounts[$format] ?? 0) >= ($formatTargets[$format] ?? 25)) {
                continue;
            }
            $allowed = $repo['formats'] ?? [];
            if (is_array($allowed) && $allowed !== [] && !in_array($format, $allowed, true)) {
                continue;
            }
            $seen++;
            $url = 'https://raw.githubusercontent.com/' . $repo['repo'] . '/' . rawurlencode((string) $repo['ref']) . '/' . str_replace('%2F', '/', rawurlencode($path));
            $new = insertCandidate($db, [
                'url' => $url,
                'format' => $format,
                'source_kind' => 'github-tree',
                'origin' => (string) $repo['repo'],
                'title' => basename($path),
                'discovery_meta' => json_encode(['repo' => $repo['repo'], 'ref' => $repo['ref'], 'path' => $path, 'size' => $size], JSON_THROW_ON_ERROR),
            ]);
            if ($new) {
                $inserted++;
                $formatCounts[$format] = ($formatCounts[$format] ?? 0) + 1;
            }
        }
    }
    fwrite(STDOUT, "Discovered {$inserted} new candidates from {$seen} matching GitHub tree entries\n");
}

function commandDiscoverKnown(string $repoRoot, string $root, string $dbPath, array $args): void
{
    commandInit($repoRoot, $root, $dbPath);
    $db = db($dbPath);
    $limit = positiveInt($args['limit'] ?? null, 300);
    $inserted = 0;
    $seen = 0;
    $formatCounts = corpusCandidateCounts($db);
    $formatTargets = corpusFormatTargets($db);
    foreach (knownGitHubDiscoveryRepos() as $repo) {
        if ($inserted >= $limit) {
            break;
        }
        try {
            $tree = githubTree((string) $repo['repo'], (string) $repo['ref']);
        } catch (Throwable $error) {
            fwrite(STDERR, 'known discovery skipped ' . (string) $repo['repo'] . '@' . (string) $repo['ref'] . ': ' . $error->getMessage() . "\n");
            continue;
        }
        foreach ($tree as $node) {
            if ($inserted >= $limit) {
                break 2;
            }
            if (($node['type'] ?? '') !== 'blob') {
                continue;
            }
            $path = (string) ($node['path'] ?? '');
            $size = (int) ($node['size'] ?? 0);
            $format = knownFormatForPath($repo, $path);
            if ($format === null || $size <= 0 || $size > maxBytesForFormat($format) || shouldSkipOrganicPath($path)) {
                continue;
            }
            if (($formatCounts[$format] ?? 0) >= ($formatTargets[$format] ?? 25)) {
                continue;
            }
            $seen++;
            $url = 'https://raw.githubusercontent.com/' . $repo['repo'] . '/' . rawurlencode((string) $repo['ref']) . '/' . str_replace('%2F', '/', rawurlencode($path));
            $new = insertCandidate($db, [
                'url' => $url,
                'format' => $format,
                'source_kind' => 'github-known-tree',
                'origin' => (string) $repo['repo'],
                'title' => basename($path),
                'discovery_meta' => json_encode(['repo' => $repo['repo'], 'ref' => $repo['ref'], 'path' => $path, 'size' => $size, 'knownRules' => true], JSON_THROW_ON_ERROR),
            ]);
            if ($new) {
                $inserted++;
                $formatCounts[$format] = ($formatCounts[$format] ?? 0) + 1;
            }
        }
    }
    fwrite(STDOUT, "Discovered {$inserted} new known-format candidates from {$seen} matching GitHub tree entries\n");
}

function commandFetch(string $repoRoot, string $root, string $dbPath, array $args): void
{
    commandInit($repoRoot, $root, $dbPath);
    $db = db($dbPath);
    $limit = positiveInt($args['limit'] ?? null, 100);
    $format = trim((string) ($args['format'] ?? ''));
    $rows = pendingCandidates($db, $limit, $format);
    [$ok, $failed] = fetchCandidateRows($db, $dbPath, $rows);
    fwrite(STDOUT, "Fetch complete: ok={$ok} failed={$failed}\n");
}

function commandFetchBalanced(string $repoRoot, string $root, string $dbPath, array $args): void
{
    commandInit($repoRoot, $root, $dbPath);
    $db = db($dbPath);
    $limit = positiveInt($args['limit'] ?? null, 100);
    $perFormat = positiveInt($args['per-format'] ?? null, 3);
    $ok = 0;
    $failed = 0;
    foreach (balancedFetchFormats($db) as $format) {
        if ($ok + $failed >= $limit) {
            break;
        }
        $remaining = $limit - $ok - $failed;
        $rows = pendingCandidates($db, min($perFormat, $remaining), $format);
        [$formatOk, $formatFailed] = fetchCandidateRows($db, $dbPath, $rows);
        $ok += $formatOk;
        $failed += $formatFailed;
    }
    fwrite(STDOUT, "Balanced fetch complete: ok={$ok} failed={$failed}\n");
}

function commandRender(string $repoRoot, string $root, string $dbPath, array $args): void
{
    commandInit($repoRoot, $root, $dbPath);
    $db = db($dbPath);
    $limit = positiveInt($args['limit'] ?? null, 50);
    $format = trim((string) ($args['format'] ?? ''));
    $rows = pendingDocuments($db, $limit, $format);
    [$ok, $failed] = renderDocumentRows($db, $dbPath, $rows);
    fwrite(STDOUT, "Render complete: ok={$ok} failed={$failed}\n");
}

function commandRenderBalanced(string $repoRoot, string $root, string $dbPath, array $args): void
{
    commandInit($repoRoot, $root, $dbPath);
    $db = db($dbPath);
    $limit = positiveInt($args['limit'] ?? null, 80);
    $perFormat = positiveInt($args['per-format'] ?? null, 3);
    $ok = 0;
    $failed = 0;
    foreach (balancedRenderFormats($db) as $format) {
        if ($ok + $failed >= $limit) {
            break;
        }
        $remaining = $limit - $ok - $failed;
        $rows = pendingDocuments($db, min($perFormat, $remaining), $format);
        [$formatOk, $formatFailed] = renderDocumentRows($db, $dbPath, $rows);
        $ok += $formatOk;
        $failed += $formatFailed;
    }
    fwrite(STDOUT, "Balanced render complete: ok={$ok} failed={$failed}\n");
}

function commandRenderOne(string $repoRoot, string $root, string $dbPath, array $args): void
{
    commandInit($repoRoot, $root, $dbPath);
    $id = positiveInt($args['id'] ?? null, 0);
    $db = db($dbPath);
    $row = documentById($db, $id);
    if ($row === null) {
        throw new RuntimeException("Document {$id} was not found");
    }
    renderDocument($repoRoot, dirname($dbPath), $db, $row);
}

function commandReport(string $repoRoot, string $root, string $dbPath, array $args): void
{
    commandInit($repoRoot, $root, $dbPath);
    $db = db($dbPath);
    $report = corpusReport($db);
    writeBytes(dirname($dbPath) . '/report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
    writeBytes(dirname($dbPath) . '/report.html', renderReportHtml($report));
    fwrite(STDOUT, 'Wrote ' . dirname($dbPath) . "/report.json\n");
    fwrite(STDOUT, 'Wrote ' . dirname($dbPath) . "/report.html\n");
}

function commandFormats(string $repoRoot, string $root, string $dbPath): void
{
    commandInit($repoRoot, $root, $dbPath);
    $db = db($dbPath);
    $stmt = $db->query(<<<'SQL'
        SELECT f.format, f.status, f.target_count,
               COUNT(DISTINCT d.id) AS documents,
               COUNT(DISTINCT CASE WHEN c.status='queued' THEN c.id END) AS queued,
               COUNT(DISTINCT CASE WHEN r.status='ok' AND r.renderer='php-wordpress' THEN r.document_id END) AS rendered
        FROM formats f
        LEFT JOIN candidates c ON c.format = f.format
        LEFT JOIN documents d ON d.format = f.format
        LEFT JOIN renders r ON r.document_id = d.id
        GROUP BY f.format
        ORDER BY f.format
    SQL);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        printf("%-18s %-17s target=%3d docs=%3d queued=%3d rendered=%3d\n", $row['format'], $row['status'], $row['target_count'], $row['documents'], $row['queued'], $row['rendered']);
    }
}

function fetchCandidateRows(PDO $db, string $dbPath, array $rows): array
{
    $ok = 0;
    $failed = 0;
    foreach ($rows as $row) {
        try {
            $fetch = fetchUrl((string) $row['url'], [], maxBytesForFormat((string) $row['format']));
            $bytes = $fetch['bytes'];
            if ($bytes === '') {
                throw new RuntimeException('empty response body');
            }
            validateCandidateBytes((string) $row['format'], (string) $row['url'], $bytes);
            if (strlen($bytes) > maxBytesForFormat((string) $row['format'])) {
                throw new RuntimeException('download exceeds bounded corpus size for format');
            }
            $sha = hash('sha256', $bytes);
            $extension = extensionForFormat((string) $row['format'], (string) $row['url']);
            $relativePath = 'files/' . substr($sha, 0, 2) . '/' . $sha . '.' . $extension;
            $path = dirname($dbPath) . '/' . $relativePath;
            writeBytes($path, $bytes);
            upsertDocument($db, $row, $relativePath, $sha, strlen($bytes), $fetch);
            markCandidate($db, (int) $row['id'], 'fetched', null);
            $ok++;
            fwrite(STDOUT, "fetched {$row['format']} {$row['url']}\n");
        } catch (Throwable $error) {
            markCandidate($db, (int) $row['id'], 'failed', $error->getMessage());
            $failed++;
            fwrite(STDERR, "failed {$row['url']}: {$error->getMessage()}\n");
        }
    }

    return [$ok, $failed];
}

function renderDocumentRows(PDO $db, string $dbPath, array $rows): array
{
    $ok = 0;
    $failed = 0;
    foreach ($rows as $row) {
        $result = runCommand([
            PHP_BINARY,
            '-d',
            'memory_limit=' . (getenv('DOCUMENT_CORPUS_RENDER_MEMORY_LIMIT') ?: '1024M'),
            __FILE__,
            'render-one',
            '--db=' . $dbPath,
            '--id=' . (string) $row['id'],
        ], 120);
        fwrite(STDOUT, $result['stdout']);
        fwrite(STDERR, $result['stderr']);
        if ($result['exitCode'] === 0) {
            $render = renderRow($db, (int) $row['id'], 'php-wordpress');
            if ($render !== null && $render['status'] === 'ok') {
                $ok++;
                fwrite(STDOUT, "rendered #{$row['id']} {$row['format']} {$row['url']}\n");
            } else {
                $failed++;
                $error = is_array($render) ? (string) ($render['error'] ?? '') : 'render subprocess did not record a php-wordpress render';
                fwrite(STDERR, "render failed #{$row['id']}: " . ($error !== '' ? $error : 'php-wordpress render failed') . "\n");
            }
            continue;
        }

        recordRenderFailure($db, (int) $row['id'], 'php-wordpress', trim($result['stderr']) ?: 'render subprocess failed');
        $failed++;
        fwrite(STDERR, "render failed #{$row['id']}: subprocess exit {$result['exitCode']}\n");
    }

    return [$ok, $failed];
}

function balancedFetchFormats(PDO $db): array
{
    $rows = $db->query(<<<'SQL'
        SELECT f.format,
               COUNT(DISTINCT d.id) AS documents,
               COUNT(DISTINCT CASE WHEN c.status='queued' THEN c.id END) AS queued
        FROM formats f
        LEFT JOIN documents d ON d.format=f.format
        LEFT JOIN candidates c ON c.format=f.format
        GROUP BY f.format
        HAVING documents < f.target_count AND queued > 0
        ORDER BY documents ASC, queued DESC, f.format ASC
    SQL)->fetchAll(PDO::FETCH_ASSOC);

    return array_map(static fn (array $row): string => (string) $row['format'], $rows);
}

function balancedRenderFormats(PDO $db): array
{
    $rows = $db->query(<<<'SQL'
        SELECT f.format,
               COUNT(DISTINCT d.id) AS documents,
               COUNT(DISTINCT CASE WHEN r.renderer='php-wordpress' THEN r.document_id END) AS attempted
        FROM formats f
        LEFT JOIN documents d ON d.format=f.format
        LEFT JOIN renders r ON r.document_id=d.id
        GROUP BY f.format
        HAVING documents > attempted
        ORDER BY attempted ASC, documents DESC, f.format ASC
    SQL)->fetchAll(PDO::FETCH_ASSOC);

    return array_map(static fn (array $row): string => (string) $row['format'], $rows);
}

function db(string $path): PDO
{
    ensureDirectory(dirname($path));
    $db = new PDO('sqlite:' . $path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA journal_mode=WAL');
    $db->exec('PRAGMA foreign_keys=ON');

    return $db;
}

function migrate(PDO $db): void
{
    $db->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS formats (
            format TEXT PRIMARY KEY,
            status TEXT NOT NULL,
            implementation TEXT NOT NULL,
            target_count INTEGER NOT NULL,
            extensions_json TEXT NOT NULL,
            notes TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS candidates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            url TEXT NOT NULL UNIQUE,
            format TEXT NOT NULL,
            source_kind TEXT NOT NULL,
            origin TEXT NOT NULL,
            title TEXT NOT NULL DEFAULT '',
            discovery_meta TEXT NOT NULL DEFAULT '{}',
            status TEXT NOT NULL DEFAULT 'queued',
            error TEXT,
            discovered_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX IF NOT EXISTS candidates_format_status_idx ON candidates(format, status);
        CREATE TABLE IF NOT EXISTS documents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            candidate_id INTEGER REFERENCES candidates(id),
            url TEXT NOT NULL UNIQUE,
            format TEXT NOT NULL,
            source_kind TEXT NOT NULL,
            origin TEXT NOT NULL,
            title TEXT NOT NULL DEFAULT '',
            local_path TEXT NOT NULL,
            sha256 TEXT NOT NULL,
            bytes INTEGER NOT NULL,
            etag TEXT,
            last_modified TEXT,
            fetched_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX IF NOT EXISTS documents_format_idx ON documents(format);
        CREATE TABLE IF NOT EXISTS renders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            document_id INTEGER NOT NULL REFERENCES documents(id),
            renderer TEXT NOT NULL,
            status TEXT NOT NULL,
            output_path TEXT,
            seconds REAL NOT NULL DEFAULT 0,
            exit_code INTEGER,
            error TEXT,
            metrics_json TEXT NOT NULL DEFAULT '{}',
            rendered_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(document_id, renderer)
        );
        CREATE TABLE IF NOT EXISTS comparisons (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            document_id INTEGER NOT NULL REFERENCES documents(id),
            reference_renderer TEXT NOT NULL,
            wordpress_renderer TEXT NOT NULL,
            status TEXT NOT NULL,
            metrics_json TEXT NOT NULL DEFAULT '{}',
            notes TEXT NOT NULL DEFAULT '',
            compared_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(document_id, reference_renderer, wordpress_renderer)
        );
    SQL);
}

function seedFormats(PDO $db): void
{
    $support = array_replace(PandocFormatRegistry::phpInputSupport(), PandocFormatRegistry::phpLocalInputSupport());
    $supported = array_filter($support, static fn (array $entry): bool => ($entry['status'] ?? '') !== 'unsupported');
    $target = (int) ceil(1000 / max(1, count($supported)));
    $stmt = $db->prepare('INSERT INTO formats(format,status,implementation,target_count,extensions_json,notes) VALUES(:format,:status,:implementation,:target_count,:extensions_json,:notes) ON CONFLICT(format) DO UPDATE SET status=excluded.status, implementation=excluded.implementation, target_count=excluded.target_count, extensions_json=excluded.extensions_json, notes=excluded.notes');
    foreach ($supported as $format => $entry) {
        $stmt->execute([
            ':format' => $format,
            ':status' => (string) $entry['status'],
            ':implementation' => (string) $entry['implementation'],
            ':target_count' => $target,
            ':extensions_json' => json_encode(extensionsForFormat($format), JSON_THROW_ON_ERROR),
            ':notes' => (string) $entry['notes'],
        ]);
    }
}

/**
 * @return list<array<string, string>>
 */
function organicSeedUrls(): array
{
    return [
        seed('https://raw.githubusercontent.com/WordPress/gutenberg/trunk/README.md', 'gfm', 'github-raw', 'WordPress/gutenberg', 'Gutenberg README'),
        seed('https://raw.githubusercontent.com/kubernetes/website/main/content/en/docs/concepts/overview/_index.md', 'gfm', 'github-raw', 'kubernetes/website', 'Kubernetes concepts overview'),
        seed('https://raw.githubusercontent.com/nodejs/node/main/doc/api/fs.md', 'gfm', 'github-raw', 'nodejs/node', 'Node.js fs API documentation'),
        seed('https://raw.githubusercontent.com/django/django/main/docs/intro/tutorial01.txt', 'rst', 'github-raw', 'django/django', 'Django tutorial RST'),
        seed('https://www.w3.org/TR/mathml-core/', 'html', 'standards-web', 'w3.org', 'W3C MathML Core'),
        seed('https://www.w3.org/TR/html-aria/', 'html', 'standards-web', 'w3.org', 'ARIA in HTML'),
        seed('https://www.gutenberg.org/ebooks/1342.epub.noimages', 'epub', 'public-domain-book', 'gutenberg.org', 'Pride and Prejudice EPUB'),
        seed('https://www.gutenberg.org/ebooks/4300.epub.noimages', 'epub', 'public-domain-book', 'gutenberg.org', 'Ulysses EPUB'),
        seed('https://raw.githubusercontent.com/plotly/datasets/master/2014_usa_states.csv', 'csv', 'github-raw', 'plotly/datasets', 'Plotly US states CSV'),
        seed('https://raw.githubusercontent.com/datasets/covid-19/main/data/countries-aggregated.csv', 'csv', 'github-raw', 'datasets/covid-19', 'COVID-19 countries CSV'),
        seed('https://raw.githubusercontent.com/mkerrisk/man-pages/master/man7/bootparam.7', 'man', 'github-raw', 'mkerrisk/man-pages', 'bootparam(7) man page'),
        seed('https://raw.githubusercontent.com/mkerrisk/man-pages/master/man5/proc.5', 'man', 'github-raw', 'mkerrisk/man-pages', 'proc(5) man page'),
        seed('https://raw.githubusercontent.com/freebsd/freebsd-src/main/bin/ls/ls.1', 'mdoc', 'github-raw', 'freebsd/freebsd-src', 'FreeBSD ls(1) mdoc page'),
        seed('https://raw.githubusercontent.com/citation-style-language/styles/master/apa.csl', 'xml', 'github-raw', 'citation-style-language/styles', 'APA CSL XML style'),
        seed('https://raw.githubusercontent.com/gwoodwa1/network_rag_pipeline/7f9de75055c9801b3fa188f9cc7075bb47ca104d/processed/evpn_design.ast.json', 'json', 'github-raw', 'gwoodwa1/network_rag_pipeline', 'Processed EVPN design Pandoc AST JSON'),
        seed('https://raw.githubusercontent.com/playboypaul/legaldocconverter/4a714e13095cf916d6d43bcfa794c787990fca5a/backend/storage/conversions/dda53d87-b909-4676-8b0a-9f28f56f65aa_converted.json', 'json', 'github-raw', 'playboypaul/legaldocconverter', 'Converted legal document Pandoc JSON'),
        seed('https://raw.githubusercontent.com/HL7/fhir/master/build.xml', 'xml', 'github-raw', 'HL7/fhir', 'FHIR build XML'),
        seed('https://raw.githubusercontent.com/torvalds/linux/master/Documentation/filesystems/proc.rst', 'rst', 'github-raw', 'torvalds/linux', 'Linux proc filesystem RST'),
        seed('https://raw.githubusercontent.com/rust-lang/rust/master/RELEASES.md', 'markdown', 'github-raw', 'rust-lang/rust', 'Rust releases Markdown'),
        seed('https://raw.githubusercontent.com/kubernetes/website/main/README.md', 'markdown', 'github-raw', 'kubernetes/website', 'Kubernetes website README'),
        seed('https://raw.githubusercontent.com/tsolucio/corebosdocs/01ab857b87ffa5ae07b593e487af5a12347802f8/pages/wiki/dokuwiki.txt', 'dokuwiki', 'github-raw', 'tsolucio/corebosdocs', 'CoreBOS DokuWiki page'),
        seed('https://raw.githubusercontent.com/wherecamppdx/wherecamppdx-dokuwiki/38b728bca45ebcd80c6c7f8990bada2198d063c8/data/pages/wiki/dokuwiki.txt', 'dokuwiki', 'github-raw', 'wherecamppdx/wherecamppdx-dokuwiki', 'WhereCampPDX DokuWiki page'),
        seed('https://raw.githubusercontent.com/dongzhang0725/PhyloSuite/master/PhyloSuite/PhyloSuite_citation.xml', 'endnotexml', 'github-raw', 'dongzhang0725/PhyloSuite', 'PhyloSuite EndNote XML citation'),
        seed('https://raw.githubusercontent.com/GeoscienceAustralia/arr/8a0f6a26bf3e5d42f9aa3796feaaaf673146e65f/arr/refs_from_endnote.xml', 'endnotexml', 'github-raw', 'GeoscienceAustralia/arr', 'Australian Rainfall and Runoff EndNote references'),
        seed('https://raw.githubusercontent.com/Proximify/publication-fetcher/2b93e1c737fc81827411d03099637e0b3c20d4d1/docs/endnote_library.xml', 'endnotexml', 'github-raw', 'Proximify/publication-fetcher', 'Publication fetcher EndNote library'),
        seed('https://raw.githubusercontent.com/medialab/reference_manager/bea9bac657ba3d1953029081eae928a578ee5528/data/endnotexml/endnote-bib.xml', 'endnotexml', 'github-raw', 'medialab/reference_manager', 'Reference manager EndNote bibliography'),
        seed('https://raw.githubusercontent.com/dveytia/ORO-product-1-mitigation/5d4adec00ac9806b6ec5edbda5930cb9a5d7ada2/data/raw-data/scoping-data-export/CDR_OAE.xml', 'endnotexml', 'github-raw', 'dveytia/ORO-product-1-mitigation', 'CDR OAE scoping EndNote export'),
        seed('https://raw.githubusercontent.com/flindersuni/DeepThoughtHPC-docs/master/docs/source/flindershpc2021-endnote.xml', 'endnotexml', 'github-raw', 'flindersuni/DeepThoughtHPC-docs', 'Flinders DeepThought HPC EndNote bibliography'),
        seed('https://raw.githubusercontent.com/guppy0130/j2m/master/j2m.jira', 'jira', 'github-raw', 'guppy0130/j2m', 'j2m Jira wiki markup document'),
        seed('https://raw.githubusercontent.com/membase/membase-cli/13195507facba8cb8f85dafb07df1eeff3ea7dcd/docs/cbtransfer-func-spec.jira', 'jira', 'github-raw', 'membase/membase-cli', 'cbtransfer functional spec Jira markup'),
        seed('https://raw.githubusercontent.com/membase/membase-cli/13195507facba8cb8f85dafb07df1eeff3ea7dcd/docs/cbbackup-restore-func-spec.jira', 'jira', 'github-raw', 'membase/membase-cli', 'cbbackup restore functional spec Jira markup'),
        seed('https://raw.githubusercontent.com/couchbase/couchbase-cli/a63b7323be21848f9449e61085052cdc028eb27c/docs/design/cbtransfer-func-spec.jira', 'jira', 'github-raw', 'couchbase/couchbase-cli', 'Couchbase cbtransfer design spec Jira markup'),
        seed('https://raw.githubusercontent.com/github/docs/main/content/account-and-profile/concepts/account-management.md', 'markdown_github', 'github-raw', 'github/docs', 'GitHub account management docs'),
        seed('https://raw.githubusercontent.com/github/docs/main/content/account-and-profile/concepts/contributions-on-your-profile.md', 'markdown_github', 'github-raw', 'github/docs', 'GitHub profile contributions docs'),
        seed('https://raw.githubusercontent.com/github/docs/main/content/account-and-profile/how-tos/account-settings/managing-accessibility-settings.md', 'markdown_github', 'github-raw', 'github/docs', 'GitHub accessibility settings docs'),
        seed('https://raw.githubusercontent.com/github/docs/main/content/account-and-profile/concepts/personal-profile.md', 'markdown_github', 'github-raw', 'github/docs', 'GitHub personal profile docs'),
        seed('https://raw.githubusercontent.com/github/docs/main/content/account-and-profile/concepts/username-changes.md', 'markdown_github', 'github-raw', 'github/docs', 'GitHub username changes docs'),
        seed('https://raw.githubusercontent.com/github/docs/main/content/get-started/git-basics/set-up-git.md', 'markdown_github', 'github-raw', 'github/docs', 'Set up Git docs'),
        seed('https://raw.githubusercontent.com/github/docs/main/content/get-started/git-basics/caching-your-github-credentials-in-git.md', 'markdown_github', 'github-raw', 'github/docs', 'Caching credentials docs'),
        seed('https://raw.githubusercontent.com/github/docs/main/content/repositories/creating-and-managing-repositories/creating-a-new-repository.md', 'markdown_github', 'github-raw', 'github/docs', 'Creating repository docs'),
        seed('https://raw.githubusercontent.com/github/docs/main/content/issues/planning-and-tracking-with-projects/learning-about-projects/about-projects.md', 'markdown_github', 'github-raw', 'github/docs', 'About projects docs'),
        seed('https://raw.githubusercontent.com/github/docs/main/content/actions/get-started/quickstart.md', 'markdown_github', 'github-raw', 'github/docs', 'GitHub Actions quickstart docs'),
        seed('https://raw.githubusercontent.com/github/docs/main/content/actions/get-started/understand-github-actions.md', 'markdown_github', 'github-raw', 'github/docs', 'Understand GitHub Actions docs'),
        seed('https://raw.githubusercontent.com/github/docs/main/content/actions/concepts/workflows-and-actions/workflows.md', 'markdown_github', 'github-raw', 'github/docs', 'GitHub Actions workflows docs'),
        seed('https://raw.githubusercontent.com/github/docs/main/content/actions/concepts/workflows-and-actions/contexts.md', 'markdown_github', 'github-raw', 'github/docs', 'GitHub Actions contexts docs'),
        seed('https://raw.githubusercontent.com/github/docs/main/content/actions/concepts/workflows-and-actions/expressions.md', 'markdown_github', 'github-raw', 'github/docs', 'GitHub Actions expressions docs'),
        seed('https://raw.githubusercontent.com/github/docs/main/content/actions/concepts/workflows-and-actions/variables.md', 'markdown_github', 'github-raw', 'github/docs', 'GitHub Actions variables docs'),
        seed('https://raw.githubusercontent.com/github/docs/main/content/actions/concepts/workflows-and-actions/dependency-caching.md', 'markdown_github', 'github-raw', 'github/docs', 'GitHub Actions dependency caching docs'),
        seed('https://raw.githubusercontent.com/github/docs/main/content/actions/concepts/security/secrets.md', 'markdown_github', 'github-raw', 'github/docs', 'GitHub Actions secrets docs'),
        seed('https://raw.githubusercontent.com/fletcher/MultiMarkdown-6/master/QuickStart/QuickStart.txt', 'markdown_mmd', 'github-raw', 'fletcher/MultiMarkdown-6', 'MultiMarkdown QuickStart source'),
        seed('https://raw.githubusercontent.com/fletcher/MultiMarkdown-6/master/DevelopmentNotes/DevelopmentNotes.txt', 'markdown_mmd', 'github-raw', 'fletcher/MultiMarkdown-6', 'MultiMarkdown Development Notes source'),
        seed('https://raw.githubusercontent.com/fletcher/MultiMarkdown-6/master/texmf/tex/latex/mmd6/README.md', 'markdown_mmd', 'github-raw', 'fletcher/MultiMarkdown-6', 'MultiMarkdown LaTeX package README'),
        ...fletcherMmdSiteSeedUrls(['features', 'download', 'install', 'use', 'help', 'ports']),
        seed('https://raw.githubusercontent.com/composer/composer/main/doc/01-basic-usage.md', 'markdown_phpextra', 'github-raw', 'composer/composer', 'Composer basic usage docs'),
        seed('https://raw.githubusercontent.com/composer/composer/main/doc/02-libraries.md', 'markdown_phpextra', 'github-raw', 'composer/composer', 'Composer libraries docs'),
        seed('https://raw.githubusercontent.com/composer/composer/main/doc/03-cli.md', 'markdown_phpextra', 'github-raw', 'composer/composer', 'Composer CLI docs'),
        seed('https://raw.githubusercontent.com/composer/composer/main/doc/04-schema.md', 'markdown_phpextra', 'github-raw', 'composer/composer', 'Composer schema docs'),
        seed('https://raw.githubusercontent.com/composer/composer/main/doc/05-repositories.md', 'markdown_phpextra', 'github-raw', 'composer/composer', 'Composer repositories docs'),
        seed('https://raw.githubusercontent.com/composer/composer/main/doc/06-config.md', 'markdown_phpextra', 'github-raw', 'composer/composer', 'Composer config docs'),
        seed('https://raw.githubusercontent.com/composer/composer/main/doc/07-runtime.md', 'markdown_phpextra', 'github-raw', 'composer/composer', 'Composer runtime docs'),
        seed('https://raw.githubusercontent.com/b0mbix/markupit/e110aa5b95cf3263796adffe85a7fbf5eae4ad50/misc/ast_analysis/gfm.json', 'native', 'github-raw', 'b0mbix/markupit', 'Markupit generated Pandoc native JSON AST'),
        seed('https://raw.githubusercontent.com/geometer/FBReaderJ/e83aec9f94084aa59d39e33876bdb6fdc275c95e/obsolete/help/MiniHelp.fr.fb2', 'fb2', 'github-raw', 'geometer/FBReaderJ', 'FBReaderJ French mini help FB2'),
        seed('https://raw.githubusercontent.com/bitcoin/bips/master/bip-0039.mediawiki', 'mediawiki', 'github-raw', 'bitcoin/bips', 'BIP 39 MediaWiki'),
        seed('https://raw.githubusercontent.com/scripting/Scripting-News/master/blog/opml/2026/04.opml', 'opml', 'github-raw', 'scripting/Scripting-News', 'Scripting News April 2026 OPML'),
        seed('https://raw.githubusercontent.com/proycon/homepage/master/proycon.ris', 'ris', 'github-raw', 'proycon/homepage', 'Proycon publications RIS'),
        seed('https://policyreview.info/jats/policyreview-2021-2-1546.xml', 'jats', 'journal-web', 'policyreview.info', 'Internet Policy Review JATS article'),
        seed('https://pubs.usgs.gov/sir/2026/5124/sir20265124.XML', 'bits', 'government-web', 'pubs.usgs.gov', 'USGS Scientific Investigations Report 2026-5124 BITS'),
        seed('https://dicom.nema.org/medical/Dicom/2024d/source/docbook/part18/part18.xml', 'docbook', 'standards-web', 'dicom.nema.org', 'DICOM PS3.18 DocBook'),
        seed('https://raw.githubusercontent.com/jchiquet/quarto-hceres/55fdc1e6f75e710eb67bfb0650bf237ff62886ca/references-HAL.bib', 'biblatex', 'github-raw', 'jchiquet/quarto-hceres', 'HCERES HAL references BibLaTeX'),
        seed('https://raw.githubusercontent.com/jackwasey/icd/f200683642833d89d177ee53b880df1eab70d1cf/vignettes/icdpkg.bib', 'biblatex', 'github-raw', 'jackwasey/icd', 'ICD package vignette bibliography BibLaTeX'),
        seed('https://raw.githubusercontent.com/Digital-Media/HagenbergThesis/1cdce816e07b37bef258a2cb0892cc9003a9f2f1/documents/HgbArticle/hgbreferences.bib', 'biblatex', 'github-raw', 'Digital-Media/HagenbergThesis', 'Hagenberg thesis article bibliography BibLaTeX'),
        seed('https://raw.githubusercontent.com/TrialAndErrorOrg/archive/0c5d918396dc781e398e62c59231fe855770330b/issues/issue-2/e4-ross/Ross.bib', 'biblatex', 'github-raw', 'TrialAndErrorOrg/archive', 'Trial and Error Ross bibliography BibLaTeX'),
        seed('https://raw.githubusercontent.com/jackwasey/icd/f200683642833d89d177ee53b880df1eab70d1cf/vignettes/gplv3.bib', 'biblatex', 'github-raw', 'jackwasey/icd', 'ICD GPL bibliography BibLaTeX'),
        seed('https://raw.githubusercontent.com/jackwasey/icd/f200683642833d89d177ee53b880df1eab70d1cf/vignettes/icd.bib', 'biblatex', 'github-raw', 'jackwasey/icd', 'ICD vignette bibliography BibLaTeX'),
        seed('https://raw.githubusercontent.com/jackwasey/icd/f200683642833d89d177ee53b880df1eab70d1cf/vignettes/icdjss.bib', 'biblatex', 'github-raw', 'jackwasey/icd', 'ICD JSS vignette bibliography BibLaTeX'),
        seed('https://raw.githubusercontent.com/jackwasey/icd/f200683642833d89d177ee53b880df1eab70d1cf/vignettes/other.bib', 'biblatex', 'github-raw', 'jackwasey/icd', 'ICD other references BibLaTeX'),
        seed('https://raw.githubusercontent.com/rstudio/blogdown/main/docs/book.bib', 'biblatex', 'github-raw', 'rstudio/blogdown', 'Blogdown book bibliography BibLaTeX'),
        seed('https://raw.githubusercontent.com/rstudio/bookdown/main/inst/examples/book.bib', 'biblatex', 'github-raw', 'rstudio/bookdown', 'Bookdown example book bibliography BibLaTeX'),
        seed('https://raw.githubusercontent.com/rstudio/bookdown/main/inst/rstudio/templates/project/resources/common/book.bib', 'biblatex', 'github-raw', 'rstudio/bookdown', 'Bookdown template book bibliography BibLaTeX'),
        seed('https://raw.githubusercontent.com/quarto-dev/quarto-web/main/references.bib', 'biblatex', 'github-raw', 'quarto-dev/quarto-web', 'Quarto web references BibLaTeX'),
        seed('https://raw.githubusercontent.com/quarto-dev/quarto-web/main/docs/get-started/authoring/_notebooks/references.bib', 'biblatex', 'github-raw', 'quarto-dev/quarto-web', 'Quarto authoring notebook references BibLaTeX'),
        seed('https://raw.githubusercontent.com/quantum-journal/quantum-journal/master/quantum-bibliographystyle-demo.bib', 'bibtex', 'github-raw', 'quantum-journal/quantum-journal', 'Quantum bibliography style demo BibTeX'),
        seed('https://api.crossref.org/works/10.7554/eLife.32822/transform/application/x-research-info-systems', 'ris', 'crossref-api', 'crossref.org', 'Crossref RIS export for eLife 32822'),
        seed('https://api.crossref.org/works/10.1038/s41586-020-2649-2/transform/application/x-research-info-systems', 'ris', 'crossref-api', 'crossref.org', 'Crossref RIS export for Nature article'),
        seed('https://api.crossref.org/works/10.1126/science.169.3946.635/transform/application/x-research-info-systems', 'ris', 'crossref-api', 'crossref.org', 'Crossref RIS export for Science article'),
        seed('https://api.crossref.org/works/10.7554/eLife.32822/transform/application/vnd.citationstyles.csl+json', 'csljson', 'crossref-api', 'crossref.org', 'Crossref CSL JSON for eLife 32822'),
        seed('https://api.crossref.org/works/10.1038/s41586-020-2649-2/transform/application/vnd.citationstyles.csl+json', 'csljson', 'crossref-api', 'crossref.org', 'Crossref CSL JSON for Nature article'),
        seed('https://api.crossref.org/works/10.1126/science.169.3946.635/transform/application/vnd.citationstyles.csl+json', 'csljson', 'crossref-api', 'crossref.org', 'Crossref CSL JSON for Science article'),
        seed('https://api.crossref.org/works/10.1371/journal.pone.0000308/transform/application/vnd.citationstyles.csl+json', 'csljson', 'crossref-api', 'crossref.org', 'Crossref CSL JSON for PLOS ONE article'),
        seed('https://api.crossref.org/works/10.1103/PhysRevLett.116.061102/transform/application/vnd.citationstyles.csl+json', 'csljson', 'crossref-api', 'crossref.org', 'Crossref CSL JSON for PRL article'),
        ...crossrefBibliographySeedUrls([
            ['10.1038/nature12373', 'Nature 12373'],
            ['10.1126/science.1259855', 'Science 1259855'],
            ['10.1093/nar/gkab1021', 'NAR gkab1021'],
            ['10.1145/3544548.3580951', 'ACM CHI 2023 paper'],
            ['10.1109/5.771073', 'IEEE paper'],
            ['10.1007/s00125-020-05180-x', 'Springer diabetes article'],
            ['10.1016/j.cell.2020.04.011', 'Cell article'],
            ['10.1016/j.neuron.2020.01.011', 'Neuron article'],
            ['10.7554/eLife.67490', 'eLife 67490'],
            ['10.3389/fpsyg.2019.01234', 'Frontiers psychology'],
            ['10.1103/PhysRevD.98.030001', 'Physical Review D'],
            ['10.3390/ijms21103574', 'MDPI IJMS'],
            ['10.1038/s41586-021-03819-2', 'Nature 2021 article'],
            ['10.1126/science.abj8754', 'Science abj8754'],
            ['10.1016/j.cell.2021.02.018', 'Cell 2021 article'],
        ]),
        seed('https://raw.githubusercontent.com/commonmark/commonmark-spec/master/spec.txt', 'commonmark', 'github-raw', 'commonmark/commonmark-spec', 'CommonMark specification source'),
        seed('https://raw.githubusercontent.com/commonmark/cmark/master/why-cmark-and-not-x.md', 'commonmark', 'github-raw', 'commonmark/cmark', 'Why cmark CommonMark document'),
        seed('https://raw.githubusercontent.com/nodejs/node/main/BUILDING.md', 'commonmark', 'github-raw', 'nodejs/node', 'Node.js building documentation CommonMark'),
        seed('https://raw.githubusercontent.com/nodejs/node/main/CONTRIBUTING.md', 'commonmark', 'github-raw', 'nodejs/node', 'Node.js contributing documentation CommonMark'),
        seed('https://raw.githubusercontent.com/nodejs/node/main/GOVERNANCE.md', 'commonmark', 'github-raw', 'nodejs/node', 'Node.js governance documentation CommonMark'),
        seed('https://raw.githubusercontent.com/nodejs/node/main/SECURITY.md', 'commonmark', 'github-raw', 'nodejs/node', 'Node.js security documentation CommonMark'),
        seed('https://raw.githubusercontent.com/nodejs/node/main/README.md', 'commonmark', 'github-raw', 'nodejs/node', 'Node.js README CommonMark'),
        seed('https://raw.githubusercontent.com/nodejs/node/main/benchmark/README.md', 'commonmark', 'github-raw', 'nodejs/node', 'Node.js benchmark README CommonMark'),
        seed('https://raw.githubusercontent.com/nodejs/node/main/doc/api/http.md', 'commonmark', 'github-raw', 'nodejs/node', 'Node.js HTTP API CommonMark'),
        seed('https://raw.githubusercontent.com/nodejs/node/main/doc/api/url.md', 'commonmark', 'github-raw', 'nodejs/node', 'Node.js URL API CommonMark'),
        seed('https://raw.githubusercontent.com/nodejs/node/main/doc/api/path.md', 'commonmark', 'github-raw', 'nodejs/node', 'Node.js Path API CommonMark'),
        seed('https://raw.githubusercontent.com/nodejs/node/main/doc/api/stream.md', 'commonmark', 'github-raw', 'nodejs/node', 'Node.js Stream API CommonMark'),
        seed('https://raw.githubusercontent.com/nodejs/node/main/doc/api/crypto.md', 'commonmark', 'github-raw', 'nodejs/node', 'Node.js Crypto API CommonMark'),
        seed('https://raw.githubusercontent.com/nodejs/node/main/doc/api/assert.md', 'commonmark', 'github-raw', 'nodejs/node', 'Node.js Assert API CommonMark'),
        seed('https://raw.githubusercontent.com/nodejs/node/main/doc/api/buffer.md', 'commonmark', 'github-raw', 'nodejs/node', 'Node.js Buffer API CommonMark'),
        seed('https://raw.githubusercontent.com/nodejs/node/main/doc/api/child_process.md', 'commonmark', 'github-raw', 'nodejs/node', 'Node.js Child Process API CommonMark'),
        seed('https://raw.githubusercontent.com/nodejs/node/main/doc/api/cli.md', 'commonmark', 'github-raw', 'nodejs/node', 'Node.js CLI API CommonMark'),
        seed('https://raw.githubusercontent.com/nodejs/node/main/doc/api/console.md', 'commonmark', 'github-raw', 'nodejs/node', 'Node.js Console API CommonMark'),
        seed('https://raw.githubusercontent.com/nodejs/node/main/doc/api/dns.md', 'commonmark', 'github-raw', 'nodejs/node', 'Node.js DNS API CommonMark'),
        seed('https://raw.githubusercontent.com/nodejs/node/main/doc/api/events.md', 'commonmark', 'github-raw', 'nodejs/node', 'Node.js Events API CommonMark'),
        seed('https://raw.githubusercontent.com/nodejs/node/main/doc/api/os.md', 'commonmark', 'github-raw', 'nodejs/node', 'Node.js OS API CommonMark'),
        seed('https://raw.githubusercontent.com/nodejs/node/main/doc/api/process.md', 'commonmark', 'github-raw', 'nodejs/node', 'Node.js Process API CommonMark'),
        seed('https://raw.githubusercontent.com/commonmark/commonmark-spec/master/alternative-html-blocks.txt', 'commonmark_x', 'github-raw', 'commonmark/commonmark-spec', 'CommonMark alternative HTML blocks discussion'),
        ...pythonMarkdownCommonMarkXSeedUrls(['abbreviations', 'admonition', 'api', 'attr_list', 'code_hilite', 'definition_lists', 'extra', 'fenced_code_blocks', 'footnotes', 'index', 'legacy_attrs', 'legacy_em', 'md_in_html', 'meta_data', 'nl2br', 'tables', 'toc', 'wikilinks', 'sane_lists']),
        seed('https://raw.githubusercontent.com/Python-Markdown/markdown/master/docs/index.md', 'commonmark_x', 'github-raw', 'Python-Markdown/markdown', 'Python Markdown index docs'),
        seed('https://raw.githubusercontent.com/Python-Markdown/markdown/master/docs/cli.md', 'commonmark_x', 'github-raw', 'Python-Markdown/markdown', 'Python Markdown CLI docs'),
        seed('https://raw.githubusercontent.com/Python-Markdown/markdown/master/docs/install.md', 'commonmark_x', 'github-raw', 'Python-Markdown/markdown', 'Python Markdown install docs'),
        seed('https://raw.githubusercontent.com/Python-Markdown/markdown/master/docs/reference.md', 'commonmark_x', 'github-raw', 'Python-Markdown/markdown', 'Python Markdown reference docs'),
        seed('https://raw.githubusercontent.com/Python-Markdown/markdown/master/docs/sanitization.md', 'commonmark_x', 'github-raw', 'Python-Markdown/markdown', 'Python Markdown sanitization docs'),
        seed('https://raw.githubusercontent.com/github/docs/main/content/pull-requests/collaborating-with-pull-requests/reviewing-changes-in-pull-requests/about-pull-request-reviews.md', 'gfm', 'github-raw', 'github/docs', 'GitHub pull request reviews GFM'),
        seed('https://raw.githubusercontent.com/github/docs/main/content/account-and-profile/concepts/email-addresses.md', 'gfm', 'github-raw', 'github/docs', 'GitHub email addresses docs GFM'),
        seed('https://raw.githubusercontent.com/github/docs/main/content/account-and-profile/concepts/organization-membership.md', 'gfm', 'github-raw', 'github/docs', 'GitHub organization membership docs GFM'),
        seed('https://raw.githubusercontent.com/github/docs/main/content/account-and-profile/concepts/organization-profile.md', 'gfm', 'github-raw', 'github/docs', 'GitHub organization profile docs GFM'),
        seed('https://raw.githubusercontent.com/WordPress/gutenberg/trunk/docs/contributors/code/coding-guidelines.md', 'gfm', 'github-raw', 'WordPress/gutenberg', 'Gutenberg coding guidelines GFM'),
        seed('https://raw.githubusercontent.com/WordPress/gutenberg/trunk/docs/contributors/code/deprecations.md', 'gfm', 'github-raw', 'WordPress/gutenberg', 'Gutenberg deprecations docs GFM'),
        seed('https://raw.githubusercontent.com/WordPress/gutenberg/trunk/docs/contributors/code/auto-cherry-picking.md', 'gfm', 'github-raw', 'WordPress/gutenberg', 'Gutenberg auto cherry picking docs GFM'),
        seed('https://raw.githubusercontent.com/WordPress/gutenberg/trunk/docs/contributors/code/backward-compatibility.md', 'gfm', 'github-raw', 'WordPress/gutenberg', 'Gutenberg backward compatibility docs GFM'),
        seed('https://raw.githubusercontent.com/github/docs/main/content/account-and-profile/how-tos/account-management/deleting-your-personal-account.md', 'gfm', 'github-raw', 'github/docs', 'GitHub deleting account docs GFM'),
        seed('https://raw.githubusercontent.com/github/docs/main/content/account-and-profile/how-tos/account-management/managing-multiple-accounts.md', 'gfm', 'github-raw', 'github/docs', 'GitHub multiple accounts docs GFM'),
        seed('https://raw.githubusercontent.com/github/docs/main/content/account-and-profile/how-tos/email-preferences/setting-your-commit-email-address.md', 'gfm', 'github-raw', 'github/docs', 'GitHub commit email address docs GFM'),
        seed('https://raw.githubusercontent.com/WordPress/gutenberg/trunk/docs/contributors/code/back-merging-to-wp-core.md', 'gfm', 'github-raw', 'WordPress/gutenberg', 'Gutenberg back merging docs GFM'),
        seed('https://raw.githubusercontent.com/WordPress/gutenberg/trunk/docs/contributors/accessibility-testing.md', 'gfm', 'github-raw', 'WordPress/gutenberg', 'Gutenberg accessibility testing docs GFM'),
        seed('https://raw.githubusercontent.com/WordPress/gutenberg/trunk/docs/contributors/code/README.md', 'gfm', 'github-raw', 'WordPress/gutenberg', 'Gutenberg code contributor README GFM'),
        seed('https://raw.githubusercontent.com/WordPress/gutenberg/trunk/docs/contributors/code/build-system-function-prefixing.md', 'gfm', 'github-raw', 'WordPress/gutenberg', 'Gutenberg build system function prefixing GFM'),
        seed('https://raw.githubusercontent.com/WordPress/gutenberg/trunk/docs/contributors/code/getting-started-with-code-contribution.md', 'gfm', 'github-raw', 'WordPress/gutenberg', 'Gutenberg getting started with code contribution GFM'),
        seed('https://raw.githubusercontent.com/WordPress/gutenberg/trunk/docs/contributors/code/git-workflow.md', 'gfm', 'github-raw', 'WordPress/gutenberg', 'Gutenberg git workflow GFM'),
        seed('https://raw.githubusercontent.com/WordPress/gutenberg/trunk/docs/contributors/code/how-to-get-your-pull-request-reviewed.md', 'gfm', 'github-raw', 'WordPress/gutenberg', 'Gutenberg pull request review GFM'),
        seed('https://raw.githubusercontent.com/WordPress/gutenberg/trunk/docs/contributors/code/managing-packages.md', 'gfm', 'github-raw', 'WordPress/gutenberg', 'Gutenberg managing packages GFM'),
        seed('https://raw.githubusercontent.com/WordPress/gutenberg/trunk/docs/contributors/code/scripts.md', 'gfm', 'github-raw', 'WordPress/gutenberg', 'Gutenberg scripts docs GFM'),
        seed('https://raw.githubusercontent.com/WordPress/gutenberg/trunk/docs/contributors/code/testing-overview.md', 'gfm', 'github-raw', 'WordPress/gutenberg', 'Gutenberg testing overview GFM'),
        seed('https://raw.githubusercontent.com/WordPress/gutenberg/trunk/docs/contributors/code/workspace-development.md', 'gfm', 'github-raw', 'WordPress/gutenberg', 'Gutenberg workspace development GFM'),
        seed('https://fletcherpenney.net/multimarkdown/index.txt', 'markdown_mmd', 'author-web', 'fletcherpenney.net', 'MultiMarkdown website source index'),
        seed('https://daringfireball.net/projects/markdown/syntax.text', 'markdown_strict', 'author-web', 'daringfireball.net', 'Original Markdown syntax'),
        seed('https://daringfireball.net/projects/markdown/basics.text', 'markdown_strict', 'author-web', 'daringfireball.net', 'Original Markdown basics'),
        seed('https://raw.githubusercontent.com/jgm/CommonMark/master/README.md', 'markdown_strict', 'github-raw', 'jgm/CommonMark', 'CommonMark Haskell README'),
        seed('https://raw.githubusercontent.com/jgm/pandoc/main/MANUAL.txt', 'markdown_strict', 'github-raw', 'jgm/pandoc', 'Pandoc manual text'),
        seed('https://raw.githubusercontent.com/jgm/pandoc/main/README.md', 'markdown_strict', 'github-raw', 'jgm/pandoc', 'Pandoc README'),
        seed('https://daringfireball.net/projects/markdown/dingus.text', 'markdown_strict', 'author-web', 'daringfireball.net', 'Markdown Dingus source text'),
        seed('https://raw.githubusercontent.com/jgm/pandoc/main/CONTRIBUTING.md', 'markdown_strict', 'github-raw', 'jgm/pandoc', 'Pandoc contributing Markdown'),
        seed('https://raw.githubusercontent.com/jgm/pandoc/main/INSTALL.md', 'markdown_strict', 'github-raw', 'jgm/pandoc', 'Pandoc install Markdown'),
        seed('https://raw.githubusercontent.com/jgm/pandoc/main/doc/org.md', 'markdown_strict', 'github-raw', 'jgm/pandoc', 'Pandoc org reader docs strict Markdown'),
        seed('https://raw.githubusercontent.com/jgm/pandoc/main/doc/custom-readers.md', 'markdown_strict', 'github-raw', 'jgm/pandoc', 'Pandoc custom readers docs strict Markdown'),
        seed('https://raw.githubusercontent.com/jgm/pandoc/main/doc/lua-filters.md', 'markdown_strict', 'github-raw', 'jgm/pandoc', 'Pandoc Lua filters docs strict Markdown'),
        seed('https://raw.githubusercontent.com/jgm/pandoc/main/doc/using-the-pandoc-api.md', 'markdown_strict', 'github-raw', 'jgm/pandoc', 'Pandoc API docs strict Markdown'),
        seed('https://raw.githubusercontent.com/jgm/pandoc/main/doc/getting-started.md', 'markdown_strict', 'github-raw', 'jgm/pandoc', 'Pandoc getting started strict Markdown'),
        seed('https://raw.githubusercontent.com/jgm/pandoc/main/doc/custom-writers.md', 'markdown_strict', 'github-raw', 'jgm/pandoc', 'Pandoc custom writers docs strict Markdown'),
        seed('https://raw.githubusercontent.com/jgm/pandoc/main/doc/customizing-pandoc.md', 'markdown_strict', 'github-raw', 'jgm/pandoc', 'Pandoc customizing docs strict Markdown'),
        seed('https://raw.githubusercontent.com/jgm/pandoc/main/doc/epub.md', 'markdown_strict', 'github-raw', 'jgm/pandoc', 'Pandoc EPUB docs strict Markdown'),
        seed('https://raw.githubusercontent.com/jgm/pandoc/main/doc/extras.md', 'markdown_strict', 'github-raw', 'jgm/pandoc', 'Pandoc extras docs strict Markdown'),
        seed('https://raw.githubusercontent.com/jgm/pandoc/main/doc/faqs.md', 'markdown_strict', 'github-raw', 'jgm/pandoc', 'Pandoc FAQs docs strict Markdown'),
        seed('https://raw.githubusercontent.com/jgm/pandoc/main/doc/filters.md', 'markdown_strict', 'github-raw', 'jgm/pandoc', 'Pandoc filters docs strict Markdown'),
        seed('https://raw.githubusercontent.com/jgm/pandoc/main/doc/jats.md', 'markdown_strict', 'github-raw', 'jgm/pandoc', 'Pandoc JATS docs strict Markdown'),
        seed('https://raw.githubusercontent.com/jgm/pandoc/main/doc/libraries.md', 'markdown_strict', 'github-raw', 'jgm/pandoc', 'Pandoc libraries docs strict Markdown'),
        seed('https://raw.githubusercontent.com/jgm/pandoc/main/doc/pandoc-lua.md', 'markdown_strict', 'github-raw', 'jgm/pandoc', 'Pandoc Lua docs strict Markdown'),
        seed('https://raw.githubusercontent.com/jgm/pandoc/main/doc/pandoc-server.md', 'markdown_strict', 'github-raw', 'jgm/pandoc', 'Pandoc server docs strict Markdown'),
        seed('https://raw.githubusercontent.com/jgm/pandoc/main/doc/xml.md', 'markdown_strict', 'github-raw', 'jgm/pandoc', 'Pandoc XML docs strict Markdown'),
        seed('https://raw.githubusercontent.com/jgm/pandoc/main/doc/typst-property-output.md', 'markdown_strict', 'github-raw', 'jgm/pandoc', 'Pandoc Typst property output docs strict Markdown'),
        seed('https://raw.githubusercontent.com/php-fig/fig-standards/master/accepted/PSR-1-basic-coding-standard.md', 'markdown_phpextra', 'github-raw', 'php-fig/fig-standards', 'PSR-1 basic coding standard'),
        seed('https://raw.githubusercontent.com/php-fig/fig-standards/master/accepted/PSR-12-extended-coding-style-guide.md', 'markdown_phpextra', 'github-raw', 'php-fig/fig-standards', 'PSR-12 extended coding style guide'),
        ...phpFigMarkdownExtraSeedUrls(['PSR-0', 'PSR-6-cache', 'PSR-7-http-message', 'PSR-11-container', 'PSR-13-links', 'PSR-14-event-dispatcher', 'PSR-15-request-handlers', 'PSR-16-simple-cache', 'PSR-17-http-factory', 'PSR-18-http-client', 'PSR-2-coding-style-guide', 'PSR-20-clock', 'PSR-3-logger-interface']),
        seed('https://raw.githubusercontent.com/python/cpython/main/Doc/tutorial/introduction.rst', 'rst', 'github-raw', 'python/cpython', 'Python tutorial introduction RST'),
        seed('https://raw.githubusercontent.com/python/cpython/main/Doc/library/pathlib.rst', 'rst', 'github-raw', 'python/cpython', 'Python pathlib documentation RST'),
        seed('https://raw.githubusercontent.com/python/cpython/main/Doc/howto/logging.rst', 'rst', 'github-raw', 'python/cpython', 'Python logging HOWTO RST'),
        ...pythonRstSeedUrls([
            ['Doc/tutorial/controlflow.rst', 'Python tutorial control flow RST'],
            ['Doc/tutorial/datastructures.rst', 'Python tutorial data structures RST'],
            ['Doc/tutorial/modules.rst', 'Python tutorial modules RST'],
            ['Doc/tutorial/classes.rst', 'Python tutorial classes RST'],
            ['Doc/tutorial/inputoutput.rst', 'Python tutorial input output RST'],
            ['Doc/tutorial/errors.rst', 'Python tutorial errors RST'],
            ['Doc/library/os.rst', 'Python os module docs RST'],
            ['Doc/library/json.rst', 'Python json module docs RST'],
            ['Doc/library/asyncio.rst', 'Python asyncio module docs RST'],
            ['Doc/library/re.rst', 'Python re documentation RST'],
            ['Doc/library/sqlite3.rst', 'Python sqlite3 documentation RST'],
            ['Doc/library/subprocess.rst', 'Python subprocess documentation RST'],
            ['Doc/library/unittest.rst', 'Python unittest documentation RST'],
            ['Doc/howto/regex.rst', 'Python regex HOWTO RST'],
            ['Doc/howto/argparse.rst', 'Python argparse HOWTO RST'],
            ['Doc/howto/urllib2.rst', 'Python urllib HOWTO RST'],
            ['Doc/howto/sorting.rst', 'Python sorting HOWTO RST'],
        ]),
        seed('https://americanenglish.state.gov/files/ae/resource_files/to_build_a_fire-efl_final.epub', 'epub', 'government-web', 'americanenglish.state.gov', 'To Build a Fire EPUB'),
        seed('https://americanenglish.state.gov/files/ae/resource_files/the_gift_of_the_magi.epub', 'epub', 'government-web', 'americanenglish.state.gov', 'The Gift of the Magi EPUB'),
        seed('https://americanenglish.state.gov/files/ae/resource_files/design_for_drama.epub', 'epub', 'government-web', 'americanenglish.state.gov', 'Design for Drama EPUB'),
        seed('https://elifesciences.org/articles/32822.xml', 'jats', 'journal-web', 'elifesciences.org', 'eLife article 32822 JATS XML'),
        seed('https://elifesciences.org/articles/09560.xml', 'jats', 'journal-web', 'elifesciences.org', 'eLife article 09560 JATS XML'),
        ...elifeJatsSeedUrls(['00013', '00031', '00288', '00311', '00666', '01201', '01567', '01832', '03032', '05068', '06547', '07464', '10181', '12386', '34420', '46269', '52760', '57679', '64793', '67490']),
        seed('https://www.nist.gov/document/componentsofcybersecurityframeworkpptx', 'pptx', 'government-web', 'nist.gov', 'NIST Components of the Cybersecurity Framework PPTX'),
        seed('https://ixpe.msfc.nasa.gov/for_scientists/templates/IXPE-Presentation-Template.pptx', 'pptx', 'government-web', 'nasa.gov', 'IXPE presentation template PPTX'),
        seed('https://laboremploymenttraining.ornl.gov/wp-content/uploads/2019/08/Good-Cyber-Hygiene-Habits-Alex-Boyd-8-15-19.pptx', 'pptx', 'government-web', 'ornl.gov', 'ORNL good cyber hygiene habits PPTX'),
        seed('https://wsac.wa.gov/sites/default/files/2021-22_MS%20HS%20Student%20and%20Family_0.pptx', 'pptx', 'government-web', 'wsac.wa.gov', 'WSAC student and family presentation PPTX'),
        seed('https://www.doit.nm.gov/wp-content/uploads/sites/4/2022/10/Raja-Sambamdan_Dan-Garcia-CIO-Forum.pptx', 'pptx', 'government-web', 'doit.nm.gov', 'New Mexico CIO forum presentation PPTX'),
        seed('https://www.faa.gov/sites/faa.gov/files/VVSummit2022-1.2-Technical-Center-Opening-508.pptx', 'pptx', 'government-web', 'faa.gov', 'FAA technical center opening presentation PPTX'),
        seed('https://www.idmanagement.gov/docs/icam-governance-sample-graphics.pptx', 'pptx', 'government-web', 'idmanagement.gov', 'ICAM governance sample graphics PPTX'),
        seed('https://odedistrict.oregon.gov/AdvisoryCommittees/Documents/2022-01-21_cio-quarterly-it-managers-meeting.pptx', 'pptx', 'government-web', 'odedistrict.oregon.gov', 'Oregon CIO quarterly IT managers meeting PPTX'),
        seed('https://www.research.va.gov/programs/epros/education/webinars/orppe-100821.pptx', 'pptx', 'government-web', 'research.va.gov', 'VA regulatory research cybersecurity guidance PPTX'),
        seed('https://www.cpuc.ca.gov/-/media/cpuc-website/files/legacyfiles/f/6442456398-future-proofing-the-evse-10-1-.pptx', 'pptx', 'government-web', 'cpuc.ca.gov', 'California CPUC VGI future proofing PPTX'),
        seed('https://raw.githubusercontent.com/quantum-journal/quantum-journal/master/quantumarticle.pdf', 'pdf', 'github-raw', 'quantum-journal/quantum-journal', 'Quantum article class documentation PDF'),
        seed('https://raw.githubusercontent.com/quantum-journal/quantum-journal/master/quantum-template.pdf', 'pdf', 'github-raw', 'quantum-journal/quantum-journal', 'Quantum article template PDF'),
        seed('https://raw.githubusercontent.com/quantum-journal/quantum-journal/master/quantum-bibliographystyle-demo.pdf', 'pdf', 'github-raw', 'quantum-journal/quantum-journal', 'Quantum bibliography style demo PDF'),
        ...arxivPdfSeedUrls([
            ['1706.03762', 'Attention Is All You Need PDF'],
            ['2005.14165', 'Language Models are Few-Shot Learners PDF'],
            ['1810.04805', 'BERT paper PDF'],
            ['1512.03385', 'ResNet paper PDF'],
            ['1409.1556', 'VGG networks paper PDF'],
            ['1603.04467', 'WaveNet paper PDF'],
            ['1905.11946', 'EfficientNet paper PDF'],
            ['1910.10683', 'T5 paper PDF'],
            ['1412.6980', 'Adam optimizer paper PDF'],
            ['1301.3781', 'word2vec paper PDF'],
            ['2103.00020', 'CLIP paper PDF'],
            ['2203.02155', 'Chinchilla paper PDF'],
            ['2302.13971', 'LLaMA paper PDF'],
            ['2303.08774', 'GPT-4 technical report PDF'],
            ['2305.10403', 'Tree of Thoughts paper PDF'],
            ['2307.09288', 'Llama 2 paper PDF'],
            ['2310.06825', 'Mistral 7B paper PDF'],
            ['2312.00752', 'Gemini technical report PDF'],
            ['2402.17764', 'The Era of 1-bit LLMs PDF'],
            ['2405.21060', 'Phi-3 technical report PDF'],
        ]),
        seed('https://www.columbus.in.gov/columbus-transit/wp-content/uploads/sites/11/2020/02/Call-A-Bus-APPLICATION.doc', 'doc', 'government-web', 'columbus.in.gov', 'ADA paratransit application DOC'),
        seed('https://www.waterboards.ca.gov/rwqcb3/board_decisions/adopted_orders/2010/2010_Oilfield_Reuse_appl_form.doc', 'doc', 'government-web', 'waterboards.ca.gov', 'Water board waste discharge application DOC'),
        seed('https://asp-prod1.crk.umn.edu/csms/app/doctemplates/syllabustemplate.doc', 'doc', 'education-web', 'umn.edu', 'University of Minnesota Crookston syllabus template DOC'),
        seed('https://www.nyc.gov/assets/planning/download/office/applicants/applicant-portal/land_use_application_form.doc', 'doc', 'government-web', 'nyc.gov', 'NYC land use application form DOC'),
        seed('https://www.finra.org/sites/default/files/NewAccountApplicationAllSectionsWordTemplate.doc', 'doc', 'organization-web', 'finra.org', 'FINRA new account application template DOC'),
        seed('https://ofm.wa.gov/wp-content/uploads/sites/default/files/public/shr/Rules/Uniformed%20Service%20Shared%20Leave%20Policy.doc', 'doc', 'government-web', 'ofm.wa.gov', 'Washington shared leave policy DOC'),
        seed('https://raw.githubusercontent.com/docbook/xslt10-stylesheets/master/xsl/docsrc/reference.xml', 'docbook', 'github-raw', 'docbook/xslt10-stylesheets', 'DocBook XSLT reference manual XML'),
        seed('https://raw.githubusercontent.com/docbook/xslt10-stylesheets/master/xsl/docsrc/warranty.xml', 'docbook', 'github-raw', 'docbook/xslt10-stylesheets', 'DocBook XSLT warranty documentation XML'),
        seed('https://raw.githubusercontent.com/docbook/xslt10-stylesheets/master/xsl/params/abstract.notitle.enabled.xml', 'docbook', 'github-raw', 'docbook/xslt10-stylesheets', 'DocBook abstract title parameter XML'),
        seed('https://raw.githubusercontent.com/docbook/xslt10-stylesheets/master/xsl/params/admon.graphics.xml', 'docbook', 'github-raw', 'docbook/xslt10-stylesheets', 'DocBook admon graphics parameter XML'),
        ...docbookParamSeedUrls(['abstract.properties', 'abstract.title.properties', 'activate.external.olinks', 'active.toc', 'admon.graphics.extension', 'admon.graphics.path', 'admon.style', 'admon.textlabel', 'alignment', 'appendix.autolabel']),
        seed('https://dir.texas.gov/sites/default/files/2024-09/Model%20Policy%20for%20Preventing%20Use%20of%20Prohibited%20Technology%20and%20Covered%20Applications.docx', 'docx', 'government-web', 'dir.texas.gov', 'Texas prohibited technology model policy DOCX'),
        seed('https://www.fedramp.gov/resources/templates/FedRAMP-High-Moderate-Low-LI-SaaS-Baseline-System-Security-Plan-%28SSP%29.docx', 'docx', 'government-web', 'fedramp.gov', 'FedRAMP SSP template DOCX'),
        seed('https://www.dgs.ca.gov/-/media/Divisions/OFAM/Statewide-Travel-Program/Forms/Miscellaneous/State-Travel-Policy-Rsources-docs/SAM-41171FAQsJune-2024.docx', 'docx', 'government-web', 'dgs.ca.gov', 'California state travel policy FAQ DOCX'),
        seed('https://opportunity.nebraska.gov/wp-content/uploads/2025/02/Limited-English-Proficiency-Guidance-Template.docx', 'docx', 'government-web', 'opportunity.nebraska.gov', 'Nebraska LEP guidance template DOCX'),
        seed('https://www.osha.gov/sites/default/files/covid-19-ets2-sample-employee-choice-vaccination-policy.docx', 'docx', 'government-web', 'osha.gov', 'OSHA vaccination policy template DOCX'),
        seed('https://www.cn.nysed.gov/sites/cn/files/prohibitionagainstmealshaming.docx', 'docx', 'government-web', 'nysed.gov', 'New York meal shaming policy template DOCX'),
        seed('https://health.hawaii.gov/substance-abuse/files/2023/04/Policy-Logic-Model-Template.docx', 'docx', 'government-web', 'health.hawaii.gov', 'Hawaii policy logic model template DOCX'),
        seed('https://oklahoma.gov/content/dam/ok/en/tset/documents/fy25-documents/fy25-forms/Tobacco-Free%20Policy%20Template.docx', 'docx', 'government-web', 'oklahoma.gov', 'Oklahoma tobacco-free policy template DOCX'),
        seed('https://dcf.wisconsin.gov/files/ccic/doc/policy-template-fcc.docx', 'docx', 'government-web', 'dcf.wisconsin.gov', 'Wisconsin child care policy template DOCX'),
        seed('https://www.dhs.wisconsin.gov/lh-depts/health-officers/lhdpolicyproceduretemplate.docx', 'docx', 'government-web', 'dhs.wisconsin.gov', 'Wisconsin local health department policy template DOCX'),
        seed('https://kidsfiles.delaware.gov/policies/dfs/policy-0101-forms-dfs-policy-template.docx', 'docx', 'government-web', 'kidsfiles.delaware.gov', 'Delaware DFS policy template DOCX'),
        seed('https://www.nasa.gov/wp-content/uploads/2024/09/biosketch-form.docx', 'docx', 'government-web', 'nasa.gov', 'NASA biosketch form DOCX'),
        seed('https://www.mspb.ms.gov/sites/mspb/files/MSPB_File/Resources%20for%20HR/Templates/Artificial%20Intelligence%20HR%20Policy%204.26.docx', 'docx', 'government-web', 'mspb.ms.gov', 'Mississippi AI HR policy template DOCX'),
        seed('https://www.dgs.ca.gov/-/media/Divisions/OFAM/Statewide-Travel-Program/Resources/Training-and-Guides/Uber-for-Business-Policy-Template-2025.docx', 'docx', 'government-web', 'dgs.ca.gov', 'California Uber for Business policy template DOCX'),
        seed('https://mnhousing.gov/documents/53210/lirc-2026-initial-app-rtf/view', 'rtf', 'government-web', 'mnhousing.gov', 'Minnesota housing LIRC application RTF'),
        seed('https://www.dot.ny.gov/divisions/operating/osss/bus-repository/application_passenger.rtf', 'rtf', 'government-web', 'dot.ny.gov', 'New York passenger authority application RTF'),
        seed('https://www.epa.gov/sites/default/files/2016-06/cl175.rtf', 'rtf', 'government-web', 'epa.gov', 'EPA checklist 175 RTF'),
        seed('https://www.legis.iowa.gov/docs/iac/rule/10-02-2013.441.76.2.rtf', 'rtf', 'government-web', 'legis.iowa.gov', 'Iowa administrative rule RTF'),
        seed('https://rules.utah.gov/publicat/code_rtf/r722-350.rtf', 'rtf', 'government-web', 'rules.utah.gov', 'Utah administrative rule RTF'),
        seed('https://www.nj.gov/transportation/eng/forms/docs/maintenance/mt120a.rtf', 'rtf', 'government-web', 'nj.gov', 'New Jersey highway occupancy application RTF'),
        seed('https://www.flcourts.gov/content/download/217368/file/indigent_status_application.rtf', 'rtf', 'government-web', 'flcourts.gov', 'Florida indigent status application RTF'),
        seed('https://flcourts-media.flcourts.gov/content/download/685848/file_rtf/912b.rtf', 'rtf', 'government-web', 'flcourts.gov', 'Florida courts form 912b RTF'),
        seed('https://www.nj.gov/transportation/eng/forms/docs/maintenance/mt33a.rtf', 'rtf', 'government-web', 'nj.gov', 'New Jersey pole erection application RTF'),
        seed('https://le.utah.gov/xcode/Title53G/Chapter10/C53G-10-P6_2022050420220504.rtf', 'rtf', 'government-web', 'le.utah.gov', 'Utah education code innovation program RTF'),
        seed('https://le.utah.gov/xcode/Title26B/Chapter4/C26B-4-P1_2023050320230503.rtf', 'rtf', 'government-web', 'le.utah.gov', 'Utah health code RTF'),
        seed('https://dnr.illinois.gov/content/dam/soi/en/web/dnr/conservation/m2p/documents/monthlybudget-m2p.odt', 'odt', 'government-web', 'dnr.illinois.gov', 'Illinois monthly expenditure schedule ODT'),
        seed('https://townofgalwayny.gov/wp-content/uploads/2023/06/Planning-Board-Meeting-Notes-April-2023-DRAFT.odt', 'odt', 'municipal-web', 'townofgalwayny.gov', 'Galway planning board meeting notes ODT'),
        seed('https://www.phenix.bnl.gov/~suhanov/ncc/materials/adhesives/adhesives.odt', 'odt', 'research-web', 'bnl.gov', 'BNL conductive adhesives ODT'),
        seed('https://ndlegis.gov/assembly/69-2025/regular/documents/25-0246-03001m.odt', 'odt', 'government-web', 'ndlegis.gov', 'North Dakota legislative measure 25-0246 ODT'),
        seed('https://camdencountymo.gov/wp-content/uploads/COMMUNICATIONS-OFFICER-SHERIFF.odt', 'odt', 'municipal-web', 'camdencountymo.gov', 'Camden County communications officer posting ODT'),
        seed('https://www.kslegislature.gov/li_2014/b2013_14/measures/odt_view/je_20130402180450_618326.odt', 'odt', 'government-web', 'kslegislature.gov', 'Kansas HB 2069 ODT'),
        seed('https://ndlegis.gov/assembly/69-2025/regular/documents/25-0497-01002m.odt', 'odt', 'government-web', 'ndlegis.gov', 'North Dakota legislative measure 25-0497 ODT'),
        seed('https://www.kslegislature.gov/li_2012/b2011_12/measures/odt_view/je_20120222103832_756527.odt', 'odt', 'government-web', 'kslegislature.gov', 'Kansas legislative measure ODT'),
        ...gsjBitsSeedUrls(['0439', '0443', '0445', '0447', '0448', '0449', '0458', '0459', '0478', '0481', '0484', '0488', '0501', '0502', '0506', '0510', '0516', '0521', '0526', '0527']),
        seed('https://www.eia.gov/energyexplained/oil-and-petroleum-products/data/US-tight-oil-production.xlsx', 'xlsx', 'government-web', 'eia.gov', 'EIA tight oil production XLSX'),
        seed('https://edd.ca.gov/siteassets/files/newsroom/facts-and-stats/excel/call-center-data.xlsx', 'xlsx', 'government-web', 'edd.ca.gov', 'California EDD call center data XLSX'),
        seed('https://www.fdic.gov/quarterly-banking-profile/all-fdic-insured-institutions-charts-and-data.xlsx', 'xlsx', 'government-web', 'fdic.gov', 'FDIC institutions charts and data XLSX'),
        seed('https://www.dli.mn.gov/sites/default/files/xls/it-data-science.xlsx', 'xlsx', 'government-web', 'dli.mn.gov', 'Minnesota IT data science apprenticeship XLSX'),
        seed('https://coast.noaa.gov/data/digitalcoast/xls/ccap-changedata.xlsx', 'xlsx', 'government-web', 'coast.noaa.gov', 'NOAA C-CAP change data workbook XLSX'),
        seed('https://raw.githubusercontent.com/plotly/datasets/master/Blog/autobook.xlsx', 'xlsx', 'github-raw', 'plotly/datasets', 'Plotly autobook XLSX'),
        seed('https://raw.githubusercontent.com/plotly/datasets/master/data-matlab-excel-example.xlsx', 'xlsx', 'github-raw', 'plotly/datasets', 'Plotly MATLAB Excel example XLSX'),
        seed('https://raw.githubusercontent.com/plotly/datasets/master/supermarket_sales.xlsx', 'xlsx', 'github-raw', 'plotly/datasets', 'Plotly supermarket sales XLSX'),
        seed('https://raw.githubusercontent.com/eamena-project/eamena-arches-dev/main/dbs/database.eamena/data/reference_data/rm/hp/mds/mds-template-readonly.tsv', 'tsv', 'github-raw', 'eamena-project/eamena-arches-dev', 'EAMENA MDS template readonly TSV'),
        seed('https://raw.githubusercontent.com/vega/vega-datasets/master/data/unemployment.tsv', 'tsv', 'github-raw', 'vega/vega-datasets', 'Vega unemployment TSV dataset'),
        seed('https://raw.githubusercontent.com/plotly/datasets/master/Dash_Bio/Chromosomal/clustergram_mtcars.tsv', 'tsv', 'github-raw', 'plotly/datasets', 'Plotly Dash Bio mtcars TSV'),
        seed('https://raw.githubusercontent.com/plotly/datasets/master/global_super_store_orders.tsv', 'tsv', 'github-raw', 'plotly/datasets', 'Plotly global super store orders TSV'),
        ...galaxyTsvSeedUrls([
            ['topics/contributing/tutorials/meta-analysis-data/sc-roles.tsv', 'Galaxy training single-cell roles TSV'],
            ['topics/contributing/tutorials/meta-analysis-data/sc.tsv', 'Galaxy training single-cell TSV'],
            ['topics/contributing/tutorials/meta-analysis-data/single-cell-over-time.tsv', 'Galaxy training single cell over time TSV'],
            ['topics/galaxy-interface/tutorials/upload-rules-advanced/PRJNA355367.tsv', 'Galaxy training PRJNA355367 TSV'],
        ]),
    ];
}

function seed(string $url, string $format, string $sourceKind, string $origin, string $title): array
{
    return compact('url', 'format') + [
        'source_kind' => $sourceKind,
        'origin' => $origin,
        'title' => $title,
        'discovery_meta' => '{}',
    ];
}

function crossrefBibliographySeedUrls(array $items): array
{
    $seeds = [];
    foreach ($items as [$doi, $label]) {
        $seeds[] = seed("https://api.crossref.org/works/{$doi}/transform/application/x-bibtex", 'bibtex', 'crossref-api', 'crossref.org', "Crossref BibTeX for {$label}");
        $seeds[] = seed("https://api.crossref.org/works/{$doi}/transform/application/x-research-info-systems", 'ris', 'crossref-api', 'crossref.org', "Crossref RIS for {$label}");
        $seeds[] = seed("https://api.crossref.org/works/{$doi}/transform/application/vnd.citationstyles.csl+json", 'csljson', 'crossref-api', 'crossref.org', "Crossref CSL JSON for {$label}");
    }

    return $seeds;
}

function gsjBitsSeedUrls(array $ids): array
{
    $seeds = [];
    foreach ($ids as $id) {
        $seeds[] = seed("https://gbank.gsj.jp/ld/zfk/xmldata/{$id}_BITS.xml", 'bits', 'government-web', 'gbank.gsj.jp', "GSJ geological map BITS XML {$id}");
    }

    return $seeds;
}

function fletcherMmdSiteSeedUrls(array $slugs): array
{
    $seeds = [];
    foreach ($slugs as $slug) {
        $label = str_replace('-', ' ', (string) $slug);
        $seeds[] = seed("https://fletcherpenney.net/multimarkdown/{$slug}/index.txt", 'markdown_mmd', 'author-web', 'fletcherpenney.net', "MultiMarkdown {$label} source page");
    }

    return $seeds;
}

function pythonMarkdownCommonMarkXSeedUrls(array $slugs): array
{
    $seeds = [];
    foreach ($slugs as $slug) {
        $label = str_replace('_', ' ', (string) $slug);
        $seeds[] = seed("https://raw.githubusercontent.com/Python-Markdown/markdown/master/docs/extensions/{$slug}.md", 'commonmark_x', 'github-raw', 'Python-Markdown/markdown', "Python Markdown {$label} extension docs");
    }

    return $seeds;
}

function phpFigMarkdownExtraSeedUrls(array $names): array
{
    $seeds = [];
    foreach ($names as $name) {
        $seeds[] = seed("https://raw.githubusercontent.com/php-fig/fig-standards/master/accepted/{$name}.md", 'markdown_phpextra', 'github-raw', 'php-fig/fig-standards', str_replace('-', ' ', "{$name} standard"));
    }

    return $seeds;
}

function pythonRstSeedUrls(array $items): array
{
    $seeds = [];
    foreach ($items as [$path, $title]) {
        $seeds[] = seed("https://raw.githubusercontent.com/python/cpython/main/{$path}", 'rst', 'github-raw', 'python/cpython', $title);
    }

    return $seeds;
}

function elifeJatsSeedUrls(array $ids): array
{
    $seeds = [];
    foreach ($ids as $id) {
        $seeds[] = seed("https://elifesciences.org/articles/{$id}.xml", 'jats', 'journal-web', 'elifesciences.org', "eLife article {$id} JATS XML");
    }

    return $seeds;
}

function arxivPdfSeedUrls(array $items): array
{
    $seeds = [];
    foreach ($items as [$id, $title]) {
        $seeds[] = seed("https://arxiv.org/pdf/{$id}.pdf", 'pdf', 'preprint-web', 'arxiv.org', $title);
    }

    return $seeds;
}

function docbookParamSeedUrls(array $names): array
{
    $seeds = [];
    foreach ($names as $name) {
        $seeds[] = seed("https://raw.githubusercontent.com/docbook/xslt10-stylesheets/master/xsl/params/{$name}.xml", 'docbook', 'github-raw', 'docbook/xslt10-stylesheets', "DocBook {$name} parameter XML");
    }

    return $seeds;
}

function galaxyTsvSeedUrls(array $items): array
{
    $seeds = [];
    foreach ($items as [$path, $title]) {
        $seeds[] = seed("https://raw.githubusercontent.com/galaxyproject/training-material/main/{$path}", 'tsv', 'github-raw', 'galaxyproject/training-material', $title);
    }

    return $seeds;
}

/**
 * @return list<array{repo:string, ref:string, formats:list<string>}>
 */
function githubDiscoveryRepos(): array
{
    return [
        ['repo' => 'kubernetes/website', 'ref' => 'main', 'formats' => ['gfm', 'markdown', 'html']],
        ['repo' => 'nodejs/node', 'ref' => 'main', 'formats' => ['gfm', 'markdown']],
        ['repo' => 'django/django', 'ref' => 'main', 'formats' => ['rst', 'markdown']],
        ['repo' => 'rust-lang/rust', 'ref' => 'master', 'formats' => ['markdown']],
        ['repo' => 'WordPress/gutenberg', 'ref' => 'trunk', 'formats' => ['gfm', 'markdown']],
        ['repo' => 'plotly/datasets', 'ref' => 'master', 'formats' => ['csv', 'tsv', 'xlsx']],
        ['repo' => 'datasets/covid-19', 'ref' => 'main', 'formats' => ['csv']],
        ['repo' => 'mkerrisk/man-pages', 'ref' => 'master', 'formats' => ['man']],
        ['repo' => 'freebsd/freebsd-src', 'ref' => 'main', 'formats' => ['mdoc', 'man']],
        ['repo' => 'citation-style-language/styles', 'ref' => 'master', 'formats' => ['xml']],
        ['repo' => 'HL7/fhir', 'ref' => 'master', 'formats' => ['xml']],
        ['repo' => 'ipython/ipython-in-depth', 'ref' => 'master', 'formats' => ['ipynb']],
        ['repo' => 'jupyter/notebook', 'ref' => 'main', 'formats' => ['ipynb', 'markdown']],
        ['repo' => 'latex3/latex2e', 'ref' => 'develop', 'formats' => ['latex', 'bibtex', 'markdown']],
        ['repo' => 'plk/biblatex', 'ref' => 'dev', 'formats' => ['bibtex', 'biblatex', 'latex', 'markdown']],
        ['repo' => 'docbook/xslt10-stylesheets', 'ref' => 'master', 'formats' => ['docbook', 'xml']],
    ];
}

/**
 * @return list<array{repo:string, ref:string, rules:list<array{pattern:string, format:string}>}>
 */
function knownGitHubDiscoveryRepos(): array
{
    return [
        ['repo' => 'bitcoin/bips', 'ref' => 'master', 'rules' => [
            ['pattern' => '/^bip-\d+\.mediawiki$/', 'format' => 'mediawiki'],
        ]],
        ['repo' => 'freebsd/freebsd-src', 'ref' => 'main', 'rules' => [
            ['pattern' => '#(^|/)(bin|sbin|usr\.bin|usr\.sbin|share/man)/.+\.[1-9]$#', 'format' => 'mdoc'],
        ]],
        ['repo' => 'scripting/Scripting-News', 'ref' => 'master', 'rules' => [
            ['pattern' => '#^blog/opml/[0-9]{4}/[0-9]{2}\.opml$#', 'format' => 'opml'],
        ]],
        ['repo' => 'geometer/FBReaderJ', 'ref' => 'e83aec9f94084aa59d39e33876bdb6fdc275c95e', 'rules' => [
            ['pattern' => '#^obsolete/help/MiniHelp\.[a-z][a-z]\.fb2$#', 'format' => 'fb2'],
        ]],
        ['repo' => 'tsolucio/corebosdocs', 'ref' => '01ab857b87ffa5ae07b593e487af5a12347802f8', 'rules' => [
            ['pattern' => '#^pages/.+\.txt$#', 'format' => 'dokuwiki'],
        ]],
        ['repo' => 'wherecamppdx/wherecamppdx-dokuwiki', 'ref' => '38b728bca45ebcd80c6c7f8990bada2198d063c8', 'rules' => [
            ['pattern' => '#^data/pages/.+\.txt$#', 'format' => 'dokuwiki'],
        ]],
        ['repo' => 'merb/old-wiki', 'ref' => '22b634a78c7e015c607c9efe0003d63e17eaff6c', 'rules' => [
            ['pattern' => '#^pages/.+\.txt$#', 'format' => 'dokuwiki'],
        ]],
        ['repo' => 'pnp4nagios/docs', 'ref' => 'f95f287fd88250945c848d5c5337b430fd1f5a1e', 'rules' => [
            ['pattern' => '#^pages/.+\.txt$#', 'format' => 'dokuwiki'],
        ]],
        ['repo' => 'jchiquet/quarto-hceres', 'ref' => '55fdc1e6f75e710eb67bfb0650bf237ff62886ca', 'rules' => [
            ['pattern' => '#\.bib$#', 'format' => 'biblatex'],
        ]],
        ['repo' => 'jackwasey/icd', 'ref' => 'f200683642833d89d177ee53b880df1eab70d1cf', 'rules' => [
            ['pattern' => '#\.bib$#', 'format' => 'biblatex'],
        ]],
        ['repo' => 'gwoodwa1/network_rag_pipeline', 'ref' => '7f9de75055c9801b3fa188f9cc7075bb47ca104d', 'rules' => [
            ['pattern' => '#\.ast\.json$#', 'format' => 'json'],
        ]],
        ['repo' => 'b0mbix/markupit', 'ref' => 'e110aa5b95cf3263796adffe85a7fbf5eae4ad50', 'rules' => [
            ['pattern' => '#^misc/ast_analysis/.+\.json$#', 'format' => 'native'],
        ]],
    ];
}

function knownFormatForPath(array $repo, string $path): ?string
{
    foreach (($repo['rules'] ?? []) as $rule) {
        if (preg_match((string) $rule['pattern'], $path) === 1) {
            return (string) $rule['format'];
        }
    }

    return null;
}

/**
 * @return array<string, int>
 */
function corpusCandidateCounts(PDO $db): array
{
    $counts = [];
    foreach ($db->query("SELECT format, COUNT(*) AS count FROM candidates WHERE status!='failed' GROUP BY format")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $counts[(string) $row['format']] = (int) $row['count'];
    }

    return $counts;
}

/**
 * @return array<string, int>
 */
function corpusFormatTargets(PDO $db): array
{
    $targets = [];
    foreach ($db->query('SELECT format, target_count FROM formats')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $targets[(string) $row['format']] = (int) $row['target_count'];
    }

    return $targets;
}

function insertCandidate(PDO $db, array $seed): bool
{
    $stmt = $db->prepare('INSERT OR IGNORE INTO candidates(url,format,source_kind,origin,title,discovery_meta) VALUES(:url,:format,:source_kind,:origin,:title,:discovery_meta)');
    $stmt->execute([
        ':url' => (string) $seed['url'],
        ':format' => (string) $seed['format'],
        ':source_kind' => (string) $seed['source_kind'],
        ':origin' => (string) $seed['origin'],
        ':title' => (string) ($seed['title'] ?? ''),
        ':discovery_meta' => (string) ($seed['discovery_meta'] ?? '{}'),
    ]);

    return $stmt->rowCount() > 0;
}

function formatExists(PDO $db, string $format): bool
{
    $stmt = $db->prepare('SELECT 1 FROM formats WHERE format=:format');
    $stmt->execute([':format' => $format]);

    return $stmt->fetchColumn() !== false;
}

function githubTree(string $repo, string $ref): array
{
    $url = "https://api.github.com/repos/{$repo}/git/trees/{$ref}?recursive=1";
    try {
        $fetch = fetchUrl($url, ['Accept: application/vnd.github+json']);
        $json = json_decode($fetch['bytes'], true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($json) || !is_array($json['tree'] ?? null)) {
            throw new RuntimeException("GitHub tree response for {$repo}@{$ref} did not contain a tree");
        }

        return $json['tree'];
    } catch (Throwable $apiError) {
        if (getenv('DOCUMENT_CORPUS_GIT_TREE_FALLBACK') !== '1') {
            throw $apiError;
        }
        try {
            return githubTreeViaGit($repo, $ref);
        } catch (Throwable $gitError) {
            throw new RuntimeException(
                "Unable to discover GitHub tree for {$repo}@{$ref}: API failed with "
                . $apiError->getMessage() . '; git fallback failed with ' . $gitError->getMessage(),
                0,
                $gitError
            );
        }
    }
}

function githubTreeViaGit(string $repo, string $ref): array
{
    if (!executableExists('git')) {
        throw new RuntimeException('git executable is not available');
    }
    $temp = tempnam(sys_get_temp_dir(), 'document-corpus-git-');
    if (!is_string($temp)) {
        throw new RuntimeException('Unable to allocate git temp path');
    }
    @unlink($temp);
    ensureDirectory($temp);
    try {
        $remote = "https://github.com/{$repo}.git";
        foreach ([
            ['git', '-C', $temp, 'init', '--quiet'],
            ['git', '-C', $temp, 'remote', 'add', 'origin', $remote],
            ['git', '-C', $temp, 'fetch', '--quiet', '--depth=1', '--filter=blob:none', 'origin', $ref],
        ] as $command) {
            $result = runCommand($command, 60);
            if ($result['exitCode'] !== 0) {
                throw new RuntimeException(trim($result['stderr']) ?: 'git command failed');
            }
        }
        $result = runCommand(['git', '-C', $temp, 'ls-tree', '-r', '-l', '--full-tree', 'FETCH_HEAD'], 60);
        if ($result['exitCode'] !== 0) {
            throw new RuntimeException(trim($result['stderr']) ?: 'git ls-tree failed');
        }

        $tree = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($result['stdout'])) ?: [] as $line) {
            if ($line === '' || !str_contains($line, "\t")) {
                continue;
            }
            [$meta, $path] = explode("\t", $line, 2);
            $fields = preg_split('/\s+/', trim($meta));
            if (!is_array($fields) || count($fields) < 4) {
                continue;
            }
            [$mode, $type, $object, $size] = array_pad($fields, 4, '');
            if ($type !== 'blob' || $path === '') {
                continue;
            }
            $tree[] = [
                'path' => $path,
                'mode' => $mode,
                'type' => $type,
                'sha' => $object,
                'size' => ctype_digit($size) ? (int) $size : 0,
            ];
        }

        return $tree;
    } finally {
        removeDirectory($temp);
    }
}

function pendingCandidates(PDO $db, int $limit, string $format): array
{
    $sql = "SELECT * FROM candidates WHERE status='queued'";
    $params = [];
    if ($format !== '') {
        $sql .= ' AND format=:format';
        $params[':format'] = $format;
    }
    $sql .= ' ORDER BY format, id LIMIT :limit';
    $stmt = $db->prepare($sql);
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function pendingDocuments(PDO $db, int $limit, string $format): array
{
    $sql = "SELECT d.* FROM documents d WHERE NOT EXISTS (SELECT 1 FROM renders r WHERE r.document_id=d.id AND r.renderer='php-wordpress')";
    if ($format !== '') {
        $sql .= ' AND d.format=:format';
    }
    $sql .= ' ORDER BY d.format, d.id LIMIT :limit';
    $stmt = $db->prepare($sql);
    if ($format !== '') {
        $stmt->bindValue(':format', $format);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function documentById(PDO $db, int $id): ?array
{
    $stmt = $db->prepare('SELECT * FROM documents WHERE id=:id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function markCandidate(PDO $db, int $id, string $status, ?string $error): void
{
    $stmt = $db->prepare('UPDATE candidates SET status=:status,error=:error WHERE id=:id');
    $stmt->execute([':status' => $status, ':error' => $error, ':id' => $id]);
}

function upsertDocument(PDO $db, array $candidate, string $path, string $sha, int $bytes, array $fetch): void
{
    $stmt = $db->prepare('INSERT INTO documents(candidate_id,url,format,source_kind,origin,title,local_path,sha256,bytes,etag,last_modified) VALUES(:candidate_id,:url,:format,:source_kind,:origin,:title,:local_path,:sha256,:bytes,:etag,:last_modified) ON CONFLICT(url) DO UPDATE SET local_path=excluded.local_path, sha256=excluded.sha256, bytes=excluded.bytes, etag=excluded.etag, last_modified=excluded.last_modified, fetched_at=CURRENT_TIMESTAMP');
    $stmt->execute([
        ':candidate_id' => (int) $candidate['id'],
        ':url' => (string) $candidate['url'],
        ':format' => (string) $candidate['format'],
        ':source_kind' => (string) $candidate['source_kind'],
        ':origin' => (string) $candidate['origin'],
        ':title' => (string) $candidate['title'],
        ':local_path' => $path,
        ':sha256' => $sha,
        ':bytes' => $bytes,
        ':etag' => $fetch['etag'] ?? null,
        ':last_modified' => $fetch['lastModified'] ?? null,
    ]);
}

function renderDocument(string $repoRoot, string $corpusRoot, PDO $db, array $document): void
{
    $inputPath = $corpusRoot . '/' . $document['local_path'];
    $bytes = (string) file_get_contents($inputPath);
    $base = 'renders/' . (int) $document['id'];
    ensureDirectory($corpusRoot . '/' . $base);

    $started = microtime(true);
    try {
        $options = converterOptions((string) $document['format']);
        $ast = PandocConverter::read($bytes, (string) $document['format'], $options['readerOptions']);
        $wordpress = PandocConverter::write($ast, 'wordpress', $options['writerOptions']);
        $path = $base . '/php-wordpress.html';
        writeBytes($corpusRoot . '/' . $path, $wordpress);
        recordRender($db, (int) $document['id'], 'php-wordpress', 'ok', $path, microtime(true) - $started, 0, null, htmlMetrics($wordpress));
    } catch (Throwable $error) {
        recordRender($db, (int) $document['id'], 'php-wordpress', 'failed', null, microtime(true) - $started, null, $error->getMessage(), []);
    }

    $reference = referenceHtml($inputPath, (string) $document['format']);
    if ($reference['status'] === 'ok') {
        $path = $base . '/reference.html';
        writeBytes($corpusRoot . '/' . $path, $reference['html']);
        recordRender($db, (int) $document['id'], (string) $reference['renderer'], 'ok', $path, (float) $reference['seconds'], (int) $reference['exitCode'], null, htmlMetrics($reference['html']));
        compareRenders($db, (int) $document['id'], $corpusRoot, (string) $reference['renderer'], 'php-wordpress');
    } else {
        recordRender($db, (int) $document['id'], (string) $reference['renderer'], 'failed', null, (float) $reference['seconds'], (int) $reference['exitCode'], (string) $reference['error'], []);
    }
}

function converterOptions(string $format): array
{
    $readerOptions = [];
    $canonical = PandocConverter::canonicalInputFormat($format);
    if ($canonical === 'pdf') {
        $readerOptions['maxTextBytes'] = 80000;
        $readerOptions['pdfGeometryTables'] = true;
        $readerOptions['pdfRepairProseText'] = true;
    }
    if ($canonical === 'csv' || $canonical === 'tsv') {
        $readerOptions['allowBlankRecords'] = true;
    }

    return ['readerOptions' => $readerOptions, 'writerOptions' => ['writerHTMLMathMethod' => 'mathml']];
}

function referenceHtml(string $path, string $format): array
{
    $pandocFormat = pandocFormat($format);
    if ($pandocFormat !== null) {
        $started = microtime(true);
        $result = runCommand(['pandoc', '-f', $pandocFormat, '-t', 'html', '--mathml', $path], 45);

        return [
            'renderer' => 'pandoc-html',
            'status' => $result['exitCode'] === 0 ? 'ok' : 'failed',
            'html' => $result['stdout'],
            'seconds' => microtime(true) - $started,
            'exitCode' => $result['exitCode'],
            'error' => trim($result['stderr']),
        ];
    }
    if ($format === 'pdf' && executableExists('pdftotext')) {
        $started = microtime(true);
        $result = runCommand(['pdftotext', '-layout', $path, '-'], 45);

        return [
            'renderer' => 'pdftotext-layout',
            'status' => $result['exitCode'] === 0 ? 'ok' : 'failed',
            'html' => '<pre>' . htmlspecialchars($result['stdout'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>',
            'seconds' => microtime(true) - $started,
            'exitCode' => $result['exitCode'],
            'error' => trim($result['stderr']),
        ];
    }
    if (in_array($format, ['doc', 'docx', 'odt', 'rtf'], true) && executableExists('textutil')) {
        $started = microtime(true);
        $tmp = tempnam(sys_get_temp_dir(), 'document-corpus-textutil-');
        if (!is_string($tmp)) {
            throw new RuntimeException('Unable to allocate textutil output path');
        }
        @unlink($tmp);
        $out = $tmp . '.html';
        $result = runCommand(['textutil', '-convert', 'html', '-output', $out, $path], 45);
        $html = is_file($out) ? (string) file_get_contents($out) : '';
        @unlink($out);

        return [
            'renderer' => 'textutil-html',
            'status' => $result['exitCode'] === 0 && $html !== '' ? 'ok' : 'failed',
            'html' => $html,
            'seconds' => microtime(true) - $started,
            'exitCode' => $result['exitCode'],
            'error' => trim($result['stderr']),
        ];
    }

    return ['renderer' => 'none', 'status' => 'failed', 'html' => '', 'seconds' => 0.0, 'exitCode' => 127, 'error' => 'No reference renderer configured for format ' . $format];
}

function pandocFormat(string $format): ?string
{
    if (in_array($format, ['pdf', 'doc'], true)) {
        return null;
    }
    $canonical = PandocConverter::canonicalInputFormat($format);
    if ($canonical === 'markdown_github') {
        return 'gfm';
    }
    if ($canonical === 'bits') {
        return 'jats';
    }
    if ($canonical === 'xml') {
        return 'html';
    }

    return $canonical;
}

function recordRenderFailure(PDO $db, int $documentId, string $renderer, string $error): void
{
    recordRender($db, $documentId, $renderer, 'failed', null, 0, null, $error, []);
}

function recordRender(PDO $db, int $documentId, string $renderer, string $status, ?string $path, float $seconds, ?int $exitCode, ?string $error, array $metrics): void
{
    $stmt = $db->prepare('INSERT INTO renders(document_id,renderer,status,output_path,seconds,exit_code,error,metrics_json) VALUES(:document_id,:renderer,:status,:output_path,:seconds,:exit_code,:error,:metrics_json) ON CONFLICT(document_id,renderer) DO UPDATE SET status=excluded.status, output_path=excluded.output_path, seconds=excluded.seconds, exit_code=excluded.exit_code, error=excluded.error, metrics_json=excluded.metrics_json, rendered_at=CURRENT_TIMESTAMP');
    $stmt->execute([
        ':document_id' => $documentId,
        ':renderer' => $renderer,
        ':status' => $status,
        ':output_path' => $path,
        ':seconds' => $seconds,
        ':exit_code' => $exitCode,
        ':error' => $error,
        ':metrics_json' => json_encode($metrics, JSON_THROW_ON_ERROR),
    ]);
}

function compareRenders(PDO $db, int $documentId, string $corpusRoot, string $referenceRenderer, string $wordpressRenderer): void
{
    $reference = renderRow($db, $documentId, $referenceRenderer);
    $wordpress = renderRow($db, $documentId, $wordpressRenderer);
    if ($reference === null || $wordpress === null || $reference['status'] !== 'ok' || $wordpress['status'] !== 'ok') {
        return;
    }
    $referenceHtml = (string) file_get_contents($corpusRoot . '/' . $reference['output_path']);
    $wordpressHtml = (string) file_get_contents($corpusRoot . '/' . $wordpress['output_path']);
    $metrics = comparisonMetrics($referenceHtml, $wordpressHtml);
    $status = ($metrics['textJaccard'] >= 0.82 && $metrics['referenceTextCoverage'] >= 0.80) ? 'review-ok' : 'needs-review';
    $stmt = $db->prepare('INSERT INTO comparisons(document_id,reference_renderer,wordpress_renderer,status,metrics_json,notes) VALUES(:document_id,:reference_renderer,:wordpress_renderer,:status,:metrics_json,:notes) ON CONFLICT(document_id,reference_renderer,wordpress_renderer) DO UPDATE SET status=excluded.status, metrics_json=excluded.metrics_json, notes=excluded.notes, compared_at=CURRENT_TIMESTAMP');
    $stmt->execute([
        ':document_id' => $documentId,
        ':reference_renderer' => $referenceRenderer,
        ':wordpress_renderer' => $wordpressRenderer,
        ':status' => $status,
        ':metrics_json' => json_encode($metrics, JSON_THROW_ON_ERROR),
        ':notes' => $status === 'needs-review' ? 'Low normalized text similarity or coverage; inspect rendered artifacts.' : '',
    ]);
}

function renderRow(PDO $db, int $documentId, string $renderer): ?array
{
    $stmt = $db->prepare('SELECT * FROM renders WHERE document_id=:document_id AND renderer=:renderer');
    $stmt->execute([':document_id' => $documentId, ':renderer' => $renderer]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function htmlMetrics(string $html): array
{
    $text = normalizedText($html);

    return [
        'bytes' => strlen($html),
        'textBytes' => strlen($text),
        'words' => count(tokens($text)),
        'tables' => preg_match_all('/<table\b/i', $html),
        'images' => preg_match_all('/<img\b/i', $html),
        'links' => preg_match_all('/<a\b/i', $html),
        'headings' => preg_match_all('/<h[1-6]\b/i', $html),
        'lists' => preg_match_all('/<[ou]l\b/i', $html),
    ];
}

function comparisonMetrics(string $referenceHtml, string $wordpressHtml): array
{
    $referenceTokens = array_count_values(tokens(normalizedText($referenceHtml)));
    $wordpressTokens = array_count_values(tokens(normalizedText($wordpressHtml)));
    $intersection = 0;
    $union = $referenceTokens;
    foreach ($wordpressTokens as $token => $count) {
        $intersection += min($referenceTokens[$token] ?? 0, $count);
        $union[$token] = max($union[$token] ?? 0, $count);
    }
    $unionCount = array_sum($union);
    $referenceCount = array_sum($referenceTokens);

    return [
        'textJaccard' => $unionCount === 0 ? 1.0 : round($intersection / $unionCount, 4),
        'referenceTextCoverage' => $referenceCount === 0 ? 1.0 : round($intersection / $referenceCount, 4),
        'reference' => htmlMetrics($referenceHtml),
        'wordpress' => htmlMetrics($wordpressHtml),
    ];
}

function normalizedText(string $html): string
{
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

    return trim(strtolower($text));
}

function tokens(string $text): array
{
    preg_match_all('/[\pL\pN][\pL\pN._-]*/u', $text, $matches);

    return array_values(array_filter($matches[0] ?? [], static fn (string $token): bool => mb_strlen($token) >= 2));
}

function corpusReport(PDO $db): array
{
    return [
        'generatedAt' => gmdate('c'),
        'summary' => $db->query('SELECT (SELECT COUNT(*) FROM formats) AS formats, (SELECT COUNT(*) FROM candidates) AS candidates, (SELECT COUNT(*) FROM documents) AS documents, (SELECT COUNT(*) FROM renders WHERE status="ok") AS ok_renders, (SELECT COUNT(*) FROM comparisons WHERE status="needs-review") AS needs_review')->fetch(PDO::FETCH_ASSOC),
        'byFormat' => $db->query('SELECT f.format, f.target_count, COUNT(DISTINCT d.id) AS documents, COUNT(DISTINCT c.id) AS candidates, COUNT(DISTINCT CASE WHEN cmp.status="needs-review" THEN cmp.id END) AS needs_review FROM formats f LEFT JOIN candidates c ON c.format=f.format LEFT JOIN documents d ON d.format=f.format LEFT JOIN comparisons cmp ON cmp.document_id=d.id GROUP BY f.format ORDER BY f.format')->fetchAll(PDO::FETCH_ASSOC),
        'needsReview' => $db->query('SELECT d.id, d.format, d.url, d.local_path, cmp.metrics_json, cmp.notes FROM comparisons cmp JOIN documents d ON d.id=cmp.document_id WHERE cmp.status="needs-review" ORDER BY d.format, d.id LIMIT 200')->fetchAll(PDO::FETCH_ASSOC),
        'failures' => $db->query('SELECT d.id, d.format, d.url, r.renderer, r.error FROM renders r JOIN documents d ON d.id=r.document_id WHERE r.status!="ok" ORDER BY d.format, d.id LIMIT 200')->fetchAll(PDO::FETCH_ASSOC),
    ];
}

function renderReportHtml(array $report): string
{
    $rows = '';
    foreach ($report['byFormat'] as $row) {
        $rows .= '<tr><td>' . e((string) $row['format']) . '</td><td>' . (int) $row['target_count'] . '</td><td>' . (int) $row['candidates'] . '</td><td>' . (int) $row['documents'] . '</td><td>' . (int) $row['needs_review'] . '</td></tr>';
    }
    $review = '';
    foreach ($report['needsReview'] as $row) {
        $metrics = json_decode((string) $row['metrics_json'], true);
        $review .= '<li><strong>' . e((string) $row['format']) . ' #' . (int) $row['id'] . '</strong> '
            . '<a href="' . e((string) $row['url']) . '">' . e((string) $row['url']) . '</a> '
            . 'jaccard=' . e((string) ($metrics['textJaccard'] ?? '')) . ' coverage=' . e((string) ($metrics['referenceTextCoverage'] ?? '')) . '</li>';
    }

    return '<!doctype html><meta charset="utf-8"><title>Document corpus report</title><style>body{font:14px system-ui,sans-serif;margin:24px;color:#17202a}table{border-collapse:collapse;width:100%}td,th{border:1px solid #d8dee4;padding:6px;text-align:left}th{background:#f6f8fa}</style><h1>Document corpus report</h1><pre>' . e(json_encode($report['summary'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre><h2>By format</h2><table><tr><th>Format</th><th>Target</th><th>Candidates</th><th>Documents</th><th>Needs review</th></tr>' . $rows . '</table><h2>Needs review</h2><ul>' . $review . '</ul>';
}

function e(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fetchUrl(string $url, array $headers = [], ?int $maxBytes = null): array
{
    $headersPath = tempnam(sys_get_temp_dir(), 'document-corpus-headers-');
    if (!is_string($headersPath)) {
        throw new RuntimeException('Unable to allocate header temp file');
    }
    $command = [
        'curl',
        '--location',
        '--fail',
        '--silent',
        '--show-error',
        '--max-time',
        '60',
        '--dump-header',
        $headersPath,
        '--user-agent',
        'port-libs-document-corpus/0.1',
    ];
    if ($maxBytes !== null) {
        $command[] = '--max-filesize';
        $command[] = (string) $maxBytes;
    }
    foreach ($headers as $header) {
        $command[] = '--header';
        $command[] = $header;
    }
    $command[] = $url;
    $result = runCommand($command, 70);
    $rawHeaders = is_file($headersPath) ? (string) file_get_contents($headersPath) : '';
    @unlink($headersPath);
    if ($result['exitCode'] !== 0) {
        throw new RuntimeException(trim($result['stderr']) ?: 'curl failed');
    }

    return ['bytes' => $result['stdout'], 'etag' => responseHeader($rawHeaders, 'etag'), 'lastModified' => responseHeader($rawHeaders, 'last-modified')];
}

function responseHeader(string $headers, string $name): ?string
{
    $name = strtolower($name);
    $value = null;
    foreach (preg_split('/\r\n|\r|\n/', $headers) ?: [] as $line) {
        [$headerName, $headerValue] = array_pad(explode(':', $line, 2), 2, '');
        if (strtolower(trim($headerName)) === $name) {
            $value = trim($headerValue);
        }
    }

    return $value === '' ? null : $value;
}

function inferFormatFromPath(string $path): ?string
{
    $lower = strtolower($path);
    foreach (extensionFormatMap() as $pattern => $format) {
        if ($pattern[0] === '/' && preg_match($pattern, $lower) === 1) {
            return $format;
        }
        if ($pattern[0] !== '/' && str_ends_with($lower, $pattern)) {
            return $format;
        }
    }

    return null;
}

function extensionForFormat(string $format, string $url = ''): string
{
    $path = parse_url($url, PHP_URL_PATH);
    $extension = is_string($path) ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : '';
    if ($extension !== '') {
        return preg_replace('/[^a-z0-9]+/', '', $extension) ?: $extension;
    }

    return extensionsForFormat($format)[0] ?? $format;
}

function extensionsForFormat(string $format): array
{
    $map = [
        'bibtex' => ['bib'], 'biblatex' => ['bib'], 'bits' => ['xml'], 'commonmark' => ['md'], 'commonmark_x' => ['md'], 'csljson' => ['json'],
        'csv' => ['csv'], 'tsv' => ['tsv'], 'docbook' => ['xml', 'dbk'], 'docx' => ['docx'], 'doc' => ['doc'], 'dokuwiki' => ['dokuwiki', 'wiki'],
        'endnotexml' => ['xml'], 'epub' => ['epub'], 'fb2' => ['fb2'], 'gfm' => ['md'], 'html' => ['html', 'htm'], 'ipynb' => ['ipynb'],
        'jats' => ['xml'], 'jira' => ['jira'], 'json' => ['json'], 'latex' => ['tex'], 'man' => ['1', '2', '3', '4', '5', '6', '7', '8', '9'],
        'markdown' => ['md', 'markdown'], 'markdown_github' => ['md'], 'markdown_mmd' => ['md'], 'markdown_phpextra' => ['md'], 'markdown_strict' => ['md'],
        'mdoc' => ['1', '2', '3', '4', '5', '6', '7', '8', '9'], 'mediawiki' => ['wiki', 'mediawiki'], 'native' => ['native'],
        'odt' => ['odt'], 'opml' => ['opml'], 'pdf' => ['pdf'], 'pptx' => ['pptx'], 'ris' => ['ris'], 'rst' => ['rst'], 'rtf' => ['rtf'],
        'xlsx' => ['xlsx'], 'xml' => ['xml'],
    ];

    return $map[$format] ?? [$format];
}

function extensionFormatMap(): array
{
    return [
        '.biblatex' => 'biblatex', '.bib' => 'bibtex', '.csv' => 'csv', '.tsv' => 'tsv', '.docbook' => 'docbook', '.dbk' => 'docbook',
        '.docx' => 'docx', '.doc' => 'doc', '.dokuwiki' => 'dokuwiki', '.epub' => 'epub', '.fb2' => 'fb2', '.gfm' => 'gfm',
        '.html' => 'html', '.htm' => 'html', '.ipynb' => 'ipynb', '.jira' => 'jira', '.json' => 'json', '.tex' => 'latex',
        '/\.[1-9][a-z]*$/' => 'man', '.mediawiki' => 'mediawiki', '.wiki' => 'mediawiki', '.native' => 'native', '.odt' => 'odt',
        '.opml' => 'opml', '.pdf' => 'pdf', '.pptx' => 'pptx', '.ris' => 'ris', '.rst' => 'rst', '.rtf' => 'rtf', '.xlsx' => 'xlsx',
        '.xml' => 'xml', '.markdown' => 'markdown', '.md' => 'markdown',
    ];
}

function shouldSkipOrganicPath(string $path): bool
{
    $lower = strtolower($path);
    if (preg_match('#(^|/)(\.(devcontainer|github|idea|vscode)|test|tests|testdata|test_data|fixture|fixtures|spec/fixtures|vendor|node_modules|third_party|third-party)(/|$)#', $lower) === 1) {
        return true;
    }
    if (preg_match('#(^|/)(license|copying|changelog|authors|contributors)(\.[a-z0-9]+)?$#', $lower) === 1) {
        return true;
    }

    return false;
}

function validateCandidateBytes(string $format, string $url, string $bytes): void
{
    $path = parse_url($url, PHP_URL_PATH);
    if (is_string($path) && shouldSkipOrganicPath($path)) {
        throw new RuntimeException('organic path exclusion matched after download');
    }

    $trimmed = ltrim(substr($bytes, 0, 4096));
    if (in_array($format, xmlLikeFormats(), true) && !str_starts_with($trimmed, '<')) {
        throw new RuntimeException("{$format} candidate is not XML-like content");
    }
    if ($format === 'json') {
        $first = $trimmed[0] ?? '';
        if ($first !== '{' && $first !== '[') {
            throw new RuntimeException('json candidate is not JSON content');
        }
        if (!str_contains(substr($bytes, 0, 20000), '"pandoc-api-version"')) {
            throw new RuntimeException('json input must be a Pandoc JSON document');
        }
    }
    if ($format === 'ipynb' && !str_contains(substr($bytes, 0, 20000), '"cells"')) {
        throw new RuntimeException('ipynb candidate does not look like a notebook');
    }
}

function xmlLikeFormats(): array
{
    return ['bits', 'docbook', 'endnotexml', 'fb2', 'jats', 'opml', 'xml'];
}

function maxBytesForFormat(string $format): int
{
    return in_array($format, ['pdf', 'doc', 'docx', 'pptx', 'xlsx', 'odt', 'epub'], true) ? 25000000 : 6000000;
}

function parseArgs(array $raw): array
{
    $args = [];
    foreach ($raw as $arg) {
        if (!str_starts_with($arg, '--')) {
            continue;
        }
        [$key, $value] = array_pad(explode('=', substr($arg, 2), 2), 2, '1');
        $args[$key] = $value;
    }

    return $args;
}

function positiveInt(mixed $value, int $default): int
{
    if ($value === null || $value === '') {
        return $default;
    }
    if (!ctype_digit((string) $value) || (int) $value < 1) {
        throw new InvalidArgumentException('Expected a positive integer limit');
    }

    return (int) $value;
}

function normalizePath(string $repoRoot, string $path): string
{
    if ($path === '') {
        throw new InvalidArgumentException('Path must not be empty');
    }

    return str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : $repoRoot . DIRECTORY_SEPARATOR . $path;
}

function ensureDirectory(string $directory): void
{
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException("Unable to create directory '{$directory}'.");
    }
}

function writeBytes(string $path, string $bytes): void
{
    ensureDirectory(dirname($path));
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException("Unable to write '{$path}'.");
    }
}

function removeDirectory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            @rmdir($entry->getPathname());
        } else {
            @unlink($entry->getPathname());
        }
    }
    @rmdir($directory);
}

function runCommand(array $command, int $timeoutSeconds = 60): array
{
    $commandLine = implode(' ', array_map('escapeshellarg', $command));
    $pipes = [];
    $process = proc_open($commandLine, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        return ['stdout' => '', 'stderr' => 'proc_open failed', 'exitCode' => 127];
    }
    $started = time();
    $stdout = '';
    $stderr = '';
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    for (;;) {
        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);
        $status = proc_get_status($process);
        if (!$status['running']) {
            break;
        }
        if (time() - $started > $timeoutSeconds) {
            proc_terminate($process);
            $stderr .= "\ncommand timed out";
            break;
        }
        usleep(50000);
    }
    $stdout .= (string) stream_get_contents($pipes[1]);
    $stderr .= (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return ['stdout' => $stdout, 'stderr' => $stderr, 'exitCode' => $exitCode];
}

function executableExists(string $name): bool
{
    $result = runCommand(['sh', '-lc', 'command -v ' . escapeshellarg($name)]);

    return $result['exitCode'] === 0 && trim($result['stdout']) !== '';
}
