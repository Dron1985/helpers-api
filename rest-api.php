<?php
/**
 * Post Types Stories, Plugins, Partners, White Papers, Webinars, Samples, Events
 * /wp-json/wp/v2/posts
 * /wp-json/wp/v2/filter-params/?type=stories
 * /wp-json/wp/v2/filters-params?filter[categories-1]=2,3&filter[categories-2]=2,3&filter[categories-3]=2,3&page=2
 * /wp-json/wp/v2/events/?filter[events-type]=43

/**
 * Add custom endpoint for Post Types Stories, Plugins, Partners, White Papers, Samples, Events
 */
add_action('rest_api_init', function () {

    register_rest_route('wp/v2', '/filter-params/', array(
        'methods' => 'GET',
        'callback' => 'filter_params',
    ));

});


/**
 * Add new fields for endpoint Post Types Stories, Plugins, Partners, White Papers, Samples, Events
 */
add_action('rest_api_init', 'add_custom_fields_to_post');
function add_custom_fields_to_post(){
    register_rest_field(array('partners', 'stories', 'plugins', 'white-papers', 'samples', 'events', 'webinars', 'features', 'solutions'),
        'featured_media_src',
        array(
            'get_callback' => 'featured_media_src',
        )
    );

    register_rest_field(array('partners', 'stories', 'plugins', 'white-papers', 'samples', 'events', 'webinars', 'features', 'solutions'),
        'type_link',
        array(
            'get_callback' => 'get_external_link',
        )
    );

    register_rest_field(array('partners', 'stories', 'plugins', 'white-papers', 'samples', 'webinars', 'features', 'solutions', 'events'),
        'top_text',
        array(
            'get_callback' => 'get_top_text',
        )
    );

    register_rest_field(array('partners', 'stories', 'plugins', 'white-papers', 'samples', 'webinars', 'features', 'solutions', 'events'),
        'date_text',
        array(
            'get_callback' => 'get_date_text',
        )
    );


    register_rest_field(array('partners', 'stories', 'plugins', 'white-papers', 'samples', 'webinars', 'features', 'solutions', 'events'),
        'type_text',
        array(
            'get_callback' => 'get_type_text',
        )
    );


    register_rest_field(array('partners', 'stories', 'plugins', 'white-papers', 'samples', 'webinars', 'features', 'solutions', 'events'),
        'type_geo',
        array(
            'get_callback' => 'get_type_geo',
        )
    );


    register_rest_field(array('partners', 'stories', 'plugins', 'white-papers', 'samples', 'webinars', 'features', 'solutions', 'events'),
        'event_info',
        array(
            'get_callback' => 'get_event_info',
        )
    );
}


/**
 * get featured image
 */
function featured_media_src($post)
{
    if (isset($post['id'])) {
        $image = get_featured_img_info('medium_large', $post['id']);
        if (!empty($image['src'])) {
            return $image['src'];
        }
    }
    return '';
}


/**
 * get external link
 */
function get_external_link($post)
{
    if (isset($post['id']) ) {
        $arr = array();
        $type = get_field('single_type', $post['id']);

        if ($post['type'] == 'white-papers' || $post['type'] == 'events' || $post['type'] == 'features'  ) {

            if ($type == 'popup') {
                $popup = get_field('single_popup_fields', $post['id']);
                if (is_array($popup) && !empty(array_filter($popup))) {
                    $logo = (isset($popup['logo']['sizes'])) ? $popup['logo']['sizes']['medium_large'] : '';
                    $title = (isset($popup['title']) && !empty($popup['title'])) ? $popup['title'] : '';
                    $description = (isset($popup['description']) && !empty($popup['description'])) ? $popup['description'] : '';
                    $arr['popup'] = array('logo' => $logo, 'title' => $title, 'description' => $description);
                }
            } elseif ($type == 'external') {
                $arr['external_link'] = get_field('single_external_link', $post['id']);
            }

        } elseif ($post['type'] == 'partners') {

            if ($type == 'popup') {
                $popup = get_field('single_popup_fields', $post['id']);
                if (is_array($popup) && !empty(array_filter($popup))) {
                    $logo = (isset($popup['logo']['sizes'])) ? $popup['logo']['sizes']['medium_large'] : '';
                    $title = (isset($popup['title']) && !empty($popup['title'])) ? $popup['title'] : '';
                    $description = (isset($popup['description']) && !empty($popup['description'])) ? $popup['description'] : '';
                    $arr['popup'] = array('logo' => $logo, 'title' => $title, 'description' => $description);
                }
            } elseif ($type == 'external') {
                $arr['external_link'] = get_field('single_external_link', $post['id']);
            } else {
                $arr['single_link'] = get_the_permalink($post['id']);
            }

        } else {
            $arr['single_link'] = get_the_permalink($post['id']);
        }

        if (!empty($arr)) {
            return $arr;
        }
    }
    return '';
}


