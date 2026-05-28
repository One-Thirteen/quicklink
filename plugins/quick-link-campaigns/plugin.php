<?php
/*
Plugin Name: Quick.Link Campaigns
Plugin URI: https://github.com/one-thirteen/quicklink/tree/main/plugins/quick-link-campaigns
Description: Adds campaign-aware short link creation and campaign analytics endpoints for Quick.Link clients.
Version: 0.1.0
Author: OneThirteen
Author URI: https://github.com/one-thirteen
*/

if (!defined('YOURLS_ABSPATH')) {
    die();
}

yourls_add_filter('api_actions', 'qlcampaigns_register_api_actions');

function qlcampaigns_register_api_actions($actions) {
    $actions['quicklink_campaign_info'] = 'qlcampaigns_api_info';
    $actions['quicklink_create_campaign_link'] = 'qlcampaigns_api_create_link';
    $actions['quicklink_campaign_overview'] = 'qlcampaigns_api_overview';
    return $actions;
}

function qlcampaigns_api_info() {
    return qlcampaigns_success(array(
        'plugin' => array(
            'name' => 'Quick.Link Campaigns',
            'version' => '0.1.0',
            'features' => array(
                'campaign_link_creation',
                'utm_builder',
                'campaign_overview',
                'source_breakdown',
                'medium_breakdown',
            ),
        ),
    ));
}

function qlcampaigns_api_create_link() {
    $url = qlcampaigns_request_string('url');
    if ($url === '') {
        return qlcampaigns_error('Missing url parameter.', 400);
    }

    $campaign_url = qlcampaigns_url_with_campaign_parameters($url);
    if ($campaign_url === '') {
        return qlcampaigns_error('Invalid url parameter.', 400);
    }

    $keyword = qlcampaigns_request_string('keyword');
    $title = qlcampaigns_request_string('title');
    $result = yourls_add_new_link($campaign_url, $keyword, $title);

    if (!is_array($result)) {
        return qlcampaigns_error('YOURLS did not return a valid link response.', 500);
    }

    $status = isset($result['status']) ? strtolower((string)$result['status']) : '';
    $status_code = isset($result['statusCode']) ? (string)$result['statusCode'] : '';
    if ($status !== 'success' && $status_code !== '200') {
        return qlcampaigns_error(
            isset($result['message']) ? (string)$result['message'] : 'Unable to create campaign link.',
            $status_code === '' ? 400 : (int)$status_code
        );
    }

    $keyword = qlcampaigns_created_keyword($result, $keyword);
    $payload = array(
        'shorturl' => $keyword !== '' ? yourls_link($keyword) : qlcampaigns_result_value($result, 'shorturl'),
        'url' => $campaign_url,
        'campaign' => qlcampaigns_campaign_from_url($campaign_url),
    );

    return qlcampaigns_success($payload);
}

function qlcampaigns_api_overview() {
    if (!qlcampaigns_log_table_available()) {
        return qlcampaigns_error('YOURLS click log table is not available.', 503);
    }

    [$start, $end] = qlcampaigns_request_range();
    $limit = qlcampaigns_request_limit(10, 50);
    $links = qlcampaigns_campaign_links($limit);
    $click_rows = qlcampaigns_campaign_click_rows($start, $end);

    $campaign_counts = array();
    $source_counts = array();
    $medium_counts = array();
    $range_clicks = 0;

    foreach ($click_rows as $row) {
        $campaign = qlcampaigns_campaign_from_url($row->url);
        if (!qlcampaigns_has_campaign($campaign)) {
            continue;
        }

        $range_clicks++;
        qlcampaigns_increment($campaign_counts, qlcampaigns_label($campaign['campaign'], 'Unlabeled'));
        qlcampaigns_increment($source_counts, qlcampaigns_label($campaign['source'], 'Unknown'));
        qlcampaigns_increment($medium_counts, qlcampaigns_label($campaign['medium'], 'Unknown'));
    }

    return qlcampaigns_success(array(
        'stats' => array(
            'range' => array(
                'start' => qlcampaigns_format_day($start),
                'end' => qlcampaigns_format_day($end),
            ),
            'total_campaign_links' => qlcampaigns_total_campaign_links(),
            'range_clicks' => $range_clicks,
            'top_campaigns' => qlcampaigns_breakdown_items($campaign_counts, $limit),
            'top_sources' => qlcampaigns_breakdown_items($source_counts, $limit),
            'top_mediums' => qlcampaigns_breakdown_items($medium_counts, $limit),
            'campaign_links' => $links,
        ),
    ));
}

