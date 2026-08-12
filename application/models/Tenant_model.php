<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Tenant_model extends CI_Model
{
    protected $table = 'tenants';

    public function get($id)
    {
        return $this->db->get_where($this->table, array('id' => $id))->row();
    }

    public function get_by_api_key($api_key)
    {
        return $this->db->get_where($this->table, array('api_key' => $api_key))->row();
    }

    public function insert($data)
    {
        if (!isset($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    public function get_all()
    {
        return $this->db->get($this->table)->result();
    }
}
