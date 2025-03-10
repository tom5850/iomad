<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

function xmldb_local_iomad_learningpath_upgrade($oldversion) {
    global $CFG, $DB;

    $result = true;
    $dbman = $DB->get_manager();

    // Add missing learning path id to group table.
    if ($oldversion < 2018043000) {

        // Define field learningpath to be added to iomad_learningpathgroup.
        $table = new xmldb_table('iomad_learningpathgroup');
        $field = new xmldb_field('learningpath', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null, 'id');

        // Conditionally launch add field learningpath.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define index ix_lp (not unique) to be added to iomad_learningpathgroup.
        $index = new xmldb_index('ix_lp', XMLDB_INDEX_NOTUNIQUE, array('learningpath'));

        // Conditionally launch add index ix_lp.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Iomad_learningpath savepoint reached.
        upgrade_plugin_savepoint(true, 2018043000, 'local', 'iomad_learningpath');
    }

    if ($oldversion < 2018043001) {

        // Find and delete orphaned entries in learningpath.
        $sql = 'SELECT lpc.id
                FROM {iomad_learningpathcourse} lpc
                LEFT OUTER JOIN {course} c ON c.id = lpc.course
                WHERE c.id IS NULL';
        $learningpathcourses = $DB->get_fieldset_sql($sql);
        if ($learningpathcourses) {
            list($sql, $params) = $DB->get_in_or_equal($learningpathcourses);
            $DB->delete_records_select('iomad_learningpathcourse', "id $sql", $params);
        }

        // Define key course (foreign) to be added to iomad_learningpathcourse.
        $table = new xmldb_table('iomad_learningpathcourse');
        $key = new xmldb_key('course', XMLDB_KEY_FOREIGN, array('course'), 'course', array('id'));

        // Launch add key course.
        $dbman->add_key($table, $key);

        // Define key group (foreign) to be added to iomad_learningpathcourse.
        $table = new xmldb_table('iomad_learningpathcourse');
        $key = new xmldb_key('group', XMLDB_KEY_FOREIGN, array('groupid'), 'iomad_learningpathgroup', array('id'));

        // Launch add key group.
        $dbman->add_key($table, $key);

        // Iomad_learningpath savepoint reached.
        upgrade_plugin_savepoint(true, 2018043001, 'local', 'iomad_learningpath');
    }

    if ($oldversion < 2019040500) {

        // Define field licenseid to be added to iomad_learningpath.
        $table = new xmldb_table('iomad_learningpath');
        $field = new xmldb_field('licenseid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, '0', 'timeupdated');

        // Conditionally launch add field licenseid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Iomad_learningpath savepoint reached.
        upgrade_plugin_savepoint(true, 2019040500, 'local', 'iomad_learningpath');
    }

    if ($oldversion < 2024090400) {

        // Define table competency_templatelearnpath to be created.
        $table = new xmldb_table('competency_templatelearnpath');

        // Adding fields to table competency_templatelearnpath.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('templateid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('learningpathid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table competency_templatelearnpath.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table competency_templatelearnpath.
        $table->add_index('comptemp_temlp_uix', XMLDB_INDEX_UNIQUE, ['templateid', 'learningpathid']);
        $table->add_index('comptemp_temlp2', XMLDB_INDEX_NOTUNIQUE, ['templateid']);
        $table->add_index('comptemp_uselp2', XMLDB_INDEX_NOTUNIQUE, ['usermodified']);

        // Conditionally launch create table for competency_templatelearnpath.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Iomad_learningpath savepoint reached.
        upgrade_plugin_savepoint(true, 2024090400, 'local', 'iomad_learningpath');
    }

    if ($oldversion < 2024121100){
        // Define index ix_com (not unique) to be dropped form iomad_learningpath.
        $table = new xmldb_table('iomad_learningpath');
        
        $index = new xmldb_index('ix_com', XMLDB_INDEX_NOTUNIQUE, ['company']);
        // Conditionally launch drop index ix_com.
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }
        
        $index = new xmldb_index('uix_comnam', XMLDB_INDEX_UNIQUE, ['company', 'name']);
        // Conditionally launch drop index uix_comnam.
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }
            
        $field = new xmldb_field('company', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id');
        // Launch change of type for field company.
        $dbman->change_field_type($table, $field);

        $index = new xmldb_index('ix_com', XMLDB_INDEX_NOTUNIQUE, ['company']);
        // Conditionally launch add index ix_com.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $index = new xmldb_index('uix_comnam', XMLDB_INDEX_UNIQUE, ['company', 'name']);
        // Conditionally launch add index uix_comnam.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Iomad_learningpath savepoint reached.
        upgrade_plugin_savepoint(true, 2024121100, 'local', 'iomad_learningpath');
    }
    
    return $result;
}