function qlcampaigns_url_with_campaign_parameters($url) {
    $url = trim((string)$url);
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        return '';
    }

    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }

    $scheme = strtolower((string)$parts['scheme']);
    if ($scheme !== 'http' && $scheme !== 'https') {
        return '';
    }

    $query = array();
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    foreach (qlcampaigns_requested_utm_parameters() as $key => $value) {
        if ($value !== '') {
            $query[$key] = $value;
        }
    }

    $parts['query'] = http_build_query($query);
    return qlcampaigns_build_url($parts);
}

function qlcampaigns_requested_utm_parameters() {
    return array(
        'utm_source' => qlcampaigns_request_string(array('utm_source', 'source')),
        'utm_medium' => qlcampaigns_request_string(array('utm_medium', 'medium')),
        'utm_campaign' => qlcampaigns_request_string(array('utm_campaign', 'campaign')),
        'utm_term' => qlcampaigns_request_string(array('utm_term', 'term')),
        'utm_content' => qlcampaigns_request_string(array('utm_content', 'content')),
    );
}

function qlcampaigns_campaign_links($limit) {
    $table = YOURLS_DB_TABLE_URL;
    $sql = "
        SELECT keyword, url, title, clicks, timestamp
        FROM `$table`
        WHERE url LIKE '%utm_%'
        ORDER BY timestamp DESC
        LIMIT $limit
    ";

    $rows = qlcampaigns_db()->fetchObjects($sql);
    $links = array();
    foreach ($rows as $row) {
        $campaign = qlcampaigns_campaign_from_url($row->url);
        if (!qlcampaigns_has_campaign($campaign)) {
            continue;
        }

        $links[] = array(
            'keyword' => $row->keyword,
            'shorturl' => yourls_link($row->keyword),
            'url' => $row->url,
            'title' => $row->title,
            'clicks' => (int)$row->clicks,
            'created_at' => $row->timestamp,
            'campaign' => $campaign,
        );
    }
    return $links;
}

function qlcampaigns_campaign_click_rows(DateTimeImmutable $start, DateTimeImmutable $end) {
    [$from, $to] = qlcampaigns_range_bounds($start, $end);
    $table_url = YOURLS_DB_TABLE_URL;
    $table_log = YOURLS_DB_TABLE_LOG;
    $sql = "
        SELECT l.click_time, l.shorturl, u.url
        FROM `$table_log` l
        INNER JOIN `$table_url` u ON u.keyword = l.shorturl
        WHERE l.click_time BETWEEN :from AND :to
          AND u.url LIKE '%utm_%'
    ";

    return qlcampaigns_db()->fetchObjects($sql, array('from' => $from, 'to' => $to));
}

function qlcampaigns_total_campaign_links() {
    $table = YOURLS_DB_TABLE_URL;
    return (int)qlcampaigns_db()->fetchValue("SELECT COUNT(*) FROM `$table` WHERE url LIKE '%utm_%'");
}

function qlcampaigns_campaign_from_url($url) {
    $query = parse_url((string)$url, PHP_URL_QUERY);
    $values = array();
    if (is_string($query) && $query !== '') {
        parse_str($query, $values);
    }

    return array(
        'source' => qlcampaigns_string_value($values, 'utm_source'),
        'medium' => qlcampaigns_string_value($values, 'utm_medium'),
        'campaign' => qlcampaigns_string_value($values, 'utm_campaign'),
        'term' => qlcampaigns_string_value($values, 'utm_term'),
        'content' => qlcampaigns_string_value($values, 'utm_content'),
    );
}

function qlcampaigns_has_campaign($campaign) {
    return is_array($campaign)
        && ($campaign['source'] !== '' || $campaign['medium'] !== '' || $campaign['campaign'] !== '');
}

