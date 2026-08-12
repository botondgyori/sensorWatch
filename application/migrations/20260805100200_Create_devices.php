<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_devices extends CI_Migration {

    public function up()
    {
        $this->dbforge->add_field(array(
            'id' => array(
                'type'           => 'INT',
                'unsigned'       => TRUE,
                'auto_increment' => TRUE
            ),
            'tenant_id' => array(
                'type'     => 'INT',
                'unsigned' => TRUE,
                'null'     => FALSE
            ),
            'name' => array(
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => FALSE
            ),
            'location' => array(
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => TRUE
            ),
            'created_at' => array(
                'type'    => 'DATETIME',
                'null'    => FALSE
            )
        ));
        $this->dbforge->add_key('id', TRUE);
        $this->dbforge->create_table('devices', TRUE);

        $this->db->query('ALTER TABLE devices ADD CONSTRAINT fk_devices_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)');
    }

    public function down()
    {
        $this->dbforge->drop_table('devices', TRUE);
    }
}
