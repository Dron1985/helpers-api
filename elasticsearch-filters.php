<?php
require_once(dirname(__DIR__) . '/vendor/autoload.php');
use Elasticsearch\ClientBuilder;

/**
 * Elastic Search connect
 */
function elasticConnect() {
    $hosts = [
        ['host'   => 'host_name',
         'port'   => 'post',
         'scheme' => 'http',
         'user'   => 'user',
         'pass'   => 'password' ]
    ];

    $clientBuilder = ClientBuilder::create();
    $clientBuilder->setHosts($hosts);
    $client = $clientBuilder->build();

    return $client;
}

/**
 * @param string $type
 * @param string $city
 * @param array $genres
 * @param string $region
 * @param string $date_begin
 * @param string $date_end
 * @param string $city_slug
 * @param string $venue_slug
 * @param string $order
 * @return string
 *
 * function create query param for event filters
 */
function es_query_param_filter($type, $city, $genres, $region,  $date_begin, $date_end, $city_slug, $venue_slug, $order){
    $filters = array();
    $args    = array();
    $dates   = array();

    if (empty($type) && empty($city) && empty($genres) && empty($region) && empty($date_begin) && empty($date_end) && !empty($city_slug) && !empty($venue_slug)) {
        $field = ($type == 'festival' || $type == 'event' ) ? 'event_start_date' : 'band_tours.tour_concerts.concert_date';
        $filters[] = '{"range": {"'.$field.'": {"gte": "' . date('Y-m-d') . '", "format": "yyyy-MM-dd"}}}';
    } else {
        if (!empty($type) || !empty($city) || !empty($region) || !empty($genres) || !empty($city_slug) || !empty($venue_slug) || !empty($order)) {
            if (!empty($type) && ($type == 'festival' || $type == 'festival-xml') ) {
                $args[] = '{"match": {"event_type": { "query": "festival" } }}';
            } elseif (!empty($type) && $type == 'event-xml'){
                $args[] = '{"match": {"event_type": { "query": "normal" } }}';
            }

            if ($type == 'event' && !empty($city)) {
                $args[] = '{"match": {"event_city.keyword": { "query": "'.$city.'", "operator": "and"} }}';
            } elseif ($type == 'festival' && !empty($city)) {
                $args[] = '{"match": {"event_name.keyword": { "query": "'.$city.'", "operator": "and"} }}';
            } elseif (($type == 'band' || $type == 'archive') && !empty($city)) {
                $args[] = '{"match": {"band_full_name.keyword": { "query": "'.$city.'", "operator": "and"} }}';
            }

            if ($type === 'festival' && !empty($region)) {
                $args[] = '{"match": {"event_region": { "query": "'.$region.'", "operator": "and", "fuzziness": "AUTO"} }}';
            } elseif (($type === 'band' || $type === 'archive') && !empty($region)) {
            //    $args[] = '{"match": {"origin_country_name.keyword": { "query": "'.$region.'", "operator": "and", "fuzziness": "AUTO"} }}';
                $args[] = '{"match": {"origin_country_name.keyword": { "query": "'.$region.'", "operator": "and" } }}';
            }

            if (!empty($genres) && count($genres) > 0) {
                $field_genres = ($type == 'festival' || $type == 'event') ? 'event_bands.band_genres.keyword' : 'band_tours.tour_band_info.tour_band_genres.keyword';
                foreach ($genres as $genre) {
                    $args[] = '{"match": {"'.$field_genres.'": { "query": "'.escapeElasticReservedChars($genre).'", "operator": "and"} }}';
                }
            }

            if (!empty($city_slug)) {
                $args[] = '{"match": {"event_city_slug": { "query": "'.$city_slug.'", "operator": "and"} }}';
            }

            if (!empty($venue_slug)) {
                $args[] = '{"match": {"event_venue_slug.keyword": { "query": "'.$venue_slug.'", "operator": "and"} }}';
            }

            if (!empty($order) && $order == 'only_new' ) {
                $args[] = '{"match": {"event_concert_new": { "query": "yes"} }}';
            }

            $filters[] = '"must": ['. implode(', ', $args).']';
        }

        if (!empty($date_begin) || !empty($date_end) || !empty($region)) {
            $date_begin = (!empty($date_begin)) ? date('Y-m-d', strtotime(date('d-m-Y', strtotime($date_begin)))) : '';
            $date_end   = (!empty($date_end)) ? date('Y-m-d', strtotime(date('d-m-Y', strtotime($date_end)))) : '';

            if ($type == 'event' && !empty($region)) {
                $dates[] = '{"term": {"event_venue_name.keyword": "'.$region.'" }}';
            }

            if (!empty($date_begin) && !empty($date_end) ) {
                switch ($type) {
                    case 'event':
                    case 'festival':
                        $dates[] = '{"bool" : {
                                    "should": [
                                        { "bool": {
                                            "must": [
                                                {"range": {"event_start_date": {"gte": "'.$date_begin.'",  "format": "yyyy-MM-dd"}}},
                                                {"range": {"event_end_date": {"lte": "'.$date_end.'",  "format": "yyyy-MM-dd"}}}
                                            ]}
                                        },            
                                        { "bool": {
                                            "must": [
                                                {"range": {"event_start_date": {"lte": "'.$date_begin.'",  "format": "yyyy-MM-dd"}}},
                                                {"range": {"event_end_date": {"gte": "'.$date_begin.'",  "format": "yyyy-MM-dd"}}}
                                            ]}
                                        },
                                        { "bool": {
                                            "must": [
                                                {"range": {"event_start_date": {"lte": "'.$date_end.'",  "format": "yyyy-MM-dd"}}},
                                                {"range": {"event_end_date": {"gte": "'.$date_end.'",  "format": "yyyy-MM-dd"}}}
                                            ]}
                                        }
                                    ]
                                }}';
                        break;
                    case 'archive-event' :
                    case 'event-xml' :
                    case 'festival-xml':
                    case 'archive-event-xml' :
                        $dates[] = '{"bool" : {
                                    "must" : [
                                        {"range": {"event_start_date": {"gte": "'.$date_begin.'",  "format": "yyyy-MM-dd"}}},
                                        {"range": {"event_start_date": {"lte": "'.$date_end.'",  "format": "yyyy-MM-dd"}}}
                                    ]
                                }}';
                        break;
                    case 'band' :
                    case 'band-xml' :
                    case 'archive' :
                        $dates[] = '{"range": {"band_tours.tour_concerts.concert_date": {"gte": "'.$date_begin.'", "lte": "'.$date_end.'", "format": "yyyy-MM-dd"}}}';
                        break;
                    case 'archive-band-xml' :
                        $dates[] = '{"range": {"last_date_modified": {"gte": "'.$date_begin.'", "lte": "'.$date_end.'", "format": "yyyy-MM-dd"}}}';
                        break;
                }
            } else {
                $field_date = ($type == 'band' || $type == 'band-xml' || $type == 'archive-band' || $type == 'archive-band-xml' || $type == 'archive') ? 'band_tours.tour_concerts.concert_date' : 'event_end_date';
                if (!empty($date_end) && ($type == 'archive-event' || $type == 'archive-event-xml' || $type == 'archive-band' || $type == 'archive-band-xml' || $type == 'archive' )) {
                    $dates[] = (!empty($date_end)) ? '{"range": {"'.$field_date.'": {"lte": "' . $date_end . '", "format": "yyyy-MM-dd"}}}' : '';
                } elseif (!empty($date_begin) && ($type == 'event' || $type == 'event-xml' || $type == 'band' || $type == 'band-xml' || $type == 'festival') || $type == 'festival-xml') {
                    $dates[] = (!empty($date_begin)) ? '{"range": {"'.$field_date.'": {"gte": "' . $date_begin. '", "format": "yyyy-MM-dd"}}}' : '';
                }
            }

            $filters[] = '"filter": ['.implode(', ', $dates).']';
        }
    }

    return $filters;
}

