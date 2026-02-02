🔐 FACE LOGIN - QUICK TEST STEPS (10 MINUTES)
================================================

PRE-TEST (Do Once)
==================

1. Start Server
   cd /Users/tranquangvu/BookStack
   php artisan serve
   → Opens: http://127.0.0.1:8000


TEST 1: FACE REGISTRATION (5 minutes)
======================================

Step 1: Login with your account
   URL: http://127.0.0.1:8000/login
   Email: (your admin email)
   Password: (your password)
   ✅ Expected: Logged in

Step 2: Go to profile
   URL: http://127.0.0.1:8000/my-account/profile
   ✅ Expected: See section "🔐 Xác thực khuôn mặt"

Step 3: Click "📸 Đăng ký khuôn mặt"
   ✅ Expected: Button says "⏳ Đang mở camera..."

Step 4: Allow camera permission
   Browser popup: "BookStack wants to use your camera"
   → Click: "Allow"
   ✅ Expected: Video preview shows your face

Step 5: Wait for auto-capture (800ms)
   ✅ Expected: Face captured, video hides, button says "🔍 Đang xử lý..."

Step 6: Check success
   ✅ Expected: Alert says ✅ Thành công!
   ✅ Expected: Page reloads
   ✅ Expected: Profile shows "✅ Khuôn mặt đã được đăng ký"

⏱️ Time: ~15-20 seconds


TEST 2: FACE LOGIN (5 minutes)
==============================

Step 1: Logout
   URL: http://127.0.0.1:8000/logout
   ✅ Expected: Redirected to login

Step 2: See face login button
   URL: http://127.0.0.1:8000/login
   ✅ Expected: See button "🔐 Đăng nhập bằng khuôn mặt"

Step 3: Click "🔐 Đăng nhập bằng khuôn mặt"
   ✅ Expected: Button says "⏳ Đang mở camera..."

Step 4: Allow camera permission
   ✅ Expected: Video preview shows your face

Step 5: Wait for auto-capture & login
   ✅ Expected: Face captured, server processes, button says "🔍 Đang nhận diện..."

Step 6: Auto login!
   ✅ Expected: Button says "✅ Nhận diện thành công!"
   ✅ Expected: Auto redirect to /home
   ✅ Expected: You are logged in!

⏱️ Time: ~20-25 seconds


TROUBLESHOOTING
===============

Issue: "No face detected"
Fix:
   ☀️ Better lighting
   📹 Face centered (50-70% of video)
   🔄 Try again 2-3 times
   🚀 Move closer to camera

Issue: Camera permission denied
Fix:
   Chrome: Settings → Privacy → Site Settings → Camera → Allow localhost

Issue: "Face not recognized"
Fix:
   ✅ Re-register with same lighting/angle
   🔆 Ensure good lighting
   📍 Same distance from camera


VERIFICATION CHECKLIST
======================

Registration:
   ☐ Camera opens
   ☐ Face detected
   ☐ Success alert appears
   ☐ Page reloads
   ☐ Profile shows ✅ status

Login:
   ☐ Camera opens
   ☐ Face detected
   ☐ Confidence > 75%
   ☐ Auto login succeeds
   ☐ Redirects to /home


DETAILED GUIDES
===============

For step-by-step help: FACE_TEST_COMPLETE_GUIDE.md
For registration only: FACE_REGISTRATION_GUIDE.md
For tech details: IMPLEMENTATION_SUMMARY.md
