<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Seed Controller — POST /api/seed/run
 *
 * Creates realistic demo data to exercise all hysteresis scenarios.
 * This seeder creates:
 *   - 2 tenants (Botond Solutions, Csongor Logistics)
 *   - 3 users (Attila & Boti for Botond, Csongi for Csongor)
 *   - 4 sensor types (temperature, humidity, vibration, door_contact)
 *   - 3 devices across the two tenants
 *   - 6 sensors with varying property overrides
 *   - 7 reading timelines (S1-S7) demonstrating each hysteresis scenario
 *
 * USAGE:
 *   1. POST /api/migrate         (create tables)
 *   2. POST /api/seed/run        (seed data)
 *   3. POST /api/admin/process-readings  (process readings)
 *   4. GET  /api/sensors/1/state  (inspect results)
 *   5. GET  /api/notifications?userId=1  (check notifications)
 *
 * SEEDED IDs:
 *   Tenants: 1=Botond Solutions (api_key: botond-key-001), 2=Csongor Logistics (api_key: csongor-key-002)
 *   Users:   1=Attila, 2=Boti, 3=Csongi
 *   Devices: 1=Cold Room, 2=Warehouse, 3=Truck Fleet Hub
 *   Sensors: 1=Freezer Temp, 2=Cold Room Temp, 3=Cold Room Humidity,
 *            4=Warehouse Vibration, 5=Warehouse Door, 6=Fleet Temp
 */
class Seeder extends MY_Controller {