/**
 * @param string $type
 * @param string $city
 * @param array $genres
 * @param string $region
 * @param string $date_begin
 * @param string $date_end
 * @param string $city_slug
 * @param string $venue_slug
 * @param string $order
 * @param string $paged
 * @return string
 *
 * function filters events by params
 */
function event_filters($type = '', $city = '', $genres = array(), $region = '',  $date_begin = '', $date_end = '', $city_slug = '', $venue_slug = '', $order = '', $paged = '') {
    $client  = elasticConnect();
    $results = '';
    $filters = es_query_param_filter($type, $city, $genres, $region, $date_begin, $date_end, $city_slug, $venue_slug, $order);
    $index   = ($type == 'archive-event-xml') ? 'archive-events' : 'events';

    if ($type == 'event-xml' || $type == 'festival-xml' || $type == 'archive-event-xml') {
        $paged = (!empty($paged) && $paged != 1) ? ($paged - 1) * 500 : 0;
        $size  = 4000;
    } else {
        $paged = (!empty($paged) && $paged != 1) ? ($paged - 1) * 10 : 0;
        $size  = 10;
    }

    if ($client->indices()->exists(['index' => $index]) ) {
        try {
            $json = (!empty($filters)) ? '{"bool": {'.implode(', ', $filters).'}}' : '{}';
            if (!empty($order) && $order == 'new') {
                $sort = '[{"_script" : { 
                        "script" : "doc[\'event_concert_new.keyword\'].value == \'yes\' ? 1 : 0",
                        "type" : "number",
                        "order" : "DESC"
                        }
                     }, 
                     {"event_start_date": {"order" : "ASC"}}]';
            } else {
                if ($type == 'archive-event-xml') {
                    $fields = 'event_last_date_modified.keyword';
                } else {
                    $fields = ($type == 'event-xml' || $type == 'festival-xml') ? 'event_last_date_modified' : 'event_start_date';
                }
                //$fields = ($type == 'event-xml' || $type == 'festival-xml' || $type == 'archive-event-xml') ? 'event_last_date_modified' : 'event_start_date.keyword';
                $sort = '{"'.$fields.'": { "order": "ASC" }}';
            }

            $params['index'] = $index;
            if (!empty($json)) {
                $params['body']['query'] = json_decode($json);
            }
            $params['body']['sort'] = json_decode($sort);
            $params['body']['size'] = $size;
            $params['body']['from'] = $paged;

            $response = $client->search($params);
        } catch (Exception $e) {
            echo $e->getMessage();
        }

        if (isset($response) && $response['hits']['total']['value'] >=1) {
            $results = $response['hits']['hits'];
        }
    } else {
        echo ($type == 'archive-event-xml') ? 'Index Archive Events not found!' : 'Index Events not found!';
    }

    return $results;
}


/**
 * @param string $type
 * @param string $city
 * @param array $genres
 * @param string $region
 * @param string $date_begin
 * @param string $date_end
 * @param string $city_slug
 * @param string $venue_slug
 * @param string $order
 * @param string $paged
 * @return string
 *
 * function filters events by params
 */