function qlcampaigns_breakdown_items($counts, $limit) {
    arsort($counts);
    $items = array();
    foreach (array_slice($counts, 0, $limit, true) as $label => $clicks) {
        $items[] = array('label' => $label, 'clicks' => (int)$clicks);
    }
    return $items;
}

function qlcampaigns_increment(&$counts, $label) {
    $counts[$label] = isset($counts[$label]) ? $counts[$label] + 1 : 1;
}

function qlcampaigns_label($value, $fallback) {
    $value = trim((string)$value);
    return $value === '' ? $fallback : $value;
}

function qlcampaigns_created_keyword($result, $fallback) {
    if (isset($result['url']['keyword'])) {
        return (string)$result['url']['keyword'];
    }
    if (isset($result['keyword'])) {
        return (string)$result['keyword'];
    }
    return (string)$fallback;
}

function qlcampaigns_result_value($result, $key) {
    if (isset($result[$key])) {
        return (string)$result[$key];
    }
    if (isset($result['url'][$key])) {
        return (string)$result['url'][$key];
    }
    return '';
}

function qlcampaigns_string_value($array, $key) {
    return isset($array[$key]) ? trim((string)$array[$key]) : '';
}

function qlcampaigns_request_string($keys) {
    if (!is_array($keys)) {
        $keys = array($keys);
    }

    foreach ($keys as $key) {
        if (isset($_REQUEST[$key])) {
            return trim((string)$_REQUEST[$key]);
        }
    }
    return '';
}

function qlcampaigns_request_range() {
    $default_end = new DateTimeImmutable('today', new DateTimeZone('UTC'));
    $default_start = $default_end->modify('-29 days');

    $start = qlcampaigns_parse_day(isset($_REQUEST['date']) ? $_REQUEST['date'] : null, $default_start);
    $end = qlcampaigns_parse_day(isset($_REQUEST['date_end']) ? $_REQUEST['date_end'] : null, $default_end);

    if ($start > $end) {
        return array($end, $start);
    }

    return array($start, $end);
}

function qlcampaigns_parse_day($value, DateTimeImmutable $fallback) {
    if (!is_string($value) || trim($value) === '') {
        return $fallback;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value), new DateTimeZone('UTC'));
    return $date instanceof DateTimeImmutable ? $date : $fallback;
}

function qlcampaigns_format_day(DateTimeImmutable $date) {
    return $date->format('Y-m-d');
}

function qlcampaigns_range_bounds(DateTimeImmutable $start, DateTimeImmutable $end) {
    return array(
        $start->format('Y-m-d 00:00:00'),
        $end->format('Y-m-d 23:59:59'),
    );
}

function qlcampaigns_request_limit($default, $max) {
    $limit = isset($_REQUEST['limit']) ? (int)$_REQUEST['limit'] : $default;
    return max(1, min($limit, $max));
}

function qlcampaigns_build_url($parts) {
    $url = '';
    if (!empty($parts['scheme'])) {
        $url .= $parts['scheme'] . '://';
    }
    if (!empty($parts['user'])) {
        $url .= $parts['user'];
        if (!empty($parts['pass'])) {
            $url .= ':' . $parts['pass'];
        }
        $url .= '@';
    }
    if (!empty($parts['host'])) {
        $url .= $parts['host'];
    }
    if (!empty($parts['port'])) {
        $url .= ':' . $parts['port'];
    }
    if (!empty($parts['path'])) {
        $url .= $parts['path'];
    }
    if (!empty($parts['query'])) {
        $url .= '?' . $parts['query'];
    }
    if (!empty($parts['fragment'])) {
        $url .= '#' . $parts['fragment'];
    }
    return $url;
}

function qlcampaigns_success($payload) {
    return array_merge(array(
        'statusCode' => 200,
        'message' => 'success',
    ), $payload);
}

function qlcampaigns_error($message, $status_code) {
    return array(
        'statusCode' => $status_code,
        'errorCode' => (string)$status_code,
        'message' => $message,
        'simple' => $message,
    );
}

function qlcampaigns_db() {
    return yourls_get_db();
}

function qlcampaigns_log_table_available() {
    return defined('YOURLS_DB_TABLE_LOG') && YOURLS_DB_TABLE_LOG !== '';
}
