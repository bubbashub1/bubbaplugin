<?php
/**
 * Plugin Name: Bubba Hub Manager MU Loader
 * Description: Loads the existing Bubba Hub Manager within the MU-plugin system.
 * Version: 0.1.0
 */
if (!defined('ABSPATH')) exit;
if (defined('BH_MANAGER_LOADED')) return;
if (class_exists('Bubba_Hub_Manager', false)) return;
define('BH_MANAGER_LOADED', true);
require_once __DIR__ . '/bubbahub-manager/bubba-hub-manager.php';
