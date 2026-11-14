<?php
/**
 * Gravity Forms JSON hidden field integration.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('gform_form_post_get_meta', 'asgf_json_ensure_field');
add_action('gform_pre_submission', 'asgf_json_populate_field', 5);

/**
 * Adds the dynamic JSON field to a form definition when it is missing.
 *
 * @param array $form Gravity Forms form definition.
 * @return array
 */
function asgf_json_ensure_field($form)
{
    if (!asgf_json_is_gravity_forms_available() || !is_array($form)) {
        return $form;
    }

    if (asgf_json_get_json_field($form)) {
        return $form;
    }

    $field_id = asgf_json_get_next_field_id($form);
    $field    = array(
        'id'                => $field_id,
        'type'              => 'hidden',
        'label'             => 'JSON',
        'adminLabel'        => 'JSON',
        'isRequired'        => false,
        'allowsPrepopulate' => false,
    );

    if (class_exists('GF_Fields') && method_exists('GF_Fields', 'create')) {
        $field = GF_Fields::create($field);
    }

    if (!isset($form['fields']) || !is_array($form['fields'])) {
        $form['fields'] = array();
    }

    $form['fields'][] = $field;

    return $form;
}

/**
 * Populates the JSON field value before Gravity Forms saves the entry.
 *
 * @param array $form Gravity Forms form definition.
 * @return void
 */
function asgf_json_populate_field($form)
{
    if (!asgf_json_is_gravity_forms_available() || !is_array($form)) {
        return;
    }

    $form       = asgf_json_ensure_field($form);
    $json_field = asgf_json_get_json_field($form);

    if (!$json_field) {
        return;
    }

    $payload    = asgf_json_build_payload($form);
    $json_value = wp_json_encode($payload);

    if (false === $json_value) {
        return;
    }

    $_POST['input_' . asgf_json_get_field_id($json_field)] = $json_value;
}

/**
 * Builds the submitted form payload.
 *
 * The entry ID is not available during gform_pre_submission, so it is left empty
 * while the form ID and submitted field values are populated before feeds run.
 *
 * @param array $form Gravity Forms form definition.
 * @return array
 */
function asgf_json_build_payload($form)
{
    $payload = array(
        'gf_entry_id' => '',
        'gf_form_id'  => rgar($form, 'id'),
    );

    foreach (asgf_json_get_form_fields($form) as $field) {
        if (asgf_json_is_json_field($field)) {
            continue;
        }

        $field_id = asgf_json_get_field_id($field);

        if (!$field_id) {
            continue;
        }

        $key = asgf_json_get_payload_key($field);

        if (asgf_json_is_checkbox_field($field)) {
            $value = asgf_json_get_checkbox_value($field);
        } elseif (asgf_json_has_inputs($field)) {
            $value = asgf_json_get_compound_value($field);
        } else {
            $value = asgf_json_get_posted_value('input_' . $field_id);
        }

        if (asgf_json_is_empty_value($value)) {
            continue;
        }

        $payload[$key] = $value;
    }

    return $payload;
}

/**
 * Gets the payload key for a Gravity Forms field.
 *
 * @param mixed $field Gravity Forms field object or array.
 * @return string
 */
function asgf_json_get_payload_key($field)
{
    $salesforce_key = asgf_json_get_salesforce_mapping_key($field);

    if ('' !== $salesforce_key) {
        return asgf_json_normalize_key($salesforce_key);
    }

    $admin_label = asgf_json_get_field_property($field, 'adminLabel');

    if ('' !== $admin_label) {
        return asgf_json_normalize_key($admin_label);
    }

    $label = asgf_json_get_field_property($field, 'label');

    if ('' !== $label) {
        return asgf_json_normalize_key($label);
    }

    return 'field_' . asgf_json_get_field_id($field);
}

/**
 * Gets the Salesforce mapping key from known field setting names.
 *
 * @param mixed $field Gravity Forms field object or array.
 * @return string
 */
function asgf_json_get_salesforce_mapping_key($field)
{
    $properties = array(
        'salesforceMappingKey',
        'salesforce_mapping_key',
        'salesforceKey',
        'salesforceField',
        'salesforceFieldName',
    );

    foreach ($properties as $property) {
        $value = asgf_json_get_field_property($field, $property);

        if ('' !== $value) {
            return $value;
        }
    }

    return '';
}

/**
 * Gets the payload key for a compound input.
 *
 * @param array $input Gravity Forms input definition.
 * @return string
 */
function asgf_json_get_input_key($input)
{
    $label = isset($input['label']) ? (string) $input['label'] : '';

    if ('' !== $label) {
        return asgf_json_normalize_key($label);
    }

    $id = isset($input['id']) ? (string) $input['id'] : '';

    return '' !== $id ? asgf_json_normalize_key($id) : 'input';
}

/**
 * Normalises labels and mapping keys for JSON payload keys.
 *
 * @param string $key Raw key.
 * @return string
 */
function asgf_json_normalize_key($key)
{
    $key = strtolower((string) $key);
    $key = preg_replace('/[^a-z0-9]+/', '_', $key);
    $key = trim((string) $key, '_');

    return '' !== $key ? $key : 'field';
}

/**
 * Checks whether Gravity Forms classes required for this integration are loaded.
 *
 * @return bool
 */
