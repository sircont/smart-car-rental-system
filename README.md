# Smart Car Rental System

**Online Vehicle Rental System** — A complete car rental management solution developed by **Abubakari Zeidu** (Student ID: 20220412010) as a final year project.

## Project Overview

Smart Car Rental is a comprehensive web-based vehicle rental system that allows customers to browse, book, and pay for vehicles online. The system includes three user interfaces:

- **Customer Portal**: Browse cars, check availability, make bookings, process payments
- **Staff Portal**: Manage maintenance tasks, view bookings, handle vehicle check-ins/outs
- **Admin Portal**: Full system control, manage vehicles, users, staff, reports

---

## Features

### Customer Features
- ✅ User registration and login (secure password hashing)
- ✅ Browse vehicles by branch, category, fuel type, transmission
- ✅ Real-time availability checker (AJAX API)
- ✅ Online booking with date selection
- ✅ Payment placeholder (Flutterwave/Paystack ready via config)
- ✅ Booking history and status tracking
- ✅ User profile management & change password
- ✅ Password recovery (forgot / reset)
- ✅ Contact form
- ✅ Feedback and testimonials (approval in admin)

### Staff Features
- ✅ Staff dashboard with task overview
- ✅ Maintenance task management (view, update status)
- ✅ View upcoming bookings
- ✅ Vehicle check-in / check-out recording
- ✅ Daily checklist system

### Admin Features
- ✅ Complete dashboard with analytics (Chart.js)
- ✅ Revenue reports and charts
- ✅ Vehicle management (CRUD, fuel type, transmission)
- ✅ Category & branch management
- ✅ Maintenance task creation & assignment to staff
- ✅ User management
- ✅ Staff management with permissions
- ✅ Booking management and conflict detection
- ✅ Payment monitoring
- ✅ Contact query management (status: new/read/replied/archived)
- ✅ Testimonial moderation (approve/unapprove)
- ✅ System activity logs

### Technical Features
- ✅ Secure authentication with session management (customer / staff / admin)
- ✅ CSRF protection
- ✅ Input validation and sanitization
- ✅ Prepared SQL statements (prevents injection)
- ✅ Password hashing (bcrypt)
- ✅ Responsive design (Bootstrap 5)
- ✅ AJAX for real-time updates
- ✅ PWA (manifest + service worker)
- ✅ Config placeholders for Flutterwave, Paystack, Twilio, SMTP
- ✅ Activity logging (admin actions)
- ✅ Error handling

**Legend:** ✅ Implemented

---

## Technology Stack

| Layer      | Technology |
|-----------|------------|
| Frontend  | HTML5, CSS3, JavaScript, Bootstrap 5, AJAX |
| Backend   | PHP 8.2 (OOP) |
| Database  | MySQL 8.0 |
| Payment   | Flutterwave API, Paystack API *(to integrate)* |
| SMS       | Twilio API *(to integrate)* |
| Email     | PHPMailer/SMTP *(to integrate)* |
| Charts    | Chart.js |
| Maps      | Google Maps API *(optional)* |

---

## Installation Guide

### Prerequisites
- XAMPP / WAMP / MAMP with **PHP 8.2+** and **MySQL 8.0+**
- Composer *(optional)*
- Git *(optional)*

### Step 1: Get the project
```bash
git clone https://github.com/yourusername/smart-car-rental.git
# OR extract the zip file to your htdocs folder (e.g. C:\xampp\htdocs\Car_Rental_System)
```

### Step 2: Database setup
1. Start **Apache** and **MySQL** in XAMPP.
2. **New install:** Create the database and tables by either:
   - **Option A:** Run the installer in browser:  
     `http://localhost/Car_Rental_System/public/install.php`  
     Then delete `public/install.php` for security.
   - **Option B:** Import in phpMyAdmin: open `database/schema.sql` and run it.
   - **Option C:** Command line:  
     `mysql -u root -p < database/schema.sql`
