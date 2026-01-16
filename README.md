# POS DEWAAN - Quick Start Guide

## 🚀 How to Start the Application

Simply **double-click** on `start_pos.vbs` file.

### What happens automatically:
1. ✅ Checks if XAMPP Apache & MySQL are running
2. ✅ Starts them if needed (silently in background)
3. ✅ Opens POS application in app mode (no browser UI)
4. ✅ Starts monitoring service

### What it looks like:
- **No CMD windows** - completely hidden
- **No browser address bar** - looks like desktop software
- **Maximized window** - professional appearance

---

## 🛑 How to Close the Application

Simply **close the application window** (click X button).

### What happens automatically:
1. ✅ Application closes
2. ✅ XAMPP Apache stops automatically
3. ✅ XAMPP MySQL stops automatically
4. ✅ Monitoring service exits

---

## 📁 Files in this folder:

- **start_pos.vbs** - Main launcher (double-click this!)
- **start_pos.bat** - Backend script (don't run directly)
- **stop_xampp_on_close.vbs** - Auto-stop monitor (runs in background)
- **README.md** - This guide

---

## 💡 Pro Tip:

Create a desktop shortcut:
1. Right-click `start_pos.vbs`
2. Send to → Desktop (create shortcut)
3. Rename shortcut to "POS DEWAAN"
4. Now launch from desktop with one click! 🎯

---

## ⚙️ Technical Details:

- **Application URL**: http://localhost/POS-DEWAAN/login.php
- **Browser Mode**: Chrome App Mode (--app flag)
- **XAMPP Services**: Apache & MySQL
- **Auto-stop**: Monitors Chrome process, stops XAMPP on exit

---

**Developed by Abdul Rafay**
© 2026 POS DEWAAN
