# V2LX Authentication System

A comprehensive authentication system for the V2LX Minecraft mod with HWID-based licensing and self-destruction capabilities.

## Features

- **HWID-based Authentication**: Licenses are bound to specific hardware IDs
- **Self-Destruction**: Mod automatically removes itself if authentication fails
- **Offline Validation**: Works offline for up to 24 hours after successful authentication
- **License Management**: Web interface for generating and managing licenses
- **Database Logging**: Tracks all authentication attempts and license usage

## Setup Instructions

### 1. Database Setup

1. Create a MySQL database and import the schema:
```bash
mysql -u root -p < database_schema.sql
```

2. Update the database credentials in `license_generator.php`:
```php
$db_host = 'localhost';
$db_name = 'auth_system';
$db_user = 'your_username';
$db_pass = 'your_password';
```

### 2. Web Server Setup

1. Upload the website files to your GitHub self-hosted domain
2. Ensure PHP 7.4+ is installed with MySQL support
3. Make sure the web server has write permissions for log files

### 3. Mod Configuration

1. Update `AuthConfig.java` with your domain:
```java
public static final String AUTH_SERVER_URL = "https://your-github-domain.com/api/auth";
```

2. Compile and test the mod

## API Endpoints

### Generate License
```http
POST /api/auth
Content-Type: application/json

{
    "action": "generate_license",
    "name": "User Name",
    "email": "user@example.com",
    "expiry_days": 30
}
```

### Authenticate
```http
POST /api/auth
Content-Type: application/json

{
    "action": "authenticate",
    "license_key": "LICENSE-KEY-HERE",
    "hwid": "HWID-HERE",
    "version": "1.2.2",
    "client_type": "minecraft_mod"
}
```

### Check License Status
```http
GET /api/auth?license_key=LICENSE-KEY-HERE
```

### Revoke License
```http
POST /api/auth
Content-Type: application/json

{
    "action": "revoke_license",
    "license_key": "LICENSE-KEY-HERE",
    "admin_key": "your_admin_key_here"
}
```

## Security Features

- **HWID Binding**: Each license can only be used on one device
- **Expiration Dates**: Licenses can have expiration dates
- **IP Logging**: All authentication attempts are logged with IP addresses
- **Admin Controls**: Licenses can be revoked remotely
- **Self-Destruction**: Mod removes itself if authentication fails

## File Structure

```
website/
├── index.html              # License management interface
├── license_generator.php   # API backend
├── database_schema.sql     # Database structure
└── README.md              # This file

src/main/java/dev/sigma/v2lx/auth/
├── AuthManager.java       # Main authentication logic
├── AuthConfig.java        # Configuration settings
├── HWIDGenerator.java     # Hardware ID generation
└── Auth.java             # Client authentication module
```

## Usage

1. **Generate License**: Use the web interface to create licenses for users
2. **Distribute**: Give users their license keys
3. **Authentication**: Users enter license keys in the mod
4. **Monitoring**: Check license usage and authentication logs in the database

## Development Mode

To disable authentication for development, set in `AuthConfig.java`:
```java
public static final boolean AUTH_ENABLED = false;
```

## Troubleshooting

- **Connection Issues**: Check firewall settings and ensure the auth server is accessible
- **HWID Generation**: Some systems may have restricted access to hardware information
- **Database Errors**: Verify database credentials and permissions
- **License Issues**: Check expiration dates and activation status

## Security Notes

- Change default admin passwords
- Use HTTPS for production
- Regularly backup the database
- Monitor authentication logs for suspicious activity
- Consider rate limiting for authentication endpoints
