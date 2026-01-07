<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="PikPakGo API",
 *     version="1.0.0",
 *     description="PikPakGo Travel Marketplace API - Hotels, Vacation Rentals & More",
 *     @OA\Contact(
 *         email="reservations@pikpakgo.com",
 *         name="PikPakGo Support"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="Local Development Server"
 * )
 * 
 * @OA\Server(
 *     url="https://pickpackgo.in-sourceit.com/api",
 *     description="Production Server"
 * )
 * 
 * @OA\SecurityScheme(
 *     type="http",
 *     description="Login with email and password to get the authentication token",
 *     name="Token based authentication",
 *     in="header",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     securityScheme="bearerAuth",
 * )
 */
abstract class Controller
{
    //
}
