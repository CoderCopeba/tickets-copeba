<?php
    require_once(__DIR__ . "/config/env.php");

    $remoteAddress = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : "";
    $isLoopback = $remoteAddress === "127.0.0.1" || $remoteAddress === "::1";

    if (!$isLoopback) {
        http_response_code(403);
        exit;
    }

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        http_response_code(405);
        header("Allow: POST");
        exit;
    }

    $apiURL = env_get("CHAT_API_URL");
    $token = env_get("CHAT_API_TOKEN");

    /*     $message = $_GET['text'];
        $phone = $_GET['phone']; */

    $data = json_encode(
        array(
            'chatId'=>'51959747175@c.us',
            'body'=>"Test"
        )
    );
    $url = $apiURL.'message?token='.$token;
    $options = stream_context_create(
        array('http' =>
            array(
                'method'  => 'POST',
                'header'  => 'Content-type: application/json',
                'content' => $data
            )
        )
    );
    $response = file_get_contents($url,false,$options);
    echo $response; exit;
?>