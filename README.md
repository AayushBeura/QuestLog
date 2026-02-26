# QuestLog

QuestLog is an end-to-end travel itinerary planning platform built with PHP, MySQL, and vanilla HTML/CSS/JS, designed to run on XAMPP. It enables tourists to search and book flights, trains, and hotels, manage trip itineraries, and view boarding passes. Administrators have a dedicated dashboard for managing users, inventory, and bookings.

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 7+ (REST API with PDO/MySQL) |
| Database | MySQL (via XAMPP) |
| Frontend | HTML5, CSS3, Vanilla JavaScript |
| Libraries | GSAP, Locomotive Scroll (landing page animations) |
| Server | Apache (XAMPP) |

## Project Structure

```
QuestLog/
├── api/
│   ├── auth/
│   │   ├── login.php              # User login with session management
│   │   ├── signup.php             # New user registration
│   │   └── logout.php             # Session teardown
│   ├── tourist/
│   │   ├── booking.php            # Create and view bookings
│   │   ├── payment.php            # Process payments
│   │   ├── cancel_booking.php     # Cancel bookings with inventory restore
│   │   ├── search.php             # Search hotels and transports
│   │   ├── availability.php       # Check real-time availability
│   │   ├── itinerary.php          # Create and manage trip itineraries
│   │   ├── profile.php            # View and update user profile
│   │   └── ticket.php             # Fetch ticket and boarding pass data
│   └── admin/
│       ├── users.php              # Manage users (block/unblock/delete)
│       ├── manage_hotels.php      # CRUD for hotel inventory
│       ├── manage_transports.php  # CRUD for transport inventory
│       └── manage_bookings.php    # View and update all bookings
├── config/
│   └── db.php                     # Database connection (PDO)
├── includes/
│   ├── auth.php                   # Session auth helpers
│   └── utils.php                  # JSON response, sanitization, CORS, CSRF
├── assets/                        # Images and media
├── index.html                     # Landing page with login/signup
├── tourist-dashboard.html         # Tourist dashboard
├── admin-dashboard.html           # Admin dashboard
├── payment-checkout.html          # Secure checkout page
├── boarding_pass.html             # Printable boarding pass / booking ticket
├── locations.js                   # Location autocomplete for city inputs
├── styles.css                     # Global stylesheet
├── schema.sql                     # Full database schema with seed data
└── test_apis.php                  # API test suite
```

## Setup

1. **Install XAMPP** and start Apache and MySQL.
2. **Clone the repository** into your XAMPP htdocs directory:
   ```
   git clone https://github.com/AayushBeura/QuestLog.git C:\xampp\htdocs\QuestLog
   ```
3. **Create the database** by opening phpMyAdmin and importing `schema.sql`, or by running:
   ```sql
   CREATE DATABASE IF NOT EXISTS questlog_db;
   USE questlog_db;
   -- Then paste the contents of schema.sql
   ```
4. **Verify the configuration** in `config/db.php` to ensure the MySQL host and port match your environment (default port: `3306`).
5. **Open** `http://localhost/QuestLog` in your browser.
6. **Default admin credentials**: `admin@questlog.com` / `password`

---

## Features

### Tourist

- **Search** for flights, trains, and hotels with location autocomplete
- **Book** transports and hotels with real-time availability checks
- **Pay** via simulated checkout (Credit Card, UPI, Net Banking)
- **View** printable boarding passes and booking tickets
- **Create** trip itineraries with attached bookings
- **Manage** profile information

### Admin

- **Manage users** — view, block/unblock, and delete accounts
- **Manage hotels** — add, update, and delete hotel inventory
- **Manage transports** — add, update, and delete flights and trains
- **Manage bookings** — view all platform bookings and update status (confirm, cancel, complete)

---

## API Endpoints

### Authentication

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/signup.php` | Register a new tourist account |
| POST | `/api/auth/login.php` | Login (returns session and redirect URL) |
| POST | `/api/auth/logout.php` | Destroy session |

### Tourist (requires login)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/tourist/search.php?type=hotel&location=...` | Search hotels |
| GET | `/api/tourist/search.php?type=flight&source=...&destination=...&date=...` | Search transports |
| GET | `/api/tourist/availability.php?type=hotel&id=...` | Check availability |
| GET, POST | `/api/tourist/booking.php` | List or create bookings |
| POST | `/api/tourist/payment.php` | Process payment |
| POST | `/api/tourist/cancel_booking.php` | Cancel a booking |
| GET | `/api/tourist/ticket.php?booking_id=...` | Get ticket details |
| GET, PUT | `/api/tourist/profile.php` | View or update profile |
| GET, POST | `/api/tourist/itinerary.php` | List or create itineraries |

