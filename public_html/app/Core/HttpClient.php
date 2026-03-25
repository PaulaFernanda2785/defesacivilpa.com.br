<?php

class HttpClient
{
    public static function request($url, $method = 'GET', $headers = [], $data = null)
    {

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $headers[] = "Content-Type: application/json";

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception('Erro CURL: ' . curl_error($ch));
        }

        curl_close($ch);

        return json_decode($response, true);
    }
}
