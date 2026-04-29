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
    private $spam_min_seconds = 3;
    private $spam_max_seconds = 86400;

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
        add_shortcode('mgd_giveaway', array($this, 'render_shortcode'));

        add_action('admin_post_mgd_giveaway_save_form', array($this, 'handle_save_form'));
        add_action('admin_post_mgd_giveaway_delete_form', array($this, 'handle_delete_form'));
        add_action('admin_post_mgd_giveaway_duplicate_form', array($this, 'handle_duplicate_form'));
        add_action('admin_post_mgd_giveaway_save_settings', array($this, 'handle_save_settings'));
        add_action('admin_post_mgd_giveaway_export_mail_list', array($this, 'handle_export_mail_list'));
        add_action('admin_post_mgd_giveaway_import_mail_list', array($this, 'handle_import_mail_list'));
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
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY form_id (form_id),
            KEY email (email)
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
                array('label' => 'Datenschutz', 'name' => 'privacy', 'type' => 'privacy', 'required' => true, 'text' => 'Ich habe die Datenschutzhinweise gelesen und bin mit der Verarbeitung meiner Angaben einverstanden.'),
            ),
            'download_attachment_id' => 0,
            'button_label' => 'Jetzt herunterladen',
            'success_message' => 'Danke fuer deine Anmeldung. Der Download ist jetzt verfuegbar.',
            'email_subject' => 'Dein Download',
            'email_body' => "Hallo,\n\nvielen Dank fuer deine Anmeldung. Dein Download ist jetzt verfuegbar:\n{download_url}",
            'send_email' => true,
        );

        $raw = get_post_meta($form_id, '_mgd_giveaway_config', true);
        $config = is_array($raw) ? $raw : array();

        return wp_parse_args($config, $default);
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
            echo '<a class="button button-link-delete" href="' . esc_url($delete_url) . '" onclick="return confirm(\'Formular wirklich loeschen?\');">Loeschen</a></td>';
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

        echo '<div class="wrap mgd-admin"><h1>' . esc_html($form_id ? 'Formular bearbeiten' : 'Neues Formular') . '</h1>';
        $this->render_notices();
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('mgd_giveaway_save_form');
        echo '<input type="hidden" name="action" value="mgd_giveaway_save_form">';
        echo '<input type="hidden" name="form_id" value="' . esc_attr((string) $form_id) . '">';
        echo '<div class="mgd-grid">';
        echo '<section class="mgd-panel"><h2>Formular</h2>';
        echo '<label class="mgd-field"><span>Name</span><input type="text" name="form_title" value="' . esc_attr($post ? $post->post_title : '') . '" required></label>';
        echo '<label class="mgd-field"><span>Download Button Text</span><input type="text" name="button_label" value="' . esc_attr($config['button_label']) . '"></label>';
        echo '<label class="mgd-field"><span>Erfolgsmeldung</span><textarea name="success_message" rows="3">' . esc_textarea($config['success_message']) . '</textarea></label>';
        echo '<label class="mgd-field"><span>Datei aus Mediathek</span><span class="mgd-media-row"><input type="hidden" id="download_attachment_id" name="download_attachment_id" value="' . esc_attr((string) $attachment_id) . '"><input type="text" id="download_attachment_title" value="' . esc_attr($attachment_title) . '" readonly><button type="button" class="button mgd-select-media">Auswaehlen</button></span></label>';
        echo '<label><input type="checkbox" name="send_email" value="1" ' . checked(!empty($config['send_email']), true, false) . '> Download-Link auch per E-Mail senden</label>';
        echo '<label class="mgd-field"><span>E-Mail Betreff</span><input type="text" name="email_subject" value="' . esc_attr($config['email_subject']) . '"></label>';
        echo '<label class="mgd-field"><span>E-Mail Text</span><textarea name="email_body" rows="6">' . esc_textarea($config['email_body']) . '</textarea><small>Platzhalter: {download_url}</small></label>';
        echo '</section>';

        echo '<section class="mgd-panel mgd-builder"><h2>Felder</h2>';
        echo '<div class="mgd-element-palette" aria-label="Formular Elemente">';
        foreach ($this->get_field_types() as $type_key => $type_label) {
            echo '<button type="button" class="button mgd-add-field" data-type="' . esc_attr($type_key) . '">' . esc_html($type_label) . '</button>';
        }
        echo '</div>';
        echo '<p class="description">Elemente koennen per Drag & Drop sortiert werden.</p>';
        echo '<div id="mgd-fields" data-next-index="' . esc_attr((string) count($config['fields'])) . '">';
        foreach ($config['fields'] as $index => $field) {
            $this->render_field_editor_row($index, $field);
        }
        echo '</div>';
        echo '</section>';
        echo '</div>';

        echo '<p><button class="button button-primary" type="submit">Speichern</button> <a class="button" href="' . esc_url(admin_url('admin.php?page=mgd-giveaway')) . '">Zurueck</a></p>';
        echo '</form>';

        if ($form_id) {
            echo '<section class="mgd-panel mgd-preview"><h2>Vorschau</h2>';
            echo do_shortcode('[mgd_giveaway id="' . (int) $form_id . '"]');
            echo '</section>';
        }

        echo '</div>';
    }

    private function render_field_editor_row($index, $field)
    {
        $types = $this->get_field_types();
        $label = isset($field['label']) ? $field['label'] : '';
        $name = isset($field['name']) ? $field['name'] : '';
        $type = isset($field['type']) ? $field['type'] : 'text';
        $required = !empty($field['required']);
        $text = isset($field['text']) ? $field['text'] : '';

        echo '<div class="mgd-field-row" draggable="true">';
        echo '<button type="button" class="mgd-drag-handle" aria-label="Element verschieben">::</button>';
        echo '<div class="mgd-field-row-main">';
        echo '<label><span>Label</span><input type="text" name="fields[' . esc_attr((string) $index) . '][label]" value="' . esc_attr($label) . '" placeholder="Label"></label>';
        echo '<label><span>Feldname</span><input type="text" name="fields[' . esc_attr((string) $index) . '][name]" value="' . esc_attr($name) . '" placeholder="feldname"></label>';
        echo '<label><span>Typ</span><select class="mgd-field-type" name="fields[' . esc_attr((string) $index) . '][type]">';
        foreach ($types as $value => $title) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($type, $value, false) . '>' . esc_html($title) . '</option>';
        }
        echo '</select></label>';
        echo '<label class="mgd-required-toggle"><input type="checkbox" name="fields[' . esc_attr((string) $index) . '][required]" value="1" ' . checked($required, true, false) . '> Pflichtfeld</label>';
        echo '<button type="button" class="button mgd-remove-field">Entfernen</button>';
        echo '<label class="mgd-field-text"><span>Hinweistext</span><textarea name="fields[' . esc_attr((string) $index) . '][text]" rows="3" placeholder="Optionaler Text, besonders fuer Datenschutz-Hinweise">' . esc_textarea($text) . '</textarea></label>';
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
        echo '<label class="mgd-field"><span>Empfaenger fuer neue Anmeldungen</span><input type="email" name="notification_recipient" value="' . esc_attr($settings['notification_recipient']) . '"><small>An diese Adresse wird jede neue Formularanmeldung gesendet.</small></label>';
        echo '<label class="mgd-field"><span>SMTP Host</span><input type="text" name="smtp_host" value="' . esc_attr($settings['smtp_host']) . '"></label>';
        echo '<label class="mgd-field"><span>SMTP Port</span><input type="number" name="smtp_port" value="' . esc_attr($settings['smtp_port']) . '"></label>';
        echo '<label class="mgd-field"><span>Verschluesselung</span><select name="smtp_encryption"><option value="none" ' . selected($settings['smtp_encryption'], 'none', false) . '>Keine</option><option value="tls" ' . selected($settings['smtp_encryption'], 'tls', false) . '>TLS</option><option value="ssl" ' . selected($settings['smtp_encryption'], 'ssl', false) . '>SSL</option></select></label>';
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
        echo '<form method="get" class="mgd-toolbar"><input type="hidden" name="page" value="mgd-giveaway-mail-list"><input type="search" name="s" value="' . esc_attr($search) . '" placeholder="E-Mail oder Daten suchen"><button class="button">Suchen</button> <a class="button" href="' . esc_url(admin_url('admin.php?page=mgd-giveaway-mail-list')) . '">Zuruecksetzen</a></form>';
        echo '<div class="mgd-toolbar">';
        echo '<a class="button button-primary" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=mgd_giveaway_export_mail_list'), 'mgd_giveaway_export_mail_list')) . '">CSV exportieren</a>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data">';
        wp_nonce_field('mgd_giveaway_import_mail_list');
        echo '<input type="hidden" name="action" value="mgd_giveaway_import_mail_list"><input type="file" name="mail_list_csv" accept=".csv,text/csv" required> <button class="button">CSV importieren</button></form>';
        echo '</div>';
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Formular</th><th>E-Mail</th><th>Daten</th><th>Datum</th></tr></thead><tbody>';

        if (!$items) {
            echo '<tr><td colspan="5">Keine Eintraege gefunden.</td></tr>';
        }

        foreach ($items as $item) {
            $data = json_decode($item->data, true);
            echo '<tr>';
            echo '<td>' . esc_html((string) $item->id) . '</td>';
            echo '<td>' . esc_html($item->form_id ? get_the_title((int) $item->form_id) : 'Import') . '</td>';
            echo '<td><a href="mailto:' . esc_attr($item->email) . '">' . esc_html($item->email) . '</a></td>';
            echo '<td><code>' . esc_html($data ? wp_json_encode($data) : '') . '</code></td>';
            echo '<td>' . esc_html($item->created_at) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table><p><small>Anzeige ist auf 200 Eintraege begrenzt. Der CSV-Export enthaelt alle Eintraege.</small></p></div>';
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
        echo '<div class="mgd-stats"><div><strong>' . esc_html((string) $this->count_logs()) . '</strong><span>Log-Eintraege</span></div><div><strong>' . esc_html($storage) . '</strong><span>Speicherverbrauch</span></div></div>';
        echo '<form method="get" class="mgd-toolbar"><input type="hidden" name="page" value="mgd-giveaway-logs"><input type="search" name="s" value="' . esc_attr($search) . '" placeholder="Logs durchsuchen"><select name="level"><option value="">Alle Level</option><option value="info" ' . selected($level, 'info', false) . '>Info</option><option value="warning" ' . selected($level, 'warning', false) . '>Warnung</option><option value="error" ' . selected($level, 'error', false) . '>Fehler</option></select><button class="button">Filtern</button> <a class="button" href="' . esc_url(admin_url('admin.php?page=mgd-giveaway-logs')) . '">Zuruecksetzen</a></form>';
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

        echo '</tbody></table><p><small>Anzeige ist auf 300 Eintraege begrenzt. Der CSV-Export enthaelt alle Logs.</small></p></div>';
    }

    public function render_credits_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Keine Berechtigung.', 'mgd-giveaway'));
        }

        $tools = array(
            array('logo' => 'wordpress.svg', 'name' => 'WordPress', 'description' => 'CMS und Plugin-API fuer Backend, Shortcodes, Mediathek und E-Mail-Versand.', 'url' => 'https://wordpress.org', 'license' => 'GPL-2.0-or-later', 'commercial' => 'Kommerzielle Nutzung erlaubt.'),
            array('logo' => 'php.svg', 'name' => 'PHP', 'description' => 'Serverseitige Programmiersprache des Plugins.', 'url' => 'https://www.php.net', 'license' => 'PHP License', 'commercial' => 'Kommerzielle Nutzung erlaubt.'),
            array('logo' => 'phpmailer.svg', 'name' => 'PHPMailer', 'description' => 'E-Mail-Bibliothek, die WordPress intern fuer wp_mail nutzt.', 'url' => 'https://github.com/PHPMailer/PHPMailer', 'license' => 'LGPL-2.1-only', 'commercial' => 'Kommerzielle Nutzung erlaubt.'),
            array('logo' => 'dashicons.svg', 'name' => 'Dashicons', 'description' => 'WordPress-Icon-Font fuer Backend-Menues und Admin-Oberflaeche.', 'url' => 'https://developer.wordpress.org/resource/dashicons/', 'license' => 'GPL-2.0-or-later', 'commercial' => 'Kommerzielle Nutzung erlaubt.'),
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

        echo '</div><p><em>Hinweis: Diese Angaben ersetzen keine rechtliche Lizenzpruefung vor produktivem Release.</em></p></section></div>';
    }

    public function handle_save_form()
    {
        if (!current_user_can('manage_options') || !check_admin_referer('mgd_giveaway_save_form')) {
            wp_die(esc_html__('Ungueltige Anfrage.', 'mgd-giveaway'));
        }

        $form_id = isset($_POST['form_id']) ? absint($_POST['form_id']) : 0;
        $title = isset($_POST['form_title']) ? sanitize_text_field(wp_unslash($_POST['form_title'])) : '';
        $fields = $this->sanitize_fields(isset($_POST['fields']) ? wp_unslash($_POST['fields']) : array());

        $post_data = array(
            'post_type' => $this->post_type,
            'post_title' => $title ? $title : 'Unbenanntes Formular',
            'post_status' => 'publish',
        );

        if ($form_id) {
            $post_data['ID'] = $form_id;
            wp_update_post($post_data);
        } else {
            $form_id = wp_insert_post($post_data);
        }

        $config = array(
            'fields' => $fields,
            'download_attachment_id' => isset($_POST['download_attachment_id']) ? absint($_POST['download_attachment_id']) : 0,
            'button_label' => isset($_POST['button_label']) ? sanitize_text_field(wp_unslash($_POST['button_label'])) : 'Jetzt herunterladen',
            'success_message' => isset($_POST['success_message']) ? sanitize_textarea_field(wp_unslash($_POST['success_message'])) : '',
            'email_subject' => isset($_POST['email_subject']) ? sanitize_text_field(wp_unslash($_POST['email_subject'])) : '',
            'email_body' => isset($_POST['email_body']) ? sanitize_textarea_field(wp_unslash($_POST['email_body'])) : '',
            'send_email' => !empty($_POST['send_email']),
        );

        update_post_meta($form_id, '_mgd_giveaway_config', $config);
        $this->add_log('info', 'form_saved', 'Formular gespeichert.', array('form_id' => $form_id, 'title' => $post_data['post_title']));

        wp_safe_redirect(admin_url('admin.php?page=mgd-giveaway-form&form_id=' . (int) $form_id . '&mgd_notice=saved'));
        exit;
    }

    private function sanitize_fields($raw_fields)
    {
        $allowed_types = array_keys($this->get_field_types());
        $fields = array();

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

            $fields[] = array(
                'label' => $label,
                'name' => $name,
                'type' => $type,
                'required' => !empty($field['required']),
                'text' => isset($field['text']) ? sanitize_textarea_field($field['text']) : '',
            );
        }

        return $fields;
    }

    public function handle_delete_form()
    {
        $form_id = isset($_GET['form_id']) ? absint($_GET['form_id']) : 0;
        if (!current_user_can('manage_options') || !$form_id || !check_admin_referer('mgd_giveaway_delete_' . $form_id)) {
            wp_die(esc_html__('Ungueltige Anfrage.', 'mgd-giveaway'));
        }

        wp_delete_post($form_id, true);
        $this->add_log('info', 'form_deleted', 'Formular geloescht.', array('form_id' => $form_id));
        wp_safe_redirect(admin_url('admin.php?page=mgd-giveaway&mgd_notice=deleted'));
        exit;
    }

    public function handle_duplicate_form()
    {
        $form_id = isset($_GET['form_id']) ? absint($_GET['form_id']) : 0;
        if (!current_user_can('manage_options') || !$form_id || !check_admin_referer('mgd_giveaway_duplicate_' . $form_id)) {
            wp_die(esc_html__('Ungueltige Anfrage.', 'mgd-giveaway'));
        }

        $post = get_post($form_id);
        if (!$post || $post->post_type !== $this->post_type) {
            wp_die(esc_html__('Formular nicht gefunden.', 'mgd-giveaway'));
        }

        $new_id = wp_insert_post(array(
            'post_type' => $this->post_type,
            'post_title' => $post->post_title . ' Kopie',
            'post_status' => 'publish',
        ));

        update_post_meta($new_id, '_mgd_giveaway_config', $this->get_form_config($form_id));
        $this->add_log('info', 'form_duplicated', 'Formular dupliziert.', array('source_form_id' => $form_id, 'new_form_id' => $new_id));

        wp_safe_redirect(admin_url('admin.php?page=mgd-giveaway-form&form_id=' . (int) $new_id . '&mgd_notice=duplicated'));
        exit;
    }

    public function handle_save_settings()
    {
        if (!current_user_can('manage_options') || !check_admin_referer('mgd_giveaway_save_settings')) {
            wp_die(esc_html__('Ungueltige Anfrage.', 'mgd-giveaway'));
        }

        $settings = array(
            'mail_method' => isset($_POST['mail_method']) && 'smtp' === $_POST['mail_method'] ? 'smtp' : 'php',
            'from_name' => isset($_POST['from_name']) ? sanitize_text_field(wp_unslash($_POST['from_name'])) : '',
            'from_email' => isset($_POST['from_email']) ? sanitize_email(wp_unslash($_POST['from_email'])) : '',
            'notification_recipient' => isset($_POST['notification_recipient']) ? sanitize_email(wp_unslash($_POST['notification_recipient'])) : '',
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
            wp_die(esc_html__('Ungueltige Anfrage.', 'mgd-giveaway'));
        }

        global $wpdb;
        $items = $wpdb->get_results("SELECT * FROM {$this->submission_table} ORDER BY created_at DESC", ARRAY_A);
        $this->add_log('info', 'mail_list_export', 'Mail-Liste als CSV exportiert.', array('count' => count($items)));

        $this->output_csv('mgd-giveaway-mail-liste.csv', array('id', 'form_id', 'email', 'data', 'created_at'), $items);
    }

    public function handle_import_mail_list()
    {
        if (!current_user_can('manage_options') || !check_admin_referer('mgd_giveaway_import_mail_list')) {
            wp_die(esc_html__('Ungueltige Anfrage.', 'mgd-giveaway'));
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

    public function handle_export_logs()
    {
        if (!current_user_can('manage_options') || !check_admin_referer('mgd_giveaway_export_logs')) {
            wp_die(esc_html__('Ungueltige Anfrage.', 'mgd-giveaway'));
        }

        global $wpdb;
        $logs = $wpdb->get_results("SELECT * FROM {$this->log_table} ORDER BY created_at DESC", ARRAY_A);
        $this->output_csv('mgd-giveaway-logs.csv', array('id', 'level', 'event', 'message', 'context', 'created_at'), $logs);
    }

    public function handle_clear_logs()
    {
        if (!current_user_can('manage_options') || !check_admin_referer('mgd_giveaway_clear_logs')) {
            wp_die(esc_html__('Ungueltige Anfrage.', 'mgd-giveaway'));
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
        echo '<div id="' . esc_attr($wrapper_id) . '" class="mgd-giveaway-wrapper">';

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
        wp_nonce_field('mgd_giveaway_submit_' . $form_id);

        foreach ($config['fields'] as $field) {
            $this->render_frontend_field($field);
        }

        echo '<button type="submit">' . esc_html($config['button_label']) . '</button>';
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
            echo '<a class="mgd-giveaway-download" href="' . esc_url((string) $payload['download_url']) . '" download>' . esc_html((string) $payload['button_label']) . '</a>';
        } else {
            echo '<p>Es wurde noch keine Download-Datei hinterlegt.</p>';
        }
        echo '</div>';
    }

    private function create_success_redirect_url($form_id, $config, $download_url)
    {
        $token = wp_generate_password(32, false, false);
        set_transient(
            $this->get_success_transient_key($token),
            array(
                'form_id' => $form_id,
                'success_message' => $config['success_message'],
                'button_label' => $config['button_label'],
                'download_url' => $download_url,
            ),
            15 * MINUTE_IN_SECONDS
        );

        $return_url = isset($_POST['mgd_giveaway_return_url']) ? esc_url_raw((string) wp_unslash($_POST['mgd_giveaway_return_url'])) : home_url('/');
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

    private function render_frontend_field($field)
    {
        $name = isset($field['name']) ? sanitize_key($field['name']) : '';
        $label = isset($field['label']) ? $field['label'] : $name;
        $type = isset($field['type']) ? $field['type'] : 'text';
        $required = !empty($field['required']) ? ' required' : '';
        $text = isset($field['text']) ? $field['text'] : '';

        echo '<label class="mgd-giveaway-field"><span>' . esc_html($label) . '</span>';
        if ('textarea' === $type) {
            echo '<textarea name="' . esc_attr($name) . '"' . $required . '></textarea>';
        } elseif ('privacy' === $type) {
            $notice = $text ? $text : $label;
            echo '<span class="mgd-giveaway-checkbox"><input type="checkbox" name="' . esc_attr($name) . '" value="1"' . $required . '> <span>' . esc_html($notice) . '</span></span>';
        } elseif ('checkbox' === $type) {
            echo '<input type="checkbox" name="' . esc_attr($name) . '" value="1"' . $required . '>';
        } else {
            echo '<input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '"' . $required . '>';
        }
        echo '</label>';
    }

    public function handle_frontend_submit()
    {
        $form_id = isset($_POST['form_id']) ? absint($_POST['form_id']) : 0;
        if (!$form_id || !check_admin_referer('mgd_giveaway_submit_' . $form_id)) {
            wp_die(esc_html__('Ungueltige Anfrage.', 'mgd-giveaway'));
        }

        if (!$this->passes_spam_check($form_id)) {
            wp_die(esc_html__('Die Anmeldung wurde aus Sicherheitsgruenden abgelehnt. Bitte lade die Seite neu und versuche es erneut.', 'mgd-giveaway'));
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
                wp_die(esc_html__('Bitte alle Pflichtfelder ausfuellen.', 'mgd-giveaway'));
            }

            if ('email' === $field['type']) {
                $value = sanitize_email($value);
                if (!empty($field['required']) && !is_email($value)) {
                    wp_die(esc_html__('Bitte eine gueltige E-Mail-Adresse eingeben.', 'mgd-giveaway'));
                }
                $email = $value;
            } elseif ('checkbox' === $field['type'] || 'privacy' === $field['type']) {
                $value = $value ? '1' : '0';
            } else {
                $value = sanitize_text_field($value);
            }

            $data[$name] = $value;
        }

        $download_url = $this->get_download_url((int) $config['download_attachment_id']);
        $submission_id = $this->store_submission($form_id, $email, $data);
        $this->add_log('info', 'form_submission', 'Neue Formularanmeldung gespeichert.', array('form_id' => $form_id, 'submission_id' => $submission_id, 'email' => $email));
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
            $this->add_log('warning', 'spam_rejected', 'Spam-Schutz: Honeypot ausgefuellt.', array('form_id' => $form_id));
            return false;
        }

        $token = isset($_POST['mgd_giveaway_started']) ? (string) wp_unslash($_POST['mgd_giveaway_started']) : '';
        $decoded = base64_decode($token, true);
        if (!$decoded || false === strpos($decoded, '|')) {
            $this->add_log('warning', 'spam_rejected', 'Spam-Schutz: Start-Token fehlt oder ist ungueltig.', array('form_id' => $form_id));
            return false;
        }

        list($timestamp, $hash) = explode('|', $decoded, 2);
        $expected = hash_hmac('sha256', (string) $timestamp, wp_salt('nonce'));
        if (!hash_equals($expected, $hash)) {
            $this->add_log('warning', 'spam_rejected', 'Spam-Schutz: Start-Token Signatur ungueltig.', array('form_id' => $form_id));
            return false;
        }

        $age = time() - (int) $timestamp;
        if ($age < $this->spam_min_seconds || $age > $this->spam_max_seconds) {
            $this->add_log('warning', 'spam_rejected', 'Spam-Schutz: Absendezeit ausserhalb des erlaubten Bereichs.', array('form_id' => $form_id, 'age' => $age));
            return false;
        }

        return true;
    }

    private function get_download_url($attachment_id)
    {
        if (!$attachment_id) {
            return '';
        }

        $url = wp_get_attachment_url($attachment_id);

        return $url ? $url : '';
    }

    private function store_submission($form_id, $email, $data)
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
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );

        return (int) $wpdb->insert_id;
    }

    private function send_download_email($email, $download_url, $config)
    {
        $settings = $this->get_settings();
        $subject = $config['email_subject'] ? $config['email_subject'] : 'Dein Download';
        $body = str_replace('{download_url}', $download_url, $config['email_body']);

        return $this->send_mail($email, $subject, $body);
    }

    private function send_notification_email($form_id, $email, $data)
    {
        $settings = $this->get_settings();
        $recipient = sanitize_email($settings['notification_recipient']);

        if (!$recipient) {
            $this->add_log('warning', 'notification_email_skipped', 'Keine Empfaengeradresse fuer neue Anmeldungen hinterlegt.', array('form_id' => $form_id, 'email' => $email));
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

        add_filter('wp_mail_from', function () use ($settings) {
            return $settings['from_email'];
        });
        add_filter('wp_mail_from_name', function () use ($settings) {
            return $settings['from_name'];
        });

        if ('smtp' === $settings['mail_method']) {
            add_action('phpmailer_init', function ($phpmailer) use ($settings) {
                $phpmailer->isSMTP();
                $phpmailer->Host = $settings['smtp_host'];
                $phpmailer->Port = (int) $settings['smtp_port'];
                $phpmailer->SMTPAuth = !empty($settings['smtp_username']);
                $phpmailer->Username = $settings['smtp_username'];
                $phpmailer->Password = $settings['smtp_password'];
                if ('none' !== $settings['smtp_encryption']) {
                    $phpmailer->SMTPSecure = $settings['smtp_encryption'];
                }
            });
        }

        return wp_mail($to, $subject, $body, $headers);
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
            'saved' => 'Gespeichert.',
            'deleted' => 'Geloescht.',
            'duplicated' => 'Dupliziert.',
            'imported' => 'CSV importiert.',
            'import_empty' => 'Keine CSV-Datei ausgewaehlt.',
            'import_invalid' => 'CSV-Datei konnte nicht importiert werden.',
            'logs_cleared' => 'Logs geleert.',
        );
        $notice = sanitize_key($_GET['mgd_notice']);

        if (isset($messages[$notice])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($messages[$notice]) . '</p></div>';
        }
    }
}
