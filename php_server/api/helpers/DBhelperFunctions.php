<?php

if (!defined('GOODREQ')) {
    die('Access denied');
}

require __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/connection.php';
// require_once __DIR__ . '/../helperFunctions.php';





function checkCreatorExists($conn, $creatorEmail)
{
    $query = "SELECT user_email, id FROM youtube_creators WHERE user_email = :user_email LIMIT 1";
    $params = ['user_email' => $creatorEmail];
    $result = RunQuery($conn, $query, $params);

    dd($result);

    if (empty($result)) {
        return false;
    } else
        return $result[0]['id'];
}





/**
 * Helper function to update creator row with firebase token
 * @param object $conn Database connection
 * @param int $userid User ID
 * @param string $firebase_token Firebase FCM token
 * @return array|null Returns row data on success, null on failure
 */
function insertOrUpdateCreatorFirebaseToken($conn, $userid, $firebase_token)
{

    dd($conn);
    dd('hhhhhhhhhhhhh');
    dd($userid);
    dd($firebase_token);
    try {
        // Validate and sanitize inputs
        $userid = filter_var($userid, FILTER_VALIDATE_INT);
        $firebase_token = trim($firebase_token);

        // Input validation
        if (!$userid || $userid <= 0) {
            return null;
        }

        // if (empty($firebase_token)) {
        //     return null;
        // }

        dd('helloddd');

        // Check if creator already exists
        $selectQuery = "SELECT * FROM youtube_creators WHERE id = ?";
        $existingCreator = RunQuery($conn, $selectQuery, [$userid]);


        dd($existingCreator);

        ddd(count($existingCreator) > 0);

        if (count($existingCreator) > 0) {
            // Update existing creator's firebase token
            $updateQuery = "UPDATE `youtube_creators` SET `firebase_token` = ? WHERE `youtube_creators`.`id` = ?;";
            $updateResult = RunQuery($conn, $updateQuery, [$firebase_token, $userid], true, true);

            dd($updateResult);

            ddd(isset($updateResult['error']));

            if (isset($updateResult['error']) == false) {
                // Fetch and return updated row
                $updatedCreator = RunQuery($conn, $selectQuery, [$userid]);
                return $updatedCreator[0];
            } else {
                return null;
            }
        }
    } catch (Exception $e) {

        // Log error if needed
        error_log("insertOrUpdateCreator Error: " . $e->getMessage());
        return null;
    }
}



/**
 * Calculate total points earned by a creator across challenges and modules.
 *
 * Logic:
 * - Challenges:
 *   A creator can submit multiple times for the same challenge,
 *   but they only earn the reward points assigned to that challenge once.
 *   (Latest submission counts, but reward_points is fixed per challenge.)
 *
 * - Modules:
 *   A creator can only submit once per module.
 *   They earn the reward points defined in the modules table.
 *
 * Total points = sum of all challenge reward points (where submitted at least once)
 *              + sum of all module reward points (where submitted).
 *
 * @param int   $creatorId  The ID of the creator.
 * @param PDO   $pdo        A PDO connection instance.
 *
 * @return int  The total points earned by the creator.
 */
function getCreatorTotalPoints($creatorId, $pdo)
{
    // Validate input
    if (empty($creatorId) || !is_numeric($creatorId)) {
        return 0;
    }

    $challengePoints = (int) getCreatorChallengePoints($creatorId, $pdo);


    $modulePoints = (int) getCreatorModulePoints($creatorId, $pdo);

    // ---- 3. Total Points ----
    $totalPoints = $challengePoints + $modulePoints;

    return $totalPoints;
}



/**
 * Get the total points earned by a creator from challenge submissions.
 *
 * - Sums up reward_points from the challenges table.
 * - Only counts a challenge once per creator (even if multiple submissions exist).
 *
 * @param int   $creatorId The ID of the creator.
 * @param PDO   $pdo       The PDO database connection.
 *
 * @return int Total challenge points earned by the creator.
 */
function getCreatorChallengePoints($creatorId, $pdo)
{
    // ✅ Validate input
    if (empty($creatorId) || !is_numeric($creatorId)) {
        return 0;
    }

    $challengeQuery = "
        SELECT SUM(c.reward_points) AS total_challenge_points
        FROM challenges c
        WHERE c.id IN (
            SELECT cs.challenge_id
            FROM challenge_submissions cs
            WHERE cs.creator_id = :creator_id
            GROUP BY cs.challenge_id
        )
    ";

    $stmt = $pdo->prepare($challengeQuery);
    $stmt->execute([':creator_id' => $creatorId]);

    return (int) $stmt->fetchColumn();
}

