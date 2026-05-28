<?php
/*
Plugin Name: Quick.Link Analytics
Plugin URI: https://github.com/one-thirteen/quicklink/tree/main/plugins/quick-link-analytics
Description: Adds analytics API endpoints for Quick.Link clients.
Version: 0.1.0
Author: OneThirteen
Author URI: https://github.com/one-thirteen
*/

if (!defined('YOURLS_ABSPATH')) {
    die();
}

yourls_add_filter('api_actions', 'qlanalytics_register_api_actions');

function qlanalytics_register_api_actions($actions) {
    $actions['shorturl_analytics'] = 'qlanalytics_api_shorturl_analytics';
    $actions['quicklink_analytics_overview'] = 'qlanalytics_api_overview';
    $actions['quicklink_recent_clicks'] = 'qlanalytics_api_recent_clicks';
    $actions['quicklink_plugin_info'] = 'qlanalytics_api_plugin_info';
    return $actions;
}

function qlanalytics_api_plugin_info() {
    return qlanalytics_success(array(
        'plugin' => array(
            'name' => 'Quick.Link Analytics',
            'version' => '0.1.0',
            'features' => array(
                'shorturl_analytics',
                'overview',
                'recent_clicks',
                'referrer_breakdown',
                'country_breakdown',
                'browser_breakdown',
            ),
            'log_table_available' => qlanalytics_log_table_available(),
        ),
    ));
}

function qlanalytics_api_shorturl_analytics() {
    if (!qlanalytics_log_table_available()) {
        return qlanalytics_error('YOURLS click log table is not available.', 503);
    }

    $keyword = qlanalytics_request_keyword();
    if ($keyword === '') {
        return qlanalytics_error('Missing shorturl parameter.', 400);
    }

    if (!qlanalytics_keyword_exists($keyword)) {
        return qlanalytics_error('Short URL not found.', 404);
    }

    [$start, $end] = qlanalytics_request_range();

    return qlanalytics_success(array(
        'stats' => array(
            'keyword'      => $keyword,
            'total_clicks' => qlanalytics_total_clicks_for_keyword($keyword),
            'range_clicks' => qlanalytics_range_clicks_for_keyword($keyword, $start, $end),
            'daily_clicks' => qlanalytics_daily_clicks_for_keyword($keyword, $start, $end),
        ),
    ));
}

function qlanalytics_api_overview() {
    if (!qlanalytics_log_table_available()) {
        return qlanalytics_error('YOURLS click log table is not available.', 503);
    }

    [$start, $end] = qlanalytics_request_range();
    $limit = qlanalytics_request_limit(10, 50);

    return qlanalytics_success(array(
        'stats' => array(
            'range' => array(
                'start' => qlanalytics_format_day($start),
                'end'   => qlanalytics_format_day($end),
            ),
            'total_links'    => qlanalytics_total_links(),
            'total_clicks'   => qlanalytics_total_clicks(),
            'range_clicks'   => qlanalytics_range_clicks($start, $end),
            'active_links'   => qlanalytics_active_links(),
            'zero_click_links' => qlanalytics_zero_click_links(),
            'daily_clicks'   => qlanalytics_daily_clicks($start, $end),
            'top_links'      => qlanalytics_top_links($start, $end, $limit),
            'top_referrers'  => qlanalytics_top_log_values('referrer', $start, $end, $limit),
            'top_countries'  => qlanalytics_top_log_values('country_code', $start, $end, $limit),
            'top_browsers'   => qlanalytics_top_browsers($start, $end, $limit),
        ),
    ));
}