function bands_filters($type = '', $city = '', $genres = array(), $region = '',  $date_begin = '', $date_end = '', $city_slug = '', $venue_slug = '', $order = '', $paged = '') {
    $client  = elasticConnect();
    $results = '';
    $filters = es_query_param_filter($type, $city, $genres, $region, $date_begin, $date_end, $city_slug, $venue_slug, $order);
    $index   = ($type == 'band' || $type == 'band-xml') ? 'bands' : 'archive-bands';
    $sortby  = ($type == 'band') ? 'ASC' : 'DESC';

    if ($type == 'band-xml' || $type == 'archive-band-xml' ) {
        $paged = (!empty($paged) && $paged != 1) ? ($paged - 1) * 1000 : 0;
        $size  = 1000;
    } else {
        $paged = (!empty($paged) && $paged != 1) ? ($paged - 1) * 50 : 0;
        $size  = 50;
    }

    if ($client->indices()->exists(['index' => $index]) ) {
        try {
            $json = (!empty($filters)) ? '{"bool": {'.implode(', ', $filters).'}}' : '{}';

            if ($type == 'band-xml' || $type == 'archive-band-xml') {
                $sort = '{"last_date_modified": {"order" : "DESC"}}';
            } elseif (!empty($order) && $order == 'alphabetically') {
                $sort = '{"band_name.keyword": {"order" : "ASC"}}';
            } else {
                $sort = '{"band_tours.tour_concerts.concert_date": { "order": "'.$sortby.'" }}';
            }

            $params['index'] = $index;
            if (!empty($json)) {
                $params['body']['query'] = json_decode($json);
            }
            $params['body']['sort'] = json_decode($sort);
            $params['body']['size'] = $size;
            $params['body']['from'] = $paged;

            $response = $client->search($params);
        } catch (Exception $e) {
            echo $e->getMessage();
        }

        if (isset($response) && $response['hits']['total']['value'] >=1) {
            $results = $response['hits']['hits'];
        }
    } else {
        echo ($type == 'band' || $type == 'band-xml') ? 'Index Bands not found!' : 'Index Archive Bands not found!';
    }

    return $results;
}


/**
 * @param string $type
 * @param string $city
 * @param array $genres
 * @param string $region
 * @param string $date_begin
 * @param string $date_end
 * @param string $city_slug
 * @param string $venue_slug
 * @param string $order
 * @return integer $count_page
 *
 * function pagination by count items Elastic Search
 */
function custom_pagination($type = '', $city = '', $genres = array(), $region = '',  $date_begin = '', $date_end = '', $city_slug = '', $venue_slug = '', $order = '') {
    $count_page = '';
    $client  = elasticConnect();
    $filters = es_query_param_filter($type, $city, $genres, $region, $date_begin, $date_end, $city_slug, $venue_slug, $order);

    switch ($type) {
        case 'festival-xml':
        case 'festival':
            $title = 'Index Events';
            $index = 'events';
            break;
        case 'event-xml':
        case 'event':
            $title = 'Index Events';
            $index = 'events';
            break;
        case 'archive-event-xml':
            $title = 'Index Archive Events';
            $index = 'archive-events';
            break;
        case 'band-xml':
        case 'band':
            $title = 'Index Bands';
            $index = 'bands';
            break;
        case 'archive-band-xml':
        case 'archive':
            $title = 'Index Archive Bands';
            $index = 'archive-bands';
            break;
    }

    if ($type == 'band-xml' || $type == 'archive-band-xml') {
        $count = 1000;
    } elseif ($type == 'event-xml' || $type == 'festival-xml' || $type == 'archive-event-xml' ) {
        $count = 500;
    } else {
        $count = ($type == 'festival' || $type == 'event') ? 10 : 50;
    }

    if ($client->indices()->exists(['index' => $index]) ) {
        try {
            $json = (!empty($filters)) ? '{"bool": {'.implode(', ', $filters).'}}' : '';
            if ($type == 'festival' || $type == 'event' || $type == 'event-xml' || $type == 'festival-xml' || $type == 'archive-event-xml') {
                if (!empty($order) && $order == 'new') {
                    $sort = '[{"_script" : { 
                    "script" : "doc[\'event_concert_new.keyword\'].value == \'yes\' ? 1 : 0",
                    "type" : "number",
                    "order" : "DESC"
                    }
                 }, 
                 {"event_start_date": {"order" : "ASC"}}]';
                } else {
                    $sort = '{"event_start_date": { "order": "ASC" }}';
                }
            } elseif ($type == 'band' || $type == 'archive' || $type == 'band-xml' || $type == 'archive-band-xml') {
                $sortby = ($type == 'band' || $type == 'band-xml') ? 'ASC' : 'DESC';
                if (!empty($order) && $order == 'alphabetically') {
                    $sort = '{"band_name.keyword": {"order" : "ASC"}}';
                } elseif ($type == 'band-xml' || $type == 'archive-band-xml') {
                    $sort = '{"last_date_modified": {"order" : "DESC"}}';
                } else {
                    $sort = '{"band_tours.tour_concerts.concert_date": { "order": "'.$sortby.'" }}';
                }
            }

            $params['index'] = $index;
            if (!empty($json)) {
                $params['body']['query'] = json_decode($json);
            }
            $params['body']['sort'] = json_decode($sort);
            $params['body']['size'] = 0;
            $params['body']['track_total_hits'] = true;

            $response = $client->search($params);
            $count_page = ceil($response['hits']['total']['value']/$count);
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    } else {
        echo $title .' not found!';
    }

    return $count_page;
}

/**
 * @param string $type
 * @param string $value
 * @return string
 *
 * return city name for autocomplete
 */
