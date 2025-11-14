# LaptopHub Plaintext Database Documentation

This project uses a **plaintext JSON file-based database** approach for data storage. This is a lightweight, portable, and easy-to-maintain solution suitable for small to medium-sized applications.

## Database Structure

### Directory Layout

```
/home/user/dd/
├── data/                      # JSON database files
│   ├── laptops.json          # Laptop product listings
│   ├── users.json            # Admin user credentials (hashed passwords)
│   ├── contacts.json         # Contact form submissions
│   └── newsletter.json       # Newsletter subscriptions
│
└── uploads/                  # User-uploaded files
    └── laptops/              # Laptop product images
        └── .gitkeep          # Keeps directory in git
```

## Data Files

### 1. laptops.json
Stores laptop product information with local image paths.

**Schema:**
```json
[
  {
    "id": 1,
    "brand": "Lenovo ThinkPad",
    "model": "T490",
    "processor": "Intel Core i7",
    "ram": "16GB RAM",
    "storage": "512GB SSD",
    "screen": "14\" FHD Display",
    "price": 549,
    "condition": "excellent",
    "description": "Professional business laptop",
    "visualRating": 9,
    "technicalRating": 8,
    "images": [
      "uploads/laptops/laptop_abc123_1234567890.jpg",
      "uploads/laptops/laptop_def456_1234567891.jpg"
    ],
    "imageUrl": "uploads/laptops/laptop_abc123_1234567890.jpg",
    "created_at": "2024-01-15 10:30:00",
    "updated_at": "2024-01-15 10:30:00"
  }
]
```

**Fields:**
- `id` (integer): Auto-incremented unique identifier
- `brand` (string): Laptop manufacturer/brand
- `model` (string): Laptop model name
- `processor` (string): CPU information
- `ram` (string): Memory specifications
- `storage` (string): Storage specifications
- `screen` (string): Display size and resolution
- `price` (number): Price in USD
- `condition` (string): One of: "excellent", "very-good", "good"
- `description` (string): Detailed product description
- `visualRating` (integer, 1-10): Visual condition rating
- `technicalRating` (integer, 1-10): Technical performance rating
- `images` (array): Array of local file paths to product images
- `imageUrl` (string): Primary image (first in images array)
- `created_at` (datetime): Creation timestamp
- `updated_at` (datetime): Last modification timestamp

### 2. users.json
Stores admin user authentication data.

**Schema:**
```json
[
  {
    "id": 1,
    "username": "admin",
    "password": "$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi",
    "email": "admin@laptophub.lv"
  }
]
```

**Security Notes:**
- Passwords are hashed using PHP's `password_hash()` with bcrypt
- Never store plaintext passwords
- Use `password_verify()` for authentication

### 3. contacts.json
Stores contact form submissions.

**Schema:**
```json
[
  {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+371 12345678",
    "message": "I'm interested in...",
    "submitted_at": "2024-01-15 14:20:00"
  }
]
```

### 4. newsletter.json
Stores newsletter subscription emails.

**Schema:**
```json
[
  {
    "id": 1,
    "name": "Jane Doe",
    "email": "jane@example.com",
    "subscribed_at": "2024-01-15 16:45:00"
  }
]
```

## Image Storage Best Practices

### Local File Storage
- **Directory**: `/uploads/laptops/`
- **Naming Convention**: `{prefix}_{unique_id}_{timestamp}.{extension}`
  - Example: `laptop_65a3f2c1b4e59_1705324321.jpg`
- **Allowed Formats**: JPG, JPEG, PNG, GIF, WebP
- **Maximum Size**: 5MB per file
- **Validation**: MIME type and extension checking

### Image Upload Flow

1. **Upload**: Admin uploads image via `/api/upload.php`
   - Supports both multipart/form-data and base64 encoded images
   - Validates file type, size, and format
   - Generates unique filename to prevent conflicts

2. **Storage**: Images saved to `/uploads/laptops/` directory
   - Original filename is replaced with secure generated name
   - File permissions set to 0644 (readable by web server)

3. **Database Reference**: Relative path stored in JSON
   - Example: `uploads/laptops/laptop_abc123_1234567890.jpg`
   - Multiple images per laptop supported via `images` array

4. **Deletion**: When laptop is deleted
   - Associated image files are automatically removed from filesystem
   - Only deletes files in `uploads/` directory (not external URLs)

### Automatic Base64 Conversion
The API automatically converts base64 encoded images to local files:
- Detects data URI format: `data:image/{type};base64,{data}`
- Decodes and saves to local filesystem
- Returns file path for database storage