function qlanalytics_api_recent_clicks() {
    if (!qlanalytics_log_table_available()) {
        return qlanalytics_error('YOURLS click log table is not available.', 503);
    }

    $limit = qlanalytics_request_limit(25, 100);
    $table_url = YOURLS_DB_TABLE_URL;
    $table_log = YOURLS_DB_TABLE_LOG;

    $sql = "
        SELECT
            l.click_time,
            l.shorturl,
            l.referrer,
            l.user_agent,
            l.country_code,
            u.url,
            u.title
        FROM `$table_log` l
        LEFT JOIN `$table_url` u ON u.keyword = l.shorturl
        ORDER BY l.click_time DESC
        LIMIT $limit
    ";

    $rows = qlanalytics_db()->fetchObjects($sql);
    $clicks = array();

    foreach ($rows as $row) {
        $clicks[] = array(
            'clicked_at'    => $row->click_time,
            'keyword'       => $row->shorturl,
            'shorturl'      => yourls_link($row->shorturl),
            'url'           => $row->url,
            'title'         => $row->title,
            'referrer'      => qlanalytics_normalize_empty($row->referrer, 'Direct'),
            'country_code'  => qlanalytics_normalize_empty($row->country_code, 'Unknown'),
            'browser'       => qlanalytics_browser_name($row->user_agent),
        );
    }

    return qlanalytics_success(array('clicks' => $clicks));
}

function qlanalytics_success($payload) {
    return array_merge(array(
        'statusCode' => 200,
        'message'    => 'success',
    ), $payload);
}

function qlanalytics_error($message, $status_code) {
    return array(
        'statusCode' => $status_code,
        'errorCode'  => (string)$status_code,
        'message'    => $message,
        'simple'     => $message,
    );
}

function qlanalytics_db() {
    return yourls_get_db();
}

function qlanalytics_log_table_available() {
    return defined('YOURLS_DB_TABLE_LOG') && YOURLS_DB_TABLE_LOG !== '';
}

function qlanalytics_request_keyword() {
    $shorturl = isset($_REQUEST['shorturl']) ? trim((string)$_REQUEST['shorturl']) : '';
    if ($shorturl === '') {
        return '';
    }

    $path = parse_url($shorturl, PHP_URL_PATH);
    if (is_string($path) && $path !== '') {
        $shorturl = basename($path);
    }

    return trim($shorturl, " \t\n\r\0\x0B/");
}

function qlanalytics_request_range() {
    $default_end = new DateTimeImmutable('today', new DateTimeZone('UTC'));
    $default_start = $default_end->modify('-29 days');

    $start = qlanalytics_parse_day(isset($_REQUEST['date']) ? $_REQUEST['date'] : null, $default_start);
    $end = qlanalytics_parse_day(isset($_REQUEST['date_end']) ? $_REQUEST['date_end'] : null, $default_end);

    if ($start > $end) {
        return array($end, $start);
    }

    return array($start, $end);
}

function qlanalytics_parse_day($value, DateTimeImmutable $fallback) {
    if (!is_string($value) || trim($value) === '') {
        return $fallback;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value), new DateTimeZone('UTC'));
    return $date instanceof DateTimeImmutable ? $date : $fallback;
}

function qlanalytics_format_day(DateTimeImmutable $date) {
    return $date->format('Y-m-d');
}

function qlanalytics_range_bounds(DateTimeImmutable $start, DateTimeImmutable $end) {
    return array(
        $start->format('Y-m-d 00:00:00'),
        $end->format('Y-m-d 23:59:59'),
    );
}

function qlanalytics_request_limit($default, $max) {
    $limit = isset($_REQUEST['limit']) ? (int)$_REQUEST['limit'] : $default;
    return max(1, min($limit, $max));
}

function qlanalytics_keyword_exists($keyword) {
    $table = YOURLS_DB_TABLE_URL;
    $sql = "SELECT COUNT(*) FROM `$table` WHERE keyword = :keyword";
    return (int)qlanalytics_db()->fetchValue($sql, array('keyword' => $keyword)) > 0;
}

function qlanalytics_total_links() {
    $table = YOURLS_DB_TABLE_URL;
    return (int)qlanalytics_db()->fetchValue("SELECT COUNT(*) FROM `$table`");
}

function qlanalytics_total_clicks() {
    $table = YOURLS_DB_TABLE_URL;
    return (int)qlanalytics_db()->fetchValue("SELECT COALESCE(SUM(clicks), 0) FROM `$table`");
}

