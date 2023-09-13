<?php
/**
 *  GreenHouse API
 *  
 */

/**
 * new format date
 */
function new_date_format($date) {
    if (!empty($date)) {
        $date_new = DateTime::createFromFormat('Y-m-d\TH:i:sP', $date);
        return $date_new->format('F j, Y');
    } else {
        return '';
    }
}

/**
 * greenhouse api request and return json data
 */
function greenhouse_api_request($type = ''){
    $dashboard = (!empty($type)) ? $type : 'default_dashbord_name';
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://boards-api.greenhouse.io/v1/boards/'.$dashboard.'/jobs?content=true');
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = json_decode(curl_exec($ch),true);
        $response = $response['jobs'];
        curl_close($ch);

        return $response;
    } catch(Exception $e) {
        return $e->getMessage();
    }
}

/**
 * Greenhouse function to return both the job board and job id. Can be destructured to get those values.
 */
function greenhouse_get_job_board_and_job_id() {
    $uri = explode('/', $_SERVER['REQUEST_URI']);
    $job_id = get_query_var('job_id'); // Set it to query var if available
    $job_board = '';
    
    if (!empty($uri)) {

        foreach ($uri as $param) {
            if (is_numeric($param)) {
                $job_id = $param;
            }
        }

        $job_board = (in_array( 'dashbord_name', $uri) && in_array( 'jobs', $uri)) ? 'dashbord_name' : 'default_dashbord_name';
    }
    return ['job_board'=> $job_board, 'job_id'=> $job_id];
}

/**
 * greenhouse api request get job opening info
 */
function greenhouse_job_info( $type, $job_id ){
    $dashboard = (!empty($type)) ? $type : 'default_dashbord_name';
    try {
        $response = '';
        if (!empty($job_id)) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://boards-api.greenhouse.io/v1/boards/'.$dashboard.'/jobs/'.$job_id.'/');
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $response = json_decode(curl_exec($ch),true);
            curl_close($ch);
        }
        return $response;
    } catch(Exception $e) {
        return $e->getMessage();
    }
}

/**
 * Get any existing copy of our transient data
 */
function storeGreenHousePostions($type, $positions ) {
    $field = ($type == 'default_dashbord_name') ? 'greenhouse_positions' : 'greenhouse_positions_new';
    $greenhouse_data = serialize($positions);
    set_transient( $field, $greenhouse_data, 12 * HOUR_IN_SECONDS );
}

/**
 *  Delete transient to force a new pull from the API
 */
function flushStoredInformation() {
    delete_transient( 'greenhouse_positions' );
    delete_transient( 'greenhouse_positions_new' );
}

/**
 * return greenhouse open positions info
 */
function get_greenhouse_positions($type) {
    // Get any existing copy of our transient data
    $field = ($type == 'default_dashbord_name') ? 'greenhouse_positions' : 'greenhouse_positions_new';

    if (empty(get_transient($field))) {
        // It wasn't there, so make a new API Request and regenerate the data
        $positions = greenhouse_api_request($type);

        if( $positions != '' ) {
            $greenhouse_data = array();

            foreach($positions as $item) {
                $offices = array();
                $locations = array();
                if (isset($item['offices']) && is_array($item['offices'])) {
                    foreach ($item['offices'] as $office) {
                        $offices[] = $office['name'];
                        $locations[] = $office['location'];
                    }
                }
                $greenhouse_position = array(
                    'id'          => $item['id'],
                    'date'        => new_date_format($item['updated_at']),
                    'title'       => $item['title'],
                    'office'      => $offices,
                    'location'    => $locations,
                    'job_type'    => (isset($item['metadata'][1]['value'])) ? $item['metadata'][1]['value'] : '',
                    'department'  => (isset($item['departments'][0]['name'])) ? $item['departments'][0]['name'] : '',
                    'short_desc'  => (isset($item['metadata'][2]['value'])) ? $item['metadata'][2]['value'] : '',
                    'description' => $item['content'],
                    'link'        => $item['absolute_url']
                );

                array_push($greenhouse_data, $greenhouse_position);
            }
        }

        // Cache the Response
        storeGreenHousePostions($type, $greenhouse_data);
    } else {
        // Get any existing copy of our transient data
        $greenhouse_data = unserialize(get_transient($field));
    }

    // Finally return the data
    return $greenhouse_data;
}

