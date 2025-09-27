<?php

if (!defined('GOODREQ')) {
    die('Access denied');
}

require __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;


function verifyJWT($secretKey)
{
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';

    // Optional: Remove debug statements in production
    // var_dump($secretKey);
    // var_dump($headers);

    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        http_response_code(401);
        exit(json_encode(['status' => 'error', 'message' => 'Authorization token not found']));
    }

    $jwt = $matches[1];

    try {
        $decoded = JWT::decode($jwt, new Key($secretKey, 'HS256'));
        return $decoded;
    } catch (Exception $e) {
        http_response_code(401);
        exit(json_encode(['status' => 'error', 'message' => 'Invalid or expired token: ' . $e->getMessage()]));
    }
}



function yourApiMethod()
{
    // Origin header (sent by browser in CORS requests)
    $origin = $_SERVER['HTTP_ORIGIN'] ?? null;

    // Referer header (sometimes useful fallback)
    $referer = $_SERVER['HTTP_REFERER'] ?? null;

    // Client IP (network-level info)
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? null;

    return [
        [
            'origin' => $origin,
            'referer' => $referer,
            'ip' => $clientIp
        ]
    ];
}




function getCustomBackendUrl($client_domain)
{
    $domain = $_SERVER['HTTP_HOST'];

    // Check for various localhost patterns
    $localhostPatterns = ['localhost', '127.0.0.1', '::1'];

    foreach ($localhostPatterns as $pattern) {
        if (strpos($domain, $pattern) !== false) {
            return 'localhost:3000';
        }
    }

    return $client_domain;
}




function formatActivityRow(array $row): array
{
    // Copy the original row
    $formatted = $row;

    // Map activity_ref_id to correct field
    switch ($row['activity_type']) {
        case 'challenge':
            $formatted['challenge_id'] = $row['activity_ref_id'];
            break;

        case 'module':
            $formatted['module_id'] = $row['activity_ref_id'];
            break;

        case 'badge':
            $formatted['badge_id'] = $row['activity_ref_id'];
            break;
    }

    // Remove the generic field
    unset($formatted['activity_ref_id']);

    return $formatted;
}





/**
 * Determine the lifecycle status of a challenge based on its start and end dates.
 *
 * This function compares only the date parts (ignores time) of the given start and end dates.
 * It works the same way as MySQL's CURDATE() logic:
 * - "active"    → if today's date is between start_date and end_date (inclusive)
 * - "upcoming"  → if today's date is before start_date
 * - "completed" → if today's date is after end_date
 *
 * @param string $startDate Challenge start date (Y-m-d or Y-m-d H:i:s)
 * @param string $endDate   Challenge end date   (Y-m-d or Y-m-d H:i:s)
 *
 * @return string One of "active", "upcoming", or "completed"
 */
function getChallengeStatus(string $startDate, string $endDate): string
{
    // Normalize to midnight (date-only comparison)
    $today = new DateTime('today');
    $start = (new DateTime($startDate))->setTime(0, 0, 0);
    $end = (new DateTime($endDate))->setTime(0, 0, 0);

    if ($today >= $start && $today <= $end) {
        return 'active';
    } elseif ($today < $start) {
        return 'upcoming';
    } else {
        return 'completed';
    }
}







// function getChallengeStatus(string $startDate, string $endDate): string
// {
//     $today = new DateTime();                // Current date
//     $start = new DateTime($startDate);      // Challenge start
//     $end = new DateTime($endDate);        // Challenge end

//     if ($today >= $start && $today <= $end) {
//         return 'active';
//     } elseif ($today < $start) {
//         return 'upcoming';
//     } else {
//         return 'completed';
//     }
// }


