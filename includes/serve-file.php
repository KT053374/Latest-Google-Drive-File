<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * File serving logic
 *
 * Base endpoint:
 *   https://your-site.com/?latest_newsletter=1
 *
 * Optional query params:
 *   folder_key    (preferred: pick folder by configured key)
 *   folder_id     (raw Drive folder id; tries to match configured folder, else uses generic defaults)
 */

add_action('init', 'dln_serve_latest_newsletter');

function dln_serve_latest_newsletter() {
    if (!isset($_GET['latest_newsletter'])) {
        return;
    }

    $api_key = get_option('dln_api_key', '');
    if (!$api_key) {
        status_header(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Error: No Google API key set. Go to Settings -> Latest Google Drive File.';
        exit;
    }

    $folders = dln_get_folders();

    // Query overrides
    $explicit_folder_id = '';
    $folder_key_param   = '';

    if (!empty($_GET['folder_id'])) {
        $explicit_folder_id = sanitize_text_field(wp_unslash($_GET['folder_id']));
    }
    if (!empty($_GET['folder_key'])) {
        $folder_key_param = sanitize_text_field(wp_unslash($_GET['folder_key']));
    }

    $selected = null;

    // 1) If folder_id provided: try to find matching folder config by ID
    if ($explicit_folder_id !== '') {
        foreach ($folders as $slot) {
            if (!empty($slot['folder_id']) && $slot['folder_id'] === $explicit_folder_id) {
                $selected = $slot;
                break;
            }
        }

        // If not found in config, still allow direct use of folder_id with generic defaults
        if ($selected === null) {
            $selected = [
                'key'           => '',
                'label'         => '',
                'folder_id'     => $explicit_folder_id,
                'cache_minutes' => '',
                'error_message' => '',
                'mime_type'     => '',
            ];
        }
    }
    // 2) Else if folder_key provided: match by key
    elseif ($folder_key_param !== '') {
        $lower_key = strtolower($folder_key_param);
        foreach ($folders as $slot) {
            if (!empty($slot['key']) && strtolower($slot['key']) === $lower_key) {
                $selected = $slot;
                break;
            }
        }

        if ($selected === null) {
            status_header(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Error: Unknown folder_key "' . esc_html($folder_key_param) . '". Check Settings -> Latest Google Drive File.';
            exit;
        }
    }
    // 3) Else: use first configured folder with a Folder ID
    else {
        foreach ($folders as $slot) {
            if (!empty($slot['folder_id'])) {
                $selected = $slot;
                break;
            }
        }

        if ($selected === null) {
            status_header(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Error: No Google Drive Folder ID configured. Set at least one folder in Settings -> Latest Google Drive File.';
            exit;
        }
    }

    $folder_id = isset($selected['folder_id']) ? trim($selected['folder_id']) : '';
    if ($folder_id === '') {
        status_header(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Error: Resolved folder has no Google Drive Folder ID.';
        exit;
    }

    $mime_type = isset($selected['mime_type']) ? trim($selected['mime_type']) : '';

    // Per folder cache and error message
    $cache_minutes = isset($selected['cache_minutes']) && $selected['cache_minutes'] !== ''
        ? (int) $selected['cache_minutes']
        : 5;

    if ($cache_minutes < 0) {
        $cache_minutes = 0;
    }
    $cache_ttl = $cache_minutes * 60;

    $public_error_message = isset($selected['error_message']) && $selected['error_message'] !== ''
        ? $selected['error_message']
        : 'File is not available right now. Please check back later.';

    // Per folder transients
    $folder_hash    = md5($folder_id . '|' . $mime_type);
    $cache_key_id   = 'dln_latest_newsletter_file_id_' . $folder_hash;
    $cache_key_name = 'dln_latest_newsletter_file_name_' . $folder_hash;
    $cache_key_mime = 'dln_latest_newsletter_file_mime_' . $folder_hash;

    $cached_file_id   = null;
    $cached_file_name = null;
    $cached_file_mime = null;

    if ($cache_ttl > 0) {
        $cached_file_id   = get_transient($cache_key_id);
        $cached_file_name = get_transient($cache_key_name);
        $cached_file_mime = get_transient($cache_key_mime);
    }

    try {
        if ($cached_file_id && $cache_ttl > 0) {
            $file_id   = $cached_file_id;
            $file_name = $cached_file_name ?: 'latest-file';
            $file_mime = $cached_file_mime ?: 'application/octet-stream';
        } else {
            // Build Drive query
            if ($mime_type !== '') {
                $query = sprintf(
                    "'%s' in parents and mimeType = '%s' and trashed = false",
                    $folder_id,
                    $mime_type
                );
            } else {
                $query = sprintf(
                    "'%s' in parents and trashed = false",
                    $folder_id
                );
            }

            $list_url = add_query_arg(
                [
                    'q'        => $query,
                    'orderBy'  => 'modifiedTime desc',
                    'pageSize' => 1,
                    'fields'   => 'files(id,name,mimeType,modifiedTime)',
                    'key'      => $api_key,
                ],
                'https://www.googleapis.com/drive/v3/files'
            );

            if (function_exists('error_log')) {
                error_log('[Latest Google Drive File] List URL: ' . $list_url);
            }

            $response = wp_remote_get($list_url, ['timeout' => 15]);

            if (is_wp_error($response)) {
                throw new Exception('HTTP error listing files: ' . $response->get_error_message());
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if (function_exists('error_log')) {
                error_log('[Latest Google Drive File] List response code: ' . $code);
                error_log('[Latest Google Drive File] List response body (truncated): ' . substr($body, 0, 500));
            }

            if ($code < 200 || $code >= 300) {
                throw new Exception('Drive listFiles returned HTTP ' . $code . ' body: ' . $body);
            }

            $data  = json_decode($body, true);
            $files = isset($data['files']) ? $data['files'] : [];

            if (empty($files)) {
                status_header(404);
                header('Content-Type: text/plain; charset=utf-8');
                if (current_user_can('manage_options')) {
                    echo $public_error_message . "\n\n(No files found by query: " . esc_html($query) . ")";
                } else {
                    echo $public_error_message;
                }
                exit;
            }

            $file      = $files[0];
            $file_id   = $file['id'];
            $file_name = $file['name'];
            $file_mime = !empty($file['mimeType']) ? $file['mimeType'] : 'application/octet-stream';

            if (function_exists('error_log')) {
                error_log('[Latest Google Drive File] Using file_id=' . $file_id . ' name=' . $file_name . ' mime=' . $file_mime);
            }

            if ($cache_ttl > 0) {
                set_transient($cache_key_id,   $file_id,   $cache_ttl);
                set_transient($cache_key_name, $file_name, $cache_ttl);
                set_transient($cache_key_mime, $file_mime, $cache_ttl);
            }
        }

        // Download the bytes
        $download_url = add_query_arg(
            [
                'alt' => 'media',
                'key' => $api_key,
            ],
            'https://www.googleapis.com/drive/v3/files/' . rawurlencode($file_id)
        );

        if (function_exists('error_log')) {
            error_log('[Latest Google Drive File] Download URL: ' . $download_url);
        }

        $download_response = wp_remote_get($download_url, [
            'timeout' => 30,
            'stream'  => false,
            'headers' => [],
        ]);

        if (is_wp_error($download_response)) {
            throw new Exception('HTTP error downloading file: ' . $download_response->get_error_message());
        }

        $download_code = wp_remote_retrieve_response_code($download_response);
        $download_body = wp_remote_retrieve_body($download_response);

        if ($download_code < 200 || $download_code >= 300) {
            if (function_exists('error_log')) {
                error_log('[Latest Google Drive File] Download error code: ' . $download_code);
                error_log('[Latest Google Drive File] Download error body: ' . substr($download_body, 0, 500));
            }
            throw new Exception('Drive files.get returned HTTP ' . $download_code . ' body: ' . $download_body);
        }

        $safe_filename = preg_replace('/"/', '', $file_name);

        header('Content-Type: ' . $file_mime);
        header('Content-Disposition: inline; filename="' . $safe_filename . '"');
        header('Cache-Control: no-store, must-revalidate');
        echo $download_body;
        exit;

    } catch (Exception $e) {
        if (function_exists('error_log')) {
            error_log('[Latest Google Drive File] Error: ' . $e->getMessage());
        }

        status_header(500);
        header('Content-Type: text/plain; charset=utf-8');

        if (isset($public_error_message)) {
            if (current_user_can('manage_options')) {
                echo 'Error fetching file: ' . $e->getMessage();
            } else {
                echo $public_error_message;
            }
        } else {
            echo 'Error fetching file.';
        }
        exit;
    }
}