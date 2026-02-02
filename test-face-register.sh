#!/bin/bash
# Test Face Registration

echo "=========================================="
echo "Testing Face Registration Fix"
echo "=========================================="
echo ""

echo "1. Clearing face_tokens from DB..."
php artisan tinker << 'TINKER'
DB::table('users')->whereIn('id', [1, 3])->update(['face_token' => null]);
echo "✅ Cleared\n";
exit();
TINKER

echo ""
echo "2. Checking current logs..."
tail -5 storage/logs/laravel.log

echo ""
echo "=========================================="
echo "Now test on browser:"
echo "- Go to: http://127.0.0.1:8000/user/profile"
echo "- Click: 📸 Đăng ký khuôn mặt"
echo "- Check console for success message"
echo "=========================================="
echo ""
echo "4. Real-time logs:"
echo "Run: tail -f storage/logs/laravel.log | grep -i face"
echo ""
