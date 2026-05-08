# JuniorCode Academy — Project Report

---

## Dedication

*To my family, whose love and patience carried me through every long night and difficult moment — this work is as much yours as it is mine.*

*To my parents, who believed in me before I believed in myself, and whose sacrifices gave me the opportunity to learn, grow, and build.*

*To my friends and colleagues, who offered encouragement, laughter, and support whenever the road felt long.*

*And to every young student who will one day use a platform like this to take their first step into the world of technology — may it spark in you the same passion that drove the creation of this project.*

*This work is dedicated to all of you.*

---

## Table of Contents

1. Introduction
2. Project Objectives
3. System Overview
4. Technologies Used
5. System Architecture
6. Database Design
7. System Modules & Features
   - 7.1 Authentication System
   - 7.2 Admin Panel
   - 7.3 Teacher Portal
   - 7.4 Student Portal
8. User Interface Design
9. Security Considerations
10. Testing
11. Challenges & Solutions
12. Conclusion

---

## 1. Introduction

JuniorCode Academy is a web-based management system designed to support a coding academy that teaches programming to children and young students. The platform provides a centralized hub for administrators, teachers, and students, allowing each role to interact with the system according to their specific needs.

The system handles the full operational lifecycle of the academy: from managing users and scheduling classes, to tracking teacher earnings, publishing course materials, and enabling student access to learning resources. It is built as a multi-role web application using standard web technologies and runs on a local server environment.

---

## 2. Project Objectives

- Build a role-based web application with three distinct portals: Admin, Teacher, and Student.
- Allow administrators to manage all aspects of the academy from a single dashboard.
- Provide teachers with a read-only view of their assigned courses, classes, schedules, and earnings.
- Give students access to their class schedules, course materials, and a way to contact their teacher.
- Enable the admin to upload course PDFs and project links that are visible to teachers.
- Ensure the system is secure, with session-based authentication and role enforcement on every page.

---

## 3. System Overview

JuniorCode Academy is organized around three user roles:

| Role    | Access                                                               |
|---------|----------------------------------------------------------------------|
| Admin   | Full control: users, classes, courses, earnings, slots, reports      |
| Teacher | View-only: their classes, schedule, earnings, students, courses      |
| Student | View-only: their classes, course materials, contact form             |

The system runs on **XAMPP** (Apache + MySQL + PHP) and is accessed via a web browser at `localhost`. All pages are server-rendered PHP with Bootstrap 5 for responsive layout and Font Awesome for icons.

---

## 4. Technologies Used

| Technology       | Purpose                                               |
|------------------|-------------------------------------------------------|
| PHP 8.x          | Server-side logic, session management, database queries |
| MySQL (MariaDB)  | Relational database for all system data               |
| HTML5 / CSS3     | Page structure and styling                            |
| JavaScript (ES6) | Client-side interactivity (tab switching, modals, toggles) |
| Bootstrap 5.3    | Responsive UI components and grid system              |
| Font Awesome 6.5 | Icon library used throughout the UI                   |
| XAMPP            | Local development server (Apache + MySQL)             |
| MySQLi (PHP ext) | Prepared statements for secure database access        |

---

## 5. System Architecture

The system follows a classic **Multi-Page Application (MPA)** architecture with **Post-Redirect-Get (PRG)** pattern for form submissions to prevent duplicate submissions on page refresh.

