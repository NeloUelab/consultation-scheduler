# Student Consultation Scheduler — Working Prototype (Group 11)

A web prototype of Group 11's **Student Consultation Scheduler**, built with **PHP 8 + MySQL (XAMPP)**. It follows the group's Mini SRS, Data Dictionary and the refined Figma screens, and includes the fixes for UX critique items **B-01 to B-04**.

## Requirements
- XAMPP with **Apache** and **MySQL/MariaDB** (PHP 8.0 or newer)
- A browser (Chrome, Edge or Firefox)

## Run it (about 2 minutes)
1. Copy the `consultation-scheduler` folder into `C:\xampp\htdocs\`
   (if you downloaded a ZIP, extract it so the path is `C:\xampp\htdocs\consultation-scheduler\index.php`).
2. Open the **XAMPP Control Panel** and click **Start** on **Apache** and **MySQL**.
3. Go to **http://localhost/consultation-scheduler/setup.php** and click **Install database**.
   *(Alternative: phpMyAdmin → Import → `database/consultation_scheduler_db.sql`.)*
4. Open **http://localhost/consultation-scheduler/** and sign in.

Running setup again resets the demo data. Demo dates are relative to the day you install (tomorrow, the day after, …), so the screens always have upcoming slots.

### Demo accounts
| Role | Email | Password |
|---|---|---|
| Student | `john.doe@example.com` | `student123` |
| Student | `ana.reyes@example.com` | `student123` |
| Instructor | `juan.delacruz@example.com` | `instructor123` |
| Instructor | `maria.delacruz@example.com` | `instructor123` |
| Instructor | `mark.anthony@example.com` | `instructor123` |
| Instructor (Unavailable) | `mark.zidane@example.com` | `instructor123` |

**Tip:** to see the real-time notifications (FR-004), open the student in one browser and the instructor in a private/incognito window, side by side.

## What to try
| Requirement / critique item | Where |
|---|---|
| FR-01 Login (Student / Instructor) | `login.php` |
| FR-02 / FR-003 Search instructors, see live availability | Student → Book a consultation |
| FR-03 / FR-04 Pick date (calendar), window and start/end time, with validation | Book a Slot → Confirm Booking |
| FR-05 Submit, view, edit (while Pending) and cancel requests | Student dashboard |
| FR-001 / FR-06 Instructor availability and slots | Instructor dashboard → status switch, Add slot |
| FR-002 / FR-07 Confirm, decline or cancel requests | Instructor → View consultations |
| FR-004 / NFR-001 Notifications within seconds | Toasts appear on the other user's screen (polls every 5 s) |
| **B-01** Status switch with "Your status: Available/Unavailable", colored dot, helper text, toast | Instructor dashboard |
| **B-02** Forgot password → reset code → new password → success message | Login → "Forgot password?" |
| **B-03** Decline: confirmation dialog with optional reason, loading state, toast with **Undo**, card leaves Pending | Instructor → View consultations |
| **B-04** Cancel (student or instructor): confirmation dialog, toast, status **Cancelled**, time reopens on the instructor's schedule, other side notified | Both dashboards |

> **Password reset email:** XAMPP has no mail server, so the 6-digit code is shown in a "Prototype mailbox" box on the reset screen. In a deployed system it would be emailed.

## Folder layout
```
consultation-scheduler/
├─ index.php, login.php, logout.php, settings.php
├─ forgot.php, reset.php            B-02 password recovery
├─ setup.php                        one-click database installer
├─ student/                         dashboard, instructors, book, confirm, submitted, action
├─ instructor/                      dashboard, consultations, status, action
├─ api/notifications.php            FR-004 polling endpoint
├─ includes/                        db, helpers, shared layout + dialogs
├─ assets/css/app.css, assets/js/app.js
├─ config/config.php                DB settings (root / empty password by default)
└─ database/consultation_scheduler_db.sql
```

## Database
Tables `student`, `instructor`, `availability_slot` and `consultation` keep the columns from the group's Data Dictionary. Additions needed by the prototype are marked **ADDED** in the SQL file:
- `password_hash` on `student` and `instructor` (login)
- `consultation.decline_reason` (B-03), `consultation.cancelled_by` (B-04), `created_at` / `updated_at` (Undo window, history)
- `password_reset` table (B-02) and `notification` table (FR-004)

## Troubleshooting
- **"Database not ready"** → MySQL isn't running or setup wasn't run. Start MySQL, open `setup.php`.
- **MySQL has a password or another port** → edit `config/config.php` (`DB_USER`, `DB_PASS`, `DB_PORT`).
- **Port 80 busy** → if Apache runs on 8080, use `http://localhost:8080/consultation-scheduler/`.
