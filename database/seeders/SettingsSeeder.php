<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // General
            ['key' => 'site_name',            'value' => 'PikPakGo',             'type' => 'string',  'group' => 'general', 'label' => 'Site Name',             'is_public' => true],
            ['key' => 'site_tagline',         'value' => 'Your Perfect Getaway', 'type' => 'string',  'group' => 'general', 'label' => 'Site Tagline',          'is_public' => true],
            ['key' => 'support_email',        'value' => 'support@pikpakgo.com', 'type' => 'string',  'group' => 'general', 'label' => 'Support Email',         'is_public' => true],
            ['key' => 'support_phone',        'value' => '',                     'type' => 'string',  'group' => 'general', 'label' => 'Support Phone',         'is_public' => true],
            ['key' => 'default_currency',     'value' => 'USD',                  'type' => 'string',  'group' => 'general', 'label' => 'Default Currency',      'is_public' => true],
            ['key' => 'default_language',     'value' => 'en',                   'type' => 'string',  'group' => 'general', 'label' => 'Default Language',      'is_public' => true],
            ['key' => 'maintenance_mode',     'value' => '0',                    'type' => 'boolean', 'group' => 'general', 'label' => 'Maintenance Mode',      'is_public' => false],

            // Booking
            ['key' => 'booking_min_advance_days', 'value' => '1',               'type' => 'integer', 'group' => 'booking', 'label' => 'Min Advance Booking Days', 'is_public' => true],
            ['key' => 'booking_max_advance_days', 'value' => '365',             'type' => 'integer', 'group' => 'booking', 'label' => 'Max Advance Booking Days', 'is_public' => true],
            ['key' => 'free_cancellation_days',   'value' => '7',               'type' => 'integer', 'group' => 'booking', 'label' => 'Free Cancellation Days',   'is_public' => true],

            // Email
            ['key' => 'email_from_name',      'value' => 'PikPakGo',             'type' => 'string',  'group' => 'email',   'label' => 'Email From Name',       'is_public' => false],
            ['key' => 'email_from_address',   'value' => 'noreply@pikpakgo.com', 'type' => 'string',  'group' => 'email',   'label' => 'Email From Address',    'is_public' => false],
            ['key' => 'send_booking_emails',  'value' => '1',                    'type' => 'boolean', 'group' => 'email',   'label' => 'Send Booking Emails',   'is_public' => false],

            // Payment
            ['key' => 'payment_gateway',      'value' => 'authorize_net',        'type' => 'string',  'group' => 'payment', 'label' => 'Payment Gateway',       'is_public' => false],
            ['key' => 'payment_test_mode',    'value' => '1',                    'type' => 'boolean', 'group' => 'payment', 'label' => 'Test Mode',             'is_public' => false],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, [
                    'description' => null,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ])
            );
        }
    }
}
