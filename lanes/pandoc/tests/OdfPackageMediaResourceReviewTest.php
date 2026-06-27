<?php

declare(strict_types=1);

use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Media resource package review.</text:p>
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
    <dc:title>Media Resource Review</dc:title>
  </office:meta>
</office:document-meta>
XML;

$audioBytes = 'AUDIODATA';
$videoBytes = 'VIDEODATA';
$conflictAudioBytes = 'AUDIO-IN-PICTURES';
$conflictVideoBytes = 'VIDEO-IN-PNG';
$replacementBytes = 'REPLACEMENT-AUDIO';
$thumbnailBytes = 'THUMBNAIL-VIDEO';

$audioSize = strlen($audioBytes);
$videoSize = strlen($videoBytes);
$conflictAudioSize = strlen($conflictAudioBytes);
$conflictVideoSize = strlen($conflictVideoBytes);
$replacementSize = strlen($replacementBytes);
$thumbnailSize = strlen($thumbnailBytes);

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:media-type="application/vnd.oasis.opendocument.text" manifest:full-path="/" manifest:version="1.3"/>
  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml"/>
  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="styles.xml"/>
  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="meta.xml"/>
  <manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png" manifest:size="7"/>
  <manifest:file-entry manifest:media-type="audio/ogg; codecs=&quot;opus&quot;" manifest:full-path="Media/narration.ogg" manifest:size="{$audioSize}"/>
  <manifest:file-entry manifest:media-type="video/mp4" manifest:full-path="Media/clip.mp4" manifest:size="{$videoSize}"/>
  <manifest:file-entry manifest:media-type="audio/ogg" manifest:full-path="Pictures/sound.ogg" manifest:size="{$conflictAudioSize}"/>
  <manifest:file-entry manifest:media-type="video/mp4" manifest:full-path="Media/poster.png" manifest:size="{$conflictVideoSize}"/>
  <manifest:file-entry manifest:media-type="video/mp4" manifest:full-path="Media/missing.mp4"/>
  <manifest:file-entry manifest:media-type="audio/ogg" manifest:full-path="ObjectReplacements/sound.ogg" manifest:size="{$replacementSize}"/>
  <manifest:file-entry manifest:media-type="video/mp4" manifest:full-path="Thumbnails/poster.png" manifest:size="{$thumbnailSize}"/>
</manifest:manifest>
XML;

$package = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'Media/narration.ogg', 'data' => $audioBytes, 'compressionMethod' => 0],
    ['name' => 'Media/clip.mp4', 'data' => $videoBytes, 'compressionMethod' => 0],
    ['name' => 'Pictures/sound.ogg', 'data' => $conflictAudioBytes, 'compressionMethod' => 0],
    ['name' => 'Media/poster.png', 'data' => $conflictVideoBytes, 'compressionMethod' => 0],
    ['name' => 'ObjectReplacements/sound.ogg', 'data' => $replacementBytes, 'compressionMethod' => 0],
    ['name' => 'Thumbnails/poster.png', 'data' => $thumbnailBytes, 'compressionMethod' => 0],
], 'compact odt media resource review');

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        $value = $item[$key] ?? null;
        if (is_string($value) && $value !== '') {
            $indexed[$value] = $item;
        }
    }

    return $indexed;
};

