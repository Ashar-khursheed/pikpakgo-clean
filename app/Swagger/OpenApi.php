<?php

namespace App\Swagger;

/**
 * @OA\OpenApi(
 *   @OA\Info(
 *     version="1.0.0",
 *     title="PikPakGo API Documentation",
 *     description="Complete API documentation for PikPakGo - Unified Travel Marketplace",
 *     @OA\Contact(
 *       email="reservations@pikpakgo.com"
 *     )
 *   ),
 *
 *   @OA\Server(
 *     url="http://localhost:8000/api",
 *     description="Local Development Server"
 *   ),
 *
 *   @OA\Server(
 *     url="https://pickpackgo.in-sourceit.com/api",
 *     description="Production Server"
 *   ),
 *
 *   @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 *   )
 * )
 */
class OpenApi {}
