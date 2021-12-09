<?php
require 'Key.php';
require 'JWK.php';
require 'JWT.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

return fn(string $method, mixed ...$params) => match($method)
{
    'encode' => JWT::encode(...$params),
    default => NULL
};