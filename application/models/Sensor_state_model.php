<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sensor_state_model extends CI_Model
{
    protected $table = 'sensor_states';

    public function get_or_create($sensor_id)
    {
        $row = $this->db->get_where($this->table, array('sensor_id' => $sensor_id))->row();

        if (!$row) {
            $data = array(
                'sensor_id'          => $sensor_id,
                'current_state'      => 'normal',
                'pending_state'      => 'none',
                'pending_since'      => null,
                'last_transition_at' => null,
                'last_notified_at'   => null,
                'last_reading_id'    => null,
                'updated_at'         => date('Y-m-d H:i:s'),
            );
            $this->db->insert($this->table, $data);
            $row = $this->db->get_where($this->table, array('sensor_id' => $sensor_id))->row();
        }

        return $row;
    }

    public function update_state($sensor_id, $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('sensor_id', $sensor_id)->update($this->table, $data);
    }

    public function get_by_sensor($sensor_id)
    {
        return $this->db->get_where($this->table, array('sensor_id' => $sensor_id))->row();
    }

    public function get_all_with_details()
    {
        return $this->db
            ->select('ss.*, s.display_name, s.properties as sensor_properties, st.type_key, st.value_kind, st.unit, d.name as device_name, d.tenant_id')
            ->from('sensor_states ss')
            ->join('sensors s', 's.id = ss.sensor_id')
            ->join('sensor_types st', 'st.id = s.sensor_type_id')
            ->join('devices d', 'd.id = s.device_id')
            ->order_by('ss.sensor_id', 'ASC')
            ->get()
            ->result();
    }
}
