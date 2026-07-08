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
        ...seedUrlList('epub', 'github-media', [
            ['https://media.githubusercontent.com/media/SCons/docs/78d4424b197c0aa3c83e2191655712444113a0d0/4.8.0/EPUB/scons-man.epub', 'SCons/docs', 'SCons manual EPUB'],
            ['https://media.githubusercontent.com/media/SCons/docs/78d4424b197c0aa3c83e2191655712444113a0d0/4.5.2/EPUB/scons-man.epub', 'SCons/docs', 'SCons manual 4.5.2 EPUB'],
            ['https://media.githubusercontent.com/media/giellalt/corpus-fin-orig/0942c122c175629b22dc00836948efd41fa76c15/ficti/fiction/pg24687.epub', 'giellalt/corpus-fin-orig', 'Finnish Project Gutenberg EPUB 24687'],
            ['https://media.githubusercontent.com/media/giellalt/corpus-fin-orig/0942c122c175629b22dc00836948efd41fa76c15/ficti/fiction/pg13662.epub', 'giellalt/corpus-fin-orig', 'Finnish Project Gutenberg EPUB 13662'],
            ['https://media.githubusercontent.com/media/giellalt/corpus-fin-orig/0942c122c175629b22dc00836948efd41fa76c15/ficti/fiction/pg59562.epub', 'giellalt/corpus-fin-orig', 'Finnish Project Gutenberg EPUB 59562'],
            ['https://media.githubusercontent.com/media/giellalt/corpus-fin-orig/0942c122c175629b22dc00836948efd41fa76c15/ficti/fiction/pg51618.epub', 'giellalt/corpus-fin-orig', 'Finnish Project Gutenberg EPUB 51618'],
            ['https://media.githubusercontent.com/media/giellalt/corpus-fin-orig/0942c122c175629b22dc00836948efd41fa76c15/ficti/fiction/pg53484.epub', 'giellalt/corpus-fin-orig', 'Finnish Project Gutenberg EPUB 53484'],
            ['https://media.githubusercontent.com/media/giellalt/corpus-fin-orig/0942c122c175629b22dc00836948efd41fa76c15/ficti/fiction/pg18144.epub', 'giellalt/corpus-fin-orig', 'Finnish Project Gutenberg EPUB 18144'],
            ['https://media.githubusercontent.com/media/giellalt/corpus-fin-orig/0942c122c175629b22dc00836948efd41fa76c15/ficti/fiction/pg10492.epub', 'giellalt/corpus-fin-orig', 'Finnish Project Gutenberg EPUB 10492'],
            ['https://media.githubusercontent.com/media/giellalt/corpus-fin-orig/0942c122c175629b22dc00836948efd41fa76c15/ficti/fiction/pg55991.epub', 'giellalt/corpus-fin-orig', 'Finnish Project Gutenberg EPUB 55991'],
            ['https://media.githubusercontent.com/media/giellalt/corpus-fin-orig/0942c122c175629b22dc00836948efd41fa76c15/ficti/fiction/pg52035.epub', 'giellalt/corpus-fin-orig', 'Finnish Project Gutenberg EPUB 52035'],
            ['https://media.githubusercontent.com/media/giellalt/corpus-fin-orig/0942c122c175629b22dc00836948efd41fa76c15/ficti/fiction/pg64973.epub', 'giellalt/corpus-fin-orig', 'Finnish Project Gutenberg EPUB 64973'],
        ]),
        seed('https://raw.githubusercontent.com/plotly/datasets/master/2014_usa_states.csv', 'csv', 'github-raw', 'plotly/datasets', 'Plotly US states CSV'),
        seed('https://raw.githubusercontent.com/datasets/covid-19/main/data/countries-aggregated.csv', 'csv', 'github-raw', 'datasets/covid-19', 'COVID-19 countries CSV'),
        seed('https://raw.githubusercontent.com/mkerrisk/man-pages/master/man7/bootparam.7', 'man', 'github-raw', 'mkerrisk/man-pages', 'bootparam(7) man page'),
        seed('https://raw.githubusercontent.com/mkerrisk/man-pages/master/man5/proc.5', 'man', 'github-raw', 'mkerrisk/man-pages', 'proc(5) man page'),
        seed('https://raw.githubusercontent.com/freebsd/freebsd-src/main/bin/ls/ls.1', 'mdoc', 'github-raw', 'freebsd/freebsd-src', 'FreeBSD ls(1) mdoc page'),
        seed('https://raw.githubusercontent.com/citation-style-language/styles/master/apa.csl', 'xml', 'github-raw', 'citation-style-language/styles', 'APA CSL XML style'),
        seed('https://raw.githubusercontent.com/citation-style-language/styles/master/academy-of-management-perspectives.csl', 'xml', 'github-raw', 'citation-style-language/styles', 'Academy of Management Perspectives CSL XML'),
        seed('https://raw.githubusercontent.com/citation-style-language/styles/master/academy-of-management-review.csl', 'xml', 'github-raw', 'citation-style-language/styles', 'Academy of Management Review CSL XML'),
        seed('https://raw.githubusercontent.com/citation-style-language/styles/master/accident-analysis-and-prevention.csl', 'xml', 'github-raw', 'citation-style-language/styles', 'Accident Analysis and Prevention CSL XML'),
        seed('https://raw.githubusercontent.com/citation-style-language/styles/master/acm-sig-proceedings.csl', 'xml', 'github-raw', 'citation-style-language/styles', 'ACM SIG proceedings CSL XML'),
        seed('https://raw.githubusercontent.com/citation-style-language/styles/master/acm-sigchi-proceedings.csl', 'xml', 'github-raw', 'citation-style-language/styles', 'ACM SIGCHI proceedings CSL XML'),
        seed('https://raw.githubusercontent.com/citation-style-language/styles/master/acta-adriatica.csl', 'xml', 'github-raw', 'citation-style-language/styles', 'Acta Adriatica CSL XML'),
        seed('https://raw.githubusercontent.com/citation-style-language/styles/master/acta-amazonica.csl', 'xml', 'github-raw', 'citation-style-language/styles', 'Acta Amazonica CSL XML'),
        seed('https://raw.githubusercontent.com/gwoodwa1/network_rag_pipeline/7f9de75055c9801b3fa188f9cc7075bb47ca104d/processed/evpn_design.ast.json', 'json', 'github-raw', 'gwoodwa1/network_rag_pipeline', 'Processed EVPN design Pandoc AST JSON'),
        seed('https://raw.githubusercontent.com/playboypaul/legaldocconverter/4a714e13095cf916d6d43bcfa794c787990fca5a/backend/storage/conversions/dda53d87-b909-4676-8b0a-9f28f56f65aa_converted.json', 'json', 'github-raw', 'playboypaul/legaldocconverter', 'Converted legal document Pandoc JSON'),
        ...seedUrlList('json', 'github-raw', [
            ['https://raw.githubusercontent.com/BroadbandForum/usp/0a170872c0b8162131f3758b63e46bd3c8c64139/docs/index.json', 'BroadbandForum/usp', 'Broadband Forum USP docs Pandoc JSON index'],
            ['https://raw.githubusercontent.com/BroadbandForum/usp/0a170872c0b8162131f3758b63e46bd3c8c64139/docs/faq/index.json', 'BroadbandForum/usp', 'Broadband Forum USP FAQ Pandoc JSON'],
            ['https://raw.githubusercontent.com/BroadbandForum/usp/0a170872c0b8162131f3758b63e46bd3c8c64139/docs/resources/index.json', 'BroadbandForum/usp', 'Broadband Forum USP resources Pandoc JSON'],
            ['https://raw.githubusercontent.com/bmschmidt/bare-bones-blog/6d68376e70ea2fa56c41b00e09be003b497d08dc/build/posts/post1/__data.json', 'bmschmidt/bare-bones-blog', 'Bare bones blog post Pandoc JSON data'],
            ['https://raw.githubusercontent.com/bmschmidt/bare-bones-blog/6d68376e70ea2fa56c41b00e09be003b497d08dc/build/posts/nested_posts/demo/__data.json', 'bmschmidt/bare-bones-blog', 'Bare bones blog nested post Pandoc JSON data'],
            ['https://raw.githubusercontent.com/bmschmidt/bare-bones-blog/6d68376e70ea2fa56c41b00e09be003b497d08dc/build/posts/ipython-notebooks.ip/__data.json', 'bmschmidt/bare-bones-blog', 'Bare bones blog notebook post Pandoc JSON data'],
            ['https://raw.githubusercontent.com/EcrituresNumeriques/markdown-ast-experiments/ad97e95839483845459844b7e2593e5c02b5cc36/pandoc-ast.json', 'EcrituresNumeriques/markdown-ast-experiments', 'Markdown AST experiments Pandoc JSON'],
            ['https://raw.githubusercontent.com/hapejot/pjl.rs/600386073cb97ab5fbb1f941b0e6079b923359cf/docs/unleash_glossar.json', 'hapejot/pjl.rs', 'Hapejot PJL unleash glossary Pandoc JSON'],
            ['https://raw.githubusercontent.com/hapejot/pjl.rs/600386073cb97ab5fbb1f941b0e6079b923359cf/pjl-pandoc/doc.json', 'hapejot/pjl.rs', 'Hapejot PJL Pandoc document JSON'],
            ['https://raw.githubusercontent.com/bmschmidt/bare-bones-blog/6d68376e70ea2fa56c41b00e09be003b497d08dc/build/posts/Have%20you%20always%20wanted%20to%20blog%20in%20Microsoft%20Word.d/__data.json', 'bmschmidt/bare-bones-blog', 'Bare bones blog Word post Pandoc JSON data'],
            ['https://raw.githubusercontent.com/leonl42/DataLit/4dce6e9efa35cae127438bd235ecbcb471fd8fec/arXiv_src_fetcher/ICLR_2023_first100/-iADdfa4GKH/%20parsed.json', 'leonl42/DataLit', 'DataLit ICLR parsed Pandoc JSON -iADdfa4GKH'],
            ['https://raw.githubusercontent.com/leonl42/DataLit/4dce6e9efa35cae127438bd235ecbcb471fd8fec/arXiv_src_fetcher/ICLR_2023_first100/-HHJZlRpGb/%20parsed.json', 'leonl42/DataLit', 'DataLit ICLR parsed Pandoc JSON -HHJZlRpGb'],
            ['https://raw.githubusercontent.com/leonl42/DataLit/4dce6e9efa35cae127438bd235ecbcb471fd8fec/arXiv_src_fetcher/ICLR_2023_first100/-Ov808Vm7dw/%20parsed.json', 'leonl42/DataLit', 'DataLit ICLR parsed Pandoc JSON -Ov808Vm7dw'],
            ['https://raw.githubusercontent.com/go-xoxo/meta-prompt/3c14f30efb2f8b27345388787e64d82361ecd523/cv/david_cv_roundtrip.json', 'go-xoxo/meta-prompt', 'Meta prompt CV Pandoc JSON'],
            ['https://raw.githubusercontent.com/go-xoxo/meta-prompt/3c14f30efb2f8b27345388787e64d82361ecd523/cv/david_cv_roundtrip_fixed.json', 'go-xoxo/meta-prompt', 'Meta prompt CV fixed Pandoc JSON'],
            ['https://raw.githubusercontent.com/go-xoxo/meta-prompt/3c14f30efb2f8b27345388787e64d82361ecd523/cv/david_cv_roundtrip_tables_fixed_final.json', 'go-xoxo/meta-prompt', 'Meta prompt CV final Pandoc JSON'],
            ['https://raw.githubusercontent.com/Soliprem/statistics-project/3962428658e48d7a55da0bd4293e899574090f77/data/index.json', 'Soliprem/statistics-project', 'Statistics project index Pandoc JSON'],
            ['https://raw.githubusercontent.com/paoloceravolo/Editoria-Digitale-Esercizi/4fbc5cfccb31fd71abdcd3f4c124a18bd7e3bda6/Markdown/ast.json', 'paoloceravolo/Editoria-Digitale-Esercizi', 'Editoria Digitale esercizi Markdown AST JSON'],
        ]),
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
        ...seedUrlList('endnotexml', 'github-raw', [
            ['https://raw.githubusercontent.com/medialab/reference_manager/master/data/endnotexml/endnote-aime-old.xml', 'medialab/reference_manager', 'Reference manager old AIME EndNote XML'],
            ['https://raw.githubusercontent.com/medialab/reference_manager/master/data/endnotexml/endnote-aime.xml', 'medialab/reference_manager', 'Reference manager AIME EndNote XML'],
            ['https://raw.githubusercontent.com/prototype-59/livereference/becc84463373ea7444565d1ba248b0d54f912540/endnote.xml', 'prototype-59/livereference', 'LiveReference EndNote XML library'],
            ['https://raw.githubusercontent.com/NCIP/cananolab/eb8efa3d812dee2d5cebed9aff65a6efedb2146a/software/common/resources/security/cananolabEndNote/Round_2_publications_ex5.xml', 'NCIP/cananolab', 'CanaNano Round 2 publications EndNote XML'],
            ['https://raw.githubusercontent.com/HughP/MLKA/11fd28e78804a9a5ba6661cbdf6cf2d1608f9d2b/Publications/2015%20-%20Thesis/XML%20and%20Paterson%20Thesis%20Source%20Files/HughsBiblographyFromEndnote.xml', 'HughP/MLKA', 'Hugh Paterson thesis bibliography EndNote XML'],
            ['https://raw.githubusercontent.com/CobbDouglas/Casual-Inf-Main-paper/afaed7442511db8b0bc4c0819e635b6d55e4878b/References%20for%20main%20paper.xml', 'CobbDouglas/Casual-Inf-Main-paper', 'Casual inference main paper references EndNote XML'],
            ['https://raw.githubusercontent.com/Doadaodao/VR-Surgery-Paper/27e57a62b98c0e3ab961da8e92449868e13c617a/VR%20Surgery%20Library.Data/VR%20Surgery%20Library.xml', 'Doadaodao/VR-Surgery-Paper', 'VR surgery paper library EndNote XML'],
            ['https://raw.githubusercontent.com/hui2109/ScreenRefsViaEndnote/817d06199db9e61671ea32ea759bf7970f55c9f3/sources/source.xml', 'hui2109/ScreenRefsViaEndnote', 'ScreenRefs source EndNote XML'],
            ['https://raw.githubusercontent.com/mogranjm/gwascat_analysis/12ec3cf5194964887ec0f86f91a6713b32361bc8/reporting/Defining%20PRS%20%2B%20SNP%20Validity%20Assessment%20Criteria/resources/Endnote.xml', 'mogranjm/gwascat_analysis', 'GWAS criteria EndNote XML resources'],
            ['https://raw.githubusercontent.com/dveytia/ORO-product-1-mitigation/5d4adec00ac9806b6ec5edbda5930cb9a5d7ada2/data/raw-data/scoping-data-export/CCS.xml', 'dveytia/ORO-product-1-mitigation', 'CDR OAE CCS EndNote export'],
            ['https://raw.githubusercontent.com/GaoBin88/urban-shrinkage-supplement/50383f19ce4b4947be84ff6b46133e5dc2bff012/%E6%96%87%E7%8C%AE.Data/%E6%96%87%E7%8C%AE.xml', 'GaoBin88/urban-shrinkage-supplement', 'Urban shrinkage literature EndNote XML'],
            ['https://raw.githubusercontent.com/Richard6195/tawe_smolt_telemetry/b90a85072967c0f03f999f3a5c12d018b24de419/references/exports/Fisheries%20.XML%20references.xml', 'Richard6195/tawe_smolt_telemetry', 'Tawe smolt telemetry fisheries references EndNote XML'],
            ['https://raw.githubusercontent.com/cwrc/GenreInALinkedDataWorld/194ad00f4fc442901aff4cbc67dd54f20f38d9ff/citations/bibliography.xml', 'cwrc/GenreInALinkedDataWorld', 'CWRC bibliography EndNote XML'],
            ['https://raw.githubusercontent.com/PLBMR/mentalHealthDataAnalysis/eef1d32462721fc537efb7855d02a4c965c86696/mentalHealthFacilities/docs/2010/N-MHSS-2010-info-bibliography.xml', 'PLBMR/mentalHealthDataAnalysis', 'Mental health 2010 bibliography EndNote XML'],
            ['https://raw.githubusercontent.com/PLBMR/mentalHealthDataAnalysis/eef1d32462721fc537efb7855d02a4c965c86696/mentalHealthFacilities/docs/2012/N-MHSS-2012-info-bibliography.xml', 'PLBMR/mentalHealthDataAnalysis', 'Mental health 2012 bibliography EndNote XML'],
            ['https://raw.githubusercontent.com/ying-hua/refill/9af60d56bb28c62a438427fbc3e02ea6849e2a3e/fixed.xml', 'ying-hua/refill', 'Refill fixed EndNote XML'],
            ['https://raw.githubusercontent.com/ying-hua/refill/9af60d56bb28c62a438427fbc3e02ea6849e2a3e/my_lib9.xml', 'ying-hua/refill', 'Refill library 9 EndNote XML'],
            ['https://raw.githubusercontent.com/dveytia/ORO-product-1-mitigation/5d4adec00ac9806b6ec5edbda5930cb9a5d7ada2/data/raw-data/scoping-data-export/CDR_OIF.xml', 'dveytia/ORO-product-1-mitigation', 'CDR OIF EndNote export'],
            ['https://raw.githubusercontent.com/haslamdb/asp_ai_agent/686c034d6aa1443b3f7318183f3f98b2cbc6f983/asp_literature/manual_download_endnote.xml', 'haslamdb/asp_ai_agent', 'ASP literature manual EndNote XML'],
        ]),
        seed('https://raw.githubusercontent.com/guppy0130/j2m/master/j2m.jira', 'jira', 'github-raw', 'guppy0130/j2m', 'j2m Jira wiki markup document'),
        seed('https://raw.githubusercontent.com/membase/membase-cli/13195507facba8cb8f85dafb07df1eeff3ea7dcd/docs/cbtransfer-func-spec.jira', 'jira', 'github-raw', 'membase/membase-cli', 'cbtransfer functional spec Jira markup'),
        seed('https://raw.githubusercontent.com/membase/membase-cli/13195507facba8cb8f85dafb07df1eeff3ea7dcd/docs/cbbackup-restore-func-spec.jira', 'jira', 'github-raw', 'membase/membase-cli', 'cbbackup restore functional spec Jira markup'),
        seed('https://raw.githubusercontent.com/couchbase/couchbase-cli/a63b7323be21848f9449e61085052cdc028eb27c/docs/design/cbtransfer-func-spec.jira', 'jira', 'github-raw', 'couchbase/couchbase-cli', 'Couchbase cbtransfer design spec Jira markup'),
        ...seedUrlList('jira', 'github-raw', [
            ['https://raw.githubusercontent.com/jgm/pandoc/7b1021eb3ec2fcb0fdb78478a9eeffbcc890737b/data/templates/default.jira', 'jgm/pandoc', 'Pandoc default Jira template'],
            ['https://raw.githubusercontent.com/jgm/pandoc-templates/6d3b0e89f62a345022ebe14b21cf8fd1c9cc5baa/default.jira', 'jgm/pandoc-templates', 'Pandoc templates default Jira template'],
            ['https://raw.githubusercontent.com/solita/solita-geekout/b3322f171b176be3d897b017496fa76f7e9cf3a4/pointless-ticket.jira', 'solita/solita-geekout', 'Solita Geekout Jira ticket'],
            ['https://raw.githubusercontent.com/karlredgate/automation/4d1b8a7f734140f0d40178145a31c0ddb8065005/jira/issues.jira', 'karlredgate/automation', 'Karl Redgate automation Jira issues'],
            ['https://raw.githubusercontent.com/fedora-ci/eln-periodic/ba0a06d121e386143966e1d41e91a8fbac621498/status.html.jira', 'fedora-ci/eln-periodic', 'Fedora ELN periodic status Jira markup'],
            ['https://raw.githubusercontent.com/fedora-ci/eln-periodic/ba0a06d121e386143966e1d41e91a8fbac621498/successrate.html.jira', 'fedora-ci/eln-periodic', 'Fedora ELN periodic success rate Jira markup'],
            ['https://raw.githubusercontent.com/go-xoxo/meta-prompt/3c14f30efb2f8b27345388787e64d82361ecd523/cv/cv.jira', 'go-xoxo/meta-prompt', 'Meta prompt CV Jira output'],
            ['https://raw.githubusercontent.com/shegerbootcamp/docs/51589b07901716498b95ca013747de3a7a9d616c/confluence.jira', 'shegerbootcamp/docs', 'Sheger Bootcamp Confluence Jira export'],
            ['https://raw.githubusercontent.com/shegerbootcamp/docs/51589b07901716498b95ca013747de3a7a9d616c/conflu.jira', 'shegerbootcamp/docs', 'Sheger Bootcamp Conflu Jira export'],
            ['https://raw.githubusercontent.com/vicamo/kteam-tools/d70bdfca8f4594d1fe5bfbe2373729e6220cd902/stable/README.JIRA', 'vicamo/kteam-tools', 'KTeam tools stable README Jira markup'],
            ['https://raw.githubusercontent.com/karlredgate/automation/4d1b8a7f734140f0d40178145a31c0ddb8065005/jira/issue.jira', 'karlredgate/automation', 'Karl Redgate automation Jira issue'],
            ['https://raw.githubusercontent.com/karlredgate/automation/4d1b8a7f734140f0d40178145a31c0ddb8065005/jira/activity.jira', 'karlredgate/automation', 'Karl Redgate automation Jira activity'],
            ['https://raw.githubusercontent.com/StevenACoffman/toolbox/e0c721d9ee0aa37a0d0f35900e5ffa71bd0eb49e/cmd/j2m/j2m.jira', 'StevenACoffman/toolbox', 'Steven Coffman toolbox j2m Jira document'],
            ['https://raw.githubusercontent.com/avsej/couchbase.deb/f9137835f626ce2e7e23f0576301398e23e4b91b/membase-cli/docs/cbbackup-restore-func-spec.jira', 'avsej/couchbase.deb', 'Avsej Couchbase cbbackup Jira spec'],
            ['https://raw.githubusercontent.com/avsej/couchbase.deb/f9137835f626ce2e7e23f0576301398e23e4b91b/membase-cli/docs/cbtransfer-func-spec.jira', 'avsej/couchbase.deb', 'Avsej Couchbase cbtransfer Jira spec'],
            ['https://raw.githubusercontent.com/UW-Madison-DoIT/jiraRemoteUserAuth/b93d68f1fe1354f64730a34f1cf9ff2bf98084c0/conf/remoteUserAuthenticator.properties.jira', 'UW-Madison-DoIT/jiraRemoteUserAuth', 'UW Madison remote user authenticator Jira properties'],
            ['https://raw.githubusercontent.com/con2/infrastructure/0437ef2ab956f316ca1d73148007322b6cdede3f/roles/atlassian/files/Dockerfile.jira', 'con2/infrastructure', 'Con2 infrastructure Atlassian Jira Dockerfile'],
            ['https://raw.githubusercontent.com/rtyler/presentations/3f400d7753cf4c66d5b9d2bc690a834a76bff6de/continuous-delivery-infra/content/Dockerfile.jira', 'rtyler/presentations', 'Rtyler continuous delivery Jira Dockerfile'],
            ['https://raw.githubusercontent.com/openpkg/packages/2e43ee5397a7a813ac126e51b30bd9972bdeb343/jira/rc.jira', 'openpkg/packages', 'OpenPKG Jira rc script'],
            ['https://raw.githubusercontent.com/scarnecchia/qa-package-rs/84c644db17773bba56094c9d37bdd845c5550e1d/qa_package.jira', 'scarnecchia/qa-package-rs', 'Scarnecchia QA package Jira document'],
        ]),
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
        seed('https://fletcherpenney.net/multimarkdown/cms/index.txt', 'markdown_mmd', 'author-web', 'fletcherpenney.net', 'MultiMarkdown CMS source page'),
        seed('https://fletcherpenney.net/colophon/index.txt', 'markdown_mmd', 'author-web', 'fletcherpenney.net', 'Fletcher Penney colophon source page'),
        seed('https://fletcherpenney.net/support/index.txt', 'markdown_mmd', 'author-web', 'fletcherpenney.net', 'Fletcher Penney support source page'),
        ...fletcherMmdPostSeedUrls([
            ['/2006/01/sample_multi_markdown_document', 'Sample MultiMarkdown document source post'],
            ['/2009/09/multimarkdown_as_cms', 'MultiMarkdown as CMS source post'],
            ['/2010/01/how_to_use_mmd_as_your_cms', 'How to use MMD as your CMS source post'],
            ['/2010/01/sample_multimarkdown_document', 'Sample MultiMarkdown document 2010 source post'],
            ['/2011/04/multimarkdown_3.0_released!!!', 'MultiMarkdown 3.0 release source post'],
            ['/2011/05/using_multimarkdown_with_omnioutlin', 'Using MultiMarkdown with OmniOutliner source post'],
            ['/2013/04/mmd4', 'MMD4 source post'],
            ['/2014/04/abbreviations', 'MultiMarkdown abbreviations source post'],
            ['/2017/03/how_to_create_epub', 'How to create EPUB source post'],
            ['/2017/03/mmd_6_epub', 'MMD 6 EPUB source post'],
            ['/2018/12/multimarkdown_accessibility', 'MultiMarkdown accessibility source post'],
            ['/2025/11/multimarkdown_7', 'MultiMarkdown 7 source post'],
        ]),
        seed('https://raw.githubusercontent.com/composer/composer/main/doc/01-basic-usage.md', 'markdown_phpextra', 'github-raw', 'composer/composer', 'Composer basic usage docs'),
        seed('https://raw.githubusercontent.com/composer/composer/main/doc/02-libraries.md', 'markdown_phpextra', 'github-raw', 'composer/composer', 'Composer libraries docs'),
        seed('https://raw.githubusercontent.com/composer/composer/main/doc/03-cli.md', 'markdown_phpextra', 'github-raw', 'composer/composer', 'Composer CLI docs'),
        seed('https://raw.githubusercontent.com/composer/composer/main/doc/04-schema.md', 'markdown_phpextra', 'github-raw', 'composer/composer', 'Composer schema docs'),
        seed('https://raw.githubusercontent.com/composer/composer/main/doc/05-repositories.md', 'markdown_phpextra', 'github-raw', 'composer/composer', 'Composer repositories docs'),
        seed('https://raw.githubusercontent.com/composer/composer/main/doc/06-config.md', 'markdown_phpextra', 'github-raw', 'composer/composer', 'Composer config docs'),
        seed('https://raw.githubusercontent.com/composer/composer/main/doc/07-runtime.md', 'markdown_phpextra', 'github-raw', 'composer/composer', 'Composer runtime docs'),
        seed('https://raw.githubusercontent.com/b0mbix/markupit/e110aa5b95cf3263796adffe85a7fbf5eae4ad50/misc/ast_analysis/gfm.json', 'native', 'github-raw', 'b0mbix/markupit', 'Markupit generated Pandoc native JSON AST'),
        ...seedUrlList('native', 'github-raw', [
            ['https://raw.githubusercontent.com/stfnbssl/wizzi.cli/6e0355a6c758166a6910e6b3a5b082227b99a6c3/packages/wizzi.scripts/pandoc/_posts_temp/preview.md.native', 'stfnbssl/wizzi.cli', 'Wizzi preview post native Pandoc output'],
            ['https://raw.githubusercontent.com/stfnbssl/wizzi.cli/6e0355a6c758166a6910e6b3a5b082227b99a6c3/packages/wizzi.scripts/pandoc/_posts_temp/hello-world.md.native', 'stfnbssl/wizzi.cli', 'Wizzi hello world post native Pandoc output'],
            ['https://raw.githubusercontent.com/stfnbssl/wizzi.cli/6e0355a6c758166a6910e6b3a5b082227b99a6c3/packages/wizzi.scripts/pandoc/_posts_temp/dynamic-routing.md.native', 'stfnbssl/wizzi.cli', 'Wizzi dynamic routing post native Pandoc output'],
            ['https://raw.githubusercontent.com/edenian-prince/translatemd/8b85973dd33de5b9d5b40e699b085953aab1f8f1/single-doc/single-doc.native', 'edenian-prince/translatemd', 'Translatemd single document native output'],
            ['https://raw.githubusercontent.com/anubav/exercises/3f8a4529a0c67e5bdb0702b2d4eea2501c6f3159/example.native', 'anubav/exercises', 'Exercises example native document'],
            ['https://raw.githubusercontent.com/kai-prince-sfhea/Additional-Citation-Options/201cb67c05a755412224827a0a02cf9c79ac53bf/_site/template.native', 'kai-prince-sfhea/Additional-Citation-Options', 'Additional citation options template native output'],
            ['https://raw.githubusercontent.com/kai-prince-sfhea/Schema/106252e3bca82dd507086687162aaf588ca8f7b3/_site/template.native', 'kai-prince-sfhea/Schema', 'Schema site template native output'],
            ['https://raw.githubusercontent.com/kai-prince-sfhea/Schema/106252e3bca82dd507086687162aaf588ca8f7b3/_site/template-folder/template-no-terms.native', 'kai-prince-sfhea/Schema', 'Schema template no terms native output'],
            ['https://raw.githubusercontent.com/DO3SE/pyDO3SE-open/e871e1f3d2e89c97a464e5ceedbdebf68c0460e6/src/pyDO3SE/docs/experiment/demo_link_rawword.native', 'DO3SE/pyDO3SE-open', 'DO3SE experiment native documentation'],
            ['https://raw.githubusercontent.com/rbdixon/pandoc-yaml-block-issue/1ccbe6e105241fb5ffe8869da982bcb830c81779/1_18_plus/report.native', 'rbdixon/pandoc-yaml-block-issue', 'YAML block issue 1.18 report native output'],
            ['https://raw.githubusercontent.com/tittoassini/www/bc2868446cd60ffe8697c14c448d8626ce001a43/site/quid2.org/web/docs/Sample.native', 'tittoassini/www', 'Quid2 sample native documentation'],
            ['https://raw.githubusercontent.com/tittoassini/www/bc2868446cd60ffe8697c14c448d8626ce001a43/site/quid2.org/web/docs/ZhengMing.native', 'tittoassini/www', 'Quid2 ZhengMing native documentation'],
            ['https://raw.githubusercontent.com/tittoassini/www/bc2868446cd60ffe8697c14c448d8626ce001a43/site/quid2.org/web/docs/manual.native', 'tittoassini/www', 'Quid2 manual native documentation'],
            ['https://raw.githubusercontent.com/tittoassini/www/bc2868446cd60ffe8697c14c448d8626ce001a43/site/quid2.org/web/docs/Quid2Model.native', 'tittoassini/www', 'Quid2 model native documentation'],
            ['https://raw.githubusercontent.com/tittoassini/www/bc2868446cd60ffe8697c14c448d8626ce001a43/site/quid2.org/web/docs/Quid2Binary.native', 'tittoassini/www', 'Quid2 binary native documentation'],
            ['https://raw.githubusercontent.com/tittoassini/www/bc2868446cd60ffe8697c14c448d8626ce001a43/site/quid2.org/web/docs/Top.native', 'tittoassini/www', 'Quid2 top native documentation'],
            ['https://raw.githubusercontent.com/tittoassini/www/bc2868446cd60ffe8697c14c448d8626ce001a43/site/quid2.org/web/docs/Flat.native', 'tittoassini/www', 'Quid2 flat native documentation'],
            ['https://raw.githubusercontent.com/kai-prince-sfhea/Schema/106252e3bca82dd507086687162aaf588ca8f7b3/_site/template-folder/template-group.native', 'kai-prince-sfhea/Schema', 'Schema template group native output'],
            ['https://raw.githubusercontent.com/kai-prince-sfhea/Schema/106252e3bca82dd507086687162aaf588ca8f7b3/_site/template-folder/template-folner.native', 'kai-prince-sfhea/Schema', 'Schema template folner native output'],
            ['https://raw.githubusercontent.com/jandermoreira/algxpar-quarto/1cdf7b165857a5e35739b013b517bce3ef9b0ac7/examples/simple/example.native', 'jandermoreira/algxpar-quarto', 'Algxpar quarto simple native example'],
            ['https://raw.githubusercontent.com/rbdixon/pandoc-yaml-block-issue/1ccbe6e105241fb5ffe8869da982bcb830c81779/2_0_1_minus/report.native', 'rbdixon/pandoc-yaml-block-issue', 'YAML block issue 2.0.1 minus report native output'],
            ['https://raw.githubusercontent.com/rbdixon/pandoc-yaml-block-issue/1ccbe6e105241fb5ffe8869da982bcb830c81779/1_17_2_none/report.native', 'rbdixon/pandoc-yaml-block-issue', 'YAML block issue 1.17.2 none report native output'],
        ]),
        seed('https://raw.githubusercontent.com/geometer/FBReaderJ/e83aec9f94084aa59d39e33876bdb6fdc275c95e/obsolete/help/MiniHelp.fr.fb2', 'fb2', 'github-raw', 'geometer/FBReaderJ', 'FBReaderJ French mini help FB2'),
        seed('https://raw.githubusercontent.com/geometer/FBReaderJ/e83aec9f94084aa59d39e33876bdb6fdc275c95e/obsolete/help/MiniHelp.de.fb2', 'fb2', 'github-raw', 'geometer/FBReaderJ', 'FBReaderJ German mini help FB2'),
        seed('https://raw.githubusercontent.com/geometer/FBReaderJ/e83aec9f94084aa59d39e33876bdb6fdc275c95e/obsolete/help/MiniHelp.vi.fb2', 'fb2', 'github-raw', 'geometer/FBReaderJ', 'FBReaderJ Vietnamese mini help FB2'),
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
        seed('https://raw.githubusercontent.com/hadley/adv-r/HEAD/book.bib', 'biblatex', 'github-raw', 'hadley/adv-r', 'Advanced R book bibliography BibLaTeX'),
        seed('https://raw.githubusercontent.com/yihui/bookdown-crc/HEAD/book.bib', 'biblatex', 'github-raw', 'yihui/bookdown-crc', 'Bookdown CRC book bibliography BibLaTeX'),
        seed('https://raw.githubusercontent.com/yihui/bookdown-crc/HEAD/packages.bib', 'biblatex', 'github-raw', 'yihui/bookdown-crc', 'Bookdown CRC packages bibliography BibLaTeX'),
        ...crossrefBiblatexSeedUrls([
            ['10.1038/s41586-024-07566-y', 'Nature AlphaFold 3 article'],
            ['10.1145/3664647.3681704', 'ACM article 2024'],
            ['10.1109/CVPR52733.2024.00042', 'IEEE CVPR 2024 paper'],
            ['10.1016/j.artint.2023.103996', 'Artificial Intelligence article'],
            ['10.1093/nar/gkae1052', 'NAR article gkae1052'],
            ['10.1371/journal.pbio.3002730', 'PLOS Biology article'],
            ['10.1038/nature16961', 'Nature gravitational waves article'],
            ['10.1126/science.aad0501', 'Science gravitational waves article'],
            ['10.1145/3133956.3134077', 'ACM Spectre-style paper'],
            ['10.1109/SP.2019.00001', 'IEEE security paper'],
            ['10.1016/j.neunet.2014.09.003', 'Adam optimizer article'],
        ]),
        seed('https://raw.githubusercontent.com/quantum-journal/quantum-journal/master/quantum-bibliographystyle-demo.bib', 'bibtex', 'github-raw', 'quantum-journal/quantum-journal', 'Quantum bibliography style demo BibTeX'),
        seed('https://api.crossref.org/works/10.7554/eLife.32822/transform/application/x-research-info-systems', 'ris', 'crossref-api', 'crossref.org', 'Crossref RIS export for eLife 32822'),
        seed('https://api.crossref.org/works/10.1038/s41586-020-2649-2/transform/application/x-research-info-systems', 'ris', 'crossref-api', 'crossref.org', 'Crossref RIS export for Nature article'),
        seed('https://api.crossref.org/works/10.1126/science.169.3946.635/transform/application/x-research-info-systems', 'ris', 'crossref-api', 'crossref.org', 'Crossref RIS export for Science article'),
        seed('https://api.crossref.org/works/10.1038/nature14539/transform/application/x-bibtex', 'bibtex', 'crossref-api', 'crossref.org', 'Crossref BibTeX for Nature AlphaGo article'),
        seed('https://api.crossref.org/works/10.1038/nature14539/transform/application/x-research-info-systems', 'ris', 'crossref-api', 'crossref.org', 'Crossref RIS for Nature AlphaGo article'),
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
        seed('https://www.sandia.gov/app/uploads/sites/177/2022/12/7_27_2022_14_35_rcole-FY22_MLDL_Submission_Abstract.pptx', 'pptx', 'government-web', 'sandia.gov', 'Sandia MLDL submission abstract PPTX'),
        seed('https://treasurer.delaware.gov/wp-content/uploads/sites/55/2021/05/Governance-Review.pptx', 'pptx', 'government-web', 'treasurer.delaware.gov', 'Delaware governance review PPTX'),
        seed('https://mmac.mo.gov/wp-content/uploads/sites/11/2021/04/Update-Meeting-Spring-2021-Provider-Review.pptx', 'pptx', 'government-web', 'mmac.mo.gov', 'Missouri provider review update PPTX'),
        seed('https://ntrs.nasa.gov/api/citations/20240011703/downloads/Wood_%20Poster%20SPD-41a_BPS-Final.pptx', 'pptx', 'government-web', 'nasa.gov', 'NASA SPD-41a poster PPTX'),
        seed('https://www.ftc.gov/sites/default/files/documents/public_events/fifth-annual-african-consumer-protection-dialogue-conference-livingstone-zambia/ghanasession5.pptx', 'pptx', 'government-web', 'ftc.gov', 'FTC Ghana session consumer protection PPTX'),
        seed('https://www.energy.gov/sites/default/files/2023-03/FCIC%20CRADA%20Call%20Pitch%20Slides%20Template.pptx', 'pptx', 'government-web', 'energy.gov', 'DOE FCIC pitch slides template PPTX'),
        seed('https://www.energy.gov/sites/default/files/2024-09/FCIC%20FY25%20IPC_Pitch%20Slides%20Template.pptx', 'pptx', 'government-web', 'energy.gov', 'DOE FCIC FY25 pitch slides template PPTX'),
        seed('https://www.energy.gov/sites/default/files/2022-05/RACER%20Webinar.pptx', 'pptx', 'government-web', 'energy.gov', 'DOE RACER webinar PPTX'),
        seed('https://www.nj.gov/iija/documents/resources/IIJA%20Slides%20for%20Website.pptx', 'pptx', 'government-web', 'nj.gov', 'New Jersey IIJA slides PPTX'),
        seed('https://ntrs.nasa.gov/api/citations/20220015530/downloads/ACTIVATE_STM_2022.pptx', 'pptx', 'government-web', 'nasa.gov', 'NASA ACTIVATE STM 2022 PPTX'),
        seed('https://www.ftc.gov/sites/default/files/documents/public_events/fifth-annual-african-consumer-protection-dialogue-conference-livingstone-zambia/comesaccsession1.pptx', 'pptx', 'government-web', 'ftc.gov', 'FTC COMESA consumer protection session PPTX'),
        seed('https://ntrs.nasa.gov/api/citations/20240015263/downloads/AGU2024_APT.pptx', 'pptx', 'government-web', 'nasa.gov', 'NASA AGU 2024 APT PPTX'),
        seed('https://www.ftc.gov/sites/default/files/documents/public_events/fifth-annual-african-consumer-protection-dialogue-conference-livingstone-zambia/nigeriasession1.pptx', 'pptx', 'government-web', 'ftc.gov', 'FTC Nigeria consumer protection session PPTX'),
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
        seed('https://vendornet.wi.gov/Download.aspx?Id=420772a3-d140-ec11-8137-0050568c7f0f&filename=SOW+Template.doc&type=contract', 'doc', 'government-web', 'vendornet.wi.gov', 'Wisconsin statement of work template DOC'),
        seed('https://docs.fcc.gov/public/attachments/FCC-19-22A1.doc', 'doc', 'government-web', 'docs.fcc.gov', 'FCC spectrum leasing rules DOC'),
        seed('https://wpcdn.web.wsu.edu/wp-daesa/uploads/sites/634/2017/01/Building-a-Syllabus.doc', 'doc', 'education-web', 'wsu.edu', 'Washington State building a syllabus DOC'),
        seed('https://www.waterboards.ca.gov/water_issues/programs/beaches/cbi_projects/docs/progress.doc', 'doc', 'government-web', 'waterboards.ca.gov', 'California water boards progress report template DOC'),
        seed('https://haas.berkeley.edu/wp-content/uploads/Course-Design-Handout.doc', 'doc', 'education-web', 'haas.berkeley.edu', 'Berkeley Haas course design handout DOC'),
        seed('https://courts.ca.gov/system/files?file=solicitation-request-document%2Fphoenix-rfp-appendf.doc', 'doc', 'government-web', 'courts.ca.gov', 'California courts Phoenix RFP appendix F DOC'),
        seed('https://oklahoma.gov/content/dam/ok/en/omes/documents/Solicitation3400001451AppendixA%20.doc', 'doc', 'government-web', 'oklahoma.gov', 'Oklahoma solicitation appendix A DOC'),
        seed('https://alamedacountyca.gov/gsaapp/purchasing/bidContent_ftp/rfpDocs/RFP%23901843.doc', 'doc', 'government-web', 'alamedacountyca.gov', 'Alameda County locum tenens RFP DOC'),
        seed('https://dwd.wisconsin.gov/dwd/forms/erd/doc/erd-18529.doc', 'doc', 'government-web', 'dwd.wisconsin.gov', 'Wisconsin retaliation complaint public employees DOC'),
        seed('https://www.waterboards.ca.gov/rwqcb2/water_issues/programs/401_certs/401_app_e-form_apr05.doc', 'doc', 'government-web', 'waterboards.ca.gov', 'California 401 WQC ROWD application DOC'),
        seed('https://docs.fcc.gov/public/attachments/DOC-310286A1.doc', 'doc', 'government-web', 'docs.fcc.gov', 'FCC public attachment DOC 310286A1'),
        seed('https://dwd.wisconsin.gov/dwd/forms/DVR/doc/dvr-18543-e.doc', 'doc', 'government-web', 'dwd.wisconsin.gov', 'Wisconsin DVR assistive technology training proposal DOC'),
        seed('https://www.waterboards.ca.gov/centralcoast/publications_forms/forms/docs/waste_pile_application.doc', 'doc', 'government-web', 'waterboards.ca.gov', 'California waste pile application DOC'),
        seed('https://dwd.wisconsin.gov/dwd/forms/dvr/doc/dvr-17445-e-v.doc', 'doc', 'government-web', 'dwd.wisconsin.gov', 'Wisconsin DVR referral Vietnamese DOC'),
        seed('https://www.waterboards.ca.gov/rwqcb2/water_issues/programs/401_certs/appinst.doc', 'doc', 'government-web', 'waterboards.ca.gov', 'California 401 WQC ROWD instructions DOC'),
        seed('https://docs.fcc.gov/public/attachments/DA-20-567A1.doc', 'doc', 'government-web', 'docs.fcc.gov', 'FCC DA 20-567A1 DOC'),
        seed('https://raw.githubusercontent.com/docbook/xslt10-stylesheets/master/xsl/docsrc/reference.xml', 'docbook', 'github-raw', 'docbook/xslt10-stylesheets', 'DocBook XSLT reference manual XML'),
        seed('https://raw.githubusercontent.com/docbook/xslt10-stylesheets/master/xsl/docsrc/warranty.xml', 'docbook', 'github-raw', 'docbook/xslt10-stylesheets', 'DocBook XSLT warranty documentation XML'),
        seed('https://raw.githubusercontent.com/docbook/xslt10-stylesheets/master/xsl/params/abstract.notitle.enabled.xml', 'docbook', 'github-raw', 'docbook/xslt10-stylesheets', 'DocBook abstract title parameter XML'),
        seed('https://raw.githubusercontent.com/docbook/xslt10-stylesheets/master/xsl/params/admon.graphics.xml', 'docbook', 'github-raw', 'docbook/xslt10-stylesheets', 'DocBook admon graphics parameter XML'),
        seed('https://docs.oasis-open.org/docbook/specs/docbook-4.5-spec.xml', 'docbook', 'standards-web', 'docs.oasis-open.org', 'DocBook 4.5 specification XML'),
        seed('https://dicom.nema.org/medical/Dicom/2024d/source/docbook/part14/part14.xml', 'docbook', 'standards-web', 'dicom.nema.org', 'DICOM PS3.14 DocBook XML'),
        ...docbookParamSeedUrls(['abstract.properties', 'abstract.title.properties', 'activate.external.olinks', 'active.toc', 'ade.extensions', 'admon.graphics.extension', 'admon.graphics.path', 'admon.style', 'admon.textlabel', 'admonition.properties', 'admonition.title.properties', 'alignment', 'annotate.toc', 'appendix.autolabel']),
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
        seed('https://www.dhs.wisconsin.gov/publications/p03173.docx', 'docx', 'government-web', 'dhs.wisconsin.gov', 'Wisconsin reproductive health policy template DOCX'),
        seed('https://www.dhs.wisconsin.gov/publications/p0/p00164.docx', 'docx', 'government-web', 'dhs.wisconsin.gov', 'Wisconsin civil rights compliance requirements DOCX'),
        seed('https://www.dhs.wisconsin.gov/immunization/vfc-vaccine-management-plan-template.docx', 'docx', 'government-web', 'dhs.wisconsin.gov', 'Wisconsin VFC vaccine management plan DOCX'),
        seed('https://www.dhs.wisconsin.gov/publications/p0/p00649.docx', 'docx', 'government-web', 'dhs.wisconsin.gov', 'Wisconsin Family Care member handbook template DOCX'),
        seed('https://www.dhs.wisconsin.gov/areaadmin/aps-staff-trng-sample-policy-manual.docx', 'docx', 'government-web', 'dhs.wisconsin.gov', 'Wisconsin APS sample policy manual DOCX'),
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
        seed('https://flcourts-media.flcourts.gov/content/download/685814/file_rtf/902d.rtf', 'rtf', 'government-web', 'flcourts.gov', 'Florida family law form 902d RTF'),
        seed('https://flcourts-media.flcourts.gov/content/download/686033/file_rtf/995c.rtf', 'rtf', 'government-web', 'flcourts.gov', 'Florida family law form 995c RTF'),
        seed('https://flcourts-media.flcourts.gov/content/download/685809/file_rtf/901b2.rtf', 'rtf', 'government-web', 'flcourts.gov', 'Florida family law form 901b2 RTF'),
        seed('https://flcourts-media.flcourts.gov/content/download/685817/file_rtf/902f2.rtf', 'rtf', 'government-web', 'flcourts.gov', 'Florida family law form 902f2 RTF'),
        seed('https://flcourts-media.flcourts.gov/content/download/685822/file_rtf/903a.rtf', 'rtf', 'government-web', 'flcourts.gov', 'Florida family law form 903a RTF'),
        seed('https://flcourts-media.flcourts.gov/content/download/685834/file_rtf/905a.rtf', 'rtf', 'government-web', 'flcourts.gov', 'Florida family law form 905a RTF'),
        seed('https://www.nj.gov/transportation/eng/forms/docs/maintenance/mt105a.rtf', 'rtf', 'government-web', 'nj.gov', 'New Jersey bridge attachment application RTF'),
        seed('https://www.nj.gov/transportation/eng/forms/docs/construction/dc20.rtf', 'rtf', 'government-web', 'nj.gov', 'NJDOT DC20 construction form RTF'),
        seed('https://nj.gov/transportation/eng/forms/docs/construction/dc34.rtf', 'rtf', 'government-web', 'nj.gov', 'NJDOT key contract personnel form RTF'),
        seed('https://www.nj.gov/transportation/eng/forms/docs/statewide/to101.rtf', 'rtf', 'government-web', 'nj.gov', 'NJDOT lane closure daily form RTF'),
        seed('https://www.nj.gov/transportation/business/vendorhelp/docs/pv.rtf', 'rtf', 'government-web', 'nj.gov', 'NJDOT payment voucher form RTF'),
        seed('https://dnr.illinois.gov/content/dam/soi/en/web/dnr/conservation/m2p/documents/monthlybudget-m2p.odt', 'odt', 'government-web', 'dnr.illinois.gov', 'Illinois monthly expenditure schedule ODT'),
        seed('https://townofgalwayny.gov/wp-content/uploads/2023/06/Planning-Board-Meeting-Notes-April-2023-DRAFT.odt', 'odt', 'municipal-web', 'townofgalwayny.gov', 'Galway planning board meeting notes ODT'),
        seed('https://www.phenix.bnl.gov/~suhanov/ncc/materials/adhesives/adhesives.odt', 'odt', 'research-web', 'bnl.gov', 'BNL conductive adhesives ODT'),
        seed('https://ndlegis.gov/assembly/69-2025/regular/documents/25-0246-03001m.odt', 'odt', 'government-web', 'ndlegis.gov', 'North Dakota legislative measure 25-0246 ODT'),
        seed('https://camdencountymo.gov/wp-content/uploads/COMMUNICATIONS-OFFICER-SHERIFF.odt', 'odt', 'municipal-web', 'camdencountymo.gov', 'Camden County communications officer posting ODT'),
        seed('https://www.kslegislature.gov/li_2014/b2013_14/measures/odt_view/je_20130402180450_618326.odt', 'odt', 'government-web', 'kslegislature.gov', 'Kansas HB 2069 ODT'),
        seed('https://ndlegis.gov/assembly/69-2025/regular/documents/25-0497-01002m.odt', 'odt', 'government-web', 'ndlegis.gov', 'North Dakota legislative measure 25-0497 ODT'),
        seed('https://www.kslegislature.gov/li_2012/b2011_12/measures/odt_view/je_20120222103832_756527.odt', 'odt', 'government-web', 'kslegislature.gov', 'Kansas legislative measure ODT'),
        seed('https://ndlegis.gov/assembly/69-2025/regular/documents/25-0902-02003m.odt', 'odt', 'government-web', 'ndlegis.gov', 'North Dakota legislative measure 25-0902 ODT'),
        seed('https://ndlegis.gov/assembly/69-2025/regular/documents/25-1011-02001m.odt', 'odt', 'government-web', 'ndlegis.gov', 'North Dakota legislative measure 25-1011 ODT'),
        seed('https://ndlegis.gov/assembly/69-2025/regular/documents/25-1303-01001m.odt', 'odt', 'government-web', 'ndlegis.gov', 'North Dakota legislative measure 25-1303 ODT'),
        seed('https://ndlegis.gov/assembly/69-2025/regular/documents/25-0246-04000.odt', 'odt', 'government-web', 'ndlegis.gov', 'North Dakota legislative measure 25-0246 engrossed ODT'),
        seed('https://ndlegis.gov/assembly/69-2025/regular/documents/25-0497-02000.odt', 'odt', 'government-web', 'ndlegis.gov', 'North Dakota legislative measure 25-0497 engrossed ODT'),
        seed('https://ndlegis.gov/assembly/69-2025/regular/documents/25-1011-03000.odt', 'odt', 'government-web', 'ndlegis.gov', 'North Dakota legislative measure 25-1011 engrossed ODT'),
        seed('https://ndlegis.gov/assembly/69-2025/regular/documents/25-1303-02000.odt', 'odt', 'government-web', 'ndlegis.gov', 'North Dakota legislative measure 25-1303 engrossed ODT'),
        seed('https://ndlegis.gov/assembly/69-2025/regular/documents/25-0246-05000.odt', 'odt', 'government-web', 'ndlegis.gov', 'North Dakota legislative measure 25-0246 final ODT'),
        seed('https://ndlegis.gov/assembly/69-2025/regular/documents/25-0497-03000.odt', 'odt', 'government-web', 'ndlegis.gov', 'North Dakota legislative measure 25-0497 final ODT'),
        seed('https://ndlegis.gov/assembly/69-2025/regular/documents/25-0740-02000.odt', 'odt', 'government-web', 'ndlegis.gov', 'North Dakota legislative measure 25-0740 amended ODT'),
        seed('https://eagleriverwi.gov/wp-content/uploads/2024/07/2024.3.25-Affordable-Housing-Committee-Meeting-Minutes.odt', 'odt', 'municipal-web', 'eagleriverwi.gov', 'Eagle River affordable housing minutes ODT'),
        seed('https://stmaryspa.gov/wp-content/uploads/2025/07/Agenda-August-6-2025.odt', 'odt', 'municipal-web', 'stmaryspa.gov', 'Saint Marys shade tree agenda ODT'),
        seed('https://lincolncountyne.gov/wp-content/uploads/2025/09/09-22-2025.odt', 'odt', 'municipal-web', 'lincolncountyne.gov', 'Lincoln County board agenda ODT'),
        ...gsjBitsSeedUrls(['0439', '0443', '0445', '0447', '0448', '0449', '0458', '0459', '0478', '0481', '0484', '0488', '0501', '0502', '0506', '0510', '0516', '0521', '0526', '0527']),
        seed('https://www.eia.gov/energyexplained/oil-and-petroleum-products/data/US-tight-oil-production.xlsx', 'xlsx', 'government-web', 'eia.gov', 'EIA tight oil production XLSX'),
        seed('https://edd.ca.gov/siteassets/files/newsroom/facts-and-stats/excel/call-center-data.xlsx', 'xlsx', 'government-web', 'edd.ca.gov', 'California EDD call center data XLSX'),
        seed('https://www.fdic.gov/quarterly-banking-profile/all-fdic-insured-institutions-charts-and-data.xlsx', 'xlsx', 'government-web', 'fdic.gov', 'FDIC institutions charts and data XLSX'),
        seed('https://www.dli.mn.gov/sites/default/files/xls/it-data-science.xlsx', 'xlsx', 'government-web', 'dli.mn.gov', 'Minnesota IT data science apprenticeship XLSX'),
        seed('https://coast.noaa.gov/data/digitalcoast/xls/ccap-changedata.xlsx', 'xlsx', 'government-web', 'coast.noaa.gov', 'NOAA C-CAP change data workbook XLSX'),
        seed('https://raw.githubusercontent.com/plotly/datasets/master/Blog/autobook.xlsx', 'xlsx', 'github-raw', 'plotly/datasets', 'Plotly autobook XLSX'),
        seed('https://raw.githubusercontent.com/plotly/datasets/master/data-matlab-excel-example.xlsx', 'xlsx', 'github-raw', 'plotly/datasets', 'Plotly MATLAB Excel example XLSX'),
        seed('https://raw.githubusercontent.com/plotly/datasets/master/supermarket_sales.xlsx', 'xlsx', 'github-raw', 'plotly/datasets', 'Plotly supermarket sales XLSX'),
        seed('https://hcup-us.ahrq.gov/doc/HCUP_DatabaseCatalog_NationwideDatabases.xlsx', 'xlsx', 'government-web', 'hcup-us.ahrq.gov', 'AHRQ HCUP database catalog XLSX'),
        seed('https://www.eia.gov/petroleum/drilling/xls/dpr-data.xlsx', 'xlsx', 'government-web', 'eia.gov', 'EIA drilling productivity report data XLSX'),
        seed('https://www2.census.gov/programs-surveys/popest/tables/2020-2025/state/totals/NST-EST2025-POP.xlsx', 'xlsx', 'government-web', 'census.gov', 'Census state population estimates XLSX'),
        seed('https://www2.census.gov/programs-surveys/popest/tables/2020-2025/counties/totals/co-est2025-pop.xlsx', 'xlsx', 'government-web', 'census.gov', 'Census county population estimates XLSX'),
        seed('https://www2.census.gov/programs-surveys/popest/tables/2020-2025/state/totals/NST-EST2025-CHG.xlsx', 'xlsx', 'government-web', 'census.gov', 'Census state population change estimates XLSX'),
        seed('https://www2.census.gov/programs-surveys/popest/tables/2020-2025/state/totals/NST-EST2025-COMP.xlsx', 'xlsx', 'government-web', 'census.gov', 'Census state population components XLSX'),
        seed('https://www2.census.gov/programs-surveys/popest/tables/2020-2025/national/totals/NA-EST2025-POP.xlsx', 'xlsx', 'government-web', 'census.gov', 'Census national population estimates XLSX'),
        seed('https://www2.census.gov/programs-surveys/popest/tables/2020-2025/counties/totals/co-est2025-pop-25.xlsx', 'xlsx', 'government-web', 'census.gov', 'Census county population estimates Missouri XLSX'),
        seed('https://www2.census.gov/programs-surveys/popest/tables/2020-2025/counties/totals/co-est2025-pop-45.xlsx', 'xlsx', 'government-web', 'census.gov', 'Census county population estimates South Carolina XLSX'),
        seed('https://www2.census.gov/programs-surveys/popest/tables/2020-2025/counties/totals/co-est2025-comp-16.xlsx', 'xlsx', 'government-web', 'census.gov', 'Census county components Idaho XLSX'),
        seed('https://www2.census.gov/programs-surveys/popest/tables/2020-2025/counties/totals/co-est2025-chg-35.xlsx', 'xlsx', 'government-web', 'census.gov', 'Census county change New Mexico XLSX'),
        seed('https://www2.census.gov/programs-surveys/popest/tables/2020-2025/counties/totals/co-est2025-pop-48.xlsx', 'xlsx', 'government-web', 'census.gov', 'Census county population estimates Texas XLSX'),
        seed('https://raw.githubusercontent.com/eamena-project/eamena-arches-dev/main/dbs/database.eamena/data/reference_data/rm/hp/mds/mds-template-readonly.tsv', 'tsv', 'github-raw', 'eamena-project/eamena-arches-dev', 'EAMENA MDS template readonly TSV'),
        seed('https://raw.githubusercontent.com/vega/vega-datasets/master/data/unemployment.tsv', 'tsv', 'github-raw', 'vega/vega-datasets', 'Vega unemployment TSV dataset'),
        seed('https://raw.githubusercontent.com/plotly/datasets/master/Dash_Bio/Chromosomal/clustergram_mtcars.tsv', 'tsv', 'github-raw', 'plotly/datasets', 'Plotly Dash Bio mtcars TSV'),
        seed('https://raw.githubusercontent.com/plotly/datasets/master/global_super_store_orders.tsv', 'tsv', 'github-raw', 'plotly/datasets', 'Plotly global super store orders TSV'),
        seed('https://raw.githubusercontent.com/rstudio/r-community-survey/refs/heads/master/2020/data/2020-english-survey-final.tsv', 'tsv', 'github-raw', 'rstudio/r-community-survey', 'R community survey 2020 TSV'),
        seed('https://raw.githubusercontent.com/biopragmatics/bioregistry/main/exports/registry/registry.tsv', 'tsv', 'github-raw', 'biopragmatics/bioregistry', 'Bioregistry registry TSV'),
        seed('https://raw.githubusercontent.com/Oshlack/scRNA-tools/master/database/tools.tsv', 'tsv', 'github-raw', 'Oshlack/scRNA-tools', 'Single-cell RNA tools database TSV'),
        seed('https://raw.githubusercontent.com/nsgrantham/tidytuesdayrocks/master/data/tweets.tsv', 'tsv', 'github-raw', 'nsgrantham/tidytuesdayrocks', 'Tidy Tuesday tweets TSV'),
        seed('https://raw.githubusercontent.com/libjohn/openrefine/master/data/bicycle-subset-phm-collection.tsv', 'tsv', 'github-raw', 'libjohn/openrefine', 'Powerhouse museum bicycle collection TSV'),
        seed('https://raw.githubusercontent.com/NGSchoolEU/ngs22_registration_form/1cc647a3733e2c8a21b47aa497b4ca8c42457aa8/data/single-cell-studies.tsv', 'tsv', 'github-raw', 'NGSchoolEU/ngs22_registration_form', 'Single-cell studies TSV'),
        seed('https://raw.githubusercontent.com/IQSS/dataverse-docker/master/config/schemas/CESSDA_CMM.tsv', 'tsv', 'github-raw', 'IQSS/dataverse-docker', 'CESSDA CMM metadata schema TSV'),
        seed('https://raw.githubusercontent.com/nellore/runs/master/sra/hg19/biosample_tags.tsv', 'tsv', 'github-raw', 'nellore/runs', 'SRA hg19 biosample tags TSV'),
        seed('https://raw.githubusercontent.com/ecds/post45-datasets/main/graduations.tsv', 'tsv', 'github-raw', 'ecds/post45-datasets', 'Post45 graduations dataset TSV'),
        seed('https://raw.githubusercontent.com/ecds/post45-datasets/main/hathi_volumes.tsv', 'tsv', 'github-raw', 'ecds/post45-datasets', 'Post45 Hathi volumes dataset TSV'),
        seed('https://raw.githubusercontent.com/ecds/post45-datasets/main/nyt_titles.tsv', 'tsv', 'github-raw', 'ecds/post45-datasets', 'Post45 NYT titles dataset TSV'),
        seed('https://raw.githubusercontent.com/ecds/post45-datasets/main/hathitrust_prizewinners.tsv', 'tsv', 'github-raw', 'ecds/post45-datasets', 'Post45 HathiTrust prizewinners TSV'),
        seed('https://raw.githubusercontent.com/ecds/post45-datasets/main/pephtrccorpusdata.tsv', 'tsv', 'github-raw', 'ecds/post45-datasets', 'Post45 PEP HTRC corpus data TSV'),
        seed('https://raw.githubusercontent.com/ecds/post45-datasets/main/programerapeople.tsv', 'tsv', 'github-raw', 'ecds/post45-datasets', 'Post45 program era people TSV'),
        seed('https://raw.githubusercontent.com/ecds/post45-datasets/main/winnersandjudges.tsv', 'tsv', 'github-raw', 'ecds/post45-datasets', 'Post45 winners and judges TSV'),
        seed('https://raw.githubusercontent.com/rfordatascience/tidytuesday/main/data/2020/2020-08-25/chopped.tsv', 'tsv', 'github-raw', 'rfordatascience/tidytuesday', 'TidyTuesday Chopped dataset TSV'),
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

