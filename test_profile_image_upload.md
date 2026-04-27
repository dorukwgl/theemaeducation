# Profile Image Upload Test Guide

## Summary

Profile image upload functionality has been successfully added to the registration endpoint.

## Files Modified/Created

1. **NEW:** `src/utils/ImageProcessor.php` - Image processing utility class
2. **MODIFIED:** `src/config/constants.php` - Added PATH_PROFILE_IMAGES constant
3. **MODIFIED:** `src/controllers/AuthController.php` - Added profile image upload handling
4. **MODIFIED:** `src/services/AuthService.php` - Added image field support
5. **MODIFIED:** `src/models/User.php` - Updated create() to handle image field

## How to Test

### Registration with Profile Image

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: multipart/form-data" \
  -F "full_name=John Doe" \
  -F "email=john@example.com" \
  -F "phone=1234567890" \
  -F "password=SecurePass123" \
  -F "profile_image=@/path/to/image.jpg"
```

### Registration without Profile Image (Still Works)

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: multipart/form-data" \
  -F "full_name=Jane Doe" \
  -F "email=jane@example.com" \
  -F "phone=0987654321" \
  -F "password=SecurePass456"
```

## Features

### Image Processing
- **Max Dimensions:** 1200x1200 pixels (maintains aspect ratio)
- **JPEG Quality:** 90% compression
- **Supported Formats:** JPEG, PNG, GIF, WebP
- **File Size Limit:** 5MB

### Security
- MIME type validation using finfo_file()
- Secure filename generation (random + timestamp)
- File permissions set to 0644
- Path traversal protection
- Upload error handling

### Storage
- **Directory:** `uploads/profile_images/` (auto-created)
- **Database:** Image path stored in users table `image` column
- **Session:** Image path stored in `$_SESSION['user_image']`

## Implementation Details

### ImageProcessor::processImage()
Processes images by:
1. Loading the source image using GD library
2. Calculating dimensions to maintain aspect ratio
3. Creating a new image with scaled dimensions
4. Saving with specified quality

### ImageProcessor::validateProfileImage()
Validates:
- Upload error codes
- File size (max 5MB)
- MIME type (JPEG, PNG, GIF, WebP)
- Image validity (checks if it's a real image)

### AuthController::processProfileImage()
Handles the upload process:
1. Validates the uploaded file
2. Determines file extension
3. Generates secure filename
4. Processes and saves the image
5. Returns the image path or error

## Expected Response

### Successful Registration with Image
```json
{
  "success": true,
  "message": "Registration successful",
  "data": {
    "id": 1,
    "full_name": "John Doe",
    "email": "john@example.com",
    "phone": "1234567890",
    "image": "uploads/profile_images/abc123def456_1234567890.jpg",
    "role": "user",
    "created_at": "2026-04-26 12:00:00"
  }
}
```

### Validation Error (Invalid Image)
```json
{
  "success": false,
  "message": "Profile image validation failed",
  "errors": {
    "profile_image": "Profile image must be less than 5MB"
  }
}
```

## Testing Checklist

- [x] ImageProcessor utility created
- [x] Constants updated with PATH_PROFILE_IMAGES
- [x] AuthController register() updated
- [x] AuthService register() updated
- [x] User model create() updated
- [x] Session includes user_image
- [ ] Test registration with valid profile image
- [ ] Test registration without profile image
- [ ] Test with oversized image (>5MB)
- [ ] Test with invalid file type (PDF)
- [ ] Verify image scaling to 1200x1200
- [ ] Verify JPEG compression quality
- [ ] Check image path in database
- [ ] Verify image accessibility

## Next Steps

The implementation is complete and ready for testing. The `uploads/profile_images/` directory will be automatically created when the first profile image is uploaded during registration.

## Notes

- Registration still works without profile image (field is optional)
- Images are stored with secure filenames to prevent path traversal
- All image processing uses GD library (PHP extension)
- Error handling includes logging for debugging
- Temporary files are cleaned up automatically by PHP
