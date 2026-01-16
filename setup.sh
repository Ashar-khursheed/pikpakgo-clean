#!/bin/bash

echo "=========================================="
echo "  PikPakGo API - Quick Setup Script"
echo "=========================================="
echo ""

# Check if composer is installed
if ! command -v composer &> /dev/null
then
    echo "❌ Composer is not installed. Please install Composer first."
    echo "Visit: https://getcomposer.org/download/"
    exit 1
fi

echo "✅ Composer found"

# Check if PHP is installed
if ! command -v php &> /dev/null
then
    echo "❌ PHP is not installed. Please install PHP 8.2 or higher."
    exit 1
fi

echo "✅ PHP found"

# Install dependencies
echo ""
echo "📦 Installing dependencies..."
composer install

# Copy .env file if it doesn't exist
if [ ! -f .env ]; then
    echo ""
    echo "📝 Creating .env file..."
    cp .env.example .env
    echo "✅ .env file created"
fi

# Generate application key
echo ""
echo "🔑 Generating application key..."
php artisan key:generate

# Generate JWT secret
echo ""
echo "🔐 Generating JWT secret..."
php artisan jwt:secret

echo ""
echo "=========================================="
echo "  Setup Complete! 🎉"
echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Configure your database in .env file"
echo "2. Run: php artisan migrate"
echo "3. Run: php artisan l5-swagger:generate"
echo "4. Run: php artisan serve"
echo "5. Visit: http://localhost:8000/documentation"
echo ""
echo "For detailed instructions, see INSTALLATION.md"
echo ""
