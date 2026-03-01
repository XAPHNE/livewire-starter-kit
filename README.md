# Livewire Starter Kit

A robust, feature-rich Laravel application scaffold built to kickstart your next ambitious project. It comes fully equipped with a powerful authentication layer, dynamic user management, complex role & permission assignments, and hardcore security out of the box.

## 🚀 Tech Stack

- **Framework**: [Laravel](https://laravel.com/)
- **Authentication Engine**: [Laravel Fortify](https://laravel.com/docs/fortify)
- **Frontend Magic**: [Livewire](https://livewire.laravel.com/) & [Livewire Volt](https://livewire.laravel.com/docs/volt)
- **Styling & UI Components**: [Tailwind CSS](https://tailwindcss.com/) & [Flux UI](https://fluxui.dev/)
- **Interactive JavaScript**: [Alpine.js](https://alpinejs.dev/)
- **Data Tables**: [PowerGrid](https://livewire-powergrid.com/)
- **Roles & Permissions**: [Spatie Laravel-Permission](https://spatie.be/docs/laravel-permission)
- **Audit Trail**: [Laravel Auditing](https://laravel.com/docs/auditing)
- **Authentication Logs**: [Laravel Authentication Logs](https://laravel.com/docs/authentication-logs)

---

## 🔒 Features

### 1. Hardened Authentication & Security

- **Fortify Powered**: Headless auth integrated into custom Volt & Flux components.
- **Dynamic Password Policy**: Admin-configurable min/max length, required character types (Upper/Lower/Numbers/Symbols), and expiry rotation.
- **Security Throttling**: UI-driven control over Login, OTP, and Password Reset attempts with IP-based "Hybrid" lockdown.
- **Two-Factor Authentication (2FA)**: Mandatory Email OTP or TOTP Authenticator support with recovery code management.
- **Input Sanitization**: Global `AllowedCharactersRule` to prevent script injection across all fields.

### 2. User & Access Management

- **PowerGrid Tables**: Interactive, filterable, and exportable user/role/permission lists.
- **Spatie Roles & Permissions**: Multi-role assignment with granular permission mapping.
- **Pillbox Selection**: Custom UI component for efficient bulk-assigning permissions.
- **Soft Deletes**: Accountable deletion tracking (who deleted whom) across the system.

### 3. Audit & Compliance

- **Activity Tracking**: Automatic logging of all database mutations via `laravel-auditing`.
- **Authentication History**: Tracks every login, logout, and failed attempt for forensic security analysis.
- **Unified Log Hub**: Tabbed interface for admins to review all security events in one place.

### 4. Session & Session Tiers

- **Timeout Management**: Adjustable inactivity timeouts enforced globally.
- **Concurrency Control**: Evict old sessions automatically to enforce device limits per user.
- **Session Tiers**: Assign users to tiers (Basic, Pro, Admin) to grant varying levels of concurrent session permissions.

---

## 🛠️ Setup Instructions

**Requirements**: PHP >= 8.2, Composer, Node.js + NPM.

**1. Installation**

```bash
git clone https://github.com/XAPHNE/livewire-starter-kit.git
cd starter-kit
composer install
npm install
```

**2. Environment**

```bash
cp .env.example .env
php artisan key:generate
```

_Update your `.env` with your database and mail credentials._

**3. Database & Seeding**

```bash
# This creates all tables and seeds default roles, permissions, settings, and tiers.
php artisan migrate --seed
```

**4. Development**

```bash
# Run the vite server for styles
npm run dev

# Run the app (or use Herd/Valet)
php artisan serve
```

---

## 📂 Key Components

- **Volt Pages**: `resources/views/pages/admin/` (Single-file Livewire components)
- **Settings Registry**: `app/Models/Setting.php` (DB-backed persistent settings)
- **Middleware**: `app/Http/Middleware/` (Session & Password enforcement)
- **Audit Hub**: `resources/views/pages/admin/⚡audit-hub.blade.php`
- **Validation**: `app/Concerns/` (Reusable security rules)

---

## 🛡️ License

This starter kit is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
