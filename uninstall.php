<?php

$data = array(
	'options' => array(),
	'umeta' => array(),
    'postmeta' => array()
);

/** Private Answer Hooks **/

$data['options'][] = array(
    'key' => 'se_PrivatePost_field_label'
);

$data['options'][] = array(
    'key' => 'se_PrivatePost_notice'
);

if ( get_option( 'private_post_uninstall_flush' ) ) {
    $data['postmeta'][] = array(
        'key' => 'private_answer'
    );

    $data['options'][] = array(
        'key' => 'private_post_uninstall_flush'
    );
}

/** End of Private Answer Hooks **/

/** do the thing **/

global $wpdb;

if ( $data['options'] ) {
	foreach ( $data['options'] as $option ) {
		if ( isset( $option['wildcard'] ) ) {
			$wpdb->query(
				$wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $option['key'])
			);
		} else {
			delete_option( $option['key'] );
		}
	}
}

if ( $data['umeta'] ) {
	foreach ( $data['umeta'] as $option ) {
		if ( isset( $option['wildcard'] ) ) {
			$wpdb->query(
				$wpdb->prepare("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $option['key'])
			);
		} else {
            $wpdb->query(
                $wpdb->prepare("DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s", $option['key'])
            );
		}
	}
}

if ( $data['postmeta'] ) {
    foreach ( $data['postmeta'] as $option ) {
        if ( isset( $option['wildcard'] ) ) {
            $wpdb->query(
                $wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $option['key'])
            );
        } else {
            $wpdb->query(
                $wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s", $option['key'])
            );
        }
    }
}

global $wpdb;