/**
 * Get the total points earned by a creator from module submissions.
 *
 * - Sums up reward_points_can_achieve from the modules table.
 * - Includes all modules submitted by the creator.
 *
 * @param int   $creatorId The ID of the creator.
 * @param PDO   $pdo       The PDO database connection.
 *
 * @return int Total module points earned by the creator.
 */
function getCreatorModulePoints($creatorId, $pdo)
{
    // ✅ Validate input
    if (empty($creatorId) || !is_numeric($creatorId)) {
        return 0;
    }

    $moduleQuery = "
        SELECT SUM(m.reward_points_can_achieve) AS total_module_points
        FROM modules m
        WHERE m.id IN (
            SELECT ms.module_id
            FROM module_submissions ms
            WHERE ms.creator_id = :creator_id
        )
    ";

    $stmt = $pdo->prepare($moduleQuery);
    $stmt->execute([':creator_id' => $creatorId]);

    return (int) $stmt->fetchColumn();
}


/**
 * 📌 Helper: Log Creator Activity
 *
 * @param PDO   $conn   Database connection
 * @param array $data   [
 *     'creator_id'      => (int) required,
 *     'activity_type'   => (string) required,
 *     'activity_ref_id' => (int) optional,
 *     'points_earned'   => (int) optional, default 0,
 *     'coins_earned'    => (int) optional, default 0,
 *     'description'     => (string) optional
 * ]
 *
 * @return bool true on success, false on failure
 */
function logCreatorActivity($conn, $data)
{
    // ✅ Required fields
    if (empty($data['creator_id']) || empty($data['activity_type'])) {
        return false; // creator_id and activity_type are mandatory
    }

    // ✅ Prepare values with defaults
    $creatorId = (int) $data['creator_id'];
    $activityType = trim($data['activity_type']);
    $activityRefId = !empty($data['activity_ref_id']) ? (int) $data['activity_ref_id'] : null;
    $pointsEarned = isset($data['points_earned']) ? (int) $data['points_earned'] : 0;
    $coinsEarned = isset($data['coins_earned']) ? (int) $data['coins_earned'] : 0;
    $description = !empty($data['description']) ? $data['description'] : null;

    // ✅ SQL Insert
    $query = "
        INSERT INTO creator_activities
            (creator_id, activity_type, activity_ref_id, points_earned, coins_earned, description)
        VALUES
            (:creator_id, :activity_type, :activity_ref_id, :points_earned, :coins_earned, :description)
    ";

    $params = [
        ':creator_id' => $creatorId,
        ':activity_type' => $activityType,
        ':activity_ref_id' => $activityRefId,
        ':points_earned' => $pointsEarned,
        ':coins_earned' => $coinsEarned,
        ':description' => $description,
    ];

    $abcd = RunQuery($conn, $query, $params, false, true);

    if (!empty($abcd['id']) && ($abcd['id']) > 0) {
        return true;
    } else {
        return false;
    }

}





/**
 * Get total coins earned by a creator from challenges/modules submissions.
 */
function getCreatorEarnedCoins(int $creatorId, PDO $conn): int
{
    // 🔹 Coins from challenges
    $challengeQuery = "SELECT SUM(c.reward_coins) as reward_coins
FROM challenges c
INNER JOIN creator_challenge_progress ccp ON c.id = ccp.challenge_id
WHERE ccp.status = 'completed' 
AND ccp.creator_id = ?;
    ";
    $challenge = RunQuery($conn, $challengeQuery, [$creatorId]);
    $totalChallengeCoins = $challenge[0]['reward_coins'] ?? 0;

    return intval($totalChallengeCoins);
}

/**
 * Get total coins spent by a creator in reward redemptions.
 */
function getCreatorSpentCoins(int $creatorId, PDO $conn): int
{
    $spentQuery = "SELECT COALESCE(SUM(coins_used),0) AS total_spent FROM reward_redemptions WHERE creator_id = ? AND status IN ('approved');
    ";
    $spent = RunQuery($conn, $spentQuery, [$creatorId]);
    return intval($spent[0]['total_spent'] ?? 0);
}

/**
 * Get total available coins for a creator (earned - spent).
 */
function getCreatorTotalCoins(int $creatorId, PDO $conn): int
{
    $earned = getCreatorEarnedCoins($creatorId, $conn);
    $spent = getCreatorSpentCoins($creatorId, $conn);
    return max(0, $earned - $spent);
}


/**
 * Fetch uploaded videos for a YouTube channel within a date range.
 * Extracts hashtags from title + description.
 *
 * @param string $apiKey      Your YouTube Data API v3 key
 * @param string $channelId   The channel ID
 * @param string $fromDate    Start date in Y-m-d format (e.g., 2025-09-01)
 * @param string $toDate      End date in Y-m-d format (e.g., 2025-09-22)
 * @param int    $maxResults  Number of videos to fetch per page (max 50)
 * @return array              List of videos with hashtags
 */
