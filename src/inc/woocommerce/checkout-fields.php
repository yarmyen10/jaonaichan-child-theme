<?php
/**
 * Make selected billing fields optional on checkout.
 */
add_filter( 'woocommerce_checkout_fields', function ( $fields ) {
    $optional = [
        'billing_first_name',
        'billing_last_name',
        'billing_country',
        'billing_address_1',
        'billing_city',
        'billing_state',
        'billing_postcode',
        'billing_phone',
    ];

    foreach ( $optional as $key ) {
        if ( isset( $fields['billing'][ $key ] ) ) {
            $fields['billing'][ $key ]['required'] = false;
        }
    }

    return $fields;
}, 9999 );
