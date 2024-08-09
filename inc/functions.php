<?php

/**
 * Check if a headers array contains the Metorik user agent.
 */
function metorik_check_headers_agent($headers)
{
    // make header keys lowercase
    $headers = array_change_key_case($headers);

    // check if headers has user agent
    if (
        $headers &&
        isset($headers['user_agent']) &&
        $headers['user_agent']
    ) {
        // get user agent
        $user_agent = $headers['user_agent'];

        // if array, use first key
        if (is_array($user_agent)) {
            $user_agent = $user_agent[0];
        }

        // lowercase
        $user_agent = strtolower($user_agent);

        // if user agent has metorik in it return true
        if (strpos($user_agent, 'metorik') !== false) {
            return true;
        }
    }

    // got it here? false
    return false;
}

/**
 * Check if Metorik cart tracking is enabled.
 * @see Metorik_Cart_Tracking::cart_tracking_enabled
 *
 * @return bool
 */
function metorik_cart_tracking_enabled()
{
    return Metorik_Cart_Tracking::cart_tracking_enabled();
}