function getChannelVideosByDate($apiKey, $channelId, $fromDate, $toDate, $maxResults = 50)
{
    $videos = [];
    $pageToken = '';

    // Convert Y-m-d dates to ISO 8601 with UTC time
    $publishedAfter = (new DateTime($fromDate, new DateTimeZone('UTC')))->format('c');
    $publishedBefore = (new DateTime($toDate, new DateTimeZone('UTC')))
        ->setTime(23, 59, 59)->format('c');

    do {
        $params = [
            'key' => $apiKey,
            'channelId' => $channelId,
            'part' => 'snippet',
            'order' => 'date',
            'publishedAfter' => $publishedAfter,
            'publishedBefore' => $publishedBefore,
            'maxResults' => $maxResults,
            'type' => 'video',
        ];

        if (!empty($pageToken)) {
            $params['pageToken'] = $pageToken;
        }

        $url = "https://www.googleapis.com/youtube/v3/search?" . http_build_query($params);

        // ✅ Use cURL instead of file_get_contents
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            curl_close($ch);
            break; // error in cURL
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            break; // API error or empty response
        }

        $data = json_decode($response, true);

        if (isset($data['items'])) {
            foreach ($data['items'] as $item) {
                $title = $item['snippet']['title'] ?? '';
                $description = $item['snippet']['description'] ?? '';

                $videos[] = [
                    'videoId' => $item['id']['videoId'],
                    'title' => $title,
                    'description' => $description,
                    'publishedAt' => $item['snippet']['publishedAt'],
                    'thumbnails' => $item['snippet']['thumbnails'],
                    'hashtags' => extractHashtags($title . ' ' . $description), // ✅ Extract hashtags
                ];
            }
        }

        $pageToken = $data['nextPageToken'] ?? '';
    } while ($pageToken);

    return $videos;
}





/**
 * Fetch the location (latitude, longitude, and description) of a YouTube video by ID.
 *
 * @param string $apiKey   Your YouTube Data API v3 key
 * @param string $videoId  The YouTube video ID
 * @return array|null      Returns location array or null if not available
 */
function getVideoLocation($apiKey, $videoId)
{
    $url = "https://www.googleapis.com/youtube/v3/videos?" . http_build_query([
        'key' => $apiKey,
        'id' => $videoId,
        'part' => 'recordingDetails'
    ]);

    $response = file_get_contents($url);
    if (!$response)
        return null;

    $data = json_decode($response, true);

    if (isset($data['items'][0]['recordingDetails']['location'])) {
        $location = $data['items'][0]['recordingDetails']['location'];
        return [
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude'],
            'altitude' => $location['altitude'] ?? null
        ];
    }

    return null; // Location not available
}






/**
 * Helper function to fetch YouTube uploads for a given channel_id
 * within the active admin event date range, filtered by event hashtags.
 *
 * @param PDO    $conn        Database connection
 * @param string $apiKey      YouTube Data API v3 key
 * @param string $channelId   YouTube channel ID
 * @return array              List of matched videos with location & hashtags
 */
