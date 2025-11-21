<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ensure we always get a normalized folders array with up to 10 slots.
 */
function dln_get_folders() {
    $folders = get_option('dln_folders', []);

    if (!is_array($folders)) {
        $folders = [];
    }

    for ($i = 1; $i <= 10; $i++) {
        if (!isset($folders[$i]) || !is_array($folders[$i])) {
            $folders[$i] = [
                'key'           => '',
                'label'         => '',
                'folder_id'     => '',
                'cache_minutes' => '',
                'error_message' => '',
                'mime_type'     => '',
            ];
        } else {
            $folders[$i] = array_merge(
                [
                    'key'           => '',
                    'label'         => '',
                    'folder_id'     => '',
                    'cache_minutes' => '',
                    'error_message' => '',
                    'mime_type'     => '',
                ],
                $folders[$i]
            );
        }
    }

    return $folders;
}

/**
 * Allowed MIME types for dropdown.
 */
function dln_get_mime_type_choices() {
    return [
        ''                                  => 'Any type (no filter)',
        'application/pdf'                  => 'PDF (application/pdf)',
        'image/jpeg'                       => 'JPEG image (image/jpeg)',
        'image/png'                        => 'PNG image (image/png)',
        'image/gif'                        => 'GIF image (image/gif)',
        'video/mp4'                        => 'MP4 video (video/mp4)',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                                          => 'Word DOCX',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                                          => 'Excel XLSX',
    ];
}

/**
 * Compute the base URL used in examples.
 * Uses the configured root domain if present, otherwise home_url.
 */
function dln_get_base_url_for_examples() {
    $root_domain = get_option('dln_root_domain', '');
    if ($root_domain) {
        $root_domain = trim($root_domain);
        // Add scheme if missing
        if (!preg_match('#^https?://#i', $root_domain)) {
            $root_domain = 'https://' . $root_domain;
        }
        return rtrim($root_domain, '/') . '/';
    }
    return home_url('/');
}