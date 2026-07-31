<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_ailogin_admin',
        get_string('pluginname', 'local_ailogin'),
        new moodle_url('/local/ailogin/admin.php'),
        'moodle/site:config'
    ));
}
