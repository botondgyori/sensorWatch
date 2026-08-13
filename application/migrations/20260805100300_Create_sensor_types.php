<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_sensor_types extends CI_Migration {

    public function up()
    {
        $this->dbforge->add_field(array(
            'id' => array(
                'type'           => 'INT',
                'unsigned'       => TRUE,
                'auto_increment' => TRUE
            ),
            'type_key' => array(
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => FALSE
            ),
            'value_kind' => array(
                'type'       => 'ENUM',
                'constraint' => array('numeric', 'boolean'),
                'null'       => FALSE
            ),
            'unit' => array(
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => TRUE
            ),
            'description' => array(
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => TRUE
            ),
            'property_schema' => array(
                'type' => 'TEXT',
                'null' => FALSE
            ),
            'default_properties' => array(
                'type' => 'TEXT',
                'null' => FALSE
            ),
            'created_at' => array(
                'type'    => 'DATETIME',
                'null'    => FALSE
            )
        ));
        $this->dbforge->add_key('id', TRUE);
        $this->dbforge->create_table('sensor_types', TRUE);

        if ($this->db->dbdriver === 'sqlite3') {
            $this->db->query('CREATE UNIQUE INDEX idx_type_key ON sensor_types (type_key)');
        } else {
            $this->db->query('ALTER TABLE sensor_types ADD UNIQUE INDEX idx_type_key (type_key)');
        }
    }

    public function down()
    {
        $this->dbforge->drop_table('sensor_types', TRUE);
    }
}