function getEventUploadsByChannelId(PDO $conn, string $apiKey, string $channelId): array
{
    try {
        // 1️⃣ Fetch admin event dates + hashtags
        $queryEvent = "SELECT start_date, end_date, hashtags FROM admin_events WHERE id = 1";
        $eventResult = RunQuery($conn, $queryEvent);

        dd('----test');
        dd($eventResult);

        if (empty($eventResult)) {
            return []; // no admin event found
        }

        $startDate = date('Y-m-d', strtotime($eventResult[0]['start_date']));
        $endDate = date('Y-m-d', strtotime($eventResult[0]['end_date']));
        $adminHashtagsStr = trim($eventResult[0]['hashtags'] ?? '');

        // Convert admin hashtags "#tag1 #tag2" → ["#tag1", "#tag2"]
        $adminHashtags = !empty($adminHashtagsStr) ? preg_split('/\s+/', $adminHashtagsStr) : [];

        // 2️⃣ Fetch videos using existing function
        $videos = getChannelVideosByDate($apiKey, $channelId, $startDate, $endDate);

        $finalData = [];

        foreach ($videos as $video) {
            $videoId = $video['id'] ?? $video['videoId'] ?? '';
            $videoUrl = "https://www.youtube.com/watch?v={$videoId}";
            $publishedAt = convertDatetime($video['publishedAt']) ?? null;

            // ✅ Video hashtags from API
            $videoHashtags = $video['hashtags'] ?? [];
            $videoTitle = $video['title'] ?? [];
            $videoDescription = $video['description'] ?? [];
            $videoThumbnail = $video['thumbnails'] ?? [];

            // ✅ Only keep hashtags that match admin event hashtags
            $matchedHashtags = array_values(array_intersect(
                array_map('strtolower', $videoHashtags),
                array_map('strtolower', $adminHashtags)
            ));

            if (!empty($matchedHashtags)) {
                // 3️⃣ Fetch video location
                $videoLocation = getVideoLocation($apiKey, $videoId);
                $city = $state = $country = null;

                if (!empty($videoLocation['latitude']) && !empty($videoLocation['longitude'])) {
                    $geo = getNominatimLocation($videoLocation['latitude'], $videoLocation['longitude']);
                    $city = $geo['city'] ?? null;
                    $state = $geo['state'] ?? null;
                    $country = $geo['country'] ?? null;
                }

                $finalData[] = [
                    'video_id' => $videoId,
                    'video_url' => $videoUrl,
                    'published_at' => $publishedAt,
                    'title' => $videoTitle,
                    'description' => $videoDescription,
                    'thumbnails' => $videoThumbnail,
                    'used_hashtags' => $matchedHashtags,
                    'city' => $city,
                    'state' => $state,
                    'country' => $country,
                    'location' => $videoLocation, // raw location info
                ];
            }
        }

        return $finalData;

    } catch (\Exception $e) {
        // In helpers we usually throw errors, not JSON
        throw new \Exception("Failed to fetch event uploads: " . $e->getMessage());
    }
}



/**
 * Get the count of completed challenge submissions for a specific creator.
 *
 * @param object $conn  Database connection object
 * @param int    $creatorId  ID of the creator
 * @return int  Number of completed challenge submissions (0 if none or error)
 */
function getChallengeSubmissionsCount($conn, int $creatorId): int
{
    try {
        if ($creatorId <= 0)
            return 0;

        $query = "SELECT COUNT(*) AS count 
                  FROM creator_challenge_progress 
                  WHERE creator_id = :creator_id AND status = 'completed'";
        $result = RunQuery($conn, $query, ['creator_id' => $creatorId]);
        return $result[0]['count'] ?? 0;
    } catch (\Exception $e) {
        return 0;
    }
}

/**
 * Get the count of completed module submissions for a specific creator.
 *
 * @param object $conn  Database connection object
 * @param int    $creatorId  ID of the creator
 * @return int  Number of completed module submissions (0 if none or error)
 */
function getModuleSubmissionsCount($conn, int $creatorId): int
{
    try {
        if ($creatorId <= 0)
            return 0;

        $query = "SELECT COUNT(*) AS count 
                  FROM creator_module_progress 
                  WHERE creator_id = :creator_id AND status = 'completed'";
        $result = RunQuery($conn, $query, ['creator_id' => $creatorId]);
        return $result[0]['count'] ?? 0;
    } catch (\Exception $e) {
        return 0;
    }
}

/**
 * Get the count of event uploads for a specific creator.
 *
 * @param object $conn  Database connection object
 * @param int    $creatorId  ID of the creator
 * @return int  Number of event uploads (0 if none or error)
 */
function getEventUploadsCount($conn, int $creatorId): int
{
    try {
        if ($creatorId <= 0)
            return 0;

        $query = "SELECT COUNT(*) AS count 
                  FROM event_uploads 
                  WHERE creator_id = :creator_id";
        $result = RunQuery($conn, $query, ['creator_id' => $creatorId]);
        return $result[0]['count'] ?? 0;
    } catch (\Exception $e) {
        return 0;
    }
}


/**
 * Get the total submissions for a specific creator.
 * 
 * This sums up completed challenges, completed modules, and event uploads.
 *
 * @param object $conn  Database connection object
 * @param int    $creatorId  ID of the creator
 * @return int  Total submissions count (0 if none or error)
 */
function getTotalSubmissions($conn, int $creatorId): int
{
    return getChallengeSubmissionsCount($conn, $creatorId)
        + getModuleSubmissionsCount($conn, $creatorId)
        + getEventUploadsCount($conn, $creatorId);
}






/**
 * Update the total_submissions field in youtube_creators table for a specific creator.
 *
 * @param object $conn       Database connection object
 * @param int    $creatorId  ID of the creator
 * @param int    $count      Total submissions count to update
 * @return string  "success" if updated, "failed" if error or invalid input
 */
