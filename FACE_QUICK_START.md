## ⚡ Quick Face Detection Test

### **1. Verify Setup (2 min)**
```bash
bash /Users/tranquangvu/BookStack/test-face-setup.sh
```
Expected: ✅ All files exist and .env variables correct

### **2. Start Server (1 min)**
```bash
cd /Users/tranquangvu/BookStack
php artisan serve
```
Expected: ✅ Server running at http://127.0.0.1:8000

### **3. Test Face Login (5 min)**
- Open http://127.0.0.1:8000/login
- Click "🔐 Đăng nhập bằng khuôn mặt"
- Allow camera when prompted
- Wait for face detection
- Check console (F12) for logs

### **4. Check Logs (1 min)**
```bash
tail -50 /Users/tranquangvu/BookStack/storage/logs/laravel.log | grep -i face
```

Expected output:
```
Face login attempt
Face detected
Face search result confidence=85
User logged in via face recognition
```

---

## 🐛 Common Issues & Fixes

| Issue | Solution |
|-------|----------|
| "No face detected" | Better lighting, face centered, try again |
| "Face not recognized" | Verify faceset token, re-register face |
| "Low confidence" | Move closer to camera, better lighting |
| Camera won't open | Check permissions (Chrome → Settings → Privacy) |
| 500 error | Check .env variables, Face++ API status |
| Build failed | Run `npm install && npm run build` |

---

## 📋 Files Changed

1. ✅ `app/Config/services.php` - Config variables fixed
2. ✅ `resources/js/face-login.ts` - Camera & error handling improved
3. ✅ `resources/js/face-register.ts` - New registration flow
4. ✅ `resources/js/app.ts` - Import face-register
5. ✅ `app/Http/Controllers/FaceLoginController.php` - Error handling + logging
6. ✅ `app/Http/Controllers/FaceRegisterController.php` - Error handling + logging
7. ✅ `resources/views/auth/login.blade.php` - UI improvements
8. ✅ `database/migrations/2026_01_28_062013_add_face_token_to_users.php` - Already exists

---

## 🔍 Debug Checklist

- [ ] .env has FACEPP_API_KEY, FACEPP_API_SECRET, FACEPP_FACESET_TOKEN
- [ ] services.php references correct .env vars
- [ ] Migration has run: `php artisan migrate`
- [ ] Assets built: `npm run build`
- [ ] Browser console shows no errors (F12 → Console)
- [ ] Network tab shows POST /login/face returning 200 (F12 → Network)
- [ ] Server logs show "Face login attempt" message

---

**All fixes applied! You're ready to test. Start with:**
```bash
php artisan serve
# Then open http://127.0.0.1:8000/login and click face login button
```
