<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ReadingProcessor {

    protected $ci;

    public function __construct() {
        $this->ci =& get_instance();
        $this->ci->load->model('Reading_model');
        $this->ci->load->model('Sensor_model');
        $this->ci->load->model('Sensor_state_model');
        $this->ci->load->model('User_model');
        $this->ci->load->model('Notification_model');
    }

    public function process_all() {
        $grouped_readings = $this->ci->Reading_model->get_unprocessed_grouped();
        $sensors_evaluated = 0;
        $transitions = 0;
        $notifications = 0;

        foreach ($grouped_readings as $sensor_id => $readings) {
            $sensors_evaluated++;
            $this->process_sensor($sensor_id, $readings, $transitions, $notifications);
        }

        return array(
            'sensors_evaluated' => $sensors_evaluated,
            'transitions'       => $transitions,
            'notifications'     => $notifications
        );
    }

    protected function process_sensor($sensor_id, $readings, &$transitions, &$notifications) {
        $sensor = $this->ci->Sensor_model->get_with_type($sensor_id);
        if (!$sensor) return;

        $properties = $this->ci->Sensor_model->get_effective_properties($sensor_id);
        $state_row = $this->ci->Sensor_state_model->get_or_create($sensor_id);

        $state = array(
            'current'            => $state_row->current_state,
            'pending'            => $state_row->pending_state,
            'pending_since'      => $state_row->pending_since,
            'last_transition_at' => $state_row->last_transition_at,
            'last_notified_at'   => $state_row->last_notified_at,
            'last_reading_id'    => $state_row->last_reading_id,
            'dirty'              => false
        );

        $processed_ids = array();

        foreach ($readings as $r) {
            $time = $r['recorded_at'];
            $val = $r['value'];
            $transitioned = false;

            if ($sensor->value_kind === 'numeric') {
                $transitioned = $this->process_numeric_reading($val, $time, $properties, $state);
            } else if ($sensor->value_kind === 'boolean') {
                $transitioned = $this->process_boolean_reading((bool)$val, $time, $properties, $state);
            }

            if ($transitioned) {
                $transitions++;
                $notifications += $this->handle_transition($sensor, $properties, $state, $time);
            }

            $state['last_reading_id'] = $r['id'];
            $state['dirty'] = true;

            $processed_ids[] = $r['id'];
        }

        if ($state['dirty']) {
            $this->ci->Sensor_state_model->update_state($sensor_id, array(
                'current_state'      => $state['current'],
                'pending_state'      => $state['pending'],
                'pending_since'      => $state['pending_since'],
                'last_transition_at' => $state['last_transition_at'],
                'last_notified_at'   => $state['last_notified_at'],
                'last_reading_id'    => $state['last_reading_id']
            ));
        }

        if (!empty($processed_ids)) {
            $this->ci->Reading_model->mark_processed($processed_ids);
        }
    }

    protected function process_numeric_reading($val, $time, $properties, &$state) {
        $warn = isset($properties['warnAbove']) ? (float)$properties['warnAbove'] : INF;
        $clear = isset($properties['clearBelow']) ? (float)$properties['clearBelow'] : -INF;
        $dwell = isset($properties['dwellSeconds']) ? (int)$properties['dwellSeconds'] : 0;
        
        $transitioned = false;

        if ($state['current'] === 'normal') {
            if ($val > $warn) {
                if ($state['pending'] !== 'pending_alert') {
                    $this->set_pending($state, 'pending_alert', $time);
                } else if ($this->has_dwelled($state['pending_since'], $time, $dwell)) {
                    $this->commit_transition($state, 'alert', $time);
                    $transitioned = true;
                }
            } else if ($val < $clear || ($val >= $clear && $val <= $warn)) {
                $this->reset_pending($state);
            }
        } else if ($state['current'] === 'alert') {
            if ($val < $clear) {
                if ($state['pending'] !== 'pending_clear') {
                    $this->set_pending($state, 'pending_clear', $time);
                } else if ($this->has_dwelled($state['pending_since'], $time, $dwell)) {
                    $this->commit_transition($state, 'normal', $time);
                    $transitioned = true;
                }
            } else if ($val > $warn || ($val >= $clear && $val <= $warn)) {
                $this->reset_pending($state);
            }
        }

        return $transitioned;
    }

    protected function process_boolean_reading($val, $time, $properties, &$state) {
        $alertWhen = isset($properties['alertWhen']) ? (bool)$properties['alertWhen'] : true;
        $dwell = isset($properties['dwellSeconds']) ? (int)$properties['dwellSeconds'] : 0;
        
        $transitioned = false;

        if ($state['current'] === 'normal') {
            if ($val === $alertWhen) {
                if ($state['pending'] !== 'pending_alert') {
                    $this->set_pending($state, 'pending_alert', $time);
                } else if ($this->has_dwelled($state['pending_since'], $time, $dwell)) {
                    $this->commit_transition($state, 'alert', $time);
                    $transitioned = true;
                }
            } else {
                $this->reset_pending($state);
            }
        } else if ($state['current'] === 'alert') {
            if ($val !== $alertWhen) {
                if ($state['pending'] !== 'pending_clear') {
                    $this->set_pending($state, 'pending_clear', $time);
                } else if ($this->has_dwelled($state['pending_since'], $time, $dwell)) {
                    $this->commit_transition($state, 'normal', $time);
                    $transitioned = true;
                }
            } else {
                $this->reset_pending($state);
            }
        }

        return $transitioned;
    }

    protected function set_pending(&$state, $target_state, $time) {
        if ($state['pending'] !== $target_state) {
            $state['pending'] = $target_state;
            $state['pending_since'] = $time;
            $state['dirty'] = true;
        }
    }

    protected function reset_pending(&$state) {
        if ($state['pending'] !== 'none') {
            $state['pending'] = 'none';
            $state['pending_since'] = null;
            $state['dirty'] = true;
        }
    }

    protected function has_dwelled($pending_since, $current_time, $dwell) {
        if (!$pending_since) return false;
        return (strtotime($current_time) - strtotime($pending_since)) >= $dwell;
    }

    protected function commit_transition(&$state, $new_state, $time) {
        $state['current'] = $new_state;
        $state['pending'] = 'none';
        $state['pending_since'] = null;
        $state['last_transition_at'] = $time;
        $state['dirty'] = true;
    }

    protected function handle_transition($sensor, $properties, &$state, $time) {
        $cooldown = isset($properties['cooldownSeconds']) ? (int)$properties['cooldownSeconds'] : 0;
        $direction = ($state['current'] === 'alert') ? 'alert' : 'clear';
        $notifications = 0;
        
        if ($this->can_notify_for_direction($sensor->id, $direction, $time, $cooldown)) {
            $state['last_notified_at'] = $time;
            $state['dirty'] = true;
            
            if ($direction === 'alert') {
                $notifications = $this->notify_users($sensor->id, $direction, $time, "Sensor {$sensor->display_name} has entered ALERT state.");
            } else {
                $notifications = $this->notify_users($sensor->id, $direction, $time, "Sensor {$sensor->display_name} has returned to NORMAL state.");
            }
        }

        return $notifications;
    }

    protected function can_notify_for_direction($sensor_id, $direction, $current_time, $cooldown) {
        if ($cooldown <= 0) return true;

        $last = $this->ci->Notification_model->get_last_for_sensor_direction($sensor_id, $direction);
        if (!$last) return true;

        return (strtotime($current_time) - strtotime($last->created_at)) >= $cooldown;
    }

    protected function notify_users($sensor_id, $direction, $time, $message) {
        $users = $this->ci->User_model->get_users_for_sensor($sensor_id);
        $count = 0;
        foreach ($users as $u) {
            $this->ci->Notification_model->insert(array(
                'user_id'    => $u->id,
                'sensor_id'  => $sensor_id,
                'direction'  => $direction,
                'message'    => $message,
                'created_at' => $time
            ));
            $count++;
        }
        return $count;
    }
}
