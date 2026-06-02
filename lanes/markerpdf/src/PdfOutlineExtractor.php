<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfOutlineExtractor
{
    private const PAGE_ACTION_EVENT_LABELS = [
        'O' => 'page_open',
        'C' => 'page_close',
    ];

    /**
     * Native boundary for marker.cleaners.toc::get_pdf_toc when the PDF outline
     * uses named destinations that pypdfium would normally resolve for us.
     *
     * @return list<array{title: string, level: int, page: int, destination: string|null}>
     */
    public function getPdfToc(string $pdfBytes, int $maxDepth = 15): array
    {
        $objects = $this->parsedObjectValues($pdfBytes);
        $catalog = $this->catalogDictionary($objects);
        if ($catalog === null) {
            return [];
        }

        $pageObjectNumbers = $this->orderedPageObjectNumbers($objects);
        if ($pageObjectNumbers === []) {
            return [];
        }

        $pageIndexes = [];
        foreach ($pageObjectNumbers as $index => $objectNumber) {
            $pageIndexes[$objectNumber] = $index;
        }

        $outlineRoot = $this->resolveDictionary($catalog['Outlines'] ?? null, $objects);
        if ($outlineRoot === null) {
            return [];
        }

        return $this->outlineItems(
            $outlineRoot['First'] ?? null,
            $objects,
            $pageIndexes,
            $this->destinationMap($catalog, $objects),
            max(1, $maxDepth)
        );
    }

    /**
     * Native boundary for PDF outline /S /GoToR actions. Marker receives link
     * and reference metadata from pdftext/pypdfium; this keeps remote document
     * targets reviewable without treating them as same-document page rows.
     *
     * @return list<array{title: string, level: int, file: string, destination: string|null, page: int|null, new_window: bool|null}>
     */
    public function getRemoteGoToActions(string $pdfBytes, int $maxDepth = 15): array
    {
        $objects = $this->parsedObjectValues($pdfBytes);
        $catalog = $this->catalogDictionary($objects);
        if ($catalog === null) {
            return [];
        }

        $outlineRoot = $this->resolveDictionary($catalog['Outlines'] ?? null, $objects);
        if ($outlineRoot === null) {
            return [];
        }

        return $this->remoteGoToOutlineItems(
            $outlineRoot['First'] ?? null,
            $objects,
            $this->destinationMap($catalog, $objects),
            max(1, $maxDepth)
        );
    }

    /**
     * Native boundary for catalog /OpenAction safety review. PDF viewers may
     * run these actions when the document opens; WordPress imports should
     * surface them as metadata only.
     *
     * @return list<array<string, mixed>>
     */
    public function getOpenActionReviewActions(string $pdfBytes): array
    {
        $objects = $this->parsedObjectValues($pdfBytes);
        $catalog = $this->catalogDictionary($objects);
        if ($catalog === null || !array_key_exists('OpenAction', $catalog)) {
            return [];
        }

        $pageObjectNumbers = $this->orderedPageObjectNumbers($objects);
        $pageIndexes = [];
        foreach ($pageObjectNumbers as $index => $objectNumber) {
            $pageIndexes[$objectNumber] = $index;
        }

        $resolved = $this->resolveValue($catalog['OpenAction'], $objects);
        $dict = $this->dictionaryItems($resolved);
        if ($dict !== null && array_key_exists('S', $dict)) {
            $seen = [];

            return $this->reviewActionsFromValue(
                $catalog['OpenAction'],
                $objects,
                $pageIndexes,
                $this->destinationMap($catalog, $objects),
                $seen
            );
        }

        $action = $this->openActionReviewAction(
            $catalog['OpenAction'],
            $objects,
            $pageIndexes,
            $this->destinationMap($catalog, $objects)
        );

        return $action === null ? [] : [$action];
    }

    /**
     * pypdfium exposes bookmark destination view metadata as a view mode and
     * page-position coordinates. Keep the existing getPdfToc() shape stable and
     * expose those fields through this richer review path for WordPress TOC UIs.
     *
     * @return list<array{
     *     title: string,
     *     level: int,
     *     page: int,
     *     destination: string|null,
     *     view_mode: string|null,
     *     view_position: list<float|null>,
     *     view_parameters: array<string, float|null>
     * }>
     */
    public function getPdfTocWithDestinationViews(string $pdfBytes, int $maxDepth = 15): array
    {
        $objects = $this->parsedObjectValues($pdfBytes);
        $catalog = $this->catalogDictionary($objects);
        if ($catalog === null) {
            return [];
        }

        $pageObjectNumbers = $this->orderedPageObjectNumbers($objects);
        if ($pageObjectNumbers === []) {
            return [];
        }

        $pageIndexes = [];
        foreach ($pageObjectNumbers as $index => $objectNumber) {
            $pageIndexes[$objectNumber] = $index;
        }

        $outlineRoot = $this->resolveDictionary($catalog['Outlines'] ?? null, $objects);
        if ($outlineRoot === null) {
            return [];
        }

        return $this->outlineItemsWithDestinationViews(
            $outlineRoot['First'] ?? null,
            $objects,
            $pageIndexes,
            $this->destinationMap($catalog, $objects),
            max(1, $maxDepth)
        );
    }

    /**
     * @return array{
     *     source: list<string>,
     *     page_mode?: string,
     *     page_layout?: string,
     *     open_action?: array{
     *         page: int,
     *         destination: string|null,
     *         view_mode: string|null,
     *         view_position: list<float|null>,
     *         view_parameters: array<string, float|null>
     *     }
     * }
     */
    public function getCatalogPageViewMetadata(string $pdfBytes): array
    {
        $objects = $this->parsedObjectValues($pdfBytes);
        $catalog = $this->catalogDictionary($objects);
        if ($catalog === null) {
            return ['source' => []];
        }

        $pageObjectNumbers = $this->orderedPageObjectNumbers($objects);
        $pageIndexes = [];
        foreach ($pageObjectNumbers as $index => $objectNumber) {
            $pageIndexes[$objectNumber] = $index;
        }

        $metadata = ['source' => []];
        $pageMode = $this->nameValue($catalog['PageMode'] ?? null);
        if ($pageMode !== null) {
            $metadata['source'][] = 'page_mode';
            $metadata['page_mode'] = $pageMode;
        }

        $pageLayout = $this->nameValue($catalog['PageLayout'] ?? null);
        if ($pageLayout !== null) {
            $metadata['source'][] = 'page_layout';
            $metadata['page_layout'] = $pageLayout;
        }

        $destination = $this->catalogOpenActionDestination($catalog, $objects);
        if ($destination !== null) {
            $details = $this->destinationViewDetails(
                $destination['value'],
                $objects,
                $pageIndexes,
                $this->destinationMap($catalog, $objects),
                $destination['name']
            );
            if ($details !== null) {
                $metadata['source'][] = 'open_action';
                $metadata['open_action'] = $details;
            }
        }

        return $metadata;
    }

    /**
     * PDF page dictionaries may carry presentation transitions and additional
     * actions that viewers can run when a page opens or closes. WordPress
     * imports keep these as review-only metadata.
     *
     * @return list<array{
     *     pnum: int,
     *     page_number: int,
     *     page_object: int,
     *     page_label: string,
     *     catalog_view?: array<string, mixed>,
     *     display_duration: float|null,
     *     transition: array{
     *         style: string|null,
     *         duration: float|null,
     *         dimension: string|null,
     *         motion: string|null,
     *         direction: float|string|null,
     *         scale: float|null,
     *         opaque_background: bool|null
     *     }|null,
     *     actions: list<array<string, mixed>>
     * }>
     */
    public function getPageTransitionActionMetadata(string $pdfBytes): array
    {
        $objects = $this->parsedObjectValues($pdfBytes);
        $catalog = $this->catalogDictionary($objects);
        if ($catalog === null) {
            return [];
        }

        $pageObjectNumbers = $this->orderedPageObjectNumbers($objects);
        $pageIndexes = [];
        foreach ($pageObjectNumbers as $index => $objectNumber) {
            $pageIndexes[$objectNumber] = $index;
        }
        $destinations = $this->destinationMap($catalog, $objects);
        $pageLabels = (new PdfTextExtractor())->extractPageLabels($pdfBytes);
        $catalogView = $this->catalogReviewContext($pdfBytes);

        $pages = [];
        foreach ($pageObjectNumbers as $pnum => $pageObjectNumber) {
            $page = $this->resolveDictionary($this->refValue($pageObjectNumber), $objects);
            if ($page === null) {
                continue;
            }

            $displayDuration = $this->numericOrNullValue($this->resolveValue($page['Dur'] ?? null, $objects));
            $transition = $this->pageTransitionMetadata($page['Trans'] ?? null, $objects);
            $actions = $this->pageAdditionalActionMetadata($page['AA'] ?? null, $objects, $pageIndexes, $destinations);
            if ($displayDuration === null && $transition === null && $actions === []) {
                continue;
            }

            $row = [
                'pnum' => $pnum,
                'page_number' => $pnum + 1,
                'page_object' => $pageObjectNumber,
                'page_label' => $pageLabels[$pnum] ?? (string) ($pnum + 1),
                'display_duration' => $displayDuration,
                'transition' => $transition,
                'actions' => $actions,
            ];
            if ($catalogView !== []) {
                $row['catalog_view'] = $catalogView;
            }

            $pages[] = $row;
        }

        return $pages;
    }

    /**
     * Composite navigation review for WordPress import UIs that need the
     * pypdfium-style outline destination, catalog OpenAction, page-label, and
     * transition metadata in one non-executing payload.
     *
     * @return array{
     *     source: list<string>,
     *     outline: list<array<string, mixed>>,
     *     open_action_review_actions: list<array<string, mixed>>,
     *     outline_action_review_actions: list<array<string, mixed>>,
     *     page_presentations: list<array<string, mixed>>,
     *     open_action_destination?: array<string, mixed>
     * }
     */
    public function getNavigationReviewMetadata(string $pdfBytes): array
    {
        $objects = $this->parsedObjectValues($pdfBytes);
        $catalog = $this->catalogDictionary($objects);
        $metadata = [
            'source' => [],
            'outline' => [],
            'open_action_review_actions' => [],
            'outline_action_review_actions' => [],
            'page_presentations' => [],
        ];
        if ($catalog === null) {
            return $metadata;
        }

        $pageObjectNumbers = $this->orderedPageObjectNumbers($objects);
        if ($pageObjectNumbers === []) {
            return $metadata;
        }

        $pageIndexes = [];
        foreach ($pageObjectNumbers as $index => $objectNumber) {
            $pageIndexes[$objectNumber] = $index;
        }

        $destinations = $this->destinationMap($catalog, $objects);
        $pageLabels = (new PdfTextExtractor())->extractPageLabels($pdfBytes);
        $pagePresentations = $this->getPageTransitionActionMetadata($pdfBytes);
        $pagePresentationsByPage = $this->pagePresentationsByPageIndex($pagePresentations);

        $outlineRoot = $this->resolveDictionary($catalog['Outlines'] ?? null, $objects);
        if ($outlineRoot !== null) {
            foreach ($this->outlineItemsWithDestinationViews($outlineRoot['First'] ?? null, $objects, $pageIndexes, $destinations, 15) as $item) {
                $item['page_label'] = $this->pageLabelForIndex($item['page'], $pageLabels);
                $item = $this->withTargetPagePresentation($item, $pagePresentationsByPage);
                $metadata['outline'][] = $item;
            }
            if ($metadata['outline'] !== []) {
                $metadata['source'][] = 'outline';
            }

            $outlineActionReviews = $this->outlineActionReviewRows(
                $outlineRoot['First'] ?? null,
                $objects,
                $pageIndexes,
                $destinations,
                $pageLabels,
                $pagePresentationsByPage,
                15
            );
            if ($outlineActionReviews !== []) {
                $metadata['source'][] = 'outline_actions';
                $metadata['outline_action_review_actions'] = $outlineActionReviews;
            }
        }

        if (array_key_exists('OpenAction', $catalog)) {
            $openActionReviews = $this->getOpenActionReviewActions($pdfBytes);
            if ($openActionReviews !== []) {
                $metadata['source'][] = 'open_action';
                $metadata['open_action_review_actions'] = $openActionReviews;
            }
        }

        if ($pagePresentations !== []) {
            $metadata['source'][] = 'page_presentations';
            $metadata['page_presentations'] = $pagePresentations;
        }

        $openActionDestination = $this->catalogOpenActionDestination($catalog, $objects);
        if ($openActionDestination !== null) {
            $details = $this->destinationViewDetails(
                $openActionDestination['value'],
                $objects,
                $pageIndexes,
                $destinations,
                $openActionDestination['name']
            );
            if ($details !== null) {
                $details['page_label'] = $this->pageLabelForIndex($details['page'], $pageLabels);
                $details = $this->withTargetPagePresentation($details, $pagePresentationsByPage);
                $metadata['open_action_destination'] = $details;
            }
        }

        return $metadata;
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogReviewContext(string $pdfBytes): array
    {
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdfBytes);
        $context = [];
        foreach (['page_layout', 'page_mode'] as $field) {
            if (is_string($metadata[$field] ?? null) && $metadata[$field] !== '') {
                $context[$field] = $metadata[$field];
            }
        }

        if (isset($metadata['viewer_preferences']) && is_array($metadata['viewer_preferences']) && $metadata['viewer_preferences'] !== []) {
            $context['viewer_preferences'] = $metadata['viewer_preferences'];
        }

        return $context;
    }

    /**
     * @param list<string> $pageLabels
     */
    private function pageLabelForIndex(int $pageIndex, array $pageLabels): string
    {
        return $pageLabels[$pageIndex] ?? (string) ($pageIndex + 1);
    }

    /**
     * @param list<array<string, mixed>> $pagePresentations
     * @return array<int, array<string, mixed>>
     */
    private function pagePresentationsByPageIndex(array $pagePresentations): array
    {
        $indexed = [];
        foreach ($pagePresentations as $pagePresentation) {
            $pageIndex = $pagePresentation['pnum'] ?? null;
            if (is_int($pageIndex)) {
                $indexed[$pageIndex] = $pagePresentation;
            }
        }

        return $indexed;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<int, array<string, mixed>> $pagePresentationsByPage
     * @return array<string, mixed>
     */
    private function withTargetPagePresentation(array $item, array $pagePresentationsByPage): array
    {
        $pageIndex = $item['page'] ?? null;
        if (!is_int($pageIndex) || !array_key_exists($pageIndex, $pagePresentationsByPage)) {
            return $item;
        }

        $pagePresentation = $pagePresentationsByPage[$pageIndex];
        $item['target_display_duration'] = $pagePresentation['display_duration'] ?? null;
        $item['target_page_transition'] = $pagePresentation['transition'] ?? null;
        $item['target_page_actions'] = is_array($pagePresentation['actions'] ?? null)
            ? $pagePresentation['actions']
            : [];

        return $item;
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @param array<string, mixed> $destinations
     * @param list<string> $pageLabels
     * @param array<int, array<string, mixed>> $pagePresentationsByPage
     * @param array<int, true> $seen
     * @return list<array<string, mixed>>
     */
    private function outlineActionReviewRows(
        mixed $firstItem,
        array $objects,
        array $pageIndexes,
        array $destinations,
        array $pageLabels,
        array $pagePresentationsByPage,
        int $maxDepth,
        int $level = 1,
        array $seen = []
    ): array {
        if ($level > $maxDepth) {
            return [];
        }

        $items = [];
        $current = $this->referenceObjectNumber($firstItem);
        while ($current !== null && !isset($seen[$current])) {
            $seen[$current] = true;
            $dict = $this->resolveDictionary($this->refValue($current), $objects);
            if ($dict === null) {
                break;
            }

            $title = $this->stringOrNameValue($this->resolveValue($dict['Title'] ?? null, $objects));
            if ($title !== null && array_key_exists('A', $dict)) {
                $seenActions = [];
                $actions = $this->reviewActionsFromValue($dict['A'], $objects, $pageIndexes, $destinations, $seenActions);
                if ($this->shouldSurfaceOutlineActionRows($actions)) {
                    foreach ($actions as $action) {
                        $row = [
                            'outline_title' => $title,
                            'outline_level' => $level,
                            'outline_object' => $current,
                        ] + $action;

                        $page = $row['page'] ?? null;
                        if (is_int($page)) {
                            $row['page_label'] = $this->pageLabelForIndex($page, $pageLabels);
                            $row = $this->withTargetPagePresentation($row, $pagePresentationsByPage);
                        }

                        $items[] = $row;
                    }
                }
            } elseif ($title !== null) {
                $destination = $this->outlineDestination($dict, $objects);
                $destinationAction = $this->destinationActionReviewValue($destination['value'], $objects, $destinations, $destination['name']);
                if ($destinationAction !== null) {
                    $seenActions = [];
                    $actions = $this->reviewActionsFromValue($destinationAction['value'], $objects, $pageIndexes, $destinations, $seenActions);
                    if ($this->shouldSurfaceOutlineActionRows($actions)) {
                        foreach ($actions as $action) {
                            if (
                                ($action['action_type'] ?? null) === 'GoTo'
                                && ($action['safety'] ?? null) === 'local-destination'
                                && ($action['destination'] ?? null) === null
                                && $destinationAction['destination_name'] !== null
                            ) {
                                $action['destination'] = $destinationAction['destination_name'];
                            }

                            $row = [
                                'outline_title' => $title,
                                'outline_level' => $level,
                                'outline_object' => $current,
                            ] + $action;

                            $page = $row['page'] ?? null;
                            if (is_int($page)) {
                                $row['page_label'] = $this->pageLabelForIndex($page, $pageLabels);
                                $row = $this->withTargetPagePresentation($row, $pagePresentationsByPage);
                            }

                            $items[] = $row;
                        }
                    }
                }
            }

            if ($level < $maxDepth) {
                foreach ($this->outlineActionReviewRows(
                    $dict['First'] ?? null,
                    $objects,
                    $pageIndexes,
                    $destinations,
                    $pageLabels,
                    $pagePresentationsByPage,
                    $maxDepth,
                    $level + 1,
                    $seen
                ) as $child) {
                    $items[] = $child;
                }
            }

            $current = $this->referenceObjectNumber($dict['Next'] ?? null);
        }

        return $items;
    }

    /**
     * Plain local GoTo outline actions are already represented by outline
     * destination rows; chained or non-local actions need a review-only row.
     *
     * @param list<array<string, mixed>> $actions
     */
    private function shouldSurfaceOutlineActionRows(array $actions): bool
    {
        foreach ($actions as $action) {
            if (($action['chained'] ?? false) === true) {
                return true;
            }

            if (($action['action_type'] ?? null) !== 'GoTo' || ($action['safety'] ?? null) !== 'local-destination') {
                return true;
            }
        }

        return false;
    }

    /**
     * Some PDF producers place a GoTo action dictionary behind a name-tree
     * destination. PDF engines still resolve the `/D` target for outlines, but
     * WordPress import review must not drop any `/Next` followups attached to
     * that action dictionary.
     *
     * @param array<int, mixed> $objects
     * @param array<string, mixed> $destinations
     * @param array<string, true> $seenNames
     * @return array{value: mixed, destination_name: string|null}|null
     */
    private function destinationActionReviewValue(
        mixed $destination,
        array $objects,
        array $destinations,
        ?string $destinationName = null,
        array $seenNames = []
    ): ?array {
        $resolved = $this->resolveValue($destination, $objects);
        $name = $this->stringOrNameValue($resolved);
        if ($name !== null) {
            if (isset($seenNames[$name]) || !array_key_exists($name, $destinations)) {
                return null;
            }
            $seenNames[$name] = true;

            return $this->destinationActionReviewValue($destinations[$name], $objects, $destinations, $name, $seenNames);
        }

        $dict = $this->dictionaryItems($resolved);
        if ($dict === null) {
            return null;
        }

        if (array_key_exists('S', $dict) || array_key_exists('Next', $dict)) {
            return [
                'value' => $destination,
                'destination_name' => $destinationName,
            ];
        }

        return null;
    }

    /**
     * @return array<int, mixed>
     */
    private function parsedObjectValues(string $pdfBytes): array
    {
        $values = [];
        if (!preg_match_all('/(\d+)\s+\d+\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER)) {
            return $values;
        }

        foreach ($matches as $match) {
            $tokens = $this->tokens(trim($match[2]));
            if ($tokens === []) {
                continue;
            }

            $index = 0;
            $values[(int) $match[1]] = $this->parseValue($tokens, $index);
        }

        return $values;
    }

    /**
     * @param array<int, mixed> $objects
     * @return array<string, mixed>|null
     */
    private function catalogDictionary(array $objects): ?array
    {
        foreach ($objects as $value) {
            $dict = $this->dictionaryItems($value);
            if ($dict !== null && $this->nameValue($dict['Type'] ?? null) === 'Catalog') {
                return $dict;
            }
        }

        return null;
    }

    /**
     * @param array<int, mixed> $objects
     * @return list<int>
     */
    private function orderedPageObjectNumbers(array $objects): array
    {
        $catalog = $this->catalogDictionary($objects);
        if ($catalog !== null) {
            $pagesRoot = $this->referenceObjectNumber($catalog['Pages'] ?? null);
            if ($pagesRoot !== null) {
                $pages = $this->pageObjectNumbersFromTree($pagesRoot, $objects);
                if ($pages !== []) {
                    return $pages;
                }
            }
        }

        $pages = [];
        foreach ($objects as $objectNumber => $value) {
            $dict = $this->dictionaryItems($value);
            if ($dict !== null && $this->nameValue($dict['Type'] ?? null) === 'Page') {
                $pages[] = $objectNumber;
            }
        }

        return $pages;
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, true> $seen
     * @return list<int>
     */
    private function pageObjectNumbersFromTree(int $objectNumber, array $objects, array $seen = []): array
    {
        if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
            return [];
        }
        $seen[$objectNumber] = true;

        $dict = $this->resolveDictionary($this->refValue($objectNumber), $objects);
        if ($dict === null) {
            return [];
        }

        $type = $this->nameValue($dict['Type'] ?? null);
        if ($type === 'Page') {
            return [$objectNumber];
        }

        $kids = $this->resolveArray($dict['Kids'] ?? null, $objects);
        if ($kids === null) {
            return [];
        }

        $pages = [];
        foreach ($kids as $kid) {
            $kidObjectNumber = $this->referenceObjectNumber($kid);
            if ($kidObjectNumber === null) {
                continue;
            }

            foreach ($this->pageObjectNumbersFromTree($kidObjectNumber, $objects, $seen) as $pageObjectNumber) {
                $pages[] = $pageObjectNumber;
            }
        }

        return $pages;
    }

    /**
     * @param array<string, mixed> $catalog
     * @param array<int, mixed> $objects
     * @return array<string, mixed>
     */
    private function destinationMap(array $catalog, array $objects): array
    {
        $destinations = [];

        $legacyDests = $this->resolveDictionary($catalog['Dests'] ?? null, $objects);
        if ($legacyDests !== null) {
            foreach ($legacyDests as $name => $destination) {
                $destinations[$name] = $destination;
            }
        }

        $names = $this->resolveDictionary($catalog['Names'] ?? null, $objects);
        $nameTreeRoot = $names === null ? null : $this->resolveDictionary($names['Dests'] ?? null, $objects);
        if ($nameTreeRoot !== null) {
            $this->collectNameTreeDestinations($nameTreeRoot, $objects, $destinations);
        }

        return $destinations;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, mixed> $objects
     * @param array<string, mixed> $destinations
     * @param array<int, true> $seen
     */
    private function collectNameTreeDestinations(array $node, array $objects, array &$destinations, array $seen = []): void
    {
        $names = $this->resolveArray($node['Names'] ?? null, $objects);
        if ($names !== null) {
            for ($index = 0, $count = count($names); $index + 1 < $count; $index += 2) {
                $name = $this->destinationNameValue($names[$index], $objects);
                if ($name !== null) {
                    $destinations[$name] = $names[$index + 1];
                }
            }
        }

        $kids = $this->resolveArray($node['Kids'] ?? null, $objects);
        if ($kids === null) {
            return;
        }

        foreach ($kids as $kid) {
            $objectNumber = $this->referenceObjectNumber($kid);
            if ($objectNumber !== null) {
                if (isset($seen[$objectNumber])) {
                    continue;
                }
                $seen[$objectNumber] = true;
            }

            $child = $this->resolveDictionary($kid, $objects);
            if ($child !== null) {
                $this->collectNameTreeDestinations($child, $objects, $destinations, $seen);
            }
        }
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @param array<string, mixed> $destinations
     * @param array<int, true> $seen
     * @return list<array{title: string, level: int, page: int, destination: string|null}>
     */
    private function outlineItems(
        mixed $firstItem,
        array $objects,
        array $pageIndexes,
        array $destinations,
        int $maxDepth,
        int $level = 1,
        array $seen = []
    ): array {
        if ($level > $maxDepth) {
            return [];
        }

        $items = [];
        $current = $this->referenceObjectNumber($firstItem);
        while ($current !== null && !isset($seen[$current])) {
            $seen[$current] = true;
            $dict = $this->resolveDictionary($this->refValue($current), $objects);
            if ($dict === null) {
                break;
            }

            $title = $this->stringOrNameValue($this->resolveValue($dict['Title'] ?? null, $objects));
            $destination = $this->outlineDestination($dict, $objects);
            $page = $this->destinationPageIndex($destination['value'], $objects, $pageIndexes, $destinations);
            if ($title !== null && $page !== null) {
                $items[] = [
                    'title' => $title,
                    'level' => $level,
                    'page' => $page,
                    'destination' => $destination['name'],
                ];
            }

            if ($level < $maxDepth) {
                foreach ($this->outlineItems($dict['First'] ?? null, $objects, $pageIndexes, $destinations, $maxDepth, $level + 1, $seen) as $child) {
                    $items[] = $child;
                }
            }

            $current = $this->referenceObjectNumber($dict['Next'] ?? null);
        }

        return $items;
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @param array<string, mixed> $destinations
     * @param array<int, true> $seen
     * @return list<array{
     *     title: string,
     *     level: int,
     *     page: int,
     *     destination: string|null,
     *     view_mode: string|null,
     *     view_position: list<float|null>,
     *     view_parameters: array<string, float|null>
     * }>
     */
    private function outlineItemsWithDestinationViews(
        mixed $firstItem,
        array $objects,
        array $pageIndexes,
        array $destinations,
        int $maxDepth,
        int $level = 1,
        array $seen = []
    ): array {
        if ($level > $maxDepth) {
            return [];
        }

        $items = [];
        $current = $this->referenceObjectNumber($firstItem);
        while ($current !== null && !isset($seen[$current])) {
            $seen[$current] = true;
            $dict = $this->resolveDictionary($this->refValue($current), $objects);
            if ($dict === null) {
                break;
            }

            $title = $this->stringOrNameValue($this->resolveValue($dict['Title'] ?? null, $objects));
            $destination = $this->outlineDestination($dict, $objects);
            $details = $this->destinationViewDetails(
                $destination['value'],
                $objects,
                $pageIndexes,
                $destinations,
                $destination['name']
            );
            if ($title !== null && $details !== null) {
                $items[] = [
                    'title' => $title,
                    'level' => $level,
                    'page' => $details['page'],
                    'destination' => $details['destination'],
                    'view_mode' => $details['view_mode'],
                    'view_position' => $details['view_position'],
                    'view_parameters' => $details['view_parameters'],
                ];
            }

            if ($level < $maxDepth) {
                foreach ($this->outlineItemsWithDestinationViews($dict['First'] ?? null, $objects, $pageIndexes, $destinations, $maxDepth, $level + 1, $seen) as $child) {
                    $items[] = $child;
                }
            }

            $current = $this->referenceObjectNumber($dict['Next'] ?? null);
        }

        return $items;
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<string, mixed> $destinations
     * @param array<int, true> $seen
     * @return list<array{title: string, level: int, file: string, destination: string|null, page: int|null, new_window: bool|null}>
     */
    private function remoteGoToOutlineItems(
        mixed $firstItem,
        array $objects,
        array $destinations,
        int $maxDepth,
        int $level = 1,
        array $seen = []
    ): array {
        if ($level > $maxDepth) {
            return [];
        }

        $items = [];
        $current = $this->referenceObjectNumber($firstItem);
        while ($current !== null && !isset($seen[$current])) {
            $seen[$current] = true;
            $dict = $this->resolveDictionary($this->refValue($current), $objects);
            if ($dict === null) {
                break;
            }

            $title = $this->stringOrNameValue($this->resolveValue($dict['Title'] ?? null, $objects));
            $target = $this->remoteGoToActionTarget($dict, $objects, $destinations);
            if ($title !== null && $target !== null) {
                $items[] = [
                    'title' => $title,
                    'level' => $level,
                    'file' => $target['file'],
                    'destination' => $target['destination'],
                    'page' => $target['page'],
                    'new_window' => $target['new_window'],
                ];
            }

            if ($level < $maxDepth) {
                foreach ($this->remoteGoToOutlineItems($dict['First'] ?? null, $objects, $destinations, $maxDepth, $level + 1, $seen) as $child) {
                    $items[] = $child;
                }
            }

            $current = $this->referenceObjectNumber($dict['Next'] ?? null);
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $outline
     * @param array<int, mixed> $objects
     * @param array<string, mixed> $destinations
     * @return array{file: string, destination: string|null, page: int|null, new_window: bool|null}|null
     */
    private function remoteGoToActionTarget(array $outline, array $objects, array $destinations): ?array
    {
        $action = $this->resolveDictionary($outline['A'] ?? null, $objects);
        if ($action !== null && $this->nameValue($action['S'] ?? null) === 'GoToR') {
            return $this->remoteGoToTargetFromAction($action, $objects);
        }

        if (array_key_exists('Dest', $outline)) {
            return $this->remoteGoToTargetFromDestination($outline['Dest'], $objects, $destinations);
        }

        return null;
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<string, mixed> $destinations
     * @param array<string, true> $seenNames
     * @return array{file: string, destination: string|null, page: int|null, new_window: bool|null}|null
     */
    private function remoteGoToTargetFromDestination(
        mixed $destination,
        array $objects,
        array $destinations,
        array $seenNames = []
    ): ?array {
        $resolved = $this->resolveValue($destination, $objects);
        $name = $this->stringOrNameValue($resolved);
        if ($name !== null) {
            if (isset($seenNames[$name]) || !array_key_exists($name, $destinations)) {
                return null;
            }
            $seenNames[$name] = true;

            return $this->remoteGoToTargetFromDestination($destinations[$name], $objects, $destinations, $seenNames);
        }

        $dict = $this->dictionaryItems($resolved);
        if ($dict === null) {
            return null;
        }

        $type = $this->nameValue($dict['S'] ?? null);
        if ($type === 'GoToR') {
            return $this->remoteGoToTargetFromAction($dict, $objects);
        }

        if ($type === null && array_key_exists('D', $dict)) {
            return $this->remoteGoToTargetFromDestination($dict['D'], $objects, $destinations, $seenNames);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $action
     * @param array<int, mixed> $objects
     * @return array{file: string, destination: string|null, page: int|null, new_window: bool|null}|null
     */
    private function remoteGoToTargetFromAction(array $action, array $objects): ?array
    {
        $file = $this->fileSpecValue($action['F'] ?? null, $objects);
        if ($file === null || !array_key_exists('D', $action)) {
            return null;
        }

        $destination = $this->remoteDestinationValue($action['D'], $objects);
        if ($destination === null) {
            return null;
        }

        return [
            'file' => $file,
            'destination' => $destination['destination'],
            'page' => $destination['page'],
            'new_window' => is_bool($action['NewWindow'] ?? null) ? $action['NewWindow'] : null,
        ];
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @param array<string, mixed> $destinations
     * @return array{action_type: string, safety: string, page: int|null, destination: string|null, uri: string|null, file: string|null, operation: string|null, new_window: bool|null, is_safe_uri: bool|null, executes_on_import: bool}|null
     */
    private function openActionReviewAction(mixed $value, array $objects, array $pageIndexes, array $destinations): ?array
    {
        $resolved = $this->resolveValue($value, $objects);
        $action = $this->dictionaryItems($resolved);
        if ($action === null || !array_key_exists('S', $action)) {
            return $this->localOpenDestinationReview($value, $objects, $pageIndexes, $destinations);
        }

        $type = $this->nameValue($action['S'] ?? null);
        if ($type === 'GoTo' && array_key_exists('D', $action)) {
            return $this->localOpenDestinationReview($action['D'], $objects, $pageIndexes, $destinations);
        }

        if ($type === 'GoToR') {
            $target = $this->remoteGoToTargetFromAction($action, $objects);
            if ($target === null) {
                return null;
            }

            return $this->reviewAction(
                'GoToR',
                'remote-document-review',
                $target['page'],
                $target['destination'],
                null,
                $target['file'],
                null,
                $target['new_window'],
                null
            );
        }

        if ($type === 'URI') {
            $uri = $this->stringOrNameValue($this->resolveValue($action['URI'] ?? null, $objects));
            if ($uri === null || trim($uri) === '') {
                return null;
            }

            $isSafeUri = $this->isSafeUri($uri);

            return $this->reviewAction(
                'URI',
                $isSafeUri ? 'review-uri' : 'blocked-unsafe-uri',
                null,
                null,
                $uri,
                null,
                null,
                null,
                $isSafeUri
            );
        }

        if ($type === 'Launch') {
            $file = $this->fileSpecValue($action['F'] ?? null, $objects);
            $win = $this->resolveDictionary($action['Win'] ?? null, $objects);
            if ($file === null && $win !== null) {
                $file = $this->fileSpecValue($win['F'] ?? null, $objects);
            }
            if ($file === null || trim($file) === '') {
                return null;
            }

            $operation = $win === null ? null : $this->stringOrNameValue($this->resolveValue($win['O'] ?? null, $objects));

            return $this->reviewAction(
                'Launch',
                'blocked-launch',
                null,
                null,
                null,
                $file,
                $operation,
                is_bool($action['NewWindow'] ?? null) ? $action['NewWindow'] : null,
                null
            );
        }

        return null;
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @param array<string, mixed> $destinations
     * @return array{action_type: string, safety: string, page: int|null, destination: string|null, uri: string|null, file: string|null, operation: string|null, new_window: bool|null, is_safe_uri: bool|null, executes_on_import: bool}|null
     */
    private function localOpenDestinationReview(mixed $destination, array $objects, array $pageIndexes, array $destinations): ?array
    {
        $page = $this->destinationPageIndex($destination, $objects, $pageIndexes, $destinations);
        if ($page === null) {
            return null;
        }

        return $this->reviewAction(
            'GoTo',
            'local-destination',
            $page,
            $this->stringOrNameValue($this->resolveValue($destination, $objects)),
            null,
            null,
            null,
            null,
            null
        );
    }

    /**
     * @return array{action_type: string, safety: string, page: int|null, destination: string|null, uri: string|null, file: string|null, operation: string|null, new_window: bool|null, is_safe_uri: bool|null, executes_on_import: bool}
     */
    private function reviewAction(
        string $type,
        string $safety,
        ?int $page,
        ?string $destination,
        ?string $uri,
        ?string $file,
        ?string $operation,
        ?bool $newWindow,
        ?bool $isSafeUri
    ): array {
        return [
            'action_type' => $type,
            'safety' => $safety,
            'page' => $page,
            'destination' => $destination,
            'uri' => $uri,
            'file' => $file,
            'operation' => $operation,
            'new_window' => $newWindow,
            'is_safe_uri' => $isSafeUri,
            'executes_on_import' => false,
        ];
    }

    /**
     * @param array<int, mixed> $objects
     * @return array{
     *     style: string|null,
     *     duration: float|null,
     *     dimension: string|null,
     *     motion: string|null,
     *     direction: float|string|null,
     *     scale: float|null,
     *     opaque_background: bool|null
     * }|null
     */
    private function pageTransitionMetadata(mixed $value, array $objects): ?array
    {
        $transition = $this->resolveDictionary($value, $objects);
        if ($transition === null) {
            return null;
        }
        $opaqueBackground = $this->resolveValue($transition['B'] ?? null, $objects);

        return [
            'style' => $this->nameValue($this->resolveValue($transition['S'] ?? null, $objects)),
            'duration' => $this->numericOrNullValue($this->resolveValue($transition['D'] ?? null, $objects)),
            'dimension' => $this->nameValue($this->resolveValue($transition['Dm'] ?? null, $objects)),
            'motion' => $this->nameValue($this->resolveValue($transition['M'] ?? null, $objects)),
            'direction' => $this->directionValue($this->resolveValue($transition['Di'] ?? null, $objects)),
            'scale' => $this->numericOrNullValue($this->resolveValue($transition['SS'] ?? null, $objects)),
            'opaque_background' => is_bool($opaqueBackground) ? $opaqueBackground : null,
        ];
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @param array<string, mixed> $destinations
     * @return list<array<string, mixed>>
     */
    private function pageAdditionalActionMetadata(mixed $value, array $objects, array $pageIndexes, array $destinations): array
    {
        $additionalActions = $this->resolveDictionary($value, $objects);
        if ($additionalActions === null) {
            return [];
        }

        $actions = [];
        foreach ($additionalActions as $event => $actionValue) {
            $seen = [];
            foreach ($this->reviewActionsFromValue($actionValue, $objects, $pageIndexes, $destinations, $seen) as $action) {
                $actions[] = [
                    'event' => $event,
                    'event_label' => self::PAGE_ACTION_EVENT_LABELS[$event] ?? 'page_additional_action',
                ] + $action;
            }
        }

        return $actions;
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @param array<string, mixed> $destinations
     * @param array<string, true> $seen
     * @return list<array<string, mixed>>
     */
    private function reviewActionsFromValue(
        mixed $value,
        array $objects,
        array $pageIndexes,
        array $destinations,
        array &$seen,
        int $depth = 0
    ): array {
        if ($value === null || $depth > 20) {
            return [];
        }

        $resolved = $this->resolveValue($value, $objects);
        $array = $this->arrayItems($resolved);
        if ($array !== null) {
            $actions = [];
            foreach ($array as $item) {
                foreach ($this->reviewActionsFromValue($item, $objects, $pageIndexes, $destinations, $seen, $depth + 1) as $action) {
                    $actions[] = $action;
                }
            }

            return $actions;
        }

        $dict = $this->dictionaryItems($resolved);
        if ($dict === null) {
            return [];
        }

        $actionObject = $this->referenceObjectNumber($value);
        $identity = $actionObject === null ? 'dict:' . md5(serialize($dict)) : 'obj:' . $actionObject;
        if (isset($seen[$identity])) {
            return [];
        }
        $seen[$identity] = true;

        $type = $this->nameValue($dict['S'] ?? null);
        $action = $type === 'JavaScript'
            ? $this->reviewAction('JavaScript', 'blocked-javascript', null, null, null, null, null, null, null)
            : $this->openActionReviewAction($value, $objects, $pageIndexes, $destinations);

        if ($action === null && $type !== null) {
            $action = $this->reviewAction($type, 'unsupported-action-review', null, null, null, null, null, null, null);
        }

        $actions = [];
        if ($action !== null) {
            if ($actionObject !== null) {
                $action['action_object'] = $actionObject;
            }
            $actions[] = $action;
        }

        if (array_key_exists('Next', $dict)) {
            foreach ($this->reviewActionsFromValue($dict['Next'], $objects, $pageIndexes, $destinations, $seen, $depth + 1) as $nextAction) {
                $nextAction['chained'] = true;
                $actions[] = $nextAction;
            }
        }

        return $actions;
    }

    private function directionValue(mixed $value): float|string|null
    {
        $name = $this->nameValue($value);
        if ($name !== null) {
            return $name;
        }

        return $this->numericOrNullValue($value);
    }

    /**
     * @param array<int, mixed> $objects
     */
    private function fileSpecValue(mixed $value, array $objects): ?string
    {
        $resolved = $this->resolveValue($value, $objects);
        $file = $this->stringOrNameValue($resolved);
        if ($file !== null && $file !== '') {
            return $file;
        }

        $dict = $this->dictionaryItems($resolved);
        if ($dict === null) {
            return null;
        }

        foreach (['UF', 'F', 'DOS', 'Unix', 'Mac'] as $key) {
            $file = $this->stringOrNameValue($this->resolveValue($dict[$key] ?? null, $objects));
            if ($file !== null && $file !== '') {
                return $file;
            }
        }

        return null;
    }

    /**
     * @param array<int, mixed> $objects
     * @return array{destination: string|null, page: int|null}|null
     */
    private function remoteDestinationValue(mixed $value, array $objects): ?array
    {
        $resolved = $this->resolveValue($value, $objects);
        $name = $this->stringOrNameValue($resolved);
        if ($name !== null) {
            return ['destination' => $name, 'page' => null];
        }

        $dict = $this->dictionaryItems($resolved);
        if ($dict !== null && array_key_exists('D', $dict)) {
            return $this->remoteDestinationValue($dict['D'], $objects);
        }

        $array = $this->arrayItems($resolved);
        if ($array === null || $array === []) {
            return null;
        }

        $first = $this->resolveValue($array[0], $objects);
        if (is_int($first) && $first >= 0) {
            return ['destination' => null, 'page' => $first];
        }

        $name = $this->stringOrNameValue($first);
        if ($name !== null) {
            return ['destination' => $name, 'page' => null];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $outline
     * @param array<int, mixed> $objects
     * @return array{name: string|null, value: mixed}
     */
    private function outlineDestination(array $outline, array $objects): array
    {
        if (array_key_exists('Dest', $outline)) {
            return [
                'name' => $this->destinationNameValue($outline['Dest'], $objects),
                'value' => $outline['Dest'],
            ];
        }

        $action = $this->resolveDictionary($outline['A'] ?? null, $objects);
        if ($action === null || $this->nameValue($action['S'] ?? null) !== 'GoTo' || !array_key_exists('D', $action)) {
            return ['name' => null, 'value' => null];
        }

        return [
            'name' => $this->destinationNameValue($action['D'], $objects),
            'value' => $action['D'],
        ];
    }

    /**
     * @param array<int, mixed> $objects
     */
    private function destinationNameValue(mixed $value, array $objects): ?string
    {
        return $this->stringOrNameValue($this->resolveValue($value, $objects));
    }

    /**
     * @param array<string, mixed> $catalog
     * @param array<int, mixed> $objects
     * @return array{name: string|null, value: mixed}|null
     */
    private function catalogOpenActionDestination(array $catalog, array $objects): ?array
    {
        if (!array_key_exists('OpenAction', $catalog)) {
            return null;
        }

        $openAction = $catalog['OpenAction'];
        $resolved = $this->resolveValue($openAction, $objects);
        $array = $this->arrayItems($resolved);
        if ($array !== null) {
            return [
                'name' => $this->stringOrNameValue($openAction),
                'value' => $openAction,
            ];
        }

        $name = $this->stringOrNameValue($resolved);
        if ($name !== null) {
            return ['name' => $name, 'value' => $openAction];
        }

        $action = $this->dictionaryItems($resolved);
        if ($action === null || $this->nameValue($action['S'] ?? null) !== 'GoTo' || !array_key_exists('D', $action)) {
            return null;
        }

        return [
            'name' => $this->stringOrNameValue($action['D']),
            'value' => $action['D'],
        ];
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @param array<string, mixed> $destinations
     * @param array<string, true> $seenNames
     * @return array{
     *     page: int,
     *     destination: string|null,
     *     view_mode: string|null,
     *     view_position: list<float|null>,
     *     view_parameters: array<string, float|null>
     * }|null
     */
    private function destinationViewDetails(
        mixed $destination,
        array $objects,
        array $pageIndexes,
        array $destinations,
        ?string $destinationName = null,
        array $seenNames = []
    ): ?array {
        $pageObjectNumber = $this->referenceObjectNumber($destination);
        if ($pageObjectNumber !== null && isset($pageIndexes[$pageObjectNumber])) {
            return [
                'page' => $pageIndexes[$pageObjectNumber],
                'destination' => $destinationName,
                'view_mode' => null,
                'view_position' => [],
                'view_parameters' => [],
            ];
        }

        $resolved = $this->resolveValue($destination, $objects);
        $name = $this->stringOrNameValue($resolved);
        if ($name !== null) {
            if (isset($seenNames[$name]) || !array_key_exists($name, $destinations)) {
                return null;
            }
            $seenNames[$name] = true;

            return $this->destinationViewDetails($destinations[$name], $objects, $pageIndexes, $destinations, $name, $seenNames);
        }

        $dict = $this->dictionaryItems($resolved);
        if ($dict !== null) {
            $localDestination = $this->localDestinationDictionaryValue($dict);
            if ($localDestination !== null) {
                return $this->destinationViewDetails($localDestination['value'], $objects, $pageIndexes, $destinations, $destinationName, $seenNames);
            }
        }

        $array = $this->arrayItems($resolved);
        if ($array !== null && $array !== []) {
            return $this->explicitDestinationDetails($array, $objects, $pageIndexes, $destinationName);
        }

        $pageObjectNumber = $this->referenceObjectNumber($resolved);
        if ($pageObjectNumber !== null && isset($pageIndexes[$pageObjectNumber])) {
            return [
                'page' => $pageIndexes[$pageObjectNumber],
                'destination' => $destinationName,
                'view_mode' => null,
                'view_position' => [],
                'view_parameters' => [],
            ];
        }

        if (is_int($resolved) && $resolved >= 0) {
            return [
                'page' => $resolved,
                'destination' => $destinationName,
                'view_mode' => null,
                'view_position' => [],
                'view_parameters' => [],
            ];
        }

        return null;
    }

    /**
     * @param list<mixed> $array
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @return array{
     *     page: int,
     *     destination: string|null,
     *     view_mode: string|null,
     *     view_position: list<float|null>,
     *     view_parameters: array<string, float|null>
     * }|null
     */
    private function explicitDestinationDetails(array $array, array $objects, array $pageIndexes, ?string $destinationName): ?array
    {
        $page = $this->destinationPageFromValue($array[0] ?? null, $objects, $pageIndexes);
        if ($page === null) {
            return null;
        }

        $viewMode = $this->nameValue($this->resolveValue($array[1] ?? null, $objects));
        $viewPosition = [];
        for ($index = 2, $count = count($array); $index < $count; $index++) {
            $viewPosition[] = $this->numericOrNullValue($this->resolveValue($array[$index], $objects));
        }
        $viewPosition = $this->normalizedViewPosition($viewMode, $viewPosition);

        if ($viewMode === 'XYZ' && array_key_exists(2, $viewPosition) && $viewPosition[2] === 0.0) {
            $viewPosition[2] = null;
        }

        return [
            'page' => $page,
            'destination' => $destinationName,
            'view_mode' => $viewMode,
            'view_position' => $viewPosition,
            'view_parameters' => $this->viewParameters($viewMode, $viewPosition),
        ];
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     */
    private function destinationPageFromValue(mixed $value, array $objects, array $pageIndexes): ?int
    {
        $pageObjectNumber = $this->referenceObjectNumber($value);
        if ($pageObjectNumber !== null) {
            return $pageIndexes[$pageObjectNumber] ?? null;
        }

        $resolved = $this->resolveValue($value, $objects);
        $pageObjectNumber = $this->referenceObjectNumber($resolved);
        if ($pageObjectNumber !== null) {
            return $pageIndexes[$pageObjectNumber] ?? null;
        }

        return is_int($resolved) && $resolved >= 0 ? $resolved : null;
    }

    /**
     * @param list<float|null> $viewPosition
     * @return list<float|null>
     */
    private function normalizedViewPosition(?string $viewMode, array $viewPosition): array
    {
        $expectedCount = match ($viewMode) {
            'Fit', 'FitB' => 0,
            'FitH', 'FitBH', 'FitV', 'FitBV' => 1,
            'FitR' => 4,
            'XYZ' => 3,
            default => null,
        };

        return $expectedCount === null ? $viewPosition : array_slice($viewPosition, 0, $expectedCount);
    }

    private function numericOrNullValue(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        return is_int($value) || is_float($value) ? (float) $value : null;
    }

    /**
     * @param list<float|null> $viewPosition
     * @return array<string, float|null>
     */
    private function viewParameters(?string $viewMode, array $viewPosition): array
    {
        $names = match ($viewMode) {
            'XYZ' => ['left', 'top', 'zoom'],
            'FitH', 'FitBH' => ['top'],
            'FitV', 'FitBV' => ['left'],
            'FitR' => ['left', 'bottom', 'right', 'top'],
            default => [],
        };

        $parameters = [];
        foreach ($names as $index => $name) {
            $parameters[$name] = $viewPosition[$index] ?? null;
        }

        return $parameters;
    }

    /**
     * @param array<int, mixed> $objects
     * @param array<int, int> $pageIndexes
     * @param array<string, mixed> $destinations
     * @param array<string, true> $seenNames
     */
    private function destinationPageIndex(
        mixed $destination,
        array $objects,
        array $pageIndexes,
        array $destinations,
        array $seenNames = []
    ): ?int {
        $pageObjectNumber = $this->referenceObjectNumber($destination);
        if ($pageObjectNumber !== null && isset($pageIndexes[$pageObjectNumber])) {
            return $pageIndexes[$pageObjectNumber];
        }

        $resolved = $this->resolveValue($destination, $objects);
        $name = $this->stringOrNameValue($resolved);
        if ($name !== null) {
            if (isset($seenNames[$name]) || !array_key_exists($name, $destinations)) {
                return null;
            }
            $seenNames[$name] = true;

            return $this->destinationPageIndex($destinations[$name], $objects, $pageIndexes, $destinations, $seenNames);
        }

        $dict = $this->dictionaryItems($resolved);
        if ($dict !== null) {
            $localDestination = $this->localDestinationDictionaryValue($dict);
            if ($localDestination !== null) {
                return $this->destinationPageIndex($localDestination['value'], $objects, $pageIndexes, $destinations, $seenNames);
            }
        }

        $array = $this->arrayItems($resolved);
        if ($array === null || $array === []) {
            return null;
        }

        $first = $array[0];
        $pageObjectNumber = $this->referenceObjectNumber($first);
        if ($pageObjectNumber !== null) {
            return $pageIndexes[$pageObjectNumber] ?? null;
        }

        if (is_int($first) && $first >= 0) {
            return $first;
        }

        return $this->destinationPageIndex($first, $objects, $pageIndexes, $destinations, $seenNames);
    }

    /**
     * @param array<string, mixed> $dict
     * @return array{value: mixed}|null
     */
    private function localDestinationDictionaryValue(array $dict): ?array
    {
        if (!array_key_exists('D', $dict)) {
            return null;
        }

        $type = $this->nameValue($dict['S'] ?? null);
        if ($type !== null && $type !== 'GoTo') {
            return null;
        }

        return ['value' => $dict['D']];
    }

    /**
     * @return list<string>
     */
    private function tokens(string $value): array
    {
        $tokens = [];
        $length = strlen($value);
        for ($index = 0; $index < $length;) {
            $char = $value[$index];
            if (ctype_space($char)) {
                $index++;
                continue;
            }

            if ($char === '%') {
                while ($index < $length && $value[$index] !== "\n" && $value[$index] !== "\r") {
                    $index++;
                }
                continue;
            }

            $pair = substr($value, $index, 2);
            if ($pair === '<<' || $pair === '>>') {
                $tokens[] = $pair;
                $index += 2;
                continue;
            }

            if ($char === '[' || $char === ']') {
                $tokens[] = $char;
                $index++;
                continue;
            }

            if ($char === '(') {
                $tokens[] = $this->readLiteralToken($value, $index);
                continue;
            }

            if ($char === '<') {
                $tokens[] = $this->readHexToken($value, $index);
                continue;
            }

            if ($char === '/') {
                $start = $index;
                $index++;
                while ($index < $length && !$this->isDelimiter($value[$index])) {
                    $index++;
                }
                $tokens[] = substr($value, $start, $index - $start);
                continue;
            }

            $start = $index;
            while ($index < $length && !$this->isDelimiter($value[$index])) {
                $index++;
            }
            $tokens[] = substr($value, $start, $index - $start);
        }

        return array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
    }

    /**
     * @param list<string> $tokens
     */
    private function parseValue(array $tokens, int &$index): mixed
    {
        $token = $tokens[$index] ?? null;
        if ($token === null) {
            return null;
        }

        if ($token === '<<') {
            $index++;
            $items = [];
            while (($tokens[$index] ?? null) !== null && $tokens[$index] !== '>>') {
                $key = $tokens[$index] ?? '';
                $index++;
                if (!is_string($key) || !str_starts_with($key, '/')) {
                    continue;
                }

                $items[$this->decodePdfName(substr($key, 1))] = $this->parseValue($tokens, $index);
            }
            if (($tokens[$index] ?? null) === '>>') {
                $index++;
            }

            return ['pdfType' => 'dict', 'items' => $items];
        }

        if ($token === '[') {
            $index++;
            $items = [];
            while (($tokens[$index] ?? null) !== null && $tokens[$index] !== ']') {
                $items[] = $this->parseValue($tokens, $index);
            }
            if (($tokens[$index] ?? null) === ']') {
                $index++;
            }

            return ['pdfType' => 'array', 'items' => $items];
        }

        if (
            preg_match('/^[+-]?\d+$/', $token) === 1
            && preg_match('/^[+-]?\d+$/', (string) ($tokens[$index + 1] ?? '')) === 1
            && ($tokens[$index + 2] ?? null) === 'R'
        ) {
            $index += 3;

            return ['pdfType' => 'ref', 'object' => (int) $token];
        }

        $index++;
        if (str_starts_with($token, '/')) {
            return ['pdfType' => 'name', 'value' => $this->decodePdfName(substr($token, 1))];
        }

        if (str_starts_with($token, '(')) {
            return ['pdfType' => 'string', 'value' => $this->decodeLiteralString($token)];
        }

        if (str_starts_with($token, '<')) {
            return ['pdfType' => 'string', 'value' => $this->decodeHexString($token)];
        }

        if ($token === 'null') {
            return null;
        }

        if ($token === 'true' || $token === 'false') {
            return $token === 'true';
        }

        if (preg_match('/^[+-]?\d+$/', $token) === 1) {
            return (int) $token;
        }

        if (is_numeric($token)) {
            return (float) $token;
        }

        return ['pdfType' => 'keyword', 'value' => $token];
    }

    private function readLiteralToken(string $value, int &$index): string
    {
        $start = $index;
        $depth = 0;
        $length = strlen($value);
        while ($index < $length) {
            $char = $value[$index];
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

        return substr($value, $start, $index - $start);
    }

    private function readHexToken(string $value, int &$index): string
    {
        $start = $index;
        $length = strlen($value);
        $index++;
        while ($index < $length && $value[$index] !== '>') {
            $index++;
        }
        if ($index < $length) {
            $index++;
        }

        return substr($value, $start, $index - $start);
    }

    private function isDelimiter(string $char): bool
    {
        return ctype_space($char) || str_contains('[]()<>{}/%', $char);
    }

    /**
     * @param array<int, mixed> $objects
     */
    private function resolveValue(mixed $value, array $objects, int $depth = 0): mixed
    {
        $objectNumber = $this->referenceObjectNumber($value);
        if ($objectNumber === null || $depth > 20 || !array_key_exists($objectNumber, $objects)) {
            return $value;
        }

        return $this->resolveValue($objects[$objectNumber], $objects, $depth + 1);
    }

    /**
     * @param array<int, mixed> $objects
     * @return array<string, mixed>|null
     */
    private function resolveDictionary(mixed $value, array $objects): ?array
    {
        return $this->dictionaryItems($this->resolveValue($value, $objects));
    }

    /**
     * @param array<int, mixed> $objects
     * @return list<mixed>|null
     */
    private function resolveArray(mixed $value, array $objects): ?array
    {
        return $this->arrayItems($this->resolveValue($value, $objects));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function dictionaryItems(mixed $value): ?array
    {
        return is_array($value) && ($value['pdfType'] ?? null) === 'dict' && is_array($value['items'] ?? null)
            ? $value['items']
            : null;
    }

    /**
     * @return list<mixed>|null
     */
    private function arrayItems(mixed $value): ?array
    {
        return is_array($value) && ($value['pdfType'] ?? null) === 'array' && is_array($value['items'] ?? null)
            ? array_values($value['items'])
            : null;
    }

    private function referenceObjectNumber(mixed $value): ?int
    {
        return is_array($value) && ($value['pdfType'] ?? null) === 'ref' && is_int($value['object'] ?? null)
            ? $value['object']
            : null;
    }

    /**
     * @return array{pdfType: string, object: int}
     */
    private function refValue(int $objectNumber): array
    {
        return ['pdfType' => 'ref', 'object' => $objectNumber];
    }

    private function nameValue(mixed $value): ?string
    {
        return is_array($value) && ($value['pdfType'] ?? null) === 'name' && is_string($value['value'] ?? null)
            ? $value['value']
            : null;
    }

    private function stringOrNameValue(mixed $value): ?string
    {
        if (is_array($value) && ($value['pdfType'] ?? null) === 'string' && is_string($value['value'] ?? null)) {
            return $value['value'];
        }

        return $this->nameValue($value);
    }

    private function decodePdfName(string $name): string
    {
        return preg_replace_callback('/#([\da-fA-F]{2})/', static function (array $match): string {
            return chr(hexdec($match[1]));
        }, $name) ?? $name;
    }

    private function isSafeUri(string $uri): bool
    {
        $trimmed = trim($uri);
        if ($trimmed === '') {
            return false;
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $trimmed, $match) === 1) {
            return in_array(strtolower(rtrim($match[0], ':')), ['http', 'https', 'mailto', 'ftp'], true);
        }

        return str_starts_with($trimmed, '#') || str_starts_with($trimmed, '/') || str_starts_with($trimmed, './') || str_starts_with($trimmed, '../');
    }

    private function decodeLiteralString(string $token): string
    {
        $bytes = substr($token, 1, -1);
        $decoded = '';
        $length = strlen($bytes);
        for ($index = 0; $index < $length; $index++) {
            $char = $bytes[$index];
            if ($char !== '\\') {
                $decoded .= $char;
                continue;
            }

            if ($index + 1 >= $length) {
                break;
            }
            $next = $bytes[++$index];
            if ($next === "\r" || $next === "\n") {
                if ($next === "\r" && ($bytes[$index + 1] ?? '') === "\n") {
                    $index++;
                }
                continue;
            }
            if (preg_match('/[0-7]/', $next) === 1) {
                $octal = $next;
                for ($count = 0; $count < 2 && preg_match('/[0-7]/', (string) ($bytes[$index + 1] ?? '')) === 1; $count++) {
                    $octal .= $bytes[++$index];
                }
                $decoded .= chr(octdec($octal) & 0xff);
                continue;
            }

            $decoded .= match ($next) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\x08",
                'f' => "\x0c",
                default => $next,
            };
        }

        return $this->decodePdfStringBytes($decoded);
    }

    private function decodeHexString(string $token): string
    {
        $hex = preg_replace('/\s+/', '', substr($token, 1, -1));
        if ($hex === null || $hex === '' || preg_match('/^[\da-fA-F]+$/', $hex) !== 1) {
            return '';
        }
        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        $bytes = hex2bin($hex);
        return $bytes === false ? '' : $this->decodePdfStringBytes($bytes);
    }

    private function decodePdfStringBytes(string $bytes): string
    {
        if (str_starts_with($bytes, "\xFE\xFF")) {
            $decoded = iconv('UTF-16BE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }

        if (str_starts_with($bytes, "\xFF\xFE")) {
            $decoded = iconv('UTF-16LE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }

        return $bytes;
    }
}
