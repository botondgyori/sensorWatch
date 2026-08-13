<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_readings extends CI_Migration {

    public function up()
    {
        $this->dbforge->add_field(array(
            'id' => array(
                'type'           => 'BIGINT',
                'unsigned'       => TRUE,
                'auto_increment' => TRUE
            ),
            'sensor_id' => array(
                'type'     => 'INT',
                'unsigned' => TRUE,
                'null'     => FALSE
            ),
            'recorded_at' => array(
                'type' => 'DATETIME',
                'null' => FALSE
            ),
            'value' => array(
                'type' => 'DOUBLE',
                'null' => FALSE
            ),
            'processed' => array(
                'type'     => 'TINYINT',
                'constraint' => 1,
                'null'     => FALSE,
                'default'  => 0
            ),
            'created_at' => array(
                'type'    => 'DATETIME',
                'null'    => FALSE
            )
        ));
        $this->dbforge->add_key('id', TRUE);
        $this->dbforge->create_table('readings', TRUE);

        if ($this->db->dbdriver === 'sqlite3') {
            $this->db->query('CREATE INDEX idx_sensor_recorded ON readings (sensor_id, recorded_at)');
            $this->db->query('CREATE INDEX idx_unprocessed ON readings (processed, sensor_id)');
        } else {
            $this->db->query('ALTER TABLE readings ADD CONSTRAINT fk_readings_sensor FOREIGN KEY (sensor_id) REFERENCES sensors(id)');
            $this->db->query('ALTER TABLE readings ADD INDEX idx_sensor_recorded (sensor_id, recorded_at)');
            $this->db->query('ALTER TABLE readings ADD INDEX idx_unprocessed (processed, sensor_id)');
        }
    }

    public function down()
    {
        $this->dbforge->drop_table('readings', TRUE);
    }
}