function autocomplete($type, $value) {
    $results = array();
    $client  = elasticConnect();

    if (!empty($type) && !empty($value) ) {
        switch ($type) {
            case 'festival':
                $title  = 'Index Events';
                $index  = 'events';
                $source = '"event_name"';
                $field  = 'event_name';
                $type   = '{"match": {"event_type": { "query": "festival" }}},';
                break;
            case 'event':
                $title  = 'Index Events';
                $index  = 'events';
                $source = '"event_city", "event_country_shortcut"';
                $field  = 'event_city';
                $type   = '';
                break;
            case 'band':
                $title  = 'Index Bands';
                $index  = 'bands';
                $source = '"band_full_name"';
                $field  = 'band_full_name';
                $type   = '';
                break;
            case 'archive':
                $title  = 'Index Archive Bands';
                $index  = 'archive-bands';
                $source = '"band_full_name"';
                $field  = 'band_full_name';
                $type   = '';
                break;
        }

    /*    $source = ($type == 'festival') ? '"event_name"' : '"event_city", "event_country_shortcut"';
        $field  = ($type == 'festival') ? 'event_name' : 'event_city';
        $type   = ($type == 'festival') ? '{"match": {"event_type": { "query": "festival" }}},' : ''; */

        $json = '{ "_source" : ['.$source.'],
                   "query" : { 
                        "bool": { 
                            "must" : [ '.$type.'                                
                                { "match_phrase_prefix": { "'.$field.'": "'.$value.'" }}
                            ]
                        }
                   },
                   "aggs": {
                        "city": {
                            "terms":  {
                                "field": "'.$field.'.keyword",
                                "size" : 1000
                            }
                        }
	               },
                   "size" : 0}';

        $params['index'] = $index;
        $params['body'] = json_decode($json);

        if ($client->indices()->exists(['index' => $index]) ) {
            try {
                $response = $client->search($params);

                if (isset($response) && $response['hits']['total']['value'] >=1) {
                    foreach ($response['aggregations']['city']['buckets'] as $item) {
                        $results[] = $item['key'];
                    };
                }
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        } else {
            echo $title .' not found!';
        }
    }

    return $results;
}

/**
 * get all genres
 */
function get_all_genres() {
    $results = array();
    $client  = elasticConnect();

    $json = '{"_source" : ["genre_name"], "query" : { "match_all" : {}}, "size":200}';

    $params['index'] = 'genres';
    $params['body'] = json_decode($json);

    if ($client->indices()->exists(['index' => 'genres']) ) {
        try {
            $response = $client->search($params);

            if (isset($response) && $response['hits']['total']['value'] >=1) {
                foreach ($response['hits']['hits'] as $item) {
                    $results[] = $item['_source']['genre_name'];
                };
            }
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    } else {
        echo 'Index Genres not found!';
    }

    return $results;
}

/**
 * @param string $type
 * @param string $city
 * @param string $region
 * @param string $date_begin
 * @param string $date_end
 * @param string $city_slug
 * @param string $venue_slug
 * @return string
 *
 * function get genres and count
 */
function filter_genres($type = '', $city = '', $genres = array(), $region = '', $date_begin = '', $date_end = '', $city_slug = '', $venue_slug = '') {
    $client  = elasticConnect();
    $results = array();
    $filters = es_query_param_filter($type, $city, $genres, $region, $date_begin, $date_end, $city_slug, $venue_slug, '');
    $source  = ($type == 'festival' || $type == 'event') ? '["event_bands.band_genres"]' : '["band_tours.tour_band_info.tour_band_genres"]';
    $fields  = ($type == 'festival' || $type == 'event') ? 'event_bands.band_genres.keyword' : 'band_tours.tour_band_info.tour_band_genres.keyword';

    switch ($type) {
        case 'festival':
            $title = 'Index Events';
            $index = 'events';
            break;
        case 'event':
            $title = 'Index Events';
            $index = 'events';
            break;
        case 'band':
            $title = 'Index Bands';
            $index = 'bands';
            break;
        case 'archive':
            $title = 'Index Archive Bands';
            $index = 'archive-bands';
            break;
    }

    if ($client->indices()->exists(['index' => $index]) ) {
        try {
            $json = (!empty($filters)) ? '{"bool": {'.implode(', ', $filters).'}}' : '';

            if ($type == 'festival' || $type == 'event') {
                if (!empty($order) && $order == 'new') {
                    $sort = '[{"_script" : { 
                        "script" : "doc[\'event_concert_new.keyword\'].value == \'yes\' ? 1 : 0",
                        "type" : "number",
                        "order" : "DESC"
                        }
                     }, 
                     {"event_start_date": {"order" : "ASC"}}]';
                } else {
                    $sort = '{"event_start_date": { "order": "ASC" }}';
                }
            } elseif ($type == 'band' || $type == 'archive') {
                $sortby = ($type == 'band') ? 'ASC' : 'DESC';
                if (!empty($order) && $order == 'alphabetically') {
                    $sort = '{"band_name.keyword.keyword": {"order" : "ASC"}}';
                } else {
                    $sort = '{"band_tours.tour_concerts.concert_date": { "order": "'.$sortby.'" }}';
                }
            }

            $aggs = '{"genres": {"terms": { "field": "'.$fields.'", "size" : 10000 } }}';

            $params['index'] = $index;
            if (!empty($json)) {
                $params['body']['query'] = json_decode($json);
            }
            $params['body']['_source'] = json_decode($source);
            $params['body']['sort'] = json_decode($sort);
            $params['body']['aggs'] = json_decode($aggs);

            $params['body']['size'] = 10000;

            $response = $client->search($params);

            if (isset($response) && $response['hits']['total']['value'] >=1) {
                foreach ($response['aggregations']['genres']['buckets'] as $item) {
                    $results[] = array('genre' => $item['key'], 'count' => $item['doc_count']);
                };
            }

        } catch (Exception $e) {
            echo $e->getMessage();
        }
    } else {
        echo $title .' not found!';
    }

    return $results;
}

/**
 * get all region
 */
