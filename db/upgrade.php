<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_ailogin_upgrade($oldversion) {
    if ($oldversion < 2026071000) {
        upgrade_plugin_savepoint(true, 2026071000, 'local', 'ailogin');
    }
    return true;
}
