<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin menu and settings registration
 */

add_action('admin_menu', 'dln_add_settings_page');
function dln_add_settings_page() {
    add_options_page(
        'Latest Google Drive File Settings',
        'Latest Google Drive File',
        'manage_options',
        'dln-settings',
        'dln_render_settings_page'
    );
}

add_action('admin_init', 'dln_register_settings');
function dln_register_settings() {
    // global options
    register_setting('dln_settings_group', 'dln_api_key');
    register_setting('dln_settings_group', 'dln_root_domain');
    register_setting('dln_settings_group', 'dln_folder_count'); // how many folder configs to show (1–10)

    // per folder config stored as one option
    register_setting('dln_settings_group', 'dln_folders');
}

/**
 * Enqueue admin CSS and JS only on our settings page.
 */
add_action('admin_enqueue_scripts', 'dln_admin_enqueue_assets');
function dln_admin_enqueue_assets( $hook ) {
    if ( $hook !== 'settings_page_dln-settings' ) {
        return;
    }

    wp_enqueue_style(
        'dln-admin-css',
        LGDF_PLUGIN_URL . 'assets/css/admin.css',
        [],
        LGDF_PLUGIN_VERSION
    );

    wp_enqueue_script(
        'dln-admin-js',
        LGDF_PLUGIN_URL . 'assets/js/admin.js',
        [],
        LGDF_PLUGIN_VERSION,
        true
    );
}

/**
 * Render the settings page.
 */
