<?php

/**
 * Apply credentials and return Google_Service_YouTube object.
 */
function get_youtube_service($google_api) {
    $service = NULL;
    if (array_has_key($google_api, ['application_name'], TRUE) && array_has_key($google_api, ['api_key'], TRUE)) {
        $client = new Google_Client();
        $client->setApplicationName($google_api['application_name']);
        $client->setDeveloperKey($google_api['api_key']);

        // Define service object for making API requests.
        $service = new Google_Service_YouTube($client);
    }

    return $service;
}

/**
 * Load video ID from playlist.
 */
function load_youtube_playlist($service, string $playlist_id) : array {
    $result = [];
    $err = '';

    $queryParams = ['playlistId' => $playlist_id, 'maxResults' => 30];
    try {
        $response = $service->playlistItems->listPlaylistItems('contentDetails', $queryParams);
    } catch (Exception $e) {
        $err = $e->getMessage();
    }

    if (empty($err) && $response && $items = $response->getItems()) {
        foreach ($items as $value) {
            $result[] = $value->getContentDetails()->getVideoId();
        }
    }

    return $result;
}

/**
 * Video detail about video list.
 */
function load_youtube_videos($service, array $items_id) : array {
    $result = [];
    if (!$items_id) {
        return $result;
    }

    $err = '';
    $queryParams = ['id' => implode(',', $items_id)];
    try {
        $response = $service->videos->listVideos('snippet', $queryParams);
    } catch (Exception $e) {
        $err = $e->getMessage();
    }

    if (empty($err) && $response && $items = $response->getItems()) {
        foreach ($items as $value) {
            $snippet = $value->getSnippet();
            $thumbs = $snippet->getThumbnails()->getStandard();

            $result[$value->getId()] = [
                'title' => $snippet->getTitle(),
                'date' => $snippet->getPublishedAt(),
                'thumbnails' => $thumbs->getUrl(),
            ];
        }
    }

    return $result;
}


/**
 * Check is key exists.
 */
function array_has_key(array $array, array $parents, $check_is_empty = FALSE): bool {
    $ref = &$array;
    foreach ($parents as $parent) {
        if (is_array($ref) && array_key_exists($parent, $ref)) {
            $ref = &$ref[$parent];
        }
        else {
            return FALSE;
        }
    }
    return $check_is_empty ? !empty($ref) : TRUE;
}
