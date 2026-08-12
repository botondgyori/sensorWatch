<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_sensor_states extends CI_Migration {

    public function up()
    {
        $this->dbforge->add_field(array(
            'id' => array(
                'type'           => 'INT',
                'unsigned'       => TRUE,
                'auto_increment' => TRUE
            ),
            'sensor_id' => array(
                'type'     => 'INT',
                'unsigned' => TRUE,
                'null'     => FALSE
            ),
            'current_state' => array(
                'type'       => 'ENUM',
                'constraint' => array('normal', 'alert'),
                'null'       => FALSE,
                'default'    => 'normal'
            ),
            'pending_state' => array(
                'type'       => 'ENUM',
                'constraint' => array('none', 'pending_alert', 'pending_clear'),
                'null'       => FALSE,
                'default'    => 'none'
            ),
            'pending_since' => array(
                'type' => 'DATETIME',
                'null' => TRUE
            ),
            'last_transition_at' => array(
                'type' => 'DATETIME',
                'null' => TRUE
            ),
            'last_notified_at' => array(
                'type' => 'DATETIME',
                'null' => TRUE
            ),
            'last_reading_id' => array(
                'type'     => 'BIGINT',
                'unsigned' => TRUE,
                'null'     => TRUE
            ),
            'updated_at' => array(
                'type'    => 'DATETIME',
                'null'    => FALSE
            )
        ));
        $this->dbforge->add_key('id', TRUE);
        $this->dbforge->create_table('sensor_states', TRUE);

        $this->db->query('ALTER TABLE sensor_states ADD CONSTRAINT fk_states_sensor FOREIGN KEY (sensor_id) REFERENCES sensors(id)');
        $this->db->query('ALTER TABLE sensor_states ADD UNIQUE INDEX idx_sensor_unique (sensor_id)');
    }

    public function down()
    {
        $this->dbforge->drop_table('sensor_states', TRUE);
    }
}
