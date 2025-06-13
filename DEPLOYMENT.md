# Deployment Guide for Is It Done Yet?

## Deployment Structure for webhatchery.au/isitdoneyet

When deploying to your Apache server with PHP support, copy the following files and folders to the `wwwroot/isitdoneyet/` directory:

### Required Files and Folders:

```
isitdoneyet/
├── index.html                    # Frontend entry point
├── app.js                        # Frontend application logic
├── api-service.js                # API communication (updated for production)
├── style.css                     # Frontend styling
├── .htaccess                     # Apache rewrite rules
├── initialize-database.php       # Web-based database initialization (delete after use)
├── api/
│   └── index.php                 # API entry point (modified from backend/public/index.php)
└── backend/                      # Entire backend folder structure
    ├── src/                      # Backend source code
    ├── vendor/                   # Composer dependencies
    ├── storage/                  # Logs and storage
    ├── scripts/                  # Database scripts
    ├── composer.json
    ├── composer.lock
    └── .env                      # Database configuration (update for production)
```

## Deployment Steps:

1. **Copy all files** from this directory to `wwwroot/isitdoneyet/`

2. **Update the backend/.env file** with your production database credentials:
   ```env
   DB_HOST=localhost
   DB_NAME=your_production_database
   DB_USER=your_production_user
   DB_PASSWORD=your_production_password
   DEBUG=false
   ```

3. **Initialize the database** using the web-based tool:
   - Navigate to: `https://webhatchery.au/isitdoneyet/initialize-database.php`
   - Click "Initialize Database" 
   - The tool will create the projects table and add sample data
   - After successful initialization, delete the file for security

   **Alternative method** (if you have command line access):
   - Run: `php backend/scripts/initialize-database.php`

4. **Set file permissions** (if needed):
   - Ensure `backend/storage/logs/` is writable
   - Ensure proper permissions for PHP files

5. **Security cleanup**:
   - Delete `initialize-database.php` after successful initialization
   - The web tool includes a button to delete itself

## URLs After Deployment:

- **Frontend**: https://webhatchery.au/isitdoneyet/
- **API**: https://webhatchery.au/isitdoneyet/api/
- **API Status**: https://webhatchery.au/isitdoneyet/api/status

## What Changed for Production:

1. **API URL**: Updated from `http://localhost:3001/api` to `https://webhatchery.au/isitdoneyet/api`
   - Modified `api-service.js` constructor
   
2. **CORS Settings**: Added production domain to allowed origins
   - Updated both `backend/public/index.php` and `api/index.php`
   - Added `https://webhatchery.au` to allowed origins
   
3. **File Structure**: Created `api/index.php` to handle API routing in the same domain
   - This allows the API to be served from the same domain as the frontend
   - Updated paths to reference backend files correctly
   
4. **Apache Configuration**: Added `.htaccess` for proper routing
   - Handles API route rewrites to `api/index.php`
   - Ensures frontend routes serve `index.html`
   - Sets proper MIME types for JS/CSS files

## Files Modified for Deployment:

✅ **api-service.js** - Updated baseURL to production domain  
✅ **backend/public/index.php** - Added production CORS domain  
✅ **api/index.php** - Created production API entry point  
✅ **.htaccess** - Added Apache rewrite rules  
✅ **DEPLOYMENT.md** - Created deployment guide

## Testing:

After deployment, test these endpoints in order:

### **Step 1: Test Direct PHP Access**
- https://webhatchery.au/isitdoneyet/api/debug.php (Debug info)
- https://webhatchery.au/isitdoneyet/api/status.php (Simple status)

### **Step 2: Test Slim Framework Routing**
- https://webhatchery.au/isitdoneyet/api/status (Slim-based status)
- https://webhatchery.au/isitdoneyet/api/projects (Project data)

### **Step 3: Test Frontend**
- https://webhatchery.au/isitdoneyet/ (Frontend application)

## Troubleshooting:

### **If Step 1 fails:**
- Check file permissions and directory structure
- Ensure PHP is working on the server
- Verify files were uploaded correctly

### **If Step 1 works but Step 2 fails:**
- Apache mod_rewrite might not be enabled
- .htaccess might not be processed
- Try alternative URL: `https://webhatchery.au/isitdoneyet/api/index.php?path=status`

### **Common Issues:**
- **404 errors**: Check .htaccess file is uploaded and mod_rewrite is enabled
- **500 errors**: Check PHP error logs, usually permission or autoload issues
- **CORS errors**: Verify allowed origins in api/index.php
- **Database errors**: Check backend/.env credentials