function updateCreatorTotalSubmissions($conn, int $creatorId, int $count): string
{
    try {
        // Validate inputs
        if ($creatorId <= 0 || $count < 0) {
            return "failed";
        }

        $query = "UPDATE youtube_creators 
                  SET total_submissions = :count 
                  WHERE id = :creator_id";
        $params = [
            'count' => $count,
            'creator_id' => $creatorId
        ];

        $result = RunQuery($conn, $query, $params, true, true);

        if (!empty($result['success']) && $result['success'] > 0) {
            return "success";
        } else {
            return "failed";
        }
    } catch (\Exception $e) {
        return "failed";
    }
}






// /**
//  * Fetch latest challenge submissions for a creator, grouped by city and state.
//  *
//  * @param mysqli $conn Database connection
//  * @param int $creatorId Creator ID to filter submissions
//  * @return array Array of results with keys: city, state, submissions_count
//  */
// function getCreatorChallengeCityStateSubmissions($conn, int $creatorId): array
// {
//     try {
//         // Sanitize input
//         $creatorId = intval($creatorId);
//         if ($creatorId <= 0) {
//             return [];
//         }

//         // MySQL 8+ query using ROW_NUMBER to get latest per challenge_id
//         $query = "
//             WITH latest_submissions AS (
//                 SELECT *
//                 FROM (
//                     SELECT *,
//                            ROW_NUMBER() OVER (PARTITION BY challenge_id ORDER BY id DESC) AS rn
//                     FROM challenge_submissions
//                     WHERE creator_id = ?
//                 ) AS t
//                 WHERE rn = 1
//             )
//             SELECT city, state, COUNT(*) AS submissions_count
//             FROM latest_submissions
//             GROUP BY city, state
//             ORDER BY submissions_count DESC
//         ";

//         $params = [$creatorId];
//         $result = RunQuery($conn, $query, $params);

//         return $result ?: [];
//     } catch (\Exception $e) {
//         // In case of DB errors, return empty array
//         return [];
//     }
// }




/**
 * Fetch latest challenge submissions for a creator, grouped by city, state, country.
 *
 * @param mysqli $conn Database connection
 * @param int $creatorId Creator ID to filter submissions
 * @return array Array of results with keys: city, state, country, submissions_count
 */
function getCreatorChallengeCityStateSubmissions($conn, int $creatorId): array
{
    try {
        $creatorId = intval($creatorId);
        if ($creatorId <= 0)
            return [];

        $query = "
            WITH latest_submissions AS (
                SELECT *
                FROM (
                    SELECT *,
                           ROW_NUMBER() OVER (PARTITION BY challenge_id ORDER BY id DESC) AS rn
                    FROM challenge_submissions
                    WHERE creator_id = ?
                ) AS t
                WHERE rn = 1
            )
            SELECT city, state, country, COUNT(*) AS submissions_count
            FROM latest_submissions
            GROUP BY city, state, country
            ORDER BY submissions_count DESC
        ";

        return RunQuery($conn, $query, [$creatorId]) ?: [];
    } catch (\Exception $e) {
        return [];
    }
}

/**
 * Fetch latest module submissions for a creator, grouped by city, state, country.
 *
 * @param mysqli $conn Database connection
 * @param int $creatorId Creator ID to filter submissions
 * @return array Array of results with keys: city, state, country, submissions_count
 */
function getCreatorModuleCityStateSubmissions($conn, int $creatorId): array
{
    try {
        $creatorId = intval($creatorId);
        if ($creatorId <= 0)
            return [];

        $query = "
            WITH latest_submissions AS (
                SELECT *
                FROM (
                    SELECT *,
                           ROW_NUMBER() OVER (PARTITION BY module_id ORDER BY id DESC) AS rn
                    FROM module_submissions
                    WHERE creator_id = ?
                ) AS t
                WHERE rn = 1
            )
            SELECT city, state, country, COUNT(*) AS submissions_count
            FROM latest_submissions
            GROUP BY city, state, country
            ORDER BY submissions_count DESC
        ";

        return RunQuery($conn, $query, [$creatorId]) ?: [];
    } catch (\Exception $e) {
        return [];
    }
}

/**
 * Fetch latest event submissions for a creator, grouped by city, state, country.
 * For now, event_id is fixed as 1.
 *
 * @param mysqli $conn Database connection
 * @param int $creatorId Creator ID to filter submissions
 * @return array Array of results with keys: city, state, country, submissions_count
 */
function getEventUploadsCityStateSubmissions($conn, int $creatorId): array
{
    try {
        $creatorId = intval($creatorId);
        if ($creatorId <= 0)
            return [];

        $query = "
            WITH latest_submissions AS (
                SELECT *
                FROM (
                    SELECT *,
                           ROW_NUMBER() OVER (PARTITION BY event_id ORDER BY id DESC) AS rn
                    FROM event_uploads
                    WHERE creator_id = ? AND event_id = 1
                ) AS t
                WHERE rn = 1
            )
            SELECT city, state, country, COUNT(*) AS submissions_count
            FROM latest_submissions
            GROUP BY city, state, country
            ORDER BY submissions_count DESC
        ";

        return RunQuery($conn, $query, [$creatorId]) ?: [];
    } catch (\Exception $e) {
        return [];
    }
}