### Admin (requires admin role)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET, PUT, DELETE | `/api/admin/users.php` | Manage users |
| GET, POST, PUT, DELETE | `/api/admin/manage_hotels.php` | Manage hotels |
| GET, POST, PUT, DELETE | `/api/admin/manage_transports.php` | Manage transports |
| GET, PUT | `/api/admin/manage_bookings.php` | Manage bookings |

---

## Bug Fixes and Updates

### Critical

| # | Issue | File(s) | Resolution |
|---|-------|---------|------------|
| 1 | Payment float comparison rejected valid payments | `payment.php` | Removed fragile frontend amount check; server uses DB amount as single source of truth |
| 2 | "Bus" transport type not in DB ENUM | `schema.sql`, `search.php`, `manage_transports.php`, dashboards, `index.html` | Removed Bus from all dropdowns and validation; app supports Flight and Train only |
| 3 | Duplicate `name="state"` in signup form | `index.html` | Removed `name` attribute from both state fields; JS reads the active field explicitly |
| 4 | `htmlspecialchars()` corrupted data before DB storage | `utils.php` | Removed from `sanitizeInput()`; prepared statements handle injection prevention |
| 5 | Admin booking cancel did not update `payment_status` | `manage_bookings.php` | Cancel now sets `payment_status = 'Refunded'`, matching tourist cancel flow |

### Medium

| # | Issue | File(s) | Resolution |
|---|-------|---------|------------|
| 6 | CORS headers sent after session destroy | `logout.php` | Moved `handleCors()` before session teardown |
| 7 | Blocked-user login revealed valid credentials | `login.php` | Check blocked status before password verification |
| 8 | Country code saved instead of full name | `index.html` | Added country name resolution map before form submission |
| 9 | Availability check logic mismatched booking logic | `availability.php` | Simplified to read `rooms_available` directly, matching `booking.php` |
| 10 | Frontend payment amount trusted by server | `payment.php` | Removed frontend amount comparison entirely |
| 11 | `sanitizeInput()` applied to numeric fields | `manage_hotels.php`, `manage_transports.php` | Split into text and numeric field handling with proper casting |

### Low

| # | Issue | File(s) | Resolution |
|---|-------|---------|------------|
| 12 | Boarding pass showed hardcoded fake data while loading | `boarding_pass.html` | Replaced with placeholder loading text |
| 13 | No CSRF token protection | `utils.php` | Added `generateCsrfToken()` and `validateCsrfToken()` utilities |
| 14 | Admin dashboard accessible to non-admin users | `admin-dashboard.html` | Added `checkAdminAuth()` guard with redirect on 401/403 |
| 15 | CORS wildcard incompatible with session auth | `utils.php` | Restricted to `localhost` origins with credentials support |
| 16 | Modal close handler targeted wrong element | `index.html` | Bound click listener to `.modal-blur` overlay |
| 17 | Autocomplete race condition on field validation | `locations.js` | Changed to `blur` event with increased delay |

### Input Validation (Feb 2026)

| # | Issue | File(s) | Resolution |
|---|-------|---------|------------|
| 18 | Booking accepted with zero or negative guest count | `booking.php` | Added validation requiring `guests_count >= 1` |
| 19 | Hotel booking accepted with past, inverted, or zero-length date range | `booking.php` | Added checks: `start_date >= today`, `end_date > start_date`, both required |
| 20 | Cancellation allowed after service date had passed | `cancel_booking.php` | Added guard comparing service date against current date |
| 21 | Test suite lacked assertions and pass/fail tracking | `test_apis.php` | Rewrote with structured assertions, 14 tests (8 core, 6 validation), and summary output |

---

## Testing

Run the API test suite from the command line:

```
php test_apis.php
```

The test suite covers:

- **Core API tests**: Signup, login, profile fetch, hotel search, admin CRUD, valid booking creation
- **Validation tests**: Zero/negative guest count, inverted dates, same-day dates, past dates, past-booking cancellation

All tests require XAMPP Apache and MySQL to be running.

---

## License

This project is for educational purposes.
