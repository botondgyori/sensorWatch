<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Test extends MY_Controller {

    protected $results = array();
    protected $pass_count = 0;
    protected $fail_count = 0;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Sensor_state_model');
        $this->load->model('Notification_model');
        $this->load->model('User_model');
        $this->load->model('Sensor_model');
        $this->load->model('Reading_model');
        $this->load->model('Tenant_model');
        $this->load->model('Device_model');
        $this->load->model('Sensor_type_model');
    }

    // Bypass tenant requirement for the test runner itself
    protected function require_tenant()
    {
        return TRUE;
    }

    public function run()
    {
        // 1. Reset database
        $this->load->library('migration');
        $this->migration->version(0);
        $this->migration->latest();

        // 2. Run seed
        $this->run_seed();

        // 3. Run processor
        $this->load->library('ReadingProcessor');
        $process_stats = $this->readingprocessor->process_all();

        // 4. Run assertions
        $this->test_s1_spike_below_dwell();
        $this->test_s2_sustained_breach();
        $this->test_s3_chatter_dead_band();
        $this->test_s4_clear_hysteresis();
        $this->test_s5_cooldown_suppress();
        $this->test_s6_boolean_door();
        $this->test_s7_multi_tenant_isolation();

        $this->json_response(array(
            'status'         => ($this->fail_count === 0) ? 'ok' : 'fail',
            'summary'        => "{$this->pass_count} passed, {$this->fail_count} failed",
            'process_stats'  => $process_stats,
            'results'        => $this->results
        ));
    }

    protected function run_seed()
    {
        $t1 = $this->Tenant_model->insert(array('name' => 'Botond Solutions',   'api_key' => 'botond-key-001'));
        $t2 = $this->Tenant_model->insert(array('name' => 'Csongor Logistics',  'api_key' => 'csongor-key-002'));

        $this->User_model->insert(array('tenant_id' => $t1, 'email' => 'attila@botond.com',  'name' => 'Attila'));
        $this->User_model->insert(array('tenant_id' => $t1, 'email' => 'boti@botond.com',    'name' => 'Boti'));
        $this->User_model->insert(array('tenant_id' => $t2, 'email' => 'csongi@csongor.com', 'name' => 'Csongi'));

        $st_temp = $this->Sensor_type_model->insert(array(
            'type_key' => 'temperature', 'value_kind' => 'numeric', 'unit' => '°C',
            'description' => 'Temperature sensor',
            'property_schema' => array('warnAbove' => array('type' => 'number'), 'clearBelow' => array('type' => 'number'), 'dwellSeconds' => array('type' => 'integer'), 'cooldownSeconds' => array('type' => 'integer')),
            'default_properties' => array('warnAbove' => 30, 'clearBelow' => 28, 'dwellSeconds' => 60, 'cooldownSeconds' => 300)
        ));
        $st_hum = $this->Sensor_type_model->insert(array(
            'type_key' => 'humidity', 'value_kind' => 'numeric', 'unit' => '%RH',
            'description' => 'Humidity sensor',
            'property_schema' => array('warnAbove' => array('type' => 'number'), 'clearBelow' => array('type' => 'number'), 'dwellSeconds' => array('type' => 'integer'), 'cooldownSeconds' => array('type' => 'integer')),
            'default_properties' => array('warnAbove' => 70, 'clearBelow' => 65, 'dwellSeconds' => 120, 'cooldownSeconds' => 600)
        ));
        $st_vib = $this->Sensor_type_model->insert(array(
            'type_key' => 'vibration', 'value_kind' => 'numeric', 'unit' => 'mm/s',
            'description' => 'Vibration sensor',
            'property_schema' => array('warnAbove' => array('type' => 'number'), 'clearBelow' => array('type' => 'number'), 'dwellSeconds' => array('type' => 'integer'), 'cooldownSeconds' => array('type' => 'integer')),
            'default_properties' => array('warnAbove' => 10, 'clearBelow' => 8, 'dwellSeconds' => 30, 'cooldownSeconds' => 180)
        ));
        $st_door = $this->Sensor_type_model->insert(array(
            'type_key' => 'door_contact', 'value_kind' => 'boolean', 'unit' => 'open/closed',
            'description' => 'Door contact sensor',
            'property_schema' => array('alertWhen' => array('type' => 'boolean'), 'dwellSeconds' => array('type' => 'integer'), 'cooldownSeconds' => array('type' => 'integer')),
            'default_properties' => array('alertWhen' => true, 'dwellSeconds' => 30, 'cooldownSeconds' => 120)
        ));


        $d1 = $this->Device_model->insert(array('tenant_id' => $t1, 'name' => 'Cold Room', 'location' => 'Building 1, Floor B1'));
        $d2 = $this->Device_model->insert(array('tenant_id' => $t1, 'name' => 'Warehouse', 'location' => 'Building 2, Main Hall'));
        $d3 = $this->Device_model->insert(array('tenant_id' => $t2, 'name' => 'Truck Fleet Hub', 'location' => 'Distribution Center'));

        $this->Sensor_model->insert(array('device_id' => $d1, 'sensor_type_id' => $st_temp, 'display_name' => 'Freezer Temp', 'properties' => array()));
        $this->Sensor_model->insert(array('device_id' => $d1, 'sensor_type_id' => $st_temp, 'display_name' => 'Cold Room Temp', 'properties' => array('cooldownSeconds' => 1200)));
        $this->Sensor_model->insert(array('device_id' => $d1, 'sensor_type_id' => $st_hum, 'display_name' => 'Cold Room Humidity', 'properties' => array('dwellSeconds' => 60)));
        $this->Sensor_model->insert(array('device_id' => $d2, 'sensor_type_id' => $st_vib, 'display_name' => 'Warehouse Vibration', 'properties' => array('warnAbove' => 12, 'clearBelow' => 9, 'dwellSeconds' => 15)));
        $this->Sensor_model->insert(array('device_id' => $d2, 'sensor_type_id' => $st_door, 'display_name' => 'Warehouse Door', 'properties' => array('dwellSeconds' => 20)));
        $this->Sensor_model->insert(array('device_id' => $d3, 'sensor_type_id' => $st_temp, 'display_name' => 'Fleet Temp', 'properties' => array('warnAbove' => 35, 'clearBelow' => 32)));

        $this->Reading_model->insert_batch(array(
            array('sensor_id' => 1, 'recorded_at' => '2026-08-10 10:00:00', 'value' => 25.0),
            array('sensor_id' => 1, 'recorded_at' => '2026-08-10 10:01:00', 'value' => 31.0),
            array('sensor_id' => 1, 'recorded_at' => '2026-08-10 10:01:30', 'value' => 32.0),
            array('sensor_id' => 1, 'recorded_at' => '2026-08-10 10:01:45', 'value' => 29.0),
            array('sensor_id' => 1, 'recorded_at' => '2026-08-10 10:02:00', 'value' => 27.0),
        ));

        $this->Reading_model->insert_batch(array(
            array('sensor_id' => 2, 'recorded_at' => '2026-08-10 10:05:00', 'value' => 25.0),
            array('sensor_id' => 2, 'recorded_at' => '2026-08-10 10:06:00', 'value' => 31.0),
            array('sensor_id' => 2, 'recorded_at' => '2026-08-10 10:06:30', 'value' => 33.0),
            array('sensor_id' => 2, 'recorded_at' => '2026-08-10 10:07:00', 'value' => 32.0),
            array('sensor_id' => 2, 'recorded_at' => '2026-08-10 10:07:01', 'value' => 31.0),
        ));

        $this->Reading_model->insert_batch(array(
            array('sensor_id' => 2, 'recorded_at' => '2026-08-10 10:10:00', 'value' => 29.0),
            array('sensor_id' => 2, 'recorded_at' => '2026-08-10 10:10:30', 'value' => 27.0),
            array('sensor_id' => 2, 'recorded_at' => '2026-08-10 10:11:00', 'value' => 29.0),
            array('sensor_id' => 2, 'recorded_at' => '2026-08-10 10:11:30', 'value' => 27.0),
            array('sensor_id' => 2, 'recorded_at' => '2026-08-10 10:12:00', 'value' => 29.0),
            array('sensor_id' => 2, 'recorded_at' => '2026-08-10 10:12:30', 'value' => 27.0),
            array('sensor_id' => 2, 'recorded_at' => '2026-08-10 10:12:50', 'value' => 29.0),
        ));

        $this->Reading_model->insert_batch(array(
            array('sensor_id' => 2, 'recorded_at' => '2026-08-10 10:15:00', 'value' => 31.0),
            array('sensor_id' => 2, 'recorded_at' => '2026-08-10 10:16:00', 'value' => 29.0),
            array('sensor_id' => 2, 'recorded_at' => '2026-08-10 10:17:00', 'value' => 27.0),
            array('sensor_id' => 2, 'recorded_at' => '2026-08-10 10:17:30', 'value' => 26.0),
            array('sensor_id' => 2, 'recorded_at' => '2026-08-10 10:18:00', 'value' => 27.0),
        ));

        $this->Reading_model->insert_batch(array(
            array('sensor_id' => 2, 'recorded_at' => '2026-08-10 10:20:00', 'value' => 25.0),
            array('sensor_id' => 2, 'recorded_at' => '2026-08-10 10:21:00', 'value' => 31.0),
            array('sensor_id' => 2, 'recorded_at' => '2026-08-10 10:21:30', 'value' => 32.0),
            array('sensor_id' => 2, 'recorded_at' => '2026-08-10 10:22:00', 'value' => 33.0),
        ));

        $this->Reading_model->insert_batch(array(
            array('sensor_id' => 5, 'recorded_at' => '2026-08-10 10:00:00', 'value' => 0),
            array('sensor_id' => 5, 'recorded_at' => '2026-08-10 10:01:00', 'value' => 1),
            array('sensor_id' => 5, 'recorded_at' => '2026-08-10 10:01:10', 'value' => 1),
            array('sensor_id' => 5, 'recorded_at' => '2026-08-10 10:01:15', 'value' => 0),
            array('sensor_id' => 5, 'recorded_at' => '2026-08-10 10:02:00', 'value' => 1),
            array('sensor_id' => 5, 'recorded_at' => '2026-08-10 10:02:10', 'value' => 1),
            array('sensor_id' => 5, 'recorded_at' => '2026-08-10 10:02:20', 'value' => 1),
        ));

        $this->Reading_model->insert_batch(array(
            array('sensor_id' => 6, 'recorded_at' => '2026-08-10 10:00:00', 'value' => 30.0),
            array('sensor_id' => 6, 'recorded_at' => '2026-08-10 10:01:00', 'value' => 36.0),
            array('sensor_id' => 6, 'recorded_at' => '2026-08-10 10:01:30', 'value' => 37.0),
            array('sensor_id' => 6, 'recorded_at' => '2026-08-10 10:02:00', 'value' => 38.0),
        ));
    }

    protected function assert($test_name, $condition, $detail = '')
    {
        if ($condition) {
            $this->pass_count++;
            $this->results[] = array('test' => $test_name, 'passed' => true);
        } else {
            $this->fail_count++;
            $this->results[] = array('test' => $test_name, 'passed' => false, 'detail' => $detail);
        }
    }

    protected function test_s1_spike_below_dwell()
    {
        $this->db->where('sensor_id', 1);
        $this->db->where('direction', 'alert');
        $this->db->where('created_at >=', '2026-08-10 10:00:00');
        $this->db->where('created_at <=', '2026-08-10 10:04:59');
        $spike_notifs = $this->db->get('notifications')->num_rows();

        $this->assert('S1: No alert notifications during spike', $spike_notifs === 0, "Got {$spike_notifs}");
    }

    protected function test_s2_sustained_breach()
    {
        $this->db->where('sensor_id', 2);
        $this->db->where('direction', 'alert');
        $this->db->where('created_at >=', '2026-08-10 10:05:00');
        $this->db->where('created_at <=', '2026-08-10 10:09:59');
        $alert_notifs = $this->db->get('notifications')->result();

        $this->assert('S2: Alert notifications for sustained breach', count($alert_notifs) === 2, "Got " . count($alert_notifs));
    }

    protected function test_s3_chatter_dead_band()
    {
        $this->db->where('sensor_id', 2);
        $this->db->where('direction', 'clear');
        $this->db->where('created_at >=', '2026-08-10 10:10:00');
        $this->db->where('created_at <=', '2026-08-10 10:14:59');
        $clear_notifs = $this->db->get('notifications')->num_rows();

        $this->assert('S3: No clear notification during chatter', $clear_notifs === 0, "Got {$clear_notifs}");
    }

    protected function test_s4_clear_hysteresis()
    {
        $this->db->where('sensor_id', 2);
        $this->db->where('direction', 'clear');
        $this->db->where('created_at >=', '2026-08-10 10:15:00');
        $this->db->where('created_at <=', '2026-08-10 10:19:59');
        $clear_notifs = $this->db->get('notifications')->result();

        $this->assert('S4: Clear notifications created', count($clear_notifs) === 2, "Got " . count($clear_notifs));
    }

    protected function test_s5_cooldown_suppress()
    {
        $state = $this->Sensor_state_model->get_by_sensor(2);
        $this->assert('S5: Final state is alert', $state && $state->current_state === 'alert');

        $this->db->where('sensor_id', 2);
        $this->db->where('direction', 'alert');
        $this->db->where('created_at >=', '2026-08-10 10:20:00');
        $this->db->where('created_at <=', '2026-08-10 10:24:59');
        $suppressed_notifs = $this->db->get('notifications')->num_rows();

        $this->assert('S5: Alert notification suppressed', $suppressed_notifs === 0, "Got {$suppressed_notifs}");
    }

    protected function test_s6_boolean_door()
    {
        $state = $this->Sensor_state_model->get_by_sensor(5);
        $this->assert('S6: Door sensor final state is alert', $state && $state->current_state === 'alert');

        $this->db->where('sensor_id', 5);
        $this->db->where('direction', 'alert');
        $door_notifs = $this->db->get('notifications')->result();
        $this->assert('S6: Door alert notifications created', count($door_notifs) === 2, "Got " . count($door_notifs));
    }

    protected function test_s7_multi_tenant_isolation()
    {
        $state = $this->Sensor_state_model->get_by_sensor(6);
        $this->assert('S7: Fleet sensor final state is alert', $state && $state->current_state === 'alert');

        $this->db->where('sensor_id', 6);
        $this->db->where('direction', 'alert');
        $fleet_notifs = $this->db->get('notifications')->result();
        $this->assert('S7: Only 1 notification for fleet sensor', count($fleet_notifs) === 1, "Got " . count($fleet_notifs));
        if (count($fleet_notifs) === 1) {
            $this->assert('S7: Notification went to Csongi', (int)$fleet_notifs[0]->user_id === 3);
        }
    }
}