function get_all_region($type = '', $city = '', $genres = array(), $region = '', $date_begin = '', $date_end = '', $city_slug = '', $venue_slug = '') {
    $results = array();
    $client  = elasticConnect();
    $filters = es_query_param_filter($type, $city, $genres, $region, $date_begin, $date_end, $city_slug, $venue_slug, '');

    switch ($type) {
        case 'festival':
            $title  = 'Index Events';
            $index  = 'events';
            $fields = 'event_region';
            break;
        case 'event':
            $title  = 'Index Events';
            $index  = 'events';
            $fields = 'event_venue_name';
            break;
        case 'band':
            $title  = 'Index Bands';
            $index  = 'bands';
            $fields = 'origin_country_name';
            break;
        case 'archive':
            $title  = 'Index Archive Events';
            $index  = 'archive-bands';
            $fields = 'origin_country_name';
            break;
    }

    if ($client->indices()->exists(['index' => $index]) ) {
        try {
            $params['index'] = $index;

            if (!empty($filters)) {
                $json = '{"_source" : ["'.$fields.'"], 
                      "query" : {"bool": {'.implode(', ', $filters).'}}, 
                      "aggs" : { "region": { "terms": { "field": "'.$fields.'.keyword", "size" : 1000 }}}, 
                      "size" : 10000 }';
                $params['body'] = json_decode($json);
            }

            $response = $client->search($params);

            if (isset($response) && $response['hits']['total']['value'] >=1) {
                foreach ($response['aggregations']['region']['buckets'] as $item) {
                    $results[] = $item['key'];
                };
            }
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    } else {
        echo $title .' not found!';
    }

    return $results;
}

/**
 * get big city
 */
function get_big_city() {
    $args = array();
    $results = array();
    $client  = elasticConnect();

    $json = '{"_source": ["city_name", "city_country_shortcut", "city_big", "city_new_event"],
              "query": {"bool": {"must_not": {"match": {"city_big": {"query": "0","operator": "and"}}}} },
              "size" : 100}';

    $params['index'] = 'cities';
    $params['body'] = json_decode($json);

    if ($client->indices()->exists(['index' => 'cities']) ) {
        try {
            $response = $client->search($params);

            if (isset($response) && $response['hits']['total']['value'] >= 1) {
                foreach ($response['hits']['hits'] as $item) {
                    $results[] = array('city_name' => $item['_source']['city_name'], "country_shortcut" => $item['_source']['city_country_shortcut'], "city_big" => $item['_source']['city_big'], "city_new_event" => $item['_source']['city_new_event']);
                };
            }
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    } else {
        echo 'Index Cities not found!';
    }

    return $results;
}


/**
 * get close city
 */
function get_close_city($city = '') {
    $results = array();
    $cities = array();
    $arr = array();
    $client  = elasticConnect();
    $big_cities = get_big_city();
    $type_city = '';

    if (!empty($big_cities)) {
        foreach ($big_cities as $item) {
            if ($city == $item['city_name']) {
              $type_city = 'big';
            }
        }
    }

    if (!empty($type_city) && $type_city == 'big') {
        $json = '{"size" : 100, "_source" : ["close_cities"], "query" : { "match" : { "city_name": { "query": "'.$city.'", "operator": "and"} }}}';
    } else {
        $json = '{"size" : 100, "_source" : ["city_name", "city_country_shortcut"], "query" : { "match" : {"close_cities.close_city_name.keyword": { "query": "'.$city.'", "operator": "and"}}}}';
    }

    $params['index'] = 'cities';
    $params['body'] = json_decode($json);

    if ($client->indices()->exists(['index' => 'cities']) ) {
        try {
            $response = $client->search($params);

            if (isset($response) && $response['hits']['total']['value'] >= 1) {
                if (!empty($type_city) && $type_city == 'big') {
                    foreach ($response['hits']['hits'] as $item) {
                        if (!empty($item['_source']['close_cities'])) {
                            foreach ($item['_source']['close_cities'] as $info) {
                                if (isset($info['close_city_name']) && !empty($info['close_city_name'])) {
                                    $arr[] = array('city_name' => $info['close_city_name'], 'code' => $info['close_city_shortcut']);
                                    $cities[] = '{"match": {"event_city.keyword": { "query": "'.$info['close_city_name'].'", "operator": "and"} }}';
                                }
                            }
                        }
                    };
                } else {
                    foreach ($response['hits']['hits'] as $item) {
                        $arr[] = array('city_name' => $item['_source']['city_name'], 'code' => $item['_source']['city_country_shortcut']);
                        $cities[] = '{"match": {"event_city.keyword": { "query": "'.$item['_source']['city_name'].'", "operator": "and"} }}';
                    };
                }

                if (!empty($cities)) {
                    $args = '"should": ['. implode(', ', $cities).']';
                    $params2['index'] = 'events';

                    if (!empty($args)) {
                        $json = '{"_source" : [], 
                                  "query" : {"bool": {'.$args.'}}, 
                                  "aggs" : { "cities": { "terms": { "field": "event_city.keyword", "size" : 10000 }}}, 
                                  "size" : 10000 }';
                        $params2['body'] = json_decode($json);
                    }

                    $response_2 = $client->search($params2);
                    if (isset($response_2) && $response_2['hits']['total']['value'] >=1) {
                        foreach ($response_2['aggregations']['cities']['buckets'] as $item) {
                            foreach ($arr as $q) {
                                if ($item['key'] === $q['city_name']) {
                                    $results[] = array('close_city_name' => $item['key'], 'close_city_shortcut' => $q['code']);
                                }
                            }
                        }
                    }
                }
            }

        } catch (Exception $e) {
            echo $e->getMessage();
        }
    } else {
        echo 'Index Cities not found!';
    }

    return $results;
}

/**
 * get meta name city and value by city_slug and venue_slug
 */
