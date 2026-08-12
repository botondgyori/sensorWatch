<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Reading_model extends CI_Model
{
    protected $table = 'readings';

    public function insert($data)
    {
        if (!isset($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    public function insert_batch($rows)
    {
        $now = date('Y-m-d H:i:s');
        foreach ($rows as &$row) {
            if (!isset($row['created_at'])) {
                $row['created_at'] = $now;
            }
        }
        return $this->db->insert_batch($this->table, $rows);
    }

    public function get_unprocessed_grouped()
    {
        $rows = $this->db
            ->where('processed', 0)
            ->order_by('sensor_id', 'ASC')
            ->order_by('recorded_at', 'ASC')
            ->get($this->table)
            ->result_array();

        $grouped = array();
        foreach ($rows as $row) {
            $sid = $row['sensor_id'];
            if (!isset($grouped[$sid])) {
                $grouped[$sid] = array();
            }
            $grouped[$sid][] = $row;
        }

        return $grouped;
    }

    public function mark_processed($ids)
    {
        if (empty($ids)) return;

        $this->db
            ->where_in('id', $ids)
            ->update($this->table, array('processed' => 1));
    }
    
    public function get_by_sensor($sensor_id, $limit = 100)
    {
        return $this->db
            ->where('sensor_id', $sensor_id)
            ->order_by('recorded_at', 'ASC')
            ->limit($limit)
            ->get($this->table)
            ->result();
    }
}
