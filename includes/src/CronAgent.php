<?php
/**
 * Docket Cache.
 *
 * @author  Nawawi Jamili
 * @license MIT
 *
 * @see    https://github.com/nawawi/docket-cache
 */

namespace Nawawi\DocketCache;

\defined('ABSPATH') || exit;

final class CronAgent
{
    private $is_pingpong;
    private $pt;

    public function __construct(Plugin $pt)
    {
        $this->pt = $pt;
        $this->is_pingpong = false;

        // warn issue: a non-numeric value encountered
        $this->pt->cf()->maybe_define('WP_CRON_LOCK_TIMEOUT', MINUTE_IN_SECONDS);
    }

    public function register()
    {
        add_action(
            'wp',
            function () {
                $this->receive_ping();
            },
            -PHP_INT_MAX
        );

        add_action(
            'shutdown',
            function () {
                $this->check_connection();
            },
            PHP_INT_MAX
        );

        add_filter(
            'docketcache/cronbot-active',
            function ($status) {
                $status = $status ? 'on' : 'off';

                return $this->send_action($status);
            },
            PHP_INT_MAX
        );

        add_filter(
            'docketcache/cronbot-pong',
            function ($status) {
                return $this->send_action('on', 'pong');
            },
            PHP_INT_MAX
        );

        add_filter(
            'docketcache/cronbot-runevent',
            function ($runnow) {
                $is_switch = $this->pt->switch_cron_site();

                $results = $this->run_wpcron($runnow);

                if (!empty($results) && \is_array($results) && !empty($results['wpcron_return'])) {
                    @Crawler::fetch_admin(admin_url('/'));
                } else {
                    $results = false;
                }

                if ($is_switch) {
                    restore_current_blog();
                }

                return $results;
            },
            PHP_INT_MAX
        );
    }

    private function is_ping_request()
    {
        return !empty($_POST['ping']) && !empty($_GET['docketcache_ping']) && !empty($_SERVER['REQUEST_URI']) && false !== strpos($_SERVER['REQUEST_URI'], '/?docketcache_ping=');
    }

    private function maybe_disable_wp_cron()
    {
        // signal wp do not run wp-cron
        $this->pt->cf()->maybe_define('DISABLE_WP_CRON', true);
    }

    private function send_action($action, $is_hello = false)
    {
        $is_quick = $is_hello && 'pong' !== $is_hello ? true : false;
        $is_pong = 'pong' === $is_hello;

        $uip = $this->pt->get_user_ip();

        static $cache = [];
        if (isset($cache[$uip])) {
            return $cache[$uip];
        }

        $site_url = $this->pt->site_url();
        $site_key = substr(md5($site_url), 0, 22);
        $site_body = $this->pt->nw_encrypt($site_url, $site_key);
        $site_id = $this->pt->nw_encrypt($site_key, $site_body);

        $args = [
            'blocking' => $is_quick ? false : true,
            'body' => [
                'timestamp' => date('Y-m-d H:i:s T'),
                'timezone' => wp_timezone_string(),
                'site' => $this->pt->base64_encode_url($site_body),
                'meta' => $this->pt->site_meta(),
                'status' => $action,
            ],
            'headers' => [
                'REFERER' => $this->pt->site_url(true),
                'DOCKETID' => $site_id,
            ],
        ];

        $stmp = time() + 120;
        $cronbot_endpoint = $this->pt->cronbot_endpoint.'/checkstatus?v='.$stmp;
        $results = Crawler::post($cronbot_endpoint, $args);

        if ($is_quick) {
            return true;
        }

        $output = [
            'created' => date('Y-m-d H:i:s T'),
            'endpoint' => $cronbot_endpoint,
            'connected' => false,
            'last_status' => $action,
            'request' => [
                'headers' => $args['headers'],
                'content' => $args['body'],
            ],
        ];

        if (is_wp_error($results)) {
            $output['error'] = $results->get_error_message();

            if (!$is_pong) {
                $this->pt->co()->save_part($output, 'cronbot');
            }

            $this->pt->co()->lookup_set('cronboterror', $output['error']);

            $cache[$uip] = false;

            return false;
        }

        $output['response'] = wp_remote_retrieve_body($results);
        if (!empty($output['response'])) {
            $output['response'] = json_decode($output['response'], true);
            if (JSON_ERROR_NONE === json_last_error()) {
                if (!empty($output['response']['error'])) {
                    $output['error'] = $output['response']['error'];

                    if (!$is_pong) {
                        $this->pt->co()->save_part($output, 'cronbot');
                    }

                    $this->pt->co()->lookup_set('cronboterror', $output['error']);

                    $cache[$uip] = false;

                    return false;
                }
            }
        }

        $code = (int) wp_remote_retrieve_response_code($results);
        if ($code > 400) {
            $output['error'] = $code;

            if (!$is_pong) {
                $this->pt->co()->save_part($output, 'cronbot');
            }

            $this->pt->co()->lookup_set('cronboterror', 'Error '.$output['error']);

            $cache[$uip] = false;

            return false;
        }

        $output['connected'] = 'off' === $action ? false : true;

        if (!$is_pong) {
            $this->pt->co()->save_part($output, 'cronbot');
        }

        $cache[$uip] = true;

        return true;
    }

