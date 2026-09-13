# Govt Polytechnic Institute (GPI) - Official Website

A complete, modern, professional, and fully responsive educational website for **Govt Polytechnic Institute (GPI)**, built strictly using **HTML5, CSS3, and Vanilla JavaScript** without any external frameworks (no Bootstrap, no Tailwind CSS, no React/Vue).

---

## 🏛 Project Overview

The website represents a prestigious government technical/polytechnic educational institution operating under the Directorate of Technical Education and accredited by the **Punjab Board of Technical Education (PBTE)** and the **National Vocational & Technical Training Commission (NAVTTC)**.

### Core Design Principles
- **Institutional & Trustworthy**: Deep navy primary colors (`#0a1d3d`, `#0f2b5c`), warm academic gold accents (`#d97706`, `#f59e0b`), and clean slate backgrounds.
- **Academic & Professional**: Clear typographic hierarchy using Google Fonts `Plus Jakarta Sans`, balanced white space, and authentic technical iconography.
- **Fully Responsive**: Optimized for 320px, 375px, 480px, 768px, 1024px, 1280px, and 1440px+ screens with no horizontal scrolling or media clipping.
- **Accessible (a11y)**: Semantic HTML5 landmark tags (`<header>`, `<nav>`, `<main>`, `<section>`, `<article>`, `<footer>`), keyboard-navigable dialogs and menus, visible focus states, form labels with `for` attributes, and `prefers-reduced-motion` compliance.

---

## 📂 File & Directory Structure

```
gpit/
│
├── index.html              # Homepage (Hero, Stats, About preview, Programs, Why Us, Facilities, Announcements, CTA, Footer)
├── about.html              # About Institute (History, Mission, Vision, Core Values, Leadership, Faculty Directory, Accreditations)
├── programs.html           # Technical Programs (6 Programs with curriculum, key skills, and career paths)
├── admissions.html         # Admissions (Workflow, Eligibility, and Complete Multi-section Application Form with client-side validation)
├── gallery.html            # Campus Gallery (Category filter tabs: Campus, Labs, Workshops, Classrooms, Events, Students + Lightbox)
├── contact.html            # Contact & Location (Contact info, office hours, validated inquiry form, styled map locator)
│
├── css/
│   └── style.css           # Unified design system, CSS custom properties, responsive typography, components, animations
│
├── js/
│   └── script.js           # Mobile drawer navigation, sticky navbar, counter animations, form validators, gallery lightbox
│
├── images/
│   ├── logo/
│   │   └── logo.svg        # Official vector emblem of Govt Polytechnic Institute
│   ├── campus/
│   │   ├── campus_main.svg # Architectural campus grounds illustration
│   │   ├── computer_lab.svg
│   │   ├── electrical_lab.svg
│   │   ├── mechanical_workshop.svg
│   │   ├── civil_lab.svg
│   │   ├── library.svg
│   │   ├── classrooms.svg
│   │   └── student_activities.svg
│   ├── faculty/
│   │   ├── principal.svg
│   │   ├── vice_principal.svg
│   │   ├── hod_electrical.svg
│   │   ├── hod_civil.svg
│   │   ├── hod_mechanical.svg
│   │   └── hod_computer.svg
│   ├── programs/
│   │   ├── electrical.svg
│   │   ├── civil.svg
│   │   ├── mechanical.svg
│   │   ├── computer.svg
│   │   ├── electronics.svg
│   │   └── autodiesel.svg
│   └── gallery/
│       ├── annual_sports.svg
│       ├── robotics_expo.svg
│       ├── convocation.svg
│       ├── tech_exhibition.svg
│       ├── electronics_workshop.svg
│       └── welding_workshop.svg
│
└── README.md
```

---

## 🚀 Key Features

### 1. Navigation & Header
- **Top Information Bar**: Displays government credentials, telephone, official email, and working hours.
- **Sticky Navbar**: Subtle elevation on scroll with active state tracking based on current URL.
- **Mobile Drawer Menu**: Accessible hamburger toggle with slide-in drawer, keyboard ESC closing, backdrop click dismissal, and auto-close on link click.

