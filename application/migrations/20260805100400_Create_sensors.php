<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_sensors extends CI_Migration {

    public function up()
    {
        $this->dbforge->add_field(array(
            'id' => array(
                'type'           => 'INT',
                'unsigned'       => TRUE,
                'auto_increment' => TRUE
            ),
            'device_id' => array(
                'type'     => 'INT',
                'unsigned' => TRUE,
                'null'     => FALSE
            ),
            'sensor_type_id' => array(
                'type'     => 'INT',
                'unsigned' => TRUE,
                'null'     => FALSE
            ),
            'display_name' => array(
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => FALSE
            ),
            'properties' => array(
                'type' => 'TEXT',
                'null' => FALSE
            ),
            'is_enabled' => array(
                'type'     => 'TINYINT',
                'constraint' => 1,
                'null'     => FALSE,
                'default'  => 1
            ),
            'created_at' => array(
                'type'    => 'DATETIME',
                'null'    => FALSE
            )
        ));
        $this->dbforge->add_key('id', TRUE);
        $this->dbforge->create_table('sensors', TRUE);

        if ($this->db->dbdriver !== 'sqlite3') {
            $this->db->query('ALTER TABLE sensors ADD CONSTRAINT fk_sensors_device FOREIGN KEY (device_id) REFERENCES devices(id)');
            $this->db->query('ALTER TABLE sensors ADD CONSTRAINT fk_sensors_type FOREIGN KEY (sensor_type_id) REFERENCES sensor_types(id)');
        }
    }

    public function down()
    {
        $this->dbforge->drop_table('sensors', TRUE);
    }
}