/**
 * Fetch latest challenge, module, and event submissions for a creator,
 * grouped by city, state, country, and return the sum of all submissions.
 *
 * @param mysqli $conn Database connection
 * @param int $creatorId Creator ID to filter submissions
 * @return array Array of results with keys: city, state, country, total_submissions
 */
function getCreatorTotalCityStateSubmissions($conn, int $creatorId): array
{
    try {
        $creatorId = intval($creatorId);
        if ($creatorId <= 0)
            return [];

        $query = "
            WITH latest_challenges AS (
                SELECT city, state, country, 1 AS submission_count
                FROM (
                    SELECT *,
                           ROW_NUMBER() OVER (PARTITION BY challenge_id ORDER BY id DESC) AS rn
                    FROM challenge_submissions
                    WHERE creator_id = ?
                ) t
                WHERE rn = 1
            ),
            latest_modules AS (
                SELECT city, state, country, 1 AS submission_count
                FROM (
                    SELECT *,
                           ROW_NUMBER() OVER (PARTITION BY module_id ORDER BY id DESC) AS rn
                    FROM module_submissions
                    WHERE creator_id = ?
                ) t
                WHERE rn = 1
            ),
            latest_events AS (
                SELECT city, state, country, 1 AS submission_count
                FROM (
                    SELECT *,
                           ROW_NUMBER() OVER (PARTITION BY event_id ORDER BY id DESC) AS rn
                    FROM event_uploads
                    WHERE creator_id = ? AND event_id = 1
                ) t
                WHERE rn = 1
            ),
            combined AS (
                SELECT city, state, country, submission_count FROM latest_challenges
                UNION ALL
                SELECT city, state, country, submission_count FROM latest_modules
                UNION ALL
                SELECT city, state, country, submission_count FROM latest_events
            )
            SELECT city, state, country, SUM(submission_count) AS total_submissions
            FROM combined
            GROUP BY city, state, country
            ORDER BY total_submissions DESC
        ";

        $params = [$creatorId, $creatorId, $creatorId];
        return RunQuery($conn, $query, $params) ?: [];

    } catch (\Exception $e) {
        return [];
    }
}




/**
 * Converts an array of arrays or objects into a JSON string with two arrays: "states" and "submissions".
 * 
 * This function filters out entries where the state value is empty, so only non-empty states
 * are included in the resulting JSON. It is useful for preparing data for frontend charts, 
 * dashboards, or APIs that require a clean mapping of states to submission counts.
 *
 * @param array $data Array of arrays or objects containing the data.
 * @param string $stateKey The key name for the state value in each item (default: 'state').
 * @param string $submissionKey The key name for the submission value in each item (default: 'total_submissions').
 * @return string JSON string in the format: {"states":[...],"submissions":[...]} containing only non-empty states.
 *
 * Example:
 * $data = [
 *     ['state' => 'Maharashtra', 'total_submissions' => 5],
 *     ['state' => '', 'total_submissions' => 3]
 * ];
 * echo createDfJsonFiltered($data);
 * // Output: {"states":["Maharashtra"],"submissions":[5]}
 */
function createDfJsonFiltered($data, $stateKey = 'state', $submissionKey = 'total_submissions')
{
    $result = [
        $stateKey => [],
        'submissions' => []
    ];

    foreach ($data as $item) {
        $state = $item[$stateKey] ?? null;
        $submission = $item[$submissionKey] ?? null;

        if (!empty($state)) {  // only include non-empty states/cities
            $result[$stateKey][] = $state;
            $result['submissions'][] = $submission;
        }
    }

    return json_encode($result);
}


// $jsonResult = createDfJsonFiltered($data);
// echo $jsonResult;





/**
 * Update submission_states and submission_cities for a given creator.
 *
 * @param mysqli $conn Database connection
 * @param int $creatorId Creator ID
 * @return string "success" | "failed"
 */
function updateCreatorSubmissions($conn, int $creatorId): string
{
    try {
        // Validate input
        if ($creatorId <= 0) {
            return "";
        }

        // ✅ Fetch total submissions grouped by city and state
        $creatorCounts = getCreatorTotalCityStateSubmissions($conn, $creatorId);

        if (empty($creatorCounts)) {
            return "";
        }

        // ✅ Transform data into JSON
        $countForState = createDfJsonFiltered($creatorCounts, 'state', 'total_submissions');
        $countForCity = createDfJsonFiltered($creatorCounts, 'city', 'total_submissions');

        // Prepare update query
        $query = "
            UPDATE youtube_creators
            SET submission_states = ?, 
                submission_cities = ?
            WHERE id = ?
        ";

        $params = [$countForState, $countForCity, $creatorId];

        $result = RunQuery($conn, $query, $params, true, true);

        if (!empty($result['success']) && $result['success'] > 0) {
            return "success";
        } else {
            return "failed";
        }

    } catch (Exception $e) {
        // Log error if needed
        return "failed";
    }
}







