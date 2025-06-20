<?php
// =============================================================================
// README.md
// =============================================================================
/*
# Email Validation Tool

A comprehensive PHP/MySQL email validation application with modern UI design and advanced features.

## Features

### Core Functionality
- Single email validation with format, domain, and SMTP checks
- Bulk email upload and validation (CSV support)
- Smart caching system (3-month validation cache)
- Multiple SMTP provider support (PHP Mail, Custom SMTP, Postmark, SendGrid, Mailgun)
- Email verification via test emails

### User Management
- Admin and user roles
- User creation and management
- Activity logging
- Session management

### User Interface
- Dark/light mode toggle
- Responsive Bootstrap design
- Interactive dashboard with statistics
- Real-time validation results
- Export functionality (CSV)

### Technical Features
- Secure password hashing
- SQL injection protection
- Input validation and sanitization
- RESTful API endpoints
- Comprehensive error handling

## Installation

1. Run the installation script: `install.php`
2. Configure your database connection
3. Create an admin account
4. Access the application via `index.php`

## File Structure

```
/
├── config/
│   └── database.php          # Database configuration
├── api/
│   ├── validate-email.php    # Single email validation
│   ├── bulk-upload.php       # Bulk upload handler
│   ├── dashboard-stats.php   # Dashboard statistics
│   ├── get-results.php       # Results retrieval
│   ├── export-results.php    # CSV export
│   ├── smtp-settings.php     # SMTP configuration
│   ├── users.php            # User management
│   └── activity-logs.php     # Activity logging
├── cron/
│   └── process-validations.php # Background validation processor
├── install.php              # Installation script
├── index.php               # Main application
├── login.php              # Login page
└── logout.php             # Logout handler
```

## Database Schema

- `users`: User accounts and roles
- `email_validations`: Validation results and history
- `email_batches`: Bulk upload tracking
- `smtp_settings`: Email provider configurations
- `activity_logs`: User activity tracking
- `system_settings`: Application configuration

## SMTP Providers Supported

1. **PHP Mail**: Uses PHP's built-in mail() function
2. **Custom SMTP**: Any SMTP server with authentication
3. **Postmark**: Transactional email service
4. **SendGrid**: Email delivery platform
5. **Mailgun**: Email automation service

## Security Features

- Password hashing with PHP's password_hash()
- SQL prepared statements
- Session-based authentication
- Input validation and sanitization
- Role-based access control
- Activity logging for audit trails

## Configuration

### System Settings
- `validation_cache_days`: How long to cache validation results (default: 90 days)
- `max_bulk_emails`: Maximum emails per bulk upload (default: 10,000)
- `verification_timeout`: SMTP verification timeout (default: 30 seconds)
- `daily_validation_limit`: Daily validation limit per user (default: 1,000)

### Cron Job Setup
Add to your crontab to process bulk validations:
```bash
*/5 * * * * php /path/to/your/app/cron/process-validations.php
```

## API Endpoints

All API endpoints return JSON responses and require authentication.

### Authentication Required
- `POST /api/validate-email.php` - Validate single email
- `POST /api/bulk-upload.php` - Upload bulk email list
- `GET /api/dashboard-stats.php` - Get dashboard statistics
- `GET /api/get-results.php` - Get validation results
- `GET /api/export-results.php` - Export results as CSV
- `GET/POST /api/smtp-settings.php` - SMTP configuration

### Admin Only
- `GET/POST/PUT/DELETE /api/users.php` - User management
- `GET /api/activity-logs.php` - Activity logs

## Usage

1. **Login**: Use your credentials to access the application
2. **Single Validation**: Enter an email address for immediate validation
3. **Bulk Upload**: Upload a CSV file with email addresses
4. **View Results**: Monitor validation progress and results
5. **Export Data**: Download validation results as CSV
6. **Configure SMTP**: Set up email sending providers
7. **Manage Users** (Admin): Create and manage user accounts

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- PDO extension
- OpenSSL extension (for SMTP)
- cURL extension (for API providers)

## Support

For issues or questions, please check the activity logs for detailed error information.
*/
?>