function qlanalytics_total_clicks_for_keyword($keyword) {
    $table = YOURLS_DB_TABLE_URL;
    $sql = "SELECT COALESCE(clicks, 0) FROM `$table` WHERE keyword = :keyword";
    return (int)qlanalytics_db()->fetchValue($sql, array('keyword' => $keyword));
}

function qlanalytics_active_links() {
    $table = YOURLS_DB_TABLE_URL;
    return (int)qlanalytics_db()->fetchValue("SELECT COUNT(*) FROM `$table` WHERE clicks > 0");
}

function qlanalytics_zero_click_links() {
    $table = YOURLS_DB_TABLE_URL;
    return (int)qlanalytics_db()->fetchValue("SELECT COUNT(*) FROM `$table` WHERE clicks = 0");
}

function qlanalytics_range_clicks(DateTimeImmutable $start, DateTimeImmutable $end) {
    [$from, $to] = qlanalytics_range_bounds($start, $end);
    $table = YOURLS_DB_TABLE_LOG;
    $sql = "SELECT COUNT(*) FROM `$table` WHERE click_time BETWEEN :from AND :to";
    return (int)qlanalytics_db()->fetchValue($sql, array('from' => $from, 'to' => $to));
}

function qlanalytics_range_clicks_for_keyword($keyword, DateTimeImmutable $start, DateTimeImmutable $end) {
    [$from, $to] = qlanalytics_range_bounds($start, $end);
    $table = YOURLS_DB_TABLE_LOG;
    $sql = "
        SELECT COUNT(*)
        FROM `$table`
        WHERE shorturl = :keyword
          AND click_time BETWEEN :from AND :to
    ";
    return (int)qlanalytics_db()->fetchValue($sql, array('keyword' => $keyword, 'from' => $from, 'to' => $to));
}

function qlanalytics_daily_clicks(DateTimeImmutable $start, DateTimeImmutable $end) {
    [$from, $to] = qlanalytics_range_bounds($start, $end);
    $table = YOURLS_DB_TABLE_LOG;
    $sql = "
        SELECT DATE(click_time) AS day, COUNT(*) AS clicks
        FROM `$table`
        WHERE click_time BETWEEN :from AND :to
        GROUP BY DATE(click_time)
        ORDER BY day ASC
    ";
    $rows = qlanalytics_db()->fetchObjects($sql, array('from' => $from, 'to' => $to));
    return qlanalytics_daily_series($start, $end, $rows);
}

function qlanalytics_daily_clicks_for_keyword($keyword, DateTimeImmutable $start, DateTimeImmutable $end) {
    [$from, $to] = qlanalytics_range_bounds($start, $end);
    $table = YOURLS_DB_TABLE_LOG;
    $sql = "
        SELECT DATE(click_time) AS day, COUNT(*) AS clicks
        FROM `$table`
        WHERE shorturl = :keyword
          AND click_time BETWEEN :from AND :to
        GROUP BY DATE(click_time)
        ORDER BY day ASC
    ";
    $rows = qlanalytics_db()->fetchObjects($sql, array('keyword' => $keyword, 'from' => $from, 'to' => $to));
    return qlanalytics_daily_series($start, $end, $rows);
}

function qlanalytics_daily_series(DateTimeImmutable $start, DateTimeImmutable $end, $rows) {
    $series = array();
    for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
        $series[$day->format('Y-m-d')] = 0;
    }

    foreach ($rows as $row) {
        $series[$row->day] = (int)$row->clicks;
    }

    return $series;
}

