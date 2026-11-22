<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Site licence client for the TAG licence service.
 *
 * @package    local_examtelemetry
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_examtelemetry\local;

/**
 * Activates, refreshes and reports the site licence.
 *
 * The stored token holds the key, the site host it was activated for, the plan and the dates.
 * A network failure never removes an activated licence; only an explicit 403/404 from the
 * service (revoked or unknown key) does.
 *
 * @package    local_examtelemetry
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class license {
    /** Plugin configuration component. */
    private const COMPONENT = 'local_examtelemetry';
    /** Product name known to the licence service. */
    private const PRODUCT = 'examtelemetry';
    /** Licence service base URL. */
    private const ENDPOINT = 'https://tandreig.com/api/license/';

    /**
     * Get the current licence status.
     *
     * @return string valid, trial_expired or unlicensed
     */
    public static function get_status(): string {
        $info = self::get_info();
        if (!$info || empty($info->key) || ($info->domain ?? '') !== self::domain()) {
            return 'unlicensed';
        }
        if (($info->plan ?? '') === 'perpetual') {
            return 'valid';
        }
        if (($info->plan ?? '') === 'trial' && !empty($info->expires_at)) {
            return (int) $info->expires_at > time() ? 'valid' : 'trial_expired';
        }
        return 'unlicensed';
    }

    /**
     * Whether telemetry may be collected and reported on this site.
     *
     * @return bool
     */
    public static function is_valid(): bool {
        return self::get_status() === 'valid';
    }

    /**
     * Get the last successful response and its associated key.
     *
     * @return object|null
     */
    public static function get_info(): ?object {
        $value = get_config(self::COMPONENT, 'license_token');
        $info = $value ? json_decode($value) : null;
        return is_object($info) ? $info : null;
    }

    /**
     * Normalise the Moodle site host for the licence service.
     *
     * @return string
     */
    public static function domain(): string {
        global $CFG;
        return strtolower((string) parse_url($CFG->wwwroot, PHP_URL_HOST));
    }

    /**
     * Activate a key with the service.
     *
     * @param string $key Licence key
     * @return bool Whether activation succeeded
     */
    public static function activate(string $key): bool {
        $key = trim($key);
        return $key !== '' && static::request('activate', $key);
    }

    /**
     * Refresh the current licence without destroying it on network failure.
     *
     * @return bool Whether the service confirmed the licence
     */
    public static function refresh(): bool {
        $info = self::get_info();
        return $info && !empty($info->key) && static::request('renew', $info->key);
    }

    /**
     * Show the licence notice instead of a plugin page when access is unavailable.
     *
     * @return void
     */
    public static function require_valid(): void {
        global $PAGE, $OUTPUT;
        if (self::is_valid()) {
            return;
        }
        require_login(null, false);
        $PAGE->set_url(new \moodle_url('/local/examtelemetry/report.php'));
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_title(get_string('licenseheading', self::COMPONENT));
        $PAGE->set_heading(get_string('pluginname', self::COMPONENT));
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('licenseheading', self::COMPONENT));
        echo \html_writer::tag('p', get_string('license_' . self::get_status(), self::COMPONENT));
        if (has_capability('moodle/site:config', \context_system::instance())) {
            echo \html_writer::tag('p', \html_writer::link(
                new \moodle_url('/local/examtelemetry/license.php'),
                get_string('licensesettings', self::COMPONENT),
                ['class' => 'btn btn-primary']
            ));
            echo self::purchase_info();
        } else {
            echo \html_writer::tag('p', get_string('license_askadmin', self::COMPONENT));
        }
        echo $OUTPUT->footer();
        exit;
    }

    /**
     * How to obtain a licence key (no external links).
     *
     * @return string HTML
     */
    public static function purchase_info(): string {
        return \html_writer::tag('p', get_string('license_purchase', self::COMPONENT));
    }

    /**
     * Call the service and save only a complete successful response.
     *
     * @param string $action Service operation
     * @param string $key Licence key
     * @return bool
     */
    private static function request(string $action, string $key): bool {
        [$code, $raw, $error] = static::http_post($action, $key);
        if (in_array($code, [403, 404], true)) {
            $current = self::get_info();
            if ($current && hash_equals((string) $current->key, $key)) {
                unset_config('license_token', self::COMPONENT);
            }
            return false;
        }
        if ($code === 402 && $action === 'renew') {
            $current = self::get_info();
            if ($current && ($current->plan ?? '') === 'trial' && hash_equals((string) $current->key, $key)) {
                $current->expires_at = 1;
                set_config('license_token', json_encode($current), self::COMPONENT);
            }
            return false;
        }
        $response = json_decode((string) $raw);
        if (
            $code !== 200 || $error || !is_object($response) || ($response->status ?? '') !== 'ok' ||
                !in_array($response->plan ?? '', ['trial', 'perpetual'], true) ||
                !is_numeric($response->expires_at ?? null) || (int) $response->expires_at <= 0 ||
                (($response->plan ?? '') === 'perpetual' && !is_numeric($response->support_until ?? null))
        ) {
            return false;
        }
        $token = (object) [
            'key' => $key,
            'domain' => self::domain(),
            'plan' => $response->plan,
            'expires_at' => (int) $response->expires_at,
            'support_until' => isset($response->support_until) ? (int) $response->support_until : null,
        ];
        set_config('license_token', json_encode($token), self::COMPONENT);
        return true;
    }

    /**
     * Send a request using Moodle's proxy-aware HTTP client.
     *
     * @param string $action Service operation
     * @param string $key Licence key
     * @return array HTTP status, response body and transport error
     */
    protected static function http_post(string $action, string $key): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();
        $curl->setHeader(['Content-Type: application/json', 'Accept: application/json']);
        $curl->setopt(['CURLOPT_TIMEOUT' => 10, 'CURLOPT_CONNECTTIMEOUT' => 5, 'CURLOPT_SSL_VERIFYPEER' => true]);
        $raw = $curl->post(self::ENDPOINT . $action, json_encode([
            'key' => $key,
            'domain' => self::domain(),
            'product' => self::PRODUCT,
        ]));
        return [(int) ($curl->get_info()['http_code'] ?? 0), $raw, $curl->get_errno()];
    }
}
