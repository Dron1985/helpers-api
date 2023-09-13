<?php
/**
 *  Lever API
 */
function lever_api_request(){
    //get detail job https://api.lever.co/v0/postings/dataiku/position-id?key=api-key
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.lever.co/v0/postings/company_name/');
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = json_decode(curl_exec($ch),true);
        curl_close($ch);

        return $response;
    } catch(Exception $e) {
        return $e->getMessage();
    }

}

function storeLeverPostions( $positions ) {
    // Get any existing copy of our transient data
   if ( false === ( $lever_data = get_transient( 'lever_positions' ) ) ) {
        // It wasn't there, so regenerate the data and save the transient for 12 hours
        $lever_data = serialize($positions);
        set_transient( 'lever_positions', $lever_data, 24 * HOUR_IN_SECONDS );
   }
}

function flushStoredInformation() {
    //Delete transient to force a new pull from the API
    delete_transient( 'lever_positions' );
}

function get_lever_positions() {
    // Get any existing copy of our transient data
    if ( false === ( $lever_data = get_transient( 'lever_positions' ) ) ) {
        // It wasn't there, so make a new API Request and regenerate the data
        $positions = lever_api_request();
        if( $positions != '' ) {
            $lever_data = array();

            foreach($positions as $item) {
                $lever_position = array(
                    'id' => $item['id'],
                    'title' => $item['text'],
                    'location' => $item['categories']['location'],
                    'commitment' => (isset($item['categories']['commitment'])) ? $item['categories']['commitment'] : '',
                    'department' => $item['categories']['team'],
                    'description' => $item['description'],
                    'lists' => $item['lists'],
                    'additional' => $item['additional'],
                    'hostedUrl' => $item['hostedUrl'],
                    'applyUrl' => $item['applyUrl'],
                    'createdAt' => $item['createdAt']
                );

                array_push($lever_data, $lever_position);
            }
        }
        // Cache the Response
        storeLeverPostions($lever_data);
    } else {
        // Get any existing copy of our transient data
        $lever_data = unserialize(get_transient( 'lever_positions' ));
    }
    // Finally return the data

    return $lever_data;
}

function count_positions() {
    $count = 0;
    $positions = get_lever_positions();

    if( $positions != '' ) {
        $count = count($positions);
    }

    return $count;
}

function get_lever_locations() {
    $locations = array();
    $positions = get_lever_positions();

    if( $positions != '' ) {
        foreach ($positions as $position) {
            $locations[]  = (isset($position['location']) && !empty($position['location'])) ? $position['location'] : '';
        }

        $locations = array_unique($locations);
        sort($locations);
    }

    return $locations;
}

function get_lever_departments() {
    $departments = array();
    $positions = get_lever_positions();

    if( $positions != '' ) {
        foreach ($positions as $position) {
            $departments[]  = (isset($position['department']) && !empty($position['department'])) ? $position['department'] : '';
        }

        $departments = array_unique($departments);
        sort($departments);
    }

    return $departments;
}

function get_lever_results( $locations = false, $departments = false ) {
    $positions = get_lever_positions();

    if( $positions != '' ) {
        $lever_data = array();

        foreach($positions as $item ) {
            $location = $item['location'];
            $department  = $item['department'];

            if ($departments || $locations ) {
                if (!empty($departments) && !empty($locations) ) {
                    $arg_sort = in_array( $department, $departments) && in_array($location, $locations);
                } elseif (!empty($departments) && empty($locations) ) {
                    $arg_sort = in_array( $department, $departments);
                } elseif (empty($departments) && !empty($locations) ) {
                    $arg_sort = in_array($location, $locations);
                } else {
                    $arg_sort = '';
                }

                if ( $arg_sort ) {
                    $lever_data[$department][] = array(
                        'id' => $item['id'],
                        'title' => $item['title'],
                        'location' => $item['location'],
                        'commitment' => (isset($item['commitment'])) ? $item['commitment'] : '',
                        'department' => $item['department'],
                        'description' => $item['description'],
                        'lists' => $item['lists'],
                        'additional' => $item['additional'],
                        'hostedUrl' => $item['hostedUrl'],
                        'applyUrl' => $item['applyUrl'],
                        'createdAt' => $item['createdAt']
                    );
                }
            } else {
                $lever_data[$department][] = array(
                    'id' => $item['id'],
                    'title' => $item['title'],
                    'location' => $item['location'],
                    'commitment' => (isset($item['commitment'])) ? $item['commitment'] : '',
                    'department' => $item['department'],
                    'description' => $item['description'],
                    'lists' => $item['lists'],
                    'additional' => $item['additional'],
                    'hostedUrl' => $item['hostedUrl'],
                    'applyUrl' => $item['applyUrl'],
                    'createdAt' => $item['createdAt']
                );

            }

        }

        ksort($lever_data);
    }

    // Finally return the data
    return $lever_data;
}

