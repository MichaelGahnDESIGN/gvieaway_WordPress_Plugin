<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/class-mgd-giveaway-plugin.php';
MGD_Giveaway_Plugin::uninstall();