function getPublicYoutubeVideoDetails($videoUrl, $apiKey)
{
    try {
        // Extract video ID (supports normal & short links)
        if (preg_match('/(?:v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $videoUrl, $matches)) {
            $videoId = $matches[1];
        } else {
            return [
                'status' => 'error',
                'message' => 'Invalid YouTube URL'
            ];
        }

        // Build API request
        $apiUrl = "https://www.googleapis.com/youtube/v3/videos"
            . "?part=snippet,contentDetails,statistics,recordingDetails"
            . "&id={$videoId}"
            . "&key={$apiKey}";

        // Use cURL for better error handling
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception("cURL Error: " . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("YouTube API returned HTTP code " . $httpCode);
        }

        $data = json_decode($response, true);

        if (empty($data['items'])) {
            return [
                'status' => 'error',
                'message' => 'Video not found or unavailable'
            ];
        }

        $video = $data['items'][0];

        // ✅ Collect hashtags
        $hastag_form_title = extractHashtags($video['snippet']['title'] ?? '');
        $hastag_form_descriptions = extractHashtags($video['snippet']['description'] ?? '');
        $all_hashtags = array_unique(array_merge($hastag_form_title, $hastag_form_descriptions));

        // ✅ Reverse Geocode
        $cityy = isset($video['recordingDetails']['location'])
            ? getNominatimLocation(
                (float) $video['recordingDetails']['location']['latitude'],
                (float) $video['recordingDetails']['location']['longitude']
            )
            : [];

        // ✅ Collect thumbnails
        $thumbnails = $video['snippet']['thumbnails'] ?? [];

        return [
            'status' => 'success',
            'data' => [
                'videoId' => $video['id'],
                'title' => $video['snippet']['title'],
                'description' => $video['snippet']['description'],
                'channelTitle' => $video['snippet']['channelTitle'],
                'publishedAt' => $video['snippet']['publishedAt'],
                'tags' => $video['snippet']['tags'] ?? [],
                'viewCount' => $video['statistics']['viewCount'] ?? 0,
                'likeCount' => $video['statistics']['likeCount'] ?? 0,
                'commentCount' => $video['statistics']['commentCount'] ?? 0,
                'location' => $video['recordingDetails']['location'] ?? null,
                'locationDesc' => $video['recordingDetails']['locationDescription'] ?? null,
                'hashtags' => $all_hashtags,
                'city' => $cityy['city'] ?? null,
                'state' => $cityy['state'] ?? null,
                'country' => $cityy['country'] ?? null,
                'thumbnails' => [
                    'default' => $thumbnails['default']['url'] ?? null,
                    'medium' => $thumbnails['medium']['url'] ?? null,
                    'high' => $thumbnails['high']['url'] ?? null,
                    'standard' => $thumbnails['standard']['url'] ?? null,
                    'maxres' => $thumbnails['maxres']['url'] ?? null,
                ]
            ]
        ];

    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Exception: ' . $e->getMessage()
        ];
    }
}



/**
 * Get public YouTube channel details by channel ID.
 *
 * Returns only channel-related fields:
 * channelId, title, description, publishedAt, country, statistics, thumbnails, banner, keywords.
 *
 * @param string $channelId  The YouTube channel ID.
 * @param string $apiKey     Your YouTube Data API key.
 * @return array             Structured response with status and channel details.
 */