function get_city_venue($city_slug = '', $venue_slug = '') {
    $results = array();
    $filters = array();
    $client  = elasticConnect();

    if (!empty($city_slug) || !empty($venue_slug)) {
        $args = array();

        if (!empty($city_slug)) {
            $args[] = '{"match": {"event_city_slug": { "query": "'.$city_slug.'", "operator": "and"} }}';
        }

        if (!empty($venue_slug)) {
            $args[] = '{"match": {"event_venue_slug": { "query": "'.$venue_slug.'", "operator": "and"} }}';
        }

        $filters[] = '"must": ['. implode(', ', $args).']';
        $json = '{"_source" : ["event_city", "event_venue"], "query" : {"bool": {'.implode(', ', $filters).'}}, "size" : -1}';

        $params['index'] = 'events';
        $params['body'] = json_decode($json);

        if ($client->indices()->exists(['index' => 'events']) ) {
            try {
                $response = $client->search($params);

                if (isset($response) && $response['hits']['total']['value'] >=1) {
                    foreach ($response['hits']['hits'] as $item) {
                        if (!empty($city_slug) && !empty($item['_source']['event_city'])) {
                            $results['event_city'][] = $item['_source']['event_city'];
                        }

                        if (!empty($venue_slug) && !empty($item['_source']['event_venue'])) {
                            $results['event_venue'][] = $item['_source']['event_venue'];
                        }

                    };
                }
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        } else {
            echo 'Index Events not found!';
        }
    }

    return $results;
}


/**
 * get event details for single page
 */
function get_info_details($page, $value) {
    $results = array();
    $client  = elasticConnect();

    if ($page == 'events' || $page == 'archive-events') {
        $json = '{"bool": {"must": {"match": {"event_slug.keyword": { "query": "'.$value.'", "operator": "and"}}}} }';
        $params['index'] = ($page == 'events') ? 'events' : 'archive-events';
        $index = ($page == 'events') ? 'events' : 'archive-events';
        $title = ($page == 'events') ? 'Index Events' : 'Index Archive Events';
    } elseif ($page == 'bands' || $page == 'archive-bands') {
        $json = '{"bool": {"must": {"match": {"band_slug.keyword": { "query": "'.$value.'", "operator": "and"}}}} }';
        $params['index'] = ($page == 'bands') ? 'bands' : 'archive-bands';
        $index = ($page == 'bands') ? 'bands' : 'archive-bands';
        $title = ($page == 'bands') ? 'Index Bands' : 'Index Archive Bands';
    }

    $params['body']['query'] = json_decode($json);

    if ($client->indices()->exists(['index' => $index]) ) {
        try {
            $response = $client->search($params);

            if (isset($response) && $response['hits']['total']['value'] >=1) {
                $results = $response['hits']['hits'];
            }
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    } else {
        echo $title.' not found!';
    }

    return $results;
}

function escapeElasticReservedChars($query) {
    //$pattern = '/[\\+\\-\\=\\&\\|\\!\\(\\)\\{\\}\\[\\]\\^\\\"\\~\\*\\<\\>\\?\\:\\\\\\/]/';
    $pattern = '/[\\+\\a=\\&\\|\\!\\(\\)\\{\\}\\[\\]\\^\\\"\\~\\*\\<\\>\\?\\:\\\\\\/]/';
    return preg_replace(
        $pattern,
        addslashes('\\$0'),
        $query
    );
}

/**
 * show/hide filter order by event/festival
 */
function filter_orderby($type = '', $city = '', $genres = array(), $region = '',  $date_begin = '', $date_end = '', $city_slug = '', $venue_slug = '', $order = '') {
    $client  = elasticConnect();
    $filters = es_query_param_filter($type, $city, $genres, $region, $date_begin, $date_end, $city_slug, $venue_slug, 'only_new');
    $big_cities = get_big_city();

    if ($client->indices()->exists(['index' => 'events']) ) {
        try {
            $params['index'] = 'events';

            if (!empty($filters)) {
                $json = '{"query" : {"bool": {'.implode(', ', $filters).'}}, 
                      "size" : 10000 }';
                $params['body'] = json_decode($json);
            }

            $response = $client->search($params);

        } catch (Exception $e) {
            echo $e->getMessage();
        }
    } else {
        echo 'Index Events not found!';
    }

    if (!empty($big_cities)) {
        foreach ($big_cities as $item) {
            if ($city == $item['city_name']) {
                return (isset($response) && $response['hits']['total']['value'] >=1) ? true : false;
            }
        }
    } else {
        return false;
    }
}

/**
 * @param array $event_ids
 * @return array
 * get event info for page band/tour details by event_id
 */
function get_event_slug_by_id($type = '', $event_ids = array()){
    $results = array();
    $client  = elasticConnect();
    $index   = (!empty($type) && $type == 'upcoming') ? 'events' : 'archive-events';
    $title   = (!empty($type) && $type == 'upcoming') ? 'Index Events' : 'Index Archive Events';
    $args = array();

    if (!empty($event_ids) && count($event_ids) > 0) {
        foreach ($event_ids as $event_id) {
            $args[] = '{"match": {"event_id": { "query": "'.$event_id.'"} }}';
        }
    }

    $json = '{"_source" : ["event_id", "event_slug", "event_type"], "query" : {"bool": {"should": ['. implode(', ', $args).']}}, "size" : 10000 }';
    $params['index'] = $index;
    $params['body'] = json_decode($json);

    if ($client->indices()->exists(['index' => $index]) ) {
        try {
            $response = $client->search($params);

            if (isset($response) && $response['hits']['total']['value'] >=1) {
                $results = $response['hits']['hits'];
            }
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    } else {

        echo $title. ' not found!';
    }

    return $results;
}


/**
 * get all the bands on tour
 */
function get_bands_on_tour($value){
    $results = array();
    $client  = elasticConnect();

    $json = '{"_source" : ["band_prefix", "band_name", "band_slug"], "query" : {"bool": {"must": {"match": {"band_tours.tour_id": { "query": "'.$value.'", "operator": "and"}}}} } }';
    $params['index'] = 'bands';
    $params['body'] = json_decode($json);

    if ($client->indices()->exists(['index' => 'archive-bands']) ) {
        try {
            $response = $client->search($params);
        } catch (Exception $e) {
            echo $e->getMessage();
        }

        if (isset($response) && $response['hits']['total']['value'] >=1) {
            $results = $response['hits']['hits'];
        }
    } else {
        echo 'Index Bands not found!';
    }

    return $results;
}


/**
 * get archive bands info
 */
function get_archive_band_info($value){
    $results = array();
    $client  = elasticConnect();

    $json = '{"bool": {"must": {"match": {"band_id": { "query": "'.$value.'", "operator": "and"}}}} }';
    $params['index'] = 'archive-bands';
    $params['body']['query'] = json_decode($json);

    if ($client->indices()->exists(['index' => 'archive-bands']) ) {
        try {
            $response = $client->search($params);
        } catch (Exception $e) {
            echo $e->getMessage();
        }

        if (isset($response) && $response['hits']['total']['value'] >=1) {
            $results = $response['hits']['hits'];
        }
    } else {
        echo 'Index Archive Bands not found!';
    }

    return $results;
}


/**
 * @param string $type
 * @param string $value
 * @param string $city
 * @return string
 *
 * function create query param for search
 */
function es_query_param_search($type = '', $value = '', $city = ''){
    $search  = array('"', ' OR ', ' or ', ' AND ', ' and ', '+');
    $replace = array(' ', ' ', ' ', ' ', ' ', ' ');
    $value   = str_replace($search, $replace, $value);
    $value   = preg_replace('/[^A-Za-z0-9 _\-\+\&\ü\ö\ä\Ü\Ö\Ä\ß\É\Ó\Ø\è\é\ô\ó\ð\á\ú\æ\þ\ø\'\*\§\$\=\/]/','',$value);
//    $value   = str_replace($search, $replace, $value);

    if ($type == 'home_full') {
        if (!empty($city) && empty($value)) {
            $json = '{"bool" : {
                "must": {"match": {"event_city.keyword": { "query": "' . $city . '", "operator": "and"} }}
            }}';
        } else {
            $json = '{"bool" : {
                "must": [
                    {"match": {"event_city.keyword": { "query": "' . $city . '", "operator": "and"} }},
                    {"bool" : 
                        {"should": [
                            { "bool": {
                                "must": 
                                    {"match": {"event_name.keyword": { "query": "' . $value . '", "operator": "and"} }}
                                }
                            },            
                            { "bool": {
                                "must": 
                                    {"match": {"event_bands.band_full_name.keyword": { "query": "' . $value . '", "operator": "and"} }}
                                }
                            },
                            { "bool": {
                                "must": 
                                    {"match": {"event_venue_name.keyword": { "query": "' . $value . '", "operator": "and"} }}
                                }
                            },
                            { "bool": {
                                "must": 
                                    {"match": {"event_bands.band_genres.keyword": { "query": "' . $value . '", "operator": "and"} }}
                                }
                            }
                        ]}                    
                    }
                ]  
            }}';
        }
    } elseif ($type == 'home_part') {
        if (!empty($city) && empty($value)) {
            $json = '{"bool" : {
                "must": {"match": {"event_city.keyword": { "query": "'.$city.'", "operator": "and"} }}
            }}';
        } else {
            $json = '{"bool" : {
                "must": [
                    {"match": {"event_city.keyword": { "query": "' . $city . '", "operator": "and"} }},
                    {"bool" : 
                        {"should": [
                            { "bool": {
                                "must": 
                                    {"match": {"event_name": { "query": "' . $value . '", "operator": "and"} }}
                                }
                            },            
                            { "bool": {
                                "must": 
                                    {"match": {"event_bands.band_prefix": { "query": "' . $value . '", "operator": "and"} }}
                                }
                            },
                            { "bool": {
                                "must": 
                                    {"match": {"event_bands.band_name": { "query": "' . $value . '", "operator": "and"} }}
                                }
                            },
                            { "bool": {
                                "must": 
                                    {"match": {"event_venue_name": { "query": "' . $value . '", "operator": "and"} }}
                                }
                            },
                            { "bool": {
                                "must": 
                                    {"match": {"event_bands.band_genres": { "query": "' . $value . '", "operator": "and"} }}
                                }
                            }
                        ]}                    
                    }
                ]  
            }}';
        }
    } elseif ($type == 'event' || $type == 'archive-event') {
        $json = '{"multi_match": {
                     "query": "'.$value.'",
                     "operator": "and",
                     "type": "cross_fields",
                     "fields": ["event_name^3", "event_venue_name^2", "event_city^2", "event_country", "event_info^0.5", "event_local_agency_name", "event_lineup^0.5",
                     "event_misc_info^0.5", "event_bands.band_name^3", "event_bands.band_prefix^3","event_bands.band_full_name^3", "event_bands.band_genres"]
                     }
                 }';
    } elseif ($type == 'band') {
        $json = '{"multi_match": {
                     "query": "'.$value.'",
                     "operator": "and",
                     "type": "cross_fields",
                     "fields": ["band_full_name^3", "origin_city^0.5", "origin_country_name^0.5", "band_tours.tour_name", "band_tours.tour_bookag_name", 
                     "band_tours.tour_description^0.5", "band_tours.tour_band_info.tour_band_description^0.5", "band_tours.tour_band_info.tour_band_genres", 
                     "band_tours.tour_band_info.tour_band_label_name^0.5", "band_tours.tour_concerts.event_name^2", "band_tours.tour_concerts.venue_name^2", 
                     "band_tours.tour_concerts.city_name^2", "band_tours.tour_concerts.country_name"]
                     }
                 }';
    } elseif ($type == 'archive-band') {
        $json = '{"multi_match": {
                     "query": "'.$value.'",
                     "operator": "and",
                     "type": "cross_fields",
                     "fields": ["band_full_name^3", "origin_city", "origin_country_name", "band_tours.tour_band_info.tour_band_genres", "band_tours.tour_band_info.tour_band_label_name", "band_tours.tour_concerts.city_name^2"]
                     }
                 }';
    }

    return $json;
}

