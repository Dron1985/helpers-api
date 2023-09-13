<?php
/**
 *  ZOHO API Data
 */

const api_url = 'https://recruit.zoho.com/recruit/v2';
class ZohoData {

	/**
	 * Auth token.
	 */
	public $auth_token;

	/**
	 * Module name
	 */
	public $module_name;

	public function __construct() {
		$this->auth_token  = ( new ZohoRecruit() )->getToken();
		$this->module_name = get_option( zoho_module );
	}

	private function headers() {
        $args = [
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $this->auth_token,
            ],
        ];

		return $args;
	}

	/**
	 * function get list jobs opening
	 */
	public function getModulesList() {
		try {
			$response     = wp_remote_get( api_url . "/settings/modules", $this->headers() );
			$responsedata = json_decode( wp_remote_retrieve_body( $response ), TRUE );

			return $responsedata;
		} catch ( Exception $e ) {
			return $e->getMessage();
		}
	}

	/**
	 * Get Records from selected module.
	 */
	public function getRecordsList() {
		if ( $this->module_name ) {
			try {
				$response     = wp_remote_get( api_url . "/$this->module_name?cvid=674403000000112170", $this->headers() );
                //$response     = wp_remote_get( api_url . "/$this->module_name?cvid=544501000000112172", $this->headers() );
				$responsedata = json_decode( wp_remote_retrieve_body( $response ), TRUE );

				return $responsedata;

			} catch ( Exception $e ) {
				return $e->getMessage();
			}
		}
	}

	/**
	 * Get Records from selected module.
	 */
	public function getRecordId( $id ) {
		if ( $id && $this->module_name ) {
			try {
				$response     = wp_remote_get( api_url . "/$this->module_name/$id", $this->headers() );
				$responsedata = json_decode( wp_remote_retrieve_body( $response ), TRUE );

				return $responsedata;
			} catch ( Exception $e ) {
				return $e->getMessage();
			}
		}
	}

	public function getFields() {
		if ( $this->module_name ) {
			try {
				$response     = wp_remote_get( api_url . "/settings/fields?module=$this->module_name", $this->headers() );
				$responsedata = json_decode( wp_remote_retrieve_body( $response ), TRUE );

				return $responsedata;

			} catch ( Exception $e ) {
				return $e->getMessage();
			}
		}
	}

}


/**
 * Create "virtual page" for open position
 *
 */
//Add a wp query variable to redirect to
add_action('query_vars', 'set_query_var_jobs');
function set_query_var_jobs($vars)
{
    array_push($vars, 'job_id'); // ref url redirected to in add rewrite rule
    return $vars;
}

//Create a redirect
add_action('init', 'custom_add_rewrite_rule');
function custom_add_rewrite_rule()
{
    add_rewrite_rule('^job-openings', 'index.php?job_id=1', 'top');
    flush_rewrite_rules();
}

//Return the file we want...
add_filter('template_include', 'include_custom_template');
function include_custom_template($template)
{
    if (get_query_var('job_id')) {
        $template = get_template_directory() . "/templates/page-job-detail.php";
    }
    return $template;
}


function storeZohoPostions( $positions ) {
    // Get any existing copy of our transient data
    if ( false === ( $zoho_data = get_transient( 'zoho_positions' ) ) ) {
        // It wasn't there, so regenerate the data and save the transient for 24 hours
        $zoho_data = serialize($positions);
        set_transient( 'zoho_positions', $zoho_data, 24 * HOUR_IN_SECONDS );
    }
}

function flushStoredInformation() {
    //Delete transient to force a new pull from the API
    delete_transient( 'zoho_positions' );
}

function get_zoho_positions() {
    $zoho_data = array();

    // Get any existing copy of our transient data
    $zoho      = new ZohoData();
    $data_arr  = $zoho->getRecordsList();

      if (isset($data_arr['data'])) {

    //    if (false === ($zoho_data = get_transient('zoho_positions'))) {
            // It wasn't there, so make a new API Request and regenerate the data

            if ($data_arr != '') {
                $zoho_data = array();

                foreach ($data_arr['data'] as $value) {
                    $zoho_positions = array(
                        'id' => $value['id'],
                        'opening_id' => $value['Job_Opening_ID'],
                        'date' => $value['Date_Opened'],
                        'status' => (isset($value['Job_Opening_Status'])) ? $value['Job_Opening_Status'] : '',
                        'remote_job' => (isset($value['Remote_Job'])) ? $value['Remote_Job'] : '',
                        'type' => (isset($value['Job_Type'])) ? $value['Job_Type'] : '',
                        'title' => $value['Job_Opening_Name'],
                        'location' => (isset($value['State'])) ? $value['State'] : '',
                        'city' => (isset($value['City'])) ? $value['City'] : '',
                        'zipcode' => (isset($value['Zip_Code'])) ? $value['Zip_Code'] : '',
                        'department' => (isset($value['Industry'])) ? $value['Industry'] : '',
                        'description' => (isset($value['Job_Description'])) ? $value['Job_Description'] : ''
                    );

                    array_push($zoho_data, $zoho_positions);
                }
            }
            // Cache the Response
            storeZohoPostions($zoho_data);
   //     } else {
            // Get any existing copy of our transient data
    //        $zoho_data = unserialize(get_transient('zoho_positions'));
    //    }
        // Finally return the data
    }

    return $zoho_data;
}

