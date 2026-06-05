<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfTextExtractor
{
    private const POSITIONED_TEXT_WORD_GAP = 12.0;
    private const SIMPLE_TEXT_ADVANCE_RATIO = 0.5;
    private const INLINE_IMAGE_KEY_ABBREVIATIONS = [
        'BPC' => 'BitsPerComponent',
        'CS' => 'ColorSpace',
        'D' => 'Decode',
        'DP' => 'DecodeParms',
        'F' => 'Filter',
        'H' => 'Height',
        'I' => 'Interpolate',
        'IM' => 'ImageMask',
        'W' => 'Width',
    ];
    private const INLINE_IMAGE_VALUE_ABBREVIATIONS = [
        'A85' => 'ASCII85Decode',
        'AHx' => 'ASCIIHexDecode',
        'CCF' => 'CCITTFaxDecode',
        'CMYK' => 'DeviceCMYK',
        'DCT' => 'DCTDecode',
        'Fl' => 'FlateDecode',
        'G' => 'DeviceGray',
        'I' => 'Indexed',
        'LZW' => 'LZWDecode',
        'RGB' => 'DeviceRGB',
        'RL' => 'RunLengthDecode',
    ];
    private const PDF_DOC_ENCODING_OVERRIDES = [
        0x18 => 0x02d8,
        0x19 => 0x02c7,
        0x1a => 0x02c6,
        0x1b => 0x02d9,
        0x1c => 0x02dd,
        0x1d => 0x02db,
        0x1e => 0x02da,
        0x1f => 0x02dc,
        0x7f => 0xfffd,
        0x80 => 0x2022,
        0x81 => 0x2020,
        0x82 => 0x2021,
        0x83 => 0x2026,
        0x84 => 0x2014,
        0x85 => 0x2013,
        0x86 => 0x0192,
        0x87 => 0x2044,
        0x88 => 0x2039,
        0x89 => 0x203a,
        0x8a => 0x2212,
        0x8b => 0x2030,
        0x8c => 0x201e,
        0x8d => 0x201c,
        0x8e => 0x201d,
        0x8f => 0x2018,
        0x90 => 0x2019,
        0x91 => 0x201a,
        0x92 => 0x2122,
        0x93 => 0xfb01,
        0x94 => 0xfb02,
        0x95 => 0x0141,
        0x96 => 0x0152,
        0x97 => 0x0160,
        0x98 => 0x0178,
        0x99 => 0x017d,
        0x9a => 0x0131,
        0x9b => 0x0142,
        0x9c => 0x0153,
        0x9d => 0x0161,
        0x9e => 0x017e,
        0x9f => 0xfffd,
        0xa0 => 0x20ac,
    ];
    private const BASE14_FONT_WIDTH_ALIASES = [
        'Courier' => 'Courier',
        'Courier-Bold' => 'Courier',
        'Courier-Oblique' => 'Courier',
        'Courier-BoldOblique' => 'Courier',
        'NimbusMonoPS-Regular' => 'Courier',
        'NimbusMonoPS-Bold' => 'Courier',
        'NimbusMonoPS-Italic' => 'Courier',
        'NimbusMonoPS-BoldItalic' => 'Courier',
        'Helvetica' => 'Helvetica',
        'Helvetica-Oblique' => 'Helvetica',
        'NimbusSans-Regular' => 'Helvetica',
        'NimbusSans-Italic' => 'Helvetica',
        'Helvetica-Bold' => 'Helvetica-Bold',
        'Helvetica-BoldOblique' => 'Helvetica-Bold',
        'NimbusSans-Bold' => 'Helvetica-Bold',
        'NimbusSans-BoldItalic' => 'Helvetica-Bold',
        'Times-Roman' => 'Times-Roman',
        'NimbusRoman-Regular' => 'Times-Roman',
        'Times-Bold' => 'Times-Bold',
        'NimbusRoman-Bold' => 'Times-Bold',
        'Times-Italic' => 'Times-Italic',
        'NimbusRoman-Italic' => 'Times-Italic',
        'Times-BoldItalic' => 'Times-BoldItalic',
        'NimbusRoman-BoldItalic' => 'Times-BoldItalic',
        'Symbol' => 'Symbol',
        'StandardSymbolsPS' => 'Symbol',
        'ZapfDingbats' => 'ZapfDingbats',
        'D050000L' => 'ZapfDingbats',
    ];
    private const BASE14_ASCII_WIDTHS = [
        'Helvetica' => '278 278 355 556 556 889 667 222 333 333 389 584 278 333 278 278 556 556 556 556 556 556 556 556 556 556 278 278 584 584 584 556 1015 667 667 722 722 667 611 778 722 278 500 667 556 833 722 778 667 778 722 667 611 722 667 944 667 667 611 278 278 278 469 556 222 556 556 500 556 556 278 556 556 222 222 500 222 833 556 556 556 556 333 500 278 556 500 722 500 500 500 334 260 334 584',
        'Helvetica-Bold' => '278 333 474 556 556 889 722 278 333 333 389 584 278 333 278 278 556 556 556 556 556 556 556 556 556 556 333 333 584 584 584 611 975 722 722 722 722 667 611 778 722 278 556 722 611 833 722 778 667 778 722 667 611 722 667 944 667 667 611 333 278 333 584 556 278 556 611 556 611 556 333 611 611 278 278 556 278 889 611 611 611 611 389 556 333 611 556 778 556 556 500 389 280 389 584',
        'Times-Roman' => '250 333 408 500 500 833 778 333 333 333 500 564 250 333 250 278 500 500 500 500 500 500 500 500 500 500 278 278 564 564 564 444 921 722 667 667 722 611 556 722 722 333 389 722 611 889 722 722 556 722 667 556 611 722 722 944 722 722 611 333 278 333 469 500 333 444 500 444 500 444 333 500 500 278 278 500 278 778 500 500 500 500 333 389 278 500 500 722 500 500 444 480 200 480 541',
        'Times-Bold' => '250 333 555 500 500 1000 833 333 333 333 500 570 250 333 250 278 500 500 500 500 500 500 500 500 500 500 333 333 570 570 570 500 930 722 667 722 722 667 611 778 778 389 500 778 667 944 722 778 611 778 722 556 667 722 722 1000 722 722 667 333 278 333 581 500 333 500 556 444 556 444 333 500 556 278 333 556 278 833 556 500 556 556 444 389 333 556 500 722 500 500 444 394 220 394 520',
        'Times-Italic' => '250 333 420 500 500 833 778 333 333 333 500 675 250 333 250 278 500 500 500 500 500 500 500 500 500 500 333 333 675 675 675 500 920 611 611 667 722 611 611 722 722 333 444 667 556 833 667 722 611 722 611 500 556 722 611 833 611 556 556 389 278 389 422 500 333 500 500 444 500 444 278 500 500 278 278 444 278 722 500 500 500 500 389 389 278 500 444 667 444 444 389 400 275 400 541',
        'Times-BoldItalic' => '250 389 555 500 500 833 778 333 333 333 500 570 250 333 250 278 500 500 500 500 500 500 500 500 500 500 333 333 570 570 570 500 832 667 667 667 722 667 667 722 778 389 500 667 611 889 722 722 611 722 667 556 611 722 667 889 667 611 611 333 278 333 570 500 333 500 500 444 500 444 333 500 556 278 278 500 278 778 556 500 500 500 389 389 278 556 444 667 500 444 389 348 220 348 570',
        'Symbol' => '250 333 713 500 549 833 778 439 333 333 500 549 250 549 250 278 500 500 500 500 500 500 500 500 500 500 278 278 549 549 549 444 549 722 667 722 612 611 763 603 722 333 631 722 686 889 722 722 768 741 556 592 611 690 439 768 645 795 611 333 863 333 658 500 500 631 549 549 494 439 521 411 603 329 603 549 549 576 521 549 549 521 549 603 439 576 713 686 493 686 494 480 200 480 549',
        'ZapfDingbats' => '278 974 961 974 980 719 789 790 791 690 960 939 549 855 911 933 911 945 974 755 846 762 761 571 677 763 760 759 754 494 552 537 577 692 786 788 788 790 793 794 816 823 789 841 823 833 816 831 923 744 723 749 790 792 695 776 768 792 759 707 708 682 701 826 815 789 789 707 687 696 689 786 787 713 791 785 791 873 761 762 762 759 759 892 892 788 784 438 138 277 415 392 392 668 668',
    ];

    /**
     * @var array<int, array{generation: int, body: string}>
     */
    private array $currentObjectReferenceOwners = [];

    /**
     * @var array<int, array<int, string>>
     */
    private array $currentDirectObjectBodiesByGeneration = [];

    /**
     * @return list<string>
     */
    public function extractTextRuns(string $pdfBytes): array
    {
        $runs = [];
        foreach ($this->contentStreamsWithFontMaps($pdfBytes) as $entry) {
            foreach ($this->textRunsFromContentStream(
                $entry['stream'],
                $entry['fontToUnicodeMaps'],
                $entry['markedContentProperties']
            ) as $run) {
                if ($run !== '') {
                    $runs[] = $run;
                }
            }
        }

        return $runs;
    }

    public function extractPlainText(string $pdfBytes): string
    {
        return implode("\n", $this->extractTextLines($pdfBytes));
    }

    /**
     * Native boundary for marker.pdf.extract_text::naive_get_text.
     *
     * Upstream asks pypdfium for bounded text per page and appends a newline
     * after each page. Here page /Contents streams are the native page
     * boundary, with a stream-only fallback for lightweight fixtures.
     */
    public function naiveGetText(string $pdfBytes): string
    {
        $text = '';
        foreach ($this->extractPageTexts($pdfBytes) as $pageText) {
            $text .= $pageText . "\n";
        }

        return $text;
    }

    /**
     * Native boundary for marker.pdf.extract_text::get_length_of_text.
     */
    public function getLengthOfText(string $filepath): int
    {
        $bytes = @file_get_contents($filepath);
        if (!is_string($bytes)) {
            throw new \InvalidArgumentException('Unable to read PDF text-length source: ' . $filepath);
        }

        return $this->length(trim($this->naiveGetText($bytes)));
    }

    /**
     * Native boundary for marker.pdf.extract_text::get_text_blocks metadata.
     *
     * Upstream obtains `pdf_toc` from pypdfium's document outline adapter
     * before model execution. This reduced boundary extracts the same
     * title/level/page shape, plus trailer `/Info` strings for WordPress review
     * metadata, without running Python or external PDF tooling.
     *
     * @return array{pdf_toc: list<array{title: string, level: int, page: int}>, document_info: array<string, string>, pages: int}
     */
    public function extractOutlineMetadata(string $pdfBytes): array
    {
        if ($this->hasEncryptedTrailer($pdfBytes)) {
            return [
                'pdf_toc' => [],
                'document_info' => [],
                'pages' => 0,
            ];
        }

        $objects = $this->pdfObjects($pdfBytes);

        return [
            'pdf_toc' => $this->pdfTocFromObjects($objects),
            'document_info' => $this->documentInfoFromPdf($pdfBytes, $objects),
            'pages' => count($this->orderedPageObjectNumbers($objects)),
        ];
    }

    /**
     * @return list<string>
     */
    public function extractPageLabels(string $pdfBytes): array
    {
        if ($this->hasEncryptedTrailer($pdfBytes)) {
            return [];
        }

        $objects = $this->pdfObjects($pdfBytes);
        $pageObjectNumbers = $this->orderedPageObjectNumbers($objects);
        $pageCount = count($pageObjectNumbers);
        if ($pageCount === 0) {
            $pageCount = count($this->allDecodedStreams($pdfBytes, $objects));
        }

        return $this->pageLabels($objects, $pageCount);
    }

    /**
     * Native review boundary for page resource image XObjects.
     *
     * Upstream markerPDF gets page text from pdftext/PDFium text pages, while
     * image XObjects are rendered separately through marker.pdf.images and
     * converted to RGB. This exposes image-resource metadata for WordPress
     * review without promoting raster payload bytes into visible paragraphs.
     *
     * @return array{
     *     source: string,
     *     review_only: true,
     *     encrypted: bool,
     *     page_count: int,
     *     image_xobject_count: int,
     *     invoked_image_xobject_count: int,
     *     uninvoked_image_xobject_count: int,
     *     entries: list<array<string, mixed>>,
     *     executes_python_or_models: false,
     *     executes_external_pdf_tools: false
     * }
     */
    public function extractImageXObjectBoundaryReview(string $pdfBytes): array
    {
        $review = [
            'source' => 'pdf_image_xobject_boundary_review',
            'review_only' => true,
            'encrypted' => $this->hasEncryptedTrailer($pdfBytes),
            'page_count' => 0,
            'image_xobject_count' => 0,
            'invoked_image_xobject_count' => 0,
            'uninvoked_image_xobject_count' => 0,
            'entries' => [],
            'executes_python_or_models' => false,
            'executes_external_pdf_tools' => false,
        ];

        if ($review['encrypted']) {
            return $review;
        }

        $objects = $this->pdfObjects($pdfBytes);
        $pageObjectNumbers = $this->orderedPageObjectNumbers($objects);
        $optionalContentStates = $this->optionalContentVisibilityStates($objects);
        $review['page_count'] = count($pageObjectNumbers);

        foreach ($pageObjectNumbers as $pageIndex => $pageObjectNumber) {
            if (!isset($objects[$pageObjectNumber])) {
                continue;
            }

            $resourceDictionary = $this->pageResourceDictionaryBody($pageObjectNumber, $objects);
            if ($resourceDictionary === null) {
                continue;
            }

            foreach ($this->imageXObjectBoundaryEntriesForResourceOwner(
                $pageIndex,
                $pageObjectNumber,
                $resourceDictionary,
                $objects,
                $this->pageDecodedContentStreams($objects[$pageObjectNumber], $objects),
                $optionalContentStates
            ) as $entry) {
                $review['entries'][] = $entry;
                $review['image_xobject_count']++;
                if ($entry['invoked']) {
                    $review['invoked_image_xobject_count']++;
                } else {
                    $review['uninvoked_image_xobject_count']++;
                }
            }
        }

        return $review;
    }

    /**
     * Review xref-stream type-2 object-stream member indexes before WordPress
     * text import. A zero-width third /W field has a strict default index of 0
     * in PDFium, while this native fallback can recover by object number for
     * current-base import. Expose that recovery instead of making it silent.
     *
     * @return array{
     *     source: string,
     *     review_only: true,
     *     encrypted: bool,
     *     compressed_entry_count: int,
     *     zero_width_index_entry_count: int,
     *     recovered_zero_width_member_count: int,
     *     ambiguous_zero_width_member_count: int,
     *     nonzero_generation_reference_count: int,
     *     compressed_generation_zero_boundary_count: int,
     *     strict_dependency_rejection_count: int,
     *     stream_member_rejection_count: int,
     *     direct_xref_stream_owner_cycle_count: int,
     *     suppressed_hybrid_type2_entry_count: int,
     *     hybrid_table_free_owner_count: int,
     *     xref_stream_free_entry_count: int,
     *     xref_stream_free_owner_count: int,
     *     entries: list<array<string, mixed>>,
     *     suppressed_hybrid_entries: list<array<string, mixed>>,
     *     free_entries: list<array<string, mixed>>,
     *     executes_python_or_models: false,
     *     executes_external_pdf_tools: false
     * }
     */
    public function extractXrefObjectStreamIndexReview(string $pdfBytes): array
    {
        $review = [
            'source' => 'pdf_xref_object_stream_index_review',
            'review_only' => true,
            'encrypted' => $this->hasEncryptedTrailer($pdfBytes),
            'compressed_entry_count' => 0,
            'zero_width_index_entry_count' => 0,
            'recovered_zero_width_member_count' => 0,
            'ambiguous_zero_width_member_count' => 0,
            'nonzero_generation_reference_count' => 0,
            'compressed_generation_zero_boundary_count' => 0,
            'strict_dependency_rejection_count' => 0,
            'stream_member_rejection_count' => 0,
            'direct_xref_stream_owner_cycle_count' => 0,
            'suppressed_hybrid_type2_entry_count' => 0,
            'hybrid_table_free_owner_count' => 0,
            'xref_stream_free_entry_count' => 0,
            'xref_stream_free_owner_count' => 0,
            'entries' => [],
            'suppressed_hybrid_entries' => [],
            'free_entries' => [],
            'executes_python_or_models' => false,
            'executes_external_pdf_tools' => false,
        ];

        if ($review['encrypted']) {
            return $review;
        }

        $definitions = $this->directObjectDefinitions($pdfBytes);
        if ($definitions === []) {
            return $review;
        }

        $preliminaryObjects = $this->latestDirectObjects($definitions);
        $xrefEntries = $this->xrefEntries($pdfBytes, $preliminaryObjects, $definitions);
        if ($xrefEntries === []) {
            return $review;
        }

        $review['suppressed_hybrid_entries'] = $this->xrefHybridSuppressedObjectStreamEntries(
            $pdfBytes,
            $preliminaryObjects,
            $definitions
        );
        $review['suppressed_hybrid_type2_entry_count'] = count($review['suppressed_hybrid_entries']);
        foreach ($review['suppressed_hybrid_entries'] as $suppressedEntry) {
            if (($suppressedEntry['owner_policy'] ?? null) === 'hybrid_table_free_entry_preserved') {
                $review['hybrid_table_free_owner_count']++;
            }
        }
        $review['free_entries'] = $this->xrefStreamFreeOwnerEntries($pdfBytes, $preliminaryObjects, $definitions);
        $review['xref_stream_free_entry_count'] = count($review['free_entries']);
        foreach ($review['free_entries'] as $freeEntry) {
            if (($freeEntry['direct_object_suppressed'] ?? false) === true || ($freeEntry['previous_entry_suppressed'] ?? false) === true) {
                $review['xref_stream_free_owner_count']++;
            }
        }

        $objects = $this->liveDirectObjects($definitions, $xrefEntries);
        $objects = $this->withReferencedDirectGenerationObjects($objects, $definitions, $xrefEntries);
        $objects = $this->withObjectStreamObjects($objects, $xrefEntries);
        $objectOwners = $this->objectReferenceOwners($objects, $definitions, $xrefEntries);
        $nonzeroGenerationReferences = $this->nonZeroGenerationObjectReferences($objects);

        foreach ($xrefEntries as $objectNumber => $entry) {
            if (($entry['type'] ?? null) !== 2 || !isset($entry['objectStream'])) {
                continue;
            }

            $review['compressed_entry_count']++;
            $indexIsExplicit = ($entry['indexIsExplicit'] ?? true) === true;
            if (!$indexIsExplicit) {
                $review['zero_width_index_entry_count']++;
            }

            $objectStreamNumber = (int) $entry['objectStream'];
            $defaultMemberIndex = (int) ($entry['index'] ?? 0);
            $objectStreamXrefEntry = $xrefEntries[$objectStreamNumber] ?? null;
            $objectStreamOwner = isset($objects[$objectStreamNumber])
                ? $this->directObjectDefinitionForBody($definitions[$objectStreamNumber] ?? [], $objects[$objectStreamNumber])
                : null;
            $memberTable = isset($objects[$objectStreamNumber])
                ? $this->decodedObjectStreamMemberTable($objects[$objectStreamNumber], $objects)
                : null;
            $members = $memberTable['members'] ?? [];
            $memberAtDefaultIndex = $this->objectStreamMemberAtHeaderIndex($members, $defaultMemberIndex);
            $membersByObjectNumber = [];
            foreach ($members as $member) {
                if ($member['objectNumber'] === $objectNumber) {
                    $membersByObjectNumber[] = $member;
                }
            }
            $matchingHeaderObjectNumberCount = count($membersByObjectNumber);
            $memberByObjectNumber = $matchingHeaderObjectNumberCount === 1 ? $membersByObjectNumber[0] : null;
            $directXrefStreamOwner = $this->latestDirectXrefStreamDefinition($definitions[$objectNumber] ?? []);
            $ownerCycleRejected = $directXrefStreamOwner !== null
                && isset($objects[$objectNumber])
                && $objects[$objectNumber] === $directXrefStreamOwner['body'];
            if ($ownerCycleRejected) {
                $review['direct_xref_stream_owner_cycle_count']++;
            }

            $strictMemberMatch = $memberAtDefaultIndex !== null
                && $memberAtDefaultIndex['objectNumber'] === $objectNumber;
            $recoveredByObjectNumber = !$indexIsExplicit
                && !$strictMemberMatch
                && $memberByObjectNumber !== null;
            $ambiguousZeroWidthMember = !$indexIsExplicit
                && !$strictMemberMatch
                && $matchingHeaderObjectNumberCount > 1;
            if ($recoveredByObjectNumber) {
                $review['recovered_zero_width_member_count']++;
            }
            if ($ambiguousZeroWidthMember) {
                $review['ambiguous_zero_width_member_count']++;
            }
            if (!$strictMemberMatch) {
                $review['strict_dependency_rejection_count']++;
            }
            $selectedMember = $strictMemberMatch
                ? $memberAtDefaultIndex
                : ($recoveredByObjectNumber ? $memberByObjectNumber : null);
            $selectedMemberBody = $memberTable !== null && $selectedMember !== null
                ? $this->objectStreamMemberBody($memberTable, $selectedMember)
                : null;
            $selectedMemberIsStream = $selectedMemberBody !== null
                && $this->objectStreamMemberIsTopLevelStreamObject($selectedMemberBody);
            $objectStreamCarrierHasFilter = isset($objects[$objectStreamNumber])
                && $this->objectStreamCarrierHasFilters($objects[$objectStreamNumber], $objects);
            $streamMemberRejected = $selectedMemberIsStream && $objectStreamCarrierHasFilter;
            if ($streamMemberRejected) {
                $review['stream_member_rejection_count']++;
            }

            $referencedGenerations = array_map('intval', array_keys($nonzeroGenerationReferences[$objectNumber] ?? []));
            sort($referencedGenerations, SORT_NUMERIC);
            $selectedGeneration = $objectOwners[$objectNumber]['generation'] ?? null;
            $generationPolicy = $this->objectStreamGenerationSelectionPolicy(
                is_int($selectedGeneration) ? $selectedGeneration : null,
                $referencedGenerations
            );
            if ($referencedGenerations !== []) {
                $review['nonzero_generation_reference_count']++;
            }
            if ($generationPolicy === 'compressed_generation_zero_not_selected_for_nonzero_reference') {
                $review['compressed_generation_zero_boundary_count']++;
            }

            $review['entries'][] = [
                'object_number' => $objectNumber,
                'object_stream' => $objectStreamNumber,
                'xref_member_index' => $defaultMemberIndex,
                'object_stream_selected_generation' => $objectStreamOwner['generation'] ?? null,
                'object_stream_selected_offset' => $objectStreamOwner['offset'] ?? null,
                'object_stream_xref_entry_type' => $objectStreamXrefEntry['type'] ?? null,
                'object_stream_xref_generation' => $objectStreamXrefEntry['generation'] ?? null,
                'object_stream_xref_offset' => $objectStreamXrefEntry['offset'] ?? null,
                'object_stream_owner_policy' => $this->objectStreamCarrierOwnerPolicy($objectStreamXrefEntry, $objectStreamOwner),
                'index_is_explicit' => $indexIsExplicit,
                'index_field_is_zero_width' => !$indexIsExplicit,
                'strict_member_index' => $defaultMemberIndex,
                'actual_member_index' => $memberByObjectNumber['index'] ?? null,
                'object_stream_member_count' => count($members),
                'matching_header_object_number_count' => $matchingHeaderObjectNumberCount,
                'duplicate_header_object_number' => $matchingHeaderObjectNumberCount > 1,
                'compressed_member_generation' => 0,
                'selected_object_generation' => $selectedGeneration,
                'nonzero_referenced_generations' => $referencedGenerations,
                'has_nonzero_generation_reference' => $referencedGenerations !== [],
                'generation_boundary_policy' => $generationPolicy,
                'strict_member_match' => $strictMemberMatch,
                'recovered_by_object_number' => $recoveredByObjectNumber,
                'ambiguous_zero_width_member' => $ambiguousZeroWidthMember,
                'strict_dependency_would_reject' => !$strictMemberMatch,
                'object_stream_carrier_has_filter' => $objectStreamCarrierHasFilter,
                'object_stream_member_is_stream' => $selectedMemberIsStream,
                'stream_member_rejected' => $streamMemberRejected,
                'direct_xref_stream_owner' => $directXrefStreamOwner !== null,
                'owner_cycle_rejected' => $ownerCycleRejected,
                'owner_policy' => $ownerCycleRejected
                    ? 'direct_xref_stream_owner_preserved'
                    : 'compressed_object_stream_member',
                'selection_policy' => $this->objectStreamIndexSelectionPolicy(
                    $indexIsExplicit,
                    $strictMemberMatch,
                    $matchingHeaderObjectNumberCount > 0,
                    $ambiguousZeroWidthMember
                ),
                'review_only' => true,
            ];
        }

        return $review;
    }

    /**
     * Review inherited /Prev xref-stream type-2 rows before WordPress import.
     *
     * Upstream reaches object-stream members through pdftext/PDFium parser
     * ownership. The native fallback exposes whether a previous compressed
     * member is still backed by the same selected object-stream carrier, or is
     * skipped because the current revision changed that carrier storage.
     *
     * @return array{
     *     source: string,
     *     review_only: true,
     *     encrypted: bool,
     *     inherited_type2_entry_count: int,
     *     preserved_type2_entry_count: int,
     *     skipped_type2_entry_count: int,
     *     skipped_unselected_carrier_count: int,
     *     skipped_replaced_carrier_count: int,
     *     skipped_current_free_object_count: int,
     *     same_carrier_storage_count: int,
     *     entries: list<array<string, mixed>>,
     *     executes_python_or_models: false,
     *     executes_external_pdf_tools: false
     * }
     */
    public function extractXrefPrevObjectStreamGenerationReview(string $pdfBytes): array
    {
        $review = [
            'source' => 'pdf_xref_prev_object_stream_generation_review',
            'review_only' => true,
            'encrypted' => $this->hasEncryptedTrailer($pdfBytes),
            'inherited_type2_entry_count' => 0,
            'preserved_type2_entry_count' => 0,
            'skipped_type2_entry_count' => 0,
            'skipped_unselected_carrier_count' => 0,
            'skipped_replaced_carrier_count' => 0,
            'skipped_current_free_object_count' => 0,
            'same_carrier_storage_count' => 0,
            'entries' => [],
            'executes_python_or_models' => false,
            'executes_external_pdf_tools' => false,
        ];

        if ($review['encrypted']) {
            return $review;
        }

        $definitions = $this->directObjectDefinitions($pdfBytes);
        if ($definitions === []) {
            return $review;
        }

        $offset = $this->latestStartxrefOffset($pdfBytes, $definitions);
        if ($offset === null) {
            return $review;
        }

        $objects = $this->latestDirectObjects($definitions);
        $entries = $this->xrefPrevObjectStreamGenerationReviewEntries($pdfBytes, $offset, $objects, $definitions);
        foreach ($entries as $entry) {
            $review['inherited_type2_entry_count']++;
            if (($entry['skipped'] ?? false) === true) {
                $review['skipped_type2_entry_count']++;
            } else {
                $review['preserved_type2_entry_count']++;
            }

            if (($entry['same_carrier_storage'] ?? false) === true) {
                $review['same_carrier_storage_count']++;
            }

            $ownerPolicy = $entry['owner_policy'] ?? null;
            if ($ownerPolicy === 'skipped_prev_carrier_absent' || $ownerPolicy === 'skipped_prev_carrier_not_direct') {
                $review['skipped_unselected_carrier_count']++;
            } elseif ($ownerPolicy === 'skipped_current_replaced_carrier_storage') {
                $review['skipped_replaced_carrier_count']++;
            } elseif ($ownerPolicy === 'skipped_current_free_object_generation') {
                $review['skipped_current_free_object_count']++;
            }
        }
        $review['entries'] = $entries;

        return $review;
    }

    /**
     * Review xref-stream /Filter and /Length operands before WordPress import.
     *
     * Upstream reaches this through pdftext/PDFium object loading. The native
     * fallback exposes whether indirect xref-stream stream operands are backed
     * by current xref-selected direct objects, instead of silently trusting
     * scanned fallback objects.
     *
     * @return array{
     *     source: string,
     *     review_only: true,
     *     encrypted: bool,
     *     xref_stream_count: int,
     *     indirect_filter_count: int,
     *     indirect_length_count: int,
     *     xref_selected_operand_count: int,
     *     unresolved_operand_count: int,
     *     entries: list<array<string, mixed>>,
     *     executes_python_or_models: false,
     *     executes_external_pdf_tools: false
     * }
     */
    public function extractXrefStreamFilterLengthOwnerReview(string $pdfBytes): array
    {
        $review = [
            'source' => 'pdf_xref_stream_filter_length_owner_review',
            'review_only' => true,
            'encrypted' => $this->hasEncryptedTrailer($pdfBytes),
            'xref_stream_count' => 0,
            'indirect_filter_count' => 0,
            'indirect_length_count' => 0,
            'xref_selected_operand_count' => 0,
            'unresolved_operand_count' => 0,
            'entries' => [],
            'executes_python_or_models' => false,
            'executes_external_pdf_tools' => false,
        ];

        if ($review['encrypted']) {
            return $review;
        }

        $definitions = $this->directObjectDefinitions($pdfBytes);
        if ($definitions === []) {
            return $review;
        }

        $objects = $this->latestDirectObjects($definitions);
        $xrefEntries = $this->xrefEntries($pdfBytes, $objects, $definitions);
        $startxrefOffset = $this->latestStartxrefOffset($pdfBytes, $definitions);

        $previousOwners = $this->currentObjectReferenceOwners;
        $objects = $this->liveDirectObjects($definitions, $xrefEntries);
        $objects = $this->withReferencedDirectGenerationObjects($objects, $definitions, $xrefEntries);
        $this->currentObjectReferenceOwners = $this->objectReferenceOwners($objects, $definitions, $xrefEntries);
        $objects = $this->withObjectStreamObjects($objects, $xrefEntries);
        $this->currentObjectReferenceOwners = $this->objectReferenceOwners($objects, $definitions, $xrefEntries);

        try {
            foreach ($definitions as $objectNumber => $objectDefinitions) {
                foreach ($objectDefinitions as $definition) {
                    if (preg_match('/\/Type\s*\/XRef\b/s', $definition['body']) !== 1) {
                        continue;
                    }

                    $review['xref_stream_count']++;
                    $dict = $this->dictionaryObjectBody($definition['body']);
                    if ($dict === null) {
                        continue;
                    }

                    $filterOperands = $this->xrefStreamOperandReviews($dict, 'Filter', $objects, $xrefEntries, $definitions);
                    $lengthOperands = $this->xrefStreamOperandReviews($dict, 'Length', $objects, $xrefEntries, $definitions);
                    $operands = array_merge($filterOperands, $lengthOperands);

                    $filterIndirectCount = $this->xrefStreamIndirectOperandCount($filterOperands);
                    $lengthIndirectCount = $this->xrefStreamIndirectOperandCount($lengthOperands);
                    $selectedOperandCount = $this->xrefStreamSelectedOperandCount($operands);
                    $unresolvedOperandCount = $this->xrefStreamUnresolvedOperandCount($operands);

                    $review['indirect_filter_count'] += $filterIndirectCount;
                    $review['indirect_length_count'] += $lengthIndirectCount;
                    $review['xref_selected_operand_count'] += $selectedOperandCount;
                    $review['unresolved_operand_count'] += $unresolvedOperandCount;

                    $filters = $this->streamFilters($dict, $objects);
                    $decodedEntries = $this->xrefStreamEntriesFromDefinition($definition, $objects, $definitions);
                    $review['entries'][] = [
                        'object_number' => $objectNumber,
                        'generation' => $definition['generation'],
                        'offset' => $definition['offset'],
                        'startxref_selected' => $startxrefOffset === $definition['offset'],
                        'filters' => $filters ?? [],
                        'filter_resolution_failed' => $filters === null,
                        'declared_length' => $this->streamLength($dict, $objects),
                        'filter_operands' => $filterOperands,
                        'length_operand' => $lengthOperands[0] ?? [
                            'name' => 'Length',
                            'kind' => 'absent',
                            'resolved' => false,
                            'xref_selected' => false,
                            'owner_policy' => 'missing_operand',
                        ],
                        'indirect_filter_count' => $filterIndirectCount,
                        'indirect_length_count' => $lengthIndirectCount,
                        'xref_selected_operand_count' => $selectedOperandCount,
                        'unresolved_operand_count' => $unresolvedOperandCount,
                        'decoded_entry_count' => count($decodedEntries),
                        'decoded_with_current_operands' => $decodedEntries !== [] && $unresolvedOperandCount === 0,
                        'owner_policy' => $this->xrefStreamOperandOwnerPolicy($selectedOperandCount, $unresolvedOperandCount, $operands),
                        'review_only' => true,
                    ];
                }
            }
        } finally {
            $this->currentObjectReferenceOwners = $previousOwners;
        }

        return $review;
    }

    /**
     * Review ToUnicode and Encoding CMap stream operands before WordPress import.
     *
     * Upstream reaches CMap streams through pdftext/PDFium font decoding. The
     * native fallback exposes whether CMap stream `/Filter`, `/Length`, and
     * `/DecodeParms` operands are backed by current xref-selected objects
     * without including the decoded mapping payload in review metadata.
     *
     * @return array{
     *     source: string,
     *     review_only: true,
     *     encrypted: bool,
     *     cmap_stream_count: int,
     *     to_unicode_cmap_stream_count: int,
     *     encoding_cmap_stream_count: int,
     *     indirect_filter_count: int,
     *     indirect_length_count: int,
     *     xref_selected_operand_count: int,
     *     unresolved_operand_count: int,
     *     invalid_filter_operand_count: int,
     *     dictionary_filter_operand_count: int,
     *     malformed_filter_operand_count: int,
     *     invalid_decodeparms_operand_count: int,
     *     malformed_decodeparms_operand_count: int,
     *     invalid_decodeparms_parameter_count: int,
     *     decoded_cmap_count: int,
     *     entries: list<array<string, mixed>>,
     *     executes_python_or_models: false,
     *     executes_external_pdf_tools: false
     * }
     */
    public function extractCMapStreamFilterLengthOwnerReview(string $pdfBytes): array
    {
        $review = [
            'source' => 'pdf_cmap_stream_filter_length_owner_review',
            'review_only' => true,
            'encrypted' => $this->hasEncryptedTrailer($pdfBytes),
            'cmap_stream_count' => 0,
            'to_unicode_cmap_stream_count' => 0,
            'encoding_cmap_stream_count' => 0,
            'indirect_filter_count' => 0,
            'indirect_length_count' => 0,
            'xref_selected_operand_count' => 0,
            'unresolved_operand_count' => 0,
            'invalid_filter_operand_count' => 0,
            'dictionary_filter_operand_count' => 0,
            'malformed_filter_operand_count' => 0,
            'invalid_decodeparms_operand_count' => 0,
            'malformed_decodeparms_operand_count' => 0,
            'invalid_decodeparms_parameter_count' => 0,
            'decoded_cmap_count' => 0,
            'entries' => [],
            'executes_python_or_models' => false,
            'executes_external_pdf_tools' => false,
        ];

        if ($review['encrypted']) {
            return $review;
        }

        $definitions = $this->directObjectDefinitions($pdfBytes);
        if ($definitions === []) {
            return $review;
        }

        $preliminaryObjects = $this->latestDirectObjects($definitions);
        if ($this->startxrefXrefStreamFilterDecodeFailed($pdfBytes, $preliminaryObjects, $definitions)) {
            return $review;
        }

        $xrefEntries = $this->xrefEntries($pdfBytes, $preliminaryObjects, $definitions);
        $objects = $this->pdfObjects($pdfBytes);
        if ($objects === []) {
            return $review;
        }

        $referenceUsages = $this->cMapStreamReferenceUsages($objects);
        foreach ($objects as $objectNumber => $body) {
            $stream = $this->streamDictionaryAndPayload($body, $objects);
            if ($stream === null) {
                continue;
            }

            $dict = $stream['dict'];
            $usages = $referenceUsages[$objectNumber] ?? [];
            if ($usages === [] && !$this->cMapStreamDictionaryLooksLikeCMap($dict, $objects)) {
                continue;
            }

            $operandGroups = [];
            foreach (['Filter', 'DecodeParms', 'Length'] as $name) {
                $operandGroups[$name] = $this->xrefStreamOperandReviews(
                    $dict,
                    $name,
                    $objects,
                    $xrefEntries,
                    $definitions
                );
            }

            $operands = [];
            foreach ($operandGroups as $group) {
                foreach ($group as $operand) {
                    $operands[] = $operand;
                }
            }

            $filterIndirectCount = $this->xrefStreamIndirectOperandCount($operandGroups['Filter']);
            $lengthIndirectCount = $this->xrefStreamIndirectOperandCount($operandGroups['Length']);
            $selectedOperandCount = $this->xrefStreamSelectedOperandCount($operands);
            $unresolvedOperandCount = $this->xrefStreamUnresolvedOperandCount($operands);
            $invalidFilterOperandCount = $this->invalidStreamFilterOperandCount($operandGroups['Filter']);
            $dictionaryFilterOperandCount = $this->dictionaryStreamFilterOperandCount($operandGroups['Filter']);
            $malformedFilterOperandCount = $this->malformedStreamFilterOperandCount($operandGroups['Filter']);
            $filters = $this->streamFilters($dict, $objects);
            $decodeParms = $this->streamDecodeParms($dict, $objects);
            $invalidDecodeParmsOperandCount = $this->invalidStreamDecodeParmsOperandCount($operandGroups['DecodeParms']);
            $malformedDecodeParmsOperandCount = $this->malformedStreamDecodeParmsOperandCount($operandGroups['DecodeParms']);
            $invalidDecodeParmsParameterCount = $this->invalidDecodeParmsParameterCount($filters, $decodeParms, $objects);
            $decoded = $this->decodedCMapBody($body, $objects);
            $owner = $this->currentObjectReferenceOwners[$objectNumber] ?? null;
            $generation = $owner['generation']
                ?? ($xrefEntries[$objectNumber]['generation'] ?? null);

            $hasToUnicodeUsage = $this->cMapReferenceUsagesContain($usages, 'to_unicode');
            $hasEncodingUsage = $this->cMapReferenceUsagesContain($usages, 'encoding_cmap');
            $review['cmap_stream_count']++;
            if ($hasToUnicodeUsage) {
                $review['to_unicode_cmap_stream_count']++;
            }
            if ($hasEncodingUsage) {
                $review['encoding_cmap_stream_count']++;
            }
            $review['indirect_filter_count'] += $filterIndirectCount;
            $review['indirect_length_count'] += $lengthIndirectCount;
            $review['xref_selected_operand_count'] += $selectedOperandCount;
            $review['unresolved_operand_count'] += $unresolvedOperandCount;
            $review['invalid_filter_operand_count'] += $invalidFilterOperandCount;
            $review['dictionary_filter_operand_count'] += $dictionaryFilterOperandCount;
            $review['malformed_filter_operand_count'] += $malformedFilterOperandCount;
            $review['invalid_decodeparms_operand_count'] += $invalidDecodeParmsOperandCount;
            $review['malformed_decodeparms_operand_count'] += $malformedDecodeParmsOperandCount;
            $review['invalid_decodeparms_parameter_count'] += $invalidDecodeParmsParameterCount;
            if ($decoded !== null) {
                $review['decoded_cmap_count']++;
            }

            $review['entries'][] = [
                'object_number' => $objectNumber,
                'generation' => is_int($generation) ? $generation : null,
                'reference_usages' => $usages,
                'cmap_name' => $decoded === null
                    ? $this->pdfNameValueAfterNameResolvingObjects($dict, 'CMapName', $objects)
                    : $this->cMapName($decoded),
                'declared_length' => $this->streamLength($dict, $objects),
                'filters' => $filters ?? [],
                'filter_resolution_failed' => $filters === null,
                'decodeparms_resolution_failed' => $decodeParms === null,
                'invalid_filter_operand_count' => $invalidFilterOperandCount,
                'dictionary_filter_operand_count' => $dictionaryFilterOperandCount,
                'malformed_filter_operand_count' => $malformedFilterOperandCount,
                'invalid_decodeparms_operand_count' => $invalidDecodeParmsOperandCount,
                'malformed_decodeparms_operand_count' => $malformedDecodeParmsOperandCount,
                'invalid_decodeparms_parameter_count' => $invalidDecodeParmsParameterCount,
                'filter_operand_policy' => $this->streamFilterOperandPolicy(
                    $filters,
                    $invalidFilterOperandCount,
                    $dictionaryFilterOperandCount,
                    $malformedFilterOperandCount
                ),
                'decodeparms_operand_policy' => $this->streamDecodeParmsOperandPolicy(
                    $decodeParms,
                    $invalidDecodeParmsOperandCount,
                    $malformedDecodeParmsOperandCount,
                    $invalidDecodeParmsParameterCount
                ),
                'decoded_cmap_length' => $decoded === null ? null : strlen($decoded),
                'decoded_cmap_sha256' => $decoded === null ? null : hash('sha256', $decoded),
                'operand_groups' => $operandGroups,
                'filter_operands' => $operandGroups['Filter'],
                'decodeparms_operands' => $operandGroups['DecodeParms'],
                'length_operand' => $operandGroups['Length'][0] ?? [
                    'name' => 'Length',
                    'kind' => 'absent',
                    'resolved' => false,
                    'xref_selected' => false,
                    'owner_policy' => 'missing_operand',
                ],
                'indirect_filter_count' => $filterIndirectCount,
                'indirect_length_count' => $lengthIndirectCount,
                'xref_selected_operand_count' => $selectedOperandCount,
                'unresolved_operand_count' => $unresolvedOperandCount,
                'decoded_with_current_operands' => $decoded !== null && $unresolvedOperandCount === 0,
                'owner_policy' => $this->xrefStreamOperandOwnerPolicy(
                    $selectedOperandCount,
                    $unresolvedOperandCount,
                    $operands
                ),
                'review_only' => true,
            ];
        }

        return $review;
    }

    /**
     * @param array<int, string> $objects
     * @return array<int, list<array{usage: string, font_object: int, generation: int, reference: string}>>
     */
    private function cMapStreamReferenceUsages(array $objects): array
    {
        $usages = [];
        foreach ($objects as $fontObjectNumber => $body) {
            if (!$this->bodyMayContainFontDictionary($body)) {
                continue;
            }

            foreach ([
                'ToUnicode' => 'to_unicode',
                'Encoding' => 'encoding_cmap',
            ] as $name => $usage) {
                if ($name === 'Encoding' && preg_match('/\/Subtype\s*\/(?:Type0|Type3)\b/s', $body) !== 1) {
                    continue;
                }

                if (preg_match_all('/\/' . $name . '\s+(\d+)\s+(\d+)\s+R\b/s', $body, $matches, PREG_SET_ORDER) !== false) {
                    foreach ($matches as $match) {
                        $objectNumber = (int) $match[1];
                        $generation = (int) $match[2];
                        if ($objectNumber <= 0) {
                            continue;
                        }

                        $key = $usage . ':' . $fontObjectNumber . ':' . $generation;
                        $usages[$objectNumber][$key] = [
                            'usage' => $usage,
                            'font_object' => $fontObjectNumber,
                            'generation' => $generation,
                            'reference' => $objectNumber . ' ' . $generation . ' R',
                        ];
                    }
                }
            }
        }

        foreach ($usages as $objectNumber => $entries) {
            $entries = array_values($entries);
            usort($entries, static fn (array $left, array $right): int => strcmp(
                $left['usage'] . ':' . $left['font_object'] . ':' . $left['generation'],
                $right['usage'] . ':' . $right['font_object'] . ':' . $right['generation']
            ));
            $usages[$objectNumber] = $entries;
        }
        ksort($usages, SORT_NUMERIC);

        return $usages;
    }

    /**
     * @param list<array{usage: string, font_object: int, generation: int, reference: string}> $usages
     */
    private function cMapReferenceUsagesContain(array $usages, string $usage): bool
    {
        foreach ($usages as $entry) {
            if (($entry['usage'] ?? null) === $usage) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $objects
     */
    private function cMapStreamDictionaryLooksLikeCMap(string $dict, array $objects): bool
    {
        if ($this->pdfNameValueAfterNameResolvingObjects($dict, 'Type', $objects) === 'CMap') {
            return true;
        }

        return $this->topLevelNameValueOffset($dict, 'CMapName') !== null
            || $this->topLevelNameValueOffset($dict, 'UseCMap') !== null
            || $this->topLevelNameValueOffset($dict, 'WMode') !== null;
    }

    /**
     * Review object-stream dictionary operands before WordPress import.
     *
     * PDF 1.5 object streams use `/N` and `/First` to split the decoded
     * member table and can use the usual stream `/Length`, `/Filter`, and
     * `/DecodeParms` operands. Expose whether those operands are current
     * xref-selected generations instead of silently trusting stale scanned
     * same-number helper objects.
     *
     * @return array{
     *     source: string,
     *     review_only: true,
     *     encrypted: bool,
     *     object_stream_count: int,
     *     indirect_operand_count: int,
     *     xref_selected_operand_count: int,
     *     unresolved_operand_count: int,
     *     invalid_filter_operand_count: int,
     *     dictionary_filter_operand_count: int,
     *     malformed_filter_operand_count: int,
     *     entries: list<array<string, mixed>>,
     *     executes_python_or_models: false,
     *     executes_external_pdf_tools: false
     * }
     */
    public function extractObjectStreamStreamDictionaryGenerationReview(string $pdfBytes): array
    {
        $review = [
            'source' => 'pdf_object_stream_stream_dictionary_generation_review',
            'review_only' => true,
            'encrypted' => $this->hasEncryptedTrailer($pdfBytes),
            'object_stream_count' => 0,
            'indirect_operand_count' => 0,
            'xref_selected_operand_count' => 0,
            'unresolved_operand_count' => 0,
            'invalid_filter_operand_count' => 0,
            'dictionary_filter_operand_count' => 0,
            'malformed_filter_operand_count' => 0,
            'entries' => [],
            'executes_python_or_models' => false,
            'executes_external_pdf_tools' => false,
        ];

        if ($review['encrypted']) {
            return $review;
        }

        $definitions = $this->directObjectDefinitions($pdfBytes);
        if ($definitions === []) {
            return $review;
        }

        $previousOwners = $this->currentObjectReferenceOwners;
        $preliminaryObjects = $this->latestDirectObjects($definitions);
        $xrefEntries = $this->xrefEntries($pdfBytes, $preliminaryObjects, $definitions);
        $objects = $this->liveDirectObjects($definitions, $xrefEntries);
        $objects = $this->withReferencedDirectGenerationObjects($objects, $definitions, $xrefEntries);
        $this->currentObjectReferenceOwners = $this->objectReferenceOwners($objects, $definitions, $xrefEntries);
        $objects = $this->withObjectStreamObjects($objects, $xrefEntries);
        $this->currentObjectReferenceOwners = $this->objectReferenceOwners($objects, $definitions, $xrefEntries);

        try {
            foreach ($this->liveDirectObjectDefinitionsInFileOrder($definitions, $xrefEntries) as $definition) {
                $body = $objects[$definition['objectNumber']] ?? null;
                if ($body !== $definition['body'] || preg_match('/\/Type\s*\/ObjStm\b/s', $body) !== 1) {
                    continue;
                }

                $dict = $this->dictionaryObjectBody($body);
                if ($dict === null) {
                    continue;
                }

                $operandGroups = [];
                foreach (['N', 'First', 'Length', 'Filter', 'DecodeParms'] as $name) {
                    $operandGroups[$name] = $this->xrefStreamOperandReviews(
                        $dict,
                        $name,
                        $objects,
                        $xrefEntries,
                        $definitions
                    );
                }

                $operands = [];
                foreach ($operandGroups as $group) {
                    foreach ($group as $operand) {
                        $operands[] = $operand;
                    }
                }

                $indirectOperandCount = $this->xrefStreamIndirectOperandCount($operands);
                $selectedOperandCount = $this->xrefStreamSelectedOperandCount($operands);
                $unresolvedOperandCount = $this->xrefStreamUnresolvedOperandCount($operands);
                $invalidFilterOperandCount = $this->invalidStreamFilterOperandCount($operandGroups['Filter'] ?? []);
                $dictionaryFilterOperandCount = $this->dictionaryStreamFilterOperandCount($operandGroups['Filter'] ?? []);
                $malformedFilterOperandCount = $this->malformedStreamFilterOperandCount($operandGroups['Filter'] ?? []);
                $memberTable = $this->decodedObjectStreamMemberTable($body, $objects);
                $filters = $this->streamFilters($dict, $objects);
                $decodeParms = $this->streamDecodeParms($dict, $objects);

                $review['object_stream_count']++;
                $review['indirect_operand_count'] += $indirectOperandCount;
                $review['xref_selected_operand_count'] += $selectedOperandCount;
                $review['unresolved_operand_count'] += $unresolvedOperandCount;
                $review['invalid_filter_operand_count'] += $invalidFilterOperandCount;
                $review['dictionary_filter_operand_count'] += $dictionaryFilterOperandCount;
                $review['malformed_filter_operand_count'] += $malformedFilterOperandCount;
                $review['entries'][] = [
                    'object_number' => $definition['objectNumber'],
                    'generation' => $definition['generation'],
                    'offset' => $definition['offset'],
                    'operand_groups' => $operandGroups,
                    'indirect_operand_count' => $indirectOperandCount,
                    'xref_selected_operand_count' => $selectedOperandCount,
                    'unresolved_operand_count' => $unresolvedOperandCount,
                    'declared_member_count' => $this->pdfIntegerValueAfterNameResolvingObjects($dict, 'N', $objects),
                    'first_object_offset' => $this->pdfIntegerValueAfterNameResolvingObjects($dict, 'First', $objects),
                    'declared_length' => $this->streamLength($dict, $objects),
                    'filters' => $filters ?? [],
                    'filter_resolution_failed' => $filters === null,
                    'decodeparms_resolution_failed' => $decodeParms === null,
                    'invalid_filter_operand_count' => $invalidFilterOperandCount,
                    'dictionary_filter_operand_count' => $dictionaryFilterOperandCount,
                    'malformed_filter_operand_count' => $malformedFilterOperandCount,
                    'filter_operand_policy' => $this->streamFilterOperandPolicy(
                        $filters,
                        $invalidFilterOperandCount,
                        $dictionaryFilterOperandCount,
                        $malformedFilterOperandCount
                    ),
                    'decoded_member_count' => $memberTable === null ? 0 : count($memberTable['members']),
                    'decoded_with_current_operands' => $memberTable !== null && $unresolvedOperandCount === 0,
                    'owner_policy' => $this->xrefStreamOperandOwnerPolicy($selectedOperandCount, $unresolvedOperandCount, $operands),
                    'review_only' => true,
                ];
            }
        } finally {
            $this->currentObjectReferenceOwners = $previousOwners;
        }

        return $review;
    }

    /**
     * @return list<array{page_index: int, page_number: int, page_label: string, text: string}>
     */
    public function extractLabeledPageTexts(string $pdfBytes): array
    {
        $labels = $this->extractPageLabels($pdfBytes);
        $entries = [];
        foreach ($this->extractPageTexts($pdfBytes) as $pageIndex => $text) {
            $entries[] = [
                'page_index' => $pageIndex,
                'page_number' => $pageIndex + 1,
                'page_label' => $labels[$pageIndex] ?? (string) ($pageIndex + 1),
                'text' => $text,
            ];
        }

        return $entries;
    }

    /**
     * Native styled-span boundary for marker.pdf.extract_text::pdftext_format_to_blocks.
     *
     * The full upstream path receives pdftext dictionaries from pdfium, where
     * each span carries PDF 1.7 FontDescriptor flags. This native reduced
     * boundary exposes the same font-name plus decomposed-flags convention for
     * PDFs that can be parsed without Python/pdfium.
     *
     * @return list<array{blocks: list<array{lines: list<array{spans: list<array<string, mixed>>, bbox: list<float>}>, bbox: list<float>, pnum: int}>, pnum: int, bbox: list<float>, rotation: int}>
     */
    public function extractStyledTextPages(string $pdfBytes): array
    {
        $pages = [];
        foreach ($this->contentStreamsWithFontMaps($pdfBytes) as $pageIndex => $entry) {
            $lines = $this->textSpanLinesFromContentStream(
                $entry['stream'],
                $entry['fontToUnicodeMaps'],
                $entry['markedContentProperties'],
                $pageIndex
            );
            if ($lines === []) {
                continue;
            }

            $blocks = [];
            foreach ($lines as $line) {
                $blocks[] = [
                    'lines' => [$line],
                    'bbox' => $line['bbox'],
                    'pnum' => $pageIndex,
                ];
            }

            $pages[] = [
                'blocks' => $blocks,
                'pnum' => $pageIndex,
                'bbox' => $this->pageBboxFromLines($lines),
                'rotation' => 0,
            ];
        }

        return $pages;
    }

    /**
     * Review-only tagged PDF structure boundary for WordPress import.
     *
     * Upstream markerPDF receives logical text and layout metadata from
     * pdfium/pdftext before block conversion. This native reduced boundary
     * exposes the tagged PDF /StructTreeRoot MCID order, including /RoleMap
     * standard-role resolution, without executing Python, models, or external
     * PDF tools.
     *
     * @return list<array<string, mixed>>
     */
    public function extractTaggedContent(string $pdfBytes): array
    {
        if ($this->hasEncryptedTrailer($pdfBytes)) {
            return [];
        }

        $objects = $this->pdfObjects($pdfBytes);
        $pageObjectNumbers = $this->orderedPageObjectNumbers($objects);
        if ($pageObjectNumbers === []) {
            return [];
        }

        $structureEntriesByPage = $this->structureTreeMcidEntriesByPage($objects);
        if ($structureEntriesByPage === []) {
            return [];
        }
        $structureMetadataByPageAndMcid = $this->taggedStructureMetadataByPageAndMcid($pdfBytes);

        $fontObjectMaps = $this->fontObjectMaps($objects);
        $optionalContentStates = $this->optionalContentVisibilityStates($objects);
        $rows = [];

        foreach ($pageObjectNumbers as $pageIndex => $pageObjectNumber) {
            if (!isset($objects[$pageObjectNumber]) || !isset($structureEntriesByPage[$pageObjectNumber])) {
                continue;
            }

            $expanded = $this->expandedPageContentStreamWithFontMaps(
                $pageObjectNumber,
                $objects,
                $fontObjectMaps,
                $optionalContentStates
            );
            if ($expanded === null || trim($expanded['stream']) === '') {
                continue;
            }

            $segments = $this->markedContentSegmentsByMcid(
                $expanded['stream'],
                $objects[$pageObjectNumber],
                $objects,
                $this->pageMarkedContentPropertyDictionaries($pageObjectNumber, $objects)
            );
            if ($segments === []) {
                continue;
            }

            foreach ($structureEntriesByPage[$pageObjectNumber] as $entry) {
                $mcid = $entry['mcid'];
                if (!isset($segments[$mcid])) {
                    continue;
                }

                $texts = [];
                $contentTags = [];
                foreach ($segments[$mcid] as $segmentTokens) {
                    $tag = $this->markedContentTagFromSegmentTokens($segmentTokens);
                    if ($tag !== null) {
                        $contentTags[$tag] = $tag;
                    }

                    $segmentStream = $this->contentStreamForMarkedContentSegment($segmentTokens);
                    if ($segmentStream === '') {
                        continue;
                    }

                    $text = trim(implode("\n", $this->textLinesFromContentStream(
                        $segmentStream,
                        $expanded['fontToUnicodeMaps'],
                        $expanded['markedContentProperties']
                    )));
                    if ($text !== '') {
                        $texts[] = $text;
                    }
                }

                $text = trim(implode("\n", $texts));
                if ($text === '') {
                    continue;
                }

                $rawRole = $entry['rawRole'];
                $role = $entry['role'];
                $row = [
                    'page_index' => $pageIndex,
                    'page_number' => $pageIndex + 1,
                    'page_object_number' => $pageObjectNumber,
                    'mcid' => $mcid,
                    'raw_role' => $rawRole,
                    'role' => $role,
                    'role_mapped' => $rawRole !== null && $role !== null && $rawRole !== $role,
                    'content_tags' => array_values($contentTags),
                    'text' => $text,
                ];

                $metadata = $structureMetadataByPageAndMcid[$pageObjectNumber][$mcid] ?? null;
                if (is_array($metadata)) {
                    foreach ($metadata as $key => $value) {
                        if (!array_key_exists($key, $row)) {
                            $row[$key] = $value;
                        }
                    }
                }

                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function taggedStructureMetadataByPageAndMcid(string $pdfBytes): array
    {
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdfBytes);
        $structureTree = $metadata['structure_tree'] ?? null;
        if (!is_array($structureTree)) {
            return [];
        }

        $elements = $structureTree['elements'] ?? null;
        if (!is_array($elements)) {
            return [];
        }

        $rows = [];
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            $markedContent = $element['marked_content'] ?? null;
            if (!is_array($markedContent)) {
                continue;
            }

            $decorations = [];
            foreach ([
                'object' => 'struct_object',
                'title' => 'title',
                'language' => 'language',
                'language_inherited' => 'language_inherited',
                'alternate_text' => 'alternate_text',
                'actual_text' => 'actual_text',
                'expansion_text' => 'expansion_text',
                'id' => 'id',
                'classes' => 'classes',
                'revision' => 'revision',
                'namespace' => 'namespace',
                'associated_file_count' => 'associated_file_count',
                'associated_files' => 'associated_files',
            ] as $sourceKey => $targetKey) {
                if (array_key_exists($sourceKey, $element)) {
                    $decorations[$targetKey] = $element[$sourceKey];
                }
            }

            if ($decorations === []) {
                continue;
            }

            foreach ($markedContent as $markedContentRow) {
                if (!is_array($markedContentRow)) {
                    continue;
                }

                $pageObject = $markedContentRow['page_object'] ?? $element['page_object'] ?? null;
                $mcid = $markedContentRow['mcid'] ?? null;
                if (!is_int($pageObject) || !is_int($mcid)) {
                    continue;
                }

                $rows[$pageObject][$mcid] = $decorations;
            }
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    public function extractTextLines(string $pdfBytes): array
    {
        $lines = [];
        foreach ($this->contentStreamsWithFontMaps($pdfBytes) as $entry) {
            foreach ($this->textLinesFromContentStream(
                $entry['stream'],
                $entry['fontToUnicodeMaps'],
                $entry['markedContentProperties']
            ) as $line) {
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function extractPageTexts(string $pdfBytes): array
    {
        $pages = [];
        foreach ($this->contentStreamsWithFontMaps($pdfBytes) as $entry) {
            $pages[] = implode("\n", $this->textLinesFromContentStream(
                $entry['stream'],
                $entry['fontToUnicodeMaps'],
                $entry['markedContentProperties']
            ));
        }

        return $pages;
    }

    /**
     * @return list<array{title: string, level: int, page: int}>
     * @param array<int, string> $objects
     */
    private function pdfTocFromObjects(array $objects): array
    {
        $catalog = $this->catalogObjectBody($objects);
        if ($catalog === null || preg_match('/\/Outlines\s+(\d+)\s+\d+\s+R\b/s', $catalog, $match) !== 1) {
            return [];
        }

        $outlineRootNumber = (int) $match[1];
        if (!isset($objects[$outlineRootNumber]) || preg_match('/\/First\s+(\d+)\s+\d+\s+R\b/s', $objects[$outlineRootNumber], $firstMatch) !== 1) {
            return [];
        }

        $pageIndexes = array_flip($this->orderedPageObjectNumbers($objects));

        return $this->outlineItemsFromLinkedList((int) $firstMatch[1], 1, $objects, $pageIndexes);
    }

    /**
     * @return list<array{title: string, level: int, page: int}>
     * @param array<int, string> $objects
     * @param array<int, int> $pageIndexes
     * @param array<int, true> $seen
     */
    private function outlineItemsFromLinkedList(
        int $objectNumber,
        int $level,
        array $objects,
        array $pageIndexes,
        array $seen = []
    ): array {
        $items = [];

        while (isset($objects[$objectNumber]) && !isset($seen[$objectNumber])) {
            $seen[$objectNumber] = true;
            $body = $objects[$objectNumber];
            $title = $this->pdfStringValueAfterName($body, 'Title', $objects);
            $page = $this->outlinePageIndex($body, $objects, $pageIndexes);

            if ($title !== null && $title !== '' && $page !== null) {
                $items[] = [
                    'title' => $title,
                    'level' => $level,
                    'page' => $page,
                ];
            }

            if (preg_match('/\/First\s+(\d+)\s+\d+\s+R\b/s', $body, $firstMatch) === 1) {
                foreach ($this->outlineItemsFromLinkedList((int) $firstMatch[1], $level + 1, $objects, $pageIndexes, $seen) as $child) {
                    $items[] = $child;
                }
            }

            if (preg_match('/\/Next\s+(\d+)\s+\d+\s+R\b/s', $body, $nextMatch) !== 1) {
                break;
            }

            $objectNumber = (int) $nextMatch[1];
        }

        return $items;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, int> $pageIndexes
     */
    private function outlinePageIndex(string $outlineBody, array $objects, array $pageIndexes): ?int
    {
        foreach (['Dest', 'D'] as $key) {
            $destination = $this->pdfArrayValueAfterName($outlineBody, $key);
            if ($destination !== null) {
                $pageObjectNumber = $this->firstObjectReference($destination);
                if ($pageObjectNumber !== null && isset($pageIndexes[$pageObjectNumber])) {
                    return $pageIndexes[$pageObjectNumber];
                }
            }
        }

        $destinationObjectNumber = $this->objectReferenceValueAfterName($outlineBody, 'Dest');
        if ($destinationObjectNumber !== null && isset($objects[$destinationObjectNumber])) {
            $destination = $this->pdfArrayAtStart(trim($objects[$destinationObjectNumber]));
            if ($destination !== null) {
                $pageObjectNumber = $this->firstObjectReference($destination);
                if ($pageObjectNumber !== null && isset($pageIndexes[$pageObjectNumber])) {
                    return $pageIndexes[$pageObjectNumber];
                }
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, string>
     */
    private function documentInfoFromPdf(string $pdfBytes, array $objects): array
    {
        if (preg_match('/\/Info\s+(\d+)\s+\d+\s+R\b/s', $pdfBytes, $match) !== 1) {
            return [];
        }

        $infoObjectNumber = (int) $match[1];
        if (!isset($objects[$infoObjectNumber])) {
            return [];
        }

        $dictionary = $this->dictionaryObjectBody($objects[$infoObjectNumber]) ?? trim($objects[$infoObjectNumber]);
        $fields = [
            'Title' => 'title',
            'Author' => 'author',
            'Subject' => 'subject',
            'Keywords' => 'keywords',
            'Creator' => 'creator',
            'Producer' => 'producer',
            'CreationDate' => 'creation_date',
            'ModDate' => 'mod_date',
        ];

        $info = [];
        foreach ($fields as $pdfName => $key) {
            $value = $this->pdfStringValueAfterName($dictionary, $pdfName, $objects);
            if ($value !== null && $value !== '') {
                $info[$key] = $value;
            }
        }

        return $info;
    }

    /**
     * @param array<int, string> $objects
     */
    private function catalogObjectBody(array $objects): ?string
    {
        foreach ($objects as $body) {
            if ($this->isCatalogObject($body)) {
                return $body;
            }
        }

        return null;
    }

    /**
     * @return list<array{stream: string, fontToUnicodeMaps: array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}>, markedContentProperties: array<string, array{actualText: string|null, altText: string|null}>}>
     */
    private function contentStreamsWithFontMaps(string $pdfBytes): array
    {
        if ($this->hasEncryptedTrailer($pdfBytes)) {
            return [];
        }

        $objects = $this->pdfObjects($pdfBytes);
        $pageObjectNumbers = $this->orderedPageObjectNumbers($objects);
        if ($pageObjectNumbers !== []) {
            $fontObjectMaps = $this->fontObjectMaps($objects);
            $pageStreams = $this->pageContentStreamsWithFontMaps($objects, $pageObjectNumbers, $fontObjectMaps);
            return $pageStreams;
        }

        $fontToUnicodeMaps = $this->fontToUnicodeMaps($pdfBytes);
        return array_map(
            static fn (string $stream): array => [
                'stream' => $stream,
                'fontToUnicodeMaps' => $fontToUnicodeMaps,
                'markedContentProperties' => [],
            ],
            $this->allDecodedStreams($pdfBytes, $objects)
        );
    }

    /**
     * @return list<array{stream: string, fontToUnicodeMaps: array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}>, markedContentProperties: array<string, array{actualText: string|null, altText: string|null}>}>
     * @param array<int, string> $objects
     * @param list<int> $pageObjectNumbers
     * @param array<int, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontObjectMaps
     */
    private function pageContentStreamsWithFontMaps(array $objects, array $pageObjectNumbers, array $fontObjectMaps): array
    {
        $pages = [];
        $articleBeadsByPage = $this->articleThreadBeadsByPage($objects);
        $structureMcidOrderByPage = $this->structureTreeMcidOrderByPage($objects);
        $optionalContentStates = $this->optionalContentVisibilityStates($objects);
        foreach ($pageObjectNumbers as $pageObjectNumber) {
            if (!isset($objects[$pageObjectNumber])) {
                continue;
            }

            $expanded = $this->expandedPageContentStreamWithFontMaps(
                $pageObjectNumber,
                $objects,
                $fontObjectMaps,
                $optionalContentStates
            );
            if ($expanded === null) {
                continue;
            }

            $structureOrderedStream = $this->structureTreeReadingOrderedStream(
                $expanded['stream'],
                $objects[$pageObjectNumber],
                $objects,
                $structureMcidOrderByPage[$pageObjectNumber] ?? [],
                $this->pageMarkedContentPropertyDictionaries($pageObjectNumber, $objects)
            );
            if ($structureOrderedStream !== null) {
                $expanded['stream'] = $structureOrderedStream;
            } else {
                $expanded['stream'] = $this->applyArticleThreadReadingOrder(
                    $expanded['stream'],
                    $articleBeadsByPage[$pageObjectNumber] ?? []
                );
            }

            foreach ($this->annotationAppearanceStreamsWithFontMaps(
                $objects[$pageObjectNumber],
                $objects,
                $fontObjectMaps,
                $expanded['fontToUnicodeMaps'],
                $optionalContentStates
            ) as $appearance) {
                $expanded['stream'] = trim($expanded['stream']) === ''
                    ? $appearance['stream']
                    : $expanded['stream'] . "\n" . $appearance['stream'];
                $expanded['fontToUnicodeMaps'] = $appearance['fontToUnicodeMaps'];
            }

            if (trim($expanded['stream']) === '') {
                continue;
            }

            $pages[] = [
                'stream' => $expanded['stream'],
                'fontToUnicodeMaps' => $expanded['fontToUnicodeMaps'],
                'markedContentProperties' => $expanded['markedContentProperties'],
            ];
        }

        return $pages;
    }

    /**
     * @return array{stream: string, fontToUnicodeMaps: array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}>, markedContentProperties: array<string, array{actualText: string|null, altText: string|null}>}|null
     * @param array<int, string> $objects
     * @param array<int, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontObjectMaps
     * @param array<int, bool> $optionalContentStates
     */
    private function expandedPageContentStreamWithFontMaps(
        int $pageObjectNumber,
        array $objects,
        array $fontObjectMaps,
        array $optionalContentStates
    ): ?array {
        if (!isset($objects[$pageObjectNumber])) {
            return null;
        }

        $streams = [];
        $optionalContentProperties = $this->pageOptionalContentPropertyVisibilityMap(
            $pageObjectNumber,
            $objects,
            $optionalContentStates
        );
        foreach ($this->pageContentObjectNumbers($objects[$pageObjectNumber], $objects) as $contentObjectNumber) {
            if (!isset($objects[$contentObjectNumber])) {
                continue;
            }

            if (!$this->optionalContentObjectVisible($objects[$contentObjectNumber], $objects, $optionalContentStates)) {
                continue;
            }

            $decoded = $this->decodeStreamObject($objects[$contentObjectNumber], $objects);
            if ($decoded !== null) {
                $streams[] = $this->filterOptionalContentMarkedBlocks($decoded, $optionalContentProperties);
            }
        }

        $expanded = [
            'stream' => implode("\n", $streams),
            'fontToUnicodeMaps' => $this->pageFontToUnicodeMaps($pageObjectNumber, $objects, $fontObjectMaps),
            'markedContentProperties' => $this->pageMarkedContentProperties($pageObjectNumber, $objects),
        ];

        if ($streams !== []) {
            $resourceOwnerBody = $this->pageResourceDictionaryBody($pageObjectNumber, $objects);
            if ($resourceOwnerBody === null && !$this->pageResourceDictionaryBlocksFallback($pageObjectNumber, $objects)) {
                $resourceOwnerBody = $objects[$pageObjectNumber];
            }

            if ($resourceOwnerBody !== null) {
                $expandedForms = $this->expandFormXObjectInvocations(
                    $expanded['stream'],
                    $resourceOwnerBody,
                    $objects,
                    $fontObjectMaps,
                    $expanded['fontToUnicodeMaps'],
                    $optionalContentStates
                );
                $expanded['stream'] = $expandedForms['stream'];
                $expanded['fontToUnicodeMaps'] = $expandedForms['fontToUnicodeMaps'];
            }
        }

        return $expanded;
    }

    /**
     * Native boundary for PDF catalog /Threads article bead reading order.
     *
     * @return array<int, list<array{thread: int, bead: int, rect: list<float>}>>
     * @param array<int, string> $objects
     */
    private function articleThreadBeadsByPage(array $objects): array
    {
        $catalog = $this->catalogObjectBody($objects);
        if ($catalog === null) {
            return [];
        }

        $threadsValue = $this->pdfValueAfterName($catalog, 'Threads');
        if ($threadsValue === null) {
            return [];
        }

        $threads = $this->pdfArrayFromValue($threadsValue, $objects);
        if ($threads === null) {
            return [];
        }

        $beadsByPage = [];
        foreach ($this->pdfArrayItems($threads) as $threadIndex => $threadValue) {
            $threadDictionary = $this->pdfDictionaryFromValue($threadValue, $objects);
            if ($threadDictionary === null) {
                continue;
            }

            $firstBeadObjectNumber = $this->objectReferenceValueAfterName($threadDictionary, 'F');
            if ($firstBeadObjectNumber === null) {
                continue;
            }

            $beadObjectNumber = $firstBeadObjectNumber;
            $seenBeads = [];
            while (isset($objects[$beadObjectNumber]) && !isset($seenBeads[$beadObjectNumber])) {
                $seenBeads[$beadObjectNumber] = true;
                $beadDictionary = $this->dictionaryObjectBody($objects[$beadObjectNumber]);
                if ($beadDictionary === null) {
                    break;
                }

                $pageObjectNumber = $this->objectReferenceValueAfterName($beadDictionary, 'P');
                $rectangle = $this->articleBeadRectangle($beadDictionary);
                if ($pageObjectNumber !== null && $rectangle !== null) {
                    $beadsByPage[$pageObjectNumber][] = [
                        'thread' => (int) $threadIndex,
                        'bead' => $beadObjectNumber,
                        'rect' => $rectangle,
                    ];
                }

                $nextBeadObjectNumber = $this->objectReferenceValueAfterName($beadDictionary, 'N');
                if ($nextBeadObjectNumber === null || $nextBeadObjectNumber === $firstBeadObjectNumber) {
                    break;
                }

                $beadObjectNumber = $nextBeadObjectNumber;
            }
        }

        return $beadsByPage;
    }

    /**
     * @return list<float>|null
     */
    private function articleBeadRectangle(string $beadDictionary): ?array
    {
        $rectangle = $this->pdfArrayValueAfterName($beadDictionary, 'R');
        if ($rectangle === null) {
            return null;
        }

        $numbers = $this->numbersFromPdfArray($rectangle);
        if (count($numbers) < 4) {
            return null;
        }

        $left = min($numbers[0], $numbers[2]);
        $right = max($numbers[0], $numbers[2]);
        $bottom = min($numbers[1], $numbers[3]);
        $top = max($numbers[1], $numbers[3]);
        if ($right <= $left || $top <= $bottom) {
            return null;
        }

        return [$left, $bottom, $right, $top];
    }

    /**
     * @param list<array{thread: int, bead: int, rect: list<float>}> $beads
     */
    private function applyArticleThreadReadingOrder(string $stream, array $beads): string
    {
        if ($beads === [] || trim($stream) === '') {
            return $stream;
        }

        $segments = $this->positionedTextSegments($stream);
        if ($segments === []) {
            return $stream;
        }

        $ordered = [];
        $selected = [];
        foreach ($beads as $bead) {
            foreach ($segments as $index => $segment) {
                if (isset($selected[$index]) || !$this->positionedTextSegmentInsideRectangle($segment, $bead['rect'])) {
                    continue;
                }

                $ordered[] = $this->contentStreamForPositionedTextSegment($segment);
                $selected[$index] = true;
            }
        }

        if ($ordered === []) {
            return $stream;
        }

        foreach ($segments as $index => $segment) {
            if (!isset($selected[$index])) {
                $ordered[] = $this->contentStreamForPositionedTextSegment($segment);
            }
        }

        return implode("\n", $ordered);
    }

    /**
     * @return list<array{x: float|null, y: float|null, tokens: list<string>}>
     */
    private function positionedTextSegments(string $stream): array
    {
        $segments = [];
        $operands = [];
        $currentFontToken = null;
        $currentFontSizeToken = null;
        $currentTextLeading = null;
        $currentTextX = null;
        $currentTextY = null;
        $currentTextMatrixHorizontalScale = 1.0;
        $textStateStack = [];

        foreach ($this->contentTokens($stream) as $token) {
            if ($this->isTextShowingOperator($token)) {
                if ($token === "'" || $token === '"') {
                    $currentTextY = $this->advanceTextYByLeading($currentTextY, $currentTextLeading);
                }

                $operand = $this->textShowingOperand($token, $operands);
                if ($operand !== null) {
                    $segmentTokens = [];
                    if ($currentFontToken !== null) {
                        $segmentTokens[] = $currentFontToken;
                        $segmentTokens[] = $currentFontSizeToken ?? '12';
                        $segmentTokens[] = 'Tf';
                    }
                    if ($currentTextX !== null && $currentTextY !== null) {
                        array_push(
                            $segmentTokens,
                            '1',
                            '0',
                            '0',
                            '1',
                            $this->formatPdfNumber($currentTextX),
                            $this->formatPdfNumber($currentTextY),
                            'Tm'
                        );
                    }

                    $segmentTokens[] = $operand;
                    $segmentTokens[] = $token === 'TJ' ? 'TJ' : 'Tj';
                    $segments[] = [
                        'x' => $currentTextX,
                        'y' => $currentTextY,
                        'tokens' => $segmentTokens,
                    ];
                }

                $operands = [];
                continue;
            }

            if ($token === 'q') {
                $textStateStack[] = [
                    'fontToken' => $currentFontToken,
                    'fontSizeToken' => $currentFontSizeToken,
                    'textLeading' => $currentTextLeading,
                ];
                $operands = [];
                continue;
            }

            if ($token === 'Q') {
                $state = array_pop($textStateStack);
                if (is_array($state)) {
                    $currentFontToken = $state['fontToken'];
                    $currentFontSizeToken = $state['fontSizeToken'];
                    $currentTextLeading = $state['textLeading'];
                }
                $operands = [];
                continue;
            }

            if ($token === 'Tf') {
                if (count($operands) >= 2 && str_starts_with($operands[count($operands) - 2], '/')) {
                    $currentFontToken = $operands[count($operands) - 2];
                    $fontSize = $this->fontSizeOperand($operands);
                    $currentFontSizeToken = $fontSize === null
                        ? $operands[count($operands) - 1]
                        : $this->formatPdfNumber($fontSize);
                }
                $operands = [];
                continue;
            }

            if ($token === 'TL') {
                $currentTextLeading = $this->textLeadingOperand($operands) ?? $currentTextLeading;
                $operands = [];
                continue;
            }

            if ($token === 'Td' || $token === 'TD') {
                if ($token === 'TD') {
                    $moveY = $this->textMoveOperandY($operands);
                    if ($moveY !== null) {
                        $currentTextLeading = -$moveY;
                    }
                }
                $currentTextX = $this->textMoveX($operands, $currentTextX, $currentTextMatrixHorizontalScale);
                $currentTextY = $this->textMoveY($operands, $currentTextY);
                $operands = [];
                continue;
            }

            if ($token === 'Tm') {
                $currentTextX = $this->textMatrixX($operands);
                $currentTextY = $this->textMatrixY($operands);
                $currentTextMatrixHorizontalScale = $this->textMatrixHorizontalScale($operands) ?? 1.0;
                $operands = [];
                continue;
            }

            if ($token === 'T*') {
                $currentTextY = $this->advanceTextYByLeading($currentTextY, $currentTextLeading);
                $operands = [];
                continue;
            }

            if ($token === 'BT') {
                $currentTextX = null;
                $currentTextY = null;
                $currentTextMatrixHorizontalScale = 1.0;
                $operands = [];
                continue;
            }

            if ($token === 'ET') {
                $currentTextX = null;
                $currentTextY = null;
                $currentTextMatrixHorizontalScale = 1.0;
                $operands = [];
                continue;
            }

            if ($this->isOperator($token)) {
                $operands = [];
                continue;
            }

            $operands[] = $token;
        }

        return $segments;
    }

    /**
     * @param array{x: float|null, y: float|null, tokens: list<string>} $segment
     * @param list<float> $rectangle
     */
    private function positionedTextSegmentInsideRectangle(array $segment, array $rectangle): bool
    {
        if ($segment['x'] === null || $segment['y'] === null || count($rectangle) < 4) {
            return false;
        }

        $tolerance = 0.5;
        return $segment['x'] >= $rectangle[0] - $tolerance
            && $segment['x'] <= $rectangle[2] + $tolerance
            && $segment['y'] >= $rectangle[1] - $tolerance
            && $segment['y'] <= $rectangle[3] + $tolerance;
    }

    /**
     * @param array{x: float|null, y: float|null, tokens: list<string>} $segment
     */
    private function contentStreamForPositionedTextSegment(array $segment): string
    {
        return 'BT ' . implode(' ', $segment['tokens']) . ' ET';
    }

    /**
     * @return array<int, list<int>>
     * @param array<int, string> $objects
     */
    private function structureTreeMcidOrderByPage(array $objects): array
    {
        $rootDictionary = $this->structureTreeRootDictionaryBody($objects);
        if ($rootDictionary === null) {
            return [];
        }

        $k = $this->pdfValueAfterName($rootDictionary, 'K');
        if ($k === null) {
            return [];
        }

        $order = [];
        $this->collectStructureMcidOrder($k, $objects, null, $order);
        $parentTreeOrder = $this->structureParentTreeMcidOrderByPage($objects, $rootDictionary);
        foreach ($parentTreeOrder as $pageObjectNumber => $mcids) {
            $order[$pageObjectNumber] ??= [];
            $knownMcids = array_fill_keys($order[$pageObjectNumber], true);
            foreach ($mcids as $mcid) {
                if (isset($knownMcids[$mcid])) {
                    continue;
                }

                $order[$pageObjectNumber][] = $mcid;
                $knownMcids[$mcid] = true;
            }
        }

        foreach ($order as $pageObjectNumber => $mcids) {
            $deduped = [];
            foreach ($mcids as $mcid) {
                $deduped[$mcid] = $mcid;
            }
            $order[$pageObjectNumber] = array_values($deduped);
        }

        return $order;
    }

    /**
     * @return array<int, list<array{mcid: int, rawRole: string|null, role: string|null}>>
     * @param array<int, string> $objects
     */
    private function structureTreeMcidEntriesByPage(array $objects): array
    {
        $rootDictionary = $this->structureTreeRootDictionaryBody($objects);
        if ($rootDictionary === null) {
            return [];
        }

        $k = $this->pdfValueAfterName($rootDictionary, 'K');
        if ($k === null) {
            return [];
        }

        $roleMap = $this->structureRoleMap($rootDictionary, $objects);
        $entries = [];
        $this->collectStructureMcidEntries(
            $k,
            $objects,
            null,
            null,
            null,
            $roleMap,
            $entries
        );
        $parentTreeEntries = $this->structureParentTreeMcidEntriesByPage($objects, $rootDictionary, $roleMap);
        foreach ($parentTreeEntries as $pageObjectNumber => $pageEntries) {
            $entries[$pageObjectNumber] ??= [];
            $knownMcids = [];
            foreach ($entries[$pageObjectNumber] as $entry) {
                $knownMcids[$entry['mcid']] = true;
            }

            foreach ($pageEntries as $entry) {
                if (isset($knownMcids[$entry['mcid']])) {
                    continue;
                }

                $entries[$pageObjectNumber][] = $entry;
                $knownMcids[$entry['mcid']] = true;
            }
        }

        foreach ($entries as $pageObjectNumber => $pageEntries) {
            $deduped = [];
            foreach ($pageEntries as $entry) {
                $deduped[$entry['mcid']] = $entry;
            }
            $entries[$pageObjectNumber] = array_values($deduped);
        }

        return $entries;
    }

    /**
     * @return array<int, list<int>>
     * @param array<int, string> $objects
     */
    private function structureParentTreeMcidOrderByPage(array $objects, string $rootDictionary): array
    {
        $entries = $this->structureParentTreeMcidEntriesByPage($objects, $rootDictionary, $this->structureRoleMap($rootDictionary, $objects));
        $order = [];
        foreach ($entries as $pageObjectNumber => $pageEntries) {
            foreach ($pageEntries as $entry) {
                $order[$pageObjectNumber][] = $entry['mcid'];
            }
        }

        return $order;
    }

    /**
     * @return array<int, list<array{mcid: int, rawRole: string|null, role: string|null}>>
     * @param array<int, string> $objects
     * @param array<string, string> $roleMap
     */
    private function structureParentTreeMcidEntriesByPage(array $objects, string $rootDictionary, array $roleMap): array
    {
        $parentTreeDictionary = $this->pdfDictionaryValueAfterNameResolved($rootDictionary, 'ParentTree', $objects);
        if ($parentTreeDictionary === null) {
            return [];
        }

        $parentArraysByStructParents = [];
        $this->collectStructureParentTreeArrays($parentTreeDictionary, $objects, $parentArraysByStructParents);
        if ($parentArraysByStructParents === []) {
            return [];
        }

        $entries = [];
        foreach ($objects as $pageObjectNumber => $pageBody) {
            if (!$this->isPageObject($pageBody)) {
                continue;
            }

            $structParents = $this->pdfIntegerValueAfterName($pageBody, 'StructParents');
            if ($structParents === null || !isset($parentArraysByStructParents[$structParents])) {
                continue;
            }

            foreach ($this->pdfArrayItems($parentArraysByStructParents[$structParents]) as $mcid => $parentValue) {
                $dictionary = $this->pdfDictionaryFromValue($parentValue, $objects);
                if ($dictionary === null) {
                    continue;
                }

                $rawRole = $this->pdfNameValueAfterName($dictionary, 'S');
                if ($rawRole === null) {
                    continue;
                }

                $entries[$pageObjectNumber][] = [
                    'mcid' => $mcid,
                    'rawRole' => $rawRole,
                    'role' => $this->resolveStructureRole($rawRole, $roleMap),
                ];
            }
        }

        return $entries;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, string> $arrays
     * @param array<int, true> $seenObjects
     */
    private function collectStructureParentTreeArrays(
        string $dictionary,
        array $objects,
        array &$arrays,
        array $seenObjects = [],
        int $depth = 0
    ): void {
        if ($depth > 20) {
            return;
        }

        $nums = $this->pdfArrayValueAfterNameResolved($dictionary, 'Nums', $objects);
        if ($nums !== null) {
            $items = $this->pdfArrayItems($nums);
            for ($index = 0, $count = count($items); $index + 1 < $count; $index += 2) {
                $key = trim($items[$index]);
                if (preg_match('/^[+-]?\d+$/', $key) !== 1) {
                    continue;
                }

                $array = $this->pdfArrayFromValue($items[$index + 1], $objects);
                if ($array !== null) {
                    $arrays[(int) $key] = $array;
                }
            }
        }

        $kids = $this->pdfArrayValueAfterNameResolved($dictionary, 'Kids', $objects);
        if ($kids === null) {
            return;
        }

        foreach ($this->objectReferences($kids) as $kidObjectNumber) {
            if (isset($seenObjects[$kidObjectNumber]) || !isset($objects[$kidObjectNumber])) {
                continue;
            }

            $kidDictionary = $this->dictionaryObjectBody($objects[$kidObjectNumber]);
            if ($kidDictionary === null) {
                continue;
            }

            $nextSeen = $seenObjects;
            $nextSeen[$kidObjectNumber] = true;
            $this->collectStructureParentTreeArrays($kidDictionary, $objects, $arrays, $nextSeen, $depth + 1);
        }
    }

    /**
     * @return array<string, string>
     * @param array<int, string> $objects
     */
    private function structureRoleMap(string $rootDictionary, array $objects): array
    {
        $roleMapDictionary = $this->pdfDictionaryValueAfterNameResolved($rootDictionary, 'RoleMap', $objects);
        if ($roleMapDictionary === null) {
            return [];
        }

        $roleMap = [];
        if (preg_match_all('/\/([^\s\[\]()<>{}\/%]+)\s+\/([^\s\[\]()<>{}\/%]+)/s', $roleMapDictionary, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $roleMap[$this->decodePdfName($match[1])] = $this->decodePdfName($match[2]);
            }
        }

        return $roleMap;
    }

    /**
     * @param array<string, string> $roleMap
     */
    private function resolveStructureRole(string $role, array $roleMap): string
    {
        $current = $role;
        $seen = [];
        for ($depth = 0; $depth < 16; $depth++) {
            if (!isset($roleMap[$current]) || isset($seen[$current])) {
                break;
            }

            $seen[$current] = true;
            $current = $roleMap[$current];
        }

        return $current;
    }

    /**
     * @param array<int, string> $objects
     */
    private function structureTreeRootDictionaryBody(array $objects): ?string
    {
        $catalog = $this->catalogObjectBody($objects);
        if ($catalog === null) {
            return null;
        }

        $value = $this->pdfValueAfterName($catalog, 'StructTreeRoot');
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if (preg_match('/^(\d+)\s+\d+\s+R\b/s', $value, $match) === 1) {
            $objectNumber = (int) $match[1];
            return isset($objects[$objectNumber]) ? $this->dictionaryObjectBody($objects[$objectNumber]) : null;
        }

        if (str_starts_with($value, '<<')) {
            return $this->readPdfDictionaryAt($value, 0);
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, list<int>> $order
     * @param array<int, true> $seenObjects
     */
    private function collectStructureMcidOrder(
        string $value,
        array $objects,
        ?int $inheritedPageObjectNumber,
        array &$order,
        array $seenObjects = []
    ): void {
        $value = trim($value);
        if ($value === '') {
            return;
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/s', $value, $match) === 1) {
            $objectNumber = (int) $match[1];
            if (isset($seenObjects[$objectNumber]) || !isset($objects[$objectNumber])) {
                return;
            }

            $seenObjects[$objectNumber] = true;
            $dictionary = $this->dictionaryObjectBody($objects[$objectNumber]);
            if ($dictionary !== null) {
                $this->collectStructureDictionaryMcidOrder($dictionary, $objects, $inheritedPageObjectNumber, $order, $seenObjects);
            }
            return;
        }

        if (str_starts_with($value, '[')) {
            $arrayBody = $this->pdfArrayAtStart($value);
            if ($arrayBody === null) {
                return;
            }

            foreach ($this->pdfArrayItems($arrayBody) as $item) {
                $this->collectStructureMcidOrder($item, $objects, $inheritedPageObjectNumber, $order, $seenObjects);
            }
            return;
        }

        if (str_starts_with($value, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($value, 0);
            if ($dictionary !== null) {
                $this->collectStructureDictionaryMcidOrder($dictionary, $objects, $inheritedPageObjectNumber, $order, $seenObjects);
            }
            return;
        }

        if ($inheritedPageObjectNumber !== null && preg_match('/^[+-]?\d+$/', $value) === 1) {
            $mcid = (int) $value;
            if ($mcid >= 0) {
                $order[$inheritedPageObjectNumber][] = $mcid;
            }
        }
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, string> $roleMap
     * @param array<int, list<array{mcid: int, rawRole: string|null, role: string|null}>> $entries
     * @param array<int, true> $seenObjects
     */
    private function collectStructureMcidEntries(
        string $value,
        array $objects,
        ?int $inheritedPageObjectNumber,
        ?string $inheritedRawRole,
        ?string $inheritedRole,
        array $roleMap,
        array &$entries,
        array $seenObjects = []
    ): void {
        $value = trim($value);
        if ($value === '') {
            return;
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/s', $value, $match) === 1) {
            $objectNumber = (int) $match[1];
            if (isset($seenObjects[$objectNumber]) || !isset($objects[$objectNumber])) {
                return;
            }

            $seenObjects[$objectNumber] = true;
            $dictionary = $this->dictionaryObjectBody($objects[$objectNumber]);
            if ($dictionary !== null) {
                $this->collectStructureDictionaryMcidEntries(
                    $dictionary,
                    $objects,
                    $inheritedPageObjectNumber,
                    $inheritedRawRole,
                    $inheritedRole,
                    $roleMap,
                    $entries,
                    $seenObjects
                );
            }
            return;
        }

        if (str_starts_with($value, '[')) {
            $arrayBody = $this->pdfArrayAtStart($value);
            if ($arrayBody === null) {
                return;
            }

            foreach ($this->pdfArrayItems($arrayBody) as $item) {
                $this->collectStructureMcidEntries(
                    $item,
                    $objects,
                    $inheritedPageObjectNumber,
                    $inheritedRawRole,
                    $inheritedRole,
                    $roleMap,
                    $entries,
                    $seenObjects
                );
            }
            return;
        }

        if (str_starts_with($value, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($value, 0);
            if ($dictionary !== null) {
                $this->collectStructureDictionaryMcidEntries(
                    $dictionary,
                    $objects,
                    $inheritedPageObjectNumber,
                    $inheritedRawRole,
                    $inheritedRole,
                    $roleMap,
                    $entries,
                    $seenObjects
                );
            }
            return;
        }

        if ($inheritedPageObjectNumber !== null && preg_match('/^[+-]?\d+$/', $value) === 1) {
            $mcid = (int) $value;
            if ($mcid >= 0) {
                $entries[$inheritedPageObjectNumber][] = [
                    'mcid' => $mcid,
                    'rawRole' => $inheritedRawRole,
                    'role' => $inheritedRole,
                ];
            }
        }
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, string> $roleMap
     * @param array<int, list<array{mcid: int, rawRole: string|null, role: string|null}>> $entries
     * @param array<int, true> $seenObjects
     */
    private function collectStructureDictionaryMcidEntries(
        string $dictionary,
        array $objects,
        ?int $inheritedPageObjectNumber,
        ?string $inheritedRawRole,
        ?string $inheritedRole,
        array $roleMap,
        array &$entries,
        array $seenObjects
    ): void {
        $rawRole = $this->pdfNameValueAfterName($dictionary, 'S') ?? $inheritedRawRole;
        $role = $rawRole === null ? $inheritedRole : $this->resolveStructureRole($rawRole, $roleMap);
        $pageObjectNumber = $this->objectReferenceValueAfterName($dictionary, 'Pg') ?? $inheritedPageObjectNumber;
        $mcid = $this->pdfIntegerValueAfterName($dictionary, 'MCID');
        if ($pageObjectNumber !== null && $mcid !== null && $mcid >= 0) {
            $entries[$pageObjectNumber][] = [
                'mcid' => $mcid,
                'rawRole' => $rawRole,
                'role' => $role,
            ];
        }

        $k = $this->pdfValueAfterName($dictionary, 'K');
        if ($k !== null) {
            $this->collectStructureMcidEntries(
                $k,
                $objects,
                $pageObjectNumber,
                $rawRole,
                $role,
                $roleMap,
                $entries,
                $seenObjects
            );
        }
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, list<int>> $order
     * @param array<int, true> $seenObjects
     */
    private function collectStructureDictionaryMcidOrder(
        string $dictionary,
        array $objects,
        ?int $inheritedPageObjectNumber,
        array &$order,
        array $seenObjects
    ): void {
        $pageObjectNumber = $this->objectReferenceValueAfterName($dictionary, 'Pg') ?? $inheritedPageObjectNumber;
        $mcid = $this->pdfIntegerValueAfterName($dictionary, 'MCID');
        if ($pageObjectNumber !== null && $mcid !== null && $mcid >= 0) {
            $order[$pageObjectNumber][] = $mcid;
        }

        $k = $this->pdfValueAfterName($dictionary, 'K');
        if ($k !== null) {
            $this->collectStructureMcidOrder($k, $objects, $pageObjectNumber, $order, $seenObjects);
        }
    }

    /**
     * @param list<int> $mcidOrder
     * @param array<int, string> $objects
     */
    private function structureTreeReadingOrderedStream(
        string $stream,
        string $pageBody,
        array $objects,
        array $mcidOrder,
        ?array $properties = null
    ): ?string
    {
        if ($mcidOrder === []) {
            return null;
        }

        $segments = $this->markedContentSegmentsByMcid($stream, $pageBody, $objects, $properties);
        if ($segments === []) {
            return null;
        }

        $orderedSegments = [];
        foreach ($mcidOrder as $mcid) {
            if (!isset($segments[$mcid])) {
                continue;
            }

            foreach ($segments[$mcid] as $segmentTokens) {
                $segment = $this->contentStreamForMarkedContentSegment($segmentTokens);
                if ($segment !== '') {
                    $orderedSegments[] = $segment;
                }
            }
        }

        return $orderedSegments === [] ? null : implode("\n", $orderedSegments);
    }

    /**
     * @return array<int, list<list<string>>>
     * @param array<int, string> $objects
     */
    private function markedContentSegmentsByMcid(
        string $stream,
        string $pageBody,
        array $objects,
        ?array $properties = null
    ): array
    {
        $properties ??= $this->markedContentPropertyDictionaries($pageBody, $objects);
        $segments = [];
        $activeSegments = [];
        $operands = [];
        $currentFontResource = null;
        $currentFontSize = null;
        $currentTransformationMatrix = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];
        $clipRectangle = null;
        $currentPathRectangle = null;
        $clipStateStack = [];

        foreach ($this->contentTokens($stream) as $token) {
            if ($token === 'BDC') {
                $activeSegment = $this->activeMarkedContentSegment($activeSegments);
                if ($activeSegment !== null) {
                    foreach ($operands as $operand) {
                        $segments[$activeSegment['mcid']][$activeSegment['segmentIndex']][] = $operand;
                    }
                    $segments[$activeSegment['mcid']][$activeSegment['segmentIndex']][] = $token;
                }

                $mcid = $this->markedContentMcidOperand($operands, $properties);
                $segmentIndex = null;
                if ($mcid !== null) {
                    $segments[$mcid] ??= [];
                    $segmentTokens = $this->markedContentSegmentPrefix($currentFontResource, $currentFontSize, $clipRectangle);
                    foreach ($operands as $operand) {
                        $segmentTokens[] = $operand;
                    }
                    $segmentTokens[] = $token;
                    $segments[$mcid][] = $segmentTokens;
                    $segmentIndex = count($segments[$mcid]) - 1;
                }

                $activeSegments[] = [
                    'mcid' => $mcid,
                    'segmentIndex' => $segmentIndex,
                    'clipWrapped' => $mcid !== null && $clipRectangle !== null,
                ];
                $operands = [];
                continue;
            }

            if ($token === 'BMC') {
                $activeSegment = $this->activeMarkedContentSegment($activeSegments);
                if ($activeSegment !== null) {
                    foreach ($operands as $operand) {
                        $segments[$activeSegment['mcid']][$activeSegment['segmentIndex']][] = $operand;
                    }
                    $segments[$activeSegment['mcid']][$activeSegment['segmentIndex']][] = $token;
                }

                $activeSegments[] = [
                    'mcid' => null,
                    'segmentIndex' => null,
                    'clipWrapped' => false,
                ];
                $operands = [];
                continue;
            }

            if ($token === 'EMC') {
                $activeSegment = $this->activeMarkedContentSegment($activeSegments);
                if ($activeSegment !== null) {
                    $segments[$activeSegment['mcid']][$activeSegment['segmentIndex']][] = $token;
                    if ($activeSegment['clipWrapped']) {
                        $segments[$activeSegment['mcid']][$activeSegment['segmentIndex']][] = 'Q';
                    }
                }

                array_pop($activeSegments);
                $operands = [];
                continue;
            }

            $activeSegment = $this->activeMarkedContentSegment($activeSegments);
            if ($activeSegment !== null) {
                $segments[$activeSegment['mcid']][$activeSegment['segmentIndex']][] = $token;
            }

            if ($token === 'Tf') {
                $currentFontResource = $this->fontResourceOperand($operands) ?? $currentFontResource;
                $fontSize = $this->fontSizeOperand($operands);
                $currentFontSize = $fontSize === null ? $currentFontSize : $this->formatPdfNumber($fontSize);
                $operands = [];
                continue;
            }

            if ($token === 'q') {
                $clipStateStack[] = [
                    'clipRectangle' => $clipRectangle,
                    'currentPathRectangle' => $currentPathRectangle,
                    'currentTransformationMatrix' => $currentTransformationMatrix,
                ];
                $operands = [];
                continue;
            }

            if ($token === 'Q') {
                $clipState = array_pop($clipStateStack);
                if (is_array($clipState)) {
                    $clipRectangle = $clipState['clipRectangle'];
                    $currentPathRectangle = $clipState['currentPathRectangle'];
                    $currentTransformationMatrix = $clipState['currentTransformationMatrix'];
                }
                $operands = [];
                continue;
            }

            if ($this->applyClipPathStateOperator(
                $token,
                $operands,
                $clipRectangle,
                $currentPathRectangle,
                $currentTransformationMatrix
            )) {
                $operands = [];
                continue;
            }

            if ($this->isOperator($token)) {
                $operands = [];
                continue;
            }

            $operands[] = $token;
        }

        return $segments;
    }

    /**
     * @param list<string> $segmentTokens
     */
    private function contentStreamForMarkedContentSegment(array $segmentTokens): string
    {
        $segment = trim(implode(' ', $segmentTokens));
        if ($segment === '') {
            return '';
        }

        if (preg_match('/(?:^|\s)BT(?:\s|$)/', $segment) === 1) {
            return $segment;
        }

        return 'BT ' . $segment . ' ET';
    }

    /**
     * @param list<string> $segmentTokens
     */
    private function markedContentTagFromSegmentTokens(array $segmentTokens): ?string
    {
        foreach ($segmentTokens as $index => $token) {
            if ($token === 'BDC') {
                $tag = $segmentTokens[$index - 2] ?? null;
                return is_string($tag) && str_starts_with($tag, '/') ? $this->decodePdfName(substr($tag, 1)) : null;
            }

            if ($token === 'BMC') {
                $tag = $segmentTokens[$index - 1] ?? null;
                return is_string($tag) && str_starts_with($tag, '/') ? $this->decodePdfName(substr($tag, 1)) : null;
            }
        }

        return null;
    }

    /**
     * @param list<array{mcid: int|null, segmentIndex: int|null, clipWrapped: bool}> $activeSegments
     * @return array{mcid: int, segmentIndex: int, clipWrapped: bool}|null
     */
    private function activeMarkedContentSegment(array $activeSegments): ?array
    {
        for ($index = count($activeSegments) - 1; $index >= 0; $index--) {
            $segment = $activeSegments[$index];
            if ($segment['mcid'] !== null && $segment['segmentIndex'] !== null) {
                return [
                    'mcid' => $segment['mcid'],
                    'segmentIndex' => $segment['segmentIndex'],
                    'clipWrapped' => $segment['clipWrapped'],
                ];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function markedContentSegmentPrefix(?string $fontResource, ?string $fontSize, ?array $clipRectangle = null): array
    {
        $prefix = [];
        if ($clipRectangle !== null) {
            $prefix[] = 'q';
            if ($this->pdfRectangleHasArea($clipRectangle)) {
                $prefix[] = $this->formatPdfNumber($clipRectangle[0]);
                $prefix[] = $this->formatPdfNumber($clipRectangle[1]);
                $prefix[] = $this->formatPdfNumber($clipRectangle[2] - $clipRectangle[0]);
                $prefix[] = $this->formatPdfNumber($clipRectangle[3] - $clipRectangle[1]);
                $prefix[] = 're';
                $prefix[] = 'W';
                $prefix[] = 'n';
            } else {
                $prefix[] = '3';
                $prefix[] = 'Tr';
            }
        }

        if ($fontResource === null) {
            return $prefix;
        }

        $prefix[] = '/' . $fontResource;
        $prefix[] = $fontSize ?? '12';
        $prefix[] = 'Tf';

        return $prefix;
    }

    /**
     * @param list<string> $operands
     * @param array<string, string> $properties
     */
    private function markedContentMcidOperand(array $operands, array $properties): ?int
    {
        for ($index = count($operands) - 1; $index >= 0; $index--) {
            $operand = trim($operands[$index]);
            if ($operand === '') {
                continue;
            }

            if (str_starts_with($operand, '<<')) {
                $mcid = $this->markedContentMcidFromDictionaryToken($operand);
                if ($mcid !== null) {
                    return $mcid;
                }
                continue;
            }

            if (str_starts_with($operand, '/')) {
                $name = $this->decodePdfName(substr($operand, 1));
                if (isset($properties[$name])) {
                    $mcid = $this->markedContentMcidFromDictionaryToken('<<' . $properties[$name] . '>>');
                    if ($mcid !== null) {
                        return $mcid;
                    }
                }
            }
        }

        return null;
    }

    private function markedContentMcidFromDictionaryToken(string $token): ?int
    {
        $dictionary = str_starts_with($token, '<<') ? $this->readPdfDictionaryAt($token, 0) : $token;
        if ($dictionary === null || preg_match('/\/MCID\s+(\d+)/s', $dictionary, $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    /**
     * @return array<string, string>
     * @param array<int, string> $objects
     */
    private function markedContentPropertyDictionaries(string $pageBody, array $objects): array
    {
        $resourceDictionary = $this->resourceDictionaryBody($pageBody, $objects);
        if ($resourceDictionary === null) {
            return [];
        }

        return $this->markedContentPropertyDictionariesFromResourceDictionary($resourceDictionary, $objects);
    }

    /**
     * @return array<string, string>
     * @param array<int, string> $objects
     */
    private function markedContentPropertyDictionariesFromResourceDictionary(string $resourceDictionary, array $objects): array
    {
        $propertiesDictionary = $this->propertiesResourceDictionaryBody($resourceDictionary, $objects);
        if ($propertiesDictionary === null) {
            return [];
        }

        $properties = [];
        if (preg_match_all('/\/([^\s\[\]()<>{}\/%]+)\s+(\d+)\s+\d+\s+R\b/s', $propertiesDictionary, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $objectNumber = (int) $match[2];
                $dictionary = isset($objects[$objectNumber]) ? $this->dictionaryObjectBody($objects[$objectNumber]) : null;
                if ($dictionary !== null) {
                    $properties[$this->decodePdfName($match[1])] = $dictionary;
                }
            }
        }

        $offset = 0;
        while (preg_match('/\/([^\s\[\]()<>{}\/%]+)\s*<</s', $propertiesDictionary, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $dictionaryOffset = strpos($propertiesDictionary, '<<', $match[0][1]);
            if ($dictionaryOffset === false) {
                break;
            }

            $dictionary = $this->readPdfDictionaryAt($propertiesDictionary, $dictionaryOffset);
            $end = $this->pdfDictionaryEndOffset($propertiesDictionary, $dictionaryOffset);
            if ($dictionary === null || $end === null) {
                break;
            }

            $properties[$this->decodePdfName($match[1][0])] = $dictionary;
            $offset = $end + 1;
        }

        return $properties;
    }

    private function formXObjectResourceOwnerBody(string $formBody, string $invokingResourceOwnerBody): string
    {
        return $this->topLevelNameValueOffset($formBody, 'Resources') === null
            ? $invokingResourceOwnerBody
            : $formBody;
    }

    private function formatPdfNumber(float $value): string
    {
        if (abs($value - round($value)) < 0.000001) {
            return (string) (int) round($value);
        }

        return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontObjectMaps
     * @param array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontToUnicodeMaps
     * @param array<int, true> $activeFormObjectNumbers
     * @param array<int, bool> $optionalContentStates
     * @param list<float>|null $initialTransformationMatrix
     * @param list<float>|null $formBoundingBox
     * @return array{stream: string, fontToUnicodeMaps: array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}>}
     */
    private function expandFormXObjectInvocations(
        string $content,
        string $resourceOwnerBody,
        array $objects,
        array $fontObjectMaps,
        array $fontToUnicodeMaps,
        array $optionalContentStates = [],
        array $activeFormObjectNumbers = [],
        ?array $initialTransformationMatrix = null,
        ?array $formBoundingBox = null
    ): array {
        $contentMayTransformTextPositions = $this->contentMayTransformTextPositions($content);
        $shouldTransformTextPositions = $contentMayTransformTextPositions
            || ($initialTransformationMatrix !== null && !$this->pdfMatrixIsIdentity($initialTransformationMatrix));
        if (
            !str_contains($content, 'Do')
            && !$shouldTransformTextPositions
            && $formBoundingBox === null
        ) {
            return [
                'stream' => $content,
                'fontToUnicodeMaps' => $fontToUnicodeMaps,
            ];
        }

        $xObjectMap = $this->xObjectResourceObjectNumbers($resourceOwnerBody, $objects);
        if (
            $xObjectMap === []
            && !$shouldTransformTextPositions
            && $formBoundingBox === null
        ) {
            return [
                'stream' => $content,
                'fontToUnicodeMaps' => $fontToUnicodeMaps,
            ];
        }

        $expanded = [];
        $expandedFontToUnicodeMaps = $fontToUnicodeMaps;
        $operands = [];
        $currentTransformationMatrix = $initialTransformationMatrix ?? [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];
        $graphicsStateStack = [];
        $textPositionState = [
            'x' => null,
            'y' => null,
            'leading' => null,
            'insideBBox' => true,
        ];
        foreach ($this->contentTokens($content) as $token) {
            if (!$this->isOperator($token)) {
                $operands[] = $token;
                continue;
            }

            if ($token === 'Do') {
                $xObjectName = $this->xObjectNameOperand($operands);
                if ($xObjectName !== null && isset($xObjectMap[$xObjectName])) {
                    $objectNumber = $xObjectMap[$xObjectName];
                    // Cyclic form resources should not turn page text extraction into
                    // an unbounded resource walk; later sibling Do calls still expand.
                    if (isset($activeFormObjectNumbers[$objectNumber])) {
                        $operands = [];
                        continue;
                    }

                    if (!$this->optionalContentObjectVisible($objects[$objectNumber], $objects, $optionalContentStates)) {
                        $operands = [];
                        continue;
                    }

                    $form = $this->decodedFormXObject($objects, $objectNumber);
                    if ($form !== null) {
                        $nextActiveForms = $activeFormObjectNumbers;
                        $nextActiveForms[$objectNumber] = true;
                        $formResourceOwnerBody = $this->formXObjectResourceOwnerBody($form['body'], $resourceOwnerBody);
                        $formFontMaps = $this->fontResourceMapsForResourceOwnerBody($formResourceOwnerBody, $objects, $fontObjectMaps);
                        $fontAliases = [];
                        foreach ($formFontMaps as $name => $map) {
                            $alias = $this->formFontResourceAlias($objectNumber, $name);
                            $fontAliases[$name] = $alias;
                            $expandedFontToUnicodeMaps[$alias] = $map;
                        }

                        $formStream = $this->filterOptionalContentMarkedBlocks(
                            $form['stream'],
                            $this->optionalContentPropertyVisibilityMapForResourceOwnerBody(
                                $formResourceOwnerBody,
                                $objects,
                                $optionalContentStates
                            )
                        );
                        $expandedForm = $this->expandFormXObjectInvocations(
                            $this->rewriteFontResourceOperands($formStream, $fontAliases),
                            $formResourceOwnerBody,
                            $objects,
                            $fontObjectMaps,
                            $expandedFontToUnicodeMaps,
                            $optionalContentStates,
                            $nextActiveForms,
                            $this->pdfMatrixMultiply(
                                $currentTransformationMatrix,
                                $this->pdfMatrixValueAfterName($form['body'], 'Matrix', $objects) ?? [1.0, 0.0, 0.0, 1.0, 0.0, 0.0]
                            ),
                            $this->pdfRectangleValueAfterName($form['body'], 'BBox', $objects)
                        );
                        $expanded[] = $expandedForm['stream'];
                        $expandedFontToUnicodeMaps = $expandedForm['fontToUnicodeMaps'];
                    }
                    $operands = [];
                    continue;
                }

                $this->appendContentOperator($expanded, $operands, $token);
                $operands = [];
                continue;
            }

            if ($token === 'q') {
                $graphicsStateStack[] = $currentTransformationMatrix;
                $this->appendContentOperator($expanded, $operands, $token);
                $operands = [];
                continue;
            }

            if ($token === 'Q') {
                $restoredMatrix = array_pop($graphicsStateStack);
                if (is_array($restoredMatrix)) {
                    $currentTransformationMatrix = $restoredMatrix;
                }
                $this->appendContentOperator($expanded, $operands, $token);
                $operands = [];
                continue;
            }

            if ($token === 'cm') {
                $matrix = $this->contentMatrixOperand($operands);
                if ($matrix !== null) {
                    $currentTransformationMatrix = $this->pdfMatrixMultiply($currentTransformationMatrix, $matrix);
                }
                $this->appendContentOperator($expanded, $operands, $token);
                $operands = [];
                continue;
            }

            $transformed = $this->transformedTextPositionOperatorTokens(
                $token,
                $operands,
                $currentTransformationMatrix,
                $formBoundingBox,
                $textPositionState
            );
            if ($transformed !== null) {
                foreach ($transformed as $transformedToken) {
                    $expanded[] = $transformedToken;
                }
                $operands = [];
                continue;
            }

            if (
                $formBoundingBox !== null
                && $this->isTextShowingOperator($token)
                && $textPositionState['insideBBox'] === false
            ) {
                $operands = [];
                continue;
            }

            $this->appendContentOperator($expanded, $operands, $token);
            $operands = [];
        }

        foreach ($operands as $operand) {
            $expanded[] = $operand;
        }

        return [
            'stream' => implode(' ', array_values(array_filter($expanded, static fn (string $segment): bool => trim($segment) !== ''))),
            'fontToUnicodeMaps' => $expandedFontToUnicodeMaps,
        ];
    }

    private function contentMayTransformTextPositions(string $content): bool
    {
        return preg_match('/(?:^|[\s\[\]()<>{}])cm(?:$|[\s\[\]()<>{}])/', $content) === 1;
    }

    /**
     * @param list<string> $expanded
     * @param list<string> $operands
     */
    private function appendContentOperator(array &$expanded, array $operands, string $operator): void
    {
        foreach ($operands as $operand) {
            $expanded[] = $operand;
        }
        $expanded[] = $operator;
    }

    /**
     * @param list<string> $operands
     * @return list<float>|null
     */
    private function contentMatrixOperand(array $operands): ?array
    {
        if (count($operands) < 6) {
            return null;
        }

        $matrix = [];
        foreach (array_slice($operands, -6) as $operand) {
            $number = $this->numericOperand($operand);
            if ($number === null) {
                return null;
            }
            $matrix[] = $number;
        }

        return $matrix;
    }

    /**
     * @param list<float> $matrix
     * @param list<float>|null $formBoundingBox
     * @param array{x: float|null, y: float|null, leading: float|null, insideBBox: bool} $textPositionState
     * @return list<string>|null
     */
    private function transformedTextPositionOperatorTokens(
        string $operator,
        array $operands,
        array $matrix,
        ?array $formBoundingBox,
        array &$textPositionState
    ): ?array {
        if ($operator === 'BT') {
            $textPositionState = [
                'x' => 0.0,
                'y' => 0.0,
                'leading' => null,
                'insideBBox' => $formBoundingBox === null
                    || $this->pdfPointInsideRectangle(0.0, 0.0, $formBoundingBox),
            ];
            if ($this->pdfMatrixIsIdentity($matrix) && $formBoundingBox === null) {
                return null;
            }
            $transformed = $this->pdfMatrixMultiply($matrix, [1.0, 0.0, 0.0, 1.0, 0.0, 0.0]);

            return [
                'BT',
                $this->formatPdfNumber($transformed[0]),
                $this->formatPdfNumber($transformed[1]),
                $this->formatPdfNumber($transformed[2]),
                $this->formatPdfNumber($transformed[3]),
                $this->formatPdfNumber($transformed[4]),
                $this->formatPdfNumber($transformed[5]),
                'Tm',
            ];
        }

        if ($operator === 'TL') {
            $leading = $this->numericOperand($operands[count($operands) - 1] ?? '');
            if ($leading !== null) {
                $textPositionState['leading'] = $leading;
            }
            return null;
        }

        if (!in_array($operator, ['Td', 'TD', 'Tm', 'T*'], true)) {
            return null;
        }

        if ($operator === 'T*') {
            if ($textPositionState['x'] === null || $textPositionState['y'] === null || $textPositionState['leading'] === null) {
                return null;
            }
            $textPositionState['y'] -= $textPositionState['leading'];
        } elseif ($operator === 'Tm') {
            if (count($operands) < 6) {
                return null;
            }
            $textMatrix = $this->contentMatrixOperand($operands);
            if ($textMatrix === null) {
                return null;
            }
            $textPositionState['x'] = $textMatrix[4];
            $textPositionState['y'] = $textMatrix[5];
        } else {
            if (count($operands) < 2) {
                return null;
            }
            $moveX = $this->numericOperand($operands[count($operands) - 2]);
            $moveY = $this->numericOperand($operands[count($operands) - 1]);
            if ($moveX === null || $moveY === null) {
                return null;
            }
            if ($operator === 'TD') {
                $textPositionState['leading'] = -$moveY;
            }
            $textPositionState['x'] = ($textPositionState['x'] ?? 0.0) + $moveX;
            $textPositionState['y'] = ($textPositionState['y'] ?? 0.0) + $moveY;
        }

        $localX = $textPositionState['x'] ?? 0.0;
        $localY = $textPositionState['y'] ?? 0.0;
        $textPositionState['insideBBox'] = $formBoundingBox === null
            || $this->pdfPointInsideRectangle($localX, $localY, $formBoundingBox);

        if ($this->pdfMatrixIsIdentity($matrix) && $formBoundingBox === null) {
            return null;
        }

        $textMatrix = $operator === 'Tm' && isset($textMatrix)
            ? $textMatrix
            : [1.0, 0.0, 0.0, 1.0, $localX, $localY];
        $transformed = $this->pdfMatrixMultiply($matrix, $textMatrix);

        return [
            $this->formatPdfNumber($transformed[0]),
            $this->formatPdfNumber($transformed[1]),
            $this->formatPdfNumber($transformed[2]),
            $this->formatPdfNumber($transformed[3]),
            $this->formatPdfNumber($transformed[4]),
            $this->formatPdfNumber($transformed[5]),
            'Tm',
        ];
    }

    /**
     * @return array<string, int>
     * @param array<int, string> $objects
     */
    private function xObjectResourceObjectNumbers(string $resourceOwnerBody, array $objects): array
    {
        $resourceDictionary = $this->resourceDictionaryBody($resourceOwnerBody, $objects) ?? $resourceOwnerBody;
        $xObjectDictionary = $this->xObjectResourceDictionaryBody($resourceDictionary, $objects);
        if ($xObjectDictionary === null) {
            return [];
        }

        if (!preg_match_all('/\/([^\s\[\]()<>{}\/%]+)\s+(\d+)\s+\d+\s+R\b/', $xObjectDictionary, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $resourceObjects = [];
        foreach ($matches as $resource) {
            $resourceObjects[$this->decodePdfName($resource[1])] = (int) $resource[2];
        }

        return $resourceObjects;
    }

    /**
     * @return list<string>
     * @param array<int, string> $objects
     */
    private function pageDecodedContentStreams(string $pageBody, array $objects): array
    {
        $streams = [];
        foreach ($this->pageContentObjectNumbers($pageBody, $objects) as $contentObjectNumber) {
            if (!isset($objects[$contentObjectNumber])) {
                continue;
            }

            $decoded = $this->decodeStreamObject($objects[$contentObjectNumber], $objects);
            if ($decoded !== null) {
                $streams[] = $decoded;
            }
        }

        return $streams;
    }

    /**
     * @param list<list<float>|array{matrix?: list<float>, clip_bbox?: list<float>|null}> $baseStates
     * @return array<string, list<array{matrix: list<float>, bbox: list<float>, clip_bbox: list<float>|null, visible_bbox: list<float>|null, clipped: bool}>>
     */
    private function contentXObjectInvocationDetails(string $content, array $baseStates = []): array
    {
        $currentStates = $this->normalizedInvocationBaseStates($baseStates);
        $graphicsStateStack = [];
        $invocations = [];
        $operands = [];

        foreach ($this->contentTokens($content) as $token) {
            if (!$this->isOperator($token)) {
                $operands[] = $token;
                continue;
            }

            if ($token === 'q') {
                $graphicsStateStack[] = $currentStates;
                $operands = [];
                continue;
            }

            if ($token === 'Q') {
                $restoredStates = array_pop($graphicsStateStack);
                if (is_array($restoredStates)) {
                    $currentStates = $restoredStates;
                }
                $operands = [];
                continue;
            }

            $clipPathOperatorHandled = false;
            foreach ($currentStates as $index => $state) {
                $matrix = $state['matrix'];
                $clipRectangle = $state['clip_bbox'];
                $currentPathRectangle = $state['path_bbox'];
                if ($this->applyClipPathStateOperator(
                    $token,
                    $operands,
                    $clipRectangle,
                    $currentPathRectangle,
                    $matrix
                )) {
                    $currentStates[$index]['matrix'] = $matrix;
                    $currentStates[$index]['clip_bbox'] = $clipRectangle;
                    $currentStates[$index]['path_bbox'] = $currentPathRectangle;
                    $clipPathOperatorHandled = true;
                }
            }
            if ($clipPathOperatorHandled) {
                $operands = [];
                continue;
            }

            if ($token === 'Do') {
                $resourceName = $this->xObjectNameOperand($operands);
                if ($resourceName !== null) {
                    foreach ($currentStates as $state) {
                        $normalizedMatrix = $this->normalizedPdfReviewNumbers($state['matrix']);
                        $bbox = $this->imageUnitBboxForMatrix($normalizedMatrix);
                        $clipRectangle = $this->normalizedPdfRectangleOrNull($state['clip_bbox']);
                        $visibleBbox = $this->visibleImageInvocationBbox($bbox, $clipRectangle);
                        $invocations[$resourceName][] = [
                            'matrix' => $normalizedMatrix,
                            'bbox' => $bbox,
                            'clip_bbox' => $clipRectangle,
                            'visible_bbox' => $visibleBbox,
                            'clipped' => $clipRectangle !== null
                                && ($visibleBbox === null || !$this->pdfRectanglesEqual($bbox, $visibleBbox)),
                        ];
                    }
                }
                $operands = [];
                continue;
            }

            $operands = [];
        }

        return $invocations;
    }

    /**
     * @param list<list<float>|array{matrix?: list<float>, clip_bbox?: list<float>|null}> $baseStates
     * @return list<array{matrix: list<float>, clip_bbox: list<float>|null, path_bbox: list<float>|null}>
     */
    private function normalizedInvocationBaseStates(array $baseStates): array
    {
        if ($baseStates === []) {
            return [[
                'matrix' => [1.0, 0.0, 0.0, 1.0, 0.0, 0.0],
                'clip_bbox' => null,
                'path_bbox' => null,
            ]];
        }

        $normalized = [];
        foreach ($baseStates as $state) {
            if (!is_array($state)) {
                continue;
            }

            $matrixValue = array_key_exists('matrix', $state) ? $state['matrix'] : $state;
            $matrix = $this->normalizedPdfMatrixOrNull($matrixValue);
            if ($matrix === null) {
                continue;
            }

            $normalized[] = [
                'matrix' => $matrix,
                'clip_bbox' => $this->normalizedPdfRectangleOrNull($state['clip_bbox'] ?? null),
                'path_bbox' => null,
            ];
        }

        return $normalized === [] ? [[
            'matrix' => [1.0, 0.0, 0.0, 1.0, 0.0, 0.0],
            'clip_bbox' => null,
            'path_bbox' => null,
        ]] : $normalized;
    }

    /**
     * @param list<float> $matrix
     * @return list<float>
     */
    private function imageUnitBboxForMatrix(array $matrix): array
    {
        $points = [
            $this->pdfMatrixTransformPoint($matrix, 0.0, 0.0),
            $this->pdfMatrixTransformPoint($matrix, 1.0, 0.0),
            $this->pdfMatrixTransformPoint($matrix, 0.0, 1.0),
            $this->pdfMatrixTransformPoint($matrix, 1.0, 1.0),
        ];
        $xs = array_column($points, 0);
        $ys = array_column($points, 1);

        return $this->normalizedPdfReviewNumbers([
            min($xs),
            min($ys),
            max($xs),
            max($ys),
        ]);
    }

    /**
     * @param mixed $value
     * @return list<float>|null
     */
    private function normalizedPdfMatrixOrNull(mixed $value): ?array
    {
        if (!is_array($value) || count($value) < 6) {
            return null;
        }

        $matrix = [];
        foreach (array_slice($value, 0, 6) as $number) {
            if (!is_int($number) && !is_float($number)) {
                return null;
            }
            $matrix[] = (float) $number;
        }

        return $matrix;
    }

    /**
     * @param mixed $value
     * @return list<float>|null
     */
    private function normalizedPdfRectangleOrNull(mixed $value): ?array
    {
        if (!is_array($value) || count($value) < 4) {
            return null;
        }

        $rectangle = [];
        foreach (array_slice($value, 0, 4) as $number) {
            if (!is_int($number) && !is_float($number)) {
                return null;
            }
            $rectangle[] = (float) $number;
        }

        return $this->normalizedPdfReviewNumbers($rectangle);
    }

    /**
     * @param list<float> $bbox
     * @param list<float>|null $clipRectangle
     * @return list<float>|null
     */
    private function visibleImageInvocationBbox(array $bbox, ?array $clipRectangle): ?array
    {
        if ($clipRectangle === null) {
            return $this->pdfRectangleHasArea($bbox) ? $this->normalizedPdfReviewNumbers($bbox) : null;
        }

        $visible = $this->pdfRectangleIntersection($clipRectangle, $bbox);
        if (!$this->pdfRectangleHasArea($visible)) {
            return null;
        }

        return $this->normalizedPdfReviewNumbers($visible);
    }

    /**
     * @param list<float> $left
     * @param list<float> $right
     */
    private function pdfRectanglesEqual(array $left, array $right): bool
    {
        if (count($left) < 4 || count($right) < 4) {
            return false;
        }

        for ($index = 0; $index < 4; $index++) {
            if (abs((float) $left[$index] - (float) $right[$index]) > 0.000001) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<float> $values
     * @return list<float>
     */
    private function normalizedPdfReviewNumbers(array $values): array
    {
        return array_map(
            static function (int|float $value): float {
                $value = (float) $value;
                if (abs($value) < 0.000001) {
                    return 0.0;
                }

                return round($value, 6);
            },
            $values
        );
    }

    /**
     * @return list<array<string, mixed>>
     * @param array<int, string> $objects
     * @param list<string> $decodedContents
     * @param array<int, bool> $optionalContentStates
     * @param list<string> $resourcePath
     * @param array<int, true> $activeFormObjectNumbers
     */
    private function imageXObjectBoundaryEntriesForResourceOwner(
        int $pageIndex,
        int $pageObjectNumber,
        string $resourceOwnerBody,
        array $objects,
        array $decodedContents,
        array $optionalContentStates = [],
        array $resourcePath = [],
        array $activeFormObjectNumbers = [],
        ?int $parentFormObjectNumber = null,
        bool $includeUninvokedImageResources = true,
        array $ownerInvocationMatrices = []
    ): array {
        $xObjectMap = $this->xObjectResourceObjectNumbers($resourceOwnerBody, $objects);
        if ($xObjectMap === []) {
            return [];
        }

        if ($decodedContents !== []) {
            $decodedContents = [implode("\n", $decodedContents)];
        }

        $optionalContentProperties = $this->optionalContentPropertyVisibilityMapForResourceOwnerBody(
            $resourceOwnerBody,
            $objects,
            $optionalContentStates
        );
        if ($optionalContentProperties !== []) {
            $decodedContents = array_map(
                fn (string $content): string => $this->filterOptionalContentMarkedBlocks($content, $optionalContentProperties),
                $decodedContents
            );
        }

        $invocations = [];
        foreach ($decodedContents as $content) {
            foreach ($this->contentXObjectInvocationDetails($content, $ownerInvocationMatrices) as $resourceName => $details) {
                $invocations[$resourceName] = array_merge($invocations[$resourceName] ?? [], $details);
            }
        }

        $entries = [];
        foreach ($xObjectMap as $resourceName => $objectNumber) {
            $optionalContentVisible = isset($objects[$objectNumber])
                ? $this->optionalContentObjectVisible($objects[$objectNumber], $objects, $optionalContentStates)
                : true;
            $localInvocationDetails = $invocations[$resourceName] ?? [];
            $effectiveInvocationDetails = $localInvocationDetails !== [] && $optionalContentVisible
                ? $localInvocationDetails
                : [];
            $entryResourcePath = [...$resourcePath, $resourceName];
            $entry = $this->imageXObjectBoundaryEntry(
                $pageIndex,
                $pageObjectNumber,
                $resourceName,
                $objectNumber,
                $effectiveInvocationDetails,
                $objects,
                $entryResourcePath,
                $parentFormObjectNumber,
                $optionalContentVisible
            );
            if ($entry !== null && ($includeUninvokedImageResources || $entry['invoked'])) {
                $entries[] = $entry;
            }

            if ($localInvocationDetails === [] || !$optionalContentVisible || isset($activeFormObjectNumbers[$objectNumber])) {
                continue;
            }

            $form = $this->decodedFormXObject($objects, $objectNumber);
            if ($form === null) {
                continue;
            }

            $nextActiveForms = $activeFormObjectNumbers;
            $nextActiveForms[$objectNumber] = true;
            $formHasOwnResources = $this->topLevelNameValueOffset($form['body'], 'Resources') !== null;
            $formResourceOwnerBody = $this->formXObjectResourceOwnerBody($form['body'], $resourceOwnerBody);
            $formMatrix = $this->pdfMatrixValueAfterName($form['body'], 'Matrix', $objects)
                ?? [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];
            $formInvocationStates = [];
            foreach ($localInvocationDetails as $detail) {
                $matrix = $detail['matrix'] ?? null;
                if (!is_array($matrix) || count($matrix) < 6) {
                    continue;
                }
                $formBaseMatrix = $this->pdfMatrixMultiply($matrix, $formMatrix);
                $formClipBbox = $this->normalizedPdfRectangleOrNull($detail['clip_bbox'] ?? null);
                $formBbox = $this->pdfRectangleValueAfterName($form['body'], 'BBox', $objects);
                if ($formBbox !== null) {
                    $formClipBbox = $this->pdfRectangleIntersection(
                        $formClipBbox,
                        $this->pdfRectangleTransform($formBaseMatrix, $formBbox)
                    );
                }
                $formInvocationStates[] = [
                    'matrix' => $formBaseMatrix,
                    'clip_bbox' => $formClipBbox,
                ];
            }
            foreach ($this->imageXObjectBoundaryEntriesForResourceOwner(
                $pageIndex,
                $pageObjectNumber,
                $formResourceOwnerBody,
                $objects,
                [$form['stream']],
                $optionalContentStates,
                $entryResourcePath,
                $nextActiveForms,
                $objectNumber,
                $formHasOwnResources,
                $formInvocationStates
            ) as $nestedEntry) {
                $entries[] = $nestedEntry;
            }
        }

        return $this->suppressUninvokedImageResourceEntriesPaintedThroughForms($entries);
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return list<array<string, mixed>>
     */
    private function suppressUninvokedImageResourceEntriesPaintedThroughForms(array $entries): array
    {
        $nestedInvokedResourceKeys = [];
        foreach ($entries as $entry) {
            $resourcePath = $entry['resource_path'] ?? null;
            $objectNumber = $entry['object_number'] ?? null;
            if (
                ($entry['invoked'] ?? false) !== true
                || !is_array($resourcePath)
                || count($resourcePath) < 2
                || !is_int($objectNumber)
            ) {
                continue;
            }

            $nestedInvokedResourceKeys[$objectNumber . "\0" . (string) ($entry['resource_name'] ?? '')] = true;
        }

        if ($nestedInvokedResourceKeys === []) {
            return $entries;
        }

        $filtered = [];
        foreach ($entries as $entry) {
            $resourcePath = $entry['resource_path'] ?? null;
            $objectNumber = $entry['object_number'] ?? null;
            if (
                ($entry['invoked'] ?? false) === false
                && is_array($resourcePath)
                && count($resourcePath) === 1
                && is_int($objectNumber)
                && isset($nestedInvokedResourceKeys[$objectNumber . "\0" . (string) ($entry['resource_name'] ?? '')])
            ) {
                continue;
            }

            $filtered[] = $entry;
        }

        return array_values($filtered);
    }

    /**
     * @return array<string, mixed>|null
     * @param array<int, string> $objects
     * @param list<string> $resourcePath
     */
    private function imageXObjectBoundaryEntry(
        int $pageIndex,
        int $pageObjectNumber,
        string $resourceName,
        int $objectNumber,
        array $invocationDetails,
        array $objects,
        array $resourcePath = [],
        ?int $parentFormObjectNumber = null,
        bool $optionalContentVisible = true
    ): ?array {
        if (!isset($objects[$objectNumber])) {
            return null;
        }

        $stream = $this->streamDictionaryAndPayload($objects[$objectNumber], $objects);
        if ($stream === null || !$this->isImageStreamDictionary($stream['dict'], $objects)) {
            return null;
        }

        $filters = $this->streamFilters($stream['dict'], $objects);
        $resolvedFilters = $filters === null
            ? []
            : array_values(array_filter($filters, static fn (?string $filter): bool => is_string($filter)));
        $previewOnlyFilters = $this->previewOnlyImageXObjectFilters($resolvedFilters);
        $decodeParms = $filters === null ? null : $this->streamDecodeParms($stream['dict'], $objects);
        $filterDetails = $filters === null ? [] : $this->imageXObjectFilterDetails($filters, $decodeParms, $objects);
        $decoded = $filters === null ? null : $this->decodeStream($stream['dict'], $stream['stream'], $objects);
        $colorSpace = $this->imageColorSpaceFamily($stream['dict'], $objects);
        $bitsPerComponent = $this->pdfIntegerValueAfterNameResolvingObjects(
            $stream['dict'],
            'BitsPerComponent',
            $objects
        );
        $imageMask = $this->pdfBooleanValueAfterName($stream['dict'], 'ImageMask') === true;
        $metadataStream = $this->imageXObjectMetadataStreamReview($stream['dict'], $objects);
        $alternateImages = $this->imageXObjectAlternateImageReviews($stream['dict'], $objects);
        $invocationMatrices = [];
        $invocationBboxes = [];
        $invocationClipBboxes = [];
        $invocationVisibleBboxes = [];
        $clipApplied = false;
        $clipReducesPaintedBbox = false;
        $clipExcludedInvocationCount = 0;
        foreach ($invocationDetails as $detail) {
            $matrix = $detail['matrix'] ?? null;
            $bbox = $detail['bbox'] ?? null;
            $clipBbox = $detail['clip_bbox'] ?? null;
            $visibleBbox = $detail['visible_bbox'] ?? null;
            if (is_array($matrix) && count($matrix) >= 6) {
                $invocationMatrices[] = $this->normalizedPdfReviewNumbers(array_slice($matrix, 0, 6));
            }
            if (is_array($bbox) && count($bbox) >= 4) {
                $invocationBboxes[] = $this->normalizedPdfReviewNumbers(array_slice($bbox, 0, 4));
            }
            if (is_array($clipBbox) && count($clipBbox) >= 4) {
                $clipApplied = true;
                $invocationClipBboxes[] = $this->normalizedPdfReviewNumbers(array_slice($clipBbox, 0, 4));
            }
            if (is_array($visibleBbox) && count($visibleBbox) >= 4) {
                $invocationVisibleBboxes[] = $this->normalizedPdfReviewNumbers(array_slice($visibleBbox, 0, 4));
            } elseif (is_array($clipBbox) && count($clipBbox) >= 4) {
                $clipExcludedInvocationCount++;
            }
            if (($detail['clipped'] ?? false) === true) {
                $clipReducesPaintedBbox = true;
            }
        }
        $imageUnitBbox = null;
        foreach ($invocationBboxes as $bbox) {
            $imageUnitBbox = $this->pdfRectangleUnion($imageUnitBbox, $bbox);
        }
        $imageUnitBbox = $imageUnitBbox === null ? null : $this->normalizedPdfReviewNumbers($imageUnitBbox);
        $imageVisibleBbox = null;
        foreach ($invocationVisibleBboxes as $bbox) {
            $imageVisibleBbox = $this->pdfRectangleUnion($imageVisibleBbox, $bbox);
        }
        $imageVisibleBbox = $imageVisibleBbox === null ? null : $this->normalizedPdfReviewNumbers($imageVisibleBbox);

        return [
            'page_index' => $pageIndex,
            'page_object' => $pageObjectNumber,
            'resource_name' => $resourceName,
            'resource_path' => $resourcePath === [] ? [$resourceName] : $resourcePath,
            'form_xobject_depth' => max(0, count($resourcePath) - 1),
            'parent_form_xobject_object' => $parentFormObjectNumber,
            'object_number' => $objectNumber,
            'optional_content_visible' => $optionalContentVisible,
            'invoked' => $invocationMatrices !== [],
            'invocation_count' => count($invocationMatrices),
            'painted_invocation_count' => count($invocationVisibleBboxes),
            'invocation_matrices' => $invocationMatrices,
            'invocation_bboxes' => $invocationBboxes,
            'invocation_clip_bboxes' => $invocationClipBboxes,
            'invocation_visible_bboxes' => $invocationVisibleBboxes,
            'image_unit_bbox' => $imageUnitBbox,
            'image_visible_bbox' => $imageVisibleBbox,
            'clip_applied' => $clipApplied,
            'clip_reduces_painted_bbox' => $clipReducesPaintedBbox,
            'clip_excludes_image' => $clipExcludedInvocationCount > 0,
            'clip_excluded_invocation_count' => $clipExcludedInvocationCount,
            'placement_review_only' => true,
            'subtype' => $this->pdfNameValueAfterNameResolvingObjects($stream['dict'], 'Subtype', $objects) ?? 'Image',
            'width' => $this->pdfIntegerValueAfterNameResolvingObjects($stream['dict'], 'Width', $objects),
            'height' => $this->pdfIntegerValueAfterNameResolvingObjects($stream['dict'], 'Height', $objects),
            'color_space' => $colorSpace,
            'bits_per_component' => $imageMask ? ($bitsPerComponent ?? 1) : $bitsPerComponent,
            'image_mask' => $imageMask,
            'interpolate' => $this->pdfBooleanValueAfterNameResolvingObjects($stream['dict'], 'Interpolate', $objects),
            'rendering_intent' => $this->pdfNameValueAfterNameResolvingObjects($stream['dict'], 'Intent', $objects),
            'image_name' => $this->pdfNameValueAfterNameResolvingObjects($stream['dict'], 'Name', $objects),
            'struct_parent' => $this->pdfIntegerValueAfterNameResolvingObjects($stream['dict'], 'StructParent', $objects),
            'struct_parents' => $this->pdfIntegerValueAfterNameResolvingObjects($stream['dict'], 'StructParents', $objects),
            'metadata_stream' => $metadataStream,
            'alternate_image_count' => count($alternateImages),
            'alternate_images' => $alternateImages,
            'alternates_review_only' => $alternateImages !== [],
            'soft_mask_object' => $this->objectReferenceValueAfterName($stream['dict'], 'SMask'),
            'filters_resolved' => $filters !== null,
            'filters' => $resolvedFilters,
            'preview_only_filters' => $previewOnlyFilters,
            'filter_details' => $filterDetails,
            'native_raster_decode' => $previewOnlyFilters === [],
            'raw_length' => strlen($stream['stream']),
            'decoded_with_current_filters' => $decoded !== null,
            'decoded_length' => $decoded === null ? null : strlen($decoded),
            'decoded_sha256' => $decoded === null ? null : hash('sha256', $decoded),
            'payload_in_visible_text' => false,
            'rgb_preview_boundary' => 'marker.pdf.images.render_image',
            'review_only' => true,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function imageXObjectAlternateImageReviews(string $imageDictionary, array $objects): array
    {
        $value = $this->topLevelPdfValueAfterNameInDictionaryBody($imageDictionary, 'Alternates');
        if ($value === null) {
            return [];
        }

        $arrayBody = $this->pdfArrayAtStart(trim($value));
        if ($arrayBody === null) {
            return [];
        }

        $reviews = [];
        foreach ($this->pdfArrayItems($arrayBody) as $item) {
            $item = trim($item);
            $defaultForPrinting = null;
            $objectNumber = null;

            if (str_starts_with($item, '<<')) {
                $dictionary = $this->readPdfDictionaryAt($item, 0);
                if ($dictionary === null) {
                    continue;
                }

                $objectNumber = $this->objectReferenceValueAfterName($dictionary, 'Image');
                $defaultForPrinting = $this->pdfBooleanValueAfterName($dictionary, 'DefaultForPrinting');
            } else {
                $objectNumber = $this->firstObjectReference($item);
            }

            if ($objectNumber === null) {
                continue;
            }

            $review = $this->imageXObjectAlternateStreamReview($objectNumber, $defaultForPrinting, $objects);
            if ($review !== null) {
                $reviews[] = $review;
            }
        }

        return $reviews;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function imageXObjectAlternateStreamReview(int $objectNumber, ?bool $defaultForPrinting, array $objects): ?array
    {
        if (!isset($objects[$objectNumber])) {
            return null;
        }

        $stream = $this->streamDictionaryAndPayload($objects[$objectNumber], $objects);
        if ($stream === null || !$this->isImageStreamDictionary($stream['dict'], $objects)) {
            return null;
        }

        $filters = $this->streamFilters($stream['dict'], $objects);
        $resolvedFilters = $filters === null
            ? []
            : array_values(array_filter($filters, static fn (?string $filter): bool => is_string($filter)));
        $previewOnlyFilters = $this->previewOnlyImageXObjectFilters($resolvedFilters);
        $decoded = $filters === null ? null : $this->decodeStream($stream['dict'], $stream['stream'], $objects);
        $bitsPerComponent = $this->pdfIntegerValueAfterNameResolvingObjects(
            $stream['dict'],
            'BitsPerComponent',
            $objects
        );
        $imageMask = $this->pdfBooleanValueAfterName($stream['dict'], 'ImageMask') === true;

        return [
            'object_number' => $objectNumber,
            'default_for_printing' => $defaultForPrinting,
            'subtype' => $this->pdfNameValueAfterNameResolvingObjects($stream['dict'], 'Subtype', $objects) ?? 'Image',
            'width' => $this->pdfIntegerValueAfterNameResolvingObjects($stream['dict'], 'Width', $objects),
            'height' => $this->pdfIntegerValueAfterNameResolvingObjects($stream['dict'], 'Height', $objects),
            'color_space' => $this->imageColorSpaceFamily($stream['dict'], $objects),
            'bits_per_component' => $imageMask ? ($bitsPerComponent ?? 1) : $bitsPerComponent,
            'image_mask' => $imageMask,
            'filters' => $resolvedFilters,
            'preview_only_filters' => $previewOnlyFilters,
            'native_raster_decode' => $previewOnlyFilters === [],
            'raw_length' => strlen($stream['stream']),
            'decoded_with_current_filters' => $decoded !== null,
            'decoded_length' => $decoded === null ? null : strlen($decoded),
            'decoded_sha256' => $decoded === null ? null : hash('sha256', $decoded),
            'payload_in_visible_text' => false,
            'review_only' => true,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array{
     *     object_number: int,
     *     subtype: string|null,
     *     filters: list<string>,
     *     preview_only_filters: list<string>,
     *     raw_length: int,
     *     decoded_with_current_filters: bool,
     *     decoded_length: int|null,
     *     decoded_sha256: string|null,
     *     payload_in_visible_text: false,
     *     review_only: true
     * }|null
     */
    private function imageXObjectMetadataStreamReview(string $imageDictionary, array $objects): ?array
    {
        $metadataObjectNumber = $this->objectReferenceValueAfterName($imageDictionary, 'Metadata');
        if ($metadataObjectNumber === null || !isset($objects[$metadataObjectNumber])) {
            return null;
        }

        $stream = $this->streamDictionaryAndPayload($objects[$metadataObjectNumber], $objects);
        if ($stream === null) {
            return null;
        }

        $filters = $this->streamFilters($stream['dict'], $objects);
        $resolvedFilters = $filters === null
            ? []
            : array_values(array_filter($filters, static fn (?string $filter): bool => is_string($filter)));
        $decoded = $filters === null ? null : $this->decodeStream($stream['dict'], $stream['stream'], $objects);

        return [
            'object_number' => $metadataObjectNumber,
            'subtype' => $this->pdfNameValueAfterNameResolvingObjects($stream['dict'], 'Subtype', $objects),
            'filters' => $resolvedFilters,
            'preview_only_filters' => $this->previewOnlyImageXObjectFilters($resolvedFilters),
            'raw_length' => strlen($stream['stream']),
            'decoded_with_current_filters' => $decoded !== null,
            'decoded_length' => $decoded === null ? null : strlen($decoded),
            'decoded_sha256' => $decoded === null ? null : hash('sha256', $decoded),
            'payload_in_visible_text' => false,
            'review_only' => true,
        ];
    }

    /**
     * @param list<string> $filters
     * @return list<string>
     */
    private function previewOnlyImageXObjectFilters(array $filters): array
    {
        return array_values(array_filter(
            $filters,
            static fn (string $filter): bool => in_array(
                $filter,
                ['DCTDecode', 'DCT', 'CCITTFaxDecode', 'CCF', 'JPXDecode', 'JBIG2Decode'],
                true
            )
        ));
    }

    /**
     * @param list<string|null> $filters
     * @param list<string|null>|null $decodeParms
     * @param array<int, string> $objects
     * @return list<array{filter: string, preview_only: bool, decode_parms: array<string, int|bool|string|null|list<string>>|null}>
     */
    private function imageXObjectFilterDetails(array $filters, ?array $decodeParms, array $objects): array
    {
        $details = [];
        $stringFilterCount = count(array_filter($filters, static fn (?string $filter): bool => is_string($filter)));

        foreach ($filters as $index => $filter) {
            if (!is_string($filter)) {
                continue;
            }

            $decodeParmsValue = $decodeParms === null
                ? null
                : ($decodeParms[$index] ?? ($stringFilterCount === 1 ? ($decodeParms[0] ?? null) : null));

            $details[] = [
                'filter' => $filter,
                'preview_only' => in_array($filter, ['DCTDecode', 'DCT', 'CCITTFaxDecode', 'CCF', 'JPXDecode', 'JBIG2Decode'], true),
                'decode_parms' => $this->imageXObjectFilterDecodeParms($filter, $decodeParmsValue, $objects),
            ];
        }

        return $details;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, int|bool|string|null|list<string>>|null
     */
    private function imageXObjectFilterDecodeParms(string $filter, ?string $value, array $objects): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if ($filter === 'CCITTFaxDecode' || $filter === 'CCF') {
            return $this->ccittFaxDecodeParmsReview($value, $objects);
        }

        if ($filter === 'DCTDecode' || $filter === 'DCT') {
            $colorTransform = $this->decodeParmsInt($value, 'ColorTransform', $objects);

            return [
                'type' => 'DCTDecode',
                'color_transform' => $colorTransform,
                'valid_color_transform' => $colorTransform === null || in_array($colorTransform, [0, 1, 2], true),
            ];
        }

        return ['type' => $filter];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, int|bool|string|null|list<string>>
     */
    private function ccittFaxDecodeParmsReview(string $decodeParms, array $objects): array
    {
        $details = [
            'type' => 'CCITTFaxDecode',
            'k' => $this->decodeParmsInt($decodeParms, 'K', $objects),
            'columns' => $this->decodeParmsInt($decodeParms, 'Columns', $objects),
            'rows' => $this->decodeParmsInt($decodeParms, 'Rows', $objects),
            'black_is_1' => $this->decodeParmsBool($decodeParms, 'BlackIs1', $objects),
            'encoded_byte_align' => $this->decodeParmsBool($decodeParms, 'EncodedByteAlign', $objects),
            'end_of_line' => $this->decodeParmsBool($decodeParms, 'EndOfLine', $objects),
            'end_of_block' => $this->decodeParmsBool($decodeParms, 'EndOfBlock', $objects),
            'damaged_rows_before_error' => $this->decodeParmsInt($decodeParms, 'DamagedRowsBeforeError', $objects),
        ];

        $invalidFields = [];
        foreach (['K' => 'k'] as $pdfName => $field) {
            if ($this->decodeParmsHasName($decodeParms, $pdfName) && $details[$field] === null) {
                $invalidFields[$field] = true;
            }
        }

        foreach (['Columns' => 'columns'] as $pdfName => $field) {
            if (
                $this->decodeParmsHasName($decodeParms, $pdfName)
                && (!is_int($details[$field]) || $details[$field] < 1)
            ) {
                $invalidFields[$field] = true;
            }
        }

        foreach (['Rows' => 'rows', 'DamagedRowsBeforeError' => 'damaged_rows_before_error'] as $pdfName => $field) {
            if (
                $this->decodeParmsHasName($decodeParms, $pdfName)
                && (!is_int($details[$field]) || $details[$field] < 0)
            ) {
                $invalidFields[$field] = true;
            }
        }

        foreach ([
            'BlackIs1' => 'black_is_1',
            'EncodedByteAlign' => 'encoded_byte_align',
            'EndOfLine' => 'end_of_line',
            'EndOfBlock' => 'end_of_block',
        ] as $pdfName => $field) {
            if ($this->decodeParmsHasName($decodeParms, $pdfName) && !is_bool($details[$field])) {
                $invalidFields[$field] = true;
            }
        }

        if ($invalidFields !== []) {
            $details['valid_decode_parms'] = false;
            $details['invalid_decode_parms_fields'] = array_values(array_filter(
                [
                    'k',
                    'columns',
                    'rows',
                    'black_is_1',
                    'encoded_byte_align',
                    'end_of_line',
                    'end_of_block',
                    'damaged_rows_before_error',
                ],
                static fn (string $field): bool => isset($invalidFields[$field])
            ));
            $details['decode_parms_review'] = 'invalid_ccitt_decodeparms_fail_closed';
        }

        return $details;
    }

    /**
     * @param array<int, string> $objects
     */
    private function xObjectResourceDictionaryBody(string $resourceDictionary, array $objects): ?string
    {
        return $this->resourceCategoryDictionaryBody($resourceDictionary, $objects, 'XObject');
    }

    /**
     * @return array{body: string, stream: string}|null
     * @param array<int, string> $objects
     */
    private function decodedFormXObject(array $objects, int $objectNumber): ?array
    {
        if (!isset($objects[$objectNumber]) || preg_match('/\/Subtype\s*\/Form\b/', $objects[$objectNumber]) !== 1) {
            return null;
        }

        $decoded = $this->decodeStreamObject($objects[$objectNumber], $objects);
        if ($decoded === null) {
            return null;
        }

        return [
            'body' => $objects[$objectNumber],
            'stream' => $decoded,
        ];
    }

    /**
     * @return array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}>
     * @param array<int, string> $objects
     * @param array<int, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontObjectMaps
     */
    private function fontResourceMapsForResourceOwnerBody(string $resourceOwnerBody, array $objects, array $fontObjectMaps): array
    {
        $resourceDictionary = $this->resourceDictionaryBody($resourceOwnerBody, $objects) ?? $resourceOwnerBody;

        return $this->fontResourceMapsFromResourceDictionary($resourceDictionary, $objects, $fontObjectMaps);
    }

    /**
     * @return array<string, array{actualText: string|null, altText: string|null}>
     * @param array<int, string> $objects
     */
    private function pageMarkedContentProperties(int $pageObjectNumber, array $objects): array
    {
        $resourceDictionary = $this->pageResourceDictionaryBody($pageObjectNumber, $objects);
        if ($resourceDictionary === null) {
            return [];
        }

        return $this->markedContentPropertiesFromResourceDictionary($resourceDictionary, $objects);
    }

    /**
     * @return array<string, string>
     * @param array<int, string> $objects
     */
    private function pageMarkedContentPropertyDictionaries(int $pageObjectNumber, array $objects): array
    {
        $resourceDictionary = $this->pageResourceDictionaryBody($pageObjectNumber, $objects);
        if ($resourceDictionary === null) {
            return [];
        }

        return $this->markedContentPropertyDictionariesFromResourceDictionary($resourceDictionary, $objects);
    }

    /**
     * @param array<string, string> $fontAliases
     */
    private function rewriteFontResourceOperands(string $content, array $fontAliases): string
    {
        if ($fontAliases === []) {
            return $content;
        }

        $rewritten = [];
        $operands = [];
        foreach ($this->contentTokens($content) as $token) {
            if ($this->isOperator($token)) {
                if ($token === 'Tf' && count($operands) >= 2) {
                    $fontOperandIndex = count($operands) - 2;
                    $fontOperand = $operands[$fontOperandIndex];
                    if (str_starts_with($fontOperand, '/')) {
                        $fontName = $this->decodePdfName(substr($fontOperand, 1));
                        if (isset($fontAliases[$fontName])) {
                            $operands[$fontOperandIndex] = '/' . $fontAliases[$fontName];
                        }
                    }
                }

                foreach ($operands as $operand) {
                    $rewritten[] = $operand;
                }
                $rewritten[] = $token;
                $operands = [];
                continue;
            }

            $operands[] = $token;
        }

        foreach ($operands as $operand) {
            $rewritten[] = $operand;
        }

        return implode(' ', $rewritten);
    }

    private function formFontResourceAlias(int $formObjectNumber, string $resourceName): string
    {
        return 'Fm' . $formObjectNumber . '_' . bin2hex($resourceName);
    }

    private function appearanceFontResourceAlias(int $appearanceObjectNumber, string $resourceName): string
    {
        return 'Ap' . $appearanceObjectNumber . '_' . bin2hex($resourceName);
    }

    /**
     * @return list<array{stream: string, fontToUnicodeMaps: array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}>}>
     * @param array<int, string> $objects
     * @param array<int, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontObjectMaps
     * @param array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontToUnicodeMaps
     * @param array<int, bool> $optionalContentStates
     */
    private function annotationAppearanceStreamsWithFontMaps(
        string $pageBody,
        array $objects,
        array $fontObjectMaps,
        array $fontToUnicodeMaps,
        array $optionalContentStates = []
    ): array {
        $appearances = [];
        $currentFontToUnicodeMaps = $fontToUnicodeMaps;

        foreach ($this->annotationBodiesForPage($pageBody, $objects) as $annotation) {
            if (!$this->annotationAppearanceContributesText($annotation['body'])) {
                continue;
            }

            if (!$this->optionalContentObjectVisible($annotation['body'], $objects, $optionalContentStates)) {
                continue;
            }

            $appearanceObjectNumber = $this->normalAppearanceObjectNumber($annotation['body'], $objects);
            if ($appearanceObjectNumber === null) {
                continue;
            }

            $appearance = $this->decodedAppearanceStreamWithFontMaps(
                $appearanceObjectNumber,
                $objects,
                $fontObjectMaps,
                $currentFontToUnicodeMaps,
                $optionalContentStates
            );
            if ($appearance === null) {
                continue;
            }

            $appearances[] = $appearance;
            $currentFontToUnicodeMaps = $appearance['fontToUnicodeMaps'];
        }

        return $appearances;
    }

    private function annotationAppearanceContributesText(string $annotationBody): bool
    {
        $subtype = $this->pdfNameValueAfterName($annotationBody, 'Subtype');
        if ($subtype === null) {
            return false;
        }

        return !in_array($subtype, ['3D', 'FileAttachment', 'Movie', 'RichMedia', 'Screen', 'Sound'], true);
    }

    /**
     * @return list<array{body: string, object: int|null}>
     * @param array<int, string> $objects
     */
    private function annotationBodiesForPage(string $pageBody, array $objects): array
    {
        $annots = $this->pdfValueAfterName($pageBody, 'Annots');
        if ($annots === null) {
            return [];
        }

        return $this->annotationBodiesFromValue($annots, $objects);
    }

    /**
     * @return list<array{body: string, object: int|null}>
     * @param array<int, string> $objects
     */
    private function annotationBodiesFromValue(string $value, array $objects): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1) {
            $objectNumber = (int) $match[1];
            if (!isset($objects[$objectNumber])) {
                return [];
            }

            $objectBody = trim($objects[$objectNumber]);
            if (str_starts_with($objectBody, '[')) {
                return $this->annotationBodiesFromArray($this->pdfArrayAtStart($objectBody), $objects);
            }

            $dictionary = $this->dictionaryObjectBody($objectBody);
            return $dictionary === null ? [] : [['body' => $dictionary, 'object' => $objectNumber]];
        }

        if (str_starts_with($value, '[')) {
            return $this->annotationBodiesFromArray($this->pdfArrayAtStart($value), $objects);
        }

        if (str_starts_with($value, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($value, 0);
            return $dictionary === null ? [] : [['body' => $dictionary, 'object' => null]];
        }

        return [];
    }

    /**
     * @return list<array{body: string, object: int|null}>
     * @param array<int, string> $objects
     */
    private function annotationBodiesFromArray(?string $arrayBody, array $objects): array
    {
        if ($arrayBody === null) {
            return [];
        }

        $annotations = [];
        foreach ($this->objectReferences($arrayBody) as $objectNumber) {
            if (!isset($objects[$objectNumber])) {
                continue;
            }

            $dictionary = $this->dictionaryObjectBody($objects[$objectNumber]);
            if ($dictionary !== null) {
                $annotations[] = ['body' => $dictionary, 'object' => $objectNumber];
            }
        }

        foreach ($this->directDictionaries($arrayBody) as $dictionary) {
            $annotations[] = ['body' => $dictionary, 'object' => null];
        }

        return $annotations;
    }

    /**
     * @return list<string>
     */
    private function directDictionaries(string $value): array
    {
        $dictionaries = [];
        $offset = 0;
        while (($start = strpos($value, '<<', $offset)) !== false) {
            $dictionary = $this->readPdfDictionaryAt($value, $start);
            $end = $this->pdfDictionaryEndOffset($value, $start);
            if ($dictionary === null || $end === null) {
                break;
            }

            $dictionaries[] = $dictionary;
            $offset = $end + 1;
        }

        return $dictionaries;
    }

    /**
     * @param array<int, string> $objects
     */
    private function normalAppearanceObjectNumber(string $annotationBody, array $objects): ?int
    {
        $appearanceDictionary = $this->appearanceDictionaryBody($annotationBody, $objects);
        if ($appearanceDictionary === null) {
            return null;
        }

        $normalAppearance = $this->pdfValueAfterName($appearanceDictionary, 'N');
        if ($normalAppearance === null) {
            return null;
        }

        $appearanceState = $this->pdfNameValueAfterName($annotationBody, 'AS');
        $normalAppearance = trim($normalAppearance);
        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $normalAppearance, $match) === 1) {
            $objectNumber = (int) $match[1];
            if (!isset($objects[$objectNumber])) {
                return null;
            }

            if ($this->isStreamObject($objects[$objectNumber])) {
                return $objectNumber;
            }

            $dictionary = $this->dictionaryObjectBody($objects[$objectNumber]);
            return $dictionary === null ? null : $this->appearanceObjectNumberFromStateDictionary($dictionary, $appearanceState);
        }

        if (str_starts_with($normalAppearance, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($normalAppearance, 0);
            return $dictionary === null ? null : $this->appearanceObjectNumberFromStateDictionary($dictionary, $appearanceState);
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function appearanceDictionaryBody(string $annotationBody, array $objects): ?string
    {
        $appearance = $this->pdfValueAfterName($annotationBody, 'AP');
        if ($appearance === null) {
            return null;
        }

        $appearance = trim($appearance);
        if (str_starts_with($appearance, '<<')) {
            return $this->readPdfDictionaryAt($appearance, 0);
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $appearance, $match) === 1) {
            $objectNumber = (int) $match[1];
            return isset($objects[$objectNumber]) ? $this->dictionaryObjectBody($objects[$objectNumber]) : null;
        }

        return null;
    }

    private function appearanceObjectNumberFromStateDictionary(string $dictionary, ?string $appearanceState): ?int
    {
        if ($appearanceState !== null) {
            return $this->objectReferenceValueAfterName($dictionary, $appearanceState);
        }

        $fallback = null;
        if (!preg_match_all('/\/([^\s\[\]()<>{}\/%]+)\s+(\d+)\s+\d+\s+R\b/', $dictionary, $matches, PREG_SET_ORDER)) {
            return null;
        }

        foreach ($matches as $match) {
            $state = $this->decodePdfName($match[1]);
            $objectNumber = (int) $match[2];
            if ($fallback === null) {
                $fallback = $objectNumber;
            }
            if ($state !== 'Off') {
                return $objectNumber;
            }
        }

        return $fallback;
    }

    /**
     * @return array{stream: string, fontToUnicodeMaps: array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}>}|null
     * @param array<int, string> $objects
     * @param array<int, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontObjectMaps
     * @param array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontToUnicodeMaps
     * @param array<int, bool> $optionalContentStates
     */
    private function decodedAppearanceStreamWithFontMaps(
        int $appearanceObjectNumber,
        array $objects,
        array $fontObjectMaps,
        array $fontToUnicodeMaps,
        array $optionalContentStates = []
    ): ?array {
        if (!isset($objects[$appearanceObjectNumber]) || preg_match('/\/Subtype\s*\/Form\b/', $objects[$appearanceObjectNumber]) !== 1) {
            return null;
        }

        if (!$this->optionalContentObjectVisible($objects[$appearanceObjectNumber], $objects, $optionalContentStates)) {
            return null;
        }

        $decoded = $this->decodeStreamObject($objects[$appearanceObjectNumber], $objects);
        if ($decoded === null) {
            return null;
        }

        $expandedFontToUnicodeMaps = $fontToUnicodeMaps;
        $fontAliases = [];
        foreach ($this->fontResourceMapsForResourceOwnerBody($objects[$appearanceObjectNumber], $objects, $fontObjectMaps) as $name => $map) {
            $alias = $this->appearanceFontResourceAlias($appearanceObjectNumber, $name);
            $fontAliases[$name] = $alias;
            $expandedFontToUnicodeMaps[$alias] = $map;
        }

        $decoded = $this->filterOptionalContentMarkedBlocks(
            $decoded,
            $this->optionalContentPropertyVisibilityMapForResourceOwnerBody(
                $objects[$appearanceObjectNumber],
                $objects,
                $optionalContentStates
            )
        );

        return $this->expandFormXObjectInvocations(
            $this->rewriteFontResourceOperands($decoded, $fontAliases),
            $objects[$appearanceObjectNumber],
            $objects,
            $fontObjectMaps,
            $expandedFontToUnicodeMaps,
            $optionalContentStates,
            [],
            $this->pdfMatrixValueAfterName($objects[$appearanceObjectNumber], 'Matrix', $objects),
            $this->pdfRectangleValueAfterName($objects[$appearanceObjectNumber], 'BBox', $objects)
        );
    }

    /**
     * Native boundary for PDFium-style default-view optional content checks.
     *
     * @return array<int, bool>
     * @param array<int, string> $objects
     */
    private function optionalContentVisibilityStates(array $objects): array
    {
        $ocProperties = $this->optionalContentPropertiesDictionaryBody($objects);
        if ($ocProperties === null) {
            return [];
        }

        $ocgArray = $this->pdfArrayValueAfterNameResolved($ocProperties, 'OCGs', $objects);
        if ($ocgArray === null) {
            return [];
        }

        $states = [];
        foreach ($this->objectReferences($ocgArray) as $objectNumber) {
            $states[$objectNumber] = true;
        }

        if ($states === []) {
            return [];
        }

        $defaultConfig = $this->pdfDictionaryValueAfterNameResolved($ocProperties, 'D', $objects);
        if ($defaultConfig === null) {
            return $states;
        }

        $configIntents = $this->optionalContentIntentNames($defaultConfig, $objects, ['View']);
        $intentMatches = [];
        foreach (array_keys($states) as $objectNumber) {
            $intentMatches[$objectNumber] = $this->optionalContentReferenceMatchesIntent(
                $objectNumber,
                $objects,
                $configIntents
            );
        }

        $baseState = $this->pdfNameValueAfterName($defaultConfig, 'BaseState') ?? 'ON';
        $baseVisible = $baseState !== 'OFF';
        foreach (array_keys($states) as $objectNumber) {
            $states[$objectNumber] = $baseVisible && ($intentMatches[$objectNumber] ?? true);
        }

        foreach ($this->optionalContentObjectNumbersAfterName($defaultConfig, 'ON', $objects) as $objectNumber) {
            if (array_key_exists($objectNumber, $states) && ($intentMatches[$objectNumber] ?? true)) {
                $states[$objectNumber] = true;
            }
        }

        foreach ($this->optionalContentObjectNumbersAfterName($defaultConfig, 'OFF', $objects) as $objectNumber) {
            if (array_key_exists($objectNumber, $states)) {
                $states[$objectNumber] = false;
            }
        }

        $states = $this->optionalContentUsageApplicationStates($defaultConfig, $objects, $states, $configIntents);
        foreach ($intentMatches as $objectNumber => $matches) {
            if (!$matches) {
                $states[$objectNumber] = false;
            }
        }

        return $states;
    }

    /**
     * @param array<int, string> $objects
     */
    private function optionalContentPropertiesDictionaryBody(array $objects): ?string
    {
        $catalog = $this->catalogObjectBody($objects);
        if ($catalog === null) {
            return null;
        }

        $value = $this->pdfValueAfterName($catalog, 'OCProperties');
        return $value === null ? null : $this->pdfDictionaryFromValue($value, $objects);
    }

    /**
     * @return list<int>
     * @param array<int, string> $objects
     */
    private function optionalContentObjectNumbersAfterName(string $dictionary, string $name, array $objects): array
    {
        $arrayBody = $this->pdfArrayValueAfterNameResolved($dictionary, $name, $objects);
        return $arrayBody === null ? [] : $this->objectReferences($arrayBody);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, bool> $states
     * @param list<string> $configIntents
     * @return array<int, bool>
     */
    private function optionalContentUsageApplicationStates(
        string $defaultConfig,
        array $objects,
        array $states,
        array $configIntents
    ): array {
        $applicationArray = $this->pdfArrayValueAfterNameResolved($defaultConfig, 'AS', $objects);
        if ($applicationArray === null) {
            return $states;
        }

        foreach ($this->pdfArrayItems($applicationArray) as $applicationValue) {
            $application = $this->pdfDictionaryFromValue($applicationValue, $objects);
            if ($application === null) {
                continue;
            }

            $event = $this->pdfNameValueAfterName($application, 'Event');
            if ($event !== null && $event !== 'View') {
                continue;
            }

            $categories = $this->optionalContentNameListValueAfterName($application, 'Category', $objects, []);
            if ($categories === []) {
                continue;
            }

            $ocgValue = $this->pdfValueAfterName($application, 'OCGs');
            if ($ocgValue === null) {
                continue;
            }

            foreach ($this->optionalContentObjectNumbersFromValue($ocgValue, $objects) as $objectNumber) {
                if (!array_key_exists($objectNumber, $states) || !isset($objects[$objectNumber])) {
                    continue;
                }

                $dictionary = $this->dictionaryObjectBody($objects[$objectNumber]);
                if (
                    $dictionary === null
                    || !$this->optionalContentDictionaryMatchesIntent($dictionary, $objects, $configIntents)
                ) {
                    continue;
                }

                $usageState = $this->optionalContentUsageStateForCategories($dictionary, $categories, $objects);
                if ($usageState !== null) {
                    $states[$objectNumber] = $usageState;
                }
            }
        }

        return $states;
    }

    /**
     * @return list<int>
     * @param array<int, string> $objects
     */
    private function optionalContentObjectNumbersFromValue(string $value, array $objects): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/s', $value, $match) === 1) {
            $objectNumber = (int) $match[1];
            $arrayBody = $this->pdfArrayFromValue($value, $objects);
            return $arrayBody === null ? [$objectNumber] : $this->objectReferences($arrayBody);
        }

        $arrayBody = $this->pdfArrayFromValue($value, $objects);
        return $arrayBody === null ? [] : $this->objectReferences($arrayBody);
    }

    /**
     * @param array<int, string> $objects
     * @param list<string> $configIntents
     */
    private function optionalContentReferenceMatchesIntent(int $objectNumber, array $objects, array $configIntents): bool
    {
        if (!isset($objects[$objectNumber])) {
            return true;
        }

        $dictionary = $this->dictionaryObjectBody($objects[$objectNumber]);
        return $dictionary === null
            || $this->optionalContentDictionaryMatchesIntent($dictionary, $objects, $configIntents);
    }

    /**
     * @param array<int, string> $objects
     * @param list<string> $configIntents
     */
    private function optionalContentDictionaryMatchesIntent(string $dictionary, array $objects, array $configIntents): bool
    {
        $type = $this->pdfNameValueAfterName($dictionary, 'Type') ?? 'OCG';
        if ($type !== 'OCG') {
            return true;
        }

        $groupIntents = $this->optionalContentIntentNames($dictionary, $objects, ['View']);
        return $this->optionalContentIntentsIntersect($configIntents, $groupIntents);
    }

    /**
     * @return list<string>
     * @param array<int, string> $objects
     * @param list<string> $default
     */
    private function optionalContentIntentNames(string $dictionary, array $objects, array $default): array
    {
        return $this->optionalContentNameListValueAfterName($dictionary, 'Intent', $objects, $default);
    }

    /**
     * @return list<string>
     * @param array<int, string> $objects
     * @param list<string> $default
     */
    private function optionalContentNameListValueAfterName(
        string $dictionary,
        string $name,
        array $objects,
        array $default
    ): array {
        $value = $this->pdfValueAfterName($dictionary, $name);
        if ($value === null) {
            return $default;
        }

        $names = $this->optionalContentNameListFromValue($value, $objects);
        return $names === [] ? $default : $names;
    }

    /**
     * @return list<string>
     * @param array<int, string> $objects
     */
    private function optionalContentNameListFromValue(string $value, array $objects): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        if (str_starts_with($value, '/')) {
            return [$this->decodePdfName(substr($value, 1))];
        }

        $arrayBody = $this->pdfArrayFromValue($value, $objects);
        if ($arrayBody === null) {
            return [];
        }

        $names = [];
        foreach ($this->pdfArrayItems($arrayBody) as $item) {
            $item = trim($item);
            if (str_starts_with($item, '/')) {
                $names[] = $this->decodePdfName(substr($item, 1));
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param list<string> $configIntents
     * @param list<string> $groupIntents
     */
    private function optionalContentIntentsIntersect(array $configIntents, array $groupIntents): bool
    {
        if (in_array('All', $configIntents, true) || in_array('All', $groupIntents, true)) {
            return true;
        }

        foreach ($groupIntents as $intent) {
            if (in_array($intent, $configIntents, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $categories
     * @param array<int, string> $objects
     */
    private function optionalContentUsageStateForCategories(
        string $dictionary,
        array $categories,
        array $objects
    ): ?bool {
        $usage = $this->pdfDictionaryValueAfterNameResolved($dictionary, 'Usage', $objects);
        if ($usage === null) {
            return null;
        }

        foreach ($categories as $category) {
            $categoryUsage = $this->pdfDictionaryValueAfterNameResolved($usage, $category, $objects);
            if ($categoryUsage === null) {
                continue;
            }

            $stateName = match ($category) {
                'Print' => 'PrintState',
                'Export' => 'ExportState',
                default => $category . 'State',
            };
            $state = $this->pdfNameValueAfterName($categoryUsage, $stateName);
            if ($state === 'ON') {
                return true;
            }
            if ($state === 'OFF') {
                return false;
            }
        }

        return null;
    }

    /**
     * @return array<string, bool>
     * @param array<int, string> $objects
     * @param array<int, bool> $optionalContentStates
     */
    private function pageOptionalContentPropertyVisibilityMap(
        int $pageObjectNumber,
        array $objects,
        array $optionalContentStates
    ): array {
        $resourceDictionary = $this->pageResourceDictionaryBody($pageObjectNumber, $objects);
        if ($resourceDictionary === null) {
            return [];
        }

        return $this->optionalContentPropertyVisibilityMapForResourceOwnerBody(
            $resourceDictionary,
            $objects,
            $optionalContentStates
        );
    }

    /**
     * @return array<string, bool>
     * @param array<int, string> $objects
     * @param array<int, bool> $optionalContentStates
     */
    private function optionalContentPropertyVisibilityMapForResourceOwnerBody(
        string $resourceOwnerBody,
        array $objects,
        array $optionalContentStates
    ): array {
        $resourceDictionary = $this->resourceDictionaryBody($resourceOwnerBody, $objects) ?? $resourceOwnerBody;
        $propertiesDictionary = $this->propertiesResourceDictionaryBody($resourceDictionary, $objects);
        if ($propertiesDictionary === null) {
            return [];
        }

        $properties = [];
        $offset = 0;
        while (preg_match('/\/([^\s\[\]()<>{}\/%]+)/s', $propertiesDictionary, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $name = $this->decodePdfName($match[1][0]);
            $valueOffset = $this->skipPdfWhitespace($propertiesDictionary, $match[0][1] + strlen($match[0][0]));
            if ($valueOffset >= strlen($propertiesDictionary)) {
                break;
            }

            if (preg_match('/\G(\d+)\s+\d+\s+R\b/s', $propertiesDictionary, $referenceMatch, 0, $valueOffset) === 1) {
                $properties[$name] = $this->optionalContentReferenceVisible(
                    (int) $referenceMatch[1],
                    $objects,
                    $optionalContentStates
                );
                $offset = $valueOffset + strlen($referenceMatch[0]);
                continue;
            }

            if (substr($propertiesDictionary, $valueOffset, 2) === '<<') {
                $dictionaryOffset = $valueOffset;
                $dictionary = $this->readPdfDictionaryTokenAt($propertiesDictionary, $dictionaryOffset);
                if ($dictionary !== null) {
                    $properties[$name] = $this->optionalContentDictionaryVisible(
                        $dictionary,
                        $objects,
                        $optionalContentStates
                    );
                    $offset = $dictionaryOffset;
                    continue;
                }
            }

            $offset = $valueOffset + 1;
        }

        return $properties;
    }

    /**
     * @param array<string, bool> $propertyVisibility
     */
    private function filterOptionalContentMarkedBlocks(string $content, array $propertyVisibility): string
    {
        if ($propertyVisibility === [] || !str_contains($content, 'BDC')) {
            return $content;
        }

        $filtered = [];
        $operands = [];
        $hiddenDepth = 0;

        foreach ($this->contentTokens($content) as $token) {
            if ($token === 'BDC') {
                $hidden = $hiddenDepth > 0 || $this->markedOptionalContentIsHidden($operands, $propertyVisibility);
                if ($hidden) {
                    $hiddenDepth++;
                    $operands = [];
                    continue;
                }

                foreach ($operands as $operand) {
                    $filtered[] = $operand;
                }
                $filtered[] = $token;
                $operands = [];
                continue;
            }

            if ($token === 'BMC') {
                if ($hiddenDepth > 0) {
                    $hiddenDepth++;
                    $operands = [];
                    continue;
                }

                foreach ($operands as $operand) {
                    $filtered[] = $operand;
                }
                $filtered[] = $token;
                $operands = [];
                continue;
            }

            if ($token === 'EMC') {
                if ($hiddenDepth > 0) {
                    $hiddenDepth--;
                    $operands = [];
                    continue;
                }

                foreach ($operands as $operand) {
                    $filtered[] = $operand;
                }
                $filtered[] = $token;
                $operands = [];
                continue;
            }

            if ($hiddenDepth > 0) {
                if ($this->isOperator($token)) {
                    $operands = [];
                    continue;
                }

                $operands[] = $token;
                continue;
            }

            if ($this->isOperator($token)) {
                foreach ($operands as $operand) {
                    $filtered[] = $operand;
                }
                $filtered[] = $token;
                $operands = [];
                continue;
            }

            $operands[] = $token;
        }

        if ($hiddenDepth === 0) {
            foreach ($operands as $operand) {
                $filtered[] = $operand;
            }
        }

        return implode(' ', $filtered);
    }

    /**
     * @param list<string> $operands
     * @param array<string, bool> $propertyVisibility
     */
    private function markedOptionalContentIsHidden(array $operands, array $propertyVisibility): bool
    {
        if (count($operands) < 2) {
            return false;
        }

        $tagOperand = $operands[count($operands) - 2];
        $propertyOperand = $operands[count($operands) - 1];
        if (!str_starts_with($tagOperand, '/') || $this->decodePdfName(substr($tagOperand, 1)) !== 'OC') {
            return false;
        }

        if (!str_starts_with($propertyOperand, '/')) {
            return false;
        }

        $propertyName = $this->decodePdfName(substr($propertyOperand, 1));
        return isset($propertyVisibility[$propertyName]) && !$propertyVisibility[$propertyName];
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, bool> $optionalContentStates
     */
    private function optionalContentObjectVisible(string $objectBody, array $objects, array $optionalContentStates): bool
    {
        $trimmed = trim($objectBody);
        $dictionary = str_starts_with($trimmed, '<<')
            ? ($this->dictionaryObjectBody($objectBody) ?? $objectBody)
            : $objectBody;
        $optionalContent = $this->pdfValueAfterName($dictionary, 'OC');
        return $optionalContent === null
            || $this->optionalContentValueVisible($optionalContent, $objects, $optionalContentStates);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, bool> $optionalContentStates
     */
    private function optionalContentValueVisible(string $value, array $objects, array $optionalContentStates): bool
    {
        $value = trim($value);
        if ($value === '') {
            return true;
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/s', $value, $match) === 1) {
            return $this->optionalContentReferenceVisible((int) $match[1], $objects, $optionalContentStates);
        }

        if (str_starts_with($value, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($value, 0);
            return $dictionary === null
                || $this->optionalContentDictionaryVisible($dictionary, $objects, $optionalContentStates);
        }

        if (str_starts_with($value, '[')) {
            $values = $this->optionalContentVisibilityValuesFromValue($value, $objects, $optionalContentStates);
            return $values === [] || in_array(true, $values, true);
        }

        return true;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, bool> $optionalContentStates
     */
    private function optionalContentReferenceVisible(int $objectNumber, array $objects, array $optionalContentStates): bool
    {
        if (!isset($objects[$objectNumber])) {
            return true;
        }

        $dictionary = $this->dictionaryObjectBody($objects[$objectNumber]);
        if ($dictionary === null) {
            return true;
        }

        return $this->optionalContentDictionaryVisible($dictionary, $objects, $optionalContentStates, $objectNumber);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, bool> $optionalContentStates
     */
    private function optionalContentDictionaryVisible(
        string $dictionary,
        array $objects,
        array $optionalContentStates,
        ?int $objectNumber = null
    ): bool {
        $type = $this->pdfNameValueAfterName($dictionary, 'Type') ?? 'OCG';
        if ($type === 'OCMD') {
            return $this->optionalContentMembershipVisible($dictionary, $objects, $optionalContentStates);
        }

        if ($type !== 'OCG') {
            return true;
        }

        if ($objectNumber !== null && array_key_exists($objectNumber, $optionalContentStates)) {
            return $optionalContentStates[$objectNumber];
        }

        if (!$this->optionalContentDictionaryMatchesIntent($dictionary, $objects, ['View'])) {
            return false;
        }

        return $this->optionalContentUsageStateForCategories($dictionary, ['View'], $objects) ?? true;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, bool> $optionalContentStates
     */
    private function optionalContentMembershipVisible(
        string $dictionary,
        array $objects,
        array $optionalContentStates
    ): bool {
        $ocgs = $this->pdfValueAfterName($dictionary, 'OCGs');
        if ($ocgs === null) {
            return true;
        }

        $values = $this->optionalContentVisibilityValuesFromValue($ocgs, $objects, $optionalContentStates);
        if ($values === []) {
            return true;
        }

        $policy = $this->pdfNameValueAfterName($dictionary, 'P') ?? 'AnyOn';
        return match ($policy) {
            'AllOn' => !in_array(false, $values, true),
            'AnyOff' => in_array(false, $values, true),
            'AllOff' => !in_array(true, $values, true),
            default => in_array(true, $values, true),
        };
    }

    /**
     * @return list<bool>
     * @param array<int, string> $objects
     * @param array<int, bool> $optionalContentStates
     */
    private function optionalContentVisibilityValuesFromValue(
        string $value,
        array $objects,
        array $optionalContentStates
    ): array {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/s', $value, $match) === 1) {
            return [$this->optionalContentReferenceVisible((int) $match[1], $objects, $optionalContentStates)];
        }

        if (str_starts_with($value, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($value, 0);
            return $dictionary === null ? [] : [$this->optionalContentDictionaryVisible($dictionary, $objects, $optionalContentStates)];
        }

        $arrayBody = $this->pdfArrayFromValue($value, $objects);
        if ($arrayBody === null) {
            return [];
        }

        $values = [];
        foreach ($this->objectReferences($arrayBody) as $objectNumber) {
            $values[] = $this->optionalContentReferenceVisible($objectNumber, $objects, $optionalContentStates);
        }

        return $values;
    }

    /**
     * @param array<int, string> $objects
     */
    private function pdfArrayValueAfterNameResolved(string $body, string $name, array $objects): ?string
    {
        $value = $this->pdfValueAfterName($body, $name);
        return $value === null ? null : $this->pdfArrayFromValue($value, $objects);
    }

    /**
     * @param array<int, string> $objects
     */
    private function pdfDictionaryValueAfterNameResolved(string $body, string $name, array $objects): ?string
    {
        $value = $this->pdfValueAfterName($body, $name);
        return $value === null ? null : $this->pdfDictionaryFromValue($value, $objects);
    }

    /**
     * @param array<int, string> $objects
     */
    private function pdfArrayFromValue(string $value, array $objects): ?string
    {
        $offset = $this->skipPdfWhitespace($value, 0);
        if (($value[$offset] ?? '') === '[') {
            return $this->readPdfArrayAt($value, $offset);
        }

        if (preg_match('/\G(\d+)\s+\d+\s+R\b/s', $value, $match, 0, $offset) !== 1) {
            return null;
        }

        $objectNumber = (int) $match[1];
        return isset($objects[$objectNumber]) ? $this->pdfArrayAtStart(trim($objects[$objectNumber])) : null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function pdfDictionaryFromValue(string $value, array $objects): ?string
    {
        $offset = $this->skipPdfWhitespace($value, 0);
        if (substr($value, $offset, 2) === '<<') {
            return $this->readPdfDictionaryAt($value, $offset);
        }

        if (preg_match('/\G(\d+)\s+\d+\s+R\b/s', $value, $match, 0, $offset) !== 1) {
            return null;
        }

        $objectNumber = (int) $match[1];
        return isset($objects[$objectNumber]) ? $this->dictionaryObjectBody($objects[$objectNumber]) : null;
    }

    private function isStreamObject(string $objectBody): bool
    {
        return preg_match('/\bstream\r?\n?/s', $objectBody) === 1;
    }

    /**
     * @param list<string> $operands
     */
    private function xObjectNameOperand(array $operands): ?string
    {
        $operand = end($operands);
        if (!is_string($operand) || !str_starts_with($operand, '/')) {
            return null;
        }

        return $this->decodePdfName(substr($operand, 1));
    }

    /**
     * @return list<string>
     * @param array<int, string> $objects
     */
    private function allDecodedStreams(string $pdfBytes, array $objects): array
    {
        $streams = [];
        $definitions = $this->directObjectDefinitions($pdfBytes);
        if ($definitions === []) {
            return $streams;
        }

        $preliminaryObjects = $this->latestDirectObjects($definitions);
        if ($this->startxrefXrefStreamFilterDecodeFailed($pdfBytes, $preliminaryObjects, $definitions)) {
            return $streams;
        }

        $xrefEntries = $this->xrefEntries($pdfBytes, $preliminaryObjects, $definitions);
        $linearizedHintRanges = $this->linearizedHintTableRanges($pdfBytes);
        $linearizedHintObjectNumbers = array_fill_keys(
            $this->linearizedHintTableObjectNumbers($pdfBytes, $definitions, $linearizedHintRanges, $objects),
            true
        );
        $embeddedFilePayloadObjectNumbers = $this->embeddedFilePayloadObjectNumbers($objects);
        $pieceInfoPrivateObjectNumbers = $this->pieceInfoPrivateStreamObjectNumbers($objects);
        $type3CharProcObjectGenerations = $this->type3CharProcObjectGenerationSet($objects);
        foreach ($this->liveDirectObjectDefinitionsInFileOrder($definitions, $xrefEntries) as $definition) {
            $streamObjectNumber = $definition['objectNumber'];
            if (
                isset($linearizedHintObjectNumbers[$streamObjectNumber])
                || !isset($objects[$streamObjectNumber])
                || $objects[$streamObjectNumber] !== $definition['body']
                || isset($embeddedFilePayloadObjectNumbers[$streamObjectNumber])
                || isset($pieceInfoPrivateObjectNumbers[$streamObjectNumber])
                || isset($type3CharProcObjectGenerations[$streamObjectNumber][$definition['generation']])
            ) {
                continue;
            }

            $entry = $this->streamDictionaryAndPayload($definition['body'], $objects);
            if ($entry === null) {
                continue;
            }

            if (
                $this->isObjectStreamDictionary($entry['dict'], $objects)
                || $this->isXrefStreamDictionary($entry['dict'], $objects)
                || $this->isImageStreamDictionary($entry['dict'], $objects)
                || $this->isEmbeddedFileStreamDictionary($entry['dict'])
            ) {
                continue;
            }

            $decoded = $this->decodeStream($entry['dict'], $entry['stream'], $objects);
            if ($decoded === null) {
                continue;
            }
            $streams[] = $decoded;
        }

        return $streams;
    }

    /**
     * @return array<int, array<int, true>>
     * @param array<int, string> $objects
     */
    private function type3CharProcObjectGenerationSet(array $objects): array
    {
        $references = [];
        foreach ($objects as $body) {
            if (!$this->isType3FontBody($body)) {
                continue;
            }

            foreach ($this->charProcObjectReferences($body, $objects) as $reference) {
                $references[$reference['objectNumber']][$reference['generation']] = true;
            }
        }

        return $references;
    }

    /**
     * @param array<int, string> $objects
     * @return array<int, true>
     */
    private function embeddedFilePayloadObjectNumbers(array $objects): array
    {
        $payloadObjectNumbers = [];
        foreach ($objects as $body) {
            foreach ($this->embeddedFileDictionariesFromBody($body, $objects) as $efDictionary) {
                foreach (['F', 'UF', 'DOS', 'Unix', 'Mac'] as $key) {
                    $value = $this->pdfValueAfterName($efDictionary, $key);
                    if ($value === null) {
                        continue;
                    }

                    foreach ($this->objectReferences($value) as $objectNumber) {
                        $payloadObjectNumbers[$objectNumber] = true;
                    }
                }
            }
        }

        return $payloadObjectNumbers;
    }

    /**
     * @param array<int, string> $objects
     * @return array<int, true>
     */
    private function pieceInfoPrivateStreamObjectNumbers(array $objects): array
    {
        $streamObjectNumbers = [];
        foreach ($objects as $body) {
            foreach ($this->pieceInfoDictionariesFromBody($body, $objects) as $pieceInfoDictionary) {
                foreach ($this->pieceInfoPrivateObjectNumbersFromDictionary($pieceInfoDictionary, $objects) as $privateObjectNumber) {
                    if (!isset($objects[$privateObjectNumber])) {
                        continue;
                    }

                    if ($this->streamDictionaryAndPayload($objects[$privateObjectNumber], $objects) !== null) {
                        $streamObjectNumbers[$privateObjectNumber] = true;
                    }
                }
            }
        }

        return $streamObjectNumbers;
    }

    /**
     * @param array<int, string> $objects
     * @return list<int>
     */
    private function pieceInfoPrivateObjectNumbersFromDictionary(string $pieceInfoDictionary, array $objects): array
    {
        $objectNumbers = $this->directPrivateObjectNumbers($pieceInfoDictionary);
        foreach ($this->pieceInfoApplicationDictionaries($pieceInfoDictionary, $objects) as $applicationDictionary) {
            foreach ($this->directPrivateObjectNumbers($applicationDictionary) as $objectNumber) {
                $objectNumbers[] = $objectNumber;
            }
        }

        return array_values(array_unique($objectNumbers));
    }

    /**
     * @return list<int>
     */
    private function directPrivateObjectNumbers(string $dictionary): array
    {
        $matchCount = preg_match_all('/\/Private\s+(\d+)\s+\d+\s+R\b/s', $dictionary, $matches);
        if ($matchCount === false || $matchCount === 0) {
            return [];
        }

        return array_map('intval', $matches[1]);
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function pieceInfoApplicationDictionaries(string $pieceInfoDictionary, array $objects): array
    {
        $dictionaries = [];
        for ($offset = 0, $length = strlen($pieceInfoDictionary); $offset < $length;) {
            $offset = $this->skipPdfWhitespace($pieceInfoDictionary, $offset);
            if ($offset >= $length) {
                break;
            }

            if (($pieceInfoDictionary[$offset] ?? '') !== '/') {
                $offset++;
                continue;
            }

            if (preg_match('/\G\/[^\s\[\]()<>{}\/%]+/s', $pieceInfoDictionary, $nameMatch, 0, $offset) !== 1) {
                $offset++;
                continue;
            }

            $valueOffset = $this->skipPdfWhitespace($pieceInfoDictionary, $offset + strlen($nameMatch[0]));
            $value = $this->pdfValueAtOffset($pieceInfoDictionary, $valueOffset);
            if ($value === null) {
                $offset += strlen($nameMatch[0]);
                continue;
            }

            $applicationDictionary = $this->pdfDictionaryFromValue($value, $objects);
            if ($applicationDictionary !== null) {
                $dictionaries[] = $applicationDictionary;
            }

            $offset = max($valueOffset + strlen($value), $offset + strlen($nameMatch[0]));
        }

        return $dictionaries;
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function pieceInfoDictionariesFromBody(string $body, array $objects): array
    {
        $dictionaries = [];
        $offset = 0;
        while (preg_match('/\/PieceInfo\b/s', $body, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $valueOffset = $this->skipPdfWhitespace($body, $match[0][1] + strlen($match[0][0]));
            $value = $this->pdfValueAtOffset($body, $valueOffset);
            if ($value !== null) {
                $dictionary = $this->pdfDictionaryFromValue($value, $objects);
                if ($dictionary !== null) {
                    $dictionaries[] = $dictionary;
                }
            }

            $offset = max($valueOffset + 1, $match[0][1] + strlen($match[0][0]));
        }

        return $dictionaries;
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function embeddedFileDictionariesFromBody(string $body, array $objects): array
    {
        $dictionaries = [];
        $offset = 0;
        while (preg_match('/\/(?:EF|RF)\b/s', $body, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $valueOffset = $this->skipPdfWhitespace($body, $match[0][1] + strlen($match[0][0]));
            $value = $this->pdfValueAtOffset($body, $valueOffset);
            if ($value !== null) {
                $dictionary = $this->pdfDictionaryFromValue($value, $objects);
                if ($dictionary !== null) {
                    $dictionaries[] = $dictionary;
                }
            }

            $offset = max($valueOffset + 1, $match[0][1] + strlen($match[0][0]));
        }

        return $dictionaries;
    }

    private function pdfValueAtOffset(string $body, int $offset): ?string
    {
        $offset = $this->skipPdfWhitespace($body, $offset);
        if ($offset >= strlen($body)) {
            return null;
        }

        if ($body[$offset] === '[') {
            $array = $this->readPdfArrayAt($body, $offset);
            return $array === null ? null : '[' . $array . ']';
        }

        if (substr($body, $offset, 2) === '<<') {
            $end = $this->pdfDictionaryEndOffset($body, $offset);
            return $end === null ? null : substr($body, $offset, $end - $offset + 1);
        }

        if ($body[$offset] === '(') {
            $end = $this->skipPdfLiteralStringAt($body, $offset);
            return $end === null ? null : substr($body, $offset, $end - $offset + 1);
        }

        if ($body[$offset] === '<') {
            $end = strpos($body, '>', $offset + 1);
            return $end === false ? null : substr($body, $offset, $end - $offset + 1);
        }

        if (preg_match('/\G\d+\s+\d+\s+R\b/s', $body, $match, 0, $offset) === 1) {
            return $match[0];
        }

        if ($body[$offset] === '/') {
            $end = $offset + 1;
            while ($end < strlen($body) && !str_contains(" \t\r\n\f[]()<>{}/%", $body[$end])) {
                $end++;
            }

            return substr($body, $offset, $end - $offset);
        }

        $end = $offset;
        while ($end < strlen($body) && !ctype_space($body[$end]) && !str_contains('[]()<>{}/%', $body[$end])) {
            $end++;
        }

        return $end === $offset ? null : substr($body, $offset, $end - $offset);
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, bodyStart: int, bodyEnd: int, body: string}>>|null $definitions
     */
    private function directObjectNumberAtOffset(string $pdfBytes, int $offset, ?array $definitions = null): ?int
    {
        $definitions ??= $this->directObjectDefinitions($pdfBytes);
        $ownerObjectNumber = null;
        $ownerOffset = -1;
        foreach ($definitions as $objectNumber => $entries) {
            foreach ($entries as $definition) {
                if (
                    $offset < $definition['bodyStart']
                    || $offset > $definition['bodyEnd']
                    || $definition['offset'] < $ownerOffset
                ) {
                    continue;
                }

                $ownerObjectNumber = $objectNumber;
                $ownerOffset = $definition['offset'];
            }
        }

        return $ownerObjectNumber;
    }

    /**
     * @param array<int, string> $objects
     */
    private function isImageStreamDictionary(string $dict, array $objects): bool
    {
        if (preg_match('/\/Subtype\s*\/Image\b/', $dict) === 1) {
            return true;
        }

        if (!$this->hasPdfNumberishName($dict, 'Width') || !$this->hasPdfNumberishName($dict, 'Height')) {
            return false;
        }

        $hasBitsPerComponent = $this->hasPdfNumberishName($dict, 'BitsPerComponent')
            || $this->hasPdfNumberishName($dict, 'BPC');
        if (!$hasBitsPerComponent && preg_match('/\/ImageMask\s+true\b/', $dict) !== 1) {
            return false;
        }

        return $this->imageColorSpaceFamily($dict, $objects) !== null
            || preg_match('/\/ImageMask\s+true\b/', $dict) === 1;
    }

    private function hasPdfNumberishName(string $dict, string $name): bool
    {
        $offset = $this->topLevelNameValueOffset($dict, $name);
        if ($offset === null) {
            return false;
        }

        return preg_match('/\G(?:[+-]?(?:\d+(?:\.\d*)?|\.\d+)|\d+\s+\d+\s+R\b)/s', $dict, $match, 0, $offset) === 1;
    }

    /**
     * @param array<int, string> $objects
     */
    private function imageColorSpaceFamily(string $dict, array $objects): ?string
    {
        foreach (['ColorSpace', 'CS'] as $name) {
            $offset = $this->topLevelNameValueOffset($dict, $name);
            if ($offset === null) {
                continue;
            }

            $family = $this->colorSpaceFamilyAt($dict, $offset, $objects);
            if ($family !== null) {
                return $family;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seenObjects
     */
    private function colorSpaceFamilyAt(string $value, int $offset, array $objects, array $seenObjects = []): ?string
    {
        $offset = $this->skipPdfWhitespace($value, $offset);
        if ($offset >= strlen($value)) {
            return null;
        }

        if (preg_match('/\G\/([^\s\[\]()<>{}\/%]+)/s', $value, $match, 0, $offset) === 1) {
            return $this->recognizedImageColorSpace($this->decodePdfName($match[1]));
        }

        if ($value[$offset] === '[') {
            $arrayBody = $this->readPdfArrayAt($value, $offset);
            if ($arrayBody === null) {
                return null;
            }

            return $this->colorSpaceFamilyAt($arrayBody, 0, $objects, $seenObjects);
        }

        if (preg_match('/\G(\d+)\s+\d+\s+R\b/s', $value, $match, 0, $offset) === 1) {
            $objectNumber = (int) $match[1];
            if (isset($seenObjects[$objectNumber]) || !isset($objects[$objectNumber])) {
                return null;
            }

            $seenObjects[$objectNumber] = true;
            return $this->colorSpaceFamilyAt(trim($objects[$objectNumber]), 0, $objects, $seenObjects);
        }

        return null;
    }

    private function recognizedImageColorSpace(string $name): ?string
    {
        return match ($name) {
            'DeviceGray', 'DeviceRGB', 'DeviceCMYK',
            'G', 'RGB', 'CMYK',
            'Indexed', 'I',
            'ICCBased' => $name,
            default => null,
        };
    }

    /**
     * @return list<int>
     * @param array<int, string> $objects
     */
    private function orderedPageObjectNumbers(array $objects): array
    {
        foreach ($objects as $objectNumber => $body) {
            if (!$this->isCatalogObject($body) || !preg_match('/\/Pages\s+(\d+)\s+\d+\s+R\b/s', $body, $match)) {
                continue;
            }

            $pages = $this->pageObjectNumbersFromTree((int) $match[1], $objects);
            if ($pages !== []) {
                return array_values(array_unique($pages, SORT_REGULAR));
            }
        }

        $pages = [];
        foreach ($objects as $objectNumber => $body) {
            if ($this->isPageObject($body)) {
                $pages[] = $objectNumber;
            }
        }

        return $pages;
    }

    /**
     * Native boundary for PDF catalog /PageLabels metadata.
     *
     * @return list<string>
     * @param array<int, string> $objects
     */
    private function pageLabels(array $objects, int $pageCount): array
    {
        if ($pageCount <= 0) {
            return [];
        }

        $fallback = [];
        for ($index = 0; $index < $pageCount; $index++) {
            $fallback[] = (string) ($index + 1);
        }

        $dictionary = $this->pageLabelsDictionaryBody($objects);
        if ($dictionary === null) {
            return $fallback;
        }

        $sections = $this->pageLabelNumberTreeEntries($dictionary, $objects, $pageCount);
        if ($sections === []) {
            return $fallback;
        }

        ksort($sections, SORT_NUMERIC);
        $labels = [];
        $activeSection = ['prefix' => '', 'style' => 'D', 'start' => 1];
        $activeIndex = 0;
        $sectionOffset = 0;
        $sectionIndexes = array_keys($sections);
        $sectionCount = count($sectionIndexes);

        for ($pageIndex = 0; $pageIndex < $pageCount; $pageIndex++) {
            while (
                $sectionOffset < $sectionCount
                && $sectionIndexes[$sectionOffset] <= $pageIndex
            ) {
                $activeIndex = (int) $sectionIndexes[$sectionOffset];
                $activeSection = $sections[$activeIndex];
                $sectionOffset++;
            }

            $number = $activeSection['start'] + ($pageIndex - $activeIndex);
            $label = $activeSection['prefix'];
            if ($activeSection['style'] !== null) {
                $label .= $this->formatPageLabelNumber($number, $activeSection['style']);
            }

            $labels[] = $label !== '' ? $label : (string) ($pageIndex + 1);
        }

        return $labels;
    }

    /**
     * @param array<int, string> $objects
     */
    private function pageLabelsDictionaryBody(array $objects): ?string
    {
        foreach ($objects as $body) {
            if (!$this->isCatalogObject($body)) {
                continue;
            }

            $value = $this->topLevelPdfValueAfterName($body, 'PageLabels');
            if ($value !== null) {
                return $this->pdfDictionaryFromValue($value, $objects);
            }
        }

        return null;
    }

    /**
     * @return array<int, array{prefix: string, style: string|null, start: int}>
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @param array{0: int, 1: int}|null $inheritedLimits
     */
    private function pageLabelNumberTreeEntries(
        string $dictionary,
        array $objects,
        int $pageCount,
        array $seen = [],
        ?array $inheritedLimits = null
    ): array
    {
        $limits = $inheritedLimits;
        $localLimits = $this->pageLabelLimits($dictionary, $objects);
        if ($localLimits !== null) {
            if ($limits !== null) {
                $lower = max($limits[0], $localLimits[0]);
                $upper = min($limits[1], $localLimits[1]);
                if ($lower > $upper) {
                    return [];
                }
                $limits = [$lower, $upper];
            } else {
                $limits = $localLimits;
            }
        }

        $entries = $this->pageLabelNumsEntries($dictionary, $objects, $limits, $pageCount);

        foreach ($this->pageLabelKidObjectNumbers($dictionary, $objects) as $kidObjectNumber) {
            if (isset($seen[$kidObjectNumber]) || !isset($objects[$kidObjectNumber])) {
                continue;
            }

            $kidDictionary = $this->dictionaryObjectBody($objects[$kidObjectNumber]);
            if ($kidDictionary === null) {
                continue;
            }

            $nextSeen = $seen;
            $nextSeen[$kidObjectNumber] = true;
            foreach ($this->pageLabelNumberTreeEntries($kidDictionary, $objects, $pageCount, $nextSeen, $limits) as $pageIndex => $section) {
                $entries[$pageIndex] = $section;
            }
        }

        return $entries;
    }

    /**
     * @return list<int>
     * @param array<int, string> $objects
     */
    private function pageLabelKidObjectNumbers(string $dictionary, array $objects): array
    {
        $arrayBody = $this->pdfArrayValueAfterNameResolved($dictionary, 'Kids', $objects);
        return $arrayBody === null ? [] : $this->objectReferences($arrayBody);
    }

    /**
     * @return array<int, array{prefix: string, style: string|null, start: int}>
     * @param array<int, string> $objects
     * @param array{0: int, 1: int}|null $limits
     */
    private function pageLabelNumsEntries(string $dictionary, array $objects, ?array $limits, int $pageCount): array
    {
        $arrayBody = $this->pdfArrayValueAfterNameResolved($dictionary, 'Nums', $objects);
        if ($arrayBody === null) {
            return [];
        }

        $entries = [];
        $index = 0;
        $length = strlen($arrayBody);
        while ($index < $length) {
            $index = $this->skipPdfWhitespace($arrayBody, $index);
            if (preg_match('/[+-]?\d+/A', substr($arrayBody, $index), $pageMatch) !== 1) {
                $index++;
                continue;
            }

            $pageIndex = (int) $pageMatch[0];
            $index += strlen($pageMatch[0]);
            $index = $this->skipPdfWhitespace($arrayBody, $index);

            $labelDictionary = $this->pageLabelValueDictionary($arrayBody, $index, $objects);
            if ($labelDictionary === null) {
                $index++;
                continue;
            }

            if (
                $pageIndex < 0
                || $pageIndex >= $pageCount
                || ($limits !== null && ($pageIndex < $limits[0] || $pageIndex > $limits[1]))
            ) {
                continue;
            }

            $entries[$pageIndex] = $this->parsePageLabelDictionary($labelDictionary, $objects);
        }

        return $entries;
    }

    /**
     * @param array<int, string> $objects
     * @return array{0: int, 1: int}|null
     */
    private function pageLabelLimits(string $dictionary, array $objects): ?array
    {
        $arrayBody = $this->pdfArrayValueAfterNameResolved($dictionary, 'Limits', $objects);
        if ($arrayBody === null) {
            return null;
        }

        preg_match_all('/[+-]?\d+/', $arrayBody, $matches);
        $items = $this->pdfArrayItems($arrayBody);
        if (count($items) >= 2) {
            $lower = $this->pageLabelLimitOperand($items[0], $objects);
            $upper = $this->pageLabelLimitOperand($items[1], $objects);
            if ($lower === null || $upper === null) {
                return null;
            }

            return $lower <= $upper ? [$lower, $upper] : null;
        }

        if (count($matches[0]) < 2) {
            return null;
        }

        $lower = (int) $matches[0][0];
        $upper = (int) $matches[0][1];
        return $lower <= $upper ? [$lower, $upper] : null;
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, true> $seen
     */
    private function pageLabelLimitOperand(string $value, array $objects, array $seen = []): ?int
    {
        $value = trim($value);
        if (preg_match('/^[+-]?\d+$/', $value) === 1) {
            return (int) $value;
        }

        if (preg_match('/^(\d+)\s+(\d+)\s+R$/', $value, $match) !== 1) {
            return null;
        }

        $objectNumber = (int) $match[1];
        $generation = (int) $match[2];
        $key = $objectNumber . ':' . $generation;
        if ($objectNumber <= 0 || isset($seen[$key])) {
            return null;
        }

        $body = $this->indirectObjectBodyForReference($objects, $objectNumber, $generation);
        if ($body === null) {
            return null;
        }

        $seen[$key] = true;
        return $this->pageLabelLimitOperand($body, $objects, $seen);
    }

    /**
     * @param array<int, string> $objects
     */
    private function pageLabelValueDictionary(string $arrayBody, int &$index, array $objects): ?string
    {
        if (substr($arrayBody, $index, 2) === '<<') {
            return $this->readPdfDictionaryTokenAt($arrayBody, $index);
        }

        if (preg_match('/(\d+)\s+(\d+)\s+R\b/A', substr($arrayBody, $index), $match) !== 1) {
            return null;
        }

        $index += strlen($match[0]);
        $objectNumber = (int) $match[1];
        $generation = (int) $match[2];
        $body = $this->pageLabelObjectBodyForReference($objects, $objectNumber, $generation);
        return $body === null ? null : $this->dictionaryObjectBody($body);
    }

    /**
     * @param array<int, string> $objects
     */
    private function pageLabelObjectBodyForReference(array $objects, int $objectNumber, int $generation): ?string
    {
        $directExactBody = $this->currentDirectObjectBodiesByGeneration[$objectNumber][$generation] ?? null;
        $owner = $this->currentObjectReferenceOwners[$objectNumber] ?? null;
        if ($owner !== null && isset($objects[$objectNumber]) && $objects[$objectNumber] === $owner['body']) {
            return $owner['generation'] === $generation ? $owner['body'] : $directExactBody;
        }

        return $directExactBody ?? $this->indirectObjectBodyForReference($objects, $objectNumber, $generation);
    }

    /**
     * @return array{prefix: string, style: string|null, start: int}
     */
    private function parsePageLabelDictionary(string $dictionary, array $objects): array
    {
        $candidate = $this->pdfNameValueAfterNameResolvingObjects($dictionary, 'S', $objects);
        $style = in_array($candidate, ['D', 'R', 'r', 'A', 'a'], true) ? $candidate : null;

        $start = 1;
        $startValue = $this->pdfIntegerValueAfterNameResolvingObjects($dictionary, 'St', $objects);
        if ($startValue !== null) {
            $start = max(1, $startValue);
        }

        return [
            'prefix' => $this->pageLabelPrefix($dictionary, $objects),
            'style' => $style,
            'start' => $start,
        ];
    }

    /**
     * @param array<int, string> $objects
     */
    private function pageLabelPrefix(string $dictionary, array $objects): string
    {
        $value = $this->pdfValueAfterName($dictionary, 'P');
        return $value === null ? '' : $this->pdfTextStringValue($value, $objects) ?? '';
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, true> $seen
     */
    private function pdfTextStringValue(string $value, array $objects, array $seen = []): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d+)\s+(\d+)\s+R\b/s', $value, $match) === 1) {
            $objectNumber = (int) $match[1];
            $generation = (int) $match[2];
            $objectKey = $objectNumber . ':' . $generation;
            if ($objectNumber <= 0 || isset($seen[$objectKey])) {
                return null;
            }

            $objectBody = $this->indirectObjectBodyForReference($objects, $objectNumber, $generation);
            if ($objectBody === null) {
                return null;
            }

            $seen[$objectKey] = true;
            return $this->pdfTextStringValue($objectBody, $objects, $seen);
        }

        if ($value[0] === '(') {
            $raw = $this->readPdfLiteralStringAt($value, 0);
            return $raw === null ? null : $this->decodePdfTextStringBytes($this->decodeLiteralString($raw));
        }

        if ($value[0] === '<' && substr($value, 0, 2) !== '<<') {
            $bytes = $this->readPdfHexStringAt($value, 0);
            return $bytes === null ? null : $this->decodePdfTextStringBytes($bytes);
        }

        if ($value[0] === '/') {
            $end = strcspn($value, " \t\r\n\f[]()<>{}/%", 1);
            return $this->decodePdfName(substr($value, 1, $end));
        }

        return null;
    }

    private function decodePdfTextStringBytes(string $bytes): string
    {
        $prefix = strtolower(bin2hex(substr($bytes, 0, 2)));
        if ($prefix === 'feff') {
            $decoded = iconv('UTF-16BE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }
        if ($prefix === 'fffe') {
            $decoded = iconv('UTF-16LE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }

        return $this->decodePdfDocEncoding($bytes);
    }

    private function decodePdfDocEncoding(string $bytes): string
    {
        $decoded = '';
        for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset++) {
            $byte = ord($bytes[$offset]);
            $codepoint = self::PDF_DOC_ENCODING_OVERRIDES[$byte] ?? $byte;
            $char = mb_chr($codepoint, 'UTF-8');
            if ($char !== false) {
                $decoded .= $char;
            }
        }

        return $decoded;
    }

    private function formatPageLabelNumber(int $number, string $style): string
    {
        return match ($style) {
            'R' => $this->romanPageLabel($number),
            'r' => strtolower($this->romanPageLabel($number)),
            'A' => $this->alphabeticPageLabel($number, false),
            'a' => $this->alphabeticPageLabel($number, true),
            default => (string) $number,
        };
    }

    private function romanPageLabel(int $number): string
    {
        if ($number <= 0) {
            return (string) $number;
        }

        $roman = '';
        foreach ([1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD', 100 => 'C', 90 => 'XC', 50 => 'L', 40 => 'XL', 10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I'] as $value => $glyph) {
            while ($number >= $value) {
                $roman .= $glyph;
                $number -= $value;
            }
        }

        return $roman;
    }

    private function alphabeticPageLabel(int $number, bool $lowercase): string
    {
        if ($number <= 0) {
            return (string) $number;
        }

        $letter = chr(ord($lowercase ? 'a' : 'A') + (($number - 1) % 26));
        return str_repeat($letter, intdiv($number - 1, 26) + 1);
    }

    /**
     * @return list<int>
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function pageObjectNumbersFromTree(int $objectNumber, array $objects, array $seen = []): array
    {
        if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
            return [];
        }

        $seen[$objectNumber] = true;
        $body = $objects[$objectNumber];
        if ($this->isPageObject($body)) {
            return [$objectNumber];
        }

        $kids = $this->pageTreeKidObjectNumbers($body, $objects);
        if ($kids === []) {
            return [];
        }

        $pages = [];
        foreach ($kids as $childObjectNumber) {
            foreach ($this->pageObjectNumbersFromTree($childObjectNumber, $objects, $seen) as $pageObjectNumber) {
                $pages[] = $pageObjectNumber;
            }
        }

        return $pages;
    }

    /**
     * @return list<int>
     * @param array<int, string> $objects
     */
    private function pageTreeKidObjectNumbers(string $body, array $objects): array
    {
        if (preg_match('/\/Kids\s*(?:(\d+)\s+(\d+)\s+R|\[)/s', $body, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return [];
        }

        if (($match[1][0] ?? '') !== '') {
            $objectNumber = (int) $match[1][0];
            $generation = (int) ($match[2][0] ?? 0);
            $objectBody = $this->indirectObjectBodyForReference($objects, $objectNumber, $generation);
            $arrayBody = $objectBody === null ? null : $this->pdfArrayAtStart(trim($objectBody));
            return $arrayBody === null ? [] : $this->pageTreeKidObjectReferences($arrayBody, $objects);
        }

        $offset = strpos($body, '[', $match[0][1]);
        $arrayBody = $offset === false ? null : $this->readPdfArrayAt($body, $offset);

        return $arrayBody === null ? [] : $this->pageTreeKidObjectReferences($arrayBody, $objects);
    }

    /**
     * @return list<int>
     * @param array<int, string> $objects
     */
    private function pageTreeKidObjectReferences(string $arrayBody, array $objects): array
    {
        $objectNumbers = [];
        foreach ($this->objectReferencePairs($arrayBody) as $reference) {
            if ($this->indirectObjectBodyForReference($objects, $reference['objectNumber'], $reference['generation']) === null) {
                continue;
            }

            $objectNumbers[] = $reference['objectNumber'];
        }

        return $objectNumbers;
    }

    private function isCatalogObject(string $body): bool
    {
        return preg_match('/\/Type\s*\/Catalog\b/', $body) === 1;
    }

    private function isPageObject(string $body): bool
    {
        return preg_match('/\/Type\s*\/Page\b/', $body) === 1;
    }

    private function isPagesObject(string $body): bool
    {
        return preg_match('/\/Type\s*\/Pages\b/', $body) === 1;
    }

    /**
     * @return list<int>
     */
    private function pageContentObjectNumbers(string $pageBody, array $objects): array
    {
        $value = $this->topLevelPdfValueAfterName($pageBody, 'Contents');
        return $value === null ? [] : $this->pageContentObjectNumbersFromValue($value, $objects, []);
    }

    /**
     * @return list<int>
     * @param array<int, string> $objects
     * @param array<int, true> $seenArrayObjects
     */
    private function pageContentObjectNumbersFromValue(string $value, array $objects, array $seenArrayObjects): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $arrayBody = $this->pdfArrayFromValue($value, $objects);
        if ($arrayBody !== null) {
            return $this->pageContentObjectNumbersFromArrayBody($arrayBody, $objects, $seenArrayObjects);
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/s', $value, $match) !== 1) {
            return [];
        }

        $objectNumber = (int) $match[1];
        if (isset($seenArrayObjects[$objectNumber])) {
            return [];
        }

        if (isset($objects[$objectNumber]) && !isset($seenArrayObjects[$objectNumber])) {
            $nestedArray = $this->pdfArrayAtStart(trim($objects[$objectNumber]));
            if ($nestedArray !== null) {
                $seenArrayObjects[$objectNumber] = true;
                return $this->pageContentObjectNumbersFromArrayBody($nestedArray, $objects, $seenArrayObjects);
            }
        }

        return [$objectNumber];
    }

    /**
     * @return list<int>
     * @param array<int, string> $objects
     * @param array<int, true> $seenArrayObjects
     */
    private function pageContentObjectNumbersFromArrayBody(string $arrayBody, array $objects, array $seenArrayObjects): array
    {
        $objectNumbers = [];
        foreach ($this->pdfArrayItems($arrayBody) as $item) {
            $item = trim($item);
            if ($item === '') {
                continue;
            }

            if (str_starts_with($item, '[')) {
                $nestedArray = $this->pdfArrayAtStart($item);
                if ($nestedArray === null) {
                    continue;
                }
                foreach ($this->pageContentObjectNumbersFromArrayBody($nestedArray, $objects, $seenArrayObjects) as $objectNumber) {
                    $objectNumbers[] = $objectNumber;
                }
                continue;
            }

            if (preg_match('/^(\d+)\s+\d+\s+R\b/s', $item, $match) !== 1) {
                continue;
            }

            $objectNumber = (int) $match[1];
            if (isset($seenArrayObjects[$objectNumber])) {
                continue;
            }

            if (isset($objects[$objectNumber]) && !isset($seenArrayObjects[$objectNumber])) {
                $nestedArray = $this->pdfArrayAtStart(trim($objects[$objectNumber]));
                if ($nestedArray !== null) {
                    $nextSeenArrayObjects = $seenArrayObjects;
                    $nextSeenArrayObjects[$objectNumber] = true;
                    foreach ($this->pageContentObjectNumbersFromArrayBody($nestedArray, $objects, $nextSeenArrayObjects) as $nestedObjectNumber) {
                        $objectNumbers[] = $nestedObjectNumber;
                    }
                    continue;
                }
            }

            $objectNumbers[] = $objectNumber;
        }

        return $objectNumbers;
    }

    /**
     * @return list<int>
     */
    private function objectReferences(string $value): array
    {
        $objectNumbers = [];
        $index = 0;
        $length = strlen($value);

        while ($index < $length) {
            $char = $value[$index];

            if (ctype_space($char)) {
                $index++;
                continue;
            }

            if ($char === '%') {
                $this->skipPdfComment($value, $index);
                continue;
            }

            if ($char === '(') {
                $skipped = $this->skipPdfLiteralStringAt($value, $index);
                $index = $skipped === null ? $index + 1 : $skipped + 1;
                continue;
            }

            if ($char === '<') {
                if (($value[$index + 1] ?? '') === '<') {
                    $index += 2;
                    continue;
                }

                $this->readHexToken($value, $index);
                continue;
            }

            if ($char === '>' && ($value[$index + 1] ?? '') === '>') {
                $index += 2;
                continue;
            }

            if ($char === '/') {
                $this->readNameToken($value, $index);
                continue;
            }

            if (preg_match('/\G(\d+)\s+\d+\s+R\b/s', $value, $match, 0, $index) === 1) {
                $objectNumbers[] = (int) $match[1];
                $index += strlen($match[0]);
                continue;
            }

            $index++;
        }

        return $objectNumbers;
    }

    /**
     * @return list<array{objectNumber: int, generation: int}>
     */
    private function objectReferencePairs(string $value): array
    {
        $references = [];
        $index = 0;
        $length = strlen($value);

        while ($index < $length) {
            $char = $value[$index];

            if (ctype_space($char)) {
                $index++;
                continue;
            }

            if ($char === '%') {
                $this->skipPdfComment($value, $index);
                continue;
            }

            if ($char === '(') {
                $skipped = $this->skipPdfLiteralStringAt($value, $index);
                $index = $skipped === null ? $index + 1 : $skipped + 1;
                continue;
            }

            if ($char === '<') {
                if (($value[$index + 1] ?? '') === '<') {
                    $index += 2;
                    continue;
                }

                $this->readHexToken($value, $index);
                continue;
            }

            if ($char === '>' && ($value[$index + 1] ?? '') === '>') {
                $index += 2;
                continue;
            }

            if ($char === '/') {
                $this->readNameToken($value, $index);
                continue;
            }

            if (preg_match('/\G(\d+)\s+(\d+)\s+R\b/s', $value, $match, 0, $index) === 1) {
                $references[] = [
                    'objectNumber' => (int) $match[1],
                    'generation' => (int) $match[2],
                ];
                $index += strlen($match[0]);
                continue;
            }

            $index++;
        }

        return $references;
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodeStreamObject(string $objectBody, array $objects): ?string
    {
        $entry = $this->streamDictionaryAndPayload($objectBody, $objects);
        if ($entry === null) {
            return null;
        }

        if ($this->isImageStreamDictionary($entry['dict'], $objects)) {
            return null;
        }

        return $this->decodeStream($entry['dict'], $entry['stream'], $objects);
    }

    /**
     * @param array<int, string> $objects
     * @return array{dict: string, stream: string}|null
     */
    private function streamDictionaryAndPayload(string $value, array $objects): ?array
    {
        $entry = $this->streamDictionaryAndPayloadWithOffsets($value, $objects);
        if ($entry === null) {
            return null;
        }

        return [
            'dict' => $entry['dict'],
            'stream' => $entry['stream'],
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array{dict: string, stream: string, streamStart: int, streamEnd: int}|null
     */
    private function streamDictionaryAndPayloadWithOffsets(string $value, array $objects): ?array
    {
        $dictionaryOffset = $this->skipPdfWhitespace($value, 0);
        $dictionaryEndOffset = $dictionaryOffset;
        $dict = $this->readPdfDictionaryTokenAt($value, $dictionaryEndOffset);
        if ($dict === null) {
            return null;
        }

        $streamKeywordOffset = $this->skipPdfWhitespace($value, $dictionaryEndOffset);
        if (!$this->pdfKeywordAt($value, $streamKeywordOffset, 'stream')) {
            return null;
        }

        $streamStart = $streamKeywordOffset + strlen('stream');
        if (substr($value, $streamStart, 2) === "\r\n") {
            $streamStart += 2;
        } elseif (($value[$streamStart] ?? '') === "\n" || ($value[$streamStart] ?? '') === "\r") {
            $streamStart++;
        }

        $stream = $this->streamPayloadAt($value, $streamStart, $dict, $objects);
        if ($stream === null) {
            return null;
        }

        return [
            'dict' => $dict,
            'stream' => $stream,
            'streamStart' => $streamStart,
            'streamEnd' => $streamStart + strlen($stream),
        ];
    }

    /**
     * @param array<int, string> $objects
     */
    private function streamPayloadAt(string $value, int $streamStart, string $dict, array $objects): ?string
    {
        $length = $this->streamLength($dict, $objects);
        if ($length !== null) {
            $declaredEnd = $streamStart + $length;
            if ($length >= 0 && $declaredEnd <= strlen($value)) {
                $declaredTerminator = $this->streamLengthTerminatorOffset($value, $declaredEnd);
                if ($declaredTerminator !== null) {
                    $dctJpegTerminator = $this->dctStreamEndstreamTerminatorOffset($value, $streamStart, $dict, $objects);
                    if ($dctJpegTerminator !== null && $dctJpegTerminator >= $declaredTerminator) {
                        return $this->stripStreamTerminatingLineEnding(substr($value, $streamStart, $dctJpegTerminator - $streamStart));
                    }

                    $recoveredTerminator = $this->startxrefRecoveredStreamTerminatorOffset(
                        $value,
                        $streamStart,
                        $dict,
                        $objects,
                        $declaredTerminator
                    );
                    if ($recoveredTerminator !== null) {
                        return $this->stripStreamTerminatingLineEnding(substr($value, $streamStart, $recoveredTerminator - $streamStart));
                    }

                    $filterStackRecoveredTerminator = $this->filterStackRecoveredStreamTerminatorOffset(
                        $value,
                        $streamStart,
                        $dict,
                        $objects,
                        $declaredTerminator
                    );
                    if ($filterStackRecoveredTerminator !== null) {
                        return $this->stripStreamTerminatingLineEnding(substr($value, $streamStart, $filterStackRecoveredTerminator - $streamStart));
                    }

                    return substr($value, $streamStart, $length);
                }

                $dctJpegTerminator = $this->dctStreamEndstreamTerminatorOffset($value, $streamStart, $dict, $objects);
                if ($dctJpegTerminator !== null) {
                    return $this->stripStreamTerminatingLineEnding(substr($value, $streamStart, $dctJpegTerminator - $streamStart));
                }

                $end = $this->filteredEndstreamTerminatorOffset($value, $streamStart, $dict, $objects, $declaredEnd)
                    ?? $this->contentStreamEndstreamTerminatorOffset($value, $streamStart, $dict)
                    ?? $this->endstreamTerminatorOffset($value, $streamStart, $declaredEnd);
                if ($end === null) {
                    return substr($value, $streamStart, $length);
                }

                return $this->stripStreamTerminatingLineEnding(substr($value, $streamStart, $end - $streamStart));
            }

            $dctJpegTerminator = $this->dctStreamEndstreamTerminatorOffset($value, $streamStart, $dict, $objects);
            if ($dctJpegTerminator !== null) {
                return $this->stripStreamTerminatingLineEnding(substr($value, $streamStart, $dctJpegTerminator - $streamStart));
            }

            $end = $this->contentStreamEndstreamTerminatorOffset($value, $streamStart, $dict)
                ?? $this->endstreamTerminatorOffset($value, $streamStart, null);
            if ($end === null) {
                return null;
            }

            return $this->stripStreamTerminatingLineEnding(substr($value, $streamStart, $end - $streamStart));
        }

        $end = $this->contentStreamEndstreamTerminatorOffset($value, $streamStart, $dict)
            ?? $this->endstreamTerminatorOffset($value, $streamStart, null);
        $end = $this->dctStreamEndstreamTerminatorOffset($value, $streamStart, $dict, $objects) ?? $end;
        $end = $this->filteredEndstreamTerminatorOffset($value, $streamStart, $dict, $objects) ?? $end;
        if ($end === null) {
            return null;
        }

        return $this->stripStreamTerminatingLineEnding(substr($value, $streamStart, $end - $streamStart));
    }

    /**
     * @param array<int, string> $objects
     */
    private function streamLength(string $dict, array $objects): ?int
    {
        $offset = $this->topLevelNameValueOffset($dict, 'Length');
        if ($offset === null) {
            return null;
        }

        return $this->streamLengthValueAt($dict, $offset, $objects);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function streamLengthValueAt(string $value, int $offset, array $objects, array $seen = []): ?int
    {
        $offset = $this->skipPdfWhitespace($value, $offset);
        if (preg_match('/\G(\d+)\s+(\d+)\s+R\b/s', $value, $match, 0, $offset) === 1) {
            $objectNumber = (int) $match[1];
            $generation = (int) $match[2];
            $objectKey = $objectNumber . ':' . $generation;
            if ($objectNumber <= 0 || isset($seen[$objectKey])) {
                return null;
            }

            $body = $this->indirectObjectBodyForReference($objects, $objectNumber, $generation);
            if ($body === null) {
                return null;
            }

            $seen[$objectKey] = true;
            return $this->streamLengthValueAt(trim($body), 0, $objects, $seen);
        }

        if (preg_match('/\G([+-]?\d+)/s', $value, $match, 0, $offset) === 1) {
            $length = (int) $match[1];
            return $length < 0 ? null : $length;
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function startxrefRecoveredStreamTerminatorOffset(
        string $value,
        int $streamStart,
        string $dict,
        array $objects,
        int $declaredTerminator
    ): ?int {
        $filteredTerminator = $this->filteredEndstreamTerminatorOffset($value, $streamStart, $dict, $objects);
        if (
            $filteredTerminator !== null
            && $filteredTerminator > $declaredTerminator
            && $this->streamTerminatorGapContainsStartxref($value, $declaredTerminator, $filteredTerminator)
        ) {
            return $filteredTerminator;
        }

        $contentTerminator = $this->contentStreamEndstreamTerminatorOffset($value, $streamStart, $dict);
        if (
            $contentTerminator !== null
            && $contentTerminator > $declaredTerminator
            && $this->streamTerminatorGapContainsStartxref($value, $declaredTerminator, $contentTerminator)
        ) {
            return $contentTerminator;
        }

        return null;
    }

    private function streamTerminatorGapContainsStartxref(
        string $value,
        int $declaredTerminator,
        int $recoveredTerminator
    ): bool {
        if ($recoveredTerminator <= $declaredTerminator) {
            return false;
        }

        $gapStart = $declaredTerminator + strlen('endstream');
        if ($gapStart >= $recoveredTerminator) {
            return false;
        }

        return preg_match('/\bstartxref\s+\d+\b/s', substr($value, $gapStart, $recoveredTerminator - $gapStart)) === 1;
    }

    /**
     * @param array<int, string> $objects
     */
    private function filterStackRecoveredStreamTerminatorOffset(
        string $value,
        int $streamStart,
        string $dict,
        array $objects,
        int $declaredTerminator
    ): ?int {
        $filters = $this->streamFilters($dict, $objects);
        if ($filters === null || !$this->hasVerifiableStreamFilter($filters)) {
            return null;
        }

        $declaredPayload = $this->stripStreamTerminatingLineEnding(substr($value, $streamStart, $declaredTerminator - $streamStart));
        if ($this->decodeStream($dict, $declaredPayload, $objects, true) !== null) {
            return null;
        }

        $filteredTerminator = $this->filteredEndstreamTerminatorOffset($value, $streamStart, $dict, $objects);
        return $filteredTerminator !== null && $filteredTerminator > $declaredTerminator
            ? $filteredTerminator
            : null;
    }

    private function streamLengthTerminatorOffset(string $value, int $declaredEnd): ?int
    {
        $offset = $declaredEnd;
        if (substr($value, $offset, 2) === "\r\n") {
            $offset += 2;
        } elseif (($value[$offset] ?? '') === "\n" || ($value[$offset] ?? '') === "\r") {
            $offset++;
        }

        return $this->endstreamKeywordAt($value, $offset) ? $offset : null;
    }

    private function endstreamTerminatorOffset(string $value, int $streamStart, ?int $declaredEnd): ?int
    {
        $fallback = null;
        $beforeDeclaredEnd = null;
        $offset = $streamStart;
        while (($candidate = strpos($value, 'endstream', $offset)) !== false) {
            $fallback ??= $candidate;
            $offset = $candidate + 9;

            if (!$this->endstreamTerminatorAt($value, $candidate, $streamStart)) {
                continue;
            }

            if ($declaredEnd === null) {
                return $candidate;
            }

            if ($candidate >= $declaredEnd) {
                return $candidate;
            }

            $beforeDeclaredEnd = $candidate;
        }

        return $beforeDeclaredEnd ?? $fallback;
    }

    private function endstreamTerminatorAt(string $value, int $offset, int $streamStart): bool
    {
        if (!$this->endstreamKeywordAt($value, $offset)) {
            return false;
        }

        if ($offset <= $streamStart) {
            return true;
        }

        $previous = $value[$offset - 1] ?? '';
        return $previous === "\n" || $previous === "\r";
    }

    private function endstreamKeywordAt(string $value, int $offset): bool
    {
        if (substr($value, $offset, 9) !== 'endstream') {
            return false;
        }

        $after = $offset + 9;
        return $after >= strlen($value) || ctype_space($value[$after]);
    }

    /**
     * @param array<int, string> $objects
     */
    private function filteredEndstreamTerminatorOffset(
        string $value,
        int $streamStart,
        string $dict,
        array $objects,
        ?int $minimumOffset = null
    ): ?int
    {
        $filters = $this->streamFilters($dict, $objects);
        if ($filters === null || !$this->hasVerifiableStreamFilter($filters)) {
            return null;
        }

        $offset = $streamStart;
        while (($candidate = strpos($value, 'endstream', $offset)) !== false) {
            $offset = $candidate + 9;
            if (!$this->endstreamTerminatorAt($value, $candidate, $streamStart)) {
                continue;
            }

            if ($minimumOffset !== null && $candidate < $minimumOffset) {
                continue;
            }

            $payload = $this->stripStreamTerminatingLineEnding(substr($value, $streamStart, $candidate - $streamStart));
            if ($this->decodeStream($dict, $payload, $objects, true) !== null) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function dctStreamEndstreamTerminatorOffset(string $value, int $streamStart, string $dict, array $objects): ?int
    {
        $filters = $this->streamFilters($dict, $objects);
        if ($filters === null || $filters === []) {
            return null;
        }

        $firstFilter = null;
        $firstFilterIndex = null;
        foreach ($filters as $index => $filter) {
            if ($filter !== null) {
                $firstFilter = $filter;
                $firstFilterIndex = $index;
                break;
            }
        }

        if ($firstFilter === null || $firstFilterIndex === null) {
            return null;
        }

        if ($firstFilter !== 'DCTDecode' && $firstFilter !== 'DCT') {
            return $this->dctPrefixFilterEndstreamTerminatorOffset(
                $value,
                $streamStart,
                $firstFilter,
                $filters,
                $firstFilterIndex,
                $dict,
                $objects
            );
        }

        $jpegStart = $streamStart;
        $length = strlen($value);
        while ($jpegStart < $length && str_contains("\x00\t\n\f\r ", $value[$jpegStart])) {
            $jpegStart++;
        }

        if (substr($value, $jpegStart, 2) !== "\xff\xd8") {
            return null;
        }

        $eoi = strpos($value, "\xff\xd9", $jpegStart + 2);
        if ($eoi === false) {
            return null;
        }

        $terminator = $this->skipPdfWhitespace($value, $eoi + 2);
        return $this->endstreamKeywordAt($value, $terminator) ? $terminator : null;
    }

    /**
     * @param list<string|null> $filters
     */
    private function dctPrefixFilterEndstreamTerminatorOffset(
        string $value,
        int $streamStart,
        string $firstFilter,
        array $filters,
        int $firstFilterIndex,
        string $dict,
        array $objects
    ): ?int {
        $dctFilterIndex = null;
        for ($index = $firstFilterIndex + 1; $index < count($filters); $index++) {
            $filter = $filters[$index];
            if ($filter === 'DCTDecode' || $filter === 'DCT') {
                $dctFilterIndex = $index;
                break;
            }
        }

        if ($dctFilterIndex === null) {
            return null;
        }

        $payloadEnd = match ($firstFilter) {
            'ASCIIHexDecode', 'AHx' => $this->firstFilterEndMarkerOffset($value, $streamStart, '>'),
            'ASCII85Decode', 'A85' => $this->firstFilterEndMarkerOffset($value, $streamStart, '~>'),
            'RunLengthDecode', 'RL' => $this->firstFilterEndMarkerOffset($value, $streamStart, chr(128)),
            default => null,
        };

        if ($payloadEnd !== null) {
            $terminator = $this->skipPdfWhitespace($value, $payloadEnd);
            if ($this->endstreamKeywordAt($value, $terminator)) {
                return $terminator;
            }
        }

        $offset = $streamStart;
        while (($candidate = strpos($value, 'endstream', $offset)) !== false) {
            $offset = $candidate + strlen('endstream');
            if (!$this->endstreamTerminatorAt($value, $candidate, $streamStart)) {
                continue;
            }

            $payload = $this->stripStreamTerminatingLineEnding(substr($value, $streamStart, $candidate - $streamStart));
            $jpegBytes = $this->decodeStreamBeforeFilter($dict, $payload, $objects, $filters, $dctFilterIndex);
            if ($jpegBytes !== null && $this->dctPreviewBytesAreCompleteJpeg($jpegBytes)) {
                return $candidate;
            }
        }

        return null;
    }

    private function firstFilterEndMarkerOffset(string $value, int $streamStart, string $marker): ?int
    {
        $offset = strpos($value, $marker, $streamStart);
        return $offset === false ? null : $offset + strlen($marker);
    }

    /**
     * @param array<int, string> $objects
     * @param list<string|null> $filters
     */
    private function decodeStreamBeforeFilter(
        string $dict,
        string $stream,
        array $objects,
        array $filters,
        int $stopBeforeIndex
    ): ?string {
        $decodeParms = $this->streamDecodeParms($dict, $objects);
        if ($decodeParms === null) {
            return null;
        }

        for ($index = 0; $index < $stopBeforeIndex; $index++) {
            $filter = $filters[$index] ?? null;
            if ($filter === null) {
                continue;
            }

            $filterDecodeParms = $this->decodeParmsForFilterIndex($filters, $decodeParms, $index);
            if (!$this->canApplyDecodeParms($filter, $filterDecodeParms, $objects)) {
                return null;
            }

            if (!$this->streamFilterInputHasExplicitEndMarker($filter, $stream)) {
                return null;
            }

            $decoded = match ($filter) {
                'ASCIIHexDecode', 'AHx' => $this->decodeAsciiHexStream($stream),
                'ASCII85Decode', 'A85' => $this->decodeAscii85Stream($stream),
                'RunLengthDecode', 'RL' => $this->decodeRunLengthStream($stream),
                'LZWDecode', 'LZW' => $this->decodeLzwStream($stream, $filterDecodeParms, $objects),
                'FlateDecode', 'Fl' => $this->decodeFlateStream($stream, $filterDecodeParms, $objects),
                default => null,
            };

            if ($decoded === null) {
                return null;
            }

            $stream = $decoded;
        }

        return $stream;
    }

    private function dctPreviewBytesAreCompleteJpeg(string $bytes): bool
    {
        $length = strlen($bytes);
        $start = 0;
        while ($start < $length && str_contains("\t\n\f\r ", $bytes[$start])) {
            $start++;
        }

        $bytes = rtrim(substr($bytes, $start), "\x00\t\n\f\r ");
        if (!str_starts_with($bytes, "\xff\xd8")) {
            return false;
        }

        $eoi = strrpos($bytes, "\xff\xd9");
        return $eoi !== false && $eoi + 2 === strlen($bytes);
    }

    private function contentStreamEndstreamTerminatorOffset(string $value, int $streamStart, string $dict): ?int
    {
        if (!$this->canScanContentStreamForInlineImages($dict)) {
            return null;
        }

        $index = $streamStart;
        $length = strlen($value);
        while ($index < $length) {
            $char = $value[$index];
            if (ctype_space($char)) {
                $index++;
                continue;
            }

            if ($char === '%') {
                $this->skipPdfComment($value, $index);
                continue;
            }

            if ($char === '(') {
                $this->readLiteralToken($value, $index);
                continue;
            }

            if ($char === '<') {
                if ($index + 1 < $length && $value[$index + 1] === '<') {
                    $this->readDictionaryToken($value, $index);
                    continue;
                }

                $this->readHexToken($value, $index);
                continue;
            }

            if ($char === '[') {
                $this->readArrayToken($value, $index);
                continue;
            }

            if (
                $this->pdfKeywordAt($value, $index, 'endstream')
                && $this->endstreamTerminatorAt($value, $index, $streamStart)
            ) {
                return $index;
            }

            $start = $index;
            while ($index < $length && !$this->isDelimiter($value[$index])) {
                $index++;
            }

            if ($index === $start) {
                $index++;
                continue;
            }

            if (substr($value, $start, $index - $start) === 'BI') {
                $inlineImageEnd = $index;
                if ($this->skipInlineImage($value, $inlineImageEnd)) {
                    $index = $inlineImageEnd;
                }
            }
        }

        return null;
    }

    private function canScanContentStreamForInlineImages(string $dict): bool
    {
        $filters = $this->streamFilters($dict, []);
        if ($filters !== []) {
            return false;
        }

        return preg_match('/\/(?:Type|Subtype)\s*\/(?:ObjStm|XRef|Image|Metadata|EmbeddedFile|XML)\b/s', $dict) !== 1
            && preg_match('/\/Type\s*\/EmbeddedFile\b/s', $dict) !== 1;
    }

    /**
     * @param list<string|null> $filters
     */
    private function hasVerifiableStreamFilter(array $filters): bool
    {
        foreach ($filters as $filter) {
            if (in_array($filter, [
                'ASCII85Decode',
                'A85',
                'ASCIIHexDecode',
                'AHx',
                'FlateDecode',
                'Fl',
                'LZWDecode',
                'LZW',
                'RunLengthDecode',
                'RL',
            ], true)) {
                return true;
            }
        }

        return false;
    }

    private function stripStreamTerminatingLineEnding(string $stream): string
    {
        if (str_ends_with($stream, "\r\n")) {
            return substr($stream, 0, -2);
        }

        if (str_ends_with($stream, "\n") || str_ends_with($stream, "\r")) {
            return substr($stream, 0, -1);
        }

        return $stream;
    }

    private function isEmbeddedFileStreamDictionary(string $dict): bool
    {
        return preg_match('/\/Type\s*\/EmbeddedFile\b/', $dict) === 1;
    }

    /**
     * @param array<int, string> $objects
     */
    private function isObjectStreamDictionary(string $dict, array $objects): bool
    {
        $offset = $this->topLevelNameValueOffset($dict, 'Type');
        if ($offset === null) {
            return false;
        }

        return $this->pdfNameValueAt($dict, $offset, $objects) === 'ObjStm';
    }

    /**
     * @param array<int, string> $objects
     */
    private function isXrefStreamDictionary(string $dict, array $objects): bool
    {
        $offset = $this->topLevelNameValueOffset($dict, 'Type');
        if ($offset === null) {
            return false;
        }

        return $this->pdfNameValueAt($dict, $offset, $objects) === 'XRef';
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodeStream(
        string $dict,
        string $stream,
        array $objects = [],
        bool $requireExplicitFilterEndMarkers = false
    ): ?string
    {
        $filters = $this->streamFilters($dict, $objects);
        if ($filters === null) {
            return null;
        }

        $decodeParms = $this->streamDecodeParms($dict, $objects);
        if ($decodeParms === null) {
            return null;
        }
        foreach ($filters as $index => $filter) {
            if ($filter === null) {
                continue;
            }

            $filterDecodeParms = $this->decodeParmsForFilterIndex($filters, $decodeParms, $index);
            if (!$this->canApplyDecodeParms($filter, $filterDecodeParms, $objects)) {
                return null;
            }

            if (
                $requireExplicitFilterEndMarkers
                && !$this->streamFilterInputHasExplicitEndMarker($filter, $stream)
            ) {
                return null;
            }

            $decoded = match ($filter) {
                'ASCIIHexDecode', 'AHx' => $this->decodeAsciiHexStream($stream),
                'ASCII85Decode', 'A85' => $this->decodeAscii85Stream($stream),
                'RunLengthDecode', 'RL' => $this->decodeRunLengthStream($stream),
                'LZWDecode', 'LZW' => $this->decodeLzwStream($stream, $filterDecodeParms, $objects),
                'FlateDecode', 'Fl' => $this->decodeFlateStream($stream, $filterDecodeParms, $objects),
                'DCTDecode', 'DCT' => null,
                'CCITTFaxDecode', 'CCF' => null,
                'JPXDecode', 'JBIG2Decode' => null,
                default => null,
            };

            if ($decoded === null) {
                return null;
            }
            $stream = $decoded;
        }

        return $stream;
    }

    private function streamFilterInputHasExplicitEndMarker(string $filter, string $stream): bool
    {
        return match ($filter) {
            'ASCIIHexDecode', 'AHx' => strpos($stream, '>') !== false,
            'ASCII85Decode', 'A85' => strpos($stream, '~>') !== false,
            'RunLengthDecode', 'RL' => strpos($stream, chr(128)) !== false,
            default => true,
        };
    }

    /**
     * @param list<string|null> $filters
     * @param list<string|null> $decodeParms
     */
    private function decodeParmsForFilterIndex(array $filters, array $decodeParms, int $index): ?string
    {
        if (array_key_exists($index, $decodeParms)) {
            return $decodeParms[$index];
        }

        if (count($decodeParms) !== 1) {
            return null;
        }

        $nonNullFilterIndexes = [];
        foreach ($filters as $filterIndex => $filter) {
            if (is_string($filter)) {
                $nonNullFilterIndexes[] = $filterIndex;
            }
        }

        return $nonNullFilterIndexes === [$index]
            ? $decodeParms[0]
            : null;
    }

    /**
     * @return list<string|null>|null
     * @param array<int, string> $objects
     */
    private function streamFilters(string $dict, array $objects = []): ?array
    {
        $offset = $this->topLevelNameValueOffset($dict, 'Filter');
        if ($offset === null) {
            return [];
        }

        if ($offset >= strlen($dict)) {
            return null;
        }

        if ($dict[$offset] === '[') {
            $arrayBody = $this->readPdfArrayAt($dict, $offset);
            return $arrayBody === null ? null : $this->filterNamesFromValue($arrayBody, $objects, [], false);
        }

        if ($dict[$offset] === '/') {
            $end = $offset + 1;
            while ($end < strlen($dict) && !str_contains(" \t\r\n\f[]()<>{}/%", $dict[$end])) {
                $end++;
            }

            return [$this->decodePdfName(substr($dict, $offset + 1, $end - $offset - 1))];
        }

        if (preg_match('/\Gnull\b/s', $dict, $match, 0, $offset) === 1) {
            return [];
        }

        if (preg_match('/\G(\d+)\s+(\d+)\s+R\b/s', $dict, $match, 0, $offset) === 1) {
            $objectNumber = (int) $match[1];
            $generation = (int) $match[2];
            $body = $this->indirectObjectBodyForReference($objects, $objectNumber, $generation);
            return $body === null
                ? null
                : $this->filterNamesFromValue(trim($body), $objects, [$objectNumber . ':' . $generation => true]);
        }

        return null;
    }

    /**
     * @return list<string|null>|null
     * @param array<int, string> $objects
     * @param array<int, true> $seenObjects
     */
    private function filterNamesFromValue(
        string $value,
        array $objects,
        array $seenObjects = [],
        bool $allowArrayValue = true
    ): ?array
    {
        $filters = [];
        $offset = 0;
        $length = strlen($value);
        while ($offset < $length) {
            $offset = $this->skipPdfWhitespace($value, $offset);
            if ($offset >= $length) {
                break;
            }

            if ($value[$offset] === '%') {
                $this->skipPdfComment($value, $offset);
                continue;
            }

            if ($value[$offset] === '[') {
                if (!$allowArrayValue || $filters !== []) {
                    return null;
                }

                $arrayBody = $this->readPdfArrayAt($value, $offset);
                if ($arrayBody === null) {
                    return null;
                }

                $nested = $this->filterNamesFromValue($arrayBody, $objects, $seenObjects, false);
                if ($nested === null) {
                    return null;
                }

                foreach ($nested as $filter) {
                    $filters[] = $filter;
                }
                $offset += strlen($arrayBody) + 2;
                continue;
            }

            if ($value[$offset] === '/') {
                $end = $offset + 1;
                while ($end < $length && !str_contains(" \t\r\n\f[]()<>{}/%", $value[$end])) {
                    $end++;
                }

                $filters[] = $this->decodePdfName(substr($value, $offset + 1, $end - $offset - 1));
                $offset = $end;
                continue;
            }

            if (preg_match('/\Gnull\b/s', $value, $match, 0, $offset) === 1) {
                $filters[] = null;
                $offset += strlen($match[0]);
                continue;
            }

            if (preg_match('/\G(\d+)\s+(\d+)\s+R\b/s', $value, $match, 0, $offset) === 1) {
                $objectNumber = (int) $match[1];
                $generation = (int) $match[2];
                $objectKey = $objectNumber . ':' . $generation;
                if ($objectNumber <= 0 || isset($seenObjects[$objectKey])) {
                    return null;
                }

                $body = $this->indirectObjectBodyForReference($objects, $objectNumber, $generation);
                if ($body === null) {
                    return null;
                }

                $nextSeen = $seenObjects;
                $nextSeen[$objectKey] = true;
                $nested = $this->filterNamesFromValue(trim($body), $objects, $nextSeen, $allowArrayValue && $filters === []);
                if ($nested === null) {
                    return null;
                }

                foreach ($nested as $filter) {
                    $filters[] = $filter;
                }
                $offset += strlen($match[0]);
                continue;
            }

            return null;
        }

        return $filters;
    }

    /**
     * @return list<string|null>|null
     * @param array<int, string> $objects
     */
    private function streamDecodeParms(string $dict, array $objects): ?array
    {
        $offset = $this->topLevelNameValueOffset($dict, 'DecodeParms');
        if ($offset === null) {
            return [];
        }

        return $this->decodeParmsValueList($dict, $offset, $objects);
    }

    /**
     * @return list<string|null>|null
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function decodeParmsValueList(string $value, int $offset, array $objects, array $seen = []): ?array
    {
        $offset = $this->skipPdfWhitespace($value, $offset);
        if ($offset >= strlen($value)) {
            return null;
        }

        if ($value[$offset] === '[') {
            $arrayBody = $this->readPdfArrayAt($value, $offset);
            return $arrayBody === null ? null : $this->decodeParmsArrayItems($arrayBody, $objects, $seen);
        }

        if (substr($value, $offset, 2) === '<<') {
            $dictionary = $this->readPdfDictionaryTokenAt($value, $offset);
            return $dictionary === null ? null : [$dictionary];
        }

        if (preg_match('/\Gnull\b/s', $value, $match, 0, $offset) === 1) {
            return [null];
        }

        if (preg_match('/\G(\d+)\s+(\d+)\s+R\b/s', $value, $match, 0, $offset) === 1) {
            $objectNumber = (int) $match[1];
            $generation = (int) $match[2];
            $objectKey = $objectNumber . ':' . $generation;
            if ($objectNumber <= 0 || isset($seen[$objectKey])) {
                return null;
            }

            $body = $this->indirectObjectBodyForReference($objects, $objectNumber, $generation);
            if ($body === null) {
                return null;
            }

            $seen[$objectKey] = true;
            return $this->decodeParmsValueList(trim($body), 0, $objects, $seen);
        }

        return null;
    }

    /**
     * @return list<string|null>|null
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function decodeParmsArrayItems(string $arrayBody, array $objects, array $seen): ?array
    {
        $items = [];
        $offset = 0;
        $length = strlen($arrayBody);
        while ($offset < $length) {
            $offset = $this->skipPdfWhitespace($arrayBody, $offset);
            if ($offset >= $length) {
                break;
            }

            if ($arrayBody[$offset] === '%') {
                $this->skipPdfComment($arrayBody, $offset);
                continue;
            }

            if (preg_match('/\Gnull\b/s', $arrayBody, $match, 0, $offset) === 1) {
                $items[] = null;
                $offset += strlen($match[0]);
                continue;
            }

            if (substr($arrayBody, $offset, 2) === '<<') {
                $dictionary = $this->readPdfDictionaryTokenAt($arrayBody, $offset);
                if ($dictionary !== null) {
                    $items[] = $dictionary;
                    continue;
                }

                return null;
            }

            if (preg_match('/\G(\d+)\s+(\d+)\s+R\b/s', $arrayBody, $match, 0, $offset) === 1) {
                $objectNumber = (int) $match[1];
                $generation = (int) $match[2];
                $objectKey = $objectNumber . ':' . $generation;
                if ($objectNumber <= 0 || isset($seen[$objectKey])) {
                    return null;
                }

                $body = $this->indirectObjectBodyForReference($objects, $objectNumber, $generation);
                if ($body === null) {
                    return null;
                }

                $nextSeen = $seen;
                $nextSeen[$objectKey] = true;
                $resolved = $this->decodeParmsValueList(trim($body), 0, $objects, $nextSeen);
                if ($resolved === null || count($resolved) !== 1) {
                    return null;
                }

                $items[] = $resolved[0];
                $offset += strlen($match[0]);
                continue;
            }

            return null;
        }

        return $items;
    }

    /**
     * @param array<int, string> $objects
     */
    private function canApplyDecodeParms(string $filter, ?string $decodeParms, array $objects): bool
    {
        if ($decodeParms === null || trim($decodeParms) === '') {
            return true;
        }

        foreach (['Predictor', 'Columns', 'Colors', 'BitsPerComponent', 'EarlyChange'] as $name) {
            if (
                $this->decodeParmsHasName($decodeParms, $name)
                && $this->decodeParmsInt($decodeParms, $name, $objects) === null
            ) {
                return false;
            }
        }

        $predictor = $this->decodeParmsInt($decodeParms, 'Predictor', $objects);
        if (
            $predictor !== null
            && $predictor !== 1
            && !in_array($filter, ['FlateDecode', 'Fl', 'LZWDecode', 'LZW'], true)
        ) {
            return false;
        }

        foreach (['Columns', 'Colors', 'BitsPerComponent'] as $name) {
            $value = $this->decodeParmsInt($decodeParms, $name, $objects);
            if ($value !== null && $value < 1) {
                return false;
            }
        }

        $earlyChange = $this->decodeParmsInt($decodeParms, 'EarlyChange', $objects);
        if (
            in_array($filter, ['LZWDecode', 'LZW'], true)
            && $earlyChange !== null
            && !in_array($earlyChange, [0, 1], true)
        ) {
            return false;
        }

        return true;
    }

    private function decodeParmsHasName(?string $decodeParms, string $name): bool
    {
        return $decodeParms !== null && $this->topLevelNameValueOffset($decodeParms, $name) !== null;
    }

    private function decodeAsciiHexStream(string $stream): ?string
    {
        $body = strstr($stream, '>', true);
        if ($body === false) {
            $body = $stream;
        }

        $hex = preg_replace('/\s+/', '', $body);
        if ($hex === null || preg_match('/^[\da-fA-F]*$/', $hex) !== 1) {
            return null;
        }

        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        $decoded = hex2bin($hex);
        return $decoded === false ? null : $decoded;
    }

    private function decodeAscii85Stream(string $stream): ?string
    {
        $body = trim($stream);
        if (str_starts_with($body, '<~')) {
            $body = substr($body, 2);
        }

        $terminator = strpos($body, '~>');
        if ($terminator !== false) {
            $body = substr($body, 0, $terminator);
        }

        $out = '';
        $group = [];
        $length = strlen($body);
        for ($index = 0; $index < $length; $index++) {
            $char = $body[$index];
            if (ctype_space($char)) {
                continue;
            }

            if ($char === 'z') {
                if ($group !== []) {
                    return null;
                }
                $out .= "\0\0\0\0";
                continue;
            }

            $ord = ord($char);
            if ($ord < 33 || $ord > 117) {
                return null;
            }

            $group[] = $ord - 33;
            if (count($group) === 5) {
                $out .= $this->decodeAscii85Group($group, 4);
                $group = [];
            }
        }

        if ($group !== []) {
            $groupLength = count($group);
            if ($groupLength === 1) {
                return null;
            }
            while (count($group) < 5) {
                $group[] = 84;
            }
            $out .= $this->decodeAscii85Group($group, $groupLength - 1);
        }

        return $out;
    }

    /**
     * @param list<int> $group
     */
    private function decodeAscii85Group(array $group, int $bytesToReturn): string
    {
        $value = 0;
        foreach ($group as $digit) {
            $value = ($value * 85) + $digit;
        }

        $bytes = '';
        for ($shift = 24; $shift >= 0; $shift -= 8) {
            $bytes .= chr(($value >> $shift) & 0xff);
        }

        return substr($bytes, 0, $bytesToReturn);
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodeFlateStream(string $stream, ?string $decodeParms = null, array $objects = []): ?string
    {
        $inflated = @gzuncompress($stream);
        if ($inflated === false) {
            $inflated = @gzinflate($stream);
        }
        if ($inflated === false) {
            $inflated = @gzdecode($stream);
        }

        if ($inflated === false) {
            return null;
        }

        return $this->applyDecodeParmsPredictor($inflated, $decodeParms, $objects);
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodeLzwStream(string $stream, ?string $decodeParms = null, array $objects = []): ?string
    {
        $earlyChange = ($this->decodeParmsInt($decodeParms, 'EarlyChange', $objects) ?? 1) === 0 ? 0 : 1;
        $bitOffset = 0;
        $dictionary = [];
        $nextCode = 258;
        $codeSize = 9;

        $resetDictionary = static function () use (&$dictionary, &$nextCode, &$codeSize): void {
            $dictionary = [];
            for ($code = 0; $code < 256; $code++) {
                $dictionary[$code] = chr($code);
            }
            $nextCode = 258;
            $codeSize = 9;
        };
        $resetDictionary();

        $out = '';
        $previous = null;
        while (($code = $this->readLzwCode($stream, $bitOffset, $codeSize)) !== null) {
            if ($code === 256) {
                $resetDictionary();
                $previous = null;
                continue;
            }

            if ($code === 257) {
                return $this->applyDecodeParmsPredictor($out, $decodeParms, $objects);
            }

            if (isset($dictionary[$code])) {
                $entry = $dictionary[$code];
            } elseif ($code === $nextCode && $previous !== null) {
                $entry = $previous . $previous[0];
            } else {
                return null;
            }

            $out .= $entry;
            if ($previous !== null && $nextCode < 4096) {
                $dictionary[$nextCode] = $previous . $entry[0];
                $nextCode++;
                if ($codeSize < 12 && $nextCode + $earlyChange >= (1 << $codeSize)) {
                    $codeSize++;
                }
            }
            $previous = $entry;
        }

        return null;
    }

    private function readLzwCode(string $bytes, int &$bitOffset, int $codeSize): ?int
    {
        $totalBits = strlen($bytes) * 8;
        if ($bitOffset + $codeSize > $totalBits) {
            return null;
        }

        $code = 0;
        for ($index = 0; $index < $codeSize; $index++) {
            $absoluteBit = $bitOffset + $index;
            $byte = ord($bytes[intdiv($absoluteBit, 8)]);
            $shift = 7 - ($absoluteBit % 8);
            $code = ($code << 1) | (($byte >> $shift) & 1);
        }
        $bitOffset += $codeSize;

        return $code;
    }

    /**
     * @param array<int, string> $objects
     */
    private function applyDecodeParmsPredictor(string $bytes, ?string $decodeParms, array $objects = []): ?string
    {
        $predictor = $this->decodeParmsInt($decodeParms, 'Predictor', $objects) ?? 1;
        if ($predictor === 1) {
            return $bytes;
        }

        $colors = max(1, $this->decodeParmsInt($decodeParms, 'Colors', $objects) ?? 1);
        $bitsPerComponent = max(1, $this->decodeParmsInt($decodeParms, 'BitsPerComponent', $objects) ?? 8);
        $columns = max(1, $this->decodeParmsInt($decodeParms, 'Columns', $objects) ?? 1);
        $rowLength = intdiv(($colors * $columns * $bitsPerComponent) + 7, 8);
        $bytesPerPixel = max(1, intdiv(($colors * $bitsPerComponent) + 7, 8));

        if ($predictor === 2) {
            return $this->applyTiffPredictor($bytes, $rowLength, $bytesPerPixel);
        }

        if ($predictor < 10 || $predictor > 15) {
            return null;
        }

        return $this->applyPngPredictor($bytes, $rowLength, $bytesPerPixel);
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodeParmsInt(?string $decodeParms, string $name, array $objects = []): ?int
    {
        if ($decodeParms === null) {
            return null;
        }

        $offset = $this->topLevelNameValueOffset($decodeParms, $name);
        return $offset === null ? null : $this->decodeParmsIntegerTokenAt($decodeParms, $offset, $objects);
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodeParmsBool(?string $decodeParms, string $name, array $objects = []): ?bool
    {
        if ($decodeParms === null) {
            return null;
        }

        $offset = $this->topLevelNameValueOffset($decodeParms, $name);
        return $offset === null ? null : $this->decodeParmsBooleanTokenAt($decodeParms, $offset, $objects);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function decodeParmsIntegerTokenAt(string $value, int $offset, array $objects, array $seen = []): ?int
    {
        $offset = $this->skipPdfWhitespace($value, $offset);
        if (preg_match('/\G(\d+)\s+(\d+)\s+R\b/s', $value, $match, 0, $offset) === 1) {
            $objectNumber = (int) $match[1];
            $generation = (int) $match[2];
            $objectKey = $objectNumber . ':' . $generation;
            if ($objectNumber <= 0 || isset($seen[$objectKey])) {
                return null;
            }

            $body = $this->indirectObjectBodyForReference($objects, $objectNumber, $generation);
            if ($body === null) {
                return null;
            }

            $seen[$objectKey] = true;
            return $this->decodeParmsIntegerTokenAt(trim($body), 0, $objects, $seen);
        }

        if (preg_match('/\G([+-]?\d+)/s', $value, $match, 0, $offset) === 1) {
            return (int) $match[1];
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function decodeParmsBooleanTokenAt(string $value, int $offset, array $objects, array $seen = []): ?bool
    {
        $offset = $this->skipPdfWhitespace($value, $offset);
        if (preg_match('/\G(\d+)\s+(\d+)\s+R\b/s', $value, $match, 0, $offset) === 1) {
            $objectNumber = (int) $match[1];
            $generation = (int) $match[2];
            $objectKey = $objectNumber . ':' . $generation;
            if ($objectNumber <= 0 || isset($seen[$objectKey])) {
                return null;
            }

            $body = $this->indirectObjectBodyForReference($objects, $objectNumber, $generation);
            if ($body === null) {
                return null;
            }

            $seen[$objectKey] = true;
            return $this->decodeParmsBooleanTokenAt(trim($body), 0, $objects, $seen);
        }

        if (preg_match('/\Gtrue\b/s', $value, $match, 0, $offset) === 1) {
            return true;
        }

        if (preg_match('/\Gfalse\b/s', $value, $match, 0, $offset) === 1) {
            return false;
        }

        return null;
    }

    private function applyTiffPredictor(string $bytes, int $rowLength, int $bytesPerPixel): ?string
    {
        if ($rowLength < 1 || strlen($bytes) % $rowLength !== 0) {
            return null;
        }

        $out = '';
        for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $rowLength) {
            $row = substr($bytes, $offset, $rowLength);
            for ($index = $bytesPerPixel; $index < $rowLength; $index++) {
                $row[$index] = chr((ord($row[$index]) + ord($row[$index - $bytesPerPixel])) & 0xff);
            }
            $out .= $row;
        }

        return $out;
    }

    private function applyPngPredictor(string $bytes, int $rowLength, int $bytesPerPixel): ?string
    {
        $stride = $rowLength + 1;
        if ($rowLength < 1 || strlen($bytes) % $stride !== 0) {
            return null;
        }

        $out = '';
        $previous = str_repeat("\0", $rowLength);
        for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $stride) {
            $filter = ord($bytes[$offset]);
            $row = substr($bytes, $offset + 1, $rowLength);
            if ($filter > 4) {
                return null;
            }

            for ($index = 0; $index < $rowLength; $index++) {
                $left = $index >= $bytesPerPixel ? ord($row[$index - $bytesPerPixel]) : 0;
                $up = ord($previous[$index]);
                $upperLeft = $index >= $bytesPerPixel ? ord($previous[$index - $bytesPerPixel]) : 0;
                $encoded = ord($row[$index]);
                $row[$index] = chr(($encoded + $this->pngPredictorValue($filter, $left, $up, $upperLeft)) & 0xff);
            }

            $out .= $row;
            $previous = $row;
        }

        return $out;
    }

    private function pngPredictorValue(int $filter, int $left, int $up, int $upperLeft): int
    {
        return match ($filter) {
            0 => 0,
            1 => $left,
            2 => $up,
            3 => intdiv($left + $up, 2),
            4 => $this->paethPredictor($left, $up, $upperLeft),
        };
    }

    private function paethPredictor(int $left, int $up, int $upperLeft): int
    {
        $estimate = $left + $up - $upperLeft;
        $leftDistance = abs($estimate - $left);
        $upDistance = abs($estimate - $up);
        $upperLeftDistance = abs($estimate - $upperLeft);

        if ($leftDistance <= $upDistance && $leftDistance <= $upperLeftDistance) {
            return $left;
        }
        if ($upDistance <= $upperLeftDistance) {
            return $up;
        }

        return $upperLeft;
    }

    private function decodeRunLengthStream(string $stream): ?string
    {
        $out = '';
        $length = strlen($stream);
        for ($offset = 0; $offset < $length; $offset++) {
            $control = ord($stream[$offset]);
            if ($control === 128) {
                return $out;
            }

            if ($control <= 127) {
                $copyLength = $control + 1;
                if ($offset + $copyLength >= $length) {
                    return null;
                }
                $out .= substr($stream, $offset + 1, $copyLength);
                $offset += $copyLength;
                continue;
            }

            if ($offset + 1 >= $length) {
                return null;
            }
            $out .= str_repeat($stream[$offset + 1], 257 - $control);
            $offset++;
        }

        return null;
    }

    /**
     * @return array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}>
     */
    private function fontToUnicodeMaps(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        $fontObjectMaps = $this->fontObjectMaps($objects);
        if ($fontObjectMaps === []) {
            return [];
        }

        $resourceMaps = [];
        foreach ($objects as $body) {
            $resourceDictionary = $this->resourceDictionaryBody($body, $objects);
            if ($resourceDictionary === null) {
                continue;
            }

            foreach ($this->fontResourceMapsFromResourceDictionary($resourceDictionary, $objects, $fontObjectMaps) as $name => $map) {
                $resourceMaps[$name] = $map;
            }
        }

        if ($resourceMaps !== []) {
            return $resourceMaps;
        }

        if (count($fontObjectMaps) === 1) {
            $onlyMap = reset($fontObjectMaps);
            return is_array($onlyMap) ? ['' => $onlyMap] : [];
        }

        return [];
    }

    /**
     * @return array<int, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}>
     * @param array<int, string> $objects
     */
    private function fontObjectMaps(array $objects): array
    {
        $namedCMapBodies = $this->namedCMapBodies($objects);
        $fontObjectMaps = [];

        foreach ($objects as $objectNumber => $body) {
            if (!$this->bodyMayContainFontDictionary($body)) {
                continue;
            }

            $map = $this->fontMapFromFontBody($body, $objects, $namedCMapBodies);
            if ($map !== null) {
                $fontObjectMaps[$objectNumber] = $map;
            }
        }

        return $fontObjectMaps;
    }

    private function bodyMayContainFontDictionary(string $body): bool
    {
        return str_contains($body, '/Type /Font') || str_contains($body, '/Type/Font');
    }

    /**
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}|null
     * @param array<int, string> $objects
     * @param array<string, string> $namedCMapBodies
     */
    private function fontMapFromFontBody(string $body, array $objects, array $namedCMapBodies): ?array
    {
        $encodingFallback = $this->fontEncodingMap($body, $objects);
        $cidEncodingMap = $this->fontCidEncodingMap($body, $objects, $namedCMapBodies);
        $cMapWordSpacing = $cidEncodingMap !== null
            && $cidEncodingMap['cidMap'] !== [];
        $widthMetrics = $this->fontWidthMetrics($body, $objects);
        $descriptorInfo = $this->fontDescriptorInfo($body, $objects);
        $type3CharProcMap = $this->type3CharProcUnicodeMap($body, $objects, $cidEncodingMap);
        $cmap = null;
        if (preg_match('/\/ToUnicode\s+(\d+)\s+\d+\s+R\b/', $body, $match)) {
            $cmapObjectNumber = (int) $match[1];
            if (isset($objects[$cmapObjectNumber])) {
                $cmap = $this->toUnicodeMapFromObject($objects[$cmapObjectNumber], $objects, $namedCMapBodies);
            }
        }

        if (($cmap === null || ($cmap['map'] === [] && $cmap['codeSpaceRanges'] === [])) && $type3CharProcMap !== null) {
            $cmap = $type3CharProcMap;
        }

        if (($cmap === null || ($cmap['map'] === [] && $cmap['codeSpaceRanges'] === [])) && $encodingFallback !== null) {
            $cmap = $encodingFallback;
        }

        if ($cmap === null && $cidEncodingMap !== null && ($cidEncodingMap['cidMap'] !== [] || $cidEncodingMap['codeSpaceRanges'] !== [])) {
            $cmap = [
                'map' => [],
                'codeSpaceRanges' => $cidEncodingMap['codeSpaceRanges'],
            ];
            if (isset($cidEncodingMap['writingMode'])) {
                $cmap['writingMode'] = (int) $cidEncodingMap['writingMode'] === 1 ? 1 : 0;
            }
        }

        if ($cmap === null && $widthMetrics['widths'] !== [] && $this->isSimpleFontBody($body)) {
            $cmap = [
                'map' => [],
                'codeSpaceRanges' => [
                    ['start' => 0, 'end' => 255, 'width' => 2],
                ],
            ];
        }

        if ($cmap === null && ($descriptorInfo['name'] !== null || $descriptorInfo['flags'] !== null)) {
            $cmap = [
                'map' => [],
                'codeSpaceRanges' => [],
            ];
        }

        if ($cmap === null || ($cmap['map'] === [] && $cmap['codeSpaceRanges'] === [] && $descriptorInfo['name'] === null && $descriptorInfo['flags'] === null)) {
            return null;
        }

        $cmap = $this->withFontCidEncodingMap($cmap, $cidEncodingMap);
        if ($cMapWordSpacing) {
            $cmap['wordSpacingUsesCidMap'] = true;
        }
        $cmap = $this->withFontWidthMetrics($cmap, $widthMetrics, $this->fontWritingMode($body, $cmap, $cidEncodingMap, $objects));
        return $this->withFontDescriptorInfo($cmap, $descriptorInfo);
    }

    /**
     * @return array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}>
     * @param array<int, string> $objects
     * @param array<int, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontObjectMaps
     */
    private function pageFontToUnicodeMaps(int $pageObjectNumber, array $objects, array $fontObjectMaps): array
    {
        $resourceDictionary = $this->pageResourceDictionaryBody($pageObjectNumber, $objects);
        if ($resourceDictionary !== null) {
            return $this->fontResourceMapsFromResourceDictionary($resourceDictionary, $objects, $fontObjectMaps);
        }

        if ($this->pageResourceDictionaryBlocksFallback($pageObjectNumber, $objects)) {
            return [];
        }

        if (count($fontObjectMaps) === 1) {
            $onlyMap = reset($fontObjectMaps);
            return is_array($onlyMap) ? ['' => $onlyMap] : [];
        }

        return [];
    }

    /**
     * @return list<int>
     * @param array<int, string> $objects
     */
    private function pageObjectLineage(int $pageObjectNumber, array $objects): array
    {
        $lineage = [];
        $seen = [];
        $objectNumber = $pageObjectNumber;

        while (isset($objects[$objectNumber]) && !isset($seen[$objectNumber])) {
            $seen[$objectNumber] = true;
            $lineage[] = $objectNumber;

            $dictionary = $this->dictionaryObjectBody($objects[$objectNumber]);
            if ($dictionary === null) {
                break;
            }

            $parentValue = $this->topLevelPdfValueAfterNameInDictionaryBody($dictionary, 'Parent');
            if (
                $parentValue === null
                || preg_match('/^(\d+)\s+\d+\s+R\b/s', trim($parentValue), $match) !== 1
            ) {
                break;
            }

            $parentObjectNumber = (int) $match[1];
            if (!isset($objects[$parentObjectNumber]) || !$this->isPagesObject($objects[$parentObjectNumber])) {
                break;
            }

            $objectNumber = $parentObjectNumber;
        }

        return $lineage;
    }

    /**
     * @param array<int, string> $objects
     */
    private function pageResourceDictionaryBody(int $pageObjectNumber, array $objects): ?string
    {
        foreach ($this->pageObjectLineage($pageObjectNumber, $objects) as $objectNumber) {
            $resolution = $this->pageResourceDictionaryResolution($objects[$objectNumber], $objects);
            if ($resolution['state'] === 'resolved') {
                return $resolution['body'];
            }

            if ($resolution['state'] === 'blocked') {
                return null;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function pageResourceDictionaryBlocksFallback(int $pageObjectNumber, array $objects): bool
    {
        foreach ($this->pageObjectLineage($pageObjectNumber, $objects) as $objectNumber) {
            $resolution = $this->pageResourceDictionaryResolution($objects[$objectNumber], $objects);
            if ($resolution['state'] === 'resolved') {
                return false;
            }

            if ($resolution['state'] === 'blocked') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $objects
     * @return array{state: 'inherit'}|array{state: 'blocked'}|array{state: 'resolved', body: string}
     */
    private function pageResourceDictionaryResolution(string $objectBody, array $objects): array
    {
        $value = $this->topLevelPdfValueAfterName($objectBody, 'Resources');
        if ($value === null) {
            return ['state' => 'inherit'];
        }

        $value = trim($value);
        if ($value === '' || $value === 'null') {
            return ['state' => 'inherit'];
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/s', $value, $match) === 1) {
            $objectNumber = (int) $match[1];
            if (!isset($objects[$objectNumber])) {
                return ['state' => 'blocked'];
            }

            if (trim($objects[$objectNumber]) === 'null') {
                return ['state' => 'inherit'];
            }

            $body = $this->dictionaryObjectBody($objects[$objectNumber]);
            return $body === null
                ? ['state' => 'blocked']
                : ['state' => 'resolved', 'body' => $body];
        }

        if (str_starts_with($value, '<<')) {
            $body = $this->readPdfDictionaryAt($value, 0);
            return $body === null
                ? ['state' => 'blocked']
                : ['state' => 'resolved', 'body' => $body];
        }

        return ['state' => 'blocked'];
    }

    /**
     * @param array<int, string> $objects
     */
    private function resourceDictionaryBody(string $objectBody, array $objects): ?string
    {
        $value = $this->topLevelPdfValueAfterName($objectBody, 'Resources');
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '' || $value === 'null') {
            return null;
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/s', $value, $match) === 1) {
            $objectNumber = (int) $match[1];
            return isset($objects[$objectNumber]) ? $this->dictionaryObjectBody($objects[$objectNumber]) : null;
        }

        return str_starts_with($value, '<<') ? $this->readPdfDictionaryAt($value, 0) : null;
    }

    /**
     * @return array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}>
     * @param array<int, string> $objects
     * @param array<int, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontObjectMaps
     */
    private function fontResourceMapsFromResourceDictionary(string $resourceDictionary, array $objects, array $fontObjectMaps): array
    {
        $fontDictionary = $this->fontResourceDictionaryBody($resourceDictionary, $objects);
        if ($fontDictionary === null) {
            return [];
        }

        $maps = [];
        if (preg_match_all('/\/([^\s\[\]()<>{}\/%]+)\s+(\d+)\s+\d+\s+R\b/', $fontDictionary, $resourceMatches, PREG_SET_ORDER)) {
            foreach ($resourceMatches as $resourceMatch) {
                $fontObjectNumber = (int) $resourceMatch[2];
                if (isset($fontObjectMaps[$fontObjectNumber])) {
                    $maps[$this->decodePdfName($resourceMatch[1])] = $fontObjectMaps[$fontObjectNumber];
                }
            }
        }

        $namedCMapBodies = $this->namedCMapBodies($objects);
        foreach ($this->directFontResourceDictionaries($fontDictionary) as $name => $fontBody) {
            if (isset($maps[$name])) {
                continue;
            }

            $map = $this->fontMapFromFontBody($fontBody, $objects, $namedCMapBodies);
            if ($map !== null) {
                $maps[$name] = $map;
            }
        }

        return $maps;
    }

    /**
     * @return array<string, string>
     */
    private function directFontResourceDictionaries(string $fontDictionary): array
    {
        $fonts = [];
        $offset = 0;
        $length = strlen($fontDictionary);

        while ($offset < $length) {
            $this->skipContentWhitespaceAndComments($fontDictionary, $offset);
            if ($offset >= $length) {
                break;
            }

            if ($fontDictionary[$offset] !== '/') {
                $offset++;
                continue;
            }

            $nameStart = $offset + 1;
            $nameEnd = $nameStart;
            while ($nameEnd < $length && !str_contains(" \t\r\n\f[]()<>{}/%", $fontDictionary[$nameEnd])) {
                $nameEnd++;
            }
            if ($nameEnd === $nameStart) {
                $offset++;
                continue;
            }

            $name = $this->decodePdfName(substr($fontDictionary, $nameStart, $nameEnd - $nameStart));
            $valueOffset = $nameEnd;
            $this->skipContentWhitespaceAndComments($fontDictionary, $valueOffset);
            if ($valueOffset >= $length) {
                break;
            }

            if (preg_match('/\G\d+\s+\d+\s+R\b/s', $fontDictionary, $match, 0, $valueOffset) === 1) {
                $offset = $valueOffset + strlen($match[0]);
                continue;
            }

            if (substr($fontDictionary, $valueOffset, 2) !== '<<') {
                $nextOffset = $this->skipPdfValueAt($fontDictionary, $valueOffset);
                $offset = $nextOffset > $valueOffset ? $nextOffset : $valueOffset + 1;
                continue;
            }

            $dictionaryOffset = $valueOffset;
            $dictionary = $this->readPdfDictionaryTokenAt($fontDictionary, $dictionaryOffset);
            if ($dictionary === null) {
                $offset = $valueOffset + 2;
                continue;
            }

            if ($this->bodyMayContainFontDictionary($dictionary)) {
                $fonts[$name] = $dictionary;
            }
            $offset = $dictionaryOffset;
        }

        return $fonts;
    }

    /**
     * @return array<string, array{actualText: string|null, altText: string|null}>
     * @param array<int, string> $objects
     */
    private function markedContentPropertiesFromResourceDictionary(string $resourceDictionary, array $objects): array
    {
        $propertiesDictionary = $this->propertiesResourceDictionaryBody($resourceDictionary, $objects);
        if ($propertiesDictionary === null) {
            return [];
        }

        $properties = [];
        if (preg_match_all(
            '/\/([^\s\[\]()<>{}\/%]+)\s*(?:(\d+)\s+\d+\s+R|<<)/s',
            $propertiesDictionary,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        ) !== false) {
            foreach ($matches as $match) {
                $name = $this->decodePdfName($match[1][0]);
                if (($match[2][0] ?? '') !== '') {
                    $objectNumber = (int) $match[2][0];
                    $dictionary = isset($objects[$objectNumber]) ? $this->dictionaryObjectBody($objects[$objectNumber]) : null;
                } else {
                    $offset = strpos($propertiesDictionary, '<<', $match[0][1]);
                    $dictionary = $offset === false ? null : $this->readPdfDictionaryAt($propertiesDictionary, $offset);
                }

                if ($dictionary === null) {
                    continue;
                }

                $properties[$name] = [
                    'actualText' => $this->pdfOptionalStringValueAfterName($dictionary, 'ActualText', $objects),
                    'altText' => $this->pdfOptionalStringValueAfterName($dictionary, 'Alt', $objects),
                ];
            }
        }

        return $properties;
    }

    /**
     * @param array<int, string> $objects
     */
    private function propertiesResourceDictionaryBody(string $resourceDictionary, array $objects): ?string
    {
        return $this->resourceCategoryDictionaryBody($resourceDictionary, $objects, 'Properties');
    }

    /**
     * @param array<int, string> $objects
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}|null
     */
    private function fontEncodingMap(string $fontBody, array $objects = []): ?array
    {
        $encodingObjectNumber = $this->objectReferenceValueAfterName($fontBody, 'Encoding');
        if ($encodingObjectNumber !== null && isset($objects[$encodingObjectNumber])) {
            $encodingObject = trim($objects[$encodingObjectNumber]);
            if (preg_match('/^\/([^\s\[\]()<>{}\/%]+)/', $encodingObject, $match) === 1) {
                return $this->namedEncodingMap($this->decodePdfName($match[1]));
            }

            if (str_starts_with($encodingObject, '<<')) {
                $objectEncoding = $this->fontEncodingMap($encodingObject, $objects);
                if ($objectEncoding !== null) {
                    return $objectEncoding;
                }
            }
        }

        $baseEncoding = null;
        $baseEncodingName = $this->pdfNameValueAfterNameResolvingObjects($fontBody, 'BaseEncoding', $objects);
        if ($baseEncodingName !== null) {
            $baseEncoding = $this->namedEncodingMap($baseEncodingName);
        }

        $differencesArray = $this->pdfArrayValueAfterNameResolvingObjects($fontBody, 'Differences', $objects);
        if ($differencesArray !== null) {
            $differences = $this->encodingDifferencesMap($differencesArray);
            if ($baseEncoding !== null) {
                $baseEncoding['map'] = array_replace($baseEncoding['map'], $differences['map']);
                return $baseEncoding;
            }

            return $differences;
        }

        if ($baseEncoding !== null) {
            return $baseEncoding;
        }

        $encodingName = $this->pdfNameValueAfterNameResolvingObjects($fontBody, 'Encoding', $objects);
        if ($encodingName !== null) {
            return $this->namedEncodingMap($encodingName);
        }

        return $this->implicitBaseFontEncodingMap($fontBody);
    }

    /**
     * @return array{cidMap: array<string, int>, codeSpaceRanges: list<array{start: int, end: int, width: int}>, writingMode?: int}|null
     * @param array<int, string> $objects
     * @param array<string, string> $namedCMapBodies
     */
    private function fontCidEncodingMap(string $fontBody, array $objects, array $namedCMapBodies): ?array
    {
        if (preg_match('/\/Subtype\s*\/Type0\b/s', $fontBody) !== 1 && !$this->isType3FontBody($fontBody)) {
            return null;
        }

        if (preg_match('/\/Encoding\s+\/([^\s\[\]()<>{}\/%]+)/s', $fontBody, $nameMatch) === 1) {
            return $this->cidEncodingMapFromNamedCMap($this->decodePdfName($nameMatch[1]), $namedCMapBodies);
        }

        if (preg_match('/\/Encoding\s+(\d+)\s+\d+\s+R\b/s', $fontBody, $match) !== 1) {
            return null;
        }

        $encodingObjectNumber = (int) $match[1];
        $encodingObject = $objects[$encodingObjectNumber] ?? null;
        if ($encodingObject === null) {
            return null;
        }

        $decoded = $this->decodedCMapBody($encodingObject, $objects);
        if ($decoded === null) {
            $encodingObject = trim($encodingObject);
            if (preg_match('/^\/([^\s\[\]()<>{}\/%]+)/', $encodingObject, $nameMatch) === 1) {
                return $this->cidEncodingMapFromNamedCMap($this->decodePdfName($nameMatch[1]), $namedCMapBodies);
            }

            return null;
        }

        $currentName = $this->cMapName($decoded);

        return $this->parseCidCMap($decoded, $namedCMapBodies, $currentName === null ? [] : [$currentName]);
    }

    /**
     * @return array{cidMap: array<string, int>, codeSpaceRanges: list<array{start: int, end: int, width: int}>, writingMode?: int}|null
     * @param array<string, string> $namedCMapBodies
     */
    private function cidEncodingMapFromNamedCMap(string $encodingName, array $namedCMapBodies): ?array
    {
        $cmap = $namedCMapBodies[$encodingName] ?? null;
        if ($cmap !== null) {
            return $this->parseCidCMap($cmap, $namedCMapBodies, [$encodingName]);
        }

        return $this->predefinedCidCMap($encodingName);
    }

    /**
     * @return array{name: string|null, flags: int|null, weight: float|null}
     * @param array<int, string> $objects
     */
    private function fontDescriptorInfo(string $fontBody, array $objects): array
    {
        $name = $this->pdfNameValueAfterName($fontBody, 'BaseFont');
        $flags = null;
        $weight = null;

        foreach ([$fontBody, ...$this->descendantFontBodies($fontBody, $objects)] as $body) {
            $descriptor = $this->fontDescriptorBody($body, $objects);
            if ($descriptor === null) {
                continue;
            }

            $descriptorName = $this->pdfNameValueAfterNameResolvingObjects($descriptor, 'FontName', $objects);
            if ($descriptorName !== null && $descriptorName !== '') {
                $name = $descriptorName;
            }

            $descriptorFlags = $this->pdfIntegerValueAfterNameResolvingObjects($descriptor, 'Flags', $objects);
            if ($descriptorFlags !== null) {
                $flags = $descriptorFlags;
            }

            $descriptorWeight = $this->pdfNumberValueAfterNameResolvingObjects($descriptor, 'FontWeight', $objects);
            if ($descriptorWeight !== null) {
                $weight = $descriptorWeight;
            }
        }

        if ($weight === null && $flags !== null && ($flags & (1 << 18)) !== 0) {
            $weight = 700.0;
        }

        return [
            'name' => $name,
            'flags' => $flags,
            'weight' => $weight,
        ];
    }

    /**
     * @return array{widths: array<int, float>, defaultWidth: float|null, cidSet: array<int, true>|null, verticalDisplacements: array<int, float>, defaultVerticalDisplacement: float|null}
     * @param array<int, string> $objects
     */
    private function fontWidthMetrics(string $fontBody, array $objects): array
    {
        $widths = [];
        $defaultWidth = null;
        $cidSet = null;
        $hasWidthArray = false;
        $hasCidFontBody = false;
        $verticalDisplacements = [];
        $defaultVerticalDisplacement = null;
        $hasVerticalWidthArray = false;

        foreach ($this->type3CharProcWidths($fontBody, $objects) as $code => $width) {
            $widths[$code] = $width;
        }

        foreach ([$fontBody, ...$this->descendantFontBodies($fontBody, $objects)] as $body) {
            if ($this->isCidFontBody($body)) {
                $hasCidFontBody = true;
            }

            $simpleWidths = $this->simpleFontWidthMetrics($body, $objects);
            foreach ($simpleWidths as $code => $width) {
                $widths[$code] = $width;
            }

            if ($this->isSimpleFontBody($body)) {
                $missingWidth = $this->fontDescriptorMissingWidth($body, $objects);
                if ($missingWidth !== null) {
                    $defaultWidth = $missingWidth;
                } elseif ($simpleWidths !== [] && !$this->isType3FontBody($body)) {
                    $defaultWidth = $this->averagePositiveFontWidth($simpleWidths) ?? $defaultWidth;
                }
            }

            $widthArray = $this->pdfArrayValueAfterNameResolvingObjects($body, 'W', $objects);
            if ($widthArray !== null) {
                $hasWidthArray = true;
                foreach ($this->cidWidthsFromWArray($widthArray, $objects) as $cid => $width) {
                    $widths[$cid] = $width;
                }
            }

            $bodyDefaultWidth = $this->pdfNumberValueAfterNameResolvingObjects($body, 'DW', $objects);
            if ($bodyDefaultWidth !== null) {
                $defaultWidth = $bodyDefaultWidth;
            }

            $verticalWidthArray = $this->pdfArrayValueAfterNameResolvingObjects($body, 'W2', $objects);
            if ($verticalWidthArray !== null) {
                $hasVerticalWidthArray = true;
                foreach ($this->cidVerticalDisplacementsFromW2Array($verticalWidthArray, $objects) as $cid => $displacement) {
                    $verticalDisplacements[$cid] = $displacement;
                }
            }

            $verticalDefaultMetrics = $this->pdfArrayValueAfterNameResolvingObjects($body, 'DW2', $objects);
            if ($verticalDefaultMetrics !== null) {
                $metrics = $this->numbersFromPdfArrayResolvingObjects($verticalDefaultMetrics, $objects);
                if (count($metrics) >= 2) {
                    $defaultVerticalDisplacement = (float) $metrics[1];
                }
            }

            $bodyCidSet = $this->cidSetFromFontBody($body, $objects);
            if ($bodyCidSet !== null) {
                $cidSet = $cidSet === null ? $bodyCidSet : ($cidSet + $bodyCidSet);
            }
        }

        if (($hasCidFontBody || $hasWidthArray || $cidSet !== null) && $defaultWidth === null) {
            $defaultWidth = 1000.0;
        }
        if ($hasVerticalWidthArray && $defaultVerticalDisplacement === null) {
            $defaultVerticalDisplacement = -1000.0;
        }

        return [
            'widths' => $widths,
            'defaultWidth' => $defaultWidth,
            'cidSet' => $cidSet,
            'verticalDisplacements' => $verticalDisplacements,
            'defaultVerticalDisplacement' => $defaultVerticalDisplacement,
        ];
    }

    /**
     * @return array<int, float>
     * @param array<int, string> $objects
     */
    private function type3CharProcWidths(string $fontBody, array $objects): array
    {
        if (preg_match('/\/Subtype\s*\/Type3\b/', $fontBody) !== 1) {
            return [];
        }

        $glyphNamesByCode = $this->type3EncodingGlyphNamesByCode($fontBody, $objects);
        $charProcObjectReferences = $this->charProcObjectReferences($fontBody, $objects);
        if ($glyphNamesByCode === [] || $charProcObjectReferences === []) {
            return [];
        }

        $widths = [];
        foreach ($glyphNamesByCode as $code => $glyphName) {
            $reference = $charProcObjectReferences[$glyphName] ?? null;
            if ($reference === null) {
                continue;
            }

            $objectBody = $this->objectBodyForExactReference($objects, $reference['objectNumber'], $reference['generation']);
            if ($objectBody === null) {
                continue;
            }

            $width = $this->type3CharProcDeclaredWidth($objectBody, $objects);
            if ($width !== null) {
                $widths[$code] = $width;
            }
        }

        return $widths;
    }

    /**
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}|null
     * @param array<int, string> $objects
     * @param array{cidMap: array<string, int>, codeSpaceRanges: list<array{start: int, end: int, width: int}>, writingMode?: int}|null $cidEncodingMap
     */
    private function type3CharProcUnicodeMap(string $fontBody, array $objects, ?array $cidEncodingMap): ?array
    {
        if (!$this->isType3FontBody($fontBody) || preg_match('/\/ToUnicode\b/s', $fontBody) === 1) {
            return null;
        }

        $unicodeByGlyphName = $this->type3StandardCharProcUnicodeByName($fontBody, $objects);
        if ($unicodeByGlyphName === null) {
            return null;
        }

        $map = [];
        foreach ($this->type3EncodingGlyphNamesByCode($fontBody, $objects) as $code => $glyphName) {
            if (!isset($unicodeByGlyphName[$glyphName])) {
                continue;
            }

            $map[str_pad(strtolower(dechex($code)), 2, '0', STR_PAD_LEFT)] = $unicodeByGlyphName[$glyphName];
        }

        $codeSpaceRanges = [
            ['start' => 0, 'end' => 255, 'width' => 2],
        ];
        if ($cidEncodingMap !== null && $cidEncodingMap['codeSpaceRanges'] !== []) {
            $codeSpaceRanges = $cidEncodingMap['codeSpaceRanges'];
        }

        if ($cidEncodingMap !== null && $cidEncodingMap['cidMap'] !== []) {
            $unicodeByCodepoint = [];
            foreach ($unicodeByGlyphName as $unicode) {
                $codepoint = $this->singleUnicodeCodepoint($unicode);
                if ($codepoint !== null) {
                    $unicodeByCodepoint[$codepoint] = $unicode;
                }
            }

            foreach ($cidEncodingMap['cidMap'] as $sourceKey => $cid) {
                if (isset($unicodeByCodepoint[$cid])) {
                    $map[$this->normalizeHexKey((string) $sourceKey)] = $unicodeByCodepoint[$cid];
                }
            }
        }

        if ($map === []) {
            return null;
        }

        return [
            'map' => $map,
            'codeSpaceRanges' => $codeSpaceRanges,
        ];
    }

    /**
     * @return array<string, string>|null
     * @param array<int, string> $objects
     */
    private function type3StandardCharProcUnicodeByName(string $fontBody, array $objects): ?array
    {
        $charProcObjectReferences = $this->charProcObjectReferences($fontBody, $objects);
        if ($charProcObjectReferences === []) {
            return null;
        }

        $unicodeByName = [];
        foreach (array_keys($charProcObjectReferences) as $glyphName) {
            $unicode = $this->glyphNameToUnicode($glyphName);
            if ($unicode === '') {
                return null;
            }

            $unicodeByName[$glyphName] = $unicode;
        }

        return $unicodeByName;
    }

    /**
     * @return array<int, string>
     * @param array<int, string> $objects
     */
    private function type3EncodingGlyphNamesByCode(string $fontBody, array $objects): array
    {
        $glyphNames = $this->baseEncodingGlyphNamesByCode($fontBody, $objects);
        $differences = $this->encodingDifferencesGlyphNames($fontBody, $objects);

        if ($glyphNames === []) {
            $glyphNames = $this->namedEncodingGlyphNamesByCode('StandardEncoding');
        }

        return $differences === [] ? $glyphNames : array_replace($glyphNames, $differences);
    }

    /**
     * @return array<int, string>
     * @param array<int, string> $objects
     */
    private function baseEncodingGlyphNamesByCode(string $fontBody, array $objects): array
    {
        $encodingObjectNumber = $this->objectReferenceValueAfterName($fontBody, 'Encoding');
        if ($encodingObjectNumber !== null && isset($objects[$encodingObjectNumber])) {
            $encodingObject = trim($objects[$encodingObjectNumber]);
            if (preg_match('/^\/([^\s\[\]()<>{}\/%]+)/', $encodingObject, $match) === 1) {
                return $this->namedEncodingGlyphNamesByCode($this->decodePdfName($match[1]));
            }

            if (str_starts_with($encodingObject, '<<')) {
                return $this->baseEncodingGlyphNamesByCode($encodingObject, $objects);
            }
        }

        $baseEncodingName = $this->pdfNameValueAfterNameResolvingObjects($fontBody, 'BaseEncoding', $objects);
        if ($baseEncodingName !== null) {
            return $this->namedEncodingGlyphNamesByCode($baseEncodingName);
        }

        $encodingName = $this->pdfNameValueAfterNameResolvingObjects($fontBody, 'Encoding', $objects);
        if ($encodingName !== null) {
            return $this->namedEncodingGlyphNamesByCode($encodingName);
        }

        return [];
    }

    /**
     * @return array<int, string>
     * @param array<int, string> $objects
     */
    private function encodingDifferencesGlyphNames(string $fontBody, array $objects = []): array
    {
        $encodingObjectNumber = $this->objectReferenceValueAfterName($fontBody, 'Encoding');
        if ($encodingObjectNumber !== null && isset($objects[$encodingObjectNumber])) {
            $encodingObject = trim($objects[$encodingObjectNumber]);
            if (str_starts_with($encodingObject, '<<')) {
                $objectGlyphNames = $this->encodingDifferencesGlyphNames($encodingObject, $objects);
                if ($objectGlyphNames !== []) {
                    return $objectGlyphNames;
                }
            }
        }

        $differences = $this->pdfArrayValueAfterNameResolvingObjects($fontBody, 'Differences', $objects);
        if ($differences === null) {
            return [];
        }

        preg_match_all('/\/[^\s\[\]()<>{}\/%]+|[+-]?\d+/', $differences, $tokens);
        $glyphNames = [];
        $code = null;
        foreach ($tokens[0] ?? [] as $token) {
            if (preg_match('/^[+-]?\d+$/', $token) === 1) {
                $code = max(0, min(255, (int) $token));
                continue;
            }

            if ($code === null || !str_starts_with($token, '/')) {
                continue;
            }

            $glyphNames[$code] = $this->decodePdfName(substr($token, 1));
            $code++;
        }

        return $glyphNames;
    }

    /**
     * @return array<int, string>
     */
    private function namedEncodingGlyphNamesByCode(string $encodingName): array
    {
        if (!in_array($encodingName, ['WinAnsiEncoding', 'StandardEncoding', 'MacRomanEncoding'], true)) {
            return [];
        }

        $glyphNames = [
            0x20 => 'space',
            0x21 => 'exclam',
            0x22 => 'quotedbl',
            0x23 => 'numbersign',
            0x24 => 'dollar',
            0x25 => 'percent',
            0x26 => 'ampersand',
            0x27 => 'quotesingle',
            0x28 => 'parenleft',
            0x29 => 'parenright',
            0x2a => 'asterisk',
            0x2b => 'plus',
            0x2c => 'comma',
            0x2d => 'hyphen',
            0x2e => 'period',
            0x2f => 'slash',
            0x3a => 'colon',
            0x3b => 'semicolon',
            0x3c => 'less',
            0x3d => 'equal',
            0x3e => 'greater',
            0x3f => 'question',
            0x40 => 'at',
            0x5b => 'bracketleft',
            0x5c => 'backslash',
            0x5d => 'bracketright',
            0x5e => 'asciicircum',
            0x5f => 'underscore',
            0x60 => 'grave',
            0x7b => 'braceleft',
            0x7c => 'bar',
            0x7d => 'braceright',
            0x7e => 'asciitilde',
        ];

        for ($code = 0x30; $code <= 0x39; $code++) {
            $glyphNames[$code] = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine'][$code - 0x30];
        }
        for ($code = 0x41; $code <= 0x5a; $code++) {
            $glyphNames[$code] = chr($code);
        }
        for ($code = 0x61; $code <= 0x7a; $code++) {
            $glyphNames[$code] = chr($code);
        }

        return $glyphNames;
    }

    /**
     * @return array<string, array{objectNumber: int, generation: int}>
     * @param array<int, string> $objects
     */
    private function charProcObjectReferences(string $fontBody, array $objects): array
    {
        $dictionary = $this->charProcsDictionaryBody($fontBody, $objects);
        if ($dictionary === null) {
            return [];
        }

        $references = [];
        $offset = 0;
        $length = strlen($dictionary);
        while ($offset < $length) {
            $this->skipContentWhitespaceAndComments($dictionary, $offset);
            if ($offset >= $length) {
                break;
            }

            if ($dictionary[$offset] !== '/') {
                $nextOffset = $this->skipPdfValueAt($dictionary, $offset);
                $offset = $nextOffset > $offset ? $nextOffset : $offset + 1;
                continue;
            }

            $nameStart = $offset + 1;
            $nameEnd = $nameStart;
            while ($nameEnd < $length && !str_contains(" \t\r\n\f[]()<>{}/%", $dictionary[$nameEnd])) {
                $nameEnd++;
            }

            if ($nameEnd === $nameStart) {
                $offset++;
                continue;
            }

            $valueOffset = $nameEnd;
            $this->skipContentWhitespaceAndComments($dictionary, $valueOffset);
            if ($valueOffset >= $length) {
                break;
            }

            if (preg_match('/\G(\d+)\s+(\d+)\s+R\b/s', $dictionary, $match, 0, $valueOffset) === 1) {
                $references[$this->decodePdfName(substr($dictionary, $nameStart, $nameEnd - $nameStart))] = [
                    'objectNumber' => (int) $match[1],
                    'generation' => (int) $match[2],
                ];
                $offset = $valueOffset + strlen($match[0]);
                continue;
            }

            $nextOffset = $this->skipPdfValueAt($dictionary, $valueOffset);
            $offset = $nextOffset > $valueOffset ? $nextOffset : $valueOffset + 1;
        }

        return $references;
    }

    /**
     * @param array<int, string> $objects
     */
    private function charProcsDictionaryBody(string $fontBody, array $objects): ?string
    {
        $offset = $this->topLevelNameValueOffset($fontBody, 'CharProcs');
        if ($offset === null) {
            return null;
        }

        if (preg_match('/\G(\d+)\s+(\d+)\s+R\b/s', $fontBody, $match, 0, $offset) === 1) {
            $objectBody = $this->objectBodyForExactReference(
                $objects,
                (int) $match[1],
                (int) $match[2]
            );

            return $objectBody === null ? null : $this->dictionaryObjectBody($objectBody);
        }

        $offset = $this->skipPdfWhitespace($fontBody, $offset);
        return substr($fontBody, $offset, 2) === '<<' ? $this->readPdfDictionaryAt($fontBody, $offset) : null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function type3CharProcDeclaredWidth(string $objectBody, array $objects): ?float
    {
        $charProc = $this->decodeStreamObject($objectBody, $objects) ?? trim($objectBody);
        $operands = [];

        foreach ($this->contentTokens($charProc) as $token) {
            if ($token === 'd0') {
                if (count($operands) < 2) {
                    return null;
                }

                return $this->numericOperand($operands[count($operands) - 2]);
            }

            if ($token === 'd1') {
                if (count($operands) < 6) {
                    return null;
                }

                return $this->numericOperand($operands[count($operands) - 6]);
            }

            if ($this->isOperator($token)) {
                $operands = [];
                continue;
            }

            $operands[] = $token;
        }

        return null;
    }

    /**
     * @return list<string>
     * @param array<int, string> $objects
     */
    private function descendantFontBodies(string $fontBody, array $objects): array
    {
        $descendantFonts = $this->pdfArrayValueAfterNameResolvingObjects($fontBody, 'DescendantFonts', $objects);
        if ($descendantFonts === null) {
            return [];
        }

        $bodies = [];
        foreach ($this->objectReferences($descendantFonts) as $objectNumber) {
            if (isset($objects[$objectNumber])) {
                $bodies[] = $objects[$objectNumber];
            }
        }

        $offset = 0;
        while (($offset = strpos($descendantFonts, '<<', $offset)) !== false) {
            $dictionary = $this->readPdfDictionaryAt($descendantFonts, $offset);
            $dictionaryEnd = $this->pdfDictionaryEndOffset($descendantFonts, $offset);
            if ($dictionary === null || $dictionaryEnd === null) {
                break;
            }
            $bodies[] = $dictionary;
            $offset = $dictionaryEnd + 1;
        }

        return $bodies;
    }

    /**
     * @return array<int, float>
     * @param array<int, string> $objects
     */
    private function simpleFontWidthMetrics(string $fontBody, array $objects): array
    {
        if (!$this->isSimpleFontBody($fontBody)) {
            return [];
        }

        $explicitWidths = $this->simpleFontExplicitWidths($fontBody, $objects);
        if ($explicitWidths !== []) {
            return $explicitWidths;
        }

        return $this->base14FontWidthMetrics($fontBody);
    }

    private function isSimpleFontBody(string $fontBody): bool
    {
        if (preg_match('/\/Subtype\s*\/Type0\b/s', $fontBody) === 1 || $this->isCidFontBody($fontBody)) {
            return false;
        }

        if (preg_match('/\/Subtype\s*\/(?:Type1|MMType1|TrueType)\b/s', $fontBody) === 1 || $this->isType3FontBody($fontBody)) {
            return true;
        }

        return $this->pdfNameValueAfterName($fontBody, 'BaseFont') !== null
            && $this->pdfArrayValueAfterName($fontBody, 'DescendantFonts') === null;
    }

    private function isCidFontBody(string $fontBody): bool
    {
        return preg_match('/\/Subtype\s*\/CIDFontType[02]\b/s', $fontBody) === 1;
    }

    private function isType3FontBody(string $fontBody): bool
    {
        return preg_match('/\/Subtype\s*\/Type3\b/s', $fontBody) === 1;
    }

    /**
     * @return array<int, float>
     * @param array<int, string> $objects
     */
    private function simpleFontExplicitWidths(string $fontBody, array $objects): array
    {
        $firstChar = $this->pdfNumberValueAfterNameResolvingObjects($fontBody, 'FirstChar', $objects);
        if ($firstChar === null) {
            return [];
        }

        $widthArray = $this->pdfArrayValueAfterNameResolvingObjects($fontBody, 'Widths', $objects);
        if ($widthArray === null) {
            return [];
        }

        $widths = [];
        foreach ($this->nullableNumbersFromPdfArrayResolvingObjects($widthArray, $objects) as $offset => $width) {
            if ($width === null) {
                continue;
            }
            $code = (int) $firstChar + $offset;
            if ($code >= 0 && $code <= 255) {
                $widths[$code] = $width;
            }
        }

        return $widths;
    }

    /**
     * @param array<int, float> $widths
     */
    private function averagePositiveFontWidth(array $widths): ?float
    {
        $sum = 0.0;
        $count = 0;
        foreach ($widths as $width) {
            if ($width <= 0.0) {
                continue;
            }

            $sum += $width;
            $count++;
        }

        return $count === 0 ? null : $sum / $count;
    }

    /**
     * @return list<float>
     * @param array<int, string> $objects
     */
    private function numbersFromPdfArrayResolvingObjects(string $arrayBody, array $objects): array
    {
        $numbers = [];
        foreach ($this->pdfArrayItems($arrayBody) as $item) {
            $number = $this->pdfNumberValueAt($item, 0, $objects);
            if ($number !== null) {
                $numbers[] = $number;
            }
        }

        return $numbers;
    }

    /**
     * @return list<float|null>
     * @param array<int, string> $objects
     */
    private function nullableNumbersFromPdfArrayResolvingObjects(string $arrayBody, array $objects): array
    {
        $numbers = [];
        foreach ($this->pdfArrayItems($arrayBody) as $item) {
            $numbers[] = $this->pdfNumberValueAt($item, 0, $objects);
        }

        return $numbers;
    }

    /**
     * @return array<int, float>
     */
    private function base14FontWidthMetrics(string $fontBody): array
    {
        $baseFont = $this->pdfNameValueAfterName($fontBody, 'BaseFont');
        if ($baseFont === null || $baseFont === '') {
            return [];
        }

        if (preg_match('/^[A-Z]{6}\+(.+)$/', $baseFont, $match) === 1) {
            $baseFont = $match[1];
        }

        $metricName = self::BASE14_FONT_WIDTH_ALIASES[$baseFont] ?? null;
        if ($metricName === null) {
            return [];
        }

        if ($metricName === 'Courier') {
            return array_fill_keys(range(32, 126), 600.0);
        }

        $widthString = self::BASE14_ASCII_WIDTHS[$metricName] ?? null;
        if ($widthString === null) {
            return [];
        }

        $widths = [];
        foreach (explode(' ', $widthString) as $offset => $width) {
            $code = 32 + $offset;
            if ($code > 126) {
                break;
            }
            $widths[$code] = (float) $width;
        }

        return $widths;
    }

    /**
     * @param array<int, string> $objects
     * @return array<int, float>
     */
    private function cidWidthsFromWArray(string $arrayBody, array $objects): array
    {
        $tokens = $this->pdfArrayItems($arrayBody);
        $widths = [];

        for ($index = 0, $count = count($tokens); $index < $count;) {
            $firstCid = $this->cidWidthArrayInteger($tokens[$index] ?? '', $objects);
            if ($firstCid === null) {
                $index++;
                continue;
            }
            $index++;

            $next = $tokens[$index] ?? null;
            if ($next === null) {
                break;
            }

            if (str_starts_with(trim($next), '[')) {
                foreach ($this->nullableNumbersFromPdfArrayResolvingObjects(substr(trim($next), 1, -1), $objects) as $offset => $width) {
                    if ($width === null) {
                        continue;
                    }
                    $cid = $firstCid + $offset;
                    if ($cid >= 0 && $cid <= 0xffff) {
                        $widths[$cid] = (float) $width;
                    }
                }
                $index++;
                continue;
            }

            $lastCid = $this->cidWidthArrayInteger($next, $objects);
            $width = $this->pdfNumberValueAt($tokens[$index + 1] ?? '', 0, $objects);
            if ($lastCid === null || $width === null) {
                $index++;
                continue;
            }

            $index += 2;
            if ($firstCid < 0 || $lastCid < $firstCid) {
                continue;
            }

            for ($cid = $firstCid, $limit = min($lastCid, 0xffff); $cid <= $limit; $cid++) {
                $widths[$cid] = (float) $width;
            }
        }

        return $widths;
    }

    /**
     * @param array<int, string> $objects
     */
    private function cidWidthArrayInteger(string $item, array $objects): ?int
    {
        $value = $this->pdfNumberValueAt($item, 0, $objects);
        if ($value === null) {
            return null;
        }

        $integer = (int) round($value);
        return abs($value - $integer) > 0.000001 ? null : $integer;
    }

    /**
     * @return array<int, float>
     * @param array<int, string> $objects
     */
    private function cidVerticalDisplacementsFromW2Array(string $arrayBody, array $objects): array
    {
        $tokens = $this->pdfArrayItems($arrayBody);
        $displacements = [];

        for ($index = 0, $count = count($tokens); $index < $count;) {
            $firstCid = $this->cidWidthArrayInteger($tokens[$index] ?? '', $objects);
            if ($firstCid === null) {
                $index++;
                continue;
            }
            $index++;

            $next = $tokens[$index] ?? null;
            if ($next === null) {
                break;
            }

            if (str_starts_with(trim($next), '[')) {
                $metrics = $this->nullableNumbersFromPdfArrayResolvingObjects(substr(trim($next), 1, -1), $objects);
                for ($offset = 0, $metricCount = count($metrics); $offset + 2 < $metricCount; $offset += 3) {
                    if ($metrics[$offset] === null || $metrics[$offset + 1] === null || $metrics[$offset + 2] === null) {
                        continue;
                    }
                    $cid = $firstCid + intdiv($offset, 3);
                    if ($cid >= 0 && $cid <= 0xffff) {
                        $displacements[$cid] = (float) $metrics[$offset];
                    }
                }
                $index++;
                continue;
            }

            $lastCid = $this->cidWidthArrayInteger($next, $objects);
            $verticalDisplacement = $this->pdfNumberValueAt($tokens[$index + 1] ?? '', 0, $objects);
            $positionX = $this->pdfNumberValueAt($tokens[$index + 2] ?? '', 0, $objects);
            $positionY = $this->pdfNumberValueAt($tokens[$index + 3] ?? '', 0, $objects);
            if ($lastCid === null || $verticalDisplacement === null || $positionX === null || $positionY === null) {
                $index++;
                continue;
            }

            $index += 4;
            if ($firstCid < 0 || $lastCid < $firstCid) {
                continue;
            }

            for ($cid = $firstCid, $limit = min($lastCid, 0xffff); $cid <= $limit; $cid++) {
                $displacements[$cid] = (float) $verticalDisplacement;
            }
        }

        return $displacements;
    }

    /**
     * @return array<int, true>|null
     * @param array<int, string> $objects
     */
    private function cidSetFromFontBody(string $fontBody, array $objects): ?array
    {
        $isCMapEncodedType3 = $this->isType3FontBody($fontBody) && $this->fontHasDecodedCMapEncoding($fontBody, $objects);
        if (!$this->isCidFontBody($fontBody) && !$isCMapEncodedType3) {
            return null;
        }

        $descriptor = $this->fontDescriptorBody($fontBody, $objects);
        if ($descriptor === null) {
            return null;
        }

        $cidSetReference = $this->objectReferenceAfterName($descriptor, 'CIDSet');
        if ($cidSetReference === null) {
            return null;
        }

        $cidSetObject = $this->indirectObjectBodyForReference(
            $objects,
            $cidSetReference['objectNumber'],
            $cidSetReference['generation']
        );
        if ($cidSetObject === null) {
            return null;
        }

        $decoded = $this->decodeStreamObject($cidSetObject, $objects);
        if ($decoded === null || $decoded === '') {
            return null;
        }

        return $this->cidSetBits($decoded);
    }

    /**
     * @param array<int, string> $objects
     */
    private function fontHasDecodedCMapEncoding(string $fontBody, array $objects): bool
    {
        $encodingObjectNumber = $this->objectReferenceValueAfterName($fontBody, 'Encoding');
        if ($encodingObjectNumber === null || !isset($objects[$encodingObjectNumber])) {
            return false;
        }

        return $this->decodedCMapBody($objects[$encodingObjectNumber], $objects) !== null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function fontDescriptorBody(string $fontBody, array $objects): ?string
    {
        $descriptorObjectNumber = $this->objectReferenceValueAfterName($fontBody, 'FontDescriptor');
        if ($descriptorObjectNumber !== null) {
            return $objects[$descriptorObjectNumber] ?? null;
        }

        if (preg_match('/\/FontDescriptor\s*<</s', $fontBody, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $offset = strpos($fontBody, '<<', $match[0][1]);
        return $offset === false ? null : $this->readPdfDictionaryAt($fontBody, $offset);
    }

    /**
     * @param array<int, string> $objects
     */
    private function fontDescriptorMissingWidth(string $fontBody, array $objects): ?float
    {
        $descriptor = $this->fontDescriptorBody($fontBody, $objects);
        if ($descriptor === null) {
            return null;
        }

        return $this->pdfNumberValueAfterNameResolvingObjects($descriptor, 'MissingWidth', $objects);
    }

    /**
     * @return array<int, true>|null
     */
    private function cidSetBits(string $bytes): ?array
    {
        $cids = [];
        for ($byteIndex = 0, $length = strlen($bytes); $byteIndex < $length; $byteIndex++) {
            $byte = ord($bytes[$byteIndex]);
            for ($bit = 0; $bit < 8; $bit++) {
                if (($byte & (1 << (7 - $bit))) === 0) {
                    continue;
                }

                $cid = ($byteIndex * 8) + $bit;
                if ($cid > 0xffff) {
                    break 2;
                }

                $cids[$cid] = true;
            }
        }

        return $cids === [] ? null : $cids;
    }

    private function integerToken(string $token): ?int
    {
        if (preg_match('/^[+-]?\d+$/', $token) !== 1) {
            return null;
        }

        return (int) $token;
    }

    /**
     * @param array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>} $map
     * @param array{widths: array<int, float>, defaultWidth: float|null, cidSet: array<int, true>|null, verticalDisplacements: array<int, float>, defaultVerticalDisplacement: float|null} $metrics
     * @return array<string, mixed>
     */
    private function withFontWidthMetrics(array $map, array $metrics, int $writingMode): array
    {
        if (
            $writingMode === 0
            && $metrics['widths'] === []
            && $metrics['defaultWidth'] === null
            && $metrics['cidSet'] === null
            && $metrics['verticalDisplacements'] === []
            && $metrics['defaultVerticalDisplacement'] === null
        ) {
            return $map;
        }

        $map['writingMode'] = $writingMode;
        $map['cidWidths'] = $metrics['widths'];
        $map['cidDefaultWidth'] = $metrics['defaultWidth'];
        $map['cidVerticalDisplacements'] = $metrics['verticalDisplacements'];
        $map['cidDefaultVerticalDisplacement'] = $writingMode === 1
            ? ($metrics['defaultVerticalDisplacement'] ?? -1000.0)
            : $metrics['defaultVerticalDisplacement'];
        if ($metrics['cidSet'] !== null) {
            $map['cidSet'] = $metrics['cidSet'];
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $map
     * @param array{cidMap: array<string, int>, codeSpaceRanges: list<array{start: int, end: int, width: int}>, writingMode?: int}|null $cidEncodingMap
     * @return array<string, mixed>
     */
    private function withFontCidEncodingMap(array $map, ?array $cidEncodingMap): array
    {
        if ($cidEncodingMap === null) {
            return $map;
        }

        if ($cidEncodingMap['cidMap'] !== []) {
            $map['cidMap'] = $cidEncodingMap['cidMap'];
        }
        if ($cidEncodingMap['codeSpaceRanges'] !== []) {
            $map['cidCodeSpaceRanges'] = $cidEncodingMap['codeSpaceRanges'];
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $map
     * @param array{name: string|null, flags: int|null, weight: float|null} $info
     * @return array<string, mixed>
     */
    private function withFontDescriptorInfo(array $map, array $info): array
    {
        if ($info['name'] !== null) {
            $map['fontName'] = $info['name'];
        }
        if ($info['flags'] !== null) {
            $map['fontFlags'] = $info['flags'];
        }
        if ($info['weight'] !== null) {
            $map['fontWeight'] = $info['weight'];
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $cmap
     * @param array{cidMap: array<string, int>, codeSpaceRanges: list<array{start: int, end: int, width: int}>, writingMode?: int}|null $cidEncodingMap
     */
    private function fontWritingMode(string $fontBody, array $cmap, ?array $cidEncodingMap = null, array $objects = []): int
    {
        $encodingName = $this->pdfNameValueAfterNameResolvingObjects($fontBody, 'Encoding', $objects);
        if ($encodingName !== null) {
            $writingMode = $this->cMapNameWritingMode($encodingName);
            if ($writingMode !== null) {
                return $writingMode;
            }
        }

        if ($cidEncodingMap !== null && array_key_exists('writingMode', $cidEncodingMap)) {
            return (int) $cidEncodingMap['writingMode'] === 1 ? 1 : 0;
        }

        return $this->mapWritingMode($cmap);
    }

    private function cMapNameWritingMode(string $encodingName): ?int
    {
        if ($encodingName === 'Identity-V' || str_ends_with($encodingName, '-V')) {
            return 1;
        }

        if ($encodingName === 'Identity-H' || str_ends_with($encodingName, '-H')) {
            return 0;
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function fontResourceDictionaryBody(string $resourceDictionary, array $objects): ?string
    {
        return $this->resourceCategoryDictionaryBody($resourceDictionary, $objects, 'Font');
    }

    /**
     * @param array<int, string> $objects
     */
    private function resourceCategoryDictionaryBody(string $resourceDictionary, array $objects, string $category): ?string
    {
        $dictionary = trim($resourceDictionary);
        if (str_starts_with($dictionary, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($dictionary, 0);
            if ($dictionary === null) {
                return null;
            }
        }

        $value = $this->topLevelPdfValueAfterNameInDictionaryBody($dictionary, $category);
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if (preg_match('/^(\d+)\s+\d+\s+R\b/s', $value, $match) === 1) {
            $objectNumber = (int) $match[1];
            return isset($objects[$objectNumber]) ? $this->dictionaryObjectBody($objects[$objectNumber]) : null;
        }

        return str_starts_with($value, '<<') ? $this->readPdfDictionaryAt($value, 0) : null;
    }

    private function dictionaryObjectBody(string $objectBody): ?string
    {
        $offset = strpos($objectBody, '<<');
        return $offset === false ? null : $this->readPdfDictionaryAt($objectBody, $offset);
    }

    private function readPdfDictionaryAt(string $value, int $offset): ?string
    {
        return $this->readPdfDictionaryTokenAt($value, $offset);
    }

    private function readPdfDictionaryTokenAt(string $value, int &$offset): ?string
    {
        if (substr($value, $offset, 2) !== '<<') {
            return null;
        }

        $depth = 0;
        $bodyStart = $offset + 2;
        $index = $offset;
        $length = strlen($value);
        while ($index < $length - 1) {
            if ($value[$index] === '%') {
                $this->skipPdfComment($value, $index);
                continue;
            }

            if ($value[$index] === '(') {
                $this->readLiteralToken($value, $index);
                continue;
            }

            if ($value[$index] === '<' && $value[$index + 1] !== '<') {
                $this->readHexToken($value, $index);
                continue;
            }

            $pair = substr($value, $index, 2);
            if ($pair === '<<') {
                $depth++;
                $index += 2;
                continue;
            }

            if ($pair === '>>') {
                $depth--;
                $index += 2;
                if ($depth === 0) {
                    $offset = $index;
                    return substr($value, $bodyStart, $index - 2 - $bodyStart);
                }
                continue;
            }

            $index++;
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function pdfStringValueAfterName(string $body, string $name, array $objects): ?string
    {
        $offset = $this->nameValueOffset($body, $name);
        if ($offset === null) {
            return null;
        }

        return $this->pdfStringTokenAt($body, $offset, $objects);
    }

    /**
     * @param array<int, string> $objects
     */
    private function pdfOptionalStringValueAfterName(string $body, string $name, array $objects): ?string
    {
        $offset = $this->nameValueOffset($body, $name);
        if ($offset === null) {
            return null;
        }

        return $this->pdfStringTokenAt($body, $offset, $objects);
    }

    private function pdfArrayValueAfterName(string $body, string $name): ?string
    {
        $offset = $this->nameValueOffset($body, $name);
        if ($offset === null) {
            return null;
        }

        return $this->readPdfArrayAt($body, $offset);
    }

    /**
     * @param array<int, string> $objects
     */
    private function pdfArrayValueAfterNameResolvingObjects(string $body, string $name, array $objects): ?string
    {
        $direct = $this->pdfArrayValueAfterName($body, $name);
        if ($direct !== null) {
            return $direct;
        }

        $objectNumber = $this->objectReferenceValueAfterName($body, $name);
        if ($objectNumber === null || !isset($objects[$objectNumber])) {
            return null;
        }

        return $this->pdfArrayAtStart(trim($objects[$objectNumber]));
    }

    /**
     * @return list<float>|null
     * @param array<int, string> $objects
     */
    private function pdfMatrixValueAfterName(string $body, string $name, array $objects): ?array
    {
        $arrayBody = $this->pdfArrayValueAfterNameResolvingObjects($body, $name, $objects);
        if ($arrayBody === null) {
            return null;
        }

        $numbers = $this->numbersFromPdfArray($arrayBody);
        if (count($numbers) < 6) {
            return null;
        }

        return array_slice($numbers, 0, 6);
    }

    /**
     * @return list<float>|null
     * @param array<int, string> $objects
     */
    private function pdfRectangleValueAfterName(string $body, string $name, array $objects): ?array
    {
        $arrayBody = $this->pdfArrayValueAfterNameResolvingObjects($body, $name, $objects);
        if ($arrayBody === null) {
            return null;
        }

        $numbers = $this->numbersFromPdfArray($arrayBody);
        if (count($numbers) < 4) {
            return null;
        }

        return [
            min($numbers[0], $numbers[2]),
            min($numbers[1], $numbers[3]),
            max($numbers[0], $numbers[2]),
            max($numbers[1], $numbers[3]),
        ];
    }

    /**
     * @param list<float> $left
     * @param list<float> $right
     * @return list<float>
     */
    private function pdfMatrixMultiply(array $left, array $right): array
    {
        return [
            ($left[0] * $right[0]) + ($left[2] * $right[1]),
            ($left[1] * $right[0]) + ($left[3] * $right[1]),
            ($left[0] * $right[2]) + ($left[2] * $right[3]),
            ($left[1] * $right[2]) + ($left[3] * $right[3]),
            ($left[0] * $right[4]) + ($left[2] * $right[5]) + $left[4],
            ($left[1] * $right[4]) + ($left[3] * $right[5]) + $left[5],
        ];
    }

    /**
     * @param list<float> $matrix
     */
    private function pdfMatrixIsIdentity(array $matrix): bool
    {
        return count($matrix) >= 6
            && abs($matrix[0] - 1.0) < 0.000001
            && abs($matrix[1]) < 0.000001
            && abs($matrix[2]) < 0.000001
            && abs($matrix[3] - 1.0) < 0.000001
            && abs($matrix[4]) < 0.000001
            && abs($matrix[5]) < 0.000001;
    }

    /**
     * @param list<float> $rectangle
     */
    private function pdfPointInsideRectangle(float $x, float $y, array $rectangle): bool
    {
        return $x >= $rectangle[0] - 0.000001
            && $x <= $rectangle[2] + 0.000001
            && $y >= $rectangle[1] - 0.000001
            && $y <= $rectangle[3] + 0.000001;
    }

    /**
     * @param list<float> $rectangle
     */
    private function pdfRectangleHasArea(array $rectangle): bool
    {
        return count($rectangle) >= 4
            && $rectangle[2] > $rectangle[0] + 0.000001
            && $rectangle[3] > $rectangle[1] + 0.000001;
    }

    /**
     * @param list<string> $operands
     * @param list<float>|null $clipRectangle
     * @param list<float>|null $currentPathRectangle
     * @param list<float> $currentTransformationMatrix
     */
    private function applyClipPathStateOperator(
        string $operator,
        array $operands,
        ?array &$clipRectangle,
        ?array &$currentPathRectangle,
        array &$currentTransformationMatrix
    ): bool {
        if ($operator === 'cm') {
            $matrix = $this->contentMatrixOperand($operands);
            if ($matrix !== null) {
                $currentTransformationMatrix = $this->pdfMatrixMultiply($currentTransformationMatrix, $matrix);
            }

            return true;
        }

        if ($operator === 're') {
            $rectangle = $this->rectanglePathOperand($operands, $currentTransformationMatrix);
            if ($rectangle !== null) {
                $currentPathRectangle = $this->pdfRectangleUnion($currentPathRectangle, $rectangle);
            }

            return true;
        }

        if ($operator === 'W' || $operator === 'W*') {
            if ($currentPathRectangle !== null) {
                $clipRectangle = $this->pdfRectangleIntersection($clipRectangle, $currentPathRectangle);
            }

            return true;
        }

        if ($this->pathOperatorClearsCurrentPath($operator)) {
            $currentPathRectangle = null;

            return true;
        }

        return false;
    }

    /**
     * @param list<string> $operands
     * @param list<float> $matrix
     * @return list<float>|null
     */
    private function rectanglePathOperand(array $operands, array $matrix): ?array
    {
        if (count($operands) < 4) {
            return null;
        }

        $numbers = [];
        foreach (array_slice($operands, -4) as $operand) {
            $number = $this->numericOperand($operand);
            if ($number === null) {
                return null;
            }
            $numbers[] = $number;
        }

        [$x, $y, $width, $height] = $numbers;
        if (abs($width) < 0.000001 || abs($height) < 0.000001) {
            return null;
        }

        $points = [
            $this->pdfMatrixTransformPoint($matrix, $x, $y),
            $this->pdfMatrixTransformPoint($matrix, $x + $width, $y),
            $this->pdfMatrixTransformPoint($matrix, $x, $y + $height),
            $this->pdfMatrixTransformPoint($matrix, $x + $width, $y + $height),
        ];
        $xs = array_column($points, 0);
        $ys = array_column($points, 1);

        return [
            min($xs),
            min($ys),
            max($xs),
            max($ys),
        ];
    }

    /**
     * @param list<float> $matrix
     * @return array{0: float, 1: float}
     */
    private function pdfMatrixTransformPoint(array $matrix, float $x, float $y): array
    {
        return [
            ($matrix[0] * $x) + ($matrix[2] * $y) + $matrix[4],
            ($matrix[1] * $x) + ($matrix[3] * $y) + $matrix[5],
        ];
    }

    /**
     * @param list<float> $matrix
     * @param list<float> $rectangle
     * @return list<float>
     */
    private function pdfRectangleTransform(array $matrix, array $rectangle): array
    {
        $points = [
            $this->pdfMatrixTransformPoint($matrix, $rectangle[0], $rectangle[1]),
            $this->pdfMatrixTransformPoint($matrix, $rectangle[2], $rectangle[1]),
            $this->pdfMatrixTransformPoint($matrix, $rectangle[0], $rectangle[3]),
            $this->pdfMatrixTransformPoint($matrix, $rectangle[2], $rectangle[3]),
        ];
        $xs = array_column($points, 0);
        $ys = array_column($points, 1);

        return [
            min($xs),
            min($ys),
            max($xs),
            max($ys),
        ];
    }

    /**
     * @param list<float>|null $left
     * @param list<float> $right
     * @return list<float>
     */
    private function pdfRectangleUnion(?array $left, array $right): array
    {
        if ($left === null) {
            return $right;
        }

        return [
            min($left[0], $right[0]),
            min($left[1], $right[1]),
            max($left[2], $right[2]),
            max($left[3], $right[3]),
        ];
    }

    /**
     * @param list<float>|null $clip
     * @param list<float> $path
     * @return list<float>
     */
    private function pdfRectangleIntersection(?array $clip, array $path): array
    {
        if ($clip === null) {
            return $path;
        }

        return [
            max($clip[0], $path[0]),
            max($clip[1], $path[1]),
            min($clip[2], $path[2]),
            min($clip[3], $path[3]),
        ];
    }

    /**
     * @param list<float>|null $clipRectangle
     */
    private function textPositionInsideActiveClip(?float $x, ?float $y, ?array $clipRectangle): bool
    {
        if ($clipRectangle === null) {
            return true;
        }

        return $this->pdfPointInsideRectangle($x ?? 0.0, $y ?? 0.0, $clipRectangle);
    }

    private function pathOperatorClearsCurrentPath(string $operator): bool
    {
        return in_array($operator, ['n', 'S', 's', 'f', 'F', 'f*', 'B', 'B*', 'b', 'b*'], true);
    }

    private function pdfNumberValueAfterName(string $body, string $name): ?float
    {
        $offset = $this->nameValueOffset($body, $name);
        if ($offset === null || preg_match('/\G([+-]?(?:\d+(?:\.\d*)?|\.\d+))/s', $body, $match, 0, $offset) !== 1) {
            return null;
        }

        return (float) $match[1];
    }

    /**
     * @param array<int, string> $objects
     */
    private function pdfNumberValueAfterNameResolvingObjects(string $body, string $name, array $objects): ?float
    {
        $offset = $this->nameValueOffset($body, $name);
        if ($offset === null) {
            return null;
        }

        return $this->pdfNumberValueAt($body, $offset, $objects);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function pdfNumberValueAt(string $value, int $offset, array $objects, array $seen = []): ?float
    {
        $offset = $this->skipPdfWhitespace($value, $offset);
        if (preg_match('/\G(\d+)\s+(\d+)\s+R\b/s', $value, $match, 0, $offset) === 1) {
            $objectNumber = (int) $match[1];
            $generation = (int) $match[2];
            $objectKey = $objectNumber . ':' . $generation;
            if ($objectNumber <= 0 || isset($seen[$objectKey])) {
                return null;
            }

            $body = $this->indirectObjectBodyForReference($objects, $objectNumber, $generation);
            if ($body === null) {
                return null;
            }

            $seen[$objectKey] = true;
            return $this->pdfNumberValueAt(trim($body), 0, $objects, $seen);
        }

        if (preg_match('/\G([+-]?(?:\d+(?:\.\d*)?|\.\d+))/s', $value, $match, 0, $offset) !== 1) {
            return null;
        }

        return (float) $match[1];
    }

    private function pdfIntegerValueAfterName(string $body, string $name): ?int
    {
        $offset = $this->nameValueOffset($body, $name);
        if ($offset === null || preg_match('/\G([+-]?\d+)/s', $body, $match, 0, $offset) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    /**
     * @param array<int, string> $objects
     */
    private function pdfIntegerValueAfterNameResolvingObjects(string $body, string $name, array $objects): ?int
    {
        $offset = $this->nameValueOffset($body, $name);
        if ($offset === null) {
            return null;
        }

        return $this->streamLengthValueAt($body, $offset, $objects);
    }

    private function objectReferenceValueAfterName(string $body, string $name): ?int
    {
        $reference = $this->objectReferenceAfterName($body, $name);
        return $reference === null ? null : $reference['objectNumber'];
    }

    /**
     * @return array{objectNumber: int, generation: int}|null
     */
    private function objectReferenceAfterName(string $body, string $name): ?array
    {
        $offset = $this->nameValueOffset($body, $name);
        if ($offset === null) {
            return null;
        }

        if (preg_match('/\G(\d+)\s+(\d+)\s+R\b/s', $body, $match, 0, $offset) !== 1) {
            return null;
        }

        return [
            'objectNumber' => (int) $match[1],
            'generation' => (int) $match[2],
        ];
    }

    private function pdfNameValueAfterName(string $body, string $name): ?string
    {
        $offset = $this->nameValueOffset($body, $name);
        if ($offset === null) {
            return null;
        }

        return $this->pdfNameValueAt($body, $offset, []);
    }

    /**
     * @param array<int, string> $objects
     */
    private function pdfNameValueAfterNameResolvingObjects(string $body, string $name, array $objects): ?string
    {
        $offset = $this->nameValueOffset($body, $name);
        if ($offset === null) {
            return null;
        }

        return $this->pdfNameValueAt($body, $offset, $objects);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function pdfNameValueAt(string $value, int $offset, array $objects, array $seen = []): ?string
    {
        $offset = $this->skipPdfWhitespace($value, $offset);
        if (preg_match('/\G(\d+)\s+(\d+)\s+R\b/s', $value, $match, 0, $offset) === 1) {
            $objectNumber = (int) $match[1];
            $generation = (int) $match[2];
            $objectKey = $objectNumber . ':' . $generation;
            if ($objectNumber <= 0 || isset($seen[$objectKey])) {
                return null;
            }

            $body = $this->indirectObjectBodyForReference($objects, $objectNumber, $generation);
            if ($body === null) {
                return null;
            }

            $seen[$objectKey] = true;
            return $this->pdfNameValueAt(trim($body), 0, $objects, $seen);
        }

        if (($value[$offset] ?? '') !== '/') {
            return null;
        }

        $end = $offset + 1;
        while ($end < strlen($value) && !str_contains(" \t\r\n\f[]()<>{}/%", $value[$end])) {
            $end++;
        }

        return $this->decodePdfName(substr($value, $offset + 1, $end - $offset - 1));
    }

    private function pdfValueAfterName(string $body, string $name): ?string
    {
        $offset = $this->nameValueOffset($body, $name);
        if ($offset === null || $offset >= strlen($body)) {
            return null;
        }

        return $this->pdfValueAtOffset($body, $offset);
    }

    private function topLevelPdfValueAfterName(string $body, string $name): ?string
    {
        $dictionary = $this->dictionaryObjectBody($body) ?? $body;

        return $this->topLevelPdfValueAfterNameInDictionaryBody($dictionary, $name);
    }

    private function topLevelPdfValueAfterNameInDictionaryBody(string $dictionary, string $name): ?string
    {
        $offset = 0;
        $length = strlen($dictionary);

        while ($offset < $length) {
            $this->skipContentWhitespaceAndComments($dictionary, $offset);
            if ($offset >= $length) {
                break;
            }

            if ($dictionary[$offset] !== '/') {
                $nextOffset = $this->skipPdfValueAt($dictionary, $offset);
                $offset = $nextOffset > $offset ? $nextOffset : $offset + 1;
                continue;
            }

            $keyStart = $offset + 1;
            $keyEnd = $keyStart;
            while ($keyEnd < $length && !str_contains(" \t\r\n\f[]()<>{}/%", $dictionary[$keyEnd])) {
                $keyEnd++;
            }

            if ($keyEnd === $keyStart) {
                $offset++;
                continue;
            }

            $key = $this->decodePdfName(substr($dictionary, $keyStart, $keyEnd - $keyStart));
            $valueOffset = $keyEnd;
            $this->skipContentWhitespaceAndComments($dictionary, $valueOffset);
            if ($valueOffset >= $length) {
                return null;
            }

            if ($key === $name) {
                return $this->pdfValueAtOffset($dictionary, $valueOffset);
            }

            $nextOffset = $this->skipPdfValueAt($dictionary, $valueOffset);
            $offset = $nextOffset > $valueOffset ? $nextOffset : $valueOffset + 1;
        }

        return null;
    }

    private function skipPdfValueAt(string $body, int $offset): int
    {
        $length = strlen($body);
        if ($offset < 0 || $offset >= $length) {
            return $length;
        }

        if ($body[$offset] === '[') {
            $array = $this->readPdfArrayAt($body, $offset);
            return $array === null ? $offset + 1 : $offset + strlen($array) + 2;
        }

        if (substr($body, $offset, 2) === '<<') {
            $end = $this->pdfDictionaryEndOffset($body, $offset);
            return $end === null ? $offset + 2 : $end + 1;
        }

        if ($body[$offset] === '(') {
            $end = $this->skipPdfLiteralStringAt($body, $offset);
            return $end === null ? $offset + 1 : $end + 1;
        }

        if ($body[$offset] === '<') {
            $end = strpos($body, '>', $offset + 1);
            return $end === false ? $length : $end + 1;
        }

        if (preg_match('/\G\d+\s+\d+\s+R\b/s', $body, $match, 0, $offset) === 1) {
            return $offset + strlen($match[0]);
        }

        if ($body[$offset] === '/') {
            $end = $offset + 1;
            while ($end < $length && !str_contains(" \t\r\n\f[]()<>{}/%", $body[$end])) {
                $end++;
            }

            return $end;
        }

        $end = $offset;
        while ($end < $length && !ctype_space($body[$end]) && !str_contains('[]()<>{}/%', $body[$end])) {
            $end++;
        }

        return $end === $offset ? $offset + 1 : $end;
    }

    /**
     * @return list<string>
     */
    private function pdfArrayItems(string $arrayBody): array
    {
        $items = [];
        $index = 0;
        $length = strlen($arrayBody);

        while ($index < $length) {
            $this->skipContentWhitespaceAndComments($arrayBody, $index);
            if ($index >= $length) {
                break;
            }

            if (preg_match('/\G\d+\s+\d+\s+R\b/s', $arrayBody, $match, 0, $index) === 1) {
                $items[] = $match[0];
                $index += strlen($match[0]);
                continue;
            }

            $char = $arrayBody[$index];
            if (substr($arrayBody, $index, 2) === '<<') {
                $items[] = $this->readDictionaryToken($arrayBody, $index);
                continue;
            }

            if ($char === '[') {
                $items[] = $this->readArrayToken($arrayBody, $index);
                continue;
            }

            if ($char === '(') {
                $items[] = $this->readLiteralToken($arrayBody, $index);
                continue;
            }

            if ($char === '<') {
                $items[] = $this->readHexToken($arrayBody, $index);
                continue;
            }

            if ($char === '/') {
                $items[] = $this->readNameToken($arrayBody, $index);
                continue;
            }

            $start = $index;
            while ($index < $length && !$this->isDelimiter($arrayBody[$index])) {
                $index++;
            }

            if ($index === $start) {
                $index++;
                continue;
            }

            $items[] = substr($arrayBody, $start, $index - $start);
        }

        return array_values(array_filter($items, static fn (string $item): bool => trim($item) !== ''));
    }

    private function nameValueOffset(string $body, string $name): ?int
    {
        return $this->pdfNameValueOffset($body, $name, false);
    }

    private function topLevelNameValueOffset(string $body, string $name): ?int
    {
        return $this->pdfNameValueOffset($body, $name, true);
    }

    private function pdfNameValueOffset(string $body, string $name, bool $topLevelOnly): ?int
    {
        $length = strlen($body);
        $firstTokenOffset = 0;
        $this->skipContentWhitespaceAndComments($body, $firstTokenOffset);
        $targetDictionaryDepth = substr($body, $firstTokenOffset, 2) === '<<' ? 1 : 0;
        $dictionaryDepth = 0;
        $arrayDepth = 0;
        $index = 0;

        while ($index < $length) {
            $char = $body[$index];

            if ($char === '%') {
                $this->skipPdfComment($body, $index);
                continue;
            }

            if ($char === '(') {
                $this->readLiteralToken($body, $index);
                continue;
            }

            if ($char === '<') {
                if (($body[$index + 1] ?? '') === '<') {
                    $dictionaryDepth++;
                    $index += 2;
                    continue;
                }

                $this->readHexToken($body, $index);
                continue;
            }

            if ($char === '>' && ($body[$index + 1] ?? '') === '>') {
                $dictionaryDepth = max(0, $dictionaryDepth - 1);
                $index += 2;
                continue;
            }

            if ($char === '[') {
                $arrayDepth++;
                $index++;
                continue;
            }

            if ($char === ']') {
                $arrayDepth = max(0, $arrayDepth - 1);
                $index++;
                continue;
            }

            if ($char !== '/') {
                $index++;
                continue;
            }

            $nameStart = $index + 1;
            $nameEnd = $nameStart;
            while ($nameEnd < $length && !str_contains(" \t\r\n\f[]()<>{}/%", $body[$nameEnd])) {
                $nameEnd++;
            }

            if (
                (!$topLevelOnly || ($arrayDepth === 0 && $dictionaryDepth === $targetDictionaryDepth))
                && $nameEnd > $nameStart
                && $this->decodePdfName(substr($body, $nameStart, $nameEnd - $nameStart)) === $name
            ) {
                $valueOffset = $nameEnd;
                $this->skipContentWhitespaceAndComments($body, $valueOffset);
                return $valueOffset;
            }

            $index = $nameEnd > $index ? $nameEnd : $index + 1;
        }

        return null;
    }

    private function skipPdfWhitespace(string $value, int $offset): int
    {
        $length = strlen($value);
        while ($offset < $length) {
            if (ctype_space($value[$offset])) {
                $offset++;
                continue;
            }

            if ($value[$offset] === '%') {
                $this->skipPdfComment($value, $offset);
                continue;
            }

            break;
        }

        return $offset;
    }

    /**
     * @param array<int, string> $objects
     */
    private function pdfStringTokenAt(string $body, int $offset, array $objects): ?string
    {
        $offset = $this->skipPdfWhitespace($body, $offset);
        if ($offset >= strlen($body)) {
            return null;
        }

        $char = $body[$offset];
        if ($char === '(') {
            $raw = $this->readPdfLiteralStringAt($body, $offset);
            return $raw === null ? null : $this->decodePdfStringBytes($this->decodeLiteralString($raw));
        }

        if ($char === '<' && substr($body, $offset, 2) !== '<<') {
            $bytes = $this->readPdfHexStringAt($body, $offset);
            return $bytes === null ? null : $this->decodePdfStringBytes($bytes);
        }

        if (preg_match('/\G(\d+)\s+(\d+)\s+R\b/s', $body, $match, 0, $offset) === 1) {
            $objectNumber = (int) $match[1];
            $generation = (int) $match[2];
            $objectBody = $this->indirectObjectBodyForReference($objects, $objectNumber, $generation);
            return $objectBody === null
                ? null
                : $this->pdfStringTokenAt(trim($objectBody), 0, $objects);
        }

        if ($char === '/') {
            $end = strcspn($body, " \t\r\n\f[]()<>{}/%", $offset + 1);
            return $this->decodePdfName(substr($body, $offset + 1, $end));
        }

        return null;
    }

    private function readPdfLiteralStringAt(string $value, int $offset): ?string
    {
        if ($offset >= strlen($value) || $value[$offset] !== '(') {
            return null;
        }

        $depth = 0;
        $raw = '';
        for ($index = $offset + 1, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '\\') {
                if ($index + 1 < $length) {
                    $raw .= $char . $value[$index + 1];
                    $index++;
                    continue;
                }

                $raw .= $char;
                continue;
            }

            if ($char === '(') {
                $depth++;
                $raw .= $char;
                continue;
            }

            if ($char === ')') {
                if ($depth === 0) {
                    return $raw;
                }
                $depth--;
                $raw .= $char;
                continue;
            }

            $raw .= $char;
        }

        return null;
    }

    private function readPdfHexStringAt(string $value, int $offset): ?string
    {
        $end = strpos($value, '>', $offset + 1);
        if ($end === false) {
            return null;
        }

        $hex = preg_replace('/\s+/', '', substr($value, $offset + 1, $end - $offset - 1));
        if ($hex === null || preg_match('/^[\da-fA-F]*$/', $hex) !== 1) {
            return null;
        }

        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        $bytes = hex2bin($hex);
        return $bytes === false ? null : $bytes;
    }

    private function pdfArrayAtStart(string $value): ?string
    {
        return str_starts_with($value, '[') ? $this->readPdfArrayAt($value, 0) : null;
    }

    private function readPdfArrayAt(string $value, int $offset): ?string
    {
        if ($offset >= strlen($value) || $value[$offset] !== '[') {
            return null;
        }

        $depth = 0;
        $bodyStart = $offset + 1;
        for ($index = $offset, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '%') {
                $this->skipPdfComment($value, $index);
                continue;
            }

            if ($char === '(') {
                $skipped = $this->skipPdfLiteralStringAt($value, $index);
                if ($skipped === null) {
                    return null;
                }
                $index = $skipped;
                continue;
            }

            if ($char === '<' && substr($value, $index, 2) === '<<') {
                $dictionaryEnd = $this->pdfDictionaryEndOffset($value, $index);
                if ($dictionaryEnd === null) {
                    return null;
                }
                $index = $dictionaryEnd;
                continue;
            }

            if ($char === '<') {
                $hexEnd = strpos($value, '>', $index + 1);
                if ($hexEnd === false) {
                    return null;
                }
                $index = $hexEnd;
                continue;
            }

            if ($char === '[') {
                $depth++;
                continue;
            }

            if ($char !== ']') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                return substr($value, $bodyStart, $index - $bodyStart);
            }
        }

        return null;
    }

    private function skipPdfLiteralStringAt(string $value, int $offset): ?int
    {
        if ($offset >= strlen($value) || $value[$offset] !== '(') {
            return null;
        }

        $depth = 0;
        for ($index = $offset + 1, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '\\') {
                $index++;
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                if ($depth === 0) {
                    return $index;
                }
                $depth--;
            }
        }

        return null;
    }

    private function pdfDictionaryEndOffset(string $value, int $offset): ?int
    {
        if (substr($value, $offset, 2) !== '<<') {
            return null;
        }

        $depth = 0;
        for ($index = $offset, $length = strlen($value); $index < $length - 1; $index++) {
            if ($value[$index] === '%') {
                $this->skipPdfComment($value, $index);
                continue;
            }

            if ($value[$index] === '(') {
                $skipped = $this->skipPdfLiteralStringAt($value, $index);
                if ($skipped === null) {
                    return null;
                }
                $index = $skipped;
                continue;
            }

            $pair = substr($value, $index, 2);
            if ($pair === '<<') {
                $depth++;
                $index++;
                continue;
            }

            if ($pair !== '>>') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                return $index + 1;
            }
            $index++;
        }

        return null;
    }

    private function firstObjectReference(string $value): ?int
    {
        $references = $this->objectReferencePairs($value);
        return $references[0]['objectNumber'] ?? null;
    }

    /**
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}
     */
    private function encodingDifferencesMap(string $differences): array
    {
        preg_match_all('/\/[^\s\[\]()<>{}\/%]+|[+-]?\d+/', $differences, $matches);
        $map = [];
        $code = null;

        foreach ($matches[0] ?? [] as $token) {
            if (preg_match('/^[+-]?\d+$/', $token) === 1) {
                $code = max(0, min(255, (int) $token));
                continue;
            }

            if ($code === null || !str_starts_with($token, '/')) {
                continue;
            }

            $glyph = $this->glyphNameToUnicode($this->decodePdfName(substr($token, 1)));
            if ($glyph !== '') {
                $map[str_pad(strtolower(dechex($code)), 2, '0', STR_PAD_LEFT)] = $glyph;
            }
            $code++;
        }

        return [
            'map' => $map,
            'codeSpaceRanges' => [
                ['start' => 0, 'end' => 255, 'width' => 2],
            ],
        ];
    }

    /**
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}|null
     */
    private function namedEncodingMap(string $encodingName): ?array
    {
        if ($encodingName === 'Identity-H' || $encodingName === 'Identity-V') {
            return [
                'map' => [],
                'codeSpaceRanges' => [
                    ['start' => 0, 'end' => 0xffff, 'width' => 4],
                ],
                'writingMode' => $encodingName === 'Identity-V' ? 1 : 0,
            ];
        }

        if ($this->isPredefinedUcs2CMapName($encodingName)) {
            return [
                'map' => [],
                'codeSpaceRanges' => [
                    ['start' => 0, 'end' => 0xffff, 'width' => 4],
                ],
                'writingMode' => str_ends_with($encodingName, '-V') ? 1 : 0,
            ];
        }

        return match ($encodingName) {
            'WinAnsiEncoding' => $this->codepointEncodingMap([
                0x80 => 0x20ac,
                0x82 => 0x201a,
                0x83 => 0x0192,
                0x84 => 0x201e,
                0x85 => 0x2026,
                0x86 => 0x2020,
                0x87 => 0x2021,
                0x88 => 0x02c6,
                0x89 => 0x2030,
                0x8a => 0x0160,
                0x8b => 0x2039,
                0x8c => 0x0152,
                0x8e => 0x017d,
                0x91 => 0x2018,
                0x92 => 0x2019,
                0x93 => 0x201c,
                0x94 => 0x201d,
                0x95 => 0x2022,
                0x96 => 0x2013,
                0x97 => 0x2014,
                0x98 => 0x02dc,
                0x99 => 0x2122,
                0x9a => 0x0161,
                0x9b => 0x203a,
                0x9c => 0x0153,
                0x9e => 0x017e,
                0x9f => 0x0178,
            ]),
            'StandardEncoding' => $this->codepointEncodingMap([
                0x27 => 0x2019,
                0x60 => 0x2018,
                0xa1 => 0x00a1,
                0xa2 => 0x00a2,
                0xa3 => 0x00a3,
                0xa4 => 0x2044,
                0xa5 => 0x00a5,
                0xa6 => 0x0192,
                0xa7 => 0x00a7,
                0xa8 => 0x00a4,
                0xa9 => 0x0027,
                0xaa => 0x201c,
                0xab => 0x00ab,
                0xac => 0x2039,
                0xad => 0x203a,
                0xae => 0xfb01,
                0xaf => 0xfb02,
                0xb1 => 0x2013,
                0xb2 => 0x2020,
                0xb3 => 0x2021,
                0xb4 => 0x00b7,
                0xb6 => 0x00b6,
                0xb7 => 0x2022,
                0xb8 => 0x201a,
                0xb9 => 0x201e,
                0xba => 0x201d,
                0xbb => 0x00bb,
                0xbc => 0x2026,
                0xbd => 0x2030,
                0xbf => 0x00bf,
                0xc1 => 0x0060,
                0xc2 => 0x00b4,
                0xc3 => 0x02c6,
                0xc4 => 0x02dc,
                0xc5 => 0x00af,
                0xc6 => 0x02d8,
                0xc7 => 0x02d9,
                0xc8 => 0x00a8,
                0xca => 0x02da,
                0xcb => 0x00b8,
                0xcd => 0x02dd,
                0xce => 0x02db,
                0xcf => 0x02c7,
                0xd0 => 0x2014,
                0xe1 => 0x00c6,
                0xe3 => 0x00aa,
                0xe8 => 0x0141,
                0xe9 => 0x00d8,
                0xea => 0x0152,
                0xeb => 0x00ba,
                0xf1 => 0x00e6,
                0xf5 => 0x0131,
                0xf8 => 0x0142,
                0xf9 => 0x00f8,
                0xfa => 0x0153,
                0xfb => 0x00df,
            ]),
            'MacRomanEncoding' => $this->codepointEncodingMap([
                0x80 => 0x00c4,
                0x81 => 0x00c5,
                0x82 => 0x00c7,
                0x83 => 0x00c9,
                0x84 => 0x00d1,
                0x85 => 0x00d6,
                0x86 => 0x00dc,
                0x87 => 0x00e1,
                0x88 => 0x00e0,
                0x89 => 0x00e2,
                0x8a => 0x00e4,
                0x8b => 0x00e3,
                0x8c => 0x00e5,
                0x8d => 0x00e7,
                0x8e => 0x00e9,
                0x8f => 0x00e8,
                0x90 => 0x00ea,
                0x91 => 0x00eb,
                0x92 => 0x00ed,
                0x93 => 0x00ec,
                0x94 => 0x00ee,
                0x95 => 0x00ef,
                0x96 => 0x00f1,
                0x97 => 0x00f3,
                0x98 => 0x00f2,
                0x99 => 0x00f4,
                0x9a => 0x00f6,
                0x9b => 0x00f5,
                0x9c => 0x00fa,
                0x9d => 0x00f9,
                0x9e => 0x00fb,
                0x9f => 0x00fc,
                0xa0 => 0x2020,
                0xa1 => 0x00b0,
                0xa2 => 0x00a2,
                0xa3 => 0x00a3,
                0xa4 => 0x00a7,
                0xa5 => 0x2022,
                0xa6 => 0x00b6,
                0xa7 => 0x00df,
                0xa8 => 0x00ae,
                0xa9 => 0x00a9,
                0xaa => 0x2122,
                0xab => 0x00b4,
                0xac => 0x00a8,
                0xae => 0x00c6,
                0xaf => 0x00d8,
                0xb1 => 0x00b1,
                0xb4 => 0x00a5,
                0xb5 => 0x00b5,
                0xbb => 0x00aa,
                0xbc => 0x00ba,
                0xbe => 0x00e6,
                0xbf => 0x00f8,
                0xc0 => 0x00bf,
                0xc1 => 0x00a1,
                0xc2 => 0x00ac,
                0xc4 => 0x0192,
                0xc7 => 0x00ab,
                0xc8 => 0x00bb,
                0xc9 => 0x2026,
                0xca => 0x0020,
                0xcb => 0x00c0,
                0xcc => 0x00c3,
                0xcd => 0x00d5,
                0xce => 0x0152,
                0xcf => 0x0153,
                0xd0 => 0x2013,
                0xd1 => 0x2014,
                0xd2 => 0x201c,
                0xd3 => 0x201d,
                0xd4 => 0x2018,
                0xd5 => 0x2019,
                0xd6 => 0x00f7,
                0xd8 => 0x00ff,
                0xd9 => 0x0178,
                0xda => 0x2044,
                0xdb => 0x00a4,
                0xdc => 0x2039,
                0xdd => 0x203a,
                0xde => 0xfb01,
                0xdf => 0xfb02,
                0xe0 => 0x2021,
                0xe1 => 0x00b7,
                0xe2 => 0x201a,
                0xe3 => 0x201e,
                0xe4 => 0x2030,
                0xe5 => 0x00c2,
                0xe6 => 0x00ca,
                0xe7 => 0x00c1,
                0xe8 => 0x00cb,
                0xe9 => 0x00c8,
                0xea => 0x00cd,
                0xeb => 0x00ce,
                0xec => 0x00cf,
                0xed => 0x00cc,
                0xee => 0x00d3,
                0xef => 0x00d4,
                0xf1 => 0x00d2,
                0xf2 => 0x00da,
                0xf3 => 0x00db,
                0xf4 => 0x00d9,
                0xf5 => 0x0131,
                0xf6 => 0x02c6,
                0xf7 => 0x02dc,
                0xf8 => 0x00af,
                0xf9 => 0x02d8,
                0xfa => 0x02d9,
                0xfb => 0x02da,
                0xfc => 0x00b8,
                0xfd => 0x02dd,
                0xfe => 0x02db,
                0xff => 0x02c7,
            ]),
            'SymbolEncoding' => $this->codepointEncodingMap([
                0x22 => 0x2200,
                0x24 => 0x2203,
                0x27 => 0x220b,
                0x2a => 0x2217,
                0x2d => 0x2212,
                0x40 => 0x2245,
                0x41 => 0x0391,
                0x42 => 0x0392,
                0x43 => 0x03a7,
                0x44 => 0x0394,
                0x45 => 0x0395,
                0x46 => 0x03a6,
                0x47 => 0x0393,
                0x48 => 0x0397,
                0x49 => 0x0399,
                0x4a => 0x03d1,
                0x4b => 0x039a,
                0x4c => 0x039b,
                0x4d => 0x039c,
                0x4e => 0x039d,
                0x4f => 0x039f,
                0x50 => 0x03a0,
                0x51 => 0x0398,
                0x52 => 0x03a1,
                0x53 => 0x03a3,
                0x54 => 0x03a4,
                0x55 => 0x03a5,
                0x56 => 0x03c2,
                0x57 => 0x03a9,
                0x58 => 0x039e,
                0x59 => 0x03a8,
                0x5a => 0x0396,
                0x5c => 0x2234,
                0x5e => 0x22a5,
                0x61 => 0x03b1,
                0x62 => 0x03b2,
                0x63 => 0x03c7,
                0x64 => 0x03b4,
                0x65 => 0x03b5,
                0x66 => 0x03c6,
                0x67 => 0x03b3,
                0x68 => 0x03b7,
                0x69 => 0x03b9,
                0x6a => 0x03d5,
                0x6b => 0x03ba,
                0x6c => 0x03bb,
                0x6d => 0x03bc,
                0x6e => 0x03bd,
                0x6f => 0x03bf,
                0x70 => 0x03c0,
                0x71 => 0x03b8,
                0x72 => 0x03c1,
                0x73 => 0x03c3,
                0x74 => 0x03c4,
                0x75 => 0x03c5,
                0x76 => 0x03d6,
                0x77 => 0x03c9,
                0x78 => 0x03be,
                0x79 => 0x03c8,
                0x7a => 0x03b6,
                0x7e => 0x223c,
                0xa0 => 0x20ac,
                0xa1 => 0x03d2,
                0xa2 => 0x2032,
                0xa3 => 0x2264,
                0xa4 => 0x2044,
                0xa5 => 0x221e,
                0xa6 => 0x0192,
                0xa7 => 0x2663,
                0xa8 => 0x2666,
                0xa9 => 0x2665,
                0xaa => 0x2660,
                0xab => 0x2194,
                0xac => 0x2190,
                0xad => 0x2191,
                0xae => 0x2192,
                0xaf => 0x2193,
                0xb0 => 0x00b0,
                0xb1 => 0x00b1,
                0xb2 => 0x2033,
                0xb3 => 0x2265,
                0xb4 => 0x00d7,
                0xb5 => 0x221d,
                0xb6 => 0x2202,
                0xb7 => 0x2022,
                0xb8 => 0x00f7,
                0xb9 => 0x2260,
                0xba => 0x2261,
                0xbb => 0x2248,
                0xbc => 0x2026,
                0xbf => 0x21b5,
                0xc0 => 0x2135,
                0xc1 => 0x2111,
                0xc2 => 0x211c,
                0xc3 => 0x2118,
                0xc4 => 0x2297,
                0xc5 => 0x2295,
                0xc6 => 0x2205,
                0xc7 => 0x2229,
                0xc8 => 0x222a,
                0xc9 => 0x2283,
                0xca => 0x2287,
                0xcb => 0x2284,
                0xcc => 0x2282,
                0xcd => 0x2286,
                0xce => 0x2208,
                0xcf => 0x2209,
                0xd0 => 0x2220,
                0xd1 => 0x2207,
                0xd2 => 0x00ae,
                0xd3 => 0x00a9,
                0xd4 => 0x2122,
                0xd5 => 0x220f,
                0xd6 => 0x221a,
                0xd7 => 0x22c5,
                0xd8 => 0x00ac,
                0xd9 => 0x2227,
                0xda => 0x2228,
                0xdb => 0x21d4,
                0xdc => 0x21d0,
                0xdd => 0x21d1,
                0xde => 0x21d2,
                0xdf => 0x21d3,
                0xe0 => 0x25ca,
                0xe1 => 0x2329,
                0xe2 => 0x00ae,
                0xe3 => 0x00a9,
                0xe4 => 0x2122,
                0xe5 => 0x2211,
                0xf1 => 0x232a,
                0xf2 => 0x222b,
            ]),
            default => null,
        };
    }

    private function isPredefinedUcs2CMapName(string $encodingName): bool
    {
        return preg_match('/^Uni[A-Za-z0-9-]*-UCS2(?:-[A-Za-z0-9]+)*-[HV]$/', $encodingName) === 1;
    }

    /**
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}
     * @param array<int, int> $codepoints
     */
    private function codepointEncodingMap(array $codepoints): array
    {
        $map = [];
        foreach ($codepoints as $code => $codepoint) {
            if (!is_int($code) || !is_int($codepoint) || $code < 0 || $code > 255 || $codepoint < 0 || $codepoint > 0x10ffff) {
                continue;
            }
            $decoded = $this->unicodeCodepoint($codepoint);
            if ($decoded !== '') {
                $map[str_pad(strtolower(dechex($code)), 2, '0', STR_PAD_LEFT)] = $decoded;
            }
        }

        return [
            'map' => $map,
            'codeSpaceRanges' => [
                ['start' => 0, 'end' => 255, 'width' => 2],
            ],
        ];
    }

    /**
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}|null
     */
    private function implicitBaseFontEncodingMap(string $fontBody): ?array
    {
        $baseFont = $this->pdfNameValueAfterName($fontBody, 'BaseFont');
        if ($baseFont === null || $baseFont === '') {
            return null;
        }

        if (preg_match('/^[A-Z]{6}\+(.+)$/', $baseFont, $match) === 1) {
            $baseFont = $match[1];
        }

        if ($baseFont === 'Symbol' || $baseFont === 'StandardSymbolsPS') {
            return $this->namedEncodingMap('SymbolEncoding');
        }

        return null;
    }

    private function unicodeCodepoint(int $codepoint): string
    {
        $decoded = iconv('UTF-32BE', 'UTF-8//IGNORE', pack('N', $codepoint));
        return $decoded === false ? '' : $decoded;
    }

    private function singleUnicodeCodepoint(string $text): ?int
    {
        $encoded = iconv('UTF-8', 'UTF-32BE//IGNORE', $text);
        if ($encoded === false || strlen($encoded) !== 4) {
            return null;
        }

        $unpacked = unpack('Ncodepoint', $encoded);
        return is_array($unpacked) ? (int) $unpacked['codepoint'] : null;
    }

    private function glyphNameToUnicode(string $glyphName): string
    {
        $baseName = explode('.', $glyphName, 2)[0];
        if ($baseName === '') {
            return '';
        }

        if (preg_match('/^uni([\da-fA-F]{4})(?:[\da-fA-F]{4})*$/', $baseName) === 1) {
            $hex = substr($baseName, 3);
            return $this->decodeCMapUnicodeHex($hex);
        }

        if (preg_match('/^u([\da-fA-F]{4,6})$/', $baseName, $match) === 1) {
            $codepoint = hexdec($match[1]);
            if ($codepoint <= 0x10ffff) {
                $decoded = iconv('UTF-32BE', 'UTF-8//IGNORE', pack('N', $codepoint));
                return $decoded === false ? '' : $decoded;
            }
        }

        if (str_contains($baseName, '_')) {
            $text = '';
            foreach (explode('_', $baseName) as $component) {
                $decoded = $this->glyphNameToUnicode($component);
                if ($decoded === '') {
                    return '';
                }
                $text .= $decoded;
            }

            return $text;
        }

        $names = [
            'space' => ' ',
            'hyphen' => '-',
            'minus' => '-',
            'period' => '.',
            'comma' => ',',
            'colon' => ':',
            'semicolon' => ';',
            'parenleft' => '(',
            'parenright' => ')',
            'slash' => '/',
            'quoteleft' => "\u{2018}",
            'quoteright' => "\u{2019}",
            'quotesinglleft' => "\u{2018}",
            'quotesinglright' => "\u{2019}",
            'quotedblleft' => "\u{201C}",
            'quotedblright' => "\u{201D}",
            'endash' => "\u{2013}",
            'emdash' => "\u{2014}",
            'ellipsis' => "\u{2026}",
            'bullet' => "\u{2022}",
            'Euro' => "\u{20AC}",
            'euro' => "\u{20AC}",
            'AE' => "\u{00C6}",
            'ae' => "\u{00E6}",
            'OE' => "\u{0152}",
            'oe' => "\u{0153}",
            'Lslash' => "\u{0141}",
            'lslash' => "\u{0142}",
            'Aacute' => "\u{00C1}",
            'aacute' => "\u{00E1}",
            'Eacute' => "\u{00C9}",
            'eacute' => "\u{00E9}",
            'Iacute' => "\u{00CD}",
            'iacute' => "\u{00ED}",
            'Oacute' => "\u{00D3}",
            'oacute' => "\u{00F3}",
            'Uacute' => "\u{00DA}",
            'uacute' => "\u{00FA}",
            'ff' => 'ff',
            'fi' => 'fi',
            'fl' => 'fl',
            'ffi' => 'ffi',
            'ffl' => 'ffl',
            'longst' => 'st',
            'slongt' => 'st',
            'st' => 'st',
            'A' => 'A',
            'B' => 'B',
            'C' => 'C',
            'D' => 'D',
            'E' => 'E',
            'F' => 'F',
            'G' => 'G',
            'H' => 'H',
            'I' => 'I',
            'J' => 'J',
            'K' => 'K',
            'L' => 'L',
            'M' => 'M',
            'N' => 'N',
            'O' => 'O',
            'P' => 'P',
            'Q' => 'Q',
            'R' => 'R',
            'S' => 'S',
            'T' => 'T',
            'U' => 'U',
            'V' => 'V',
            'W' => 'W',
            'X' => 'X',
            'Y' => 'Y',
            'Z' => 'Z',
            'a' => 'a',
            'b' => 'b',
            'c' => 'c',
            'd' => 'd',
            'e' => 'e',
            'f' => 'f',
            'g' => 'g',
            'h' => 'h',
            'i' => 'i',
            'j' => 'j',
            'k' => 'k',
            'l' => 'l',
            'm' => 'm',
            'n' => 'n',
            'o' => 'o',
            'p' => 'p',
            'q' => 'q',
            'r' => 'r',
            's' => 's',
            't' => 't',
            'u' => 'u',
            'v' => 'v',
            'w' => 'w',
            'x' => 'x',
            'y' => 'y',
            'z' => 'z',
        ];

        return $names[$baseName] ?? '';
    }

    /**
     * @return array<int, string>
     */
    private function pdfObjects(string $pdfBytes): array
    {
        $this->currentObjectReferenceOwners = [];
        $this->currentDirectObjectBodiesByGeneration = [];
        if ($this->hasEncryptedTrailer($pdfBytes)) {
            return [];
        }

        $definitions = $this->directObjectDefinitions($pdfBytes);
        if ($definitions === []) {
            return [];
        }
        $this->currentDirectObjectBodiesByGeneration = $this->directObjectBodiesByGeneration($definitions);

        $preliminaryObjects = $this->latestDirectObjects($definitions);
        if ($this->startxrefXrefStreamFilterDecodeFailed($pdfBytes, $preliminaryObjects, $definitions)) {
            $this->currentDirectObjectBodiesByGeneration = [];
            return [];
        }

        $xrefEntries = $this->xrefEntries($pdfBytes, $preliminaryObjects, $definitions);
        $objects = $this->liveDirectObjects($definitions, $xrefEntries);
        $objects = $this->withReferencedDirectGenerationObjects($objects, $definitions, $xrefEntries);
        $this->currentObjectReferenceOwners = $this->objectReferenceOwners($objects, $definitions, $xrefEntries);
        $linearizedHintRanges = $this->linearizedHintTableRanges($pdfBytes);
        $linearizedHintObjectStreamMemberNumbers = $this->linearizedHintTableObjectStreamMemberNumbers(
            $objects,
            $definitions,
            $linearizedHintRanges
        );
        foreach ($this->linearizedHintTableObjectNumbers($pdfBytes, $definitions, $linearizedHintRanges, $objects) as $objectNumber) {
            unset($objects[$objectNumber]);
        }
        $this->currentObjectReferenceOwners = $this->objectReferenceOwners($objects, $definitions, $xrefEntries);

        $objects = $this->withObjectStreamObjects($objects, $xrefEntries);
        $objects = $this->withReferencedDirectGenerationObjects($objects, $definitions, $xrefEntries);
        $objects = $this->withoutLinearizedHintObjectStreamMembers($objects, $definitions, $linearizedHintObjectStreamMemberNumbers);
        $objects = $this->withRepairedDirectStreamObjects($pdfBytes, $objects, $definitions, $xrefEntries);
        $this->currentObjectReferenceOwners = $this->objectReferenceOwners($objects, $definitions, $xrefEntries);
        $objects = $this->withObjectStreamObjects($objects, $xrefEntries);
        $objects = $this->withoutLinearizedHintObjectStreamMembers($objects, $definitions, $linearizedHintObjectStreamMemberNumbers);
        ksort($objects, SORT_NUMERIC);
        $this->currentObjectReferenceOwners = $this->objectReferenceOwners($objects, $definitions, $xrefEntries);

        $rootReference = $this->trailerRootReferenceFromStartxrefChain($pdfBytes, $definitions)
            ?? $this->trailerRootReferenceFromLatestClassicXrefTable($pdfBytes, $definitions);
        if ($rootReference !== null && $rootReference['generation'] > 0) {
            $objects = $this->withDirectGenerationObjectReference(
                $objects,
                $definitions,
                $rootReference['objectNumber'],
                $rootReference['generation']
            );
            $this->currentObjectReferenceOwners = $this->objectReferenceOwners($objects, $definitions, $xrefEntries);
            $objects = $this->withReferencedDirectGenerationObjects($objects, $definitions, $xrefEntries);
            $this->currentObjectReferenceOwners = $this->objectReferenceOwners($objects, $definitions, $xrefEntries);
        }

        if ($rootReference !== null && isset($objects[$rootReference['objectNumber']])) {
            $objects = $this->promoteObjectToFront($objects, $rootReference['objectNumber']);
        }

        return $objects;
    }

    /**
     * Initial object scanning can only resolve direct stream boundaries with
     * operands available at that point. Once object streams are expanded,
     * selected direct streams may gain indirect /Length, /Filter, or
     * /DecodeParms helpers; rebuild those bodies from the original bytes before
     * page text extraction so compressed payload decoys do not own the object.
     *
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, bodyStart: int, bodyEnd: int, body: string}>> $definitions
     * @param array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}> $xrefEntries
     * @return array<int, string>
     */
    private function withRepairedDirectStreamObjects(
        string $pdfBytes,
        array $objects,
        array $definitions,
        array $xrefEntries
    ): array {
        $repaired = $objects;
        foreach ($this->liveDirectObjectDefinitionsInFileOrder($definitions, $xrefEntries) as $definition) {
            $objectNumber = $definition['objectNumber'];
            if (!isset($repaired[$objectNumber]) || $repaired[$objectNumber] !== $definition['body']) {
                continue;
            }

            $body = $this->repairedDirectStreamObjectBody($pdfBytes, $definition, $repaired);
            if ($body !== null && $body !== $definition['body']) {
                $repaired[$objectNumber] = $body;
            }
        }

        ksort($repaired, SORT_NUMERIC);

        return $repaired;
    }

    /**
     * @param array{objectNumber: int, generation: int, offset: int, bodyStart: int, bodyEnd: int, body: string} $definition
     * @param array<int, string> $objects
     */
    private function repairedDirectStreamObjectBody(string $pdfBytes, array $definition, array $objects): ?string
    {
        $dictionaryOffset = $this->skipPdfWhitespace($pdfBytes, $definition['bodyStart']);
        $dictionaryEndOffset = $dictionaryOffset;
        $dict = $this->readPdfDictionaryTokenAt($pdfBytes, $dictionaryEndOffset);
        if ($dict === null) {
            return null;
        }

        $streamKeywordOffset = $this->skipPdfWhitespace($pdfBytes, $dictionaryEndOffset);
        if (!$this->pdfKeywordAt($pdfBytes, $streamKeywordOffset, 'stream')) {
            return null;
        }

        $streamStart = $streamKeywordOffset + strlen('stream');
        if (substr($pdfBytes, $streamStart, 2) === "\r\n") {
            $streamStart += 2;
        } elseif (($pdfBytes[$streamStart] ?? '') === "\n" || ($pdfBytes[$streamStart] ?? '') === "\r") {
            $streamStart++;
        }

        $declaredLength = $this->streamLength($dict, $objects);
        $streamEnd = null;
        if ($declaredLength !== null && $declaredLength >= 0) {
            $declaredEnd = $streamStart + $declaredLength;
            if ($declaredEnd <= strlen($pdfBytes)) {
                $dctJpegTerminator = $this->dctStreamEndstreamTerminatorOffset($pdfBytes, $streamStart, $dict, $objects);
                $streamEnd = $this->streamLengthTerminatorOffset($pdfBytes, $declaredEnd)
                    ?? $this->filteredEndstreamTerminatorOffset($pdfBytes, $streamStart, $dict, $objects, $declaredEnd)
                    ?? $this->contentStreamEndstreamTerminatorOffset($pdfBytes, $streamStart, $dict)
                    ?? $this->endstreamTerminatorOffset($pdfBytes, $streamStart, $declaredEnd);
                if (
                    $dctJpegTerminator !== null
                    && ($streamEnd === null || $dctJpegTerminator >= $streamEnd)
                ) {
                    $streamEnd = $dctJpegTerminator;
                }
                if ($streamEnd !== null) {
                    $filterStackRecoveredTerminator = $this->filterStackRecoveredStreamTerminatorOffset(
                        $pdfBytes,
                        $streamStart,
                        $dict,
                        $objects,
                        $streamEnd
                    );
                    if ($filterStackRecoveredTerminator !== null) {
                        $streamEnd = $filterStackRecoveredTerminator;
                    }
                }
            }
        }

        $streamEnd ??= $this->filteredEndstreamTerminatorOffset($pdfBytes, $streamStart, $dict, $objects);
        $streamEnd ??= $this->dctStreamEndstreamTerminatorOffset($pdfBytes, $streamStart, $dict, $objects);
        if ($streamEnd === null) {
            return null;
        }

        $bodyEnd = $streamEnd + strlen('endstream');
        if ($bodyEnd <= $definition['bodyStart'] || $bodyEnd > strlen($pdfBytes)) {
            return null;
        }

        if ($bodyEnd <= $definition['bodyEnd']) {
            return null;
        }

        return substr($pdfBytes, $definition['bodyStart'], $bodyEnd - $definition['bodyStart']);
    }

    /**
     * Object-stream dictionaries may use indirect /Filter, /DecodeParms, /N,
     * and /First operands. A selected object stream can therefore depend on
     * ordinary compressed helper objects recovered from another object stream.
     *
     * @param array<int, string> $objects
     * @param array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}> $xrefEntries
     * @return array<int, string>
     */
    private function withObjectStreamObjects(array $objects, array $xrefEntries): array
    {
        for ($pass = 0; $pass < 8; $pass++) {
            $added = false;
            foreach ($this->objectsFromObjectStreams($objects, $xrefEntries) as $objectNumber => $body) {
                if (isset($objects[$objectNumber])) {
                    continue;
                }

                $objects[$objectNumber] = $body;
                $added = true;
            }

            if (!$added) {
                break;
            }

            ksort($objects, SORT_NUMERIC);
        }

        return $objects;
    }

    /**
     * @param array<int, string> $objects
     * @return array<int, string>
     */
    private function promoteObjectToFront(array $objects, int $objectNumber): array
    {
        $ordered = [$objectNumber => $objects[$objectNumber]];
        unset($objects[$objectNumber]);

        foreach ($objects as $candidateObjectNumber => $body) {
            $ordered[$candidateObjectNumber] = $body;
        }

        return $ordered;
    }

    private function hasEncryptedTrailer(string $pdfBytes): bool
    {
        $definitions = $this->directObjectDefinitions($pdfBytes);
        $currentTrailerEncrypt = $this->trailerEncryptValueFromStartxrefChain($pdfBytes, $definitions);
        if ($currentTrailerEncrypt['parsed']) {
            return $this->pdfEncryptValueIsEncrypted($currentTrailerEncrypt['value']);
        }

        foreach ($this->trailerDictionaryBodies($pdfBytes) as $trailer) {
            $value = $this->pdfValueAfterName($trailer, 'Encrypt');
            if ($this->pdfEncryptValueIsEncrypted($value)) {
                return true;
            }
        }

        if (!preg_match_all('/\d+\s+\d+\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER)) {
            return false;
        }

        foreach ($matches as $match) {
            $body = $match[1];
            if (preg_match('/\/Type\s*\/XRef\b/s', $body) !== 1) {
                continue;
            }

            $value = $this->pdfValueAfterName($body, 'Encrypt');
            if ($this->pdfEncryptValueIsEncrypted($value)) {
                return true;
            }
        }

        return false;
    }

    private function pdfEncryptValueIsEncrypted(?string $value): bool
    {
        return $value !== null && trim($value) !== 'null';
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, bodyStart: int, bodyEnd: int, body: string}>> $definitions
     * @return array{parsed: bool, value: string|null}
     */
    private function trailerEncryptValueFromStartxrefChain(string $pdfBytes, array $definitions): array
    {
        $offset = $this->startxrefOffsetWithClassicRebuild($pdfBytes, $definitions);
        if ($offset === null) {
            return ['parsed' => false, 'value' => null];
        }

        return $this->trailerEncryptValueFromOffsetChain($pdfBytes, $offset, $definitions);
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, bodyStart: int, bodyEnd: int, body: string}>> $definitions
     * @param array<int, bool> $seenOffsets
     * @return array{parsed: bool, value: string|null}
     */
    private function trailerEncryptValueFromOffsetChain(
        string $pdfBytes,
        int $offset,
        array $definitions,
        array $seenOffsets = []
    ): array {
        if ($offset < 0 || isset($seenOffsets[$offset])) {
            return ['parsed' => false, 'value' => null];
        }
        $seenOffsets[$offset] = true;

        $tableSection = $this->xrefTableSectionAt($pdfBytes, $offset, $definitions);
        if ($tableSection !== null) {
            $value = $this->topLevelPdfValueAfterName($tableSection['trailer'], 'Encrypt');
            if ($value !== null) {
                return ['parsed' => true, 'value' => $value];
            }

            $hybridStreamOffset = $this->pdfIntegerValueAfterName($tableSection['trailer'], 'XRefStm');
            if ($hybridStreamOffset !== null && $hybridStreamOffset >= 0 && !isset($seenOffsets[$hybridStreamOffset])) {
                $streamSection = $this->xrefStreamSectionAtOffset($hybridStreamOffset, $definitions);
                if ($streamSection !== null) {
                    $value = $this->topLevelPdfValueAfterName($streamSection['body'], 'Encrypt');
                    if ($value !== null) {
                        return ['parsed' => true, 'value' => $value];
                    }
                }
            }

            $previousOffset = $this->previousXrefOffsetFromSectionBody($pdfBytes, $tableSection['trailer']);
            if ($previousOffset !== null && $previousOffset >= 0) {
                $previous = $this->trailerEncryptValueFromOffsetChain($pdfBytes, $previousOffset, $definitions, $seenOffsets);
                return $previous['parsed'] ? $previous : ['parsed' => true, 'value' => null];
            }

            return ['parsed' => true, 'value' => null];
        }

        $streamSection = $this->xrefStreamSectionAtOffset($offset, $definitions);
        if ($streamSection === null) {
            return ['parsed' => false, 'value' => null];
        }

        $value = $this->topLevelPdfValueAfterName($streamSection['body'], 'Encrypt');
        if ($value !== null) {
            return ['parsed' => true, 'value' => $value];
        }

        $previousOffset = $this->previousXrefOffsetFromSectionBody($pdfBytes, $streamSection['body']);
        if ($previousOffset !== null && $previousOffset >= 0) {
            $previous = $this->trailerEncryptValueFromOffsetChain($pdfBytes, $previousOffset, $definitions, $seenOffsets);
            return $previous['parsed'] ? $previous : ['parsed' => true, 'value' => null];
        }

        return ['parsed' => true, 'value' => null];
    }

    /**
     * @return list<string>
     */
    private function trailerDictionaryBodies(string $pdfBytes): array
    {
        $trailers = [];
        if (!preg_match_all('/(?:^|[\r\n])trailer\s*<</s', $pdfBytes, $matches, PREG_OFFSET_CAPTURE)) {
            return $trailers;
        }

        foreach ($matches[0] as $match) {
            $offset = strpos($pdfBytes, '<<', $match[1]);
            if ($offset === false) {
                continue;
            }

            $dictionary = $this->readPdfDictionaryAt($pdfBytes, $offset);
            if ($dictionary !== null) {
                $trailers[] = $dictionary;
            }
        }

        return $trailers;
    }

    /**
     * @return array<int, list<array{generation: int, offset: int, bodyStart: int, bodyEnd: int, body: string}>>
     */
    private function directObjectDefinitions(string $pdfBytes): array
    {
        $definitions = [];
        $offset = 0;
        while (preg_match('/(\d+)\s+(\d+)\s+obj\b/s', $pdfBytes, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $objectOffset = $match[1][1];
            $bodyStart = $match[0][1] + strlen($match[0][0]);
            $bodyEnd = $this->pdfObjectEndOffset($pdfBytes, $bodyStart);
            if ($bodyEnd === null) {
                break;
            }

            $objectNumber = (int) $match[1][0];
            $definitions[$objectNumber][] = [
                'generation' => (int) $match[2][0],
                'offset' => $objectOffset,
                'bodyStart' => $bodyStart,
                'bodyEnd' => $bodyEnd,
                'body' => substr($pdfBytes, $bodyStart, $bodyEnd - $bodyStart),
            ];
            $offset = $bodyEnd + strlen('endobj');
        }
        ksort($definitions, SORT_NUMERIC);

        return $definitions;
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, bodyStart: int, bodyEnd: int, body: string}>> $definitions
     * @return array<int, array<int, string>>
     */
    private function directObjectBodiesByGeneration(array $definitions): array
    {
        $bodies = [];

        foreach ($definitions as $objectNumber => $entries) {
            $byGeneration = [];
            foreach ($entries as $entry) {
                $generation = $entry['generation'];
                if (!isset($byGeneration[$generation]) || $entry['offset'] >= $byGeneration[$generation]['offset']) {
                    $byGeneration[$generation] = [
                        'offset' => $entry['offset'],
                        'body' => $entry['body'],
                    ];
                }
            }

            foreach ($byGeneration as $generation => $entry) {
                $bodies[$objectNumber][$generation] = $entry['body'];
            }
        }

        return $bodies;
    }

    private function pdfObjectEndOffset(string $pdfBytes, int $offset): ?int
    {
        $objectBodyStart = $offset;
        $index = $offset;
        $length = strlen($pdfBytes);
        while ($index < $length) {
            $char = $pdfBytes[$index];
            if ($char === '%') {
                $this->skipPdfComment($pdfBytes, $index);
                continue;
            }

            if ($char === '(') {
                $this->readLiteralToken($pdfBytes, $index);
                continue;
            }

            if ($char === '<') {
                if ($index + 1 < $length && $pdfBytes[$index + 1] === '<') {
                    $this->readDictionaryToken($pdfBytes, $index);
                    continue;
                }

                $this->readHexToken($pdfBytes, $index);
                continue;
            }

            if ($char === '[') {
                $this->readArrayToken($pdfBytes, $index);
                continue;
            }

            if ($this->pdfKeywordAt($pdfBytes, $index, 'stream')) {
                $streamStart = $index + strlen('stream');
                if (substr($pdfBytes, $streamStart, 2) === "\r\n") {
                    $streamStart += 2;
                } elseif (($pdfBytes[$streamStart] ?? '') === "\n" || ($pdfBytes[$streamStart] ?? '') === "\r") {
                    $streamStart++;
                }

                $dict = $this->directObjectStreamDictionaryBeforeKeyword($pdfBytes, $objectBodyStart, $index);
                $declaredEnd = $this->directObjectStreamDeclaredEnd($pdfBytes, $objectBodyStart, $index, $streamStart);
                if ($declaredEnd !== null) {
                    $streamEnd = $this->streamLengthTerminatorOffset($pdfBytes, $declaredEnd);
                    if ($streamEnd !== null && $dict !== null) {
                        $filterObjects = $this->directObjectStreamFilterObjectsBeforeOffset($pdfBytes, $dict, $objectBodyStart);
                        $dctJpegTerminator = $this->dctStreamEndstreamTerminatorOffset(
                            $pdfBytes,
                            $streamStart,
                            $dict,
                            $filterObjects
                        );
                        if ($dctJpegTerminator !== null && $dctJpegTerminator >= $streamEnd) {
                            $streamEnd = $dctJpegTerminator;
                        }

                        $recoveredTerminator = $this->startxrefRecoveredStreamTerminatorOffset(
                            $pdfBytes,
                            $streamStart,
                            $dict,
                            $filterObjects,
                            $streamEnd
                        );
                        if ($recoveredTerminator !== null) {
                            $streamEnd = $recoveredTerminator;
                        }

                        $filterStackRecoveredTerminator = $this->filterStackRecoveredStreamTerminatorOffset(
                            $pdfBytes,
                            $streamStart,
                            $dict,
                            $filterObjects,
                            $streamEnd
                        );
                        if ($filterStackRecoveredTerminator !== null) {
                            $streamEnd = $filterStackRecoveredTerminator;
                        }
                    }
                    $streamEnd ??= $dict === null
                        ? $this->endstreamTerminatorOffset($pdfBytes, $streamStart, $declaredEnd)
                        : (
                            $this->filteredEndstreamTerminatorOffset(
                                $pdfBytes,
                                $streamStart,
                                $dict,
                                $this->directObjectStreamFilterObjectsBeforeOffset($pdfBytes, $dict, $objectBodyStart),
                                $declaredEnd
                            )
                            ?? $this->contentStreamEndstreamTerminatorOffset($pdfBytes, $streamStart, $dict)
                            ?? $this->endstreamTerminatorOffset($pdfBytes, $streamStart, $declaredEnd)
                        );
                    if ($dict !== null) {
                        $dctJpegTerminator = $this->dctStreamEndstreamTerminatorOffset(
                            $pdfBytes,
                            $streamStart,
                            $dict,
                            $this->directObjectStreamFilterObjectsBeforeOffset($pdfBytes, $dict, $objectBodyStart)
                        );
                        if (
                            $dctJpegTerminator !== null
                            && ($streamEnd === null || $dctJpegTerminator >= $streamEnd)
                        ) {
                            $streamEnd = $dctJpegTerminator;
                        }
                    }
                    if ($streamEnd !== null && $streamEnd >= $declaredEnd) {
                        $index = $streamEnd + strlen('endstream');
                        continue;
                    }
                }

                $streamEnd = null;
                if ($dict !== null) {
                    $streamEnd = $this->filteredEndstreamTerminatorOffset(
                        $pdfBytes,
                        $streamStart,
                        $dict,
                        $this->directObjectStreamFilterObjectsBeforeOffset($pdfBytes, $dict, $objectBodyStart)
                    );
                    $streamEnd ??= $this->dctStreamEndstreamTerminatorOffset(
                        $pdfBytes,
                        $streamStart,
                        $dict,
                        $this->directObjectStreamFilterObjectsBeforeOffset($pdfBytes, $dict, $objectBodyStart)
                    );
                    $streamEnd ??= $this->contentStreamEndstreamTerminatorOffset($pdfBytes, $streamStart, $dict);
                }
                $streamEnd ??= $this->endstreamTerminatorOffset($pdfBytes, $streamStart, null);
                if ($streamEnd !== null) {
                    $index = $streamEnd + strlen('endstream');
                    continue;
                }
            }

            if ($this->pdfKeywordAt($pdfBytes, $index, 'endobj')) {
                return $index;
            }

            $index++;
        }

        return null;
    }

    private function directObjectStreamDeclaredEnd(
        string $pdfBytes,
        int $objectBodyStart,
        int $streamKeywordOffset,
        int $streamStart
    ): ?int {
        $dict = $this->directObjectStreamDictionaryBeforeKeyword($pdfBytes, $objectBodyStart, $streamKeywordOffset);
        if ($dict === null) {
            return null;
        }

        $lengthOffset = $this->topLevelNameValueOffset($dict, 'Length');
        if ($lengthOffset === null) {
            return null;
        }

        $length = $this->directObjectStreamLengthAt($pdfBytes, $dict, $lengthOffset, $objectBodyStart);
        if ($length === null || $length < 0) {
            return null;
        }

        $declaredEnd = $streamStart + $length;
        return $declaredEnd <= strlen($pdfBytes) ? $declaredEnd : null;
    }

    private function directObjectStreamDictionaryBeforeKeyword(
        string $pdfBytes,
        int $objectBodyStart,
        int $streamKeywordOffset
    ): ?string {
        $dictionaryOffset = $this->skipPdfWhitespace($pdfBytes, $objectBodyStart);
        $dictionaryEndOffset = $dictionaryOffset;
        $dict = $this->readPdfDictionaryTokenAt($pdfBytes, $dictionaryEndOffset);
        if ($dict === null || $this->skipPdfWhitespace($pdfBytes, $dictionaryEndOffset) !== $streamKeywordOffset) {
            return null;
        }

        return $dict;
    }

    private function directObjectStreamLengthAt(string $pdfBytes, string $dict, int $offset, int $beforeOffset): ?int
    {
        $offset = $this->skipPdfWhitespace($dict, $offset);
        if (preg_match('/\G(\d+)\s+(\d+)\s+R\b/s', $dict, $match, 0, $offset) === 1) {
            return $this->directObjectSimpleIntegerBeforeOffset(
                $pdfBytes,
                (int) $match[1],
                (int) $match[2],
                $beforeOffset
            );
        }

        if (preg_match('/\G([+-]?\d+)/s', $dict, $match, 0, $offset) === 1) {
            $length = (int) $match[1];
            return $length < 0 ? null : $length;
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function directObjectStreamFilterObjectsBeforeOffset(string $pdfBytes, string $dict, int $beforeOffset): array
    {
        $pending = [];
        foreach (['Filter', 'DecodeParms'] as $name) {
            $offset = $this->topLevelNameValueOffset($dict, $name);
            if ($offset === null) {
                continue;
            }

            $value = $this->pdfValueAtOffset($dict, $offset);
            if ($value === null) {
                continue;
            }

            foreach ($this->pdfObjectReferencePairs($value) as $reference) {
                $pending[] = $reference;
            }
        }

        $objects = [];
        $seen = [];
        while ($pending !== [] && count($objects) < 32) {
            $reference = array_shift($pending);
            if (!is_array($reference)) {
                continue;
            }

            $objectNumber = $reference['objectNumber'];
            $generation = $reference['generation'];
            $key = $objectNumber . ':' . $generation;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $body = $this->directObjectBodyBeforeOffset($pdfBytes, $objectNumber, $generation, $beforeOffset);
            if ($body === null || !$this->directObjectStreamFilterHelperBodyIsSafe($body)) {
                continue;
            }

            $objects[$objectNumber] = $body;
            foreach ($this->pdfObjectReferencePairs($body) as $nestedReference) {
                $pending[] = $nestedReference;
            }
        }

        return $objects;
    }

    /**
     * @return list<array{objectNumber: int, generation: int}>
     */
    private function pdfObjectReferencePairs(string $value): array
    {
        if (!preg_match_all('/\b(\d+)\s+(\d+)\s+R\b/s', $value, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $references = [];
        foreach ($matches as $match) {
            $references[] = [
                'objectNumber' => (int) $match[1],
                'generation' => (int) $match[2],
            ];
        }

        return $references;
    }

    private function directObjectBodyBeforeOffset(
        string $pdfBytes,
        int $objectNumber,
        int $generation,
        int $beforeOffset
    ): ?string {
        if ($objectNumber <= 0 || $generation < 0) {
            return null;
        }

        $pattern = '/(?:^|[\r\n])' . preg_quote((string) $objectNumber, '/') . '\s+'
            . preg_quote((string) $generation, '/') . '\s+obj\b/s';
        $offset = 0;
        $selected = null;
        while (preg_match($pattern, $pdfBytes, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $objectOffset = $match[0][1];
            if ($objectOffset >= $beforeOffset) {
                break;
            }

            $bodyStart = $objectOffset + strlen($match[0][0]);
            $bodyEnd = strpos($pdfBytes, 'endobj', $bodyStart);
            if ($bodyEnd === false || $bodyEnd > $beforeOffset) {
                break;
            }

            $selected = trim(substr($pdfBytes, $bodyStart, $bodyEnd - $bodyStart));
            $offset = $bodyEnd + strlen('endobj');
        }

        return $selected;
    }

    private function directObjectStreamFilterHelperBodyIsSafe(string $body): bool
    {
        if ($body === '' || preg_match('/\b(?:obj|endobj|stream|endstream|xref|trailer|startxref)\b/s', $body) === 1) {
            return false;
        }

        $offset = $this->skipPdfWhitespace($body, 0);
        return $offset < strlen($body)
            && (
                $body[$offset] === '/'
                || $body[$offset] === '['
                || substr($body, $offset, 2) === '<<'
                || preg_match('/\G(?:null|[+-]?\d+)\b/s', $body, $match, 0, $offset) === 1
            );
    }

    private function directObjectSimpleIntegerBeforeOffset(
        string $pdfBytes,
        int $objectNumber,
        int $generation,
        int $beforeOffset
    ): ?int {
        if ($objectNumber <= 0 || $generation < 0) {
            return null;
        }

        $pattern = '/(?:^|[\r\n])' . preg_quote((string) $objectNumber, '/') . '\s+'
            . preg_quote((string) $generation, '/') . '\s+obj\b/s';
        $offset = 0;
        $selected = null;
        while (preg_match($pattern, $pdfBytes, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $objectOffset = $match[0][1];
            if ($objectOffset >= $beforeOffset) {
                break;
            }

            $bodyStart = $objectOffset + strlen($match[0][0]);
            $bodyEnd = strpos($pdfBytes, 'endobj', $bodyStart);
            if ($bodyEnd === false || $bodyEnd > $beforeOffset) {
                break;
            }

            $body = trim(substr($pdfBytes, $bodyStart, $bodyEnd - $bodyStart));
            if (preg_match('/^[+-]?\d+$/', $body) === 1) {
                $selected = (int) $body;
            }

            $offset = $bodyEnd + strlen('endobj');
        }

        return $selected !== null && $selected >= 0 ? $selected : null;
    }

    private function pdfKeywordAt(string $value, int $offset, string $keyword): bool
    {
        $keywordLength = strlen($keyword);
        if (substr($value, $offset, $keywordLength) !== $keyword) {
            return false;
        }

        if ($offset > 0) {
            $before = $value[$offset - 1];
            if ($before === '/' || (!ctype_space($before) && !str_contains('[]()<>{}%', $before))) {
                return false;
            }
        }

        $afterOffset = $offset + $keywordLength;
        if ($afterOffset >= strlen($value)) {
            return true;
        }

        $after = $value[$afterOffset];
        return ctype_space($after) || str_contains('[]()<>{}/%', $after);
    }

    /**
     * @return list<array{start: int, end: int}>
     */
    private function linearizedHintTableRanges(string $pdfBytes): array
    {
        $definitions = $this->directObjectDefinitions($pdfBytes);
        $firstDefinition = null;
        foreach ($definitions as $entries) {
            foreach ($entries as $definition) {
                if ($firstDefinition === null || $definition['offset'] < $firstDefinition['offset']) {
                    $firstDefinition = $definition;
                }
            }
        }

        if (
            $firstDefinition === null
            || preg_match('/\/Linearized\b/', $firstDefinition['body']) !== 1
            || preg_match('/\/H\s*\[(.*?)\]/s', $firstDefinition['body'], $match) !== 1
        ) {
            return [];
        }

        $values = $this->integerValuesFromPdfArrayResolvingObjects(
            $match[1],
            $this->latestDirectObjects($definitions)
        );
        $ranges = [];
        for ($index = 0, $count = count($values); $index + 1 < $count; $index += 2) {
            $start = max(0, $values[$index]);
            $length = max(0, $values[$index + 1]);
            if ($length === 0) {
                continue;
            }

            $ranges[] = [
                'start' => $start,
                'end' => $start + $length,
            ];
        }

        return $ranges;
    }

    /**
     * @param array<int, string> $objects
     * @return list<int>
     */
    private function integerValuesFromPdfArrayResolvingObjects(string $arrayBody, array $objects): array
    {
        $values = [];
        foreach ($this->pdfArrayItems($arrayBody) as $item) {
            $value = $this->streamLengthValueAt($item, 0, $objects);
            if ($value !== null) {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, bodyStart: int, bodyEnd: int, body: string}>> $definitions
     * @param list<array{start: int, end: int}> $ranges
     * @return list<int>
     */
    private function linearizedHintTableObjectStreamMemberNumbers(array $objects, array $definitions, array $ranges): array
    {
        if ($ranges === []) {
            return [];
        }

        $objectNumbers = [];
        foreach ($definitions as $carrierObjectNumber => $entries) {
            if (!isset($objects[$carrierObjectNumber])) {
                continue;
            }

            foreach ($entries as $definition) {
                if ($objects[$carrierObjectNumber] !== $definition['body']) {
                    continue;
                }

                $entry = $this->streamDictionaryAndPayloadWithOffsets($definition['body'], $objects);
                if ($entry === null || !$this->isObjectStreamDictionary($entry['dict'], $objects)) {
                    continue;
                }

                $filters = $this->streamFilters($entry['dict'], $objects);
                if ($filters !== []) {
                    continue;
                }

                $memberTable = $this->decodedObjectStreamMemberTable($definition['body'], $objects);
                if ($memberTable === null) {
                    continue;
                }

                $objectDataLength = strlen($memberTable['decoded']) - $memberTable['first'];
                foreach ($memberTable['members'] as $member) {
                    $nextOffset = $this->objectStreamMemberEndOffset(
                        $memberTable['members'],
                        $member['offset'],
                        $objectDataLength
                    );
                    if ($nextOffset === null) {
                        continue;
                    }

                    $memberStart = $definition['bodyStart'] + $entry['streamStart'] + $memberTable['first'] + $member['offset'];
                    $memberEnd = $definition['bodyStart'] + $entry['streamStart'] + $memberTable['first'] + $nextOffset;
                    if ($memberEnd <= $memberStart) {
                        continue;
                    }

                    foreach ($ranges as $range) {
                        if ($this->byteRangesOverlap($memberStart, $memberEnd, $range['start'], $range['end'])) {
                            $objectNumbers[$member['objectNumber']] = true;
                            break;
                        }
                    }
                }
            }
        }

        return array_keys($objectNumbers);
    }

    /**
     * Linearized hint-table bytes are not page content, but their exclusion is
     * scoped to the compressed generation-zero member body that intersects the
     * hint range. Preserve a repaired direct generation for the same object
     * number when the current hybrid xref graph explicitly references it.
     *
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @param list<int> $objectNumbers
     * @return array<int, string>
     */
    private function withoutLinearizedHintObjectStreamMembers(array $objects, array $definitions, array $objectNumbers): array
    {
        foreach ($objectNumbers as $objectNumber) {
            if (!isset($objects[$objectNumber])) {
                continue;
            }

            if ($this->directObjectDefinitionForBody($definitions[$objectNumber] ?? [], $objects[$objectNumber]) !== null) {
                continue;
            }

            unset($objects[$objectNumber]);
        }

        return $objects;
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, bodyStart: int, bodyEnd: int, body: string}>> $definitions
     * @param list<array{start: int, end: int}> $ranges
     * @param array<int, string> $objects
     * @return list<int>
     */
    private function linearizedHintTableObjectNumbers(string $pdfBytes, array $definitions, array $ranges, array $objects): array
    {
        if ($ranges === []) {
            return [];
        }

        $objectNumbers = [];
        foreach ($definitions as $objectNumber => $entries) {
            foreach ($entries as $definition) {
                if ($this->offsetInPdfByteRanges($definition['offset'], $ranges)) {
                    $objectNumbers[$objectNumber] = true;
                    break;
                }

                foreach ($ranges as $range) {
                    if (!$this->byteRangesOverlap($definition['bodyStart'], $definition['bodyEnd'], $range['start'], $range['end'])) {
                        continue;
                    }

                    if ($this->linearizedHintRangeHitsObjectStreamPayload($definition, $range, $objects)) {
                        continue;
                    }

                    $objectNumbers[$objectNumber] = true;
                    break 2;
                }
            }
        }

        return array_keys($objectNumbers);
    }

    /**
     * @param array{generation: int, offset: int, bodyStart: int, bodyEnd: int, body: string} $definition
     * @param array{start: int, end: int} $range
     * @param array<int, string> $objects
     */
    private function linearizedHintRangeHitsObjectStreamPayload(array $definition, array $range, array $objects): bool
    {
        $entry = $this->streamDictionaryAndPayloadWithOffsets($definition['body'], $objects);
        if ($entry === null || !$this->isObjectStreamDictionary($entry['dict'], $objects)) {
            return false;
        }

        return $this->byteRangesOverlap(
            $definition['bodyStart'] + $entry['streamStart'],
            $definition['bodyStart'] + $entry['streamEnd'],
            $range['start'],
            $range['end']
        );
    }

    /**
     * @param list<array{start: int, end: int}> $ranges
     */
    private function offsetInPdfByteRanges(int $offset, array $ranges): bool
    {
        foreach ($ranges as $range) {
            if ($offset >= $range['start'] && $offset < $range['end']) {
                return true;
            }
        }

        return false;
    }

    private function byteRangesOverlap(int $leftStart, int $leftEnd, int $rightStart, int $rightEnd): bool
    {
        return $leftStart < $rightEnd && $rightStart < $leftEnd;
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array<int, string>
     */
    private function latestDirectObjects(array $definitions): array
    {
        $objects = [];
        foreach ($definitions as $objectNumber => $entries) {
            $selected = $this->latestDirectObjectDefinition($entries);
            if ($selected !== null) {
                $objects[$objectNumber] = $selected['body'];
            }
        }

        return $objects;
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, bodyStart: int, bodyEnd: int, body: string}>> $definitions
     * @param array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}> $xrefEntries
     * @return list<array{objectNumber: int, generation: int, offset: int, bodyStart: int, bodyEnd: int, body: string}>
     */
    private function liveDirectObjectDefinitionsInFileOrder(array $definitions, array $xrefEntries): array
    {
        $liveDefinitions = [];
        foreach ($definitions as $objectNumber => $entries) {
            $definition = $this->liveDirectObjectDefinition($entries, $xrefEntries[$objectNumber] ?? null);
            if ($definition === null) {
                continue;
            }

            $liveDefinitions[] = [
                'objectNumber' => $objectNumber,
                'generation' => $definition['generation'],
                'offset' => $definition['offset'],
                'bodyStart' => $definition['bodyStart'],
                'bodyEnd' => $definition['bodyEnd'],
                'body' => $definition['body'],
            ];
        }

        usort(
            $liveDefinitions,
            static fn (array $left, array $right): int => $left['offset'] <=> $right['offset']
        );

        return $liveDefinitions;
    }

    /**
     * @param list<array{generation: int, offset: int, body: string}> $definitions
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function latestDirectObjectDefinition(array $definitions): ?array
    {
        if ($definitions === []) {
            return null;
        }

        usort(
            $definitions,
            static fn (array $left, array $right): int => [$left['generation'], $left['offset']] <=> [$right['generation'], $right['offset']]
        );

        $selected = end($definitions);
        return is_array($selected) ? $selected : null;
    }

    /**
     * Object streams can only carry generation-zero objects. Hybrid companion
     * xref streams can also advertise a stale direct generation-zero row while
     * the current graph references a newer generation. If the selected graph
     * names a higher generation directly, recover that direct object before
     * expanding or keeping any conflicting same-number object.
     *
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @param array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}> $xrefEntries
     * @return array<int, string>
     */
    private function withReferencedDirectGenerationObjects(array $objects, array $definitions, array $xrefEntries): array
    {
        $repaired = $objects;

        for ($pass = 0; $pass < 8; $pass++) {
            $added = false;
            foreach ($this->nonZeroGenerationObjectReferences($repaired) as $objectNumber => $generations) {
                $xrefEntry = $xrefEntries[$objectNumber] ?? null;
                $selected = isset($repaired[$objectNumber])
                    ? $this->liveDirectObjectDefinition($definitions[$objectNumber] ?? [], $xrefEntry)
                    : null;
                $canRepairCompressedMember = !isset($repaired[$objectNumber]) && ($xrefEntry['type'] ?? null) === 2;
                $canRepairExpandedCompressedMember = isset($repaired[$objectNumber])
                    && ($xrefEntry['type'] ?? null) === 2
                    && $this->directObjectDefinitionForBody($definitions[$objectNumber] ?? [], $repaired[$objectNumber]) === null;
                $canRepairMissingDirectGeneration = !isset($repaired[$objectNumber]) && ($xrefEntry['type'] ?? null) === 1;
                $canRepairDirectGeneration = isset($repaired[$objectNumber])
                    && ($xrefEntry['type'] ?? null) === 1
                    && $selected !== null;

                if (
                    !$canRepairCompressedMember
                    && !$canRepairExpandedCompressedMember
                    && !$canRepairMissingDirectGeneration
                    && !$canRepairDirectGeneration
                ) {
                    continue;
                }

                krsort($generations, SORT_NUMERIC);
                foreach (array_keys($generations) as $generation) {
                    if ($canRepairDirectGeneration && $selected['generation'] === (int) $generation) {
                        continue;
                    }

                    $definition = $this->directObjectDefinitionForGeneration($definitions[$objectNumber] ?? [], (int) $generation);
                    if ($definition === null) {
                        continue;
                    }

                    $repaired[$objectNumber] = $definition['body'];
                    $added = true;
                    break;
                }
            }

            if (!$added) {
                break;
            }
        }

        ksort($repaired, SORT_NUMERIC);

        return $repaired;
    }

    /**
     * @param array<int, string> $objects
     * @return array<int, array<int, true>>
     */
    private function nonZeroGenerationObjectReferences(array $objects): array
    {
        $references = [];
        foreach ($objects as $body) {
            $source = $this->dictionaryObjectBody($body) ?? $body;
            if (!preg_match_all('/\b(\d+)\s+([1-9]\d*)\s+R\b/s', $source, $matches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($matches as $match) {
                $references[(int) $match[1]][(int) $match[2]] = true;
            }
        }

        return $references;
    }

    /**
     * @param list<array{generation: int, offset: int, body: string}> $definitions
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function directObjectDefinitionForGeneration(array $definitions, int $generation): ?array
    {
        $candidates = [];
        foreach ($definitions as $definition) {
            if ($definition['generation'] === $generation) {
                $candidates[] = $definition;
            }
        }

        return $this->latestDirectObjectDefinition($candidates);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array<int, string>
     */
    private function withDirectGenerationObjectReference(
        array $objects,
        array $definitions,
        int $objectNumber,
        int $generation
    ): array {
        if ($objectNumber <= 0 || $generation < 0) {
            return $objects;
        }

        $definition = $this->directObjectDefinitionForGeneration($definitions[$objectNumber] ?? [], $generation);
        if ($definition === null) {
            return $objects;
        }

        $objects[$objectNumber] = $definition['body'];
        ksort($objects, SORT_NUMERIC);

        return $objects;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @param array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}> $xrefEntries
     * @return array<int, array{generation: int, body: string}>
     */
    private function objectReferenceOwners(array $objects, array $definitions, array $xrefEntries): array
    {
        $owners = [];
        foreach ($objects as $objectNumber => $body) {
            $definition = $this->directObjectDefinitionForBody($definitions[$objectNumber] ?? [], $body);
            if ($definition !== null) {
                $owners[$objectNumber] = [
                    'generation' => $definition['generation'],
                    'body' => $definition['body'],
                ];
                continue;
            }

            if (($xrefEntries[$objectNumber]['type'] ?? null) === 2) {
                $owners[$objectNumber] = [
                    'generation' => 0,
                    'body' => $body,
                ];
            }
        }

        return $owners;
    }

    /**
     * @param list<array{generation: int, offset: int, body: string}> $definitions
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function directObjectDefinitionForBody(array $definitions, string $body): ?array
    {
        $candidates = [];
        foreach ($definitions as $definition) {
            if ($definition['body'] === $body) {
                $candidates[] = $definition;
            }
        }

        return $this->latestDirectObjectDefinition($candidates);
    }

    /**
     * @param array<int, string> $objects
     */
    private function indirectObjectBodyForReference(array $objects, int $objectNumber, int $generation): ?string
    {
        if ($objectNumber <= 0 || $generation < 0 || !isset($objects[$objectNumber])) {
            return null;
        }

        $owner = $this->currentObjectReferenceOwners[$objectNumber] ?? null;
        if ($owner !== null && $objects[$objectNumber] === $owner['body']) {
            return $owner['generation'] === $generation ? $owner['body'] : null;
        }

        return $objects[$objectNumber];
    }

    /**
     * @param array<int, string> $objects
     */
    private function objectBodyForExactReference(array $objects, int $objectNumber, int $generation): ?string
    {
        $body = $this->indirectObjectBodyForReference($objects, $objectNumber, $generation);
        if ($body !== null) {
            return $body;
        }

        return $this->currentDirectObjectBodiesByGeneration[$objectNumber][$generation] ?? null;
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @param array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}> $xrefEntries
     * @return array<int, string>
     */
    private function liveDirectObjects(array $definitions, array $xrefEntries): array
    {
        $objects = [];
        foreach ($definitions as $objectNumber => $entries) {
            $selected = $this->liveDirectObjectDefinition($entries, $xrefEntries[$objectNumber] ?? null);
            if ($selected !== null) {
                $objects[$objectNumber] = $selected['body'];
            }
        }
        ksort($objects, SORT_NUMERIC);

        return $objects;
    }

    /**
     * @param list<array{generation: int, offset: int, body: string}> $definitions
     * @param array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}|null $xrefEntry
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function liveDirectObjectDefinition(array $definitions, ?array $xrefEntry): ?array
    {
        if ($xrefEntry === null) {
            return $this->latestDirectObjectDefinition($definitions);
        }

        if (($xrefEntry['type'] ?? 1) === 2) {
            $objectStreamDefinition = $this->latestDirectObjectStreamDefinition($definitions);
            if ($objectStreamDefinition !== null) {
                return $objectStreamDefinition;
            }

            $xrefStreamDefinition = $this->latestDirectXrefStreamDefinition($definitions);
            if ($xrefStreamDefinition !== null) {
                return $xrefStreamDefinition;
            }

            return null;
        }

        if (($xrefEntry['type'] ?? 1) !== 1) {
            return null;
        }

        $generation = $xrefEntry['generation'] ?? null;
        $offset = $xrefEntry['offset'] ?? null;
        if ($offset !== null) {
            foreach ($definitions as $definition) {
                if ($definition['offset'] === $offset) {
                    return $definition;
                }
            }

            if (($xrefEntry['offsetIsExplicit'] ?? true) === true) {
                return null;
            }
        }

        $candidates = [];
        foreach ($definitions as $definition) {
            if ($generation !== null && $definition['generation'] !== $generation) {
                continue;
            }
            $candidates[] = $definition;
        }

        return $this->latestDirectObjectDefinition($candidates);
    }

    /**
     * Object streams are stream objects and cannot themselves be compressed
     * members. Keep a scanned /ObjStm body available when a malformed type-2
     * row tries to hide the current object-stream base.
     *
     * @param list<array{generation: int, offset: int, body: string}> $definitions
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function latestDirectObjectStreamDefinition(array $definitions): ?array
    {
        $candidates = [];
        foreach ($definitions as $definition) {
            if (preg_match('/\/Type\s*\/ObjStm\b/', $definition['body']) === 1) {
                $candidates[] = $definition;
            }
        }

        return $this->latestDirectObjectDefinition($candidates);
    }

    /**
     * Xref streams are selected as direct file-level stream objects by
     * startxref. A malformed decoded type-2 row for that same object number
     * must not replace the direct xref owner with a compressed member cycle.
     *
     * @param list<array{generation: int, offset: int, body: string}> $definitions
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function latestDirectXrefStreamDefinition(array $definitions): ?array
    {
        $candidates = [];
        foreach ($definitions as $definition) {
            if (preg_match('/\/Type\s*\/XRef\b/', $definition['body']) === 1) {
                $candidates[] = $definition;
            }
        }

        return $this->latestDirectObjectDefinition($candidates);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}>
     */
    private function xrefEntries(string $pdfBytes, array $objects, array $definitions): array
    {
        $entries = $this->xrefEntriesFromStartxrefChain($pdfBytes, $objects, $definitions);
        if ($entries !== []) {
            ksort($entries, SORT_NUMERIC);

            return $entries;
        }

        if ($this->startxrefXrefStreamFilterDecodeFailed($pdfBytes, $objects, $definitions)) {
            return [];
        }

        $startxrefEntry = $this->latestStartxrefEntry($pdfBytes, $definitions);
        $fallbackClassicOffset = $this->latestClassicXrefTableOffset(
            $pdfBytes,
            $definitions,
            $startxrefEntry['tokenOffset'] ?? null
        );
        $entries = $fallbackClassicOffset === null
            ? $this->xrefTableEntries($pdfBytes, $definitions)
            : $this->xrefEntriesFromOffsetChain($pdfBytes, $fallbackClassicOffset, $objects, $definitions);
        foreach ($this->xrefStreamEntries($objects, $definitions) as $objectNumber => $entry) {
            $entries[$objectNumber] = $entry;
        }

        ksort($entries, SORT_NUMERIC);

        return $entries;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     */
    private function startxrefXrefStreamFilterDecodeFailed(string $pdfBytes, array $objects, array $definitions): bool
    {
        $offset = $this->latestStartxrefOffset($pdfBytes, $definitions);
        if ($offset === null) {
            return false;
        }

        $definition = $this->xrefStreamDefinitionAtOffset($definitions, $offset);
        if ($definition === null) {
            return false;
        }

        $streamObjects = $this->objectsWithDirectStreamDictionaryOperandOwners(
            $objects,
            $definition['body'],
            $definitions,
            $definition['offset']
        );
        $entry = $this->streamDictionaryAndPayload($definition['body'], $streamObjects);
        if ($entry === null) {
            return true;
        }

        $filters = $this->streamFilters($entry['dict'], $streamObjects);
        if ($filters === null) {
            return true;
        }

        if ($filters === []) {
            return false;
        }

        return $this->decodeStream($entry['dict'], $entry['stream'], $streamObjects) === null;
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, bodyStart: int, bodyEnd: int, body: string}>>|null $definitions
     * @return array<int, array{type: int, generation: int, offset: int, offsetIsExplicit: bool}>
     */
    private function xrefTableEntries(string $pdfBytes, ?array $definitions = null): array
    {
        $entries = [];
        foreach ($this->xrefTableKeywordOffsets($pdfBytes) as $xrefOffset) {
            if ($definitions !== null && $this->offsetOwnedByDirectObjectBody($xrefOffset, $definitions)) {
                continue;
            }

            $section = $this->xrefTableSectionAt($pdfBytes, $xrefOffset, $definitions);
            if ($section === null) {
                continue;
            }

            foreach ($section['entries'] as $objectNumber => $entry) {
                $entries[$objectNumber] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @return list<int>
     */
    private function xrefTableKeywordOffsets(string $pdfBytes): array
    {
        $offsets = [];
        $length = strlen($pdfBytes);
        $index = 0;
        while ($index < $length) {
            $char = $pdfBytes[$index];

            if ($char === '%') {
                $this->skipPdfComment($pdfBytes, $index);
                continue;
            }

            if ($char === '(') {
                $skipped = $this->skipPdfLiteralStringAt($pdfBytes, $index);
                $index = $skipped === null ? $index + 1 : $skipped + 1;
                continue;
            }

            if ($char === '<' && ($pdfBytes[$index + 1] ?? '') !== '<') {
                $end = strpos($pdfBytes, '>', $index + 1);
                $index = $end === false ? $length : $end + 1;
                continue;
            }

            if ($this->pdfKeywordAt($pdfBytes, $index, 'xref')) {
                $offsets[] = $index;
                $index += strlen('xref');
                continue;
            }

            $index++;
        }

        return $offsets;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return list<array<string, mixed>>
     */
    private function xrefHybridSuppressedObjectStreamEntries(string $pdfBytes, array $objects, array $definitions): array
    {
        $offset = $this->latestStartxrefOffset($pdfBytes, $definitions);
        if ($offset === null) {
            return [];
        }

        return $this->xrefHybridSuppressedObjectStreamEntriesFromOffsetChain($pdfBytes, $offset, $objects, $definitions);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return list<array<string, mixed>>
     */
    private function xrefStreamFreeOwnerEntries(string $pdfBytes, array $objects, array $definitions): array
    {
        $offset = $this->latestStartxrefOffset($pdfBytes, $definitions);
        if ($offset === null) {
            return [];
        }

        return $this->xrefStreamFreeOwnerEntriesFromOffsetChain($pdfBytes, $offset, $objects, $definitions);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @param array<int, bool> $seenOffsets
     * @return list<array<string, mixed>>
     */
    private function xrefStreamFreeOwnerEntriesFromOffsetChain(
        string $pdfBytes,
        int $offset,
        array $objects,
        array $definitions,
        array $seenOffsets = []
    ): array {
        if ($offset < 0 || isset($seenOffsets[$offset])) {
            return [];
        }
        $seenOffsets[$offset] = true;

        $tableSection = $this->xrefTableSectionAt($pdfBytes, $offset, $definitions);
        if ($tableSection !== null) {
            $entries = [];
            $trailer = $tableSection['trailer'];
            $previousOffset = $this->previousXrefOffsetFromSectionBody($pdfBytes, $trailer, $objects);
            $previousEntries = $previousOffset !== null && $previousOffset >= 0
                ? $this->xrefEntriesFromOffsetChain($pdfBytes, $previousOffset, $objects, $definitions, $seenOffsets)
                : [];
            $hybridStreamOffset = $this->pdfIntegerValueAfterName($trailer, 'XRefStm');
            if ($hybridStreamOffset !== null && $hybridStreamOffset >= 0 && !isset($seenOffsets[$hybridStreamOffset])) {
                foreach ($this->xrefStreamEntriesAtOffset($hybridStreamOffset, $objects, $definitions) as $objectNumber => $entry) {
                    if ($objectNumber === 0 || ($entry['type'] ?? null) !== 0) {
                        continue;
                    }

                    $tableEntry = $tableSection['entries'][$objectNumber] ?? null;
                    $objectDefinitions = $definitions[$objectNumber] ?? [];
                    $selected = $this->liveDirectObjectDefinition($objectDefinitions, $entry);
                    $previousEntry = $previousEntries[$objectNumber] ?? null;
                    $directDefinitionCount = count($objectDefinitions);
                    if ($directDefinitionCount === 0 && $previousEntry === null && $tableEntry === null) {
                        continue;
                    }

                    $entries[] = [
                        'object_number' => $objectNumber,
                        'current_xref_offset' => $offset,
                        'hybrid_xref_stream_offset' => $hybridStreamOffset,
                        'free_generation' => $entry['generation'] ?? null,
                        'next_free_object' => $entry['offset'] ?? null,
                        'offset_field_is_explicit' => ($entry['offsetIsExplicit'] ?? true) === true,
                        'direct_definition_count' => $directDefinitionCount,
                        'direct_object_suppressed' => $directDefinitionCount > 0 && $selected === null,
                        'table_entry_suppressed' => $tableEntry !== null,
                        'table_entry_type' => $tableEntry['type'] ?? null,
                        'table_generation' => $tableEntry['generation'] ?? null,
                        'table_offset' => $tableEntry['offset'] ?? null,
                        'previous_entry_suppressed' => $previousEntry !== null,
                        'previous_entry_type' => $previousEntry['type'] ?? null,
                        'previous_generation' => $previousEntry['generation'] ?? null,
                        'previous_offset' => $previousEntry['offset'] ?? null,
                        'previous_object_stream' => $previousEntry['objectStream'] ?? null,
                        'previous_member_index' => $previousEntry['index'] ?? null,
                        'suppressed_by_free_entry' => true,
                        'owner_policy' => $this->xrefHybridStreamFreeOwnerPolicy($tableEntry, $directDefinitionCount, $previousEntry),
                        'review_only' => true,
                    ];
                }
            }

            if ($previousOffset !== null && $previousOffset >= 0) {
                $entries = array_merge(
                    $entries,
                    $this->xrefStreamFreeOwnerEntriesFromOffsetChain(
                        $pdfBytes,
                        $previousOffset,
                        $objects,
                        $definitions,
                        $seenOffsets
                    )
                );
            }

            return $entries;
        }

        $section = $this->xrefSectionEntriesAndPreviousOffset($pdfBytes, $offset, $objects, $definitions, $seenOffsets);
        if ($section === null) {
            return [];
        }

        $previousOffset = $section['previousOffset'];
        $previousEntries = $previousOffset !== null && $previousOffset >= 0
            ? $this->xrefEntriesFromOffsetChain($pdfBytes, $previousOffset, $objects, $definitions, $seenOffsets)
            : [];

        $entries = [];
        if ($section['source'] === 'xref_stream') {
            foreach ($section['entries'] as $objectNumber => $entry) {
                if ($objectNumber === 0 || ($entry['type'] ?? null) !== 0) {
                    continue;
                }

                $objectDefinitions = $definitions[$objectNumber] ?? [];
                $selected = $this->liveDirectObjectDefinition($objectDefinitions, $entry);
                $previousEntry = $previousEntries[$objectNumber] ?? null;
                $directDefinitionCount = count($objectDefinitions);
                if ($directDefinitionCount === 0 && $previousEntry === null) {
                    continue;
                }

                $entries[] = [
                    'object_number' => $objectNumber,
                    'current_xref_offset' => $section['offset'],
                    'free_generation' => $entry['generation'] ?? null,
                    'next_free_object' => $entry['offset'] ?? null,
                    'offset_field_is_explicit' => ($entry['offsetIsExplicit'] ?? true) === true,
                    'direct_definition_count' => $directDefinitionCount,
                    'direct_object_suppressed' => $directDefinitionCount > 0 && $selected === null,
                    'previous_entry_suppressed' => $previousEntry !== null,
                    'previous_entry_type' => $previousEntry['type'] ?? null,
                    'previous_generation' => $previousEntry['generation'] ?? null,
                    'previous_offset' => $previousEntry['offset'] ?? null,
                    'previous_object_stream' => $previousEntry['objectStream'] ?? null,
                    'previous_member_index' => $previousEntry['index'] ?? null,
                    'suppressed_by_free_entry' => true,
                    'owner_policy' => $this->xrefStreamFreeOwnerPolicy($directDefinitionCount, $previousEntry),
                    'review_only' => true,
                ];
            }
        }

        if ($previousOffset !== null && $previousOffset >= 0) {
            $entries = array_merge(
                $entries,
                $this->xrefStreamFreeOwnerEntriesFromOffsetChain(
                    $pdfBytes,
                    $previousOffset,
                    $objects,
                    $definitions,
                    $seenOffsets
                )
            );
        }

        return $entries;
    }

    /**
     * Hybrid-reference files can include a current xref stream alongside a
     * compatibility xref table. A current type-0 stream row must still own that
     * object number; otherwise stale table/direct objects leak into import text.
     *
     * @param array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool} $streamEntry
     * @param array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool} $tableEntry
     */
    private function hybridXrefStreamEntryOwnsTableEntry(array $streamEntry, array $tableEntry): bool
    {
        return ($streamEntry['type'] ?? null) === 0 && ($tableEntry['type'] ?? null) !== 0;
    }

    /**
     * @param array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}|null $tableEntry
     * @param array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}|null $previousEntry
     */
    private function xrefHybridStreamFreeOwnerPolicy(?array $tableEntry, int $directDefinitionCount, ?array $previousEntry): string
    {
        if (($tableEntry['type'] ?? null) === 1) {
            return 'hybrid_xref_stream_free_entry_suppressed_table_direct_object';
        }

        if (($tableEntry['type'] ?? null) === 2) {
            return 'hybrid_xref_stream_free_entry_suppressed_table_compressed_object';
        }

        if (($tableEntry['type'] ?? null) === 0) {
            return 'hybrid_xref_stream_free_entry_preserved_table_free_object';
        }

        return $this->xrefStreamFreeOwnerPolicy($directDefinitionCount, $previousEntry);
    }

    /**
     * @param array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}|null $previousEntry
     */
    private function xrefStreamFreeOwnerPolicy(int $directDefinitionCount, ?array $previousEntry): string
    {
        if (($previousEntry['type'] ?? null) === 2) {
            return 'xref_stream_free_entry_suppressed_prev_compressed_object';
        }

        if ($previousEntry !== null) {
            return 'xref_stream_free_entry_suppressed_prev_object';
        }

        if ($directDefinitionCount > 0) {
            return 'xref_stream_free_entry_suppressed_scanned_direct_object';
        }

        return 'xref_stream_free_entry_preserved';
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @param array<int, bool> $seenOffsets
     * @return list<array<string, mixed>>
     */
    private function xrefHybridSuppressedObjectStreamEntriesFromOffsetChain(
        string $pdfBytes,
        int $offset,
        array $objects,
        array $definitions,
        array $seenOffsets = []
    ): array {
        if ($offset < 0 || isset($seenOffsets[$offset])) {
            return [];
        }
        $seenOffsets[$offset] = true;

        $tableSection = $this->xrefTableSectionAt($pdfBytes, $offset, $definitions);
        if ($tableSection !== null) {
            $entries = [];
            $trailer = $tableSection['trailer'];
            $hybridStreamOffset = $this->pdfIntegerValueAfterName($trailer, 'XRefStm');
            if ($hybridStreamOffset !== null && $hybridStreamOffset >= 0 && !isset($seenOffsets[$hybridStreamOffset])) {
                foreach ($this->xrefStreamEntriesAtOffset($hybridStreamOffset, $objects, $definitions) as $objectNumber => $entry) {
                    if (($entry['type'] ?? null) !== 2 || !isset($entry['objectStream'], $tableSection['entries'][$objectNumber])) {
                        continue;
                    }

                    $tableEntry = $tableSection['entries'][$objectNumber];
                    $tableEntryType = (int) ($tableEntry['type'] ?? -1);
                    $entries[] = [
                        'object_number' => $objectNumber,
                        'object_stream' => (int) $entry['objectStream'],
                        'xref_member_index' => (int) ($entry['index'] ?? 0),
                        'index_is_explicit' => ($entry['indexIsExplicit'] ?? true) === true,
                        'current_xref_offset' => $offset,
                        'hybrid_xref_stream_offset' => $hybridStreamOffset,
                        'table_entry_type' => $tableEntryType,
                        'table_generation' => $tableEntry['generation'] ?? null,
                        'table_offset_field' => $tableEntry['offset'] ?? null,
                        'table_offset_is_explicit' => ($tableEntry['offsetIsExplicit'] ?? true) === true,
                        'suppressed_by_table_entry' => true,
                        'owner_policy' => $this->xrefHybridSuppressedObjectStreamOwnerPolicy($tableEntryType),
                        'review_only' => true,
                    ];
                }
            }

            $previousOffset = $this->previousXrefOffsetFromSectionBody($pdfBytes, $trailer, $objects);
            if ($previousOffset !== null && $previousOffset >= 0) {
                $entries = array_merge(
                    $entries,
                    $this->xrefHybridSuppressedObjectStreamEntriesFromOffsetChain(
                        $pdfBytes,
                        $previousOffset,
                        $objects,
                        $definitions,
                        $seenOffsets
                    )
                );
            }

            return $entries;
        }

        $streamSection = $this->xrefStreamSectionAtOffset($offset, $definitions);
        if ($streamSection === null) {
            return [];
        }

        $streamObjects = $this->objectsWithDirectStreamDictionaryOperandOwners(
            $objects,
            $streamSection['definition']['body'],
            $definitions,
            $streamSection['definition']['offset']
        );
        $previousOffset = $this->previousXrefOffsetFromSectionBody($pdfBytes, $streamSection['body'], $streamObjects);
        return $previousOffset === null || $previousOffset < 0
            ? []
            : $this->xrefHybridSuppressedObjectStreamEntriesFromOffsetChain(
                $pdfBytes,
                $previousOffset,
                $objects,
                $definitions,
                $seenOffsets
            );
    }

    private function xrefHybridSuppressedObjectStreamOwnerPolicy(int $tableEntryType): string
    {
        if ($tableEntryType === 0) {
            return 'hybrid_table_free_entry_preserved';
        }

        if ($tableEntryType === 1) {
            return 'hybrid_table_direct_entry_preserved';
        }

        return 'hybrid_table_entry_preserved';
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}>
     */
    private function xrefEntriesFromStartxrefChain(string $pdfBytes, array $objects, array $definitions): array
    {
        $offset = $this->startxrefOffsetWithClassicRebuild($pdfBytes, $definitions);
        if ($offset === null) {
            return [];
        }

        return $this->xrefEntriesFromOffsetChain($pdfBytes, $offset, $objects, $definitions);
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array{objectNumber: int, generation: int}|null
     */
    private function trailerRootReferenceFromStartxrefChain(string $pdfBytes, array $definitions): ?array
    {
        $offset = $this->startxrefOffsetWithClassicRebuild($pdfBytes, $definitions);
        if ($offset === null) {
            return null;
        }

        return $this->trailerRootReferenceFromOffsetChain($pdfBytes, $offset, $definitions);
    }

    /**
     * Damaged files can leave the final startxref pointing outside the file
     * while still appending a valid classic xref table and trailer. Use that
     * latest top-level table as the rebuild boundary instead of letting older
     * trailer roots win during object-boundary recovery.
     *
     * @param array<int, list<array{generation: int, offset: int, bodyStart: int, bodyEnd: int, body: string}>> $definitions
     * @return array{objectNumber: int, generation: int}|null
     */
    private function trailerRootReferenceFromLatestClassicXrefTable(string $pdfBytes, array $definitions): ?array
    {
        $startxrefEntry = $this->latestStartxrefEntry($pdfBytes, $definitions);
        $offset = $this->latestClassicXrefTableOffset(
            $pdfBytes,
            $definitions,
            $startxrefEntry['tokenOffset'] ?? null
        );
        if ($offset === null) {
            return null;
        }

        return $this->trailerRootReferenceFromOffsetChain($pdfBytes, $offset, $definitions);
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @param array<int, bool> $seenOffsets
     * @return array{objectNumber: int, generation: int}|null
     */
    private function trailerRootReferenceFromOffsetChain(
        string $pdfBytes,
        int $offset,
        array $definitions,
        array $seenOffsets = []
    ): ?array {
        if ($offset < 0 || isset($seenOffsets[$offset])) {
            return null;
        }
        $seenOffsets[$offset] = true;

        $tableSection = $this->xrefTableSectionAt($pdfBytes, $offset, $definitions);
        if ($tableSection !== null) {
            $root = $this->objectReferenceAfterName($tableSection['trailer'], 'Root');
            if ($root !== null) {
                return $root;
            }

            $hybridStreamOffset = $this->pdfIntegerValueAfterName($tableSection['trailer'], 'XRefStm');
            if ($hybridStreamOffset !== null && $hybridStreamOffset >= 0 && !isset($seenOffsets[$hybridStreamOffset])) {
                $streamSection = $this->xrefStreamSectionAtOffset($hybridStreamOffset, $definitions);
                if ($streamSection !== null) {
                    $root = $this->objectReferenceAfterName($streamSection['body'], 'Root');
                    if ($root !== null) {
                        return $root;
                    }
                }
            }

            $previousOffset = $this->previousXrefOffsetFromSectionBody($pdfBytes, $tableSection['trailer']);
            return $previousOffset === null
                ? null
                : $this->trailerRootReferenceFromOffsetChain($pdfBytes, $previousOffset, $definitions, $seenOffsets);
        }

        $streamSection = $this->xrefStreamSectionAtOffset($offset, $definitions);
        if ($streamSection === null) {
            return null;
        }

        $root = $this->objectReferenceAfterName($streamSection['body'], 'Root');
        if ($root !== null) {
            return $root;
        }

        $previousOffset = $this->previousXrefOffsetFromSectionBody($pdfBytes, $streamSection['body']);
        return $previousOffset === null
            ? null
            : $this->trailerRootReferenceFromOffsetChain($pdfBytes, $previousOffset, $definitions, $seenOffsets);
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, bodyStart: int, bodyEnd: int, body: string}>>|null $definitions
     */
    private function latestStartxrefOffset(string $pdfBytes, ?array $definitions = null): ?int
    {
        $entry = $this->latestStartxrefEntry($pdfBytes, $definitions);

        return $entry['offset'] ?? null;
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, bodyStart: int, bodyEnd: int, body: string}>>|null $definitions
     * @return array{offset: int, tokenOffset: int}|null
     */
    private function latestStartxrefEntry(string $pdfBytes, ?array $definitions = null): ?array
    {
        if (preg_match_all('/\bstartxref\s+(\d+)/s', $pdfBytes, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) < 1) {
            return null;
        }

        $linearizedHintRanges = $this->linearizedHintTableRanges($pdfBytes);
        for ($index = count($matches) - 1; $index >= 0; $index--) {
            $match = $matches[$index];
            $tokenOffset = $match[0][1] ?? null;
            if (
                !is_int($tokenOffset)
                || $this->tokenStartsInPdfCommentLine($pdfBytes, $tokenOffset)
                || (
                    $definitions !== null
                    && $this->offsetOwnedByDirectObjectBody($tokenOffset, $definitions)
                )
                || $this->offsetInPdfByteRanges($tokenOffset, $linearizedHintRanges)
            ) {
                continue;
            }

            return [
                'offset' => max(0, (int) ($match[1][0] ?? 0)),
                'tokenOffset' => $tokenOffset,
            ];
        }

        return null;
    }

    private function tokenStartsInPdfCommentLine(string $pdfBytes, int $tokenOffset): bool
    {
        $before = substr($pdfBytes, 0, $tokenOffset);
        $lastLineFeed = strrpos($before, "\n");
        $lastCarriageReturn = strrpos($before, "\r");
        $lineStart = max($lastLineFeed === false ? -1 : $lastLineFeed, $lastCarriageReturn === false ? -1 : $lastCarriageReturn) + 1;
        $commentOffset = strpos($pdfBytes, '%', $lineStart);

        return $commentOffset !== false && $commentOffset < $tokenOffset;
    }

    /**
     * Damaged producer output sometimes leaves the final startxref token
     * pointing at an older classic table even though a later top-level classic
     * table and trailer were appended. Treat that as a rebuild boundary for
     * classic tables only; xref-stream startxref errors stay fail-closed.
     *
     * @param array<int, list<array{generation: int, offset: int, bodyStart: int, bodyEnd: int, body: string}>> $definitions
     */
    private function startxrefOffsetWithClassicRebuild(string $pdfBytes, array $definitions): ?int
    {
        $entry = $this->latestStartxrefEntry($pdfBytes, $definitions);
        if ($entry === null) {
            return null;
        }

        return $this->classicRebuildOffsetForStartxref(
            $pdfBytes,
            $entry['offset'],
            $definitions,
            $entry['tokenOffset']
        ) ?? $entry['offset'];
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, bodyStart: int, bodyEnd: int, body: string}>> $definitions
     */
    private function classicRebuildOffsetForStartxref(
        string $pdfBytes,
        int $offset,
        array $definitions,
        ?int $candidateBeforeOffset = null
    ): ?int {
        if ($this->xrefStreamSectionAtOffset($offset, $definitions) !== null) {
            return null;
        }

        $latestClassicOffset = $this->latestClassicXrefTableOffset($pdfBytes, $definitions, $candidateBeforeOffset);
        if ($latestClassicOffset === null) {
            return null;
        }

        if ($this->xrefTableSectionAt($pdfBytes, $offset, $definitions) === null) {
            if ($offset < strlen($pdfBytes) && $latestClassicOffset <= $offset) {
                return null;
            }

            return $latestClassicOffset;
        }

        return $latestClassicOffset > $offset ? $latestClassicOffset : null;
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, bodyStart: int, bodyEnd: int, body: string}>>|null $definitions
     */
    private function latestClassicXrefTableOffset(
        string $pdfBytes,
        ?array $definitions = null,
        ?int $candidateBeforeOffset = null
    ): ?int {
        $offsets = $this->xrefTableKeywordOffsets($pdfBytes);
        for ($index = count($offsets) - 1; $index >= 0; $index--) {
            $offset = $offsets[$index];
            if ($candidateBeforeOffset !== null && $offset > $candidateBeforeOffset) {
                continue;
            }

            if ($this->xrefTableSectionAt($pdfBytes, $offset, $definitions) !== null) {
                return $offset;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function previousXrefOffsetFromSectionBody(string $pdfBytes, string $sectionBody, array $objects = []): ?int
    {
        $previousOffset = $objects === []
            ? $this->pdfIntegerValueAfterName($sectionBody, 'Prev')
            : $this->pdfIntegerValueAfterNameResolvingObjects($sectionBody, 'Prev', $objects);
        if ($previousOffset === null || $previousOffset < 0) {
            return $previousOffset;
        }

        return $this->offsetInPdfByteRanges($previousOffset, $this->linearizedHintTableRanges($pdfBytes))
            ? null
            : $previousOffset;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @param array<int, bool> $seenOffsets
     * @return array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}>
     */
    private function xrefEntriesFromOffsetChain(string $pdfBytes, int $offset, array $objects, array $definitions, array $seenOffsets = []): array
    {
        if ($offset < 0 || isset($seenOffsets[$offset])) {
            return [];
        }
        $seenOffsets[$offset] = true;

        $tableSection = $this->xrefTableSectionAt($pdfBytes, $offset, $definitions);
        if ($tableSection !== null) {
            $entries = $tableSection['entries'];
            $trailer = $tableSection['trailer'];
            $hybridStreamOffset = $this->pdfIntegerValueAfterName($trailer, 'XRefStm');
            if ($hybridStreamOffset !== null && $hybridStreamOffset >= 0 && !isset($seenOffsets[$hybridStreamOffset])) {
                foreach ($this->xrefStreamEntriesAtOffset($hybridStreamOffset, $objects, $definitions) as $objectNumber => $entry) {
                    if (isset($entries[$objectNumber])) {
                        if ($this->hybridXrefStreamEntryOwnsTableEntry($entry, $entries[$objectNumber])) {
                            $entries[$objectNumber] = $entry;
                        }

                        continue;
                    }

                    $entries[$objectNumber] = $entry;
                }
            }

            $previousOffset = $this->previousXrefOffsetFromSectionBody($pdfBytes, $trailer, $objects);
            if ($previousOffset !== null && $previousOffset >= 0) {
                $previousEntries = $this->xrefEntriesFromOffsetChain($pdfBytes, $previousOffset, $objects, $definitions, $seenOffsets);
                foreach ($previousEntries as $objectNumber => $entry) {
                    if (isset($entries[$objectNumber])) {
                        if ($this->currentCarrierEntryCanRecoverPreviousObjectStreamStorage(
                            $objectNumber,
                            $entries[$objectNumber],
                            $entry,
                            $previousEntries,
                            $definitions
                        )) {
                            $entries[$objectNumber] = $entry;
                        }

                        continue;
                    }

                    if ($this->previousEntryShadowsCurrentObjectStreamBase($objectNumber, $entry, $entries, $definitions, $previousOffset, $offset)) {
                        continue;
                    }

                    if ($this->previousCompressedEntryUsesUpdatedObjectStream($entry, $entries, $previousEntries, $definitions)) {
                        continue;
                    }

                    $entries[$objectNumber] = $entry;
                }
            }

            return $entries;
        }

        $streamSection = $this->xrefStreamSectionAtOffset($offset, $definitions);
        if ($streamSection === null) {
            return [];
        }

        $entries = $this->xrefStreamEntriesFromDefinition($streamSection['definition'], $objects, $definitions);
        $streamObjects = $this->objectsWithDirectStreamDictionaryOperandOwners(
            $objects,
            $streamSection['definition']['body'],
            $definitions,
            $streamSection['definition']['offset']
        );
        $previousOffset = $this->previousXrefOffsetFromSectionBody($pdfBytes, $streamSection['body'], $streamObjects);
        if ($previousOffset !== null && $previousOffset >= 0) {
            $previousEntries = $this->xrefEntriesFromOffsetChain($pdfBytes, $previousOffset, $objects, $definitions, $seenOffsets);
            foreach ($previousEntries as $objectNumber => $entry) {
                if (isset($entries[$objectNumber])) {
                    if ($this->currentCarrierEntryCanRecoverPreviousObjectStreamStorage(
                        $objectNumber,
                        $entries[$objectNumber],
                        $entry,
                        $previousEntries,
                        $definitions
                    )) {
                        $entries[$objectNumber] = $entry;
                    }

                    continue;
                }

                if ($this->previousEntryShadowsCurrentObjectStreamBase($objectNumber, $entry, $entries, $definitions, $previousOffset, $offset)) {
                    continue;
                }

                if ($this->previousCompressedEntryUsesUpdatedObjectStream($entry, $entries, $previousEntries, $definitions)) {
                    continue;
                }

                $entries[$objectNumber] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @param array<int, bool> $seenOffsets
     * @return list<array<string, mixed>>
     */
    private function xrefPrevObjectStreamGenerationReviewEntries(
        string $pdfBytes,
        int $offset,
        array $objects,
        array $definitions,
        array $seenOffsets = []
    ): array {
        if ($offset < 0 || isset($seenOffsets[$offset])) {
            return [];
        }
        $seenOffsets[$offset] = true;

        $section = $this->xrefSectionEntriesAndPreviousOffset($pdfBytes, $offset, $objects, $definitions, $seenOffsets);
        if ($section === null) {
            return [];
        }

        $reviewEntries = [];
        $previousOffset = $section['previousOffset'];
        if ($previousOffset !== null && $previousOffset >= 0) {
            $previousEntries = $this->xrefEntriesFromOffsetChain($pdfBytes, $previousOffset, $objects, $definitions, $seenOffsets);
            foreach ($previousEntries as $objectNumber => $entry) {
                if (($entry['type'] ?? null) !== 2 || !isset($entry['objectStream'])) {
                    continue;
                }

                $currentObjectEntry = $section['entries'][$objectNumber] ?? null;
                if ($currentObjectEntry !== null) {
                    if (($currentObjectEntry['type'] ?? null) === 0) {
                        $objectStreamNumber = (int) $entry['objectStream'];
                        $previousCarrierEntry = $previousEntries[$objectStreamNumber] ?? null;
                        $currentCarrierEntry = $section['entries'][$objectStreamNumber] ?? null;
                        $reviewEntries[] = [
                            'object_number' => $objectNumber,
                            'object_stream' => $objectStreamNumber,
                            'member_index' => (int) ($entry['index'] ?? 0),
                            'index_is_explicit' => ($entry['indexIsExplicit'] ?? true) === true,
                            'current_section_source' => $section['source'],
                            'current_xref_offset' => $section['offset'],
                            'previous_xref_offset' => $previousOffset,
                            'previous_carrier_selected' => ($previousCarrierEntry['type'] ?? null) === 1,
                            'previous_carrier_type' => $previousCarrierEntry['type'] ?? null,
                            'previous_carrier_generation' => $previousCarrierEntry['generation'] ?? null,
                            'previous_carrier_offset' => $previousCarrierEntry['offset'] ?? null,
                            'current_object_entry_type' => $currentObjectEntry['type'],
                            'current_object_generation' => $currentObjectEntry['generation'] ?? null,
                            'current_object_offset' => $currentObjectEntry['offset'] ?? null,
                            'current_free_object_suppressed' => true,
                            'current_carrier_present' => $currentCarrierEntry !== null,
                            'current_carrier_type' => $currentCarrierEntry['type'] ?? null,
                            'current_carrier_generation' => $currentCarrierEntry['generation'] ?? null,
                            'current_carrier_offset' => $currentCarrierEntry['offset'] ?? null,
                            'same_carrier_storage' => false,
                            'current_carrier_invalid_generation_recovered' => false,
                            'skipped' => true,
                            'owner_policy' => 'skipped_current_free_object_generation',
                            'review_only' => true,
                        ];
                    }

                    continue;
                }

                $objectStreamNumber = (int) $entry['objectStream'];
                $previousCarrierEntry = $previousEntries[$objectStreamNumber] ?? null;
                $currentCarrierEntry = $section['entries'][$objectStreamNumber] ?? null;
                $currentCarrierRecovered = $currentCarrierEntry !== null
                    && $previousCarrierEntry !== null
                    && $this->currentCarrierEntryCanRecoverPreviousObjectStreamStorage(
                        $objectStreamNumber,
                        $currentCarrierEntry,
                        $previousCarrierEntry,
                        $previousEntries,
                        $definitions
                    );
                $sameCarrierStorage = $previousCarrierEntry !== null
                    && $currentCarrierEntry !== null
                    && (
                        $this->xrefEntriesSelectSameStorage($currentCarrierEntry, $previousCarrierEntry)
                        || $currentCarrierRecovered
                    );
                $ownerPolicy = $this->xrefPrevObjectStreamGenerationOwnerPolicy(
                    $previousCarrierEntry,
                    $currentCarrierEntry,
                    $currentCarrierRecovered
                );
                $skipped = str_starts_with($ownerPolicy, 'skipped_');

                $reviewEntries[] = [
                    'object_number' => $objectNumber,
                    'object_stream' => $objectStreamNumber,
                    'member_index' => (int) ($entry['index'] ?? 0),
                    'index_is_explicit' => ($entry['indexIsExplicit'] ?? true) === true,
                    'current_section_source' => $section['source'],
                    'current_xref_offset' => $section['offset'],
                    'previous_xref_offset' => $previousOffset,
                    'previous_carrier_selected' => ($previousCarrierEntry['type'] ?? null) === 1,
                    'previous_carrier_type' => $previousCarrierEntry['type'] ?? null,
                    'previous_carrier_generation' => $previousCarrierEntry['generation'] ?? null,
                    'previous_carrier_offset' => $previousCarrierEntry['offset'] ?? null,
                    'current_carrier_present' => $currentCarrierEntry !== null,
                    'current_carrier_type' => $currentCarrierEntry['type'] ?? null,
                    'current_carrier_generation' => $currentCarrierEntry['generation'] ?? null,
                    'current_carrier_offset' => $currentCarrierEntry['offset'] ?? null,
                    'same_carrier_storage' => $sameCarrierStorage,
                    'current_carrier_invalid_generation_recovered' => $currentCarrierRecovered,
                    'skipped' => $skipped,
                    'owner_policy' => $ownerPolicy,
                    'review_only' => true,
                ];
            }

            $reviewEntries = array_merge(
                $reviewEntries,
                $this->xrefPrevObjectStreamGenerationReviewEntries($pdfBytes, $previousOffset, $objects, $definitions, $seenOffsets)
            );
        }

        return $reviewEntries;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @param array<int, bool> $seenOffsets
     * @return array{
     *     source: string,
     *     offset: int,
     *     entries: array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}>,
     *     previousOffset: int|null
     * }|null
     */
    private function xrefSectionEntriesAndPreviousOffset(
        string $pdfBytes,
        int $offset,
        array $objects,
        array $definitions,
        array $seenOffsets
    ): ?array {
        $tableSection = $this->xrefTableSectionAt($pdfBytes, $offset, $definitions);
        if ($tableSection !== null) {
            $entries = $tableSection['entries'];
            $trailer = $tableSection['trailer'];
            $hybridStreamOffset = $this->pdfIntegerValueAfterName($trailer, 'XRefStm');
            if ($hybridStreamOffset !== null && $hybridStreamOffset >= 0 && !isset($seenOffsets[$hybridStreamOffset])) {
                foreach ($this->xrefStreamEntriesAtOffset($hybridStreamOffset, $objects, $definitions) as $objectNumber => $entry) {
                    if (
                        !isset($entries[$objectNumber])
                        || $this->hybridXrefStreamEntryOwnsTableEntry($entry, $entries[$objectNumber])
                    ) {
                        $entries[$objectNumber] = $entry;
                    }
                }
            }

            return [
                'source' => 'xref_table',
                'offset' => $offset,
                'entries' => $entries,
                'previousOffset' => $this->previousXrefOffsetFromSectionBody($pdfBytes, $trailer, $objects),
            ];
        }

        $streamSection = $this->xrefStreamSectionAtOffset($offset, $definitions);
        if ($streamSection === null) {
            return null;
        }

        return [
            'source' => 'xref_stream',
            'offset' => $offset,
            'entries' => $this->xrefStreamEntriesFromDefinition($streamSection['definition'], $objects, $definitions),
            'previousOffset' => $this->previousXrefOffsetFromSectionBody(
                $pdfBytes,
                $streamSection['body'],
                $this->objectsWithDirectStreamDictionaryOperandOwners(
                    $objects,
                    $streamSection['definition']['body'],
                    $definitions,
                    $streamSection['definition']['offset']
                )
            ),
        ];
    }

    /**
     * @param array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}|null $previousCarrierEntry
     * @param array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}|null $currentCarrierEntry
     */
    private function xrefPrevObjectStreamGenerationOwnerPolicy(
        ?array $previousCarrierEntry,
        ?array $currentCarrierEntry,
        bool $currentCarrierRecovered = false
    ): string
    {
        if ($previousCarrierEntry === null) {
            return 'skipped_prev_carrier_absent';
        }

        if (($previousCarrierEntry['type'] ?? null) !== 1) {
            return 'skipped_prev_carrier_not_direct';
        }

        if ($currentCarrierEntry === null) {
            return 'preserved_prev_carrier_storage';
        }

        if ($currentCarrierRecovered) {
            return 'preserved_previous_carrier_after_invalid_current_generation';
        }

        if ($this->xrefEntriesSelectSameStorage($currentCarrierEntry, $previousCarrierEntry)) {
            return 'preserved_same_carrier_storage';
        }

        return 'skipped_current_replaced_carrier_storage';
    }

    /**
     * A type-2 row from a previous xref section is owned by that section's
     * object-stream carrier. If the current section replaces that carrier,
     * do not let the stale compressed-object row bind to the current carrier.
     * If the previous chain never selected the carrier, the member row is not
     * safe to replay against a scanned newer /ObjStm fallback.
     *
     * @param array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool} $entry
     * @param array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}> $currentEntries
     * @param array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}> $previousEntries
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     */
    private function previousCompressedEntryUsesUpdatedObjectStream(
        array $entry,
        array $currentEntries,
        array $previousEntries,
        array $definitions
    ): bool
    {
        if (($entry['type'] ?? null) !== 2 || !isset($entry['objectStream'])) {
            return false;
        }

        $objectStreamNumber = $entry['objectStream'];
        $previousObjectStreamEntry = $previousEntries[$objectStreamNumber] ?? null;
        if ($previousObjectStreamEntry === null) {
            return true;
        }

        if (($previousObjectStreamEntry['type'] ?? null) !== 1) {
            return true;
        }

        if (!isset($currentEntries[$objectStreamNumber])) {
            return false;
        }

        if ($this->currentCarrierEntryCanRecoverPreviousObjectStreamStorage(
            (int) $objectStreamNumber,
            $currentEntries[$objectStreamNumber],
            $previousObjectStreamEntry,
            $previousEntries,
            $definitions
        )) {
            return false;
        }

        return !$this->xrefEntriesSelectSameStorage($currentEntries[$objectStreamNumber], $previousObjectStreamEntry);
    }

    /**
     * If a current xref-stream row for an object-stream carrier has generation
     * noise or an invalid offset that selects no direct object, it should not
     * suppress a previous hybrid-selected carrier required by inherited type-2
     * member rows. A valid current carrier replacement still wins.
     *
     * @param array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool} $currentEntry
     * @param array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool} $previousEntry
     * @param array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}> $previousEntries
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     */
    private function currentCarrierEntryCanRecoverPreviousObjectStreamStorage(
        int $objectNumber,
        array $currentEntry,
        array $previousEntry,
        array $previousEntries,
        array $definitions
    ): bool {
        if (($currentEntry['type'] ?? null) !== 1 || ($previousEntry['type'] ?? null) !== 1) {
            return false;
        }

        if (!$this->xrefEntriesContainType2CarrierReference($previousEntries, $objectNumber)) {
            return false;
        }

        $currentDefinition = $this->xrefEntrySelectedDirectDefinition($objectNumber, $currentEntry, $definitions);
        if ($currentDefinition !== null) {
            return false;
        }

        $previousDefinition = $this->xrefEntrySelectedDirectDefinition($objectNumber, $previousEntry, $definitions);
        return $previousDefinition !== null && preg_match('/\/Type\s*\/ObjStm\b/s', $previousDefinition['body']) === 1;
    }

    /**
     * @param array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}> $entries
     */
    private function xrefEntriesContainType2CarrierReference(array $entries, int $objectStreamNumber): bool
    {
        foreach ($entries as $entry) {
            if (($entry['type'] ?? null) === 2 && ($entry['objectStream'] ?? null) === $objectStreamNumber) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool} $entry
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function xrefEntrySelectedDirectDefinition(int $objectNumber, array $entry, array $definitions): ?array
    {
        if (($entry['type'] ?? null) !== 1) {
            return null;
        }

        return $this->liveDirectObjectDefinition($definitions[$objectNumber] ?? [], $entry);
    }

    /**
     * A previous row for an object-stream carrier should not suppress a current
     * direct /ObjStm body when the latest xref section has type-2 members that
     * name that carrier. This covers free rows and older direct carrier
     * generations; same-storage replay remains valid.
     *
     * @param array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool} $entry
     * @param array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}> $currentEntries
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     */
    private function previousEntryShadowsCurrentObjectStreamBase(
        int $objectNumber,
        array $entry,
        array $currentEntries,
        array $definitions,
        int $previousOffset,
        int $currentOffset
    ): bool {
        $entryType = $entry['type'] ?? null;
        if ($entryType !== 0 && $entryType !== 1) {
            return false;
        }

        $currentType2ReferencesCarrier = false;
        foreach ($currentEntries as $currentEntry) {
            if (($currentEntry['type'] ?? null) === 2 && ($currentEntry['objectStream'] ?? null) === $objectNumber) {
                $currentType2ReferencesCarrier = true;
                break;
            }
        }

        if (!$currentType2ReferencesCarrier) {
            return false;
        }

        $currentObjectStreamBase = $this->latestDirectObjectStreamDefinitionBetweenOffsets(
            $definitions[$objectNumber] ?? [],
            $previousOffset,
            $currentOffset
        );
        if ($currentObjectStreamBase === null) {
            return false;
        }

        if ($entryType === 1) {
            return !$this->xrefEntriesSelectSameStorage(
                [
                    'type' => 1,
                    'generation' => $currentObjectStreamBase['generation'],
                    'offset' => $currentObjectStreamBase['offset'],
                    'offsetIsExplicit' => true,
                ],
                $entry
            );
        }

        return true;
    }

    /**
     * @param list<array{generation: int, offset: int, body: string}> $definitions
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function latestDirectObjectStreamDefinitionBetweenOffsets(array $definitions, int $afterOffset, int $beforeOffset): ?array
    {
        $candidates = [];
        foreach ($definitions as $definition) {
            if (
                $definition['offset'] > $afterOffset
                && $definition['offset'] < $beforeOffset
                && preg_match('/\/Type\s*\/ObjStm\b/', $definition['body']) === 1
            ) {
                $candidates[] = $definition;
            }
        }

        return $this->latestDirectObjectDefinition($candidates);
    }

    /**
     * @param array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool} $left
     * @param array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool} $right
     */
    private function xrefEntriesSelectSameStorage(array $left, array $right): bool
    {
        if (
            ($left['type'] ?? null) === 1
            && ($right['type'] ?? null) === 1
            && ($left['offsetIsExplicit'] ?? true) === true
            && ($right['offsetIsExplicit'] ?? true) === true
            && isset($left['offset'], $right['offset'])
            && $left['offset'] === $right['offset']
        ) {
            return true;
        }

        foreach (['type', 'generation', 'offset', 'objectStream', 'index'] as $field) {
            if (($left[$field] ?? null) !== ($right[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, bodyStart: int, bodyEnd: int, body: string}>>|null $definitions
     * @return array{entries: array<int, array{type: int, generation: int, offset: int, offsetIsExplicit: bool}>, trailer: string}|null
     */
    private function xrefTableSectionAt(string $pdfBytes, int $offset, ?array $definitions = null): ?array
    {
        if ($definitions !== null && $this->offsetOwnedByDirectObjectBody($offset, $definitions)) {
            return null;
        }

        $offset = $this->skipPdfWhitespace($pdfBytes, $offset);
        if (substr($pdfBytes, $offset, 4) !== 'xref') {
            return null;
        }

        $sectionBodyOffset = $offset + 4;
        $trailerOffset = $this->xrefTableTrailerKeywordOffset($pdfBytes, $sectionBodyOffset);
        if ($trailerOffset === null) {
            return null;
        }

        $dictionaryOffset = $this->skipPdfWhitespace($pdfBytes, $trailerOffset + strlen('trailer'));
        if (substr($pdfBytes, $dictionaryOffset, 2) !== '<<') {
            return null;
        }

        $trailer = $this->readPdfDictionaryAt($pdfBytes, $dictionaryOffset);
        if ($trailer === null) {
            return null;
        }

        $entries = $this->xrefTableRows(substr($pdfBytes, $sectionBodyOffset, $trailerOffset - $sectionBodyOffset));
        if ($definitions !== null) {
            $entries = $this->repairCurrentUpdateXrefTableRows($pdfBytes, $entries, $definitions, $trailer, $offset);
        }

        return [
            'entries' => $entries,
            'trailer' => $trailer,
        ];
    }

    private function xrefTableTrailerKeywordOffset(string $pdfBytes, int $offset): ?int
    {
        $length = strlen($pdfBytes);
        $index = $offset;
        while ($index < $length) {
            $char = $pdfBytes[$index];

            if ($char === '%') {
                $this->skipPdfComment($pdfBytes, $index);
                continue;
            }

            if ($char === '(') {
                $skipped = $this->skipPdfLiteralStringAt($pdfBytes, $index);
                $index = $skipped === null ? $index + 1 : $skipped + 1;
                continue;
            }

            if ($char === '<' && ($pdfBytes[$index + 1] ?? '') !== '<') {
                $end = strpos($pdfBytes, '>', $index + 1);
                $index = $end === false ? $length : $end + 1;
                continue;
            }

            if ($this->pdfKeywordAt($pdfBytes, $index, 'trailer')) {
                $dictionaryOffset = $this->skipPdfWhitespace($pdfBytes, $index + strlen('trailer'));
                if (substr($pdfBytes, $dictionaryOffset, 2) === '<<') {
                    return $index;
                }
            }

            $index++;
        }

        return null;
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, bodyStart: int, bodyEnd: int, body: string}>> $definitions
     */
    private function offsetOwnedByDirectObjectBody(int $offset, array $definitions): bool
    {
        foreach ($definitions as $entries) {
            foreach ($entries as $definition) {
                if ($offset >= $definition['bodyStart'] && $offset <= $definition['bodyEnd']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<int, array{type: int, generation: int, offset: int, offsetIsExplicit: bool}>
     */
    private function xrefTableRows(string $sectionBody): array
    {
        $entries = [];
        $lines = preg_split('/\r\n|\r|\n/', trim($sectionBody));
        if ($lines === false) {
            return $entries;
        }

        for ($lineIndex = 0, $lineCount = count($lines); $lineIndex < $lineCount; $lineIndex++) {
            $line = trim($lines[$lineIndex]);
            if (preg_match('/^(\d+)\s+(\d+)$/', $line, $header) !== 1) {
                continue;
            }

            $startObject = (int) $header[1];
            $count = max(0, (int) $header[2]);
            for ($entryIndex = 0; $entryIndex < $count && $lineIndex + 1 < $lineCount; $entryIndex++) {
                $row = trim($lines[++$lineIndex]);
                if (preg_match('/^(\d{10})\s+(\d{5})\s+([nf])\b/', $row, $rowMatch) !== 1) {
                    continue;
                }

                $entries[$startObject + $entryIndex] = [
                    'type' => $rowMatch[3] === 'n' ? 1 : 0,
                    'generation' => (int) $rowMatch[2],
                    'offset' => (int) $rowMatch[1],
                    'offsetIsExplicit' => true,
                ];
            }
        }

        return $entries;
    }

    /**
     * @param array<int, array{type: int, generation: int, offset: int, offsetIsExplicit: bool}> $entries
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array<int, array{type: int, generation: int, offset: int, offsetIsExplicit: bool}>
     */
    private function repairCurrentUpdateXrefTableRows(
        string $pdfBytes,
        array $entries,
        array $definitions,
        string $trailer,
        int $xrefOffset
    ): array {
        $previousOffset = $this->previousXrefOffsetFromSectionBody($pdfBytes, $trailer);
        if ($previousOffset === null || $previousOffset < 0) {
            return $entries;
        }

        foreach ($entries as $objectNumber => $entry) {
            if (($entry['type'] ?? null) !== 1) {
                continue;
            }

            $offset = $entry['offset'] ?? null;
            $offsetOwner = is_int($offset) ? $this->directObjectDefinitionAtOffset($definitions, $offset) : null;
            $updateOwner = $this->currentUpdateDirectObjectDefinitionForStaleXrefOffset(
                (int) $objectNumber,
                (int) ($entry['generation'] ?? 0),
                $offsetOwner,
                $previousOffset,
                $xrefOffset,
                $definitions
            );
            if ($offsetOwner !== null && $updateOwner === null) {
                continue;
            }

            if ($updateOwner === null) {
                continue;
            }

            $entries[$objectNumber]['offset'] = $updateOwner['offset'];
            $entries[$objectNumber]['generation'] = $updateOwner['generation'];
        }

        return $entries;
    }

    /**
     * @return array<int, string>
     * @param array<int, string> $objects
     * @param array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}> $xrefEntries
     */
    private function objectsFromObjectStreams(array $objects, array $xrefEntries): array
    {
        $expanded = [];
        $hasSelectedXrefEntries = $xrefEntries !== [];

        foreach ($objects as $objectStreamNumber => $body) {
            if (preg_match('/\/Type\s*\/ObjStm\b/', $body) !== 1) {
                continue;
            }

            $memberTable = $this->decodedObjectStreamMemberTable($body, $objects);
            if ($memberTable === null) {
                continue;
            }

            $pairs = $memberTable['members'];
            $memberObjectNumberCounts = $this->objectStreamMemberObjectNumberCounts($pairs);
            $hasCompressedXrefEntriesForStream = $this->hasCompressedXrefEntriesForObjectStream($xrefEntries, $objectStreamNumber);
            if ($hasSelectedXrefEntries && !$hasCompressedXrefEntriesForStream) {
                continue;
            }
            $carrierRejectsTopLevelStreamMembers = $this->objectStreamCarrierHasFilters($body, $objects);

            foreach ($pairs as $pair) {
                $objectNumber = $pair['objectNumber'];
                $xrefEntry = $xrefEntries[$objectNumber] ?? null;
                if ($xrefEntry !== null) {
                    if (isset($objects[$objectNumber]) && ($xrefEntry['type'] ?? null) === 2) {
                        continue;
                    }

                    if (
                        ($xrefEntry['type'] ?? null) !== 2
                        || ($xrefEntry['objectStream'] ?? null) !== $objectStreamNumber
                    ) {
                        continue;
                    }

                    $xrefIndex = (int) ($xrefEntry['index'] ?? 0);
                    $indexIsExplicit = ($xrefEntry['indexIsExplicit'] ?? true) === true;
                    if ($indexIsExplicit) {
                        if ($xrefIndex !== $pair['index']) {
                            continue;
                        }
                    } else {
                        $strictMember = $this->objectStreamMemberAtHeaderIndex($pairs, $xrefIndex);
                        if ($strictMember !== null && $strictMember['objectNumber'] === $objectNumber) {
                            if ($xrefIndex !== $pair['index']) {
                                continue;
                            }
                        } elseif (($memberObjectNumberCounts[$objectNumber] ?? 0) !== 1) {
                            continue;
                        }
                    }
                } elseif ($hasCompressedXrefEntriesForStream) {
                    continue;
                }

                $memberBody = $this->objectStreamMemberBody($memberTable, $pair);
                if ($memberBody === null || $memberBody === '') {
                    continue;
                }

                if (
                    $carrierRejectsTopLevelStreamMembers
                    && $this->objectStreamMemberIsTopLevelStreamObject($memberBody)
                ) {
                    continue;
                }

                $expanded[$objectNumber] = $memberBody;
            }
        }

        return $expanded;
    }

    /**
     * @param list<array{objectNumber: int, offset: int, index: int}> $members
     * @return array<int, int>
     */
    private function objectStreamMemberObjectNumberCounts(array $members): array
    {
        $counts = [];
        foreach ($members as $member) {
            $objectNumber = $member['objectNumber'];
            $counts[$objectNumber] = ($counts[$objectNumber] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param list<array{objectNumber: int, offset: int, index: int}> $members
     * @return array{objectNumber: int, offset: int, index: int}|null
     */
    private function objectStreamMemberAtHeaderIndex(array $members, int $headerIndex): ?array
    {
        foreach ($members as $member) {
            if ($member['index'] === $headerIndex) {
                return $member;
            }
        }

        return null;
    }

    /**
     * Object-stream member offsets are relative to the first object byte. Do
     * not assume the header rows are sorted by offset; xref type-2 indexes
     * still point to header indexes, while body slicing is offset-owned.
     *
     * @param list<array{objectNumber: int, offset: int, index: int}> $members
     */
    private function objectStreamMemberEndOffset(array $members, int $memberOffset, int $objectDataLength): ?int
    {
        if ($memberOffset < 0 || $memberOffset >= $objectDataLength) {
            return null;
        }

        $endOffset = $objectDataLength;
        foreach ($members as $candidate) {
            $candidateOffset = $candidate['offset'];
            if ($candidateOffset > $memberOffset && $candidateOffset < $endOffset) {
                $endOffset = $candidateOffset;
            }
        }

        return $endOffset > $memberOffset ? $endOffset : null;
    }

    /**
     * @param array{decoded: string, first: int, members: list<array{objectNumber: int, offset: int, index: int}>} $memberTable
     * @param array{objectNumber: int, offset: int, index: int} $member
     */
    private function objectStreamMemberBody(array $memberTable, array $member): ?string
    {
        $objectDataLength = strlen($memberTable['decoded']) - $memberTable['first'];
        $nextOffset = $this->objectStreamMemberEndOffset(
            $memberTable['members'],
            $member['offset'],
            $objectDataLength
        );
        if ($nextOffset === null) {
            return null;
        }

        return trim(substr(
            $memberTable['decoded'],
            $memberTable['first'] + $member['offset'],
            $nextOffset - $member['offset']
        ));
    }

    private function objectStreamMemberIsTopLevelStreamObject(string $memberBody): bool
    {
        $dictionaryOffset = $this->skipPdfWhitespace($memberBody, 0);
        $dictionaryEndOffset = $dictionaryOffset;
        if ($this->readPdfDictionaryTokenAt($memberBody, $dictionaryEndOffset) === null) {
            return false;
        }

        $streamKeywordOffset = $this->skipPdfWhitespace($memberBody, $dictionaryEndOffset);
        return $this->pdfKeywordAt($memberBody, $streamKeywordOffset, 'stream');
    }

    /**
     * @param array<int, string> $objects
     */
    private function objectStreamCarrierHasFilters(string $body, array $objects): bool
    {
        $stream = $this->streamDictionaryAndPayload($body, $objects);
        if ($stream === null) {
            return false;
        }

        $filters = $this->streamFilters($stream['dict'], $objects);
        return is_array($filters) && $filters !== [];
    }

    private function objectStreamIndexSelectionPolicy(
        bool $indexIsExplicit,
        bool $strictMemberMatch,
        bool $memberExists,
        bool $ambiguousZeroWidthMember = false
    ): string
    {
        if ($strictMemberMatch) {
            return $indexIsExplicit ? 'explicit_member_index' : 'default_zero_member_index';
        }

        if ($ambiguousZeroWidthMember) {
            return 'ambiguous_zero_width_duplicate_header_object_number';
        }

        if (!$memberExists) {
            return 'missing_object_stream_member';
        }

        return $indexIsExplicit ? 'explicit_member_index_mismatch' : 'recovered_by_header_object_number';
    }

    /**
     * @param array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}|null $xrefEntry
     * @param array{generation: int, offset: int, body: string}|null $selectedDefinition
     */
    private function objectStreamCarrierOwnerPolicy(?array $xrefEntry, ?array $selectedDefinition): string
    {
        if (($xrefEntry['type'] ?? null) === 1 && $selectedDefinition !== null) {
            return 'xref_selected_object_stream_carrier';
        }

        if ($xrefEntry === null && $selectedDefinition !== null) {
            return 'scanned_object_stream_carrier';
        }

        if (($xrefEntry['type'] ?? null) === 0) {
            return 'free_object_stream_carrier';
        }

        if (($xrefEntry['type'] ?? null) === 2) {
            return $selectedDefinition !== null
                ? 'direct_object_stream_carrier_preserved'
                : 'compressed_object_stream_carrier_unavailable';
        }

        return 'missing_object_stream_carrier';
    }

    /**
     * Object streams contain generation-zero indirect objects. A nonzero
     * reference must be satisfied by a selected direct object generation or
     * remain unresolved; it must not bind to the compressed generation-zero
     * member merely because the object numbers match.
     *
     * @param list<int> $referencedGenerations
     */
    private function objectStreamGenerationSelectionPolicy(?int $selectedGeneration, array $referencedGenerations): string
    {
        if ($referencedGenerations === []) {
            return 'compressed_generation_zero_member';
        }

        if ($selectedGeneration !== null && in_array($selectedGeneration, $referencedGenerations, true)) {
            return 'direct_generation_reference_preserved';
        }

        if ($selectedGeneration === 0) {
            return 'compressed_generation_zero_not_selected_for_nonzero_reference';
        }

        return 'nonzero_generation_reference_unresolved';
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}> $xrefEntries
     * @param array<int, list<array{generation: int, offset: int, bodyStart: int, bodyEnd: int, body: string}>> $definitions
     * @return list<array<string, mixed>>
     */
    private function xrefStreamOperandReviews(
        string $dict,
        string $name,
        array $objects,
        array $xrefEntries,
        array $definitions
    ): array {
        $offset = $this->topLevelNameValueOffset($dict, $name);
        if ($offset === null) {
            return [];
        }

        $value = $this->pdfValueAtOffset($dict, $offset);
        if ($value === null) {
            return [[
                'name' => $name,
                'kind' => 'malformed',
                'resolved' => false,
                'xref_selected' => false,
                'owner_policy' => 'malformed_operand',
            ]];
        }

        $items = $this->xrefStreamOperandItems($value);
        if ($items === []) {
            return [[
                'name' => $name,
                'kind' => 'empty',
                'resolved' => false,
                'xref_selected' => false,
                'owner_policy' => 'empty_operand',
            ]];
        }

        $reviews = [];
        foreach ($items as $item) {
            $item = trim($item);
            if (preg_match('/^(\d+)\s+(\d+)\s+R\b/s', $item, $match) === 1) {
                $reviews[] = $this->xrefStreamIndirectOperandReview(
                    $name,
                    (int) $match[1],
                    (int) $match[2],
                    $objects,
                    $xrefEntries,
                    $definitions
                );
                continue;
            }

            $tokenType = $this->pdfOperandTokenType($item);
            $review = [
                'name' => $name,
                'kind' => 'direct',
                'value' => $this->xrefStreamDirectOperandValue($item),
                'token_type' => $tokenType,
                'resolved' => true,
                'xref_selected' => false,
                'owner_policy' => 'direct_operand',
            ];
            if ($name === 'Filter') {
                $review['valid_filter_operand'] = $this->directFilterOperandTokenTypeIsValid($tokenType);
            }
            if ($name === 'DecodeParms') {
                $review['valid_decodeparms_operand'] = $this->decodeParmsOperandBodyIsValid($item, $objects);
            }

            $reviews[] = $review;
        }

        return $reviews;
    }

    /**
     * @return list<string>
     */
    private function xrefStreamOperandItems(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
            return $this->pdfArrayItems(substr($value, 1, -1));
        }

        return [$value];
    }

    private function xrefStreamDirectOperandValue(string $item): mixed
    {
        if ($item === 'null') {
            return null;
        }

        if (str_starts_with($item, '/')) {
            return $this->decodePdfName(substr($item, 1));
        }

        if (preg_match('/^[+-]?\d+$/', $item) === 1) {
            return (int) $item;
        }

        return $item;
    }

    private function pdfOperandTokenType(string $item): string
    {
        $item = trim($item);
        if ($item === 'null') {
            return 'null';
        }

        if ($item === 'true' || $item === 'false') {
            return 'boolean';
        }

        if (str_starts_with($item, '/')) {
            return 'name';
        }

        if (str_starts_with($item, '<<')) {
            return 'dictionary';
        }

        if (str_starts_with($item, '[')) {
            return 'array';
        }

        if (str_starts_with($item, '(')) {
            return 'literal';
        }

        if (str_starts_with($item, '<')) {
            return 'hex_string';
        }

        if (preg_match('/^[+-]?(?:\d+|\d*\.\d+)$/', $item) === 1) {
            return 'number';
        }

        return 'bareword';
    }

    private function directFilterOperandTokenTypeIsValid(string $tokenType): bool
    {
        return $tokenType === 'name' || $tokenType === 'null';
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function decodeParmsOperandBodyIsValid(string $body, array $objects, array $seen = []): bool
    {
        return $this->decodeParmsValueList(trim($body), 0, $objects, $seen) !== null;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}> $xrefEntries
     * @param array<int, list<array{generation: int, offset: int, bodyStart: int, bodyEnd: int, body: string}>> $definitions
     * @return array<string, mixed>
     */
    private function xrefStreamIndirectOperandReview(
        string $name,
        int $objectNumber,
        int $generation,
        array $objects,
        array $xrefEntries,
        array $definitions
    ): array {
        $definition = $this->directObjectDefinitionForGeneration($definitions[$objectNumber] ?? [], $generation)
            ?? $this->latestDirectObjectDefinition($definitions[$objectNumber] ?? []);
        $xrefEntry = $xrefEntries[$objectNumber] ?? null;
        $selected = $xrefEntry === null
            ? null
            : $this->liveDirectObjectDefinition($definitions[$objectNumber] ?? [], $xrefEntry);
        $directXrefSelected = $definition !== null
            && $selected !== null
            && $selected['offset'] === $definition['offset'];
        $compressedXrefSelected = ($xrefEntry['type'] ?? null) === 2 && isset($objects[$objectNumber]);
        $xrefSelected = $directXrefSelected || $compressedXrefSelected;
        $ownerPolicy = $this->xrefStreamIndirectOperandOwnerPolicy(
            $objectNumber,
            $objects,
            $xrefEntry,
            $definition,
            $selected,
            $xrefSelected
        );

        $body = $this->xrefStreamIndirectOperandBody($objectNumber, $objects, $xrefEntry, $definition, $selected);

        $review = [
            'name' => $name,
            'kind' => 'indirect',
            'object_number' => $objectNumber,
            'generation' => $generation,
            'resolved' => $body !== null,
            'xref_selected' => $xrefSelected,
            'xref_entry_type' => $xrefEntry['type'] ?? null,
            'xref_offset' => $xrefEntry['offset'] ?? null,
            'definition_offset' => $definition['offset'] ?? null,
            'selected_offset' => $selected['offset'] ?? null,
            'owner_policy' => $ownerPolicy,
            'value_preview' => $body === null ? null : $this->xrefStreamOperandValuePreview($body),
        ];

        if ($name === 'Filter' && $body !== null) {
            $body = trim($body);
            $review['token_type'] = $this->pdfOperandTokenType($body);
            $review['dictionary_filter_operand'] = $this->filterOperandBodyContainsDictionary($body);
            $review['valid_filter_operand'] = $this->filterNamesFromValue(
                $body,
                $objects,
                [$objectNumber . ':' . $generation => true]
            ) !== null;
        }
        if ($name === 'DecodeParms' && $body !== null) {
            $body = trim($body);
            $review['token_type'] = $this->pdfOperandTokenType($body);
            $review['valid_decodeparms_operand'] = $this->decodeParmsOperandBodyIsValid(
                $body,
                $objects,
                [$objectNumber . ':' . $generation => true]
            );
        }

        return $review;
    }

    /**
     * @param array<int, string> $objects
     * @param array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}|null $xrefEntry
     * @param array{generation: int, offset: int, body: string}|null $definition
     * @param array{generation: int, offset: int, body: string}|null $selected
     */
    private function xrefStreamIndirectOperandOwnerPolicy(
        int $objectNumber,
        array $objects,
        ?array $xrefEntry,
        ?array $definition,
        ?array $selected,
        bool $xrefSelected
    ): string {
        if ($xrefEntry === null) {
            if (!isset($objects[$objectNumber]) || $definition === null) {
                return 'missing_object';
            }

            return 'scanned_without_xref_entry';
        }

        if (($xrefEntry['type'] ?? null) === 0) {
            return 'free_xref_entry';
        }

        if (($xrefEntry['type'] ?? null) === 2) {
            return isset($objects[$objectNumber]) ? 'compressed_operand_after_xref_decode' : 'missing_compressed_operand';
        }

        if (!isset($objects[$objectNumber]) || $definition === null) {
            return 'missing_object';
        }

        if (($xrefEntry['type'] ?? null) === 1 && $xrefSelected) {
            return 'xref_selected_direct_object';
        }

        if (($xrefEntry['type'] ?? null) === 1 && $selected !== null) {
            return 'xref_entry_points_elsewhere';
        }

        return 'unsupported_xref_entry';
    }

    /**
     * @param array<int, string> $objects
     * @param array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}|null $xrefEntry
     * @param array{generation: int, offset: int, body: string}|null $definition
     * @param array{generation: int, offset: int, body: string}|null $selected
     */
    private function xrefStreamIndirectOperandBody(
        int $objectNumber,
        array $objects,
        ?array $xrefEntry,
        ?array $definition,
        ?array $selected
    ): ?string {
        if (($xrefEntry['type'] ?? null) === 2) {
            return $objects[$objectNumber] ?? null;
        }

        if ($definition !== null) {
            return $definition['body'];
        }

        if ($selected !== null) {
            return $selected['body'];
        }

        return $objects[$objectNumber] ?? null;
    }

    private function xrefStreamOperandValuePreview(string $body): string
    {
        $preview = trim($body);
        $preview = (string) preg_replace('/\s+/', ' ', $preview);
        if (strlen($preview) > 80) {
            return substr($preview, 0, 77) . '...';
        }

        return $preview;
    }

    /**
     * @param list<array<string, mixed>> $operands
     */
    private function xrefStreamIndirectOperandCount(array $operands): int
    {
        $count = 0;
        foreach ($operands as $operand) {
            if (($operand['kind'] ?? null) === 'indirect') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param list<array<string, mixed>> $operands
     */
    private function invalidStreamFilterOperandCount(array $operands): int
    {
        $count = 0;
        foreach ($operands as $operand) {
            if (
                ($operand['kind'] ?? null) !== 'direct'
                && (($operand['resolved'] ?? false) !== true || ($operand['xref_selected'] ?? false) !== true)
            ) {
                $count++;
                continue;
            }

            if ($this->streamFilterOperandIsDictionary($operand)) {
                $count++;
                continue;
            }

            if ($this->streamFilterOperandIsMalformed($operand)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param list<array<string, mixed>> $operands
     */
    private function dictionaryStreamFilterOperandCount(array $operands): int
    {
        $count = 0;
        foreach ($operands as $operand) {
            if ($this->streamFilterOperandIsDictionary($operand)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param list<array<string, mixed>> $operands
     */
    private function malformedStreamFilterOperandCount(array $operands): int
    {
        $count = 0;
        foreach ($operands as $operand) {
            if ($this->streamFilterOperandIsMalformed($operand)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param list<array<string, mixed>> $operands
     */
    private function invalidStreamDecodeParmsOperandCount(array $operands): int
    {
        $count = 0;
        foreach ($operands as $operand) {
            if (
                ($operand['kind'] ?? null) !== 'direct'
                && (($operand['resolved'] ?? false) !== true || ($operand['xref_selected'] ?? false) !== true)
            ) {
                $count++;
                continue;
            }

            if (($operand['valid_decodeparms_operand'] ?? true) === false) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param list<array<string, mixed>> $operands
     */
    private function malformedStreamDecodeParmsOperandCount(array $operands): int
    {
        $count = 0;
        foreach ($operands as $operand) {
            if (($operand['valid_decodeparms_operand'] ?? null) === false) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param list<string|null>|null $filters
     * @param list<string|null>|null $decodeParms
     * @param array<int, string> $objects
     */
    private function invalidDecodeParmsParameterCount(?array $filters, ?array $decodeParms, array $objects): int
    {
        if ($filters === null || $decodeParms === null) {
            return 0;
        }

        $count = 0;
        foreach ($filters as $index => $filter) {
            if ($filter === null) {
                continue;
            }

            $filterDecodeParms = $decodeParms[$index] ?? null;
            if (
                $filterDecodeParms !== null
                && trim($filterDecodeParms) !== ''
                && !$this->canApplyDecodeParms($filter, $filterDecodeParms, $objects)
            ) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $operand
     */
    private function streamFilterOperandIsDictionary(array $operand): bool
    {
        if (($operand['dictionary_filter_operand'] ?? false) === true) {
            return true;
        }

        $preview = $operand['value_preview'] ?? $operand['value'] ?? null;
        if (!is_string($preview)) {
            return false;
        }

        return $this->filterOperandBodyContainsDictionary($preview);
    }

    private function filterOperandBodyContainsDictionary(string $body): bool
    {
        $body = ltrim($body);
        if (str_starts_with($body, '<<')) {
            return true;
        }

        if (!str_starts_with($body, '[')) {
            return false;
        }

        $arrayBody = $this->readPdfArrayAt($body, 0);
        if ($arrayBody === null) {
            return false;
        }

        foreach ($this->pdfArrayItems($arrayBody) as $item) {
            if (str_starts_with(ltrim($item), '<<')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $operand
     */
    private function streamFilterOperandIsMalformed(array $operand): bool
    {
        $valid = $operand['valid_filter_operand'] ?? null;
        return $valid === false && !$this->streamFilterOperandIsDictionary($operand);
    }

    /**
     * @param list<string|null>|null $filters
     */
    private function streamFilterOperandPolicy(
        ?array $filters,
        int $invalidCount,
        int $dictionaryCount,
        int $malformedCount = 0
    ): string
    {
        if ($dictionaryCount > 0) {
            return 'reject_dictionary_filter_operands';
        }

        if ($malformedCount > 0) {
            return 'reject_malformed_filter_operands';
        }

        if ($invalidCount > 0) {
            return 'reject_unresolved_filter_operands';
        }

        if ($filters === null) {
            return 'filter_resolution_failed';
        }

        return 'filters_resolved';
    }

    /**
     * @param list<string|null>|null $decodeParms
     */
    private function streamDecodeParmsOperandPolicy(
        ?array $decodeParms,
        int $invalidOperandCount,
        int $malformedOperandCount,
        int $invalidParameterCount
    ): string {
        if ($invalidParameterCount > 0) {
            return 'reject_malformed_decodeparms_parameters';
        }

        if ($malformedOperandCount > 0) {
            return 'reject_malformed_decodeparms_operands';
        }

        if ($invalidOperandCount > 0) {
            return 'reject_unresolved_decodeparms_operands';
        }

        if ($decodeParms === null) {
            return 'decodeparms_resolution_failed';
        }

        return 'decodeparms_resolved';
    }

    /**
     * @param list<array<string, mixed>> $operands
     */
    private function xrefStreamSelectedOperandCount(array $operands): int
    {
        $count = 0;
        foreach ($operands as $operand) {
            if (($operand['kind'] ?? null) === 'indirect' && ($operand['xref_selected'] ?? false) === true) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param list<array<string, mixed>> $operands
     */
    private function xrefStreamUnresolvedOperandCount(array $operands): int
    {
        $count = 0;
        foreach ($operands as $operand) {
            if (
                ($operand['kind'] ?? null) !== 'direct'
                && (($operand['resolved'] ?? false) !== true || ($operand['xref_selected'] ?? false) !== true)
            ) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param list<array<string, mixed>> $operands
     */
    private function xrefStreamOperandOwnerPolicy(int $selectedOperandCount, int $unresolvedOperandCount, array $operands): string
    {
        if ($operands === []) {
            return 'direct_or_absent_operands';
        }

        if ($unresolvedOperandCount > 0) {
            return 'unresolved_or_unselected_indirect_operands';
        }

        return $selectedOperandCount > 0 ? 'xref_selected_indirect_operands' : 'direct_operands';
    }

    /**
     * @param array<int, string> $objects
     * @return array{decoded: string, first: int, members: list<array{objectNumber: int, offset: int, index: int}>}|null
     */
    private function decodedObjectStreamMemberTable(string $body, array $objects): ?array
    {
        $decoded = $this->decodeStreamObject($body, $objects);
        if ($decoded === null) {
            return null;
        }

        $count = $this->pdfIntegerValueAfterNameResolvingObjects($body, 'N', $objects);
        $first = $this->pdfIntegerValueAfterNameResolvingObjects($body, 'First', $objects);
        if ($count === null || $first === null) {
            return null;
        }

        $count = max(0, $count);
        if ($count === 0 || $first < 0 || $first >= strlen($decoded)) {
            return null;
        }

        $members = $this->objectStreamHeaderMembers(substr($decoded, 0, $first), $count);
        if ($members === []) {
            return null;
        }

        return [
            'decoded' => $decoded,
            'first' => $first,
            'members' => $members,
        ];
    }

    /**
     * @return list<array{objectNumber: int, offset: int, index: int}>
     */
    private function objectStreamHeaderMembers(string $header, int $count): array
    {
        $members = [];
        $offset = 0;
        for ($index = 0; $index < $count; $index++) {
            $objectNumber = $this->readPdfUnsignedIntegerToken($header, $offset);
            if ($objectNumber === null) {
                return [];
            }

            $objectOffset = $this->readPdfUnsignedIntegerToken($header, $offset);
            if ($objectOffset === null) {
                return [];
            }

            if ($objectNumber === 0) {
                continue;
            }

            $members[] = [
                'objectNumber' => $objectNumber,
                'offset' => $objectOffset,
                'index' => $index,
            ];
        }

        return $members;
    }

    private function readPdfUnsignedIntegerToken(string $value, int &$offset): ?int
    {
        $offset = $this->skipPdfWhitespace($value, $offset);
        if (preg_match('/\G(\d+)(?=$|[\s\[\]()<>{}\/%])/s', $value, $match, 0, $offset) !== 1) {
            return null;
        }

        $offset += strlen($match[1]);

        return (int) $match[1];
    }

    /**
     * @param array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}> $xrefEntries
     */
    private function hasCompressedXrefEntriesForObjectStream(array $xrefEntries, int $objectStreamNumber): bool
    {
        foreach ($xrefEntries as $entry) {
            if (($entry['type'] ?? null) === 2 && ($entry['objectStream'] ?? null) === $objectStreamNumber) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}>
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     */
    private function xrefStreamEntries(array $objects, array $definitions): array
    {
        $entries = [];
        foreach ($this->xrefStreamDefinitionsInFileOrder($definitions) as $definition) {
            foreach ($this->xrefStreamEntriesFromDefinition($definition, $objects, $definitions) as $objectNumber => $entry) {
                $entries[$objectNumber] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}>
     */
    private function xrefStreamEntriesAtOffset(int $offset, array $objects, array $definitions): array
    {
        $section = $this->xrefStreamSectionAtOffset($offset, $definitions);
        return $section === null ? [] : $this->xrefStreamEntriesFromDefinition($section['definition'], $objects, $definitions);
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array{definition: array{generation: int, offset: int, body: string}, body: string}|null
     */
    private function xrefStreamSectionAtOffset(int $offset, array $definitions): ?array
    {
        $definition = $this->xrefStreamDefinitionAtOffset($definitions, $offset);
        if ($definition === null) {
            return null;
        }

        return [
            'definition' => $definition,
            'body' => $definition['body'],
        ];
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function xrefStreamDefinitionAtOffset(array $definitions, int $offset): ?array
    {
        foreach ($definitions as $entries) {
            foreach ($entries as $definition) {
                if ($definition['offset'] === $offset && preg_match('/\/Type\s*\/XRef\b/', $definition['body']) === 1) {
                    return $definition;
                }
            }
        }

        return null;
    }

    /**
     * @param array{generation: int, offset: int, body: string} $definition
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, body: string}>>|null $definitions
     * @return array<int, array{type: int, generation?: int, offset?: int, offsetIsExplicit?: bool, objectStream?: int, index?: int, indexIsExplicit?: bool}>
     */
    private function xrefStreamEntriesFromDefinition(array $definition, array $objects, ?array $definitions = null): array
    {
        $entries = [];
        $body = $definition['body'];
        $dictionary = $this->dictionaryObjectBody($body) ?? $body;

        $streamObjects = $definitions === null
            ? $objects
            : $this->objectsWithDirectStreamDictionaryOperandOwners($objects, $body, $definitions, $definition['offset']);
        $widthArray = $this->pdfArrayValueAfterNameResolvingObjects($dictionary, 'W', $streamObjects);
        if ($widthArray === null) {
            return $entries;
        }

        $widths = $this->integersFromPdfArray($widthArray);
        if (count($widths) < 3) {
            return $entries;
        }
        $widths = array_slice($widths, 0, 3);
        $entryWidth = array_sum($widths);
        if ($entryWidth <= 0) {
            return $entries;
        }

        $decoded = $this->decodeStreamObject($body, $streamObjects);
        if ($decoded === null) {
            return $entries;
        }

        $decodedEntryCount = strlen($decoded) % $entryWidth === 0 ? intdiv(strlen($decoded), $entryWidth) : null;
        $previousOffset = $definitions === null
            ? null
            : $this->pdfIntegerValueAfterNameResolvingObjects($dictionary, 'Prev', $streamObjects);
        $xrefOffset = (int) $definition['offset'];
        $offset = 0;
        foreach ($this->xrefIndexRanges($dictionary, $decodedEntryCount, $streamObjects) as $range) {
            [$startObject, $count] = $range;
            for ($index = 0; $index < $count; $index++) {
                if ($offset + $entryWidth > strlen($decoded)) {
                    break 2;
                }

                $fieldOffset = $offset;
                $type = $widths[0] === 0 ? 1 : $this->xrefFieldValue($decoded, $fieldOffset, $widths[0]);
                $fieldTwo = $this->xrefFieldValue($decoded, $fieldOffset, $widths[1]);
                $fieldThree = $this->xrefFieldValue($decoded, $fieldOffset, $widths[2]);
                $objectNumber = $startObject + $index;
                $generation = $fieldThree;
                if ($type === 1 && $widths[1] > 0 && $definitions !== null) {
                    $rowObjectNumber = $objectNumber;
                    $rowGeneration = $generation;
                    $offsetOwner = $this->directObjectDefinitionAtOffset($definitions, $fieldTwo);
                    $updateOwner = $previousOffset !== null && $previousOffset >= 0
                        ? $this->currentUpdateDirectObjectDefinitionForStaleXrefOffset(
                            $rowObjectNumber,
                            $rowGeneration,
                            $offsetOwner,
                            $previousOffset,
                            $xrefOffset,
                            $definitions
                        )
                        : null;
                    if ($updateOwner !== null) {
                        $objectNumber = $rowObjectNumber;
                        $fieldTwo = $updateOwner['offset'];
                        $generation = $updateOwner['generation'];
                    } elseif ($offsetOwner !== null) {
                        $objectNumber = $offsetOwner['objectNumber'];
                        $generation = $offsetOwner['generation'];
                    }
                }

                if (isset($entries[$objectNumber])) {
                    $offset += $entryWidth;
                    continue;
                }

                if ($type === 0) {
                    $entries[$objectNumber] = [
                        'type' => 0,
                        'generation' => $generation,
                        'offset' => $fieldTwo,
                        'offsetIsExplicit' => $widths[1] > 0,
                    ];
                } elseif ($type === 1) {
                    $entries[$objectNumber] = [
                        'type' => 1,
                        'offset' => $fieldTwo,
                        'generation' => $generation,
                        'offsetIsExplicit' => $widths[1] > 0,
                    ];
                } elseif ($type === 2 && $fieldTwo > 0) {
                    $entries[$objectNumber] = [
                        'type' => 2,
                        'objectStream' => $fieldTwo,
                        'index' => $fieldThree,
                        'indexIsExplicit' => $widths[2] > 0,
                    ];
                }

                $offset += $entryWidth;
            }
        }

        return $entries;
    }

    /**
     * Xref-stream rows are needed before the final xref owner map exists.
     * Resolve the stream dictionary operands by exact object generation so
     * later stale same-number helpers cannot break current-base recovery.
     *
     * @param array<int, string> $objects
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array<int, string>
     */
    private function objectsWithDirectStreamDictionaryOperandOwners(
        array $objects,
        string $objectBody,
        array $definitions,
        ?int $beforeOffset = null
    ): array {
        $dict = $this->dictionaryObjectBody($objectBody);
        if ($dict === null) {
            return $objects;
        }

        $pending = [];
        foreach (['Length', 'Filter', 'DecodeParms', 'W', 'Index', 'Size', 'Prev'] as $name) {
            $offset = $this->topLevelNameValueOffset($dict, $name);
            if ($offset === null) {
                continue;
            }

            $value = $this->pdfValueAtOffset($dict, $offset);
            if ($value === null) {
                continue;
            }

            foreach ($this->pdfObjectReferencePairs($value) as $reference) {
                $pending[] = $reference;
            }
        }

        $resolved = $objects;
        $seen = [];
        while ($pending !== [] && count($seen) < 32) {
            $reference = array_shift($pending);
            if (!is_array($reference)) {
                continue;
            }

            $objectNumber = $reference['objectNumber'];
            $generation = $reference['generation'];
            $key = $objectNumber . ':' . $generation;
            if ($objectNumber <= 0 || $generation < 0 || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $definition = $this->directObjectDefinitionForGeneration($definitions[$objectNumber] ?? [], $generation);
            $compressed = $this->compressedStreamDictionaryOperandHelperBeforeOffset(
                $definitions,
                $resolved,
                $objectNumber,
                $generation,
                $beforeOffset
            );
            if ($definition === null && $compressed === null) {
                continue;
            }

            $usesCompressedOwner = $compressed !== null
                && (
                    $definition === null
                    || $compressed['carrierOffset'] > $definition['offset']
                );
            $body = trim($usesCompressedOwner ? $compressed['body'] : $definition['body']);
            if (!$this->directObjectStreamFilterHelperBodyIsSafe($body)) {
                continue;
            }

            $resolved[$objectNumber] = $body;
            foreach ($this->pdfObjectReferencePairs($body) as $nestedReference) {
                $pending[] = $nestedReference;
            }
        }

        return $resolved;
    }

    /**
     * Before an xref stream has decoded, its own stream dictionary operands can
     * still be current generation-zero members of a direct object stream. This
     * keeps newer compressed helper operands from losing to stale scanned
     * direct same-number operands while staying bounded to safe helper values.
     *
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @param array<int, string> $objects
     * @return array{body: string, carrierOffset: int}|null
     */
    private function compressedStreamDictionaryOperandHelperBeforeOffset(
        array $definitions,
        array $objects,
        int $objectNumber,
        int $generation,
        ?int $beforeOffset
    ): ?array {
        if ($objectNumber <= 0 || $generation !== 0) {
            return null;
        }

        $selected = null;
        foreach ($definitions as $carrierDefinitions) {
            foreach ($carrierDefinitions as $definition) {
                if ($beforeOffset !== null && $definition['offset'] >= $beforeOffset) {
                    continue;
                }

                if (preg_match('/\/Type\s*\/ObjStm\b/', $definition['body']) !== 1) {
                    continue;
                }

                $memberTable = $this->decodedObjectStreamMemberTable($definition['body'], $objects);
                if ($memberTable === null) {
                    continue;
                }

                $objectDataLength = strlen($memberTable['decoded']) - $memberTable['first'];
                foreach ($memberTable['members'] as $member) {
                    if ($member['objectNumber'] !== $objectNumber) {
                        continue;
                    }

                    $nextOffset = $this->objectStreamMemberEndOffset(
                        $memberTable['members'],
                        $member['offset'],
                        $objectDataLength
                    );
                    if ($nextOffset === null) {
                        continue;
                    }

                    $body = trim(substr(
                        $memberTable['decoded'],
                        $memberTable['first'] + $member['offset'],
                        $nextOffset - $member['offset']
                    ));
                    if (!$this->directObjectStreamFilterHelperBodyIsSafe($body)) {
                        continue;
                    }

                    if ($selected === null || $definition['offset'] > $selected['carrierOffset']) {
                        $selected = [
                            'body' => $body,
                            'carrierOffset' => $definition['offset'],
                        ];
                    }
                }
            }
        }

        return $selected;
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array{objectNumber: int, generation: int, offset: int, body: string}|null
     */
    private function directObjectDefinitionAtOffset(array $definitions, int $offset): ?array
    {
        foreach ($definitions as $objectNumber => $entries) {
            foreach ($entries as $definition) {
                if ($definition['offset'] === $offset) {
                    return [
                        'objectNumber' => $objectNumber,
                        'generation' => $definition['generation'],
                        'offset' => $definition['offset'],
                        'body' => $definition['body'],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param array{objectNumber: int, generation: int, offset: int, body: string}|null $offsetOwner
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function currentUpdateDirectObjectDefinitionForStaleXrefOffset(
        int $objectNumber,
        int $generation,
        ?array $offsetOwner,
        int $previousOffset,
        int $xrefOffset,
        array $definitions
    ): ?array {
        if (
            $offsetOwner !== null
            && $offsetOwner['offset'] > $previousOffset
            && $offsetOwner['offset'] < $xrefOffset
        ) {
            if (
                $offsetOwner['objectNumber'] === $objectNumber
                && $offsetOwner['generation'] === $generation
            ) {
                return null;
            }

            return $this->currentUpdateDirectObjectDefinitionForXrefRow(
                $objectNumber,
                $generation,
                $previousOffset,
                $xrefOffset,
                $definitions
            );
        }

        return $this->currentUpdateDirectObjectDefinitionForXrefRow(
            $objectNumber,
            $generation,
            $previousOffset,
            $xrefOffset,
            $definitions
        );
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function currentUpdateDirectObjectDefinitionForXrefRow(
        int $objectNumber,
        int $generation,
        int $previousOffset,
        int $xrefOffset,
        array $definitions
    ): ?array {
        if ($objectNumber <= 0 || $previousOffset < 0 || $xrefOffset <= $previousOffset) {
            return null;
        }

        $candidates = [];
        foreach ($definitions[$objectNumber] ?? [] as $definition) {
            if (
                $definition['generation'] !== $generation
                || $definition['offset'] <= $previousOffset
                || $definition['offset'] >= $xrefOffset
            ) {
                continue;
            }

            $candidates[] = $definition;
        }

        return $this->latestDirectObjectDefinition($candidates);
    }

    /**
     * @return list<array{generation: int, offset: int, body: string}>
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     */
    private function xrefStreamDefinitionsInFileOrder(array $definitions): array
    {
        $xrefStreams = [];
        foreach ($definitions as $entries) {
            foreach ($entries as $definition) {
                if (preg_match('/\/Type\s*\/XRef\b/', $definition['body']) === 1) {
                    $xrefStreams[] = $definition;
                }
            }
        }

        usort(
            $xrefStreams,
            static fn (array $left, array $right): int => $left['offset'] <=> $right['offset']
        );

        return $xrefStreams;
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    private function xrefIndexRanges(string $xrefBody, ?int $decodedEntryCount = null, array $objects = []): array
    {
        $indexArray = $this->pdfArrayValueAfterNameResolvingObjects($xrefBody, 'Index', $objects);
        if ($indexArray !== null) {
            $values = $this->integersFromPdfArray($indexArray);
            $ranges = [];
            for ($index = 0, $count = count($values); $index + 1 < $count; $index += 2) {
                $ranges[] = [max(0, $values[$index]), max(0, $values[$index + 1])];
            }

            return $ranges;
        }

        $sizeValue = $this->pdfIntegerValueAfterNameResolvingObjects($xrefBody, 'Size', $objects);
        if ($sizeValue !== null) {
            $size = max(0, $sizeValue);
            if ($decodedEntryCount !== null && $decodedEntryCount > $size) {
                $size = $decodedEntryCount;
            }

            return [[0, $size]];
        }

        return [];
    }

    /**
     * @return list<int>
     */
    private function integersFromPdfArray(string $arrayBody): array
    {
        $integers = [];
        foreach ($this->pdfArrayItems($arrayBody) as $item) {
            $item = trim($item);
            if (preg_match('/^[+-]?\d+$/', $item) !== 1) {
                continue;
            }

            $integers[] = (int) $item;
        }

        return $integers;
    }

    /**
     * @return list<float>
     */
    private function numbersFromPdfArray(string $arrayBody): array
    {
        if (!preg_match_all('/[+-]?(?:\d+(?:\.\d*)?|\.\d+)/', $arrayBody, $matches)) {
            return [];
        }

        return array_map('floatval', $matches[0]);
    }

    private function xrefFieldValue(string $bytes, int &$offset, int $width): int
    {
        $value = 0;
        for ($index = 0; $index < $width; $index++) {
            $value = ($value << 8) + ord($bytes[$offset + $index]);
        }
        $offset += $width;

        return $value;
    }

    /**
     * @return array<string, string>
     * @param array<int, string> $objects
     */
    private function namedCMapBodies(array $objects): array
    {
        $named = [];
        foreach ($objects as $body) {
            $cmap = $this->decodedCMapBody($body, $objects);
            $name = is_string($cmap) ? $this->cMapName($cmap) : null;
            if ($cmap === null || $name === null) {
                continue;
            }

            $named[$name] = $cmap;
        }

        return $named;
    }

    /**
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}|null
     * @param array<int, string> $objects
     * @param array<string, string> $namedCMapBodies
     */
    private function toUnicodeMapFromObject(string $objectBody, array $objects, array $namedCMapBodies): ?array
    {
        $decoded = $this->decodedCMapBody($objectBody, $objects);
        if ($decoded === null) {
            return null;
        }

        $currentName = $this->cMapName($decoded);

        return $this->parseToUnicodeCMap($decoded, $namedCMapBodies, $currentName === null ? [] : [$currentName]);
    }

    private function cMapName(string $cmap): ?string
    {
        $cmap = $this->stripPdfLineComments($cmap);
        if (preg_match('/\/CMapName\s+\/([^\s\[\]()<>{}\/%]+)\s+def\b/s', $cmap, $match) !== 1) {
            return null;
        }

        return $this->decodePdfName($match[1]);
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodedCMapBody(string $objectBody, array $objects, array $seenCMapObjects = []): ?string
    {
        $entry = $this->streamDictionaryAndPayload($objectBody, $objects);
        if ($entry === null) {
            return null;
        }

        $decoded = $this->decodeStream($entry['dict'], $entry['stream'], $objects);
        if ($decoded === null) {
            return null;
        }

        return $this->cMapStreamDictionaryPrelude($entry['dict'], $objects, $seenCMapObjects) . $decoded;
    }

    /**
     * @param array<int, string> $objects
     */
    private function cMapStreamDictionaryPrelude(string $dict, array $objects, array $seenCMapObjects = []): string
    {
        $lines = [];
        $useCMapName = $this->cMapUseCMapNameFromDictionary($dict, $objects);
        if ($useCMapName !== null && $useCMapName !== '') {
            $lines[] = '/' . $this->encodePdfName($useCMapName) . ' usecmap';
        } else {
            $useCMapObjectNumber = $this->objectReferenceValueAfterName($dict, 'UseCMap');
            if ($useCMapObjectNumber !== null && isset($objects[$useCMapObjectNumber]) && !isset($seenCMapObjects[$useCMapObjectNumber])) {
                $seenCMapObjects[$useCMapObjectNumber] = true;
                $baseCMap = $this->decodedCMapBody($objects[$useCMapObjectNumber], $objects, $seenCMapObjects);
                if ($baseCMap !== null && trim($baseCMap) !== '') {
                    $lines[] = rtrim($baseCMap);
                }
            }
        }

        $cMapName = $this->pdfNameValueAfterNameResolvingObjects($dict, 'CMapName', $objects);
        if ($cMapName !== null && $cMapName !== '') {
            $lines[] = '/CMapName /' . $this->encodePdfName($cMapName) . ' def';
        }

        $writingMode = $this->pdfIntegerValueAfterNameResolvingObjects($dict, 'WMode', $objects);
        if ($writingMode !== null) {
            $lines[] = '/WMode ' . ($writingMode === 1 ? '1' : '0') . ' def';
        } elseif ($useCMapName !== null) {
            $inheritedMode = $this->cMapNameWritingMode($useCMapName);
            if ($inheritedMode !== null) {
                $lines[] = '/WMode ' . $inheritedMode . ' def';
            }
        }

        return $lines === [] ? '' : implode("\n", $lines) . "\n";
    }

    /**
     * @param array<int, string> $objects
     */
    private function cMapUseCMapNameFromDictionary(string $dict, array $objects): ?string
    {
        $name = $this->pdfNameValueAfterNameResolvingObjects($dict, 'UseCMap', $objects);
        if ($name !== null && $name !== '') {
            return $name;
        }

        $objectNumber = $this->objectReferenceValueAfterName($dict, 'UseCMap');
        if ($objectNumber === null || !isset($objects[$objectNumber])) {
            return null;
        }

        return $this->cMapNameFromObjectBody($objects[$objectNumber], $objects);
    }

    /**
     * @param array<int, string> $objects
     */
    private function cMapNameFromObjectBody(string $objectBody, array $objects): ?string
    {
        $entry = $this->streamDictionaryAndPayload($objectBody, $objects);
        if ($entry !== null) {
            $name = $this->pdfNameValueAfterNameResolvingObjects($entry['dict'], 'CMapName', $objects);
            if ($name !== null && $name !== '') {
                return $name;
            }

            $decoded = $this->decodeStream($entry['dict'], $entry['stream'], $objects);
            return $decoded === null ? null : $this->cMapName($decoded);
        }

        return $this->cMapName($objectBody);
    }

    /**
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}
     * @param array<string, string> $namedCMapBodies
     * @param list<string> $seenCMaps
     */
    private function parseToUnicodeCMap(string $cmap, array $namedCMapBodies = [], array $seenCMaps = []): array
    {
        $cmap = $this->stripPdfLineComments($cmap);
        $map = [];
        $codeSpaceRanges = [];
        $writingMode = null;

        if (preg_match_all('/\/([^\s\[\]()<>{}\/%]+)\s+usecmap\b/s', $cmap, $useCMapMatches)) {
            foreach ($useCMapMatches[1] as $rawName) {
                $name = $this->decodePdfName($rawName);
                if (in_array($name, $seenCMaps, true) || !isset($namedCMapBodies[$name])) {
                    continue;
                }

                $base = $this->parseToUnicodeCMap($namedCMapBodies[$name], $namedCMapBodies, [...$seenCMaps, $name]);
                $map = $base['map'] + $map;
                if (isset($base['writingMode'])) {
                    $writingMode = (int) $base['writingMode'] === 1 ? 1 : 0;
                }
                foreach ($base['codeSpaceRanges'] as $range) {
                    $codeSpaceRanges[$range['start'] . ':' . $range['end'] . ':' . $range['width']] = $range;
                }
            }
        }

        if (preg_match_all('/\/WMode\s+([01])\s+def\b/s', $cmap, $wModeMatches) > 0) {
            $lastMode = end($wModeMatches[1]);
            $writingMode = (int) $lastMode === 1 ? 1 : 0;
        }

        foreach ($this->cMapOperatorBlocks($cmap, 'beginbfchar', 'endbfchar') as $charBlock) {
            $block = $charBlock['body'];
            $declaredCount = $charBlock['declaredCount'];
            if (preg_match_all('/<([\da-fA-F\s]+)>\s*<([\da-fA-F\s]+)>/s', $block, $entries, PREG_SET_ORDER)) {
                if ($declaredCount !== null) {
                    $entries = array_slice($entries, 0, max(0, $declaredCount));
                }

                foreach ($entries as $entry) {
                    $source = $this->normalizeHexKey($entry[1]);
                    if ($source !== '') {
                        $map[$source] = $this->decodeCMapUnicodeHex($entry[2]);
                    }
                }
            }
        }

        foreach ($this->cMapOperatorBlocks($cmap, 'beginbfrange', 'endbfrange') as $rangeBlock) {
            $this->parseToUnicodeRanges($rangeBlock['body'], $map, $rangeBlock['declaredCount']);
        }

        foreach ($this->parseCMapCodeSpaceRanges($cmap) as $range) {
            $codeSpaceRanges[$range['start'] . ':' . $range['end'] . ':' . $range['width']] = $range;
        }
        $codeSpaceRanges = array_values($codeSpaceRanges);
        usort($codeSpaceRanges, static function (array $left, array $right): int {
            return $right['width'] <=> $left['width'] ?: $left['start'] <=> $right['start'];
        });

        $result = [
            'map' => $map,
            'codeSpaceRanges' => $codeSpaceRanges,
        ];
        if ($writingMode !== null) {
            $result['writingMode'] = $writingMode;
        }

        return $result;
    }

    /**
     * @return array{cidMap: array<string, int>, codeSpaceRanges: list<array{start: int, end: int, width: int}>, writingMode?: int}
     * @param array<string, string> $namedCMapBodies
     * @param list<string> $seenCMaps
     */
    private function parseCidCMap(string $cmap, array $namedCMapBodies = [], array $seenCMaps = []): array
    {
        $cmap = $this->stripPdfLineComments($cmap);
        $cidMap = [];
        $codeSpaceRanges = [];
        $writingMode = null;

        if (preg_match_all('/\/([^\s\[\]()<>{}\/%]+)\s+usecmap\b/s', $cmap, $useCMapMatches)) {
            foreach ($useCMapMatches[1] as $rawName) {
                $name = $this->decodePdfName($rawName);
                if (in_array($name, $seenCMaps, true)) {
                    continue;
                }

                $base = isset($namedCMapBodies[$name])
                    ? $this->parseCidCMap($namedCMapBodies[$name], $namedCMapBodies, [...$seenCMaps, $name])
                    : $this->predefinedCidCMap($name);
                if ($base === null) {
                    continue;
                }

                $cidMap = $base['cidMap'] + $cidMap;
                if (isset($base['writingMode'])) {
                    $writingMode = (int) $base['writingMode'] === 1 ? 1 : 0;
                }
                foreach ($base['codeSpaceRanges'] as $range) {
                    $codeSpaceRanges[$range['start'] . ':' . $range['end'] . ':' . $range['width']] = $range;
                }
            }
        }

        if (preg_match_all('/\/WMode\s+([01])\s+def\b/s', $cmap, $wModeMatches) > 0) {
            $lastMode = end($wModeMatches[1]);
            $writingMode = (int) $lastMode === 1 ? 1 : 0;
        }

        foreach ($this->cMapOperatorBlocks($cmap, 'begincidchar', 'endcidchar') as $charBlock) {
            $this->parseCidChars($charBlock['body'], $cidMap, $charBlock['declaredCount']);
        }

        foreach ($this->cMapOperatorBlocks($cmap, 'begincidrange', 'endcidrange') as $rangeBlock) {
            $this->parseCidRanges($rangeBlock['body'], $cidMap, $rangeBlock['declaredCount']);
        }

        foreach ($this->parseCMapCodeSpaceRanges($cmap) as $range) {
            $codeSpaceRanges[$range['start'] . ':' . $range['end'] . ':' . $range['width']] = $range;
        }
        $codeSpaceRanges = array_values($codeSpaceRanges);
        usort($codeSpaceRanges, static function (array $left, array $right): int {
            return $right['width'] <=> $left['width'] ?: $left['start'] <=> $right['start'];
        });

        $result = [
            'cidMap' => $cidMap,
            'codeSpaceRanges' => $codeSpaceRanges,
        ];
        if ($writingMode !== null) {
            $result['writingMode'] = $writingMode;
        }

        return $result;
    }

    /**
     * @return array{cidMap: array<string, int>, codeSpaceRanges: list<array{start: int, end: int, width: int}>, writingMode: int}|null
     */
    private function predefinedCidCMap(string $name): ?array
    {
        if ($name !== 'Identity-H' && $name !== 'Identity-V') {
            return null;
        }

        return [
            'cidMap' => [],
            'codeSpaceRanges' => [
                ['start' => 0, 'end' => 0xffff, 'width' => 4],
            ],
            'writingMode' => $name === 'Identity-V' ? 1 : 0,
        ];
    }

    private function stripPdfLineComments(string $source): string
    {
        $stripped = '';
        $length = strlen($source);
        for ($index = 0; $index < $length; $index++) {
            $char = $source[$index];
            if ($char === '(') {
                $stripped .= $this->readLiteralToken($source, $index);
                $index--;
                continue;
            }

            if ($char === '<' && ($index + 1 >= $length || $source[$index + 1] !== '<')) {
                $stripped .= $this->readHexToken($source, $index);
                $index--;
                continue;
            }

            if ($char === '%') {
                while ($index < $length && !in_array($source[$index], ["\n", "\r"], true)) {
                    $index++;
                }
                if ($index < $length) {
                    $stripped .= $source[$index];
                }
                continue;
            }

            $stripped .= $char;
        }

        return $stripped;
    }

    /**
     * @return list<array{body: string, declaredCount: int|null}>
     */
    private function cMapOperatorBlocks(string $cmap, string $beginOperator, string $endOperator): array
    {
        $pattern = '/(?:(\d+)\s+)?' . preg_quote($beginOperator, '/') . '(.*?)' . preg_quote($endOperator, '/') . '/s';
        if (!preg_match_all($pattern, $cmap, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $blocks = [];
        foreach ($matches as $match) {
            $rawCount = $match[1] ?? '';
            $blocks[] = [
                'body' => $match[2],
                'declaredCount' => $rawCount === '' ? null : max(0, (int) $rawCount),
            ];
        }

        return $blocks;
    }

    /**
     * @param array<string, string> $map
     */
    private function parseToUnicodeRanges(string $block, array &$map, ?int $declaredCount = null): void
    {
        if (!preg_match_all('/<([\da-fA-F\s]+)>\s*<([\da-fA-F\s]+)>\s*(?:\[(.*?)\]|<([\da-fA-F\s]+)>)/s', $block, $ranges, PREG_SET_ORDER)) {
            return;
        }

        if ($declaredCount !== null) {
            $ranges = array_slice($ranges, 0, max(0, $declaredCount));
        }

        foreach ($ranges as $range) {
            $start = $this->normalizeHexKey($range[1]);
            $end = $this->normalizeHexKey($range[2]);
            if ($start === '' || $end === '') {
                continue;
            }

            $source = hexdec($start);
            $last = hexdec($end);
            $sourceWidth = strlen($start);
            $arrayTargets = $range[3] ?? '';
            if ($arrayTargets !== '') {
                preg_match_all('/<([\da-fA-F\s]+)>/s', $arrayTargets, $targets);
                foreach ($targets[1] ?? [] as $target) {
                    if ($source > $last) {
                        break;
                    }

                    $sourceKey = str_pad(strtolower(dechex($source)), $sourceWidth, '0', STR_PAD_LEFT);
                    $map[$sourceKey] = $this->decodeCMapUnicodeHex($target);
                    $source++;
                }
                continue;
            }

            $target = $this->normalizeHexKey($range[4] ?? '');
            if ($target === '') {
                continue;
            }

            $count = 0;
            $targetHex = $target;
            while ($source <= $last && $count < 512) {
                $sourceKey = str_pad(strtolower(dechex($source)), $sourceWidth, '0', STR_PAD_LEFT);
                $map[$sourceKey] = $this->decodeCMapUnicodeHex($targetHex);
                $source++;
                $count++;
                $targetHex = $this->incrementFixedWidthHex($targetHex);
            }
        }
    }

    private function incrementFixedWidthHex(string $hex): string
    {
        $digits = str_split(strtolower($hex));
        $carry = 1;
        for ($index = count($digits) - 1; $index >= 0; $index--) {
            $value = hexdec($digits[$index]) + $carry;
            if ($value >= 16) {
                $digits[$index] = '0';
                continue;
            }

            $digits[$index] = dechex($value);
            $carry = 0;
            break;
        }

        return implode('', $digits);
    }

    /**
     * @param array<string, int> $cidMap
     */
    private function parseCidChars(string $block, array &$cidMap, ?int $declaredCount = null): void
    {
        if (!preg_match_all('/<([\da-fA-F\s]+)>\s+([+-]?\d+)/s', $block, $entries, PREG_SET_ORDER)) {
            return;
        }

        if ($declaredCount !== null) {
            $entries = array_slice($entries, 0, max(0, $declaredCount));
        }

        foreach ($entries as $entry) {
            $source = $this->normalizeHexKey($entry[1]);
            $cid = (int) $entry[2];
            if ($source !== '' && $cid >= 0 && $cid <= 0xffff) {
                $cidMap[$source] = $cid;
            }
        }
    }

    /**
     * @param array<string, int> $cidMap
     */
    private function parseCidRanges(string $block, array &$cidMap, ?int $declaredCount = null): void
    {
        if (!preg_match_all('/<([\da-fA-F\s]+)>\s*<([\da-fA-F\s]+)>\s*([+-]?\d+)/s', $block, $ranges, PREG_SET_ORDER)) {
            return;
        }

        if ($declaredCount !== null) {
            $ranges = array_slice($ranges, 0, max(0, $declaredCount));
        }

        foreach ($ranges as $range) {
            $start = $this->normalizeHexKey($range[1]);
            $end = $this->normalizeHexKey($range[2]);
            if ($start === '' || $end === '' || strlen($start) !== strlen($end)) {
                continue;
            }

            $source = hexdec($start);
            $last = hexdec($end);
            $cid = (int) $range[3];
            $sourceWidth = strlen($start);
            $count = 0;
            while ($source <= $last && $count < 512) {
                $currentCid = $cid + $count;
                if ($currentCid >= 0 && $currentCid <= 0xffff) {
                    $sourceKey = str_pad(strtolower(dechex($source)), $sourceWidth, '0', STR_PAD_LEFT);
                    $cidMap[$sourceKey] = $currentCid;
                }
                $source++;
                $count++;
            }
        }
    }

    /**
     * @return list<array{start: int, end: int, width: int}>
     */
    private function parseCMapCodeSpaceRanges(string $cmap): array
    {
        $ranges = [];
        if (!preg_match_all('/(?:(\d+)\s+)?begincodespacerange(.*?)endcodespacerange/s', $cmap, $blocks, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($blocks as $blockMatch) {
            $block = $blockMatch[2];
            if (!preg_match_all('/<([\da-fA-F\s]+)>\s*<([\da-fA-F\s]+)>/s', $block, $entries, PREG_SET_ORDER)) {
                continue;
            }

            if (($blockMatch[1] ?? '') !== '') {
                $entries = array_slice($entries, 0, max(0, (int) $blockMatch[1]));
            }

            foreach ($entries as $entry) {
                $start = $this->normalizeHexKey($entry[1]);
                $end = $this->normalizeHexKey($entry[2]);
                if ($start === '' || $end === '' || strlen($start) !== strlen($end) || strlen($start) > 8) {
                    continue;
                }

                $startValue = hexdec($start);
                $endValue = hexdec($end);
                if ($startValue > $endValue) {
                    continue;
                }

                $ranges[$start . ':' . $end] = [
                    'start' => $startValue,
                    'end' => $endValue,
                    'width' => strlen($start),
                ];
            }
        }

        $ranges = array_values($ranges);
        usort($ranges, static function (array $left, array $right): int {
            return $right['width'] <=> $left['width'] ?: $left['start'] <=> $right['start'];
        });

        return $ranges;
    }

    private function normalizeHexKey(string $hex): string
    {
        $normalized = preg_replace('/\s+/', '', strtolower($hex));
        if ($normalized === null || $normalized === '' || preg_match('/^[\da-f]+$/', $normalized) !== 1) {
            return '';
        }
        if (strlen($normalized) % 2 === 1) {
            $normalized = '0' . $normalized;
        }

        return $normalized;
    }

    private function decodeCMapUnicodeHex(string $hex): string
    {
        $normalized = $this->normalizeHexKey($hex);
        if ($normalized === '') {
            return '';
        }

        if (strlen($normalized) % 4 === 0) {
            $bytes = hex2bin($normalized);
            if ($bytes !== false) {
                $decoded = iconv('UTF-16BE', 'UTF-8//IGNORE', $bytes);
                if ($decoded !== false) {
                    return $decoded;
                }
            }
        }

        return $this->decodeHexString($normalized);
    }

    /**
     * @return list<string>
     * @param array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontToUnicodeMaps
     * @param array<string, array{actualText: string|null, altText: string|null}> $markedContentProperties
     */
    private function textRunsFromContentStream(string $stream, array $fontToUnicodeMaps, array $markedContentProperties = []): array
    {
        $runs = [];
        $operands = [];
        $currentFontResource = null;
        $currentFontSize = null;
        $textRenderingMode = 0;
        $textStateStack = [];
        $markedContentStack = [];
        $currentTextLeading = null;
        $currentTextX = null;
        $currentTextY = null;
        $characterSpacing = 0.0;
        $wordSpacing = 0.0;
        $horizontalScale = 100.0;
        $currentTextMatrixHorizontalScale = 1.0;
        $currentTransformationMatrix = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];
        $clipRectangle = null;
        $currentPathRectangle = null;
        $clipStateStack = [];
        foreach ($this->contentTokens($stream) as $token) {
            if ($this->isTextShowingOperator($token)) {
                if ($token === "'" || $token === '"') {
                    $currentTextY = $this->advanceTextYByLeading($currentTextY, $currentTextLeading);
                }

                if ($token === '"') {
                    $wordSpacing = $this->quoteWordSpacingOperand($operands) ?? $wordSpacing;
                    $characterSpacing = $this->quoteCharacterSpacingOperand($operands) ?? $characterSpacing;
                }

                $operand = $this->textShowingOperand($token, $operands);
                if ($operand !== null) {
                    $toUnicodeMap = $this->currentToUnicodeMap($fontToUnicodeMaps, $currentFontResource);
                    $replacementIndex = $this->activeMarkedContentReplacementIndex($markedContentStack);
                    $insideActiveClip = $this->textPositionInsideActiveClip($currentTextX, $currentTextY, $clipRectangle);
                    if (!$insideActiveClip) {
                        if ($replacementIndex !== null) {
                            $markedContentStack[$replacementIndex]['emitted'] = true;
                        }
                    } elseif (!$this->isVisibleTextRenderingMode($textRenderingMode)) {
                        if ($replacementIndex !== null) {
                            $markedContentStack[$replacementIndex]['emitted'] = true;
                        }
                    } elseif ($replacementIndex !== null) {
                        if (!$markedContentStack[$replacementIndex]['emitted']) {
                            $runs[] = $markedContentStack[$replacementIndex]['replacement'];
                            $markedContentStack[$replacementIndex]['emitted'] = true;
                        }
                    } else {
                        $runs[] = $this->decodePositionedTextOperand(
                            $operand,
                            $toUnicodeMap,
                            $currentFontSize,
                            $characterSpacing,
                            $wordSpacing,
                            $horizontalScale * $currentTextMatrixHorizontalScale
                        );
                    }
                }
                $operands = [];
                continue;
            }

            if ($token === 'q') {
                $textStateStack[] = [
                    'fontResource' => $currentFontResource,
                    'fontSize' => $currentFontSize,
                    'textLeading' => $currentTextLeading,
                    'characterSpacing' => $characterSpacing,
                    'wordSpacing' => $wordSpacing,
                    'horizontalScale' => $horizontalScale,
                    'textMatrixHorizontalScale' => $currentTextMatrixHorizontalScale,
                    'textRenderingMode' => $textRenderingMode,
                ];
                $clipStateStack[] = [
                    'clipRectangle' => $clipRectangle,
                    'currentPathRectangle' => $currentPathRectangle,
                    'currentTransformationMatrix' => $currentTransformationMatrix,
                ];
                $operands = [];
                continue;
            }

            if ($token === 'Q') {
                $state = array_pop($textStateStack);
                if (is_array($state)) {
                    $currentFontResource = $state['fontResource'];
                    $currentFontSize = $state['fontSize'];
                    $currentTextLeading = $state['textLeading'];
                    $characterSpacing = $state['characterSpacing'];
                    $wordSpacing = $state['wordSpacing'];
                    $horizontalScale = $state['horizontalScale'];
                    $currentTextMatrixHorizontalScale = $state['textMatrixHorizontalScale'];
                    $textRenderingMode = $state['textRenderingMode'];
                }
                $clipState = array_pop($clipStateStack);
                if (is_array($clipState)) {
                    $clipRectangle = $clipState['clipRectangle'];
                    $currentPathRectangle = $clipState['currentPathRectangle'];
                    $currentTransformationMatrix = $clipState['currentTransformationMatrix'];
                }
                $operands = [];
                continue;
            }

            if ($token === 'Tf') {
                $currentFontResource = $this->fontResourceOperand($operands) ?? $currentFontResource;
                $currentFontSize = $this->fontSizeOperand($operands) ?? $currentFontSize;
                $operands = [];
                continue;
            }

            if ($token === 'TL') {
                $currentTextLeading = $this->textLeadingOperand($operands) ?? $currentTextLeading;
                $operands = [];
                continue;
            }

            if ($token === 'Tc') {
                $characterSpacing = $this->textCharacterSpacingOperand($operands) ?? $characterSpacing;
                $operands = [];
                continue;
            }

            if ($token === 'Tw') {
                $wordSpacing = $this->textWordSpacingOperand($operands) ?? $wordSpacing;
                $operands = [];
                continue;
            }

            if ($token === 'Tz') {
                $horizontalScale = $this->textHorizontalScaleOperand($operands) ?? $horizontalScale;
                $operands = [];
                continue;
            }

            if ($token === 'Tr') {
                $textRenderingMode = $this->textRenderingModeOperand($operands) ?? $textRenderingMode;
                $operands = [];
                continue;
            }

            if ($token === 'Td' || $token === 'TD') {
                if ($token === 'TD') {
                    $moveY = $this->textMoveOperandY($operands);
                    if ($moveY !== null) {
                        $currentTextLeading = -$moveY;
                    }
                }
                $currentTextX = $this->textMoveX($operands, $currentTextX, $currentTextMatrixHorizontalScale);
                $currentTextY = $this->textMoveY($operands, $currentTextY);
                $operands = [];
                continue;
            }

            if ($token === 'Tm') {
                $currentTextX = $this->textMatrixX($operands);
                $currentTextY = $this->textMatrixY($operands);
                $currentTextMatrixHorizontalScale = $this->textMatrixHorizontalScale($operands) ?? 1.0;
                $operands = [];
                continue;
            }

            if ($token === 'T*') {
                $currentTextY = $this->advanceTextYByLeading($currentTextY, $currentTextLeading);
                $operands = [];
                continue;
            }

            if ($token === 'BT') {
                $currentTextX = 0.0;
                $currentTextY = 0.0;
                $currentTextMatrixHorizontalScale = 1.0;
                $operands = [];
                continue;
            }

            if ($token === 'ET') {
                $currentTextX = null;
                $currentTextY = null;
                $currentTextMatrixHorizontalScale = 1.0;
                $operands = [];
                continue;
            }

            if ($token === 'BMC') {
                $markedContentStack[] = [
                    'replacement' => null,
                    'emitted' => true,
                ];
                $operands = [];
                continue;
            }

            if ($token === 'BDC') {
                $markedContentStack[] = [
                    'replacement' => $this->markedContentReplacementOperand($operands, $markedContentProperties),
                    'emitted' => false,
                ];
                $operands = [];
                continue;
            }

            if ($token === 'EMC') {
                $markedContent = array_pop($markedContentStack);
                if (
                    is_array($markedContent)
                    && $markedContent['replacement'] !== null
                    && !$markedContent['emitted']
                    && $this->activeMarkedContentReplacementIndex($markedContentStack) === null
                ) {
                    $runs[] = $markedContent['replacement'];
                }
                $operands = [];
                continue;
            }

            if ($this->applyClipPathStateOperator(
                $token,
                $operands,
                $clipRectangle,
                $currentPathRectangle,
                $currentTransformationMatrix
            )) {
                $operands = [];
                continue;
            }

            if ($this->isOperator($token)) {
                $operands = [];
                continue;
            }

            $operands[] = $token;
        }

        return $runs;
    }

    /**
     * @return list<array{spans: list<array<string, mixed>>, bbox: list<float>}>
     * @param array<string, array<string, mixed>> $fontToUnicodeMaps
     * @param array<string, array{actualText: string|null, altText: string|null}> $markedContentProperties
     */
    private function textSpanLinesFromContentStream(
        string $stream,
        array $fontToUnicodeMaps,
        array $markedContentProperties,
        int $pageIndex
    ): array {
        $lines = [];
        $spans = [];
        $operands = [];
        $currentFontResource = null;
        $currentFontSize = 12.0;
        $currentTextX = null;
        $currentTextY = null;
        $currentTextLeading = null;
        $characterSpacing = 0.0;
        $wordSpacing = 0.0;
        $horizontalScale = 100.0;
        $currentTextMatrixHorizontalScale = 1.0;
        $currentTextMatrixVerticalScale = 1.0;
        $spanId = 0;
        $textRenderingMode = 0;
        $textStateStack = [];
        $markedContentStack = [];
        $currentTransformationMatrix = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];
        $clipRectangle = null;
        $currentPathRectangle = null;
        $clipStateStack = [];

        foreach ($this->contentTokens($stream) as $token) {
            if ($this->isTextShowingOperator($token)) {
                if ($token === "'" || $token === '"') {
                    $this->pushSpanLine($lines, $spans);
                    $currentTextY = $this->advanceTextYByLeading($currentTextY, $currentTextLeading);
                }

                if ($token === '"') {
                    $wordSpacing = $this->quoteWordSpacingOperand($operands) ?? $wordSpacing;
                    $characterSpacing = $this->quoteCharacterSpacingOperand($operands) ?? $characterSpacing;
                }

                $operand = $this->textShowingOperand($token, $operands);
                if ($operand !== null) {
                    $toUnicodeMap = $this->currentToUnicodeMap($fontToUnicodeMaps, $currentFontResource);
                    $replacementIndex = $this->activeMarkedContentReplacementIndex($markedContentStack);
                    $insideActiveClip = $this->textPositionInsideActiveClip($currentTextX, $currentTextY, $clipRectangle);
                    if (!$insideActiveClip) {
                        $decoded = '';
                        if ($replacementIndex !== null) {
                            $markedContentStack[$replacementIndex]['emitted'] = true;
                        }
                    } elseif (!$this->isVisibleTextRenderingMode($textRenderingMode)) {
                        $decoded = '';
                        if ($replacementIndex !== null) {
                            $markedContentStack[$replacementIndex]['emitted'] = true;
                        }
                    } elseif ($replacementIndex !== null) {
                        $decoded = $markedContentStack[$replacementIndex]['emitted']
                            ? ''
                            : $markedContentStack[$replacementIndex]['replacement'];
                        $markedContentStack[$replacementIndex]['emitted'] = true;
                    } else {
                        $decoded = $this->decodePositionedTextOperand(
                            $operand,
                            $toUnicodeMap,
                            $currentFontSize,
                            $characterSpacing,
                            $wordSpacing,
                            $horizontalScale * $currentTextMatrixHorizontalScale
                        );
                    }

                    $this->appendNativeTextSpan(
                        $spans,
                        (string) $decoded,
                        $currentFontResource,
                        $currentFontSize,
                        $fontToUnicodeMaps,
                        $pageIndex,
                        $spanId,
                        $operand,
                        $toUnicodeMap,
                        $characterSpacing,
                        $wordSpacing,
                        $horizontalScale * $currentTextMatrixHorizontalScale,
                        $currentTextMatrixVerticalScale
                    );
                }
                $operands = [];
                continue;
            }

            if ($token === 'BMC') {
                $markedContentStack[] = [
                    'replacement' => null,
                    'emitted' => true,
                ];
                $operands = [];
                continue;
            }

            if ($token === 'BDC') {
                $markedContentStack[] = [
                    'replacement' => $this->markedContentReplacementOperand($operands, $markedContentProperties),
                    'emitted' => false,
                ];
                $operands = [];
                continue;
            }

            if ($token === 'EMC') {
                $markedContent = array_pop($markedContentStack);
                if (
                    is_array($markedContent)
                    && $markedContent['replacement'] !== null
                    && !$markedContent['emitted']
                    && $this->activeMarkedContentReplacementIndex($markedContentStack) === null
                ) {
                    $this->appendNativeTextSpan(
                        $spans,
                        $markedContent['replacement'],
                        $currentFontResource,
                        $currentFontSize,
                        $fontToUnicodeMaps,
                        $pageIndex,
                        $spanId,
                        null,
                        null,
                        0.0,
                        0.0,
                        100.0,
                        $currentTextMatrixVerticalScale
                    );
                }
                $operands = [];
                continue;
            }

            if ($token === 'q') {
                $textStateStack[] = [
                    'fontResource' => $currentFontResource,
                    'fontSize' => $currentFontSize,
                    'textLeading' => $currentTextLeading,
                    'characterSpacing' => $characterSpacing,
                    'wordSpacing' => $wordSpacing,
                    'horizontalScale' => $horizontalScale,
                    'textRenderingMode' => $textRenderingMode,
                ];
                $clipStateStack[] = [
                    'clipRectangle' => $clipRectangle,
                    'currentPathRectangle' => $currentPathRectangle,
                    'currentTransformationMatrix' => $currentTransformationMatrix,
                ];
                $operands = [];
                continue;
            }

            if ($token === 'Q') {
                $state = array_pop($textStateStack);
                if (is_array($state)) {
                    $currentFontResource = $state['fontResource'];
                    $currentFontSize = $state['fontSize'];
                    $currentTextLeading = $state['textLeading'];
                    $characterSpacing = $state['characterSpacing'];
                    $wordSpacing = $state['wordSpacing'];
                    $horizontalScale = $state['horizontalScale'];
                    $textRenderingMode = $state['textRenderingMode'];
                }
                $clipState = array_pop($clipStateStack);
                if (is_array($clipState)) {
                    $clipRectangle = $clipState['clipRectangle'];
                    $currentPathRectangle = $clipState['currentPathRectangle'];
                    $currentTransformationMatrix = $clipState['currentTransformationMatrix'];
                }
                $operands = [];
                continue;
            }

            if ($token === 'Tf') {
                $currentFontResource = $this->fontResourceOperand($operands) ?? $currentFontResource;
                $currentFontSize = $this->fontSizeOperand($operands) ?? $currentFontSize;
                $operands = [];
                continue;
            }

            if ($token === 'TL') {
                $currentTextLeading = $this->textLeadingOperand($operands) ?? $currentTextLeading;
                $operands = [];
                continue;
            }

            if ($token === 'Tc') {
                $characterSpacing = $this->textCharacterSpacingOperand($operands) ?? $characterSpacing;
                $operands = [];
                continue;
            }

            if ($token === 'Tw') {
                $wordSpacing = $this->textWordSpacingOperand($operands) ?? $wordSpacing;
                $operands = [];
                continue;
            }

            if ($token === 'Tz') {
                $horizontalScale = $this->textHorizontalScaleOperand($operands) ?? $horizontalScale;
                $operands = [];
                continue;
            }

            if ($token === 'Tr') {
                $textRenderingMode = $this->textRenderingModeOperand($operands) ?? $textRenderingMode;
                $operands = [];
                continue;
            }

            if ($token === 'Td' || $token === 'TD') {
                if ($token === 'TD') {
                    $moveY = $this->textMoveOperandY($operands);
                    if ($moveY !== null) {
                        $currentTextLeading = -$moveY;
                    }
                }
                $toUnicodeMap = $this->currentToUnicodeMap($fontToUnicodeMaps, $currentFontResource);
                if (
                    $this->mapWritingMode($toUnicodeMap) === 1
                    ? $this->verticalTextMoveBreaksLine($operands)
                    : $this->textMoveBreaksLine($operands)
                ) {
                    $this->pushSpanLine($lines, $spans);
                }
                $currentTextX = $this->textMoveX($operands, $currentTextX, $currentTextMatrixHorizontalScale);
                $currentTextY = $this->textMoveY($operands, $currentTextY);
                $operands = [];
                continue;
            }

            if ($token === 'Tm') {
                $toUnicodeMap = $this->currentToUnicodeMap($fontToUnicodeMaps, $currentFontResource);
                $matrixY = $this->textMatrixY($operands);
                if (
                    $this->mapWritingMode($toUnicodeMap) === 1
                    ? $this->verticalTextMatrixBreaksLine($operands, $currentTextX)
                    : ($matrixY !== null && $currentTextY !== null && abs($matrixY - $currentTextY) > 0.000001)
                ) {
                    $this->pushSpanLine($lines, $spans);
                }
                $currentTextX = $this->textMatrixX($operands);
                $currentTextY = $matrixY;
                $currentTextMatrixHorizontalScale = $this->textMatrixHorizontalScale($operands) ?? 1.0;
                $currentTextMatrixVerticalScale = $this->textMatrixVerticalScale($operands) ?? 1.0;
                $operands = [];
                continue;
            }

            if ($token === 'T*' || $token === 'ET') {
                $this->pushSpanLine($lines, $spans);
                if ($token === 'T*') {
                    $currentTextY = $this->advanceTextYByLeading($currentTextY, $currentTextLeading);
                } else {
                    $currentTextX = null;
                    $currentTextY = null;
                    $currentTextMatrixVerticalScale = 1.0;
                }
                $operands = [];
                continue;
            }

            if ($token === 'BT') {
                $currentTextX = 0.0;
                $currentTextY = null;
                $currentTextMatrixVerticalScale = 1.0;
                $operands = [];
                continue;
            }

            if ($this->applyClipPathStateOperator(
                $token,
                $operands,
                $clipRectangle,
                $currentPathRectangle,
                $currentTransformationMatrix
            )) {
                $operands = [];
                continue;
            }

            if ($this->isOperator($token)) {
                $operands = [];
                continue;
            }

            $operands[] = $token;
        }

        $this->pushSpanLine($lines, $spans);

        return $lines;
    }

    /**
     * @param list<array<string, mixed>> $spans
     * @param array<string, array<string, mixed>> $fontToUnicodeMaps
     */
    private function appendNativeTextSpan(
        array &$spans,
        string $text,
        ?string $fontResource,
        ?float $fontSize,
        array $fontToUnicodeMaps,
        int $pageIndex,
        int &$spanId,
        ?string $sourceOperand = null,
        ?array $toUnicodeMap = null,
        float $characterSpacing = 0.0,
        float $wordSpacing = 0.0,
        float $horizontalScale = 100.0,
        float $verticalScale = 1.0
    ): void {
        if ($text === '') {
            return;
        }

        $toUnicodeMap ??= $this->currentToUnicodeMap($fontToUnicodeMaps, $fontResource);
        $fontInfo = $this->currentFontDescriptorInfo($fontToUnicodeMaps, $fontResource);
        $flags = $fontInfo['flags'];
        $fontName = $fontInfo['name'] ?? $fontResource ?? 'None';
        $fontSize ??= 12.0;
        $xStart = 0.0;
        if ($spans !== []) {
            $previousBbox = $spans[count($spans) - 1]['bbox'] ?? null;
            if (is_array($previousBbox) && isset($previousBbox[2]) && (is_int($previousBbox[2]) || is_float($previousBbox[2]))) {
                $xStart = (float) $previousBbox[2];
            }
        }
        $bbox = $this->nativeTextSpanBbox(
            $xStart,
            $text,
            $sourceOperand,
            $toUnicodeMap,
            $fontSize,
            $characterSpacing,
            $wordSpacing,
            $horizontalScale,
            $verticalScale
        );

        $span = [
            'text' => $text,
            'bbox' => $bbox,
            'span_id' => $pageIndex . '_' . $spanId,
            'font' => $fontName . '_' . (new PdfTextBlockConverter())->fontFlagsDecomposer($flags),
            'font_weight' => $fontInfo['weight'],
            'font_size' => $fontSize,
        ];
        if ($flags !== null) {
            $span['font_flags'] = $flags;
        }

        $spans[] = $span;
        $spanId++;
    }

    /**
     * @return list<float>
     */
    private function nativeTextSpanBbox(
        float $xStart,
        string $text,
        ?string $sourceOperand,
        ?array $toUnicodeMap,
        float $fontSize,
        float $characterSpacing,
        float $wordSpacing,
        float $horizontalScale,
        float $verticalScale = 1.0
    ): array {
        $heightScale = is_finite($verticalScale) ? abs($verticalScale) : 1.0;
        $height = max(1.0, $fontSize * $heightScale);
        if ($sourceOperand !== null && $this->mapWritingMode($toUnicodeMap) === 1) {
            $endY = $this->advanceTextEndYForOperand(
                0.0,
                $sourceOperand,
                $toUnicodeMap,
                $fontSize,
                $characterSpacing,
                $wordSpacing
            );
            if ($endY !== null && is_finite($endY)) {
                return [$xStart, 0.0, $xStart + $height, max(1.0, abs($endY))];
            }
        }

        $width = $this->nativeTextSpanWidth(
            $text,
            $sourceOperand,
            $toUnicodeMap,
            $fontSize,
            $characterSpacing,
            $wordSpacing,
            $horizontalScale
        );

        return [$xStart, 0.0, $xStart + $width, $height];
    }

    private function nativeTextSpanWidth(
        string $text,
        ?string $sourceOperand,
        ?array $toUnicodeMap,
        float $fontSize,
        float $characterSpacing,
        float $wordSpacing,
        float $horizontalScale
    ): float {
        if ($sourceOperand !== null && $this->mapWritingMode($toUnicodeMap) !== 1) {
            $endX = $this->advanceTextEndXForOperand(
                0.0,
                $sourceOperand,
                $toUnicodeMap,
                $fontSize,
                $characterSpacing,
                $wordSpacing,
                $horizontalScale
            );
            if ($endX !== null && is_finite($endX)) {
                return max(1.0, $endX);
            }
        }

        return max(1.0, $this->length($text) * $fontSize * self::SIMPLE_TEXT_ADVANCE_RATIO);
    }

    /**
     * @param array<string, array<string, mixed>> $fontToUnicodeMaps
     * @return array{name: string|null, flags: int|null, weight: float}
     */
    private function currentFontDescriptorInfo(array $fontToUnicodeMaps, ?string $fontResource): array
    {
        $map = $this->currentToUnicodeMap($fontToUnicodeMaps, $fontResource);
        $name = isset($map['fontName']) && is_string($map['fontName']) && $map['fontName'] !== ''
            ? $map['fontName']
            : null;
        $flags = isset($map['fontFlags']) && is_int($map['fontFlags'])
            ? $map['fontFlags']
            : null;
        $weight = isset($map['fontWeight']) && (is_int($map['fontWeight']) || is_float($map['fontWeight']))
            ? (float) $map['fontWeight']
            : null;

        if ($weight === null) {
            $weight = $flags !== null && ($flags & (1 << 18)) !== 0 ? 700.0 : 400.0;
        }

        return [
            'name' => $name,
            'flags' => $flags,
            'weight' => $weight,
        ];
    }

    /**
     * @param list<array{spans: list<array<string, mixed>>, bbox: list<float>}> $lines
     * @param list<array<string, mixed>> $spans
     */
    private function pushSpanLine(array &$lines, array &$spans): void
    {
        $spans = array_values(array_filter($spans, static fn (array $span): bool => trim((string) ($span['text'] ?? '')) !== ''));
        if ($spans === []) {
            return;
        }

        $bbox = $this->bboxFromSpans($spans);
        $lines[] = [
            'spans' => $spans,
            'bbox' => $bbox,
        ];
        $spans = [];
    }

    /**
     * @param list<array<string, mixed>> $spans
     * @return list<float>
     */
    private function bboxFromSpans(array $spans): array
    {
        $x1 = null;
        $y1 = null;
        $x2 = null;
        $y2 = null;
        foreach ($spans as $span) {
            $bbox = $span['bbox'] ?? null;
            if (!is_array($bbox) || count($bbox) < 4) {
                continue;
            }

            $x1 = $x1 === null ? (float) $bbox[0] : min($x1, (float) $bbox[0]);
            $y1 = $y1 === null ? (float) $bbox[1] : min($y1, (float) $bbox[1]);
            $x2 = $x2 === null ? (float) $bbox[2] : max($x2, (float) $bbox[2]);
            $y2 = $y2 === null ? (float) $bbox[3] : max($y2, (float) $bbox[3]);
        }

        return [$x1 ?? 0.0, $y1 ?? 0.0, $x2 ?? 0.0, $y2 ?? 0.0];
    }

    /**
     * @param list<array{spans: list<array<string, mixed>>, bbox: list<float>}> $lines
     * @return list<float>
     */
    private function pageBboxFromLines(array $lines): array
    {
        return $this->bboxFromSpans(array_map(
            static fn (array $line): array => ['bbox' => $line['bbox'], 'text' => 'line'],
            $lines
        ));
    }

    /**
     * @return list<string>
     * @param array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontToUnicodeMaps
     * @param array<string, array{actualText: string|null, altText: string|null}> $markedContentProperties
     */
    private function textLinesFromContentStream(string $stream, array $fontToUnicodeMaps, array $markedContentProperties = []): array
    {
        $lines = [];
        $operands = [];
        $currentLine = '';
        $currentFontResource = null;
        $currentFontSize = null;
        $currentTextLeading = null;
        $currentTextX = null;
        $currentTextY = null;
        $currentTextEndX = null;
        $currentTextEndY = null;
        $characterSpacing = 0.0;
        $wordSpacing = 0.0;
        $horizontalScale = 100.0;
        $currentTextMatrixHorizontalScale = 1.0;
        $pendingPositionWordGap = false;
        $textRenderingMode = 0;
        $textStateStack = [];
        $markedContentStack = [];
        $currentTransformationMatrix = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];
        $clipRectangle = null;
        $currentPathRectangle = null;
        $clipStateStack = [];

        foreach ($this->contentTokens($stream) as $token) {
            if ($this->isTextShowingOperator($token)) {
                if ($token === "'" || $token === '"') {
                    $this->pushLine($lines, $currentLine);
                    $currentTextY = $this->advanceTextYByLeading($currentTextY, $currentTextLeading);
                    $currentTextEndX = $currentTextX;
                    $currentTextEndY = $currentTextY;
                    $pendingPositionWordGap = false;
                }

                if ($token === '"') {
                    $wordSpacing = $this->quoteWordSpacingOperand($operands) ?? $wordSpacing;
                    $characterSpacing = $this->quoteCharacterSpacingOperand($operands) ?? $characterSpacing;
                }

                $operand = $this->textShowingOperand($token, $operands);
                if ($operand !== null) {
                    $toUnicodeMap = $this->currentToUnicodeMap($fontToUnicodeMaps, $currentFontResource);
                    $replacementIndex = $this->activeMarkedContentReplacementIndex($markedContentStack);
                    $insideActiveClip = $this->textPositionInsideActiveClip($currentTextX, $currentTextY, $clipRectangle);
                    if (!$insideActiveClip) {
                        $decoded = '';
                        if ($replacementIndex !== null) {
                            $markedContentStack[$replacementIndex]['emitted'] = true;
                        }
                    } elseif (!$this->isVisibleTextRenderingMode($textRenderingMode)) {
                        $decoded = '';
                        if ($replacementIndex !== null) {
                            $markedContentStack[$replacementIndex]['emitted'] = true;
                        }
                    } elseif ($replacementIndex !== null) {
                        $decoded = $markedContentStack[$replacementIndex]['emitted']
                            ? ''
                            : $markedContentStack[$replacementIndex]['replacement'];
                        $markedContentStack[$replacementIndex]['emitted'] = true;
                    } else {
                        $decoded = $this->decodePositionedTextOperand(
                            $operand,
                            $toUnicodeMap,
                            $currentFontSize,
                            $characterSpacing,
                            $wordSpacing,
                            $horizontalScale * $currentTextMatrixHorizontalScale
                        );
                    }
                    if ($insideActiveClip && $this->isVisibleTextRenderingMode($textRenderingMode)) {
                        $this->appendPositionedText($currentLine, $decoded, $pendingPositionWordGap);
                    } else {
                        $pendingPositionWordGap = false;
                    }
                    if ($this->mapWritingMode($toUnicodeMap) === 1) {
                        $currentTextEndY = $this->advanceTextEndYForOperand(
                            $currentTextEndY ?? $currentTextY,
                            $operand,
                            $toUnicodeMap,
                            $currentFontSize,
                            $characterSpacing,
                            $wordSpacing
                        );
                    } else {
                        $currentTextEndX = $this->advanceTextEndXForOperand(
                            $currentTextEndX ?? $currentTextX,
                            $operand,
                            $toUnicodeMap,
                            $currentFontSize,
                            $characterSpacing,
                            $wordSpacing,
                            $horizontalScale * $currentTextMatrixHorizontalScale
                        );
                    }
                }
                $operands = [];
                continue;
            }

            if ($token === 'BMC') {
                $markedContentStack[] = [
                    'replacement' => null,
                    'emitted' => true,
                ];
                $operands = [];
                continue;
            }

            if ($token === 'BDC') {
                $markedContentStack[] = [
                    'replacement' => $this->markedContentReplacementOperand($operands, $markedContentProperties),
                    'emitted' => false,
                ];
                $operands = [];
                continue;
            }

            if ($token === 'EMC') {
                $markedContent = array_pop($markedContentStack);
                if (
                    is_array($markedContent)
                    && $markedContent['replacement'] !== null
                    && !$markedContent['emitted']
                    && $this->activeMarkedContentReplacementIndex($markedContentStack) === null
                ) {
                    $this->appendPositionedText($currentLine, $markedContent['replacement'], $pendingPositionWordGap);
                }
                $operands = [];
                continue;
            }

            if ($token === 'q') {
                $textStateStack[] = [
                    'fontSize' => $currentFontSize,
                    'fontResource' => $currentFontResource,
                    'textLeading' => $currentTextLeading,
                    'characterSpacing' => $characterSpacing,
                    'wordSpacing' => $wordSpacing,
                    'horizontalScale' => $horizontalScale,
                    'textRenderingMode' => $textRenderingMode,
                ];
                $clipStateStack[] = [
                    'clipRectangle' => $clipRectangle,
                    'currentPathRectangle' => $currentPathRectangle,
                    'currentTransformationMatrix' => $currentTransformationMatrix,
                ];
                $operands = [];
                continue;
            }

            if ($token === 'Q') {
                $state = array_pop($textStateStack);
                if (is_array($state)) {
                    $currentFontSize = $state['fontSize'];
                    $currentFontResource = $state['fontResource'];
                    $currentTextLeading = $state['textLeading'];
                    $characterSpacing = $state['characterSpacing'];
                    $wordSpacing = $state['wordSpacing'];
                    $horizontalScale = $state['horizontalScale'];
                    $textRenderingMode = $state['textRenderingMode'];
                }
                $clipState = array_pop($clipStateStack);
                if (is_array($clipState)) {
                    $clipRectangle = $clipState['clipRectangle'];
                    $currentPathRectangle = $clipState['currentPathRectangle'];
                    $currentTransformationMatrix = $clipState['currentTransformationMatrix'];
                }
                $operands = [];
                continue;
            }

            if ($token === 'Tf') {
                $currentFontResource = $this->fontResourceOperand($operands) ?? $currentFontResource;
                $currentFontSize = $this->fontSizeOperand($operands) ?? $currentFontSize;
                $operands = [];
                continue;
            }

            if ($token === 'TL') {
                $currentTextLeading = $this->textLeadingOperand($operands) ?? $currentTextLeading;
                $operands = [];
                continue;
            }

            if ($token === 'Tc') {
                $characterSpacing = $this->textCharacterSpacingOperand($operands) ?? $characterSpacing;
                $operands = [];
                continue;
            }

            if ($token === 'Tw') {
                $wordSpacing = $this->textWordSpacingOperand($operands) ?? $wordSpacing;
                $operands = [];
                continue;
            }

            if ($token === 'Tz') {
                $horizontalScale = $this->textHorizontalScaleOperand($operands) ?? $horizontalScale;
                $operands = [];
                continue;
            }

            if ($token === 'Tr') {
                $textRenderingMode = $this->textRenderingModeOperand($operands) ?? $textRenderingMode;
                $operands = [];
                continue;
            }

            if ($token === 'Td' || $token === 'TD') {
                if ($token === 'TD') {
                    $moveY = $this->textMoveOperandY($operands);
                    if ($moveY !== null) {
                        $currentTextLeading = -$moveY;
                    }
                }
                $toUnicodeMap = $this->currentToUnicodeMap($fontToUnicodeMaps, $currentFontResource);
                if ($this->mapWritingMode($toUnicodeMap) === 1) {
                    if ($this->verticalTextMoveBreaksLine($operands)) {
                        $this->pushLine($lines, $currentLine);
                        $pendingPositionWordGap = false;
                    } elseif ($this->verticalTextMoveCreatesWordGap($operands, $currentTextY, $currentTextEndY)) {
                        $pendingPositionWordGap = $currentLine !== '';
                    }
                } else {
                    if ($this->textMoveBreaksLine($operands)) {
                        $this->pushLine($lines, $currentLine);
                        $pendingPositionWordGap = false;
                    } elseif ($this->textMoveCreatesWordGap($operands, $currentTextX, $currentTextEndX, $currentTextMatrixHorizontalScale)) {
                        $pendingPositionWordGap = $currentLine !== '';
                    }
                }
                $currentTextX = $this->textMoveX($operands, $currentTextX, $currentTextMatrixHorizontalScale);
                $currentTextY = $this->textMoveY($operands, $currentTextY);
                $currentTextEndX = $currentTextX;
                $currentTextEndY = $currentTextY;
                $operands = [];
                continue;
            }

            if ($token === 'Tm') {
                $toUnicodeMap = $this->currentToUnicodeMap($fontToUnicodeMaps, $currentFontResource);
                if ($this->mapWritingMode($toUnicodeMap) === 1) {
                    if ($this->verticalTextMatrixBreaksLine($operands, $currentTextX)) {
                        $this->pushLine($lines, $currentLine);
                        $pendingPositionWordGap = false;
                    } elseif ($this->verticalTextMatrixCreatesWordGap($operands, $currentTextEndY)) {
                        $pendingPositionWordGap = $currentLine !== '';
                    }
                } elseif ($this->textMatrixBreaksLine($operands, $currentTextY)) {
                    $this->pushLine($lines, $currentLine);
                    $pendingPositionWordGap = false;
                } elseif ($this->textMatrixCreatesWordGap($operands, $currentTextEndX)) {
                    $pendingPositionWordGap = $currentLine !== '';
                }
                $currentTextX = $this->textMatrixX($operands);
                $currentTextY = $this->textMatrixY($operands);
                $currentTextEndX = $currentTextX;
                $currentTextEndY = $currentTextY;
                $currentTextMatrixHorizontalScale = $this->textMatrixHorizontalScale($operands) ?? 1.0;
                $operands = [];
                continue;
            }

            if ($token === 'T*') {
                $this->pushLine($lines, $currentLine);
                $currentTextY = $this->advanceTextYByLeading($currentTextY, $currentTextLeading);
                $currentTextEndX = $currentTextX;
                $currentTextEndY = $currentTextY;
                $pendingPositionWordGap = false;
                $operands = [];
                continue;
            }

            if ($token === 'BT') {
                $currentTextX = null;
                $currentTextY = null;
                $currentTextEndX = null;
                $currentTextEndY = null;
                $currentTextMatrixHorizontalScale = 1.0;
                $pendingPositionWordGap = false;
                $operands = [];
                continue;
            }

            if ($token === 'ET') {
                $this->pushLine($lines, $currentLine);
                $currentTextX = null;
                $currentTextY = null;
                $currentTextEndX = null;
                $currentTextEndY = null;
                $currentTextMatrixHorizontalScale = 1.0;
                $pendingPositionWordGap = false;
                $operands = [];
                continue;
            }

            if ($this->applyClipPathStateOperator(
                $token,
                $operands,
                $clipRectangle,
                $currentPathRectangle,
                $currentTransformationMatrix
            )) {
                $operands = [];
                continue;
            }

            if ($this->isOperator($token)) {
                $operands = [];
                continue;
            }

            $operands[] = $token;
        }

        $this->pushLine($lines, $currentLine);

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function contentTokens(string $stream): array
    {
        $tokens = [];
        $length = strlen($stream);
        $index = 0;

        while ($index < $length) {
            $char = $stream[$index];
            if (ctype_space($char)) {
                $index++;
                continue;
            }

            if ($char === '%') {
                while ($index < $length && !in_array($stream[$index], ["\n", "\r"], true)) {
                    $index++;
                }
                continue;
            }

            if ($char === '(') {
                $tokens[] = $this->readLiteralToken($stream, $index);
                continue;
            }

            if ($char === '<' && ($index + 1 >= $length || $stream[$index + 1] !== '<')) {
                $tokens[] = $this->readHexToken($stream, $index);
                continue;
            }

            if ($char === '<' && $index + 1 < $length && $stream[$index + 1] === '<') {
                $tokens[] = $this->readDictionaryToken($stream, $index);
                continue;
            }

            if ($char === '[') {
                $tokens[] = $this->readArrayToken($stream, $index);
                continue;
            }

            if ($char === '/') {
                $tokens[] = $this->readNameToken($stream, $index);
                continue;
            }

            $start = $index;
            while ($index < $length && !$this->isDelimiter($stream[$index])) {
                $index++;
            }
            if ($index === $start) {
                $index++;
                continue;
            }
            $token = substr($stream, $start, $index - $start);
            if ($token === 'BI') {
                $inlineImageEnd = $index;
                if ($this->skipInlineImage($stream, $inlineImageEnd)) {
                    $index = $inlineImageEnd;
                    continue;
                }
            }

            $tokens[] = $token;
        }

        return array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
    }

    private function skipInlineImage(string $stream, int &$index): bool
    {
        $length = strlen($stream);
        $dictionary = $this->readInlineImageDictionary($stream, $index);

        if ($dictionary === null || !$this->inlineImageDictionaryHasImageKeys($dictionary)) {
            return false;
        }

        $this->consumeInlineImageDataPrefixWhitespace($stream, $index);
        $dataStart = $index;
        $incompletePreviewFallbackEnd = null;
        $incompletePreviewFallbackCanCloseBeforeNextImage = false;
        while ($index < $length) {
            $end = strpos($stream, 'EI', $index);
            if ($end === false) {
                $index = $incompletePreviewFallbackEnd === null ? $length : $incompletePreviewFallbackEnd + 2;
                return true;
            }

            if ($this->inlineImageEndMarkerAt($stream, $end)) {
                if (
                    $incompletePreviewFallbackEnd !== null
                    && $incompletePreviewFallbackCanCloseBeforeNextImage
                    && $this->contentSegmentContainsInlineImagePreamble(
                        substr($stream, $incompletePreviewFallbackEnd + 2, $end - $incompletePreviewFallbackEnd - 2)
                    )
                ) {
                    $index = $incompletePreviewFallbackEnd + 2;
                    return true;
                }

                $candidate = $this->inlineImageDataCandidate($stream, $dataStart, $end);
                if ($this->inlineImageCandidateMatchesDictionary($dictionary, $candidate)) {
                    $index = $end + 2;
                    return true;
                }

                if ($this->inlineImageCandidateIsIncompletePreviewOnly($dictionary, $candidate)) {
                    $incompletePreviewFallbackEnd = $end;
                    $incompletePreviewFallbackCanCloseBeforeNextImage =
                        $this->inlineImageIncompletePreviewCandidateReachedSampleFloor($dictionary, $candidate);
                }
            }

            $index = $end + 2;
        }

        if ($incompletePreviewFallbackEnd !== null) {
            $index = $incompletePreviewFallbackEnd + 2;
            return true;
        }

        return true;
    }

    private function contentSegmentContainsInlineImagePreamble(string $segment): bool
    {
        $index = 0;
        $length = strlen($segment);

        while ($index < $length) {
            $char = $segment[$index];
            if (ctype_space($char)) {
                $index++;
                continue;
            }

            if ($char === '%') {
                $this->skipPdfComment($segment, $index);
                continue;
            }

            if ($char === '(') {
                $this->readLiteralToken($segment, $index);
                continue;
            }

            if ($char === '<') {
                if ($index + 1 < $length && $segment[$index + 1] === '<') {
                    $this->readDictionaryToken($segment, $index);
                    continue;
                }

                $this->readHexToken($segment, $index);
                continue;
            }

            if ($char === '[') {
                $this->readArrayToken($segment, $index);
                continue;
            }

            if ($char === '/') {
                $this->readNameToken($segment, $index);
                continue;
            }

            $start = $index;
            while ($index < $length && !$this->isDelimiter($segment[$index])) {
                $index++;
            }

            if ($index === $start) {
                $index++;
                continue;
            }

            if (substr($segment, $start, $index - $start) !== 'BI') {
                continue;
            }

            $dictionaryEnd = $index;
            $dictionary = $this->readInlineImageDictionary($segment, $dictionaryEnd);
            if ($dictionary !== null && $this->inlineImageDictionaryHasImageKeys($dictionary)) {
                return true;
            }
        }

        return false;
    }

    private function readInlineImageDictionary(string $stream, int &$index): ?string
    {
        $entries = [];
        $length = strlen($stream);

        while ($index < $length) {
            $this->skipContentWhitespaceAndComments($stream, $index);
            if ($index >= $length) {
                return null;
            }

            $keyToken = $this->readInlineImageToken($stream, $index);
            if ($keyToken === null) {
                return null;
            }

            if ($keyToken === 'ID') {
                return implode(' ', $entries);
            }

            if (!str_starts_with($keyToken, '/')) {
                return null;
            }

            $this->skipContentWhitespaceAndComments($stream, $index);
            if ($index >= $length) {
                return null;
            }

            $valueToken = $this->readInlineImageToken($stream, $index);
            if ($valueToken === null) {
                return null;
            }

            if ($valueToken === 'ID') {
                return null;
            }

            $valueToken = $this->readInlineImageIndirectReferenceValue($stream, $index, $valueToken);
            $key = $this->canonicalInlineImageKey($keyToken);
            $entries[] = $key . ' ' . $this->canonicalInlineImageValue($valueToken);
        }

        return null;
    }

    private function readInlineImageToken(string $stream, int &$index): ?string
    {
        $this->skipContentWhitespaceAndComments($stream, $index);
        if ($index >= strlen($stream)) {
            return null;
        }

        $char = $stream[$index];
        if ($char === '(') {
            return $this->readLiteralToken($stream, $index);
        }

        if ($char === '<' && ($index + 1 >= strlen($stream) || $stream[$index + 1] !== '<')) {
            return $this->readHexToken($stream, $index);
        }

        if ($char === '<' && $index + 1 < strlen($stream) && $stream[$index + 1] === '<') {
            return $this->readDictionaryToken($stream, $index);
        }

        if ($char === '[') {
            return $this->readArrayToken($stream, $index);
        }

        if ($char === '/') {
            return $this->readNameToken($stream, $index);
        }

        $start = $index;
        while ($index < strlen($stream) && !$this->isDelimiter($stream[$index])) {
            $index++;
        }

        return $index === $start ? null : substr($stream, $start, $index - $start);
    }

    private function readInlineImageIndirectReferenceValue(string $stream, int &$index, string $firstToken): string
    {
        if (preg_match('/^[+-]?\d+$/', $firstToken) !== 1) {
            return $firstToken;
        }

        $referenceIndex = $index;
        $generationToken = $this->readInlineImageToken($stream, $referenceIndex);
        if ($generationToken === null || preg_match('/^[+-]?\d+$/', $generationToken) !== 1) {
            return $firstToken;
        }

        $referenceToken = $this->readInlineImageToken($stream, $referenceIndex);
        if ($referenceToken !== 'R') {
            return $firstToken;
        }

        $index = $referenceIndex;
        return $firstToken . ' ' . $generationToken . ' R';
    }

    private function canonicalInlineImageKey(string $token): string
    {
        $name = $this->decodePdfName(substr($token, 1));
        return '/' . (self::INLINE_IMAGE_KEY_ABBREVIATIONS[$name] ?? $name);
    }

    private function canonicalInlineImageValue(string $token): string
    {
        if (str_starts_with($token, '/')) {
            $name = $this->decodePdfName(substr($token, 1));
            return '/' . (self::INLINE_IMAGE_VALUE_ABBREVIATIONS[$name] ?? $name);
        }

        if (!str_starts_with($token, '[')) {
            return $token;
        }

        return (string) preg_replace_callback(
            '/\/([^\s\[\]\(\)<>{}\/%]+)/',
            function (array $match): string {
                $name = $this->decodePdfName($match[1]);
                return '/' . (self::INLINE_IMAGE_VALUE_ABBREVIATIONS[$name] ?? $name);
            },
            $token
        );
    }

    private function inlineImageDictionaryHasImageKeys(string $dictionary): bool
    {
        return preg_match(
            '/\/(?:Width|Height|ColorSpace|BitsPerComponent|ImageMask|Filter|Decode|DecodeParms|Interpolate)(?=[\s\[\]()<>{}\/%]|$)/s',
            $dictionary
        ) === 1;
    }

    private function inlineImageDataCandidate(string $stream, int $dataStart, int $markerOffset): string
    {
        $dataEnd = $markerOffset;
        if ($dataEnd > $dataStart && ctype_space($stream[$dataEnd - 1])) {
            $dataEnd--;
            if ($dataEnd > $dataStart && $stream[$dataEnd - 1] === "\r" && ($stream[$dataEnd] ?? '') === "\n") {
                $dataEnd--;
            }
        }

        return substr($stream, $dataStart, $dataEnd - $dataStart);
    }

    private function inlineImageCandidateMatchesDictionary(string $dictionary, string $candidate): bool
    {
        $filters = $this->streamFilters($dictionary, []);
        if ($filters === null) {
            return true;
        }

        $jpxState = $this->inlineImageUsesJpxDecode($filters)
            ? $this->inlineJpxCandidateStateForFilters($dictionary, $filters, $candidate)
            : null;
        if ($jpxState === 'incomplete') {
            return false;
        }
        if ($jpxState === 'complete') {
            return true;
        }

        $jbig2State = $this->inlineImageUsesJbig2Decode($filters)
            ? $this->inlineJbig2CandidateStateForFilters($dictionary, $filters, $candidate)
            : null;
        if ($jbig2State === 'incomplete') {
            return false;
        }
        if ($jbig2State === 'complete') {
            return true;
        }

        $dctState = $this->inlineImageUsesDctDecode($filters)
            ? $this->inlineDctCandidateStateForFilters($dictionary, $filters, $candidate)
            : null;
        if ($dctState === 'incomplete') {
            return false;
        }
        if ($dctState === 'complete') {
            return true;
        }

        $ccittState = $this->inlineImageUsesCcittFaxDecode($filters)
            ? $this->inlineCcittFaxCandidateStateForFilters($dictionary, $filters, $candidate)
            : null;
        if ($ccittState === 'incomplete') {
            return false;
        }
        if ($ccittState === 'complete') {
            return true;
        }

        $expectedLength = $this->inlineImageExpectedDecodedLength($dictionary);
        if ($filters === []) {
            return $expectedLength === null || strlen($candidate) >= $expectedLength;
        }

        if (!$this->hasVerifiableInlineImageFilter($filters)) {
            return true;
        }

        $decoded = $this->decodeStream($dictionary, $candidate, [], true);
        if ($decoded === null) {
            return false;
        }

        return $expectedLength === null || strlen($decoded) === $expectedLength;
    }

    private function inlineImageCandidateIsIncompletePreviewOnly(string $dictionary, string $candidate): bool
    {
        $filters = $this->streamFilters($dictionary, []);
        if ($filters === null) {
            return false;
        }

        return (
            $this->inlineImageUsesJpxDecode($filters)
            && $this->inlineJpxCandidateStateForFilters($dictionary, $filters, $candidate) === 'incomplete'
        )
            || (
                $this->inlineImageUsesJbig2Decode($filters)
                && $this->inlineJbig2CandidateStateForFilters($dictionary, $filters, $candidate) === 'incomplete'
            )
            || (
                $this->inlineImageUsesDctDecode($filters)
                && $this->inlineDctCandidateStateForFilters($dictionary, $filters, $candidate) === 'incomplete'
            )
            || (
                $this->inlineImageUsesCcittFaxDecode($filters)
                && $this->inlineCcittFaxCandidateStateForFilters($dictionary, $filters, $candidate) === 'incomplete'
            );
    }

    private function inlineImageIncompletePreviewCandidateReachedSampleFloor(string $dictionary, string $candidate): bool
    {
        $expectedLength = $this->inlineImageExpectedDecodedLength($dictionary);
        if ($expectedLength === null) {
            return false;
        }

        $filters = $this->streamFilters($dictionary, []);
        if ($filters === null || $filters === []) {
            return false;
        }

        $previewFilters = ['JBIG2Decode', 'CCITTFaxDecode', 'CCF'];
        $hasOpenEndedPreviewFilter = false;
        foreach ($filters as $filter) {
            if (in_array($filter, $previewFilters, true)) {
                $hasOpenEndedPreviewFilter = true;
                break;
            }
        }

        if (!$hasOpenEndedPreviewFilter) {
            return false;
        }

        $bytes = $this->inlineImageBytesBeforePreviewFilter($dictionary, $filters, $candidate, $previewFilters);
        return $bytes !== null && strlen($bytes) >= $expectedLength;
    }

    /**
     * @param list<string|null> $filters
     */
    private function inlineImageUsesJpxDecode(array $filters): bool
    {
        foreach ($filters as $filter) {
            if ($filter === 'JPXDecode') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string|null> $filters
     */
    private function inlineImageUsesJbig2Decode(array $filters): bool
    {
        foreach ($filters as $filter) {
            if ($filter === 'JBIG2Decode') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string|null> $filters
     */
    private function inlineImageUsesDctDecode(array $filters): bool
    {
        foreach ($filters as $filter) {
            if ($filter === 'DCTDecode' || $filter === 'DCT') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string|null> $filters
     */
    private function inlineImageUsesCcittFaxDecode(array $filters): bool
    {
        foreach ($filters as $filter) {
            if ($filter === 'CCITTFaxDecode' || $filter === 'CCF') {
                return true;
            }
        }

        return false;
    }

    /**
     * JPXDecode image data is preview-only in this native port, but raw JPEG
     * 2000 payloads still expose enough framing to reject delimiter-looking
     * `EI` bytes before the codestream EOC marker.
     */
    private function inlineJpxCandidateState(string $candidate): string
    {
        $bytes = rtrim($candidate, "\x00\t\n\f\r ");
        if ($bytes === '') {
            return 'unknown';
        }

        $isRawCodestream = str_starts_with($bytes, "\xff\x4f");
        $isJp2File = str_starts_with($bytes, "\x00\x00\x00\x0cjP  \r\n\x87\n");
        if (!$isRawCodestream && !$isJp2File) {
            return 'unknown';
        }

        return str_contains($bytes, "\xff\xd9") ? 'complete' : 'incomplete';
    }

    /**
     * @param list<string|null> $filters
     */
    private function inlineJpxCandidateStateForFilters(string $dictionary, array $filters, string $candidate): string
    {
        $bytes = $this->inlineImageBytesBeforePreviewFilter($dictionary, $filters, $candidate, ['JPXDecode']);
        if ($bytes === null) {
            return 'unknown';
        }

        return $this->inlineJpxCandidateState($bytes);
    }

    /**
     * JBIG2Decode is preview-only in this native port. PDF inline JBIG2 data
     * may be raw segments without the JBIG2 file header, so any non-empty
     * candidate remains open until the final fallback boundary.
     */
    private function inlineJbig2CandidateState(string $candidate): string
    {
        if (rtrim($candidate, "\x00\t\n\f\r ") === '') {
            return 'unknown';
        }

        return 'incomplete';
    }

    /**
     * @param list<string|null> $filters
     */
    private function inlineJbig2CandidateStateForFilters(string $dictionary, array $filters, string $candidate): string
    {
        $bytes = $this->inlineImageBytesBeforePreviewFilter($dictionary, $filters, $candidate, ['JBIG2Decode']);
        if ($bytes === null) {
            return 'unknown';
        }

        return $this->inlineJbig2CandidateState($bytes);
    }

    /**
     * DCTDecode image data is JPEG preview-only here, but JPEG framing still
     * lets the tokenizer reject delimiter-looking `EI` bytes before EOI.
     */
    private function inlineDctCandidateState(string $candidate): string
    {
        $bytes = rtrim($candidate, "\x00\t\n\f\r ");
        if ($bytes === '') {
            return 'unknown';
        }

        if (!str_starts_with($bytes, "\xff\xd8")) {
            return 'unknown';
        }

        return str_contains($bytes, "\xff\xd9") ? 'complete' : 'incomplete';
    }

    /**
     * @param list<string|null> $filters
     */
    private function inlineDctCandidateStateForFilters(string $dictionary, array $filters, string $candidate): string
    {
        $bytes = $this->inlineImageBytesBeforePreviewFilter($dictionary, $filters, $candidate, ['DCTDecode', 'DCT']);
        if ($bytes === null) {
            return 'unknown';
        }

        return $this->inlineDctCandidateState($bytes);
    }

    /**
     * CCITTFaxDecode image data is preview-only in this native port. The fax
     * bitstream has no cheap text-safe end marker, so delimiter-looking bytes
     * stay closed until the tokenizer reaches the final inline-image fallback.
     */
    private function inlineCcittFaxCandidateState(string $candidate): string
    {
        return rtrim($candidate, "\x00\t\n\f\r ") === '' ? 'unknown' : 'incomplete';
    }

    /**
     * @param list<string|null> $filters
     */
    private function inlineCcittFaxCandidateStateForFilters(string $dictionary, array $filters, string $candidate): string
    {
        $bytes = $this->inlineImageBytesBeforePreviewFilter($dictionary, $filters, $candidate, ['CCITTFaxDecode', 'CCF']);
        if ($bytes === null) {
            return 'unknown';
        }

        return $this->inlineCcittFaxCandidateState($bytes);
    }

    /**
     * @param list<string|null> $filters
     * @param list<string> $previewFilters
     */
    private function inlineImageBytesBeforePreviewFilter(
        string $dictionary,
        array $filters,
        string $candidate,
        array $previewFilters
    ): ?string {
        $decodeParms = $this->streamDecodeParms($dictionary, []);
        if ($decodeParms === null) {
            return null;
        }

        $stream = $candidate;
        foreach ($filters as $index => $filter) {
            if ($filter === null) {
                continue;
            }

            if (in_array($filter, $previewFilters, true)) {
                return $stream;
            }

            $filterDecodeParms = $this->decodeParmsForFilterIndex($filters, $decodeParms, $index);
            if (!$this->canApplyDecodeParms($filter, $filterDecodeParms, [])) {
                return null;
            }

            if (!$this->streamFilterInputHasExplicitEndMarker($filter, $stream)) {
                return null;
            }

            $decoded = match ($filter) {
                'ASCIIHexDecode', 'AHx' => $this->decodeAsciiHexStream($stream),
                'ASCII85Decode', 'A85' => $this->decodeAscii85Stream($stream),
                'RunLengthDecode', 'RL' => $this->decodeRunLengthStream($stream),
                'LZWDecode', 'LZW' => $this->decodeLzwStream($stream, $filterDecodeParms, []),
                'FlateDecode', 'Fl' => $this->decodeFlateStream($stream, $filterDecodeParms, []),
                default => null,
            };

            if ($decoded === null) {
                return null;
            }

            $stream = $decoded;
        }

        return null;
    }

    /**
     * @param list<string|null> $filters
     */
    private function hasVerifiableInlineImageFilter(array $filters): bool
    {
        foreach ($filters as $filter) {
            if (in_array($filter, [
                'ASCII85Decode',
                'ASCIIHexDecode',
                'FlateDecode',
                'LZWDecode',
                'RunLengthDecode',
            ], true)) {
                return true;
            }
        }

        return false;
    }

    private function inlineImageExpectedDecodedLength(string $dictionary): ?int
    {
        $width = $this->pdfIntegerValueAfterName($dictionary, 'Width');
        $height = $this->pdfIntegerValueAfterName($dictionary, 'Height');
        if ($width === null || $height === null || $width < 1 || $height < 1) {
            return null;
        }

        $imageMask = $this->pdfBooleanValueAfterName($dictionary, 'ImageMask') === true;
        $components = $imageMask ? 1 : $this->inlineImageColorComponents($dictionary);
        if ($components === null) {
            return null;
        }

        $bitsPerComponent = $imageMask
            ? 1
            : ($this->pdfIntegerValueAfterName($dictionary, 'BitsPerComponent') ?? null);
        if ($bitsPerComponent === null || $bitsPerComponent < 1) {
            return null;
        }

        return intdiv(($width * $height * $components * $bitsPerComponent) + 7, 8);
    }

    private function inlineImageColorComponents(string $dictionary): ?int
    {
        $colorSpace = $this->pdfValueAfterName($dictionary, 'ColorSpace');
        if ($colorSpace === null) {
            return null;
        }

        $colorSpace = trim($colorSpace);
        if (str_starts_with($colorSpace, '[')) {
            return str_contains($colorSpace, '/Indexed') ? 1 : null;
        }

        return match ($colorSpace) {
            '/DeviceGray' => 1,
            '/DeviceRGB' => 3,
            '/DeviceCMYK' => 4,
            '/Indexed' => 1,
            default => null,
        };
    }

    private function pdfBooleanValueAfterName(string $body, string $name): ?bool
    {
        $offset = $this->nameValueOffset($body, $name);
        if ($offset === null) {
            return null;
        }

        if (preg_match('/\Gtrue\b/s', $body, $match, 0, $offset) === 1) {
            return true;
        }

        if (preg_match('/\Gfalse\b/s', $body, $match, 0, $offset) === 1) {
            return false;
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, true> $seen
     */
    private function pdfBooleanValueAfterNameResolvingObjects(string $body, string $name, array $objects, array $seen = []): ?bool
    {
        $offset = $this->nameValueOffset($body, $name);
        if ($offset === null) {
            return null;
        }

        $offset = $this->skipPdfWhitespace($body, $offset);
        if (preg_match('/\Gtrue\b/s', $body, $match, 0, $offset) === 1) {
            return true;
        }
        if (preg_match('/\Gfalse\b/s', $body, $match, 0, $offset) === 1) {
            return false;
        }
        if (preg_match('/\G(\d+)\s+(\d+)\s+R\b/s', $body, $match, 0, $offset) !== 1) {
            return null;
        }

        $objectNumber = (int) $match[1];
        $generation = (int) $match[2];
        $key = $objectNumber . ':' . $generation;
        if ($objectNumber <= 0 || isset($seen[$key])) {
            return null;
        }

        $objectBody = $this->indirectObjectBodyForReference($objects, $objectNumber, $generation);
        if ($objectBody === null) {
            return null;
        }

        $seen[$key] = true;
        return $this->pdfBooleanValueAfterNameResolvingObjects('/Value ' . trim($objectBody), 'Value', $objects, $seen);
    }

    private function skipContentWhitespaceAndComments(string $stream, int &$index): void
    {
        $length = strlen($stream);
        while ($index < $length) {
            if (ctype_space($stream[$index])) {
                $index++;
                continue;
            }

            if ($stream[$index] !== '%') {
                return;
            }

            while ($index < $length && !in_array($stream[$index], ["\n", "\r"], true)) {
                $index++;
            }
        }
    }

    private function consumeInlineImageDataPrefixWhitespace(string $stream, int &$index): void
    {
        $length = strlen($stream);
        if ($index >= $length || !ctype_space($stream[$index])) {
            return;
        }

        if ($stream[$index] === "\r") {
            $index++;
            if ($index < $length && $stream[$index] === "\n") {
                $index++;
            }
            return;
        }

        $index++;
    }

    private function inlineImageEndMarkerAt(string $stream, int $offset): bool
    {
        if ($offset <= 0 || !ctype_space($stream[$offset - 1])) {
            return false;
        }

        $after = $offset + 2;
        return $after >= strlen($stream) || $this->isDelimiter($stream[$after]);
    }

    private function readLiteralToken(string $stream, int &$index): string
    {
        $start = $index;
        $depth = 0;
        $length = strlen($stream);

        while ($index < $length) {
            $char = $stream[$index];
            if ($char === '\\') {
                $index += 2;
                continue;
            }
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    $index++;
                    break;
                }
            }
            $index++;
        }

        return substr($stream, $start, $index - $start);
    }

    private function readHexToken(string $stream, int &$index): string
    {
        $start = $index;
        $length = strlen($stream);
        $index++;

        while ($index < $length && $stream[$index] !== '>') {
            $index++;
        }
        if ($index < $length) {
            $index++;
        }

        return substr($stream, $start, $index - $start);
    }

    private function readDictionaryToken(string $stream, int &$index): string
    {
        $body = $this->readPdfDictionaryTokenAt($stream, $index);
        if ($body === null) {
            $index += 2;
            return '<<';
        }

        return '<<' . $body . '>>';
    }

    private function readArrayToken(string $stream, int &$index): string
    {
        $start = $index;
        $length = strlen($stream);
        $depth = 0;

        while ($index < $length) {
            $char = $stream[$index];
            if ($char === '(') {
                $this->readLiteralToken($stream, $index);
                continue;
            }
            if ($char === '<' && $index + 1 < $length && $stream[$index + 1] === '<') {
                $this->readDictionaryToken($stream, $index);
                continue;
            }
            if ($char === '<' && ($index + 1 >= $length || $stream[$index + 1] !== '<')) {
                $this->readHexToken($stream, $index);
                continue;
            }
            if ($char === '%') {
                $this->skipPdfComment($stream, $index);
                continue;
            }
            if ($char === '[') {
                $depth++;
                $index++;
                continue;
            }
            if ($char === ']') {
                $depth--;
                $index++;
                if ($depth <= 0) {
                    break;
                }
                continue;
            }
            $index++;
        }

        return substr($stream, $start, $index - $start);
    }

    private function readNameToken(string $stream, int &$index): string
    {
        $start = $index;
        $index++;
        $length = strlen($stream);

        while ($index < $length && !$this->isPdfNameDelimiter($stream[$index])) {
            $index++;
        }

        return substr($stream, $start, $index - $start);
    }

    private function isDelimiter(string $char): bool
    {
        return ctype_space($char) || str_contains('[]()<>{}%', $char);
    }

    private function isPdfNameDelimiter(string $char): bool
    {
        return ctype_space($char) || str_contains('[]()<>{}/%', $char);
    }

    /**
     * @param list<string> $operands
     */
    private function textShowingOperand(string $operator, array $operands): ?string
    {
        if ($operator === '"') {
            for ($index = count($operands) - 1; $index >= 0; $index--) {
                if ($this->isTextOperand($operands[$index])) {
                    return $operands[$index];
                }
            }

            return null;
        }

        $operand = end($operands);
        return is_string($operand) && $this->isTextOperand($operand) ? $operand : null;
    }

    private function isTextShowingOperator(string $token): bool
    {
        return in_array($token, ['Tj', 'TJ', "'", '"'], true);
    }

    /**
     * @param list<string> $operands
     * @param array<string, array{actualText: string|null, altText: string|null}> $markedContentProperties
     */
    private function markedContentReplacementOperand(array $operands, array $markedContentProperties): ?string
    {
        if (count($operands) < 2) {
            return null;
        }

        $propertyOperand = trim((string) $operands[count($operands) - 1]);
        if (str_starts_with($propertyOperand, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($propertyOperand, 0);
            if ($dictionary === null) {
                return null;
            }

            return $this->markedContentReplacementFromProperty([
                'actualText' => $this->pdfOptionalStringValueAfterName($dictionary, 'ActualText', []),
                'altText' => $this->pdfOptionalStringValueAfterName($dictionary, 'Alt', []),
            ]);
        }

        if (!str_starts_with($propertyOperand, '/')) {
            return null;
        }

        $resourceName = $this->decodePdfName(substr($propertyOperand, 1));
        return isset($markedContentProperties[$resourceName])
            ? $this->markedContentReplacementFromProperty($markedContentProperties[$resourceName])
            : null;
    }

    /**
     * @param array{actualText: string|null, altText: string|null} $property
     */
    private function markedContentReplacementFromProperty(array $property): ?string
    {
        if ($property['actualText'] !== null) {
            return $property['actualText'];
        }

        return $property['altText'];
    }

    /**
     * @param list<array{replacement: string|null, emitted: bool}> $markedContentStack
     */
    private function activeMarkedContentReplacementIndex(array $markedContentStack): ?int
    {
        foreach ($markedContentStack as $index => $markedContent) {
            if ($markedContent['replacement'] !== null) {
                return $index;
            }
        }

        return null;
    }

    private function isTextOperand(string $token): bool
    {
        $token = ltrim($token);
        return str_starts_with($token, '(') || str_starts_with($token, '[') || preg_match('/^<[\da-fA-F\s]*>$/', $token) === 1;
    }

    private function isOperator(string $token): bool
    {
        return preg_match('/^[A-Za-z*"\']+$/', $token) === 1;
    }

    /**
     * @param list<string> $operands
     */
    private function fontResourceOperand(array $operands): ?string
    {
        if (count($operands) < 2) {
            return null;
        }

        $operand = $operands[count($operands) - 2];
        if (!str_starts_with($operand, '/')) {
            return null;
        }

        return $this->decodePdfName(substr($operand, 1));
    }

    private function decodePdfName(string $name): string
    {
        return preg_replace_callback('/#([\da-fA-F]{2})/', static function (array $match): string {
            return chr(hexdec($match[1]));
        }, $name) ?? $name;
    }

    private function encodePdfName(string $name): string
    {
        $encoded = '';
        for ($index = 0, $length = strlen($name); $index < $length; $index++) {
            $char = $name[$index];
            $ordinal = ord($char);
            if ($ordinal <= 0x20 || $ordinal >= 0x7f || str_contains('#[]()<>{}/%', $char)) {
                $encoded .= sprintf('#%02X', $ordinal);
                continue;
            }

            $encoded .= $char;
        }

        return $encoded;
    }

    /**
     * @param array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontToUnicodeMaps
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}|null
     */
    private function currentToUnicodeMap(array $fontToUnicodeMaps, ?string $fontResource): ?array
    {
        if ($fontResource !== null && isset($fontToUnicodeMaps[$fontResource])) {
            return $fontToUnicodeMaps[$fontResource];
        }

        return $fontToUnicodeMaps[''] ?? null;
    }

    private function mapWritingMode(?array $toUnicodeMap): int
    {
        $writingMode = $toUnicodeMap['writingMode'] ?? 0;

        return (int) $writingMode === 1 ? 1 : 0;
    }

    /**
     * @param list<string> $operands
     */
    private function fontSizeOperand(array $operands): ?float
    {
        if ($operands === []) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 1]);
    }

    /**
     * @param list<string> $operands
     */
    private function textRenderingModeOperand(array $operands): ?int
    {
        if ($operands === []) {
            return null;
        }

        $mode = $this->numericOperand($operands[count($operands) - 1]);
        if ($mode === null || floor($mode) !== $mode || $mode < 0.0 || $mode > 7.0) {
            return null;
        }

        return (int) $mode;
    }

    private function isVisibleTextRenderingMode(int $mode): bool
    {
        return !in_array($mode, [3, 7], true);
    }

    /**
     * @param list<string> $operands
     */
    private function textLeadingOperand(array $operands): ?float
    {
        if ($operands === []) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 1]);
    }

    /**
     * @param list<string> $operands
     */
    private function textCharacterSpacingOperand(array $operands): ?float
    {
        if ($operands === []) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 1]);
    }

    /**
     * @param list<string> $operands
     */
    private function textWordSpacingOperand(array $operands): ?float
    {
        if ($operands === []) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 1]);
    }

    /**
     * @param list<string> $operands
     */
    private function textHorizontalScaleOperand(array $operands): ?float
    {
        if ($operands === []) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 1]);
    }

    /**
     * @param list<string> $operands
     */
    private function quoteWordSpacingOperand(array $operands): ?float
    {
        if (count($operands) < 3) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 3]);
    }

    /**
     * @param list<string> $operands
     */
    private function quoteCharacterSpacingOperand(array $operands): ?float
    {
        if (count($operands) < 3) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 2]);
    }

    /**
     * @param list<string> $operands
     */
    private function textMoveBreaksLine(array $operands): bool
    {
        $ty = $this->textMoveOperandY($operands);
        if ($ty === null) {
            return true;
        }

        return abs($ty) > 0.000001;
    }

    /**
     * @param list<string> $operands
     */
    private function textMoveCreatesWordGap(
        array $operands,
        ?float $currentTextX = null,
        ?float $currentTextEndX = null,
        float $textMatrixHorizontalScale = 1.0
    ): bool
    {
        $tx = $this->scaledTextMoveOperandX($operands, $textMatrixHorizontalScale);
        if ($tx === null) {
            return false;
        }

        if ($currentTextX !== null && $currentTextEndX !== null) {
            $nextX = $currentTextX + $tx;
            return $nextX - $currentTextEndX >= self::POSITIONED_TEXT_WORD_GAP;
        }

        return $tx >= self::POSITIONED_TEXT_WORD_GAP;
    }

    /**
     * @param list<string> $operands
     */
    private function verticalTextMoveBreaksLine(array $operands): bool
    {
        $tx = $this->textMoveOperandX($operands);
        if ($tx === null) {
            return true;
        }

        return abs($tx) > 0.000001;
    }

    /**
     * @param list<string> $operands
     */
    private function verticalTextMoveCreatesWordGap(array $operands, ?float $currentTextY, ?float $currentTextEndY): bool
    {
        if ($currentTextY === null || $currentTextEndY === null) {
            return false;
        }

        $nextY = $this->textMoveY($operands, $currentTextY);
        if ($nextY === null) {
            return false;
        }

        return abs($nextY - $currentTextEndY) >= self::POSITIONED_TEXT_WORD_GAP;
    }

    /**
     * @param list<string> $operands
     */
    private function textMoveX(array $operands, ?float $currentTextX, float $textMatrixHorizontalScale = 1.0): ?float
    {
        $tx = $this->scaledTextMoveOperandX($operands, $textMatrixHorizontalScale);
        if ($tx === null) {
            return null;
        }

        return $currentTextX === null ? $tx : $currentTextX + $tx;
    }

    /**
     * @param list<string> $operands
     */
    private function scaledTextMoveOperandX(array $operands, float $textMatrixHorizontalScale): ?float
    {
        $tx = $this->textMoveOperandX($operands);
        if ($tx === null) {
            return null;
        }

        if (!is_finite($textMatrixHorizontalScale)) {
            return $tx;
        }

        return $tx * $textMatrixHorizontalScale;
    }

    /**
     * @param list<string> $operands
     */
    private function textMoveY(array $operands, ?float $currentTextY): ?float
    {
        $ty = $this->textMoveOperandY($operands);
        if ($ty === null) {
            return null;
        }

        return $currentTextY === null ? $ty : $currentTextY + $ty;
    }

    /**
     * @param list<string> $operands
     */
    private function textMoveOperandX(array $operands): ?float
    {
        if (count($operands) < 2) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 2]);
    }

    /**
     * @param list<string> $operands
     */
    private function textMoveOperandY(array $operands): ?float
    {
        if (count($operands) < 2) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 1]);
    }

    /**
     * @param list<string> $operands
     */
    private function textMatrixBreaksLine(array $operands, ?float $currentTextY): bool
    {
        $matrixY = $this->textMatrixY($operands);
        if ($matrixY === null || $currentTextY === null) {
            return true;
        }

        return abs($matrixY - $currentTextY) > 0.000001;
    }

    /**
     * @param list<string> $operands
     */
    private function textMatrixCreatesWordGap(array $operands, ?float $currentTextEndX): bool
    {
        $matrixX = $this->textMatrixX($operands);
        if ($matrixX === null || $currentTextEndX === null) {
            return false;
        }

        return $matrixX - $currentTextEndX >= self::POSITIONED_TEXT_WORD_GAP;
    }

    /**
     * @param list<string> $operands
     */
    private function verticalTextMatrixBreaksLine(array $operands, ?float $currentTextX): bool
    {
        $matrixX = $this->textMatrixX($operands);
        if ($matrixX === null || $currentTextX === null) {
            return true;
        }

        return abs($matrixX - $currentTextX) > 0.000001;
    }

    /**
     * @param list<string> $operands
     */
    private function verticalTextMatrixCreatesWordGap(array $operands, ?float $currentTextEndY): bool
    {
        $matrixY = $this->textMatrixY($operands);
        if ($matrixY === null || $currentTextEndY === null) {
            return false;
        }

        return abs($matrixY - $currentTextEndY) >= self::POSITIONED_TEXT_WORD_GAP;
    }

    /**
     * @param list<string> $operands
     */
    private function textMatrixX(array $operands): ?float
    {
        if (count($operands) < 6) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 2]);
    }

    /**
     * @param list<string> $operands
     */
    private function textMatrixY(array $operands): ?float
    {
        if (count($operands) < 6) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 1]);
    }

    /**
     * @param list<string> $operands
     */
    private function textMatrixHorizontalScale(array $operands): ?float
    {
        if (count($operands) < 6) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 6]);
    }

    /**
     * @param list<string> $operands
     */
    private function textMatrixVerticalScale(array $operands): ?float
    {
        if (count($operands) < 6) {
            return null;
        }

        $c = $this->numericOperand($operands[count($operands) - 4]);
        $d = $this->numericOperand($operands[count($operands) - 3]);
        if ($c === null || $d === null) {
            return null;
        }

        return sqrt(($c * $c) + ($d * $d));
    }

    private function advanceTextYByLeading(?float $currentTextY, ?float $currentTextLeading): ?float
    {
        if ($currentTextY === null || $currentTextLeading === null) {
            return null;
        }

        return $currentTextY - $currentTextLeading;
    }

    /**
     * @param list<string> $lines
     */
    private function pushLine(array &$lines, string &$currentLine): void
    {
        $line = rtrim($currentLine);
        if ($line !== '') {
            $lines[] = $line;
        }
        $currentLine = '';
    }

    private function appendPositionedText(string &$currentLine, string $decoded, bool &$pendingPositionWordGap): void
    {
        if ($decoded === '') {
            $pendingPositionWordGap = false;
            return;
        }

        if ($pendingPositionWordGap && !$this->endsWithWhitespace($currentLine) && !$this->startsWithWhitespace($decoded)) {
            $currentLine .= ' ';
        }

        $currentLine .= $decoded;
        $pendingPositionWordGap = false;
    }

    private function advanceTextEndX(
        ?float $currentTextEndX,
        string $decoded,
        ?float $fontSize,
        float $characterSpacing,
        float $wordSpacing,
        float $horizontalScale,
        ?array $glyphWidths = null,
        ?int $sourceSpaceCount = null
    ): ?float {
        if ($currentTextEndX === null || $decoded === '') {
            return $currentTextEndX;
        }

        $fontSize ??= 12.0;
        $characters = $glyphWidths !== null && $glyphWidths !== [] ? count($glyphWidths) : $this->length($decoded);
        $baseAdvance = $glyphWidths !== null && $glyphWidths !== []
            ? (array_sum($glyphWidths) / 1000.0) * $fontSize
            : $characters * $fontSize * self::SIMPLE_TEXT_ADVANCE_RATIO;
        $spaceCount = $sourceSpaceCount ?? substr_count($decoded, ' ');
        $spacingAdvance = (max(0, $characters - 1) * $characterSpacing) + ($spaceCount * $wordSpacing);
        $scale = $horizontalScale / 100.0;

        return $currentTextEndX + (($baseAdvance + $spacingAdvance) * $scale);
    }

    private function advanceTextEndXForOperand(
        ?float $currentTextEndX,
        string $operand,
        ?array $toUnicodeMap,
        ?float $fontSize,
        float $characterSpacing,
        float $wordSpacing,
        float $horizontalScale
    ): ?float {
        if ($currentTextEndX === null) {
            return null;
        }

        $operand = trim($operand);
        if (!str_starts_with($operand, '[')) {
            return $this->advanceTextEndX(
                $currentTextEndX,
                $this->decodeTextOperand($operand, $toUnicodeMap),
                $fontSize,
                $characterSpacing,
                $wordSpacing,
                $horizontalScale,
                $this->glyphWidthsForTextOperand($operand, $toUnicodeMap),
                $this->sourceSpaceCountForTextOperand($operand, $toUnicodeMap)
            );
        }

        $endX = $currentTextEndX;
        foreach ($this->textArrayElements($operand) as $element) {
            if ($element['type'] === 'text') {
                $endX = $this->advanceTextEndX(
                    $endX,
                    $this->decodeTextOperand($element['value'], $toUnicodeMap),
                    $fontSize,
                    $characterSpacing,
                    $wordSpacing,
                    $horizontalScale,
                    $this->glyphWidthsForTextOperand((string) $element['value'], $toUnicodeMap),
                    $this->sourceSpaceCountForTextOperand((string) $element['value'], $toUnicodeMap)
                );
                continue;
            }

            $endX = $this->adjustTextEndX($endX, (float) $element['value'], $fontSize, $horizontalScale);
        }

        return $endX;
    }

    private function advanceTextEndY(
        ?float $currentTextEndY,
        string $decoded,
        ?float $fontSize,
        float $characterSpacing,
        float $wordSpacing,
        ?array $glyphDisplacements = null,
        ?int $sourceSpaceCount = null
    ): ?float {
        if ($currentTextEndY === null || $decoded === '') {
            return $currentTextEndY;
        }

        $fontSize ??= 12.0;
        $characters = $glyphDisplacements !== null && $glyphDisplacements !== [] ? count($glyphDisplacements) : $this->length($decoded);
        $baseAdvance = $glyphDisplacements !== null && $glyphDisplacements !== []
            ? (array_sum($glyphDisplacements) / 1000.0) * $fontSize
            : -$characters * $fontSize;
        $spaceCount = $sourceSpaceCount ?? substr_count($decoded, ' ');
        $spacingAdvance = (max(0, $characters - 1) * $characterSpacing) + ($spaceCount * $wordSpacing);
        $direction = $baseAdvance < 0 ? -1.0 : 1.0;

        return $currentTextEndY + $baseAdvance + ($spacingAdvance * $direction);
    }

    private function advanceTextEndYForOperand(
        ?float $currentTextEndY,
        string $operand,
        ?array $toUnicodeMap,
        ?float $fontSize,
        float $characterSpacing,
        float $wordSpacing
    ): ?float {
        if ($currentTextEndY === null) {
            return null;
        }

        $operand = trim($operand);
        if (!str_starts_with($operand, '[')) {
            return $this->advanceTextEndY(
                $currentTextEndY,
                $this->decodeTextOperand($operand, $toUnicodeMap),
                $fontSize,
                $characterSpacing,
                $wordSpacing,
                $this->glyphVerticalDisplacementsForTextOperand($operand, $toUnicodeMap),
                $this->sourceSpaceCountForTextOperand($operand, $toUnicodeMap)
            );
        }

        $endY = $currentTextEndY;
        foreach ($this->textArrayElements($operand) as $element) {
            if ($element['type'] === 'text') {
                $endY = $this->advanceTextEndY(
                    $endY,
                    $this->decodeTextOperand($element['value'], $toUnicodeMap),
                    $fontSize,
                    $characterSpacing,
                    $wordSpacing,
                    $this->glyphVerticalDisplacementsForTextOperand((string) $element['value'], $toUnicodeMap),
                    $this->sourceSpaceCountForTextOperand((string) $element['value'], $toUnicodeMap)
                );
                continue;
            }

            $endY = $this->adjustTextEndY($endY, (float) $element['value'], $fontSize);
        }

        return $endY;
    }

    /**
     * @return list<float>|null
     */
    private function glyphWidthsForTextOperand(string $operand, ?array $toUnicodeMap): ?array
    {
        if ($toUnicodeMap === null) {
            return null;
        }

        $cidWidths = $toUnicodeMap['cidWidths'] ?? [];
        $defaultWidth = $toUnicodeMap['cidDefaultWidth'] ?? null;
        $cidSet = $toUnicodeMap['cidSet'] ?? null;
        $hasWidthData = (is_array($cidWidths) && $cidWidths !== []) || $defaultWidth !== null || is_array($cidSet);
        if (!$hasWidthData && !$this->hasSourceBoundaryDataForGlyphAdvance($toUnicodeMap)) {
            return null;
        }

        $hex = $this->textOperandSourceHex($operand);
        if ($hex === '') {
            return [];
        }

        $sourceKeys = $this->textOperandSourceKeysForFontWidths($hex, $toUnicodeMap);
        if ($sourceKeys === []) {
            return [];
        }

        if (!$hasWidthData) {
            return array_fill(0, count($sourceKeys), 500.0);
        }

        $widths = [];
        foreach ($sourceKeys as $key) {
            $cid = $this->cidForWidthSourceKey($key, $toUnicodeMap);
            if (is_array($cidWidths) && array_key_exists($cid, $cidWidths)) {
                $widths[] = (float) $cidWidths[$cid];
                continue;
            }
            if (is_array($cidSet) && !isset($cidSet[$cid])) {
                $widths[] = 500.0;
                continue;
            }
            $widths[] = (float) ($defaultWidth ?? 500.0);
        }

        return $widths;
    }

    private function sourceSpaceCountForTextOperand(string $operand, ?array $toUnicodeMap): ?int
    {
        if ($toUnicodeMap === null || !$this->hasSourceBoundaryDataForGlyphAdvance($toUnicodeMap)) {
            return null;
        }

        $hex = $this->textOperandSourceHex($operand);
        if ($hex === '') {
            return null;
        }

        $sourceKeys = $this->textOperandSourceKeysForFontWidths($hex, $toUnicodeMap);
        if ($sourceKeys === []) {
            return null;
        }

        $spaces = 0;
        foreach ($sourceKeys as $key) {
            if ($this->sourceKeyUsesWordSpacing($key, $toUnicodeMap)) {
                $spaces++;
            }
        }

        return $spaces;
    }

    private function sourceKeyUsesWordSpacing(string $sourceKey, array $toUnicodeMap): bool
    {
        $usesCidMapForWordSpacing = ($toUnicodeMap['wordSpacingUsesCidMap'] ?? false) === true;
        $cidMap = $toUnicodeMap['cidMap'] ?? [];
        if (
            $usesCidMapForWordSpacing
            && is_array($cidMap)
            && array_key_exists($sourceKey, $cidMap)
            && is_int($cidMap[$sourceKey])
        ) {
            return $cidMap[$sourceKey] === 0x20;
        }

        if (hexdec($sourceKey) === 0x20) {
            return true;
        }

        return $usesCidMapForWordSpacing
            && $this->cidForWidthSourceKey($sourceKey, $toUnicodeMap) === 0x20;
    }

    private function hasSourceBoundaryDataForGlyphAdvance(array $toUnicodeMap): bool
    {
        foreach (['map', 'codeSpaceRanges', 'cidMap', 'cidCodeSpaceRanges'] as $key) {
            $value = $toUnicodeMap[$key] ?? null;
            if (is_array($value) && $value !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<float>|null
     */
    private function glyphVerticalDisplacementsForTextOperand(string $operand, ?array $toUnicodeMap): ?array
    {
        if ($toUnicodeMap === null || $this->mapWritingMode($toUnicodeMap) !== 1) {
            return null;
        }

        $cidDisplacements = $toUnicodeMap['cidVerticalDisplacements'] ?? [];
        $defaultDisplacement = $toUnicodeMap['cidDefaultVerticalDisplacement'] ?? -1000.0;
        $cidSet = $toUnicodeMap['cidSet'] ?? null;

        $hex = $this->textOperandSourceHex($operand);
        if ($hex === '') {
            return [];
        }

        $displacements = [];
        foreach ($this->textOperandSourceKeysForFontWidths($hex, $toUnicodeMap) as $key) {
            $cid = $this->cidForWidthSourceKey($key, $toUnicodeMap);
            if (is_array($cidDisplacements) && array_key_exists($cid, $cidDisplacements)) {
                $displacements[] = (float) $cidDisplacements[$cid];
                continue;
            }

            if (is_array($cidSet) && !isset($cidSet[$cid])) {
                $displacements[] = $defaultDisplacement < 0 ? -500.0 : 500.0;
                continue;
            }

            $displacements[] = (float) $defaultDisplacement;
        }

        return $displacements;
    }

    private function cidForWidthSourceKey(string $sourceKey, array $toUnicodeMap): int
    {
        $cidMap = $toUnicodeMap['cidMap'] ?? [];
        if (is_array($cidMap) && array_key_exists($sourceKey, $cidMap) && is_int($cidMap[$sourceKey])) {
            return $cidMap[$sourceKey];
        }

        return hexdec($sourceKey);
    }

    private function textOperandSourceHex(string $operand): string
    {
        $operand = trim($operand);
        if (str_starts_with($operand, '<')) {
            $hex = preg_replace('/\s+/', '', strtolower(trim($operand, '<>')));
            if ($hex === null || $hex === '' || preg_match('/^[\da-f]+$/', $hex) !== 1) {
                return '';
            }
            if (strlen($hex) % 2 === 1) {
                $hex .= '0';
            }

            return $hex;
        }

        if (str_starts_with($operand, '(')) {
            return bin2hex($this->decodeLiteralString(substr($operand, 1, -1)));
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function textOperandSourceKeysForFontWidths(string $hex, array $toUnicodeMap): array
    {
        $widthMap = $toUnicodeMap;
        $cidCodeSpaceRanges = $toUnicodeMap['cidCodeSpaceRanges'] ?? [];
        if (is_array($cidCodeSpaceRanges) && $cidCodeSpaceRanges !== []) {
            $widthMap['codeSpaceRanges'] = $cidCodeSpaceRanges;
        }

        $cidMap = $toUnicodeMap['cidMap'] ?? [];
        if (is_array($cidMap) && $cidMap !== []) {
            $widthMap['map'] = $cidMap;
        }

        $zeroPaddedKeys = $this->zeroPaddedSourceKeysForFontWidths($hex, $widthMap, $toUnicodeMap);
        if ($zeroPaddedKeys !== []) {
            return $zeroPaddedKeys;
        }

        $sourceKeys = $this->textOperandSourceKeys($hex, $widthMap);
        $toUnicodeFallbackKeys = $this->toUnicodeSourceKeysForFontWidthsWhenCidMetricsMiss($sourceKeys, $hex, $toUnicodeMap);

        return $toUnicodeFallbackKeys === [] ? $sourceKeys : $toUnicodeFallbackKeys;
    }

    /**
     * @param list<string> $sourceKeys
     * @return list<string>
     */
    private function toUnicodeSourceKeysForFontWidthsWhenCidMetricsMiss(array $sourceKeys, string $hex, array $toUnicodeMap): array
    {
        $cidCodeSpaceRanges = $toUnicodeMap['cidCodeSpaceRanges'] ?? [];
        if (!is_array($cidCodeSpaceRanges) || $cidCodeSpaceRanges === []) {
            return [];
        }

        $cidMap = $toUnicodeMap['cidMap'] ?? [];
        if (is_array($cidMap) && $cidMap !== []) {
            return [];
        }

        if ($sourceKeys === [] || $this->sourceKeysHaveAnyDirectFontMetric($sourceKeys, $toUnicodeMap)) {
            return [];
        }

        $fallbackKeys = $this->textOperandSourceKeys($hex, $toUnicodeMap);
        if ($fallbackKeys === [] || $fallbackKeys === $sourceKeys || count($fallbackKeys) <= count($sourceKeys)) {
            return [];
        }

        return $this->sourceKeysHaveAllDirectFontMetrics($fallbackKeys, $toUnicodeMap) ? $fallbackKeys : [];
    }

    /**
     * @return list<string>
     */
    private function zeroPaddedSourceKeysForFontWidths(string $hex, array $widthMap, array $toUnicodeMap): array
    {
        $codeSpaceRanges = $widthMap['codeSpaceRanges'] ?? [];
        if (is_array($codeSpaceRanges) && $codeSpaceRanges !== []) {
            return [];
        }

        $mappings = $widthMap['map'] ?? [];
        if (!is_array($mappings) || $mappings === []) {
            return [];
        }

        $keyLengths = array_values(array_unique(array_map('strlen', array_keys($mappings))));
        rsort($keyLengths, SORT_NUMERIC);
        if ($keyLengths === []) {
            return [];
        }

        $normalized = $this->normalizeHexKey($hex);
        if ($normalized === '') {
            return [];
        }

        $keys = [];
        $collapsed = false;
        $offset = 0;
        $length = strlen($normalized);
        while ($offset < $length) {
            $matched = false;
            foreach ($keyLengths as $keyLength) {
                if ($keyLength <= 0 || $offset + $keyLength > $length) {
                    continue;
                }

                $exact = substr($normalized, $offset, $keyLength);
                if (array_key_exists($exact, $mappings)) {
                    $keys[] = $exact;
                    $offset += $keyLength;
                    $matched = true;
                    break;
                }

                if (
                    preg_match('/^(?:00)+$/', $exact) !== 1
                    || $offset + ($keyLength * 2) > $length
                ) {
                    continue;
                }

                $suffix = substr($normalized, $offset + $keyLength, $keyLength);
                if (!array_key_exists($suffix, $mappings)) {
                    continue;
                }

                $combined = substr($normalized, $offset, $keyLength * 2);
                if (!$this->fontWidthMapContainsCid(hexdec($combined), $toUnicodeMap)) {
                    continue;
                }

                $keys[] = $combined;
                $offset += $keyLength * 2;
                $matched = true;
                $collapsed = true;
                break;
            }

            if (!$matched) {
                return [];
            }
        }

        return $collapsed ? $keys : [];
    }

    private function fontWidthMapContainsCid(int $cid, array $toUnicodeMap): bool
    {
        $cidWidths = $toUnicodeMap['cidWidths'] ?? [];
        if (is_array($cidWidths) && array_key_exists($cid, $cidWidths)) {
            return true;
        }

        $defaultWidth = $toUnicodeMap['cidDefaultWidth'] ?? null;
        if ((is_int($defaultWidth) || is_float($defaultWidth)) && $cid >= 0 && $cid <= 0xffff) {
            return true;
        }

        $cidSet = $toUnicodeMap['cidSet'] ?? [];
        return is_array($cidSet) && isset($cidSet[$cid]);
    }

    /**
     * @param list<string> $sourceKeys
     */
    private function sourceKeysHaveAnyDirectFontMetric(array $sourceKeys, array $toUnicodeMap): bool
    {
        foreach ($sourceKeys as $sourceKey) {
            if ($this->sourceKeyHasDirectFontMetric($sourceKey, $toUnicodeMap)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $sourceKeys
     */
    private function sourceKeysHaveAllDirectFontMetrics(array $sourceKeys, array $toUnicodeMap): bool
    {
        foreach ($sourceKeys as $sourceKey) {
            if (!$this->sourceKeyHasDirectFontMetric($sourceKey, $toUnicodeMap)) {
                return false;
            }
        }

        return $sourceKeys !== [];
    }

    private function sourceKeyHasDirectFontMetric(string $sourceKey, array $toUnicodeMap): bool
    {
        $cid = $this->cidForWidthSourceKey($sourceKey, $toUnicodeMap);
        $cidWidths = $toUnicodeMap['cidWidths'] ?? [];
        if (is_array($cidWidths) && array_key_exists($cid, $cidWidths)) {
            return true;
        }

        $cidSet = $toUnicodeMap['cidSet'] ?? [];
        return is_array($cidSet) && isset($cidSet[$cid]);
    }

    /**
     * @return list<string>
     */
    private function textOperandSourceKeys(string $hex, array $toUnicodeMap): array
    {
        $normalized = $this->normalizeHexKey($hex);
        if ($normalized === '') {
            return [];
        }

        $mappings = $toUnicodeMap['map'] ?? [];
        $keyLengths = is_array($mappings)
            ? array_values(array_unique(array_map('strlen', array_keys($mappings))))
            : [];
        rsort($keyLengths, SORT_NUMERIC);

        $keys = [];
        $offset = 0;
        $length = strlen($normalized);
        while ($offset < $length) {
            $sourceLength = $this->toUnicodeSourceLength(
                $keyLengths,
                $length - $offset,
                $toUnicodeMap['codeSpaceRanges'] ?? [],
                is_array($mappings) ? $mappings : [],
                $normalized,
                $offset
            );
            $keys[] = substr($normalized, $offset, $sourceLength);
            $offset += $sourceLength;
        }

        return $keys;
    }

    private function adjustTextEndX(?float $currentTextEndX, float $adjustment, ?float $fontSize, float $horizontalScale): ?float
    {
        if ($currentTextEndX === null) {
            return null;
        }

        $fontSize ??= 12.0;
        $scale = $horizontalScale / 100.0;

        return $currentTextEndX - (($adjustment / 1000.0) * $fontSize * $scale);
    }

    private function adjustTextEndY(?float $currentTextEndY, float $adjustment, ?float $fontSize): ?float
    {
        if ($currentTextEndY === null) {
            return null;
        }

        $fontSize ??= 12.0;

        return $currentTextEndY - (($adjustment / 1000.0) * $fontSize);
    }

    /**
     * @return list<array{type: string, value: string|float}>
     */
    private function textArrayElements(string $operand): array
    {
        $operand = trim($operand);
        $body = substr($operand, 1, -1);
        $elements = [];
        $index = 0;
        $length = strlen($body);

        while ($index < $length) {
            if (ctype_space($body[$index])) {
                $index++;
                continue;
            }

            if ($body[$index] === '%') {
                $this->skipPdfComment($body, $index);
                continue;
            }

            if ($body[$index] === '(') {
                $elements[] = [
                    'type' => 'text',
                    'value' => $this->readLiteralToken($body, $index),
                ];
                continue;
            }

            if ($body[$index] === '<' && ($index + 1 >= $length || $body[$index + 1] !== '<')) {
                $elements[] = [
                    'type' => 'text',
                    'value' => $this->readHexToken($body, $index),
                ];
                continue;
            }

            $start = $index;
            while ($index < $length && !ctype_space($body[$index]) && !str_contains('[]()<>{}%', $body[$index])) {
                $index++;
            }

            if ($index === $start) {
                $index++;
                continue;
            }

            $token = substr($body, $start, $index - $start);
            $adjustment = $this->numericOperand($token);
            if ($adjustment !== null) {
                $elements[] = [
                    'type' => 'adjustment',
                    'value' => $adjustment,
                ];
            }
        }

        return $elements;
    }

    private function skipPdfComment(string $value, int &$index): void
    {
        $length = strlen($value);
        while ($index < $length && !in_array($value[$index], ["\n", "\r"], true)) {
            $index++;
        }
    }

    private function startsWithWhitespace(string $text): bool
    {
        return $text !== '' && ctype_space($text[0]);
    }

    private function endsWithWhitespace(string $text): bool
    {
        return $text !== '' && ctype_space(substr($text, -1));
    }

    private function numericOperand(string $operand): ?float
    {
        if (preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/', $operand) !== 1) {
            return null;
        }

        return (float) $operand;
    }

    /**
     * @param array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}|null $toUnicodeMap
     */
    private function decodeTextOperand(string $operand, ?array $toUnicodeMap = null): string
    {
        $operand = trim($operand);
        if (str_starts_with($operand, '[')) {
            $text = '';
            foreach ($this->textArrayElements($operand) as $element) {
                if ($element['type'] === 'text') {
                    $text .= $this->decodeTextOperand((string) $element['value'], $toUnicodeMap);
                }
            }
            return $text;
        }
        if (str_starts_with($operand, '<')) {
            $hex = preg_replace('/\s+/', '', trim($operand, '<>'));
            if ($hex === null || $hex === '') {
                return '';
            }
            if (strlen($hex) % 2 === 1) {
                $hex .= '0';
            }
            if ($toUnicodeMap !== null) {
                return $this->decodeHexStringWithToUnicodeMap($hex, $toUnicodeMap);
            }
            return $this->decodeHexString($hex);
        }

        $decoded = $this->decodeLiteralString(substr($operand, 1, -1));
        if ($toUnicodeMap !== null) {
            return $this->decodeHexStringWithToUnicodeMap(bin2hex($decoded), $toUnicodeMap);
        }

        return $this->decodePdfStringBytes($decoded);
    }

    /**
     * @param array{map?: array<string, string>, codeSpaceRanges?: list<array{start: int, end: int, width: int}>}|null $toUnicodeMap
     */
    private function decodePositionedTextOperand(
        string $operand,
        ?array $toUnicodeMap,
        ?float $fontSize,
        float $characterSpacing,
        float $wordSpacing,
        float $horizontalScale
    ): string {
        $operand = trim($operand);
        if (
            !str_starts_with($operand, '[')
            || $toUnicodeMap === null
            || $this->mapWritingMode($toUnicodeMap) === 1
            || !$this->hasSourceBoundaryDataForGlyphAdvance($toUnicodeMap)
        ) {
            return $this->decodeTextOperand($operand, $toUnicodeMap);
        }

        $text = '';
        $endX = 0.0;
        $pendingWordGap = false;
        foreach ($this->textArrayElements($operand) as $element) {
            if ($element['type'] === 'text') {
                $textOperand = (string) $element['value'];
                $decoded = $this->decodeTextOperand($textOperand, $toUnicodeMap);
                if ($decoded !== '') {
                    if ($pendingWordGap && !$this->endsWithWhitespace($text) && !$this->startsWithWhitespace($decoded)) {
                        $text .= ' ';
                    }
                    $text .= $decoded;
                    $pendingWordGap = false;
                }

                $endX = $this->advanceTextEndX(
                    $endX,
                    $decoded,
                    $fontSize,
                    $characterSpacing,
                    $wordSpacing,
                    $horizontalScale,
                    $this->glyphWidthsForTextOperand($textOperand, $toUnicodeMap),
                    $this->sourceSpaceCountForTextOperand($textOperand, $toUnicodeMap)
                ) ?? $endX;
                continue;
            }

            $previousEndX = $endX;
            $adjustedEndX = $this->adjustTextEndX($endX, (float) $element['value'], $fontSize, $horizontalScale);
            if ($adjustedEndX === null) {
                continue;
            }
            if ($adjustedEndX - $previousEndX >= self::POSITIONED_TEXT_WORD_GAP) {
                $pendingWordGap = $text !== '';
            }
            $endX = $adjustedEndX;
        }

        return $text;
    }

    /**
     * @param array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>} $toUnicodeMap
     */
    private function decodeHexStringWithToUnicodeMap(string $hex, array $toUnicodeMap): string
    {
        $normalized = $this->normalizeHexKey($hex);
        if ($normalized === '') {
            return '';
        }

        $mappings = $toUnicodeMap['map'] ?? [];
        $keyLengths = array_values(array_unique(array_map('strlen', array_keys($mappings))));
        rsort($keyLengths, SORT_NUMERIC);
        if ($keyLengths === [] && ($toUnicodeMap['codeSpaceRanges'] ?? []) === []) {
            return $this->decodeHexString($normalized);
        }

        $text = '';
        $offset = 0;
        $length = strlen($normalized);
        while ($offset < $length) {
            $sourceLength = $this->toUnicodeSourceLength(
                $keyLengths,
                $length - $offset,
                $toUnicodeMap['codeSpaceRanges'] ?? [],
                $mappings,
                $normalized,
                $offset
            );
            $key = substr($normalized, $offset, $sourceLength);
            $text .= array_key_exists($key, $mappings)
                ? $mappings[$key]
                : $this->decodeUnmappedToUnicodeSource($key);
            $offset += $sourceLength;
        }

        return $text;
    }

    /**
     * @param list<int> $keyLengths
     * @param list<array{start: int, end: int, width: int}> $codeSpaceRanges
     * @param array<string, string> $mappings
     */
    private function toUnicodeSourceLength(
        array $keyLengths,
        int $remainingHexLength,
        array $codeSpaceRanges,
        array $mappings,
        string $normalized,
        int $offset
    ): int {
        foreach ($codeSpaceRanges as $range) {
            $width = $range['width'];
            if ($width <= 0 || $width > $remainingHexLength) {
                continue;
            }

            $source = hexdec(substr($normalized, $offset, $width));
            if ($source >= $range['start'] && $source <= $range['end']) {
                return $width;
            }
        }

        foreach ($keyLengths as $keyLength) {
            if ($keyLength <= 0 || $keyLength > $remainingHexLength) {
                continue;
            }

            if (array_key_exists(substr($normalized, $offset, $keyLength), $mappings)) {
                return $keyLength;
            }
        }

        $usableLengths = array_values(array_filter(
            $keyLengths,
            static fn (int $keyLength): bool => $keyLength > 0 && $keyLength <= $remainingHexLength
        ));
        rsort($usableLengths, SORT_NUMERIC);

        return $usableLengths[0] ?? min(2, max(1, $remainingHexLength));
    }

    private function decodeUnmappedToUnicodeSource(string $hex): string
    {
        if ($hex === '') {
            return '';
        }

        $decoded = $this->decodeCMapUnicodeHex($hex);
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $decoded) ?? $decoded;
    }

    private function decodeHexString(string $hex): string
    {
        $bytes = hex2bin($hex);
        if ($bytes === false) {
            return '';
        }

        return $this->decodePdfStringBytes($bytes);
    }

    private function decodePdfStringBytes(string $bytes): string
    {
        $prefix = strtolower(bin2hex(substr($bytes, 0, 2)));
        if ($prefix === 'feff') {
            $decoded = iconv('UTF-16BE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }
        if ($prefix === 'fffe') {
            $decoded = iconv('UTF-16LE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }

        return $bytes;
    }

    private function decodeLiteralString(string $value): string
    {
        $value = preg_replace("/\\\\\r\n|\\\\\n|\\\\\r/s", '', $value) ?? $value;

        return preg_replace_callback('/\\\\([0-7]{1,3}|.)/s', static function (array $match): string {
            return match ($match[1]) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\x08",
                'f' => "\x0c",
                '(' => '(',
                ')' => ')',
                '\\' => '\\',
                default => preg_match('/^[0-7]+$/', $match[1]) === 1 ? chr(octdec($match[1]) & 0xff) : $match[1],
            };
        }, $value) ?? $value;
    }

    private function length(string $text): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($text, 'UTF-8');
        }

        return strlen($text);
    }
}