/**
 * custom home search
 */
function home_search($type = '', $value = '', $city = '', $order = '', $paged = ''){
    $results = array();
    $client  = elasticConnect();
    $paged   = (!empty($paged) && $paged != 1) ? ($paged - 1) * 10 : 0;
    $size    = 10;

    $json  = es_query_param_search($type,$value,$city);
    $index = 'events';
    $sort  = '{"event_start_date": { "order": "ASC" }}';

    if ($client->indices()->exists(['index' => $index]) ) {
        $params['index'] = $index;
        if (!empty($json)) {
            $params['body']['query'] = json_decode($json);
        }

        $params['body']['sort'] = json_decode($sort);
        $params['body']['size'] = $size;
        $params['body']['from'] = $paged;

        try {
            $response = $client->search($params);
        } catch (Exception $e) {
            echo $e->getMessage();
        }

        if (isset($response) && $response['hits']['total']['value'] >=1) {
            $results = $response['hits']['hits'];
        }
    } else {
        echo 'Index Events not found!';
    }

    return $results;
}

/**
 * custom search
 */
function full_search($type = '', $value = ''){
    $results = array();
    $client  = elasticConnect();
    $size    = 150;
    $json    = es_query_param_search($type,$value);

    switch ($type) {
        case 'band':
            $title = 'Index Bands';
            $index = 'bands';
            $sort  = ''; //'{"band_tours.tour_concerts.concert_date": { "order": "DESC" }}';
            break;
        case 'archive-band':
            $title = 'Index Archive Bands';
            $index = 'archive-bands';
            $sort  = '{"band_tours.tour_concerts.concert_date": { "order": "DESC" }}';
            break;
        case 'event':
            $title = 'Index Events';
            $index = 'events';
            $sort  = '{"event_start_date": { "order": "DESC" }}';
            break;
        case 'archive-event':
            $title = 'Index Archive Events';
            $index = 'archive-events';
            $sort  = '{"event_start_date": { "order": "DESC" }}';
            break;
    }

    if ($client->indices()->exists(['index' => $index]) ) {
        $params['index'] = $index;
        if (!empty($json)) {
            $params['body']['query'] = json_decode($json);
        }

        if (!empty($sort)) {
            $params['body']['sort'] = json_decode($sort);
        }

        $params['body']['size'] = $size;

        try {
            $response = $client->search($params);
        } catch (Exception $e) {
            echo $e->getMessage();
        }

        if (isset($response) && $response['hits']['total']['value'] >=1) {
            $results = $response['hits']['hits'];
        }
    } else {
        echo '<div class="error-message">'.$title.' not found!</div>';
    }

    return $results;
}

