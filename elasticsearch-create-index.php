<?php

require_once(dirname(__DIR__) . '/ES-service/event-filters.php');
require_once(dirname(__DIR__) . '/ES-service/extras.php');
require_once(dirname(__DIR__) . '/ES-service/dbconfig.php');
require_once(dirname(__DIR__) . '/vendor/autoload.php');
use Elasticsearch\ClientBuilder;

/**
 * connect to ElasticSearch
 */
function ElasticSearchConnect() {
    $es_host   = 'enter host name';
    $es_port   = 'enter post';
    $es_scheme = 'enter scheme';
    $es_user   = 'enter user';
    $es_pass   = 'enter password';

    $hosts = [
        ['host'   => $es_host, 
         'port'   => $es_port,
         'scheme' => $es_scheme,
         'user'   => $es_user,
         'pass'   => $es_pass ]
    ];

    $clientBuilder = ClientBuilder::create();
    $clientBuilder->setHosts($hosts);
    $client = $clientBuilder->build();

    return $client;
}

/**
 * create event json and import to ElasticSearch
 */
function events_json($hostname,$username,$password,$db) {
    $client = ElasticSearchConnect();
    $mysqli = new mysqli($hostname, $username, $password, $db);
    $date   = date('Y-m-d');
    $cities = get_big_city();

    if (!$mysqli) {
        die("Connection failed: " . mysqli_connect_error());
        echo ' not connected';
    } else {
        $deleteParams = ['index' => 'events'];
        if ($client->indices()->exists($deleteParams)) {
            $response = $client->indices()->delete($deleteParams);
        }

        $events_count = $mysqli->query('SELECT count(a.id) FROM event a 
             INNER JOIN venue b ON a.venue_id = b.id 
             INNER JOIN country c ON a.country_shortcut = c.shortcut 
             WHERE a.end_date >= "'.$date.'" and a.status != "In Bearbeitung"
             ORDER BY a.start_date');
        $count = ceil($events_count->fetch_array()[0]/1500);

        echo 'Creating an event index ...';

        // sql events
        $sql_event = $mysqli->prepare('SELECT 
             a.id, a.number, a.name, a.start_date, a.end_date, a.doors_time, a.start_time, a.type, a.status, a.last_message,
             a.info, a.presented_by, a.url, a.lineup, a.misc_info, a.adv_price, a.box_price, a.picture, a.picture_title, a.local_agency_name,
             (select d.url from local_agency d WHERE d.name = a.local_agency_name LIMIT 1 ) AS local_agency_url,
             a.venue_id, a.venue_name, a.city_name, a.region_name, a.country_shortcut, c.name AS country, b.plz, b.street, b.url as venue_url, b.access,
             a.show_concert_dates, a.ticket_link, a.date_created, a.date_modified,
             (select e.last_mod_date from last_mod_date e WHERE e.object_id = a.id and e.object_type = "event" LIMIT 1 ) AS last_date_modified
        FROM event a 
        INNER JOIN venue b ON a.venue_id = b.id 
        INNER JOIN country c ON a.country_shortcut = c.shortcut 
        WHERE a.end_date >= "'.$date.'" and a.status != "In Bearbeitung"
        ORDER BY a.start_date ASC LIMIT ?, 1500');

        for ($i = 0; $i < $count; $i++) {
            $num = ($i == 0) ? 0 : ($i * 1500);

            $event_ids = array();
            $events = array();
            $bands = array();
            $concerts = array();
            $results = array();

            // execute query event
            $sql_event->bind_param("s", $num);
            $sql_event->execute();

            if ($result = $sql_event->get_result()) {
                while ($field = $result->fetch_array()) {
                    $event_ids[] = $field['id'];

                    if (!empty($field['event_slug']) && $field['event_slug'] != '') {
                        $slug = $field['event_slug'];
                    } else {
                        $slug = ($field['type'] == 'normal') ? $field['city_name'] . '-' . $field['venue_name'] . '-' . new_date_format($field['start_date']) : $field['name'] . '-' . $field['city_name'] . '-' . new_date_format($field['start_date']);
                    }
                    $slug = replace_character($slug);

                    if ($field['country_shortcut'] === 'D') {
                        $region = (!empty($field['plz']) && $field['plz'] != null ) ? $field['country_shortcut'] . '-PLZ ' . substr($field['plz'], 0, 1) : null;
                    } else {
                        $region = (!empty($field['country'])) ? $field['country'] : null;
                    }

                    $count_date = date_diff(new DateTime(), new DateTime($field['date_created']))->days;
                    $event_new = 'no';

                    if (!empty($cities)) {
                        foreach ($cities as $city){
                            if ($field['city_name'] == $city['city_name'] && $count_date < $city['city_new_event']) {
                                $event_new = 'yes';
                            }
                        }
                    }

                    // array event info
                    $events[] = array(
                        "event_id" => $field['id'],
                        "event_number" => $field['number'],
                        "event_type" => $field['type'],
                        "event_name" => $field['name'],
                        "event_slug" => sanitize_title($slug),
                        "event_start_date" => new_date_format($field['start_date']),
                        "event_end_date" => new_date_format($field['end_date']),
                        "event_doors_time" => $field['doors_time'],
                        "event_start_time" => $field['start_time'],
                        "event_venue_id" => $field['venue_id'],
                        "event_venue_name" => $field['venue_name'],
                        "event_venue_slug" => sanitize_title($field['venue_name']),
                        "event_venue_street" => $field['street'],
                        "event_venue_plz" => $field['plz'],
                        "event_venue_url" => $field['venue_url'],
                        "event_venue_access" => $field['access'],
                        "event_city" => $field['city_name'],
                        "event_city_slug" => sanitize_title(replace_character($field['city_name']).'-'.$field['country_shortcut']),
                        "event_region" => $region,
                        "event_country_shortcut" => $field['country_shortcut'],
                        "event_country" => $field['country'],
                        "event_status" => $field['status'],
                        "event_last_message" => $field['last_message'],
                        "event_info" => $field['info'],
                        "event_picture" => $field['picture'],
                        "event_picture_title" => $field['picture_title'],
                        "event_local_agency_name" => $field['local_agency_name'],
                        "event_local_agency_url" => $field['local_agency_url'],
                        "event_presented_by" => $field['presented_by'],
                        "event_site_url" => $field['url'],
                        "event_ticket_link" => $field['ticket_link'],
                        "event_lineup" => $field['lineup'],
                        "event_misc_info" => $field['misc_info'],
                        "event_adv_price" => $field['adv_price'],
                        "event_box_price" => $field['box_price'],
                        "show_concert_dates" => $field['show_concert_dates'],
                        "event_concert_new" => $event_new,
                        "event_creation_date" => new_date_format($field['date_created']),
                        "event_date_modified" => new_date_format($field['date_modified']),
                        "event_last_date_modified" => new_date_format($field['last_date_modified'])
                    );
                }
            }

            // $sql_event->close();
            // sql band and concerts
            $sql_band = $mysqli->prepare('
            SELECT
            a.id, b.event_id, a.band_id, b.bandinfo_id, a.tour_id, d.band_slug, a.band_prefix, a.band_name, a.origin_city, a.origin_country, c.name AS country, 
            a.sub_genre_name, a.sec_genre_name_1, a.sec_genre_name_2, a.sec_genre_name_3, b.id as concert_id, b.date, b.status, b.concert_order,
            a.picture, a.picture_title, a.ticket_link
            FROM bandinfo a 
            INNER JOIN concert b ON a.id = b.bandinfo_id AND b.event_id IN (' . implode(',', $event_ids) . ')
            INNER JOIN country c ON a.origin_country = c.shortcut
            INNER JOIN band d ON a.band_id = d.id 
            /*WHERE b.date >= "$date"*/
            GROUP BY b.event_id, a.band_id, b.date
            ORDER BY b.event_id, b.date, b.concert_order ASC');

            $sql_band->execute();

            $arr = array();

            if ($result = $sql_band->get_result()) {
                while ($band_field = $result->fetch_array()) {
                    $genres = array();

                    $band_name = (!empty($band_field['band_prefix']) && !empty($band_field['band_name'])) ? $band_field['band_prefix'] . ' ' . $band_field['band_name'] : $band_field['band_name'];
                    //$band_slug = replace_character($band_name);

                    if (!empty($band_field['sub_genre_name'])) {
                        array_push($genres, $band_field['sub_genre_name']);
                    }
                    if (!empty($band_field['sec_genre_name_1'])) {
                        array_push($genres, $band_field['sec_genre_name_1']);
                    }
                    if (!empty($band_field['sec_genre_name_2'])) {
                        array_push($genres, $band_field['sec_genre_name_2']);
                    }
                    if (!empty($band_field['sec_genre_name_3'])) {
                        array_push($genres, $band_field['sec_genre_name_3']);
                    }

                    $arr[$band_field['event_id']][] = array(
                        "band_id" => $band_field['band_id'],
                        "bandinfo_id" => $band_field['bandinfo_id'],
                        "band_slug" => $band_field['band_slug'],
                        "band_prefix" => $band_field['band_prefix'],
                        "band_name" => $band_field['band_name'],
                        "band_full_name" => $band_name,
                        "band_origin_city" => $band_field['origin_city'],
                        "band_origin_country" => $band_field['country'],
                        "band_country_shortcode" => $band_field['origin_country'],
                        "band_genres" => $genres,
                        "band_pictures" => $band_field['picture'],
                        "band_picture_name" => $band_field['picture_title'],
                        "band_ticket_link" => $band_field['ticket_link'],
                        "tour_id" => $band_field['tour_id'],
                        "concerts" => array(
                            "concert_id" => $band_field['concert_id'],
                            "concert_date" => new_date_format($band_field['date']),
                            "concert_status" => $band_field['status'],
                            "concert_order" => $band_field['concert_order']
                        )
                    );
                }
            }

            // $sql_event->close();
            foreach ($events as $event) {
                foreach ($arr as $key => $values) {

                    if ($event['event_id'] == $key) {
                        $event['event_picture'] = (!empty($event['event_picture'])) ? $event['event_picture'] : $values[0]['band_pictures'];
                        $event['event_picture_title'] = (!empty($event['event_picture_title'])) ? $event['event_picture_title'] : $values[0]['band_picture_name'];

                        $band_name = (!empty($values[0]['band_prefix']) && !empty($values[0]['band_name'])) ? $values[0]['band_prefix'] .' '. $values[0]['band_name'] : $values[0]['band_name'];
                        $link_param = ($event['event_name'] == '') ? sanitize_title($values[0]['band_prefix'] .' '. $values[0]['band_name']) : urlencode($event['event_name']);

                        if ($event['event_type'] == "normal" && $event['event_ticket_link'] == NULL && $values[0]['band_ticket_link'] == NULL) {
                            $event['event_ticket_link'] = 'https://www.awin1.com/cread.php?awinmid=11388&awinaffid=787169&ued=https://www.eventim.de/search/?searchterm='.urlencode($band_name).'&tab=0';
                        } elseif ($event['event_type'] == "normal"  && $event['event_ticket_link'] == NULL && $values[0]['band_ticket_link'] != NULL && $values[0]['band_ticket_link'] != 'NOLINK') {
                            $event['event_ticket_link'] = 'https://www.awin1.com/cread.php?awinmid=11388&awinaffid=787169&ued=https://www.eventim.de/artist/'.$values[0]['band_ticket_link'].'/';
                        } elseif ($event['event_type'] == "normal" && $event['event_ticket_link'] != NULL && $event['event_ticket_link'] != 'NOLINK' && $values[0]['band_ticket_link'] != NULL && $values[0]['band_ticket_link'] != 'NOLINK') {
                            $event['event_ticket_link'] = 'https://www.awin1.com/cread.php?awinmid=11388&awinaffid=787169&ued=https://www.eventim.de/artist/'.$event['event_ticket_link'].'/';
                        } elseif ($event['event_type'] == "Festival" && $event['event_ticket_link'] == NULL ) {
                            $event['event_ticket_link'] = 'https://www.awin1.com/cread.php?awinmid=11388&awinaffid=787169&ued=https://www.eventim.de/search/?searchterm='.urlencode($event['event_name']).'&tab=0';
                        } elseif ($event['event_type'] == "Festival" && $event['event_ticket_link'] != NULL && $event['event_ticket_link'] != 'NOLINK' )  {
                            $event['event_ticket_link'] = 'https://www.awin1.com/cread.php?awinmid=11388&awinaffid=787169&ued=https://www.eventim.de/artist/'.$event['event_ticket_link'].'/';
                        } else {
                            $event['event_ticket_link'] = '';
                        }

                        if ($event['event_type'] == 'normal') {
                            $title = $values[0]['band_prefix'] .' '. $values[0]['band_name'];
                            $slug = $title . '-' . $event['event_city'] . '-' . $event['event_venue_name'] .'-'. $event['event_start_date'];
                            $event['event_slug'] = sanitize_title($slug);
                        }

                        $event['event_bands'] = $values;
                    }
                }

                $results[] = $event;
            }

            $params = ['body' => []];
            for ($k = 0; $k < count($results); $k++) {
                $params['body'][] = array(
                    'index' => array(
                        '_id' => $results[$k]['event_id'],
                        '_index' => 'events'
                    )
                );

                $params["body"][] = $results[$k];

                // Every 100 documents stop and send the bulk request
                if ($k % 300 == 0) {
                    $responses = $client->bulk($params);

                    // erase the old bulk request
                    $params = ['body' => []];

                    // unset the bulk response when you are done to save memory
                    unset($responses);
                }
            }

            $responses = $client->bulk($params);
            $params = ['body' => []];
            unset($responses);
        }

        echo 'Event index created successfully';
    }
    $mysqli->close();
}