/**
 * Updates the `region` of a YouTube creator based on their highest submission state.
 *
 * This helper function takes a creator ID and a state-submission dataset, identifies the state
 * with the highest submission count, retrieves the corresponding region from the `indian_states`
 * table, and updates the creator's `region` in the `youtube_creators` table.
 *
 * @param mysqli $conn       Database connection object.
 * @param int    $creatorId  ID of the creator whose region should be updated.
 * @param array  $stateData  JSON-decoded array with two keys:
 *                           - "state": array of state names
 *                           - "submissions": array of corresponding submission counts
 *
 * @return string            Returns one of:
 *                           - "success" → region updated successfully
 *                           - "failed"  → DB action failed or exception occurred
 *                           - ""        → invalid input or no action needed
 *
 * Example usage:
 * $stateData = json_decode('{"state":["Maharashtra"],"submissions":["1"]}', true);
 * $status = updateCreatorRegionByStateData($conn, 123, $stateData);
 */

// function updateCreatorRegionByStateData($conn, int $creatorId, array $stateData): string
// {
//     try {
//         // Validate input
//         if ($creatorId <= 0 || empty($stateData['state']) || empty($stateData['submissions'])) {
//             return "";
//         }

//         $states = $stateData['state'];
//         $submissions = $stateData['submissions'];

//         // Find the state with the highest submission count
//         $maxIndex = 0;
//         $maxSubmissions = 0;
//         foreach ($submissions as $index => $count) {
//             $countInt = (int)$count;
//             if ($countInt > $maxSubmissions) {
//                 $maxSubmissions = $countInt;
//                 $maxIndex = $index;
//             }
//         }

//         $topState = $states[$maxIndex] ?? '';
//         if (empty($topState)) {
//             return "";
//         }

//         // Lookup region for the state
//         $query = "SELECT region FROM indian_states WHERE state_name LIKE :state";
//         $result = RunQuery($conn, $query, [':state' => $topState]);

//         if (empty($result) || empty($result[0]['region'])) {
//             return "";
//         }

//         $region = $result[0]['region'];

//         // Update creator's region
//         $updateQuery = "UPDATE youtube_creators SET region = :region WHERE id = :creatorId";
//         $updateResult = RunQuery($conn, $updateQuery, [
//             ':region' => $region,
//             ':creatorId' => $creatorId
//         ], true, true);

//         return (!empty($updateResult['success']) && $updateResult['success'] > 0) ? "success" : "failed";

//     } catch (\Throwable $e) {
//         // Log error if needed
//         return "failed";
//     }
// }
function updateCreatorRegionByStateData($conn, int $creatorId, $stateData): string
{
    try {
        // ✅ Validate creatorId
        if ($creatorId <= 0) {
            return "";
        }

        // ✅ Validate stateData structure
        if (
            !is_array($stateData) ||
            empty($stateData) ||
            !isset($stateData['state'], $stateData['submissions']) ||
            !is_array($stateData['state']) ||
            !is_array($stateData['submissions']) ||
            count($stateData['state']) === 0 ||
            count($stateData['submissions']) === 0
        ) {
            return "";
        }

        $states = $stateData['state'];
        $submissions = $stateData['submissions'];

        // ✅ Find the state with the highest submission count
        $maxIndex = 0;
        $maxSubmissions = 0;
        foreach ($submissions as $index => $count) {
            $countInt = (int) $count;
            if ($countInt > $maxSubmissions) {
                $maxSubmissions = $countInt;
                $maxIndex = $index;
            }
        }

        $topState = $states[$maxIndex] ?? '';
        if (empty($topState)) {
            return "";
        }

        // ✅ Lookup region for the state
        $query = "SELECT region FROM indian_states WHERE state_name LIKE :state";
        $result = RunQuery($conn, $query, [':state' => $topState]);

        if (empty($result) || empty($result[0]['region'])) {
            return "";
        }

        $region = $result[0]['region'];

        // ✅ Update creator's region
        $updateQuery = "UPDATE youtube_creators SET region = :region WHERE id = :creatorId";
        $updateResult = RunQuery($conn, $updateQuery, [
            ':region' => $region,
            ':creatorId' => $creatorId
        ], true, true);

        return (!empty($updateResult['success']) && $updateResult['success'] > 0) ? "success" : "failed";

    } catch (\Throwable $e) {
        // Log error if needed
        return "failed";
    }
}