/**
 * search_pagination
 */
function search_pagination($type = '', $value = '', $city = ''){
    $count_page = '';
    $index  = 'events';
    $count  = 10;
    $client = elasticConnect();
    $json   = es_query_param_search($type, $value, $city);
    $sort   = '{"event_start_date": { "order": "DESC" }}';

    if ($client->indices()->exists(['index' => $index]) ) {
        try {
            $params['index'] = $index;
            if (!empty($json)) {
                $params['body']['query'] = json_decode($json);
            }

            $params['body']['sort'] = json_decode($sort);
            $params['body']['size'] = -1;

            $response = $client->search($params);
            $count_page = ceil($response['hits']['total']['value']/$count);
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    } else {
        echo 'Index Events not found!';
    }

    return $count_page;
}


/**
 * get info band/event by id
 */
function get_info_by_id($type, $post_id){
    $results = array();
    $client = elasticConnect();
    $index  = ($type == 'event') ? array('events', 'archive-events') : array('bands', 'archive-bands');

    if ($type == 'event') {
        $json   = '{"bool" : {"must": {"match": {"event_id": { "query": "'.$post_id.'", "operator": "and"} }} }}';
    } elseif ($type == 'band') {
        $json = '{"bool" : {
                    "must": [
                        {"bool" : 
                            {"should": [                            
                                { "bool": {
                                    "must": 
                                        {"match": {"band_id": { "query": "'.$post_id.'", "operator": "and"} }}
                                    }
                                },          
                                { "bool": {
                                    "must": 
                                        {"match": {"band_tours.tour_band_info.tour_bandinfo_id": { "query": "'.$post_id.'", "operator": "and"} }}
                                    }
                                }
                            ]}                    
                        }
                    ]  
                }}';
    }

    if ($client->indices()->exists(['index' => $index]) ) {
        $params['index'] = $index;
        if (!empty($json)) {
            $params['body']['query'] = json_decode($json);
        }
        $params['body']['size'] = -1;

        try {
            $response = $client->search($params);
        } catch (Exception $e) {
            echo $e->getMessage();
        }

        if (isset($response) && $response['hits']['total']['value'] >=1) {
            $results = $response['hits']['hits'];
        }
    } else {
        echo ($type == 'event') ? 'Index Events/Archive Events not found!' : 'Index Bands/Archive Bands not found!';
    }

    return $results;
}


function check_event_id($paged){
    $results = array();
    $client  = elasticConnect();
    $date    = date('Y-m-d');

    if ( $paged == 1) {
        $symbol = 'lte';
        $date = '2022-09-01';
    } else {
        $symbol = 'gte';
        $date = '2030-12-31';
    }

   // $json = '{"_source" : ["event_id"], "query" : {"match_all": {}}, "size" : 10000 }';
    $json = '{"query" : {"bool" : {"filter": {"range": {"event_start_date": {"'.$symbol.'": "'.$date.'", "format": "yyyy-MM-dd"}}}}  }, "size" : 10000}';
    $params['index'] = 'events';
    $params['body'] = json_decode($json);

    $response = $client->search($params);
    if (isset($response) && $response['hits']['total']['value'] >=1) {
         $results = $response['hits']['hits'];
    }

    return $results;
}

