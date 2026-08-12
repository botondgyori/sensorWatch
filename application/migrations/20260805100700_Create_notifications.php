<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_notifications extends CI_Migration {

    public function up()
    {
        $this->dbforge->add_field(array(
            'id' => array(
                'type'           => 'INT',
                'unsigned'       => TRUE,
                'auto_increment' => TRUE
            ),
            'user_id' => array(
                'type'     => 'INT',
                'unsigned' => TRUE,
                'null'     => FALSE
            ),
            'sensor_id' => array(
                'type'     => 'INT',
                'unsigned' => TRUE,
                'null'     => FALSE
            ),
            'direction' => array(
                'type'       => 'ENUM',
                'constraint' => array('alert', 'clear'),
                'null'       => FALSE
            ),
            'message' => array(
                'type' => 'TEXT',
                'null' => FALSE
            ),
            'reading_value' => array(
                'type' => 'DOUBLE',
                'null' => TRUE
            ),
            'created_at' => array(
                'type'    => 'DATETIME',
                'null'    => FALSE
            )
        ));
        $this->dbforge->add_key('id', TRUE);
        $this->dbforge->create_table('notifications', TRUE);

        $this->db->query('ALTER TABLE notifications ADD CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id)');
        $this->db->query('ALTER TABLE notifications ADD CONSTRAINT fk_notif_sensor FOREIGN KEY (sensor_id) REFERENCES sensors(id)');
        $this->db->query('ALTER TABLE notifications ADD INDEX idx_user (user_id)');
        $this->db->query('ALTER TABLE notifications ADD INDEX idx_sensor_direction (sensor_id, direction, created_at)');
    }

    public function down()
    {
        $this->dbforge->drop_table('notifications', TRUE);
    }
}
