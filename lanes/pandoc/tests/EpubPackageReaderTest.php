<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\EpubPackageReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$fixture = static fn (): string => dirname(__DIR__) . '/fixtures/epub3-package';
$writePackageFile = static function (string $root, string $relativePath, string $bytes): void {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new \RuntimeException('Unable to create EPUB fixture directory: ' . $directory);
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new \RuntimeException('Unable to write EPUB fixture file: ' . $path);
    }
};
$removeDirectory = static function (string $directory): void {
    if (!is_dir($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($directory);
};

return [
    'maps epub container opf manifest spine and metadata handoff' => static function (TestRunner $t) use ($fixture): void {
        $document = (new EpubPackageReader())->readDirectory($fixture());
        $meta = $document->attr('meta');
        $epub = $document->attr('epub');
        $manifest = $epub['manifestById'];
        $spine = $epub['spine'];

        $t->same('document', $document->type);
        $t->same('WordPress EPUB Import Packet', $meta['title']);
        $t->same(['Migration Team'], $meta['creators']);
        $t->same('en', $meta['language']);
        $t->same('urn:isbn:9780000000002', $meta['identifier']);
        $t->same('Port Libs Press', $meta['publisher']);
        $t->same('2026-06-09', $meta['date']);
        $t->same('EPUB/package.opf', $epub['containerRootfile']);
        $t->same('3.0', $epub['packageVersion']);
        $t->same('pub-id', $epub['uniqueIdentifierId']);
        $t->same(3, count($epub['metadataProperties']));
        $t->same('dcterms:modified', $epub['metadataProperties'][0]['property']);
        $t->same('2026-06-09T11:50:37Z', $epub['metadataProperties'][0]['value']);
        $t->same('file-as', $epub['metadataProperties'][2]['property']);
        $t->same('#creator', $epub['metadataProperties'][2]['refines']);
        $t->same(2, count($epub['metadataLinks']));
        $t->same('source-record', $epub['metadataLinks'][0]['id']);
        $t->same(['record'], $epub['metadataLinks'][0]['rel']);
        $t->same('chapter1.xhtml#opening-title', $epub['metadataLinks'][0]['href']);
        $t->same('EPUB/chapter1.xhtml', $epub['metadataLinks'][0]['path']);
        $t->same('opening-title', $epub['metadataLinks'][0]['fragment']);
        $t->same('application/xhtml+xml', $epub['metadataLinks'][0]['mediaType']);
        $t->same(['preview'], $epub['metadataLinks'][0]['properties']);
        $t->same('#pub-id', $epub['metadataLinks'][0]['refines']);
        $t->same(false, $epub['metadataLinks'][0]['external']);
        $t->same('remote-a11y', $epub['metadataLinks'][1]['id']);
        $t->same(['record', 'accessibility'], $epub['metadataLinks'][1]['rel']);
        $t->same('https://example.test/a11y/report.json', $epub['metadataLinks'][1]['path']);
        $t->same(true, $epub['metadataLinks'][1]['external']);
        $t->same(5, count($epub['manifest']));
        $t->same('nav.xhtml', $manifest['nav']['href']);
        $t->same('EPUB/nav.xhtml', $manifest['nav']['path']);
        $t->same('application/xhtml+xml', $manifest['nav']['mediaType']);
        $t->same(['nav'], $manifest['nav']['properties']);
        $t->same('EPUB/images/cover.png', $manifest['cover']['path']);
        $t->same(['cover-image'], $manifest['cover']['properties']);
        $t->same(2, count($spine));
        $t->same('chapter1', $spine[0]['idref']);
        $t->same('EPUB/chapter1.xhtml', $spine[0]['path']);
        $t->same(true, $spine[0]['linear']);
        $t->same('chapter2', $spine[1]['idref']);
        $t->same('EPUB/chapter2.xhtml', $spine[1]['path']);
        $t->same(true, $spine[1]['linear']);
    },
    'maps epub guide references for compact package review' => static function (TestRunner $t) use ($fixture): void {
        $document = (new EpubPackageReader())->readDirectory($fixture());
        $epub = $document->attr('epub');
        $guide = $epub['guide'];

        $t->same(true, $guide['present']);
        $t->same(4, $guide['itemCount']);
        $t->same(3, $guide['typedItemCount']);
        $t->same(1, $guide['missingTypeCount']);
        $t->same(['cover', 'text', 'glossary', 'appendix'], $guide['types']);
        $t->same(['cover' => 1, 'text' => 1, 'glossary' => 1, 'appendix' => 1], $guide['typeCounts']);
        $t->same(4, count($guide['items']));
        $t->same('cover', $guide['items'][0]['type']);
        $t->same(['cover'], $guide['items'][0]['types']);
        $t->same('Cover image', $guide['items'][0]['title']);
        $t->same('images/cover.png', $guide['items'][0]['href']);
        $t->same('EPUB/images/cover.png', $guide['items'][0]['path']);
        $t->same('', $guide['items'][0]['fragment']);
        $t->same(true, $guide['items'][0]['exists']);
        $t->same('cover', $guide['items'][0]['manifestId']);
        $t->same('image/png', $guide['items'][0]['mediaType']);
        $t->same([], $guide['items'][0]['diagnostics']);
        $t->same('EPUB/chapter1.xhtml', $guide['items'][1]['path']);
        $t->same('opening-title', $guide['items'][1]['fragment']);
        $t->same('Start reading', $guide['itemsByType']['text'][0]['title']);
        $t->same(['glossary', 'appendix'], $guide['items'][2]['types']);
        $t->same('EPUB/glossary.xhtml', $guide['items'][2]['path']);
        $t->same(false, $guide['items'][2]['exists']);
        $t->same('missing-guide-reference', $guide['items'][2]['diagnostics'][0]['type']);
        $t->same('Glossary', $guide['itemsByType']['appendix'][0]['title']);
        $t->same('', $guide['items'][3]['type']);
        $t->same('', $guide['items'][3]['typeRaw']);
        $t->same([], $guide['items'][3]['types']);
        $t->same('EPUB/chapter2.xhtml', $guide['items'][3]['path']);
        $t->same('details', $guide['items'][3]['fragment']);
        $t->same(true, $guide['items'][3]['exists']);
        $t->same('chapter2', $guide['items'][3]['manifestId']);
        $t->same('missing-guide-reference-type', $guide['items'][3]['diagnostics'][0]['type']);
        $t->same(2, $guide['diagnosticCount']);
        $t->same(2, $guide['diagnostics'][0]['index']);
        $t->same(3, $guide['diagnostics'][1]['index']);
    },
    'maps epub nav document and ncx fallback outlines' => static function (TestRunner $t) use ($fixture): void {
        $document = (new EpubPackageReader())->readDirectory($fixture());
        $epub = $document->attr('epub');
        $toc = array_values(array_filter(
            $epub['toc'],
            static fn (array $entry): bool => $entry['type'] === 'toc'
        ));
        $landmarks = array_values(array_filter(
            $epub['toc'],
            static fn (array $entry): bool => $entry['type'] === 'landmarks'
        ));
        $ncx = $epub['ncx'];

        $t->same(2, count($toc));
        $t->same('Opening Packet', $toc[0]['label']);
        $t->same('chapter1.xhtml', $toc[0]['href']);
        $t->same('EPUB/chapter1.xhtml', $toc[0]['path']);
        $t->same('', $toc[0]['fragment']);
        $t->same(1, count($toc[0]['children']));
        $t->same('Opening Note', $toc[0]['children'][0]['label']);
        $t->same('EPUB/chapter1.xhtml', $toc[0]['children'][0]['path']);
        $t->same('opening-note', $toc[0]['children'][0]['fragment']);
        $t->same('Details', $toc[1]['label']);
        $t->same('EPUB/chapter2.xhtml', $toc[1]['path']);
        $t->same('details', $toc[1]['fragment']);
        $t->same(2, count($landmarks));
        $t->same('Start', $landmarks[0]['label']);
        $t->same('EPUB/chapter1.xhtml', $landmarks[0]['path']);
        $t->same('Table of contents', $landmarks[1]['label']);
        $t->same('EPUB/nav.xhtml', $landmarks[1]['path']);
        $t->same(2, count($ncx));
        $t->same('Opening Packet', $ncx[0]['label']);
        $t->same(1, $ncx[0]['playOrder']);
        $t->same('EPUB/chapter1.xhtml', $ncx[0]['path']);
        $t->same(1, count($ncx[0]['children']));
        $t->same('Opening Note', $ncx[0]['children'][0]['label']);
        $t->same(2, $ncx[0]['children'][0]['playOrder']);
        $t->same('opening-note', $ncx[0]['children'][0]['fragment']);
        $t->same('Details', $ncx[1]['label']);
        $t->same(3, $ncx[1]['playOrder']);
        $t->same('details', $ncx[1]['fragment']);
    },
    'maps epub page-list navigation targets for print provenance' => static function (TestRunner $t) use ($fixture): void {
        $document = (new EpubPackageReader())->readDirectory($fixture());
        $epub = $document->attr('epub');
        $pageList = array_values(array_filter(
            $epub['toc'],
            static fn (array $entry): bool => $entry['type'] === 'page-list'
        ));

        $t->same(2, count($pageList));
        $t->same('1', $pageList[0]['label']);
        $t->same('chapter1.xhtml#opening-title', $pageList[0]['href']);
        $t->same('EPUB/chapter1.xhtml', $pageList[0]['path']);
        $t->same('opening-title', $pageList[0]['fragment']);
        $t->same('page-list', $pageList[0]['type']);
        $t->same(['label', 'href', 'path', 'fragment', 'type', 'children'], array_keys($pageList[0]));
        $t->same('2', $pageList[1]['label']);
        $t->same('EPUB/chapter2.xhtml', $pageList[1]['path']);
        $t->same('details', $pageList[1]['fragment']);

        $tocReport = $epub['tocReport'];
        $pageListReport = $tocReport['pageList'];
        $t->same(7, $tocReport['entryCount']);
        $t->same(['toc' => 3, 'landmarks' => 2, 'page-list' => 2], $tocReport['typeCounts']);
        $t->same(2, $tocReport['pageListEntryCount']);
        $t->same(2, $pageListReport['itemCount']);
        $t->same(2, $pageListReport['mappedItemCount']);
        $t->same(2, $pageListReport['linearTargetCount']);
        $t->same(0, $pageListReport['diagnosticCount']);
        $t->same('EPUB/chapter1.xhtml#opening-title', $pageListReport['items'][0]['target']);
        $t->same('chapter1', $pageListReport['items'][0]['manifestId']);
        $t->same([0], $pageListReport['items'][0]['spineIndexes']);
        $t->same([0], $pageListReport['items'][0]['linearSpineIndexes']);
        $t->same(0, $pageListReport['items'][0]['primarySpineIndex']);
        $t->same('EPUB/chapter2.xhtml#details', $pageListReport['items'][1]['target']);
        $t->same([1], $pageListReport['items'][1]['spineIndexes']);
        $t->same(1, $pageListReport['items'][1]['primarySpineIndex']);
    },
    'maps epub spine xhtml assets into shared ast and wordpress blocks' => static function (TestRunner $t) use ($fixture): void {
        $document = (new EpubPackageReader())->readDirectory($fixture());
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(7, count($document->children));
        $t->same(['heading', 'paragraph', 'blockquote', 'heading', 'paragraph', 'bullet_list', 'definition_list'], array_map(
            static fn (AstNode $node): string => $node->type,
            $document->children
        ));

        $heading = $document->children[0];
        $intro = $document->children[1];
        $quote = $document->children[2];
        $details = $document->children[4];
        $list = $document->children[5];
        $definitions = $document->children[6];

        $t->same(1, $heading->attr('level'));
        $t->same('opening-title', $heading->attr('id'));
        $t->same('Opening Packet', $heading->attr('text'));
        $t->same('paragraph', $intro->type);
        $t->same('Intro EPUB packet with details and Cover image.', $intro->attr('text'));
        $t->same(['text', 'strong', 'text', 'link', 'text', 'image', 'text'], array_map(
            static fn (AstNode $node): string => $node->type,
            $intro->children
        ));
        $t->same('EPUB', $intro->children[1]->children[0]->attr('text'));
        $t->same('EPUB/chapter2.xhtml#details', $intro->children[3]->attr('url'));
        $t->same('Details', $intro->children[3]->attr('title'));
        $t->same('details', $intro->children[3]->children[0]->attr('text'));
        $t->same('EPUB/images/cover.png', $intro->children[5]->attr('url'));
        $t->same('Cover image', $intro->children[5]->attr('alt'));
        $t->same('Cover', $intro->children[5]->attr('title'));
        $t->same('blockquote', $quote->type);
        $t->same('Reviewer note with wp_insert_post.', $quote->children[0]->attr('text'));
        $t->same('code', $quote->children[0]->children[1]->type);
        $t->same('wp_insert_post', $quote->children[0]->children[1]->attr('text'));
        $t->same(['text', 'emph', 'linebreak', 'text'], array_map(
            static fn (AstNode $node): string => $node->type,
            $details->children
        ));
        $t->same('reading order', $details->children[1]->children[0]->attr('text'));
        $t->same(2, count($list->children));
        $t->same('First migration check', $list->children[0]->children[0]->attr('text'));
        $t->same('Second check with source', $list->children[1]->children[0]->attr('text'));
        $t->same('https://example.test/source', $list->children[1]->children[0]->children[1]->attr('url'));
        $t->same('Review status Resource note', $definitions->attr('text'));
        $t->same('review-glossary', $definitions->attr('htmlAttributes')['id']);
        $t->same('migration-terms', $definitions->attr('htmlAttributes')['class']);
        $t->same(2, count($definitions->children));
        $t->same('Review status', $definitions->children[0]->attr('term'));
        $t->same('Ready for direct XHTML handoff.', $definitions->children[0]->children[1]->children[0]->attr('text'));
        $t->same('strong', $definitions->children[0]->children[1]->children[0]->children[1]->type);
        $t->same('Resource note', $definitions->children[1]->attr('term'));
        $t->same(true, $definitions->children[1]->children[1]->attr('loose'));
        $t->same('EPUB/chapter1.xhtml#opening-note', $definitions->children[1]->children[1]->children[0]->children[1]->attr('url'));
        $t->same('bullet_list', $definitions->children[1]->children[1]->children[1]->type);
        $t->contains('<h1 id="opening-title">Opening Packet</h1>', $blocks);
        $t->contains('<strong>EPUB</strong>', $blocks);
        $t->contains('<a href="EPUB/chapter2.xhtml#details" title="Details">details</a>', $blocks);
        $t->contains('<img src="EPUB/images/cover.png" alt="Cover image" title="Cover"/>', $blocks);
        $t->contains('<blockquote class="wp-block-quote"><p>Reviewer note with <code>wp_insert_post</code>.</p></blockquote>', $blocks);
        $t->contains('<em>reading order</em><br/>and a hard break.', $blocks);
        $t->contains('<li>First migration check</li><li>Second check with <a href="https://example.test/source">source</a></li>', $blocks);
        $t->contains('<dl id="review-glossary" class="migration-terms"><dt>Review status</dt><dd>Ready for <strong>direct XHTML</strong> handoff.</dd>', $blocks);
        $t->contains('<dt>Resource note</dt><dd><p>Keep package-local links like <a href="EPUB/chapter1.xhtml#opening-note">opening note</a> reviewable.</p><ul><li>Preserve nested checks.</li></ul></dd></dl>', $blocks);
    },
    'maps epub xhtml definition lists into shared ast and wordpress blocks' => static function (TestRunner $t) use ($fixture): void {
        $document = (new EpubPackageReader())->readDirectory($fixture());
        $blocks = (new WordPressBlockWriter())->write($document);
        $definitions = $document->children[6];
        $firstItem = $definitions->children[0];
        $secondItem = $definitions->children[1];

        $t->same('definition_list', $definitions->type);
        $t->same('review-glossary', $definitions->attr('htmlAttributes')['id']);
        $t->same('migration-terms', $definitions->attr('htmlAttributes')['class']);
        $t->same('Review status', $firstItem->attr('term'));
        $t->same('term', $firstItem->children[0]->type);
        $t->same('Ready for direct XHTML handoff.', $firstItem->children[1]->children[0]->attr('text'));
        $t->same('direct XHTML', $firstItem->children[1]->children[0]->children[1]->children[0]->attr('text'));
        $t->same('Resource note', $secondItem->attr('term'));
        $t->same(true, $secondItem->children[1]->attr('loose'));
        $t->same('EPUB/chapter1.xhtml#opening-note', $secondItem->children[1]->children[0]->children[1]->attr('url'));
        $t->same('Preserve nested checks.', $secondItem->children[1]->children[1]->children[0]->children[0]->attr('text'));
        $t->contains('<dl id="review-glossary" class="migration-terms"><dt>Review status</dt><dd>Ready for <strong>direct XHTML</strong> handoff.</dd>', $blocks);
        $t->contains('<dt>Resource note</dt><dd><p>Keep package-local links like <a href="EPUB/chapter1.xhtml#opening-note">opening note</a> reviewable.</p><ul><li>Preserve nested checks.</li></ul></dd></dl>', $blocks);
    },
    'reports direct package manifest suffixes and skipped spine entries' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-reader-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-manifest-review</dc:identifier>
    <dc:title>Manifest Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="appendix" href="appendix.xhtml?draft=review#appendix-start" media-type="application/xhtml+xml"/>
    <item id="remote" href="https://cdn.example.invalid/remote.xhtml?edition=review#remote" media-type="application/xhtml+xml"/>
    <item id="missing" href="missing.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
    <itemref idref="appendix" linear="no"/>
    <itemref idref="remote"/>
    <itemref idref="missing"/>
    <itemref idref="ghost"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Readable chapter.</p></body></html>');
            $writePackageFile($root, 'EPUB/appendix.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Nonlinear appendix.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $manifest = $epub['manifestById'];
            $manifestReport = $epub['manifestReport'];
            $spine = $epub['spine'];
            $spineReport = $epub['spineReport'];

            $t->same(1, count($document->children));
            $t->same('Readable chapter.', $document->children[0]->attr('text'));
            $t->same('EPUB/appendix.xhtml?draft=review#appendix-start', $manifest['appendix']['target']);
            $t->same('EPUB/appendix.xhtml', $manifest['appendix']['path']);
            $t->same(true, $manifest['appendix']['exists']);
            $t->same(true, $manifest['appendix']['hrefHasQuery']);
            $t->same('draft=review', $manifest['appendix']['hrefQuery']);
            $t->same(true, $manifest['appendix']['hrefHasFragment']);
            $t->same('appendix-start', $manifest['appendix']['hrefFragment']);
            $t->same(['manifest-href-query-component', 'manifest-href-fragment-component'], array_column($manifest['appendix']['diagnostics'], 'type'));

            $t->same(true, $manifest['remote']['external']);
            $t->same('https://cdn.example.invalid/remote.xhtml?edition=review#remote', $manifest['remote']['target']);
            $t->same('https://cdn.example.invalid/remote.xhtml', $manifest['remote']['path']);
            $t->same(false, $manifest['remote']['exists']);
            $t->same(['external-manifest-href-target', 'manifest-href-query-component', 'manifest-href-fragment-component'], array_column($manifest['remote']['diagnostics'], 'type'));
            $t->same(false, $manifest['missing']['exists']);
            $t->same(['missing-manifest-href-target'], array_column($manifest['missing']['diagnostics'], 'type'));

            $t->same(4, $manifestReport['itemCount']);
            $t->same(1, $manifestReport['externalItemCount']);
            $t->same(1, $manifestReport['missingItemCount']);
            $t->same(2, $manifestReport['hrefSuffixCount']);
            $t->same(6, $manifestReport['diagnosticCount']);
            $t->same(['appendix', 'remote'], array_column($manifestReport['hrefSuffixItems'], 'id'));
            $t->same(['manifest-href-query-component', 'manifest-href-fragment-component', 'external-manifest-href-target', 'manifest-href-query-component', 'manifest-href-fragment-component', 'missing-manifest-href-target'], array_column($manifestReport['diagnostics'], 'type'));

            $t->same(5, $spineReport['itemCount']);
            $t->same(4, $spineReport['linearItemCount']);
            $t->same(1, $spineReport['nonlinearItemCount']);
            $t->same(1, $spineReport['readableItemCount']);
            $t->same(4, $spineReport['skippedItemCount']);
            $t->same(1, $spineReport['externalItemCount']);
            $t->same(1, $spineReport['missingPackagePartItemCount']);
            $t->same(1, $spineReport['missingManifestItemCount']);
            $t->same(3, $spineReport['diagnosticCount']);
            $t->same('EPUB/appendix.xhtml?draft=review#appendix-start', $spine[1]['target']);
            $t->same(false, $spine[1]['readable']);
            $t->same('external-spine-item', $spine[2]['diagnostics'][0]['type']);
            $t->same('missing-spine-item-package-part', $spine[3]['diagnostics'][0]['type']);
            $t->same('missing-spine-manifest-item', $spine[4]['diagnostics'][0]['type']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports page-list target reading-order indexes and stable issue ordering' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-reader-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-page-list-review</dc:identifier>
    <dc:title>Page List Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="appendix" href="appendix.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
    <itemref idref="appendix" linear="no"/>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="page-list">
      <ol>
        <li><a href="chapter.xhtml#p1">1</a></li>
        <li><a href="appendix.xhtml#p2">2</a></li>
        <li><a href="orphan.xhtml#p3">3</a></li>
        <li><a href="chapter.xhtml#p1">1 repeat</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Chapter.</p></body></html>');
            $writePackageFile($root, 'EPUB/appendix.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Appendix.</p></body></html>');
            $writePackageFile($root, 'EPUB/orphan.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Orphan.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $spineReport = $epub['spineReport'];
            $tocReport = $epub['tocReport'];
            $pageList = $tocReport['pageList'];

            $t->same(['label', 'href', 'path', 'fragment', 'type', 'children'], array_keys($epub['toc'][0]));
            $t->same(1, $spineReport['duplicateIdrefGroupCount']);
            $t->same('chapter', $spineReport['duplicateIdrefItems'][0]['idref']);
            $t->same([0, 2], $spineReport['duplicateIdrefItems'][0]['indexes']);
            $t->same(4, $pageList['itemCount']);
            $t->same(3, $pageList['mappedItemCount']);
            $t->same(2, $pageList['linearTargetCount']);
            $t->same(1, $pageList['nonlinearTargetCount']);
            $t->same(1, $pageList['missingManifestTargetCount']);
            $t->same(2, $pageList['duplicateSpineIdrefTargetCount']);
            $t->same(2, $pageList['repeatedTargetCount']);
            $t->same(1, $pageList['repeatedTargetGroupCount']);

            $first = $pageList['items'][0];
            $t->same('EPUB/chapter.xhtml#p1', $first['target']);
            $t->same('chapter', $first['manifestId']);
            $t->same([0, 2], $first['spineIndexes']);
            $t->same(['chapter', 'chapter'], $first['spineIdrefs']);
            $t->same([0, 2], $first['linearSpineIndexes']);
            $t->same(0, $first['primarySpineIndex']);
            $t->same(true, $first['duplicateSpineIdref']);

            $nonlinear = $pageList['items'][1];
            $t->same('EPUB/appendix.xhtml#p2', $nonlinear['target']);
            $t->same([1], $nonlinear['spineIndexes']);
            $t->same([], $nonlinear['linearSpineIndexes']);
            $t->same([1], $nonlinear['nonlinearSpineIndexes']);
            $t->same(1, $nonlinear['primarySpineIndex']);
            $t->same(false, $nonlinear['linear']);

            $missingManifest = $pageList['items'][2];
            $t->same('EPUB/orphan.xhtml#p3', $missingManifest['target']);
            $t->same('', $missingManifest['manifestId']);
            $t->same(true, $missingManifest['exists']);
            $t->same([], $missingManifest['spineIndexes']);

            $repeated = $pageList['items'][3];
            $t->same('EPUB/chapter.xhtml#p1', $repeated['target']);
            $t->same(true, $repeated['repeatedTarget']);
            $t->same(2, $repeated['repeatedTargetCount']);
            $t->same([0, 3], array_column($pageList['itemsByTarget']['EPUB/chapter.xhtml#p1'], 'index'));
            $t->same([0, 3], array_column($pageList['itemsBySpineIndex'][0], 'index'));
            $t->same([1], array_column($pageList['itemsBySpineIndex'][1], 'index'));
            $t->same([0, 3], array_column($pageList['itemsBySpineIndex'][2], 'index'));
            $t->same([
                'page-list-target-duplicate-spine-idref',
                'page-list-target-nonlinear',
                'missing-page-list-manifest-target',
                'page-list-target-duplicate-spine-idref',
                'repeated-page-list-target',
            ], array_column($pageList['diagnostics'], 'type'));
            $t->same([0, 1, 2, 3, 3], array_column($pageList['diagnostics'], 'index'));
            $t->same($pageList['diagnostics'], $pageList['issues']);
            $t->same($tocReport['diagnostics'], $tocReport['issues']);
            $t->same(['page-list', 'page-list', 'page-list', 'page-list', 'page-list'], array_column($tocReport['diagnostics'], 'section'));
        } finally {
            $removeDirectory($root);
        }
    },
    'rejects missing epub package directories before parsing' => static function (TestRunner $t): void {
        $t->throws(\RuntimeException::class, static function (): void {
            (new EpubPackageReader())->readDirectory(dirname(__DIR__) . '/fixtures/missing-epub-package');
        });
    },
];
