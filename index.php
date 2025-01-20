<?php
// Define the bot token
$bot_token = '7272929349:AAH5UsjWwpzOnv3XrsAXEdyjQN0AByKD_WY';
$api_url = "https://api.telegram.org/bot$bot_token/";

// Get the update from Telegram
$update = file_get_contents("php://input");
$update_array = json_decode($update, TRUE);

// Check if message or callback_query exists
$chat_id = null;
$message_id = null;
$text = null;
$callback_data = null;

if (isset($update_array["message"]["chat"]["id"])) {
    $chat_id = $update_array["message"]["chat"]["id"];
}

if (isset($update_array["callback_query"]["message"]["chat"]["id"])) {
    $chat_id = $update_array["callback_query"]["message"]["chat"]["id"];
}

if (isset($update_array["callback_query"]["message"]["message_id"])) {
    $message_id = $update_array["callback_query"]["message"]["message_id"];
}

if (isset($update_array["message"]["text"])) {
    $text = $update_array["message"]["text"];
}

if (isset($update_array["callback_query"]["data"])) {
    $callback_data = $update_array["callback_query"]["data"];
}

// Function to send a chat action (e.g., typing, uploading audio)
function sendChatAction($chat_id, $action) {
    global $api_url;

    $post_fields = [
        'chat_id' => $chat_id,
        'action' => $action
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url . "sendChatAction");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_exec($ch);
    curl_close($ch);
}

// Function to send a message
function sendMessage($chat_id, $message) {
    global $api_url;

    sendChatAction($chat_id, 'typing'); // Show typing action

    $post_fields = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'Markdown'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url . "sendMessage");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_exec($ch);
    curl_close($ch);
}

// Function to send media (audio file in this case)
function sendMedia($chat_id, $type, $media_url, $caption, $api_url, $spotify_url) {
    sendChatAction($chat_id, 'upload_audio'); // Show uploading audio action

$keyboard = [
    'inline_keyboard' => [
        [
            [
                'text' => "Updates", 
                'url' => "https://t.me/outlawbots"
            ],
            [
                'text' => "Support", 
                'url' => "https://t.me/offchats"
            ]
        ]
    ]
];


    // Prepare the POST fields
    $post_fields = [
        'chat_id' => $chat_id,
        'caption' => $caption,
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode($keyboard),
        $type => $media_url
    ];

    // Send the request using cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url . "send" . ucfirst($type));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_exec($ch);
    curl_close($ch);
}
// Function to edit an existing message
function editMessage($chat_id, $message_id, $message, $reply_markup = null) {
    global $api_url;

    $post_fields = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];

    if ($reply_markup) {
        $post_fields['reply_markup'] = json_encode($reply_markup);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url . "editMessageText");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_exec($ch);
    curl_close($ch);
}

// Function to handle API request to Spotify service
function fetchSongData($query) {
    $url = "https://teleserviceapi.vercel.app/spotify?q=" . urlencode($query);
    $response = file_get_contents($url);
    return json_decode($response, true);
}

// Function to download audio from Spotify URL
function downloadSong($spotify_url) {
    $apiResponse = file_get_contents("https://song-teleservice.vercel.app/spotify/down?url=" . urlencode($spotify_url));
    $response = json_decode($apiResponse, true);
    return $response['download_link'] ?? null;
}


// Define the folder where session files will be stored
$user_session_folder = "session/";

// Ensure the folder exists
if (!file_exists($user_session_folder)) {
    mkdir($user_session_folder, 0777, true); // Create the folder if it doesn't exist
}

// Define the session file path
$user_session_file = $user_session_folder . "session_$chat_id.json";

// Function to save user session (current track index)
function saveSession($chat_id, $session_data) {
    global $user_session_file;
    file_put_contents($user_session_file, json_encode($session_data));
}

// Function to load user session
function loadSession($chat_id) {
    global $user_session_file;
    if (file_exists($user_session_file)) {
        return json_decode(file_get_contents($user_session_file), true);
    }
    return null;
}
// Function to send the initial song details with "Download" and "Next" buttons
function sendInitialSongDetails($chat_id, $song_data, $track_index) {
    $total_tracks = count($song_data['tracks']);
    $track = $song_data['tracks'][$track_index];

    $track_name = $track['trackName'];
    $artist = $track['artist'];
    $album = $track['album'];
    $spotify_url = $track['spotifyUrl'];
    $image_url = $track['image'];

    // Create Next/Back buttons based on the track index
    $keyboard = [];
    $navigation_buttons = [];
    
    if ($track_index > 0) {
        $navigation_buttons[] = ['text' => "⬅️ Back", 'callback_data' => "prev"];
    }
    if ($track_index < $total_tracks - 1) {
        $navigation_buttons[] = ['text' => "Next ➡️", 'callback_data' => "next"];
    }

    $keyboard[] = $navigation_buttons; // Put Back and Next on the same row
    $keyboard[] = [['text' => "Download", 'callback_data' => "/dwn " . $spotify_url]];

    $message = "🎼 Name: $track_name\n👨‍🎨 Artist: $artist\n✨ Album: $album <a href='$image_url'>ㅤ</a>";

    // Send the message with inline buttons
    sendMessageWithKeyboard($chat_id, $message, ['inline_keyboard' => $keyboard]);

    // Save the current track index to the session
    saveSession($chat_id, ['query' => $song_data, 'track_index' => $track_index]);
}

