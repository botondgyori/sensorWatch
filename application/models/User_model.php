<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User_model extends CI_Model
{
    protected $table = 'users';

    public function get($id)
    {
        return $this->db->get_where($this->table, array('id' => $id))->row();
    }

    public function get_by_tenant($tenant_id)
    {
        return $this->db->get_where($this->table, array('tenant_id' => $tenant_id))->result();
    }

    public function get_users_for_sensor($sensor_id)
    {
        return $this->db
            ->select('u.*')
            ->from('users u')
            ->join('devices d', 'd.tenant_id = u.tenant_id')
            ->join('sensors s', 's.device_id = d.id')
            ->where('s.id', $sensor_id)
            ->group_by('u.id')
            ->get()
            ->result();
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
