<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Device_model extends CI_Model
{
    protected $table = 'devices';

    public function get($id)
    {
        return $this->db->get_where($this->table, array('id' => $id))->row();
    }

    public function get_by_tenant($tenant_id)
    {
        return $this->db->get_where($this->table, array('tenant_id' => $tenant_id))->result();
    }

    public function insert($data)
    {
        if (!isset($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }
}