function getPublicYoutubeChannelDetails($channelId, $apiKey)
{
    try {
        if (empty($channelId)) {
            return [
                'status' => 'error',
                'message' => 'Channel ID is required'
            ];
        }

        // Build API request
        $apiUrl = "https://www.googleapis.com/youtube/v3/channels"
            . "?part=snippet,statistics,brandingSettings"
            . "&id={$channelId}"
            . "&key={$apiKey}";

        // Use cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception("cURL Error: " . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("YouTube API returned HTTP code " . $httpCode);
        }

        $data = json_decode($response, true);

        if (empty($data['items'])) {
            return [
                'status' => 'error',
                'message' => 'Channel not found or unavailable'
            ];
        }

        $channel = $data['items'][0];

        // Collect thumbnails
        $thumbnails = $channel['snippet']['thumbnails'] ?? [];

        // Branding banner
        $banner = $channel['brandingSettings']['image']['bannerExternalUrl'] ?? null;

        return [
            'status' => 'success',
            'data' => [
                'channelId' => $channel['id'] ?? null,
                'title' => $channel['snippet']['title'] ?? null,
                'description' => $channel['snippet']['description'] ?? null,
                'publishedAt' => $channel['snippet']['publishedAt'] ?? null,
                'country' => $channel['snippet']['country'] ?? null,
                'statistics' => [
                    'subscriberCount' => $channel['statistics']['subscriberCount'] ?? 0,
                    'videoCount' => $channel['statistics']['videoCount'] ?? 0,
                    'viewCount' => $channel['statistics']['viewCount'] ?? 0,
                ],
                'thumbnails' => [
                    'default' => $thumbnails['default']['url'] ?? null,
                    'medium' => $thumbnails['medium']['url'] ?? null,
                    'high' => $thumbnails['high']['url'] ?? null,
                ],
                'banner' => $banner,
                'keywords' => $channel['brandingSettings']['channel']['keywords'] ?? null
            ]
        ];

    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Exception: ' . $e->getMessage()
        ];
    }
}



// function isValidYoutubeUrl($url)
// {
//     $pattern = '/^(https?\:\/\/)?(www\.)?(youtube\.com\/watch\?v=|youtu\.be\/)[a-zA-Z0-9_-]{11}$/';
//     return (bool) preg_match($pattern, $url);
// }



/**
 * Validate whether a given URL is a valid YouTube video link.
 *
 * This function checks if the input URL matches any of the supported
 * YouTube video formats, including:
 *  - Standard watch links (youtube.com/watch?v=VIDEO_ID)
 *  - Mobile watch links (m.youtube.com/watch?v=VIDEO_ID)
 *  - Shortened links (youtu.be/VIDEO_ID)
 *  - Shorts links (youtube.com/shorts/VIDEO_ID)
 *  - Embed links (youtube.com/embed/VIDEO_ID)
 *
 * It also accepts optional query parameters (e.g., ?t=30s, &feature=share).
 * The function ensures the video ID is exactly 11 valid YouTube characters.
 *
 * @param string $url  The URL to validate.
 * @return bool        True if the URL is a valid YouTube video link, false otherwise.
 */
function isValidYoutubeUrl($url)
{
    $pattern = '/^(https?:\/\/)?((www|m)\.)?(youtube\.com\/(watch\?v=|shorts\/|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})([&?][^\s]*)?$/';
    return (bool) preg_match($pattern, $url);
}




function extractHashtags($text)
{
    // Match all words starting with #
    preg_match_all('/#\w+/', $text, $matches);

    // Return array of hashtags (including #)
    return $matches[0];
}







/**
 * Retrieves location details (city, state, country, and full address) from latitude and longitude 
 * using the Nominatim (OpenStreetMap) reverse geocoding API.
 *
 * @param float $lat  Latitude coordinate.
 * @param float $lng  Longitude coordinate.
 * @return array|null Returns an associative array with keys:
 *                    - city (string|null): City, town, or village name.
 *                    - state (string|null): State/region name.
 *                    - country (string|null): Country name.
 *                    - full_address (string|null): Human-readable complete address.
 *                    Returns null if no address details are found.
 */

function getNominatimLocation($lat, $lng)
{
    $url = "https://nominatim.openstreetmap.org/reverse";
    $params = http_build_query([
        'format' => 'json',
        'lat' => $lat,
        'lon' => $lng,
        'addressdetails' => 1
    ]);

    $context = stream_context_create([
        'http' => [
            'header' => "User-Agent: connectPingnetwork/1.0 (abhinandan@pingnetwork.in)\r\n"
        ]
    ]);

    $response = file_get_contents($url . '?' . $params, false, $context);
    $data = json_decode($response, true);

    if (isset($data['address'])) {
        $address = $data['address'];
        return [
            'city' => $address['city'] ?? $address['town'] ?? $address['village'] ?? null,
            'state' => $address['state'] ?? null,
            'country' => $address['country'] ?? null,
            'full_address' => $data['display_name'] ?? null
        ];
    }

    return null;
}