    /** @var string Base timestamp for all seeded readings */
    protected $base_time = '2026-08-10 10:00:00';

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Tenant_model');
        $this->load->model('User_model');
        $this->load->model('Device_model');
        $this->load->model('Sensor_type_model');
        $this->load->model('Sensor_model');
        $this->load->model('Reading_model');
    }

    /**
     * POST /api/seed/run
     *
     * Seeds all demo data. Truncates existing data first to allow re-running.
     */
    public function run()
    {
        // Truncate in reverse FK order to avoid constraint violations
        $this->truncate_all();

        // Seed in dependency order
        $tenant_ids      = $this->seed_tenants();
        $user_ids        = $this->seed_users($tenant_ids);
        $sensor_type_ids = $this->seed_sensor_types();
        $device_ids      = $this->seed_devices($tenant_ids);
        $sensor_ids      = $this->seed_sensors($device_ids, $sensor_type_ids);
        $reading_count   = $this->seed_readings($sensor_ids);

        $this->json_response(array(
            'status'  => 'ok',
            'message' => 'Seed complete',
            'seeded'  => array(
                'tenants'      => count($tenant_ids),
                'users'        => count($user_ids),
                'sensor_types' => count($sensor_type_ids),
                'devices'      => count($device_ids),
                'sensors'      => count($sensor_ids),
                'readings'     => $reading_count
            ),
            'reference' => array(
                'tenants' => array(
                    '1 - Botond Solutions'    => 'api_key: botond-key-001',
                    '2 - Csongor Logistics'   => 'api_key: csongor-key-002'
                ),
                'users' => array(
                    '1 - Attila (attila@botond.com)'  => 'tenant: Botond Solutions',
                    '2 - Boti (boti@botond.com)'      => 'tenant: Botond Solutions',
                    '3 - Csongi (csongi@csongor.com)' => 'tenant: Csongor Logistics'
                ),
                'sensors' => array(
                    '1 - Freezer Temp'         => 'type: temperature, device: Cold Room (Botond)',
                    '2 - Cold Room Temp'       => 'type: temperature, device: Cold Room (Botond)',
                    '3 - Cold Room Humidity'   => 'type: humidity, device: Cold Room (Botond)',
                    '4 - Warehouse Vibration'  => 'type: vibration, device: Warehouse (Botond)',
                    '5 - Warehouse Door'       => 'type: door_contact, device: Warehouse (Botond)',
                    '6 - Fleet Temp'           => 'type: temperature, device: Truck Fleet Hub (Csongor)'
                ),
                'scenarios' => array(
                    'S1' => 'Spike below dwell (sensor 1) → no alert',
                    'S2' => 'Sustained breach (sensor 2) → alert + notify',
                    'S3' => 'Chatter in dead band (sensor 2) → no additional alert',
                    'S4' => 'Clear with hysteresis (sensor 2) → clear + notify',
                    'S5' => 'Cooldown suppress (sensor 2) → alert state but notify suppressed',
                    'S6' => 'Boolean door (sensor 5) → alert after sustained open',
                    'S7' => 'Multi-tenant isolation (sensor 6) → only tenant 2 notified'
                )
            )
        ));
    }

    /**
     * Truncate all tables in reverse FK order.
     *
     * Disables foreign key checks temporarily to allow truncation
     * regardless of FK relationships.
     */
    protected function truncate_all()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');
        $this->db->truncate('notifications');
        $this->db->truncate('sensor_states');
        $this->db->truncate('readings');
        $this->db->truncate('sensors');
        $this->db->truncate('sensor_types');
        $this->db->truncate('devices');
        $this->db->truncate('users');
        $this->db->truncate('tenants');
        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Seed 2 tenants: Botond Solutions and Csongor Logistics.
     *
     * Each tenant gets a unique API key used for all API authentication.
     *
     * @return array  Map of name => id
     */
    protected function seed_tenants()
    {
        $tenants = array(
            array('name' => 'Botond Solutions',   'api_key' => 'botond-key-001'),
            array('name' => 'Csongor Logistics',  'api_key' => 'csongor-key-002')
        );

        $ids = array();
        foreach ($tenants as $t) {
            $ids[$t['name']] = $this->Tenant_model->insert($t);
        }
        return $ids;
    }

    /**
     * Seed 3 users across the two tenants.
     *
     * Attila and Boti belong to Botond Solutions — they'll receive
     * notifications for Botond's sensors. Csongi belongs to Csongor
     * Logistics — he'll only get notifications for Csongor's sensors
     * (multi-tenant isolation).
     *
     * @param  array $tenant_ids  Map of tenant name => id
     * @return array  Map of name => id
     */
    protected function seed_users($tenant_ids)
    {
        $users = array(
            array('tenant_id' => $tenant_ids['Botond Solutions'],  'email' => 'attila@botond.com',  'name' => 'Attila'),
            array('tenant_id' => $tenant_ids['Botond Solutions'],  'email' => 'boti@botond.com',    'name' => 'Boti'),
            array('tenant_id' => $tenant_ids['Csongor Logistics'], 'email' => 'csongi@csongor.com', 'name' => 'Csongi')
        );

        $ids = array();
        foreach ($users as $u) {
            $ids[$u['name']] = $this->User_model->insert($u);
        }
        return $ids;
    }

    /**
     * Seed 4 sensor types with property schemas and defaults.
     *
     * Property schema defines valid keys, their types, and descriptions.
     * Default properties provide the baseline thresholds that sensors
     * inherit unless they override specific values.
     *
     * @return array  Map of type_key => id
     */
    protected function seed_sensor_types()
    {
        $types = array(
            array(
                'type_key'   => 'temperature',
                'value_kind' => 'numeric',
                'unit'       => '°C',
                'description'=> 'Temperature sensor',
                'property_schema' => array(
                    'warnAbove'      => array('type' => 'number', 'description' => 'Alert threshold (value above this triggers pending)'),
                    'clearBelow'     => array('type' => 'number', 'description' => 'Clear threshold (value below this starts clear pending)'),
                    'dwellSeconds'   => array('type' => 'integer', 'description' => 'Seconds of contiguous qualifying readings before transition'),
                    'cooldownSeconds'=> array('type' => 'integer', 'description' => 'Minimum seconds between notifications for same direction')
                ),
                'default_properties' => array(
                    'warnAbove'       => 30,
                    'clearBelow'      => 28,
                    'dwellSeconds'    => 60,
                    'cooldownSeconds' => 300
                )
            ),
            array(
                'type_key'   => 'humidity',
                'value_kind' => 'numeric',
                'unit'       => '%RH',
                'description'=> 'Humidity sensor',
                'property_schema' => array(
                    'warnAbove'      => array('type' => 'number', 'description' => 'Alert threshold'),
                    'clearBelow'     => array('type' => 'number', 'description' => 'Clear threshold'),
                    'dwellSeconds'   => array('type' => 'integer', 'description' => 'Dwell time in seconds'),
                    'cooldownSeconds'=> array('type' => 'integer', 'description' => 'Cooldown in seconds')
                ),
                'default_properties' => array(
                    'warnAbove'       => 70,
                    'clearBelow'      => 65,
                    'dwellSeconds'    => 120,
                    'cooldownSeconds' => 600
                )
            ),
            array(
                'type_key'   => 'vibration',
                'value_kind' => 'numeric',
                'unit'       => 'mm/s',
                'description'=> 'Vibration sensor',
                'property_schema' => array(
                    'warnAbove'      => array('type' => 'number', 'description' => 'Alert threshold'),
                    'clearBelow'     => array('type' => 'number', 'description' => 'Clear threshold'),
                    'dwellSeconds'   => array('type' => 'integer', 'description' => 'Dwell time in seconds'),
                    'cooldownSeconds'=> array('type' => 'integer', 'description' => 'Cooldown in seconds')
                ),
                'default_properties' => array(
                    'warnAbove'       => 10,
                    'clearBelow'      => 8,
                    'dwellSeconds'    => 30,
                    'cooldownSeconds' => 180
                )
            ),
            array(
                'type_key'   => 'door_contact',
                'value_kind' => 'boolean',
                'unit'       => 'open/closed',
                'description'=> 'Door contact sensor (true=open, false=closed)',
                'property_schema' => array(
                    'alertWhen'      => array('type' => 'boolean', 'description' => 'Value that triggers alert (true=open)'),
                    'dwellSeconds'   => array('type' => 'integer', 'description' => 'Dwell time in seconds'),
                    'cooldownSeconds'=> array('type' => 'integer', 'description' => 'Cooldown in seconds')
                ),
                'default_properties' => array(
                    'alertWhen'       => true,
                    'dwellSeconds'    => 30,
                    'cooldownSeconds' => 120
                )
            )
        );

        $ids = array();
        foreach ($types as $t) {
            $ids[$t['type_key']] = $this->Sensor_type_model->insert($t);
        }
        return $ids;
    }

    /**
     * Seed 3 devices across the two tenants.
     *
     * @param  array $tenant_ids  Map of tenant name => id
     * @return array  Map of name => id
     */
    protected function seed_devices($tenant_ids)
    {
        $devices = array(
            array('tenant_id' => $tenant_ids['Botond Solutions'],  'name' => 'Cold Room',        'location' => 'Building 1, Floor B1'),
            array('tenant_id' => $tenant_ids['Botond Solutions'],  'name' => 'Warehouse',        'location' => 'Building 2, Main Hall'),
            array('tenant_id' => $tenant_ids['Csongor Logistics'], 'name' => 'Truck Fleet Hub',  'location' => 'Distribution Center')
        );

        $ids = array();
        foreach ($devices as $d) {
            $ids[$d['name']] = $this->Device_model->insert($d);
        }
        return $ids;
    }

    /**
     * Seed 6 sensors with varying property overrides.
     *
     * Each sensor inherits defaults from its type but can override
     * specific values. This demonstrates the merge behavior:
     *   Sensor 1: uses all type defaults — no overrides (S1 baseline sensor)
     *   Sensor 2: overrides cooldownSeconds=1200 (type default is 300), uses type defaults for thresholds
     *   Sensor 3: overrides dwellSeconds to 60 (type default is 120)
     *   Sensor 4: wider gap (warnAbove=12, clearBelow=9) and shorter dwell (15s)
     *   Sensor 5: shorter dwell for boolean (20s vs default 30s)
     *   Sensor 6: different thresholds for tenant 2 (warnAbove=35, clearBelow=32)
     *
     * @param  array $device_ids      Map of device name => id
     * @param  array $sensor_type_ids Map of type_key => id
     * @return array  Map of display_name => id
     */
    protected function seed_sensors($device_ids, $sensor_type_ids)
    {
        $sensors = array(
            array(
                'device_id'      => $device_ids['Cold Room'],
                'sensor_type_id' => $sensor_type_ids['temperature'],
                'display_name'   => 'Freezer Temp',
                'properties'     => array()  // Uses all type defaults — no overrides (S1 baseline)
            ),
            array(
                'device_id'      => $device_ids['Cold Room'],
                'sensor_type_id' => $sensor_type_ids['temperature'],
                'display_name'   => 'Cold Room Temp',
                'properties'     => array('cooldownSeconds' => 1200)  // Override: longer cooldown for S5 scenario
            ),
            array(
                'device_id'      => $device_ids['Cold Room'],
                'sensor_type_id' => $sensor_type_ids['humidity'],
                'display_name'   => 'Cold Room Humidity',
                'properties'     => array('dwellSeconds' => 60)  // Override: tighter dwell
            ),
            array(
                'device_id'      => $device_ids['Warehouse'],
                'sensor_type_id' => $sensor_type_ids['vibration'],
                'display_name'   => 'Warehouse Vibration',
                'properties'     => array(
                    'warnAbove'    => 12,   // Wider gap than default (12 vs 10)
                    'clearBelow'   => 9,    // 9 vs 8
                    'dwellSeconds' => 15    // Shorter dwell
                )
            ),
            array(
                'device_id'      => $device_ids['Warehouse'],
                'sensor_type_id' => $sensor_type_ids['door_contact'],
                'display_name'   => 'Warehouse Door',
                'properties'     => array('dwellSeconds' => 20)  // Shorter dwell for boolean
            ),
            array(
                'device_id'      => $device_ids['Truck Fleet Hub'],
                'sensor_type_id' => $sensor_type_ids['temperature'],
                'display_name'   => 'Fleet Temp',
                'properties'     => array(
                    'warnAbove'  => 35,   // Higher threshold for fleet
                    'clearBelow' => 32
                )
            )
        );

        $ids = array();
        foreach ($sensors as $s) {
            $ids[$s['display_name']] = $this->Sensor_model->insert($s);
        }
        return $ids;
    }

    /**
     * Seed reading timelines for all 7 scenarios.
     *
     * Each scenario creates a time series of readings that, when processed,
     * demonstrates a specific aspect of the hysteresis state machine.
     *
     * The readings are ordered chronologically within each sensor.
     * The processor evaluates them in time order, so the sequence matters.
     *
     * @param  array $sensor_ids  Map of sensor display_name => id
     * @return int   Total readings created
     */
    protected function seed_readings($sensor_ids)
    {
        $total = 0;

        $total += $this->seed_s1_spike_below_dwell($sensor_ids['Freezer Temp']);
        $total += $this->seed_s2_sustained_breach($sensor_ids['Cold Room Temp']);
        $total += $this->seed_s3_chatter_dead_band($sensor_ids['Cold Room Temp']);
        $total += $this->seed_s4_clear_hysteresis($sensor_ids['Cold Room Temp']);
        $total += $this->seed_s5_cooldown_suppress($sensor_ids['Cold Room Temp']);
        $total += $this->seed_s6_boolean_door($sensor_ids['Warehouse Door']);
        $total += $this->seed_s7_multi_tenant($sensor_ids['Fleet Temp']);

        return $total;
    }

    /**
     * S1 — Spike below dwell (Sensor 1: Freezer Temp)
     *
     * Temperature crosses warnAbove (30°C) briefly but drops back
     * before dwellSeconds (60s) elapses.
     *
     * Timeline:
     *   10:00:00  25°C  (normal baseline)
     *   10:01:00  31°C  (above 30 → pending_alert starts)
     *   10:01:30  32°C  (still above, 30s elapsed < 60s dwell)
     *   10:01:45  29°C  (dead band [28,30] → RESETS pending)
     *   10:02:00  27°C  (below 28, normal)
     *
     * Expected after processing: state = normal, no notifications.
     *
     * @param  int $sensor_id
     * @return int  Number of readings created
     */
    protected function seed_s1_spike_below_dwell($sensor_id)
    {
        $readings = array(
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:00:00', 'value' => 25.0),
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:01:00', 'value' => 31.0),
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:01:30', 'value' => 32.0),
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:01:45', 'value' => 29.0),
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:02:00', 'value' => 27.0),
        );
        return $this->Reading_model->insert_batch($readings);
    }

    /**
     * S2 — Sustained breach (Sensor 2: Cold Room Temp)
     *
     * Temperature stays above warnAbove (30°C) for longer than
     * dwellSeconds (60s), triggering an alert transition.
     *
     * Timeline:
     *   10:05:00  25°C  (normal baseline)
     *   10:06:00  31°C  (above 30 → pending_alert starts)
     *   10:06:30  33°C  (still above, 30s elapsed)
     *   10:07:00  32°C  (still above, 60s elapsed → TRANSITION to alert!)
     *   10:07:01  31°C  (still in alert, confirms it)
     *
     * Expected after processing: state = alert, 2 notifications created
     * (one for Attila, one for Boti — both Botond Solutions users).
     *
     * @param  int $sensor_id
     * @return int
     */
    protected function seed_s2_sustained_breach($sensor_id)
    {
        $readings = array(
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:05:00', 'value' => 25.0),
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:06:00', 'value' => 31.0),
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:06:30', 'value' => 33.0),
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:07:00', 'value' => 32.0),
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:07:01', 'value' => 31.0),
        );
        return $this->Reading_model->insert_batch($readings);
    }

    /**
     * S3 — Chatter in dead band (Sensor 2: Cold Room Temp)
     *
     * While in alert state (from S2), values oscillate between
     * clearBelow (28) and warnAbove (30) — the dead band.
     * Since dead band values RESET the pending_clear timer,
     * the sensor stays in alert state.
     *
     * Timeline:
     *   10:10:00  29°C  (dead band — no pending_clear starts, stays alert)
     *   10:10:30  27°C  (below 28 → pending_clear starts)
     *   10:11:00  29°C  (dead band → RESETS pending_clear!)
     *   10:11:30  27°C  (below 28 → pending_clear RE-starts)
     *   10:12:00  29°C  (dead band → RESETS again!)
     *   10:12:30  27°C  (below 28 → pending_clear RE-starts)
     *   10:12:50  29°C  (dead band → RESETS again, only 20s elapsed)
     *
     * Expected after processing: state stays alert, no clear notification.
     * The pending_clear timer never reaches 60s because dead band resets it.
     *
     * @param  int $sensor_id
     * @return int
     */
    protected function seed_s3_chatter_dead_band($sensor_id)
    {
        $readings = array(
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:10:00', 'value' => 29.0),
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:10:30', 'value' => 27.0),
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:11:00', 'value' => 29.0),
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:11:30', 'value' => 27.0),
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:12:00', 'value' => 29.0),
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:12:30', 'value' => 27.0),
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:12:50', 'value' => 29.0),
        );
        return $this->Reading_model->insert_batch($readings);
    }

    /**
     * S4 — Clear with hysteresis (Sensor 2: Cold Room Temp)
     *
     * Sensor is in alert (from S2, survived S3). Now values drop below
     * clearBelow (28°C) and stay there for dwellSeconds (60s).
     *
     * Timeline:
     *   10:15:00  31°C  (still alert, above warnAbove — no pending_clear)
     *   10:16:00  29°C  (dead band — no pending_clear starts)
     *   10:17:00  27°C  (below clearBelow=28 → pending_clear starts!)
     *   10:17:30  26°C  (still below 28, 30s elapsed)
     *   10:18:00  27°C  (still below 28, 60s elapsed → TRANSITION to normal!)
     *
     * Expected after processing: state = normal, 2 clear notifications
     * (Attila and Boti).
     *
     * @param  int $sensor_id
     * @return int
     */
    protected function seed_s4_clear_hysteresis($sensor_id)
    {
        $readings = array(
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:15:00', 'value' => 31.0),
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:16:00', 'value' => 29.0),
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:17:00', 'value' => 27.0),
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:17:30', 'value' => 26.0),
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:18:00', 'value' => 27.0),
        );
        return $this->Reading_model->insert_batch($readings);
    }

    /**
     * S5 — Cooldown suppress (Sensor 2: Cold Room Temp)
     *
     * After clearing at 10:18:00 (S4), a new breach occurs and sustains
     * past dwellSeconds. Cooldown is checked per-direction: the last ALERT
     * notification was sent at 10:07:00 (from S2). With cooldownSeconds=1200,
     * the new alert at 10:22:00 is only 900s later (< 1200s), so the
     * notification is SUPPRESSED.
     *
     * KEY: The state DOES transition to alert. Only the notification is suppressed.
     *
     * Timeline:
     *   10:20:00  25°C  (normal after clear)
     *   10:21:00  31°C  (above warnAbove=30 → pending_alert starts)
     *   10:21:30  32°C  (still above, 30s elapsed)
     *   10:22:00  33°C  (60s elapsed → TRANSITION to alert, but...)
     *
     * 10:22:00 - 10:07:00 = 900s < 1200s cooldown (per-direction) → NOTIFICATION SUPPRESSED
     * State changes to alert, but no notification rows are created.
     *
     * @param  int $sensor_id
     * @return int
     */
    protected function seed_s5_cooldown_suppress($sensor_id)
    {
        $readings = array(
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:20:00', 'value' => 25.0),
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:21:00', 'value' => 31.0),
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:21:30', 'value' => 32.0),
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:22:00', 'value' => 33.0),
        );
        return $this->Reading_model->insert_batch($readings);
    }

    /**
     * S6 — Boolean door sensor (Sensor 5: Warehouse Door)
     *
     * Door contact sensor uses alertWhen=true (1.0), dwellSeconds=20.
     * First opening is too short (15s). Second opening sustains for 20s.
     *
     * Timeline:
     *   10:00:00  0 (closed, normal)
     *   10:01:00  1 (open → pending_alert starts)
     *   10:01:10  1 (still open, 10s elapsed < 20s)
     *   10:01:15  0 (closed → RESETS pending)
     *   10:02:00  1 (open → pending_alert starts again)
     *   10:02:10  1 (still open, 10s elapsed)
     *   10:02:20  1 (20s elapsed → TRANSITION to alert!)
     *
     * Expected: First open too short → no alert.
     * Second open sustains 20s → alert + notifications for Attila & Boti.
     *
     * @param  int $sensor_id
     * @return int
     */
    protected function seed_s6_boolean_door($sensor_id)
    {
        $readings = array(
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:00:00', 'value' => 0),
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:01:00', 'value' => 1),
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:01:10', 'value' => 1),
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:01:15', 'value' => 0),
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:02:00', 'value' => 1),
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:02:10', 'value' => 1),
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:02:20', 'value' => 1),
        );
        return $this->Reading_model->insert_batch($readings);
    }

    /**
     * S7 — Multi-tenant isolation (Sensor 6: Fleet Temp, Csongor Logistics)
     *
     * Sensor 6 belongs to tenant 2 (Csongor Logistics). When it alerts,
     * only Csongi (Csongor's user) should receive notifications.
     * Attila and Boti (Botond's users) must NOT get notified.
     *
     * Uses warnAbove=35, clearBelow=32, dwellSeconds=60.
     *
     * Timeline:
     *   10:00:00  30°C  (normal)
     *   10:01:00  36°C  (above 35 → pending_alert starts)
     *   10:01:30  37°C  (still above)
     *   10:02:00  38°C  (60s elapsed → TRANSITION to alert!)
     *
     * Expected: Alert transition, notification ONLY for Csongi (user 3).
     *
     * @param  int $sensor_id
     * @return int
     */
    protected function seed_s7_multi_tenant($sensor_id)
    {
        $readings = array(
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:00:00', 'value' => 30.0),
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:01:00', 'value' => 36.0),
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:01:30', 'value' => 37.0),
            array('sensor_id' => $sensor_id, 'recorded_at' => '2026-08-10 10:02:00', 'value' => 38.0),
        );
        return $this->Reading_model->insert_batch($readings);
    }
}
