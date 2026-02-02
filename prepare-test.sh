#!/bin/bash

# Test Registration Flow
echo "======================================"
echo "🎯 Face Registration - Ready to Test"
echo "======================================"
echo ""

cd /Users/tranquangvu/BookStack

echo "1️⃣  Clearing caches..."
rm -rf bootstrap/cache/*.php
php artisan cache:clear > /dev/null 2>&1
php artisan config:clear > /dev/null 2>&1
php artisan view:clear > /dev/null 2>&1
echo "   ✅ Done"
echo ""

echo "2️⃣  Resetting database..."
php artisan tinker << 'TINKER' > /dev/null 2>&1
DB::table('users')->whereIn('id', [1, 3])->update(['face_token' => null]);
exit();
TINKER
echo "   ✅ Users 1,3 face_token cleared"
echo ""

echo "3️⃣  Starting log watcher..."
echo "   Run in another terminal:"
echo "   tail -f storage/logs/laravel.log | grep -E 'Face|face'"
echo ""

echo "======================================"
echo "🚀 READY TO TEST!"
echo "======================================"
echo ""
echo "Steps:"
echo "1. Go to: http://127.0.0.1:8000/user/profile"
echo "2. Click: 📸 Đăng ký khuôn mặt"
echo "3. Check console logs for success"
echo ""
