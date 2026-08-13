<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_users extends CI_Migration {

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
            'email' => array(
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => FALSE
            ),
            'name' => array(
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => FALSE
            ),
            'created_at' => array(
                'type'    => 'DATETIME',
                'null'    => FALSE
            )
        ));
        $this->dbforge->add_key('id', TRUE);
        $this->dbforge->create_table('users', TRUE);

        if ($this->db->dbdriver === 'sqlite3') {
            $this->db->query('CREATE UNIQUE INDEX idx_tenant_email ON users (tenant_id, email)');
        } else {
            $this->db->query('ALTER TABLE users ADD CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)');
            $this->db->query('ALTER TABLE users ADD UNIQUE INDEX idx_tenant_email (tenant_id, email)');
        }
    }

    public function down()
    {
        $this->dbforge->drop_table('users', TRUE);
    }
}