```
Browser
  │
  ▼
Apache (XAMPP)
  │
  ├── login.php / authenticate.php   ← Entry point, session creation
  │
  ├── Admin Pages (admin_*)          ← Role: admin
  │     ├── admin_dashboard.php
  │     ├── manage_users.php / add_user.php / edit_user.php / delete_user.php
  │     ├── manage_classes.php / create_class.php / edit_class.php / delete_class.php
  │     ├── teacher_earnings.php / add_earning.php / edit_earning.php / delete_earning.php
  │     ├── courses.php / add_course.php / edit_course.php / delete_course.php
  │     ├── manage_projects.php      ← Course PDF & project link management
  │     ├── available_slots.php / save_availability.php
  │     ├── reports.php
  │     └── settings.php
  │
  ├── Teacher Pages (teacher_*)      ← Role: teacher
  │     ├── teacher_dashboard.php
  │     ├── teacher_classes.php
  │     ├── teacher_schedule.php
  │     ├── teacher_monthly_earnings.php
  │     ├── teacher_students.php
  │     ├── teacher_courses.php
  │     └── teacher_profile.php
  │
  ├── Student Pages (student_*)      ← Role: student
  │     ├── student_dashboard.php
  │     ├── student_classes.php
  │     ├── student_contact.php
  │     └── student_profile.php
  │
  └── db.php                         ← Shared database connection
```

Every protected page checks `$_SESSION["role"]` at the top. If the session is missing or the role does not match, the user is immediately redirected to `login.php`.

---

## 6. Database Design

**Database name:** `juniorcode_db2`

### Tables

#### `users`
Stores all system users (admins, teachers, students).

| Column           | Type         | Description                        |
|------------------|--------------|------------------------------------|
| id               | INT (PK, AI) | Unique user ID                     |
| username         | VARCHAR(100) | Display name                       |
| email            | VARCHAR(150) | Login email                        |
| password         | VARCHAR(255) | Bcrypt-hashed password             |
| role             | ENUM         | 'admin', 'teacher', 'student'      |
| profile_picture  | VARCHAR(255) | Filename of uploaded profile photo |

#### `classes`
Stores every scheduled class session.

| Column       | Type         | Description                          |
|--------------|--------------|--------------------------------------|
| id           | INT (PK, AI) | Unique class ID                      |
| student_name | VARCHAR(150) | Student assigned to this class       |
| teacher_name | VARCHAR(150) | Teacher assigned                     |
| teacher_id   | INT          | FK to users.id (teacher)             |
| class_date   | DATE         | Date of the class                    |
| class_time   | TIME         | Time of the class                    |
| type         | VARCHAR(50)  | Paid / Half Pay / No Pay / Demo ...  |
| zoom_link    | TEXT         | Zoom meeting URL                     |
| details      | TEXT         | Additional notes                     |

#### `teacher_earnings`
Records individual payment entries for teachers.

| Column       | Type         | Description                    |
|--------------|--------------|--------------------------------|
| id           | INT (PK, AI) | Unique record ID               |
| teacher_id   | INT          | FK to users.id                 |
| teacher_name | VARCHAR(150) | Teacher name (fallback)        |
| lesson_title | VARCHAR(255) | Description of the session     |
| amount       | DECIMAL(10,2)| Payment amount in USD          |
| lesson_date  | DATE         | Date of the paid lesson        |
| notes        | TEXT         | Optional notes                 |

#### `courses`
Stores all academy courses.

| Column      | Type         | Description                              |
|-------------|--------------|------------------------------------------|
| id          | INT (PK, AI) | Unique course ID                         |
| course_name | VARCHAR(255) | Name of the course                       |
| category    | VARCHAR(100) | e.g. Game Development, Python Introduction |
| section     | VARCHAR(50)  | 'kids', 'junior', or 'demo'              |
| age_group   | VARCHAR(50)  | Target age group                         |
| level       | VARCHAR(50)  | Beginner / Intermediate / Advanced       |
| price       | DECIMAL(10,2)| Course price                             |
| course_type | VARCHAR(50)  | 'paid' or 'demo'                         |
| status      | VARCHAR(20)  | 'active' or 'inactive'                   |
| duration    | VARCHAR(50)  | e.g. "8 weeks"                           |
| image       | TEXT         | Image path or URL                        |

#### `course_projects`
Stores project links and PDF resources linked to course categories.

| Column     | Type         | Description                              |
|------------|--------------|------------------------------------------|
| id         | INT (PK, AI) | Unique record ID                         |
| section    | VARCHAR(50)  | 'kids', 'junior', or 'demo'              |
| category   | VARCHAR(100) | Course category this project belongs to  |
| title      | VARCHAR(255) | Project title                            |
| url        | TEXT         | External project URL                     |
| image      | TEXT         | Thumbnail image path                     |
| pdf_url    | TEXT         | Uploaded PDF filename (stored locally)   |
| sort_order | INT          | Display ordering                         |

