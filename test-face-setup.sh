#!/bin/bash
# Test Face Detection System

echo "🔍 Testing Face Detection Setup..."
echo ""

# 1. Check .env variables
echo "✅ Checking .env variables..."
grep -E "FACEPP_" /Users/tranquangvu/BookStack/.env | grep -v "^#"
echo ""

# 2. Check services.php config
echo "✅ Checking services.php..."
grep -A 3 "'facepp'" /Users/tranquangvu/BookStack/app/Config/services.php
echo ""

# 3. Check database migration
echo "✅ Checking database migration..."
sqlite3 /Users/tranquangvu/BookStack/storage/app/database.sqlite ".schema users" | grep face_token || mysql -h 127.0.0.1 -u root bookstack -e "SHOW COLUMNS FROM users LIKE 'face_token';" || echo "Cannot check DB - check manually"
echo ""

# 4. Check if face-login.ts exists
echo "✅ Checking face-login.ts..."
ls -lh /Users/tranquangvu/BookStack/resources/js/face-login.ts
echo ""

# 5. Check if app.js built successfully
echo "✅ Checking built app.js..."
ls -lh /Users/tranquangvu/BookStack/public/dist/app.js | head -1
echo ""

# 6. Check controllers
echo "✅ Checking FaceLoginController..."
ls -lh /Users/tranquangvu/BookStack/app/Http/Controllers/FaceLoginController.php
echo ""

echo "✅ Checking FaceRegisterController..."
ls -lh /Users/tranquangvu/BookStack/app/Http/Controllers/FaceRegisterController.php
echo ""

echo "🎉 All checks done!"
echo ""
echo "📝 Next steps:"
echo "1. Start your server: php artisan serve"
echo "2. Go to login page: http://127.0.0.1:8000/login"
echo "3. Click '🔐 Đăng nhập bằng khuôn mặt'"
echo "4. Allow camera when prompted"
echo "5. Check browser console (F12) for logs"
echo ""
