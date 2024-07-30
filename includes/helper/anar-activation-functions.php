<?php
function awca_check_activation_state()
{
    $activation_key = get_option('_awca_activation_key');
    return ($activation_key && awca_is_valid_activation_key($activation_key)) ? true : false;
}

function awca_is_valid_activation_key()
{
    $tokenValidation = awca_get_data_from_api('https://api.anar360.com/api/360/auth/validate');

    if ($tokenValidation !== null) {
        if (isset($tokenValidation->success) && $tokenValidation->success === true) {
            return true;
        }
    }
    return false;
}

function awca_save_activation_key()
{
    try {
        if (isset($_POST['activation_code'])) {
            $activation_code = sanitize_text_field($_POST['activation_code']);
            $activation = update_option('_awca_activation_key', $activation_code);
            if ($activation) {
                return true;
            } else {
                throw new Exception('Failed to update activation key.');
            }
        } else {
            throw new Exception('Activation code not found in POST data.');
        }
    } catch (Exception $e) {
        awca_log('Error: ' . $e->getMessage());

        // Sentry\captureException($e);

        return false;
    }
}

function awca_get_activation_key()
{
    return (get_option('_awca_activation_key')) ? get_option('_awca_activation_key') : '';
}