3. **Existing database (from before full spec):** Run the migration to add staff, maintenance, contact, testimonials, activity log, vehicle fuel/transmission:  
     `mysql -u root -p car_rental < database/migrations/001_full_spec.sql`  
     (If you get "Duplicate column" for fuel_type/transmission, those columns already exist; run the rest of the migration manually if needed.)

### Step 3: Configuration
- Edit `config/database.php` if needed (host, database name, user, password).
- Set `config/app.php` → `url` to your base URL, e.g.  
  `http://localhost/Car_Rental_System/public`

### Step 4: Access the system
- **Site:** `http://localhost/Car_Rental_System/public/`
- **Admin:** `http://localhost/Car_Rental_System/public/admin/`  
  Default admin: **email** `admin@carrental.gh` · **password** `password`
- **Staff:** `http://localhost/Car_Rental_System/public/staff/`  
  Default staff: **email** `staff@carrental.gh` · **password** `password` *(create staff users in DB or via admin; set role = staff)*  
*(Change all default passwords in production.)*

---

## Project Structure

```
Car_Rental_System/
├── config/
│   ├── app.php          # App URL, timezone, CSRF key
│   └── database.php     # DB credentials
├── database/
│   └── schema.sql       # MySQL schema (users, branches, vehicles, bookings, payments)
├── src/
│   ├── Auth.php         # Login, roles, requireAdmin
│   ├── Csrf.php         # CSRF token and validation
│   ├── Database.php     # PDO wrapper, prepared statements
│   └── Helpers.php      # baseUrl, flash, redirect, e()
├── public/
│   ├── index.php        # Home + featured vehicles
│   ├── cars.php         # Browse cars (filter by branch/category)
│   ├── book.php         # Booking form (dates, total)
│   ├── payment.php      # Payment (Flutterwave/Mobile Money placeholder)
│   ├── my-bookings.php  # Customer booking history
│   ├── login.php
│   ├── register.php
│   ├── logout.php
│   ├── install.php      # One-time DB setup (delete after use)
│   ├── api/
│   │   └── availability.php   # GET ?vehicle_id=&pickup_date=&return_date=
│   └── admin/           # Dark-theme dashboard
│       ├── index.php    # Dashboard, charts, recent bookings
│       ├── vehicles.php, vehicle-edit.php
│       ├── bookings.php
│       ├── branches.php, branch-edit.php
│       ├── categories.php, category-edit.php
│       ├── reports.php
│       ├── layout/      # Sidebar header & footer
│       └── assets/
│           └── admin.css
├── bootstrap.php
└── README.md
```

---

## Config (optional)

- **Email:** `config/mail.php` — set `MAIL_*` env or edit for PHPMailer/SMTP.
- **SMS:** `config/sms.php` — set `TWILIO_*` for Twilio.
- **Payment:** `config/payment.php` — set `FLUTTERWAVE_*` or `PAYSTACK_*` and wire in `public/payment.php`.

---

## Roadmap (completed)

1. **Staff portal** — Add `staff` role, staff login, staff dashboard (maintenance, check-in/out, checklist).
2. **User & staff management** — Admin pages to list/edit users and staff, permissions.
3. **Payments** — Integrate Flutterwave/Paystack in `payment.php`; payment history/monitoring in admin.
4. **Communications** — PHPMailer for email; Twilio for SMS (booking confirmations, alerts).
5. **Customer profile** — Profile page, edit name/phone, change password, password recovery.
6. **Feedback & testimonials** — Tables + customer submission + admin moderation.
7. **Contact queries** — Contact form + admin “Contact query management” page.
8. **Activity logs** — Log table and admin view for key actions.
9. **PWA** — Manifest and service worker for installable/mobile experience.
10. **Vehicle filters** — Expose fuel type and transmission in DB and filters (brand already in vehicles).

---

## License & Author

Final year project · **Abubakari Zeidu** · Student ID: 20220412010  
University of Technology and Applied Sciences, Navrongo.