    private function close_ping($response)
    {
        $output = $response;
        $output['request'] = array_filter(
            $_SERVER,
            function ($arr) {
                if ('HTTP_' === substr($arr, 0, 5)) {
                    return true;
                }
            },
            ARRAY_FILTER_USE_KEY
        );

        $this->pt->co()->save_part($output, 'pings');

        $this->pt->json_header();
        $this->pt->close_exit(json_encode($response, JSON_UNESCAPED_SLASHES));
    }

    private function run_wpcron($run_now = false)
    {
        $crons = $this->pt->get_crons($run_now, $cron_event);

        $results = [
            'wpcron_return' => 0,
            'wpcron_msg' => '',
            'wpcron_crons' => $cron_event,
            'wpcron_event' => 0,
        ];

        if (empty($crons)) {
            $results['wpcron_return'] = 1;
            $results['wpcron_msg'] = 'No scheduled event ready to run';

            return $results;
        }

        if (false !== strpos($_SERVER['REQUEST_URI'], '/wp-cron.php') || isset($_GET['doing_wp_cron']) || wp_doing_cron()) {
            $results['wpcron_return'] = 1;
            $results['wpcron_msg'] = 'Another cron process is currently running wp-cron.php';

            return $results;
        }

        $gmt_time = microtime(true);

        // overwrite cron lock
        $doing_wp_cron = sprintf('%.22F', microtime(true));
        set_transient('doing_cron', $doing_wp_cron, 86400);

        $run_event = 0;
        $slowdown = 0;
        $delay = $this->is_pingpong ? 750 : 200;

        foreach ($crons as $timestamp => $cronhooks) {
            if (false === $run_now && ($timestamp > $gmt_time)) {
                continue;
            }

            if ($slowdown > 10) {
                $slowdown = 0;

                usleep($delay);
            }

            ++$slowdown;

            foreach ($cronhooks as $hook => $keys) {
                if (!has_action($hook)) {
                    wp_clear_scheduled_hook($hook);
                    continue;
                }

                foreach ($keys as $k => $v) {
                    $schedule = $v['schedule'];

                    if ($schedule) {
                        wp_reschedule_event($timestamp, $schedule, $hook, $v['args']);
                    }

                    wp_unschedule_event($timestamp, $hook, $v['args']);

                    try {
                        do_action_ref_array($hook, $v['args']);
                        ++$run_event;
                    } catch (\Throwable $e) {
                        $results['wpcron_error'][$hook] = $e->getMessage();
                        wp_clear_scheduled_hook($hook);
                        --$run_event;
                    }
                }
            }
        }

        unset($cronhooks);

        // lock must below 10 minutes
        // wp-includes/cron.php -> spawn_cron()
        // wp-cron.php
        $lock_wp_cron = microtime(true) + 300;
        set_transient('doing_cron', $lock_wp_cron, 86400);

        $results['wpcron_return'] = 1;
        $results['wpcron_event'] = $run_event;

        return $results;
    }