function get_lever_details( $position_id = false ) {
    $positions = get_lever_positions();

    if( $positions != '' ) {
        $position_data = array();

        foreach($positions as $item ) {
            if ( $position_id == $item['id'] ) {
                $position_data = array(
                    'id' => $item['id'],
                    'title' => $item['title'],
                    'location' => $item['location'],
                    'commitment' => (isset($item['commitment'])) ? $item['commitment'] : '',
                    'department' => $item['department'],
                    'description' => $item['description'],
                    'lists' => $item['lists'],
                    'additional' => $item['additional'],
                    'hostedUrl' => $item['hostedUrl'],
                    'applyUrl' => $item['applyUrl'],
                    'createdAt' => $item['createdAt']
                );
            }
        }
    }

    // Finally return the data
    return $position_data;
}


/**
 * Rewrite for virtualpage
 */
function rewrite_rules_jobs() {

    if (function_exists ('icl_object_id') ) {
        $career_page_id = get_page_ID_by_page_template_and_lang('templates/page-careers-2022.php', ICL_LANGUAGE_CODE);

        $en_link        = apply_filters( 'wpml_permalink', get_the_permalink($career_page_id) , 'en', true );
        $en_url_path    = parse_url($en_link)['path'];

        $url= trim($en_url_path, '/'.ICL_LANGUAGE_CODE.'/');
    //    $en_url         = trim($en_url_path, '/en/');
    //    $de_link        = apply_filters( 'wpml_permalink', get_the_permalink($career_page_id) , 'de', true );
    //    $de_url_path    = parse_url($de_link)['path'];
    //    $de_url         = trim($de_url_path, '/de/');
    //    $url= trim($de_url_path, '/de/');

    } else {
        $career_page_id = get_page_ID_by_page_template('templates/page-careers-2022.php');
        $career_page    = parse_url(get_the_permalink($career_page_id))['path'];
        $url            = trim($career_page, '/');
    }

    add_rewrite_tag('%virtualjobs%', '([^&]+)');
    add_rewrite_tag('%virtualjob_details%', '([^&]+)');

    if (function_exists ('icl_object_id') ) {
    //    add_rewrite_rule($en_url.'/([^/]*)/?$', 'index.php?virtualjobs=job-single&virtualjob_details=$matches[1]', 'top');
    //    add_rewrite_rule($de_url.'/([^/]*)/?$', 'index.php?virtualjobs=job-single&virtualjob_details=$matches[1]', 'top');

        add_rewrite_rule($url.'/([^/]*)/?$', 'index.php?virtualjobs=job-single&virtualjob_details=$matches[1]', 'top');
    } else {
        add_rewrite_rule($url.'/([^/]*)/?$', 'index.php?virtualjobs=job-single&virtualjob_details=$matches[1]', 'top');
    }
}


function template_include($template) {
    global $wp_query;
    $new_template = '';

    if (array_key_exists('virtualjob_details', $wp_query->query_vars)) {
        switch ($wp_query->query_vars['virtualjobs']) {
            case 'job-single':
                $new_template = locate_template(array('templates/page-job-detail.php'));
                break;
        }

        if ($new_template != '') {
            return $new_template;
        } else {
            $wp_query->set_404();
            status_header(404);
            return get_404_template();
        }
    }

    return $template;
}
add_action('init', 'rewrite_rules_jobs');

add_filter('query_vars', function ($vars){
    $vars[] = 'virtualjobs';
    return $vars;
});
add_filter('template_include', 'template_include', 10, 1);