function qlanalytics_top_links(DateTimeImmutable $start, DateTimeImmutable $end, $limit) {
    [$from, $to] = qlanalytics_range_bounds($start, $end);
    $table_url = YOURLS_DB_TABLE_URL;
    $table_log = YOURLS_DB_TABLE_LOG;
    $sql = "
        SELECT
            u.keyword,
            u.url,
            u.title,
            u.clicks AS total_clicks,
            COUNT(l.click_id) AS range_clicks
        FROM `$table_url` u
        LEFT JOIN `$table_log` l
            ON l.shorturl = u.keyword
           AND l.click_time BETWEEN :from AND :to
        GROUP BY u.keyword, u.url, u.title, u.clicks
        ORDER BY range_clicks DESC, u.clicks DESC
        LIMIT $limit
    ";

    $rows = qlanalytics_db()->fetchObjects($sql, array('from' => $from, 'to' => $to));
    $links = array();
    foreach ($rows as $row) {
        $links[] = array(
            'keyword'      => $row->keyword,
            'shorturl'     => yourls_link($row->keyword),
            'url'          => $row->url,
            'title'        => $row->title,
            'total_clicks' => (int)$row->total_clicks,
            'range_clicks' => (int)$row->range_clicks,
        );
    }
    return $links;
}

function qlanalytics_top_log_values($column, DateTimeImmutable $start, DateTimeImmutable $end, $limit) {
    $allowed = array('referrer', 'country_code');
    if (!in_array($column, $allowed, true)) {
        return array();
    }

    [$from, $to] = qlanalytics_range_bounds($start, $end);
    $table = YOURLS_DB_TABLE_LOG;
    $sql = "
        SELECT `$column` AS label, COUNT(*) AS clicks
        FROM `$table`
        WHERE click_time BETWEEN :from AND :to
        GROUP BY `$column`
        ORDER BY clicks DESC
        LIMIT $limit
    ";

    $rows = qlanalytics_db()->fetchObjects($sql, array('from' => $from, 'to' => $to));
    $items = array();
    foreach ($rows as $row) {
        $fallback = $column === 'referrer' ? 'Direct' : 'Unknown';
        $label = qlanalytics_normalize_empty($row->label, $fallback);
        if ($column === 'referrer') {
            $label = qlanalytics_referrer_label($label);
        }

        $items[] = array(
            'label'  => $label,
            'clicks' => (int)$row->clicks,
        );
    }
    return $items;
}

function qlanalytics_top_browsers(DateTimeImmutable $start, DateTimeImmutable $end, $limit) {
    [$from, $to] = qlanalytics_range_bounds($start, $end);
    $table = YOURLS_DB_TABLE_LOG;
    $sql = "
        SELECT user_agent
        FROM `$table`
        WHERE click_time BETWEEN :from AND :to
    ";

    $rows = qlanalytics_db()->fetchObjects($sql, array('from' => $from, 'to' => $to));
    $counts = array();
    foreach ($rows as $row) {
        $browser = qlanalytics_browser_name($row->user_agent);
        $counts[$browser] = isset($counts[$browser]) ? $counts[$browser] + 1 : 1;
    }

    arsort($counts);
    $items = array();
    foreach (array_slice($counts, 0, $limit, true) as $browser => $clicks) {
        $items[] = array('label' => $browser, 'clicks' => $clicks);
    }
    return $items;
}

function qlanalytics_normalize_empty($value, $fallback) {
    $value = trim((string)$value);
    return $value === '' ? $fallback : $value;
}

function qlanalytics_referrer_label($value) {
    if (strtolower($value) === 'direct') {
        return 'Direct';
    }

    $host = parse_url($value, PHP_URL_HOST);
    if (is_string($host) && $host !== '') {
        $path = parse_url($value, PHP_URL_PATH);
        $host = preg_replace('/^www\./', '', $host);
        return $host . ($path && $path !== '/' ? $path : '');
    }

    return $value;
}

function qlanalytics_browser_name($user_agent) {
    $ua = strtolower((string)$user_agent);
    if ($ua === '') {
        return 'Unknown';
    }
    if (strpos($ua, 'edg/') !== false || strpos($ua, 'edge/') !== false) {
        return 'Edge';
    }
    if (strpos($ua, 'chrome/') !== false && strpos($ua, 'chromium') === false) {
        return 'Chrome';
    }
    if (strpos($ua, 'safari/') !== false && strpos($ua, 'chrome/') === false) {
        return 'Safari';
    }
    if (strpos($ua, 'firefox/') !== false) {
        return 'Firefox';
    }
    if (strpos($ua, 'curl/') !== false) {
        return 'curl';
    }
    return 'Other';
}