### 2. Homepage (`index.html`)
- **Hero Section**: High-impact headline, supporting text, dual CTAs ("Explore Programs" & "Apply Now"), and dynamic floating statistics badges.
- **Animated Statistics Counters**: `IntersectionObserver` triggered smooth counter animations (25+ Years, 10+ Programs, 1,200+ Students, 50+ Faculty).
- **About Preview**: Two-column layout showcasing institutional history, workshop training, and accreditation.
- **Programs Showcase**: Cards for 6 major DAE technologies with department tags and duration pills.
- **Why Choose GPI**: Six institutional strengths highlighting experienced faculty, modern labs, practical training, and job placement cell.
- **Campus Facilities**: Visual showcase of computer lab, heavy machine shop, electrical bench, civil lab, digital library, and student societies.
- **Latest Announcements**: Bulletin cards with modal dialog previews for notifications, exam schedules, and scholarship dates.

### 3. About Page (`about.html`)
- **Institutional History**: Tracing 25+ years of excellence under the Directorate of Technical Education.
- **Mission & Vision**: Distinctive cards highlighting the core purpose of technical skill development.
- **Core Values**: 6 foundational values (Excellence, Integrity, Innovation, Discipline, Practical Learning, Student Success).
- **Principal's Address**: Official message, photo portrait, credentials, and quote.
- **Faculty Directory**: Profiles for Principal, Vice Principal, and Heads of Departments (Electrical, Civil, Mechanical, Computer Technology, and Registrar).

### 4. Programs Page (`programs.html`)
- Catalog of all 6 flagship 3-year Programs:
  1. **Diploma in Electrical Engineering**
  2. **Diploma in Civil Engineering**
  3. **Diploma in Mechanical Engineering**
  4. **Diploma in Computer Technology (CIT)**
  5. **Diploma in Electronics Engineering**
  6. **Diploma in Auto & Diesel Technology**
- Department, duration, eligibility, key skills, and career opportunities per program.
- 40:60 theoretical to practical curriculum breakdown across 1st, 2nd, and 3rd year capstone.

### 5. Admissions Page (`admissions.html`)
- **6-Step Admission Workflow**: Eligibility check &rarr; Program selection &rarr; Application &rarr; Document verification &rarr; Merit list &rarr; Fee submission.
- **Eligibility Criteria**: Matriculation Science requirements, age limits, and quota allocations.
- **Complete Admission Application Form**:
  - Personal Information (Name, Father's Name, DOB, Gender, CNIC/B-Form with auto-masking, Phone, Email, Address, City).
  - Academic Information (Qualification, Year, Board, Total Marks, Obtained Marks, and auto-calculated Percentage).
  - Program Selection (Primary choice, shift preference: Morning/Evening).
  - Document Upload UI (Passport photo, matric marksheet, CNIC copy with file size check and name display).
  - Declaration Checkbox.
  - **Comprehensive JavaScript Validation**: Real-time inline feedback, error highlighting, and smooth scroll to first invalid field.
  - **Success Modal & Receipt**: Generates unique Application Reference ID (e.g., `GPI-2026-8492`), applicant summary, and print receipt option (`window.print()`).

### 6. Gallery Page (`gallery.html`)
- **Category Filter Tabs**: Smooth filtering across "All", "Campus", "Laboratories", "Workshops", "Classrooms", "Events", and "Students".
- **Interactive Lightbox Viewer**:
  - Next / Previous buttons.
  - Keyboard navigation (Left arrow, Right arrow, Escape).
  - Mobile touch swipe navigation (swipe left/right).
  - Image counter (`Photo X of Y`) and detailed caption text.

### 7. Contact Page (`contact.html`)
- Official contact numbers, departmental emails, physical address, and visiting hours.
- Interactive Contact Inquiry Form with client-side validation and interactive toast confirmation.
- Styled Google Maps locator placeholder with coordinates and instructions for embedding production iframe.

---

## 🛠 Local Setup & Running

Because this project is built entirely using vanilla HTML5, CSS3, and JavaScript, it does not require any build tools, compilers, or package installations.

### Option 1: Direct File Access
Simply double-click `index.html` to open it directly in any modern web browser (Google Chrome, Microsoft Edge, Mozilla Firefox, Apple Safari).

### Option 2: Local HTTP Server
Run a lightweight local server from the project directory:

**Using Python:**
```bash
python -m http.server 8000
```
Then open: `http://localhost:8000`

**Using Node.js:**
```bash
npx serve .
```

---

## 📱 Browser & Device Compatibility
- Chrome, Edge, Firefox, Safari, Opera (latest 2 versions).
- Tested across mobile (320px, 375px, 480px), tablet (768px), laptop (1024px), and desktop (1280px, 1440px+).

---

## 📜 License & Accreditation Notice
This project is developed for **Govt Polytechnic Institute**. All rights reserved &copy; 2026.
Accredited by the Punjab Board of Technical Education (PBTE) & NAVTTC.