function seedUrlList(string $format, string $sourceKind, array $items): array
{
    $seeds = [];
    foreach ($items as [$url, $origin, $title]) {
        $seeds[] = seed($url, $format, $sourceKind, $origin, $title);
    }

    return $seeds;
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

function crossrefBiblatexSeedUrls(array $items): array
{
    $seeds = [];
    foreach ($items as [$doi, $label]) {
        $seeds[] = seed("https://api.crossref.org/works/{$doi}/transform/application/x-bibtex", 'biblatex', 'crossref-api', 'crossref.org', "Crossref BibLaTeX for {$label}");
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

function fletcherMmdPostSeedUrls(array $items): array
{
    $seeds = [];
    foreach ($items as [$slug, $title]) {
        $seeds[] = seed("https://fletcherpenney.net{$slug}.txt", 'markdown_mmd', 'author-web', 'fletcherpenney.net', $title);
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
    $summary = $db->query(<<<'SQL'
        SELECT
            (SELECT COUNT(*) FROM formats) AS formats,
            (SELECT COUNT(*) FROM candidates) AS candidates,
            (SELECT COUNT(*) FROM candidates WHERE status='queued') AS queued_candidates,
            (SELECT COUNT(*) FROM candidates WHERE status='failed') AS failed_candidates,
            (SELECT COUNT(*) FROM documents) AS documents,
            (SELECT COUNT(*) FROM renders WHERE status='ok') AS ok_renders,
            (SELECT COUNT(*) FROM renders WHERE status!='ok') AS failed_renders,
            (SELECT COUNT(*) FROM renders WHERE renderer='php-wordpress') AS wordpress_attempts,
            (SELECT COUNT(*) FROM renders WHERE renderer='php-wordpress' AND status='ok') AS wordpress_ok,
            (SELECT COUNT(*) FROM renders WHERE renderer='php-wordpress' AND status!='ok') AS wordpress_failed,
            (SELECT COUNT(*) FROM documents d WHERE NOT EXISTS (SELECT 1 FROM renders r WHERE r.document_id=d.id AND r.renderer='php-wordpress')) AS pending_wordpress,
            (SELECT COUNT(*) FROM comparisons) AS comparisons,
            (SELECT COUNT(*) FROM comparisons WHERE status='needs-review') AS needs_review,
            (SELECT COUNT(*) FROM (
                SELECT f.format
                FROM formats f
                LEFT JOIN documents d ON d.format=f.format
                GROUP BY f.format
                HAVING COUNT(d.id) < f.target_count
            )) AS formats_under_target
    SQL)->fetch(PDO::FETCH_ASSOC);

    $byFormat = $db->query(<<<'SQL'
        SELECT
            f.format,
            f.status,
            f.target_count,
            COUNT(DISTINCT c.id) AS candidates,
            COUNT(DISTINCT CASE WHEN c.status='queued' THEN c.id END) AS queued_candidates,
            COUNT(DISTINCT CASE WHEN c.status='failed' THEN c.id END) AS failed_candidates,
            COUNT(DISTINCT d.id) AS documents,
            COUNT(DISTINCT CASE WHEN wp.renderer='php-wordpress' THEN wp.document_id END) AS wordpress_attempts,
            COUNT(DISTINCT CASE WHEN wp.renderer='php-wordpress' AND wp.status='ok' THEN wp.document_id END) AS wordpress_ok,
            COUNT(DISTINCT CASE WHEN wp.renderer='php-wordpress' AND wp.status!='ok' THEN wp.document_id END) AS wordpress_failed,
            COUNT(DISTINCT cmp.id) AS comparisons,
            COUNT(DISTINCT CASE WHEN cmp.status='needs-review' THEN cmp.id END) AS needs_review
        FROM formats f
        LEFT JOIN candidates c ON c.format=f.format
        LEFT JOIN documents d ON d.format=f.format
        LEFT JOIN renders wp ON wp.document_id=d.id AND wp.renderer='php-wordpress'
        LEFT JOIN comparisons cmp ON cmp.document_id=d.id
        GROUP BY f.format
        ORDER BY f.format
    SQL)->fetchAll(PDO::FETCH_ASSOC);

    $comparisonRows = $db->query(<<<'SQL'
        SELECT d.id, d.format, d.title, d.url, d.local_path, cmp.status, cmp.reference_renderer, cmp.wordpress_renderer, cmp.metrics_json, cmp.notes
        FROM comparisons cmp
        JOIN documents d ON d.id=cmp.document_id
        ORDER BY d.format, d.id
    SQL)->fetchAll(PDO::FETCH_ASSOC);
    $comparisonInsights = comparisonInsights($comparisonRows);

    $failureRows = $db->query(<<<'SQL'
        SELECT d.id, d.format, d.title, d.url, d.local_path, r.renderer, r.error, r.metrics_json
        FROM renders r
        JOIN documents d ON d.id=r.document_id
        WHERE r.status!='ok'
        ORDER BY d.format, d.id, r.renderer
    SQL)->fetchAll(PDO::FETCH_ASSOC);
    $failureInsights = failureInsights($failureRows);

    return [
        'generatedAt' => gmdate('c'),
        'summary' => $summary,
        'byFormat' => enrichFormatRows($byFormat),
        'reviewQueue' => $comparisonInsights['reviewQueue'],
        'worstComparisons' => $comparisonInsights['worstComparisons'],
        'structureRisks' => $comparisonInsights['structureRisks'],
        'failureClusters' => $failureInsights['clusters'],
        'failureQueue' => $failureInsights['queue'],
        'needsReview' => array_slice($comparisonInsights['reviewQueue'], 0, 200),
        'failures' => array_slice($failureInsights['queue'], 0, 200),
    ];
}

function renderReportHtml(array $report): string
{
    $summaryCards = '';
    foreach (($report['summary'] ?? []) as $key => $value) {
        $summaryCards .= '<div class="card"><span>' . e((string) $key) . '</span><strong>' . e((string) $value) . '</strong></div>';
    }

    $rows = '';
    foreach ($report['byFormat'] as $row) {
        $rows .= '<tr><td>' . e((string) $row['format']) . '</td><td>' . e((string) $row['triage']) . '</td><td>' . (int) $row['target_count'] . '</td><td>' . (int) $row['documents'] . '</td><td>' . (int) $row['wordpress_ok'] . '</td><td>' . (int) $row['wordpress_failed'] . '</td><td>' . (int) $row['comparisons'] . '</td><td>' . (int) $row['needs_review'] . '</td></tr>';
    }

    $review = '';
    foreach (array_slice($report['reviewQueue'] ?? [], 0, 100) as $row) {
        $review .= '<li><strong>' . e((string) $row['format']) . ' #' . (int) $row['id'] . '</strong> '
            . '<a href="' . e((string) $row['url']) . '">' . e((string) $row['url']) . '</a> '
            . '<span class="pill">' . e((string) $row['triage']) . '</span> '
            . 'jaccard=' . e((string) ($row['textJaccard'] ?? '')) . ' coverage=' . e((string) ($row['referenceTextCoverage'] ?? ''))
            . ' ref=' . e((string) ($row['referencePath'] ?? '')) . ' wp=' . e((string) ($row['wordpressPath'] ?? '')) . '</li>';
    }

    $clusters = '';
    foreach (array_slice($report['failureClusters'] ?? [], 0, 80) as $cluster) {
        $clusters .= '<tr><td>' . e((string) $cluster['format']) . '</td><td>' . e((string) $cluster['renderer']) . '</td><td>' . e((string) $cluster['category']) . '</td><td>' . (int) $cluster['count'] . '</td><td>' . e((string) $cluster['sampleError']) . '</td></tr>';
    }

    $structure = '';
    foreach (array_slice($report['structureRisks'] ?? [], 0, 100) as $row) {
        $structure .= '<li><strong>' . e((string) $row['format']) . ' #' . (int) $row['id'] . '</strong> '
            . '<span class="pill">' . e(implode(', ', $row['risks'])) . '</span> '
            . '<a href="' . e((string) $row['url']) . '">' . e((string) $row['url']) . '</a></li>';
    }

    return '<!doctype html><meta charset="utf-8"><title>Document corpus report</title><style>body{font:14px system-ui,sans-serif;margin:24px;color:#17202a}h1,h2{margin:22px 0 10px}.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px}.card{border:1px solid #d8dee4;background:#f6f8fa;padding:8px}.card span{display:block;color:#57606a;font-size:12px}.card strong{font-size:20px}table{border-collapse:collapse;width:100%}td,th{border:1px solid #d8dee4;padding:6px;text-align:left;vertical-align:top}th{background:#f6f8fa}.pill{display:inline-block;border:1px solid #d8dee4;border-radius:999px;padding:1px 6px;background:#fff;margin:0 4px 2px 0}li{margin:6px 0}a{color:#0969da;word-break:break-all}</style><h1>Document corpus report</h1><div class="cards">' . $summaryCards . '</div><h2>By format</h2><table><tr><th>Format</th><th>Triage</th><th>Target</th><th>Documents</th><th>WP ok</th><th>WP failed</th><th>Comparisons</th><th>Needs review</th></tr>' . $rows . '</table><h2>Worst comparison queue</h2><ul>' . $review . '</ul><h2>Structure risks</h2><ul>' . $structure . '</ul><h2>Failure clusters</h2><table><tr><th>Format</th><th>Renderer</th><th>Category</th><th>Count</th><th>Sample error</th></tr>' . $clusters . '</table>';
}

function enrichFormatRows(array $rows): array
{
    foreach ($rows as &$row) {
        $documents = (int) $row['documents'];
        $target = (int) $row['target_count'];
        $wpFailed = (int) $row['wordpress_failed'];
        $needsReview = (int) $row['needs_review'];
        $row['target_met'] = $documents >= $target;
        $row['wordpress_success_rate'] = $documents === 0 ? 0.0 : round((int) $row['wordpress_ok'] / $documents, 4);
        $row['triage'] = formatTriage($documents, $target, $wpFailed, $needsReview);
    }
    unset($row);

    return $rows;
}

function formatTriage(int $documents, int $target, int $wpFailed, int $needsReview): string
{
    if ($documents < $target) {
        return 'coverage-gap';
    }
    if ($wpFailed > 0) {
        return 'conversion-failures';
    }
    if ($needsReview > 0) {
        return 'comparison-review';
    }

    return 'passing-sample';
}

function comparisonInsights(array $rows): array
{
    $reviewQueue = [];
    $structureRisks = [];
    foreach ($rows as $row) {
        $metrics = json_decode((string) $row['metrics_json'], true);
        if (!is_array($metrics)) {
            $metrics = [];
        }
        $item = [
            'id' => (int) $row['id'],
            'format' => (string) $row['format'],
            'title' => (string) ($row['title'] ?? ''),
            'url' => (string) $row['url'],
            'localPath' => (string) $row['local_path'],
            'referenceRenderer' => (string) $row['reference_renderer'],
            'wordpressRenderer' => (string) $row['wordpress_renderer'],
            'status' => (string) $row['status'],
            'textJaccard' => (float) ($metrics['textJaccard'] ?? 0),
            'referenceTextCoverage' => (float) ($metrics['referenceTextCoverage'] ?? 0),
            'triage' => comparisonTriage($metrics),
            'referencePath' => 'renders/' . (int) $row['id'] . '/reference.html',
            'wordpressPath' => 'renders/' . (int) $row['id'] . '/php-wordpress.html',
            'metrics' => $metrics,
            'notes' => (string) ($row['notes'] ?? ''),
        ];
        if ($row['status'] === 'needs-review') {
            $reviewQueue[] = $item;
        }
        $risks = structureRisks($metrics);
        if ($risks !== []) {
            $item['risks'] = $risks;
            $structureRisks[] = $item;
        }
    }

    usort($reviewQueue, static fn (array $a, array $b): int => [$a['referenceTextCoverage'], $a['textJaccard'], $a['id']] <=> [$b['referenceTextCoverage'], $b['textJaccard'], $b['id']]);
    usort($structureRisks, static fn (array $a, array $b): int => count($b['risks']) <=> count($a['risks']) ?: $a['id'] <=> $b['id']);

    return [
        'reviewQueue' => $reviewQueue,
        'worstComparisons' => array_slice($reviewQueue, 0, 50),
        'structureRisks' => $structureRisks,
    ];
}

function comparisonTriage(array $metrics): string
{
    $coverage = (float) ($metrics['referenceTextCoverage'] ?? 0);
    $jaccard = (float) ($metrics['textJaccard'] ?? 0);
    if ($coverage < 0.60) {
        return 'major-text-loss';
    }
    if ($coverage < 0.80) {
        return 'text-coverage-risk';
    }
    if ($jaccard < 0.82) {
        return 'semantic-drift-risk';
    }
    if (structureRisks($metrics) !== []) {
        return 'structure-risk';
    }

    return 'review-ok';
}

function structureRisks(array $metrics): array
{
    $risks = [];
    $reference = is_array($metrics['reference'] ?? null) ? $metrics['reference'] : [];
    $wordpress = is_array($metrics['wordpress'] ?? null) ? $metrics['wordpress'] : [];
    foreach (['tables', 'images', 'lists', 'headings', 'links'] as $key) {
        $left = (int) ($reference[$key] ?? 0);
        $right = (int) ($wordpress[$key] ?? 0);
        if ($left > $right) {
            $risks[] = "lost-{$key}:{$left}-to-{$right}";
        }
    }

    return $risks;
}

function failureInsights(array $rows): array
{
    $queue = [];
    $clusters = [];
    foreach ($rows as $row) {
        $error = trim((string) ($row['error'] ?? ''));
        $category = failureCategory((string) $row['renderer'], $error);
        $item = [
            'id' => (int) $row['id'],
            'format' => (string) $row['format'],
            'title' => (string) ($row['title'] ?? ''),
            'url' => (string) $row['url'],
            'localPath' => (string) $row['local_path'],
            'renderer' => (string) $row['renderer'],
            'category' => $category,
            'error' => errorExcerpt($error),
            'errorHash' => sha1($error),
            'likelyAction' => failureAction($category),
        ];
        $queue[] = $item;
        $key = $item['format'] . "\0" . $item['renderer'] . "\0" . $category . "\0" . normalizeFailureMessage($error);
        if (!isset($clusters[$key])) {
            $clusters[$key] = [
                'format' => $item['format'],
                'renderer' => $item['renderer'],
                'category' => $category,
                'count' => 0,
                'sampleError' => errorExcerpt($error),
                'sampleErrorHash' => sha1($error),
                'sampleDocumentId' => $item['id'],
                'sampleUrl' => $item['url'],
                'likelyAction' => $item['likelyAction'],
            ];
        }
        $clusters[$key]['count']++;
    }
    usort($queue, static fn (array $a, array $b): int => [$a['format'], $a['category'], $a['id']] <=> [$b['format'], $b['category'], $b['id']]);
    $clusters = array_values($clusters);
    usort($clusters, static fn (array $a, array $b): int => $b['count'] <=> $a['count'] ?: [$a['format'], $a['category']] <=> [$b['format'], $b['category']]);

    return ['queue' => $queue, 'clusters' => $clusters];
}

function failureCategory(string $renderer, string $error): string
{
    $lower = strtolower($error);
    if (str_contains($lower, 'allowed memory size') || str_contains($lower, 'fatal error')) {
        return 'resource-exhaustion';
    }
    if ($renderer === 'none') {
        return 'no-reference-renderer';
    }
    if (str_contains($lower, 'incompatible pandoc json api version')) {
        return 'input-version-gap';
    }
    if (str_contains($lower, 'must not declare a document type')) {
        return 'xml-safety-rejection';
    }
    if (str_contains($lower, 'entity') && str_contains($lower, 'not defined')) {
        return 'xml-entity-resolution-gap';
    }
    if (str_contains($lower, 'expected ') || str_contains($lower, 'unable to parse')) {
        return $renderer === 'php-wordpress' ? 'reader-parser-gap' : 'reference-parser-gap';
    }
    if (str_contains($lower, 'no reference renderer configured')) {
        return 'no-reference-renderer';
    }

    return $renderer === 'php-wordpress' ? 'conversion-failure' : 'reference-failure';
}

function failureAction(string $category): string
{
    return match ($category) {
        'no-reference-renderer' => 'Add or document a reference renderer before treating this as a conversion-quality signal.',
        'input-version-gap' => 'Decide whether to support older Pandoc JSON/native versions or classify them as unsupported input variants.',
        'xml-safety-rejection' => 'Keep safe XML defaults; add a sanitized parser path only if this format requires DTD-bearing organic files.',
        'xml-entity-resolution-gap' => 'Add safe entity handling or preprocessing for XML-like formats without enabling arbitrary external entities.',
        'resource-exhaustion' => 'Bound pathological table/layout work and add a regression that proves large organic inputs fail gracefully or complete.',
        'reader-parser-gap' => 'Improve the local reader for this syntax family and add a focused regression.',
        'reference-parser-gap' => 'Inspect the source and reference command before blaming WordPress output.',
        default => 'Inspect the rendered artifacts and source document.',
    };
}

function normalizeFailureMessage(string $error): string
{
    $message = preg_replace('/^\s*(?:#\d+\s+.*|Stack trace:)\s*$/m', '', $error) ?? $error;
    $message = preg_replace('/#\d+/', '#N', $message) ?? $message;
    $message = preg_replace('/line \d+, column \d+/i', 'line N, column N', $message) ?? $message;
    $message = preg_replace('/\d+(?:\.\d+)?/', 'N', $message) ?? $message;

    return mb_substr($message, 0, 180);
}

function errorExcerpt(string $error): string
{
    $error = trim($error);
    if ($error === '') {
        return '';
    }
    $lines = preg_split('/\r\n|\r|\n/', $error) ?: [$error];
    $lines = array_values(array_filter($lines, static fn (string $line): bool => !preg_match('/^\s*(?:#\d+\s+|Stack trace:)/', $line)));
    $excerpt = implode("\n", array_slice($lines, 0, 2));
    if (mb_strlen($excerpt) > 260) {
        $excerpt = mb_substr($excerpt, 0, 257) . '...';
    }
    if (count($lines) > 2) {
        $excerpt .= "\n...";
    }

    return $excerpt;
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

    $trimmed = ltrim(stripUtf8Bom(substr($bytes, 0, 4096)));
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
    if (in_array($format, ['docx', 'epub', 'odt', 'pptx', 'xlsx'], true) && !isZipLikeBytes($bytes)) {
        throw new RuntimeException("{$format} candidate is not a ZIP package");
    }
    if ($format === 'doc' && !str_starts_with($bytes, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1")) {
        throw new RuntimeException('doc candidate is not an OLE CFB document');
    }
    if ($format === 'pdf' && !str_starts_with($bytes, '%PDF-')) {
        throw new RuntimeException('pdf candidate is not a PDF document');
    }
    if ($format === 'rtf' && !str_starts_with(ltrim(substr($bytes, 0, 128)), '{\rtf')) {
        throw new RuntimeException('rtf candidate is not RTF content');
    }
}

function isZipLikeBytes(string $bytes): bool
{
    return str_starts_with($bytes, "PK\x03\x04")
        || str_starts_with($bytes, "PK\x05\x06")
        || str_starts_with($bytes, "PK\x07\x08");
}

function stripUtf8Bom(string $bytes): string
{
    return str_starts_with($bytes, "\xEF\xBB\xBF") ? substr($bytes, 3) : $bytes;
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
