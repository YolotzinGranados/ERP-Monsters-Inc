<?php

if (session_status() === PHP_SESSION_NONE) {

    ini_set('session.cookie_samesite', '');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', '0');
    ini_set('session.cookie_path', '/');

    session_start();
}