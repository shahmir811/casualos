#!/bin/bash
# CasualOS — One-time setup script
# Run from inside the casualos/ directory: bash setup.sh

echo "=== CasualOS Setup ==="

# 1. Publish Spatie Permission migrations + config
echo "[1/5] Publishing Spatie Permission..."
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --force

# 2. Publish Spatie Activitylog migrations + config
echo "[2/5] Publishing Spatie Activitylog..."
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations" --force

# 3. Run ALL migrations (Laravel defaults + Spatie + ours)
echo "[3/5] Running migrations..."
php artisan migrate

# 4. Seed admin account and roles
echo "[4/5] Seeding admin account..."
php artisan db:seed --class=AdminSeeder

# 5. Create storage symlink for file uploads
echo "[5/5] Creating storage symlink..."
php artisan storage:link

echo ""
echo "=== Setup Complete! ==="
echo ""
echo "Login at: http://localhost:8000/login"
echo "Email:    admin@casualite.com"
echo "Password: Admin@1234"
echo ""
echo "Start the dev server with: php artisan serve"
