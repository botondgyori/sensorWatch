<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sensor_type_model extends CI_Model
{
    protected $table = 'sensor_types';

    public function get($id)
    {
        return $this->db->get_where($this->table, array('id' => $id))->row();
    }

    public function get_by_key($type_key)
    {
        return $this->db->get_where($this->table, array('type_key' => $type_key))->row();
    }

    public function insert($data)
    {
        if (!isset($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        // Encode JSON fields
        if (isset($data['default_properties']) && is_array($data['default_properties'])) {
            $data['default_properties'] = json_encode($data['default_properties']);
        }
        if (isset($data['property_schema']) && is_array($data['property_schema'])) {
            $data['property_schema'] = json_encode($data['property_schema']);
        }
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    public function get_all()
    {
        return $this->db->get($this->table)->result();
    }
}