function count_positions() {
    $count = 0;
    $positions = get_zoho_positions();

    if( $positions != '' ) {
        $count = count($positions);
    }

    return $count;
}

function get_locations() {
    $locations = array();
    $positions = get_zoho_positions();

    if( $positions != '' ) {
        foreach ($positions as $position) {
            if (isset($position['location']) && !empty($position['location'])) {
                $locations[] = $position['location'];
            }
        }

        $locations = array_unique($locations);
        rsort($locations);
    }

    return $locations;
}


function get_job_types() {
    $types = array();
    $positions = get_zoho_positions();

    if( $positions != '' ) {
        foreach ($positions as $position) {
            if (isset($position['type']) && !empty($position['type'])) {
                $types[] = $position['type'];
            }
        }

        $types = array_unique($types);
        sort($types);
    }

    return $types;
}

function get_departments() {
    $departments = array();
    $positions = get_zoho_positions();

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

function get_zoho_results( $locations = false, $types = false,  $departments = false ) {
    $positions = get_zoho_positions();
    if( $positions != '' ) {
        $zoho_data = array();

        foreach($positions as $item ) {
            $location = $item['location'];
            $department = $item['department'];
            $type = $item['type'];

            if ($departments != 'all' || $locations != 'all' || $types != 'all') {
                if ($departments != 'all'  && $locations != 'all' && $types != 'all') {
                    $arg_sort = in_array( $department, array($departments)) && in_array($location, array($locations)) && in_array($type, array($types));

                } elseif ($departments == 'all' && $locations != 'all' && $types != 'all' ) {
                    $arg_sort = in_array($location, array($locations)) && in_array($type, array($types));

                } elseif ($locations == 'all' && $departments != 'all' && $types != 'all' ) {
                    $arg_sort = in_array($department, array($departments)) && in_array($type, array($types));

                } elseif ($types == 'all' && $departments != 'all' && $locations != 'all' ) {
                    $arg_sort = in_array($department, array($departments)) && in_array($location, array($locations));

                } elseif ($departments == 'all' && $locations == 'all' && $types != 'all') {
                    $arg_sort = in_array($type, array($types));

                } elseif ($departments == 'all' && $locations != 'all' && $types == 'all') {
                    $arg_sort = in_array($location, array($locations));

                } elseif ($departments != 'all' && $locations == 'all' && $types == 'all') {
                    $arg_sort = in_array($department, array($departments));

                } else {
                    $arg_sort = '';
                }

                if ($arg_sort) {
                    $zoho_data[] = array(
                        'id'          => $item['id'],
                        'opening_id'  => $item['opening_id'],
                        'date'        => $item['date'],
                        'status'      => $item['status'],
                        'remote_job'  => $item['remote_job'],
                        'type'        => $item['type'],
                        'title'       => $item['title'],
                        'location'    => $item['location'],
                        'city'        => $item['city'],
                        'zipcode'     => $item['zipcode'],
                        'department'  => $item['department'],
                        'description' => $item['description']
                    );
                }
            } else {
                $zoho_data[] = array(
                    'id'          => $item['id'],
                    'opening_id'  => $item['opening_id'],
                    'date'        => $item['date'],
                    'status'      => $item['status'],
                    'remote_job'  => $item['remote_job'],
                    'type'        => $item['type'],
                    'title'       => $item['title'],
                    'location'    => $item['location'],
                    'city'        => $item['city'],
                    'zipcode'     => $item['zipcode'],
                    'department'  => $item['department'],
                    'description' => $item['description']
                );

            }
        }

        ksort($zoho_data);
    }

    // Finally return the data
    return $zoho_data;
}