/**
 * Helper function to calculate submission counts per state for a given creator.
 *
 * @param mysqli $conn       Database connection
 * @param int    $creatorId  Creator ID to calculate submissions for
 *
 * @return array|string      Associative array of state => submissions, or "" if no data / invalid
 */
function getCreatorStateSubmissions($conn, int $creatorId)
{
    try {
        // Validate input
        if ($creatorId <= 0) {
            return "";
        }

        // Fetch submission_states JSON for the creator
        $query = "SELECT submission_states FROM youtube_creators WHERE id = ?";
        $result = RunQuery($conn, $query, [$creatorId]);

        if (empty($result) || empty($result[0]['submission_states'])) {
            return "";
        }

        $submissionStates = json_decode($result[0]['submission_states'], true);

        // Validate JSON structure
        if (
            !isset($submissionStates['state'], $submissionStates['submissions']) ||
            !is_array($submissionStates['state']) ||
            !is_array($submissionStates['submissions'])
        ) {
            return "";
        }

        $states = $submissionStates['state'];
        $submissions = $submissionStates['submissions'];

        $stateCounts = [];

        // Pair states with their submissions
        foreach ($states as $index => $stateName) {
            $count = isset($submissions[$index]) ? (int) $submissions[$index] : 0;
            if (!empty($stateName)) {
                $stateCounts[$stateName] = $count;
            }
        }

        return $stateCounts; // Example: ["Maharashtra" => 5, "Karnataka" => 2]

    } catch (\Throwable $e) {
        return ""; // failed silently
    }
}



/**
 * Helper function to insert/update state submissions count for a creator.
 *
 * @param mysqli $conn       Database connection
 * @param int    $creatorId  Creator ID to process
 *
 * @return string            "success" | "failed" | ""
 */
function updateStateSubmissionsCount($conn, int $creatorId)
{
    try {
        // Validate input
        if ($creatorId <= 0) {
            return "";
        }

        // Get the creator's state submissions
        $stateCounts = getCreatorStateSubmissions($conn, $creatorId);

        if (empty($stateCounts)) {
            return "";
        }

        foreach ($stateCounts as $stateName => $count) {
            // Skip invalid state names or zero counts
            if (empty($stateName) || $count <= 0) {
                continue;
            }

            $query = "
                INSERT INTO state_submissions_count (creator_id, state_name, total_submissions)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE total_submissions = VALUES(total_submissions)
            ";

            $params = [$creatorId, $stateName, $count];

            $result = RunQuery($conn, $query, $params, true, true);

            // Check for failure
            if (empty($result) || !isset($result['success'])) {
                return "failed";
            }
        }

        return "success";

    } catch (\Throwable $e) {
        return "failed";
    }
}




/**
 * Helper function to get the state with the highest total submissions.
 *
 * @param mysqli $conn  Database connection
 *
 * @return string       State name with highest total submissions or "" if none found
 */
function getHighPerformingState($conn)
{
    try {
        $query = "
            SELECT state_name, SUM(total_submissions) AS total_submissions
            FROM state_submissions_count
            GROUP BY state_name
            ORDER BY total_submissions DESC
            LIMIT 1
        ";

        $result = RunQuery($conn, $query);

        if (!empty($result) && isset($result[0]['state_name'])) {
            return $result[0]['state_name'];
        }

        return "";

    } catch (\Throwable $e) {
        return "";
    }
}



//  this will not work

// /**
//  * Get the rank position of a creator by their ID
//  *
//  * @param mysqli $conn
//  * @param int    $creatorId
//  * @return string  Rank number as string, "" if not found
//  */
// function getCreatorRank(mysqli $conn, int $creatorId): string
// {
//     try {
//         // ✅ Validate input
//         if ($creatorId <= 0) {
//             return "";
//         }

//         // ✅ SQL: Use window function to calculate rank directly
//         $query = "
//             SELECT r.rank_position
//             FROM (
//                 SELECT cs.creator_id,
//                        RANK() OVER (ORDER BY cs.total_points DESC, cs.total_coins DESC) AS rank_position
//                 FROM creator_stats cs
//             ) AS r
//             WHERE r.creator_id = ?
//             LIMIT 1
//         ";

//         $params = [$creatorId];
//         $result = RunQuery($conn, $query, $params);

//         if (!empty($result) && isset($result[0]['rank_position'])) {
//             return (string)$result[0]['rank_position']; // return rank as string
//         }

//         return ""; // not found
//     } catch (Exception $e) {
//         return "";
//     }
// }
