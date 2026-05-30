<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteFts5Corpus;

$docs = [
    ['rowid' => 1, 'title' => 'Cache settings', 'body' => 'Cache plugin stores option values and refreshes cache entries quickly.'],
    ['rowid' => 2, 'title' => 'Search index', 'body' => 'Search plugin builds an index for posts, pages, and product content.'],
    ['rowid' => 3, 'title' => 'Media cleanup', 'body' => 'Media plugin removes stale thumbnails and scans upload folders.'],
    ['rowid' => 4, 'title' => 'Cache search bridge', 'body' => 'Search cache bridge links plugin search results to cached option metadata.'],
    ['rowid' => 5, 'title' => 'Order exports', 'body' => 'Commerce export tool writes product order archives.'],
];

$search = static fn (string $query, array $options = []): array => SQLiteFts5Corpus::search($docs, ['title', 'body'], $query, $options);

return [
    'tokenizes fts5 unicode61 style words case-insensitively' => static function (TestRunner $t): void {
        $t->same(['cache', 'plugin', 'área', '42'], SQLiteFts5Corpus::tokenize('Cache plugin, Área 42!'));
    },
    'extracts quoted fts5 phrase query tokens' => static function (TestRunner $t): void {
        $t->same(['cache', 'search', 'plugin'], SQLiteFts5Corpus::queryTokens('"cache search" plugin'));
    },
    'matches documents requiring every query token' => static function (TestRunner $t) use ($search): void {
        $rows = $search('cache plugin');
        $t->same([1, 4], array_column($rows, 'rowid'));
    },
    'sorts fts5 matches by ascending bm25 rank' => static function (TestRunner $t) use ($search): void {
        $rows = $search('search plugin');
        $t->same(4, $rows[0]['rowid']);
        $t->true($rows[0]['fts5_rank'] < $rows[1]['fts5_rank']);
    },
    'preserves stable source order for equal rank ties' => static function (TestRunner $t): void {
        $rows = SQLiteFts5Corpus::search([
            ['rowid' => 9, 'body' => 'alpha beta'],
            ['rowid' => 10, 'body' => 'alpha beta'],
        ], ['body'], 'alpha beta');
        $t->same([9, 10], array_column($rows, 'rowid'));
    },
    'weights title columns ahead of body columns for bm25 rank' => static function (TestRunner $t) use ($search): void {
        $rows = $search('search', ['columnWeights' => ['title' => 4.0, 'body' => 1.0]]);
        $t->same(4, $rows[0]['rowid']);
    },
    'returns fts5 match counts across all indexed columns' => static function (TestRunner $t) use ($search): void {
        $rows = $search('cache');
        $t->same([3, 2], array_column($rows, 'fts5_match_count'));
    },
    'builds snippet from the requested indexed column' => static function (TestRunner $t) use ($search): void {
        $rows = $search('metadata', ['snippetColumn' => 'body']);
        $t->same('...results to cached option <b>metadata</b>', $rows[0]['fts5_snippet']);
    },
    'uses custom snippet markers and ellipsis text' => static function (TestRunner $t) use ($search): void {
        $rows = $search('folders', ['snippetColumn' => 'body', 'start' => '[', 'end' => ']', 'ellipsis' => ' -- ', 'snippetTokens' => 4]);
        $t->same(' -- upload [folders]', $rows[0]['fts5_snippet']);
    },
    'clips snippets around the first match token' => static function (TestRunner $t) use ($search): void {
        $rows = $search('refreshes', ['snippetColumn' => 'body', 'snippetTokens' => 5]);
        $t->same('...and <b>refreshes</b> cache entries quickly', $rows[0]['fts5_snippet']);
    },
    'supports prefix query matching over indexed terms' => static function (TestRunner $t) use ($search): void {
        $rows = $search('plug', ['prefix' => true]);
        $t->same([3, 1, 2, 4], array_column($rows, 'rowid'));
    },
    'prefix snippets highlight the full matched token' => static function (TestRunner $t) use ($search): void {
        $rows = $search('thumb', ['prefix' => true, 'snippetColumn' => 'body']);
        $t->same('Media plugin removes stale <b>thumbnails</b> and scans upload folders', $rows[0]['fts5_snippet']);
    },
    'honors fts5 star suffix as a per token prefix query' => static function (TestRunner $t): void {
        $rows = SQLiteFts5Corpus::search([
            ['rowid' => 1, 'body' => 'plugin cache refresh'],
            ['rowid' => 2, 'body' => 'plugin cached refresh'],
            ['rowid' => 3, 'body' => 'plugins cache refresh'],
        ], ['body'], 'plugin cach*');
        $t->same([1, 2], array_column($rows, 'rowid'));
    },
    'keeps exact terms exact when another query term uses star prefix' => static function (TestRunner $t): void {
        $rows = SQLiteFts5Corpus::search([
            ['rowid' => 1, 'body' => 'plugin cache refresh'],
            ['rowid' => 2, 'body' => 'plugins cache refresh'],
            ['rowid' => 3, 'body' => 'plugin cached refresh'],
        ], ['body'], 'plugin cach*');
        $t->same([1, 3], array_column($rows, 'rowid'));
    },
    'counts only per token star prefix matches in fts5 match count' => static function (TestRunner $t): void {
        $rows = SQLiteFts5Corpus::search([
            ['rowid' => 1, 'body' => 'plugin plugins cache cached'],
            ['rowid' => 2, 'body' => 'plugin cache cached'],
        ], ['body'], 'plugin cach*');
        $t->same([3, 3], array_column($rows, 'fts5_match_count'));
    },
    'highlights only the per token prefix term in snippets' => static function (TestRunner $t): void {
        $rows = SQLiteFts5Corpus::search([
            ['rowid' => 1, 'body' => 'plugins keep cached values'],
            ['rowid' => 2, 'body' => 'plugin keeps cached values'],
        ], ['body'], 'plugin cach*', ['snippetColumn' => 'body']);
        $t->same('<b>plugin</b> keeps <b>cached</b> values', $rows[0]['fts5_snippet']);
    },
    'supports fts5 star suffix inside phrase queries' => static function (TestRunner $t): void {
        $rows = SQLiteFts5Corpus::search([
            ['rowid' => 1, 'body' => 'cache refreshes quickly'],
            ['rowid' => 2, 'body' => 'cache metadata refreshes quickly'],
            ['rowid' => 3, 'body' => 'cached refreshes quickly'],
        ], ['body'], 'cache refresh*', ['phrase' => true]);
        $t->same([1], array_column($rows, 'rowid'));
    },
    'extracts fts5 query tokens without exposing star suffix metadata' => static function (TestRunner $t): void {
        $t->same(['plugin', 'cach', 'refresh'], SQLiteFts5Corpus::queryTokens('plugin cach* refresh'));
    },
    'supports phrase matching when adjacent terms are required' => static function (TestRunner $t) use ($search): void {
        $rows = $search('cache bridge', ['phrase' => true]);
        $t->same([4], array_column($rows, 'rowid'));
    },
    'rejects non-adjacent phrase matches' => static function (TestRunner $t) use ($search): void {
        $rows = $search('cache entries', ['phrase' => true]);
        $t->same([1], array_column($rows, 'rowid'));
    },
    'treats null indexed values as empty text' => static function (TestRunner $t): void {
        $rows = SQLiteFts5Corpus::search([
            ['rowid' => 1, 'title' => null, 'body' => 'plugin option'],
            ['rowid' => 2, 'title' => 'plugin', 'body' => null],
        ], ['title', 'body'], 'plugin');
        $t->same([2, 1], array_column($rows, 'rowid'));
    },
    'ignores unindexed columns during fts5 matching' => static function (TestRunner $t): void {
        $rows = SQLiteFts5Corpus::search([
            ['rowid' => 1, 'title' => 'cache', 'hidden' => 'search'],
            ['rowid' => 2, 'title' => 'media', 'hidden' => 'cache'],
        ], ['title'], 'search');
        $t->same([], $rows);
    },
    'keeps original row columns in fts5 results' => static function (TestRunner $t) use ($search): void {
        $rows = $search('commerce');
        $t->same('Order exports', $rows[0]['title']);
        $t->same('Commerce export tool writes product order archives.', $rows[0]['body']);
    },
    'annotates result rows with original source index' => static function (TestRunner $t) use ($search): void {
        $rows = $search('commerce');
        $t->same(4, $rows[0]['fts5_source_index']);
    },
    'returns an empty rowset when no fts5 document matches' => static function (TestRunner $t) use ($search): void {
        $t->same([], $search('missing token'));
    },
    'handles numeric tokens in copied option search text' => static function (TestRunner $t): void {
        $rows = SQLiteFts5Corpus::search([
            ['rowid' => 1, 'body' => 'release 42 cache'],
            ['rowid' => 2, 'body' => 'release 43 search'],
        ], ['body'], '42');
        $t->same([1], array_column($rows, 'rowid'));
    },
    'requires at least one indexed column' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteFts5Corpus::search([], [], 'cache'));
    },
    'requires at least one query token' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteFts5Corpus::search([], ['body'], '!!!'));
    },
    'requires snippet column to be indexed' => static function (TestRunner $t) use ($search): void {
        $t->throws(InvalidArgumentException::class, static fn () => $search('cache', ['snippetColumn' => 'missing']));
    },
    'requires numeric fts5 column weights' => static function (TestRunner $t) use ($search): void {
        $t->throws(InvalidArgumentException::class, static fn () => $search('cache', ['columnWeights' => ['title' => 'heavy']]));
    },
    'requires positive snippet token limits' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteFts5Corpus::snippet('cache plugin', ['cache'], false, '<b>', '</b>', '...', 0));
    },
];
