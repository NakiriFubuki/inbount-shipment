# 📦 **Product Inbound Shipment Counting**

A full-featured **Inbound Shipment Counting Record System** built with **PHP, MySQL, HTML, CSS & JavaScript** — streamline warehouse inbound checks, compare forecast vs counted quantities, and collaborate across admin & warehouse users.

<p align="center">
  <img src="https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP" />
  <img src="https://img.shields.io/badge/MySQL-Database-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL" />
  <img src="https://img.shields.io/badge/XAMPP-Ready-FB7A24?style=for-the-badge&logo=xampp&logoColor=white" alt="XAMPP" />
  <img src="https://img.shields.io/badge/License-MIT-green?style=for-the-badge" alt="License" />
</p>

<p align="center">
  <img src="docs/screenshots/04-admin-dashboard.png" alt="Admin Dashboard Preview" width="820"/>
</p>

---

## ✨ **Features**

- 🔐 **Role-Based Auth** — Separate **Admin** / **Warehouse User** login, registration, show/hide password, and forgot-password reset links  
- 📥 **Inbound Records** — Admins create shipments with date, product name, shipment number, cartons & quantity  
- 🧮 **Counting Workflow** — Users record counted cartons/quantity with start–end time and optional counting timer  
- 🟢🔴 **Live Status Badges** — Not started · Quantity matches · Quantity mismatch · Completed  
- 🔍 **Search & Filters** — Search by product / shipment # · Today · By date · Last 7 days · All  
- 👥 **User Management** — Register, edit, delete, and search warehouse users from the admin panel  
- 📧 **Email Settings** — Configure mail for password reset (PHPMailer)  
- 🎨 **Modern UI** — Soft gradient theme, glass navbar, responsive cards & forms  

---

## 🏗️ **Tech Stack**

| **Category**       | **Technology**                          |
|--------------------|-----------------------------------------|
| 🖥️ **Frontend**    | HTML5, CSS3, JavaScript                 |
| 🔙 **Backend**     | PHP 7.4+ (PDO, sessions)                |
| 🗄️ **Database**    | MySQL / MariaDB                         |
| 🧰 **Environment** | XAMPP (Apache + MySQL)                  |
| 📬 **Mail**        | PHPMailer                               |
| 🔒 **Security**    | Prepared statements, XSS escaping, password hashing |

---

## 🚀 **Getting Started**

### 1️⃣ Prerequisites
- XAMPP (Apache + MySQL)
- PHP 7.4+
- Composer (for PHPMailer)
- A modern browser

### 2️⃣ Setup
1. Place the project in `C:\xampp\htdocs\inbount shipment` (or any `htdocs` folder)
2. Start **Apache** and **MySQL** in XAMPP
3. Install dependencies:
   ```bash
   composer install
   ```
4. Open the installer:
   - `http://localhost/inbount%20shipment/install.php`
5. Fill in DB credentials + admin account → finish install  
6. **Delete `install.php`** after setup for security  
7. Login at `http://localhost/inbount%20shipment/`

### 3️⃣ Manual install (optional)
1. Import `database/schema.sql` in phpMyAdmin  
2. Copy `config/database.example.php` → `config/database.local.php` and set credentials  
3. Copy `config/email.example.php` → `config/email.local.php` if using mail  

### 4️⃣ Default accounts (example)
| Role  | Username | Password  |
|-------|----------|-----------|
| Admin | `admin`  | set during install (e.g. `admin123`) |
| User  | register via **Register** or Admin → User Management |

---

## 📁 **Project Structure**

```text
inbount-shipment/
├── admin/                 # Admin dashboard, users, email settings
├── auth/                  # Login, register, forgot / reset password
├── assets/css|js          # Styles & scripts
├── config/                # DB, app, email config (+ examples)
├── database/              # SQL schema
├── docs/screenshots/      # README screenshots
├── includes/              # Auth, layout, helpers, mailer
├── lang/                  # Language strings
├── user/                  # Warehouse counting dashboard
├── vendor/                # Composer packages (PHPMailer)
├── index.php              # Entry → login
├── install.php            # One-click installer (delete after use)
├── CONTRIBUTING.md
├── LICENSE
└── README.md
```

---

## 🖼️ **Project Screenshots**

<table>
  <tr>
    <td align="center">
      <img src="docs/screenshots/01-login-admin.png" alt="Admin Login" width="400"/><br/>
      <b>🔐 Admin Login</b>
    </td>
    <td align="center">
      <img src="docs/screenshots/02-login-user.png" alt="User Login" width="400"/><br/>
      <b>👤 User Login</b>
    </td>
  </tr>
  <tr>
    <td align="center">
      <img src="docs/screenshots/04-admin-dashboard.png" alt="Admin Dashboard" width="400"/><br/>
      <b>📊 Admin Dashboard</b>
    </td>
    <td align="center">
      <img src="docs/screenshots/05-admin-add.png" alt="Add Inbound" width="400"/><br/>
      <b>📥 Add Inbound Record</b>
    </td>
  </tr>
  <tr>
    <td align="center">
      <img src="docs/screenshots/06-admin-users.png" alt="User Management" width="400"/><br/>
      <b>👥 User Management</b>
    </td>
    <td align="center">
      <img src="docs/screenshots/07-user-dashboard.png" alt="Counting Dashboard" width="400"/><br/>
      <b>🧮 Counting Dashboard</b>
    </td>
  </tr>
  <tr>
    <td align="center">
      <img src="docs/screenshots/08-register.png" alt="Register" width="400"/><br/>
      <b>📝 Register</b>
    </td>
    <td align="center">
      <img src="docs/screenshots/09-forgot-password.png" alt="Forgot Password" width="400"/><br/>
      <b>🔑 Forgot Password</b>
    </td>
  </tr>
  <tr>
    <td align="center" colspan="2">
      <img src="docs/screenshots/03-install.png" alt="Installer" width="400"/><br/>
      <b>⚙️ One-Click Installer</b>
    </td>
  </tr>
</table>

---

## ✅ **How to Test**

1. Login as **Admin** → add an inbound shipment (product, shipment #, cartons, quantity)  
2. Open **User Management** → register a warehouse user  
3. Login as **User** → open pending shipments → start counting / save a counting record  
4. Back in Admin → check status badges (match / mismatch / completed)  
5. Try **Search**, date filters, edit/delete records, and **Forgot password**  

---

## 🤝 **Contributing**

Contributions are welcome! Please read **[CONTRIBUTING.md](CONTRIBUTING.md)** for guidelines.

---

## 📄 **License**

This project is licensed under the **[MIT License](LICENSE)**.

---

## 👨‍💻 **Author**

**Eng Choon Hao**

Copyright © 2026 Eng Choon Hao. All Rights Reserved.

---

✨ Feel free to explore, contribute, and enhance the project! 🚀

⭐ If you find this project helpful, don't forget to **star** the repository! 🌟
