# 🚀 Fashion Shines POS

[![PHP Version](https://img.shields.io/badge/PHP-7.4+-777bb4.svg?style=flat-square&logo=php)](https://www.php.net/)
[![Platform](https://img.shields.io/badge/Platform-XAMPP-orange.svg?style=flat-square)](https://www.apachefriends.org/)
[![Storage](https://img.shields.io/badge/Storage-CSV%20%2F%20Excel-green.svg?style=flat-square)](https://en.wikipedia.org/wiki/Comma-separated_values)

**Fashion Shines POS** is a modern, lightweight, and professional Point of Sale & Inventory Management System designed for efficiency and ease of use. It features a desktop-like experience within a web-based framework, utilizing CSV files for ultra-portable and lightning-fast data management.

---

## ✨ Key Features

- **📦 Inventory Management**: Track products, stock levels, categories, and units.
- **💰 Smart POS Terminal**: Fast billing interface with dynamic calculations.
- **📉 Real-time Analytics**: Dashboard with sales trends, low stock alerts, and expiry notifications.
- **📑 Comprehensive Ledgers**: Detailed transaction history for both Customers and Dealers.
- **⏰ Expiry Alerts**: Automatically notifies you of products nearing their expiry date.
- **🔄 Backup & Restore**: Secure your data with easy-to-use export/import functionality.
- **🚀 Desktop Experience**: Launch as a standalone application without browser UI clutter.
- **🌐 Remote Ready**: Built-in support for live server sharing (via ngrok).

---

## 🛠️ Technology Stack

- **Backend**: PHP (7.4+)
- **Storage**: CSV (Flat-file database - No SQL server setup required for storage logic, though it runs on XAMPP)
- **Frontend**: HTML5, CSS3 (Tailwind CSS), JavaScript (Vanilla)
- **Icons**: Font Awesome 6
- **Scripts**: VBScript & Batch for seamless Windows integration

---

## 🚀 Installation & Quick Start

### Prerequisites
- [XAMPP](https://www.apachefriends.org/index.html) (Apache & MySQL)

### Setup
1. Clone or download this repository to `C:\xampp\htdocs\POS-DEWAAN`.
2. Ensure XAMPP is installed in the default directory (`C:\xampp`).

### Running the App
Simply **double-click** on the `start_pos.vbs` file.

**What happens automatically:**
- ✅ Checks if XAMPP Apache & MySQL are running.
- ✅ Starts them silently in the background if needed.
- ✅ Opens the POS application in **App Mode** (no browser UI).
- ✅ Starts a monitoring service to stop XAMPP when you close the app.

> [!TIP]
> **Desktop Shortcut**: Right-click `start_pos.vbs` → Send to → Desktop. Rename it to "Fashion Shines POS".

---

## 🔒 Security & Performance
- **Session Managed**: Secure login system with `requireLogin()` checks.
- **Atomic CSV Operations**: Exclusive file locking (`LOCK_EX`) prevents data corruption during simultaneous writes.
- **Auto-Migration**: System automatically updates CSV headers if new features are added.

---

## 👨‍💻 Developer Information

**Developed by Abdul Rafay**
*Primary Educator | Web Developer | Content Writer*

- 📧 **Email**: abdulrafehqazi@gmail.com
- 🌐 **Web**: [knowledgeshout.com](https://www.knowledgeshout.com)
- 💼 **LinkedIn**: [abdulrafayqazi](https://linkedin.com/in/abdulrafayqazi)
- 🐙 **GitHub**: [rafayqazi](https://github.com/rafayqazi)

---

© 2026 Fashion Shines POS. All rights reserved.
