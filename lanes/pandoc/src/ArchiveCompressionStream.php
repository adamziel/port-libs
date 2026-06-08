<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class ArchiveCompressionStream
{
    public const FORMAT_TAR = 'tar';
    public const FORMAT_GZIP_TAR = 'gzip-tar';
    public const FORMAT_ZLIB_TAR = 'zlib-tar';
    public const FORMAT_RAW_DEFLATE_TAR = 'raw-deflate-tar';
    public const FORMAT_LZ4_TAR = 'lz4-tar';
    public const FORMAT_ZIP = 'zip';
    public const FORMAT_GZIP_ZIP = 'gzip-zip';
    public const FORMAT_ZLIB_ZIP = 'zlib-zip';
    public const FORMAT_RAW_DEFLATE_ZIP = 'raw-deflate-zip';
    public const FORMAT_LZ4_ZIP = 'lz4-zip';
    public const PACKAGE_KIND_TAR = 'tar';
    public const PACKAGE_KIND_ZIP = 'zip';

    /**
     * @return list<string>
     */
    public static function supportedTarFormats(): array
    {
        return [
            self::FORMAT_TAR,
            self::FORMAT_GZIP_TAR,
            self::FORMAT_ZLIB_TAR,
            self::FORMAT_RAW_DEFLATE_TAR,
            self::FORMAT_LZ4_TAR,
        ];
    }

    /**
     * @return list<string>
     */
    public static function supportedZipFormats(): array
    {
        return [
            self::FORMAT_ZIP,
            self::FORMAT_GZIP_ZIP,
            self::FORMAT_ZLIB_ZIP,
            self::FORMAT_RAW_DEFLATE_ZIP,
            self::FORMAT_LZ4_ZIP,
        ];
    }

    public static function openTar(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): TarArchive {
        $tarBytes = self::decodeTarBytes($bytes, $format, $maxUncompressedBytes);

        return TarArchive::fromString($tarBytes, $maxUnpackedBytes);
    }

    public static function openTarAuto(
        string $bytes,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): TarArchive {
        return self::detectTarCandidate($bytes, $maxUncompressedBytes, $maxUnpackedBytes)['archive'];
    }

    public static function detectTarFormat(
        string $bytes,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): string {
        return self::detectTarCandidate($bytes, $maxUncompressedBytes, $maxUnpackedBytes)['format'];
    }

    public static function decodeTarBytesAuto(
        string $bytes,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): string {
        return self::detectTarCandidate($bytes, $maxUncompressedBytes, $maxUnpackedBytes)['tarBytes'];
    }

    public static function openZip(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): ZipPackage {
        return ZipPackage::fromString(self::decodeZipBytes($bytes, $format, $maxUncompressedBytes));
    }

    public static function openZipAuto(
        string $bytes,
        ?int $maxUncompressedBytes = null
    ): ZipPackage {
        return self::detectZipCandidate($bytes, $maxUncompressedBytes)['package'];
    }

    public static function detectZipFormat(
        string $bytes,
        ?int $maxUncompressedBytes = null
    ): string {
        return self::detectZipCandidate($bytes, $maxUncompressedBytes)['format'];
    }

    public static function decodeZipBytesAuto(
        string $bytes,
        ?int $maxUncompressedBytes = null
    ): string {
        return self::detectZipCandidate($bytes, $maxUncompressedBytes)['zipBytes'];
    }

    public static function detectPackageKindAuto(
        string $bytes,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): string {
        return self::detectPackageCandidate($bytes, $maxUncompressedBytes, $maxUnpackedBytes)['kind'];
    }

    /**
     * @return array{
     *     rootKind:string,
     *     rootFormat:string,
     *     rootEntryCount:int,
     *     maxDepth:int,
     *     policy:string,
     *     candidateCount:int,
     *     packageCount:int,
     *     diagnosticCount:int,
     *     entries:list<array<string, mixed>>
     * }
     */
    public static function inspectNestedPackageStreamsAuto(
        string $bytes,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null,
        int $maxDepth = 1
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        self::assertLimit($maxUnpackedBytes, 'archive stream max unpacked byte limit');
        if ($maxDepth < 0) {
            throw new \RuntimeException('Nested archive inspection max depth must not be negative');
        }

        $root = self::detectPackageCandidate($bytes, $maxUncompressedBytes, $maxUnpackedBytes);
        $entries = [];
        if ($maxDepth > 0) {
            self::collectNestedPackageEntries(
                $root,
                '',
                1,
                $maxDepth,
                $maxUncompressedBytes,
                $maxUnpackedBytes,
                $entries
            );
        }

        $packageCount = 0;
        $diagnosticCount = 0;
        foreach ($entries as $entry) {
            if (($entry['status'] ?? null) === 'package') {
                $packageCount++;
            }

            if (($entry['diagnostics'] ?? []) !== []) {
                $diagnosticCount++;
            }
        }

        return [
            'rootKind' => $root['kind'],
            'rootFormat' => $root['format'],
            'rootEntryCount' => count(self::candidateEntryNames($root)),
            'maxDepth' => $maxDepth,
            'policy' => 'metadata-only-no-extraction',
            'candidateCount' => count($entries),
            'packageCount' => $packageCount,
            'diagnosticCount' => $diagnosticCount,
            'entries' => $entries,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inspectPackageStreamAuto(
        string $bytes,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): array {
        $candidate = self::detectPackageCandidate($bytes, $maxUncompressedBytes, $maxUnpackedBytes);

        if ($candidate['kind'] === self::PACKAGE_KIND_TAR) {
            return [
                'kind' => self::PACKAGE_KIND_TAR,
            ] + self::tarStreamInspection(
                $bytes,
                $candidate['format'],
                $candidate['tarBytes'],
                $candidate['archive'],
                $maxUncompressedBytes
            );
        }

        return [
            'kind' => self::PACKAGE_KIND_ZIP,
        ] + self::zipStreamInspection(
            $bytes,
            $candidate['format'],
            $candidate['zipBytes'],
            $candidate['package'],
            $maxUncompressedBytes
        );
    }

    /**
     * @param array<int|string, string> $dictionaries
     * @return array<string, mixed>
     */
    public static function inspectPackageStreamWithZlibDictionaries(
        string $bytes,
        string $format,
        array $dictionaries,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): array {
        return match ($format) {
            self::FORMAT_ZLIB_TAR => [
                'kind' => self::PACKAGE_KIND_TAR,
            ] + self::inspectTarStreamWithZlibDictionaries(
                $bytes,
                $format,
                $dictionaries,
                $maxUncompressedBytes,
                $maxUnpackedBytes
            ),
            self::FORMAT_ZLIB_ZIP => [
                'kind' => self::PACKAGE_KIND_ZIP,
            ] + self::inspectZipStreamWithZlibDictionaries(
                $bytes,
                $format,
                $dictionaries,
                $maxUncompressedBytes
            ),
            default => throw new \RuntimeException("ZLIB dictionary package inspection requires a ZLIB archive stream format: {$format}"),
        };
    }

    /**
     * @param array<int|string, string> $dictionaries
     * @return array<string, mixed>
     */
    public static function inspectPackageStreamWithLz4Dictionaries(
        string $bytes,
        string $format,
        array $dictionaries,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): array {
        return match ($format) {
            self::FORMAT_LZ4_TAR => [
                'kind' => self::PACKAGE_KIND_TAR,
            ] + self::inspectTarStreamWithLz4Dictionaries(
                $bytes,
                $format,
                $dictionaries,
                $maxUncompressedBytes,
                $maxUnpackedBytes
            ),
            self::FORMAT_LZ4_ZIP => [
                'kind' => self::PACKAGE_KIND_ZIP,
            ] + self::inspectZipStreamWithLz4Dictionaries(
                $bytes,
                $format,
                $dictionaries,
                $maxUncompressedBytes
            ),
            default => throw new \RuntimeException("LZ4 dictionary package inspection requires an LZ4 archive stream format: {$format}"),
        };
    }

    /**
     * @return array{
     *     kind:string,
     *     format:string,
     *     compressedSize:int,
     *     decodedPackageSize:int,
     *     entryUncompressedSize:int,
     *     entryCount:int,
     *     streamCompressionRatio:float,
     *     packageExpansionRatio:float,
     *     totalExpansionRatio:float,
     *     maxStreamCompressionRatio:float,
     *     maxPackageExpansionRatio:float,
     *     maxTotalExpansionRatio:float,
     *     diagnosticCount:int,
     *     diagnostics:list<string>,
     *     handoffPolicy:string,
     *     extractionPolicy:string,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectArchiveBombPolicyAuto(
        string $bytes,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null,
        float $maxStreamCompressionRatio = 100.0,
        float $maxPackageExpansionRatio = 100.0,
        float $maxTotalExpansionRatio = 100.0
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        self::assertLimit($maxUnpackedBytes, 'archive stream max unpacked byte limit');
        self::assertPositiveRatio($maxStreamCompressionRatio, 'archive stream compression-ratio threshold');
        self::assertPositiveRatio($maxPackageExpansionRatio, 'archive package expansion-ratio threshold');
        self::assertPositiveRatio($maxTotalExpansionRatio, 'archive total expansion-ratio threshold');

        $candidate = self::detectPackageCandidate($bytes, $maxUncompressedBytes, $maxUnpackedBytes);
        $kind = $candidate['kind'];
        $format = $candidate['format'];
        $compressedSize = strlen($bytes);

        if ($kind === self::PACKAGE_KIND_TAR) {
            $archive = $candidate['archive'];
            if (!$archive instanceof TarArchive) {
                throw new \RuntimeException('Detected TAR archive candidate is missing archive metadata');
            }

            $decodedPackageSize = strlen($candidate['tarBytes']);
            $entryUncompressedSize = self::archiveUnpackedSize($archive);
            $entryCount = count($archive->names());
        } else {
            $package = $candidate['package'];
            if (!$package instanceof ZipPackage) {
                throw new \RuntimeException('Detected ZIP package candidate is missing package metadata');
            }

            $decodedPackageSize = strlen($candidate['zipBytes']);
            $entryUncompressedSize = self::zipPackageUncompressedSize($package);
            $entryCount = count($package->names());
        }

        $streamCompressionRatio = self::expansionRatio($decodedPackageSize, $compressedSize);
        $packageExpansionRatio = self::expansionRatio($entryUncompressedSize, $decodedPackageSize);
        $totalExpansionRatio = self::expansionRatio($entryUncompressedSize, $compressedSize);
        $diagnostics = [];
        if ($streamCompressionRatio > $maxStreamCompressionRatio) {
            $diagnostics[] = 'archive-stream-compression-ratio-exceeds-threshold';
        }

        if ($packageExpansionRatio > $maxPackageExpansionRatio) {
            $diagnostics[] = 'archive-package-expansion-ratio-exceeds-threshold';
        }

        if ($totalExpansionRatio > $maxTotalExpansionRatio) {
            $diagnostics[] = 'archive-total-expansion-ratio-exceeds-threshold';
        }

        return [
            'kind' => $kind,
            'format' => $format,
            'compressedSize' => $compressedSize,
            'decodedPackageSize' => $decodedPackageSize,
            'entryUncompressedSize' => $entryUncompressedSize,
            'entryCount' => $entryCount,
            'streamCompressionRatio' => $streamCompressionRatio,
            'packageExpansionRatio' => $packageExpansionRatio,
            'totalExpansionRatio' => $totalExpansionRatio,
            'maxStreamCompressionRatio' => $maxStreamCompressionRatio,
            'maxPackageExpansionRatio' => $maxPackageExpansionRatio,
            'maxTotalExpansionRatio' => $maxTotalExpansionRatio,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
            'handoffPolicy' => $diagnostics === [] ? 'within-thresholds' : 'review-before-conversion',
            'extractionPolicy' => 'metadata-only-no-extraction',
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     type:string,
     *     compressedSize:int,
     *     frameCount:int,
     *     dataFrameCount:int,
     *     skippableFrameCount:int,
     *     dictionaryFrameCount:int,
     *     extractionPolicy:string,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectLz4DictionaryPolicy(string $bytes): array
    {
        $policy = Lz4Frame::dictionaryPolicyPreflight($bytes);

        return [
            'format' => 'lz4',
            'type' => 'lz4-dictionary-policy',
            'compressedSize' => strlen($bytes),
            'frameCount' => $policy['frameCount'],
            'dataFrameCount' => $policy['dataFrameCount'],
            'skippableFrameCount' => $policy['skippableFrameCount'],
            'dictionaryFrameCount' => $policy['dictionaryFrameCount'],
            'extractionPolicy' => $policy['extractionPolicy'],
            'stream' => $policy,
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     type:string,
     *     compressedSize:int,
     *     dictionaryStreamCount:int,
     *     extractionPolicy:string,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectZlibPresetDictionaryPolicy(string $bytes): array
    {
        $policy = DeflateStream::presetDictionaryPolicyPreflight($bytes);

        return [
            'format' => DeflateStream::FORMAT_ZLIB,
            'type' => 'zlib-preset-dictionary-policy',
            'compressedSize' => strlen($bytes),
            'dictionaryStreamCount' => $policy['dictionaryStreamCount'],
            'extractionPolicy' => $policy['extractionPolicy'],
            'stream' => $policy,
        ];
    }

    /**
     * @return array{
     *     type:string,
     *     format:string,
     *     sourceName:?string,
     *     candidateKind:?string,
     *     candidateFormat:?string,
     *     compressedSize:int,
     *     signatureMatched:bool,
     *     signatureName:?string,
     *     signatureBytesHex:?string,
     *     streamHeaderSize:?int,
     *     streamFlagsHex:?string,
     *     blockSize100k:?int,
     *     handoffPolicy:string,
     *     extractionPolicy:string,
     *     diagnostics:list<string>
     * }
     */
    public static function inspectUnsupportedCompressionStreamPolicy(string $bytes, ?string $sourceName = null): array
    {
        $signature = self::unsupportedCompressionSignature($bytes);
        $nameCandidate = $sourceName === null ? null : self::unsupportedCompressionNameCandidate($sourceName);

        if ($signature === null && $nameCandidate === null) {
            throw new \RuntimeException('Unsupported archive compression policy requires a BZip2 or XZ stream signature or source name');
        }

        $format = $signature['format'] ?? $nameCandidate['format'];
        $candidateKind = $nameCandidate['kind'] ?? null;
        $diagnostics = [
            'archive-compression-format-unsupported',
            'archive-compression-format-' . $format . '-not-decoded',
            'archive-external-decompressor-not-run',
            'archive-package-bytes-not-exposed',
        ];

        if ($signature === null) {
            $diagnostics[] = 'archive-compression-signature-unverified';
        } elseif ($nameCandidate !== null && $nameCandidate['format'] !== $signature['format']) {
            $diagnostics[] = 'archive-compression-signature-source-name-mismatch';
        }

        return [
            'type' => 'unsupported-archive-compression-stream',
            'format' => $format,
            'sourceName' => $sourceName,
            'candidateKind' => $candidateKind,
            'candidateFormat' => $candidateKind === null ? null : $format . '-' . $candidateKind,
            'compressedSize' => strlen($bytes),
            'signatureMatched' => $signature !== null,
            'signatureName' => $signature['name'] ?? null,
            'signatureBytesHex' => $signature['signatureBytesHex'] ?? null,
            'streamHeaderSize' => $signature['streamHeaderSize'] ?? null,
            'streamFlagsHex' => $signature['streamFlagsHex'] ?? null,
            'blockSize100k' => $signature['blockSize100k'] ?? null,
            'handoffPolicy' => 'review-before-conversion',
            'extractionPolicy' => 'unsupported-compression-stream-blocked',
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     tarBytes:string,
     *     archive:TarArchive,
     *     entryNames:list<string>,
     *     entryCount:int,
     *     regularFileCount:int,
     *     directoryCount:int,
     *     uncompressedSize:int,
     *     unpackedSize:int,
     *     endMarkerOffset:int,
     *     trailingZeroBytes:int,
     *     entryLayouts:list<array{
     *         name:string,
     *         type:string,
     *         size:int,
     *         mode:int,
     *         modifiedAt:int,
     *         accessedAt:?int,
     *         changedAt:?int,
     *         createdAt:?int,
     *         uid:int,
     *         gid:int,
     *         userName:string,
     *         groupName:string,
     *         paxHeaderCount:int,
     *         paxHeaderKeys:list<string>,
     *         paxGlobalHeaderCount:int,
     *         paxGlobalHeaderKeys:list<string>,
     *         paxLocalHeaderCount:int,
     *         paxLocalHeaderKeys:list<string>,
     *         paxDeletedHeaderKeys:list<string>,
     *         nameSource:string,
     *         gnuLongName:?string,
     *         headerOffset:int,
     *         dataOffset:int,
     *         dataEndOffset:int,
     *         paddedDataSize:int,
     *         recordSize:int,
     *         decodedSourceSegmentCount:int,
     *         decodedSourceSegments:list<array{
     *             sourceType:string,
     *             sourceIndex:int,
     *             sourceLabel:?string,
     *             sourceDecodedOffset:int,
     *             sourceDecodedEndOffset:int,
     *             entryRecordOffset:int,
     *             entryRecordEndOffset:int
     *         }>
     *     }>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectTarStream(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        self::assertLimit($maxUnpackedBytes, 'archive stream max unpacked byte limit');

        $tarBytes = self::decodeTarBytes($bytes, $format, $maxUncompressedBytes);
        $archive = TarArchive::fromString($tarBytes, $maxUnpackedBytes);

        return self::tarStreamInspection($bytes, $format, $tarBytes, $archive, $maxUncompressedBytes);
    }

    /**
     * @return array{
     *     format:string,
     *     tarBytes:string,
     *     archive:TarArchive,
     *     entryNames:list<string>,
     *     entryCount:int,
     *     regularFileCount:int,
     *     directoryCount:int,
     *     uncompressedSize:int,
     *     unpackedSize:int,
     *     endMarkerOffset:int,
     *     trailingZeroBytes:int,
     *     entryLayouts:list<array{
     *         name:string,
     *         type:string,
     *         size:int,
     *         mode:int,
     *         modifiedAt:int,
     *         accessedAt:?int,
     *         changedAt:?int,
     *         createdAt:?int,
     *         uid:int,
     *         gid:int,
     *         userName:string,
     *         groupName:string,
     *         paxHeaderCount:int,
     *         paxHeaderKeys:list<string>,
     *         paxGlobalHeaderCount:int,
     *         paxGlobalHeaderKeys:list<string>,
     *         paxLocalHeaderCount:int,
     *         paxLocalHeaderKeys:list<string>,
     *         paxDeletedHeaderKeys:list<string>,
     *         nameSource:string,
     *         gnuLongName:?string,
     *         headerOffset:int,
     *         dataOffset:int,
     *         dataEndOffset:int,
     *         paddedDataSize:int,
     *         recordSize:int,
     *         decodedSourceSegmentCount:int,
     *         decodedSourceSegments:list<array{
     *             sourceType:string,
     *             sourceIndex:int,
     *             sourceLabel:?string,
     *             sourceDecodedOffset:int,
     *             sourceDecodedEndOffset:int,
     *             entryRecordOffset:int,
     *             entryRecordEndOffset:int
     *         }>
     *     }>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectTarStreamAuto(
        string $bytes,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): array {
        $candidate = self::detectTarCandidate($bytes, $maxUncompressedBytes, $maxUnpackedBytes);

        return self::tarStreamInspection(
            $bytes,
            $candidate['format'],
            $candidate['tarBytes'],
            $candidate['archive'],
            $maxUncompressedBytes
        );
    }

    /**
     * @param array<int|string, string> $dictionaries
     * @return array<string, mixed>
     */
    public static function inspectTarStreamWithZlibDictionaries(
        string $bytes,
        string $format,
        array $dictionaries,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        self::assertLimit($maxUnpackedBytes, 'archive stream max unpacked byte limit');
        if ($format !== self::FORMAT_ZLIB_TAR) {
            throw new \RuntimeException("ZLIB dictionary TAR inspection requires ZLIB TAR stream format: {$format}");
        }

        $metadata = DeflateStream::inspectZlibWithDictionaries($bytes, $dictionaries, $maxUncompressedBytes);
        $tarBytes = $metadata['data'];
        $archive = TarArchive::fromString($tarBytes, $maxUnpackedBytes);

        return self::tarStreamInspection(
            $bytes,
            $format,
            $tarBytes,
            $archive,
            $maxUncompressedBytes,
            self::zlibDictionaryStreamInspection($bytes, $metadata)
        );
    }

    /**
     * @param array<int|string, string> $dictionaries
     * @return array<string, mixed>
     */
    public static function inspectTarStreamWithLz4Dictionaries(
        string $bytes,
        string $format,
        array $dictionaries,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        self::assertLimit($maxUnpackedBytes, 'archive stream max unpacked byte limit');
        if ($format !== self::FORMAT_LZ4_TAR) {
            throw new \RuntimeException("LZ4 dictionary TAR inspection requires LZ4 TAR stream format: {$format}");
        }

        $dictionaryMap = self::normalizeLz4ExternalDictionaries($dictionaries);
        $frames = Lz4Frame::framesWithDictionaries($bytes, $dictionaryMap, $maxUncompressedBytes);
        $tarBytes = self::decodedLz4DataFromFrames($frames);
        $archive = TarArchive::fromString($tarBytes, $maxUnpackedBytes);

        return self::tarStreamInspection(
            $bytes,
            $format,
            $tarBytes,
            $archive,
            $maxUncompressedBytes,
            self::lz4DictionaryStreamInspection($bytes, $frames, $dictionaryMap)
        );
    }

    /**
     * @return array{
     *     format:string,
     *     tarBytes:string,
     *     uncompressedSize:int,
     *     entryCount:int,
     *     linkEntryCount:int,
     *     hardLinkCount:int,
     *     symbolicLinkCount:int,
     *     extractionPolicy:string,
     *     entries:list<array{
     *         name:string,
     *         linkType:string,
     *         linkTarget:string,
     *         linkTargetSource:string,
     *         targetEntryExists:bool,
     *         nameSource:string,
     *         headerOffset:int,
     *         dataOffset:int,
     *         policy:string,
     *         diagnostics:list<string>
     *     }>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectTarLinkPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $tarBytes = self::decodeTarBytes($bytes, $format, $maxUncompressedBytes);
        $policy = TarArchive::linkPolicyPreflight($tarBytes);

        return [
            'format' => $format,
            'tarBytes' => $tarBytes,
            'uncompressedSize' => strlen($tarBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     tarBytes:string,
     *     uncompressedSize:int,
     *     entryCount:int,
     *     specialFileEntryCount:int,
     *     characterDeviceCount:int,
     *     blockDeviceCount:int,
     *     fifoCount:int,
     *     extractionPolicy:string,
     *     entries:list<array{
     *         name:string,
     *         specialType:string,
     *         typeFlag:string,
     *         deviceMajor:?int,
     *         deviceMinor:?int,
     *         deviceNumberSource:string,
     *         payloadSize:int,
     *         nameSource:string,
     *         headerOffset:int,
     *         dataOffset:int,
     *         policy:string,
     *         diagnostics:list<string>
     *     }>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectTarSpecialFilePolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $tarBytes = self::decodeTarBytes($bytes, $format, $maxUncompressedBytes);
        $policy = TarArchive::specialFilePolicyPreflight($tarBytes);

        return [
            'format' => $format,
            'tarBytes' => $tarBytes,
            'uncompressedSize' => strlen($tarBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     tarBytes:string,
     *     uncompressedSize:int,
     *     entryCount:int,
     *     sparseEntryCount:int,
     *     extractionPolicy:string,
     *     entries:list<array{
     *         name:string,
     *         sparseType:string,
     *         sparseHeaderFamilies:list<string>,
     *         sparseHeaderKeys:list<string>,
     *         realSize:?int,
     *         sparseMapSource:?string,
     *         sparseMapSegments:list<array{offset:int, length:int, endOffset:int}>,
     *         sparseMapSegmentCount:int,
     *         sparseMapPayloadBytes:int,
     *         payloadSize:int,
     *         nameSource:string,
     *         headerOffset:int,
     *         dataOffset:int,
     *         policy:string,
     *         diagnostics:list<string>
     *     }>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectTarSparsePolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $tarBytes = self::decodeTarBytes($bytes, $format, $maxUncompressedBytes);
        $policy = TarArchive::sparsePolicyPreflight($tarBytes);

        return [
            'format' => $format,
            'tarBytes' => $tarBytes,
            'uncompressedSize' => strlen($tarBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     tarBytes:string,
     *     uncompressedSize:int,
     *     entryCount:int,
     *     multiVolumeEntryCount:int,
     *     typeflagEntryCount:int,
     *     paxMetadataEntryCount:int,
     *     extractionPolicy:string,
     *     entries:list<array{
     *         name:string,
     *         multiVolumeType:string,
     *         volumeHeaderFamilies:list<string>,
     *         volumeHeaderKeys:list<string>,
     *         continuationOffset:?int,
     *         continuationOffsetSource:?string,
     *         originalName:?string,
     *         declaredVolumeSize:?int,
     *         payloadSize:int,
     *         nameSource:string,
     *         headerOffset:int,
     *         dataOffset:int,
     *         policy:string,
     *         diagnostics:list<string>
     *     }>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectTarMultiVolumePolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $tarBytes = self::decodeTarBytes($bytes, $format, $maxUncompressedBytes);
        $policy = TarArchive::multiVolumePolicyPreflight($tarBytes);

        return [
            'format' => $format,
            'tarBytes' => $tarBytes,
            'uncompressedSize' => strlen($tarBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     tarBytes:string,
     *     uncompressedSize:int,
     *     entryCount:int,
     *     incrementalEntryCount:int,
     *     typeflagEntryCount:int,
     *     paxMetadataEntryCount:int,
     *     dumpdirRecordCount:int,
     *     deletedRecordCount:int,
     *     directoryRecordCount:int,
     *     extractionPolicy:string,
     *     entries:list<array{
     *         name:string,
     *         incrementalType:string,
     *         incrementalHeaderFamilies:list<string>,
     *         incrementalHeaderKeys:list<string>,
     *         dumpdirRecordCount:int,
     *         deletedRecordCount:int,
     *         directoryRecordCount:int,
     *         dumpdirRecords:list<array{source:string, marker:string, name:string, action:string, raw:string}>,
     *         payloadSize:int,
     *         nameSource:string,
     *         headerOffset:int,
     *         dataOffset:int,
     *         policy:string,
     *         diagnostics:list<string>
     *     }>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectTarIncrementalSnapshotPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $tarBytes = self::decodeTarBytes($bytes, $format, $maxUncompressedBytes);
        $policy = TarArchive::incrementalSnapshotPolicyPreflight($tarBytes);

        return [
            'format' => $format,
            'tarBytes' => $tarBytes,
            'uncompressedSize' => strlen($tarBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     tarBytes:string,
     *     uncompressedSize:int,
     *     entryCount:int,
     *     paxEntryCount:int,
     *     duplicatePaxEntryCount:int,
     *     duplicateKeywordCount:int,
     *     duplicateRecordCount:int,
     *     extractionPolicy:string,
     *     entries:list<array{
     *         paxEntryName:string,
     *         paxType:string,
     *         headerOffset:int,
     *         dataOffset:int,
     *         payloadSize:int,
     *         recordCount:int,
     *         duplicateKeywordCount:int,
     *         duplicateRecordCount:int,
     *         duplicateKeywords:list<string>,
     *         duplicateRecords:list<array{keyword:string, occurrences:int, values:list<string>, firstValue:string, duplicateValues:list<string>}>,
     *         policy:string,
     *         diagnostics:list<string>
     *     }>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectTarPaxDuplicateKeywordPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $tarBytes = self::decodeTarBytes($bytes, $format, $maxUncompressedBytes);
        $policy = TarArchive::paxDuplicateKeywordPreflight($tarBytes);

        return [
            'format' => $format,
            'tarBytes' => $tarBytes,
            'uncompressedSize' => strlen($tarBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     tarBytes:string,
     *     uncompressedSize:int,
     *     entryCount:int,
     *     filesystemMetadataEntryCount:int,
     *     metadataRecordCount:int,
     *     extendedAttributeRecordCount:int,
     *     accessControlListRecordCount:int,
     *     fileFlagRecordCount:int,
     *     extractionPolicy:string,
     *     entries:list<array{
     *         name:string,
     *         nameSource:string,
     *         headerOffset:int,
     *         dataOffset:int,
     *         metadataRecordCount:int,
     *         extendedAttributeRecordCount:int,
     *         accessControlListRecordCount:int,
     *         fileFlagRecordCount:int,
     *         metadataKeys:list<string>,
     *         records:list<array{keyword:string, category:string, name:string, source:string, value:string}>,
     *         policy:string,
     *         diagnostics:list<string>
     *     }>,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectTarFilesystemMetadataPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $tarBytes = self::decodeTarBytes($bytes, $format, $maxUncompressedBytes);
        $policy = TarArchive::paxFilesystemMetadataPolicyPreflight($tarBytes);

        return [
            'format' => $format,
            'tarBytes' => $tarBytes,
            'uncompressedSize' => strlen($tarBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     zipBytes:string,
     *     package:ZipPackage,
     *     entryNames:list<string>,
     *     entryCount:int,
     *     packageByteSize:int,
     *     entryUncompressedSize:int,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectZipStream(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $zipBytes = self::decodeZipBytes($bytes, $format, $maxUncompressedBytes);
        $package = ZipPackage::fromString($zipBytes);

        return self::zipStreamInspection($bytes, $format, $zipBytes, $package, $maxUncompressedBytes);
    }

    /**
     * @return array<string, mixed>
     */
    public static function inspectZipEncryptionPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $zipBytes = self::decodeZipBytes($bytes, $format, $maxUncompressedBytes);
        $policy = ZipPackage::encryptionPolicyPreflight($zipBytes);

        return [
            'format' => $format,
            'zipBytes' => $zipBytes,
            'packageByteSize' => strlen($zipBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inspectZipCompressionMethodPolicy(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $zipBytes = self::decodeZipBytes($bytes, $format, $maxUncompressedBytes);
        $policy = ZipPackage::compressionMethodPolicyPreflight($zipBytes);

        return [
            'format' => $format,
            'zipBytes' => $zipBytes,
            'packageByteSize' => strlen($zipBytes),
        ] + $policy + [
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     zipBytes:string,
     *     package:ZipPackage,
     *     entryNames:list<string>,
     *     entryCount:int,
     *     packageByteSize:int,
     *     entryUncompressedSize:int,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectZipStreamAuto(
        string $bytes,
        ?int $maxUncompressedBytes = null
    ): array {
        $candidate = self::detectZipCandidate($bytes, $maxUncompressedBytes);

        return self::zipStreamInspection(
            $bytes,
            $candidate['format'],
            $candidate['zipBytes'],
            $candidate['package'],
            $maxUncompressedBytes
        );
    }

    /**
     * @param array<int|string, string> $dictionaries
     * @return array<string, mixed>
     */
    public static function inspectZipStreamWithZlibDictionaries(
        string $bytes,
        string $format,
        array $dictionaries,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        if ($format !== self::FORMAT_ZLIB_ZIP) {
            throw new \RuntimeException("ZLIB dictionary ZIP inspection requires ZLIB ZIP stream format: {$format}");
        }

        $metadata = DeflateStream::inspectZlibWithDictionaries($bytes, $dictionaries, $maxUncompressedBytes);
        $zipBytes = $metadata['data'];
        $package = ZipPackage::fromString($zipBytes);

        return self::zipStreamInspection(
            $bytes,
            $format,
            $zipBytes,
            $package,
            $maxUncompressedBytes,
            self::zlibDictionaryStreamInspection($bytes, $metadata)
        );
    }

    /**
     * @param array<int|string, string> $dictionaries
     * @return array<string, mixed>
     */
    public static function inspectZipStreamWithLz4Dictionaries(
        string $bytes,
        string $format,
        array $dictionaries,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        if ($format !== self::FORMAT_LZ4_ZIP) {
            throw new \RuntimeException("LZ4 dictionary ZIP inspection requires LZ4 ZIP stream format: {$format}");
        }

        $dictionaryMap = self::normalizeLz4ExternalDictionaries($dictionaries);
        $frames = Lz4Frame::framesWithDictionaries($bytes, $dictionaryMap, $maxUncompressedBytes);
        $zipBytes = self::decodedLz4DataFromFrames($frames);
        $package = ZipPackage::fromString($zipBytes);

        return self::zipStreamInspection(
            $bytes,
            $format,
            $zipBytes,
            $package,
            $maxUncompressedBytes,
            self::lz4DictionaryStreamInspection($bytes, $frames, $dictionaryMap)
        );
    }

    public static function decodeTarBytes(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): string {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        return match ($format) {
            self::FORMAT_TAR => self::boundedPlainBytes($bytes, $maxUncompressedBytes, 'Plain TAR stream'),
            self::FORMAT_GZIP_TAR => GzipStream::decode($bytes, $maxUncompressedBytes),
            self::FORMAT_ZLIB_TAR => DeflateStream::decode($bytes, DeflateStream::FORMAT_ZLIB, $maxUncompressedBytes),
            self::FORMAT_RAW_DEFLATE_TAR => DeflateStream::decode($bytes, DeflateStream::FORMAT_RAW, $maxUncompressedBytes),
            self::FORMAT_LZ4_TAR => Lz4Frame::decode($bytes, $maxUncompressedBytes),
            default => throw new \RuntimeException("Unsupported archive compression stream format: {$format}"),
        };
    }

    /**
     * @param array<int|string, string> $dictionaries
     */
    public static function decodeTarBytesWithLz4Dictionaries(
        string $bytes,
        string $format,
        array $dictionaries,
        ?int $maxUncompressedBytes = null
    ): string {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        if ($format !== self::FORMAT_LZ4_TAR) {
            throw new \RuntimeException("LZ4 dictionary decoding requires LZ4 TAR stream format: {$format}");
        }

        return Lz4Frame::decodeWithDictionaries($bytes, $dictionaries, $maxUncompressedBytes);
    }

    /**
     * @param array<int|string, string> $dictionaries
     */
    public static function decodeTarBytesWithZlibDictionaries(
        string $bytes,
        string $format,
        array $dictionaries,
        ?int $maxUncompressedBytes = null
    ): string {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        if ($format !== self::FORMAT_ZLIB_TAR) {
            throw new \RuntimeException("ZLIB dictionary decoding requires ZLIB TAR stream format: {$format}");
        }

        return DeflateStream::decodeZlibWithDictionaries($bytes, $dictionaries, $maxUncompressedBytes);
    }

    public static function decodeZipBytes(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): string {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        return match ($format) {
            self::FORMAT_ZIP => self::boundedPlainBytes($bytes, $maxUncompressedBytes, 'Plain ZIP package stream'),
            self::FORMAT_GZIP_ZIP => GzipStream::decode($bytes, $maxUncompressedBytes),
            self::FORMAT_ZLIB_ZIP => DeflateStream::decode($bytes, DeflateStream::FORMAT_ZLIB, $maxUncompressedBytes),
            self::FORMAT_RAW_DEFLATE_ZIP => DeflateStream::decode($bytes, DeflateStream::FORMAT_RAW, $maxUncompressedBytes),
            self::FORMAT_LZ4_ZIP => Lz4Frame::decode($bytes, $maxUncompressedBytes),
            default => throw new \RuntimeException("Unsupported archive compression stream format: {$format}"),
        };
    }

    /**
     * @param array<int|string, string> $dictionaries
     */
    public static function decodeZipBytesWithLz4Dictionaries(
        string $bytes,
        string $format,
        array $dictionaries,
        ?int $maxUncompressedBytes = null
    ): string {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        if ($format !== self::FORMAT_LZ4_ZIP) {
            throw new \RuntimeException("LZ4 dictionary decoding requires LZ4 ZIP stream format: {$format}");
        }

        return Lz4Frame::decodeWithDictionaries($bytes, $dictionaries, $maxUncompressedBytes);
    }

    /**
     * @param array<int|string, string> $dictionaries
     */
    public static function decodeZipBytesWithZlibDictionaries(
        string $bytes,
        string $format,
        array $dictionaries,
        ?int $maxUncompressedBytes = null
    ): string {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        if ($format !== self::FORMAT_ZLIB_ZIP) {
            throw new \RuntimeException("ZLIB dictionary decoding requires ZLIB ZIP stream format: {$format}");
        }

        return DeflateStream::decodeZlibWithDictionaries($bytes, $dictionaries, $maxUncompressedBytes);
    }

    private static function boundedPlainBytes(string $bytes, ?int $maxUncompressedBytes, string $label): string
    {
        if ($maxUncompressedBytes !== null && strlen($bytes) > $maxUncompressedBytes) {
            throw new \RuntimeException("{$label} exceeds the configured uncompressed byte limit");
        }

        return $bytes;
    }

    /**
     * @return array{format:string, tarBytes:string, archive:TarArchive}
     */
    private static function detectTarCandidate(
        string $bytes,
        ?int $maxUncompressedBytes,
        ?int $maxUnpackedBytes
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        self::assertLimit($maxUnpackedBytes, 'archive stream max unpacked byte limit');

        $matches = [];
        $errors = [];
        foreach (self::candidateTarFormats($bytes) as $format) {
            try {
                $tarBytes = self::decodeTarBytes($bytes, $format, $maxUncompressedBytes);
                $matches[] = [
                    'format' => $format,
                    'tarBytes' => $tarBytes,
                    'archive' => TarArchive::fromString($tarBytes, $maxUnpackedBytes),
                ];
            } catch (\RuntimeException $exception) {
                $errors[$format] = $exception->getMessage();
            }
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        if (count($matches) > 1) {
            $formats = implode(', ', array_map(
                static fn (array $match): string => $match['format'],
                $matches
            ));

            throw new \RuntimeException("Ambiguous archive compression stream format; matched TAR candidates: {$formats}");
        }

        $details = self::formatDetectionDetails($errors);
        throw new \RuntimeException('Unable to detect archive compression stream format as TAR' . ($details === '' ? '' : ": {$details}"));
    }

    /**
     * @return array{format:string, zipBytes:string, package:ZipPackage}
     */
    private static function detectZipCandidate(
        string $bytes,
        ?int $maxUncompressedBytes
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $matches = [];
        $errors = [];
        foreach (self::candidateZipFormats($bytes) as $format) {
            try {
                $zipBytes = self::decodeZipBytes($bytes, $format, $maxUncompressedBytes);
                $matches[] = [
                    'format' => $format,
                    'zipBytes' => $zipBytes,
                    'package' => ZipPackage::fromString($zipBytes),
                ];
            } catch (\RuntimeException $exception) {
                $errors[$format] = $exception->getMessage();
            }
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        if (count($matches) > 1) {
            $formats = implode(', ', array_map(
                static fn (array $match): string => $match['format'],
                $matches
            ));

            throw new \RuntimeException("Ambiguous archive compression stream format; matched ZIP candidates: {$formats}");
        }

        $details = self::formatDetectionDetails($errors);
        throw new \RuntimeException('Unable to detect archive compression stream format as ZIP' . ($details === '' ? '' : ": {$details}"));
    }

    /**
     * @return array<string, mixed>
     */
    private static function detectPackageCandidate(
        string $bytes,
        ?int $maxUncompressedBytes,
        ?int $maxUnpackedBytes
    ): array {
        $matches = [];
        $errors = [];

        try {
            $tar = self::detectTarCandidate($bytes, $maxUncompressedBytes, $maxUnpackedBytes);
            $matches[] = [
                'kind' => self::PACKAGE_KIND_TAR,
            ] + $tar;
        } catch (\RuntimeException $exception) {
            $errors[self::PACKAGE_KIND_TAR] = $exception->getMessage();
        }

        try {
            $zip = self::detectZipCandidate($bytes, $maxUncompressedBytes);
            $matches[] = [
                'kind' => self::PACKAGE_KIND_ZIP,
            ] + $zip;
        } catch (\RuntimeException $exception) {
            $errors[self::PACKAGE_KIND_ZIP] = $exception->getMessage();
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        if (count($matches) > 1) {
            $descriptions = implode(', ', array_map(
                static fn (array $match): string => $match['kind'] . '/' . $match['format'],
                $matches
            ));

            throw new \RuntimeException("Ambiguous archive package stream; matched candidates: {$descriptions}");
        }

        $details = self::formatDetectionDetails($errors);
        throw new \RuntimeException('Unable to detect archive package stream as TAR or ZIP' . ($details === '' ? '' : ": {$details}"));
    }

    /**
     * @return array{
     *     format:string,
     *     tarBytes:string,
     *     archive:TarArchive,
     *     entryNames:list<string>,
     *     entryCount:int,
     *     regularFileCount:int,
     *     directoryCount:int,
     *     uncompressedSize:int,
     *     unpackedSize:int,
     *     endMarkerOffset:int,
     *     trailingZeroBytes:int,
     *     entryLayouts:list<array{
     *         name:string,
     *         type:string,
     *         size:int,
     *         mode:int,
     *         modifiedAt:int,
     *         accessedAt:?int,
     *         changedAt:?int,
     *         createdAt:?int,
     *         uid:int,
     *         gid:int,
     *         userName:string,
     *         groupName:string,
     *         paxHeaderCount:int,
     *         paxHeaderKeys:list<string>,
     *         paxGlobalHeaderCount:int,
     *         paxGlobalHeaderKeys:list<string>,
     *         paxLocalHeaderCount:int,
     *         paxLocalHeaderKeys:list<string>,
     *         paxDeletedHeaderKeys:list<string>,
     *         nameSource:string,
     *         gnuLongName:?string,
     *         headerOffset:int,
     *         dataOffset:int,
     *         dataEndOffset:int,
     *         paddedDataSize:int,
     *         recordSize:int,
     *         decodedSourceSegmentCount:int,
     *         decodedSourceSegments:list<array{
     *             sourceType:string,
     *             sourceIndex:int,
     *             sourceLabel:?string,
     *             sourceDecodedOffset:int,
     *             sourceDecodedEndOffset:int,
     *             entryRecordOffset:int,
     *             entryRecordEndOffset:int
     *         }>
     *     }>,
     *     stream:array<string, mixed>
     * }
     */
    private static function tarStreamInspection(
        string $bytes,
        string $format,
        string $tarBytes,
        TarArchive $archive,
        ?int $maxUncompressedBytes,
        ?array $streamInspection = null
    ): array {
        $stream = $streamInspection ?? self::streamInspection($bytes, $format, $maxUncompressedBytes);
        $entryNames = $archive->names();
        $endMarkerOffset = self::tarEndMarkerOffset($tarBytes);
        $entryLayouts = self::tarEntryLayouts($archive, $stream);

        return [
            'format' => $format,
            'tarBytes' => $tarBytes,
            'archive' => $archive,
            'entryNames' => $entryNames,
            'entryCount' => count($entryNames),
            'regularFileCount' => count(array_filter(
                $archive->entries(),
                static fn (TarArchiveEntry $entry): bool => $entry->isRegularFile()
            )),
            'directoryCount' => count(array_filter(
                $archive->entries(),
                static fn (TarArchiveEntry $entry): bool => $entry->isDirectory()
            )),
            'uncompressedSize' => strlen($tarBytes),
            'unpackedSize' => self::archiveUnpackedSize($archive),
            'endMarkerOffset' => $endMarkerOffset,
            'trailingZeroBytes' => strlen($tarBytes) - $endMarkerOffset,
            'entryLayouts' => $entryLayouts,
            'stream' => $stream,
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     zipBytes:string,
     *     package:ZipPackage,
     *     entryNames:list<string>,
     *     entryCount:int,
     *     packageByteSize:int,
     *     entryUncompressedSize:int,
     *     stream:array<string, mixed>
     * }
     */
    private static function zipStreamInspection(
        string $bytes,
        string $format,
        string $zipBytes,
        ZipPackage $package,
        ?int $maxUncompressedBytes,
        ?array $streamInspection = null
    ): array {
        $entryNames = $package->names();

        return [
            'format' => $format,
            'zipBytes' => $zipBytes,
            'package' => $package,
            'entryNames' => $entryNames,
            'entryCount' => count($entryNames),
            'packageByteSize' => strlen($zipBytes),
            'entryUncompressedSize' => self::zipPackageUncompressedSize($package),
            'stream' => $streamInspection ?? self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    private static function archiveUnpackedSize(TarArchive $archive): int
    {
        $size = 0;
        foreach ($archive->entries() as $entry) {
            if ($entry->isRegularFile()) {
                $size += $entry->size;
            }
        }

        return $size;
    }

    /**
     * @return list<array{
     *     name:string,
     *     type:string,
     *     size:int,
     *     mode:int,
     *     modifiedAt:int,
     *     accessedAt:?int,
     *     changedAt:?int,
     *     createdAt:?int,
     *     uid:int,
     *     gid:int,
     *     userName:string,
     *     groupName:string,
     *     paxHeaderCount:int,
     *     paxHeaderKeys:list<string>,
     *     paxGlobalHeaderCount:int,
     *     paxGlobalHeaderKeys:list<string>,
     *     paxLocalHeaderCount:int,
     *     paxLocalHeaderKeys:list<string>,
     *     paxDeletedHeaderKeys:list<string>,
     *     nameSource:string,
     *     gnuLongName:?string,
     *     headerOffset:int,
     *     dataOffset:int,
     *     dataEndOffset:int,
     *     paddedDataSize:int,
     *     recordSize:int,
     *     decodedSourceSegmentCount:int,
     *     decodedSourceSegments:list<array{
     *         sourceType:string,
     *         sourceIndex:int,
     *         sourceLabel:?string,
     *         sourceDecodedOffset:int,
     *         sourceDecodedEndOffset:int,
     *         entryRecordOffset:int,
     *         entryRecordEndOffset:int
     *     }>
     * }>
     */
    private static function tarEntryLayouts(TarArchive $archive, array $streamInspection): array
    {
        $layouts = [];
        $decodedSourceSegments = self::decodedStreamSourceSegments(
            $streamInspection,
            strlen($archive->bytes())
        );

        foreach ($archive->entries() as $entry) {
            $headerOffset = $entry->dataOffset - 512;
            $paddedDataSize = self::paddedTarPayloadSize($entry->size);
            $recordSize = 512 + $paddedDataSize;
            $paxHeaderKeys = array_keys($entry->paxHeaders);
            sort($paxHeaderKeys);
            $paxGlobalHeaderKeys = array_keys($entry->globalPaxHeaders);
            sort($paxGlobalHeaderKeys);
            $paxLocalHeaderKeys = array_keys($entry->localPaxHeaders);
            sort($paxLocalHeaderKeys);
            $entrySourceSegments = self::entryDecodedSourceSegments(
                $headerOffset,
                $headerOffset + $recordSize,
                $decodedSourceSegments
            );

            $layouts[] = [
                'name' => $entry->name,
                'type' => $entry->type,
                'size' => $entry->size,
                'mode' => $entry->mode,
                'modifiedAt' => $entry->modifiedAt,
                'accessedAt' => $entry->accessedAt,
                'changedAt' => $entry->changedAt,
                'createdAt' => $entry->createdAt,
                'uid' => $entry->uid,
                'gid' => $entry->gid,
                'userName' => $entry->userName,
                'groupName' => $entry->groupName,
                'paxHeaderCount' => count($entry->paxHeaders),
                'paxHeaderKeys' => $paxHeaderKeys,
                'paxGlobalHeaderCount' => count($entry->globalPaxHeaders),
                'paxGlobalHeaderKeys' => $paxGlobalHeaderKeys,
                'paxLocalHeaderCount' => count($entry->localPaxHeaders),
                'paxLocalHeaderKeys' => $paxLocalHeaderKeys,
                'paxDeletedHeaderKeys' => $entry->deletedPaxHeaderKeys,
                'nameSource' => $entry->nameSource,
                'gnuLongName' => $entry->gnuLongName,
                'headerOffset' => $headerOffset,
                'dataOffset' => $entry->dataOffset,
                'dataEndOffset' => $entry->dataOffset + $entry->size,
                'paddedDataSize' => $paddedDataSize,
                'recordSize' => $recordSize,
                'decodedSourceSegmentCount' => count($entrySourceSegments),
                'decodedSourceSegments' => $entrySourceSegments,
            ];
        }

        return $layouts;
    }

    /**
     * @return list<array{
     *     sourceType:string,
     *     sourceIndex:int,
     *     sourceLabel:?string,
     *     decodedDataOffset:int,
     *     decodedDataEndOffset:int
     * }>
     */
    private static function decodedStreamSourceSegments(array $streamInspection, int $decodedSize): array
    {
        $type = (string) ($streamInspection['type'] ?? '');
        if ($type === 'gzip') {
            $segments = [];
            foreach (($streamInspection['members'] ?? []) as $index => $member) {
                if (!is_array($member)) {
                    continue;
                }

                $label = null;
                if (is_string($member['filenameText'] ?? null)) {
                    $label = $member['filenameText'];
                } elseif (is_string($member['filename'] ?? null)) {
                    $label = $member['filename'];
                }

                $segments[] = [
                    'sourceType' => 'gzip-member',
                    'sourceIndex' => (int) $index,
                    'sourceLabel' => $label,
                    'decodedDataOffset' => (int) ($member['decodedDataOffset'] ?? 0),
                    'decodedDataEndOffset' => (int) ($member['decodedDataEndOffset'] ?? 0),
                ];
            }

            return $segments;
        }

        if ($type === 'lz4') {
            $segments = [];
            $dataFrameIndex = 0;
            foreach (($streamInspection['frames'] ?? []) as $frame) {
                if (!is_array($frame) || ($frame['type'] ?? null) !== 'frame') {
                    continue;
                }

                $segments[] = [
                    'sourceType' => 'lz4-frame',
                    'sourceIndex' => $dataFrameIndex,
                    'sourceLabel' => null,
                    'decodedDataOffset' => (int) ($frame['decodedDataOffset'] ?? 0),
                    'decodedDataEndOffset' => (int) ($frame['decodedDataEndOffset'] ?? 0),
                ];
                $dataFrameIndex++;
            }

            return $segments;
        }

        $sourceType = match ($type) {
            'plain-tar' => 'plain-tar',
            'zlib-deflate' => 'zlib-deflate',
            'raw-deflate' => 'raw-deflate',
            default => $type === '' ? 'unknown' : $type,
        };

        return [[
            'sourceType' => $sourceType,
            'sourceIndex' => 0,
            'sourceLabel' => null,
            'decodedDataOffset' => 0,
            'decodedDataEndOffset' => $decodedSize,
        ]];
    }

    /**
     * @param list<array{
     *     sourceType:string,
     *     sourceIndex:int,
     *     sourceLabel:?string,
     *     decodedDataOffset:int,
     *     decodedDataEndOffset:int
     * }> $sourceSegments
     * @return list<array{
     *     sourceType:string,
     *     sourceIndex:int,
     *     sourceLabel:?string,
     *     sourceDecodedOffset:int,
     *     sourceDecodedEndOffset:int,
     *     entryRecordOffset:int,
     *     entryRecordEndOffset:int
     * }>
     */
    private static function entryDecodedSourceSegments(int $recordStart, int $recordEnd, array $sourceSegments): array
    {
        $segments = [];
        foreach ($sourceSegments as $source) {
            $sourceStart = $source['decodedDataOffset'];
            $sourceEnd = $source['decodedDataEndOffset'];
            $overlapStart = max($recordStart, $sourceStart);
            $overlapEnd = min($recordEnd, $sourceEnd);
            if ($overlapStart >= $overlapEnd) {
                continue;
            }

            $segments[] = [
                'sourceType' => $source['sourceType'],
                'sourceIndex' => $source['sourceIndex'],
                'sourceLabel' => $source['sourceLabel'],
                'sourceDecodedOffset' => $overlapStart,
                'sourceDecodedEndOffset' => $overlapEnd,
                'entryRecordOffset' => $overlapStart - $recordStart,
                'entryRecordEndOffset' => $overlapEnd - $recordStart,
            ];
        }

        return $segments;
    }

    private static function paddedTarPayloadSize(int $size): int
    {
        $remainder = $size % 512;

        return $remainder === 0 ? $size : $size + (512 - $remainder);
    }

    private static function tarEndMarkerOffset(string $tarBytes): int
    {
        $zeroBlock = str_repeat("\0", 512);
        $length = strlen($tarBytes);
        for ($offset = 0; $offset + 1024 <= $length; $offset += 512) {
            if (substr($tarBytes, $offset, 512) === $zeroBlock
                && substr($tarBytes, $offset + 512, 512) === $zeroBlock
            ) {
                return $offset;
            }
        }

        throw new \RuntimeException('TAR archive is missing the required two-block end marker');
    }

    private static function zipPackageUncompressedSize(ZipPackage $package): int
    {
        $size = 0;
        foreach ($package->entries() as $entry) {
            if (!$entry->isDirectory()) {
                $size += $entry->uncompressedSize;
            }
        }

        return $size;
    }

    private static function expansionRatio(int $expandedBytes, int $baseBytes): float
    {
        if ($expandedBytes === 0) {
            return 0.0;
        }

        if ($baseBytes <= 0) {
            return INF;
        }

        return $expandedBytes / $baseBytes;
    }

    private static function assertPositiveRatio(float $value, string $label): void
    {
        if (!is_finite($value) || $value <= 0.0) {
            throw new \RuntimeException("{$label} must be a positive finite number");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function streamInspection(string $bytes, string $format, ?int $maxUncompressedBytes): array
    {
        return match ($format) {
            self::FORMAT_TAR => [
                'type' => 'plain-tar',
                'compressedSize' => strlen($bytes),
                'memberCount' => 0,
                'frameCount' => 0,
            ],
            self::FORMAT_ZIP => [
                'type' => 'plain-zip',
                'compressedSize' => strlen($bytes),
                'memberCount' => 0,
                'frameCount' => 0,
            ],
            self::FORMAT_GZIP_TAR, self::FORMAT_GZIP_ZIP => self::gzipStreamInspection($bytes, $maxUncompressedBytes),
            self::FORMAT_ZLIB_TAR, self::FORMAT_ZLIB_ZIP => self::zlibStreamInspection($bytes, $maxUncompressedBytes),
            self::FORMAT_RAW_DEFLATE_TAR, self::FORMAT_RAW_DEFLATE_ZIP => self::rawDeflateStreamInspection($bytes, $maxUncompressedBytes),
            self::FORMAT_LZ4_TAR, self::FORMAT_LZ4_ZIP => self::lz4StreamInspection($bytes, $maxUncompressedBytes),
            default => throw new \RuntimeException("Unsupported archive compression stream format: {$format}"),
        };
    }

    /**
     * @return array{
     *     type:string,
     *     memberCount:int,
     *     compressedSize:int,
     *     uncompressedSize:int,
     *     trailingPaddingBytes:int,
     *     members:list<array{
     *         filename:?string,
     *         filenameText:?string,
     *         filenameEncoding:?string,
     *         comment:?string,
     *         commentText:?string,
     *         commentEncoding:?string,
     *         modifiedAt:int,
     *         modifiedAtKnown:bool,
     *         modifiedAtText:?string,
     *         flags:int,
     *         textHint:bool,
     *         extraFlags:int,
     *         extraFlagsMeaning:string,
     *         operatingSystem:int,
     *         operatingSystemName:string,
     *         extraFieldData:?string,
     *         extraFields:list<array{identifier:string,id1:int,id2:int,length:int,data:string}>,
     *         crc32:int,
     *         uncompressedSize:int,
     *         compressedSize:int,
     *         decodedDataOffset:int,
     *         decodedDataEndOffset:int,
     *         memberSize:int,
     *         memberOffset:int,
     *         headerSize:int,
     *         compressedDataOffset:int,
     *         trailerOffset:int,
     *         nextMemberOffset:int,
     *         extraFieldCount:int,
     *         headerCrcPresent:bool,
     *         headerCrc16:?int,
     *         headerCrc16Hex:?string,
     *         headerCrcOffset:?int,
     *         headerCrcCoverageSize:?int
     *     }>
     * }
     */
    private static function gzipStreamInspection(string $bytes, ?int $maxUncompressedBytes): array
    {
        $inspection = GzipStream::inspect($bytes, $maxUncompressedBytes);
        $members = array_map(
            static fn (array $member): array => [
                'filename' => $member['filename'],
                'filenameText' => $member['filenameText'],
                'filenameEncoding' => $member['filenameEncoding'],
                'comment' => $member['comment'],
                'commentText' => $member['commentText'],
                'commentEncoding' => $member['commentEncoding'],
                'modifiedAt' => $member['modifiedAt'],
                'modifiedAtKnown' => $member['modifiedAtKnown'],
                'modifiedAtText' => $member['modifiedAtText'],
                'flags' => $member['flags'],
                'textHint' => $member['textHint'],
                'extraFlags' => $member['extraFlags'],
                'extraFlagsMeaning' => $member['extraFlagsMeaning'],
                'operatingSystem' => $member['operatingSystem'],
                'operatingSystemName' => $member['operatingSystemName'],
                'extraFieldData' => $member['extraFieldData'],
                'extraFields' => $member['extraFields'],
                'crc32' => $member['crc32'],
                'uncompressedSize' => $member['uncompressedSize'],
                'compressedSize' => $member['compressedSize'],
                'decodedDataOffset' => $member['decodedDataOffset'],
                'decodedDataEndOffset' => $member['decodedDataEndOffset'],
                'memberSize' => $member['memberSize'],
                'memberOffset' => $member['memberOffset'],
                'headerSize' => $member['headerSize'],
                'compressedDataOffset' => $member['compressedDataOffset'],
                'trailerOffset' => $member['trailerOffset'],
                'nextMemberOffset' => $member['nextMemberOffset'],
                'extraFieldCount' => count($member['extraFields']),
                'headerCrcPresent' => $member['headerCrcPresent'],
                'headerCrc16' => $member['headerCrc16'],
                'headerCrc16Hex' => $member['headerCrc16Hex'],
                'headerCrcOffset' => $member['headerCrcOffset'],
                'headerCrcCoverageSize' => $member['headerCrcCoverageSize'],
            ],
            $inspection['members']
        );

        return [
            'type' => 'gzip',
            'memberCount' => $inspection['memberCount'],
            'compressedSize' => strlen($bytes),
            'uncompressedSize' => $inspection['uncompressedSize'],
            'trailingPaddingBytes' => $inspection['trailingPaddingBytes'],
            'members' => $members,
        ];
    }

    /**
     * @return array{
     *     type:string,
     *     memberCount:int,
     *     compressedSize:int,
     *     compressedPayloadSize:int,
     *     uncompressedSize:int,
     *     headerSize:int,
     *     compressedPayloadOffset:int,
     *     trailerOffset:int,
     *     trailerSize:int,
     *     consumedBytes:int,
     *     compressionMethod:int,
     *     windowSize:int,
     *     compressionLevelHint:string,
     *     adler32:int,
     *     adler32Hex:string
     * }
     */
    private static function zlibStreamInspection(string $bytes, ?int $maxUncompressedBytes): array
    {
        $metadata = DeflateStream::inspectZlib($bytes, $maxUncompressedBytes);

        return [
            'type' => 'zlib-deflate',
            'memberCount' => 1,
            'compressedSize' => strlen($bytes),
            'compressedPayloadSize' => $metadata['compressedSize'],
            'headerSize' => $metadata['headerSize'],
            'compressedPayloadOffset' => $metadata['compressedPayloadOffset'],
            'trailerOffset' => $metadata['trailerOffset'],
            'trailerSize' => $metadata['trailerSize'],
            'consumedBytes' => $metadata['consumedBytes'],
            'uncompressedSize' => $metadata['uncompressedSize'],
            'compressionMethod' => $metadata['compressionMethod'],
            'windowSize' => $metadata['windowSize'],
            'compressionLevelHint' => $metadata['compressionLevelHint'],
            'adler32' => $metadata['adler32'],
            'adler32Hex' => $metadata['adler32Hex'],
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private static function zlibDictionaryStreamInspection(string $bytes, array $metadata): array
    {
        return [
            'type' => 'zlib-deflate',
            'memberCount' => 1,
            'compressedSize' => strlen($bytes),
            'compressedPayloadSize' => $metadata['compressedSize'],
            'headerSize' => $metadata['headerSize'],
            'compressedPayloadOffset' => $metadata['compressedPayloadOffset'],
            'trailerOffset' => $metadata['trailerOffset'],
            'trailerSize' => $metadata['trailerSize'],
            'consumedBytes' => $metadata['consumedBytes'],
            'uncompressedSize' => $metadata['uncompressedSize'],
            'compressionMethod' => $metadata['compressionMethod'],
            'windowSize' => $metadata['windowSize'],
            'compressionLevelHint' => $metadata['compressionLevelHint'],
            'hasPresetDictionary' => $metadata['hasPresetDictionary'],
            'presetDictionaryId' => $metadata['presetDictionaryId'],
            'presetDictionaryIdHex' => $metadata['presetDictionaryIdHex'],
            'dictionarySupplied' => $metadata['dictionarySupplied'],
            'dictionarySize' => $metadata['dictionarySize'],
            'dictionaryAdler32' => $metadata['dictionaryAdler32'],
            'dictionaryAdler32Hex' => $metadata['dictionaryAdler32Hex'],
            'adler32' => $metadata['adler32'],
            'adler32Hex' => $metadata['adler32Hex'],
        ];
    }

    /**
     * @return array{
     *     type:string,
     *     memberCount:int,
     *     compressedSize:int,
     *     compressedPayloadSize:int,
     *     uncompressedSize:int,
     *     headerSize:int,
     *     compressedPayloadOffset:int,
     *     trailerOffset:?int,
     *     trailerSize:int,
     *     consumedBytes:int
     * }
     */
    private static function rawDeflateStreamInspection(string $bytes, ?int $maxUncompressedBytes): array
    {
        $metadata = DeflateStream::inspectRaw($bytes, $maxUncompressedBytes);

        return [
            'type' => 'raw-deflate',
            'memberCount' => 1,
            'compressedSize' => strlen($bytes),
            'compressedPayloadSize' => $metadata['compressedSize'],
            'uncompressedSize' => $metadata['uncompressedSize'],
            'headerSize' => $metadata['headerSize'],
            'compressedPayloadOffset' => $metadata['compressedPayloadOffset'],
            'trailerOffset' => $metadata['trailerOffset'],
            'trailerSize' => $metadata['trailerSize'],
            'consumedBytes' => $metadata['consumedBytes'],
        ];
    }

    /**
     * @return array{
     *     type:string,
     *     frameCount:int,
     *     dataFrameCount:int,
     *     skippableFrameCount:int,
     *     blockCount:int,
     *     compressedSize:int,
     *     uncompressedSize:int,
     *     frames:list<array<string, mixed>>
     * }
     */
    private static function lz4StreamInspection(string $bytes, ?int $maxUncompressedBytes): array
    {
        $frames = [];
        $dataFrameCount = 0;
        $skippableFrameCount = 0;
        $blockCount = 0;
        $uncompressedSize = 0;

        foreach (Lz4Frame::frames($bytes, $maxUncompressedBytes) as $frame) {
            if ($frame['type'] === 'skippable') {
                $skippableFrameCount++;
                $frames[] = [
                    'type' => 'skippable',
                    'id' => $frame['id'],
                    'data' => $frame['data'],
                    'frameSize' => $frame['frameSize'],
                    'frameOffset' => $frame['frameOffset'],
                    'nextFrameOffset' => $frame['nextFrameOffset'],
                ];
                continue;
            }

            $dataSize = strlen($frame['data']);
            $dataFrameCount++;
            $blockCount += $frame['blockCount'];
            $uncompressedSize += $dataSize;
            $frames[] = [
                'type' => 'frame',
                'contentSize' => $frame['contentSize'],
                'blockMaxSize' => $frame['blockMaxSize'],
                'blockIndependent' => $frame['blockIndependent'],
                'blockChecksum' => $frame['blockChecksum'],
                'contentChecksum' => $frame['contentChecksum'],
                'blockCount' => $frame['blockCount'],
                'blockTypes' => $frame['blockTypes'],
                'compressedSize' => $frame['compressedSize'],
                'decodedDataSize' => $dataSize,
                'decodedDataOffset' => $frame['decodedDataOffset'],
                'decodedDataEndOffset' => $frame['decodedDataEndOffset'],
                'frameSize' => $frame['frameSize'],
                'frameOffset' => $frame['frameOffset'],
                'nextFrameOffset' => $frame['nextFrameOffset'],
            ];
        }

        return [
            'type' => 'lz4',
            'frameCount' => count($frames),
            'dataFrameCount' => $dataFrameCount,
            'skippableFrameCount' => $skippableFrameCount,
            'blockCount' => $blockCount,
            'compressedSize' => strlen($bytes),
            'uncompressedSize' => $uncompressedSize,
            'frames' => $frames,
        ];
    }

    /**
     * @param list<array<string, mixed>> $frames
     * @param array<int, string> $dictionaryMap
     * @return array<string, mixed>
     */
    private static function lz4DictionaryStreamInspection(string $bytes, array $frames, array $dictionaryMap): array
    {
        $summaryFrames = [];
        $dataFrameCount = 0;
        $skippableFrameCount = 0;
        $dictionaryFrameCount = 0;
        $blockCount = 0;
        $uncompressedSize = 0;

        foreach ($frames as $frame) {
            if (($frame['type'] ?? null) === 'skippable') {
                $skippableFrameCount++;
                $summaryFrames[] = [
                    'type' => 'skippable',
                    'id' => $frame['id'],
                    'data' => $frame['data'],
                    'frameSize' => $frame['frameSize'],
                    'frameOffset' => $frame['frameOffset'],
                    'nextFrameOffset' => $frame['nextFrameOffset'],
                ];
                continue;
            }

            if (($frame['type'] ?? null) !== 'frame') {
                throw new \RuntimeException('Unexpected LZ4 frame metadata record');
            }

            $dictionaryId = $frame['dictionaryId'];
            $dictionarySize = null;
            if ($dictionaryId !== null) {
                $dictionaryFrameCount++;
                $dictionarySize = strlen($dictionaryMap[$dictionaryId] ?? '');
            }

            $dataSize = strlen($frame['data']);
            $decodedDataOffset = $uncompressedSize;
            $decodedDataEndOffset = $decodedDataOffset + $dataSize;
            $dataFrameCount++;
            $blockCount += $frame['blockCount'];
            $uncompressedSize = $decodedDataEndOffset;
            $summaryFrames[] = [
                'type' => 'frame',
                'contentSize' => $frame['contentSize'],
                'dictionaryId' => $dictionaryId,
                'dictionarySupplied' => $dictionaryId !== null,
                'dictionarySize' => $dictionarySize,
                'blockMaxSize' => $frame['blockMaxSize'],
                'blockIndependent' => $frame['blockIndependent'],
                'blockChecksum' => $frame['blockChecksum'],
                'contentChecksum' => $frame['contentChecksum'],
                'blockCount' => $frame['blockCount'],
                'blockTypes' => $frame['blockTypes'],
                'compressedSize' => $frame['compressedSize'],
                'decodedDataSize' => $dataSize,
                'decodedDataOffset' => $decodedDataOffset,
                'decodedDataEndOffset' => $decodedDataEndOffset,
                'frameSize' => $frame['frameSize'],
                'frameOffset' => $frame['frameOffset'],
                'nextFrameOffset' => $frame['nextFrameOffset'],
            ];
        }

        return [
            'type' => 'lz4',
            'frameCount' => count($summaryFrames),
            'dataFrameCount' => $dataFrameCount,
            'skippableFrameCount' => $skippableFrameCount,
            'dictionaryFrameCount' => $dictionaryFrameCount,
            'blockCount' => $blockCount,
            'compressedSize' => strlen($bytes),
            'uncompressedSize' => $uncompressedSize,
            'frames' => $summaryFrames,
        ];
    }

    /**
     * @param list<array<string, mixed>> $frames
     */
    private static function decodedLz4DataFromFrames(array $frames): string
    {
        $data = '';
        foreach ($frames as $frame) {
            if (($frame['type'] ?? null) === 'frame') {
                $data .= $frame['data'];
            }
        }

        return $data;
    }

    /**
     * @param array<int|string, string> $dictionaries
     * @return array<int, string>
     */
    private static function normalizeLz4ExternalDictionaries(array $dictionaries): array
    {
        $normalized = [];
        foreach ($dictionaries as $id => $dictionary) {
            if (!is_string($dictionary)) {
                throw new \RuntimeException('LZ4 external dictionaries must be byte strings');
            }

            if (is_int($id)) {
                $dictionaryId = $id;
            } elseif (is_string($id) && preg_match('/^(?:0|[1-9][0-9]*)$/', $id) === 1) {
                $dictionaryId = (int) $id;
            } else {
                throw new \RuntimeException('LZ4 external dictionary ids must be unsigned 32-bit integers');
            }

            if ($dictionaryId < 0 || $dictionaryId > 0xffffffff) {
                throw new \RuntimeException('LZ4 external dictionary id must fit in an unsigned 32-bit field');
            }

            $normalized[$dictionaryId] = $dictionary;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $candidate
     * @param list<array<string, mixed>> $entries
     */
    private static function collectNestedPackageEntries(
        array $candidate,
        string $parentPath,
        int $depth,
        int $maxDepth,
        ?int $maxUncompressedBytes,
        ?int $maxUnpackedBytes,
        array &$entries
    ): void {
        foreach (self::nestedPackagePayloads($candidate, $maxUncompressedBytes) as $payload) {
            $reasons = self::nestedPackageCandidateReasons($payload['entryName'], $payload['data']);
            if ($reasons === []) {
                continue;
            }

            $path = $parentPath === ''
                ? $payload['entryName']
                : $parentPath . '!' . $payload['entryName'];
            $base = [
                'path' => $path,
                'parentPath' => $parentPath,
                'parentKind' => $payload['parentKind'],
                'entryName' => $payload['entryName'],
                'depth' => $depth,
                'size' => $payload['size'],
                'candidateReasons' => $reasons,
                'policy' => 'metadata-only-no-extraction',
            ];

            if ($payload['readError'] !== null) {
                $entries[] = $base + [
                    'status' => 'unreadable',
                    'kind' => null,
                    'format' => null,
                    'entryCount' => 0,
                    'entryNames' => [],
                    'uncompressedSize' => null,
                    'packageByteSize' => null,
                    'diagnostics' => ['nested-package-read-failed: ' . $payload['readError']],
                ];
                continue;
            }

            try {
                $nested = self::detectPackageCandidate(
                    $payload['data'],
                    $maxUncompressedBytes,
                    $maxUnpackedBytes
                );
            } catch (\RuntimeException $exception) {
                $entries[] = $base + [
                    'status' => 'unreadable',
                    'kind' => null,
                    'format' => null,
                    'entryCount' => 0,
                    'entryNames' => [],
                    'uncompressedSize' => null,
                    'packageByteSize' => null,
                    'diagnostics' => ['nested-package-detection-failed: ' . $exception->getMessage()],
                ];
                continue;
            }

            $entries[] = $base + self::nestedPackageSummary($nested);
            if ($depth < $maxDepth) {
                self::collectNestedPackageEntries(
                    $nested,
                    $path,
                    $depth + 1,
                    $maxDepth,
                    $maxUncompressedBytes,
                    $maxUnpackedBytes,
                    $entries
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $candidate
     * @return iterable<array{parentKind:string, entryName:string, size:int, data:?string, readError:?string}>
     */
    private static function nestedPackagePayloads(array $candidate, ?int $maxUncompressedBytes): iterable
    {
        if (($candidate['kind'] ?? null) === self::PACKAGE_KIND_TAR) {
            $archive = $candidate['archive'] ?? null;
            if (!$archive instanceof TarArchive) {
                return;
            }

            foreach ($archive->entries() as $entry) {
                if (!$entry->isRegularFile()) {
                    continue;
                }

                yield [
                    'parentKind' => self::PACKAGE_KIND_TAR,
                    'entryName' => $entry->name,
                    'size' => $entry->size,
                    'data' => $archive->read($entry->name),
                    'readError' => null,
                ];
            }

            return;
        }

        $package = $candidate['package'] ?? null;
        if (!$package instanceof ZipPackage) {
            return;
        }

        foreach ($package->entries() as $entry) {
            if ($entry->isDirectory()) {
                continue;
            }

            try {
                $data = $package->read($entry->name, $maxUncompressedBytes);
                $readError = null;
            } catch (\RuntimeException $exception) {
                $data = null;
                $readError = $exception->getMessage();
            }

            yield [
                'parentKind' => self::PACKAGE_KIND_ZIP,
                'entryName' => $entry->name,
                'size' => $entry->uncompressedSize,
                'data' => $data,
                'readError' => $readError,
            ];
        }
    }

    /**
     * @param array<string, mixed> $candidate
     * @return array<string, mixed>
     */
    private static function nestedPackageSummary(array $candidate): array
    {
        $entryNames = self::candidateEntryNames($candidate);
        $summary = [
            'status' => 'package',
            'kind' => $candidate['kind'],
            'format' => $candidate['format'],
            'entryCount' => count($entryNames),
            'entryNames' => $entryNames,
            'uncompressedSize' => self::candidateUncompressedSize($candidate),
            'packageByteSize' => self::candidatePackageByteSize($candidate),
            'diagnostics' => [],
        ];

        if (($candidate['kind'] ?? null) === self::PACKAGE_KIND_TAR) {
            $archive = $candidate['archive'];
            $summary['regularFileCount'] = count(array_filter(
                $archive->entries(),
                static fn (TarArchiveEntry $entry): bool => $entry->isRegularFile()
            ));
            $summary['directoryCount'] = count(array_filter(
                $archive->entries(),
                static fn (TarArchiveEntry $entry): bool => $entry->isDirectory()
            ));
            $summary['unpackedSize'] = self::archiveUnpackedSize($archive);
        } elseif (($candidate['kind'] ?? null) === self::PACKAGE_KIND_ZIP) {
            $summary['entryUncompressedSize'] = self::zipPackageUncompressedSize($candidate['package']);
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $candidate
     * @return list<string>
     */
    private static function candidateEntryNames(array $candidate): array
    {
        if (($candidate['kind'] ?? null) === self::PACKAGE_KIND_TAR && ($candidate['archive'] ?? null) instanceof TarArchive) {
            return $candidate['archive']->names();
        }

        if (($candidate['kind'] ?? null) === self::PACKAGE_KIND_ZIP && ($candidate['package'] ?? null) instanceof ZipPackage) {
            return $candidate['package']->names();
        }

        return [];
    }

    /**
     * @param array<string, mixed> $candidate
     */
    private static function candidateUncompressedSize(array $candidate): ?int
    {
        if (($candidate['kind'] ?? null) === self::PACKAGE_KIND_TAR && isset($candidate['tarBytes']) && is_string($candidate['tarBytes'])) {
            return strlen($candidate['tarBytes']);
        }

        if (($candidate['kind'] ?? null) === self::PACKAGE_KIND_ZIP && isset($candidate['zipBytes']) && is_string($candidate['zipBytes'])) {
            return strlen($candidate['zipBytes']);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $candidate
     */
    private static function candidatePackageByteSize(array $candidate): ?int
    {
        if (($candidate['kind'] ?? null) === self::PACKAGE_KIND_TAR && isset($candidate['tarBytes']) && is_string($candidate['tarBytes'])) {
            return strlen($candidate['tarBytes']);
        }

        if (($candidate['kind'] ?? null) === self::PACKAGE_KIND_ZIP && isset($candidate['zipBytes']) && is_string($candidate['zipBytes'])) {
            return strlen($candidate['zipBytes']);
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function nestedPackageCandidateReasons(string $entryName, ?string $data): array
    {
        $reasons = self::nestedPackageNameCandidateReasons($entryName);
        if ($data !== null) {
            if (self::startsWithZipHeader($data)) {
                $reasons[] = 'signature:zip';
            }
            if (self::startsWithGzipHeader($data)) {
                $reasons[] = 'signature:gzip';
            }
            if (self::startsWithZlibHeader($data)) {
                $reasons[] = 'signature:zlib';
            }
            if (self::startsWithLz4Header($data)) {
                $reasons[] = 'signature:lz4';
            }
            if (self::startsWithTarHeader($data)) {
                $reasons[] = 'signature:tar';
            }
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @return list<string>
     */
    private static function nestedPackageNameCandidateReasons(string $entryName): array
    {
        $lower = strtolower($entryName);
        $extensionMap = [
            '.tar.gz' => 'extension:gzip-tar',
            '.tgz' => 'extension:gzip-tar',
            '.tar.zlib' => 'extension:zlib-tar',
            '.tar.deflate' => 'extension:raw-deflate-tar',
            '.tar.lz4' => 'extension:lz4-tar',
            '.tlz4' => 'extension:lz4-tar',
            '.tar' => 'extension:tar',
            '.zip.gz' => 'extension:gzip-zip',
            '.zip.zlib' => 'extension:zlib-zip',
            '.zip.deflate' => 'extension:raw-deflate-zip',
            '.zip.lz4' => 'extension:lz4-zip',
            '.zip' => 'extension:zip',
            '.docx' => 'extension:zip-package',
            '.dotx' => 'extension:zip-package',
            '.docm' => 'extension:zip-package',
            '.odt' => 'extension:zip-package',
            '.ods' => 'extension:zip-package',
            '.odp' => 'extension:zip-package',
            '.epub' => 'extension:zip-package',
        ];

        $reasons = [];
        foreach ($extensionMap as $suffix => $reason) {
            if (str_ends_with($lower, $suffix)) {
                $reasons[] = $reason;
                break;
            }
        }

        return $reasons;
    }

    /**
     * @return list<string>
     */
    private static function candidateTarFormats(string $bytes): array
    {
        $formats = [];
        if (self::startsWithGzipHeader($bytes)) {
            $formats[] = self::FORMAT_GZIP_TAR;
        }

        if (self::startsWithZlibHeader($bytes)) {
            $formats[] = self::FORMAT_ZLIB_TAR;
        }

        if (self::startsWithLz4Header($bytes)) {
            $formats[] = self::FORMAT_LZ4_TAR;
        }

        $formats[] = self::FORMAT_TAR;
        $formats[] = self::FORMAT_RAW_DEFLATE_TAR;

        return array_values(array_unique($formats));
    }

    /**
     * @return list<string>
     */
    private static function candidateZipFormats(string $bytes): array
    {
        $formats = [];
        if (self::startsWithGzipHeader($bytes)) {
            $formats[] = self::FORMAT_GZIP_ZIP;
        }

        if (self::startsWithZlibHeader($bytes)) {
            $formats[] = self::FORMAT_ZLIB_ZIP;
        }

        if (self::startsWithLz4Header($bytes)) {
            $formats[] = self::FORMAT_LZ4_ZIP;
        }

        if (self::startsWithZipHeader($bytes)) {
            $formats[] = self::FORMAT_ZIP;
        }

        $formats[] = self::FORMAT_RAW_DEFLATE_ZIP;

        return array_values(array_unique($formats));
    }

    private static function startsWithZipHeader(string $bytes): bool
    {
        return str_starts_with($bytes, "PK\x03\x04")
            || str_starts_with($bytes, "PK\x05\x06");
    }

    private static function startsWithGzipHeader(string $bytes): bool
    {
        return strlen($bytes) >= 2 && ord($bytes[0]) === 0x1f && ord($bytes[1]) === 0x8b;
    }

    private static function startsWithZlibHeader(string $bytes): bool
    {
        if (strlen($bytes) < 2) {
            return false;
        }

        $cmf = ord($bytes[0]);
        $flg = ord($bytes[1]);

        return ($cmf & 0x0f) === 8
            && (($cmf >> 4) & 0x0f) <= 7
            && (($cmf << 8) + $flg) % 31 === 0;
    }

    private static function startsWithLz4Header(string $bytes): bool
    {
        if (strlen($bytes) < 4) {
            return false;
        }

        $values = unpack('Vmagic', substr($bytes, 0, 4));
        if (!is_array($values)) {
            return false;
        }

        $magic = (int) $values['magic'];

        return $magic === 0x184d2204 || ($magic >= 0x184d2a50 && $magic <= 0x184d2a5f);
    }

    private static function startsWithTarHeader(string $bytes): bool
    {
        return strlen($bytes) >= 512
            && (
                substr($bytes, 257, 6) === "ustar\0"
                || substr($bytes, 257, 8) === "ustar  \0"
            );
    }

    /**
     * @return array{
     *     format:string,
     *     name:string,
     *     signatureBytesHex:string,
     *     streamHeaderSize:int,
     *     streamFlagsHex:?string,
     *     blockSize100k:?int
     * }|null
     */
    private static function unsupportedCompressionSignature(string $bytes): ?array
    {
        if (strlen($bytes) >= 4
            && str_starts_with($bytes, 'BZh')
            && ord($bytes[3]) >= ord('1')
            && ord($bytes[3]) <= ord('9')
        ) {
            return [
                'format' => 'bzip2',
                'name' => 'bzip2',
                'signatureBytesHex' => bin2hex(substr($bytes, 0, 4)),
                'streamHeaderSize' => 4,
                'streamFlagsHex' => null,
                'blockSize100k' => (int) $bytes[3],
            ];
        }

        if (str_starts_with($bytes, "\xfd" . '7zXZ' . "\0")) {
            return [
                'format' => 'xz',
                'name' => 'xz',
                'signatureBytesHex' => bin2hex(substr($bytes, 0, 6)),
                'streamHeaderSize' => 12,
                'streamFlagsHex' => strlen($bytes) >= 8 ? bin2hex(substr($bytes, 6, 2)) : null,
                'blockSize100k' => null,
            ];
        }

        return null;
    }

    /**
     * @return array{format:string, kind:?string}|null
     */
    private static function unsupportedCompressionNameCandidate(string $sourceName): ?array
    {
        $lower = strtolower($sourceName);
        $compressedSuffixes = [
            '.tar.bz2' => ['bzip2', self::PACKAGE_KIND_TAR],
            '.tbz2' => ['bzip2', self::PACKAGE_KIND_TAR],
            '.tbz' => ['bzip2', self::PACKAGE_KIND_TAR],
            '.zip.bz2' => ['bzip2', self::PACKAGE_KIND_ZIP],
            '.tar.xz' => ['xz', self::PACKAGE_KIND_TAR],
            '.txz' => ['xz', self::PACKAGE_KIND_TAR],
            '.zip.xz' => ['xz', self::PACKAGE_KIND_ZIP],
            '.bz2' => ['bzip2', null],
            '.xz' => ['xz', null],
        ];

        foreach ($compressedSuffixes as $suffix => [$format, $kind]) {
            if (str_ends_with($lower, $suffix)) {
                return [
                    'format' => $format,
                    'kind' => $kind,
                ];
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $errors
     */
    private static function formatDetectionDetails(array $errors): string
    {
        if ($errors === []) {
            return '';
        }

        $parts = [];
        foreach ($errors as $format => $message) {
            $parts[] = "{$format}: {$message}";
        }

        return implode('; ', $parts);
    }

    private static function assertLimit(?int $limit, string $label): void
    {
        if ($limit !== null && $limit < 0) {
            throw new \RuntimeException("{$label} must not be negative");
        }
    }
}
