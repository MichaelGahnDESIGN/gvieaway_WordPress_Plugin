<?php

if (!defined('ABSPATH')) {
    exit;
}

class MGD_Giveaway_Plugin
{
    private static $instance = null;
    private $post_type = 'mgd_giveaway_form';
    private $submission_table;

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

        add_action('init', array($this, 'register_post_type'));
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_shortcode('mgd_giveaway', array($this, 'render_shortcode'));

        add_action('admin_post_mgd_giveaway_save_form', array($this, 'handle_save_form'));
        add_action('admin_post_mgd_giveaway_delete_form', array($this, 'handle_delete_form'));
        add_action('admin_post_mgd_giveaway_duplicate_form', array($this, 'handle_duplicate_form'));
        add_action('admin_post_mgd_giveaway_save_settings', array($this, 'handle_save_settings'));
        add_action('admin_post_nopriv_mgd_giveaway_submit', array($this, 'handle_frontend_submit'));
        add_action('admin_post_mgd_giveaway_submit', array($this, 'handle_frontend_submit'));
    }

    public static function activate()
    {
        self::create_submission_table();
        flush_rewrite_rules();
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

        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mgd_giveaway_submissions");
    }

    private static function create_submission_table()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mgd_giveaway_submissions';
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table_name} (
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

        dbDelta($sql);
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

        echo '<section class="mgd-panel"><h2>Felder</h2>';
        echo '<div id="mgd-fields" data-next-index="' . esc_attr((string) count($config['fields'])) . '">';
        foreach ($config['fields'] as $index => $field) {
            $this->render_field_editor_row($index, $field);
        }
        echo '</div><button type="button" class="button mgd-add-field">Feld hinzufuegen</button>';
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
        $types = array('text' => 'Text', 'email' => 'E-Mail', 'number' => 'Zahl', 'date' => 'Datum', 'checkbox' => 'Checkbox', 'textarea' => 'Mehrzeilig');
        $label = isset($field['label']) ? $field['label'] : '';
        $name = isset($field['name']) ? $field['name'] : '';
        $type = isset($field['type']) ? $field['type'] : 'text';
        $required = !empty($field['required']);

        echo '<div class="mgd-field-row">';
        echo '<input type="text" name="fields[' . esc_attr((string) $index) . '][label]" value="' . esc_attr($label) . '" placeholder="Label">';
        echo '<input type="text" name="fields[' . esc_attr((string) $index) . '][name]" value="' . esc_attr($name) . '" placeholder="feldname">';
        echo '<select name="fields[' . esc_attr((string) $index) . '][type]">';
        foreach ($types as $value => $title) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($type, $value, false) . '>' . esc_html($title) . '</option>';
        }
        echo '</select>';
        echo '<label><input type="checkbox" name="fields[' . esc_attr((string) $index) . '][required]" value="1" ' . checked($required, true, false) . '> Pflicht</label>';
        echo '<button type="button" class="button mgd-remove-field">Entfernen</button>';
        echo '</div>';
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
        echo '<label class="mgd-field"><span>SMTP Host</span><input type="text" name="smtp_host" value="' . esc_attr($settings['smtp_host']) . '"></label>';
        echo '<label class="mgd-field"><span>SMTP Port</span><input type="number" name="smtp_port" value="' . esc_attr($settings['smtp_port']) . '"></label>';
        echo '<label class="mgd-field"><span>Verschluesselung</span><select name="smtp_encryption"><option value="none" ' . selected($settings['smtp_encryption'], 'none', false) . '>Keine</option><option value="tls" ' . selected($settings['smtp_encryption'], 'tls', false) . '>TLS</option><option value="ssl" ' . selected($settings['smtp_encryption'], 'ssl', false) . '>SSL</option></select></label>';
        echo '<label class="mgd-field"><span>SMTP Benutzer</span><input type="text" name="smtp_username" value="' . esc_attr($settings['smtp_username']) . '"></label>';
        echo '<label class="mgd-field"><span>SMTP Passwort</span><input type="password" name="smtp_password" value="' . esc_attr($settings['smtp_password']) . '"></label>';
        echo '<p><button class="button button-primary" type="submit">Speichern</button></p>';
        echo '</form></div>';
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

        wp_safe_redirect(admin_url('admin.php?page=mgd-giveaway-form&form_id=' . (int) $form_id . '&mgd_notice=saved'));
        exit;
    }

    private function sanitize_fields($raw_fields)
    {
        $allowed_types = array('text', 'email', 'number', 'date', 'checkbox', 'textarea');
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
            'smtp_host' => isset($_POST['smtp_host']) ? sanitize_text_field(wp_unslash($_POST['smtp_host'])) : '',
            'smtp_port' => isset($_POST['smtp_port']) ? absint($_POST['smtp_port']) : 587,
            'smtp_encryption' => isset($_POST['smtp_encryption']) && in_array($_POST['smtp_encryption'], array('none', 'tls', 'ssl'), true) ? sanitize_key($_POST['smtp_encryption']) : 'tls',
            'smtp_username' => isset($_POST['smtp_username']) ? sanitize_text_field(wp_unslash($_POST['smtp_username'])) : '',
            'smtp_password' => isset($_POST['smtp_password']) ? sanitize_text_field(wp_unslash($_POST['smtp_password'])) : '',
        );

        update_option('mgd_giveaway_settings', $settings, false);
        wp_safe_redirect(admin_url('admin.php?page=mgd-giveaway-settings&mgd_notice=saved'));
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
        echo '<form class="mgd-giveaway-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="mgd_giveaway_submit">';
        echo '<input type="hidden" name="form_id" value="' . esc_attr((string) $form_id) . '">';
        wp_nonce_field('mgd_giveaway_submit_' . $form_id);

        foreach ($config['fields'] as $field) {
            $this->render_frontend_field($field);
        }

        echo '<button type="submit">' . esc_html($config['button_label']) . '</button>';
        echo '</form>';

        return ob_get_clean();
    }

    private function render_frontend_field($field)
    {
        $name = isset($field['name']) ? sanitize_key($field['name']) : '';
        $label = isset($field['label']) ? $field['label'] : $name;
        $type = isset($field['type']) ? $field['type'] : 'text';
        $required = !empty($field['required']) ? ' required' : '';

        echo '<label class="mgd-giveaway-field"><span>' . esc_html($label) . '</span>';
        if ('textarea' === $type) {
            echo '<textarea name="' . esc_attr($name) . '"' . $required . '></textarea>';
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
                $email = $value;
            } elseif ('checkbox' === $field['type']) {
                $value = $value ? '1' : '0';
            } else {
                $value = sanitize_text_field($value);
            }

            $data[$name] = $value;
        }

        $download_url = $this->get_download_url((int) $config['download_attachment_id']);
        $this->store_submission($form_id, $email, $data);

        if (!empty($config['send_email']) && $email && $download_url) {
            $this->send_download_email($email, $download_url, $config);
        }

        echo '<!doctype html><html><head><meta charset="' . esc_attr(get_bloginfo('charset')) . '"><meta name="viewport" content="width=device-width, initial-scale=1">';
        wp_head();
        echo '</head><body class="mgd-giveaway-result"><main class="mgd-giveaway-success">';
        echo '<p>' . esc_html($config['success_message']) . '</p>';
        if ($download_url) {
            echo '<a class="mgd-giveaway-download" href="' . esc_url($download_url) . '" download>' . esc_html($config['button_label']) . '</a>';
        } else {
            echo '<p>Es wurde noch keine Download-Datei hinterlegt.</p>';
        }
        echo '</main>';
        wp_footer();
        echo '</body></html>';
        exit;
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
    }

    private function send_download_email($email, $download_url, $config)
    {
        $settings = $this->get_settings();
        $subject = $config['email_subject'] ? $config['email_subject'] : 'Dein Download';
        $body = str_replace('{download_url}', $download_url, $config['email_body']);
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

        wp_mail($email, $subject, $body, $headers);
    }

    private function count_submissions($form_id = 0)
    {
        global $wpdb;
        if ($form_id) {
            return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->submission_table} WHERE form_id = %d", $form_id));
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->submission_table}");
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
        );
        $notice = sanitize_key($_GET['mgd_notice']);

        if (isset($messages[$notice])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($messages[$notice]) . '</p></div>';
        }
    }
}
