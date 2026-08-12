<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sensor_model extends CI_Model
{
    protected $table = 'sensors';

    public function get($id)
    {
        return $this->db->get_where($this->table, array('id' => $id))->row();
    }

    public function get_with_type($sensor_id)
    {
        return $this->db
            ->select('s.*, st.type_key, st.value_kind, st.unit, st.default_properties')
            ->from('sensors s')
            ->join('sensor_types st', 'st.id = s.sensor_type_id')
            ->where('s.id', $sensor_id)
            ->get()
            ->row();
    }

    public function get_for_tenant($sensor_id, $tenant_id)
    {
        return $this->db
            ->select('s.*')
            ->from('sensors s')
            ->join('devices d', 'd.id = s.device_id')
            ->where('s.id', $sensor_id)
            ->where('d.tenant_id', $tenant_id)
            ->get()
            ->row();
    }

    public function get_effective_properties($sensor_id)
    {
        $sensor = $this->get_with_type($sensor_id);
        if (!$sensor) {
            return array();
        }

        $type_props = json_decode($sensor->default_properties, true);
        $defaults = array();
        if (isset($type_props['schema'])) {
            foreach ($type_props['schema'] as $key => $def) {
                if (isset($def['default'])) {
                    $defaults[$key] = $def['default'];
                }
            }
        }

        $overrides = $sensor->properties ? json_decode($sensor->properties, true) : array();

        return array_merge($defaults, $overrides);
    }

    public function get_by_device($device_id)
    {
        return $this->db->get_where($this->table, array('device_id' => $device_id))->result();
    }

    public function get_all_with_type()
    {
        return $this->db
            ->select('s.*, st.type_key, st.value_kind, st.unit, st.default_properties')
            ->from('sensors s')
            ->join('sensor_types st', 'st.id = s.sensor_type_id')
            ->get()
            ->result();
    }

    public function insert($data)
    {
        if (!isset($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        if (isset($data['properties']) && is_array($data['properties'])) {
            $data['properties'] = json_encode($data['properties']);
        }
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }
}
