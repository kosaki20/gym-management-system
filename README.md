# Boiyets Fitness Gym Management System

A comprehensive, web-based management platform built for gym administrators, trainers, staff, and members. Features include member registration and attendance tracking via QR codes, POS counter sales, equipment monitoring & maintenance logs, personalized meal and workout plan assignments, revenue reporting, feedback management, and real-time client-trainer chat.

---

## 🛠️ Technology Stack

- **Backend**: Plain PHP 8+ (Procedural & OOP with PDO / mysqli)
- **Frontend**: Vanilla HTML5, Vanilla JavaScript, Vanilla CSS (`assets/css/gym_layout.css`)
- **Database**: MySQL / MariaDB
- **Libraries**:
  - `dompdf/` - PDF report generation
  - `phpqrcode/` - QR Code generation for client & walk-in check-ins

---

## 🔐 Security Architecture

This repository enforces a strict separation between **web-accessible assets** and **private application resources**:

```
gym-management-system/
├── config/
│   └── config.php            <-- Private DB connection, PDO class & CSRF helpers (NOT web-accessible)
├── database/
│   └── init_db.sql           <-- Database schema & initialization data (NOT web-accessible)
├── public_html/              <-- SERVED WEB ROOT (Apache/Hostinger Document Root)
│   ├── assets/               <-- CSS & JS assets
│   ├── chat_uploads/         <-- User media uploads
│   ├── dompdf/               <-- Dompdf library
│   ├── includes/             <-- Shared UI partials (header, nav, footer)
│   ├── phpqrcode/            <-- QR Code generator library
│   ├── profile_pictures/     <-- User profile avatars
│   ├── qrcodes/              <-- System generated QR code images
│   ├── index.php             <-- Web entry point
│   └── ... (all 73 front-facing PHP page scripts)
├── .env.example              <-- Environment template
├── .gitignore                <-- Git ignore configuration
└── README.md                 <-- Setup & project documentation
```

### Key Security Measures
- **Web Root Isolation**: Only files inside `public_html/` are exposed to the public web server.
- **Protected Configuration**: `config/config.php` and SQL dumps reside outside `public_html/` and cannot be accessed or downloaded via HTTP URLs.
- **CSRF Token Validation**: Centralized CSRF generation (`ensureCsrfToken()`) and verification (`verifyCsrfToken()`) for state-changing forms.

---

## 🚀 Setup Instructions

### Prerequisites
- PHP 8.0 or higher with `pdo_mysql` and `gd` extensions enabled.
- MySQL 5.7+ or MariaDB 10.4+.
- Web server (Apache, Nginx, or XAMPP).

### 1. Clone the Repository
```bash
git clone https://github.com/kosaki20/gym-management-system.git
cd gym-management-system
```

### 2. Configure Web Server Document Root
Set your web server's **Document Root** to the `public_html/` folder inside the repository:
- **XAMPP / Apache VirtualHost Example**:
  ```apache
  <VirtualHost *:80>
      ServerName gym.local
      DocumentRoot "C:/xampp/htdocs/gym-management-system/public_html"
      <Directory "C:/xampp/htdocs/gym-management-system/public_html">
          AllowOverride All
          Require all granted
      </Directory>
  </VirtualHost>
  ```
- **cPanel / Hostinger**: Deploy the contents of `public_html/` to your server's `public_html` directory, and place `config/` and `database/` one level above `public_html`.

### 3. Database Setup
1. Create a MySQL database (e.g. `gym_db`).
2. Import the initial database schema:
   ```bash
   mysql -u root -p gym_db < database/init_db.sql
   ```

### 4. Environment Configuration
Copy `.env.example` to `.env` at the repository root and adjust your database connection settings if needed:
```bash
cp .env.example .env
```

Default fallback settings (if `.env` is omitted):
```env
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=gym_db
```

---

## 💻 Running Locally

### Option A: Using XAMPP
1. Place the `gym-management-system` folder in `C:\xampp\htdocs\`.
2. Start Apache and MySQL in XAMPP Control Panel.
3. Access the application at: `http://localhost/gym-management-system/public_html/index.php`.

### Option B: PHP Built-in Server (Development Only)
Run the built-in PHP development server targeting `public_html/` as the document root:
```bash
php -S localhost:8000 -t public_html
```
Then visit `http://localhost:8000/index.php` in your browser.