/**
 * get top text
 */
function get_top_text($post){
    if (isset($post['id']) ) {
        $top_text = (get_field('story_text', $post['id'])) ? get_field('story_text', $post['id']) : '';

        if (!empty($top_text)) {
            return $top_text;
        }
    }

    return '';
}


/**
 * get date
 */
function get_date_text($post){
    if (isset($post['id']) ) {
        $date_text = (get_field('date_text', $post['id'])) ? get_field('date_text', $post['id']) : '';


        if (!empty($date_text)) {
            return $date_text;
        }
    }

    return '';
}


/**
 * get type text
 */
function get_type_text($post){
    if (isset($post['id']) ) {
        $type_text = (get_field('taxonomy', $post['id'])  ) ? get_field('taxonomy', $post['id']) : '';

        if (!empty($type_text)) {
            return $type_text;
        }
    }

    return '';
}


/**
 * get geo id
 */
function get_type_geo($post){
    if (isset($post['id']) ) {
        $geo_text = (get_field('geo', $post['id'])  ) ? get_field('geo', $post['id']) : '';

        if (!empty($geo_text)) {
            return $geo_text;
        }
    }

    return '';
}


/**
 * get event info
 */
function get_event_info($post){
    if (isset($post['id']) ) {
        $event_info = (get_field('event_info', $post['id'])) ? get_field('event_info', $post['id']) : '';

        if (!empty($event_info)) {
            return $event_info;
        }
    }

    return '';
}

/**
 * Change per_page for Post Types Stories, Plugins, Partners, White Papers, Samples, Events
 */
add_filter('rest_stories_collection_params', 'change_post_per_page');
add_filter('rest_plugins_collection_params', 'change_post_per_page');
add_filter('rest_partners_collection_params', 'change_post_per_page');
add_filter('rest_white-papers_collection_params', 'change_post_per_page');
add_filter('rest_samples_collection_params', 'change_post_per_page');
add_filter('rest_solutions_collection_params', 'change_post_per_page');
add_filter('rest_events_collection_params', 'change_post_per_page');
add_filter('rest_webinars_collection_params', 'change_post_per_page');
add_filter('rest_features_collection_params', 'change_post_per_page');
function change_post_per_page($query_params){ 
    $query_params['per_page']['default'] = 30;

    return $query_params;
}


/**
 * filters by params
 */
function filter_params($request){
    $results = array();

    if (isset($_GET['type']) && !empty(esc_sql($_GET['type']))){
        $filters  = array();

        if (get_field('display_filters_'.$_GET['type'], 'option') == 'show') {
            $post_type_obj = get_post_type_object($_GET['type']);
            if (isset($post_type_obj->taxonomies) && !empty($post_type_obj->taxonomies)) {

                foreach ($post_type_obj->taxonomies as $tax) {
                    $categories = get_terms(array(
                        'taxonomy' => $tax,
                        'hide_empty' => true,
                        'fields' => 'all'
                    ));

                    $tax_name = get_taxonomy($tax);

                    if (count($categories) > 0) {
                        foreach ($categories as $category) {
                            $filters[$tax]['category'] = $tax_name->labels->name;
                            $filters[$tax]['params'][] = array('name' => $category->name, 'slug' =>$category->slug );
                        }
                    }

                }
            }

        }

        if (!empty($filters)) {
            $results['filters'] = $filters;
        }

    }

    if (isset($_GET['type'])) {
        $field    = 'feature_post_'.$_GET['type'];
        $featured = get_field($field, 'option');
        $post_arr = array();

        if( $featured ) :
            foreach( $featured as $p) :
                $link     = '';
                $id       = $p->ID;
                $top_text = get_field('story_text', $p->ID );
                $title    = get_the_title($p->ID);
                $text     = $p->post_excerpt;
                $image    = get_featured_img_info('medium_large', $p->ID);
                $type     = get_field('single_type', $p->ID);

                if ($p->post_type == 'white-papers' || $p->post_type == 'events' ) {

                    if ($type == 'popup') {
                        $popup = get_field('single_popup_fields', $p->ID);
                        if (is_array($popup) && !empty(array_filter($popup))) {
                            $logo          = (isset($popup['logo']['sizes'])) ? $popup['logo']['sizes']['medium_large'] : '';
                            $popup_title   = (isset($popup['title']) && !empty($popup['title'])) ? $popup['title'] : '';
                            $description   = (isset($popup['description']) && !empty($popup['description'])) ? $popup['description'] : '';
                            $link          = array('popup' => array('logo' => $logo, 'title' => $popup_title, 'description' => $description));
                        }
                    } elseif ($type == 'external') {
                        $link = array('external_link' => get_field('single_external_link', $p->ID));
                    }

                } elseif ($p->post_type == 'partners') {

                    if ($type == 'popup') {
                        $popup = get_field('single_popup_fields', $p->ID);
                        if (is_array($popup) && !empty(array_filter($popup))) {
                            $logo          = (isset($popup['logo']['sizes'])) ? $popup['logo']['sizes']['medium_large'] : '';
                            $popup_title   = (isset($popup['title']) && !empty($popup['title'])) ? $popup['title'] : '';
                            $description   = (isset($popup['description']) && !empty($popup['description'])) ? $popup['description'] : '';
                            $link          = array('popup' => array('logo' => $logo, 'title' => $popup_title, 'description' => $description));
                        }
                    } elseif ($type == 'external') {
                        $link = array('external_link' => get_field('single_external_link', $p->ID));
                    } else {
                        $link = array('single_link' => get_the_permalink($p->ID));
                    }

                } else {
                    $link = array('single_link' => get_the_permalink($p->ID));
                }

                $post_arr[] = (array('ID' => $id, 'title' => $title, 'text' => $text, 'image' => $image['src'], 'type_link' => $link));
            endforeach;
        endif;

        if (!empty($post_arr)) {
            $results['feature_posts'] = $post_arr;
        }

    }

    return $results;

}

