<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>XML comment provenance packet.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  office:version="1.3">
  <office:styles>
    <style:style style:name="BodyText" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  office:version="1.3">
  <office:meta>
    <dc:title>XML Comment Packet</dc:title>
  </office:meta>
</office:document-meta>
XML;

return [
    'summarizes ODF package XML comments without exposing text' => static function (TestRunner $t) use ($contentXml, $stylesXml, $metaXml): void {
        $reviewPrologComment = 'hidden-payload-alpha review packet state';
        $reviewInnerComment = 'hidden-payload-beta nested review value';
        $auditComment = 'hidden-payload-gamma audit marker';
        $looseComment = 'hidden-payload-delta loose sidecar';
        $manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="META-INF/comment-review.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="META-INF/audit-comments.xml" manifest:media-type="application/xml; profile=comments"/>
</manifest:manifest>
XML;
        $reviewXml = <<<XML
<!--{$reviewPrologComment}-->
<review:packet xmlns:review="urn:odf-comment-review">
  <review:value><!--{$reviewInnerComment}-->safe</review:value>
</review:packet>
XML;
        $auditXml = <<<XML
<audit:state xmlns:audit="urn:odf-comment-audit">
  <audit:item><!--{$auditComment}--><audit:value>safe</audit:value></audit:item>
</audit:state>
XML;
        $looseXml = <<<XML
<loose:packet xmlns:loose="urn:odf-comment-loose">
  <!--{$looseComment}-->
  <loose:value>safe</loose:value>
</loose:packet>
XML;

        $buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
            ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
            ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
            ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
            ['name' => 'META-INF/comment-review.xml', 'data' => $reviewXml, 'compressionMethod' => 0],
            ['name' => 'META-INF/audit-comments.xml', 'data' => $auditXml, 'compressionMethod' => 0],
            ['name' => 'META-INF/loose-comment.xml', 'data' => $looseXml, 'compressionMethod' => 0],
        ], 'odf xml comment provenance');

        $compact = OpenDocumentPackage::fromPackage($buildPackage())->summarize()['packageInventory'];
        $rich = (new OdfReader())->readPackage($buildPackage())['importReport']['manifest']['packageProvenance'];
        $expectedPartNames = ['META-INF/audit-comments.xml', 'META-INF/comment-review.xml', 'META-INF/loose-comment.xml'];
        $expectedByteLength = strlen($reviewPrologComment) + strlen($reviewInnerComment) + strlen($auditComment) + strlen($looseComment);

        foreach (['compact' => $compact, 'rich' => $rich] as $label => $inventory) {
            $parts = $inventory['parts'];
            $reviewPart = $parts['META-INF/comment-review.xml'];
            $auditPart = $parts['META-INF/audit-comments.xml'];
            $loosePart = $parts['META-INF/loose-comment.xml'];
            $commentsByPartAndPath = [];
            foreach ($inventory['packagePartXmlComments'] as $comment) {
                $commentsByPartAndPath[$comment['partName'] . ' ' . $comment['parentPath']] = $comment;
            }

            $t->same(3, $inventory['packagePartXmlCommentPartCount'], "{$label} comment part count");
            $t->same(4, $inventory['packagePartXmlCommentCount'], "{$label} comment count");
            $t->same($expectedByteLength, $inventory['packagePartXmlCommentByteLength'], "{$label} comment byte length");
            $t->same($expectedPartNames, $inventory['packagePartXmlCommentPartNames'], "{$label} comment part names");
            $t->same(false, $inventory['packagePartXmlCommentsTruncated'], "{$label} comment summary not truncated");

            $t->same(2, $reviewPart['xmlCommentCount'], "{$label} review comment count");
            $t->same(strlen($reviewPrologComment) + strlen($reviewInnerComment), $reviewPart['xmlCommentByteLength'], "{$label} review byte length");
            $t->same(false, $reviewPart['xmlCommentsTruncated'], "{$label} review comments not truncated");
            $t->same('/', $reviewPart['xmlComments'][0]['parentPath'], "{$label} review prolog parent path");
            $t->same(0, $reviewPart['xmlComments'][0]['parentDepth'], "{$label} review prolog parent depth");
            $t->same(strlen($reviewPrologComment), $reviewPart['xmlComments'][0]['byteLength'], "{$label} review prolog length");
            $t->same(sprintf('%08x', crc32($reviewPrologComment)), $reviewPart['xmlComments'][0]['crc32'], "{$label} review prolog crc32");
            $t->same(hash('sha256', $reviewPrologComment), $reviewPart['xmlComments'][0]['sha256'], "{$label} review prolog sha256");
            $t->same('/review:packet/review:value', $reviewPart['xmlComments'][1]['parentPath'], "{$label} review inner parent path");
            $t->same(2, $reviewPart['xmlComments'][1]['parentDepth'], "{$label} review inner parent depth");
            $t->same(hash('sha256', $reviewInnerComment), $reviewPart['xmlComments'][1]['sha256'], "{$label} review inner sha256");

            $t->same(1, $auditPart['xmlCommentCount'], "{$label} audit comment count");
            $t->same('/audit:state/audit:item', $auditPart['xmlComments'][0]['parentPath'], "{$label} audit parent path");
            $t->same(2, $auditPart['xmlComments'][0]['parentDepth'], "{$label} audit parent depth");
            $t->same(hash('sha256', $auditComment), $auditPart['xmlComments'][0]['sha256'], "{$label} audit sha256");

            $t->same(1, $loosePart['xmlCommentCount'], "{$label} loose comment count");
            $t->same('/loose:packet', $loosePart['xmlComments'][0]['parentPath'], "{$label} loose parent path");
            $t->same(1, $loosePart['xmlComments'][0]['parentDepth'], "{$label} loose parent depth");
            $t->same(hash('sha256', $looseComment), $loosePart['xmlComments'][0]['sha256'], "{$label} loose sha256");

            $t->same('/', $commentsByPartAndPath['META-INF/comment-review.xml /']['parentPath'], "{$label} summary review prolog path");
            $t->same('/review:packet/review:value', $commentsByPartAndPath['META-INF/comment-review.xml /review:packet/review:value']['parentPath'], "{$label} summary review inner path");
            $t->same('/audit:state/audit:item', $commentsByPartAndPath['META-INF/audit-comments.xml /audit:state/audit:item']['parentPath'], "{$label} summary audit path");
            $t->same('/loose:packet', $commentsByPartAndPath['META-INF/loose-comment.xml /loose:packet']['parentPath'], "{$label} summary loose path");

            $t->true(!isset($reviewPart['xmlComments'][0]['text']), "{$label} raw XML comment text should not be exposed");
            $encodedComments = json_encode([
                $reviewPart['xmlComments'],
                $auditPart['xmlComments'],
                $loosePart['xmlComments'],
                $inventory['packagePartXmlComments'],
            ]);
            $t->true(is_string($encodedComments), "{$label} XML comment metadata should encode for review");
            $t->true(!str_contains((string) $encodedComments, 'hidden-payload'), "{$label} raw XML comment text should not appear in metadata");
        }
    },
];
