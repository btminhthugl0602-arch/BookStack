# 📋 FACE LOGIN COMPLETE TEST GUIDE

## 🎯 Tất cả đã được fix! Đây là cách test từ đầu tới cuối:

---

## **PART 1: Chuẩn bị môi trường (5 phút)**

### Step 1.1: Restart server
```bash
# Terminal 1
cd /Users/tranquangvu/BookStack
php artisan serve
# Output: http://127.0.0.1:8000
```

### Step 1.2: Watch logs (optional)
```bash
# Terminal 2 (optional)
tail -f /Users/tranquangvu/BookStack/storage/logs/laravel.log
```

### Step 1.3: Verify setup
```bash
# Terminal 3
bash /Users/tranquangvu/BookStack/verify-face-setup.sh
```

Expected output:
```
✅ face-register-profile.ts imported
✅ face-login.ts imported
✅ Face registration form added to profile
✅ File exists (164 lines)
✅ face-register-profile code in app.js
✅ FaceLoginController exists
✅ FaceRegisterController exists
✅ POST /user/face/register route exists
✅ POST /login/face route exists
🎯 Ready to test!
```

---

## **PART 2: Test Face Registration (5 phút)**

### Step 2.1: Open browser & login
```
URL: http://127.0.0.1:8000/login
Email: (admin account)
Password: (password)
```

### Step 2.2: Navigate to profile
```
URL: http://127.0.0.1:8000/my-account/profile
Tìm section: "🔐 Xác thực khuôn mặt"
```

You should see:
```
Heading: "🔐 Xác thực khuôn mặt"
Sub-text: "Đăng ký khuôn mặt để đăng nhập bằng nhận diện khuôn mặt"
Button: "📸 Đăng ký khuôn mặt"
Video: (hidden)
Canvas: (hidden)
```

### Step 2.3: Click registration button
```
Click: "📸 Đăng ký khuôn mặt"
Expected: Button text changes to "⏳ Đang mở camera..."
```

### Step 2.4: Allow camera permission
```
Browser prompt: "BookStack wants to use your camera"
Click: "Allow"
Expected: Video preview shows your face (mirrored)
```

### Step 2.5: Auto-capture (no action needed)
```
After 800ms:
- Button text: "📸 Chụp ảnh..."
- Auto captures your face
- Video hides
- Button text: "🔍 Đang xử lý..."
- Sending to server...
```

### Step 2.6: Success!
```
Expected result:
✅ Alert popup: "✅ Khuôn mặt của bạn đã được đăng ký thành công!
   Giờ bạn có thể đăng nhập bằng khuôn mặt"
✅ Page reloads
✅ Profile section shows: "✅ Khuôn mặt đã được đăng ký"
```

### Step 2.7: Verify in database (optional)
```bash
mysql -u root bookstack -e "SELECT id, email, face_token FROM users WHERE face_token IS NOT NULL;"
```

You should see your user with a face_token value!

---

## **PART 3: Test Face Login (5 phút)**

### Step 3.1: Logout
```
URL: http://127.0.0.1:8000/logout
Expected: Redirect to login page
```

### Step 3.2: See face login button
```
URL: http://127.0.0.1:8000/login
Expected: You see button "🔐 Đăng nhập bằng khuôn mặt"
```

### Step 3.3: Click face login
```
Click: "🔐 Đăng nhập bằng khuôn mặt"
Expected: 
- Button text: "⏳ Đang mở camera..."
- Camera opens
```

### Step 3.4: Face detected & recognized
```
Expected console logs:
✅ Camera opened successfully
✅ Camera resolution: 1280x720
📸 Captured image, sending to server...
Server response: {success: true, confidence: 85}
```

### Step 3.5: Auto login!
```
Expected:
✅ Button text: "✅ Nhận diện thành công!"
✅ Auto redirect to /home
✅ You are logged in!
```

---

## **PART 4: Console Debugging (if errors)**

### Open DevTools
```
F12 → Console tab
```

### Look for these logs:

**Success logs:**
```
✅ Camera opened successfully
✅ Camera opened for face registration
Camera resolution: 1280x720
📸 Captured image, sending to server...
Face registration response: {success: true}
Server response: {success: true, confidence: 85}
```

