#!/bin/bash

echo "🔍 Face Registration Setup Verification"
echo "======================================="
echo ""

# Check if app.ts imports face-register-profile.ts
echo "✅ Checking app.ts imports..."
if grep -q "face-register-profile" /Users/tranquangvu/BookStack/resources/js/app.ts; then
    echo "   ✅ face-register-profile.ts imported"
else
    echo "   ❌ face-register-profile.ts NOT imported"
fi

if grep -q "face-login" /Users/tranquangvu/BookStack/resources/js/app.ts; then
    echo "   ✅ face-login.ts imported"
else
    echo "   ❌ face-login.ts NOT imported"
fi
echo ""

# Check if profile view has face registration form
echo "✅ Checking profile.blade.php..."
if grep -q "face-register-btn" /Users/tranquangvu/BookStack/resources/views/users/account/profile.blade.php; then
    echo "   ✅ Face registration form added to profile"
else
    echo "   ❌ Face registration form NOT found"
fi
echo ""

# Check if face-register-profile.ts exists
echo "✅ Checking face-register-profile.ts..."
if [ -f /Users/tranquangvu/BookStack/resources/js/face-register-profile.ts ]; then
    echo "   ✅ File exists ($(wc -l < /Users/tranquangvu/BookStack/resources/js/face-register-profile.ts) lines)"
else
    echo "   ❌ File NOT found"
fi
echo ""

# Check if build was successful
echo "✅ Checking built assets..."
if grep -q "face-register-profile" /Users/tranquangvu/BookStack/public/dist/app.js; then
    echo "   ✅ face-register-profile code in app.js"
else
    echo "   ⚠️  May need to rebuild: npm run build"
fi
echo ""

# Check controllers
echo "✅ Checking controllers..."
if [ -f /Users/tranquangvu/BookStack/app/Http/Controllers/FaceLoginController.php ]; then
    echo "   ✅ FaceLoginController exists"
else
    echo "   ❌ FaceLoginController NOT found"
fi

if [ -f /Users/tranquangvu/BookStack/app/Http/Controllers/FaceRegisterController.php ]; then
    echo "   ✅ FaceRegisterController exists"
else
    echo "   ❌ FaceRegisterController NOT found"
fi
echo ""

# Check routes
echo "✅ Checking routes..."
if grep -q "/user/face/register" /Users/tranquangvu/BookStack/routes/web.php; then
    echo "   ✅ POST /user/face/register route exists"
else
    echo "   ❌ Registration route NOT found"
fi

if grep -q "/login/face" /Users/tranquangvu/BookStack/routes/web.php; then
    echo "   ✅ POST /login/face route exists"
else
    echo "   ❌ Login route NOT found"
fi
echo ""

# Check database
echo "✅ Checking database..."
MYSQL_OUTPUT=$(mysql -u root bookstack -e "SHOW COLUMNS FROM users LIKE 'face_token';" 2>/dev/null)
if [ ! -z "$MYSQL_OUTPUT" ]; then
    echo "   ✅ face_token column exists in users table"
else
    echo "   ⚠️  face_token column NOT found (may need migration)"
fi
echo ""

echo "======================================="
echo "🎯 Ready to test! Follow these steps:"
echo ""
echo "1. php artisan serve"
echo "2. Login to http://127.0.0.1:8000"
echo "3. Go to /my-account/profile"
echo "4. Click '📸 Đăng ký khuôn mặt'"
echo "5. Allow camera & capture face"
echo "6. If successful, you can test face login"
echo ""