    private function receive_ping()
    {
        if (headers_sent() || !$this->is_ping_request()) {
            return;
        }

        if ($_POST['ping'] !== md5($_GET['docketcache_ping'])) {
            $this->close_ping('Invalid ping');

            return;
        }

        // signal wp do not run wp-cron
        $this->maybe_disable_wp_cron();

        $site_url = $this->pt->site_url();

        if (!empty($_POST['token'])) {
            $verify = $this->pt->nw_decrypt($_POST['token'], $_POST['ping']);
            if ($verify !== $site_url) {
                $this->close_ping('Invalid token');

                return;
            }
        }

        if (!@preg_match('@compatible;\s+cronbot/[0-9\.]+;\s+docket\-cache/[0-9\.]+;\s+@', $this->pt->get_user_agent())) {
            $this->close_ping('Invalid version');

            return;
        }

        $uip = $this->pt->get_user_ip();

        static $cache = [];
        if (!empty($cache[$uip])) {
            return $cache[$uip];
        }

        $response = [
            'timestamp' => date('Y-m-d H:i:s T'),
            'timezone' => wp_timezone_string(),
            'site' => $this->pt->site_url(),
            'meta' => $this->pt->site_meta(),
            'status' => 1,
        ];

        if ($this->pt->cf()->is_dcfalse('CRONBOT')) {
            $response['status'] = 0;
            $cache[$uip] = $response;
            $this->close_ping($response);
        }

        if ($this->pt->co()->lockproc('receive_ping', time() + 60)) {
            $response['msg'] = 'Already received. Try again in a few minutes';
            $this->close_ping($response);

            return false;
        }

        $this->is_pingpong = true;
        $is_multisite = is_multisite();
        $siteall = 0;
        $sites = $this->pt->get_network_sites($siteall);
        $maxrun = 0;
        $maxcan = (int) $this->pt->cf()->dcvalue('CRONBOT_MAX');
        $maxcan = $maxcan < 1 ? 5 : $maxcan;
        $halt = false;
        foreach ($sites as $num => $site) {
            if ($halt) {
                break;
            }

            if ($is_multisite) {
                switch_to_blog($site['id']);
            }

            if ($maxrun >= $maxcan) {
                $results = [
                    'wpcron_return' => 3,
                    'wpcron_msg' => 'Reach maximum run: '.$maxrun.'/'.$siteall,
                    'wpcron_crons' => 0,
                    'wpcron_event' => 0,
                ];
                $halt = true;
            } else {
                $results = $this->run_wpcron();
            }

            if ($is_multisite) {
                restore_current_blog();
            }

            if ($site['is_main']) {
                $response = array_merge($response, $results);
                --$maxrun;
            } else {
                $response['site_'.$site['id']] = [
                    'site' => $site['url'],
                    'wpcron' => $results,
                ];
            }
            ++$maxrun;

            // slow down cpu
            usleep(100);
        }

        $cache[$uip] = $response;

        $this->is_pingpong = false;
        $this->close_ping($cache[$uip]);
    }

    private function check_connection()
    {
        if (\function_exists('wp_is_maintenance_mode') && wp_is_maintenance_mode()) {
            return;
        }

        // only main site
        if (!is_main_site()) {
            return;
        }

        if ($this->is_ping_request()) {
            return;
        }

        if ($this->pt->cf()->is_true('WP_IMPORTING')) {
            return;
        }

        if ($this->pt->co()->lockexp('check_connection')) {
            return;
        }

        $crondata = $this->pt->co()->get_part('cronbot', true);
        if (empty($crondata)) {
            return;
        }

        if (\is_array($crondata) && !empty($crondata['connected'])) {
            $pingdata = $this->pt->co()->get_part('pings', true);
            if (empty($pingdata)) {
                return;
            }
            if (\is_array($pingdata) && !empty($pingdata['timestamp'])) {
                $timestamp = strtotime('+90 minutes', strtotime($pingdata['timestamp']));
                if ($timestamp > 0 && time() > $timestamp) {
                    $this->pt->co()->setlock('check_connection', time() + 300);

                    // signal wp do not run wp-cron
                    $this->maybe_disable_wp_cron();

                    $this->send_action('on', true);
                }
            }
        }
    }
}