// Function to edit song details for a specific track index
function editSongDetails($chat_id, $message_id, $song_data, $track_index) {
    $total_tracks = count($song_data['tracks']);
    $track = $song_data['tracks'][$track_index];

    $track_name = $track['trackName'];
    $artist = $track['artist'];
    $album = $track['album'];
    $spotify_url = $track['spotifyUrl'];
    $image_url = $track['image'];

    // Create Next/Back buttons based on the track index
    $keyboard = [];
    $navigation_buttons = [];
    
    if ($track_index > 0) {
        $navigation_buttons[] = ['text' => "⬅️ Back", 'callback_data' => "prev"];
    }
    if ($track_index < $total_tracks - 1) {
        $navigation_buttons[] = ['text' => "Next ➡️", 'callback_data' => "next"];
    }

    $keyboard[] = $navigation_buttons; // Put Back and Next on the same row
    $keyboard[] = [['text' => "Download", 'callback_data' => "/dwn " . $spotify_url]];

    $message = "🎼 Name: $track_name\n👨‍🎨 Artist: $artist\n✨ Album: $album <a href='$image_url'>ㅤ</a>";

    // Edit the existing message instead of sending a new one
    editMessage($chat_id, $message_id, $message, ['inline_keyboard' => $keyboard]);

    // Save the current track index to the session
    saveSession($chat_id, ['query' => $song_data, 'track_index' => $track_index]);
}

// Function to send a message with inline keyboard
function sendMessageWithKeyboard($chat_id, $message, $keyboard) {
    global $api_url;

    sendChatAction($chat_id, 'typing'); // Show typing action

    $post_fields = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'HTML',
        'reply_markup' => json_encode($keyboard),
        'disable_web_page_preview' => $disable_web_page_preview,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url . "sendMessage");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_exec($ch);
    curl_close($ch);
}

// Handle the bot logic
if ($text == "/start") {
    // Send typing action before the message
    sendChatAction($chat_id, 'typing'); // Typing action

    // Send a detailed welcome message with HTML formatting and inline button
// Sleek and engaging welcome message
$welcome_message = "🎵 <b>Hello, Music Enthusiast!</b>\n\n"
    . "Welcome to <b>@MusicDownloadXBot</b>, your one-stop bot for all your music needs. 🎧\n\n"
    . "<blockquote>✨ <b>Here’s what I can do for you:</b></blockquote>\n\n"
    . "🎶 <b>Find Songs:</b> Search for any track instantly.\n"
    . "📥 <b>Download Music:</b> Get your favorites in high quality.\n"
    . "🎤 <b>Share the Vibes:</b> Add me to your groups for endless fun.\n\n"
    . "💬 <i>Type the name of a song to get started now!</i>";

$keyboard = [
    'inline_keyboard' => [
        [
            ['text' => "Updates", 'url' => "https://t.me/outlawbots"],
            ['text' => "Support", 'url' => "https://t.me/offchats"]
        ]
    ]
];


    // Send the welcome message with inline button
    sendMessageWithKeyboard($chat_id, $welcome_message, $keyboard);
} elseif ($text) {
    // Fetch song data from API based on user's message
    $song_data = fetchSongData($text);
    
    if (isset($song_data['tracks']) && count($song_data['tracks']) > 0) {
        // Send the first track and save the session
        sendInitialSongDetails($chat_id, $song_data, 0);
    } else {
        // If no track found, send error message
        sendMessage($chat_id, "Sorry, I couldn't find any song with that name.");
    }
} elseif ($callback_data) {
    // Handle callback queries for navigation (Next/Back) or download
    $session_data = loadSession($chat_id);
    
    if ($session_data) {
        $track_index = $session_data['track_index'];
        $song_data = $session_data['query'];

        if ($callback_data == "next" && $track_index < count($song_data['tracks']) - 1) {
            // Go to the next track
            editSongDetails($chat_id, $message_id, $song_data, $track_index + 1);
        } elseif ($callback_data == "prev" && $track_index > 0) {
            // Go to the previous track
            editSongDetails($chat_id, $message_id, $song_data, $track_index - 1);
        } elseif (strpos($callback_data, "/dwn") === 0) {
            // Handle download
            $spotify_url = trim(str_replace("/dwn", "", $callback_data));
            $download_link = downloadSong($spotify_url);
            if ($download_link) {
                $caption = "*🎶 Music downloaded by @MusicDownloadXBot 🎵*\n\n_Enjoy your Beats 🎧_";
                
              sendMedia($chat_id, 'audio', $download_link, $caption, $api_url, $spotify_url);
            } else {
                sendMessage($chat_id, "Sorry, I couldn't fetch the download link.");
            }
        }
    } else {
        sendMessage($chat_id, "Please search for a song first.");
    }
}

?>