/**
 * Convert ISO 8601 datetime (e.g., YouTube publishedAt) to desired timezone/format.
 *
 * @param string $datetime   ISO 8601 datetime string (e.g., "2025-09-20T15:03:21Z")
 * @param string $timezone   Target timezone (default: Asia/Kolkata)
 * @param string $format     Output format (default: Y-m-d H:i:s)
 * @return string|false      Converted datetime string or false if invalid
 */
function convertDatetime($datetime, $timezone = 'Asia/Kolkata', $format = 'Y-m-d H:i:s')
{
    try {
        $dt = new DateTime($datetime, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone($timezone));
        return $dt->format($format);
    } catch (Exception $e) {
        trigger_error("Invalid datetime: " . $e->getMessage(), E_USER_WARNING);
        return false;
    }
}






/**
 * Format challenge submission row into desired structure | adds youtube fetched data in youtube sub-object
 *
 * @param array $row - single row from challenge_submissions
 * @return array - formatted row
 */
function formatChallengeSubmissionRow(array $row): array
{
    // List of YouTube-related fields
    $youtubeFields = [
        'video_id',
        'title',
        'thumbnail',
        'description',
        'channel_title',
        'published_at',
        'tags',
        'view_count',
        'like_count',
        'comment_count',
        'location',
        'location_desc',
        'hastags',
        'city'
    ];

    $youtube = [];

    foreach ($youtubeFields as $field) {
        if (array_key_exists($field, $row)) {
            $youtube[$field] = $row[$field];
            unset($row[$field]); // remove from top-level
        }
    }

    // Add nested YouTube object
    $row['youtube'] = $youtube;

    return $row;
}





/**
 * Convert an array to a comma-separated string
 *
 * @param array|string|null $input Input array (or string/null)
 * @param bool $withKeys If true, include keys (key: value)
 * @return string Comma-separated string
 */
function arrayToCommaString($input, $withKeys = false)
{
    if (empty($input)) {
        return '';
    }

    if (!is_array($input)) {
        return (string) $input;
    }

    if ($withKeys) {
        // Include keys: "key: value"
        $pairs = [];
        foreach ($input as $key => $value) {
            $pairs[] = $key . ': ' . $value;
        }
        return implode(', ', $pairs);
    }

    // Only values
    return implode(', ', $input);
}




// /**
//  * Normalize hashtags input into a separated string of hashtags.
//  *
//  * @param string|array $input Array of strings OR comma/space separated string
//  * @param string $separator Separator to use between hashtags (default: space)
//  * @return string|false Normalized hashtags separated by $separator, or false on invalid input
//  */
// function normalizeHashtags($input, $separator = ' ')
// {
//     // Validate separator
//     if (!is_string($separator) || $separator === '') {
//         trigger_error('Separator must be a non-empty string', E_USER_WARNING);
//         return false;
//     }

//     // Validate input type
//     if (!is_string($input) && !is_array($input)) {
//         trigger_error('Input must be a string or an array', E_USER_WARNING);
//         return false;
//     }

//     // Convert array to string
//     if (is_array($input)) {
//         $input = implode(' ', $input);
//     }

//     // Replace commas with spaces
//     $input = str_replace(',', ' ', $input);

//     // Split by any whitespace
//     $tags = preg_split('/\s+/', trim($input));

//     // Remove empty values
//     $tags = array_filter($tags);

//     // Ensure each tag starts with #
//     $tags = array_map(function ($tag) {
//         $tag = ltrim($tag, "#"); // remove existing #
//         return "#" . $tag;
//     }, $tags);