return [
    'summarizes compact ODT media-resource package role precedence' => static function (TestRunner $t) use ($package, $indexBy): void {
        $summary = OpenDocumentPackage::fromPackage($package())->summarize();
        $mediaResources = $summary['manifestReview']['mediaResources'];
        $resourceItemsByPart = $indexBy($mediaResources['items'], 'part');
        $reviewByPath = $indexBy($summary['manifestReview']['items'], 'path');
        $inventory = $summary['packageInventory']['parts'];

        $t->same([
            'Pictures/hero.png',
            'Media/narration.ogg',
            'Media/clip.mp4',
            'Pictures/sound.ogg',
            'Media/poster.png',
            'Media/missing.mp4',
        ], array_column($summary['mediaParts'], 'path'));
        $t->same(5, $summary['packageInventory']['mediaResourcePartCount']);
        $t->same(5, $summary['packageInventory']['roleCounts']['media-resource']);

        $t->same(8, $mediaResources['manifestDeclaredCount']);
        $t->same(6, $mediaResources['mediaResourceCount']);
        $t->same(5, $mediaResources['mediaResourceExistingCount']);
        $t->same(1, $mediaResources['mediaResourceMissingCount']);
        $t->same(5, $mediaResources['mediaResourceCanExposeCount']);
        $t->same(7, $mediaResources['existingCount']);
        $t->same(1, $mediaResources['missingCount']);
        $t->same(['image' => 1, 'audio' => 3, 'video' => 4, 'other' => 0], $mediaResources['familyCounts']);
        $t->same([
            'audio/ogg' => 3,
            'image/png' => 1,
            'video/mp4' => 4,
        ], $mediaResources['mediaTypeBaseCounts']);
        $t->same(3, $mediaResources['roleConflictCount']);
        $t->same(2, $mediaResources['resourceRoleConflictCount']);
        $t->same(3, $mediaResources['pathMediaTypeConflictCount']);
        $t->same(['Pictures/sound.ogg', 'Media/poster.png', 'Thumbnails/poster.png'], array_column($mediaResources['pathMediaTypeConflictItems'], 'part'));
        $t->same([
            [
                'pathMediaFamily' => 'image',
                'declaredMediaFamily' => 'audio',
                'count' => 1,
                'parts' => ['Pictures/sound.ogg'],
            ],
            [
                'pathMediaFamily' => 'image',
                'declaredMediaFamily' => 'video',
                'count' => 2,
                'parts' => ['Media/poster.png', 'Thumbnails/poster.png'],
            ],
        ], $mediaResources['pathMediaTypeConflictPairs']);
        $t->same(1, $mediaResources['missingMediaResourceItemCount']);
        $t->same(['Media/missing.mp4'], array_column($mediaResources['missingMediaResourceItems'], 'part'));
        $t->same(2, $mediaResources['packageRolePrecedenceCount']);
        $t->same([
            'odf-media-resource-missing-package-part' => 1,
            'odf-media-resource-package-role-precedence' => 2,
            'odf-media-resource-role-conflict' => 3,
        ], $mediaResources['issueCodeCounts']);

        $narration = $resourceItemsByPart['Media/narration.ogg'];
        $t->same(['manifest-media-type', 'package-extension'], $narration['roleSources']);
        $t->same('audio', $narration['declaredMediaFamily']);
        $t->same('audio', $narration['packagePathMediaFamily']);
        $t->same(false, $narration['roleConflict']);
        $t->same(true, $narration['mediaResource']);
        $t->same(true, $narration['canExposeBytes']);

        $pictureAudio = $resourceItemsByPart['Pictures/sound.ogg'];
        $t->same(['manifest-media-type', 'pictures-path'], $pictureAudio['roleSources']);
        $t->same('audio', $pictureAudio['declaredMediaFamily']);
        $t->same('image', $pictureAudio['packagePathMediaFamily']);
        $t->same(true, $pictureAudio['roleConflict']);
        $t->same('image:audio', $pictureAudio['pathMediaTypeConflictPair']);
        $t->same(['odf-media-resource-role-conflict'], $pictureAudio['issues']);

        $missing = $resourceItemsByPart['Media/missing.mp4'];
        $t->same(false, $missing['exists']);
        $t->same(false, $missing['canExposeBytes']);
        $t->same(['odf-media-resource-missing-package-part'], $missing['issues']);

        $replacement = $resourceItemsByPart['ObjectReplacements/sound.ogg'];
        $thumbnail = $resourceItemsByPart['Thumbnails/poster.png'];
        $t->same(false, $replacement['mediaResource']);
        $t->same(['object-replacement'], $replacement['packageRolePrecedence']);
        $t->same(['odf-media-resource-package-role-precedence'], $replacement['issues']);
        $t->same(false, $thumbnail['mediaResource']);
        $t->same(['package-thumbnail'], $thumbnail['packageRolePrecedence']);
        $t->same(['odf-media-resource-role-conflict', 'odf-media-resource-package-role-precedence'], $thumbnail['issues']);

        $t->same(false, in_array('ObjectReplacements/sound.ogg', array_column($summary['mediaParts'], 'path'), true));
        $t->same(false, in_array('Thumbnails/poster.png', array_column($summary['mediaParts'], 'path'), true));
        $t->same(false, in_array('media-resource', $inventory['ObjectReplacements/sound.ogg']['roles'], true));
        $t->same(false, in_array('media-resource', $inventory['Thumbnails/poster.png']['roles'], true));
        $t->same(true, $reviewByPath['ObjectReplacements/sound.ogg']['objectReplacementPackagePart']);
        $t->same(true, $reviewByPath['Thumbnails/poster.png']['canExposeBytes']);
    },
];