#### `teacher_availability`
Records teacher availability slots.

| Column     | Type         | Description                   |
|------------|--------------|-------------------------------|
| id         | INT (PK, AI) | Unique slot ID                |
| teacher_id | INT          | FK to users.id                |
| day        | VARCHAR(20)  | Day of the week               |
| time_slot  | VARCHAR(50)  | Time range                    |
| status     | VARCHAR(20)  | 'available' or 'unavailable'  |

---

## 7. System Modules & Features

### 7.1 Authentication System

**Files:** `login.php`, `authenticate.php`, `logout.php`

- Single login page for all user roles.
- Passwords are stored as **bcrypt hashes** (`password_hash()` / `password_verify()`).
- On successful login, `$_SESSION["role"]`, `$_SESSION["user_id"]`, and `$_SESSION["username"]` are set.
- The user is automatically redirected to the correct dashboard based on their role.
- Every protected page enforces role-based access at the top of the PHP file; unauthorized access redirects to `login.php`.
- `logout.php` destroys the session and redirects to the login page.

---

### 7.2 Admin Panel

The admin has the most comprehensive access in the system. All admin pages are protected by `$_SESSION["role"] === "admin"`.

#### Dashboard (`admin_dashboard.php`)
Displays a summary of system activity:
- Total users, students, teachers
- Total classes scheduled
- Total teacher earnings
- Recent activity feeds (recent users, classes, earnings)

#### User Management (`manage_users.php`, `add_user.php`, `edit_user.php`, `delete_user.php`)
- View all registered users in a searchable table.
- Add new users with role assignment (admin, teacher, student).
- Edit user details including name, email, role, and password.
- Delete users with a confirmation step.

#### Class Management (`manage_classes.php`, `create_class.php`, `edit_class.php`, `delete_class.php`)
- View all scheduled classes across all teachers and students.
- Create new class sessions with student name, teacher assignment, date, time, type, and Zoom link.
- Edit existing classes including updating the Zoom meeting URL.
- Delete classes with a confirmation prompt.
- Class types include: Paid, Half Pay, No Pay, Demo Enrolled, Demo Pending.

#### Teacher Earnings (`teacher_earnings.php`, `add_earning.php`, `edit_earning.php`, `delete_earning.php`)
- Record and manage payment entries for each teacher.
- Each record includes lesson title, date, amount, and notes.
- Filter earnings by teacher name.

#### Course Management (`courses.php`, `add_course.php`, `edit_course.php`, `delete_course.php`)
- Courses are organized into three sections: **Kids**, **Junior**, and **Demo**.
- Within Kids and Junior, courses are grouped by category (e.g., Game Development, Python Introduction, Virtual Machine).
- Admin can add, edit, or delete any course.
- Each course has: name, category, section, age group, level, price, type (paid/demo), status (active/inactive), duration, and an optional image.

#### Project Links & PDF Upload (`manage_projects.php`)
- For each course category, the admin can add project links that appear to teachers.
- Each project entry has: title, thumbnail image, external project URL, and an **uploaded PDF file**.
- PDF files are uploaded directly from the admin's computer and stored in `uploads/pdfs/`.
- Teachers can click "Check Course" to open the PDF and "View Project" to open the external URL.
- Projects can be edited (with PDF replacement) or deleted.

#### Available Slots (`available_slots.php`, `save_availability.php`)
- Admin can view and manage teacher availability schedules.
- Availability is stored per teacher, per day and time slot.

#### Reports (`reports.php`)
- System-wide statistics: total users, classes, earnings.
- Recent user registrations and class activity.

#### Settings (`settings.php`)
- Academy-level configuration options for the admin.

---

### 7.3 Teacher Portal

Teacher pages are protected by `$_SESSION["role"] === "teacher"`. All views are **read-only** — teachers cannot modify system data.