//     // Return separated string
//     return implode($separator, $tags);
// }


/**
 * Normalize hashtags input.
 *
 * @param string|array $input Array of strings OR comma/space separated string
 * @param string $separator Separator to use between hashtags (default: space)
 * @param bool $returnArray If true, return array of hashtags; else return string
 * @return array|string|false Normalized hashtags (array or string), or false on invalid input
 */
function normalizeHashtags($input, $separator = ' ', $returnArray = false)
{
    // Validate separator
    if (!is_string($separator) || $separator === '') {
        trigger_error('Separator must be a non-empty string', E_USER_WARNING);
        return false;
    }

    // Validate input type
    if (!is_string($input) && !is_array($input)) {
        trigger_error('Input must be a string or an array', E_USER_WARNING);
        return false;
    }

    // Convert array to string
    if (is_array($input)) {
        $input = implode(' ', $input);
    }

    // Replace commas with spaces
    $input = str_replace(',', ' ', $input);

    // Split by any whitespace
    $tags = preg_split('/\s+/', trim($input));

    // Remove empty values
    $tags = array_filter($tags);

    // Ensure each tag starts with #
    $tags = array_map(function ($tag) {
        $tag = ltrim($tag, "#"); // remove existing #
        return "#" . $tag;
    }, $tags);

    // Return array or string
    return $returnArray ? array_values($tags) : implode($separator, $tags);
}




/**
 * Convert ISO 8601 timestamp to MySQL DATETIME format
 *
 * @param string $isoTimestamp The ISO 8601 timestamp (e.g., 2025-07-28T15:30:02Z)
 * @param string|null $timezone Optional timezone (default: null, returns UTC)
 * @return string MySQL DATETIME string (YYYY-MM-DD HH:MM:SS)
 */
function isoToMysqlDatetime($isoTimestamp, $timezone = 'Asia/Kolkata')
{
    try {
        // Create DateTime object from ISO timestamp
        $date = new DateTime($isoTimestamp);

        // If timezone provided, set it
        if ($timezone) {
            $date->setTimezone(new DateTimeZone($timezone));
        }

        // Format as MySQL DATETIME
        return $date->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        // Return null if conversion fails
        return null;
    }
}





/**
 * getLocationFromLatLng
 *
 * Retrieves location details (city, state, country) from given latitude and longitude
 * using the Google Maps Geocoding API.
 *
 * @param float  $lat    Latitude of the location.
 * @param float  $lng    Longitude of the location.
 * @param string $apiKey Google Maps API key with Geocoding API enabled.
 *
 * @return array|null Returns an associative array with keys:
 *                   - 'city'    => Name of the city (if available)
 *                   - 'state'   => Name of the state/region (if available)
 *                   - 'country' => Name of the country (if available)
 *                   Returns null if the API request fails or no location data is found.
 *
 * Example usage:
 * $location = getLocationFromLatLng(28.6139, 77.2090, 'YOUR_API_KEY');
 * print_r($location);
 *
 * Output:
 * Array
 * (
 *     [city] => New Delhi
 *     [state] => Delhi
 *     [country] => India
 * )
 */
function getLocationFromLatLng($lat, $lng, $apiKey)
{
    $url = "https://maps.googleapis.com/maps/api/geocode/json?latlng=$lat,$lng&key=$apiKey";

    // Make the HTTP request
    $response = file_get_contents($url);
    if (!$response)
        return null;

    $data = json_decode($response, true);
    if ($data['status'] !== 'OK')
        return null;

    $location = [
        'city' => null,
        'state' => null,
        'country' => null
    ];

    // Loop through address components
    foreach ($data['results'][0]['address_components'] as $component) {
        if (in_array('locality', $component['types'])) {
            $location['city'] = $component['long_name'];
        }
        if (in_array('administrative_area_level_1', $component['types'])) {
            $location['state'] = $component['long_name'];
        }
        if (in_array('country', $component['types'])) {
            $location['country'] = $component['long_name'];
        }
    }

    return $location;
}


