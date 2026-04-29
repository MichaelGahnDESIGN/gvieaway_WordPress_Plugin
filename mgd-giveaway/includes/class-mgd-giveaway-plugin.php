<?php

if (!defined('ABSPATH')) {
    exit;
}

class MGD_Giveaway_Plugin
{
    private static $instance = null;
    private $post_type = 'mgd_giveaway_form';
    private $submission_table;
    private $log_table;
    private $max_csv_upload_bytes = 2097152;
    private $max_csv_import_rows = 5000;
    private $spam_min_seconds = 0;
    private $spam_max_seconds = 0;
    private $download_token_max_age = 2592000;
    private $confirm_token_max_age = 1209600;

    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function __construct()
    {
        global $wpdb;
        $this->submission_table = $wpdb->prefix . 'mgd_giveaway_submissions';
        $this->log_table = $wpdb->prefix . 'mgd_giveaway_logs';

        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'maybe_upgrade'));
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('template_redirect', array($this, 'handle_masked_download'));
        add_action('template_redirect', array($this, 'handle_double_opt_in_confirm'));
        add_shortcode('mgd_giveaway', array($this, 'render_shortcode'));

        add_action('admin_post_mgd_giveaway_save_form', array($this, 'handle_save_form'));
        add_action('admin_post_mgd_giveaway_delete_form', array($this, 'handle_delete_form'));
        add_action('admin_post_mgd_giveaway_duplicate_form', array($this, 'handle_duplicate_form'));
        add_action('admin_post_mgd_giveaway_save_settings', array($this, 'handle_save_settings'));
        add_action('admin_post_mgd_giveaway_export_mail_list', array($this, 'handle_export_mail_list'));
        add_action('admin_post_mgd_giveaway_import_mail_list', array($this, 'handle_import_mail_list'));
        add_action('admin_post_mgd_giveaway_export_contact', array($this, 'handle_export_contact'));
        add_action('admin_post_mgd_giveaway_delete_contact', array($this, 'handle_delete_contact'));
        add_action('admin_post_mgd_giveaway_export_logs', array($this, 'handle_export_logs'));
        add_action('admin_post_mgd_giveaway_clear_logs', array($this, 'handle_clear_logs'));
        add_action('admin_post_nopriv_mgd_giveaway_submit', array($this, 'handle_frontend_submit'));
        add_action('admin_post_mgd_giveaway_submit', array($this, 'handle_frontend_submit'));
    }

    public static function activate()
    {
        self::create_tables();
        update_option('mgd_giveaway_version', MGD_GIVEAWAY_VERSION, false);
        flush_rewrite_rules();
    }

    public function maybe_upgrade()
    {
        if (get_option('mgd_giveaway_version') !== MGD_GIVEAWAY_VERSION) {
            self::create_tables();
            update_option('mgd_giveaway_version', MGD_GIVEAWAY_VERSION, false);
        }
    }

    public static function uninstall()
    {
        $forms = get_posts(array(
            'post_type' => 'mgd_giveaway_form',
            'post_status' => 'any',
            'numberposts' => -1,
            'fields' => 'ids',
        ));

        foreach ($forms as $form_id) {
            wp_delete_post((int) $form_id, true);
        }

        delete_option('mgd_giveaway_settings');
        delete_option('mgd_giveaway_version');

        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mgd_giveaway_submissions");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mgd_giveaway_logs");
    }

    private static function create_tables()
    {
        global $wpdb;
        $submission_table = $wpdb->prefix . 'mgd_giveaway_submissions';
        $log_table = $wpdb->prefix . 'mgd_giveaway_logs';
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $submission_sql = "CREATE TABLE {$submission_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            form_id bigint(20) unsigned NOT NULL,
            email varchar(190) NOT NULL DEFAULT '',
            data longtext NULL,
            ip_hash varchar(64) NOT NULL DEFAULT '',
            user_agent varchar(255) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'confirmed',
            confirmed_at datetime NULL,
            download_count int(11) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY form_id (form_id),
            KEY email (email),
            KEY status (status)
        ) {$charset_collate};";

        $log_sql = "CREATE TABLE {$log_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            level varchar(20) NOT NULL DEFAULT 'info',
            event varchar(100) NOT NULL DEFAULT '',
            message text NULL,
            context longtext NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY level (level),
            KEY event (event),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta($submission_sql);
        dbDelta($log_sql);
    }

    public function register_post_type()
    {
        register_post_type($this->post_type, array(
            'labels' => array(
                'name' => 'MGD Giveaway Formulare',
                'singular_name' => 'MGD Giveaway Formular',
            ),
            'public' => false,
            'show_ui' => false,
            'supports' => array('title'),
            'capability_type' => 'post',
        ));
    }

    public function register_admin_menu()
    {
        add_menu_page(
            'MGD Giveaway',
            'MGD Giveaway',
            'manage_options',
            'mgd-giveaway',
            array($this, 'render_dashboard_page'),
            'dashicons-download',
            56
        );

        add_submenu_page('mgd-giveaway', 'Dashboard', 'Dashboard', 'manage_options', 'mgd-giveaway', array($this, 'render_dashboard_page'));
        add_submenu_page('mgd-giveaway', 'Neues Formular', 'Neues Formular', 'manage_options', 'mgd-giveaway-form', array($this, 'render_form_editor_page'));
        add_submenu_page('mgd-giveaway', 'Mail-Liste', 'Mail-Liste', 'manage_options', 'mgd-giveaway-mail-list', array($this, 'render_mail_list_page'));
        add_submenu_page('mgd-giveaway', 'Logs', 'Logs', 'manage_options', 'mgd-giveaway-logs', array($this, 'render_logs_page'));
        add_submenu_page('mgd-giveaway', 'E-Mail Einstellungen', 'E-Mail', 'manage_options', 'mgd-giveaway-settings', array($this, 'render_settings_page'));
        add_submenu_page('mgd-giveaway', 'Credits', 'Credits', 'manage_options', 'mgd-giveaway-credits', array($this, 'render_credits_page'));
    }

    public function enqueue_admin_assets($hook)
    {
        if (false === strpos($hook, 'mgd-giveaway')) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('mgd-giveaway-admin', MGD_GIVEAWAY_URL . 'assets/admin.css', array(), MGD_GIVEAWAY_VERSION);
        wp_enqueue_script('mgd-giveaway-admin', MGD_GIVEAWAY_URL . 'assets/admin.js', array('jquery'), MGD_GIVEAWAY_VERSION, true);
    }

    public function enqueue_frontend_assets()
    {
        wp_enqueue_style('mgd-giveaway-frontend', MGD_GIVEAWAY_URL . 'assets/frontend.css', array(), MGD_GIVEAWAY_VERSION);
        wp_enqueue_script('mgd-giveaway-frontend', MGD_GIVEAWAY_URL . 'assets/frontend.js', array(), MGD_GIVEAWAY_VERSION, true);
    }

    private function get_form_config($form_id)
    {
        $default = array(
            'fields' => array(
                array('label' => 'E-Mail', 'name' => 'email', 'type' => 'email', 'required' => true),
                array('label' => 'Datenschutz', 'name' => 'privacy', 'type' => 'privacy', 'required' => true, 'text' => 'Ich habe die Datenschutzhinweise gelesen und bin mit der Verarbeitung meiner Angaben einverstanden.', 'privacy_url' => ''),
            ),
            'download_attachment_id' => 0,
            'protected_file' => array(),
            'submit_button_label' => 'Gratis-Ratgeber anfordern',
            'button_label' => 'Jetzt herunterladen',
            'success_message' => 'Danke für deine Anmeldung. Der Download ist jetzt verfügbar.',
            'email_subject' => 'Dein Download',
            'email_body' => "Hallo,\n\nvielen Dank für deine Anmeldung. Dein Download ist jetzt verfügbar:\n{download_url}",
            'send_email' => true,
            'double_opt_in' => false,
            'confirm_subject' => 'Bitte bestätige deine Anmeldung',
            'confirm_body' => "Hallo,\n\nbitte bestätige deine Anmeldung über diesen Link:\n{confirm_url}",
            'confirm_message' => 'Bitte bestätige deine Anmeldung über den Link in deiner E-Mail. Danach ist der Download verfügbar.',
            'style' => array(
                'max_width' => '560',
                'button_bg' => '#151515',
                'button_text' => '#ffffff',
                'field_border' => '#bbbbbb',
                'radius' => '6',
            ),
        );

        $raw = get_post_meta($form_id, '_mgd_giveaway_config', true);
        $config = is_array($raw) ? $raw : array();

        $config = wp_parse_args($config, $default);
        $config['style'] = wp_parse_args(is_array($config['style']) ? $config['style'] : array(), $default['style']);
        $config['protected_file'] = is_array($config['protected_file']) ? $config['protected_file'] : array();

        return $config;
    }

    private function get_settings()
    {
        $defaults = array(
            'mail_method' => 'php',
            'from_name' => get_bloginfo('name'),
            'from_email' => get_option('admin_email'),
            'notification_recipient' => get_option('admin_email'),
            'smtp_host' => '',
            'smtp_port' => '587',
            'smtp_encryption' => 'tls',
            'smtp_username' => '',
            'smtp_password' => '',
        );

        $settings = get_option('mgd_giveaway_settings', array());

        return wp_parse_args(is_array($settings) ? $settings : array(), $defaults);
    }

    public function render_dashboard_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Keine Berechtigung.', 'mgd-giveaway'));
        }

        $forms = get_posts(array(
            'post_type' => $this->post_type,
            'post_status' => 'any',
            'numberposts' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ));

        echo '<div class="wrap mgd-admin"><h1>MGD Giveaway</h1>';
        $this->render_notices();
        echo '<div class="mgd-stats">';
        echo '<div><strong>' . esc_html(count($forms)) . '</strong><span>Formulare</span></div>';
        echo '<div><strong>' . esc_html($this->count_submissions()) . '</strong><span>Anmeldungen</span></div>';
        echo '</div>';
        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=mgd-giveaway-form')) . '">Neues Formular erstellen</a></p>';
        echo '<table class="widefat striped"><thead><tr><th>Name</th><th>Shortcode</th><th>Anmeldungen</th><th>Aktionen</th></tr></thead><tbody>';

        if (!$forms) {
            echo '<tr><td colspan="4">Noch keine Formulare vorhanden.</td></tr>';
        }

        foreach ($forms as $form) {
            $edit_url = admin_url('admin.php?page=mgd-giveaway-form&form_id=' . (int) $form->ID);
            $duplicate_url = wp_nonce_url(admin_url('admin-post.php?action=mgd_giveaway_duplicate_form&form_id=' . (int) $form->ID), 'mgd_giveaway_duplicate_' . (int) $form->ID);
            $delete_url = wp_nonce_url(admin_url('admin-post.php?action=mgd_giveaway_delete_form&form_id=' . (int) $form->ID), 'mgd_giveaway_delete_' . (int) $form->ID);
            echo '<tr>';
            echo '<td><strong>' . esc_html(get_the_title($form)) . '</strong></td>';
            echo '<td><code>[mgd_giveaway id="' . esc_html((string) $form->ID) . '"]</code></td>';
            echo '<td>' . esc_html($this->count_submissions((int) $form->ID)) . '</td>';
            echo '<td><a class="button" href="' . esc_url($edit_url) . '">Bearbeiten</a> ';
            echo '<a class="button" href="' . esc_url($duplicate_url) . '">Duplizieren</a> ';
            echo '<a class="button button-link-delete" href="' . esc_url($delete_url) . '" onclick="return confirm(\'Formular wirklich löschen?\');">Löschen</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }

    public function render_form_editor_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Keine Berechtigung.', 'mgd-giveaway'));
        }

        $form_id = isset($_GET['form_id']) ? absint($_GET['form_id']) : 0;
        $post = $form_id ? get_post($form_id) : null;
        $config = $form_id ? $this->get_form_config($form_id) : $this->get_form_config(0);
        $attachment_id = (int) $config['download_attachment_id'];
        $attachment_title = $attachment_id ? get_the_title($attachment_id) : '';
        $active_tab = isset($_GET['mgd_tab']) ? sanitize_key(wp_unslash($_GET['mgd_tab'])) : 'fields';
        $allowed_tabs = array('fields', 'settings', 'download', 'email', 'design', 'preview');
        if (!in_array($active_tab, $allowed_tabs, true)) {
            $active_tab = 'fields';
        }

        echo '<div class="wrap mgd-admin mgd-editor-page"><div class="mgd-editor-header"><div><h1>' . esc_html($form_id ? 'Formular bearbeiten' : 'Neues Formular') . '</h1><p>Bearbeite Felder, Grundeinstellungen, Download, E-Mail und Vorschau in getrennten Bereichen.</p></div><div class="mgd-editor-actions"><button class="button button-primary mgd-editor-save" type="submit" form="mgd-giveaway-editor-form">Speichern</button><a class="button" href="' . esc_url(admin_url('admin.php?page=mgd-giveaway')) . '">Zurück</a></div></div>';
        $this->render_notices();
        echo '<form id="mgd-giveaway-editor-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '" novalidate>';
        wp_nonce_field('mgd_giveaway_save_form');
        echo '<input type="hidden" name="action" value="mgd_giveaway_save_form">';
        echo '<input type="hidden" name="form_id" value="' . esc_attr((string) $form_id) . '">';
        echo '<input type="hidden" id="mgd_active_tab" name="mgd_active_tab" value="' . esc_attr($active_tab) . '">';

        echo '<nav class="mgd-editor-tabs" aria-label="Editor Bereiche">';
        echo '<button type="button" class="mgd-tab ' . esc_attr('fields' === $active_tab ? 'is-active' : '') . '" data-tab="fields"><span class="dashicons dashicons-feedback"></span>Felder</button>';
        echo '<button type="button" class="mgd-tab ' . esc_attr('settings' === $active_tab ? 'is-active' : '') . '" data-tab="settings"><span class="dashicons dashicons-admin-generic"></span>Formular</button>';
        echo '<button type="button" class="mgd-tab ' . esc_attr('download' === $active_tab ? 'is-active' : '') . '" data-tab="download"><span class="dashicons dashicons-download"></span>Download</button>';
        echo '<button type="button" class="mgd-tab ' . esc_attr('email' === $active_tab ? 'is-active' : '') . '" data-tab="email"><span class="dashicons dashicons-email-alt"></span>E-Mail</button>';
        echo '<button type="button" class="mgd-tab ' . esc_attr('design' === $active_tab ? 'is-active' : '') . '" data-tab="design"><span class="dashicons dashicons-admin-appearance"></span>Design</button>';
        echo '<button type="button" class="mgd-tab ' . esc_attr('preview' === $active_tab ? 'is-active' : '') . '" data-tab="preview"><span class="dashicons dashicons-visibility"></span>Vorschau</button>';
        echo '</nav>';

        echo '<div class="mgd-tab-panel ' . esc_attr('fields' === $active_tab ? 'is-active' : '') . '" data-panel="fields">';
        echo '<section class="mgd-builder-shell" aria-label="Formular Builder">';
        echo '<aside class="mgd-builder-palette"><h2>Elemente</h2><p>Per Klick oder Drag & Drop in das Formular ziehen.</p><div class="mgd-element-palette" aria-label="Formular Elemente">';
        foreach ($this->get_field_types() as $type_key => $type_label) {
            echo '<button type="button" class="mgd-add-field" draggable="true" data-type="' . esc_attr($type_key) . '"><span class="dashicons ' . esc_attr($this->get_field_type_icon($type_key)) . '"></span><strong>' . esc_html($type_label) . '</strong><small>' . esc_html($this->get_field_type_description($type_key)) . '</small></button>';
        }
        echo '</div></aside>';
        echo '<main class="mgd-builder-canvas"><div class="mgd-canvas-head"><div><h2>Formular</h2><p>Felder anklicken, um rechts die Einstellungen zu bearbeiten.</p></div><span class="mgd-builder-count">' . esc_html((string) count($config['fields'])) . ' Felder</span></div>';
        echo '<div id="mgd-fields" class="mgd-fields-canvas" data-next-index="' . esc_attr((string) count($config['fields'])) . '">';
        echo '<div class="mgd-empty-state"><span class="dashicons dashicons-plus-alt2"></span><strong>Element hier ablegen</strong><small>Wähle links ein Feld oder ziehe es in diese Fläche.</small></div>';
        foreach ($config['fields'] as $index => $field) {
            $this->render_field_editor_row($index, $field);
        }
        echo '</div></main>';
        echo '<aside class="mgd-field-inspector"><h2>Einstellungen</h2><div id="mgd-inspector-empty"><span class="dashicons dashicons-edit-page"></span><strong>Kein Feld ausgewählt</strong><small>Klicke ein Feld im Formular an.</small></div><div id="mgd-inspector-content"></div></aside>';
        echo '</section></div>';

        echo '<div class="mgd-tab-panel ' . esc_attr('settings' === $active_tab ? 'is-active' : '') . '" data-panel="settings"><section class="mgd-panel mgd-editor-card"><h2>Formular</h2><p class="description">Grunddaten und Texte, die nach der Anmeldung angezeigt werden.</p>';
        echo '<label class="mgd-field"><span>Name</span><input type="text" name="form_title" value="' . esc_attr($post ? $post->post_title : '') . '"></label>';
        echo '<label class="mgd-field"><span>Absenden Button Text</span><input type="text" name="submit_button_label" value="' . esc_attr($config['submit_button_label']) . '"><small>Text für den Button, der die Formulardaten absendet.</small></label>';
        echo '<label class="mgd-field"><span>Erfolgsmeldung</span><textarea name="success_message" rows="4">' . esc_textarea($config['success_message']) . '</textarea></label>';
        echo '</section></div>';

        echo '<div class="mgd-tab-panel ' . esc_attr('download' === $active_tab ? 'is-active' : '') . '" data-panel="download"><section class="mgd-panel mgd-editor-card"><h2>Download</h2><p class="description">Die Datei wird nach erfolgreicher Anmeldung als Button auf der Seite angezeigt. Beim Speichern wird eine geschützte Kopie für die Plugin-Auslieferung angelegt.</p>';
        echo '<label class="mgd-field"><span>Download Button Text</span><input type="text" name="button_label" value="' . esc_attr($config['button_label']) . '"><small>Text für den Button, der nach erfolgreicher Anmeldung den Download startet.</small></label>';
        echo '<label class="mgd-field"><span>Datei aus Mediathek</span><span class="mgd-media-row"><input type="hidden" id="download_attachment_id" name="download_attachment_id" value="' . esc_attr((string) $attachment_id) . '"><input type="text" id="download_attachment_title" value="' . esc_attr($attachment_title) . '" readonly><button type="button" class="button mgd-select-media">Auswählen</button></span></label>';
        if (!empty($config['protected_file']['path'])) {
            echo '<p class="description">Geschützte Kopie aktiv: ' . esc_html(isset($config['protected_file']['name']) ? $config['protected_file']['name'] : basename($config['protected_file']['path'])) . '</p>';
        }
        echo '</section></div>';

        echo '<div class="mgd-tab-panel ' . esc_attr('email' === $active_tab ? 'is-active' : '') . '" data-panel="email"><section class="mgd-panel mgd-editor-card"><h2>E-Mail</h2><p class="description">Optionaler Versand des Download-Links an die eingetragene Adresse.</p>';
        echo '<label class="mgd-check-row"><input type="checkbox" name="double_opt_in" value="1" ' . checked(!empty($config['double_opt_in']), true, false) . '> Double-Opt-In aktivieren</label>';
        echo '<label class="mgd-field"><span>Hinweis nach Anmeldung</span><textarea name="confirm_message" rows="3">' . esc_textarea($config['confirm_message']) . '</textarea><small>Wird angezeigt, bevor der Bestätigungslink angeklickt wurde.</small></label>';
        echo '<label class="mgd-field"><span>Bestätigungs-E-Mail Betreff</span><input type="text" name="confirm_subject" value="' . esc_attr($config['confirm_subject']) . '"></label>';
        echo '<label class="mgd-field"><span>Bestätigungs-E-Mail Text</span><textarea name="confirm_body" rows="6">' . esc_textarea($config['confirm_body']) . '</textarea><small>Platzhalter: {confirm_url}</small></label>';
        echo '<hr>';
        echo '<label class="mgd-check-row"><input type="checkbox" name="send_email" value="1" ' . checked(!empty($config['send_email']), true, false) . '> Download-Link auch per E-Mail senden</label>';
        echo '<label class="mgd-field"><span>E-Mail Betreff</span><input type="text" name="email_subject" value="' . esc_attr($config['email_subject']) . '"></label>';
        echo '<label class="mgd-field"><span>E-Mail Text</span><textarea name="email_body" rows="8">' . esc_textarea($config['email_body']) . '</textarea><small>Platzhalter: {download_url}</small></label>';
        echo '</section></div>';

        echo '<div class="mgd-tab-panel ' . esc_attr('design' === $active_tab ? 'is-active' : '') . '" data-panel="design"><section class="mgd-panel mgd-editor-card"><h2>Design</h2><p class="description">Einfache Formularoptik für die Ausgabe per Shortcode.</p>';
        echo '<label class="mgd-field"><span>Maximale Breite in Pixel</span><input type="number" min="260" max="1200" name="style[max_width]" value="' . esc_attr((string) $config['style']['max_width']) . '"></label>';
        echo '<label class="mgd-field"><span>Button Hintergrund</span><input type="color" name="style[button_bg]" value="' . esc_attr($config['style']['button_bg']) . '"></label>';
        echo '<label class="mgd-field"><span>Button Textfarbe</span><input type="color" name="style[button_text]" value="' . esc_attr($config['style']['button_text']) . '"></label>';
        echo '<label class="mgd-field"><span>Feld-Rahmenfarbe</span><input type="color" name="style[field_border]" value="' . esc_attr($config['style']['field_border']) . '"></label>';
        echo '<label class="mgd-field"><span>Eckenradius in Pixel</span><input type="number" min="0" max="32" name="style[radius]" value="' . esc_attr((string) $config['style']['radius']) . '"></label>';
        echo '</section></div>';

        echo '<div class="mgd-tab-panel ' . esc_attr('preview' === $active_tab ? 'is-active' : '') . '" data-panel="preview"><section class="mgd-panel mgd-editor-card"><h2>Vorschau</h2>';
        if ($form_id) {
            $this->render_editor_preview($config);
        } else {
            echo '<p class="description">Speichere das Formular einmal, damit die Vorschau mit Shortcode angezeigt werden kann.</p>';
        }
        echo '</section></div>';

        echo '<p class="mgd-editor-bottom-actions"><button class="button button-primary mgd-editor-save" type="submit">Speichern</button> <a class="button" href="' . esc_url(admin_url('admin.php?page=mgd-giveaway')) . '">Zurück</a></p>';
        echo '</form>';

        echo '</div>';
    }

    private function render_editor_preview($config)
    {
        echo '<div class="mgd-editor-preview" style="' . esc_attr($this->get_frontend_style_vars($config)) . '">';
        echo '<div class="mgd-giveaway-form">';
        foreach ($config['fields'] as $field) {
            $this->render_editor_preview_field($field);
        }
        echo '<button type="button" disabled>' . esc_html($config['submit_button_label']) . '</button>';
        echo '</div>';
        echo '<p class="description">Diese Vorschau ist statisch und sendet keine Daten ab.</p>';
        echo '</div>';
    }

    private function render_editor_preview_field($field)
    {
        $label = isset($field['label']) ? $field['label'] : '';
        $type = isset($field['type']) ? $field['type'] : 'text';
        $text = isset($field['text']) ? $field['text'] : '';

        echo '<label class="mgd-giveaway-field"><span>' . esc_html($label) . '</span>';
        if ('textarea' === $type) {
            echo '<textarea disabled></textarea>';
        } elseif ('privacy' === $type) {
            $notice = $text ? $text : $label;
            echo '<span class="mgd-giveaway-checkbox"><input type="checkbox" disabled> <span>' . esc_html($notice) . ' <button type="button" class="mgd-privacy-link" disabled>zur Datenschutzerklärung</button></span></span>';
        } elseif ('checkbox' === $type) {
            echo '<input type="checkbox" disabled>';
        } else {
            echo '<input type="' . esc_attr($type) . '" disabled>';
        }
        echo '</label>';
    }

    private function render_field_editor_row($index, $field)
    {
        $types = $this->get_field_types();
        $label = isset($field['label']) ? $field['label'] : '';
        $name = isset($field['name']) ? $field['name'] : '';
        $type = isset($field['type']) ? $field['type'] : 'text';
        $type_label = isset($types[$type]) ? $types[$type] : $types['text'];
        $required = !empty($field['required']);
        $text = isset($field['text']) ? $field['text'] : '';
        $privacy_url = isset($field['privacy_url']) ? $field['privacy_url'] : '';

        echo '<div class="mgd-field-row" draggable="true" data-type="' . esc_attr($type) . '">';
        echo '<button type="button" class="mgd-drag-handle" aria-label="Element verschieben"><span class="dashicons dashicons-move"></span></button>';
        echo '<button type="button" class="mgd-field-preview" aria-label="Feld bearbeiten">';
        echo '<span class="mgd-field-preview-icon dashicons ' . esc_attr($this->get_field_type_icon($type)) . '"></span><span class="mgd-field-preview-body"><strong>' . esc_html($label ? $label : $type_label) . '</strong><small>' . esc_html($type_label . ($required ? ' - Pflichtfeld' : '')) . '</small><span class="mgd-preview-control"></span></span>';
        echo '</button>';
        echo '<div class="mgd-field-config-slot">';
        echo '<div class="mgd-field-config">';
        echo '<div class="mgd-config-head"><strong>Feld bearbeiten</strong><button type="button" class="button-link-delete mgd-remove-field">Entfernen</button></div>';
        echo '<label><span>Label</span><input class="mgd-field-label-input" type="text" name="fields[' . esc_attr((string) $index) . '][label]" value="' . esc_attr($label) . '" placeholder="Label"></label>';
        echo '<label><span>Feldname</span><input type="text" name="fields[' . esc_attr((string) $index) . '][name]" value="' . esc_attr($name) . '" placeholder="feldname"></label>';
        echo '<label><span>Typ</span><select class="mgd-field-type" name="fields[' . esc_attr((string) $index) . '][type]">';
        foreach ($types as $value => $title) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($type, $value, false) . '>' . esc_html($title) . '</option>';
        }
        echo '</select></label>';
        echo '<label class="mgd-required-toggle"><input type="checkbox" name="fields[' . esc_attr((string) $index) . '][required]" value="1" ' . checked($required, true, false) . '> Pflichtfeld</label>';
        echo '<label class="mgd-field-text"><span>Hinweistext</span><textarea name="fields[' . esc_attr((string) $index) . '][text]" rows="3" placeholder="Optionaler Text, besonders für Datenschutz-Hinweise">' . esc_textarea($text) . '</textarea></label>';
        echo '<label class="mgd-privacy-url-field"><span>URL zur Datenschutzerklärung</span><input type="url" name="fields[' . esc_attr((string) $index) . '][privacy_url]" value="' . esc_attr($privacy_url) . '" placeholder="https://example.de/datenschutzerklaerung/"><small>Nur für Datenschutz-Felder. Interne WordPress-Seiten werden direkt im Popup angezeigt.</small></label>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    private function get_field_types()
    {
        return array(
            'text' => 'Text',
            'email' => 'E-Mail',
            'number' => 'Zahl',
            'date' => 'Datum',
            'checkbox' => 'Checkbox',
            'textarea' => 'Mehrzeilig',
            'privacy' => 'Datenschutz',
        );
    }

    private function get_field_type_icon($type)
    {
        $icons = array(
            'text' => 'dashicons-editor-textcolor',
            'email' => 'dashicons-email-alt',
            'number' => 'dashicons-editor-ol',
            'date' => 'dashicons-calendar-alt',
            'checkbox' => 'dashicons-yes-alt',
            'textarea' => 'dashicons-text-page',
            'privacy' => 'dashicons-shield',
        );

        return isset($icons[$type]) ? $icons[$type] : 'dashicons-feedback';
    }

    private function get_field_type_description($type)
    {
        $descriptions = array(
            'text' => 'Einzeilige Eingabe',
            'email' => 'E-Mail Adresse',
            'number' => 'Numerischer Wert',
            'date' => 'Datumsauswahl',
            'checkbox' => 'Ja/Nein Auswahl',
            'textarea' => 'Langer Text',
            'privacy' => 'DSGVO Hinweis',
        );

        return isset($descriptions[$type]) ? $descriptions[$type] : 'Formularfeld';
    }

    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Keine Berechtigung.', 'mgd-giveaway'));
        }

        $settings = $this->get_settings();
        echo '<div class="wrap mgd-admin"><h1>E-Mail Einstellungen</h1>';
        $this->render_notices();
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="mgd-panel">';
        wp_nonce_field('mgd_giveaway_save_settings');
        echo '<input type="hidden" name="action" value="mgd_giveaway_save_settings">';
        echo '<label class="mgd-field"><span>Versandart</span><select name="mail_method"><option value="php" ' . selected($settings['mail_method'], 'php', false) . '>PHP mail / wp_mail</option><option value="smtp" ' . selected($settings['mail_method'], 'smtp', false) . '>SMTP</option></select></label>';
        echo '<label class="mgd-field"><span>Absender Name</span><input type="text" name="from_name" value="' . esc_attr($settings['from_name']) . '"></label>';
        echo '<label class="mgd-field"><span>Absender E-Mail</span><input type="email" name="from_email" value="' . esc_attr($settings['from_email']) . '"></label>';
        echo '<label class="mgd-field"><span>Empfänger für neue Anmeldungen</span><input type="email" name="notification_recipient" value="' . esc_attr($settings['notification_recipient']) . '"><small>An diese Adresse wird jede neue Formularanmeldung gesendet.</small></label>';
        echo '<label class="mgd-field"><span>SMTP Host</span><input type="text" name="smtp_host" value="' . esc_attr($settings['smtp_host']) . '"></label>';
        echo '<label class="mgd-field"><span>SMTP Port</span><input type="number" name="smtp_port" value="' . esc_attr($settings['smtp_port']) . '"></label>';
        echo '<label class="mgd-field"><span>Verschlüsselung</span><select name="smtp_encryption"><option value="none" ' . selected($settings['smtp_encryption'], 'none', false) . '>Keine</option><option value="tls" ' . selected($settings['smtp_encryption'], 'tls', false) . '>TLS</option><option value="ssl" ' . selected($settings['smtp_encryption'], 'ssl', false) . '>SSL</option></select></label>';
        echo '<label class="mgd-field"><span>SMTP Benutzer</span><input type="text" name="smtp_username" value="' . esc_attr($settings['smtp_username']) . '"></label>';
        echo '<label class="mgd-field"><span>SMTP Passwort</span><input type="password" name="smtp_password" value="' . esc_attr($settings['smtp_password']) . '"></label>';
        echo '<p><button class="button button-primary" type="submit">Speichern</button></p>';
        echo '</form></div>';
    }

    public function render_mail_list_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Keine Berechtigung.', 'mgd-giveaway'));
        }

        global $wpdb;
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $where = '1=1';
        $params = array();

        if ($search) {
            $where .= ' AND (email LIKE %s OR data LIKE %s)';
            $like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "SELECT * FROM {$this->submission_table} WHERE {$where} ORDER BY created_at DESC LIMIT 200";
        $items = $params ? $wpdb->get_results($wpdb->prepare($sql, $params)) : $wpdb->get_results($sql);

        echo '<div class="wrap mgd-admin"><h1>Mail-Liste</h1>';
        $this->render_notices();
        echo '<form method="get" class="mgd-toolbar"><input type="hidden" name="page" value="mgd-giveaway-mail-list"><input type="search" name="s" value="' . esc_attr($search) . '" placeholder="E-Mail oder Daten suchen"><button class="button">Suchen</button> <a class="button" href="' . esc_url(admin_url('admin.php?page=mgd-giveaway-mail-list')) . '">Zurücksetzen</a></form>';
        echo '<div class="mgd-toolbar">';
        echo '<a class="button button-primary" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=mgd_giveaway_export_mail_list'), 'mgd_giveaway_export_mail_list')) . '">CSV exportieren</a>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data">';
        wp_nonce_field('mgd_giveaway_import_mail_list');
        echo '<input type="hidden" name="action" value="mgd_giveaway_import_mail_list"><input type="file" name="mail_list_csv" accept=".csv,text/csv" required> <button class="button">CSV importieren</button></form>';
        echo '</div>';
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Formular</th><th>E-Mail</th><th>Status</th><th>Downloads</th><th>Datum</th><th>Aktionen</th><th>DSGVO</th></tr></thead><tbody>';

        if (!$items) {
            echo '<tr><td colspan="8">Keine Einträge gefunden.</td></tr>';
        }

        foreach ($items as $item) {
            $data = json_decode($item->data, true);
            $modal_id = 'mgd-submission-modal-' . (int) $item->id;
            $export_url = wp_nonce_url(admin_url('admin-post.php?action=mgd_giveaway_export_contact&submission_id=' . (int) $item->id), 'mgd_giveaway_export_contact_' . (int) $item->id);
            $delete_url = wp_nonce_url(admin_url('admin-post.php?action=mgd_giveaway_delete_contact&submission_id=' . (int) $item->id), 'mgd_giveaway_delete_contact_' . (int) $item->id);
            echo '<tr>';
            echo '<td>' . esc_html((string) $item->id) . '</td>';
            echo '<td>' . esc_html($item->form_id ? get_the_title((int) $item->form_id) : 'Import') . '</td>';
            echo '<td><a href="mailto:' . esc_attr($item->email) . '">' . esc_html($item->email) . '</a></td>';
            echo '<td>' . esc_html(isset($item->status) ? $item->status : 'confirmed') . '</td>';
            echo '<td>' . esc_html(isset($item->download_count) ? (string) $item->download_count : '0') . '</td>';
            echo '<td>' . esc_html($item->created_at) . '</td>';
            echo '<td><button type="button" class="button mgd-open-admin-modal" data-mgd-admin-modal="' . esc_attr($modal_id) . '">Ansehen</button></td>';
            echo '<td><a class="button" href="' . esc_url($export_url) . '">Export</a> <a class="button button-link-delete" href="' . esc_url($delete_url) . '" onclick="return confirm(\'Eintrag wirklich löschen?\');">Löschen</a></td>';
            echo '</tr>';
            $this->render_submission_modal($modal_id, $item, is_array($data) ? $data : array());
        }

        echo '</tbody></table><p><small>Anzeige ist auf 200 Einträge begrenzt. Der CSV-Export enthält alle Einträge.</small></p></div>';
    }

    private function render_submission_modal($modal_id, $item, $data)
    {
        echo '<div id="' . esc_attr($modal_id) . '" class="mgd-admin-modal" aria-hidden="true">';
        echo '<div class="mgd-admin-modal-backdrop" data-mgd-admin-modal-close></div>';
        echo '<div class="mgd-admin-modal-dialog" role="dialog" aria-modal="true" aria-label="Nutzerdaten">';
        echo '<button type="button" class="mgd-admin-modal-close" data-mgd-admin-modal-close aria-label="Schließen">&times;</button>';
        echo '<h2>Nutzerdaten</h2>';
        echo '<dl class="mgd-detail-list">';
        echo '<dt>ID</dt><dd>' . esc_html((string) $item->id) . '</dd>';
        echo '<dt>Formular</dt><dd>' . esc_html($item->form_id ? get_the_title((int) $item->form_id) : 'Import') . '</dd>';
        echo '<dt>E-Mail</dt><dd><a href="mailto:' . esc_attr($item->email) . '">' . esc_html($item->email) . '</a></dd>';
        echo '<dt>Status</dt><dd>' . esc_html(isset($item->status) ? $item->status : 'confirmed') . '</dd>';
        echo '<dt>Downloads</dt><dd>' . esc_html(isset($item->download_count) ? (string) $item->download_count : '0') . '</dd>';
        echo '<dt>Datum</dt><dd>' . esc_html($item->created_at) . '</dd>';
        foreach ($data as $key => $value) {
            if (0 === strpos((string) $key, '_')) {
                continue;
            }
            echo '<dt>' . esc_html((string) $key) . '</dt><dd>' . esc_html(is_scalar($value) ? (string) $value : wp_json_encode($value)) . '</dd>';
        }
        echo '</dl>';
        echo '</div>';
        echo '</div>';
    }

    public function render_logs_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Keine Berechtigung.', 'mgd-giveaway'));
        }

        global $wpdb;
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $level = isset($_GET['level']) ? sanitize_key($_GET['level']) : '';
        $where = '1=1';
        $params = array();

        if ($search) {
            $where .= ' AND (event LIKE %s OR message LIKE %s OR context LIKE %s)';
            $like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($level && in_array($level, array('info', 'warning', 'error'), true)) {
            $where .= ' AND level = %s';
            $params[] = $level;
        }

        $sql = "SELECT * FROM {$this->log_table} WHERE {$where} ORDER BY created_at DESC LIMIT 300";
        $logs = $params ? $wpdb->get_results($wpdb->prepare($sql, $params)) : $wpdb->get_results($sql);
        $storage = $this->get_log_storage_usage();

        echo '<div class="wrap mgd-admin"><h1>Logs</h1>';
        $this->render_notices();
        echo '<div class="mgd-stats"><div><strong>' . esc_html((string) $this->count_logs()) . '</strong><span>Log-Einträge</span></div><div><strong>' . esc_html($storage) . '</strong><span>Speicherverbrauch</span></div></div>';
        echo '<form method="get" class="mgd-toolbar"><input type="hidden" name="page" value="mgd-giveaway-logs"><input type="search" name="s" value="' . esc_attr($search) . '" placeholder="Logs durchsuchen"><select name="level"><option value="">Alle Level</option><option value="info" ' . selected($level, 'info', false) . '>Info</option><option value="warning" ' . selected($level, 'warning', false) . '>Warnung</option><option value="error" ' . selected($level, 'error', false) . '>Fehler</option></select><button class="button">Filtern</button> <a class="button" href="' . esc_url(admin_url('admin.php?page=mgd-giveaway-logs')) . '">Zurücksetzen</a></form>';
        echo '<div class="mgd-toolbar"><a class="button button-primary" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=mgd_giveaway_export_logs'), 'mgd_giveaway_export_logs')) . '">Logs exportieren</a>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'Logs wirklich leeren?\');">';
        wp_nonce_field('mgd_giveaway_clear_logs');
        echo '<input type="hidden" name="action" value="mgd_giveaway_clear_logs"><button class="button button-link-delete">Leeren</button></form></div>';
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Level</th><th>Event</th><th>Nachricht</th><th>Kontext</th><th>Datum</th></tr></thead><tbody>';

        if (!$logs) {
            echo '<tr><td colspan="6">Keine Logs gefunden.</td></tr>';
        }

        foreach ($logs as $log) {
            echo '<tr>';
            echo '<td>' . esc_html((string) $log->id) . '</td>';
            echo '<td>' . esc_html($log->level) . '</td>';
            echo '<td>' . esc_html($log->event) . '</td>';
            echo '<td>' . esc_html($log->message) . '</td>';
            echo '<td><code>' . esc_html($log->context) . '</code></td>';
            echo '<td>' . esc_html($log->created_at) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table><p><small>Anzeige ist auf 300 Einträge begrenzt. Der CSV-Export enthält alle Logs.</small></p></div>';
    }

    public function render_credits_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Keine Berechtigung.', 'mgd-giveaway'));
        }

        $tools = array(
            array('logo' => 'wordpress.svg', 'name' => 'WordPress', 'description' => 'CMS und Plugin-API für Backend, Shortcodes, Mediathek und E-Mail-Versand.', 'url' => 'https://wordpress.org', 'license' => 'GPL-2.0-or-later', 'commercial' => 'Kommerzielle Nutzung erlaubt.'),
            array('logo' => 'php.svg', 'name' => 'PHP', 'description' => 'Serverseitige Programmiersprache des Plugins.', 'url' => 'https://www.php.net', 'license' => 'PHP License', 'commercial' => 'Kommerzielle Nutzung erlaubt.'),
            array('logo' => 'phpmailer.svg', 'name' => 'PHPMailer', 'description' => 'E-Mail-Bibliothek, die WordPress intern für wp_mail nutzt.', 'url' => 'https://github.com/PHPMailer/PHPMailer', 'license' => 'LGPL-2.1-only', 'commercial' => 'Kommerzielle Nutzung erlaubt.'),
            array('logo' => 'dashicons.svg', 'name' => 'Dashicons', 'description' => 'WordPress-Icon-Font für Backend-Menüs und Admin-Oberfläche.', 'url' => 'https://developer.wordpress.org/resource/dashicons/', 'license' => 'GPL-2.0-or-later', 'commercial' => 'Kommerzielle Nutzung erlaubt.'),
        );

        echo '<div class="wrap mgd-admin"><h1>Credits</h1>';
        echo '<section class="mgd-panel"><h2>Michael Gahn DESIGN</h2><p>Website: <a href="https://Michael-Gahn.de" target="_blank" rel="noopener">Michael-Gahn.de</a></p><p>Impressum: <a href="https://michael-gahn.de/impressum" target="_blank" rel="noopener">michael-gahn.de/impressum</a></p></section>';
        echo '<section class="mgd-panel"><h2>Verwendete Tools</h2><div class="mgd-credit-list">';

        foreach ($tools as $tool) {
            echo '<article class="mgd-credit">';
            echo '<img src="' . esc_url(MGD_GIVEAWAY_URL . 'assets/logos/' . $tool['logo']) . '" alt="">';
            echo '<div><h3>' . esc_html($tool['name']) . '</h3><p>' . esc_html($tool['description']) . '</p><p><a href="' . esc_url($tool['url']) . '" target="_blank" rel="noopener">Website</a> · ' . esc_html($tool['commercial']) . ' · Lizenz: ' . esc_html($tool['license']) . '</p></div>';
            echo '</article>';
        }

        echo '</div><p><em>Hinweis: Diese Angaben ersetzen keine rechtliche Lizenzprüfung vor produktivem Release.</em></p></section></div>';
    }

    public function handle_save_form()
    {
        if (!current_user_can('manage_options') || !check_admin_referer('mgd_giveaway_save_form')) {
            wp_die(esc_html__('Ungültige Anfrage.', 'mgd-giveaway'));
        }

        $form_id = isset($_POST['form_id']) ? absint($_POST['form_id']) : 0;
        $title = isset($_POST['form_title']) ? sanitize_text_field(wp_unslash($_POST['form_title'])) : '';
        $fields = $this->sanitize_fields(isset($_POST['fields']) ? wp_unslash($_POST['fields']) : array());

        if ($form_id) {
            $existing_post = get_post($form_id);
            if (!$existing_post || $existing_post->post_type !== $this->post_type) {
                wp_die(esc_html__('Formular nicht gefunden.', 'mgd-giveaway'));
            }
        }

        $post_data = array(
            'post_type' => $this->post_type,
            'post_title' => $title ? $title : 'Unbenanntes Formular',
            'post_status' => 'publish',
        );

        if ($form_id) {
            $post_data['ID'] = $form_id;
            $updated = wp_update_post($post_data, true);
            if (is_wp_error($updated)) {
                wp_die(esc_html($updated->get_error_message()));
            }
        } else {
            $form_id = wp_insert_post($post_data, true);
            if (is_wp_error($form_id) || !$form_id) {
                wp_die(esc_html(is_wp_error($form_id) ? $form_id->get_error_message() : __('Formular konnte nicht gespeichert werden.', 'mgd-giveaway')));
            }
        }

        $download_attachment_id = isset($_POST['download_attachment_id']) ? absint($_POST['download_attachment_id']) : 0;
        $old_config = $form_id ? $this->get_form_config($form_id) : array();
        $protected_file = $download_attachment_id ? $this->ensure_protected_download_copy($form_id, $download_attachment_id, $old_config) : array();

        $config = array(
            'fields' => $fields,
            'download_attachment_id' => $download_attachment_id,
            'protected_file' => $protected_file,
            'submit_button_label' => isset($_POST['submit_button_label']) ? sanitize_text_field(wp_unslash($_POST['submit_button_label'])) : 'Gratis-Ratgeber anfordern',
            'button_label' => isset($_POST['button_label']) ? sanitize_text_field(wp_unslash($_POST['button_label'])) : 'Jetzt herunterladen',
            'success_message' => isset($_POST['success_message']) ? sanitize_textarea_field(wp_unslash($_POST['success_message'])) : '',
            'email_subject' => isset($_POST['email_subject']) ? sanitize_text_field(wp_unslash($_POST['email_subject'])) : '',
            'email_body' => isset($_POST['email_body']) ? sanitize_textarea_field(wp_unslash($_POST['email_body'])) : '',
            'send_email' => !empty($_POST['send_email']),
            'double_opt_in' => !empty($_POST['double_opt_in']),
            'confirm_subject' => isset($_POST['confirm_subject']) ? sanitize_text_field(wp_unslash($_POST['confirm_subject'])) : '',
            'confirm_body' => isset($_POST['confirm_body']) ? sanitize_textarea_field(wp_unslash($_POST['confirm_body'])) : '',
            'confirm_message' => isset($_POST['confirm_message']) ? sanitize_textarea_field(wp_unslash($_POST['confirm_message'])) : '',
            'style' => $this->sanitize_style(isset($_POST['style']) ? wp_unslash($_POST['style']) : array()),
        );

        update_post_meta($form_id, '_mgd_giveaway_config', $config);
        $this->add_log('info', 'form_saved', 'Formular gespeichert.', array('form_id' => $form_id, 'title' => $post_data['post_title']));

        $active_tab = isset($_POST['mgd_active_tab']) ? sanitize_key(wp_unslash($_POST['mgd_active_tab'])) : 'fields';
        wp_safe_redirect(admin_url('admin.php?page=mgd-giveaway-form&form_id=' . (int) $form_id . '&mgd_notice=saved&mgd_tab=' . rawurlencode($active_tab)));
        exit;
    }

    private function sanitize_fields($raw_fields)
    {
        $allowed_types = array_keys($this->get_field_types());
        $reserved_names = array(
            'action' => true,
            'form_id' => true,
            'mgd_giveaway_return_url' => true,
            'mgd_giveaway_started' => true,
            'mgd_giveaway_website' => true,
        );
        $fields = array();
        $used_names = array();

        if (!is_array($raw_fields)) {
            return array();
        }

        foreach ($raw_fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $label = isset($field['label']) ? sanitize_text_field($field['label']) : '';
            $name = isset($field['name']) ? sanitize_key($field['name']) : '';
            $type = isset($field['type']) && in_array($field['type'], $allowed_types, true) ? $field['type'] : 'text';

            if (!$label || !$name) {
                continue;
            }

            if (isset($reserved_names[$name])) {
                $name = 'field_' . $name;
            }

            $base_name = $name;
            $suffix = 2;
            while (isset($used_names[$name])) {
                $name = $base_name . '_' . $suffix;
                $suffix++;
            }
            $used_names[$name] = true;

            $fields[] = array(
                'label' => $label,
                'name' => $name,
                'type' => $type,
                'required' => !empty($field['required']),
                'text' => isset($field['text']) ? sanitize_textarea_field($field['text']) : '',
                'privacy_url' => isset($field['privacy_url']) ? esc_url_raw($field['privacy_url']) : '',
            );
        }

        return $fields;
    }

    private function sanitize_style($raw_style)
    {
        $raw_style = is_array($raw_style) ? $raw_style : array();

        return array(
            'max_width' => (string) min(1200, max(260, isset($raw_style['max_width']) ? absint($raw_style['max_width']) : 560)),
            'button_bg' => !empty($raw_style['button_bg']) && sanitize_hex_color($raw_style['button_bg']) ? sanitize_hex_color($raw_style['button_bg']) : '#151515',
            'button_text' => !empty($raw_style['button_text']) && sanitize_hex_color($raw_style['button_text']) ? sanitize_hex_color($raw_style['button_text']) : '#ffffff',
            'field_border' => !empty($raw_style['field_border']) && sanitize_hex_color($raw_style['field_border']) ? sanitize_hex_color($raw_style['field_border']) : '#bbbbbb',
            'radius' => (string) min(32, max(0, isset($raw_style['radius']) ? absint($raw_style['radius']) : 6)),
        );
    }

    private function ensure_protected_download_copy($form_id, $attachment_id, $old_config)
    {
        if (!empty($old_config['download_attachment_id']) && (int) $old_config['download_attachment_id'] === $attachment_id && !empty($old_config['protected_file']['path']) && is_readable($old_config['protected_file']['path'])) {
            return $old_config['protected_file'];
        }

        $source_path = get_attached_file($attachment_id);
        if (!$source_path || !is_readable($source_path)) {
            $this->add_log('warning', 'protected_copy_failed', 'Geschützte Download-Kopie konnte nicht erstellt werden.', array('form_id' => $form_id, 'attachment_id' => $attachment_id));
            return array();
        }

        $dir = $this->get_protected_download_dir();
        if (!$dir || !wp_mkdir_p($dir)) {
            $this->add_log('error', 'protected_dir_failed', 'Geschützter Download-Ordner konnte nicht erstellt werden.', array('form_id' => $form_id));
            return array();
        }

        $this->write_protected_dir_files($dir);
        $filename = sanitize_file_name(basename($source_path));
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $stored_name = 'form-' . $form_id . '-file-' . $attachment_id . '-' . substr(hash('sha256', wp_salt('auth') . $source_path . time()), 0, 12) . ($extension ? '.' . $extension : '');
        $target_path = trailingslashit($dir) . $stored_name;

        if (!copy($source_path, $target_path)) {
            $this->add_log('error', 'protected_copy_failed', 'Download-Datei konnte nicht in geschützten Ordner kopiert werden.', array('form_id' => $form_id, 'attachment_id' => $attachment_id));
            return array();
        }

        $this->add_log('info', 'protected_copy_created', 'Geschützte Download-Kopie erstellt.', array('form_id' => $form_id, 'attachment_id' => $attachment_id));

        return array(
            'path' => $target_path,
            'name' => $filename,
            'mime' => get_post_mime_type($attachment_id),
            'attachment_id' => $attachment_id,
        );
    }

    private function get_protected_download_dir()
    {
        $uploads = wp_upload_dir(null, false);
        if (!empty($uploads['error']) || empty($uploads['basedir'])) {
            return '';
        }

        return trailingslashit($uploads['basedir']) . 'mgd-giveaway-protected';
    }

    private function write_protected_dir_files($dir)
    {
        $htaccess = trailingslashit($dir) . '.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all\nRequire all denied\n");
        }

        $index = trailingslashit($dir) . 'index.php';
        if (!file_exists($index)) {
            file_put_contents($index, "<?php\n// Silence is golden.\n");
        }
    }

    public function handle_delete_form()
    {
        $form_id = isset($_GET['form_id']) ? absint($_GET['form_id']) : 0;
        if (!current_user_can('manage_options') || !$form_id || !check_admin_referer('mgd_giveaway_delete_' . $form_id)) {
            wp_die(esc_html__('Ungültige Anfrage.', 'mgd-giveaway'));
        }

        wp_delete_post($form_id, true);
        $this->add_log('info', 'form_deleted', 'Formular gelöscht.', array('form_id' => $form_id));
        wp_safe_redirect(admin_url('admin.php?page=mgd-giveaway&mgd_notice=deleted'));
        exit;
    }

    public function handle_duplicate_form()
    {
        $form_id = isset($_GET['form_id']) ? absint($_GET['form_id']) : 0;
        if (!current_user_can('manage_options') || !$form_id || !check_admin_referer('mgd_giveaway_duplicate_' . $form_id)) {
            wp_die(esc_html__('Ungültige Anfrage.', 'mgd-giveaway'));
        }

        $post = get_post($form_id);
        if (!$post || $post->post_type !== $this->post_type) {
            wp_die(esc_html__('Formular nicht gefunden.', 'mgd-giveaway'));
        }

        $new_id = wp_insert_post(array(
            'post_type' => $this->post_type,
            'post_title' => $post->post_title . ' Kopie',
            'post_status' => 'publish',
        ), true);

        if (is_wp_error($new_id) || !$new_id) {
            wp_die(esc_html(is_wp_error($new_id) ? $new_id->get_error_message() : __('Formular konnte nicht dupliziert werden.', 'mgd-giveaway')));
        }

        update_post_meta($new_id, '_mgd_giveaway_config', $this->get_form_config($form_id));
        $this->add_log('info', 'form_duplicated', 'Formular dupliziert.', array('source_form_id' => $form_id, 'new_form_id' => $new_id));

        wp_safe_redirect(admin_url('admin.php?page=mgd-giveaway-form&form_id=' . (int) $new_id . '&mgd_notice=duplicated'));
        exit;
    }

    public function handle_save_settings()
    {
        if (!current_user_can('manage_options') || !check_admin_referer('mgd_giveaway_save_settings')) {
            wp_die(esc_html__('Ungültige Anfrage.', 'mgd-giveaway'));
        }

        $from_email = isset($_POST['from_email']) ? sanitize_email(wp_unslash($_POST['from_email'])) : '';
        $notification_recipient = isset($_POST['notification_recipient']) ? sanitize_email(wp_unslash($_POST['notification_recipient'])) : '';
        $settings = array(
            'mail_method' => isset($_POST['mail_method']) && 'smtp' === $_POST['mail_method'] ? 'smtp' : 'php',
            'from_name' => isset($_POST['from_name']) ? sanitize_text_field(wp_unslash($_POST['from_name'])) : '',
            'from_email' => is_email($from_email) ? $from_email : get_option('admin_email'),
            'notification_recipient' => is_email($notification_recipient) ? $notification_recipient : get_option('admin_email'),
            'smtp_host' => isset($_POST['smtp_host']) ? sanitize_text_field(wp_unslash($_POST['smtp_host'])) : '',
            'smtp_port' => isset($_POST['smtp_port']) ? absint($_POST['smtp_port']) : 587,
            'smtp_encryption' => isset($_POST['smtp_encryption']) && in_array($_POST['smtp_encryption'], array('none', 'tls', 'ssl'), true) ? sanitize_key($_POST['smtp_encryption']) : 'tls',
            'smtp_username' => isset($_POST['smtp_username']) ? sanitize_text_field(wp_unslash($_POST['smtp_username'])) : '',
            'smtp_password' => isset($_POST['smtp_password']) ? sanitize_text_field(wp_unslash($_POST['smtp_password'])) : '',
        );

        update_option('mgd_giveaway_settings', $settings, false);
        $this->add_log('info', 'settings_saved', 'E-Mail Einstellungen gespeichert.', array('notification_recipient' => $settings['notification_recipient'], 'mail_method' => $settings['mail_method']));
        wp_safe_redirect(admin_url('admin.php?page=mgd-giveaway-settings&mgd_notice=saved'));
        exit;
    }

    public function handle_export_mail_list()
    {
        if (!current_user_can('manage_options') || !check_admin_referer('mgd_giveaway_export_mail_list')) {
            wp_die(esc_html__('Ungültige Anfrage.', 'mgd-giveaway'));
        }

        global $wpdb;
        $items = $wpdb->get_results("SELECT * FROM {$this->submission_table} ORDER BY created_at DESC", ARRAY_A);
        $this->add_log('info', 'mail_list_export', 'Mail-Liste als CSV exportiert.', array('count' => count($items)));

        $this->output_csv('mgd-giveaway-mail-liste.csv', array('id', 'form_id', 'email', 'status', 'confirmed_at', 'download_count', 'data', 'created_at'), $items);
    }

    public function handle_import_mail_list()
    {
        if (!current_user_can('manage_options') || !check_admin_referer('mgd_giveaway_import_mail_list')) {
            wp_die(esc_html__('Ungültige Anfrage.', 'mgd-giveaway'));
        }

        if (empty($_FILES['mail_list_csv']['tmp_name'])) {
            wp_safe_redirect(admin_url('admin.php?page=mgd-giveaway-mail-list&mgd_notice=import_empty'));
            exit;
        }

        $file = $_FILES['mail_list_csv'];
        $filename = isset($file['name']) ? sanitize_file_name($file['name']) : '';
        $tmp_name = isset($file['tmp_name']) ? $file['tmp_name'] : '';
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $file_size = isset($file['size']) ? (int) $file['size'] : 0;

        if ('csv' !== $extension || !is_uploaded_file($tmp_name) || $file_size > $this->max_csv_upload_bytes) {
            $this->add_log('warning', 'mail_list_import_rejected', 'CSV Import abgelehnt.', array('filename' => $filename, 'size' => $file_size));
            wp_safe_redirect(admin_url('admin.php?page=mgd-giveaway-mail-list&mgd_notice=import_invalid'));
            exit;
        }

        $handle = fopen($tmp_name, 'r');
        $imported = 0;

        if ($handle) {
            $header = fgetcsv($handle, 0, ',');
            while (($row = fgetcsv($handle, 0, ',')) !== false && $imported < $this->max_csv_import_rows) {
                $record = $this->map_csv_row($header, $row);
                $email = isset($record['email']) ? sanitize_email($record['email']) : '';

                if (!$email || !is_email($email)) {
                    continue;
                }

                $data = $record;
                unset($data['email']);
                $this->store_submission(0, $email, array_merge(array('import_source' => $filename), $data));
                $imported++;
            }
            fclose($handle);
        }

        $this->add_log('info', 'mail_list_import', 'Mail-Liste als CSV importiert.', array('filename' => $filename, 'count' => $imported));
        wp_safe_redirect(admin_url('admin.php?page=mgd-giveaway-mail-list&mgd_notice=imported'));
        exit;
    }

    public function handle_export_contact()
    {
        $submission_id = isset($_GET['submission_id']) ? absint($_GET['submission_id']) : 0;
        if (!current_user_can('manage_options') || !$submission_id || !check_admin_referer('mgd_giveaway_export_contact_' . $submission_id)) {
            wp_die(esc_html__('Ungültige Anfrage.', 'mgd-giveaway'));
        }

        global $wpdb;
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->submission_table} WHERE id = %d", $submission_id), ARRAY_A);
        if (!$item) {
            wp_die(esc_html__('Eintrag nicht gefunden.', 'mgd-giveaway'));
        }

        $this->add_log('info', 'contact_export', 'Kontakt-Daten exportiert.', array('submission_id' => $submission_id, 'email' => $item['email']));
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="mgd-giveaway-kontakt-' . $submission_id . '.json"');
        echo wp_json_encode($item, JSON_PRETTY_PRINT);
        exit;
    }

    public function handle_delete_contact()
    {
        $submission_id = isset($_GET['submission_id']) ? absint($_GET['submission_id']) : 0;
        if (!current_user_can('manage_options') || !$submission_id || !check_admin_referer('mgd_giveaway_delete_contact_' . $submission_id)) {
            wp_die(esc_html__('Ungültige Anfrage.', 'mgd-giveaway'));
        }

        global $wpdb;
        $item = $wpdb->get_row($wpdb->prepare("SELECT email FROM {$this->submission_table} WHERE id = %d", $submission_id), ARRAY_A);
        $wpdb->delete($this->submission_table, array('id' => $submission_id), array('%d'));
        $this->add_log('info', 'contact_deleted', 'Kontakt-Daten gelöscht.', array('submission_id' => $submission_id, 'email' => isset($item['email']) ? $item['email'] : ''));
        wp_safe_redirect(admin_url('admin.php?page=mgd-giveaway-mail-list&mgd_notice=contact_deleted'));
        exit;
    }

    public function handle_export_logs()
    {
        if (!current_user_can('manage_options') || !check_admin_referer('mgd_giveaway_export_logs')) {
            wp_die(esc_html__('Ungültige Anfrage.', 'mgd-giveaway'));
        }

        global $wpdb;
        $logs = $wpdb->get_results("SELECT * FROM {$this->log_table} ORDER BY created_at DESC", ARRAY_A);
        $this->output_csv('mgd-giveaway-logs.csv', array('id', 'level', 'event', 'message', 'context', 'created_at'), $logs);
    }

    public function handle_clear_logs()
    {
        if (!current_user_can('manage_options') || !check_admin_referer('mgd_giveaway_clear_logs')) {
            wp_die(esc_html__('Ungültige Anfrage.', 'mgd-giveaway'));
        }

        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$this->log_table}");
        wp_safe_redirect(admin_url('admin.php?page=mgd-giveaway-logs&mgd_notice=logs_cleared'));
        exit;
    }

    public function render_shortcode($atts)
    {
        $atts = shortcode_atts(array('id' => 0), $atts, 'mgd_giveaway');
        $form_id = absint($atts['id']);
        $post = $form_id ? get_post($form_id) : null;

        if (!$post || $post->post_type !== $this->post_type) {
            return '<p class="mgd-giveaway-error">Formular nicht gefunden.</p>';
        }

        $config = $this->get_form_config($form_id);
        ob_start();
        $wrapper_id = 'mgd-giveaway-' . $form_id;
        echo '<div id="' . esc_attr($wrapper_id) . '" class="mgd-giveaway-wrapper" style="' . esc_attr($this->get_frontend_style_vars($config)) . '">';

        $success_payload = $this->get_success_payload($form_id);
        if ($success_payload) {
            $this->render_success_message($success_payload);
            echo '</div>';
            return ob_get_clean();
        }

        echo '<form class="mgd-giveaway-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="mgd_giveaway_submit">';
        echo '<input type="hidden" name="form_id" value="' . esc_attr((string) $form_id) . '">';
        echo '<input type="hidden" name="mgd_giveaway_return_url" value="' . esc_attr($this->get_current_page_url()) . '">';
        echo '<input type="text" name="mgd_giveaway_website" value="" class="mgd-giveaway-hp" tabindex="-1" autocomplete="off" aria-hidden="true">';
        echo '<input type="hidden" name="mgd_giveaway_started" value="' . esc_attr($this->create_spam_token()) . '">';

        foreach ($config['fields'] as $field) {
            $this->render_frontend_field($field);
        }

        $submit_fallback = "if(this.form){if(this.form.reportValidity&&!this.form.reportValidity()){return false;}this.disabled=true;this.textContent='Wird gesendet...';if(window.HTMLFormElement&&HTMLFormElement.prototype.submit){HTMLFormElement.prototype.submit.call(this.form);}else{this.form.submit();}return false;}";
        echo '<button type="submit" class="mgd-giveaway-submit" style="' . esc_attr($this->get_button_inline_style($config)) . '" onclick="' . esc_attr($submit_fallback) . '">' . esc_html($config['submit_button_label']) . '</button>';
        echo '</form>';
        echo '</div>';

        return ob_get_clean();
    }

    private function get_current_page_url()
    {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $request_uri = '/' . ltrim($request_uri, '/');
        $current_url = home_url($request_uri);

        return remove_query_arg(array('mgd_giveaway_success', 'mgd_form'), $current_url);
    }

    private function get_success_payload($form_id)
    {
        $success_form_id = isset($_GET['mgd_form']) ? absint($_GET['mgd_form']) : 0;
        if ($success_form_id !== $form_id || empty($_GET['mgd_giveaway_success'])) {
            return false;
        }

        $token = sanitize_text_field((string) wp_unslash($_GET['mgd_giveaway_success']));
        $payload = get_transient($this->get_success_transient_key($token));
        if (!is_array($payload) || empty($payload['form_id']) || (int) $payload['form_id'] !== $form_id) {
            return false;
        }

        return $payload;
    }

    private function render_success_message($payload)
    {
        echo '<div class="mgd-giveaway-success-inline">';
        echo '<p>' . esc_html((string) $payload['success_message']) . '</p>';
        if (!empty($payload['download_url'])) {
            echo '<a class="mgd-giveaway-download" href="' . esc_url((string) $payload['download_url']) . '" download style="' . esc_attr($this->get_button_inline_style($payload)) . '">' . esc_html((string) $payload['button_label']) . '</a>';
        } elseif (empty($payload['message_only'])) {
            echo '<p>Es wurde noch keine Download-Datei hinterlegt.</p>';
        }
        echo '</div>';
    }

    private function get_frontend_style_vars($config)
    {
        $style = isset($config['style']) && is_array($config['style']) ? $config['style'] : array();
        $style = $this->sanitize_style($style);

        return '--mgd-form-max-width:' . (int) $style['max_width'] . 'px;--mgd-button-bg:' . $style['button_bg'] . ';--mgd-button-text:' . $style['button_text'] . ';--mgd-field-border:' . $style['field_border'] . ';--mgd-radius:' . (int) $style['radius'] . 'px;';
    }

    private function create_success_redirect_url($form_id, $config, $download_url, $message = '', $message_only = false, $return_url = '')
    {
        $token = wp_generate_password(32, false, false);
        set_transient(
            $this->get_success_transient_key($token),
            array(
                'form_id' => $form_id,
                'success_message' => $message ? $message : $config['success_message'],
                'button_label' => $config['button_label'],
                'download_url' => $download_url,
                'message_only' => $message_only,
                'style' => isset($config['style']) ? $config['style'] : array(),
            ),
            15 * MINUTE_IN_SECONDS
        );

        $return_url = $return_url ? $return_url : (isset($_POST['mgd_giveaway_return_url']) ? esc_url_raw((string) wp_unslash($_POST['mgd_giveaway_return_url'])) : home_url('/'));
        $return_url = wp_validate_redirect($return_url, home_url('/'));
        $return_url = remove_query_arg(array('mgd_giveaway_success', 'mgd_form'), $return_url);

        return add_query_arg(
            array(
                'mgd_giveaway_success' => $token,
                'mgd_form' => $form_id,
            ),
            $return_url
        ) . '#mgd-giveaway-' . $form_id;
    }

    private function get_success_transient_key($token)
    {
        return 'mgd_giveaway_success_' . md5($token);
    }

    private function get_button_inline_style($config)
    {
        $style = isset($config['style']) && is_array($config['style']) ? $config['style'] : array();
        $style = $this->sanitize_style($style);

        return 'background:' . $style['button_bg'] . ' !important;background-color:' . $style['button_bg'] . ' !important;color:' . $style['button_text'] . ' !important;border-color:' . $style['button_bg'] . ' !important;border-radius:' . (int) $style['radius'] . 'px !important;margin-top:12px !important;';
    }

    private function render_frontend_field($field)
    {
        $name = isset($field['name']) ? sanitize_key($field['name']) : '';
        $label = isset($field['label']) ? $field['label'] : $name;
        $type = isset($field['type']) ? $field['type'] : 'text';
        $required = !empty($field['required']) ? ' required' : '';
        $text = isset($field['text']) ? $field['text'] : '';
        $privacy_url = isset($field['privacy_url']) ? esc_url_raw($field['privacy_url']) : '';

        echo '<label class="mgd-giveaway-field"><span>' . esc_html($label) . '</span>';
        if ('textarea' === $type) {
            echo '<textarea name="' . esc_attr($name) . '"' . $required . '></textarea>';
        } elseif ('privacy' === $type) {
            $notice = $text ? $text : $label;
            $modal_id = wp_unique_id('mgd-privacy-modal-');
            echo '<span class="mgd-giveaway-checkbox"><input type="checkbox" name="' . esc_attr($name) . '" value="1"' . $required . '> <span>' . esc_html($notice) . ' <button type="button" class="mgd-privacy-link" data-mgd-modal="' . esc_attr($modal_id) . '">zur Datenschutzerklärung</button></span></span>';
            echo '</label>';
            $this->render_privacy_modal($modal_id, $privacy_url);
            return;
        } elseif ('checkbox' === $type) {
            echo '<input type="checkbox" name="' . esc_attr($name) . '" value="1"' . $required . '>';
        } else {
            echo '<input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '"' . $required . '>';
        }
        echo '</label>';
    }

    private function render_privacy_modal($modal_id, $privacy_url = '')
    {
        $privacy_page_id = $privacy_url ? url_to_postid($privacy_url) : (int) get_option('wp_page_for_privacy_policy');
        $privacy_page = $privacy_page_id ? get_post($privacy_page_id) : null;

        echo '<div id="' . esc_attr($modal_id) . '" class="mgd-privacy-modal" aria-hidden="true">';
        echo '<div class="mgd-privacy-backdrop" data-mgd-modal-close></div>';
        echo '<div class="mgd-privacy-dialog" role="dialog" aria-modal="true" aria-label="Datenschutzerklärung">';
        echo '<button type="button" class="mgd-privacy-close" data-mgd-modal-close aria-label="Schließen">&times;</button>';
        echo '<h2>Datenschutzerklärung</h2>';
        echo '<div class="mgd-privacy-content">';
        if ($privacy_page && 'publish' === $privacy_page->post_status) {
            echo apply_filters('the_content', $privacy_page->post_content);
        } elseif ($privacy_url) {
            echo '<iframe class="mgd-privacy-frame" src="' . esc_url($privacy_url) . '" title="Datenschutzerklärung"></iframe>';
            echo '<p><a href="' . esc_url($privacy_url) . '" target="_blank" rel="noopener noreferrer">Datenschutzerklärung in neuem Fenster öffnen</a></p>';
        } else {
            echo '<p>Es wurde noch keine Datenschutzerklärung in WordPress hinterlegt.</p>';
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    public function handle_frontend_submit()
    {
        $form_id = isset($_POST['form_id']) ? absint($_POST['form_id']) : 0;
        if (!$form_id) {
            wp_die(esc_html__('Ungültige Anfrage.', 'mgd-giveaway'));
        }

        $post = get_post($form_id);
        if (!$post || $post->post_type !== $this->post_type) {
            wp_die(esc_html__('Formular nicht gefunden.', 'mgd-giveaway'));
        }

        if (!$this->passes_spam_check($form_id)) {
            wp_die(esc_html__('Die Anmeldung wurde aus Sicherheitsgründen abgelehnt. Bitte lade die Seite neu und versuche es erneut.', 'mgd-giveaway'));
        }

        $config = $this->get_form_config($form_id);
        $data = array();
        $email = '';

        foreach ($config['fields'] as $field) {
            $name = isset($field['name']) ? sanitize_key($field['name']) : '';
            if (!$name) {
                continue;
            }

            $value = isset($_POST[$name]) ? wp_unslash($_POST[$name]) : '';
            if (!empty($field['required']) && '' === $value) {
                wp_die(esc_html__('Bitte alle Pflichtfelder ausfüllen.', 'mgd-giveaway'));
            }

            if ('email' === $field['type']) {
                $value = sanitize_email($value);
                if ('' !== $value && !is_email($value)) {
                    wp_die(esc_html__('Bitte eine gültige E-Mail-Adresse eingeben.', 'mgd-giveaway'));
                }
                $email = $value;
            } elseif ('checkbox' === $field['type'] || 'privacy' === $field['type']) {
                $value = $value ? '1' : '0';
            } else {
                $value = sanitize_text_field($value);
            }

            $data[$name] = $value;
        }

        $data['_return_url'] = isset($_POST['mgd_giveaway_return_url']) ? esc_url_raw((string) wp_unslash($_POST['mgd_giveaway_return_url'])) : home_url('/');
        $requires_confirmation = !empty($config['double_opt_in']) && $email;
        $submission_id = $this->store_submission($form_id, $email, $data, $requires_confirmation ? 'pending' : 'confirmed');
        $download_url = $this->get_masked_download_url($form_id, (int) $config['download_attachment_id'], $submission_id);
        $this->add_log('info', 'form_submission', 'Neue Formularanmeldung gespeichert.', array('form_id' => $form_id, 'submission_id' => $submission_id, 'email' => $email));

        if ($requires_confirmation) {
            $sent = $this->send_confirmation_email($email, $this->get_confirmation_url($form_id, $submission_id, $email), $config);
            $this->add_log($sent ? 'info' : 'error', 'double_opt_in_email', $sent ? 'Double-Opt-In E-Mail versendet.' : 'Double-Opt-In E-Mail konnte nicht versendet werden.', array('form_id' => $form_id, 'submission_id' => $submission_id, 'email' => $email));
            wp_safe_redirect($this->create_success_redirect_url($form_id, $config, '', $config['confirm_message'], true));
            exit;
        }

        $this->send_notification_email($form_id, $email, $data);

        if (!empty($config['send_email']) && $email && $download_url) {
            $sent = $this->send_download_email($email, $download_url, $config);
            $this->add_log($sent ? 'info' : 'error', 'download_email', $sent ? 'Download-E-Mail versendet.' : 'Download-E-Mail konnte nicht versendet werden.', array('form_id' => $form_id, 'email' => $email));
        }

        wp_safe_redirect($this->create_success_redirect_url($form_id, $config, $download_url));
        exit;
    }

    private function create_spam_token()
    {
        $timestamp = time();
        $hash = hash_hmac('sha256', (string) $timestamp, wp_salt('nonce'));

        return base64_encode($timestamp . '|' . $hash);
    }

    private function passes_spam_check($form_id)
    {
        $honeypot = isset($_POST['mgd_giveaway_website']) ? trim((string) wp_unslash($_POST['mgd_giveaway_website'])) : '';
        if ('' !== $honeypot) {
            $this->add_log('warning', 'spam_rejected', 'Spam-Schutz: Honeypot ausgefüllt.', array('form_id' => $form_id));
            return false;
        }

        $token = isset($_POST['mgd_giveaway_started']) ? (string) wp_unslash($_POST['mgd_giveaway_started']) : '';
        $decoded = base64_decode($token, true);
        if (!$decoded || false === strpos($decoded, '|')) {
            $this->add_log('warning', 'spam_rejected', 'Spam-Schutz: Start-Token fehlt oder ist ungültig.', array('form_id' => $form_id));
            return false;
        }

        list($timestamp, $hash) = explode('|', $decoded, 2);
        $expected = hash_hmac('sha256', (string) $timestamp, wp_salt('nonce'));
        if (!hash_equals($expected, $hash)) {
            $this->add_log('warning', 'spam_rejected', 'Spam-Schutz: Start-Token Signatur ungültig.', array('form_id' => $form_id));
            return false;
        }

        $age = time() - (int) $timestamp;
        if ($age < $this->spam_min_seconds || ($this->spam_max_seconds > 0 && $age > $this->spam_max_seconds)) {
            $this->add_log('warning', 'spam_rejected', 'Spam-Schutz: Absendezeit außerhalb des erlaubten Bereichs.', array('form_id' => $form_id, 'age' => $age));
            return false;
        }

        return true;
    }

    public function handle_masked_download()
    {
        if (empty($_GET['mgd_giveaway_download'])) {
            return;
        }

        $token = sanitize_text_field((string) wp_unslash($_GET['mgd_giveaway_download']));
        $payload = $this->verify_download_token($token, $this->download_token_max_age);

        if (!$payload || empty($payload['form_id'])) {
            $this->add_log('warning', 'download_rejected', 'Maskierter Download-Link ungültig.', array());
            status_header(404);
            wp_die(esc_html__('Download nicht gefunden.', 'mgd-giveaway'));
        }

        $form_id = (int) $payload['form_id'];
        $post = get_post($form_id);
        if (!$post || $post->post_type !== $this->post_type) {
            $this->add_log('warning', 'download_rejected', 'Maskierter Download-Link verweist auf kein gültiges Formular.', array('form_id' => $form_id));
            status_header(404);
            wp_die(esc_html__('Download nicht gefunden.', 'mgd-giveaway'));
        }

        $config = $this->get_form_config($form_id);
        $token_attachment_id = isset($payload['attachment_id']) ? (int) $payload['attachment_id'] : 0;
        if (empty($config['download_attachment_id']) || (int) $config['download_attachment_id'] !== $token_attachment_id) {
            $this->add_log('warning', 'download_rejected', 'Maskierter Download-Link passt nicht zur hinterlegten Datei.', array('form_id' => $form_id));
            status_header(404);
            wp_die(esc_html__('Download nicht gefunden.', 'mgd-giveaway'));
        }

        $protected_file = isset($config['protected_file']) && is_array($config['protected_file']) ? $config['protected_file'] : array();
        if ((empty($protected_file['path']) || !is_readable($protected_file['path'])) && !empty($config['download_attachment_id'])) {
            $protected_file = $this->ensure_protected_download_copy($form_id, (int) $config['download_attachment_id'], $config);
            if ($protected_file) {
                $config['protected_file'] = $protected_file;
                update_post_meta($form_id, '_mgd_giveaway_config', $config);
            }
        }
        $file_path = !empty($protected_file['path']) ? $protected_file['path'] : '';
        if (!$file_path || !is_readable($file_path)) {
            $this->add_log('error', 'download_missing_file', 'Geschützte Download-Datei konnte nicht gelesen werden.', array('form_id' => $form_id));
            status_header(404);
            wp_die(esc_html__('Download-Datei nicht gefunden.', 'mgd-giveaway'));
        }

        $filename = !empty($protected_file['name']) ? sanitize_file_name($protected_file['name']) : basename($file_path);
        $mime = !empty($protected_file['mime']) ? $protected_file['mime'] : '';
        if (!$mime) {
            $filetype = wp_check_filetype($filename);
            $mime = !empty($filetype['type']) ? $filetype['type'] : 'application/octet-stream';
        }

        if (!empty($payload['submission_id'])) {
            $this->increment_download_count((int) $payload['submission_id']);
        }
        $this->add_log('info', 'masked_download', 'Geschützter Download ausgeliefert.', array('form_id' => $form_id, 'submission_id' => isset($payload['submission_id']) ? (int) $payload['submission_id'] : 0));

        nocache_headers();
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit;
    }

    public function handle_double_opt_in_confirm()
    {
        if (empty($_GET['mgd_giveaway_confirm'])) {
            return;
        }

        $token = sanitize_text_field((string) wp_unslash($_GET['mgd_giveaway_confirm']));
        $payload = $this->verify_download_token($token, $this->confirm_token_max_age);
        if (!$payload || empty($payload['form_id']) || empty($payload['submission_id']) || empty($payload['email'])) {
            status_header(404);
            wp_die(esc_html__('Bestätigungslink ist ungültig.', 'mgd-giveaway'));
        }

        global $wpdb;
        $post = get_post((int) $payload['form_id']);
        if (!$post || $post->post_type !== $this->post_type) {
            status_header(404);
            wp_die(esc_html__('Bestätigungslink ist ungültig.', 'mgd-giveaway'));
        }

        $submission = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->submission_table} WHERE id = %d AND form_id = %d", (int) $payload['submission_id'], (int) $payload['form_id']));
        if (!$submission || !hash_equals((string) $submission->email, (string) $payload['email'])) {
            status_header(404);
            wp_die(esc_html__('Bestätigung nicht gefunden.', 'mgd-giveaway'));
        }

        $config = $this->get_form_config((int) $payload['form_id']);
        $data = json_decode($submission->data, true);
        $data = is_array($data) ? $data : array();
        $return_url = !empty($data['_return_url']) ? esc_url_raw($data['_return_url']) : home_url('/');

        if ('confirmed' !== $submission->status) {
            $wpdb->update(
                $this->submission_table,
                array('status' => 'confirmed', 'confirmed_at' => current_time('mysql')),
                array('id' => (int) $submission->id),
                array('%s', '%s'),
                array('%d')
            );
            $this->add_log('info', 'double_opt_in_confirmed', 'Double-Opt-In bestätigt.', array('form_id' => (int) $payload['form_id'], 'submission_id' => (int) $submission->id));
            $this->send_notification_email((int) $payload['form_id'], $submission->email, $data);
        }

        $download_url = $this->get_masked_download_url((int) $payload['form_id'], (int) $config['download_attachment_id'], (int) $submission->id);
        if (!empty($config['send_email']) && $submission->email && $download_url) {
            $this->send_download_email($submission->email, $download_url, $config);
        }

        wp_safe_redirect($this->create_success_redirect_url((int) $payload['form_id'], $config, $download_url, '', false, $return_url));
        exit;
    }

    private function get_masked_download_url($form_id, $attachment_id, $submission_id)
    {
        if (!$attachment_id) {
            return '';
        }

        $token = $this->create_download_token(array(
            'form_id' => $form_id,
            'attachment_id' => $attachment_id,
            'submission_id' => $submission_id,
            'created_at' => time(),
        ));

        return add_query_arg('mgd_giveaway_download', $token, home_url('/'));
    }

    private function create_download_token($payload)
    {
        $encoded = rtrim(strtr(base64_encode((string) wp_json_encode($payload)), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $encoded, wp_salt('auth'));

        return $encoded . '.' . $signature;
    }

    private function get_confirmation_url($form_id, $submission_id, $email)
    {
        $token = $this->create_download_token(array(
            'form_id' => $form_id,
            'submission_id' => $submission_id,
            'email' => $email,
            'created_at' => time(),
        ));

        return add_query_arg('mgd_giveaway_confirm', $token, home_url('/'));
    }

    private function verify_download_token($token, $max_age = 0)
    {
        if (false === strpos($token, '.')) {
            return false;
        }

        list($encoded, $signature) = explode('.', $token, 2);
        $expected = hash_hmac('sha256', $encoded, wp_salt('auth'));
        if (!hash_equals($expected, $signature)) {
            return false;
        }

        $base64 = strtr($encoded, '-_', '+/');
        $base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4);
        $json = base64_decode($base64, true);
        $payload = $json ? json_decode($json, true) : null;
        if (!is_array($payload)) {
            return false;
        }

        if ($max_age > 0) {
            $created_at = isset($payload['created_at']) ? (int) $payload['created_at'] : 0;
            if (!$created_at || time() - $created_at > $max_age) {
                return false;
            }
        }

        return $payload;
    }

    private function store_submission($form_id, $email, $data, $status = 'confirmed')
    {
        global $wpdb;
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 255) : '';

        $wpdb->insert(
            $this->submission_table,
            array(
                'form_id' => $form_id,
                'email' => $email,
                'data' => wp_json_encode($data),
                'ip_hash' => $ip ? hash('sha256', wp_salt('auth') . $ip) : '',
                'user_agent' => $user_agent,
                'status' => $status,
                'confirmed_at' => 'confirmed' === $status ? current_time('mysql') : null,
                'download_count' => 0,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
        );

        return (int) $wpdb->insert_id;
    }

    private function increment_download_count($submission_id)
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare("UPDATE {$this->submission_table} SET download_count = download_count + 1 WHERE id = %d", $submission_id));
    }

    private function send_download_email($email, $download_url, $config)
    {
        $subject = $config['email_subject'] ? $config['email_subject'] : 'Dein Download';
        $body = str_replace('{download_url}', $download_url, $config['email_body']);

        return $this->send_mail($email, $subject, $body);
    }

    private function send_confirmation_email($email, $confirm_url, $config)
    {
        $subject = $config['confirm_subject'] ? $config['confirm_subject'] : 'Bitte bestätige deine Anmeldung';
        $body = str_replace('{confirm_url}', $confirm_url, $config['confirm_body']);

        return $this->send_mail($email, $subject, $body);
    }

    private function send_notification_email($form_id, $email, $data)
    {
        $settings = $this->get_settings();
        $recipient = sanitize_email($settings['notification_recipient']);

        if (!$recipient) {
            $this->add_log('warning', 'notification_email_skipped', 'Keine Empfängeradresse für neue Anmeldungen hinterlegt.', array('form_id' => $form_id, 'email' => $email));
            return false;
        }

        $subject = 'Neue MGD Giveaway Anmeldung';
        $lines = array(
            'Formular: ' . get_the_title($form_id),
            'E-Mail: ' . $email,
            '',
            'Daten:',
        );

        foreach ($data as $key => $value) {
            $lines[] = $key . ': ' . $value;
        }

        $sent = $this->send_mail($recipient, $subject, implode("\n", $lines));
        $this->add_log($sent ? 'info' : 'error', 'notification_email', $sent ? 'Benachrichtigung versendet.' : 'Benachrichtigung konnte nicht versendet werden.', array('recipient' => $recipient, 'form_id' => $form_id));

        return $sent;
    }

    private function send_mail($to, $subject, $body)
    {
        $settings = $this->get_settings();
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        $from_filter = function () use ($settings) {
            return $settings['from_email'];
        };
        $from_name_filter = function () use ($settings) {
            return $settings['from_name'];
        };
        $smtp_action = null;

        add_filter('wp_mail_from', $from_filter);
        add_filter('wp_mail_from_name', $from_name_filter);

        if ('smtp' === $settings['mail_method'] && !empty($settings['smtp_host'])) {
            $smtp_action = function ($phpmailer) use ($settings) {
                $phpmailer->isSMTP();
                $phpmailer->Host = $settings['smtp_host'];
                $phpmailer->Port = (int) $settings['smtp_port'];
                $phpmailer->SMTPAuth = !empty($settings['smtp_username']);
                $phpmailer->Username = $settings['smtp_username'];
                $phpmailer->Password = $settings['smtp_password'];
                if ('none' !== $settings['smtp_encryption']) {
                    $phpmailer->SMTPSecure = $settings['smtp_encryption'];
                }
            };
            add_action('phpmailer_init', $smtp_action);
        }

        $sent = wp_mail($to, $subject, $body, $headers);

        remove_filter('wp_mail_from', $from_filter);
        remove_filter('wp_mail_from_name', $from_name_filter);
        if ($smtp_action) {
            remove_action('phpmailer_init', $smtp_action);
        }

        return $sent;
    }

    private function output_csv($filename, $columns, $rows)
    {
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fputcsv($output, $columns);

        foreach ($rows as $row) {
            $line = array();
            foreach ($columns as $column) {
                $line[] = $this->escape_csv_cell(isset($row[$column]) ? $row[$column] : '');
            }
            fputcsv($output, $line);
        }

        fclose($output);
        exit;
    }

    private function map_csv_row($header, $row)
    {
        $record = array();

        if (!is_array($header)) {
            return $record;
        }

        foreach ($header as $index => $column) {
            $key = sanitize_key($column);
            if (!$key) {
                continue;
            }
            $record[$key] = isset($row[$index]) ? sanitize_text_field($row[$index]) : '';
        }

        return $record;
    }

    private function escape_csv_cell($value)
    {
        $value = (string) $value;
        if ('' !== $value && preg_match('/^[=+\-@]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }

    private function add_log($level, $event, $message, $context = array())
    {
        global $wpdb;
        $allowed_levels = array('info', 'warning', 'error');
        $level = in_array($level, $allowed_levels, true) ? $level : 'info';

        $wpdb->insert(
            $this->log_table,
            array(
                'level' => $level,
                'event' => sanitize_key($event),
                'message' => sanitize_text_field($message),
                'context' => wp_json_encode($context),
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
    }

    private function count_submissions($form_id = 0)
    {
        global $wpdb;
        if ($form_id) {
            return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->submission_table} WHERE form_id = %d", $form_id));
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->submission_table}");
    }

    private function count_logs()
    {
        global $wpdb;

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->log_table}");
    }

    private function get_log_storage_usage()
    {
        global $wpdb;
        $bytes = (int) $wpdb->get_var("SELECT COALESCE(SUM(CHAR_LENGTH(level) + CHAR_LENGTH(event) + CHAR_LENGTH(message) + CHAR_LENGTH(context) + 32), 0) FROM {$this->log_table}");

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }

    private function render_notices()
    {
        if (empty($_GET['mgd_notice'])) {
            return;
        }

        $messages = array(
            'saved' => 'Formular wurde gespeichert.',
            'deleted' => 'Gelöscht.',
            'duplicated' => 'Dupliziert.',
            'imported' => 'CSV importiert.',
            'import_empty' => 'Keine CSV-Datei ausgewählt.',
            'import_invalid' => 'CSV-Datei konnte nicht importiert werden.',
            'logs_cleared' => 'Logs geleert.',
        );
        $notice = sanitize_key($_GET['mgd_notice']);

        if (isset($messages[$notice])) {
            echo '<div class="notice notice-success is-dismissible mgd-save-notice"><p><strong>' . esc_html($messages[$notice]) . '</strong></p></div>';
        }
    }
}
