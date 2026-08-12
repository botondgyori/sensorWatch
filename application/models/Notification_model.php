<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Notification_model extends CI_Model
{
    protected $table = 'notifications';

    public function insert($data)
    {
        if (!isset($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    public function get_by_user($user_id, $limit = 50, $offset = 0)
    {
        return $this->db
            ->select('n.*, s.display_name as sensor_name, st.type_key, d.name as device_name')
            ->from('notifications n')
            ->join('sensors s', 's.id = n.sensor_id')
            ->join('sensor_types st', 'st.id = s.sensor_type_id')
            ->join('devices d', 'd.id = s.device_id')
            ->where('n.user_id', $user_id)
            ->order_by('n.created_at', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->result();
    }

    public function user_belongs_to_tenant($user_id, $tenant_id)
    {
        $this->load->model('user_model');
        $user = $this->user_model->get($user_id);
        return $user && ((int) $user->tenant_id === (int) $tenant_id);
    }

    public function get_all_with_details()
    {
        return $this->db
            ->select('n.*, u.name as user_name, u.email, s.display_name as sensor_name, st.type_key, d.name as device_name, t.name as tenant_name')
            ->from('notifications n')
            ->join('users u', 'u.id = n.user_id')
            ->join('sensors s', 's.id = n.sensor_id')
            ->join('sensor_types st', 'st.id = s.sensor_type_id')
            ->join('devices d', 'd.id = s.device_id')
            ->join('tenants t', 't.id = d.tenant_id')
            ->order_by('n.created_at', 'ASC')
            ->get()
            ->result();
    }
}
