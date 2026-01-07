<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     title="User",
 *     required={"id","first_name","last_name","email","user_type","status"},
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="first_name", type="string", example="John"),
 *     @OA\Property(property="last_name", type="string", example="Doe"),
 *     @OA\Property(property="full_name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", example="john@example.com"),
 *     @OA\Property(property="phone", type="string", example="+1234567890"),
 *     @OA\Property(property="user_type", type="string", example="customer"),
 *     @OA\Property(property="status", type="string", example="active"),
 *     @OA\Property(property="email_verified", type="boolean", example=true),
 *     @OA\Property(property="country", type="string", example="USA"),
 *     @OA\Property(property="preferred_currency", type="string", example="USD"),
 *     @OA\Property(property="preferred_language", type="string", example="en")
 * )
 */
class UserSchema {}