function generatePasswordHash($plainPassword)
{
    if (empty($plainPassword)) {
        return null; // or throw error
    }

    return password_hash($plainPassword, PASSWORD_BCRYPT);
}



/**
 * Send a standardized JSON response and exit.
 *
 * Builds a JSON response with required fields (`status`, `message`), 
 * an optional `data` payload, and any additional key-value pairs.
 * 
 * Example:
 * sendJsonResponse(200, 'success', 'Challenge started successfully', $data, ['challengeStatus' => 'upcoming']);
 *
 * @param int         $statusCode  HTTP status code (e.g., 200, 404, 500)
 * @param string      $status      Response status ('success' or 'error')
 * @param string      $message     Response message to describe the outcome
 * @param mixed|null  $data        Optional payload data (array, object, etc.)
 * @param array|null  $extra       Optional associative array of extra fields to merge into the response
 *
 * @return void
 */
function sendJsonResponse(
    int $statusCode,
    string $status,
    string $message,
    $data = null,
    array $extra = null
): void {
    http_response_code($statusCode);

    $response = [
        'status' => $status,
        'message' => $message
    ];

    if (!is_null($data)) {
        $response['data'] = $data;
    }

    if (!is_null($extra) && is_array($extra)) {
        $response = array_merge($response, $extra);
    }

    // header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}




/**
 * Converts an array of objects/arrays into JSON with states and submissions arrays.
 *
 * Example output:
 * {"states":["state1","state2"],"submissions":[1,5]}
 *
 * @param object[]|array $data Array of objects or arrays
 * @param string $stateKey Key name for state in object (default: 'state')
 * @param string $submissionKey Key name for submission in object (default: 'submission')
 * @return string JSON string
 */
function createDfJson($data, $stateKey = 'state', $submissionKey = 'submission')
{
    $result = [
        'states' => [],
        'submissions' => []
    ];

    foreach ($data as $item) {
        if (is_object($item)) {
            $result['states'][] = $item->$stateKey ?? null;
            $result['submissions'][] = $item->$submissionKey ?? null;
        } elseif (is_array($item)) {
            $result['states'][] = $item[$stateKey] ?? null;
            $result['submissions'][] = $item[$submissionKey] ?? null;
        }
    }

    return json_encode($result);
}

// Example usage
// $input = [
//     (object)['state' => 'Maharashtra', 'submission' => 3],
//     (object)['state' => 'Karnataka', 'submission' => 5],
//     (object)['state' => 'Gujarat', 'submission' => 2]
// ];

// // Call the helper function
// $jsonResult = createDfJson($input);

// // Print the JSON
// echo $jsonResult;

// {"states":["Maharashtra","Karnataka","Gujarat"],"submissions":[3,5,2]}





// ----------------------
// Helper functions
// ----------------------

function sanitizeText($value, $maxLen = 255)
{
    $value = trim((string) $value);
    $value = strip_tags($value);
    if ($value === '') {
        return '';
    }
    if (function_exists('mb_substr')) {
        $value = mb_substr($value, 0, $maxLen);
    } else {
        $value = substr($value, 0, $maxLen);
    }
    return $value;
}

function toBoolInt($value, $default = 0)
{
    if ($value === null || $value === '') {
        return (int) $default;
    }
    $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($bool === null) {
        if ($value === '1' || $value === 1)
            return 1;
        if ($value === '0' || $value === 0)
            return 0;
        return (int) $default;
    }
    return $bool ? 1 : 0;
}

function normalizeDate($value)
{
    $value = trim((string) $value);
    if ($value === '')
        return '';
    $ts = strtotime($value);
    if ($ts === false)
        return '';
    return date('Y-m-d', $ts);
}
