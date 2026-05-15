<?php

function jsonResponse($statusCode, $payload) {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit();
}
