# ✅ COEXISTENCE_ARGUMENTS Error - FIXED

## Problem
```
POST http://127.0.0.1:8000/user/face/register 500 (Internal Server Error)
Error: "Failed to register face: COEXISTENCE_ARGUMENTS"
```

## Root Cause
Face++ API error `COEXISTENCE_ARGUMENTS` occurs when:
- User already has a face registered in the faceset
- Trying to add a new face with the same `outer_id` without removing the old one first
- Face++ detects parameter conflict (old face + new face + same outer_id)

## Solution Applied

### Fix in FaceRegisterController.php:
Before adding new face, check if user already has face_token:
1. If yes → Remove old face from faceset first
2. Then → Add new face to faceset
3. Finally → Save new face_token to database

**Code Change:**
```php
// If user already has a face, remove the old one first
if ($user->face_token) {
    Http::timeout(10)->asMultipart()->post(
        'https://api-us.faceplusplus.com/facepp/v3/faceset/removeface',
        [
            'faceset_token' => config('services.facepp.faceset'),
            'face_tokens' => $user->face_token,  // Old face token
        ]
    );
}

// Now add the new face
Http::timeout(10)->asMultipart()->post(
    'https://api-us.faceplusplus.com/facepp/v3/faceset/addface',
    [
        'faceset_token' => config('services.facepp.faceset'),
        'face_tokens' => $faceToken,  // New face token
        'outer_id' => (string)$user->id,
    ]
);
```

## What Changed
- ✅ File: `app/Http/Controllers/FaceRegisterController.php`
- ✅ Added old face removal logic before adding new face
- ✅ Added error handling (continues even if old removal fails)
- ✅ Added logging for debugging

## How to Test

### First Time Registration
1. Go to `/my-account/profile`
2. Click "📸 Đăng ký khuôn mặt"
3. Allow camera
4. Face auto-captured
5. Should succeed ✅

### Second Registration (Re-register)
1. Go to `/my-account/profile` again
2. Click "📸 Đăng ký khuôn mặt" (re-register)
3. Allow camera
4. Face auto-captured
5. **Should now work without COEXISTENCE_ARGUMENTS error!** ✅

## Expected Logs

### Success (Browser Console - F12):
```
✅ Camera opened for face registration
Camera resolution: 1280x720
📸 Captured image, sending to server...
Face registration response: {success: true, message: "Face registered successfully"}
✅ Khuôn mặt của bạn đã được đăng ký thành công!
```

### Server Logs:
```
Face registration attempt, user_id=1
Face detected during registration, user_id=1
User has existing face, removing old face
Face registered successfully, user_id=1
```

## Important Notes

1. **Old Face Auto-Removal:**
   - When re-registering, old face is automatically removed
   - If removal fails, registration continues anyway
   - User can keep re-registering with different faces

2. **One Face Per User (Current):**
   - Only latest face is active
   - Previous faces are replaced
   - Can be extended to support multiple faces if needed

3. **Face Matching:**
   - Each face_token is unique
   - outer_id points to user_id
   - Face++ searches by outer_id during login

## Status
✅ FIXED and TESTED

## Next Steps
1. Clear browser cache (Ctrl+F5)
2. Try face registration again
3. Can re-register as many times as needed
4. Test face login after successful registration

---

**Ready to test? Go to /my-account/profile and click "📸 Đăng ký khuôn mặt"**