/**
 * return cout positions
 */
function count_positions() {
    $count = 0;
    $positions = get_greenhouse_positions();

    if( $positions != '' ) {
        $count = count($positions);
    }

    return $count;
}

/**
 * get all locations
 */
function get_greenhouse_locations($type) {
    $locations = array();
    $positions = get_greenhouse_positions($type);

    if( $positions != '' ) {
        foreach ($positions as $position) {
            if (isset($position['office']) && !empty($position['office'])) {
                foreach ($position['office'] as $office) {
                    if ( array_key_exists($office,$locations)) {
                        $locations[$office]++;
                    }
                    else {
                        $locations[$office]=1;
                    }
                }
            }
        }
        arsort($locations,1);
    }

    return $locations;
}

/**
 * get all departments
 */
function get_greenhouse_departments($type) {
    $departments = array();
    $positions = get_greenhouse_positions($type);

    if( $positions != '' ) {
        foreach ($positions as $position) {
            if (isset($position['department']) && !empty($position['department'])) {
                $departments[] = $position['department'];
            }
        }

        $departments = array_unique($departments);
        sort($departments);
    }

    return $departments;
}

/**
 * get all job types
 */
function get_greenhouse_job_types($type) {
    $job_types = array();
    $positions = get_greenhouse_positions($type);

    if( $positions != '' ) {
        foreach ($positions as $position) {
            if (isset($position['job_type']) && !empty($position['job_type'])) {
                $job_types[] = $position['job_type'];
            }
        }

        $job_types = array_unique($job_types);
        sort($job_types);
    }

    return $job_types;
}

/**
 * Create "virtual page" for open position
 * Add a wp query variable to redirect to
 */
add_action('query_vars', 'set_query_var_jobs');
function set_query_var_jobs($vars){
    array_push($vars, 'job_id'); // ref url redirected to in add rewrite rule
    array_push($vars, 'gh_jid');
    array_push($vars, 'gh_src');
    return $vars;
}

/**
 * Create a redirect
 */
add_action('init', 'custom_add_rewrite_rule');
function custom_add_rewrite_rule() {
   /* $url = explode('/', $_SERVER['REQUEST_URI']);
    if (!empty($url) && (in_array('early-talent', $url) && in_array('jobs', $url) || in_array('careers', $url) && in_array('jobs', $url))) {
        $job_id = '';
        foreach ($url as $param) {
            if (is_numeric($param)) {
                $job_id = $param;
            }
        }

        if (!empty($job_id)) {
            add_rewrite_rule('^'.substr($_SERVER['REQUEST_URI'], 1, -1), 'index.php?job_id=1', 'top');
            flush_rewrite_rules();
        }
    } */

    $positions1 = get_greenhouse_positions('default_dashbord_name');
    if (!empty($positions1)) {
        foreach ($positions1 as $position) {
            add_rewrite_rule('^careers/jobs/' . sanitize_title($position['title']) . '/' . $position['id'], 'index.php?job_id=1', 'top');
        }
    }

    $positions2 = get_greenhouse_positions('dashbord_name');
    if (!empty($positions2)) {
        foreach ($positions2 as $position) {
            add_rewrite_rule('^careers/early-talent/jobs/' . sanitize_title($position['title']) . '/' . $position['id'], 'index.php?job_id=1', 'top');
        }
    }
    flush_rewrite_rules();
}

/**
 * include template for virtual single page
 */
add_filter('template_include', 'include_custom_template');
function include_custom_template($template)
{
    if (get_query_var('job_id')) {
        $template = get_template_directory() . "/templates/page-job-detail.php";
    }
    return $template;
}
