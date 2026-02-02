# 🔐 Face Detection - Complete Setup Guide

## ✅ Vấn đề "No user mapping" Đã Sửa!

**Nguyên nhân:** Người dùng chưa đăng ký khuôn mặt  
**Giải pháp:** Thêm form đăng ký khuôn mặt vào user profile page

---

## 🚀 Test Step-by-Step

### **Step 1: Đăng nhập vào account của bạn**
```
URL: http://127.0.0.1:8000/login
Email: your@email.com
Password: your_password
```

### **Step 2: Vào My Account → Profile**
```
URL: http://127.0.0.1:8000/my-account/profile
Tìm section: "🔐 Xác thực khuôn mặt"
Click button: "📸 Đăng ký khuôn mặt"
```

### **Step 3: Cho phép camera**
- Browser sẽ hỏi quyền camera
- Click "Allow" (Cho phép)
- Video preview sẽ hiện lên

### **Step 4: Chụp ảnh khuôn mặt**
- Đợi 800ms tự động chụp
- Hoặc bạn có thể click lại button
- Đợi server xử lý (~1-2 giây)

### **Step 5: Kiểm tra kết quả**
✅ **Nếu thành công:**
- Alert: "✅ Khuôn mặt của bạn đã được đăng ký thành công!"
- Page reload
- Profile sẽ hiển thị: "✅ Khuôn mặt đã được đăng ký"

❌ **Nếu lỗi:**
- Check console (F12) cho error message
- Thử lại với ánh sáng tốt hơn

### **Step 6: Test Face Login**
```
1. Logout: /logout
2. Vào login: http://127.0.0.1:8000/login
3. Click: "🔐 Đăng nhập bằng khuôn mặt"
4. Cho phép camera
5. Đợi nhận diện (~2 giây)
6. Nếu match: Tự động redirect tới /home ✅
```

---

## 🐛 Debugging

### **Console Logs (F12 → Console)**
```javascript
// Thành công sẽ có:
✅ Camera opened for face registration
Camera resolution: 1280x720
📸 Captured image, sending to server...
Face registration response: {success: true}
```

### **Server Logs**
```bash
tail -f /Users/tranquangvu/BookStack/storage/logs/laravel.log | grep -i face
```

Expected:
```
Face registration attempt, user_id=1, image_size=12345
Face detected during registration, user_id=1
Face registered successfully, user_id=1
```

### **Database Check**
```bash
# Check nếu face_token được lưu
mysql -u root -e "SELECT id, email, face_token FROM bookstack.users;"
```

Expected (sau khi đăng ký):
```
| id | email            | face_token           |
|----|------------------|----------------------|
| 1  | admin@admin.com   | 123456abcdef...      |
```

---

## ❌ Common Issues & Solutions

### **Issue 1: "No face detected" when registering**
**Nguyên nhân:** Ánh sáng yếu hoặc mặt không rõ

**Fix:**
- ☀️ Tăng ánh sáng (mở đèn, ngồi gần cửa sổ)
- 📹 Đảm bảo mặt chiếm 50-70% frame
- 🔄 Thử lại 2-3 lần
- 🚀 Di chuyển gần camera hơn

### **Issue 2: "Camera permission denied"**
**Nguyên nhân:** Browser chưa cấp quyền camera

**Fix (Chrome):**
1. Settings → Privacy and security
2. Site Settings → Camera
3. Find localhost:8000
4. Change to "Allow"
5. Reload page

**Fix (Safari/Firefox):**
- Click icon camera bên URL bar
- Click "Allow"
- Reload page

### **Issue 3: "500 Internal Server Error" saat registering**
**Nguyên nhân:** Face++ API error (credentials sai)

**Fix:**
1. Check .env variables:
   ```bash
   grep FACEPP /Users/tranquangvu/BookStack/.env
   ```

2. Verify API credentials are valid:
   - FACEPP_API_KEY=i_G-c9lyEFdkUHaqM8mUTRdnep8-thWX
   - FACEPP_API_SECRET=1HdImu9JgDUjw9Vn84flgsgxo51JOtvd
   - FACEPP_FACESET_TOKEN=7ce8f1b8dd7f42f2986f7202d2a071d2

3. Check server logs for detailed error:
   ```bash
   tail -100 /Users/tranquangvu/BookStack/storage/logs/laravel.log
   ```

### **Issue 4: Face registration works, but login says "Face not recognized"**
**Nguyên nhân:** Ảnh khác quá (lighting, angle, expression)

**Fix:**
- ✅ Re-register dengan cùng điều kiện lighting/angle
- 🔆 Thử login lại với điều kiện tương tự
- 👁️ Mất kính mắt nếu có
- 😊 Biểu cảm bình thường (không cười quá)

---

## 📋 Complete Flow Diagram

```
1. Đăng nhập
   ↓
2. Vào /my-account/profile
   ↓
3. Click "📸 Đăng ký khuôn mặt"
   ↓
4. Camera request → User cho phép
   ↓
5. Video preview hiển thị
   ↓
6. Tự động chụp sau 800ms
   ↓
7. Gửi ảnh tới /user/face/register (POST)
   ↓
   ├─→ Detect faces (Face++ API)
   │   ├─→ No face? → Error 422
   │   └─→ Has face? → Lấy face_token
   │
   ├─→ Add to Faceset (Face++ API)
   │   └─→ Lưu outer_id = user_id
   │
   └─→ Save face_token tới database
       └─→ Success! Refresh page
   ↓
8. User logout
   ↓
9. Vào /login
   ↓
10. Click "🔐 Đăng nhập bằng khuôn mặt"
    ↓
11. Camera request → User cho phép
    ↓
12. Chụp ảnh
    ↓
13. Gửi tới /login/face (POST)
    ↓
    ├─→ Detect faces
    │   └─→ No face? → Error 422
    │
    ├─→ Search in Faceset
    │   └─→ No match? → Error 401
    │   └─→ Found? → Lấy outer_id (user_id)
    │
    ├─→ Check confidence
    │   └─→ Low? → Error 401
    │
    └─→ Find & Login user
        └─→ Success! Redirect to /home
```

---

## ✅ Verification Checklist

- [ ] Có thể login bằng email/password
- [ ] Vào profile page không error
- [ ] Camera opens khi click "📸 Đăng ký"
- [ ] Face được detect (check console)
- [ ] Face registration thành công
- [ ] Profile shows "✅ Khuôn mặt đã được đăng ký"
- [ ] Database has face_token (SELECT face_token FROM users)
- [ ] Logout
- [ ] Vào login page
- [ ] Click "🔐 Đăng nhập bằng khuôn mặt"
- [ ] Face được detect
- [ ] Login thành công → Redirect to /home

---

## 📞 Quick Help

| Command | Purpose |
|---------|---------|
| `tail -f storage/logs/laravel.log` | Watch real-time logs |
| `grep "face_token" database/migrations/*.php` | Check migration |
| `php artisan migrate --step` | Run migrations |
| `npm run build` | Rebuild assets |
| `php artisan serve` | Start dev server |
| `mysql -u root bookstack` | Access database |

---

## 🎯 Next After Successful Registration

1. ✅ Register 2-3 different faces (different angles/lighting)
2. ✅ Test login from mobile
3. ✅ Test with glasses/without glasses
4. ✅ Test with different expressions
5. ✅ Test with poor lighting
6. ✅ Adjust confidence threshold if needed

---

**Last Updated:** 2026-02-01  
**Status:** ✅ Ready to Test Face Registration!
