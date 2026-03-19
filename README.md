# OJT Calcu — PHP Edition

A PHP-based OJT (On-the-Job Training) internship hours tracker.

## Project Structure

```
ojt_tracker/
│
├── index.php           ← Login & Register page
├── dashboard.php       ← Main dashboard with stats & progress
├── logs.php            ← Full time logs table
├── settings.php        ← Profile, password, logout
├── logout.php          ← Quick logout shortcut
│
├── includes/
│   ├── config.php      ← App config, data helpers, auth helpers
│   ├── header.php      ← Shared HTML head + sidebar
│   └── footer.php      ← Closing HTML + JS
│
├── css/
│   └── main.css        ← All styles
│
├── js/
│   └── app.js          ← Modal, hour preview, delete confirm
│
└── data/
    └── users.json      ← Auto-created; stores all user data
```

## Requirements

- PHP 8.0+
- No database required (uses flat JSON file storage)
- Web server (Apache/Nginx) OR PHP built-in server

## Quick Start

### Option A — PHP built-in server
```bash
cd ojt_tracker
php -S localhost:8000
# Open http://localhost:8000
```

### Option B — XAMPP / WAMP / Laragon
1. Copy the `ojt_tracker/` folder into your `htdocs` (XAMPP) or `www` (WAMP) directory
2. Visit `http://localhost/ojt_tracker/`

### Option C — Production server
1. Upload all files via FTP/SFTP
2. Make sure `data/` directory is writable: `chmod 755 data/`
3. Visit your domain

## Features

- Multi-user authentication (register / login / logout)
- Secure password hashing via `password_hash()`
- Log hours by date, description, time-in and time-out
- Auto-calculated hours per entry
- Dashboard with Required / Logged / Remaining stats
- Progress bar with estimated completion date
- Delete individual log entries
- Update profile name and required OJT hours
- Change password with current-password verification
- Flash notifications (success/error)
- Responsive sidebar layout

## Security Notes

- Passwords are hashed using PHP's `PASSWORD_DEFAULT` (bcrypt)
- All output is escaped with `htmlspecialchars()`
- Session-based authentication
- For production use, consider moving `data/` outside the web root
  and pointing `DATA_DIR` in `config.php` accordingly