## API Endpoints

### Laptop Management
- `GET /api/laptops.php` - Fetch all laptops
- `GET /api/laptops.php?id={id}` - Fetch single laptop
- `POST /api/laptops.php` - Create new laptop (auto-converts base64 images)
- `PUT /api/laptops.php` - Update laptop (auto-converts base64 images)
- `DELETE /api/laptops.php` - Delete laptop (removes image files)

### Image Upload
- `POST /api/upload.php` - Upload images (multipart or base64)
- `DELETE /api/upload.php` - Delete single image file

### Authentication
- `POST /api/auth.php` - Admin login
- `GET /api/auth.php` - Check login status
- `DELETE /api/auth.php` - Logout

### Contact Forms
- `POST /api/contact.php` - Submit contact form
- `GET /api/contact.php` - Fetch contact submissions (admin)

## File Operations (config.php)

### JSON Operations
```php
readJSONFile($filepath)   // Read and parse JSON file
writeJSONFile($filepath, $data)  // Write data to JSON with file locking
getNextId($data)          // Generate auto-increment ID
```

### Image Operations
```php
saveUploadedImage($file, $directory, $prefix)  // Save multipart upload
saveBase64Image($base64Data, $directory, $prefix)  // Save base64 image
deleteImageFile($path)    // Delete image file
validateImageFile($file)  // Validate uploaded file
```

### Security Functions
```php
sanitizeInput($data)      // HTML entity encoding
isValidEmail($email)      // Email validation
requireAdmin()            // Enforce admin authentication
startSecureSession()      // Start secure PHP session
```

## Backup Recommendations

### Manual Backup
```bash
# Backup entire database
tar -czf backup_$(date +%Y%m%d).tar.gz data/ uploads/
```

### Automated Backup (cron)
```bash
# Add to crontab: Daily backup at 2 AM
0 2 * * * cd /home/user/dd && tar -czf backups/backup_$(date +\%Y\%m\%d).tar.gz data/ uploads/
```

## Migration Path

If the application grows beyond the capacity of file-based storage, migration to a relational database (MySQL/PostgreSQL) is straightforward:

1. **Schema Conversion**: JSON structure maps directly to database tables
2. **Image Storage**: Keep local file storage, update paths if needed
3. **API Layer**: Minimal changes required (swap JSON file operations with SQL queries)
4. **Data Import**: Simple JSON parsing and SQL INSERT operations

## Advantages of This Approach

1. **Simplicity**: No database server required
2. **Portability**: Entire database is a set of text files
3. **Version Control**: Database changes can be tracked in git
4. **Transparency**: Easy to inspect and edit manually
5. **Backup**: Simple file copy operations
6. **Development**: No database setup needed for local development
7. **Hosting**: Works on any PHP hosting (no database required)

## Limitations

1. **Concurrency**: File locking used, but not as robust as RDBMS
2. **Performance**: Not suitable for high-traffic applications (>1000 concurrent users)
3. **Querying**: No complex queries (filters done in PHP)
4. **Indexing**: No built-in indexing (full file reads required)
5. **Relationships**: Manual relationship management
6. **Scalability**: Suitable for <10,000 records total

## Security Considerations

1. **File Permissions**:
   - JSON files: 0644 (read/write for owner, read for others)
   - Upload directory: 0755 (executable for directory listing)

2. **Input Validation**:
   - All user input is sanitized using `sanitizeInput()`
   - Email addresses validated with PHP filters
   - File uploads validated by MIME type and extension

3. **Authentication**:
   - Session-based authentication with secure cookies
   - Password hashing with bcrypt (cost factor 10)
   - CSRF protection via same-site cookies

4. **File Upload Security**:
   - MIME type verification (not just extension)
   - File size limits enforced
   - Unique filenames prevent overwriting
   - Upload directory outside document root recommended for production

5. **Directory Listing**:
   - Ensure directory listing is disabled in web server config
   - Add `.htaccess` to prevent direct access to uploads (if using Apache)

## Production Recommendations

1. Move `data/` directory outside web root
2. Move `uploads/` outside web root and serve via PHP script
3. Enable HTTPS (update `session.cookie_secure` to 1)
4. Change `ALLOWED_ORIGIN` from `*` to your domain
5. Disable error display: `ini_set('display_errors', 0)`
6. Implement rate limiting on API endpoints
7. Add CSRF tokens to forms
8. Regular backups (automated daily)
9. Monitor file system usage
10. Consider moving to database if traffic exceeds 100 concurrent users
