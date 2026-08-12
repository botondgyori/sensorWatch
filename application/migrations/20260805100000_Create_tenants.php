<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_tenants extends CI_Migration {

    public function up()
    {
        $this->dbforge->add_field(array(
            'id' => array(
                'type'           => 'INT',
                'unsigned'       => TRUE,
                'auto_increment' => TRUE
            ),
            'name' => array(
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => FALSE
            ),
            'api_key' => array(
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => FALSE
            ),
            'created_at' => array(
                'type'    => 'DATETIME',
                'null'    => FALSE
            )
        ));
        $this->dbforge->add_key('id', TRUE);
        $this->dbforge->create_table('tenants', TRUE);

        $this->db->query('ALTER TABLE tenants ADD UNIQUE INDEX idx_api_key (api_key)');
    }

    public function down()
    {
        $this->dbforge->drop_table('tenants', TRUE);
    }
}