function asgf_json_is_gravity_forms_available()
{
    return class_exists('GFForms') || class_exists('GF_Fields');
}

/**
 * Gets the existing JSON field from a form definition.
 *
 * @param array $form Gravity Forms form definition.
 * @return mixed|null
 */
function asgf_json_get_json_field($form)
{
    foreach (asgf_json_get_form_fields($form) as $field) {
        if (asgf_json_is_json_field($field)) {
            return $field;
        }
    }

    return null;
}

/**
 * Checks whether a field is the JSON hidden field.
 *
 * @param mixed $field Gravity Forms field object or array.
 * @return bool
 */
function asgf_json_is_json_field($field)
{
    return 'JSON' === asgf_json_get_field_property($field, 'label')
        || 'JSON' === asgf_json_get_field_property($field, 'adminLabel');
}

/**
 * Gets the next available field ID.
 *
 * @param array $form Gravity Forms form definition.
 * @return int
 */
function asgf_json_get_next_field_id($form)
{
    $highest_id = 0;

    foreach (asgf_json_get_form_fields($form) as $field) {
        $highest_id = max($highest_id, (int) asgf_json_get_field_id($field));
    }

    return $highest_id + 1;
}

/**
 * Gets form fields safely from a Gravity Forms form definition.
 *
 * @param array $form Gravity Forms form definition.
 * @return array
 */
function asgf_json_get_form_fields($form)
{
    $fields = rgar($form, 'fields');

    return is_array($fields) ? $fields : array();
}

/**
 * Gets a field ID from a Gravity Forms field.
 *
 * @param mixed $field Gravity Forms field object or array.
 * @return string
 */
function asgf_json_get_field_id($field)
{
    return asgf_json_get_field_property($field, 'id');
}

/**
 * Gets a field property from either an object or an array.
 *
 * @param mixed  $field Gravity Forms field object or array.
 * @param string $property Property name.
 * @return string
 */
function asgf_json_get_field_property($field, $property)
{
    if (is_object($field) && isset($field->{$property})) {
        return (string) $field->{$property};
    }

    if (is_array($field) && isset($field[$property])) {
        return (string) $field[$property];
    }

    return '';
}

/**
 * Checks whether a field has compound inputs.
 *
 * @param mixed $field Gravity Forms field object or array.
 * @return bool
 */
function asgf_json_has_inputs($field)
{
    $inputs = asgf_json_get_field_inputs($field);

    return !empty($inputs);
}

/**
 * Gets field inputs from an object or an array.
 *
 * @param mixed $field Gravity Forms field object or array.
 * @return array
 */
function asgf_json_get_field_inputs($field)
{
    if (is_object($field) && isset($field->inputs) && is_array($field->inputs)) {
        return $field->inputs;
    }

    if (is_array($field) && isset($field['inputs']) && is_array($field['inputs'])) {
        return $field['inputs'];
    }

    return array();
}

/**
 * Checks whether a field is a checkbox field.
 *
 * @param mixed $field Gravity Forms field object or array.
 * @return bool
 */
function asgf_json_is_checkbox_field($field)
{
    return 'checkbox' === asgf_json_get_field_property($field, 'type');
}

/**
 * Gets selected checkbox choice labels.
 *
 * @param mixed $field Gravity Forms field object or array.
 * @return array
 */
function asgf_json_get_checkbox_value($field)
{
    $selected = array();
    $inputs   = asgf_json_get_field_inputs($field);

    foreach ($inputs as $input) {
        if (!is_array($input) || empty($input['id'])) {
            continue;
        }

        $input_name = 'input_' . str_replace('.', '_', (string) $input['id']);
        $value      = asgf_json_get_posted_value($input_name);

        if (asgf_json_is_empty_value($value)) {
            continue;
        }

        $selected[] = isset($input['label']) && '' !== (string) $input['label'] ? (string) $input['label'] : $value;
    }

    return $selected;
}

/**
 * Gets child values for compound fields such as Name and Address.
 *
 * @param mixed $field Gravity Forms field object or array.
 * @return array
 */
function asgf_json_get_compound_value($field)
{
    $value = array();

    foreach (asgf_json_get_field_inputs($field) as $input) {
        if (!is_array($input) || empty($input['id'])) {
            continue;
        }

        $input_name = 'input_' . str_replace('.', '_', (string) $input['id']);
        $child      = asgf_json_get_posted_value($input_name);

        if (asgf_json_is_empty_value($child)) {
            continue;
        }

        $value[asgf_json_get_input_key($input)] = $child;
    }

    return $value;
}

/**
 * Reads a submitted value from $_POST.
 *
 * @param string $key Posted input key.
 * @return mixed
 */
function asgf_json_get_posted_value($key)
{
    if (!isset($_POST[$key])) {
        return '';
    }

    return wp_unslash($_POST[$key]);
}

/**
 * Checks whether a payload value should be skipped.
 *
 * @param mixed $value Value to check.
 * @return bool
 */
function asgf_json_is_empty_value($value)
{
    if (is_array($value)) {
        return empty(array_filter($value, 'asgf_json_is_not_empty_value'));
    }

    return '' === trim((string) $value);
}

/**
 * Callback used to retain non-empty array values.
 *
 * @param mixed $value Value to check.
 * @return bool
 */
function asgf_json_is_not_empty_value($value)
{
    return !asgf_json_is_empty_value($value);
}