/**
 * REST query
 * @param array $args The query arguments.
 * @param WP_REST_Request $request Full details about the request.
 * @return array $args.
 **/
add_filter('rest_stories_query', 'rest_api_post_add_filter_param', 10, 2);
add_filter('rest_plugins_query', 'rest_api_post_add_filter_param', 10, 2);
add_filter('rest_partners_query', 'rest_api_post_add_filter_param', 10, 2);
add_filter('rest_white-papers_query', 'rest_api_post_add_filter_param', 10, 2);
add_filter('rest_samples_query', 'rest_api_post_add_filter_param', 10, 2);
add_filter('rest_solutions_query', 'rest_api_post_add_filter_param', 10, 2);
add_filter('rest_events_query', 'rest_api_post_add_filter_param', 10, 2);
add_filter('rest_webinars_query', 'rest_api_post_add_filter_param', 10, 2);
add_filter('rest_features_query', 'rest_api_post_add_filter_param', 10, 2);
function rest_api_post_add_filter_param($args, $request)
{
    // Bail out if no filter parameter is set.
    if ((isset($request['filter']) && empty($request['filter'])) || (!isset($_GET['page_num']) && !empty($_GET['page_num']))) {
        return $args;
    }

    $filter = $request['filter'];

    global $wp;
    $vars = apply_filters('query_vars', $wp->public_query_vars);
    foreach ($vars as $var) {
        if (isset($filter[$var])) {
            $args[$var] = $filter[$var];
        }
    }

    $post_type_obj = get_post_type_object($args['post_type']);
    if (isset($post_type_obj->taxonomies) && !empty($post_type_obj->taxonomies)) {
        $tax_arr = array();
        foreach ($post_type_obj->taxonomies as $tax) {
            if (isset($filter[$tax]) && !empty($filter[$tax])) {
                $checked_terms = explode(',', esc_sql($filter[$tax]));
                $tax_arr[] = array(
                    'taxonomy' => $tax,
                    'field' => 'slug', 
                    'terms' => $checked_terms
                );
            }
        }
        if (!empty($tax_arr)) {
            $args['tax_query'] = array(
                'relation' => 'OR',
                $tax_arr
            );
        }
    }

    if (!isset($request['filter']) && !empty($request['filter'])) {
        $field = 'feature_post_' . $args['post_type'];
        $featured = get_field($field, 'option');

        if ($featured) {
            $arr_id = array();
            foreach ($featured as $p) :
                $arr_id[] = $p->ID;
            endforeach;

            $args['post__not_in'] = $arr_id;
        }
    }

    if (isset($_GET['page_num']) && !empty($_GET['page_num'])) {
        $args['paged'] = $_GET['page_num'];
    }

    if (function_exists ('icl_object_id') ) {
        $args['suppress_filters'] = false;
    }

    $args['order'] = 'DESC';
    $args['orderby'] = 'menu_order';

    return $args;
}
