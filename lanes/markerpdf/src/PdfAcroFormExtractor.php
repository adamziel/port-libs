<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfAcroFormExtractor
{
    /**
     * @var array<int, int>
     */
    private array $objectGenerations = [];

    private const COMMON_FIELD_FLAGS = [
        1 => 'read_only',
        2 => 'required',
        3 => 'no_export',
    ];

    private const FIELD_TYPE_FLAGS = [
        'Tx' => [
            13 => 'multiline',
            14 => 'password',
            21 => 'file_select',
            23 => 'do_not_spell_check',
            24 => 'do_not_scroll',
            25 => 'comb',
            26 => 'rich_text',
        ],
        'Btn' => [
            15 => 'no_toggle_to_off',
            16 => 'radio',
            17 => 'push_button',
            26 => 'radios_in_unison',
        ],
        'Ch' => [
            18 => 'combo',
            19 => 'edit',
            20 => 'sort',
            22 => 'multi_select',
            23 => 'do_not_spell_check',
            27 => 'commit_on_sel_change',
        ],
    ];

    private const SIGNATURE_SEED_REQUIRED_FLAGS = [
        1 => 'filter',
        2 => 'subfilter',
        4 => 'seed_value_parser_version',
        8 => 'reason',
        16 => 'legal_attestation',
        32 => 'add_revision_info',
        64 => 'digest_method',
    ];

    private const ANNOTATION_FLAGS = [
        1 => 'invisible',
        2 => 'hidden',
        3 => 'print',
        4 => 'no_zoom',
        5 => 'no_rotate',
        6 => 'no_view',
        7 => 'read_only',
        8 => 'locked',
        9 => 'toggle_no_view',
        10 => 'locked_contents',
    ];

    private const WIDGET_HIGHLIGHT_MODE_LABELS = [
        'N' => 'none',
        'I' => 'invert',
        'O' => 'outline',
        'P' => 'push',
        'T' => 'toggle',
    ];

    private const WIDGET_TEXT_POSITION_LABELS = [
        0 => 'caption_only',
        1 => 'caption_above_icon',
        2 => 'caption_below_icon',
        3 => 'caption_right_of_icon',
        4 => 'caption_left_of_icon',
        5 => 'caption_overlaid_icon',
        6 => 'icon_only',
    ];

    private const SUBMIT_FORM_FLAG_NAMES = [
        2 => 'include_no_value_fields',
        3 => 'html_format',
        4 => 'get_method',
        5 => 'submit_coordinates',
        6 => 'xfdf_format',
        7 => 'include_append_saves',
        8 => 'include_annotations',
        9 => 'submit_pdf',
        10 => 'canonical_format',
        11 => 'exclude_non_user_annotations',
        12 => 'exclude_f_key',
        14 => 'embed_form',
    ];

    private const MAX_ACTION_CHAIN_DEPTH = 8;

    /**
     * Native boundary for PDF AcroForm field dictionaries.
     *
     * @return array{need_appearances: bool, default_resources: array<string, mixed>, permissions: array<string, mixed>, signature_flags: array<string, mixed>, xfa_overrides_page_content: bool, xfa_packets: list<array<string, mixed>>, calculation_order: list<array{object: int, field_name: string|null}>, calculation_order_review: list<array<string, mixed>>, fields: list<array<string, mixed>>}
     */
    public function extractForm(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        $catalog = $this->catalogObjectBody($pdfBytes, $objects);
        $permissions = $catalog === null ? $this->emptyPermissions() : $this->documentPermissions($catalog, $objects);
        $acroForm = $catalog === null ? null : $this->acroFormDictionaryBody($catalog, $objects);
        if ($acroForm === null) {
            return [
                'need_appearances' => false,
                'default_resources' => $this->emptyDefaultResources(),
                'permissions' => $permissions,
                'signature_flags' => $this->emptySignatureFlags(),
                'xfa_overrides_page_content' => false,
                'xfa_packets' => [],
                'calculation_order' => [],
                'calculation_order_review' => [],
                'fields' => [],
            ];
        }

        $pageObjectNumbers = $this->orderedPageObjectNumbers($objects, $catalog);
        $pageIndexes = array_flip($pageObjectNumbers);
        $pageWidgets = $this->pageWidgetMap($objects, $pageObjectNumbers);
        $formDefaults = $this->acroFormDefaults($acroForm);
        $defaultResources = $this->defaultResourcesFromEffective($formDefaults, $objects);
        $xfaPackets = $this->xfaPacketsFromAcroForm($acroForm, $objects);
        $fields = [];
        $fieldRefs = $this->fieldReferencesFromAcroForm($acroForm, $objects);
        $fieldRefs = $this->fieldReferencesWithPageWidgetBoundaries($fieldRefs, $objects, $pageWidgets);
        $fieldNamesByObject = $this->fieldNamesByObject($fieldRefs, $objects);
        $fieldNamesByObject = $this->fieldNamesWithPageWidgetParents($fieldNamesByObject, $objects, $pageWidgets);
        $calculationOrder = $this->calculationOrderFromAcroForm($acroForm, $objects, $fieldNamesByObject);
        $calculationOrderReview = $this->calculationOrderReviewFromAcroForm($acroForm, $objects, $fieldNamesByObject);
        $signatureFlags = $this->acroFormSignatureFlags($acroForm);

        foreach ($fieldRefs as $fieldRef) {
            $context = $this->fieldReferenceAncestorContext($fieldRef, $objects, $formDefaults);
            foreach ($this->fieldsFromObject(
                $fieldRef,
                $objects,
                $context['inherited'],
                $context['name_parts'],
                $context['hierarchy_path'],
                [],
                $pageIndexes,
                $pageWidgets,
                $fieldNamesByObject
            ) as $field) {
                $fields[] = $field;
            }
        }

        $fields = $this->annotateSubmitResetActionValueReviews($fields);
        $fields = $this->markCertifyingSignatureFields($fields, $permissions);
        $fields = $this->annotateCalculationAndSignatureState($fields, $calculationOrder, $calculationOrderReview, $signatureFlags);
        if ($xfaPackets !== []) {
            $fields = $this->annotateXfaFieldBoundaries($fields, $xfaPackets);
            $fields = $this->annotateWidgetXfaActionAppearanceValueReviews($fields);
        }
        $fields = $this->annotateRichTextXfaActionStateReviews($fields);
        $fields = $this->annotateSignatureWidgetReviews($fields);
        $fields = $this->annotateSubmitResetAppearanceLockReviews($fields);

        return [
            'need_appearances' => $this->boolValueAfterName($acroForm, 'NeedAppearances') === true,
            'default_resources' => $defaultResources,
            'permissions' => $permissions,
            'signature_flags' => $signatureFlags,
            'xfa_overrides_page_content' => $xfaPackets !== [],
            'xfa_packets' => $xfaPackets,
            'calculation_order' => $calculationOrder,
            'calculation_order_review' => $calculationOrderReview,
            'fields' => $fields,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function extractFields(string $pdfBytes): array
    {
        return $this->extractForm($pdfBytes)['fields'];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySignatureFlags(): array
    {
        return [
            'source' => null,
            'flags' => 0,
            'flag_names' => [],
            'signatures_exist' => false,
            'append_only' => false,
            'append_only_required' => false,
            'executes_signature_validation' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function acroFormSignatureFlags(string $acroForm): array
    {
        $rawFlags = $this->valueAfterName($acroForm, 'SigFlags');
        $flags = $this->numberValueAfterName($acroForm, 'SigFlags') ?? 0;

        return [
            'source' => $rawFlags === null ? null : 'acroform_sigflags',
            'flags' => $flags,
            'flag_names' => $this->signatureFlagNames($flags),
            'signatures_exist' => ($flags & 1) !== 0,
            'append_only' => ($flags & 2) !== 0,
            'append_only_required' => ($flags & 2) !== 0,
            'executes_signature_validation' => false,
        ];
    }

    /**
     * @return list<string>
     */
    private function signatureFlagNames(int $flags): array
    {
        $names = [];
        if (($flags & 1) !== 0) {
            $names[] = 'signatures_exist';
        }
        if (($flags & 2) !== 0) {
            $names[] = 'append_only';
        }

        return $names;
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @param list<array{object: int, field_name: string|null}> $calculationOrder
     * @param list<array<string, mixed>> $calculationOrderReview
     * @param array<string, mixed> $signatureFlags
     * @return list<array<string, mixed>>
     */
    private function annotateCalculationAndSignatureState(array $fields, array $calculationOrder, array $calculationOrderReview, array $signatureFlags): array
    {
        $calculationIndexesByObject = [];
        $calculationIndexesByName = [];
        foreach ($calculationOrder as $index => $entry) {
            if (!array_key_exists((int) $entry['object'], $calculationIndexesByObject)) {
                $calculationIndexesByObject[(int) $entry['object']] = $index;
            }
            if (is_string($entry['field_name']) && $entry['field_name'] !== '') {
                if (!array_key_exists($entry['field_name'], $calculationIndexesByName)) {
                    $calculationIndexesByName[$entry['field_name']] = $index;
                }
            }
        }

        foreach ($fields as $index => $field) {
            $fields[$index]['calculation_state'] = $this->fieldCalculationState($field, $calculationOrder, $calculationOrderReview, $calculationIndexesByObject, $calculationIndexesByName);
            if (($field['field_type'] ?? null) === 'Sig') {
                $fields[$index]['signature_state'] = $this->fieldSignatureState($field, $signatureFlags);
            }
        }

        $signedLocks = $this->signedSignatureLockEntries($fields);
        foreach ($fields as $index => $field) {
            $fields[$index]['signature_lock_state'] = $this->fieldSignatureLockState($field, $signedLocks);
        }

        foreach ($fields as $index => $field) {
            if (($field['field_type'] ?? null) === 'Sig') {
                $fields[$index]['signature_action_state'] = $this->signatureFieldActionState($fields[$index]);
                $fields[$index]['signature_seed_lock_action_review'] = $this->signatureSeedLockActionReview($fields[$index]);
            }
        }

        return $fields;
    }

    /**
     * @param list<array{object: int, field_name: string|null}> $calculationOrder
     * @param array<int, int> $calculationIndexesByObject
     * @param array<string, int> $calculationIndexesByName
     * @return array<string, mixed>
     */
    private function fieldCalculationState(
        array $field,
        array $calculationOrder,
        array $calculationOrderReview,
        array $calculationIndexesByObject,
        array $calculationIndexesByName
    ): array {
        $objectNumber = $field['object'] ?? null;
        $name = $field['name'] ?? null;
        $orderIndex = is_int($objectNumber) && array_key_exists($objectNumber, $calculationIndexesByObject)
            ? $calculationIndexesByObject[$objectNumber]
            : (is_string($name) && array_key_exists($name, $calculationIndexesByName) ? $calculationIndexesByName[$name] : null);
        $orderEntry = $orderIndex === null ? null : ($calculationOrder[$orderIndex] ?? null);
        $orderReview = $orderIndex === null ? null : ($calculationOrderReview[$orderIndex] ?? null);
        $calculateActions = $this->calculateActionSources($field);

        return [
            'source' => 'acroform_calculation_state_boundary',
            'in_calculation_order' => $orderIndex !== null,
            'calculation_order_index' => $orderIndex,
            'calculation_order_object' => is_array($orderEntry) ? $orderEntry['object'] : null,
            'calculation_order_field_name' => is_array($orderEntry) ? $orderEntry['field_name'] : null,
            'calculation_order_target_kind' => is_array($orderReview) ? ($orderReview['target_kind'] ?? null) : null,
            'calculation_order_field_object' => is_array($orderReview) ? ($orderReview['field_object'] ?? null) : null,
            'calculation_order_widget_object' => is_array($orderReview) ? ($orderReview['widget_object'] ?? null) : null,
            'calculation_order_resolved_from_widget' => is_array($orderReview) && ($orderReview['resolved_from_widget'] ?? false) === true,
            'calculation_order_appearance_state' => is_array($orderReview) ? ($orderReview['appearance_state'] ?? null) : null,
            'calculation_order_appearance_states' => is_array($orderReview) ? ($orderReview['appearance_states'] ?? []) : [],
            'calculation_order_selected_appearance_object' => is_array($orderReview) ? ($orderReview['selected_appearance_object'] ?? null) : null,
            'calculation_order_stale_appearance_state' => is_array($orderReview) ? ($orderReview['stale_appearance_state'] ?? null) : null,
            'has_calculate_action' => $calculateActions !== [],
            'calculate_actions' => $calculateActions,
            'value_is_static_review' => true,
            'appearance_value_used_for_calculation' => false,
            'appearance_value_used_for_import' => false,
            'executes_javascript' => false,
            'executes_action' => false,
            'executes_appearance_streams' => false,
            'renders_appearances' => false,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function calculateActionSources(array $field): array
    {
        $actions = [];
        foreach ($field['actions'] ?? [] as $action) {
            if (is_array($action) && ($action['trigger'] ?? null) === 'C') {
                $actions[] = $this->calculateActionSource($action);
            }
        }

        foreach ($field['widgets'] ?? [] as $widget) {
            if (!is_array($widget)) {
                continue;
            }

            foreach ($widget['actions'] ?? [] as $action) {
                if (is_array($action) && ($action['trigger'] ?? null) === 'C') {
                    $actions[] = $this->calculateActionSource($action);
                }
            }
        }

        return $actions;
    }

    /**
     * @return array<string, mixed>
     */
    private function calculateActionSource(array $action): array
    {
        $source = [
            'source' => $action['source'] ?? null,
            'source_object' => $action['source_object'] ?? null,
            'action_object' => $action['action_object'] ?? null,
            'script_sha256' => $action['script_sha256'] ?? null,
            'script_bytes' => $action['script_bytes'] ?? null,
            'executes_javascript' => false,
            'executes_action' => false,
        ];

        if (isset($action['script_object'])) {
            $source['script_object'] = $action['script_object'];
        }
        if (isset($action['script_filters'])) {
            $source['script_filters'] = $action['script_filters'];
        }

        return $source;
    }

    /**
     * @param array<string, mixed> $signatureFlags
     * @return array<string, mixed>
     */
    private function fieldSignatureState(array $field, array $signatureFlags): array
    {
        $signature = $field['signature'] ?? null;
        $hasSignature = is_array($signature);
        $byteRange = $hasSignature ? ($signature['byte_range'] ?? null) : null;

        return [
            'source' => 'acroform_signature_state_boundary',
            'signatures_exist_hint' => (bool) ($signatureFlags['signatures_exist'] ?? false),
            'append_only' => (bool) ($signatureFlags['append_only'] ?? false),
            'append_only_required' => (bool) ($signatureFlags['append_only_required'] ?? false),
            'has_signature_dictionary' => $hasSignature,
            'signed' => $hasSignature && $this->signatureMetadataIndicatesSigned($signature),
            'signature_object' => $hasSignature ? ($signature['object'] ?? null) : null,
            'signed_at' => $hasSignature ? ($signature['signed_at'] ?? null) : null,
            'byte_range' => is_array($byteRange) ? $byteRange : null,
            'byte_range_segment_count' => is_array($byteRange) ? intdiv(count($byteRange), 2) : 0,
            'contents_present' => $hasSignature && ($signature['contents_present'] ?? false) === true,
            'contents_length_bytes' => $hasSignature ? ($signature['contents_length_bytes'] ?? null) : null,
            'certifying_signature' => (bool) ($field['certifying_signature'] ?? false),
            'value_state_source' => 'signature_dictionary_not_field_value',
            'executes_signature_validation' => false,
            'executes_signing' => false,
            'executes_action' => false,
        ];
    }

    private function signatureMetadataIndicatesSigned(?array $signature): bool
    {
        if ($signature === null) {
            return false;
        }

        return ($signature['contents_present'] ?? false) === true
            && ($signature['contents_length_bytes'] ?? 0) > 0
            && is_array($signature['byte_range'] ?? null)
            && count($signature['byte_range']) >= 4;
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @return list<array<string, mixed>>
     */
    private function signedSignatureLockEntries(array $fields): array
    {
        $locks = [];
        foreach ($fields as $field) {
            if (($field['field_type'] ?? null) !== 'Sig') {
                continue;
            }

            $signature = $field['signature'] ?? null;
            $lock = $field['signature_lock'] ?? null;
            if (!is_array($signature) || !is_array($lock) || !$this->signatureMetadataIndicatesSigned($signature)) {
                continue;
            }

            $locks[] = [
                'signature_field' => $field['name'] ?? null,
                'signature_field_object' => $field['object'] ?? null,
                'signature_object' => $signature['object'] ?? null,
                'action' => $lock['action'] ?? null,
                'action_label' => $lock['action_label'] ?? 'unknown',
                'field_names' => $lock['field_names'] ?? [],
                'permission_level' => $lock['permission_level'] ?? null,
                'permission_label' => $lock['permission_label'] ?? null,
                'allowed_changes' => $lock['allowed_changes'] ?? [],
            ];
        }

        return $locks;
    }

    /**
     * @param list<array<string, mixed>> $signedLocks
     * @return array<string, mixed>
     */
    private function fieldSignatureLockState(array $field, array $signedLocks): array
    {
        $fieldName = $field['name'] ?? null;
        $lockedBy = [];
        foreach ($signedLocks as $lock) {
            if (!is_string($fieldName) || !$this->signatureLockAppliesToField($lock, $fieldName)) {
                continue;
            }

            $lockedBy[] = [
                'signature_field' => $lock['signature_field'],
                'signature_object' => $lock['signature_object'],
                'action' => $lock['action'],
                'action_label' => $lock['action_label'],
                'permission_level' => $lock['permission_level'],
                'permission_label' => $lock['permission_label'],
            ];
        }

        return [
            'source' => 'acroform_signature_lock_state_boundary',
            'lock_state_source' => $lockedBy === [] ? null : 'signed_signature_lock',
            'effective_locked' => $lockedBy !== [],
            'locked_by_signature_count' => count($lockedBy),
            'locked_by_signatures' => array_values(array_filter(array_map(
                static fn (array $lock): ?string => is_string($lock['signature_field'] ?? null) ? $lock['signature_field'] : null,
                $lockedBy
            ))),
            'locks' => $lockedBy,
            'permission_labels' => $this->uniqueScalarValues(array_map(
                static fn (array $lock): mixed => $lock['permission_label'] ?? null,
                $lockedBy
            )),
            'executes_action' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function signatureFieldActionState(array $field): array
    {
        $signatureState = is_array($field['signature_state'] ?? null) ? $field['signature_state'] : [];
        $valueState = is_array($field['value_state'] ?? null) ? $field['value_state'] : [];
        $lock = is_array($field['signature_lock'] ?? null) ? $field['signature_lock'] : null;
        $lockState = is_array($field['signature_lock_state'] ?? null) ? $field['signature_lock_state'] : [];
        $widgets = $this->arrayRows($field['widgets'] ?? []);
        $fieldActions = $this->arrayRows($field['actions'] ?? []);
        $widgetActions = [];
        $widgetObjects = [];
        $appearanceStates = [];
        $selectedAppearanceObjects = [];
        $staleAppearanceStateCount = 0;

        foreach ($widgets as $widget) {
            if (is_int($widget['object'] ?? null)) {
                $widgetObjects[] = $widget['object'];
            }
            if (is_string($widget['appearance_state'] ?? null) && $widget['appearance_state'] !== '') {
                $appearanceStates[] = $widget['appearance_state'];
            }

            $normalAppearance = is_array($widget['normal_appearance'] ?? null) ? $widget['normal_appearance'] : null;
            if ($normalAppearance !== null && ($normalAppearance['stale_appearance_state'] ?? false) === true) {
                $staleAppearanceStateCount++;
            }

            $selectedAppearance = is_array($normalAppearance['selected_appearance'] ?? null)
                ? $normalAppearance['selected_appearance']
                : null;
            if ($selectedAppearance !== null && is_int($selectedAppearance['object'] ?? null)) {
                $selectedAppearanceObjects[] = $selectedAppearance['object'];
            }

            foreach ($this->arrayRows($widget['actions'] ?? []) as $action) {
                $widgetActions[] = $action;
            }
        }

        $actions = array_merge($fieldActions, $widgetActions);
        $signed = ($signatureState['signed'] ?? false) === true;

        return [
            'source' => 'acroform_signature_field_action_state_boundary',
            'field_name' => $field['name'] ?? null,
            'field_object' => $field['object'] ?? null,
            'signed' => $signed,
            'signature_object' => $signatureState['signature_object'] ?? null,
            'signed_at' => $signatureState['signed_at'] ?? null,
            'signatures_exist_hint' => (bool) ($signatureState['signatures_exist_hint'] ?? false),
            'append_only' => (bool) ($signatureState['append_only'] ?? false),
            'value_state_source' => $signatureState['value_state_source'] ?? null,
            'has_current_value' => (bool) ($valueState['has_current_value'] ?? false),
            'field_value_used_for_signature' => false,
            'field_value_used_for_import' => false,
            'appearance_value_used_for_signature' => false,
            'appearance_value_used_for_import' => false,
            'action_count' => count($actions),
            'field_action_count' => count($fieldActions),
            'widget_action_count' => count($widgetActions),
            'action_types' => $this->uniqueScalarValues(array_map(
                static fn (array $action): mixed => $action['action_type'] ?? null,
                $actions
            )),
            'action_triggers' => $this->uniqueScalarValues(array_map(
                static fn (array $action): mixed => $action['trigger'] ?? null,
                $actions
            )),
            'action_safety_labels' => $this->uniqueScalarValues(array_map(
                static fn (array $action): mixed => $action['safety'] ?? null,
                $actions
            )),
            'blocked_unsafe_action_count' => $this->actionCountWithSafety($actions, 'blocked-unsafe-uri'),
            'launch_action_count' => $this->actionCountWithType($actions, 'Launch'),
            'review_only_action_count' => count($actions),
            'executes_action' => $this->anyActionFlagTrue($actions, 'executes_action'),
            'executes_javascript' => $this->anyActionFlagTrue($actions, 'executes_javascript'),
            'executes_signature_validation' => false,
            'executes_signing' => false,
            'widget_count' => count($widgets),
            'widget_objects' => array_values(array_unique($widgetObjects)),
            'appearance_states' => $this->uniqueScalarValues($appearanceStates),
            'selected_appearance_objects' => array_values(array_unique($selectedAppearanceObjects)),
            'stale_appearance_state_count' => $staleAppearanceStateCount,
            'signature_lock_action' => $lock['action'] ?? null,
            'signature_lock_field_names' => is_array($lock['field_names'] ?? null) ? $lock['field_names'] : [],
            'signature_lock_applies_after_signing' => $signed && $lock !== null && ($lock['action_valid'] ?? false) === true,
            'signature_lock_effective_locked' => (bool) ($lockState['effective_locked'] ?? false),
            'locked_by_signature_count' => (int) ($lockState['locked_by_signature_count'] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function signatureSeedLockActionReview(array $field): array
    {
        $signatureState = is_array($field['signature_state'] ?? null) ? $field['signature_state'] : [];
        $seedValue = is_array($field['signature_seed_value'] ?? null) ? $field['signature_seed_value'] : null;
        $lock = is_array($field['signature_lock'] ?? null) ? $field['signature_lock'] : null;
        $widgets = $this->arrayRows($field['widgets'] ?? []);
        $fieldActions = $this->arrayRows($field['actions'] ?? []);
        $widgetActions = [];

        foreach ($widgets as $widget) {
            foreach ($this->arrayRows($widget['actions'] ?? []) as $action) {
                $widgetActions[] = $action;
            }
        }

        $actions = array_merge($fieldActions, $widgetActions);
        $timestamp = is_array($seedValue['timestamp'] ?? null) ? $seedValue['timestamp'] : null;
        $mdp = is_array($seedValue['mdp'] ?? null) ? $seedValue['mdp'] : null;
        $actionFieldNames = $this->actionFieldNamesFromRows($actions);
        $submitFieldNames = $this->actionFieldNames($actions, ['SubmitForm']);
        $resetFieldNames = $this->actionFieldNames($actions, ['ResetForm']);
        $hideFieldNames = $this->actionFieldNames($actions, ['Hide']);
        $lockedActionFieldNames = $this->lockedActionFieldNames($actionFieldNames, $lock);
        $lockedSubmitFieldNames = $this->lockedActionFieldNames($submitFieldNames, $lock);
        $lockedResetFieldNames = $this->lockedActionFieldNames($resetFieldNames, $lock);
        $lockedHideFieldNames = $this->lockedActionFieldNames($hideFieldNames, $lock);

        return [
            'source' => 'acroform_signature_seed_lock_action_boundary',
            'field_name' => $field['name'] ?? null,
            'field_object' => $field['object'] ?? null,
            'signed' => (bool) ($signatureState['signed'] ?? false),
            'signature_object' => $signatureState['signature_object'] ?? null,
            'seed_value_present' => $seedValue !== null,
            'seed_value_object' => $seedValue['object'] ?? null,
            'seed_value_required_constraints' => is_array($seedValue['required_constraints'] ?? null) ? $seedValue['required_constraints'] : [],
            'seed_required_constraint_count' => is_array($seedValue['required_constraints'] ?? null) ? count($seedValue['required_constraints']) : 0,
            'seed_constraints_required' => is_array($seedValue['required_constraints'] ?? null) && $seedValue['required_constraints'] !== [],
            'seed_value_filter' => $seedValue['filter'] ?? null,
            'seed_value_subfilters' => is_array($seedValue['subfilters'] ?? null) ? $seedValue['subfilters'] : [],
            'seed_value_digest_methods' => is_array($seedValue['digest_methods'] ?? null) ? $seedValue['digest_methods'] : [],
            'seed_value_reason_count' => is_array($seedValue['reasons'] ?? null) ? count($seedValue['reasons']) : 0,
            'seed_value_timestamp_required' => (bool) ($timestamp['required'] ?? false),
            'seed_value_timestamp_url' => $timestamp['url'] ?? null,
            'seed_mdp_permission_level' => $mdp['permission_level'] ?? null,
            'seed_mdp_permission_label' => $mdp['permission_label'] ?? null,
            'seed_mdp_allowed_changes' => is_array($mdp['allowed_changes'] ?? null) ? $mdp['allowed_changes'] : [],
            'lock_present' => $lock !== null,
            'lock_object' => $lock['object'] ?? null,
            'lock_action' => $lock['action'] ?? null,
            'lock_action_label' => $lock['action_label'] ?? null,
            'lock_field_names' => is_array($lock['field_names'] ?? null) ? $lock['field_names'] : [],
            'lock_field_count' => is_array($lock['field_names'] ?? null) ? count($lock['field_names']) : 0,
            'lock_permission_label' => $lock['permission_label'] ?? null,
            'lock_applies_after_signing' => (bool) ($signatureState['signed'] ?? false) && $lock !== null && ($lock['action_valid'] ?? false) === true,
            'action_count' => count($actions),
            'field_action_count' => count($fieldActions),
            'widget_action_count' => count($widgetActions),
            'action_types' => $this->uniqueScalarValues(array_map(
                static fn (array $action): mixed => $action['action_type'] ?? null,
                $actions
            )),
            'action_safety_labels' => $this->actionSafetyLabels($actions),
            'submit_form_action_count' => $this->actionCountWithType($actions, 'SubmitForm'),
            'reset_form_action_count' => $this->actionCountWithType($actions, 'ResetForm'),
            'import_data_action_count' => $this->actionCountWithType($actions, 'ImportData'),
            'hide_action_count' => $this->actionCountWithType($actions, 'Hide'),
            'unsafe_action_count' => $this->unsafeSeedLockActionCount($actions),
            'action_field_names' => $actionFieldNames,
            'submit_action_field_names' => $submitFieldNames,
            'reset_action_field_names' => $resetFieldNames,
            'hide_action_field_names' => $hideFieldNames,
            'actions_target_locked_fields' => $lockedActionFieldNames !== [],
            'locked_action_field_names' => $lockedActionFieldNames,
            'locked_submit_field_names' => $lockedSubmitFieldNames,
            'locked_reset_field_names' => $lockedResetFieldNames,
            'locked_hide_field_names' => $lockedHideFieldNames,
            'review_only' => true,
            'seed_constraints_enforced_on_import' => false,
            'lock_used_for_form_action_execution' => false,
            'form_actions_execute_on_import' => false,
            'submits_form_data' => false,
            'resets_form_values' => false,
            'imports_form_data' => false,
            'executes_action' => false,
            'executes_javascript' => false,
            'executes_signature_validation' => false,
            'executes_signing' => false,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function arrayRows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, static fn (mixed $row): bool => is_array($row)));
    }

    /**
     * @param list<array<string, mixed>> $actions
     */
    private function actionCountWithSafety(array $actions, string $safety): int
    {
        return count(array_filter(
            $actions,
            static fn (array $action): bool => ($action['safety'] ?? null) === $safety
        ));
    }

    /**
     * @param list<array<string, mixed>> $actions
     */
    private function actionCountWithType(array $actions, string $type): int
    {
        return count(array_filter(
            $actions,
            static fn (array $action): bool => ($action['action_type'] ?? null) === $type
        ));
    }

    /**
     * @param list<array<string, mixed>> $actions
     */
    private function anyActionFlagTrue(array $actions, string $flag): bool
    {
        foreach ($actions as $action) {
            if (($action[$flag] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $lock
     */
    private function signatureLockAppliesToField(array $lock, string $fieldName): bool
    {
        $action = $lock['action'] ?? null;
        $fieldNames = array_values(array_filter(
            $lock['field_names'] ?? [],
            static fn (mixed $name): bool => is_string($name) && $name !== ''
        ));

        return match ($action) {
            'All' => true,
            'Include' => in_array($fieldName, $fieldNames, true),
            'Exclude' => !in_array($fieldName, $fieldNames, true),
            default => false,
        };
    }

    /**
     * @param list<mixed> $values
     * @return list<mixed>
     */
    private function uniqueScalarValues(array $values): array
    {
        $unique = [];
        foreach ($values as $value) {
            if (!is_scalar($value) || in_array($value, $unique, true)) {
                continue;
            }

            $unique[] = $value;
        }

        return $unique;
    }

    /**
     * @return list<array<string, mixed>>
     * @param array<int, string> $objects
     */
    private function xfaPacketsFromAcroForm(string $acroForm, array $objects): array
    {
        $xfa = $this->valueAfterName($acroForm, 'XFA');
        if ($xfa === null) {
            return [];
        }

        $xfa = trim($xfa);
        if ($xfa === '') {
            return [];
        }

        if (str_starts_with($xfa, '[')) {
            $body = $this->arrayBodyFromValue($xfa);
            if ($body === null) {
                return [];
            }

            $tokens = $this->xfaArrayTokens($body);
            $packets = [];
            for ($index = 0, $count = count($tokens); $index < $count; $index++) {
                $token = $tokens[$index];
                $packetName = $token['type'] === 'string' ? $token['value'] : null;
                if ($packetName === null) {
                    $packet = $this->xfaPacketFromToken($token, $objects, null, count($packets), 'acroform_xfa_array');
                    if ($packet !== null) {
                        $packets[] = $packet;
                    }
                    continue;
                }

                $valueToken = $tokens[$index + 1] ?? null;
                if ($valueToken === null) {
                    break;
                }

                $packet = $this->xfaPacketFromToken($valueToken, $objects, $packetName, count($packets), 'acroform_xfa_array');
                if ($packet !== null) {
                    $packets[] = $packet;
                }
                $index++;
            }

            return $packets;
        }

        $token = $this->xfaTokenFromValue($xfa);
        $packet = $token === null ? null : $this->xfaPacketFromToken($token, $objects, null, 0, 'acroform_xfa_value');

        return $packet === null ? [] : [$packet];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function xfaArrayTokens(string $body): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($body);
        while ($offset < $length) {
            $token = $this->readXfaTokenAt($body, $offset);
            if ($token === null) {
                $offset++;
                continue;
            }

            $offset = $token['end'];
            unset($token['end']);
            $tokens[] = $token;
        }

        return $tokens;
    }

    /**
     * @return array{type: string, value?: string, object?: int, end: int}|null
     */
    private function readXfaTokenAt(string $body, int $offset): ?array
    {
        $this->skipWhitespace($body, $offset);
        if ($offset >= strlen($body)) {
            return null;
        }

        $referenceEnd = null;
        $reference = $this->readIndirectReferenceAt($body, $offset, $referenceEnd);
        if ($reference !== null && $referenceEnd !== null) {
            return [
                'type' => 'reference',
                'object' => $reference['object'],
                'end' => $referenceEnd,
            ];
        }

        if ($body[$offset] === '(') {
            $end = $this->skipLiteralString($body, $offset);
            return [
                'type' => 'string',
                'value' => $this->decodePdfStringBytes($this->decodeLiteralString(substr($body, $offset + 1, $end - $offset - 2))),
                'end' => $end,
            ];
        }

        if ($body[$offset] === '<' && substr($body, $offset, 2) !== '<<') {
            $end = $this->skipHexString($body, $offset);
            $hex = preg_replace('/\s+/', '', substr($body, $offset + 1, $end - $offset - 2)) ?? '';
            if (strlen($hex) % 2 === 1) {
                $hex .= '0';
            }

            $bytes = $hex === '' ? '' : hex2bin($hex);
            if ($bytes === false) {
                return null;
            }

            return [
                'type' => 'string',
                'value' => $this->decodePdfStringBytes($bytes),
                'end' => $end,
            ];
        }

        if ($body[$offset] === '/') {
            $end = $this->skipPdfName($body, $offset);
            return [
                'type' => 'string',
                'value' => $this->decodePdfName(substr($body, $offset, $end - $offset)),
                'end' => $end,
            ];
        }

        if (substr($body, $offset, 2) === '<<') {
            $endOffset = null;
            $dictionary = $this->readPdfDictionaryAt($body, $offset, $endOffset);
            if ($dictionary === null || $endOffset === null) {
                return null;
            }

            return [
                'type' => 'dictionary',
                'value' => $dictionary,
                'end' => $endOffset,
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function xfaPacketFromToken(array $token, array $objects, ?string $packetName, int $index, string $source): ?array
    {
        $payload = $this->xfaPayloadFromToken($token, $objects);
        if ($payload === null) {
            return null;
        }

        $normalized = $this->normalizeXfaXmlPayload($payload['xml']);
        $xml = trim($normalized['xml']);
        if ($xml === '') {
            return null;
        }

        $root = $this->xmlRootName($xml);
        $name = $packetName !== null && $packetName !== '' ? $packetName : ($root ?? 'xfa');
        $xdpPacketNames = $this->xdpPacketNames($xml);
        $fieldNames = $this->xfaFieldNames($xml);
        $dataNodeNames = $this->xfaDataNodeNames($xml);
        $dataPaths = $this->xfaDataPaths($xml);
        $dataPathValues = $this->xfaDataPathValues($xml);
        $signatureFieldNames = $this->xfaSignatureFieldNames($fieldNames, $dataPaths);

        return [
            'index' => $index,
            'name' => $name,
            'object' => $payload['object'],
            'source' => $source,
            'filters' => $payload['filters'],
            'xml_root' => $root,
            'xml_encoding' => $normalized['encoding'],
            'decoded_to_utf8' => $normalized['decoded_to_utf8'],
            'length_bytes' => strlen($xml),
            'xml_sha256' => hash('sha256', $xml),
            'is_xdp_package' => $this->xmlLocalName($root) === 'xdp',
            'xdp_packet_names' => $xdpPacketNames,
            'field_names' => $fieldNames,
            'data_node_names' => $dataNodeNames,
            'data_paths' => $dataPaths,
            'data_path_values' => $dataPathValues,
            'signature_field_names' => $signatureFieldNames,
            'has_signature_field' => $signatureFieldNames !== []
                || in_array('signature', $xdpPacketNames, true)
                || $this->xmlContainsElement($xml, 'signature')
                || $this->xmlContainsElement($xml, 'signData'),
            'signature_payload_exposed' => false,
            'executes_signature_validation' => false,
            'executes_signing' => false,
            'has_template' => $this->xfaPayloadHasRole($name, $root, $xdpPacketNames, $xml, 'template'),
            'has_datasets' => $this->xfaPayloadHasRole($name, $root, $xdpPacketNames, $xml, 'datasets'),
            'text_preview' => $this->xmlTextPreview($xml),
        ];
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @param list<array<string, mixed>> $xfaPackets
     * @return list<array<string, mixed>>
     */
    private function annotateXfaFieldBoundaries(array $fields, array $xfaPackets): array
    {
        foreach ($fields as $index => $field) {
            $fieldName = is_string($field['name'] ?? null) ? $field['name'] : null;
            $fieldType = is_string($field['field_type'] ?? null) ? $field['field_type'] : null;
            $boundary = $this->xfaBoundaryForField($fieldName, $fieldType, $xfaPackets);
            $fields[$index]['xfa_boundary'] = $boundary;
            $fields[$index]['xfa_widget_review'] = $this->xfaWidgetCurrentBaseReview($fields[$index], $boundary);

            if ($fieldType === 'Sig' && is_array($fields[$index]['signature_state'] ?? null)) {
                $fields[$index]['signature_state']['xfa_referenced'] = $boundary['referenced_by_xfa'];
                $fields[$index]['signature_state']['xfa_dynamic_value_present'] = $boundary['dynamic_value_present'];
                $fields[$index]['signature_state']['xfa_value_used_for_signature'] = false;
            }
        }

        return $fields;
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @return list<array<string, mixed>>
     */
    private function annotateWidgetXfaActionAppearanceValueReviews(array $fields): array
    {
        foreach ($fields as $index => $field) {
            $xfaBoundary = is_array($field['xfa_boundary'] ?? null) ? $field['xfa_boundary'] : null;
            if ($xfaBoundary === null || ($xfaBoundary['referenced_by_xfa'] ?? false) !== true) {
                continue;
            }

            $widgets = $this->arrayRows($field['widgets'] ?? []);
            $fieldActions = $this->arrayRows($field['actions'] ?? []);
            $widgetActions = [];
            foreach ($widgets as $widget) {
                foreach ($this->arrayRows($widget['actions'] ?? []) as $action) {
                    $widgetActions[] = $action;
                }
            }

            if ($widgets === [] && $fieldActions === [] && $widgetActions === []) {
                continue;
            }

            $fields[$index]['widget_xfa_action_appearance_value_review'] = $this->widgetXfaActionAppearanceValueReview(
                $field,
                $xfaBoundary,
                $widgets,
                $fieldActions,
                $widgetActions
            );
        }

        return $fields;
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @return list<array<string, mixed>>
     */
    private function annotateSignatureWidgetReviews(array $fields): array
    {
        foreach ($fields as $index => $field) {
            if (($field['field_type'] ?? null) !== 'Sig') {
                continue;
            }

            $review = $this->signatureWidgetReview($fields[$index]);
            $fields[$index]['signature_widget_review'] = $review;
            if (is_array($review['action_bundle'] ?? null)) {
                $fields[$index]['signature_widget_action_bundle'] = $review['action_bundle'];
            }
            if (is_array($review['lock_resource_review'] ?? null)) {
                $fields[$index]['signature_widget_lock_resource_review'] = $review['lock_resource_review'];
            }
        }

        return $fields;
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @return list<array<string, mixed>>
     */
    private function annotateRichTextXfaActionStateReviews(array $fields): array
    {
        foreach ($fields as $index => $field) {
            $state = is_array($field['value_state'] ?? null) ? $field['value_state'] : [];
            $richTextReview = is_array($field['rich_text_review'] ?? null)
                ? $field['rich_text_review']
                : (is_array($state['rich_text_review'] ?? null) ? $state['rich_text_review'] : null);
            if ($richTextReview === null) {
                continue;
            }

            $xfaBoundary = is_array($field['xfa_boundary'] ?? null) ? $field['xfa_boundary'] : [];
            $hasXfaReference = ($xfaBoundary['referenced_by_xfa'] ?? false) === true
                || ($xfaBoundary['dynamic_value_present'] ?? false) === true;
            $hasActions = $this->arrayRows($field['actions'] ?? []) !== [];
            foreach ($this->arrayRows($field['widgets'] ?? []) as $widget) {
                if ($this->arrayRows($widget['actions'] ?? []) !== []) {
                    $hasActions = true;
                    break;
                }
            }

            if (!$hasXfaReference && !$hasActions) {
                continue;
            }

            $fields[$index]['rich_text_xfa_action_state_review'] = $this->richTextXfaActionStateReview($field, $richTextReview, $xfaBoundary);
        }

        return $fields;
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @return list<array<string, mixed>>
     */
    private function annotateSubmitResetAppearanceLockReviews(array $fields): array
    {
        $fieldsByName = [];
        foreach ($fields as $field) {
            $name = $field['name'] ?? null;
            if (is_string($name) && $name !== '') {
                $fieldsByName[$name] = $field;
            }
        }

        foreach ($fields as $index => $field) {
            $fieldActions = $this->submitResetActions($this->arrayRows($field['actions'] ?? []));
            $widgetActions = [];
            foreach ($this->arrayRows($field['widgets'] ?? []) as $widget) {
                foreach ($this->submitResetActions($this->arrayRows($widget['actions'] ?? [])) as $action) {
                    $widgetActions[] = $action;
                }
            }

            $actions = array_merge($fieldActions, $widgetActions);
            if ($actions === []) {
                continue;
            }

            $fields[$index]['submit_reset_appearance_lock_review'] = $this->submitResetAppearanceLockReview($field, $actions, $fieldActions, $widgetActions, $fieldsByName);
        }

        return $fields;
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @return list<array<string, mixed>>
     */
    private function submitResetActions(array $actions): array
    {
        return array_values(array_filter(
            $actions,
            static fn (array $action): bool => in_array($action['action_type'] ?? null, ['SubmitForm', 'ResetForm'], true)
        ));
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @param list<array<string, mixed>> $fieldActions
     * @param list<array<string, mixed>> $widgetActions
     * @param array<string, array<string, mixed>> $fieldsByName
     * @return array<string, mixed>
     */
    private function submitResetAppearanceLockReview(array $field, array $actions, array $fieldActions, array $widgetActions, array $fieldsByName): array
    {
        $selectedFieldNames = [];
        $submittedFieldNames = [];
        $resetFieldNames = [];
        $fieldModes = [];

        foreach ($actions as $action) {
            $review = is_array($action['field_value_review'] ?? null) ? $action['field_value_review'] : [];
            foreach ($this->fieldNamesFromRows($this->arrayRows($review['field_rows'] ?? [])) as $name) {
                $this->appendUniqueString($selectedFieldNames, $name);
            }
            foreach ($this->stringListValue($review['submitted_field_names'] ?? []) as $name) {
                $this->appendUniqueString($submittedFieldNames, $name);
            }
            foreach ($this->stringListValue($review['reset_field_names'] ?? []) as $name) {
                $this->appendUniqueString($resetFieldNames, $name);
            }
            if (is_string($action['fields_mode'] ?? null)) {
                $this->appendUniqueString($fieldModes, $action['fields_mode']);
            }
        }

        $targetRows = $this->submitResetAppearanceLockTargetRows($selectedFieldNames, $fieldsByName);
        $lockedTargetRows = array_values(array_filter(
            $targetRows,
            static fn (array $row): bool => ($row['locked_by_signed_signature'] ?? false) === true
        ));
        $appearanceRows = array_values(array_filter(
            $targetRows,
            static fn (array $row): bool => ($row['appearance_states'] ?? []) !== [] || ($row['selected_appearance_objects'] ?? []) !== []
        ));
        $staleAppearanceRows = array_values(array_filter(
            $targetRows,
            static fn (array $row): bool => ($row['stale_appearance_state_count'] ?? 0) > 0
        ));
        $selectedAppearanceObjects = [];
        foreach ($appearanceRows as $row) {
            foreach ($this->integerListValue($row['selected_appearance_objects'] ?? []) as $objectNumber) {
                if (!in_array($objectNumber, $selectedAppearanceObjects, true)) {
                    $selectedAppearanceObjects[] = $objectNumber;
                }
            }
        }

        return [
            'source' => 'acroform_submit_reset_appearance_lock_currentbase_review_boundary',
            'field_name' => $field['name'] ?? null,
            'field_object' => $field['object'] ?? null,
            'field_type' => $field['field_type'] ?? null,
            'action_count' => count($actions),
            'field_action_count' => count($fieldActions),
            'widget_action_count' => count($widgetActions),
            'submit_form_action_count' => $this->actionCountWithType($actions, 'SubmitForm'),
            'reset_form_action_count' => $this->actionCountWithType($actions, 'ResetForm'),
            'action_objects' => $this->integerValuesFromRows($actions, 'action_object'),
            'action_source_objects' => $this->integerValuesFromRows($actions, 'source_object'),
            'action_triggers' => $this->uniqueScalarValues(array_map(
                static fn (array $action): mixed => $action['trigger'] ?? null,
                $actions
            )),
            'action_trigger_labels' => $this->uniqueScalarValues(array_map(
                static fn (array $action): mixed => $action['trigger_label'] ?? null,
                $actions
            )),
            'fields_modes' => $fieldModes,
            'selected_field_count' => count($selectedFieldNames),
            'selected_field_names' => $selectedFieldNames,
            'submitted_field_names' => $submittedFieldNames,
            'reset_field_names' => $resetFieldNames,
            'target_field_count' => count($targetRows),
            'target_fields' => $targetRows,
            'locked_target_field_names' => $this->fieldNamesFromRows($lockedTargetRows),
            'locked_submit_field_names' => $this->lockedSubmitResetActionFieldNames($submittedFieldNames, $fieldsByName),
            'locked_reset_field_names' => $this->lockedSubmitResetActionFieldNames($resetFieldNames, $fieldsByName),
            'appearance_field_names' => $this->fieldNamesFromRows($appearanceRows),
            'selected_appearance_objects' => $selectedAppearanceObjects,
            'stale_appearance_field_names' => $this->fieldNamesFromRows($staleAppearanceRows),
            'stale_appearance_state_count' => array_sum(array_map(
                static fn (array $row): int => (int) ($row['stale_appearance_state_count'] ?? 0),
                $targetRows
            )),
            'current_value_field_names' => $this->fieldNamesFromRows(array_filter(
                $targetRows,
                static fn (array $row): bool => ($row['has_current_value'] ?? false) === true
            )),
            'default_value_field_names' => $this->fieldNamesFromRows(array_filter(
                $targetRows,
                static fn (array $row): bool => ($row['has_default_value'] ?? false) === true
            )),
            'changed_from_default_field_names' => $this->fieldNamesFromRows(array_filter(
                $targetRows,
                static fn (array $row): bool => ($row['changed_from_default'] ?? false) === true
            )),
            'field_value_authoritative' => true,
            'default_value_authoritative_for_reset_review' => true,
            'submit_values_review_only' => true,
            'reset_values_review_only' => true,
            'signature_locks_enforced_on_import' => false,
            'appearance_value_used_for_import' => false,
            'appearance_payload_text_exposed' => false,
            'submits_form_data' => false,
            'resets_form_values' => false,
            'executes_action' => false,
            'executes_javascript' => false,
            'executes_appearance_streams' => false,
            'renders_appearances' => false,
        ];
    }

    /**
     * @param list<string> $fieldNames
     * @param array<string, array<string, mixed>> $fieldsByName
     * @return list<string>
     */
    private function lockedSubmitResetActionFieldNames(array $fieldNames, array $fieldsByName): array
    {
        $locked = [];
        foreach ($fieldNames as $name) {
            $field = $fieldsByName[$name] ?? null;
            $lockState = is_array($field['signature_lock_state'] ?? null) ? $field['signature_lock_state'] : null;
            if ($lockState !== null && ($lockState['effective_locked'] ?? false) === true) {
                $locked[] = $name;
            }
        }

        return $locked;
    }

    /**
     * @param list<string> $fieldNames
     * @param array<string, array<string, mixed>> $fieldsByName
     * @return list<array<string, mixed>>
     */
    private function submitResetAppearanceLockTargetRows(array $fieldNames, array $fieldsByName): array
    {
        $rows = [];
        foreach ($fieldNames as $name) {
            $field = $fieldsByName[$name] ?? null;
            if ($field === null) {
                continue;
            }

            $valueState = is_array($field['value_state'] ?? null) ? $field['value_state'] : [];
            $lockState = is_array($field['signature_lock_state'] ?? null) ? $field['signature_lock_state'] : [];
            $appearance = $this->fieldWidgetAppearanceSummary($field);

            $rows[] = [
                'source' => 'acroform_submit_reset_appearance_lock_target_currentbase',
                'field_name' => $name,
                'field_object' => $field['object'] ?? null,
                'field_type' => $field['field_type'] ?? null,
                'field_type_label' => $field['field_type_label'] ?? null,
                'flags' => $field['flags'] ?? 0,
                'flag_names' => $field['flag_names'] ?? [],
                'has_current_value' => (bool) ($valueState['has_current_value'] ?? false),
                'has_default_value' => (bool) ($valueState['has_default_value'] ?? false),
                'current' => $valueState['effective_current_state'] ?? ($valueState['current'] ?? ($field['value'] ?? null)),
                'default' => $valueState['default'] ?? ($field['default_value'] ?? null),
                'current_source' => $valueState['state_source'] ?? ($valueState['current_source'] ?? null),
                'default_source' => $valueState['default_source'] ?? null,
                'changed_from_default' => $valueState['changed_from_default'] ?? null,
                'locked_by_signed_signature' => (bool) ($lockState['effective_locked'] ?? false),
                'locked_by_signatures' => $lockState['locked_by_signatures'] ?? [],
                'locked_by_signature_count' => (int) ($lockState['locked_by_signature_count'] ?? 0),
                'signature_lock_permission_labels' => $lockState['permission_labels'] ?? [],
                'widget_count' => $appearance['widget_count'],
                'page_referenced_widget_count' => $appearance['page_referenced_widget_count'],
                'widget_objects' => $appearance['widget_objects'],
                'appearance_states' => $appearance['appearance_states'],
                'selected_appearance_objects' => $appearance['selected_appearance_objects'],
                'selected_appearance_decoded_sha256' => $appearance['selected_appearance_decoded_sha256'],
                'checked_widget_count' => $valueState['checked_widget_count'] ?? $appearance['checked_widget_count'],
                'widget_state_consistent' => $valueState['widget_state_consistent'] ?? null,
                'stale_appearance_state_count' => $appearance['stale_appearance_state_count'],
                'appearance_value_used_for_import' => false,
                'appearance_payload_text_exposed' => false,
                'executes_appearance_streams' => false,
                'renders_appearances' => false,
                'executes_action' => false,
            ];
        }

        return $rows;
    }

    /**
     * @return array{widget_count: int, page_referenced_widget_count: int, widget_objects: list<int>, appearance_states: list<string>, selected_appearance_objects: list<int>, selected_appearance_decoded_sha256: list<string>, checked_widget_count: int, stale_appearance_state_count: int}
     */
    private function fieldWidgetAppearanceSummary(array $field): array
    {
        $widgets = $this->arrayRows($field['widgets'] ?? []);
        $appearanceStates = [];
        $selectedObjects = [];
        $selectedHashes = [];
        $staleCount = 0;
        $checkedCount = 0;

        foreach ($widgets as $widget) {
            foreach ($this->stringListValue($widget['appearance_states'] ?? []) as $state) {
                $this->appendUniqueString($appearanceStates, $state);
            }
            if (($widget['checked'] ?? false) === true) {
                $checkedCount++;
            }

            $normalAppearance = is_array($widget['normal_appearance'] ?? null) ? $widget['normal_appearance'] : null;
            if ($normalAppearance !== null && ($normalAppearance['stale_appearance_state'] ?? false) === true) {
                $staleCount++;
            }

            $selected = is_array($normalAppearance['selected_appearance'] ?? null)
                ? $normalAppearance['selected_appearance']
                : null;
            if ($selected === null) {
                continue;
            }

            $objectNumber = $selected['object'] ?? null;
            if (is_int($objectNumber) && !in_array($objectNumber, $selectedObjects, true)) {
                $selectedObjects[] = $objectNumber;
            }

            $hash = $selected['decoded_sha256'] ?? null;
            if (is_string($hash) && $hash !== '') {
                $this->appendUniqueString($selectedHashes, $hash);
            }
        }

        return [
            'widget_count' => count($widgets),
            'page_referenced_widget_count' => count(array_filter(
                $widgets,
                static fn (array $widget): bool => ($widget['referenced_from_page_annots'] ?? false) === true
            )),
            'widget_objects' => $this->integerValuesFromRows($widgets, 'object'),
            'appearance_states' => $appearanceStates,
            'selected_appearance_objects' => $selectedObjects,
            'selected_appearance_decoded_sha256' => $selectedHashes,
            'checked_widget_count' => $checkedCount,
            'stale_appearance_state_count' => $staleCount,
        ];
    }

    /**
     * @param list<string> $values
     */
    private function appendUniqueString(array &$values, string $value): void
    {
        if ($value !== '' && !in_array($value, $values, true)) {
            $values[] = $value;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function richTextXfaActionStateReview(array $field, array $richTextReview, array $xfaBoundary): array
    {
        $valueState = is_array($field['value_state'] ?? null) ? $field['value_state'] : [];
        $widgets = $this->arrayRows($field['widgets'] ?? []);
        $fieldActions = $this->arrayRows($field['actions'] ?? []);
        $widgetActions = [];
        $widgetObjects = [];
        $appearanceStates = [];
        $selectedAppearanceObjects = [];
        $staleAppearanceStateCount = 0;
        $pageReferencedWidgetCount = 0;

        foreach ($widgets as $widget) {
            if (($widget['referenced_from_page_annots'] ?? false) === true) {
                $pageReferencedWidgetCount++;
            }
            if (is_int($widget['object'] ?? null)) {
                $widgetObjects[] = $widget['object'];
            }
            if (is_string($widget['appearance_state'] ?? null) && $widget['appearance_state'] !== '') {
                $appearanceStates[] = $widget['appearance_state'];
            }

            $normalAppearance = is_array($widget['normal_appearance'] ?? null) ? $widget['normal_appearance'] : null;
            if ($normalAppearance !== null && ($normalAppearance['stale_appearance_state'] ?? false) === true) {
                $staleAppearanceStateCount++;
            }

            $selectedAppearance = is_array($normalAppearance['selected_appearance'] ?? null)
                ? $normalAppearance['selected_appearance']
                : null;
            if ($selectedAppearance !== null && is_int($selectedAppearance['object'] ?? null)) {
                $selectedAppearanceObjects[] = $selectedAppearance['object'];
            }

            foreach ($this->arrayRows($widget['actions'] ?? []) as $action) {
                $widgetActions[] = $action;
            }
        }

        $actions = array_merge($fieldActions, $widgetActions);

        return [
            'source' => 'acroform_richtext_xfa_action_state_currentbase_review_boundary',
            'field_name' => $field['name'] ?? null,
            'field_object' => $field['object'] ?? null,
            'field_type' => $field['field_type'] ?? null,
            'field_type_label' => $field['field_type_label'] ?? null,
            'has_current_value' => (bool) ($valueState['has_current_value'] ?? false),
            'has_default_value' => (bool) ($valueState['has_default_value'] ?? false),
            'current' => $valueState['current'] ?? ($field['value'] ?? null),
            'default' => $valueState['default'] ?? ($field['default_value'] ?? null),
            'display_value' => $valueState['display_value'] ?? null,
            'current_source' => $valueState['current_source'] ?? null,
            'current_source_object' => $valueState['current_source_object'] ?? null,
            'default_source' => $valueState['default_source'] ?? null,
            'default_source_object' => $valueState['default_source_object'] ?? null,
            'changed_from_default' => $valueState['changed_from_default'] ?? null,
            'acroform_current_value_authoritative' => true,
            'acroform_default_value_authoritative_for_reset' => true,
            'rich_text_flag' => (bool) ($richTextReview['rich_text_flag'] ?? false),
            'has_rich_text_value' => (bool) ($richTextReview['has_rich_text_value'] ?? false),
            'rich_text_sha256' => $richTextReview['rich_text_sha256'] ?? null,
            'rich_text_plain_preview' => $richTextReview['rich_text_plain_preview'] ?? null,
            'rich_text_source_object' => $richTextReview['source_object'] ?? null,
            'has_default_style' => (bool) ($richTextReview['has_default_style'] ?? false),
            'default_style_source' => $richTextReview['default_style_source'] ?? null,
            'default_style_source_object' => $richTextReview['default_style_source_object'] ?? null,
            'default_style_sha256' => $richTextReview['default_style_sha256'] ?? null,
            'default_style_preview' => $richTextReview['default_style_preview'] ?? null,
            'referenced_by_xfa' => (bool) ($xfaBoundary['referenced_by_xfa'] ?? false),
            'has_xfa_template_reference' => (bool) ($xfaBoundary['has_xfa_template_reference'] ?? false),
            'has_xfa_dataset_reference' => (bool) ($xfaBoundary['has_xfa_dataset_reference'] ?? false),
            'xfa_dynamic_value_present' => (bool) ($xfaBoundary['dynamic_value_present'] ?? false),
            'xfa_packet_indexes' => $xfaBoundary['packet_indexes'] ?? [],
            'xfa_packet_names' => $xfaBoundary['packet_names'] ?? [],
            'xfa_packet_objects' => $xfaBoundary['packet_objects'] ?? [],
            'xfa_matched_field_names' => $xfaBoundary['matched_field_names'] ?? [],
            'xfa_matched_data_paths' => $xfaBoundary['matched_data_paths'] ?? [],
            'xfa_matched_data_value_count' => (int) ($xfaBoundary['matched_data_value_count'] ?? 0),
            'xfa_matched_data_value_previews' => $xfaBoundary['matched_data_value_previews'] ?? [],
            'xfa_matched_data_value_sha256' => $xfaBoundary['matched_data_value_sha256'] ?? [],
            'xfa_value_used_for_current_value' => false,
            'xfa_value_used_for_default_value' => false,
            'xfa_value_used_for_submit' => false,
            'xfa_payload_text_exposed' => false,
            'action_count' => count($actions),
            'unique_action_count' => $this->uniqueActionReviewCount($actions),
            'field_action_count' => count($fieldActions),
            'widget_action_count' => count($widgetActions),
            'action_types' => $this->uniqueScalarValues(array_map(
                static fn (array $action): mixed => $action['action_type'] ?? null,
                $actions
            )),
            'action_triggers' => $this->uniqueScalarValues(array_map(
                static fn (array $action): mixed => $action['trigger'] ?? null,
                $actions
            )),
            'action_trigger_labels' => $this->uniqueScalarValues(array_map(
                static fn (array $action): mixed => $action['trigger_label'] ?? null,
                $actions
            )),
            'action_safety_labels' => $this->actionSafetyLabels($actions),
            'action_objects' => $this->integerValuesFromRows($actions, 'action_object'),
            'action_targets' => $this->actionTargets($actions),
            'submit_targets' => $this->actionTargets($actions, ['SubmitForm']),
            'unsafe_uri_targets' => $this->unsafeUriTargets($actions),
            'action_field_names' => $this->actionFieldNamesFromRows($actions),
            'submit_action_field_names' => $this->actionFieldNames($actions, ['SubmitForm']),
            'reset_action_field_names' => $this->actionFieldNames($actions, ['ResetForm']),
            'javascript_action_count' => $this->actionCountWithType($actions, 'JavaScript'),
            'submit_form_action_count' => $this->actionCountWithType($actions, 'SubmitForm'),
            'reset_form_action_count' => $this->actionCountWithType($actions, 'ResetForm'),
            'import_data_action_count' => $this->actionCountWithType($actions, 'ImportData'),
            'unsafe_uri_action_count' => $this->unsafeUriActionCount($actions),
            'field_value_review_action_count' => count(array_filter(
                $actions,
                static fn (array $action): bool => is_array($action['field_value_review'] ?? null)
            )),
            'widget_count' => count($widgets),
            'page_referenced_widget_count' => $pageReferencedWidgetCount,
            'widget_objects' => array_values(array_unique($widgetObjects)),
            'appearance_states' => $this->uniqueScalarValues($appearanceStates),
            'selected_appearance_objects' => array_values(array_unique($selectedAppearanceObjects)),
            'stale_appearance_state_count' => $staleAppearanceStateCount,
            'rich_text_used_for_import' => false,
            'rich_text_used_for_submit' => false,
            'rich_text_used_for_reset' => false,
            'default_style_used_for_import' => false,
            'default_style_exposed_as_css' => false,
            'appearance_value_used_for_import' => false,
            'payload_text_exposed' => false,
            'imports_xfa_payload' => false,
            'executes_xfa_javascript' => false,
            'executes_action' => false,
            'executes_javascript' => false,
            'executes_appearance_streams' => false,
            'renders_appearances' => false,
            'executes_python_or_models' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function signatureWidgetReview(array $field): array
    {
        $widgets = array_values(array_filter(
            $field['widgets'] ?? [],
            static fn (mixed $widget): bool => is_array($widget)
        ));
        $pageWidgets = array_values(array_filter(
            $widgets,
            static fn (array $widget): bool => ($widget['referenced_from_page_annots'] ?? false) === true
        ));
        $primaryWidget = $pageWidgets[0] ?? ($widgets[0] ?? null);
        $normalAppearance = is_array($primaryWidget['normal_appearance'] ?? null) ? $primaryWidget['normal_appearance'] : null;
        $selectedAppearance = is_array($normalAppearance['selected_appearance'] ?? null) ? $normalAppearance['selected_appearance'] : null;
        $rolloverAppearance = is_array($primaryWidget['rollover_appearance'] ?? null) ? $primaryWidget['rollover_appearance'] : null;
        $rolloverSelectedAppearance = is_array($rolloverAppearance['selected_appearance'] ?? null) ? $rolloverAppearance['selected_appearance'] : null;
        $downAppearance = is_array($primaryWidget['down_appearance'] ?? null) ? $primaryWidget['down_appearance'] : null;
        $downSelectedAppearance = is_array($downAppearance['selected_appearance'] ?? null) ? $downAppearance['selected_appearance'] : null;
        $signature = is_array($field['signature'] ?? null) ? $field['signature'] : [];
        $signatureState = is_array($field['signature_state'] ?? null) ? $field['signature_state'] : [];
        $xfaBoundary = is_array($field['xfa_boundary'] ?? null) ? $field['xfa_boundary'] : [];
        $seedValue = is_array($field['signature_seed_value'] ?? null) ? $field['signature_seed_value'] : null;
        $lock = is_array($field['signature_lock'] ?? null) ? $field['signature_lock'] : null;
        $lockState = is_array($field['signature_lock_state'] ?? null) ? $field['signature_lock_state'] : [];

        $fieldActions = array_values(array_filter($field['actions'] ?? [], static fn (mixed $action): bool => is_array($action)));
        $widgetActionRows = [];
        foreach ($widgets as $widget) {
            foreach ($widget['actions'] ?? [] as $action) {
                if (is_array($action)) {
                    $widgetActionRows[] = $action;
                }
            }
        }

        $appearanceStates = [];
        $selectedAppearanceObjects = [];
        foreach ($widgets as $widget) {
            foreach ($widget['appearance_states'] ?? [] as $state) {
                if (is_string($state) && $state !== '' && !in_array($state, $appearanceStates, true)) {
                    $appearanceStates[] = $state;
                }
            }

            $appearance = is_array($widget['normal_appearance'] ?? null) ? $widget['normal_appearance'] : null;
            $selected = is_array($appearance['selected_appearance'] ?? null) ? $appearance['selected_appearance'] : null;
            $selectedObject = $selected['object'] ?? null;
            if (is_int($selectedObject) && !in_array($selectedObject, $selectedAppearanceObjects, true)) {
                $selectedAppearanceObjects[] = $selectedObject;
            }
        }

        $actionReview = $this->signatureWidgetActionReview($field, $widgets, $fieldActions, $widgetActionRows);
        $actionBundle = $this->signatureWidgetActionBundleReview($field, $widgets, $fieldActions, $widgetActionRows, $actionReview);
        $lockResourceReview = $this->signatureWidgetLockResourceReview($field, $widgets, $primaryWidget, [
            ['mode' => 'normal', 'appearance' => $normalAppearance],
            ['mode' => 'rollover', 'appearance' => $rolloverAppearance],
            ['mode' => 'down', 'appearance' => $downAppearance],
        ]);

        return [
            'source' => 'acroform_xfa_signature_widget_review_boundary',
            'field_name' => $field['name'] ?? null,
            'field_object' => $field['object'] ?? null,
            'widget_count' => count($widgets),
            'page_referenced_widget_count' => count($pageWidgets),
            'widget_objects' => $this->integerValuesFromRows($widgets, 'object'),
            'page_widget_objects' => $this->integerValuesFromRows($pageWidgets, 'object'),
            'primary_widget_object' => is_array($primaryWidget) ? ($primaryWidget['object'] ?? null) : null,
            'primary_widget_page_index' => is_array($primaryWidget) ? ($primaryWidget['page_index'] ?? null) : null,
            'primary_widget_page_object' => is_array($primaryWidget) ? ($primaryWidget['page_object'] ?? null) : null,
            'primary_widget_page_annotation_index' => is_array($primaryWidget) ? ($primaryWidget['page_annotation_index'] ?? null) : null,
            'primary_widget_referenced_from_page_annots' => is_array($primaryWidget) && ($primaryWidget['referenced_from_page_annots'] ?? false) === true,
            'primary_widget_rect' => is_array($primaryWidget) ? ($primaryWidget['rect'] ?? null) : null,
            'primary_widget_visibility' => is_array($primaryWidget) ? ($primaryWidget['annotation_visibility'] ?? null) : null,
            'primary_widget_flag_names' => is_array($primaryWidget) ? ($primaryWidget['annotation_flag_names'] ?? []) : [],
            'primary_widget_printable' => is_array($primaryWidget) && ($primaryWidget['printable'] ?? false) === true,
            'primary_widget_hidden' => is_array($primaryWidget) && ($primaryWidget['hidden'] ?? false) === true,
            'primary_widget_no_view' => is_array($primaryWidget) && ($primaryWidget['no_view'] ?? false) === true,
            'visible_widget_count' => count(array_filter($widgets, static fn (array $widget): bool => ($widget['visible'] ?? false) === true)),
            'hidden_widget_count' => count(array_filter($widgets, static fn (array $widget): bool => ($widget['hidden'] ?? false) === true)),
            'printable_widget_count' => count(array_filter($widgets, static fn (array $widget): bool => ($widget['printable'] ?? false) === true)),
            'appearance_state' => is_array($primaryWidget) ? ($primaryWidget['appearance_state'] ?? null) : null,
            'appearance_states' => $appearanceStates,
            'state_matches_appearance' => is_array($normalAppearance) ? ($normalAppearance['state_matches_appearance'] ?? null) : null,
            'stale_appearance_state' => is_array($normalAppearance) ? (bool) ($normalAppearance['stale_appearance_state'] ?? false) : false,
            'selected_appearance_state' => is_array($normalAppearance) ? ($normalAppearance['selected_state'] ?? null) : null,
            'selected_appearance_object' => is_array($selectedAppearance) ? ($selectedAppearance['object'] ?? null) : null,
            'selected_appearance_objects' => $selectedAppearanceObjects,
            'selected_appearance_decoded_sha256' => is_array($selectedAppearance) ? ($selectedAppearance['decoded_sha256'] ?? null) : null,
            'appearance_value_used_for_import' => false,
            'appearance_payload_text_exposed' => false,
            'rollover_appearance_type' => is_array($rolloverAppearance) ? ($rolloverAppearance['appearance_type'] ?? null) : null,
            'rollover_selected_appearance_state' => is_array($rolloverAppearance) ? ($rolloverAppearance['selected_state'] ?? null) : null,
            'rollover_selected_appearance_object' => is_array($rolloverSelectedAppearance) ? ($rolloverSelectedAppearance['object'] ?? null) : null,
            'rollover_selected_appearance_decoded_sha256' => is_array($rolloverSelectedAppearance) ? ($rolloverSelectedAppearance['decoded_sha256'] ?? null) : null,
            'rollover_state_matches_appearance' => is_array($rolloverAppearance) ? ($rolloverAppearance['state_matches_appearance'] ?? null) : null,
            'down_appearance_type' => is_array($downAppearance) ? ($downAppearance['appearance_type'] ?? null) : null,
            'down_selected_appearance_state' => is_array($downAppearance) ? ($downAppearance['selected_state'] ?? null) : null,
            'down_selected_appearance_object' => is_array($downSelectedAppearance) ? ($downSelectedAppearance['object'] ?? null) : null,
            'down_selected_appearance_decoded_sha256' => is_array($downSelectedAppearance) ? ($downSelectedAppearance['decoded_sha256'] ?? null) : null,
            'down_state_matches_appearance' => is_array($downAppearance) ? ($downAppearance['state_matches_appearance'] ?? null) : null,
            'interactive_appearance_value_used_for_import' => false,
            'interactive_appearance_payload_text_exposed' => false,
            'has_signature_dictionary' => (bool) ($signatureState['has_signature_dictionary'] ?? false),
            'signed' => (bool) ($signatureState['signed'] ?? false),
            'signature_object' => $signatureState['signature_object'] ?? ($signature['object'] ?? null),
            'signature_name' => $signature['name'] ?? null,
            'signature_reason' => $signature['reason'] ?? null,
            'signed_at' => $signatureState['signed_at'] ?? ($signature['signed_at'] ?? null),
            'byte_range' => $signatureState['byte_range'] ?? ($signature['byte_range'] ?? null),
            'byte_range_segment_count' => $signatureState['byte_range_segment_count'] ?? 0,
            'contents_present' => (bool) ($signatureState['contents_present'] ?? false),
            'contents_length_bytes' => $signatureState['contents_length_bytes'] ?? ($signature['contents_length_bytes'] ?? null),
            'certifying_signature' => (bool) ($signatureState['certifying_signature'] ?? false),
            'signature_dictionary_is_field_value' => $signature !== [],
            'xfa_referenced' => (bool) ($xfaBoundary['referenced_by_xfa'] ?? false),
            'xfa_dynamic_value_present' => (bool) ($xfaBoundary['dynamic_value_present'] ?? false),
            'xfa_packet_names' => $xfaBoundary['packet_names'] ?? [],
            'xfa_packet_objects' => $xfaBoundary['packet_objects'] ?? [],
            'xfa_matched_field_names' => $xfaBoundary['matched_field_names'] ?? [],
            'xfa_matched_data_paths' => $xfaBoundary['matched_data_paths'] ?? [],
            'xfa_value_used_for_signature' => false,
            'xfa_value_used_for_import' => false,
            'xfa_payload_text_exposed' => false,
            'seed_value_object' => is_array($seedValue) ? ($seedValue['object'] ?? null) : null,
            'seed_value_required_constraints' => is_array($seedValue) ? ($seedValue['required_constraints'] ?? []) : [],
            'seed_value_filter' => is_array($seedValue) ? ($seedValue['filter'] ?? null) : null,
            'seed_value_subfilters' => is_array($seedValue) ? ($seedValue['subfilters'] ?? []) : [],
            'seed_value_digest_methods' => is_array($seedValue) ? ($seedValue['digest_methods'] ?? []) : [],
            'lock_object' => is_array($lock) ? ($lock['object'] ?? null) : null,
            'lock_action' => is_array($lock) ? ($lock['action'] ?? null) : null,
            'lock_action_label' => is_array($lock) ? ($lock['action_label'] ?? null) : null,
            'lock_field_names' => is_array($lock) ? ($lock['field_names'] ?? []) : [],
            'lock_permission_label' => is_array($lock) ? ($lock['permission_label'] ?? null) : null,
            'field_locked_by_signed_signature' => (bool) ($lockState['effective_locked'] ?? false),
            'locked_by_signatures' => $lockState['locked_by_signatures'] ?? [],
            'action_review' => $actionReview,
            'action_bundle' => $actionBundle,
            'lock_resource_review' => $lockResourceReview,
            'field_action_count' => $actionReview['field_action_count'],
            'widget_action_count' => $actionReview['widget_action_count'],
            'action_count' => $actionReview['action_count'],
            'action_types' => $actionReview['action_types'],
            'action_triggers' => $actionReview['action_triggers'],
            'action_safety_labels' => $actionReview['action_safety_labels'],
            'review_only' => true,
            'value_used_for_import' => false,
            'executes_action' => false,
            'executes_javascript' => false,
            'executes_appearance_streams' => false,
            'renders_appearances' => false,
            'executes_signature_validation' => false,
            'executes_signing' => false,
            'executes_xfa_javascript' => false,
            'imports_xfa_payload' => false,
        ];
    }

    /**
     * @param list<array<string, mixed>> $widgets
     * @param list<array{mode: string, appearance: array<string, mixed>|null}> $appearanceReviews
     * @return array<string, mixed>
     */
    private function signatureWidgetLockResourceReview(array $field, array $widgets, ?array $primaryWidget, array $appearanceReviews): array
    {
        $signatureState = is_array($field['signature_state'] ?? null) ? $field['signature_state'] : [];
        $lock = is_array($field['signature_lock'] ?? null) ? $field['signature_lock'] : null;
        $lockState = is_array($field['signature_lock_state'] ?? null) ? $field['signature_lock_state'] : [];
        $appearanceRows = $this->signatureWidgetSelectedAppearanceResourceRows($appearanceReviews);
        $widgetRows = $this->signatureWidgetResourceRows($widgets);

        $fontNames = [];
        $xobjectNames = [];
        $xobjectActionTypes = [];
        $xobjectActionObjects = [];
        $xobjectActionCount = 0;
        foreach ($appearanceRows as $row) {
            foreach ($this->stringListValue($row['resource_font_names'] ?? []) as $name) {
                $this->appendUniqueString($fontNames, $name);
            }
            foreach ($this->stringListValue($row['resource_xobject_names'] ?? []) as $name) {
                $this->appendUniqueString($xobjectNames, $name);
            }
            foreach ($this->stringListValue($row['resource_xobject_action_types'] ?? []) as $type) {
                $this->appendUniqueString($xobjectActionTypes, $type);
            }
            foreach ($this->integerListValue($row['resource_xobject_action_objects'] ?? []) as $objectNumber) {
                if (!in_array($objectNumber, $xobjectActionObjects, true)) {
                    $xobjectActionObjects[] = $objectNumber;
                }
            }
            $xobjectActionCount += (int) ($row['resource_xobject_action_count'] ?? 0);
        }

        return [
            'source' => 'acroform_signature_widget_lock_resource_currentbase_review_boundary',
            'field_name' => $field['name'] ?? null,
            'field_object' => $field['object'] ?? null,
            'field_type' => $field['field_type'] ?? null,
            'signed' => (bool) ($signatureState['signed'] ?? false),
            'signature_object' => $signatureState['signature_object'] ?? null,
            'signature_byte_range_segment_count' => (int) ($signatureState['byte_range_segment_count'] ?? 0),
            'lock_present' => $lock !== null,
            'lock_object' => $lock['object'] ?? null,
            'lock_action' => $lock['action'] ?? null,
            'lock_action_label' => $lock['action_label'] ?? null,
            'lock_action_valid' => (bool) ($lock['action_valid'] ?? false),
            'lock_field_names' => is_array($lock['field_names'] ?? null) ? $lock['field_names'] : [],
            'lock_field_count' => is_array($lock['field_names'] ?? null) ? count($lock['field_names']) : 0,
            'lock_permission_level' => $lock['permission_level'] ?? null,
            'lock_permission_label' => $lock['permission_label'] ?? null,
            'lock_allowed_changes' => is_array($lock['allowed_changes'] ?? null) ? $lock['allowed_changes'] : [],
            'lock_applies_after_signing' => (bool) ($signatureState['signed'] ?? false) && $lock !== null && ($lock['action_valid'] ?? false) === true,
            'field_locked_by_signed_signature' => (bool) ($lockState['effective_locked'] ?? false),
            'locked_by_signatures' => $lockState['locked_by_signatures'] ?? [],
            'widget_count' => count($widgets),
            'page_referenced_widget_count' => count(array_filter(
                $widgets,
                static fn (array $widget): bool => ($widget['referenced_from_page_annots'] ?? false) === true
            )),
            'primary_widget_object' => is_array($primaryWidget) ? ($primaryWidget['object'] ?? null) : null,
            'primary_widget_page_object' => is_array($primaryWidget) ? ($primaryWidget['page_object'] ?? null) : null,
            'primary_widget_page_index' => is_array($primaryWidget) ? ($primaryWidget['page_index'] ?? null) : null,
            'primary_widget_page_annotation_index' => is_array($primaryWidget) ? ($primaryWidget['page_annotation_index'] ?? null) : null,
            'primary_widget_appearance_state' => is_array($primaryWidget) ? ($primaryWidget['appearance_state'] ?? null) : null,
            'widget_resource_rows' => $widgetRows,
            'selected_appearance_resource_rows' => $appearanceRows,
            'selected_appearance_modes' => $this->stringListValue(array_map(
                static fn (array $row): mixed => $row['appearance_mode'] ?? null,
                $appearanceRows
            )),
            'selected_appearance_objects' => $this->integerValuesFromRows($appearanceRows, 'appearance_object'),
            'appearance_resource_objects' => $this->integerValuesFromRows($appearanceRows, 'resource_object'),
            'appearance_resource_font_names' => $fontNames,
            'appearance_resource_xobject_names' => $xobjectNames,
            'appearance_resource_xobject_action_count' => $xobjectActionCount,
            'appearance_resource_xobject_action_types' => $xobjectActionTypes,
            'appearance_resource_xobject_action_objects' => $xobjectActionObjects,
            'signature_locks_enforced_on_import' => false,
            'appearance_resources_used_for_import' => false,
            'appearance_resource_payload_text_exposed' => false,
            'field_value_used_for_import' => false,
            'review_only' => true,
            'executes_action' => false,
            'executes_javascript' => false,
            'executes_appearance_streams' => false,
            'renders_appearances' => false,
            'executes_signature_validation' => false,
            'executes_signing' => false,
            'executes_python_or_models' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * @param list<array{mode: string, appearance: array<string, mixed>|null}> $appearanceReviews
     * @return list<array<string, mixed>>
     */
    private function signatureWidgetSelectedAppearanceResourceRows(array $appearanceReviews): array
    {
        $rows = [];
        foreach ($appearanceReviews as $entry) {
            $appearance = is_array($entry['appearance'] ?? null) ? $entry['appearance'] : null;
            $selected = is_array($appearance['selected_appearance'] ?? null) ? $appearance['selected_appearance'] : null;
            if ($appearance === null || $selected === null) {
                continue;
            }

            $rows[] = [
                'source' => 'acroform_signature_widget_selected_appearance_resource_currentbase',
                'appearance_mode' => $entry['mode'],
                'appearance_dictionary_object' => $appearance['appearance_dictionary_object'] ?? null,
                'appearance_dictionary_object_for_mode' => $appearance['appearance_dictionary_object_for_mode'] ?? null,
                'appearance_key' => $appearance['appearance_key'] ?? null,
                'appearance_type' => $appearance['normal_appearance_type'] ?? ($appearance['appearance_type'] ?? null),
                'appearance_state' => $appearance['appearance_state'] ?? null,
                'selected_state' => $appearance['selected_state'] ?? null,
                'appearance_object' => $selected['object'] ?? null,
                'appearance_source' => $selected['source'] ?? null,
                'bbox' => $selected['bbox'] ?? null,
                'matrix' => $selected['matrix'] ?? null,
                'filters' => $selected['filters'] ?? [],
                'decoded_stream_available' => (bool) ($selected['decoded_stream_available'] ?? false),
                'decoded_length_bytes' => $selected['decoded_length_bytes'] ?? null,
                'decoded_sha256' => $selected['decoded_sha256'] ?? null,
                'resource_object' => $selected['resource_object'] ?? null,
                'resource_font_names' => $selected['resource_font_names'] ?? [],
                'resource_xobject_names' => $selected['resource_xobject_names'] ?? [],
                'resource_xobject_reviews' => $selected['resource_xobject_reviews'] ?? [],
                'resource_xobject_action_count' => (int) ($selected['resource_xobject_action_count'] ?? 0),
                'resource_xobject_action_types' => $selected['resource_xobject_action_types'] ?? [],
                'resource_xobject_action_objects' => $selected['resource_xobject_action_objects'] ?? [],
                'resource_xobject_payload_text_exposed' => false,
                'appearance_value_used_for_import' => false,
                'payload_text_exposed' => false,
                'executes_action' => false,
                'executes_javascript' => false,
                'executes_appearance_streams' => false,
                'renders_appearances' => false,
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $widgets
     * @return list<array<string, mixed>>
     */
    private function signatureWidgetResourceRows(array $widgets): array
    {
        $rows = [];
        foreach ($widgets as $widget) {
            $appearance = is_array($widget['normal_appearance'] ?? null) ? $widget['normal_appearance'] : null;
            $selected = is_array($appearance['selected_appearance'] ?? null) ? $appearance['selected_appearance'] : null;
            $rows[] = [
                'source' => 'acroform_signature_widget_resource_currentbase',
                'widget_object' => $widget['object'] ?? null,
                'referenced_from_page_annots' => (bool) ($widget['referenced_from_page_annots'] ?? false),
                'page_object' => $widget['page_object'] ?? null,
                'page_index' => $widget['page_index'] ?? null,
                'page_annotation_index' => $widget['page_annotation_index'] ?? null,
                'appearance_state' => $widget['appearance_state'] ?? null,
                'appearance_states' => $widget['appearance_states'] ?? [],
                'selected_appearance_object' => is_array($selected) ? ($selected['object'] ?? null) : null,
                'resource_object' => is_array($selected) ? ($selected['resource_object'] ?? null) : null,
                'resource_font_names' => is_array($selected) ? ($selected['resource_font_names'] ?? []) : [],
                'resource_xobject_names' => is_array($selected) ? ($selected['resource_xobject_names'] ?? []) : [],
                'resource_xobject_action_count' => is_array($selected) ? (int) ($selected['resource_xobject_action_count'] ?? 0) : 0,
                'resource_xobject_action_types' => is_array($selected) ? ($selected['resource_xobject_action_types'] ?? []) : [],
                'resource_xobject_action_objects' => is_array($selected) ? ($selected['resource_xobject_action_objects'] ?? []) : [],
                'appearance_value_used_for_import' => false,
                'payload_text_exposed' => false,
                'executes_action' => false,
                'executes_javascript' => false,
                'executes_appearance_streams' => false,
                'renders_appearances' => false,
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $widgets
     * @param list<array<string, mixed>> $fieldActions
     * @param list<array<string, mixed>> $widgetActions
     * @return array<string, mixed>
     */
    private function signatureWidgetActionBundleReview(
        array $field,
        array $widgets,
        array $fieldActions,
        array $widgetActions,
        array $actionReview
    ): array {
        $actions = array_merge($fieldActions, $widgetActions);
        $fieldObject = is_int($field['object'] ?? null) ? $field['object'] : null;
        $fieldHierarchy = is_array($field['field_hierarchy'] ?? null) ? $field['field_hierarchy'] : [];
        $valueState = is_array($field['value_state'] ?? null) ? $field['value_state'] : [];
        $signatureState = is_array($field['signature_state'] ?? null) ? $field['signature_state'] : [];
        $xfaBoundary = is_array($field['xfa_boundary'] ?? null) ? $field['xfa_boundary'] : [];
        $seedLockReview = is_array($field['signature_seed_lock_action_review'] ?? null)
            ? $field['signature_seed_lock_action_review']
            : [];
        $defaultAppearance = is_array($field['default_appearance'] ?? null) ? $field['default_appearance'] : null;
        $widgetRows = $this->signatureWidgetBundleRows($widgets, $fieldObject);
        $pageWidgetRows = array_values(array_filter(
            $widgetRows,
            static fn (array $row): bool => ($row['referenced_from_page_annots'] ?? false) === true
        ));
        $mixedRows = array_values(array_filter(
            $widgetRows,
            static fn (array $row): bool => ($row['mixed_field_widget_dictionary'] ?? false) === true
        ));
        $duplicateKeys = $this->duplicateMixedFieldWidgetActionKeys($fieldActions, $widgetActions);

        return [
            'source' => 'acroform_signature_xfa_widget_action_bundle_currentbase',
            'field_name' => $field['name'] ?? null,
            'field_object' => $fieldObject,
            'field_type' => $field['field_type'] ?? null,
            'signed' => (bool) ($signatureState['signed'] ?? false),
            'signature_object' => $signatureState['signature_object'] ?? null,
            'signature_byte_range_segment_count' => (int) ($signatureState['byte_range_segment_count'] ?? 0),
            'xfa_referenced' => (bool) ($xfaBoundary['referenced_by_xfa'] ?? false),
            'xfa_dynamic_value_present' => (bool) ($xfaBoundary['dynamic_value_present'] ?? false),
            'xfa_packet_names' => $xfaBoundary['packet_names'] ?? [],
            'xfa_packet_objects' => $xfaBoundary['packet_objects'] ?? [],
            'xfa_matched_field_names' => $xfaBoundary['matched_field_names'] ?? [],
            'xfa_matched_data_paths' => $xfaBoundary['matched_data_paths'] ?? [],
            'current_value_source_object' => $valueState['current_source_object'] ?? null,
            'current_value_inherited' => (bool) ($valueState['hierarchy_boundary']['current_value_inherited'] ?? false),
            'field_hierarchy_depth' => (int) ($fieldHierarchy['depth'] ?? 0),
            'inherited_field_attributes' => $fieldHierarchy['inherited_attributes'] ?? [],
            'local_field_attributes' => $fieldHierarchy['local_attributes'] ?? [],
            'field_default_appearance_source' => $defaultAppearance['source'] ?? null,
            'field_default_appearance_source_object' => $defaultAppearance['source_object'] ?? null,
            'field_default_font_resource' => $defaultAppearance['font_resource'] ?? null,
            'field_default_font_resource_resolved' => (bool) ($defaultAppearance['font_resource_resolved'] ?? false),
            'field_default_font_resource_object' => $defaultAppearance['font_resource_object'] ?? null,
            'field_default_resource_source' => $defaultAppearance['default_resource_source'] ?? null,
            'field_default_resource_source_object' => $defaultAppearance['default_resource_source_object'] ?? null,
            'widget_count' => count($widgets),
            'page_referenced_widget_count' => count($pageWidgetRows),
            'mixed_field_widget_count' => count($mixedRows),
            'widget_order_objects' => $this->integerValuesFromRows($widgetRows, 'widget_object'),
            'page_annotation_order_objects' => $this->integerValuesFromRows($pageWidgetRows, 'widget_object'),
            'mixed_field_widget_objects' => $this->integerValuesFromRows($mixedRows, 'widget_object'),
            'visible_widget_objects' => $this->integerValuesFromRows(array_filter(
                $widgetRows,
                static fn (array $row): bool => ($row['visible'] ?? false) === true
            ), 'widget_object'),
            'hidden_widget_objects' => $this->integerValuesFromRows(array_filter(
                $widgetRows,
                static fn (array $row): bool => ($row['hidden'] ?? false) === true
            ), 'widget_object'),
            'printable_widget_objects' => $this->integerValuesFromRows(array_filter(
                $widgetRows,
                static fn (array $row): bool => ($row['printable'] ?? false) === true
            ), 'widget_object'),
            'widgets' => $widgetRows,
            'action_count' => $actionReview['action_count'] ?? $this->uniqueActionReviewCount($actions),
            'action_review_row_count' => count($actions),
            'field_action_count' => count($fieldActions),
            'widget_action_count' => count($widgetActions),
            'chained_action_count' => $this->actionCountWithFlag($actions, 'chained'),
            'duplicate_mixed_field_widget_action_count' => count($duplicateKeys),
            'duplicate_mixed_field_widget_action_keys' => $duplicateKeys,
            'action_types' => $actionReview['action_types'] ?? $this->uniqueScalarValues(array_map(
                static fn (array $action): mixed => $action['action_type'] ?? null,
                $actions
            )),
            'action_triggers' => $actionReview['action_triggers'] ?? $this->uniqueScalarValues(array_map(
                static fn (array $action): mixed => $action['trigger'] ?? null,
                $actions
            )),
            'action_sources' => $actionReview['action_sources'] ?? $this->uniqueScalarValues(array_map(
                static fn (array $action): mixed => $action['source'] ?? null,
                $actions
            )),
            'action_objects' => $this->integerValuesFromRows($actions, 'action_object'),
            'field_action_objects' => $this->integerValuesFromRows($fieldActions, 'action_object'),
            'widget_action_objects' => $this->integerValuesFromRows($widgetActions, 'action_object'),
            'action_rows' => $this->signatureWidgetBundleActionRows($actions),
            'submit_targets' => $this->actionTargets($actions, ['SubmitForm']),
            'unsafe_uri_targets' => $this->unsafeUriTargets($actions),
            'form_action_field_names' => $this->actionFieldNames($actions, ['SubmitForm', 'ResetForm']),
            'hide_field_names' => $this->actionFieldNames($actions, ['Hide']),
            'locked_action_field_names' => $seedLockReview['locked_action_field_names'] ?? [],
            'locked_submit_field_names' => $seedLockReview['locked_submit_field_names'] ?? [],
            'locked_reset_field_names' => $seedLockReview['locked_reset_field_names'] ?? [],
            'locked_hide_field_names' => $seedLockReview['locked_hide_field_names'] ?? [],
            'review_only' => true,
            'field_values_authoritative' => true,
            'page_annotation_order_authoritative' => true,
            'appearance_value_used_for_import' => false,
            'appearance_payload_text_exposed' => false,
            'xfa_value_used_for_signature' => false,
            'xfa_payload_text_exposed' => false,
            'imports_xfa_payload' => false,
            'executes_action' => false,
            'executes_javascript' => false,
            'submits_form_data' => false,
            'resets_form_values' => false,
            'changes_widget_visibility' => false,
            'executes_appearance_streams' => false,
            'renders_appearances' => false,
            'executes_signature_validation' => false,
            'executes_signing' => false,
            'executes_xfa_javascript' => false,
            'executes_python_or_models' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * @param list<array<string, mixed>> $widgets
     * @return list<array<string, mixed>>
     */
    private function signatureWidgetBundleRows(array $widgets, ?int $fieldObject): array
    {
        $rows = [];
        foreach ($widgets as $widget) {
            $appearance = is_array($widget['normal_appearance'] ?? null) ? $widget['normal_appearance'] : null;
            $selected = is_array($appearance['selected_appearance'] ?? null) ? $appearance['selected_appearance'] : null;
            $defaultAppearance = is_array($widget['default_appearance'] ?? null) ? $widget['default_appearance'] : null;
            $actions = $this->arrayRows($widget['actions'] ?? []);
            $widgetObject = is_int($widget['object'] ?? null) ? $widget['object'] : null;

            $rows[] = [
                'source' => 'acroform_signature_widget_bundle_widget_currentbase',
                'widget_object' => $widgetObject,
                'page_index' => $widget['page_index'] ?? null,
                'page_object' => $widget['page_object'] ?? null,
                'page_annotation_index' => $widget['page_annotation_index'] ?? null,
                'referenced_from_page_annots' => (bool) ($widget['referenced_from_page_annots'] ?? false),
                'mixed_field_widget_dictionary' => $fieldObject !== null && $widgetObject === $fieldObject,
                'rect' => $widget['rect'] ?? null,
                'visible' => (bool) ($widget['visible'] ?? false),
                'hidden' => (bool) ($widget['hidden'] ?? false),
                'printable' => (bool) ($widget['printable'] ?? false),
                'annotation_visibility' => $widget['annotation_visibility'] ?? null,
                'annotation_flag_names' => $widget['annotation_flag_names'] ?? [],
                'appearance_state' => $widget['appearance_state'] ?? null,
                'appearance_states' => $widget['appearance_states'] ?? [],
                'selected_appearance_state' => is_array($appearance) ? ($appearance['selected_state'] ?? null) : null,
                'selected_appearance_object' => is_array($selected) ? ($selected['object'] ?? null) : null,
                'state_matches_appearance' => is_array($appearance) ? ($appearance['state_matches_appearance'] ?? null) : null,
                'stale_appearance_state' => is_array($appearance) ? (bool) ($appearance['stale_appearance_state'] ?? false) : false,
                'default_appearance_source' => $defaultAppearance['source'] ?? null,
                'default_appearance_source_object' => $defaultAppearance['source_object'] ?? null,
                'default_font_resource' => $defaultAppearance['font_resource'] ?? null,
                'default_font_resource_resolved' => (bool) ($defaultAppearance['font_resource_resolved'] ?? false),
                'default_font_resource_object' => $defaultAppearance['font_resource_object'] ?? null,
                'default_resource_source' => $defaultAppearance['default_resource_source'] ?? null,
                'default_resource_source_object' => $defaultAppearance['default_resource_source_object'] ?? null,
                'action_count' => count($actions),
                'action_objects' => $this->integerValuesFromRows($actions, 'action_object'),
                'action_types' => $this->uniqueScalarValues(array_map(
                    static fn (array $action): mixed => $action['action_type'] ?? null,
                    $actions
                )),
                'action_triggers' => $this->uniqueScalarValues(array_map(
                    static fn (array $action): mixed => $action['trigger'] ?? null,
                    $actions
                )),
                'review_only' => true,
                'appearance_value_used_for_import' => false,
                'appearance_payload_text_exposed' => false,
                'executes_action' => false,
                'executes_javascript' => false,
                'executes_appearance_streams' => false,
                'renders_appearances' => false,
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @return list<array<string, mixed>>
     */
    private function signatureWidgetBundleActionRows(array $actions): array
    {
        $rows = [];
        foreach ($actions as $action) {
            $rows[] = [
                'source' => $action['source'] ?? null,
                'source_object' => $action['source_object'] ?? null,
                'trigger' => $action['trigger'] ?? null,
                'trigger_label' => $action['trigger_label'] ?? null,
                'action_type' => $action['action_type'] ?? null,
                'action_object' => $action['action_object'] ?? null,
                'safety' => $this->actionSafetyLabel($action),
                'target' => $action['target'] ?? ($action['uri'] ?? ($action['file'] ?? null)),
                'target_scheme' => $action['target_scheme'] ?? null,
                'field_names' => is_array($action['field_names'] ?? null) ? $action['field_names'] : [],
                'chained' => (bool) ($action['chained'] ?? false),
                'executes_action' => false,
                'executes_javascript' => false,
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $fieldActions
     * @param list<array<string, mixed>> $widgetActions
     * @return list<string>
     */
    private function duplicateMixedFieldWidgetActionKeys(array $fieldActions, array $widgetActions): array
    {
        $fieldKeys = [];
        foreach ($fieldActions as $action) {
            $fieldKeys[$this->fieldWidgetActionBundleKey($action)] = true;
        }

        $duplicates = [];
        foreach ($widgetActions as $action) {
            $key = $this->fieldWidgetActionBundleKey($action);
            if (isset($fieldKeys[$key]) && !in_array($key, $duplicates, true)) {
                $duplicates[] = $key;
            }
        }

        return $duplicates;
    }

    /**
     * @param array<string, mixed> $action
     */
    private function fieldWidgetActionBundleKey(array $action): string
    {
        $actionObject = $action['action_object'] ?? null;
        $actionIdentity = is_int($actionObject)
            ? 'object:' . $actionObject
            : 'inline:' . hash('sha256', serialize([
                $action['action_type'] ?? null,
                $action['target'] ?? null,
                $action['uri'] ?? null,
                $action['file'] ?? null,
                $action['field_names'] ?? [],
            ]));

        return implode('|', [
            (string) ($action['source_object'] ?? ''),
            (string) ($action['trigger'] ?? ''),
            (string) ($action['action_type'] ?? ''),
            $actionIdentity,
        ]);
    }

    /**
     * @param list<array<string, mixed>> $widgets
     * @param list<array<string, mixed>> $fieldActions
     * @param list<array<string, mixed>> $widgetActions
     * @return array<string, mixed>
     */
    private function signatureWidgetActionReview(array $field, array $widgets, array $fieldActions, array $widgetActions): array
    {
        $actions = array_merge($fieldActions, $widgetActions);
        $chainReviews = [];
        if (is_array($field['action_review'] ?? null)) {
            $chainReviews[] = $field['action_review'];
        }

        foreach ($widgets as $widget) {
            if (is_array($widget['action_review'] ?? null)) {
                $chainReviews[] = $widget['action_review'];
            }
        }

        return [
            'source' => 'acroform_xfa_signature_widget_action_review_boundary',
            'action_count' => $this->uniqueActionReviewCount($actions),
            'action_review_row_count' => count($actions),
            'field_action_count' => count($fieldActions),
            'widget_action_count' => count($widgetActions),
            'chained_action_count' => $this->actionCountWithFlag($actions, 'chained'),
            'field_chained_action_count' => $this->actionCountWithFlag($fieldActions, 'chained'),
            'widget_chained_action_count' => $this->actionCountWithFlag($widgetActions, 'chained'),
            'action_types' => $this->uniqueScalarValues(array_map(
                static fn (array $action): mixed => $action['action_type'] ?? null,
                $actions
            )),
            'action_triggers' => $this->uniqueScalarValues(array_map(
                static fn (array $action): mixed => $action['trigger'] ?? null,
                $actions
            )),
            'action_trigger_labels' => $this->uniqueScalarValues(array_map(
                static fn (array $action): mixed => $action['trigger_label'] ?? null,
                $actions
            )),
            'action_sources' => $this->uniqueScalarValues(array_map(
                static fn (array $action): mixed => $action['source'] ?? null,
                $actions
            )),
            'action_source_objects' => $this->integerValuesFromRows($actions, 'source_object'),
            'action_objects' => $this->integerValuesFromRows($actions, 'action_object'),
            'action_safety_labels' => $this->uniqueScalarValues(array_map(
                static fn (array $action): mixed => $action['safety'] ?? null,
                $actions
            )),
            'action_targets' => $this->actionTargets($actions),
            'action_target_schemes' => $this->actionTargetSchemes($actions),
            'submit_targets' => $this->actionTargets($actions, ['SubmitForm']),
            'unsafe_uri_targets' => $this->unsafeUriTargets($actions),
            'form_action_field_names' => $this->actionFieldNames($actions, ['SubmitForm', 'ResetForm']),
            'hide_field_names' => $this->actionFieldNames($actions, ['Hide']),
            'javascript_action_count' => $this->actionCountWithType($actions, 'JavaScript'),
            'submit_form_action_count' => $this->actionCountWithType($actions, 'SubmitForm'),
            'reset_form_action_count' => $this->actionCountWithType($actions, 'ResetForm'),
            'import_data_action_count' => $this->actionCountWithType($actions, 'ImportData'),
            'hide_action_count' => $this->actionCountWithType($actions, 'Hide'),
            'unsafe_uri_action_count' => $this->unsafeUriActionCount($actions),
            'remote_goto_action_count' => $this->actionCountWithType($actions, 'GoToR'),
            'action_chain_cycle_edges_blocked' => $this->sumActionReviewRows($chainReviews, 'cycle_edges_blocked'),
            'action_chain_max_depth_edges_blocked' => $this->sumActionReviewRows($chainReviews, 'max_depth_edges_blocked'),
            'blocked_cycle_action_objects' => $this->integerValuesFromActionReviews($chainReviews, 'blocked_cycle_action_objects'),
            'blocked_max_depth_action_objects' => $this->integerValuesFromActionReviews($chainReviews, 'blocked_max_depth_action_objects'),
            'review_only' => true,
            'executes_action' => false,
            'executes_javascript' => false,
            'imports_form_data' => false,
            'submits_form_data' => false,
            'resets_form_values' => false,
            'changes_widget_visibility' => false,
            'executes_signature_validation' => false,
            'executes_signing' => false,
            'executes_xfa_javascript' => false,
        ];
    }

    /**
     * @param list<array<string, mixed>> $actions
     */
    private function uniqueActionReviewCount(array $actions): int
    {
        $seen = [];
        foreach ($actions as $action) {
            $object = $action['action_object'] ?? null;
            $key = is_int($object)
                ? 'object:' . $object . ':' . (string) ($action['trigger'] ?? '') . ':' . (string) ($action['action_type'] ?? '')
                : 'inline:' . (string) ($action['source_object'] ?? '') . ':' . (string) ($action['trigger'] ?? '') . ':' . (string) ($action['action_type'] ?? '');
            $seen[$key] = true;
        }

        return count($seen);
    }

    /**
     * @param list<array<string, mixed>> $actions
     */
    private function actionCountWithFlag(array $actions, string $flag): int
    {
        return count(array_filter(
            $actions,
            static fn (array $action): bool => ($action[$flag] ?? false) === true
        ));
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @param list<string> $types
     * @return list<string>
     */
    private function actionTargets(array $actions, array $types = []): array
    {
        $targets = [];
        foreach ($actions as $action) {
            $type = $action['action_type'] ?? null;
            if ($types !== [] && (!is_string($type) || !in_array($type, $types, true))) {
                continue;
            }

            foreach (['target', 'uri', 'file'] as $key) {
                $target = $action[$key] ?? null;
                if (is_string($target) && $target !== '' && !in_array($target, $targets, true)) {
                    $targets[] = $target;
                    break;
                }
            }
        }

        return $targets;
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @return list<string>
     */
    private function actionTargetSchemes(array $actions): array
    {
        return $this->uniqueScalarValues(array_map(
            static fn (array $action): mixed => is_string($action['target_scheme'] ?? null) && $action['target_scheme'] !== ''
                ? $action['target_scheme']
                : null,
            $actions
        ));
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @return list<string>
     */
    private function unsafeUriTargets(array $actions): array
    {
        $targets = [];
        foreach ($actions as $action) {
            if (($action['action_type'] ?? null) !== 'URI') {
                continue;
            }

            $safe = $action['safe_uri'] ?? ($action['is_safe_uri'] ?? null);
            if ($safe === true && ($action['safety'] ?? null) !== 'blocked-unsafe-uri') {
                continue;
            }

            $target = $action['target'] ?? ($action['uri'] ?? null);
            if (is_string($target) && $target !== '' && !in_array($target, $targets, true)) {
                $targets[] = $target;
            }
        }

        return $targets;
    }

    /**
     * @param list<array<string, mixed>> $actions
     */
    private function unsafeUriActionCount(array $actions): int
    {
        return count(array_filter(
            $actions,
            static fn (array $action): bool => ($action['action_type'] ?? null) === 'URI'
                && (($action['safe_uri'] ?? ($action['is_safe_uri'] ?? null)) !== true
                    || ($action['safety'] ?? null) === 'blocked-unsafe-uri')
        ));
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @param list<string> $types
     * @return list<string>
     */
    private function actionFieldNames(array $actions, array $types): array
    {
        $names = [];
        foreach ($actions as $action) {
            $type = $action['action_type'] ?? null;
            if (!is_string($type) || !in_array($type, $types, true)) {
                continue;
            }

            foreach ($action['field_names'] ?? [] as $name) {
                if (is_string($name) && $name !== '' && !in_array($name, $names, true)) {
                    $names[] = $name;
                }
            }
        }

        return $names;
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @return list<string>
     */
    private function actionFieldNamesFromRows(array $actions): array
    {
        $names = [];
        foreach ($actions as $action) {
            foreach ($action['field_names'] ?? [] as $name) {
                if (is_string($name) && $name !== '' && !in_array($name, $names, true)) {
                    $names[] = $name;
                }
            }
        }

        return $names;
    }

    /**
     * @param list<string> $fieldNames
     * @param array<string, mixed>|null $lock
     * @return list<string>
     */
    private function lockedActionFieldNames(array $fieldNames, ?array $lock): array
    {
        if ($fieldNames === [] || $lock === null || ($lock['action_valid'] ?? false) !== true) {
            return [];
        }

        return array_values(array_filter(
            $fieldNames,
            fn (string $fieldName): bool => $this->signatureLockAppliesToField($lock, $fieldName)
        ));
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @return list<string>
     */
    private function actionSafetyLabels(array $actions): array
    {
        return $this->uniqueScalarValues(array_map(
            fn (array $action): mixed => $this->actionSafetyLabel($action),
            $actions
        ));
    }

    /**
     * @param array<string, mixed> $action
     */
    private function actionSafetyLabel(array $action): ?string
    {
        if (is_string($action['safety'] ?? null) && $action['safety'] !== '') {
            return $action['safety'];
        }

        return match ($action['action_type'] ?? null) {
            'JavaScript' => 'blocked-javascript',
            'Launch' => 'launch-action-review',
            'SubmitForm' => 'submit-form-action-review',
            'ResetForm' => 'reset-form-action-review',
            'ImportData' => 'import-data-action-review',
            'Hide' => 'hide-action-review',
            'URI' => ($action['safe_uri'] ?? ($action['is_safe_uri'] ?? null)) === true ? 'review-uri' : 'blocked-unsafe-uri',
            'Named' => 'named-action-review',
            'GoTo' => 'local-destination-review',
            'GoToR' => 'remote-document-review',
            null, '' => null,
            default => 'unsupported-action-review',
        };
    }

    /**
     * @param list<array<string, mixed>> $actions
     */
    private function unsafeSeedLockActionCount(array $actions): int
    {
        $unsafeLabels = [
            'blocked-javascript',
            'blocked-unsafe-uri',
            'launch-action-review',
            'submit-form-action-review',
            'reset-form-action-review',
            'import-data-action-review',
            'hide-action-review',
        ];

        return count(array_filter(
            $actions,
            fn (array $action): bool => in_array($this->actionSafetyLabel($action), $unsafeLabels, true)
        ));
    }

    /**
     * @param list<array<string, mixed>> $reviews
     */
    private function sumActionReviewRows(array $reviews, string $key): int
    {
        $sum = 0;
        foreach ($reviews as $review) {
            $value = $review[$key] ?? null;
            if (is_int($value)) {
                $sum += $value;
            }
        }

        return $sum;
    }

    /**
     * @param list<array<string, mixed>> $reviews
     * @return list<int>
     */
    private function integerValuesFromActionReviews(array $reviews, string $key): array
    {
        $values = [];
        foreach ($reviews as $review) {
            foreach ($review[$key] ?? [] as $value) {
                if (is_int($value) && !in_array($value, $values, true)) {
                    $values[] = $value;
                }
            }
        }

        return $values;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<int>
     */
    private function integerValuesFromRows(array $rows, string $key): array
    {
        $values = [];
        foreach ($rows as $row) {
            $value = $row[$key] ?? null;
            if (is_int($value) && !in_array($value, $values, true)) {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * @param list<array<string, mixed>> $xfaPackets
     * @return array<string, mixed>
     */
    private function xfaBoundaryForField(?string $fieldName, ?string $fieldType, array $xfaPackets): array
    {
        $packetIndexes = [];
        $packetNames = [];
        $packetObjects = [];
        $matchedFieldNames = [];
        $matchedDataPaths = [];
        $matchedDataValueRows = [];

        if ($fieldName !== null && $fieldName !== '') {
            foreach ($xfaPackets as $packet) {
                $fieldMatches = $this->matchingXfaNames($packet['field_names'] ?? [], $fieldName);
                $dataMatches = $this->matchingXfaNames($packet['data_paths'] ?? [], $fieldName);
                $dataValueMatches = $this->matchingXfaDataValueRows($packet['data_path_values'] ?? [], $fieldName);
                if ($fieldMatches === [] && $dataMatches === [] && $dataValueMatches === []) {
                    continue;
                }

                if (is_int($packet['index'] ?? null)) {
                    $packetIndexes[] = $packet['index'];
                }
                if (is_string($packet['name'] ?? null)) {
                    $packetNames[] = $packet['name'];
                }
                if (is_int($packet['object'] ?? null)) {
                    $packetObjects[] = $packet['object'];
                }

                $matchedFieldNames = array_merge($matchedFieldNames, $fieldMatches);
                $matchedDataPaths = array_merge($matchedDataPaths, $dataMatches);
                $matchedDataValueRows = array_merge($matchedDataValueRows, $dataValueMatches);
            }
        }

        $matchedFieldNames = $this->uniqueStrings($matchedFieldNames);
        $matchedDataPaths = $this->uniqueStrings($matchedDataPaths);
        $matchedDataValueRows = $this->uniqueXfaDataValueRows($matchedDataValueRows);
        $boundary = [
            'source' => 'acroform_xfa_field_boundary',
            'field_name' => $fieldName,
            'referenced_by_xfa' => $matchedFieldNames !== [] || $matchedDataPaths !== [],
            'packet_count' => count($this->uniqueStrings(array_map('strval', $packetIndexes))),
            'packet_indexes' => array_values(array_unique($packetIndexes)),
            'packet_names' => $this->uniqueStrings($packetNames),
            'packet_objects' => array_values(array_unique($packetObjects)),
            'matched_field_names' => $matchedFieldNames,
            'matched_data_paths' => $matchedDataPaths,
            'matched_data_values' => $matchedDataValueRows,
            'matched_data_value_count' => count($matchedDataValueRows),
            'matched_data_value_previews' => $this->uniqueStrings(array_values(array_filter(array_map(
                static fn (array $row): mixed => $row['value_preview'] ?? null,
                $matchedDataValueRows
            ), static fn (mixed $value): bool => is_string($value)))),
            'matched_data_value_sha256' => $this->uniqueStrings(array_values(array_filter(array_map(
                static fn (array $row): mixed => $row['value_sha256'] ?? null,
                $matchedDataValueRows
            ), static fn (mixed $value): bool => is_string($value)))),
            'has_xfa_template_reference' => $matchedFieldNames !== [],
            'has_xfa_dataset_reference' => $matchedDataPaths !== [],
            'dynamic_value_present' => $matchedDataPaths !== [],
            'dynamic_value_used_for_current_value' => false,
            'value_used_for_import' => false,
            'xfa_payload_text_exposed' => false,
            'executes_xfa_javascript' => false,
            'executes_form_calculation' => false,
        ];

        if ($fieldType === 'Sig') {
            $boundary += [
                'xfa_value_used_for_signature' => false,
                'executes_signature_validation' => false,
                'executes_signing' => false,
            ];
        }

        return $boundary;
    }

    /**
     * @return array<string, mixed>
     */
    private function xfaWidgetCurrentBaseReview(array $field, array $boundary): array
    {
        $widgets = $this->arrayRows($field['widgets'] ?? []);
        $pageWidgets = array_values(array_filter(
            $widgets,
            static fn (array $widget): bool => ($widget['referenced_from_page_annots'] ?? false) === true
        ));
        $primaryWidget = $pageWidgets[0] ?? ($widgets[0] ?? null);
        $normalAppearance = is_array($primaryWidget['normal_appearance'] ?? null) ? $primaryWidget['normal_appearance'] : null;
        $selectedAppearance = is_array($normalAppearance['selected_appearance'] ?? null) ? $normalAppearance['selected_appearance'] : null;
        $valueState = is_array($field['value_state'] ?? null) ? $field['value_state'] : [];

        $appearanceStates = [];
        $checkedExportValues = [];
        $selectedAppearanceObjects = [];
        $staleAppearanceStateCount = 0;
        foreach ($widgets as $widget) {
            foreach ($widget['appearance_states'] ?? [] as $state) {
                if (is_string($state) && $state !== '' && !in_array($state, $appearanceStates, true)) {
                    $appearanceStates[] = $state;
                }
            }

            $exportValue = $widget['export_value'] ?? null;
            if (($widget['checked'] ?? false) === true && is_string($exportValue) && $exportValue !== '' && !in_array($exportValue, $checkedExportValues, true)) {
                $checkedExportValues[] = $exportValue;
            }

            $appearance = is_array($widget['normal_appearance'] ?? null) ? $widget['normal_appearance'] : null;
            if ($appearance !== null && ($appearance['stale_appearance_state'] ?? false) === true) {
                $staleAppearanceStateCount++;
            }
            $selected = is_array($appearance['selected_appearance'] ?? null) ? $appearance['selected_appearance'] : null;
            $selectedObject = $selected['object'] ?? null;
            if (is_int($selectedObject) && !in_array($selectedObject, $selectedAppearanceObjects, true)) {
                $selectedAppearanceObjects[] = $selectedObject;
            }
        }

        return [
            'source' => 'acroform_xfa_widget_currentbase_review_boundary',
            'field_name' => $field['name'] ?? null,
            'field_type' => $field['field_type'] ?? null,
            'field_object' => $field['object'] ?? null,
            'referenced_by_xfa' => (bool) ($boundary['referenced_by_xfa'] ?? false),
            'has_xfa_template_reference' => (bool) ($boundary['has_xfa_template_reference'] ?? false),
            'has_xfa_dataset_reference' => (bool) ($boundary['has_xfa_dataset_reference'] ?? false),
            'dynamic_value_present' => (bool) ($boundary['dynamic_value_present'] ?? false),
            'packet_indexes' => $boundary['packet_indexes'] ?? [],
            'packet_names' => $boundary['packet_names'] ?? [],
            'packet_objects' => $boundary['packet_objects'] ?? [],
            'matched_field_names' => $boundary['matched_field_names'] ?? [],
            'matched_data_paths' => $boundary['matched_data_paths'] ?? [],
            'matched_data_value_count' => (int) ($boundary['matched_data_value_count'] ?? 0),
            'matched_data_value_previews' => $boundary['matched_data_value_previews'] ?? [],
            'matched_data_value_sha256' => $boundary['matched_data_value_sha256'] ?? [],
            'has_current_value' => (bool) ($valueState['has_current_value'] ?? false),
            'has_default_value' => (bool) ($valueState['has_default_value'] ?? false),
            'current' => $valueState['current'] ?? ($field['value'] ?? null),
            'default' => $valueState['default'] ?? ($field['default_value'] ?? null),
            'display_value' => $valueState['display_value'] ?? $this->displayValue($field['value'] ?? null),
            'current_source' => $valueState['current_source'] ?? null,
            'current_source_object' => $valueState['current_source_object'] ?? null,
            'default_source' => $valueState['default_source'] ?? null,
            'default_source_object' => $valueState['default_source_object'] ?? null,
            'state_source' => $valueState['state_source'] ?? null,
            'effective_current_state' => $valueState['effective_current_state'] ?? null,
            'changed_from_default' => $valueState['changed_from_default'] ?? null,
            'acroform_current_value_authoritative' => true,
            'acroform_default_value_authoritative_for_reset' => true,
            'widget_appearance_state_authoritative' => ($field['field_type'] ?? null) === 'Btn',
            'xfa_value_used_for_current_value' => false,
            'xfa_value_used_for_default_value' => false,
            'xfa_value_used_for_widget_state' => false,
            'xfa_value_used_for_import' => false,
            'xfa_payload_text_exposed' => false,
            'widget_count' => count($widgets),
            'page_referenced_widget_count' => count($pageWidgets),
            'widget_objects' => $this->integerValuesFromRows($widgets, 'object'),
            'page_widget_objects' => $this->integerValuesFromRows($pageWidgets, 'object'),
            'visible_widget_count' => count(array_filter($widgets, static fn (array $widget): bool => ($widget['visible'] ?? false) === true)),
            'hidden_widget_count' => count(array_filter($widgets, static fn (array $widget): bool => ($widget['hidden'] ?? false) === true)),
            'printable_widget_count' => count(array_filter($widgets, static fn (array $widget): bool => ($widget['printable'] ?? false) === true)),
            'primary_widget_object' => is_array($primaryWidget) ? ($primaryWidget['object'] ?? null) : null,
            'primary_widget_page_index' => is_array($primaryWidget) ? ($primaryWidget['page_index'] ?? null) : null,
            'primary_widget_page_object' => is_array($primaryWidget) ? ($primaryWidget['page_object'] ?? null) : null,
            'primary_widget_page_annotation_index' => is_array($primaryWidget) ? ($primaryWidget['page_annotation_index'] ?? null) : null,
            'primary_widget_referenced_from_page_annots' => is_array($primaryWidget) && ($primaryWidget['referenced_from_page_annots'] ?? false) === true,
            'primary_widget_visibility' => is_array($primaryWidget) ? ($primaryWidget['annotation_visibility'] ?? null) : null,
            'primary_widget_flag_names' => is_array($primaryWidget) ? ($primaryWidget['annotation_flag_names'] ?? []) : [],
            'primary_widget_rect' => is_array($primaryWidget) ? ($primaryWidget['rect'] ?? null) : null,
            'primary_widget_appearance_state' => is_array($primaryWidget) ? ($primaryWidget['appearance_state'] ?? null) : null,
            'primary_widget_normal_appearance_type' => is_array($normalAppearance) ? ($normalAppearance['normal_appearance_type'] ?? null) : null,
            'widget_appearance_states' => $appearanceStates,
            'checked_widget_export_values' => $checkedExportValues,
            'checked_widget_count' => (int) ($valueState['checked_widget_count'] ?? 0),
            'widget_state_consistent' => $valueState['widget_state_consistent'] ?? null,
            'selected_appearance_object' => is_array($selectedAppearance) ? ($selectedAppearance['object'] ?? null) : null,
            'selected_appearance_objects' => $selectedAppearanceObjects,
            'selected_appearance_decoded_sha256' => is_array($selectedAppearance) ? ($selectedAppearance['decoded_sha256'] ?? null) : null,
            'state_matches_appearance' => is_array($normalAppearance) ? ($normalAppearance['state_matches_appearance'] ?? null) : null,
            'stale_appearance_state_count' => $staleAppearanceStateCount,
            'appearance_value_used_for_import' => false,
            'appearance_payload_text_exposed' => false,
            'executes_appearance_streams' => false,
            'renders_appearances' => false,
            'imports_xfa_payload' => false,
            'executes_xfa_javascript' => false,
            'executes_form_actions' => false,
            'executes_javascript' => false,
        ];
    }

    /**
     * @param list<array<string, mixed>> $widgets
     * @param list<array<string, mixed>> $fieldActions
     * @param list<array<string, mixed>> $widgetActions
     * @return array<string, mixed>
     */
    private function widgetXfaActionAppearanceValueReview(
        array $field,
        array $boundary,
        array $widgets,
        array $fieldActions,
        array $widgetActions
    ): array {
        $pageWidgets = array_values(array_filter(
            $widgets,
            static fn (array $widget): bool => ($widget['referenced_from_page_annots'] ?? false) === true
        ));
        $primaryWidget = $pageWidgets[0] ?? ($widgets[0] ?? null);
        $normalAppearance = is_array($primaryWidget['normal_appearance'] ?? null) ? $primaryWidget['normal_appearance'] : null;
        $selectedAppearance = is_array($normalAppearance['selected_appearance'] ?? null) ? $normalAppearance['selected_appearance'] : null;
        $valueState = is_array($field['value_state'] ?? null) ? $field['value_state'] : [];
        $appearance = $this->fieldWidgetAppearanceSummary($field);
        $actions = array_merge($fieldActions, $widgetActions);
        $chainReviews = $this->actionReviewRowsForField($field, $widgets);

        return [
            'source' => 'acroform_widget_xfa_action_appearance_value_currentbase_review_boundary',
            'field_name' => $field['name'] ?? null,
            'field_type' => $field['field_type'] ?? null,
            'field_type_label' => $field['field_type_label'] ?? null,
            'field_object' => $field['object'] ?? null,
            'has_current_value' => (bool) ($valueState['has_current_value'] ?? false),
            'has_default_value' => (bool) ($valueState['has_default_value'] ?? false),
            'current' => $valueState['effective_current_state'] ?? ($valueState['current'] ?? ($field['value'] ?? null)),
            'default' => $valueState['default'] ?? ($field['default_value'] ?? null),
            'display_value' => $valueState['display_value'] ?? $this->displayValue($field['value'] ?? null),
            'current_source' => $valueState['state_source'] ?? ($valueState['current_source'] ?? null),
            'current_source_object' => $valueState['current_source_object'] ?? null,
            'default_source' => $valueState['default_source'] ?? null,
            'default_source_object' => $valueState['default_source_object'] ?? null,
            'changed_from_default' => $valueState['changed_from_default'] ?? null,
            'acroform_current_value_authoritative' => true,
            'acroform_default_value_authoritative_for_reset' => true,
            'widget_appearance_state_authoritative' => ($field['field_type'] ?? null) === 'Btn',
            'referenced_by_xfa' => (bool) ($boundary['referenced_by_xfa'] ?? false),
            'has_xfa_template_reference' => (bool) ($boundary['has_xfa_template_reference'] ?? false),
            'has_xfa_dataset_reference' => (bool) ($boundary['has_xfa_dataset_reference'] ?? false),
            'dynamic_value_present' => (bool) ($boundary['dynamic_value_present'] ?? false),
            'xfa_packet_indexes' => $boundary['packet_indexes'] ?? [],
            'xfa_packet_names' => $boundary['packet_names'] ?? [],
            'xfa_packet_objects' => $boundary['packet_objects'] ?? [],
            'xfa_matched_field_names' => $boundary['matched_field_names'] ?? [],
            'xfa_matched_data_paths' => $boundary['matched_data_paths'] ?? [],
            'xfa_matched_data_value_count' => (int) ($boundary['matched_data_value_count'] ?? 0),
            'xfa_matched_data_value_previews' => $boundary['matched_data_value_previews'] ?? [],
            'xfa_matched_data_value_sha256' => $boundary['matched_data_value_sha256'] ?? [],
            'xfa_value_used_for_current_value' => false,
            'xfa_value_used_for_default_value' => false,
            'xfa_value_used_for_widget_state' => false,
            'xfa_value_used_for_submit' => false,
            'xfa_value_used_for_import' => false,
            'xfa_payload_text_exposed' => false,
            'widget_count' => $appearance['widget_count'],
            'page_referenced_widget_count' => $appearance['page_referenced_widget_count'],
            'widget_objects' => $appearance['widget_objects'],
            'page_widget_objects' => $this->integerValuesFromRows($pageWidgets, 'object'),
            'visible_widget_count' => count(array_filter($widgets, static fn (array $widget): bool => ($widget['visible'] ?? false) === true)),
            'hidden_widget_count' => count(array_filter($widgets, static fn (array $widget): bool => ($widget['hidden'] ?? false) === true)),
            'printable_widget_count' => count(array_filter($widgets, static fn (array $widget): bool => ($widget['printable'] ?? false) === true)),
            'primary_widget_object' => is_array($primaryWidget) ? ($primaryWidget['object'] ?? null) : null,
            'primary_widget_page_index' => is_array($primaryWidget) ? ($primaryWidget['page_index'] ?? null) : null,
            'primary_widget_page_object' => is_array($primaryWidget) ? ($primaryWidget['page_object'] ?? null) : null,
            'primary_widget_page_annotation_index' => is_array($primaryWidget) ? ($primaryWidget['page_annotation_index'] ?? null) : null,
            'primary_widget_referenced_from_page_annots' => is_array($primaryWidget) && ($primaryWidget['referenced_from_page_annots'] ?? false) === true,
            'primary_widget_rect' => is_array($primaryWidget) ? ($primaryWidget['rect'] ?? null) : null,
            'primary_widget_visibility' => is_array($primaryWidget) ? ($primaryWidget['annotation_visibility'] ?? null) : null,
            'primary_widget_flag_names' => is_array($primaryWidget) ? ($primaryWidget['annotation_flag_names'] ?? []) : [],
            'primary_widget_appearance_state' => is_array($primaryWidget) ? ($primaryWidget['appearance_state'] ?? null) : null,
            'primary_widget_normal_appearance_type' => is_array($normalAppearance) ? ($normalAppearance['normal_appearance_type'] ?? null) : null,
            'widget_appearance_states' => $appearance['appearance_states'],
            'checked_widget_count' => (int) ($valueState['checked_widget_count'] ?? $appearance['checked_widget_count']),
            'widget_state_consistent' => $valueState['widget_state_consistent'] ?? null,
            'selected_appearance_object' => is_array($selectedAppearance) ? ($selectedAppearance['object'] ?? null) : null,
            'selected_appearance_objects' => $appearance['selected_appearance_objects'],
            'selected_appearance_decoded_sha256' => is_array($selectedAppearance) ? ($selectedAppearance['decoded_sha256'] ?? null) : null,
            'selected_appearance_decoded_sha256_values' => $appearance['selected_appearance_decoded_sha256'],
            'state_matches_appearance' => is_array($normalAppearance) ? ($normalAppearance['state_matches_appearance'] ?? null) : null,
            'stale_appearance_state_count' => $appearance['stale_appearance_state_count'],
            'appearance_value_used_for_import' => false,
            'appearance_payload_text_exposed' => false,
            'action_count' => count($actions),
            'unique_action_count' => $this->uniqueActionReviewCount($actions),
            'field_action_count' => count($fieldActions),
            'widget_action_count' => count($widgetActions),
            'chained_action_count' => $this->actionCountWithFlag($actions, 'chained'),
            'action_types' => $this->uniqueScalarValues(array_map(
                static fn (array $action): mixed => $action['action_type'] ?? null,
                $actions
            )),
            'action_triggers' => $this->uniqueScalarValues(array_map(
                static fn (array $action): mixed => $action['trigger'] ?? null,
                $actions
            )),
            'action_trigger_labels' => $this->uniqueScalarValues(array_map(
                static fn (array $action): mixed => $action['trigger_label'] ?? null,
                $actions
            )),
            'action_safety_labels' => $this->actionSafetyLabels($actions),
            'action_objects' => $this->integerValuesFromRows($actions, 'action_object'),
            'action_targets' => $this->actionTargets($actions),
            'action_field_names' => $this->actionFieldNamesFromRows($actions),
            'submit_action_field_names' => $this->actionFieldNames($actions, ['SubmitForm']),
            'reset_action_field_names' => $this->actionFieldNames($actions, ['ResetForm']),
            'hide_action_field_names' => $this->actionFieldNames($actions, ['Hide']),
            'javascript_action_count' => $this->actionCountWithType($actions, 'JavaScript'),
            'submit_form_action_count' => $this->actionCountWithType($actions, 'SubmitForm'),
            'reset_form_action_count' => $this->actionCountWithType($actions, 'ResetForm'),
            'import_data_action_count' => $this->actionCountWithType($actions, 'ImportData'),
            'hide_action_count' => $this->actionCountWithType($actions, 'Hide'),
            'unsafe_uri_action_count' => $this->unsafeUriActionCount($actions),
            'action_chain_cycle_edges_blocked' => $this->sumActionReviewRows($chainReviews, 'cycle_edges_blocked'),
            'action_chain_max_depth_edges_blocked' => $this->sumActionReviewRows($chainReviews, 'max_depth_edges_blocked'),
            'review_only' => true,
            'form_actions_review_only' => true,
            'submits_form_data' => false,
            'resets_form_values' => false,
            'imports_form_data' => false,
            'changes_widget_visibility' => false,
            'executes_action' => false,
            'executes_javascript' => false,
            'executes_appearance_streams' => false,
            'renders_appearances' => false,
            'executes_xfa_javascript' => false,
            'imports_xfa_payload' => false,
            'executes_python_or_models' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * @param list<array<string, mixed>> $widgets
     * @return list<array<string, mixed>>
     */
    private function actionReviewRowsForField(array $field, array $widgets): array
    {
        $reviews = [];
        if (is_array($field['action_review'] ?? null)) {
            $reviews[] = $field['action_review'];
        }

        foreach ($widgets as $widget) {
            if (is_array($widget['action_review'] ?? null)) {
                $reviews[] = $widget['action_review'];
            }
        }

        return $reviews;
    }

    /**
     * @param mixed $names
     * @return list<string>
     */
    private function matchingXfaNames(mixed $names, string $fieldName): array
    {
        if (!is_array($names)) {
            return [];
        }

        return array_values(array_filter(
            $names,
            static fn (mixed $name): bool => is_string($name) && $name === $fieldName
        ));
    }

    /**
     * @param mixed $rows
     * @return list<array<string, mixed>>
     */
    private function matchingXfaDataValueRows(mixed $rows, string $fieldName): array
    {
        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_filter(
            $rows,
            static fn (mixed $row): bool => is_array($row)
                && is_string($row['path'] ?? null)
                && $row['path'] === $fieldName
        ));
    }

    /**
     * @return array{xml: string, object: int|null, filters: list<string>}|null
     * @param array<int, string> $objects
     */
    private function xfaPayloadFromToken(array $token, array $objects): ?array
    {
        if (($token['type'] ?? null) === 'string') {
            return [
                'xml' => (string) ($token['value'] ?? ''),
                'object' => null,
                'filters' => [],
            ];
        }

        if (($token['type'] ?? null) === 'dictionary') {
            return [
                'xml' => (string) ($token['value'] ?? ''),
                'object' => null,
                'filters' => [],
            ];
        }

        if (($token['type'] ?? null) !== 'reference') {
            return null;
        }

        $objectNumber = (int) ($token['object'] ?? 0);
        if ($objectNumber <= 0 || !isset($objects[$objectNumber])) {
            return null;
        }

        $stream = $this->decodeStreamObject($objects[$objectNumber], $objects);
        if ($stream !== null) {
            return [
                'xml' => $stream,
                'object' => $objectNumber,
                'filters' => $this->streamObjectFilters($objects[$objectNumber], $objects),
            ];
        }

        $value = $this->pdfValueToString(trim($objects[$objectNumber]), $objects);

        return [
            'xml' => $value ?? trim($objects[$objectNumber]),
            'object' => $objectNumber,
            'filters' => [],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function xfaTokenFromValue(string $value): ?array
    {
        $token = $this->readXfaTokenAt($value, 0);
        if ($token === null) {
            return null;
        }

        unset($token['end']);
        return $token;
    }

    private function xmlRootName(string $xml): ?string
    {
        return preg_match('/<\s*([A-Za-z_][A-Za-z0-9_.:-]*)\b/s', $xml, $match) === 1
            ? $match[1]
            : null;
    }

    private function xmlLocalName(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }

        $parts = explode(':', $name);
        return end($parts) ?: $name;
    }

    /**
     * @return array{xml: string, encoding: string, decoded_to_utf8: bool}
     */
    private function normalizeXfaXmlPayload(string $xml): array
    {
        if (str_starts_with($xml, "\xEF\xBB\xBF")) {
            return [
                'xml' => substr($xml, 3),
                'encoding' => 'UTF-8-BOM',
                'decoded_to_utf8' => false,
            ];
        }

        if (str_starts_with($xml, "\xFE\xFF")) {
            $decoded = function_exists('iconv') ? @iconv('UTF-16BE', 'UTF-8//IGNORE', substr($xml, 2)) : false;
            if (is_string($decoded)) {
                return [
                    'xml' => $decoded,
                    'encoding' => 'UTF-16BE',
                    'decoded_to_utf8' => true,
                ];
            }
        }

        if (str_starts_with($xml, "\xFF\xFE")) {
            $decoded = function_exists('iconv') ? @iconv('UTF-16LE', 'UTF-8//IGNORE', substr($xml, 2)) : false;
            if (is_string($decoded)) {
                return [
                    'xml' => $decoded,
                    'encoding' => 'UTF-16LE',
                    'decoded_to_utf8' => true,
                ];
            }
        }

        return [
            'xml' => $xml,
            'encoding' => 'UTF-8',
            'decoded_to_utf8' => false,
        ];
    }

    /**
     * @return list<string>
     */
    private function xdpPacketNames(string $xml): array
    {
        if ($this->xmlLocalName($this->xmlRootName($xml)) !== 'xdp') {
            return [];
        }

        if (preg_match('/<\s*([A-Za-z_][A-Za-z0-9_.:-]*)\b[^>]*>/s', $xml, $rootMatch, PREG_OFFSET_CAPTURE) !== 1) {
            return [];
        }

        $body = substr($xml, $rootMatch[0][1] + strlen($rootMatch[0][0]));
        $names = [];
        $seen = [];
        $depth = 0;
        $offset = 0;
        while (preg_match('/<\s*(\/?)([A-Za-z_][A-Za-z0-9_.:-]*)\b[^>]*(\/?)>/s', $body, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $offset = $match[0][1] + strlen($match[0][0]);
            $closing = $match[1][0] === '/';
            $tagName = $match[2][0];
            $localName = $this->xmlLocalName($tagName);
            if ($closing) {
                if ($depth === 0 && $localName === 'xdp') {
                    break;
                }

                $depth = max(0, $depth - 1);
                continue;
            }

            if ($depth === 0 && $localName !== null && $localName !== 'xdp' && !isset($seen[$localName])) {
                $seen[$localName] = true;
                $names[] = $localName;
            }

            if (!str_ends_with(rtrim($match[0][0]), '/>')) {
                $depth++;
            }
        }

        return $names;
    }

    /**
     * @param list<string> $xdpPacketNames
     */
    private function xfaPayloadHasRole(string $name, ?string $root, array $xdpPacketNames, string $xml, string $role): bool
    {
        return $this->xmlLocalName($name) === $role
            || $this->xmlLocalName($root) === $role
            || in_array($role, $xdpPacketNames, true)
            || $this->xmlContainsElement($xml, $role);
    }

    private function xmlContainsElement(string $xml, string $localName): bool
    {
        return preg_match('/<\s*(?:[A-Za-z_][A-Za-z0-9_.-]*:)?' . preg_quote($localName, '/') . '\b/si', $xml) === 1;
    }

    /**
     * @return list<string>
     */
    private function xfaFieldNames(string $xml): array
    {
        if (preg_match_all('/<(?:[A-Za-z_][A-Za-z0-9_.-]*:)?field\b[^>]*\bname\s*=\s*(["\'])(.*?)\1/si', $xml, $matches) === false) {
            return [];
        }

        $names = [];
        foreach ($matches[2] as $name) {
            $decoded = $this->decodeXmlText($name);
            if ($decoded !== '' && !in_array($decoded, $names, true)) {
                $names[] = $decoded;
            }
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private function xfaDataNodeNames(string $xml): array
    {
        if (preg_match('/<(?:[A-Za-z_][A-Za-z0-9_.-]*:)?data\b[^>]*>(.*?)<\/(?:[A-Za-z_][A-Za-z0-9_.-]*:)?data>/si', $xml, $match) !== 1) {
            return [];
        }

        if (preg_match_all('/<\s*([A-Za-z_][A-Za-z0-9_.:-]*)\b(?![^>]*\/>)/s', $match[1], $tagMatches) === false) {
            return [];
        }

        $names = [];
        foreach ($tagMatches[1] as $tagName) {
            $localName = $this->xmlLocalName($tagName);
            if ($localName === null || in_array($localName, ['data'], true) || in_array($localName, $names, true)) {
                continue;
            }

            $names[] = $localName;
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private function xfaDataPaths(string $xml): array
    {
        $paths = [];
        foreach ($this->xfaDataSections($xml) as $section) {
            foreach ($this->xmlLeafTextPaths($section) as $path) {
                if (!in_array($path, $paths, true)) {
                    $paths[] = $path;
                }
            }
        }

        return $paths;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function xfaDataPathValues(string $xml): array
    {
        $rows = [];
        foreach ($this->xfaDataSections($xml) as $section) {
            foreach ($this->xmlLeafTextPathValueRows($section) as $row) {
                $rows[] = $row;
            }
        }

        return $this->uniqueXfaDataValueRows($rows);
    }

    /**
     * @return list<string>
     */
    private function xfaDataSections(string $xml): array
    {
        if (preg_match_all('/<\s*(?:[A-Za-z_][A-Za-z0-9_.-]*:)?data\b[^>]*>(.*?)<\/\s*(?:[A-Za-z_][A-Za-z0-9_.-]*:)?data\s*>/si', $xml, $matches) === false) {
            return [];
        }

        return $matches[1];
    }

    /**
     * @return list<string>
     */
    private function xmlLeafTextPaths(string $xml): array
    {
        $paths = [];
        $stack = [];
        $offset = 0;
        while (preg_match('/<\s*(\/?)([A-Za-z_][A-Za-z0-9_.:-]*)\b[^>]*(\/?)>/s', $xml, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $tag = $match[0][0];
            $tagStart = $match[0][1];
            $tagEnd = $tagStart + strlen($tag);
            $offset = $tagEnd;

            $closing = $match[1][0] === '/';
            $localName = $this->xmlLocalName($match[2][0]);
            if ($localName === null || $localName === '') {
                continue;
            }

            if ($closing) {
                $this->popXmlStackTo($stack, $localName);
                continue;
            }

            $selfClosing = str_ends_with(rtrim($tag), '/>');
            if (!$selfClosing) {
                $nextTagStart = strpos($xml, '<', $tagEnd);
                $text = $nextTagStart === false
                    ? substr($xml, $tagEnd)
                    : substr($xml, $tagEnd, $nextTagStart - $tagEnd);
                $text = trim($this->decodeXmlText($text));
                if ($text !== '') {
                    $path = implode('.', array_merge($stack, [$localName]));
                    if ($path !== '' && !in_array($path, $paths, true)) {
                        $paths[] = $path;
                    }
                }

                $stack[] = $localName;
            }
        }

        return $paths;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function xmlLeafTextPathValueRows(string $xml): array
    {
        $rows = [];
        $stack = [];
        $offset = 0;
        while (preg_match('/<\s*(\/?)([A-Za-z_][A-Za-z0-9_.:-]*)\b[^>]*(\/?)>/s', $xml, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $tag = $match[0][0];
            $tagStart = $match[0][1];
            $tagEnd = $tagStart + strlen($tag);
            $offset = $tagEnd;

            $closing = $match[1][0] === '/';
            $localName = $this->xmlLocalName($match[2][0]);
            if ($localName === null || $localName === '') {
                continue;
            }

            if ($closing) {
                $this->popXmlStackTo($stack, $localName);
                continue;
            }

            $selfClosing = str_ends_with(rtrim($tag), '/>');
            if ($selfClosing) {
                continue;
            }

            $nextTagStart = strpos($xml, '<', $tagEnd);
            $text = $nextTagStart === false
                ? substr($xml, $tagEnd)
                : substr($xml, $tagEnd, $nextTagStart - $tagEnd);
            $text = trim($this->decodeXmlText($text));
            if ($text !== '') {
                $path = implode('.', array_merge($stack, [$localName]));
                if ($path !== '') {
                    $preview = $this->boundedPreview($text, 160);
                    $rows[] = [
                        'path' => $path,
                        'value_preview' => $preview['preview'],
                        'value_truncated' => $preview['truncated'],
                        'value_bytes' => strlen($text),
                        'value_sha256' => hash('sha256', $text),
                        'value_used_for_import' => false,
                        'payload_text_exposed' => false,
                    ];
                }
            }

            $stack[] = $localName;
        }

        return $rows;
    }

    /**
     * @param list<string> $stack
     */
    private function popXmlStackTo(array &$stack, string $localName): void
    {
        for ($index = count($stack) - 1; $index >= 0; $index--) {
            if ($stack[$index] !== $localName) {
                continue;
            }

            array_splice($stack, $index);
            return;
        }

        array_pop($stack);
    }

    /**
     * @param list<string> $fieldNames
     * @param list<string> $dataPaths
     * @return list<string>
     */
    private function xfaSignatureFieldNames(array $fieldNames, array $dataPaths): array
    {
        return $this->uniqueStrings(array_filter(
            array_merge($fieldNames, $dataPaths),
            fn (string $name): bool => $this->looksLikeSignatureFieldName($name)
        ));
    }

    private function looksLikeSignatureFieldName(string $name): bool
    {
        $parts = preg_split('/[.:\s_-]+/', strtolower($name)) ?: [];
        foreach ($parts as $part) {
            if (in_array($part, ['sig', 'signature', 'signatures'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private function uniqueStrings(array $values): array
    {
        $unique = [];
        foreach ($values as $value) {
            if ($value === '' || in_array($value, $unique, true)) {
                continue;
            }

            $unique[] = $value;
        }

        return $unique;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function uniqueXfaDataValueRows(array $rows): array
    {
        $unique = [];
        $seen = [];
        foreach ($rows as $row) {
            $path = $row['path'] ?? null;
            $hash = $row['value_sha256'] ?? null;
            if (!is_string($path) || $path === '' || !is_string($hash) || $hash === '') {
                continue;
            }

            $key = $path . "\0" . $hash;
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $row;
        }

        return $unique;
    }

    private function xmlTextPreview(string $xml): string
    {
        $withoutDeclarations = preg_replace('/<\?(?:.|\R)*?\?>|<!\[CDATA\[|]]>/s', ' ', $xml) ?? $xml;
        $text = strip_tags($withoutDeclarations);
        $text = $this->decodeXmlText($text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', trim($text)) ?? trim($text);

        return strlen($text) > 180 ? substr($text, 0, 177) . '...' : $text;
    }

    private function decodeXmlText(string $text): string
    {
        return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, array{value: string, source: string, source_object: int|null}> $formDefaults
     * @return array{inherited: array<string, array{value: string, source: string, source_object: int|null}>, name_parts: list<string>, hierarchy_path: list<array{object: int, partial_name: string|null, full_name: string, alternate_name: string|null, mapping_name: string|null}>}
     */
    private function fieldReferenceAncestorContext(int $objectNumber, array $objects, array $formDefaults): array
    {
        $ancestors = [];
        $candidate = $objectNumber;
        $seen = [];

        while (isset($objects[$candidate]) && !isset($seen[$candidate])) {
            $seen[$candidate] = true;
            $body = $this->dictionaryObjectBody($objects[$candidate]) ?? trim($objects[$candidate]);
            $parentObject = $this->validObjectReferenceValueAfterName($body, 'Parent', $objects);
            if ($parentObject === null || !isset($objects[$parentObject]) || isset($seen[$parentObject])) {
                break;
            }

            $parentBody = $this->dictionaryObjectBody($objects[$parentObject]) ?? trim($objects[$parentObject]);
            if (!$this->isFieldDictionaryCandidate($parentBody) || !$this->fieldParentOwnsChild($parentObject, $candidate, $objects)) {
                break;
            }

            array_unshift($ancestors, $parentObject);
            $candidate = $parentObject;
        }

        $inherited = $formDefaults;
        $nameParts = [];
        $hierarchyPath = [];
        foreach ($ancestors as $ancestorObject) {
            $body = $this->dictionaryObjectBody($objects[$ancestorObject] ?? '') ?? trim($objects[$ancestorObject] ?? '');
            $inherited = $this->mergeFieldAttributes($body, $inherited, $ancestorObject);

            $partialName = $this->pdfStringValueAfterName($body, 'T', $objects);
            $alternateName = $this->pdfStringValueAfterName($body, 'TU', $objects);
            $mappingName = $this->pdfStringValueAfterName($body, 'TM', $objects);
            if ($partialName !== null && $partialName !== '') {
                $nameParts[] = $partialName;
            }

            $hierarchyPath[] = [
                'object' => $ancestorObject,
                'partial_name' => $partialName,
                'full_name' => $nameParts === [] ? '#' . $ancestorObject : implode('.', $nameParts),
                'alternate_name' => $alternateName,
                'mapping_name' => $mappingName,
            ];
        }

        return [
            'inherited' => $inherited,
            'name_parts' => $nameParts,
            'hierarchy_path' => $hierarchyPath,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     * @param array<int, string> $objects
     * @param array<string, array{value: string, source: string, source_object: int|null}> $inherited
     * @param list<string> $nameParts
     * @param list<array{object: int, partial_name: string|null, full_name: string, alternate_name: string|null, mapping_name: string|null}> $hierarchyPath
     * @param array<int, true> $seen
     * @param array<int, int> $pageIndexes
     * @param array<int, array{page_index: int, page_object: int, annotation_index: int}> $pageWidgets
     * @param array<int, string> $fieldNamesByObject
     */
    private function fieldsFromObject(
        int $objectNumber,
        array $objects,
        array $inherited,
        array $nameParts,
        array $hierarchyPath,
        array $seen,
        array $pageIndexes,
        array $pageWidgets,
        array $fieldNamesByObject
    ): array {
        if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
            return [];
        }

        $seen[$objectNumber] = true;
        $body = $this->dictionaryObjectBody($objects[$objectNumber]) ?? trim($objects[$objectNumber]);
        $effective = $this->mergeFieldAttributes($body, $inherited, $objectNumber);
        $partialName = $this->pdfStringValueAfterName($body, 'T', $objects);
        $alternateName = $this->pdfStringValueAfterName($body, 'TU', $objects);
        $mappingName = $this->pdfStringValueAfterName($body, 'TM', $objects);
        $currentNameParts = $nameParts;
        if ($partialName !== null && $partialName !== '') {
            $currentNameParts[] = $partialName;
        }
        $currentFullName = $currentNameParts === [] ? '#' . $objectNumber : implode('.', $currentNameParts);
        $currentHierarchyPath = $hierarchyPath;
        $currentHierarchyPath[] = [
            'object' => $objectNumber,
            'partial_name' => $partialName,
            'full_name' => $currentFullName,
            'alternate_name' => $alternateName,
            'mapping_name' => $mappingName,
        ];

        $kidRefs = $this->kidReferences($body, $objects);
        $childFieldRefs = [];
        $widgetRefs = [];
        foreach ($kidRefs as $kidRef) {
            if (isset($seen[$kidRef]) || !isset($objects[$kidRef])) {
                continue;
            }

            $kidBody = $this->dictionaryObjectBody($objects[$kidRef]) ?? trim($objects[$kidRef]);
            if ($this->isPureWidget($kidBody)) {
                $widgetRefs[] = $kidRef;
                continue;
            }

            if ($this->isFieldDictionaryCandidate($kidBody)) {
                $childFieldRefs[] = $kidRef;
            }
        }

        if ($childFieldRefs !== []) {
            $fields = [];
            foreach ($childFieldRefs as $childRef) {
                foreach ($this->fieldsFromObject(
                    $childRef,
                    $objects,
                    $effective,
                    $currentNameParts,
                    $currentHierarchyPath,
                    $seen,
                    $pageIndexes,
                    $pageWidgets,
                    $fieldNamesByObject
                ) as $field) {
                    $fields[] = $field;
                }
            }

            return $fields;
        }

        if ($this->isWidget($body)) {
            array_unshift($widgetRefs, $objectNumber);
        }
        $widgetRefs = $this->widgetReferencesForField($objectNumber, $widgetRefs, $objects, $pageWidgets);

        $fieldType = $this->fieldType($effective, $objects);
        if ($fieldType === null && $partialName === null && $mappingName === null) {
            return [];
        }

        $flags = $this->integerFromEffective($effective, 'Ff', 0, $objects);
        $defaultAppearance = $this->defaultAppearanceFromEffective($effective, $objects);
        $password = $fieldType === 'Tx' && $this->hasFlagBit($flags, 14);

        $name = $currentFullName;
        $value = $password ? null : $this->valueFromEffective($effective, 'V', $objects);
        $defaultValue = $password ? null : $this->valueFromEffective($effective, 'DV', $objects);
        $options = in_array($fieldType, ['Btn', 'Ch'], true) ? $this->optionsFromEffective($effective, $objects) : [];
        $widgets = $this->widgetsForField($widgetRefs, $objects, $defaultAppearance, $effective, $pageIndexes, $pageWidgets, $fieldNamesByObject);
        $widgets = $this->widgetsWithButtonExportOptions($widgets, $fieldType, $options);
        $widgets = $this->widgetsWithCurrentValueState($widgets, $fieldType, $flags, $value);
        $fieldHierarchy = $this->fieldHierarchyBoundary($currentHierarchyPath, $effective, $inherited, $objectNumber, $password);
        $valueState = $this->fieldValueState($fieldType, $flags, $effective, $password, $value, $defaultValue, $options, $widgets, $objects);
        $valueState['hierarchy_boundary'] = $this->fieldHierarchyValueState($fieldHierarchy);
        $maxLengthReview = $fieldType === 'Tx'
            ? $this->maxLengthReviewForField($objectNumber, $name, $effective, $value, $defaultValue, $password, $objects)
            : null;
        if ($maxLengthReview !== null) {
            $valueState['max_length'] = $maxLengthReview['max_length'];
            $valueState['max_length_source'] = $maxLengthReview['max_length_source'];
            $valueState['max_length_source_object'] = $maxLengthReview['max_length_source_object'];
            $valueState['max_length_inherited'] = $maxLengthReview['max_length_inherited'];
            $valueState['current_value_length'] = $maxLengthReview['current_value_length'];
            $valueState['current_value_exceeds_max_length'] = $maxLengthReview['current_value_exceeds_max_length'];
            $valueState['max_length_enforced_on_import'] = false;
            $valueState['current_value_truncated_for_import'] = false;
        }
        $widgetCurrentBaseReview = $fieldType === 'Btn'
            ? $this->widgetCurrentBaseReviewForField($objectNumber, $name, $valueState, $widgets)
            : null;
        $buttonExportReview = $fieldType === 'Btn' && $options !== []
            ? $this->buttonExportReviewForField($objectNumber, $name, $valueState, $options, $widgets)
            : null;

        $actionReview = $this->actionsWithReviewFromDictionary($body, $objects, $fieldNamesByObject, 'field', $objectNumber);

        $field = [
            'object' => $objectNumber,
            'name' => $name,
            'partial_name' => $partialName,
            'alternate_name' => $alternateName,
            'mapping_name' => $mappingName ?? $name,
            'field_type' => $fieldType,
            'field_type_label' => $this->fieldTypeLabel($fieldType),
            'flags' => $flags,
            'flag_names' => $this->flagNames($flags, $fieldType),
            'value' => $value,
            'value_redacted' => $password,
            'default_value' => $defaultValue,
            'max_length' => $maxLengthReview['max_length'] ?? null,
            'value_state' => $valueState,
            'field_hierarchy' => $fieldHierarchy,
            'field_name_review' => $this->fieldNameReview($objectNumber, $name, $partialName, $alternateName, $mappingName, $fieldType, $currentHierarchyPath),
            'default_appearance' => $defaultAppearance,
            'actions' => $actionReview['actions'],
            'action_review' => $actionReview['review'],
            'widgets' => $widgets,
        ];

        if ($widgetCurrentBaseReview !== null) {
            $field['widget_current_base_review'] = $widgetCurrentBaseReview;
        }
        if ($buttonExportReview !== null) {
            $field['button_export_review'] = $buttonExportReview;
        }
        if (isset($valueState['rich_text_review']) && is_array($valueState['rich_text_review'])) {
            $field['rich_text_review'] = $valueState['rich_text_review'];
        }
        if ($maxLengthReview !== null) {
            $field['max_length_review'] = $maxLengthReview;
        }
        if ($fieldType === 'Ch') {
            $field['options'] = $options;
        }
        if ($fieldType === 'Btn' && $options !== []) {
            $field['button_export_options'] = $options;
        }
        if ($fieldType === 'Sig') {
            $field['signature'] = isset($effective['V'])
                ? $this->signatureMetadataFromValue($effective['V']['value'], $objects, $fieldNamesByObject)
                : null;
            $field['signature_seed_value'] = $this->signatureSeedValueFromField($body, $objects);
            $field['signature_lock'] = $this->signatureLockFromField($body, $objects, $fieldNamesByObject);
            $field['certifying_signature'] = false;
        }

        return [$field];
    }

    /**
     * @param list<array{object: int, partial_name: string|null, full_name: string, alternate_name: string|null, mapping_name: string|null}> $path
     * @param array<string, array{value: string, source: string, source_object: int|null}> $effective
     * @param array<string, array{value: string, source: string, source_object: int|null}> $inherited
     * @return array<string, mixed>
     */
    private function fieldHierarchyBoundary(array $path, array $effective, array $inherited, int $terminalObject, bool $password): array
    {
        $attributeSources = [];
        $inheritedAttributes = [];
        $localAttributes = [];
        $localValueAttributes = [];
        foreach (['FT', 'Ff', 'V', 'DV', 'RV', 'DS', 'DA', 'DR', 'Q', 'Opt', 'I', 'MaxLen'] as $name) {
            if (!isset($effective[$name])) {
                continue;
            }

            $sourceObject = $effective[$name]['source_object'];
            $attributeSources[$name] = [
                'source' => $effective[$name]['source'],
                'source_object' => $sourceObject,
                'inherited' => $sourceObject !== $terminalObject,
            ];
            if ($sourceObject === $terminalObject) {
                $localAttributes[] = $name;
                if (in_array($name, ['V', 'DV', 'RV', 'DS'], true)) {
                    $localValueAttributes[] = $name;
                }
                continue;
            }

            $inheritedAttributes[] = $name;
        }

        $currentValueSourceObject = $effective['V']['source_object'] ?? null;
        $defaultValueSourceObject = $effective['DV']['source_object'] ?? null;
        $hasParentCurrentValue = isset($effective['V']) && $currentValueSourceObject !== null && $currentValueSourceObject !== $terminalObject;
        $hasParentDefaultValue = isset($effective['DV']) && $defaultValueSourceObject !== null && $defaultValueSourceObject !== $terminalObject;
        $terminalHasCurrentValue = isset($effective['V']) && $currentValueSourceObject === $terminalObject;
        $terminalHasDefaultValue = isset($effective['DV']) && $defaultValueSourceObject === $terminalObject;
        $maxLengthSourceObject = $effective['MaxLen']['source_object'] ?? null;
        $hasParentMaxLength = isset($effective['MaxLen']) && $maxLengthSourceObject !== null && $maxLengthSourceObject !== $terminalObject;
        $terminalHasMaxLength = isset($effective['MaxLen']) && $maxLengthSourceObject === $terminalObject;

        return [
            'source' => 'acroform_field_hierarchy_value_boundary',
            'terminal_object' => $terminalObject,
            'terminal_name' => $path[count($path) - 1]['full_name'] ?? '#' . $terminalObject,
            'depth' => max(0, count($path) - 1),
            'path' => $path,
            'ancestor_objects' => array_values(array_map(
                static fn (array $entry): int => $entry['object'],
                array_slice($path, 0, -1)
            )),
            'attribute_sources' => $attributeSources,
            'inherited_attributes' => $inheritedAttributes,
            'local_attributes' => $localAttributes,
            'local_value_attributes' => $localValueAttributes,
            'field_type_source_object' => $effective['FT']['source_object'] ?? null,
            'flags_source_object' => $effective['Ff']['source_object'] ?? null,
            'current_value_source_object' => $currentValueSourceObject,
            'default_value_source_object' => $defaultValueSourceObject,
            'max_length_source_object' => $maxLengthSourceObject,
            'current_value_inherited' => $hasParentCurrentValue,
            'default_value_inherited' => $hasParentDefaultValue,
            'max_length_inherited' => $hasParentMaxLength,
            'terminal_has_current_value' => $terminalHasCurrentValue,
            'terminal_has_default_value' => $terminalHasDefaultValue,
            'terminal_has_max_length' => $terminalHasMaxLength,
            'terminal_overrides_parent_value' => $terminalHasCurrentValue && isset($inherited['V']),
            'terminal_overrides_parent_default' => $terminalHasDefaultValue && isset($inherited['DV']),
            'terminal_overrides_parent_max_length' => $terminalHasMaxLength && isset($inherited['MaxLen']),
            'value_redacted' => $password,
            'value_used_for_import' => $password ? false : isset($effective['V']),
            'executes_form_actions' => false,
            'executes_javascript' => false,
        ];
    }

    /**
     * @param list<array{object: int, partial_name: string|null, full_name: string, alternate_name: string|null, mapping_name: string|null}> $path
     * @return array<string, mixed>
     */
    private function fieldNameReview(
        int $fieldObject,
        string $fieldName,
        ?string $partialName,
        ?string $alternateName,
        ?string $mappingName,
        ?string $fieldType,
        array $path
    ): array {
        $wordpressLabel = $alternateName !== null && $alternateName !== ''
            ? $alternateName
            : (($mappingName !== null && $mappingName !== '') ? $mappingName : $fieldName);

        return [
            'source' => 'acroform_field_name_review_boundary',
            'field_object' => $fieldObject,
            'field_name' => $fieldName,
            'partial_name' => $partialName,
            'alternate_name' => $alternateName,
            'mapping_name' => $mappingName ?? $fieldName,
            'explicit_mapping_name' => $mappingName !== null,
            'field_type' => $fieldType,
            'wordpress_label' => $wordpressLabel,
            'path' => $path,
            'path_objects' => array_values(array_map(
                static fn (array $entry): int => $entry['object'],
                $path
            )),
            'partial_name_path' => array_values(array_map(
                static fn (array $entry): ?string => $entry['partial_name'],
                $path
            )),
            'alternate_name_path' => array_values(array_map(
                static fn (array $entry): ?string => $entry['alternate_name'],
                $path
            )),
            'mapping_name_path' => array_values(array_map(
                static fn (array $entry): ?string => $entry['mapping_name'],
                $path
            )),
            'review_only' => true,
            'alternate_name_used_as_visible_text' => false,
            'mapping_name_used_as_visible_text' => false,
            'field_value_used_as_visible_text' => false,
            'executes_form_actions' => false,
            'executes_javascript' => false,
        ];
    }

    /**
     * @param array<string, mixed> $fieldHierarchy
     * @return array<string, mixed>
     */
    private function fieldHierarchyValueState(array $fieldHierarchy): array
    {
        $currentSource = null;
        if (($fieldHierarchy['terminal_has_current_value'] ?? false) === true) {
            $currentSource = ($fieldHierarchy['terminal_overrides_parent_value'] ?? false) === true
                ? 'field_terminal_override'
                : 'field_terminal';
        } elseif (($fieldHierarchy['current_value_inherited'] ?? false) === true) {
            $currentSource = 'field_hierarchy_inherited';
        } elseif (($fieldHierarchy['value_redacted'] ?? false) === true) {
            $currentSource = 'redacted';
        }

        return [
            'source' => 'acroform_field_hierarchy_value_boundary',
            'current_value_source' => $currentSource,
            'current_value_source_object' => $fieldHierarchy['current_value_source_object'] ?? null,
            'default_value_source_object' => $fieldHierarchy['default_value_source_object'] ?? null,
            'max_length_source_object' => $fieldHierarchy['max_length_source_object'] ?? null,
            'current_value_inherited' => (bool) ($fieldHierarchy['current_value_inherited'] ?? false),
            'default_value_inherited' => (bool) ($fieldHierarchy['default_value_inherited'] ?? false),
            'max_length_inherited' => (bool) ($fieldHierarchy['max_length_inherited'] ?? false),
            'terminal_overrides_parent_value' => (bool) ($fieldHierarchy['terminal_overrides_parent_value'] ?? false),
            'terminal_overrides_parent_default' => (bool) ($fieldHierarchy['terminal_overrides_parent_default'] ?? false),
            'terminal_overrides_parent_max_length' => (bool) ($fieldHierarchy['terminal_overrides_parent_max_length'] ?? false),
            'path_depth' => (int) ($fieldHierarchy['depth'] ?? 0),
            'value_redacted' => (bool) ($fieldHierarchy['value_redacted'] ?? false),
            'value_used_for_import' => (bool) ($fieldHierarchy['value_used_for_import'] ?? false),
            'executes_form_actions' => false,
            'executes_javascript' => false,
        ];
    }

    /**
     * @param array<string, array{value: string, source: string, source_object: int|null}> $effective
     * @return array<string, mixed>|null
     */
    private function maxLengthReviewForField(
        int $fieldObject,
        string $fieldName,
        array $effective,
        mixed $value,
        mixed $defaultValue,
        bool $password,
        array $objects
    ): ?array {
        if (!isset($effective['MaxLen'])) {
            return null;
        }

        $maxLength = $this->integerFromEffectiveOrNull($effective, 'MaxLen', $objects);
        if ($maxLength === null) {
            return null;
        }

        $sourceObject = $effective['MaxLen']['source_object'];
        $currentText = $password ? null : $this->displayValue($value);
        $defaultText = $password ? null : $this->displayValue($defaultValue);
        $currentLength = $currentText === null ? null : $this->utf8CharacterLength($currentText);
        $defaultLength = $defaultText === null ? null : $this->utf8CharacterLength($defaultText);
        $valid = $maxLength >= 0;

        return [
            'source' => 'acroform_text_maxlen_boundary',
            'field_name' => $fieldName,
            'field_object' => $fieldObject,
            'max_length' => $maxLength,
            'max_length_valid' => $valid,
            'max_length_source' => $effective['MaxLen']['source'],
            'max_length_source_object' => $sourceObject,
            'max_length_inherited' => $sourceObject !== null && $sourceObject !== $fieldObject,
            'value_redacted' => $password,
            'current_value_length' => $currentLength,
            'default_value_length' => $defaultLength,
            'current_value_exceeds_max_length' => $valid && $currentLength !== null ? $currentLength > $maxLength : null,
            'default_value_exceeds_max_length' => $valid && $defaultLength !== null ? $defaultLength > $maxLength : null,
            'password_value_length_exposed' => false,
            'max_length_enforced_on_import' => false,
            'current_value_truncated_for_import' => false,
            'default_value_truncated_for_reset_review' => false,
            'executes_form_actions' => false,
            'executes_javascript' => false,
        ];
    }

    private function utf8CharacterLength(string $value): int
    {
        if (preg_match_all('/./us', $value, $matches) !== false) {
            return count($matches[0]);
        }

        return strlen($value);
    }

    /**
     * @return array{doc_mdp: array<string, mixed>|null}
     */
    private function emptyPermissions(): array
    {
        return ['doc_mdp' => null];
    }

    /**
     * @param array<int, string> $objects
     * @return array{doc_mdp: array<string, mixed>|null}
     */
    private function documentPermissions(string $catalog, array $objects): array
    {
        $permsValue = $this->valueAfterName($catalog, 'Perms');
        $perms = $permsValue === null ? null : $this->resolvedDictionaryFromValue($permsValue, $objects);
        if ($perms === null) {
            return $this->emptyPermissions();
        }

        $docMdpValue = $this->valueAfterName($perms['body'], 'DocMDP');
        $signature = $docMdpValue === null ? null : $this->signatureMetadataFromValue($docMdpValue, $objects);
        if ($signature === null) {
            return $this->emptyPermissions();
        }

        $docMdpTransform = $this->docMdpTransformFromSignatureMetadata($signature);

        return [
            'doc_mdp' => [
                'signature_object' => $signature['object'],
                'signature_name' => $signature['name'],
                'signed_at' => $signature['signed_at'],
                'permission_level' => $docMdpTransform['permission_level'] ?? null,
                'permission_label' => $docMdpTransform['permission_label'] ?? 'unknown',
                'allowed_changes' => $docMdpTransform['allowed_changes'] ?? [],
                'transform_params_version' => $docMdpTransform['transform_params_version'] ?? null,
                'source' => 'catalog_perms_doc_mdp',
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @param array<string, mixed> $permissions
     * @return list<array<string, mixed>>
     */
    private function markCertifyingSignatureFields(array $fields, array $permissions): array
    {
        $docMdpObject = is_array($permissions['doc_mdp'] ?? null)
            ? ($permissions['doc_mdp']['signature_object'] ?? null)
            : null;
        if (!is_int($docMdpObject)) {
            return $fields;
        }

        foreach ($fields as $index => $field) {
            $signature = $field['signature'] ?? null;
            if (!is_array($signature) || ($signature['object'] ?? null) !== $docMdpObject) {
                continue;
            }

            $fields[$index]['certifying_signature'] = true;
            $fields[$index]['signature']['certifying_signature'] = true;
        }

        return $fields;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function signatureSeedValueFromField(string $fieldBody, array $objects): ?array
    {
        $seedValue = $this->valueAfterName($fieldBody, 'SV');
        $seed = $seedValue === null ? null : $this->resolvedDictionaryFromValue($seedValue, $objects);
        if ($seed === null) {
            return null;
        }

        $body = $seed['body'];
        $flags = $this->numberValueAfterName($body, 'Ff') ?? 0;

        return [
            'object' => $seed['object'],
            'type' => $this->pdfNameValueAfterName($body, 'Type'),
            'source' => 'signature_field_seed_value_dictionary',
            'flags' => $flags,
            'required_constraints' => $this->signatureSeedRequiredConstraints($flags),
            'filter' => $this->pdfNameValueAfterName($body, 'Filter'),
            'filter_required' => $this->signatureSeedConstraintRequired($flags, 1),
            'subfilters' => $this->scalarListValueAfterName($body, 'SubFilter', $objects),
            'subfilter_required' => $this->signatureSeedConstraintRequired($flags, 2),
            'parser_version' => $this->realValueAfterName($body, 'V'),
            'parser_version_required' => $this->signatureSeedConstraintRequired($flags, 4),
            'reasons' => $this->scalarListValueAfterName($body, 'Reasons', $objects),
            'reason_required' => $this->signatureSeedConstraintRequired($flags, 8),
            'legal_attestations' => $this->scalarListValueAfterName($body, 'LegalAttestation', $objects),
            'legal_attestation_required' => $this->signatureSeedConstraintRequired($flags, 16),
            'add_revision_info' => $this->boolValueAfterName($body, 'AddRevInfo'),
            'add_revision_info_required' => $this->signatureSeedConstraintRequired($flags, 32),
            'digest_methods' => $this->scalarListValueAfterName($body, 'DigestMethod', $objects),
            'digest_method_required' => $this->signatureSeedConstraintRequired($flags, 64),
            'mdp' => $this->signatureSeedMdp($body, $objects),
            'timestamp' => $this->signatureSeedTimestamp($body, $objects),
            'executes_signing' => false,
            'executes_action' => false,
        ];
    }

    /**
     * @return list<string>
     */
    private function signatureSeedRequiredConstraints(int $flags): array
    {
        $required = [];
        foreach (self::SIGNATURE_SEED_REQUIRED_FLAGS as $bit => $name) {
            if ($this->signatureSeedConstraintRequired($flags, $bit)) {
                $required[] = $name;
            }
        }

        return $required;
    }

    private function signatureSeedConstraintRequired(int $flags, int $bit): bool
    {
        return ($flags & $bit) !== 0;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function signatureSeedMdp(string $seedBody, array $objects): ?array
    {
        $value = $this->valueAfterName($seedBody, 'MDP');
        $mdp = $value === null ? null : $this->resolvedDictionaryFromValue($value, $objects);
        if ($mdp === null) {
            return null;
        }

        $level = $this->numberValueAfterName($mdp['body'], 'P');

        return [
            'object' => $mdp['object'],
            'permission_level' => $level,
            'permission_valid' => in_array($level, [0, 1, 2, 3], true),
            'signature_type' => match ($level) {
                0 => 'ordinary_signature',
                1, 2, 3 => 'certifying_signature',
                default => 'unknown',
            },
            'permission_label' => $level === 0 ? 'ordinary_signature' : $this->docMdpPermissionLabel($level),
            'allowed_changes' => in_array($level, [1, 2, 3], true) ? $this->docMdpAllowedChanges($level) : [],
            'source' => 'signature_seed_value_mdp',
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function signatureSeedTimestamp(string $seedBody, array $objects): ?array
    {
        $value = $this->valueAfterName($seedBody, 'TimeStamp');
        $timestamp = $value === null ? null : $this->resolvedDictionaryFromValue($value, $objects);
        if ($timestamp === null) {
            return null;
        }

        $flags = $this->numberValueAfterName($timestamp['body'], 'Ff') ?? 0;

        return [
            'object' => $timestamp['object'],
            'url' => $this->pdfStringValueAfterName($timestamp['body'], 'URL', $objects),
            'flags' => $flags,
            'required' => ($flags & 1) !== 0,
            'source' => 'signature_seed_value_timestamp',
        ];
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, string> $fieldNamesByObject
     * @return array<string, mixed>|null
     */
    private function signatureLockFromField(string $fieldBody, array $objects, array $fieldNamesByObject): ?array
    {
        $lockValue = $this->valueAfterName($fieldBody, 'Lock');
        $lock = $lockValue === null ? null : $this->resolvedDictionaryFromValue($lockValue, $objects);
        if ($lock === null) {
            return null;
        }

        $body = $lock['body'];
        $action = $this->pdfNameValueAfterName($body, 'Action');
        $fields = $this->signatureLockFieldNames($body, $objects, $fieldNamesByObject);
        $permissionLevel = $this->numberValueAfterName($body, 'P');

        return [
            'object' => $lock['object'],
            'type' => $this->pdfNameValueAfterName($body, 'Type'),
            'source' => 'signature_field_lock_dictionary',
            'action' => $action,
            'action_valid' => in_array($action, ['All', 'Include', 'Exclude'], true),
            'action_label' => $this->signatureLockActionLabel($action),
            'field_names' => $fields,
            'field_count' => count($fields),
            'locks_all_fields' => $action === 'All',
            'included_fields' => $action === 'Include' ? $fields : [],
            'excluded_fields' => $action === 'Exclude' ? $fields : [],
            'permission_level' => $permissionLevel,
            'permission_valid' => $permissionLevel === null || in_array($permissionLevel, [1, 2, 3], true),
            'permission_label' => $permissionLevel === null ? null : $this->docMdpPermissionLabel($permissionLevel),
            'allowed_changes' => $permissionLevel === null ? [] : $this->docMdpAllowedChanges($permissionLevel),
            'executes_action' => false,
        ];
    }

    private function signatureLockActionLabel(?string $action): string
    {
        return match ($action) {
            'All' => 'lock_all_fields',
            'Include' => 'lock_included_fields',
            'Exclude' => 'lock_all_except_excluded_fields',
            default => 'unknown',
        };
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, string> $fieldNamesByObject
     * @return list<string>
     */
    private function signatureLockFieldNames(string $lockBody, array $objects, array $fieldNamesByObject): array
    {
        $value = $this->valueAfterName($lockBody, 'Fields');
        if ($value === null || !str_starts_with(trim($value), '[')) {
            return [];
        }

        $body = $this->arrayBodyFromValue($value);
        if ($body === null) {
            return [];
        }

        $names = [];
        $offset = 0;
        while ($offset < strlen($body)) {
            $this->skipWhitespace($body, $offset);
            if ($offset >= strlen($body)) {
                break;
            }

            $referenceEnd = null;
            $reference = $this->readIndirectReferenceAt($body, $offset, $referenceEnd);
            if ($reference !== null && $referenceEnd !== null) {
                $objectNumber = $reference['object'];
                if (
                    $this->referenceGenerationMatches($objectNumber, $reference['generation'], $objects)
                    && isset($fieldNamesByObject[$objectNumber])
                    && !in_array($fieldNamesByObject[$objectNumber], $names, true)
                ) {
                    $names[] = $fieldNamesByObject[$objectNumber];
                }
                $offset = $referenceEnd;
                continue;
            }

            $scalar = $this->readScalarAt($body, $offset, $objects, $scalarEnd);
            if ($scalar !== null) {
                if ($scalar['value'] !== '' && !in_array($scalar['value'], $names, true)) {
                    $names[] = $scalar['value'];
                }
                $offset = $scalar['end'];
                continue;
            }
            if ($scalarEnd !== null && $scalarEnd > $offset) {
                $offset = $scalarEnd;
                continue;
            }

            $offset++;
        }

        return $names;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, string> $fieldNamesByObject
     * @return list<array{object: int, field_name: string|null}>
     */
    private function calculationOrderFromAcroForm(string $acroForm, array $objects, array $fieldNamesByObject): array
    {
        $value = $this->valueAfterName($acroForm, 'CO');
        if ($value === null || !str_starts_with(trim($value), '[')) {
            return [];
        }

        $body = $this->arrayBodyFromValue($value);
        if ($body === null) {
            return [];
        }

        $order = [];
        foreach (array_values(array_unique($this->reviewObjectReferencesWithCurrentGenerationBoundary($body, $objects))) as $objectNumber) {
            $order[] = [
                'object' => $objectNumber,
                'field_name' => $fieldNamesByObject[$objectNumber] ?? null,
            ];
        }

        return $order;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, string> $fieldNamesByObject
     * @return list<array<string, mixed>>
     */
    private function calculationOrderReviewFromAcroForm(string $acroForm, array $objects, array $fieldNamesByObject): array
    {
        $value = $this->valueAfterName($acroForm, 'CO');
        if ($value === null || !str_starts_with(trim($value), '[')) {
            return [];
        }

        $body = $this->arrayBodyFromValue($value);
        if ($body === null) {
            return [];
        }

        $reviews = [];
        foreach (array_values(array_unique($this->reviewObjectReferencesWithCurrentGenerationBoundary($body, $objects))) as $index => $objectNumber) {
            $reviews[] = $this->calculationOrderReviewEntry(
                $index,
                $objectNumber,
                $objects,
                $fieldNamesByObject
            );
        }

        return $reviews;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, string> $fieldNamesByObject
     * @return array<string, mixed>
     */
    private function calculationOrderReviewEntry(
        int $index,
        int $objectNumber,
        array $objects,
        array $fieldNamesByObject
    ): array {
        $body = isset($objects[$objectNumber])
            ? ($this->dictionaryObjectBody($objects[$objectNumber]) ?? trim($objects[$objectNumber]))
            : null;
        $isWidget = is_string($body) && $this->isWidget($body);
        $parentFieldObject = is_string($body) ? $this->validObjectReferenceValueAfterName($body, 'Parent', $objects) : null;
        $fieldObject = $parentFieldObject !== null && isset($fieldNamesByObject[$parentFieldObject])
            ? $parentFieldObject
            : (isset($fieldNamesByObject[$objectNumber]) ? $objectNumber : null);
        $fieldName = $fieldObject !== null
            ? ($fieldNamesByObject[$fieldObject] ?? null)
            : ($fieldNamesByObject[$objectNumber] ?? null);
        $targetKind = 'unresolved';
        if ($body !== null && $isWidget && $parentFieldObject !== null) {
            $targetKind = 'widget';
        } elseif ($body !== null && $isWidget) {
            $targetKind = 'field_widget';
        } elseif ($body !== null && isset($fieldNamesByObject[$objectNumber])) {
            $targetKind = 'field';
        } elseif ($body !== null) {
            $targetKind = 'non_field_object';
        }

        $appearance = $isWidget && $body !== null
            ? $this->calculationOrderWidgetAppearanceReview($body, $objects)
            : $this->emptyCalculationOrderAppearanceReview();

        return [
            'source' => 'acroform_calculation_order_review_boundary',
            'index' => $index,
            'object' => $objectNumber,
            'target_kind' => $targetKind,
            'field_name' => $fieldName,
            'field_object' => $fieldObject,
            'widget_object' => $isWidget ? $objectNumber : null,
            'resolved_from_widget' => $isWidget && $fieldObject !== null && $fieldObject !== $objectNumber,
            'unresolved' => $fieldName === null,
            'appearance_state' => $appearance['appearance_state'],
            'appearance_states' => $appearance['appearance_states'],
            'normal_appearance_type' => $appearance['normal_appearance_type'],
            'selected_appearance_state' => $appearance['selected_appearance_state'],
            'selected_appearance_object' => $appearance['selected_appearance_object'],
            'selected_appearance_decoded_sha256' => $appearance['selected_appearance_decoded_sha256'],
            'state_matches_appearance' => $appearance['state_matches_appearance'],
            'stale_appearance_state' => $appearance['stale_appearance_state'],
            'appearance_value_used_for_calculation' => false,
            'appearance_value_used_for_import' => false,
            'review_only' => true,
            'executes_calculation' => false,
            'executes_javascript' => false,
            'executes_action' => false,
            'executes_appearance_streams' => false,
            'renders_appearances' => false,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function calculationOrderWidgetAppearanceReview(string $widgetBody, array $objects): array
    {
        $appearanceState = $this->pdfNameValueAfterName($widgetBody, 'AS');
        $appearance = $this->normalAppearanceReview($widgetBody, $objects, $appearanceState);
        if ($appearance === null) {
            return $this->emptyCalculationOrderAppearanceReview($appearanceState);
        }

        $selected = is_array($appearance['selected_appearance'] ?? null) ? $appearance['selected_appearance'] : null;

        return [
            'appearance_state' => $appearanceState,
            'appearance_states' => $appearance['available_states'] ?? [],
            'normal_appearance_type' => $appearance['normal_appearance_type'] ?? null,
            'selected_appearance_state' => $appearance['selected_state'] ?? null,
            'selected_appearance_object' => is_array($selected) ? ($selected['object'] ?? null) : null,
            'selected_appearance_decoded_sha256' => is_array($selected) ? ($selected['decoded_sha256'] ?? null) : null,
            'state_matches_appearance' => $appearance['state_matches_appearance'] ?? null,
            'stale_appearance_state' => $appearance['stale_appearance_state'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyCalculationOrderAppearanceReview(?string $appearanceState = null): array
    {
        return [
            'appearance_state' => $appearanceState,
            'appearance_states' => [],
            'normal_appearance_type' => null,
            'selected_appearance_state' => null,
            'selected_appearance_object' => null,
            'selected_appearance_decoded_sha256' => null,
            'state_matches_appearance' => null,
            'stale_appearance_state' => null,
        ];
    }

    /**
     * @param array<string, mixed> $signature
     * @return array<string, mixed>|null
     */
    private function docMdpTransformFromSignatureMetadata(array $signature): ?array
    {
        foreach ($signature['reference_transforms'] ?? [] as $transform) {
            if (is_array($transform) && ($transform['transform_method'] ?? null) === 'DocMDP') {
                return $transform;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function signatureMetadataFromValue(string $value, array $objects, array $fieldNamesByObject = []): ?array
    {
        $signature = $this->resolvedDictionaryFromValue($value, $objects);
        if ($signature === null) {
            return null;
        }

        $body = $signature['body'];
        $contentsValue = $this->valueAfterName($body, 'Contents');
        $metadata = [
            'object' => $signature['object'],
            'filter' => $this->pdfNameValueAfterName($body, 'Filter'),
            'subfilter' => $this->pdfNameValueAfterName($body, 'SubFilter'),
            'name' => $this->pdfStringValueAfterName($body, 'Name', $objects),
            'reason' => $this->pdfStringValueAfterName($body, 'Reason', $objects),
            'location' => $this->pdfStringValueAfterName($body, 'Location', $objects),
            'contact_info' => $this->pdfStringValueAfterName($body, 'ContactInfo', $objects),
            'signed_at' => $this->pdfStringValueAfterName($body, 'M', $objects),
            'byte_range' => $this->integerArrayValueAfterName($body, 'ByteRange'),
            'contents_present' => $contentsValue !== null,
            'contents_length_bytes' => $contentsValue === null ? null : $this->signatureContentsLength($contentsValue, $objects),
            'contents_digest' => $contentsValue === null
                ? $this->emptySignatureContentsDigest()
                : $this->signatureContentsDigest($contentsValue, $objects),
            'reference_transforms' => $this->signatureReferenceTransforms($body, $objects, $fieldNamesByObject),
            'certifying_signature' => false,
        ];

        $docMdp = $this->docMdpTransformFromSignatureMetadata($metadata);
        if ($docMdp !== null) {
            $metadata['doc_mdp'] = $docMdp;
        }

        return $metadata;
    }

    /**
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function signatureReferenceTransforms(string $signatureBody, array $objects, array $fieldNamesByObject = []): array
    {
        $reference = $this->valueAfterName($signatureBody, 'Reference');
        if ($reference === null || !str_starts_with(trim($reference), '[')) {
            return [];
        }

        $body = $this->arrayBodyFromValue($reference);
        if ($body === null) {
            return [];
        }

        $transforms = [];
        foreach ($this->dictionaryValuesFromArrayBody($body, $objects) as $dictionary) {
            $transformBody = $dictionary['body'];
            $method = $this->pdfNameValueAfterName($transformBody, 'TransformMethod');
            if ($method === null) {
                continue;
            }

            $transform = [
                'object' => $dictionary['object'],
                'type' => $this->pdfNameValueAfterName($transformBody, 'Type'),
                'transform_method' => $method,
                'data_object' => $this->objectReferenceValueAfterName($transformBody, 'Data'),
                'digest_method' => $this->pdfNameValueAfterName($transformBody, 'DigestMethod'),
                'digest_value_present' => $this->valueAfterName($transformBody, 'DigestValue') !== null,
                'digest_value_exposed' => false,
            ];

            $params = $this->signatureTransformParams($transformBody, $objects, $method, $fieldNamesByObject);
            if ($params !== null) {
                $transform += $params;
            }

            $transforms[] = $transform;
        }

        return $transforms;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function signatureTransformParams(string $referenceBody, array $objects, string $method, array $fieldNamesByObject): ?array
    {
        $paramsValue = $this->valueAfterName($referenceBody, 'TransformParams');
        $params = $paramsValue === null ? null : $this->resolvedDictionaryFromValue($paramsValue, $objects);
        if ($method === 'DocMDP') {
            return $this->docMdpTransformParams($params);
        }
        if ($params === null) {
            return null;
        }

        return match ($method) {
            'FieldMDP' => $this->fieldMdpTransformParams($params, $objects, $fieldNamesByObject),
            'UR', 'UR3' => $this->usageRightsTransformParams($params, $objects, $method),
            default => $this->genericSignatureTransformParams($params),
        };
    }

    /**
     * @param array{body: string, object: int|null}|null $params
     * @return array<string, mixed>|null
     */
    private function docMdpTransformParams(?array $params): ?array
    {
        $paramsBody = $params['body'] ?? '';
        $level = $paramsBody === '' ? null : $this->numberValueAfterName($paramsBody, 'P');
        if ($level === null) {
            $level = 2;
        }

        return [
            'transform_category' => 'certification_permissions',
            'transform_params_object' => $params['object'] ?? null,
            'transform_params_type' => $paramsBody === '' ? null : $this->pdfNameValueAfterName($paramsBody, 'Type'),
            'transform_params_version' => $paramsBody === '' ? null : $this->pdfNameValueAfterName($paramsBody, 'V'),
            'permission_level' => $level,
            'permission_valid' => in_array($level, [1, 2, 3], true),
            'permission_label' => $this->docMdpPermissionLabel($level),
            'allowed_changes' => $this->docMdpAllowedChanges($level),
            'review_only' => true,
            'executes_signature_validation' => false,
            'executes_action' => false,
        ];
    }

    /**
     * @param array{body: string, object: int|null} $params
     * @param array<int, string> $objects
     * @param array<int, string> $fieldNamesByObject
     * @return array<string, mixed>
     */
    private function fieldMdpTransformParams(array $params, array $objects, array $fieldNamesByObject): array
    {
        $body = $params['body'];
        $action = $this->pdfNameValueAfterName($body, 'Action');
        $fieldNames = $this->signatureLockFieldNames($body, $objects, $fieldNamesByObject);

        return [
            'transform_category' => 'field_modification_permissions',
            'transform_params_object' => $params['object'],
            'transform_params_type' => $this->pdfNameValueAfterName($body, 'Type'),
            'transform_params_version' => $this->pdfNameValueAfterName($body, 'V'),
            'action' => $action,
            'action_valid' => in_array($action, ['All', 'Include', 'Exclude'], true),
            'action_label' => $this->fieldMdpActionLabel($action),
            'field_names' => $fieldNames,
            'included_fields' => $action === 'Include' ? $fieldNames : [],
            'excluded_fields' => $action === 'Exclude' ? $fieldNames : [],
            'locks_all_fields' => $action === 'All',
            'review_only' => true,
            'executes_signature_validation' => false,
            'executes_action' => false,
        ];
    }

    private function fieldMdpActionLabel(?string $action): string
    {
        return match ($action) {
            'All' => 'locks_all_fields',
            'Include' => 'locks_included_fields',
            'Exclude' => 'locks_all_except_excluded_fields',
            default => 'unknown',
        };
    }

    /**
     * @param array{body: string, object: int|null} $params
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function usageRightsTransformParams(array $params, array $objects, string $method): array
    {
        $body = $params['body'];
        $rights = $this->usageRightsFromTransformParams($body, $objects);

        return [
            'transform_category' => $method === 'UR3' ? 'usage_rights_ur3' : 'usage_rights',
            'transform_params_object' => $params['object'],
            'transform_params_type' => $this->pdfNameValueAfterName($body, 'Type'),
            'transform_params_version' => $this->pdfNameValueAfterName($body, 'V'),
            'message' => $this->pdfStringValueAfterName($body, 'Msg', $objects),
            'rights' => $rights,
            'right_categories' => array_keys(array_filter($rights, static fn (array $values): bool => $values !== [])),
            'right_count' => array_sum(array_map('count', $rights)),
            'review_only' => true,
            'executes_rights_enforcement' => false,
            'executes_signature_validation' => false,
            'executes_action' => false,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, list<string>>
     */
    private function usageRightsFromTransformParams(string $body, array $objects): array
    {
        return [
            'document' => $this->scalarListValueAfterName($body, 'Document', $objects),
            'form' => $this->scalarListValueAfterName($body, 'Form', $objects),
            'signature' => $this->scalarListValueAfterName($body, 'Signature', $objects),
            'annotations' => $this->scalarListValueAfterName($body, 'Annots', $objects),
            'embedded_files' => $this->scalarListValueAfterName($body, 'EF', $objects),
        ];
    }

    /**
     * @param array{body: string, object: int|null} $params
     * @return array<string, mixed>
     */
    private function genericSignatureTransformParams(array $params): array
    {
        $body = $params['body'];

        return [
            'transform_category' => 'unknown',
            'transform_params_object' => $params['object'],
            'transform_params_type' => $this->pdfNameValueAfterName($body, 'Type'),
            'transform_params_version' => $this->pdfNameValueAfterName($body, 'V'),
            'review_only' => true,
            'executes_signature_validation' => false,
            'executes_action' => false,
        ];
    }

    private function docMdpPermissionLabel(?int $level): string
    {
        return match ($level) {
            1 => 'no_changes',
            2 => 'form_fill_templates_signatures',
            3 => 'form_fill_templates_signatures_annotations',
            default => 'unknown',
        };
    }

    /**
     * @return list<string>
     */
    private function docMdpAllowedChanges(?int $level): array
    {
        return match ($level) {
            1 => [],
            2 => ['fill_forms', 'instantiate_page_templates', 'sign'],
            3 => ['fill_forms', 'instantiate_page_templates', 'sign', 'create_modify_delete_annotations'],
            default => [],
        };
    }

    /**
     * @param array<string, array{value: string, source: string, source_object: int|null}> $inherited
     * @return array<string, array{value: string, source: string, source_object: int|null}>
     */
    private function mergeFieldAttributes(string $body, array $inherited, int $objectNumber): array
    {
        $effective = $inherited;
        foreach (['FT', 'Ff', 'V', 'DV', 'RV', 'DS', 'DA', 'DR', 'Q', 'Opt', 'I', 'MaxLen'] as $name) {
            $value = $this->valueAfterName($body, $name);
            if ($value === null) {
                continue;
            }

            $effective[$name] = [
                'value' => $value,
                'source' => 'field',
                'source_object' => $objectNumber,
            ];
        }

        return $effective;
    }

    /**
     * @return array<string, array{value: string, source: string, source_object: int|null}>
     */
    private function acroFormDefaults(string $acroForm): array
    {
        $defaults = [];
        foreach (['DA', 'DR', 'Q'] as $name) {
            $value = $this->valueAfterName($acroForm, $name);
            if ($value !== null) {
                $defaults[$name] = [
                    'value' => $value,
                    'source' => 'acroform',
                    'source_object' => null,
                ];
            }
        }

        return $defaults;
    }

    /**
     * @param array<string, array{value: string, source: string, source_object: int|null}> $effective
     * @param array<int, string> $objects
     */
    private function fieldType(array $effective, array $objects): ?string
    {
        if (!isset($effective['FT'])) {
            return null;
        }

        return $this->pdfNameFromValueResolvingObjects($effective['FT']['value'], $objects);
    }

    private function fieldTypeLabel(?string $fieldType): string
    {
        return match ($fieldType) {
            'Tx' => 'text',
            'Ch' => 'choice',
            'Btn' => 'button',
            'Sig' => 'signature',
            default => 'unknown',
        };
    }

    /**
     * @param array<string, array{value: string, source: string, source_object: int|null}> $effective
     */
    private function integerFromEffective(array $effective, string $name, int $default, array $objects): int
    {
        if (!isset($effective[$name])) {
            return $default;
        }

        return $this->integerFromEffectivePdfValue($effective[$name]['value'], $objects) ?? $default;
    }

    /**
     * @param array<string, array{value: string, source: string, source_object: int|null}> $effective
     */
    private function integerFromEffectiveOrNull(array $effective, string $name, array $objects): ?int
    {
        if (!isset($effective[$name])) {
            return null;
        }

        return $this->integerFromEffectivePdfValue($effective[$name]['value'], $objects);
    }

    /**
     * @param array<int, string> $objects
     */
    private function integerFromEffectivePdfValue(string $value, array $objects): ?int
    {
        $number = $this->pdfNumberFromValue($value, $objects);
        if ($number === null || floor($number) !== $number) {
            return null;
        }

        return (int) $number;
    }

    /**
     * @param array<string, array{value: string, source: string, source_object: int|null}> $effective
     */
    private function valueFromEffective(array $effective, string $name, array $objects): mixed
    {
        if (!isset($effective[$name])) {
            return null;
        }

        return $this->pdfValueToPhpValue($effective[$name]['value'], $objects);
    }

    /**
     * @param array<string, array{value: string, source: string, source_object: int|null}> $effective
     * @param list<array{export: string, label: string}> $options
     * @param list<array<string, mixed>> $widgets
     * @return array<string, mixed>
     */
    private function fieldValueState(
        ?string $fieldType,
        int $flags,
        array $effective,
        bool $password,
        mixed $value,
        mixed $defaultValue,
        array $options,
        array $widgets,
        array $objects
    ): array {
        $hasCurrent = isset($effective['V']);
        $hasDefault = isset($effective['DV']);
        $state = [
            'source' => 'acroform_current_value_state',
            'field_type' => $fieldType,
            'value_redacted' => $password,
            'has_current_value' => $hasCurrent,
            'has_default_value' => $hasDefault,
            'current' => $password ? null : $value,
            'default' => $password ? null : $defaultValue,
            'display_value' => $password ? '[redacted]' : $this->displayValue($value),
            'current_source' => $effective['V']['source'] ?? null,
            'current_source_object' => $effective['V']['source_object'] ?? null,
            'default_source' => $effective['DV']['source'] ?? null,
            'default_source_object' => $effective['DV']['source_object'] ?? null,
            'changed_from_default' => $password || !$hasDefault ? null : !$this->valuesMatch($value, $defaultValue),
        ];

        if ($fieldType === 'Ch') {
            $explicitIndices = $this->integerArrayFromEffective($effective, 'I', $objects);
            $selectedIndices = $this->selectedChoiceIndices($value, $options, $explicitIndices);
            $state += [
                'choice_values' => $password ? [] : $this->valueList($value),
                'default_choice_values' => $password ? [] : $this->valueList($defaultValue),
                'selected_indices' => $selectedIndices,
                'selected_indices_source' => $explicitIndices === [] ? ($selectedIndices === [] ? null : 'inferred_from_value') : 'field',
                'selected_options' => $this->selectedChoiceOptions($value, $options, $selectedIndices),
                'unmatched_values' => $this->unmatchedChoiceValues($value, $options),
            ];
        }

        $richTextReview = $this->richTextReviewFromEffective($effective, $objects, $fieldType, $flags, $password, $value);
        if ($richTextReview !== null) {
            $state['rich_text_review'] = $richTextReview;
        }

        if ($fieldType === 'Btn') {
            $checkedWidgets = array_values(array_filter(
                $widgets,
                static fn (array $widget): bool => ($widget['checked'] ?? false) === true
            ));
            $currentBaseStates = $this->widgetCurrentBaseStateRows($widgets);
            $hasButtonExportOptions = $options !== [];
            $appearanceValues = [];
            foreach ($checkedWidgets as $widget) {
                $exportValue = $widget['export_value'] ?? $widget['appearance_state'] ?? null;
                if (is_string($exportValue) && $exportValue !== '' && !in_array($exportValue, $appearanceValues, true)) {
                    $appearanceValues[] = $exportValue;
                }
            }

            $effectiveCurrent = $value;
            $stateSource = $hasCurrent ? 'field_value' : 'missing_or_off';
            if (!$hasCurrent && $appearanceValues !== []) {
                $effectiveCurrent = count($appearanceValues) === 1 ? $appearanceValues[0] : $appearanceValues;
                $stateSource = 'widget_appearance_state';
            }
            $selectedExportValues = $hasButtonExportOptions
                ? $this->buttonSelectedExportValues($widgets, $hasCurrent)
                : [];
            $effectiveExportValue = $selectedExportValues === []
                ? null
                : (count($selectedExportValues) === 1 ? $selectedExportValues[0] : $selectedExportValues);
            $exportValueSource = $effectiveExportValue === null
                ? null
                : $this->buttonExportValueSourceForSelection($widgets, $hasCurrent);

            $state += [
                'button_kind' => $this->buttonKind($flags),
                'current_state' => $password ? null : $value,
                'default_state' => $password ? null : $defaultValue,
                'effective_current_state' => $password ? null : $effectiveCurrent,
                'state_source' => $stateSource,
                'on_values' => $this->buttonOnValues($widgets),
                'export_values' => $hasButtonExportOptions ? $this->buttonExportValues($widgets) : [],
                'selected_export_values' => $selectedExportValues,
                'effective_export_value' => $password ? null : $effectiveExportValue,
                'export_value_source' => $password ? null : $exportValueSource,
                'export_option_count' => $hasButtonExportOptions ? count($options) : 0,
                'checked_widget_count' => count($checkedWidgets),
                'widget_state_consistent' => $this->widgetsConsistentWithFieldValue($widgets),
                'widget_current_base_states' => $currentBaseStates,
                'stale_widget_appearance_state_count' => count(array_filter(
                    $currentBaseStates,
                    static fn (array $state): bool => ($state['stale_appearance_state'] ?? false) === true
                )),
            ];
            $state['display_value'] = $password ? '[redacted]' : $this->displayValue($effectiveCurrent);
            $state['changed_from_default'] = $password || !$hasDefault
                ? null
                : !$this->buttonValuesMatch($effectiveCurrent, $defaultValue);
        }

        if ($password) {
            $state['state_source'] = 'redacted_password';
        }

        return $state;
    }

    /**
     * @param array<string, array{value: string, source: string, source_object: int|null}> $effective
     * @return array<string, mixed>|null
     */
    private function richTextReviewFromEffective(
        array $effective,
        array $objects,
        ?string $fieldType,
        int $flags,
        bool $password,
        mixed $plainValue
    ): ?array {
        $hasRichTextFlag = $fieldType === 'Tx' && $this->hasFlagBit($flags, 26);
        if (!isset($effective['RV']) && !$hasRichTextFlag) {
            return null;
        }

        $richText = isset($effective['RV']) && !$password
            ? $this->valueFromEffective($effective, 'RV', $objects)
            : null;
        $richTextString = $this->displayValue($richText);
        $plainPreview = $richTextString === null ? null : $this->plainTextFromRichText($richTextString);
        $richPreview = $richTextString === null ? null : $this->boundedPreview($richTextString, 180);
        $defaultStyle = isset($effective['DS']) && !$password
            ? $this->valueFromEffective($effective, 'DS', $objects)
            : null;
        $defaultStyleString = $this->displayValue($defaultStyle);
        $defaultStylePreview = $defaultStyleString === null ? null : $this->boundedPreview($defaultStyleString, 180);

        return [
            'source' => 'acroform_rich_text_value_review_boundary',
            'field_type' => $fieldType,
            'rich_text_flag' => $hasRichTextFlag,
            'has_rich_text_value' => $richTextString !== null,
            'rich_text_source' => $effective['RV']['source'] ?? null,
            'rich_text_source_object' => $effective['RV']['source_object'] ?? null,
            'has_default_style' => $defaultStyleString !== null,
            'default_style_source' => $effective['DS']['source'] ?? null,
            'default_style_source_object' => $effective['DS']['source_object'] ?? null,
            'default_style_preview' => $defaultStylePreview === null ? null : $defaultStylePreview['preview'],
            'default_style_truncated' => $defaultStylePreview['truncated'] ?? false,
            'default_style_bytes' => $defaultStyleString === null ? 0 : strlen($defaultStyleString),
            'default_style_sha256' => $defaultStyleString === null ? null : hash('sha256', $defaultStyleString),
            'default_style_used_for_import' => false,
            'default_style_used_for_submit' => false,
            'default_style_exposed_as_css' => false,
            'plain_value' => $password ? null : $this->displayValue($plainValue),
            'plain_value_used_for_import' => !$password && $plainValue !== null,
            'rich_text_preview' => $richPreview === null ? null : $richPreview['preview'],
            'rich_text_truncated' => $richPreview['truncated'] ?? false,
            'rich_text_bytes' => $richTextString === null ? 0 : strlen($richTextString),
            'rich_text_sha256' => $richTextString === null ? null : hash('sha256', $richTextString),
            'rich_text_plain_preview' => $plainPreview,
            'rich_text_used_for_import' => false,
            'rich_text_used_for_submit' => false,
            'rich_text_used_for_reset' => false,
            'payload_text_exposed' => false,
            'imports_rich_text_html' => false,
            'executes_rich_text_javascript' => false,
            'executes_form_actions' => false,
        ];
    }

    private function plainTextFromRichText(string $richText): string
    {
        $text = preg_replace('/<\?(?:.|\R)*?\?>|<!\[CDATA\[|]]>/s', ' ', $richText) ?? $richText;
        $text = preg_replace('/<[^>]+>/', ' ', $text) ?? $text;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return $this->boundedPreview($text, 180)['preview'];
    }

    /**
     * @return array{preview: string, truncated: bool}
     */
    private function boundedPreview(string $value, int $limit): array
    {
        $normalized = trim(preg_replace('/[\x00-\x1f\x7f]+/', ' ', $value) ?? $value);
        $limit = max(16, $limit);
        if (strlen($normalized) <= $limit) {
            return ['preview' => $normalized, 'truncated' => false];
        }

        return ['preview' => substr($normalized, 0, $limit) . '...', 'truncated' => true];
    }

    private function buttonKind(int $flags): string
    {
        if ($this->hasFlagBit($flags, 17)) {
            return 'push_button';
        }

        return $this->hasFlagBit($flags, 16) ? 'radio' : 'checkbox';
    }

    /**
     * @param list<array<string, mixed>> $widgets
     * @return list<string>
     */
    private function buttonOnValues(array $widgets): array
    {
        $values = [];
        foreach ($widgets as $widget) {
            foreach ($widget['appearance_states'] ?? [] as $state) {
                if (!is_string($state) || $state === 'Off' || in_array($state, $values, true)) {
                    continue;
                }

                $values[] = $state;
            }
        }

        return $values;
    }

    /**
     * @param list<array<string, mixed>> $widgets
     */
    private function widgetsConsistentWithFieldValue(array $widgets): ?bool
    {
        $sawComparison = false;
        foreach ($widgets as $widget) {
            $matches = $widget['state_matches_field_value'] ?? null;
            if ($matches === null) {
                continue;
            }

            $sawComparison = true;
            if ($matches !== true) {
                return false;
            }
        }

        return $sawComparison ? true : null;
    }

    /**
     * @param list<array<string, mixed>> $widgets
     * @param list<array{export: string, label: string}> $options
     * @return list<array<string, mixed>>
     */
    private function widgetsWithButtonExportOptions(array $widgets, ?string $fieldType, array $options): array
    {
        if ($fieldType !== 'Btn' || $options === []) {
            return $widgets;
        }

        foreach ($widgets as $index => $widget) {
            if (!isset($options[$index])) {
                continue;
            }

            $widgets[$index]['export_option_index'] = $index;
            $widgets[$index]['export_option_export'] = $options[$index]['export'];
            $widgets[$index]['export_option_label'] = $options[$index]['label'];
        }

        return $widgets;
    }

    /**
     * @param list<array<string, mixed>> $widgets
     * @return list<array<string, mixed>>
     */
    private function widgetsWithCurrentValueState(array $widgets, ?string $fieldType, int $flags, mixed $fieldValue): array
    {
        if ($fieldType !== 'Btn' || $this->hasFlagBit($flags, 17)) {
            return $widgets;
        }

        $fieldValues = $this->valueList($fieldValue);
        foreach ($widgets as $index => $widget) {
            $appearanceState = $widget['appearance_state'] ?? null;
            $onStates = $this->widgetOnAppearanceStates($widget);
            $appearanceStateValid = $this->widgetAppearanceStateIsValid($widget, $appearanceState, $onStates);
            $checked = is_string($appearanceState)
                && $appearanceState !== ''
                && $appearanceState !== 'Off'
                && $appearanceStateValid === true;
            $exportValue = $this->widgetExportValue($widget);
            $exportValueSource = $this->widgetExportValueSource($widget, $exportValue);
            $selectedByField = $this->widgetSelectedByFieldValue($widget, $fieldValues, $exportValue, $onStates);
            $stateMatchesFieldValue = $fieldValue === null || $exportValue === null
                ? ($appearanceStateValid === false ? false : null)
                : ($appearanceStateValid === false ? false : ($checked ? $selectedByField : !$selectedByField));
            $widgets[$index]['checked'] = $checked;
            $widgets[$index]['export_value'] = $exportValue;
            $widgets[$index]['export_value_source'] = $exportValueSource;
            $widgets[$index]['selected_by_field_value'] = $selectedByField;
            $widgets[$index]['state_matches_field_value'] = $stateMatchesFieldValue;
            $widgets[$index]['appearance_state_valid'] = $appearanceStateValid;
            $widgets[$index]['current_base_state'] = $this->widgetAppearanceStateCurrentBaseReview(
                $widget,
                $fieldType,
                $flags,
                $fieldValue,
                $exportValue,
                $checked,
                $selectedByField,
                $stateMatchesFieldValue,
                $appearanceStateValid,
                $onStates
            );
        }

        return $widgets;
    }

    /**
     * @param array<string, mixed> $widget
     */
    private function widgetExportValue(array $widget): ?string
    {
        $optionExport = $widget['export_option_export'] ?? null;
        if (is_string($optionExport) && $optionExport !== '') {
            return $optionExport;
        }

        $appearanceState = $widget['appearance_state'] ?? null;
        $states = $this->widgetOnAppearanceStates($widget);

        if (is_string($appearanceState) && $appearanceState !== 'Off' && in_array($appearanceState, $states, true)) {
            return $appearanceState;
        }

        if ($states !== []) {
            return $states[0];
        }

        return is_string($appearanceState) && $appearanceState !== 'Off' ? $appearanceState : null;
    }

    /**
     * @param array<string, mixed> $widget
     */
    private function widgetExportValueSource(array $widget, ?string $exportValue): ?string
    {
        $optionExport = $widget['export_option_export'] ?? null;
        if (is_string($optionExport) && $optionExport !== '' && $optionExport === $exportValue) {
            return 'button_opt';
        }

        return $exportValue === null ? null : 'appearance_state';
    }

    /**
     * @param array<string, mixed> $widget
     * @param list<string> $fieldValues
     * @param list<string> $onStates
     */
    private function widgetSelectedByFieldValue(array $widget, array $fieldValues, ?string $exportValue, array $onStates): bool
    {
        if ($fieldValues === []) {
            return false;
        }

        if ($exportValue !== null && in_array($exportValue, $fieldValues, true)) {
            return true;
        }

        $appearanceState = $widget['appearance_state'] ?? null;
        if (is_string($appearanceState) && $appearanceState !== 'Off' && in_array($appearanceState, $fieldValues, true)) {
            return true;
        }

        foreach ($onStates as $onState) {
            if (in_array($onState, $fieldValues, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $widget
     * @return list<string>
     */
    private function widgetOnAppearanceStates(array $widget): array
    {
        return array_values(array_filter(
            $widget['appearance_states'] ?? [],
            static fn (mixed $state): bool => is_string($state) && $state !== 'Off'
        ));
    }

    /**
     * @param array<string, mixed> $widget
     * @param list<string> $onStates
     */
    private function widgetAppearanceStateIsValid(array $widget, mixed $appearanceState, array $onStates): ?bool
    {
        if (!is_string($appearanceState) || $appearanceState === '') {
            return null;
        }

        if ($appearanceState === 'Off') {
            return true;
        }

        if ($onStates === []) {
            return true;
        }

        return in_array($appearanceState, $onStates, true);
    }

    /**
     * @param array<string, mixed> $widget
     * @param list<string> $onStates
     * @return array<string, mixed>
     */
    private function widgetAppearanceStateCurrentBaseReview(
        array $widget,
        ?string $fieldType,
        int $flags,
        mixed $fieldValue,
        ?string $exportValue,
        bool $checked,
        bool $selectedByField,
        ?bool $stateMatchesFieldValue,
        ?bool $appearanceStateValid,
        array $onStates
    ): array {
        $appearanceState = $widget['appearance_state'] ?? null;
        $fieldValues = $this->valueList($fieldValue);
        $normalAppearance = is_array($widget['normal_appearance'] ?? null) ? $widget['normal_appearance'] : null;
        $staleAppearanceState = $appearanceStateValid === false
            || ($normalAppearance !== null && ($normalAppearance['stale_appearance_state'] ?? false) === true);
        $current = $fieldValue;
        $currentSource = $fieldValue === null ? 'missing_or_off' : 'field_value';
        if ($fieldValue === null && $checked && $exportValue !== null) {
            $current = $exportValue;
            $currentSource = 'widget_appearance_state';
        }

        return [
            'source' => 'acroform_widget_appearance_state_currentbase',
            'widget_object' => $widget['object'] ?? null,
            'field_type' => $fieldType,
            'button_kind' => $fieldType === 'Btn' ? $this->buttonKind($flags) : null,
            'appearance_state' => is_string($appearanceState) ? $appearanceState : null,
            'appearance_state_valid' => $appearanceStateValid,
            'appearance_states' => array_values(array_filter(
                $widget['appearance_states'] ?? [],
                static fn (mixed $state): bool => is_string($state)
            )),
            'on_states' => $onStates,
            'export_value' => $exportValue,
            'export_value_source' => $widget['export_value_source'] ?? $this->widgetExportValueSource($widget, $exportValue),
            'export_option_index' => $widget['export_option_index'] ?? null,
            'export_option_label' => $widget['export_option_label'] ?? null,
            'field_value_matches_appearance_state' => is_string($appearanceState)
                && $appearanceState !== 'Off'
                && in_array($appearanceState, $fieldValues, true),
            'field_value_matches_export_value' => $exportValue !== null && in_array($exportValue, $fieldValues, true),
            'checked_by_widget_appearance' => $checked,
            'selected_by_field_value' => $selectedByField,
            'state_matches_field_value' => $stateMatchesFieldValue,
            'stale_appearance_state' => $staleAppearanceState,
            'normal_appearance_type' => $normalAppearance['normal_appearance_type'] ?? null,
            'selected_appearance_state' => $normalAppearance['selected_state'] ?? null,
            'selected_appearance_object' => is_array($normalAppearance['selected_appearance'] ?? null)
                ? ($normalAppearance['selected_appearance']['object'] ?? null)
                : null,
            'current' => $current,
            'current_source' => $currentSource,
            'field_value_authoritative' => $fieldValue !== null,
            'appearance_value_used_for_import' => false,
            'appearance_payload_text_exposed' => false,
            'executes_appearance_streams' => false,
            'renders_appearances' => false,
        ];
    }

    /**
     * @param list<array<string, mixed>> $widgets
     * @return list<array<string, mixed>>
     */
    private function widgetCurrentBaseStateRows(array $widgets): array
    {
        $rows = [];
        foreach ($widgets as $widget) {
            if (is_array($widget['current_base_state'] ?? null)) {
                $rows[] = $widget['current_base_state'];
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $valueState
     * @param list<array<string, mixed>> $widgets
     * @return array<string, mixed>
     */
    private function widgetCurrentBaseReviewForField(int $fieldObject, string $fieldName, array $valueState, array $widgets): array
    {
        $stateRows = $this->widgetCurrentBaseStateRows($widgets);

        return [
            'source' => 'acroform_widget_appearance_state_currentbase',
            'field_object' => $fieldObject,
            'field_name' => $fieldName,
            'button_kind' => $valueState['button_kind'] ?? null,
            'current' => $valueState['effective_current_state'] ?? ($valueState['current'] ?? null),
            'current_source' => $valueState['state_source'] ?? ($valueState['current_source'] ?? null),
            'default' => $valueState['default_state'] ?? ($valueState['default'] ?? null),
            'changed_from_default' => $valueState['changed_from_default'] ?? null,
            'widget_count' => count($stateRows),
            'checked_widget_count' => $valueState['checked_widget_count'] ?? 0,
            'state_consistent' => $valueState['widget_state_consistent'] ?? null,
            'stale_appearance_state_count' => count(array_filter(
                $stateRows,
                static fn (array $state): bool => ($state['stale_appearance_state'] ?? false) === true
            )),
            'stale_appearance_widgets' => array_values(array_filter(array_map(
                static fn (array $state): ?int => ($state['stale_appearance_state'] ?? false) === true
                    ? (is_int($state['widget_object'] ?? null) ? $state['widget_object'] : null)
                    : null,
                $stateRows
            ))),
            'appearance_value_used_for_import' => false,
            'appearance_payload_text_exposed' => false,
            'executes_appearance_streams' => false,
            'renders_appearances' => false,
        ];
    }

    /**
     * @param array<string, mixed> $valueState
     * @param list<array{export: string, label: string}> $options
     * @param list<array<string, mixed>> $widgets
     * @return array<string, mixed>
     */
    private function buttonExportReviewForField(int $fieldObject, string $fieldName, array $valueState, array $options, array $widgets): array
    {
        $selectedExportValues = $this->stringListValue($valueState['selected_export_values'] ?? []);
        $exportValue = $valueState['effective_export_value'] ?? null;

        return [
            'source' => 'acroform_widget_appearance_export_currentbase',
            'field_object' => $fieldObject,
            'field_name' => $fieldName,
            'button_kind' => $valueState['button_kind'] ?? null,
            'field_current_state' => $valueState['current_state'] ?? null,
            'field_current_source' => $valueState['current_source'] ?? null,
            'effective_current_state' => $valueState['effective_current_state'] ?? null,
            'state_source' => $valueState['state_source'] ?? null,
            'effective_export_value' => $exportValue,
            'export_value_source' => $valueState['export_value_source'] ?? null,
            'option_count' => count($options),
            'option_export_values' => $this->uniqueStrings(array_map(
                static fn (array $option): string => $option['export'],
                $options
            )),
            'option_labels' => $this->uniqueStrings(array_map(
                static fn (array $option): string => $option['label'],
                $options
            )),
            'widget_count' => count($widgets),
            'widget_export_values' => $this->buttonExportValues($widgets),
            'widget_export_value_sources' => $this->uniqueStrings(array_values(array_filter(array_map(
                static fn (array $widget): ?string => is_string($widget['export_value_source'] ?? null) ? $widget['export_value_source'] : null,
                $widgets
            )))),
            'selected_export_values' => $selectedExportValues,
            'checked_export_values' => $this->buttonCheckedExportValues($widgets),
            'appearance_on_values' => $this->buttonOnValues($widgets),
            'checked_widget_count' => $valueState['checked_widget_count'] ?? 0,
            'state_consistent' => $valueState['widget_state_consistent'] ?? null,
            'field_value_authoritative' => ($valueState['has_current_value'] ?? false) === true,
            'uses_button_opt_for_export' => in_array('button_opt', $this->uniqueStrings(array_values(array_filter(array_map(
                static fn (array $widget): ?string => is_string($widget['export_value_source'] ?? null) ? $widget['export_value_source'] : null,
                $widgets
            )))), true),
            'export_value_used_for_submit_review' => $exportValue !== null && $exportValue !== [],
            'appearance_value_used_for_import' => false,
            'export_value_used_for_import' => false,
            'appearance_payload_text_exposed' => false,
            'executes_appearance_streams' => false,
            'renders_appearances' => false,
            'executes_form_actions' => false,
            'executes_javascript' => false,
        ];
    }

    /**
     * @param list<array<string, mixed>> $widgets
     * @return list<string>
     */
    private function buttonExportValues(array $widgets): array
    {
        return $this->uniqueStrings(array_values(array_filter(array_map(
            static fn (array $widget): ?string => is_string($widget['export_value'] ?? null) ? $widget['export_value'] : null,
            $widgets
        ))));
    }

    /**
     * @param list<array<string, mixed>> $widgets
     * @return list<string>
     */
    private function buttonCheckedExportValues(array $widgets): array
    {
        return $this->uniqueStrings(array_values(array_filter(array_map(
            static fn (array $widget): ?string => ($widget['checked'] ?? false) === true && is_string($widget['export_value'] ?? null)
                ? $widget['export_value']
                : null,
            $widgets
        ))));
    }

    /**
     * @param list<array<string, mixed>> $widgets
     * @return list<string>
     */
    private function buttonSelectedExportValues(array $widgets, bool $hasCurrent): array
    {
        return $this->uniqueStrings(array_values(array_filter(array_map(
            static function (array $widget) use ($hasCurrent): ?string {
                $selected = $hasCurrent
                    ? (($widget['selected_by_field_value'] ?? false) === true)
                    : (($widget['checked'] ?? false) === true);

                return $selected && is_string($widget['export_value'] ?? null) ? $widget['export_value'] : null;
            },
            $widgets
        ))));
    }

    /**
     * @param list<array<string, mixed>> $widgets
     */
    private function buttonExportValueSourceForSelection(array $widgets, bool $hasCurrent): ?string
    {
        $sources = [];
        foreach ($widgets as $widget) {
            $selected = $hasCurrent
                ? (($widget['selected_by_field_value'] ?? false) === true)
                : (($widget['checked'] ?? false) === true);
            if (!$selected || !is_string($widget['export_value_source'] ?? null)) {
                continue;
            }

            $sources[] = $widget['export_value_source'];
        }

        $sources = $this->uniqueStrings($sources);
        if (in_array('button_opt', $sources, true)) {
            return 'button_opt';
        }

        return $sources[0] ?? null;
    }

    /**
     * @param array<string, array{value: string, source: string, source_object: int|null}> $effective
     * @return list<int>
     */
    private function integerArrayFromEffective(array $effective, string $name, array $objects): array
    {
        if (!isset($effective[$name])) {
            return [];
        }

        $value = trim($effective[$name]['value']);
        if (!str_starts_with($value, '[')) {
            return [];
        }

        $body = $this->arrayBodyFromValue($value);
        if ($body === null) {
            return [];
        }

        $integers = [];
        $offset = 0;
        $length = strlen($body);
        while ($offset < $length) {
            $this->skipWhitespace($body, $offset);
            if ($offset >= $length) {
                break;
            }
            if ($body[$offset] === '%') {
                $offset = $this->skipPdfComment($body, $offset);
                continue;
            }

            $value = $this->readPdfValueAt($body, $offset, $endOffset);
            if ($value === null || $endOffset === null) {
                $offset++;
                continue;
            }

            $integer = $this->integerFromEffectivePdfValue($value, $objects);
            if ($integer !== null) {
                $integers[] = $integer;
            }
            $offset = $endOffset;
        }

        return $integers;
    }

    /**
     * @param list<array{export: string, label: string}> $options
     * @param list<int> $explicitIndices
     * @return list<int>
     */
    private function selectedChoiceIndices(mixed $value, array $options, array $explicitIndices): array
    {
        if ($explicitIndices !== []) {
            return array_values(array_filter($explicitIndices, static fn (int $index): bool => $index >= 0));
        }

        $indices = [];
        foreach ($this->valueList($value) as $selectedValue) {
            foreach ($options as $index => $option) {
                if (($option['export'] === $selectedValue || $option['label'] === $selectedValue) && !in_array($index, $indices, true)) {
                    $indices[] = $index;
                    break;
                }
            }
        }

        return $indices;
    }

    /**
     * @param list<array{export: string, label: string}> $options
     * @param list<int> $selectedIndices
     * @return list<array{index: int, export: string, label: string}>
     */
    private function selectedChoiceOptions(mixed $value, array $options, array $selectedIndices): array
    {
        $selected = [];
        $seen = [];
        foreach ($this->valueList($value) as $selectedValue) {
            foreach ($options as $index => $option) {
                if ($option['export'] !== $selectedValue && $option['label'] !== $selectedValue) {
                    continue;
                }

                $this->appendSelectedChoiceOption($selected, $seen, $index, $option);
                break;
            }
        }

        foreach ($selectedIndices as $index) {
            if (isset($options[$index])) {
                $this->appendSelectedChoiceOption($selected, $seen, $index, $options[$index]);
            }
        }

        return $selected;
    }

    /**
     * @param list<array{index: int, export: string, label: string}> $selected
     * @param array<string, true> $seen
     * @param array{export: string, label: string} $option
     */
    private function appendSelectedChoiceOption(array &$selected, array &$seen, int $index, array $option): void
    {
        $key = (string) $index . "\0" . $option['export'] . "\0" . $option['label'];
        if (isset($seen[$key])) {
            return;
        }

        $seen[$key] = true;
        $selected[] = [
            'index' => $index,
            'export' => $option['export'],
            'label' => $option['label'],
        ];
    }

    /**
     * @param list<array{export: string, label: string}> $options
     * @return list<string>
     */
    private function unmatchedChoiceValues(mixed $value, array $options): array
    {
        $unmatched = [];
        foreach ($this->valueList($value) as $selectedValue) {
            $matched = false;
            foreach ($options as $option) {
                if ($option['export'] === $selectedValue || $option['label'] === $selectedValue) {
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                $unmatched[] = $selectedValue;
            }
        }

        return $unmatched;
    }

    /**
     * @return list<string>
     */
    private function valueList(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (!is_array($value)) {
            return [(string) $value];
        }

        $values = [];
        foreach ($value as $item) {
            if ($item === null) {
                continue;
            }

            $values[] = (string) $item;
        }

        return $values;
    }

    private function displayValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_array($value) ? implode(', ', $this->valueList($value)) : (string) $value;
    }

    private function valuesMatch(mixed $left, mixed $right): bool
    {
        if (is_array($left) || is_array($right)) {
            return $this->valueList($left) === $this->valueList($right);
        }

        return $left === $right;
    }

    private function buttonValuesMatch(mixed $left, mixed $right): bool
    {
        return $this->normalizedButtonValueList($left) === $this->normalizedButtonValueList($right);
    }

    /**
     * @return list<string>
     */
    private function normalizedButtonValueList(mixed $value): array
    {
        return array_values(array_filter(
            $this->valueList($value),
            static fn (string $state): bool => $state !== 'Off'
        ));
    }

    /**
     * @return list<string>
     */
    private function flagNames(int $flags, ?string $fieldType): array
    {
        $names = [];
        foreach (self::COMMON_FIELD_FLAGS as $bit => $name) {
            if ($this->hasFlagBit($flags, $bit)) {
                $names[] = $name;
            }
        }

        foreach (self::FIELD_TYPE_FLAGS[$fieldType ?? ''] ?? [] as $bit => $name) {
            if ($this->hasFlagBit($flags, $bit)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function hasFlagBit(int $flags, int $oneBasedBit): bool
    {
        return ($flags & (1 << ($oneBasedBit - 1))) !== 0;
    }

    /**
     * @param array<string, array{value: string, source: string, source_object: int|null}> $effective
     * @return array<string, mixed>|null
     */
    private function defaultAppearanceFromEffective(array $effective, array $objects): ?array
    {
        if (!isset($effective['DA'])) {
            return null;
        }

        $raw = $this->pdfValueToString($effective['DA']['value'], []);
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $appearance = $this->parseDefaultAppearance($raw);
        $appearance = $this->defaultAppearanceWithResourceReview($appearance, $effective, $objects);
        $appearance['raw'] = $raw;
        $appearance['source'] = $effective['DA']['source'];
        $appearance['source_object'] = $effective['DA']['source_object'];

        return $appearance;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseDefaultAppearance(string $appearance): array
    {
        $tokens = $this->appearanceTokens($appearance);
        $parsed = [
            'font_resource' => null,
            'font_size' => null,
            'text_color' => null,
            'font_resource_resolved' => false,
            'font_resource_object' => null,
            'font_resource_base_font' => null,
            'font_resource_subtype' => null,
            'font_resource_encoding' => null,
            'font_descriptor_object' => null,
            'font_descriptor_name' => null,
            'font_descriptor_flags' => null,
            'font_weight' => null,
            'default_resource_source' => null,
            'default_resource_source_object' => null,
        ];

        foreach ($tokens as $index => $token) {
            if ($token === 'Tf' && isset($tokens[$index - 2], $tokens[$index - 1])) {
                $font = $tokens[$index - 2];
                $size = $tokens[$index - 1];
                if (is_string($font) && str_starts_with($font, '/') && is_numeric($size)) {
                    $parsed['font_resource'] = $this->decodePdfName($font);
                    $parsed['font_size'] = (float) $size;
                }
                continue;
            }

            if ($token === 'g' && isset($tokens[$index - 1]) && is_numeric($tokens[$index - 1])) {
                $parsed['text_color'] = [
                    'space' => 'DeviceGray',
                    'components' => [(float) $tokens[$index - 1]],
                ];
                continue;
            }

            if ($token === 'rg' && isset($tokens[$index - 3], $tokens[$index - 2], $tokens[$index - 1])) {
                $components = [$tokens[$index - 3], $tokens[$index - 2], $tokens[$index - 1]];
                if ($this->allNumeric($components)) {
                    $parsed['text_color'] = [
                        'space' => 'DeviceRGB',
                        'components' => array_map('floatval', $components),
                    ];
                }
                continue;
            }

            if ($token === 'k' && isset($tokens[$index - 4], $tokens[$index - 3], $tokens[$index - 2], $tokens[$index - 1])) {
                $components = [$tokens[$index - 4], $tokens[$index - 3], $tokens[$index - 2], $tokens[$index - 1]];
                if ($this->allNumeric($components)) {
                    $parsed['text_color'] = [
                        'space' => 'DeviceCMYK',
                        'components' => array_map('floatval', $components),
                    ];
                }
            }
        }

        return $parsed;
    }

    /**
     * @return list<string>
     */
    private function appearanceTokens(string $appearance): array
    {
        if (preg_match_all('/\/(?:#[0-9A-Fa-f]{2}|[^\s\[\]\(\)<>{}\/%])+|[+-]?(?:\d+(?:\.\d*)?|\.\d+)|[A-Za-z][A-Za-z0-9*]*/', $appearance, $matches) === false) {
            return [];
        }

        return $matches[0];
    }

    /**
     * @param list<mixed> $values
     */
    private function allNumeric(array $values): bool
    {
        foreach ($values as $value) {
            if (!is_numeric($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $appearance
     * @param array<string, array{value: string, source: string, source_object: int|null}> $effective
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function defaultAppearanceWithResourceReview(array $appearance, array $effective, array $objects): array
    {
        $fontResource = $appearance['font_resource'] ?? null;
        if (!is_string($fontResource) || $fontResource === '') {
            return $appearance;
        }

        $resources = $this->defaultResourcesFromEffective($effective, $objects);
        $fonts = is_array($resources['fonts'] ?? null) ? $resources['fonts'] : [];
        $font = $fonts[$fontResource] ?? null;
        $appearance['default_resource_source'] = $resources['source'] ?? null;
        $appearance['default_resource_source_object'] = $resources['object'] ?? null;

        if (!is_array($font)) {
            return $appearance;
        }

        $descriptor = is_array($font['font_descriptor'] ?? null) ? $font['font_descriptor'] : [];
        $appearance['font_resource_resolved'] = true;
        $appearance['font_resource_object'] = $font['object'] ?? null;
        $appearance['font_resource_base_font'] = $font['base_font'] ?? null;
        $appearance['font_resource_subtype'] = $font['subtype'] ?? null;
        $appearance['font_resource_encoding'] = $font['encoding'] ?? null;
        $appearance['font_descriptor_object'] = $descriptor['object'] ?? null;
        $appearance['font_descriptor_name'] = $descriptor['font_name'] ?? null;
        $appearance['font_descriptor_flags'] = $descriptor['flags'] ?? null;
        $appearance['font_weight'] = $descriptor['font_weight'] ?? null;

        return $appearance;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDefaultResources(): array
    {
        return [
            'source' => null,
            'object' => null,
            'font_count' => 0,
            'fonts' => [],
            'executes_appearance_streams' => false,
            'renders_appearances' => false,
        ];
    }

    /**
     * @param array<string, array{value: string, source: string, source_object: int|null}> $effective
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function defaultResourcesFromEffective(array $effective, array $objects): array
    {
        if (!isset($effective['DR'])) {
            return $this->emptyDefaultResources();
        }

        $resources = $this->resolvedDictionaryFromValue($effective['DR']['value'], $objects);
        if ($resources === null) {
            return $this->emptyDefaultResources();
        }

        $fonts = $this->fontResourcesFromDefaultResourceDictionary($resources['body'], $objects);

        return [
            'source' => $effective['DR']['source'],
            'object' => $resources['object'],
            'font_count' => count($fonts),
            'fonts' => $fonts,
            'executes_appearance_streams' => false,
            'renders_appearances' => false,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, array<string, mixed>>
     */
    private function fontResourcesFromDefaultResourceDictionary(string $resourceDictionary, array $objects): array
    {
        $fontValue = $this->valueAfterName($resourceDictionary, 'Font');
        if ($fontValue === null) {
            return [];
        }

        $fontDictionary = $this->resolvedDictionaryFromValue($fontValue, $objects);
        if ($fontDictionary === null) {
            return [];
        }

        $fonts = [];
        $body = $fontDictionary['body'];
        $offset = 0;
        $length = strlen($body);
        while ($offset < $length) {
            $this->skipWhitespace($body, $offset);
            if ($offset >= $length) {
                break;
            }

            if ($body[$offset] !== '/') {
                $offset++;
                continue;
            }

            $nameEnd = $this->skipPdfName($body, $offset);
            $resourceName = $this->decodePdfName(substr($body, $offset + 1, $nameEnd - $offset - 1));
            $offset = $nameEnd;
            $this->skipWhitespace($body, $offset);

            $fontObject = null;
            $fontBody = null;
            $referenceEnd = null;
            $reference = $this->readIndirectReferenceAt($body, $offset, $referenceEnd);
            if ($reference !== null && $referenceEnd !== null) {
                $fontObject = $reference['object'];
                $offset = $referenceEnd;
                if (!$this->referenceGenerationMatches($fontObject, $reference['generation'], $objects)) {
                    continue;
                }
                $fontBody = $this->dictionaryObjectBody($objects[$fontObject] ?? '');
            } elseif (substr($body, $offset, 2) === '<<') {
                $endOffset = null;
                $fontBody = $this->readPdfDictionaryAt($body, $offset, $endOffset);
                $offset = $endOffset ?? ($offset + 2);
            } else {
                continue;
            }

            if ($fontBody === null) {
                continue;
            }

            $fonts[$resourceName] = $this->fontResourceReview($resourceName, $fontObject, $fontBody, $objects);
        }

        return $fonts;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function fontResourceReview(string $resourceName, ?int $objectNumber, string $fontBody, array $objects): array
    {
        return [
            'resource_name' => $resourceName,
            'object' => $objectNumber,
            'type' => $this->pdfNameValueAfterName($fontBody, 'Type'),
            'subtype' => $this->pdfNameValueAfterName($fontBody, 'Subtype'),
            'base_font' => $this->pdfNameValueAfterName($fontBody, 'BaseFont'),
            'encoding' => $this->fontEncodingName($fontBody, $objects),
            'font_descriptor' => $this->fontDescriptorReview($fontBody, $objects),
            'executes_appearance_streams' => false,
            'renders_appearances' => false,
        ];
    }

    /**
     * @param array<int, string> $objects
     */
    private function fontEncodingName(string $fontBody, array $objects): ?string
    {
        $value = $this->valueAfterName($fontBody, 'Encoding');
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if (str_starts_with($value, '/')) {
            return $this->decodePdfName($value);
        }

        $reference = $this->objectReferenceFromValue($value);
        if (
            $reference !== null
            && isset($objects[$reference['object']])
            && $this->referenceGenerationMatches($reference['object'], $reference['generation'], $objects)
        ) {
            $object = trim($objects[$reference['object']]);
            if (str_starts_with($object, '/')) {
                return $this->decodePdfName($object);
            }

            $dictionary = $this->dictionaryObjectBody($object) ?? (str_starts_with($object, '<<') ? $this->readPdfDictionaryAt($object, 0) : null);
            return $dictionary === null ? null : $this->pdfNameValueAfterName($dictionary, 'BaseEncoding');
        }

        $dictionary = str_starts_with($value, '<<') ? $this->readPdfDictionaryAt($value, 0) : null;
        return $dictionary === null ? null : $this->pdfNameValueAfterName($dictionary, 'BaseEncoding');
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function fontDescriptorReview(string $fontBody, array $objects): ?array
    {
        $descriptorObject = $this->objectReferenceValueAfterName($fontBody, 'FontDescriptor');
        if ($descriptorObject !== null) {
            $descriptorBody = $this->dictionaryObjectBody($objects[$descriptorObject] ?? '');
        } else {
            $descriptorValue = $this->valueAfterName($fontBody, 'FontDescriptor');
            $descriptorBody = $descriptorValue !== null && str_starts_with(trim($descriptorValue), '<<')
                ? $this->readPdfDictionaryAt($descriptorValue, 0)
                : null;
        }

        if ($descriptorBody === null) {
            return null;
        }

        return [
            'object' => $descriptorObject,
            'font_name' => $this->pdfNameValueAfterName($descriptorBody, 'FontName'),
            'flags' => $this->numberValueAfterName($descriptorBody, 'Flags'),
            'font_weight' => $this->numberValueAfterName($descriptorBody, 'FontWeight'),
        ];
    }

    /**
     * @param list<int> $widgetRefs
     * @param array<int, string> $objects
     * @param array<string, mixed>|null $fieldDefaultAppearance
     * @param array<string, array{value: string, source: string, source_object: int|null}> $effective
     * @param array<int, int> $pageIndexes
     * @param array<int, array{page_index: int, page_object: int}> $pageWidgets
     * @param array<int, string> $fieldNamesByObject
     * @return list<array<string, mixed>>
     */
    private function widgetsForField(
        array $widgetRefs,
        array $objects,
        ?array $fieldDefaultAppearance,
        array $effective,
        array $pageIndexes,
        array $pageWidgets,
        array $fieldNamesByObject
    ): array {
        $widgets = [];
        $seen = [];
        foreach ($widgetRefs as $widgetRef) {
            if (isset($seen[$widgetRef]) || !isset($objects[$widgetRef])) {
                continue;
            }
            $seen[$widgetRef] = true;

            $body = $this->dictionaryObjectBody($objects[$widgetRef]) ?? trim($objects[$widgetRef]);
            $pageObject = $this->validObjectReferenceValueAfterName($body, 'P', $objects) ?? ($pageWidgets[$widgetRef]['page_object'] ?? null);
            $pageIndex = $pageObject !== null && isset($pageIndexes[$pageObject])
                ? $pageIndexes[$pageObject]
                : ($pageWidgets[$widgetRef]['page_index'] ?? null);
            $annotationFlags = $this->numberValueAfterNameResolvingObjects($body, 'F', $objects);
            $widgetAppearance = $this->widgetDefaultAppearance($body, $fieldDefaultAppearance, $effective, $objects, $widgetRef);
            $referencedFromPageAnnots = isset($pageWidgets[$widgetRef]);
            $appearanceState = $this->pdfNameValueAfterName($body, 'AS');
            $normalAppearance = $this->normalAppearanceReview($body, $objects, $appearanceState, $fieldNamesByObject);
            $rolloverAppearance = $this->interactiveAppearanceReview($body, $objects, $appearanceState, 'R', 'rollover', $fieldNamesByObject);
            $downAppearance = $this->interactiveAppearanceReview($body, $objects, $appearanceState, 'D', 'down', $fieldNamesByObject);
            $highlightMode = $this->pdfNameValueAfterName($body, 'H') ?? 'I';
            $appearanceCharacteristics = $this->widgetAppearanceCharacteristics($body, $objects);

            $actionReview = $this->actionsWithReviewFromDictionary($body, $objects, $fieldNamesByObject, 'widget', $widgetRef);

            $widgets[] = [
                'object' => $widgetRef,
                'page_index' => $pageIndex,
                'page_object' => $pageObject,
                'page_annotation_index' => $pageWidgets[$widgetRef]['annotation_index'] ?? null,
                'referenced_from_page_annots' => $referencedFromPageAnnots,
                'rect' => $this->rectFromAnnotation($body, $objects),
                'annotation_flags' => $annotationFlags,
                'annotation_flag_names' => $this->annotationFlagNames($annotationFlags ?? 0),
                'annotation_visibility' => $this->annotationVisibility($annotationFlags ?? 0),
                'hidden' => $this->annotationFlagsHideWidget($annotationFlags ?? 0),
                'visible' => !$this->annotationFlagsHideWidget($annotationFlags ?? 0),
                'printable' => $this->hasFlagBit($annotationFlags ?? 0, 3),
                'no_view' => $this->hasFlagBit($annotationFlags ?? 0, 6),
                'highlight_mode' => $highlightMode,
                'highlight_mode_label' => self::WIDGET_HIGHLIGHT_MODE_LABELS[$highlightMode] ?? 'unknown',
                'appearance_state' => $appearanceState,
                'appearance_states' => is_array($normalAppearance) ? $normalAppearance['available_states'] : [],
                'normal_appearance' => $normalAppearance,
                'rollover_appearance' => $rolloverAppearance,
                'down_appearance' => $downAppearance,
                'appearance_characteristics' => $appearanceCharacteristics,
                'default_appearance' => $widgetAppearance,
                'actions' => $actionReview['actions'],
                'action_review' => $actionReview['review'],
            ];
        }

        return $widgets;
    }

    /**
     * @return list<string>
     */
    private function annotationFlagNames(int $flags): array
    {
        $names = [];
        foreach (self::ANNOTATION_FLAGS as $bit => $name) {
            if ($this->hasFlagBit($flags, $bit)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function annotationFlagsHideWidget(int $flags): bool
    {
        return $this->hasFlagBit($flags, 1)
            || $this->hasFlagBit($flags, 2)
            || $this->hasFlagBit($flags, 6);
    }

    private function annotationVisibility(int $flags): string
    {
        if ($this->hasFlagBit($flags, 2)) {
            return 'hidden';
        }

        if ($this->hasFlagBit($flags, 1)) {
            return 'invisible';
        }

        if ($this->hasFlagBit($flags, 6)) {
            return 'no_view';
        }

        return 'visible';
    }

    /**
     * @param list<int> $fieldRefs
     * @param array<int, string> $objects
     * @return array<int, string>
     */
    private function fieldNamesByObject(array $fieldRefs, array $objects): array
    {
        $names = [];
        foreach ($fieldRefs as $fieldRef) {
            $context = $this->fieldReferenceAncestorContext($fieldRef, $objects, []);
            $this->collectFieldNamesByObject($fieldRef, $objects, $context['name_parts'], $names, []);
        }

        return $names;
    }

    /**
     * @param array<int, string> $names
     * @param array<int, string> $objects
     * @param array<int, array{page_index: int, page_object: int, annotation_index: int}> $pageWidgets
     * @return array<int, string>
     */
    private function fieldNamesWithPageWidgetParents(array $names, array $objects, array $pageWidgets): array
    {
        foreach (array_keys($pageWidgets) as $widgetObject) {
            if (isset($names[$widgetObject]) || !isset($objects[$widgetObject])) {
                continue;
            }

            $body = $this->dictionaryObjectBody($objects[$widgetObject]) ?? trim($objects[$widgetObject]);
            $parentObject = $this->validObjectReferenceValueAfterName($body, 'Parent', $objects);
            if ($parentObject === null || !isset($names[$parentObject])) {
                continue;
            }

            $names[$widgetObject] = $names[$parentObject];
        }

        return $names;
    }

    /**
     * @param list<int> $widgetRefs
     * @param array<int, string> $objects
     * @param array<int, array{page_index: int, page_object: int, annotation_index: int}> $pageWidgets
     * @return list<int>
     */
    private function widgetReferencesForField(int $fieldObject, array $widgetRefs, array $objects, array $pageWidgets): array
    {
        $refs = $widgetRefs;
        foreach (array_keys($pageWidgets) as $widgetObject) {
            if (in_array($widgetObject, $refs, true) || !isset($objects[$widgetObject])) {
                continue;
            }

            $body = $this->dictionaryObjectBody($objects[$widgetObject]) ?? trim($objects[$widgetObject]);
            if (!$this->isWidget($body) || $this->validObjectReferenceValueAfterName($body, 'Parent', $objects) !== $fieldObject) {
                continue;
            }

            $refs[] = $widgetObject;
        }

        return $this->orderedWidgetReferencesByPageAnnotations($refs, $pageWidgets);
    }

    /**
     * @param list<int> $refs
     * @param array<int, array{page_index: int, page_object: int, annotation_index: int}> $pageWidgets
     * @return list<int>
     */
    private function orderedWidgetReferencesByPageAnnotations(array $refs, array $pageWidgets): array
    {
        $originalPositions = [];
        foreach ($refs as $position => $ref) {
            if (!isset($originalPositions[$ref])) {
                $originalPositions[$ref] = $position;
            }
        }

        usort(
            $refs,
            static function (int $left, int $right) use ($pageWidgets, $originalPositions): int {
                $leftPage = $pageWidgets[$left] ?? null;
                $rightPage = $pageWidgets[$right] ?? null;
                if ($leftPage !== null && $rightPage !== null) {
                    $pageCompare = $leftPage['page_index'] <=> $rightPage['page_index'];
                    if ($pageCompare !== 0) {
                        return $pageCompare;
                    }

                    $annotationCompare = $leftPage['annotation_index'] <=> $rightPage['annotation_index'];
                    if ($annotationCompare !== 0) {
                        return $annotationCompare;
                    }
                } elseif ($leftPage !== null || $rightPage !== null) {
                    return $leftPage !== null ? -1 : 1;
                }

                return ($originalPositions[$left] ?? 0) <=> ($originalPositions[$right] ?? 0);
            }
        );

        return $refs;
    }

    /**
     * @param array<int, string> $objects
     * @param list<string> $nameParts
     * @param array<int, string> $names
     * @param array<int, true> $seen
     */
    private function collectFieldNamesByObject(
        int $objectNumber,
        array $objects,
        array $nameParts,
        array &$names,
        array $seen
    ): void {
        if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
            return;
        }

        $seen[$objectNumber] = true;
        $body = $this->dictionaryObjectBody($objects[$objectNumber]) ?? trim($objects[$objectNumber]);
        $partialName = $this->pdfStringValueAfterName($body, 'T', $objects);
        $currentNameParts = $nameParts;
        if ($partialName !== null && $partialName !== '') {
            $currentNameParts[] = $partialName;
        }

        $fieldName = $currentNameParts === [] ? '#' . $objectNumber : implode('.', $currentNameParts);
        if (
            $partialName !== null
            || $this->pdfStringValueAfterName($body, 'TM', $objects) !== null
            || $this->valueAfterName($body, 'FT') !== null
            || $this->valueAfterName($body, 'Kids') !== null
            || $this->isWidget($body)
        ) {
            $names[$objectNumber] = $fieldName;
        }

        foreach ($this->kidReferences($body, $objects) as $kidRef) {
            if (!isset($objects[$kidRef])) {
                continue;
            }

            $kidBody = $this->dictionaryObjectBody($objects[$kidRef]) ?? trim($objects[$kidRef]);
            if ($this->isPureWidget($kidBody)) {
                $names[$kidRef] = $fieldName;
                continue;
            }

            $this->collectFieldNamesByObject($kidRef, $objects, $currentNameParts, $names, $seen);
        }
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @return list<array<string, mixed>>
     */
    private function annotateSubmitResetActionValueReviews(array $fields): array
    {
        $fieldRows = $this->formActionFieldRows($fields);
        foreach ($fields as $fieldIndex => $field) {
            foreach (($field['actions'] ?? []) as $actionIndex => $action) {
                if (is_array($action)) {
                    $fields[$fieldIndex]['actions'][$actionIndex] = $this->actionWithSubmitResetFieldValueReview($action, $fieldRows);
                }
            }

            foreach (($field['widgets'] ?? []) as $widgetIndex => $widget) {
                if (!is_array($widget)) {
                    continue;
                }

                foreach (($widget['actions'] ?? []) as $actionIndex => $action) {
                    if (is_array($action)) {
                        $fields[$fieldIndex]['widgets'][$widgetIndex]['actions'][$actionIndex] = $this->actionWithSubmitResetFieldValueReview($action, $fieldRows);
                    }
                }
            }
        }

        return $fields;
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @return list<array<string, mixed>>
     */
    private function formActionFieldRows(array $fields): array
    {
        $rows = [];
        foreach ($fields as $field) {
            $state = is_array($field['value_state'] ?? null) ? $field['value_state'] : [];
            $richTextReview = is_array($field['rich_text_review'] ?? null)
                ? $field['rich_text_review']
                : (is_array($state['rich_text_review'] ?? null) ? $state['rich_text_review'] : null);
            $rows[] = [
                'object' => $field['object'] ?? null,
                'name' => $field['name'] ?? null,
                'field_type' => $field['field_type'] ?? null,
                'field_type_label' => $field['field_type_label'] ?? null,
                'flags' => $field['flags'] ?? 0,
                'flag_names' => $field['flag_names'] ?? [],
                'no_export' => in_array('no_export', $field['flag_names'] ?? [], true),
                'value_redacted' => (bool) ($field['value_redacted'] ?? false),
                'has_current_value' => (bool) ($state['has_current_value'] ?? false),
                'has_default_value' => (bool) ($state['has_default_value'] ?? false),
                'current' => $state['current'] ?? ($field['value'] ?? null),
                'default' => $state['default'] ?? ($field['default_value'] ?? null),
                'display_value' => $state['display_value'] ?? null,
                'effective_current' => $state['effective_current_state'] ?? ($state['current'] ?? ($field['value'] ?? null)),
                'current_source' => $state['state_source'] ?? ($state['current_source'] ?? null),
                'effective_export_value' => $state['effective_export_value'] ?? null,
                'export_value_source' => $state['export_value_source'] ?? null,
                'button_kind' => $state['button_kind'] ?? null,
                'options' => $field['options'] ?? [],
                'button_export_options' => $field['button_export_options'] ?? [],
                'button_export_review' => is_array($field['button_export_review'] ?? null) ? $field['button_export_review'] : null,
                'choice_values' => $state['choice_values'] ?? [],
                'default_choice_values' => $state['default_choice_values'] ?? [],
                'selected_options' => $state['selected_options'] ?? [],
                'rich_text_review' => $richTextReview,
                'default_appearance' => is_array($field['default_appearance'] ?? null) ? $field['default_appearance'] : null,
                'widgets' => $this->arrayRows($field['widgets'] ?? []),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $fieldRows
     * @return array<string, mixed>
     */
    private function actionWithSubmitResetFieldValueReview(array $action, array $fieldRows): array
    {
        $actionType = $action['action_type'] ?? null;
        if ($actionType === 'SubmitForm') {
            $review = $this->submitFormFieldValueReview($action, $fieldRows);
            $action['field_value_review'] = $review;
            $action['action_resource_review'] = $this->submitResetActionResourceReview($action, $review);
        } elseif ($actionType === 'ResetForm') {
            $review = $this->resetFormFieldValueReview($action, $fieldRows);
            $action['field_value_review'] = $review;
            $action['action_resource_review'] = $this->submitResetActionResourceReview($action, $review);
        }

        return $action;
    }

    /**
     * @param list<array<string, mixed>> $fieldRows
     * @return array<string, mixed>
     */
    private function submitFormFieldValueReview(array $action, array $fieldRows): array
    {
        $candidateRows = $this->actionCandidateFieldRows($action, $fieldRows, 'all_exportable');
        $includeNoValue = (bool) ($action['include_no_value_fields'] ?? false);
        $rows = [];
        foreach ($candidateRows as $row) {
            $submitValue = $this->submitValueForFieldRow($row);
            $included = true;
            $omitReason = null;
            if (($row['button_kind'] ?? null) === 'push_button') {
                $included = false;
                $omitReason = 'push_button';
            } elseif (($row['no_export'] ?? false) === true) {
                $included = false;
                $omitReason = 'no_export';
            } elseif ($submitValue['has_value'] === false && !$includeNoValue) {
                $included = false;
                $omitReason = 'no_value';
            }

            $rows[] = $this->fieldReviewBaseRow($row) + [
                'selected_by_action' => true,
                'submit_included' => $included,
                'omit_reason' => $omitReason,
                'submit_value' => $included ? $submitValue['value'] : null,
                'submit_value_source' => $included ? $submitValue['source'] : null,
                'choice_review' => $this->choiceReviewForFieldRow($row, $row['current'] ?? null),
                'rich_text_review' => $row['rich_text_review'] ?? null,
                'rich_text_included' => false,
            ];
        }

        return [
            'source' => 'acroform_choice_richtext_submit_reset_review_boundary',
            'action_type' => 'SubmitForm',
            'fields_mode' => $action['fields_mode'] ?? 'all_exportable',
            'requested_submit_format' => $action['requested_submit_format'] ?? ($action['submit_format'] ?? 'fdf'),
            'html_format_requested' => (bool) ($action['html_format_requested'] ?? (($action['submit_format'] ?? null) === 'html')),
            'xfdf_requested' => (bool) ($action['xfdf_requested'] ?? false),
            'submit_pdf_requested' => (bool) ($action['submit_pdf_requested'] ?? false),
            'get_method_requested' => (bool) ($action['get_method_requested'] ?? false),
            'submit_coordinates_requested' => (bool) ($action['submit_coordinates_requested'] ?? false),
            'include_append_saves_requested' => (bool) ($action['include_append_saves_requested'] ?? false),
            'include_annotations_requested' => (bool) ($action['include_annotations_requested'] ?? false),
            'canonical_format_requested' => (bool) ($action['canonical_format_requested'] ?? false),
            'exclude_non_user_annotations_requested' => (bool) ($action['exclude_non_user_annotations_requested'] ?? false),
            'exclude_f_key_requested' => (bool) ($action['exclude_f_key_requested'] ?? false),
            'embed_form_requested' => (bool) ($action['embed_form_requested'] ?? false),
            'candidate_field_count' => count($candidateRows),
            'included_field_count' => count(array_filter($rows, static fn (array $row): bool => ($row['submit_included'] ?? false) === true)),
            'excluded_field_count' => count(array_filter($rows, static fn (array $row): bool => ($row['submit_included'] ?? false) !== true)),
            'submitted_field_names' => $this->fieldNamesFromRows(array_filter($rows, static fn (array $row): bool => ($row['submit_included'] ?? false) === true)),
            'no_export_excluded_field_names' => $this->fieldNamesFromRows(array_filter($rows, static fn (array $row): bool => ($row['omit_reason'] ?? null) === 'no_export')),
            'no_value_excluded_field_names' => $this->fieldNamesFromRows(array_filter($rows, static fn (array $row): bool => ($row['omit_reason'] ?? null) === 'no_value')),
            'push_button_excluded_field_names' => $this->fieldNamesFromRows(array_filter($rows, static fn (array $row): bool => ($row['omit_reason'] ?? null) === 'push_button')),
            'choice_field_names' => $this->fieldNamesFromRows(array_filter($rows, static fn (array $row): bool => ($row['field_type'] ?? null) === 'Ch')),
            'rich_text_field_names' => $this->fieldNamesFromRows(array_filter($rows, static fn (array $row): bool => is_array($row['rich_text_review'] ?? null))),
            'field_rows' => $rows,
            'uses_plain_value_for_rich_text' => true,
            'exports_rich_text_html' => false,
            'submits_pdf_on_import' => false,
            'embeds_form_on_import' => false,
            'includes_annotations_on_import' => false,
            'uses_get_method_on_import' => false,
            'executes_action' => false,
            'executes_javascript' => false,
            'imports_form_data' => false,
            'payload_text_exposed' => false,
        ];
    }

    /**
     * @param list<array<string, mixed>> $fieldRows
     * @return array<string, mixed>
     */
    private function resetFormFieldValueReview(array $action, array $fieldRows): array
    {
        $candidateRows = $this->actionCandidateFieldRows($action, $fieldRows, 'all');
        $rows = [];
        foreach ($candidateRows as $row) {
            $reset = $this->resetValueForFieldRow($row);
            $rows[] = $this->fieldReviewBaseRow($row) + [
                'selected_by_action' => true,
                'reset_applies' => true,
                'reset_value' => $reset['value'],
                'reset_value_source' => $reset['source'],
                'reset_display_value' => $this->displayValue($reset['value']),
                'choice_review' => $this->choiceReviewForFieldRow($row, $reset['value']),
                'rich_text_review' => $row['rich_text_review'] ?? null,
                'rich_text_restored' => false,
            ];
        }

        return [
            'source' => 'acroform_choice_richtext_submit_reset_review_boundary',
            'action_type' => 'ResetForm',
            'fields_mode' => $action['fields_mode'] ?? 'all',
            'resets_resources_on_import' => false,
            'renders_default_resources_on_import' => false,
            'reset_field_count' => count($rows),
            'reset_field_names' => $this->fieldNamesFromRows($rows),
            'default_value_field_names' => $this->fieldNamesFromRows(array_filter($rows, static fn (array $row): bool => ($row['reset_value_source'] ?? null) === 'default_value')),
            'cleared_field_names' => $this->fieldNamesFromRows(array_filter($rows, static fn (array $row): bool => in_array($row['reset_value_source'] ?? null, ['cleared_to_null', 'cleared_to_off'], true))),
            'choice_field_names' => $this->fieldNamesFromRows(array_filter($rows, static fn (array $row): bool => ($row['field_type'] ?? null) === 'Ch')),
            'rich_text_field_names' => $this->fieldNamesFromRows(array_filter($rows, static fn (array $row): bool => is_array($row['rich_text_review'] ?? null))),
            'field_rows' => $rows,
            'restores_rich_text_html' => false,
            'executes_action' => false,
            'executes_javascript' => false,
            'payload_text_exposed' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function submitResetActionResourceReview(array $action, array $fieldValueReview): array
    {
        $rows = $this->arrayRows($fieldValueReview['field_rows'] ?? []);
        $resourceRows = array_values(array_filter(
            $rows,
            static fn (array $row): bool => is_array($row['appearance_resource_review'] ?? null)
        ));
        $resourceValues = $this->submitResetActionResourceValues($resourceRows);
        $actionType = is_string($action['action_type'] ?? null)
            ? $action['action_type']
            : (string) ($fieldValueReview['action_type'] ?? 'unknown');
        $submittedFieldNames = $this->stringListValue($fieldValueReview['submitted_field_names'] ?? []);
        $resetFieldNames = $this->stringListValue($fieldValueReview['reset_field_names'] ?? []);
        $fileSpec = is_array($action['file_spec'] ?? null) ? $action['file_spec'] : null;

        return [
            'source' => 'acroform_field_action_submit_reset_resource_currentbase_review_boundary',
            'action_type' => $actionType,
            'trigger' => $action['trigger'] ?? null,
            'trigger_label' => $action['trigger_label'] ?? null,
            'action_source' => $action['source'] ?? null,
            'source_object' => $action['source_object'] ?? null,
            'action_object' => $action['action_object'] ?? null,
            'fields_mode' => $action['fields_mode'] ?? ($fieldValueReview['fields_mode'] ?? null),
            'selected_field_count' => count($rows),
            'selected_field_names' => $this->fieldNamesFromRows($rows),
            'field_resource_count' => count($resourceRows),
            'field_resource_names' => $this->fieldNamesFromRows($resourceRows),
            'included_field_names' => $actionType === 'ResetForm' ? $resetFieldNames : $submittedFieldNames,
            'submitted_field_names' => $submittedFieldNames,
            'reset_field_names' => $resetFieldNames,
            'no_export_excluded_field_names' => $this->stringListValue($fieldValueReview['no_export_excluded_field_names'] ?? []),
            'no_value_excluded_field_names' => $this->stringListValue($fieldValueReview['no_value_excluded_field_names'] ?? []),
            'push_button_excluded_field_names' => $this->stringListValue($fieldValueReview['push_button_excluded_field_names'] ?? []),
            'default_value_field_names' => $this->stringListValue($fieldValueReview['default_value_field_names'] ?? []),
            'cleared_field_names' => $this->stringListValue($fieldValueReview['cleared_field_names'] ?? []),
            'choice_field_names' => $this->stringListValue($fieldValueReview['choice_field_names'] ?? []),
            'rich_text_field_names' => $this->stringListValue($fieldValueReview['rich_text_field_names'] ?? []),
            'field_font_resources' => $resourceValues['field_font_resources'],
            'field_font_resource_base_fonts' => $resourceValues['field_font_resource_base_fonts'],
            'field_font_descriptor_objects' => $resourceValues['field_font_descriptor_objects'],
            'widget_font_resources' => $resourceValues['widget_font_resources'],
            'widget_font_resource_base_fonts' => $resourceValues['widget_font_resource_base_fonts'],
            'target' => $action['target'] ?? null,
            'target_scheme' => $action['target_scheme'] ?? null,
            'has_target_file_spec' => $fileSpec !== null,
            'target_file_spec_object' => $fileSpec['file_spec_object'] ?? null,
            'target_file_spec_file_system' => $fileSpec['file_system'] ?? null,
            'target_file_spec_relationship' => $fileSpec['relationship'] ?? null,
            'target_embedded_file_count' => $fileSpec === null ? 0 : (int) ($fileSpec['embedded_file_count'] ?? 0),
            'target_embedded_file_objects' => $this->integerListValue($fileSpec['embedded_file_objects'] ?? []),
            'target_related_file_count' => $fileSpec === null ? 0 : (int) ($fileSpec['related_file_count'] ?? 0),
            'review_only' => true,
            'uses_default_resources_for_submit' => false,
            'uses_default_resources_for_reset' => false,
            'uses_default_resources_for_import' => false,
            'field_value_payload_exposed' => false,
            'file_spec_payload_text_exposed' => false,
            'submits_pdf_on_import' => false,
            'resets_form_values_on_import' => false,
            'embeds_form_on_import' => false,
            'imports_form_data' => false,
            'renders_appearances' => false,
            'executes_appearance_streams' => false,
            'executes_action' => false,
            'executes_javascript' => false,
        ];
    }

    /**
     * @param list<array<string, mixed>> $resourceRows
     * @return array{field_font_resources: list<string>, field_font_resource_base_fonts: list<string>, field_font_descriptor_objects: list<int>, widget_font_resources: list<string>, widget_font_resource_base_fonts: list<string>}
     */
    private function submitResetActionResourceValues(array $resourceRows): array
    {
        $fieldFontResources = [];
        $fieldFontBaseFonts = [];
        $fieldFontDescriptorObjects = [];
        $widgetFontResources = [];
        $widgetFontBaseFonts = [];

        foreach ($resourceRows as $row) {
            $review = is_array($row['appearance_resource_review'] ?? null) ? $row['appearance_resource_review'] : null;
            if ($review === null) {
                continue;
            }

            if (is_string($review['font_resource'] ?? null)) {
                $fieldFontResources[] = $review['font_resource'];
            }
            if (is_string($review['font_resource_base_font'] ?? null)) {
                $fieldFontBaseFonts[] = $review['font_resource_base_font'];
            }
            if (is_int($review['font_descriptor_object'] ?? null)) {
                $fieldFontDescriptorObjects[] = $review['font_descriptor_object'];
            }

            foreach ($this->arrayRows($review['widget_appearances'] ?? []) as $widgetAppearance) {
                if (is_string($widgetAppearance['font_resource'] ?? null)) {
                    $widgetFontResources[] = $widgetAppearance['font_resource'];
                }
                if (is_string($widgetAppearance['font_resource_base_font'] ?? null)) {
                    $widgetFontBaseFonts[] = $widgetAppearance['font_resource_base_font'];
                }
            }
        }

        return [
            'field_font_resources' => $this->uniqueStrings($fieldFontResources),
            'field_font_resource_base_fonts' => $this->uniqueStrings($fieldFontBaseFonts),
            'field_font_descriptor_objects' => array_values(array_unique($fieldFontDescriptorObjects)),
            'widget_font_resources' => $this->uniqueStrings($widgetFontResources),
            'widget_font_resource_base_fonts' => $this->uniqueStrings($widgetFontBaseFonts),
        ];
    }

    /**
     * @return list<string>
     */
    private function stringListValue(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        return $this->uniqueStrings(array_values(array_filter($values, static fn (mixed $value): bool => is_string($value))));
    }

    /**
     * @return list<int>
     */
    private function integerListValue(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $integers = [];
        foreach ($values as $value) {
            if (is_int($value) && !in_array($value, $integers, true)) {
                $integers[] = $value;
            }
        }

        return $integers;
    }

    /**
     * @param list<array<string, mixed>> $fieldRows
     * @return list<array<string, mixed>>
     */
    private function actionCandidateFieldRows(array $action, array $fieldRows, string $defaultMode): array
    {
        $mode = (string) ($action['fields_mode'] ?? $defaultMode);
        $fieldObjects = array_values(array_filter(
            $action['field_objects'] ?? [],
            static fn (mixed $object): bool => is_int($object)
        ));
        $fieldNames = array_values(array_filter(
            $action['field_names'] ?? [],
            static fn (mixed $name): bool => is_string($name) && $name !== ''
        ));

        $rows = [];
        foreach ($fieldRows as $row) {
            $listed = (is_int($row['object'] ?? null) && in_array($row['object'], $fieldObjects, true))
                || (is_string($row['name'] ?? null) && in_array($row['name'], $fieldNames, true));
            $selected = match ($mode) {
                'include' => $listed,
                'exclude' => !$listed,
                default => true,
            };
            if ($selected) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return array{has_value: bool, value: mixed, source: string|null}
     */
    private function submitValueForFieldRow(array $row): array
    {
        if (($row['value_redacted'] ?? false) === true) {
            return ['has_value' => false, 'value' => null, 'source' => 'redacted'];
        }

        if (($row['field_type'] ?? null) === 'Btn') {
            $exportValue = $row['effective_export_value'] ?? null;
            if ($exportValue !== null && $exportValue !== []) {
                return [
                    'has_value' => true,
                    'value' => $exportValue,
                    'source' => (string) ($row['export_value_source'] ?? 'button_export_value'),
                ];
            }

            $value = $row['effective_current'] ?? null;
            return [
                'has_value' => $value !== null,
                'value' => $value,
                'source' => $value === null ? null : (string) ($row['current_source'] ?? 'button_state'),
            ];
        }

        $hasValue = ($row['has_current_value'] ?? false) === true && ($row['current'] ?? null) !== null;
        return [
            'has_value' => $hasValue,
            'value' => $hasValue ? $row['current'] : null,
            'source' => $hasValue ? (string) ($row['current_source'] ?? 'current_value') : null,
        ];
    }

    /**
     * @return array{value: mixed, source: string}
     */
    private function resetValueForFieldRow(array $row): array
    {
        if (($row['has_default_value'] ?? false) === true) {
            return ['value' => $row['default'] ?? null, 'source' => 'default_value'];
        }

        if (($row['field_type'] ?? null) === 'Btn') {
            return ['value' => 'Off', 'source' => 'cleared_to_off'];
        }

        return ['value' => null, 'source' => 'cleared_to_null'];
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldReviewBaseRow(array $row): array
    {
        $base = [
            'field_object' => $row['object'] ?? null,
            'field_name' => $row['name'] ?? null,
            'field_type' => $row['field_type'] ?? null,
            'field_type_label' => $row['field_type_label'] ?? null,
            'flags' => $row['flags'] ?? 0,
            'flag_names' => $row['flag_names'] ?? [],
            'no_export' => (bool) ($row['no_export'] ?? false),
            'value_redacted' => (bool) ($row['value_redacted'] ?? false),
            'current' => $row['current'] ?? null,
            'default' => $row['default'] ?? null,
            'display_value' => $row['display_value'] ?? null,
        ];

        if (($row['field_type'] ?? null) === 'Btn') {
            $base['effective_export_value'] = $row['effective_export_value'] ?? null;
            $base['export_value_source'] = $row['export_value_source'] ?? null;
            if (is_array($row['button_export_review'] ?? null)) {
                $base['button_export_review'] = $row['button_export_review'];
            }
        }

        $appearanceResourceReview = $this->appearanceResourceReviewForFieldRow($row);
        if ($appearanceResourceReview !== null) {
            $base['appearance_resource_review'] = $appearanceResourceReview;
        }

        return $base;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function appearanceResourceReviewForFieldRow(array $row): ?array
    {
        $appearance = is_array($row['default_appearance'] ?? null) ? $row['default_appearance'] : null;
        $widgets = $this->arrayRows($row['widgets'] ?? []);
        $widgetAppearances = [];
        foreach ($widgets as $widget) {
            $widgetAppearance = is_array($widget['default_appearance'] ?? null) ? $widget['default_appearance'] : null;
            if ($widgetAppearance === null) {
                continue;
            }

            $widgetAppearances[] = [
                'widget_object' => $widget['object'] ?? null,
                'source' => $widgetAppearance['source'] ?? null,
                'source_object' => $widgetAppearance['source_object'] ?? null,
                'font_resource' => $widgetAppearance['font_resource'] ?? null,
                'font_resource_resolved' => (bool) ($widgetAppearance['font_resource_resolved'] ?? false),
                'font_resource_object' => $widgetAppearance['font_resource_object'] ?? null,
                'font_resource_base_font' => $widgetAppearance['font_resource_base_font'] ?? null,
                'font_resource_encoding' => $widgetAppearance['font_resource_encoding'] ?? null,
                'default_resource_source' => $widgetAppearance['default_resource_source'] ?? null,
                'default_resource_source_object' => $widgetAppearance['default_resource_source_object'] ?? null,
                'renders_appearance' => false,
                'executes_appearance_streams' => false,
            ];
        }

        if ($appearance === null && $widgetAppearances === []) {
            return null;
        }

        return [
            'source' => 'acroform_submit_reset_resource_review_boundary',
            'field_appearance_source' => $appearance['source'] ?? null,
            'field_appearance_source_object' => $appearance['source_object'] ?? null,
            'font_resource' => $appearance['font_resource'] ?? null,
            'font_resource_resolved' => (bool) ($appearance['font_resource_resolved'] ?? false),
            'font_resource_object' => $appearance['font_resource_object'] ?? null,
            'font_resource_base_font' => $appearance['font_resource_base_font'] ?? null,
            'font_resource_subtype' => $appearance['font_resource_subtype'] ?? null,
            'font_resource_encoding' => $appearance['font_resource_encoding'] ?? null,
            'font_descriptor_object' => $appearance['font_descriptor_object'] ?? null,
            'font_descriptor_name' => $appearance['font_descriptor_name'] ?? null,
            'font_descriptor_flags' => $appearance['font_descriptor_flags'] ?? null,
            'font_weight' => $appearance['font_weight'] ?? null,
            'default_resource_source' => $appearance['default_resource_source'] ?? null,
            'default_resource_source_object' => $appearance['default_resource_source_object'] ?? null,
            'widget_appearance_count' => count($widgetAppearances),
            'widget_appearances' => $widgetAppearances,
            'uses_default_resources_for_import' => false,
            'uses_default_resources_for_submit' => false,
            'uses_default_resources_for_reset' => false,
            'renders_appearances' => false,
            'executes_appearance_streams' => false,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function choiceReviewForFieldRow(array $row, mixed $value): ?array
    {
        if (($row['field_type'] ?? null) !== 'Ch') {
            return null;
        }

        $options = is_array($row['options'] ?? null) ? $row['options'] : [];
        $selectedIndices = $this->selectedChoiceIndices($value, $options, []);

        return [
            'choice_values' => $this->valueList($value),
            'selected_indices' => $selectedIndices,
            'selected_options' => $this->selectedChoiceOptions($value, $options, $selectedIndices),
            'unmatched_values' => $this->unmatchedChoiceValues($value, $options),
        ];
    }

    /**
     * @param iterable<array<string, mixed>> $rows
     * @return list<string>
     */
    private function fieldNamesFromRows(iterable $rows): array
    {
        $names = [];
        foreach ($rows as $row) {
            $name = $row['field_name'] ?? ($row['name'] ?? null);
            if (is_string($name) && $name !== '' && !in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, string> $fieldNamesByObject
     * @return array{actions: list<array<string, mixed>>, review: array<string, mixed>}
     */
    private function actionsWithReviewFromDictionary(
        string $body,
        array $objects,
        array $fieldNamesByObject,
        string $source,
        int $sourceObject
    ): array {
        $actions = [];
        $chainSafety = $this->emptyActionChainSafety();
        $activation = $this->valueAfterName($body, 'A');
        if ($activation !== null) {
            foreach ($this->actionMetadataFromValue($activation, $objects, $fieldNamesByObject, 'activation', $source, $sourceObject, $chainSafety) as $action) {
                $actions[] = $action;
            }
        }

        $additionalActions = $this->valueAfterName($body, 'AA');
        $additionalActionsDictionary = $additionalActions === null ? null : $this->resolvedDictionaryFromValue($additionalActions, $objects);
        if ($additionalActionsDictionary === null) {
            return [
                'actions' => $actions,
                'review' => $this->actionReviewSummary($source, $sourceObject, $actions, $chainSafety),
            ];
        }

        foreach (['E', 'X', 'D', 'U', 'Fo', 'Bl', 'K', 'F', 'V', 'C'] as $trigger) {
            $value = $this->valueAfterName($additionalActionsDictionary['body'], $trigger);
            if ($value === null) {
                continue;
            }

            foreach ($this->actionMetadataFromValue($value, $objects, $fieldNamesByObject, $trigger, $source, $sourceObject, $chainSafety) as $action) {
                $actions[] = $action;
            }
        }

        return [
            'actions' => $actions,
            'review' => $this->actionReviewSummary($source, $sourceObject, $actions, $chainSafety),
        ];
    }

    /**
     * @return array{cycle_edges_blocked: int, max_depth_edges_blocked: int, blocked_cycle_action_objects: list<int>, blocked_max_depth_action_objects: list<int>}
     */
    private function emptyActionChainSafety(): array
    {
        return [
            'cycle_edges_blocked' => 0,
            'max_depth_edges_blocked' => 0,
            'blocked_cycle_action_objects' => [],
            'blocked_max_depth_action_objects' => [],
        ];
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @param array{cycle_edges_blocked: int, max_depth_edges_blocked: int, blocked_cycle_action_objects: list<int>, blocked_max_depth_action_objects: list<int>} $chainSafety
     * @return array<string, mixed>
     */
    private function actionReviewSummary(string $source, int $sourceObject, array $actions, array $chainSafety): array
    {
        return [
            'source' => 'acroform_action_chain_review_boundary',
            'action_source' => $source,
            'source_object' => $sourceObject,
            'max_depth' => self::MAX_ACTION_CHAIN_DEPTH,
            'action_count' => count($actions),
            'chained_action_count' => count(array_filter(
                $actions,
                static fn (array $action): bool => ($action['chained'] ?? false) === true
            )),
            'cycle_edges_blocked' => $chainSafety['cycle_edges_blocked'],
            'max_depth_edges_blocked' => $chainSafety['max_depth_edges_blocked'],
            'blocked_cycle_action_objects' => $chainSafety['blocked_cycle_action_objects'],
            'blocked_max_depth_action_objects' => $chainSafety['blocked_max_depth_action_objects'],
            'has_blocked_cycle' => $chainSafety['cycle_edges_blocked'] > 0,
            'has_blocked_depth_edge' => $chainSafety['max_depth_edges_blocked'] > 0,
            'review_only' => true,
            'appearance_value_used_for_import' => false,
            'payload_text_exposed' => false,
            'executes_action' => false,
            'executes_javascript' => false,
            'executes_appearance_streams' => false,
            'renders_appearances' => false,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, string> $fieldNamesByObject
     * @param array<int, true> $seenActionObjects
     * @return list<array<string, mixed>>
     */
    private function actionMetadataFromValue(
        string $value,
        array $objects,
        array $fieldNamesByObject,
        string $trigger,
        string $source,
        int $sourceObject,
        array &$chainSafety,
        array $seenActionObjects = [],
        int $depth = 0,
        bool $chained = false
    ): array {
        $action = $this->resolvedDictionaryFromValue($value, $objects);
        if ($action === null) {
            return [];
        }

        return $this->actionMetadataFromResolvedDictionary(
            $action,
            $objects,
            $fieldNamesByObject,
            $trigger,
            $source,
            $sourceObject,
            $chainSafety,
            $seenActionObjects,
            $depth,
            $chained
        );
    }

    /**
     * @param array{body: string, object: int|null} $action
     * @param array<int, string> $objects
     * @param array<int, string> $fieldNamesByObject
     * @param array<int, true> $seenActionObjects
     * @return list<array<string, mixed>>
     */
    private function actionMetadataFromResolvedDictionary(
        array $action,
        array $objects,
        array $fieldNamesByObject,
        string $trigger,
        string $source,
        int $sourceObject,
        array &$chainSafety,
        array $seenActionObjects,
        int $depth,
        bool $chained
    ): array {
        if ($depth > self::MAX_ACTION_CHAIN_DEPTH) {
            $this->recordMaxDepthBlockedAction($chainSafety, $action['object']);
            return [];
        }

        $actionObject = $action['object'];
        if ($actionObject !== null) {
            if (isset($seenActionObjects[$actionObject])) {
                $this->recordCycleBlockedAction($chainSafety, $actionObject);
                return [];
            }
            $seenActionObjects[$actionObject] = true;
        }

        $actions = [];
        $metadata = $this->actionMetadataFromBody(
            $action['body'],
            $objects,
            $fieldNamesByObject,
            $trigger,
            $source,
            $sourceObject,
            $actionObject
        );
        if ($metadata !== null) {
            if ($chained) {
                $metadata['chained'] = true;
            }
            $actions[] = $metadata;
        }

        $next = $this->valueAfterName($action['body'], 'Next');
        if ($next === null) {
            return $actions;
        }

        if ($depth >= self::MAX_ACTION_CHAIN_DEPTH) {
            $this->recordMaxDepthBlockedActionsFromValue($chainSafety, $next);
            return $actions;
        }

        $nextValue = trim($next);
        if (str_starts_with($nextValue, '[')) {
            $arrayBody = $this->arrayBodyFromValue($nextValue);
            if ($arrayBody === null) {
                return $actions;
            }

            foreach ($this->dictionaryValuesFromArrayBody($arrayBody, $objects) as $nextDictionary) {
                foreach ($this->actionMetadataFromResolvedDictionary(
                    $nextDictionary,
                    $objects,
                    $fieldNamesByObject,
                    $trigger,
                    $source,
                    $sourceObject,
                    $chainSafety,
                    $seenActionObjects,
                    $depth + 1,
                    true
                ) as $nextAction) {
                    $actions[] = $nextAction;
                }
            }

            return $actions;
        }

        foreach ($this->actionMetadataFromValue($nextValue, $objects, $fieldNamesByObject, $trigger, $source, $sourceObject, $chainSafety, $seenActionObjects, $depth + 1, true) as $nextAction) {
            $actions[] = $nextAction;
        }

        return $actions;
    }

    private function recordCycleBlockedAction(array &$chainSafety, ?int $actionObject): void
    {
        $chainSafety['cycle_edges_blocked']++;
        if ($actionObject !== null && !in_array($actionObject, $chainSafety['blocked_cycle_action_objects'], true)) {
            $chainSafety['blocked_cycle_action_objects'][] = $actionObject;
        }
    }

    private function recordMaxDepthBlockedAction(array &$chainSafety, ?int $actionObject): void
    {
        $chainSafety['max_depth_edges_blocked']++;
        if ($actionObject !== null && !in_array($actionObject, $chainSafety['blocked_max_depth_action_objects'], true)) {
            $chainSafety['blocked_max_depth_action_objects'][] = $actionObject;
        }
    }

    private function recordMaxDepthBlockedActionsFromValue(array &$chainSafety, string $value): void
    {
        $objects = $this->actionObjectReferencesFromValue($value);
        if ($objects === []) {
            $this->recordMaxDepthBlockedAction($chainSafety, null);
            return;
        }

        foreach ($objects as $object) {
            $this->recordMaxDepthBlockedAction($chainSafety, $object);
        }
    }

    /**
     * @return list<int>
     */
    private function actionObjectReferencesFromValue(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        if (str_starts_with($value, '[')) {
            $body = $this->arrayBodyFromValue($value);
            return $body === null ? [] : array_values(array_unique($this->objectReferences($body)));
        }

        $reference = $this->objectReferenceFromValue($value);

        return $reference === null ? [] : [$reference['object']];
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, string> $fieldNamesByObject
     * @return array<string, mixed>|null
     */
    private function actionMetadataFromBody(
        string $actionBody,
        array $objects,
        array $fieldNamesByObject,
        string $trigger,
        string $source,
        int $sourceObject,
        ?int $actionObject
    ): ?array {
        $actionType = $this->pdfNameValueAfterName($actionBody, 'S');
        if ($actionType === 'JavaScript') {
            return $this->javaScriptActionMetadataFromBody(
                $actionBody,
                $objects,
                $trigger,
                $source,
                $sourceObject,
                $actionObject
            );
        }

        if (!in_array($actionType, ['SubmitForm', 'ResetForm'], true)) {
            return $this->genericActionMetadataFromBody(
                $actionBody,
                $objects,
                $fieldNamesByObject,
                $trigger,
                $source,
                $sourceObject,
                $actionObject,
                $actionType
            );
        }

        $flags = $this->numberValueAfterName($actionBody, 'Flags') ?? 0;
        $selection = $this->fieldSelectionFromAction($actionBody, $objects, $fieldNamesByObject, $actionType, $flags);
        $metadata = [
            'action_type' => $actionType,
            'trigger' => $trigger,
            'trigger_label' => $this->actionTriggerLabel($trigger),
            'source' => $source,
            'source_object' => $sourceObject,
            'action_object' => $actionObject,
            'flags' => $flags,
            'flag_names' => $this->actionFlagNames($actionType, $flags),
            'fields_mode' => $selection['fields_mode'],
            'field_objects' => $selection['field_objects'],
            'field_names' => $selection['field_names'],
            'unresolved_field_objects' => $selection['unresolved_field_objects'],
            'executes_action' => false,
        ];

        if ($actionType === 'SubmitForm') {
            $targetValue = $this->valueAfterName($actionBody, 'F');
            $target = $targetValue === null ? null : $this->fileSpecificationFromValue($targetValue, $objects);
            $fileSpec = $targetValue === null ? null : $this->fileSpecificationReviewFromValue($targetValue, $objects);
            $metadata += [
                'target' => $target,
                'target_scheme' => $target === null ? null : $this->uriScheme($target),
                'submit_format' => $this->submitFormatFromFlags($flags),
                'requested_submit_format' => $this->submitFormatFromFlags($flags),
                'include_no_value_fields' => $this->hasFlagBit($flags, 2),
                'html_format_requested' => $this->hasFlagBit($flags, 3),
                'get_method_requested' => $this->hasFlagBit($flags, 4),
                'submit_coordinates_requested' => $this->hasFlagBit($flags, 5),
                'xfdf_requested' => $this->hasFlagBit($flags, 6),
                'include_append_saves_requested' => $this->hasFlagBit($flags, 7),
                'include_annotations_requested' => $this->hasFlagBit($flags, 8),
                'submit_pdf_requested' => $this->hasFlagBit($flags, 9),
                'canonical_format_requested' => $this->hasFlagBit($flags, 10),
                'exclude_non_user_annotations_requested' => $this->hasFlagBit($flags, 11),
                'exclude_f_key_requested' => $this->hasFlagBit($flags, 12),
                'embed_form_requested' => $this->hasFlagBit($flags, 14),
                'submits_pdf_on_import' => false,
                'embeds_form_on_import' => false,
                'includes_annotations_on_import' => false,
                'uses_get_method_on_import' => false,
                'default_excludes_no_export' => true,
            ];
            if ($fileSpec !== null) {
                $metadata['file_spec'] = $fileSpec;
            }
        } else {
            $metadata['reset_to_default'] = true;
        }

        return $metadata;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, string> $fieldNamesByObject
     * @return array<string, mixed>|null
     */
    private function genericActionMetadataFromBody(
        string $actionBody,
        array $objects,
        array $fieldNamesByObject,
        string $trigger,
        string $source,
        int $sourceObject,
        ?int $actionObject,
        ?string $actionType
    ): ?array {
        if ($actionType === null || $actionType === '') {
            return null;
        }

        $metadata = [
            'action_type' => $actionType,
            'trigger' => $trigger,
            'trigger_label' => $this->actionTriggerLabel($trigger),
            'source' => $source,
            'source_object' => $sourceObject,
            'action_object' => $actionObject,
            'safety' => $this->genericActionSafety($actionType, $actionBody, $objects),
            'review_only' => true,
            'executes_action' => false,
            'executes_javascript' => false,
        ];

        if ($actionType === 'URI') {
            $uriValue = $this->dictionaryEntryValueAfterName($actionBody, 'URI');
            $uri = $uriValue === null ? null : $this->pdfValueToString($uriValue, $objects);
            $metadata['uri'] = $uri;
            $metadata['target'] = $uri;
            $metadata['target_scheme'] = is_string($uri) ? $this->uriScheme($uri) : null;
            $metadata['safe_uri'] = is_string($uri) && $this->isSafeUri($uri);

            return $metadata;
        }

        if ($actionType === 'Launch') {
            $targetValue = $this->dictionaryEntryValueAfterName($actionBody, 'F');
            $target = $targetValue === null ? null : $this->fileSpecificationFromValue($targetValue, $objects);
            $fileSpec = $targetValue === null ? null : $this->fileSpecificationReviewFromValue($targetValue, $objects);
            $platform = $this->launchPlatformMetadataFromAction($actionBody, $objects);
            if ($target === null && is_string($platform['target'] ?? null)) {
                $target = $platform['target'];
            }
            $metadata['target'] = $target;
            $metadata['target_scheme'] = is_string($target) ? $this->uriScheme($target) : null;
            $metadata['new_window'] = $this->boolValueAfterName($actionBody, 'NewWindow');
            if ($fileSpec !== null) {
                $metadata['file_spec'] = $fileSpec;
            } elseif (is_array($platform['file_spec'] ?? null)) {
                $metadata['file_spec'] = $platform['file_spec'];
            }
            if (is_array($platform['file_spec'] ?? null)) {
                $metadata['platform_file_spec'] = $platform['file_spec'];
            }
            foreach (['target_platform', 'operation', 'parameters', 'default_directory', 'platform_dictionary_object'] as $key) {
                if (($platform[$key] ?? null) !== null) {
                    $metadata[$key] = $platform[$key];
                }
            }

            return $metadata;
        }

        if ($actionType === 'ImportData') {
            $targetValue = $this->dictionaryEntryValueAfterName($actionBody, 'F');
            $target = $targetValue === null ? null : $this->fileSpecificationFromValue($targetValue, $objects);
            $fileSpec = $targetValue === null ? null : $this->fileSpecificationReviewFromValue($targetValue, $objects);
            $metadata['target'] = $target;
            $metadata['target_scheme'] = is_string($target) ? $this->uriScheme($target) : null;
            $metadata['imports_form_data'] = false;
            if ($fileSpec !== null) {
                $metadata['file_spec'] = $fileSpec;
            }

            return $metadata;
        }

        if ($actionType === 'Hide') {
            $targetValue = $this->dictionaryEntryValueAfterName($actionBody, 'T');
            $selection = $targetValue === null
                ? ['field_objects' => [], 'field_names' => [], 'unresolved_field_objects' => []]
                : $this->fieldTargetsFromValue($targetValue, $objects, $fieldNamesByObject);
            $hide = $this->boolValueAfterName($actionBody, 'H');
            $metadata += [
                'hide' => $hide ?? true,
                'operation' => ($hide ?? true) ? 'hide' : 'show',
                'field_objects' => $selection['field_objects'],
                'field_names' => $selection['field_names'],
                'unresolved_field_objects' => $selection['unresolved_field_objects'],
            ];

            return $metadata;
        }

        if ($actionType === 'Named') {
            $nameValue = $this->dictionaryEntryValueAfterName($actionBody, 'N');
            $metadata['named_action'] = $nameValue === null ? null : $this->pdfValueToString($nameValue, $objects);

            return $metadata;
        }

        if ($actionType === 'GoToE') {
            $targetValue = $this->dictionaryEntryValueAfterName($actionBody, 'F');
            $target = $targetValue === null ? null : $this->fileSpecificationFromValue($targetValue, $objects);
            $fileSpec = $targetValue === null ? null : $this->fileSpecificationReviewFromValue($targetValue, $objects);
            $metadata['target'] = $target;
            $metadata['file'] = $target;
            $metadata['target_scheme'] = is_string($target) ? $this->uriScheme($target) : null;
            if ($fileSpec !== null) {
                $metadata['file_spec'] = $fileSpec;
            }
            $metadata['destination'] = $this->actionDestinationValue($this->dictionaryEntryValueAfterName($actionBody, 'D'), $objects);
            $metadata['embedded_target'] = $this->embeddedTargetFromValue($this->dictionaryEntryValueAfterName($actionBody, 'T'), $objects);
            $metadata['new_window'] = $this->boolValueAfterName($actionBody, 'NewWindow');

            return $metadata;
        }

        if ($actionType === 'GoTo' || $actionType === 'GoToR') {
            $destination = $this->actionDestinationValue($this->dictionaryEntryValueAfterName($actionBody, 'D'), $objects);
            $metadata['destination'] = $destination;
            if ($actionType === 'GoToR') {
                $targetValue = $this->dictionaryEntryValueAfterName($actionBody, 'F');
                $target = $targetValue === null ? null : $this->fileSpecificationFromValue($targetValue, $objects);
                $fileSpec = $targetValue === null ? null : $this->fileSpecificationReviewFromValue($targetValue, $objects);
                $metadata['target'] = $target;
                $metadata['target_scheme'] = is_string($target) ? $this->uriScheme($target) : null;
                $metadata['new_window'] = $this->boolValueAfterName($actionBody, 'NewWindow');
                if ($fileSpec !== null) {
                    $metadata['file_spec'] = $fileSpec;
                }
            }

            return $metadata;
        }

        return $metadata;
    }

    /**
     * @param array<int, string> $objects
     */
    private function genericActionSafety(string $actionType, string $actionBody, array $objects): string
    {
        if ($actionType === 'URI') {
            $uriValue = $this->dictionaryEntryValueAfterName($actionBody, 'URI');
            $uri = $uriValue === null ? null : $this->pdfValueToString($uriValue, $objects);
            return is_string($uri) && $this->isSafeUri($uri) ? 'review-uri' : 'blocked-unsafe-uri';
        }

        return match ($actionType) {
            'Launch' => 'launch-action-review',
            'ImportData' => 'import-data-action-review',
            'Hide' => 'hide-action-review',
            'Named' => 'named-action-review',
            'GoTo' => 'local-destination-review',
            'GoToR' => 'remote-document-review',
            'GoToE' => 'embedded-document-review',
            default => 'unsupported-action-review',
        };
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function launchPlatformMetadataFromAction(string $actionBody, array $objects): array
    {
        $empty = [
            'target' => null,
            'target_platform' => null,
            'operation' => null,
            'parameters' => null,
            'default_directory' => null,
            'platform_dictionary_object' => null,
        ];

        foreach (['Win', 'Unix', 'Mac', 'DOS'] as $platformName) {
            $platformValue = $this->dictionaryEntryValueAfterName($actionBody, $platformName);
            if ($platformValue === null) {
                continue;
            }

            $platformDictionary = $this->resolvedDictionaryFromValue($platformValue, $objects);
            if ($platformDictionary === null) {
                $target = $this->pdfValueToString($platformValue, $objects);
                $metadata = $empty;
                $metadata['target'] = $target;
                $metadata['target_platform'] = $platformName;

                return $metadata;
            }

            $targetValue = $this->dictionaryEntryValueAfterName($platformDictionary['body'], 'F');
            $target = $targetValue === null ? null : $this->fileSpecificationFromValue($targetValue, $objects);
            $fileSpec = $targetValue === null ? null : $this->fileSpecificationReviewFromValue($targetValue, $objects);

            $metadata = [
                'target' => $target,
                'target_platform' => $platformName,
                'operation' => $this->pdfStringValueAfterName($platformDictionary['body'], 'O', $objects),
                'parameters' => $this->pdfStringValueAfterName($platformDictionary['body'], 'P', $objects),
                'default_directory' => $this->pdfStringValueAfterName($platformDictionary['body'], 'D', $objects),
                'platform_dictionary_object' => $platformDictionary['object'],
            ];
            if ($fileSpec !== null) {
                $metadata['file_spec'] = $fileSpec;
            }

            return $metadata;
        }

        return $empty;
    }

    /**
     * @param array<int, string> $objects
     * @return array{relationship: string|null, relationship_label: string|null, name: string|null, page: int|null, annotation: mixed, nested_target: mixed}|null
     */
    private function embeddedTargetFromValue(?string $value, array $objects, int $depth = 0): ?array
    {
        if ($value === null || $depth > 8) {
            return null;
        }

        $target = $this->resolvedDictionaryFromValue($value, $objects);
        if ($target === null) {
            return null;
        }

        $relationshipValue = $this->dictionaryEntryValueAfterName($target['body'], 'R');
        $relationship = $relationshipValue === null ? null : $this->pdfValueToString($relationshipValue, $objects);
        $nameValue = $this->dictionaryEntryValueAfterName($target['body'], 'N');
        $pageValue = $this->dictionaryEntryValueAfterName($target['body'], 'P');
        $annotationValue = $this->dictionaryEntryValueAfterName($target['body'], 'A');
        $nestedTargetValue = $this->dictionaryEntryValueAfterName($target['body'], 'T');

        return [
            'relationship' => $relationship,
            'relationship_label' => $this->embeddedTargetRelationshipLabel($relationship),
            'name' => $nameValue === null ? null : $this->pdfValueToString($nameValue, $objects),
            'page' => $this->integerFromPdfValue($pageValue),
            'annotation' => $annotationValue === null ? null : $this->pdfValueToPhpValue($annotationValue, $objects),
            'nested_target' => $this->embeddedTargetFromValue($nestedTargetValue, $objects, $depth + 1),
        ];
    }

    private function embeddedTargetRelationshipLabel(?string $relationship): ?string
    {
        return match ($relationship) {
            'C' => 'child',
            'P' => 'parent',
            'R' => 'root',
            default => $relationship === null ? null : 'unknown',
        };
    }

    private function integerFromPdfValue(?string $value): ?int
    {
        return $value !== null && preg_match('/^[+-]?\d+\b/', trim($value), $match) === 1
            ? (int) $match[0]
            : null;
    }

    private function isSafeUri(string $uri): bool
    {
        $scheme = $this->uriScheme($uri);
        return $scheme === null || in_array($scheme, ['http', 'https', 'mailto'], true);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, string> $fieldNamesByObject
     * @return array{field_objects: list<int>, field_names: list<string>, unresolved_field_objects: list<int>}
     */
    private function fieldTargetsFromValue(string $value, array $objects, array $fieldNamesByObject): array
    {
        $value = trim($value);
        $body = str_starts_with($value, '[') ? $this->arrayBodyFromValue($value) : $value;
        if ($body === null) {
            return [
                'field_objects' => [],
                'field_names' => [],
                'unresolved_field_objects' => [],
            ];
        }

        $fieldObjects = array_values(array_unique($this->objectReferences($body)));
        $fieldNames = [];
        $unresolved = [];
        foreach ($fieldObjects as $fieldObject) {
            if (isset($fieldNamesByObject[$fieldObject])) {
                $fieldNames[] = $fieldNamesByObject[$fieldObject];
                continue;
            }

            $unresolved[] = $fieldObject;
        }

        foreach ($this->scalarValuesFromArrayBody($body, $objects) as $fieldName) {
            if ($fieldName !== '' && !in_array($fieldName, $fieldNames, true)) {
                $fieldNames[] = $fieldName;
            }
        }

        return [
            'field_objects' => $fieldObjects,
            'field_names' => $fieldNames,
            'unresolved_field_objects' => $unresolved,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return mixed
     */
    private function actionDestinationValue(?string $value, array $objects): mixed
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '[')) {
            $body = $this->arrayBodyFromValue($value);
            return $body === null ? [] : $this->destinationArrayPreview($body, $objects);
        }

        return $this->pdfValueToPhpValue($value, $objects);
    }

    /**
     * @param array<int, string> $objects
     * @return list<mixed>
     */
    private function destinationArrayPreview(string $body, array $objects): array
    {
        $items = [];
        $offset = 0;
        $length = strlen($body);
        while ($offset < $length) {
            $endOffset = null;
            $value = $this->readPdfValueAt($body, $offset, $endOffset);
            if ($value === null || $endOffset === null) {
                $offset++;
                continue;
            }

            $value = trim($value);
            $reference = $this->objectReferenceFromValue($value);
            if ($reference !== null && $this->referenceGenerationMatches($reference['object'], $reference['generation'], $objects)) {
                $items[] = ['object' => $reference['object']];
            } else {
                $items[] = $this->pdfValueToPhpValue($value, $objects);
            }
            $offset = $endOffset;
        }

        return $items;
    }

    private function dictionaryEntryValueAfterName(string $dictionaryBody, string $name): ?string
    {
        $entries = $this->dictionaryNameValueMap($dictionaryBody);
        return $entries[$name] ?? null;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function javaScriptActionMetadataFromBody(
        string $actionBody,
        array $objects,
        string $trigger,
        string $source,
        int $sourceObject,
        ?int $actionObject
    ): array {
        $metadata = [
            'action_type' => 'JavaScript',
            'trigger' => $trigger,
            'trigger_label' => $this->actionTriggerLabel($trigger),
            'source' => $source,
            'source_object' => $sourceObject,
            'action_object' => $actionObject,
            'executes_action' => false,
            'executes_javascript' => false,
        ];

        $script = $this->javaScriptPayloadFromAction($actionBody, $objects, 160);
        if ($script === null) {
            $metadata['script_missing'] = true;
            return $metadata;
        }

        return $metadata + $script;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function javaScriptPayloadFromAction(string $actionBody, array $objects, int $previewBytes): ?array
    {
        $value = $this->valueAfterName($actionBody, 'JS');
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        $scriptReference = $this->objectReferenceFromValue($value);
        $scriptObject = null;
        if (
            $scriptReference !== null
            && $this->referenceGenerationMatches($scriptReference['object'], $scriptReference['generation'], $objects)
        ) {
            $scriptObject = $scriptReference['object'];
        }
        $filters = [];
        if ($scriptObject !== null && isset($objects[$scriptObject])) {
            $stream = $this->decodeStreamObject($objects[$scriptObject], $objects);
            if ($stream !== null) {
                $script = $this->decodePdfStringBytes($stream);
                $filters = $this->streamObjectFilters($objects[$scriptObject], $objects);
            } else {
                $script = $this->pdfValueToString(trim($objects[$scriptObject]), $objects);
            }
        } else {
            $script = $this->pdfValueToString($value, $objects);
        }

        if ($script === null) {
            return null;
        }

        $preview = $this->scriptPreview($script, $previewBytes);
        $payload = [
            'script_preview' => $preview['preview'],
            'script_sha256' => hash('sha256', $script),
            'script_bytes' => strlen($script),
            'script_truncated' => $preview['truncated'],
        ];

        if ($scriptObject !== null) {
            $payload['script_object'] = $scriptObject;
        }
        if ($filters !== []) {
            $payload['script_filters'] = $filters;
        }

        return $payload;
    }

    /**
     * @return array{preview: string, truncated: bool}
     */
    private function scriptPreview(string $script, int $previewBytes): array
    {
        $normalized = trim(preg_replace('/[\x00-\x1f\x7f]+/', ' ', $script) ?? $script);
        $limit = max(16, $previewBytes);
        if (strlen($normalized) <= $limit) {
            return ['preview' => $normalized, 'truncated' => false];
        }

        return ['preview' => substr($normalized, 0, $limit) . '...', 'truncated' => true];
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, string> $fieldNamesByObject
     * @return array{fields_mode: string, field_objects: list<int>, field_names: list<string>, unresolved_field_objects: list<int>}
     */
    private function fieldSelectionFromAction(
        string $actionBody,
        array $objects,
        array $fieldNamesByObject,
        string $actionType,
        int $flags
    ): array {
        $fields = $this->valueAfterName($actionBody, 'Fields');
        if ($fields === null || !str_starts_with(trim($fields), '[')) {
            return [
                'fields_mode' => $actionType === 'SubmitForm' ? 'all_exportable' : 'all',
                'field_objects' => [],
                'field_names' => [],
                'unresolved_field_objects' => [],
            ];
        }

        $arrayBody = $this->arrayBodyFromValue($fields);
        if ($arrayBody === null) {
            return [
                'fields_mode' => $this->hasFlagBit($flags, 1) ? 'exclude' : 'include',
                'field_objects' => [],
                'field_names' => [],
                'unresolved_field_objects' => [],
            ];
        }

        $fieldObjects = array_values(array_unique($this->objectReferences($arrayBody)));
        $fieldNames = [];
        $unresolved = [];
        foreach ($fieldObjects as $fieldObject) {
            if (isset($fieldNamesByObject[$fieldObject])) {
                $fieldNames[] = $fieldNamesByObject[$fieldObject];
                continue;
            }

            $unresolved[] = $fieldObject;
        }

        foreach ($this->scalarValuesFromArrayBody($arrayBody, $objects) as $fieldName) {
            if (!in_array($fieldName, $fieldNames, true)) {
                $fieldNames[] = $fieldName;
            }
        }

        return [
            'fields_mode' => $this->hasFlagBit($flags, 1) ? 'exclude' : 'include',
            'field_objects' => $fieldObjects,
            'field_names' => $fieldNames,
            'unresolved_field_objects' => $unresolved,
        ];
    }

    /**
     * @return list<string>
     */
    private function actionFlagNames(string $actionType, int $flags): array
    {
        $names = [];
        if ($this->hasFlagBit($flags, 1)) {
            $names[] = 'exclude_list';
        }

        if ($actionType === 'SubmitForm') {
            foreach (self::SUBMIT_FORM_FLAG_NAMES as $bit => $name) {
                if ($this->hasFlagBit($flags, $bit)) {
                    $names[] = $name;
                }
            }
        }

        return $names;
    }

    private function submitFormatFromFlags(int $flags): string
    {
        if ($this->hasFlagBit($flags, 9)) {
            return 'pdf';
        }

        if ($this->hasFlagBit($flags, 6)) {
            return 'xfdf';
        }

        if ($this->hasFlagBit($flags, 3)) {
            return 'html';
        }

        return 'fdf';
    }

    private function actionTriggerLabel(string $trigger): string
    {
        return match ($trigger) {
            'activation' => 'activation',
            'E' => 'cursor_enter',
            'X' => 'cursor_exit',
            'D' => 'mouse_down',
            'U' => 'mouse_up',
            'Fo' => 'focus',
            'Bl' => 'blur',
            'K' => 'keystroke',
            'F' => 'format',
            'V' => 'validate',
            'C' => 'calculate',
            default => 'unknown',
        };
    }

    private function fileSpecificationFromValue(string $value, array $objects): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $dictionary = $this->resolvedDictionaryFromValue($value, $objects);
        if ($dictionary !== null) {
            foreach (['UF', 'F', 'DOS', 'Mac', 'Unix'] as $name) {
                $file = $this->pdfStringValueAfterName($dictionary['body'], $name, $objects);
                if ($file !== null && $file !== '') {
                    return $file;
                }
            }

            return null;
        }

        $reference = $this->objectReferenceFromValue($value);
        if (
            $reference !== null
            && isset($objects[$reference['object']])
            && $this->referenceGenerationMatches($reference['object'], $reference['generation'], $objects)
        ) {
            return $this->pdfValueToString(trim($objects[$reference['object']]), $objects);
        }

        return $this->pdfValueToString($value, $objects);
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function fileSpecificationReviewFromValue(string $value, array $objects): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $filename = $this->fileSpecificationFromValue($value, $objects);
        $dictionary = $this->resolvedDictionaryFromValue($value, $objects);
        if ($dictionary === null) {
            if ($filename === null || $filename === '') {
                return null;
            }

            return [
                'source' => 'acroform_action_filespec_review_boundary',
                'file_spec_object' => $this->objectNumberFromReferenceValue($value),
                'type' => null,
                'file_system' => null,
                'filename' => $filename,
                'unicode_filename' => null,
                'platform_filenames' => ['F' => $filename],
                'description' => null,
                'relationship' => null,
                'embedded_file_count' => 0,
                'embedded_files' => [],
                'embedded_file_objects' => [],
                'related_file_count' => 0,
                'related_files' => [],
                'content_returned' => false,
                'embedded_payload_text_exposed' => false,
                'executes_action' => false,
            ];
        }

        $body = $dictionary['body'];
        $platformFilenames = $this->fileSpecificationPlatformFilenames($body, $objects);
        $embeddedFiles = $this->embeddedFilesFromFileSpecBody($body, $objects);
        $relatedFiles = $this->relatedFilesFromFileSpecBody($body, $objects);

        return [
            'source' => 'acroform_action_filespec_review_boundary',
            'file_spec_object' => $dictionary['object'],
            'type' => $this->pdfNameValueAfterName($body, 'Type'),
            'file_system' => $this->pdfNameValueAfterName($body, 'FS'),
            'filename' => $filename,
            'unicode_filename' => $this->pdfStringValueAfterName($body, 'UF', $objects),
            'platform_filenames' => $platformFilenames,
            'description' => $this->pdfStringValueAfterName($body, 'Desc', $objects),
            'relationship' => $this->pdfNameValueAfterName($body, 'AFRelationship'),
            'embedded_file_count' => count($embeddedFiles),
            'embedded_files' => $embeddedFiles,
            'embedded_file_objects' => $this->integerValuesFromRows($embeddedFiles, 'object'),
            'related_file_count' => count($relatedFiles),
            'related_files' => $relatedFiles,
            'content_returned' => false,
            'embedded_payload_text_exposed' => false,
            'executes_action' => false,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, string>
     */
    private function fileSpecificationPlatformFilenames(string $body, array $objects): array
    {
        $filenames = [];
        foreach (['F', 'UF', 'DOS', 'Mac', 'Unix'] as $name) {
            $filename = $this->pdfStringValueAfterName($body, $name, $objects);
            if ($filename !== null && $filename !== '') {
                $filenames[$name] = $filename;
            }
        }

        return $filenames;
    }

    /**
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function embeddedFilesFromFileSpecBody(string $body, array $objects): array
    {
        $efValue = $this->valueAfterName($body, 'EF');
        if ($efValue === null) {
            return [];
        }

        $ef = $this->resolvedDictionaryFromValue($efValue, $objects);
        if ($ef === null) {
            return [];
        }

        $files = [];
        foreach (['UF', 'F', 'DOS', 'Mac', 'Unix'] as $key) {
            $streamValue = $this->dictionaryEntryValueAfterName($ef['body'], $key);
            $stream = $streamValue === null ? null : $this->embeddedFileStreamReviewFromValue($streamValue, $objects, $key);
            if ($stream !== null) {
                $files[] = $stream;
            }
        }

        return $files;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function embeddedFileStreamReviewFromValue(string $value, array $objects, string $key): ?array
    {
        $objectNumber = $this->objectNumberFromReferenceValue($value);
        $objectBody = $objectNumber === null ? trim($value) : ($objects[$objectNumber] ?? null);
        if ($objectBody === null || $objectBody === '') {
            return null;
        }

        $dictionaryBody = $this->dictionaryObjectBody($objectBody)
            ?? (str_starts_with(trim($objectBody), '<<') ? $this->readPdfDictionaryAt($objectBody, 0) : null);
        if ($dictionaryBody === null) {
            return null;
        }

        $decoded = $this->decodeStreamObject($objectBody, $objects);

        return [
            'source' => 'filespec_embedded_file_stream_review',
            'key' => $key,
            'object' => $objectNumber,
            'type' => $this->pdfNameValueAfterName($dictionaryBody, 'Type'),
            'subtype' => $this->pdfNameValueAfterName($dictionaryBody, 'Subtype'),
            'declared_length_bytes' => $this->numberValueAfterName($dictionaryBody, 'Length'),
            'filters' => $this->streamObjectFilters($objectBody, $objects),
            'decoded_stream_available' => $decoded !== null,
            'decoded_length_bytes' => $decoded === null ? null : strlen($decoded),
            'decoded_sha256' => $decoded === null ? null : hash('sha256', $decoded),
            'params' => $this->embeddedFileParamsFromStreamDictionary($dictionaryBody, $objects),
            'content_returned' => false,
            'payload_text_exposed' => false,
            'executes_action' => false,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function embeddedFileParamsFromStreamDictionary(string $streamDictionaryBody, array $objects): array
    {
        $paramsValue = $this->valueAfterName($streamDictionaryBody, 'Params');
        if ($paramsValue === null) {
            return [];
        }

        $params = $this->resolvedDictionaryFromValue($paramsValue, $objects);
        if ($params === null) {
            return [];
        }

        return array_filter([
            'size' => $this->numberValueAfterName($params['body'], 'Size'),
            'check_sum' => $this->pdfStringValueAfterName($params['body'], 'CheckSum', $objects),
            'creation_date' => $this->pdfStringValueAfterName($params['body'], 'CreationDate', $objects),
            'mod_date' => $this->pdfStringValueAfterName($params['body'], 'ModDate', $objects),
        ], static fn (mixed $metadataValue): bool => $metadataValue !== null);
    }

    /**
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function relatedFilesFromFileSpecBody(string $body, array $objects): array
    {
        $rfValue = $this->valueAfterName($body, 'RF');
        if ($rfValue === null) {
            return [];
        }

        $rf = $this->resolvedDictionaryFromValue($rfValue, $objects);
        if ($rf === null) {
            return [];
        }

        $related = [];
        foreach ($this->dictionaryNameValueMap($rf['body']) as $key => $value) {
            if (!str_starts_with(trim($value), '[')) {
                continue;
            }

            $arrayBody = $this->arrayBodyFromValue($value);
            if ($arrayBody === null) {
                continue;
            }

            foreach ($this->relatedFilePairsFromArrayBody($key, $arrayBody, $objects) as $row) {
                $related[] = $row;
            }
        }

        return $related;
    }

    /**
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function relatedFilePairsFromArrayBody(string $key, string $arrayBody, array $objects): array
    {
        $pairs = [];
        $offset = 0;
        $length = strlen($arrayBody);
        while ($offset < $length) {
            $filenameValueEnd = null;
            $filenameValue = $this->readPdfValueAt($arrayBody, $offset, $filenameValueEnd);
            if ($filenameValue === null || $filenameValueEnd === null) {
                $offset++;
                continue;
            }

            $offset = $filenameValueEnd;
            $streamValueEnd = null;
            $streamValue = $this->readPdfValueAt($arrayBody, $offset, $streamValueEnd);
            if ($streamValue === null || $streamValueEnd === null) {
                $pairs[] = [
                    'source' => 'filespec_related_file_review',
                    'key' => $key,
                    'filename' => $this->fileSpecificationFromValue($filenameValue, $objects) ?? $this->pdfValueToString($filenameValue, $objects),
                    'embedded_file' => null,
                    'content_returned' => false,
                    'payload_text_exposed' => false,
                    'executes_action' => false,
                ];
                break;
            }

            $pairs[] = [
                'source' => 'filespec_related_file_review',
                'key' => $key,
                'filename' => $this->fileSpecificationFromValue($filenameValue, $objects) ?? $this->pdfValueToString($filenameValue, $objects),
                'embedded_file' => $this->embeddedFileStreamReviewFromValue($streamValue, $objects, $key),
                'content_returned' => false,
                'payload_text_exposed' => false,
                'executes_action' => false,
            ];
            $offset = $streamValueEnd;
        }

        return $pairs;
    }

    private function objectNumberFromReferenceValue(string $value): ?int
    {
        $reference = $this->objectReferenceFromValue($value);

        return $reference === null ? null : $reference['object'];
    }

    private function uriScheme(string $target): ?string
    {
        if (preg_match('/^([A-Za-z][A-Za-z0-9+.\-]*):/', $target, $match) !== 1) {
            return null;
        }

        return strtolower($match[1]);
    }

    /**
     * @param array<string, mixed>|null $fieldDefaultAppearance
     * @param array<string, array{value: string, source: string, source_object: int|null}> $effective
     * @return array<string, mixed>|null
     */
    private function widgetDefaultAppearance(string $widgetBody, ?array $fieldDefaultAppearance, array $effective, array $objects, int $widgetObject): ?array
    {
        $raw = $this->pdfStringValueAfterName($widgetBody, 'DA', []);
        if ($raw === null || $raw === '') {
            return $fieldDefaultAppearance;
        }

        $appearance = $this->parseDefaultAppearance($raw);
        $appearance = $this->defaultAppearanceWithResourceReview($appearance, $effective, $objects);
        $appearance['raw'] = $raw;
        $appearance['source'] = 'widget';
        $appearance['source_object'] = $widgetObject;

        return $appearance;
    }

    /**
     * @return list<array{export: string, label: string}>
     * @param array<string, array{value: string, source: string, source_object: int|null}> $effective
     */
    private function optionsFromEffective(array $effective, array $objects): array
    {
        if (!isset($effective['Opt'])) {
            return [];
        }

        $value = trim($effective['Opt']['value']);
        if (!str_starts_with($value, '[')) {
            return [];
        }

        $body = $this->arrayBodyFromValue($value);
        if ($body === null) {
            return [];
        }

        $options = [];
        $offset = 0;
        while ($offset < strlen($body)) {
            $this->skipWhitespace($body, $offset);
            if ($offset >= strlen($body)) {
                break;
            }

            if ($body[$offset] === '[') {
                $endOffset = null;
                $nested = $this->readPdfArrayAt($body, $offset, $endOffset);
                if ($nested === null || $endOffset === null) {
                    break;
                }
                $parts = $this->scalarValuesFromArrayBody($nested, $objects);
                if (count($parts) >= 2) {
                    $options[] = ['export' => $parts[0], 'label' => $parts[1]];
                }
                $offset = $endOffset;
                continue;
            }

            $item = $this->readScalarAt($body, $offset, $objects, $scalarEnd);
            if ($item !== null) {
                $options[] = ['export' => $item['value'], 'label' => $item['value']];
                $offset = $item['end'];
                continue;
            }
            if ($scalarEnd !== null && $scalarEnd > $offset) {
                $offset = $scalarEnd;
                continue;
            }

            $offset++;
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    private function scalarValuesFromArrayBody(string $body, array $objects): array
    {
        $values = [];
        $offset = 0;
        while ($offset < strlen($body)) {
            $this->skipWhitespace($body, $offset);
            $item = $this->readScalarAt($body, $offset, $objects, $scalarEnd);
            if ($item === null) {
                if ($scalarEnd !== null && $scalarEnd > $offset) {
                    $offset = $scalarEnd;
                    continue;
                }
                $offset++;
                continue;
            }
            $values[] = $item['value'];
            $offset = $item['end'];
        }

        return $values;
    }

    /**
     * @return array{value: string, end: int}|null
     */
    private function readScalarAt(string $body, int $offset, array $objects, ?int &$endOffset = null): ?array
    {
        $endOffset = null;
        $this->skipWhitespace($body, $offset);
        if ($offset >= strlen($body)) {
            return null;
        }

        if ($body[$offset] === '(') {
            $end = $this->skipLiteralString($body, $offset);
            $value = $this->decodePdfStringBytes($this->decodeLiteralString(substr($body, $offset + 1, $end - $offset - 2)));
            return ['value' => $value, 'end' => $end];
        }

        if ($body[$offset] === '<' && substr($body, $offset, 2) !== '<<') {
            $end = $this->skipHexString($body, $offset);
            $hex = preg_replace('/\s+/', '', substr($body, $offset + 1, $end - $offset - 2)) ?? '';
            if (strlen($hex) % 2 === 1) {
                $hex .= '0';
            }
            $bytes = $hex === '' ? '' : hex2bin($hex);
            if ($bytes === false) {
                return null;
            }

            return ['value' => $this->decodePdfStringBytes($bytes), 'end' => $end];
        }

        if ($body[$offset] === '/') {
            $end = $this->skipPdfName($body, $offset);
            return ['value' => $this->decodePdfName(substr($body, $offset, $end - $offset)), 'end' => $end];
        }

        $referenceEnd = null;
        $reference = $this->readIndirectReferenceAt($body, $offset, $referenceEnd);
        if ($reference !== null && $referenceEnd !== null) {
            $ref = $reference['object'];
            $generation = $reference['generation'];
            $endOffset = $referenceEnd;
            if (!isset($objects[$ref]) || !$this->referenceGenerationMatches($ref, $generation, $objects)) {
                return null;
            }
            $resolved = $this->pdfValueToString(trim($objects[$ref]), $objects);
            return $resolved === null ? null : ['value' => $resolved, 'end' => $endOffset];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     * @param array<int, string> $objects
     */
    private function normalAppearanceReview(string $widgetBody, array $objects, ?string $appearanceState, array $fieldNamesByObject = []): ?array
    {
        $apValue = $this->valueAfterName($widgetBody, 'AP');
        if ($apValue === null) {
            return null;
        }

        $ap = $this->resolvedDictionaryFromValue($apValue, $objects);
        if ($ap === null) {
            return [
                'source' => 'widget_appearance_dictionary',
                'appearance_dictionary_object' => null,
                'normal_appearance_type' => 'unresolved',
                'appearance_state' => $appearanceState,
                'available_states' => [],
                'selected_state' => null,
                'selected_appearance' => null,
                'state_matches_appearance' => null,
                'appearance_value_used_for_import' => false,
                'payload_text_exposed' => false,
                'executes_appearance_streams' => false,
                'renders_appearances' => false,
                'executes_action' => false,
            ];
        }

        $normalValue = $this->valueAfterName($ap['body'], 'N');
        if ($normalValue === null) {
            return [
                'source' => 'widget_appearance_dictionary',
                'appearance_dictionary_object' => $ap['object'],
                'normal_appearance_type' => 'missing',
                'appearance_state' => $appearanceState,
                'available_states' => [],
                'selected_state' => null,
                'selected_appearance' => null,
                'state_matches_appearance' => null,
                'appearance_value_used_for_import' => false,
                'payload_text_exposed' => false,
                'executes_appearance_streams' => false,
                'renders_appearances' => false,
                'executes_action' => false,
            ];
        }

        if ($this->valueReferencesStreamObject($normalValue, $objects)) {
            return [
                'source' => 'widget_appearance_dictionary',
                'appearance_dictionary_object' => $ap['object'],
                'normal_appearance_type' => 'direct_stream',
                'appearance_state' => $appearanceState,
                'available_states' => [],
                'selected_state' => null,
                'selected_appearance' => $this->appearanceStreamReviewFromValue($normalValue, $objects, null, 'normal_direct', $fieldNamesByObject),
                'state_matches_appearance' => null,
                'appearance_value_used_for_import' => false,
                'payload_text_exposed' => false,
                'executes_appearance_streams' => false,
                'renders_appearances' => false,
                'executes_action' => false,
            ];
        }

        $normalDictionary = $this->resolvedDictionaryFromValue($normalValue, $objects);
        if ($normalDictionary === null) {
            return [
                'source' => 'widget_appearance_dictionary',
                'appearance_dictionary_object' => $ap['object'],
                'normal_appearance_type' => 'unresolved',
                'appearance_state' => $appearanceState,
                'available_states' => [],
                'selected_state' => null,
                'selected_appearance' => null,
                'state_matches_appearance' => null,
                'appearance_value_used_for_import' => false,
                'payload_text_exposed' => false,
                'executes_appearance_streams' => false,
                'renders_appearances' => false,
                'executes_action' => false,
            ];
        }

        $entries = $this->dictionaryNameValueMap($normalDictionary['body']);
        $availableStates = array_keys($entries);
        $selectedValue = null;
        if ($appearanceState !== null && array_key_exists($appearanceState, $entries)) {
            $selectedValue = $entries[$appearanceState];
        }

        return [
            'source' => 'widget_appearance_dictionary',
            'appearance_dictionary_object' => $ap['object'],
            'normal_appearance_object' => $normalDictionary['object'],
            'normal_appearance_type' => 'state_dictionary',
            'appearance_state' => $appearanceState,
            'available_states' => $availableStates,
            'selected_state' => $selectedValue === null ? null : $appearanceState,
            'selected_appearance' => $selectedValue === null
                ? null
                : $this->appearanceStreamReviewFromValue($selectedValue, $objects, $appearanceState, 'normal_state', $fieldNamesByObject),
            'state_matches_appearance' => $appearanceState === null ? null : $selectedValue !== null,
            'stale_appearance_state' => $appearanceState !== null && $selectedValue === null,
            'appearance_value_used_for_import' => false,
            'payload_text_exposed' => false,
            'executes_appearance_streams' => false,
            'renders_appearances' => false,
            'executes_action' => false,
        ];
    }

    /**
     * @return array<string, mixed>|null
     * @param array<int, string> $objects
     */
    private function interactiveAppearanceReview(
        string $widgetBody,
        array $objects,
        ?string $appearanceState,
        string $appearanceKey,
        string $appearanceMode,
        array $fieldNamesByObject = []
    ): ?array {
        $apValue = $this->valueAfterName($widgetBody, 'AP');
        if ($apValue === null) {
            return null;
        }

        $ap = $this->resolvedDictionaryFromValue($apValue, $objects);
        if ($ap === null) {
            return null;
        }

        $appearanceValue = $this->valueAfterName($ap['body'], $appearanceKey);
        if ($appearanceValue === null) {
            return null;
        }

        if ($this->valueReferencesStreamObject($appearanceValue, $objects)) {
            return [
                'source' => 'widget_appearance_dictionary',
                'appearance_dictionary_object' => $ap['object'],
                'appearance_mode' => $appearanceMode,
                'appearance_key' => $appearanceKey,
                'appearance_type' => 'direct_stream',
                'appearance_state' => $appearanceState,
                'available_states' => [],
                'selected_state' => null,
                'selected_appearance' => $this->appearanceStreamReviewFromValue($appearanceValue, $objects, null, $appearanceMode . '_direct', $fieldNamesByObject),
                'state_matches_appearance' => null,
                'stale_appearance_state' => null,
                'appearance_value_used_for_import' => false,
                'payload_text_exposed' => false,
                'executes_appearance_streams' => false,
                'renders_appearances' => false,
                'executes_action' => false,
            ];
        }

        $appearanceDictionary = $this->resolvedDictionaryFromValue($appearanceValue, $objects);
        if ($appearanceDictionary === null) {
            return [
                'source' => 'widget_appearance_dictionary',
                'appearance_dictionary_object' => $ap['object'],
                'appearance_mode' => $appearanceMode,
                'appearance_key' => $appearanceKey,
                'appearance_type' => 'unresolved',
                'appearance_state' => $appearanceState,
                'available_states' => [],
                'selected_state' => null,
                'selected_appearance' => null,
                'state_matches_appearance' => null,
                'stale_appearance_state' => null,
                'appearance_value_used_for_import' => false,
                'payload_text_exposed' => false,
                'executes_appearance_streams' => false,
                'renders_appearances' => false,
                'executes_action' => false,
            ];
        }

        $entries = $this->dictionaryNameValueMap($appearanceDictionary['body']);
        $availableStates = array_keys($entries);
        $selectedValue = null;
        if ($appearanceState !== null && array_key_exists($appearanceState, $entries)) {
            $selectedValue = $entries[$appearanceState];
        }

        return [
            'source' => 'widget_appearance_dictionary',
            'appearance_dictionary_object' => $ap['object'],
            'appearance_dictionary_object_for_mode' => $appearanceDictionary['object'],
            'appearance_mode' => $appearanceMode,
            'appearance_key' => $appearanceKey,
            'appearance_type' => 'state_dictionary',
            'appearance_state' => $appearanceState,
            'available_states' => $availableStates,
            'selected_state' => $selectedValue === null ? null : $appearanceState,
            'selected_appearance' => $selectedValue === null
                ? null
                : $this->appearanceStreamReviewFromValue($selectedValue, $objects, $appearanceState, $appearanceMode . '_state', $fieldNamesByObject),
            'state_matches_appearance' => $appearanceState === null ? null : $selectedValue !== null,
            'stale_appearance_state' => $appearanceState !== null && $selectedValue === null,
            'appearance_value_used_for_import' => false,
            'payload_text_exposed' => false,
            'executes_appearance_streams' => false,
            'renders_appearances' => false,
            'executes_action' => false,
        ];
    }

    /**
     * @return array<string, mixed>|null
     * @param array<int, string> $objects
     */
    private function appearanceStreamReviewFromValue(string $value, array $objects, ?string $state, string $source, array $fieldNamesByObject = []): ?array
    {
        $value = trim($value);
        $objectNumber = null;
        $objectBody = null;
        $reference = $this->objectReferenceFromValue($value);
        if ($reference !== null && $this->referenceGenerationMatches($reference['object'], $reference['generation'], $objects)) {
            $objectNumber = $reference['object'];
            $objectBody = $objects[$objectNumber] ?? null;
        } elseif (str_starts_with($value, '<<')) {
            $objectBody = $value;
        }

        if ($objectBody === null) {
            return null;
        }

        $dictionaryBody = $this->dictionaryObjectBody($objectBody) ?? (str_starts_with(trim($objectBody), '<<') ? $this->readPdfDictionaryAt($objectBody, 0) : null);
        if ($dictionaryBody === null) {
            return null;
        }

        $decodedStream = $this->decodeStreamObject($objectBody, $objects);
        $resources = $this->appearanceResourceReview($dictionaryBody, $objects, $fieldNamesByObject);

        return [
            'source' => $source,
            'state' => $state,
            'object' => $objectNumber,
            'type' => $this->pdfNameValueAfterName($dictionaryBody, 'Type'),
            'subtype' => $this->pdfNameValueAfterName($dictionaryBody, 'Subtype'),
            'form_xobject' => $this->pdfNameValueAfterName($dictionaryBody, 'Type') === 'XObject'
                && $this->pdfNameValueAfterName($dictionaryBody, 'Subtype') === 'Form',
            'bbox' => $this->numericArrayValueAfterName($dictionaryBody, 'BBox'),
            'matrix' => $this->numericArrayValueAfterName($dictionaryBody, 'Matrix'),
            'declared_length_bytes' => $this->numberValueAfterName($dictionaryBody, 'Length'),
            'filters' => $this->streamObjectFilters($objectBody, $objects),
            'decoded_stream_available' => $decodedStream !== null,
            'decoded_length_bytes' => $decodedStream === null ? null : strlen($decodedStream),
            'decoded_sha256' => $decodedStream === null ? null : hash('sha256', $decodedStream),
            'resource_object' => $resources['object'],
            'resource_font_names' => $resources['font_names'],
            'resource_xobject_names' => $resources['xobject_names'],
            'resource_xobject_reviews' => $resources['xobject_reviews'],
            'resource_xobject_action_count' => $resources['xobject_action_count'],
            'resource_xobject_action_types' => $resources['xobject_action_types'],
            'resource_xobject_action_objects' => $resources['xobject_action_objects'],
            'resource_xobject_payload_text_exposed' => false,
            'payload_text_exposed' => false,
            'imports_visible_text' => false,
            'executes_appearance_streams' => false,
            'renders_appearances' => false,
            'executes_action' => false,
            'executes_javascript' => false,
        ];
    }

    /**
     * @return array{object: int|null, font_names: list<string>, xobject_names: list<string>, xobject_reviews: list<array<string, mixed>>, xobject_action_count: int, xobject_action_types: list<mixed>, xobject_action_objects: list<int>}
     * @param array<int, string> $objects
     */
    private function appearanceResourceReview(string $appearanceDictionaryBody, array $objects, array $fieldNamesByObject = []): array
    {
        $resourcesValue = $this->valueAfterName($appearanceDictionaryBody, 'Resources');
        $resources = $resourcesValue === null ? null : $this->resolvedDictionaryFromValue($resourcesValue, $objects);
        if ($resources === null) {
            return [
                'object' => null,
                'font_names' => [],
                'xobject_names' => [],
                'xobject_reviews' => [],
                'xobject_action_count' => 0,
                'xobject_action_types' => [],
                'xobject_action_objects' => [],
            ];
        }

        $xobjectReviews = $this->xobjectResourceReviewsFromResourceDictionary($resources['body'], $objects, $fieldNamesByObject);
        $xobjectActions = [];
        foreach ($xobjectReviews as $review) {
            foreach ($this->arrayRows($review['actions'] ?? []) as $action) {
                $xobjectActions[] = $action;
            }
        }

        return [
            'object' => $resources['object'],
            'font_names' => array_keys($this->fontResourcesFromDefaultResourceDictionary($resources['body'], $objects)),
            'xobject_names' => $this->xobjectResourceNamesFromResourceDictionary($resources['body'], $objects),
            'xobject_reviews' => $xobjectReviews,
            'xobject_action_count' => count($xobjectActions),
            'xobject_action_types' => $this->uniqueScalarValues(array_map(
                static fn (array $action): mixed => $action['action_type'] ?? null,
                $xobjectActions
            )),
            'xobject_action_objects' => $this->integerValuesFromRows($xobjectActions, 'action_object'),
        ];
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, string> $fieldNamesByObject
     * @return list<array<string, mixed>>
     */
    private function xobjectResourceReviewsFromResourceDictionary(string $resourceDictionary, array $objects, array $fieldNamesByObject): array
    {
        $xobjectValue = $this->valueAfterName($resourceDictionary, 'XObject');
        $xobjects = $xobjectValue === null ? null : $this->resolvedDictionaryFromValue($xobjectValue, $objects);
        if ($xobjects === null) {
            return [];
        }

        $reviews = [];
        foreach ($this->dictionaryNameValueMap($xobjects['body']) as $resourceName => $value) {
            $objectNumber = $this->objectNumberFromReferenceValue($value);
            $objectBody = $objectNumber === null ? trim($value) : ($objects[$objectNumber] ?? null);
            if ($objectBody === null || $objectBody === '') {
                continue;
            }

            $dictionaryBody = $this->dictionaryObjectBody($objectBody)
                ?? (str_starts_with(trim($objectBody), '<<') ? $this->readPdfDictionaryAt($objectBody, 0) : null);
            if ($dictionaryBody === null) {
                continue;
            }

            $sourceObject = $objectNumber ?? 0;
            $actionReview = $this->actionsWithReviewFromDictionary(
                $dictionaryBody,
                $objects,
                $fieldNamesByObject,
                'appearance_resource_xobject',
                $sourceObject
            );
            $actions = $actionReview['actions'];

            $reviews[] = [
                'source' => 'acroform_widget_appearance_resource_xobject_review_boundary',
                'resource_name' => $resourceName,
                'xobject_object' => $objectNumber,
                'type' => $this->pdfNameValueAfterName($dictionaryBody, 'Type'),
                'subtype' => $this->pdfNameValueAfterName($dictionaryBody, 'Subtype'),
                'bbox' => $this->numericArrayValueAfterName($dictionaryBody, 'BBox'),
                'matrix' => $this->numericArrayValueAfterName($dictionaryBody, 'Matrix'),
                'declared_length_bytes' => $this->numberValueAfterName($dictionaryBody, 'Length'),
                'filters' => $this->streamObjectFilters($objectBody, $objects),
                'decoded_stream_available' => $this->decodeStreamObject($objectBody, $objects) !== null,
                'action_count' => count($actions),
                'action_types' => $this->uniqueScalarValues(array_map(
                    static fn (array $action): mixed => $action['action_type'] ?? null,
                    $actions
                )),
                'action_objects' => $this->integerValuesFromRows($actions, 'action_object'),
                'actions' => $actions,
                'action_review' => $actionReview['review'],
                'review_only' => true,
                'payload_text_exposed' => false,
                'imports_visible_text' => false,
                'executes_action' => false,
                'executes_javascript' => false,
                'executes_appearance_streams' => false,
                'renders_appearances' => false,
            ];
        }

        return $reviews;
    }

    /**
     * @return array<string, mixed>|null
     * @param array<int, string> $objects
     */
    private function widgetAppearanceCharacteristics(string $widgetBody, array $objects): ?array
    {
        $mkValue = $this->valueAfterName($widgetBody, 'MK');
        if ($mkValue === null) {
            return null;
        }

        $mk = $this->resolvedDictionaryFromValue($mkValue, $objects);
        if ($mk === null) {
            return null;
        }

        $textPosition = $this->numberValueAfterName($mk['body'], 'TP');

        return array_filter([
            'source' => 'acroform_widget_mk_appearance_characteristics',
            'dictionary_object' => $mk['object'],
            'rotation' => $this->numberValueAfterName($mk['body'], 'R'),
            'border_color' => $this->colorValueAfterName($mk['body'], 'BC'),
            'background_color' => $this->colorValueAfterName($mk['body'], 'BG'),
            'normal_caption' => $this->pdfStringValueAfterName($mk['body'], 'CA', $objects),
            'rollover_caption' => $this->pdfStringValueAfterName($mk['body'], 'RC', $objects),
            'alternate_caption' => $this->pdfStringValueAfterName($mk['body'], 'AC', $objects),
            'text_position' => $textPosition,
            'text_position_label' => $textPosition === null ? null : (self::WIDGET_TEXT_POSITION_LABELS[$textPosition] ?? 'unknown'),
            'icon_object' => $this->objectReferenceValueAfterName($mk['body'], 'I'),
            'rollover_icon_object' => $this->objectReferenceValueAfterName($mk['body'], 'RI'),
            'alternate_icon_object' => $this->objectReferenceValueAfterName($mk['body'], 'IX'),
            'icon_fit' => $this->widgetIconFitFromAppearanceCharacteristics($mk['body'], $objects),
            'appearance_value_used_for_import' => false,
            'caption_text_used_for_import' => false,
            'icon_payload_text_exposed' => false,
            'renders_appearance' => false,
            'executes_action' => false,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>|null
     * @param array<int, string> $objects
     */
    private function widgetIconFitFromAppearanceCharacteristics(string $mkBody, array $objects): ?array
    {
        $value = $this->valueAfterName($mkBody, 'IF');
        if ($value === null) {
            return null;
        }

        $iconFit = $this->resolvedDictionaryFromValue($value, $objects);
        if ($iconFit === null) {
            return null;
        }

        $entries = $this->dictionaryNameValueMap($iconFit['body']);

        return array_filter([
            'scale_when' => $this->pdfNameFromValue($entries['SW'] ?? null),
            'scale_type' => $this->pdfNameFromValue($entries['S'] ?? null),
            'position' => $this->numericArrayFromValue($entries['A'] ?? null),
            'fit_bounds' => $this->boolFromValue($entries['FB'] ?? null),
            'renders_icon' => false,
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function pdfNameFromValue(?string $value): ?string
    {
        if ($value === null || !str_starts_with(trim($value), '/')) {
            return null;
        }

        return $this->decodePdfName($value);
    }

    private function boolFromValue(?string $value): ?bool
    {
        return match (trim((string) $value)) {
            'true' => true,
            'false' => false,
            default => null,
        };
    }

    /**
     * @return list<float>|null
     */
    private function numericArrayFromValue(?string $value): ?array
    {
        if ($value === null || !str_starts_with(trim($value), '[')) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromValue($value);
        if ($arrayBody === null) {
            return [];
        }

        return $this->numbersFromPdfArray($arrayBody);
    }

    /**
     * @return array{space: string, components: list<float>, hex: string|null}|null
     */
    private function colorValueAfterName(string $body, string $name): ?array
    {
        $components = $this->numericArrayValueAfterName($body, $name);
        if ($components === null) {
            return null;
        }

        $components = array_map(fn (float $component): float => $this->clamp($component), $components);
        $count = count($components);
        if ($count === 0) {
            return [
                'space' => 'transparent',
                'components' => [],
                'hex' => null,
            ];
        }

        if ($count === 1) {
            $rgb = [$components[0], $components[0], $components[0]];

            return [
                'space' => 'DeviceGray',
                'components' => $components,
                'hex' => $this->rgbHex($rgb),
            ];
        }

        if ($count === 3) {
            return [
                'space' => 'DeviceRGB',
                'components' => $components,
                'hex' => $this->rgbHex($components),
            ];
        }

        if ($count === 4) {
            [$c, $m, $y, $k] = $components;
            $rgb = [
                (1.0 - $c) * (1.0 - $k),
                (1.0 - $m) * (1.0 - $k),
                (1.0 - $y) * (1.0 - $k),
            ];

            return [
                'space' => 'DeviceCMYK',
                'components' => $components,
                'hex' => $this->rgbHex($rgb),
            ];
        }

        return [
            'space' => 'DeviceN',
            'components' => $components,
            'hex' => null,
        ];
    }

    /**
     * @param list<float> $components
     */
    private function rgbHex(array $components): string
    {
        $parts = [];
        foreach (array_slice($components, 0, 3) as $component) {
            $parts[] = str_pad(dechex((int) round($this->clamp($component) * 255)), 2, '0', STR_PAD_LEFT);
        }

        return '#' . implode('', $parts);
    }

    private function clamp(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }

    /**
     * @return list<string>
     * @param array<int, string> $objects
     */
    private function xobjectResourceNamesFromResourceDictionary(string $resourceDictionary, array $objects): array
    {
        $xobjectValue = $this->valueAfterName($resourceDictionary, 'XObject');
        $xobjects = $xobjectValue === null ? null : $this->resolvedDictionaryFromValue($xobjectValue, $objects);
        if ($xobjects === null) {
            return [];
        }

        return array_keys($this->dictionaryNameValueMap($xobjects['body']));
    }

    /**
     * @return array<string, string>
     */
    private function dictionaryNameValueMap(string $dictionaryBody): array
    {
        $entries = [];
        $offset = 0;
        $length = strlen($dictionaryBody);
        while ($offset < $length) {
            $this->skipWhitespace($dictionaryBody, $offset);
            if ($offset >= $length) {
                break;
            }

            if ($dictionaryBody[$offset] !== '/') {
                $offset++;
                continue;
            }

            $nameEnd = $this->skipPdfName($dictionaryBody, $offset);
            $name = $this->decodePdfName(substr($dictionaryBody, $offset, $nameEnd - $offset));
            $offset = $nameEnd;
            $endOffset = null;
            $value = $this->readPdfValueAt($dictionaryBody, $offset, $endOffset);
            if ($value === null || $endOffset === null) {
                continue;
            }

            $entries[$name] = $value;
            $offset = $endOffset;
        }

        return $entries;
    }

    /**
     * @return list<float>|null
     */
    private function numericArrayValueAfterName(string $body, string $name): ?array
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null || !str_starts_with(trim($value), '[')) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromValue($value);
        if ($arrayBody === null) {
            return [];
        }

        return $this->numbersFromPdfArray($arrayBody);
    }

    /**
     * @param array<int, string> $objects
     */
    private function valueReferencesStreamObject(string $value, array $objects): bool
    {
        $reference = $this->objectReferenceFromValue($value);

        return $reference !== null
            && str_contains($objects[$reference['object']] ?? '', 'stream');
    }

    /**
     * @return list<int>
     */
    private function fieldReferencesFromAcroForm(string $acroForm, array $objects): array
    {
        $fields = $this->valueAfterName($acroForm, 'Fields');
        if ($fields === null) {
            return [];
        }

        $body = $this->arrayBodyFromValueOrReference($fields, $objects);
        return $body === null ? [] : $this->validObjectReferences($body, $objects);
    }

    /**
     * @param list<int> $fieldRefs
     * @param array<int, string> $objects
     * @param array<int, array{page_index: int, page_object: int, annotation_index: int}> $pageWidgets
     * @return list<int>
     */
    private function fieldReferencesWithPageWidgetBoundaries(array $fieldRefs, array $objects, array $pageWidgets): array
    {
        $refs = $this->rootFieldReferencesFromAcroFormReferences($fieldRefs, $objects);
        $reachable = $this->fieldTreeObjectNumbers($refs, $objects);

        foreach (array_keys($pageWidgets) as $widgetObject) {
            if (isset($reachable[$widgetObject]) || !isset($objects[$widgetObject])) {
                continue;
            }

            $widgetBody = $this->dictionaryObjectBody($objects[$widgetObject]) ?? trim($objects[$widgetObject]);
            if (!$this->isWidget($widgetBody)) {
                continue;
            }

            $candidate = null;
            $parentObject = $this->validObjectReferenceValueAfterName($widgetBody, 'Parent', $objects);
            if ($parentObject !== null && isset($objects[$parentObject]) && !isset($reachable[$parentObject])) {
                $candidate = $this->pageWidgetRootFieldCandidate($parentObject, $objects, $reachable);
                if ($candidate !== null && !$this->fieldTreeContainsObject($candidate, $widgetObject, $objects)) {
                    $candidate = null;
                }
            } elseif ($this->isFieldWidgetDictionary($widgetBody)) {
                $candidate = $widgetObject;
            }

            if ($candidate === null || isset($reachable[$candidate]) || in_array($candidate, $refs, true) || !isset($objects[$candidate])) {
                continue;
            }

            $candidateBody = $this->dictionaryObjectBody($objects[$candidate]) ?? trim($objects[$candidate]);
            if (!$this->isFieldDictionaryCandidate($candidateBody)) {
                continue;
            }

            $refs[] = $candidate;
            foreach ($this->fieldTreeObjectNumbers([$candidate], $objects) as $objectNumber => $_) {
                $reachable[$objectNumber] = true;
            }
        }

        return $refs;
    }

    /**
     * @param list<int> $fieldRefs
     * @param array<int, string> $objects
     * @return list<int>
     */
    private function rootFieldReferencesFromAcroFormReferences(array $fieldRefs, array $objects): array
    {
        $refs = [];
        foreach ($fieldRefs as $fieldRef) {
            $candidate = $fieldRef;
            if (isset($objects[$fieldRef])) {
                $body = $this->dictionaryObjectBody($objects[$fieldRef]) ?? trim($objects[$fieldRef]);
                if ($this->isPureWidget($body)) {
                    $parentObject = $this->validObjectReferenceValueAfterName($body, 'Parent', $objects);
                    $rootField = $parentObject === null ? null : $this->pageWidgetRootFieldCandidate($parentObject, $objects, []);
                    if ($rootField !== null) {
                        $candidate = $rootField;
                    }
                }
            }

            if (!in_array($candidate, $refs, true)) {
                $refs[] = $candidate;
            }
        }

        return $refs;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $reachable
     */
    private function pageWidgetRootFieldCandidate(int $objectNumber, array $objects, array $reachable): ?int
    {
        $candidate = $objectNumber;
        $seen = [];

        while (isset($objects[$candidate]) && !isset($seen[$candidate])) {
            $seen[$candidate] = true;
            $body = $this->dictionaryObjectBody($objects[$candidate]) ?? trim($objects[$candidate]);
            if (!$this->isFieldDictionaryCandidate($body)) {
                return null;
            }

            $parentObject = $this->validObjectReferenceValueAfterName($body, 'Parent', $objects);
            if ($parentObject === null || !isset($objects[$parentObject]) || isset($reachable[$parentObject])) {
                return $candidate;
            }

            $parentBody = $this->dictionaryObjectBody($objects[$parentObject]) ?? trim($objects[$parentObject]);
            if (!$this->isFieldDictionaryCandidate($parentBody)) {
                return $candidate;
            }
            if (!$this->fieldParentOwnsChild($parentObject, $candidate, $objects)) {
                return $candidate;
            }

            $candidate = $parentObject;
        }

        return $candidate;
    }

    /**
     * @param array<int, string> $objects
     */
    private function fieldParentOwnsChild(int $parentObject, int $childObject, array $objects): bool
    {
        if (!isset($objects[$parentObject])) {
            return false;
        }

        $parentBody = $this->dictionaryObjectBody($objects[$parentObject]) ?? trim($objects[$parentObject]);
        return in_array($childObject, $this->kidReferences($parentBody, $objects), true);
    }

    /**
     * @param list<int> $roots
     * @param array<int, string> $objects
     * @return array<int, true>
     */
    private function fieldTreeObjectNumbers(array $roots, array $objects): array
    {
        $seen = [];
        foreach ($roots as $root) {
            $this->collectFieldTreeObjectNumbers($root, $objects, $seen);
        }

        return $seen;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function fieldTreeContainsObject(int $rootObject, int $targetObject, array $objects, array $seen = []): bool
    {
        if ($rootObject === $targetObject) {
            return true;
        }

        if (isset($seen[$rootObject]) || !isset($objects[$rootObject])) {
            return false;
        }

        $seen[$rootObject] = true;
        $body = $this->dictionaryObjectBody($objects[$rootObject]) ?? trim($objects[$rootObject]);
        foreach ($this->kidReferences($body, $objects) as $kidRef) {
            if ($kidRef === $targetObject || $this->fieldTreeContainsObject($kidRef, $targetObject, $objects, $seen)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function collectFieldTreeObjectNumbers(int $objectNumber, array $objects, array &$seen): void
    {
        if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
            return;
        }

        $seen[$objectNumber] = true;
        $body = $this->dictionaryObjectBody($objects[$objectNumber]) ?? trim($objects[$objectNumber]);
        foreach ($this->kidReferences($body, $objects) as $kidRef) {
            $this->collectFieldTreeObjectNumbers($kidRef, $objects, $seen);
        }
    }

    /**
     * @return list<int>
     */
    private function kidReferences(string $body, array $objects): array
    {
        $kids = $this->valueAfterName($body, 'Kids');
        if ($kids === null) {
            return [];
        }

        $body = $this->arrayBodyFromValueOrReference($kids, $objects);
        return $body === null ? [] : $this->validObjectReferences($body, $objects);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function arrayBodyFromValueOrReference(string $value, array $objects, array $seen = []): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '[')) {
            return $this->arrayBodyFromValue($value);
        }

        $reference = $this->validObjectReferenceFromValue($value, $objects);
        if ($reference === null || isset($seen[$reference])) {
            return null;
        }

        $seen[$reference] = true;
        return $this->arrayBodyFromValueOrReference(trim($objects[$reference]), $objects, $seen);
    }

    private function isFieldWidgetDictionary(string $body): bool
    {
        return $this->isWidget($body)
            && (
                $this->valueAfterName($body, 'T') !== null
                || $this->valueAfterName($body, 'TM') !== null
                || $this->valueAfterName($body, 'FT') !== null
            );
    }

    private function isFieldDictionaryCandidate(string $body): bool
    {
        return $this->valueAfterName($body, 'T') !== null
            || $this->valueAfterName($body, 'TM') !== null
            || $this->valueAfterName($body, 'FT') !== null
            || $this->valueAfterName($body, 'Kids') !== null
            || $this->isWidget($body);
    }

    private function isPureWidget(string $body): bool
    {
        if (!$this->isWidget($body)) {
            return false;
        }

        return $this->valueAfterName($body, 'T') === null
            && $this->valueAfterName($body, 'TM') === null
            && $this->valueAfterName($body, 'FT') === null
            && $this->valueAfterName($body, 'Kids') === null;
    }

    private function isWidget(string $body): bool
    {
        return $this->pdfNameValueAfterName($body, 'Subtype') === 'Widget';
    }

    /**
     * @return array<int, array{page_index: int, page_object: int, annotation_index: int}>
     * @param array<int, string> $objects
     * @param list<int> $pageObjectNumbers
     */
    private function pageWidgetMap(array $objects, array $pageObjectNumbers): array
    {
        $widgets = [];
        foreach ($pageObjectNumbers as $pageIndex => $pageObjectNumber) {
            if (!isset($objects[$pageObjectNumber])) {
                continue;
            }

            $annots = $this->valueAfterName($objects[$pageObjectNumber], 'Annots');
            if ($annots === null) {
                continue;
            }

            $annotationRefs = $this->annotationObjectReferences($annots, $objects);
            foreach ($annotationRefs as $annotationIndex => $annotationRef) {
                $annotationBody = $this->dictionaryObjectBody($objects[$annotationRef] ?? '') ?? '';
                if ($annotationBody === '' || !$this->isWidget($annotationBody)) {
                    continue;
                }
                if (!$this->widgetAnnotationBelongsToPage($annotationBody, $objects, $pageObjectNumber)) {
                    continue;
                }

                $widgets[$annotationRef] = [
                    'page_index' => $pageIndex,
                    'page_object' => $pageObjectNumber,
                    'annotation_index' => $annotationIndex,
                ];
            }
        }

        return $widgets;
    }

    /**
     * @param array<int, string> $objects
     */
    private function widgetAnnotationBelongsToPage(string $annotationBody, array $objects, int $pageObjectNumber): bool
    {
        $pageValue = $this->valueAfterName($annotationBody, 'P');
        if ($pageValue === null) {
            return true;
        }

        $pageObject = $this->validObjectReferenceFromValue($pageValue, $objects);
        return $pageObject === $pageObjectNumber;
    }

    /**
     * @return list<int>
     */
    private function annotationObjectReferences(string $annots, array $objects): array
    {
        $annots = trim($annots);
        $reference = $this->validObjectReferenceFromValue($annots, $objects);
        if ($reference !== null) {
            if (!isset($objects[$reference])) {
                return [];
            }

            $objectBody = trim($objects[$reference]);
            if (str_starts_with($objectBody, '[')) {
                $arrayBody = $this->arrayBodyFromValue($objectBody);
                return $arrayBody === null ? [] : $this->validObjectReferences($arrayBody, $objects);
            }

            return [$reference];
        }

        if (!str_starts_with($annots, '[')) {
            return [];
        }

        $arrayBody = $this->arrayBodyFromValue($annots);
        return $arrayBody === null ? [] : $this->validObjectReferences($arrayBody, $objects);
    }

    private function acroFormDictionaryBody(string $catalog, array $objects): ?string
    {
        $value = $this->valueAfterName($catalog, 'AcroForm');
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if (str_starts_with($value, '<<')) {
            return $this->readPdfDictionaryAt($value, 0);
        }

        $objectNumber = $this->validObjectReferenceFromValue($value, $objects);
        if ($objectNumber !== null) {
            return $this->dictionaryObjectBody($objects[$objectNumber] ?? '');
        }

        return null;
    }

    /**
     * @return list<float>|null
     */
    private function rectFromAnnotation(string $annotationBody, array $objects): ?array
    {
        $value = $this->valueAfterName($annotationBody, 'Rect');
        if ($value === null) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromValueOrReference($value, $objects);
        if ($arrayBody === null) {
            return null;
        }

        $numbers = $this->numbersFromPdfArrayResolvingObjects($arrayBody, $objects);
        if (count($numbers) < 4) {
            return null;
        }

        $rect = array_slice($numbers, 0, 4);
        foreach ($rect as $number) {
            if (!is_float($number) && !is_int($number)) {
                return null;
            }
        }

        return $this->normalizeRect($rect);
    }

    /**
     * @param list<float> $rect
     * @return list<float>
     */
    private function normalizeRect(array $rect): array
    {
        return [
            min($rect[0], $rect[2]),
            min($rect[1], $rect[3]),
            max($rect[0], $rect[2]),
            max($rect[1], $rect[3]),
        ];
    }

    private function pdfValueToPhpValue(string $value, array $objects): mixed
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '[')) {
            $body = $this->arrayBodyFromValue($value);
            return $body === null ? [] : $this->scalarValuesFromArrayBody($body, $objects);
        }

        return $this->pdfValueToString($value, $objects);
    }

    private function pdfValueToString(string $value, array $objects): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if ($value[0] === '(') {
            $end = $this->skipLiteralString($value, 0);
            return $this->decodePdfStringBytes($this->decodeLiteralString(substr($value, 1, $end - 2)));
        }

        if ($value[0] === '<' && substr($value, 0, 2) !== '<<') {
            $end = $this->skipHexString($value, 0);
            $hex = preg_replace('/\s+/', '', substr($value, 1, $end - 2)) ?? '';
            if (strlen($hex) % 2 === 1) {
                $hex .= '0';
            }
            $bytes = $hex === '' ? '' : hex2bin($hex);
            return $bytes === false ? null : $this->decodePdfStringBytes($bytes);
        }

        if ($value[0] === '/') {
            return $this->decodePdfName($value);
        }

        $reference = $this->objectReferenceFromValue($value);
        if ($reference !== null) {
            $objectNumber = $reference['object'];
            if (!isset($objects[$objectNumber]) || !$this->referenceGenerationMatches($objectNumber, $reference['generation'], $objects)) {
                return null;
            }

            return $this->pdfValueToString(trim($objects[$objectNumber]), $objects);
        }

        if ($value === 'null') {
            return null;
        }

        if ($value === 'true' || $value === 'false' || is_numeric($value)) {
            return $value;
        }

        return null;
    }

    private function pdfStringValueAfterName(string $body, string $name, array $objects): ?string
    {
        $value = $this->valueAfterName($body, $name);
        return $value === null ? null : $this->pdfValueToString($value, $objects);
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function scalarListValueAfterName(string $body, string $name, array $objects): array
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null) {
            return [];
        }

        $value = trim($value);
        if (str_starts_with($value, '[')) {
            $arrayBody = $this->arrayBodyFromValue($value);
            return $arrayBody === null ? [] : $this->scalarValuesFromArrayBody($arrayBody, $objects);
        }

        $scalar = $this->pdfValueToString($value, $objects);
        return $scalar === null ? [] : [$scalar];
    }

    /**
     * @return list<int>|null
     */
    private function integerArrayValueAfterName(string $body, string $name): ?array
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null || !str_starts_with(trim($value), '[')) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromValue($value);
        if ($arrayBody === null || preg_match_all('/[+-]?\d+/', $arrayBody, $matches) === false) {
            return [];
        }

        return array_map('intval', $matches[0]);
    }

    private function pdfNameValueAfterName(string $body, string $name): ?string
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null || !str_starts_with(trim($value), '/')) {
            return null;
        }

        return $this->decodePdfName($value);
    }

    /**
     * @param array<int, string> $objects
     */
    private function pdfNameFromValueResolvingObjects(string $value, array $objects, array $seen = []): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $reference = $this->objectReferenceFromValue($value);
        if ($reference !== null) {
            $objectNumber = $reference['object'];
            if (isset($seen[$objectNumber]) || !$this->referenceGenerationMatches($objectNumber, $reference['generation'], $objects)) {
                return null;
            }

            $seen[$objectNumber] = true;
            return $this->pdfNameFromValueResolvingObjects(trim($objects[$objectNumber]), $objects, $seen);
        }

        if (!str_starts_with($value, '/')) {
            return null;
        }

        return $this->decodePdfName($value);
    }

    private function realValueAfterName(string $body, string $name): ?float
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null || preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)/', trim($value), $match) !== 1) {
            return null;
        }

        return (float) $match[0];
    }

    private function numberValueAfterName(string $body, string $name): ?int
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null || preg_match('/^[+-]?\d+/', trim($value), $match) !== 1) {
            return null;
        }

        return (int) $match[0];
    }

    private function numberValueAfterNameResolvingObjects(string $body, string $name, array $objects): ?int
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null) {
            return null;
        }

        $number = $this->pdfNumberFromValue($value, $objects);
        return $number === null ? null : (int) $number;
    }

    private function objectReferenceValueAfterName(string $body, string $name): ?int
    {
        $value = $this->valueAfterName($body, $name);
        $reference = $this->objectReferenceFromValue($value);

        return $reference === null ? null : $reference['object'];
    }

    private function validObjectReferenceValueAfterName(string $body, string $name, array $objects): ?int
    {
        return $this->validObjectReferenceFromValue($this->valueAfterName($body, $name), $objects);
    }

    /**
     * @param array<int, string> $objects
     */
    private function validObjectReferenceFromValue(?string $value, array $objects): ?int
    {
        $reference = $this->objectReferenceFromValue($value);
        if ($reference === null) {
            return null;
        }

        return $this->referenceGenerationMatches($reference['object'], $reference['generation'], $objects)
            ? $reference['object']
            : null;
    }

    /**
     * @return array{object: int, generation: int}|null
     */
    private function objectReferenceFromValue(?string $value): ?array
    {
        return $value === null ? null : $this->readIndirectReferenceAt($value, 0);
    }

    /**
     * @return array{object: int, generation: int}|null
     */
    private function readIndirectReferenceAt(string $body, int $offset, ?int &$endOffset = null): ?array
    {
        $this->skipWhitespace($body, $offset);
        if ($offset >= strlen($body) || preg_match('/\G\d+/s', $body, $objectMatch, 0, $offset) !== 1) {
            return null;
        }

        $objectNumber = (int) $objectMatch[0];
        $offset += strlen($objectMatch[0]);
        if (!$this->isPdfTokenBoundary($body, $offset)) {
            return null;
        }

        $this->skipWhitespace($body, $offset);
        if ($offset >= strlen($body) || preg_match('/\G\d+/s', $body, $generationMatch, 0, $offset) !== 1) {
            return null;
        }

        $generation = (int) $generationMatch[0];
        $offset += strlen($generationMatch[0]);
        if (!$this->isPdfTokenBoundary($body, $offset)) {
            return null;
        }

        $this->skipWhitespace($body, $offset);
        if (($body[$offset] ?? '') !== 'R') {
            return null;
        }

        $afterReference = $offset + 1;
        if (!$this->isPdfTokenBoundary($body, $afterReference)) {
            return null;
        }

        $endOffset = $afterReference;

        return [
            'object' => $objectNumber,
            'generation' => $generation,
        ];
    }

    private function isPdfTokenBoundary(string $body, int $offset): bool
    {
        if ($offset >= strlen($body)) {
            return true;
        }

        $char = $body[$offset];

        return ctype_space($char) || str_contains('[]()<>{}/%', $char);
    }

    /**
     * @param array<int, string> $objects
     */
    private function referenceGenerationMatches(int $objectNumber, int $generation, array $objects): bool
    {
        return isset($objects[$objectNumber])
            && ($this->objectGenerations[$objectNumber] ?? 0) === $generation;
    }

    private function boolValueAfterName(string $body, string $name): ?bool
    {
        $value = $this->valueAfterName($body, $name);
        return match (trim((string) $value)) {
            'true' => true,
            'false' => false,
            default => null,
        };
    }

    private function valueAfterName(string $body, string $name): ?string
    {
        $dictionaryBody = $this->topLevelDictionaryBody($body);
        if ($dictionaryBody !== null) {
            $body = $dictionaryBody;
        }

        $offset = $this->offsetAfterTopLevelName($body, $name);
        if ($offset === null) {
            return null;
        }

        $this->skipWhitespace($body, $offset);
        if ($offset >= strlen($body)) {
            return null;
        }

        $referenceEnd = null;
        $reference = $this->readIndirectReferenceAt($body, $offset, $referenceEnd);
        if ($reference !== null && $referenceEnd !== null) {
            return substr($body, $offset, $referenceEnd - $offset);
        }

        if ($body[$offset] === '[') {
            $endOffset = null;
            $this->readPdfArrayAt($body, $offset, $endOffset);
            return $endOffset === null ? null : substr($body, $offset, $endOffset - $offset);
        }

        if (substr($body, $offset, 2) === '<<') {
            $endOffset = null;
            $this->readPdfDictionaryAt($body, $offset, $endOffset);
            return $endOffset === null ? null : substr($body, $offset, $endOffset - $offset);
        }

        if ($body[$offset] === '(') {
            $endOffset = $this->skipLiteralString($body, $offset);
            return substr($body, $offset, $endOffset - $offset);
        }

        if ($body[$offset] === '<') {
            $endOffset = $this->skipHexString($body, $offset);
            return substr($body, $offset, $endOffset - $offset);
        }

        if ($body[$offset] === '/') {
            $endOffset = $this->skipPdfName($body, $offset);
            return substr($body, $offset, $endOffset - $offset);
        }

        $end = $offset;
        while ($end < strlen($body) && !ctype_space($body[$end]) && !str_contains('[]()<>{}/%', $body[$end])) {
            $end++;
        }

        return substr($body, $offset, max(0, $end - $offset));
    }

    private function topLevelDictionaryBody(string $body): ?string
    {
        $offset = 0;
        $this->skipWhitespace($body, $offset);
        if (substr($body, $offset, 2) !== '<<') {
            return null;
        }

        return $this->readPdfDictionaryAt($body, $offset);
    }

    private function offsetAfterTopLevelName(string $body, string $name): ?int
    {
        $offset = 0;
        $length = strlen($body);
        while ($offset < $length) {
            $this->skipWhitespace($body, $offset);
            if ($offset >= $length) {
                break;
            }

            $char = $body[$offset];
            if ($char === '(') {
                $offset = $this->skipLiteralString($body, $offset);
                continue;
            }

            if ($char === '<' && substr($body, $offset, 2) === '<<') {
                $endOffset = null;
                $this->readPdfDictionaryAt($body, $offset, $endOffset);
                $offset = $endOffset ?? ($offset + 2);
                continue;
            }

            if ($char === '<') {
                $offset = $this->skipHexString($body, $offset);
                continue;
            }

            if ($char === '[') {
                $endOffset = null;
                $this->readPdfArrayAt($body, $offset, $endOffset);
                $offset = $endOffset ?? ($offset + 1);
                continue;
            }

            if ($char === '/') {
                $endOffset = $this->skipPdfName($body, $offset);
                if ($this->decodePdfName(substr($body, $offset, $endOffset - $offset)) === $name) {
                    return $endOffset;
                }
                $offset = $endOffset;
                continue;
            }

            $offset++;
        }

        return null;
    }

    private function readPdfValueAt(string $body, int $offset, ?int &$endOffset = null): ?string
    {
        $this->skipWhitespace($body, $offset);
        if ($offset >= strlen($body)) {
            return null;
        }

        $referenceEnd = null;
        $reference = $this->readIndirectReferenceAt($body, $offset, $referenceEnd);
        if ($reference !== null && $referenceEnd !== null) {
            $endOffset = $referenceEnd;
            return substr($body, $offset, $referenceEnd - $offset);
        }

        if ($body[$offset] === '[') {
            $end = null;
            $this->readPdfArrayAt($body, $offset, $end);
            if ($end === null) {
                return null;
            }
            $endOffset = $end;
            return substr($body, $offset, $end - $offset);
        }

        if (substr($body, $offset, 2) === '<<') {
            $end = null;
            $this->readPdfDictionaryAt($body, $offset, $end);
            if ($end === null) {
                return null;
            }
            $endOffset = $end;
            return substr($body, $offset, $end - $offset);
        }

        if ($body[$offset] === '(') {
            $endOffset = $this->skipLiteralString($body, $offset);
            return substr($body, $offset, $endOffset - $offset);
        }

        if ($body[$offset] === '<') {
            $endOffset = $this->skipHexString($body, $offset);
            return substr($body, $offset, $endOffset - $offset);
        }

        if ($body[$offset] === '/') {
            $endOffset = $this->skipPdfName($body, $offset);
            return substr($body, $offset, $endOffset - $offset);
        }

        $end = $offset;
        while ($end < strlen($body) && !ctype_space($body[$end]) && !str_contains('[]()<>{}/%', $body[$end])) {
            $end++;
        }

        if ($end === $offset) {
            return null;
        }

        $endOffset = $end;
        return substr($body, $offset, $end - $offset);
    }

    /**
     * @param array<int, string> $objects
     * @return array{body: string, object: int|null}|null
     */
    private function resolvedDictionaryFromValue(string $value, array $objects): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '<<')) {
            $body = $this->readPdfDictionaryAt($value, 0);
            return $body === null ? null : ['body' => $body, 'object' => null];
        }

        $objectNumber = $this->validObjectReferenceFromValue($value, $objects);
        if ($objectNumber === null) {
            return null;
        }

        $body = $this->dictionaryObjectBody($objects[$objectNumber] ?? '');
        return $body === null ? null : ['body' => $body, 'object' => $objectNumber];
    }

    /**
     * @param array<int, string> $objects
     * @return list<array{body: string, object: int|null}>
     */
    private function dictionaryValuesFromArrayBody(string $body, array $objects): array
    {
        $dictionaries = [];
        $offset = 0;
        $length = strlen($body);
        while ($offset < $length) {
            $this->skipWhitespace($body, $offset);
            if ($offset >= $length) {
                break;
            }

            if (substr($body, $offset, 2) === '<<') {
                $endOffset = null;
                $dictionaryBody = $this->readPdfDictionaryAt($body, $offset, $endOffset);
                if ($dictionaryBody === null || $endOffset === null) {
                    $offset++;
                    continue;
                }

                $dictionaries[] = ['body' => $dictionaryBody, 'object' => null];
                $offset = $endOffset;
                continue;
            }

            $referenceEnd = null;
            $reference = $this->readIndirectReferenceAt($body, $offset, $referenceEnd);
            if ($reference !== null && $referenceEnd !== null) {
                $objectNumber = $reference['object'];
                $dictionaryBody = $this->referenceGenerationMatches($objectNumber, $reference['generation'], $objects)
                    ? $this->dictionaryObjectBody($objects[$objectNumber] ?? '')
                    : null;
                if ($dictionaryBody !== null) {
                    $dictionaries[] = ['body' => $dictionaryBody, 'object' => $objectNumber];
                }
                $offset = $referenceEnd;
                continue;
            }

            $offset++;
        }

        return $dictionaries;
    }

    private function signatureContentsLength(string $value, array $objects): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if ($value[0] === '<' && substr($value, 0, 2) !== '<<') {
            $end = $this->skipHexString($value, 0);
            $hex = preg_replace('/\s+/', '', substr($value, 1, $end - 2)) ?? '';
            return intdiv(strlen($hex) + 1, 2);
        }

        if ($value[0] === '(') {
            $end = $this->skipLiteralString($value, 0);
            return strlen($this->decodeLiteralString(substr($value, 1, $end - 2)));
        }

        $reference = $this->objectReferenceFromValue($value);
        if (
            $reference !== null
            && isset($objects[$reference['object']])
            && $this->referenceGenerationMatches($reference['object'], $reference['generation'], $objects)
        ) {
            return $this->signatureContentsLength(trim($objects[$reference['object']]), $objects);
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @return array{present: bool, bytes: int|null, sha1: string|null, sha256: string|null, raw_bytes_exposed: bool}
     */
    private function signatureContentsDigest(string $value, array $objects): array
    {
        $bytes = $this->signatureContentsBytes($value, $objects);
        if ($bytes === null) {
            return $this->emptySignatureContentsDigest();
        }

        return [
            'present' => true,
            'bytes' => strlen($bytes),
            'sha1' => hash('sha1', $bytes),
            'sha256' => hash('sha256', $bytes),
            'raw_bytes_exposed' => false,
        ];
    }

    /**
     * @return array{present: bool, bytes: int|null, sha1: string|null, sha256: string|null, raw_bytes_exposed: bool}
     */
    private function emptySignatureContentsDigest(): array
    {
        return [
            'present' => false,
            'bytes' => null,
            'sha1' => null,
            'sha256' => null,
            'raw_bytes_exposed' => false,
        ];
    }

    /**
     * @param array<int, string> $objects
     */
    private function signatureContentsBytes(string $value, array $objects): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if ($value[0] === '<' && substr($value, 0, 2) !== '<<') {
            $end = $this->skipHexString($value, 0);
            $hex = preg_replace('/\s+/', '', substr($value, 1, $end - 2)) ?? '';
            if ($hex !== '' && preg_match('/^[\da-fA-F]+$/', $hex) !== 1) {
                return null;
            }
            if (strlen($hex) % 2 === 1) {
                $hex .= '0';
            }

            $bytes = hex2bin($hex);
            return is_string($bytes) ? $bytes : null;
        }

        if ($value[0] === '(') {
            $end = $this->skipLiteralString($value, 0);
            return $this->decodeLiteralString(substr($value, 1, $end - 2));
        }

        $reference = $this->objectReferenceFromValue($value);
        if (
            $reference !== null
            && isset($objects[$reference['object']])
            && $this->referenceGenerationMatches($reference['object'], $reference['generation'], $objects)
        ) {
            return $this->signatureContentsBytes(trim($objects[$reference['object']]), $objects);
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodeStreamObject(string $objectBody, array $objects): ?string
    {
        if (!preg_match('/<<(.*?)>>\s*stream\r?\n?(.*?)\r?\n?endstream/s', $objectBody, $match)) {
            return null;
        }

        return $this->decodeStream($match[1], $match[2], $objects);
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodeStream(string $dict, string $stream, array $objects): ?string
    {
        foreach ($this->streamFilters($dict, $objects) as $filter) {
            $decoded = match ($filter) {
                'ASCIIHexDecode', 'AHx' => $this->decodeAsciiHexStream($stream),
                'FlateDecode', 'Fl' => $this->decodeFlateStream($stream),
                default => $stream,
            };
            if ($decoded === null) {
                return null;
            }
            $stream = $decoded;
        }

        return $stream;
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function streamObjectFilters(string $objectBody, array $objects): array
    {
        if (!preg_match('/<<(.*?)>>\s*stream/s', $objectBody, $match)) {
            return [];
        }

        return $this->streamFilters($match[1], $objects);
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function streamFilters(string $dict, array $objects): array
    {
        $filter = $this->valueAfterName($dict, 'Filter');
        if ($filter === null) {
            return [];
        }

        return $this->filterNamesFromValue($filter, $objects);
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function filterNamesFromValue(string $value, array $objects): array
    {
        $filters = [];
        $offset = 0;
        $length = strlen($value);
        while ($offset < $length) {
            $this->skipWhitespace($value, $offset);
            if ($offset >= $length) {
                break;
            }

            if ($value[$offset] === '/') {
                $nameEnd = $this->skipPdfName($value, $offset);
                $filters[] = $this->decodePdfName(substr($value, $offset + 1, $nameEnd - $offset - 1));
                $offset = $nameEnd;
                continue;
            }

            $referenceEnd = null;
            $reference = $this->readIndirectReferenceAt($value, $offset, $referenceEnd);
            if ($reference !== null && $referenceEnd !== null) {
                $objectNumber = $reference['object'];
                if (
                    isset($objects[$objectNumber])
                    && $this->referenceGenerationMatches($objectNumber, $reference['generation'], $objects)
                ) {
                    foreach ($this->filterNamesFromValue($objects[$objectNumber], $objects) as $filter) {
                        $filters[] = $filter;
                    }
                }
                $offset = $referenceEnd;
                continue;
            }

            if ($value[$offset] === '[') {
                $endOffset = null;
                $arrayBody = $this->readPdfArrayAt($value, $offset, $endOffset);
                if ($arrayBody !== null) {
                    foreach ($this->filterNamesFromValue($arrayBody, $objects) as $filter) {
                        $filters[] = $filter;
                    }
                }
                $offset = $endOffset ?? ($offset + 1);
                continue;
            }

            $endOffset = null;
            $this->readPdfValueAt($value, $offset, $endOffset);
            $offset = $endOffset !== null && $endOffset > $offset ? $endOffset : $offset + 1;
        }

        return $filters;
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

    private function decodeFlateStream(string $stream): ?string
    {
        $inflated = @gzuncompress($stream);
        if ($inflated === false) {
            $inflated = @gzinflate($stream);
        }
        if ($inflated === false) {
            $inflated = @gzdecode($stream);
        }

        return $inflated === false ? null : $inflated;
    }

    private function skipWhitespace(string $body, int &$offset): void
    {
        $length = strlen($body);
        while ($offset < $length) {
            while ($offset < $length && ctype_space($body[$offset])) {
                $offset++;
            }

            if (($body[$offset] ?? '') !== '%') {
                break;
            }

            $offset = $this->skipPdfComment($body, $offset);
        }
    }

    private function skipPdfComment(string $body, int $offset): int
    {
        $length = strlen($body);
        for ($index = $offset + 1; $index < $length; $index++) {
            if ($body[$index] === "\n" || $body[$index] === "\r") {
                return $index + 1;
            }
        }

        return $length;
    }

    private function skipPdfName(string $body, int $offset): int
    {
        $end = $offset + 1;
        while ($end < strlen($body) && !ctype_space($body[$end]) && !str_contains('[]()<>{}/%', $body[$end])) {
            $end++;
        }

        return $end;
    }

    /**
     * @return array<int, string>
     */
    private function pdfObjects(string $pdfBytes): array
    {
        $objects = [];
        $this->objectGenerations = [];

        $offset = 0;
        while (preg_match('/(\d+)\s+(\d+)\s+obj\b/s', $pdfBytes, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $bodyStart = $match[0][1] + strlen($match[0][0]);
            $bodyEnd = $this->pdfObjectEndOffset($pdfBytes, $bodyStart);
            if ($bodyEnd === null) {
                break;
            }

            $objectNumber = (int) $match[1][0];
            $generation = (int) $match[2][0];
            $selectedGeneration = $this->objectGenerations[$objectNumber] ?? null;
            if ($selectedGeneration !== null && $generation < $selectedGeneration) {
                $offset = $bodyEnd + strlen('endobj');
                continue;
            }

            $objects[$objectNumber] = substr($pdfBytes, $bodyStart, $bodyEnd - $bodyStart);
            $this->objectGenerations[$objectNumber] = $generation;
            $offset = $bodyEnd + strlen('endobj');
        }

        return $objects;
    }

    private function pdfObjectEndOffset(string $pdfBytes, int $offset): ?int
    {
        $index = $offset;
        $length = strlen($pdfBytes);
        while ($index < $length) {
            $char = $pdfBytes[$index];
            if ($char === '%') {
                $index = $this->skipPdfComment($pdfBytes, $index);
                continue;
            }

            if ($char === '(') {
                $index = $this->skipLiteralString($pdfBytes, $index);
                continue;
            }

            if ($char === '<') {
                if (($pdfBytes[$index + 1] ?? '') === '<') {
                    $endOffset = null;
                    $this->readPdfDictionaryAt($pdfBytes, $index, $endOffset);
                    if ($endOffset !== null) {
                        $index = $endOffset;
                        continue;
                    }
                } else {
                    $index = $this->skipHexString($pdfBytes, $index);
                    continue;
                }
            }

            if ($char === '[') {
                $endOffset = null;
                $this->readPdfArrayAt($pdfBytes, $index, $endOffset);
                if ($endOffset !== null) {
                    $index = $endOffset;
                    continue;
                }
            }

            if ($char === '/') {
                $index = $this->skipPdfName($pdfBytes, $index);
                continue;
            }

            if ($this->pdfKeywordAt($pdfBytes, $index, 'stream')) {
                $streamEnd = $this->pdfStreamEndOffset($pdfBytes, $index);
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

    private function pdfStreamEndOffset(string $pdfBytes, int $streamKeywordOffset): ?int
    {
        $offset = $streamKeywordOffset + strlen('stream');
        if (substr($pdfBytes, $offset, 2) === "\r\n") {
            $offset += 2;
        } elseif (($pdfBytes[$offset] ?? '') === "\n" || ($pdfBytes[$offset] ?? '') === "\r") {
            $offset++;
        }

        $searchOffset = $offset;
        while (($candidate = strpos($pdfBytes, 'endstream', $searchOffset)) !== false) {
            if ($this->pdfKeywordAt($pdfBytes, $candidate, 'endstream')) {
                return $candidate;
            }

            $searchOffset = $candidate + 1;
        }

        return null;
    }

    private function pdfKeywordAt(string $body, int $offset, string $keyword): bool
    {
        if (substr($body, $offset, strlen($keyword)) !== $keyword) {
            return false;
        }

        $before = $offset === 0 ? null : $body[$offset - 1];
        $afterOffset = $offset + strlen($keyword);
        $after = $afterOffset >= strlen($body) ? null : $body[$afterOffset];

        return $this->isPdfKeywordBoundary($before) && $this->isPdfKeywordBoundary($after);
    }

    private function isPdfKeywordBoundary(?string $char): bool
    {
        return $char === null || ctype_space($char) || str_contains('[]()<>{}/%', $char);
    }

    /**
     * @param string $pdfBytes
     * @param array<int, string> $objects
     */
    private function catalogObjectBody(string $pdfBytes, array $objects): ?string
    {
        $rootReference = $this->currentTrailerRootReference($pdfBytes);
        if ($rootReference !== null) {
            $rootObject = $rootReference['object'];
            if (
                isset($objects[$rootObject])
                && ($this->objectGenerations[$rootObject] ?? 0) === $rootReference['generation']
            ) {
                $body = $this->dictionaryObjectBody($objects[$rootObject]);
                if ($body !== null && preg_match('/\/Type\s*\/Catalog\b/', $body) === 1) {
                    return $body;
                }
            }
        }

        foreach ($objects as $body) {
            if (preg_match('/\/Type\s*\/Catalog\b/', $body) === 1) {
                return $this->dictionaryObjectBody($body);
            }
        }

        return null;
    }

    /**
     * @return array{object: int, generation: int}|null
     */
    private function currentTrailerRootReference(string $pdfBytes): ?array
    {
        if (preg_match_all('/\bstartxref\s+(\d+)/s', $pdfBytes, $matches, PREG_SET_ORDER) < 1) {
            return null;
        }

        $latest = end($matches);
        if (!is_array($latest)) {
            return null;
        }

        $seenOffsets = [];
        return $this->trailerRootReferenceFromClassicXrefOffset($pdfBytes, (int) $latest[1], $seenOffsets);
    }

    /**
     * @param array<int, true> $seenOffsets
     * @return array{object: int, generation: int}|null
     */
    private function trailerRootReferenceFromClassicXrefOffset(string $pdfBytes, int $offset, array &$seenOffsets): ?array
    {
        if ($offset < 0 || isset($seenOffsets[$offset])) {
            return null;
        }

        $seenOffsets[$offset] = true;
        $this->skipWhitespace($pdfBytes, $offset);
        if (substr($pdfBytes, $offset, 4) !== 'xref') {
            return null;
        }

        $trailer = $this->classicXrefTrailerDictionaryBody($pdfBytes, $offset);
        if ($trailer === null) {
            return null;
        }

        $root = $this->objectReferenceFromValue($this->valueAfterName($trailer, 'Root'));
        if ($root !== null) {
            return $root;
        }

        $previousOffset = $this->numberValueAfterName($trailer, 'Prev');
        if ($previousOffset === null) {
            return null;
        }

        return $this->trailerRootReferenceFromClassicXrefOffset($pdfBytes, $previousOffset, $seenOffsets);
    }

    private function classicXrefTrailerDictionaryBody(string $pdfBytes, int $offset): ?string
    {
        $trailerOffset = strpos($pdfBytes, 'trailer', $offset + 4);
        if ($trailerOffset === false) {
            return null;
        }

        $dictionaryOffset = strpos($pdfBytes, '<<', $trailerOffset);
        if ($dictionaryOffset === false) {
            return null;
        }

        return $this->readPdfDictionaryAt($pdfBytes, $dictionaryOffset);
    }

    /**
     * @return list<int>
     * @param array<int, string> $objects
     * @param string|null $catalog
     */
    private function orderedPageObjectNumbers(array $objects, ?string $catalog = null): array
    {
        if ($catalog !== null) {
            $pagesObject = $this->validObjectReferenceValueAfterName($catalog, 'Pages', $objects);
            if ($pagesObject !== null) {
                $pages = $this->pageObjectNumbersFromTree($pagesObject, $objects);
                if ($pages !== []) {
                    return $pages;
                }
            }
        }

        foreach ($objects as $body) {
            if (preg_match('/\/Type\s*\/Catalog\b/', $body) !== 1) {
                continue;
            }

            $pagesObject = $this->validObjectReferenceValueAfterName($body, 'Pages', $objects);
            if ($pagesObject === null) {
                continue;
            }

            $pages = $this->pageObjectNumbersFromTree($pagesObject, $objects);
            if ($pages !== []) {
                return $pages;
            }
        }

        $pages = [];
        foreach ($objects as $objectNumber => $body) {
            if (preg_match('/\/Type\s*\/Page\b/', $body) === 1) {
                $pages[] = $objectNumber;
            }
        }

        return $pages;
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
        if (preg_match('/\/Type\s*\/Page\b/', $body) === 1) {
            return [$objectNumber];
        }

        $kids = $this->valueAfterName($body, 'Kids');
        if ($kids === null || !str_starts_with(trim($kids), '[')) {
            return [];
        }

        $arrayBody = $this->arrayBodyFromValue($kids);
        if ($arrayBody === null) {
            return [];
        }

        $pages = [];
        foreach ($this->validObjectReferences($arrayBody, $objects) as $childObjectNumber) {
            foreach ($this->pageObjectNumbersFromTree($childObjectNumber, $objects, $seen) as $pageObjectNumber) {
                $pages[] = $pageObjectNumber;
            }
        }

        return $pages;
    }

    /**
     * @return list<int>
     */
    private function objectReferences(string $value): array
    {
        $references = [];
        $offset = 0;
        $length = strlen($value);
        while ($offset < $length) {
            $this->skipWhitespace($value, $offset);
            if ($offset >= $length) {
                break;
            }

            $referenceEnd = null;
            $reference = $this->readIndirectReferenceAt($value, $offset, $referenceEnd);
            if ($reference !== null && $referenceEnd !== null) {
                $references[] = $reference['object'];
                $offset = $referenceEnd;
                continue;
            }

            $endOffset = null;
            $this->readPdfValueAt($value, $offset, $endOffset);
            $offset = $endOffset !== null && $endOffset > $offset ? $endOffset : $offset + 1;
        }

        return $references;
    }

    /**
     * Keep missing review-only object references visible, but reject references
     * whose generation conflicts with the current selected object body.
     *
     * @param array<int, string> $objects
     * @return list<int>
     */
    private function reviewObjectReferencesWithCurrentGenerationBoundary(string $value, array $objects): array
    {
        $references = [];
        $offset = 0;
        $length = strlen($value);
        while ($offset < $length) {
            $this->skipWhitespace($value, $offset);
            if ($offset >= $length) {
                break;
            }

            $char = $value[$offset];
            if ($char === '(') {
                $offset = $this->skipLiteralString($value, $offset);
                continue;
            }

            if ($char === '[') {
                $endOffset = null;
                $this->readPdfArrayAt($value, $offset, $endOffset);
                $offset = $endOffset ?? ($offset + 1);
                continue;
            }

            if ($char === '<' && substr($value, $offset, 2) === '<<') {
                $endOffset = null;
                $this->readPdfDictionaryAt($value, $offset, $endOffset);
                $offset = $endOffset ?? ($offset + 2);
                continue;
            }

            if ($char === '<') {
                $offset = $this->skipHexString($value, $offset);
                continue;
            }

            if ($char === '/') {
                $offset = $this->skipPdfName($value, $offset);
                continue;
            }

            $referenceEnd = null;
            $reference = $this->readIndirectReferenceAt($value, $offset, $referenceEnd);
            if ($reference !== null && $referenceEnd !== null) {
                $objectNumber = $reference['object'];
                $generation = $reference['generation'];
                if (!isset($objects[$objectNumber]) || $this->referenceGenerationMatches($objectNumber, $generation, $objects)) {
                    $references[] = $objectNumber;
                }
                $offset = $referenceEnd;
                continue;
            }

            $endOffset = null;
            $this->readPdfValueAt($value, $offset, $endOffset);
            $offset = $endOffset !== null && $endOffset > $offset ? $endOffset : $offset + 1;
        }

        return $references;
    }

    /**
     * @param array<int, string> $objects
     * @return list<int>
     */
    private function validObjectReferences(string $value, array $objects): array
    {
        $references = [];
        $offset = 0;
        $length = strlen($value);
        while ($offset < $length) {
            $this->skipWhitespace($value, $offset);
            if ($offset >= $length) {
                break;
            }

            $char = $value[$offset];
            if ($char === '(') {
                $offset = $this->skipLiteralString($value, $offset);
                continue;
            }

            if ($char === '[') {
                $endOffset = null;
                $this->readPdfArrayAt($value, $offset, $endOffset);
                $offset = $endOffset ?? ($offset + 1);
                continue;
            }

            if ($char === '<' && substr($value, $offset, 2) === '<<') {
                $endOffset = null;
                $this->readPdfDictionaryAt($value, $offset, $endOffset);
                $offset = $endOffset ?? ($offset + 2);
                continue;
            }

            if ($char === '<') {
                $offset = $this->skipHexString($value, $offset);
                continue;
            }

            if ($char === '/') {
                $offset = $this->skipPdfName($value, $offset);
                continue;
            }

            $referenceEnd = null;
            $reference = $this->readIndirectReferenceAt($value, $offset, $referenceEnd);
            if ($reference !== null && $referenceEnd !== null) {
                $objectNumber = $reference['object'];
                $generation = $reference['generation'];
                if ($this->referenceGenerationMatches($objectNumber, $generation, $objects)) {
                    $references[] = $objectNumber;
                }
                $offset = $referenceEnd;
                continue;
            }

            $endOffset = null;
            $this->readPdfValueAt($value, $offset, $endOffset);
            $offset = $endOffset !== null && $endOffset > $offset ? $endOffset : $offset + 1;
        }

        return $references;
    }

    private function dictionaryObjectBody(string $objectBody): ?string
    {
        $offset = strpos($objectBody, '<<');
        return $offset === false ? null : $this->readPdfDictionaryAt($objectBody, $offset);
    }

    private function arrayBodyFromValue(string $value): ?string
    {
        $offset = strpos($value, '[');
        return $offset === false ? null : $this->readPdfArrayAt($value, $offset);
    }

    private function readPdfDictionaryAt(string $value, int $offset, ?int &$endOffset = null): ?string
    {
        if (substr($value, $offset, 2) !== '<<') {
            return null;
        }

        $depth = 0;
        $bodyStart = $offset + 2;
        for ($index = $offset, $length = strlen($value); $index < $length - 1; $index++) {
            $char = $value[$index];
            if ($char === '(') {
                $index = $this->skipLiteralString($value, $index) - 1;
                continue;
            }
            if ($char === '%') {
                $index = $this->skipPdfComment($value, $index) - 1;
                continue;
            }
            if ($char === '<' && substr($value, $index, 2) !== '<<') {
                $index = $this->skipHexString($value, $index) - 1;
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
                $endOffset = $index + 2;
                return substr($value, $bodyStart, $index - $bodyStart);
            }
            $index++;
        }

        return null;
    }

    private function readPdfArrayAt(string $value, int $offset, ?int &$endOffset = null): ?string
    {
        if (($value[$offset] ?? '') !== '[') {
            return null;
        }

        $depth = 0;
        $bodyStart = $offset + 1;
        for ($index = $offset, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '(') {
                $index = $this->skipLiteralString($value, $index) - 1;
                continue;
            }
            if ($char === '%') {
                $index = $this->skipPdfComment($value, $index) - 1;
                continue;
            }
            if ($char === '<' && substr($value, $index, 2) === '<<') {
                $endDictionary = null;
                $this->readPdfDictionaryAt($value, $index, $endDictionary);
                if ($endDictionary !== null) {
                    $index = $endDictionary - 1;
                    continue;
                }
            }
            if ($char === '<') {
                $index = $this->skipHexString($value, $index) - 1;
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
                $endOffset = $index + 1;
                return substr($value, $bodyStart, $index - $bodyStart);
            }
        }

        return null;
    }

    private function skipLiteralString(string $value, int $offset): int
    {
        $depth = 0;
        for ($index = $offset, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '\\') {
                $index++;
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char !== ')') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                return $index + 1;
            }
        }

        return strlen($value);
    }

    private function skipHexString(string $value, int $offset): int
    {
        $end = strpos($value, '>', $offset + 1);
        return $end === false ? strlen($value) : $end + 1;
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

    /**
     * @return list<float|null>
     */
    private function numbersFromPdfArrayResolvingObjects(string $arrayBody, array $objects): array
    {
        $numbers = [];
        $offset = 0;
        $length = strlen($arrayBody);
        while ($offset < $length) {
            $value = $this->readPdfValueAt($arrayBody, $offset, $endOffset);
            if ($value === null || $endOffset === null) {
                $offset++;
                continue;
            }

            $numbers[] = $this->pdfNumberFromValue($value, $objects);
            $offset = $endOffset;
        }

        return $numbers;
    }

    private function pdfNumberFromValue(string $value, array $objects, array $seen = []): ?float
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $reference = $this->objectReferenceFromValue($value);
        if ($reference !== null) {
            $objectNumber = $reference['object'];
            if (isset($seen[$objectNumber]) || !$this->referenceGenerationMatches($objectNumber, $reference['generation'], $objects)) {
                return null;
            }

            $seen[$objectNumber] = true;
            return $this->pdfNumberFromValue(trim($objects[$objectNumber]), $objects, $seen);
        }

        if (preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/', $value) !== 1) {
            return null;
        }

        return (float) $value;
    }

    private function decodePdfName(string $name): string
    {
        $name = ltrim($name, '/');

        return preg_replace_callback('/#([0-9A-Fa-f]{2})/', static fn (array $match): string => chr(hexdec($match[1])), $name) ?? $name;
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
}