#### Dashboard (`teacher_dashboard.php`)
- Personalized welcome with the teacher's name and profile picture.
- **Stats overview:** Paid classes, Demo classes (enrolled / pending / other), Conversion rate.
- **Today's Schedule:** List of classes scheduled for the current day with Zoom join links.
- **Courses section:** Full read-only view of all academy courses (Kids, Junior, Demo) with project links and PDF resources.

#### My Classes (`teacher_classes.php`)
- Table of all upcoming classes assigned to this teacher.
- Shows student name, date, time, class type, details, and a Zoom join button.

#### My Schedule (`teacher_schedule.php`)
- Schedule view of upcoming sessions from today onwards.

#### My Earnings (`teacher_monthly_earnings.php`)
- Summary of total earnings and paid session count.
- Detailed table of individual payment records.

#### My Students (`teacher_students.php`)
- List of all unique students assigned to this teacher based on class records.

#### Courses (`teacher_courses.php`)
- Full course browser matching the admin's course layout:
  - **Kids / Junior / Demo** tab bar.
  - Category dropdown (Select Module) within Kids and Junior tabs.
  - Project links (PDF + View Project buttons) displayed per category.
  - Full course table for each category.

#### Profile & Settings (`teacher_profile.php`)
- View and update profile information.
- Upload or remove a profile picture.
- Custom confirmation modal (no browser popups) for photo removal.

---

### 7.4 Student Portal

Student pages are protected by `$_SESSION["role"] === "student"`.

#### Dashboard (`student_dashboard.php`)
- Overview of the student's upcoming classes and today's schedule.

#### My Classes (`student_classes.php`)
- Table of all classes assigned to this student, with date, time, type, and Zoom link.

#### Contact (`student_contact.php`)
- Form for the student to send a message or question to their teacher.

#### Profile (`student_profile.php`)
- View and update profile details and profile picture.
- Same custom photo removal modal as the teacher profile.

---

## 8. User Interface Design

The UI follows a consistent design language across all pages:

- **Color scheme:** Deep navy sidebar (`#0f172a` to `#172554`) with a clean white/light blue main content area.
- **Typography:** Arial / Helvetica sans-serif throughout.
- **Cards:** Rounded panel cards (`border-radius: 22px`) with subtle box-shadow for content sections.
- **Sidebar:** Fixed-position (teacher/student) or sticky (admin) navigation sidebar with smooth collapse animation triggered by a hamburger button (☰).
- **Badges:** Color-coded pill badges for class types (green = paid, yellow = demo) and statuses (green = active, red = inactive).
- **Modals:** Custom HTML/CSS modals instead of browser `confirm()` dialogs for destructive actions (e.g., removing a profile photo).
- **Responsive:** Bootstrap grid and media queries ensure the layout adapts to smaller screens.
- **Consistent topbar:** Each page has a gradient hero/topbar showing the page title and the logged-in user's name.

---

## 9. Security Considerations

| Threat              | Mitigation Applied                                                   |
|---------------------|----------------------------------------------------------------------|
| Unauthorized access | Role check (`$_SESSION["role"]`) at the top of every protected page |
| SQL injection       | All queries use **MySQLi prepared statements** with bound parameters |
| Password storage    | Passwords stored as **bcrypt hashes** (`password_hash()`)            |
| XSS (output)        | All user data is escaped with `htmlspecialchars()` before rendering  |
| File upload abuse   | PDF uploads validated by file extension before saving                |
| CSRF (forms)        | Post-Redirect-Get (PRG) pattern prevents duplicate form submissions  |
| Session fixation    | Session is regenerated on login (`session_regenerate_id()`)          |

---

## 10. Testing

The system was tested manually across all three user roles:

| Test Area                        | Result  |
|----------------------------------|---------|
| Login with correct credentials   | Pass    |
| Login with wrong credentials     | Pass    |
| Admin: add / edit / delete user  | Pass    |
| Admin: create and edit class     | Pass    |
| Admin: add course with image     | Pass    |
| Admin: upload PDF for project    | Pass    |
| Admin: edit project, replace PDF | Pass    |
| Teacher: view courses (all tabs) | Pass    |
| Teacher: view today's schedule   | Pass    |
| Teacher: upload profile photo    | Pass    |
| Teacher: remove profile photo    | Pass    |
| Student: view classes            | Pass    |
| Student: contact form            | Pass    |
| Sidebar hamburger toggle         | Pass    |
| Unauthorized page access         | Redirect to login — Pass |
| SQL injection attempt in forms   | Blocked by prepared statements — Pass |

---

## 11. Challenges & Solutions

### Challenge 1: Hardcoded Course Categories
**Problem:** The initial implementation fetched courses using 7 hardcoded category names. Any course the admin added with a different category name was silently missing from all views.

**Solution:** Replaced all hardcoded fetches with a single `SELECT * FROM courses WHERE section = ?` query, then grouped results dynamically by category in PHP using `array_keys()`. HTML element IDs are generated using a slug function (`preg_replace('/[^a-z0-9]+/', '_', strtolower($category))`), making the system fully dynamic.

---

### Challenge 2: Course Views Hidden Behind Broken Navigation
**Problem:** The teacher dashboard had a courses section that was permanently hidden (`display:none`) because the JavaScript navigation system used a wrong CSS selector (`.nav-item` instead of `.nav-link-custom`), so clicking the sidebar never triggered the courses view.

**Solution:** Decoupled the courses section from the broken SPA navigation system. The courses view is now rendered directly on the page, always visible. Tab switching (Kids/Junior/Demo) uses independent inline JavaScript functions that work without any global navigation state.

---

### Challenge 3: Browser `confirm()` Dialogs
**Problem:** The "Remove Photo" buttons used browser-native `confirm()` dialogs which look unprofessional and cannot be styled to match the application.

**Solution:** Built custom HTML/CSS modal dialogs with a trash icon, Cancel and Confirm buttons, and a dark overlay background. Forms were given explicit IDs so the modal's confirm button can programmatically submit only that form.

---

### Challenge 4: PDF Upload vs URL Input
**Problem:** The project link form originally accepted only an external URL for PDFs. This required teachers to use external hosting (e.g., Google Drive) for all course materials, adding friction and dependency on third-party services.

**Solution:** Changed the PDF field to `<input type="file" accept=".pdf">` with `enctype="multipart/form-data"`. The server validates the extension, moves the file to `uploads/pdfs/`, and stores only the filename in the database. Editing preserves the existing file if no new one is uploaded, and replaces (and deletes) the old file if a new one is provided.

---

### Challenge 5: Post-Redirect-Get for Profile Photo Actions
**Problem:** Separate `if` blocks for the upload and delete POST handlers meant both could run on the same request, causing conflicts (e.g., a photo upload being followed immediately by a deletion check).

**Solution:** Refactored handlers into a strict `if/elseif` chain so only one action executes per request. After each action, a `header("Location: page.php?status=...")` redirect is issued, and the success/error message is shown on the redirected GET request.

---

## 12. Conclusion

JuniorCode Academy is a complete, functional web-based management system for a coding academy. It successfully achieves its core objectives: a secure role-based login system, a powerful admin panel with full CRUD capabilities, a comprehensive teacher portal with read-only access to their data and course materials, and a simple student portal for accessing class information.

The system demonstrates key web development concepts including:
- **MVC-style separation** of data fetching (PHP) and presentation (HTML/CSS)
- **Prepared statements** for safe database access
- **Role-based access control** via PHP sessions
- **File upload handling** with server-side validation
- **Responsive design** with Bootstrap and custom CSS
- **Progressive enhancement** with JavaScript used only for UI interactivity

The platform can be extended in the future with features such as email notifications, a student progress tracking module, online payment integration, and a mobile application interface.

---

*Project: JuniorCode Academy Web Management System*
*Technology Stack: PHP, MySQL, HTML, CSS, JavaScript, Bootstrap 5*
*Server Environment: XAMPP (Apache + MySQL)*