function dln_render_settings_page() {
    $api_key      = get_option('dln_api_key', '');
    $root_domain  = get_option('dln_root_domain', '');
    $folders      = dln_get_folders();
    $base_url     = dln_get_base_url_for_examples();
    $count_opt    = get_option('dln_folder_count', 3);

    $folder_count = (int) $count_opt;
    if ($folder_count < 1)  $folder_count = 1;
    if ($folder_count > 10) $folder_count = 10;

    $mime_choices = dln_get_mime_type_choices();
    ?>
    <div class="wrap dln-settings-wrap">
        <h1>Latest Google Drive File Settings</h1>

        <div class="dln-settings-intro">
            <p>
                This plugin exposes a simple endpoint that always returns the <strong>most recent file</strong>
                from one of your configured Google Drive folders. You can then embed that URL in PDF Embedder,
                <code>&lt;img&gt;</code>, <code>&lt;video&gt;</code>, or custom code.
            </p>

            <h2>Step 1: Set up a restricted Google Drive API key</h2>
            <ol>
                <li>In the Google Cloud Console, create or select a project.</li>
                <li>Go to <strong>APIs and Services</strong> -> <strong>Library</strong> and enable <strong>Google Drive API</strong> for your project.</li>
                <li>Go to <strong>APIs and Services</strong> -> <strong>Credentials</strong> and create an <strong>API key</strong>.</li>
                <li>Edit the API key and set restrictions:
                    <ol>
                        <li>Under <strong>Application restrictions</strong>, choose <strong>HTTP referrers (web sites)</strong> and add your site domain (for example <code>https://example.com/*</code>).</li>
                        <li>Under <strong>API restrictions</strong>, choose <strong>Restrict key</strong> and select only <strong>Google Drive API</strong>.</li>
                    </ol>
                </li>
                <li>Copy the API key and paste it into the <strong>Google API Key</strong> field below.</li>
            </ol>

            <h2>Step 2: Share the Google Drive folder</h2>
            <ol>
                <li>In Google Drive, right click the folder that will hold your files and choose <strong>Share</strong>.</li>
                <li>Click <strong>General access</strong> and set it to <strong>Anyone with the link</strong>.</li>
                <li>Set the role to <strong>Viewer</strong>.</li>
                <li>Click <strong>Copy link</strong> and use the part after <code>/folders/</code> as the <strong>Folder ID</strong> in the settings below.</li>
                <li>Upload files into that folder. Files inherit the folder sharing so the plugin can read them through the Drive API.</li>
            </ol>
        </div>

        <form method="post" action="options.php">
            <?php settings_fields('dln_settings_group'); ?>
            <?php do_settings_sections('dln_settings_group'); ?>

            <div class="dln-settings-section">
                <h2>Global Settings</h2>
                <p class="description">
                    Configure your Google API key, site root domain, and how many folder slots you want visible in the UI.
                </p>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Google API Key</th>
                        <td>
                            <input type="text"
                                   name="dln_api_key"
                                   value="<?php echo esc_attr($api_key); ?>"
                                   style="width: 400px;">
                            <p class="description">
                                API key from Google Cloud with Drive API enabled, restricted to your domain and Drive API only.
                                The Drive folders and files must be shared as "Anyone with the link - Viewer".
                            </p>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">Root domain</th>
                        <td>
                            <input type="text"
                                   name="dln_root_domain"
                                   value="<?php echo esc_attr($root_domain); ?>"
                                   style="width: 400px;"
                                   placeholder="https://alp166sc.com">
                            <p class="description">
                                Optional. Used to build example URLs and shortcodes. For example <code>https://alp166sc.com</code>.
                                If left empty, the WordPress site URL is used instead.
                            </p>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">Folder slots to show</th>
                        <td>
                            <select name="dln_folder_count">
                                <?php for ($i = 1; $i <= 10; $i++) : ?>
                                    <option value="<?php echo (int)$i; ?>" <?php selected($folder_count, $i); ?>>
                                        <?php echo (int)$i; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <p class="description">
                                Controls how many folder configuration cards are visible below (max 10). Changing this will
                                show or hide cards immediately in the UI. You still need to Save to persist the new count.
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="dln-divider"></div>

            <div class="dln-settings-section">
                <h2>Google Drive Folders</h2>
                <p class="description">
                    Each folder is identified by a <strong>Folder key</strong>, and can have its own MIME filter,
                    cache duration, and public error message.<br>
                    Use <code>folder_key</code> in the URL or shortcode to target a specific folder.
                </p>

                <div class="dln-folders-grid">
                    <?php
                    for ($i = 1; $i <= 10; $i++) {
                        $folder = $folders[$i];

                        $key           = isset($folder['key']) ? $folder['key'] : '';
                        $label         = isset($folder['label']) ? $folder['label'] : '';
                        $folder_id     = isset($folder['folder_id']) ? $folder['folder_id'] : '';
                        $cache_minutes = isset($folder['cache_minutes']) ? $folder['cache_minutes'] : '';
                        $error_message = isset($folder['error_message']) ? $folder['error_message'] : '';
                        $mime_type     = isset($folder['mime_type']) ? $folder['mime_type'] : '';

                        if ($cache_minutes === '') {
                            $cache_minutes = 5;
                        }
                        if ($error_message === '') {
                            $error_message = 'File is not available right now. Please check back later.';
                        }

                        // Build examples based on folder key and base URL
                        $example_url       = '';
                        $example_shortcode = '';

                        if ($key !== '') {
                            $example_url = add_query_arg(
                                [
                                    'latest_newsletter' => '1',
                                    'folder_key'        => $key,
                                ],
                                $base_url
                            );

                            $example_shortcode = '[pdf-embedder url="' . esc_url($example_url) . '"]';
                        }

                        $initial_style = ($i > $folder_count) ? 'display:none;' : '';
                        ?>
                        <div class="dln-folder-card" data-slot="<?php echo (int)$i; ?>" style="<?php echo esc_attr($initial_style); ?>">
                            <div class="dln-folder-card-header">
                                <div class="dln-folder-card-header-title">
                                    Folder <?php echo (int)$i; ?>
                                </div>
                                <?php if ($key !== '') : ?>
                                    <span class="dln-folder-card-header-badge">
                                        key: <?php echo esc_html($key); ?>
                                    </span>
                                <?php else : ?>
                                    <span class="dln-folder-card-header-badge dln-small-label">
                                        Not configured
                                    </span>
                                <?php endif; ?>
                            </div>

                            <table class="dln-folder-inner-table">
                                <tr>
                                    <th>Folder key</th>
                                    <td>
                                        <input type="text"
                                               name="dln_folders[<?php echo (int)$i; ?>][key]"
                                               value="<?php echo esc_attr($key); ?>">
                                        <div class="dln-field-help">
                                            Short identifier for this folder (no spaces), for example <code>main</code> or <code>events</code>.
                                            Used as <code>folder_key</code> in the URL.
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <th>Label</th>
                                    <td>
                                        <input type="text"
                                               name="dln_folders[<?php echo (int)$i; ?>][label]"
                                               value="<?php echo esc_attr($label); ?>">
                                        <div class="dln-field-help">
                                            Human friendly label to remind you what this folder is for. Not used in URLs.
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <th>Folder ID</th>
                                    <td>
                                        <input type="text"
                                               name="dln_folders[<?php echo (int)$i; ?>][folder_id]"
                                               value="<?php echo esc_attr($folder_id); ?>">
                                        <div class="dln-field-help">
                                            The string after <code>/folders/</code> in the Google Drive URL.
                                            Can be My Drive or Shared Drive, as long as it and the files are
                                            shared "Anyone with the link - Viewer".
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <th>MIME type</th>
                                    <td>
                                        <select name="dln_folders[<?php echo (int)$i; ?>][mime_type]">
                                            <?php foreach ($mime_choices as $value => $label_text) : ?>
                                                <option value="<?php echo esc_attr($value); ?>" <?php selected($mime_type, $value); ?>>
                                                    <?php echo esc_html($label_text); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="dln-field-help">
                                            Filters by MIME type when selecting the latest file. Choose "Any type" to allow all files.
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <th>Cache (minutes)</th>
                                    <td>
                                        <input type="number"
                                               name="dln_folders[<?php echo (int)$i; ?>][cache_minutes]"
                                               value="<?php echo esc_attr($cache_minutes); ?>"
                                               min="0">
                                        <div class="dln-field-help">
                                            How long to cache the ID of the latest file for this folder. Set to 0 to disable caching.
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <th>Error message</th>
                                    <td>
                                        <input type="text"
                                               name="dln_folders[<?php echo (int)$i; ?>][error_message]"
                                               value="<?php echo esc_attr($error_message); ?>">
                                        <div class="dln-field-help">
                                            Shown to normal visitors when no file is found or an error occurs for this folder.
                                            Admins may see more detailed error information.
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <?php if ($example_url && $example_shortcode) : ?>
                                <div class="dln-example-block">
                                    <span class="dln-example-label">Example URL:</span>
                                    <div class="dln-example-code"><?php echo esc_html($example_url); ?></div>

                                    <span class="dln-example-label" style="margin-top:4px;">Example shortcode (PDF Embedder):</span>
                                    <div class="dln-example-code"><?php echo esc_html($example_shortcode); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}