**Error logs:**
```
❌ Camera error: NotAllowedError
❌ Fetch error: ...
❌ Capture error: ...
```

---

## **PART 5: Common Issues & Fixes**

### **Issue: "No face detected" 
```
Cause: 
- Bad lighting
- Face not centered
- Face too small
- Resolution too low

Fix:
☀️ Move to better lighting
📹 Center face in frame (50-70% of screen)
🔄 Try again 2-3 times
🚀 Move closer to camera
```

### **Issue: Camera won't open (Permission denied)**
```
Chrome fix:
1. Chrome menu → Settings
2. Privacy and security → Site Settings
3. Camera
4. Find localhost:8000
5. Change to "Allow"
6. Reload page
```

### **Issue: "Face not recognized" in login**
```
Cause:
- Lighting different from registration
- Angle different
- Expression different
- Distance from camera different

Fix:
✅ Re-register with same conditions
🔆 Use same lighting as registration
😊 Same expression
📍 Same distance
```

### **Issue: Server error 500**
```
Check server logs:
tail -50 /Users/tranquangvu/BookStack/storage/logs/laravel.log

Look for:
- "Face++ service error"
- "No user mapping"
- API timeout

Fix:
1. Verify .env variables
2. Check Face++ API status
3. Try with better image quality
```

---

## **PART 6: Advanced Testing**

### Test multiple faces
```
Register face 2:
1. Go back to profile
2. Click "📸 Đăng ký khuôn mặt" again
3. Different angle/lighting
4. This will replace previous face_token
(Current implementation keeps only 1 face per user)
```

### Test with different conditions
```
✅ Register with glasses → Login without glasses
✅ Register in daylight → Login in artificial light
✅ Register standing → Login sitting
✅ Register at distance → Login close up
(How much variation can Face++ handle?)
```

### Test on mobile
```
If on same network:
1. Get your Mac IP: ifconfig | grep inet
2. Access from phone: http://YOUR_IP:8000
3. Test camera on mobile browser
4. Register & login from phone
```

---

## **PART 7: Performance Metrics**

### Time measurements:
```
Camera open: ~500ms
Auto capture delay: 800ms
Face detection: ~1-2s
Search in faceset: ~1-2s
Total login time: ~3-4s
Total registration time: ~4-5s
```

### API calls made:
```
Registration:
POST /facepp/v3/detect
POST /facepp/v3/faceset/addface

Login:
POST /facepp/v3/detect
POST /facepp/v3/search
```

---

## **PART 8: Final Verification Checklist**

- [ ] Server running on http://127.0.0.1:8000
- [ ] Can login with email/password
- [ ] Profile page loads without errors
- [ ] Face registration button appears
- [ ] Camera opens when clicked
- [ ] Face is detected
- [ ] Face registration succeeds
- [ ] Profile shows "✅ Khuôn mặt đã được đăng ký"
- [ ] face_token saved in database
- [ ] Logout works
- [ ] Face login button appears on login page
- [ ] Camera opens for face login
- [ ] Face is detected
- [ ] Login succeeds with face
- [ ] Redirected to /home
- [ ] Console shows no errors

---

## **QUICK REFERENCE**

| What | URL | Expected |
|------|-----|----------|
| Profile | http://127.0.0.1:8000/my-account/profile | Face registration section visible |
| Login | http://127.0.0.1:8000/login | Face login button visible |
| Register face | Click "📸 Đăng ký khuôn mặt" | Alert success, page reloads |
| Login face | Click "🔐 Đăng nhập bằng khuôn mặt" | Redirects to /home |
| Server logs | tail -f storage/logs/laravel.log | "Face login attempt", "Face detected" |
| Database | mysql→ SELECT face_token FROM users | Shows face token for registered users |

---

## **📞 SUPPORT**

If stuck:
1. **Check console (F12 → Console)** - Copy-paste error
2. **Check server logs** - tail -f storage/logs/laravel.log
3. **Verify .env** - grep FACEPP .env
4. **Clear cache** - php artisan cache:clear
5. **Rebuild assets** - npm run build
6. **Restart server** - Kill and restart php artisan serve

---

**You are ready! Start with Step 1.1 and follow the guide 🚀**
